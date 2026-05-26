# Release-Workflow

## Voraussetzungen

- Git CLI
- GitHub CLI (`gh`) oder GitHub Web-Interface
- Schreibrechte auf `cai-gmbh-dev/wp-deubner-hp-services`

## Schritte fuer ein neues Release

### 1. Version aktualisieren

In `Deubner_HP_Services.php` drei Stellen aendern:

```php
// Zeile 4: Plugin-Header
 * Version: 0.9.6

// Zeile 18: PHPDoc
 * @version 0.9.6

// Zeile 39: Konstante
define( 'DEUBNER_HP_SERVICES_VERSION', '0.9.6' );
```

### 2. README.md aktualisieren

```markdown
Version: 0.9.6 | ...
```

### 3. Commit und Tag

```bash
git add -A
git commit -m "release: v0.9.6 - Beschreibung der Aenderungen"
git tag v0.9.6
git push origin main --tags
```

### 4. GitHub Release erstellen

```bash
gh release create v0.9.6 \
  --title "v0.9.6" \
  --notes "## Changelog

- Feature: Neue Funktion X
- Fix: Fehler Y behoben
- Hardening: Sicherheitsverbesserung Z"
```

Oder ueber die GitHub Web-Oberflaeche:
1. https://github.com/cai-gmbh-dev/wp-deubner-hp-services/releases/new
2. Tag auswaehlen (v0.9.6)
3. Release-Notes eingeben
4. "Publish release" klicken

### 5. Verifikation

- WordPress-Installation oeffnen
- Dashboard > Aktualisierungen pruefen
- Plugin-Update sollte angezeigt werden (nach max. 12h Cache-TTL)
- Zum sofortigen Test: `wp transient delete dhps_github_release --allow-root`

## Tag-Konventionen

```
v{major}.{minor}.{patch}

Beispiele:
v0.9.5   - Patch/Bugfix
v0.10.0  - Minor (neue Features)
v1.0.0   - Major (Breaking Changes)
```

Seit v0.16.0 zusaetzlich Pre-Release-Format (siehe Pre-Release-Schritt unten):

```
v{major}.{minor}.{patch}-rc.{n}    z.B. v0.16.0-rc.1
v{major}.{minor}.{patch}-beta.{n}  z.B. v0.16.0-beta.1
```

Wichtig: Der Suffix MUSS einen Punkt enthalten (`-rc.1`, NICHT `-rc1`).
PHPs `version_compare` sortiert sonst falsch (Trust-Decision T14).

## Pre-Release-Schritt vor Stable (seit v0.16.0)

Seit v0.16.0 gibt es ein zweistufiges Release-Gate: zuerst Pre-Release auf der
Stage-Site testen, dann erst Stable promoten. Details, Smoke-Tests und
Rollback-Strategie stehen in der Checkliste:

-> `docs/team-knowledge/07-RELEASE-CHECKLIST.md`

Kurz-Ablauf:

```bash
# 1. Pre-Release auf GitHub schneiden (sichtbar nur fuer Beta-Channel-Sites)
gh release create v0.16.0-rc.1 \
  --prerelease \
  --title "v0.16.0-rc.1" \
  --notes "Release-Candidate fuer Stage-Test"

# 2. Stage-Site (Channel=beta) testet -> Smoke-Tests aus 07-RELEASE-CHECKLIST.md

# 3. Bei OK: NEUEN Stable-Tag schneiden (nicht den Pre-Release-Tag editieren)
gh release create v0.16.0 \
  --notes-file release-notes-v0.16.0.md \
  --latest

# 4. Bei NICHT-OK: Pre-Release loeschen oder neuen rc-Tag schneiden
gh release delete v0.16.0-rc.1
git tag -d v0.16.0-rc.1
git push --delete origin v0.16.0-rc.1
```

Voraussetzung: Auf der Stage-Site ist `dhps_update_channel = beta` gesetzt
(WP-Admin -> Deubner Verlag -> Update-Channel oder per WP-CLI).

## Checkliste vor Release

- [ ] Version in Plugin-Header, PHPDoc und Konstante aktualisiert
- [ ] README.md Version aktualisiert
- [ ] Alle Tests lokal bestanden
- [ ] Docker-Umgebung getestet
- [ ] Keine Credentials oder Secrets im Code
- [ ] .gitignore aktuell (docker/, .env.docker nicht im Release)
- [ ] Changelog in Release-Notes dokumentiert

## Wie der Update-Mechanismus funktioniert

1. WordPress prueft regelmaessig Plugin-Updates (Transient `update_plugins`)
2. `DHPS_GitHub_Updater::check_for_update()` fragt GitHub API
3. Vergleicht `tag_name` (z.B. `v0.9.6`) mit `DEUBNER_HP_SERVICES_VERSION`
4. Bei neuerer Version: Update im Dashboard angezeigt
5. User klickt "Aktualisieren"
6. WordPress laedt ZIP von `zipball_url`
7. `fix_directory_name()` benennt entpacktes Verzeichnis korrekt um
8. Plugin ist aktualisiert

## Troubleshooting

### Update wird nicht angezeigt
- GitHub Release als "latest" markiert? (kein Pre-Release)
- Tag-Format korrekt? (`v0.9.6`, nicht `0.9.6`)
- Cache leeren: `wp transient delete dhps_github_release`
- GitHub API erreichbar? (Firewall, Rate Limit)

### Update schlaegt fehl
- Verzeichnisrechte pruefen (`wp-content/plugins/`)
- PHP-Fehlerlog pruefen (`wp-content/debug.log`)
- Manuell: ZIP herunterladen, per FTP hochladen
