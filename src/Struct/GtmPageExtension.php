<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Struct;

use Shopware\Core\Framework\Struct\Struct;

class GtmPageExtension extends Struct
{
    public const EXTENSION_NAME = 's4gtm';
    protected array $events = [];

    public function addEvent(DataLayerEvent $event): void
    {
        $this->events[] = $event;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function hasEvents(): bool
    {
        return $this->events !== [];
    }

    public function getSerializedEvents(): array
    {
        return array_map(
            static fn (DataLayerEvent $event): array => $event->jsonSerialize(),
            $this->events,
        );
    }
}
