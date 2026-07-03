<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\GtmEventCatalog;

/**
 * Der Event-Katalog existiert sowohl im Backend (GtmEventCatalog) als auch in der Admin
 * (constant/event-catalog.js). Statt einen Laufzeit-Endpoint zu bauen, sichert dieser Test
 * die Paritaet beider Quellen ab – laeuft eine Seite auseinander, schlaegt der Test fehl.
 */
class EventCatalogParityTest extends TestCase
{
    private const JS_CATALOG = '/src/Resources/app/administration/src/module/s4gtm-settings/constant/event-catalog.js';

    public function testCustomContextsMatchBackend(): void
    {
        $jsContexts = $this->extractStrings('CUSTOM_CONTEXTS\s*=\s*\[(.*?)\]');

        static::assertSame(
            $this->sorted(GtmEventCatalog::CUSTOM_CONTEXTS),
            $this->sorted($jsContexts),
            'CUSTOM_CONTEXTS in event-catalog.js weicht von GtmEventCatalog::CUSTOM_CONTEXTS ab.',
        );
    }

    public function testStandardEventsMatchBackend(): void
    {
        $jsEvents = $this->extractStrings('STANDARD_EVENTS\s*=\s*\{(.*?)\};');

        static::assertSame(
            $this->sorted(array_keys(GtmEventCatalog::STANDARD_EVENTS)),
            $this->sorted($jsEvents),
            'STANDARD_EVENTS in event-catalog.js weicht von GtmEventCatalog::STANDARD_EVENTS ab.',
        );
    }

    /**
     * @return string[]
     */
    private function extractStrings(string $blockPattern): array
    {
        $js = file_get_contents(dirname(__DIR__) . self::JS_CATALOG);
        static::assertIsString($js, 'event-catalog.js konnte nicht gelesen werden.');

        static::assertSame(1, preg_match('/' . $blockPattern . '/s', $js, $block));

        // einfache string-werte sind einfach-gequotet; objekt-keys (product, listing ...) nicht
        preg_match_all("/'([a-z_0-9]+)'/", $block[1], $matches);

        return $matches[1];
    }

    /**
     * @param string[] $values
     *
     * @return string[]
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
