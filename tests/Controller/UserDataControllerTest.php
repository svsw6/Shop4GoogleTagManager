<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Controller\UserDataController;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\ConsentService;
use Shop4GoogleTagManager\Service\DataLayerService;
use Shop4GoogleTagManager\Service\PendingEventStore;
use Shop4GoogleTagManager\Struct\PluginConfig;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;

class UserDataControllerTest extends TestCase
{
    public function testReturnsUserDataWithNoStoreHeaders(): void
    {
        $dataLayer = $this->createMock(DataLayerService::class);
        $dataLayer->method('buildUserData')->willReturn(['customerId' => 'cust-1', 'isGuest' => false]);

        $controller = new UserDataController($this->configService(operational: true), $dataLayer, $this->pendingStore());

        $response = $controller->userData($this->request(analyticsGranted: true), $this->context());

        static::assertSame('{"user":{"customerId":"cust-1","isGuest":false}}', $response->getContent());

        // niemals (shared) cachen
        $cacheControl = $response->headers->get('Cache-Control');
        static::assertStringContainsString('no-store', (string) $cacheControl);
        static::assertStringContainsString('private', (string) $cacheControl);
    }

    public function testReturnsEmptyObjectWhenNotOperational(): void
    {
        $dataLayer = $this->createMock(DataLayerService::class);
        // bei nicht-operativem plugin darf gar nicht erst gebaut werden
        $dataLayer->expects(static::never())->method('buildUserData');

        $controller = new UserDataController($this->configService(operational: false), $dataLayer, $this->pendingStore());

        $response = $controller->userData($this->request(analyticsGranted: true), $this->context());

        // leeres objekt, nicht leeres array
        static::assertSame('{"user":{}}', $response->getContent());
    }

    public function testReturnsEmptyObjectWhenNoUserData(): void
    {
        $dataLayer = $this->createMock(DataLayerService::class);
        $dataLayer->method('buildUserData')->willReturn([]);

        $controller = new UserDataController($this->configService(operational: true), $dataLayer, $this->pendingStore());

        $response = $controller->userData($this->request(analyticsGranted: true), $this->context());

        static::assertSame('{"user":{}}', $response->getContent());
    }

    public function testReturnsEmptyObjectWhenAnalyticsConsentMissing(): void
    {
        $dataLayer = $this->createMock(DataLayerService::class);
        // ohne analytics-einwilligung duerfen identifizierende daten gar nicht gebaut werden
        $dataLayer->expects(static::never())->method('buildUserData');

        $controller = new UserDataController($this->configService(operational: true), $dataLayer, $this->pendingStore());

        $response = $controller->userData($this->request(analyticsGranted: false), $this->context());

        static::assertSame('{"user":{}}', $response->getContent());
    }

    public function testPendingEventsDrainsQueueWithNoStoreHeaders(): void
    {
        $pendingStore = $this->createMock(PendingEventStore::class);
        $pendingStore->expects(static::once())->method('pull')->willReturn([
            ['event' => 'login', 'method' => 'shopware'],
        ]);

        $controller = new UserDataController(
            $this->configService(operational: true),
            $this->createMock(DataLayerService::class),
            $pendingStore,
        );

        $response = $controller->pendingEvents($this->xhrRequest(), $this->context());

        static::assertSame('{"events":[{"event":"login","method":"shopware"}]}', $response->getContent());

        $cacheControl = (string) $response->headers->get('Cache-Control');
        static::assertStringContainsString('no-store', $cacheControl);
        static::assertStringContainsString('private', $cacheControl);
    }

    public function testPendingEventsKeepsQueueWhenNotOperational(): void
    {
        $pendingStore = $this->createMock(PendingEventStore::class);
        // bei nicht-operativem plugin die queue NICHT leeren -> events landen auf der naechsten seite
        $pendingStore->expects(static::never())->method('pull');

        $controller = new UserDataController(
            $this->configService(operational: false),
            $this->createMock(DataLayerService::class),
            $pendingStore,
        );

        $response = $controller->pendingEvents($this->xhrRequest(), $this->context());

        static::assertSame('{"events":[]}', $response->getContent());
    }

    public function testPendingEventsIgnoresCrossSiteRequestWithoutXhrHeader(): void
    {
        $pendingStore = $this->createMock(PendingEventStore::class);
        // ohne X-Requested-With-header (z.b. cross-site-GET via <img>) darf die queue NICHT geleert
        // werden -> kein CSRF-bedingtes wegloeschen vorgemerkter events
        $pendingStore->expects(static::never())->method('pull');

        $controller = new UserDataController(
            $this->configService(operational: true),
            $this->createMock(DataLayerService::class),
            $pendingStore,
        );

        $response = $controller->pendingEvents(new Request(), $this->context());

        static::assertSame('{"events":[]}', $response->getContent());

        $cacheControl = (string) $response->headers->get('Cache-Control');
        static::assertStringContainsString('no-store', $cacheControl);
    }

    private function pendingStore(): PendingEventStore
    {
        return $this->createMock(PendingEventStore::class);
    }

    private function configService(bool $operational): ConfigService
    {
        $configService = $this->createMock(ConfigService::class);
        $configService->method('getConfig')->willReturn(new PluginConfig(
            active: $operational,
            containerId: $operational ? 'GTM-ABC123' : '',
            debug: false,
            consentMode: true,
            dataLayerEnabled: true,
            enhancedEcommerce: true,
            checkoutTracking: true,
            remarketing: true,
            userIdTracking: false,
            customerTracking: true,
            trackContactForm: false,
            trackNewsletter: true,
            trackCustomForms: false,
        ));

        return $configService;
    }

    private function request(bool $analyticsGranted): Request
    {
        $cookies = $analyticsGranted ? [ConsentService::COOKIE_ANALYTICS => '1'] : [];

        return new Request([], [], [], $cookies);
    }

    // same-origin-XHR, wie das storefront-js es stellt (X-Requested-With: XMLHttpRequest)
    private function xhrRequest(): Request
    {
        return new Request([], [], [], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    }

    private function context(): SalesChannelContext
    {
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannel->method('getId')->willReturn('sc-1');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);

        return $context;
    }
}
