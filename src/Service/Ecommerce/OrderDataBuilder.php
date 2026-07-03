<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service\Ecommerce;

use Shop4GoogleTagManager\Struct\DataLayerEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;


class OrderDataBuilder
{
    use RoundsMonetaryValues;
    use MapsProductLineItems;

    public function __construct(
        private readonly ItemFactory $itemFactory,
        private readonly ManufacturerNameResolver $manufacturerNameResolver,
    ) {
    }

    public function buildPurchase(OrderEntity $order, SalesChannelContext $context): DataLayerEvent
    {
        $items = $this->productLineItems($order);

        return new DataLayerEvent('purchase', [
            'ecommerce' => [
                'transaction_id' => $order->getOrderNumber(),
                'currency' => $order->getCurrency()?->getIsoCode() ?? $context->getCurrency()->getIsoCode(),
                'value' => $this->itemsValue($items),
                'tax' => $this->round($this->resolveTax($order)),
                'shipping' => $this->round($order->getShippingTotal()),
                'coupon' => $this->resolveCoupon($order),
                'items' => $this->mapItems($items, $context),
            ],
        ]);
    }

    private function productLineItems(OrderEntity $order): array
    {
        $lineItems = $order->getLineItems();
        if ($lineItems === null) {
            return [];
        }

        $items = [];
        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                $items[] = $lineItem;
            }
        }

        return $items;
    }

    private function itemsValue(array $productLineItems): float
    {
        $sum = 0.0;
        foreach ($productLineItems as $lineItem) {
            $sum += $lineItem->getTotalPrice();
        }

        return $this->round($sum);
    }

    private function mapItems(array $productLineItems, SalesChannelContext $context): array
    {
        return $this->buildItems(
            $productLineItems,
            $context->getContext(),
            static fn (OrderLineItemEntity $li): ?string => ($li->getPayload() ?? [])['manufacturerId'] ?? null,
            fn (OrderLineItemEntity $li, ?string $brand): array => $this->itemFactory->fromOrderLineItem($li, $brand),
        );
    }

    private function resolveTax(OrderEntity $order): float
    {
        $price = $order->getPrice();

        return $price !== null ? $price->getCalculatedTaxes()->getAmount() : 0.0;
    }

    private function resolveCoupon(OrderEntity $order): string
    {
        $lineItems = $order->getLineItems();
        if ($lineItems === null) {
            return '';
        }

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() !== LineItem::PROMOTION_LINE_ITEM_TYPE) {
                continue;
            }
            $payload = $lineItem->getPayload() ?? [];
            if (isset($payload['code']) && $payload['code'] !== '') {
                return (string) $payload['code'];
            }
        }

        return '';
    }
}
