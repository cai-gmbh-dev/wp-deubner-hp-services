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

## Inventar der Helper-Pattern (Stand v0.17.5)

| Helper | Pfad | Verwendung |
|--------|------|------------|
| `dhps_build_collection_for($service, $parsed_data)` | Sub-Shortcode-Bridges | MAES + Steuertermine |
| `dhps_mmb_search_to_collection($parsed, $service)` | AJAX-Search-Side-Channel | MMB-Search-AJAX |
| `dhps_tp_item_to_legacy_video($item)` | Template-Rebuild | TP/TPT/LP Templates |
| `dhps_mio_item_to_legacy_month($item)` | Template-Rebuild | MIO/Steuertermine Templates |

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
