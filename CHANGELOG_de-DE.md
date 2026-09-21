# 1.1.0

## Behobene Tracking-Ausfälle

- `user_id`, Kundengruppe und die gehashten Enhanced-Conversion-Daten wurden asynchron nachgeladen und landeten dadurch immer **nach** den Seiten-Events im `dataLayer`. GA4- und Ads-Tags lasen beim Feuern leere Variablen – Enhanced Conversions griffen faktisch nie. Die Seiten-Events warten jetzt auf diese Daten (spätestens 1,5 Sekunden, danach feuern sie auch ohne).
- `Eager-Load im Checkout` hat den `consent default` auf `denied` gesetzt **und** `wait_for_update` auf `0` – GTM feuerte dadurch sofort cookielos, obwohl die Einwilligung serverseitig bekannt war. Auf `no-store`-Seiten (Warenkorb, Bestellbestätigung, Bestellabschluss) stehen die bereits erteilten Zwecke jetzt direkt als `granted` im Default, `wait_for_update` behält seinen konfigurierten Wert. Auf cachebaren Seiten bleibt alles unverändert `denied`, damit keine fremde Einwilligung aus dem HTTP-Cache ausgeliefert wird.
- `add_to_cart` aus Kategorieseiten, Sliders und Suchergebnissen fiel komplett aus, sobald ein Theme den Block `component_product_box` ohne `{{ parent() }}` ersetzt. Als Rückfallebene wird jetzt Shopwares eigenes Attribut `data-product-information` an der Produktbox gelesen.
- `view_item_list` feuerte nur beim vollständigen Seitenaufbau. Filter, Sortierung und Seitenwechsel tauschen die Liste per AJAX aus – diese Impressionen werden jetzt clientseitig nachgetrackt.
- Das Hinweis-Flag auf vorgemerkte Events (`login`, `logout`, `sign_up`, `newsletter_signup`) steckte nur im HTML und konnte nach einem Logout aus dem HTTP-Cache stammen. Es liegt jetzt zusätzlich im Cookie `s4gtm-pending`.

## Consent

- `ad_user_data` hängt jetzt am Marketing-Eintrag des Cookie-Banners statt am separaten Haken „Erweitertes Conversion-Tracking“. Consent Mode v2 verlangt das Signal bereits für die normale Google-Ads-Conversion-Messung; bisher verlor man Conversions von Besuchern, die der Marketing-Nutzung ausdrücklich zugestimmt hatten.
- Der Banner-Eintrag „Erweitertes Conversion-Tracking“ erscheint nur noch, wenn Enhanced Conversions tatsächlich aktiv sind. Bisher wurde er schon bei aktivem Remarketing eingeblendet und beschrieb damit eine Datenübermittlung, die gar nicht stattfand.
- Das Storefront-JS setzt die Gate-Cookies nicht mehr selbst nach, wenn das Shopware-Banner sie ohnehin verwaltet. Bei einer Teil-Einwilligung hätte das zu viel freigeschaltet.

## Geänderte Standardwerte

- „Suchbegriff anonymisieren“ ist jetzt **aus**. Ein `search`-Event ohne `search_term` hat keinen Aussagewert, und der Begriff wird ohnehin erst nach erteilter Statistik-Einwilligung übertragen. Bestehende Installationen behalten ihre gespeicherte Einstellung.

## Datenqualität

- `value` der Warenkorb-, Checkout- und Kauf-Events zieht jetzt die Rabatt-Positionen ab. Shopware lässt die Produktpreise bei Aktionen unverändert, sodass GA4 bisher bei jedem eingelösten Gutschein zu hohen Umsatz meldete.
- **Kategoriepfad statt einzelner Kategorie:** `item_category` ist jetzt die oberste Ebene unterhalb des Verkaufskanal-Einstiegs, `item_category2` bis `item_category5` die tieferen – so erwartet es GA4. Bei Produkten mit nur einer Kategorie ändert sich nichts.

## Neue Events

- **`select_item`** beim Klick auf eine Produktkachel – inklusive Listen-ID, Listenname und Position.
- **Listen-Zuordnung über den Seitenwechsel:** Die angeklickte Liste folgt dem Produkt bis in `view_item` und `add_to_cart` (`sessionStorage`, 30 Minuten, nur Produkt- und Listenkontext). Ohne diese Brücke bleiben die GA4-Listenberichte leer.
- **`view_cart` im Offcanvas-Warenkorb** – für viele Besucher die einzige Warenkorb-Ansicht. Gefeuert wird beim Öffnen, nicht bei jedem Neurendern. **Die Zahl der `view_cart`-Events steigt dadurch spürbar**; abschaltbar im Tab „Events“.
- **Mengenänderung im Warenkorb** sendet die Differenz als `add_to_cart` beziehungsweise `remove_from_cart`.
- **`add_to_wishlist`** beim Merken eines Produkts.
- **`view_promotion`, `select_promotion` und `share`** über die Opt-in-Attribute `data-s4gtm-promotion` und `data-s4gtm-share`. Shopware hat dafür keine Entsprechung im Standard – ohne die Attribute im Markup passiert nichts.
- Die GTM-Container-Vorlage bringt Tags und Trigger für alle neuen Events mit, dazu erstmals auch für `generate_lead` und `form_submit`.

## Server-Side Tagging

- Neues Feld **Server-Container-URL**: `gtm.js` und der `noscript`-Fallback laden dann vom eigenen GTM-Server-Container statt von `googletagmanager.com`. Akzeptiert werden nur `https`-Adressen ohne Query-Parameter; eine ungültige Eingabe wird verworfen und der Container lädt wieder von Google. Consent-Gating bleibt unverändert.

## Qualitätssicherung

- `npm test` prüft über eine Prüfsumme, ob das eingecheckte Bundle zu den Storefront-Quellen passt – sonst laufen die Tests gegen veralteten Code.
- Neues Skript `tests/integration/consent-matrix.sh` prüft das Consent-Verhalten gegen einen laufenden Shop, unter anderem dass eine cachebare Seite niemals `granted` ausliefert.
- Der Paritäts-Test der Konfigurations-Defaults vergleicht jetzt auch die **Werte**, nicht nur die Schlüssel.

## Weiteres

- Im Dropdown „Tag-Position“ fehlte der eigentliche Wert: Die Labels enthielten `<head>` und `<body>`, und der HTML-Parser hat die beiden Tags beim Rendern der Option verschluckt. Jetzt ohne spitze Klammern.
- Der `noscript`-Fallback wird wieder ausgegeben – allerdings nur dort, wo der Container ohnehin ohne Einwilligung laden darf. Ein Iframe lässt sich nicht nachträglich einwilligungsabhängig zurückhalten.
- Die Tag-Position „Am Ende des Body“ wird jetzt auch dann beachtet, wenn der Container erst nach der Einwilligung nachgeladen wird.
- Der `/s4gtm/user-data`-Endpunkt wird nicht mehr angefragt, wenn Kunden-Tracking, User-ID-Tracking und Enhanced Conversions alle aus sind – die Antwort wäre garantiert leer.
- Die Plugin-Konfiguration lässt sich nicht mehr mit ungültiger Container-ID speichern und weist im Admin darauf hin, wenn das Plugin aktiv ist, aber mangels gültiger ID nichts ausliefert.
- Neu: jsdom-Integrationstests des gebauten Storefront-Bundles gegen echtes Shop-Markup (`tests/js`) sowie eine PHPStan-Konfiguration (Level 5, fehlerfrei).
- Die README beschrieb noch das alte Consent-Modell mit zwei Schaltern; sie ist auf die tatsächliche Konfiguration (Consent-Quelle) umgestellt.

# 1.0.9

- Vererbungs-Switches in der Plugin-Konfiguration zeigten den geerbten Standardwert nicht an: Ein Verkaufskanal-Switch im geerbten Zustand übernimmt jetzt korrekt die globale Einstellung, statt immer als deaktiviert zu erscheinen.
