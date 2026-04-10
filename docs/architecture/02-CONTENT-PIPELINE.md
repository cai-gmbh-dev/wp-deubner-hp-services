# Content Pipeline

## Datenfluss: API -> Parser -> Template

```
[mio layout="card"]
     │
     ▼
DHPS_Shortcodes::handle_shortcode()
     │  Parameter-Aufbau: Auth + Defaults + Admin-Options + Shortcode-Atts + URL-Params
     ▼
DHPS_Content_Pipeline::render_service(tag, endpoint, params, ttl, layout, css_class)
     │
     ├─► DHPS_API_Client::fetch_content()
     │       ├─ L1-Cache pruefen (Raw HTML, Transient)
     │       └─ Bei Miss: DHPS_Legacy_API::fetch() -> wp_remote_get()
     │           └─ DHPS_API_Response (Value Object)
     │
     ├─► DHPS_Parser_Registry::get_parser(tag)
     │       ├─ Parser vorhanden? -> parse(html) -> strukturiertes Array
     │       └─ Kein Parser? -> Fallback auf Raw-HTML-Rendering
     │
     ├─► L2-Cache (Parsed Data, serialisiertes Array)
     │       Prefix: dhps_p_{md5}
     │
     └─► DHPS_Renderer::render_parsed(data, tag, layout, css_class)
             ├─ Service-Template laden: public/views/services/{tag}/{layout}.php
             ├─ Theme-Override: {theme}/dhps/services/{tag}/{layout}.php
             └─ Output Buffering -> HTML
```

## 2-Layer Caching

| Layer | Inhalt | Prefix | TTL | Miss-Kosten |
|-------|--------|--------|-----|-------------|
| L1 | Raw HTML (String) | `dhps_` | 3600s (konfigurierbar) | HTTP-Request (~200ms) |
| L2 | Parsed Data (Array) | `dhps_p_` | 3600s (konfigurierbar) | DOM-Parsing (~50ms) |

**Cache-Key-Generierung:**
```php
$key = 'dhps_' . md5($endpoint . '|' . json_encode($params));
```

## Parser-Interface

```php
interface DHPS_Parser_Interface {
    public function parse(string $html): array;
}
```

Jeder Parser gibt ein Array mit `service_tag`-Key zurueck.

## Registrierte Parser

| Service-Tag | Parser-Klasse | Parsed Data |
|-------------|---------------|-------------|
| `mio`, `lxmio` | DHPS_MIO_Parser | tax_dates, search_config, ajax_params |
| `mmb` | DHPS_MMB_Parser | categories (mit fact_sheets), search_config |
| `tp` | DHPS_TP_Parser | featured_video, categories (mit videos) |

**Zusaetzliche Parser (nur AJAX):**
- `DHPS_MIO_News_Parser` - fuer AJAX-News-Paginierung
- `DHPS_MMB_Search_Parser` - fuer AJAX-Suche

## Services ohne Parser

MIL, TPT, TC, MAES, LP nutzen den Legacy-Pfad:
- Raw HTML wird direkt ueber Layout-Templates gerendert
- `DHPS_Renderer::render(html, tag, layout)` statt `render_parsed()`

## Template-Fallback-Kette

1. Service-Template: `public/views/services/{tag}/{layout}.php`
2. Fallback-Tag (Filter `dhps_template_fallbacks`): z.B. `lxmio` -> `mio`
3. Default-Layout: `public/views/services/{tag}/default.php`
4. Layout-Template: `public/views/layout-{layout}.php` (Raw HTML)

## Parameter-Aufbau (Shortcode)

Die Parameter werden in dieser Reihenfolge zusammengebaut (spaetere ueberschreiben fruehere):

1. **Auth-Parameter**: OTA/kdnr aus WordPress-Options
2. **Service-Defaults**: aus Service-Registry (`default_params`)
3. **Admin-Options**: aus WordPress-Options (mit Variante-Switch-Logik)
4. **Shortcode-Attribute**: direkt vom Nutzer im Shortcode
5. **URL-Parameter**: `$_GET['video']` fuer Video-Services

### Variante-Switch-Logik

```
variante_switch = '0' -> variante aus Parameter (Standard)
variante_switch = '1' -> variante = 'TAGESAKTUELL'
variante_switch = '2' -> variante = 'KATEGORIEN'
```
