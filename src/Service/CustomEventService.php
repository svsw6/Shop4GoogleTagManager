<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service;

use Shop4GoogleTagManager\Core\Content\GtmEvent\GtmEventCollection;
use Shop4GoogleTagManager\Core\Content\GtmEvent\GtmEventEntity;
use Shop4GoogleTagManager\Struct\DataLayerEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Psr\Cache\CacheItemPoolInterface;

class CustomEventService
{
    private const MAX_EVENTS = 200;
    public const ACTIVE_EVENTS_CACHE_KEY = 's4gtm-active-events';
    private const CACHE_TTL = 3600;

    /** @var array<string, array<string, DataLayerEvent[]>> */
    private array $eventCache = [];

    /** @var list<array{ga4Event: string, payload: array<string, mixed>, eventContext: string, salesChannelIds: list<string>}>|null */
    private ?array $activeEvents = null;

    public function __construct(
        private readonly EntityRepository $gtmEventRepository,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function invalidate(): void
    {
        $this->cache->deleteItem(self::ACTIVE_EVENTS_CACHE_KEY);
        $this->eventCache = [];
        $this->activeEvents = null;
    }

    public function getEventsForContext(string $eventContext, ?string $salesChannelId, Context $context): array
    {
        return $this->loadActiveEvents($salesChannelId, $context)[$eventContext] ?? [];
    }

    private function loadActiveEvents(?string $salesChannelId, Context $context): array
    {
        $cacheKey = $salesChannelId ?? '';
        if (isset($this->eventCache[$cacheKey])) {
            return $this->eventCache[$cacheKey];
        }

        $grouped = [];
        foreach ($this->getAllActiveEvents($context) as $event) {
            if (!$this->appliesToSalesChannel($event['salesChannelIds'], $salesChannelId)) {
                continue;
            }

            $eventContext = $event['eventContext'];
            if (!GtmEventCatalog::isValidCustomContext($eventContext)) {
                continue;
            }
            $grouped[$eventContext][] = new DataLayerEvent($event['ga4Event'], $event['payload']);
        }

        return $this->eventCache[$cacheKey] = $grouped;
    }

    private function getAllActiveEvents(Context $context): array
    {
        if ($this->activeEvents !== null) {
            return $this->activeEvents;
        }

        $item = $this->cache->getItem(self::ACTIVE_EVENTS_CACHE_KEY);
        if ($item->isHit()) {
            return $this->activeEvents = $item->get();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('salesChannels');
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::ASCENDING));
        $criteria->setLimit(self::MAX_EVENTS);

        /** @var GtmEventCollection $entities */
        $entities = $this->gtmEventRepository->search($criteria, $context)->getEntities();

        $events = [];
        foreach ($entities as $entity) {
            $events[] = [
                'ga4Event' => $entity->getGa4Event(),
                'payload' => $entity->getPayload() ?? [],
                'eventContext' => $entity->getEventContext(),
                'salesChannelIds' => $this->salesChannelIds($entity),
            ];
        }

        $item->set($events);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $this->activeEvents = $events;
    }

    private function salesChannelIds(GtmEventEntity $entity): array
    {
        $salesChannels = $entity->getSalesChannels();
        if ($salesChannels === null) {
            return [];
        }

        return array_values($salesChannels->getIds());
    }

    private function appliesToSalesChannel(array $salesChannelIds, ?string $salesChannelId): bool
    {
        if ($salesChannelIds === []) {
            return true;
        }

        return $salesChannelId !== null && in_array($salesChannelId, $salesChannelIds, true);
    }
}
