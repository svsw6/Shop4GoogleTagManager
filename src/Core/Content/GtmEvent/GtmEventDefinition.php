<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Core\Content\GtmEvent;

use Shop4GoogleTagManager\Core\Content\GtmEvent\Aggregate\GtmEventSalesChannel\GtmEventSalesChannelDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;


class GtmEventDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 's4gtm_event';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GtmEventEntity::class;
    }

    public function getCollectionClass(): string
    {
        return GtmEventCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required(), new ApiAware()),
            (new StringField('event_context', 'eventContext'))->addFlags(new Required(), new ApiAware()),
            (new StringField('ga4_event', 'ga4Event'))->addFlags(new Required(), new ApiAware()),
            (new JsonField('payload', 'payload'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            (new IntField('priority', 'priority'))->addFlags(new ApiAware()),
            (new ManyToManyAssociationField(
                'salesChannels',
                SalesChannelDefinition::class,
                GtmEventSalesChannelDefinition::class,
                's4gtm_event_id',
                'sales_channel_id'
            ))->addFlags(new ApiAware()),
        ]);
    }
}
