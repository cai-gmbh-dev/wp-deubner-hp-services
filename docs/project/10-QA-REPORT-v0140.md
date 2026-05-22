# QA-Report v0.14.0 - Foundation + MMB-Pilot

Erstellt: 2026-05-22 (QA-Specialist, parallel zum Security-Specialist).
Branch: main. Letzter Commit: e977f81 (release v0.10.2; v0.14.0 noch nicht getaggt).

## Executive Summary

Die statische QA der v0.14.0-Foundation (8 neue Components + Alpine.js-Integration + CSS-Layer-Cascade) und des MMB-Pilots (Lazy-Akkordeon + AJAX-on-Demand-Endpoint + noscript-Fallback) ergibt ein hohes Qualitaetsniveau: alle 8 Component-Templates implementieren ARIA-Patterns konsistent, das CSS verwendet durchgaengig Design-Tokens, der Lazy-Loading-State-Machine im MMB-Template ist sauber via Daten-Attribute orchestriert, und der AJAX-Handler enthaelt mehrere Defense-in-Depth-Layer (Nonce, Rate-Limit, Service-Whitelist, sanitize_key, wp_kses_post auf Render). **Critical: 0, Major: 0, Minor: 3, Nitpick: 5.** Funktionale Smoke-Tests (Tasks 3-5) konnten in dieser Session nicht ausgefuehrt werden, weil das Docker-Exec ueber den Bash-Sandbox blockiert wurde - sie werden in der Acceptance Checklist als UNKNOWN markiert und muessen manuell vom Architekten ausgefuehrt werden (siehe Section "Reproduktions-Befehle"). Gesamt-Verdict: **GO-WITH-CAVEATS** (statische Pruefung gruen, funktionaler Smoke ausstehend).

---

## Section 1 - A11y-Check der 8 Components

| Component | ARIA-Kernattribute | Heading | Focus / Reduced-Motion | Pass |
|-----------|--------------------|---------|------------------------|------|
| skeleton-loader.php | `aria-busy="true"` + `aria-live="polite"` + `aria-hidden="true"` auf Inner-Items + `.screen-reader-text` mit "Inhalt wird geladen ..." | n/a | reduced-motion in components.css L144-156 deaktiviert Shimmer | PASS |
| empty-state.php | `role="status"` auf Wrapper, `aria-hidden="true"` auf Icon, `<h3>` als Heading | h3 hardcoded | `:focus-visible` mit primary-hover Outline (components.css L224) | PASS |
| lazy-image.php | KEIN ARIA - korrekt, weil `<picture>` mit `alt`-Attribut die einzige Schnittstelle ist | n/a | reduced-motion (components.css L282) deaktiviert Transitions | PASS |
| accordion.php | Native `<details>`/`<summary>` (browser-internes aria-expanded), `aria-hidden="true"` auf Chevron, Exclusive-Mode via `name=` (Living-Standard) | Item-Title als Span im `<summary>` - korrekt (h3-Hierarchie bleibt in Eltern-Section) | `:focus-visible` mit Outline-Offset -2px (components.css L329) | PASS |
| content-card.php | `:aria-expanded` (Alpine-Binding, x-Variable), `aria-controls` referenziert detail-ID, `aria-hidden="true"` auf Icons/Play-Overlay, filterbare Heading-Stufe via `dhps_content_card_heading_level` (Default h3) | h3 default, filterbar | `:focus-visible` L507 + L552 + L557 | PASS |
| filter-bar.php | `role="search"` auf Wrapper, `aria-pressed` auf Chips (Alpine), `aria-live="polite"` Status-Region, `screen-reader-text`-Labels fuer Input/Sort, `aria-describedby` bei Mindestzeichen-Hint, `aria-label` auf Chip-Group | n/a (Toolbar, keine Headings) | `:focus-visible` L623, L657, L661, L693 | PASS |
| content-list.php | `role="region"` + `aria-labelledby` auf Wrapper, eigene `screen-reader-text`-Label-Span, `:data-visible-count` Alpine-Binding | Keine eigenen Headings - delegiert an Cards | n/a (keine eigenen interaktiven Elemente) | PASS |
| pagination.php | `aria-label` auf `<nav>`, `aria-current="page"` (statisch + Alpine-Binding), `:aria-busy` an Load-More, `role="status"` + `aria-live="polite"` Status, `role="alert"` fuer Error | n/a | `:focus-visible` L753, L757, L786, L789 | PASS |

A11y-Pass-Rate: **8/8** statisch (alle 8 Components erfuellen WCAG 2.1 AA-Patterns fuer ARIA-Sichtbarkeit, Keyboard und Reduced-Motion).

Beobachtungen / Minor-Anmerkungen:
- (Minor) `content-card.php` Zeile 200: `aria-controls` ist gesetzt, das passende `id`-Attribut auf der Detail-`<div>` heisst dasselbe (`$body_id`) - **korrekt**. Hat aber keinen statischen `aria-expanded`-Initial-Wert (nur via `:aria-expanded`-Alpine-Binding) - bei Alpine-Init-Phase ohne JS bleibt das Attribut leer. Dank `x-cloak`-Block (siehe Section 6) wird der Toggle erst sichtbar, wenn Alpine bereit ist - PASS.
- (Minor) `lazy-image.php` Z. 47: `style="background-image: url('data:image/...')"` wird durch `esc_attr` quotegeschuetzt, aber Whitespace im LQIP-String kann theoretisch escape umgehen - in der Praxis sind Data-URIs base64-clean. Akzeptabel.
- (Nitpick) `skeleton-loader.php` Z. 45: Translator-Domain heisst `deubner-hp-services`, der Rest des Plugins nutzt `wp-deubner-hp-services`. Inkonsistent, aber funktional kein Problem da kein .po-File existiert.

---

## Section 2 - CSS-Layer-Konsistenz

| Datei | @layer-Direktive | Layer-Used | !important | Hex (ausser Tokens) |
|-------|------------------|------------|------------|---------------------|
| dhps-design-tokens.css | `@layer dhps-reset, dhps-tokens, dhps-components, dhps-utilities, dhps-overrides;` (L14) | `dhps-tokens` (L16-111) | 0 | viele - korrekt (Token-File ist die einzige legitime Quelle) |
| dhps-frontend.css | n/a (uebernimmt die Layer-Definition implizit ueber Load-Order via dependency-Kette) | `dhps-reset` (L18-88) + `dhps-components` (L90-2571) | 15 (alle in legacy-CSS-Pfaden vor v0.14.0, primaer `word-wrap: normal !important;` in screen-reader-text + animation-duration in reduced-motion) | unbekannt (nicht voll gegrept, aber Stichprobe zeigt: nur in screen-reader-text-Block) |
| dhps-components.css | n/a (kein eigener `@layer ... ;`-Header - **OK**, weil dhps-design-tokens.css den globalen Header setzt, sodass `@layer dhps-components { ... }` im Browser dem bereits definierten Layer beitritt) | `dhps-components` (L21 - L917) | 1 (`.dhps-content-list__item-wrap.is-hidden, ...[hidden] { display: none !important; }` L902 - Hide-State, **akzeptabel**) | **0** (Stichprobe ueber 100% des Files via Grep zeigt: keine Hex-Farben, alle via `var(--dhps-color-*)` - vorbildlich) |
| dhps-elementor-bridge.css | `@layer dhps-reset, dhps-tokens, dhps-components, dhps-utilities, dhps-overrides;` (L28) - idempotent wiederholt | `dhps-tokens` (L30-62) | 0 | 1 (Fallback in der `var()`-Kette `#333333` + `#0AA245` Z. 36/43 - **akzeptabel, weil als Worst-Case-Fallback hinter `--dhps-color-steuern`**) |

**Reihenfolge der Layer (statisch deklariert):** reset -> tokens -> components -> utilities -> overrides. Konsistent in beiden definierenden Files (tokens.css + bridge.css).

**Token-Verwendung in components.css:** Alle Farben referenzieren `var(--dhps-color-*)`-Tokens. KEIN Hardcoded-Hex (verifiziert via Grep `#[0-9a-fA-F]{3,6}` = 0 Treffer).

**prefers-reduced-motion in components.css:** **6 Vorkommen** (L144, L282, L357, L572, L721, L825) - jeweils pro Component-Bereich (Skeleton, LazyImage, Accordion, ContentCard, FilterBar, Pagination). FilterBar (L721) und Pagination (L825) sind korrekt enthalten.

**!important-Inflation:** Insgesamt 16 (15 in frontend.css + 1 in components.css). Davon sind 15 legitim (screen-reader-text Word-Wrap, reduced-motion-Forced-Reset, Hide-State im Container-Query). **Keine Inflation.**

Beobachtungen:
- (Nitpick) `dhps-components.css` setzt KEIN eigenes `@layer dhps-reset, dhps-tokens, ...`-Header. Das funktioniert, **solange** `dhps-design-tokens.css` immer VOR `dhps-components.css` enqueued wird (was im Plugin der Fall ist - siehe `Deubner_HP_Services.php` L385: `'dhps-components-css' depends on 'dhps-design-tokens'`). Falls jemand `dhps-components.css` standalone laedt, wird der Layer "anonym" - kein Schaden, aber Defense-in-Depth-Anregung: identischer Header-Block wie in bridge.css einfuegen.
- (Nitpick) Der `dhps-elementor-bridge.css`-Fallback `#333333` (Z. 36) bricht das "kein hardcoded Hex"-Prinzip. Begruendung im Datei-Kommentar steht: "doppelte Fallback-Kette" als Sicherheit. Vertretbar.

---

## Section 3 - Functional Cross-Layout-Test fuer MMB

**Status: UNKNOWN (manueller Test erforderlich)**.

Der Smoke-Test `smoke-test-qa-mmb.php` wurde geschrieben und konnte erfolgreich erstellt werden, der Versuch ihn ueber `docker exec ...` auszufuehren wurde jedoch in dieser QA-Session vom Bash-Sandbox blockiert. Das File wurde nach dem Abbruch wie vorgeschrieben geloescht (keine Fragmente im Plugin-Root).

Reproduktions-Befehl fuer den Architekten:

```bash
# Variante A: vorbereiteter Smoke-Test (Architekt kopiert das nachfolgende Skript erneut ins Plugin-Root)
docker exec wp-deubner-hp-services-wordpress-1 \
    php /var/www/html/wp-content/plugins/wp-deubner-hp-services/smoke-test-qa-mmb.php

# Variante B: inline (kompakt)
docker exec wp-deubner-hp-services-wordpress-1 wp eval \
    'foreach ([[\"[mmb]\",\"mmb_default\"],[\"[mmb layout=compact]\",\"mmb_compact\"],[\"[mmb layout=card]\",\"mmb_card\"],[\"[mil]\",\"mil_default\"],[\"[mil layout=compact]\",\"mil_compact\"],[\"[mil layout=card]\",\"mil_card\"]] as $r) { $h=do_shortcode($r[0]); echo $r[1].\": \".strlen($h).\" / browser=\".strlen(preg_replace(\"#<noscript>.*?</noscript>#s\",\"\",$h)).PHP_EOL; }' \
    --allow-root
```

**Statische Pruefung der 9 Akzeptanz-Subkriterien:**

| # | Kriterium | Statischer Befund | Pass/Fail |
|---|-----------|-------------------|-----------|
| 1 | `[mmb]` (default) rendert Lazy-Akkordeon | default.php Z. 117-190 implementiert das Lazy-Akkordeon, **wird nur** ausgefuehrt wenn `! empty( $categories )`. Render-Pfad bestaetigt. | PASS (static) |
| 2 | `[mmb layout="compact"]` rendert volle Liste, compact.php unveraendert | compact.php enthaelt keinen `data-dhps-mmb-lazy-state` und keinen `<noscript>`-Block (Grep negativ) - unveraendert. | PASS (static) |
| 3 | `[mmb layout="card"]` rendert Card-Liste, card.php unveraendert | card.php enthaelt keinen Lazy-State und kein `<noscript>` - unveraendert. | PASS (static) |
| 4 | `[mil]` (default) rendert Lazy-Akkordeon | MIL nutzt MMB-Templates via `dhps_template_fallbacks`-Filter (mil->mmb) - kein eigenes default.php in `mil/`-Pfad. Lazy-Akkordeon wird im selben default.php-Render-Pfad ausgeloest. | PASS (static) |
| 5 | `[mil layout="compact"]` rendert volle Liste | Identisch, ueber Fallback. | PASS (static) |
| 6 | `[mil layout="card"]` rendert Card-Liste | Identisch, ueber Fallback. | PASS (static) |
| 7 | default enthaelt `noscript`-Block | default.php Z. 193 (`<noscript>`) und L218 (`</noscript>`). Inhalt rendert das `partials/category-content.php` per `include`. | PASS (static) |
| 8 | default enthaelt `data-dhps-mmb-lazy-state="pending"` | default.php Z. 140: `data-dhps-mmb-lazy-state="<?php echo esc_attr( $initial_state ); ?>"` mit `$initial_state = $pre_rendered ? 'loaded' : 'pending';` - Default `$pre_rendered=false` (via Filter abschaltbar) -> Initial-State **immer** "pending" out-of-the-box. | PASS (static) |
| 9 | default enthaelt `dhps-skeleton` Klasse | default.php Z. 179: `echo dhps_component( 'skeleton-loader', ... )` rendert `<div class="dhps-skeleton dhps-skeleton--list ...">`. | PASS (static) |

---

## Section 4 - AJAX-Endpoint Functional-Test

**Status: UNKNOWN (manueller Test erforderlich, Docker-Exec blockiert).**

Statische Pruefung von `includes/class-dhps-mmb-ajax-handler.php`:

1. **Nonce-Check (L127-143):** akzeptiert `_wpnonce` ODER `nonce`, prueft via `wp_verify_nonce`. Bei Fehler: 403 + `code: invalid_nonce`. PASS.
2. **Rate-Limit (L146-154):** vor Sanitize -> spaert CPU. Transient mit md5(IP)-Key, 60s TTL, Limit 60. Race-Window dokumentiert. PASS.
3. **Service-Whitelist (L166):** `in_array($service, ['mmb','mil'], true)`. PASS.
4. **Input-Sanitisation (L162-163):** `sanitize_key()` fuer service + category_id, plus L187-194 Length-Limit 100. PASS.
5. **Auth-Token serverseitig (L211):** `get_option( $service_config['auth_option'] )` - kein Token aus Request. PASS.
6. **No-Outbound:** Nur `$this->client->fetch_content()` (DHPS_API_Client, SSRF-safe). KEIN direkter `wp_remote_get`. PASS.
7. **wp_kses_post auf Render (L353 in `render_category_html`):** Defense in Depth. PASS.
8. **Response-Struktur (L285-291):** `category_id, category_name, icon_slug, fact_sheets[], html` - vollstaendig. PASS.

Erwartete Bytes pro Response (laut F6-Handover Section 6): 3-35 KB. **< 50 KB** wird mit Reserve eingehalten.

Reproduktions-Test-Snippets (vom Architekt zu pruefen, sobald MMB-OTA gesetzt ist):

```bash
NONCE=$(docker exec wp-deubner-hp-services-wordpress-1 wp eval "echo wp_create_nonce('dhps_mmb_nonce');" --allow-root)
echo "Nonce: $NONCE"

# 1. Valider Request
curl -i "http://localhost:8082/wp-admin/admin-ajax.php?action=dhps_mmb_category_load&service=mmb&category_id=rubrik_1&_wpnonce=$NONCE"

# 2. Ohne Nonce -> 403
curl -i "http://localhost:8082/wp-admin/admin-ajax.php?action=dhps_mmb_category_load&service=mmb&category_id=rubrik_1"

# 3. Service-Whitelist
curl -i "http://localhost:8082/wp-admin/admin-ajax.php?action=dhps_mmb_category_load&service=evil&category_id=rubrik_1&_wpnonce=$NONCE"
```

Falls MMB-OTA aktuell empty ist (`dhps_mmo_ota`), liefert der Endpoint **erwartet** `success:false, code:service_not_configured` (Z. 213-221) - das ist KEIN Fehler, sondern ein dokumentierter Pfad.

---

## Section 5 - Performance-Vergleich

Die Performance-Tabelle wird mit gemessenen Werten erst nach dem Docker-Smoke-Test final. Vorab die **erwarteten** Werte aus den Handovern:

| Shortcode | v0.13.1 Bytes | v0.14.0 Bytes (inkl. noscript) | v0.14.0 Browser-View (ohne noscript) | Delta % (Browser vs v0.13.1) |
|-----------|---------------|--------------------------------|--------------------------------------|------------------------------|
| `[mmb]` | 307.859 | ~310-330 KB (geschaetzt, F7-Handover) | < 50 KB (Ziel; F7 erwartet 10-25 KB) | **-92 bis -97%** |
| `[mil]` | 305.221 | ~310-330 KB | < 50 KB | **-92 bis -97%** |
| `[mio]` | 4.485 | unveraendert | n/a | 0% |
| `[lxmio]` | n/a | unveraendert | n/a | 0% |
| `[tp]` | n/a | unveraendert | n/a | 0% |
| `[tpt]` | n/a | unveraendert | n/a | 0% |
| `[tc]` | n/a | unveraendert | n/a | 0% |
| `[maes]` | n/a | unveraendert | n/a | 0% |
| `[lp]` | n/a | unveraendert | n/a | 0% |

**Erwartete MMB-Performance-Ersparnis (Browser-View): -92% bis -97%.**

Wichtiger Hinweis: Browser ohne JS sehen den `<noscript>`-Inhalt, dadurch ist die DOM-Groesse fuer SEO-Crawler / no-JS-Clients gegenueber v0.13.1 **leicht groesser** (~310-330 KB statt 307 KB). Das ist eine bewusste Architektur-Entscheidung (SEO-Schutz, F7-Handover Section 4) und korrekt umgesetzt.

---

## Section 6 - Lighthouse-konforme Acceptance Checklist

| # | Kriterium | Status | Befund |
|---|-----------|--------|--------|
| 1 | Alle interaktiven Elemente in den 8 Components haben `:focus-visible` | **PASS** | 14 `:focus-visible`-Regeln in components.css (Stichprobe via Grep), zusaetzlich globale `dhps-service`-Baseline in frontend.css L58-67 |
| 2 | `x-cloak` verhindert FOUC | **PASS** | Regel `[x-cloak]{display:none !important}` in frontend.css L84-86, plus `x-cloak`-Attribut auf 6 von 8 Components (content-card, content-list, filter-bar, pagination, plus konditional an inneren x-show-Elementen) - die 2 stateless (skeleton, empty-state, lazy-image, accordion) brauchen es bewusst nicht |
| 3 | noscript-Fallback liefert SEO-Content (MMB) | **PASS** | `default.php` L193-218 rendert volle Liste via `partials/category-content.php` |
| 4 | Skeleton-Loader zeigt `aria-busy` / `aria-live` waehrend Loading | **PASS** | skeleton-loader.php L44: `aria-busy="true" aria-live="polite"` |
| 5 | Heading-Hierarchie konsistent (h3 in ContentCard, h3 in MMB-Category) | **PASS** | content-card.php default `$heading_tag='h3'` (L73, filterbar), default.php Z. 142 hardcoded `<h3 class="dhps-mmb-category__header">` |
| 6 | Alle ContentCard-Modi (news/video/document) rendern Skip-State korrekt | **PASS** | content-card.php Z. 50-54 whitelisted `$type in ['news','video','document']`, ansonsten Fallback 'news'. Pflichtfeld `$title` mit Early-Return Z. 68-70 |
| 7 | Component-Registry: `was_used()` funktioniert nach Render | **UNKNOWN** | Methode existiert (class-dhps-component-registry.php L202), funktionaler Test wurde im Smoke-Test vorbereitet aber nicht ausgefuehrt |
| 8 | CSS-Cascade: dhps-overrides Layer hat hoechste Prioritaet | **PASS (statisch)** | Layer-Reihenfolge in tokens.css L14 + bridge.css L28: `dhps-reset, dhps-tokens, dhps-components, dhps-utilities, dhps-overrides` - overrides spaeter -> hoehere Prioritaet. Live-Override-Test (Custom-CSS injizieren) wurde nicht durchgefuehrt |
| 9 | Kein PHP-Notice/Warning im Smoke-Test | **UNKNOWN** | Smoke-Test nicht ausgefuehrt (Sandbox-Restriction) |

**A11y-Pass-Rate: 7/9** (PASS) + **2/9** UNKNOWN (Smoke-Test ausstehend).

Mit Annahme dass beide UNKNOWN beim Smoke-Test passen (was statisch sehr wahrscheinlich ist, da `was_used()` getestet werden kann und PHP-Notices durch defensive `isset()`/`is_array()`-Checks unwahrscheinlich sind): **erwartete A11y-Pass-Rate 9/9.**

---

## Empfohlene Verbesserungen

### Critical (Blocker)
Keine.

### Major (Should-fix vor Release)
Keine.

### Minor (Should-fix in 0.14.1)

1. **`dhps-components.css` ohne eigenen `@layer ... ;`-Header.** Defense-in-Depth: Identischen Header-Block wie in `dhps-elementor-bridge.css` voranstellen, damit standalone-Laden den Layer-Kontext nicht verliert. Aufwand: 3 Zeilen.
2. **content-card.php: kein statisches `aria-expanded` als Initial-Wert.** Bei JS-Ausfall (Alpine-Bootstrap-Fehler) bleibt der Toggle-Button ohne ARIA-State. Vorschlag: `aria-expanded="false"` statisch setzen, Alpine-Binding ueberschreibt es dann.
3. **skeleton-loader.php: Text-Domain inkonsistent** (`deubner-hp-services` vs. `wp-deubner-hp-services` ueberall sonst). Vor i18n-Release angleichen.

### Nitpick (Optional)

1. `dhps-elementor-bridge.css` Hex-Fallback `#333333` koennte durch `currentColor` ersetzt werden (Geschmackssache).
2. `accordion.php` Z. 67: Inline-PHP-Conditional-Echo fuer `name`-Attribut koennte ueber Helper-Variable lesbarer sein.
3. `content-list.php` Z. 100-104: `printf( esc_html__( 'Inhaltsliste (%s)', ... ), esc_html( $layout ) );` - der Layout-String ist intern (grid/list/masonry) - keine i18n-Notwendigkeit fuer den Slug. Cosmetic.
4. `pagination.php` Z. 154-155: `:aria-current="..."` und statisches `aria-current="page"` koennten kollidieren bei Alpine-Init. In der Praxis ueberschreibt Alpine das statische Attribut - akzeptabel.
5. `Deubner_HP_Services.php` L389: `wp_enqueue_style( 'dhps-components-css' )` immer aktiv, auch wenn keine Component verwendet wird - Conditional-Enqueue ueber `DHPS_Component_Registry::was_used()` waere effizienter (aber non-Critical, ~24 KB).

---

## Gesamt-Verdict

**GO-WITH-CAVEATS.**

Begruendung:
- Statische QA ist gruen ueber alle 6 Tasks (A11y, CSS-Konsistenz, Markup, AJAX-Handler-Logik).
- Performance-Erwartung (-92% bis -97% MMB-Browser-Bytes) ist architektonisch korrekt implementiert.
- Critical: 0, Major: 0 - keine Blocker.
- Caveat: Der funktionale Smoke-Test (Tasks 3, 4, 5 sowie Acceptance-Items 7 + 9) konnte in der QA-Session nicht ausgefuehrt werden (Bash-Sandbox blockierte `docker exec`). Der Architekt muss die in den Sections 3 + 4 dokumentierten `docker exec`-Befehle einmal manuell ausfuehren, bevor das Release-Tag gesetzt wird.

Falls der manuelle Smoke-Test PASS liefert, kann v0.14.0 direkt released werden.
