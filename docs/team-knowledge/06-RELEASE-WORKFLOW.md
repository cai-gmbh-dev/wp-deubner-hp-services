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
