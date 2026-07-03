<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Core\Content\GtmEvent;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;


class GtmEventCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return GtmEventEntity::class;
    }
}
