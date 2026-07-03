export default class GtmConsentService {
    constructor(config, dataLayerService) {
        this._mapping = config.consentMapping || {};

        this._consentManaged = config.consentManaged === true;
        this._sendConsentSignals = config.sendConsentSignals === true;
        this._externalCmpBridge = config.externalCmpBridge === true;
        this._containerId = config.containerId;
        this._cspNonce = this._resolveNonce();
        this._loginStatus = config.loginStatus || 'guest';
        this._dataLayer = dataLayerService;
        this._userDataUrl = config.userDataUrl || '';

        this._ecEnabled = typeof config.enhancedConversions === 'string' && config.enhancedConversions !== 'off';

        this._grantedAnalytics = false;
        this._grantedAdUserData = false;
        this._pushedUser = false;
        this._pushedEc = false;
        this._userDataPending = false;

        this._containerLoaded = config.containerAutoLoaded === true || !this._consentManaged;
        this._analyticsCookieName = this._resolveCookieFor('analytics_storage') || 's4gtm-analytics';
        this._adUserDataCookieName = this._resolveCookieFor('ad_user_data');
        this._readyCallbacks = [];
    }

    _resolveNonce() {
        const el = document.querySelector('script[data-s4gtm-bootstrap]');
        return (el && el.nonce) || '';
    }

    _resolveCookieFor(consentKey) {
        return Object.keys(this._mapping).find(
            (cookieName) => (this._mapping[cookieName] || []).indexOf(consentKey) !== -1,
        ) || '';
    }

    onReady(callback) {
        if (this._containerLoaded) {
            callback();

            return;
        }
        this._readyCallbacks.push(callback);
    }

    init() {
        if (!this._consentManaged) {
            this._grantedAnalytics = true;
            this._grantedAdUserData = true;
            this._syncUserData();
        }

        this._applyFromCookies();

        document.addEventListener('CookieConfiguration_Update', this._onConsentUpdate.bind(this));

        if (this._externalCmpBridge) {
            document.addEventListener('s4gtm:consent-update', this._onExternalConsentUpdate.bind(this));
        }
    }

    _onExternalConsentUpdate(event) {
        this.applyExternalConsent(event && event.detail ? event.detail : {});
    }

    applyExternalConsent(consentState) {
        if (!this._externalCmpBridge || !consentState || typeof consentState !== 'object') {
            return;
        }

        const state = {};
        let anyGranted = false;

        Object.keys(consentState).forEach((key) => {
            const granted = consentState[key] === true || consentState[key] === 'granted';
            state[key] = granted ? 'granted' : 'denied';
            if (granted) {
                anyGranted = true;
            }
        });

        if (Object.keys(state).length === 0) {
            return;
        }

        if (this._sendConsentSignals) {
            this._dataLayer.consentUpdate(state);
        }

        this._syncGateCookies(state);
        this._noteConsent(state);

        if (anyGranted) {
            this._loadContainer();
        }
    }

    _applyFromCookies() {
        const granted = {};
        let anyGranted = false;

        Object.keys(this._mapping).forEach((cookieName) => {
            if (!this._isCookieGranted(cookieName)) {
                return;
            }
            anyGranted = true;
            this._mapping[cookieName].forEach((consentKey) => {
                granted[consentKey] = 'granted';
            });
        });

        if (this._sendConsentSignals) {
            this._dataLayer.consentUpdate(granted);
        }

        this._noteConsent(granted);

        if (anyGranted) {
            this._loadContainer();
        }
    }

    _onConsentUpdate(event) {
        const updated = event.detail || {};
        const state = {};
        let anyGranted = false;

        Object.keys(updated).forEach((cookieName) => {
            const consentKeys = this._mapping[cookieName];
            if (!consentKeys) {
                return;
            }
            const value = updated[cookieName] ? 'granted' : 'denied';
            if (updated[cookieName]) {
                anyGranted = true;
            }
            consentKeys.forEach((consentKey) => {
                state[consentKey] = value;
            });
        });

        if (this._sendConsentSignals) {
            this._dataLayer.consentUpdate(state);
        }

        this._syncGateCookies(state);
        this._noteConsent(state);

        if (anyGranted) {
            this._loadContainer();
        }
    }

    _noteConsent(state) {
        if (state.analytics_storage === 'granted') {
            this._grantedAnalytics = true;
        }
        if (state.ad_user_data === 'granted') {
            this._grantedAdUserData = true;
        }
        this._syncUserData();
    }

    _syncGateCookies(state) {
        if ('analytics_storage' in state) {
            this._syncCookie(this._analyticsCookieName, state.analytics_storage === 'granted');
        }
        if (this._adUserDataCookieName && 'ad_user_data' in state) {
            this._syncCookie(this._adUserDataCookieName, state.ad_user_data === 'granted');
        }
    }

    _syncCookie(name, granted) {
        if (!name) {
            return;
        }

        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        if (granted) {
            const maxAge = 60 * 60 * 24 * 30;
            document.cookie = `${name}=1; path=/; max-age=${maxAge}; SameSite=Lax${secure}`;
        } else {
            document.cookie = `${name}=; path=/; max-age=0; SameSite=Lax${secure}`;
        }
    }

    _syncUserData() {
        if (this._userDataPending || !this._userDataUrl) {
            return;
        }

        const needUser = this._grantedAnalytics && !this._pushedUser;
        const needEc = this._ecEnabled && this._grantedAdUserData && !this._pushedEc;
        if (!needUser && !needEc) {
            return;
        }

        if (this._loginStatus !== 'logged-in') {
            this._pushedUser = true;
            this._pushedEc = true;
            return;
        }

        this._userDataPending = true;

        fetch(this._userDataUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((payload) => {
                this._userDataPending = false;

                if (payload) {
                    const user = payload.user;
                    if (!this._pushedUser && user && typeof user === 'object' && Object.keys(user).length > 0) {
                        this._dataLayer.push({ user });
                    }
                    const ec = payload.enhancedConversion;
                    if (!this._pushedEc && ec && typeof ec === 'object' && Object.keys(ec).length > 0) {
                        this._dataLayer.push({ enhancedConversion: ec });
                    }
                }

                if (this._grantedAnalytics) {
                    this._pushedUser = true;
                }
                if (this._ecEnabled && this._grantedAdUserData) {
                    this._pushedEc = true;
                }

                this._syncUserData();
            })
            .catch(() => {
                this._userDataPending = false;
            });
    }

    _loadContainer() {
        if (this._containerLoaded || !this._containerId) {
            return;
        }
        this._containerLoaded = true;

        const nonce = this._cspNonce;
        (function loadGtm(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
            const f = d.getElementsByTagName(s)[0];
            const j = d.createElement(s);
            j.async = true;
            if (nonce) {
                j.setAttribute('nonce', nonce);
            }
            j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i;
            f.parentNode.insertBefore(j, f);
        }(window, document, 'script', 'dataLayer', this._containerId));

        this._readyCallbacks.forEach((callback) => callback());
        this._readyCallbacks = [];
    }

    _isCookieGranted(name) {
        if (!name) {
            return false;
        }
        return document.cookie.split('; ').some((entry) => {
            const [key, value] = entry.split('=');
            return key === name && value === '1';
        });
    }
}
