<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service;

use Shop4GoogleTagManager\Struct\DataLayerEvent;
use Shop4GoogleTagManager\Struct\PluginConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class GtmRenderContextFactory
{
    private const NONCE_PATTERN = '/^[A-Za-z0-9+\/_-]{8,256}={0,2}$/';

    public function __construct(
        private readonly ConfigService $configService,
        private readonly ConsentService $consentService,
        private readonly DataLayerService $dataLayerService,
        private readonly CustomEventService $customEventService,
        private readonly PendingEventStore $pendingEventStore,
    ) {
    }

    public function build(SalesChannelContext $context, Request $request, string $view = ''): array
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $config = $this->configService->getConfig($salesChannelId);

        return [
            's4gtmConfig' => $config,
            's4gtmConsentDefault' => $this->consentService->getDefaultConsentState(
                $salesChannelId,
                null,
                $this->resolveServerSideGrants($request, $salesChannelId),
            ),
            's4gtmConsentMapping' => $this->consentService->getCookieConsentMapping($salesChannelId),
            's4gtmEnhancedConversionsCookie' => $this->consentService->getEnhancedConversionsCookie($salesChannelId),
            's4gtmBaseDataLayer' => $this->dataLayerService->buildBaseDataLayer($context, $request->getLocale()),
            's4gtmGlobalEvents' => ($config->dataLayerEnabled && !$request->isXmlHttpRequest())
                ? $this->resolveGlobalEvents($salesChannelId, $context->getContext())
                : [],
            's4gtmClientEvents' => $this->configService->getClientEventConfig($salesChannelId),
            's4gtmCspNonce' => $this->resolveCspNonce($request),
            's4gtmHasUserData' => $this->hasUserData($config),
            's4gtmNavigationCategoryId' => $context->getSalesChannel()->getNavigationCategoryId(),
            's4gtmPendingCookie' => PendingEventStore::FLAG_COOKIE,
            's4gtmHasPendingEvents' => $this->shouldSignalPending($config, $request, $view)
                && $this->pendingEventStore->hasPending(),
            's4gtmContainerLoaded' => $config->autoLoadsContainer()
                || $this->shouldEagerLoadCheckout($config, $request, $salesChannelId),
        ];
    }

    /**
     * Bereits per Cookie erteilte Zwecke duerfen nur dann in den `consent default` wandern,
     * wenn die Antwort garantiert nicht im HTTP-Cache landet. Sonst koennte eine fuer einen
     * einwilligenden Besucher gerenderte Seite jemandem ausgeliefert werden, der abgelehnt hat.
     *
     * @return list<string>
     */
    private function resolveServerSideGrants(Request $request, ?string $salesChannelId): array
    {
        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE)) {
            return [];
        }

        return $this->consentService->resolveGrantedConsentKeys($request->cookies->all(), $salesChannelId);
    }

    private function shouldEagerLoadCheckout(PluginConfig $config, Request $request, ?string $salesChannelId): bool
    {
        if (!$config->eagerCheckoutLoad || !$config->isConsentManaged() || $config->autoLoadsContainer()) {
            return false;
        }

        $route = (string) $request->attributes->get('_route');
        if (!str_starts_with($route, 'frontend.checkout.')) {
            return false;
        }

        return $this->consentService->resolveGrantedConsentKeys($request->cookies->all(), $salesChannelId) !== [];
    }

    /**
     * Ohne kundenbezogene Felder und ohne Enhanced Conversions liefert der Endpunkt
     * garantiert ein leeres Objekt - dann gar nicht erst anfragen.
     */
    private function hasUserData(PluginConfig $config): bool
    {
        return $config->customerTracking
            || $config->userIdTracking
            || $config->enhancedConversionsEnabled();
    }

    private function shouldSignalPending(PluginConfig $config, Request $request, string $view): bool
    {
        if (!$config->dataLayerEnabled || $request->isXmlHttpRequest()) {
            return false;
        }

        return !str_contains($view, '/page/error/');
    }

    private function resolveCspNonce(Request $request): string
    {
        $nonce = $request->attributes->get('csp_nonce');

        return (is_string($nonce) && preg_match(self::NONCE_PATTERN, $nonce) === 1) ? $nonce : '';
    }

    private function resolveGlobalEvents(string $salesChannelId, Context $context): array
    {
        $events = $this->customEventService->getEventsForContext(
            GtmEventCatalog::CONTEXT_GLOBAL,
            $salesChannelId,
            $context,
        );

        return array_map(
            static fn (DataLayerEvent $event): array => $event->jsonSerialize(),
            $events,
        );
    }
}
