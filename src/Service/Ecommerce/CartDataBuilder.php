<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service\Ecommerce;

use Shop4GoogleTagManager\Struct\DataLayerEvent;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartDataBuilder
{
    use RoundsMonetaryValues;
    use MapsProductLineItems;

    public function __construct(
        private readonly ItemFactory $itemFactory,
        private readonly ManufacturerNameResolver $manufacturerNameResolver,
    ) {
    }

    public function buildViewCart(Cart $cart, SalesChannelContext $context): DataLayerEvent
    {
        $items = $this->productLineItems($cart);

        return new DataLayerEvent('view_cart', [
            'ecommerce' => [
                'currency' => $context->getCurrency()->getIsoCode(),
                'value' => $this->itemsValue($items),
                'items' => $this->mapItems($items, $context),
            ],
        ]);
    }

    public function buildBeginCheckout(Cart $cart, SalesChannelContext $context): DataLayerEvent
    {
        $items = $this->productLineItems($cart);

        return new DataLayerEvent('begin_checkout', [
            'ecommerce' => [
                'currency' => $context->getCurrency()->getIsoCode(),
                'value' => $this->itemsValue($items),
                'items' => $this->mapItems($items, $context),
            ],
        ]);
    }

    public function buildAddShippingInfo(Cart $cart, SalesChannelContext $context): DataLayerEvent
    {
        $items = $this->productLineItems($cart);

        return new DataLayerEvent('add_shipping_info', [
            'ecommerce' => [
                'currency' => $context->getCurrency()->getIsoCode(),
                'value' => $this->itemsValue($items),
                'shipping_tier' => $context->getShippingMethod()->getTranslation('name')
                    ?? $context->getShippingMethod()->getName(),
                'items' => $this->mapItems($items, $context),
            ],
        ]);
    }

    public function buildAddPaymentInfo(Cart $cart, SalesChannelContext $context): DataLayerEvent
    {
        $items = $this->productLineItems($cart);

        return new DataLayerEvent('add_payment_info', [
            'ecommerce' => [
                'currency' => $context->getCurrency()->getIsoCode(),
                'value' => $this->itemsValue($items),
                'payment_type' => $context->getPaymentMethod()->getTranslation('name')
                    ?? $context->getPaymentMethod()->getName(),
                'items' => $this->mapItems($items, $context),
            ],
        ]);
    }

    private function productLineItems(Cart $cart): array
    {
        $items = [];
        foreach ($cart->getLineItems() as $lineItem) {
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
            $sum += $lineItem->getPrice()?->getTotalPrice() ?? 0.0;
        }

        return $this->round($sum);
    }

    private function mapItems(array $productLineItems, SalesChannelContext $context): array
    {
        return $this->buildItems(
            $productLineItems,
            $context->getContext(),
            static fn (LineItem $li): ?string => $li->getPayload()['manufacturerId'] ?? null,
            fn (LineItem $li, ?string $brand): array => $this->itemFactory->fromLineItem($li, $brand),
        );
    }
}
