# MIO / LXMIO Migrations-Plan v0.14.2 - Discovery-Refresh

> Status: Discovery-only (keine Code-Aenderungen) | Stand: 2026-05-23
> Owner: Architektur-Team (Specialist B) | Vorlage: 15-MAES-MIGRATION-PLAN-v0141.md
> Foundation: v0.14.1 (8 Components, Alpine 3.14.9, ContentCard `data_attrs`)
> Hybrid-Strategie: dhps-mio.js (1247 LOC) bleibt Vanilla; nur Templates +
> serverseitig erzeugte Render-Stuecke modernisieren.

---

## Mission

MIO ist der **primaere News-Service** und teilt seine Templates mit LXMIO via
`dhps_template_fallbacks` (`lxmio -> mio`). Migration auf v0.14.0-Components
unter folgenden Constraints:

1. **dhps-mio.js Pipeline darf NICHT brechen** - Suchformular, AJAX-Loading,
   client-seitige Pagination, Themenfilter, Print-Funktion, IntersectionObserver
   sind komplex und stabil.
2. **AJAX-Pipeline ist JSON-basiert** (kein Server-Side-HTML-Render-Path) -
   ContentCard kann nur fuer **statische Render-Stuecke** greifen, AJAX-News-
   Items werden weiterhin clientseitig vom JS gebaut.
3. **LXMIO erbt automatisch** via Template-Fallback - Branding muss via
   `.dhps-content-card--service-lxmio` greifen (bereits in v0.14.1
   `dhps-components.css:570` vorhanden).
4. **Steuertermine sind separater Shortcode** `[mio_termine]` mit eigener
   Klasse `DHPS_Steuertermine` + eigenem View-Verzeichnis (`steuertermine/`),
   nicht zu verwechseln mit dem in `mio/default.php` inlinten Block.

---

## Sektion 1 - Status-Quo

### 1.1 File-Inventar

| # | File | Zeilen | Zweck | Komplexitaet |
|---|------|-------:|-------|--------------|
| 1 | `public/views/services/mio/default.php` | 142 | Haupt-Template: Steuertermine + Search + News-Container (AJAX) | M |
| 2 | `public/views/services/mio/card.php` | 133 | wie default + `.dhps-card`-Wrap + `.dhps-filter-bar`-Stub | M |
| 3 | `public/views/services/mio/compact.php` | 116 | inline-Steuertermine + Search + News-Container (compact-Layout) | M |
| 4 | `public/views/steuertermine/default.php` | 42 | Standalone [mio_termine] Default-Grid | S |
| 5 | `public/views/steuertermine/card.php` | 42 | Standalone [mio_termine] Card | S |
| 6 | `public/views/steuertermine/compact.php` | 38 | Standalone [mio_termine] Compact | S |
| 7 | `public/views/steuertermine/inline.php` | 32 | Standalone [mio_termine] Inline | S |
| 8 | `includes/parsers/class-dhps-mio-parser.php` | 263 | DOMDocument-Parser fuer Initial-HTML (tax_dates + search_config + ajax_params) | L (stabil) |
| 9 | `includes/parsers/class-dhps-mio-news-parser.php` | 257 | Sekundaerer Parser fuer AJAX-News-Response (groups + articles + metadata + share_links) | L (stabil) |
| 10 | `includes/class-dhps-steuertermine.php` | 170 | Standalone-Shortcode `[mio_termine]` mit eigenem Render-Path | M |
| 11 | `includes/class-dhps-ajax-proxy.php::handle_news_request` | ~80 | Server-Proxy + Aufruf MIO_News_Parser, gibt **JSON** zurueck (kein HTML!) | M |
| 12 | `public/js/dhps-mio.js` | 1246 | Client-Pipeline: Init, AJAX-Fetch, Render-Funktionen (default/card/compact), Pagination, IntersectionObserver, Print, Filter | XL (Hybrid-Strategie: bleibt) |

Total: **3 Service-Templates + 4 Steuertermine-Templates + 2 Parser + 1 Shortcode-Klasse + 1 AJAX-Proxy-Handler + 1 JS-File**.

### 1.2 Architektur-Diagramm (ASCII)

```
                  +----------------------------+
[mio]/[lxmio] --> | Service-Pipeline (init)    |
                  |  - Parser: MIO_Parser      |
                  |  - Daten: tax_dates,       |
                  |    search_config,          |
                  |    ajax_params             |
                  +-------------+--------------+
                                |
                  +-------------v--------------+
                  | mio/{default|card|compact} |--+
                  | .php (Initial-Render)      |  | enqueue dhps-mio-js
                  +-------------+--------------+  |
                                |                 |
                                | HTML aus PHP:   |
                                |  - Steuertermine|
                                |  - Search-Form  |
                                |  - leerer       |
                                |    News-Conta.  |
                                |    (data-*)     |
                                v                 v
                          +-----+----+      +-----+---------+
                          | Browser  |<-----+ dhps-mio.js    |
                          | DOM      |      | init()         |
                          +-----+----+      +-----+----------+
                                |                 |
                                | fetch POST      |
                                v                 |
                  +-------------+--------------+  |
                  | wp-ajax dhps_load_news     |  |
                  |  - Nonce-Check             |  |
                  |  - OTA-Injection (Server)  |  |
                  |  - API-Call hintergrund... |  |
                  |  - MIO_News_Parser.parse() |  |
                  +-------------+--------------+  |
                                |                 |
                                | wp_send_json_success(parsed)
                                | { groups: [...],
                                |   pagination: {...} }
                                v                 |
                  +-------------+--------------+  |
                  | dhps-mio.js renderNews()   |<-+
                  |  - layout=default/card/    |
                  |    compact Branch          |
                  |  - buildDefaultArticleHtml |
                  |  - buildCardArticleHtml    |
                  |  - buildCompactArticleHtml |
                  |  - container.innerHTML =   |
                  |    htmlString              |
                  +----------------------------+

Standalone:
[mio_termine] -->  DHPS_Steuertermine.render()
                   - reused MIO-Parser fuer tax_dates
                   - render_template() -> steuertermine/*.php
                   (UNABHAENGIG von services/mio/* und JS-Pipeline)
```

### 1.3 LXMIO-Vererbung

- Service-Registry: `lxmio` registriert separat (`dhps_lxmio_ota` Auth-Option).
- Pipeline-Filter: `dhps_template_fallbacks` => `lxmio -> mio`.
- Pipeline ueberschreibt `service_tag = 'lxmio'` nach dem Parsing - im
  Template kommt `$data['service_tag']` als `'lxmio'` an.
- **Folge**: jede MIO-Template-Aenderung wirkt automatisch auch auf LXMIO.
- Branding-Trigger: `.dhps-service--lxmio` Class + `data-service-tag="lxmio"`.

### 1.4 Steuertermine - 2 Code-Pfade

Wichtig: **Steuertermine erscheinen an zwei Stellen** mit **unterschiedlichen
CSS-Klassen**:

| Kontext | Quelle | Wrapper-Klasse | CSS |
|---------|--------|---------------|-----|
| Inline in `[mio]`/`[lxmio]` | `services/mio/default.php:48-79` | `.dhps-tax-dates`, `.dhps-tax-dates__grid` etc. | `dhps-frontend.css` (etwa Z. 700+) |
| Standalone `[mio_termine]` | `steuertermine/default.php` | `.dhps-termine`, `.dhps-termine__grid` etc. | separater CSS-Block |

Diese **Duplikation** ist heute akzeptiert, koennte aber im Rahmen von
v0.14.2 zu **einem** Termine-Partial konsolidiert werden.

---

## Sektion 2 - AJAX-Rendering-Strategie

### 2.1 Wo wird HTML erzeugt?

**Antwort**: HTML fuer News-Items wird **ausschliesslich clientseitig** in
`dhps-mio.js` gebaut (`buildDefaultArticleHtml` Z.563-630,
`buildCardArticleHtml` Z.637-680, `buildCompactArticleHtml` Z.713-755).

Der AJAX-Proxy (`class-dhps-ajax-proxy.php::handle_news_request`) sendet
ausschliesslich **strukturiertes JSON** ueber `wp_send_json_success()`:

```php
$parsed = $this->news_parser->parse( $response->get_body() );
wp_send_json_success( $parsed );
// $parsed = ['groups' => [...], 'pagination' => [...]]
```

### 2.2 Was bedeutet das fuer ContentCard?

**ContentCard kann nicht direkt fuer AJAX-News-Items eingesetzt werden,
ohne JS-Pipeline-Refactor.** Drei Strategien koennen sinnvoll evaluiert werden:

| Strategie | Beschreibung | Aufwand | Risiko | Empfehlung |
|-----------|--------------|--------:|-------:|-----------|
| A | JS bleibt unveraendert, ContentCard nur fuer **statische Render-Stuecke** (Steuertermine, Search-Form, EmptyState, Skeleton) | S | Niedrig | **JA - empfohlen fuer v0.14.2** |
| B | JS-`buildXxxArticleHtml`-Funktionen werden so umgeschrieben, dass sie **identische BEM-Klassen** wie ContentCard erzeugen (`.dhps-content-card--news` etc.) | M | Mittel - dhps-mio.js Pipeline beruehrt | Optional Phase 2 |
| C | Server liefert kompletes HTML als String (statt JSON), JS fuegt nur ein | L | Hoch - Pipeline-Umbau, Cache-Invalidierung, Print-Funktion broken | NEIN |

### 2.3 Initial-Render: was wird heute gerendert?

Aktuell (verifiziert in `mio/default.php`):
- Steuertermine-Block (server-side, voll)
- Such- + Filter-Form (server-side, voll)
- **Leerer News-Container** mit `data-dhps-loading` Spinner und
  `data-*`-Attributen fuer JS-Pipeline-Konfiguration

Es gibt **kein** Initial-News-Item-Rendering im Template. Das UI-Audit-
Finding 1 ist korrekt: "Render-Volumen klein, weil AJAX. Default-Template
rendert nur Suchleiste + leeren News-Container."

### 2.4 ContentCard-Greifpunkte in v0.14.2

| Render-Stueck | Heute | v0.14.2 | Component |
|---------------|-------|---------|-----------|
| Steuertermine (inline + standalone) | `.dhps-tax-dates` Custom-Markup | optional zu Partial konsolidiert | **kein** ContentCard - eigenes Pattern (Sektion 9) |
| Search-Form | Custom-Markup mit Inline-SVG | ggf. FilterBar-Component nutzen | FilterBar (search-Mode) |
| Loading-Spinner | `<span class="dhps-news__spinner">` | **Skeleton-Loader** Component | SkeletonLoader |
| Empty-State | `<p class="dhps-news__empty">` im JS | weiterhin im JS (Pipeline-Constraint) | optional EmptyState-Klassen |
| News-Item Render | JS innerHTML | **Phase B Optional** - BEM-Klassen anpassen | (later) |

---

## Sektion 3 - Component-Coverage-Matrix

| Render-Bereich | ContentList | ContentCard | FilterBar | SkeletonLoader | EmptyState | LazyImage | Coverage |
|----------------|:-----------:|:-----------:|:---------:|:--------------:|:----------:|:---------:|:--------:|
| Steuertermine | OPT (als Liste? Eher Custom-Pattern) | - | - | - | OK | - | **20%** (eigenes Pattern) |
| Search/Filter-Form | - | - | OK (search-Mode) | - | - | - | **80%** (oder weiter Custom) |
| News-Loading-State | - | - | - | OK | - | - | **100%** |
| News-Empty-State | - | - | - | - | OK | - | **80%** (JS-Insertion) |
| News-Items (Default-Layout) | - | OPT B | - | - | - | - | **0%** Phase A / **70%** Phase B |
| News-Items (Card-Layout) | - | OPT B | - | - | - | - | **0%** Phase A / **70%** Phase B |
| News-Items (Compact-Layout) | - | OPT B | - | - | - | - | **0%** Phase A / **40%** Phase B |
| Mehr-laden-Button | - | - | - | OK (Skeleton) | - | - | **70%** |

Legende: OK = sofort einsetzbar, OPT = optional, "Phase A" = v0.14.2 Strategie A
(JS unveraendert), "Phase B" = optionaler spaeterer JS-BEM-Refactor.

### Identifizierte Erweiterungen / neue Patterns

**Notwendig (BLOCKER)**: keine.

**Empfehlenswert**:

1. **SkeletonLoader fuer News-Container**.
   Heute `.dhps-news__loading` mit Spinner. Bei langsamer Verbindung leerer
   Raum. SkeletonLoader-Component (BEM-Modifikator `--list` oder `--card`)
   bringt UX-Win. **Aktion**: Server rendert beim Initial-Load
   `dhps_component( 'skeleton-loader', [...] )` als Default im News-Container,
   JS ersetzt `innerHTML` bei Success/Error. **Risiko**: dhps-mio.js setzt
   `container.innerHTML = '<p class="dhps-news__empty">...'` (Z.346),
   ueberschreibt Skeleton problemlos - **kompatibel**.

2. **"Mehr laden"-Button Skeleton**.
   Quick-Win aus Finding 2. Vor `appendNews()` (Z.438) eine "Loading more"-
   Skeleton-Card einfuegen, die durch das tatsaechliche Append ersetzt wird.

3. **Steuertermine-Partial-Konsolidierung**.
   `services/mio/*.php` rendern Steuertermine inline mit anderen Klassen als
   `steuertermine/*.php`. Konsolidierung zu **einem Partial**
   (`public/views/partials/tax-dates.php`) mit Layout-Token-Variation.
   **Aktion**: optionaler v0.14.2-Refactor; nicht zwingend.

4. **`.screen-reader-text` global definieren**.
   UI-Audit Finding 5 ist **noch nicht** geloest (Memory war zu optimistisch -
   in `dhps-frontend.css` existiert nur `.dhps-service .screen-reader-text:focus`,
   nicht die Basis-`.screen-reader-text { position: absolute; ... }`-Regel).
   **Quick-Win** in `dhps-design-tokens.css` ergaenzen.

5. **`prefers-reduced-motion` fuer den Spinner**.
   `.dhps-news__spinner` rotiert via CSS-Animation. Reduced-Motion-Block
   sollte in `dhps-frontend.css` Spinner-Animation deaktivieren.

6. **Search-Bar mit Live-Search + Debounce** (Finding 4).
   Heute Submit-only. Optional: in `dhps-mio.js` `input`-Event mit 300ms
   Debounce ergaenzen. **Hybrid-konform** (keine Alpine-Migration noetig,
   nur Vanilla-Extension).

7. **Container-Queries fuer Steuertermine-Grid** (Finding 3).
   Heute `@media (max-width: 768px)`. In Elementor-Spalten kollabiert das
   Grid zu spaet. Loesung: `container-type: inline-size` auf
   `.dhps-tax-dates` + `@container`-Queries.

---

## Sektion 4 - Konkrete Implementierungs-Vorschlaege

### 4.1 News-Container: SkeletonLoader statt Spinner (M1)

**Heute**:
```php
<section class="dhps-news" data-dhps-news-container ...>
    <div class="dhps-news__loading" data-dhps-loading>
        <span class="dhps-news__spinner" aria-hidden="true"></span>
        <span class="screen-reader-text">Nachrichten werden geladen...</span>
    </div>
</section>
```

**Neu (Default-Layout)**:
```php
<section class="dhps-news" data-dhps-news-container ...>
    <?php
    // Skeleton bleibt sichtbar, bis dhps-mio.js den Container ueberschreibt.
    dhps_component( 'skeleton-loader', array(
        'variant'    => 'list',     // BEM --list (Akkordeon-Schimmer)
        'count'      => 5,
        'label'      => __( 'Nachrichten werden geladen', 'wp-deubner-hp-services' ),
        'class'      => 'dhps-news__loading',  // BC fuer JS-Detection
    ) );
    ?>
</section>
```

**Card-Layout**: `variant=>'card'`, `count=>$grid_columns*2`.
**Compact-Layout**: `variant=>'list'`, `count=>8` mit kleineren Hoehen.

**JS-Anpassung**: keine - `container.innerHTML = html` ueberschreibt sowieso.

### 4.2 "Mehr laden"-Skeleton (Quick-Win)

In `dhps-mio.js` Z.~770 (`createLoadMoreHtml`):
```js
function createLoadMoreSkeletonHtml() {
    return '<div class="dhps-news__load-more-skeleton" aria-hidden="true">'
        + '<div class="dhps-skeleton-loader dhps-skeleton-loader--card"></div>'
        + '</div>';
}
// Bei Button-Click vor appendNews(): insertAdjacentHTML(...)
```

Server-side noch keine Aktion - reines JS-Snippet.

### 4.3 Branding-Tokens fuer LXMIO (Pflicht)

In `dhps-components.css` ist bereits `.dhps-content-card--service-lxmio`
gehookt. **Aber**: MIO-spezifische Klassen wie `.dhps-news__group-title`,
`.dhps-mio-card-article__title` etc. rendert die JS-Pipeline. LXMIO erbt
heute via:

```css
/* dhps-frontend.css */
.dhps-service--lxmio .dhps-news__group-title { color: var(--dhps-color-recht); }
.dhps-service--lxmio .dhps-search-bar__button { ... }
```

**Empfehlung v0.14.2**: keine Aenderung der bestehenden LXMIO-Klassen,
ggf. **CSS-Custom-Property-Switch** ergaenzen:

```css
.dhps-service--lxmio {
    --dhps-color-primary: var(--dhps-color-recht);
    --dhps-color-primary-hover: var(--dhps-color-recht-hover);
}
```

Damit greifen bestehende Components automatisch ueber Token-Aliasing.

### 4.4 Search-Bar - 3 Optionen

**Option A (konservativ)**: Markup unveraendert, nur Live-Search-Debounce
in `dhps-mio.js` (Z.83-89 erweitern). Aufwand: S, Risiko: Niedrig.

**Option B (Refactor)**: `search-bar`-Partial extrahieren - `mio/default.php`,
`mio/card.php`, `mio/compact.php` haben **identischen Search-Form-Block**
(je ~25 Zeilen, 75 Zeilen Duplikat). Konsolidierung zu
`public/views/partials/search-bar.php`. Aufwand: M, Risiko: Niedrig.

**Option C (FilterBar-Component)**: search-Mode der FilterBar nutzen.
**Nicht empfohlen** in v0.14.2 - FilterBar ist Tag-orientiert, Search ist
Subset; mehr Risiko als Gewinn.

**Empfehlung**: **B + A kombiniert** als Quick-Win.

### 4.5 EmptyState fuer "Keine Nachrichten gefunden"

Heute (`dhps-mio.js` Z.346):
```js
container.innerHTML = '<p class="dhps-news__empty">Keine Nachrichten gefunden.</p>';
```

**Empfehlung**: JS rendert wenig invasiv ein EmptyState-konformes
Markup (in JS-Strings), keine PHP-Komponente noetig (bleibt Hybrid):

```js
container.innerHTML =
    '<div class="dhps-empty-state">' +
        '<div class="dhps-empty-state__icon">[INLINE SVG]</div>' +
        '<h3 class="dhps-empty-state__title">Keine Nachrichten gefunden</h3>' +
        '<p class="dhps-empty-state__message">Bitte passen Sie Suche oder Filter an.</p>' +
    '</div>';
```

CSS aus `dhps-components.css` greift automatisch.

### 4.6 Steuertermine-Konsolidierung (optional)

Heute zwei Klassen-Praefixe (`.dhps-tax-dates*` inline vs `.dhps-termine*`
standalone). Konsolidierung waere ein **separates Vorhaben**, nicht v0.14.2-Pflicht.
**Empfehlung**: in v0.14.2 belassen, Memo fuer v0.15.0 oder "Einheitliches
Datenmodell".

---

## Sektion 5 - Performance-Prognose

> WARNUNG: Discovery-Bytes-Prognose schon einmal schiefgegangen (MAES v0.14.1:
> +176% statt -25%). Daher hier nur **Qualitatives + Magnituden**, kein
> festgenagelter Wert.

### 5.1 Initial-HTML

MIO hat **kein** Render-Volumen-Problem (UI-Audit Score 2/5 - "Render-Volumen
klein, weil AJAX"). Initial-Render ist heute kurz:
- Steuertermine ~3-4 KB (2 Monate x 8-12 Eintraege)
- Search-Form ~1 KB
- News-Container leer (~0.5 KB)
- Total: ~5-6 KB

**Nach v0.14.2 (Strategie A)**:
- Steuertermine unveraendert ~3-4 KB
- Search-Form: bei Partial-Extraktion gleicher Output, ~0 Delta
- SkeletonLoader 5 Items ~2 KB (gross gegenueber heutigem 0.2 KB-Spinner)
- Total: ~7-8 KB

**Erwartete Veraenderung**: **+1.5 bis +2.5 KB** initial. Vertretbar weil:
- Wahrgenommene Performance besser (Skeleton vs blank-mit-Spinner)
- LCP nicht negativ beeinflusst (sichtbarer Content bleibt gleich)
- Cache-Profitabel (Skeleton-CSS in dhps-components.css ist seitenweit
  gecached durch v0.14.0/v0.14.1)

### 5.2 AJAX-Render

**Keine Aenderung in Strategie A.** JS-Pipeline unveraendert, JSON-Response
unveraendert. Wenn Phase B (BEM-Refactor) spaeter kommt: zu erwarten ist
**Wachstum** je Card durch ContentCard-Markup-Overhead (Aktionsfooter,
ARIA-Attribute, SVG-Icons), analog MAES.

### 5.3 JS-Bundle-Effekt

- `dhps-mio.js` bleibt 1247 LOC (gross, aber stabil).
- Alpine ist bereits in v0.14.1 conditional fuer MIO-Shortcodes geladen
  (`dhps_detect_alpine_need()` matcht `mio` + `mio_termine`).
- `dhps-components-alpine.js` ~6 KB (uebernimmt ContentCard-Toggle - hier
  nicht genutzt, schadet aber nicht).
- **Netto-Effekt**: **0 KB JS-Aenderung** in Strategie A.

### 5.4 Erwartete Wartbarkeits-Verbesserung

| Metric | v0.14.1 | v0.14.2 (geplant) | Delta |
|--------|--------:|------------------:|-------|
| Service-Templates `mio/*.php` Zeilen | 391 | ~330 (search-bar-Partial) | -16% |
| Inline-Skeleton-Markup im Template | 4 Zeilen Spinner | dhps_component-Aufruf 1 Zeile | -75% |
| Duplikate (Search-Form 3x) | 75 Zeilen | 25 Zeilen + 1 Include | -67% |
| CSS-Aenderungen | gering | a11y-Baseline + lxmio-Token | additiv |

---

## Sektion 6 - Risiken

| # | Risiko | Wahrscheinlichkeit | Impact | Mitigation |
|---|--------|---:|---:|------------|
| R1 | `dhps-mio.js` bricht, weil Container-Struktur veraendert | Mittel | Hoch | Strategie A: Selectors `[data-dhps-news-container]`, `[data-dhps-loading]`, `[data-dhps-search]` bleiben. JS-Code NICHT anfassen. |
| R2 | SkeletonLoader-Variant unpassend - Card vs List | Niedrig | Mittel | SkeletonLoader-Component akzeptiert `variant`-Prop. Layout-Map: default->list, card->card, compact->list. |
| R3 | LXMIO-Theme-Override fuer Service-Templates bricht | Mittel | Mittel | BC-Notiz im CHANGELOG. Pruefe: gibt es Kunden mit `{theme}/dhps/services/mio/default.php` Overrides? Wenn ja: Migration-Hinweis. |
| R4 | Steuertermine-Konsolidierung bricht `[mio_termine]` | Hoch (wenn versucht) | Hoch | **Nicht in v0.14.2 angehen**. Standalone Shortcode bleibt unveraendert. |
| R5 | Live-Search-Debounce kollidiert mit Submit-Handler | Niedrig | Niedrig | `input`-Event mit Debounce zusaetzlich zum bestehenden Submit-Event. Min 3 Chars als Default. Cancellable via `AbortController`. |
| R6 | `.screen-reader-text` Global-Definition kollidiert mit Theme | Niedrig | Niedrig | Verwende `:where()` oder hohe Spezifitaet `.dhps-service .screen-reader-text { ... }`-Approach (analog v0.13.1). |
| R7 | Alpine wird im News-Section nicht initialisiert (keine `x-data` heute) | Niedrig | Niedrig | Strategie A nutzt nur statisches Skeleton, kein Alpine-State noetig. |
| R8 | Discovery-Prognose Bytes falsch (siehe MAES v0.14.1) | Hoch | Niedrig | Empirie-Smoke-Test vor Specialist-Briefing: 1 Template anpassen, Bytes-Messung, dann breit ausrollen. |
| R9 | CSS-Variablen-Switch in `.dhps-service--lxmio { --dhps-color-primary: ... }` ueberschreibt zu viel | Mittel | Mittel | Token-Switch scope-eng halten: nur `--dhps-color-primary`, NICHT alle Tokens umbiegen. Visual-Regression-Test. |

### Lessons Learned aus v0.14.1 (MAES)
- Discovery-Prognose-Bytes oft falsch -> qualitatives Wording statt Zahlen.
- ContentCard-Bytes-Overhead je Item ~15 KB bei vielen Items.
- Pre-Release-Smoke-Test (1-2 Templates anpassen, Bytes messen) waere
  hilfreich gewesen.

---

## Sektion 7 - Spec-Aufteilung-Empfehlung

### Variante A (empfohlen): 1 grosser Specialist (Hybrid-Modus)

**Begruendung**: MIO ist anders strukturiert als MAES. Bei MAES gab es
9 unabhaengige Sub-Templates - perfekt fuer 3 parallele Specialists.
Bei MIO gibt es nur 3 Service-Templates **mit hohem Duplikat-Grad**
(Search-Form 3x, Steuertermine-Block 2x, News-Container 3x). Parallele
Aufteilung wuerde sich gegenseitig blockieren (alle 3 Files muessten an
gleichen Stellen geaendert werden).

| Spec | Scope | Files In | Files Out | Komplexitaet | Dauer |
|------|-------|---------:|----------:|--------------|-------|
| **MIO-1 Hybrid** | `mio/default.php`, `mio/card.php`, `mio/compact.php` + optional Partial `search-bar.php` + CSS-Branding-Tokens + a11y-Baseline | 3 | 3-4 | **M** | 1 Session |

**Innerhalb dieses Specs**: 4 logische Schritte sequenziell:
1. SkeletonLoader fuer News-Container in 3 Templates (S)
2. Search-Form Partial extrahieren + in 3 Templates includieren (S)
3. LXMIO-Branding-Token-Switch in `dhps-frontend.css`/`dhps-components.css` (S)
4. a11y-Baseline (`.screen-reader-text` + `prefers-reduced-motion`) in
   `dhps-design-tokens.css` (S)

### Variante B (Alternative): 2 Specialists

| Spec | Scope | Vorteil | Nachteil |
|------|-------|---------|----------|
| MIO-T (Templates) | 3 Service-Templates + Search-Partial | Klare Boundary | Wartet auf CSS |
| MIO-CSS | a11y-Baseline + Branding-Token-Switch + Container-Queries-Vorbereitung | Unabhaengig | Riskiert Layout-Drift |

**Empfehlung**: **Variante A**. MIO ist zu klein fuer parallele Specs - der
Koordinations-Overhead uebersteigt den Throughput-Gain.

### Variante C (Quick-Win-Bundle)

Wenn das Team v0.14.2 minimal halten will und v0.14.3 fuer TP/TPT/LP planen:
- **MIO-1** wie oben + Quick-Wins aus Finding 1-5 buendeln.
- Steuertermine + AJAX-Bytes-Refactor erst v0.15.0.

**Empfehlung**: ja, das ist meine Empfehlung.

### Reihenfolge-Empfehlung innerhalb MIO-1

1. **CSS a11y-Baseline + LXMIO-Token-Switch** (Foundation, 30 Min)
2. **SkeletonLoader-Component im News-Container** (3 Templates, 60 Min)
3. **Search-Form Partial** (Konsolidierung, 30 Min)
4. **Live-Search-Debounce in dhps-mio.js** (Optional Vanilla-Extension, 30 Min)
5. **Visual-QA + Bytes-Smoketest** (vor Release, 30 Min)

### Specialist-Briefing-Minimum

- Diesen Plan (Sektion 4.x als Implementierungs-Spec)
- Hybrid-Strategie-Constraint: **dhps-mio.js NICHT umstellen**
- Acceptance: `[mio]`, `[lxmio]`, `[mio_termine]` Shortcodes unveraendert
  funktional, Visual-Regression OK, A11y-Lighthouse >= 95, keine neuen
  Console-Errors, Test mit konfigurierter OTA + Demo-Mode + leerem Cache.

---

## Sektion 8 - LXMIO-Inheritance-Validation

### 8.1 Was wird heute via Filter `dhps_template_fallbacks` geerbt?

Alle 3 Service-Templates (`mio/default.php`, `mio/card.php`, `mio/compact.php`)
+ alle bestehenden Klassen + alle JS-Hook-Selektoren. LXMIO hat **keine
eigenen Templates**.

### 8.2 Was muss LXMIO-spezifisch erhalten bleiben?

| Aspekt | Heute | v0.14.2 | Strategy |
|--------|-------|---------|----------|
| Service-Tag | `'lxmio'` (Pipeline ueberschreibt) | unveraendert | OK |
| Wrapper-Klasse | `.dhps-service--lxmio` | unveraendert | OK |
| Branding-Farbe | Recht-Blau `#0054A6` ueber Klassen-Targeting | Token-Switch via `--dhps-color-primary` | **Verbesserung empfohlen** |
| Auth | `dhps_lxmio_ota` (eigener OTA) | unveraendert | OK |
| AJAX-Service-Tag | `data-service-tag="lxmio"` | unveraendert | OK |
| Sicherheit | dhps-frontend.css selektiert `.dhps-service--lxmio .dhps-...` (Recht-Blau-Override) | bleiben + Token-Switch addiert | additiv |

### 8.3 Reicht ContentCard's `service`-Prop?

**Ja, aber nur fuer Phase B** (JS-Refactor). In Strategie A wird ContentCard
nicht fuer News-Items genutzt - daher `service`-Prop fuer MIO/LXMIO **nicht
relevant**. Fuer Steuertermine wuerde es greifen, aber diese sind heute auch
nicht ContentCard-basiert.

Empfehlung: **CSS-Token-Switch auf `.dhps-service--lxmio`** ist der
sauberere Weg. Beispiel:

```css
.dhps-service--lxmio {
    --dhps-color-primary: var(--dhps-color-recht);
    --dhps-color-primary-hover: var(--dhps-color-recht-hover);
}
```

So profitiert sowohl der News-JS-Render (wenn er CSS-Variables nutzt) als auch
zukuenftige Component-Migration automatisch.

### 8.4 Risiken LXMIO-Inheritance

- **R3 (oben)**: Theme-Override-Bruch nur wenn ein Theme `mio/default.php`
  ueberschreibt. LXMIO selbst hat keinen eigenen Override-Pfad, daher
  doppelt sicher.
- **Token-Override-Bruch**: wenn `dhps-frontend.css` LXMIO-Selektoren
  Recht-Blau **direkt** setzt, ueberschreibt das den Token-Switch.
  Loesung: stattdessen Token-Variablen nutzen.

---

## Sektion 9 - Steuertermine-Strategy

### 9.1 Datenstruktur

```php
$tax_dates = array(
    array(
        'title'    => 'Mai 2026',
        'entries'  => array(
            array( 'date' => '10.05.2026', 'taxes' => ['ESt', 'KSt'] ),
            array( 'date' => '15.05.2026', 'taxes' => ['USt'] ),
        ),
        'footnote' => 'Schonfrist bei Ueberweisung 3 Tage',
    ),
    // 2. Monat
);
```

### 9.2 ContentCard(type='news') passend?

**Nein**. Steuertermine sind:
- **Tabellarisch** (date + taxes pro Eintrag, Liste pro Monat)
- **Keine "Card"** im UX-Sinn (kein Titel-Body-Meta-Pattern)
- **Sehr kompakt** (pro Monat 5-10 Zeilen, Total < 4 KB)

ContentCard wuerde mehr Markup-Overhead bringen als Wertgewinn und die
semantische Struktur (`dl` mit `dt`/`dd`) zerstoeren.

### 9.3 Empfehlung: eigenes Pattern beibehalten

Steuertermine sollten **eigene Klassenfamilie** behalten:
- Inline: `.dhps-tax-dates` (in MIO-Service-Templates)
- Standalone: `.dhps-termine` (in `[mio_termine]`-Templates)

**Optional fuer v0.14.2**: Konsolidierung zu **einem Partial**
`public/views/partials/tax-dates.php` mit Layout-Token (default/card/compact/
inline). Reduziert Code-Duplikat von ~150 Zeilen (4 Standalone + 3 Inline)
auf ~50 Zeilen (1 Partial mit 4 Layout-Branches).

**Empfehlung**: **Konsolidierung NICHT in v0.14.2** angehen. Risiko (R4) zu
hoch, Gewinn zu klein. Memo fuer v0.15.0 unter "Einheitliches Datenmodell".

### 9.4 Container-Queries fuer Termine-Grid (Quick-Win)

UI-Audit Finding 3: `@media (max-width: 768px)` kollabiert zu spaet in
Elementor-Spalten. Quick-Win:

```css
.dhps-tax-dates,
.dhps-termine {
    container-type: inline-size;
    container-name: tax-dates;
}

@container tax-dates (max-width: 480px) {
    .dhps-tax-dates__grid,
    .dhps-termine__grid {
        grid-template-columns: 1fr;
    }
}
```

Aufwand S, Impact M. **Empfohlen** fuer v0.14.2.

---

## Anhang A - Quick-Wins aus UI-Audit (Relevanz v0.14.2)

| Finding | Beschreibung | v0.14.2? | Begruendung |
|---------|--------------|:--------:|-------------|
| 1 | Render-Volumen klein | INFO | kein Problem, Score 2/5 |
| 2 | "Mehr laden" ohne Skeleton | **JA** | Quick-Win, ~10 Zeilen JS-Snippet |
| 3 | Steuertermine 768px Breakpoint | **JA** | Container-Queries, ~15 Zeilen CSS |
| 4 | Search-Input Submit-only | **JA** | Live-Search-Debounce, ~20 Zeilen JS, Hybrid-konform |
| 5 | `.screen-reader-text` global fehlt | **JA** | a11y-Baseline, ~10 Zeilen CSS |

**Alle 4 sind Quick-Wins** und passen ins v0.14.2-Budget.

---

## Anhang B - Spec-Briefing-Minimum (MIO-1)

### Konkret zu liefernde Aenderungen

1. **`mio/default.php`**: News-Container-Inhalt von Spinner auf
   `dhps_component( 'skeleton-loader', ...)` umstellen. Search-Form via
   `include __DIR__ . '/../../partials/search-bar.php'`.

2. **`mio/card.php`**: gleiche Aenderungen, Skeleton-`variant=>'card'`,
   `count=>$grid_columns*2`.

3. **`mio/compact.php`**: gleiche Aenderungen, Skeleton-`variant=>'list'`,
   `count=>8`.

4. **`public/views/partials/search-bar.php`** (NEU): extrahierter Search-
   Form-Block, parametrisiert via `$service_tag`, `$search_config`.

5. **`css/dhps-design-tokens.css`**: a11y-Baseline (`screen-reader-text`,
   `prefers-reduced-motion`-Block fuer Spinner).

6. **`css/dhps-frontend.css`**: Container-Query fuer `.dhps-tax-dates` und
   `.dhps-termine` Grid. LXMIO-Token-Switch (Custom-Properties).

7. **`public/js/dhps-mio.js`**: optional Live-Search-Debounce + "Mehr
   laden"-Skeleton. Minimal-invasive Vanilla-Extension.

### Bewusst NICHT in v0.14.2

- Steuertermine-Partial-Konsolidierung (R4)
- ContentCard fuer News-Items (Hybrid-Strategie haelt JS unveraendert)
- dhps-mio.js Pipeline-Refactor
- FilterBar-Component fuer Search (zu hohes Konflikt-Risiko)

### Acceptance Criteria

- `[mio]`, `[lxmio]`, `[mio_termine]` rendern identisch funktional.
- LXMIO-Recht-Blau-Branding sichtbar (Visual-Regression).
- A11y-Lighthouse >= 95 fuer alle 3 Shortcodes.
- 0 neue JS-Console-Errors / -Warnings.
- News-AJAX-Pipeline laedt + rendert wie heute.
- Smoke-Test mit Bytes-Vergleich Initial-HTML vor/nach (Empirie).

---

## Anhang C - Offene Fragen fuer Team-Review

1. **Live-Search**: Min-Chars-Default (2, 3, 4)?
2. **Skeleton-Count**: Default `data-anzahl`=10, aber Skeleton mit 10
   Items ist optisch viel. Empfehlung 5 fuer default, 8 fuer compact.
3. **LXMIO-Token-Switch**: globaler Switch in `.dhps-service--lxmio` oder
   nur in Components? Empfehlung global, damit JS-rendered MIO-Items auch
   profitieren.
4. **Steuertermine-Konsolidierung**: jetzt oder spaeter? Empfehlung: spaeter
   (v0.15.0 oder Datenmodell-Refactor).
5. **Search-Bar-Partial**: Pfad `public/views/partials/search-bar.php`
   einfuehren - 2 Service-Templates aus dem MIO-Bereich + ggf. spaeter
   andere Services nutzen.

---

## Naechste Releases

- v0.14.2: MIO + LXMIO Migration (dieser Plan)
- v0.14.3: TP + TPT + LP Migration
- v0.14.4: TC Migration (Wrapper-Service, nur Accordion-Anpassung)
- v0.15.0: Einheitliches Datenmodell, Steuertermine-Konsolidierung,
  Phase-B-Optional (dhps-mio.js BEM-Refactor wenn gewuenscht)
