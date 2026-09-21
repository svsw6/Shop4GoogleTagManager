import Plugin from 'src/plugin-system/plugin.class';
import GtmDataLayerService from '../../service/gtm-data-layer.service';
import GtmConsentService from '../../service/gtm-consent.service';
import GtmListAttribution from '../../service/gtm-list-attribution.service';
import GtmItemReader, {
    PRODUCT_DATA_SELECTOR,
    CART_ITEM_DATA_SELECTOR,
    CORE_PRODUCT_SELECTOR,
} from '../../service/gtm-item-reader.service';

const LISTING_WRAPPER_SELECTOR = '.cms-element-product-listing-wrapper';
const OFFCANVAS_CART_SELECTOR = '.s4gtm-offcanvas-cart';
const WISHLIST_BUTTON_SELECTOR = '[data-add-to-wishlist]';
const WISHLIST_NOT_ADDED_CLASS = 'product-wishlist-not-added';
const PROMOTION_SELECTOR = '[data-s4gtm-promotion]';
const SHARE_SELECTOR = '[data-s4gtm-share]';
const PROMOTION_FIELDS = ['promotion_id', 'promotion_name', 'creative_name', 'creative_slot', 'location_id'];
const MUTATION_DEBOUNCE = 150;

export default class GtmPlugin extends Plugin {
    init() {
        this._config = this._parseConfig();
        if (this._config === null) {
            return;
        }

        this._dataLayer = new GtmDataLayerService(this._config.debug);
        this._consent = new GtmConsentService(this._config, this._dataLayer);
        this._items = new GtmItemReader();
        this._listAttribution = new GtmListAttribution();

        this._listContext = null;
        this._listSignature = '';
        this._listTimer = null;
        this._offcanvasCartOpen = false;
        this._offcanvasTimer = null;

        this._consent.init();
        this._registerFormTracking();
        this._registerClickTracking();
        this._registerListingTracking();
        this._registerOffcanvasCartTracking();
        this._registerPromotionTracking();
        this._consent.onReady(() => this._pushPageEvents());

        if (this._config.externalCmpBridge === true) {
            window.s4gtm = window.s4gtm || {};
            window.s4gtm.setConsent = (state) => this._consent.applyExternalConsent(state);
        }
    }

    _parseConfig() {
        try {
            return JSON.parse(this.el.textContent);
        } catch (error) {
            return null;
        }
    }

    _trackingEnabled() {
        return this._config.dataLayerEnabled !== false;
    }

    _pushPageEvents() {
        (this._config.events || []).forEach((event) => {
            const transactionId = this._transactionId(event);
            // bereits getrackte transaction (purchase) nicht erneut feuern
            if (transactionId !== null && this._isTransactionTracked(transactionId)) {
                return;
            }

            this._enrichWithListAttribution(event);
            this._dataLayer.pushEvent(event);

            if (transactionId !== null) {
                this._markTransactionTracked(transactionId);
            }
        });

        this._pullPendingEvents();
    }

    _enrichWithListAttribution(event) {
        const ecommerce = event && event.ecommerce;
        if (!ecommerce || ecommerce.item_list_id || !Array.isArray(ecommerce.items) || ecommerce.items.length !== 1) {
            return;
        }

        if (!this._listAttribution.apply(ecommerce.items[0])) {
            return;
        }

        ecommerce.item_list_id = ecommerce.items[0].item_list_id;
        if (ecommerce.items[0].item_list_name !== undefined) {
            ecommerce.item_list_name = ecommerce.items[0].item_list_name;
        }
    }

    _pullPendingEvents() {
        if (!this._config.pendingEventsUrl || !this._hasPendingEvents()) {
            return;
        }

        fetch(this._config.pendingEventsUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((payload) => {
                const events = payload && Array.isArray(payload.events) ? payload.events : [];
                events.forEach((event) => this._dataLayer.pushEvent(event));
            })
            .catch(() => {});
    }

    _hasPendingEvents() {
        if (this._config.hasPendingEvents === true) {
            return true;
        }

        return this._readCookie(this._config.pendingCookie) === '1';
    }

    _readCookie(name) {
        if (!name) {
            return null;
        }

        const match = document.cookie.split('; ').find((entry) => entry.split('=')[0] === name);
        return match === undefined ? null : match.substring(name.length + 1);
    }

    _transactionId(event) {
        const transactionId = event && event.ecommerce && event.ecommerce.transaction_id;
        return transactionId || null;
    }

    _isTransactionTracked(transactionId) {
        return this._readTrackedTransactions().indexOf(transactionId) !== -1;
    }

    _markTransactionTracked(transactionId) {
        let tracked = this._readTrackedTransactions();
        if (tracked.indexOf(transactionId) !== -1) {
            return;
        }

        tracked.push(transactionId);

        if (tracked.length > 50) {
            tracked = tracked.slice(tracked.length - 50);
        }
        this._persistTrackedTransactions(tracked);
    }

    _trackingStores() {
        const stores = [];
        try { if (window.localStorage) { stores.push(window.localStorage); } } catch (e) { /* gesperrt */ }
        try { if (window.sessionStorage) { stores.push(window.sessionStorage); } } catch (e) { /* gesperrt */ }
        return stores;
    }

    _readTrackedTransactions() {
        const storageKey = 's4gtm_tracked_transactions';
        const stores = this._trackingStores();
        for (let i = 0; i < stores.length; i += 1) {
            try {
                const parsed = JSON.parse(stores[i].getItem(storageKey) || '[]');
                if (Array.isArray(parsed) && parsed.length > 0) {
                    return parsed;
                }
            } catch (error) {
                // next store
            }
        }
        return Array.isArray(GtmPlugin._memoryTrackedTransactions) ? GtmPlugin._memoryTrackedTransactions.slice() : [];
    }

    _persistTrackedTransactions(tracked) {
        const storageKey = 's4gtm_tracked_transactions';
        GtmPlugin._memoryTrackedTransactions = tracked;
        this._trackingStores().forEach((store) => {
            try {
                store.setItem(storageKey, JSON.stringify(tracked));
            } catch (error) {
                // store not writeable > in-memory-fallback
            }
        });
    }

    _registerFormTracking() {
        document.addEventListener('submit', this._onSubmit.bind(this), true);
    }

    _onSubmit(event) {
        // data layer off > no ecommerce-events
        if (!this._trackingEnabled()) {
            return;
        }

        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const action = form.getAttribute('action') || '';

        if (action.indexOf('/checkout/line-item/add') !== -1) {
            this._trackAddToCart(form);
        } else if (action.indexOf('/checkout/line-item/delete') !== -1) {
            this._trackRemoveFromCart(form);
        } else if (action.indexOf('/checkout/line-item/change-quantity') !== -1) {
            this._trackQuantityChange(form);
        } else if (this._config.trackContactForm === true && action.indexOf('/form/contact') !== -1) {
            this._trackContactForm(form);
        } else if (this._config.trackCustomForms === true && this._isCustomForm(form, action)) {
            this._trackCustomForm(form, action);
        }
    }

    _trackAddToCart(form) {
        const cfg = this._clientEventConfig('add_to_cart');
        if (cfg === null) {
            return;
        }

        const item = this._items.forForm(form, PRODUCT_DATA_SELECTOR);
        if (item === null) {
            return;
        }

        item.quantity = this._readQuantity(form);
        this._listAttribution.apply(item);

        this._pushClientEvent({
            event: cfg.ga4Event || 'add_to_cart',
            ecommerce: {
                currency: this._config.currency || undefined,
                value: this._round((item.price || 0) * item.quantity),
                items: [item],
            },
            ...(cfg.payload || {}),
        });
    }

    _trackRemoveFromCart(form) {
        const cfg = this._clientEventConfig('remove_from_cart');
        if (cfg === null) {
            return;
        }

        const item = this._items.forForm(form, CART_ITEM_DATA_SELECTOR);
        if (item === null) {
            return;
        }

        this._pushCartDelta(cfg, 'remove_from_cart', item, item.quantity || 1);
    }

    _trackQuantityChange(form) {
        const item = this._items.forForm(form, CART_ITEM_DATA_SELECTOR);
        if (item === null) {
            return;
        }

        const before = item.quantity || 0;
        const after = this._readQuantity(form, before);
        const delta = after - before;
        if (delta === 0) {
            return;
        }

        const name = delta > 0 ? 'add_to_cart' : 'remove_from_cart';
        const cfg = this._clientEventConfig(name);
        if (cfg === null) {
            return;
        }

        this._pushCartDelta(cfg, name, item, Math.abs(delta));
    }

    _pushCartDelta(cfg, fallbackName, item, quantity) {
        const payloadItem = { ...item, quantity };

        this._pushClientEvent({
            event: cfg.ga4Event || fallbackName,
            ecommerce: {
                currency: this._config.currency || undefined,
                value: this._round((payloadItem.price || 0) * quantity),
                items: [payloadItem],
            },
            ...(cfg.payload || {}),
        });
    }

    _trackContactForm(form) {
        this._pushClientEvent({
            event: 'generate_lead',
            form_id: this._formIdentifier(form),
            form_destination: 'contact',
        });
    }

    _trackCustomForm(form, action) {
        this._pushClientEvent({
            event: 'form_submit',
            form_id: this._formIdentifier(form),
            form_destination: this._lastPathSegment(action),
        });
    }

    _isCustomForm(form, action) {
        if (form.hasAttribute('data-s4gtm-form')) {
            return true;
        }
        return action.indexOf('/form/') !== -1
            && action.indexOf('/form/contact') === -1
            && action.indexOf('/form/newsletter') === -1;
    }

    _registerClickTracking() {
        document.addEventListener('click', this._onClick.bind(this), true);
    }

    _onClick(event) {
        if (!this._trackingEnabled() || !(event.target instanceof Element)) {
            return;
        }

        const share = event.target.closest(SHARE_SELECTOR);
        if (share !== null) {
            this._trackShare(share);
            return;
        }

        const promotion = event.target.closest(PROMOTION_SELECTOR);
        if (promotion !== null) {
            this._trackPromotion('select_promotion', promotion);
            return;
        }

        const wishlist = event.target.closest(WISHLIST_BUTTON_SELECTOR);
        if (wishlist !== null) {
            this._trackAddToWishlist(wishlist);
            return;
        }

        this._trackSelectItem(event.target);
    }

    _trackSelectItem(target) {
        const link = target.closest('a');
        if (link === null) {
            return;
        }

        const box = link.closest(CORE_PRODUCT_SELECTOR);
        if (box === null) {
            return;
        }

        const cfg = this._clientEventConfig('select_item');
        if (cfg === null) {
            return;
        }

        const annotated = box.querySelector(PRODUCT_DATA_SELECTOR);
        const item = annotated ? this._items.parseAnnotated(annotated) : this._items.parseCoreProduct(box);
        if (item === null) {
            return;
        }

        const list = this._listForBox(box, item);
        if (list !== null) {
            item.item_list_id = list.item_list_id;
            if (list.item_list_name !== undefined) {
                item.item_list_name = list.item_list_name;
            }
            if (typeof list.index === 'number') {
                item.index = list.index;
            }
        }

        this._listAttribution.remember(item.item_id, list || {});

        this._pushClientEvent({
            event: cfg.ga4Event || 'select_item',
            ecommerce: {
                item_list_id: item.item_list_id,
                item_list_name: item.item_list_name,
                items: [item],
            },
            ...(cfg.payload || {}),
        });
    }

    _listForBox(box, item) {
        if (this._listContext === null) {
            return null;
        }

        const wrapper = box.closest(LISTING_WRAPPER_SELECTOR);
        if (wrapper === null) {
            return null;
        }

        const position = this._items.collectProducts(wrapper)
            .findIndex((candidate) => candidate.item_id === item.item_id);

        return {
            item_list_id: this._listContext.id,
            item_list_name: this._listContext.name,
            index: position === -1 ? undefined : this._listStartIndex() + position,
        };
    }

    _trackAddToWishlist(button) {
        if (!button.classList.contains(WISHLIST_NOT_ADDED_CLASS)) {
            return;
        }

        const cfg = this._clientEventConfig('add_to_wishlist');
        if (cfg === null) {
            return;
        }

        const item = this._items.forElement(button);
        if (item === null) {
            return;
        }

        this._pushClientEvent({
            event: cfg.ga4Event || 'add_to_wishlist',
            ecommerce: {
                currency: this._config.currency || undefined,
                value: this._round((item.price || 0) * (item.quantity || 1)),
                items: [item],
            },
            ...(cfg.payload || {}),
        });
    }

    _trackShare(el) {
        const cfg = this._clientEventConfig('share');
        if (cfg === null) {
            return;
        }

        const event = {
            event: cfg.ga4Event || 'share',
            method: el.getAttribute('data-s4gtm-share') || 'unknown',
        };

        const contentType = el.getAttribute('data-s4gtm-share-content-type');
        if (contentType) {
            event.content_type = contentType;
        }
        const itemId = el.getAttribute('data-s4gtm-share-item-id');
        if (itemId) {
            event.item_id = itemId;
        }

        this._pushClientEvent({ ...event, ...(cfg.payload || {}) });
    }

    _registerPromotionTracking() {
        if (!this._trackingEnabled() || typeof IntersectionObserver === 'undefined') {
            return;
        }

        const banners = document.querySelectorAll(PROMOTION_SELECTOR);
        if (banners.length === 0) {
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }
                observer.unobserve(entry.target);
                this._trackPromotion('view_promotion', entry.target);
            });
        }, { threshold: 0.5 });

        Array.prototype.forEach.call(banners, (banner) => observer.observe(banner));
    }

    _trackPromotion(eventName, el) {
        const cfg = this._clientEventConfig(eventName);
        if (cfg === null) {
            return;
        }

        let data;
        try {
            data = JSON.parse(el.getAttribute('data-s4gtm-promotion'));
        } catch (error) {
            return;
        }
        if (!data || typeof data !== 'object') {
            return;
        }

        const promotion = {};
        PROMOTION_FIELDS.forEach((field) => {
            if (typeof data[field] === 'string' && data[field] !== '') {
                promotion[field] = data[field];
            }
        });

        if (promotion.promotion_id === undefined && promotion.promotion_name === undefined) {
            return;
        }

        this._pushClientEvent({
            event: cfg.ga4Event || eventName,
            ecommerce: { ...promotion, items: [{ ...promotion }] },
            ...(cfg.payload || {}),
        });
    }

    _registerListingTracking() {
        if (!this._trackingEnabled() || typeof MutationObserver === 'undefined') {
            return;
        }

        const wrapper = document.querySelector(LISTING_WRAPPER_SELECTOR);
        if (wrapper === null) {
            return;
        }

        const initial = (this._config.events || []).find((event) => this._isListEvent(event));
        if (initial === undefined) {
            return;
        }

        const items = this._items.collectProducts(wrapper);

        this._listContext = {
            id: initial.ecommerce.item_list_id,
            name: initial.ecommerce.item_list_name,
            ga4Event: initial.event,
            payload: this._extraPayload(initial),
            pageSize: items.length,
        };
        this._listSignature = this._signature(items);

        const observer = new MutationObserver(() => {
            window.clearTimeout(this._listTimer);
            this._listTimer = window.setTimeout(() => this._trackListingUpdate(wrapper), MUTATION_DEBOUNCE);
        });
        observer.observe(wrapper, { childList: true, subtree: true });
    }

    _isListEvent(event) {
        return !!(event
            && event.ecommerce
            && event.ecommerce.item_list_id
            && Array.isArray(event.ecommerce.items));
    }

    _extraPayload(event) {
        const payload = {};
        Object.keys(event).forEach((key) => {
            if (key !== 'event' && key !== 'ecommerce') {
                payload[key] = event[key];
            }
        });
        return payload;
    }

    _trackListingUpdate(wrapper) {
        const items = this._items.collectProducts(wrapper);
        if (items.length === 0) {
            return;
        }

        const signature = this._signature(items);
        if (signature === this._listSignature) {
            return;
        }
        this._listSignature = signature;

        const start = this._listStartIndex();
        items.forEach((item, offset) => {
            item.index = start + offset;
            item.item_list_id = this._listContext.id;
            item.item_list_name = this._listContext.name;
        });

        this._pushClientEvent({
            event: this._listContext.ga4Event,
            ecommerce: {
                currency: this._config.currency || undefined,
                item_list_id: this._listContext.id,
                item_list_name: this._listContext.name,
                items,
            },
            ...this._listContext.payload,
        });
    }

    _signature(items) {
        return items.map((item) => item.item_id).join('|');
    }

    _listStartIndex() {
        const page = parseInt(new URLSearchParams(window.location.search).get('p'), 10);
        const size = this._listContext === null ? 0 : this._listContext.pageSize;

        if (Number.isNaN(page) || page < 1 || !size) {
            return 0;
        }

        return (page - 1) * size;
    }

    _registerOffcanvasCartTracking() {
        if (!this._trackingEnabled() || typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(() => {
            window.clearTimeout(this._offcanvasTimer);
            this._offcanvasTimer = window.setTimeout(() => this._checkOffcanvasCart(), MUTATION_DEBOUNCE);
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    _checkOffcanvasCart() {
        const marker = document.querySelector(OFFCANVAS_CART_SELECTOR);

        if (marker === null) {
            this._offcanvasCartOpen = false;
            return;
        }
        if (this._offcanvasCartOpen) {
            return;
        }
        this._offcanvasCartOpen = true;

        const cfg = this._clientEventConfig('view_cart');
        if (cfg === null) {
            return;
        }

        const offcanvas = marker.closest('.offcanvas') || document;
        const items = this._items.collectCartItems(offcanvas);
        if (items.length === 0) {
            return;
        }

        const value = items.reduce((sum, item) => sum + (item.price || 0) * (item.quantity || 1), 0);

        this._pushClientEvent({
            event: cfg.ga4Event || 'view_cart',
            ecommerce: {
                currency: this._config.currency || undefined,
                value: this._round(value),
                items,
            },
            ...(cfg.payload || {}),
        });
    }

    _formIdentifier(form) {
        return form.getAttribute('data-s4gtm-form')
            || form.getAttribute('id')
            || form.getAttribute('name')
            || '';
    }

    _lastPathSegment(action) {
        const path = action.split('?')[0].replace(/\/+$/, '');
        const segment = path.substring(path.lastIndexOf('/') + 1);
        return segment || 'form';
    }

    _pushClientEvent(event) {
        this._consent.onReady(() => this._dataLayer.pushEvent(event));
    }

    _clientEventConfig(eventName) {
        const cfg = (this._config.clientEvents || {})[eventName];

        if (cfg === undefined) {
            return { ga4Event: eventName, payload: {} };
        }
        if (cfg.active === false) {
            return null;
        }
        return cfg;
    }

    _readQuantity(form, fallback = 1) {
        const input = form.querySelector('[name="quantity"]') || form.querySelector('[name$="[quantity]"]');
        if (input && input.value) {
            const value = parseInt(input.value, 10);
            return Number.isNaN(value) ? fallback : value;
        }
        return fallback;
    }

    _round(value) {
        return Math.round(value * 100) / 100;
    }
}
