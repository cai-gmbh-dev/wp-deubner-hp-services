# Admin-Dashboard-Plan v0.15.0 (Discovery)

## Stand: 2026-05-24
## Status: Architektur-Vorschlag (KEINE Code-Aenderungen)
## Zielversion: v0.15.0 - Backend-Admin-Dashboard
## Plattform: WP 6.9.4, Elementor 4.0.1, PHP 8.3.30, React 18 (mitgeliefert von WP)

---

## 1. Status-Quo: Aktuelles Admin-System

### 1.1 Menue-Struktur (registriert in `DHPS_Admin::register_menu()`)

```
Deubner Verlag  (Top-Level, Position 5, SVG-Icon, manage_options)
+-- Dashboard         page=dhps_dashboard       render_dashboard()
+-- Mi-Online         page=dhps_mio_page        render_page('mio') -> render_mio_page()
+-- Merkblaetter      page=dhps_mmb_page        render_page('mmb')
+-- Infografiken      page=dhps_mil_page        render_page('mil')
+-- Tax-Videos        page=dhps_tp_page         render_page('tp')   [+ Sibling tpt]
+-- Tax-Rechner       page=dhps_tc_page         render_page('tc')
+-- Aerzte-Info       page=dhps_maes_page       render_page('maes')
+-- Lexplain          page=dhps_lp_spage        render_page('lp')
```

URL-Pfad der Dashboard-Seite: `wp-admin/admin.php?page=dhps_dashboard`

Plugin-Page-Slug-Whitelist in `DHPS_Admin::$plugin_pages` (Array; nicht in der Registry):
`dhps_dashboard, dhps_mio_page, dhps_mmb_page, dhps_mil_page, dhps_tp_page, dhps_tc_page, dhps_maes_page, dhps_lp_spage`

### 1.2 Files (Admin-Schicht v0.14.4)

| Datei | Rolle | LOC | Hinweis |
|-------|-------|-----|---------|
| `includes/class-dhps-admin.php` | Hooks, Menue-Registrierung, CSS/JS-Enqueue, Page-Routing | 600 | Konstruktor nimmt DHPS_Demo_Manager via DI |
| `includes/class-dhps-admin-page-handler.php` | POST-Verarbeitung, Nonce-Pruefung, get/save Options | 228 | Nicht-statisch, wird in DHPS_Admin instanziiert |
| `admin/views/dashboard.php` | Status-Cards mit Demo-Buttons (Kategorie-Gruppen) | 189 | Klassisch PHP-rendered, Inline-AJAX-Nonce |
| `admin/views/service-config.php` | Generisches Config-Formular (Felder aus Registry, optional Sibling-Sections) | 240 | Deubner-Branding-Look |
| `admin/views/mio-config.php` | Spezial-Template fuer MIO + LXMIO (2 Formulare nebeneinander) | 242 | Eigene Layout-Logik |
| `admin/views/partials/header.php` | Gemeinsamer Branding-Header (Logo, Version, Nav) | 39 | In allen Admin-Pages eingebunden |
| `admin/js/dhps-admin.js` | jQuery-AJAX fuer Demo-Toggle-Buttons | 57 | Nutzt `ajaxurl` global |

CSS-Bundle fuer Admin:
- `css/dhps-design-tokens.css` (CSS-Variablen)
- `css/dhps_admin.css` (Legacy-Admin-Styles)
- `css/dhps-ui.css` (UI-Framework)
- `css/dhps-dashboard.css` (Deubner-Branding)

### 1.3 Routing-Modell

- Alle Pages laufen ueber `add_submenu_page()` mit Render-Closures.
- Keine WordPress Settings-API (`register_setting`, `do_settings_sections`) - alles via Custom-Forms + Nonce + `update_option()` im `DHPS_Admin_Page_Handler`.
- Demo-Toggle ist der einzige AJAX-Endpoint im Admin: `wp_ajax_dhps_toggle_demo` -> `DHPS_Admin::handle_demo_toggle()`.
- Settings werden alle als einzelne `wp_options`-Eintraege gespeichert (Keys aus `DHPS_Service_Registry::get_service()['admin_fields'][n]['option_key']`).

### 1.4 Wiederverwendbare Bausteine

- `DHPS_Service_Registry::get_services()` liefert alle 9 Services inkl. `admin_page`, `category`, `auth_option`, `auth_type`, `endpoint`, `default_params`, `shop_url`, `icon`, `admin_fields`.
- `DHPS_Demo_Manager` bietet bereits `get_all_statuses()`, `get_service_status($slug)`, `is_demo_active()`, `activate_demo()`, `deactivate_demo()`, `check_expired_demos()`, `get_demo_duration()`.
- `DHPS_API_Client::fetch_content()` kapselt Cache-Aside-Pattern; `DHPS_Legacy_API::is_available()` macht HEAD-Test gegen `https://www.deubner-online.de/`.
- `DHPS_MMB_AJAX_Handler` (v0.14.0) ist das Vorbild fuer SSRF-saubere Service-Whitelist + Rate-Limit.

---

## 2. React-Integration-Strategie

### 2.1 Warum @wordpress/components statt eigenes Build

- WP 6.9.4 liefert React 18 via `wp-element` mit. Kein npm/webpack/Vite noetig.
- `@wordpress/components` bietet produktionsreife UI: `Card`, `CardBody`, `Button`, `Notice`, `Spinner`, `Panel`, `TabPanel`, `SelectControl`, `__experimentalConfirmDialog`.
- Alle Skripte sind als WordPress-Handles registriert (`wp-element`, `wp-components`, `wp-api-fetch`, `wp-i18n`, `wp-data`, `wp-icons`).
- Stylesheet `wp-components` muss mit-enqueued werden, damit die Components ihr Default-Styling bekommen.

### 2.2 Enqueue-Pattern (Vorschlag)

Erweitert `DHPS_Admin::enqueue_scripts()` um einen Branch fuer die neue Dashboard-Page:

```php
if ( $current_screen->id === 'deubner-verlag_page_dhps_dashboard' ) {
    wp_enqueue_style( 'wp-components' );

    wp_register_script(
        'dhps-admin-react',
        DEUBNER_HP_SERVICES_URL . 'admin/js/dhps-admin-react.js',
        array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-data' ),
        DEUBNER_HP_SERVICES_VERSION,
        true
    );

    wp_set_script_translations( 'dhps-admin-react', 'deubner_hp_services' );

    // Konfigurations-Bridge (Services, Nonces, REST-Route, i18n-Strings).
    wp_localize_script( 'dhps-admin-react', 'dhpsAdminConfig', array(
        'restUrl'   => esc_url_raw( rest_url( 'dhps/v1/' ) ),
        'restNonce' => wp_create_nonce( 'wp_rest' ),
        'services'  => DHPS_Service_Registry::get_services(),
        'version'   => DEUBNER_HP_SERVICES_VERSION,
    ) );

    wp_enqueue_script( 'dhps-admin-react' );
}
```

### 2.3 Datei-Layout (neu)

```
admin/
  js/
    dhps-admin.js                 (bestehend, jQuery, Demo-Toggle - bleibt)
    dhps-admin-react.js           (NEU: React-Bundle, ohne Build)
  css/
    dhps-admin-react.css          (NEU: BEM-Layer um wp-components herum)
  views/
    dashboard.php                 (bestehend - wird ein Mount-Point bekommen)
    dashboard-react.php           (OPTIONAL: separate Page, BC-sicher)
```

### 2.4 React-ohne-Build-Strategie

Da WP React + JSX-Pragma nicht via Bundler bereitstellt, schreiben wir den Code als **`React.createElement`-Aufrufe** oder verwenden den **HTM-Tagged-Template** (`htm/preact`-Variante, ~1KB). Empfohlen ist `wp.element.createElement` (`wp.element` ist die globale Bridge zu React 18).

Beispiel (vereinfacht):

```js
( function( wp ) {
    const { createElement: h, render, useState, useEffect } = wp.element;
    const { Card, CardBody, Button, Spinner, Notice } = wp.components;
    const { __ } = wp.i18n;
    const apiFetch = wp.apiFetch;

    apiFetch.use( apiFetch.createNonceMiddleware( window.dhpsAdminConfig.restNonce ) );

    function ServiceHealthCard( props ) {
        // ...
    }

    function App() {
        return h( 'div', { className: 'dhps-react-app' },
            h( ServiceHealthCard, { service: 'mio' } )
        );
    }

    document.addEventListener( 'DOMContentLoaded', function() {
        const mount = document.getElementById( 'dhps-admin-react-root' );
        if ( mount ) {
            render( h( App ), mount );
        }
    } );
} )( window.wp );
```

Vorteil: kein Build-Tool, sofort lauffaehig. Trade-off: kein JSX, etwas verbosere Syntax. Bei staerkerem Component-Wachstum kann optional spaeter ein Vite-/wp-scripts-Build nachgezogen werden (separater Schritt, nicht Teil v0.15.0).

---

## 3. Datenquellen-Inventar

### 3.1 Verfuegbar (kann direkt gelesen werden)

| Datum | Quelle | Aufruf |
|-------|--------|--------|
| Service-Definitionen (9 Services) | `DHPS_Service_Registry::get_services()` | statisch verfuegbar |
| Auth-Token / Konfig pro Service | `get_option( $svc['auth_option'], '' )` | pro Service |
| Demo-Status (active/demo/inactive) | `DHPS_Demo_Manager::get_all_statuses()` | aggregiert |
| Demo-Dauer (Tage) | `DHPS_Demo_Manager::get_demo_duration()` | global |
| API-Erreichbarkeit (Base-URL) | `DHPS_Legacy_API::is_available()` | HEAD-Test mit 5s Timeout |
| Transient-Inventar (DHPS-prefixed) | direkter DB-Query auf `wp_options` (`option_name LIKE '_transient_dhps_%'`) | mit `$wpdb->prepare` |
| Cache-Key-Generierung | `DHPS_Cache::generate_key( $endpoint, $params )` | deterministisch |

### 3.2 Muss neu gebaut werden

| Datum | Vorschlag | Aufwand |
|-------|-----------|---------|
| **Cache-Hit/Miss-Counter** | Optional: `set_transient('dhps_stats_hits_{svc}', n, DAY)`-Counter erhoehen im `DHPS_API_Client::fetch_content()`-Hit-Pfad. v0.15.0: NUR Existenz-Zaehlung (Anzahl Transients pro Service via Key-Prefix), kein echter Hit/Miss-Counter. | M |
| **Letzte-API-Antwort-Tracking** | NEU: Bei jedem `fetch_content()`-Aufruf `update_option('dhps_last_api_{svc}', ['ts','bytes','status','duration_ms'])`. Optional, Default aus. v0.15.0: WEGLASSEN, nur Test-Tool-Ergebnis darstellen. | S |
| **Service-Health-Score** | Berechnet aus: OTA-gesetzt (1/0), Demo-Status, API erreichbar (1/0), Test-Erfolg (1/0). Berechnung im REST-Endpoint. | S |
| **Render-Test (Bytes-Probe)** | `do_shortcode('[mio]')` in `ob_start()`-Capture; Bytes + Render-Zeit messen. ACHTUNG: Frontend-CSS muss im Admin nicht geladen werden, da nur die Render-Pipeline gemessen wird. | M |
| **API-Test-Endpoint** | Neuer REST-Endpoint, der gezielt `DHPS_API_Client::fetch_content( $svc->endpoint, [...], 0 )` (Cache-TTL=0 = no-cache-storage) macht und Roh-Response-Metriken liefert (Bytes, Status, Duration). | M |

### 3.3 Transient-Inventar-Query

```php
// Anzahl + Gesamtgroesse aller DHPS-Transients.
global $wpdb;
$transients = $wpdb->get_results(
    "SELECT option_name, LENGTH(option_value) AS bytes
     FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_dhps_%'
     AND option_name NOT LIKE '_transient_timeout_dhps_%'"
);
// Pro Service-Zuordnung: Transient-Keys sind dhps_{md5} - es gibt keine
// direkte Service-Assoziation. Loesung: API-Client erweitern, sodass Keys
// Service-Prefix bekommen (BC-Break in v0.16.0). Fuer v0.15.0: nur Gesamt-
// Anzahl + Gesamtgroesse + Top-10-Eintraege darstellen.
```

**Wichtige Einschraenkung:** Die aktuellen Cache-Keys (`dhps_{md5}`) lassen sich nicht eindeutig einem Service zuordnen, weil `generate_key()` aus Endpoint + Params hasht. Konsequenz fuer v0.15.0: Cache-Stats sind **Plugin-global**, nicht pro Service.

**Mitigation fuer v0.15.1:** Cache-Key-Schema erweitern (`dhps_{svc}_{md5}`) - das ist allerdings ein BC-Break fuer existierende Caches (alle Eintraege werden auf einmal MISS). In v0.15.0 NICHT umsetzen.

---

## 4. Backend-API-Design

### 4.1 Entscheidung: REST > AJAX

**Begruendung:**
- React-typisch via `wp.apiFetch` + Nonce-Middleware (`wp_rest`).
- Klares Schema (`permission_callback`, `args`) statt POST-Parsen.
- Mehrere Endpoints sauber unter einem Namespace.
- AJAX bleibt fuer Legacy: Demo-Toggle (`wp_ajax_dhps_toggle_demo`) **unveraendert**.

### 4.2 REST-Namespace: `dhps/v1`

| Route | Methode | Permission | Zweck |
|-------|---------|------------|-------|
| `/services` | GET | `manage_options` | Liste aller Services + Status (aggregiert) |
| `/services/(?P<slug>[a-z]+)/health` | GET | `manage_options` | Health-Bundle eines Services (OTA-gesetzt, Demo-Status, API-erreichbar, Cache-Eintraege) |
| `/services/(?P<slug>[a-z]+)/test` | POST | `manage_options` | API-Test-Request (mit Rate-Limit) |
| `/cache/stats` | GET | `manage_options` | Plugin-Cache-Inventar (Anzahl, Bytes, Top-N) |
| `/cache/flush` | POST | `manage_options` | `DHPS_Cache::flush()` |

### 4.3 Response-Schemas

**GET `/services`**
```json
[
  {
    "slug": "mio",
    "name": "MI-Online Steuerrecht",
    "category": "steuern",
    "demo_status": "active",
    "ota_configured": true,
    "admin_url": "...?page=dhps_mio_page"
  }
]
```

**GET `/services/{slug}/health`**
```json
{
  "slug": "mio",
  "ota_configured": true,
  "ota_preview": "OTA-202...",
  "demo_status": "active",
  "api_reachable": true,
  "api_last_check": 1716530400,
  "cache_entries_global": 87,
  "endpoint": "einbau/mio/bin/php_inhalt.php",
  "health_score": 100
}
```

**POST `/services/{slug}/test`**
```json
{
  "success": true,
  "http_status": 200,
  "duration_ms": 312,
  "bytes": 18420,
  "cache_hit": false,
  "tested_at": 1716530450
}
```

**GET `/cache/stats`**
```json
{
  "total_entries": 87,
  "total_bytes": 1283474,
  "human_size": "1.2 MB",
  "top_entries": [
    { "key": "dhps_abc...", "bytes": 18420, "expires_in": 2840 }
  ]
}
```

### 4.4 Rate-Limit fuer Test-Endpoint

Analog zu `DHPS_MMB_AJAX_Handler` (60/min pro IP). Fuer Admin-Test: konservativ 30/min pro User-ID. Transient-Key: `dhps_admin_test_rate_{user_id}`.

### 4.5 Permissions

- Alle Endpoints: `permission_callback = function() { return current_user_can( 'manage_options' ); }`.
- `wp.apiFetch.createNonceMiddleware( wpRestNonce )` setzt Nonce automatisch.
- Cache-Flush ist destruktiv -> ggf. `__experimentalConfirmDialog` im React-UI als Frontend-Sicherung (keine Backend-Implikation).

---

## 5. React-Komponenten-Inventar

### 5.1 Komponenten-Liste

| Komponente | Zweck | wp.components-Bausteine |
|------------|-------|--------------------------|
| `App` | Root, TabPanel mit 3 Tabs (Health / Cache / Test) | `TabPanel`, `Notice` |
| `HealthOverview` | Grid aller 9 Services mit Health-Score | `Card`, `CardBody`, `Flex` |
| `ServiceHealthCard` | Eine Karte pro Service: Score-Badge, Detail-Liste, Test-Button | `Card`, `Badge`, `Button`, `Spinner` |
| `HealthScoreBadge` | Farbiges Badge (gruen 100, gelb 50-99, rot <50) | (custom) |
| `ServiceDetailList` | Key-Value-Liste: OTA, Demo, API-Reachable, Cache-Count | `__experimentalText`, Custom |
| `ApiTestButton` | Test-Request + Ergebnis-Anzeige | `Button`, `Notice`, `Spinner` |
| `ApiTestResult` | Tabelle mit HTTP-Status / Bytes / Duration / Cache-Hit | Custom |
| `CacheStatsPanel` | Anzeige Anzahl + Bytes + Top-N + Flush-Button | `Panel`, `PanelBody`, `Button` |
| `CacheFlushButton` | Mit Confirm-Dialog | `Button`, `__experimentalConfirmDialog` |

### 5.2 State-Management

- Lokal via `useState` + `useEffect` pro Komponente (Health-Status, Test-Ergebnis).
- KEIN Redux-Store / `wp.data`-Store noetig in v0.15.0 (zu wenig Cross-Component-State).
- API-Calls via `wp.apiFetch` (cached durch Browser).

### 5.3 i18n

- Alle Strings via `wp.i18n.__('...', 'deubner_hp_services')`.
- `wp_set_script_translations()` haengt PO-File-Generierung an (kann v0.15.0 noch leer bleiben - Strings sind Deutsch hardcoded oder via __()).

### 5.4 BEM-Layer

`css/dhps-admin-react.css` als duenne Schicht, ueberschreibt nur was noetig:
- `.dhps-health-card__score--red/yellow/green`
- `.dhps-react-app` Root-Container-Spacing
- KEINE umfangreichen Component-Overrides - `wp.components`-Default-Styling reicht.

---

## 6. PHP-Admin-Erweiterung

### 6.1 Empfehlung: Bestehende Dashboard-Page erweitern (kein neuer Menue-Eintrag)

**Begruendung:**
- User-Workflow bleibt: ein Klick auf "Dashboard" -> alles sichtbar.
- Bestehende Demo-Cards behalten ihre Position (BC).
- Neue React-Sections kommen UEBER oder UNTER die Demo-Cards.

### 6.2 Konkret: dashboard.php-Erweiterung (Variante A, empfohlen)

```php
// In dashboard.php nach dem bestehenden Markup:
?>
<div id="dhps-admin-react-root"
     data-current-tab="health"
     class="dhps-react-mount">
    <!-- React rendert hier hinein. Skeleton-Placeholder als Fallback: -->
    <p><?php esc_html_e( 'Lade Service-Monitor...', 'deubner_hp_services' ); ?></p>
</div>
<?php
```

Die bestehenden Demo-Cards bleiben **unberuehrt** (BC). Der React-Bereich erweitert nur.

### 6.3 Alternative: Eigene Subseite (Variante B, nicht empfohlen)

```php
add_submenu_page(
    'deubner_hp_services',
    'Service-Monitor',
    'Service-Monitor',
    'manage_options',
    'dhps_monitor',
    array( $this, 'render_monitor' )
);
```

Trade-off: 9. Menue-Eintrag (Top-Level-Menue wird voller), aber sauberer Concern-Split. Variante A wird gewaehlt, weil das Dashboard ohnehin der Einstieg ist.

### 6.4 REST-Routes registrieren

Neue Klasse `DHPS_Admin_REST` mit `register()`-Methode, die in `dhps_init()` aufgerufen wird:

```php
$admin_rest = new DHPS_Admin_REST( $client, $cache, $api, $demo_manager );
add_action( 'rest_api_init', array( $admin_rest, 'register_routes' ) );
```

### 6.5 Datei-Bilanz (NEU)

```
includes/
  class-dhps-admin-rest.php       (NEU, ~300 LOC - 5 Endpoints)
  class-dhps-health-collector.php (NEU, ~150 LOC - Health-Score-Logik)
  class-dhps-cache-stats.php      (NEU, ~120 LOC - Transient-Inventar-Query)
admin/
  js/
    dhps-admin-react.js           (NEU, ~600 LOC - alle Komponenten)
  css/
    dhps-admin-react.css          (NEU, ~80 LOC)
admin/views/
  dashboard.php                   (GEAENDERT, +5 Zeilen Mount-Point)
```

---

## 7. Scope-Empfehlung v0.15.0 vs v0.15.1

### 7.1 v0.15.0 (Empfohlen)

| Feature | Aufwand | Begruendung |
|---------|---------|-------------|
| **Service-Health-Monitor** | 2 Tage | Zentraler Mehrwert. Alle Datenquellen vorhanden (Demo, OTA, API-Reachable). |
| **API-Test-Tools** | 1.5 Tage | Mittlerer Aufwand. REST-Endpoint mit Rate-Limit + React-Button + Ergebnis-Anzeige. |
| **Cache-Statistik** | 1 Tag | Wenig Aufwand (Plugin-global, ohne Service-Aufschluesselung). Inkl. Flush-Button. |
| REST-API-Foundation | 0.5 Tage | `DHPS_Admin_REST`-Klasse mit 5 Routes als Foundation. |
| Asset-Enqueue + Bootstrap | 0.5 Tage | wp-components + wp-element + apiFetch + Localize. |
| QA + Security-Audit | 0.5 Tage | Permission, Nonce, Rate-Limit pruefen. |
| Doku + CHANGELOG | 0.5 Tage | Roadmap-konformes CHANGELOG-v0150. |

**Gesamt: 5-7 Arbeitstage** - passt zur User-Vorgabe "1 Woche".

### 7.2 v0.15.1 (Verschoben)

| Feature | Begruendung Verschiebung |
|---------|--------------------------|
| **Live-Preview pro Service** | Risiko: Frontend-CSS-Loading im Admin -> Konflikt mit `wp-admin`-Styles. Loesung erfordert iframe + isolierter Render-Context. Eigene Iteration mit eigenem Sicherheitsmodell (Capability-Check + CSP-Header). |
| **Cache-Stats pro Service** | Benoetigt Cache-Key-Schema-Aenderung (BC-Break). Eigene Iteration. |
| **Last-API-Response-Tracking** | Erfordert Instrumentierung in `DHPS_API_Client::fetch_content()`. Eigene Iteration. |
| **Echter Cache-Hit/Miss-Counter** | Wie oben - Instrumentierung im Client. |
| **Health-History (7-Tage-Trend)** | Benoetigt eigene Tabelle / Custom-Post-Type. Eigene Iteration. |

### 7.3 Out-of-Scope fuer v0.15.x komplett

- WP-CLI-Integration (`wp dhps health`)
- Mehrsprachigkeit (POT-File-Update)
- Settings-API-Migration der bestehenden Formulare

---

## 8. Specialist-Aufteilung-Empfehlung

### Empfehlung: 3 parallele Specialists + 1 sequentieller Lead

**Begruendung:** Backend (REST/Health/Cache), Frontend (React), und PHP-Bootstrap (Enqueue + Mount-Point) sind orthogonal und koennen parallel laufen. Erst nach Implementierung wird zusammengeschaltet.

| # | Spec | Scope | Parallel | Files |
|---|------|-------|----------|-------|
| **F1** | **Backend-REST-Spec** | `DHPS_Admin_REST` mit 5 Endpoints, `DHPS_Health_Collector`, `DHPS_Cache_Stats`. Rate-Limit-Pattern aus MMB_AJAX_Handler uebernehmen. | ja | 3 neue PHP-Klassen + Init-Hook |
| **F2** | **Frontend-React-Spec** | `dhps-admin-react.js` mit `App` + `HealthOverview` + `ServiceHealthCard` + `ApiTestButton` + `CacheStatsPanel`. `wp.element.createElement`-Style. | ja | 1 JS-File + 1 CSS-File |
| **F3** | **PHP-Foundation-Spec** | `DHPS_Admin::enqueue_scripts()`-Erweiterung (conditional wp-components-Enqueue), Mount-Point in `dashboard.php`, `wp_localize_script`-Bridge, REST-Init-Hook. | ja | 2 PHP-Aenderungen |
| **L1** | **Composition-Lead** (sequentiell, nach F1-F3) | Verkabelt, smokes, fixes Schnittstellen-Konflikte (z.B. REST-Schema-Felder vs. React-Erwartungen). | nein | Iterative Smokes |
| **Q1** | **QA-Spec** + **Security-Spec** (parallel, nach L1) | manage_options auf allen Routes? Nonce in jeder Request? Rate-Limit greift? React-State-Leaks? | ja | Reports |

Bei kleinerer Personal-/Token-Ressource: **2 Specs** (Backend + Frontend kombiniert, PHP-Foundation als Teil von Backend). Die 3-Spec-Variante ist die Risiko-aermste, weil React und REST sich nicht gegenseitig blockieren.

---

## 9. BC-Strategie

### 9.1 Was bleibt unangetastet

| Element | Begruendung |
|---------|-------------|
| 8 bestehende Admin-Pages (Dashboard + 7 Service-Pages) | Routing + Render-Methoden vollstaendig erhalten. |
| `DHPS_Admin_Page_Handler` (POST-Save-Logik) | Settings-API-Migration ist out-of-scope. |
| Demo-Manager + Demo-Toggle (`wp_ajax_dhps_toggle_demo`) | Kompletter Datenpfad bleibt. |
| `dhps_admin.css`, `dhps-ui.css`, `dhps-dashboard.css` | Vorhandene Styles bleiben aktiv. |
| `admin/js/dhps-admin.js` (jQuery Demo-Toggle) | Lebt parallel zu React (unterschiedliche Concerns). |
| `mio-config.php`, `service-config.php` Templates | Wird in v0.15.0 NICHT angefasst. |

### 9.2 Was ist neu (additiv)

| Element | BC-Risiko |
|---------|-----------|
| REST-Namespace `dhps/v1` | KEIN (neuer Namespace) |
| `dhps-admin-react.js` + CSS | KEIN (neue Files) |
| Mount-Point `<div id="dhps-admin-react-root">` in `dashboard.php` | KEIN (additives Markup nach den Service-Cards) |
| Conditional `wp-components`-Enqueue | KEIN (nur auf Dashboard-Page) |
| 3 neue Klassen (`DHPS_Admin_REST`, `DHPS_Health_Collector`, `DHPS_Cache_Stats`) | KEIN (neue Klassen) |

### 9.3 Versteckte BC-Risiken

| Risiko | Mitigation |
|--------|------------|
| `wp-components` CSS ueberschreibt evtl. `.dhps-db-*`-Klassen | React-Mount-Point in eigenem Container ohne Bleed - `.dhps-react-app`-Scope. |
| Cache-Flush-Button loescht auch produktive Caches | Bestaetigungs-Dialog (UX), Admin-only. Existierender `flush()` ist bereits geprueft. |
| Test-Endpoint kann Lizenzen abnutzen (API-Quota) | Rate-Limit 30/min pro User. Default-Cache-TTL `0` bedeutet: kein Cache-Eintrag fuer Test-Aufrufe (Trade-off: jeder Test-Call ist live). |

---

## 10. Security-Anforderungen

### 10.1 Permissions

- **Alle REST-Endpoints**: `permission_callback = function() { return current_user_can( 'manage_options' ); }`.
- **JS-Mount nur fuer Admins**: `enqueue_scripts()` ist bereits screen-gated, zusaetzlich `if ( ! current_user_can( 'manage_options' ) ) { return; }` als Belt-and-Suspenders.
- **REST-Routes existieren auch fuer nicht-eingeloggte User?** Nein - `permission_callback` blockiert vor jedem Routing.

### 10.2 Nonces

- `wp_create_nonce( 'wp_rest' )` im `wp_localize_script` -> automatisch via `apiFetch.createNonceMiddleware()`.
- Bestehender Demo-Toggle-Nonce `dhps_demo_toggle` bleibt unveraendert.

### 10.3 Rate-Limits

| Endpoint | Limit | Schluessel | Begruendung |
|----------|-------|------------|-------------|
| `/services/{slug}/test` | 30/min | User-ID | Admin-Pool kleiner als oeffentlich, aber API-Quota schuetzen. |
| `/cache/flush` | 6/min | User-ID | Destruktiv, sollte aber sofort wirken koennen. |
| `/services` | unbegrenzt | - | Pure Read von In-Memory-Registry. |
| `/services/{slug}/health` | unbegrenzt | - | Read-Only; `is_available()` HEAD-Test nur on-demand und Cache-fixed (Transient 60s, siehe 10.4). |
| `/cache/stats` | unbegrenzt | - | Read-Only DB-Query (Top-10 Limit). |

### 10.4 Caching defensiv

- `is_available()` ist 5s-Timeout - bei haeufiger Health-Anfrage entstehen 5s-Latenzen. Mitigation: Ergebnis 60s in Transient cachen (`dhps_admin_api_reachable`).
- `/services` Antwort 60s in Transient cachen (per-Request - in v0.15.0 evtl. weglassen).

### 10.5 Input-Sanitisierung

- `slug` Route-Parameter: Regex `[a-z]+`, danach `in_array( $slug, DHPS_Service_Registry::get_shortcode_names(), true )`.
- Cache-Flush hat keine User-Inputs.
- Test-Endpoint hat keine User-Inputs (Service-Slug aus Route).

### 10.6 Output-Escaping

- REST-Responses sind JSON - WordPress handhabt das.
- OTA-Preview: NUR die ersten 6 Zeichen + `...` zeigen (`OTA-202...`) - NICHT die vollstaendige Nummer (Audit-Trail-Schutz).
- Cache-Keys in `/cache/stats`: Anzeige unkritisch (sind hashed).

### 10.7 SSRF-Schutz

- Test-Endpoint nutzt ausschliesslich `DHPS_API_Client::fetch_content()` mit Endpoint aus Registry. KEINE freie URL-Eingabe vom Client.
- Identisches Schutz-Pattern wie `DHPS_MMB_AJAX_Handler`.

---

## 11. Risiken + Mitigation

| Risiko | Wahrscheinlichkeit | Auswirkung | Mitigation |
|--------|--------------------|------------|------------|
| `wp-components`-CSS-Bundle ist ~150KB | sicher | langsamer Admin-Page-Load auf Dashboard | Conditional enqueue - nur auf Dashboard-Page. Kein Impact auf andere Admin-Pages. |
| React-Lifecycle stoesst sich mit jQuery-Demo-Toggle | gering | UI-Glitch bei Demo-Aktivierung | React-Mount-Point getrennt von Demo-Cards. Beide arbeiten auf disjunkten DOM-Bereichen. |
| API-Test trifft Live-API und verursacht Last | mittel | Lizenz-Quota / Rate-Limit auf API-Seite | 30 Tests/min pro Admin. Test-Result wird NICHT zwischengespeichert (Cache-TTL=0 im fetch_content-Aufruf -> jeder Test ist live). |
| `is_available()` mit 5s Timeout blockt Health-Render | hoch | UI haengt bis 5s | Health-Endpoint: `is_available()` Ergebnis 60s in Transient cachen. React zeigt Spinner waehrenddessen. |
| Cache-Flush wird versehentlich geklickt | mittel | alle Caches weg, naechste Frontend-Requests slow | Confirm-Dialog + 6/min-Limit. |
| Cache-Stats kann mit DB-Queries Last erzeugen | gering | Slow-Query bei vielen Transients | Top-10-Limit + LIMIT-Clause + Index auf option_name (WP Standard). |
| React via `wp.element.createElement` ist verbose und schwer zu warten | mittel | Tech-Debt | Akzeptiert in v0.15.0. Migration zu wp-scripts/Vite kann v0.16.0 erfolgen. |
| OTA-Preview leakt in UI-Screenshots oder Browser-Devtools | gering | Audit-Trail-Verschmutzung | OTA wird beschnitten (erste 6 Zeichen + `...`). |
| `wp_localize_script( 'dhpsAdminConfig' )` enthaelt vollstaendige Service-Definitionen im HTML | gering | Endpoint-Pfade im Quelltext sichtbar | Endpoints sind keine Geheimnisse (sind in der Doku). Auth-Tokens werden NICHT durchgereicht. |
| WP-Updates aendern `wp.components`-API | mittel-langfristig | Components brechen | Pin auf konkrete WP-Version in Plugin-Header (`Requires at least: 6.5`). |
| Wenig Tests fuer React-Code (kein Build = kein Jest) | mittel | Regressions | Smoke-Tests im Browser; QA-Spec macht manuellen Klick-Pfad. v0.16.0 ggf. Build-Pipeline einfuehren. |

### 11.1 Akzeptierte Trade-offs

- ~150KB zusaetzliches CSS auf Dashboard-Page (wp-components).
- React ohne JSX -> verbosere Syntax.
- Cache-Stats sind global, nicht pro Service.
- API-Tests sind live - keine simulierten Responses.

---

## 12. Naechste Schritte

1. Plan-Review durch User (Architekt).
2. Specialist-Briefings F1-F3 erstellen.
3. Parallel-Implementierung F1-F3.
4. Composition-Lead L1.
5. QA-Spec + Security-Spec (parallel) Q1.
6. CHANGELOG-v0150, Version-Bump, Git-Tag.

---

## Quellen

- `includes/class-dhps-admin.php` - Aktuelle Menue-/Routing-Logik
- `includes/class-dhps-admin-page-handler.php` - Settings-API-freier POST-Handler
- `includes/class-dhps-service-registry.php` - 9 Service-Definitionen
- `includes/class-dhps-cache.php` - keine Statistik-API vorhanden (bestaetigt)
- `includes/class-dhps-api-client.php` - Cache-Aside-Pattern, fetch_content
- `includes/class-dhps-api-interface.php` - is_available() existiert
- `includes/class-dhps-mmb-ajax-handler.php` - Rate-Limit + Whitelist-Vorbild
- `includes/class-dhps-demo-manager.php` - get_all_statuses, get_service_status, get_demo_duration
- `admin/views/dashboard.php` - aktuelles Layout (additive React-Erweiterung moeglich)
- `docs/architecture/13-alpinejs-integration-v0140.md` - Loading-Strategie-Vorbild
- `docs/project/24-CHANGELOG-v0144.md` - v0.14.x-Bilanz + v0.15.0-Optionen
- WordPress Block Editor Handbook (Components Package): https://developer.wordpress.org/block-editor/reference-guides/packages/packages-components/
- WP REST API Handbook: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- `wp.apiFetch`: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-api-fetch/
