<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\ConsentService;
use Shop4GoogleTagManager\Struct\PluginConfig;

class ConsentServiceTest extends TestCase
{
    public function testDefaultStateIsDeniedForShopwareSource(): void
    {
        $service = new ConsentService($this->configService(consentSource: 'shopware', remarketing: true));

        $state = $service->getDefaultConsentState('sc');

        static::assertSame('denied', $state['ad_storage']);
        static::assertSame('denied', $state['analytics_storage']);
        static::assertSame('denied', $state['ad_user_data']);
        static::assertSame('denied', $state['ad_personalization']);
        // personalisierung ist nicht technisch notwendig -> vor consent ebenfalls "denied"
        static::assertSame('denied', $state['personalization_storage']);
        // security und functionality bleiben grundsaetzlich erlaubt
        static::assertSame('granted', $state['security_storage']);
        static::assertSame('granted', $state['functionality_storage']);
        static::assertSame(500, $state['wait_for_update']);
    }

    public function testWaitForUpdateUsesConfiguredValue(): void
    {
        $service = new ConsentService(
            $this->configService(consentSource: 'shopware', remarketing: true, consentWaitForUpdate: 200),
        );

        static::assertSame(200, $service->getDefaultConsentState('sc')['wait_for_update']);
    }

    public function testWaitForUpdateOverrideWinsOverConfig(): void
    {
        // im checkout (eager) wird 0 uebergeben -> keine kuenstliche verzoegerung, unabhaengig vom config-wert
        $service = new ConsentService(
            $this->configService(consentSource: 'shopware', remarketing: true, consentWaitForUpdate: 500),
        );

        static::assertSame(0, $service->getDefaultConsentState('sc', 0)['wait_for_update']);
    }

    public function testDefaultStateIsDeniedForExternalCmpSource(): void
    {
        // auch der externe cmp gilt als verwaltete quelle -> defaults bleiben "denied"
        $service = new ConsentService($this->configService(consentSource: 'cmp', remarketing: true));

        $state = $service->getDefaultConsentState('sc');

        static::assertSame('denied', $state['ad_storage']);
        static::assertSame('denied', $state['analytics_storage']);
        static::assertSame('denied', $state['personalization_storage']);
    }

    public function testDefaultStateIsGrantedWhenConsentNotManaged(): void
    {
        // quelle "keine cookies" -> bewusst kein consent-management
        $service = new ConsentService($this->configService(consentSource: 'none', remarketing: true));

        $state = $service->getDefaultConsentState('sc');

        static::assertSame('granted', $state['ad_storage']);
        static::assertSame('granted', $state['analytics_storage']);
        static::assertSame('granted', $state['personalization_storage']);
    }

    public function testAdSignalsStayDeniedWhenRemarketingDisabled(): void
    {
        // ohne consent-management waeren die signale "granted" – remarketing=false haelt die
        // werbebezogenen signale dennoch auf "denied", analytics bleibt unberuehrt.
        $service = new ConsentService($this->configService(consentSource: 'none', remarketing: false));

        $state = $service->getDefaultConsentState('sc');

        static::assertSame('denied', $state['ad_storage']);
        static::assertSame('denied', $state['ad_user_data']);
        static::assertSame('denied', $state['ad_personalization']);
        // personalisierung folgt den werbebezogenen signalen -> ohne remarketing "denied"
        static::assertSame('denied', $state['personalization_storage']);
        static::assertSame('granted', $state['analytics_storage']);
    }

    public function testCookieMappingCoversAllPurposesWhenRemarketingEnabled(): void
    {
        $service = new ConsentService($this->configService(consentSource: 'shopware', remarketing: true));

        $mapping = $service->getCookieConsentMapping('sc');

        // drei einzeln steuerbare zwecke
        static::assertCount(3, $mapping);
        static::assertSame(['analytics_storage'], $mapping[ConsentService::COOKIE_ANALYTICS]);
        static::assertContains('ad_storage', $mapping[ConsentService::COOKIE_MARKETING]);
        static::assertContains('ad_personalization', $mapping[ConsentService::COOKIE_MARKETING]);
        // personalisierung wird zusammen mit der marketing-gruppe angehoben
        static::assertContains('personalization_storage', $mapping[ConsentService::COOKIE_MARKETING]);
        // erweitertes conversion-tracking ist ein eigener cookie mit eigenem signal
        static::assertSame(['ad_user_data'], $mapping[ConsentService::COOKIE_ENHANCED]);
        static::assertNotContains('ad_user_data', $mapping[ConsentService::COOKIE_MARKETING]);
    }

    public function testCookieMappingOmitsAdPurposesWhenRemarketingDisabled(): void
    {
        $service = new ConsentService($this->configService(consentSource: 'shopware', remarketing: false));

        $mapping = $service->getCookieConsentMapping('sc');

        // ohne remarketing (und ohne enhanced conversions) bleibt nur der analytics-zweck abgebildet
        static::assertCount(1, $mapping);
        static::assertSame(['analytics_storage'], $mapping[ConsentService::COOKIE_ANALYTICS]);
        static::assertArrayNotHasKey(ConsentService::COOKIE_MARKETING, $mapping);
        static::assertArrayNotHasKey(ConsentService::COOKIE_ENHANCED, $mapping);
    }

    public function testCookieMappingAddsAdUserDataForEnhancedConversionsWithoutRemarketing(): void
    {
        // enhanced conversions braucht ad_user_data -> der enhanced-cookie wird auch ohne remarketing abgebildet
        $service = new ConsentService(
            $this->configService(consentSource: 'shopware', remarketing: false, enhancedConversions: 'email'),
        );

        $mapping = $service->getCookieConsentMapping('sc');

        static::assertCount(2, $mapping);
        static::assertSame(['analytics_storage'], $mapping[ConsentService::COOKIE_ANALYTICS]);
        static::assertSame(['ad_user_data'], $mapping[ConsentService::COOKIE_ENHANCED]);
        static::assertArrayNotHasKey(ConsentService::COOKIE_MARKETING, $mapping);
    }

    private function configService(string $consentSource, bool $remarketing, string $enhancedConversions = 'off', int $consentWaitForUpdate = 500): ConfigService
    {
        $configService = $this->createMock(ConfigService::class);
        $configService->method('getConfig')->willReturn(new PluginConfig(
            active: true,
            containerId: 'GTM-ABC123',
            debug: false,
            dataLayerEnabled: true,
            enhancedEcommerce: true,
            checkoutTracking: true,
            remarketing: $remarketing,
            userIdTracking: false,
            customerTracking: true,
            trackContactForm: false,
            trackNewsletter: true,
            trackCustomForms: false,
            consentSource: $consentSource,
            enhancedConversions: $enhancedConversions,
            consentWaitForUpdate: $consentWaitForUpdate,
        ));

        return $configService;
    }
}
