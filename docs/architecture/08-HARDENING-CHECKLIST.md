# Sicherheits-Hardening Checkliste

## Status: Geprueft am 2026-04-10

### Ergebnis: 0 Critical, 0 High, 0 Medium (alle behoben), 0 Low (alle behoben)

## Checkliste

### Input Validation
- [x] Alle `$_POST`-Zugriffe mit `sanitize_text_field(wp_unslash())` oder `absint()`
- [x] Alle `$_GET`-Zugriffe mit `absint()` oder `sanitize_text_field()`
- [x] AJAX-Parameter: Whitelist-Ansatz fuer erlaubte Parameter
- [x] PDF-Proxy: Nur sichere Parameter (id, rubrik, header, modus) akzeptiert

### Output Escaping
- [x] Templates (MIO, MMB, TP): `esc_html()`, `esc_attr()`, `esc_url()` durchgaengig
- [x] Layout-Templates: `phpcs:ignore` dokumentiert (Raw-HTML Trust Boundary)
- [x] API-Fehler: `esc_html()` in HTML-Kommentaren
- [x] Admin-Templates: Escaping in allen Ausgaben

### Authentication & Authorization
- [x] Admin-Seiten: `current_user_can('manage_options')` in Render-Funktionen
- [x] Admin-Page-Handler: `current_user_can('manage_options')` in allen Save-Methoden
- [x] Demo-Toggle: Admin-only AJAX mit Nonce
- [x] Public AJAX: Nonce-Validierung auf allen Endpoints

### CSRF Protection
- [x] Alle Formulare: `wp_nonce_field()` + `check_admin_referer()` / `verify_nonce()`
- [x] Alle AJAX-Handler: `check_ajax_referer()` mit spezifischen Nonce-Actions

### SQL Injection
- [x] `DHPS_Cache::flush()`: `$wpdb->prepare()` mit LIKE-Patterns
- [x] `dhps_deactivate()`: `$wpdb->prepare()` (gefixt 2026-04-10)
- [x] `uninstall.php`: `$wpdb->prepare()` (gefixt 2026-04-10)
- [x] Keine sonstigen direkten SQL-Queries

### Directory Listing
- [x] `index.php` in allen 27+ Unterverzeichnissen
- [x] Root: `index.php` + `index.html`

### Direct File Access
- [x] Alle PHP-Dateien in `includes/`: `if (!defined('ABSPATH')) exit;`
- [x] Alle PHP-Dateien in `admin/views/`: ABSPATH-Check
- [x] Alle PHP-Dateien in `public/views/`: ABSPATH-Check
- [x] Elementor-Widget-Dateien: ABSPATH-Check (gefixt 2026-04-10)
- [x] `uninstall.php`: `WP_UNINSTALL_PLUGIN`-Check

### Credential Security
- [x] OTA/kdnr NIEMALS im Frontend exponiert
- [x] AJAX-Proxy: Credentials server-seitig aus Options geladen
- [x] PDF-Proxy: kdnr nur server-seitig in URL eingesetzt
- [x] Demo-Credentials: Filterbar via `dhps_demo_credentials`

### Deprecated Functions
- [x] `current_time('timestamp')` ersetzt durch `time()` (gefixt 2026-04-10)
- [x] Keine weiteren WP-Deprecations gefunden

### API Security
- [x] HTTPS erzwungen (`sslverify => true`)
- [x] WordPress HTTP API (`wp_remote_get`) statt `curl`/`file_get_contents`
- [x] PDF-Proxy: Content-Type-Validierung (gefixt 2026-04-10)
- [x] Timeout: 30 Sekunden

### Bekannte Limitierungen (dokumentiert)
- [ ] Raw-HTML-Output fuer Legacy-Services (MIL, TPT, TC, LP) - Migration pending
- [ ] kdnr in TaxPlain Video-iframe-URL sichtbar (Plattform-Limitierung)
- [ ] API-Fehler in HTML-Kommentaren (minimales Info-Disclosure)
