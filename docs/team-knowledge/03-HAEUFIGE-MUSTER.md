# Haeufige Muster und Rezepte

## Neuen Service mit Parser hinzufuegen

### 1. Parser erstellen
```php
// includes/parsers/class-dhps-new-parser.php
class DHPS_New_Parser implements DHPS_Parser_Interface {
    public function parse(string $html): array {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>');
        // ... Parsing-Logik ...
        return [
            'parsed_data' => $result,
            'service_tag' => 'new',
        ];
    }
}
```

### 2. Parser registrieren
In `Deubner_HP_Services.php`, Funktion `dhps_init()`:
```php
DHPS_Parser_Registry::register('new', new DHPS_New_Parser());
```

### 3. Templates erstellen
```
public/views/services/new/default.php
public/views/services/new/card.php
public/views/services/new/compact.php
```

### 4. JavaScript (falls AJAX noetig)
```
public/js/dhps-new.js
```
Im Template einbinden:
```php
wp_enqueue_script('dhps-new-js');
```

### 5. AJAX-Endpoint (falls noetig)
In `DHPS_AJAX_Proxy::register()` neuen Handler registrieren.

## AJAX-Proxy Endpoint hinzufuegen

```php
// In DHPS_AJAX_Proxy::register()
add_action('wp_ajax_dhps_new_action', [$this, 'handle_new_action']);
add_action('wp_ajax_nopriv_dhps_new_action', [$this, 'handle_new_action']);

public function handle_new_action(): void {
    check_ajax_referer('dhps_new_nonce', 'nonce', false);
    
    // Parameter sanitizen
    $param = sanitize_text_field($_POST['param'] ?? '');
    
    // Credential serverseitig laden
    $ota = get_option('dhps_ota_service');
    
    // API aufrufen
    $response = $this->api->fetch($endpoint, $params);
    
    // Parsen und zurueckgeben
    wp_send_json_success($parsed_data);
}
```

## Theme-Template ueberschreiben

```
{theme}/
  dhps/
    layout-custom.php                    # Layout ueberschreiben
    services/mio/default.php             # Service-Template ueberschreiben
```

Template-Variablen:
```php
$data          // Array: Parsed Service-Daten
$service_class // String: 'dhps-service--mio'
$layout_class  // String: 'dhps-layout--default'
$custom_class  // String: ' my-class' (mit Leerzeichen) oder ''
```

## Cache leeren

### Programmatisch
```php
DHPS_Cache::flush(); // Alle dhps_* Transients loeschen
```

### Per wp-cli
```bash
MSYS_NO_PATHCONV=1 docker exec wordpress wp transient delete --all --path=/var/www/html
```

## Template-Fallbacks konfigurieren

```php
add_filter('dhps_template_fallbacks', function($fallbacks) {
    $fallbacks['lxmio'] = 'mio';      // Standard: lxmio nutzt mio Templates
    $fallbacks['new_service'] = 'mio'; // Neuer Service nutzt mio als Fallback
    return $fallbacks;
});
```

## Demo-Credentials anpassen

```php
add_filter('dhps_demo_credentials', function($creds) {
    $creds['mio'] = 'CUSTOM-DEMO-KEY';
    return $creds;
});
```

## Variante-Switch verstehen

Die Admin-Option `variante_switch` steuert den Darstellungsmodus:

| Wert | Bedeutung | API-Parameter |
|------|-----------|---------------|
| `'0'` | Aus Shortcode/Widget-Parameter | variante bleibt wie gesetzt |
| `'1'` | Tagesaktuell | variante = 'TAGESAKTUELL' |
| `'2'` | Kategorisiert | variante = 'KATEGORIEN' |

## DOM-Parsing Best Practice

```php
// UTF-8 sicherstellen + Fehler unterdruecken (Legacy HTML)
$doc = new DOMDocument();
@$doc->loadHTML(
    '<?xml encoding="UTF-8"><body>' . $html . '</body>',
    LIBXML_NOERROR | LIBXML_NOWARNING
);
$xpath = new DOMXPath($doc);

// Elemente finden
$elements = $xpath->query('//div[contains(@class, "target")]');
```
