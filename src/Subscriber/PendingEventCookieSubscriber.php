<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Subscriber;

use Shop4GoogleTagManager\Service\PendingEventStore;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Haelt ein Cookie synchron zur Session-Queue der vorgemerkten Events.
 *
 * Das Hinweis-Flag im HTML allein genuegt nicht: nach einem Logout landet der Besucher
 * auf einer cachebaren Seite, deren Flag aus dem Cache stammen kann. Das Cookie ist
 * dagegen pro Besucher korrekt. Es enthaelt keine Nutzdaten, nur "da liegt etwas an".
 */
class PendingEventCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PendingEventStore $pendingEventStore,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -10],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($this->isCacheable($request)) {
            return;
        }

        $hasPending = $this->pendingEventStore->hasPending();
        $flagSet = $request->cookies->get(PendingEventStore::FLAG_COOKIE) === '1';

        if ($hasPending === $flagSet) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            $this->cookie($request, $hasPending ? '1' : '', $hasPending ? 0 : 1),
        );
    }

    /**
     * Auf cachebaren Routen darf kein Set-Cookie angehaengt werden, sonst wandert
     * das Flag mit der Antwort in den HTTP-Cache.
     */
    private function isCacheable(Request $request): bool
    {
        $cache = $request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE);

        return $cache !== null && $cache !== false;
    }

    private function cookie(Request $request, string $value, int $expire): Cookie
    {
        return new Cookie(
            PendingEventStore::FLAG_COOKIE,
            $value,
            $expire,
            '/',
            null,
            $request->isSecure(),
            false, // muss fuer das storefront-js lesbar sein
            false,
            Cookie::SAMESITE_LAX,
        );
    }
}
