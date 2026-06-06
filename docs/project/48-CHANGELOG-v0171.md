# Changelog v0.17.1 - MMB-Adapter + Sub-Shortcodes-Bridge

## Stand: 2026-06-04

## Mission

Fortsetzung der inkrementellen Datenmodell-Migration aus v0.17.0:

- **F1**: zweiter Service-Adapter MMB (plus MIL via gleiche Adapter-Instanz)
- **F2**: Tech-Debt m3 abgearbeitet - Sub-Shortcodes `[maes_videos]`/`[maes_merkblaetter]`/`[maes_aktuelles]` nutzen jetzt ebenfalls den Adapter-Pfad (vorher umgangen die Pipeline)

## Hauptaenderungen

### 1) MMB-Adapter (F1, 1 neue Klasse, 3 Templates migriert)

#### `DHPS_MMB_Adapter`

`includes/class-dhps-mmb-adapter.php` (NEU, ca. 180 LOC)

- `final class DHPS_MMB_Adapter implements DHPS_Content_Adapter_Interface`
- Mappet `categories[].fact_sheets[]` -> `ContentItem` mit `type='document'`
- Item-ID-Convention: `{service}-doc-{cat_idx}-{sheet_id}`
- Item-Service: uebernimmt $service-Param (mmb oder mil)
- Item-meta: `pdf_params`, `icon_slug`, `category_id`, `category_name`, `category_index`, `doc_index`, optional `source_id`
- Collection-Meta: `categories_order`, `categories_meta`, `search_config`, `total_documents`
- Robustheit: Items ohne Title werden skip, defensive Casts

#### MIL via gleicher Adapter-Instance (Option B)

`Deubner_HP_Services.php` `dhps_init`:

```php
$mmb_adapter = new DHPS_MMB_Adapter();
DHPS_Content_Adapter_Registry::register( 'mmb', $mmb_adapter );
DHPS_Content_Adapter_Registry::register( 'mil', $mmb_adapter );
```

Begruendung Option B (statt eigenem MIL-Adapter):

- MMB-Adapter ist service-agnostisch (Item-Service kommt vom Param)
- Identisches Pattern wie Parser-Registry
- MIL-Spezifika (PDF-URL-Direktlink) leben in den Partials, nicht im Adapter
- Klasse-Split spaeter offen, kein Tech-Debt

#### Template-Migration: 3 MMB-Templates (Pseudo-Rebuild-Pattern)

`public/views/services/mmb/default.php`, `card.php`, `compact.php`:

BC-Pattern mit **Pseudo-Rebuild**: aus Collection wird die Legacy-`$categories`-Shape rekonstruiert, sodass das **bestehende Lazy-Akkordeon-Markup bytewise unveraendert bleibt**:

```php
if ( $has_collection && ! empty( $categories_overview ) ) {
    $categories_overview = $collection->get_meta( 'categories_order', [] );
    foreach ( $collection as $item ) {
        $cat_id = $item->meta['category_id'] ?? '';
        $items_by_category[ $cat_id ][] = [ /* fact_sheets-shape rebuild */ ];
    }
    $categories    = /* rebuild from overview + items_by_category */;
    $search_config = $collection->get_meta( 'search_config', [] );
} else {
    // Legacy-Pfad UNVERAENDERT
    $categories    = $data['categories']    ?? [];
    $search_config = $data['search_config'] ?? [];
}
// AB HIER: bestehendes Markup nutzt $categories/$search_config unveraendert
```

Vorteile:

- AJAX-Handler (Lazy-Loading) bleibt unangetastet
- Partials (`mmb/partials/*.php`) unangetastet
- Bytewise BC garantiert (Page 6 MMB-Klassen-Diff: leer)
- Tab-Filter-Markup unveraendert

### 2) Sub-Shortcodes-Bridge (F2, Tech-Debt m3 aus v0.17.0)

#### Helper `dhps_build_collection_for`

`includes/dhps-content-helpers.php` (NEU)

Globale Funktion (kein Klasse, analog `dhps_component()`):

```php
function dhps_build_collection_for( string $service, array $parsed_data ): ?DHPS_Content_Collection {
    if ( ! class_exists( 'DHPS_Content_Adapter_Registry' ) ) return null;
    $adapter = DHPS_Content_Adapter_Registry::for_service( $service );
    if ( null === $adapter ) return null;
    try {
        return $adapter->adapt( $parsed_data, $service );
    } catch ( \Throwable $e ) {
        if ( function_exists( 'error_log' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( 'DHPS adapter failure (helper) for service "%s": %s', $service, $e->getMessage() ) );
        }
        return null;
    }
}
```

Fail-Soft analog Pipeline-Logik. EINE Stelle fuer Adapter-Filter ausserhalb der Pipeline.

#### `DHPS_MAES_Modules::get_collection()`

Neue Methode in `includes/class-dhps-maes-modules.php`:

```php
public const FORCE_LEGACY_ATTS = [ 'einzelvideo', 'videoliste', 'kategorie', 'rubrik' ];

public function get_collection( string $section, array $atts = [] ): ?DHPS_Content_Collection {
    // Force-Legacy bei aktiven Filter-Atts
    foreach ( self::FORCE_LEGACY_ATTS as $att ) {
        if ( isset( $atts[ $att ] ) ) {
            $value = trim( (string) $atts[ $att ] );
            if ( '' !== $value && '0' !== $value ) return null;
        }
    }
    $parsed_data = $this->get_data( $section, $atts );
    return dhps_build_collection_for( 'maes', $parsed_data );
}
```

**Force-Legacy-Pattern**: bei aktiven Filter-Atts (nicht-Default) returnt `null` -> Sub-Shortcode-Template faellt auf Legacy-Pfad. Begruendung: Filter wirken im Modules-Layer auf das Array; der Adapter wuerde das gefilterte Subset zeigen ohne die Filter-Semantik im Item-Set zu spiegeln.

#### Sub-Shortcode-Handler patchen

`includes/class-dhps-maes-modules.php` (Sub-Shortcode-Handler liegen hier, nicht in `class-dhps-shortcodes.php` - QA-M1 Korrektur):

3 Handler `render_videos`/`render_merkblaetter`/`render_aktuelles` setzen jetzt `$collection = $this->get_collection(...)` vor `include $template`. Template-Scope hat `$collection` verfuegbar - vorhandenes `$has_collection`-Pattern aus v0.17.0 in den 3 Sub-Templates greift automatisch.

### 3) Bootstrap-Integration

`Deubner_HP_Services.php`:

- Plus `require_once includes/dhps-content-helpers.php` nach Component-Helpers-Block
- Plus `register('mmb', $a); register('mil', $a)` in dhps_init nach MAES-Reg
- Version 0.17.0 -> 0.17.1 (3 Stellen)

## Backward Compatibility

**Vollstaendig BC**:

- 9 Parser unveraendert
- MMB-AJAX-Handler unangetastet (Tech-Debt TD-V0171-2 fuer v0.17.x-Abschluss)
- MMB-Partials unangetastet
- 3 MAES-Templates aus v0.17.0 unveraendert (BC-Pattern greift weiter)
- Lazy-Akkordeon-Trigger funktional
- Page 6 MMB-Klassen-Diff vor/nach v0.17.1: leer (bytewise)

## QA + Security (P4 laeuft)

### Lead-Smoke (P3)

- F1-Tests: **10/10 PASS** (Stage-Container)
- F2-Tests: **14/14 PASS** (Stage-Container)
- Page 6 MMB-Klassen-Diff: leer
- 282 dhps-mmb-item__* Klassen rendern unveraendert
- 3 Adapters registriert: mmb=Y, mil=Y, maes=Y
- Helper-Funktion `dhps_build_collection_for` aktiv
- debug.log clean (nur erwartete T10-Throw-Sim)

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/27-MMB-SUBSHORTCODES-ADAPTER-PLAN-v0171.md` | Discovery (Schema-Vertrag) |
| `docs/project/48-CHANGELOG-v0171.md` | (dieses Dokument) |
| `includes/class-dhps-mmb-adapter.php` | MMB-Adapter (F1) |
| `includes/dhps-content-helpers.php` | dhps_build_collection_for-Helper (F2) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.17.0 -> 0.17.1, MMB+MIL-Adapter-Reg, Helper-require_once |
| `README.md` | Version-Bump |
| `includes/class-dhps-maes-modules.php` | Neue FORCE_LEGACY_ATTS const + get_collection-Methode + Patch in render_videos/merkblaetter/aktuelles |
| `public/views/services/mmb/default.php` | BC-Pattern Pseudo-Rebuild |
| `public/views/services/mmb/card.php` | BC-Pattern Pseudo-Rebuild |
| `public/views/services/mmb/compact.php` | BC-Pattern Pseudo-Rebuild |
| `MEMORY.md` | MILESTONE 19 + 8 v0.17.1 Implementation-Notes |

## Trust-Decisions (kumulativ T27-T29)

| # | Decision | Begruendung |
|---|----------|-------------|
| T27 | MIL via gleicher Adapter-Instance | Adapter ist service-agnostisch (Param), spaetere Klasse-Split offen |
| T28 | Pseudo-Rebuild statt direkter Collection-Iteration | Lazy-Akkordeon-Markup bleibt bytewise, AJAX-Handler unangetastet |
| T29 | Force-Legacy bei Filter-Atts | Filter-Semantik kann Adapter nicht spiegeln, Legacy-Pfad ist sicherer |

(kumulativ 29 Trust-Decisions seit v0.14.0)

## Tech-Debt-Tickets fuer v0.17.x

- **TD-V0171-1**: MAES-Sub-Shortcode-Filter-Atts -> Collection-Adapter selbst kennt die Atts (statt Force-Legacy). Erfordert Adapter-Param-Erweiterung
- **TD-V0171-2**: MMB-AJAX-Handler (Lazy-Akkordeon) auf Adapter umstellen. Aktuell direkter Parser-Aufruf
- **TD-V0171-3**: MMB-Search-AJAX (Sucher) auf Adapter umstellen

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.17.2** | TP/TPT/LP-Adapter (Video-Items mit Featured+Categories) |
| **v0.17.3** | MIO-Adapter (komplexester - Tax-Dates Sondertyp) |
| **v0.17.4** | TC-Adapter (Wrapper) + AJAX-Handler-Migration |
| **v0.18.0** | Legacy-Pfad entfernen (nach allen Service-Migrationen) |

## Bilanz v0.17.1

- **Zweiter Service-Adapter** (MMB) produktiv, MIL automatisch mit
- **Sub-Shortcodes-Bridge** loest Tech-Debt m3 aus v0.17.0
- **Pseudo-Rebuild-Pattern** als wiederverwendbares BC-Pattern fuer komplexe Templates etabliert
- **Force-Legacy-Pattern** schuetzt vor Filter-Drift
- F1 + F2 Tests: **24/24 PASS** (10 + 14)
- 0 BC-Bruch (Page 6 MMB-Diff leer)
- Schema-Vertrag-Vorgehen **9x in Folge** ohne Critical-Drift
