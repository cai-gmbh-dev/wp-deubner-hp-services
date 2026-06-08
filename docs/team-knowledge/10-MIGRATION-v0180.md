# Migration v0.17.x -> v0.18.0

## Stand: 2026-06-08

## TL;DR

v0.18.0 entfernt den Legacy-`else`-Branch aus allen Templates. Pipeline ist
einzige Datenquelle. Templates lesen ausschliesslich aus
`$collection` (DHPS_Content_Collection-Instanz).

## Auswirkungen pro Konsumenten-Typ

### Anwender / Site-Owner

**Kein Handlungsbedarf.** Plugin-Update via WP-Update-Workflow installiert v0.18.0
und alle Shortcodes funktionieren weiter wie gewohnt. Die Render-HTML-Shapes
sind bytewise unveraendert (verifiziert per Stage-Smoke vor jedem v0.17.x-Release).

### Theme-Entwickler mit Template-Overrides

**Pruefen ob Theme-Overrides aktiv sind**:

```
{your-theme}/dhps/services/{service}/{layout}.php
{your-theme}/dhps/steuertermine/{layout}.php
```

Falls **JA**: Theme-Override-Templates auf neues Pattern migrieren:

#### Vorher (v0.17.x)

```php
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;
if ( $has_collection ) {
    // Pseudo-Rebuild aus Collection
    $categories = ...;
} else {
    // Legacy aus $data
    $categories = $data['categories'] ?? array();
}
```

#### Nachher (v0.18.0)

```php
$collection = dhps_collection_or_empty( $collection, 'mmb' );
$categories = dhps_mmb_collection_to_legacy_categories( $collection );
```

Helper-Funktionen sind in `includes/dhps-content-helpers.php` und
`includes/dhps-{service}-content-helpers.php` definiert.

### Plugin-Entwickler mit Adapter-Erweiterungen

**Pruefen ob eigener `dhps_content_adapter_for_service`-Filter aktiv ist**:

- Filter darf weiterhin Adapter zurueckgeben oder null
- **NEU**: Pipeline garantiert bei null-Filter-Return eine LEERE Collection
  (statt null). Templates sehen NIE null mehr - der `dhps_collection_or_empty`-
  Helper ist Belt-and-Braces Defense-in-Depth.

### Plugin-Entwickler mit Sub-Shortcode-Modules-Hooks

**`DHPS_MAES_Modules::get_collection( $section, $atts )`** ist erhalten geblieben.
Sub-Shortcode-Render-Handler (`render_videos`, `render_merkblaetter`,
`render_aktuelles`) bauen jetzt die Collection IMMER vor Render - Force-Legacy
ist obsolet. Filter-Atts (`einzelvideo`, `videoliste`) wirken VOR Adapter-Build,
nicht durch Force-Legacy.

**`DHPS_Steuertermine`**: gleiche Migration. `get_collection()` ist erhalten,
`render()` baut immer eine Collection aus gefilterten `$tax_dates`.

## Was bleibt unveraendert

- 9 Adapter-Klassen (mio/mmb/tp/tpt/maes/tc) unangetastet
- 9 Parser unangetastet
- 9 Service-Tags: mio/lxmio/tp/tpt/lp/mmb/mil/maes/tc
- HTML-Render-Output bytewise unveraendert (verifiziert per Stage-Smoke)
- AJAX-JSON-Responses bytewise unveraendert
- Shortcode-Atts unveraendert
- Theme-Override-Mechanismus unveraendert (nur Template-Body braucht ggf. Migration)
- `echo $tc_html` Trust-Decision (v0.13.0/v0.14.4) unangetastet

## Code-Reduktion

v0.18.0 entfernt **~340 LOC** aus Templates (Pseudo-Rebuild-Logik raus, Helper
fasst sie zusammen). Templates sind durchschnittlich um **15-50 Zeilen** kuerzer.

## Neue Helper-Funktionen

| Helper | Pfad | Verwendung |
|--------|------|------------|
| `dhps_collection_or_empty($col, $service)` | `dhps-content-helpers.php` | Pipeline-Garantie-Wrapper (3.B) |
| `dhps_mmb_collection_to_legacy_categories($col)` | `dhps-content-helpers.php` | 3 MMB-Templates |
| `dhps_tp_collection_to_legacy_categories($col)` | `dhps-tp-content-helpers.php` | 3 TP-Templates (default/card/compact) |

## Pipeline-Garantie

v0.18.0 patcht `DHPS_Content_Pipeline::render_service` so dass `$collection`
nach Adapter-Lookup NIE null ist:

- Adapter registriert + adapt() erfolgreich: Collection vom Adapter
- Adapter registriert + adapt() wirft: Pipeline-catch-Block + Fallback auf leere Collection
- Adapter nicht registriert: Pipeline baut leere Collection als Fallback

**Defense-in-Depth-Strategie 3.A + 3.B**:

- Pipeline-Patch (3.A) ist primary defense
- Helper `dhps_collection_or_empty` (3.B) ist Belt-and-Braces - falls Theme-
  Override direkt von alter API ausgeht, faengt der Helper das ab + loggt
  bei WP_DEBUG

## Rollback bei Problemen

Sollten Site-Probleme auftreten, kann auf v0.17.5 zurueckgewechselt werden:

```bash
wp plugin update wp-deubner-hp-services --version=0.17.5
```

Oder ueber den Beta-Channel-Switch im Admin-Dashboard (v0.16.0).

## Naechste Schritte

- **v0.18.1 (geplant)**: Datum-Normalisierung TP/TPT/MIO (TD-V0172-2 + TD-V0173-2)
- **v0.19.0 (geplant)**: `$data`-Parameter aus Template-Scope entfernen (heute noch
  als Param vorhanden fuer Theme-Override-BC, ab v0.19.0 nur `$collection`)
