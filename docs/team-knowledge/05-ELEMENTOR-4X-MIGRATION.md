# Elementor 4.x Migration Guide

## Stand: 2026-04-10 - Elementor 4.0.1

## Kompatibilitaetsstatus: VOLL KOMPATIBEL

Das Plugin nutzt ausschliesslich moderne Elementor-APIs (eingefuehrt in 3.5+).
Beim Update von 3.35.4 auf 4.0.1 waren **keine Code-Aenderungen** noetig.

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
