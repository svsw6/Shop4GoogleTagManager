<?php declare(strict_types=1);

namespace Shop4GoogleTagManager\Service;

final class GtmEventCatalog
{
    public const CONTEXT_PRODUCT = 'product';
    public const CONTEXT_LISTING = 'listing';
    public const CONTEXT_SEARCH = 'search';
    public const CONTEXT_CART = 'cart';
    public const CONTEXT_CHECKOUT = 'checkout';
    public const CONTEXT_PURCHASE = 'purchase';
    public const CONTEXT_ACCOUNT = 'account';
    public const CONTEXT_GLOBAL = 'global';

    public const CUSTOM_CONTEXTS = [
        self::CONTEXT_PRODUCT,
        self::CONTEXT_LISTING,
        self::CONTEXT_SEARCH,
        self::CONTEXT_CART,
        self::CONTEXT_CHECKOUT,
        self::CONTEXT_PURCHASE,
        self::CONTEXT_GLOBAL,
    ];

    public const STANDARD_EVENTS = [
        'view_item' => self::CONTEXT_PRODUCT,
        'view_item_list' => self::CONTEXT_LISTING,
        'search' => self::CONTEXT_SEARCH,
        'add_to_cart' => self::CONTEXT_CART,
        'remove_from_cart' => self::CONTEXT_CART,
        'view_cart' => self::CONTEXT_CART,
        'begin_checkout' => self::CONTEXT_CHECKOUT,
        'add_shipping_info' => self::CONTEXT_CHECKOUT,
        'add_payment_info' => self::CONTEXT_CHECKOUT,
        'purchase' => self::CONTEXT_PURCHASE,
        'login' => self::CONTEXT_ACCOUNT,
        'logout' => self::CONTEXT_ACCOUNT,
        'sign_up' => self::CONTEXT_ACCOUNT,
        'newsletter_signup' => self::CONTEXT_ACCOUNT,
    ];

    public const CLIENT_EVENTS = [
        'add_to_cart',
        'remove_from_cart',
    ];

    public static function isValidCustomContext(string $context): bool
    {
        return in_array($context, self::CUSTOM_CONTEXTS, true);
    }
}
