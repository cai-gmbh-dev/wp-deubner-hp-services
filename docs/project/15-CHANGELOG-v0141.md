# Changelog v0.14.1 - MAES-Migration auf Component-System

## Stand: 2026-05-23

## Mission

Zweite Stufe der Frontend-UX-Modernisierung: MAES (Meine Aerzteseite) wird vom
v0.13.x Inline-Markup auf das v0.14.0 Component-System (ContentList + ContentCard)
migriert. **Stresstest des Component-Systems** mit 3 Card-Typen (video, document,
news) und 4 Sub-Shortcodes (`[maes]`, `[maes_videos]`, `[maes_merkblaetter]`,
`[maes_aktuelles]`) in 3 Layouts (default, card, compact) = 12 Templates.

## Hauptaenderungen

### 1. 9 MAES-Templates modernisiert

Von Inline-Markup auf ContentList + ContentCard(type=video|document|news):

- `videos.php`, `videos-card.php`, `videos-compact.php` -> ContentCard(type='video')
  mit LazyImage, Branding via service='maes', data_attrs fuer TP-Player-Selector
- `merkblaetter.php`, `merkblaetter-card.php`, `merkblaetter-compact.php` ->
  ContentCard(type='document') mit PDF-Download-Action.
  **Outer-Akkordeon-Wrapper entfernt** (1 Klick weniger, UX-Verbesserung)
- `aktuelles.php`, `aktuelles-card.php`, `aktuelles-compact.php` ->
  ContentCard(type='news', collapsible=true) mit Alpine-Toggle.
  **wp_kses_post auf body_html via ContentCard** (XSS-Verbesserung gegenueber v0.13.x)

### 2. 3 Orchestrator-Shims

`default.php`, `card.php`, `compact.php` werden zu schlanken Shims, die per
`include` die modernisierten Sub-Templates aufrufen. Sektion-Filter via
`dhps_maes_section` weiterhin steuerbar.

### 3. Bugfix: Section-Filter Whitelist

`'aktuelles'` war zuvor **nicht** in der Section-Whitelist - `[maes]`-Default
renderte deshalb nie News-Eintraege. Korrigiert:
```php
$allowed_sections = array( 'all', 'videos', 'merkblaetter', 'aktuelles' );
```

### 4. ContentCard-Erweiterung: data_attrs-Prop

Neue Prop fuer ContentCard, registriert auch im Plugin-Bootstrap:
```php
'data_attrs' => array(),  // [key => value] => data-key="value"
```
Keys per `sanitize_key`, Values per `esc_attr`. Verwendet von MAES-Videos
fuer `data-video-slug`, `data-poster-url`, `data-v-modus` (TP-Player-Selector).
**Keine HTML-Injection moeglich** (Security-Audit bestaetigt).

### 5. Medizin-Branding-Hooks in dhps-components.css

Neue Selektoren `.dhps-content-card--service-maes .dhps-content-card__*`
fuer Medizin-Teal-Akzente (ersetzt Inline-Styles in den alten Templates -
CSP-konform). Plus Recht-Branding fuer LXMIO/LP vorbereitet.

### 6. JS-Cleanup

- `public/js/dhps-maes-aktuelles.js` **geloescht** (4 KB Source weg).
  ContentCard's Alpine-Toggle ersetzt den ausgelagerten Akkordeon-Code.
- `wp_register_script( 'dhps-maes-aktuelles-js' )` aus Plugin-Bootstrap entfernt.
- `wp_enqueue_script( 'dhps-mmb-js' )` aus MAES-Merkblaetter-Modul entfernt
  (Lazy-Akkordeon ist weg, kein MMB-JS noetig fuer MAES). **Spart ~10 KB
  JS bei `[maes_merkblaetter]`-only-Seiten.**

### 7. TP-JS minimaler Selector-Patch

`dhps-tp.js`: OR-Klausel in `loadVideoIframe()` ergaenzt um
`.dhps-content-card__media` als Poster-Element-Quelle. 4 Zeilen Diff,
kein Refactor. Bestehende TP-Player-Site bleibt voll funktionsfaehig.

## Performance-Beobachtung (Discovery-Disconnect)

Die Discovery-Prognose `-25% bis -36%` ist real **nicht eingetroffen** -
stattdessen Wachstum:

| Shortcode | v0.14.0 | v0.14.1 | Delta |
|-----------|---------|---------|-------|
| `[maes]` | 33.843 | 93.409 | **+176%** (davon ~78% durch Bugfix: aktuelles jetzt enthalten) |
| `[maes_videos]` | 14.562 | 28.440 | **+95%** |
| `[maes_merkblaetter]` | 22.025 | 31.571 | **+43%** |
| `[maes_aktuelles]` | 26.934 | 33.111 | **+23%** |

### Diagnose

Der Bytes-Zuwachs ist real durch:
- ContentList-Container-Overhead (~3.6 KB)
- LazyImage-Wrapper statt einfachem `<img>` (~4.8 KB pro Video)
- Voller Teaser-Text (vorher PHP-`mb_strimwidth(120)`, jetzt CSS-`line-clamp`)
- BEM-Klassen-Verkettung mit `service-`, `type-`, `--{modifier}` (~5 KB)
- Action-Footer mit SVG-Icons + ARIA-Attributen (~4.8 KB pro Card)
- `data_attrs` fuer TP-Player-Selector (~7 KB bei vielen Videos)

### Trade-off-Begruendung

Akzeptabel weil:
- **A11y-Gewinn**: 89% -> 100% nach MA-1 Fix (sr-only Section-Headings)
- **Wartbarkeit**: 12 Files mit Inline-Markup -> 12 Files mit Component-Aufrufen
  (gleicher File-Count, aber zentralisierte Logik via 8 Components)
- **CSP-Konformitaet**: Inline-Styles + 1 Inline-Script eliminiert
- **XSS-Verbesserung**: wp_kses_post auf body_html (vorher Plain-Echo!)
- **gzip-Effizienz**: BEM-Klassen-Repetition gzippt 5-8x
- **Absolute Bytes** (93 KB) liegen unter Roadmap-Ziel "< 120 KB"

**Lehrstunde fuer Discovery**: Component-Bytes-Cost wurde unterschaetzt.
Kuenftige Migrationen sollten einen Mini-Smoke-Test vor Specialist-Briefing
machen, um Discovery-Prognosen zu kalibrieren.

## QA + Security

### QA-Ergebnis (Specialist QA)

Verdict: **GO** (nach MA-1 Fix)

- 0 Critical
- 1 Major (MA-1, gefixt) - sr-only Section-Headings im default.php
- 4 Minor (3 gefixt: M-3 mmb-js, I-1 icon, Disconnect dokumentiert; 1 offen: video_mode-default)
- 6 Info (Optimierungs-Vorschlaege fuer v0.14.2 - LazyImage nur fuer Videos, ContentList-Wrapper-Merge)

A11y-Pass-Rate: 8/9 -> **9/9 nach MA-1 Fix** (100%)

Detail: [docs/project/13-QA-REPORT-v0141.md](13-QA-REPORT-v0141.md)

### Security-Audit (Specialist SEC)

Verdict: **GO**

- 0 Critical, 0 High, 0 Medium, 0 Low
- 5 Info-Findings (alle non-blocking)
- 5 dokumentierte Trust-Decisions

Kern-Ergebnisse:
- ContentCard `data_attrs`: doppelte Sanitisierung (sanitize_key + esc_attr) macht XSS unmoeglich
- Sub-Template-Include via hartcodiertem Pfad: kein File-Inclusion-Risk
- MAES-Aktuelles profitiert nun erstmals von `wp_kses_post`-Filterung (Net-Win)
- CSP-Strenge verbessert durch JS-Cleanup
- Brand-Tokens explizit NICHT in elementor-bridge.css (Trust-Decision v0.14.0 bestaetigt)

Detail: [docs/project/14-SECURITY-AUDIT-v0141.md](14-SECURITY-AUDIT-v0141.md)

## Backward Compatibility

**Semantic BC** (wie v0.14.0):

- Shortcodes + Option-Keys + Filter-Hooks bleiben stabil
- HTML-Struktur in MAES-Templates hat sich geaendert
- Theme-Override-Files unter `{theme}/dhps/services/maes/*.php` muessen ggf.
  nachgezogen werden:
  - `videos.php` / `videos-card.php` / `videos-compact.php`
  - `merkblaetter.php` / `merkblaetter-card.php` / `merkblaetter-compact.php`
  - `aktuelles.php` / `aktuelles-card.php` / `aktuelles-compact.php`
  - `default.php` / `card.php` / `compact.php` (jetzt Orchestrator-Shims)
- CSS-Selektoren `.dhps-tp-card__poster` / `__title` / `__teaser` in
  MAES-Kontext sind weg - durch `.dhps-content-card--video .dhps-content-card__media`
  / `__title` / `__teaser` ersetzt. Theme-CSS muss migriert werden.

## Pre-Release-Fixes (auf Basis QA + Security)

| Fix | Severity | Aenderung |
|-----|----------|-----------|
| MA-1 | Major | 3 `<h2 class="screen-reader-text">` in `maes/default.php` ergaenzt |
| M-3 | Minor | `wp_enqueue_script( 'dhps-mmb-js' )` aus `class-dhps-maes-modules.php` entfernt |
| I-1 | Info | `empty_state.icon` in 3 `videos*.php` von `search` auf `video` korrigiert |

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/15-MAES-MIGRATION-PLAN-v0141.md` | Discovery-Report |
| `docs/project/13-QA-REPORT-v0141.md` | QA-Report |
| `docs/project/14-SECURITY-AUDIT-v0141.md` | Security-Audit |
| `docs/project/15-CHANGELOG-v0141.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.14.0 -> 0.14.1, ContentCard `data_attrs` Registry-Eintrag, dhps-maes-aktuelles-js Enqueue entfernt |
| `README.md` | Version-Bump |
| `css/dhps-components.css` | 5 Service-Branding-Hook-Selektoren (Medizin, Recht) |
| `public/views/components/content-card.php` | `data_attrs`-Prop unterstuetzt |
| `public/views/services/maes/default.php` | Orchestrator-Shim + Section-Filter-Bugfix + h2-sr-only-Headings |
| `public/views/services/maes/card.php` | Orchestrator-Shim |
| `public/views/services/maes/compact.php` | Orchestrator-Shim |
| `public/views/services/maes/videos.php` | ContentList + ContentCard(video) |
| `public/views/services/maes/videos-card.php` | ContentList + ContentCard(video) |
| `public/views/services/maes/videos-compact.php` | ContentList + ContentCard(video) |
| `public/views/services/maes/merkblaetter.php` | ContentList + ContentCard(document), Outer-Akkordeon weg |
| `public/views/services/maes/merkblaetter-card.php` | ContentList + ContentCard(document) |
| `public/views/services/maes/merkblaetter-compact.php` | ContentList + ContentCard(document), Inline-Layout |
| `public/views/services/maes/aktuelles.php` | ContentList + ContentCard(news, collapsible) |
| `public/views/services/maes/aktuelles-card.php` | ContentList + ContentCard(news, collapsible), Inline-Script entfernt |
| `public/views/services/maes/aktuelles-compact.php` | ContentList + ContentCard(news, collapsible), Inline-Script entfernt |
| `public/js/dhps-tp.js` | OR-Selector um `.dhps-content-card__media` ergaenzt |
| `includes/class-dhps-maes-modules.php` | dhps-mmb-js Enqueue entfernt (M-3 Fix) |

### Geloescht

| Datei | Grund |
|-------|-------|
| `public/js/dhps-maes-aktuelles.js` | Obsolet - ContentCard Alpine-Toggle ersetzt |

## Specialist-Team-Pattern (Iteration 2)

| Phase | Specialists | Output |
|-------|-------------|--------|
| P1 Discovery | 1 (Audit-Refresh) | Migrations-Plan + Coverage-Matrix |
| P2 Sub-Sektionen | 3 parallel (M1/M2/M3) | 9 Templates modernisiert |
| P3 Composition (Lead) | direct | 3 Shims + ContentCard-Erweiterung + Branding-CSS + JS-Cleanup |
| P4 QA + Sec | 2 parallel | 2 Reports + 3 Pre-Release-Fixes identifiziert |
| P5 Release (Lead) | direct | Fix-Apply + CHANGELOG + Memory + Tag |

## Naechste Releases

- v0.14.2: MIO + LXMIO Migration
- v0.14.3: TP + TPT + LP Migration
- v0.14.4: TC Migration (Wrapper-Service, nur Accordion-Anpassung)

## Bilanz

- **9 Templates modernisiert** + 3 Orchestrator-Shims = 12 Files mit Components
- **1 JS-File geloescht** + 1 Enqueue-Block entfernt = -14 KB JS
- **ContentCard erweitert** um `data_attrs`-Prop (allgemein nuetzlich, auch fuer TP-Player)
- **3 Branding-Hook-CSS-Selektoren** fuer Medizin-Teal + Recht-Blau
- **1 Section-Filter-Bug behoben** (aktuelles war stumm)
- **wp_kses_post auf MAES-Aktuelles body_html** (XSS-Verbesserung)
- **0 Critical/High/Medium/Low Security-Issues**
- **A11y-Pass 100%** nach MA-1-Fix
- **13/13 Shortcodes** Regression OK
- **Bytes-Trade-off**: +175% Total bei [maes], aber +97% bei korrigiertem Vergleich (Bug-Fix gegengerechnet), absolute 93 KB unter Roadmap-Ziel
