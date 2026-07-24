<?php

namespace App\Controller;

use App\Service\GeminiImageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @Route("/api")
 */
class ImageController extends AbstractController
{
    /**
     * @Route("/generar-imagen", name="api_generar_imagen", methods={"POST"})
     */
    public function generar(Request $request, GeminiImageService $service): Response
    {
        $prompt = (string) $request->request->get('prompt', '');
        $previewBase64 = (string) $request->request->get('preview_base64', '');

        if ($prompt === '' || $previewBase64 === '') {
            return $this->json(['error' => 'Falta "prompt" o "preview_base64"'], 400);
        }

        // preview_base64 viene como: data:image/png;base64,AAAA...
        if (preg_match('#^data:(.*?);base64,(.*)$#', $previewBase64, $m) !== 1) {
            return $this->json(['error' => '"preview_base64" no es un dataURL válido'], 400);
        }

        $previewMime = $m[1];
        $previewBinary = base64_decode($m[2], true);

        if ($previewBinary === false) {
            return $this->json(['error' => 'No se pudo decodificar preview_base64'], 400);
        }

        // (OPCIONAL) Imagen fija (plantilla) guardada en /public/assets/plantilla.png
        $fixedPath = $this->getParameter('kernel.project_dir') . '/public/assets/plantilla.png';

        if (!file_exists($fixedPath)) {
            return $this->json([
                'error' => 'No existe la imagen fija en public/assets/plantilla.png. Crea esa ruta o cambia $fixedPath.'
            ], 500);
        }

        $fixedBinary = file_get_contents($fixedPath);
        if ($fixedBinary === false) {
            return $this->json(['error' => 'No se pudo leer la imagen fija'], 500);
        }

        $fixedMime = 'image/jpeg';

        // Llamar a Gemini con DOS imágenes: plantilla + preview
        $base64 = $service->generateFromTextAndTwoImages(
            $prompt,
            $fixedBinary, $fixedMime,
            $previewBinary, $previewMime
        );

        if (!$base64) {
            return $this->json(['error' => 'No se recibió imagen generada'], 502);
        }

        return $this->json([
            'mime' => 'image/png',
            'image_base64' => $base64,
        ]);
    }

    /**
     * @Route("/image-proxy", name="image_proxy", methods={"GET"})
     */
    public function proxy(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $url = $request->query->get('url', '');

        if (!preg_match('#^https?://#', $url)) {
            return new Response('Invalid URL', 400);
        }

        // If the URL points to this same server, serve the file directly from disk
        $host = $request->getSchemeAndHttpHost();
        $parsed = parse_url($url);
        $requestedHost = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
        if (isset($parsed['port'])) {
            $requestedHost .= ':' . $parsed['port'];
        }

        if (rtrim($requestedHost, '/') === rtrim($host, '/')) {
            $urlPath = $parsed['path'] ?? '';
            $localPath = $this->getParameter('kernel.project_dir') . '/public' . $urlPath;
            $localPath = realpath($localPath);
            $publicDir = realpath($this->getParameter('kernel.project_dir') . '/public');

            if ($localPath && $publicDir && str_starts_with($localPath, $publicDir) && is_file($localPath)) {
                $data = file_get_contents($localPath);
            } else {
                return new Response('File not found', 404);
            }
        } else {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $data = @file_get_contents($url, false, $context);
        }

        if ($data === false) {
            return new Response('Could not fetch image', 502);
        }

        $mime = str_ends_with(strtok($url, '?'), '.png') ? 'image/png' : 'image/jpeg';

        return new Response($data, 200, [
            'Content-Type'                => $mime,
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'               => 'public, max-age=3600',
        ]);
    }
}
