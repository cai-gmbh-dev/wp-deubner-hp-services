# Widget-System

## Drei Einbettungswege

| Methode | Klasse | Zielgruppe |
|---------|--------|------------|
| Shortcode | DHPS_Shortcodes | Content-Redakteure |
| WP-Widget | DHPS_Widget | Sidebar/Footer |
| Elementor | DHPS_Elementor_Widget_* | Page-Builder-Nutzer |

## Shortcodes

Ein generischer Handler fuer alle 9 Services:

```php
DHPS_Shortcodes::handle_shortcode($atts, $content, $tag)
```

- `$tag` bestimmt den Service (mio, mmb, tp, etc.)
- Attribute: `layout`, `class`, `cache_ttl`, service-spezifische Params
- Nutzt Content Pipeline fuer Rendering

## WordPress Widget (DHPS_Widget)

- Extends `WP_Widget`
- Konfigurierbar: Service, Layout, CSS-Klasse, Cache-TTL, Titel
- DI via Setter: `set_dependencies(DHPS_API_Client, DHPS_Renderer)`
- Nutzt API Client direkt (NICHT Pipeline) - Legacy-Weg

## Elementor Widgets

### Architektur

```
DHPS_Elementor (Registrierung + Kategorie)
     │
     ▼
DHPS_Elementor_Widget_Base (abstrakte Basis)
     │  - Static DI: set_dependencies(Pipeline)
     │  - Generische Controls aus Service_Registry
     │  - Gemeinsames render() via Pipeline
     │
     ├── DHPS_Elementor_Widget_MIO
     ├── DHPS_Elementor_Widget_LXMIO
     ├── DHPS_Elementor_Widget_MMB
     ├── DHPS_Elementor_Widget_MIL
     ├── DHPS_Elementor_Widget_TP
     ├── DHPS_Elementor_Widget_TPT
     ├── DHPS_Elementor_Widget_TC
     ├── DHPS_Elementor_Widget_MAES
     └── DHPS_Elementor_Widget_LP
```

### Service-Widget-Klassen

Jede Klasse definiert nur:
- `get_service_key()` -> z.B. 'mio'
- `get_icon()` -> z.B. 'eicon-post-list'

Alles andere erbt von der Base-Klasse.

### Elementor-Controls

Dynamisch generiert aus `DHPS_Service_Registry`:
- **Content Section**: Service-spezifische Admin-Fields + universelle Controls (Layout, CSS-Klasse, Cache-TTL)
- **Style Section**: Padding, Border-Radius (nur bei Card-Layout)
- Control-Typen: SELECT, NUMBER, TEXT (automatisch aus admin_fields Typ abgeleitet)

### Registrierungsreihenfolge

1. `DHPS_Elementor::__construct($pipeline)` - speichert Pipeline
2. Hook `elementor/elements/categories_registered` -> Kategorie "Deubner Services"
3. Hook `elementor/widgets/register` -> `register_widgets()`
   - Include Widget-Dateien
   - `DHPS_Elementor_Widget_Base::set_dependencies($pipeline)` (MUSS vor Registrierung!)
   - Registriere alle 9 Widget-Klassen

### Render-Flow

```
Elementor ruft render() auf
     │
     ▼
DHPS_Elementor_Widget_Base::render()
     │  1. Settings extrahieren
     │  2. Auth-Params laden (ota/kdnr)
     │  3. Service-Defaults mergen
     │  4. Admin-Options mergen (Variante-Switch!)
     │  5. Widget-Overrides mergen
     ▼
self::$pipeline->render_service(tag, endpoint, params, ttl, layout, css)
```

## Template-System

### Layout-Templates (Raw HTML Wrapper)
```
public/views/layout-default.php   -> Einfacher Container
public/views/layout-card.php      -> Card mit Shadow/Padding
public/views/layout-compact.php   -> Minimales Padding
```

### Service-Templates (Parsed Data)
```
public/views/services/mio/default.php
public/views/services/mio/card.php
public/views/services/mio/compact.php
public/views/services/mmb/{default,card,compact}.php
public/views/services/tp/{default,card,compact}.php
```

### Theme-Override
```
{theme}/dhps/layout-{name}.php
{theme}/dhps/services/{tag}/{layout}.php
```

### Template-Variablen

```php
$data          // Parsed Array (service-spezifisch)
$service_class // 'dhps-service--mio'
$layout_class  // 'dhps-layout--card'
$custom_class  // User-CSS (mit fuehrendem Leerzeichen oder leer)
```
