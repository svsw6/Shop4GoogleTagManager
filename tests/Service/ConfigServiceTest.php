<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\PayloadValidator;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigServiceTest extends TestCase
{
    public function testReturnsDefaultsWhenNothingConfigured(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        // alle bool-werte unkonfiguriert -> null, getString -> leer
        $systemConfig->method('get')->willReturn(null);
        $systemConfig->method('getString')->willReturn('');

        $config = (new ConfigService($systemConfig, new PayloadValidator()))->getConfig('sales-channel-id');

        static::assertTrue($config->active);
        static::assertSame('', $config->containerId);
        static::assertFalse($config->debug);
        static::assertTrue($config->consentMode);
        // neues consent-modell: quelle/position/enhanced-conversions als enum-defaults
        static::assertSame('shopware', $config->consentSource);
        static::assertSame('head', $config->tagPosition);
        static::assertSame('off', $config->enhancedConversions);
        // checkout-sofort-laden ist opt-in
        static::assertFalse($config->eagerCheckoutLoad);
        // consent-wartezeit default
        static::assertSame(500, $config->consentWaitForUpdate);
        // werbebezogene cookies sind standardmaessig verfuegbar (opt-in, feuert nur nach consent)
        static::assertTrue($config->remarketing);
    }

    public function testReadsConfiguredValues(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(static function (string $key) {
            return match ($key) {
                'Shop4GoogleTagManager.config.active' => true,
                'Shop4GoogleTagManager.config.debug' => true,
                'Shop4GoogleTagManager.config.remarketing' => true,
                default => null,
            };
        });
        $systemConfig->method('getString')
            ->with('Shop4GoogleTagManager.config.containerId', 'sc')
            ->willReturn('  GTM-ABC123  ');

        $config = (new ConfigService($systemConfig, new PayloadValidator()))->getConfig('sc');

        static::assertTrue($config->active);
        static::assertTrue($config->debug);
        static::assertTrue($config->remarketing);
        // container-id wird getrimmt
        static::assertSame('GTM-ABC123', $config->containerId);
    }

    /**
     * defense in depth: nur ein wohlgeformter container landet in der ausgabe – ein nicht
     * passender (z.b. injizierter) wert wird serverseitig zu '' verworfen (-> nicht operational).
     */
    #[DataProvider('containerIdProvider')]
    public function testContainerIdIsNormalizedDefensively(string $stored, string $expected): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturn(null);
        $systemConfig->method('getString')->willReturn($stored);

        $config = (new ConfigService($systemConfig, new PayloadValidator()))->getConfig('sc');

        static::assertSame($expected, $config->containerId);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function containerIdProvider(): array
    {
        return [
            'gueltig' => ['GTM-ABC123', 'GTM-ABC123'],
            'getrimmt' => ['  GTM-OK1  ', 'GTM-OK1'],
            'kleinbuchstaben verworfen' => ['GTM-abc123', ''],
            'falscher prefix verworfen' => ['GA-123456', ''],
            'ohne prefix verworfen' => ['ABC123', ''],
            'script-injection verworfen' => ['GTM-X"><script>alert(1)</script>', ''],
            'zu lang verworfen' => ['GTM-' . str_repeat('A', 21), ''],
            'leer' => ['', ''],
        ];
    }

    #[DataProvider('waitForUpdateProvider')]
    public function testWaitForUpdateIsReadAndClamped(mixed $stored, int $expected): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(
            static fn (string $key) => $key === 'Shop4GoogleTagManager.config.consentWaitForUpdate' ? $stored : null,
        );
        $systemConfig->method('getString')->willReturn('');

        $config = (new ConfigService($systemConfig, new PayloadValidator()))->getConfig('sc');

        static::assertSame($expected, $config->consentWaitForUpdate);
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function waitForUpdateProvider(): array
    {
        return [
            'default bei null' => [null, 500],
            'null-wert erlaubt' => [0, 0],
            'normaler wert' => [200, 200],
            'numerischer string' => ['750', 750],
            'ueber max geklemmt' => [99999, 10000],
            'negativ -> 0' => [-50, 0],
            'nicht numerisch -> default' => ['abc', 500],
        ];
    }

    public function testStandardEventOverrideAcceptsValidValues(): void
    {
        $service = $this->configServiceWithOverride([
            'ga4Event' => 'view_promotion',
            'payload' => ['affiliation' => 'Onlineshop', 'nested' => ['a' => 1]],
        ]);

        $override = $service->getStandardEventOverride('view_item', 'sc');

        static::assertSame('view_promotion', $override['ga4Event']);
        static::assertSame(['affiliation' => 'Onlineshop', 'nested' => ['a' => 1]], $override['payload']);
    }

    public function testStandardEventOverrideRejectsInvalidGa4Name(): void
    {
        // ungueltiger name (leerzeichen/sonderzeichen) -> verworfen, faellt auf '' zurueck
        $service = $this->configServiceWithOverride([
            'ga4Event' => 'view promotion!',
            'payload' => [],
        ]);

        static::assertSame('', $service->getStandardEventOverride('view_item', 'sc')['ga4Event']);
    }

    public function testStandardEventOverrideSanitizesPayload(): void
    {
        $service = $this->configServiceWithOverride([
            'ga4Event' => '',
            'payload' => [
                'valid' => 'ok',
                'event' => 'darf-nicht',           // reserviert -> raus
                'ecommerce' => ['x' => 1],         // reserviert -> raus
                'bad key!' => 'x',                 // ungueltiger schluessel -> raus
                'tooDeep' => ['a' => ['b' => ['c' => 1]]], // zu tief -> raus
            ],
        ]);

        $payload = $service->getStandardEventOverride('purchase', 'sc')['payload'];

        static::assertSame(['valid' => 'ok'], $payload);
    }

    /**
     * @param array<string, mixed> $override
     */
    private function configServiceWithOverride(array $override): ConfigService
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('get')->willReturnCallback(
            static fn (string $key) => str_contains($key, 'stdOverride.') ? $override : null,
        );
        $systemConfig->method('getString')->willReturn('');

        return new ConfigService($systemConfig, new PayloadValidator());
    }
}
