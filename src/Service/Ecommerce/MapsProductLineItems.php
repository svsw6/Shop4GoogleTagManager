<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service\Ecommerce;

use Shopware\Core\Framework\Context;

trait MapsProductLineItems
{
    private function buildItems(array $productLineItems, Context $context, callable $manufacturerId, callable $toItem): array
    {
        $brands = $this->manufacturerNameResolver->resolve(
            array_map($manufacturerId, $productLineItems),
            $context,
        );

        $items = [];
        foreach ($productLineItems as $lineItem) {
            $id = $manufacturerId($lineItem);
            $brand = is_string($id) ? ($brands[$id] ?? null) : null;
            $items[] = $toItem($lineItem, $brand);
        }

        return $items;
    }
}
