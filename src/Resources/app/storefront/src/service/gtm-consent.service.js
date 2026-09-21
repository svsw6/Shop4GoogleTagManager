// spaetestens nach dieser zeit duerfen die seiten-events raus, auch wenn der
// user-data-request noch haengt - lieber ohne user_id messen als gar nicht
const USER_DATA_TIMEOUT = 1500;

export default class GtmConsentService {
    constructor(config, dataLayerService) {
        this._mapping = config.consentMapping || {};

        this._consentManaged = config.consentManaged === true;
        this._sendConsentSignals = config.sendConsentSignals === true;
        this._externalCmpBridge = config.externalCmpBridge === true;
        this._containerId = config.containerId;
        this._scriptOrigin = config.scriptOrigin || 'https://www.googletagmanager.com';
        this._tagInBody = config.tagInBody === true;
        this._cspNonce = this._resolveNonce();
        this._loginStatus = config.loginStatus || 'guest';
        this._dataLayer = dataLayerService;
        this._userDataUrl = config.userDataUrl || '';

        this._ecEnabled = typeof config.enhancedConversions === 'string' && config.enhancedConversions !== 'off';
        this._ecCookieName = config.enhancedConversionsCookie || '';

        this._grantedAnalytics = false;
        this._grantedEc = false;
        this._pushedUser = false;
        this._pushedEc = false;
        this._userDataPending = false;
        this._userDataSettled = false;
        this._userDataTimer = null;

        this._containerLoaded = config.containerAutoLoaded === true || !this._consentManaged;
        this._analyticsCookieName = this._resolveCookieFor('analytics_storage') || 's4gtm-analytics';
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
        if (this._isReady()) {
            callback();

            return;
        }
        this._readyCallbacks.push(callback);
    }

    _isReady() {
        return this._containerLoaded && this._userDataSettled;
    }

    _flushReady() {
        if (!this._isReady() || this._readyCallbacks.length === 0) {
            return;
        }

        const callbacks = this._readyCallbacks;
        this._readyCallbacks = [];
        callbacks.forEach((callback) => callback());
    }

    init() {
        if (!this._consentManaged) {
            this._grantedAnalytics = true;
            this._grantedEc = true;
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

        this._syncGateCookies(state);
        this._apply(state, state.ad_user_data === 'granted', anyGranted);
    }

    _applyFromCookies() {
        const state = {};
        let anyGranted = false;

        Object.keys(this._mapping).forEach((cookieName) => {
            if (!this._isCookieGranted(cookieName)) {
                return;
            }
            anyGranted = true;
            this._mapping[cookieName].forEach((consentKey) => {
                state[consentKey] = 'granted';
            });
        });

        this._apply(state, this._isCookieGranted(this._ecCookieName), anyGranted);
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

        this._apply(state, updated[this._ecCookieName] === true, anyGranted);
    }

    _apply(state, ecGranted, anyGranted) {
        if (this._sendConsentSignals) {
            this._dataLayer.consentUpdate(state);
        }

        if (state.analytics_storage === 'granted') {
            this._grantedAnalytics = true;
        }
        if (ecGranted) {
            this._grantedEc = true;
        }

        this._syncUserData();

        if (anyGranted) {
            this._loadContainer();
        }
    }

    _syncGateCookies(state) {
        if ('analytics_storage' in state) {
            this._syncCookie(this._analyticsCookieName, state.analytics_storage === 'granted');
        }
        if (this._ecCookieName && 'ad_user_data' in state) {
            this._syncCookie(this._ecCookieName, state.ad_user_data === 'granted');
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
        if (this._userDataPending) {
            return;
        }

        if (!this._userDataUrl) {
            this._settleUserData();

            return;
        }

        const needUser = this._grantedAnalytics && !this._pushedUser;
        const needEc = this._ecEnabled && this._grantedEc && !this._pushedEc;
        if (!needUser && !needEc) {
            this._settleUserData();

            return;
        }

        if (this._loginStatus !== 'logged-in') {
            this._pushedUser = true;
            this._pushedEc = true;
            this._settleUserData();

            return;
        }

        this._userDataPending = true;
        this._startUserDataDeadline();

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
                if (this._ecEnabled && this._grantedEc) {
                    this._pushedEc = true;
                }

                this._syncUserData();
            })
            .catch(() => {
                this._userDataPending = false;
                this._settleUserData();
            });
    }

    _startUserDataDeadline() {
        if (this._userDataTimer !== null || this._userDataSettled) {
            return;
        }

        this._userDataTimer = window.setTimeout(() => {
            this._userDataTimer = null;
            this._settleUserData();
        }, USER_DATA_TIMEOUT);
    }

    _settleUserData() {
        if (this._userDataTimer !== null) {
            window.clearTimeout(this._userDataTimer);
            this._userDataTimer = null;
        }
        if (this._userDataSettled) {
            return;
        }

        this._userDataSettled = true;
        this._flushReady();
    }

    _loadContainer() {
        if (this._containerLoaded || !this._containerId) {
            return;
        }
        this._containerLoaded = true;

        const nonce = this._cspNonce;
        const inBody = this._tagInBody;
        const origin = this._scriptOrigin;
        (function loadGtm(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
            const j = d.createElement(s);
            j.async = true;
            if (nonce) {
                j.setAttribute('nonce', nonce);
            }
            j.src = origin + '/gtm.js?id=' + i;

            const anchor = inBody ? null : d.getElementsByTagName(s)[0];
            if (anchor && anchor.parentNode) {
                anchor.parentNode.insertBefore(j, anchor);
            } else {
                (d.body || d.head || d.documentElement).appendChild(j);
            }
        }(window, document, 'script', 'dataLayer', this._containerId));

        this._flushReady();
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
