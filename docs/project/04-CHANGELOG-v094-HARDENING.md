# Changelog v0.9.4 - Update & Hardening (2026-04-10)

## Plattform-Updates

| Komponente | Vorher | Nachher |
|-----------|--------|---------|
| WordPress | 6.9.1 | **6.9.4** (Minor Security Update) |
| Elementor | 3.35.4 | **4.0.1** (Major Update) |
| PHP | 8.3.30 | 8.3.30 (unveraendert) |
| Plugin | 0.9.4 | 0.9.4 (Hardening) |

## Elementor 4.x Kompatibilitaet

**Ergebnis: Voll kompatibel, keine Code-Aenderungen noetig.**

Das Plugin nutzt ausschliesslich moderne Elementor-APIs:
- Hook `elementor/widgets/register` (nicht das alte `widgets_registered`)
- API `$widgets_manager->register()` (nicht `register_widget_type()`)
- Keine Scheme-Referenzen (seit Elementor 3.0 deprecated)
- Keine Group_Control-Nutzung
- Standard-Controls: SELECT, TEXT, NUMBER, DIMENSIONS, DIVIDER

## MAES Service aktiviert

- Neue kdnr: `51708720` (ohne OTA-Prefix)
- Option: `dhps_maes_kdnr`
- Service rendert erfolgreich: 86KB Content
- Endpoint: `infokombi/bin/infokombi.php`

## Sicherheits-Hardening

### Behobene Findings

| # | Severity | Finding | Fix |
|---|----------|---------|-----|
| H1 | MEDIUM | Fehlende `index.php` in 27 Unterverzeichnissen | `index.php` in allen Verzeichnissen erstellt |
| H2 | MEDIUM | PDF-Proxy akzeptiert beliebige Content-Types | Validierung: Content-Type oder PDF-Magic-Bytes pruefen |
| H3 | LOW | `DHPS_Admin_Page_Handler` ohne `current_user_can()` | Capability-Check in `save_settings()`, `save_sibling_form()`, `save_mio_form()` |
| H4 | LOW | Elementor-Widget-Dateien ohne ABSPATH-Check | `if (!defined('ABSPATH')) exit;` hinzugefuegt |
| H5 | LOW | `current_time('timestamp')` deprecated seit WP 5.3 | Ersetzt durch `time()` (3 Stellen in Demo-Manager) |
| H6 | LOW | Raw SQL LIKE ohne `$wpdb->prepare()` | `$wpdb->prepare()` in `dhps_deactivate()` und `uninstall.php` |

### Beibehaltene Architekturentscheidungen (dokumentiert)

| # | Thema | Entscheidung |
|---|-------|-------------|
| A1 | Raw-HTML-Output fuer Legacy-Services | Vertrauensgrenze ist Deubner-API (HTTPS). Migration auf Parser+Templates laufend. |
| A2 | kdnr in Video-iframe-URL sichtbar | Technische Limitierung der Video-Plattform (mandantenvideo.de). Nicht vermeidbar. |
| A3 | AJAX-Proxy oeffentlich zugaenglich | Korrekt fuer Frontend-Content. Nonce-geschuetzt. |
| A4 | API-Fehler als HTML-Kommentare | Nutzt `esc_html()`. Info-Disclosure minimal. |

## QA-Ergebnisse (Post-Hardening)

| Test | Status |
|------|--------|
| MIO Shortcode (4.5KB) | PASS |
| LXMIO Shortcode (2.4KB) | PASS |
| MMB Shortcode (223KB) | PASS |
| MAES Shortcode (86KB) | PASS |
| TP Shortcode (74KB) | PASS |
| 9/9 Shortcodes registriert | PASS |
| 4/4 Parser aktiv (MIO, LXMIO, MMB, TP) | PASS |
| 4/4 AJAX-Endpoints aktiv | PASS |
| Elementor 4.0.1 geladen | PASS |
| Keine PHP-Fehler im Debug-Log | PASS |
