# Elementor 4.x Migration Guide

## Stand: 2026-05-27 - Elementor Free 4.1.0 + Pro 4.1.0 verifiziert

## Kompatibilitaetsstatus: VOLL KOMPATIBEL

Das Plugin nutzt ausschliesslich moderne Elementor-APIs (eingefuehrt in 3.5+).
Beim Update von 3.35.4 -> 4.0.1 -> **4.1.0** waren **keine Code-Aenderungen**
am Widget-API noetig. v0.16.1 fuegt nur einen defensiven Version-Check als
Admin-Notice hinzu (siehe Sektion "Version-Check" am Ende).

## Verifizierter Plattform-Stand (v0.16.1)

| Komponente | Version | Quelle | Status |
|------------|---------|--------|--------|
| Elementor Free | 4.1.0 | wordpress.org/plugins/elementor | aktiv getestet auf Stage |
| Elementor Pro | 4.1.0 | related-infos/vs-nfd/elementor-pro-4.1.0.zip | aktiv getestet auf Stage |
| WordPress | 6.9.1 | offiziell | aktiv getestet |
| PHP | 8.3.30 | Docker WP-Image | aktiv getestet |

**Test-Setup:** Stage-Stack auf `http://localhost:8086` (siehe
`docs/team-knowledge/01-ENTWICKLUNGSUMGEBUNG.md`).

**Stage-Smoke Ergebnis:** 11/11 Tests effektiv OK (T3+T4 Lazy-Autoloader-
Artefakte werden ueber den `elementor/widgets/register`-Hook normal aufgeloest,
T7 beweist Widget-Instanziierbarkeit; T10 Container-Internal-Netz-Quirk, vom
Host aus HTTP 200).

## Verwendete APIs (alle 4.x-kompatibel)

| API | Status | Datei |
|-----|--------|-------|
| `elementor/widgets/register` Hook | OK | class-dhps-elementor.php |
| `elementor/elements/categories_registered` Hook | OK | class-dhps-elementor.php |
| `$widgets_manager->register()` | OK | class-dhps-elementor.php |
| `\Elementor\Widget_Base` Basisklasse | OK | class-dhps-elementor-widget-base.php |
| `Controls_Manager::SELECT/TEXT/NUMBER/DIMENSIONS` | OK | class-dhps-elementor-widget-base.php |
| `start_controls_section()` / `add_control()` | OK | class-dhps-elementor-widget-base.php |
| `{{WRAPPER}}` Selektor-Syntax | OK | class-dhps-elementor-widget-base.php |

## NICHT verwendete (deprecated) APIs

| API | Status | Seit |
|-----|--------|------|
| `elementor/widgets/widgets_registered` | Nicht verwendet | Deprecated 3.5 |
| `register_widget_type()` | Nicht verwendet | Deprecated 3.5 |
| `\Elementor\Scheme_*` | Nicht verwendet | Removed 3.0 |
| `\Elementor\Plugin::instance()` direkt | Nicht verwendet | - |

## Zukuenftige Elementor-Updates

Bei zukuenftigen Elementor-Updates pruefen:
1. `elementor/widgets/register` Hook weiterhin unterstuetzt?
2. `Widget_Base` Klasse unveraendert?
3. `Controls_Manager` Konstanten stabil?
4. `get_settings_for_display()` Methode verfuegbar?

## Dateien mit Elementor-Integration

- `includes/class-dhps-elementor.php` - Registrierung + Kategorie
- `widgets/elementor/class-dhps-elementor-widget-base.php` - Abstrakte Basis
- `widgets/elementor/class-dhps-elementor-service-widgets.php` - 9 konkrete Widgets
