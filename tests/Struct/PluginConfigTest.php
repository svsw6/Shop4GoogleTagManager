<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Struct;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Struct\PluginConfig;

class PluginConfigTest extends TestCase
{
    #[DataProvider('containerIdProvider')]
    public function testIsValidContainerId(string $containerId, bool $expected): void
    {
        $config = $this->createConfig(active: true, containerId: $containerId);

        static::assertSame($expected, $config->isValidContainerId());
    }

    public function testIsOperationalRequiresActiveAndValidContainer(): void
    {
        static::assertTrue($this->createConfig(true, 'GTM-ABC123')->isOperational());
        static::assertFalse($this->createConfig(false, 'GTM-ABC123')->isOperational());
        static::assertFalse($this->createConfig(true, '')->isOperational());
        static::assertFalse($this->createConfig(true, 'INVALID')->isOperational());
    }

    #[DataProvider('consentManagedProvider')]
    public function testIsConsentManaged(string $consentSource, bool $expected): void
    {
        $config = $this->createConfig(active: true, containerId: 'GTM-ABC123', consentSource: $consentSource);

        static::assertSame($expected, $config->isConsentManaged());
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function consentManagedProvider(): array
    {
        return [
            // nur "keine cookies" ist ungated; shopware-banner und externer cmp gaten beide
            'shopware-banner' => ['shopware', true],
            'externer cmp' => ['cmp', true],
            'keine cookies' => ['none', false],
        ];
    }

    public function testIsExternalCmpOnlyForCmpSource(): void
    {
        static::assertTrue($this->createConfig(true, 'GTM-ABC123', consentSource: 'cmp')->isExternalCmp());
        static::assertFalse($this->createConfig(true, 'GTM-ABC123', consentSource: 'shopware')->isExternalCmp());
        static::assertFalse($this->createConfig(true, 'GTM-ABC123', consentSource: 'none')->isExternalCmp());
    }

    #[DataProvider('advancedConsentProvider')]
    public function testAdvancedConsentModeAndAutoLoad(
        string $consentSource,
        bool $consentMode,
        bool $advancedConsentMode,
        bool $expectedAdvanced,
        bool $expectedAutoLoad,
    ): void {
        $config = $this->createConfig(
            active: true,
            containerId: 'GTM-ABC123',
            consentSource: $consentSource,
            consentMode: $consentMode,
            advancedConsentMode: $advancedConsentMode,
        );

        static::assertSame($expectedAdvanced, $config->isAdvancedConsentMode());
        static::assertSame($expectedAutoLoad, $config->autoLoadsContainer());
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: bool, 3: bool, 4: bool}>
     */
    public static function advancedConsentProvider(): array
    {
        // [consentSource, consentMode, advancedConsentMode, isAdvanced, autoLoadsContainer]
        return [
            // advanced wirkt nur bei verwalteter quelle + aktivem consent mode v2
            'shopware advanced wirksam' => ['shopware', true, true, true, true],
            // consent mode an, advanced aus -> container erst nach einwilligung
            'shopware ohne advanced blockiert' => ['shopware', true, false, false, false],
            // ohne consent mode fehlen die denied-signale -> advanced unwirksam, container blockiert
            'shopware advanced ohne consent mode' => ['shopware', false, true, false, false],
            // auch der externe cmp kann advanced fahren
            'cmp advanced wirksam' => ['cmp', true, true, true, true],
            // keine cookies -> container laedt ohnehin sofort, advanced ist gegenstandslos
            'keine cookies laedt sofort' => ['none', true, true, false, true],
        ];
    }

    #[DataProvider('sendsSignalsProvider')]
    public function testSendsConsentSignals(string $consentSource, bool $consentMode, bool $expected): void
    {
        $config = $this->createConfig(
            active: true,
            containerId: 'GTM-ABC123',
            consentSource: $consentSource,
            consentMode: $consentMode,
        );

        static::assertSame($expected, $config->sendsConsentSignals());
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function sendsSignalsProvider(): array
    {
        return [
            'verwaltet + consent mode' => ['shopware', true, true],
            'verwaltet ohne consent mode' => ['shopware', false, false],
            'keine cookies sendet nie signale' => ['none', true, false],
        ];
    }

    public function testTagPositionHelpers(): void
    {
        $head = $this->createConfig(true, 'GTM-ABC123', tagPosition: 'head');
        static::assertTrue($head->tagInHead());
        static::assertFalse($head->tagInBody());

        $body = $this->createConfig(true, 'GTM-ABC123', tagPosition: 'body');
        static::assertTrue($body->tagInBody());
        static::assertFalse($body->tagInHead());
    }

    public function testEnhancedConversionsHelpers(): void
    {
        static::assertFalse($this->createConfig(true, 'GTM-ABC123', enhancedConversions: 'off')->enhancedConversionsEnabled());
        static::assertTrue($this->createConfig(true, 'GTM-ABC123', enhancedConversions: 'email')->enhancedConversionsEnabled());

        static::assertFalse($this->createConfig(true, 'GTM-ABC123', enhancedConversions: 'email')->enhancedConversionsFull());
        static::assertTrue($this->createConfig(true, 'GTM-ABC123', enhancedConversions: 'full')->enhancedConversionsFull());
    }

    public static function containerIdProvider(): array
    {
        return [
            'gueltig' => ['GTM-ABC123', true],
            'gueltig nur buchstaben' => ['GTM-ABCDEF', true],
            'maximale laenge' => ['GTM-' . str_repeat('A', 20), true],
            'leer' => ['', false],
            'falscher prefix' => ['GA-123456', false],
            'kleinbuchstaben' => ['GTM-abc123', false],
            'ohne prefix' => ['ABC123', false],
            'zu lang verworfen' => ['GTM-' . str_repeat('A', 21), false],
        ];
    }

    private function createConfig(
        bool $active,
        string $containerId,
        string $consentSource = 'shopware',
        bool $consentMode = true,
        bool $advancedConsentMode = false,
        string $tagPosition = 'head',
        string $enhancedConversions = 'off',
    ): PluginConfig {
        return new PluginConfig(
            active: $active,
            containerId: $containerId,
            debug: false,
            dataLayerEnabled: true,
            enhancedEcommerce: true,
            checkoutTracking: true,
            remarketing: false,
            userIdTracking: false,
            customerTracking: true,
            trackContactForm: false,
            trackNewsletter: true,
            trackCustomForms: false,
            consentSource: $consentSource,
            consentMode: $consentMode,
            advancedConsentMode: $advancedConsentMode,
            tagPosition: $tagPosition,
            enhancedConversions: $enhancedConversions,
        );
    }
}
