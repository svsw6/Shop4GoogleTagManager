<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service\Ecommerce;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\Ecommerce\ItemFactory;
use Shop4GoogleTagManager\Service\Ecommerce\ManufacturerNameResolver;
use Shop4GoogleTagManager\Service\Ecommerce\OrderDataBuilder;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderDataBuilderTest extends TestCase
{
    public function testBuildPurchaseMapsTransactionAndAmounts(): void
    {
        $event = $this->builder()->buildPurchase($this->order(), $this->context());
        $data = $event->jsonSerialize();
        $eco = $data['ecommerce'];

        static::assertSame('purchase', $data['event']);
        static::assertSame('10001', $eco['transaction_id']);
        static::assertSame('EUR', $eco['currency']);
        // value = summe der produkt-items OHNE versand (50*2 = 100); der cart-rabatt steckt in coupon,
        // nicht im value -> so bleibt value == summe(items)
        static::assertSame(100.0, $eco['value']);
        static::assertSame(19.0, $eco['tax']);
        static::assertSame(5.0, $eco['shipping']);
        static::assertSame('SUMMER10', $eco['coupon']);
        // nur produkte landen als items, der promotion-line-item nicht
        static::assertCount(1, $eco['items']);
        static::assertSame('SW-100', $eco['items'][0]['item_id']);
    }

    public function testPurchaseNumericFieldsAreNumbersNotStrings(): void
    {
        $eco = $this->builder()
            ->buildPurchase($this->order(), $this->context())
            ->jsonSerialize()['ecommerce'];

        static::assertIsFloat($eco['value']);
        static::assertIsFloat($eco['tax']);
        static::assertIsFloat($eco['shipping']);
        static::assertIsFloat($eco['items'][0]['price']);
        static::assertIsInt($eco['items'][0]['quantity']);
    }

    private function builder(): OrderDataBuilder
    {
        $resolver = $this->createMock(ManufacturerNameResolver::class);
        $resolver->method('resolve')->willReturn([]);

        return new OrderDataBuilder(new ItemFactory(), $resolver);
    }

    private function order(): OrderEntity
    {
        $product = new OrderLineItemEntity();
        $product->setId('p1');
        $product->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $product->setPayload(['productNumber' => 'SW-100']);
        $product->setLabel('Testprodukt');
        $product->setUnitPrice(50.0);
        $product->setTotalPrice(100.0);
        $product->setQuantity(2);

        $promotion = new OrderLineItemEntity();
        $promotion->setId('promo1');
        $promotion->setType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        $promotion->setPayload(['code' => 'SUMMER10']);
        $promotion->setLabel('Rabatt');
        $promotion->setUnitPrice(-10.0);
        $promotion->setQuantity(1);

        $price = $this->createMock(CartPrice::class);
        $price->method('getCalculatedTaxes')->willReturn(
            new CalculatedTaxCollection([new CalculatedTax(19.0, 19.0, 100.0)]),
        );
        // positionssumme = warensumme ohne versand (produkt 100 abzgl. rabatt 10)
        $price->method('getPositionPrice')->willReturn(90.0);

        $order = $this->createMock(OrderEntity::class);
        $order->method('getOrderNumber')->willReturn('10001');
        $order->method('getCurrency')->willReturn(null);
        $order->method('getAmountTotal')->willReturn(119.0);
        $order->method('getShippingTotal')->willReturn(5.0);
        $order->method('getPrice')->willReturn($price);
        $order->method('getLineItems')->willReturn(new OrderLineItemCollection([$product, $promotion]));

        return $order;
    }

    private function context(): SalesChannelContext
    {
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('EUR');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }
}
