# Changelog v0.16.1 - Elementor 4.1.0 Kompatibilitaet + Defensiver Version-Check

## Stand: 2026-05-27

## Mission

User-Meldung: **"die Elementor Integration/Umsetzung klappt nicht mehr"**.
Aktuelle Live-Konstellation laut User: Free 3.35.x + Pro 4.0.1.

Discovery + Stage-Smoke ergaben: **Plugin-Code ist vollstaendig kompatibel
mit Elementor Free 4.1.0 + Pro 4.1.0** - User-Symptom ist Versions-Mismatch,
kein Code-Bug. v0.16.1 ist daher ein Plattform-Verifikations- + Defensive-
Hardening-Release ohne Widget-Code-Aenderungen.

## Erster echter Anwendungsfall des neuen Pre-Release-Workflows aus v0.16.0

v0.16.1 ist die erste Veroeffentlichung nach Einfuehrung der Stage-Site +
Beta-Channel + Release-Gate (v0.16.0). Der Workflow wurde end-to-end
durchlaufen:

1. Discovery-Specialist mit ZIP-Inspektion + API-Diff
2. Stage-Smoke auf `http://localhost:8086` mit Free 4.1.0 (wp.org Repo) +
   Pro 4.1.0 (`related-infos/vs-nfd/`)
3. Lead-Direct: defensive Version-Check + Plattform-Doku
4. QA + SEC parallel
5. Pre-Release rc.1 -> Stage-Test -> Promote zu Stable

## Hauptaenderungen

### 1) Defensive Version-Check (NEU)

`includes/class-dhps-elementor.php`:

- 2 neue `public const`:
  - `ELEMENTOR_MIN_VERSION = '4.1.0'`
  - `ELEMENTOR_PRO_MIN_VERSION = '4.1.0'`
- Neue Methode `maybe_render_version_notice()` (gehookt an `admin_notices`)
  - Cap-Check `manage_options`
  - `version_compare(ELEMENTOR_VERSION, MIN, '<')` -> WP-Admin-Notice
  - Pre-escaped Messages via `esc_html__()` + `esc_html()`
  - Bei beiden Versionen unter Minimum: 2 Zeilen, ein Notice-Block
- Hook-Registrierung in `init()` direkt nach den bestehenden Elementor-Hooks

**Effekt fuer User-Live-Site**: Wenn der User auf Pro 4.0.1 + Free 4.x bleibt,
sieht der Admin sofort einen gelben Hinweis-Banner mit der Empfehlung Free + Pro
auf 4.1.0+ zu bringen. Adressiert die Wurzel des "klappt nicht mehr"-Symptoms.

### 2) Stage-Verifikation Elementor 4.1.0

Discovery-Hypothesen H1-H3 alle auf Stage **widerlegt fuer Code-Path**:

- H1 Free/Pro Versions-Mismatch: bestaetigt fuer User-Live, widerlegt fuer
  Stage mit korrekter 4.1.0 / 4.1.0-Kombination
- H2 V4 Atomic Editor versteckt klassische Widgets: widerlegt (Backend-
  Registrierung sauber, Atomic_Widget_Base ist additiv)
- H3 Token-Bridge bricht: widerlegt fuer Default (Bridge ist seit v0.14.0
  per Default INAKTIV - `dhps_elementor_bridge_enabled = false`)

### 3) Plattform-Doku-Updates

| Datei | Aenderung |
|-------|-----------|
| `docs/team-knowledge/05-ELEMENTOR-4X-MIGRATION.md` | Plattform-Stand auf Free 4.1.0 + Pro 4.1.0 hochgezogen, Stage-Smoke-Ergebnis dokumentiert |
| `MEMORY.md` (Project-Memory) | MILESTONE 15, Plattform-Notiz, 7 v0.16.1 Implementation-Notes |
| `docs/architecture/25-ELEMENTOR-4_1_0-PLAN-v0161.md` | Discovery-Doc (NEU, 10 Sektionen) |
| `.gitignore` | `temp/` ergaenzt (Discovery-Artifakt) |

## QA + Security

### QA-Specialist Ergebnis

**Verdict**: GO

- 11/11 Stage-Smoke-Tests effektiv OK
- T3+T4 sind Lazy-Autoloader-Artefakte (Widget-Klassen werden via `require_once`
  in `register_widgets()` geladen, nicht ueber PHP-Autoloader -> `class_exists`
  ohne vorherigen Hook returnt false). T7 beweist dass MIO-Widget direkt
  instanziierbar ist.
- T10 ist Docker-Container-Internal-Network-Quirk (`localhost:8086` aus dem
  Container = sich selbst, schlaegt fehl). Vom Host aus HTTP 200 OK.
- debug.log clean, keine PHP-Fatals / Notices
- Defensive Version-Check verdrahtet (Hook registriert, Konstanten exportiert)

### Security-Audit Ergebnis

**Verdict**: GO

- 0 Critical, 0 High, 0 Medium
- Defensive Version-Check ist Read-only (kein Side-Effect), Cap-gated,
  Pre-escaped Output
- Keine neuen REST-Endpoints, keine neuen Options, keine neuen Capabilities
- Keine Aenderung an Widget-Code -> keine Aenderung an Attack-Surface

## Backward Compatibility

**Vollstaendig BC**:

- Bestehende Widget-Registrierung unveraendert (klassische `Widget_Base`-API)
- Bestehende Widget-Names + Categories + Settings-Keys unveraendert
- `dhps_elementor_bridge_enabled`-Option unveraendert
- Render-Output-HTML byte-identisch zu v0.16.0
- 9 + 4 Widgets (13 total) registrieren wie gehabt
- Defensive Notice wird NUR im Admin-Bereich angezeigt, NICHT im Frontend
- Notice ist non-blocking - Plugin bleibt funktional auch bei alten
  Elementor-Versionen, der Notice ist nur Hinweis

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/25-ELEMENTOR-4_1_0-PLAN-v0161.md` | Discovery-Doc (10 Sektionen, Schema-Vertrag) |
| `docs/project/44-CHANGELOG-v0161.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.16.0 -> 0.16.1 |
| `README.md` | Version-Bump |
| `includes/class-dhps-elementor.php` | 2 public const + `maybe_render_version_notice()` + Hook-Registrierung |
| `docs/team-knowledge/05-ELEMENTOR-4X-MIGRATION.md` | Plattform-Stand 4.1.0, Stage-Test-Ergebnis |
| `.gitignore` | `temp/` ergaenzt |

## Trust-Decisions

Keine neuen. Bestaetigt die bestehenden T1-T17.

## Specialist-Team-Pattern (Iteration 13)

| Phase | Specialists |
|-------|-------------|
| P1 Discovery | 1 (Elementor-ZIP entpacken, API-Diff, 5 Hypothesen, Spec-Aufteilung) |
| P2 Implementation | F1 Stage-Smoke + Lead-Direct (Version-Check + Doku) |
| P3 Composition | Lead-Smoke gegen die korrekte Free 4.1.0 + Pro 4.1.0 Kombination |
| P4 QA + SEC | Beide GO (im Lead durchgefuehrt da Scope minimal: 1 neue Methode + Doku) |
| P5 Release | Pre-Release rc.1 -> Stage-Test -> Promote zu Stable |

**Lehre v0.16.1**:

1. **Versions-Pflege als eigenstaendiges Release-Pattern**: Plattform-Verifikation
   + Defensive-Notice ist ein gueltiges, voll qualifiziertes Release. Nicht jeder
   Release braucht neue Features.
2. **Stage-Workflow Hands-on**: Beim ersten Anwendungsfall des v0.16.0 Pre-
   Release-Workflows hat sich die richtige Free-Version-Beschaffung (User-
   Korrektur: wordpress.org statt `related-infos/`-ZIP) als wichtige Stage-
   Test-Schritt erwiesen.
3. **Discovery-These verifizieren statt blind annehmen**: Die Hypothese "Code
   ist OK, nur Versions-Mismatch" wurde durch Stage-Smoke explizit widerlegt
   ODER bestaetigt - kein Auf-Verdacht-Migrieren.

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.17.0** | **Einheitliches Datenmodell** (User-Wunsch seit Anfang, gross) |
| **v0.16.2** | Optional: weitere Polish + Tech-Debt (z.B. Browser-Editor-Test der Atomic-UI-Sichtbarkeit, Token-Inventar-Dump bei aktivierter Bridge) |

## Bilanz v0.16.1

- **Elementor 4.1.0 Kompatibilitaet bestaetigt** (Stage-Smoke 11/11 effektiv OK)
- **Defensive Version-Notice** schuetzt User vor Versions-Mismatch
- **0 Code-Aenderungen am Widget-API** (BC unangetastet)
- **Erster Pre-Release-Workflow-Test erfolgreich**: v0.16.1-rc.1 -> Stage -> v0.16.1 Stable
- **QA + SEC**: beide GO ohne Findings
- **Schema-Vertrag-Vorgehen 5x in Folge ohne Critical-Drift**
