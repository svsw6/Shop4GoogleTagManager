# Google Tag Manager

[🇩🇪 Deutsch](README.md) | [🇬🇧 English](README.en.md)

Google-Tag-Manager-Integration für Shopware 6.7 mit vollständigem GA4-Enhanced-Ecommerce-dataLayer und Google Consent Mode v2.

> ℹ️ Newsletter-Tracking erfolgt über das `newsletter_signup`-Event nach Double-Opt-in. Kontaktformular-Tracking (`generate_lead`) und das Tracking sonstiger Formulare (`form_submit`) sind clientseitig umgesetzt und übertragen dabei **keine Formularfeld-Inhalte** (siehe „Formular-Tracking“).

## Anforderungen

- Shopware 6.7
- PHP 8.3 oder höher

## Funktionen

- Einbindung des GTM-Containers im Head (alternativ am Ende des Body); ein `noscript`-Fallback wird nur dort ausgegeben, wo der Container ohnehin ohne Einwilligung laden darf
- Serverseitig aufgebautes dataLayer für Shop-, Sprach-, Währungs- und Kundenkontext
- GA4 Enhanced Ecommerce: `view_item`, `view_item_list`, `select_item`, `search`, `add_to_cart`, `remove_from_cart`, `view_cart`, `add_to_wishlist`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`
- Kunden-Events: `login`, `logout`, `sign_up`, `newsletter_signup`
- Kampagnen- und Teilen-Events (`view_promotion`, `select_promotion`, `share`) über Opt-in-Attribute am eigenen Markup
- Formular-Events: `generate_lead` (Kontaktformular) und `form_submit` (CMS-/Plugin-Formulare) – clientseitig, **ohne** Übertragung von Formularfeld-Inhalten
- Eigene **Custom-Events** je Seitenkontext, frei konfigurierbar und einzelnen Verkaufskanälen zuweisbar
- Google Consent Mode v2 inklusive `ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`
- Pro Verkaufskanal konfigurierbar
- **Server-Side Tagging**: `gtm.js` und der `noscript`-Fallback können vom eigenen GTM-Server-Container statt von googletagmanager.com geladen werden
- Debug-Modus mit Konsolen-Logging

## Installation

```bash
# plugin in custom/plugins/Shop4GoogleTagManager ablegen
bin/console plugin:refresh
bin/console plugin:install --activate Shop4GoogleTagManager
bin/console cache:clear
```

## Konfiguration

Zu finden in der Administration unter **Einstellungen → Erweiterungen → Google Tag Manager**. Die Oberfläche hat zwei Tabs: **Konfiguration** und **Events**.

### Tab „Konfiguration“

Verkaufskanal-Auswahl und Speichern liegen oben rechts in der Kopfzeile. Ohne Auswahl wird die globale Konfiguration bearbeitet; bei gewähltem Kanal zeigt jedes Feld per Vererbungs-Symbol an, ob es den globalen Wert erbt oder kanalspezifisch überschrieben ist (Shopware-Standard).

| Bereich | Feld | Bedeutung |
| --- | --- | --- |
| Grundkonfiguration | Plugin aktiv | Schaltet die gesamte Einbindung an oder aus |
| Grundkonfiguration | GTM Container-ID | Container im Format `GTM-XXXXXXX` |
| Grundkonfiguration | Server-Container-URL | Optional. Adresse des eigenen GTM-Server-Containers (`https://…`). Ist sie gesetzt, lädt der Container von dort statt von googletagmanager.com |
| Grundkonfiguration | Debug-Modus | Konsolen-Logging (Präfix `[s4gtm]`); Lade-/Consent-Verhalten identisch zum Normalbetrieb |
| Grundkonfiguration | Tag-Position | Head-Bereich (Standard) oder Ende des Body |
| Consent | Consent-Quelle | `Shopware-Cookie-Banner` (Standard), `Externer CMP` oder `Keine Cookies`. Die ersten beiden gelten als **verwaltet**: der Container lädt erst nach Einwilligung. `Keine Cookies` lädt sofort und ungated |
| Consent | Google Consent Mode v2 | `gtag('consent', …)`-Signale senden; Defaults auf `denied`, Anhebung pro Zweck. **Standard: an** |
| Consent | Advanced Consent Mode | **Opt-in, rechtlich umstritten.** Lädt den Container bereits vor der Einwilligung mit `denied`-Defaults, sodass Google bei Ablehnung cookielose Modellierungs-Pings erhält. Nur wirksam bei verwalteter Consent-Quelle **und** aktivem Consent Mode v2. Eigene PII bleibt bis zur Analytics-Einwilligung blockiert. **Standard: aus** |
| Consent | `wait_for_update` | Wartezeit in Millisekunden, die GTM dem Consent-Update einräumt, bevor die Tags mit den Defaults feuern. **Standard: 500** |
| Erweitert | Data Layer | dataLayer-Ausgabe aktivieren |
| Erweitert | Enhanced Ecommerce | Produkt-, Listen- und Such-Events |
| Erweitert | Checkout-Tracking | Warenkorb-, Checkout- und Kauf-Events |
| Erweitert | Remarketing | Werbebezogene Signale (`ad_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage`) freischalten und den Marketing-Cookie im Banner einblenden; aus = nie angehoben |
| Erweitert | Eager-Load im Checkout | Lädt den Container auf Checkout-Seiten serverseitig, **wenn bereits eingewilligt wurde**. Checkout-Seiten sind `no-store`, daher darf der bereits erteilte Zweck dort direkt als `granted` im `consent default` stehen. **Standard: aus** |
| Erweitert | Enhanced Conversions | `Deaktiviert` (Standard), `Nur E-Mail-Adresse` oder `Komplette Kundendaten`. Überträgt ausschließlich SHA-256-Hashes und nur nach Einwilligung in den eigenen Banner-Eintrag |
| Erweitert | User-ID-Tracking | pseudonyme `user_id` (Kunden-UUID) übertragen (erst nach Analytics-Einwilligung) – **einzige** Stelle, an der die stabile Personenkennung ausgegeben wird |
| Erweitert | Kunden-Tracking | nur **nicht** direkt identifizierende Merkmale (Kundengruppe, Gast-Flag) ins dataLayer (erst nach Analytics-Einwilligung) |
| Erweitert | Suchbegriff anonymisieren | Lässt das Feld `search_term` im `search`-Event weg. Das Event verliert damit seinen Aussagewert, deshalb **Standard: aus** – der Begriff geht ohnehin erst nach Statistik-Einwilligung raus |
| Formulare | Newsletter | Newsletter-`sign_up`-Event nach Double-Opt-in |
| Formulare | Kontaktformular | `generate_lead` beim Absenden des Kontaktformulars (ohne Feldinhalte) |
| Formulare | Individuelle Formulare | `form_submit` für CMS-/Plugin-Formulare (ohne Feldinhalte; siehe „Formular-Tracking“) |

### Tab „Events“

- **Standard-Events** lassen sich je Event ein-/ausschalten und mit abweichendem GA4-Namen sowie zusätzlichen statischen dataLayer-Feldern (Payload) versehen. Das gilt auch für die clientseitigen Events (`select_item`, `add_to_wishlist`, `view_promotion`, `select_promotion`, `share`, `add_to_cart`, `remove_from_cart`, `view_cart`).
- **Custom-Events** werden frei angelegt: technischer Name, Seitenkontext (`product`, `listing`, `search`, `cart`, `checkout`, `purchase`, `global`), GA4-Event-Name, Priorität und statische Payload-Felder.
- Jedes Custom-Event lässt sich einem oder mehreren **Verkaufskanälen** zuweisen (Mehrfachauswahl). Ohne Zuweisung gilt es für alle Kanäle. Über das Dropdown lässt sich die Liste nach Verkaufskanal filtern.

## GTM-Container-Vorlage importieren

Unter [`docs/gtm-container-import.json`](docs/gtm-container-import.json) liegt eine generische, importierbare Container-Vorlage, die zu den Standard-Events des Plugins passt.

**Import:** GTM → Admin → *Container importieren* → Datei wählen → Workspace wählen → *Zusammenführen* (empfohlen).

Sie enthält:

- einen **Google-Tag (GA4-Konfiguration)** auf „Initialisierung – Alle Seiten“,
- je ein **GA4-Ereignis-Tag** für `view_item`, `view_item_list`, `select_item`, `search`, `add_to_cart`, `remove_from_cart`, `view_cart`, `add_to_wishlist`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`, `login`, `logout`, `sign_up`, `newsletter_signup`, `view_promotion`, `select_promotion`, `share`, `generate_lead` und `form_submit`,
- passende **Custom-Event-Trigger**, die den `event`-Schlüssel im dataLayer matchen,
- einen **Google-Ads-Block**: `Conversion Linker` (alle Seiten), die Conversion-Tags `Google Ads - Conversion (purchase)`, `Google Ads - add_to_cart` und `Google Ads - begin_checkout` (jeweils auf dem passenden Event-Trigger) sowie `Google Ads - Remarketing` (alle Seiten),
- die Variablen `GA4 Measurement ID`, `Google Ads Conversion ID`, `Google Ads Purchase Label`, `Google Ads Add To Cart Label`, `Google Ads Begin Checkout Label` (Konstanten) sowie die DataLayer-Variablen `DLV - search_term`, `DLV - method`, `DLV - value`, `DLV - currency`, `DLV - transaction_id`, `DLV - form_id` und `DLV - form_destination`.

Die Ecommerce-Tags lesen das `ecommerce`-Objekt automatisch aus dem dataLayer (entspricht exakt dem Plugin-Output). **Nach dem Import nur die Variable `GA4 Measurement ID` auf deine `G-XXXXXXXX`-ID setzen** – fertig. Alle GA4-Tags sind in den Consent-Einstellungen auf **„zusätzliche Einwilligung erforderlich: `analytics_storage`“** gesetzt. Sie feuern dadurch ausschließlich nach erteilter Statistik-Einwilligung – auch dann, wenn der Container bereits über die Zustimmung zu einem anderen Zweck (z. B. Google Ads) geladen wurde.

### Google Ads aktivieren

Der Google-Ads-Block ist **vorbereitet, aber inaktiv** (Platzhalter-IDs) und muss nach dem Import befüllt werden:

1. Variable **`Google Ads Conversion ID`** auf deine **numerische** Conversion-ID setzen (die Ziffern hinter `AW-`, z. B. `123456789`).
2. Pro Conversion-Aktion (Google Ads → Tools → Conversions) das jeweilige **Conversion-Label** in die passende Variable eintragen: **`Google Ads Purchase Label`** (Kauf), **`Google Ads Add To Cart Label`** (`add_to_cart`), **`Google Ads Begin Checkout Label`** (`begin_checkout`). Jede dieser Conversions muss in Google Ads als eigene Aktion angelegt sein.
3. Conversion-Tags, die du **nicht** als Conversion zählst (z. B. `add_to_cart`/`begin_checkout` als reine Micro-Conversions), kannst du löschen/pausieren – oder als Audience-Signal behalten.
4. Tag **`Google Ads - Remarketing`** nur behalten, wenn du Remarketing nutzt (sonst löschen/pausieren).

Die Ads-Tags lesen Wert/Währung/Bestellnummer aus dem `ecommerce`-Objekt (`DLV - value`/`currency`/`transaction_id`) und sind auf die Consent-Bedingungen **`ad_storage`** (Conversion/Linker) bzw. **`ad_storage` + `ad_personalization`** (Remarketing) gesetzt – sie feuern also erst nach erteilter Marketing-Einwilligung. Sie erfordern, dass im Plugin **„Remarketing“ aktiv** ist (sonst werden diese Signale nie angehoben).

> **Enhanced Conversions:** Aktivierst du im Plugin die Option *Enhanced Conversions*, liefert das Plugin nach `ad_user_data`-Einwilligung das gehashte `enhancedConversion`-Objekt in den dataLayer. Um es zu nutzen, im `Google Ads - Conversion`-Tag *Enhanced Conversions* einschalten und als „benutzerdefinierte Variable für Nutzerdaten“ eine DataLayer-Variable auf `enhancedConversion` mappen. Das ist account-/setup-spezifisch und daher bewusst **nicht** vorkonfiguriert.

## Consent Mode – Verhalten

Es gibt genau **einen** GTM-Container für alle Zwecke. Sobald einem Tracking-Zweck zugestimmt wird (z. B. nur Google Ads), lädt der Container. Welche Tags dann tatsächlich feuern, steuern zwei Mechanismen zusammen:

1. **Consent-Mode-Signale** des Plugins: bei „nur Ads“ stehen `ad_storage`/`ad_personalization` auf `granted`, `analytics_storage` bleibt `denied`.
2. **Consent-Bedingungen der Tags im Container** (Consent Settings): Die mitgelieferte Vorlage setzt für alle GA4-Tags `analytics_storage` als zusätzliche Voraussetzung voraus.

Dadurch werden GA4-Tags ohne Statistik-Einwilligung gar nicht erst ausgeführt – es gehen also auch keine cookielosen Pings an Google, solange der Statistik-Zweck nicht bestätigt wurde. Verwendest du einen eigenen Container, setze die Consent-Bedingungen deiner Tags entsprechend (`analytics_storage` für GA4, `ad_storage`/`ad_user_data` für Google Ads).

Identifizierende Nutzerdaten (pseudonyme `user_id`, Kundengruppe) werden erst **nach erteilter Analytics-Einwilligung** in den `dataLayer` geschrieben und damit an den Container/Google übergeben. Diese Werte werden **nicht in den Seitenquelltext eingebettet** (der HTML kann über Reverse-Proxy-/HTTP-Caches kundenübergreifend ausgeliefert werden). Stattdessen lädt das Storefront-JS sie erst nach der Einwilligung über einen eigenen, **niemals cachebaren Endpunkt** (`/s4gtm/user-data`, `Cache-Control: no-store, private`) und pusht sie dann in den `dataLayer`. Der grobe Login-Status (Gast/eingeloggt) im Basis-`dataLayer` ist immer verfügbar und enthält keine identifizierenden Merkmale.

Aus demselben Grund landen auch die **vorgemerkten Events** (`login`/`sign_up`/`newsletter_signup`, die wegen des Redirects erst auf der nächsten Seite ausgespielt werden) **nicht** im cachebaren HTML. Der Seitenaufbau setzt lediglich ein boolesches Hinweis-Flag; die eigentlichen Events holt das Storefront-JS nach dem Container-Load über den ebenfalls nicht cachebaren Endpunkt `/s4gtm/pending-events` (`Cache-Control: no-store, private`), der die Session-Queue dann serverseitig leert. So kann selbst ein spurious Event nicht kundenübergreifend ausgeliefert werden.

Das Hinweis-Flag steht zusätzlich in einem eigenen Cookie (`s4gtm-pending`). Das HTML-Flag allein genügt nämlich nicht: Nach einem Logout landet der Besucher auf einer cachebaren Seite, deren Flag aus dem Cache stammen kann – das Event bliebe dann in der Session liegen. Das Cookie wird pro Antwort gesetzt bzw. gelöscht und enthält keine Nutzdaten, nur „es liegt etwas an“.

### Consent-Quelle

Die **Consent-Quelle** entscheidet, ob der Container überhaupt einwilligungsabhängig lädt.

| Wert | Verhalten |
| --- | --- |
| **Shopware-Cookie-Banner** (Standard) | Consent gilt als **verwaltet**. Das Plugin ergänzt seine Einträge in der Statistik- und Marketing-Gruppe und hört auf `CookieConfiguration_Update`. `gtm.js` bleibt server- wie clientseitig blockiert, bis mindestens ein Zweck bestätigt ist. |
| **Externer CMP** | Ebenfalls **verwaltet** – nur meldet der CMP den Consent über die Bridge (siehe „Externe Consent-Manager“). Das Plugin setzt die First-Party-Gate-Cookies dann selbst, damit auch der serverseitige Endpunkt die Einwilligung kennt. |
| **Keine Cookies** | Consent gilt als **nicht verwaltet**: keine Consent-Signale, der Container lädt sofort beim Seitenaufruf, Tracking läuft vollständig **ungated**. In der EU regelmäßig nicht zulässig und allein Betreiberverantwortung – das Admin-UI verlangt vor dem Speichern eine explizite Bestätigung. |

**Google Consent Mode v2** steuert davon unabhängig nur, ob zusätzlich `gtag('consent', …)`-Signale gesendet werden: Defaults aller trackbaren Zwecke auf `denied`, Anhebung pro Zweck per Update. Bei verwalteter Quelle bleibt der Container auch ohne diese Signale blockiert – die zweckgenaue Steuerung im Container entfällt dann aber. Standardmäßig (Shopware-Banner + Consent Mode v2) entspricht das Googles **Basic-Modus**: vor der Einwilligung geht nichts an Google.

**Advanced Consent Mode** (Opt-in, nur wirksam bei verwalteter Quelle **und** aktivem Consent Mode v2): Der Container lädt **sofort** beim Seitenaufruf mit `denied`-Defaults. Lehnt der Besucher ab, sendet Google nur **cookielose Pings** (Einwilligungsstatus, Zeitstempel, URL, User-Agent, Conversion-Signale) für die Conversion- und Verhaltensmodellierung – es werden **keine Cookies** gesetzt. Das entspricht Googles **Advanced-Modus**. Identifizierende Nutzerdaten (`user_id`, Kundengruppe) bleiben weiterhin bis zur Analytics-Einwilligung blockiert. **Rechtlich umstritten:** Ob cookielose Pings ohne Einwilligung zulässig sind, ist in der EU nicht abschließend geklärt – Einsatz auf eigene Verantwortung nach Rechtsprüfung.

### Banner-Einträge und Consent-Zwecke

| Banner-Eintrag | Cookie | Consent-Mode-Zwecke | erscheint wenn |
| --- | --- | --- | --- |
| Google Analytics | `s4gtm-analytics` | `analytics_storage` | immer |
| Google Ads | `s4gtm-marketing` | `ad_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage` | Remarketing aktiv |
| Erweitertes Conversion-Tracking | `s4gtm-enhanced-conversions` | `ad_user_data` | Enhanced Conversions aktiv |

`ad_user_data` hängt bewusst am **Marketing**-Eintrag: Consent Mode v2 verlangt dieses Signal bereits für die gewöhnliche Google-Ads-Conversion-Messung, nicht erst für Enhanced Conversions. Hängt es an einem separaten Haken, verliert man Conversions von Besuchern, die der Marketing-Nutzung ausdrücklich zugestimmt haben. Der dritte Eintrag steuert deshalb nur noch die Übermittlung **gehashter Kundendaten** und wird auch nur dann eingeblendet, wenn Enhanced Conversions tatsächlich aktiv sind.

### Serverseitige Consent-Defaults und HTTP-Cache

Der `consent default` im Seitenkopf steht grundsätzlich auf `denied` – auch für Besucher, die längst eingewilligt haben. Grund: Das HTML kann im HTTP-Cache landen und von dort kundenübergreifend ausgeliefert werden; ein `granted` im Cache würde die Einwilligung eines Besuchers auf alle anderen übertragen.

Nur auf Seiten, die Shopware als **`no-store`** markiert (Warenkorb, Bestellbestätigung, Bestellabschluss), liest das Plugin die Consent-Cookies serverseitig und schreibt die bereits erteilten Zwecke direkt als `granted` in den Default. Zusammen mit **Eager-Load im Checkout** feuert das `purchase`-Tag dadurch sofort mit der richtigen Einwilligung, statt erst cookielos zu senden und auf das Storefront-JavaScript zu warten.

## Formular-Tracking

Kontaktformular- und Custom-Form-Tracking laufen **clientseitig** beim Absenden des jeweiligen Formulars und werden – wie die übrigen Seiten-Events – erst **nach** dem Laden des Containers (also nach Einwilligung) in den `dataLayer` gepusht.

- **Kontaktformular** (`trackContactForm`): Beim Absenden des Shopware-Kontaktformulars (`/form/contact`) wird `generate_lead` ausgelöst.
- **Individuelle Formulare** (`trackCustomForms`): Erfasst werden CMS-/Plugin-Formulare, die auf einen `/form/*`-Endpunkt posten (Kontakt und Newsletter ausgenommen, da separat abgedeckt), sowie jedes `<form>`, das explizit das Attribut `data-s4gtm-form` trägt. Ausgelöst wird `form_submit`.

```html
<!-- beliebiges formular gezielt für das Tracking markieren -->
<form action="/eigene-route" method="post" data-s4gtm-form="kontakt-footer">
    …
</form>
```

> 🔒 **Keine personenbezogenen Daten:** Es werden **ausschließlich statische Markup-Attribute** des Formulars gelesen (`data-s4gtm-form`, `id`, `name`, Pfadsegment der `action`) und als `form_id`/`form_destination` ausgegeben. **Formularfeld-Inhalte (Name, E-Mail, Nachricht) gelangen nie in den `dataLayer`.** Da das Event beim Absenden feuert, kann es auch dann gezählt werden, wenn die anschließende serverseitige Validierung das Formular ablehnt.

## Tracking-Verhalten im Browser

- **`purchase`-Deduplizierung:** Das `purchase`-Event wird je Bestellung nur **einmal** ausgelöst. Die Deduplizierung erfolgt client-seitig über die `transaction_id` (Bestellnummer), die das Storefront-JS in `localStorage` merkt – bewusst **ohne** schreibenden Zugriff auf die Bestellung im Seitenaufbau (das vermeidet `order.written`-Events, Indexer-Last und Race-Conditions). Löscht ein Besucher seinen `localStorage` und ruft die Bestellbestätigung erneut auf, kann das Event erneut feuern; GA4 dedupliziert `purchase` zusätzlich über die `transaction_id`.
- **Optimistisches `add_to_cart`:** Das client-seitige `add_to_cart`-Event wird bereits beim Absenden des Warenkorb-Formulars erfasst – also **bevor** der Server das Hinzufügen bestätigt hat. Schlägt der serverseitige Vorgang fehl (z. B. Bestand erschöpft), kann ein `add_to_cart` getrackt worden sein, das nicht zu einem Warenkorb-Eintrag führte. Die server-seitigen Events (`view_cart` etc.) überschneiden sich damit nicht. **Staffelpreise:** `price`/`value` des client-seitigen `add_to_cart` basieren auf dem im DOM hinterlegten Einzelpreis (Menge 1). Bei mengenabhängigen Staffelpreisen kann der getrackte Betrag daher vom tatsächlichen Warenkorbwert abweichen; die maßgeblichen server-seitigen Events (`view_cart`, `purchase`) verwenden die echten Preise.
- **Produktdaten im Listing:** Die Item-Daten für `add_to_cart` hängen am eigenen `<data>`-Element in der Produktbox. Ersetzt ein Theme den Block `component_product_box` ohne `{{ parent() }}`, verschwindet dieses Element – das Plugin fällt dann auf Shopwares eigenes Attribut `data-product-information` an der umgebenden Box zurück. Der Fallback liefert `item_id`, `item_name`, `item_brand` und `price`, jedoch keine Kategorie und keine Variante.
- **`view_item_list` bei Filtern und Pagination:** Filter, Sortierung und Seitenwechsel tauschen die Produktliste per AJAX aus, ohne dass ein Seiten-Event feuert. Das Storefront-JS beobachtet deshalb den Listen-Container und schickt bei geänderter Trefferliste ein weiteres `view_item_list` – mit `item_list_id`/`item_list_name` der Ausgangsliste. Der `index` wird aus dem Seitenparameter und der Seitengröße der ersten Liste gerechnet und kann auf der letzten Seite abweichen.
- **`select_item` und Listen-Zuordnung:** Ein Klick auf eine Produktkachel löst `select_item` aus und merkt sich Produkt, Liste und Position im `sessionStorage` (Schlüssel `s4gtm_list_attribution`, 30 Minuten gültig, ausschließlich Produkt- und Listenkontext, keine Nutzerdaten). Auf der folgenden Produktdetailseite erhalten `view_item` und `add_to_cart` dadurch dieselbe `item_list_id`/`item_list_name` – ohne diese Brücke bleiben GA4-Listenberichte leer. Zeigt die Liste die Stammartikelnummer und die Detailseite eine Variante, greift die Zuordnung nicht – dann fehlen die Listenfelder.
- **`view_cart` im Offcanvas-Warenkorb:** Für viele Besucher ist der Offcanvas die einzige Warenkorb-Ansicht. Gefeuert wird beim Öffnen, nicht bei jedem Neurendern – eine Mengenänderung im Offcanvas löst also kein zweites `view_cart` aus. Das Event folgt derselben Ein-/Aus-Schaltung wie das serverseitige `view_cart`. **Nach dem Update steigt die Zahl der `view_cart`-Events dadurch spürbar**; wer das nicht will, schaltet das Event im Tab „Events“ ab.
- **Mengenänderung im Warenkorb:** Wird die Menge einer Position geändert, sendet das Plugin die **Differenz** als `add_to_cart` beziehungsweise `remove_from_cart` – so erwartet es GA4. Unveränderte Mengen senden nichts.
- **`add_to_wishlist`:** Feuert beim Klick auf das Merkzettel-Symbol, solange das Produkt noch nicht gemerkt ist. Für das Entfernen kennt GA4 kein Standard-Event, deshalb wird dafür auch keins gesendet.
- **Reihenfolge der Nutzerdaten:** Die identifizierenden Nutzerdaten werden asynchron über `/s4gtm/user-data` nachgeladen, die Seiten-Events warten aber darauf. `user` und `enhancedConversion` stehen dadurch **vor** dem ersten Ecommerce-Event im `dataLayer` – sonst läse ein Conversion-Tag beim Feuern leere Variablen. Damit ein hängender Request das Tracking nicht blockiert, feuern die Events spätestens nach **1,5 Sekunden** auch ohne die Nutzerdaten. Sind Kunden-Tracking, User-ID-Tracking und Enhanced Conversions alle aus, entfällt der Request komplett.

## Kampagnen- und Teilen-Events

Shopware kennt keine Kampagnen-Elemente. Welches CMS-Element eine Kampagne ist, zeichnest du selbst aus – das Plugin liest die Attribute dann aus.

**Kampagnenbanner** (`data-s4gtm-promotion`): `view_promotion` feuert, sobald das Element zur Hälfte sichtbar wird (einmal pro Seitenaufruf), `select_promotion` beim Klick darauf.

```html
<div class="hero-banner"
     data-s4gtm-promotion='{"promotion_id":"SOMMER25","promotion_name":"Sommeraktion","creative_name":"hero","creative_slot":"startseite_oben"}'>
    …
</div>
```

Übernommen werden ausschließlich die GA4-Felder `promotion_id`, `promotion_name`, `creative_name`, `creative_slot` und `location_id`; mindestens `promotion_id` oder `promotion_name` muss gesetzt sein. Alles andere wird verworfen.

**Teilen-Buttons** (`data-s4gtm-share`): Der Attributwert wird zu `method`.

```html
<button data-s4gtm-share="whatsapp"
        data-s4gtm-share-content-type="product"
        data-s4gtm-share-item-id="SW-10001">Teilen</button>
```

Beide Event-Gruppen lassen sich im Tab „Events“ einzeln abschalten und umbenennen. Ohne die Attribute im Markup passiert nichts.

## Server-Side Tagging

Trägst du unter **Grundkonfiguration → Server-Container-URL** eine Adresse ein, lädt das Plugin `gtm.js` und den `noscript`-Fallback von dort statt von `googletagmanager.com`:

```
https://gtm.deinshop.de   →   https://gtm.deinshop.de/gtm.js?id=GTM-XXXXXXX
                              https://gtm.deinshop.de/ns.html?id=GTM-XXXXXXX
```

Akzeptiert werden nur `https`-Adressen ohne Query-Parameter. Eine ungültige Eingabe wird serverseitig verworfen und der Container lädt wieder von Google – sonst zeigt die Ladeadresse ins Leere und es wird gar nichts mehr getrackt. Das Admin-UI blockiert das Speichern zusätzlich.

Mehr macht das Plugin nicht. Ob auch die Messhits über deine Domain laufen, hängt davon ab, was hinter der Adresse steht – ein echter GTM-Server-Container oder ein Reverse Proxy – und wie die Tags im Container konfiguriert sind. Wie du so einen Endpunkt auf dem Hosting des Shops einrichtest, steht in [`docs/server-side-tagging-proxy.md`](docs/server-side-tagging-proxy.md).

Am Consent ändert sich nichts: Der Container lädt auch über die eigene Domain erst nach Einwilligung, und die Daten gehen weiterhin an Google – nur mit deinem Server dazwischen.

## Externe Consent-Manager (CMP)

Standardmäßig nutzt das Plugin den **nativen Shopware-Cookie-Consent-Manager**: Es ergänzt die Cookies in der Statistik-/Marketing-Gruppe und reagiert auf das Event `CookieConfiguration_Update`. Setzt du stattdessen einen externen CMP (Cookiebot, Usercentrics, Borlabs Cookie, Consentmanager o. Ä.) ein, werden die Shopware-eigenen Consent-Cookies nicht gesetzt – das Plugin würde den Container dann **nie laden**. Für diesen Fall gibt es eine dokumentierte Bridge, über die der externe CMP den Consent direkt an das Plugin meldet.

> ⚠️ **Opt-in:** Die Bridge ist standardmäßig **deaktiviert** und muss unter **Konfiguration → Consent → „Externe-CMP-Bridge“** eingeschaltet werden. Grund: Ist sie aktiv, kann **jedes** Script auf der Seite über das DOM-Event Consent-Signale senden. Aktiviere sie nur, wenn du tatsächlich einen externen CMP statt des Shopware-Banners verwendest. Sobald die Bridge Analytics-Einwilligung meldet, setzt das Storefront-JS zusätzlich ein First-Party-Cookie (`s4gtm-analytics`), damit auch der serverseitige Endpunkt die identifizierenden Nutzerdaten freigibt (der externe CMP setzt dieses Cookie selbst nicht).

Übergeben werden **Consent-Mode-Felder direkt** (`analytics_storage`, `ad_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage`). Werte dürfen `true`/`false` oder `'granted'`/`'denied'` sein. Sobald mindestens ein Tracking-Zweck gewährt wird, lädt der Container; identifizierende Nutzerdaten gehen erst nach `analytics_storage: granted` in den dataLayer, gehashte Kundendaten erst nach `ad_user_data: granted`.

**Variante A – DOM-Event** (empfohlen, funktioniert unabhängig von der Ladereihenfolge):

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

**Variante B – imperative API** (sobald das Storefront-Plugin initialisiert ist):

```js
window.s4gtm.setConsent({ analytics_storage: true, ad_storage: true });
```

Diesen Aufruf hängst du an den Consent-Callback deines CMP (z. B. Cookiebot `CookiebotOnAccept`, Usercentrics-Consent-Event). Bei jeder Consent-Änderung erneut aufrufen – das Plugin sendet dann das passende `gtag('consent','update',…)`-Signal. Beide Varianten sind additiv: Der native Shopware-Pfad bleibt parallel aktiv.

## Google Tag Assistant / Vorschau

Der **Debug-Modus** (Grundkonfiguration) ändert ausschließlich das Logging: jeder dataLayer-Push und jedes Consent-Update wird in die Browser-Konsole geschrieben (Präfix `[s4gtm]`). Das Lade- und Consent-Verhalten bleibt identisch zum Normalbetrieb – der Container lädt also weiterhin nur nach Einwilligung.

Zum Testen mit dem Google Tag Assistant öffnest du die Storefront aus der GTM-Vorschau und **stimmst im Cookie-Banner zu** (Statistik bzw. die jeweils benötigte Kategorie). Erst dann lädt der Container und der Assistant verbindet sich – so testest du exakt das Verhalten, das auch echte Besucher erleben.

## Rechtliche Hinweise / Betreiberpflichten

> Keine Rechtsberatung. Das Plugin liefert die technischen Voraussetzungen für einen DSGVO- und TTDSG-konformen Betrieb (Consent-Pflicht als Standard, kein Laden vor Einwilligung, Consent Mode v2, keine Übertragung von Klardaten). Die folgenden Pflichten kann nur der Shop-Betreiber erfüllen – bitte mit der eigenen Rechtsberatung abstimmen.

- **Datenschutzerklärung:** Google Tag Manager, Google Analytics bzw. Google Ads benennen, jeweils mit Zweck, Rechtsgrundlage (Einwilligung, Art. 6 Abs. 1 lit. a DSGVO) und Speicherdauer der gesetzten Cookies (die vom Plugin angelegten Consent-Cookies laufen nach **30 Tagen** ab).
- **Drittlandübermittlung (USA):** Auf die Übermittlung an Google (Google Ireland Ltd. / Google LLC), das EU-US Data Privacy Framework und das Risiko des Drittlandtransfers hinweisen.
- **Auftragsverarbeitung:** Mit Google die Datenverarbeitungsbedingungen (Google Ads/Analytics Data Processing Terms) abschließen und dokumentieren (Verzeichnis von Verarbeitungstätigkeiten, Art. 30 DSGVO).
- **Einwilligung als Voraussetzung:** Lass die **Consent-Quelle** auf „Shopware-Cookie-Banner“ oder „Externer CMP“ stehen (Standard: Shopware). Beide gelten als verwaltet – solange zusätzlich der „Advanced Consent Mode“ aus bleibt, lädt der Container erst nach Zustimmung und alle Zwecke starten auf `denied`. Nur die Quelle „Keine Cookies“ lädt den Container sofort und lässt das Tracking vollständig ungated laufen – das ist in der EU regelmäßig nicht zulässig und liegt allein in deiner Verantwortung (das Admin-UI verlangt dafür eine explizite Bestätigung).
- **Advanced Consent Mode (Opt-in):** Aktivierst du den „Advanced Consent Mode“, lädt der Container bereits **vor** der Einwilligung und Google erhält bei Ablehnung cookielose Modellierungs-Pings (ohne Cookies, ohne identifizierende Kundendaten). Ob das ohne vorherige Einwilligung zulässig ist, ist in der EU rechtlich umstritten und höchstrichterlich nicht geklärt. Setze die Option nur nach eigener Rechtsprüfung ein und weise die Verarbeitung ggf. in der Datenschutzerklärung aus.
- **Cookie-Banner:** Die vom Plugin ergänzten Einträge müssen im Consent-Banner verständlich beschrieben sein. Die mitgelieferten Texte sind neutral gehalten; passe sie bei Bedarf an deine Datenschutzerklärung an.
- **Enhanced Conversions:** Steht die Option nicht auf „Deaktiviert“, überträgt das Plugin gehashte Kundendaten (SHA-256: E-Mail, optional Telefon, Name, Straße; PLZ, Ort, Region und Land im Klartext, wie von Google gefordert). Das geschieht ausschließlich nach Einwilligung in den eigenen Banner-Eintrag „Erweitertes Conversion-Tracking“ und nur für eingeloggte Kunden. Diese Verarbeitung gehört in die Datenschutzerklärung und in das Verzeichnis der Verarbeitungstätigkeiten; die Datenverarbeitungsbedingungen mit Google sind Voraussetzung.
- **Suchbegriff:** Das `search`-Event überträgt den eingegebenen Suchbegriff (`search_term`) **standardmäßig** – aber erst nach erteilter Statistik-Einwilligung, wie jedes andere Event auch. Ein `search`-Event ohne Begriff hätte keinen Aussagewert. Suchbegriffe können allerdings personenbezogene Angaben enthalten (Besucher suchen z. B. nach eigenen Namen); wird das im Shop beobachtet, lässt sich das Feld über die Option **„Suchbegriff anonymisieren“** (Erweitert) weglassen.
- **Übertragene Identifikatoren (Datenminimierung):** Die stabile, wiedererkennbare Personenkennung (Kunden-UUID) wird **ausschließlich** dann übertragen, wenn **„User-ID-Tracking“** aktiv ist – dann als GA4-`user_id` für das User-ID-Feature. „Kunden-Tracking“ überträgt **keine** Personenkennung mehr, sondern nur Kundengruppe und Gast-Flag. Beides nur nach Analytics-Einwilligung. Aktivierst du User-ID-Tracking, benenne diese Verarbeitung im Verzeichnis der Verarbeitungstätigkeiten und in der Datenschutzerklärung.
- **Server-Side Tagging:** Lädt der Container über deine eigene Domain, ändert das nichts an der Einwilligungspflicht – die Daten fließen weiterhin an Google, nur mit deinem Server als Zwischenstation. Du wirst damit für die Verarbeitung im Server-Container selbst verantwortlich: Benenne den Server-Container in der Datenschutzerklärung und im Verzeichnis der Verarbeitungstätigkeiten und dokumentiere, welche Daten er an Google weitergibt.
- **Externe Consent-Manager:** Nutzt du einen externen CMP statt des Shopware-Banners, stelle über die dokumentierte Bridge (siehe „Externe Consent-Manager (CMP)“) sicher, dass der Container tatsächlich erst nach Einwilligung lädt.

## Hinweise zu den GA4-Werten

- Monetäre Felder (`value`, `price`, `tax`, `shipping`, `discount`) werden immer als **Zahlen** ausgegeben, nie als Strings, und kaufmännisch auf zwei Nachkommastellen gerundet.
- `value` der Warenkorb-, Checkout- und Kauf-Events ist die **Warensumme ohne Versandkosten, abzüglich der Rabatt-Positionen** (GA4-Empfehlung). Shopware lässt die Produktpreise bei Aktionen unverändert und bucht den Nachlass als eigene `promotion`-Position; diese taucht nicht in `items` auf, mindert aber den `value`. `shipping` wird beim `purchase` separat ausgewiesen.
- Im `view_item_list`/`search` spiegelt `index` die absolute Position inklusive Paginierungs-Offset. Bei clientseitig nachgetrackten Listen (Filter, Pagination) ist der Offset eine Näherung, siehe „Tracking-Verhalten im Browser“.
- **Item-Anreicherung:** `view_item`/`view_item_list` liefern `item_brand`, `item_variant` und den **Kategoriepfad**. GA4 erwartet ihn von grob nach fein: `item_category` ist die oberste Ebene unterhalb des Verkaufskanal-Einstiegs, `item_category2` bis `item_category5` die tieferen. Grundlage ist der Breadcrumb der `seoCategory` des Produkts. Auf Kategorieseiten sind die Kategorien der Listenprodukte nicht geladen; dort füllt das Plugin `item_category` ersatzweise mit dem gerade betrachteten Kategorienamen (eine Näherung, dafür ohne Zusatzabfrage). `view_cart`/`begin_checkout`/`purchase` liefern `item_variant` (aus dem Warenkorb-Payload) sowie `item_brand` (Herstellername wird je Seite in **einer** gebündelten Query aufgelöst).

## Entwicklung

Architektur:

- `Core/Content/GtmEvent` – DAL-Entität für Custom-Events inkl. Many-to-Many zu Verkaufskanälen
- `Service` – Konfiguration, Consent, dataLayer und die `Ecommerce`-Builder (reine Entity-zu-`DataLayerEvent`-Abbildung)
- `Controller` – `UserDataController` liefert über nicht cachebare Endpunkte (`/s4gtm/user-data`, `/s4gtm/pending-events`) die identifizierenden Nutzerdaten und die vorgemerkten Events, damit beide nicht im (cachebaren) Seiten-HTML landen
- `Subscriber` – hängen die Events an Storefront-Seiten (jeder Subscriber gekapselt mit Fehler-Logging); `PendingEventCookieSubscriber` hält zusätzlich das `s4gtm-pending`-Cookie synchron zur Session-Queue
- `Struct` – Value Objects (`DataLayerEvent`, `PluginConfig`, `GtmPageExtension`)
- `Resources/app/administration` – Vue-Modul (eine `sw-page` mit Tabs und `router-view`)
- `Resources/app/storefront` – dataLayer-, Consent- und Event-Handling im Browser

Der Event-Katalog liegt in `Service/GtmEventCatalog.php` und gespiegelt in `Resources/app/administration/.../constant/event-catalog.js`; ein PHPUnit-Parity-Test stellt sicher, dass beide Quellen nicht auseinanderlaufen.

### Sicherheit & Härtung

- **Custom-Event-Payload:** Die statischen Payload-Felder werden serverseitig validiert (`GtmEventValidationSubscriber`): restriktives Schlüssel-Pattern, max. 30 Felder, max. 3 Verschachtelungsebenen, nur skalare Werte, reservierte Schlüssel (`event`/`ecommerce`) verboten. Greift auch beim direkten Admin-API-Zugriff, nicht nur über die Oberfläche. Die Regeln liegen zentral im `PayloadValidator`.
- **Standard-Event-Overrides:** Abweichender GA4-Name und Zusatz-Payload der Standard-Events werden über die System-Config gespeichert (kein DAL-Pre-Write-Hook). Sie durchlaufen daher denselben `PayloadValidator` **beim Lesen** (`ConfigService`): ungültige GA4-Namen fallen auf den Originalnamen zurück, die Payload wird gefiltert (Schlüssel-Pattern, Größe, Tiefe, reservierte Schlüssel). Damit gilt die Defense-in-Depth für beide Eingabewege.
- **Analytics-Cookie (`s4gtm-analytics`):** Das serverseitige Gate des `/s4gtm/user-data`-Endpunkts liest dieses First-Party-Cookie. Es ist clientseitig setzbar – ein Nutzer kann damit nur sein **eigenes** Consent-Gate umgehen; zurückgegeben werden ausschließlich die Daten des eingeloggten Session-Kunden (kein IDOR).
- **ACL:** Die `s4gtm_event`-Entität ist über die Admin-API zugänglich. Standardmäßig sind nur Administratoren schreibberechtigt; die Konfigurations- und Event-Seiten sind hinter `system.system_config` gegated. Für granulare Rollen liefert das Plugin eine eigene Rechte-Karte **„GTM Custom-Events“** (`addPrivilegeMappingEntry` in `Resources/app/administration/src/acl/index.js`) mit den Rollen Lesen/Bearbeiten/Anlegen/Löschen aus. Eine Rolle, die die Event-Seite nutzen soll, benötigt zusätzlich `system.system_config`.

## Tests

**PHP** (Unit-Tests, ohne Datenbank lauffähig):

```bash
# aus dem shopware-root
vendor/bin/phpunit -c custom/plugins/Shop4GoogleTagManager/phpunit.xml.dist
```

**Statische Analyse** (PHPStan Level 5, muss fehlerfrei durchlaufen):

```bash
# einmalig im shopware-root: composer require --dev phpstan/phpstan
cd custom/plugins/Shop4GoogleTagManager
../../../vendor/bin/phpstan analyse
```

**Storefront** (jsdom-Integrationstests gegen das **gebaute** Bundle und gegen echtes Shop-Markup):

```bash
# vorher einmal bin/build-storefront.sh laufen lassen
cd custom/plugins/Shop4GoogleTagManager/tests/js
npm install
npm test
```

`npm test` prüft zuerst, ob das eingecheckte Bundle zu den Storefront-Quellen passt, und läuft erst
dann los. Nach jedem Build gehört deshalb ein `npm run stamp` dazu – sonst schlägt der Lauf mit
einem Hinweis fehl statt still den alten Code zu testen.

**Consent-Verhalten gegen einen laufenden Shop** (prüft die tatsächlich ausgelieferte Seite, u.a.
dass eine cachebare Seite niemals `granted` ausliefert):

```bash
tests/integration/consent-matrix.sh http://localhost:8006
```

**Außerhalb einer Shopware-Installation** zieht `composer install` im Plugin-Verzeichnis
`shopware/core`, `storefront` und `administration` als Dev-Abhängigkeiten; PHPUnit und PHPStan laufen
dann mit `vendor/bin/…` aus dem Plugin-eigenen `vendor/`. Getestet mit PHP 8.3 und 8.4.

Die Storefront-Tests prüfen unter anderem die Reihenfolge im `dataLayer` (Nutzerdaten vor den
Ecommerce-Events), das Consent-Gating, den Produktdaten-Fallback für Themes, die
Nachverfolgung ausgetauschter Produktlisten und den Abruf vorgemerkter Events. Die Fixtures
stammen aus einem echten Shop – `fixtures/listing.html` bildet gezielt ein Theme ab, das
`component_product_box` ohne `{{ parent() }}` ersetzt.

# Haftungsausschluss

Dieses Plugin wird als Open-Source-Software kostenlos zur Verfügung gestellt und erfolgt ohne jegliche ausdrückliche oder stillschweigende Gewährleistung.

Die Installation, Konfiguration und Nutzung des Plugins erfolgen ausschließlich auf eigene Verantwortung. Der Betreiber des Plugins übernimmt keine Gewähr für die Funktionsfähigkeit, Kompatibilität oder Eignung für einen bestimmten Einsatzzweck.

## Haftungsausschluss

Insbesondere wird keine Haftung übernommen für:

- Datenverlust oder Datenbeschädigungen
- Ausfälle oder Fehlfunktionen des Shops
- Umsatzausfälle oder entgangenen Gewinn
- Fehlerhafte Tracking-Daten oder fehlerhafte Übermittlung von Ereignissen
- Probleme durch fehlerhafte Konfigurationen des Google Tag Managers, Google Analytics oder anderer angebundener Dienste
- Schäden, die durch Updates von Shopware, Drittanbieter-Plugins oder externen Diensten entstehen

Der Nutzer ist selbst dafür verantwortlich,

- das Plugin vor dem produktiven Einsatz ausreichend zu testen,
- die Konfiguration des Google Tag Managers und aller verbundenen Dienste zu überprüfen,
- die Einhaltung aller geltenden gesetzlichen Vorgaben (insbesondere DSGVO und Datenschutzbestimmungen) sicherzustellen.

Es wird ausdrücklich empfohlen, das Plugin zunächst in einer Testumgebung zu installieren und vor dem produktiven Einsatz umfassend zu testen.

Eine Verpflichtung zur Wartung, Weiterentwicklung, Fehlerbehebung oder zur Bereitstellung von Support besteht nicht.

Soweit gesetzlich zulässig, ist jede Haftung für unmittelbare oder mittelbare Schäden ausgeschlossen.

## Datenschutz

Dieses Plugin stellt ausschließlich die technische Anbindung des Google Tag Managers bereit. Welche Tags, Skripte oder Drittanbieter-Dienste darüber eingebunden werden, liegt ausschließlich in der Verantwortung des jeweiligen Shopbetreibers.

Der Entwickler dieses Plugins übernimmt keinerlei Verantwortung für die datenschutzkonforme Verwendung des Google Tag Managers oder der darüber eingebundenen Dienste. Der Shopbetreiber ist selbst dafür verantwortlich, alle geltenden gesetzlichen Vorschriften, insbesondere die DSGVO, das TTDSG sowie gegebenenfalls weitere nationale Datenschutzbestimmungen einzuhalten.

## Zustimmung

Mit der Installation oder Nutzung dieses Plugins erklärt sich der Nutzer mit den vorstehenden Bedingungen einverstanden.
