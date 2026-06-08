# Changelog v0.18.3 - Polish ($extra_meta-Bridge + Pattern-Doc-Konsolidierung)

## Stand: 2026-06-08

## Mission

Polish-Release fuer 2 Info-Findings aus dem v0.18.2-QA-Report:

- **F1**: Helper-Signaturen weichen vom Discovery-Plan 35 ab (`$extra_meta`-Param fehlt)
- **F4**: Naming-Drift `sheet_index` (v0.18.2) vs. `result_index` (v0.17.5)

## Strategie: Option C (Hybrid) - eingeschraenkt

Discovery 36 hat 3 Optionen verglichen:

| Option | Aufwand | BC-Risiko | Verdikt |
|--------|---------|-----------|---------|
| A (Doku-only) | XS | 0 | Spec-Drift unkorrigiert |
| B (Code-only) | S | 0 | Pattern-Doku verbleibt unkomplett |
| **C (Hybrid eingeschraenkt)** | **S** | **0** | **EMPFOHLEN** |

- `$extra_meta` nachruesten in 2 Helfern (NICHT 4):
  - `dhps_mmb_category_to_collection` (v0.18.2)
  - `dhps_mio_news_to_collection` (v0.18.2)
- F4: reine Doku-Konvention (kein Code-Refactor)

## Hauptaenderungen

### 2 Helper-Signaturen erweitert

`includes/dhps-content-helpers.php`:

```php
function dhps_mmb_category_to_collection( array $category, string $service, array $extra_meta = array() )
function dhps_mio_news_to_collection( array $parsed_news, string $service, array $extra_meta = array() )
```

`$extra_meta` wird in Collection-Meta gemerged. Merge-Order:

```php
$collection_meta = array_merge(
    $extra_meta,            // 1) Aufrufer-Kontext zuerst
    array(                  // 2) Helper-Defaults dominieren
        'is_lazy_category' => true,
        // ...
    )
);
```

**Bei Key-Kollision gewinnen Helper-Defaults** - Side-Channel-Invarianten
(`is_lazy_category`, `is_news` etc.) bleiben erhalten.

### 2 AJAX-Handler nutzen $extra_meta

`includes/class-dhps-mmb-ajax-handler.php` `handle_request`:

```php
$category_collection = dhps_mmb_category_to_collection(
    $category,
    $service,
    array( 'layout' => $layout )  // Layout-Hint
);
```

`includes/class-dhps-ajax-proxy.php` `handle_news_request`:

```php
$news_collection = dhps_mio_news_to_collection(
    $parsed,
    'mio',
    array(
        'fachgebiet'  => $fachgebiet,
        'variante'    => $variante,
        'anzahl'      => $anzahl,
        'teasermodus' => $teasermodus,
    )
);
```

### Pattern-Doc-Update

`docs/architecture/32-SUB-SHORTCODE-PATTERN.md`:

- Helper-Inventar aktualisiert (10 Helper mit "Seit"-Spalte)
- Action-Hook-Side-Channels-Tabelle (3 Hooks)
- **NEUE Sektion**: Konvention `$extra_meta`-Param (B.1-Merge-Order)
- **NEUE Sektion**: Konvention Item-Meta-Indices (`result_index` / `sheet_index` / `group_index`)

## Tests

`test-v0183-polish.php` (Lead-Smoke):

- T1-T3 MMB-Cat-Helper: layout im Collection-Meta + Key-Kollision-Defense + BC ohne $extra_meta
- T4-T5 News-Helper: 4 Filter-Atts im Collection-Meta + Helper-Defaults bleiben
- T6-T7 News Key-Kollision-Defense + BC ohne $extra_meta

**Resultat: 18 PASS / 0 FAIL**

## Backward Compatibility

**Vollstaendig BC**:

- `$extra_meta` ist optionaler 3. Parameter mit Default `array()`
- Bestehende 2-arg-Aufrufer (in v0.18.2-Code, Theme-Hooks, Plugin-Hooks) funktionieren unveraendert
- Helper-Defaults dominieren bei Key-Kollision (Side-Channel-Invarianten geschuetzt)
- 9 Adapter-Klassen unangetastet
- 9 Parser unangetastet
- Templates unangetastet
- Pipeline unangetastet
- JSON-Response-Vertraege bytewise unveraendert (extra_meta landet NUR in Collection-Meta, NICHT in JSON-Response)
- Stage-Smoke Page 6 = 76 dhps-Klassen bytewise stabil

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/36-POLISH-PLAN-v0183.md` | Discovery |
| `docs/project/56-CHANGELOG-v0183.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.18.2 -> 0.18.3 |
| `README.md` | Version-Bump |
| `includes/dhps-content-helpers.php` | 2 Helper-Signaturen + Doc-Blocks + array_merge |
| `includes/class-dhps-mmb-ajax-handler.php` | Helper-Aufruf mit `['layout' => $layout]` |
| `includes/class-dhps-ajax-proxy.php` | Helper-Aufruf mit 4 Filter-Atts |
| `docs/architecture/32-SUB-SHORTCODE-PATTERN.md` | $extra_meta-Konvention + Naming-Konvention + Inventar-Updates |
| `MEMORY.md` | MILESTONE 27 + Implementation-Notes |

## Bilanz v0.18.3

- **2 QA-Findings sauber abgearbeitet** (F1 Spec-Drift + F4 Naming-Doku)
- **Lead-Tests 18/18 PASS**
- **0 BC-Bruch** (optionaler 3. Parameter, Default `[]`)
- **2 Helper-Signaturen** erweitert (2-arg -> 3-arg defensive Extension-API)
- **Pattern-Doc** erweitert um $extra_meta-Konvention + Naming-Konvention
- Schema-Vertrag-Vorgehen **18x in Folge** ohne Critical-Drift
- **Defensive Extension-API** etabliert: Side-Channel-Helper koennen Aufruf-Kontext ohne BC-Bruch nachgeruestet werden

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.19.0** | MAJOR: $data-Param raus + Deprecated-Proxy + $service_tag direkt im Template-Scope |
| **v0.18.4** | Weitere kleine Polish-Items |
