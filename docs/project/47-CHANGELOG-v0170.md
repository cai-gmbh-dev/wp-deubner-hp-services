# Changelog v0.17.0 - Einheitliches Datenmodell (Foundation + MAES-Pilot)

## Stand: 2026-06-04

## Mission

User-Wunsch seit Anfang des Plugins: einheitliches Datenmodell statt service-
spezifischer Parser-Returns. v0.17.0 fuehrt die DTO-Foundation ein und
migriert MAES als Pilot auf das neue Modell - **ohne BC-Bruch**.

## Strategie: Option C - Adapter-Layer (inkrementell)

Aus 4 Migration-Optionen in der Discovery (A-D) wurde **Option C** gewaehlt:

- Parser bleiben **unveraendert** in v0.17.0
- Neuer Adapter-Layer wandelt Parser-Outputs in `DHPS_Content_Collection`
- Pipeline reicht **beides** weiter: Legacy `$data`-Array + neues `$collection`-Objekt
- Templates koennen schrittweise migrieren (1 Pilot in v0.17.0, weitere in v0.17.1+)
- Inkrementell, BC-sicher, niedriges Risiko - bewaehrtes Pattern aus v0.14.0-v0.16.3

## Hauptaenderungen

### 1) DTO-Foundation (F1, 4 neue Klassen)

#### `DHPS_Content_Item`

`includes/class-dhps-content-item.php` (NEU)

- `final class`, alle Properties `public readonly` (PHP 8.1+)
- 13 Felder: id, service, type, title, body, excerpt, image, media, link, date, tags, category, meta
- Konstruktor validiert id non-empty, service in 13-er-Whitelist, type in
  5-er-Whitelist (news, video, document, tax_date, generic)
- Methoden:
  - `to_content_card_props(): array` - Mapping nach ContentCard-Component-Schema
  - `to_array(): array` - vollstaendiges assoz Array (Cache-Roundtrip-faehig)
  - `static from_array(array $data): self` - Re-Hydration mit Default-Fallbacks
- ALLOWED_SERVICES + ALLOWED_TYPES als `public const`

#### `DHPS_Content_Collection`

`includes/class-dhps-content-collection.php` (NEU)

- `implements IteratorAggregate, Countable`
- `public readonly string $service`, `public readonly array $meta`
- private `array $items`, Type-Check Items via `instanceof DHPS_Content_Item`
- Methoden: add (NEUE Instanz!), count, is_empty, get_items, first, filter,
  group_by('type'|'category'|'service'), get_meta, getIterator,
  to_content_card_items, to_array, from_array
- Immutability via spl_object_id-Garantie in Tests

#### `DHPS_Content_Adapter_Interface`

`includes/class-dhps-content-adapter-interface.php` (NEU)

- Eine Methode: `adapt(array $parser_output, string $service): DHPS_Content_Collection`

#### `DHPS_Content_Adapter_Registry`

`includes/class-dhps-content-adapter-registry.php` (NEU)

- Statisches Registry: `private static array $adapters = []`
- `register/for_service/has/clear/get_registered_services`
- Filter-Hook `dhps_content_adapter_for_service` fuer Theme/Plugin-Overrides

### 2) MAES-Pilot-Adapter (F2)

`includes/class-dhps-maes-adapter.php` (NEU)

- `final class DHPS_MAES_Adapter implements DHPS_Content_Adapter_Interface`
- Mappet 3 MAES-Item-Typen:
  - `videos[]` -> Items mit `type='video'`, media={kind:video, slug, poster}
  - `merkblaetter[]` -> Items mit `type='document'`, meta={pdf_params, doc_index}
  - `news[]` -> Items mit `type='news'`, body=`wp_kses_post($body_html)`
- Item-IDs: `maes-{type}-{idx}` Convention
- Collection-Meta: overview + total_videos + total_merkblaetter + total_news
- Defensive: kein Throw bei missing keys, leere Listen wenn Parser nichts liefert

### 3) Adapter-Registrierung (Bootstrap)

`Deubner_HP_Services.php` (`dhps_init`):

```php
DHPS_Content_Adapter_Registry::register( 'maes', new DHPS_MAES_Adapter() );
```

Eingehakt nach Parser-Registry, vor Service-Bootstrap.

### 4) Pipeline-Patch (Lead-Direct)

`includes/class-dhps-content-pipeline.php` (`render_service`):

- Nach Parser, vor Adapter: **Filter `dhps_pipeline_data_{tag}` wird hier aufgerufen** (QA-Major-2 Fix). Bisher war der Filter im Renderer, was eine Daten-Drift zwischen `$data` (gefiltert) und `$collection` (ungefiltert) erzeugt haette
- Nach Filter: `DHPS_Content_Adapter_Registry::for_service($tag)` -> Adapter sieht gefilterte Daten
- Wenn Adapter da: `$collection = $adapter->adapt($parsed_data, $tag)`
- Wenn nicht: `$collection = null`
- Defensiv mit `class_exists`-Guard auf Registry
- **Fail-Soft (SEC-MEDIUM-1)**: `try/catch (\Throwable)` um `$adapter->adapt()` -> bei Exception `$collection = null` + `error_log`, Templates fallen auf Legacy-Pfad zurueck
- Renderer-Aufruf bekommt zusaetzlich `$collection`

`includes/class-dhps-content-adapter-registry.php`:

- **SEC-MEDIUM-2 Fix**: Filter-Return-Type-Check via `instanceof` + `_doing_it_wrong` Diagnose-Log wenn ein Filter etwas anderes als null oder Interface-Instanz liefert

`includes/class-dhps-renderer.php` (`render_parsed`):

- Neuer 5. Parameter `?DHPS_Content_Collection $collection = null`
- Template-Scope hat zusaetzlich `$collection` verfuegbar
- Doc-Block mit `@since 0.17.0`-Annotation
- BC: alle bestehenden Aufrufe ohne 5. Parameter weiter funktional

### 5) Template-Migration: 3 MAES-Templates

`public/views/services/maes/videos.php`, `merkblaetter.php`, `aktuelles.php`:

BC-Pattern:

```php
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
    // neue Collection-basierte Render-Logik
    $items_props = $collection->filter( fn( $item ) => 'video' === $item->type )
                              ->to_content_card_items();
    echo dhps_component( 'content-list', array( 'items' => $items_props ) );
} else {
    // Legacy-Pfad: UNVERAENDERTER bisheriger Code als Fallback
}
```

Legacy-`else`-Branch sichert BC fuer:
- Sub-Shortcodes `[maes_videos]` etc. die ueber `DHPS_MAES_Modules::get_data()` direkt rendern (umgehen die Content-Pipeline)
- Hypothetische Plugin-Reihenfolge-Issues

### 6) PHP-Mindestversion 8.0 -> 8.1

`Requires PHP: 8.1` im Plugin-Header. Begruendung:

- `readonly` Properties sind PHP 8.1 Sprach-Feature
- PHP 8.0 EOL seit 2023-11 (>2.5 Jahre)
- Live-Sites laufen ohnehin auf PHP 8.3.30 (Plattform-Doku)
- Erlaeuternder Block im Plugin-Header verlinkt auf Discovery-Doc

## Backward Compatibility

**Vollstaendig BC**:

- Alle 9 Parser unveraendert (`includes/parsers/` 0 Aenderungen)
- Bestehende Templates ausser den 3 MAES-Pilot-Templates unveraendert
- 3 MAES-Templates haben Legacy-`else`-Branch identisch zur v0.16.3-Logik
- Sub-Shortcodes `[maes_videos]` etc. nutzen weiter den Legacy-Pfad
- Renderer-Signatur erweitert um optionalen Parameter (BC-konform)
- 80 dhps-CSS-Klassen auf Page 6 unveraendert vor/nach (Smoke verifiziert)

**Kein BC**:

- Plugin verweigert sich auf PHP 8.0 (`Requires PHP: 8.1` im Header)

## QA + Security

### QA-Smoke (Lead durchgefuehrt)

- F1-Test (DTO-Foundation): **17/17 PASS** im Stage-Container
- F2-Test (MAES-Pilot): **T1-T8 PASS** (Code-Path), T9+T10 sind Container-Internal-Netz-Quirk (vom Host curl 200 OK)
- Page 6 (9 Hauptservices): 80 dhps-Klassen unveraendert
- Page 7 (Sub-Shortcodes mit MAES): 28 dhps-Klassen + dhps-content-card__* Klassen rendern korrekt
- Pipeline-Patch BC: 80 dhps-Klassen vor und nach Patch identisch
- debug.log clean (keine v0.17.0-Errors)

Specialist-QA + SEC laufen in P4 parallel.

## 9 Neue Trust-Decisions T18-T26 (kumulativ 26)

| # | Decision | Begruendung |
|---|----------|-------------|
| T18 | Adapter-Layer Option C statt Big-Bang | BC-sicher, bewaehrtes Pattern aus 7 Releases |
| T19 | MAES als Pilot statt MIO | nutzt ContentCard schon, 3 Sub-Shortcodes als Stresstest |
| T20 | PHP 8.0 -> 8.1 Anhebung | readonly-Properties Pflicht-Feature, PHP 8.0 EOL >2.5J |
| T21 | Klassen-Naming mit Underscore | Autoloader-Konvention `DHPS_Content_Item` -> `class-dhps-content-item.php` |
| T22 | DTO immutable via readonly | keine Setter-Boilerplate, IDE-Friendly, klare Vertraege |
| T23 | Adapter im includes/-Root, nicht Subordner | Autoloader durchsucht nur includes/ + includes/parsers/ |
| T24 | wp_kses_post im Adapter, nicht im DTO | Adapter ist Erzeuger, DTO ist transport |
| T25 | from_array nutzt positional args | Defense gegen named-arg-Brueche bei spaeteren Param-Renames |
| T26 | Items ohne title werden geskippt, nicht throwen | Robustheit gegen unsaubere Parser-Outputs |

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/26-EINHEITLICHES-DATENMODELL-PLAN-v0170.md` | Discovery (Schema-Vertrag) |
| `docs/project/47-CHANGELOG-v0170.md` | (dieses Dokument) |
| `includes/class-dhps-content-item.php` | DTO (F1) |
| `includes/class-dhps-content-collection.php` | Collection (F1) |
| `includes/class-dhps-content-adapter-interface.php` | Adapter-Interface (F1) |
| `includes/class-dhps-content-adapter-registry.php` | Adapter-Registry (F1) |
| `includes/class-dhps-maes-adapter.php` | MAES-Adapter (F2) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.16.3 -> 0.17.0, `Requires PHP` 8.0 -> 8.1, Erlaeuterungs-Block, Adapter-Registrierung in `dhps_init` |
| `README.md` | Version-Bump |
| `includes/class-dhps-content-pipeline.php` | Adapter-Aufruf nach Parser, `$collection`-Injection in Renderer-Call |
| `includes/class-dhps-renderer.php` | `render_parsed` neuer 5. Parameter `?DHPS_Content_Collection $collection` |
| `public/views/services/maes/videos.php` | BC-Pattern mit `$has_collection`-Check + Legacy-else |
| `public/views/services/maes/merkblaetter.php` | BC-Pattern |
| `public/views/services/maes/aktuelles.php` | BC-Pattern |
| `MEMORY.md` | MILESTONE 18 + 8 v0.17.0 Implementation-Notes |

## Specialist-Team-Pattern (Iteration 15)

| Phase | Specialists |
|-------|-------------|
| P1 Discovery | 1 (Datenmodell-Analyse, 4 Strategie-Optionen, Schema-Vertrag, Spec-Aufteilung) |
| P2 Implementation | F1 DTO-Foundation **parallel** zu Lead-Direct Pipeline-Patch + Renderer-Signatur; danach F2 MAES-Pilot sequenziell (braucht F1's Klassen) |
| P3 Composition | Lead (Version-Bump, MEMORY, CHANGELOG, Doku-Updates) |
| P4 QA + SEC | 2 parallel |
| P5 Release | Pre-Release rc.1 -> Stage-Test -> Promote zu Stable |

**Lehre v0.17.0**:

1. **Autoloader-Konvention vor Spec briefen**: F1 musste die Discovery-Doc-Klassennamen `DHPS_ContentItem` zu `DHPS_Content_Item` korrigieren weil der Autoloader nur per-Underscore zerlegt. Naechste Discovery sollte den Autoloader-Mechanismus explizit in der Klassenname-Sektion erwaehnen.
2. **Container-Internal-Netz-Quirk dokumentieren**: F2-Test T9+T10 schlugen fehl wegen wp_remote_get aus dem Container - vom Host aus curl 200 OK. Pattern aus v0.16.1 nochmal bestaetigt. Test-Skripte sollten Frontend-Smoke nicht via wp_remote_get loesen sondern via dem expliziten Hinweis "Lead pruefen mit Host-curl".
3. **Adapter-Pattern skaliert**: F1+F2 zusammen ~600 LOC, Pipeline-Patch ~15 LOC, 0 BC-Bruch. v0.17.1+ kann weitere Service-Adapter parallel hinzufuegen ohne sich gegenseitig zu blockieren.

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.17.1** | MMB-Adapter + Templates (mittlere Komplexitaet, Categories+Search) |
| **v0.17.2** | TP/TPT/LP-Adapter (Video-Items mit Featured+Categories) |
| **v0.17.3** | MIO-Adapter (komplexester - Tax-Dates Sondertyp) |
| **v0.17.4** | TC + MAES-Sub-Shortcodes-Bridge (Cleanup) |
| **v0.18.0** | Legacy-Pfad in Templates entfernen (nach allen Service-Migrationen) |

## Bilanz v0.17.0

- **DTO-Foundation produktiv** (4 Klassen, 17/17 Tests)
- **MAES-Pilot** migriert (Adapter + 3 Templates mit BC)
- **0 Critical-Drift** (8x Schema-Vertrag bestaetigt: v0.15.3, v0.15.4, v0.15.5, v0.16.0, v0.16.1, v0.16.2, v0.16.3, v0.17.0)
- **9 neue Trust-Decisions** T18-T26
- **0 BC-Bruch** (Pipeline-Smoke 80 dhps-Klassen unveraendert)
- **PHP 8.0 -> 8.1** Anhebung dokumentiert
- **Lang gehegter User-Wunsch erfuellt**: einheitliches Datenmodell ist da, weitere Services in v0.17.1+ inkrementell
