# Migration v0.19.0 -> v0.19.1 (Hard-Aus $data)

## Stand: 2026-06-08

## TL;DR

v0.19.1 erfuellt das in v0.19.0 angekuendigte Versprechen: **`$data` ist nicht mehr im Template-Scope**.
DHPS_Deprecated_Data_Proxy wurde geloescht. Theme-Overrides die noch `$data['...']` lesen, **brechen mit
PHP-Notice `Undefined variable $data`**.

Bonus: Neue **Collection-Sort-API** (`sorted_by` + `sort_by_date_iso`).

## Auswirkungen pro Konsumenten-Typ

### Anwender / Site-Owner

**Kein Handlungsbedarf.** HTML-Render bytewise unveraendert.

### Theme-Entwickler mit Template-Overrides

**WICHTIG**: Wenn dein Theme-Override `$data['...']` liest, hast du jetzt:

```
PHP Notice: Undefined variable $data in {your-theme}/dhps/services/.../default.php on line XX
```

#### Migrations-Pfad (letzte Chance!)

```
{your-theme}/dhps/services/{service}/{layout}.php
{your-theme}/dhps/steuertermine/{layout}.php
```

Mapping `$data['...']` -> v0.19.1-API:

| Vorher | Nachher (v0.19.1) |
|--------|-------------------|
| `$data['service_tag']` | `$service_tag` (direkt im Scope) |
| `$data['categories']` | `dhps_mmb_collection_to_legacy_categories( $collection )` |
| `$data['categories_order']` | `$collection->get_meta( 'categories_order', [] )` |
| `$data['tax_dates']` | `foreach ( $collection as $item )` + `dhps_mio_item_to_legacy_month( $item )` |
| `$data['featured_video']` | `dhps_tp_collection_to_legacy_categories( $collection )['featured']` |
| `$data['html']` (TC) | `$collection->get_meta( 'html', '' )` |
| `$data['is_empty']` (TC) | `$collection->get_meta( 'is_empty', true )` |
| `$data['videos']` (MAES) | `$collection->filter( fn($i) => $i->type === 'video' )` |
| `$data['news']` (MAES) | `$collection->filter( fn($i) => $i->type === 'news' )` |
| `$data['merkblaetter']` (MAES) | `$collection->filter( fn($i) => $i->type === 'document' )` |
| `$data` in Steuertermine (`foreach ($data as $month)`) | `$months` (Variable umbenannt) |

### Plugin-Entwickler

Neue Sort-Hook-API in `DHPS_Content_Collection`:

```php
// Sortiert nach meta.date_iso ASC (YYYY-MM-Format aus v0.18.1)
$sorted = $collection->sort_by_date_iso();

// Sortiert nach meta.date_iso DESC
$sorted_desc = $collection->sort_by_date_iso( 'desc' );

// Generisch nach Meta-Key
$by_custom = $collection->sorted_by( 'my_meta_key', 'asc' );

// Generisch mit Callable
$by_callable = $collection->sorted_by(
    fn( DHPS_Content_Item $item ) => strtolower( $item->title ),
    'asc'
);
```

**Konventionen**:

- Immutable: liefert NEUE Collection, Original unveraendert
- Items ohne Sort-Wert landen IMMER am Ende (unabhaengig von `$direction`)
- PHP 8.0+ stable usort: Items mit gleichen Werten behalten Original-Reihenfolge
- Invalid `$direction` -> `InvalidArgumentException`

## Was bleibt unveraendert

- 9 Adapter-Klassen
- 9 Parser
- Pipeline-Schema
- AJAX-JSON-Responses (alle 3)
- Templates des Plugins (alle migriert in v0.19.1 - 19 Templates clean)
- Sub-Shortcode-Bridges
- Helper-Pool (10 Funktionen)
- 3 Action-Hook-Side-Channels
- `echo $tc_html` Trust-Decision
- Shortcode-Atts
- Theme-Override-Mechanismus (nur Template-Body braucht ggf. Migration)
- Component-System

## Template-Scope-API (final)

```php
// v0.19.1 Template-Scope (definitiv)
$collection     // DHPS_Content_Collection (Pflicht)
$service_tag    // string ('mio', 'mmb', etc.) - direkt im Scope
$service_class  // string ('dhps-service--{tag}')
$layout_class   // string ('dhps-layout--{layout}')
$custom_class   // string (Custom-CSS)
// $data ist NICHT mehr im Scope (v0.19.0-Notice-Fenster -> v0.19.1-Hard-Aus)
```

## Rollback bei Problemen

```bash
wp plugin update wp-deubner-hp-services --version=0.19.0
```

Oder via Beta-Channel-Switch im Admin-Dashboard.

## Verbleibender Tech-Debt fuer v0.20.0+

- **Component-System v1-Stabilitaets-Release** (Option Zeta-Plus aus Discovery 39)
- **Theme-Override-Modernisierungs-Kit** (hypothetisch)
- **Component-Slot-System** (hypothetisch)

## Schema-Vertrag-Vorgehen

**20. Iteration** ohne Critical-Drift. **v0.19.1 markiert das Ende der DTO-Foundation-Aera**
(v0.17.0 -> v0.18.x -> v0.19.x).
