<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CookieProvider implements CookieProviderInterface
{
    private const GROUP_STATISTICAL = 'cookie.groupStatistical';
    private const GROUP_MARKETING = 'cookie.groupMarketing';

    public function __construct(
        private readonly CookieProviderInterface $decorated,
        private readonly ConfigService $configService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getCookieGroups(): array
    {
        $groups = $this->decorated->getCookieGroups();

        $config = $this->configService->getConfig($this->resolveSalesChannelId());
        $remarketing = $config->remarketing;
        $enhanced = $remarketing || $config->enhancedConversionsEnabled();

        foreach ($groups as &$group) {
            $name = $group['snippet_name'] ?? '';

            if ($name === self::GROUP_STATISTICAL) {
                $group['entries'][] = $this->analyticsEntry();
            } elseif ($name === self::GROUP_MARKETING) {
                if ($remarketing) {
                    $group['entries'][] = $this->marketingEntry();
                }
                if ($enhanced) {
                    $group['entries'][] = $this->enhancedEntry();
                }
            }
        }
        unset($group);

        return $groups;
    }

    private function resolveSalesChannelId(): ?string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return null;
        }

        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        return is_string($salesChannelId) ? $salesChannelId : null;
    }

    private function analyticsEntry(): array
    {
        return [
            'snippet_name' => 'shop4Gtm.cookie.analytics.name',
            'snippet_description' => 'shop4Gtm.cookie.analytics.description',
            'cookie' => ConsentService::COOKIE_ANALYTICS,
            'value' => '1',
            'expiration' => '30',
        ];
    }

    private function marketingEntry(): array
    {
        return [
            'snippet_name' => 'shop4Gtm.cookie.marketing.name',
            'snippet_description' => 'shop4Gtm.cookie.marketing.description',
            'cookie' => ConsentService::COOKIE_MARKETING,
            'value' => '1',
            'expiration' => '30',
        ];
    }

    private function enhancedEntry(): array
    {
        return [
            'snippet_name' => 'shop4Gtm.cookie.enhanced.name',
            'snippet_description' => 'shop4Gtm.cookie.enhanced.description',
            'cookie' => ConsentService::COOKIE_ENHANCED,
            'value' => '1',
            'expiration' => '30',
        ];
    }
}
