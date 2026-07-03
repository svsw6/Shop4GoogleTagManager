<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service\Ecommerce;

use Shop4GoogleTagManager\Struct\DataLayerEvent;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
class ProductDataBuilder
{
    public function __construct(
        private readonly ItemFactory $itemFactory,
    ) {
    }

    public function buildViewItem(SalesChannelProductEntity $product, SalesChannelContext $context): DataLayerEvent
    {
        $item = $this->itemFactory->fromProduct($product);

        return new DataLayerEvent('view_item', [
            'ecommerce' => [
                'currency' => $context->getCurrency()->getIsoCode(),
                'value' => $item['price'] ?? 0.0,
                'items' => [$item],
            ],
        ]);
    }

    public function buildViewItemList(
        array $products,
        SalesChannelContext $context,
        string $listId,
        string $listName,
        int $startIndex = 0,
        ?string $itemCategory = null,
    ): DataLayerEvent {
        $items = [];
        $index = $startIndex;
        foreach ($products as $product) {
            $item = $this->itemFactory->fromProduct($product, 1, $index);
            $item['item_list_id'] = $listId;
            $item['item_list_name'] = $listName;

            if ($itemCategory !== null && $itemCategory !== '') {
                $item['item_category'] ??= $itemCategory;
            }
            $items[] = $item;
            ++$index;
        }

        return new DataLayerEvent('view_item_list', [
            'ecommerce' => [
                'currency' => $context->getCurrency()->getIsoCode(),
                'item_list_id' => $listId,
                'item_list_name' => $listName,
                'items' => $items,
            ],
        ]);
    }

    public function buildSearch(
        string $searchTerm,
        array $products,
        SalesChannelContext $context,
        int $startIndex = 0,
        bool $includeSearchTerm = true,
    ): DataLayerEvent {
        $items = [];
        $index = $startIndex;
        foreach ($products as $product) {
            $item = $this->itemFactory->fromProduct($product, 1, $index);
            $items[] = $item;
            ++$index;
        }

        $data = [];
        if ($includeSearchTerm) {
            $data['search_term'] = $searchTerm;
        }
        $data['ecommerce'] = [
            'currency' => $context->getCurrency()->getIsoCode(),
            'items' => $items,
        ];

        return new DataLayerEvent('search', $data);
    }
}
