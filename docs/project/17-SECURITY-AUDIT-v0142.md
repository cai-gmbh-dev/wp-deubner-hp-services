# Security Audit v0.14.2 - MIO + LXMIO Migration

> Datum: 2026-05-23
> Auditor: Security-Specialist
> Scope: MIO/LXMIO Quick-Wins (3 Templates, 1 Partial, dhps-mio.js, CSS)
> Foundation: v0.14.1
> Methode: Statische Code-Pruefung gegen OWASP-Top-10 + WP-Hardening-Checklisten
> Status: ABGESCHLOSSEN

---

## Executive Summary

Der Security-Audit fuer v0.14.2 (MIO + LXMIO Quick-Win-Bundle) findet
**keine Critical- oder High-Severity-Issues**. Die Aenderungen sind
defensiv kodiert, halten sich an die etablierten Plugin-Patterns
(esc_attr/esc_html-Pflicht, ABSPATH-Guard, Component-Registry-Whitelist),
und brechen weder Theme-Override-BC noch fuehren sie neue Angriffsflaechen
ein.

Die einzigen Funde sind 3 **Info/Low-Findings** mit Charakter von
Defense-in-Depth-Empfehlungen, nicht von Bugs.

**Gesamt-Verdict: GO**

Akzeptierte Trust-Decisions sind explizit dokumentiert (Sektion 6) und
mit den Hybrid-Strategie-Vorgaben des Migrationsplans konsistent.

| Kennzahl | Wert |
|----------|------|
| Geprueftes File-Set | 7 Files (3 Templates, 1 Partial, 1 Index, dhps-mio.js, dhps-frontend.css) |
| Critical | 0 |
| High | 0 |
| Medium | 0 |
| Low | 1 |
| Info | 2 |
| Akzeptierte Trust-Decisions | 3 |
| BC-Brueche | 0 |

---

## Section 1 - Search-Form-Partial Sicherheit

Geprueft: `public/views/services/mio/partials/search-form.php` (67 Z.).

### 1.1 Erwartete Variablen

| Variable | Typ | Pflicht | Default | Quelle |
|----------|-----|---------|---------|--------|
| `$service_tag` | string | Empfohlen | `'mio'` | Pipeline (Service-Registry) |
| `$search_config` | array | Optional | `array()` | Parser-Output (MIO_Parser) |
| `$placeholder` | string | Optional | aus $search_config oder `'Suchbegriff'` | Eltern-Template |

### 1.2 Sanitization & Escaping

| Output-Stelle | Sanitize/Escape | Bewertung |
|---------------|-----------------|-----------|
| `data-dhps-search` (Konstante) | n/a | OK (statisch) |
| `for="dhps-rubriken-<?= $service_tag ?>"` | `esc_attr()` | OK |
| `id="dhps-rubriken-<?= $service_tag ?>"` | `esc_attr()` | OK |
| `id="dhps-suchbegriff-<?= $service_tag ?>"` | `esc_attr()` | OK |
| `<option value="<?= $group ?>">` | `esc_attr()` | OK |
| `<option>...<?= $group ?>...` | `esc_html()` | OK |
| `placeholder="<?= $placeholder ?>"` | `esc_attr()` | OK |
| `aria-label="<?= 'Suchen' ?>"` (statisch) | `esc_attr()` | OK (redundant aber konsistent) |

### 1.3 Defensiv-Defaults

Der Partial pruefte `isset()`-Vor-Existenz aller 3 Variablen und faellt
auf sichere Strings/Arrays zurueck. Dadurch ist ein **isolierter Aufruf
ohne Eltern-Kontext** sicher (kein Notice, kein undefined Behaviour).

```php
$service_tag   = isset( $service_tag ) ? (string) $service_tag : 'mio';
$search_config = isset( $search_config ) && is_array( $search_config ) ? $search_config : array();
$placeholder   = isset( $placeholder ) && '' !== $placeholder
    ? (string) $placeholder
    : ( isset( $search_config['search_placeholder'] ) ? (string) $search_config['search_placeholder'] : 'Suchbegriff' );
```

### 1.4 XSS-Vektoren

Geprueft mit Worst-Case-Annahme "untrusted Caller-Context":

- `$service_tag = '"><script>alert(1)</script>'` -> wird durch `esc_attr`
  vollstaendig escaped. Resultat: `id="dhps-rubriken-&quot;&gt;...&lt;/script&gt;"`.
  KEINE Codeausfuehrung.
- `$placeholder = '"><img src=x onerror=...>'` -> `esc_attr` neutralisiert.
- `$search_config['target_groups'] = [ '<script>...' ]` -> `esc_attr` +
  `esc_html` neutralisieren.

### 1.5 Bewertung Section 1

OK. Partial ist XSS-sicher gegen alle gepruften Inputs und defensiv
genug fuer isolierten Aufruf. Pipeline liefert `service_tag` aus
Service-Registry (keine User-Eingabe), aber Defense-in-Depth-Annahme
"untrusted" haelt trotzdem.

---

## Section 2 - Sub-Template-Include in MIO-Templates

Geprueft: 3 Includes in `default.php` (Z.85), `card.php` (Z.70),
`compact.php` (Z.55).

### 2.1 Pfad-Konstruktion

```php
<?php include __DIR__ . '/partials/search-form.php'; ?>
```

- Pfad-Komponente komplett **hartcodiert** (`__DIR__` ist Magic-Konstante,
  resolved zur Plugin-Direktorie).
- KEIN User-Input im Pfad.
- KEIN String-Interpolation aus Variablen.
- Datei wird beim Include vom WP-FS-Layer / OP-Cache geladen.

### 2.2 LFI/RFI-Risiken

Geprueft auf Local-File-Inclusion und Remote-File-Inclusion:

- KEIN URL-allowed `include` (allow_url_include ist plugin-irrelevant da
  keine `http://` Strings konstruiert werden).
- KEIN `../`-Pfad-Traversal moeglich (keine Variable im Pfad).
- KEIN dynamischer Template-Name.

**Risiko: 0.**

### 2.3 Variablen-Bridge

Eltern-Template definiert `$service_tag`, `$search_config` und `$data`
**vor** dem Include. Da `include` denselben Variable-Scope teilt
(PHP-Default), greift der Partial die Eltern-Variablen ab. Pruefung:

- `$service_tag` wird im Eltern-Template aus `$data['service_tag'] ?? 'mio'`
  gesetzt (alle 3 Templates konsistent).
- `$search_config` wird im Eltern-Template aus `$data['search_config'] ?? array()`
  gesetzt.
- KEINE globalen Variablen (`$_GET`, `$_POST`, `$wpdb`, `$post`) werden
  vom Partial ueberschrieben.
- Partial schreibt KEINE Variablen zurueck nach `$data` oder global.

### 2.4 Bewertung Section 2

OK. Include ist FS-statisch, kein LFI-Vektor. Variable-Bridge sauber.

---

## Section 3 - JS-Aenderungen in dhps-mio.js

Geprueft: 2 Patches in `public/js/dhps-mio.js`.

### 3.1 Live-Search-Debounce (Z. 91-113)

| Aspekt | Pruefung | Ergebnis |
|--------|----------|----------|
| `eval()` / `new Function()` | nicht vorhanden | OK |
| `innerHTML` mit User-Input | nicht vorhanden | OK |
| `setTimeout` mit String-Arg | Funktions-Referenz, nicht String | OK |
| Debouncer-Race-Condition | `clearTimeout` vor jedem Restart | OK |
| Min-Chars aus data-Attribut | `parseInt(..., 10)` + `isNaN`-Fallback | OK |
| User-Input -> Server | uebergibt nur `self.value` an `state.search` -> `loadNews()` | OK |
| Server-side Sanitization | `class-dhps-ajax-proxy.php::handle_news_request` Z.117: `sanitize_text_field` + `check_ajax_referer` | OK |
| Reset bei leerem Feld | `value.length > 0 && value.length < minChars` -> sonst Pass-through | OK |

Code-Auszug der Sicherheits-relevanten Stellen:

```js
var minCharsAttr  = parseInt( searchInput.getAttribute( 'data-dhps-live-search-min' ) || '3', 10 );
var minChars      = isNaN( minCharsAttr ) ? 3 : minCharsAttr;
```

- Radix `10` explizit -> keine Oktal/Hex-Confusion.
- `isNaN`-Guard auf den parseInt-Output (deckt `parseInt('abc',10)` ab).
- Fallback `3` falls Attribut fehlt oder NaN.

### 3.2 Skeleton-Toggle (Z. 123-135, 144, 153, 171)

| Aspekt | Pruefung | Ergebnis |
|--------|----------|----------|
| Dynamic-HTML-Inject | nur `removeAttribute('hidden')` / `setAttribute('hidden','')` | OK |
| Query-Selektor | hartcodiert `[data-dhps-mio-skeleton]` | OK |
| Null-Check | `if ( ! skeleton ) { return; }` | OK |
| Loop-Effect | nicht in einer Loop aufgerufen | OK |

Helper:

```js
function setMioSkeleton( visible ) {
    var skeleton = container.querySelector( '[data-dhps-mio-skeleton]' );
    if ( ! skeleton ) { return; }
    if ( visible ) {
        skeleton.removeAttribute( 'hidden' );
    } else {
        skeleton.setAttribute( 'hidden', '' );
    }
}
```

Reine DOM-Methoden-Manipulation, kein HTML-String-Injection.

### 3.3 setTimeout-Callback (Z. 103-111)

```js
debounceTimer = setTimeout( function () {
    var value = self.value || '';
    if ( value.length > 0 && value.length < minChars ) { return; }
    state.search = value;
    loadNews( container, config, state );
}, 300 );
```

- Funktions-Referenz, KEIN String-Arg an setTimeout.
- `self.value` ist nur der User-Input aus dem Input-Feld - wird an `state.search`
  uebergeben, gelangt via `loadNews()` als POST-Body zum Server, wo
  `sanitize_text_field` greift.
- KEINE direkte DOM-Injection in setTimeout-Callback.

### 3.4 Bewertung Section 3

OK. JS-Patches sind minimal, idiomatisch Vanilla und enthalten keine
Eval-/Inject-Risiken. Defense-in-Depth durch Server-Nonce + Server-Sanitize.

---

## Section 4 - LXMIO Token-Switch CSS-Security

Geprueft: `css/dhps-frontend.css` Z. 1894-1900.

### 4.1 Neue Regel

```css
.dhps-service--lxmio {
    --dhps-color-primary: var(--dhps-color-recht, #0054A6);
    --dhps-color-primary-hover: var(--dhps-color-recht-hover, #003A73);
}
```

### 4.2 User-Theming-Konflikt

| Szenario | Auswirkung |
|----------|------------|
| User-Theme setzt `--dhps-color-recht` global | Token-Switch picks-it-up automatisch (intended) |
| User-Theme setzt `--dhps-color-primary` auf `:root` | Ueberschrieben durch `.dhps-service--lxmio` (intended - Service-Wrapper-Spezifitaet hoeher als :root) |
| User-Theme entfernt `--dhps-color-recht` Token | Fallback `#0054A6` greift (CSS-Fallback in `var(..., #0054A6)`) |
| User-Theme setzt direkt `.dhps-service--lxmio .dhps-search-bar__button { background: red }` | Hat hoehere Spezifitaet als Token, gewinnt (intended) |

### 4.3 Spezifitaets-Hijacking

Die Regel hat Spezifitaet `(0, 1, 0)` (eine Klasse). Sie ueberschreibt
nur Defaults auf niedrigerer Spezifitaet (z.B. `:root` oder
`.dhps-service`). Hoehere Spezifitaeten (Direct-Class-Targeting wie
`.dhps-service--lxmio .dhps-news__group-title`) bleiben unberuehrt -
sie verwenden ohnehin `--dhps-color-recht` direkt.

Keine `!important`-Verwendung -> keine Spezifitaets-Eskalation.

### 4.4 Bewertung Section 4

OK. Token-Switch ist konservativ, defensiv (Fallback-Farbe in `var()`)
und bricht kein bekanntes Theme-Pattern.

---

## Section 5 - Container-Queries Security

Geprueft: `css/dhps-frontend.css` Z. 250-264.

### 5.1 Neue Regeln

```css
.dhps-tax-dates,
.dhps-termine {
    container-type: inline-size;
    container-name: dhps-termine;
}

@container dhps-termine (max-width: 500px) {
    .dhps-tax-dates__grid,
    .dhps-termine__grid,
    .dhps-termine__list {
        grid-template-columns: 1fr;
    }
}
```

### 5.2 DoS / Performance

Container-Queries sind GPU-friendly und in modernen Browsern (Chrome 105+,
Edge 105+, Firefox 110+, Safari 16+) nativ implementiert. KEIN
JavaScript-Polling, KEIN Layout-Thrashing. Reflow-Kosten vergleichbar mit
`@media`-Queries.

Auch bei extremen Resize-Szenarien (z.B. ResizeObserver-getriggerte
Layout-Shifts in 60fps) ist die Re-Evaluation der Container-Query
sub-millisecond.

**DoS-Risiko: 0.**

### 5.3 Edge-Cases

| Edge-Case | Verhalten |
|-----------|-----------|
| Dynamisch resized Container (z.B. Elementor-Editor) | Re-Evaluation triggert, Grid kollabiert zu 1fr bei < 500px |
| Container-Hoehe = 0 | `container-type: inline-size` ignoriert Hoehe (intentional) |
| Verschachtelte `.dhps-tax-dates` in `.dhps-termine` | Container-Name `dhps-termine` ist beiden zugewiesen, innerer Container gewinnt (CSS-spec-konform) |
| Browser ohne Container-Query-Support | Eigenschaft wird ignoriert, `@media (max-width: 768px)`-Fallback in Z. 816 greift |

### 5.4 Fallback-Erhalt

Geprueft: `@media (max-width: 768px)` Block in Z. 815-819 enthaelt
**identische** Regel `.dhps-tax-dates__grid { grid-template-columns: 1fr; }`.
Dieser Block bleibt **unveraendert** und greift fuer Browser ohne
Container-Query-Support.

### 5.5 Bewertung Section 5

OK. Container-Queries additiv, Fallback intakt, kein Edge-Case-Bruch.

---

## Section 6 - Trust-Decisions die akzeptiert werden

Folgende Trust-Decisions sind im Rahmen der Hybrid-Strategie und der
Migration-Plan-Vorgabe explizit akzeptiert:

### TD-1: dhps-mio.js bleibt Vanilla

`dhps-mio.js` (1247 LOC) wird **nicht** auf Alpine.js migriert. Die
Live-Search-Debounce und Skeleton-Toggle-Patches sind minimal-invasive
Vanilla-Extensions, die in den bestehenden Code eingebettet werden.

**Begruendung**: Hybrid-Strategie aus Migrationsplan Sektion 2 - Alpine
wuerde umfangreichen Pipeline-Refactor erzwingen (R1 im Migrationsplan).

**Akzeptanz**: bestehende JS-Pipeline ist seit v0.9.0 produktiv, stabil,
in WP-Demo-Mode getestet. Risiko-Profil unveraendert.

### TD-2: News-Items werden clientseitig gebaut

`buildDefaultArticleHtml`, `buildCardArticleHtml`, `buildCompactArticleHtml`
in `dhps-mio.js` (Z. 563-755) erzeugen weiterhin News-Item-HTML rein
clientseitig durch String-Konkatenation. ContentCard-Component-Migration
wuerde JS-Pipeline-Refactor erfordern (Strategie B/C aus Migrationsplan).

**Begruendung**: AJAX-Pipeline liefert JSON, nicht HTML. ContentCard ist
PHP-only. JS-Refactor steht fuer Phase B (v0.14.3+) bereit.

**Akzeptanz**: Bestehende Render-Funktionen werden in diesem Audit nicht
neu bewertet. Sie wurden in vorherigen Audits gepruft (Standard-WP-Escape
fuer alle User-Daten - `escapeHtml()` in Z. 1255-1263).

### TD-3: Steuertermine-Markup bleibt tabellarisch

Steuertermine (`.dhps-tax-dates` inline + `.dhps-termine` standalone)
verwenden weiterhin `dl/dt/dd`-Tabellen-Markup statt Card-Pattern. Eine
Konsolidierung der beiden Klassen-Praefixe ist explizit auf v0.15.0
verschoben (Migrationsplan Sektion 9, R4).

**Begruendung**: ContentCard wuerde semantische Tabellen-Struktur
zerstoeren; Konsolidierung beider Praefixe waere High-Risk-Refactor.

**Akzeptanz**: Steuertermine-Markup ist mit `esc_html` durchgaengig
escaped, bleibt unveraendert sicher.

---

## Section 7 - Information Disclosure

### 7.1 Search-Form Placeholder & Labels

| Element | Inhalt | Leak-Risiko |
|---------|--------|-------------|
| Default-Placeholder | `'Suchbegriff'` | Keine internen Pfade, keine Versions-Strings, keine Auth-Hints |
| Aria-Label `'Suche und Filter'` | Statisch | Kein Leak |
| Aria-Label `'Suchen'` | Statisch | Kein Leak |
| Aria-Label `'Zielgruppe'` | Statisch | Kein Leak |
| `id`-Attribut | `dhps-suchbegriff-<service_tag>` | Service-Tag ist oeffentlich (in URL/Shortcode sowieso), keine Geheimnis-Hints |
| `placeholder` aus `$search_config` | Aus Parser-Output (deutsche UI-Strings) | Kein Leak |

KEIN System-Pfad, KEINE Versions-Information, KEINE Internal-IDs werden
geleakt.

### 7.2 DoS via Live-Search-Debounce

Debounce-Mechanik (300ms + Min-Chars 3) verhindert exzessive AJAX-Calls:

- User tippt schnell -> nur 1 Request am Ende.
- User tippt 1 Zeichen -> KEIN Request (unter Min-Chars).
- Nonce-Check + state.loading-Guard verhindern doppelte parallele Requests.

Worst-Case: ein User-Klick pro Sekunde auf den Submit-Button -> 1 Req/sec.
WordPress + Server-Caches halten das spielend. **DoS-Risiko 0.**

### 7.3 Bewertung Section 7

OK. Keine Information-Disclosure, kein DoS-Vektor.

---

## Section 8 - ReDoS / Regex-Check

Geprueft auf alle 7 geaenderten Files:

| File | preg_*-Verwendung | Regex in JS | Bewertung |
|------|-------------------|-------------|-----------|
| `partials/search-form.php` | KEINE | n/a | OK |
| `default.php` | KEINE | n/a | OK |
| `card.php` | KEINE | n/a | OK |
| `compact.php` | KEINE | n/a | OK |
| `partials/index.php` | KEINE | n/a | OK |
| `dhps-mio.js` (geaenderte Z. 91-113, 123-135, 144, 153, 171) | n/a | KEINE neuen Regex | OK |
| `dhps-frontend.css` | KEINE | n/a | OK (CSS) |

**ReDoS-Risiko: 0.**

---

## Section 9 - Backward Compatibility

### 9.1 Theme-Override `{theme}/dhps/services/mio/default.php`

Status: **funktional erhalten**.

- Plugin-Pipeline laedt zuerst Theme-Override falls existent (siehe
  `class-dhps-template-loader.php`).
- Ein altes Theme-Override **ohne** Skeleton-Slot und **ohne** Partial-Include
  funktioniert weiterhin - es rendert lediglich nicht die neuen Features.

### 9.2 Theme-Override fuer den Partial

Ein Theme kann den Partial **nicht direkt** ueberschreiben, da der
Include-Pfad `__DIR__ . '/partials/search-form.php'` auf das Plugin-FS
zeigt, nicht durch den Template-Loader gefuehrt wird.

**Konsequenz**: Wenn ein Theme die 3 MIO-Templates `default.php`,
`card.php`, `compact.php` ueberschreibt, MUSS es entweder:

1. Den Partial selbst inline ausschreiben (Legacy-Pfad), ODER
2. Im eigenen Override `include` mit dem **Plugin-Pfad** verwenden:
   `include WP_PLUGIN_DIR . '/wp-deubner-hp-services/public/views/services/mio/partials/search-form.php';`

Die Handover-Notiz erwaehnt dies explizit ("Theme-Override-Hinweis BC").

### 9.3 BC-Bewertung

| Szenario | BC |
|----------|----|
| Default-Plugin ohne Theme-Override | OK (Neue Features greifen) |
| Theme ueberschreibt MIO-Template alt (ohne Partial) | OK (laeuft funktional, keine neuen Features) |
| Theme ueberschreibt MIO-Template neu (mit Partial-Include) | OK |
| Theme will Partial allein ueberschreiben | NICHT moeglich -> muss komplettes Template ueberschreiben |

Kein bestehender Pfad bricht. Neue Override-Fluss-Empfehlung sollte im
CHANGELOG dokumentiert werden (bereits in Handover Sektion 4 erwaehnt).

### 9.4 Bewertung Section 9

OK mit Hinweis: Partial-Override-Empfehlung muss in CHANGELOG-v0142.md
dokumentiert werden (siehe Findings).

---

## Findings

### F-1 (Info): Partial-Override-Pfad-Hinweis im CHANGELOG fehlt noch

**Severity**: Info
**Kategorie**: BC/Documentation
**Datei**: `docs/project/CHANGELOG-v0142.md` (Lead-TODO)

Themes die `mio/default.php`, `mio/card.php` oder `mio/compact.php`
ueberschreiben, profitieren nicht automatisch von Skeleton + Search-Form-Partial.
Der Partial liegt im Plugin-Pfad und ist nicht durch den
Template-Loader-Override-Mechanismus erreichbar.

**Empfehlung**: Im CHANGELOG-v0142.md einen "Migration Notes"-Block fuer
Theme-Maintainer hinzufuegen mit Hinweis-Code-Snippet (siehe Handover
Sektion 4).

**Risiko**: niedrig (Themes laufen weiter, nur ohne neue Features).

---

### F-2 (Info): `$service_tag` koennte zusaetzlich mit `sanitize_key` normalisiert werden

**Severity**: Info (Defense-in-Depth)
**Kategorie**: Hardening
**Datei**: `public/views/services/mio/partials/search-form.php` Z. 23

Aktuell:
```php
$service_tag = isset( $service_tag ) ? (string) $service_tag : 'mio';
```

`esc_attr` macht den Output XSS-sicher. Da `$service_tag` aber als
HTML-`id`-Attribut verwendet wird, waere `sanitize_key()` zusaetzlich
zur strikten Normalisierung sinnvoll (lowercase, nur a-z0-9_-).

**Aktueller Risiko-Status**: Pipeline (`class-dhps-content-pipeline.php`
Z. 133) setzt `service_tag` aus der Service-Registry (hartcodierte
Strings: 'mio', 'lxmio', etc.). KEIN User-Input-Pfad fuehrt zum Template.

**Empfehlung** (optional fuer v0.14.3):
```php
$service_tag = isset( $service_tag ) ? sanitize_key( (string) $service_tag ) : 'mio';
```

**Risiko**: praktisch null - Belt-and-Suspenders.

---

### F-3 (Low): `.screen-reader-text` Global-Klasse fehlt weiterhin

**Severity**: Low
**Kategorie**: A11y (nicht Security im engeren Sinn)
**Datei**: `css/dhps-frontend.css` / `css/dhps-design-tokens.css`

Im Partial werden Labels mit `class="screen-reader-text"` ausgeblendet.
Wenn keine globale `.screen-reader-text { position: absolute; clip: rect(0,0,0,0); ... }`
Definition existiert (z.B. weil Theme keine WP-Standard-Klassen
mitbringt), werden die `<label>`-Elemente sichtbar.

UI-Audit Finding 5 ist aktuell explizit **NICHT** Teil dieser Audit-Spec
(siehe Handover Risiko R6 - separater Quick-Win).

**Empfehlung**: in v0.14.3 oder v0.14.2-Hotfix die a11y-Baseline-Regel
in `dhps-design-tokens.css` ergaenzen.

**Risiko**: Information-Disclosure niedrig (Labels sind funktional,
keine internen Hints).

---

## Verdict

### Pro-GO Argumente

- 0 Critical, 0 High, 0 Medium Findings.
- Saubere `esc_attr`/`esc_html`-Disziplin in Partial + Templates.
- Include-Pfad ist hartcodiert, kein LFI.
- JS-Patches DOM-only, kein eval/innerHTML mit User-Input.
- Server-side Nonce-Check + Sanitization unveraendert wirksam.
- Container-Queries additiv, Fallback intakt.
- LXMIO-Token-Switch konservativ, defensive Fallbacks.
- Component-Helper (`dhps_component`) Registry-whitelist-basiert.
- 3 Trust-Decisions explizit dokumentiert + im Migrationsplan begruendet.

### Contra-GO Argumente

- Keine blockierenden.
- 1 Low + 2 Info als Defense-in-Depth-Empfehlungen ohne Release-Blocker.

### Final-Verdict

**GO**

Empfohlene Follow-up-Aktionen (nicht-blockend):

1. CHANGELOG-v0142.md mit Theme-Override-Migration-Hinweis ergaenzen (F-1).
2. `sanitize_key` auf `$service_tag` im Partial fuer v0.14.3 evaluieren (F-2).
3. `.screen-reader-text` global in v0.14.3 als Quick-Win adressieren (F-3).

---

## Anhang A - Geprueftes File-Set

| # | Pfad | Art | LOC-Aenderung |
|---|------|-----|---------------|
| 1 | `public/views/services/mio/default.php` | Modified | ~7 Zeilen (Skeleton-Slot + Include) |
| 2 | `public/views/services/mio/card.php` | Modified | ~7 Zeilen |
| 3 | `public/views/services/mio/compact.php` | Modified | ~7 Zeilen |
| 4 | `public/views/services/mio/partials/search-form.php` | NEU | 67 |
| 5 | `public/views/services/mio/partials/index.php` | NEU | 2 |
| 6 | `public/js/dhps-mio.js` | Modified | ~40 Zeilen (Live-Search + Skeleton-Toggle) |
| 7 | `css/dhps-frontend.css` | Modified | ~25 Zeilen (LXMIO-Token-Switch + Container-Queries) |

---

## Anhang B - Referenz-Dokumente

- Handover: `.specialist-MIO-1-handover.md`
- Migrationsplan: `docs/architecture/16-MIO-MIGRATION-PLAN-v0142.md`
- Component-Helper: `includes/dhps-component-helpers.php`
- AJAX-Proxy: `includes/class-dhps-ajax-proxy.php`
- Pipeline: `includes/class-dhps-content-pipeline.php`
- SkeletonLoader: `public/views/components/skeleton-loader.php`
