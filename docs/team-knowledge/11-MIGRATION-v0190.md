# Migration v0.18.3 -> v0.19.0 (MAJOR)

## Stand: 2026-06-08

## TL;DR

v0.19.0 entfernt `$data` als Lese-API aus dem Template-Scope. Pipeline ist
seit v0.18.0 einzige Datenquelle, jetzt wird der Tech-Debt-`$data`-Pfad
zum **Deprecated-Data-Proxy** verschlankt. Theme-Overrides die `$data['...']`
lesen, **funktionieren weiter** und bekommen eine WP_DEBUG-Notice mit
Migrations-Hinweis.

Zusaetzlich: `$service_tag` ist neu **direkt im Template-Scope** verfuegbar.

## Auswirkungen pro Konsumenten-Typ

### Anwender / Site-Owner

**Kein Handlungsbedarf.** Plugin-Update auf v0.19.0 funktioniert wie gewohnt.
HTML-Render-Output ist bytewise unveraendert (Stage-verifiziert: 76+8+91
dhps-Klassen pre/post identisch).

### Theme-Entwickler mit Template-Overrides

**Pruefen ob Theme-Overrides aktiv sind**:

```
{your-theme}/dhps/services/{service}/{layout}.php
{your-theme}/dhps/steuertermine/{layout}.php
```

Falls **JA**: nach v0.19.0-Update WP_DEBUG-Mode aktivieren und `debug.log`
beobachten. Notice-Zeilen wie:

```
DHPS deprecated: $data['categories'] gelesen in Template "default"
(Service "mmb"). Nutzen Sie $collection oder $service_tag.
```

zeigen genau, welcher Override migriert werden muss.

#### Migrations-Mapping fuer haeufige $data-Reads

| Vorher (v0.18.x) | Nachher (v0.19.0) |
|------------------|-------------------|
| `$data['service_tag']` | **`$service_tag`** (direkt im Scope) |
| `$data['categories']` | `dhps_mmb_collection_to_legacy_categories( $collection )` |
| `$data['categories_order']` | `$collection->get_meta( 'categories_order', [] )` |
| `$data['tax_dates']` | `foreach ( $collection as $item )` + `dhps_mio_item_to_legacy_month( $item )` |
| `$data['featured_video']` | `dhps_tp_collection_to_legacy_categories( $collection )['featured']` |
| `$data['html']` (TC) | `$collection->get_meta( 'html', '' )` |
| `$data['is_empty']` (TC) | `$collection->get_meta( 'is_empty', true )` |
| `$data['videos']` (MAES) | `$collection->filter( fn($i) => $i->type === 'video' )` |
| `$data['news']` (MAES) | `$collection->filter( fn($i) => $i->type === 'news' )` |
| `$data['merkblaetter']` (MAES) | `$collection->filter( fn($i) => $i->type === 'document' )` |

### Plugin-Entwickler

`$data`-Parameter ist nicht aus `render_parsed()`-Signatur entfernt (BC),
aber der Wert im Template-Scope ist ab v0.19.0 ein
`DHPS_Deprecated_Data_Proxy` statt ein direktes Array.

- `isset( $data )` -> true (keine Notice)
- `is_object( $data )` -> true (keine Notice)
- `$data instanceof DHPS_Deprecated_Data_Proxy` -> true
- `$data['foo']` -> Notice + Wert
- `count( $data )` -> Notice + Wert
- `foreach ( $data as ... )` -> Notice + Iteration

## Was bleibt unveraendert

- 9 Adapter-Klassen
- 9 Parser
- Pipeline-Schema
- AJAX-JSON-Responses (alle 3)
- Templates des Plugins (alle 22)
- Sub-Shortcode-Bridges
- Helper-Pool (10 Funktionen)
- 3 Action-Hook-Side-Channels
- `echo $tc_html` Trust-Decision
- Shortcode-Atts
- Theme-Override-Mechanismus

## Neue Template-Scope-API

```php
// v0.19.0 Template-Scope (NEU)
$collection     // DHPS_Content_Collection (Pflicht)
$service_tag    // string ('mio', 'mmb', etc.) - NEU direkt im Scope
$service_class  // string ('dhps-service--{tag}')
$layout_class   // string ('dhps-layout--{layout}')
$custom_class   // string (Custom-CSS)
$data           // DHPS_Deprecated_Data_Proxy (Theme-Override-BC)
```

## Pipeline-Garantie unveraendert (seit v0.18.0)

- Pipeline-Garantie 3.A + 3.B: `$collection` ist NIE null
- `dhps_collection_or_empty()` als Belt-and-Braces in Templates

## Code-Aenderungen unter der Haube

| Datei | Aenderung |
|-------|-----------|
| `includes/class-dhps-deprecated-data-proxy.php` | NEU - Proxy-Klasse (ArrayAccess+Countable+IteratorAggregate) |
| `includes/class-dhps-renderer.php` | `$service_tag` im Scope + `$data` als Proxy |
| `includes/class-dhps-maes-modules.php` | analog Renderer fuer 3 Sub-Shortcodes |
| `includes/class-dhps-steuertermine.php` | `$service_tag` im Scope (kein Proxy - Templates iterieren `$data` direkt) |
| 22 Plugin-Templates | UNANGETASTET (lesen seit v0.18.0 nur `$collection`) |

## Rollback bei Problemen

Sollten Theme-Override-Brueche auftreten:

```bash
wp plugin update wp-deubner-hp-services --version=0.18.3
```

Oder via Beta-Channel-Switch im Admin-Dashboard (v0.16.0).

## Verbleibender Tech-Debt fuer v0.19.1 oder v0.20.0

- **`$data` komplett entfernen**: nach 1 Release Migrations-Fenster
- **`Collection::sort_by_date_iso()`**: Hook fuer `meta.date_iso` aus v0.18.1
- **`Collection::sorted_by()`**: generischer Sortier-Hook
- **Component-System v2**: hypothetisches Render-Layer-Refactor

## Schema-Vertrag-Vorgehen

**19. Iteration** ohne Critical-Drift. v0.19.0 ist die letzte strukturelle
Aenderung im DTO-Foundation-Cycle (v0.17.0 -> v0.18.x -> v0.19.0).
