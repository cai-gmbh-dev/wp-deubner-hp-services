# Sub-Shortcode-Adapter-Pattern (Architektur-Referenz)

## Stand: 2026-06-07 (v0.17.5 TD-V0174-2)

## Zweck

Diese Doku klaert die in v0.17.4 unscharf formulierte Tech-Debt **TD-V0174-2**
("Sub-Adapter fuer `mio_termine`/`maes_videos`/etc Whitelist-Aufnahme"). Sie
beantwortet final die Architektur-Frage: **brauchen Sub-Shortcodes eigene
Adapter-Klassen?**

**Kurze Antwort: Nein.** Sub-Shortcodes nutzen den Adapter ihres Haupt-Services.

## Sub-Shortcode-Inventar

Plugin hat aktuell **4 Sub-Shortcodes**:

| Sub-Shortcode | Haupt-Service | Bridge-Pfad |
|---------------|---------------|-------------|
| `[mio_termine]` | mio | `DHPS_Steuertermine::get_collection()` (v0.17.5) |
| `[maes_videos]` | maes | `DHPS_MAES_Modules::get_collection()` (v0.17.1) |
| `[maes_merkblaetter]` | maes | `DHPS_MAES_Modules::get_collection()` (v0.17.1) |
| `[maes_aktuelles]` | maes | `DHPS_MAES_Modules::get_collection()` (v0.17.1) |

Zusaetzlich: **AJAX-Sub-Pfade** (z.B. MMB-Search) nutzen Helper-Side-Channels
(`dhps_mmb_search_to_collection`) statt Adapter-Registry.

## Architektur-Entscheidung

### Sub-Shortcodes wiederverwenden den Haupt-Service-Adapter

Begruendung:

1. **DTO-Whitelist ist Service-orientiert, nicht Shortcode-orientiert.**
   `DHPS_Content_Item::ALLOWED_SERVICES` enthaelt 13 Eintraege: 9
   Hauptservices + 4 Sub-Shortcode-Slugs. Die 4 Sub-Shortcode-Slugs sind
   aber NUR als Item-Service-Wert zugelassen, NICHT als Adapter-Registry-Tag.
2. **Mehrfach-Registrierung ist nicht noetig**. Sub-Shortcodes-Bridges
   rufen `dhps_build_collection_for($main_service, $parsed_data)` mit dem
   **Haupt-Service-Tag**. Der Adapter sieht ein normales Parser-Output und
   liefert eine normale Collection. Item-`service` ist dann der Haupt-
   Service (`mio` fuer `[mio_termine]`, `maes` fuer `[maes_*]`).
3. **Eigene Sub-Adapter waeren Anti-Pattern.** Sie wuerden:
   - die 1-Adapter-pro-Service-Konvention brechen
   - Schema-Drift-Risiko erhoehen (5 statt 1 Mapping-Stelle pro Service)
   - dem Registry-Pattern widersprechen (Mehrfach-Eintraege fuer
     verwandte Datenquellen)

### Force-Legacy-Pattern bei Filter-Atts

Sub-Shortcodes haben oft Filter-Atts (`einzelvideo`, `videoliste`, `month`,
`count`, ...) die VOR dem Render auf das Parser-Output angewendet werden.

Der **Adapter sieht das gefilterte Parser-Output**, kann aber die
Filter-Semantik nicht im Item-Set spiegeln (Items in der Collection sind
in der Reihenfolge des Parser-Outputs, nicht in der Filter-Reihenfolge).

Loesung: **Force-Legacy bei aktiven Filter-Atts** - die Bridge-Methode
returnt `null`, Template faellt automatisch auf Legacy-Pfad.

```php
private const FORCE_LEGACY_ATTS = array( /* Att-Namen, die Force-Legacy triggern */ );

public function get_collection( array $atts, array $parsed_data ): ?DHPS_Content_Collection {
    foreach ( self::FORCE_LEGACY_ATTS as $att_name ) {
        if ( /* att aktiv und nicht-default */ ) {
            return null;
        }
    }
    return dhps_build_collection_for( $main_service, $parsed_data );
}
```

Default-Wert-Toleranz pro Att-Typ:

- String-Atts: `'' === trim($value)` zaehlt als nicht-gesetzt
- Numerische-Atts: `'0' === trim($value)` zaehlt als nicht-gesetzt
- Enum-Atts: spezifischer Default-Wert (z.B. `'all'` fuer `month`) zaehlt als nicht-gesetzt

## Template-Pattern

Sub-Shortcode-Templates pruefen `$has_collection` und rebuilden Legacy-Form:

```php
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection && function_exists( 'dhps_{main_service}_item_to_legacy_*' ) ) {
    $rebuilt = array();
    foreach ( $collection as $item ) {
        $legacy = dhps_{main_service}_item_to_legacy_*( $item );
        if ( ! empty( $legacy ) ) {
            $rebuilt[] = $legacy;
        }
    }
    if ( ! empty( $rebuilt ) ) {
        $data = $rebuilt;
    }
}
```

Render-Code unter dem Block ist **bytewise unveraendert** (BC-Garantie).

## AJAX-Sub-Pfade: Helper-Side-Channel-Pattern

AJAX-Handler (z.B. MMB-Search, MMB-Lazy-Akkordeon) liefern oft eine andere
Daten-Shape als der Haupt-Service-Parser. Beispiel: MMB-Search-Parser liefert
flache Result-Liste, nicht Categories-Struktur.

Loesung: **Helper-Funktion in `dhps-content-helpers.php`** + **Action-Hook**:

```php
// Helper liefert Collection (Side-Channel, NICHT in der JSON-Response)
$search_collection = dhps_mmb_search_to_collection( $parsed, $service_tag );

// Plugins/Themes konsumieren via Action-Hook
do_action( 'dhps_mmb_search_collection', $search_collection, $parsed, $service_tag );
```

**Frontend-JS-Vertrag bleibt unveraendert** - die JSON-Response ist bytewise
identisch zu pre-Bridge-Version.

## Inventar der Helper-Pattern (Stand v0.18.3)

| Helper | Pfad | Verwendung | Seit |
|--------|------|------------|------|
| `dhps_build_collection_for($service, $parsed)` | Sub-Shortcode-Bridges | MAES + Steuertermine | v0.17.1 |
| `dhps_collection_or_empty($col, $service)` | Pipeline-Garantie 3.B | alle Templates | v0.18.0 |
| `dhps_mmb_search_to_collection($parsed, $service)` | AJAX-Search-Side-Channel | MMB-Search-AJAX | v0.17.5 |
| `dhps_mmb_category_to_collection($cat, $service, $extra_meta=[])` | AJAX-Lazy-Akkordeon-Side-Channel | MMB-Category-AJAX | v0.18.2 / v0.18.3 |
| `dhps_mio_news_to_collection($parsed, $service, $extra_meta=[])` | AJAX-News-Side-Channel | MIO-News-AJAX | v0.18.2 / v0.18.3 |
| `dhps_mmb_collection_to_legacy_categories($col)` | Template-Rebuild | 3 MMB-Templates | v0.18.0 |
| `dhps_tp_collection_to_legacy_categories($col)` | Template-Rebuild | TP-Templates | v0.18.0 |
| `dhps_tp_item_to_legacy_video($item)` | Template-Rebuild | TP/TPT/LP-Templates | v0.17.2 |
| `dhps_mio_item_to_legacy_month($item)` | Template-Rebuild | MIO/Steuertermine-Templates | v0.17.3 |
| `dhps_partial_date_to_iso($input, $format)` | Adapter-Beimaterial | TP/TPT/MIO-Adapter | v0.18.1 |

**10 Helper-Funktionen** in 4 Helper-Files: `dhps-content-helpers.php`,
`dhps-tp-content-helpers.php`, `dhps-mio-content-helpers.php`,
`dhps-date-helpers.php`.

## Action-Hook-Side-Channels (Stand v0.18.3)

| Hook | Seit | Trigger | Parameter |
|------|------|---------|-----------|
| `dhps_mmb_search_collection` | v0.17.5 | MMB/MIL-Search-AJAX | $col, $parsed, $service_tag |
| `dhps_mmb_category_collection` | v0.18.2 | MMB/MIL-Lazy-Akkordeon-AJAX | $col, $category, $service |
| `dhps_news_collection` | v0.18.2 | MIO-News-Container-AJAX | $col, $parsed, 'mio' |

Alle drei sind **`do_action`** (NICHT `apply_filters`) - Subscriber sehen die
Collection, koennen aber die AJAX-JSON-Response NICHT veraendern.

## Konvention: $extra_meta-Param (seit v0.18.3)

Side-Channel-Helper, deren AJAX-Handler **Aufruf-Kontext** besitzen
(z.B. Layout-Hint, Filter-Atts), exponieren einen optionalen 3. Param
`array $extra_meta = array()`. Der Helper merged ihn in die Collection-Meta:

```php
$collection_meta = array_merge(
    $extra_meta,            // 1) Aufrufer-Kontext zuerst
    array(                  // 2) Helper-Defaults dominieren
        'is_lazy_category' => true,
        // ...
    )
);
```

**Merge-Order-Konvention**: bei Key-Kollision **gewinnen Helper-Defaults**.
Damit bleiben Side-Channel-Invarianten (`is_lazy_category`, `is_news` etc.)
erhalten, auch wenn ein Aufrufer sie versehentlich ueberschreibt.

**Wann anbieten**: wenn der AJAX-Handler Request-Kontext besitzt, den der
Parser/Helper nicht aus den Daten herleiten kann (Layout, Filter-Atts).

**Wann NICHT anbieten**: bei reinen Lookup-Helpern, die schon alle relevanten
Felder aus dem Parser-Output lesen (`dhps_mmb_search_to_collection` -
Search-Query ist bereits in `query`-Meta).

## Konvention: Item-Meta-Indices

Side-Channel-Helper nutzen **kontext-spezifische Index-Namen** statt einer
einheitlichen Konvention:

| Helper | Index-Schluessel | Semantik |
|--------|------------------|----------|
| `dhps_mmb_search_to_collection` | `result_index` | Index in der Search-Result-Liste |
| `dhps_mmb_category_to_collection` | `sheet_index` | Index im `fact_sheets`-Array dieser Kategorie |
| `dhps_mio_news_to_collection` | `group_index` (+ `article_index` intern) | Index der Gruppe (+ Position innerhalb) |

**Begruendung**: semantische Klarheit > Cross-Helper-Konsistenz. Indices
sind **Side-Channel-intern** (nicht in JSON-Response, kein Frontend-JS-
Konsument), daher kein BC-Risiko durch unterschiedliche Namen.

**Bei neuen Helpern**: kontext-spezifischen Namen waehlen, der den
Container-Typ widerspiegelt (`row_index` fuer Tabellen, `entry_index` fuer
Listen, etc.).

## Anti-Pattern

**Bitte VERMEIDEN**:

1. **Eigene Sub-Adapter-Klassen fuer Sub-Shortcodes/AJAX-Pfade.** Sie wuerden
   die 1-Adapter-pro-Service-Konvention brechen.
2. **Sub-Shortcode-Slugs in `Adapter_Registry`** registrieren (z.B.
   `register('mio_termine', $adapter)`). Sub-Shortcodes bekommen ihre
   Collection via Helper, nicht via Registry-Lookup.
3. **Filter-Atts ignorieren**. Wenn Sub-Shortcode Filter-Atts hat (count,
   month, einzelvideo, ...), MUSS die Bridge Force-Legacy implementieren.
4. **AJAX-JSON-Response veraendern**, um Collection-Items einzubauen. Side-
   Channel via Action-Hook ist Pflicht.

## Roadmap

| Sub-Shortcode | Status | Geplant |
|---------------|--------|---------|
| `[maes_videos]` | Bridge aktiv (v0.17.1) | - |
| `[maes_merkblaetter]` | Bridge aktiv (v0.17.1) | - |
| `[maes_aktuelles]` | Bridge aktiv (v0.17.1) | - |
| `[mio_termine]` | Bridge aktiv (v0.17.5) | - |
| MMB-Search-AJAX | Helper-Side-Channel aktiv (v0.17.5) | - |
| MMB-Lazy-Akkordeon-AJAX | offen (TD-V0171-2) | v0.17.6 |
| MIO-News-Container-AJAX | offen (TD-V0174-1) | v0.18.0 |

Nach v0.17.6 + v0.18.0 sind alle Pfade Bridge-faehig.
