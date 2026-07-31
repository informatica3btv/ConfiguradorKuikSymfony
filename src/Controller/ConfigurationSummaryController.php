<?php

namespace App\Controller;

use App\Entity\Configuration;
use App\Repository\ConfigurationRepository;
use App\Service\ConfigurationService;
use App\Service\NavOfferService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ConfigurationSummaryController extends AbstractController
{
    private ConfigurationService $configService;
    private NavOfferService $navOfferService;

    public function __construct(ConfigurationService $configService, NavOfferService $navOfferService)
    {
        $this->configService   = $configService;
        $this->navOfferService = $navOfferService;
    }

    /**
     * @Route("/configuration/{id}/summary", name="configuration_summary", methods={"GET"})
     */
    public function summary(
        int $id,
        ConfigurationRepository $repo
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configuration = $repo->find($id);
        if (!$configuration) {
            return $this->redirectToRoute('projects_list');
        }

        $p = $configuration->getProject();

        $payload = $configuration->getDecodedPayload();

        $payloadPrepared = $this->configService->prepareSummaryPayload($payload);

        return $this->render('configurations/summary.html.twig', [
            'project'       => $p,
            'configuration' => $configuration,
            'payload'       => $payloadPrepared,
        ]);
    }

    /**
     * @Route("/btv-debug", name="btv_debug", methods={"GET"})
     */
    public function btvDebug(\App\Service\BtvApiService $btvApi): JsonResponse
    {
        $result = $btvApi->getProductInfo('05828', 1);
        return new JsonResponse([
            'result' => $result,
            'error'  => $result === null ? 'getProductInfo returned null — check var/log/prod.log' : null,
        ]);
    }

    /**
     * @Route("/configuration/{id}/product-table", name="configuration_product_table", methods={"GET"})
     */
    public function productTable(
        int $id,
        ConfigurationRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $configuration = $repo->find($id);
        if (!$configuration) {
            throw $this->createNotFoundException('Configuration not found');
        }

        $payload = $configuration->getDecodedPayload();

        $isClosed = in_array($configuration->getStatus(), [
            Configuration::STATUS_ACCEPTED,
            Configuration::STATUS_CLOSED,
        ], true);

        if ($isClosed && isset($payload['_acceptedProductTable'])) {
            $table = $payload['_acceptedProductTable'];
        } elseif ($isClosed) {
            $snapshot = $this->navOfferService->buildProductSnapshot($payload);
            $payload['_acceptedProductTable'] = $snapshot;
            $configuration->setPayload(json_encode($payload));
            $em->persist($configuration);
            $em->flush();
            $table = $snapshot;
        } else {
            $table = $this->configService->buildProductTable($payload);
        }

        $table['instalacionPrecio']    = (float) ($payload['_instalacion_precio'] ?? 0);
        $table['instalacionIva']       = (bool)  ($payload['_instalacion_iva']    ?? false);
        $table['instalacionReference'] = $this->configService->getInstalacionReference();
        $table['descuento']            = (float) ($payload['_descuento']           ?? 0);
        $table['manualLines']          = $payload['_manualLines'] ?? [];
        $table['configurationId']      = $configuration->getId();

        return $this->render('configurations/ajax.html.twig', $table);
    }
}
