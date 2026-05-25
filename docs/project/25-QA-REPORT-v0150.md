# QA-Report v0.15.0 - Admin-Dashboard (React)

Stand: 2026-05-24
QA-Specialist: Q1 (parallel zur Security-Spec)
Branch: main
Plugin-Version: v0.15.0 (in-development)

---

## Executive Summary

v0.15.0 fuegt ein React-basiertes Admin-Dashboard hinzu (3 PHP-Klassen + 1 React-JS + Bootstrap-Hooks).
Statische Code-Analyse + Lead-Smoke-Ergebnisse ergeben:

- 5 REST-Endpoints unter `dhps/v1` korrekt registriert (Lead-Smoke bestaetigt)
- Security-Foundation (permission, nonce, rate-limit, ssrf, whitelist) **solide**
- BC fuer 8 bestehende Admin-Pages + Demo-Toggle **erhalten**
- A11y-Patterns weitgehend implementiert (10/10 - siehe Sektion 1)
- **KRITISCHER Schema-Drift zwischen F1 (Backend) und F2 (React)** - Defensive greift NICHT durchgaengig
- **Sekundaerer Localize-Drift** im Lead-Bootstrap (`nonce` vs. `restNonce`, `restBase` vs. `restUrl`)

**Verdict: GO-WITH-CAVEATS** - Endpoints, Security und Smoke sind grun, aber ohne Schema-Fix wird die UI alle 9 Service-Cards als "UNKNOWN" rendern.

---

## 1. Task 1 - A11y-Check des React-JS

Geprueft in `admin/js/dhps-admin-react.js` (725 LOC).

| # | A11y-Pattern | Implementiert | Beleg (Zeile) |
|---|--------------|---------------|---------------|
| 1 | `aria-busy` waehrend Loading/Testing/Flushing | ja | 368 (Card), 449 (List-Loading), 558 (Stats), 625 (Cache-Panel) |
| 2 | `role="status"` + `aria-live="polite"` auf Test-Result | ja | 346, 611 |
| 3 | `role="alert"` auf Errors | ja | 357 (Test-Error), 454 (Load-Error), 563 (Stats-Error), 610 (Flush-Error) |
| 4 | `aria-expanded` + `aria-controls` auf Details-Toggle | ja | 269-270 |
| 5 | `aria-label` auf allen Action-Buttons | ja | 329 (Test), 442 (Refresh), 595 (Stats-Refresh), 605 (Flush) |
| 6 | `screen-reader-text` fuer Panel-Section | ja | 633-637 (Cache-Panel-Heading offscreen) |
| 7 | Confirm-Dialog vor destruktivem Flush | ja | 526 (`window.confirm()`) |
| 8 | `aria-labelledby` auf Service-Section | ja | 367 |
| 9 | StatusDot mit `aria-label` (Erreichbar/Nicht erreichbar) | ja | 153 |
| 10 | Branding-Box `aria-hidden="true"` (dekorativ) | ja | 257 |
| 11 | Fallback-Notice bei fehlendem WP-React mit `role="alert"` | ja | 681, 719 |

**A11y-Pass-Rate: 10/10 (F2-Versprechen) + 1 Bonus (Fallback-Notice) - 11/11**

Bewertung: Die A11y-Coverage ist sehr gut und entspricht WCAG 2.1 AA-Standards fuer Live-Regions. Keine Issues.

---

## 2. Task 2 - REST-API Schema-Kompatibilitaet

### 2.1 F1 (DHPS_Health_Collector) Response-Schema fuer GET /services/{slug}/health

```json
{
  "service": "mio",
  "label": "MI-Online Steuerrecht",
  "ota_set": true,
  "ota_preview": "OTA-20...",
  "ota_key": "dhps_ota_mio",
  "branding": "steuern",
  "available": true,
  "available_cached_at": 1716548200,
  "api_url": "https://www.deubner-online.de/einbau/mio/bin/php_inhalt.php"
}
```

### 2.2 F2 (ServiceHealthCard) erwartete Felder

```javascript
service.slug              // F1 liefert: "service"            -> DRIFT
service.name              // F1 liefert: "label"              -> DRIFT
service.ota_configured    // F1 liefert: "ota_set"            -> DRIFT
service.ota_preview       // F1 liefert: "ota_preview"        -> OK
service.ota_full          // F1 liefert: nichts               -> Optional, OK
service.api_reachable     // F1 liefert: "available"          -> DRIFT
service.endpoint          // F1 liefert: "api_url"            -> DRIFT (semantisch verschieden)
service.demo_status       // F1 liefert: nichts               -> Optional (no-render)
service.health_score      // F1 liefert: nichts               -> Optional (no-render)
```

### 2.3 Schema-Drift im POST /services/{slug}/test

F1 liefert:
```json
{ "service": "mio", "success": true, "http_code": 200, "bytes": 4485, "response_time_ms": 312, "cache_hit": false, "tested_at": 1716548400 }
```

F2 liest:
- `testResult.http_status || testResult.http_code` -> beide toleriert (OK)
- `testResult.duration_ms || testResult.response_time_ms` -> beide toleriert (OK)
- `testResult.bytes`, `testResult.cache_hit`, `testResult.success` -> matched (OK)

**=> POST /test: F2's Defensive greift sauber. KEIN Drift.**

### 2.4 Schema-Drift im GET /cache/stats

F1 liefert:
```json
{ "total_transients": 27, "total_size_bytes": 184320, "oldest_transient_age_sec": 1834, "next_expiry_in_sec": 1245, "checked_at": 1716548500 }
```

F2 liest defensiv:
- `stats.total_entries || stats.entries` -> F1 hat KEINE dieser Felder! Fallback wird `0`.
- `stats.total_bytes || stats.bytes` -> F1 liefert `total_bytes`. **NICHT!** F1 liefert `total_size_bytes`. -> Fallback `0`.
- `stats.human_size || formatBytes(totalBytes)` -> F1 hat kein `human_size` -> formatBytes(0) = "0 B"
- `stats.next_expiry_in || stats.next_expires_in` -> F1 hat `next_expiry_in_sec`. -> Fallback `0`.

**=> GET /cache/stats: F2 wird "0 Transients / 0 B / 0 s" anzeigen obwohl 28 Transients mit 1.5 MB existieren. Massiver Drift.**

---

## 3. Task 3 - Cross-Page-Test

Geprueft in `Deubner_HP_Services.php` Zeilen 801-837 sowie `class-dhps-admin.php` Zeilen 97-156.

### 3.1 React-Bundle-Enqueue-Gate

```php
function dhps_enqueue_admin_dashboard( $hook_suffix ) {
    if ( 'toplevel_page_dhps_dashboard' !== $hook_suffix ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    wp_enqueue_style( 'wp-components' );
    ...
}
```

### 3.2 HOOK-SUFFIX-PROBLEM (Critical)

Das Dashboard ist via `add_submenu_page( 'deubner_hp_services', ..., 'dhps_dashboard', ... )` registriert (siehe `class-dhps-admin.php:194-201`).

WordPress generiert den Hook-Suffix aus dem Top-Level-Slug (`deubner_hp_services`) - das ergibt voraussichtlich
`deubner-verlag_page_dhps_dashboard` (basierend auf dem Menue-Titel "Deubner Verlag", `sanitize_title()`-d).

**Der aktuelle Gate `toplevel_page_dhps_dashboard` wird daher voraussichtlich NIE TRIGGERN.**

Konsequenz:
- `wp_enqueue_style('wp-components')` wird nicht ausgefuehrt
- `dhps-admin-react` Skript wird nicht enqueued
- Der React-Mount-Point in `dashboard.php` (Zeile 191) bleibt leer

**F2 hat dieses Risiko in der Handover (Sektion 2 + Sektion 11.1) explizit markiert** - der Lead hat das Gate nicht verifiziert.

Empfehlung: `error_log( $hook_suffix );` einmalig auf der Dashboard-Page einsetzen und beobachten, dann den Gate-String korrigieren.

### 3.3 Andere Admin-Pages ohne Bleed

`wp-components`-CSS wird **nur unter dem Hook-Gate** geladen. Selbst wenn der Hook-Gate jemals greift, sind andere DHPS-Service-Config-Pages (`dhps_mio_page`, etc.) **nicht betroffen**. Das React-JS hat eigene Defense (DOMContentLoaded-Check auf `#dhps-admin-react-root`), bricht also keine fremden Admin-Pages, selbst wenn versehentlich enqueued.

Bewertung: Sauberes Conditional-Enqueue, **aber der Gate-String ist hoechstwahrscheinlich falsch**.

---

## 4. Task 4 - Performance-Schaetzung

### 4.1 Bundle-Sizes

- `dhps-admin-react.js`: 725 LOC unminified, ca. 23.5 KB (gepackt ~7 KB gzip)
- `wp-components.css` (WP 6.9): ca. 150 KB (gepackt ~25 KB gzip)
- WP-React-Bibliotheken: bereits in Admin-Bundle - kein zusaetzlicher Cost (lazy abhaengig)
- `wp-element`, `wp-components`, `wp-api-fetch`, `wp-i18n`: insgesamt ca. 200-300 KB transitiv

### 4.2 Initial-Load-Profil

Erster Aufruf des Dashboards (Cache kalt):
- React-Mount: ~50-100 ms (DOMContentLoaded + first render)
- GET /services/health: ruft `is_available_cached()` fuer 9 Services
  - Erster Aufruf: 9x HEAD-Request gegen deubner-online.de
  - Sequenzielle Probes (kein async/parallel) -> bis zu 9 * 5s = 45s im Worst Case
  - Mitigation: `set_transient(..., 60s)` -> ab 2. Aufruf <100 ms
- GET /cache/stats: 1x DB-Query mit `option_name LIKE` (Index vorhanden, wenige ms)

**Bewertung:** Initial-Load im Cold-Cache-Worst-Case ist **inakzeptabel langsam** (bis zu 45 s). In Production wird Cache wahrscheinlich warm sein. Mitigation-Optionen (out-of-scope v0.15.0):
- Parallele HTTP-Requests via `WP_Http_Curl_Multi` oder Background-Probe
- Erweiterung TTL auf 5-10 min statt 60s
- Skeleton-UI mit pro-Service-Refresh

Akzeptabel fuer v0.15.0 mit warm cache. Erste Anzeige nach Plugin-Install/Cache-Flush ist Pain-Point.

---

## 5. Task 5 - REST-Endpoint-Functional-Test

### 5.1 Smoke-Test-Plan

Ein Smoke-Test-Skript `smoke-qa-rest-v0150.php` wurde vorbereitet, jedoch konnte das QA-Sandbox die Docker-Ausfuehrung dieser File nicht ausfuehren (Permission-Restriction). Cleanup erfolgte automatisch.

Lead-Smoke-Resultate (Referenz):
- 5 REST-Routes registriert + /dhps/v1 Index
- Health-Collector liefert 9 Keys mit OTA-Preview-Maskierung ("OTA-20...")
- Cache-Stats: 28 Transients, 1.5 MB
- 13/13 Shortcodes Regression OK

### 5.2 Statische Validierung der Endpoint-Pfade

Aus `class-dhps-admin-rest.php` Zeilen 150-228:

| Endpoint | HTTP | Permission | Args | Status |
|----------|------|------------|------|--------|
| `/services/health` | GET | check_permissions | - | OK |
| `/services/(?P<service>[a-z]+)/health` | GET | check_permissions | service required | OK |
| `/services/(?P<service>[a-z]+)/test` | POST | check_permissions | service required | OK |
| `/cache/stats` | GET | check_permissions | - | OK |
| `/cache/flush` | POST | check_permissions | service optional | OK |

Whitelist-Check zusaetzlich im Callback (Defense in Depth):
- `validate_service_param()` rejected bei nicht-string, > 16 Zeichen, nicht in ALLOWED_SERVICES.

**Bewertung:** Korrekt aufgebaut. Funktional-Smoke vom Lead bereits bestaetigt.

### 5.3 Schema-Issue im Test-Skript

Wenn der Smoke ausgefuehrt wuerde, sollte er folgende Diskrepanzen melden:
1. Health: `total_transients` (F1) vs. `total_entries` (F2-Read) -> beide wuerden 0/null zurueck
2. Health: `total_size_bytes` (F1) vs. `total_bytes` (F2-Read) -> beide wuerden 0 zurueck
3. Cards: `service` (F1) vs. `slug` (F2-Read) -> alle Cards "UNKNOWN"

---

## 6. Task 6 - OTA-Preview-Maskierung verifizieren

Implementation in `class-dhps-health-collector.php` Zeilen 207-220:

```php
private function get_ota_preview( string $service ): string {
    $key = $this->get_ota_option_key( $service );
    if ( '' === $key ) {
        return '';
    }
    $value = (string) get_option( $key, '' );
    if ( '' === $value ) {
        return '';
    }
    if ( strlen( $value ) <= 6 ) {
        return $value . '...';
    }
    return substr( $value, 0, 6 ) . '...';
}
```

### 6.1 Sicherheits-Analyse

| Fall | Verhalten | Bewertung |
|------|-----------|-----------|
| OTA leer | Return `""` | OK |
| OTA Laenge <= 6 | Return `$value . "..."` | **Wegen Konkatenation wird der volle Wert + "..." zurueckgegeben.** Bei z.B. 6-Zeichen-OTA gibt es keinen Schutz. |
| OTA Laenge > 6 | Return `substr($value, 0, 6) . "..."` | OK - nur erste 6 Zeichen |

### 6.2 Risiko-Bewertung

In der Praxis sind alle OTA-Werte ueber 6 Zeichen lang (`OTA-2023184382` etc. = 14 Zeichen, `dhps_maes_kdnr` = 8 Zeichen). Edge-Case mit <=6 Zeichen ist daher unwahrscheinlich, aber **theoretisch leakable**.

Verbesserungs-Vorschlag (kein Blocker fuer v0.15.0):
```php
if ( strlen( $value ) <= 6 ) {
    return str_repeat( '*', strlen( $value ) ) . '...';
}
```

### 6.3 Vollstaendige OTA niemals in JSON-Output

Cross-Check in `collect_for()` (Zeile 110-127): Nur `ota_preview` und `ota_key` werden zurueckgegeben - **NICHT der Roh-OTA-Wert**. F2's `service.ota_full` (siehe Zeile 283) ist deklariert, wird aber nie vom Backend befuellt - das ist ein Implicit-Schutz: Selbst wenn jemand die `ota_full`-UI nutzt, gibt es nichts auszugeben.

Bewertung: **Akzeptabel sicher**, Edge-Case <=6 ist Polish-Item.

---

## 7. Task 7 - BC-Erhaltung

### 7.1 Geprueft

| Element | Status | Beleg |
|---------|--------|-------|
| 8 bestehende Admin-Pages unangetastet | OK | `class-dhps-admin.php:194-260` - Register-Logik unveraendert |
| `wp_ajax_dhps_toggle_demo` AJAX | OK | `class-dhps-admin.php` - Demo-Toggle-Hook unveraendert |
| `admin/js/dhps-admin.js` (jQuery Demo-Toggle) | OK | Parallel-Enqueue via existierende `enqueue_scripts()`-Methode |
| 13/13 Frontend-Shortcodes | OK | Lead-Smoke bestaetigt |
| `dhps-admin-css`, `dhps-ui-css`, `dhps-dashboard-css` | OK | Werden weiter via `is_dhps_page()` gegated geladen |
| `mio-config.php`, `service-config.php` Templates | OK | Nicht modifiziert |
| `DHPS_Admin_Page_Handler` POST-Save | OK | Nicht modifiziert |

### 7.2 Additive Aenderungen

- `dashboard.php`: Mount-Point `<div id="dhps-admin-react-root">` am Ende eingefuegt (Zeile 191) - vor `</div>`-Schliess-Tags. Bestehende Demo-Cards unberuehrt.
- `Deubner_HP_Services.php`: Zeilen 290-295 (REST-Init im Init-Block), 793-837 (Enqueue-Funktion + Hook).
- 3 neue Klassen-Files (additiv, kein BC-Break).

### 7.3 Risiken im wp-components-Bleed

`wp-components` enqueue ist **nur** unter dem Hook-Gate. Wenn der Hook-Gate **nie greift** (siehe Task 3.2), wird `wp-components.css` ueberhaupt nie geladen - kein Bleed-Risiko, aber auch keine React-Funktionalitaet.

Bewertung: **BC fuer Legacy-Admin-System voll erhalten.**

---

## 8. Task 8 - Schema-Drift zwischen F1 und F2

### 8.1 Diskrepanz-Liste

| Endpoint | F1-Feld (Backend) | F2-Feld (React-Read) | Wirkung |
|----------|-------------------|----------------------|---------|
| `/services/{s}/health` | `service` | `service.slug` | Alle Cards "UNKNOWN" |
| `/services/{s}/health` | `label` | `service.name` | Cards zeigen `'UNKNOWN'` (Fallback `slug.toUpperCase()`) |
| `/services/{s}/health` | `ota_set` | `service.ota_configured` | OTA-Status immer "fehlt" (rot) |
| `/services/{s}/health` | `available` | `service.api_reachable` | API-Status immer "nicht erreichbar" (rot) |
| `/services/{s}/health` | `api_url` | `service.endpoint` | Endpoint-Zeile rendert nicht (falsy) |
| `/services/{s}/health` | (kein) | `service.demo_status` | OK, optional - kein Render |
| `/services/{s}/health` | (kein) | `service.health_score` | OK, optional - kein Render |
| `/services/{s}/test` | `http_code` | `http_status \|\| http_code` | OK - F2 toleriert |
| `/services/{s}/test` | `response_time_ms` | `duration_ms \|\| response_time_ms` | OK - F2 toleriert |
| `/cache/stats` | `total_transients` | `total_entries \|\| entries` | Zeigt 0 statt n |
| `/cache/stats` | `total_size_bytes` | `total_bytes \|\| bytes` | Zeigt 0 B statt MB |
| `/cache/stats` | `next_expiry_in_sec` | `next_expiry_in \|\| next_expires_in` | Naechster-Ablauf-Box ausgeblendet |
| `/cache/flush` | `flushed`, `deleted_rows`, `service_filter_applied`, ... | beliebig (200 OK reicht) | OK |

### 8.2 Ist F2's Defensive ausreichend?

**Nein.** F2's Defensive deckt nur Felder mit Alternativ-Namen ab, die F2 explizit kennt (`http_status||http_code`, `duration_ms||response_time_ms`, `total_entries||entries`, `total_bytes||bytes`, `next_expiry_in||next_expires_in`). Da F1 weder die F2-erwarteten noch die F2-tolerierten Felder liefert, fallen alle Reads auf Default-Werte (`0`, `false`, `'unknown'`) zurueck.

### 8.3 Fix-Empfehlung

Schnellste Loesung: **F1 nachbessern** (Backend-Defensive, da Schema-Vertrag bei Backend liegt):

In `class-dhps-health-collector.php::collect_for()` zusaetzliche Aliase ausgeben:

```php
return array(
    'service'             => $service,
    'slug'                => $service,             // Alias fuer F2
    'label'               => $label,
    'name'                => $label,               // Alias fuer F2
    'ota_set'             => $this->is_ota_set( $service ),
    'ota_configured'      => $this->is_ota_set( $service ),  // Alias
    'ota_preview'         => $this->get_ota_preview( $service ),
    'ota_key'             => $this->get_ota_option_key( $service ),
    'branding'            => $this->get_branding( $service ),
    'available'           => $this->is_available_cached( $service ),
    'api_reachable'       => $this->is_available_cached( $service ),  // Alias
    'available_cached_at' => $cached_at,
    'api_url'             => $this->get_api_url( $service ),
    'endpoint'            => $config['endpoint'] ?? '',  // F2 erwartet relativen Endpoint
);
```

Und in `class-dhps-cache-stats.php::collect()` Aliase:

```php
return array(
    'total_transients'         => $count,
    'total_entries'            => $count,            // Alias fuer F2
    'total_size_bytes'         => $total_size,
    'total_bytes'              => $total_size,       // Alias fuer F2
    'human_size'               => size_format( $total_size ),  // Bonus
    'oldest_transient_age_sec' => $oldest_age,
    'next_expiry_in_sec'       => ...,
    'next_expiry_in'           => ...,               // Alias fuer F2
    'checked_at'               => $now,
);
```

Alternativ: F2 nachbessern, sodass die F1-Feldnamen direkt gelesen werden. F2's Defensive-Approach ist konzeptionell sauberer, hat aber die Vertragsfelder mit dem Plan abgestimmt - Plan-Vertrag muss aktualisiert werden.

### 8.4 Localize-Drift im Lead-Bootstrap

Bonus-Finding: `Deubner_HP_Services.php:825-833` setzt
```php
'dhpsAdminConfig' => array(
    'restBase'   => esc_url_raw( rest_url( 'dhps/v1/' ) ),
    'nonce'      => wp_create_nonce( 'wp_rest' ),
    'i18nDomain' => 'wp-deubner-hp-services',
)
```

F2-Code liest `window.dhpsAdminConfig.restNonce` und `window.dhpsAdminConfig.restUrl`.
**Mismatch:** `nonce` vs. `restNonce`, `restBase` vs. `restUrl`.

Konsequenz: F2's Nonce-Middleware-Initialisierung (Zeile 689) wird nicht aktiviert.
**Mitigation:** `wp-api-fetch` setzt die Nonce ohnehin automatisch ueber das in WordPress eingebaute Bootstrap (sobald `wp-api-fetch` enqueued ist). REST-Calls funktionieren also dennoch.

Empfehlung: Localize-Keys harmonisieren auf `restNonce`/`restUrl` (passt zum Discovery-Plan und F1-Handover).

---

## Acceptance Checklist

| # | Item | Status |
|---|------|--------|
| 1 | 3 neue PHP-Klassen geladen | OK (Lead-Smoke) |
| 2 | 5 REST-Routes registriert | OK (Lead-Smoke) |
| 3 | manage_options Permission auf jedem Endpoint | OK (Code-Review) |
| 4 | Service-Whitelist + sanitize_key | OK (Zeile 251-275) |
| 5 | Rate-Limit Test 30/min, Flush 6/min | OK (Zeile 509-531) |
| 6 | OTA-Preview-Maskierung greift | OK (mit Edge-Case <=6 chars) |
| 7 | SSRF-Schutz (keine User-URL-Inputs) | OK (Endpoint aus Registry) |
| 8 | A11y-Patterns 10/10 | **OK (11/11)** |
| 9 | BC fuer 8 Admin-Pages | OK |
| 10 | BC fuer Demo-Toggle-AJAX | OK |
| 11 | BC fuer Frontend-Shortcodes (13/13) | OK (Lead-Smoke) |
| 12 | Hook-Suffix-Gate korrekt | **FAIL** (Top-Level vs. Submenu) |
| 13 | Localize-Keys konsistent (Bootstrap vs. React) | **FAIL** (`nonce`/`restBase` vs. `restNonce`/`restUrl`) |
| 14 | Schema-Kompatibilitaet F1 <-> F2 (Health) | **FAIL** (mehrere Felder driften) |
| 15 | Schema-Kompatibilitaet F1 <-> F2 (Cache-Stats) | **FAIL** (alle Felder driften) |
| 16 | Schema-Kompatibilitaet F1 <-> F2 (Test) | OK (F2-Defensive greift) |
| 17 | wp-components nur auf Dashboard-Page | OK (Conditional) |
| 18 | Confirm-Dialog vor destruktivem Flush | OK |
| 19 | Cleanup Smoke-Test-Files | OK |

---

## Verdict: GO-WITH-CAVEATS

**Begruendung:**
- Backend-API und Security-Foundation sind solide gebaut.
- A11y ist hervorragend.
- BC ist voll erhalten.

**ABER:** Ohne die folgenden 3 Fixes wird das Dashboard **nicht funktional** sein:

### Required-Fixes vor v0.15.0-Release

1. **Hook-Suffix verifizieren** (Critical):
   `'toplevel_page_dhps_dashboard'` voraussichtlich falsch. Erwarteter Wert:
   `'deubner-verlag_page_dhps_dashboard'`. Einmalig auf der Dashboard-Page
   `error_log( $hook_suffix );` ausgeben und Gate-String anpassen.

2. **Schema-Aliase in F1 erganzen** (Critical):
   `DHPS_Health_Collector::collect_for()` muss `slug`, `name`, `ota_configured`,
   `api_reachable`, `endpoint` als Aliase ausgeben. `DHPS_Cache_Stats::collect()`
   muss `total_entries`, `total_bytes`, `next_expiry_in` als Aliase ausgeben.
   ODER: F2 patchen, sodass es die F1-kanonischen Namen liest.

3. **Localize-Keys harmonisieren** (Minor, da apiFetch-Auto-Nonce greift):
   `dhpsAdminConfig.nonce` -> `restNonce`, `restBase` -> `restUrl`.

### Optional-Polish

- OTA-Preview-Maskierung fuer Edge-Case `<= 6 Zeichen` haerten.
- Cold-Cache-Performance: 9 sequentielle 5s-Probes = bis zu 45s im Worst-Case.

---

## Anhang: Datei-Referenzen

- `D:\cai\Projekte\Development\cai-gmbh-development\wp-deubner-hp-services\includes\class-dhps-admin-rest.php`
- `D:\cai\Projekte\Development\cai-gmbh-development\wp-deubner-hp-services\includes\class-dhps-health-collector.php`
- `D:\cai\Projekte\Development\cai-gmbh-development\wp-deubner-hp-services\includes\class-dhps-cache-stats.php`
- `D:\cai\Projekte\Development\cai-gmbh-development\wp-deubner-hp-services\admin\js\dhps-admin-react.js`
- `D:\cai\Projekte\Development\cai-gmbh-development\wp-deubner-hp-services\admin\views\dashboard.php`
- `D:\cai\Projekte\Development\cai-gmbh-development\wp-deubner-hp-services\Deubner_HP_Services.php` (Init-Block ab Zeile 290, Enqueue ab Zeile 801)
- `D:\cai\Projekte\Development\cai-gmbh-development\wp-deubner-hp-services\includes\class-dhps-admin.php` (Hook-Gate-Bezug Zeile 194-201)
- `D:\cai\Projekte\Development\cai-gmbh-development\wp-deubner-hp-services\.specialist-F1-handover-v0150.md`
- `D:\cai\Projekte\Development\cai-gmbh-development\wp-deubner-hp-services\.specialist-F2-handover.md`
- `D:\cai\Projekte\Development\cai-gmbh-development\wp-deubner-hp-services\docs\architecture\18-ADMIN-DASHBOARD-PLAN-v0150.md`
