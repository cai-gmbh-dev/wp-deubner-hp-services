# Changelog v0.14.4 - TC Migration + v0.14.x Abschluss

## Stand: 2026-05-24

## Mission

Letztes Plus der v0.14.x-Frontend-UX-Modernisierungs-Reihe. TC (Tax-Rechner)
wird auf das v0.14.0 Component-System migriert. Sehr kleine Aenderung
(Empty-State-Deduplikation), grosse symbolische Bedeutung: **alle 9
Services beruehren jetzt das Component-System**.

## Hauptaenderungen

### 1. 3 TC-Templates Empty-State-Deduplikation

`tc/default.php`, `tc/card.php`, `tc/compact.php` ersetzen ihr eigenes
Empty-State-Markup durch `dhps_component('empty-state', ...)`:

```php
echo dhps_component( 'empty-state', array(
    'icon'  => 'calculator',
    'title' => __( 'Keine Steuer-Rechner verfuegbar', 'wp-deubner-hp-services' ),
    'hint'  => __( 'Pruefen Sie die Tax-Rechner Kundennummer in den Plugin-Einstellungen oder kontaktieren Sie den Deubner Verlag.', 'wp-deubner-hp-services' ),
    'class' => 'dhps-tc__empty',  // BC-Hook-Klasse bleibt
) );
```

EmptyState-Component-Icon-Map enthielt bereits `calculator` (war seit
v0.14.0 vorhanden) - keine Component-Erweiterung noetig.

### 2. TC-Compact-UX-Verbesserung

Vorher rendete `tc/compact.php` im Empty-State nur einen p-Text. Jetzt
bekommt der Empty-State auch im Compact-Layout den **Calculator-Icon +
Title + Hint** durch die EmptyState-Component. UX-Aufwertung von 220 B
auf ~600 B pro Empty-Render.

### 3. echo $tc_html Trust-Decision UNVERAENDERT

Die seit v0.13.0 dokumentierte Trust-Decision bleibt bytewise erhalten:

- `echo $tc_html` ohne Escaping in allen 3 Templates
- `phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped`-Marker
  an Ort und Stelle (3 Stellen)
- Inline-JS aus API (`test_einblenden` / `test_ausblenden`) funktioniert
  unveraendert
- DHPS_TC_Parser ist nicht angefasst

### 4. Backward Compatibility

- Wrapper-Klassen `.dhps-tc__empty` und `.dhps-tc__empty--compact` bleiben
  am EmptyState-Component-Wrapper als BC-Hooks fuer Theme-Overrides
- Heading-Hierarchie wechselt von h4 auf h3 (Component-Default) - geringe
  visuelle Aenderung, semantisch korrekter (h3 = Section-Heading)
- Alte BEM-Children-Klassen `.dhps-tc__empty-icon|title|text` sind nicht
  mehr im Markup - Theme-Overrides darauf muessen ggf. auf die
  EmptyState-Component-Klassen umgestellt werden (`.dhps-empty-state__icon`
  etc.)

## QA + Security

### QA-Specialist Ergebnis

**Verdict**: GO-WITH-CAVEATS

- 0 Critical, 0 Major
- 2 Minor (alle nicht-blockend):
  - Compact-Icon-Groesse als CSS-TODO fuer v0.14.5 (Icon ggf. zu gross
    in Sidebar)
  - Toter CSS-Code `.dhps-tc__empty-icon|title|text` als Cleanup-Kandidat
    fuer v0.15.0+ (bleibt als BC-Hook fuer Theme-Overrides)
- 1 Info: Heading h4 -> h3 Aenderung

Detail: [docs/project/22-QA-REPORT-v0144.md](22-QA-REPORT-v0144.md)

### Security-Audit Ergebnis

**Verdict**: GO

- 0 Critical, 0 High, 0 Medium, 0 Low
- 1 Info (CSP-Doku-Hinweis fuer Inline-Script aus API - identisch zu v0.13.0)
- 3 Trust-Decisions akzeptiert (alle unveraendert aus v0.13.0/v0.14.0):
  - TD-1: `echo $tc_html` unescaped (API-Trust-Boundary)
  - TD-2: Inline-`<script>` aus API (CSP-Implikation dokumentiert)
  - TD-3: EmptyState-Component-Calls mit statischen Werten

Detail: [docs/project/23-SECURITY-AUDIT-v0144.md](23-SECURITY-AUDIT-v0144.md)

## Performance

| Shortcode | v0.14.3 | v0.14.4 | Delta |
|-----------|---------|---------|-------|
| `[tc]` (non-empty, "Kundennummer erforderlich") | 1.031 | 1.031 | 0 (non-empty-Pfad unangetastet) |
| `[tc]` (empty) | ~285 (Lead-Sim) | ~985 (Lead-Sim) | +700 (EmptyState-Component-Wrapper) |
| `[tc layout=compact]` (empty) | ~220 | ~600 | +380 (UX-Aufwertung) |

Empty-Pfad waechst durch ContentCard-aehnliche Wrapper-Bytes. Akzeptabel
weil: 1 Stelle statt 3 zu pflegen, A11y-konsistent, Icon+Title+Hint
einheitlich.

## v0.14.x ABSCHLUSS - Gesamt-Bilanz

Die v0.14.x-Reihe (Frontend-UX-Modernisierung) ist mit v0.14.4 **komplett**.
Alle 9 Services beruehren das Component-System:

| Service | Status | v0.14.x Migration |
|---------|--------|-------------------|
| MMB | Pilot (Lazy-Akkordeon AJAX) | v0.14.0 default; card/compact: partial (lazy-loading nur in default) |
| MIL | erbt MMB | automatisch |
| MAES | Vollstaendig modernisiert | v0.14.1 alle 9 Templates + 3 Orchestrator-Shims |
| MIO | Quick-Wins (Hybrid-Strategie) | v0.14.2 Skeleton-Slots, Search-Partial, Live-Search, Container-Queries. News-Items bleiben JS-rendered (Hybrid-Trust-Decision) |
| LXMIO | erbt MIO | automatisch + Wrapper-Token-Switch (Recht-Blau) |
| TP | Vollstaendig modernisiert (-Compact) | v0.14.3 default + card. compact UNVERAENDERT (initCompactAccordion-Risiko) |
| TPT | Vollstaendig modernisiert | v0.14.3 alle 3 Templates + EmptyState |
| LP | erbt TP | automatisch + Wrapper-Token-Switch (Recht-Blau) |
| TC | Vollstaendig modernisiert | v0.14.4 alle 3 Templates Empty-State-Deduplikation |

**Bilanz: 5 von 9 Services vollstaendig migriert (MAES, TPT, TC, MIO, LXMIO),
4 partiell (MMB, MIL, TP, LP) mit dokumentierten Tech-Debt-Tickets.**

### v0.14.x Foundation-Errungenschaften

| Errungenschaft | Release |
|----------------|---------|
| CSS @layer Cascade (reset/tokens/components/utilities/overrides) | v0.14.0 |
| Alpine.js v3.14.9 lokal gebundled (~44 KB, defer, conditional) | v0.14.0 |
| 8 Shared Components (Skeleton, EmptyState, LazyImage, Accordion, ContentCard, FilterBar, Pagination, ContentList) | v0.14.0 |
| PHP-Component-Renderer mit DHPS_Component_Registry + Theme-Override | v0.14.0 |
| Elementor-Atomic-Token-Bridge (opt-in) | v0.14.0 |
| MMB Browser-Perception -94.8% (Lazy-Akkordeon) | v0.14.0 |
| ContentCard `data_attrs`-Prop (sanitize_key + esc_attr) | v0.14.1 |
| Service-Branding-Hooks (`.dhps-content-card--service-{maes,tp,lp,...}`) | v0.14.1+ |
| Wrapper-Token-Switch-Pattern (`.dhps-service--{lxmio,lp}` -> Recht-Blau) | v0.14.2+ |
| Search-Form-Partial-Pattern (MIO) | v0.14.2 |
| Container-Queries fuer Container-responsive Layouts | v0.14.2 |
| CSP-Fix: Inline-Styles eliminiert (TP-Play-Buttons) | v0.14.3 |
| Empty-State-Component-Deduplikation (MAES, TC) | v0.14.1, v0.14.4 |

### v0.14.x Security/QA-Trust-Decisions (kumulativ)

| # | Trust-Decision | Akzeptiert in |
|---|----------------|---------------|
| 1 | dhps-mio.js (1247 LOC) bleibt Vanilla | v0.14.2 |
| 2 | dhps-tp.js (695 LOC) bleibt Vanilla | v0.14.3 |
| 3 | News-Items bleiben JS-rendered in dhps-mio.js | v0.14.2 |
| 4 | tp/compact.php unveraendert (initCompactAccordion-Risiko) | v0.14.3 |
| 5 | TPT get_option-Reads bleiben im Template | v0.14.3 |
| 6 | LP erbt TP via Template-Fallback | v0.14.3 |
| 7 | LXMIO erbt MIO via Template-Fallback | (vorbestehend) |
| 8 | MIL erbt MMB via Template-Fallback | (vorbestehend) |
| 9 | `echo $tc_html` unescaped (API-Trust-Boundary) | v0.13.0, unveraendert |
| 10 | Inline-`<script>` aus TC-API (CSP-Implikation) | v0.13.0, unveraendert |
| 11 | Steuertermine bleiben tabellarisch (dl/dt/dd) | v0.14.2 |
| 12 | `wp_kses_post` auf API-Content (Defense-in-Depth) | v0.14.0 |
| 13 | `extract($props, EXTR_SKIP)` im Component-Renderer | v0.14.0 |
| 14 | Alpine.js benoetigt `script-src 'unsafe-eval'` (Framework-Limit) | v0.14.0 |

## Tech-Debt-Tickets (kumulativ fuer v0.15.0+)

| # | Ticket | Quelle |
|---|--------|--------|
| 1 | `tp/compact.php` ContentCard-Migration mit JS-Refactor von `initCompactAccordion` | v0.14.3 |
| 2 | TPT-Modules-Layer einfuehren (Admin-Texte via `$data` durchreichen) | v0.14.3 |
| 3 | MMB-card/compact Lazy-Akkordeon-Migration | v0.14.0 |
| 4 | TC-CSS-Cleanup: `.dhps-tc__empty-icon|title|text` toter Code | v0.14.4 |
| 5 | TC-Compact-Icon-Groesse via `.dhps-empty-state--compact` Modifier | v0.14.4 |
| 6 | aria-controls auf TP-Filter-Buttons | v0.14.3 |

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/project/22-QA-REPORT-v0144.md` | QA-Report |
| `docs/project/23-SECURITY-AUDIT-v0144.md` | Security-Audit |
| `docs/project/24-CHANGELOG-v0144.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.14.3 -> 0.14.4 |
| `README.md` | Version-Bump |
| `public/views/services/tc/default.php` | EmptyState-Component-Aufruf statt eigenes Markup |
| `public/views/services/tc/card.php` | EmptyState-Component-Aufruf |
| `public/views/services/tc/compact.php` | EmptyState-Component-Aufruf + UX-Aufwertung (Icon+Title) |

## Naechste Release-Optionen (nach v0.14.x Abschluss)

**v0.15.0+ Optionen** (offen, User-Entscheidung):

| Option | Scope |
|--------|-------|
| **v0.15.0a - Tech-Debt** | 6 dokumentierte Tickets abarbeiten (MMB-card/compact, tp/compact, TPT-Modules-Layer, TC-CSS-Cleanup, aria-controls) |
| **v0.15.0b - Einheitliches Datenmodell** | User-Wunsch seit fruehesten Iterationen: internes Schema das alle 9 API-Outputs normalisiert |
| **v0.15.0c - Backend-Admin-Dashboard** | React (@wordpress/components), Service-Health-Monitor, API-Test-Tools, Live-Preview |
| **v0.15.0d - REST-API-Endpoints** | Headless-Nutzung des Plugins via WP-REST |
| **v0.15.0e - Performance-Audit** | LCP/CLS-Messungen, Component-Bytes-Optimierung, ggf. Server-Side-Pre-Render |

## Specialist-Team-Pattern (Iteration 5, kleinste der v0.14.x-Reihe)

| Phase | Specialists |
|-------|-------------|
| P1 Implementation | 1 sequenzieller Spec (TC-1, 3 Templates) |
| P2 Composition (Lead) | direct (Version-Bump + Smoke + Empty-Pfad-Sim) |
| P3 QA + Sec | 2 parallel |
| P4 Release | Lead (CHANGELOG + Memory + Tag) |

Bei sehr kleinen Migrationen (3 Files, 1 Component-Aufruf) ist die Discovery
implizit im Spec-Briefing. Lehre nach 5 Iterationen: **Discovery-Spec-Aufwand
proportional zur Migrations-Komplexitaet**.

## Bilanz v0.14.4

- **3 Templates dedupliziert** (Empty-State -> Component-Aufruf)
- **TC-Compact bekommt UX-Aufwertung** (Icon+Title statt nur p-Text)
- **0 Critical/High/Medium/Low** Security-Issues
- **13/13 Shortcodes** Regression OK
- **Letzte Migration der v0.14.x-Reihe** - alle 9 Services beruehren jetzt das Component-System

## Bilanz v0.14.x (kumulativ - 5 Releases)

- **v0.14.0**: Foundation + MMB-Pilot (Browser-Perception -94.8%)
- **v0.14.1**: MAES Stresstest (9 Templates + ContentCard data_attrs)
- **v0.14.2**: MIO/LXMIO Quick-Wins (Hybrid-Strategie respektiert)
- **v0.14.3**: TP/TPT/LP Migration (5 Templates + CSP-Fix + LP-Inheritance)
- **v0.14.4**: TC Empty-State-Deduplikation
- **14 Trust-Decisions** dokumentiert
- **6 Tech-Debt-Tickets** fuer v0.15.0+
- **40 Templates beruehrt** (von ~50 Service-Templates)
- **8 Shared Components** als Foundation etabliert
- **Alpine.js v3.14.9** als JS-Layer integriert
- **A11y-Pass-Rates** konstant 89-100% pro Release
- **0 Critical-Security-Issues** ueber alle 5 Releases
