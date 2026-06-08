# Changelog v0.18.1 - Datum-Normalisierung

## Stand: 2026-06-08

## Mission

Zwei Tech-Debt-Tickets in einem Block:

- **TD-V0172-2**: TP/TPT/LP Datum MM/YY -> ISO YYYY-MM
- **TD-V0173-2**: MIO Monat-Slug -> ISO YYYY-MM

## Strategie: Option C (Beimaterial-Helper)

Discovery 34-DATUM-NORMALISIERUNG-PLAN-v0181 hat **3 Optionen** verglichen:

| Option | Aufwand | BC-Risiko | Semantik | Verdikt |
|--------|---------|-----------|----------|---------|
| A (DTI + Tag 01) | M | NIEDRIG-MITTEL | FALSCH ("01.10.2024" suggeriert Tag) | VERWORFEN |
| B (meta.date_partial neuer Vertrag) | M | NIEDRIG | KORREKT | Overkill |
| **C (meta.date_iso Beimaterial)** | **S** | **0** | KORREKT | **EMPFOHLEN** |

Use-Case-Analyse hat ergeben: **0 Code-Pfade sortieren heute nach Datum**. Sortier-
Bedarf ist HYPOTHETISCH. Option C bringt funktional 100% von B bei 50% Aufwand
und ohne neuen Schema-Vertrag.

## Hauptaenderungen

### Neuer Helper-File

`includes/dhps-date-helpers.php`:

- `dhps_partial_date_to_iso( string $input, string $format ): ?string` Dispatcher
- `dhps_partial_date_mm_yy_to_iso( string $input ): ?string` fuer "10/24"
- `dhps_partial_date_de_month_year_to_iso( string $input ): ?string` fuer "Juli 2025"
- DE-Monatsnamen-Map mit ASCII+UTF-8-Doppelform (`maerz` + `märz`)
- 20YY-Konvention analog `DHPS_TP_Parser::format_datum`

### Bootstrap-Erweiterung

`Deubner_HP_Services.php`:

- Neuer `require_once` fuer `dhps-date-helpers.php` (nach mio-content-helpers, vor CLI)

### 3 Adapter additiv erweitert

`includes/class-dhps-tp-adapter.php`:

```php
if ( '' !== $datum ) {
    $meta['datum'] = $datum;
    // v0.18.1 (Option C): Beimaterial-Feld meta.date_iso.
    if ( function_exists( 'dhps_partial_date_to_iso' ) ) {
        $date_iso = dhps_partial_date_to_iso( $datum, 'mm_yy' );
        if ( null !== $date_iso ) {
            $meta['date_iso'] = $date_iso;
        }
    }
}
```

`includes/class-dhps-tpt-adapter.php`: analog TP (Single-Video-Output).

`includes/class-dhps-mio-adapter.php`:

```php
// Nur setzen wenn Parser-Title (NICHT Fallback-Title 'Monat N') parsebar ist.
if ( '' !== trim( $raw_title ) && function_exists( 'dhps_partial_date_to_iso' ) ) {
    $date_iso = dhps_partial_date_to_iso( $raw_title, 'de_month_year' );
    if ( null !== $date_iso ) {
        $meta['date_iso'] = $date_iso;
    }
}
```

## Schema-Vertrag-Vorgehen Iteration 16

| Feld | Adapter | Format | Beispiel |
|------|---------|--------|----------|
| `meta.date_iso` (TP/TPT) | TP/TPT-Adapter | `YYYY-MM` | `"2024-10"` |
| `meta.date_iso` (MIO) | MIO-Adapter | `YYYY-MM` | `"2025-07"` |
| `meta.datum` (TP/TPT) | TP/TPT-Adapter | `MM/YY` | `"10/24"` (UNVERAENDERT) |

`meta.date_iso` ist **opt-in**: Konsumenten muessen `isset($item->meta['date_iso'])`
pruefen. Bei Garbage-Input setzt der Adapter das Feld gar nicht.

## Tests

Lead-Smoke **35/35 PASS**:

- T1-T3: Helper-Existenz
- T4-T12: MM/YY-Helper (inkl. invalid month, kein Trenner, garbage)
- T13-T25: DE-Month-Year-Helper (inkl. case-insensitive, ASCII+UTF-8 Maerz,
  Mehrfach-Whitespace, Fallback-Title-Skip)
- T26-T28: TP-Adapter Integration (date_iso gesetzt, datum bytewise erhalten, leerer datum-Skip)
- T29: TPT-Adapter Integration
- T30-T33: MIO-Adapter Integration (Juli/Maerz, Fallback-Title Skip, title bytewise)
- T34: LP via TP-Adapter geteilt
- T35: LXMIO via MIO-Adapter geteilt

## Backward Compatibility

**Vollstaendig BC**:

- Templates UNANGETASTET (rendern weiter `meta.datum` MM/YY bzw. `$month['title']`)
- DTO-Schema UNANGETASTET (kein neues Pflichtfeld, kein neuer ALLOWED_TYPES-Eintrag)
- 9 Parser unangetastet
- Pipeline unangetastet
- AJAX-Endpoints unangetastet
- `echo $tc_html` Trust-Decision unangetastet
- Stage-Smoke Page 6 = 76 dhps-Klassen bytewise stabil (BC verifiziert)
- Stage-Smoke Page 8 = 91 dhps-Klassen bytewise stabil

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/34-DATUM-NORMALISIERUNG-PLAN-v0181.md` | Discovery |
| `docs/project/54-CHANGELOG-v0181.md` | (dieses Dokument) |
| `includes/dhps-date-helpers.php` | Helper-File |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.18.0 -> 0.18.1, require_once date-helpers |
| `README.md` | Version-Bump |
| `includes/class-dhps-tp-adapter.php` | meta.date_iso additiv (MM/YY) |
| `includes/class-dhps-tpt-adapter.php` | meta.date_iso additiv (MM/YY) |
| `includes/class-dhps-mio-adapter.php` | meta.date_iso additiv (de_month_year, Fallback-Title-Skip) |
| `MEMORY.md` | MILESTONE 25 + 7 v0.18.1 Implementation-Notes |

## Verbleibende Tech-Debt-Tickets

| Ticket | Geplant |
|--------|---------|
| TD-V0171-2 MMB-Lazy-Akkordeon-AJAX | v0.18.2 |
| TD-V0174-1 MIO-News-Container-AJAX | v0.18.2 |

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.18.2** | MMB-Lazy-Akkordeon + News-Container AJAX-Migrationen (Helper-Side-Channel-Pattern aus v0.17.5) |
| **v0.19.0** | `$data`-Param aus Template-Scope entfernen, evtl. Collection::sort_by_date_iso() |

## Bilanz v0.18.1

- **2 Tech-Debt-Tickets erledigt** (TD-V0172-2 + TD-V0173-2)
- **Lead-Tests 35/35 PASS**
- **0 BC-Bruch** (Templates + DTO + Parser + Pipeline unangetastet)
- **3 Adapter additiv erweitert** (5 Service-Tags durch Mehrfach-Registrierung)
- **1 neuer Helper-File** (3 Funktionen + DE-Monatsnamen-Map)
- Schema-Vertrag-Vorgehen **16x in Folge** ohne Critical-Drift
- **Beimaterial-Pattern** etabliert fuer kuenftige hypothetische Sortier-/Filter-Felder
