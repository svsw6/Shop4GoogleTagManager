<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service\Ecommerce;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\Ecommerce\ItemFactory;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class ItemFactoryTest extends TestCase
{
    public function testFromLineItemMapsGa4Fields(): void
    {
        $lineItem = new LineItem('line-id', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-id', 3);
        $lineItem->setLabel('Testprodukt');
        $lineItem->setPayload(['productNumber' => 'SW-100', 'manufacturerName' => 'ACME']);
        $lineItem->setPrice(new CalculatedPrice(
            19.999,
            59.997,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            3,
        ));

        $item = (new ItemFactory())->fromLineItem($lineItem);

        static::assertSame('SW-100', $item['item_id']);
        static::assertSame('Testprodukt', $item['item_name']);
        static::assertSame('ACME', $item['item_brand']);
        static::assertSame(3, $item['quantity']);
        // preis wird kaufmaennisch auf zwei stellen gerundet
        static::assertSame(20.0, $item['price']);
    }

    public function testFromLineItemAddsVariantFromPayloadOptions(): void
    {
        $lineItem = new LineItem('line-id', LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-id', 1);
        $lineItem->setLabel('Variantenprodukt');
        // entspricht dem core-payload (ProductCartProcessor: 'options' => getVariation())
        $lineItem->setPayload([
            'productNumber' => 'SW-200',
            'options' => [
                ['group' => 'Farbe', 'option' => 'Rot'],
                ['group' => 'Groesse', 'option' => 'L'],
            ],
        ]);
        $lineItem->setPrice(new CalculatedPrice(
            10.0,
            10.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            1,
        ));

        $item = (new ItemFactory())->fromLineItem($lineItem);

        static::assertSame('Farbe: Rot, Groesse: L', $item['item_variant']);
    }

    public function testFromLineItemFallsBackToReferencedId(): void
    {
        $lineItem = new LineItem('line-id', LineItem::PRODUCT_LINE_ITEM_TYPE, 'ref-id', 1);
        $lineItem->setLabel('Ohne Nummer');
        $lineItem->setPrice(new CalculatedPrice(
            10.0,
            10.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            1,
        ));

        $item = (new ItemFactory())->fromLineItem($lineItem);

        static::assertSame('ref-id', $item['item_id']);
        static::assertArrayNotHasKey('item_brand', $item);
    }
}
