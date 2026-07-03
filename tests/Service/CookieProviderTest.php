<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\ConsentService;
use Shop4GoogleTagManager\Service\CookieProvider;
use Shop4GoogleTagManager\Struct\PluginConfig;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class CookieProviderTest extends TestCase
{
    public function testAddsAnalyticsAndAdEntriesWhenRemarketingEnabled(): void
    {
        $provider = new CookieProvider($this->decorated(), $this->configService(remarketing: true), new RequestStack());

        $cookies = $this->collectCookies($provider->getCookieGroups());

        static::assertContains(ConsentService::COOKIE_ANALYTICS, $cookies);
        static::assertContains(ConsentService::COOKIE_MARKETING, $cookies);
        static::assertContains(ConsentService::COOKIE_ENHANCED, $cookies);
    }

    public function testOmitsAdEntriesWhenRemarketingDisabled(): void
    {
        $provider = new CookieProvider($this->decorated(), $this->configService(remarketing: false), new RequestStack());

        $cookies = $this->collectCookies($provider->getCookieGroups());

        // analytics bleibt, werbebezogene cookies werden ohne remarketing nicht eingeblendet
        static::assertContains(ConsentService::COOKIE_ANALYTICS, $cookies);
        static::assertNotContains(ConsentService::COOKIE_MARKETING, $cookies);
        static::assertNotContains(ConsentService::COOKIE_ENHANCED, $cookies);
    }

    public function testKeepsDecoratedGroupsIntact(): void
    {
        $provider = new CookieProvider($this->decorated(), $this->configService(remarketing: true), new RequestStack());

        $groups = $provider->getCookieGroups();

        // die urspruenglichen gruppen des dekorierten providers bleiben erhalten
        static::assertCount(2, $groups);
    }

    public function testUsesSalesChannelFromCurrentRequest(): void
    {
        $configService = $this->createMock(ConfigService::class);
        // erwartet, dass die kanal-id aus dem request an getConfig durchgereicht wird
        $configService->expects(static::atLeastOnce())
            ->method('getConfig')
            ->with('sc-from-request')
            ->willReturn($this->config(remarketing: true));

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'sc-from-request');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new CookieProvider($this->decorated(), $configService, $requestStack);

        $cookies = $this->collectCookies($provider->getCookieGroups());
        static::assertContains(ConsentService::COOKIE_MARKETING, $cookies);
    }

    /**
     * @param array<int, array<string, mixed>> $groups
     *
     * @return list<string>
     */
    private function collectCookies(array $groups): array
    {
        $cookies = [];
        foreach ($groups as $group) {
            foreach ($group['entries'] ?? [] as $entry) {
                if (isset($entry['cookie'])) {
                    $cookies[] = $entry['cookie'];
                }
            }
        }

        return $cookies;
    }

    private function decorated(): CookieProviderInterface
    {
        $decorated = $this->createMock(CookieProviderInterface::class);
        $decorated->method('getCookieGroups')->willReturn([
            ['snippet_name' => 'cookie.groupStatistical', 'entries' => []],
            ['snippet_name' => 'cookie.groupMarketing', 'entries' => []],
        ]);

        return $decorated;
    }

    private function configService(bool $remarketing): ConfigService
    {
        $configService = $this->createMock(ConfigService::class);
        $configService->method('getConfig')->willReturn($this->config($remarketing));

        return $configService;
    }

    public function testAddsEnhancedCookieForEnhancedConversionsWithoutRemarketing(): void
    {
        // EC braucht ad_user_data -> der enhanced-cookie wird auch ohne remarketing eingeblendet
        $configService = $this->createMock(ConfigService::class);
        $configService->method('getConfig')->willReturn(
            $this->config(remarketing: false, enhancedConversions: 'email'),
        );

        $provider = new CookieProvider($this->decorated(), $configService, new RequestStack());

        $cookies = $this->collectCookies($provider->getCookieGroups());

        static::assertContains(ConsentService::COOKIE_ENHANCED, $cookies);
        static::assertNotContains(ConsentService::COOKIE_MARKETING, $cookies);
    }

    private function config(bool $remarketing, string $enhancedConversions = 'off'): PluginConfig
    {
        return new PluginConfig(
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
            enhancedConversions: $enhancedConversions,
        );
    }
}
