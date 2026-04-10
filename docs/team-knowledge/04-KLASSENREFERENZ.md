# Klassenreferenz

## Alle PHP-Klassen

### API-Layer

| Klasse | Datei | Typ | Abhaengigkeiten |
|--------|-------|-----|-----------------|
| DHPS_API_Interface | class-dhps-api-interface.php | Interface | - |
| DHPS_Legacy_API | class-dhps-legacy-api.php | Implementierung | wp_remote_get |
| DHPS_API_Response | class-dhps-api-response.php | Value Object | - |
| DHPS_API_Client | class-dhps-api-client.php | Facade | API_Interface, Cache |
| DHPS_Cache | class-dhps-cache.php | Wrapper | WordPress Transients |

### Content Pipeline

| Klasse | Datei | Typ | Abhaengigkeiten |
|--------|-------|-----|-----------------|
| DHPS_Content_Pipeline | class-dhps-content-pipeline.php | Orchestrator | API_Client, Renderer, Cache |
| DHPS_Parser_Interface | class-dhps-parser-interface.php | Interface | - |
| DHPS_Parser_Registry | class-dhps-parser-registry.php | Static Registry | - |
| DHPS_MIO_Parser | parsers/class-dhps-mio-parser.php | Parser | DOMDocument |
| DHPS_MIO_News_Parser | parsers/class-dhps-mio-news-parser.php | Parser (AJAX) | DOMDocument |
| DHPS_MMB_Parser | parsers/class-dhps-mmb-parser.php | Parser | DOMDocument |
| DHPS_MMB_Search_Parser | parsers/class-dhps-mmb-search-parser.php | Parser (AJAX) | DOMDocument |
| DHPS_TP_Parser | parsers/class-dhps-tp-parser.php | Parser | DOMDocument |

### Rendering

| Klasse | Datei | Typ | Abhaengigkeiten |
|--------|-------|-----|-----------------|
| DHPS_Renderer | class-dhps-renderer.php | Template Engine | Demo_Manager (optional) |
| DHPS_Shortcodes | class-dhps-shortcodes.php | Shortcode Handler | API_Client, Renderer, Pipeline |

### Widgets

| Klasse | Datei | Typ | Abhaengigkeiten |
|--------|-------|-----|-----------------|
| DHPS_Widget | class-dhps-widget.php | WP Widget | API_Client, Renderer |
| DHPS_Elementor | class-dhps-elementor.php | Elementor Loader | Pipeline |
| DHPS_Elementor_Widget_Base | widgets/elementor/class-dhps-elementor-widget-base.php | Abstract Widget | Pipeline (static) |
| DHPS_Elementor_Widget_MIO | widgets/elementor/class-dhps-elementor-service-widgets.php | Konkretes Widget | Base |
| DHPS_Elementor_Widget_LXMIO | " | " | " |
| DHPS_Elementor_Widget_MMB | " | " | " |
| DHPS_Elementor_Widget_MIL | " | " | " |
| DHPS_Elementor_Widget_TP | " | " | " |
| DHPS_Elementor_Widget_TPT | " | " | " |
| DHPS_Elementor_Widget_TC | " | " | " |
| DHPS_Elementor_Widget_MAES | " | " | " |
| DHPS_Elementor_Widget_LP | " | " | " |

### Infrastruktur

| Klasse | Datei | Typ | Abhaengigkeiten |
|--------|-------|-----|-----------------|
| DHPS_Service_Registry | class-dhps-service-registry.php | Static Registry | - |
| DHPS_Demo_Manager | class-dhps-demo-manager.php | Lifecycle Manager | Service_Registry, WP Options |
| DHPS_AJAX_Proxy | class-dhps-ajax-proxy.php | AJAX Handler | Legacy_API, Cache |
| DHPS_Admin | class-dhps-admin.php | Admin Menu | Demo_Manager |
| DHPS_Admin_Page_Handler | class-dhps-admin-page-handler.php | Form Handler | Service_Registry, WP Options |

## Methoden-Schnellreferenz

### DHPS_API_Client
```php
fetch_content(string $endpoint, array $params = [], int $cache_ttl = 3600): string
flush_cache(): void
```

### DHPS_Content_Pipeline
```php
render_service(string $tag, string $endpoint, array $params, int $ttl, string $layout, string $css): string
```

### DHPS_Renderer
```php
render(string $html, string $tag, string $layout = 'default', string $css = ''): string
render_parsed(array $data, string $tag, string $layout = 'default', string $css = ''): string
set_demo_manager(DHPS_Demo_Manager $manager): void
locate_template(string $layout): ?string
locate_service_template(string $tag, string $layout): ?string
```

### DHPS_Cache
```php
get(string $key): ?string
set(string $key, string $value, int $ttl = 3600): bool
get_data(string $key): ?array
set_data(string $key, array $data, int $ttl = 3600): bool
generate_key(string $endpoint, array $params): string
flush(): void
```

### DHPS_Service_Registry
```php
static get_services(): array
static get_service(string $shortcode): ?array
static get_shortcode_names(): array
```

### DHPS_Parser_Registry
```php
static register(string $tag, DHPS_Parser_Interface $parser): void
static get_parser(string $tag): ?DHPS_Parser_Interface
static has_parser(string $tag): bool
static reset(): void
```

### DHPS_Demo_Manager
```php
activate_demo(string $slug): bool
deactivate_demo(string $slug): void
is_demo_active(string $slug): bool
is_demo_available(string $slug): bool
get_days_remaining(string $slug): int
get_service_status(string $slug): string   // 'active'|'demo'|'inactive'
get_all_statuses(): array
check_expired_demos(): void
```
