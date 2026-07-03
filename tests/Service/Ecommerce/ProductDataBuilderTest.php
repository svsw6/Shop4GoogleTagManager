<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service\Ecommerce;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\Ecommerce\ItemFactory;
use Shop4GoogleTagManager\Service\Ecommerce\ProductDataBuilder;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductDataBuilderTest extends TestCase
{
    public function testViewItemUsesUnitPriceAsValue(): void
    {
        $event = (new ProductDataBuilder(new ItemFactory()))->buildViewItem($this->product('SW-1', 50.0), $this->context());
        $eco = $event->jsonSerialize()['ecommerce'];

        static::assertSame('view_item', $event->jsonSerialize()['event']);
        static::assertSame('EUR', $eco['currency']);
        static::assertSame(50.0, $eco['value']);
        static::assertIsFloat($eco['value']);
        static::assertIsFloat($eco['items'][0]['price']);
        static::assertIsInt($eco['items'][0]['quantity']);
    }

    public function testViewItemListAppliesPaginationOffsetToIndex(): void
    {
        $products = [$this->product('SW-1', 10.0), $this->product('SW-2', 20.0)];

        $eco = (new ProductDataBuilder(new ItemFactory()))
            ->buildViewItemList($products, $this->context(), 'cat-1', 'Kategorie', 24)
            ->jsonSerialize()['ecommerce'];

        // index spiegelt die absolute position in der liste (seite 2 ab offset 24)
        static::assertSame(24, $eco['items'][0]['index']);
        static::assertSame(25, $eco['items'][1]['index']);
        static::assertSame('cat-1', $eco['items'][0]['item_list_id']);
        static::assertSame('Kategorie', $eco['items'][0]['item_list_name']);
    }

    public function testViewItemListUsesBrowsedCategoryAsItemCategory(): void
    {
        $eco = (new ProductDataBuilder(new ItemFactory()))
            ->buildViewItemList([$this->product('SW-1', 10.0)], $this->context(), 'cat-1', 'Bekleidung', 0, 'Bekleidung')
            ->jsonSerialize()['ecommerce'];

        static::assertSame('Bekleidung', $eco['items'][0]['item_category']);
    }

    public function testSearchCarriesTermAndItems(): void
    {
        $eco = (new ProductDataBuilder(new ItemFactory()))
            ->buildSearch('schuhe', [$this->product('SW-1', 10.0)], $this->context())
            ->jsonSerialize();

        static::assertSame('search', $eco['event']);
        static::assertSame('schuhe', $eco['search_term']);
        static::assertCount(1, $eco['ecommerce']['items']);
    }

    public function testSearchOmitsTermWhenAnonymized(): void
    {
        $eco = (new ProductDataBuilder(new ItemFactory()))
            ->buildSearch('max mustermann', [$this->product('SW-1', 10.0)], $this->context(), 0, false)
            ->jsonSerialize();

        static::assertSame('search', $eco['event']);
        // datenminimierung: search_term darf nicht uebertragen werden
        static::assertArrayNotHasKey('search_term', $eco);
        static::assertCount(1, $eco['ecommerce']['items']);
    }

    private function product(string $number, float $unitPrice): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId(md5($number));
        $product->setProductNumber($number);
        $product->setName('Produkt ' . $number);
        $product->setTranslated(['name' => 'Produkt ' . $number]);
        $product->setCalculatedPrice(new CalculatedPrice(
            $unitPrice,
            $unitPrice,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));

        return $product;
    }

    private function context(): SalesChannelContext
    {
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('EUR');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCurrency')->willReturn($currency);

        return $context;
    }
}
