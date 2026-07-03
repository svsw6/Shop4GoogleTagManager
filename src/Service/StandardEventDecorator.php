<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service;

use Shop4GoogleTagManager\Struct\DataLayerEvent;

class StandardEventDecorator
{
    public function __construct(
        private readonly ConfigService $configService,
    ) {
    }

    public function apply(DataLayerEvent $event, ?string $salesChannelId): DataLayerEvent
    {
        $override = $this->configService->getStandardEventOverride($event->event, $salesChannelId);

        $name = $override['ga4Event'] !== '' ? $override['ga4Event'] : $event->event;
        $data = $event->data;

        if ($override['payload'] !== []) {
            $payload = array_diff_key($override['payload'], ['event' => null, 'ecommerce' => null]);
            $data = array_merge($data, $payload);
        }

        return new DataLayerEvent($name, $data);
    }
}
