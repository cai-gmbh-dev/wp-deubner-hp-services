# Data-Hard-Aus + Collection-Sort-Hook Plan v0.19.1

## Stand: 2026-06-08 (Discovery-Phase, 20. Schema-Vertrag-Iteration)

## Mission

Zwei strategische Bloecke, geschnitten als 1 Release:

- **TD-Phase-A (Hard-Aus)**: `$data`-Variable raus aus Template-Scope.
  v0.19.0 hat einen `DHPS_Deprecated_Data_Proxy` als 1-Release-Migrations-
  Fenster eingebaut. v0.19.1 entfernt das Symbol vollstaendig und schliesst
  damit den Migrations-Pfad ab.
- **TD-Phase-B (Sort-Hook)**: `DHPS_Content_Collection::sorted_by(...)` +
  Convenience-Wrapper `sort_by_date_iso()`. Nutzt das `meta.date_iso`-
  Beimaterial-Feld aus v0.18.1, das aktuell von 0 Konsumenten gelesen wird -
  Forward-Looking-API fuer Theme-/Plugin-Entwickler.

## Status-Check post-v0.19.0

| Komponente | Status |
|------------|--------|
| `DHPS_Deprecated_Data_Proxy` | da, ~210 LOC |
| Renderer setzt `$service_tag = $tag` direkt | da |
| Renderer setzt `$data = new Proxy(...)` | da (Theme-BC-Anker) |
| 22 Plugin-Templates | LESEN TEILS NOCH `$data['service_tag']` (!) |
| 4 Steuertermine-Templates | recyceln `$data = $rebuilt;` lokal |
| 3 MAES-Orchestrator-Templates | lesen `$data['videos'/'merkblaetter'/'news']` + `service_tag` |
| `meta.date_iso` in 3 Adaptern | gesetzt (MIO, TP, TPT), 0 Konsumenten |
| Adapter setzen `Collection->service` korrekt aus `$service`-Param | bestaetigt (MMB-Adapter Z. 185) |

## Sektion 1: $data-Usage-Verifikation (KRITISCHER BEFUND)

`grep '\$data\[' public/views/`-Live-Lauf liefert **18 Treffer** in
Plugin-Code (nicht nur Doc-Blocks):

### Klasse A1: $data['service_tag']-Reads (10 aktive Reads)

| Template | Zeile | Code |
|----------|-------|------|
| `services/mio/default.php` | 53 | `$service_tag = $data['service_tag'] ?? 'mio';` |
| `services/mio/card.php` | 42 | dito |
| `services/mio/compact.php` | 33 | dito |
| `services/mmb/default.php` | 41 | `$service_tag = $data['service_tag'] ?? 'mmb';` |
| `services/mmb/card.php` | 37 | dito |
| `services/mmb/compact.php` | 34 | dito |
| `services/tp/default.php` | 54 | `$service_tag = $data['service_tag'] ?? 'tp';` |
| `services/tp/card.php` | 46 | dito |
| `services/tp/compact.php` | 27 | dito |
| `services/maes/default.php`/`card.php`/`compact.php` | 36/23/23 | dito (`?? 'maes'`) |

Das ist ein **Migrations-Restbestand aus v0.19.0**: Renderer setzt
`$service_tag = $tag;` zwar bereits VOR `include`, aber die Templates
ueberschreiben diese saubere Variable mit einem Proxy-Lookup. Bei jedem
Render feuert `DHPS_Deprecated_Data_Proxy::offsetGet('service_tag')` -
nur einmal pro Service-Tag, aber feuert. WP_DEBUG-Notice + error_log.

**v0.19.0-Claim "0 Plugin-Template-Touches" war ueberbreit gefasst** -
Plugin-Templates triggern die Proxy-Notice selbst.

### Klasse A2: MAES-Orchestrator-Empty-Guards (9 weitere $data-Reads)

| Template | Zeilen | Reads |
|----------|--------|-------|
| `services/maes/default.php` | 33-36 | `$videos`/`$merkblaetter`/`$news`/`$service_tag` |
| `services/maes/card.php` | 20-23 | dito |
| `services/maes/compact.php` | 20-23 | dito |

Aus diesen werden `! empty( $videos )`/`! empty( $news )` als Empty-Guards
gelesen. Sub-Templates iterieren bereits ueber Collection - nur diese
3 Orchestrator-Templates haengen am `$data`-Pfad.

### Klasse B: Steuertermine-Templates (Variable-Recycling, kein Proxy-Read)

| Template | Code | Verhalten |
|----------|------|-----------|
| `steuertermine/default.php` | Z. 28 `$data = $rebuilt;` Z. 31+35 | rebuilded lokal, dann `foreach ( $data ... )` |
| `steuertermine/card.php` | dito | dito |
| `steuertermine/compact.php` | dito | dito |
| `steuertermine/inline.php` | dito | dito |

Diese 4 Templates lesen `$data` zu Beginn NICHT - sie ueberschreiben
die Variable mit dem lokalen `$rebuilt`-Array und iterieren danach.
**Aber `$rebuilt` ist anfangs `[]`** -> wenn Collection leer ist, bleibt
`$data` der Proxy. Die `count( $data )`-Aufrufe in default.php/card.php
wuerden dann Notice triggern.

Pragmatischer v0.19.0-Ansatz: Steuertermine-Renderer setzt `$data = $tax_dates`
DIREKT (kein Proxy) -> `count( $data )` ist sicher.

In v0.19.1 (Hard-Aus): Variable umbenennen zu `$months`.

### Klasse C: TPT/TC Templates (nur Doc-Block-Mentions, kein Code-Read)

3 TPT + 3 TC Templates erwaehnen `$data['tpt_config']`/`$data['html']` nur
im Header-Doc-Block. Code liest `$collection->get_meta(...)`. Bei
Hard-Aus reine Doku-Bereinigung.

### Stolperstein-Map

| Stolperstein | Wo | Hard-Aus-Impact |
|--------------|-----|------------------|
| Plugin-Template-Reads `$data['service_tag']` | 12 Templates | **muss vorher migriert werden** (siehe Sektion 4) |
| Plugin-Template MAES-Empty-Guards | 3 Templates | **muss migriert werden** (Collection-Filter) |
| Steuertermine `$data = $rebuilt; foreach` | 4 Templates | Variable-Rename `$data` -> `$months` |
| TPT/TC Doc-Block | 6 Templates | reine Doku-Bereinigung |
| `videos.php` Doc-Block + Kommentar | 1 Template | Doku-Bereinigung |
| Theme-Overrides (Black-Box) | unmessbar | MIGRATION.md, Beta-Channel-Window |

## Sektion 2: Strategie-Empfehlung (A/B/C/D)

### Empfehlung: Option A (Hard-Bruch + Variable raus)

| Option | Kurz | Verdikt |
|--------|------|---------|
| **A) Komplettes Removal** | `$data` ist nicht mehr im Scope. Theme-Overrides die `$data['x']` lesen -> `Undefined variable $data` Notice + null. | **EMPFOHLEN** |
| B) Empty-Array-Default | `$data = []` im Scope. Theme-Reads -> null aus offsetGet. | Verworfen (verlaengert Tech-Debt indefinitely) |
| C) Final-Notice-Proxy | $data bleibt Proxy aber Notice wird FATAL-Throw. | Verworfen (aggressive UX-Bruch ohne Mehrwert) |
| D) Renderer-Scope ohne Var-Name | $data wird einfach nicht extracted. | == Option A (Notice-Verhalten identisch) |

**Begruendung Option A**:

1. v0.19.0 hat die Migrations-Doku + Proxy + 1-Release-Fenster bereits
   bereitgestellt. Versprechen einhalten.
2. v0.19.0 ist als MAJOR markiert - Theme-Override-BC-Bruch ist erwartet,
   nicht versteckt.
3. Option B verlaengert Tech-Debt um 1 Release-Cycle ohne Mehrwert -
   Theme-Entwickler, die die WP_DEBUG-Notice ignoriert haben, ignorieren auch
   ein Empty-Array. Sauberer ist der harte Schnitt.
4. PHP-Notice + `null` ist diagnostisch besser als stille leere Render-
   Stelle (Option B). User-Feedback "Mein Override rendert nichts" ist
   weniger informativ als "Undefined variable $data".

**Trade-off**: User-Live-Sites mit ungetesteten Theme-Overrides koennen
Render-Brueche zeigen. Mitigation: Beta-Channel-Promotion (v0.16.0-Infra)
+ MIGRATION-Doku + Stage-Smoke-Hinweis.

## Sektion 3: Collection-Sort-Hook Schema-Vertrag

### Empfehlung: B.2 (generisches `sorted_by` + Convenience `sort_by_date_iso`)

```php
final class DHPS_Content_Collection {

    /**
     * Sortiert die Collection und liefert eine NEUE Instanz.
     *
     * Sort ist stable (PHP 8.0+ usort/uasort sind stable).
     *
     * @since 0.19.1
     *
     * @param callable|string $key_or_callable
     *   String: Item-Feld-Name aus Whitelist {'title','category','type','service'}
     *           ODER `meta.{key}` fuer Item-Meta-Zugriff (z.B. 'meta.date_iso')
     *   Callable: signature (DHPS_Content_Item $a, DHPS_Content_Item $b): int
     *             - Standard usort-Vergleichs-Funktion.
     * @param string $direction 'asc' (default) | 'desc'.
     *
     * @return self Neue Collection. Service+Meta+Item-Identitaeten unberuehrt.
     *
     * @throws InvalidArgumentException
     *   Wenn $key_or_callable ein String ist und nicht in der Whitelist liegt,
     *   ODER wenn $direction nicht 'asc'/'desc' ist.
     */
    public function sorted_by(
        callable|string $key_or_callable,
        string $direction = 'asc'
    ): self;

    /**
     * Convenience-Wrapper: sortiert nach meta.date_iso (YYYY-MM-Strings).
     *
     * Items ohne meta.date_iso landen je nach $direction am Ende (asc) oder
     * am Ende (desc) - Items ohne Beimaterial werden semantisch wie
     * "Datum unbekannt" behandelt und ans Ende sortiert (siehe Sektion 5).
     *
     * @since 0.19.1
     *
     * @param string $direction 'asc' (default, oldest first) | 'desc' (newest first).
     *
     * @return self Neue Collection.
     */
    public function sort_by_date_iso( string $direction = 'asc' ): self;
}
```

### Schema-Vertrag

- **Return**: NEUE Collection-Instanz (Immutable-Pattern, identisch zu
  `filter`, `group_by`, `add`).
- **Sort-stable**: PHP 8.0+ usort garantiert stabile Sortierung.
- **Items ohne date_iso**: landen **am Ende** unabhaengig von Direction
  (Begruendung Sektion 5).
- **Service+Meta**: bleiben unangetastet, nur Item-Reihenfolge aendert
  sich.
- **String-Lookup-Whitelist** fuer `sorted_by($string)`:
  - `'title'` -> `$item->title`
  - `'category'` -> `$item->category` (null sortiert ans Ende)
  - `'type'` -> `$item->type`
  - `'service'` -> `$item->service`
  - `'meta.{key}'` -> `$item->meta[$key]` (null sortiert ans Ende)
- **Direction-Whitelist**: `'asc'` | `'desc'`. Default `'asc'`.
- **Throw**: `InvalidArgumentException` bei unerlaubtem String-Key oder
  unerlaubter Direction (defensive, sieht InvalidArgumentException-Pattern
  aus `group_by` als Vorlage).

### Konstanten

```php
public const ALLOWED_SORT_KEYS = array( 'title', 'category', 'type', 'service' );
public const ALLOWED_SORT_DIRECTIONS = array( 'asc', 'desc' );
public const META_KEY_PREFIX = 'meta.';
```

## Sektion 4: $data-Param-Removal-Konkretes

### Schritt 1: Renderer aufraeumen

`includes/class-dhps-renderer.php` Z. 138-186:

```php
// alt (v0.19.0):
public function render_parsed( array $data, string $tag, string $layout = 'default', string $css_class = '', ?DHPS_Content_Collection $collection = null ): string {
    ...
    $service_tag = $tag;
    if ( class_exists( 'DHPS_Deprecated_Data_Proxy' ) ) {
        $data = new DHPS_Deprecated_Data_Proxy( $data, $tag, $layout );
    }
    ob_start();
    include $template_file;   // <-- $data ist Proxy im Scope
    ...
}

// neu (v0.19.1, Option A):
public function render_parsed( array $data, string $tag, string $layout = 'default', string $css_class = '', ?DHPS_Content_Collection $collection = null ): string {
    ...
    $service_tag = $tag;
    // v0.19.1 MAJOR Hard-Aus: $data wird NICHT mehr im Template-Scope
    // gesetzt. Theme-Overrides die `$data` lesen, bekommen PHP-Notice.
    unset( $data );
    ob_start();
    include $template_file;
    ...
}
```

Begruendung `unset($data)`: Der Param heisst `array $data` und ist standardmaessig
nach `extract`-ish includes im Scope sichtbar. Da der include hier direkt
im Scope der Methode laeuft, ist `$data` automatisch im Scope. `unset()`
entfernt das explizit.

Alternative: `array $data` Param BLEIBT in Signatur (BC fuer
externe `$renderer->render_parsed( $data, 'mio', ... )`-Aufrufer), aber
intern `unset()` direkt nach Eingangs-Validierung.

### Schritt 2: 12 Plugin-Templates `$data['service_tag']` -> `$service_tag` direkt

`$service_tag` ist bereits im Scope (vom Renderer gesetzt, v0.19.0). Die
12 Templates muessen nur die Zeile `$service_tag = $data['service_tag'] ??
'xxx';` ENTFERNEN. Die Variable ist schon richtig gesetzt.

Defensive Bytewise-BC: vorher `$data['service_tag'] ?? 'mmb'` (Fallback
bei fehlendem Schluessel), nachher `$service_tag = $tag` (Renderer-Param).
Bei MMB-Pipeline ist `$tag = 'mmb'` -> Identitaet ist gewahrt. Falls
externer Aufrufer `render_parsed($data, '')` macht, bekommt er leer-String -
das ist heute bereits ein Edge-Case und nicht supported.

```php
// in 12 Templates:
- $service_tag    = $data['service_tag'] ?? 'mmb';
+ // $service_tag wird seit v0.19.0 vom Renderer direkt im Scope gesetzt.
```

ACHTUNG: bei MAES-Sub-Shortcodes setzt `DHPS_MAES_Modules::render_videos`
auch `$service_tag = 'maes'` direkt (v0.19.0). Identitaet bleibt.

### Schritt 3: 3 MAES-Orchestrator-Templates Empty-Guards umbauen

`services/maes/default.php` Z. 33-36:

```php
// alt:
$videos       = $data['videos'] ?? array();
$merkblaetter = $data['merkblaetter'] ?? array();
$news         = $data['news'] ?? array();
$service_tag  = $data['service_tag'] ?? 'maes';

// neu:
$collection = dhps_collection_or_empty( $collection, 'maes' );

$has_videos       = $collection->filter( static fn( $item ) => 'video' === $item->type )->count() > 0;
$has_merkblaetter = $collection->filter( static fn( $item ) => 'document' === $item->type )->count() > 0;
$has_aktuelles    = $collection->filter( static fn( $item ) => 'news' === $item->type )->count() > 0;
```

Templates lesen dann `! empty( $videos )` -> `$has_videos`. 2x ersetzen
pro Template. Sub-Templates lesen weiter aus $collection (unveraendert).

ACHTUNG: MAES-Adapter mappt Aktuelles auf `type='news'`? Im Discovery-Plan
37 wurde "ja, news ist seit v0.18.2 produktiv" angenommen. Lead muss via
`grep "'news'" includes/class-dhps-maes-adapter.php` bestaetigen vor
Implementation.

### Schritt 4: Steuertermine 4 Templates Variable-Rename

`steuertermine/default.php` Z. 27-31:

```php
// alt:
if ( ! empty( $rebuilt ) ) {
    $data = $rebuilt;
}

$grid_modifier = ( 1 === count( $data ) ) ? ' dhps-termine__grid--single' : '';
?>
    ...
    <?php foreach ( $data as $month ) : ?>

// neu:
$months = $rebuilt;

$grid_modifier = ( 1 === count( $months ) ) ? ' dhps-termine__grid--single' : '';
?>
    ...
    <?php foreach ( $months as $month ) : ?>
```

4 Templates: `default.php`, `card.php`, `compact.php`, `inline.php`. Alle
identisches Rename-Pattern (10 Stellen total inkl. `count`, `foreach`,
`$rebuilt`-Assignment).

Steuertermine::render_template muss `$data = $tax_dates;` ebenfalls
entfernen - kein Bedarf mehr fuer die Template-Scope-Variable.

### Schritt 5: 6 TPT/TC + 1 MAES `videos.php` Doc-Block-Bereinigung

TPT- und TC-Doc-Blocks erwaehnen `$data` als Template-Var. Header-Kommentar
in 7 Templates updaten (Such-und-Ersetz: `$data` -> `$collection`).

### Schritt 6: DHPS_Deprecated_Data_Proxy entfernen

`includes/class-dhps-deprecated-data-proxy.php` komplett **loeschen** + Bootstrap-
Require entfernen (falls vorhanden).

### Schritt 7: DHPS_MAES_Modules-Patches

3 render_*-Methoden: `$data = new DHPS_Deprecated_Data_Proxy( ... )` Zeile
ENTFERNEN. Variable nicht mehr setzen.

```php
// alt:
if ( class_exists( 'DHPS_Deprecated_Data_Proxy' ) ) {
    $data = new DHPS_Deprecated_Data_Proxy( $filtered_data, 'maes', $layout );
}

ob_start();
include $template;

// neu:
ob_start();
include $template;
```

3 Stellen.

### Schritt 8: DHPS_Steuertermine::render_template aufraeumen

```php
// alt:
$data         = $tax_dates;
$custom_class = ! empty( $css_class ) ? ' ' . $css_class : '';
$service_tag  = 'mio';
ob_start();
include $template;

// neu:
$custom_class = ! empty( $css_class ) ? ' ' . $css_class : '';
$service_tag  = 'mio';
ob_start();
include $template;
```

Eine Zeile raus.

## Sektion 5: Tests T1-T22

### Renderer-Layer (T1-T4)

- **T1**: `DHPS_Renderer::render_parsed([], 'mio', ...)` -> Template
  Scope hat `$collection`, `$service_class`, `$layout_class`, `$custom_class`,
  `$service_tag`. **`isset( $data )` ist false** im Template-Scope.
- **T2**: Template das `$data['service_tag']` liest -> PHP-Notice
  "Undefined variable $data" + Wert ist null.
- **T3**: Plugin-Template (z.B. mmb/default.php) rendert ohne Notice
  (Migration in Schritt 2 abgeschlossen).
- **T4**: `$service_tag` ist verlaesslich `'mil'` bei [mil]-Shortcode,
  `'lp'` bei [lp], etc. (Multi-Adapter-Service-Tags via Pipeline-Patch).

### Plugin-Template-BC (T5-T10)

- **T5**: Page 6 dhps-Klassen bytewise identisch zu v0.19.0 (Stage-Smoke).
- **T6**: Page 7 bytewise.
- **T7**: Page 8 bytewise.
- **T8**: Steuertermine-Standalone bytewise.
- **T9**: MAES-Orchestrator (videos/merkblaetter/aktuelles) zeigt korrekt
  conditional je Section.
- **T10**: debug.log clean (0 Deprecation-Notices, 0 Undefined-Variable-
  Notices in Plugin-Pfaden).

### Proxy-Removal (T11-T12)

- **T11**: `class_exists( 'DHPS_Deprecated_Data_Proxy' )` -> false.
- **T12**: keine include-Calls auf `class-dhps-deprecated-data-proxy.php`
  mehr im Bootstrap-Pfad.

### Collection-Sort-Hook (T13-T22)

- **T13**: `Collection::sorted_by( 'title', 'asc' )` sortiert
  alphabetisch aufsteigend.
- **T14**: `Collection::sorted_by( 'title', 'desc' )` sortiert absteigend.
- **T15**: `Collection::sorted_by( 'invalid_key' )` -> InvalidArgumentException.
- **T16**: `Collection::sorted_by( 'asc' )` als Direction-typo am Anfang ->
  Whitelist-Throw bei String-Lookup (kein silent-Fail).
- **T17**: `Collection::sorted_by( fn($a,$b) => $a->title <=> $b->title )` ->
  Custom-Callable-Pfad funktioniert.
- **T18**: `Collection::sorted_by( 'meta.date_iso' )` sortiert nach ISO-
  YYYY-MM-String aufsteigend (String-Vergleich, lexikographisch ist
  chronologisch korrekt fuer ISO-Format).
- **T19**: `Collection::sort_by_date_iso()` Convenience funktioniert
  identisch zu `sorted_by('meta.date_iso')`.
- **T20**: `Collection::sort_by_date_iso('desc')` reversed Reihenfolge.
- **T21**: Items ohne `meta.date_iso` landen **am Ende** unabhaengig von
  Direction. Reihenfolge der no-date-Items zueinander bleibt stable
  (PHP 8.0+ usort).
- **T22**: `Collection::sorted_by(...)` liefert NEUE Instanz - Original-
  Collection ist unveraendert (`spl_object_id` Vergleich).

**Total Target: 22/22**.

### Sektion 5.1: Sort-Direction-Implementation

```php
public function sorted_by( callable|string $key_or_callable, string $direction = 'asc' ): self {
    if ( ! in_array( $direction, self::ALLOWED_SORT_DIRECTIONS, true ) ) {
        throw new InvalidArgumentException( sprintf(
            'DHPS_Content_Collection::sorted_by(): direction "%s" nicht erlaubt (asc|desc).',
            $direction
        ) );
    }

    if ( is_string( $key_or_callable ) ) {
        $comparator = $this->build_string_key_comparator( $key_or_callable, $direction );
    } else {
        $comparator = $key_or_callable;
        // Direction wird bei Callable IGNORIERT - Callable ist self-direction.
        // (Doc-Block warnt: Callable bestimmt Direction selbst.)
    }

    $sorted = $this->items;
    usort( $sorted, $comparator );

    return new self( $this->service, $sorted, $this->meta );
}
```

`build_string_key_comparator()` (private):

```php
private function build_string_key_comparator( string $key, string $direction ): callable {
    if ( ! in_array( $key, self::ALLOWED_SORT_KEYS, true )
        && 0 !== strpos( $key, self::META_KEY_PREFIX ) ) {
        throw new InvalidArgumentException( sprintf(
            'DHPS_Content_Collection::sorted_by(): key "%s" nicht erlaubt.',
            $key
        ) );
    }

    $is_meta_key = ( 0 === strpos( $key, self::META_KEY_PREFIX ) );
    $meta_field  = $is_meta_key ? substr( $key, strlen( self::META_KEY_PREFIX ) ) : '';
    $multiplier  = ( 'desc' === $direction ) ? -1 : 1;

    return static function ( DHPS_Content_Item $a, DHPS_Content_Item $b ) use (
        $key, $is_meta_key, $meta_field, $multiplier
    ): int {
        if ( $is_meta_key ) {
            $a_val = $a->meta[ $meta_field ] ?? null;
            $b_val = $b->meta[ $meta_field ] ?? null;
        } else {
            $a_val = $a->{$key} ?? null;
            $b_val = $b->{$key} ?? null;
        }

        // Null-Werte ans Ende (auch bei desc).
        if ( null === $a_val && null === $b_val ) {
            return 0;
        }
        if ( null === $a_val ) {
            return 1;  // a ans Ende
        }
        if ( null === $b_val ) {
            return -1; // b ans Ende
        }

        return $multiplier * ( $a_val <=> $b_val );
    };
}
```

## Sektion 6: Spec-Aufteilung

### Empfehlung: Pure Lead-Direct

Begruendung:

- Pattern bekannt aus v0.19.0 (Renderer-Edit, Template-Migration).
- Scope klein:
  - Renderer: 1 Edit (`unset($data)` + Kommentar)
  - MAES_Modules: 3 Edits (Proxy-Zeile raus)
  - Steuertermine: 1 Edit (Renderer + 4 Templates Variable-Rename)
  - 12 Plugin-Templates: 1 Zeile raus pro Template
  - 3 MAES-Orchestrator-Templates: Empty-Guards umbauen
  - Proxy-Klasse: loeschen
  - Collection: ~80 LOC neue Methoden + ~5 LOC Konstanten
  - Tests: ~250 LOC
  - MIGRATION-Doku: ~150 Zeilen
- 0 Adapter/Parser/Pipeline-Aenderungen.
- 0 BC-Risiko fuer Site-Owner (HTML-Render bytewise).
- BC-Risiko fuer Theme-Overrides ist v0.19.0-angekuendigt.

**Aufwand**: S (klein), kein Specialist.

### Sub-Phasen

| Phase | Scope | LOC |
|-------|-------|-----|
| P1 Collection::sorted_by + sort_by_date_iso + Konstanten | 1 Klasse | +80 |
| P2 Renderer unset($data) + Doc-Block | 1 Datei | +/- 0 |
| P3 12 Plugin-Templates `$service_tag` Lookup raus | 12 Templates | -12 |
| P4 3 MAES-Orchestrator Empty-Guards Collection-Filter | 3 Templates | +12 |
| P5 4 Steuertermine `$data` -> `$months` Rename + Renderer-Cleanup | 5 Files | -4 |
| P6 7 Templates Doc-Block-Update | 7 Templates | Doku |
| P7 DHPS_Deprecated_Data_Proxy loeschen + MAES_Modules-Patches | 4 Files | -220 |
| P8 MIGRATION-Doku v0.19.1 + CHANGELOG + Version-Bump | 3 Files | +200 Zeilen |
| P9 Lead-Tests T1-T22 | 1 test-v0191.php | +250 |
| P10 Stage-Smoke 76+9+35 + Steuertermine-Page | manuell | - |

**Total**: +/- 0 Code-Netto (Proxy-Loeschung kompensiert Sort-Hook), ~250 LOC Tests, ~200 Zeilen Doku.

## Sektion 7: BC-Impact

### BC bleibt vollstaendig fuer Site-Owner

- HTML-Render bytewise unveraendert (alle 22 Plugin-Templates).
- AJAX-JSON-Responses unveraendert.
- Shortcode-Atts unveraendert.
- 9 Service-Tags + Adapter unveraendert.
- Pipeline unveraendert.
- `dhps_pipeline_data_{tag}`-Filter, `dhps_template_fallbacks`-Filter,
  `dhps_content_adapter_for_service`-Filter unveraendert.
- `echo $tc_html` Trust-Decision unangetastet.
- `meta.date_iso`-Beimaterial-Feld (3 Adapter) unveraendert.
- WP-Option `dhps_update_channel` + Beta-Channel-Mechanik unangetastet.
- 4 Steuertermine-Layouts (default/card/compact/inline) sehen aus wie vorher.

### BC-Bruch fuer Theme-Overrides (angekuendigt v0.19.0)

- Theme-Override liest `$data['anything']` -> "Undefined variable $data"
  Notice + null-Wert.
- `isset( $data )` -> false (vorher true).
- `is_object( $data )` -> false (vorher true).
- `$data instanceof DHPS_Deprecated_Data_Proxy` -> Klasse existiert nicht
  mehr, Notice "class not found" oder false.
- Theme-Overrides die `$collection` lesen funktionieren weiter.
- Theme-Overrides die `$service_tag` lesen funktionieren weiter
  (NEU seit v0.19.0).

### BC-Bruch fuer Plugin-Code, der Proxy-Klasse direkt nutzt

- Externes Plugin instantiiert `new DHPS_Deprecated_Data_Proxy(...)` ->
  "Class not found" Fatal.
- Vermutlich 0 externe Konsumenten (Klasse ist nur intern dokumentiert).

### Theoretischer BC-Bruch fuer Sort-Hook-Konflikte

- Wenn ein externes Plugin schon eine Methode `sorted_by` oder
  `sort_by_date_iso` auf DHPS_Content_Collection mocked/extended hat -
  unmoeglich, Klasse ist `final`.

## Sektion 8: MIGRATION-Doku-Update

`docs/team-knowledge/12-MIGRATION-v0191.md` (NEU):

Skelett:

```markdown
# Migration v0.19.0 -> v0.19.1

## TL;DR

v0.19.1 schliesst den $data-Migrations-Pfad ab: `$data` ist nicht mehr
im Template-Scope. v0.19.0 hat das 1-Release-Migrations-Fenster
bereitgestellt - in v0.19.1 ist Schluss.

Zusaetzlich: `Collection::sort_by_date_iso()` und der generische
`Collection::sorted_by()`-Hook stehen als Forward-Looking-API bereit.

## Hard-Aus $data: Theme-Override-Pflicht-Migration

### Pre-Migration (v0.19.x-Theme-Overrides)

```php
$service_tag = $data['service_tag'] ?? 'mio';   // BRICHT v0.19.1
$categories  = $data['categories'] ?? array();  // BRICHT v0.19.1
```

### Post-Migration (v0.19.1)

```php
// $service_tag ist seit v0.19.0 direkt im Scope (kein $data-Lookup noetig)
$service_tag = $service_tag ?? 'mio';

// Service-spezifische Daten via Adapter-Helper:
$collection = dhps_collection_or_empty( $collection, $service_tag );
$categories = dhps_mmb_collection_to_legacy_categories( $collection );
// oder fuer TP:
$rebuilt    = dhps_tp_collection_to_legacy_categories( $collection );
$categories = $rebuilt['categories'];
$featured   = $rebuilt['featured'];
// oder fuer MIO:
$tax_dates = array();
foreach ( $collection as $item ) {
    $legacy_month = dhps_mio_item_to_legacy_month( $item );
    if ( ! empty( $legacy_month ) ) {
        $tax_dates[] = $legacy_month;
    }
}
```

## Mapping-Tabelle (Letzte Migrations-Chance)

| alt (v0.18.x oder frueher) | neu (v0.19.1) |
|---|---|
| `$data['service_tag']` | `$service_tag` (direkt im Scope) |
| `$data['categories']` (MMB) | `dhps_mmb_collection_to_legacy_categories($collection)` |
| `$data['categories']` (TP) | `dhps_tp_collection_to_legacy_categories($collection)['categories']` |
| `$data['featured_video']` | `dhps_tp_collection_to_legacy_categories($collection)['featured']` |
| `$data['video']` (TPT) | foreach Collection + `dhps_tp_item_to_legacy_video($item)` |
| `$data['tax_dates']` | foreach Collection + `dhps_mio_item_to_legacy_month($item)` |
| `$data['html']` (TC) | `$collection->get_meta('html', '')` |
| `$data['is_empty']` (TC) | `$collection->get_meta('is_empty', true)` |
| `$data['tpt_config']` | `$collection->get_meta('tpt_config', [])` |
| `$data['search_config']` | `$collection->get_meta('search_config', [])` |
| `$data['videos']` (MAES) | `$collection->filter(fn($i) => $i->type === 'video')` |
| `$data['merkblaetter']` (MAES) | `$collection->filter(fn($i) => $i->type === 'document')` |
| `$data['news']` (MAES) | `$collection->filter(fn($i) => $i->type === 'news')` |
| `isset($data)` | `isset($collection)` |
| `$data instanceof DHPS_Deprecated_Data_Proxy` | Klasse existiert nicht mehr -> false |

## Neue API: Collection-Sort-Hook

### sort_by_date_iso (Convenience fuer Beimaterial-Datum)

```php
// MIO/TP/TPT-Items haben meta.date_iso (YYYY-MM) seit v0.18.1
$sorted = $collection->sort_by_date_iso( 'desc' );  // neueste zuerst

foreach ( $sorted as $item ) {
    // Item-Order ist jetzt chronologisch absteigend
}
```

### sorted_by (Generisch)

```php
// Alphabetisch nach Titel
$sorted = $collection->sorted_by( 'title' );
$sorted = $collection->sorted_by( 'title', 'desc' );

// Nach Item-Meta-Feld
$sorted = $collection->sorted_by( 'meta.date_iso' );
$sorted = $collection->sorted_by( 'meta.custom_priority' );

// Custom-Callable
$sorted = $collection->sorted_by(
    fn( $a, $b ) => strnatcmp( $a->title, $b->title )
);
```

### Use-Case-Beispiel: TP-Videos chronologisch

```php
// Template-Override: TP-Videos nach Datum absteigend
add_filter( 'dhps_pipeline_data_tp', function( $data ) {
    // Hier ist $data noch das Array vor dem Adapter - das alte Filter-Pattern.
    return $data;
} );

// Im Template:
$collection = dhps_collection_or_empty( $collection, 'tp' );
$sorted_by_date = $collection->sort_by_date_iso( 'desc' );
foreach ( $sorted_by_date as $item ) {
    // Anzeige
}
```

### Schema-Vertrag

| Methode | Signatur | Notes |
|---------|----------|-------|
| `sorted_by` | `(callable\|string $key_or_callable, string $direction = 'asc'): self` | Liefert NEUE Collection |
| `sort_by_date_iso` | `(string $direction = 'asc'): self` | Wrapper auf `sorted_by('meta.date_iso', $direction)` |

Erlaubte String-Keys: `title`, `category`, `type`, `service`, `meta.*`.
Erlaubte Directions: `asc`, `desc`.
Items ohne Sort-Wert (null) landen am Ende.

## Rollback bei Problemen

```bash
wp plugin update wp-deubner-hp-services --version=0.19.0
```
```

## Sektion 9: Schema-Vertrag-Vorgehen 20. Iteration

| Iteration | Release | Modifikation | Critical-Drift |
|-----------|---------|--------------|----------------|
| 1 | v0.17.0 | DTO-Foundation + MAES-Pilot | 0 |
| 2 | v0.17.1 | MMB-Adapter + Sub-Shortcodes-Bridge | 0 |
| 3 | v0.17.2 | TP/TPT/LP-Adapter | 0 |
| 4 | v0.17.3 | MIO/LXMIO-Adapter | 0 |
| 5 | v0.17.4 | TC-Adapter | 0 |
| 6 | v0.17.5 | Tech-Debt-Cleanup Tranche | 0 |
| 7 | v0.18.0 | Legacy-Pfad raus | 0 |
| 8 | v0.18.1 | Datum-Normalisierung Beimaterial | 0 |
| 9 | v0.18.2 | AJAX-Migration MMB+MIO-News Side-Channels | 0 |
| 10 | v0.18.3 | Polish + extra_meta-Param | 0 |
| 11 | v0.19.0 | Deprecated-Data-Proxy + $service_tag | 0 |
| 12 (Discovery) | v0.19.1 | Hard-Aus $data + Sort-Hook | TBD |

**Pattern bewaehrt 11 Iterationen ohne Critical-Drift**. Discovery 38 ist
die 12. Iteration. Discovery -> Plan -> Schema-Vertrag -> Lead-Direct ->
Stage-Smoke + Tests T1-T22.

## Sektion 10: Spec-Briefing

### Empfehlung

- **Option A (Hard-Bruch + Variable raus)** fuer Phase A
- **B.2 (Generic + Convenience)** fuer Phase B
- **Pure Lead-Direct** (kein Specialist)
- **Aufwand S** (klein), ~80 LOC Code netto + 250 LOC Tests + 200 Zeilen Doku

### Sub-Phasen-Reihenfolge (Lead-Direct)

```
P1: Collection::sorted_by + sort_by_date_iso + Konstanten + Tests T13-T22
P2: 12 Plugin-Templates `$data['service_tag']`-Zeile raus
P3: 3 MAES-Orchestrator Empty-Guards via Collection-Filter
P4: 4 Steuertermine-Templates `$data` -> `$months` Rename + Renderer-Cleanup
P5: Renderer unset($data) + 3 MAES_Modules-Patches + Doc-Block-Update
P6: DHPS_Deprecated_Data_Proxy.php loeschen
P7: 7 Templates Doc-Block-Update (TPT/TC/MAES videos.php)
P8: MIGRATION + CHANGELOG + Version-Bump
P9: Lead-Tests + Stage-Smoke
```

Reihenfolge wichtig:

- P2-P5 muessen VOR P6 fertig sein, sonst feuert Notice-Lawine.
- P1 kann zeitlich parallel, weil orthogonal zum Hard-Aus-Block.
- P9 als Letztes (Stage-Smoke nach allen Code-Patches).

### Lead-Smoke-Test-Skelett

`test-v0191.php` im Plugin-Root, executed via:

```bash
docker exec wp-deubner-hp-services-wordpress-1 php /var/www/html/wp-content/plugins/wp-deubner-hp-services/test-v0191.php
```

22 Tests in 4 Gruppen: Renderer (T1-T4), Plugin-Template-BC (T5-T10),
Proxy-Removal (T11-T12), Collection-Sort-Hook (T13-T22). 22/22 Target.

### Top-3-Risiken

#### R1 - Theme-Override-Brueche (MITTEL)

**Was**: Live-Sites mit eigenen `{theme}/dhps/services/{x}/{layout}.php`
Overrides die `$data['x']` lesen brechen ab v0.19.1.

**Mitigation**:

- v0.19.0 hatte WP_DEBUG-Notice + 1-Release-Fenster vorbereitet
- MIGRATION-Doku v0.19.1 mit konkreter Mapping-Tabelle
- Beta-Channel-Promotion (v0.16.0-Infra) erlaubt Vor-Tests durch Site-Owner
- Stage-Smoke kann Theme-Overrides nicht catchen - User-Live-Test-Empfehlung
  vor Stable-Promotion explizit in Release-Notes

#### R2 - MAES-Empty-Guards Filter-Mismatch (NIEDRIG-MITTEL)

**Was**: Discovery 37 hat angenommen, dass `$has_videos` via
`$collection->filter(fn => $item->type === 'video')` aequivalent zu
`! empty( $data['videos'] )` ist. Wenn MAES-Adapter Aktuelles auf einen
**anderen** type als `'news'` mapped, brechen die 3 Orchestrator-
Templates.

**Mitigation**:

- Lead muss vor P3 verifizieren:
  ```bash
  grep -n "'news'\|'video'\|'document'" includes/class-dhps-maes-adapter.php
  ```
- Erwartung: Adapter setzt diese Item-Types explizit (analog MMB-Adapter).
- Falls Mismatch: Item-Type-Konsolidierung VOR P3 (eigener Mini-Patch im
  Adapter), oder Filter umstellen auf das tatsaechlich gemappte Type.
- Stage-Smoke Page 7 (vermutlich MAES-Seite) deckt das ab.

#### R3 - Sort-Hook null-Sortier-Semantik (NIEDRIG)

**Was**: Items ohne `meta.date_iso` landen "am Ende" - was wenn ein
User in beide Richtungen sortiert? Bei `desc` waeren die null-Items
"unten" (am wenigsten relevant) - das ist semantisch korrekt fuer
"chronologisch absteigend". Bei `asc` waeren die null-Items "am Ende"
nach den datierten - das ist umstritten (User koennte erwarten "ohne
Datum = am Anfang").

**Mitigation**:

- Doc-Block warnt explizit "null-Items landen am Ende unabhaengig von
  Direction"
- Test T21 deckt das ab und dokumentiert die Semantik
- Alternative API in v0.19.2 falls User-Feedback negativ: Param
  `$null_position = 'last'|'first'`

## Bilanz-Erwartung v0.19.1

- **Hard-Aus $data**: kein Symbol im Template-Scope mehr.
- **DHPS_Deprecated_Data_Proxy.php geloescht** (~210 LOC raus).
- **Collection::sorted_by + sort_by_date_iso neu** (~80 LOC + Tests).
- **22 Plugin-Templates lesen nur noch $collection + $service_tag + Component-Klassen**.
- **4 Steuertermine-Templates** Variable-Rename.
- **3 MAES-Orchestrator-Templates** Empty-Guards modernisiert.
- **MIGRATION-Doku v0.19.1** schliesst Theme-Override-Migrations-Pfad ab.
- **0 BC-Bruch fuer Site-Owner** (HTML-Render bytewise).
- **BC-Bruch fuer Theme-Overrides angekuendigt + dokumentiert** (v0.19.0-Fenster genutzt).
- **Schema-Vertrag-Vorgehen 20x in Folge** ohne Critical-Drift erwartet.
- **Ende der DTO-Foundation-Aera** + Forward-Looking-Sort-Hook-API.

## Naechste Optionen nach v0.19.1

| Option | Scope |
|--------|-------|
| **v0.19.2** | Polish-Sammelrelease (falls Edge-Cases auftreten) |
| **v0.20.0** | Component-System v2 / Render-Layer-Refactor / Theme-Override-Kit |

## Antwort auf die Architekt-Frage

> Sind 0 Plugin-Templates und 0 Modules-Klassen $data-Leser? Wo lauern
> Migrations-Stolpersteine fuer Theme-Entwickler?

**Befund - es ist NICHT clean**:

- **12 Plugin-Templates** lesen weiterhin `$data['service_tag']` ueber den
  Proxy (Klasse A1 oben). Das v0.19.0-Migrations-Versprechen "0
  Plugin-Template-Touches" war ueberbreit gefasst - Renderer setzt
  `$service_tag` direkt, aber Templates ueberschreiben mit Proxy-Lookup.
  Notice feuert **pro Pageload einmal** je Service-Tag.
- **3 MAES-Orchestrator-Templates** lesen `$data['videos'/'merkblaetter'/
  'news'/'service_tag']` als Empty-Guards (Klasse A2). Ebenfalls Proxy-
  Notice-Trigger.
- **4 Steuertermine-Templates** recyceln `$data` als lokale Variable.
  Steuertermine-Renderer setzt `$data` zu echten Array (nicht Proxy) -
  keine Notice, aber Variable-Konfusion. v0.19.1 Hard-Aus erfordert
  Rename zu `$months`.
- **0 TPT-Templates**, **0 TC-Templates** lesen `$data` im Code (nur
  Doc-Block-Mentions).
- **0 Modules-Klassen** lesen `$data` als Template-Scope-Var.

**Migrations-Stolpersteine fuer Theme-Entwickler**:

1. Theme-Override-Pfade `{theme}/dhps/services/{x}/{layout}.php` lesen
   Service-spezifische Felder ueber `$data` (z.B. `$data['categories']`,
   `$data['featured_video']`, `$data['tax_dates']`). Hard-Aus bricht
   diese mit "Undefined variable $data" PHP-Notice + null.
2. Theme-Overrides die `isset( $data )` oder `is_object( $data )` als
   Pseudo-Existenz-Check nutzen -> beide werden false (vorher true via
   Proxy).
3. Theme-Overrides die `$data instanceof DHPS_Deprecated_Data_Proxy`
   pruefen -> Klasse existiert nicht mehr, Fatal "Class not found" oder
   false je nach Pfad.
4. Theme-Plugins, die `DHPS_Deprecated_Data_Proxy` direkt instanziieren
   -> Fatal-Error (unwahrscheinlich, da Klasse nur intern).

**Mitigation**:

- v0.19.0 hat WP_DEBUG-Notice + MIGRATION-Doku bereitgestellt
- MIGRATION-Doku v0.19.1 mit konkreten 1:1-Mapping
- Beta-Channel-Promotion ermoeglicht Site-Owner-Vortests
- Stage-Smoke testet Plugin-Templates, NICHT Theme-Overrides (Black-Box)
- Release-Notes-Empfehlung: User-Live-Test mit eigenen Theme-Overrides
  vor Stable-Promotion

**Resultat**: v0.19.1 ist ein "klein-im-Code, gross-im-Vertrag"-Release.
Code-Footprint ~80 LOC Sort-Hook + ~30 LOC Template-Cleanup - ~210 LOC
Proxy-Loeschung = NETTO ~-100 LOC. Theme-Override-BC-Bruch ist
v0.19.0-angekuendigt und in MIGRATION-Doku abgedeckt.
