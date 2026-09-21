<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service\Ecommerce;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;

class ItemFactory
{
    use RoundsMonetaryValues;

    public const MAX_CATEGORY_LEVELS = 5;

    public function fromProduct(
        SalesChannelProductEntity $product,
        int $quantity = 1,
        ?int $index = null,
        ?string $rootCategoryId = null,
    ): array {
        $unitPrice = $product->getCalculatedPrice()->getUnitPrice();
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

        foreach ($this->resolveCategoryPath($product, $rootCategoryId) as $level => $name) {
            $item[$level === 0 ? 'item_category' : 'item_category' . ($level + 1)] = $name;
        }

        $variant = $this->resolveVariant($product);
        if ($variant !== null) {
            $item['item_variant'] = $variant;
        }

        $listPrice = $product->getCalculatedPrice()->getListPrice()?->getPrice();
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

    /**
     * @return list<string>
     */
    private function resolveCategoryPath(SalesChannelProductEntity $product, ?string $rootCategoryId): array
    {
        $category = $product->getSeoCategory() ?? $product->getCategories()?->first();
        if ($category === null) {
            return [];
        }

        $breadcrumb = $category->getPlainBreadcrumb();
        if ($breadcrumb === []) {
            $name = $category->getTranslation('name') ?? $category->getName();

            return is_string($name) && $name !== '' ? [$name] : [];
        }

        if ($rootCategoryId !== null && \array_key_exists($rootCategoryId, $breadcrumb)) {
            $breadcrumb = \array_slice($breadcrumb, array_search($rootCategoryId, array_keys($breadcrumb), true) + 1, null, true);
        }

        $path = [];
        foreach ($breadcrumb as $name) {
            if (!is_string($name) || $name === '') {
                continue;
            }
            $path[] = $name;
            if (\count($path) === self::MAX_CATEGORY_LEVELS) {
                break;
            }
        }

        return $path;
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
