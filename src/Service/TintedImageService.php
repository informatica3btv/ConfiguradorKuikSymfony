<?php

namespace App\Service;

/**
 * Genera (y cachea en disco) versiones de una imagen base teñidas con un
 * color arbitrario, mediante una mezcla "multiply" (igual que
 * background-blend-mode: multiply en CSS, pero como PNG real generado una
 * sola vez por color, para que se vea igual en cualquier sitio, incluidas
 * las capturas que hace html2canvas para el PDF).
 */
class TintedImageService
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * Devuelve la URL pública (relativa) de la imagen teñida para ese color,
     * generándola si todavía no existe en caché.
     */
    public function getTintedImageUrl(string $sourceAsset, string $hexColor): ?string
    {
        $hex = $this->normalizeHex($hexColor);
        if ($hex === null) {
            return null;
        }

        $sourcePath = $this->projectDir . '/public/' . ltrim($sourceAsset, '/');
        if (!is_file($sourcePath)) {
            return null;
        }

        $baseName  = pathinfo($sourceAsset, PATHINFO_FILENAME);
        $cacheDir  = $this->projectDir . '/public/uploads/tinted/' . $baseName;
        $cachePath = $cacheDir . '/' . $hex . '.png';
        $publicUrl = '/uploads/tinted/' . $baseName . '/' . $hex . '.png';

        if (is_file($cachePath)) {
            return $publicUrl;
        }

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        if (!$this->generateTinted($sourcePath, $cachePath, $hex)) {
            return null;
        }

        return $publicUrl;
    }

    private function normalizeHex(string $hexColor): ?string
    {
        $hex = strtolower(ltrim(trim($hexColor), '#'));
        if (!preg_match('/^[0-9a-f]{6}$/', $hex)) {
            return null;
        }
        return $hex;
    }

    private function generateTinted(string $sourcePath, string $destPath, string $hex): bool
    {
        $src = @imagecreatefrompng($sourcePath);
        if (!$src) {
            return false;
        }

        [$r, $g, $b] = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        $width  = imagesx($src);
        $height = imagesy($src);
        $out    = imagecreatetruecolor($width, $height);
        imagealphablending($out, false);
        imagesavealpha($out, true);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($src, $x, $y);
                $sa   = ($rgba >> 24) & 0x7F;
                $sr   = ($rgba >> 16) & 0xFF;
                $sg   = ($rgba >> 8) & 0xFF;
                $sb   = $rgba & 0xFF;

                $nr = (int) round($sr * $r / 255);
                $ng = (int) round($sg * $g / 255);
                $nb = (int) round($sb * $b / 255);

                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $nr, $ng, $nb, $sa));
            }
        }

        $ok = imagepng($out, $destPath);
        imagedestroy($src);
        imagedestroy($out);

        return $ok;
    }
}
