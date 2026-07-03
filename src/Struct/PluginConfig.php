<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Struct;

use Shopware\Core\Framework\Struct\Struct;

class PluginConfig extends Struct
{
    public const CONTAINER_ID_PATTERN = '/^GTM-[A-Z0-9]{1,20}$/';
    public const SOURCE_SHOPWARE = 'shopware';
    public const SOURCE_CMP = 'cmp';
    public const SOURCE_NONE = 'none';
    public const SOURCES = [self::SOURCE_SHOPWARE, self::SOURCE_CMP, self::SOURCE_NONE];
    public const POSITION_HEAD = 'head';
    public const POSITION_BODY = 'body';
    public const POSITIONS = [self::POSITION_HEAD, self::POSITION_BODY];
    public const EC_OFF = 'off';
    public const EC_EMAIL = 'email';
    public const EC_FULL = 'full';
    public const EC_MODES = [self::EC_OFF, self::EC_EMAIL, self::EC_FULL];
    public const DEFAULT_WAIT_FOR_UPDATE = 500;
    public const MAX_WAIT_FOR_UPDATE = 10000;

    public function __construct(
        public readonly bool $active,
        public readonly string $containerId,
        public readonly bool $debug,
        public readonly bool $dataLayerEnabled,
        public readonly bool $enhancedEcommerce,
        public readonly bool $checkoutTracking,
        public readonly bool $remarketing,
        public readonly bool $userIdTracking,
        public readonly bool $customerTracking,
        public readonly bool $trackContactForm,
        public readonly bool $trackNewsletter,
        public readonly bool $trackCustomForms,
        public readonly string $consentSource = self::SOURCE_SHOPWARE,
        public readonly bool $consentMode = true,
        public readonly bool $advancedConsentMode = false,
        public readonly string $tagPosition = self::POSITION_HEAD,
        public readonly string $enhancedConversions = self::EC_OFF,
        public readonly bool $anonymizeSearchTerm = true,
        public readonly bool $eagerCheckoutLoad = false,
        public readonly int $consentWaitForUpdate = self::DEFAULT_WAIT_FOR_UPDATE,
    ) {
    }

    public function isOperational(): bool
    {
        return $this->active && $this->isValidContainerId();
    }

    public function isValidContainerId(): bool
    {
        return (bool) preg_match(self::CONTAINER_ID_PATTERN, $this->containerId);
    }

    public function isConsentManaged(): bool
    {
        return $this->consentSource !== self::SOURCE_NONE;
    }

    public function isExternalCmp(): bool
    {
        return $this->consentSource === self::SOURCE_CMP;
    }

    public function sendsConsentSignals(): bool
    {
        return $this->isConsentManaged() && $this->consentMode;
    }

    public function isAdvancedConsentMode(): bool
    {
        return $this->isConsentManaged() && $this->consentMode && $this->advancedConsentMode;
    }

    public function autoLoadsContainer(): bool
    {
        return !$this->isConsentManaged() || $this->isAdvancedConsentMode();
    }

    public function tagInBody(): bool
    {
        return $this->tagPosition === self::POSITION_BODY;
    }

    public function tagInHead(): bool
    {
        return !$this->tagInBody();
    }

    public function enhancedConversionsEnabled(): bool
    {
        return $this->enhancedConversions !== self::EC_OFF;
    }

    public function enhancedConversionsFull(): bool
    {
        return $this->enhancedConversions === self::EC_FULL;
    }
}
