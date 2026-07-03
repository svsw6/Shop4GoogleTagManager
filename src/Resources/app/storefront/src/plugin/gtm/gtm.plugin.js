import Plugin from 'src/plugin-system/plugin.class';
import GtmDataLayerService from '../../service/gtm-data-layer.service';
import GtmConsentService from '../../service/gtm-consent.service';

export default class GtmPlugin extends Plugin {
    init() {
        this._config = this._parseConfig();
        if (this._config === null) {
            return;
        }

        this._dataLayer = new GtmDataLayerService(this._config.debug);
        this._consent = new GtmConsentService(this._config, this._dataLayer);

        this._consent.init();
        this._registerCartTracking();
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

    _pushPageEvents() {
        (this._config.events || []).forEach((event) => {
            const transactionId = this._transactionId(event);
            // bereits getrackte transaction (purchase) nicht erneut feuern
            if (transactionId !== null && this._isTransactionTracked(transactionId)) {
                return;
            }

            this._dataLayer.pushEvent(event);

            if (transactionId !== null) {
                this._markTransactionTracked(transactionId);
            }
        });

        this._pullPendingEvents();
    }

    _pullPendingEvents() {
        if (this._config.hasPendingEvents !== true || !this._config.pendingEventsUrl) {
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
                // naechsten store probieren
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
                // store nicht beschreibbar -> der in-memory-fallback greift
            }
        });
    }

    _registerCartTracking() {
        document.addEventListener('submit', this._onSubmit.bind(this), true);
    }

    _onSubmit(event) {
        // data layer aus -> keine ecommerce-events (wie serverseitig)
        if (this._config.dataLayerEnabled === false) {
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

        const item = this._findItem(form, '.s4gtm-product-data');
        if (item === null) {
            return;
        }

        item.quantity = this._readQuantity(form);
        this._pushClientEvent({
            event: cfg.ga4Event || 'add_to_cart',
            ecommerce: {
                currency: this._config.currency || undefined,
                value: (item.price || 0) * item.quantity,
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

        const item = this._findItem(form, '.s4gtm-cart-item-data');
        if (item === null) {
            return;
        }

        this._pushClientEvent({
            event: cfg.ga4Event || 'remove_from_cart',
            ecommerce: {
                currency: this._config.currency || undefined,
                value: (item.price || 0) * (item.quantity || 1),
                items: [item],
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

    _findItem(form, selector) {
        // 1. bevorzugt das produkt-/positions-data im formular selbst
        const inForm = form.querySelector(selector);
        if (inForm) {
            return this._parseItem(inForm);
        }

        const candidates = Array.from(document.querySelectorAll(selector));
        if (candidates.length === 0) {
            return null;
        }
        if (candidates.length === 1) {
            return this._parseItem(candidates[0]);
        }

        const nearest = this._nearestByCommonAncestor(form, candidates);
        return nearest ? this._parseItem(nearest) : null;
    }

    _nearestByCommonAncestor(form, candidates) {
        const formDepth = new Map();
        let depth = 0;
        for (let node = form; node; node = node.parentElement) {
            formDepth.set(node, depth);
            depth += 1;
        }

        let best = null;
        let bestDepth = Infinity;
        let ambiguous = false;

        candidates.forEach((candidate) => {
            for (let node = candidate; node; node = node.parentElement) {
                if (formDepth.has(node)) {
                    const d = formDepth.get(node);
                    if (d < bestDepth) {
                        bestDepth = d;
                        best = candidate;
                        ambiguous = false;
                    } else if (d === bestDepth) {
                        ambiguous = true;
                    }
                    break;
                }
            }
        });

        return ambiguous ? null : best;
    }

    _parseItem(dataEl) {
        try {
            return JSON.parse(dataEl.getAttribute('data-s4gtm-item'));
        } catch (error) {
            return null;
        }
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

    _readQuantity(form) {
        const input = form.querySelector('[name="quantity"]') || form.querySelector('[name$="[quantity]"]');
        if (input && input.value) {
            const value = parseInt(input.value, 10);
            return Number.isNaN(value) ? 1 : value;
        }
        return 1;
    }
}
