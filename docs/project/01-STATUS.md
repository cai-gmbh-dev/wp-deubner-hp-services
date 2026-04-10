# Projektstatus

## Aktuelle Version: v0.9.4 (Hardening 2026-04-10)

## Plattform
- WordPress: **6.9.4**
- Elementor: **4.0.1**
- PHP: **8.3.30**

## Versionshistorie

| Version | Inhalt | Status |
|---------|--------|--------|
| v0.3.0 | Security Hardening | Abgeschlossen |
| v0.4.0 | Architecture Refactoring (Service Registry, API Client) | Abgeschlossen |
| v0.5.0 | Layouts, WP-Widget, Elementor-Widget | Abgeschlossen |
| v0.6.0 | Demo Mode & Onboarding | Abgeschlossen |
| v0.7.0 | Per-Service Elementor Widgets (Static DI Fix) | Abgeschlossen |
| v0.8.0 | UI/UX Redesign, Design Tokens, CSS Migration | Abgeschlossen |
| v0.9.0 | Content Pipeline, MIO Parser, AJAX Proxy, MIO Templates | Abgeschlossen |
| v0.9.x | MMB Parser + Templates, TP Parser + Templates | Abgeschlossen |

## Services mit Content Pipeline (v0.9.0+)

| Service | Parser | Templates | AJAX | Status |
|---------|--------|-----------|------|--------|
| MIO | DHPS_MIO_Parser | 3 Layouts | News, Suche | Fertig |
| LXMIO | (nutzt MIO Parser) | Fallback MIO | News, Suche | Fertig |
| MMB | DHPS_MMB_Parser | 3 Layouts | Suche, PDF | Fertig |
| TP | DHPS_TP_Parser | 3 Layouts | Video-Src | Fertig |
| MIL | - | Raw HTML | - | Legacy |
| TPT | - | Raw HTML | - | Legacy |
| TC | - | Raw HTML | - | Legacy |
| MAES | - | Raw HTML | - | Legacy |
| LP | - | Raw HTML | - | Legacy |

## Offene Punkte / Bekannte Issues

1. **CSS Design Tokens**: `dhps-frontend.css` hat 46 hardcodierte Hex-Werte statt CSS-Variablen
2. **Legacy-Services**: MIL, TPT, TC, LP noch ohne Parser/Templates (MAES jetzt aktiv mit kdnr)
3. **Test-Coverage**: Keine automatisierten Tests vorhanden
4. **kdnr in Video-iframe**: Technische Limitierung der Video-Plattform, nicht vermeidbar

## Entwicklungsumgebung

- Docker: `localhost:8082` (WordPress), `localhost:8083` (phpMyAdmin)
- Datenbank: MariaDB 10.11
- Debug: `WP_DEBUG=true`, `WP_DEBUG_LOG=true`

## Zugangsdaten (Produktion/Test)

| Service | Typ | Nummer |
|---------|-----|--------|
| MIO | OTA | OTA-2023184382 |
| MMB | OTA | OTA-2024186296 |
| TP | OTA | OTA-2023182947 |
| MAES | kdnr | 51708720 |
