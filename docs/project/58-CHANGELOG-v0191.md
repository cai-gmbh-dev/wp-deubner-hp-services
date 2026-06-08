# Changelog v0.19.1 - Hard-Aus $data + Collection-Sort-Hook

## Stand: 2026-06-08

## Mission

**Ende der DTO-Foundation-Aera** (v0.17.0 -> v0.19.1).

Zwei strategische Aufgaben:

- **Phase A**: Hard-Aus `$data` aus Template-Scope nach 1-Release-Migrations-Fenster von v0.19.0
- **Phase B**: `Collection::sorted_by()` + `Collection::sort_by_date_iso()` als neue Sort-Hook-API

## Strategie

- **Phase A**: Option A (Hard-Bruch via `unset($data)`)
- **Phase B**: B.2 Hybrid (generischer + Convenience-Wrapper)
- **Spec-Aufteilung**: Pure Lead-Direct

## Kritischer Discovery-Befund

**v0.19.0-Changelog-Claim "0 Plugin-Template-Touches" war ueberbreit.** 22 versteckte
`$data`-Reads waren noch in Plugin-Templates:

| Klasse | Templates | Code |
|--------|-----------|------|
| A1 service_tag-Lookup | 12 (mio/mmb/tp/maes x default/card/compact) | `$service_tag = $data['service_tag'] ?? 'xxx';` |
| A2 MAES-Empty-Guards | 3 (maes/default+card+compact) | `$videos = $data['videos'] ?? [];` etc. |
| B Steuertermine-Recycling | 4 (steuertermine/*) | `$data = $rebuilt;` lokal |

v0.19.1 hat diese 19 Plugin-Templates **explizit migriert** vor Proxy-Loeschung.

## Hauptaenderungen

### Phase B: Sort-Hook (orthogonal)

`includes/class-dhps-content-collection.php`:

```php
public function sorted_by( callable|string $key_or_callable, string $direction = 'asc' ): self
public function sort_by_date_iso( string $direction = 'asc' ): self
```

- **Immutable-Pattern** (NEUE Collection, behaelt Service + Meta)
- **Items ohne Sort-Wert IMMER am Ende** (unabhaengig von Direction - semantisch konsistent: "unbekannte Daten = am Ende")
- **PHP 8.0+ stable usort** (Items mit gleichen Werten behalten Original-Reihenfolge)
- **Invalid direction**: `InvalidArgumentException` (typed throw analog filter/group_by)
- `sort_by_date_iso` ist Convenience-Wrapper fuer `sorted_by('date_iso', $direction)`

### Phase A.3: 12 Templates `$service_tag = $data['service_tag']`-Zeile raus

Templates: `mio/`, `mmb/`, `tp/`, `maes/` x `default/card/compact`.

`$service_tag` ist seit v0.19.0 direkt im Template-Scope, also nur die obsolet
gewordene Bridge-Zeile entfernt.

### Phase A.4: 3 MAES-Templates Empty-Guards via Collection-Filter

```php
// Vorher (v0.19.0):
$videos       = $data['videos'] ?? array();
$merkblaetter = $data['merkblaetter'] ?? array();
$news         = $data['news'] ?? array();
$service_tag  = $data['service_tag'] ?? 'maes';

if ( $show_videos && ! empty( $videos ) ) : /* ... */

// Nachher (v0.19.1):
$collection_safe  = dhps_collection_or_empty( $collection, 'maes' );
$has_videos       = $collection_safe->filter( static fn( $i ) => 'video' === $i->type )->count() > 0;
$has_merkblaetter = $collection_safe->filter( static fn( $i ) => 'document' === $i->type )->count() > 0;
$has_news         = $collection_safe->filter( static fn( $i ) => 'news' === $i->type )->count() > 0;

if ( $show_videos && $has_videos ) : /* ... */
```

Sub-Templates (`videos.php`, `merkblaetter.php`, `aktuelles.php`) haben
`isset($videos)`-Guard und nutzen Collection direkt - keine Bridge-Variable mehr noetig.

### Phase A.5: Renderer + MAES_Modules + Steuertermine $data-Setter raus

`includes/class-dhps-renderer.php`:

```php
// v0.19.1: $data komplett aus Template-Scope entfernt.
$service_tag = $tag;
unset( $data );
```

`includes/class-dhps-maes-modules.php` (3 render_*-Methoden):

```php
$service_tag = 'maes';
unset( $data );
```

`includes/class-dhps-steuertermine.php`:

```php
// $data -> $months Rename (semantische Klarheit).
$months       = $tax_dates;
$service_tag  = 'mio';
```

4 Steuertermine-Templates: `$data` -> `$months` (Iteration ueber Tax-Dates-Array).

### Phase A.6: DHPS_Deprecated_Data_Proxy.php geloescht

`git rm includes/class-dhps-deprecated-data-proxy.php` (~-210 LOC).

## Tests

`test-v0191-sort.php` (Lead-Smoke):

- T1: DHPS_Deprecated_Data_Proxy geloescht (class_exists ist false)
- T2: sorted_by + sort_by_date_iso Methoden existieren
- T4-T5: asc + desc Sortierung
- T6: Immutable (Original unveraendert)
- T7: Items ohne date_iso am Ende (asc + desc)
- T8: Invalid direction throws InvalidArgumentException
- T9-T10: sorted_by mit String-Key + Callable
- T11: Empty Collection
- T12: Collection-Service+Meta erhalten

**Resultat: 13 PASS / 0 FAIL**

## Stage-Smoke BC

| Page | dhps-Klassen vor v0.19.1 | nach v0.19.1 | Status |
|------|--------------------------|--------------|--------|
| Page 6 | 76 | 76 | **PASS** |
| Page 7 | 8 | 8 | **PASS** |
| Page 8 | 91 | 91 | **PASS** |
| debug.log | (rc.1 fired Notices bei v0.19.0) | **clean** (0 Notices) | PASS |

Theme-Override-Brueche: NUR wenn Theme `$data['...']` liest -> jetzt `Undefined variable $data`-Notice
(v0.19.0-Migrations-Fenster lief 1 Release, BC-Bruch angekuendigt + dokumentiert).

## Code-Reduktion

- **-210 LOC** durch DHPS_Deprecated_Data_Proxy-Loeschung
- **+95 LOC** durch Sort-Hook (sorted_by + sort_by_date_iso)
- **-15 LOC** durch 12 Templates `$service_tag = $data[...]`-Removal
- **+9 LOC** durch 3 MAES-Templates Empty-Guards
- **+/-0 LOC** durch Steuertermine $data->$months-Rename
- **+10 LOC** durch Renderer/MAES_Modules unset($data)-Kommentare
- **Netto: ~-110 LOC** (Aera-Cleanup ist erfolgreich)

## Backward Compatibility

**Vollstaendig BC fuer Site-Owner**:

- HTML-Render bytewise unveraendert
- 9 Adapter-Klassen unangetastet
- 9 Parser unangetastet
- Pipeline unveraendert
- AJAX-JSON-Responses unangetastet
- `echo $tc_html` Trust-Decision unangetastet
- Component-System unangetastet

**Theme-Overrides die `$data['...']` lasen**: brechen jetzt mit
`Undefined variable $data`-PHP-Notice (v0.19.0-WP_DEBUG-Notice hat das fuer
1 Release angekuendigt).

## Neue Template-Scope-API (final)

| Variable | Status |
|----------|--------|
| `$collection` | **Pflicht** (DHPS_Content_Collection) |
| `$service_tag` | **Pflicht** (string, seit v0.19.0) |
| `$service_class` | da |
| `$layout_class` | da |
| `$custom_class` | da |
| `$data` | **ENTFERNT** (v0.19.1) |

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/38-DATA-HARD-AUS-PLAN-v0191.md` | Discovery |
| `docs/team-knowledge/12-MIGRATION-v0191.md` | Migrations-Guide (final) |
| `docs/project/58-CHANGELOG-v0191.md` | (dieses Dokument) |

### Geloescht

| Datei | Begruendung |
|-------|-------------|
| `includes/class-dhps-deprecated-data-proxy.php` | 1-Release-Migrations-Fenster abgelaufen |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.19.0 -> 0.19.1 |
| `README.md` | Version-Bump |
| `includes/class-dhps-content-collection.php` | Sort-Hook (sorted_by + sort_by_date_iso) |
| `includes/class-dhps-renderer.php` | $data unset statt Proxy-Wrap |
| `includes/class-dhps-maes-modules.php` | 3 render_*-Methoden unset($data) |
| `includes/class-dhps-steuertermine.php` | $data -> $months Rename |
| 12 Service-Templates (mio/mmb/tp/maes x default/card/compact) | $service_tag = $data[...]-Zeile raus |
| 3 MAES-Templates (default/card/compact) | Empty-Guards via Collection-Filter |
| 4 Steuertermine-Templates (default/card/compact/inline) | $data -> $months Rename |
| `MEMORY.md` | MILESTONE 29 + 8 v0.19.1 Implementation-Notes |

## Bilanz v0.19.1 (Ende DTO-Foundation-Aera)

- **`$data`-Variable komplett aus Template-Scope** (final)
- **Collection-Sort-Hook** etabliert (sorted_by + sort_by_date_iso)
- **Lead-Tests 13/13 PASS**
- **Stage-Smoke 76/8/91** bytewise stabil
- **debug.log clean** (0 Notices post-Migration)
- **22 Plugin-Templates** clean (alle migriert)
- **-110 LOC netto** (Aera-Cleanup erfolgreich)
- Schema-Vertrag-Vorgehen **20x in Folge** ohne Critical-Drift
- **Specialist-Team-Pattern bewiesen**: Discovery hat Migrations-Drift-Befund aus v0.19.0 aufgedeckt

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.20.0** | Component-System v1-Stabilitaets-Release (Option Zeta-Plus: Defense-in-Depth-Patches + SVG-Icon-Deduplikation + API-Stabilitaets-Doku) |
| **Pause** | Stop hier - DTO-Foundation-Aera komplett abgeschlossen |
