# Entwicklungsplan (Roadmap)

## Versionsplanung

### Phase 1: Code-Review & Hardening (v0.3.0) - FERTIG

**Prioritaet: KRITISCH** - Sicherheit und Stabilitaet

#### 1.1 Security Hardening
- [x] CSRF-Schutz: `wp_nonce_field()` + `wp_verify_nonce()` in allen Admin-Formularen
- [x] Input-Sanitierung: `sanitize_text_field()` fuer alle `$_POST`-Eingaben
- [x] Output-Escaping: `esc_attr()`, `esc_html()`, `esc_url()` fuer alle Ausgaben
- [x] `$_GET['video']` absichern mit `absint()` (nur Integer erlaubt)
- [x] URL-Encoding: `urlencode()` fuer alle API-Parameter
- [x] `file_get_contents()` + `fsockopen()` ersetzen durch `wp_remote_get()`
- [x] Capability-Checks in allen Admin-Seiten-Methoden
- [x] Options-Prefix einfuehren (`dhps_`) um Namenskollisionen zu vermeiden

#### 1.2 Bug-Fixes
- [x] Getter-Methoden korrigieren (Z.146, 157, 168 - falsche Property-Namen)
- [x] Tippfehler "Dashbaord" -> "Dashboard" korrigieren
- [x] `echo '<!--1.1-->';` in maes_shortcode entfernen (Debug-Output)
- [x] Variablen-Fehler in lp_shortcode: `$videonr` undefiniert in fsockopen-Pfad
- [x] Inkonsistenter fsockopen-Aufruf in tp_shortcode (fehlender `$einzelvideostr`)

#### 1.3 Uninstall-Logik
- [x] `uninstall.php` implementieren: Alle `dhps_*` Options entfernen
- [x] Aktivierungs-Hook: Standard-Optionen setzen
- [x] Deaktivierungs-Hook: Transient-Cache leeren

---

### Phase 2: Architektur-Refactoring (v0.4.0) - FERTIG

**Prioritaet: HOCH** - Wartbarkeit und Erweiterbarkeit

#### 2.1 Dateistruktur (Ist-Zustand v0.4.0)

```text
wp-deubner-hp-services/
├── Deubner_HP_Services.php               # Bootstrap: Konstanten, Autoloader, Init
├── includes/
│   ├── class-dhps-api-interface.php      # API-Interface (Vertrag)
│   ├── class-dhps-api-response.php       # API-Response Value-Object
│   ├── class-dhps-legacy-api.php         # Legacy-HTML-API-Implementierung
│   ├── class-dhps-cache.php              # Transient-basierter Cache
│   ├── class-dhps-api-client.php         # API-Fassade (Cache-Aside-Pattern)
│   ├── class-dhps-service-registry.php   # Deklarative Service-Definitionen (9 Services)
│   ├── class-dhps-shortcodes.php         # Generischer Shortcode-Handler
│   ├── class-dhps-admin.php              # Admin-Menues, CSS, Rendering
│   └── class-dhps-admin-page-handler.php # Formular-Verarbeitung (Nonce, Sanitize, Save)
├── admin/views/
│   ├── partials/header.php               # Gemeinsamer Plugin-Header
│   ├── dashboard.php                     # Dashboard-Uebersicht
│   ├── service-config.php                # Generisches Service-Formular (inkl. Extra-Sections)
│   └── mio-config.php                    # MI-Online Dual-Formular (Steuerrecht + Recht)
├── assets/images/                        # Plugin-Assets (Logo/Icon)
├── css/
│   ├── dhps_admin.css                    # Admin-Bereich Styles
│   ├── dhps_base.css                     # Basis-Styles (Elementor)
│   └── dhps-ui.css                       # UI-Framework
├── docs/                                 # Projektdokumentation
├── uninstall.php                         # Plugin-Deinstallation
└── README.md
```

#### 2.2 API-Abstraktionsschicht (implementiert)

- [x] `DHPS_API_Interface` - Vertrag fuer alle API-Implementierungen
- [x] `DHPS_API_Response` - Immutables Value-Object (Factory-Pattern: success/error)
- [x] `DHPS_Legacy_API` - Konkrete Implementierung via `wp_remote_get()`
- [x] `DHPS_Cache` - WordPress Transients mit deterministischer Key-Generierung
- [x] `DHPS_API_Client` - Fassade mit Cache-Aside-Pattern (Dependency Injection)

#### 2.3 Service-Registry (implementiert)

- [x] `DHPS_Service_Registry` - Deklarative Definition aller 9 Services
- [x] Jeder Service definiert: endpoint, auth_type, shortcode_atts, admin_fields, admin_options
- [x] Extra-Sections-Support (z.B. TaxPlain Teaser auf Tax-Videos-Seite)
- [x] Statischer Zugriff via `get_service()`, `get_services()`, `get_shortcode_names()`

#### 2.4 Code-Duplikation eliminiert

- [x] Fetch-Logik: `DHPS_API_Client::fetch_content()` ersetzt 9 individuelle Methoden
- [x] Admin-Formulare: `service-config.php` Template ersetzt 7 hardcodierte echo-Bloecke
- [x] Parameter-Building: `DHPS_Shortcodes::handle_shortcode()` generischer Handler fuer alle 9 Shortcodes
- [x] Autoloader: `spl_autoload_register()` statt manueller require-Ketten
- [x] Haupt-Plugin-Datei: Von 1.309 Zeilen monolithisch auf 241 Zeilen Bootstrap reduziert

---

### Phase 3: Moderne Layouts & Widgets (v0.5.0) - FERTIG

**Prioritaet: HOCH** - Kundenwunsch

#### 3.1 Renderer-Engine & Frontend-Templates (implementiert)

- [x] `DHPS_Renderer` - Template-Engine mit Theme-Override-System
- [x] Template-Suchreihenfolge: Theme (`{theme}/dhps/`) → Plugin (`public/views/`)
- [x] Layout `default` - Einfacher Wrapper-Container
- [x] Layout `card` - Box mit Shadow, Border-Radius, Padding
- [x] Layout `compact` - Minimales Wrapping, reduziertes Padding
- [x] Responsive Design fuer alle Layouts (768px Breakpoint)
- [x] BEM-CSS-Klassen: `.dhps-service`, `.dhps-service--{tag}`, `.dhps-layout--{name}`, `.dhps-card`
- [x] `dhps-frontend.css` - Layout-Stylesheet (getrennt von Basis-Styles)

#### 3.2 WordPress-Widget (implementiert)

- [x] `DHPS_Widget` (extends `WP_Widget`) fuer Sidebar/Footer
- [x] Konfigurierbar: Titel, Service-Auswahl, Layout, CSS-Klasse, Cache-Dauer
- [x] Validierung gegen Service-Registry und verfuegbare Layouts
- [x] Dependency Injection via `set_dependencies()` Setter-Pattern
- [x] Variante-Switch-Logik fuer MIO/LXMIO im Widget-Kontext

#### 3.3 Elementor-Widget (implementiert)

- [x] `DHPS_Elementor` - Loader mit Elementor-Detection (`did_action('elementor/loaded')`)
- [x] `DHPS_Elementor_Widget` (extends `\Elementor\Widget_Base`) mit Constructor-DI
- [x] Eigene Elementor-Kategorie "Deubner Services" (`dhps-services`)
- [x] Content-Controls: Service-Auswahl, Layout, CSS-Klasse, Cache-Dauer
- [x] Style-Controls: Innenabstand (bei Card-Layout), Eckenradius
- [x] Widget-Datei bewusst ausserhalb `includes/` (kein Autoloader bei fehlendem Elementor)

#### 3.4 Shortcode-Erweiterung (implementiert)

- [x] Universelle Parameter: `layout="default|card|compact"`, `class="css-class"`, `cache="3600"`
- [x] Alle 9 Services in der Registry um universelle Attribute erweitert
- [x] `DHPS_Shortcodes` Constructor akzeptiert jetzt `DHPS_Renderer` via DI
- [x] Layout/Class/Cache werden vor API-Aufruf extrahiert und an Renderer uebergeben

#### 3.5 Dateistruktur (Ist-Zustand v0.5.0)

```text
wp-deubner-hp-services/
├── Deubner_HP_Services.php               # Bootstrap (v0.5.0): +Renderer, +Widget, +Elementor
├── includes/
│   ├── class-dhps-api-interface.php      # API-Interface (Vertrag)
│   ├── class-dhps-api-response.php       # API-Response Value-Object
│   ├── class-dhps-legacy-api.php         # Legacy-HTML-API-Implementierung
│   ├── class-dhps-cache.php              # Transient-basierter Cache
│   ├── class-dhps-api-client.php         # API-Fassade (Cache-Aside-Pattern)
│   ├── class-dhps-service-registry.php   # Deklarative Service-Definitionen (9 Services)
│   ├── class-dhps-shortcodes.php         # Generischer Shortcode-Handler (+Renderer)
│   ├── class-dhps-renderer.php           # [NEU] Layout-Template-Engine
│   ├── class-dhps-widget.php             # [NEU] WordPress-Widget
│   ├── class-dhps-elementor.php          # [NEU] Elementor-Loader
│   ├── class-dhps-admin.php              # Admin-Menues, CSS, Rendering
│   └── class-dhps-admin-page-handler.php # Formular-Verarbeitung
├── public/views/                         # [NEU] Frontend-Templates
│   ├── layout-default.php                # Standard-Layout
│   ├── layout-card.php                   # Card-Layout (Shadow + Radius)
│   └── layout-compact.php                # Kompakt-Layout
├── widgets/elementor/                    # [NEU] Elementor-Widgets
│   └── class-dhps-elementor-widget.php   # Elementor-Widget (ausserhalb Autoloader)
├── admin/views/                          # Admin-Templates
│   ├── partials/header.php
│   ├── dashboard.php
│   ├── service-config.php
│   └── mio-config.php
├── assets/images/                        # Plugin-Assets
├── css/
│   ├── dhps_admin.css                    # Admin-Styles
│   ├── dhps_base.css                     # Basis-Styles (Elementor)
│   ├── dhps-frontend.css                 # [NEU] Layout-Container-Styles
│   └── dhps-ui.css                       # UI-Framework
├── docs/                                 # Projektdokumentation
├── uninstall.php                         # Plugin-Deinstallation
└── README.md
```

---

### Phase 4: Demo-Modus & Onboarding (v0.6.0) - FERTIG

**Prioritaet: MITTEL** - Kundengewinnung

#### 4.1 Demo-Manager (implementiert)

- [x] `DHPS_Demo_Manager` - State-Machine fuer Demo-Zustaende aller 9 Services
- [x] Demo-Credentials als Platzhalter, filterbar via `apply_filters('dhps_demo_credentials', ...)`
- [x] Aktivierung: Backup des aktuellen Auth-Werts, Demo-Credential schreiben, Timestamp speichern
- [x] Deaktivierung: Original-Wert wiederherstellen, State bereinigen
- [x] Automatische Ablaufpruefung (`check_expired_demos()`) bei Plugin-Init
- [x] Konfigurierbare Demo-Dauer (Option `dhps_demo_duration_days`, Standard: 30 Tage)
- [x] Service-Status-Ermittlung: 'active' / 'demo' / 'inactive'

#### 4.2 Dashboard-Redesign (implementiert)

- [x] Service-Cards-Grid mit 3-Spalten-Layout (dhpsui-col-lg-4)
- [x] Farbcodierte Status-Badges: Gruen (aktiv), Gelb (Demo + Resttage), Grau (inaktiv)
- [x] Kontextabhaengige Aktions-Buttons: "Demo starten", "Demo beenden", "Konfigurieren"
- [x] Shop-Links zur Freischaltung (deubner-steuern.de)
- [x] Info-Box mit Demo-Dauer-Hinweis und Kontaktdaten
- [x] AJAX-basierter Demo-Toggle via `wp_ajax_dhps_toggle_demo`
- [x] Dashboard-CSS (`dhps-dashboard.css`) und Admin-JS (`dhps-admin.js`)

#### 4.3 Demo-Badge Frontend (implementiert)

- [x] `DHPS_Renderer::wrap_demo_badge()` - Gelber Banner ueber Demo-Inhalten
- [x] "Demo-Inhalt" Text + "Jetzt freischalten" Call-to-Action-Button
- [x] BEM-CSS: `.dhps-demo-banner`, `.dhps-demo-banner__text`, `.dhps-demo-banner__link`
- [x] Demo-Manager via Setter-DI in den Renderer injiziert

#### 4.4 Docker-Testumgebung (implementiert)

- [x] `docker-compose.yml` - WordPress + MariaDB + phpMyAdmin
- [x] Plugin als Volume-Mount (Live-Reload bei Code-Aenderungen)
- [x] `docker/wp-setup.sh` - Automatisches Setup mit WP-CLI
- [x] Testseiten mit allen Shortcodes und Layout-Varianten
- [x] Deutsche Sprache und Debug-Modus vorkonfiguriert

#### 4.5 Dateistruktur (Ist-Zustand v0.6.0)

```text
wp-deubner-hp-services/
├── Deubner_HP_Services.php               # Bootstrap (v0.6.0): +Demo-Manager
├── includes/
│   ├── class-dhps-api-interface.php      # API-Interface (Vertrag)
│   ├── class-dhps-api-response.php       # API-Response Value-Object
│   ├── class-dhps-legacy-api.php         # Legacy-HTML-API-Implementierung
│   ├── class-dhps-cache.php              # Transient-basierter Cache
│   ├── class-dhps-api-client.php         # API-Fassade (Cache-Aside-Pattern)
│   ├── class-dhps-service-registry.php   # Deklarative Service-Definitionen (9 Services)
│   ├── class-dhps-shortcodes.php         # Generischer Shortcode-Handler (+Renderer)
│   ├── class-dhps-renderer.php           # Layout-Template-Engine (+Demo-Badge)
│   ├── class-dhps-demo-manager.php       # [NEU] Demo-Modus State-Machine
│   ├── class-dhps-widget.php             # WordPress-Widget
│   ├── class-dhps-elementor.php          # Elementor-Loader
│   ├── class-dhps-admin.php              # Admin (+AJAX Demo-Toggle, +Dashboard-Vars)
│   └── class-dhps-admin-page-handler.php # Formular-Verarbeitung
├── public/views/                         # Frontend-Templates
│   ├── layout-default.php
│   ├── layout-card.php
│   └── layout-compact.php
├── widgets/elementor/
│   └── class-dhps-elementor-widget.php
├── admin/
│   ├── views/
│   │   ├── partials/header.php
│   │   ├── dashboard.php                 # [NEU] Redesign mit Service-Cards
│   │   ├── service-config.php
│   │   └── mio-config.php
│   └── js/
│       └── dhps-admin.js                 # [NEU] AJAX Demo-Toggle
├── css/
│   ├── dhps_admin.css
│   ├── dhps_base.css
│   ├── dhps-frontend.css                 # (+Demo-Badge-Styles)
│   ├── dhps-dashboard.css                # [NEU] Dashboard-Styles
│   └── dhps-ui.css
├── docker-compose.yml                    # [NEU] Docker-Testumgebung
├── docker/
│   └── wp-setup.sh                       # [NEU] WP-CLI Setup-Script
├── docs/
├── uninstall.php
└── README.md
```

---

### Phase 4b: Elementor-Widgets pro Service (v0.7.0) - FERTIG

**Prioritaet: HOCH** - Bugfix + Feature

#### 4b.1 500-Fehler behoben (implementiert)

- [x] Ursache: Constructor-DI inkompatibel mit Elementor-interner Widget-Instanziierung
- [x] Elementor ruft `new WidgetClass($data, $args)` auf, altes Widget erwartete `($api_client, $renderer, $data, $args)`
- [x] Loesung: Static Dependency Injection via `DHPS_Elementor_Widget_Base::set_dependencies()`
- [x] Standard-Elementor-Konstruktor `__construct($data = [], $args = null)` in Base-Klasse

#### 4b.2 Separate Elementor-Widgets (implementiert)

- [x] `DHPS_Elementor_Widget_Base` - Abstrakte Basisklasse mit gesamter Logik
- [x] 9 service-spezifische Subklassen mit eigenem Name, Icon und Controls
- [x] Controls dynamisch aus `DHPS_Service_Registry::get_service()['shortcode_atts']` generiert
- [x] Select-Felder fuer: variante, teasermodus, show_teaser, modus
- [x] Number-Felder fuer: einzelvideo, anzahl
- [x] Text-Felder fuer: filter, st_kategorie, id_merkblatt, rubrik, videoliste
- [x] Universelle Controls: layout, custom_class, cache_ttl
- [x] Style-Section: Innenabstand (Card), Eckenradius
- [x] Deutsche Labels fuer alle Controls
- [x] DHPS_Elementor Loader registriert alle 9 Widgets ueber foreach-Loop
- [x] Altes generisches Widget (`class-dhps-elementor-widget.php`) entfernt

#### 4b.3 Widget-Zuordnung

| Widget-Klasse | Service | Elementor-Icon |
| --- | --- | --- |
| `DHPS_Elementor_Widget_MIO` | MI-Online Steuerrecht | `eicon-post-list` |
| `DHPS_Elementor_Widget_LXMIO` | MI-Online Recht | `eicon-post-list` |
| `DHPS_Elementor_Widget_MMB` | Merkblaetter | `eicon-document-file` |
| `DHPS_Elementor_Widget_MIL` | Infografiken | `eicon-image` |
| `DHPS_Elementor_Widget_TP` | TaxPlain Videos | `eicon-play` |
| `DHPS_Elementor_Widget_TPT` | TaxPlain Teaser | `eicon-featured-image` |
| `DHPS_Elementor_Widget_TC` | Tax-Rechner | `eicon-number-field` |
| `DHPS_Elementor_Widget_MAES` | Meine Aerzteseite | `eicon-person` |
| `DHPS_Elementor_Widget_LP` | Lexplain | `eicon-play` |

#### 4b.4 Dateistruktur-Aenderungen (v0.6.0 -> v0.7.0)

```text
widgets/elementor/
├── class-dhps-elementor-widget.php           # [ENTFERNT] Altes generisches Widget
├── class-dhps-elementor-widget-base.php      # [NEU] Abstrakte Basis mit Static-DI
└── class-dhps-elementor-service-widgets.php  # [NEU] 9 Service-Subklassen
```

---

### Phase 4c: UI/UX Redesign & Design Tokens (v0.8.0) - FERTIG

**Prioritaet: HOCH** - Professionelles Erscheinungsbild

#### 4c.1 Design-Token-System (implementiert)

- [x] `css/dhps-design-tokens.css` - Zentrales `:root`-Stylesheet mit 53 CSS Custom Properties
- [x] Kategorie-Farben: Recht (#1E73BE), Steuern (#2e8a37), Medizin (#0097a7)
- [x] Semantische Farben, Neutrale Palette, Typografie-Skala, Spacing-System
- [x] Border-Radii, Schatten, Grid-Konfiguration als Tokens

#### 4c.2 CSS-Migration (implementiert)

- [x] Alle 235 `--rsp-*` CSS-Variablen zu `--dhps-*` migriert
- [x] 0 verbleibende Legacy-Referenzen
- [x] 285 neue `--dhps-*` Referenzen im Admin-CSS
- [x] Alte `:root`-Block mit `--rsp-*` Definitionen entfernt
- [x] Neue BEM-Komponentenstyles: `.dhps-service-card`, `.dhps-config-header`, `.dhps-form-*`

#### 4c.3 Service-Kategorisierung (implementiert)

- [x] Registry erweitert um: `category`, `shop_url`, `icon` fuer alle 9 Services
- [x] Drei Kategorien: steuern (6), recht (2), medizin (1)
- [x] Kategorie-spezifische Shop-URLs: deubner-steuern.de vs. deubner-recht.de
- [x] Dashicons pro Service (media-text, chart-bar, video-alt3, calculator, heart, etc.)

#### 4c.4 Dashboard-Redesign (implementiert)

- [x] Kategorie-Gruppierung: Services nach Steuern/Recht/Medizin sortiert
- [x] BEM Service-Cards: `.dhps-service-card` mit `.dhps-category--{cat}` Farbcodierung
- [x] Dashicons-Integration, Status-Badges, Shortcode-Anzeige
- [x] Service-spezifische Shop-Links statt globalem Link
- [x] Alle Inline-Styles entfernt, vollstaendig ueber CSS gesteuert

#### 4c.5 Header-Modernisierung (implementiert)

- [x] Zwei Shop-Links: "Steuern-Shop" + "Recht-Shop" statt einzelnem Premium-CTA
- [x] Dokumentations-Link beibehalten
- [x] `.dhps-header-link` Klasse fuer einheitliches Styling

#### 4c.6 Konfigurationsseiten (implementiert)

- [x] `service-config.php`: Config-Header mit Icon, Titel, Status-Badge, Shop-Link
- [x] `mio-config.php`: Gemeinsamer Header + kategorie-farbige Unter-Header (Steuern/Recht)
- [x] BEM-Form-Klassen: `.dhps-form-group`, `.dhps-form-label`, `.dhps-form-input`
- [x] Admin-Klasse liefert `$category`, `$shop_url`, `$icon`, `$status` an Templates

#### 4c.7 Dateistruktur-Aenderungen (v0.7.0 -> v0.8.0)

```text
css/
├── dhps-design-tokens.css     # [NEU] Zentrales Design-Token-System
└── dhps_admin.css             # [GEAENDERT] --rsp-* -> --dhps-*, +BEM-Komponenten

includes/
├── class-dhps-service-registry.php  # [GEAENDERT] +category, +shop_url, +icon
└── class-dhps-admin.php             # [GEAENDERT] +Design-Tokens CSS, +Template-Vars

admin/views/
├── dashboard.php              # [GEAENDERT] Kategorie-Gruppierung, BEM-Cards
├── partials/header.php        # [GEAENDERT] Zwei Shop-Links
├── service-config.php         # [GEAENDERT] Config-Header, BEM-Formulare
└── mio-config.php             # [GEAENDERT] Kategorie-Header, BEM-Formulare
```

---

### Phase 5: Content-Pipeline & HTML-Parser (v0.9.0) - FERTIG

**Prioritaet: HOCH** - Sicherheit & Frontend-Kontrolle

#### 5.1 Parser-Infrastruktur (implementiert)

- [x] `DHPS_Parser_Interface` - Vertrag fuer alle Service-Parser (parse() -> array)
- [x] `DHPS_Parser_Registry` - Statische Registry fuer Parser-Instanzen
- [x] `DHPS_Content_Pipeline` - Orchestrator (API-Fetch → Parse → Render)
- [x] 2-Layer-Caching: L1 (Raw HTML, bestehendes `dhps_*`) + L2 (Parsed Data, neues `dhps_p_*`)
- [x] `DHPS_Cache::get_data()` / `set_data()` fuer serialisierte Array-Daten
- [x] Autoloader erweitert um `includes/parsers/` Suchpfad

#### 5.2 MIO-Parser (implementiert)

- [x] `DHPS_MIO_Parser` - DOMDocument-basierter Parser fuer MIO/LXMIO
  - Steuertermine: Titel, Eintraege (Datum + Steuerarten), Fussnote
  - Such-Konfiguration: Zielgruppen, Suchfeld-Placeholder
  - AJAX-Parameter: Fachgebiet, Variante, Anzahl (ohne OTA)
- [x] `DHPS_MIO_News_Parser` - Parser fuer AJAX-geladene News-Response
  - Zielgruppen-Gruppierung, Artikel-Extraktion (Titel, Body, Metadaten)
  - Social-Share-Links (E-Mail, Twitter, Facebook, XING, LinkedIn)

#### 5.3 AJAX-Proxy (implementiert)

- [x] `DHPS_AJAX_Proxy` - Serverseitiger Proxy fuer News-AJAX-Anfragen
- [x] OTA-Kundennummer wird serverseitig aus `get_option()` injiziert
- [x] OTA ist NICHT mehr im Browser-Quelltext sichtbar (Sicherheits-Fix)
- [x] Nonce-Verifikation fuer CSRF-Schutz
- [x] Parameter-Sanitisierung aller Client-Eingaben
- [x] Caching der geparsten AJAX-Responses (15 Min TTL)

#### 5.4 Renderer-Erweiterung (implementiert)

- [x] `DHPS_Renderer::render_parsed()` - Rendert geparste Daten in Service-Templates
- [x] `DHPS_Renderer::locate_service_template()` - Template-Suche unter `services/{tag}/`
- [x] Template-Fallback-Kette: lxmio → mio (via `dhps_template_fallbacks` Filter)
- [x] Theme-Override: `{theme}/dhps/services/{tag}/{layout}.php`

#### 5.5 MIO-Templates (implementiert)

- [x] `public/views/services/mio/default.php` - Standard-Layout
- [x] `public/views/services/mio/card.php` - Card-Layout mit Box-Shadow
- [x] `public/views/services/mio/compact.php` - Kompakt-Layout
- [x] Steuertermine als CSS-Grid (ersetzt Table-Layout)
- [x] Accessible Search-Bar mit `role="search"` und ARIA-Labels
- [x] News-Container mit `aria-live="polite"` fuer AJAX-Updates
- [x] Daten-Attribute statt Inline-JavaScript fuer AJAX-Config

#### 5.6 Frontend-JavaScript (implementiert)

- [x] `public/js/dhps-mio.js` - Vanilla-JS (kein jQuery)
- [x] AJAX-Loading ueber WordPress admin-ajax.php (nicht direkt zur API)
- [x] Accessible Accordion mit `aria-expanded` / `aria-hidden`
- [x] Event-Delegation fuer Toggle, Print, Collapse
- [x] Druck-Funktion fuer einzelne Artikel (neues Fenster)
- [x] Conditional Enqueue: JS wird nur geladen wenn MIO-Shortcode auf der Seite

#### 5.7 CSS-Erweiterung (implementiert)

- [x] BEM-Klassen fuer Steuertermine: `.dhps-tax-dates`, `__grid`, `__column`, `__entry`
- [x] BEM-Klassen fuer Suchleiste: `.dhps-search-bar`, `__form`, `__select`, `__input`, `__button`
- [x] BEM-Klassen fuer News: `.dhps-news`, `__group`, `__article`, `__title`, `__body`, `__meta`
- [x] Lade-Spinner: `.dhps-news__spinner` mit `@keyframes dhps-spin`
- [x] Responsive: Steuertermine Grid → 1-Spalte, Suchleiste wrap auf Mobile

#### 5.8 Integration (implementiert)

- [x] `DHPS_Shortcodes` akzeptiert `DHPS_Content_Pipeline` als 3. Constructor-Dependency
- [x] `handle_shortcode()` delegiert an `$pipeline->render_service()` statt direkt
- [x] Pipeline prueft Parser-Registry: mit Parser → Parse + Template, ohne → Fallback Raw-HTML
- [x] Nicht-migrierte Services (mmb, tp, etc.) funktionieren weiterhin identisch
- [x] MIO und LXMIO teilen sich Parser-Instanz, Templates via Fallback-Kette

#### 5.9 Dateistruktur-Aenderungen (v0.8.0 -> v0.9.0)

```text
includes/
├── class-dhps-content-pipeline.php      # [NEU] Orchestrator (API → Parse → Render)
├── class-dhps-parser-interface.php      # [NEU] Parser-Vertrag
├── class-dhps-parser-registry.php       # [NEU] Parser-Registry
├── class-dhps-ajax-proxy.php            # [NEU] Serverseitiger AJAX-Proxy
├── class-dhps-cache.php                 # [GEAENDERT] +get_data(), +set_data()
├── class-dhps-renderer.php              # [GEAENDERT] +render_parsed(), +locate_service_template()
├── class-dhps-shortcodes.php            # [GEAENDERT] +Pipeline-DI
├── parsers/                             # [NEU] Service-Parser
│   ├── class-dhps-mio-parser.php        # MIO HTML-Parser (DOMDocument)
│   └── class-dhps-mio-news-parser.php   # MIO News AJAX-Parser

public/
├── views/
│   └── services/                        # [NEU] Service-spezifische Templates
│       └── mio/
│           ├── default.php              # MIO Standard-Layout
│           ├── card.php                 # MIO Card-Layout
│           └── compact.php              # MIO Kompakt-Layout
└── js/
    └── dhps-mio.js                      # [NEU] MIO Frontend-JS (AJAX, Accordion)

Deubner_HP_Services.php                  # [GEAENDERT] v0.9.0, +Pipeline, +Parser, +Proxy, +JS
css/dhps-frontend.css                    # [GEAENDERT] +MIO BEM-Klassen
```

---

### Phase 6: Neue API-Anbindung (v1.0.0)

**Prioritaet: MITTEL** - Zukunftssicherheit

#### 6.1 Neue API-Anbindung

- [ ] REST-API Client fuer neue Deubner-API
- [ ] JSON-Response-Handling
- [ ] OAuth2/API-Key Authentifizierung
- [ ] Rate-Limiting und Retry-Logik
- [ ] Webhook-Support fuer Content-Updates

#### 6.2 Weitere Service-Parser

- [ ] MMB-Parser (Merkblaetter)
- [ ] TP-Parser (TaxPlain Videos)
- [ ] MIL-Parser (Infografiken)
- [ ] TC-Parser (Tax-Rechner)
- [ ] MAES-Parser (Meine Aerzteseite)
- [ ] LP-Parser (Lexplain)

#### 6.3 Caching-Erweiterung

- [ ] Object-Cache-Unterstuetzung (Redis/Memcached)
- [ ] Cache-Invalidierung per Admin-Button
- [ ] Cache-Warmup via WP-Cron

---

## Priorisierte Umsetzungsreihenfolge

```text
[KRITISCH] Phase 1:  Security Hardening         -> v0.3.0
[HOCH]     Phase 2:  Architektur-Refactoring     -> v0.4.0
[HOCH]     Phase 3:  Moderne Layouts/Widgets     -> v0.5.0
[MITTEL]   Phase 4:  Demo-Modus/Onboarding      -> v0.6.0
[HOCH]     Phase 4b: Elementor pro Service       -> v0.7.0
[HOCH]     Phase 4c: UI/UX Redesign             -> v0.8.0
[HOCH]     Phase 5:  Content-Pipeline/Parser     -> v0.9.0
[MITTEL]   Phase 6:  Neue API / v1.0            -> v1.0.0
```

## Technische Konventionen

- **PHP:** PSR-4 kompatibel, WordPress Coding Standards
- **Prefix:** `dhps_` fuer Funktionen, Options, Hooks, CSS-Klassen
- **Namespace:** Kein PHP-Namespace (WP-Kompatibilitaet), Klassen-Prefix `DHPS_`
- **Kommentierung:** PHPDoc fuer alle Klassen/Methoden
- **i18n:** Alle Strings via `__()` / `esc_html__()` mit Text-Domain `deubner_hp_services`
- **Assets:** Versionierung via `DEUBNER_HP_SERVICES_VERSION`
- **Git:** Conventional Commits (`feat:`, `fix:`, `refactor:`, `docs:`)
