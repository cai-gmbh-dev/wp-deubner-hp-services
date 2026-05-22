# Roadmap v0.14.0 - Frontend-UX-Modernisierung

## Stand: 2026-05-22 (Konsolidierter Vorschlag - WARTET AUF APPROVAL)

## Ziel

Sichtbarer Frontend-Sprung: moderne UI/UX (Filter, Lazy, Skeleton, A11y) gegen
bestehende Parser-Outputs, mit Alpine.js + Elementor 4.x Atomic-Bridge. BC-Strategie:
Semantic BC (HTML-Struktur darf sich aendern, Shortcodes bleiben kompatibel).

## Discovery-Grundlage

Vier parallele Specialist-Reports (alle in `docs/architecture/`):

| # | Report | Kern-Ergebnis |
|---|--------|---------------|
| A | `10-elementor4-atomic-research.md` | Selektive Token-Bridge, Brand-Tokens NICHT bridgen, `{{WRAPPER}}` korrekt |
| B | `11-uiux-audit-v0140.md` | MMB/MIL 300KB strukturell, Inline-Script in MAES, kein Skeleton, `.screen-reader-text` undefiniert |
| C | `12-component-system-v0140.md` | 8 Components designed, MMB als Pilot, PHP-Renderer + `@layer` |
| D | `13-alpinejs-integration-v0140.md` | Alpine 3.14.x lokal gebundled (~14 KB), defer, conditional enqueue |

## Konsolidierte Strategie

### 1. Architektur-Foundation (alle Services profitieren)

**CSS @layer-Strategie**
```css
@layer dhps-reset, dhps-tokens, dhps-components, dhps-utilities, dhps-overrides;
```
- Klare Cascade-Reihenfolge, Theme- und Elementor-Konflikte minimiert
- `dhps-tokens` Layer enthaelt Brand-Tokens (geschuetzt)
- `dhps-overrides` letzte Schicht fuer Site-Anpassungen

**Token-Bridge (selektiv)**
- Neue Datei: `css/dhps-elementor-bridge.css` (abschaltbar via Option)
- Generische UI-Tokens werden gebridged: `--dhps-color-text`, `--dhps-font-family`, `--dhps-spacing-*`
- Brand-Tokens bleiben isoliert: `--dhps-color-steuern/recht/medizin`
- Fallback-Kette: `var(--e-global-color-primary, var(--e-color-primary, #default))`

**Alpine.js Loading**
- `public/js/vendor/alpinejs-3.14.x.min.js` (lokal, DSGVO)
- Conditional enqueue: nur wenn `[dhps_*]` Shortcode auf Seite (Detection via `has_shortcode()` oder Render-Marker)
- `defer` Attribut via `script_loader_tag` Filter
- Komponenten via `Alpine.data('dhpsFoo', ...)` (kein Inline-x-data mit Code)

**PHP-Renderer**
- Neue Funktion: `dhps_component( string $name, array $props ): string`
- Registry-Klasse: `DHPS_Component_Registry`
- Component-Templates: `public/views/components/{name}.php`
- Theme-Override-Pfad: `wp-content/themes/{theme}/dhps/components/{name}.php`

### 2. 8 Shared Components

| # | Component | Funktion | Verwendet in |
|---|-----------|----------|--------------|
| 1 | `SkeletonLoader` | CSS-only Shimmer-Placeholder | Alle Services |
| 2 | `EmptyState` | Icon + Titel + Hinweis + optional Action | Bereits in MIO/MMB/TC vorhanden, vereinheitlichen |
| 3 | `LazyImage` | IntersectionObserver + LQIP | MAES-Videos, MIO-News |
| 4 | `ContentCard` | Karte (type-Modifier: news/video/document) | MIO, MMB, MAES, LP, TP |
| 5 | `FilterBar` | Search + Tag-Chips + Sort | MMB, MAES, MIO, TP |
| 6 | `Pagination` | Load-More + numerische Seiten | MMB, MAES, MIO |
| 7 | `Accordion` | Multi/Single Expand | MMB, MAES-News, TC |
| 8 | `ContentList` | Container der oben kombiniert | Alle Listen-Services |

### 3. Quick-Wins (parallel zu Foundation)

Drei Findings aus dem UI/UX-Audit, die isoliert behoben werden koennen:

| QW | Fix | Datei | Aufwand |
|----|-----|-------|---------|
| 1 | `.screen-reader-text` als eigene Klasse definieren | `css/dhps-frontend.css` | 5 min |
| 2 | MAES-Aktuelles Inline-`<script>` extrahieren | `public/views/services/maes/aktuelles.php:69` -> neues JS-File | 30 min |
| 3 | MMB-Default Filter-Markup nachziehen (Feature-Parity zu Compact/Card) | `public/views/services/mmb/default.php` | 30 min |

### 4. Service-Migration (Pilot + Folge-Releases)

Phasen-Plan aus Specialist C, leicht angepasst:

**v0.14.0 - Foundation + MMB/MIL Pilot**
- Architektur-Foundation komplett (CSS @layer, Bridge, Alpine, Renderer)
- 8 Components implementiert
- MMB modernisiert: ContentList + ContentCard-document + FilterBar + Accordion + EmptyState + Skeleton
- MIL erbt automatisch via Fallback-Filter (Bonus-Service)
- **Erfolgskriterium**: MMB initialer Payload < 50 KB (von ~307 KB), MIL analog
- 3 Quick-Wins erledigt
- Migrations-Guide fuer Theme-Anpassungen

**v0.14.1 - MAES**
- Komplexester Output (4 Card-Typen), Stresstest des Component-Systems
- MAES-Videos: LazyImage-Integration
- MAES-Aktuelles: ContentCard-news + FilterBar
- MAES-Merkblaetter: ContentCard-document + Pagination

**v0.14.2 - MIO + LXMIO**
- MIO-News -> ContentCard-news
- MIO-Termine -> bleibt eigenes Template (semantisch unterschiedlich)
- LXMIO erbt via Fallback

**v0.14.3 - TP + TPT + LP**
- TP-Videos -> ContentCard-video + LazyImage
- TPT-Teaser -> ContentCard-video kompakt
- LP erbt via Fallback

**v0.14.4 - TC**
- Wrapper-Service, nur Accordion-Component-Anpassung
- Inline-JS bleibt (funktionale Anforderung)
- Empty-State auf neue Komponente migrieren

### 5. Performance-Ziele

| Metrik | Vorher (Smoke-Test 2026-05-22) | Ziel v0.14.0 | Wie |
|--------|--------------------------------|--------------|-----|
| MMB initial Payload | 306 KB | < 50 KB | AJAX-on-Demand pro Kategorie |
| MIL initial Payload | 304 KB | < 50 KB | erbt MMB |
| MAES Render-Zeit | 170 ms | < 100 ms | Skeleton + Lazy |
| TP Render-Zeit | 484 ms | < 300 ms | Skeleton (perceived) + Lazy-Cards |
| Erste Card sichtbar (LCP-Proxy) | nicht messbar | < 800 ms | Skeleton |
| Erstes JS-Bundle | n/a | < 20 KB gz | Alpine + dhps-init |

### 6. Risiken + Mitigation

| Risiko | Wahrscheinlichkeit | Mitigation |
|--------|--------------------|-----------|
| Theme-CSS-Selektoren brechen (Semantic BC) | hoch | Migrations-Guide, alte Klassen via `@layer dhps-overrides` 1 Release lang co-existieren |
| Elementor 4.x Atomic-Konflikte | mittel | DevTools-Verifikation der Tokens VOR Bridge-Aktivierung, Bridge abschaltbar |
| Alpine-Init laeuft 2x (Theme + Plugin) | mittel | `window.Alpine` Detection, defensiver Init-Guard |
| MMB AJAX-on-Demand bricht SEO | niedrig | `<noscript>`-Fallback liefert volle Liste, structured-data via JSON-LD |
| User-Theming durch Bridge zerstoert (Brand-Farben) | niedrig | Brand-Tokens explizit NICHT gebridged (Specialist A's Empfehlung) |

### 7. Out-of-Scope fuer v0.14.0

- Backend-Admin-Dashboard (eigene Achse, spaeter)
- Einheitliches Datenmodell (eigene Achse, spaeter)
- REST-API-Endpoints (eigene Achse, spaeter)
- Dark-Mode (optional, ggf. v0.14.5)
- WP-CLI-Integration (eigene Achse, spaeter)

## Implementations-Reihenfolge (v0.14.0)

```
Phase 1: Foundation (1-2 Tage)
   1.1  CSS @layer + dhps-tokens isolieren
   1.2  dhps-elementor-bridge.css + Admin-Toggle
   1.3  Alpine.js bundled + conditional enqueue
   1.4  dhps_component() + DHPS_Component_Registry
   1.5  3 Quick-Wins

Phase 2: Components (2-3 Tage)
   2.1  SkeletonLoader + EmptyState (CSS-only)
   2.2  ContentCard + LazyImage
   2.3  FilterBar + Pagination (Alpine)
   2.4  Accordion + ContentList

Phase 3: MMB-Pilot (1 Tag)
   3.1  MMB-Template umstellen
   3.2  AJAX-on-Demand-Endpoint
   3.3  MIL-Verifikation (Fallback)

Phase 4: QA + Hardening (1 Tag)
   4.1  Smoke-Test alle 13 Shortcodes
   4.2  A11y-Audit (Keyboard, ARIA, Contrast)
   4.3  Performance-Vergleich
   4.4  Security-Audit (XSS in neuen Components)

Phase 5: Release (0.5 Tag)
   5.1  Migrations-Guide (Theme-CSS-Aenderungen)
   5.2  CHANGELOG-v0140
   5.3  Memory-Update
   5.4  Git-Tag + GitHub-Release
```

Geschaetzte Gesamtdauer v0.14.0: **5-7 Arbeitstage**

## Offene Entscheidungen (vor Start)

1. **Token-Bridge-Default**: Standardmaessig aktiv oder opt-in via Settings?
2. **Quick-Wins parallel zu Foundation oder vorab als v0.13.1?**
3. **MIL als Bonus-Service in v0.14.0 oder erst v0.14.1?** (Specialist C: Bonus, da Fallback)
4. **`<noscript>`-Strategie fuer MMB AJAX-on-Demand**: voll-redundante Liste oder nur Hinweis?
