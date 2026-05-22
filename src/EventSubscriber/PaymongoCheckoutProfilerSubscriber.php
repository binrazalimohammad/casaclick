<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Hides the Symfony debug toolbar on Paymongo checkout pages (mobile WebView).
 */
final class PaymongoCheckoutProfilerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 2048],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!preg_match('#^/api/paymongo/(dev-checkout|dev-complete)#', $path)) {
            return;
        }

        $event->getRequest()->attributes->set('_profiler', false);
        $event->getRequest()->attributes->set('_wdt', false);
    }
}
