# Projektanalyse: Deubner Homepage Services

## 1. Ist-Zustand (v0.2.0)

### 1.1 Produktbeschreibung

Das WordPress-Plugin **"Deubner Homepage Services"** integriert verschiedene Content-Services des Deubner Verlags (Koeln) in Kundenwebsites. Die Inhalte werden per API von `www.deubner-online.de` abgerufen und per Shortcode auf der Kundenseite eingebettet.

### 1.2 Konsolidierte Services

Das Plugin fasst 8 ehemals eigenstaendige Plugins zusammen:

| # | Service | Shortcode | Zielgruppe | Beschreibung |
|---|---------|-----------|------------|--------------|
| 1 | MI-Online Steuerrecht | `[mio]` | Steuerberater | Tagesaktuelle Steuerrecht-Nachrichten |
| 2 | MI-Online Recht | `[lxmio]` | Rechtsanwaelte | Tagesaktuelle Rechts-Nachrichten |
| 3 | Merkblaetter | `[mmb]` | Steuerberater | Downloadbare Mandanten-Merkblaetter |
| 4 | Infografiken | `[mil]` | Steuerberater | Visuelle Infografiken |
| 5 | TaxPlain Videos | `[tp]` | Steuerberater | Steuer-Erklaervideos |
| 6 | TaxPlain Teaser | `[tpt]` | Steuerberater | Video-Teaser-Widgets |
| 7 | Tax-Rechner | `[tc]` | Steuerberater | Online-Steuerrechner |
| 8 | Meine Aerzteseite | `[maes]` | Aerzte/Steuerberater | Informationsportal fuer Aerzte |
| 9 | Lexplain | `[lp]` | Rechtsanwaelte | Rechtsinformations-Videos |

### 1.3 Technische Architektur (Ist)

```
Deubner_HP_Services.php (1.287 Zeilen - Monolith)
├── class Deubner_HP_Services        (Singleton, Plugin-Initialisierung)
├── class DeubnerHPServicesAdmin     (CSS-Loading, Admin-Enqueue)
├── class DeubnerHPServicesShortCodes (9 Shortcodes, API-Aufrufe)
└── class DeubnerHPServicesDashBoardMenus (8 Admin-Seiten, Formulare)
```

**Dateien:**
- `Deubner_HP_Services.php` - Gesamte Plugin-Logik (54 KB)
- `css/dhps_admin.css` - Admin-Styling (~1.709 Zeilen)
- `css/dhps_base.css` - Elementor-Basisstyles (37 Zeilen)
- `css/dhps-ui.css` - UI-Framework (~460 KB)
- `assets/images/dvicon.svg` - Deubner-Logo
- `uninstall.php` - Leer (keine Cleanup-Logik)

### 1.4 API-Kommunikation

**Base-URL:** `https://www.deubner-online.de/`

**Authentifizierung:** OTA-Nummer (Online-Transactions-Account) oder Kundennummer (kdnr)

| Service | API-Endpoint | Auth-Typ |
|---------|-------------|----------|
| MIO/LXMIO | `einbau/mio/bin/php_inhalt.php` | OTA |
| Merkblaetter | `einbau/mmo/merkblattpages/php_inhalt.php` | OTA |
| Infografiken | `einbau/mil/bin/php_inhalt.php` | OTA |
| TaxPlain | `einbau/taxplain/videopages/php_inhalt.php` | OTA |
| TaxPlain Teaser | `taxplain/videopages/teaser_php.php` | Kundennr |
| Tax-Rechner | `webcalc/bin/php_inhalt_v2.php` | Kundennr |
| Aerzteseite | `infokombi/bin/infokombi.php` | Kundennr |
| Lexplain | `lexplain/bin/php_inhalt.php` | OTA |

**Datenformat:** Die API liefert HTML-Fragmente (kein JSON/XML), die direkt in die Seite eingefuegt werden.

### 1.5 OTA-Nummern / Freigabe-Nummern

OTA-Nummern werden als WordPress-Options gespeichert:

| Option-Key | Service | Typ |
|-----------|---------|-----|
| `ota_mio` | MI-Online Steuerrecht | OTA |
| `lxmio_ota` | MI-Online Recht | OTA |
| `mmo_ota` | Merkblaetter | OTA |
| `mil_ota` | Infografiken | OTA |
| `ota_tp` | TaxPlain | OTA |
| `tp_kdnr` | TaxPlain Teaser | Kundennr |
| `tc_kdnr` | Tax-Rechner | Kundennr |
| `maes_kdnr` | Aerzteseite | Kundennr |
| `lp_ota` | Lexplain | OTA |

**Demo-OTA-Nummern:** Muessen vom Verlag bereitgestellt werden, damit Kunden die Services ohne kostenpflichtige Freischaltung testen koennen.

---

## 2. Code-Review: Kritische Befunde

### 2.1 Sicherheitsprobleme (KRITISCH)

| # | Problem | Schwere | Datei:Zeile | Beschreibung |
|---|---------|---------|-------------|-------------|
| S1 | **Kein CSRF-Schutz** | KRITISCH | Alle Admin-Formulare | Keine `wp_nonce_field()` / `wp_verify_nonce()` |
| S2 | **Keine Input-Sanitierung** | KRITISCH | Z.898-911, etc. | `$_POST` nur mit `stripslashes()`, kein `sanitize_text_field()` |
| S3 | **Keine Output-Escaping** | HOCH | Z.930, 967, etc. | `get_option()` ohne `esc_attr()` / `esc_html()` |
| S4 | **XSS via $_GET** | KRITISCH | Z.619-623, 696-701, 739-743, 789-793 | `$_GET['video']` ohne Sanitierung direkt in URL |
| S5 | **Kein URL-Encoding** | HOCH | Alle Shortcodes | Parameter ohne `urlencode()` in API-URL |
| S6 | **file_get_contents()** | MITTEL | Z.380, 469, etc. | Statt `wp_remote_get()` - kein Timeout, kein Error-Handling |
| S7 | **fsockopen() Fallback** | HOCH | Z.382-401, etc. | Veraltete Methode, unvollstaendiger HTTP-Request (kein HTTP/1.1) |

### 2.2 Architekturprobleme

| # | Problem | Beschreibung |
|---|---------|-------------|
| A1 | **Monolithische Datei** | 1.287 Zeilen in einer Datei, 4 Klassen |
| A2 | **Massive Code-Duplikation** | Identische Fetch-Logik 9x kopiert (~50 Zeilen je Shortcode) |
| A3 | **Keine Separation of Concerns** | HTML-Ausgabe direkt in Methoden (echo-Kaskaden) |
| A4 | **Falsche Getter** | Z.146: `$this->DeubnerHPServicesDashBoardMenus` statt `$this->dhpsMenu` |
| A5 | **Hartcodierte API-URL** | `www.deubner-online.de` ueberall direkt im Code |
| A6 | **Kein Caching** | Jeder Seitenaufruf = neuer API-Call |
| A7 | **Kein Error-Handling** | Kein Fallback bei API-Fehler |
| A8 | **Leere Methoden** | `load_certain_stylesheet()`, `add_menu_pages()` - toter Code |
| A9 | **Tippfehler** | Z.1247: "Dashbaord" statt "Dashboard" |
| A10 | **Fehlende Uninstall-Logik** | `uninstall.php` ist leer - Options bleiben in DB |

### 2.3 Fehlende Features

| # | Feature | Status |
|---|---------|--------|
| F1 | WordPress-Widgets | Nicht implementiert |
| F2 | Elementor-Widgets | Nicht implementiert |
| F3 | Demo-Modus | Nicht implementiert |
| F4 | API-Abstraktionsschicht | Nicht vorhanden |
| F5 | Template-System | Nicht vorhanden |
| F6 | Response-Caching | Nicht vorhanden |
| F7 | i18n / Uebersetzungen | Vorbereitet aber leer |
| F8 | Settings-API | Nicht genutzt (raw update_option) |
| F9 | REST-API Endpoints | Nicht vorhanden |
| F10 | Aktivierung/Deaktivierung | Keine Hooks |
| F11 | Moderne Layouts | Nur Roh-HTML vom Server |

---

## 3. Abhaengigkeiten

- **WordPress** >= 5.0 (empfohlen >= 6.0)
- **PHP** >= 7.4 (empfohlen >= 8.0)
- **Elementor** (optional, fuer Elementor-Widgets)
- **API-Server:** `www.deubner-online.de` (extern, nicht kontrollierbar)
- **Keine** externen PHP-Bibliotheken (kein Composer)
