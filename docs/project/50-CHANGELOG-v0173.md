# Changelog v0.17.3 - MIO/LXMIO-Adapter (vierter Migrations-Block)

## Stand: 2026-06-04

## Mission

Vierter Block der Adapter-Migration nach MAES (v0.17.0), MMB/MIL (v0.17.1) und TP/TPT/LP (v0.17.2). Damit sind **8 von 9 Hauptservices** auf das einheitliche Datenmodell migriert - nur TC verbleibt fuer v0.17.4.

MIO ist der komplexeste Service (Tax-Dates Sondertyp + News-AJAX-Container + Search-Form + Sub-Shortcode `[mio_termine]`). LXMIO erbt analog zu LP+TP via Mehrfach-Registrierung.

## Strategie

- **1 MIO-Adapter fuer mio + lxmio** (gleiche Instance, Service-Tag entscheidet im Item-Mapping)
- **tax_dates** als 1 Item pro Monatsspalte mit `type='tax_date'` (Option A)
- **News-Container bleibt clientside** (Adapter reicht nur `ajax_params` durch)
- **[mio_termine]** bleibt Legacy in v0.17.3 (Standalone-Klasse, Tech-Debt TD-V0173-1)
- LXMIO nutzt MIO-Templates ueber `dhps_template_fallbacks`-Filter

## Hauptaenderungen

### Phase 0 (Lead): Geteilter Helper

`includes/dhps-mio-content-helpers.php` (NEU):

- `dhps_mio_item_to_legacy_month( DHPS_Content_Item $item ): array`
- Wandelt ContentItem (type=tax_date) zurueck in Legacy-Monats-Shape (3 Felder: title, entries, footnote)
- Type-Filter im Helper (returnt [] bei type!=tax_date) -> Single-Responsibility
- Genutzt von 3 MIO-Templates -> EINZIGE Rebuild-Stelle

### F1: MIO-Adapter (175 LOC)

`includes/class-dhps-mio-adapter.php` (NEU):

- `final class DHPS_MIO_Adapter implements DHPS_Content_Adapter_Interface`
- Mappet `tax_dates[]` -> ContentItem mit `type='tax_date'`
- Item-ID Convention: `{service}-taxdate-{month_index}` (mio oder lxmio)
- Fallback-Title `'Monat N'` wenn Parser-leer (verhindert DTO-Exception)
- Skip-Condition: Monat ohne Title UND ohne Entries -> kein Item
- Item-meta: entries (Sub-Struktur), footnote, month_index
- Collection-Meta:
  - `search_config` (durchgereicht)
  - `ajax_params` (PFLICHT - News-Container nutzt das clientside)
  - `months_order` (numerische Indizes)
  - `total_months`, `total_entries`

**F1-Tests: 40/40 PASS** (T1-T12 mit jeweils 1-8 Sub-Assertions)

### Bootstrap-Registrierung

`Deubner_HP_Services.php` `dhps_init`:

```php
$mio_adapter = new DHPS_MIO_Adapter();
DHPS_Content_Adapter_Registry::register( 'mio', $mio_adapter );
DHPS_Content_Adapter_Registry::register( 'lxmio', $mio_adapter );
```

### Template-Migration (3 MIO-Templates)

`public/views/services/mio/default.php`, `card.php`, `compact.php`:

BC-Pattern mit Pseudo-Rebuild:

```php
if ( $has_collection ) {
    $tax_dates = array();
    foreach ( $collection as $item ) {
        $legacy_month = dhps_mio_item_to_legacy_month( $item );
        if ( ! empty( $legacy_month ) ) {
            $tax_dates[] = $legacy_month;
        }
    }
    $search_config = $collection->get_meta( 'search_config', array() );
    $ajax_params   = $collection->get_meta( 'ajax_params', array() );
} else {
    // Legacy-Pfad UNVERAENDERT
    $tax_dates     = $data['tax_dates']     ?? array();
    $search_config = $data['search_config'] ?? array();
    $ajax_params   = $data['ajax_params']   ?? array();
}
// AB HIER: bestehender Render-Code nutzt $tax_dates/$search_config/$ajax_params unveraendert
```

WICHTIG:

- `public/views/services/mio/partials/` unangetastet (search-form.php nutzt $search_config als Scope-Variable)
- News-Container weiterhin clientside-AJAX (data-Attribute aus ajax_params)
- LXMIO-Templates existieren nicht -> Fallback auf MIO-Templates via Filter

## Backward Compatibility

**Vollstaendig BC**:

- 9 Parser unveraendert
- MIO-Partials unveraendert
- News-Container-Logik unveraendert (clientside via dhps-mio.js)
- 3 modifizierte Templates: Render-Code bytewise unveraendert (nur Pseudo-Rebuild oben einfuegt)
- `[mio_termine]` Standalone-Klasse weiter funktional (TD-V0173-1)
- 8 Adapter aktiv: mio/lxmio/tp/tpt/lp/mmb/mil/maes

## Tech-Debt-Tickets v0.17.x

- **TD-V0173-1**: `[mio_termine]` Sub-Shortcode auf Collection-Bridge umstellen (Force-Legacy bei Filter-Atts month/count)
- **TD-V0173-2**: Datum-Normalisierung Monat-Slug -> DateTimeImmutable (Tag fehlt - Konsistenz-Frage)
- **TD-V0171-2** (offen): MMB-AJAX-Handler auf Adapter

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/29-MIO-ADAPTER-PLAN-v0173.md` | Discovery + Schema-Vertrag |
| `docs/project/50-CHANGELOG-v0173.md` | (dieses Dokument) |
| `includes/dhps-mio-content-helpers.php` | Helper `dhps_mio_item_to_legacy_month` |
| `includes/class-dhps-mio-adapter.php` | MIO-Adapter (F1) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.17.2 -> 0.17.3, Helper-require, 2 Adapter-Reg (mio/lxmio) |
| `README.md` | Version-Bump |
| `public/views/services/mio/default.php` | Pseudo-Rebuild-Block |
| `public/views/services/mio/card.php` | Pseudo-Rebuild-Block |
| `public/views/services/mio/compact.php` | Pseudo-Rebuild-Block |
| `MEMORY.md` | MILESTONE 21 + 7 v0.17.3 Implementation-Notes |

## Migrations-Status nach v0.17.3

| Service | Adapter | Templates |
|---------|---------|-----------|
| MAES | DHPS_MAES_Adapter | 3 (v0.17.0) |
| MMB | DHPS_MMB_Adapter | 3 (v0.17.1) |
| MIL | DHPS_MMB_Adapter | erbt MMB |
| TP | DHPS_TP_Adapter | 2 (compact Tech-Debt) |
| TPT | DHPS_TPT_Adapter | 3 (v0.17.2) |
| LP | DHPS_TP_Adapter | erbt TP |
| MIO | DHPS_MIO_Adapter | 3 (v0.17.3) |
| LXMIO | DHPS_MIO_Adapter | erbt MIO |
| TC | offen | offen (v0.17.4) |

**8 von 9 Hauptservices migriert** - nur TC verbleibt.

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.17.4** | **TC-Adapter** (Wrapper-Pattern, letzter Hauptservice) + Cleanup |
| **v0.18.0** | Legacy-Pfad in Templates entfernen (nach v0.17.4 Migrations-Abschluss) |

## Bilanz v0.17.3

- **8 Adapter aktiv** (mio/lxmio/tp/tpt/lp/mmb/mil/maes)
- **F1-Tests: 40/40 PASS** (umfangreichste Test-Suite seit v0.17.0 - 12 Tests + 28 Sub-Assertions)
- **0 BC-Bruch** (Render-Code bytewise unveraendert in 3 Templates)
- **Tax-Dates Sondertyp** sauber ueber ALLOWED_TYPES=tax_date (DTO-Whitelist seit v0.17.0)
- Schema-Vertrag-Vorgehen **12x in Folge** ohne Critical-Drift
- MIO ist komplexester Service, sauber migriert ohne Sonderfall-Brueche
