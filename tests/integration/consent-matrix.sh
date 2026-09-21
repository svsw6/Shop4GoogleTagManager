#!/usr/bin/env bash
#
# Prueft das Consent-Verhalten gegen einen LAUFENDEN Shop.
#
# Die Unit- und jsdom-Tests decken Logik und Browser-Verhalten ab; was sie nicht zeigen,
# ist die tatsaechlich ausgelieferte Seite. Genau da sitzen die teuren Fehler: ein
# "granted" im HTTP-Cache oder ein Container, der vor der Einwilligung laedt.
#
# Aufruf (aus dem Plugin-Verzeichnis):
#   tests/integration/consent-matrix.sh http://localhost:8006
#
# Laeuft der Shop hinter einem abweichenden Host-Header (z.B. im Container):
#   BASE=http://localhost HOST=localhost:8006 tests/integration/consent-matrix.sh
#
# Voraussetzung: das Plugin ist aktiv, eine gueltige Container-ID ist hinterlegt und
# die Consent-Quelle steht auf "Shopware-Cookie-Banner" (Standardkonfiguration).

set -u

BASE="${BASE:-${1:-http://localhost:8006}}"
HOST="${HOST:-}"
FAILED=0

curl_page() {
    local path="$1"
    shift
    if [ -n "$HOST" ]; then
        curl -sS -H "Host: ${HOST}" "$@" "${BASE}${path}"
    else
        curl -sS "$@" "${BASE}${path}"
    fi
}

pass() {
    printf 'PASS  %s\n' "$1"
}

fail() {
    FAILED=$((FAILED + 1))
    printf 'FAIL  %s\n' "$1"
    if [ "$#" -gt 1 ]; then
        printf '      erwartet: %s\n      erhalten: %s\n' "$2" "$3"
    fi
}

check() {
    if [ "$2" = "$3" ]; then
        pass "$1"
    else
        fail "$1" "$2" "$3"
    fi
}

check_contains() {
    if printf '%s' "$3" | grep -q "$2"; then
        pass "$1"
    else
        fail "$1" "enthaelt ${2}" '(nicht gefunden)'
    fi
}

consent_default() {
    grep -o '"analytics_storage":"[a-z]*"' | head -1 | sed 's/.*:"//;s/"//'
}

echo "Shop: ${BASE}${HOST:+ (Host: ${HOST})}"
echo

# --- 1) ohne einwilligung darf nichts laden ---------------------------------
home_anonymous="$(curl_page '/')"
check 'Startseite ohne Einwilligung: consent default denied' \
    'denied' "$(printf '%s' "$home_anonymous" | consent_default)"

if printf '%s' "$home_anonymous" | grep -q 'gtm\.js'; then
    fail 'Startseite ohne Einwilligung: kein gtm.js im HTML'
else
    pass 'Startseite ohne Einwilligung: kein gtm.js im HTML'
fi

# --- 2) cachebare seite darf die einwilligung NICHT uebernehmen -------------
# sonst landet ein "granted" im HTTP-Cache und wird an ablehnende besucher ausgeliefert
home_consented="$(curl_page '/' -b 's4gtm-analytics=1; s4gtm-marketing=1')"
check 'Startseite MIT Consent-Cookie: consent default bleibt denied (Cache-Schutz)' \
    'denied' "$(printf '%s' "$home_consented" | consent_default)"

# --- 3) checkout ist no-store: dort darf der server die einwilligung kennen --
cart_consented="$(curl_page '/checkout/cart' -b 's4gtm-analytics=1')"
check 'Warenkorb MIT Consent-Cookie: consent default granted (no-store-Route)' \
    'granted' "$(printf '%s' "$cart_consented" | consent_default)"

cart_anonymous="$(curl_page '/checkout/cart')"
check 'Warenkorb ohne Consent-Cookie: consent default denied' \
    'denied' "$(printf '%s' "$cart_anonymous" | consent_default)"

# --- 4) die eigenen endpunkte duerfen nie in einen cache ---------------------
user_data_headers="$(curl_page '/s4gtm/user-data' -D- -o /dev/null -H 'X-Requested-With: XMLHttpRequest')"
check_contains '/s4gtm/user-data ist no-store' 'no-store' "$user_data_headers"

check '/s4gtm/user-data ohne Einwilligung leer' \
    '{"user":{}}' "$(curl_page '/s4gtm/user-data' -H 'X-Requested-With: XMLHttpRequest')"

# --- 5) cookie-banner ------------------------------------------------------
offcanvas="$(curl_page '/cookie/offcanvas')"
check_contains 'Cookie-Banner enthaelt den Analytics-Eintrag' 's4gtm-analytics' "$offcanvas"

echo
if [ "$FAILED" -eq 0 ]; then
    echo 'OK - Consent-Verhalten wie erwartet'
    exit 0
fi
echo "FEHLER - ${FAILED} Pruefung(en) fehlgeschlagen"
exit 1
