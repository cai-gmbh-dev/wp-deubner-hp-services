# Changelog v0.17.4 - TC-Adapter + 9/9 Migrations-Abschluss

## Stand: 2026-06-04

## Mission

**Letzter Adapter-Block: TC.** Damit ist die in v0.17.0 begonnene Datenmodell-
Initiative **komplett**: alle 9 Hauptservices nutzen einheitliches Adapter-Pattern.

TC ist ein Wrapper-Service: API liefert HTML + Inline-JS (test_einblenden/
test_ausblenden Akkordeon) als Einheit, kein strukturierter Output extrahierbar.
Die `echo $tc_html` Trust-Decision aus v0.13.0/v0.14.4 bleibt **unangetastet**.

## Strategie

**Option C/B-Hybrid** aus Discovery v0.17.4:

- Empty-State: leere Collection (0 Items)
- Sonst: 1 Item `type='generic'` mit `body=''`
- HTML lebt im **Collection-Meta** (`html`, `is_empty`)
- Pure **Lead-Direct** (kein Specialist - Scope ~80 LOC + 3x ~10 LOC Templates)

## Hauptaenderungen

### TC-Adapter (NEU)

`includes/class-dhps-tc-adapter.php` (~95 LOC inkl. Doc-Block):

- `final class DHPS_TC_Adapter implements DHPS_Content_Adapter_Interface`
- **0 HTML-Transformation** - 1:1 Passthrough mit `(string)`-Cast
- Inline-JS bleibt 1:1 erhalten
- Item-ID: `tc-calculators` (fix, TC ist Singleton)
- Item-Type: `'generic'` (erste Nutzung der ALLOWED_TYPES-Vorbereitung aus v0.17.0)
- Item-Title: `'TC Rechner'` (Pflicht non-empty, wird NIE gerendert)
- Collection-Meta: `html`, `is_empty`
- **Defensive Hardening**: whitespace-only HTML zaehlt als Empty
  (Parser-Bug-Resilienz)

### Bootstrap-Registrierung

`Deubner_HP_Services.php`:

```php
DHPS_Content_Adapter_Registry::register( 'tc', new DHPS_TC_Adapter() );
```

EINE Registrierung - kein Service-Variant wie LXMIO/LP/MIL.

### Template-Migration (3 TC-Templates)

`public/views/services/tc/default.php`, `card.php`, `compact.php`:

Pseudo-Rebuild-Pattern mit explizitem Trust-Decision-Hinweis:

```php
// Pseudo-Rebuild aus Collection wenn vorhanden (v0.17.4), sonst Legacy aus $data.
// echo $tc_html Trust-Decision (v0.13.0/v0.14.4) BLEIBT UNANGETASTET.
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
    $tc_html  = (string) $collection->get_meta( 'html', '' );
    $is_empty = (bool) $collection->get_meta( 'is_empty', true );
} else {
    $tc_html  = $data['html'] ?? '';
    $is_empty = ! empty( $data['is_empty'] );
}
// AB HIER: bestehender Render-Code inkl. echo $tc_html bytewise unveraendert
```

Render-Code ab dem Pseudo-Rebuild-Block ist **bytewise identisch** zur v0.17.3-
Version. PHPCS-Pragma + Begruendungs-Komment auf `echo $tc_html` unangetastet.

### Tests

Lead-Smoke 16/16 PASS:

- T1-T2: Adapter-Registrierung + Klassen-Identitaet
- T3: Empty-State -> count=0
- T4: Whitespace-only HTML -> Auto-Empty (Defense Hardening)
- T5: Content -> 1 Item type='generic'
- T6: Inline-JS bleibt 1:1
- T7: Sonderzeichen bleiben 1:1

## 9/9 Migrations-Abschluss-Report

### Status nach v0.17.4

| Service | Adapter-Klasse | Release | Templates migriert |
|---------|----------------|---------|---------------------|
| MAES | DHPS_MAES_Adapter | v0.17.0 | 3 |
| MMB | DHPS_MMB_Adapter | v0.17.1 | 3 |
| MIL | DHPS_MMB_Adapter (geteilt) | v0.17.1 | erbt MMB |
| TP | DHPS_TP_Adapter | v0.17.2 | 2 (compact Tech-Debt) |
| TPT | DHPS_TPT_Adapter | v0.17.2 | 3 |
| LP | DHPS_TP_Adapter (geteilt) | v0.17.2 | erbt TP |
| MIO | DHPS_MIO_Adapter | v0.17.3 | 3 |
| LXMIO | DHPS_MIO_Adapter (geteilt) | v0.17.3 | erbt MIO |
| **TC** | **DHPS_TC_Adapter** | **v0.17.4** | **3** |

**9/9 Hauptservices migriert.** Einheitliches Datenmodell-Initiative komplett.

### Bilanz v0.17.x-Zyklus

- **5 Adapter-Klassen** (MAES/MMB/TP/TPT/MIO/TC) + 1 DTO-Foundation
- **9 Service-Tags** via Mehrfach-Registrierung (MIL=MMB, LP=TP, LXMIO=MIO)
- **17 Templates** auf Collection-Pfad
- **3 Helper-Files** (`dhps-content-helpers.php`, `dhps-tp-content-helpers.php`,
  `dhps-mio-content-helpers.php`)
- **0 BC-Brueche** im gesamten Zyklus
- **Schema-Vertrag-Vorgehen 13x in Folge ohne Critical-Drift**

### 8 offene Tech-Debt-Tickets fuer v0.17.5 / v0.18.0

| ID | Scope | Geplant fuer |
|----|-------|--------------|
| TD-V0171-2 | MMB-AJAX-Handler (Lazy-Akkordeon) auf Adapter | v0.17.5 |
| TD-V0171-3 | MMB-Search-AJAX auf Adapter | v0.17.5 |
| TD-V0172-1 | tp/compact.php Collection-Migration (initCompactAccordion-Refactor) | v0.17.5 oder v0.18.0 |
| TD-V0172-2 | Datum-Normalisierung MM/YY -> ISO im DTO | v0.18.0 |
| TD-V0173-1 | [mio_termine] Sub-Shortcode auf Collection-Bridge | v0.17.5 |
| TD-V0173-2 | MIO Monat-Slug -> DateTimeImmutable | v0.18.0 |
| TD-V0174-1 | News-Container AJAX-Endpoint auf Adapter | v0.18.0 |
| TD-V0174-2 | Sub-Adapter fuer mio_termine/maes_videos/etc Whitelist-Aufnahme | v0.18.0 |

### Roadmap v0.18.0

**Legacy-Pfad in Templates entfernen**. Nach v0.17.4 sind alle Templates BC-Pattern-
faehig, aber else-Branch (Legacy aus `$data`) ist noch ueberall drin. v0.18.0
entfernt diesen `else`-Branch nach grosser BC-Smoke-Phase. Damit wird Pipeline-
Patch v0.17.0 zur **einzigen** Datenquelle, kein Doppel-Pfad mehr.

## Backward Compatibility

**Vollstaendig BC**:

- 9 Parser unveraendert
- `echo $tc_html` Trust-Decision (v0.13.0/v0.14.4) unangetastet (PHPCS-Pragma + Begruendungs-Komment unveraendert)
- 3 TC-Templates: Render-Code bytewise unveraendert
- TC-Empty-State-Component-Aufruf unveraendert (v0.14.4 EmptyState-Deduplikation)
- TC Inline-JS test_einblenden/test_ausblenden funktioniert weiter
- 9 Adapter aktiv: mio/lxmio/tp/tpt/lp/mmb/mil/maes/**tc**

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/30-TC-CLEANUP-PLAN-v0174.md` | Discovery |
| `docs/project/51-CHANGELOG-v0174.md` | (dieses Dokument) |
| `includes/class-dhps-tc-adapter.php` | TC-Adapter |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.17.3 -> 0.17.4, TC-Adapter-Registrierung |
| `README.md` | Version-Bump |
| `public/views/services/tc/default.php` | Pseudo-Rebuild-Block |
| `public/views/services/tc/card.php` | Pseudo-Rebuild-Block |
| `public/views/services/tc/compact.php` | Pseudo-Rebuild-Block |
| `MEMORY.md` | MILESTONE 22 + 7 v0.17.4 Implementation-Notes |

## Bilanz v0.17.4

- **9/9 Adapter aktiv** - Migration komplett
- **Lead-Smoke 16/16 PASS**
- **0 BC-Bruch** (echo $tc_html bytewise unveraendert)
- **'generic' Type** erstmals genutzt (DTO-Whitelist aus v0.17.0 voll genutzt)
- Schema-Vertrag-Vorgehen **13x in Folge** ohne Critical-Drift
- **v0.17.x-Initiative komplett** - lang gehegter User-Wunsch erfuellt

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.17.5** | Tech-Debt-Sammelrelease (MMB-AJAX + [mio_termine]-Bridge + tp/compact) |
| **v0.18.0** | Legacy-Pfad in Templates entfernen (Pipeline ist einzige Datenquelle) |
