# System-Uebersicht: Deubner HP Services Plugin

## Zweck

WordPress-Plugin zur Einbettung von Content-Services des Deubner Verlags (Steuerrecht, Recht, Medizin) in Kundenwebseiten. Das Plugin holt HTML-Inhalte ueber eine API, parst sie zu strukturierten Daten und rendert sie ueber Templates.

## Version

- Aktuelle Version: **v0.9.4**
- PHP: 8.0+
- WordPress: 6.0+
- Elementor: Optional (erweiterte Widget-Unterstuetzung)

## 9 Services

| Shortcode | Name | Auth-Typ | Kategorie |
|-----------|------|----------|-----------|
| `[mio]` | MI-Online Steuerrecht | OTA | Steuern |
| `[lxmio]` | MI-Online Recht | OTA | Recht |
| `[mmb]` | Merkblaetter | OTA | Steuern |
| `[mil]` | Infografiken | - | Steuern |
| `[tp]` | TaxPlain Videos | kdnr | Steuern |
| `[tpt]` | TaxPlain Teaser | kdnr | Steuern |
| `[tc]` | Tax-Rechner | - | Steuern |
| `[maes]` | Meine Aerzteseite | OTA | Medizin |
| `[lp]` | Lexplain | kdnr | Recht |

## Architektur-Schichten

```
┌─────────────────────────────────────────────────┐
│  Frontend (Templates + Vanilla JS)              │
│  Shortcodes | WP-Widget | Elementor-Widgets     │
├─────────────────────────────────────────────────┤
│  Rendering Layer (DHPS_Renderer)                │
│  Layout-Templates + Service-Templates           │
├─────────────────────────────────────────────────┤
│  Content Pipeline (DHPS_Content_Pipeline)       │
│  L2-Cache (Parsed Data) + Parser Registry       │
├─────────────────────────────────────────────────┤
│  API Client (DHPS_API_Client)                   │
│  L1-Cache (Raw HTML) + Cache-Aside Pattern      │
├─────────────────────────────────────────────────┤
│  Legacy API Adapter (DHPS_Legacy_API)           │
│  wp_remote_get() -> deubner-online.de           │
├─────────────────────────────────────────────────┤
│  Querschnitt: Demo-Manager, AJAX-Proxy, Admin   │
└─────────────────────────────────────────────────┘
```

## Verzeichnisstruktur

```
wp-deubner-hp-services/
├── Deubner_HP_Services.php      # Bootstrap, Autoloader, DI-Container
├── includes/                     # Core-Klassen (22 PHP-Dateien)
│   ├── class-dhps-*.php          # API, Cache, Pipeline, Renderer, etc.
│   └── parsers/                  # Service-spezifische Parser (5 Dateien)
├── widgets/elementor/            # Elementor-Widget-Klassen (2 Dateien)
├── admin/                        # Admin-Bereich
│   ├── views/                    # Admin-Templates (Dashboard, Config)
│   └── js/                       # Admin-JavaScript
├── public/                       # Frontend-Output
│   ├── views/                    # Layout- und Service-Templates
│   │   ├── layout-*.php          # 3 Layout-Varianten
│   │   └── services/{tag}/       # Service-spezifische Templates
│   └── js/                       # Frontend-JavaScript (MIO, MMB, TP)
├── css/                          # Stylesheets (6 Dateien)
│   ├── dhps-design-tokens.css    # CSS Custom Properties
│   ├── dhps-frontend.css         # Haupt-Frontend-CSS
│   └── dhps-ui.css               # UI-Framework (Admin)
├── docs/                         # Dokumentation
├── docker/                       # Docker-Entwicklungsumgebung
└── demo/                         # Demo-HTML-Dateien
```

## Bootstrap-Reihenfolge (dhps_init)

1. **API-Layer**: Legacy_API -> Cache -> API_Client -> Renderer
2. **Pipeline**: Content_Pipeline(client, renderer, cache)
3. **Parser**: MIO_Parser, MMB_Parser, TP_Parser -> Parser_Registry
4. **AJAX**: AJAX_Proxy(api, cache)
5. **Demo**: Demo_Manager -> check_expired_demos()
6. **Shortcodes**: Shortcodes(client, renderer, pipeline)
7. **Widgets**: WP_Widget + set_dependencies(), Elementor(pipeline)
8. **Admin**: Admin(demo_manager)
9. **Frontend**: CSS/JS-Registrierung
