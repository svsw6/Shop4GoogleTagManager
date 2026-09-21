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

    /**
     * @param list<string> $grantedKeys
     *
     * @return array<string, string|int>
     */
    public function getDefaultConsentState(
        ?string $salesChannelId = null,
        ?int $waitForUpdate = null,
        array $grantedKeys = [],
    ): array {
        $config = $this->configService->getConfig($salesChannelId);
        $base = $config->isConsentManaged() ? 'denied' : 'granted';
        $adBase = $config->remarketing ? $base : 'denied';

        $state = [
            'ad_storage' => $adBase,
            'ad_user_data' => $adBase,
            'ad_personalization' => $adBase,
            'analytics_storage' => $base,
            'personalization_storage' => $adBase,
            'functionality_storage' => 'granted',
            'security_storage' => 'granted',
        ];

        foreach ($grantedKeys as $key) {
            if (isset($state[$key])) {
                $state[$key] = 'granted';
            }
        }

        $state['wait_for_update'] = $waitForUpdate ?? $config->consentWaitForUpdate;

        return $state;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getCookieConsentMapping(?string $salesChannelId = null): array
    {
        $config = $this->configService->getConfig($salesChannelId);

        $mapping = [
            self::COOKIE_ANALYTICS => ['analytics_storage'],
        ];

        if ($config->remarketing) {
            $mapping[self::COOKIE_MARKETING] = [
                'ad_storage',
                'ad_user_data',
                'ad_personalization',
                'personalization_storage',
            ];
        }

        if ($config->enhancedConversionsEnabled()) {
            $mapping[self::COOKIE_ENHANCED] = ['ad_user_data'];
        }

        return $mapping;
    }

    public function getEnhancedConversionsCookie(?string $salesChannelId = null): ?string
    {
        return $this->configService->getConfig($salesChannelId)->enhancedConversionsEnabled()
            ? self::COOKIE_ENHANCED
            : null;
    }

    /**
     * @param array<string, mixed> $cookies
     *
     * @return list<string>
     */
    public function resolveGrantedConsentKeys(array $cookies, ?string $salesChannelId = null): array
    {
        $granted = [];

        foreach ($this->getCookieConsentMapping($salesChannelId) as $cookieName => $consentKeys) {
            if (($cookies[$cookieName] ?? null) !== '1') {
                continue;
            }
            foreach ($consentKeys as $consentKey) {
                $granted[$consentKey] = true;
            }
        }

        return array_keys($granted);
    }
}
