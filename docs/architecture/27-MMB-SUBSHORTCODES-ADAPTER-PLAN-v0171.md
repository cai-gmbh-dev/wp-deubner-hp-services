# 27 - MMB-Adapter + Sub-Shortcodes-Bridge - Plan v0.17.1

**Status:** Discovery (2026-06-04)
**Aktuelle Plugin-Version:** v0.17.0
**Ziel-Version:** v0.17.1
**Architekt-Auftrag:** Zweiter Adapter (MMB nach MAES) + Sub-Shortcodes-Bridge
(zog auf von v0.17.4, weil MAES-Sub-Shortcodes ihre BC ohne Adapter-Bridge nicht
wirklich nachweisen koennen).

**Discovery-Empfehlung vorab (Kurzfassung):**

- **MIL-Adapter-Entscheidung:** **Option B** - DHPS_MMB_Adapter wird fuer BEIDE
  Services (`mmb` + `mil`) registriert. Adapter ist service-agnostisch.
- **Sub-Shortcodes-Bridge-Entscheidung:** **Option C + Helper** -
  `DHPS_MAES_Modules::get_collection()` als NEUE Methode (alte get_data bleibt),
  intern via reused Adapter. Sub-Shortcode-Templates pruefen `$has_collection`.
- **Spec-Aufteilung:** 2 Specialists - **F1 MMB-Adapter+Templates+MIL-Reg** /
  **F2 Sub-Shortcodes-Bridge** (entkoppelt, parallel ausfuehrbar).
- **Schema-Vertrag:** Sektion 7 ist verbindlich (9x Schema-Vertrag-Vorgehen
  ohne Critical-Drift erfolgreich).
- **Aufwand:** M (mittel) - MMB-Adapter ~120 LOC, 3 Templates ~150 LOC mit
  BC-Pattern, Sub-Shortcodes-Bridge ~100 LOC, Tests ~80 LOC = ca. 450 LOC.

---

## Sektion 1: Ausgangslage MMB-Parser

### 1.1 Top-Level-Keys (aus class-dhps-mmb-parser.php Z. 54-58)

```
array(
    'categories'    => array(),  // Liste der Rubriken
    'search_config' => array(),  // Such-Konfiguration
    'service_tag'   => 'mmb',    // wird in der Pipeline auf den Aufrufer-Tag ueberschrieben
)
```

### 1.2 Category-Item-Schema (parse_categories, Z. 85-90)

```
array(
    'id'          => 'rubrik_1',          // string, generierte ID aus header-Counter
    'name'        => 'Hausbesitzer',      // string, aus <h5.rubrik_n> extrahiert
    'icon_slug'   => 'hausbesitzer',      // string, aus img-src extrahiert (regex)
    'fact_sheets' => array(),             // Liste der Merkblaetter
)
```

### 1.3 Fact-Sheet-Item-Schema (parse_fact_sheets, Z. 156-161)

```
array(
    'id'          => '201',                       // string, aus href-Pattern (mb_201 oder ig_201)
    'title'       => 'Wegezoll Hausgemeinschaft', // string, aus <a.merkblatt>.textContent
    'description' => 'Was Sie wissen sollten...', // string, aus <p.teaser>-Childtexten
    'pdf_params'  => array(),                     // Sichere PDF-Parameter (KEINE kdnr)
)
```

`pdf_params` enthaelt nur Whitelist-Keys (Z. 239):
`merkblatt`, `header`, `id`, `rubrik`, `modus` (alle sanitize_text_field).

### 1.4 search_config-Schema (parse_search_config, Z. 260-264)

```
array(
    'search_placeholder' => 'Suchbegriff',  // aus #suchbegriff[placeholder]
    'has_search'         => true,           // Boolean-Flag (immer true z.Zt.)
)
```

### 1.5 MMB-Spezifika

- **Lazy-Akkordeon**: Templates rendern initial NUR die Header + Counts
  jeder Kategorie. Erste Kategorie pre-rendered nur in `card.php` und
  `compact.php` (Filter `dhps_mmb_card_prerender_first_category` /
  `dhps_mmb_compact_prerender_first_category`, default `true`).
  In `default.php` ist Pre-Render Filter `dhps_mmb_default_prerender_first_category`
  default `false`.
- **AJAX-Handler**: `DHPS_MMB_AJAX_Handler::handle_request` nimmt
  `service` + `category_id` + `layout`, ruft Parser, extrahiert die
  passende Kategorie, rendert ein Partial. Output ist `wp_kses_post`-gefiltert.
- **Search-Parser** (`DHPS_MMB_Search_Parser`): EIGENE Klasse, kein Top-Level
  in der Pipeline. Wird nur fuer AJAX-Search-Result-Render genutzt.
  Items-Shape ist `{id, title, description, pdf_params}` ohne Category-Bezug.
- **MIL-Sondercase**: PDF-URL ist DIREKT auf `deubner-online.de` aufloesbar
  statt Proxy (Partials Z. 59-62 in jedem der 3 Partials). Auth-Property
  `is_mil` steuert das. MMB-Adapter muss MIL-Service-Tag tolerieren.

---

## Sektion 2: Ziel-Datenmodell MMB

### 2.1 Mapping-Tabelle: MMB-Parser-Output -> Collection<ContentItem>

| Parser-Feld | DTO-Mapping | Begruendung |
|---|---|---|
| `categories[]` | Keine eigene Container-Klasse, aber: jedes `fact_sheet` wird zu einem ContentItem mit `category` = `category.id` | ContentList kann anhand Item-`category`-Feld gruppieren via `group_by('category')` |
| `categories[].id` | -> Item.`category` (gesamtes "rubrik_N") | Eindeutiger Bucket-Key, von Templates auch fuer Filter-Bar-Match genutzt |
| `categories[].name` | -> Collection-Meta `categories_meta[category_id]['name']` | Kategorie-Header braucht Name + Icon, gehoert nicht ins einzelne Item |
| `categories[].icon_slug` | -> Collection-Meta `categories_meta[category_id]['icon_slug']` | Wie name; Item-Level waere Duplikat fuer jedes Fact-Sheet |
| `categories[].fact_sheets[].id` | -> Item.`id` als `'mmb-doc-' + category_idx + '-' + sheet_id_or_idx` ODER `'mil-doc-...'` je nach $service | ID-Convention konsistent mit MAES-Adapter (`maes-doc-N`) |
| `categories[].fact_sheets[].title` | -> Item.`title` | Direktes Mapping |
| `categories[].fact_sheets[].description` | -> Item.`excerpt` | Description ist plain Text, kein HTML-Body |
| `categories[].fact_sheets[].pdf_params` | -> Item.`meta['pdf_params']` | Service-spezifisches Extra |
| `categories[].fact_sheets[]` | Item.`type` = `'document'` | Konsistent mit MAES-Merkblaetter-Mapping |
| `search_config` | -> Collection-Meta `search_config` | Search-Layer ist Collection-uebergreifend, nicht pro Item |
| `service_tag` | -> ignoriert (Pipeline ueberschreibt sowieso) | Standard-Verhalten alle Adapter |

### 2.2 Begruendung der Entscheidung

**Frage 1: `categories[]` als Collection-Meta oder als Item-`category`-Feld?**

Antwort: **Beide** - Items haben `category` (sodass Templates per
`group_by('category')` re-gruppieren koennen ohne Search-State zu verlieren),
und die Collection traegt zusaetzliche Category-Meta (Name + Icon + Reihenfolge)
in `meta['categories_meta']`. Begruendung:

- Tab-Filter im Template: matchet Item-`category` gegen Button-`data-filter`.
  Klappt nur, wenn jedes Item seine Category kennt.
- Counts pro Kategorie: berechnet ueber `count($collection->filter(...))` oder
  via Collection-Meta `categories_meta[id]['count']`. Template waehlt selbst.
- Reihenfolge wichtig fuer Lazy-Akkordeon (alle Templates rendern Kategorien
  in Parser-Order). `group_by('category')` waere alphabetisch sortiert nach
  Schluessel - daher Collection-Meta `categories_order: ['rubrik_1',
  'rubrik_2', ...]` zusaetzlich notwendig.

**Frage 2: Funktioniert ContentList mit Category-Grouping out-of-the-box?**

Nein. ContentList ist eine flache Item-Liste mit Grid/List-Layout (siehe
`public/views/components/content-list.php`). Es kennt keine Category-Header
und kein Akkordeon. **=> Templates rendern weiterhin MANUELL die Akkordeon-
Struktur mit foreach ueber Categories, nutzen aber pro Kategorie eine
Collection-Filter (`->filter(...)`) statt rohe `$category['fact_sheets']`.**

Das ist konsistent mit MAES-Pattern (Sub-Templates rendern manuell, nutzen
Collection-Filter intern).

**Frage 3: Was passiert mit `group_by('category')`?**

Funktioniert ohne Anpassung - Items haben Category-Feld. Aber Templates
brauchen die Output-REIHENFOLGE der Parser-Order. `group_by` liefert ein
PHP-Array, Schluesselreihenfolge entspricht Erst-Vorkommen pro Item -
das ist die Parser-Order, weil Adapter Items in Parser-Order anhaengt.

**Schlussfolgerung:** Adapter haengt Items in Parser-Order an
(category_idx-aufsteigend, dann fact_sheet-Order pro Kategorie). Templates
rufen `$collection->group_by('category')` -> Iterieren in Parser-Order, je
Bucket ein Akkordeon-Item. Header (name+icon+count) holen sie aus Collection-
Meta `categories_meta[category_id]`.

### 2.3 Collection-Meta-Felder (MMB)

```
array(
    'search_config'      => array(...),  // 1:1 Parser-search_config
    'categories_order'   => array('rubrik_1', 'rubrik_2', ...), // Reihenfolge der Buckets
    'categories_meta'    => array(
        'rubrik_1' => array(
            'name'      => 'Hausbesitzer',
            'icon_slug' => 'hausbesitzer',
            'count'     => 5,            // Anzahl gemappter Fact-Sheets (nach Title-Skip)
        ),
        // ...
    ),
    'total_documents'    => 27,          // Sum aller Fact-Sheets nach Title-Skip
)
```

---

## Sektion 3: MMB-Adapter-Snippet

```php
<?php
/**
 * MMB-Adapter (v0.17.1): wandelt DHPS_MMB_Parser-Output in DHPS_Content_Collection.
 *
 * Zweiter Adapter im einheitlichen Datenmodell (nach MAES). Mapped die
 * MMB-Category-Fact-Sheet-Struktur in eine flache ContentItem-Collection,
 * wo jedes Item via $item->category einer Rubrik zugeordnet ist. Header-
 * Daten (Name, Icon, Count) leben in Collection-Meta unter
 * `categories_meta[category_id]`.
 *
 * Service-Tolerant: Wird sowohl fuer `mmb` als auch `mil` registriert
 * (Trust-Decision F1-TD-MIL aus Discovery v0.17.1 Sektion 5). Der
 * Service-Tag kommt vom Pipeline-Aufrufer.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DHPS_MMB_Adapter
 *
 * @since 0.17.1
 */
final class DHPS_MMB_Adapter implements DHPS_Content_Adapter_Interface {

    /**
     * @inheritDoc
     */
    public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {

        $categories = isset( $parser_output['categories'] ) && is_array( $parser_output['categories'] )
            ? $parser_output['categories']
            : array();

        $items             = array();
        $categories_order  = array();
        $categories_meta   = array();
        $total_documents   = 0;

        foreach ( $categories as $cat_idx => $category ) {
            if ( ! is_array( $category ) ) {
                continue;
            }

            $cat_id        = isset( $category['id'] ) ? (string) $category['id'] : '';
            $cat_name      = isset( $category['name'] ) ? (string) $category['name'] : '';
            $cat_icon_slug = isset( $category['icon_slug'] ) ? (string) $category['icon_slug'] : '';
            $fact_sheets   = isset( $category['fact_sheets'] ) && is_array( $category['fact_sheets'] )
                ? $category['fact_sheets']
                : array();

            // Kategorie ohne ID + ohne Sheets: skippen (Robustheit).
            if ( '' === $cat_id && empty( $fact_sheets ) ) {
                continue;
            }

            $bucket_count = 0;
            foreach ( $fact_sheets as $sheet_idx => $sheet ) {
                if ( ! is_array( $sheet ) || empty( $sheet['title'] ) ) {
                    continue;
                }

                $title       = (string) $sheet['title'];
                $description = isset( $sheet['description'] ) ? (string) $sheet['description'] : null;
                $sheet_id    = isset( $sheet['id'] ) ? (string) $sheet['id'] : '';

                // ID-Konvention: '{service}-doc-{cat_idx}-{sheet_id_or_idx}'.
                // Wir nutzen cat_idx statt cat_id (rubrik_N), damit der String
                // kuerzer bleibt und konsistent zu MAES-`maes-doc-N` aufgebaut ist.
                $item_id_tail = ( '' !== $sheet_id ) ? $sheet_id : (string) $sheet_idx;
                $item_id      = $service . '-doc-' . (int) $cat_idx . '-' . $item_id_tail;

                $meta = array(
                    'category_index' => (int) $cat_idx,
                    'doc_index'      => (int) $sheet_idx,
                );
                if ( '' !== $sheet_id ) {
                    $meta['source_id'] = $sheet_id; // bewahrt parser-id fuer Templates.
                }
                if ( isset( $sheet['pdf_params'] ) && is_array( $sheet['pdf_params'] ) ) {
                    $meta['pdf_params'] = $sheet['pdf_params'];
                }

                $items[] = new DHPS_Content_Item(
                    $item_id,
                    $service,
                    $title,
                    'document',
                    '',           // body
                    $description, // excerpt
                    null,         // image
                    null,         // media
                    null,         // link
                    null,         // date
                    array(),      // tags
                    $cat_id,      // category
                    $meta
                );

                ++$bucket_count;
                ++$total_documents;
            }

            // Category-Meta nur dann eintragen, wenn wir eine ID haben - sonst
            // koennen Templates die Header-Daten nicht via Item.$category nachschlagen.
            if ( '' !== $cat_id ) {
                $categories_order[]            = $cat_id;
                $categories_meta[ $cat_id ]    = array(
                    'name'      => $cat_name,
                    'icon_slug' => $cat_icon_slug,
                    'count'     => $bucket_count,
                );
            }
        }

        $search_config = isset( $parser_output['search_config'] ) && is_array( $parser_output['search_config'] )
            ? $parser_output['search_config']
            : array();

        $meta = array(
            'search_config'    => $search_config,
            'categories_order' => $categories_order,
            'categories_meta'  => $categories_meta,
            'total_documents'  => $total_documents,
        );

        return new DHPS_Content_Collection( $service, $items, $meta );
    }
}
```

**Wichtige Aspekte:**

1. Adapter ist Service-AGNOSTISCH. Der Service-Tag wird vom Aufrufer (Pipeline)
   gesetzt und 1:1 ins Item geschrieben. Sowohl `mmb` als auch `mil` triggern
   denselben Adapter, der via $service-Param entscheidet welcher Tag rein
   geht. Item-IDs sind `mmb-doc-...` bzw. `mil-doc-...`.

2. Items behalten `category` = `rubrik_N` (Parser-ID). Templates matchen
   das gegen Filter-Bar-Buttons (heute: `data-filter="rubrik_N"`).

3. `meta['categories_order']` ist die Parser-Reihenfolge. Templates iterieren
   ueber diese Liste, holen die Items pro Bucket via
   `$collection->filter(fn($i) => $i->category === $cat_id)`.

4. `meta['categories_meta']` enthaelt die Akkordeon-Header-Daten. Templates
   greifen via `$collection->get_meta('categories_meta')[$cat_id]` zu.

---

## Sektion 4: Template-Migration MMB

### 4.1 Strategie: BC-Pattern wie MAES

Alle 3 Templates bekommen einen `$has_collection`-Check. Wenn vorhanden,
wird die Lazy-Akkordeon-STRUKTUR aus der Collection abgeleitet. Wenn nicht,
laeuft der Legacy-Pfad weiter.

**Wichtig:** Die LAZY-AJAX-LOGIK bleibt unveraendert. Der Adapter beruehrt
NICHT den `DHPS_MMB_AJAX_Handler`. AJAX-Endpoint liefert weiter HTML aus
den 3 Partials (`category-content.php`, `card-content.php`,
`compact-content.php`). Begruendung:

- Der AJAX-Handler hat eigene Konstanten + Security-Layer
  (`ALLOWED_LAYOUTS`, Rate-Limit, Nonce). Refactor waere risikoreich und
  ist nicht durch v0.17.1 motiviert.
- Die Partials konsumieren `$category` (Parser-Shape) und werden in
  v0.17.1 NICHT migriert. Erst v0.17.2+ Cleanup-Phase.
- Tech-Debt-Ticket TD-V0171-3 (siehe Sektion 10) dokumentiert dies fuer
  v0.17.x-Abschluss.

### 4.2 Template-Patch-Pattern (am Beispiel default.php)

Aktuell beginnt das Template bei Z. 116 mit
`foreach ( $categories as $index => $category )`. Migrations-Pattern:

```php
// --- Daten-Pfad waehlen: Collection wenn verfuegbar, sonst Legacy. ---
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
    // v0.17.1-Pfad: aus Collection rekonstruieren.
    $categories_order = (array) $collection->get_meta( 'categories_order', array() );
    $categories_meta  = (array) $collection->get_meta( 'categories_meta', array() );

    // Build ein Pseudo-$categories-Array, das den Legacy-Foreach unveraendert weiterfuettert.
    // Damit bleibt die Lazy-Akkordeon-Markup-Struktur exakt identisch (Bytewise-Smoke).
    $categories = array();
    foreach ( $categories_order as $cat_id ) {
        $cat_meta = isset( $categories_meta[ $cat_id ] ) && is_array( $categories_meta[ $cat_id ] )
            ? $categories_meta[ $cat_id ]
            : array();

        // Items dieser Category aus der Collection.
        $bucket = $collection->filter(
            static function ( $item ) use ( $cat_id ) {
                return $item instanceof DHPS_Content_Item && $item->category === $cat_id;
            }
        );

        // fact_sheets-Liste in Legacy-Shape rekonstruieren (Render-Logik unveraendert).
        $fact_sheets = array();
        foreach ( $bucket as $item ) {
            /** @var DHPS_Content_Item $item */
            $fact_sheets[] = array(
                'id'          => isset( $item->meta['source_id'] ) ? (string) $item->meta['source_id'] : '',
                'title'       => $item->title,
                'description' => null !== $item->excerpt ? $item->excerpt : '',
                'pdf_params'  => isset( $item->meta['pdf_params'] ) && is_array( $item->meta['pdf_params'] )
                    ? $item->meta['pdf_params']
                    : array(),
            );
        }

        $categories[] = array(
            'id'          => $cat_id,
            'name'        => isset( $cat_meta['name'] ) ? (string) $cat_meta['name'] : '',
            'icon_slug'   => isset( $cat_meta['icon_slug'] ) ? (string) $cat_meta['icon_slug'] : '',
            'fact_sheets' => $fact_sheets,
        );
    }

    // search_config evtl. aus Collection-Meta ziehen wenn Pipeline-$data leer ist.
    if ( empty( $search_config ) ) {
        $search_config = (array) $collection->get_meta( 'search_config', array() );
    }
}
// ELSE: $categories ist bereits aus $data gesetzt (Z. 33-37, Legacy-Pfad).
```

**Erkenntnis:** Migration ist DEFENSIV - der Render-Teil ab Z. 116
(foreach Categories -> Akkordeon-Markup) bleibt buchstaeblich byte-identisch.
Der Collection-Pfad rekonstruiert dieselbe `$categories`-Shape, sodass das
gerenderte HTML unveraendert bleibt (Smoke-Garantie).

**Vorteil:** Bytewise-Identitaet ist trivial verifizierbar.
**Nachteil:** Die "Schoenheit" der Migration ist begrenzt - wir konvertieren
hin und her. Das ist OK fuer Pilot 2; v0.17.x-Abschluss wird die Partials
nachziehen und dann das Pseudo-Array-Pattern entfernen.

### 4.3 Card.php / Compact.php

Analog zu default.php - exakt dasselbe Pseudo-`$categories`-Rebuild-Pattern,
da alle 3 Templates die gleichen Foreach-Logik mit derselben Markup-Klasse
nutzen. Nur die CSS-Klassen-Variation (`--card` / `--compact`) und das
Pre-Render-Filter unterscheiden sich.

---

## Sektion 5: MIL-Adapter-Frage

### 5.1 Optionen-Bewertung

**Option A**: Eigener `DHPS_MIL_Adapter extends DHPS_MMB_Adapter` mit
`$service = 'mil'` Override.

- Pro: Strikt typed, leicht erweiterbar wenn MIL spaeter divergiert
- Contra: Duplicate-Klasse ohne aktuellen Mehrwert; Erweiterungen koennten
  via Filter `dhps_pipeline_data_mil` oder Service-Wrapper-CSS gemacht
  werden ohne neue Klasse. Zudem 1 zusaetzliche autoloader-Datei.

**Option B (EMPFOHLEN)**: DHPS_MMB_Adapter wird fuer BEIDE Services
registriert.

```php
$mmb_adapter = new DHPS_MMB_Adapter();
DHPS_Content_Adapter_Registry::register( 'mmb', $mmb_adapter );
DHPS_Content_Adapter_Registry::register( 'mil', $mmb_adapter );
```

- Pro: Identisch zu Parser-Registry-Pattern (`$mmb_parser` 1x instanziert,
  fuer beide Tags registriert). Konsistente Konvention.
- Pro: Adapter ist `service`-agnostisch via Konstruktor-Param - er kennt
  seinen Tag erst beim `adapt($parser_output, $service)`-Call. Pattern
  funktioniert ohne weitere Anpassungen.
- Pro: Item-IDs sind `mil-doc-...` bzw. `mmb-doc-...` korrekt (Service-Tag
  wird in die ID gebaut).
- Pro: Item.`service` ist `mil` oder `mmb` korrekt - Branding-CSS-Hook
  greift.
- Contra: Wenn MIL-Spezifika wachsen, muss MMB_Adapter case-spezifisch
  werden (z.B. `if ( 'mil' === $service ) ...`) oder die Klasse splitten.
  Aktuell sind die MIL-Besonderheiten NUR im Partial-Layer (PDF-URL-
  Direktlink) - der Adapter sieht diese nicht.

**Option C**: MIL hat KEINEN Adapter, faellt auf $data-Legacy-Pfad zurueck.

- Pro: 0 Aenderung
- Contra: Bricht das Versprechen "alle MMB-Services collection-faehig".
  MIL-Templates haetten keinen `$collection`-Pfad und muessten weiter
  Pseudo-rebuilden. Inkonsistent.
- Contra: Live-Preview-Endpoint von MIL bekaeme kein $collection -
  Drift zwischen Frontend und Preview.

### 5.2 Empfehlung: **Option B**

Registrierung:

```php
// In Deubner_HP_Services.php, dhps_init(), nach der MAES-Adapter-Zeile:
$mmb_adapter = new DHPS_MMB_Adapter();
DHPS_Content_Adapter_Registry::register( 'mmb', $mmb_adapter );
DHPS_Content_Adapter_Registry::register( 'mil', $mmb_adapter );
```

Begruendung wie oben. Bei zukuenftiger MIL-Divergenz haben wir 2 Optionen:

1. Klasse splitten in `DHPS_MMB_Adapter` + `DHPS_MIL_Adapter extends
   DHPS_MMB_Adapter` mit dann konkretem Override.
2. Adapter intern `case`-en (`if ($service === 'mil')`).

Beide Pfade sind v0.17.1-konform offen und kein Tech-Debt-Block.

---

## Sektion 6: Sub-Shortcodes-Bridge (Tech-Debt M3)

### 6.1 Aktueller Pfad (Bestand)

`[maes_videos]` -> `DHPS_Shortcodes::handle_maes_videos` (falls vorhanden) oder
direkt `DHPS_MAES_Modules::render_videos($atts)` ueber Shortcode-API.
Innerhalb `render_videos`:

1. `$data = $this->get_data( $cache_ttl )` (eigener Cache-Key, nicht der
   Pipeline-Cache!)
2. Manuelle Filter-Logik (einzelvideo / videoliste)
3. `include $template;` mit Legacy-Variablen ($videos, $columns, ...)

**Problem:** Templates haben seit v0.17.0 BC-Pattern mit
`$has_collection`-Check. Beim Aufruf via Sub-Shortcode ist `$collection`
**niemals** gesetzt, weil die Pipeline umgangen wird. Templates fallen
also IMMER auf Legacy-Pfad - das funktioniert, ist aber inkonsistent mit
dem Pipeline-Pfad.

**Konsequenz:**

- Pipeline-Pfad: `$collection` ist gesetzt, Template nutzt v0.17.0-Pfad.
- Sub-Shortcode-Pfad: `$collection` ist nicht gesetzt, Template nutzt
  Legacy. Wir haben **2 unterschiedliche Renderpfade pro Template**.

### 6.2 Optionen-Bewertung

**Option A: Helper-Funktion `dhps_build_collection_for($tag, $parsed_data)`**

```php
function dhps_build_collection_for( string $tag, array $parsed_data ): ?DHPS_Content_Collection {
    if ( ! class_exists( 'DHPS_Content_Adapter_Registry' ) ) {
        return null;
    }
    $adapter = DHPS_Content_Adapter_Registry::for_service( $tag );
    if ( null === $adapter ) {
        return null;
    }
    try {
        return $adapter->adapt( $parsed_data, $tag );
    } catch ( \Throwable $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[DHPS] sub-shortcode adapter failure for "' . $tag . '": ' . $e->getMessage() );
        }
        return null;
    }
}
```

- Sub-Shortcodes rufen den Helper, geben `$collection` zusaetzlich ans Template.
- Vorteil: minimaler Eingriff, Pipeline bleibt unveraendert.
- Nachteil: 2 Stellen wissen vom Adapter (Pipeline + Helper). Aber:
  beide nutzen dieselbe Registry, daher kein doppeltes Wissen.

**Option B: MAES_Modules::get_data() liefert Collection statt array**

- BC-Bruch fuer alle hypothetischen externen Consumer von get_data()
  (Methode ist `private`, also kein externer Consumer - aber: das ist
  ein gefaehrlicher Refactor weil mehrere Methoden auf get_data zugreifen
  und auf das Array-Shape angewiesen sind).
- Sehr riskant. Abgelehnt.

**Option C (EMPFOHLEN): Neue Methode `get_collection()`, alte get_data bleibt**

```php
private function get_collection( int $cache_ttl = 3600 ): ?DHPS_Content_Collection {
    $data = $this->get_data( $cache_ttl );
    if ( null === $data ) {
        return null;
    }
    return dhps_build_collection_for( 'maes', $data );
}
```

- Beste BC-Garantie.
- Beste Testbarkeit (getrennte Verantwortlichkeiten).
- Sub-Shortcodes nutzen `get_collection()` direkt.
- Beste Vorbereitung fuer v0.17.2+ wenn TP_Modules / TPT_Modules dasselbe
  Pattern erben.

### 6.3 Empfehlung: **Option C + Helper-Pattern**

**Begruendung:**

1. Helper `dhps_build_collection_for()` ist die kanonische Adapter-Aufruf-
   Stelle ausserhalb der Pipeline. Wenn naechste Releases weitere Modules
   einfuehren (TP_Modules, TPT_Modules), nutzen die denselben Helper -
   wir haben dann EINE Stelle, an der Adapter-Filter-Hook + Try-Catch +
   Fail-Soft konsistent verankert sind.
2. `get_collection()` lebt in `DHPS_MAES_Modules` als duenne Wrapper-
   Methode. Spaeter (v0.17.2+) bekommt jede Modules-Klasse so eine
   Methode (TP_Modules, TPT_Modules). Diese Modules-Methoden sind die
   "Sub-Pipeline" fuer Sub-Shortcodes.
3. Render-Methoden (`render_videos`, `render_merkblaetter`,
   `render_aktuelles`) extrahieren $collection zusaetzlich und geben
   beides ans Template - identisch zur Pipeline-Renderer-Logik.

**Mini-Risiko:** Sub-Shortcode haengt am MAES-Cache statt am Pipeline-
Cache. Beim Aufruf von `get_collection()` wird zwar `get_data()` genutzt
(L2-Cache greift), aber der Adapter laeuft jedes Mal frisch (kein Cache).
Konsistent mit Pipeline-Entscheidung TD-7. Acceptance T6.

---

## Sektion 7: Schema-Vertrag (verbindlich!)

Schema-Vertrag-Vorgehen ist 9x in Folge ohne Critical-Drift gelaufen -
v0.17.1 ist Nummer 10. Disziplin halten.

### 7.1 MMB-Adapter Item-Konstruktor-Signatur (1 fact_sheet)

```php
new DHPS_Content_Item(
    $id,         // 'mmb-doc-{cat_idx}-{sheet_id_or_idx}' ODER 'mil-doc-...'
    $service,    // 'mmb' ODER 'mil' (aus $service-Param)
    $title,      // (string) $sheet['title'], Pflicht
    'document',  // type
    '',          // body (leer, weil Description in $excerpt)
    $excerpt,    // (string)|null aus $sheet['description'] - kein wp_kses
    null,        // image
    null,        // media
    null,        // link
    null,        // date
    array(),     // tags
    $cat_id,     // category = 'rubrik_N' aus Parser-ID
    $meta        // {category_index, doc_index, source_id?, pdf_params?}
);
```

**Meta-Felder-Vertrag:**

| Key | Typ | Pflicht | Quelle |
|---|---|---|---|
| `category_index` | int | ja | Schleifen-Index $cat_idx |
| `doc_index` | int | ja | Schleifen-Index $sheet_idx |
| `source_id` | string | nur wenn Parser-ID vorhanden | `$sheet['id']` (z.B. '201') |
| `pdf_params` | array | nur wenn Parser-PDF-Params vorhanden | `$sheet['pdf_params']` |

### 7.2 Collection-Meta-Felder fuer MMB

Siehe Sektion 2.3. Exakte Shape verbindlich:

```php
array(
    'search_config'    => array(),                   // 1:1 aus Parser
    'categories_order' => array(),                   // string[], z.B. ['rubrik_1', 'rubrik_2']
    'categories_meta'  => array(
        $cat_id => array(
            'name'      => '',  // string
            'icon_slug' => '',  // string
            'count'     => 0,   // int (Anzahl gemappter Items)
        ),
    ),
    'total_documents'  => 0,                         // int
)
```

### 7.3 Bridge-Helper-Signatur

**Datei:** `includes/helpers/dhps-content-helpers.php` (NEUE Datei,
existiert bisher nicht; siehe Sektion 10.3 fuer Autoloader-Notiz)
ODER **`includes/class-dhps-content-collection-builder.php`** als
Static-Helper-Klasse falls Helper-Funktionen unerwuenscht sind.

**Empfehlung:** Pure PHP-Function in einer neuen Datei
`includes/dhps-content-helpers.php`, manueller require im Bootstrap.
Begruendung: Funktion ist 5 Zeilen, hat kein State, Static-Klasse waere
Overkill. Bootstrap zieht die Datei via expliziten `require_once` rein
(NICHT Autoloader-Konvention).

```php
/**
 * Baut eine ContentCollection fuer einen Sub-Shortcode (umgeht die Pipeline).
 *
 * Verwendung in Modules-Klassen (DHPS_MAES_Modules, DHPS_TP_Modules, ...)
 * die Sub-Shortcodes registrieren und damit den normalen
 * DHPS_Content_Pipeline::render_service()-Flow umgehen, aber trotzdem
 * Adapter-faehige Collections an die Templates uebergeben sollen.
 *
 * Fail-Soft (analog Pipeline): bei Adapter-Exception wird null zurueckgegeben
 * und das Template faellt auf Legacy-Pfad zurueck.
 *
 * @since 0.17.1
 *
 * @param string $service     Service-Tag (z.B. 'maes').
 * @param array  $parsed_data Parser-Output (Legacy-Array-Shape).
 *
 * @return DHPS_Content_Collection|null Collection wenn Adapter erfolgreich, sonst null.
 */
function dhps_build_collection_for( string $service, array $parsed_data ): ?DHPS_Content_Collection {
    if ( ! class_exists( 'DHPS_Content_Adapter_Registry' ) ) {
        return null;
    }
    $adapter = DHPS_Content_Adapter_Registry::for_service( $service );
    if ( null === $adapter ) {
        return null;
    }
    try {
        return $adapter->adapt( $parsed_data, $service );
    } catch ( \Throwable $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
            error_log( sprintf(
                'DHPS sub-shortcode adapter failure for service "%s": %s',
                $service,
                $e->getMessage()
            ) );
        }
        return null;
    }
}
```

### 7.4 MAES_Modules::get_collection() Signatur

```php
/**
 * Liefert die MAES-Daten als DHPS_Content_Collection (v0.17.1-Brueckenmethode).
 *
 * @since 0.17.1
 *
 * @param int $cache_ttl Cache-TTL fuer die unterliegende get_data().
 *
 * @return DHPS_Content_Collection|null Collection oder null bei Fehler.
 */
private function get_collection( int $cache_ttl = 3600 ): ?DHPS_Content_Collection {
    $data = $this->get_data( $cache_ttl );
    if ( null === $data ) {
        return null;
    }
    return dhps_build_collection_for( 'maes', $data );
}
```

### 7.5 Sub-Shortcode-Template-BC-Pattern (analog MAES bereits gemacht)

Sub-Shortcodes (render_videos / render_merkblaetter / render_aktuelles)
extrahieren BEIDES und reichen weiter:

```php
$data       = $this->get_data( absint( $atts['cache'] ) );
if ( null === $data || empty( $data['videos'] ) ) {
    return '';
}
$collection = $this->get_collection( absint( $atts['cache'] ) );
$videos     = $data['videos'];

// ... bestehende Filter-Logik (einzelvideo/videoliste) auf $videos UNVERAENDERT ...

// Vor include $template fuer das Template-Scope verfuegbar machen.
// $collection ist OPTIONAL - Template's $has_collection-Check greift wenn null.

ob_start();
include $template;
return ob_get_clean();
```

**Wichtige Caveats:**

1. Bei `[maes_videos einzelvideo="3"]` wird `$videos` auf 1 Element
   reduziert. Die Collection enthaelt aber ALLE Videos. Das ist
   **Tech-Debt-T7 (siehe Sektion 10)** - Template sieht beides
   inkonsistent. Wahl in v0.17.1: Templates nutzen entweder $videos
   (Legacy-Pfad) wenn Filter-Param aktiv ODER nutzen $collection. Wir
   geben dem Template beides und akzeptieren dass `$has_collection`
   nur dann True wird wenn KEIN Filter aktiv ist:

   ```php
   // In render_videos, NACH Filter-Logik:
   $filter_active = ( $einzelvideo > 0 ) || ( '' !== $videoliste );
   if ( $filter_active ) {
       $collection = null; // Force Legacy-Pfad, weil Collection nicht gefiltert ist.
   }
   ```

   Begruendung: Sicherer Default. v0.17.2+ Tech-Debt: Collection-Filter
   im Sub-Shortcode mappen.

2. Bei `[maes_merkblaetter]` und `[maes_aktuelles]` gibt es heute KEINE
   Filter-Atts -> Collection darf immer durchgereicht werden.

---

## Sektion 8: Acceptance-Kriterien T1-T15

### T1: MMB-Adapter mit leerem Parser-Output

```php
$collection = ( new DHPS_MMB_Adapter() )->adapt( array(), 'mmb' );
```

Erwartet: `$collection->is_empty() === true`, `get_meta('total_documents') === 0`.

### T2: MMB-Adapter mit minimaler Category

```php
$parsed = array(
    'categories' => array(
        array(
            'id'          => 'rubrik_1',
            'name'        => 'Test-Rubrik',
            'icon_slug'   => 'unternehmer',
            'fact_sheets' => array(
                array( 'id' => '201', 'title' => 'Sheet 1', 'description' => 'desc',
                       'pdf_params' => array( 'merkblatt' => '201' ) ),
            ),
        ),
    ),
    'search_config' => array( 'has_search' => true ),
);
$collection = ( new DHPS_MMB_Adapter() )->adapt( $parsed, 'mmb' );
```

Erwartet:
- `count() === 1`
- `first()->id === 'mmb-doc-0-201'`
- `first()->type === 'document'`
- `first()->category === 'rubrik_1'`
- `first()->service === 'mmb'`
- `first()->meta['pdf_params']['merkblatt'] === '201'`
- `first()->meta['source_id'] === '201'`
- `get_meta('categories_order') === ['rubrik_1']`
- `get_meta('categories_meta')['rubrik_1']['count'] === 1`

### T3: MMB-Adapter mit MIL-Service-Tag

Selber Parser-Output wie T2, aber `adapt($parsed, 'mil')`:

Erwartet: `first()->id === 'mil-doc-0-201'`, `first()->service === 'mil'`.

### T4: MMB-Adapter ueberspringt Sheets ohne Title

Fact-Sheet mit `'title' => ''` wird ueberlesen. Counter
`total_documents` und `categories_meta[cat]['count']` reflektieren das.

### T5: MMB-Adapter behaelt Parser-Order

Bei 3 Kategorien wird `get_meta('categories_order')` eine Liste der
Kategorie-IDs in Parser-Order liefern. Items in `get_items()` sind in
derselben Reihenfolge gruppiert (Cat1-Items, dann Cat2, dann Cat3).

### T6: dhps_build_collection_for() mit nicht-registriertem Service

```php
$result = dhps_build_collection_for( 'unknown_xyz', array( 'foo' => 'bar' ) );
```

Erwartet: `$result === null`.

### T7: dhps_build_collection_for() mit MAES-Daten

```php
$data       = /* MAES-Parser-Output */;
$collection = dhps_build_collection_for( 'maes', $data );
```

Erwartet: `$collection instanceof DHPS_Content_Collection`,
`$collection->count() > 0`.

### T8: DHPS_MAES_Modules::get_collection() liefert dieselbe Shape wie Pipeline

Vergleich: Pipeline ruft `(new DHPS_MAES_Adapter())->adapt($parsed, 'maes')`.
Sub-Shortcode-Pfad ruft `$modules->get_collection()` -> intern denselben
Adapter. `to_array()`-Shapes MUESSEN identisch sein.

### T9: MMB-Pipeline-Smoke - Frontend `[mmb]` rendert HTML-bytewise

Vor- und nach-Migration: HTML-Diff zwischen v0.17.0 (ohne MMB-Adapter)
und v0.17.1 (mit MMB-Adapter aktiv) ist **0**. Pseudo-Categories-Rebuild
im Template muss das garantieren.

### T10: MIL-Pipeline-Smoke - Frontend `[mil]` rendert HTML-bytewise

Wie T9 fuer MIL. Bestaetigt dass MIL-Pfad via geteiltem Adapter klappt.

### T11: MMB-Card-Layout - Pre-Render der ersten Kategorie funktioniert

Mit `dhps_mmb_card_prerender_first_category` default true muss die erste
Kategorie in $pseudo_$categories[0] korrekte fact_sheets enthalten (sonst
ist das Pre-Render-Markup leer).

### T12: MMB-Lazy-AJAX-Endpoint UNVERAENDERT

`POST /wp-admin/admin-ajax.php?action=dhps_mmb_category_load` mit
`service=mmb&category_id=rubrik_3` liefert das gleiche HTML wie vor
v0.17.1. Beweist dass AJAX-Handler nicht beruehrt wurde.

### T13: Sub-Shortcode `[maes_videos]` rendert mit Collection-Pfad

Wenn KEIN `einzelvideo`/`videoliste`-Param gesetzt, ist `$collection`
im Template gesetzt. Smoke: HTML-Output identisch zur v0.17.0 (Bytewise).

### T14: Sub-Shortcode `[maes_videos einzelvideo="2"]` faellt auf Legacy

Mit Filter-Param ist `$collection === null` im Template (Force-Legacy
in render_videos). HTML-Output identisch zur v0.17.0.

### T15: Sub-Shortcode-Adapter-Exception ist Fail-Soft

Bei manipuliertem MAES-Parser-Output, der Adapter zum Werfen bringt
(z.B. `'service'` als Non-String), liefert `dhps_build_collection_for()`
null. `[maes_videos]` rendert weiter via Legacy. Smoke: kein PHP-Fatal.

---

## Sektion 9: Spec-Aufteilung

### 9.1 Empfehlung: 2 Specialists + Lead-Direct

**Begruendung:** F1 (MMB-Adapter+Templates+MIL-Registrierung) und F2
(Sub-Shortcodes-Bridge) sind ENTKOPPELT - F2 braucht nur die bereits
gelandeten v0.17.0 Klassen (DHPS_Content_Item / DHPS_Content_Collection /
DHPS_MAES_Adapter). F1 und F2 koennen parallel laufen. Reine Spec-Aufteilung
spart Zeit, identifiziert Schwachstellen schneller.

### 9.2 F1: MMB-Adapter + Template-Migration + MIL-Registrierung

**Scope:**
- `includes/class-dhps-mmb-adapter.php` (~140 LOC): MMB_Adapter-Klasse.
- `public/views/services/mmb/default.php` BC-Pattern (~30 LOC ergaenzt).
- `public/views/services/mmb/card.php` BC-Pattern (~30 LOC ergaenzt).
- `public/views/services/mmb/compact.php` BC-Pattern (~30 LOC ergaenzt).
- Bootstrap-Patch `Deubner_HP_Services.php`: 2 Zeilen Adapter-Registry-
  Calls (`mmb` + `mil`).

**Pflicht-Lesematerial:**
- Discovery (dieses Doc, insbesondere Sektion 3 + 4 + 7).
- `includes/class-dhps-maes-adapter.php` (Vorbild aus v0.17.0).
- `includes/class-dhps-content-item.php` + `class-dhps-content-collection.php`
  (Schema-Vertrag).
- `includes/parsers/class-dhps-mmb-parser.php`.
- `public/views/services/maes/aktuelles.php` (BC-Pattern Vorbild).

**Acceptance:** T1-T5, T9-T12 + Lead-Smoke `[mmb]` / `[mil]`.

**Aufwand:** M (mittel-leicht), ~250 LOC.

### 9.3 F2: Sub-Shortcodes-Bridge

**Scope:**
- `includes/dhps-content-helpers.php` (~40 LOC): `dhps_build_collection_for()`.
- `Deubner_HP_Services.php`: explicit `require_once` fuer den Helper.
- `includes/class-dhps-maes-modules.php`: neue Methode `get_collection()`
  (~10 LOC), Anpassung der 3 render_*-Methoden (~30 LOC).
- 0 Template-Aenderungen (die 3 MAES-Sub-Templates haben das BC-Pattern
  bereits seit v0.17.0).

**Pflicht-Lesematerial:**
- Discovery (dieses Doc, insbesondere Sektion 6 + 7.3-7.5).
- `includes/class-dhps-maes-modules.php`.
- `includes/class-dhps-maes-adapter.php`.
- `public/views/services/maes/videos.php` (verifizieren dass BC-Pattern
  greift bei Sub-Shortcode-Aufruf).

**Acceptance:** T6-T8, T13-T15.

**Aufwand:** S (small), ~100 LOC.

### 9.4 Lead-Direct

- Bootstrap-Registration Patch (Adapter-Registry-Calls + helper-require).
- Version-Bump `Deubner_HP_Services.php`, `README.md`.
- `docs/project/48-CHANGELOG-v0171.md`.
- MEMORY.md Milestone 19 + Implementation-Notes.

### 9.5 Phasen-Reihenfolge

```
Phase 1 (parallel): F1 (MMB) + F2 (Bridge)
Phase 2 (Lead):     Bootstrap-Patches (Registry + helper-require), Version-Bump
Phase 3 (parallel): QA-Specialist + SEC-Specialist
Phase 4 (Lead):     Doku, Stage-Smoke, Pre-Release-RC
```

### 9.6 Alternative: 1 grosser Spec (F12)

Nicht empfohlen. F1 und F2 sind unterschiedliche Subsysteme. Eng-Kopplung
nur in Bootstrap (4 Zeilen Lead-Direct). Spec-Doc-Trennung erlaubt
saubere Abnahme + Parallelisierung.

---

## Sektion 10: Risiken + Tech-Debt

### 10.1 Risiken-Matrix

| ID | Risiko | Severity | Mitigation |
|---|---|---|---|
| R1 | **Pseudo-Categories-Rebuild im Template fuehrt zu HTML-Drift** wenn der Adapter ein Feld vergisst | HIGH | Acceptance T9+T10 bytewise. Discovery 4.2 sagt klar welche 4 Felder rekonstruiert werden muessen. Pre-Release-Smoke mit `curl | diff` |
| R2 | **MIL-PDF-URL-Direktlink** lebt im Partial - wenn die Pseudo-Rebuilds die `pdf_params` falsch zurueckschreiben, brechen MIL-Downloads | MED | Acceptance T2/T3 prueft `meta['pdf_params']`-Roundtrip. Lead-Smoke testet `[mil]` Click |
| R3 | **`get_meta('categories_order')` ist leer** wenn alle Categories ohne ID -> Pseudo-`$categories` ist leer -> Akkordeon zeigt nichts | MED | Adapter trackt Categories ohne `id` nicht. Templates fallen via `$has_collection`-Pfad auf Legacy zurueck wenn order leer ist - Defensive Check ergaenzen |
| R4 | **Sub-Shortcode-Filter (`einzelvideo`/`videoliste`)** + Collection ergeben Item-Drift | MED | F2 forciert `$collection = null` wenn Filter aktiv (Sektion 6.3 Caveat 1). Tech-Debt-Ticket TD-V0171-1 fuer v0.17.2 |
| R5 | **AJAX-Handler braucht weiterhin Parser-Shape** - wenn das Pipeline-Filter `dhps_pipeline_data_mmb` etwas aendert das Templates aber AJAX nicht sieht, gibt es Schema-Drift Frontend vs AJAX | MED | AJAX-Handler beruehrt KEINE Pipeline und liest Parser-Output direkt aus dem Cache. Filter wirken NICHT auf AJAX-Output. Doku-Hinweis im AJAX-Handler-Header reicht (Tech-Debt-Ticket TD-V0171-2) |
| R6 | **`categories_order` als String-Array kollidiert mit numerischen Bucket-Schluesseln** in PHP (z.B. wenn ID wirklich nur `"1"`) - PHP castet Array-Keys still zu int | LOW | MMB-IDs sind `rubrik_N`, also nicht numerisch. Test T2 verifiziert. Doku-Hinweis im Adapter-Header |
| R7 | **Helper `dhps_build_collection_for()` global** = Namespace-Risiko bei Plugin-Konflikten | LOW | Funktion-Name hat `dhps_`-Prefix, kollidiert nicht. Andere DHPS-Helper folgen demselben Pattern (`dhps_component`, `dhps_request_alpine`) |
| R8 | **Adapter-Registry-Filter `dhps_content_adapter_for_service`** kann fuer `mmb` einen anderen Adapter zurueckgeben, der die MMB-Schema-Erwartungen nicht erfuellt | LOW | Bereits abgesichert via SEC-MEDIUM-2 Fix in v0.17.0 (Filter-Return-Type-Check + `_doing_it_wrong`). Doku im Adapter-Header |
| R9 | **Sub-Shortcode bei `[maes_videos einzelvideo="3"]` nutzt Legacy** - Schema-Identitaet ueber Pfade prueft niemand | LOW | Acceptance T14 prueft genau diesen Fall bytewise. Plus Tech-Debt TD-V0171-1 |
| R10 | **MAES_Modules cached eigene Daten unter `dhps_p_md5(...)`** - identisch zu Pipeline-Cache-Schluessel? | LOW | Geprueft: Pipeline-Cache nutzt denselben Prefix `dhps_p_` und L2-Key-Generation `endpoint|json(params)`. MAES_Modules nutzt denselben Algorithmus. Cache-Sharing zwischen Pipeline und Sub-Shortcode ist intendiert (Hit-Effizienz). Kein Risiko |

### 10.2 Tech-Debt-Tickets fuer v0.17.2+

| Ticket | Beschreibung | Zielversion |
|---|---|---|
| TD-V0171-1 | **Sub-Shortcode-Collection-Filter**: `[maes_videos einzelvideo="3"]` soll die Collection filtern statt auf Legacy zurueckzufallen. Erfordert Item-Index-Tracking via Item.meta.video_index | v0.17.2 |
| TD-V0171-2 | **MMB-AJAX-Handler-Migration**: Endpoint nutzt heute den Parser-Output direkt. Migration auf Adapter -> Collection -> Partial-Re-Render auf Collection-Basis | v0.17.x-Abschluss |
| TD-V0171-3 | **MMB-Partials auf Collection migrieren**: `category-content.php` / `card-content.php` / `compact-content.php` konsumieren noch `$category[fact_sheets]`-Shape. Auf Collection-`$bucket`-Pfad migrieren waere die saubere Loesung | v0.17.x-Abschluss |
| TD-V0171-4 | **MMB-Search-Parser-Bridge**: Search-Response nutzt eigenen Parser ohne Adapter-Bridge. Search-Results-Templates konsumieren Parser-Shape direkt. Wenn AJAX-Search auch Collection-faehig werden soll, braucht es einen 2. Adapter `DHPS_MMB_Search_Adapter` oder eine geteilte Mapping-Funktion | v0.17.x-Abschluss |
| TD-V0171-5 | **TP/TPT/LP-Adapter** (v0.17.2 Roadmap) | v0.17.2 |
| TD-V0171-6 | **MIO-Adapter mit tax_dates-Sondertyp** (v0.17.3 Roadmap) | v0.17.3 |
| TD-V0171-7 | **Adapter-Schema-Tests** automatisiert (z.B. Vergleich Parser-Output -> Collection -> ContentCard-Props gegen Snapshot) | v0.17.x-Abschluss |
| TD-V0171-8 | **PHP_Linter fuer `wp_kses_post` auf $excerpt im MMB-Adapter** - heute nicht sanitisiert (Plain-Text aus DOM-Parser), zukuenftig Defense-in-Depth? | v0.17.x-Abschluss |

### 10.3 Autoloader-Notiz (Lehre aus v0.17.0)

Autoloader-Konvention im Plugin: `class-dhps-foo-bar.php` -> `DHPS_Foo_Bar`.
F1 muss den Adapter als `DHPS_MMB_Adapter` benennen und die Datei
`includes/class-dhps-mmb-adapter.php` legen (NICHT `includes/adapters/`).

**Helper-Funktion** in `includes/dhps-content-helpers.php`: Folgt NICHT
der Klassen-Konvention, daher Autoloader greift nicht. F2 muss im
Bootstrap explizit `require_once DEUBNER_HP_SERVICES_PATH . 'includes/dhps-content-helpers.php';`
hinzufuegen. Empfohlene Position: vor der Adapter-Registry-Sektion in
`dhps_init()`.

### 10.4 Discovery-Lessons fuer Specialists

1. **Discovery-Doc-Klassenname check**: `DHPS_MMB_Adapter` (mit `_`, alle
   3 Buchstaben Underscore-frei weil Abkuerzung), Datei
   `class-dhps-mmb-adapter.php`.
2. **Bytewise-Smoke-Test mit `curl | diff`** vom Host (NICHT aus dem
   Container - v0.17.0-Lehre 2).
3. **wp_localize_script-Bridge fuer Live-Preview**: aktuell verteilt
   die `attsSchema` an JS. MMB-Atts existieren - `services-mmb` muss
   gepflegt sein. Live-Preview-Endpoint sollte mit MMB-Adapter aktiv
   nochmal getestet werden (T11 deckt das ab).

---

## Sektion 11: Spec-Briefing-Material

### 11.1 Dateipfade fuer Neuschoepfung (F1)

```
includes/class-dhps-mmb-adapter.php
```

### 11.2 Dateipfade fuer Neuschoepfung (F2)

```
includes/dhps-content-helpers.php
```

### 11.3 Dateipfade fuer Anpassung (Lead + F1 + F2)

```
Deubner_HP_Services.php
    - Version-Bump 0.17.0 -> 0.17.1 (Lead)
    - require_once includes/dhps-content-helpers.php (F2)
    - DHPS_Content_Adapter_Registry::register( 'mmb', $mmb_adapter ) (F1)
    - DHPS_Content_Adapter_Registry::register( 'mil', $mmb_adapter ) (F1)
public/views/services/mmb/default.php   (F1: BC-Pattern)
public/views/services/mmb/card.php      (F1: BC-Pattern)
public/views/services/mmb/compact.php   (F1: BC-Pattern)
includes/class-dhps-maes-modules.php    (F2: get_collection() + render_*-Patch)
README.md                               (Lead: Version-Bump)
docs/project/48-CHANGELOG-v0171.md      (Lead: Release-Doku, NEU)
MEMORY.md                               (Lead: MILESTONE 19)
```

### 11.4 Bootstrap-Diff-Beispiel (Lead)

```php
// In dhps_init(), nach Z. 300 (MAES-Adapter-Registrierung):

// MMB-Adapter (v0.17.1): wird sowohl fuer 'mmb' als auch 'mil'
// registriert, weil MIL den MMB-Parser teilt und Adapter Service-
// agnostisch ist.
$mmb_adapter = new DHPS_MMB_Adapter();
DHPS_Content_Adapter_Registry::register( 'mmb', $mmb_adapter );
DHPS_Content_Adapter_Registry::register( 'mil', $mmb_adapter );
```

```php
// VOR der Adapter-Sektion (oder am Anfang von dhps_init()):

// Helper-Funktionen fuer das einheitliche Datenmodell (v0.17.1).
require_once DEUBNER_HP_SERVICES_PATH . 'includes/dhps-content-helpers.php';
```

---

## Anhang: Lead-Briefing-Zusammenfassung

| Frage | Antwort |
|---|---|
| **MIL-Adapter-Entscheidung** | **Option B** - MMB_Adapter wird fuer beide Services (`mmb`, `mil`) registriert. Adapter ist service-agnostisch. Eigener MIL_Adapter waere over-engineering. |
| **Sub-Shortcodes-Bridge-Entscheidung** | **Option C + Helper** - Helper `dhps_build_collection_for()` als kanonische Aufruf-Stelle ausserhalb der Pipeline, plus neue `get_collection()`-Methode pro Modules-Klasse. Beste BC-Garantie, sauberste Verteilung der Verantwortlichkeit. |
| **Spec-Aufteilung** | **2 Specialists parallel** - F1 (MMB-Adapter+Templates+MIL-Reg) und F2 (Sub-Shortcodes-Bridge) sind entkoppelt. Lead macht Bootstrap + Doku |
| **Top-3-Risiken** | R1 (Pseudo-Categories-Rebuild HTML-Drift), R4 (Sub-Shortcode-Filter + Collection Item-Drift), R5 (AJAX-Handler-Schema-Drift gegen Pipeline-Filter) |
| **Geschaetzter Aufwand** | **M (mittel)** - F1 ~250 LOC, F2 ~100 LOC, Lead ~50 LOC, Doku ~200 LOC = ca. 600 LOC. 1 Discovery-Doc (dieses), 2 Spec-Docs, QA+SEC parallel. |
| **Schema-Vertrag-Status** | Sektion 7 ist verbindlich. 10. Iteration in Folge - Disziplin halten |

**Risiko-Gegenmittel-Map:**

- R1: Bytewise-HTML-Smoke (Acceptance T9+T10) + explizite Schema-Vertrag-
  Felder in Sektion 7.1+7.2 (was geht rein, was kommt raus)
- R4: Force-Legacy bei Filter-Param (Sektion 6.3 Caveat 1) + Tech-Debt-
  Ticket TD-V0171-1
- R5: Doku-Hinweis im AJAX-Handler-Header + Tech-Debt TD-V0171-2; aktuelles
  Verhalten ist OK weil AJAX-Endpoint mit Cache-Key konsistent zur Pipeline
  arbeitet

**Reihenfolge fuer Implementation:**

1. Phase 1 parallel: F1 MMB-Adapter + Templates / F2 Bridge-Helper +
   MAES_Modules-Patch.
2. Phase 2 Lead: Bootstrap-Patches mergen (Adapter-Registry + helper-require).
3. Phase 3 parallel: QA-Smoke (T1-T15) + SEC-Audit (Filter-Hook-Verification,
   Helper-XSS-Diagnose).
4. Phase 4 Lead: Stage-Smoke, CHANGELOG, MEMORY, RC-Tag.

**Ende Discovery v0.17.1.**
