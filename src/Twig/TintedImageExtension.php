<?php

namespace App\Twig;

use App\Service\TintedImageService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TintedImageExtension extends AbstractExtension
{
    private TintedImageService $tintedImageService;

    public function __construct(TintedImageService $tintedImageService)
    {
        $this->tintedImageService = $tintedImageService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tinted_image', [$this, 'getTintedImageUrl']),
        ];
    }

    public function getTintedImageUrl(string $sourceAsset, ?string $hexColor): ?string
    {
        if (!$hexColor) {
            return null;
        }
        return $this->tintedImageService->getTintedImageUrl($sourceAsset, $hexColor);
    }
}
