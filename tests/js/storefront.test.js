/**
 * Integrationstests des Storefront-Bundles gegen echtes Shop-Markup.
 *
 * Bewusst ohne Test-Framework: eine einzige Abhaengigkeit (jsdom) genuegt, und das
 * Ergebnis laesst sich in jeder CI ohne weitere Einrichtung lesen.
 *
 *   cd tests/js && npm install && npm test
 */
const { loadPlugin, instantiate, fixture, wait } = require('./harness');

let failed = 0;

function check(name, condition, detail) {
    if (!condition) {
        failed += 1;
        console.log(`FAIL  ${name}${detail ? '\n      ' + detail : ''}`);
        return;
    }
    console.log(`PASS  ${name}`);
}

function section(title) {
    console.log(`\n--- ${title}`);
}

/**
 * Kurzform der dataLayer-Pushes, damit sich die Reihenfolge lesbar pruefen laesst.
 */
function sequence(pushes) {
    return pushes.map((entry) => (entry.event ? 'event:' + entry.event : Object.keys(entry).join('+')));
}

async function listingTests() {
    section('Listing (Theme ersetzt den Produktbox-Block ohne parent())');

    const ctx = loadPlugin(fixture('listing.html'), 'https://shop.example/Food/Bakery-products/');
    const { window } = ctx;

    window.document.cookie = 's4gtm-analytics=1; path=/';
    instantiate(ctx, {
        events: [{
            event: 'view_item_list',
            ecommerce: {
                currency: 'EUR',
                item_list_id: 'kategorie-1',
                item_list_name: 'Backwaren',
                items: [],
            },
        }],
    });
    await wait(50);

    check('Fixture bildet den Theme-Fall ab (kein eigenes data-Element)',
        window.document.querySelectorAll('.s4gtm-product-data').length === 0);
    check('Shopwares data-product-information ist vorhanden',
        window.document.querySelectorAll('[data-product-information]').length > 0);

    const form = window.document.querySelector('form[action*="/checkout/line-item/add"]');
    check('Add-to-Cart-Formular im Listing gefunden', form !== null);

    if (form !== null) {
        const before = ctx.pushes.length;
        form.dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        await wait(20);

        const added = ctx.pushes.slice(before).filter((p) => p.event === 'add_to_cart');
        check('add_to_cart feuert ueber den Core-Fallback', added.length === 1,
            JSON.stringify(sequence(ctx.pushes.slice(before))));

        if (added.length === 1) {
            const item = added[0].ecommerce.items[0];
            check('Fallback-Item traegt item_id', typeof item.item_id === 'string' && item.item_id !== '',
                JSON.stringify(item));
            check('Fallback-Item traegt einen numerischen Preis', typeof item.price === 'number',
                JSON.stringify(item));
        }
    }

    const wrapper = window.document.querySelector('.cms-element-product-listing-wrapper');
    check('Listing-Wrapper vorhanden', wrapper !== null);

    if (wrapper !== null) {
        const before = ctx.pushes.length;
        const box = wrapper.querySelector('[data-product-information]');
        box.parentElement.removeChild(box);
        await wait(300);

        const lists = ctx.pushes.slice(before).filter((p) => p.event === 'view_item_list');
        check('view_item_list nach AJAX-Austausch der Liste', lists.length === 1,
            JSON.stringify(sequence(ctx.pushes.slice(before))));

        if (lists.length === 1) {
            check('nachgetracktes Event traegt item_list_id',
                lists[0].ecommerce.item_list_id === 'kategorie-1');
            check('nachgetracktes Event traegt Items',
                Array.isArray(lists[0].ecommerce.items) && lists[0].ecommerce.items.length > 0);
        }

        const before2 = ctx.pushes.length;
        wrapper.setAttribute('data-noise', '1');
        await wait(300);
        check('kein doppeltes view_item_list bei unveraenderter Liste',
            ctx.pushes.slice(before2).filter((p) => p.event === 'view_item_list').length === 0);
    }
}

async function orderingTests() {
    section('Reihenfolge im dataLayer');

    const ctx = loadPlugin(fixture('finish.html'), 'https://shop.example/checkout/finish');
    ctx.window.document.cookie = 's4gtm-analytics=1; path=/';
    ctx.window.document.cookie = 's4gtm-enhanced-conversions=1; path=/';
    ctx.window.__s4gtmUserData = {
        user: { customerGroup: 'Standard', userId: 'abc' },
        enhancedConversion: { sha256_email_address: 'deadbeef' },
    };
    instantiate(ctx);
    await wait(150);

    const order = sequence(ctx.pushes);
    const userIdx = order.findIndex((o) => o.indexOf('user') !== -1);
    const ecIdx = order.findIndex((o) => o.indexOf('enhancedConversion') !== -1);
    const purchaseIdx = order.indexOf('event:purchase');

    check('user-Objekt landet im dataLayer', userIdx !== -1, JSON.stringify(order));
    check('enhancedConversion landet im dataLayer', ecIdx !== -1, JSON.stringify(order));
    check('purchase-Event landet im dataLayer', purchaseIdx !== -1, JSON.stringify(order));
    // kern der sache: GTM liest die variablen beim feuern des conversion-tags.
    // stehen sie danach im dataLayer, sind sie fuer dieses tag nie da gewesen.
    check('user-Daten stehen VOR dem purchase-Event',
        userIdx !== -1 && purchaseIdx !== -1 && userIdx < purchaseIdx, JSON.stringify(order));
    check('enhancedConversion steht VOR dem purchase-Event',
        ecIdx !== -1 && purchaseIdx !== -1 && ecIdx < purchaseIdx, JSON.stringify(order));
}

async function userDataTests() {
    section('User-Data-Endpunkt');

    const ctx = loadPlugin(fixture('finish.html'), 'https://shop.example/checkout/finish');
    ctx.window.document.cookie = 's4gtm-analytics=1; path=/';
    instantiate(ctx, { userDataUrl: null });
    await wait(60);

    check('ohne Endpunkt feuert purchase sofort',
        ctx.pushes.some((p) => p.event === 'purchase'), JSON.stringify(sequence(ctx.pushes)));
    check('ohne Endpunkt wird nicht angefragt',
        ctx.fetches.filter((f) => f.url.indexOf('user-data') !== -1).length === 0);

    // haengender request darf die events nicht dauerhaft blockieren
    const slow = loadPlugin(fixture('finish.html'), 'https://shop.example/checkout/finish');
    slow.window.document.cookie = 's4gtm-analytics=1; path=/';
    slow.window.fetch = () => new Promise(() => {});
    instantiate(slow);
    await wait(60);
    check('haengender Request haelt die Events zunaechst zurueck',
        !slow.pushes.some((p) => p.event === 'purchase'));
    await wait(1700);
    check('nach dem Timeout feuert purchase trotzdem',
        slow.pushes.some((p) => p.event === 'purchase'), JSON.stringify(sequence(slow.pushes)));
}

async function consentTests() {
    section('Consent-Gating');

    const ctx = loadPlugin(fixture('finish.html'), 'https://shop.example/checkout/finish');
    instantiate(ctx);
    await wait(120);

    check('ohne Einwilligung kein purchase-Event',
        ctx.pushes.filter((p) => p.event === 'purchase').length === 0, JSON.stringify(sequence(ctx.pushes)));
    check('ohne Einwilligung kein gtm.js',
        ctx.window.document.querySelectorAll('script[src*="googletagmanager.com/gtm.js"]').length === 0);

    ctx.window.document.dispatchEvent(new ctx.window.CustomEvent('CookieConfiguration_Update', {
        detail: { 's4gtm-analytics': true },
    }));
    await wait(120);

    check('nach Einwilligung feuert purchase genau einmal',
        ctx.pushes.filter((p) => p.event === 'purchase').length === 1, JSON.stringify(sequence(ctx.pushes)));
    check('nach Einwilligung wird gtm.js eingehaengt',
        ctx.window.document.querySelectorAll('script[src*="googletagmanager.com/gtm.js"]').length === 1);

    // marketing-einwilligung hebt ad_user_data, oeffnet aber NICHT die uebermittlung
    // gehashter kundendaten - die haengt an ihrem eigenen banner-eintrag
    const ec = loadPlugin(fixture('finish.html'), 'https://shop.example/checkout/finish');
    ec.window.document.cookie = 's4gtm-marketing=1; path=/';
    ec.window.__s4gtmUserData = {
        user: {},
        enhancedConversion: { sha256_email_address: 'deadbeef' },
    };
    instantiate(ec);
    await wait(150);
    check('Marketing allein laedt keine gehashten Kundendaten nach',
        ec.fetches.filter((f) => f.url.indexOf('user-data') !== -1).length === 0,
        JSON.stringify(ec.fetches.map((f) => f.url)));
}

async function pendingEventTests() {
    section('Vorgemerkte Events');

    const viaCookie = loadPlugin(fixture('finish.html'), 'https://shop.example/');
    viaCookie.window.document.cookie = 's4gtm-analytics=1; path=/';
    viaCookie.window.document.cookie = 's4gtm-pending=1; path=/';
    instantiate(viaCookie, { hasPendingEvents: false, events: [], userDataUrl: null });
    await wait(80);
    check('Cookie loest den Abruf aus, auch wenn das HTML-Flag aus dem Cache kommt',
        viaCookie.fetches.filter((f) => f.url.indexOf('pending-events') !== -1).length === 1,
        JSON.stringify(viaCookie.fetches.map((f) => f.url)));

    const neither = loadPlugin(fixture('finish.html'), 'https://shop.example/');
    neither.window.document.cookie = 's4gtm-analytics=1; path=/';
    instantiate(neither, { hasPendingEvents: false, events: [], userDataUrl: null });
    await wait(80);
    check('ohne Flag und ohne Cookie kein Abruf',
        neither.fetches.filter((f) => f.url.indexOf('pending-events') !== -1).length === 0);
}

const LIST_EVENT = {
    event: 'view_item_list',
    ecommerce: {
        currency: 'EUR',
        item_list_id: 'kategorie-1',
        item_list_name: 'Backwaren',
        items: [],
    },
};

async function selectItemTests() {
    section('select_item und Listen-Zuordnung');

    const ctx = loadPlugin(fixture('listing.html'), 'https://shop.example/Food/Bakery-products/');
    ctx.window.document.cookie = 's4gtm-analytics=1; path=/';
    instantiate(ctx, { events: [LIST_EVENT], userDataUrl: null });
    await wait(50);

    const link = ctx.window.document.querySelector('[data-product-information] a');
    check('Produktlink im Listing gefunden', link !== null);

    if (link !== null) {
        const before = ctx.pushes.length;
        link.dispatchEvent(new ctx.window.MouseEvent('click', { bubbles: true, cancelable: true }));
        await wait(20);

        const selected = ctx.pushes.slice(before).filter((p) => p.event === 'select_item');
        check('select_item beim Klick auf die Produktkachel', selected.length === 1,
            JSON.stringify(sequence(ctx.pushes.slice(before))));

        if (selected.length === 1) {
            const item = selected[0].ecommerce.items[0];
            check('select_item traegt die Liste', selected[0].ecommerce.item_list_id === 'kategorie-1',
                JSON.stringify(selected[0].ecommerce));
            check('select_item traegt die Position', typeof item.index === 'number', JSON.stringify(item));

            // zweite plugin-instanz im selben tab: der sessionStorage ist derselbe, die
            // gemerkte liste muss das folgende view_item erreichen
            const itemId = item.item_id;
            const before2 = ctx.pushes.length;
            instantiate(ctx, {
                userDataUrl: null,
                events: [{
                    event: 'view_item',
                    ecommerce: { currency: 'EUR', value: 1, items: [{ item_id: itemId, item_name: 'X', price: 1, quantity: 1 }] },
                }],
            });
            await wait(50);

            const viewed = ctx.pushes.slice(before2).filter((p) => p.event === 'view_item');
            check('view_item uebernimmt die Liste des Klicks',
                viewed.length === 1 && viewed[0].ecommerce.item_list_id === 'kategorie-1',
                JSON.stringify(viewed.map((v) => v.ecommerce)));
        }
    }

    // klick auf den warenkorb-button darf kein select_item ausloesen
    const submitButton = ctx.window.document.querySelector('form[action*="/checkout/line-item/add"] button');
    if (submitButton !== null) {
        const before = ctx.pushes.length;
        submitButton.dispatchEvent(new ctx.window.MouseEvent('click', { bubbles: true, cancelable: true }));
        await wait(20);
        check('Warenkorb-Button loest kein select_item aus',
            ctx.pushes.slice(before).filter((p) => p.event === 'select_item').length === 0);
    }
}

async function offcanvasCartTests() {
    section('Offcanvas-Warenkorb');

    const ctx = loadPlugin(fixture('offcanvas-cart.html'), 'https://shop.example/');
    ctx.window.document.cookie = 's4gtm-analytics=1; path=/';
    instantiate(ctx, { events: [], userDataUrl: null });
    await wait(50);

    const template = ctx.window.document.getElementById('offcanvas-cart');
    check('Offcanvas-Fixture enthaelt den Marker',
        template !== null && template.innerHTML.indexOf('s4gtm-offcanvas-cart') !== -1);

    const open = () => {
        const node = template.content.cloneNode(true);
        ctx.window.document.body.appendChild(node);
    };
    const close = () => {
        const el = ctx.window.document.querySelector('.offcanvas');
        if (el !== null) { el.parentElement.removeChild(el); }
    };

    let before = ctx.pushes.length;
    open();
    await wait(300);
    const first = ctx.pushes.slice(before).filter((p) => p.event === 'view_cart');
    check('view_cart beim Oeffnen des Offcanvas-Warenkorbs', first.length === 1,
        JSON.stringify(sequence(ctx.pushes.slice(before))));
    if (first.length === 1) {
        check('view_cart traegt Positionen',
            Array.isArray(first[0].ecommerce.items) && first[0].ecommerce.items.length > 0);
        check('view_cart traegt einen Wert', typeof first[0].ecommerce.value === 'number');
    }

    // neurendern waehrend geoeffnet (z.B. nach Mengenaenderung) darf nicht erneut feuern
    before = ctx.pushes.length;
    const marker = ctx.window.document.querySelector('.s4gtm-offcanvas-cart');
    marker.setAttribute('data-noise', '1');
    await wait(300);
    check('kein zweites view_cart waehrend der Warenkorb offen bleibt',
        ctx.pushes.slice(before).filter((p) => p.event === 'view_cart').length === 0);

    // schliessen und erneut oeffnen feuert wieder
    before = ctx.pushes.length;
    close();
    await wait(300);
    open();
    await wait(300);
    check('erneutes Oeffnen feuert wieder view_cart',
        ctx.pushes.slice(before).filter((p) => p.event === 'view_cart').length === 1,
        JSON.stringify(sequence(ctx.pushes.slice(before))));
}

async function cartQuantityTests() {
    section('Mengenaenderung im Warenkorb');

    const ctx = loadPlugin(fixture('cart-page.html'), 'https://shop.example/checkout/cart');
    ctx.window.document.cookie = 's4gtm-analytics=1; path=/';
    instantiate(ctx, { events: [], userDataUrl: null });
    await wait(50);

    const form = ctx.window.document.querySelector('form[action*="change-quantity"]');
    check('Mengen-Formular gefunden', form !== null);

    const data = ctx.window.document.querySelector('.s4gtm-cart-item-data');
    check('Positionsdaten gefunden', data !== null);

    if (form !== null && data !== null) {
        const current = JSON.parse(data.getAttribute('data-s4gtm-item')).quantity;
        const input = form.querySelector('[name="quantity"]');
        check('Mengenfeld im Formular gefunden', input !== null);

        if (input !== null) {
            let before = ctx.pushes.length;
            input.value = String(current + 3);
            form.dispatchEvent(new ctx.window.Event('submit', { bubbles: true, cancelable: true }));
            await wait(20);
            const added = ctx.pushes.slice(before).filter((p) => p.event === 'add_to_cart');
            check('Erhoehen sendet add_to_cart mit der Differenz',
                added.length === 1 && added[0].ecommerce.items[0].quantity === 3,
                JSON.stringify(added.map((a) => a.ecommerce.items[0])));

            before = ctx.pushes.length;
            input.value = String(Math.max(0, current - 1));
            form.dispatchEvent(new ctx.window.Event('submit', { bubbles: true, cancelable: true }));
            await wait(20);
            const removed = ctx.pushes.slice(before).filter((p) => p.event === 'remove_from_cart');
            check('Verringern sendet remove_from_cart mit der Differenz',
                removed.length === 1 && removed[0].ecommerce.items[0].quantity === 1,
                JSON.stringify(removed.map((r) => r.ecommerce.items[0])));

            before = ctx.pushes.length;
            input.value = String(current);
            form.dispatchEvent(new ctx.window.Event('submit', { bubbles: true, cancelable: true }));
            await wait(20);
            check('unveraenderte Menge sendet nichts',
                ctx.pushes.slice(before).length === 0, JSON.stringify(sequence(ctx.pushes.slice(before))));
        }
    }
}

async function wishlistPromotionShareTests() {
    section('Merkzettel, Promotionen und Teilen');

    const ctx = loadPlugin(fixture('wishlist.html'), 'https://shop.example/produkt');
    ctx.window.document.cookie = 's4gtm-analytics=1; path=/';
    instantiate(ctx, { events: [], userDataUrl: null });
    await wait(50);

    const button = ctx.window.document.querySelector('[data-add-to-wishlist]');
    let before = ctx.pushes.length;
    button.dispatchEvent(new ctx.window.MouseEvent('click', { bubbles: true, cancelable: true }));
    await wait(20);
    const wished = ctx.pushes.slice(before).filter((p) => p.event === 'add_to_wishlist');
    check('add_to_wishlist beim Merken', wished.length === 1,
        JSON.stringify(sequence(ctx.pushes.slice(before))));
    if (wished.length === 1) {
        check('add_to_wishlist traegt das Produkt',
            wished[0].ecommerce.items[0].item_id === 'SW-WISH-1', JSON.stringify(wished[0].ecommerce));
    }

    // bereits gemerkt -> der klick entfernt, GA4 kennt dafuer kein standard-event
    before = ctx.pushes.length;
    button.classList.remove('product-wishlist-not-added');
    button.classList.add('product-wishlist-added');
    button.dispatchEvent(new ctx.window.MouseEvent('click', { bubbles: true, cancelable: true }));
    await wait(20);
    check('Entfernen vom Merkzettel sendet kein Event',
        ctx.pushes.slice(before).filter((p) => p.event === 'add_to_wishlist').length === 0);

    // view_promotion ueber den IntersectionObserver
    before = ctx.pushes.length;
    check('Promotion-Banner wird beobachtet', ctx.observed.length === 1);
    ctx.observed.forEach((observer) => observer.trigger());
    await wait(20);
    const viewed = ctx.pushes.slice(before).filter((p) => p.event === 'view_promotion');
    check('view_promotion beim Sichtbarwerden', viewed.length === 1,
        JSON.stringify(sequence(ctx.pushes.slice(before))));
    if (viewed.length === 1) {
        check('view_promotion traegt die Kampagnendaten',
            viewed[0].ecommerce.promotion_id === 'SOMMER25'
            && viewed[0].ecommerce.creative_name === 'hero',
            JSON.stringify(viewed[0].ecommerce));
    }

    before = ctx.pushes.length;
    ctx.window.document.querySelector('[data-s4gtm-promotion] a')
        .dispatchEvent(new ctx.window.MouseEvent('click', { bubbles: true, cancelable: true }));
    await wait(20);
    check('select_promotion beim Klick',
        ctx.pushes.slice(before).filter((p) => p.event === 'select_promotion').length === 1,
        JSON.stringify(sequence(ctx.pushes.slice(before))));

    before = ctx.pushes.length;
    ctx.window.document.querySelector('[data-s4gtm-share]')
        .dispatchEvent(new ctx.window.MouseEvent('click', { bubbles: true, cancelable: true }));
    await wait(20);
    const shared = ctx.pushes.slice(before).filter((p) => p.event === 'share');
    check('share beim Klick auf den Teilen-Button', shared.length === 1,
        JSON.stringify(sequence(ctx.pushes.slice(before))));
    if (shared.length === 1) {
        check('share traegt method und item_id',
            shared[0].method === 'whatsapp' && shared[0].item_id === 'SW-WISH-1',
            JSON.stringify(shared[0]));
    }
}

async function serverContainerTests() {
    section('Server-Side Tagging');

    const ctx = loadPlugin(fixture('finish.html'), 'https://shop.example/checkout/finish');
    instantiate(ctx, { scriptOrigin: 'https://gtm.example.com', userDataUrl: null });
    await wait(50);

    ctx.window.document.dispatchEvent(new ctx.window.CustomEvent('CookieConfiguration_Update', {
        detail: { 's4gtm-analytics': true },
    }));
    await wait(80);

    const scripts = Array.from(ctx.window.document.querySelectorAll('script[src*="/gtm.js"]'));
    check('gtm.js wird vom eigenen Server-Container geladen',
        scripts.length === 1 && scripts[0].src.indexOf('https://gtm.example.com/gtm.js?id=') === 0,
        JSON.stringify(scripts.map((s) => s.src)));
    check('kein Request an googletagmanager.com',
        scripts.every((s) => s.src.indexOf('googletagmanager.com') === -1));
}

async function main() {
    await listingTests();
    await selectItemTests();
    await offcanvasCartTests();
    await cartQuantityTests();
    await wishlistPromotionShareTests();
    await orderingTests();
    await userDataTests();
    await consentTests();
    await serverContainerTests();
    await pendingEventTests();

    console.log('');
    if (failed === 0) {
        console.log('OK - alle Storefront-Tests bestanden');
        process.exit(0);
    }
    console.log(`FEHLER - ${failed} Test(s) fehlgeschlagen`);
    process.exit(1);
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
