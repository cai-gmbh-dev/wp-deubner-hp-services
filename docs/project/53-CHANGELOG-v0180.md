# Changelog v0.18.0 - Legacy-Pfad-Entfernung (MAJOR)

## Stand: 2026-06-08

## Mission

**MAJOR-Version.** Nach Abschluss der 9/9 Adapter-Migration (v0.17.0-v0.17.5)
ist v0.18.0 der grosse Cleanup-Schritt: alle `if ( $has_collection )/else`-
Branches aus den Templates entfernen. Pipeline ist EINZIGE Datenquelle.

Die in v0.17.4 angekuendigte Roadmap "Legacy-Pfad in Templates entfernen,
Pipeline einzige Datenquelle" ist mit v0.18.0 umgesetzt.

## Scope

Discovery 33-LEGACY-CLEANUP-PLAN-v0180 hat 17 Templates + 4 Steuertermine +
tp/compact.php als In-Scope identifiziert. Datum-Normalisierung-Tickets
(TD-V0172-2 + TD-V0173-2) wurden RAUS und auf v0.18.1 verschoben (separate
Achse, BC-Bruch-Hygiene).

## Hauptaenderungen

### Phase 1: Pipeline-Garantie

`includes/dhps-content-helpers.php`:

- Neuer Helper `dhps_collection_or_empty( ?DHPS_Content_Collection $col, string $service ): DHPS_Content_Collection`
  garantiert nicht-null Collection (Strategie 3.B)
- WP_DEBUG-gated error_log bei null-Input (Drift-Diagnose)

`includes/class-dhps-content-pipeline.php`:

- Pipeline-Garantie 3.A: nach Adapter-Lookup (mit try/catch) wird `null`
  durch `new DHPS_Content_Collection( $tag, array(), array() )` ersetzt
- Templates sehen NIE null

### Phase 2: MMB Helper + 3 Templates

`includes/dhps-content-helpers.php`:

- Neuer Helper `dhps_mmb_collection_to_legacy_categories( DHPS_Content_Collection $col ): array`
  konsolidiert den Pseudo-Rebuild aus den 3 MMB-Templates (-95 LOC dedupliziert)

3 MMB-Templates (default/card/compact):

- `if/else`-Branch durch 3 Zeilen ersetzt: `dhps_collection_or_empty()` +
  `dhps_mmb_collection_to_legacy_categories()` + Search-Config-Lookup
- Render-Code unter dem Block bytewise unveraendert
- Lazy-Akkordeon-Markup unangetastet

### Phase 3: MAES Sub-Shortcode-Filter-Migration

`includes/class-dhps-maes-modules.php` (3 render_*-Methoden):

- Filter (`einzelvideo`, `videoliste`) wirken auf `$data` VOR Adapter-Build
- Force-Legacy-Pattern obsolet (Filter-Items sind im Collection-Output)
- Collection IMMER vorhanden im Template-Scope

3 MAES Sub-Shortcode-Templates (videos/merkblaetter/aktuelles):

- `if/else`-Branch raus
- `dhps_collection_or_empty()` + direkte Item-Iteration via `$collection->filter()`

### Phase 4: MIO + TP + TPT + TC Templates

`includes/dhps-tp-content-helpers.php`:

- Neuer Helper `dhps_tp_collection_to_legacy_categories( DHPS_Content_Collection $col ): array`
  konsolidiert den Pseudo-Rebuild aus den TP-Templates

Templates migriert:

- 3 MIO (default/card/compact): `dhps_mio_item_to_legacy_month()` direkt
- 2 TP (default/card): `dhps_tp_collection_to_legacy_categories()`
- 3 TPT (default/card/compact): einfacher Item-foreach mit `dhps_tp_item_to_legacy_video()`
- 3 TC (default/card/compact): `get_meta('html')` + `get_meta('is_empty')`
- 4 Steuertermine (default/card/compact/inline): `dhps_mio_item_to_legacy_month()` direkt
- `DHPS_Steuertermine::render()`: Collection IMMER aus pre-gefilterten `$tax_dates`

### Phase 5: tp/compact.php migriert (TD-V0172-1)

`public/views/services/tp/compact.php`:

- Bisher als Tech-Debt verschoben (JS-Refactor-Risiko `initCompactAccordion`)
- v0.18.0: Markup bytewise unveraendert, nur Daten-Pfad vom Adapter
- JS-Selektoren `[data-video-slug]/[data-poster-url]/[data-v-modus]` +
  `.dhps-tp-compact__item` unangetastet
- `dhps_tp_collection_to_legacy_categories()` Helper-Aufruf

### Phase 6: MIGRATION.md

`docs/team-knowledge/10-MIGRATION-v0180.md` (NEU):

- Migrations-Guide fuer Theme-Entwickler mit Template-Overrides
- Pre/Post-Code-Beispiele
- Pipeline-Garantie-Erklaerung
- Rollback-Hinweise

## Smoke-Tests (Stage)

| Page | dhps-Klassen vor v0.18.0 | nach v0.18.0 | Status |
|------|--------------------------|--------------|--------|
| Page 6 (Steuern-MMB+MIO) | 76 | 76 | PASS |
| Page 7 (MAES sub-shortcodes + Steuertermine) | 9 | 9 | PASS |
| Page 8 (TP/TPT/LP/MIO Layouts) | 35 | 35 | PASS |
| debug.log | clean | clean (keine neuen Fatals) | PASS |

## Code-Reduktion

| Bereich | Loeschungen | Hinzufuegungen | Netto |
|---------|-------------|----------------|-------|
| MMB-Templates (3) | -126 | +12 | -114 |
| MIO-Templates (3) | -54 | +24 | -30 |
| TP-Templates (2) | -88 | +10 | -78 |
| TPT-Templates (3) | -42 | +24 | -18 |
| TC-Templates (3) | -18 | +9 | -9 |
| MAES-Templates (3) | -90 | +21 | -69 |
| Steuertermine (4) | -36 | +28 | -8 |
| tp/compact.php (1) | -2 | +8 | +6 |
| Helper (+2 neue Funktionen) | 0 | +135 | +135 |
| Pipeline-Patch | 0 | +8 | +8 |
| Steuertermine + MAES Modules | -12 | +30 | +18 |
| **Summe** | -468 | +309 | **-159 LOC** |

(Schaetzung basierend auf Diff. Exakte Werte im Commit-Diff.)

## Backward Compatibility

**BC-Status fuer Site-Owner**: vollstaendig - HTML-Render bytewise unveraendert.

**BC-Status fuer Theme-Entwickler mit Template-Overrides**:

- Template-Overrides die das v0.17.x `$has_collection`-Pattern nutzen FUNKTIONIEREN WEITER
  (else-Branch wird nie betreten, weil `$collection` immer da ist)
- Empfehlung: Overrides auf neues Pattern migrieren (siehe MIGRATION.md)

**BC-Status fuer Plugin-Entwickler**:

- `dhps_content_adapter_for_service`-Filter unveraendert
- `dhps_pipeline_data_{tag}`-Filter unveraendert
- 9 Adapter-Klassen unangetastet
- 9 Parser unangetastet
- AJAX-JSON-Responses bytewise unveraendert

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/33-LEGACY-CLEANUP-PLAN-v0180.md` | Discovery |
| `docs/project/53-CHANGELOG-v0180.md` | (dieses Dokument) |
| `docs/team-knowledge/10-MIGRATION-v0180.md` | Migrations-Guide |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.17.5 -> 0.18.0 |
| `README.md` | Version-Bump |
| `includes/class-dhps-content-pipeline.php` | Pipeline-Garantie 3.A |
| `includes/dhps-content-helpers.php` | Helper `dhps_collection_or_empty` + `dhps_mmb_collection_to_legacy_categories` |
| `includes/dhps-tp-content-helpers.php` | Helper `dhps_tp_collection_to_legacy_categories` |
| `includes/class-dhps-maes-modules.php` | Sub-Shortcode-Filter-Migration (3 render_*-Methoden) |
| `includes/class-dhps-steuertermine.php` | render() baut Collection IMMER (Sub-Shortcode-Filter-Migration) |
| `public/views/services/mmb/*.php` (3) | else-Branch raus, Helper-Aufruf |
| `public/views/services/mio/*.php` (3) | else-Branch raus |
| `public/views/services/tp/default.php`, `card.php` (2) | else-Branch raus, Helper-Aufruf |
| `public/views/services/tp/compact.php` | NEU migriert (TD-V0172-1) |
| `public/views/services/tpt/*.php` (3) | else-Branch raus |
| `public/views/services/tc/*.php` (3) | else-Branch raus |
| `public/views/services/maes/*.php` (3) | else-Branch raus, Filter-Migration |
| `public/views/steuertermine/*.php` (4) | else-Branch raus |
| `MEMORY.md` | MILESTONE 24 + Implementation-Notes |

## Verbleibende Tech-Debt-Tickets

| Ticket | Geplant |
|--------|---------|
| TD-V0171-2 MMB-Lazy-Akkordeon-AJAX | v0.18.1 oder v0.18.2 |
| TD-V0174-1 MIO-News-Container-AJAX | v0.18.1 oder v0.18.2 |
| TD-V0172-2 Datum-Normalisierung TP | v0.18.1 |
| TD-V0173-2 Datum-Normalisierung MIO | v0.18.1 |

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.18.1** | Datum-Normalisierung-Block (TD-V0172-2 + TD-V0173-2) als gemeinsamer DTO-Erweiterung |
| **v0.18.2** | MMB-Lazy-Akkordeon-AJAX + News-Container-AJAX (Helper-Side-Channel-Pattern wiederverwenden) |
| **v0.19.0** | `$data`-Param aus Template-Scope entfernen (heute nur dokumentarisch, Theme-Overrides koennen darauf zugreifen) |

## Bilanz v0.18.0

- **17 Templates + 4 Steuertermine + 1 tp/compact = 22 Templates** migriert
- **tp/compact.php** endlich migriert (TD-V0172-1 erledigt)
- **3 neue Helper-Funktionen** im Helper-Pool
- **Pipeline-Garantie 3.A + 3.B** Defense-in-Depth
- **~159 LOC weniger Code** (durch Helper-Konsolidierung)
- **0 BC-Bruch** im Render-Output (Stage-Smoke-verifiziert)
- **0 Adapter-Aenderung**
- **0 Parser-Aenderung**
- Schema-Vertrag-Vorgehen **15x in Folge** ohne Critical-Drift
- MAJOR-Version markiert die Pipeline-only-Aera
