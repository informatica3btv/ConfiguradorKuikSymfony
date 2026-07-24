<?php

namespace App\Service;

use App\Entity\Project;
use Symfony\Component\Security\Core\Security;

class NavOfferService
{
    private ConfigurationService $configService;
    private BtvApiService $btvApi;
    private Security $security;

    public function __construct(ConfigurationService $configService, BtvApiService $btvApi, Security $security)
    {
        $this->configService = $configService;
        $this->btvApi        = $btvApi;
        $this->security      = $security;
    }

    public function buildProductSnapshot(array $payload): array
    {
        $table = $this->configService->buildProductTable($payload);
        $normalized = [];
        foreach ($table['products'] as $key => $product) {
            $normalized[$key] = (is_object($product) && method_exists($product, 'getReference'))
                ? ['reference' => $product->getReference()]
                : $product;
        }
        $table['products'] = $normalized;
        return $table;
    }

    public function createNavOffer(array $snapshot, Project $project, string $codCliente, array $payload = []): ?array
    {
        $items   = [];
        $cartId  = 1;
        $products    = $snapshot['products']    ?? [];
        $sizeCounts  = $snapshot['sizeCounts']  ?? [];
        $productInfo = $snapshot['productInfo'] ?? [];

        foreach ($products as $key => $product) {
            $reference = is_array($product) ? ($product['reference'] ?? null) : $product;
            if (!$reference) {
                continue;
            }
            $quantity = (int) ($sizeCounts[$key] ?? 1);
            $name     = $productInfo[$key]['_descripcion'] ?? $reference;

            $items[] = [
                'cartId'                   => $cartId,
                'type'                     => 1,
                'id'                       => $cartId,
                'productCode'              => $reference,
                'name'                     => $name,
                'productQuantity'          => $quantity,
                'productDescription'       => '',
                'discount'                 => '',
                'price'                    => '',
                'productTotalPrice'        => 0,
                'productSellingPrice'      => 0,
                'productDiscountPerc1'     => '',
                'productDiscountPerc2'     => 0,
                'productItemPrice'         => 0,
                'productDisponibility'     => '',
                'productDisponibilityDate' => '',
                'comments'                 => '',
                'errorMessage'             => '',
            ];
            $cartId++;
        }

        $instalacionPrecio = $payload['_instalacion_precio'] ?? 0;
        if ($instalacionPrecio > 0) {
            $instalacionRef = $this->configService->getInstalacionReference();
            $items[] = [
                'cartId'                   => $cartId,
                'type'                     => 1,
                'id'                       => $cartId,
                'productCode'              => $instalacionRef,
                'name'                     => 'Instalación',
                'productQuantity'          => 1,
                'productDescription'       => '',
                'discount'                 => '',
                'price'                    => '',
                'productTotalPrice'        => 0,
                'productSellingPrice'      => 0,
                'productDiscountPerc1'     => '',
                'productDiscountPerc2'     => 0,
                'productItemPrice'         => 0,
                'productDisponibility'     => '',
                'productDisponibilityDate' => '',
                'comments'                 => '',
                'errorMessage'             => '',
            ];
        }

        if (empty($items)) {
            return null;
        }

        $user  = $this->security->getUser();
        $email = $user instanceof \App\Entity\User ? $user->getEmail() : '';

        $orderData = [
            'codCliente'               => $codCliente,
            'suplantado'               => true,
            'emailTradeRepresentative' => 'logsistemas.it@btv.es',
            'email'                    => 'logsistemas.it@btv.es',
            'codOrder'                 => 0,
            'codCenter'                => 1,
            'codSendAddress'           => false,
            'codSeller'                => $this->btvApi->getCodSeller(),
            'addressName'              => $project->getClientName() ?? '',
            'receiver'                 => null,
            'receiverPhone'            => $project->getPhone() ?? null,
            'street'                   => $project->getAddress() ?? '',
            'street2'                  => '',
            'postalCode'               => '',
            'locality'                 => $project->getCity() ?? '',
            'region'                   => '',
            'country'                  => 'ES',
            'codGroup'                 => false,
            'authorizationNumber'      => 0,
            'sellingPrice'             => '',
            'comments'                 => '',
            'purchaseOrder'            => '',
            'requestType'              => 'Grabación',
            'documentType'             => 'Oferta',
            'SusMedios'                => 0,
            'items'                    => $items,
        ];

        return $this->btvApi->createOffer($orderData);
    }
}
