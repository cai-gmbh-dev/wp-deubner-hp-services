# MAES-Migrations-Plan v0.14.1 - Discovery-Refresh

> Status: Discovery-only (keine Code-Aenderungen) | Stand: 2026-05-22
> Owner: Architektur-Team (Specialist B) | Vorlage: 12-component-system-v0140.md, 11-uiux-audit-v0140.md
> Quelle Parser: `includes/parsers/class-dhps-maes-parser.php`
> Foundation: v0.14.0 (8 Components, Alpine 3.14.9, @layer-Cascade)

## Mission

Konsolidierung der 13 MAES-Template-Files auf das v0.14.0-Component-System
unter Erhalt der Section-Filter-Logik (`dhps_maes_section`) und der
Branding-Kennzeichnung (Medizin-Teal `#0097a7` / `--dhps-color-medizin`).
Nicht-Ziel: Parser-Output-Aenderung (Daten-Shape bleibt stabil).

MAES ist der erste komplexe Service ohne dominantes Render-Volumen-Problem
(33 KB initial, nicht ~300 KB wie MMB), aber mit der hoechsten Sub-Sektions-
Vielfalt: 3 Card-Typen (video / document / news) und 4 Sub-Sektionen
(default / videos / merkblaetter / aktuelles) je in 3 Layout-Varianten
(default / card / compact).

---

## Sektion 1 - Status-Quo-Tabelle (13 Files)

Quelle: Zeilen-Count via wc, manuelle Komplexitaets-Einschaetzung aus den
gelesenen Templates.

| # | File | Zeilen | Sub-Sektion | Layout | Card-Typ | Komplexitaet | Auffaellig |
|---|------|-------:|-------------|--------|----------|--------------|------------|
| 1 | `default.php` | 128 | all (videos+mb) | default | video+doc | M | Doppelt: Videos + MB inline, duplicate from videos.php / merkblaetter.php |
| 2 | `card.php` | 127 | all (videos+mb) | card | video+doc | M | wrapper `.dhps-card`, sonst identisch zu default.php |
| 3 | `compact.php` | 96 | all (videos+mb) | compact | video+doc | S | Inline-style auf play-btn, doppelter JS-Enqueue (tp+mmb) |
| 4 | `videos.php` | 78 | videos | default | video | M | Lazy-Count-Support, Load-More-Button, Inline-style |
| 5 | `videos-card.php` | 62 | videos | card | video | S | Style-Preset 'medizin', kein Lazy-Count |
| 6 | `videos-compact.php` | 52 | videos | compact | video | S | Listen-Style, kein Lazy-Count |
| 7 | `merkblaetter.php` | 105 | merkblaetter | default | document | M | MMB-Akkordeon-Wrapper, doppeltes Akkordeon (Cat+Item) |
| 8 | `merkblaetter-card.php` | 78 | merkblaetter | card | document | S | Grid-Layout, hart-codierte 140-char-Truncation |
| 9 | `merkblaetter-compact.php` | 66 | merkblaetter | compact | document | S | List-Style, kein Akkordeon |
| 10 | `aktuelles.php` | 71 | aktuelles | default | news | M | Externes JS (`dhps-maes-aktuelles-js`), Akkordeon |
| 11 | `aktuelles-card.php` | 95 | aktuelles | card | news | L | **Inline-`<script>` 18 Z. - CSP-Bruch** |
| 12 | `aktuelles-compact.php` | 89 | aktuelles | compact | news | L | **Inline-`<script>` 18 Z. - CSP-Bruch** |
| 13 | `dhps-maes-aktuelles.js` | 66 | aktuelles | - | - | S | v0.13.1 Toggle-Script - nach Migration evtl. obsolet |

Total: 13 Files, **1.114 Zeilen Code** (inkl. JS), davon ~250 Zeilen
Duplikat-Markup zwischen `default.php` / `card.php` / `videos.php` /
`videos-card.php` und ~150 Zeilen Duplikat zwischen `aktuelles*-card.php`
und `aktuelles*-compact.php` (Inline-Script + Akkordeon-Markup).

Geschaetzter HTML-Render (60 Videos + 30 Merkblaetter + 8 News, mittlere
Texte):
- `default.php` (alle Sub-Sektionen): ~33 KB (gemessen v0.13.x).
- `videos.php`: ~18 KB (ohne lazy_count), mit lazy_count=8 ~3 KB.
- `merkblaetter.php`: ~11 KB.
- `aktuelles.php`: ~6-12 KB (abhaengig von body_html Laengen).

---

## Sektion 2 - Component-Coverage-Matrix

| Sub-Sektion | Card-Typ | ContentList | ContentCard | FilterBar | LazyImage | Accordion | Pagination | EmptyState | Skeleton | Coverage |
|-------------|----------|:-----------:|:-----------:|:---------:|:---------:|:---------:|:----------:|:----------:|:--------:|----------|
| videos | video | OK | OK (+1 prop) | OPT | OK (built-in) | - | OPT | OK | OPT | **95%** |
| merkblaetter | document | OK | OK (+1 prop) | OPT | - | - | OPT | OK | OPT | **90%** |
| aktuelles | news | OK | OK (collapsible) | OPT | - | - | - | OK | - | **100%** |
| default (multi-section) | mixed | OK (3x) | OK | - | OK | - | OPT | OK | - | **100%** |

Legende: OK = direkt einsetzbar, OPT = optional/nice-to-have, +1 prop =
kleine Component-Erweiterung empfohlen.

### Identifizierte Erweiterungen / neue Patterns

**Notwendig (BLOCKER):**

(keine)

**Empfehlenswert (nicht blockierend, kann via Custom-Class oder Action
geloest werden):**

1. **ContentCard: video-poster overlay branding**.
   Heute hardcoded `style="color: var(--dhps-color-medizin)"` auf
   `.dhps-tp-card__play-btn` ([maes/videos.php:55](../../public/views/services/maes/videos.php#L55)).
   In v0.14.0-ContentCard rendert die Component bereits ein
   `.dhps-content-card__play-overlay` ([content-card.php:141](../../public/views/components/content-card.php#L141))
   mit `currentColor`. Branding kann via `service` Prop +
   `.dhps-content-card--service-maes` CSS-Hook geloest werden
   (siehe 12-component-system-v0140.md Abschnitt 4.2 Token-Bridge).
   **Aktion:** CSS-Regel in `dhps-components.css` ergaenzen, KEINE Component-
   Erweiterung noetig.

2. **ContentCard: video-action mit Poster-Click-Verhalten**.
   Heute ist der gesamte Poster ein `role="button" tabindex="0" data-video-slug`,
   waehrend die ContentCard die `actions[]`-Liste am Footer rendert. Fuer
   Inline-Player muss der Poster-Click den Player triggern.
   **Aktion:** Entweder
   - Variante A: TP-Click-Handler bekommt zusaetzlich Selector
     `.dhps-content-card--video .dhps-content-card__media` (kein
     Component-Change, nur JS-Selektor-Erweiterung in `dhps-tp.js`); oder
   - Variante B: Action-Prop `primary: true` + Icon `play` rendert als
     grosser Overlay-Button, kein zusaetzlicher Poster-Click. Sauberer.

3. **ContentCard: data-attributes durchreichen**.
   Heute `data-video-slug`, `data-poster-url`, `data-v-modus="0"`.
   ContentCard hat aktuell keinen `data_attrs`-Prop.
   **Aktion:** ContentCard erweitern um `data_attrs => [ 'video-slug' => ..., 'poster-url' => ..., 'v-modus' => '0' ]`. **Kleine Component-Erweiterung
   (1 Foreach-Schleife in `content-card.php`)**, BC-safe.

4. **FilterBar (Tag-Modus): pro Sub-Sektion sinnvoll?**
   - Videos: MAES-API liefert KEINE Kategorien -> nur Volltext-Suche
     ueber Titel sinnvoll. **Tags entfallen, Search OPT.**
   - Merkblaetter: MAES hat nur EINE Kategorie ("Merkblaetter und Checklisten"
     fix in `merkblaetter.php:37`), Tags wuerden keine Filter-Funktion erfuellen.
     **Tags entfallen, Search OPT.**
   - Aktuelles: News-Liste mit 8-15 Items, Tags unnoetig. **Entfallen.**
   - Default: kombinierte Anzeige mit moeglichem Sub-Sektion-Filter
     ("Alle / Videos / Merkblaetter / News") als Tabs/Tags. **Hier kann
     FilterBar als Tab-Strip dienen** (aber alternativ Section-Filter
     bleibt via Shortcode-Attribut, Frontend-Tabs YAGNI).

5. **LazyImage**: bereits via ContentCard automatisch eingebunden
   ([content-card.php:119](../../public/views/components/content-card.php#L119)).
   Keine Aktion.

6. **Pagination**: bei 60+ Videos heute via `lazy_count` und Load-More-Button.
   ContentList haelt Alpine-Pagination ([content-list.php:67](../../public/views/components/content-list.php#L67)),
   v0.14.0-Pagination-Component liefert 3 Modi.
   **Aktion:** `pagination_mode = 'load-more'` mit `page_size = 8` als
   Default fuer videos.

7. **Aktuelles + ContentCard `collapsible=true`**: passt 1:1 zur heutigen
   Akkordeon-Funktion. `body_html` wird in expandiertem `__detail`-Div
   gerendert mit `wp_kses_post()`.
   **Aktion:** keine, ContentCard-Coverage ist 100%.

---

## Sektion 3 - Konkrete Implementierungs-Vorschlaege

### 3.1 MAES Videos (M1)

**Heute (3 Files: `videos.php` 78Z, `videos-card.php` 62Z, `videos-compact.php` 52Z):**

Direktes `<article class="dhps-tp-card">`-Markup mit Poster-Click via
TP-JS. `lazy_count` + Load-More nur in `videos.php`.

**Neu (1 File, ~30 Zeilen pro Layout-Verzweigung):**

```php
<?php
$layout = $layout ?? 'default'; // default|card|compact
$items  = array_map( function( $v ) {
    return array(
        'type'      => 'video',
        'title'     => $v['title'],
        'teaser'    => $v['description'], // CSS line-clamp statt PHP mb_strimwidth
        'media_url' => $v['poster_url'],
        'media_alt' => $v['title'],
        'service'   => 'maes',
        'actions'   => array( array(
            'label'   => __( 'Video abspielen', 'wp-deubner-hp-services' ),
            'href'    => '#play',
            'icon'    => 'play',
            'primary' => true,
        ) ),
        // NEU: data_attrs (Component-Erweiterung 3.2)
        'data_attrs' => array(
            'video-slug'  => $v['video_slug'],
            'poster-url'  => $v['poster_url'],
            'v-modus'     => '0',
        ),
    );
}, $videos );

dhps_component( 'content-list', array(
    'id'          => 'maes-videos-' . wp_unique_id(),
    'layout'      => 'compact' === $layout ? 'list' : 'grid',
    'columns'     => $columns ?? 2,
    'items'       => $items,
    'item_type'   => 'video',
    'class'       => 'dhps-service--maes dhps-service--maes-videos',
    'pagination'  => $lazy_count > 0 ? array(
        'mode'      => 'load-more',
        'page_size' => $lazy_count,
        'target'    => 'maes-videos-...',
    ) : null,
    'empty_state' => array(
        'icon'  => 'search',
        'title' => __( 'Keine Video-Tipps verfuegbar', 'wp-deubner-hp-services' ),
    ),
) );
```

Vorteile: -150 Zeilen Markup-Duplikat ueber 3 Files, einheitliche
Card-Klassen, Pagination via Alpine, Branding via Token-Bridge.

**JS-Anpassung:** `public/js/dhps-tp.js` Selektor-Erweiterung von
`.dhps-tp-card__poster` auf zusaetzlich
`.dhps-content-card--video .dhps-content-card__media` ODER neue Click-
Handler-Registrierung fuer `.dhps-content-card__action[href="#play"]`.

### 3.2 MAES Merkblaetter (M2)

**Heute (3 Files: `merkblaetter.php` 105Z, `merkblaetter-card.php` 78Z, `merkblaetter-compact.php` 66Z):**

Doppeltes Akkordeon im default: Kategorie ("Merkblaetter und Checklisten")
+ pro Sheet ein eigenes Akkordeon-Item mit Beschreibung + Download.
Card-Layout zeigt direkt Grid ohne Akkordeon. Compact = Linkliste.

**Neu:**

```php
<?php
$items = array_map( function( $sheet ) {
    $pdf_href = admin_url( 'admin-ajax.php' ) . '?' . http_build_query(
        array_merge(
            array(
                'action'  => 'dhps_mmb_pdf',
                'nonce'   => wp_create_nonce( 'dhps_mmb_nonce' ),
                'service' => 'maes',
            ),
            $sheet['pdf_params']
        )
    );
    return array(
        'type'        => 'document',
        'title'       => $sheet['title'],
        'teaser'      => $sheet['description'], // CSS line-clamp
        'collapsible' => 'default' === $layout, // nur default klappt auf
        'body_html'   => '', // ContentCard zeigt teaser direkt, kein body
        'actions'     => array( array(
            'label'   => __( 'Merkblatt herunterladen', 'wp-deubner-hp-services' ),
            'href'    => $pdf_href,
            'icon'    => 'download',
            'target'  => '_blank',
            'primary' => true,
        ) ),
        'service'     => 'maes',
    );
}, $merkblaetter );

dhps_component( 'content-list', array(
    'id'          => 'maes-mb-' . wp_unique_id(),
    'layout'      => 'compact' === $layout ? 'list' : 'grid',
    'columns'     => $columns ?? 2,
    'items'       => $items,
    'item_type'   => 'document',
    'class'       => 'dhps-service--maes dhps-service--maes-merkblaetter',
    'empty_state' => array(
        'icon'  => 'inbox',
        'title' => __( 'Keine Merkblaetter verfuegbar', 'wp-deubner-hp-services' ),
    ),
) );
```

Vorteile: kein doppeltes Akkordeon noetig (MAES hat nur 1 Kategorie - das
Outer-Akkordeon entfaellt). Trotzdem Detail-Expand via
`collapsible=true` an der Card. -100 Zeilen Markup, +A11y via native
Component.

**Sonderfall default-Layout:** Outer-Akkordeon (1 Kategorie) entfaellt
ersatzlos - User sieht direkt die Liste der Sheets mit kollabierbarer
Beschreibung. Verbesserung gegenueber heute (1 Klick weniger).

### 3.3 MAES Aktuelles (M3)

**Heute (3 Files: `aktuelles.php` 71Z, `aktuelles-card.php` 95Z, `aktuelles-compact.php` 89Z + `dhps-maes-aktuelles.js` 66Z):**

Akkordeon-News mit Inline-Script in 2 von 3 Layouts (CSP-Bruch). Externes
JS (`dhps-maes-aktuelles.js`) nur in `aktuelles.php` (default-Layout).

**Neu:**

```php
<?php
$items = array_map( function( $article ) {
    return array(
        'type'        => 'news',
        'title'       => $article['title'],
        'teaser'      => $article['teaser'],
        'body_html'   => $article['body_html'],
        'collapsible' => true,
        'service'     => 'maes',
    );
}, $news );

dhps_component( 'content-list', array(
    'id'          => 'maes-aktuelles-' . wp_unique_id(),
    'layout'      => 'compact' === $layout ? 'list' : 'grid',
    'columns'     => 'card' === $layout ? ( $columns ?? 2 ) : 1,
    'items'       => $items,
    'item_type'   => 'news',
    'class'       => 'dhps-service--maes dhps-service--maes-aktuelles',
    'empty_state' => array(
        'icon'  => 'inbox',
        'title' => __( 'Keine aktuellen Meldungen', 'wp-deubner-hp-services' ),
    ),
) );
```

Vorteile: 3 Files mit 255 Zeilen werden zu 1 File mit ~40 Zeilen.
Akkordeon-Toggle uebernimmt Alpine via `dhpsContentCard().toggle()` -
kein eigenes JS noetig.

**Effekt auf `dhps-maes-aktuelles.js`**: Wird **obsolet**. Empfehlung
loeschen + Enqueue-Registrierung in `Deubner_HP_Services.php` entfernen
(siehe Sektion 7).

### 3.4 MAES Default (kombiniertes Layout)

**Heute (1 File: `default.php` 128Z):**

Inlined-Markup beider Sub-Sektionen mit `dhps_maes_section`-Filter.

**Neu:**

```php
<?php
$section = sanitize_key( apply_filters( 'dhps_maes_section', 'all' ) );
$show_videos       = in_array( $section, array( 'all', 'videos' ), true );
$show_merkblaetter = in_array( $section, array( 'all', 'merkblaetter' ), true );
$show_aktuelles    = in_array( $section, array( 'all', 'aktuelles' ), true );
?>
<div class="dhps-service dhps-service--maes <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>">

<?php if ( $show_videos && ! empty( $videos ) ) :
    include __DIR__ . '/videos.php'; // delegiert
endif; ?>

<?php if ( $show_merkblaetter && ! empty( $merkblaetter ) ) :
    include __DIR__ . '/merkblaetter.php';
endif; ?>

<?php if ( $show_aktuelles && ! empty( $news ) ) :
    include __DIR__ . '/aktuelles.php';
endif; ?>

</div>
```

Vorteile: default.php wird zum Orchestrator (~25 Zeilen). Jede Sub-Sektion
ist single-source-of-truth. Section-Filter bleibt unveraendert (BC).

**Hinweis Section-Erweiterung:** Heute fehlt der `'aktuelles'`-Wert im
in_array-Check. Mit Refactor sauber drin (BC-additiv).

### 3.5 Card/Compact-Layout-Strategie

Heute haben `card.php` und `compact.php` ein eigenes Outer-Markup, das
beide Sub-Sektionen wrappt. Das ist **redundant**, da Card-Wrapping bereits
in den `videos-card.php`, `merkblaetter-card.php`, `aktuelles-card.php`
geschieht.

**Vorschlag**: `card.php` und `compact.php` werden zu Layout-Variant-Files
des default-Orchestrators:
- `card.php` setzt `$layout = 'card'` und delegiert an die Sub-Sektionen.
- `compact.php` setzt `$layout = 'compact'` und delegiert an die Sub-Sektionen.
- `default.php` setzt `$layout = 'default'` und delegiert.

Ergebnis: Card/Compact werden zu ~10-15 Zeilen Shims, die das
Layout-Token an die Sub-Sektionen reichen. Alle Outputs durchlaufen
die jeweiligen Sub-Sektions-Files.

---

## Sektion 4 - Render-Performance-Prognose

### 4.1 Initial-HTML-Vergleich

Annahme: 60 Videos, 30 Merkblaetter, 8 News (typische volle MAES-Lizenz).

| Layout | v0.13.x | v0.14.1 Schaetzung | Veraenderung | Anmerkung |
|--------|--------:|-------------------:|-------------:|-----------|
| `default.php` (all) | ~33 KB | ~22 KB | -33% | Doppelt-Markup raus + line-clamp statt 120-char-Truncation; Outer-Akkordeon-Wrapper entfaellt fuer MB |
| `card.php` (all) | ~34 KB | ~23 KB | -32% | analog |
| `compact.php` (all) | ~12 KB | ~9 KB | -25% | bereits schlank |
| `videos.php` (60 V, lazy_count=8) | ~3 KB initial | ~3 KB initial | ~0% | Pagination-Component statt Custom-JS, gleicher Effekt |
| `videos.php` (60 V, lazy_count=0) | ~18 KB | ~15 KB | -17% | Markup-Slim |
| `merkblaetter.php` | ~11 KB | ~7 KB | -36% | Akkordeon-Kategorie-Wrapper raus, weniger DOM-Tiefe |
| `aktuelles.php` (8 News) | ~10 KB | ~8 KB | -20% | abhaengig body_html |
| `aktuelles-card.php` | ~12 KB | ~8 KB | -33% | Inline-Script (1 KB) raus |

**Erwartetes Ziel:** Initial-HTML in default-Layout ~20-24 KB
(vs. 33 KB heute). Browser-Perception via SkeletonLoader optional,
**aber bei 33 KB nicht zwingend noetig** (kein User-Pain wie bei MMB).

### 4.2 JS-Bundle-Effekt

Heute: `dhps-tp.js` (~25 KB) + `dhps-mmb.js` (~10 KB) + `dhps-maes-aktuelles.js`
(~2 KB) = ~37 KB unkomprimiert in `default.php`.

Nach Migration:
- Alpine 3.14.9 (44 KB) wird ohnehin geladen (v0.14.0-Foundation, conditional).
- `dhps-components-alpine.js` (~6 KB) wird ohnehin geladen.
- `dhps-tp.js` bleibt fuer Video-Player-Logik noetig (Inline-Player +
  `dhps_tp_video_src`-Proxy) - **muss bleiben**.
- `dhps-mmb.js` kann **fuer MAES entfallen** (kein Outer-Akkordeon mehr).
- `dhps-maes-aktuelles.js` kann **entfallen** (Alpine ContentCard
  uebernimmt Toggle).

**Netto-Effekt JS**: -12 KB (dhps-mmb.js fuer MAES entlastet, aktuelles.js
geloescht), +50 KB Alpine (war aber schon da fuer MMB-v0.14.0).
In Summe bei reiner MAES-Seite: **+38 KB JS**, aber **stark cachebar**
(Alpine wird seitenweit geteilt).

### 4.3 LCP / FCP

Da MAES heute kein Render-Bottleneck ist (33 KB < 50 KB Roadmap-Ziel),
ist der Hauptgewinn nicht Performance, sondern:
- **Wartbarkeit**: 13 Files -> ~7 Files (-46%)
- **Konsistenz**: 1 Card-Component statt 3 verschiedene Card-Markups
- **A11y-Hardening**: Inline-Scripts raus, Heading-Hierarchie via Filter
- **CSP-Compliance**: 2 Inline-Scripts + 5 Inline-Styles raus

---

## Sektion 5 - Identifizierte Risiken + Mitigation

| # | Risiko | Wahrscheinlichkeit | Impact | Mitigation |
|---|--------|---:|---:|------------|
| R1 | TP-Player-JS bindet auf `.dhps-tp-card__poster`, nicht auf neue ContentCard-Selektoren | Hoch | Hoch | JS-Delegation-Selector um `.dhps-content-card--video [data-video-slug]` erweitern, alte Selektoren bleiben aktiv (BC) |
| R2 | Theme-Override unter `{theme}/dhps/services/maes/*.php` bricht | Mittel | Mittel | Pro Layout Feature-Flag `dhps_components_enabled` (analog v0.14.0 MMB-Rollback), 1-Release Legacy parallel |
| R3 | Section-Filter `'aktuelles'`-Wert war nicht im default.php gelistet | Niedrig | Niedrig | Doku im Filter-Hook ergaenzen; bestehende Werte `'all'`, `'videos'`, `'merkblaetter'` bleiben gueltig |
| R4 | Card-Layout-Inhaltskonflikt: heute wrapt card.php BEIDE Sub-Sektionen mit EINEM `.dhps-card` - bei Refactor wird jede Sub-Sektion eigener Card-Wrapper | Mittel | Niedrig | Optionales Top-Level-Wrap-Prop pro Layout-Variante (default false), Theme-Adopters informieren |
| R5 | Branding-Stil `style="color: var(--dhps-color-medizin)"` wird durch Service-Class ersetzt - CSP-Verbesserung, aber Override-Selectoren in Themes brechen | Niedrig | Niedrig | Neue Klasse `.dhps-content-card--service-maes .dhps-content-card__play-overlay { color: var(--dhps-color-medizin); }` in `dhps-components.css`, Migrationsnotiz im CHANGELOG |
| R6 | `dhps-maes-aktuelles.js` wird geloescht, Enqueue-Registrierung in `Deubner_HP_Services.php` muss mit entfernt werden | Niedrig | Niedrig | Im Specialist-Auftrag explizit als Cleanup-Schritt mitgeben |
| R7 | Alpine.js noch nicht auf MAES-Pages aktiviert (v0.14.0 hat Detection nur fuer MMB-Shortcodes?) | Mittel | Hoch | Pruefen in `Deubner_HP_Services.php` die `has_shortcode`-Erkennung, fuer `[maes]`, `[maes_videos]`, `[maes_merkblaetter]`, `[maes_aktuelles]` ergaenzen |
| R8 | Doppel-Enqueue `dhps-tp-js` + `dhps-mmb-js` in heutigem `default.php` wird durch alleinigen Alpine-Bedarf reduziert - aber TP bleibt noetig fuer Player | Niedrig | Niedrig | `dhps-mmb-js`-Enqueue aus MAES-Templates entfernen; `dhps-tp-js` bleibt nur fuer Videos |
| R9 | Truncation: mb_strimwidth (120 Zeichen) -> CSS line-clamp veraendert Mobile/Desktop-Optik | Niedrig | Niedrig | line-clamp:3 auf `.dhps-content-card__teaser` schon vorhanden? Falls nein, pro Sub-Sektion in `dhps-components.css` definieren |

**Lessons Learned aus v0.14.0 MMB-Pilot**: noscript-Fallback wurde gebraucht
fuer SEO. MAES hat **kein** AJAX-on-Demand-Bedarf (33 KB rendert komplett
serverseitig), daher noscript-Block **entfaellt**. Aber: bei Akkordeon-
Aktuelles brauchen Crawler die `body_html` im DOM - Alpine `x-show`
versteckt mit `display:none`, **Inhalt ist trotzdem im DOM**. SEO sicher.

---

## Sektion 6 - Empfehlung Specialist-Aufteilung

### Variante A (empfohlen): 3 parallele Specialists M1/M2/M3 + 1 sequenzieller Integrator

Begruendung: Sub-Sektionen sind **Daten-isoliert** (Videos, Merkblaetter,
Aktuelles - separate Parser-Outputs), und die Layout-Varianten (default/
card/compact) sind heute pro Sub-Sektion bereits in 3 Files getrennt.
3 parallele Spezialisten koennen jeweils 3 Files in 1 File konsolidieren,
ohne sich gegenseitig zu blockieren.

| Spec | Scope | Files In | Files Out | Komplexitaet | Geschaetzte Dauer |
|------|-------|---------:|----------:|--------------|------------------|
| **M1 Videos** | `videos*.php` (3 Files) + JS-Player-Click-Erweiterung in `dhps-tp.js` | 3 | 1 | **M** | 1 Session |
| **M2 Merkblaetter** | `merkblaetter*.php` (3 Files); Wegfall Outer-Akkordeon | 3 | 1 | **M** | 1 Session |
| **M3 Aktuelles** | `aktuelles*.php` (3 Files) + Loeschung `dhps-maes-aktuelles.js` + Enqueue-Cleanup | 3 + 1 JS | 1 | **S** (collapsible reicht 1:1) | 1 Session |
| **M-Int (sequenziell)** | `default.php`, `card.php`, `compact.php` als Orchestrator-Shims; CSS-Branding-Hooks; Asset-Detection in `Deubner_HP_Services.php` | 3 | 3 | **S** | 0.5 Session |

Total: 7 Files (statt heute 13), ~400 Zeilen Code (statt 1.114, -64%).

### Variante B (Alternative): 1 grosser Specialist

Sinnvoll, wenn parallele Koordination-Kosten zu hoch sind. Setzt voraus,
dass derselbe Specialist alle ContentCard-Prop-Patterns kennt. **Nicht
empfohlen** wegen Wartbarkeit und paralleler Throughput-Optimierung.

### Variante C (granularer): 4 + 1 + 1

M1/M2/M3 + ein Spezial-Spec fuer das Player-JS-Refactor (TP-Selector-
Erweiterung). **Nicht empfohlen** - das JS-Update ist <30 Zeilen und
sollte mit M1 (Videos) gebundelt sein.

### Reihenfolge-Empfehlung

1. **M3 Aktuelles** zuerst (kleinste, klarste Migration, validiert
   ContentCard-collapsible).
2. **M1 Videos + M2 Merkblaetter parallel** (sobald M3 OK).
3. **M-Int** zum Schluss (Orchestrator + CSS-Cleanup).
4. QA + Release.

### Specialist-Briefing-Minimum

Jeder Specialist erhaelt:
- Diesen Plan (Sektion 3.x als Implementierungs-Spec).
- Parser-Output-Shape (Sektion `class-dhps-maes-parser.php:parse_*`).
- Acceptance: Visual-Regression OK (Vor/Nach Screenshot), Shortcode-API
  unveraendert, A11y-Lighthouse >= 95, keine neuen Console-Errors.

---

## Sektion 7 - Files die geloescht / archiviert werden koennen

### 7.1 Hard-Delete nach erfolgreichem Release (v0.14.1)

| File | Begruendung |
|------|-------------|
| `public/views/services/maes/aktuelles-card.php` | Wird durch `aktuelles.php` + `$layout='card'` ersetzt |
| `public/views/services/maes/aktuelles-compact.php` | Wird durch `aktuelles.php` + `$layout='compact'` ersetzt |
| `public/views/services/maes/merkblaetter-card.php` | Wird durch `merkblaetter.php` + `$layout='card'` ersetzt |
| `public/views/services/maes/merkblaetter-compact.php` | Wird durch `merkblaetter.php` + `$layout='compact'` ersetzt |
| `public/views/services/maes/videos-card.php` | Wird durch `videos.php` + `$layout='card'` ersetzt |
| `public/views/services/maes/videos-compact.php` | Wird durch `videos.php` + `$layout='compact'` ersetzt |
| `public/js/dhps-maes-aktuelles.js` | Ersetzt durch Alpine `dhpsContentCard().toggle()` |

**7 Files geloescht.** Enqueue-Registrierung `dhps-maes-aktuelles-js`
in `Deubner_HP_Services.php` muss mit entfernt werden.

### 7.2 Konsolidiert (bleiben, aber refaktoriert)

| File | Neue Rolle |
|------|------------|
| `public/views/services/maes/default.php` | Orchestrator: ruft Sub-Sektionen ueber Section-Filter auf |
| `public/views/services/maes/card.php` | Layout-Shim: setzt `$layout='card'` + delegiert an default.php |
| `public/views/services/maes/compact.php` | Layout-Shim: setzt `$layout='compact'` + delegiert an default.php |
| `public/views/services/maes/videos.php` | Konsolidiert + nutzt content-list/content-card |
| `public/views/services/maes/merkblaetter.php` | Konsolidiert + nutzt content-list/content-card |
| `public/views/services/maes/aktuelles.php` | Konsolidiert + nutzt content-list/content-card mit collapsible |

**6 Files bleiben** (statt 12 ohne index.php).

### 7.3 Rollback-Plan

Filter `dhps_components_enabled` ('maes' => false) muss in v0.14.1
implementiert werden, **bevor** Files geloescht werden. Empfehlung:
- v0.14.1: Migration + Feature-Flag, alte Files bleiben unter
  `public/views/services/maes/_legacy/` archiviert.
- v0.14.2 oder v0.15.0: Hard-Delete `_legacy/` nach 1 Release-Cycle ohne
  Bug-Reports.

---

## Anhang A - Sub-Sektions-spezifische Component-Prop-Cheatsheets

### A.1 MAES Video -> ContentCard
```
type        'video'
title       $video['title']
teaser      $video['description']           (CSS line-clamp:3 statt mb_strimwidth)
media_url   $video['poster_url']
media_alt   $video['title']
service     'maes'                          (Branding-Hook)
actions     [{label:'Video abspielen', href:'#play', icon:'play', primary:true}]
data_attrs  {video-slug, poster-url, v-modus:'0'}   (NEU - Component-Erweiterung 1x foreach)
```

### A.2 MAES Merkblatt -> ContentCard
```
type        'document'
title       $sheet['title']
teaser      $sheet['description']           (CSS line-clamp:3)
service     'maes'
collapsible $layout === 'default'           (in card/compact: false, kein Toggle)
actions     [{label:'Merkblatt herunterladen', href:$pdf_href, icon:'download', target:'_blank', primary:true}]
```

### A.3 MAES News (Aktuelles) -> ContentCard
```
type        'news'
title       $article['title']
teaser      $article['teaser']
body_html   $article['body_html']
collapsible true
service     'maes'
```

### A.4 ContentList - Pattern fuer alle 3 Sub-Sektionen
```
id          'maes-{sub}-' . wp_unique_id()
layout      'list' (compact) | 'grid' (default/card)
columns     1 (compact/aktuelles-default) | 2-4 (card)
items       array_map(...)
item_type   'video'|'document'|'news'
class       'dhps-service--maes dhps-service--maes-{sub}'
pagination  videos only, mode='load-more'
empty_state {icon, title}
```

---

## Anhang B - Offene Fragen fuer Team-Review

1. **Section-Filter Erweiterung**: Soll der neue Wert `'aktuelles'`
   offiziell im `dhps_maes_section`-Filter dokumentiert werden? Heute
   ist es nur `'all'|'videos'|'merkblaetter'` (siehe `default.php:28`).
2. **`columns`-Default fuer videos / merkblaetter / aktuelles**: 2 oder 3?
   Heute durchgehend 2 (`dhps-tp-grid--2col`). Behalten?
3. **Lazy-Count Default fuer videos**: 0 (alle) oder 8 (Load-More)?
   Heute Default 0. Empfehlung: 0 bleibt, da MAES selten >30 Videos
   liefert und Pagination-Bedarf klein ist.
4. **ContentCard `data_attrs`-Erweiterung**: 1x Foreach mit `esc_attr`
   pro Key, ~6 Zeilen. Wird das als BC-safe Erweiterung akzeptiert
   oder lieber Service-spezifisches Action-Schema mit
   `action[type='video-trigger']`?
5. **Theme-Override-Migration**: Wer kommuniziert mit den 2-3 Deubner-
   Kunden-Sites, die heute MAES-Overrides nutzen? CHANGELOG-Eintrag
   genuegt nicht.
