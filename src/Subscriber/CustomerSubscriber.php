<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Subscriber;

use Psr\Log\LoggerInterface;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\PendingEventStore;
use Shop4GoogleTagManager\Service\StandardEventDecorator;
use Shop4GoogleTagManager\Struct\DataLayerEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly PendingEventStore $pendingEventStore,
        private readonly StandardEventDecorator $standardEventDecorator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onLogin',
            CustomerLogoutEvent::class => 'onLogout',
            CustomerRegisterEvent::class => 'onRegister',
            NewsletterConfirmEvent::class => 'onNewsletterConfirm',
        ];
    }

    public function onLogin(CustomerLoginEvent $event): void
    {
        $this->guard('login', function () use ($event): void {
            if (!$this->isCustomerEventActive('login', $event->getSalesChannelId())) {
                return;
            }

            $this->addEvent(new DataLayerEvent('login', ['method' => 'shopware']), $event->getSalesChannelId());
        });
    }

    public function onLogout(CustomerLogoutEvent $event): void
    {
        $this->guard('logout', function () use ($event): void {
            if (!$this->isCustomerEventActive('logout', $event->getSalesChannelId())) {
                return;
            }

            $this->addEvent(new DataLayerEvent('logout'), $event->getSalesChannelId());
        });
    }

    public function onRegister(CustomerRegisterEvent $event): void
    {
        $this->guard('sign_up', function () use ($event): void {
            if (!$this->isCustomerEventActive('sign_up', $event->getSalesChannelId())) {
                return;
            }

            $this->addEvent(new DataLayerEvent('sign_up', ['method' => 'shopware']), $event->getSalesChannelId());
        });
    }

    public function onNewsletterConfirm(NewsletterConfirmEvent $event): void
    {
        $this->guard('newsletter_signup', function () use ($event): void {
            $config = $this->configService->getConfig($event->getSalesChannelId());
            if (!$config->isOperational()
                || !$config->dataLayerEnabled
                || !$config->trackNewsletter
                || !$this->configService->isStandardEventEnabled('newsletter_signup', $event->getSalesChannelId())
            ) {
                return;
            }

            $this->addEvent(new DataLayerEvent('newsletter_signup', ['method' => 'double-opt-in']), $event->getSalesChannelId());
        });
    }

    private function addEvent(DataLayerEvent $event, string $salesChannelId): void
    {
        $this->pendingEventStore->add($this->standardEventDecorator->apply($event, $salesChannelId));
    }

    private function guard(string $eventName, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $exception) {
            $this->logger->error('s4gtm: aufbau von ' . $eventName . ' fehlgeschlagen', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function isCustomerEventActive(string $event, string $salesChannelId): bool
    {
        $config = $this->configService->getConfig($salesChannelId);

        return $config->isOperational()
            && $config->dataLayerEnabled
            && $this->configService->isStandardEventEnabled($event, $salesChannelId);
    }
}
