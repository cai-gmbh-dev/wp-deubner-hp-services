# Coding Conventions

## Namenskonventionen

### PHP-Klassen
```
DHPS_{PascalCase}
Beispiel: DHPS_Content_Pipeline, DHPS_MIO_Parser
```

### Dateinamen
```
class-dhps-{kebab-case}.php
Beispiel: class-dhps-content-pipeline.php, class-dhps-mio-parser.php
```

### WordPress Options
```
dhps_{snake_case}
Beispiel: dhps_ota_mio, dhps_demo_state, dhps_demo_duration_days
```

### CSS-Klassen (BEM)
```
.dhps-{block}__{element}--{modifier}
Beispiel: .dhps-news__title, .dhps-layout--card, .dhps-filter-bar__btn--active
```

### CSS Custom Properties
```
--dhps-{kategorie}-{name}
Beispiel: --dhps-color-primary, --dhps-space-md, --dhps-fs-lg
```

### JavaScript
```
Vanilla JS, keine Frameworks
Funktionen: camelCase
DOM-Selektoren: data-dhps-* Attribute
```

### Constants
```php
DEUBNER_HP_SERVICES_VERSION
DEUBNER_HP_SERVICES_PATH
DEUBNER_HP_SERVICES_URL
DEUBNER_HP_SERVICES_BASENAME
DEUBNER_HP_SERVICES_API_BASE
DEUBNER_HP_SERVICES_NONCE_ACTION
```

## Architektur-Regeln

### 1. Constructor Injection bevorzugen
```php
// Gut
$pipeline = new DHPS_Content_Pipeline($client, $renderer, $cache);

// Nur wenn noetig (WP_Widget, Elementor)
$widget->set_dependencies($client, $renderer);
```

### 2. Service Registry als Single Source of Truth
```php
// Immer
$services = DHPS_Service_Registry::get_services();

// Nie: Hardcodierte Service-Listen in anderen Klassen
```

### 3. Parser-Interface implementieren
```php
class DHPS_New_Parser implements DHPS_Parser_Interface {
    public function parse(string $html): array {
        return ['data' => ..., 'service_tag' => 'new'];
    }
}
```

### 4. Credentials nur serverseitig
```php
// Gut: In AJAX-Proxy
$ota = get_option('dhps_ota_mio');

// NIE: Im Frontend/Template
echo $ota; // VERBOTEN
```

### 5. Output Escaping
```php
esc_html($text);
esc_attr($attribute);
esc_url($url);
wp_kses_post($html);
```

### 6. Nonce fuer jede Aktion
```php
// Template
wp_create_nonce('dhps_news_nonce');

// AJAX-Handler
check_ajax_referer('dhps_news_nonce', 'nonce', false);
```

## Autoloader-Pfade

Der SPL-Autoloader sucht in:
1. `includes/class-dhps-{name}.php`
2. `includes/parsers/class-dhps-{name}.php`

Neue Klassen muessen in einem dieser Verzeichnisse liegen.

## Dokumentationssprache

- Code-Kommentare: Englisch
- Dokumentation: Deutsch (ASCII-sicher, keine Umlaute in Code)
- Variablen/Klassen: Englisch
