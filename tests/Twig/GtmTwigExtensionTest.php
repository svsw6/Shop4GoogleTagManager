<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\Ecommerce\ItemFactory;
use Shop4GoogleTagManager\Twig\GtmTwigExtension;

class GtmTwigExtensionTest extends TestCase
{
    public function testRenderItemJsonReturnsEmptyObjectForNullProduct(): void
    {
        $extension = new GtmTwigExtension($this->createMock(ItemFactory::class));

        static::assertSame('{}', $extension->renderItemJson(null));
    }

    public function testRegistersItemJsonFunction(): void
    {
        $extension = new GtmTwigExtension($this->createMock(ItemFactory::class));

        $names = array_map(
            static fn ($function): string => $function->getName(),
            $extension->getFunctions(),
        );

        static::assertContains('s4gtm_item_json', $names);
        // die pending-event-queue wird seit A1 serverseitig (GtmRenderContextFactory) konsumiert,
        // nicht mehr ueber eine seiteneffekt-behaftete twig-funktion
        static::assertNotContains('s4gtm_consume_pending_events', $names);
    }
}
