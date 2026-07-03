<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Core\Content\GtmEvent;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class GtmEventEntity extends Entity
{
    use EntityIdTrait;

    protected string $technicalName;

    protected string $eventContext;

    protected string $ga4Event;

    /** @var array<string, mixed>|null statische zusatz-felder */
    protected ?array $payload = null;

    protected bool $active = true;

    protected int $priority = 0;

    protected ?SalesChannelCollection $salesChannels = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getEventContext(): string
    {
        return $this->eventContext;
    }

    public function setEventContext(string $eventContext): void
    {
        $this->eventContext = $eventContext;
    }

    public function getGa4Event(): string
    {
        return $this->ga4Event;
    }

    public function setGa4Event(string $ga4Event): void
    {
        $this->ga4Event = $ga4Event;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(?SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }
}
