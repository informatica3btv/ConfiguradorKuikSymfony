<?php

namespace App\EventSubscriber;

use App\Repository\LanguageRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private LanguageRepository $languageRepository;
    private string $defaultLocale;

    public function __construct(LanguageRepository $languageRepository, string $defaultLocale)
    {
        $this->languageRepository = $languageRepository;
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $activeCodes = array_map(fn ($l) => $l->getCode(), $this->languageRepository->findActive());

        $locale = $request->query->get('_locale');
        if ($locale && !in_array($locale, $activeCodes, true)) {
            $locale = null;
        }

        if (!$locale && $request->hasPreviousSession()) {
            $sessionLocale = $request->getSession()->get('_locale');
            if ($sessionLocale && in_array($sessionLocale, $activeCodes, true)) {
                $locale = $sessionLocale;
            }
        }

        if (!$locale) {
            $default = $this->languageRepository->findDefault();
            $locale = $default ? $default->getCode() : $this->defaultLocale;
        }

        $request->setLocale($locale);
        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $locale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
