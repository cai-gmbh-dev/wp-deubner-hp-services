# Component-System v0.14.0 - Architektur-Spezifikation

> Status: Entwurf | Zielversion: v0.14.0 | Stand: 2026-05-22
> Owner: Architektur-Team | Reviewer: Frontend, Pipeline, Security

## Ziel & Scope

Konsolidierung der heute pro Service dupliziert vorhandenen Markup-Fragmente
(News-Karten, Video-Karten, Merkblatt-Listen, Filter-Leisten, Such-Felder)
in eine wiederverwendbare PHP-Component-Bibliothek mit progressiver
Alpine.js-Enhancement-Schicht und Elementor 4.x Atomic-Token-Bridge.

Nicht-Ziele: SPA-Umbau, Vendor-JS-Frameworks (React/Vue), Breaking-Changes
an Parser-Outputs.

---

## 1. Component-Inventar

8 Kern-Components decken die Bedarfe von News, Videos, Documents,
Calculators und Events ab.

### 1.1 ContentCard

Universelle Karte fuer News / Video / Document. Variante via `--type`-Modifier.

**Props (PHP):**
```php
[
    'type'        => 'news|video|document',  // Pflicht
    'title'       => string,                  // Pflicht
    'teaser'      => string,                  // optional
    'image_url'   => string,                  // optional, Poster/Thumb
    'image_alt'   => string,                  // empfohlen
    'meta'        => ['date' => ..., 'author' => ...],
    'actions'     => [                        // 0..n
        ['label' => 'PDF', 'href' => ..., 'icon' => 'download', 'target' => '_blank'],
    ],
    'badges'      => ['Neu', 'Rubrik X'],
    'body_html'   => string,                  // gefiltertes HTML (collapsed)
    'collapsible' => bool,                    // Default false
    'service'     => 'mio|maes|...',          // fuer Branding-Klasse
]
```

**BEM-Klassen:**
- `.dhps-card` (Block)
- `.dhps-card--news | --video | --document` (Typ-Modifier)
- `.dhps-card--service-maes` (Branding-Hook)
- `.dhps-card__media`, `.dhps-card__body`, `.dhps-card__title`,
  `.dhps-card__teaser`, `.dhps-card__meta`, `.dhps-card__actions`,
  `.dhps-card__badge`, `.dhps-card__detail` (Elemente)

**Alpine x-data Schema:**
```js
{
  open: false,
  toggle() { this.open = !this.open; },
  // a11y
  get ariaExpanded() { return String(this.open); }
}
```
Nur bei `collapsible=true` injiziert. Sonst statisches Markup.

**A11y:**
- Wenn `collapsible`: `<button aria-expanded aria-controls>` auf Titel.
- `image_alt` Pflicht wenn `image_url` gesetzt (sonst `alt=""` decorative).
- Video-Card: Play-Button mit `aria-label="Video abspielen: {title}"`.
- Document-Card-Action: `aria-label="{title} als PDF herunterladen"`.

---

### 1.2 ContentList

Container, der eine Liste von `ContentCard`-Instanzen rendert und Filter,
Search, Sort, Pagination bindet.

**Props:**
```php
[
    'items'      => array,        // Pflicht, Liste von Card-Props
    'layout'     => 'grid|list|compact',
    'columns'    => 1|2|3|4,      // nur layout=grid
    'filterable' => bool,
    'searchable' => bool,
    'sortable'   => array|false,  // [['key'=>'date','label'=>'Datum']]
    'page_size'  => int,          // 0 = alle
    'pagination' => 'load-more|numeric|none',
    'empty'      => array,        // Props fuer EmptyState
    'id'         => string,       // Pflicht (Alpine-Scope, ARIA-IDs)
]
```

**BEM-Klassen:** `.dhps-list`, `.dhps-list--grid`, `.dhps-list--grid-cols-3`,
`.dhps-list__toolbar`, `.dhps-list__items`, `.dhps-list__status`.

**Alpine x-data:**
```js
{
  q: '',
  tags: [],
  sort: '',
  page: 1,
  pageSize: 12,
  items: window.__dhpsListData[id],   // hydratisiert via JSON-Script
  get filtered() { /* q + tags + sort */ },
  get visible() { return this.filtered.slice(0, this.page * this.pageSize); },
  loadMore() { this.page++; this.announce(`${this.visible.length} sichtbar`); },
  reset() { this.q=''; this.tags=[]; this.page=1; }
}
```

**A11y:** `role="region"` + `aria-labelledby`, Status-Bar als
`aria-live="polite"`, Filter-Aenderungen kuendigen Treffer an.

---

### 1.3 FilterBar

Kombination aus Suche, Tag-Chips und Sort-Select. Eigenstaendig oder
eingebettet in `ContentList`.

**Props:**
```php
[
    'search_placeholder' => string,
    'tags'   => [['key'=>'rubrik-1','label'=>'Arbeitgeber','count'=>12]],
    'sorts'  => [['key'=>'date_desc','label'=>'Neueste zuerst']],
    'target' => string,           // Alpine-Scope-ID
]
```

**BEM:** `.dhps-filterbar`, `.dhps-filterbar__search`,
`.dhps-filterbar__chips`, `.dhps-filterbar__chip--active`,
`.dhps-filterbar__sort`, `.dhps-filterbar__reset`.

**Alpine:** Bindet via `x-model` an Parent-Scope (`q`, `tags`, `sort`).

**A11y:**
- Search-Input: `<label>` (visually-hidden) + `type="search"`.
- Chips: `<button aria-pressed="true|false">`.
- Sort: native `<select>` mit `<label>`.

---

### 1.4 LazyImage

Intersection-Observer-basiertes Lazy-Loading mit LQIP-Fallback.

**Props:**
```php
[
    'src'     => string,
    'alt'     => string,
    'width'   => int,
    'height'  => int,
    'sizes'   => string,
    'lqip'    => string,   // Data-URI oder Mikro-JPEG
    'eager'   => bool,     // skip lazy fuer above-the-fold
]
```

**BEM:** `.dhps-lazy-img`, `.dhps-lazy-img--loaded`, `.dhps-lazy-img__ph`.

**Alpine:**
```js
{
  loaded: false,
  init() {
    const io = new IntersectionObserver(([e]) => {
      if (e.isIntersecting) { this.$refs.img.src = this.$refs.img.dataset.src; io.disconnect(); }
    }, { rootMargin: '200px' });
    io.observe(this.$el);
  }
}
```
Fallback ohne JS: `<noscript>`-Variante mit direktem `src`.

**A11y:** `alt` Pflicht-Prop, `width`/`height` zur CLS-Vermeidung.

---

### 1.5 SkeletonLoader

CSS-only Shimmer-Placeholder waehrend AJAX-Hydration.

**Props:**
```php
[
    'variant' => 'card|list-item|text|media',
    'count'   => int,   // 1..n Wiederholungen
]
```

**BEM:** `.dhps-skeleton`, `.dhps-skeleton--card`, `.dhps-skeleton__line`,
`.dhps-skeleton__media`.

**Alpine:** keiner - rein CSS (`@keyframes dhps-shimmer`).

**A11y:** `aria-hidden="true"` (Status via parent `aria-busy`).

---

### 1.6 EmptyState

Konsistenter Leerzustand bei 0 Treffern, fehlender Lizenz, Demo-Modus.

**Props:**
```php
[
    'icon'   => 'search|inbox|lock|alert',
    'title'  => string,
    'hint'   => string,
    'action' => ['label' => 'Filter zuruecksetzen', '@click' => 'reset()'],
]
```

**BEM:** `.dhps-empty`, `.dhps-empty__icon`, `.dhps-empty__title`,
`.dhps-empty__hint`, `.dhps-empty__action`.

**A11y:** `role="status"` wenn dynamisch eingeblendet.

---

### 1.7 Pagination

Universell: "Load More"-Button ODER nummerierte Seiten.

**Props:**
```php
[
    'mode'    => 'load-more|numeric',
    'target'  => string,   // Alpine-Scope
    'label_more' => 'Weitere laden',
]
```

**BEM:** `.dhps-pagination`, `.dhps-pagination--numeric`,
`.dhps-pagination__btn`, `.dhps-pagination__page--current`.

**Alpine:** Bindet an `page`, `pageSize`, `filtered.length` des Parents.

**A11y:** `<nav aria-label="Seitennavigation">`, current-page mit
`aria-current="page"`.

---

### 1.8 Accordion

Wrapper fuer FAQ-aehnliche Inhalte. Hauptanwendung: TC-Calculator-Liste
sowie MMB-Merkblaetter und MAES-News.

**Props:**
```php
[
    'items' => [
        ['id' => '...', 'title' => '...', 'body_html' => '...', 'open' => false],
    ],
    'multi' => bool,     // mehrere gleichzeitig offen?
    'id'    => string,
]
```

**BEM:** `.dhps-accordion`, `.dhps-accordion__item`,
`.dhps-accordion__trigger`, `.dhps-accordion__panel`,
`.dhps-accordion__item--open`.

**Alpine:**
```js
{
  openIds: [],
  toggle(id) {
    if (this.multi) {
      this.openIds = this.openIds.includes(id)
        ? this.openIds.filter(x => x !== id) : [...this.openIds, id];
    } else {
      this.openIds = this.openIds.includes(id) ? [] : [id];
    }
  },
  isOpen(id) { return this.openIds.includes(id); }
}
```

**A11y:** `<button aria-expanded aria-controls>`, Panel
`role="region" aria-labelledby`, Pfeiltasten-Navigation per Alpine-Plugin.

---

## 2. PHP-Renderer-API

### 2.1 Aufruf-Konvention

```php
dhps_component( string $name, array $props = [] ): void   // direktes echo
dhps_component_html( string $name, array $props = [] ): string  // return string
```

Beispiel im Service-Template:
```php
<?php dhps_component('content-list', [
    'id'         => 'maes-videos',
    'layout'     => 'grid',
    'columns'    => 2,
    'searchable' => true,
    'page_size'  => 8,
    'items'      => array_map(fn($v) => [
        'type'      => 'video',
        'title'     => $v['title'],
        'teaser'    => $v['description'],
        'image_url' => $v['poster_url'],
        'service'   => 'maes',
        'actions'   => [/* play via TP-Proxy */],
    ], $videos),
    'empty' => ['icon' => 'search', 'title' => 'Keine Videos gefunden'],
]); ?>
```

### 2.2 Dateilayout

```
public/views/components/
├── content-card.php
├── content-list.php
├── filterbar.php
├── lazy-image.php
├── skeleton.php
├── empty-state.php
├── pagination.php
└── accordion.php
includes/components/
├── class-dhps-component-registry.php   # Renderer + Prop-Validierung
└── class-dhps-component-props.php      # Schema-Definition pro Component
```

### 2.3 Theme-Override

Loader-Reihenfolge analog zu Services:
1. `{theme}/dhps/components/{name}.php`
2. Plugin-Default `public/views/components/{name}.php`

Wrapper: `DHPS_Component_Registry::locate_template($name)`.

### 2.4 Integration mit Service-Templates

Service-Templates (`public/views/services/{svc}/default.php`) bleiben
strukturell erhalten, ersetzen jedoch ihre Markup-Fragmente durch
`dhps_component()`-Aufrufe. Parser-Output und Pipeline aendern sich nicht
(keine Breaking-Changes an Daten-Shape).

Prop-Validierung erfolgt im Registry:
- Fehlende Pflichtfelder werfen `WP_DEBUG` Notice + rendern leer.
- Unbekannte Props werden ignoriert (Forward-Compatibility).

---

## 3. Alpine.js-Pattern

### 3.1 Globale Registrierung

```js
// assets/js/dhps-components.js (registriert via wp_enqueue_script)
document.addEventListener('alpine:init', () => {
  Alpine.data('dhpsList', (id) => ({ /* siehe 1.2 */ }));
  Alpine.data('dhpsAccordion', (cfg) => ({ /* siehe 1.8 */ }));
  Alpine.data('dhpsCard', () => ({ /* siehe 1.1 */ }));
  Alpine.data('dhpsLazyImg', () => ({ /* siehe 1.4 */ }));
});
```

Templates verwenden `x-data="dhpsList('maes-videos')"`, kein Inline-JS.

### 3.2 State-Persistenz

URL-Sync fuer Filter/Search via `Alpine.persist`-Aequivalent:
```js
init() {
  const params = new URLSearchParams(location.hash.slice(1));
  this.q = params.get('q') ?? '';
  this.$watch('q', v => this.syncHash());
}
```
Browser-Back stellt Filter-Zustand wieder her. Pro `ContentList`-Instanz
ein Namespace (`#maes-videos:q=foo`).

### 3.3 A11y-Hooks

- **Focus-Trap** in Accordion-Panels: optional via `x-trap` (Alpine-Plugin).
- **aria-live**: `<div role="status" aria-live="polite" x-text="statusText">`
  innerhalb jeder `ContentList`. Statusmeldungen z.B.
  "12 von 47 Eintraegen sichtbar".
- **Keyboard**: ESC schliesst geoeffnete Accordions/Cards via
  `@keydown.escape.window`.
- **prefers-reduced-motion**: CSS-Animationen via Media-Query
  ausgeklammert (siehe 4.3).

### 3.4 Performance

- Alpine wird nur geladen, wenn mindestens eine Component im Template aktiv ist.
  Marker via `DHPS_Component_Registry::mark_used()` -> `wp_enqueue_script`.
- `defer`-Loading von Alpine, `x-cloak` verhindert FOUC.

---

## 4. CSS-Architektur

### 4.1 @layer-Strategie

```css
/* css/dhps-components.css */
@layer dhps-reset, dhps-tokens, dhps-components, dhps-utilities, dhps-overrides;

@layer dhps-tokens { :root { --dhps-color-primary: ...; } }
@layer dhps-components { .dhps-card { ... } }
@layer dhps-utilities { .dhps-u-stack > * + * { margin-block-start: var(--dhps-gap); } }
```

Vorteil: Theme- und Elementor-Styles ueberschreiben die `dhps-components`-
Layer ohne Spezifitaets-Krieg. `dhps-overrides` bleibt frei fuer Hot-Fixes.

### 4.2 Token-Bridge zu Elementor 4.x Atomic

```css
@layer dhps-tokens {
  :root {
    /* Atomic-Token Fallback-Kette: Elementor 4 -> Legacy --e- -> Hardcode */
    --dhps-color-primary:   var(--e-global-color-primary,   var(--e-color-primary,   #0AA245));
    --dhps-color-secondary: var(--e-global-color-secondary, var(--e-color-secondary, #0054A6));
    --dhps-color-accent:    var(--e-global-color-accent,    #C9A961);
    --dhps-color-text:      var(--e-global-color-text,      #333);
    --dhps-font-body:       var(--e-global-typography-text-font-family, system-ui, sans-serif);
    --dhps-font-heading:    var(--e-global-typography-primary-font-family, var(--dhps-font-body));

    /* Service-spezifische Brand-Akzente (ueberschreibbar pro .dhps-service--*) */
    --dhps-color-steuern:  #0AA245;
    --dhps-color-recht:    #0054A6;
    --dhps-color-medizin:  #14B8A6;

    /* Spacing-Skala (4er-Schritte) */
    --dhps-space-1: 4px;  --dhps-space-2: 8px;  --dhps-space-3: 12px;
    --dhps-space-4: 16px; --dhps-space-6: 24px; --dhps-space-8: 32px;

    /* Radius / Shadow */
    --dhps-radius-sm: 4px; --dhps-radius-md: 8px; --dhps-radius-lg: 12px;
    --dhps-shadow-card: 0 2px 8px rgba(0,0,0,.08);
  }
}
```

Branding-Switch pro Service:
```css
.dhps-card--service-lp,
.dhps-card--service-lxmio { --dhps-color-primary: var(--dhps-color-recht); }
.dhps-card--service-maes { --dhps-color-primary: var(--dhps-color-medizin); }
```

### 4.3 Container-Queries fuer Cards

```css
.dhps-list { container-type: inline-size; container-name: dhps-list; }

.dhps-card { display: grid; gap: var(--dhps-space-3); }
@container dhps-list (min-width: 480px) {
  .dhps-card--news { grid-template-columns: 120px 1fr; }
}
@container dhps-list (min-width: 720px) {
  .dhps-list--grid { grid-template-columns: repeat(var(--dhps-cols, 2), 1fr); }
}

@media (prefers-reduced-motion: reduce) {
  .dhps-skeleton { animation: none; }
}
```

Vorteil gegenueber Media-Queries: ContentList passt sich an seinen
Elementor-Container an (z.B. Sidebar vs. Hauptspalte), nicht ans Viewport.

### 4.4 Datei-Aufteilung

```
css/
├── dhps-frontend.css         # bleibt (Legacy bis Migration komplett)
├── dhps-tokens.css           # NEU: nur Custom Properties
├── dhps-components.css       # NEU: Components in @layer
└── dhps-service-{svc}.css    # service-spezifische Akzente (optional)
```

Enqueue-Reihenfolge: tokens -> components -> frontend -> service.

---

## 5. Migrations-Plan pro Service

### 5.1 Component-Usage-Matrix

| Service | ContentList | ContentCard | FilterBar | LazyImage | Accordion | Pagination | EmptyState | Skeleton |
|---------|:-----------:|:-----------:|:---------:|:---------:|:---------:|:----------:|:----------:|:--------:|
| MIO     | X (News)    | X (news)    | X         | X         | -         | X          | X          | X        |
| LXMIO   | X (News)    | X (news)    | X         | X         | -         | X          | X          | X        |
| MMB     | X (Liste)   | X (document)| X (Search)| -         | X         | -          | X          | X        |
| MIL     | X (Liste)   | X (document)| X (Search)| -         | X         | -          | X          | X        |
| TP      | X (Videos)  | X (video)   | X (Search)| X (Poster)| -         | X          | X          | X        |
| TPT     | X (Videos)  | X (video)   | -         | X         | -         | -          | X          | X        |
| LP      | X (Videos)  | X (video)   | X         | X         | -         | X          | X          | X        |
| MAES    | X (multi)   | X (alle)    | X         | X         | X (News)  | X          | X          | X        |
| TC      | -           | -           | -         | -         | X (Rechner)| -         | X (Empty)  | -        |

### 5.2 Migrations-Reihenfolge

**Phase 1 - Pilot (MMB, Woche 1)**

Gruende: Geringste Komplexitaet (List + Accordion + Search), klar
abgrenzbare Karte (Document), Parser-Shape stabil seit v0.9.9,
Empty-State-Pfad bereits etabliert. Liefert Validierung fuer
ContentList + FilterBar + Accordion + EmptyState + Pagination.

**Phase 2 - MIL (Woche 1, Ende)**

Erbt MMB-Templates via `dhps_template_fallbacks`. Free-Ride: 1 Service
mehr ohne neue Component-Arbeit. Validiert Template-Fallback-Bridge.

**Phase 3 - MAES (Woche 2)**

Komplexester Output (4 Sektionen: Overview, News, Videos, Merkblaetter).
Validiert Multi-Card-Typ-Mix, Section-Filter-Shortcode bleibt erhalten.
Liefert Accordion (News collapse) + LazyImage (Video-Poster).

**Phase 4 - MIO/LXMIO (Woche 3)**

News-Liste + AJAX-Pagination + Tax-Dates-Special. Tax-Dates erhaelt
eigene kleine Sub-Component (`tax-dates`) oder bleibt Service-spezifisch.
LXMIO erbt MIO-Templates -> Recht-Branding via Token-Switch.

**Phase 5 - TP/TPT/LP (Woche 4)**

Video-Karten mit AJAX-Player-Proxy. Aenderung am Proxy-JS minimal,
da nur Markup ersetzt wird. LP/TPT erben TP-Templates.

**Phase 6 - TC (Woche 5)**

Nur Accordion-Wrapper + Empty-State. Wrapper-Parser bleibt, lediglich
Empty-State-Markup nutzt die neue `EmptyState`-Component. Calculator-
Markup vom API-Endpoint bleibt wie heute durchgereicht.

### 5.3 Akzeptanzkriterien pro Phase

- Visueller Regressions-Test (Backstop oder Screenshot-Diff Vor/Nach).
- Keine Aenderung am Parser-Array-Shape.
- Bestehende Shortcode-Attribute (`section`, `layout`, `class`)
  funktionieren weiter.
- Lighthouse: A11y >= 95, Performance >= 90 auf Demo-Seite.
- Keine neuen Console-Errors, `x-cloak` greift vor Alpine-Init.

### 5.4 Rollback-Strategie

Pro Service liegt ein Feature-Flag `dhps_components_enabled` (Filter).
Setzt das Theme/Site den Filter auf `false` fuer einen Service, faellt
die Pipeline auf das Legacy-Template zurueck. Legacy-Templates werden
erst nach erfolgreicher Phase 6 + 1 Release-Cycle (v0.15.0) entfernt.

---

## Anhang A - Pfad-Konventionen (Zusammenfassung)

```
includes/components/class-dhps-component-registry.php   # Loader/Renderer
includes/components/class-dhps-component-props.php      # Schemas
public/views/components/{name}.php                      # Templates
css/dhps-tokens.css                                     # Tokens
css/dhps-components.css                                 # Components
assets/js/dhps-components.js                            # Alpine.data()
```

## Anhang B - Offene Fragen

- Soll Alpine.js gebundelt oder per CDN ausgeliefert werden? (Empfehlung: lokal `assets/vendor/alpine.min.js`, ~15 KB gz.)
- Eigene Component-Klassen-API zusaetzlich zur Funktions-API? (Empfehlung: vorerst nein, YAGNI.)
- Snapshot-Tests fuer Component-Output via WP_Mock? (Empfehlung: Phase 1 mit MMB pilotieren.)
