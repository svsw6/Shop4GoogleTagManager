# Google Tag Manager

🇬🇧 English | [🇩🇪 Deutsch](README.md)

Google Tag Manager integration for Shopware 6.7 with a complete GA4 Enhanced Ecommerce dataLayer and Google Consent Mode v2.

> ℹ️ Newsletter tracking is triggered via the `newsletter_signup` event after double opt-in. Contact-form tracking (`generate_lead`) and the tracking of other forms (`form_submit`) are implemented client-side and **never transmit form field contents** (see "Form tracking").

## Requirements

- Shopware 6.7
- PHP 8.3 or higher

## Features

- Embeds the GTM container in the head (or at the end of the body); a `noscript` fallback is only rendered where the container is allowed to load without consent anyway
- Server-side dataLayer for shop, language, currency and customer context
- GA4 Enhanced Ecommerce: `view_item`, `view_item_list`, `select_item`, `search`, `add_to_cart`, `remove_from_cart`, `view_cart`, `add_to_wishlist`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`
- Customer events: `login`, `logout`, `sign_up`, `newsletter_signup`
- Campaign and share events (`view_promotion`, `select_promotion`, `share`) via opt-in attributes on your own markup
- Form events: `generate_lead` (contact form) and `form_submit` (CMS/plugin forms) – client-side, **without** transmitting form field contents
- Custom **events** per page context, freely configurable and assignable to individual sales channels
- Google Consent Mode v2 including `ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`
- Configurable per sales channel with Shopware's standard inheritance
- **Server-side tagging**: `gtm.js` and the `noscript` fallback can be loaded from your own GTM server container instead of googletagmanager.com
- Debug mode with console logging

## Installation

```bash
# place the plugin in custom/plugins/Shop4GoogleTagManager
bin/console plugin:refresh
bin/console plugin:install --activate Shop4GoogleTagManager
bin/console cache:clear

# rebuild administration and storefront
bin/build-administration.sh
bin/build-storefront.sh
```

## Configuration

Found in the Administration under **Settings → Extensions → Google Tag Manager**. The interface has two tabs: **Configuration** and **Events**.

### "Configuration" tab

The sales channel selector and the save button are in the top right of the header. Without a selection you edit the global configuration; with a channel selected, each field shows via an inheritance icon whether it inherits the global value or is overridden per channel (Shopware standard).

| Area | Field | Meaning |
| --- | --- | --- |
| Basic configuration | Plugin active | Turns the whole integration on or off |
| Basic configuration | GTM Container ID | Container in the format `GTM-XXXXXXX` |
| Basic configuration | Server container URL | Optional. Address of your own GTM server container (`https://…`). When set, the container loads from there instead of googletagmanager.com |
| Basic configuration | Debug mode | Console logging (prefix `[s4gtm]`); loading/consent behaviour identical to normal operation |
| Basic configuration | Tag position | Head (default) or end of the body |
| Consent | Consent source | `Shopware cookie banner` (default), `External CMP` or `No cookies`. The first two count as **managed**: the container only loads after consent. `No cookies` loads immediately and ungated |
| Consent | Google Consent Mode v2 | Send `gtag('consent', …)` signals; defaults set to `denied`, raised per purpose. **Default: on** |
| Consent | Advanced Consent Mode | **Opt-in, legally contested.** Loads the container before consent with `denied` defaults, so that Google receives cookieless modelling pings on rejection. Only effective with a managed consent source **and** Consent Mode v2 enabled. Your own PII stays blocked until analytics consent is given. **Default: off** |
| Consent | `wait_for_update` | Milliseconds GTM waits for the consent update before the tags fire with the defaults. **Default: 500** |
| Advanced | Data Layer | Enable dataLayer output |
| Advanced | Enhanced Ecommerce | Product, list and search events |
| Advanced | Checkout tracking | Cart, checkout and purchase events |
| Advanced | Remarketing | Enable the advertising-related signals (`ad_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage`) and show the marketing cookie in the banner; off = never raised |
| Advanced | Eager load in checkout | Loads the container server-side on checkout pages **if consent has already been given**. Checkout pages are `no-store`, so the purposes already granted may go straight into the `consent default` as `granted`. **Default: off** |
| Advanced | Enhanced Conversions | `Disabled` (default), `Email only` or `Full customer data`. Transmits SHA-256 hashes only and only after consent to its own banner entry |
| Advanced | User ID tracking | Transmit a pseudonymous `user_id` (customer UUID) (only after analytics consent) – the **only** place where the stable person identifier is emitted |
| Advanced | Customer tracking | Only **non**-directly-identifying attributes (customer group, guest flag) into the dataLayer (only after analytics consent) |
| Advanced | Anonymise search term | Omits the `search_term` field from the `search` event. The event then carries no meaningful information, hence **default: off** – the term is only sent after statistics consent anyway |
| Forms | Newsletter | Newsletter `sign_up` event after double opt-in |
| Forms | Contact form | `generate_lead` when the contact form is submitted (without field contents) |
| Forms | Custom forms | `form_submit` for CMS/plugin forms (without field contents; see "Form tracking") |

### "Events" tab

- **Standard events** can be enabled/disabled per event and given a different GA4 name as well as additional static dataLayer fields (payload). This also applies to the client-side events (`select_item`, `add_to_wishlist`, `view_promotion`, `select_promotion`, `share`, `add_to_cart`, `remove_from_cart`, `view_cart`).
- **Custom events** are created freely: technical name, page context (`product`, `listing`, `search`, `cart`, `checkout`, `purchase`, `global`), GA4 event name, priority and static payload fields.
- Each custom event can be assigned to one or more **sales channels** (multi-select). Without an assignment it applies to all channels. The dropdown lets you filter the list by sales channel.

## Importing the GTM container template

Under [`docs/gtm-container-import.json`](docs/gtm-container-import.json) there is a generic, importable container template that matches the plugin's standard events.

**Import:** GTM → Admin → *Import Container* → choose file → choose workspace → *Merge* (recommended).

It contains:

- a **Google tag (GA4 configuration)** on "Initialization – All Pages",
- one **GA4 event tag** each for `view_item`, `view_item_list`, `select_item`, `search`, `add_to_cart`, `remove_from_cart`, `view_cart`, `add_to_wishlist`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`, `login`, `logout`, `sign_up`, `newsletter_signup`, `view_promotion`, `select_promotion`, `share`, `generate_lead` and `form_submit`,
- matching **custom event triggers** that match the `event` key in the dataLayer,
- a **Google Ads block**: `Conversion Linker` (all pages), the conversion tags `Google Ads - Conversion (purchase)`, `Google Ads - add_to_cart` and `Google Ads - begin_checkout` (each on the matching event trigger) as well as `Google Ads - Remarketing` (all pages),
- the variables `GA4 Measurement ID`, `Google Ads Conversion ID`, `Google Ads Purchase Label`, `Google Ads Add To Cart Label`, `Google Ads Begin Checkout Label` (constants) as well as the dataLayer variables `DLV - search_term`, `DLV - method`, `DLV - value`, `DLV - currency`, `DLV - transaction_id`, `DLV - form_id` and `DLV - form_destination`.

The ecommerce tags read the `ecommerce` object from the dataLayer automatically (exactly matching the plugin output). **After the import you only need to set the `GA4 Measurement ID` variable to your `G-XXXXXXXX` ID** – done. All GA4 tags are set in the consent settings to **"additional consent required: `analytics_storage`"**. They therefore fire only after statistics consent has been given – even if the container was already loaded because of consent to another purpose (e.g. Google Ads).

### Enabling Google Ads

The Google Ads block is **prepared but inactive** (placeholder IDs) and must be filled in after the import:

1. Set the variable **`Google Ads Conversion ID`** to your **numeric** conversion ID (the digits after `AW-`, e.g. `123456789`).
2. For each conversion action (Google Ads → Tools → Conversions) enter the respective **conversion label** into the matching variable: **`Google Ads Purchase Label`** (purchase), **`Google Ads Add To Cart Label`** (`add_to_cart`), **`Google Ads Begin Checkout Label`** (`begin_checkout`). Each of these conversions must be set up as its own action in Google Ads.
3. Conversion tags you do **not** count as a conversion (e.g. `add_to_cart`/`begin_checkout` as pure micro-conversions) can be deleted/paused – or kept as an audience signal.
4. Keep the **`Google Ads - Remarketing`** tag only if you use remarketing (otherwise delete/pause it).

The Ads tags read value/currency/order number from the `ecommerce` object (`DLV - value`/`currency`/`transaction_id`) and are set to the consent conditions **`ad_storage`** (conversion/linker) or **`ad_storage` + `ad_personalization`** (remarketing) – so they only fire after marketing consent has been given. They require that **"Remarketing" is active** in the plugin (otherwise these signals are never raised).

> **Enhanced Conversions:** If you enable the *Enhanced Conversions* option in the plugin, the plugin delivers the hashed `enhancedConversion` object into the dataLayer after `ad_user_data` consent. To use it, enable *Enhanced Conversions* in the `Google Ads - Conversion` tag and map a dataLayer variable to `enhancedConversion` as the "user-provided data variable". This is account/setup specific and therefore deliberately **not** pre-configured.

## Consent Mode – behaviour

There is exactly **one** GTM container for all purposes. As soon as one tracking purpose is consented to (e.g. only Google Ads), the container loads. Which tags then actually fire is controlled by two mechanisms working together:

1. **Consent Mode signals** of the plugin: for "Ads only" `ad_storage`/`ad_personalization` are `granted`, `analytics_storage` stays `denied`.
2. **Consent conditions of the tags in the container** (Consent Settings): the supplied template requires `analytics_storage` as an additional condition for all GA4 tags.

This means GA4 tags are not executed at all without statistics consent – so no cookieless pings go to Google either, as long as the statistics purpose has not been confirmed. If you use your own container, set the consent conditions of your tags accordingly (`analytics_storage` for GA4, `ad_storage`/`ad_user_data` for Google Ads).

Identifying user data (pseudonymous `user_id`, customer group) is only written into the `dataLayer` – and thus handed over to the container/Google – **after analytics consent has been given**. These values are **not embedded in the page source** (the HTML may be served across customers via reverse-proxy/HTTP caches). Instead, the storefront JS loads them only after consent via a dedicated, **never cacheable endpoint** (`/s4gtm/user-data`, `Cache-Control: no-store, private`) and then pushes them into the `dataLayer`. The coarse login status (guest/logged in) in the base `dataLayer` is always available and contains no identifying attributes.

For the same reason the **queued events** (`login`/`sign_up`/`newsletter_signup`, which are only played out on the next page because of the redirect) also do **not** end up in the cacheable HTML. The page render only sets a boolean hint flag; the storefront JS fetches the actual events after the container load via the equally non-cacheable endpoint `/s4gtm/pending-events` (`Cache-Control: no-store, private`), which then clears the session queue server-side. This way even a spurious event cannot be served across customers.

The hint flag is also kept in a dedicated cookie (`s4gtm-pending`). The HTML flag alone is not enough: after a logout the visitor lands on a cacheable page whose flag may come from the cache – the event would then stay in the session. The cookie is set or cleared per response and carries no payload, only "something is queued".

### Consent source

The **consent source** decides whether the container loads depending on consent at all.

| Value | Behaviour |
| --- | --- |
| **Shopware cookie banner** (default) | Consent counts as **managed**. The plugin adds its entries to the statistics and marketing groups and listens to `CookieConfiguration_Update`. `gtm.js` stays blocked both server- and client-side until at least one purpose is confirmed. |
| **External CMP** | Also **managed** – only the CMP reports consent through the bridge (see "External consent managers"). The plugin then sets the first-party gate cookies itself, so the server-side endpoint knows about the consent too. |
| **No cookies** | Consent counts as **unmanaged**: no consent signals, the container loads immediately on page load, tracking runs entirely **ungated**. Regularly not permitted in the EU and solely the operator's responsibility – the admin UI requires an explicit confirmation before saving. |

**Google Consent Mode v2** independently controls only whether `gtag('consent', …)` signals are sent in addition: defaults for all trackable purposes set to `denied`, raised per purpose via update. With a managed source the container stays blocked even without these signals – but the per-purpose control inside the container is then lost. By default (Shopware banner + Consent Mode v2) this matches Google's **basic mode**: nothing reaches Google before consent.

**Advanced Consent Mode** (opt-in, only effective with a managed source **and** Consent Mode v2 enabled): the container loads **immediately** on page load with `denied` defaults. If the visitor declines, Google only receives **cookieless pings** (consent state, timestamp, URL, user agent, conversion signals) for conversion and behavioural modelling – **no cookies** are set. This matches Google's **advanced mode**. Identifying user data (`user_id`, customer group) stays blocked until analytics consent. **Legally contested:** whether cookieless pings without prior consent are permissible has not been settled in the EU – use at your own risk after your own legal review.

### Banner entries and consent purposes

| Banner entry | Cookie | Consent Mode purposes | shown when |
| --- | --- | --- | --- |
| Google Analytics | `s4gtm-analytics` | `analytics_storage` | always |
| Google Ads | `s4gtm-marketing` | `ad_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage` | remarketing enabled |
| Enhanced conversion tracking | `s4gtm-enhanced-conversions` | `ad_user_data` | Enhanced Conversions enabled |

`ad_user_data` deliberately belongs to the **marketing** entry: Consent Mode v2 already requires this signal for ordinary Google Ads conversion measurement, not just for Enhanced Conversions. Tying it to a separate checkbox loses conversions from visitors who explicitly agreed to marketing use. The third entry therefore only controls the transmission of **hashed customer data**, and is only shown when Enhanced Conversions are actually enabled.

### Server-side consent defaults and the HTTP cache

The `consent default` in the page head is `denied` throughout – even for visitors who consented long ago. The reason: the HTML can end up in the HTTP cache and be served from there across visitors; a `granted` in the cache would transfer one visitor's consent to everyone else.

Only on pages Shopware marks as **`no-store`** (cart, order confirmation, order completion) does the plugin read the consent cookies server-side and write the purposes already granted straight into the default as `granted`. Together with **eager load in checkout**, the `purchase` tag then fires immediately with the correct consent instead of sending cookielessly first and waiting for the storefront JavaScript.

## Form tracking

Contact-form and custom-form tracking run **client-side** when the respective form is submitted and are – like the other page events – only pushed into the `dataLayer` **after** the container has loaded (i.e. after consent).

- **Contact form** (`trackContactForm`): when the Shopware contact form (`/form/contact`) is submitted, `generate_lead` is triggered.
- **Custom forms** (`trackCustomForms`): captures CMS/plugin forms that post to a `/form/*` endpoint (contact and newsletter excluded, as they are covered separately), as well as any `<form>` that explicitly carries the `data-s4gtm-form` attribute. `form_submit` is triggered.

```html
<!-- mark any form specifically for tracking -->
<form action="/my-route" method="post" data-s4gtm-form="contact-footer">
    …
</form>
```

> 🔒 **No personal data:** only **static markup attributes** of the form are read (`data-s4gtm-form`, `id`, `name`, path segment of the `action`) and emitted as `form_id`/`form_destination`. **Form field contents (name, email, message) never enter the `dataLayer`.** Because the event fires on submit, it can be counted even if the subsequent server-side validation rejects the form.

## Tracking behaviour in the browser

- **`purchase` deduplication:** the `purchase` event is triggered only **once** per order. Deduplication happens client-side via the `transaction_id` (order number), which the storefront JS remembers in `localStorage` – deliberately **without** write access to the order during page render (this avoids `order.written` events, indexer load and race conditions). If a visitor clears their `localStorage` and reopens the order confirmation, the event may fire again; GA4 additionally deduplicates `purchase` via the `transaction_id`.
- **Optimistic `add_to_cart`:** the client-side `add_to_cart` event is captured already when the cart form is submitted – i.e. **before** the server has confirmed the addition. If the server-side operation fails (e.g. out of stock), an `add_to_cart` may have been tracked that did not result in a cart entry. The server-side events (`view_cart` etc.) do not overlap with it. **Tiered prices:** `price`/`value` of the client-side `add_to_cart` are based on the unit price stored in the DOM (quantity 1). With quantity-dependent tiered prices the tracked amount may therefore differ from the actual cart value; the authoritative server-side events (`view_cart`, `purchase`) use the real prices.
- **Product data in listings:** the item data for `add_to_cart` lives in the plugin's own `<data>` element inside the product box. If a theme replaces the `component_product_box` block without `{{ parent() }}`, that element disappears – the plugin then falls back to Shopware's own `data-product-information` attribute on the surrounding box. The fallback provides `item_id`, `item_name`, `item_brand` and `price`, but no category and no variant.
- **`view_item_list` with filters and pagination:** filters, sorting and page changes swap the product list via AJAX without any page event firing. The storefront JS therefore watches the list container and sends another `view_item_list` whenever the result set changes – with the `item_list_id`/`item_list_name` of the initial list. The `index` is derived from the page parameter and the page size of the first list and may be off on the last page.
- **`select_item` and list attribution:** clicking a product card fires `select_item` and remembers product, list and position in `sessionStorage` (key `s4gtm_list_attribution`, valid for 30 minutes, product and list context only, no user data). On the following product detail page `view_item` and `add_to_cart` then carry the same `item_list_id`/`item_list_name` – without that bridge GA4 list reports stay empty. If the list shows the parent product number and the detail page a variant, the attribution does not match; the list is then simply missing.
- **`view_cart` in the offcanvas cart:** for many visitors the offcanvas is the only cart view. It fires on opening, not on every re-render – a quantity change inside the offcanvas does not trigger a second `view_cart`. The event follows the same on/off switch as the server-side `view_cart`. **After the update the number of `view_cart` events therefore rises noticeably**; turn the event off in the "Events" tab if that is not wanted.
- **Cart quantity changes:** when the quantity of a line item changes, the plugin sends the **difference** as `add_to_cart` or `remove_from_cart` – that is what GA4 expects. Unchanged quantities send nothing.
- **`add_to_wishlist`:** fires when the wishlist icon is clicked while the product is not yet on the wishlist. GA4 has no standard event for removal, so none is sent for it.
- **Order of user data:** the identifying user data is loaded asynchronously via `/s4gtm/user-data`, but the page events wait for it. `user` and `enhancedConversion` therefore sit **before** the first ecommerce event in the `dataLayer` – otherwise a conversion tag would read empty variables when it fires. So that a hanging request cannot block tracking, the events fire after **1.5 seconds** at the latest even without the user data. If customer tracking, user ID tracking and Enhanced Conversions are all off, the request is skipped entirely.

## Campaign and share events

Shopware has no concept of campaign elements. You mark up which CMS element is a campaign yourself – the plugin then reads the attributes.

**Campaign banners** (`data-s4gtm-promotion`): `view_promotion` fires as soon as the element is half visible (once per page view), `select_promotion` when it is clicked.

```html
<div class="hero-banner"
     data-s4gtm-promotion='{"promotion_id":"SUMMER25","promotion_name":"Summer campaign","creative_name":"hero","creative_slot":"home_top"}'>
    …
</div>
```

Only the GA4 fields `promotion_id`, `promotion_name`, `creative_name`, `creative_slot` and `location_id` are taken over; at least `promotion_id` or `promotion_name` must be set. Everything else is discarded.

**Share buttons** (`data-s4gtm-share`): the attribute value becomes `method`.

```html
<button data-s4gtm-share="whatsapp"
        data-s4gtm-share-content-type="product"
        data-s4gtm-share-item-id="SW-10001">Share</button>
```

Both event groups can be disabled and renamed individually in the "Events" tab. Without the attributes in your markup nothing happens.

## Server-side tagging

If you enter an address under **Basic configuration → Server container URL**, the plugin loads `gtm.js` and the `noscript` fallback from there instead of `googletagmanager.com`:

```
https://gtm.yourshop.com   →   https://gtm.yourshop.com/gtm.js?id=GTM-XXXXXXX
                               https://gtm.yourshop.com/ns.html?id=GTM-XXXXXXX
```

Only `https` addresses without query parameters are accepted. An invalid entry is discarded server-side and the container loads from Google again – otherwise the loading address would point nowhere and nothing would be tracked at all. The admin UI blocks saving it as well.

That is all the plugin does. Whether the measurement hits also travel via your domain depends on what sits behind that address – a real GTM server container or a reverse proxy – and how the tags inside the container are configured. Setting up such an endpoint on the shop hosting is described in [`docs/server-side-tagging-proxy.md`](docs/server-side-tagging-proxy.md) (German).

Consent is unaffected: even via your own domain the container only loads after consent, and the data still goes to Google – just with your server in between.

## External consent managers (CMP)

By default the plugin uses the **native Shopware cookie consent manager**: it adds the cookies to the statistics/marketing group and reacts to the `CookieConfiguration_Update` event. If you use an external CMP instead (Cookiebot, Usercentrics, Borlabs Cookie, Consentmanager, etc.), the Shopware-native consent cookies are not set – the plugin would then **never load** the container. For this case there is a documented bridge through which the external CMP reports consent directly to the plugin.

> ⚠️ **Opt-in:** the bridge is **disabled** by default and must be enabled under **Configuration → Consent → "External CMP bridge"**. Reason: when active, **any** script on the page can send consent signals via the DOM event. Only enable it if you actually use an external CMP instead of the Shopware banner. As soon as the bridge reports analytics consent, the storefront JS additionally sets a first-party cookie (`s4gtm-analytics`) so that the server-side endpoint also releases the identifying user data (the external CMP does not set this cookie itself).

**Consent Mode fields** are passed **directly** (`analytics_storage`, `ad_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage`). Values may be `true`/`false` or `'granted'`/`'denied'`. As soon as at least one tracking purpose is granted, the container loads; identifying user data only enters the dataLayer after `analytics_storage: granted`, hashed customer data only after `ad_user_data: granted`.

**Variant A – DOM event** (recommended, works independently of load order):

```js
document.dispatchEvent(new CustomEvent('s4gtm:consent-update', {
    detail: {
        analytics_storage: true,
        ad_storage: false,
        ad_user_data: false,
        ad_personalization: false,
    },
}));
```

**Variant B – imperative API** (once the storefront plugin is initialised):

```js
window.s4gtm.setConsent({ analytics_storage: true, ad_storage: true });
```

Hook this call into your CMP's consent callback (e.g. Cookiebot `CookiebotOnAccept`, Usercentrics consent event). Call it again on every consent change – the plugin then sends the matching `gtag('consent','update',…)` signal. Both variants are additive: the native Shopware path stays active in parallel.

## Google Tag Assistant / preview

**Debug mode** (basic configuration) only changes the logging: every dataLayer push and every consent update is written to the browser console (prefix `[s4gtm]`). Loading and consent behaviour stay identical to normal operation – so the container still only loads after consent.

To test with the Google Tag Assistant, open the storefront from the GTM preview and **give consent in the cookie banner** (statistics or the respective required category). Only then does the container load and the assistant connect – so you test exactly the behaviour that real visitors experience.

## Legal notes / operator obligations

> Not legal advice. The plugin provides the technical prerequisites for GDPR- and TTDSG-compliant operation (consent requirement as default, no loading before consent, Consent Mode v2, no transmission of plaintext data). The following obligations can only be fulfilled by the shop operator – please coordinate with your own legal counsel.

- **Privacy policy:** name Google Tag Manager, Google Analytics or Google Ads, each with purpose, legal basis (consent, Art. 6(1)(a) GDPR) and storage period of the cookies set (the consent cookies created by the plugin expire after **30 days**).
- **Third-country transfer (USA):** point out the transfer to Google (Google Ireland Ltd. / Google LLC), the EU-US Data Privacy Framework and the risk of the third-country transfer.
- **Data processing:** conclude and document the data processing terms (Google Ads/Analytics Data Processing Terms) with Google (record of processing activities, Art. 30 GDPR).
- **Consent as a prerequisite:** leave the **consent source** on "Shopware cookie banner" or "External CMP" (default: Shopware). Both count as managed – as long as "Advanced Consent Mode" also stays off, the container only loads after consent and all purposes start out `denied`. Only the source "No cookies" loads the container immediately and lets tracking run entirely ungated – regularly not permitted in the EU and solely your responsibility (the admin UI requires an explicit confirmation for it).
- **Advanced Consent Mode (opt-in):** if you enable "Advanced Consent Mode", the container loads already **before** consent and Google receives cookieless modelling pings on rejection (without cookies, without identifying customer data). Whether this is permissible without prior consent is legally contested in the EU and not settled by the highest courts. Only use the option after your own legal review and, if applicable, disclose the processing in your privacy policy.
- **Cookie banner:** the entries added by the plugin must be described understandably in the consent banner. The supplied texts are kept neutral; adapt them to your privacy policy if needed.
- **Enhanced Conversions:** unless the option is set to "Disabled", the plugin transmits hashed customer data (SHA-256: email, optionally phone, name, street; postcode, city, region and country in clear text, as required by Google). This only happens after consent to its own banner entry "Enhanced conversion tracking" and only for logged-in customers. Declare this processing in your privacy policy and your record of processing activities; the data processing terms with Google are a prerequisite.
- **Search term:** the `search` event transmits the entered search term (`search_term`) **by default** – but only after statistics consent, like every other event. A `search` event without the term carries no meaningful information. Search terms can however contain personal details (visitors search e.g. for their own names); if you observe that in your shop, the field can be omitted via the option **"Anonymise search term"** (Advanced).
- **Transmitted identifiers (data minimisation):** the stable, re-identifiable person identifier (customer UUID) is transmitted **only** when **"User ID tracking"** is active – then as the GA4 `user_id` for the User-ID feature. "Customer tracking" no longer transmits any person identifier, only the customer group and guest flag. Both only after analytics consent. If you enable User ID tracking, name this processing in the record of processing activities and in the privacy policy.
- **Server-side tagging:** loading the container via your own domain does not change the consent requirement – the data still flows to Google, just with your server in between. It does make you responsible for the processing inside the server container: name the server container in your privacy policy and record of processing activities and document what it forwards to Google.
- **External consent managers:** if you use an external CMP instead of the Shopware banner, use the documented bridge (see "External consent managers (CMP)") to ensure the container really only loads after consent.

## Notes on the GA4 values

- Monetary fields (`value`, `price`, `tax`, `shipping`, `discount`) are always emitted as **numbers**, never as strings, and commercially rounded to two decimal places.
- `value` of the cart, checkout and purchase events is the **goods total without shipping costs, less the discount line items** (GA4 recommendation). Shopware leaves product prices untouched for promotions and books the reduction as its own `promotion` line item; that item does not appear in `items` but does reduce the `value`. `shipping` is reported separately on `purchase`.
- In `view_item_list`/`search` `index` reflects the absolute position including pagination offset. For lists tracked client-side (filters, pagination) the offset is an approximation, see "Tracking behaviour in the browser".
- **Item enrichment:** `view_item`/`view_item_list` provide `item_brand`, `item_variant` and the **category path**. GA4 expects it from broad to narrow: `item_category` is the top level below the sales channel entry point, `item_category2` to `item_category5` the deeper ones. The basis is the breadcrumb of the product's `seoCategory`. On category pages the categories of the listed products are not loaded; there the plugin fills `item_category` with the currently browsed category name instead (an approximation, but without an extra query). `view_cart`/`begin_checkout`/`purchase` provide `item_variant` (from the cart payload) as well as `item_brand` (the manufacturer name is resolved per page in **one** bundled query).

## Development

Architecture:

- `Core/Content/GtmEvent` – DAL entity for custom events including many-to-many to sales channels
- `Service` – configuration, consent, dataLayer and the `Ecommerce` builders (pure entity-to-`DataLayerEvent` mapping)
- `Controller` – `UserDataController` delivers the identifying user data and the queued events via non-cacheable endpoints (`/s4gtm/user-data`, `/s4gtm/pending-events`) so that neither ends up in the (cacheable) page HTML
- `Subscriber` – attach the events to storefront pages (each subscriber encapsulated with error logging); `PendingEventCookieSubscriber` additionally keeps the `s4gtm-pending` cookie in sync with the session queue
- `Struct` – value objects (`DataLayerEvent`, `PluginConfig`, `GtmPageExtension`)
- `Resources/app/administration` – Vue module (one `sw-page` with tabs and `router-view`)
- `Resources/app/storefront` – dataLayer, consent and event handling in the browser

The event catalog lives in `Service/GtmEventCatalog.php` and is mirrored in `Resources/app/administration/.../constant/event-catalog.js`; a PHPUnit parity test ensures both sources do not drift apart.

### Security & hardening

- **Custom event payload:** the static payload fields are validated server-side (`GtmEventValidationSubscriber`): restrictive key pattern, max. 30 fields, max. 3 nesting levels, only scalar values, reserved keys (`event`/`ecommerce`) forbidden. This also applies to direct admin API access, not just via the UI. The rules live centrally in the `PayloadValidator`.
- **Standard event overrides:** a differing GA4 name and additional payload of the standard events are stored via the system config (no DAL pre-write hook). They therefore run through the same `PayloadValidator` **on read** (`ConfigService`): invalid GA4 names fall back to the original name, the payload is filtered (key pattern, size, depth, reserved keys). This gives defense-in-depth for both input paths.
- **Analytics cookie (`s4gtm-analytics`):** the server-side gate of the `/s4gtm/user-data` endpoint reads this first-party cookie. It is client-settable – a user can thereby only bypass their **own** consent gate; only the data of the logged-in session customer is returned (no IDOR).
- **ACL:** the `s4gtm_event` entity is accessible via the admin API. By default only administrators have write permission; the configuration and event pages are gated behind `system.system_config`. For granular roles the plugin ships its own privilege map **"GTM Custom Events"** (`addPrivilegeMappingEntry` in `Resources/app/administration/src/acl/index.js`) with the read/edit/create/delete roles. A role that should use the event page additionally needs `system.system_config`.

## Tests

**PHP** (unit tests, run without a database):

```bash
# from the shopware root
vendor/bin/phpunit -c custom/plugins/Shop4GoogleTagManager/phpunit.xml.dist
```

**Static analysis** (PHPStan level 5, must pass clean):

```bash
# once in the shopware root: composer require --dev phpstan/phpstan
cd custom/plugins/Shop4GoogleTagManager
../../../vendor/bin/phpstan analyse
```

**Storefront** (jsdom integration tests against the **built** bundle and real shop markup):

```bash
# run bin/build-storefront.sh once beforehand
cd custom/plugins/Shop4GoogleTagManager/tests/js
npm install
npm test
```

The storefront tests cover, among other things, the order inside the `dataLayer` (user data before the
ecommerce events), consent gating, the product data fallback for themes, the re-tracking of swapped
product lists and the retrieval of queued events. The fixtures come from a real shop –
`fixtures/listing.html` deliberately reproduces a theme that replaces `component_product_box` without
`{{ parent() }}`.

`npm test` first checks whether the committed bundle matches the storefront sources and only then runs. Every build therefore needs a `npm run stamp` afterwards – otherwise the run fails with a hint instead of silently testing the old code.

**Consent behaviour against a running shop** (checks the page that is actually served, among other things that a cacheable page never serves `granted`):

```bash
tests/integration/consent-matrix.sh http://localhost:8006
```

**Outside a Shopware installation** `composer install` in the plugin directory pulls in `shopware/core`, `storefront` and `administration` as dev dependencies; PHPUnit and PHPStan then run from the plugin's own `vendor/`. Tested with PHP 8.3 and 8.4.

## Disclaimer

This plugin is provided as open-source software free of charge and comes without any express or implied warranty.

Installation, configuration and use of the plugin are entirely at your own risk. The operator of the plugin assumes no liability for its functionality, compatibility or fitness for a particular purpose.

No liability is assumed, in particular, for:

- data loss or data corruption
- outages or malfunctions of the shop
- loss of revenue or lost profit
- incorrect tracking data or faulty transmission of events
- problems caused by faulty configurations of Google Tag Manager, Google Analytics or other connected services
- damage arising from updates to Shopware, third-party plugins or external services

The user is responsible for:

- sufficiently testing the plugin before productive use,
- checking the configuration of Google Tag Manager and all connected services,
- ensuring compliance with all applicable legal requirements (in particular the GDPR and data protection regulations).

It is expressly recommended to install the plugin in a test environment first and to test it thoroughly before productive use.

There is no obligation to provide maintenance, further development, bug fixing or support.

To the extent permitted by law, any liability for direct or indirect damages is excluded.

## Data protection

This plugin provides only the technical integration of Google Tag Manager. Which tags, scripts or third-party services are embedded through it is solely the responsibility of the respective shop operator.

The developer of this plugin assumes no responsibility whatsoever for the data-protection-compliant use of Google Tag Manager or the services embedded through it. The shop operator is responsible for complying with all applicable legal requirements, in particular the GDPR, the TTDSG and, where applicable, further national data protection regulations.

## Consent

By installing or using this plugin, the user agrees to the above conditions.
