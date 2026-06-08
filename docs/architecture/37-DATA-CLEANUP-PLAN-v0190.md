# Data-Cleanup-Plan v0.19.0 - $data-Param raus aus Template-Scope

## Stand: 2026-06-08 (Discovery-Phase)

## Mission

**MAJOR-Version.** Letzter Schritt nach v0.18.0 Legacy-Pfad-Entfernung:
`$data`-Parameter aus Template-Scope und Renderer-Signaturen entfernen.
`$collection` (DHPS_Content_Collection) ist EINZIGE Daten-Quelle im
Template-Scope.

Heutiger Status (post-v0.18.2):

- Pipeline reicht sowohl `$data` als auch `$collection` an Templates
- 22 Plugin-Templates lesen `$collection` immer (v0.18.0 cleanup)
- 22 Plugin-Templates lesen `$data` jedoch noch fuer **2 Use-Cases**
- Theme-Override-Templates koennten weiter `$data['...']` lesen (BC-Risiko)

v0.19.0 = bewusster BC-Bruch fuer **Theme-Overrides**. Site-Owner-HTML-BC
bleibt vollstaendig.

## Schlussel-Befund: $data-Usage ist klein

`grep "\$data\[" public/views` ueber 22 Plugin-Templates ergibt nur
**2 verschiedene Use-Cases**:

| Use-Case | Code-Stelle | Vorkommen | Notwendigkeit |
|----------|-------------|-----------|---------------|
| `$data['service_tag']` als String-Lookup fuer Branding | 12 Templates | hoch | mittlerer Migrations-Aufwand |
| `$data['videos'/'merkblaetter'/'news']` Reverse-Compat (MAES Orchestrator-Shim) | 3 MAES-Templates (default/card/compact) | hoch | tot - Sub-Templates lesen aus Collection |

**Keine** Template liest mehr Service-spezifische Datenfelder ueber `$data`
(`tax_dates`, `categories`, `featured`, `video`, etc.) - die Adapter+Helper-
Pipeline hat das alles abgeloest in v0.18.0.

## $data-Inventar (alle 22 Templates + 1 Renderer)

### Klasse A: nur `$data['service_tag']`-Lookup (10 Templates)

| Template | Zeile | Code | Service-Tag-Use |
|----------|-------|------|------------------|
| `services/mio/default.php` | 53 | `$service_tag = $data['service_tag'] ?? 'mio';` | data-service-tag-Attribut |
| `services/mio/card.php` | 42 | dito | dito |
| `services/mio/compact.php` | 33 | dito | dito |
| `services/mmb/default.php` | 41 | `$service_tag = $data['service_tag'] ?? 'mmb';` | data-service-tag + MIL-Label |
| `services/mmb/card.php` | 37 | dito | dito |
| `services/mmb/compact.php` | 34 | dito | dito |
| `services/tp/default.php` | 54 | `$service_tag = $data['service_tag'] ?? 'tp';` | LP-Branding-Switch |
| `services/tp/card.php` | 46 | dito | dito |
| `services/tp/compact.php` | 27 | dito | dito |
| `services/maes/default.php`, `card.php`, `compact.php` | siehe Klasse B | dito | nur fuer Token-Schluessel im Wrapper |

Migration: 3 Zeilen pro Template.
**Lesen `$data['service_tag']` -> `$collection->service`** (Collection-Service-Tag).

ACHTUNG: bei MMB/MIL liefert `$collection->service` immer `mmb` (Adapter ist
fuer beide service-Tags registriert, aber Collection bekommt das tag aus dem
Aufruf via Pipeline-Patch in v0.17.0). Cross-Check:

```bash
grep "Adapter_Registry::register" includes/class-dhps-*-adapter.php
```

Liefert: `mmb+mil` shared Adapter, `tp+lp` shared, `mio+lxmio` shared.
**Pipeline patcht `$parsed_data['service_tag'] = $tag` VOR Adapter** (Z. 133
class-dhps-content-pipeline.php), Adapter setzt das in `Item::service` UND
Collection-Service via `Collection->__construct($tag, ...)`. **Collection->service
ist verlaesslich der Wrapper-Service-Tag** (mmb/mil/tp/lp).

Damit ist `$data['service_tag']` zu `$collection->service` 1:1 austauschbar.

### Klasse B: MAES Orchestrator-Shim (3 Templates)

| Template | Zeilen | Code | Status |
|----------|--------|------|--------|
| `services/maes/default.php` | 33-36 | `$videos = $data['videos'] ?? array();` (+ merkblaetter/news/service_tag) | **toter Code** |
| `services/maes/card.php` | 20-23 | dito | **toter Code** |
| `services/maes/compact.php` | 20-23 | dito | **toter Code** |

Diese 3 Templates sind **Orchestrator-Shims** (siehe Header-Docblock von
`maes/default.php`): sie includen `videos.php` / `merkblaetter.php` /
`aktuelles.php` / oder die `-card`/`-compact`-Varianten. Die Variablen
`$videos` / `$merkblaetter` / `$news` werden im Top-Level-Orchestrator
deklariert, **aber die Sub-Templates lesen aus `$collection`** (v0.18.0 - siehe
`videos.php` Z. 53-58, `aktuelles.php`, `merkblaetter.php`).

Trotzdem werden die Variablen heute als **Empty-Guards** verwendet:

```php
<?php if ( $show_videos && ! empty( $videos ) ) : ?>
  <?php include $base_path . 'videos.php'; ?>
<?php endif; ?>
```

Migration: die Empty-Guards aus Collection lesen via:

```php
$collection_videos = $collection->filter(
    static fn( $item ) => $item->type === 'video'
);
$has_videos = ! $collection_videos->is_empty();
```

Oder einfacher (3 Zeilen am Anfang):

```php
$collection = dhps_collection_or_empty( $collection, 'maes' );
$has_videos       = $collection->filter( static fn( $item ) => $item->type === 'video' )->count() > 0;
$has_merkblaetter = $collection->filter( static fn( $item ) => $item->type === 'document' )->count() > 0;
$has_aktuelles    = $collection->filter( static fn( $item ) => $item->type === 'news' )->count() > 0;
```

Achtung: MAES-Adapter mappt Aktuelles auf welchen `type`? Pruefen via Discovery
(`grep "'news'" includes/class-dhps-maes-adapter.php`). Aus v0.18.2-CHANGELOG:
`'news'` Item-Type ist seit v0.18.2 produktiv fuer MIO-News - MAES-Adapter
sollte das auch nutzen.

### Klasse C: TPT Doc-Block-Referenz (3 Templates, kein Code-Lesen)

| Template | Zeile | Code | Status |
|----------|-------|------|--------|
| `services/tpt/default.php` | 20, 33 | Docblock `@param $data` | Doku-Update |
| `services/tpt/card.php` | 10, 18 | Doc-Block | Doku-Update |
| `services/tpt/compact.php` | 11, 19 | Doc-Block | Doku-Update |

`$data['tpt_config']` ist heute **NICHT** im Template-Code, das Modul liest
`$collection->get_meta('tpt_config')` (Z. 43 tpt/default.php). Die
Docblock-Erwaehnung ist veraltet.

Migration: nur Header-Docblock-Update (`@param $data` raus, `@param $collection` rein).

### Klasse D: TC (3 Templates, nur Docblock)

| Template | Zeile | Code | Status |
|----------|-------|------|--------|
| `services/tc/default.php` | 10 | Doc-Block: `@param $data array { 'html' => ..., 'is_empty' => ... }` | Doku-Update |
| `services/tc/card.php`, `compact.php` | analog | Doc-Block | Doku-Update |

Templates lesen `$collection->get_meta('html')` / `get_meta('is_empty')`
(v0.18.0).

### Klasse E: Steuertermine (4 Templates)

| Template | Zeile | Code | Status |
|----------|-------|------|--------|
| `steuertermine/default.php`, `card.php`, `compact.php`, `inline.php` | 27 | `$data = $rebuilt;` | **Variable-Recycling** |
| Selbe Templates | 31, 34/35 | `count( $data )` / `foreach ( $data as ... )` | **Vorhandene Iteration** |

Aktuell wird `$data` als **lokale Variable** ueberschrieben mit dem
Pseudo-Rebuild-Resultat. Migration: `$data` -> `$months` als lokale Variable
umbenennen.

```php
// alt:
$data = $rebuilt;
foreach ( $data as $month ) : ...

// neu:
$months = $rebuilt;  // oder direkt: $months = $collection->get_items()-rebuilded
foreach ( $months as $month ) : ...
```

### Klasse F: `videos.php` (1 Sub-Template, nur Kommentar)

`services/maes/videos.php` Z. 51 hat einen Kommentar "Modules-Layer filtert
`$data` BEVOR Adapter-Build" - Code-Pfad lest aber bereits aus
`$collection`. Header-Docblock Z. 11 erwaehnt `$videos - Array (Legacy)`.
Migration: Doku-Update.

### Klasse G: Renderer (1 Signatur)

`includes/class-dhps-renderer.php` Z. 138:

```php
public function render_parsed(
    array $data,
    string $tag,
    string $layout = 'default',
    string $css_class = '',
    ?DHPS_Content_Collection $collection = null
): string {
    ...
    include $template_file;   // <-- $data und $collection im Template-Scope
}
```

Migration-Optionen unten.

### Klasse H: Sub-Shortcode-Module-Renderer (2 Klassen)

| Klasse | Methode | Code |
|--------|---------|------|
| `DHPS_MAES_Modules` | `render_videos`, `render_merkblaetter`, `render_aktuelles` | `include $template; return ob_get_clean();` mit `$collection` + `$data` im Scope |
| `DHPS_Steuertermine` | `render_template` | `$data = $tax_dates;` + `include $template;` |

Migration: $data-Variable streichen.

### Klasse I: 4 Partials (alle ok, kein $data)

`services/mmb/partials/category-content.php`, `card-content.php`,
`compact-content.php`, `services/mio/partials/search-form.php`:

`grep "\$data" public/views/services/.../partials/*.php` -> 0 Treffer.
Diese sind nur Includes von oben + lesen Variablen aus Outer-Scope (z.B.
`$category`, `$search_config`). Keine Migration noetig.

## Producer-Scan: Wer setzt $data im Scope?

| Quelle | Zeile | Code | Migration |
|--------|-------|------|-----------|
| `DHPS_Renderer::render_parsed` | 138 (param) + 177 (include) | `array $data` Param + `include $template_file` | Signatur-Aenderung |
| `DHPS_MAES_Modules::render_videos/_merkblaetter/_aktuelles` | jeweils ~Z. 246+/297+/344+ | `include $template; ` mit `$data` im Scope durch Variablen-Definition oben | Variable-Cleanup |
| `DHPS_Steuertermine::render_template` | 228 | `$data = $tax_dates;` + `include $template;` | Variable-Cleanup |
| Templates SELBST (Klasse E) | jeweils Z. 27 | `$data = $rebuilt;` Lokales Recycling | Variable-Rename |

**Keine** anderen Producer. Pipeline reicht $data DIREKT durch
`render_parsed(...)`-Aufruf an den Renderer, der es im Template-Scope
sichtbar macht.

## BC-Impact-Liste

### Brueche durch $data-Entfernung

| Konsument | Risiko | Mitigation |
|-----------|--------|------------|
| **Theme-Override-Templates (T-CRIT)** | Theme-Override liest `$data['featured']`/`['categories']`/`['tax_dates']` -> Notice + leere Render-Stelle | Migration-Strategie (s.u.) + MIGRATION-Doku |
| **Plugin-Code mit `dhps_pipeline_data_{tag}`-Filter** | Hooks LESEN `$parsed_data` und MODIFIZIEREN es - das ist BEFORE Adapter, NICHT betroffen | 0 Impact - Filter laeuft auf Pipeline-Layer, nicht im Template |
| **Sub-Shortcode-Render-Handler intern** | `DHPS_MAES_Modules::render_videos` baut `$collection` aus `$filtered_data` -> Filter wirken intern, nicht ueber $data im Template-Scope | 0 Impact |
| **Custom-Render-Aufrufer ausserhalb Pipeline** | Ein Plugin koennte `$renderer->render_parsed( $data, ... )` direkt aufrufen | Renderer-Signatur-Variante entscheidet |

### Bleibt vollstaendig BC

- HTML-Render-Output bytewise unveraendert (Site-Owner)
- AJAX-JSON-Responses bytewise unveraendert
- Shortcode-Atts unveraendert
- Adapter+Parser+Pipeline-Filter unveraendert
- `dhps_template_fallbacks`-Filter unveraendert
- `dhps_content_adapter_for_service`-Filter unveraendert
- 9 Service-Tags + 6 Adapter unveraendert
- `echo $tc_html` Trust-Decision unangetastet
- `meta.date_iso` Beimaterial-Feld aus v0.18.1 unveraendert

## Migration-Strategie - Option-Vergleich

### Option A: Hard-Bruch

`array $data` Param raus aus Renderer-Signatur. `$data` ist nicht mehr im
Template-Scope. Theme-Overrides die `$data` lesen -> PHP-Notice.

**Vorteil**: kleinster Code-Footprint, kuerzeste MIGRATION.md.
**Nachteil**: keine Warnung, Theme-Entwickler merken den Bruch erst beim
Live-Render. Lighthouse/SEO koennen es nicht catchen.

### Option B: Deprecated-Helper (EMPFOHLEN)

`array $data` Param BLEIBT in Renderer-Signatur (Default `array()`). Im
Template-Scope wird `$data` NICHT mehr exposed - stattdessen wird vor
`include` ein Magic-Variable-Mechanismus gesetzt:

```php
// Renderer::render_parsed v0.19.0
$collection_obj = ...;

// $data wird bewusst NICHT in den Template-Scope durchgereicht.
// Theme-Overrides die noch `$data['service_tag']` lesen bekommen via
// undeclared-variable-Notice ein Signal.

// Alternativ: Magic-Getter via Proxy-Klasse mit deprecation_log
$data = new DHPS_Deprecated_Data_Proxy( $data, $collection, $tag, $layout );

ob_start();
include $template_file;
```

Wo `DHPS_Deprecated_Data_Proxy` ein **ArrayAccess+Countable**-Wrapper ist,
der bei jedem Lese-Zugriff `_doing_it_wrong()` aufruft + WP_DEBUG-error_log
schreibt. Konsumenten sehen den Deprecation-Notice in WP_DEBUG-Mode.

**Vorteil**: explizite Deprecation-Notice + 1-Release-Migration-Fenster +
Theme-Entwickler bekommen Hinweis.
**Nachteil**: ~30 LOC Proxy-Klasse + Test, leichte Performance-Penalty bei
$data-Lesern.

### Option C: Filter-Hook-Opt-In

Site-Owner kann `$data` via Filter `dhps_template_legacy_data_enabled` opt-in
zurueckholen:

```php
add_filter( 'dhps_template_legacy_data_enabled', '__return_true' );
```

Default: kein `$data`. Mit Filter aktiv: `$data` wird im Template-Scope
exposed.

**Vorteil**: theoretisch unbegrenzte Migrations-Zeit fuer Theme-Entwickler.
**Nachteil**: Tech-Debt verlaengert sich indefinitely, zweistufiger
Code-Pfad, Tests muessen beide Modi pruefen.

### Empfehlung: Option B (Deprecated-Helper)

| Kriterium | A | B | C |
|-----------|---|---|---|
| Theme-Entwickler-Freundlich | nein | JA | hoechste |
| Code-Footprint | XS | S | M |
| Tech-Debt nach Release | 0 | 1 Release | indefinit |
| BC-Bruch-Signal | hart | deprecation_log | versteckt |
| Stage-Smoke-Aufwand | klein | klein | mittel |

**Option B** ist Standard-PHP-Library-Patroll-Pattern (vgl. Magento
`Mage_Deprecated_Object`, Symfony `TriggerDeprecation`).

In v0.19.1 oder v0.20.0 wird der Proxy-Wrapper entfernt -> hartes Aus.

### Alternative: Option A-eingeschraenkt

Falls B als "zu viel Mechanik" empfunden wird: Option A mit ausfuehrlicher
MIGRATION-Doku + WP_DEBUG-error_log im Renderer-Layer:

```php
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    // Diagnose-Helper: erkennt $data-Reads im Template via ob_start-Hook? NEIN, geht nicht.
    // Daher nur Doku-Pfad.
}
```

WP_DEBUG-Pfad bringt aber nur was, wenn das System es ueberhaupt erkennen
kann - nicht moeglich ohne Proxy. Daher entweder B oder A pur.

**Entscheidungs-Vorschlag fuer Lead**: **Option B** umsetzen,
DHPS_Deprecated_Data_Proxy als final readonly Klasse mit ArrayAccess +
Countable + IteratorAggregate. Deprecation-Notice nur bei
`offsetGet`/`offsetExists`/`count`/`getIterator`-Calls, NICHT bei reinem
"Variable existiert im Scope"-Check (`isset( $data )` muss truthy bleiben
ohne Notice).

## TD-Phase-B: Collection-Hooks (Sort)

Discovery-Prompt fragt: soll `Collection::sort_by_date_iso(): Collection`
mit rein?

### Analyse

| Use-Case | Bestehend? | Geplanter Konsument |
|----------|------------|---------------------|
| News-Sortierung nach Datum | nein (clientside-AJAX) | hypothetisch |
| Tax-Dates-Sortierung nach Monat | nein (Parser liefert chronologisch) | hypothetisch |
| TP-Videos-Sortierung nach `meta.date_iso` | nein | hypothetisch |

Kein bestehender Konsument. v0.18.1 hat `meta.date_iso` als **Beimaterial-
Feld** etabliert - aktuell von 0 Templates und 0 Hooks gelesen.

### Empfehlung: TD-Phase-B AUSSCHLIESSEN aus v0.19.0

| Argument | Gewicht |
|----------|---------|
| Mix-Risiko: BC-Bruch (Phase-A) + Feature-Add (Phase-B) im selben Release | hoch |
| Test-Surface verdoppelt | hoch |
| Kein produktiver Konsument | hoch |
| Phase-B kann als reines Pure-Add in v0.19.1 ohne BC-Risiko nachkommen | mittel |

**Verschiebung auf v0.19.1**: `Collection::sort_by_date_iso(): Collection`
+ optional `Collection::sorted_by( callable $cmp ): Collection` als
generisches Pattern.

## TD-Phase-C: Sub-Shortcode-Module-Patches

Aus dem Discovery-Prompt:

> 3 MAES render_*-Methoden: $data raus, DHPS_Steuertermine::render_template:
> $data raus, Signatur anpassen

### Konkrete Aenderungen

**`DHPS_MAES_Modules::render_videos`** (analog merkblaetter/aktuelles):

```php
// alt (heute, v0.18.2):
$collection = dhps_build_collection_for( 'maes', $filtered_data );
ob_start();
include $template;     // <-- $data ist hier im Scope, kommt aus $data = $this->get_data();
return ob_get_clean();

// neu (v0.19.0, Option B):
$collection = dhps_build_collection_for( 'maes', $filtered_data );
$data       = new DHPS_Deprecated_Data_Proxy( $filtered_data, $collection, 'maes', $layout );
ob_start();
include $template;
return ob_get_clean();
```

ODER (Option B-eingeschraenkt, kein Proxy in Modules - nur in Renderer):

```php
// neu (v0.19.0):
$collection = dhps_build_collection_for( 'maes', $filtered_data );
ob_start();
include $template;     // <-- $data ist NICHT mehr im Scope. Theme-Override bricht via Notice.
return ob_get_clean();
```

**`DHPS_Steuertermine::render_template`**:

```php
// alt:
$data         = $tax_dates;
$custom_class = ! empty( $css_class ) ? ' ' . $css_class : '';
ob_start();
include $template;

// neu:
$custom_class = ! empty( $css_class ) ? ' ' . $css_class : '';
ob_start();
include $template;
```

Signatur unangetastet (`render_template( array $tax_dates, ... )` -
$tax_dates wird nicht in den Template-Scope geleakt, sondern nur ueber
$collection via dhps_mio_item_to_legacy_month rebuilded).

## TD-Phase-D: Theme-Override-Doku

`docs/team-knowledge/11-MIGRATION-v0190.md` (NEU):

Analog `10-MIGRATION-v0180.md`:

```markdown
# Migration v0.18.x -> v0.19.0

## TL;DR

v0.19.0 entfernt `$data` aus dem Template-Scope. Theme-Override-Templates
muessen migrieren.

## Pre-Migration

```php
// alt (v0.18.x):
$service_tag = $data['service_tag'] ?? 'mio';
$categories  = $data['categories'] ?? array();
```

## Post-Migration

```php
// neu (v0.19.0):
$collection  = dhps_collection_or_empty( $collection, 'mio' );
$service_tag = $collection->service;
$categories  = dhps_mmb_collection_to_legacy_categories( $collection );  // fuer MMB
```

## Mapping-Tabelle

| alt | neu |
|-----|-----|
| `$data['service_tag']` | `$collection->service` |
| `$data['categories']` | `dhps_mmb_collection_to_legacy_categories( $collection )` |
| `$data['featured']` + `$data['categories']` | `dhps_tp_collection_to_legacy_categories( $collection )` |
| `$data['video']` (TPT) | foreach + `dhps_tp_item_to_legacy_video( $item )` |
| `$data['tax_dates']` | `dhps_mio_item_to_legacy_month( $item )` |
| `$data['html']` (TC) | `$collection->get_meta( 'html' )` |
| `$data['is_empty']` (TC) | `$collection->get_meta( 'is_empty' )` |
| `$data['tpt_config']` | `$collection->get_meta( 'tpt_config' )` |
| `$data['videos']`/`merkblaetter`/`news` (MAES) | `$collection->filter( $type_pred )` |
```

## Schema-Vertrag (Was bleibt, was raus)

| Template-Scope-Variable | v0.18.x | v0.19.0 |
|-------------------------|---------|---------|
| `$collection` | da | **da** (Pflicht) |
| `$service_class` | da | **da** |
| `$layout_class` | da | **da** |
| `$custom_class` | da | **da** |
| `$data` | da | **deprecation-proxy** (Option B) **oder raus** (Option A) |
| `$service_tag` | nicht direkt, aus `$data['service_tag']` | **NEU**: optional direkt im Scope (siehe unten) |

### Option: $service_tag direkt im Scope (Nice-to-Have)

Heute lesen 12 Templates `$service_tag = $data['service_tag'] ?? 'xyz'` als
Quasi-Identifier. Mit v0.19.0 muessten sie auf `$collection->service`
umsteigen, was BC-mig konsistent ist aber 12 Templates anfasst.

Alternative: Renderer reicht `$service_tag` direkt mit:

```php
// Renderer::render_parsed v0.19.0
$service_tag = $tag;   // bereits Param der Methode
ob_start();
include $template_file;
```

Templates koennen dann **ohne Aenderung** weiter `$service_tag` lesen (kein
$data-Lookup mehr noetig). Migration ist ein No-Op fuer 12 Templates.

**Empfehlung**: ja, $service_tag direkt im Scope. Spart 12 Templates-Touches
und macht die API expliziter.

## Spec-Aufteilung

### Empfehlung: Pure Lead-Direct

Begruendung:

- Pattern bekannt (Renderer-Signatur-Edit, Variable-Cleanup)
- Scope kleiner als erwartet:
  - 1 Klasse `DHPS_Deprecated_Data_Proxy` (~50 LOC, Option B)
  - 1 Renderer-Signatur-Edit + 1 Block-Body-Edit
  - 2 Sub-Shortcode-Module-Klassen-Patches (3+1 Methoden)
  - 12 Templates: `$service_tag`-Source switchen (1 Zeile pro Template wenn
    Renderer das auch durchreicht: 0 Templates)
  - 4 Templates (Klasse E + 1 MAES `videos.php`): Variable-Rename
  - 3 MAES Orchestrator-Templates: empty-guards umbauen
- 0 Adapter-/Parser-/Pipeline-Aenderungen
- 1 MIGRATION-Doku-Datei + 1 CHANGELOG + Version-Bump

**Aufwand**: M (mittelschwer).

Sub-Phasen:

| Phase | Scope | LOC |
|-------|-------|-----|
| P1 Renderer-Signatur + $service_tag-Scope | 1 Datei | +5 |
| P2 DHPS_Deprecated_Data_Proxy + Tests | 1 neue Klasse | +50 |
| P3 Sub-Shortcode-Module-Patches | 2 Klassen | -8 |
| P4 MAES-Orchestrator-Empty-Guards | 3 Templates | -12 / +12 |
| P5 Steuertermine $data-Recycling-Rename | 4 Templates | +/- 0 |
| P6 Doc-Block-Updates | 3 TPT + 3 TC + 6 andere | Doku |
| P7 MIGRATION.md + CHANGELOG + Version-Bump | 3 Files | +200 Zeilen Doku |
| P8 Lead-Smoke T1-T20 + Stage-Smoke | 1 test-v0190.php | +200 |

**Total**: ~50 LOC Code + ~200 Zeilen Doku + ~200 LOC Tests.

### Specialist-Triage

Falls ein Specialist gewuenscht:

- **Renderer + Sub-Shortcode-Module-Specialist**: P1+P2+P3 (~70 LOC + Tests).
  Trade-off: erst sinnvoll bei Option A-vs-B-Klarheit + DHPS_Deprecated_Data_Proxy
  als Klasse. Discovery-Time bisher: 0 - wir koennen pure-Lead.

Empfehlung: **Pure Lead-Direct**, kein Specialist.

## Tests T1-T20

`test-v0190.php` (Plugin-Root-Smoke):

### Renderer-Layer (T1-T6)

- **T1**: `DHPS_Renderer::render_parsed(['service_tag'=>'mio'], 'mio', ...)`
  -> Template sieht **NUR** `$collection` + `$service_tag` + `$service_class`
  + `$layout_class` + `$custom_class` im Scope. **NICHT** `$data` (oder
  Proxy bei Option B).
- **T2**: Template das `$data['service_tag']` liest:
  - Option A: PHP-Notice + leere Variable
  - Option B: WP_DEBUG-Log via Proxy + funktioniert weiter
- **T3**: Renderer-Signatur akzeptiert weiter `array $data` Param (intern
  ignoriert/verarbeitet je nach Option).
- **T4**: Template das nur `$collection` liest funktioniert wie bisher.
- **T5**: BC: 22 Plugin-Templates rendern bytewise identische HTML-Outputs
  pre vs. post v0.19.0 (Stage-Smoke).
- **T6**: `$service_tag` im Scope verlaesslich, auch fuer LXMIO/LP/MIL
  (Multi-Adapter-Service-Tags).

### Deprecated-Proxy (T7-T11, nur Option B)

- **T7**: `$data['service_tag']` Read -> deprecation_log written.
- **T8**: `isset( $data )` -> true (keine Notice).
- **T9**: `count( $data )` -> deprecation_log + count-Value korrekt.
- **T10**: `foreach ( $data as ... )` -> deprecation_log + Iterator-funktional.
- **T11**: Proxy ist `final readonly`.

### Sub-Shortcode-Module (T12-T15)

- **T12**: `DHPS_MAES_Modules::render_videos` rendert ohne `$data` im
  Template-Scope.
- **T13**: dito `render_merkblaetter` + `render_aktuelles`.
- **T14**: `DHPS_Steuertermine::render_template` rendert ohne `$data`-Var
  ueberschreibt. Lokale Variable `$months` korrekt.
- **T15**: Sub-Shortcode-JSON-Responses unveraendert.

### Stage-Smoke (T16-T20)

- **T16**: Page 6 dhps-Klassen = 76 bytewise.
- **T17**: Page 7 dhps-Klassen = 9 bytewise.
- **T18**: Page 8 dhps-Klassen = 35 bytewise.
- **T19**: Steuertermine-Standalone-Page = bytewise.
- **T20**: debug.log: keine neuen Fatals, NUR deprecation_log-Eintraege bei
  alten Theme-Overrides (kein Plugin-internes Theme-Override aktiv ->
  expected: 0 deprecation-Logs auf Stage).

Total **20/20 Target**.

## Risiken Top-3

### R1 - Theme-Override-Brueche (MITTEL)

**Was**: Live-Sites mit eigenen `{theme}/dhps/services/{service}/{layout}.php`
Overrides die `$data` lesen brechen.

**Mitigation**:

- Option B mit Deprecation-Proxy laesst die Theme-Overrides weiter
  funktionieren + loggt Deprecation
- MIGRATION-Doku detailliert (siehe TD-Phase-D)
- v0.19.0 als MAJOR-Tag signalisiert BC-Bruch klar
- Stage-Smoke kann Theme-Overrides nicht catchen - User-Live-Test-Empfehlung
  vor Promotion

### R2 - $service_tag-Sicherheit bei Mehrfach-Adapter-Services (NIEDRIG)

**Was**: `$collection->service` bei MMB-Adapter (registriert fuer mmb+mil) -
liefert es das Wrapper-Service-Tag oder das Item-Service?

**Mitigation**: Pipeline patcht `$parsed_data['service_tag'] = $tag` VOR
Adapter (Z. 133 class-dhps-content-pipeline.php). Adapter setzt
Collection-Service ueber den ihm uebergebenen `$tag`-Param (siehe
`DHPS_MMB_Adapter::adapt( $parsed_data, $service_tag )`). **Collection->service
ist verlaesslich der Wrapper-Tag** (mmb fuer [mmb], mil fuer [mil]).

Lead muss aber bestaetigen via:

```bash
grep "new DHPS_Content_Collection" includes/class-dhps-mmb-adapter.php
```

Erwartung: `new DHPS_Content_Collection( $service_tag, ... )` (NICHT
hartcodiert `'mmb'`).

### R3 - DHPS_Deprecated_Data_Proxy-Edge-Cases (NIEDRIG-MITTEL)

**Was**: Theme-Override macht `array_keys( $data )` oder serialisiert
`json_encode( $data )` -> Proxy muss Array-Cast korrekt unterstuetzen.

**Mitigation**:

- Proxy implementiert `ArrayAccess + Countable + IteratorAggregate`
- `to_array()`-Methode fuer expliziten Cast (deprecated_log)
- `__toString()` kein Sinn - leerer String + log
- Tests T7-T11 decken die Edge-Cases ab
- Pattern bewaehrt aus PHP-Library-Land (Doctrine, Symfony)

## Aufwand-Schaetzung

| Phase | Effort |
|-------|--------|
| P1 Renderer + $service_tag-Scope | XS |
| P2 Deprecated-Proxy + Tests | S |
| P3 Sub-Shortcode-Module | XS |
| P4 MAES-Orchestrator-Empty-Guards | XS |
| P5 Steuertermine $data-Rename | XS |
| P6 Doc-Blocks-Update (~12 Stellen) | S |
| P7 MIGRATION + CHANGELOG | S |
| P8 Tests + Stage-Smoke | M |
| **Total** | **M** |

Pure-Lead-Direct, ~50 LOC Code + ~50 LOC Proxy-Klasse + ~200 LOC Tests +
~250 Zeilen Doku.

## Schema-Vertrag-Vorgehen

**18. (Discovery) + 19. (Implementation) Iteration**. v0.19.0 ist die letzte
strukturelle Aenderung im DTO-Foundation-Cycle (v0.17.0 bis v0.19.0).

Pattern bewaehrt:

- Discovery -> Plan -> Schema-Vertrag -> Lead-Direct -> Stage-Smoke
- 0 Adapter/Parser/Pipeline-Aenderungen
- BC additiv fuer Site-Owner, BC-Bruch dokumentiert fuer Theme-Overrides
- MIGRATION.md mit konkreten Pre/Post-Beispielen

## Bilanz-Erwartung v0.19.0

- **22 Templates** lesen nur noch `$collection` + `$service_tag` (kein
  `$data` mehr im Scope)
- **1 neue Klasse** `DHPS_Deprecated_Data_Proxy` (Option B, optional)
- **1 Renderer-Signatur** intern verbessert ($data wird Proxy oder raus)
- **Sub-Shortcode-Modules** sauberer (kein Variable-Leak)
- **MAJOR-Version** markiert das Ende der DTO-Foundation-Aera
- **Theme-Override-Migration** in MIGRATION.md dokumentiert
- **0 BC-Bruch** fuer Site-Owner (HTML-Render unveraendert)
- **Schema-Vertrag-Vorgehen 18x in Folge** ohne Critical-Drift erwartet

## Naechste Optionen nach v0.19.0

| Option | Scope |
|--------|-------|
| **v0.19.1** | Collection-Sort-Hooks (`sort_by_date_iso`, `sorted_by`) + Hard-Aus von $data-Proxy (falls Option B) |
| **v0.20.0** | Theme-Override-Modernisierungs-Kit / Component-System v2 |

## Antwort auf die Architekt-Frage

> Welcher Subset der Templates liest noch $data? Wie gross ist der echte
> Migrations-Bedarf?

**Subset ist sehr klein**:

- **10 Templates** lesen `$data['service_tag']` als String-Lookup
  (austauschbar via `$collection->service` ODER neuer direkter `$service_tag`-
  Scope-Variable)
- **3 MAES-Orchestrator-Templates** lesen `$data['videos'/'merkblaetter'/'news']`
  als Empty-Guards (austauschbar via `$collection->filter(...)`)
- **4 Steuertermine-Templates** recyceln `$data` als lokale Variable (reine
  Umbenennung)
- **0 Templates** lesen Service-spezifische Datenfelder ueber `$data`

**Echter Migrations-Bedarf in Plugin-Templates**: 0 wenn Renderer
`$service_tag` direkt im Scope reicht (Empfehlung). 12 Templates wenn nicht.

**Theme-Override-Bedarf**: nicht messbar (Black-Box). MIGRATION.md mit
Mapping-Tabelle deckt die wichtigsten Read-Pfade ab.

**Resultat**: v0.19.0 ist ein "klein-im-Code, gross-im-Vertrag" Release.
Code-Footprint < 100 LOC + Doku, BC-Bruch klar markiert via MAJOR-Tag und
MIGRATION.md.
