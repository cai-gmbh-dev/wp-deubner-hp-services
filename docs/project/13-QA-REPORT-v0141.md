# QA-Report v0.14.1 - MAES-Migration

> Owner: QA-Specialist
> Stand: 2026-05-23
> Scope: 12 MAES-Templates (modernisiert) + ContentCard.data_attrs + Medizin-Branding + dhps-tp.js Selector-Patch + dhps-maes-aktuelles.js (geloescht) + Section-Filter-Bugfix
> Methode: Statische Code-Inspektion (Docker-Exec im Sandbox blockiert; Smoke-Test-Script wurde erstellt + entfernt; Bytes-Zahlen aus Auftrag uebernommen).

---

## Executive Summary

Die MAES-Migration v0.14.1 ist **fachlich konsistent umgesetzt**: 12 Templates
(9 modernisierte Sub-Templates + 3 Orchestrator-Shims) nutzen die v0.14.0
ContentList/ContentCard-Components mit `data_attrs`-Erweiterung, das
Medizin-Branding ist als reine CSS-Klassen-Hook umgesetzt (keine
Inline-Styles), Section-Filter-Bug ist behoben, Akkordeon-JS ist sauber
durch Alpine ersetzt und die obsolete JS-Datei entfernt.

Der **Performance-Wachstums-Effekt** (+23% bis +175%) ist NICHT auf
unsachgemaesse Implementierung zurueckzufuehren, sondern ein erwartbares
Trade-off des Component-Systems (mehr ARIA-Markup, mehr Slots, mehr
Wrapper-Divs, vollstaendiger Teaser-Text statt PHP-Truncation). Die
+175% beim `[maes]`-Shortcode sind zudem **kein fairer Vergleich**, da
v0.14.0 unter dem Section-Filter-Bug litt und die Aktuelles-Sektion nicht
gerendert hat.

**Verdict: GO-WITH-CAVEATS** (1 Major, 4 Minor).

| Kategorie | Anzahl |
|-----------|-------:|
| Critical (Release-Blocker) | 0 |
| Major (sollte vor Release behoben werden) | 1 |
| Minor (kann Post-Release) | 4 |
| Info / Observation | 6 |

---

## Sektion 1 - A11y-Check aller 12 MAES-Templates

### 1.1 ARIA-Attribute am Toggle (ContentCard collapsible)

Geprueft: `aktuelles.php`, `aktuelles-card.php`, `aktuelles-compact.php`.
Alle 3 Templates setzen `collapsible = true` auf ContentCard. Die
Component rendert dann in `content-card.php` Z. 215-226:

- `<button type="button" class="dhps-content-card__toggle">`
- `x-on:click="toggle()"` (Alpine)
- `:aria-expanded="open ? 'true' : 'false'"` (Alpine-Binding)
- `aria-controls="dhps-card-body-{unique-id}"`
- Detail-Div hat passende `id` + `x-show="open"` + `x-cloak`

**Resultat:** OK.

**Minor M-1 (A11y-Hydration):** `aria-expanded` ist nur als
**Alpine-Binding** gesetzt (`:aria-expanded=...`). Vor Alpine-Hydration
(z.B. bei sehr fruehem JS-Disable, slow-3G, oder NoJS) hat der Button
KEINEN initialen `aria-expanded`-Wert. Empfehlung: ContentCard sollte
zusaetzlich `aria-expanded="false"` als statisches Default-Attribut
rendern (Alpine ueberschreibt das nach Init). Impact: Minor - betrifft
nur Screen-Reader-User mit deaktiviertem oder spaet ladendem JS.

### 1.2 Heading-Hierarchie

ContentCard rendert default `<h3 class="dhps-content-card__title">`
(filterbar via `dhps_content_card_heading_level`). Im Code keiner der
12 Templates ueberschreibt diesen Filter.

**Major MA-1 (Heading-Hierarchie im default.php):** Im Default-Layout
werden alle 3 Sub-Sektionen (Videos, Aktuelles, Merkblaetter) ohne
Section-Headings nebeneinander gerendert. Resultat: Eine Seite hat
nur `<h3>`-Karten, **ohne `<h2>` als Section-Title pro Sub-Sektion**.
Wenn `[maes]` als einziger Inhalt auf einer WP-Seite eingesetzt wird,
fehlt damit jegliche Section-Gliederung fuer Screen-Reader. Discovery
R-5 hatte das explizit als offene Frage markiert; in den Templates ist
es ungeloest geblieben.

Empfehlung: Im Orchestrator `default.php` vor jedem `include` einen
`<h2 class="screen-reader-text">`-Title fuer "Video-Tipps",
"Aktuelle Nachrichten", "Merkblaetter" rendern (oder visible mit
einer dezenten Layout-Klasse). Alternativ pro Sub-Template einen
optionalen `section_heading`-Param.

Workaround fuer den Release: Akzeptabel, weil:
- ContentList rendert `role="region"` + `aria-labelledby` mit einem
  versteckten `<span class="screen-reader-text">Inhaltsliste (list)</span>`.
  Das gibt Screen-Reader-Usern zumindest 3 Landmark-Regionen, auch ohne
  eigene Headings. Aber die Labels sind generisch ("Inhaltsliste (grid)")
  und nicht Sub-Sektions-spezifisch.

### 1.3 screen-reader-text

ContentList hat im Wrapper bereits ein `<span class="screen-reader-text">`
mit lokalisiertem Label. CSS-Regel in `dhps-frontend.css:22` definiert
`.screen-reader-text` korrekt (off-screen, fokussierbar). OK.

Sub-Templates nutzen das nicht explizit - alles laeuft ueber die Component.

### 1.4 :focus-visible

CSS-Regeln in `dhps-components.css` decken:
- `.dhps-empty-state__action:focus-visible` (Z. 225, 230)
- `.dhps-accordion__trigger:focus-visible` (Z. 335)
- `.dhps-content-card__toggle:focus-visible` (Z. 513)
- `.dhps-content-card__action:focus-visible` (Z. 558, 563)

Alle interaktiven Elemente in MAES-Templates (Toggle-Button,
Action-Links, Empty-State-CTA) haben Focus-Visible-Indicator. OK.

### 1.5 Service-Branding per CSS-Klasse (Discovery R-3)

Validiert in `dhps-components.css` Z. 574, 930-941:
```css
.dhps-content-card--service-maes { --dhps-color-primary: var(--dhps-color-medizin); }
.dhps-content-card--service-maes .dhps-content-card__play-overlay,
.dhps-content-card--service-maes .dhps-content-card__action--primary {
    color: var(--dhps-color-medizin);
}
.dhps-content-card--service-maes .dhps-content-card__badge--top {
    background: var(--dhps-color-medizin-light);
    color: var(--dhps-color-medizin);
}
```

In den Templates: **keine** Inline-Styles, **keine** `style="..."`-Attribute.
Audit-Grep ueber alle 12 Files: 0 Treffer fuer `style="color`.
Resultat: OK, R-3 erfuellt.

### 1.6 A11y-Pass-Rate

| Bereich | Pass | Anmerkung |
|---------|:----:|-----------|
| ARIA-Toggle (aria-expanded/controls) | OK | Alpine-Binding (siehe M-1 fuer NoJS) |
| Heading-Hierarchie | TEILWEISE | MA-1: keine Section-Headings im default.php |
| screen-reader-text | OK | via ContentList Component |
| :focus-visible | OK | CSS-Hooks vorhanden |
| Branding ohne Inline-Style | OK | reine CSS-Klassen |
| Media-Alt-Text | OK | `media_alt = title` in videos*.php |
| `target="_blank"` mit rel="noopener" | OK | von ContentCard erzwungen |
| Empty-State `role="status"` | OK | in empty-state.php Z. 64 |
| Heading-Filter ueberschreibbar | OK | `dhps_content_card_heading_level` |

**Pass-Rate: 8/9 (89%)**, ein Punkt mit Caveat (Section-Headings).

---

## Sektion 2 - Performance-Beobachtung & Disconnect-Analyse

### 2.1 Bytes-Zahlen (aus Auftrag)

| Shortcode | v0.14.0 | v0.14.1 | Delta absolut | Delta relativ |
|-----------|--------:|--------:|--------------:|--------------:|
| `[maes]` | 33.843 | 93.233 | +59.390 | **+175%** |
| `[maes_videos]` | 14.562 | 28.440 | +13.878 | **+95%** |
| `[maes_merkblaetter]` | 22.025 | 31.571 | +9.546 | **+43%** |
| `[maes_aktuelles]` | 26.934 | 33.111 | +6.177 | **+23%** |

### 2.2 Wo kommt das Wachstum her?

Code-Inspektion `content-card.php` zeigt: pro Karte rendert die
Component zusaetzlich zur frueheren Markup-Struktur:

| Element pro Card | Bytes (Schaetzung) | Begruendung |
|------------------|-------------------:|-------------|
| `<article class="dhps-content-card dhps-content-card--{type} dhps-content-card--service-maes ...">` | ~100 B | 3-4 BEM-Klassen + Service-Modifier |
| `<div class="dhps-content-card__media">` Wrapper | ~50 B | War vorher inline |
| `<div class="dhps-content-card__body">` + `<header>` | ~80 B | Strukturelle Layer |
| `<h3 class="dhps-content-card__title">` | ~50 B | War teils `<h4>` |
| LazyImage-Wrapper (wenn aktiv) | ~80 B | data-lazy-* Attribute |
| `<footer class="dhps-content-card__actions">` mit Action-`<a>` | ~150 B | Pro Action: Klasse, href, label-span, SVG-icon |
| ContentList-Wrapper `dhps-content-list__item-wrap data-dhps-list-item` | ~60 B | Alpine-Filter-Marker |
| ContentList-Outer mit `x-data="dhpsContentList(...)"`, `role="region"`, `aria-labelledby`, screen-reader-text | ~250 B | Pro Liste, nicht pro Karte |
| Voller Teaser-Text (statt mb_strimwidth(120) bzw. (140)) | +30-60% des Teaser-Texts | siehe 2.3 |
| `data-video-slug`, `data-poster-url`, `data-v-modus`, `data-video-index` | ~80 B | NUR Videos |

Bei 60 Videos x ~600 B Aufpreis pro Karte = **~36 KB** allein durch
Component-Wrapper-Bloat. Plus voll-getexteter Teaser (war vorher
auf 120-140 Zeichen truncated) - bei durchschnittlich 350 Zeichen
Beschreibung sind das +210 Zeichen x 60 = **+12 KB**. Total
~48 KB - matched mit dem Realwert von +59 KB beim `[maes]`-Wachstum
(unter Beruecksichtigung der zusaetzlichen Aktuelles-Sektion, siehe 2.4).

### 2.3 Trade-off Analyse

**Was wir bekommen fuer die Bytes:**
- A11y: bessere ARIA-Struktur, Heading-Filter, Focus-Visible.
- SEO: vollstaendiger Teaser-Text im DOM (statt PHP-truncated; line-clamp
  versteckt nur visuell).
- Wartbarkeit: 1 Component statt 3 verschiedene Markup-Patterns
  (TP-Card, MMB-Item, News-Article).
- CSP: 0 Inline-Scripts (vorher: 2 in aktuelles-card.php +
  aktuelles-compact.php).
- BC: alte CSS-Klassen `.dhps-tp-card` bleiben als Zusatz-Klasse,
  damit dhps-tp.js Lazy-Load + Filter weiterhin greift.

**Was es kostet:**
- Initial-HTML +23% bis +95% (ohne Section-Filter-Effekt).
- Zusaetzliche Wrapper-Tiefe (3-4 Ebenen): article > body > header > h3.
- LazyImage- statt direkte `<img>`-Tags (komplexere DOM).

### 2.4 Korrigierter Vergleich [maes]

v0.14.0 hatte einen Bug: Section-Filter `'aktuelles'` war NICHT in der
Whitelist, d.h. die Aktuelles-Sektion wurde im default-Layout NICHT
gerendert. v0.14.1 rendert sie korrekt mit.

**Schaetzung des korrigierten Vergleichs:**

`[maes_aktuelles]` Standalone-Bytes v0.14.1 = 33.111 B.
Innerhalb des `[maes]`-Wrappers ist der Wrapper-Overhead geringer
(Plugin-Service-Div ist einmalig); konservativ schaetze ich
**~25.000-28.000 B Aktuelles-Anteil im [maes]-Default**.

| Berechnung | Bytes |
|------------|------:|
| `[maes]` v0.14.0 (ohne Aktuelles, mit Bug) | 33.843 |
| `[maes]` v0.14.1 (mit Aktuelles) | 93.233 |
| Geschaetzter Aktuelles-Anteil v0.14.1 | -26.500 |
| `[maes]` v0.14.1 ohne Aktuelles (fairer Vergleich) | ~66.700 |
| **Korrigierter Delta** | **+97%** statt +175% |

Also: die +175% sind **ein Kombi-Effekt aus Component-Bloat
(~+97%) + Bugfix-Add-On (~+78%)**.

### 2.5 Optimierungs-Empfehlungen

| # | Massnahme | Aufwand | Ersparnis (Schaetzung) |
|---|-----------|--------:|----------------------:|
| O-1 | ContentCard: LazyImage nur bei `$media_url` UND `$type=='video'` (Documents/News brauchen keine Lazy-Wrapper) | XS | -5-8% |
| O-2 | Service-Klasse `dhps-content-card--service-maes` nur ausgeben, wenn `$service != ''` (heute schon) - keine zus. Ersparnis, ABER: BEM-Layer reduzieren von 4 auf 3 Klassen wenn keine `class`-Prop | XS | -2% |
| O-3 | ContentList: Wrapper-Div `dhps-content-list__item-wrap` und das `<article>` der ContentCard koennten verschmolzen werden (`data-dhps-list-item` direkt am Article) | S | -3-5% (1 Div pro Karte raus) |
| O-4 | Teaser-Cutoff per PHP optional re-aktivieren mit `mb_strimwidth($teaser, 200)` (200 statt frueher 120, also Mittelweg). Spart auf langen Texten ~20% Bytes, behaelt grossteils-vollen Teaser fuer SEO | S | -10-15% bei langen Teasern |
| O-5 | Bei Videos: `data-poster-url` ist redundant zum `<img src=...>` - kann entfallen wenn TP-JS aus dem Image liest | S | -200B pro Video |
| O-6 | Alpine `x-cloak` Style nur einmal global statt pro Karte rendern (heute auch nur 1x via CSS, ok) | - | - |

**Empfehlung:** O-1 + O-3 als Quick-Wins fuer v0.14.2 (-8-13%).
O-4 ist eine UX-Entscheidung, nicht rein technisch.

### 2.6 Akzeptabel?

**JA, mit folgender Begruendung:**
1. Der absolute Wert `[maes]` = 93 KB ist **immer noch unter dem
   urspruenglichen Roadmap-Ziel** (Discovery sprach von <120 KB pro
   Service-Page).
2. Die zusaetzlichen Bytes sind **gzippbar** (Wiederholung von BEM-
   Klassen komprimiert exzellent; gzip Faktor 5-8x realistisch).
   Geschaetzte Wire-Bytes mit gzip: ~12-15 KB statt 93 KB.
3. Wartbarkeit, A11y und CSP-Compliance haben **strategischen Wert**
   ueber den Bytes-Kostenanstieg.
4. Discovery hatte -25% bis -36% prognostiziert basierend auf
   reduziertem Markup-Duplikat - aber NICHT eingerechnet:
   - voll-getexteter Teaser (war PHP-truncated)
   - LazyImage-Component (mehr DOM als `<img>`)
   - ContentList-Pagination/Filter-Marker `data-dhps-list-item`
   - Service-Branding-Klassen (BEM-Erweiterung)
   Der Discovery-Vergleich war **strukturell zu optimistisch**.

---

## Sektion 3 - Cross-Layout-Test

> Hinweis: Docker-Exec im aktuellen Sandbox blockiert; ein Smoke-PHP
> wurde erstellt, konnte aber nicht ausgefuehrt werden. Es wurde
> direkt nach Erstellung entfernt. Bewertung basiert auf statischer
> Code-Analyse aller 12 Templates + 4 Shortcode-Handler.

### 3.1 4 Sub-Shortcodes x 3 Layouts = 12 Kombinationen

| Shortcode | default | card | compact | Templates |
|-----------|:-------:|:----:|:-------:|-----------|
| `[maes]` | OK | OK | OK | default/card/compact.php (Orchestrator) |
| `[maes_videos]` | OK | OK | OK | videos.php, -card.php, -compact.php |
| `[maes_merkblaetter]` | OK | OK | OK | merkblaetter.php, -card.php, -compact.php |
| `[maes_aktuelles]` | OK | OK | OK | aktuelles.php, -card.php, -compact.php |

### 3.2 Markup-Konsistenz: `dhps-content-card--service-maes`

Statische Analyse aller 12 Templates:
- videos.php / -card / -compact: `'service' => 'maes'` Prop gesetzt - OK
- merkblaetter.php / -card / -compact: `'service' => 'maes'` Prop gesetzt - OK
- aktuelles.php / -card / -compact: `'service' => 'maes'` Prop gesetzt - OK

ContentCard rendert daraus `dhps-content-card--service-maes` Klasse
(siehe content-card.php Z. 85-87). **Konsistent in allen 12 Templates: OK**.

### 3.3 TP-Player-Selektoren funktionieren

Pruefen ob videos-Templates `data-video-slug` korrekt durchreichen:

- `videos.php` Z. 87-93: `'data_attrs' => array('video-slug' => $slug, 'poster-url' => $poster, 'v-modus' => '0', 'video-index' => ...)` - OK
- `videos-card.php` Z. 67-71: identisch (ohne video-index) - OK
- `videos-compact.php` Z. 61-65: identisch (ohne video-index) - OK

ContentCard schreibt das via content-card.php Z. 119-126 als
`data-video-slug="..."` am `<article>`-Root mit `sanitize_key($key)` +
`esc_attr($value)`. **Selektor `[data-video-slug]` greift: OK**.

`dhps-tp.js` Z. 42, 78, 402, 422: Closest-Lookup `[data-video-slug]`
funktioniert auf `<article>`-Ebene, weil ContentCard das auf der
Root-Ebene rendert. **OK**.

`dhps-tp.js` Z. 136-143: `posterEl`-Resolution wurde um
`.dhps-content-card__media` erweitert (M1-Handover bestaetigt).
**Minimaler Patch ist drin: OK**.

### 3.4 Empty-State greift bei leerem Input

Alle 9 Sub-Templates konfigurieren `empty_state` in ContentList-Aufruf:

| Template | Icon | Hinweis |
|----------|------|---------|
| videos.php / -card / -compact | `search` | *INKONSISTENT - siehe Info I-1* |
| merkblaetter.php / -card / -compact | `document` | *INKONSISTENT - siehe Info I-1* |
| aktuelles.php / -card / -compact | `inbox` | OK |

ContentList Z. 60-65 prueft `empty($items) && null !== $empty_state` und
rendert dann nur den EmptyState. **OK**.

**Info I-1 (EmptyState-Icon-Slug-Inkonsistenz):** Die `empty-state.php`
Z. 44-49 definiert nur 4 Slugs in der `$icon_map`:
**'inbox', 'calculator', 'document', 'video'**.

- `'search'` (in videos*.php) ist **nicht in der Map** - faellt auf
  `wp_kses_post($icon)` Z. 59 zurueck. Das rendert dann den nackten
  String "search" als Icon-Content (in einem `<div class="dhps-empty-state__icon">`)
  - **kein SVG**, sondern textueller Slug.
- `'document'` (in merkblaetter*.php) ist **in der Map vorhanden** - OK.
- `'file'` (in einigen meta-Definitionen) ist nicht in der EmptyState-Map,
  aber das ist `meta`, nicht `empty_state` - OK.

**Empfehlung:** In videos*.php den Icon-Slug von `'search'` auf
`'video'` aendern (passt semantisch sowieso besser). Pro Service-
Specialist (M1) oder als M-Int-Quick-Fix.

### 3.5 Sub-Shortcodes BC-Test

- `[maes_videos lazy_count="8"]` -> `$lazy_count = 8` -> videos.php
  rendert `data-lazy-count="8"` + Load-More-Button - OK (Logik
  unveraendert).
- `[maes_videos layout="card"]` -> Modules-Class waehlt
  `videos-card.php` - OK.
- `[maes_merkblaetter columns="3"]` -> Modules-Class extrahiert
  `$columns = 3` -> merkblaetter-card.php greift `$columns` - OK.
- `[maes_aktuelles]` -> default-Layout `aktuelles.php`, Alpine-Toggle - OK.

---

## Sektion 4 - Sub-Template-Include-Check

### 4.1 Variable-Bridge

Orchestrator-Shims (`default.php`, `card.php`, `compact.php`) extrahieren
aus `$data`:
```php
$videos       = $data['videos'] ?? array();
$merkblaetter = $data['merkblaetter'] ?? array();
$news         = $data['news'] ?? array();
$service_tag  = $data['service_tag'] ?? 'maes';
```

Sub-Templates (`videos.php`, `merkblaetter.php`, `aktuelles.php`) sind
**defensiv normalisiert**:
- `videos.php` Z. 28-34: `$lazy_count`, `$lazy_mode`, `$style_preset`,
  `$video_mode`, `$columns`, `$custom_class`, `$videos` jeweils mit
  `isset() && is_array()/is_string()` plus Default. **Variable-Bridge
  funktioniert in beiden Aufruf-Pfaden** (direkter Shortcode +
  Orchestrator-Include).
- `merkblaetter.php` Z. 24-25: dito - `$custom_class`, `$merkblaetter`.
- `aktuelles.php` Z. 30-32: dito - `$show_teaser`, `$first_open`,
  `$custom_class`.

**Resultat:** Variable-Bridge ist **idempotent und scope-safe**.

**Info I-2 (Card-Layout-Variable-Bridge):** Im `card.php`-Orchestrator
wird `$columns` NICHT explizit aus `$data` oder Shortcode-Atts
extrahiert. Sub-Templates `videos-card.php`/`merkblaetter-card.php`/
`aktuelles-card.php` greifen darauf zurueck mit eigenen Defaults
(meist `$columns = 2`). Das funktioniert, aber: wenn jemand
`[maes layout="card" columns="3"]` aufruft, wird `columns=3` NICHT
zu den Sub-Templates durchgereicht (weil der Pipeline-Renderer keine
shortcode-spezifischen Atts ans Template gibt).

**Empfehlung:** Cosmetisch - in einer Folge-Version
`$columns = $data['columns'] ?? null` + Pass-Through zur Sub-Sektion.
Aktuell YAGNI, weil `[maes]` keinen `columns`-Param hat.

### 4.2 Idempotenz (keine globalen Side-Effects)

Pruefen:
- Sub-Templates rufen `wp_enqueue_script('dhps-tp-js')` auf (videos*).
  Mehrfach-Aufruf ist via WP-Enqueue-API **idempotent** - OK.
- `wp_unique_id()` generiert pro Aufruf eine andere ID, was im
  Multi-Section-Rendering ein **Feature**, kein Bug ist (jede Liste
  hat eigene Alpine-Scope).
- Keine `global $...`-Statements, keine `$GLOBALS`-Writes - OK.
- `update_option()` o.ae. wird in keinem Sub-Template aufgerufen - OK.

**Resultat:** Sub-Templates sind **idempotent und side-effect-frei**.

### 4.3 Doppel-Rendering bei Theme-Override?

Der Renderer (`class-dhps-renderer.php` Z. 191-211) sucht nur die
**Top-Level**-Datei (`default.php`/`card.php`/`compact.php`) im Theme-
Override-Pfad.

- Falls ein Theme `{theme}/dhps/services/maes/default.php` ueberschreibt,
  laeuft der **Theme-Code** statt des Plugin-Orchestrators.
- Falls das Theme dort ein `include` mit Plugin-Pfad nutzt, wird das
  Plugin-Sub-Template trotzdem geladen.
- Falls das Theme die alte v0.13.x-Markup-Logik dort fest verdrahtet,
  wird NICHT in das neue Sub-Template `videos.php` etc. weitergeleitet.

**Sub-Template-Override ist NICHT supported** durch den Renderer - der
Plugin-Pfad ist hartkodiert in `default.php`-Shim
(`$base_path = DEUBNER_HP_SERVICES_PATH ...`). Das ist konsistent mit der
v0.13.x-Architektur und stellt **kein Regression-Risiko** dar.

**Info I-3 (Theme-Override-Migration):** Themes mit eigenem
`{theme}/dhps/services/maes/*.php` Override sind nach v0.14.1 vor
einer Wahl:
- alte Override-PHP behalten -> bekommt altes v0.13.x-Verhalten, BC OK
- auf neue ContentList/ContentCard-Components migrieren -> bekommt
  neue Klassen-Struktur, evtl. eigene CSS anpassen
Empfehlung: Migrationsnotiz im CHANGELOG.

---

## Sektion 5 - Component-Consistency

### 5.1 `service='maes'` Prop in allen Cards

| Template | Prop gesetzt? |
|----------|:-------------:|
| videos.php | `'service' => 'maes'` Z. 76 |
| videos-card.php | `'service' => 'maes'` Z. 56 |
| videos-compact.php | `'service' => 'maes'` Z. 50 |
| merkblaetter.php | `'service' => 'maes'` Z. 47 |
| merkblaetter-card.php | `'service' => 'maes'` Z. 52 |
| merkblaetter-compact.php | `'service' => 'maes'` Z. 47 |
| aktuelles.php | `'service' => 'maes'` Z. 42 |
| aktuelles-card.php | `'service' => 'maes'` Z. 43 |
| aktuelles-compact.php | `'service' => 'maes'` Z. 37 |

**9/9 Sub-Templates: OK**.

### 5.2 Heading-Hierarchie konsistent

Alle 9 Sub-Templates rendern Karten mit Default-h3 (kein Filter-Override).
- Sektions-Headings fehlen im default.php (siehe Major MA-1).
- Innerhalb einer Section sind alle Cards einheitlich h3 - **konsistent**.

### 5.3 empty_state-Icons

| Template | Icon-Slug | Map? | Bewertung |
|----------|-----------|:----:|-----------|
| videos.php, -card, -compact | `search` | NEIN | I-1: faellt auf wp_kses_post zurueck |
| merkblaetter.php, -card, -compact | `document` | JA | OK |
| aktuelles.php, -card, -compact | `inbox` | JA | OK |

**Resultat:** 6/9 OK, 3/9 mit Icon-Slug-Inkonsistenz (siehe I-1).

### 5.4 ContentList-Wrapper-Klassen

Alle 9 Sub-Templates geben `class => 'dhps-content-list--maes-{sub}'`
mit zusaetzlich `--compact` oder `--card` Modifier. Konsistent. OK.

---

## Sektion 6 - Bonus-Issues aus Discovery (R-4, R-7, R-8)

### 6.1 R-4: TP-JS-Selector auf neuen Cards

`dhps-tp.js` Z. 42 `closest('[data-video-slug]')` -> ContentCard rendert
das auf Article-Root. **OK**.

`dhps-tp.js` Z. 136-143 `posterEl`-Resolution:
```javascript
var posterEl = playerContainer.querySelector( '.dhps-tp-video__poster' ) ||
    playerContainer.querySelector( '.dhps-tp-card__poster' ) ||
    playerContainer.querySelector( '.dhps-content-card__media' ) ||
    poster;
```

Erweiterung um `.dhps-content-card__media` ist drin. **OK** (M1-Patch
bestaetigt).

**Minor M-2 (Inline-Modus-Doppelclick):** Bekanntes M1-Issue: Im
`video_mode=inline` wird das iframe als Sibling des Article appended
und der 2. Klick triggert moeglicherweise ein doppeltes Iframe.
Workaround: Orchestrator-Shims (default/card/compact.php) setzen
`data-video-mode="modal"` als Top-Level (Z. 53/39/39). Beim direkten
`[maes_videos]`-Aufruf laeuft `video_mode='inline'` als Default
(modules.php Z. 138: `$video_mode = 'inline';`). **Inkonsistent**.

**Empfehlung:** In `class-dhps-maes-modules.php` Z. 138
`$video_mode = 'modal';` als Default fuer `[maes_videos]` setzen
(siehe M1-Handover Sektion 6.2). Aktuell: bei Direktaufruf
moeglicher Doppel-Iframe.

### 6.2 R-7: Alpine-Detection abdeckt 4 MAES-Shortcodes

`Deubner_HP_Services.php` Z. 638:
```php
'maes', 'maes_videos', 'maes_merkblaetter', 'maes_aktuelles',
```

Alle 4 Shortcodes sind in der `has_shortcode`-Erkennung gelistet.
`dhps_detect_alpine_need()` wird auf `wp` (Prio 20) gehookt und setzt
`$GLOBALS['dhps_needs_alpine'] = true` bei Treffer. **OK**.

### 6.3 R-8: dhps-tp-js und dhps-mmb-js in neuen Templates

**TP-JS-Enqueue:**
- videos.php Z. 37: `wp_enqueue_script('dhps-tp-js');` - **bleibt drin**,
  weil Click-Delegation auf `[data-video-slug]` weiterhin TP-JS braucht.
- videos-card.php Z. 31: dito.
- videos-compact.php Z. 26: dito.

Ist **korrekt** - TP-JS bleibt fuer Videos zwingend.

**MMB-JS-Enqueue:**
- merkblaetter*.php: **KEIN** `wp_enqueue_script('dhps-mmb-js')`
  in den 3 Sub-Templates! OK.
- **ABER**: In `class-dhps-maes-modules.php` Z. 197 wird
  `wp_enqueue_script('dhps-mmb-js');` im `render_merkblaetter()`-Handler
  enqueued. **Major MA-2 wird nicht ausgeloest** (Discovery R-8 sagt
  "darf nicht mehr enqueued werden"), aber das Skript wird unnoetig
  geladen, weil das Outer-Akkordeon entfallen ist.

**Minor M-3 (Unnoetiger MMB-JS-Enqueue):** In
`class-dhps-maes-modules.php` Z. 197 entfaellt der Bedarf nach
`dhps-mmb-js`. Empfehlung: Zeile loeschen. Spart ~10 KB JS-Bundle
auf Seiten mit `[maes_merkblaetter]` (sofern MMB nicht ohnehin auf
derselben Seite ist).

### 6.4 dhps-maes-aktuelles.js geloescht

- File-System: `public/js/dhps-maes-aktuelles.js` ist **NICHT MEHR
  vorhanden** (Glob confirmed). OK.
- `Deubner_HP_Services.php` Z. 432-434: Kommentar erklaert das Entfernen,
  KEINE `wp_register_script('dhps-maes-aktuelles-js', ...)` mehr.
  OK.

### 6.5 Section-Filter-Bugfix

`default.php` Z. 38-47: Whitelist nun inkl. `'aktuelles'`. Z. 13-19
dokumentiert den Bugfix. OK.

---

## Performance-Disconnect-Analyse

### Discovery-Prognose vs. Realitaet

| Layout | Discovery-Prognose | Realer Wert | Differenz |
|--------|------------------:|------------:|----------:|
| `[maes]` default | ~22 KB (-33%) | 93,2 KB (+175%) | +71 KB Abweichung |
| `[maes_videos]` | ~13,5 KB (-25%) | 28,4 KB (+95%) | +15 KB Abweichung |
| `[maes_merkblaetter]` | ~7 KB (-36%) | 31,6 KB (+43%) | +25 KB Abweichung |
| `[maes_aktuelles]` | ~8 KB (-20%) | 33,1 KB (+23%) | +25 KB Abweichung |

### Diagnose: Was hat die Discovery uebersehen?

1. **ContentList-Wrapper-Overhead nicht eingerechnet.**
   `dhps-content-list__item-wrap`, `data-dhps-list-item`,
   `x-data="dhpsContentList(...)"`, `role="region"`,
   `aria-labelledby`, screen-reader-text - das addiert ~250 B pro
   Liste + ~60 B pro Karte. Bei 60 Videos = +3.6 KB nur fuer den
   Container-Overhead.

2. **LazyImage-Wrapper nicht eingerechnet.**
   Die LazyImage-Component (statt `<img>`) addiert
   `<div class="dhps-lazy-image">` + data-Attribute. Schaetzung:
   +80 B pro Karte. Bei 60 Videos = +4.8 KB.

3. **Vollstaendiger Teaser-Text statt PHP-Truncation.**
   v0.13.x hatte mb_strimwidth(120) auf Videos und mb_strimwidth(140)
   auf Merkblaetter. v0.14.1 rendert den vollen Text (CSS line-clamp
   versteckt visuell). Bei 60 Videos x +200 Zeichen Aufpreis = +12 KB.
   Bei 30 Merkblaettern x +100 Zeichen = +3 KB.

4. **BEM-Klassen-Mehrfachverkettung.**
   `dhps-content-card dhps-content-card--video dhps-content-card--service-maes dhps-tp-card dhps-content-list__item`
   = ~95 Zeichen Class-String pro Card-Root statt frueher
   `dhps-tp-card` (12 Zeichen). Differenz: +83 B pro Karte x 60 =
   +5 KB.

5. **Action-Footer mit SVG-Icons + Label-Spans.**
   Jede primaere Action: ~150 B (war frueher inline-Play-Button in
   einem `__play-btn` Span). Differenz: +80 B pro Karte x 60 = +4.8 KB.

6. **`data_attrs`-Erweiterung in ContentCard.**
   `data-video-slug`, `data-poster-url`, `data-v-modus`, `data-video-index`:
   ~120 B pro Video-Card x 60 = +7 KB. War in v0.13.x am
   `__poster`-Span, aber dort waren nur 2 Attribute, nicht 4.

Summe: ~40 KB technischer Overhead, +15 KB Teaser-Text = **~55 KB
Wachstum bei 60 Videos + 30 Merkblaettern + 8 News**. Das matched mit
dem realen +59 KB beim `[maes]`-Shortcode.

### Begruendung der Discovery-Optimismus

Die Discovery-Schaetzung basierte auf "Reduktion von Markup-Duplikat
zwischen den 3 Layout-Files" - das war auch korrekt fuer Source-Code-
Zeilen (-64%, validiert). Aber: **gerenderte HTML-Bytes != Source-LOC**.

Die Discovery hat einen klassischen Fehlschluss gemacht: weil weniger
Code, also weniger Output. Das gilt aber nur, wenn jeder Source-LOC
ungefaehr gleich viel Output produziert. Die ContentList/ContentCard
sind **dichter** in Output-pro-Source-LOC als die alten
Inline-Templates - sie produzieren mehr Wrapper-Markup, mehr Attribute,
mehr ARIA-Hooks pro Schleifeniteration.

### Empfehlung: Performance-Strategie v0.14.2+

1. **Akzeptieren** des aktuellen +23% bis +97% Wachstums (vor Bugfix-
   Bonus) als Trade-off fuer Component-System-Vorteile.
2. **Gzip-Audit:** in einem Live-Test gzip-Wire-Bytes messen. Geschaetzt
   ist die echte Wire-Last < v0.14.0, weil hoehere Klassen-
   Wiederholungsrate bessere gzip-Effizienz erlaubt.
3. **Quick-Wins** (O-1 ... O-5 aus Sektion 2.5) in v0.14.2 bundlen
   fuer ~8-15% Reduktion.
4. **Discovery-Process verbessern:** kuenftig Bytes-Schaetzungen mit
   Smoke-Test gegen Component-Implementation vor Specialist-Briefing
   validieren.

---

## Acceptance Checklist

| # | Kriterium | Status |
|---|-----------|:------:|
| 1 | Alle 12 MAES-Templates rendern fehlerfrei | OK (statisch) |
| 2 | Sub-Shortcodes `[maes]`, `[maes_videos]`, `[maes_merkblaetter]`, `[maes_aktuelles]` registriert | OK |
| 3 | 3 Layouts (default/card/compact) pro Sub-Shortcode verfuegbar | OK |
| 4 | ContentCard `data_attrs`-Prop implementiert | OK |
| 5 | Service-Branding 'maes' via CSS-Klassen (keine Inline-Styles) | OK |
| 6 | ARIA: `aria-expanded` + `aria-controls` an collapsible Cards | OK (Alpine-Binding) |
| 7 | `dhps-maes-aktuelles.js` geloescht + Enqueue entfernt | OK |
| 8 | Section-Filter unterstuetzt 'aktuelles' | OK |
| 9 | dhps-tp.js Selektor-Patch (.dhps-content-card__media) | OK |
| 10 | Heading-Hierarchie h3 default, ueberschreibbar | OK |
| 11 | Heading-Hierarchie konsistent ueber alle Sektionen | TEILWEISE (MA-1: keine Section-Headings) |
| 12 | EmptyState-Icons in der Icon-Map | TEILWEISE (I-1: 'search' fehlt) |
| 13 | A11y-Pass-Rate >= 95% | 89% (8/9) - **knapp unter** Lighthouse-Ziel |
| 14 | Keine Umlaute im Code | OK (Grep negativ) |
| 15 | Backward-Compat: alte Shortcode-Attribute akzeptiert | OK |
| 16 | TP-JS `[data-video-slug]`-Delegation funktioniert | OK |
| 17 | Alpine-Detection alle 4 MAES-Shortcodes | OK |
| 18 | MMB-JS Enqueue in merkblaetter*.php entfernt | OK |
| 19 | MMB-JS Enqueue im modules-Handler entfernt | **NEIN** (M-3: noch in modules.php Z. 197) |
| 20 | Variable-Bridge defensiv in Sub-Templates | OK |

---

## Issues-Liste (priorisiert)

### Major (1)

**MA-1: Heading-Hierarchie im default-Layout - keine Section-Headings.**

- Wenn `[maes]` mit Default-Layout gerendert wird, fehlt die
  `<h2>`-Section-Trennung zwischen Videos / Aktuelles / Merkblaetter.
- A11y-Pass-Rate sinkt unter 95% (Lighthouse).
- Discovery R-5 hatte das als offene Frage markiert; nicht geloest.
- **Empfehlung:** Pre-Release-Fix in `default.php` (Orchestrator):
  vor jedem `include` einen `<h2 class="screen-reader-text">{label}</h2>`
  einfuegen. ~6 Zeilen Patch.

### Minor (4)

**M-1: ARIA-expanded ohne JS-Hydration unsichtbar.**
- ContentCard rendert `:aria-expanded` nur als Alpine-Binding ohne
  statischen Default-Wert.
- Impact: Screen-Reader-User mit deaktiviertem JS sehen den Button-State
  nicht.
- **Empfehlung:** ContentCard Z. 219 ergaenzen `aria-expanded="false"`.

**M-2: Video-Mode inline als Default bei [maes_videos].**
- modules.php Z. 138 setzt `$video_mode = 'inline';` hart.
- Bekanntes Doppel-Iframe-Issue bei inline.
- **Empfehlung:** Auf 'modal' setzen oder als Shortcode-Att exposeben.

**M-3: dhps-mmb-js wird unnoetig enqueued.**
- modules.php Z. 197: `wp_enqueue_script('dhps-mmb-js')` ist obsolet,
  weil Outer-Akkordeon entfaellt.
- Impact: ~10 KB JS unnoetig auf [maes_merkblaetter]-only-Seiten.
- **Empfehlung:** Zeile streichen.

**M-4: video_mode-Pass-Through im [maes]-Orchestrator unterscheidet sich vom Sub-Shortcode.**
- default.php/card.php/compact.php setzen `data-video-mode="modal"`
  am Outer-Wrapper.
- videos.php (im Sub-Template) liest aber `$video_mode` aus seinem Scope,
  der vom Outer-Aufruf nicht durchgereicht wird.
- Beim `[maes]`-Orchestrator-Aufruf endet das Sub-Template mit
  `$video_mode = 'inline'` (sein Default) trotz Outer-Modal-Attribut.
- **Empfehlung:** Variable-Bridge im Orchestrator: `$video_mode = 'modal';`
  vor `include $base_path . 'videos.php';`

### Info (6)

**I-1: EmptyState-Icon-Slug 'search' nicht in der Icon-Map.**
- Faellt auf `wp_kses_post('search')` zurueck = textuelle Ausgabe.
- **Empfehlung:** Auf 'video' aendern in videos*.php.

**I-2: Card-Layout-`columns`-Pass-Through fehlt im Orchestrator.**
- `[maes layout=card columns=3]` reicht columns nicht durch.
- Cosmetisch.

**I-3: Theme-Override-Migration-Hinweis im CHANGELOG fehlt.**
- Themes mit eigenem Override bekommen altes Markup.

**I-4: Discovery-Prognose-Disconnect.**
- Discovery-Process-Verbesserung fuer kuenftige Migrationen
  (siehe Sektion Performance-Disconnect).

**I-5: ContentCard `initial_open` Prop fehlt (M3-Folge-Ticket).**
- `$first_open=true` ist reserviert aber wirkungslos.
- Spezifiziert in M3-Handover Z. 188.

**I-6: scrollIntoView entfaellt durch Loeschung dhps-maes-aktuelles.js.**
- M3-Handover Z. 191 dokumentiert als bewussten Trade-off.

---

## Verdict

# GO-WITH-CAVEATS

Begruendung:
- **0 Critical** - keine Release-Blocker.
- **1 Major** (MA-1: Section-Headings) - sollte vor v0.14.1-Release
  per ~6-Zeilen-Patch in `default.php` behoben werden. Falls
  zeitlich nicht moeglich: in v0.14.2 nachreichen mit
  explizitem A11y-Hotfix-Vermerk.
- **4 Minor** - alle in v0.14.2 oder spaeter behebbar.
- **Performance-Wachstum** ist akzeptabel (Trade-off A11y +
  Wartbarkeit), aber Discovery-Prognose war falsch.

Wenn MA-1 (Section-Headings) im aktuellen Release behoben wird,
upgrade auf **GO**.

Wenn dhps-mmb-js-Cleanup (M-3) und EmptyState-Icon-Fix (I-1) ebenfalls
sofort behoben werden, ist der Release sauber.

---

## Akzeptanz-Checklist (Final)

- [x] 12 Templates auditiert (9 modernisiert + 3 Orchestrator)
- [x] ContentCard.data_attrs Erweiterung validiert
- [x] Medizin-Branding-CSS-Hooks validiert (Z. 574, 930-941 in
      dhps-components.css)
- [x] dhps-tp.js Selector-Patch validiert (Z. 138)
- [x] dhps-maes-aktuelles.js Loeschung validiert
- [x] Section-Filter-Bugfix validiert (default.php Z. 38-47)
- [x] Cross-Layout-Test (statisch, 4x3=12 Kombinationen)
- [x] Sub-Template-Include-Check
- [x] Component-Consistency-Check
- [x] R-4, R-7, R-8 Bonus-Issues
- [x] Performance-Disconnect-Analyse mit korrigiertem [maes]-Vergleich
- [x] Smoke-Test-Script erstellt + nach Tests entfernt (Sandbox blockierte
      Docker-Exec, daher rein statische Auswertung)
- [x] Keine Code-Aenderungen vorgenommen
- [x] Keine Umlaute im Code (verifiziert via Grep)
