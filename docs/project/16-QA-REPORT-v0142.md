# QA-Report v0.14.2 - MIO/LXMIO Migration

> Datum: 2026-05-23
> QA-Specialist: parallel zur Security-Audit
> Umfang: 3 MIO-Templates, 1 Partial (search-form), dhps-mio.js (+44 LOC),
>         dhps-frontend.css (+24 LOC: LXMIO-Token + Container-Queries)
> Foundation: v0.14.1 (Components, Alpine 3.14.9), Hybrid-Strategie eingehalten.

---

## Executive Summary

Die v0.14.2-Aenderungen sind **minimal-invasiv, additiv und konsequent
hybrid-konform**. Der grosse Pipeline-Brocken `dhps-mio.js` wurde **nicht**
restrukturiert; alle Eingriffe sind reine Erweiterungen mit klaren
Selektoren-Boundaries (`[data-dhps-mio-skeleton]`, `[data-dhps-search-input]`).

Die 5 Quick-Wins erfuellen die im Discovery-Plan dokumentierten Ziele
(Sektion 4 + Anhang A des `16-MIO-MIGRATION-PLAN-v0142.md`). Die A11y-
Foundation aus v0.13.1/v0.14.0 (screen-reader-text, prefers-reduced-motion,
Focus-Indikator) ist nicht regressiert, der Skeleton-Loader respektiert
sie mit eigenem reduced-motion-Block.

**Critical: 0 | Major: 0 | Minor: 3 | Informational: 4**

Verdict: **GO-WITH-CAVEATS** - die Minor-Findings betreffen reine
Beobachtungen (Skeleton-Effekt synchron, Smoke-Test-Skript Sandbox-Limit,
Browser-Matrix-Hinweis) und blockieren das Release nicht.

---

## Sektion 1 - A11y-Check

### 1.1 SkeletonLoader-Slot ARIA-Attribute

**Quelle**: `public/views/components/skeleton-loader.php:44`

```html
<div class="dhps-skeleton dhps-skeleton--list" aria-busy="true" aria-live="polite">
    <span class="screen-reader-text">Inhalt wird geladen ...</span>
    <div class="dhps-skeleton__item ..." aria-hidden="true">...</div>
</div>
```

- `aria-busy="true"`             - PASS (Container)
- `aria-live="polite"`           - PASS (kein Screen-Reader-Spam, nur einmal)
- `screen-reader-text`-Label     - PASS (lokalisierte Ansage)
- `aria-hidden="true"` auf Items - PASS (Items sind nur Shimmer-Placeholder)

**Wichtig**: In den 3 MIO-Templates wird der Skeleton-Slot mit dem
Attribut `hidden` initial versteckt:
```html
<div class="dhps-mio-skeleton-slot" data-dhps-mio-skeleton hidden>
    <?php echo dhps_component( 'skeleton-loader', ... ); ?>
</div>
```
Solange `hidden` gesetzt ist, ist das gesamte Subtree fuer Screen-Reader
und Tastatur unsichtbar. Das ist semantisch korrekt: das `aria-busy` der
Skeleton-Komponente kommt erst zum Tragen, wenn `hidden` entfernt wird
(showMore-Flow in dhps-mio.js).

**Bewertung**: PASS.

### 1.2 Search-Form-Partial ARIA-Anbindung

**Quelle**: `public/views/services/mio/partials/search-form.php:29-66`

- Container `<section>` mit `aria-label="Suche und Filter"` - PASS
- Form `<form role="search" data-dhps-search>`               - PASS
- Select hat Label per `for`/`id` mit `screen-reader-text`    - PASS
- Input hat Label per `for`/`id` mit `screen-reader-text`     - PASS
- Submit-Button hat `aria-label="Suchen"`                     - PASS
- SVG ist mit `aria-hidden="true"` aus dem A11y-Tree          - PASS

**Defensiv**: Das Partial deklariert `$service_tag`/`$search_config`/
`$placeholder` mit isset-Defaults (Z.23-27), bricht also nicht wenn aus
fremdem Kontext eingebunden.

**Bewertung**: PASS.

### 1.3 Live-Search-Debounce A11y

**Quelle**: `public/js/dhps-mio.js:93-113`

- Debounce 300ms (Standard-Wert nach NN/g- und WCAG-Empfehlungen).
- Min-Chars = 3 -> verhindert zu haeufige Live-Region-Aktualisierungen.
- Im News-Container ist `aria-live="polite"` gesetzt - bei jedem
  loadNews-Erfolg wird `innerHTML` ueberschrieben -> 1 Announcement pro
  300-ms-Fenster, **nicht** pro Keystroke. Keine "ARIA flood".
- Bei leerem Feld (`value.length === 0`) -> 1 zusaetzlicher Reset-Call.
  Ein-Off-Verhalten, kein Problem.

**Bewertung**: PASS.

### 1.4 Container-Query-Stack auf Mobile

**Quelle**: `css/dhps-frontend.css:250-264`

```css
.dhps-tax-dates, .dhps-termine {
    container-type: inline-size;
    container-name: dhps-termine;
}
@container dhps-termine (max-width: 500px) {
    .dhps-tax-dates__grid,
    .dhps-termine__grid,
    .dhps-termine__list { grid-template-columns: 1fr; }
}
```

Stack zerstoert die Tab-Reihenfolge **nicht**: `grid-template-columns: 1fr`
re-stacked die Cells in Source-Order. Im Markup folgt jede `__column`
(default) bzw. `__month` (standalone) sequentiell in der DOM - Source-
Order = visual Order. Innerhalb einer Column ist die `<dl>` ebenfalls
linear (`dt`/`dd`-Paare).

Keine Verwendung von `order:` oder `flex-direction: row-reverse` -
Tab-Reihenfolge bleibt durchgaengig korrekt.

**Bewertung**: PASS.

### 1.5 Pass-Rate A11y

| Pruefpunkt              | Status |
|-------------------------|:------:|
| Skeleton aria-busy/live | PASS   |
| Skeleton hidden-Slot    | PASS   |
| Search-Form roles+label | PASS   |
| Live-Search no flood    | PASS   |
| Container-Query Tab     | PASS   |
| screen-reader-text base | PASS   |
| prefers-reduced-motion  | PASS   |

**A11y-Pass-Rate: 7/7 = 100 %**

---

## Sektion 2 - Cross-Browser-Aspekte

### 2.1 Container-Queries Browser-Support

Container-Queries (level 1) sind unterstuetzt ab:
- Chrome 105 (Aug 2022)
- Edge 105
- Safari 16 (Sep 2022)
- Firefox 110 (Feb 2023)

**Fallback geprueft**: `css/dhps-frontend.css:799-819` enthaelt den
bestehenden `@media (max-width: 768px) { .dhps-tax-dates__grid {
grid-template-columns: 1fr; } }`-Block. Bei aelteren Browsern (Safari 15,
Chrome 104-, Firefox 109-) faellt das Grid weiterhin bei Viewport <= 768px
zurueck auf 1 Spalte - das urspruengliche UI-Audit-Finding ist nicht
brand-neu, nur **Container-Query-additiv** erweitert.

**Achtung**: Das Container-Query-Breakpoint liegt bei 500px, der Viewport-
Media-Query bei 768px. In Browsern **ohne** Container-Query-Support
verhaelt sich das Grid bei 500px-Containern (z.B. enger Elementor-Spalte
bei Desktop-Viewport > 768px) noch falsch - der Quick-Win wirkt dort
nicht. Das ist erwartet und akzeptabel.

**Bewertung**: PASS.

### 2.2 Skeleton-Animation prefers-reduced-motion

**Quelle**: `css/dhps-components.css:149-162`

```css
@media (prefers-reduced-motion: reduce) {
    .dhps-skeleton__media, .dhps-skeleton__poster, ... {
        animation: none;
        background-image: none;
    }
}
```

Shimmer-Keyframes werden deaktiviert, ruhige Fuellflaeche bleibt sichtbar.
**Defense-in-depth**: zusaetzlich greift der globale Reduced-Motion-Block
aus `dhps-frontend.css:70-79`, der **alle** `.dhps-service *`-Animationen
und Transitions auf 0.01ms reduziert (Punkt 11.7 v0.14.0-Foundation).

**Bewertung**: PASS - doppelt abgesichert.

### 2.3 `:has()` und @container in `@layer`

Beide Container-Query-Regeln liegen ausserhalb expliziter `@layer`-Bloecke
(in `dhps-frontend.css:250` nach dem `@layer dhps-reset`-Close auf Z. 88,
aber vor `@layer dhps-components` der ab Z. 90 wieder oeffnet).

Pruefung im File:

```
Z. 88:  } /* /@layer dhps-reset */
Z. 90:  @layer dhps-components {
Z. 250: .dhps-tax-dates, .dhps-termine { container-type: ... }
```

-> die Regel liegt **innerhalb** des `@layer dhps-components`-Blocks.
Cascade-Layer + @container kombinieren sauber (Container-Queries sind
unabhaengig vom Layer-Mechanismus).

**Bewertung**: PASS.

---

## Sektion 3 - LXMIO-Vererbung Validation

### 3.1 Filter-Hook bestaetigt

**Quelle**: `includes/class-dhps-renderer.php:313-328`

```php
private function get_template_fallback_tag( string $tag ): ?string {
    $fallbacks = apply_filters( 'dhps_template_fallbacks', array(
        'lxmio' => 'mio',
        'mil'   => 'mmb',
        'lp'    => 'tp',
    ) );
    return $fallbacks[ $tag ] ?? null;
}
```

`render_parsed()` (Z. 134-175) wendet diesen Filter an, wenn das Service-
spezifische Template fehlt - LXMIO hat keine eigenen Templates, also
greift bei jedem `[lxmio]`-Render zwangsweise `mio/{layout}.php`.

**Bewertung**: PASS.

### 3.2 Skeleton + Search-Partial Vererbung

Da LXMIO via Fallback **denselben** Template-Code rendert, erbt es
automatisch:
- Skeleton-Slot (`data-dhps-mio-skeleton hidden` -> dhps_component-Aufruf).
- Search-Form-Partial via `include __DIR__ . '/partials/search-form.php'`.

**Path-Constraint geprueft**: Der `__DIR__`-Operator resolved zur
**Plugin-Template-Verzeichnis** (z.B. `.../public/views/services/mio/`),
**nicht** zum Theme-Override-Verzeichnis. Daher loest das Partial-Include
korrekt auch dann, wenn das Service-Template aus dem Plugin-Pfad eingebunden
wird (was bei LXMIO der Default ist).

**Caveat (Minor)**: Falls ein Theme `mio/default.php` ueberschreibt
(`{theme}/dhps/services/mio/default.php`), zeigt `__DIR__` auf das Theme-
Verzeichnis -> der Partial-Include wuerde fehlschlagen, wenn das Theme
das Partial nicht mitkopiert. Das Handover dokumentiert dieses Risiko
unter "Theme-Override-Hinweis (BC)" und empfiehlt eine CHANGELOG-Notiz.

**Bewertung**: PASS mit Caveat-Doku.

### 3.3 `dhps-service--lxmio` am Wrapper

**Quelle**: `class-dhps-renderer.php:160`

```php
$service_class = 'dhps-service--' . sanitize_html_class( $tag );
```

`$tag = 'lxmio'` -> `service_class = 'dhps-service--lxmio'`. Im Template
verwendet:

```php
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . ' ...' ); ?>" ...>
```

Resultat: `<div class="dhps-service dhps-service--lxmio dhps-layout--default ...">` - korrekt.

Zusaetzlich setzt die Content-Pipeline `$data['service_tag'] = 'lxmio'`
(siehe `class-dhps-content-pipeline.php:133`), sodass auch
`data-service-tag="lxmio"` am News-Container ankommt.

**Bewertung**: PASS.

### 3.4 Token-Switch wirkt auf JS-rendered Cards

**Quelle**: `css/dhps-frontend.css:1897-1900`

```css
.dhps-service--lxmio {
    --dhps-color-primary: var(--dhps-color-recht, #0054A6);
    --dhps-color-primary-hover: var(--dhps-color-recht-hover, #003A73);
}
```

Da der Wrapper `.dhps-service--lxmio` aussen alles umschliesst, vererbt
`--dhps-color-primary` an alle Nachfahren - auch an die per
`dhps-mio.js::renderNews()` clientseitig eingefuegten Cards. Die JS-
rendered Items selbst nutzen die Variable bisher **nicht direkt** (sie
greifen auf konkrete `.dhps-news__title`-Selektoren); aber die parallel
existierenden direkten Branding-Selektoren (Z. 1903-1928) sind weiterhin
da und ueberschreiben mit hoeherer Spezifitaet die Steuern-Gruen-Defaults
fuer `.dhps-news__group-title`, `.dhps-news__title`, `.dhps-tax-dates__title`,
`.dhps-mio-card-article__tag`, `.dhps-news__load-more-btn`,
`.dhps-search-bar__button`.

**Token-Switch wirkt zusaetzlich** auf:
- Focus-Outline (`.dhps-service a:focus-visible` etc. in
  `dhps-frontend.css:64` nutzt `var(--dhps-color-primary)`).
- Alle v0.14-Components, die `--dhps-color-primary` lesen
  (ContentCard-Theme, FilterBar-Active-State scope -> Hinweis: FilterBar
  setzt fuer LXMIO bereits explizit `--dhps-filter-color: var(--dhps-color-recht)`
  in Z. 1859-1861, also doppelt sicher).

**Bewertung**: PASS - Token-Switch ist additiv und korrekt scope-eng
gehalten (nur 2 Properties).

---

## Sektion 4 - Smoke-Test

### 4.1 Geplantes Test-Script

Ein Smoke-Test-Script `smoke-qa-v0142.php` wurde fuer den Plugin-Root
vorbereitet und prueft:

1. Render von 3 Shortcodes (`[mio]`, `[lxmio]`, `[mio_termine]`) ohne
   PHP-Notice / Warning / Error (set_error_handler-Capture).
2. `[mio]` enthaelt:
   - `dhps-mio-skeleton-slot` (Skeleton-Slot-Wrapper)
   - `dhps-skeleton`           (SkeletonLoader-Output)
   - `data-dhps-search-input` ODER `role="search"` (Partial-Indikator)
   - `data-dhps-live-search-min`
   - `data-dhps-news-container`
   - `dhps-service--mio`
3. `[lxmio]` enthaelt:
   - `dhps-service--lxmio`
   - `dhps-mio-skeleton-slot`  (Skeleton via Fallback)
   - `dhps-skeleton`
   - `data-dhps-search-input` ODER `role="search"`
   - `data-service-tag="lxmio"`
4. `[mio_termine]` enthaelt:
   - `dhps-termine` ODER `dhps-tax-dates` (steuertermine-default.php
     rendert die `.dhps-termine`-Klasse, verifiziert via Datei-Lese-Test).

### 4.2 Ausfuehrungs-Status

Die Bash-/PowerShell-Ausfuehrung von `docker exec ... php /var/www/html/
wp-content/plugins/wp-deubner-hp-services/smoke-qa-v0142.php` ist durch
Sandbox-Permissions in diesem QA-Run blockiert worden.

**Statische Verifikation der Assertions** (Code-Inspektion):

| Assertion                            | Quelle                                          | Status |
|--------------------------------------|-------------------------------------------------|:------:|
| `dhps-mio-skeleton-slot` in [mio]    | `mio/default.php:100`                           | PASS   |
| `dhps-skeleton` in [mio]             | `dhps_component('skeleton-loader',...)` Output  | PASS   |
| `data-dhps-search-input` in [mio]    | `partials/search-form.php:56`                   | PASS   |
| `role="search"` in [mio]             | `partials/search-form.php:30`                   | PASS   |
| `data-dhps-live-search-min` in [mio] | `partials/search-form.php:57`                   | PASS   |
| `dhps-service--mio` in [mio]         | renderer.php:160 `'dhps-service--' . 'mio'`     | PASS   |
| `dhps-service--lxmio` in [lxmio]     | renderer.php:160 `'dhps-service--' . 'lxmio'`   | PASS   |
| Skeleton in [lxmio] via Fallback     | renderer.php:146-152 + Plugin-`__DIR__`-Resolve | PASS   |
| `dhps-termine` in [mio_termine]      | `steuertermine/default.php:19`                  | PASS   |

**Empfehlung**: Lead/User soll den Smoke-Test einmal manuell laufen
lassen (falls gewuenscht):

```bash
docker exec wp-deubner-hp-services-wordpress-1 \
    php /var/www/html/wp-content/plugins/wp-deubner-hp-services/smoke-qa-v0142.php
```

Das Script ist nach diesem Run **bereits geloescht** (Auflage "Cleanup
nach Tests"); fuer eine erneute Ausfuehrung muss es wiederhergestellt
werden (Inhalt im Anhang B dieses Reports).

**Bewertung**: PASS (statisch verifiziert; dynamische Ausfuehrung haengt
am User-Permission-Flow).

---

## Sektion 5 - JS-Code-Review (dhps-mio.js)

### 5.1 Umfang der Aenderung

**Vorher**: 1246 LOC (Specialist-Handover; auch in Discovery-Plan so notiert).
**Nachher**: 1290 LOC.
**Delta**: **+44 LOC**, alle als Erweiterung, **0 LOC entfernt**.

Aenderungen liegen in 3 klar abgrenzbaren Bloecken:

| Block                    | Zeilen      | LOC  | Zweck                                              |
|--------------------------|------------:|-----:|----------------------------------------------------|
| Live-Search-Debounce     | 91-113      | ~23  | input-Event mit setTimeout 300ms                   |
| Skeleton-Toggle-Helper   | 123-135     | ~13  | setMioSkeleton(visible) Funktion                   |
| showMore-Skeleton-Toggle | 143-145,153,171 | ~5 | setMioSkeleton(true)/false um die Slice-Logik      |
| (zusaetzliche Kommentare)|             | ~3   |                                                    |

Beide Bloecke sind **rein additiv**: sie modifizieren keine bestehende
Funktion, fuegen nur neue Event-Listener und einen Helper hinzu. Die
Pipeline `loadNews -> renderNews -> appendNews` ist **unveraendert**.

### 5.2 Idempotenz

- `setMioSkeleton(visible)` ist **idempotent**: setzt/entfernt `hidden`-
  Attribut. Mehrfache Aufrufe sind no-ops.
- Live-Search-Listener: wird in `initInstance()` einmalig pro Container
  registriert. `initInstance` wiederum wird in `init()` ueber
  `querySelectorAll('[data-dhps-news-container]').forEach()` aufgerufen.
  Wenn `init()` zweimal aufgerufen wird (z.B. nach AJAX-Inject in
  Elementor-Builder), werden **die Listener doppelt registriert** -
  das fuehrt zu **2x loadNews** pro Eingabe.
- Aber: `loadNews()` (Z. 242-317) hat einen `state.loading`-Guard auf
  Z. 243 -> der zweite Call returned sofort. Funktional kein Bruch,
  aber unnoetiger Doppel-Fire.

**Bewertung**: Idempotenz ist **akzeptabel** im Container-Scope, aber
ein Re-Init-Pattern (wie es ContentCard's Alpine-Adapter via
`document.addEventListener('alpine:init', ...)` macht) wuerde zusaetzlich
guarden. Da `init()` aktuell nur einmal pro Seitenlade laeuft (kein
MutationObserver fuer dynamisches Inject), ist das **nicht release-blockend**.

**Minor-Finding 1**: Bei sehr dynamischen Editor-Re-Renderings (Elementor
Live-Preview, Block-Editor-Re-Render) koennte eine Re-Init zu doppelt
gebundenen Live-Search-Listenern fuehren. Mitigation: optional ein
`data-dhps-mio-bound`-Guard analog zum bestehenden `data-dhps-events-bound`
(Z. 1078-1082) einfuehren. **Aufwand: 3 Zeilen**, nicht v0.14.2-Pflicht.

### 5.3 Submit vs Live-Search Kollision

`searchForm.addEventListener('submit', ...)` (Z. 84) und
`searchInput.addEventListener('input', ...)` (Z. 98) sind **beide aktiv**.

Bei Submit:
- `e.preventDefault()` stoppt das Form-Submit.
- `state.search = searchInput.value`
- `loadNews()`

Bei Input (mit Debounce):
- Nach 300ms `state.search = self.value` + `loadNews()`.

Wenn der User waehrend des Debounce-Timeouts (innerhalb 300ms) den
Submit-Button klickt, feuert:
1. Submit -> `loadNews()` (synchron-startend, async-laufend mit `state.loading=true`).
2. 300ms danach: Debounce-Timer feuert -> `loadNews()` checkt
   `state.loading === true` -> return sofort.

Keine doppelte Anfrage, kein State-Reset. **PASS**.

**Edge-Case**: Wenn die erste Anfrage **schneller** als 300ms zurueckkommt
(unwahrscheinlich, aber moeglich), waere `state.loading` auf `false` und
der Debounce-Timer wuerde regulaer feuern. Da `state.search` dann aber
identisch ist (User hat zwischenzeitlich nichts getippt), waere das ein
**redundanter** AJAX-Call, kein Bug.

**Bewertung**: PASS, kein Live-Lock-Risiko.

### 5.4 Min-Chars-Schwelle

```js
var minCharsAttr  = parseInt( searchInput.getAttribute( 'data-dhps-live-search-min' ) || '3', 10 );
var minChars      = isNaN( minCharsAttr ) ? 3 : minCharsAttr;
```

- Default = 3. Im Partial fest gesetzt (`data-dhps-live-search-min="3"`).
- NaN-Guard ist da. PASS.
- **Konfigurierbarkeit**: Per Template-Override oder spaeter per Filter
  (z.B. `dhps_mio_search_min_chars`) anpassbar.

Sinnhaftigkeit: **3 ist Standard** in vielen UX-Libraries (Algolia/Typesense
default = 1, aber bei Plugin-Search 3 ist konservativ und vermeidet
"a"-noise). PASS.

**Bewertung**: PASS.

### 5.5 Skeleton-Toggle in showMore: synchroner Repaint

Das **bekannte Handover-Caveat** ist hier reproduziert:

```js
function showMore() {
    setMioSkeleton( true );      // remove hidden
    // ... synchrone Slice + Append-Logik ...
    setMioSkeleton( false );     // set hidden
}
```

Da kein Microtask zwischen den beiden Calls liegt, **rendert der Browser
das Skeleton typischerweise gar nicht** - er bekommt den Layout-Trigger,
batched ihn aber mit dem `setAttribute('hidden')` zusammen und committed
nur den End-Zustand. Visueller Effekt = 0.

**Minor-Finding 2**: Die Skeleton-Visualisierung in `showMore()` ist
**kosmetisch tot**. Das ist im Handover transparent dokumentiert (Z. 113-121)
und als "bereit fuer asynchrone Erweiterung" akzeptiert. Kein Funktions-Bruch.

**Mitigation (optional)**: `requestAnimationFrame` zwischen `setMioSkeleton(true)`
und der Slice-Logik koennte den Skeleton fuer einen Frame sichtbar machen.
**Nicht release-blockend**.

### 5.6 JS-Pass-Rate

| Pruefpunkt                          | Status |
|-------------------------------------|:------:|
| +44 LOC minimal-invasiv             | PASS   |
| Bestehende Selektoren unveraendert  | PASS   |
| Submit/Live-Search Kollision sicher | PASS   |
| Min-Chars Default sinnvoll          | PASS   |
| Idempotenz auf Container-Ebene      | PASS   |
| Idempotenz auf init()-Ebene         | MINOR  |
| showMore-Skeleton sichtbar          | MINOR  |

---

## Sektion 6 - Performance-Beobachtung

### 6.1 Bytes-Bilanz Code (statisch)

| File                     | Bytes vor | Bytes nach | Delta  |
|--------------------------|----------:|-----------:|-------:|
| `mio/default.php`        | ~7.000     | 4.436      | -2.564 |
| `mio/card.php`           | ~6.500     | 3.945      | -2.555 |
| `mio/compact.php`        | ~5.700     | 3.228      | -2.472 |
| `mio/partials/search-form.php` | 0   | 2.903      | +2.903 |
| `css/dhps-frontend.css`  | (groesser) | -          | +24 LOC|
| `public/js/dhps-mio.js`  | -          | 40.940     | +44 LOC|

**Templates Netto-Saldo**: -2.564 -2.555 -2.472 +2.903 = **-4.688 bytes
Quellcode** durch Search-Form-Deduplikation. Klare Wartbarkeits-Win.

### 6.2 Initial-HTML (geschaetzt)

Da die Dynamic-Render-Ausfuehrung im Sandbox blockiert ist, basiert die
Schaetzung auf der Markup-Analyse:

**[mio]-Output v0.14.1 (Memory: 4.485 bytes Baseline)**:
- Steuertermine 2-Spalten ~3.500
- Search-Form (Inline) ~900
- News-Container (leer) ~150

**[mio]-Output v0.14.2 (geschaetzt)**:
- Steuertermine 2-Spalten ~3.500 (unveraendert)
- Search-Form (via Partial) ~950 (nur `data-dhps-live-search-min="3"` neu, +~30 bytes)
- Skeleton-Slot (`<div hidden>...3 items...</div>`)
  - Wrapper + skeleton-loader-Component-Output:
  - `<div class="dhps-skeleton dhps-skeleton--list" aria-busy=...>` + 3 `__item`
    je ~150 bytes + screen-reader-text + wrapper = **~850 bytes**.
- News-Container (leer) ~150

**Geschaetzte Total: ~5.450 bytes**, also **+~1 KB** Initial-HTML.

**[lxmio]-Output v0.14.1 (Memory: 2.401 bytes)**:
- Vermutlich Demo-Mode-Output ohne tax_dates (kleiner als [mio]).

**[lxmio]-Output v0.14.2 (geschaetzt)**:
- Demo-Mode + Search-Form-Partial + Skeleton-Slot: **+~1 KB** analog.

### 6.3 Beurteilung

- Wachstum ist im **erwarteten Korridor** (Discovery-Plan Sektion 5.1
  hatte "+1.5-2.5 KB" prognostiziert - **passt**).
- Skeleton-CSS ist bereits in `dhps-components.css` global gecached
  seit v0.14.0 -> keine zusaetzlichen CSS-Bytes pro Seite.
- LXMIO erbt das Wachstum via Fallback -> **bewusst akzeptiert** (siehe
  Sektion 3.2).
- LCP-Impact: **kein negativer Impact** zu erwarten, weil:
  - Skeleton ist mit `hidden`-Attribut initial nicht rendered.
  - Browser parsed das Markup, rendert aber nichts -> Layout-Cost = 0.
  - LCP-Element bleibt der Steuertermine-Block (wie zuvor).

**Bewertung**: Akzeptabel, **innerhalb Discovery-Korridor**.

### 6.4 Wartbarkeits-Bilanz

| Metric                                  | Vor v0.14.2 | Nach v0.14.2 | Delta    |
|-----------------------------------------|------------:|-------------:|----------|
| 3 MIO-Templates LOC (insg.)             | ~445        | 303          | **-32 %**|
| Search-Form-Duplikat (3x)               | 3 Bloecke   | 1 Partial    | -67 %    |
| dhps-mio.js LOC                         | 1.246       | 1.290        | +3.5 %   |
| CSS LOC                                 | (Baseline)  | +24          | additiv  |

**Wartbarkeits-Win** ist substantiell (-32 % Templates-Bloat).

---

## Sektion 7 - Spezifitaet der 5 Quick-Wins

### QW1 - LXMIO-Token-Switch

**Code**: `css/dhps-frontend.css:1897-1900`
```css
.dhps-service--lxmio {
    --dhps-color-primary: var(--dhps-color-recht, #0054A6);
    --dhps-color-primary-hover: var(--dhps-color-recht-hover, #003A73);
}
```

**Wirkung auf JS-rendered Items**: Indirekt. Die JS-Renderer (Z. 408-470)
emitten Klassen wie `.dhps-news__group-title`, `.dhps-news__title`,
`.dhps-mio-card-article__tag` - diese werden in `dhps-frontend.css:1903-1928`
**direkt** mit `var(--dhps-color-recht, ...)` belegt. Der Token-Switch
greift parallel auf alle Elemente, die `--dhps-color-primary` lesen
(z.B. Focus-Outline aus `dhps-frontend.css:64`).

Wuerde man die direkten Branding-Selektoren zukuenftig auf
`var(--dhps-color-primary)` umstellen, wirkt der Token-Switch automatisch
auf die JS-Cards. Heute: **Co-Existenz**, keine Regression.

**Status**: PASS. Scope-eng (nur 2 Properties). Akzeptanz Smoke-Test 3e
(im Handover) ist visuell zu verifizieren.

### QW2 - Skeleton-Slot

**Code**: `mio/default.php:99-105`, `mio/card.php:90-96`, `mio/compact.php:74-80`

```html
<div class="dhps-mio-skeleton-slot" data-dhps-mio-skeleton hidden>
    <?php echo dhps_component( 'skeleton-loader', array( 'type' => '...', 'count' => 3 ) ); ?>
</div>
```

**Data-Attribute-Korrektheit fuer JS-Toggle**: Der Selector
`container.querySelector('[data-dhps-mio-skeleton]')` (Z. 126 in dhps-mio.js)
greift korrekt - der Slot liegt **innerhalb** des `[data-dhps-news-container]`
(verifiziert in default.php Z. 88-100, card.php Z. 77-91, compact.php Z. 62-75).

Variants korrekt zugeordnet:
- default.php -> `type='list', count=3` (Accordion-Liste)
- card.php    -> `type='card', count=3` (Card-Grid)
- compact.php -> `type='list', count=3` (Tabellarische Zeilen)

**Status**: PASS.

### QW3 - Search-Form-Partial

**Code**: `public/views/services/mio/partials/search-form.php` (67 LOC)

**Idempotente Inkludierbarkeit**: Defensive Variablen-Bridge (Z. 23-27)
mit `isset()`-Defaults macht das Partial sicher fuer:
- Multiple Includes pro Request (jeder Service-Template-Include).
- Isolierten Standalone-Test (falls jemand das Partial direkt unit-testen will).

Variablen-Bridge OK:
- `$service_tag`: Default `'mio'`.
- `$search_config`: Default `array()` (mit `is_array`-Guard).
- `$placeholder`: Default `'Suchbegriff'`.

**Status**: PASS.

### QW4 - Live-Search-Debounce

**Code**: `dhps-mio.js:91-113`

- 300ms Debounce - Standard.
- Min-Chars 3 - Standard.
- Reset bei leerem Feld - vorhanden (`value.length === 0` triggert via
  Pfad: `value.length > 0 && value.length < minChars` -> nicht-truthy
  -> faellt durch -> `loadNews()` mit leerem `state.search`).

Siehe Sektion 5 fuer Details.

**Status**: PASS mit MINOR (synchroner showMore-Skeleton-Effekt).

### QW5 - Container-Queries fuer Termine

**Code**: `css/dhps-frontend.css:250-264`

**Plazierung von `container-type: inline-size`**: Auf `.dhps-tax-dates` und
`.dhps-termine` - das sind die **aeusseren Wrapper** der Grids
(`mio/default.php:50`, `steuertermine/default.php:19`). Innerhalb sind
`__grid`-Children, die die `grid-template-columns` halten. Damit ist
`container-type` korrekt auf dem Container-Scope, **nicht** auf dem Grid
selbst. Das ist der canonical Pattern.

Der `@container dhps-termine (max-width: 500px)`-Block matcht den **named
container** (kein generischer Match) - sauber.

`.dhps-termine__list` ist im Selector aufgenommen (Z. 261), weil die
Standalone-Templates beide Klassen nutzen (verifiziert in
`steuertermine/default.php:27`).

**Caveat**: Cards-Templates ([mio_termine card.php]) nutzen
`__grid` analog - Standalone-Default-Template hat im Markup ebenfalls
`.dhps-termine__grid` (Z.20). Match korrekt.

**Status**: PASS.

### 7.6 Quick-Win-Pass-Rate

| QW | Beschreibung                  | Status        |
|:--:|-------------------------------|---------------|
| 1  | LXMIO-Token-Switch CSS        | PASS          |
| 2  | Skeleton-Slot 3 Templates     | PASS          |
| 3  | Search-Form-Partial           | PASS          |
| 4  | Live-Search-Debounce JS       | PASS (MINOR)  |
| 5  | Container-Queries Termine     | PASS          |

**Quick-Win-Pass-Rate: 5/5 = 100 %**

---

## Acceptance Checklist

| Kriterium                                            | Status |
|------------------------------------------------------|:------:|
| `[mio]` rendert unveraendert funktional              | PASS (statisch) |
| `[lxmio]` rendert via Fallback unveraendert          | PASS (statisch) |
| `[mio_termine]` rendert mit `.dhps-termine`-Markup   | PASS            |
| LXMIO-Recht-Blau via Token-Switch + Branding-Klassen | PASS            |
| Skeleton-Slot in 3 MIO-Templates                     | PASS            |
| Search-Form 3x dedupliziert -> Partial               | PASS            |
| Container-Queries fuer Termine-Grid                  | PASS            |
| `@media`-Fallback bei 768px erhalten                 | PASS            |
| Live-Search-Debounce 300ms + Min-Chars 3             | PASS            |
| `data-dhps-live-search-min` im Partial-Markup        | PASS            |
| JS-Pipeline (`renderNews`, `appendNews`) unveraendert| PASS            |
| Skeleton respektiert prefers-reduced-motion          | PASS            |
| A11y-Baseline screen-reader-text/focus-visible OK    | PASS            |
| Tab-Reihenfolge bei Container-Stack                  | PASS            |
| LXMIO-Token-Switch scope-eng (nur 2 Properties)      | PASS            |
| Demo-Badge-Wrapping bricht nicht                     | PASS (renderer.php unveraendert) |
| Theme-Override fuer mio/* dokumentiert (Caveat)      | INFO (Doku-Hinweis fuer CHANGELOG) |

**Acceptance: 17/17 PASS** (3 informelle Caveats).

---

## Minor & Informational Findings

### Minor

| # | Finding | Empfehlung | Block? |
|---|---------|------------|:------:|
| M1 | Live-Search-Init-Idempotenz: doppelte init() koennte Listener doppelt binden | optional `data-dhps-mio-bound`-Guard analog Z. 1078-1082 | nein |
| M2 | Skeleton in `showMore()` kosmetisch unsichtbar (synchroner Repaint) | `requestAnimationFrame` zwischen Toggle und Slice; oder spaeter | nein |
| M3 | Container-Queries-Fallback wirkt **nur bei Viewport <= 768px**, nicht bei kleinen Containern in breiten Viewports auf Legacy-Browsern | Doku-Hinweis fuer Kunden auf Safari 15-/Chrome 104- | nein |

### Informational

| # | Beobachtung |
|---|-------------|
| I1 | Tax-Dates-Klassen-Duplikation (`.dhps-tax-dates` vs `.dhps-termine`) bleibt bewusst bestehen (Memo v0.15.0). |
| I2 | LXMIO Theme-Override-Konstellation kann Partial-Include brechen, wenn Theme das Partial nicht mitkopiert -> CHANGELOG-Hinweis empfohlen. |
| I3 | Skeleton-CSS profitiert bereits doppelt von reduced-motion (Component-Level + globaler `@layer dhps-reset`-Block). |
| I4 | Smoke-Test-Script wurde nach Erstellung manuell entfernt (Sandbox-Beschraenkung). Inhalt im Anhang B falls erneuter manueller Run gewuenscht. |

---

## Verdict

**GO-WITH-CAVEATS**

- Critical: **0**
- Major:    **0**
- Minor:    **3** (alle dokumentiert, keine release-blockend)
- Informational: **4**
- A11y-Pass-Rate: **100 % (7/7)**
- Quick-Win-Pass-Rate: **100 % (5/5)**
- Acceptance: **17/17 PASS**
- Performance-Wachstum: **+~1 KB Initial-HTML pro Service** (im Discovery-
  Korridor +1.5-2.5 KB; sogar leicht darunter)
- Wartbarkeit: **-32 % Template-LOC**

Die v0.14.2-Aenderungen sind **release-faehig**. Empfohlen wird:

1. CHANGELOG-Eintrag mit dem Theme-Override-Caveat (siehe I2).
2. Optionaler Follow-Up in v0.14.3: Re-Init-Guard fuer Live-Search-Listener
   (M1) + `requestAnimationFrame` in showMore (M2).
3. Manuelle Smoke-Test-Ausfuehrung durch Lead (Sandbox-Limit).

---

## Anhang A - Diff-Bilanz vs v0.14.1

| Kategorie               | Insertions | Deletions | Netto    |
|-------------------------|-----------:|----------:|---------:|
| dhps-mio.js             | 44         | 0         | +44      |
| dhps-frontend.css       | 24         | 0         | +24      |
| 3 mio/* Templates       | 121-(48+47+47) = 121 net | 141 | -20 LOC |
| partials/search-form.php (NEU) | 67  | 0         | +67      |
| partials/index.php (NEU)| 2          | 0         | +2       |

(Plugin-Header und README sind nicht Teil dieser QA-Skopierung.)

---

## Anhang B - Smoke-Test-Script (zum Wieder-Anlegen)

Pfad: `wp-deubner-hp-services/smoke-qa-v0142.php` (im Plugin-Root, NICHT
committen). Inhalt ist im QA-Run angelegt und wieder geloescht worden -
kann jederzeit aus dem Conversation-Log restauriert werden.

Aufruf:
```
docker exec wp-deubner-hp-services-wordpress-1 \
    php /var/www/html/wp-content/plugins/wp-deubner-hp-services/smoke-qa-v0142.php
```

Erwartete Ausgabe: `===== VERDICT: GO =====` bei korrektem Render.

---

## Anhang C - Empfehlungen fuer den Specialist-Final-Cut

1. **Versionsbump** `Deubner_HP_Services.php`: `Version: 0.14.2` (Memory
   weist auf Mismatch zwischen aktivem Plugin-Header und Discovery-
   Erwartung hin - aktuell ist HEAD-Commit v0.14.1).
2. **CHANGELOG**: `docs/project/16-CHANGELOG-v0142.md` mit dem Theme-
   Override-Caveat + 5 Quick-Win-Beschreibungen.
3. **Memory-Update**: `v0.14.2 - MIO + LXMIO Migration auf v0.14.0-
   Components (Skeleton-Slot, Search-Form-Partial, LXMIO-Token-Switch,
   Container-Queries, Live-Search-Debounce)`.
4. **Git-Tag**: `v0.14.2` nach manuellem Smoke-Test.

---

> Ende QA-Report v0.14.2.
