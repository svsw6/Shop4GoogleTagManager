<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service\Ecommerce;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\Ecommerce\ItemFactory;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;

/**
 * GA4 erwartet den Kategoriepfad von grob nach fein. Shopware liefert den Breadcrumb
 * inklusive des Verkaufskanal-Einstiegs, der im Shop gar nicht sichtbar ist.
 */
class ItemFactoryCategoryPathTest extends TestCase
{
    private const ROOT_ID = '01900000000000000000000000000000';

    public function testBreadcrumbBecomesCategoryLevels(): void
    {
        $product = $this->product([
            self::ROOT_ID => 'Katalog',
            'cat-1' => 'Lebensmittel',
            'cat-2' => 'Backwaren',
            'cat-3' => 'Brot',
        ]);

        $item = (new ItemFactory())->fromProduct($product, 1, null, self::ROOT_ID);

        // der einstieg des verkaufskanals gehoert nicht in den pfad
        static::assertSame('Lebensmittel', $item['item_category']);
        static::assertSame('Backwaren', $item['item_category2']);
        static::assertSame('Brot', $item['item_category3']);
        static::assertArrayNotHasKey('item_category4', $item);
    }

    public function testRootIsKeptWhenNoRootIdIsKnown(): void
    {
        $product = $this->product([
            self::ROOT_ID => 'Katalog',
            'cat-1' => 'Lebensmittel',
        ]);

        $item = (new ItemFactory())->fromProduct($product);

        static::assertSame('Katalog', $item['item_category']);
        static::assertSame('Lebensmittel', $item['item_category2']);
    }

    public function testPathIsCappedAtFiveLevels(): void
    {
        $product = $this->product([
            self::ROOT_ID => 'Katalog',
            'c1' => 'Eins',
            'c2' => 'Zwei',
            'c3' => 'Drei',
            'c4' => 'Vier',
            'c5' => 'Fuenf',
            'c6' => 'Sechs',
        ]);

        $item = (new ItemFactory())->fromProduct($product, 1, null, self::ROOT_ID);

        static::assertSame('Eins', $item['item_category']);
        static::assertSame('Fuenf', $item['item_category5']);
        // GA4 kennt nur fuenf ebenen
        static::assertArrayNotHasKey('item_category6', $item);
    }

    public function testFallsBackToTheCategoryNameWithoutBreadcrumb(): void
    {
        $category = new CategoryEntity();
        $category->setId('cat-1');
        $category->setName('Einzelkategorie');

        $item = (new ItemFactory())->fromProduct($this->product(null, $category), 1, null, self::ROOT_ID);

        static::assertSame('Einzelkategorie', $item['item_category']);
        static::assertArrayNotHasKey('item_category2', $item);
    }

    public function testNoCategoryMeansNoCategoryFields(): void
    {
        $item = (new ItemFactory())->fromProduct($this->product(null, null), 1, null, self::ROOT_ID);

        static::assertArrayNotHasKey('item_category', $item);
    }

    /**
     * @param array<string, string>|null $breadcrumb
     */
    private function product(?array $breadcrumb, ?CategoryEntity $category = null): SalesChannelProductEntity
    {
        if ($breadcrumb !== null) {
            $category = new CategoryEntity();
            $category->setId(array_key_last($breadcrumb));
            $category->setName($breadcrumb[array_key_last($breadcrumb)]);
            // der breadcrumb ist ein uebersetztes feld - die DAL fuellt translated,
            // getPlainBreadcrumb() liest ausschliesslich von dort
            $category->setTranslated(['breadcrumb' => $breadcrumb]);
        }

        $product = new SalesChannelProductEntity();
        $product->setId('01900000000000000000000000000009');
        $product->setProductNumber('SW-CAT');
        $product->setName('Kategorieprodukt');
        $product->setCalculatedPrice(new CalculatedPrice(
            10.0,
            10.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            1,
        ));

        if ($category !== null) {
            $product->setSeoCategory($category);
        }

        return $product;
    }
}
