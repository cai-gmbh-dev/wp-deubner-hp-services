# Dev-Strecke + Release-Gate + Beta-Channel Plan v0.16.0 (Discovery)

## Stand: 2026-05-26
## Status: Architektur-Vorschlag (KEINE Code-Aenderungen)
## Zielversion: v0.16.0 - Dev-Strecke, Release-Gate, Beta-Channel
## Folgeversion (geplant): v0.16.1 - Elementor 4.1.0 Update auf der neuen Stage
## Plattform: WP 6.9.4, Elementor 4.0.1, PHP 8.3.30

---

## 1. Ausgangsbasis

### 1.1 Aktueller Update-Flow (Stand v0.15.5)

```
Entwickler-Workstation
  |
  | git tag v0.X.Y && git push --tags
  | gh release create v0.X.Y --notes "..."
  v
GitHub Repository (cai-gmbh-dev/wp-deubner-hp-services)
  |
  | (Cache-TTL 12h)
  v
DHPS_GitHub_Updater::get_latest_release()
  |
  | GET https://api.github.com/repos/cai-gmbh-dev/wp-deubner-hp-services/releases/latest
  |   --> liefert ein einziges Release-Objekt (das jeweils neueste ohne Pre-Release-Filter)
  v
WordPress Update-Mechanismus (Transient `update_plugins`)
  |
  | check_update_uri() / check_for_update()
  | version_compare( $latest_version, $current_version, '>' )
  v
WP-Dashboard "Aktualisierungen verfuegbar"
  |
  | Ein Klick im Live-System
  v
Live-Site fuehrt das Update durch (zipball_url -> Plugin entpacken).
```

### 1.2 Bekannte Probleme

| # | Problem | Auswirkung |
|---|---------|------------|
| P1 | **Kein Staging-System** - Releases gehen direkt von Entwicklung -> Live. | Bugs aus dem Updater-Pipeline-Bereich (z.B. v0.15.3 Live-Preview, v0.15.5 Voller-Atts-Editor) erreichen Endkunden ohne Vorab-Test. |
| P2 | **Kein Pre-Release-Bewusstsein** - `DHPS_GitHub_Updater` ruft pauschal `/releases/latest`. GitHub-API blendet Pre-Releases hier zwar aus, aber: der Updater kann **keine** Pre-Releases sehen, selbst wenn er sollte. | Beta-Tester koennen Release-Kandidaten nicht ausprobieren. |
| P3 | **Kein Approval-Gate** - Sobald ein Release auf GitHub erscheint, sehen es ALLE Installationen innerhalb 12h Cache-TTL. | Keine Moeglichkeit, ein Release nach Stage-Test wieder zurueckzuziehen ohne neues Release zu schneiden. |
| P4 | **Kein Channel-Modell** - Alle Sites sind im gleichen "Stable"-Channel. | Wir koennen kein Soft-Rollout (10% Beta, 90% Stable) etablieren. |
| P5 | **Lokale Dev-Site teilt sich Plugin-Mount mit allen Test-Szenarien** - `docker-compose.yml` mountet `.` direkt nach `wp-content/plugins/wp-deubner-hp-services`. | Kein iso­lierter Test einer veraenderten Version moeglich, ohne den Code zu commit-en. |

### 1.3 Versionsstatus

| Element | Wert |
|---------|------|
| Aktuelle Plugin-Version | `0.15.5` (Konstante `DEUBNER_HP_SERVICES_VERSION`, Plugin-Header, `@version`-PHPDoc) |
| Naechste Version | `0.16.0` (Dev-Strecke + Beta-Channel + Release-Gate) |
| Folgeversion | `0.16.1` (Elementor 4.1.0 Update auf der neuen Stage validiert) |
| `DHPS_GitHub_Updater::$cache_ttl` | 10800 Sekunden (3 Stunden) - laut Code |
| `DHPS_GitHub_Updater::$transient_key` | `dhps_github_release` (string) |

> Hinweis: Die Architektur-Doc `09-GITHUB-UPDATER.md` nennt 12h TTL, der Code hat 3h. Wir uebernehmen den Code-Wert (3h) als Wahrheit.

### 1.4 Bestehende Datenpfade, die wir respektieren muessen

- `class-dhps-github-updater.php` ist die einzige Schnittstelle zu GitHub-Releases. Erweiterung **dort**.
- `dhps_init()` in `Deubner_HP_Services.php` instanziiert den Updater ohne weitere Parameter. Channel muss intern aufgeloest werden.
- `dhps_register_options()` ist seit v0.15.5 der Settings-API-Anker. Eine zweite Option `dhps_update_channel` haengen wir genau dort an.
- `DHPS_Admin_REST` (REST-Foundation) ist bereits vorhanden - falls wir die Channel-Steuerung ueber REST exponieren wollen.
- `admin/js/dhps-admin-react.js` ist die Single-Page-React-App. Channel-Switch koennte dort ein neues Tab/Panel sein.

---

## 2. Ziel-Architektur

### 2.1 Channel-Modell

```
                          [WP-Option: dhps_update_channel]
                          /                              \
                  "stable" (Default)                  "beta"
                          |                              |
                          v                              v
                  /releases/latest                 /releases (Liste, prerelease+stable)
                          |                              |
                          v                              v
                  einzelnes Release-Objekt        Array, sortiert nach published_at desc
                          |                              |
                          v                              v
                  version_compare > current?      Erstes Release nehmen, das
                          |                       version_compare > current erfuellt
                          v                              |
                  Update angezeigt                       v
                                                  Update angezeigt
                                                  (kann Pre-Release sein)
```

**Beta-Channel sieht beide:** Pre-Releases UND Stable - die Reihenfolge ist allein die Versions-Nummer (`version_compare` mit Pre-Release-Suffix-Support, siehe Sektion 3.2). Stable sieht nur das, was GitHub als `/releases/latest` ausliefert (das ist per Definition nicht-Pre-Release, sofern es ein Stable gibt).

### 2.2 GitHub-Pre-Release-Semantik

GitHub markiert Releases mit `prerelease: true|false`. Beim Erstellen via `gh release create`:

```bash
# Stable
gh release create v0.16.0 --title "v0.16.0" --notes "..."

# Pre-Release / Release-Candidate (Beta-Channel sichtbar)
gh release create v0.16.0-rc.1 --prerelease --title "v0.16.0-rc.1" --notes "..."

# Auch erlaubt: -beta.N, -alpha.N, -dev.N (semver-konformes Pre-Release-Suffix)
gh release create v0.16.0-beta.1 --prerelease ...
```

**Tag-Format-Konvention v0.16.0+** (siehe Sektion 3.5 Schema):

```
Stable:      v{major}.{minor}.{patch}              z.B. v0.16.0
Beta:        v{major}.{minor}.{patch}-rc.{n}       z.B. v0.16.0-rc.1, v0.16.0-rc.2
             v{major}.{minor}.{patch}-beta.{n}     z.B. v0.16.0-beta.1
```

`version_compare()` von PHP behandelt das korrekt:
- `version_compare('0.16.0-rc.1', '0.16.0', '<')` -> `true` (rc kleiner als final)
- `version_compare('0.16.0-rc.2', '0.16.0-rc.1', '>')` -> `true`
- `version_compare('0.15.5', '0.16.0-rc.1', '<')` -> `true`

### 2.3 Staging-Docker-Compose

Neue Datei `docker-compose.staging.yml` neben der bestehenden `docker-compose.yml`. **Beide laufen parallel** auf demselben Host, separate Container-Namen, separate Volumes, separate Ports.

```
[localhost]
 +- Port 8082  --> wp-deubner-hp-services-wordpress-1  (Dev,   Volume wp_data,        DB wordpress)
 +- Port 8083  --> wp-deubner-hp-services-phpmyadmin-1 (Dev-PMA)
 +- Port 8086  --> wp-deubner-hp-services-wp-stage-1   (Stage, Volume wp_data_stage,  DB wordpress_stage)
 +- Port 8087  --> wp-deubner-hp-services-pma-stage-1  (Stage-PMA)
```

Project-Name-Trennung via Compose-Flag:

```bash
docker compose -f docker-compose.yml up -d                       # Dev (default project name aus dir)
docker compose -p dhps-stage -f docker-compose.staging.yml up -d # Stage
```

Dadurch sind die Container-Namen wirklich getrennt (`dhps-stage_wordpress_1` vs `wp-deubner-hp-services_wordpress_1`), kein Volume-Konflikt, kein DB-Konflikt.

**Plugin-Mount-Empfehlung**: Beide Compose-Files mounten dasselbe Source-Directory `.` nach `wp-content/plugins/wp-deubner-hp-services`. Begruendung: Eine Code-Aenderung wirkt sofort in beiden Stages und der Workflow `Code editieren -> in Dev testen -> in Stage testen` braucht keine zweite Checkout-Kopie. **Trade-off:** Eine Stage testet damit niemals einen "echten" Release-ZIP. Das ist akzeptabel, weil der Test der ZIP-Pipeline ueber die WP-Update-UI passiert (sobald wir das Pre-Release auf GitHub geschnitten haben, klickt der Stage-Admin "Aktualisieren" und holt den echten ZIP). Siehe Sektion 5 Acceptance T7.

### 2.4 Release-Gate-Workflow

```
1. Developer schneidet ein Pre-Release
   git tag v0.16.0-rc.1
   gh release create v0.16.0-rc.1 --prerelease

2. Stage-Site (Channel=beta) sieht Update innerhalb der Cache-TTL (max 3h).
   Manuell zu beschleunigen: WP -> Aktualisierungen -> "Erneut pruefen".

3. Stage-Admin testet das Update:
   - "Aktualisieren" klicken in der Stage-UI
   - Smoke-Tests laut RELEASE-CHECKLIST durchgehen
   - QA-Befund dokumentieren

4. Bei OK: Promotion zu Stable
   gh release edit v0.16.0-rc.1 --tag v0.16.0 --latest=true --prerelease=false
   ODER
   gh release create v0.16.0 --notes "<copy from rc.1>"
   (Empfehlung: NEUEN Release-Tag schneiden, Pre-Release-Tag liegen lassen.
    Dadurch bleibt die Beta-Historie sichtbar.)

5. Bei NICHT-OK: kein Promotion. Pre-Release loeschen ODER neuen Pre-Release-Tag.
   gh release delete v0.16.0-rc.1
   git tag -d v0.16.0-rc.1
   git push --delete origin v0.16.0-rc.1
   (oder fortfahren mit v0.16.0-rc.2)

6. Live-Sites (Channel=stable) sehen nur den finalen v0.16.0-Tag.
```

---

## 3. Schema-Vertrag (verbindlich fuer alle Implementation-Specialists)

> **WARUM verbindlich**: v0.15.3 / v0.15.4 / v0.15.5 haben 3x in Folge **0 Critical-Schema-Drift-Fixes** erreicht, weil das Schema VOR der Implementierung dokumentiert wurde und jeder Spec es als Pflichtbestandteil bekam. Dieses Vorgehen wenden wir hier konsequent weiter an.

### 3.1 WP-Option `dhps_update_channel`

| Property | Wert |
|----------|------|
| **Option-Key** | `dhps_update_channel` |
| **Erlaubte Werte** | `stable`, `beta` (case-sensitive lowercase Strings) |
| **Default** | `stable` |
| **Storage-Typ** | `string` (1 Zeile in `wp_options`) |
| **Autoload** | `yes` (Default - wir lesen den Channel bei jedem Update-Check) |
| **Sanitize-Callback** | `sanitize_key( $value )` -> Whitelist-Check -> Fallback `stable` |
| **Validation-Whitelist** | `array( 'stable', 'beta' )` als public const im Updater oder Helper |
| **register_setting** | innerhalb `dhps_register_options()` (siehe v0.15.5 Pattern) mit `sanitize_callback`, `default => 'stable'`, `show_in_rest => false` |
| **Migration** | nicht noetig. Sites ohne Option lesen `get_option('dhps_update_channel', 'stable')`. |

**Beispiel-Sanitize-Callback** (Pflicht-Snippet fuer F1-Spec):

```php
public const ALLOWED_CHANNELS = array( 'stable', 'beta' );

public static function sanitize_channel( $value ): string {
    $value = sanitize_key( (string) $value );
    return in_array( $value, self::ALLOWED_CHANNELS, true ) ? $value : 'stable';
}
```

### 3.2 GitHub-Updater-Channel-Logik

| Wenn Channel = ... | dann ... |
|---------------------|----------|
| `stable` | `GET /repos/{owner}/{repo}/releases/latest` (ein Objekt, ohne Pre-Release-Filter) - bestehender Pfad. Cache-Key bleibt `dhps_github_release`. |
| `beta` | `GET /repos/{owner}/{repo}/releases?per_page=30` (Liste). Iteriere bis erstes Release, dessen `tag_name` per `version_compare` > current. Cache-Key wird auf `dhps_github_release_beta` getrennt. |

**Cache-Trennung Pflicht**: Wenn beide Channels denselben Cache-Key teilen, bekommt eine Beta-Site nach einem `flush_release_cache()` auf einer Stable-Site falsche Daten. Deshalb 2 getrennte Transients.

**Pre-Release-Tag-Erkennung in der Beta-Schleife** (Pflicht-Snippet fuer F1-Spec):

```php
$releases = json_decode( wp_remote_retrieve_body( $response ), true );
if ( ! is_array( $releases ) ) {
    return null;
}

foreach ( $releases as $release ) {
    // GitHub-Felder: 'tag_name' (string), 'prerelease' (bool), 'draft' (bool), 'zipball_url', 'html_url', 'published_at'.
    if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
        continue;
    }
    if ( ! empty( $release['draft'] ) ) {
        continue; // Drafts sind nie sichtbar.
    }

    $version = $this->normalize_version( $release['tag_name'] );
    if ( version_compare( $version, $this->current_version, '>' ) ) {
        return $release;
    }
}

return null;
```

**Auto-Downgrade-Verhalten** (Pflicht-Trust-Decision):

| Szenario | Verhalten |
|----------|-----------|
| Site ist auf `v0.16.0-rc.3`, Channel=beta, kein neueres Release | Kein Update angeboten. KEIN Auto-Downgrade auf `v0.16.0` (das waere kleiner per `version_compare`). |
| Site ist auf `v0.16.0-rc.3`, User schaltet Channel auf `stable` | Solange kein Stable >= 0.16.0-rc.3 existiert: kein Update. Sobald `v0.16.0` Stable erscheint, sieht die Site das Update (weil `version_compare('0.16.0', '0.16.0-rc.3', '>') === true`). |
| Site ist auf `v0.16.0` Stable, User schaltet Channel auf `beta` | Channel=beta zeigt nur Release **groesser** als current. Wenn das naechste Beta `v0.17.0-rc.1` ist, sieht die Site es. **Kein Re-Install des aktuellen Stable als Beta.** |

**Trust-Decision T13** (v0.16.0): Kein Auto-Downgrade ueber Channels - der Updater respektiert die `version_compare > current`-Regel strikt. Wer von Beta zurueck auf Stable wechselt und auf einer Beta-Version stuck-t, muss manuell downgraden (FTP/CLI).

### 3.3 GitHub-API-Schema-Vertrag

Felder, die der Updater aus der GitHub-Response liest (Pflicht-Liste fuer F1-Spec):

| Feld | Typ | Quelle | Defensiv-Lesung |
|------|-----|--------|-----------------|
| `tag_name` | string | required | `empty( $release['tag_name'] )` -> skip |
| `prerelease` | bool | `false` default | `! empty( $release['prerelease'] )` |
| `draft` | bool | `false` default | `! empty( $release['draft'] )` -> immer skip |
| `zipball_url` | string | required | `empty( $release['zipball_url'] )` -> skip (Download nicht moeglich) |
| `html_url` | string | optional | `$release['html_url'] ?? ''` |
| `published_at` | string ISO-8601 | optional | `$release['published_at'] ?? ''` |
| `body` | string Markdown | optional | `$release['body'] ?? ''` |

### 3.4 Release-Gate-Checkliste (Datei-Vertrag)

Neue Datei: `docs/team-knowledge/07-RELEASE-CHECKLIST.md`

**Pflicht-Sektionen**:

1. **Pre-Release-Kriterien** - Was muss erfuellt sein, bevor man `gh release create --prerelease` ausfuehrt:
   - Lokal Plugin-Aktivierung ohne PHP-Fatal in Dev-Stack getestet
   - Alle relevanten Smoke-Shortcodes rendern (Test-Liste aus Service-Tabelle)
   - Version-Bump in 3 Stellen vollzogen: Plugin-Header / @version-PHPDoc / `DEUBNER_HP_SERVICES_VERSION`
   - README.md Version-Zeile aktualisiert
   - Aenderungs-Notes vorbereitet (Markdown fuer `--notes`)
2. **Stage-Test-Schritte** - Was der Stage-Admin tut, nachdem das Pre-Release auf GitHub sichtbar ist:
   - WP-Admin -> Aktualisierungen -> "Erneut pruefen" (oder warten max 3h Cache-TTL)
   - Update-Banner muss erscheinen mit korrekter Version (`v0.16.0-rc.1`-Format zeigt sich als `0.16.0-rc.1`)
   - "Aktualisieren" -> Plugin entpacken muss ohne `fix_directory_name`-Fehler durchlaufen
   - Admin-Dashboard oeffnen, dann Live-Preview-Endpunkt aus v0.15.3 ausprobieren
   - Alle 9 Service-Shortcodes auf einer Testseite anzeigen
   - DB-State pruefen: `dhps_update_channel = beta`, alle bisherigen `dhps_*`-Options unveraendert
3. **Promotion-Kriterien** - Was erfuellt sein muss, damit ein Pre-Release zu Stable promoted wird:
   - Stage-Smoke-Tests gruen
   - Mindestens 24h Beobachtungs-Zeit auf Stage-Site ohne Folgefehler
   - Keine offenen Critical/High-Issues im aktuellen Sprint
   - Aktion: `gh release create v0.16.0 --notes "<kopiert>"` (neuer Tag, nicht Pre-Release-Tag-Edit)
4. **Rollback-Strategie** - Wenn Stable in der Praxis bricht:
   - Vorherigen Tag `v0.15.5` als neuen Release re-publizieren? **Nein** - das ist Down-Versioning und der Updater filtert es weg (version_compare). Stattdessen Hotfix-Patch `v0.16.1` mit Revert.
   - Notfall-Doku: WP-Admin -> Plugins -> Deaktivieren -> FTP -> Plugin-Ordner durch alte Version ersetzen.

### 3.5 Staging-Setup-Vertrag

**Port-Allocation** (Pflicht):

| Service | Dev (bestehend) | Stage (neu) |
|---------|-----------------|-------------|
| WordPress | 8082 | 8086 |
| phpMyAdmin | 8083 | 8087 |
| MariaDB intern | 3306 | 3306 (innerhalb des stage-Netzwerks - kein Host-Port-Mapping noetig) |

**Volume-Naming** (Pflicht):

| Was | Dev | Stage |
|-----|-----|-------|
| WP-Core-Files | `wp_data` | `wp_data_stage` |
| MariaDB-Files | `db_data` | `db_data_stage` |
| Plugin-Mount | `.:/var/www/html/wp-content/plugins/wp-deubner-hp-services` | `.:/var/www/html/wp-content/plugins/wp-deubner-hp-services` (gleiches Source-Dir!) |
| Theme-Mount (optional) | `./docker/deubner-theme:/var/www/html/wp-content/themes/deubner-demo` | `./docker/deubner-theme:/var/www/html/wp-content/themes/deubner-demo` (gleich) |

**DB-Naming** (Pflicht):

| Wert | Dev | Stage |
|------|-----|-------|
| `MYSQL_DATABASE` | `wordpress` | `wordpress_stage` |
| `MYSQL_USER` | `wp_user` | `wp_user_stage` |
| `MYSQL_PASSWORD` | `wp_pass_2025` | `wp_pass_stage_2025` |
| `MYSQL_ROOT_PASSWORD` | `root_pass_2025` | `root_pass_stage_2025` |

**Plugin-Mount-Empfehlung**: **EIN gemeinsames Source-Directory** (`.`) wird in beide Stacks gemountet.

Begruendung:
- Workflow `editieren -> in Dev testen -> in Stage testen` benoetigt keine zweite Working-Copy.
- Live-Preview-Endpoints aus v0.15.3 funktionieren ohne Sync-Schritt.
- Die *Update-Pipeline* (ZIP-Download von GitHub Release, fix_directory_name, Entpacken) wird **getrennt** getestet: Stage-Admin klickt nach einem Pre-Release auf "Aktualisieren" - dann wird der echte ZIP geholt und ueber den Plugin-Mount geschrieben. **WICHTIG**: Beim ersten "Aktualisieren" auf Stage muss der WordPress-Container Schreibrechte auf den Plugin-Mount haben. Lokal ist das ueblicherweise gegeben (Docker Desktop), eine kurze Notiz dazu in RELEASE-CHECKLIST.

**Alternative dokumentieren (nicht empfohlen)**: Zwei separate Mounts (`./.:` Dev, `./build/wp-deubner-hp-services-stage:` Stage) mit manueller Synchronisation. Vorteil waere echte Code-Isolation. Trade-off ist Sync-Aufwand, den wir nicht stemmen.

### 3.6 Admin-UI-Vertrag (Channel-Switcher)

Das Channel-Switching-UI lebt im bestehenden Admin-Dashboard (siehe v0.15.0 Architektur). **Zwei Optionen**, jeweils mit Pflicht-Schema:

**Option A** (empfohlen, kleiner Aufwand): Settings-API-Sektion in einer bestehenden Settings-Page

- Anker: `dhps_register_options()` registriert die Setting
- Render: Neuer Block in `dashboard.php` ODER neue Mini-Settings-Page unter Top-Level-Menue "Deubner Verlag"
- Form: Klassischer Radio-Button-Block "Update-Channel: ( ) Stabil  ( ) Beta"
- Nonce: `dhps_save_settings` (bestehende Konstante `DEUBNER_HP_SERVICES_NONCE_ACTION`)
- Cap: `manage_options`

**Option B** (mehr Aufwand, konsistenter mit v0.15.x): React-Panel im Dashboard-Tab "Update-Channel"

- REST-Endpoint `GET /dhps/v1/update-channel` (liefert aktuellen Channel + verfuegbare Werte)
- REST-Endpoint `POST /dhps/v1/update-channel` mit Body `{ "channel": "stable"|"beta" }`
- Permission: `manage_options`, Nonce via `apiFetch.createNonceMiddleware`
- Localize: `dhpsAdminConfig.updateChannel = 'stable'|'beta'`
- React: `RadioControl` aus `wp.components`

**Empfehlung**: **Option A** in v0.16.0 (schnell, BC-arm, Updater-Logik ist der Hauptfokus). Option B als Tech-Debt-Ticket fuer v0.16.x falls gewuenscht.

---

## 4. Spec-Aufteilung (Implementation-Phase)

**Empfehlung: 2 Specialists + Lead-Direct**.

Begruendung: F1 (Updater + Admin-UI) ist eng gekoppelter PHP-Code im Backend. F2 (Docker-Compose + RELEASE-CHECKLIST) ist Infrastructure/Docs. Beide sind unabhaengig, koennen parallel laufen. Lead-Direct uebernimmt die Easy-Wins.

### 4.1 Spec F1: GitHub-Updater Channel-Support + Admin-UI

**Scope**: Backend-PHP + Admin-Formular.

**Dateien (anfassen oder neu)**:

- `includes/class-dhps-github-updater.php` (ERWEITERN): neue private Property `$channel`, neuer Konstruktor-Parameter, neue Methode `get_releases_for_beta_channel()`, Anpassung von `get_latest_release()` als Channel-Switch, Cache-Key-Trennung.
- `Deubner_HP_Services.php` (ANPASSEN): Updater-Konstruktor-Aufruf bekommt 5. Argument `$channel` aus `get_option('dhps_update_channel', 'stable')`. `dhps_register_options()` bekommt Block fuer die neue Option.
- `admin/views/dashboard.php` ODER neue `admin/views/update-channel-section.php` (NEU/ANPASSEN): Radio-Block fuer Channel-Switch.
- `includes/class-dhps-admin-page-handler.php` (PRUEFEN): Handhabt der bestehende POST-Handler den neuen Option-Key automatisch? Falls nein, additiv erweitern.

**LOC-Schaetzung**: ca. 150-200 LOC PHP + 40 LOC View.

**Schema-Pflicht-Bestandteile** (siehe Sektion 3):
- Tabelle 3.1 (Option-Vertrag)
- Tabelle 3.2 (Channel-Logik)
- Tabelle 3.3 (GitHub-API-Felder)
- Sektion 3.6 Option A (Admin-UI)

**Akzeptanz F1**:
- `dhps_update_channel` Option existiert nach Plugin-Aktivierung (Default `stable`).
- Updater liest Channel beim Konstruktor.
- Bei Channel=beta wird `releases?per_page=30` abgefragt, Cache-Key `dhps_github_release_beta`.
- Pre-Release-Tag `v0.16.0-rc.1` wird auf einer Test-Site mit Channel=beta korrekt erkannt.
- Stable-Channel sieht das Pre-Release NICHT.
- Channel-Switch im Admin-UI funktioniert, Sanitize-Whitelist greift bei Mutwillen.

### 4.2 Spec F2: docker-compose.staging.yml + RELEASE-CHECKLIST.md

**Scope**: Infrastruktur + Dokumentation.

**Dateien (neu)**:

- `docker-compose.staging.yml` (NEU): siehe Snippet in Sektion 7.
- `docs/team-knowledge/07-RELEASE-CHECKLIST.md` (NEU): siehe Sektion 3.4.

**Dateien (anpassen, optional)**:

- `docs/team-knowledge/06-RELEASE-WORKFLOW.md` (ANPASSEN): neuen Pre-Release-Schritt einbauen.
- `docs/team-knowledge/01-ENTWICKLUNGSUMGEBUNG.md` (ANPASSEN): Stage-Compose-Erwaehnung + Port-Liste.
- `README.md` (OPTIONAL): Kurz auf Beta-Channel hinweisen.

**Schema-Pflicht-Bestandteile** (siehe Sektion 3):
- Sektion 3.5 (Staging-Setup-Vertrag mit allen Port/Volume/DB-Werten)
- Sektion 3.4 (Release-Checkliste-Datei-Vertrag mit allen 4 Sektionen)
- Sektion 2.4 (Release-Gate-Workflow als Diagram-Referenz)

**Akzeptanz F2**:
- `docker compose -p dhps-stage -f docker-compose.staging.yml up -d` startet erfolgreich.
- Stage erreichbar unter `http://localhost:8086`.
- Stage-PMA erreichbar unter `http://localhost:8087`.
- Dev und Stage laufen parallel ohne Port-/Volume-Konflikte.
- RELEASE-CHECKLIST.md hat alle 4 Pflicht-Sektionen, ist verstaendlich fuer einen externen QA-Helfer.

### 4.3 Lead-Direct (kein Specialist, vom Lead direkt erledigt)

| Task | Aufwand | Datei |
|------|---------|-------|
| Version-Bump `0.15.5` -> `0.16.0` an 3 Stellen | trivial | `Deubner_HP_Services.php` |
| README.md Version-Zeile + kurze Beta-Channel-Erwaehnung | trivial | `README.md` |
| MEMORY.md / Project-Memory ergaenzen (v0.16.0 Eintrag) | trivial | nicht im Repo |
| Doc-Update `09-GITHUB-UPDATER.md` (Channel-Sektion ergaenzen) | klein | `docs/architecture/09-GITHUB-UPDATER.md` |
| CHANGELOG-Eintrag fuer v0.16.0 | klein | wahrscheinlich `docs/project/...-v0160.md` (neu) |
| Verifikation Acceptance-Tests T1-T8 (siehe Sektion 5) | mittel | manuell + Browser |
| Discord-/Slack-Notification an Beta-Tester nach Pre-Release | trivial | extern |

---

## 5. Acceptance-Kriterien (QA-Pflicht)

Konkrete Tests, die VOR dem Stable-Release v0.16.0 alle gruen sein muessen:

| # | Test | Erwartet |
|---|------|----------|
| T1 | Option-Existenz: `wp option get dhps_update_channel` nach Plugin-Aktivierung | Liefert `stable` (Default). |
| T2 | Channel-Whitelist: `wp option update dhps_update_channel "garbage"` | Sanitize wirft auf `stable` zurueck. Wert in DB ist `stable`. |
| T3 | Beta-Channel-API-Call: Site auf `0.15.5`, Channel=beta, ein Pre-Release `v0.16.0-rc.1` existiert | Updater zeigt Update auf `0.16.0-rc.1`. |
| T4 | Stable-Channel-Blindspot: Site auf `0.15.5`, Channel=stable, NUR `v0.16.0-rc.1` Pre-Release existiert, kein Stable | Updater zeigt KEIN Update. |
| T5 | Channel-Wechsel-Reaktivitaet: Site auf `0.15.5`, Channel=stable, kein Update; User schaltet Channel=beta um, Cache-Flush ueber "Erneut pruefen" | Update auf `0.16.0-rc.1` erscheint. |
| T6 | Admin-UI-Sichtbarkeit: Channel-Switcher erscheint im Dashboard | Radio-Buttons "Stabil"/"Beta" sichtbar, aktueller Wert vorausgewaehlt. |
| T7 | Stage-Update-Pipeline: Pre-Release auf GitHub geschnitten, Stage-Site mit Channel=beta -> "Aktualisieren" klicken | Plugin-Ordner wird mit ZIP-Inhalt ueberschrieben, `fix_directory_name` greift, kein Fehler, Plugin bleibt aktiv. |
| T8 | Dev+Stage-Parallelitaet: `docker compose up -d` (Dev) und `docker compose -p dhps-stage -f docker-compose.staging.yml up -d` (Stage) | Beide Container-Stacks laufen, beide WP-Instanzen erreichbar (8082+8086), keine MariaDB-Port-Kollision. |
| T9 | Code-Change-Wirkung: Aenderung in `Deubner_HP_Services.php` (z.B. neue Konstante) | wirkt sofort in beiden Stacks (gemeinsamer Plugin-Mount). |
| T10 | Auto-Downgrade-Schutz: Stage auf `0.16.0-rc.3`, User schaltet Channel=stable um, kein neueres Stable existiert | KEIN Update angeboten (T13 Trust-Decision). |
| T11 | RELEASE-CHECKLIST-Vollstaendigkeit: Datei `docs/team-knowledge/07-RELEASE-CHECKLIST.md` | hat 4 Sektionen Pre-Release / Stage-Test / Promotion / Rollback. |
| T12 | Cache-Trennung: Channel=stable cached `dhps_github_release`, Channel=beta cached `dhps_github_release_beta`. `wp transient delete dhps_github_release` loescht NUR den Stable-Cache. | Beide Transients existieren unabhaengig. |
| T13 | Doc-Konsistenz: `09-GITHUB-UPDATER.md` und `06-RELEASE-WORKFLOW.md` erwaehnen den Channel | Ja, mindestens 1 Absatz je Doc. |

---

## 6. Tech-Debt / Risiken

### 6.1 Risiken (was kann schiefgehen)

| # | Risiko | Wahrscheinlichkeit | Auswirkung | Mitigation |
|---|--------|--------------------|------------|------------|
| R1 | `version_compare()` interpretiert Pre-Release-Suffixe nicht so wie semver erwartet (z.B. `0.16.0-rc1` ohne Punkt) | mittel | Beta sieht falsche Reihenfolge | Tag-Format-Vertrag (`-rc.N`, `-beta.N` MIT Punkt) in RELEASE-CHECKLIST hart festschreiben + Sanity-Test im F1-Spec. |
| R2 | GitHub-API-Rate-Limit (60/h unauthenticated) wird durch `releases?per_page=30` schneller geschluckt | gering | Cache-Misses bei viel Klick-Aktivitaet | Cache-TTL bleibt 3h. Pro Channel separater Cache. Optional Personal-Access-Token via Constant-Hook fuer Power-User. |
| R3 | Stage-Site teilt Plugin-Code mit Dev-Site - "Test mit echtem ZIP" passiert erst nach Pre-Release auf GitHub | mittel | Lokal kann ZIP-Pipeline nicht ohne GitHub-Roundtrip geprueft werden | RELEASE-CHECKLIST-Hinweis: lokal ein ZIP via `git archive HEAD -o test.zip` bauen und manuell via Plugin-Hochladen-UI auf Stage installieren - das simuliert die Pipeline ohne echtes Release. |
| R4 | User wechselt Channel im laufenden Update-Vorgang | gering | Zustand inkonsistent | Cache-Flush bei Channel-Wechsel triggern. Lead-Direct sollte das in F1 mit-spec-en (siehe Sektion 7 Snippet). |
| R5 | Schreibrechte auf Plugin-Mount in Docker-Container blockieren das WP-Update | mittel auf Windows-Hosts | "Aktualisieren" auf Stage schlaegt fehl | Doku in `01-ENTWICKLUNGSUMGEBUNG.md` + Lead-Hinweis. Workaround: `docker exec` als `www-data` einen `chown` triggern. |
| R6 | `register_setting` der neuen Option in `dhps_register_options()` kollidiert mit dem POST-Handler in `class-dhps-admin-page-handler.php`, der ohne Settings-API arbeitet | gering | Doppel-Save-Pfad | F1-Spec muss explizit klaeren: Settings-API ODER Custom-POST. Empfehlung: Custom-POST-Block analog zu Demo-Toggle, kein Settings-API-Form. |
| R7 | Promotion-via-Tag-Edit (`gh release edit --tag ... --prerelease=false`) erzeugt zwar Stable-Marker, aber zipball_url bleibt der Pre-Release-ZIP | gering | Stable laedt Pre-Release-Bytes herunter | RELEASE-CHECKLIST schreibt vor: NEUEN Release-Tag schneiden, nicht editieren. |
| R8 | Beta-Channel-Sites uebersehen einen Hotfix, weil der noch im Stable-Channel landet, bevor er ein Pre-Release durchlaufen hat | mittel | Beta-Sites bleiben auf alter Beta stehen | Trust-Decision: Hotfix-Releases werden parallel als Pre-Release UND Stable geschnitten (z.B. `v0.16.1` + `v0.16.1-rc.1` oder `v0.17.0-rc.1`). Dokumentieren in RELEASE-CHECKLIST. |

### 6.2 Tech-Debt fuer v0.16.x+ (bewusst verschoben)

| # | Verschobenes Feature | Begruendung |
|---|----------------------|-------------|
| TD1 | Channel-Switcher als React-Panel (Option B in Sektion 3.6) | Option A reicht in v0.16.0. React-Migration wenn Bedarf entsteht. |
| TD2 | Per-Channel-Update-History (welche Site sah welches Release wann?) | Erfordert Custom-Tabelle oder Post-Type. Nicht in v0.16.0. |
| TD3 | Mehrere Beta-Tester-Sites parallel betreiben (z.B. Channel=alpha) | 3+ Channels brauchen UI-Erweiterung. Falls Bedarf entsteht, in v0.16.x. |
| TD4 | Auto-Rollback bei PHP-Fatal nach Update | WordPress hat das nativ (Recovery-Mode). Kein Plugin-Custom-Code noetig. |
| TD5 | GitHub Personal-Access-Token-Eingabe im Admin-UI (5000 API-Calls/h statt 60) | Erst wenn Rate-Limit-Probleme auftreten. |
| TD6 | Stage-Site bekommt eigenes Branding (z.B. roter Admin-Bar-Hintergrund) als Sicht-Schutz vor Vertauschen mit Dev | Easy-Win, kann in v0.16.x oder Lead-Direct in v0.16.0 falls Zeit. |
| TD7 | Update-Channel-Switch loest sofortigen Cache-Flush + Update-Check aus | F1-Spec macht das (siehe R4). Kein TD. |

---

## 7. Spec-Briefing-Material (fuer Implementation-Specialists)

### 7.1 Exakte Datei-Pfade

```
includes/class-dhps-github-updater.php                  (ANPASSEN, F1)
Deubner_HP_Services.php                                 (ANPASSEN, F1 + Lead)
admin/views/dashboard.php                               (ANPASSEN, F1, Mount des Channel-Blocks)
includes/class-dhps-admin-page-handler.php              (PRUEFEN, F1)
docker-compose.staging.yml                              (NEU, F2)
docs/team-knowledge/07-RELEASE-CHECKLIST.md             (NEU, F2)
docs/team-knowledge/06-RELEASE-WORKFLOW.md              (ANPASSEN, F2 oder Lead)
docs/team-knowledge/01-ENTWICKLUNGSUMGEBUNG.md          (ANPASSEN, F2 oder Lead)
docs/architecture/09-GITHUB-UPDATER.md                  (ANPASSEN, Lead)
README.md                                               (ANPASSEN, Lead)
```

### 7.2 Exakte Konstanten / Klassen-Member

| Element | Wert / Pfad |
|---------|-------------|
| Option-Key | `dhps_update_channel` |
| Default-Value | `'stable'` |
| Allowed-Channels-Konstante | `DHPS_GitHub_Updater::ALLOWED_CHANNELS = array( 'stable', 'beta' )` |
| Sanitize-Methode | `DHPS_GitHub_Updater::sanitize_channel( $value ): string` (public static) |
| Cache-Key Stable | `dhps_github_release` (bestehend) |
| Cache-Key Beta | `dhps_github_release_beta` (neu) |
| Cache-TTL | 10800 Sekunden (3h, bestehend) |
| Error-Cache-TTL | 600 Sekunden (10 Min, bestehend) |
| GitHub-API Stable | `https://api.github.com/repos/{owner}/{repo}/releases/latest` |
| GitHub-API Beta | `https://api.github.com/repos/{owner}/{repo}/releases?per_page=30` |
| Nonce-Action (POST-Save) | `DEUBNER_HP_SERVICES_NONCE_ACTION` (= `'dhps_save_settings'`, bestehend) |

### 7.3 Exakte Funktionssignaturen

**Konstruktor erweitern**:

```php
public function __construct(
    string $owner,
    string $repo,
    string $plugin_basename,
    string $current_version,
    string $channel = 'stable'   // NEU
) {
    $this->channel = self::sanitize_channel( $channel );
    // ...
}
```

**Instanziierung in `dhps_init()`**:

```php
$updater = new DHPS_GitHub_Updater(
    'cai-gmbh-dev',
    'wp-deubner-hp-services',
    DEUBNER_HP_SERVICES_BASENAME,
    DEUBNER_HP_SERVICES_VERSION,
    get_option( 'dhps_update_channel', 'stable' )   // NEU
);
$updater->init();
```

**get_latest_release als Channel-Switch** (Skizze, kein Pflicht-Wortlaut):

```php
private function get_latest_release(): ?array {
    if ( null !== $this->github_data ) {
        return $this->github_data;
    }

    if ( 'beta' === $this->channel ) {
        $this->github_data = $this->get_release_for_beta_channel();
    } else {
        $this->github_data = $this->get_release_for_stable_channel();
    }

    return $this->github_data;
}

private function get_release_for_stable_channel(): ?array {
    // bisheriger Code, unveraendert. Cache-Key dhps_github_release.
}

private function get_release_for_beta_channel(): ?array {
    $cache_key = 'dhps_github_release_beta';
    $cached = get_transient( $cache_key );
    if ( false !== $cached && is_array( $cached ) ) {
        return ! empty( $cached['tag_name'] ) ? $cached : null;
    }

    $url = sprintf(
        'https://api.github.com/repos/%s/%s/releases?per_page=30',
        $this->owner,
        $this->repo
    );

    $response = wp_remote_get( $url, array(
        'timeout'    => 10,
        'headers'    => array( 'Accept' => 'application/vnd.github.v3+json' ),
        'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
    ) );

    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        set_transient( $cache_key, array(), 600 );
        return null;
    }

    $releases = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $releases ) ) {
        set_transient( $cache_key, array(), 600 );
        return null;
    }

    foreach ( $releases as $release ) {
        if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
            continue;
        }
        if ( ! empty( $release['draft'] ) ) {
            continue;
        }
        $version = $this->normalize_version( $release['tag_name'] );
        if ( version_compare( $version, $this->current_version, '>' ) ) {
            set_transient( $cache_key, $release, $this->cache_ttl );
            return $release;
        }
    }

    // Kein neueres Release gefunden - leeren Cache setzen, damit wir nicht spamen.
    set_transient( $cache_key, array(), $this->cache_ttl );
    return null;
}
```

**flush_release_cache erweitern**:

```php
public function flush_release_cache(): void {
    delete_transient( 'dhps_github_release' );
    delete_transient( 'dhps_github_release_beta' );
    $this->github_data = null;
}
```

**Option-Registrierung in `dhps_register_options()`**:

```php
register_setting(
    'dhps_settings_group',
    'dhps_update_channel',
    array(
        'type'              => 'string',
        'sanitize_callback' => array( 'DHPS_GitHub_Updater', 'sanitize_channel' ),
        'default'           => 'stable',
        'show_in_rest'      => false,
    )
);
```

### 7.4 docker-compose.staging.yml Skelett

```yaml
# Docker-Compose fuer die STAGE-Site (parallel zur Dev).
# Starte mit: docker compose -p dhps-stage -f docker-compose.staging.yml up -d
# WordPress Stage:   http://localhost:8086
# phpMyAdmin Stage:  http://localhost:8087

services:
  db:
    image: mariadb:10.11
    restart: unless-stopped
    volumes:
      - db_data_stage:/var/lib/mysql
    environment:
      MYSQL_ROOT_PASSWORD: root_pass_stage_2025
      MYSQL_DATABASE: wordpress_stage
      MYSQL_USER: wp_user_stage
      MYSQL_PASSWORD: wp_pass_stage_2025
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-proot_pass_stage_2025"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  wordpress:
    image: wordpress:latest
    restart: unless-stopped
    ports:
      - "8086:80"
    volumes:
      - wp_data_stage:/var/www/html
      - .:/var/www/html/wp-content/plugins/wp-deubner-hp-services
      - ./docker/deubner-theme:/var/www/html/wp-content/themes/deubner-demo
    environment:
      WORDPRESS_DB_HOST: db:3306
      WORDPRESS_DB_NAME: wordpress_stage
      WORDPRESS_DB_USER: wp_user_stage
      WORDPRESS_DB_PASSWORD: wp_pass_stage_2025
      WORDPRESS_DEBUG: "1"
      WORDPRESS_CONFIG_EXTRA: |
        define('WP_DEBUG', true);
        define('WP_DEBUG_LOG', true);
        define('WP_DEBUG_DISPLAY', true);
        // Optional visueller Marker zur Unterscheidung von Dev:
        define('DHPS_ENV_LABEL', 'STAGE');
    depends_on:
      db:
        condition: service_healthy

  phpmyadmin:
    image: phpmyadmin:latest
    restart: unless-stopped
    ports:
      - "8087:80"
    environment:
      PMA_HOST: db
      PMA_PORT: 3306
      MYSQL_ROOT_PASSWORD: root_pass_stage_2025
    depends_on:
      - db

volumes:
  wp_data_stage:
  db_data_stage:
```

### 7.5 Beispiel Channel-Switch-Block in dashboard.php

```php
<!-- Update-Channel-Block (v0.16.0) -->
<div class="dhps-update-channel-section">
    <h2><?php esc_html_e( 'Update-Channel', 'deubner_hp_services' ); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field( DEUBNER_HP_SERVICES_NONCE_ACTION ); ?>
        <input type="hidden" name="dhps_action" value="save_update_channel" />

        <?php $current_channel = get_option( 'dhps_update_channel', 'stable' ); ?>

        <label>
            <input type="radio" name="dhps_update_channel" value="stable"
                <?php checked( $current_channel, 'stable' ); ?> />
            <?php esc_html_e( 'Stabil (empfohlen fuer Produktivsysteme)', 'deubner_hp_services' ); ?>
        </label>
        <br />
        <label>
            <input type="radio" name="dhps_update_channel" value="beta"
                <?php checked( $current_channel, 'beta' ); ?> />
            <?php esc_html_e( 'Beta (Pre-Releases inklusive)', 'deubner_hp_services' ); ?>
        </label>

        <p>
            <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Channel speichern', 'deubner_hp_services' ); ?>
            </button>
        </p>
    </form>
</div>
```

POST-Handler in `class-dhps-admin-page-handler.php` muss `save_update_channel` dispatchen, Sanitize via `DHPS_GitHub_Updater::sanitize_channel()`, `update_option()`, anschliessend `delete_transient('dhps_github_release')` UND `delete_transient('dhps_github_release_beta')` triggern (Cache-Flush bei Channel-Wechsel).

### 7.6 Pflicht-Trust-Decisions fuer v0.16.0 (Doc-Verpflichtung)

| ID | Trust-Decision | Begruendung |
|----|----------------|-------------|
| T13 | **Kein Auto-Downgrade ueber Channels** | `version_compare > current` ist hartes Gatekeeping. Wer per Beta auf eine Pre-Release-Version stuck-t und zurueck zu Stable will, muss manuell handeln. |
| T14 | **Pre-Release-Tag-Format ist semver-konform mit Punkten** (`-rc.N`, `-beta.N`) | `version_compare` haengt am Punkt. `v0.16.0-rc1` ohne Punkt sortiert falsch. |
| T15 | **Cache-Trennung Stable vs Beta ist Pflicht** | Sonst kontaminiert ein Channel-Switch den anderen Channel. |
| T16 | **Plugin-Mount geteilt zwischen Dev und Stage** | Workflow-Praktikabilitaet > strikte ZIP-Pipeline-Isolation. ZIP-Pipeline-Test erfolgt nach echtem Pre-Release. |
| T17 | **Channel-Wechsel triggert sofortigen Cache-Flush beider Transients** | Verhindert Inkonsistenz-Zustaende. |

---

## 8. Naechste Schritte (Lead-Sicht)

1. **Discovery-Doc-Review durch User (Architekt)**.
2. **Specialist-Briefing F1** vorbereiten (mit Sektionen 3.1-3.3, 3.6, 7.1-7.3, 7.5, 7.6 als Pflicht-Anhang).
3. **Specialist-Briefing F2** vorbereiten (mit Sektionen 3.4, 3.5, 7.4 als Pflicht-Anhang).
4. **Parallel-Implementation** F1 + F2.
5. **Lead-Direct**: Version-Bump 0.15.5 -> 0.16.0, README, MEMORY-Eintrag, Doc-Updates 09/06/01.
6. **QA**: T1-T13 durchspielen, ein erstes Pre-Release `v0.16.0-rc.1` auf GitHub schneiden und auf Stage validieren.
7. **Promotion-Trigger** sobald T1-T13 gruen: `v0.16.0` Stable-Release.
8. **Beobachtungsphase** 24h auf Stage + Live-Reports.
9. **v0.16.1** kann jetzt mit Elementor 4.1.0 starten - die neue Stage ist die Test-Wiege.

---

## 9. Quellen / Referenzen

- `includes/class-dhps-github-updater.php` (Stand v0.15.5) - Code-Wahrheit fuer Updater
- `docs/architecture/09-GITHUB-UPDATER.md` - Architektur-Doku
- `docs/team-knowledge/06-RELEASE-WORKFLOW.md` - Release-Schritte
- `docker-compose.yml` (Stand v0.15.5) - Dev-Stack-Vorbild
- `Deubner_HP_Services.php` Zeile 339-345 - Updater-Bootstrap
- `Deubner_HP_Services.php` Zeile 750-764 - `dhps_register_options()` Settings-API-Anker
- v0.15.0 / v0.15.3 / v0.15.5 Schema-Vertrag-Vorgehen (3x in Folge 0 Critical-Drift-Fixes)
- GitHub-API: https://docs.github.com/en/rest/releases/releases
- PHP `version_compare` Semver-Verhalten: https://www.php.net/manual/en/function.version-compare.php
