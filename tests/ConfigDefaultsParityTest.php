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
        // container-id und server-container-url werden backend-seitig gesondert behandelt (eigene
        // getter mit validierung, nicht in DEFAULTS); im admin sind sie teil von getDefaults()
        $backendKeys = array_merge(
            array_keys(ConfigService::DEFAULTS),
            array_keys(ConfigService::ENUM_DEFAULTS),
            array_keys(ConfigService::INT_DEFAULTS),
            ['containerId', 'serverContainerUrl'],
        );

        static::assertSame(
            $this->sorted($backendKeys),
            $this->sorted($this->adminDefaultKeys()),
            'getDefaults() in s4gtm-settings/index.js weicht von ConfigService::DEFAULTS (+ containerId) ab.',
        );
    }

    public function testAdminDefaultValuesMatchBackend(): void
    {
        // die schluessel-paritaet allein reicht nicht: laufen die WERTE auseinander, zeigt die
        // admin-oberflaeche fuer einen noch nie gespeicherten kanal etwas anderes an, als der
        // server tatsaechlich anwendet.
        $expected = ConfigService::DEFAULTS;
        foreach (ConfigService::ENUM_DEFAULTS as $key => [, $default]) {
            $expected[$key] = $default;
        }
        foreach (ConfigService::INT_DEFAULTS as $key => $default) {
            $expected[$key] = $default;
        }
        $expected['containerId'] = '';
        $expected['serverContainerUrl'] = '';
        ksort($expected);

        $actual = $this->adminDefaultValues();
        ksort($actual);

        static::assertSame(
            $expected,
            $actual,
            'getDefaults() in s4gtm-settings/index.js weicht in den Werten von ConfigService ab.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function adminDefaultValues(): array
    {
        $block = $this->defaultsBlock();

        preg_match_all("/(\w+):\s*('[^']*'|true|false|-?\d+)\s*,/", $block, $matches, \PREG_SET_ORDER);

        $values = [];
        foreach ($matches as $match) {
            $values[$match[1]] = match (true) {
                $match[2] === 'true' => true,
                $match[2] === 'false' => false,
                str_starts_with($match[2], "'") => trim($match[2], "'"),
                default => (int) $match[2],
            };
        }

        return $values;
    }

    /**
     * @return string[]
     */
    private function adminDefaultKeys(): array
    {
        // flache objekt-keys: "active:", "containerId:", ...
        preg_match_all('/(\w+):/', $this->defaultsBlock(), $matches);

        return $matches[1];
    }

    private function defaultsBlock(): string
    {
        $js = file_get_contents(dirname(__DIR__) . self::ADMIN_PAGE);
        static::assertIsString($js, 's4gtm-settings/index.js konnte nicht gelesen werden.');

        static::assertSame(
            1,
            preg_match('/getDefaults\(\)\s*\{\s*return\s*\{(.*?)\};/s', $js, $block),
            'getDefaults()-Block in index.js nicht gefunden.',
        );

        return $block[1];
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
