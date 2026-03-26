<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class BtvApiService
{
    private $cachedToken = null;
    private $httpClient;
    private $baseUrl;
    private $username;
    private $password;

    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        string $username,
        string $password
    ) {
        $this->httpClient = $httpClient;
        $this->baseUrl    = $baseUrl;
        $this->username   = $username;
        $this->password   = $password;
    }
    
    private function getToken(): string
    {
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        $response = $this->httpClient->request('POST', $this->baseUrl . '/api/login_check', [
            'json' => [
                'username' => $this->username,
                'password' => $this->password,
            ],
        ]);

        $data = $response->toArray();
        $this->cachedToken = $data['token'];

        return $this->cachedToken;
    }

    /**
     * Returns the BTV API result for a product reference.
     * $quantity is the number of units needed (e.g. doors of that size).
     *
     * @return array{CodigoProducto: string, PrecioVenta: string, Disponibilidad: string, CantidadDisponible: string, FechaProximaDisponibilidad: string}|null
     */
    public function getProductInfo(string $reference, int $quantity = 1): ?array
    {
        try {
            $token = $this->getToken();

            $response = $this->httpClient->request('POST', $this->baseUrl . '/api/products/product', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'json' => [
                    'codProduct' => $reference,
                    'quantity'   => (string) $quantity,
                ],
            ]);

            $data = $response->toArray(false);
            return $data['result'] ?? null;

        } catch (TransportExceptionInterface $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
