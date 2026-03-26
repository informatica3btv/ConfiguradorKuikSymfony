<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class BtvApiService
{
    private $httpClient;
    private $cache;
    private $logger;
    private $baseUrl;
    private $username;
    private $password;

    // Shared HTTP options for all BTV calls
    private $httpOptions = [
        'timeout'     => 8,
        'verify_peer' => false,
        'verify_host' => false,
    ];

    public function __construct(
        HttpClientInterface $httpClient,
        CacheInterface $cache,
        LoggerInterface $logger,
        string $baseUrl,
        string $username,
        string $password
    ) {
        $this->httpClient = $httpClient;
        $this->cache      = $cache;
        $this->logger     = $logger;
        $this->baseUrl    = $baseUrl;
        $this->username   = $username;
        $this->password   = $password;
    }

    private function getToken(): string
    {
        return $this->cache->get('btv_api_token', function (ItemInterface $item) {
            $item->expiresAfter(3500); // ~1 hour, slightly under typical JWT expiry

            $response = $this->httpClient->request('POST', $this->baseUrl . '/api/login_check', array_merge($this->httpOptions, [
                'json' => [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
            ]));

            $data = $response->toArray();

            if (empty($data['token'])) {
                throw new \RuntimeException('BTV API login did not return a token');
            }

            return $data['token'];
        });
    }

    /**
     * Returns the BTV API result for a product reference, or null on failure.
     *
     * @return array|null  Keys: CodigoProducto, PrecioVenta, Disponibilidad, CantidadDisponible, FechaProximaDisponibilidad
     */
    public function getProductInfo(string $reference, int $quantity = 1): ?array
    {
        try {
            $token = $this->getToken();

            $response = $this->httpClient->request('POST', $this->baseUrl . '/api/products/product', array_merge($this->httpOptions, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'json' => [
                    'codProduct' => $reference,
                    'quantity'   => (string) $quantity,
                ],
            ]));

            $data = $response->toArray(false);
            return $data['result'] ?? null;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('BTV API transport error for ref {ref}: {msg}', [
                'ref' => $reference,
                'msg' => $e->getMessage(),
            ]);
            return null;
        } catch (\Throwable $e) {
            $this->logger->error('BTV API error for ref {ref}: {msg}', [
                'ref' => $reference,
                'msg' => $e->getMessage(),
            ]);
            // If the token is expired or invalid, invalidate cached token so next request re-authenticates
            if (str_contains($e->getMessage(), '401') || str_contains($e->getMessage(), 'token')) {
                $this->cache->delete('btv_api_token');
            }
            return null;
        }
    }
}
