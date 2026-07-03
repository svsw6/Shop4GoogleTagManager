<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service\Ecommerce;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;

class ItemFactory
{
    use RoundsMonetaryValues;

    public function fromProduct(SalesChannelProductEntity $product, int $quantity = 1, ?int $index = null): array
    {
        $unitPrice = $product->getCalculatedPrice()?->getUnitPrice() ?? 0.0;
        $item = [
            'item_id' => $product->getProductNumber(),
            'item_name' => $product->getTranslation('name') ?? $product->getName(),
            'price' => $this->round($unitPrice),
            'quantity' => $quantity,
        ];

        $brand = $product->getManufacturer()?->getTranslation('name')
            ?? $product->getManufacturer()?->getName();
        if ($brand !== null) {
            $item['item_brand'] = $brand;
        }

        $category = $this->resolveCategoryName($product);
        if ($category !== null) {
            $item['item_category'] = $category;
        }

        $variant = $this->resolveVariant($product);
        if ($variant !== null) {
            $item['item_variant'] = $variant;
        }

        $listPrice = $product->getCalculatedPrice()?->getListPrice()?->getPrice();
        if ($listPrice !== null && $listPrice > $unitPrice) {
            $item['discount'] = $this->round($listPrice - $unitPrice);
        }

        if ($index !== null) {
            $item['index'] = $index;
        }

        return $item;
    }

    public function fromLineItem(LineItem $lineItem, ?string $brand = null): array
    {
        $payload = $lineItem->getPayload();

        $item = [
            'item_id' => $payload['productNumber'] ?? $lineItem->getReferencedId() ?? $lineItem->getId(),
            'item_name' => $lineItem->getLabel(),
            'price' => $this->round($lineItem->getPrice()?->getUnitPrice() ?? 0.0),
            'quantity' => $lineItem->getQuantity(),
        ];

        $brand ??= isset($payload['manufacturerName']) ? (string) $payload['manufacturerName'] : null;
        if ($brand !== null && $brand !== '') {
            $item['item_brand'] = $brand;
        }

        if (isset($payload['options']) && is_array($payload['options'])) {
            $variant = $this->formatVariation($payload['options']);
            if ($variant !== null) {
                $item['item_variant'] = $variant;
            }
        }

        return $item;
    }

    public function fromOrderLineItem(OrderLineItemEntity $lineItem, ?string $brand = null): array
    {
        $payload = $lineItem->getPayload() ?? [];

        $item = [
            'item_id' => $payload['productNumber'] ?? $lineItem->getProductId() ?? $lineItem->getId(),
            'item_name' => $lineItem->getLabel(),
            'price' => $this->round($lineItem->getUnitPrice()),
            'quantity' => $lineItem->getQuantity(),
        ];

        $brand ??= isset($payload['manufacturerName']) ? (string) $payload['manufacturerName'] : null;
        if ($brand !== null && $brand !== '') {
            $item['item_brand'] = $brand;
        }

        if (isset($payload['options']) && is_array($payload['options'])) {
            $variant = $this->formatVariation($payload['options']);
            if ($variant !== null) {
                $item['item_variant'] = $variant;
            }
        }

        return $item;
    }

    private function resolveCategoryName(SalesChannelProductEntity $product): ?string
    {
        $seoCategory = $product->getSeoCategory();
        if ($seoCategory !== null) {
            return $seoCategory->getTranslation('name') ?? $seoCategory->getName();
        }

        $categories = $product->getCategories();
        if ($categories !== null && $categories->count() > 0) {
            $first = $categories->first();

            return $first?->getTranslation('name') ?? $first?->getName();
        }

        return null;
    }

    private function resolveVariant(SalesChannelProductEntity $product): ?string
    {
        return $this->formatVariation($product->getVariation());
    }

    private function formatVariation(array $variation): ?string
    {
        if ($variation === []) {
            return null;
        }

        $parts = [];
        foreach ($variation as $option) {
            if (isset($option['group'], $option['option'])) {
                $parts[] = $option['group'] . ': ' . $option['option'];
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
