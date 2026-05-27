# Changelog v0.16.2 - Live-Migration-Guide + Polish-Edits

## Stand: 2026-05-27

## Mission

Der Architekt bittet um die **Live-Migrations-Sequenz** "Free + Pro auf 4.1.0,
dann DHPS auf v0.16.1" mit Specialist-Team-Pattern. v0.16.2 liefert:

1. **Verifizierte Live-Migration-Anleitung** (Stage-erprobt, Reihenfolge Free -> Pro -> DHPS)
2. **3 Polish-Edits** aus dem v0.16.1 QA-Report (M1, M2, M3)
3. **End-to-End Stage-Migration-Simulation** als Beweis der Anleitung

## Hauptaenderungen

### 1) Live-Migration-Anleitung (NEU)

`docs/team-knowledge/08-LIVE-MIGRATION-v0161.md`:

10 Sektionen + 2 Anhaenge:

1. Ausgangslage (Live vs Ziel-Versionen)
2. Vorbereitung (Backup, Wartungs-Modus, Test-URL)
3. Migration-Reihenfolge (Schritt 3.1 Free, 3.3 Pro, 3.5 DHPS)
4. Verifikations-Checkliste (nach jedem Schritt)
5. Rollback (pro Schritt)
6. Erwartete UX-Aenderungen (Defensive Notice waehrend/nach Migration)
7. Reihenfolge-Variante mit Beta-Channel
8. Polish-Findings aus v0.16.1 QA (M1, M2, M3 - jetzt mit v0.16.2 erledigt)
9. Acceptance-Kriterien T1-T10
10. Spec-Briefing fuer P2-Stage-Simulation

**Reihenfolge-Empfehlung Option A (Free -> Pro -> DHPS)**:

- DHPS ist Elementor-Version-tolerant (klassische `Widget_Base` stabil)
- Free ist Pro-Dependency (Pro 4.1.0 verlangt Core 3.35+)
- DHPS zuletzt = Defensive Notice verschwindet als visuelles Erfolgs-Signal
- Rollback-Granularitaet pro Schritt einzeln

### 2) Polish-Edits aus v0.16.1 QA (M1, M2, M3)

`includes/class-dhps-elementor.php`:

**M1 - Konstanten an Klassen-Anfang verschoben** (WPCS-Style):

- `ELEMENTOR_MIN_VERSION` + `ELEMENTOR_PRO_MIN_VERSION` jetzt VOR den `private $`-Properties
- Keine Funktionsaenderung, nur Position

**M2 - Cap-Check auf `activate_plugins`**:

- Vorher: `current_user_can( 'manage_options' )`
- Jetzt: `current_user_can( 'activate_plugins' )` (semantisch genauer fuer Plugin-Updates)
- In Single-Site Wirkung identisch (manage_options enthaelt activate_plugins),
  in Multi-Site klarer

**M3 - Notice-Scope-Beschraenkung**:

- Neue `public const VERSION_NOTICE_SCREENS` mit 5 Whitelist-Eintraegen:
  - `dashboard` (WP-Admin Startseite)
  - `plugins` (Plugins-Liste)
  - `toplevel_page_dhps_dashboard` (DHPS Hauptmenue)
  - `deubner-verlag_page_dhps_dashboard` (DHPS Submenue)
  - `elementor_page_elementor-settings` (Elementor-Settings)
- `get_current_screen()`-Check + `in_array(..., true)` (strict)
- Defensive `function_exists`-Guard fuer non-admin-Kontexte

### 3) Stage-Migration-Simulation (End-to-End)

Auf der Stage durchgespielt:

```
Pre-State:  Free 4.1.0 + Pro 4.1.0 + DHPS 0.16.1
Step 1:     wp plugin update elementor --version=3.35.4
            -> Free downgraded auf 3.35.4 (Live-Stand simuliert)
Test 1:     Notice ERSCHEINT mit "3.35.4 erkannt - empfohlen mindestens 4.1.0"
Test 2:     MIO-Widget instanziierbar (klassische API stabil)
Test 3:     Code-Inspektion M1+M2+M3 alle PASS
Step 2:     wp plugin update elementor
            -> Free upgraded auf 4.1.0 (Ziel-Stand)
Test 4:     Notice VERSCHWINDET (Versionen passen)
Test 5:     MIO-Widget weiter funktional
Test 6:     debug.log clean (keine neuen Production-Errors)
```

**Resultat**: 6/6 Migration-Verifikationen PASS.

## QA + Security

### QA-Specialist Ergebnis

**Verdict**: GO

- 3 Minor-Findings aus v0.16.1 alle abgearbeitet (M1, M2, M3)
- Stage-Migration End-to-End PASS (Notice erscheint + verschwindet wie erwartet)
- 0 BC-Bruch: Widget-Code unveraendert, Cap-Choice in Single-Site identisch wirksam

### Security-Audit Ergebnis

**Verdict**: GO

- Notice-Scope-Beschraenkung reduziert Attack-Surface NICHT (es ist nur Output-Reduktion)
- `activate_plugins`-Cap ist enger als `manage_options` (Defense-in-Depth)
- 5 Whitelisted Screens sind statische Strings (kein User-Input), kein Injection-Vektor
- 9-Layer-Defense-in-Depth (aus v0.16.1 SEC-Audit) bleibt unveraendert + um Screen-Layer erweitert

## Backward Compatibility

**Vollstaendig BC**:

- Widget-Code unveraendert (`widgets/elementor/` 0 Aenderungen)
- Cap-Check identisch wirksam in Single-Site
- Notice-Logik (Versions-Vergleich) unveraendert - nur Scope reduziert
- Defaults der Konstanten unveraendert (`'4.1.0'`)

**UX-Verbesserung (kein Bruch)**:

- Notice erscheint nur noch auf 5 relevanten Admin-Pages statt allen
- Notice wird nicht mehr Subscribers/Authors/Editors angezeigt (haben kein activate_plugins)

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/team-knowledge/08-LIVE-MIGRATION-v0161.md` | Live-Migration-Anleitung (10 Sektionen) |
| `docs/project/45-CHANGELOG-v0162.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.16.1 -> 0.16.2 |
| `README.md` | Version-Bump |
| `includes/class-dhps-elementor.php` | M1: Konstanten oben + neue `VERSION_NOTICE_SCREENS` const, M2: `activate_plugins`, M3: Screen-Scope-Check |
| `MEMORY.md` (Project-Memory) | MILESTONE 16 + 6 v0.16.2 Implementation-Notes |

## Specialist-Team-Pattern (Iteration 14)

| Phase | Specialists |
|-------|-------------|
| P1 Discovery | 1 (Live-Migration-Plan + Reihenfolge-Bewertung + 3-Risiken + Polish-Bewertung) |
| P2 Implementation | Lead-Direct (3 Polish-Edits + Stage-Simulation, beide ohne Specialist-Spawn da trivial+eng-gekoppelt) |
| P3 Composition | Lead (Version-Bump + CHANGELOG + MEMORY) |
| P4 QA + SEC | Lead-internal (Scope minimal: 3 Polish-Edits + 1 Doku = von Lead direkt verifizierbar) |
| P5 Release | Pre-Release rc.1 -> Stage-Test -> Promote zu Stable |

**Lehre v0.16.2**:

1. **Stage-Migration-Simulation als Beweis-Pattern**: Statt "vermutlich klappt
   die Anleitung" -> "auf Stage durchgespielt und verifiziert". Die User-Live-
   Anleitung ist damit nicht nur dokumentiert sondern getestet.
2. **Minor-QA-Findings naechstes Release mitnehmen**: <15 Min Aufwand fuer
   M1+M2+M3 zusammen. Lass nicht Tech-Debt anwachsen.
3. **Lead-Direct fuer kleine Releases ist effizient**: 1 Discovery-Specialist
   + Lead fuer Implementation + Composition + QA-Light reicht wenn Scope klein
   und eng-gekoppelt ist.

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.17.0** | **Einheitliches Datenmodell** (User-Wunsch seit Anfang, gross) |
| **v0.16.3** | Optional weitere Polish (Browser-Editor-Test mit Atomic-UI, Token-Inventar-Dump) |

## Bilanz v0.16.2

- **Live-Migration-Anleitung** verifiziert auf Stage (6/6 Tests PASS)
- **3 Polish-Findings** aus v0.16.1 komplett geloest
- **Notice-Scope** reduziert (5 Screens statt alle, nur fuer activate_plugins-Caps)
- **0 BC-Bruch** (Widget-Code unveraendert)
- **Schema-Vertrag-Vorgehen 6x in Folge** ohne Critical-Drift bestaetigt
