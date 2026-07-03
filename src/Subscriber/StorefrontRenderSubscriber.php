<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Subscriber;

use Psr\Log\LoggerInterface;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\GtmRenderContextFactory;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StorefrontRenderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly GtmRenderContextFactory $renderContextFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onRender',
        ];
    }

    public function onRender(StorefrontRenderEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $config = $this->configService->getConfig($context->getSalesChannel()->getId());

        if (!$config->isOperational()) {
            return;
        }

        try {
            foreach ($this->renderContextFactory->build($context, $event->getRequest(), $event->getView()) as $parameter => $value) {
                $event->setParameter($parameter, $value);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('s4gtm: aufbau der render-parameter fehlgeschlagen', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
