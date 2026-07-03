<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Tests\Service;

use PHPUnit\Framework\TestCase;
use Shop4GoogleTagManager\Service\ConfigService;
use Shop4GoogleTagManager\Service\DataLayerService;
use Shop4GoogleTagManager\Struct\PluginConfig;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class DataLayerServiceTest extends TestCase
{
    public function testBaseDataLayerContainsNoIdentifyingData(): void
    {
        $service = new DataLayerService($this->configService(customerTracking: true, userIdTracking: true));

        $data = $service->buildBaseDataLayer($this->context($this->customer('cust-1', false)), 'de-DE');

        static::assertSame('Mein Shop', $data['shop']['name']);
        // interne salesChannelId wird bewusst NICHT ausgegeben (id-offenlegung im cachebaren html)
        static::assertArrayNotHasKey('salesChannelId', $data['shop']);
        static::assertSame('de-DE', $data['shop']['language']);
        static::assertSame('EUR', $data['shop']['currency']);
        // basis-layer enthaelt nur den groben login-status, KEINE identifizierenden merkmale
        static::assertSame('logged-in', $data['user']['loginStatus']);
        static::assertArrayNotHasKey('customerId', $data['user']);
        static::assertArrayNotHasKey('userId', $data['user']);
        static::assertArrayNotHasKey('customerGroup', $data['user']);
    }

    public function testBaseDataLayerLoginStatusGuestWithoutCustomer(): void
    {
        $service = new DataLayerService($this->configService(customerTracking: true, userIdTracking: true));

        $data = $service->buildBaseDataLayer($this->context(null));

        static::assertSame('guest', $data['user']['loginStatus']);
    }

    public function testUserDataEmptyWithoutCustomer(): void
    {
        $service = new DataLayerService($this->configService(customerTracking: true, userIdTracking: true));

        static::assertSame([], $service->buildUserData($this->context(null)));
    }

    public function testCustomerTrackingSendsOnlyNonIdentifyingFields(): void
    {
        $service = new DataLayerService($this->configService(customerTracking: true, userIdTracking: false));

        $data = $service->buildUserData($this->context($this->customer('cust-1', false)));

        static::assertSame('Stammkunde', $data['customerGroup']);
        static::assertFalse($data['isGuest']);
        // datenminimierung: die stabile personenkennung wird ohne user-id-tracking NICHT gesendet
        static::assertArrayNotHasKey('customerId', $data);
        static::assertArrayNotHasKey('userId', $data);
    }

    public function testUserDataContainsUserIdOnlyWhenUserIdTrackingEnabled(): void
    {
        $service = new DataLayerService($this->configService(customerTracking: false, userIdTracking: true));

        $data = $service->buildUserData($this->context($this->customer('cust-1', true)));

        static::assertSame('cust-1', $data['userId']);
        // ohne kunden-tracking keine kundengruppe; eine separate "customerId" gibt es nicht mehr
        static::assertArrayNotHasKey('customerId', $data);
        static::assertArrayNotHasKey('customerGroup', $data);
    }

    public function testUserIdAndGroupCombineWhenBothEnabled(): void
    {
        $service = new DataLayerService($this->configService(customerTracking: true, userIdTracking: true));

        $data = $service->buildUserData($this->context($this->customer('cust-1', false)));

        static::assertSame('cust-1', $data['userId']);
        static::assertSame('Stammkunde', $data['customerGroup']);
        static::assertArrayNotHasKey('customerId', $data);
    }

    public function testUserDataEmptyWhenAllTrackingDisabled(): void
    {
        $service = new DataLayerService($this->configService(customerTracking: false, userIdTracking: false));

        static::assertSame([], $service->buildUserData($this->context($this->customer('cust-1', false))));
    }

    public function testEnhancedConversionsEmptyWhenDisabled(): void
    {
        $service = new DataLayerService($this->configService(customerTracking: false, userIdTracking: false, enhancedConversions: 'off'));

        static::assertSame([], $service->buildEnhancedConversionData($this->context($this->customerWithData())));
    }

    public function testEnhancedConversionsEmptyWithoutCustomer(): void
    {
        $service = new DataLayerService($this->configService(customerTracking: false, userIdTracking: false, enhancedConversions: 'email'));

        static::assertSame([], $service->buildEnhancedConversionData($this->context(null)));
    }

    public function testEnhancedConversionsEmailHashesOnlyEmail(): void
    {
        $service = new DataLayerService($this->configService(customerTracking: false, userIdTracking: false, enhancedConversions: 'email'));

        $data = $service->buildEnhancedConversionData($this->context($this->customerWithData()));

        // e-mail wird normalisiert (lowercase/trim) und SHA-256-gehasht
        static::assertSame(hash('sha256', 'max@example.com'), $data['sha256_email_address']);
        // im email-modus keine weiteren felder
        static::assertArrayNotHasKey('sha256_phone_number', $data);
        static::assertArrayNotHasKey('address', $data);
    }

    public function testEnhancedConversionsFullHashesNameAndAddress(): void
    {
        $service = new DataLayerService($this->configService(customerTracking: false, userIdTracking: false, enhancedConversions: 'full'));

        $data = $service->buildEnhancedConversionData($this->context($this->customerWithData()));

        static::assertSame(hash('sha256', 'max@example.com'), $data['sha256_email_address']);
        // telefon wird auf ziffern/+ normalisiert, dann gehasht
        static::assertSame(hash('sha256', '+4915112345678'), $data['sha256_phone_number']);
        static::assertSame(hash('sha256', 'max'), $data['address']['sha256_first_name']);
        static::assertSame(hash('sha256', 'mustermann'), $data['address']['sha256_last_name']);
        static::assertSame(hash('sha256', 'musterstrasse 1'), $data['address']['sha256_street']);
        // ort/region/plz/land bleiben im klartext (google-konvention)
        static::assertSame('10115', $data['address']['postal_code']);
        static::assertSame('Berlin', $data['address']['city']);
        static::assertSame('DE', $data['address']['country']);
    }

    private function customerWithData(): CustomerEntity
    {
        $country = $this->createMock(CountryEntity::class);
        $country->method('getIso')->willReturn('DE');

        $state = $this->createMock(CountryStateEntity::class);
        $state->method('getName')->willReturn('Berlin');

        $address = $this->createMock(CustomerAddressEntity::class);
        $address->method('getPhoneNumber')->willReturn(' +49 151 12345678 ');
        $address->method('getStreet')->willReturn('Musterstrasse 1');
        $address->method('getZipcode')->willReturn('10115');
        $address->method('getCity')->willReturn('Berlin');
        $address->method('getCountry')->willReturn($country);
        $address->method('getCountryState')->willReturn($state);

        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getEmail')->willReturn('Max@Example.com');
        $customer->method('getFirstName')->willReturn('Max');
        $customer->method('getLastName')->willReturn('Mustermann');
        $customer->method('getDefaultBillingAddress')->willReturn($address);

        return $customer;
    }

    private function configService(bool $customerTracking, bool $userIdTracking, string $enhancedConversions = 'off'): ConfigService
    {
        $configService = $this->createMock(ConfigService::class);
        $configService->method('getConfig')->willReturn(new PluginConfig(
            active: true,
            containerId: 'GTM-ABC123',
            debug: false,
            dataLayerEnabled: true,
            enhancedEcommerce: true,
            checkoutTracking: true,
            remarketing: false,
            userIdTracking: $userIdTracking,
            customerTracking: $customerTracking,
            trackContactForm: false,
            trackNewsletter: true,
            trackCustomForms: false,
            enhancedConversions: $enhancedConversions,
        ));

        return $configService;
    }

    private function customer(string $id, bool $guest): CustomerEntity
    {
        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getId')->willReturn($id);
        $customer->method('getGuest')->willReturn($guest);

        return $customer;
    }

    private function context(?CustomerEntity $customer): SalesChannelContext
    {
        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannel->method('getId')->willReturn('sc-1');
        $salesChannel->method('getName')->willReturn('Mein Shop');

        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('EUR');

        $group = $this->createMock(CustomerGroupEntity::class);
        $group->method('getTranslation')->willReturn('Stammkunde');
        $group->method('getName')->willReturn('Stammkunde');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getCurrency')->willReturn($currency);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getCurrentCustomerGroup')->willReturn($group);

        return $context;
    }
}
