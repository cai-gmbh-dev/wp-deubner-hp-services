# Sicherheitsmodell

## Kernprinzip: Credentials NIEMALS im Frontend

OTA-Nummern und kdnr-Werte werden **ausschliesslich serverseitig** geladen und injiziert. Das Frontend erhaelt nur Content-Daten, niemals Authentifizierungsinformationen.

## Credential-Management

### Speicherung
- WordPress Options-Tabelle: `dhps_ota_mio`, `dhps_mmo_ota`, `dhps_tp_kdnr`, etc.
- Admin setzt Werte ueber geschuetzte Formulare mit Nonce-Validierung

### Injection Points

| Kontext | Credential-Quelle | Injection |
|---------|-------------------|-----------|
| Shortcode-Rendering | `get_option('dhps_ota_mio')` | In API-Call Parameter |
| AJAX News | `get_option('dhps_ota_mio')` | Server-seitig vor API-Call |
| AJAX MMB-Suche | `get_option('dhps_mmo_ota')` | Server-seitig vor API-Call |
| AJAX PDF-Download | `get_option('dhps_mmo_ota')` | In PDF-URL server-seitig |
| AJAX Video-Src | `get_option('dhps_tp_kdnr')` | In iframe-URL server-seitig |

## AJAX-Proxy Sicherheit

### Nonce-Validierung
Jeder AJAX-Endpoint validiert einen WordPress-Nonce:

```php
check_ajax_referer('dhps_news_nonce', 'nonce', false);
```

Nonces werden im Template generiert und per `data-nonce`-Attribut uebergeben.

### Parameter-Filterung (Whitelist)

MMB PDF-Download als kritisches Beispiel:

```php
$safe_keys = ['id', 'rubrik', 'header', 'modus'];
// NIEMALS: 'kd_nr', 'ota', 'kdnr'
```

Nur explizit erlaubte Parameter werden vom Client akzeptiert.

### AJAX-Endpoints

| Action | Nonce | Oeffentlich | Schutz |
|--------|-------|-------------|--------|
| `dhps_load_news` | dhps_news_nonce | Ja | OTA server-seitig |
| `dhps_mmb_search` | dhps_mmb_nonce | Ja | kdnr server-seitig |
| `dhps_mmb_pdf` | dhps_mmb_nonce (GET) | Ja | kdnr server-seitig, Whitelist |
| `dhps_tp_video_src` | dhps_tp_nonce | Ja | kdnr server-seitig |
| `dhps_toggle_demo` | dhps_admin_nonce | Nein | Admin-only |

## Output-Escaping

- `esc_html()` fuer Textausgaben
- `esc_attr()` fuer HTML-Attribute
- `esc_url()` fuer URLs
- `wp_kses_post()` fuer zugelassenes HTML
- API-Fehler als HTML-Kommentare: `<!-- DHPS: Fehlermeldung -->`

## Admin-Schutz

- Nonce-Validierung: `DEUBNER_HP_SERVICES_NONCE_ACTION = 'dhps_save_settings'`
- Capability-Check: `manage_options` fuer Admin-Seiten
- Settings-Speicherung ueber `DHPS_Admin_Page_Handler::verify_nonce()`

## Uninstall

`uninstall.php` loescht alle `dhps_*` Options und Transients bei Plugin-Deinstallation.
