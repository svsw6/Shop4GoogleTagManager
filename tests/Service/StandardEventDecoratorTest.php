<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\StandardEventDecorator;
use Shop4GoogleTagManager\Struct\DataLayerEvent;

class StandardEventDecoratorTest extends TestCase
{
    public function testWithoutOverrideEventStaysUnchanged(): void
    {
        $decorator = new StandardEventDecorator($this->configServiceReturning(['ga4Event' => '', 'payload' => []]));

        $result = $decorator->apply(new DataLayerEvent('view_item', ['ecommerce' => ['value' => 10]]), 'sc');

        static::assertSame('view_item', $result->event);
        static::assertSame(['ecommerce' => ['value' => 10]], $result->data);
    }

    public function testGa4NameOverrideRenamesEvent(): void
    {
        $decorator = new StandardEventDecorator($this->configServiceReturning(['ga4Event' => 'view_product', 'payload' => []]));

        $result = $decorator->apply(new DataLayerEvent('view_item', ['ecommerce' => ['value' => 10]]), 'sc');

        static::assertSame('view_product', $result->event);
        // die automatisch ermittelten daten bleiben erhalten
        static::assertSame(['ecommerce' => ['value' => 10]], $result->data);
    }

    public function testPayloadIsMergedIntoEventData(): void
    {
        $decorator = new StandardEventDecorator($this->configServiceReturning([
            'ga4Event' => '',
            'payload' => ['affiliation' => 'Onlineshop', 'coupon' => 'SOMMER10'],
        ]));

        $result = $decorator->apply(new DataLayerEvent('purchase', ['ecommerce' => ['value' => 99]]), 'sc');

        static::assertSame('purchase', $result->event);
        static::assertSame('Onlineshop', $result->data['affiliation']);
        static::assertSame('SOMMER10', $result->data['coupon']);
        // der ecommerce-knoten der standard-daten bleibt bestehen
        static::assertSame(['value' => 99], $result->data['ecommerce']);
    }

    private function configServiceReturning(array $override): ConfigService
    {
        $configService = $this->createMock(ConfigService::class);
        $configService->method('getStandardEventOverride')->willReturn($override);

        return $configService;
    }
}
