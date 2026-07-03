# Shop4GoogleTagManager

Google-Tag-Manager-Integration für Shopware 6.7 mit vollständigem GA4-Enhanced-Ecommerce-dataLayer und Google Consent Mode v2.

> ℹ️ Newsletter-Tracking erfolgt über das `newsletter_signup`-Event nach Double-Opt-in. Kontaktformular-Tracking (`generate_lead`) und das Tracking sonstiger Formulare (`form_submit`) sind clientseitig umgesetzt und übertragen dabei **keine Formularfeld-Inhalte** (siehe „Formular-Tracking").

## Anforderungen

- Shopware 6.7
- PHP 8.3 oder höher

## Funktionen

- Einbindung des GTM-Containers im Head und als noscript-Fallback im Body
- Serverseitig aufgebautes dataLayer für Shop-, Sprach-, Währungs- und Kundenkontext
- GA4 Enhanced Ecommerce: `view_item`, `view_item_list`, `search`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`
- Kunden-Events: `login`, `logout`, `sign_up`, `newsletter_signup`
- Formular-Events: `generate_lead` (Kontaktformular) und `form_submit` (CMS-/Plugin-Formulare) – clientseitig, **ohne** Übertragung von Formularfeld-Inhalten
- Eigene **Custom-Events** je Seitenkontext, frei konfigurierbar und einzelnen Verkaufskanälen zuweisbar
- Google Consent Mode v2 inklusive `ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`
- Pro Verkaufskanal konfigurierbar mit Shopware-Standard-Vererbung 
- Debug-Modus mit Konsolen-Logging

## Installation

```bash
# plugin in custom/plugins/Shop4GoogleTagManager ablegen
bin/console plugin:refresh
bin/console plugin:install --activate Shop4GoogleTagManager
bin/console cache:clear

# administration und storefront neu bauen
bin/build-administration.sh
bin/build-storefront.sh
```

## Konfiguration

Zu finden in der Administration unter **Einstellungen → Erweiterungen → Google Tag Manager**. Die Oberfläche hat zwei Tabs: **Konfiguration** und **Events**.

### Tab „Konfiguration"

Verkaufskanal-Auswahl und Speichern liegen oben rechts in der Kopfzeile. Ohne Auswahl wird die globale Konfiguration bearbeitet; bei gewähltem Kanal zeigt jedes Feld per Vererbungs-Symbol an, ob es den globalen Wert erbt oder kanalspezifisch überschrieben ist (Shopware-Standard).

| Bereich | Feld | Bedeutung |
| --- | --- | --- |
| Grundkonfiguration | Plugin aktiv | Schaltet die gesamte Einbindung an oder aus |
| Grundkonfiguration | GTM Container-ID | Container im Format `GTM-XXXXXXX` |
| Grundkonfiguration | Debug-Modus | Konsolen-Logging (Präfix `[s4gtm]`); Lade-/Consent-Verhalten identisch zum Normalbetrieb |
| Consent | Google Consent Mode v2 | Container mit `denied`-Defaults laden, nach Consent anheben |
| Consent | Tracking erst nach Einwilligung | Hard-Block: Container lädt erst nach Zustimmung |
| Consent | Advanced Consent Mode | **Opt-in, rechtlich umstritten.** Lädt den Container bereits vor der Einwilligung mit `denied`-Defaults, sodass Google bei Ablehnung cookielose Modellierungs-Pings erhält. Nur wirksam, wenn „Consent Mode v2" an und „Tracking erst nach Einwilligung" aus ist. Eigene PII bleibt bis zur Analytics-Einwilligung blockiert. **Standard: aus** |
| Erweitert | Data Layer | dataLayer-Ausgabe aktivieren |
| Erweitert | Enhanced Ecommerce | Produkt-, Listen- und Such-Events |
| Erweitert | Checkout-Tracking | Warenkorb-, Checkout- und Kauf-Events |
| Erweitert | Remarketing | Werbebezogene Signale (`ad_storage`/`ad_personalization`/`ad_user_data`) freischalten und Marketing-/Enhanced-Cookies im Banner einblenden; aus = nie angehoben |
| Erweitert | User-ID-Tracking | pseudonyme `user_id` (Kunden-UUID) übertragen (erst nach Analytics-Einwilligung) – **einzige** Stelle, an der die stabile Personenkennung ausgegeben wird |
| Erweitert | Kunden-Tracking | nur **nicht** direkt identifizierende Merkmale (Kundengruppe, Gast-Flag) ins dataLayer (erst nach Analytics-Einwilligung) |
| Erweitert | Suchbegriff anonymisieren | Lässt das Feld `search_term` im `search`-Event weg (Datenminimierung; **Standard: an**) |
| Formulare | Newsletter | Newsletter-`sign_up`-Event nach Double-Opt-in |
| Formulare | Kontaktformular | `generate_lead` beim Absenden des Kontaktformulars (ohne Feldinhalte) |
| Formulare | Individuelle Formulare | `form_submit` für CMS-/Plugin-Formulare (ohne Feldinhalte; siehe „Formular-Tracking") |

### Tab „Events"

- **Standard-Events** lassen sich je Event ein-/ausschalten und mit abweichendem GA4-Namen sowie zusätzlichen statischen dataLayer-Feldern (Payload) versehen.
- **Custom-Events** werden frei angelegt: technischer Name, Seitenkontext (`product`, `listing`, `search`, `cart`, `checkout`, `purchase`, `global`), GA4-Event-Name, Priorität und statische Payload-Felder.
- Jedes Custom-Event lässt sich einem oder mehreren **Verkaufskanälen** zuweisen (Mehrfachauswahl). Ohne Zuweisung gilt es für alle Kanäle. Über das Dropdown lässt sich die Liste nach Verkaufskanal filtern.

## GTM-Container-Vorlage importieren

Unter [`docs/gtm-container-import.json`](docs/gtm-container-import.json) liegt eine generische, importierbare Container-Vorlage, die zu den Standard-Events des Plugins passt.

**Import:** GTM → Admin → *Container importieren* → Datei wählen → Workspace wählen → *Zusammenführen* (empfohlen).

Sie enthält:

- einen **Google-Tag (GA4-Konfiguration)** auf „Initialisierung – Alle Seiten",
- je ein **GA4-Ereignis-Tag** für `view_item`, `view_item_list`, `search`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`, `login`, `logout`, `sign_up`, `newsletter_signup`,
- passende **Custom-Event-Trigger**, die den `event`-Schlüssel im dataLayer matchen,
- einen **Google-Ads-Block**: `Conversion Linker` (alle Seiten), die Conversion-Tags `Google Ads - Conversion (purchase)`, `Google Ads - add_to_cart` und `Google Ads - begin_checkout` (jeweils auf dem passenden Event-Trigger) sowie `Google Ads - Remarketing` (alle Seiten),
- die Variablen `GA4 Measurement ID`, `Google Ads Conversion ID`, `Google Ads Purchase Label`, `Google Ads Add To Cart Label`, `Google Ads Begin Checkout Label` (Konstanten) sowie die DataLayer-Variablen `DLV - search_term`, `DLV - method`, `DLV - value`, `DLV - currency`, `DLV - transaction_id`.

Die Ecommerce-Tags lesen das `ecommerce`-Objekt automatisch aus dem dataLayer (entspricht exakt dem Plugin-Output). **Nach dem Import nur die Variable `GA4 Measurement ID` auf deine `G-XXXXXXXX`-ID setzen** – fertig. Alle GA4-Tags sind in den Consent-Einstellungen auf **„zusätzliche Einwilligung erforderlich: `analytics_storage`"** gesetzt. Sie feuern dadurch ausschließlich nach erteilter Statistik-Einwilligung – auch dann, wenn der Container bereits über die Zustimmung zu einem anderen Zweck (z. B. Google Ads) geladen wurde.

### Google Ads aktivieren

Der Google-Ads-Block ist **vorbereitet, aber inaktiv** (Platzhalter-IDs) und muss nach dem Import befüllt werden:

1. Variable **`Google Ads Conversion ID`** auf deine **numerische** Conversion-ID setzen (die Ziffern hinter `AW-`, z. B. `123456789`).
2. Pro Conversion-Aktion (Google Ads → Tools → Conversions) das jeweilige **Conversion-Label** in die passende Variable eintragen: **`Google Ads Purchase Label`** (Kauf), **`Google Ads Add To Cart Label`** (`add_to_cart`), **`Google Ads Begin Checkout Label`** (`begin_checkout`). Jede dieser Conversions muss in Google Ads als eigene Aktion angelegt sein.
3. Conversion-Tags, die du **nicht** als Conversion zählst (z. B. `add_to_cart`/`begin_checkout` als reine Micro-Conversions), kannst du löschen/pausieren – oder als Audience-Signal behalten.
4. Tag **`Google Ads - Remarketing`** nur behalten, wenn du Remarketing nutzt (sonst löschen/pausieren).

Die Ads-Tags lesen Wert/Währung/Bestellnummer aus dem `ecommerce`-Objekt (`DLV - value`/`currency`/`transaction_id`) und sind auf die Consent-Bedingungen **`ad_storage`** (Conversion/Linker) bzw. **`ad_storage` + `ad_personalization`** (Remarketing) gesetzt – sie feuern also erst nach erteilter Marketing-Einwilligung. Sie erfordern, dass im Plugin **„Remarketing" aktiv** ist (sonst werden diese Signale nie angehoben).

> **Enhanced Conversions:** Aktivierst du im Plugin die Option *Enhanced Conversions*, liefert das Plugin nach `ad_user_data`-Einwilligung das gehashte `enhancedConversion`-Objekt in den dataLayer. Um es zu nutzen, im `Google Ads - Conversion`-Tag *Enhanced Conversions* einschalten und als „benutzerdefinierte Variable für Nutzerdaten" eine DataLayer-Variable auf `enhancedConversion` mappen. Das ist account-/setup-spezifisch und daher bewusst **nicht** vorkonfiguriert.

## Consent Mode – Verhalten

Es gibt genau **einen** GTM-Container für alle Zwecke. Sobald einem Tracking-Zweck zugestimmt wird (z. B. nur Google Ads), lädt der Container. Welche Tags dann tatsächlich feuern, steuern zwei Mechanismen zusammen:

1. **Consent-Mode-Signale** des Plugins: bei „nur Ads" stehen `ad_storage`/`ad_personalization` auf `granted`, `analytics_storage` bleibt `denied`.
2. **Consent-Bedingungen der Tags im Container** (Consent Settings): Die mitgelieferte Vorlage setzt für alle GA4-Tags `analytics_storage` als zusätzliche Voraussetzung voraus.

Dadurch werden GA4-Tags ohne Statistik-Einwilligung gar nicht erst ausgeführt – es gehen also auch keine cookielosen Pings an Google, solange der Statistik-Zweck nicht bestätigt wurde. Verwendest du einen eigenen Container, setze die Consent-Bedingungen deiner Tags entsprechend (`analytics_storage` für GA4, `ad_storage`/`ad_user_data` für Google Ads).

Identifizierende Nutzerdaten (pseudonyme `user_id`, Kundengruppe) werden erst **nach erteilter Analytics-Einwilligung** in den `dataLayer` geschrieben und damit an den Container/Google übergeben. Diese Werte werden **nicht in den Seitenquelltext eingebettet** (der HTML kann über Reverse-Proxy-/HTTP-Caches kundenübergreifend ausgeliefert werden). Stattdessen lädt das Storefront-JS sie erst nach der Einwilligung über einen eigenen, **niemals cachebaren Endpunkt** (`/s4gtm/user-data`, `Cache-Control: no-store, private`) und pusht sie dann in den `dataLayer`. Der grobe Login-Status (Gast/eingeloggt) im Basis-`dataLayer` ist immer verfügbar und enthält keine identifizierenden Merkmale.

Aus demselben Grund landen auch die **vorgemerkten Events** (`login`/`sign_up`/`newsletter_signup`, die wegen des Redirects erst auf der nächsten Seite ausgespielt werden) **nicht** im cachebaren HTML. Der Seitenaufbau setzt lediglich ein boolesches Hinweis-Flag; die eigentlichen Events holt das Storefront-JS nach dem Container-Load über den ebenfalls nicht cachebaren Endpunkt `/s4gtm/pending-events` (`Cache-Control: no-store, private`), der die Session-Queue dann serverseitig leert. So kann selbst ein spurious Event nicht kundenübergreifend ausgeliefert werden.

**Verhältnis der Consent-Schalter:** Standardmäßig (beide Schalter an) lädt der Container in diesem Plugin **nie** vor der Einwilligung. Sobald **einer** der beiden Schalter aktiv ist (Consent gilt damit als „verwaltet"), bleibt `gtm.js` server- wie clientseitig blockiert und wird erst nach Zustimmung nachgeladen – **es sei denn**, der **Advanced Consent Mode** ist bewusst aktiviert (siehe unten).

- **Consent Mode v2 an** und/oder **„Tracking erst nach Einwilligung" an** (Standard: beide an): Das Plugin sendet `gtag('consent', …)`-Signale – Defaults aller trackbaren Zwecke auf `denied`, Anhebung pro Zweck per Update. Der Container lädt erst nach Zustimmung. Das entspricht Googles **Basic-Modus** (nichts geht vor Einwilligung an Google). **Folge:** Wer nur einem Zweck zustimmt (z. B. Marketing), löst dank der zweckgenauen Signale keine Analytics-Tags aus – auch dann nicht, wenn nur der Hard-Block (ohne Consent Mode v2) aktiv ist.
- **Advanced Consent Mode an** (Opt-in, nur wirksam bei „Consent Mode v2" an + „Tracking erst nach Einwilligung" aus): Der Container lädt **sofort** beim Seitenaufruf mit `denied`-Defaults. Lehnt der Besucher ab, sendet Google nur **cookielose Pings** (Einwilligungsstatus, Zeitstempel, URL, User-Agent, Conversion-Signale) für die Conversion- und Verhaltensmodellierung – es werden **keine Cookies** gesetzt. Das entspricht Googles **Advanced-Modus**. Identifizierende Nutzerdaten (`user_id`, Kundengruppe) bleiben weiterhin bis zur Analytics-Einwilligung blockiert. **Rechtlich umstritten:** Ob cookielose Pings ohne Einwilligung zulässig sind, ist in der EU nicht abschließend geklärt – Einsatz auf eigene Verantwortung nach Rechtsprüfung. Der Hard-Block hat immer Vorrang: Ist „Tracking erst nach Einwilligung" an, wird der Advanced Mode ignoriert.
- **Beide Schalter aus:** Consent gilt als **nicht verwaltet**. Es werden keine Consent-Signale gesendet und der Container lädt **sofort** beim Seitenaufruf – Tracking läuft damit vollständig **ungated** (in der EU regelmäßig nicht zulässig, allein Betreiberverantwortung; das Admin-UI verlangt vor dem Speichern eine explizite Bestätigung).

## Formular-Tracking

Kontaktformular- und Custom-Form-Tracking laufen **clientseitig** beim Absenden des jeweiligen Formulars und werden – wie die übrigen Seiten-Events – erst **nach** dem Laden des Containers (also nach Einwilligung) in den `dataLayer` gepusht.

- **Kontaktformular** (`trackContactForm`): Beim Absenden des Shopware-Kontaktformulars (`/form/contact`) wird `generate_lead` ausgelöst.
- **Individuelle Formulare** (`trackCustomForms`): Erfasst werden CMS-/Plugin-Formulare, die auf einen `/form/*`-Endpunkt posten (Kontakt und Newsletter ausgenommen, da separat abgedeckt), sowie jedes `<form>`, das explizit das Attribut `data-s4gtm-form` trägt. Ausgelöst wird `form_submit`.

```html
<!-- beliebiges formular gezielt fuer das tracking markieren -->
<form action="/eigene-route" method="post" data-s4gtm-form="kontakt-footer">
    …
</form>
```

> 🔒 **Keine personenbezogenen Daten:** Es werden **ausschließlich statische Markup-Attribute** des Formulars gelesen (`data-s4gtm-form`, `id`, `name`, Pfadsegment der `action`) und als `form_id`/`form_destination` ausgegeben. **Formularfeld-Inhalte (Name, E-Mail, Nachricht) gelangen nie in den `dataLayer`.** Da das Event beim Absenden feuert, kann es auch dann gezählt werden, wenn die anschließende serverseitige Validierung das Formular ablehnt.

## Tracking-Verhalten im Browser

- **`purchase`-Deduplizierung:** Das `purchase`-Event wird je Bestellung nur **einmal** ausgelöst. Die Deduplizierung erfolgt client-seitig über die `transaction_id` (Bestellnummer), die das Storefront-JS in `localStorage` merkt – bewusst **ohne** schreibenden Zugriff auf die Bestellung im Seitenaufbau (das vermeidet `order.written`-Events, Indexer-Last und Race-Conditions). Löscht ein Besucher seinen `localStorage` und ruft die Bestellbestätigung erneut auf, kann das Event erneut feuern; GA4 dedupliziert `purchase` zusätzlich über die `transaction_id`.
- **Optimistisches `add_to_cart`:** Das client-seitige `add_to_cart`-Event wird bereits beim Absenden des Warenkorb-Formulars erfasst – also **bevor** der Server das Hinzufügen bestätigt hat. Schlägt der serverseitige Vorgang fehl (z. B. Bestand erschöpft), kann ein `add_to_cart` getrackt worden sein, das nicht zu einem Warenkorb-Eintrag führte. Die server-seitigen Events (`view_cart` etc.) überschneiden sich damit nicht. **Staffelpreise:** `price`/`value` des client-seitigen `add_to_cart` basieren auf dem im DOM hinterlegten Einzelpreis (Menge 1). Bei mengenabhängigen Staffelpreisen kann der getrackte Betrag daher vom tatsächlichen Warenkorbwert abweichen; die maßgeblichen server-seitigen Events (`view_cart`, `purchase`) verwenden die echten Preise.
- **Reihenfolge der Nutzerdaten:** Die identifizierenden Nutzerdaten werden asynchron über `/s4gtm/user-data` nachgeladen und können daher **nach** den ersten Ecommerce-Events im `dataLayer` ankommen. Tags, die `user_id` schon beim ersten Event erwarten, sollten als GA4-User-Property gelesen werden (greift auf folgende Hits).

## Content-Security-Policy (CSP)

Die für Consent Mode v2 nötigen Inline-Scripts (Consent-Default, Basis-`dataLayer`) müssen **synchron vor** `gtm.js` laufen und sind daher bewusst inline. Setzt dein Shop eine strikte CSP ohne `unsafe-inline`, übernimmt das Plugin automatisch eine **Nonce**, sofern sie im Request-Attribut `csp_nonce` bereitsteht (z. B. über das NelmioSecurityBundle oder einen eigenen Kernel-Listener). Die Nonce wird sowohl auf die Inline-Scripts als auch auf das dynamisch nachgeladene Container-Script gesetzt. Ohne ein solches Attribut bleibt das Verhalten unverändert; pflege dann bei Bedarf Script-Hashes in deiner CSP.

## Sicherheit des GTM-Containers (Supply Chain)

Das Plugin lädt `gtm.js` dynamisch von Google – eine Subresource-Integrity-Prüfung (SRI) ist bei GTM technisch nicht möglich, da der Container-Inhalt veränderlich ist. Die eigentliche Angriffsfläche liegt damit **im Container selbst**: Wer Schreibrechte auf den GTM-Workspace hat, kann beliebiges JavaScript in deine Storefront einschleusen. Empfohlene Härtung auf Google-Seite:

- **Zugriff minimieren:** Nur wenige Personen mit Veröffentlichungsrecht; 2FA auf allen Google-Konten erzwingen.
- **Benutzerdefiniertes HTML einschränken:** In den Container-Einstellungen „Benutzerdefinierte HTML-Tags zulassen" nur aktiviert lassen, wenn wirklich benötigt; sonst deaktivieren.
- **Berechtigungen prüfen:** Tag-Berechtigungen (z. B. `inject_script`) regelmäßig im Workspace reviewen; nur vertrauenswürdige Tag-Vorlagen aus der Community-Galerie verwenden.
- **Versionierung nutzen:** Änderungen über Versionen/Umgebungen ausrollen und vor der Live-Schaltung in der Vorschau prüfen; Versionen erlauben ein schnelles Rollback.
- **CSP:** Eine restriktive CSP (siehe oben) begrenzt zumindest, welche Ziele eingeschleuste Skripte erreichen können.

## Externe Consent-Manager (CMP)

Standardmäßig nutzt das Plugin den **nativen Shopware-Cookie-Consent-Manager**: Es ergänzt die Cookies in der Statistik-/Marketing-Gruppe und reagiert auf das Event `CookieConfiguration_Update`. Setzt du stattdessen einen externen CMP (Cookiebot, Usercentrics, Borlabs Cookie, Consentmanager o. Ä.) ein, werden die Shopware-eigenen Consent-Cookies nicht gesetzt – das Plugin würde den Container dann **nie laden**. Für diesen Fall gibt es eine dokumentierte Bridge, über die der externe CMP den Consent direkt an das Plugin meldet.

> ⚠️ **Opt-in:** Die Bridge ist standardmäßig **deaktiviert** und muss unter **Konfiguration → Consent → „Externe-CMP-Bridge"** eingeschaltet werden. Grund: Ist sie aktiv, kann **jedes** Script auf der Seite über das DOM-Event Consent-Signale senden. Aktiviere sie nur, wenn du tatsächlich einen externen CMP statt des Shopware-Banners verwendest. Sobald die Bridge Analytics-Einwilligung meldet, setzt das Storefront-JS zusätzlich ein First-Party-Cookie (`s4gtm-analytics`), damit auch der serverseitige Endpunkt die identifizierenden Nutzerdaten freigibt (der externe CMP setzt dieses Cookie selbst nicht).

Übergeben werden **Consent-Mode-Felder direkt** (`analytics_storage`, `ad_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage`). Werte dürfen `true`/`false` oder `'granted'`/`'denied'` sein. Sobald mindestens ein Tracking-Zweck gewährt wird, lädt der Container (im Hard-Block-Modus); identifizierende Nutzerdaten gehen erst nach `analytics_storage: granted` in den dataLayer.

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
- **Einwilligung als Voraussetzung:** Lass mindestens **einen** der beiden Consent-Schalter („Tracking erst nach Einwilligung" oder „Consent Mode v2") aktiviert (Standard: beide an). Solange einer aktiv ist – **und** der „Advanced Consent Mode" aus bleibt – lädt der Container erst nach Zustimmung und alle Zwecke starten auf `denied`. Erst wenn **beide** deaktiviert sind, lädt der Container sofort und das Tracking läuft vollständig ungated – das ist in der EU regelmäßig nicht zulässig und liegt allein in deiner Verantwortung (das Admin-UI verlangt dafür eine explizite Bestätigung).
- **Advanced Consent Mode (Opt-in):** Aktivierst du den „Advanced Consent Mode", lädt der Container bereits **vor** der Einwilligung und Google erhält bei Ablehnung cookielose Modellierungs-Pings (ohne Cookies, ohne identifizierende Kundendaten). Ob das ohne vorherige Einwilligung zulässig ist, ist in der EU rechtlich umstritten und höchstrichterlich nicht geklärt. Setze die Option nur nach eigener Rechtsprüfung ein und weise die Verarbeitung ggf. in der Datenschutzerklärung aus.
- **Cookie-Banner:** Die vom Plugin ergänzten Einträge müssen im Consent-Banner verständlich beschrieben sein. Die mitgelieferten Texte sind neutral gehalten; passe sie bei Bedarf an deine Datenschutzerklärung an.
- **Enhanced Conversions:** Der Cookie/das Consent-Signal `ad_user_data` ist vorbereitet, das Plugin überträgt jedoch selbst **keine** Nutzerdaten (E-Mail, Telefon). Aktivierst du Enhanced Conversions im GTM-Container selbst, müssen diese Daten gehasht und ausschließlich nach passender Einwilligung übergeben werden.
- **Datenminimierung (Suchbegriff):** Das `search`-Event überträgt den eingegebenen Suchbegriff (`search_term`) **standardmäßig nicht** mehr, da Suchbegriffe personenbezogene Daten enthalten können (Nutzer suchen z. B. nach eigenen Namen). Über die Option **„Suchbegriff anonymisieren"** (Erweitert) lässt sich die Übertragung bei Bedarf wieder aktivieren (deaktivieren der Option).
- **Übertragene Identifikatoren (Datenminimierung):** Die stabile, wiedererkennbare Personenkennung (Kunden-UUID) wird **ausschließlich** dann übertragen, wenn **„User-ID-Tracking"** aktiv ist – dann als GA4-`user_id` für das User-ID-Feature. „Kunden-Tracking" überträgt **keine** Personenkennung mehr, sondern nur Kundengruppe und Gast-Flag. Beides nur nach Analytics-Einwilligung. Aktivierst du User-ID-Tracking, benenne diese Verarbeitung im Verzeichnis der Verarbeitungstätigkeiten und in der Datenschutzerklärung.
- **Externe Consent-Manager:** Nutzt du einen externen CMP statt des Shopware-Banners, stelle über die dokumentierte Bridge (siehe „Externe Consent-Manager (CMP)") sicher, dass der Container tatsächlich erst nach Einwilligung lädt.

## Hinweise zu den GA4-Werten

- Monetäre Felder (`value`, `price`, `tax`, `shipping`, `discount`) werden immer als **Zahlen** ausgegeben, nie als Strings, und kaufmännisch auf zwei Nachkommastellen gerundet.
- `value` der Warenkorb-/Checkout-Events ist die **Warensumme ohne Versandkosten** (GA4-Empfehlung); `shipping` wird beim `purchase` separat ausgewiesen.
- Im `view_item_list`/`search` spiegelt `index` die absolute Position inklusive Paginierungs-Offset.
- **Item-Anreicherung:** `view_item`/`view_item_list` liefern `item_brand`, `item_variant` und `item_category`. Auf Kategorieseiten ist `item_category` der aktuell betrachtete Kategoriename (Näherung, kostenlos aus der Navigationsseite); auf der Produktdetailseite die produkteigene `seoCategory`. `view_cart`/`begin_checkout`/`purchase` liefern `item_variant` (aus dem Warenkorb-Payload) sowie `item_brand` (Herstellername wird je Seite in **einer** gebündelten Query aufgelöst).

## Entwicklung

Architektur (saubere Schichtung):

- `Core/Content/GtmEvent` – DAL-Entität für Custom-Events inkl. Many-to-Many zu Verkaufskanälen
- `Service` – Konfiguration, Consent, dataLayer und die `Ecommerce`-Builder (reine Entity-zu-`DataLayerEvent`-Abbildung)
- `Controller` – `UserDataController` liefert über nicht cachebare Endpunkte (`/s4gtm/user-data`, `/s4gtm/pending-events`) die identifizierenden Nutzerdaten und die vorgemerkten Events, damit beide nicht im (cachebaren) Seiten-HTML landen
- `Subscriber` – hängen die Events an Storefront-Seiten (jeder Subscriber gekapselt mit Fehler-Logging)
- `Struct` – Value Objects (`DataLayerEvent`, `PluginConfig`, `GtmPageExtension`)
- `Resources/app/administration` – Vue-Modul (eine `sw-page` mit Tabs und `router-view`)
- `Resources/app/storefront` – dataLayer-, Consent- und Event-Handling im Browser

Der Event-Katalog liegt in `Service/GtmEventCatalog.php` und gespiegelt in `Resources/app/administration/.../constant/event-catalog.js`; ein PHPUnit-Parity-Test stellt sicher, dass beide Quellen nicht auseinanderlaufen.

### Sicherheit & Härtung

- **Custom-Event-Payload:** Die statischen Payload-Felder werden serverseitig validiert (`GtmEventValidationSubscriber`): restriktives Schlüssel-Pattern, max. 30 Felder, max. 3 Verschachtelungsebenen, nur skalare Werte, reservierte Schlüssel (`event`/`ecommerce`) verboten. Greift auch beim direkten Admin-API-Zugriff, nicht nur über die Oberfläche. Die Regeln liegen zentral im `PayloadValidator`.
- **Standard-Event-Overrides:** Abweichender GA4-Name und Zusatz-Payload der Standard-Events werden über die System-Config gespeichert (kein DAL-Pre-Write-Hook). Sie durchlaufen daher denselben `PayloadValidator` **beim Lesen** (`ConfigService`): ungültige GA4-Namen fallen auf den Originalnamen zurück, die Payload wird gefiltert (Schlüssel-Pattern, Größe, Tiefe, reservierte Schlüssel). Damit gilt die Defense-in-Depth für beide Eingabewege.
- **Analytics-Cookie (`s4gtm-analytics`):** Das serverseitige Gate des `/s4gtm/user-data`-Endpunkts liest dieses First-Party-Cookie. Es ist clientseitig setzbar – ein Nutzer kann damit nur sein **eigenes** Consent-Gate umgehen; zurückgegeben werden ausschließlich die Daten des eingeloggten Session-Kunden (kein IDOR).
- **ACL:** Die `s4gtm_event`-Entität ist über die Admin-API zugänglich. Standardmäßig sind nur Administratoren schreibberechtigt; die Konfigurations- und Event-Seiten sind hinter `system.system_config` gegated. Für granulare Rollen liefert das Plugin eine eigene Rechte-Karte **„GTM Custom-Events"** (`addPrivilegeMappingEntry` in `Resources/app/administration/src/acl/index.js`) mit den Rollen Lesen/Bearbeiten/Anlegen/Löschen aus. Eine Rolle, die die Event-Seite nutzen soll, benötigt zusätzlich `system.system_config`.

## Tests

```bash
# aus dem shopware-root
vendor/bin/phpunit -c custom/plugins/Shop4GoogleTagManager/phpunit.xml.dist
```

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
