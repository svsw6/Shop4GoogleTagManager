<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\ConfigService;

/**
 * Die Konfigurations-Defaults existieren zweifach: backend-seitig in ConfigService::DEFAULTS und in
 * der Admin-Oberflaeche in s4gtm-settings/page/s4gtm-settings/index.js (getDefaults()). Laeuft eine
 * Seite auseinander (neuer/entfernter Schalter), wuerde ein Feld nur an einer Stelle gespeichert oder
 * angezeigt. Dieser Test sichert die Schluessel-Paritaet ab – analog zum EventCatalogParityTest.
 */
class ConfigDefaultsParityTest extends TestCase
{
    private const ADMIN_PAGE = '/src/Resources/app/administration/src/module/s4gtm-settings/page/s4gtm-settings/index.js';

    public function testAdminDefaultsMatchBackend(): void
    {
        // die container-id wird backend-seitig gesondert behandelt (getContainerId, nicht in DEFAULTS),
        // im admin ist sie teil von getDefaults() -> hier ergaenzen, damit beide seiten vergleichbar sind
        $backendKeys = array_merge(
            array_keys(ConfigService::DEFAULTS),
            array_keys(ConfigService::ENUM_DEFAULTS),
            array_keys(ConfigService::INT_DEFAULTS),
            ['containerId'],
        );

        static::assertSame(
            $this->sorted($backendKeys),
            $this->sorted($this->adminDefaultKeys()),
            'getDefaults() in s4gtm-settings/index.js weicht von ConfigService::DEFAULTS (+ containerId) ab.',
        );
    }

    /**
     * @return string[]
     */
    private function adminDefaultKeys(): array
    {
        $js = file_get_contents(dirname(__DIR__) . self::ADMIN_PAGE);
        static::assertIsString($js, 's4gtm-settings/index.js konnte nicht gelesen werden.');

        static::assertSame(
            1,
            preg_match('/getDefaults\(\)\s*\{\s*return\s*\{(.*?)\};/s', $js, $block),
            'getDefaults()-Block in index.js nicht gefunden.',
        );

        // flache objekt-keys: "active:", "containerId:", ...
        preg_match_all('/(\w+):/', $block[1], $matches);

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
