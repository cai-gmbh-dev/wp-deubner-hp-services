# Changelog v0.14.3 - TP + TPT + LP Migration

## Stand: 2026-05-23

## Mission

Vierte Stufe der Frontend-UX-Modernisierung: TP (TaxPlain Videos) + TPT
(TaxPlain Teaser) + LP (LexPlain Videos via Fallback) auf das v0.14.0
Component-System. **MAES-Videos-Pattern aus v0.14.1 als Vorbild**.

LP bekommt automatisches Recht-Blau-Branding ueber Wrapper-Token-Switch
und Component-Hooks - kein eigenes LP-Template noetig.

## Hauptaenderungen

### 1. TP-Templates modernisiert (2 von 3)

- `public/views/services/tp/default.php` -> Featured-Video als separate
  ContentCard + ContentList mit ContentCard(type='video', service=$tag)
  fuer das 60-Video-Grid. lazy_count via `.dhps-tp-card--lazy-hidden` CSS-Klasse.
- `public/views/services/tp/card.php` -> analog, mit Card-Wrapper
- **`tp/compact.php` BEWUSST UNVERAENDERT** (Discovery R 7.2: `initCompactAccordion`
  spawnt Player dynamisch, ContentCard-Migration wuerde JS-Refactor erfordern.
  Tech-Debt-Ticket angelegt fuer v0.15.0+)

### 2. TPT-Templates modernisiert (alle 3)

- `tpt/default.php`, `tpt/card.php`, `tpt/compact.php` -> ContentCard(type='video',
  service='tp') als Single-Featured-Card
- **Wrapper/Klassen-Inkonsistenz geloest** (vorher Mix aus `dhps-tpt-card` +
  `dhps-tp-card__*`), jetzt einheitlich via ContentCard mit `class='dhps-tp-card
  dhps-tpt-card'` (beide fuer BC + TP-JS-Selektor)
- **Empty-State implementiert**: wenn `$video` null, rendert ein
  EmptyState-Component statt stummem return

### 3. LP erbt automatisch

LP nutzt Filter `dhps_template_fallbacks` (lp -> tp) - bekommt **automatisch**
alle TP-Verbesserungen ohne eigene LP-Templates. Branding via:

- **CSS-Wrapper-Token-Switch** (analog LXMIO v0.14.2): `.dhps-service--lp`
  setzt `--dhps-color-primary` auf `--dhps-color-recht` (wirkt auf Filter-
  Buttons, Load-More, Featured-Heading, alles ausserhalb ContentCard)
- **ContentCard-Service-Hook**: `.dhps-content-card--service-lp .dhps-content-card__play-overlay`
  in Recht-Blau (analog MAES-Medizin-Teal-Pattern)
- ContentCard `service`-Prop wird im Template **dynamisch** aus `$service_tag`
  gesetzt: 'tp' fuer TP, 'lp' fuer LP

### 4. dhps-tp.js Selektor-Erweiterung

Von `'.dhps-service--tp'` auf `'.dhps-service--tp, .dhps-service--lp'` an 3
funktional relevanten Stellen (init + 2 closest-Calls). Alle anderen
Selektor-Operationen laufen ueber den bereits-init()-validierten
`container`-Parameter und brauchen keine Aenderung. **Minimal-invasiv,
Hybrid-Strategie respektiert** (kein Pipeline-Refactor).

### 5. CSP-Inline-Style-Fix (UI-Audit Finding TP-5)

Inline-Styles `style="color: var(--dhps-color-steuern)"` auf TP-Play-Buttons
sind **komplett entfernt** (3 Stellen in den 5 modernisierten Templates).
Branding kommt jetzt ausschliesslich ueber Service-Hook-CSS. CSP
`style-src 'self'` ohne `'unsafe-inline'` ist konform.

### 6. Service-Branding-Hooks ergaenzt

In `css/dhps-components.css` neu:

- `.dhps-content-card--service-tp .dhps-content-card__play-overlay { color: var(--dhps-color-steuern); }`
- `.dhps-content-card--service-lp .dhps-content-card__play-overlay { color: var(--dhps-color-recht); }`
- `.dhps-content-card--service-lp .dhps-content-card__badge--top { background/color: Recht-Blau }`

In `css/dhps-frontend.css` neu:

- `.dhps-service--lp { --dhps-color-primary: var(--dhps-color-recht, #0054A6); }` (analog LXMIO v0.14.2)

## QA + Security

### QA-Specialist Ergebnis

**Verdict**: GO-WITH-CAVEATS (statisch GO, Live-Smoke vom Lead bereits durchgefuehrt: 13/13 OK)

- 0 Critical, 0 Major
- 3 Minor (alle vorbestehend oder kosmetisch):
  - aria-controls auf Filter-Buttons fehlt (vorbestehend)
  - TPT `get_option` im Template (Tech-Debt-Ticket dokumentiert)
  - Discovery sprach von "10 Stellen JS-Patch", real 3 funktional ausreichend (Plan-vs-Implementation-Drift)

A11y: ContentCard rendert h3, alt-Texte korrekt, Empty-State mit role=status

Detail: [docs/project/19-QA-REPORT-v0143.md](19-QA-REPORT-v0143.md)

### Security-Audit Ergebnis

**Verdict**: GO

- 0 Critical, 0 High, 0 Medium
- 2 Low (kosmetisch / Tech-Debt-Hinweise)
- 4 dokumentierte Trust-Decisions:
  - TD-1: dhps-tp.js bleibt Vanilla (Hybrid-Strategie)
  - TD-2: tp/compact.php unveraendert (initCompactAccordion-Risiko)
  - TD-3: TPT get_option-Reads bleiben (WP-API sicher + esc_html)
  - TD-4: LP erbt automatisch via Template-Fallback

Kern-Verbesserungen:
- CSP-Fix: Inline-Styles in TP/TPT-Templates komplett entfernt
- ContentCard `data_attrs` Schutzkette aus v0.14.1 deckt alle 6 neuen Keys ab
- CSS-Branding-Hooks scope-eng (Klassen-Spezifitaet 0,2,0), kein Hijacking

Detail: [docs/project/20-SECURITY-AUDIT-v0143.md](20-SECURITY-AUDIT-v0143.md)

## Performance

| Shortcode | v0.14.2 | v0.14.3 | Delta |
|-----------|---------|---------|-------|
| `[tp]` (60 Videos) | 79.457 | 153.445 | **+93%** |
| `[tpt]` (1 Video) | 1.533 | 2.803 | **+83%** |
| `[lp]` (empty, OTA fehlt) | 333 | 333 | 0 |

`[tp]`-Wachstum im Discovery-Korridor (+50 bis +120%). Verursacht durch
ContentCard-Wrapper, LazyImage, Action-Footer, BEM-Klassen-Verkettung,
data_attrs (analog MAES-Migration v0.14.1).

**Trade-off akzeptabel**: A11y-Verbesserung (ContentCard's h3 + ARIA-Slots),
CSP-Konformitaet (Inline-Style raus), Wartbarkeit (DRY via Component),
gzip-effizient (BEM-Repetition komprimiert 5-8x), absolute 153 KB sind
manageable.

`[lp]`-Wachstum kommt erst bei gesetztem LP-OTA zum Tragen (aktuell empty).

## UI-Audit-Findings Status

### TP-Findings (5)

| # | Finding | Status |
|---|---------|--------|
| 1 | Render-Volumen (60 Videos im DOM) | unveraendert - lazy_count bleibt einziger Hebel |
| 2 | 484ms Render-Zeit | unveraendert (API-Bottleneck) |
| 3 | Filter ohne URL-State | unveraendert |
| 4 | Heading-Hierarchie h3/h4 | **teilweise** geloest (ContentCard rendert h3 via Filter) |
| 5 | Inline-Style auf Play-Btn (CSP) | **GELOEST** automatisch durch ContentCard |

### TPT-Findings (4)

| # | Finding | Status |
|---|---------|--------|
| 1 | Single-Video, kein Render-Problem | unveraendert |
| 2 | Wrapper/Klassen-Mix | **GELOEST** durch ContentCard |
| 3 | get_option-Reads im Template | Tech-Debt-Ticket (akzeptabel) |
| 4 | Kein Skeleton/Empty-State | **GELOEST** durch EmptyState-Component |

## Backward Compatibility

**Semantic BC**:

- Shortcodes + Option-Keys + Filter-Hooks bleiben stabil
- HTML-Struktur in TP/TPT-Templates geaendert (ContentCard-Markup statt
  alter TP-Card-BEM)
- Theme-Overrides unter `{theme}/dhps/services/{tp,tpt}/*.php` muessen
  ggf. nachgezogen werden
- Wrapper-Klassen `.dhps-tp-card`, `.dhps-tpt-card` bleiben an der
  ContentCard-Root fuer TP-JS-Selektoren + Theme-CSS-BC
- LP bleibt unveraendert (erbt automatisch via Fallback)
- `tp/compact.php` Markup bleibt EXAKT wie v0.14.2

### CSS-Selektoren die ggf. brechen

- `.dhps-tp-card__poster`, `.dhps-tp-card__title`, `.dhps-tp-card__teaser`
  in tp/default.php + tp/card.php Kontext sind weg - durch
  `.dhps-content-card--video .dhps-content-card__media|__title|__teaser`
  ersetzt
- Im tp/compact.php Kontext sind die alten Selektoren UNVERAENDERT
- `.dhps-tpt-card__*` (TPT-spezifische BEM-Children) sind weg - durch
  ContentCard-Pattern ersetzt

## Specialist-Team-Pattern (Iteration 4)

| Phase | Specialists | Output |
|-------|-------------|--------|
| P1 Discovery | 1 (Audit + JS-Selektor-Inventar + LP-Inheritance) | Migrations-Plan |
| P2 Implementation | 2 parallel (TP-1: tp/default+card, TPT-1: 3 tpt/*) | 5 Files |
| P3 Composition (Lead) | direct | JS-Selektor-Erweiterung (3 Stellen) + Foundation-CSS (LP-Wrapper + Play-Overlay-Hooks) + Smoke |
| P4 QA + Sec | 2 parallel | 2 Reports |
| P5 Release (Lead) | direct | CHANGELOG + Memory + Tag |

## Tech-Debt-Tickets (fuer v0.15.0+)

1. `tp/compact.php` ContentCard-Migration mit JS-Refactor von
   `initCompactAccordion` (dynamic Player-Spawn pruefen)
2. TPT-Modules-Layer einfuehren (analog zu anderen Services), der
   `get_option('dhps_tpt_*')` Admin-Texte via `$data` ans Template
   durchreicht (statt direkter Reads im Template)

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/17-TP-MIGRATION-PLAN-v0143.md` | Discovery |
| `docs/project/19-QA-REPORT-v0143.md` | QA-Report |
| `docs/project/20-SECURITY-AUDIT-v0143.md` | Security-Audit |
| `docs/project/21-CHANGELOG-v0143.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.14.2 -> 0.14.3 |
| `README.md` | Version-Bump |
| `css/dhps-frontend.css` | LP-Wrapper-Token-Switch (analog LXMIO v0.14.2) |
| `css/dhps-components.css` | LP-Play-Overlay-Hook + LP-Badge-Top + TP-Play-Overlay-Hook |
| `public/js/dhps-tp.js` | Selektor-Erweiterung `'.dhps-service--tp, .dhps-service--lp'` (3 funktional relevante Stellen) + DocBlock-Update |
| `public/views/services/tp/default.php` | ContentCard-Migration (Featured + Grid + lazy + filter) |
| `public/views/services/tp/card.php` | ContentCard-Migration |
| `public/views/services/tpt/default.php` | ContentCard + EmptyState |
| `public/views/services/tpt/card.php` | ContentCard + EmptyState |
| `public/views/services/tpt/compact.php` | ContentCard + EmptyState |

### UNVERAENDERT (Trust-Decision)

| Datei | Grund |
|-------|-------|
| `public/views/services/tp/compact.php` | initCompactAccordion-JS-Spawn-Risiko (Discovery R 7.2), Tech-Debt-Ticket fuer v0.15.0+ |

## Naechste Releases

- v0.14.4: TC (Wrapper-Service, nur Accordion-Component-Anpassung) - **letztes Plus** der v0.14.x-Reihe

## Bilanz

- **5 Templates modernisiert** (2 TP + 3 TPT), 1 bewusst unveraendert (tp/compact)
- **LP profitiert automatisch** ohne eigene Templates (Filter-Fallback)
- **dhps-tp.js minimal-invasiv** (3 Stellen + DocBlock, kein Pipeline-Refactor)
- **CSP-Fix**: 3 Inline-Style-Stellen eliminiert (Audit-Finding F5 GELOEST)
- **2 Wrapper/Klassen-Inkonsistenzen geloest** (TPT)
- **0 Critical/High** Security-Issues
- **13/13 Shortcodes** Regression OK
- **[tp] +93% Bytes** (im Discovery-Korridor +50/+120%, akzeptabel fuer A11y + CSP + DRY)
- **2 Tech-Debt-Tickets** dokumentiert (tp/compact, TPT-Modules-Layer)
