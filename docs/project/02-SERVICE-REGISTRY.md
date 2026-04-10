# Service Registry - Alle 9 Services

## Uebersicht

Die zentrale Klasse `DHPS_Service_Registry` definiert alle Services statisch. Wird von Shortcodes, Admin, Demo-Manager, Elementor und Renderer genutzt.

## Services im Detail

### 1. MIO - MI-Online Steuerrecht
- **Shortcode**: `[mio]`
- **Endpoint**: `einbau/mio/bin/php_inhalt.php`
- **Auth**: OTA (`dhps_ota_mio`)
- **Kategorie**: Steuern
- **Features**: Steuertermine, News mit Paginierung, Zielgruppen-Filter, Suche
- **Parser**: DHPS_MIO_Parser
- **AJAX**: dhps_load_news
- **Admin-Options**: OTA, Variante, Anzahl, Teaser-Modus

### 2. LXMIO - MI-Online Recht
- **Shortcode**: `[lxmio]`
- **Endpoint**: `einbau/mio/bin/php_inhalt.php` (gleicher Endpoint wie MIO)
- **Auth**: OTA (`dhps_ota_lxmio`)
- **Kategorie**: Recht
- **Features**: Wie MIO, aber Fachgebiet Recht
- **Parser**: DHPS_MIO_Parser (gleicher Parser, Fallback-Templates)
- **Admin-Page**: Teilt sich Seite mit MIO (2-Spalten-Layout)

### 3. MMB - Merkblaetter
- **Shortcode**: `[mmb]`
- **Endpoint**: `einbau/mmo/merkblattpages/php_inhalt.php`
- **Auth**: OTA (`dhps_mmo_ota`)
- **Kategorie**: Steuern
- **Features**: Kategorisierte Factsheets, Suche, PDF-Download
- **Parser**: DHPS_MMB_Parser
- **AJAX**: dhps_mmb_search, dhps_mmb_pdf

### 4. MIL - Infografiken
- **Shortcode**: `[mil]`
- **Endpoint**: `einbau/mio/bin/php_inhalt.php`
- **Auth**: keine (oeffentlich)
- **Kategorie**: Steuern
- **Status**: Legacy (kein Parser)

### 5. TP - TaxPlain Videos
- **Shortcode**: `[tp]`
- **Endpoint**: `einbau/mio/bin/taxplain_inhalt.php`
- **Auth**: kdnr (`dhps_tp_kdnr`)
- **Kategorie**: Steuern
- **Features**: Video-Grid, Kategorie-Filter, Lazy-Load iframes
- **Parser**: DHPS_TP_Parser
- **AJAX**: dhps_tp_video_src

### 6. TPT - TaxPlain Teaser
- **Shortcode**: `[tpt]`
- **Endpoint**: `einbau/mio/bin/taxplain_inhalt.php`
- **Auth**: kdnr (`dhps_tp_kdnr`)
- **Kategorie**: Steuern
- **Status**: Legacy (kein Parser), teilt Admin-Seite mit TP

### 7. TC - Tax-Rechner
- **Shortcode**: `[tc]`
- **Endpoint**: `einbau/mio/bin/php_inhalt.php`
- **Auth**: keine
- **Kategorie**: Steuern
- **Status**: Legacy (kein Parser)

### 8. MAES - Meine Aerzteseite
- **Shortcode**: `[maes]`
- **Endpoint**: `einbau/mio/bin/php_inhalt.php`
- **Auth**: OTA (`dhps_ota_maes`)
- **Kategorie**: Medizin
- **Status**: Legacy (kein Parser)

### 9. LP - Lexplain
- **Shortcode**: `[lp]`
- **Endpoint**: `einbau/mio/bin/php_inhalt.php`
- **Auth**: kdnr (`dhps_lp_kdnr`)
- **Kategorie**: Recht
- **Status**: Legacy (kein Parser)

## Auth-Typen

| Typ | Parameter | Beschreibung |
|-----|-----------|--------------|
| `ota` | `ota` | Online-Teilnehmer-Ausweis (Steuerberater) |
| `kdnr` | `kdnr` | Kundennummer (Verlag) |
| keine | - | Oeffentlich zugaenglich |

## Service-Definition Schema

```php
'shortcode_tag' => [
    'name'           => 'Display Name',
    'endpoint'       => 'einbau/.../php_inhalt.php',
    'auth_type'      => 'ota' | 'kdnr' | '',
    'auth_option'    => 'dhps_option_key',
    'shortcode_atts' => ['layout', 'class', ...],
    'admin_options'  => ['dhps_key' => 'url_param'],
    'admin_fields'   => [
        ['option_key' => '...', 'field_name' => '...', 'label' => '...', 'type' => 'text|select|number']
    ],
    'supports_video' => true|false,
    'default_params' => ['modus' => 'p'],
    'admin_page'     => 'dhps_{tag}_page',
    'admin_title'    => 'Menu Title',
    'category'       => 'steuern|recht|medizin',
    'icon'           => 'dashicons-*',
    'shop_url'       => 'https://...',
]
```
