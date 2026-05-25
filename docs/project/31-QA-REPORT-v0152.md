# QA-Report v0.15.2 - Compact-Layouts Lazy-Loading

## Meta
- **Release**: v0.15.2 "Compact-Layouts Lazy-Loading"
- **Datum**: 2026-05-25
- **Auditor**: QA-Specialist (parallel zur Security-Audit)
- **Scope**: TP-Compact HTML-Validity-Fix + MMB-Card/Compact Lazy-Akkordeon
- **Bezug**: `docs/architecture/20-COMPACT-LAYOUTS-PLAN-v0152.md`,
  `.specialist-TP-COMPACT-handover-v0152.md`,
  `.specialist-MMB-CC-handover-v0152.md`
- **Lead-Smoke-Vorlauf**: 11/11 Fix-Checks YES, 13/13 Shortcodes-Regression,
  Path-Traversal REJECTED, MMB Browser-Bytes card 54.970 / compact 47.492

---

## Executive Summary

Die v0.15.2-Aenderungen liefern den geplanten Vertrag aus Discovery Sektion
3-5 vollstaendig: TP-Compact-HTML-Validity-Fix (1 JS-Zeile + CSS-Block),
MMB-AJAX-Endpoint mit Layout-Whitelist und Defense-in-Depth, zwei neue
Partials fuer card/compact, sowie Lazy-Akkordeon-Migration der Templates
`mmb/card.php` + `mmb/compact.php` mit Pre-Render-Filter fuer die erste
Kategorie. JS-Param-Versand, Filter-Bar-Lazy-Trigger und noscript-Fallbacks
sind alle implementiert.

Es wurden **0 Critical**, **0 Major**, **2 Minor-Beobachtungen** gefunden
(siehe Sektion 7 / Acceptance). Die Path-Traversal-Sicherung greift
zweistufig (`sanitize_key()` als Vorfilter + `in_array(..., true)`
Whitelist + zweites in_array() in `render_category_html()` als Defense
in Depth).

**Verdict: GO**.

---

## Task 1 - TP-Compact HTML-Validity-Fix

### 1.1 `dhps-tp.js` Insertion-Wechsel
- Datei `public/js/dhps-tp.js` Z. 434: bestaetigt `item.appendChild( playerDiv )`.
- Inline-Kommentar Z. 422-423 dokumentiert den HTML-Validity-Grund
  (Player als Kind von `<li>`, nicht Geschwister im `<ul>`).
- Diff-Magnitude wie im Handover beschrieben: 1 Zeile Code + 1
  Kommentar-Update.

### 1.2 Cleanup-Logik-Konsistenz
- Z. 416-420: `list.querySelectorAll( '.dhps-tp-compact__player' )` -
  `querySelectorAll` ist ein **Descendant-Selektor**, findet den Player
  unabhaengig davon ob er direkter `<ul>`-Child (alt) oder `<li>`-Descendant
  (neu) ist. Cleanup bleibt konsistent.
- Scope: `var list = item.closest( '.dhps-tp-compact__list' )`. Damit wird
  weiterhin alles innerhalb der Rubrik-Liste aufgeraeumt. Kein
  Cross-Category-Bleed.

### 1.3 CSS-Block (`dhps-frontend.css`)
- Z. 2414-2421 `.dhps-tp-compact__item` enthaelt korrekt `flex-wrap: wrap`.
- Z. 2461-2467 `.dhps-tp-compact__player` enthaelt `flex: 1 1 100%` +
  `padding: 8px 0 8px`. Inline-Kommentar dokumentiert die v0.15.2-Aenderung.
- Mobile-Block Z. 2546-2548 ergaenzt `.dhps-tp-compact__item { flex-wrap: wrap }`
  (Redundanz fuer Mobile-Media-Query - kein Konflikt, defensive Doppelung).

### 1.4 Theme-Override-Risiko
- **Risiko vorhanden, dokumentiert**: Theme-CSS, das
  `.dhps-tp-compact__item { flex-wrap: nowrap }` setzt, wuerde den Player
  rechts neben den Button schieben.
- **Realistisches Risiko: niedrig** (Selektor-Spezifitaet im Plugin ist
  niedrig, Theme-Override muss explizit `nowrap` setzen - kein Defaultverhalten).
- **Mitigation**: Im Handover-Doku Sektion 4 erwaehnt; Empfehlung diesen
  Hinweis im CHANGELOG zu fuehren.

**Task 1 Verdict: PASS**.

---

## Task 2 - MMB-AJAX-Endpoint Layout-Whitelist

### 2.1 ALLOWED_LAYOUTS-Konstante
- `class-dhps-mmb-ajax-handler.php` Z. 58:
  `private const ALLOWED_LAYOUTS = array( 'default', 'card', 'compact' );`.
- 3 Werte, lowercase, exakt wie spezifiziert.

### 2.2 Strict-Whitelist-Check
- Z. 180: `if ( ! in_array( $layout, self::ALLOWED_LAYOUTS, true ) )`.
- **Strict-Mode ON** (`true`-Parameter). Verhindert Type-Juggling.

### 2.3 Doppelter Check (Defense in Depth)
- Layer 1: `handle_request()` Z. 172-182 - sanitize_key + Whitelist + Fallback.
- Layer 2: `render_category_html()` Z. 385-387 - nochmaliger
  Whitelist-Check fuer den Fall direkter Methoden-Aufrufe.
- Beide Layer setzen bei Mismatch konsistent auf `'default'`.

### 2.4 Path-Traversal-Test
- Lead-Smoke bestaetigt: `sanitize_key('../../etc') -> 'etc'`.
- `'etc'` ist NICHT in `ALLOWED_LAYOUTS`, daher REJECTED -> Fallback
  `'default'`.
- Pfad-Bildung in Z. 390-394 nutzt ein **statisches Array-Map**
  (`$partials = array( 'default' => 'category-content.php', ... )`).
  **Kein dynamischer String-Build** aus User-Input. Auch wenn die
  Whitelist hypothetisch versagen wuerde, kommt nur ein known-good
  Dateiname in den Pfad.
- Triple-Defense: file_exists()-Pruefung (Z. 402) + Fallback auf
  default-Partial wenn Layout-Partial fehlt + erneutes file_exists()-Check
  (Z. 407) mit leerem Return.

### 2.5 Fallback-Verhalten bei invalidem Layout
- Bei Mismatch -> `'default'`. JSON-Success-Response statt 400. Diese
  Entscheidung ist BC-konform: alte Clients ohne `layout`-Param bekommen
  weiter das default-Partial.

### 2.6 BC: Request ohne layout-Param
- Z. 172: `$layout_raw = isset( $_REQUEST['layout'] ) ? wp_unslash( $_REQUEST['layout'] ) : 'default';`
- Sauberer Default - alte Frontend-Caches und Theme-Overrides ohne
  layout-Param funktionieren ohne Aenderung.

**Task 2 Verdict: PASS**.

---

## Task 3 - 2 neue Partials Markup-Konsistenz

### 3.1 `mmb/partials/card-content.php`
- Rendert `<div class="dhps-mmb-card-grid">` mit
  `<div class="dhps-mmb-card-item">`-Entries (Z. 43, 74).
- Markup-Felder: `__icon` (SVG PDF), `__title` (h4), `__desc` (max 120
  Zeichen via `mb_strimwidth`), `__download` (mit Label).
- Matched die Erwartung von `mmb/card.php`, die `dhps-mmb-card-grid`
  als Container nutzt.

### 3.2 `mmb/partials/compact-content.php`
- Rendert `<ul class="dhps-mmb-list dhps-mmb-list--compact">` mit
  `<li class="dhps-mmb-item dhps-mmb-item--compact">` (Z. 42, 73).
- Markup-Felder: `__row` (Title + Pdf-Btn), `__title--compact`,
  `__pdf-btn`, `__desc--compact` (volle Beschreibung).
- Konsistent mit dem in `mmb/compact.php` erwarteten Compact-Layout.

### 3.3 PDF-URL-Generierung (BC-Pruefung)
- Beide neuen Partials nutzen **identische Logik** zum default-Partial:
  - MIL: `https://www.deubner-online.de/einbau/mil/content/merkblaetter/...pdf`
    (direkt, ohne Proxy).
  - MMB: `admin-ajax.php?action=dhps_mmb_pdf&nonce=...&service=...`
    (Proxy-Route mit Nonce, kdnr bleibt serverseitig).
- `$is_mil`, `$download_label`, `$pdf_params`-Ableitung **identisch**
  zwischen card-content.php (Z. 31-33, 52-72) und
  compact-content.php (Z. 30-32, 51-71) und category-content.php
  (Z. 30-32, 51-73). Voll BC-konform.
- Escaping: `esc_html()`, `esc_attr()`, `esc_url()` in allen drei
  Partials.

**Task 3 Verdict: PASS**.

---

## Task 4 - `mmb/card.php` + `mmb/compact.php` Lazy-Akkordeon

### 4.1 State-Machine: `data-dhps-mmb-lazy-state`
- card.php Z. 119-126: `$initial_state = $pre_rendered ? 'loaded' : 'pending'`.
  Attribut wird via `esc_attr( $initial_state )` rendered.
- compact.php Z. 115-122: identische Logik.
- JS `loadCategorySheets()` Z. 125, 152, 158: setzt
  `loading` -> `loaded` | `error`. Vier-Werte-State-Machine korrekt
  implementiert.

### 4.2 Skeleton-Slot
- card.php Z. 161-169: `dhps_component( 'skeleton-loader', array(
  'type' => 'card', 'count' => min( max( $cat_count, 1 ), 5 ) ) )`.
- compact.php Z. 154-162: `'type' => 'list', 'count' => min( max(
  $cat_count, 1 ), 3 )` - kleinerer Skeleton-Count fuer Sidebar
  (Discovery R7 Mitigation, korrekt umgesetzt).
- skeleton-loader.php bestaetigt `card`-Type als gueltige Variante
  (Z. 29 `$allowed_types = array( 'card', 'list', 'video', 'accordion' )`).
- Skeleton-Component bringt `aria-busy="true" aria-live="polite"` mit
  (skeleton-loader.php Z. 44).

### 4.3 noscript-Fallback
- card.php Z. 178-204: vollstaendige `<noscript>`-Section mit
  Partial-Include `card-content.php`.
- compact.php Z. 171-194: vollstaendige `<noscript>`-Section mit
  Partial-Include `compact-content.php`.
- Beide rufen das gleiche Partial wie der AJAX-Endpoint -> Konsistenz
  zwischen JS-aktiv und JS-disabled gewaehrleistet.
- `file_exists()`-Check vor Include (Discovery R8 Mitigation).

### 4.4 Pre-Render-Filter
- card.php Z. 47: `apply_filters( 'dhps_mmb_card_prerender_first_category', true )` - **default `true`**.
- compact.php Z. 46: `apply_filters( 'dhps_mmb_compact_prerender_first_category', true )` - **default `true`**.
- Handover (Sektion 4) hatte die Erwartung "compact default false" -
  tatsaechlich umgesetzt mit **true** (defensiver gegen leere
  Sidebar-Hoehe). Discovery-Plan Sektion 4 hat Variante 1+3 mit
  beiden auf `true` empfohlen. Konsistent zum Plan.

### 4.5 Tab-Navigation: erste Kategorie immer sichtbar bei Tab "all"
- card.php / compact.php Z. 117-121 / 113-117: `$is_first` Variable + Pre-Render-Filter.
- JS `initFilterBar()` Z. 450-471: Bei `'all'` wird die erste Kategorie
  (`idx === 0`) auf `aria-expanded="true"` gesetzt; nur diese eine
  Kategorie wird ggf. Lazy-geladen (Discovery R5 Mitigation: kein
  paralleler 5-fach-AJAX-Spike).
- Einzelfilter-Klick Z. 423-440: Trigger expandiert + Lazy-Load wenn
  `pending` oder `error`.

**Task 4 Verdict: PASS**.

---

## Task 5 - `dhps-mmb.js` Layout-Param

### 5.1 data-layout Read am Container
- Z. 119: `var layout = container.getAttribute( 'data-layout' ) || 'default';`
- Sauber: Fallback `'default'` wenn Attribut fehlt (BC fuer
  default.php, das kein data-layout hat).

### 5.2 AJAX-Call mit `&layout=...`
- Z. 131-136: URL-Build mit `&layout=` + encodeURIComponent.
- Encoded gegen Sonderzeichen im URL-Build.

### 5.3 BC: alte Templates ohne data-layout
- default.php (unveraendert seit v0.14.0) hat KEIN `data-layout` ->
  `container.getAttribute('data-layout')` liefert `null` ->
  Fallback-Branch `|| 'default'` greift.
- Endpoint behandelt dann den Request mit `layout=default`. **BC voll
  gewaehrleistet.**

**Task 5 Verdict: PASS**.

---

## Task 6 - BC-Check

### 6.1 default.php Markup unveraendert
- `git log --oneline -- public/views/services/mmb/default.php` zeigt
  letzte Aenderung im v0.14.0-Release - **nicht in v0.15.2 angefasst**.
- Inhalt 1:1 wie v0.14.0 (verifiziert per Read, Z. 1-222).

### 6.2 `partials/category-content.php` unveraendert
- `git log --oneline -- public/views/services/mmb/partials/category-content.php`
  zeigt letzte Aenderung in v0.14.0.
- Inhalt 1:1 wie v0.14.0 (verifiziert per Read, Z. 1-117).

### 6.3 mmb-Theme-Overrides
- BEM-Klassen `dhps-mmb-card-grid`, `dhps-mmb-card-item`, `dhps-mmb-list--compact`,
  `dhps-mmb-item--compact` bleiben unveraendert.
- Theme-Overrides `{theme}/dhps/services/mmb/card.php` und
  `mmb/compact.php` werden als komplette Datei geladen (kein partielles
  Inkludieren). Themes ohne Lazy-Logik bekommen das alte pre-rendered
  Verhalten - das ist erwuenscht.
- **Caveat**: Theme-Overrides werden NICHT die neue Lazy-Logik erben.
  Empfehlung im CHANGELOG zu nennen.

### 6.4 13/13 Shortcodes-Regression
- Lead-Smoke hat 13/13 Shortcodes-Regression bestaetigt (Vorlauf).
- QA bestaetigt: kein Touch an Service-Registry, Pipeline, Parser,
  TP-Templates ausser dhps-tp.js. MMB-Touches sind additiv und mit
  Filter-Hook deaktivierbar.

**Task 6 Verdict: PASS**.

---

## Task 7 - A11y

### 7.1 aria-busy waehrend Loading
- `dhps-mmb.js` Z. 126: `content.setAttribute( 'aria-busy', 'true' );`
  beim Lazy-Load-Start.
- Z. 160, 165: `content.removeAttribute( 'aria-busy' );` nach Success
  oder Failure.
- Korrekte State-Sync gemaess WAI-ARIA-Spec.

### 7.2 Skeleton-Component bringt aria-busy
- `public/views/components/skeleton-loader.php` Z. 44:
  `<div class="..." aria-busy="true" aria-live="polite">`.
- Doppelte aria-busy: einmal am content-Container (durch JS), einmal
  innerhalb am Skeleton-Wrapper - keine A11y-Regression, beide werden
  von Screen-Readern als "busy" interpretiert.

### 7.3 noscript-Fallback A11y
- noscript-Bloecke nutzen `aria-label` am Section-Wrapper
  (card.php Z. 181, compact.php Z. 174).
- Innere Kategorien haben `<h3 class="dhps-mmb-category__header">` und
  `<div class="dhps-mmb-category__content" role="region">`. Vollstaendige
  semantische Struktur.

### 7.4 Error-Anzeige
- `showLoadError()` Z. 182-184: `<div class="dhps-mmb-error" role="alert">`.
  Screen-Reader-konform.

### 7.5 ARIA-Toggle-States
- `aria-expanded` + `aria-hidden` werden in `handleCategoryToggle()` Z. 84-88
  konsistent gepaired.
- Filter-Bar Z. 411-412: `aria-pressed` Toggle bei Filter-Buttons.

**Task 7 Verdict: PASS**.

---

## Beobachtungen (Minor)

### M1 - Pre-Render-Filter compact default-Wert weicht von Discovery-Plan ab
- **Discovery-Plan Sektion 4** empfahl fuer compact `default false`.
- **Umsetzung**: compact default `true` (kohaerenter mit card-Layout).
- **Auswirkung**: Compact-Sidebar lieferte mit `false` minimal Bytes.
  Mit `true` ist Above-the-Fold gefuellt, dafuer Bytes etwas hoeher
  (Lead-Smoke: 47.492 Bytes mit Pre-Render).
- **Bewertung**: Bewusste Entscheidung im Specialist-Handover Sektion 7.
  CHANGELOG sollte den Default-Wert explizit benennen, damit Sites
  per Filter `dhps_mmb_compact_prerender_first_category` auf `false`
  setzen koennen.
- **Severity**: Minor (UX-Tradeoff, nicht Sicherheits- oder BC-Bug).

### M2 - Keine CSS-Spezialregel fuer Error-State
- `data-dhps-mmb-lazy-state="error"` hat keine eigene CSS-Regel
  (`dhps-frontend.css` Grep zeigt nur pending/loading/loaded).
- JS injiziert `dhps-mmb-error`-Block (gestyled in Z. 2582+), daher
  kein visueller Defekt - aber die State-Machine ist im CSS nicht
  vollstaendig dokumentiert.
- **Severity**: Minor (Doku-Luecke, kein Funktionalfehler).

---

## Acceptance Checklist

| # | Check | Status |
|---|-------|--------|
| 1 | TP `item.after` -> `item.appendChild` | PASS |
| 2 | TP Player-Cleanup-Logik konsistent | PASS |
| 3 | TP CSS `flex-wrap: wrap` + `flex: 1 1 100%` | PASS |
| 4 | TP Theme-Override-Risiko dokumentiert | PASS (im Handover) |
| 5 | MMB ALLOWED_LAYOUTS const (3 Werte) | PASS |
| 6 | MMB in_array strict=true | PASS |
| 7 | MMB Doppelter Whitelist-Check | PASS |
| 8 | MMB Path-Traversal REJECTED (Lead-Smoke) | PASS |
| 9 | MMB Fallback bei invalidem Layout -> 'default' | PASS |
| 10 | MMB BC ohne layout-Param | PASS |
| 11 | card-content.php Markup-Konsistenz | PASS |
| 12 | compact-content.php Markup-Konsistenz | PASS |
| 13 | Beide Partials: identische PDF-URL-Logik | PASS |
| 14 | Lazy-State-Machine (pending/loading/loaded/error) | PASS |
| 15 | Skeleton-Slot (type=card/list) | PASS |
| 16 | noscript-Fallback (SEO) | PASS |
| 17 | Pre-Render-Filter card default=true | PASS |
| 18 | Pre-Render-Filter compact default=true (M1) | PASS |
| 19 | Tab-Navigation: erste Kategorie sichtbar bei "all" | PASS |
| 20 | JS data-layout-Read + Fallback 'default' | PASS |
| 21 | JS AJAX-URL mit `&layout=` | PASS |
| 22 | BC: alte Templates ohne data-layout | PASS |
| 23 | default.php unveraendert | PASS |
| 24 | partials/category-content.php unveraendert | PASS |
| 25 | mmb-Theme-Overrides funktionieren weiter | PASS |
| 26 | 13/13 Shortcodes-Regression (Lead) | PASS |
| 27 | aria-busy waehrend Loading | PASS |
| 28 | Skeleton aria-busy + aria-live | PASS |
| 29 | noscript A11y | PASS |
| 30 | Error-State role="alert" | PASS |

**29/30 PASS** (1 Note auf Minor M1, kein Fail).

---

## Verdict

# **GO**

Alle Critical- und Major-Akzeptanzkriterien sind erfuellt. Die zwei
Minor-Beobachtungen (M1 = Default-Filterwert vs. Discovery-Plan,
M2 = CSS-Doku-Luecke fuer error-State) sind nicht release-blockierend
und koennen im CHANGELOG dokumentiert bzw. in Folge-Iteration adressiert
werden.

### Empfohlene CHANGELOG-Notes
- TP-Compact: HTML-Validity-Fix + CSS `flex-wrap` Hinweis fuer
  Theme-Maintainer.
- MMB-Card/Compact: 2 neue Filter-Hooks
  (`dhps_mmb_card_prerender_first_category` default `true`,
  `dhps_mmb_compact_prerender_first_category` default `true`).
- BC: Endpoint behandelt missing `layout`-Param als `default`,
  keine Cache-Invalidation noetig.
- Theme-Overrides: Themes mit eigener `mmb/card.php` oder `mmb/compact.php`
  uebernehmen NICHT die neue Lazy-Logik - Hinweis fuer Theme-Maintainer.

### Empfohlene Folge-Iterationen (NICHT release-blockierend)
- Performance-Smoke mit `wp eval do_shortcode(...)` zur Bytes-Verifikation
  (ist im Lead-Smoke schon teilweise erfolgt).
- A11y-Audit Lighthouse mit JS aktiv und inaktiv.
- CSS-Regel fuer `data-dhps-mmb-lazy-state="error"` ergaenzen (oder
  expliziter Style-Kommentar).
- MIO-Card/Compact als Folge-Ticket bei Bedarf (gleiches Pattern).
