<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Subscriber;

use Shop4GoogleTagManager\Service\CustomEventService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GtmEventCacheSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CustomEventService $customEventService,
    ) {
    }


    public static function getSubscribedEvents(): array
    {
        return [
            's4gtm_event.written' => 'onChange',
            's4gtm_event.deleted' => 'onChange',
        ];
    }

    public function onChange(): void
    {
        $this->customEventService->invalidate();
    }
}
