<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DataLayerService
{
    public function __construct(
        private readonly ConfigService $configService,
    ) {
    }

    public function buildBaseDataLayer(SalesChannelContext $context, ?string $localeCode = null): array
    {
        $salesChannel = $context->getSalesChannel();
        $customer = $context->getCustomer();

        return [
            'shop' => [
                'name' => $salesChannel->getName(),
                'language' => $localeCode,
                'currency' => $context->getCurrency()->getIsoCode(),
            ],
            'user' => [
                'loginStatus' => $customer !== null ? 'logged-in' : 'guest',
            ],
        ];
    }

    public function buildUserData(SalesChannelContext $context): array
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            return [];
        }

        $config = $this->configService->getConfig($context->getSalesChannel()->getId());
        $data = [];

        if ($config->customerTracking) {
            $data['customerGroup'] = $context->getCurrentCustomerGroup()->getTranslation('name')
                ?? $context->getCurrentCustomerGroup()->getName();
            $data['isGuest'] = $customer->getGuest();
        }

        if ($config->userIdTracking) {
            $data['userId'] = $customer->getId();
        }

        return $data;
    }

    public function buildEnhancedConversionData(SalesChannelContext $context): array
    {
        $config = $this->configService->getConfig($context->getSalesChannel()->getId());
        if (!$config->enhancedConversionsEnabled()) {
            return [];
        }

        $customer = $context->getCustomer();
        if ($customer === null) {
            return [];
        }

        $email = $this->hash($customer->getEmail());
        if ($email === null) {
            return [];
        }

        $data = ['sha256_email_address' => $email];

        if (!$config->enhancedConversionsFull()) {
            return $data;
        }

        $address = $customer->getDefaultBillingAddress();

        $phone = $this->hash($this->normalizePhone($address?->getPhoneNumber()));
        if ($phone !== null) {
            $data['sha256_phone_number'] = $phone;
        }

        $addressBlock = array_filter([
            'sha256_first_name' => $this->hash($customer->getFirstName()),
            'sha256_last_name' => $this->hash($customer->getLastName()),
            'sha256_street' => $this->hash($address?->getStreet()),
            'postal_code' => $this->clean($address?->getZipcode()),
            'city' => $this->clean($address?->getCity()),
            'region' => $this->clean($address?->getCountryState()?->getName()),
            'country' => $this->clean($address?->getCountry()?->getIso()),
        ], static fn ($v): bool => $v !== null);

        if ($addressBlock !== []) {
            $data['address'] = $addressBlock;
        }

        return $data;
    }

    private function hash(?string $value): ?string
    {
        $normalized = $value === null ? '' : mb_strtolower(trim($value));

        return $normalized === '' ? null : hash('sha256', $normalized);
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $cleaned = preg_replace('/(?!^\+)\D/', '', trim($phone));

        return is_string($cleaned) && $cleaned !== '' ? $cleaned : null;
    }

    private function clean(?string $value): ?string
    {
        $trimmed = $value === null ? '' : trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
