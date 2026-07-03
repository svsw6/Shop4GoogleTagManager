<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service;

use Shop4GoogleTagManager\Struct\DataLayerEvent;
use Shop4GoogleTagManager\Struct\PluginConfig;
use Shopware\Core\Framework\Context;
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
        $eagerCheckout = $this->shouldEagerLoadCheckout($config, $request, $salesChannelId);

        return [
            's4gtmConfig' => $config,
            's4gtmConsentDefault' => $this->consentService->getDefaultConsentState(
                $salesChannelId,
                $eagerCheckout ? 0 : null,
            ),
            's4gtmConsentMapping' => $this->consentService->getCookieConsentMapping($salesChannelId),
            's4gtmBaseDataLayer' => $this->dataLayerService->buildBaseDataLayer($context, $request->getLocale()),
            's4gtmGlobalEvents' => ($config->dataLayerEnabled && !$request->isXmlHttpRequest())
                ? $this->resolveGlobalEvents($salesChannelId, $context->getContext())
                : [],
            's4gtmClientEvents' => $this->configService->getClientEventConfig($salesChannelId),
            's4gtmCspNonce' => $this->resolveCspNonce($request),
            's4gtmHasPendingEvents' => $this->shouldSignalPending($config, $request, $view)
                && $this->pendingEventStore->hasPending(),
            's4gtmContainerLoaded' => $config->autoLoadsContainer() || $eagerCheckout,
        ];
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

        foreach (array_keys($this->consentService->getCookieConsentMapping($salesChannelId)) as $cookie) {
            if ($request->cookies->get($cookie) === '1') {
                return true;
            }
        }

        return false;
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
