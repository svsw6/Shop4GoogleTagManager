<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service;

use Shop4GoogleTagManager\Struct\PluginConfig;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    private const PREFIX = 'Shop4GoogleTagManager.config.';
    public const DEFAULTS = [
        'active' => true,
        'debug' => false,
        'consentMode' => true,
        'dataLayerEnabled' => true,
        'enhancedEcommerce' => true,
        'checkoutTracking' => true,
        'remarketing' => true,
        'userIdTracking' => false,
        'customerTracking' => true,
        'trackContactForm' => false,
        'trackNewsletter' => true,
        'trackCustomForms' => false,
        'anonymizeSearchTerm' => true,
        'advancedConsentMode' => false,
        'eagerCheckoutLoad' => false,
    ];
    public const INT_DEFAULTS = [
        'consentWaitForUpdate' => PluginConfig::DEFAULT_WAIT_FOR_UPDATE,
    ];
    public const ENUM_DEFAULTS = [
        'consentSource' => [PluginConfig::SOURCES, PluginConfig::SOURCE_SHOPWARE],
        'tagPosition' => [PluginConfig::POSITIONS, PluginConfig::POSITION_HEAD],
        'enhancedConversions' => [PluginConfig::EC_MODES, PluginConfig::EC_OFF],
    ];

    private const STD_EVENT_DEFAULT = true;

    /** @var array<string, PluginConfig> */
    private array $configCache = [];

    /** @var array<string, array{ga4Event: string, payload: array<string, mixed>}> */
    private array $overrideCache = [];

    /** @var array<string, array<string, array{active: bool, ga4Event: string, payload: array<string, mixed>}>> */
    private array $clientEventConfigCache = [];

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly PayloadValidator $payloadValidator,
    ) {
    }

    public function getConfig(?string $salesChannelId = null): PluginConfig
    {
        return $this->configCache[$salesChannelId ?? ''] ??= $this->buildConfig($salesChannelId);
    }

    private function buildConfig(?string $salesChannelId): PluginConfig
    {
        return new PluginConfig(
            active: $this->getBool('active', $salesChannelId),
            containerId: $this->getContainerId($salesChannelId),
            debug: $this->getBool('debug', $salesChannelId),
            dataLayerEnabled: $this->getBool('dataLayerEnabled', $salesChannelId),
            enhancedEcommerce: $this->getBool('enhancedEcommerce', $salesChannelId),
            checkoutTracking: $this->getBool('checkoutTracking', $salesChannelId),
            remarketing: $this->getBool('remarketing', $salesChannelId),
            userIdTracking: $this->getBool('userIdTracking', $salesChannelId),
            customerTracking: $this->getBool('customerTracking', $salesChannelId),
            trackContactForm: $this->getBool('trackContactForm', $salesChannelId),
            trackNewsletter: $this->getBool('trackNewsletter', $salesChannelId),
            trackCustomForms: $this->getBool('trackCustomForms', $salesChannelId),
            consentSource: $this->getEnum('consentSource', $salesChannelId),
            consentMode: $this->getBool('consentMode', $salesChannelId),
            advancedConsentMode: $this->getBool('advancedConsentMode', $salesChannelId),
            tagPosition: $this->getEnum('tagPosition', $salesChannelId),
            enhancedConversions: $this->getEnum('enhancedConversions', $salesChannelId),
            anonymizeSearchTerm: $this->getBool('anonymizeSearchTerm', $salesChannelId),
            eagerCheckoutLoad: $this->getBool('eagerCheckoutLoad', $salesChannelId),
            consentWaitForUpdate: $this->getInt('consentWaitForUpdate', $salesChannelId),
        );
    }

    public function isStandardEventEnabled(string $event, ?string $salesChannelId = null): bool
    {
        return $this->getBool('std.' . $event, $salesChannelId, self::STD_EVENT_DEFAULT);
    }

    public function getStandardEventOverride(string $event, ?string $salesChannelId = null): array
    {
        $cacheKey = ($salesChannelId ?? '') . '|' . $event;

        return $this->overrideCache[$cacheKey] ??= $this->buildStandardEventOverride($event, $salesChannelId);
    }

    private function buildStandardEventOverride(string $event, ?string $salesChannelId): array
    {
        $value = $this->systemConfigService->get(self::PREFIX . 'stdOverride.' . $event, $salesChannelId);

        $ga4Event = is_array($value) ? trim((string) ($value['ga4Event'] ?? '')) : '';

        return [
            'ga4Event' => $this->payloadValidator->isValidGa4EventName($ga4Event) ? $ga4Event : '',
            'payload' => $this->payloadValidator->sanitizePayload(
                is_array($value) ? ($value['payload'] ?? []) : [],
            ),
        ];
    }

    public function getClientEventConfig(?string $salesChannelId = null): array
    {
        return $this->clientEventConfigCache[$salesChannelId ?? ''] ??= $this->buildClientEventConfig($salesChannelId);
    }

    private function buildClientEventConfig(?string $salesChannelId): array
    {
        $config = [];
        foreach (GtmEventCatalog::CLIENT_EVENTS as $event) {
            $override = $this->getStandardEventOverride($event, $salesChannelId);
            $config[$event] = [
                'active' => $this->isStandardEventEnabled($event, $salesChannelId),
                'ga4Event' => $override['ga4Event'] !== '' ? $override['ga4Event'] : $event,
                'payload' => $override['payload'],
            ];
        }

        return $config;
    }

    private function getBool(string $key, ?string $salesChannelId, ?bool $default = null): bool
    {
        $value = $this->systemConfigService->get(self::PREFIX . $key, $salesChannelId);

        if ($value !== null) {
            return (bool) $value;
        }

        if ($default !== null) {
            return $default;
        }

        if (!\array_key_exists($key, self::DEFAULTS)) {
            throw new \LogicException(sprintf(
                'Kein Default fuer Config-Schluessel "%s": entweder in ConfigService::DEFAULTS aufnehmen oder $default uebergeben.',
                $key,
            ));
        }

        return self::DEFAULTS[$key];
    }

    private function getInt(string $key, ?string $salesChannelId): int
    {
        if (!isset(self::INT_DEFAULTS[$key])) {
            throw new \LogicException(sprintf('Kein Int-Default fuer Config-Schluessel "%s".', $key));
        }

        $value = $this->systemConfigService->get(self::PREFIX . $key, $salesChannelId);
        $int = is_numeric($value) ? (int) $value : self::INT_DEFAULTS[$key];

        return max(0, min(PluginConfig::MAX_WAIT_FOR_UPDATE, $int));
    }

    private function getEnum(string $key, ?string $salesChannelId): string
    {
        if (!isset(self::ENUM_DEFAULTS[$key])) {
            throw new \LogicException(sprintf('Kein Enum-Default fuer Config-Schluessel "%s".', $key));
        }

        [$allowed, $default] = self::ENUM_DEFAULTS[$key];
        $value = $this->systemConfigService->get(self::PREFIX . $key, $salesChannelId);

        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    private function getContainerId(?string $salesChannelId): string
    {
        $value = trim($this->systemConfigService->getString(self::PREFIX . 'containerId', $salesChannelId));

        return preg_match(PluginConfig::CONTAINER_ID_PATTERN, $value) === 1 ? $value : '';
    }
}
