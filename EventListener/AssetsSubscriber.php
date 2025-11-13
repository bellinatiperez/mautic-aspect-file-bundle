<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomAssetsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Subscriber to inject AspectFile JavaScript assets
 */
class AssetsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_ASSETS => ['injectAssets', 0],
        ];
    }

    public function injectAssets(CustomAssetsEvent $assetsEvent): void
    {
        // Only inject in Mautic administration pages
        if (!$this->isMauticAdministrationPage()) {
            return;
        }

        // Add AspectFile CSS
        $assetsEvent->addStylesheet('plugins/MauticAspectFileBundle/Assets/css/aspectfile.css');

        // Add AspectFile JavaScript
        $assetsEvent->addScript('plugins/MauticAspectFileBundle/Assets/js/aspectfile.js');
    }

    /**
     * Returns true for routes that start with /s/
     */
    private function isMauticAdministrationPage(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return false;
        }

        return preg_match('/^\/s\//', $request->getPathInfo()) >= 1;
    }
}
