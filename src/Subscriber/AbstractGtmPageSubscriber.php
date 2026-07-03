<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Subscriber;

use Psr\Log\LoggerInterface;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\CustomEventService;
use Shop4GoogleTagManager\Service\StandardEventDecorator;
use Shop4GoogleTagManager\Struct\GtmPageExtension;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractGtmPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected readonly ConfigService $configService,
        protected readonly CustomEventService $customEventService,
        protected readonly StandardEventDecorator $standardEventDecorator,
        protected readonly LoggerInterface $logger,
    ) {
    }

    protected function isActive(string $salesChannelId): bool
    {
        $config = $this->configService->getConfig($salesChannelId);

        return $config->isOperational() && $config->dataLayerEnabled;
    }

    protected function appendCustomEvents(GtmPageExtension $extension, string $eventContext, SalesChannelContext $context): void
    {
        $events = $this->customEventService->getEventsForContext(
            $eventContext,
            $context->getSalesChannel()->getId(),
            $context->getContext(),
        );

        foreach ($events as $event) {
            $extension->addEvent($event);
        }
    }

    protected function attach(Page $page, GtmPageExtension $extension): void
    {
        if (!$extension->hasEvents()) {
            return;
        }

        $page->addExtension(GtmPageExtension::EXTENSION_NAME, $extension);
    }

    protected function logError(string $eventName, \Throwable $exception): void
    {
        $this->logger->error('s4gtm: aufbau von ' . $eventName . ' fehlgeschlagen', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
