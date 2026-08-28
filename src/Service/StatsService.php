<?php

namespace App\Service;

use App\Entity\Configuration;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Estadísticas para el dashboard de administración. Todo se calcula al
 * vuelo a partir de Project/Configuration — no hay tablas de agregados.
 */
class StatsService
{
    private EntityManagerInterface $em;
    private ConfigurationService $configService;

    public function __construct(EntityManagerInterface $em, ConfigurationService $configService)
    {
        $this->em            = $em;
        $this->configService = $configService;
    }

    public function getStats(): array
    {
        $projectRepo = $this->em->getRepository(Project::class);
        $configRepo  = $this->em->getRepository(Configuration::class);

        $totalProjects    = (int) $projectRepo->count([]);
        $acceptedProjects = (int) $projectRepo->count(['status' => Project::STATUS_ACCEPTED]);
        $rejectedProjects = (int) $projectRepo->count(['status' => Project::STATUS_REJECTED]);
        $openProjects     = $totalProjects - $acceptedProjects - $rejectedProjects;

        $totalConfigs = (int) $configRepo->count([]);
        $acceptedConfigurations = $configRepo->findBy(['status' => Configuration::STATUS_ACCEPTED]);

        $prices = [];
        foreach ($acceptedConfigurations as $configuration) {
            $total = $this->computeConfigurationTotal($configuration);
            if ($total !== null) {
                $prices[] = $total;
            }
        }

        $typeBreakdown         = $this->computeTypeBreakdown($configRepo->findAll());
        $acceptanceByTypeCols  = $this->computeAcceptanceByTypeAndColumns();

        return [
            'totalProjects'        => $totalProjects,
            'openProjects'         => $openProjects,
            'acceptedProjects'     => $acceptedProjects,
            'rejectedProjects'     => $rejectedProjects,
            'pctOpen'              => $this->pct($openProjects, $totalProjects),
            'pctAccepted'          => $this->pct($acceptedProjects, $totalProjects),
            'pctRejected'          => $this->pct($rejectedProjects, $totalProjects),

            'totalConfigs'         => $totalConfigs,
            'avgConfigsPerProject' => $totalProjects > 0 ? round($totalConfigs / $totalProjects, 2) : 0.0,

            'acceptedConfigsCount' => count($acceptedConfigurations),
            'avgAcceptedPrice'     => count($prices) > 0 ? array_sum($prices) / count($prices) : null,
            'minAcceptedPrice'     => count($prices) > 0 ? min($prices) : null,
            'maxAcceptedPrice'     => count($prices) > 0 ? max($prices) : null,
            'pricedAcceptedCount'  => count($prices),

            'typeBreakdown'        => $typeBreakdown,
            'acceptanceByTypeCols' => $acceptanceByTypeCols,
        ];
    }

    /**
     * Cuenta las configuraciones por "type" (home / profesional / lo que
     * haya en el payload), con su porcentaje sobre el total.
     *
     * @param Configuration[] $configurations
     */
    private function computeTypeBreakdown(array $configurations): array
    {
        $counts = [];
        foreach ($configurations as $configuration) {
            $payload = $configuration->getDecodedPayload() ?? [];
            $type = trim((string) ($payload['type'] ?? ''));
            $key  = $type !== '' ? $type : 'sin_tipo';
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $total = count($configurations);
        $rows  = [];
        foreach ($counts as $type => $count) {
            $rows[] = [
                'type'  => $type,
                'count' => $count,
                'pct'   => $this->pct($count, $total),
            ];
        }

        usort($rows, static fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return $rows;
    }

    /**
     * Para cada proyecto ya decidido (aceptado o rechazado), mira sus
     * configuraciones, las clasifica por (tipo, rango de columnas) y les
     * calcula el precio — para ver, por ejemplo, a qué precio se suelen
     * rechazar los "home" de 7-10 columnas, y cuál es el precio más alto
     * que aun así se ha aceptado en ese mismo grupo (techo orientativo).
     */
    private function computeAcceptanceByTypeAndColumns(): array
    {
        $projects = $this->em->getRepository(Project::class)->findBy([
            'status' => [Project::STATUS_ACCEPTED, Project::STATUS_REJECTED],
        ]);
        $configRepo = $this->em->getRepository(Configuration::class);

        $buckets = [];
        foreach ($projects as $project) {
            $isAccepted = $project->getStatus() === Project::STATUS_ACCEPTED;

            foreach ($configRepo->findBy(['project' => $project]) as $configuration) {
                $payload = $configuration->getDecodedPayload() ?? [];
                $type    = trim((string) ($payload['type'] ?? '')) ?: 'sin_tipo';
                $columns = $this->countColumns($payload);
                $range   = $this->columnRange($columns);
                $price   = $this->computeConfigurationTotal($configuration);

                $key = $type . '|' . $range;
                if (!isset($buckets[$key])) {
                    $buckets[$key] = [
                        'type'           => $type,
                        'range'          => $range,
                        'accepted'       => 0,
                        'rejected'       => 0,
                        'acceptedPrices' => [],
                        'rejectedPrices' => [],
                    ];
                }

                $buckets[$key][$isAccepted ? 'accepted' : 'rejected']++;
                if ($price !== null) {
                    $buckets[$key][$isAccepted ? 'acceptedPrices' : 'rejectedPrices'][] = $price;
                }
            }
        }

        $rows = [];
        foreach ($buckets as $bucket) {
            $total = $bucket['accepted'] + $bucket['rejected'];
            $acceptedPrices = $bucket['acceptedPrices'];
            $rejectedPrices = $bucket['rejectedPrices'];

            $rows[] = [
                'type'              => $bucket['type'],
                'range'             => $bucket['range'],
                'accepted'          => $bucket['accepted'],
                'rejected'          => $bucket['rejected'],
                'total'             => $total,
                'pctAccepted'       => $this->pct($bucket['accepted'], $total),
                'avgAcceptedPrice'  => $acceptedPrices ? array_sum($acceptedPrices) / count($acceptedPrices) : null,
                'maxAcceptedPrice'  => $acceptedPrices ? max($acceptedPrices) : null,
                'avgRejectedPrice'  => $rejectedPrices ? array_sum($rejectedPrices) / count($rejectedPrices) : null,
                'minRejectedPrice'  => $rejectedPrices ? min($rejectedPrices) : null,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return [$a['type'], $a['range']] <=> [$b['type'], $b['range']];
        });

        return $rows;
    }

    private function countColumns(array $payload): int
    {
        $groups = $payload['groups'] ?? [];
        if (!is_array($groups) || !$groups) {
            $columns = $payload['columns'] ?? [];
            return is_array($columns) ? count($columns) : 0;
        }

        $count = 0;
        foreach ($groups as $group) {
            if (is_array($group)) {
                $count += count($group);
            }
        }
        return $count;
    }

    private function columnRange(int $columns): string
    {
        if ($columns <= 3) return '1-3';
        if ($columns <= 6) return '4-6';
        if ($columns <= 10) return '7-10';
        if ($columns <= 20) return '11-20';
        return '21+';
    }

    private function pct(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }

    /**
     * Reproduce el cálculo de total que ya se hace en las plantillas
     * (ajax.html.twig / PDF): suma de productos + instalación + líneas
     * manuales, aplica IVA y descuento si procede.
     */
    private function computeConfigurationTotal(Configuration $configuration): ?float
    {
        $payload = $configuration->getDecodedPayload() ?? [];

        $table = $payload['_acceptedProductTable']
            ?? $this->configService->buildProductTable($payload);

        $products    = $table['products'] ?? [];
        $productInfo = $table['productInfo'] ?? [];
        $sizeCounts  = $table['sizeCounts'] ?? [];

        if (!$products) {
            return null;
        }

        $grandTotal = 0.0;
        foreach ($products as $size => $product) {
            if (!$product) {
                continue;
            }

            $info  = $productInfo[$size] ?? null;
            $count = (float) ($sizeCounts[$size] ?? 0);

            if (is_array($info) && isset($info['PrecioVenta']) && !is_array($info['PrecioVenta'])) {
                $priceStr = str_replace(',', '.', str_replace('.', '', (string) $info['PrecioVenta']));
                $grandTotal += (float) $priceStr * $count;
            }
        }

        foreach ($payload['_manualLines'] ?? [] as $line) {
            $grandTotal += (float) ($line['uds'] ?? 0) * (float) ($line['precio'] ?? 0);
        }

        $instalacionPrecio = (float) ($payload['_instalacion_precio'] ?? 0);
        $instalacionIva    = (bool)  ($payload['_instalacion_iva'] ?? false);
        $descuento         = (float) ($payload['_descuento'] ?? 0);

        $subtotal        = $grandTotal + $instalacionPrecio;
        $subtotalConDto  = $subtotal - ($subtotal * $descuento / 100);

        return $instalacionIva ? $subtotalConDto * 1.21 : $subtotalConDto;
    }
}
