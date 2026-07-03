<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Controller;

use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\ConsentService;
use Shop4GoogleTagManager\Service\DataLayerService;
use Shop4GoogleTagManager\Service\PendingEventStore;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class UserDataController
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly DataLayerService $dataLayerService,
        private readonly PendingEventStore $pendingEventStore,
    ) {
    }

    #[Route(
        path: '/s4gtm/user-data',
        name: 's4gtm.user-data',
        methods: ['GET'],
        defaults: ['_httpCache' => false, 'XmlHttpRequest' => true],
    )]
    public function userData(Request $request, SalesChannelContext $context): JsonResponse
    {
        $config = $this->configService->getConfig($context->getSalesChannel()->getId());
        $unmanaged = !$config->isConsentManaged();

        $analyticsGranted = $unmanaged || $request->cookies->get(ConsentService::COOKIE_ANALYTICS) === '1';
        $data = ($config->isOperational() && $analyticsGranted)
            ? $this->dataLayerService->buildUserData($context)
            : [];

        $payload = ['user' => $data === [] ? new \stdClass() : $data];

        $adUserDataGranted = $unmanaged || $request->cookies->get(ConsentService::COOKIE_ENHANCED) === '1';
        if ($config->isOperational() && $config->enhancedConversionsEnabled() && $adUserDataGranted) {
            $ec = $this->dataLayerService->buildEnhancedConversionData($context);
            if ($ec !== []) {
                $payload['enhancedConversion'] = $ec;
            }
        }

        return $this->noStore(new JsonResponse($payload));
    }

    #[Route(
        path: '/s4gtm/pending-events',
        name: 's4gtm.pending-events',
        methods: ['POST'],
        defaults: ['_httpCache' => false, 'XmlHttpRequest' => true],
    )]
    public function pendingEvents(Request $request, SalesChannelContext $context): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->noStore(new JsonResponse(['events' => []]));
        }

        $config = $this->configService->getConfig($context->getSalesChannel()->getId());

        $events = ($config->isOperational() && $config->dataLayerEnabled)
            ? $this->pendingEventStore->pull()
            : [];

        return $this->noStore(new JsonResponse(['events' => $events]));
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->addCacheControlDirective('must-revalidate');

        return $response;
    }
}
