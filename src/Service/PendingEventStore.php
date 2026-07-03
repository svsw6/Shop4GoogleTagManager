<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service;

use Shop4GoogleTagManager\Struct\DataLayerEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class PendingEventStore
{
    private const SESSION_KEY = 's4gtm_pending_events';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function add(DataLayerEvent $event): void
    {
        $session = $this->getSession();
        if ($session === null) {
            return;
        }

        $events = $session->get(self::SESSION_KEY, []);
        $events[] = $event->jsonSerialize();
        $session->set(self::SESSION_KEY, $events);
    }

    public function hasPending(): bool
    {
        $session = $this->getSession();
        if ($session === null) {
            return false;
        }

        $events = $session->get(self::SESSION_KEY, []);

        return is_array($events) && $events !== [];
    }

    public function pull(): array
    {
        $session = $this->getSession();
        if ($session === null) {
            return [];
        }

        $events = $session->get(self::SESSION_KEY, []);
        $session->remove(self::SESSION_KEY);

        return $events;
    }

    private function getSession(): ?SessionInterface
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
