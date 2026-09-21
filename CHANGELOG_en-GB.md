# 1.1.0

## Fixed tracking failures

- `user_id`, customer group and the hashed enhanced conversion data were fetched asynchronously and therefore always landed in the `dataLayer` **after** the page events. GA4 and Ads tags read empty variables when firing, so enhanced conversions effectively never worked. The page events now wait for this data (at most 1.5 seconds, after which they fire without it).
- `Eager load in checkout` set the `consent default` to `denied` **and** `wait_for_update` to `0`, so GTM fired cookielessly right away even though the consent was known server-side. On `no-store` pages (cart, order confirmation, order completion) the purposes already granted now go straight into the default as `granted`, and `wait_for_update` keeps its configured value. On cacheable pages everything stays `denied` so that no one else's consent can be served from the HTTP cache.
- `add_to_cart` from category pages, sliders and search results failed completely as soon as a theme replaced the `component_product_box` block without `{{ parent() }}`. Shopware's own `data-product-information` attribute on the product box is now used as a fallback.
- `view_item_list` only fired on a full page render. Filters, sorting and page changes swap the list via AJAX – those impressions are now tracked client-side.
- The hint flag for queued events (`login`, `logout`, `sign_up`, `newsletter_signup`) lived only in the HTML and could come from the HTTP cache after a logout. It is now also kept in the `s4gtm-pending` cookie.

## Consent

- `ad_user_data` now belongs to the marketing entry of the cookie banner instead of the separate "Enhanced conversion tracking" checkbox. Consent Mode v2 already requires the signal for ordinary Google Ads conversion measurement; previously conversions were lost from visitors who had explicitly agreed to marketing use.
- The banner entry "Enhanced conversion tracking" is now only shown when Enhanced Conversions are actually enabled. Previously it appeared as soon as remarketing was on and thus described a data transmission that never took place.
- The storefront JS no longer re-sets the gate cookies when the Shopware banner manages them anyway. With a partial consent that would have unlocked too much.

## Changed defaults

- "Anonymise search term" is now **off**. A `search` event without `search_term` carries no meaningful information, and the term is only transmitted after statistics consent anyway. Existing installations keep their stored setting.

## Data quality

- `value` of the cart, checkout and purchase events now subtracts the discount line items. Shopware leaves product prices untouched for promotions, so GA4 previously reported inflated revenue for every redeemed voucher.
- **Category path instead of a single category:** `item_category` is now the top level below the sales channel entry point, `item_category2` to `item_category5` the deeper ones – as GA4 expects. For products with only one category nothing changes.

## New events

- **`select_item`** when a product card is clicked – including list id, list name and position.
- **List attribution across the page change:** the clicked list follows the product into `view_item` and `add_to_cart` (`sessionStorage`, 30 minutes, product and list context only). Without that bridge GA4 list reports stay empty.
- **`view_cart` in the offcanvas cart** – for many visitors the only cart view. It fires on opening, not on every re-render. **The number of `view_cart` events rises noticeably**; it can be turned off in the "Events" tab.
- **Cart quantity changes** send the difference as `add_to_cart` or `remove_from_cart`.
- **`add_to_wishlist`** when a product is added to the wishlist.
- **`view_promotion`, `select_promotion` and `share`** via the opt-in attributes `data-s4gtm-promotion` and `data-s4gtm-share`. Shopware has no core equivalent for these – without the attributes in your markup nothing happens.
- The GTM container template now ships tags and triggers for all new events, and for the first time also for `generate_lead` and `form_submit`.

## Server-side tagging

- New field **Server container URL**: `gtm.js` and the `noscript` fallback then load from your own GTM server container instead of `googletagmanager.com`. Only `https` addresses without query parameters are accepted; an invalid entry is discarded and the container loads from Google again. Consent gating is unchanged.

## Quality assurance

- `npm test` uses a checksum to verify that the committed bundle matches the storefront sources – otherwise the tests would run against stale code.
- New script `tests/integration/consent-matrix.sh` checks the consent behaviour against a running shop, among other things that a cacheable page never serves `granted`.
- The configuration defaults parity test now compares the **values** too, not just the keys.

## Other

- The "Tag position" dropdown was missing the actual value: the labels contained `<head>` and `<body>`, and the HTML parser swallowed both tags when rendering the option. Now without angle brackets.
- The `noscript` fallback is rendered again – but only where the container is allowed to load without consent anyway. An iframe cannot be held back depending on consent after the fact.
- The tag position "at the end of the body" is now honoured when the container is loaded after consent as well.
- The `/s4gtm/user-data` endpoint is no longer called when customer tracking, user ID tracking and Enhanced Conversions are all off – the response would be guaranteed empty.
- The plugin configuration can no longer be saved with an invalid container ID, and the admin now points out when the plugin is active but delivers nothing for lack of a valid ID.
- New: jsdom integration tests of the built storefront bundle against real shop markup (`tests/js`), plus a PHPStan configuration (level 5, clean).
- The README still described the old consent model with two switches; it has been rewritten for the actual configuration (consent source).

# 1.0.9

- Fixed inheritance switches in the plugin configuration not showing the inherited default value: a sales-channel switch in the inherited state now correctly reflects the global setting instead of always appearing as off.
