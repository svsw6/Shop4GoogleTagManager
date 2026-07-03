<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Subscriber;

use Psr\Log\LoggerInterface;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\CustomEventService;
use Shop4GoogleTagManager\Service\Ecommerce\ProductDataBuilder;
use Shop4GoogleTagManager\Service\GtmEventCatalog;
use Shop4GoogleTagManager\Service\StandardEventDecorator;
use Shop4GoogleTagManager\Struct\GtmPageExtension;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;

class ProductPageSubscriber extends AbstractGtmPageSubscriber
{
    private const MAX_LIST_ITEMS = 24;

    public function __construct(
        ConfigService $configService,
        private readonly ProductDataBuilder $productDataBuilder,
        CustomEventService $customEventService,
        StandardEventDecorator $standardEventDecorator,
        LoggerInterface $logger,
    ) {
        parent::__construct($configService, $customEventService, $standardEventDecorator, $logger);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPage',
            NavigationPageLoadedEvent::class => 'onNavigationPage',
            SearchPageLoadedEvent::class => 'onSearchPage',
        ];
    }

    public function onProductPage(ProductPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $salesChannelId = $context->getSalesChannel()->getId();
        if (!$this->isActive($salesChannelId)) {
            return;
        }

        try {
            $page = $event->getPage();
            $extension = new GtmPageExtension();

            if ($this->isStandardEcommerceEvent('view_item', $salesChannelId)) {
                $extension->addEvent($this->standardEventDecorator->apply(
                    $this->productDataBuilder->buildViewItem($page->getProduct(), $context),
                    $salesChannelId,
                ));
            }

            $this->appendCustomEvents($extension, GtmEventCatalog::CONTEXT_PRODUCT, $context);
            $this->attach($page, $extension);
        } catch (\Throwable $exception) {
            $this->logError('view_item', $exception);
        }
    }

    public function onNavigationPage(NavigationPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $salesChannelId = $context->getSalesChannel()->getId();
        if (!$this->isActive($salesChannelId)) {
            return;
        }

        try {
            $page = $event->getPage();
            $extension = new GtmPageExtension();

            $listing = $this->extractListingFromCmsPage($page);
            if ($listing !== null
                && $listing->count() > 0
                && $this->isStandardEcommerceEvent('view_item_list', $salesChannelId)
            ) {
                $category = $page->getCategory();
                $categoryName = $category?->getTranslation('name') ?? $category?->getName();

                $extension->addEvent($this->standardEventDecorator->apply(
                    $this->productDataBuilder->buildViewItemList(
                        array_slice($listing->getElements(), 0, self::MAX_LIST_ITEMS),
                        $context,
                        $page->getNavigationId() ?? 'category',
                        $categoryName ?? $page->getMetaInformation()?->getMetaTitle() ?? 'Category',
                        $this->listingOffset($listing),
                        $categoryName,
                    ),
                    $salesChannelId,
                ));
            }

            $this->appendCustomEvents($extension, GtmEventCatalog::CONTEXT_LISTING, $context);
            $this->attach($page, $extension);
        } catch (\Throwable $exception) {
            $this->logError('view_item_list', $exception);
        }
    }

    public function onSearchPage(SearchPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $salesChannelId = $context->getSalesChannel()->getId();
        if (!$this->isActive($salesChannelId)) {
            return;
        }

        try {
            $page = $event->getPage();
            $extension = new GtmPageExtension();

            if ($this->isStandardEcommerceEvent('search', $salesChannelId)) {
                $listing = $page->getListing();
                $products = $listing !== null
                    ? array_slice($listing->getElements(), 0, self::MAX_LIST_ITEMS)
                    : [];
                $extension->addEvent($this->standardEventDecorator->apply(
                    $this->productDataBuilder->buildSearch(
                        $page->getSearchTerm(),
                        $products,
                        $context,
                        $listing !== null ? $this->listingOffset($listing) : 0,
                        !$this->configService->getConfig($salesChannelId)->anonymizeSearchTerm,
                    ),
                    $salesChannelId,
                ));
            }

            $this->appendCustomEvents($extension, GtmEventCatalog::CONTEXT_SEARCH, $context);
            $this->attach($page, $extension);
        } catch (\Throwable $exception) {
            $this->logError('search', $exception);
        }
    }

    private function extractListingFromCmsPage(NavigationPage $page): ?ProductListingResult
    {
        $cmsPage = $page->getCmsPage();
        if ($cmsPage === null || $cmsPage->getSections() === null) {
            return null;
        }

        foreach ($cmsPage->getSections() as $section) {
            foreach ($section->getBlocks() ?? [] as $block) {
                foreach ($block->getSlots() ?? [] as $slot) {
                    $data = $slot->getData();
                    if ($data instanceof ProductListingStruct && $data->getListing() !== null) {
                        return $data->getListing();
                    }
                }
            }
        }

        return null;
    }

    private function listingOffset(ProductListingResult $listing): int
    {
        $limit = $listing->getLimit() ?? 0;
        $page = $listing->getPage() ?? 1;

        return ($page - 1) * $limit;
    }

    private function isStandardEcommerceEvent(string $event, string $salesChannelId): bool
    {
        return $this->configService->getConfig($salesChannelId)->enhancedEcommerce
            && $this->configService->isStandardEventEnabled($event, $salesChannelId);
    }
}
