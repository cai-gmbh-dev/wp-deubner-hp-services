# Admin-System

## Menu-Struktur

```
Deubner Verlag (Top-Level, dvicon.svg)
├── Dashboard           -> Uebersicht aller Services, Demo-Status
├── Mi-Online           -> 2-Spalten: MIO + LXMIO Konfiguration
├── Merkblaetter        -> MMB Konfiguration
├── Infografiken        -> MIL Konfiguration
├── Tax-Videos          -> TP + TPT Konfiguration
├── Tax-Rechner         -> TC Konfiguration
├── Aerzte-Info         -> MAES Konfiguration
└── Lexplain            -> LP Konfiguration
```

## Klassen

### DHPS_Admin
- Registriert Menu + Submenus
- Laedt CSS/JS konditionell (nur auf Plugin-Seiten)
- Handelt Demo-Toggle AJAX

### DHPS_Admin_Page_Handler
- Trennt Datenlayer von Templates
- `save_settings(page_slug)` - Speichert Service-Formular
- `save_mio_form(form_key)` - Speziell fuer MIO/LXMIO (2 Formulare auf einer Seite)
- `get_page_data(page_slug)` - Laedt Options-Werte
- Nonce-Verifizierung: `verify_nonce()`

## Admin-Templates

| Template | Pfad | Zweck |
|----------|------|-------|
| Dashboard | `admin/views/dashboard.php` | Service-Status, Demo-Controls |
| MIO Config | `admin/views/mio-config.php` | 2-Spalten MIO + LXMIO |
| Service Config | `admin/views/service-config.php` | Generisches Service-Formular |
| Header | `admin/views/partials/header.php` | Navigation + Breadcrumb |

## Dashboard Features

- Service-Status-Karten: aktiv / demo / inaktiv
- Demo-Aktivierung per AJAX-Button
- Tage-Countdown bei aktiver Demo
- Shop-Links fuer inaktive Services

## Demo-System (DHPS_Demo_Manager)

### Status-Typen
- **active**: Gueltige Credentials konfiguriert
- **demo**: Demo-Modus laeuft (max 30 Tage)
- **inactive**: Keine Credentials, keine Demo

### Demo-Ablauf
1. Admin klickt "Demo starten"
2. AJAX -> `dhps_toggle_demo`
3. Original-Credentials gesichert in `dhps_demo_state`
4. Demo-Credentials geschrieben: `DEMO-{SERVICE}-2025`
5. Timer laeuft (30 Tage Standard)
6. Bei Ablauf: Original wiederhergestellt oder leer

### Demo-Credentials (filterbar)
```php
'mio'  => 'DEMO-MIO-2025'
'mmb'  => 'DEMO-MMB-2025'
'tp'   => 'DEMO-TP-2025'
// ... alle 9 Services
```

Filter: `apply_filters('dhps_demo_credentials', $creds)`

## CSS-Dateien (Admin)

```
dhps-design-tokens.css  -> CSS Custom Properties (immer)
dhps_admin.css          -> Admin-Styles (immer auf Plugin-Seiten)
dhps-ui.css             -> UI-Framework (immer auf Plugin-Seiten)
dhps-dashboard.css      -> Dashboard-spezifisch (nur Dashboard)
```

## Admin-JavaScript (dhps-admin.js)

- Demo-Toggle: AJAX POST mit Nonce
- Formular-Interaktionen
- Service-Status-Updates
