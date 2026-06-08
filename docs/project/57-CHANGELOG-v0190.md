# Changelog v0.19.0 - MAJOR $data raus (Deprecated-Data-Proxy)

## Stand: 2026-06-08

## Mission

**Letzte strukturelle Aenderung im DTO-Foundation-Cycle** (v0.17.0 ->
v0.18.x -> v0.19.0). `$data`-Param wird im Template-Scope durch einen
Deprecated-Data-Proxy ersetzt. Theme-Overrides die noch `$data['...']` lesen,
**funktionieren weiter** (BC), bekommen aber WP_DEBUG-Notice mit
Migrations-Hinweis.

Zusaetzlich: `$service_tag` ist **neu direkt im Template-Scope** verfuegbar.

## Strategie: Option B (Deprecated-Data-Proxy)

Discovery 37 hat 3 Optionen verglichen:

| Option | Aufwand | BC-Risiko | Verdikt |
|--------|---------|-----------|---------|
| A (Hard-Bruch) | S | HOCH (Theme-Overrides crashen sofort) | Verworfen |
| **B (Deprecated-Proxy)** | **M** | **NIEDRIG (Notice + funktionierender Code)** | **EMPFOHLEN** |
| C (Filter-Opt-In) | M | NIEDRIG (aber Tech-Debt unbegrenzt) | Verworfen |

## Hauptaenderungen

### Neue Klasse DHPS_Deprecated_Data_Proxy

`includes/class-dhps-deprecated-data-proxy.php` (~210 LOC):

- `final` mit ArrayAccess + Countable + IteratorAggregate
- `isset($data)` + `is_object($data)` bleiben Notice-frei
- Notice nur bei `offsetGet/offsetExists/count/getIterator`-Calls
- Read-Only: offsetSet/offsetUnset triggern Notice ohne Mutation
- **Notice-Once-Pro-Key** via private static `$notified_keys`-Set
  (verhindert Log-Lawine bei foreach-Iteration)
- WP_DEBUG-gated `error_log` mit Migrations-Hinweis
- `_doing_it_wrong`-Notice (Admin-Bar-sichtbar bei WP_DEBUG)

### Renderer-Patch

`includes/class-dhps-renderer.php` `render_parsed`:

```php
// v0.19.0 MAJOR: $service_tag direkt im Template-Scope.
$service_tag = $tag;

// v0.19.0 MAJOR: $data wird Deprecated-Data-Proxy.
if ( class_exists( 'DHPS_Deprecated_Data_Proxy' ) ) {
    $data = new DHPS_Deprecated_Data_Proxy( $data, $tag, $layout );
}
```

10 Templates lasen bisher `$data['service_tag']` - mit `$service_tag` direkt
im Scope **0 Template-Touches** noetig.

### Sub-Shortcode-Module-Patches

`includes/class-dhps-maes-modules.php`:

- 3 render_*-Methoden (render_videos/render_merkblaetter/render_aktuelles)
- Analog Renderer: `$service_tag = 'maes'` + `$data` als Proxy

`includes/class-dhps-steuertermine.php`:

- Nur `$service_tag = 'mio'` (KEIN Proxy)
- Begruendung: Steuertermine-Templates iterieren `$data` direkt als
  Tax-Dates-Array (`foreach ($data as $month)`). Bei leerem
  Pseudo-Rebuild bleibt `$data` der Proxy -> Notice-Lawine. Pragmatischer
  Trade-Off: Steuertermine bleibt mit echtem Array.

### MIGRATION.md

`docs/team-knowledge/11-MIGRATION-v0190.md`:

- 10-Eintraege-Mapping-Tabelle (`$data['xxx']` -> Helper / Collection / $service_tag)
- WP_DEBUG-Notice-Beispiele
- Rollback-Hinweis
- Verbleibender Tech-Debt fuer v0.19.1

## Tests

`test-v0190-proxy.php` (Lead-Smoke):

- T1-T2 Klassen-Identitaet + Interface-Implementations + final
- T3 isset+is_object+instanceof Notice-frei
- T4-T5 ArrayAccess (offsetGet + offsetExists + null bei missing)
- T6 Countable
- T7 IteratorAggregate (foreach iteriert)
- T8 Read-Only-Defense (offsetSet schlaegt nicht durch)

**Resultat: 16 PASS / 0 FAIL**

## Stage-Smoke BC

| Page | dhps-Klassen vor v0.19.0 | nach v0.19.0 | Status |
|------|--------------------------|--------------|--------|
| Page 6 | 76 | 76 | **PASS** |
| Page 7 | 8 | 8 | **PASS** |
| Page 8 | 91 | 91 | **PASS** |
| debug.log | clean | **Deprecated-Notices feuern korrekt** (Test-Reads) | PASS |

## Backward Compatibility

**Vollstaendig BC fuer Site-Owner (HTML-Render)**:

- HTML-Render-Output bytewise unveraendert
- 9 Adapter-Klassen unangetastet
- 9 Parser unangetastet
- 22 Templates unangetastet
- Pipeline-Schema unveraendert
- AJAX-JSON-Responses unangetastet
- Shortcode-Atts unveraendert
- Theme-Override-Mechanismus unveraendert

**Bewusster BC-Bruch fuer Theme-Overrides die `$data['...']` lesen** (per
Discovery genehmigt fuer MAJOR-Bump):

- Lese-Zugriff funktioniert weiter (Proxy liefert Wert)
- WP_DEBUG-Notice mit Migrations-Hinweis
- 1 Release Migrations-Fenster (v0.19.1 hartes Aus)

## Neue Template-Scope-API

| Variable | Vor v0.19.0 | Ab v0.19.0 |
|----------|-------------|------------|
| `$collection` | da | **da** (Pflicht) |
| `$service_class` | da | **da** |
| `$layout_class` | da | **da** |
| `$custom_class` | da | **da** |
| `$service_tag` | nicht direkt | **NEU**: direkt im Scope |
| `$data` | Array | **DHPS_Deprecated_Data_Proxy** (Theme-Override-BC) |

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/37-DATA-CLEANUP-PLAN-v0190.md` | Discovery (Option B) |
| `docs/team-knowledge/11-MIGRATION-v0190.md` | Migrations-Guide |
| `docs/project/57-CHANGELOG-v0190.md` | (dieses Dokument) |
| `includes/class-dhps-deprecated-data-proxy.php` | Proxy-Klasse |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.18.3 -> 0.19.0 |
| `README.md` | Version-Bump |
| `includes/class-dhps-renderer.php` | $service_tag im Scope + $data als Proxy |
| `includes/class-dhps-maes-modules.php` | analog Renderer fuer 3 Sub-Shortcodes |
| `includes/class-dhps-steuertermine.php` | $service_tag im Scope (kein Proxy - siehe Discovery) |
| `MEMORY.md` | MILESTONE 28 + 7 v0.19.0 Implementation-Notes |

## Was bewusst NICHT in v0.19.0

- **TD-Phase-B**: `Collection::sort_by_date_iso()` -> verschoben auf v0.19.1
  (Mix-Risiko: BC-Bruch + Feature-Add im selben Release verdoppelt
  Test-Surface)
- **Hard-Aus $data**: Proxy bleibt - v0.19.1 entfernt komplett
- **22 Plugin-Template-Touches**: 0 (alle bereits seit v0.18.0
  auf Collection-Reads umgestellt)

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.19.1** | Hard-Aus `$data`-Proxy (kompletter Removal) + `Collection::sort_by_date_iso()` + `Collection::sorted_by()` Hook |
| **v0.20.0** | Component-System v2 / Render-Layer-Refactor |

## Bilanz v0.19.0

- **1 neue Klasse** `DHPS_Deprecated_Data_Proxy` (~210 LOC)
- **3 Module-Patches** (Renderer + MAES_Modules + Steuertermine)
- **Lead-Tests 16/16 PASS**
- **Stage-Smoke 76/76 + 8/8 + 91/91 dhps-Klassen** bytewise stabil
- **Deprecated-Notices funktionieren** (debug.log-verifiziert)
- **0 Plugin-Template-Touches** (alle 22 unangetastet)
- **0 BC-Bruch im Render-Output**
- **0 Adapter/Parser/Pipeline-Aenderung**
- Schema-Vertrag-Vorgehen **19x in Folge** ohne Critical-Drift
- **MAJOR-Version markiert Ende der DTO-Foundation-Aera**
