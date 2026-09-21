<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\ConsentService;
use Shop4GoogleTagManager\Service\CustomEventService;
use Shop4GoogleTagManager\Service\DataLayerService;
use Shop4GoogleTagManager\Service\GtmRenderContextFactory;
use Shop4GoogleTagManager\Service\PendingEventStore;
use Shop4GoogleTagManager\Struct\PluginConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;

class GtmRenderContextFactoryTest extends TestCase
{
    public function testConsentDefaultStaysDeniedOnCacheableRoutes(): void
    {
        // die wichtigste invariante: eine fuer einen einwilligenden besucher gerenderte seite
        // darf nicht mit "granted" im cache landen und dann an ablehnende ausgeliefert werden
        $request = $this->request();
        $request->cookies->set(ConsentService::COOKIE_ANALYTICS, '1');

        $params = $this->factory()->build($this->context(), $request);

        static::assertSame('denied', $params['s4gtmConsentDefault']['analytics_storage']);
    }

    public function testConsentDefaultIsRaisedOnNoStoreRoutes(): void
    {
        $request = $this->request(noStore: true);
        $request->cookies->set(ConsentService::COOKIE_ANALYTICS, '1');

        $params = $this->factory()->build($this->context(), $request);

        static::assertSame('granted', $params['s4gtmConsentDefault']['analytics_storage']);
        static::assertSame('denied', $params['s4gtmConsentDefault']['ad_storage']);
    }

    public function testConsentDefaultStaysDeniedWithoutCookies(): void
    {
        $params = $this->factory()->build($this->context(), $this->request(noStore: true));

        static::assertSame('denied', $params['s4gtmConsentDefault']['analytics_storage']);
    }

    public function testWaitForUpdateKeepsConfiguredValueOnCheckout(): void
    {
        // frueher wurde hier 0 gesetzt - zusammen mit "denied" nahm das GTM jede schonfrist
        $request = $this->request(noStore: true, route: 'frontend.checkout.finish.page');
        $request->cookies->set(ConsentService::COOKIE_ANALYTICS, '1');

        $params = $this->factory(eagerCheckoutLoad: true)->build($this->context(), $request);

        static::assertSame(500, $params['s4gtmConsentDefault']['wait_for_update']);
        static::assertTrue($params['s4gtmContainerLoaded']);
    }

    public function testEagerCheckoutNeedsBothTheRouteAndAConsentCookie(): void
    {
        $factory = $this->factory(eagerCheckoutLoad: true);

        $withoutCookie = $this->request(noStore: true, route: 'frontend.checkout.finish.page');
        static::assertFalse($factory->build($this->context(), $withoutCookie)['s4gtmContainerLoaded']);

        $wrongRoute = $this->request(noStore: true, route: 'frontend.account.home.page');
        $wrongRoute->cookies->set(ConsentService::COOKIE_ANALYTICS, '1');
        static::assertFalse($factory->build($this->context(), $wrongRoute)['s4gtmContainerLoaded']);
    }

    public function testUserDataUrlIsSuppressedWhenThereIsNothingToFetch(): void
    {
        $params = $this->factory(customerTracking: false)->build($this->context(), $this->request());

        static::assertFalse($params['s4gtmHasUserData']);
    }

    public function testUserDataUrlIsOfferedForCustomerTracking(): void
    {
        $params = $this->factory()->build($this->context(), $this->request());

        static::assertTrue($params['s4gtmHasUserData']);
    }

    public function testEnhancedConversionsCookieIsOnlyExposedWhenEnabled(): void
    {
        static::assertNull(
            $this->factory()->build($this->context(), $this->request())['s4gtmEnhancedConversionsCookie'],
        );

        static::assertSame(
            ConsentService::COOKIE_ENHANCED,
            $this->factory(enhancedConversions: 'email')
                ->build($this->context(), $this->request())['s4gtmEnhancedConversionsCookie'],
        );
    }

    private function factory(
        bool $eagerCheckoutLoad = false,
        bool $customerTracking = true,
        string $enhancedConversions = 'off',
    ): GtmRenderContextFactory {
        $config = new PluginConfig(
            active: true,
            containerId: 'GTM-ABC123',
            debug: false,
            dataLayerEnabled: true,
            enhancedEcommerce: true,
            checkoutTracking: true,
            remarketing: true,
            userIdTracking: false,
            customerTracking: $customerTracking,
            trackContactForm: false,
            trackNewsletter: true,
            trackCustomForms: false,
            consentSource: PluginConfig::SOURCE_SHOPWARE,
            enhancedConversions: $enhancedConversions,
            eagerCheckoutLoad: $eagerCheckoutLoad,
        );

        $configService = $this->createMock(ConfigService::class);
        $configService->method('getConfig')->willReturn($config);
        $configService->method('getClientEventConfig')->willReturn([]);

        $customEvents = $this->createMock(CustomEventService::class);
        $customEvents->method('getEventsForContext')->willReturn([]);

        $pending = $this->createMock(PendingEventStore::class);
        $pending->method('hasPending')->willReturn(false);

        return new GtmRenderContextFactory(
            $configService,
            new ConsentService($configService),
            new DataLayerService($configService),
            $customEvents,
            $pending,
        );
    }

    private function request(bool $noStore = false, string $route = 'frontend.home.page'): Request
    {
        $request = Request::create('https://shop.example/');
        $request->attributes->set('_route', $route);

        if ($noStore) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);
        }

        return $request;
    }

    private function context(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('01900000000000000000000000000000');
        $salesChannel->setName('Testkanal');
        $salesChannel->setNavigationCategoryId('01900000000000000000000000000002');

        $currency = new CurrencyEntity();
        $currency->setId('01900000000000000000000000000001');
        $currency->setIsoCode('EUR');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getCustomer')->willReturn(null);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }
}
