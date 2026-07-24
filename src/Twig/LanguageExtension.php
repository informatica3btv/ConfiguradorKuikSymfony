<?php

namespace App\Twig;

use App\Repository\LanguageRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LanguageExtension extends AbstractExtension
{
    private LanguageRepository $languageRepository;

    public function __construct(LanguageRepository $languageRepository)
    {
        $this->languageRepository = $languageRepository;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('active_languages', [$this, 'getActiveLanguages']),
        ];
    }

    public function getActiveLanguages(): array
    {
        return $this->languageRepository->findActive();
    }
}
