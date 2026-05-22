# UI/UX-Audit v0.14.0 - Service-Templates

Stand: 2026-05-22 | Specialist B | Research-only (keine Code-Aenderungen).

Untersucht wurden alle Default-Templates der modernisierten Services
plus zentrale Asset-Dateien:

- Templates: [public/views/services](../../public/views/services)
  ([mio/default.php](../../public/views/services/mio/default.php),
   [mmb/default.php](../../public/views/services/mmb/default.php),
   [tp/default.php](../../public/views/services/tp/default.php),
   [maes/default.php](../../public/views/services/maes/default.php),
   [tc/default.php](../../public/views/services/tc/default.php),
   [tpt/default.php](../../public/views/services/tpt/default.php),
   [maes/videos.php](../../public/views/services/maes/videos.php),
   [maes/merkblaetter.php](../../public/views/services/maes/merkblaetter.php),
   [maes/aktuelles.php](../../public/views/services/maes/aktuelles.php),
   [mmb/compact.php](../../public/views/services/mmb/compact.php))
- CSS: [css/dhps-frontend.css](../../css/dhps-frontend.css) (2.445 Zeilen)
- JS: [public/js/dhps-mio.js](../../public/js/dhps-mio.js) (1.246 Z.),
       [public/js/dhps-mmb.js](../../public/js/dhps-mmb.js) (368 Z.),
       [public/js/dhps-tp.js](../../public/js/dhps-tp.js) (695 Z.)

---

## 1. Defizit-Score (1=ok / 5=kritisch)

Achsen in der vorgegebenen Wichtigkeitsreihenfolge.

| Service | RenderVol | Filter/Suche | LazyLoad | Skeleton | A11y | Responsive | Visual | Mittel |
|---------|-----------|--------------|----------|----------|------|------------|--------|--------|
| MIO     | 2 | 2 | 2 | 2 | 2 | 3 | 3 | 2,3 |
| MMB     | 5 | 3 | 3 | 3 | 3 | 4 | 3 | 3,4 |
| MIL     | 5 | 3 | 3 | 3 | 3 | 4 | 3 | 3,4 |
| TP      | 4 | 2 | 2 | 4 | 2 | 3 | 3 | 2,9 |
| TPT     | 1 | - | 2 | 3 | 2 | 3 | 3 | 2,3 |
| TC      | 3 | 5 | 5 | 4 | 4 | 5 | 5 | 4,4 |
| MAES    | 4 | 5 | 3 | 4 | 3 | 4 | 3 | 3,7 |
| LP      | 4 | 2 | 2 | 4 | 2 | 3 | 3 | 2,9 |
| LXMIO   | 2 | 2 | 2 | 2 | 2 | 3 | 3 | 2,3 |

Hauptproblemzonen: **MMB/MIL (Render-Volumen)**, **TC (Fremd-HTML, keine
eigene UX)**, **MAES (kein Filter/Suche, keine Pagination)**.

---

## 2. Findings pro Service

### MIO / LXMIO (Defizit-Mittel 2,3)

1. **Render-Volumen klein, weil AJAX**. Default-Template rendert nur
   Suchleiste + leeren News-Container
   ([mio/default.php:124](../../public/views/services/mio/default.php#L124)).
   Spinner + `aria-live="polite"` sind vorhanden - solides Baseline-Pattern.

2. **Client-side Pagination ohne Skeleton**. Beim "Mehr laden" wird der
   Load-More-Button kurz entfernt, ohne Placeholder
   ([dhps-mio.js:118](../../public/js/dhps-mio.js#L118)). Bei langsamer
   Verbindung sieht der User leeren Raum.

3. **Steuertermine: feste 2-Spalten-Grid via Filter**. Keine Container-
   Queries, nur 768px-Breakpoint
   ([dhps-frontend.css:720](../../css/dhps-frontend.css#L720)).
   In schmalen Elementor-Spalten kollabiert das Layout zu frueh.

4. **Search-Input ohne Live-Suggestions**. Submit-only via Enter/Button
   ([mio/default.php:107](../../public/views/services/mio/default.php#L107)).
   Modern: Debounced `input`-Event + Suggest-Dropdown.

5. **A11y solide**. `screen-reader-text`-Klasse wird genutzt, aber im
   CSS NICHT definiert (Grep: 0 Treffer fuer `.screen-reader-text` in
   [dhps-frontend.css](../../css/dhps-frontend.css)). Plugin verlaesst
   sich auf WP-Core-Theme - bricht bei minimalistischen Themes.

### MMB / MIL (Defizit-Mittel 3,4)

1. **Render-Volumen kritisch (>300 KB)**. Alle Rubriken inklusive
   aller Fact-Sheet-Titel + Beschreibungen + PDF-Links werden vollstaendig
   im Initial-HTML emittiert
   ([mmb/default.php:118](../../public/views/services/mmb/default.php#L118)).
   Bei 200+ Merkblaettern ist das massiv. Loesungen: nur erste Rubrik
   server-rendern, Rest per AJAX-on-Demand bei Akkordeon-Open.

2. **Filter-Bar fehlt im Default-Template**. Nur Compact/Card haben
   `data-dhps-mmb-filter-bar`
   ([mmb/compact.php:55](../../public/views/services/mmb/compact.php#L55)).
   Im Default-Template fehlt sowohl der Filter-Bar-Container als auch
   das `data-category="..."` Attribut auf den Kategorien
   ([mmb/default.php:90](../../public/views/services/mmb/default.php#L90)).
   Inkonsistente Feature-Parity zwischen Layouts.

3. **Such-Ergebnisse rendern Inline-HTML ohne Pagination**.
   [dhps-mmb.js:222](../../public/js/dhps-mmb.js#L222) baut Liste aus
   ALLEN Treffern. Bei sehr generischen Queries (z.B. "Steuer")
   kann das hunderte Items sein.

4. **Doppeltes Akkordeon erfordert zwei Klicks**. Erst Rubrik oeffnen,
   dann Merkblatt oeffnen
   ([mmb/default.php:122](../../public/views/services/mmb/default.php#L122)).
   Detail-Beschreibung ist gut, aber Klick-Tiefe 2 ist mobiloptimiert
   suboptimal. Card-Layout zeigt Beschreibung direkt
   ([mmb/card.php](../../public/views/services/mmb/card.php)) - sollte
   Default werden.

5. **CSS-Volumen MMB allein ~500 Zeilen** in
   [dhps-frontend.css:767-1200](../../css/dhps-frontend.css#L767). Drei
   Layout-Varianten + Compact-Variante + Card-Variante mit Duplikaten.

### TP / LP (Defizit-Mittel 2,9)

1. **Featured-Video + Grid mit ALLEN Videos im Initial-HTML**
   ([tp/default.php:142](../../public/views/services/tp/default.php#L142)).
   Bei 60 Videos = 60 Poster-Tags. Lazy-Count-Filter existiert
   ([tp/default.php:144](../../public/views/services/tp/default.php#L144))
   und versteckt per `hidden`, aber Bilder werden vom Browser trotzdem
   in den DOM eingehaengt. `loading="lazy"` mildert es.

2. **484ms Render-Zeit** primaer durch API-Call + grosser Parse-Output
   (vermutet). Template-Rendering selbst ist linear. Verbesserung:
   Server-side Cache mit `dhps_tp_payload_*` Transient bereits geplant?

3. **Filter ueber Buttons mit `data-filter=<index>`**
   ([tp/default.php:131](../../public/views/services/tp/default.php#L131))
   - keine echte URL-State-Synchronisation. F5-Reload verliert Filter.

4. **Featured-Video Heading-Hierarchie**: `<h3>` und `<h4>` Featured-
   Video, dann wieder `<h3>` fuer Catalog
   ([tp/default.php:83, 123](../../public/views/services/tp/default.php#L83)).
   In Page-Context kann das zu doppelten H3 fuehren - sollte
   konfigurierbar sein via Filter.

5. **Inline-`style="color:..."`** auf Play-Button
   ([tp/default.php:97](../../public/views/services/tp/default.php#L97)).
   Bricht CSP `style-src 'self'`. Sollte CSS-Klasse sein.

### TPT (Defizit-Mittel 2,3)

1. **Single-Video-Card, sehr klein** - kein Render-Problem
   ([tpt/default.php:43](../../public/views/services/tpt/default.php#L43)).

2. **Eigener Wrapper `dhps-tpt-card` aber re-using `dhps-tp-card__*`**
   Klassen
   ([tpt/default.php:49,55](../../public/views/services/tpt/default.php#L49)).
   Inkonsistent: entweder ein Card-Komponent oder zwei.

3. **`get_option`-Reads im Template**
   ([tpt/default.php:32](../../public/views/services/tpt/default.php#L32)).
   Sollte in den Parser/Pipeline gehoeren (Daten in `$data`-Array).

4. **Kein Skeleton/Empty-State** wenn `$video` null ist - es wird
   einfach `return` aufgerufen
   ([tpt/default.php:28](../../public/views/services/tpt/default.php#L28)),
   dann sieht der Editor nichts.

### TC (Defizit-Mittel 4,4 - KRITISCH)

1. **Fremd-HTML wird unescaped echoed**
   ([tc/default.php:54](../../public/views/services/tc/default.php#L54)).
   Dokumentiert sicher, aber: kein Filter/Such, keine Sortierung, keine
   Visual-Hierarchy moeglich - DHPS hat 0 UX-Kontrolle.

2. **25+ Rechner als monolithisches Akkordeon**. Render-Volumen via
   Deubner-API gross - nicht messbar in HTML-Bytes, aber Inline-JS
   `test_einblenden` ist Legacy.

3. **Empty-State ist GUT** ([tc/default.php:32-49](../../public/views/services/tc/default.php#L32))
   - schoenes Icon + Hinweis. Vorbild fuer andere Services.

4. **Branding-Container nur via Wrapper-Klasse**
   ([tc/default.php:29](../../public/views/services/tc/default.php#L29)).
   Steuern-Gruen-Akzente koennen nur via `:has()` oder Spezifitaets-
   Override durchgreifen
   ([dhps-frontend.css:1846](../../css/dhps-frontend.css#L1846)). Fragil.

5. **A11y unbekannt** - das Fremd-HTML hat keine garantierten
   ARIA-Attribute. DHPS koennte einen Mutation-Observer einsetzen,
   um `role="region"` etc. nachzuruesten.

### MAES (Defizit-Mittel 3,7)

1. **Inline-Script im aktuelles-Template**
   ([maes/aktuelles.php:69](../../public/views/services/maes/aktuelles.php#L69)).
   Bricht CSP, blockt Caching, schwer testbar. Sollte in
   `dhps-maes-aktuelles.js` ausgelagert werden.

2. **Keine Suche/kein Filter ueber MAES-Sektionen**
   ([maes/default.php:42](../../public/views/services/maes/default.php#L42)).
   Bei 50+ Videos + 30+ Merkblaettern unzumutbar.

3. **MAES-Videos `compact.php` ohne Lazy-Loading**
   ([maes/videos-compact.php:27](../../public/views/services/maes/videos-compact.php#L27)).
   Liste rendert alle Videos vollstaendig - kein `lazy_count`-Support.

4. **Description-Truncation hart codiert auf 120 Zeichen**
   ([maes/default.php:67](../../public/views/services/maes/default.php#L67),
   `mb_strimwidth(... 120 ...)`). Sollte CSS `line-clamp` sein - dann
   responsiv.

5. **Doppelter JS-Enqueue** ([maes/default.php:33-34](../../public/views/services/maes/default.php#L33)):
   `dhps-tp-js` + `dhps-mmb-js`. Bei
   isolierter `[maes_videos]` ohne Akkordeon-Bedarf trotzdem MMB-JS
   geladen. Modular ausbauen.

---

## 3. Quer-Patterns (Universelle Defizite)

Diese Defizite tauchen in mehreren Services auf und sollten in einem
Shared-Component-System v0.14.0 geloest werden:

### 3.1 Skeleton/Loading-State fehlt

Aktuell: nur `<span class="dhps-news__spinner">` als Lade-Indikator
([dhps-frontend.css:290](../../css/dhps-frontend.css#L290)). Spinner ist
binaerer Hint ("etwas passiert"). Moderne UX: Skeleton-Cards mit der
Form des erwarteten Contents (Grid-Schimmer fuer TP-Videos, Listen-
Schimmer fuer MMB-Sheets, Card-Schimmer fuer MAES-Aktuelles).

Empfehlung: `dhps-skeleton`-Komponente mit BEM-Modifikatoren
`--card`, `--list`, `--video`, `--accordion`. Shimmer via
`background-position`-Animation. CSS-only, kein JS noetig.

### 3.2 Filter-Bar als Shared-Component

`dhps-filter-bar` existiert bereits
([dhps-frontend.css ab ~770](../../css/dhps-frontend.css#L770) und in
Templates [tp/default.php:126](../../public/views/services/tp/default.php#L126),
[mmb/compact.php:56](../../public/views/services/mmb/compact.php#L56)).
Inkonsistente Nutzung: MMB-Default hat keine, TP rendert immer,
MAES hat gar keinen Filter.

Empfehlung: einheitliches `dhps-filter-bar`-Partial-Template
(`public/views/partials/filter-bar.php`) mit Pflicht-Attributen
`data-target`, `data-filter`, `aria-pressed`. Wiederverwendbar in
allen Services.

### 3.3 Search-Bar mit Live-Search + Debounce

MIO und MMB haben Submit-only-Suche. Modern: `input`-Event mit 300ms
Debounce, Mindestens-3-Zeichen, "X" zum Loeschen.

Empfehlung: `dhps-search-bar`-Component mit konfigurierbarem
`data-min-chars`, `data-debounce-ms`. In `dhps-frontend.js`
zentralisieren.

### 3.4 A11y-Mindeststandards einheitlich

- `.screen-reader-text` ist in keinem CSS-File definiert - bricht
  ohne WP-Core-Theme. Muss in [dhps-frontend.css](../../css/dhps-frontend.css)
  oder besser [dhps-design-tokens.css](../../css/dhps-design-tokens.css).
- `:focus-visible` nur an 7 Stellen
  ([dhps-frontend.css:266, 481, 1352, 1353, 1616, 1778, 2157](../../css/dhps-frontend.css#L266)).
  Sollte fuer alle Buttons/Links existieren.
- `prefers-reduced-motion` wird nirgends respektiert (0 Treffer in CSS).
  Spinner und Hover-Transitions sollten unterdrueckt werden.
- Heading-Hierarchie variiert (h3/h4) - sollte via Filter konfigurierbar
  werden.

### 3.5 Inline-Styles + Inline-Scripts

- TP/MAES Play-Buttons mit `style="color: var(...)"`
  ([tp/default.php:97](../../public/views/services/tp/default.php#L97),
  [maes/videos.php:55](../../public/views/services/maes/videos.php#L55)).
- MAES-Aktuelles mit Inline-`<script>`
  ([maes/aktuelles.php:69](../../public/views/services/maes/aktuelles.php#L69)).

Bricht CSP `'self'`-Policies, blockt Browser-Cache. Vor v0.14.0
auslagern.

### 3.6 Container-Queries fuer Editor-Kontext

Plugin laeuft in Elementor-Spalten. `@media (max-width: 768px)` ist
Viewport-basiert - in einer 33%-Spalte am Desktop kollabiert das Layout
NICHT, obwohl es muesste. Moderne Loesung: `container-type: inline-size`
auf `.dhps-service` + `@container`-Queries.

### 3.7 Truncation via CSS statt PHP

`mb_strimwidth(... 120 ...)` erscheint 6x
([Grep-Befund: maes/default.php:67](../../public/views/services/maes/default.php#L67),
[maes/card.php:65](../../public/views/services/maes/card.php#L65),
[maes/merkblaetter-card.php:55](../../public/views/services/maes/merkblaetter-card.php#L55),
[maes/videos-card.php:54](../../public/views/services/maes/videos-card.php#L54),
[maes/videos.php:65](../../public/views/services/maes/videos.php#L65),
[mmb/card.php:130](../../public/views/services/mmb/card.php#L130),
[tp/card.php:130](../../public/views/services/tp/card.php#L130)).
Fixe Pixel-Zeichenzahl ist responsive-feindlich. Loesung:
`-webkit-line-clamp: 2/3` pro Card-Komponent. Server liefert vollen
Text, CSS truncatet visuell.

### 3.8 URL-State / Deep-Linking

Filter, Tabs, geoeffnete Akkordeons sind nicht in der URL persistiert.
Browser-Back verliert State. Empfehlung: optionaler
`history.replaceState`-Hook via `dhps_url_state_enabled` Filter.

---

## 4. Top-10-Prioritaeten v0.14.0 (User-Impact / Aufwand)

Geordnet nach `Impact / Aufwand` (hoechster ROI zuerst).

1. **Skeleton-Component fuer MMB/TP/MAES** (Aufwand: M, Impact: Hoch).
   `dhps-skeleton`-CSS + `<template>`-Snippets vor AJAX-Inserts.
   Verbessert wahrgenommene Performance fuer den 484ms-TP-Render
   und die >300KB-MMB-Renders.

2. **MMB: AJAX-on-Demand fuer Akkordeon-Inhalte** (Aufwand: L, Impact:
   Sehr hoch). Initial nur Rubrik-Titel + Counts rendern, Fact-Sheets
   beim Open laden. Halbiert >300KB-Render leicht. Backend-Endpoint
   `dhps_mmb_category_load` ergaenzen.

3. **MAES-Aktuelles: Inline-Script auslagern**
   ([maes/aktuelles.php:69](../../public/views/services/maes/aktuelles.php#L69))
   (Aufwand: S, Impact: Mittel). Schnelles Win fuer CSP-Compliance.

4. **`.screen-reader-text` + `:focus-visible` global definieren**
   (Aufwand: S, Impact: Mittel). Plugin-eigenes a11y-Baseline in
   [dhps-design-tokens.css](../../css/dhps-design-tokens.css).

5. **Live-Search mit Debounce fuer MIO+MMB** (Aufwand: M, Impact: Hoch).
   `dhps-search-bar`-Component konsolidieren, Debounced-Input statt
   Submit-only.

6. **Container-Queries auf `.dhps-service`** (Aufwand: M, Impact: Mittel).
   `container-type: inline-size` + Refactor der 9 `@media`-Queries.
   Macht das Plugin Elementor-Spalten-tauglich.

7. **CSS `line-clamp` statt `mb_strimwidth`** (Aufwand: S, Impact: Mittel).
   In 7 Card-Templates ersetzen. Server liefert vollen Text, CSS
   truncatet. Responsive + SEO-freundlich.

8. **MMB-Default: Filter-Bar nachruesten** (Aufwand: S, Impact: Mittel).
   `data-category`-Attribute + Filter-Bar-Markup analog zu compact.php
   ([mmb/compact.php:56](../../public/views/services/mmb/compact.php#L56)).

9. **MAES-Videos: `lazy_count` + Filter** (Aufwand: M, Impact: Mittel).
   Analog zu TP. Bei vielen Videos heute hart geladen.

10. **`prefers-reduced-motion`-Block global** (Aufwand: S, Impact: Niedrig
    aber wichtig). Spinner-Rotation + Hover-Transitions in
    [dhps-frontend.css](../../css/dhps-frontend.css) deaktivieren.

---

## Anhang: Backward-Compat-Hinweis

Alle Empfehlungen sind unter dem Semantic-BC-Vertrag des Plugins:
Shortcode-API + Option-Keys + Filter-Hooks bleiben stabil. HTML-Struktur,
CSS-Klassen, JS-Selektoren duerfen sich aendern. Themes, die das Plugin
ueber das Theme-Override-Verzeichnis `{theme}/dhps/services/{service}/`
ueberschreiben, muessen ggf. nachgezogen werden - das ist im
Theme-Override-Vertrag akzeptiert.
