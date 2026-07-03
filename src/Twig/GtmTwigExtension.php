<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Twig;

use Shop4GoogleTagManager\Service\Ecommerce\ItemFactory;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GtmTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly ItemFactory $itemFactory,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('s4gtm_item_json', $this->renderItemJson(...)),
        ];
    }

    public function renderItemJson(?SalesChannelProductEntity $product, int $quantity = 1): string
    {
        if ($product === null) {
            return '{}';
        }

        $item = $this->itemFactory->fromProduct($product, $quantity);

        return json_encode(
            $item,
            JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_THROW_ON_ERROR,
        );
    }
}
