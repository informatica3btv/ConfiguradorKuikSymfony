<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiImageService
{
    private HttpClientInterface $httpClient;
    private string $geminiApiKey;

    public function __construct(HttpClientInterface $httpClient, string $geminiApiKey)
    {
        $this->httpClient = $httpClient;
        $this->geminiApiKey = $geminiApiKey;
    }

    /**
     * Devuelve:
     * - string base64 de la imagen si va bien
     * - array ['error' => '...'] si Gemini devuelve texto/error
     */
    public function generateFromTextAndTwoImages(
        string $prompt,
        string $img1Binary, string $img1Mime,
        string $img2Binary, string $img2Mime
    ) {
        $model = 'gemini-1.5-flash';


        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $model,
            urlencode($this->geminiApiKey)
        );

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $img1Mime, 'data' => base64_encode($img1Binary)]],
                    ['inline_data' => ['mime_type' => $img2Mime, 'data' => base64_encode($img2Binary)]],
                ],
            ]],
        ];

        $response = $this->httpClient->request('POST', $url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $payload,
        ]);

        $data = $response->toArray(false);

        // DEBUG: guardar respuesta cruda de Gemini
        @file_put_contents(
            dirname(__DIR__, 2) . '/var/log/gemini_debug.log',
            date('c') . PHP_EOL . print_r($data, true) . PHP_EOL . str_repeat('-', 60) . PHP_EOL,
            FILE_APPEND
        );

        // 1) Si viene error en estructura típica
        if (isset($data['error']['message'])) {
            return ['error' => 'Gemini error: ' . $data['error']['message']];
        }

        // 2) Si devuelve parts con inline_data (imagen)
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['inline_data']['data'])) {
                return $part['inline_data']['data']; // base64
            }
        }

        // 3) Si devuelve texto en vez de imagen
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                return ['error' => 'Gemini devolvió texto en vez de imagen: ' . $part['text']];
            }
        }

        // 4) Si no vino nada interpretable
        return ['error' => 'Respuesta sin imagen. Revisa var/log/gemini_debug.log'];
    }
}
