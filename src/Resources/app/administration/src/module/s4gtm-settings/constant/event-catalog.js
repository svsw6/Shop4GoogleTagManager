export const CONTEXT_ORDER = ['product', 'listing', 'search', 'cart', 'checkout', 'purchase', 'account', 'global'];

export const CUSTOM_CONTEXTS = ['product', 'listing', 'search', 'cart', 'checkout', 'purchase', 'global'];

export const STANDARD_EVENTS = {
    product: ['view_item', 'add_to_wishlist'],
    listing: ['view_item_list', 'select_item'],
    search: ['search'],
    cart: ['view_cart', 'add_to_cart', 'remove_from_cart'],
    checkout: ['begin_checkout', 'add_shipping_info', 'add_payment_info'],
    purchase: ['purchase'],
    account: ['login', 'logout', 'sign_up', 'newsletter_signup'],
    global: ['view_promotion', 'select_promotion', 'share'],
};
