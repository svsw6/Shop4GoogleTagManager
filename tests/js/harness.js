/**
 * Laedt das GEBAUTE Storefront-Bundle in jsdom.
 *
 * Bewusst gegen das Build-Artefakt statt gegen die Quellen: geprueft wird damit genau das,
 * was im Shop ausgeliefert wird - inklusive Webpack-Transpilierung.
 */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const PLUGIN_ROOT = path.resolve(__dirname, '..', '..');
const BUNDLE = path.join(
    PLUGIN_ROOT,
    'src/Resources/app/storefront/dist/storefront/js/shop4-google-tag-manager/shop4-google-tag-manager.js',
);

function fixture(name) {
    return fs.readFileSync(path.join(__dirname, 'fixtures', name), 'utf8');
}

/**
 * @param {string} html  Fixture-Markup
 * @param {string} url   Seiten-URL (bestimmt u.a. den Query-Parameter p fuer den Listen-Index)
 */
function loadPlugin(html, url) {
    if (!fs.existsSync(BUNDLE)) {
        throw new Error(`Bundle fehlt: ${BUNDLE}\nVorher bin/build-storefront.sh laufen lassen.`);
    }

    const dom = new JSDOM(html, {
        url: url || 'https://shop.example/',
        runScripts: 'outside-only',
        pretendToBeVisual: true,
    });

    const { window } = dom;
    const registered = {};

    // vom plugin-system wird nur register() gebraucht
    window.PluginManager = {
        register(name, ctor, selector) {
            registered[name] = { ctor, selector };
        },
        getPluginInstances() { return []; },
    };

    // dataLayer als einfacher rekorder: so ist die REIHENFOLGE der pushes pruefbar
    const pushes = [];
    window.dataLayer = {
        push(entry) { pushes.push(entry); return 1; },
    };

    const fetches = [];
    window.fetch = (input, opts) => {
        fetches.push({ url: String(input), opts });
        return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(window.__s4gtmUserData || { user: {} }),
        });
    };

    // jsdom kennt keinen IntersectionObserver - der stub merkt sich die beobachteten
    // elemente, damit der test das Sichtbarwerden gezielt ausloesen kann
    const observed = [];
    window.IntersectionObserver = class {
        constructor(callback) { this._callback = callback; observed.push(this); }
        observe(el) { this._el = this._el || []; this._el.push(el); }
        unobserve(el) { this._el = (this._el || []).filter((e) => e !== el); }
        disconnect() { this._el = []; }
        trigger() {
            this._callback((this._el || []).map((target) => ({ target, isIntersecting: true })), this);
        }
    };

    window.eval(fs.readFileSync(BUNDLE, 'utf8'));

    return { dom, window, registered, pushes, fetches, observed };
}

/**
 * Instanziert das registrierte Plugin ohne Shopwares Plugin-Lifecycle.
 *
 * @param {object} ctx              Rueckgabe von loadPlugin()
 * @param {object} [configOverrides] wird ueber die Konfiguration im Fixture gelegt
 */
function instantiate(ctx, configOverrides) {
    const { window, registered } = ctx;
    const entry = registered.S4GtmPlugin;
    if (!entry) {
        throw new Error('S4GtmPlugin wurde nicht registriert');
    }

    let el = window.document.querySelector(entry.selector);
    if (el === null) {
        el = window.document.createElement('script');
        el.setAttribute('type', 'application/json');
        el.setAttribute('data-s4gtm-data', '');
        window.document.body.appendChild(el);
    }

    if (configOverrides !== undefined) {
        const base = el.textContent.trim() === '' ? {} : JSON.parse(el.textContent);
        el.textContent = JSON.stringify({ ...base, ...configOverrides });
    }

    const instance = Object.create(entry.ctor.prototype);
    instance.el = el;
    instance.$emitter = { publish() {}, subscribe() {} };
    instance.options = {};
    instance.init();

    return instance;
}

function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

module.exports = { loadPlugin, instantiate, fixture, wait, BUNDLE };
