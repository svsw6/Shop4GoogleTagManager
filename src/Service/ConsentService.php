<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service;

class ConsentService
{
    public const COOKIE_ANALYTICS = 's4gtm-analytics';
    public const COOKIE_MARKETING = 's4gtm-marketing';
    public const COOKIE_ENHANCED = 's4gtm-enhanced-conversions';

    public function __construct(
        private readonly ConfigService $configService,
    ) {
    }

    public function getDefaultConsentState(?string $salesChannelId = null, ?int $waitForUpdate = null): array
    {
        $config = $this->configService->getConfig($salesChannelId);
        $base = $config->isConsentManaged() ? 'denied' : 'granted';
        $adBase = $config->remarketing ? $base : 'denied';

        $wait = $waitForUpdate ?? $config->consentWaitForUpdate;

        return [
            'ad_storage' => $adBase,
            'ad_user_data' => $adBase,
            'ad_personalization' => $adBase,
            'analytics_storage' => $base,
            'personalization_storage' => $adBase,
            'functionality_storage' => 'granted',
            'security_storage' => 'granted',
            'wait_for_update' => $wait,
        ];
    }

    public function getCookieConsentMapping(?string $salesChannelId = null): array
    {
        $config = $this->configService->getConfig($salesChannelId);

        $mapping = [
            self::COOKIE_ANALYTICS => ['analytics_storage'],
        ];

        if ($config->remarketing) {
            $mapping[self::COOKIE_MARKETING] = ['ad_storage', 'ad_personalization', 'personalization_storage'];
            $mapping[self::COOKIE_ENHANCED] = ['ad_user_data'];
        } elseif ($config->enhancedConversionsEnabled()) {
            $mapping[self::COOKIE_ENHANCED] = ['ad_user_data'];
        }

        return $mapping;
    }
}
