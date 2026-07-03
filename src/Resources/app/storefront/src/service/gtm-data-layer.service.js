export default class GtmDataLayerService {
    constructor(debug = false) {
        this._debug = debug;
        window.dataLayer = window.dataLayer || [];
    }

    push(data) {
        window.dataLayer.push(data);
        this._log('push', data);
    }

    pushEvent(event) {
        if (!event || !event.event) {
            return;
        }

        if (event.ecommerce) {
            window.dataLayer.push({ ecommerce: null });
        }

        this.push(event);
    }

    consentUpdate(state) {
        if (!state || Object.keys(state).length === 0) {
            return;
        }

        function gtag() {
            window.dataLayer.push(arguments);
        }
        gtag('consent', 'update', state);

        this._log('consent.update', state);
    }

    _log(type, payload) {
        if (this._debug) {
            // eslint-disable-next-line no-console
            console.log('[s4gtm]', type, payload);
        }
    }
}
