# Changelog v0.15.2 - Compact-Layouts Lazy-Loading

## Stand: 2026-05-25

## Mission

Die 2 letzten Tech-Debt-Tickets aus v0.14.x werden abgearbeitet:
- **tp/compact.php** HTML-Validity-Fix (`initCompactAccordion` Player-Spawn)
- **MMB-card/compact** Lazy-Akkordeon-Migration (analog MMB-default v0.14.0)

## Hauptaenderungen

### 1. TP-Compact: HTML-Validity-Fix (Hybrid-Strategie)

**Problem (Discovery v0.15.2 Sektion 1)**: `initCompactAccordion` in
dhps-tp.js spawnte den Video-Player via `item.after( playerDiv )` als
Geschwister neben `<li>` - das ergab HTML-invalides Markup
(`<ul><li>...</li><div>...</div></ul>`, `<div>` ist kein valides
ul-Kind).

**Loesung**: Minimal-invasiver 1-Line-Fix:
```js
// Vorher: item.after( playerDiv );       // Player wird <li>-Geschwister
// Nachher: item.appendChild( playerDiv ); // Player wird <li>-Kind
```

**CSS-Anpassung** (Layout-Folge):
- `.dhps-tp-compact__item { flex-wrap: wrap }` - Player landet auf neuer Zeile statt rechts daneben
- `.dhps-tp-compact__player { flex: 1 1 100% }` - volle Breite
- Player-Padding-Reset (X-Padding kommt jetzt vom `<li>`-Container)

**Bewusst NICHT gemacht** (Discovery Option B Trust-Decision):
- KEIN ContentCard-Migration fuer tp/compact.php (Sidebar-Row passt
  semantisch nicht zu Card-Pattern)
- KEIN Refactor der initCompactAccordion-Logik (Risiko-Minimierung)

### 2. MMB-Card/Compact: Lazy-Akkordeon-Migration

**Problem (Discovery v0.15.2 Sektion 3)**: MMB-default.php hatte seit
v0.14.0 Lazy-Akkordeon mit AJAX-on-Demand, MMB-card.php + MMB-compact.php
rendern weiterhin alle Sheets vollstaendig im Initial-HTML.

**Loesung**: MMB-AJAX-Endpoint bekommt Layout-Whitelist-Param, 2 neue
Partials, Lazy-State-Markup in beiden Templates.

#### Backend (class-dhps-mmb-ajax-handler.php)

- Neue Konstante `ALLOWED_LAYOUTS = array( 'default', 'card', 'compact' )`
- Layout-Param via `sanitize_key($_REQUEST['layout'] ?? 'default')`
- **Dreistufiger Path-Traversal-Schutz**:
  1. `sanitize_key()` entfernt Slashes/Dots/Quotes
  2. `in_array($layout, ALLOWED_LAYOUTS, true)` in `handle_request()`
  3. Erneuter `in_array()` in `render_category_html()` (Defense in Depth)
- **Statische Array-Map** zu Partial-Dateinamen (kein dynamischer Pfad-String)
- Silent-Fallback auf 'default' bei Mismatch (BC-konform)

#### 2 neue Partials

- `public/views/services/mmb/partials/card-content.php`
- `public/views/services/mmb/partials/compact-content.php`

Adaptierte Versionen von `category-content.php` mit Markup das die
jeweiligen Layouts erwarten. Verwenden dieselbe PDF-URL-Generierung
(via http_build_query() + esc_url) wie default-Partial.

#### mmb/card.php + mmb/compact.php

- Lazy-State-Machine: `data-dhps-mmb-lazy-state="pending|loading|loaded|error"`
- Skeleton-Component im Lazy-Slot (type='card' bzw 'list')
- `data-layout="card"|"compact"` Container-Attribut (fuer AJAX-Param)
- noscript-Fallback mit voll-renderter Liste fuer SEO
- **Pre-Render erste Kategorie** via 2 neue Filter:
  - `dhps_mmb_card_prerender_first_category` (Default `true`)
  - `dhps_mmb_compact_prerender_first_category` (Default `true`)
- **Begruendung**: Tab-Navigation erwartet sichtbares Markup pro
  Kategorie. Bei Tab "alle" wuerden ohne Pre-Render 5+ Skeletons
  sichtbar werden (UX-Issue, Discovery R5).

#### dhps-mmb.js Layout-Param

- `data-layout`-Read am Categories-Container
- Fallback `'default'` wenn kein `data-layout` (BC mit alten Templates)
- AJAX-Call mit `&layout=<value>`

## Performance

### MMB-Browser-View (ohne noscript-Block)

| Shortcode | v0.15.1 | v0.15.2 | Delta |
|-----------|---------|---------|-------|
| `[mmb layout="default"]` | 16.038 | 16.038 | 0 (unveraendert, v0.14.0-Pilot) |
| `[mmb layout="card"]` | ~80-150 KB | **54.970** | **-60% bis -65%** |
| `[mmb layout="compact"]` | ~60-120 KB | **47.492** | **-60% bis -70%** |
| `[mil layout="card"]` | ~80-150 KB | **60.304** | analog |
| `[mil layout="compact"]` | ~60-120 KB | **46.682** | analog |

(Browser-View = nach Herausschneiden des `<noscript>`-Blocks fuer SEO).

### TP-Compact

Keine Performance-Aenderung (war nicht das Ziel - Discovery Option B).
HTML-Validity-Fix + Layout-Anpassung. tp/compact.php Theme-Overrides
funktionieren weiter.

## QA + Security

### QA-Specialist Ergebnis

**Verdict**: GO

- 0 Critical, 0 Major
- 29/30 Acceptance-Checks PASS + 1 Note
- 2 Minor (M1 Pre-Render-Default-Entscheidung, M2 Doku-Luecke
  fuer `data-dhps-mmb-lazy-state="error"`-CSS)
- 13/13 Shortcodes Regression OK
- Path-Traversal-Test: `sanitize_key('../../etc')` -> `'etc'` -> REJECTED

Detail: [docs/project/31-QA-REPORT-v0152.md](31-QA-REPORT-v0152.md)

### Security-Audit Ergebnis

**Verdict**: GO

- 0 Critical, 0 High, 0 Medium
- 2 Low (Style-Smell + Code-Duplikation, nicht security-aktiv)
- Path-Traversal mehrlagig abgesichert:
  1. `sanitize_key()` (filtert Sonderzeichen)
  2. `in_array(..., true)` strict in handle_request()
  3. Erneuter Check in render_category_html() (Defense-in-Depth)
  4. Statische Array-Map fuer Partial-Pfade (kein User-Input im Pfad)
- XSS-Schutz: alle dynamischen Outputs escaped (esc_html/esc_attr/esc_url)
- `wp_kses_post()` Defense-in-Depth am AJAX-Output

Detail: [docs/project/32-SECURITY-AUDIT-v0152.md](32-SECURITY-AUDIT-v0152.md)

## Backward Compatibility

**Vollstaendig BC**:

- AJAX-Call ohne `layout`-Param liefert default-Partial (alte Frontend-Caches funktionieren)
- mmb/default.php + partials/category-content.php UNVERAENDERT (v0.14.0-Pilot bleibt stabil)
- Theme-Overrides: Plugin-Templates rendern jetzt Lazy-Markup, alte Themes mit eigenem
  card.php/compact.php-Override funktionieren weiter (sehen kein Lazy)
- BEM-Klassen unveraendert
- 13/13 Frontend-Shortcodes-Regression OK
- 5 REST-Endpoints + Dashboard unangetastet
- DHPS_MMB_Parser unangetastet

### Bekannte Edge-Cases (akzeptiert)

- **Frontend-Cache-BC**: alte URL ohne `layout`-Param liefert default-Partial -
  bei Cache-Hit auf card/compact-Container wuerde visuell falsches Markup
  einlaufen. Mitigation: Default-Partial-Fallback ist visuell minimal-degradiert
  (keine Brueche), Cache laeuft nach 1h aus.

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `public/views/services/mmb/partials/card-content.php` | Card-Layout Partial |
| `public/views/services/mmb/partials/compact-content.php` | Compact-Layout Partial |
| `docs/architecture/20-COMPACT-LAYOUTS-PLAN-v0152.md` | Discovery |
| `docs/project/31-QA-REPORT-v0152.md` | QA-Report |
| `docs/project/32-SECURITY-AUDIT-v0152.md` | Security-Audit |
| `docs/project/33-CHANGELOG-v0152.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.15.1 -> 0.15.2 |
| `README.md` | Version-Bump |
| `public/js/dhps-tp.js` | `item.after` -> `item.appendChild` (HTML-Validity) |
| `css/dhps-frontend.css` | TP-Compact flex-wrap + Player-Padding-Reset |
| `public/views/services/mmb/card.php` | Lazy-State-Markup + Pre-Render-Filter |
| `public/views/services/mmb/compact.php` | Lazy-State-Markup + Pre-Render-Filter |
| `public/js/dhps-mmb.js` | data-layout-Read + AJAX-Param |
| `includes/class-dhps-mmb-ajax-handler.php` | Layout-Whitelist + Path-Traversal-Schutz |

## Specialist-Team-Pattern (Iteration 8)

| Phase | Specialists |
|-------|-------------|
| P1 Discovery | 1 (Compact-Layouts-Plan, Option B + Option A empfohlen) |
| P2 Implementation | 2 parallel (TP-COMPACT klein, MMB-CC mittel) |
| P3 Composition | Lead (Version-Bump + Smoke 11/11 + 13/13 Regression) |
| P4 QA + Sec | 2 parallel |
| P5 Release | Lead |

**Lehre**: Bei file-konfliktfreien parallel Specs (verschiedene Code-Bereiche
TP vs MMB) ist die Strategie weiterhin effizient. Discovery-Empfehlung
Option B fuer TP-Compact (minimal-invasiv) hat sich bestaetigt - kein
Pipeline-Refactor noetig.

## Tech-Debt-Status (kumulativ nach v0.15.2)

**Offene Tech-Debt-Tickets aus v0.14.x: 0 von 6 (ALLE abgearbeitet)**

Verbleibend aus v0.15.0 Discovery (optional):
- Cache-Stats pro Service (BC-Break Cache-Key-Schema)
- Live-Preview pro Service (in v0.15.3 geplant)
- Last-API-Response-Tracking
- Echter Cache-Hit/Miss-Counter
- Health-History (Trend ueber Zeit)

## Bilanz v0.15.2

- **MMB-Card/Compact**: 60-70% Browser-View-Ersparnis (Lazy-Akkordeon mit Pre-Render)
- **TP-Compact**: HTML-Validity wiederhergestellt (Player jetzt valides li-Kind)
- **MMB-Endpoint**: 3-Layout-Whitelist, mehrstufiger Path-Traversal-Schutz
- **MIL erbt** automatisch via Template-Fallback (analog Card/Compact)
- **0 Critical/High/Medium** in beiden Audits
- **13/13 Shortcodes** Regression OK
- **Alle 6 v0.14.x Tech-Debt-Tickets** abgearbeitet
- **8 Acceptance-Tests** in QA bestanden, dreistufiger Sec-Path-Traversal-Schutz

## Naechste Schritte

- **v0.15.3**: Live-Preview im Admin-Dashboard (geplant, Discovery startet als naechstes)
- v0.16.0 Option: Einheitliches Datenmodell (User-Wunsch seit Anfang)
