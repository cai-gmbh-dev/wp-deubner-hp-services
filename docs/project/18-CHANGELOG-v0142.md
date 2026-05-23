# Changelog v0.14.2 - MIO + LXMIO Migration

## Stand: 2026-05-23

## Mission

Dritte Stufe der Frontend-UX-Modernisierung: MIO (MI-Online Steuerrecht) +
LXMIO (MI-Online Recht) bekommen 5 Quick-Wins aus dem v0.14.0 UI/UX-Audit
plus einen LXMIO-Token-Switch fuer Recht-Blau-Branding.

**Hybrid-Strategie respektiert**: `dhps-mio.js` (1247 LOC, komplexeste
JS-Pipeline) wurde NICHT umgeschrieben - nur minimal-invasiv erweitert.
News-Items werden weiterhin clientseitig in JS gebaut, daher KEIN
ContentCard-Migration fuer News (Memory-Trust-Decision).

## 5 Quick-Wins angewendet

### QW1 - LXMIO Service-Wrapper-Token-Switch

Neue CSS-Regel `.dhps-service--lxmio { --dhps-color-primary: var(--dhps-color-recht); }`
sorgt dafuer, dass ALLE Plugin-Elemente die `--dhps-color-primary` nutzen
(inkl. der JS-rendered News-Articles in dhps-mio.js) auf LXMIO-Seiten
Recht-Blau bekommen, ohne dass die JS-Pipeline geaendert werden muss.

### QW2 - SkeletonLoader-Slots in 3 MIO-Templates

`mio/default.php`, `mio/card.php`, `mio/compact.php` haben jetzt einen
Skeleton-Slot mit dem v0.14.0 SkeletonLoader-Component:

```php
<div class="dhps-mio-skeleton-slot" data-dhps-mio-skeleton hidden>
    <?php echo dhps_component( 'skeleton-loader', array( 'type'=>'list|card', 'count'=>3 ) ); ?>
</div>
```

Per Default hidden, kann von dhps-mio.js via `removeAttribute('hidden')`
sichtbar gemacht werden waehrend AJAX-Load. Quick-Win 1 aus UI-Audit
("Mehr laden hat keinen Skeleton") teilweise geloest - synchron-Repaint
bei showMore() macht das Skeleton kosmetisch unsichtbar (QA-Finding M2),
Markup ist aber vorbereitet fuer kuenftige asynchrone Erweiterung.

### QW3 - Search-Form-Partial extrahiert

3x identischer Such-Form-Block (~75 Zeilen Duplikat) -> 1 Partial:

- Neue Datei `public/views/services/mio/partials/search-form.php`
- Variablen: `$service_tag`, `$placeholder` (default 'Suchbegriff')
- Idempotent inkludierbar, defensive `isset`-Defaults
- Theme-Override-Pfad funktional (via Template-Hierarchie)

### QW4 - Live-Search-Debounce + Mehr-laden-Skeleton-Toggle in JS

Minimal-invasive Erweiterung in `dhps-mio.js`:

- Neuer `input`-Event-Listener auf `[data-dhps-search-input]` mit 300ms
  Debounce, triggert `loadNews()` (dieselbe Funktion wie Submit-Handler)
- Min-Chars-Schwelle: 3 (konfigurierbar via `data-dhps-live-search-min`)
- Bei leerem Feld: Reset, kein Submit
- `showMore()` blendet vor fetch den Skeleton-Slot ein, nach Erfolg/Fehler aus
- Idempotenz-Guard via `dataset.dhpsLiveSearchBound` (QA-M1-Fix)

Original-Submit-Handler bleibt unveraendert (BC). +~44 LOC.

### QW5 - Container-Queries fuer Steuertermine-Grid

`.dhps-tax-dates` + `.dhps-termine` bekommen `container-type: inline-size`
+ `@container dhps-termine (max-width: 500px) { grid-template-columns: 1fr }`.
`@media (max-width: 768px)`-Fallback bei Z. 815 erhalten fuer Safari < 16.
Steuertermine kollabieren jetzt korrekt in schmalen Elementor-Spalten,
nicht nur bei schmalem Viewport (UI-Audit Finding 3).

## QA + Security

### QA-Specialist Ergebnis

**Verdict**: GO-WITH-CAVEATS

- 0 Critical, 0 Major
- 3 Minor (alle nicht-blockend, M1 als 3-LOC-Quick-Fix angewendet):
  - **M1**: Re-Init-Idempotenz fuer Live-Search-Listener -> **gefixt** (dataset-Guard)
  - **M2**: Skeleton-Toggle in showMore() kosmetisch unsichtbar (synchroner Repaint) - akzeptiert (Markup ist vorbereitet fuer async-Erweiterung)
  - **M3**: Container-Query-Fallback wirkt nur bei Viewport <= 768px, nicht bei schmalen Containern auf Legacy-Browsern - akzeptiert (Safari < 16 Anteil < 5%)
- A11y-Pass-Rate: **100% (7/7)**
- Performance: Initial-HTML +~1 KB pro Layout (akzeptabel, unter Discovery-Prognose 1.5-2.5 KB)

Detail: [docs/project/16-QA-REPORT-v0142.md](16-QA-REPORT-v0142.md)

### Security-Audit Ergebnis

**Verdict**: GO

- 0 Critical, 0 High, 0 Medium
- 1 Low (out-of-scope) + 2 Info (Follow-ups fuer v0.14.3)
- 3 dokumentierte Trust-Decisions:
  - TD-1: dhps-mio.js bleibt Vanilla (Hybrid-Strategie)
  - TD-2: News-Items werden clientseitig in JS gebaut (keine ContentCard-Migration ohne Pipeline-Refactor)
  - TD-3: Steuertermine-Markup bleibt tabellarisch (dl/dt/dd)

Detail: [docs/project/17-SECURITY-AUDIT-v0142.md](17-SECURITY-AUDIT-v0142.md)

## Backward Compatibility

**Semantic BC** wie v0.14.0/v0.14.1:

- Shortcodes + Option-Keys + Filter-Hooks bleiben stabil
- HTML-Struktur in MIO-Templates leicht erweitert (Skeleton-Slot dazu)
- LXMIO erbt automatisch (Filter `dhps_template_fallbacks`)
- Theme-Overrides unter `{theme}/dhps/services/mio/*.php` bleiben funktional
- Neuer Theme-Override-Pfad fuer Search-Form: `{theme}/dhps/services/mio/partials/search-form.php`

## Performance

| Shortcode | v0.14.1 | v0.14.2 | Delta |
|-----------|---------|---------|-------|
| `[mio]` | 4.485 | 5.696 | +1.211 (+27%) |
| `[lxmio]` | 2.401 | 3.614 | +1.213 (+51%) |
| `[mio_termine]` | 1.998 | 1.998 | 0 |

Wachstum primaer durch Skeleton-Slot-Markup (~1 KB pro Template).
**Akzeptabel** (im Discovery-Korridor 1.5-2.5 KB sogar leicht unterschritten,
da Search-Partial-Deduplikation netto kompensiert).

## Specialist-Team-Pattern (Iteration 3)

| Phase | Specialists | Output |
|-------|-------------|--------|
| P1 Discovery | 1 (Audit-Refresh + AJAX-Renderer-Mapping) | Migrations-Plan |
| P2 Implementation | 1 grosser Spec (MIO-1, 5 sequenzielle Quick-Wins) | 7 Files |
| P3 Composition (Lead) | direct | Version-Bump + Smoke-Test |
| P4 QA + Sec | 2 parallel | 2 Reports + 1 Pre-Release-Fix (M1) |
| P5 Release (Lead) | direct | CHANGELOG + Memory + Tag |

**Lehre**: Bei dicht gekoppelten Quick-Wins (alle in MIO/LXMIO-Kontext) ist
1 grosser Spec effizienter als 2-3 parallele - Koordinations-Overhead und
Datei-Konflikte werden vermieden.

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/16-MIO-MIGRATION-PLAN-v0142.md` | Discovery |
| `docs/project/16-QA-REPORT-v0142.md` | QA-Report |
| `docs/project/17-SECURITY-AUDIT-v0142.md` | Security-Audit |
| `docs/project/18-CHANGELOG-v0142.md` | (dieses Dokument) |
| `public/views/services/mio/partials/search-form.php` | Deduplikation 3x Search-Form |
| `public/views/services/mio/partials/index.php` | Silence-Guard |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.14.1 -> 0.14.2 |
| `README.md` | Version-Bump |
| `css/dhps-frontend.css` | QW1 LXMIO Token-Switch + QW5 Container-Queries |
| `public/views/services/mio/default.php` | QW2 Skeleton-Slot + QW3 Search-Partial-Include |
| `public/views/services/mio/card.php` | QW2 Skeleton-Slot (type=card) + QW3 Search-Partial-Include |
| `public/views/services/mio/compact.php` | QW2 Skeleton-Slot + QW3 Search-Partial-Include |
| `public/js/dhps-mio.js` | QW4 Live-Search-Debounce + Skeleton-Toggle + M1 Idempotenz-Guard |

## Naechste Releases

- v0.14.3: TP + TPT + LP (Videos) - profitieren vom MAES-Videos-Template-Pattern
- v0.14.4: TC (Wrapper-Service, nur Accordion-Component-Anpassung)

## Bilanz

- **5 Quick-Wins** umgesetzt (LXMIO-Branding, Skeleton, Search-Partial, Live-Search, Container-Queries)
- **LXMIO erbt automatisch** via Template-Fallback (Filter `dhps_template_fallbacks`)
- **dhps-mio.js minimal-invasiv** erweitert (+44 LOC, Hybrid-Strategie respektiert)
- **3 Templates deduplizieren** 75 Zeilen via Search-Form-Partial
- **0 Critical/High** Security-Issues, **A11y-Pass 100%**
- **13/13 Shortcodes** Regression OK
- **Initial-HTML +1 KB** pro Template (akzeptabler Skeleton-Markup-Overhead)
- **M1 Pre-Release-Fix**: Idempotenz-Guard fuer Live-Search-Listener (dataset-Marker)
