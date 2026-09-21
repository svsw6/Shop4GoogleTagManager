# Storefront-Tests

jsdom-Integrationstests gegen das **gebaute** Bundle
(`src/Resources/app/storefront/dist/.../shop4-google-tag-manager.js`) und gegen echtes
Shop-Markup. Geprueft wird damit das Artefakt, das im Shop ausgeliefert wird.

```bash
cd tests/js
npm install
npm test
```

Vorher muss `bin/build-storefront.sh` gelaufen sein, sonst fehlt das Bundle.

## Nach jedem Build: stempeln

`npm test` vergleicht zuerst eine Pruefsumme der Storefront-Quellen mit `bundle.sha256`. Passt
sie nicht, bricht der Lauf ab – sonst wuerden die Tests unbemerkt gegen veralteten Code laufen.
Nach dem Build und dem Zurueckholen des `dist/`-Bundles gehoert deshalb dazu:

```bash
npm run stamp
```

## Fixtures

Bis auf `wishlist.html` stammen alle aus einem echten Shop.

- `listing.html` – gekuerzte Kategorieseite eines Shops, dessen Theme `component_product_box`
  **ohne** `{{ parent() }}` ersetzt. Das eigene `<data>`-Element des Plugins fehlt darin also,
  Shopwares `data-product-information` ist vorhanden. Genau dieser Fall hat `add_to_cart` im
  Listing frueher verschluckt.
- `offcanvas-cart.html` – Offcanvas-Warenkorb als `<template>`, das der Test in den DOM haengt
  und wieder entfernt (Oeffnen, Neurendern, Schliessen).
- `cart-page.html` – Positionsliste der Warenkorbseite mit dem Mengen-Formular.
- `finish.html` – Bestellabschluss mit `purchase`-Event, eingeloggtem Kunden und aktiven
  Enhanced Conversions.
- `wishlist.html` – Merkzettel-Button, Kampagnenbanner und Teilen-Button. Aus dem
  Core-Template `component/product/card/wishlist.html.twig` zusammengesetzt, weil der Testshop
  die Merkzettel-Funktion deaktiviert hat.

## Was noch nicht abgedeckt ist

Der `IntersectionObserver` ist in jsdom nicht vorhanden und wird im Harness gestubbt – geprueft
wird also die Verdrahtung von `view_promotion`, nicht der Sichtbarkeitsschwellwert selbst.
