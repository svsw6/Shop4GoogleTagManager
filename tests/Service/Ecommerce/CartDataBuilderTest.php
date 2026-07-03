<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service\Ecommerce;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\Ecommerce\CartDataBuilder;
use Shop4GoogleTagManager\Service\Ecommerce\ItemFactory;
use Shop4GoogleTagManager\Service\Ecommerce\ManufacturerNameResolver;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartDataBuilderTest extends TestCase
{
    public function testViewCartUsesGoodsValueWithoutShipping(): void
    {
        $builder = $this->builder();

        $event = $builder->buildViewCart($this->cart(), $this->context());
        $eco = $event->jsonSerialize()['ecommerce'];

        static::assertSame('view_cart', $event->jsonSerialize()['event']);
        static::assertSame('EUR', $eco['currency']);
        // value = summe der produkt-items (warensumme) ohne versandkosten
        static::assertSame(100.0, $eco['value']);
        static::assertCount(1, $eco['items']);
    }

    public function testBeginCheckoutUsesGoodsValue(): void
    {
        $builder = $this->builder();

        $eco = $builder->buildBeginCheckout($this->cart(), $this->context())->jsonSerialize()['ecommerce'];

        static::assertSame(100.0, $eco['value']);
    }

    public function testNumericFieldsAreNumbersNotStrings(): void
    {
        $builder = $this->builder();

        $eco = $builder->buildViewCart($this->cart(), $this->context())->jsonSerialize()['ecommerce'];

        static::assertIsFloat($eco['value']);
        static::assertIsFloat($eco['items'][0]['price']);
        static::assertIsInt($eco['items'][0]['quantity']);
    }

    public function testItemBrandIsResolvedFromManufacturerId(): void
    {
        $lineItem = new LineItem('line-id', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-id', 1);
        $lineItem->setLabel('Markenprodukt');
        $lineItem->setPayload(['productNumber' => 'SW-300', 'manufacturerId' => 'man-1']);
        $lineItem->setPrice(new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection(), 1));

        $price = $this->createMock(CartPrice::class);
        $price->method('getPositionPrice')->willReturn(10.0);
        $cart = $this->createMock(Cart::class);
        $cart->method('getPrice')->willReturn($price);
        $cart->method('getLineItems')->willReturn(new LineItemCollection([$lineItem]));

        $resolver = $this->createMock(ManufacturerNameResolver::class);
        $resolver->method('resolve')->willReturn(['man-1' => 'ACME']);

        $builder = new CartDataBuilder(new ItemFactory(), $resolver);
        $eco = $builder->buildViewCart($cart, $this->context())->jsonSerialize()['ecommerce'];

        static::assertSame('ACME', $eco['items'][0]['item_brand']);
    }

    private function builder(): CartDataBuilder
    {
        $resolver = $this->createMock(ManufacturerNameResolver::class);
        $resolver->method('resolve')->willReturn([]);

        return new CartDataBuilder(new ItemFactory(), $resolver);
    }

    private function cart(): Cart
    {
        $lineItem = new LineItem('line-id', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-id', 2);
        $lineItem->setLabel('Testprodukt');
        $lineItem->setPayload(['productNumber' => 'SW-100']);
        $lineItem->setPrice(new CalculatedPrice(
            50.0,
            100.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            2,
        ));

        $price = $this->createMock(CartPrice::class);
        // positionspreis = warensumme ohne versand; totalPrice waere inkl. versand
        $price->method('getPositionPrice')->willReturn(100.0);

        $cart = $this->createMock(Cart::class);
        $cart->method('getPrice')->willReturn($price);
        $cart->method('getLineItems')->willReturn(new LineItemCollection([$lineItem]));

        return $cart;
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
