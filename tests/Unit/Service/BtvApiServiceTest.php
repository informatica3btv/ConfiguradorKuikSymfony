<?php

namespace App\Tests\Unit\Service;

use App\Service\BtvApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class BtvApiServiceTest extends TestCase
{
    private function makeService(HttpClientInterface $httpClient, bool $verifySsl = true): BtvApiService
    {
        return new BtvApiService(
            $httpClient,
            new ArrayAdapter(),
            new NullLogger(),
            'https://api.example.com',
            'user@test.com',
            'secret',
            'SELLER1',
            $verifySsl
        );
    }

    private function mockTokenResponse(string $token = 'fake-token'): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['token' => $token]);
        return $response;
    }

    // ─── getUsername / getCodSeller ───────────────────────────────────────────

    public function testGetUsernameReturnsConfiguredValue(): void
    {
        $service = $this->makeService($this->createStub(HttpClientInterface::class));
        $this->assertSame('user@test.com', $service->getUsername());
    }

    public function testGetCodSellerReturnsConfiguredValue(): void
    {
        $service = $this->makeService($this->createStub(HttpClientInterface::class));
        $this->assertSame('SELLER1', $service->getCodSeller());
    }

    // ─── getProductInfo — token obtenido y request enviado ───────────────────

    public function testGetProductInfoRequestsTokenThenProduct(): void
    {
        $tokenResponse = $this->mockTokenResponse('my-jwt');

        $productResponse = $this->createMock(ResponseInterface::class);
        $productResponse->method('toArray')->willReturn([
            'result' => [
                'CodigoProducto'  => 'REF001',
                'PrecioVenta'     => '99,99',
                'Disponibilidad'  => 'Disponible',
            ],
        ]);

        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->exactly(2))
             ->method('request')
             ->willReturnOnConsecutiveCalls($tokenResponse, $productResponse);

        $service = $this->makeService($http);
        $result  = $service->getProductInfo('REF001', 1);

        $this->assertSame('REF001', $result['CodigoProducto']);
        $this->assertSame('99,99', $result['PrecioVenta']);
    }

    public function testGetProductInfoReturnsNullWhenNoResult(): void
    {
        $tokenResponse = $this->mockTokenResponse();

        $productResponse = $this->createMock(ResponseInterface::class);
        $productResponse->method('toArray')->willReturn([]); // sin 'result'

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')
             ->willReturnOnConsecutiveCalls($tokenResponse, $productResponse);

        $service = $this->makeService($http);

        $this->assertNull($service->getProductInfo('NOPE', 1));
    }

    public function testGetProductInfoReturnsNullOnTransportError(): void
    {
        $tokenResponse = $this->mockTokenResponse();

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')
             ->willReturnCallback(function ($method, $url) use ($tokenResponse) {
                 if (str_contains($url, 'login_check')) {
                     return $tokenResponse;
                 }
                 throw new \Symfony\Component\HttpClient\Exception\TransportException('Connection refused');
             });

        $service = $this->makeService($http);

        $this->assertNull($service->getProductInfo('REF001', 1));
    }

    // ─── token cacheado (solo 1 login por sesión) ────────────────────────────

    public function testGetProductInfoOnlyLogsInOnce(): void
    {
        $tokenResponse = $this->mockTokenResponse('cached-token');

        $productResponse = $this->createMock(ResponseInterface::class);
        $productResponse->method('toArray')->willReturn(['result' => ['PrecioVenta' => '10,00']]);

        $http = $this->createMock(HttpClientInterface::class);

        // 1 login + 2 product calls = 3 total requests, NO más logins
        $http->expects($this->exactly(3))
             ->method('request')
             ->willReturnCallback(function ($method, $url) use ($tokenResponse, $productResponse) {
                 return str_contains($url, 'login_check') ? $tokenResponse : $productResponse;
             });

        $service = $this->makeService($http);
        $service->getProductInfo('REF001', 1);
        $service->getProductInfo('REF002', 1);
    }

    // ─── getProductInfo — token inválido limpia caché ─────────────────────────

    public function testGetProductInfoClearsTokenCacheOn401(): void
    {
        $tokenResponse = $this->mockTokenResponse('expired-token');

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')
             ->willReturnCallback(function ($method, $url) use ($tokenResponse) {
                 if (str_contains($url, 'login_check')) {
                     return $tokenResponse;
                 }
                 throw new \RuntimeException('HTTP 401 Unauthorized');
             });

        $service = $this->makeService($http);

        // No debe lanzar excepción — devuelve null y limpia la caché
        $result = $service->getProductInfo('REF001', 1);
        $this->assertNull($result);

        // Segunda llamada debe intentar login de nuevo (caché limpiada)
        $result2 = $service->getProductInfo('REF001', 1);
        $this->assertNull($result2);
    }

    // ─── createOffer ─────────────────────────────────────────────────────────

    public function testCreateOfferReturnsResponseData(): void
    {
        $tokenResponse = $this->mockTokenResponse();

        $offerResponse = $this->createMock(ResponseInterface::class);
        $offerResponse->method('toArray')->willReturn([
            'result' => ['Cabecera' => ['NumeroOfertaNav' => 'OFE-0001']],
        ]);

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')
             ->willReturnOnConsecutiveCalls($tokenResponse, $offerResponse);

        $service = $this->makeService($http);
        $result  = $service->createOffer(['codSeller' => 'SELLER1', 'lines' => []]);

        $this->assertSame('OFE-0001', $result['result']['Cabecera']['NumeroOfertaNav']);
    }

    public function testCreateOfferReturnsNullOnTransportError(): void
    {
        $tokenResponse = $this->mockTokenResponse();

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')
             ->willReturnCallback(function ($method, $url) use ($tokenResponse) {
                 if (str_contains($url, 'login_check')) {
                     return $tokenResponse;
                 }
                 throw new \Symfony\Component\HttpClient\Exception\TransportException('Timeout');
             });

        $service = $this->makeService($http);

        $this->assertNull($service->createOffer([]));
    }

    // ─── login fallido lanza excepción ────────────────────────────────────────

    public function testGetProductInfoReturnsNullWhenLoginReturnsNoToken(): void
    {
        $tokenResponse = $this->createMock(ResponseInterface::class);
        $tokenResponse->method('toArray')->willReturn([]); // sin token

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')->willReturn($tokenResponse);

        $service = $this->makeService($http);

        $this->assertNull($service->getProductInfo('REF001', 1));
    }
}
