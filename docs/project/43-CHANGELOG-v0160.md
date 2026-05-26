# Changelog v0.16.0 - Dev-Strecke + Release-Gate + Beta-Channel

## Stand: 2026-05-26

## Mission

Erstes Release der v0.16.x-Serie. Behebt die User-Meldung "automatisierte
Aktualisierungen melden zurueck, dass es zu BUGs kommt" durch Einfuehrung eines
**Beta-Channels** im GitHub-Updater, einer parallelen **Stage-Site** als
zweites Docker-Compose-Setup und einer verbindlichen **Release-Gate-Checkliste**.

## Hauptaenderungen

### 1) GitHub-Updater Beta-Channel-Support

Backend (`includes/class-dhps-github-updater.php`):

- Neue `public const ALLOWED_CHANNELS = array( 'stable', 'beta' )`
- Neue `public static sanitize_channel( $value ): string` mit Whitelist-Check
  via `sanitize_key( (string) $value )` + `in_array( ..., true )` + Fallback
  `'stable'`
- Konstruktor um 5. Parameter `string $channel = 'stable'` erweitert
- `get_latest_release()` als Channel-Switch
- Neue private Methode `get_release_for_stable_channel()` (vorhandener Pfad,
  Cache-Key `dhps_github_release`, Endpoint `/releases/latest`)
- Neue private Methode `get_release_for_beta_channel()` (Cache-Key
  `dhps_github_release_beta`, Endpoint `/releases?per_page=30`, foreach mit
  Skip auf `draft`, Match-Bedingung `version_compare > current`)
- `flush_release_cache()` leert BEIDE Transients

Plugin-Bootstrap (`Deubner_HP_Services.php`):

- `dhps_init()`: Updater-Konstruktor-Aufruf bekommt 5. Argument
  `get_option( 'dhps_update_channel', 'stable' )`
- `dhps_register_options()`: neuer `register_setting`-Block fuer
  `dhps_update_channel` mit Sanitize-Callback und `show_in_rest => false`
- `dhps_activate()` `$defaults` um `dhps_update_channel => 'stable'`
  ergaenzt (M2 aus QA-Report)

### 2) Admin-UI - Channel-Switcher

Admin-Layer (`includes/class-dhps-admin.php`):

- Neue public Property `$update_channel_saved`
- Neue Methode `dispatch_dashboard_action()` mit `switch ( $_POST['dhps_action'] )`
- Neuer Case `save_update_channel` ruft neue Methode
  `handle_save_update_channel()` mit Cap-Check `manage_options`, Nonce-Verify
  `DEUBNER_HP_SERVICES_NONCE_ACTION`, Sanitize via
  `DHPS_GitHub_Updater::sanitize_channel()`, `update_option()` und
  Cache-Flush BEIDER Transients

View (`admin/views/dashboard.php`):

- Channel-Switch-Block am Ende der Content-Area
- `wp_nonce_field`, Radio-Buttons mit `checked()`-Helper auf aktuellen Wert
- Inline-Success-Notice (kein `admin_notices`-Hook, weil `render_dashboard()`
  laeuft nach dem Hook)

### 3) Stage-Site (zweites Docker-Compose-Setup)

`docker-compose.staging.yml` (NEU):

- Project-Name `dhps-stage` (via `-p` Flag)
- Ports: **8086** (WordPress) + **8087** (phpMyAdmin)
  - Plan-Doc nannte urspruenglich 8084/8085. Auf dem Entwickler-Host
    sind die durch andere Docker-Projekte (opengov-*) belegt -
    **Lead-Decision: Wechsel auf 8086/8087**, Plan-Doc + Checkliste +
    ENTWICKLUNGSUMGEBUNG nachgezogen
- Volumes strikt getrennt: `wp_data_stage`, `db_data_stage`
- DB strikt getrennt: `wordpress_stage` / `wp_user_stage` / `wp_pass_stage_2025`
- Plugin-Mount GETEILT mit Dev-Stack (T16 Trust-Decision): `.` wird in
  beide Stacks gemountet, Code-Aenderungen wirken sofort
- `DHPS_ENV_LABEL = 'STAGE'` als visueller Marker

Start-Befehle:

```bash
docker compose up -d                                              # Dev (8082/8083)
docker compose -p dhps-stage -f docker-compose.staging.yml up -d  # Stage (8086/8087)
```

### 4) Release-Gate-Checkliste

`docs/team-knowledge/07-RELEASE-CHECKLIST.md` (NEU):

4 Pflicht-Sektionen + Schreibrechte-Hinweis:

1. **Pre-Release-Kriterien** (Tag-Format-Vertrag `v0.X.Y-rc.N` mit Punkt,
   3-Stellen-Version-Bump, Smoke der 9 Hauptservices + 4 Sub-Shortcodes)
2. **Stage-Test-Schritte** (Stack hochfahren, `Aktualisieren` klicken,
   `git archive`-Workaround fuer ZIP-Pipeline-Test ohne GitHub-Roundtrip)
3. **Promotion-Kriterien** (24h Beobachtung, NEUER Tag statt Pre-Release-Edit,
   siehe R7)
4. **Rollback-Strategie** (Hotfix-Patch statt Tag-Re-Publish,
   Pre-Release-Cleanup, Notfall-Manuell-Recovery)
5. **Schreibrechte-Hinweis R5** (Windows-Host: `docker exec ... chown` als
   Workaround)

### 5) Doku-Updates

- `docs/architecture/24-DEV-STRECKE-PLAN-v0160.md` (NEU, Discovery-Doc)
- `docs/architecture/09-GITHUB-UPDATER.md` (Channel-Sektion ergaenzt,
  Cache-TTL-Drift M1 aus QA-Report gefixt: 43200 -> 10800, dual-Transient
  dokumentiert)
- `docs/team-knowledge/06-RELEASE-WORKFLOW.md` (Pre-Release-Schritt ergaenzt)
- `docs/team-knowledge/01-ENTWICKLUNGSUMGEBUNG.md` (Stage-Stack-Sektion,
  Port-Tabelle 8086/8087)
- `README.md` (Version 0.15.5 -> 0.16.0)

## QA + Security

### QA-Specialist Ergebnis

**Verdict**: GO

- 0 Critical, 0 Major
- 13/13 Acceptance-Checks PASS
- 3 Minor (M1 + M2 wurden als Pre-Release-Patches direkt erledigt;
  M3 = Live-Smoke wurde durch Lead-Smoke abgedeckt)
- **Schema-Vertrag-Einhaltung STRIKT** (4. Mal in Folge ohne Critical-Drift)

Detail: [docs/project/44-QA-REPORT-v0160.md](44-QA-REPORT-v0160.md) (siehe
QA-Specialist-Return in Lead-Logbuch)

### Security-Audit Ergebnis

**Verdict**: GO

- 0 Critical, 0 High, 0 Medium
- 2 Low (akzeptiert):
  - SEC-LOW-1: Cap-Check vor Nonce-Verify (Best-Practice, kein Defekt)
  - SEC-LOW-2: Stille `return` bei Nonce-Failure (UX-Nuance)
- 5 Info-Findings (alle als Defense-in-Depth-Bestaetigungen)
- **3-Schichten-Sanitize-Kette**: Konstruktor + register_setting + POST-Handler
  - alle drei rufen `DHPS_GitHub_Updater::sanitize_channel()` (SSoT-Pattern)
- **SSRF**: kein Vektor (Owner/Repo hartcodiert in Bootstrap)
- 5 Trust-Decisions T13-T17 dokumentiert (alle SEC-bestaetigt)

## 5 Neue Trust-Decisions (kumulativ T1-T17)

| # | Decision | Begruendung |
|---|----------|-------------|
| T1-T12 | (aus v0.15.x) | siehe Vor-CHANGELOGs |
| T13 | Kein Auto-Downgrade ueber Channels | `version_compare > current` strikt - schuetzt vor Re-Install vulnerabler alter Versionen |
| T14 | Tag-Format-Vertrag mit Punkt-Suffix | `v0.X.Y-rc.N` MIT Punkt - semver-konform fuer `version_compare`, nicht `v0.X.Y-rcN` |
| T15 | Custom-POST statt Settings-API-Form | Dashboard ist kein Settings-Hub, Cache-Flush-Side-Effect lebt ausserhalb von `options.php`. `register_setting` BLEIBT als Defense-in-Depth |
| T16 | Plugin-Mount geteilt Dev+Stage | Code-Aenderungen wirken sofort in beiden Stacks. ZIP-Update-Pipeline wird trotzdem getestet (Stage-Admin klickt `Aktualisieren`, holt echten GitHub-ZIP) |
| T17 | Channel-Wechsel triggert sofortigen Cache-Flush beider Transients | Verhindert Stale-Data nach Channel-Switch |

## Backward Compatibility

**Vollstaendig BC**:

- Bestehende `register_setting`-Optionen unangetastet (16 Stueck, +1 neu)
- Bestehende REST-Endpoints unangetastet (6 Stueck, keine neuen)
- Bestehende React-Komponenten unangetastet (8+ Stueck, keine neuen - siehe TD2)
- Bestehender Updater-Pfad fuer Channel `stable` ist 1:1 das alte Verhalten
- Sites ohne `dhps_update_channel`-Option lesen Default `'stable'` und sehen
  unveraendert die `/releases/latest`-Antwort
- Bestehende Transient-Keys unveraendert (Stable nutzt weiter `dhps_github_release`)
- 13/13 Shortcodes Regression OK (durch Lead-Smoke + QA-Codereview verifiziert)

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/24-DEV-STRECKE-PLAN-v0160.md` | Discovery + Schema-Vertrag |
| `docs/team-knowledge/07-RELEASE-CHECKLIST.md` | Release-Gate-Checkliste (4 Pflicht-Sektionen) |
| `docs/project/43-CHANGELOG-v0160.md` | (dieses Dokument) |
| `docker-compose.staging.yml` | Stage-Stack auf Ports 8086+8087 |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.15.5 -> 0.16.0, Updater-Init mit Channel-Arg, `register_setting`-Block, `dhps_activate` Default `'stable'` |
| `README.md` | Version-Bump |
| `includes/class-dhps-github-updater.php` | `ALLOWED_CHANNELS` const, `$channel` property, `sanitize_channel` public static, Konstruktor-5-Param, `get_release_for_stable_channel`+`get_release_for_beta_channel`, `flush_release_cache` leert beide |
| `includes/class-dhps-admin.php` | `$update_channel_saved` Property, `dispatch_dashboard_action`, `handle_save_update_channel` mit Cap+Nonce+Sanitize+Cache-Flush |
| `admin/views/dashboard.php` | Channel-Switch-Radio-Block + Inline-Success-Notice |
| `docs/architecture/09-GITHUB-UPDATER.md` | Channel-Sektion, Cache-TTL-Fix 43200 -> 10800 (M1) |
| `docs/team-knowledge/06-RELEASE-WORKFLOW.md` | Pre-Release-Schritt + Tag-Format-Anhang |
| `docs/team-knowledge/01-ENTWICKLUNGSUMGEBUNG.md` | Port-Tabelle, Stage-Stack-Sektion, Schreibrechte-Hinweis |

## Specialist-Team-Pattern (Iteration 12)

| Phase | Specialists |
|-------|-------------|
| P1 Discovery | 1 (Architektur + Schema-Vertrag + Risiken + Spec-Aufteilung) |
| P2 Implementation | F1 (Updater + Admin-UI) + F2 (Compose + Checklist) **parallel** + Lead-Direct (Version-Bump + Docs + MEMORY + Port-Decision) |
| P3 Composition | Lead (Stage-Stack hochfahren, F1-Smoke 8/8, Port-Verschiebung 8084/85 -> 8086/87, Doku-Nachzug) |
| P4 QA + SEC | 2 parallel (QA 13/13 GO, SEC 0 Crit GO) |
| P5 Release | Lead (M1+M2-Patches als Lead-Direct, Pre-Release rc.1 -> Stage-Test -> Promote zu Stable) |

**Lehre v0.16.0**:

1. **Port-Annahmen pruefen**: Plan-Doc-Defaults sollten durch Lead beim
   ersten Smoke-Test verifiziert werden. 8084/8085 waren auf diesem Host
   belegt - Wechsel auf 8086/8087 war pragmatisch und gut dokumentiert.
2. **Schema-Vertrag-Vorgehen wirkt 4x in Folge**: 0 Critical-Schema-Drift-Fixes
   (v0.15.3, v0.15.4, v0.15.5, v0.16.0).
3. **1 grosser kombinierter F12-Spec** waere hier moeglich gewesen, aber die
   Trennung Backend/PHP (F1) vs Infrastructure/Docs (F2) war orthogonal und
   parallel sinnvoll - keine Schema-Drift zwischen F1+F2 weil sie nicht
   denselben Schema-Bereich teilten.
4. **Pre-Release-Workflow validiert sich selbst**: v0.16.0 als erstes
   Release schneidet ein `v0.16.0-rc.1`, das auf der neuen Stage getestet
   wird - bevor `v0.16.0` Stable promoted wird. Doku-Vertrag und Workflow
   gehen Hand in Hand.

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.16.1** | **Elementor 4.1.0 Migration** (User-Wunsch - Integration klappt nicht mehr, `related-infos/vs-nfd/elementor-pro-4.1.0.zip` vorhanden). Erster echter Pre-Release-Test des neuen Workflows |
| **v0.17.0** | Einheitliches Datenmodell (User-Wunsch seit Anfang, gross) - spaeter |

## Bilanz v0.16.0

- **Beta-Channel** funktional (Stable + Beta Update-Pfade getrennt)
- **Stage-Stack** lokal (Ports 8086/8087, parallel zu Dev 8082/8083)
- **Release-Gate-Checkliste** verbindlich (4 Pflicht-Sektionen)
- **5 neue Trust-Decisions** T13-T17
- **3-Schichten-Sanitize-Defense** fuer Channel-Option
- **0 Critical-Schema-Drift-Fixes** (4x bestaetigt)
- **QA 13/13 GO**, **SEC 0 Crit/High/Med GO**, 2 Low akzeptiert
- **Workflow validiert sich selbst** (Pre-Release rc.1 -> Stage -> Promote)
