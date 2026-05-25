# Changelog v0.15.0 - Backend-Admin-Dashboard

## Stand: 2026-05-25

## Mission

Erster Release nach v0.14.x-Abschluss. Neue strategische Achse: **React-basiertes
Admin-Dashboard** via @wordpress/components (WordPress-eigene Bibliothek, kein
eigenes Build-Tool). Erweitert die bestehende Dashboard-Page additiv ohne
bestehende Admin-Pages anzutasten.

## Hauptaenderungen

### 1. 5 REST-Endpoints unter `dhps/v1`

Neue Klasse `DHPS_Admin_REST` registriert via `rest_api_init`:

| # | Methode | Route | Permission | Rate-Limit |
|---|---------|-------|------------|-----------|
| 1 | GET  | `/dhps/v1/services/health` | manage_options | - |
| 2 | GET  | `/dhps/v1/services/{service}/health` | manage_options | - |
| 3 | POST | `/dhps/v1/services/{service}/test` | manage_options | 30/min/User |
| 4 | GET  | `/dhps/v1/cache/stats` | manage_options | - |
| 5 | POST | `/dhps/v1/cache/flush` | manage_options | 6/min/User |

Service-Whitelist (9 Slugs), `sanitize_key` + Laengen-Limit 16 Zeichen,
WP-REST-Nonce (X-WP-Nonce Header).

### 2. Health-Collector pro Service

Neue Klasse `DHPS_Health_Collector::collect_for($service)` liefert:
- `service` / `slug` (Service-Identifier)
- `label` / `name` (Anzeigename)
- `ota_set` / `ota_configured` (boolean)
- `ota_preview` (erste 6 Zeichen + `...`, OTA wird NIE vollstaendig exposed)
- `ota_key` (Option-Name)
- `branding` ('steuern' | 'recht' | 'medizin')
- `available` / `api_reachable` (HEAD-Probe mit 60s Transient-Cache)
- `available_cached_at` (Timestamp)
- `api_url` / `endpoint`

Availability-Probe mit 5s-Timeout, 60s Transient-Cache (verhindert UI-Block).

### 3. Cache-Stats global

Neue Klasse `DHPS_Cache_Stats::collect()` liefert (alles plugin-global, nicht
pro Service - Discovery v0.15.1):
- `total_transients` / `total_entries` / `entries` (Count)
- `total_size_bytes` / `total_bytes` / `bytes` (Bytes)
- `oldest_transient_age_sec`
- `next_expiry_in_sec` / `next_expiry_in` / `next_expires_in` (Sekunden)
- `checked_at` (Timestamp)

`flush()` loescht alle `_transient_dhps_*` Eintraege via einzigen `$wpdb->query`
mit `$wpdb->prepare()`.

### 4. React-Dashboard ohne Build-Tool

Neue Datei `admin/js/dhps-admin-react.js` (725 LOC):

- IIFE-Wrapper, Strict-Mode, ASCII-only
- React via `wp.element.createElement` (kein Webpack/Vite noetig)
- Dependencies: `wp-element` + `wp-components` + `wp-api-fetch` + `wp-i18n`
- 4 React-Komponenten + 1 Helper:
  - `App` (Root, Panel-Layout)
  - `ServiceHealthList` (Container, REST-Load, Refresh)
  - `ServiceHealthCard` (pro Service: OTA-Dot, API-Dot, Endpoint, Test-Button)
  - `CacheStatsPanel` (Metriken-Grid, Refresh + Flush mit Confirm)
  - `StatusDot` (factory function)
- React-18 `createRoot`-Fallback falls `wp.element.render` deprecated
- Defensives REST-Schema-Reading (toleriert beide F1/F2-Schemas)
- 10 A11y-Patterns implementiert (siehe QA-Report)

### 5. Mount-Point in dashboard.php

Einfache Erweiterung der bestehenden Dashboard-Page:
```html
<div id="dhps-admin-react-root" data-dhps-admin-mount="dashboard"></div>
```
Wird vor dem schliessenden `</div>` der Content-Area eingefuegt.

### 6. Plugin-Main Wire-Up

- DI-Injection in `dhps_init()` (nach AJAX-Proxy, vor Demo-Manager)
- `admin_enqueue_scripts` Hook fuer conditional Enqueue (nur auf
  `dhps_dashboard`-Page)
- `wp_localize_script` Bridge mit `restUrl`, `restNonce`, `i18nDomain`

## Pre-Release-Fixes (Critical-Bugs aus QA + Security-Audit)

QA-Audit identifizierte 3 Critical-Bugs die das Dashboard funktionsunfaehig
gemacht haetten. Alle vor Release behoben:

### Critical-1: Hook-Suffix-Gate
**Problem**: `'toplevel_page_dhps_dashboard'` matched nicht im realen Submenu-
Setup (Hook-Suffix ist `deubner-verlag_page_dhps_dashboard`).
**Fix**: `strpos($hook_suffix, 'dhps_dashboard')` matcht beide Varianten.

### Critical-2: Schema-Drift Health
**Problem**: F1 lieferte `service`/`label`/`ota_set`/`available`/`api_url`;
F2 erwartete `slug`/`name`/`ota_configured`/`api_reachable`/`endpoint`.
Resultat: alle Cards wuerden "UNKNOWN" rendern.
**Fix**: F1 Health-Collector liefert beide Schluesselnamen additiv (BC-sicher).

### Critical-3: Schema-Drift Cache-Stats
**Problem**: F1 lieferte `total_transients`/`total_size_bytes`/`next_expiry_in_sec`;
F2 erwartete `total_entries||entries`/`total_bytes||bytes`/`next_expiry_in||next_expires_in`.
**Fix**: F1 Cache-Stats liefert alle 3 Varianten additiv.

### Minor-1/2 (SEC): Localize-Bridge + i18nDomain
**Problem**: `dhpsAdminConfig.restBase`/`nonce` vs. JS-Erwartung `restUrl`/`restNonce`.
i18nDomain falsch (`wp-deubner-hp-services` statt `deubner_hp_services`).
**Fix**: Schluesselnamen synchronisiert.

## QA + Security

### QA-Specialist Ergebnis (nach Fixes)

**Verdict**: GO

- Vor Fix-Anwendung: GO-WITH-CAVEATS (3 Critical + 1 Minor + 2 Polish)
- Nach Fix-Anwendung: alle Critical/Major gefixt
- A11y-Pass-Rate: **11/11** (10 F2-Versprechen + 1 Bonus Fallback-Notice)
- WCAG 2.1 AA-konform
- 13/13 Shortcodes Regression OK

Detail: [docs/project/25-QA-REPORT-v0150.md](25-QA-REPORT-v0150.md)

### Security-Audit Ergebnis

**Verdict**: GO-WITH-FIXES (Fixes alle angewendet)

- 0 Critical, 0 High, 1 Medium (Localize-Drift, gefixt), 3 Low (2 gefixt, 1 v0.15.1)
- 4 Trust-Decisions:
  - TD-1: React via `wp.element` ohne Build-Pipeline (Bundle unminified, OK fuer Admin)
  - TD-2: Cache-Stats global statt pro Service (v0.15.1)
  - TD-3: OTA-Preview-Maskierung (6 Zeichen, Edge-Case <=6 in v0.15.1 fixen)
  - TD-4: REST-Bundle laeuft NUR fuer manage_options-User

Kern-Verifizierungen:
- OTA-Werte werden NIE komplett im JSON-Output exposed (nur ersten 6 chars + "...")
- Rate-Limiting korrekt (30/min Test, 6/min Flush, per User-ID)
- SSRF-Schutz: nur Registry-Endpoints, keine freie URL-Eingabe
- DB-Queries via `$wpdb->prepare()` mit festen LIKE-Patterns
- React-JS: kein eval, kein innerHTML mit User-Input

Detail: [docs/project/26-SECURITY-AUDIT-v0150.md](26-SECURITY-AUDIT-v0150.md)

## Backward Compatibility

**Vollstaendig BC**:

- 8 bestehende Admin-Pages unangetastet
- Demo-Toggle-AJAX (`wp_ajax_dhps_toggle_demo`) funktioniert weiter
- `admin/js/dhps-admin.js` (jQuery Demo-Toggle) lebt parallel weiter
- `DHPS_Admin_Page_Handler` unveraendert
- 13/13 Frontend-Shortcodes Regression OK
- Keine neuen DB-Tabellen, nur neue Transients (`dhps_health_avail_*` + Rate-Limit-Counter)

## Performance

- React-Bundle: 23.5 KB (unminified, OK fuer Admin-Only)
- WP-Components-Bundle: ~50-100 KB (WordPress-Standard, gecached)
- Initial-Load Dashboard: erste 9 Health-Probes parallel (durch `apiFetch`),
  60s Cache verhindert Re-Probes
- Worst-Case Cold-Cache: ~5s warten (parallel HEAD-Probes mit Timeout)
- Cache-Stats-DB-Query: O(n) ueber `wp_options` mit `option_name LIKE`
  (akzeptabel bis ~10k Transient-Eintraege)

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `includes/class-dhps-admin-rest.php` | 5 REST-Endpoints + Permissions + Rate-Limits (~430 LOC) |
| `includes/class-dhps-health-collector.php` | Health-Daten pro Service (~280 LOC nach Fix-2) |
| `includes/class-dhps-cache-stats.php` | Globale Cache-Statistik (~150 LOC nach Fix-3) |
| `admin/js/dhps-admin-react.js` | 4 React-Komponenten + 1 Helper (725 LOC) |
| `docs/architecture/18-ADMIN-DASHBOARD-PLAN-v0150.md` | Discovery |
| `docs/project/25-QA-REPORT-v0150.md` | QA-Report |
| `docs/project/26-SECURITY-AUDIT-v0150.md` | Security-Audit |
| `docs/project/27-CHANGELOG-v0150.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version-Bump + DI-Block fuer Admin-REST + `dhps_enqueue_admin_dashboard` Funktion (mit Hook-Gate-Fix) + Localize-Bridge mit korrekten Keys |
| `README.md` | Version-Bump |
| `admin/views/dashboard.php` | Mount-Point `<div id="dhps-admin-react-root">` am Ende der Content-Area |

## v0.15.1+ Tech-Debt (verschoben)

| # | Ticket | Quelle |
|---|--------|--------|
| 1 | Cache-Stats pro Service (Cache-Key-Schema-Migration) | Discovery |
| 2 | Live-Preview pro Service (iframe oder Inline) | Discovery |
| 3 | Last-API-Response-Tracking (Timestamp + Bytes + Status) | Discovery |
| 4 | Echter Cache-Hit/Miss-Counter | Discovery |
| 5 | Health-History (Trend ueber Zeit) | Discovery |
| 6 | OTA-Preview Edge-Case `<=6` Zeichen | SEC-Audit |
| 7 | Rate-Limiter Sliding-Window-Drift | SEC-Audit |
| 8 | Rate-Limiter Race-Condition Counter-Increment | SEC-Audit |

Plus die 6 bestehenden Tech-Debt-Tickets aus v0.14.x.

## Specialist-Team-Pattern (Iteration 6)

| Phase | Specialists |
|-------|-------------|
| P1 Discovery | 1 (Admin-System-Analyse + Scope-Festlegung) |
| P2 Implementation | 2 parallel (F1 Backend-REST + F2 React-Frontend) |
| P3 Composition (Lead) | direct (Plugin-Main DI + Mount-Point + Enqueue + Smoke) |
| P4 QA + Sec | 2 parallel |
| P5 Release (Lead) | direct (4 Critical/Minor-Fixes + CHANGELOG + Memory + Tag) |

**Lehre nach 6 Iterationen**: Bei parallel-developed Specs (F1 + F2) ist
Schema-Drift erwartbar. Vorbeugung: explizites Schema-Vertrag-Dokument
vorab, ODER one-of-them autoritativ machen (F1 Backend). Defensives Reading
allein reicht nicht.

## Bilanz v0.15.0

- **5 REST-Endpoints** + 3 PHP-Klassen + 4 React-Komponenten
- **Service-Health-Monitor + API-Test + Cache-Stats** funktional
- **0 Critical/High** Security-Issues
- **A11y 11/11** (WCAG 2.1 AA)
- **13/13 Shortcodes** Regression OK
- **8 bestehende Admin-Pages** unangetastet (BC)
- **4 Pre-Release-Fixes** angewendet (3 Critical + 1 Minor)
- **8 Tech-Debt-Tickets** fuer v0.15.1+
- **OTA-Preview-Maskierung** (Audit-Trail-Schutz)
- **Rate-Limiting** + Service-Whitelist + SSRF-Schutz

## Naechste Schritte

| Option | Scope |
|--------|-------|
| **v0.15.1** | Live-Preview pro Service + OTA-Edge-Case-Fix + andere v0.15.0-Tech-Debt |
| **v0.16.0** | Einheitliches Datenmodell (User-Wunsch seit Anfang) |
| **v0.14.5** | Tech-Debt-Cleanup aus v0.14.x (6 Tickets) |
