# Changelog v0.16.3 - Polish-Sammelrelease (Stage-Branding + CLI-Diagnostik + Editor-Test-Doku)

## Stand: 2026-05-29

## Mission

Kleines Polish-Release mit 4 unabhaengigen Tickets aus der Tech-Debt-Liste:

- **P1+P2** Stage-Branding (sichtbares Erfolgs-Signal "nicht Live")
- **P4** WP-CLI Token-Inventar-Diagnostik
- **P6** Manueller Editor-Test als Pflicht-Checkliste

P3 (LXMIO-OTA fehlt) bewusst nicht in v0.16.3 - bleibt offen als Hinweis.
P5/P7-P9 (Bridge-Smoke / Updater-Tech-Debt) bleiben fuer spaeter.

## Hauptaenderungen

### 1) Stage-Branding (P1+P2)

`docker/stage-mu-plugins/dhps-stage-marker.php` (NEU, ~95 LOC):

WordPress-mu-plugin das **nur in der Stage** aktiv ist (Gate via
`DHPS_ENV_LABEL === 'STAGE'`-Konstante, die im `docker-compose.staging.yml`
gesetzt ist). Vier sichtbare Effekte:

- **Roter Admin-Bar** (`#b32d2e`) statt schwarz/grau
- **"[ STAGE ]"-Praefix** in 4 Title-Hooks:
  - `admin_bar_menu` (Site-Name-Node)
  - `admin_title` (Browser-Tab im wp-admin)
  - `wp_title` (Frontend Tab-Title fallback)
  - `document_title_parts` (Frontend Tab-Title modern)
- **Hellroter Banner** unter dem Admin-Bar: "Diese WordPress-Instanz ist nicht
  Live. Aenderungen wirken nur lokal."
- Kein Eingriff in Frontend-Rendering ausser Tab-Title-Praefix

`docker-compose.staging.yml`: neuer Volume-Mount fuer
`./docker/stage-mu-plugins/` nach `/var/www/html/wp-content/mu-plugins/`.

### 2) WP-CLI Token-Inventar (P4)

`includes/class-dhps-cli-commands.php` (NEU, ~160 LOC):

Neuer Befehl `wp dhps elementor-tokens`. Liefert auf der CLI:

- Plugin-Version, Elementor-Version, Pro-Version, Bridge-Status
- Mindest-Versionen aus `DHPS_Elementor::ELEMENTOR_MIN_VERSION`
- Geparste `--dhps-color-*`-Tokens aus `css/dhps-design-tokens.css`
- Klassifikation in 7 Gruppen: brand-steuern, brand-recht, brand-medizin,
  semantic, text, layout, badge, misc
- Hinweis-Text wie Bridge aktiviert wird (falls inaktiv)

Stage-Output (Live-Test 2026-05-29):

```
=== DHPS Elementor Token-Inventar ===
Plugin:           0.16.3
Elementor:        4.1.0
Elementor Pro:    4.1.0
Bridge aktiv:     NEIN (Default)
Min Elementor:    4.1.0

Geparst aus css/dhps-design-tokens.css:
66 Tokens gefunden.
```

Implementierung:

- Datei mit early-return-Gate `!defined('WP_CLI') || true !== WP_CLI`
- Manueller `require_once` im Plugin-Bootstrap (kein Autoload, weil
  `WP_CLI::add_command` sonst nie laeuft)
- Static-Method-Pattern fuer Command-Callback (WP-CLI-Best-Practice)
- Regex-Parser `/(--[a-z0-9-]+)\s*:\s*([^;]+);/i` mit `preg_match_all`
- Erstwert gewinnt bei wiederholten Definitionen

### 3) Editor-Test-Doku (P6)

`docs/team-knowledge/09-ELEMENTOR-EDITOR-MANUAL-TEST.md` (NEU):

15-Schritte-Pflicht-Checkliste fuer manuellen Browser-Sichtbarkeitstest des
Elementor-Editors. Deckt Hypothese H2 aus v0.16.1 Discovery (V4 Atomic-Editor
koennte klassische Widgets unter neuer UI-Sektion verstecken).

Sektionen:

- Voraussetzungen + Setup einer Test-Page
- T1-T2 Editor-Load + Browser-Console
- T3-T5 Widget-Panel-Sichtbarkeit + Kategorie + Icon
- T6-T8 Drag-and-Drop + Settings-Panel + Live-Preview
- T9.1-T9.9 Smoke aller 9 Hauptservice-Widgets
- T10-T11 Steuertermine + 3 MAES-Sub-Widgets
- T12-T14 Speichern + Vorschau + Frontend
- T15 V4 Atomic-Editor-Verhalten
- Bewertungs-Matrix + Aktion bei NOK
- Token-Bridge-Diagnose-Hinweis mit `wp dhps elementor-tokens`
- Visuelle Stage-Marker-Erwartungen

## QA + Security

### QA-Smoke (Lead-direkt durchgefuehrt, kein Specialist-Spawn)

- v0.16.3 Version-Bump an 3 Stellen + README + MEMORY -> OK
- mu-plugin geladen + 2 Hooks registriert + `DHPS_ENV_LABEL=STAGE` aktiv -> OK
- `wp dhps elementor-tokens` laeuft, 66 Tokens geparst, Bridge-Status korrekt -> OK
- Editor-Test-Doku hat alle 15 Schritte + Voraussetzungen + Bewertungs-Matrix -> OK
- Widget-Code in `widgets/elementor/` unveraendert seit v0.16.2 -> OK
- debug.log clean nach mu-plugin-Reload -> OK

### Security-Skizze

- mu-plugin: Output nur im Admin (Cap-implizit via `admin_head`/`admin_bar_menu`-Hook
  laufen nur fuer eingeloggte Admin-User mit `read`-Cap, output ist statisches HTML
  ohne User-Input)
- CLI-Command: nur via `WP_CLI === true`-Gate, CLI-Kontext hat ohnehin Root-Access
  zur DB, kein zusaetzliches Risiko
- Editor-Test-Doku: reine Markdown-Datei

Keine neuen Angriffsflaechen. Keine REST-Endpoints, keine Options-Schreibzugriffe,
keine User-Inputs verarbeitet.

## Backward Compatibility

**Vollstaendig BC**:

- Live-Site (ohne `DHPS_ENV_LABEL`-Konstante) sieht nichts vom mu-plugin
- CLI-Command nur im CLI-Kontext sichtbar, im Web-Request inaktiv
- Widget-Code unveraendert (`widgets/elementor/` 0 Aenderungen)
- Bestehende REST-Endpoints unangetastet
- Bestehende Options unangetastet
- Bestehender Updater unangetastet

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docker/stage-mu-plugins/dhps-stage-marker.php` | Stage-only WordPress-mu-plugin (P1+P2) |
| `includes/class-dhps-cli-commands.php` | WP-CLI Token-Inventar-Command (P4) |
| `docs/team-knowledge/09-ELEMENTOR-EDITOR-MANUAL-TEST.md` | 15-Schritte-Pflicht-Checkliste (P6) |
| `docs/project/46-CHANGELOG-v0163.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.16.2 -> 0.16.3 + neuer require_once fuer CLI-Klasse |
| `README.md` | Version-Bump |
| `docker-compose.staging.yml` | Neuer Volume-Mount fuer mu-plugins |
| `MEMORY.md` (Project-Memory) | MILESTONE 17 + 5 v0.16.3 Implementation-Notes |

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.17.0** | **Einheitliches Datenmodell** (User-Wunsch seit Anfang, gross) |
| **v0.16.4** | weitere Polish: P3 (LXMIO-OTA-Doku), P5 (Bridge-Smoke), P7-P9 (Updater) |

## Bilanz v0.16.3

- **Stage-Branding** sichtbar (4 Title-Hooks + Banner + roter Admin-Bar)
- **CLI-Diagnostik** liefert 66 Tokens + Bridge-Status
- **Editor-Test-Pflicht** in 15 Schritten dokumentiert
- **0 Code-Aenderungen** an Widget-Files (`widgets/elementor/`)
- **0 BC-Bruch**
- Schema-Vertrag-Vorgehen 7x in Folge
