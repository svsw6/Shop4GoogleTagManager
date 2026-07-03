<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Subscriber;

use Psr\Log\LoggerInterface;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\CustomEventService;
use Shop4GoogleTagManager\Service\Ecommerce\CartDataBuilder;
use Shop4GoogleTagManager\Service\Ecommerce\OrderDataBuilder;
use Shop4GoogleTagManager\Service\GtmEventCatalog;
use Shop4GoogleTagManager\Service\StandardEventDecorator;
use Shop4GoogleTagManager\Struct\GtmPageExtension;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;

class CheckoutPageSubscriber extends AbstractGtmPageSubscriber
{
    public function __construct(
        ConfigService $configService,
        private readonly CartDataBuilder $cartDataBuilder,
        private readonly OrderDataBuilder $orderDataBuilder,
        CustomEventService $customEventService,
        StandardEventDecorator $standardEventDecorator,
        LoggerInterface $logger,
    ) {
        parent::__construct($configService, $customEventService, $standardEventDecorator, $logger);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class => 'onCartPage',
            CheckoutConfirmPageLoadedEvent::class => 'onConfirmPage',
            CheckoutFinishPageLoadedEvent::class => 'onFinishPage',
        ];
    }

    public function onCartPage(CheckoutCartPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $salesChannelId = $context->getSalesChannel()->getId();
        if (!$this->isActive($salesChannelId)) {
            return;
        }

        try {
            $page = $event->getPage();
            $extension = new GtmPageExtension();

            if ($this->isStandardCheckoutEvent('view_cart', $salesChannelId)) {
                $extension->addEvent($this->standardEventDecorator->apply(
                    $this->cartDataBuilder->buildViewCart($page->getCart(), $context),
                    $salesChannelId,
                ));
            }

            $this->appendCustomEvents($extension, GtmEventCatalog::CONTEXT_CART, $context);
            $this->attach($page, $extension);
        } catch (\Throwable $exception) {
            $this->logError('view_cart', $exception);
        }
    }

    public function onConfirmPage(CheckoutConfirmPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $salesChannelId = $context->getSalesChannel()->getId();
        if (!$this->isActive($salesChannelId)) {
            return;
        }

        try {
            $page = $event->getPage();
            $cart = $page->getCart();
            $extension = new GtmPageExtension();

            if ($this->isStandardCheckoutEvent('begin_checkout', $salesChannelId)) {
                $extension->addEvent($this->standardEventDecorator->apply(
                    $this->cartDataBuilder->buildBeginCheckout($cart, $context),
                    $salesChannelId,
                ));
            }
            if ($this->isStandardCheckoutEvent('add_shipping_info', $salesChannelId)) {
                $extension->addEvent($this->standardEventDecorator->apply(
                    $this->cartDataBuilder->buildAddShippingInfo($cart, $context),
                    $salesChannelId,
                ));
            }
            if ($this->isStandardCheckoutEvent('add_payment_info', $salesChannelId)) {
                $extension->addEvent($this->standardEventDecorator->apply(
                    $this->cartDataBuilder->buildAddPaymentInfo($cart, $context),
                    $salesChannelId,
                ));
            }

            $this->appendCustomEvents($extension, GtmEventCatalog::CONTEXT_CHECKOUT, $context);
            $this->attach($page, $extension);
        } catch (\Throwable $exception) {
            $this->logError('begin_checkout', $exception);
        }
    }

    public function onFinishPage(CheckoutFinishPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $salesChannelId = $context->getSalesChannel()->getId();
        if (!$this->isActive($salesChannelId)) {
            return;
        }

        try {
            $page = $event->getPage();
            $order = $page->getOrder();
            $extension = new GtmPageExtension();

            if ($this->isStandardCheckoutEvent('purchase', $salesChannelId)) {
                $extension->addEvent($this->standardEventDecorator->apply(
                    $this->orderDataBuilder->buildPurchase($order, $context),
                    $salesChannelId,
                ));
            }

            $this->appendCustomEvents($extension, GtmEventCatalog::CONTEXT_PURCHASE, $context);
            $this->attach($page, $extension);
        } catch (\Throwable $exception) {
            $this->logError('purchase', $exception);
        }
    }

    private function isStandardCheckoutEvent(string $event, string $salesChannelId): bool
    {
        return $this->configService->getConfig($salesChannelId)->checkoutTracking
            && $this->configService->isStandardEventEnabled($event, $salesChannelId);
    }
}
