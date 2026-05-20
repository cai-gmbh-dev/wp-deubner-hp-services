# Changelog v0.11.0 - LP Lexplain + Recht-Branding

## Stand: 2026-05-20

## Neue Funktionen

### LP (Lexplain) Service mit Parser
- **NEU**: `DHPS_LP_Parser` (erbt von `DHPS_TP_Parser`)
- Setzt `service_tag = 'lp'` und `service = 'lexplain'` in Videos
- Template-Fallback: `lp -> tp` (nutzt TP-Templates)
- AJAX-Proxy unterstuetzt `service=lexplain` mit eigener Auth-Kette

### Multi-Service AJAX-Proxy
- `handle_tp_video_src()` erkennt jetzt `service` POST-Parameter
- Whitelist: `taxplain` / `lexplain` / `maes`
- Service-spezifische Auth-Keys:
  - `taxplain`: `dhps_tp_kdnr` -> `dhps_ota_tp`
  - `lexplain`: `dhps_lp_ota` -> `dhps_lp_kdnr`
  - `maes`: `dhps_maes_kdnr`
- iframe-URL nutzt `service` aus dem Request

### TP-JS Service-Detection
- `dhps-tp.js` liest `data-service` vom Container
- Sendet `service`-Parameter an AJAX-Proxy
- TP-Templates haben jetzt `data-service` Attribut

## Sicherheit

### SSRF-Schutz (MEDIUM Finding behoben)
- Poster-URL wird gegen Host-Whitelist validiert:
  - `deubner-online.de` (+ www)
  - `mandantenvideo.de` (+ www)
- Bei ungueltigem Host: poster_url wird verworfen

### Bekannte Limitierungen (akzeptiert)
- Nonce-Sharing bei `wp_ajax_nopriv_*`: Erwartet, da Frontend-Service. Rate-Limiting nicht implementiert.

## Recht-Branding (CSS)

### LP (Lexplain)
- `.dhps-service--lp .dhps-tp-featured__heading` -> Recht-Blau
- `.dhps-service--lp .dhps-tp-card__play-btn polygon` -> Recht-Blau
- `.dhps-service--lp .dhps-tp-load-more` -> Recht-Blau mit Hover
- Shadow-Style mit Recht-Blau-Tint

### LXMIO (MI-Online Recht)
- `.dhps-service--lxmio .dhps-news__group-title` -> Recht-Blau
- `.dhps-service--lxmio .dhps-news__title` -> Recht-Blau
- `.dhps-service--lxmio .dhps-news__body` Border-Left -> Recht-Blau
- `.dhps-service--lxmio .dhps-tax-dates__title` -> Recht-Blau
- `.dhps-service--lxmio .dhps-mio-card-article__tag` -> Recht-Blau
- Search-Button + Load-More-Button -> Recht-Blau

## Bekannte Punkte

### LP-OTA fehlt
- `dhps_lp_ota` Option ist leer
- LP rendert Fehler-Output (333 bytes, nicht 79KB wie TP)
- Sobald OTA hinterlegt ist, funktioniert LP vollstaendig wie TP

## Geaenderte Dateien

| Datei | Aenderung |
|-------|-----------|
| `includes/parsers/class-dhps-lp-parser.php` | NEU - Erbt von TP-Parser |
| `Deubner_HP_Services.php` | LP-Parser registriert |
| `includes/class-dhps-renderer.php` | Fallback `lp -> tp` |
| `includes/class-dhps-ajax-proxy.php` | Multi-Service + SSRF-Schutz |
| `public/js/dhps-tp.js` | Liest `data-service`, sendet im AJAX |
| `public/views/services/tp/default.php` | `data-service` Attribut |
| `public/views/services/tp/card.php` | `data-service` Attribut |
| `css/dhps-frontend.css` | LP + LXMIO Recht-Branding |

## QA-Ergebnisse

| Test | Status |
|------|--------|
| LP Parser registriert | OK |
| LP Parser erbt von TP | OK |
| Service-Whitelist taxplain/lexplain/maes | OK |
| TP-JS sendet data-service | OK |
| LP CSS Recht-Branding | OK |
| LXMIO CSS Recht-Branding | OK |
| Andere Services unbeeintraechtigt | OK (mio/lxmio/mmb/mil/tp/maes) |
| SSRF Host-Whitelist | OK |
