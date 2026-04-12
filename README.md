# Deubner Homepage Services

WordPress-Plugin zur Integration der Deubner Verlag Content-Services

Version: 0.10.1 | Lizenz: GPL-2.0-or-later | Entwicklung: CAI GmbH

---

## Was ist Deubner Homepage Services?

Das Plugin **Deubner Homepage Services** ermoeglicht es Steuerberatern, Rechtsanwaelten und Aerzten, hochwertige Fachinhalte des [Deubner Verlags](https://deubner-verlag.de) direkt in ihre WordPress-Website einzubinden. Die Inhalte werden automatisch aktualisiert und professionell dargestellt.

### Vorteile fuer Ihre Website

- **Aktuelle Fachinhalte** - Tagesaktuelle Steuer- und Rechts-Nachrichten fuer Ihre Mandanten
- **Mandanten-Merkblaetter** - Professionelle Informationsblaetter zum Download
- **Erklaervideos** - TaxPlain und Lexplain Video-Content
- **Steuerrechner** - Interaktive Online-Rechner fuer Ihre Besucher
- **Infografiken** - Visuell aufbereitete Steuer-Informationen
- **Einfache Einrichtung** - Shortcode einfuegen, fertig

---

## Enthaltene Services

| Service               | Shortcode | Beschreibung                          |
| --------------------- | --------- | ------------------------------------- |
| MI-Online Steuerrecht | `[mio]`   | Tagesaktuelle Steuerrecht-Nachrichten |
| MI-Online Recht       | `[lxmio]` | Tagesaktuelle Rechts-Nachrichten      |
| Merkblaetter          | `[mmb]`   | Mandanten-Merkblaetter zum Download   |
| Infografiken          | `[mil]`   | Steuer-Infografiken                   |
| TaxPlain Videos       | `[tp]`    | Steuer-Erklaervideos                  |
| TaxPlain Teaser       | `[tpt]`   | Video-Teaser-Element                  |
| Tax-Rechner           | `[tc]`    | Online-Steuerrechner                  |
| Meine Aerzteseite     | `[maes]`  | Informationsportal fuer Aerzte        |
| Lexplain              | `[lp]`    | Rechts-Informationsvideos             |

---

## Installation

### Variante 1: GitHub Release (empfohlen)

1. Neueste Version von [GitHub Releases](https://github.com/cai-gmbh-dev/wp-deubner-hp-services/releases) als ZIP herunterladen
2. Im WordPress-Admin unter **Plugins > Installieren > Plugin hochladen** die ZIP-Datei hochladen
3. Plugin aktivieren
4. Im Menue **Deubner Verlag** die OTA-Nummern / Kundennummern eintragen
5. Shortcode in gewuenschte Seite oder Beitrag einfuegen

### Variante 2: Git Clone

```bash
cd /pfad/zu/wp-content/plugins/
git clone https://github.com/cai-gmbh-dev/wp-deubner-hp-services.git
```

### Automatische Updates

Das Plugin prueft automatisch auf neue GitHub Releases. Updates werden im WordPress-Dashboard unter **Dashboard > Aktualisierungen** angezeigt und koennen mit einem Klick installiert werden.

### Voraussetzungen

- WordPress >= 6.0
- PHP >= 8.0
- Gueltige OTA-Nummer oder Kundennummer vom Deubner Verlag
- Optional: Elementor (fuer Elementor-Widgets)

---

## Konfiguration

### OTA-Nummer / Freigabenummer

Jeder Service benoetigt eine eigene **OTA-Nummer** (Online-Transactions-Account) oder **Kundennummer** zur Authentifizierung. Diese erhalten Sie vom Deubner Verlag.

Die Konfiguration erfolgt im WordPress-Admin unter:
**Deubner Verlag > [Service-Name]**

### Demo-Modus

Sie koennen die Services mit Demo-OTA-Nummern testen, ohne eine kostenpflichtige Freischaltung zu benoetigen. Kontaktieren Sie den Deubner Verlag fuer Demo-Zugaenge.

---

## Shortcode-Verwendung

### Grundlagen

Shortcodes werden im WordPress-Editor (Textmodus) oder in Elementor-Text-Widgets eingefuegt:

```html
[mio]
```

### Erweiterte Parameter

Einige Shortcodes unterstuetzen zusaetzliche Parameter:

```html
[mio variante="tagesaktuell" st_kategorie="einkommensteuer"]
[mmb rubrik="steuertipps"]
[tp einzelvideo="123"]
[lp filter="arbeitsrecht" teasermodus="1"]
```

### Universelle Layout-Parameter (ab v0.5.0)

Alle Shortcodes unterstuetzen die folgenden universellen Parameter:

```html
[mio layout="card"]
[mio layout="compact" class="mein-eigener-stil"]
[tp layout="card" cache="7200"]
```

| Parameter | Werte                        | Standard  | Beschreibung                    |
| --------- | ---------------------------- | --------- | ------------------------------- |
| `layout`  | `default`, `card`, `compact` | `default` | Layout-Variante des Containers  |
| `class`   | CSS-Klassenname              | (leer)    | Zusaetzliche CSS-Klasse         |
| `cache`   | Sekunden (z.B. `3600`)       | `3600`    | Cache-Dauer fuer den API-Abruf  |

Die vollstaendige Shortcode-Referenz finden Sie unter [docs/04-SHORTCODE-REFERENCE.md](docs/04-SHORTCODE-REFERENCE.md).

---

## Widget-Verwendung (ab v0.5.0)

### WordPress-Widget

Das Plugin stellt ein natives WordPress-Widget bereit, das in Sidebars und Footer-Bereichen verwendet werden kann:

1. Im WordPress-Admin unter **Design > Widgets** das Widget "Deubner Homepage Service" hinzufuegen
2. Service, Layout und optionale CSS-Klasse auswaehlen
3. Speichern

### Elementor-Widget

Bei installiertem Elementor steht ein eigenes Widget in der Kategorie "Deubner Services" zur Verfuegung:

1. Im Elementor-Editor das Widget "Deubner Service" aus dem Panel ziehen
2. Service und Layout auswaehlen
3. Im Style-Tab: Innenabstand und Eckenradius anpassen (bei Card-Layout)

---

## Projektstruktur

```text
wp-deubner-hp-services/
├── Deubner_HP_Services.php                  # Bootstrap: Konstanten, Autoloader, DI, Init
├── LICENSE                                  # GPL-2.0 Lizenztext
├── includes/                                # PHP-Klassen (Autoloaded)
│   ├── class-dhps-api-interface.php         # API-Interface (Vertrag)
│   ├── class-dhps-api-response.php          # API-Response Value-Object
│   ├── class-dhps-legacy-api.php            # Legacy-HTML-API-Implementierung
│   ├── class-dhps-cache.php                 # Transient-basierter Cache (L1 + L2)
│   ├── class-dhps-api-client.php            # API-Fassade (Cache-Aside Pattern)
│   ├── class-dhps-content-pipeline.php      # Content Pipeline (API -> Parser -> Template)
│   ├── class-dhps-parser-interface.php      # Parser-Interface
│   ├── class-dhps-parser-registry.php       # Parser-Registry (Static)
│   ├── class-dhps-service-registry.php      # Deklarative Service-Definitionen (9 Services)
│   ├── class-dhps-shortcodes.php            # Generischer Shortcode-Handler
│   ├── class-dhps-renderer.php              # Layout- und Service-Template-Engine
│   ├── class-dhps-ajax-proxy.php            # Server-seitiger AJAX-Proxy (Credential-Schutz)
│   ├── class-dhps-demo-manager.php          # Demo-Modus State-Machine (30 Tage)
│   ├── class-dhps-github-updater.php        # Automatische Updates via GitHub Releases
│   ├── class-dhps-widget.php                # WordPress-Widget
│   ├── class-dhps-elementor.php             # Elementor-Loader (9 Widgets)
│   ├── class-dhps-admin.php                 # Admin-Menues und Rendering
│   ├── class-dhps-admin-page-handler.php    # Formular-Verarbeitung
│   └── parsers/                             # Service-spezifische HTML-Parser
│       ├── class-dhps-mio-parser.php        # MIO/LXMIO: Steuertermine, News, Suche
│       ├── class-dhps-mio-news-parser.php   # MIO: AJAX-News-Paginierung
│       ├── class-dhps-mmb-parser.php        # MMB: Kategorien, Merkblaetter, PDFs
│       ├── class-dhps-mmb-search-parser.php # MMB: AJAX-Suchergebnisse
│       └── class-dhps-tp-parser.php         # TP: Videos, Kategorien, Lazy-Load
├── public/                                  # Frontend-Output
│   ├── views/                               # Templates
│   │   ├── layout-default.php               # Standard-Layout (Raw HTML)
│   │   ├── layout-card.php                  # Card-Layout (Raw HTML)
│   │   ├── layout-compact.php               # Kompakt-Layout (Raw HTML)
│   │   └── services/                        # Service-spezifische Templates (Parsed Data)
│   │       ├── mio/{default,card,compact}.php
│   │       ├── mmb/{default,card,compact}.php
│   │       └── tp/{default,card,compact}.php
│   └── js/                                  # Frontend-JavaScript (Vanilla, kein jQuery)
│       ├── dhps-mio.js                      # AJAX-News, Akkordeon, Paginierung
│       ├── dhps-mmb.js                      # Suche, Akkordeon, PDF-Download
│       └── dhps-tp.js                       # Video Lazy-Load, Kategorie-Filter
├── widgets/elementor/                       # Elementor-Widgets (Elementor 4.x kompatibel)
│   ├── class-dhps-elementor-widget-base.php     # Abstrakte Basis (Static DI)
│   └── class-dhps-elementor-service-widgets.php # 9 Service-Widgets
├── admin/                                   # Admin-Bereich
│   ├── views/                               # Admin-Templates
│   │   ├── dashboard.php                    # Service-Status, Demo-Controls
│   │   ├── service-config.php               # Generisches Service-Formular
│   │   ├── mio-config.php                   # MI-Online Dual-Formular (MIO + LXMIO)
│   │   └── partials/header.php              # Navigation + Breadcrumb
│   └── js/dhps-admin.js                     # Admin-JavaScript (Demo-Toggle)
├── css/                                     # Stylesheets
│   ├── dhps-design-tokens.css               # CSS Custom Properties (--dhps-*)
│   ├── dhps_base.css                        # Basis-Styles (Elementor-Compat)
│   ├── dhps-frontend.css                    # Frontend-Komponenten (BEM)
│   ├── dhps-ui.css                          # UI-Framework (Admin)
│   ├── dhps_admin.css                       # Admin-Dashboard Styles
│   └── dhps-dashboard.css                   # Dashboard-spezifisch
├── docs/                                    # Dokumentation
│   ├── architecture/                        # Architektur (9 Dokumente)
│   ├── project/                             # Projektstatus (4 Dokumente)
│   └── team-knowledge/                      # Team-Wissen (6 Dokumente)
├── assets/images/dvicon.svg                 # Deubner Verlag Icon
├── uninstall.php                            # Plugin-Deinstallation (Options + Transients)
└── README.md                                # Diese Datei
```

---

## Entwicklung

### Roadmap

| Version | Phase                                          | Status  |
| ------- | ---------------------------------------------- | ------- |
| v0.3.0  | Security Hardening & Code-Review               | Fertig  |
| v0.4.0  | Architektur-Refactoring                        | Fertig  |
| v0.5.0  | Moderne Layouts, WP-Widgets, Elementor-Widgets | Fertig  |
| v0.6.0  | Demo-Modus & Onboarding                        | Fertig  |
| v0.7.0  | Elementor-Widgets pro Service                  | Fertig  |
| v0.8.0  | UI/UX Redesign, Design Tokens, CSS Migration   | Fertig  |
| v0.9.0  | Content Pipeline, Parser, AJAX Proxy            | Fertig  |
| v0.9.5  | GitHub Updater, Hardening, Elementor 4.x        | Fertig  |
| v1.0.0  | Neue API-Anbindung, Datenaufbereitung          | Geplant |

Details: [docs/02-DEVELOPMENT-ROADMAP.md](docs/02-DEVELOPMENT-ROADMAP.md)

### Dokumentation

Die gesamte Projektdokumentation befindet sich im [docs/](docs/) Verzeichnis:

- **[Projektanalyse](docs/01-PROJECT-ANALYSIS.md)** - Ist-Zustand, Code-Review, Sicherheitsbefunde
- **[Entwicklungsplan](docs/02-DEVELOPMENT-ROADMAP.md)** - Phasen, Tasks, Architektur-Ziel
- **[API-Referenz](docs/03-API-REFERENCE.md)** - Aktuelle und geplante API-Endpoints
- **[Shortcode-Referenz](docs/04-SHORTCODE-REFERENCE.md)** - Alle Shortcodes und Parameter

### Technische Konventionen

- PHP: WordPress Coding Standards
- Prefix: `dhps_` fuer alle Funktionen, Optionen, Hooks
- Kommentierung: PHPDoc fuer alle Klassen und Methoden
- Git: Conventional Commits (`feat:`, `fix:`, `refactor:`, `docs:`)

---

## Support

Bei Fragen zur Einrichtung oder Freischaltung:

- **E-Mail:** <mi-online-technik@deubner-verlag.de>
- **Telefon:** 0221 / 93 70 18-28
- **Shop:** <https://www.deubner-steuern.de/shop/homepage-services.html>

---

## Lizenz und Copyright

Dieses Plugin ist lizenziert unter der [GNU General Public License v2.0 or later](LICENSE).

- **Herausgeber:** CAI GmbH, Hansestadt Wipperfuerth, Deutschland
- **Entwicklung:** CAI GmbH / Kai R. Emde
- **Inhalte-Anbieter:** Deubner Verlag GmbH & Co. KG, Koeln
- **Copyright:** 2004 - 2026, Deubner Verlag GmbH & Co. KG / CAI GmbH

Die ueber dieses Plugin eingebundenen Inhalte (Texte, Merkblaetter, Videos, Infografiken)
sind Eigentum des Deubner Verlags und nicht Bestandteil dieser Lizenz.
Die Nutzung der Inhalte erfordert eine gueltige Freischaltung (OTA-Nummer / Kundennummer)
beim Deubner Verlag.
