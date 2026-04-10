# Dependency Injection Pattern

## Uebersicht

Das Plugin nutzt manuelle DI ohne Container. Alle Abhaengigkeiten werden in `dhps_init()` in strikter Reihenfolge aufgeloest.

## DI-Typen im Plugin

### 1. Constructor Injection (bevorzugt)

```php
$api    = new DHPS_Legacy_API();
$cache  = new DHPS_Cache();
$client = new DHPS_API_Client($api, $cache);          // API + Cache
$renderer = new DHPS_Renderer();
$pipeline = new DHPS_Content_Pipeline($client, $renderer, $cache);
$shortcodes = new DHPS_Shortcodes($client, $renderer, $pipeline);
$elementor = new DHPS_Elementor($pipeline);
$admin = new DHPS_Admin($demo_manager);
$ajax_proxy = new DHPS_AJAX_Proxy($api, $cache);
```

### 2. Setter Injection (WP-Widget-Einschraenkung)

WordPress erlaubt keine Custom-Constructors fuer WP_Widget. Daher:

```php
// Native WP Widget (Instanz-Level)
$widget = $wp_widget_factory->get_widget('DHPS_Widget');
$widget->set_dependencies($client, $renderer);

// Renderer (optionale Demo-Funktion)
$renderer->set_demo_manager($demo_manager);
```

### 3. Static Setter (Elementor-Einschraenkung)

Elementor instanziiert Widgets selbst. Daher statische Injection:

```php
// Vor Widget-Registrierung aufgerufen
DHPS_Elementor_Widget_Base::set_dependencies($pipeline);

// In der Widget-Klasse:
private static $pipeline = null;

public static function set_dependencies(DHPS_Content_Pipeline $pipeline): void {
    self::$pipeline = $pipeline;
}

protected function render(): void {
    if (null === self::$pipeline) return;  // Guard Clause
    echo self::$pipeline->render_service(...);
}
```

### 4. Static Registry (Service-Definitionen)

```php
DHPS_Service_Registry::get_services();     // Alle 9 Services
DHPS_Service_Registry::get_service('mio'); // Einzelner Service

DHPS_Parser_Registry::register('mio', new DHPS_MIO_Parser());
DHPS_Parser_Registry::get_parser('mio');
```

### 5. Factory Pattern (Value Objects)

```php
DHPS_API_Response::success($body, $status_code);
DHPS_API_Response::error($message, $status_code);
```

## Abhaengigkeitsgraph

```
DHPS_Legacy_API (keine Deps)
DHPS_Cache (keine Deps)
DHPS_Renderer (keine Deps)
DHPS_Demo_Manager (keine Deps)
     │
     ▼
DHPS_API_Client(API_Interface, Cache)
     │
     ▼
DHPS_Content_Pipeline(API_Client, Renderer, Cache)
     │
     ├──► DHPS_Shortcodes(API_Client, Renderer, Pipeline)
     ├──► DHPS_Elementor(Pipeline)
     └──► DHPS_Widget.set_dependencies(API_Client, Renderer)

DHPS_AJAX_Proxy(Legacy_API, Cache) ─── parallel, unabhaengig
DHPS_Admin(Demo_Manager) ─── parallel, unabhaengig
```

## Autoloader

SPL-Autoloader fuer `DHPS_`-Prefix:

```
DHPS_Foo_Bar -> includes/class-dhps-foo-bar.php
                includes/parsers/class-dhps-foo-bar.php
```

Suchpfade: `includes/` und `includes/parsers/`
