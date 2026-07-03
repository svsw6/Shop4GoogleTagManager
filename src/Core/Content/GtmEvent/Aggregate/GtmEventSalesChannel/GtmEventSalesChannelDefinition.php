<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Core\Content\GtmEvent\Aggregate\GtmEventSalesChannel;

use Shop4GoogleTagManager\Core\Content\GtmEvent\GtmEventDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;


class GtmEventSalesChannelDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 's4gtm_event_sales_channel';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('s4gtm_event_id', 'gtmEventId', GtmEventDefinition::class))
                ->addFlags(new PrimaryKey(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))
                ->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('gtmEvent', 's4gtm_event_id', GtmEventDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
