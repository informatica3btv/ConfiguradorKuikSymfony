<?php

namespace App\Controller;

use App\Entity\Configuration;
use App\Entity\ProductTypeCategory;
use App\Repository\ConfigurationRepository;
use App\Service\ConfigurationService;
use App\Service\NavOfferService;
use App\Service\ProductTypeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class ConfigurationPdfController extends AbstractController
{
    private ConfigurationService $configService;
    private NavOfferService $navOfferService;
    private ProductTypeResolver $productTypeResolver;

    public function __construct(
        ConfigurationService $configService,
        NavOfferService $navOfferService,
        ProductTypeResolver $productTypeResolver
    ) {
        $this->configService      = $configService;
        $this->navOfferService    = $navOfferService;
        $this->productTypeResolver = $productTypeResolver;
    }

    /**
     * @Route("/configuration/{id}/pdf", name="configuration_pdf", methods={"GET"})
     */
    public function pdf(
        int $id,
        ConfigurationRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configuration = $repo->find($id);

        if (!$configuration) {
            throw $this->createNotFoundException('Configuration not found');
        }

        $project = $configuration->getProject();

        if (!$project || $project->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $payloadArr = $configuration->getDecodedPayload() ?? [];
        if (!isset($payloadArr['_acceptedProductTable'])) {
            $payloadArr['_acceptedProductTable'] = $this->navOfferService->buildProductSnapshot($payloadArr);
        }
        if (!isset($payloadArr['_pdf_fecha'])) {
            $payloadArr['_pdf_fecha'] = (new \DateTimeImmutable())->format('d/m/Y');
        }
        $configuration->setPayload(json_encode($payloadArr));

        $configuration->setStatus(Configuration::STATUS_CLOSED);
        $em->persist($configuration);
        $em->flush();

        $payload = $payloadArr;

        $projectDir = $this->getParameter('kernel.project_dir');

        $screenBase64  = $this->loadBase64($projectDir . '/public/assets/pantalla.png',        'image/png');
        $armBlanco     = $this->loadBase64($projectDir . '/public/assets/brazo_blanco.png',    'image/jpeg');
        $armPlata      = $this->loadBase64($projectDir . '/public/assets/brazo_plata.png',     'image/jpeg');
        $armNegro      = $this->loadBase64($projectDir . '/public/assets/brazo_negro.png',     'image/jpeg');
        $buzonBase64   = $this->loadBase64($projectDir . '/public/assets/buzon_kuik.png',      'image/png');
        $legBase64     = $this->loadBase64($projectDir . '/public/assets/pie_negro.jpg',       'image/jpeg');
        $logoBase64    = $this->loadBase64($projectDir . '/public/assets/Kuik Smart Lockers Azul.png', 'image/png');

        $mbGroupLocalPath = realpath(__DIR__ . '/../../public/assets/buzon_agrupacion.jpg');
        $mbGroupBase64    = ($mbGroupLocalPath && file_exists($mbGroupLocalPath))
            ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($mbGroupLocalPath))
            : null;

        $productTable = $payload['_acceptedProductTable'] ?? $this->configService->buildProductTable($payload);

        [$sizeCategoryLabels, $categoryOrder] = $this->buildCategoryGrouping(
            array_keys($productTable['products'] ?? []),
            $em
        );

        $html = $this->renderView('pdf/configuration_summary.html.twig', [
            'project'         => $project,
            'configuration'   => $configuration,
            'payload'         => $payload,
            'public_dir'      => $projectDir . '/public',
            'screen_base64'   => $screenBase64,
            'arm_blanco'      => $armBlanco,
            'arm_plata'       => $armPlata,
            'arm_negro'       => $armNegro,
            'buzon_base64'    => $buzonBase64,
            'mb_group_base64' => $mbGroupBase64,
            'leg_base64'      => $legBase64,
            'logo_base64'     => $logoBase64,
            'products'        => $productTable['products'],
            'productInfo'     => $productTable['productInfo'],
            'sizeCounts'      => $productTable['sizeCounts'],
            'sizeCategoryLabels' => $sizeCategoryLabels,
            'categoryOrder'      => $categoryOrder,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('configuracion_%d.pdf', $configuration->getId());

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => (new ResponseHeaderBag())
                    ->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename),
            ]
        );
    }

    private function loadBase64(string $path, string $mime): ?string
    {
        if (!file_exists($path)) {
            return null;
        }
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }

    /**
     * Para cada `size` de la tabla de productos, resuelve su categoría de
     * agrupación (o "Sin categoría" si no tiene ninguna asignada) y devuelve
     * también el orden en que deben mostrarse las categorías en el PDF.
     *
     * @param string[] $sizes
     * @return array{0: array<string,string>, 1: string[]}
     */
    private function buildCategoryGrouping(array $sizes, EntityManagerInterface $em): array
    {
        $uncategorizedLabel = 'Sin categoría';

        $typeCategories = $em->getRepository(ProductTypeCategory::class)->findAllIndexedByTypeKey();

        $sizeCategoryLabels = [];
        $categoryPositions = [];

        foreach ($sizes as $size) {
            $typeKey = $this->productTypeResolver->resolve($size);
            $typeCategory = $typeCategories[$typeKey] ?? null;
            $category = $typeCategory ? $typeCategory->getCategory() : null;

            $label = $category ? $category->getName() : $uncategorizedLabel;
            $sizeCategoryLabels[$size] = $label;

            if (!array_key_exists($label, $categoryPositions)) {
                $categoryPositions[$label] = $category ? $category->getPosition() : PHP_INT_MAX;
            }
        }

        asort($categoryPositions);
        $categoryOrder = array_keys($categoryPositions);

        return [$sizeCategoryLabels, $categoryOrder];
    }
}
