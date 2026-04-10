# GitHub Updater

## Zweck

Ermoeglicht automatische Plugin-Updates ueber GitHub Releases. Nutzer sehen Updates im WordPress-Dashboard und koennen mit einem Klick aktualisieren.

## Architektur

```
GitHub Repository (cai-gmbh-dev/wp-deubner-hp-services)
     │
     │  git tag v0.9.5 && git push --tags
     │  -> GitHub Release erstellen (manuell oder CI)
     │
     ▼
GitHub REST API v3
  GET /repos/{owner}/{repo}/releases/latest
     │
     ▼
DHPS_GitHub_Updater (WordPress-Plugin)
     │
     ├─► pre_set_site_transient_update_plugins
     │     Vergleicht GitHub-Version mit lokaler Version
     │     Traegt Update in WP-Transient ein
     │
     ├─► plugins_api
     │     Liefert Plugin-Details fuer "Details ansehen"-Dialog
     │     Zeigt Changelog aus Release-Notes
     │
     └─► upgrader_source_selection
           Korrigiert Verzeichnisnamen nach ZIP-Entpacken
           GitHub: {owner}-{repo}-{hash}/ -> wp-deubner-hp-services/
```

## Klasse: DHPS_GitHub_Updater

**Datei:** `includes/class-dhps-github-updater.php`

### Konstruktor
```php
new DHPS_GitHub_Updater(
    'cai-gmbh-dev',                    // GitHub Owner
    'wp-deubner-hp-services',          // Repository Name
    DEUBNER_HP_SERVICES_BASENAME,      // Plugin Basename
    DEUBNER_HP_SERVICES_VERSION        // Aktuelle Version
);
```

### Hooks

| Hook | Methode | Zweck |
|------|---------|-------|
| `pre_set_site_transient_update_plugins` | `check_for_update()` | Version vergleichen |
| `plugins_api` | `plugin_info()` | Plugin-Details Dialog |
| `upgrader_source_selection` | `fix_directory_name()` | ZIP-Verzeichnis korrigieren |

### Caching

- **Transient:** `dhps_github_release`
- **TTL:** 43200 Sekunden (12 Stunden)
- **Error-TTL:** 600 Sekunden (10 Minuten) bei API-Fehler
- **Kein API-Token noetig** (oeffentliches Repository)

### Version-Normalisierung

Git-Tags werden normalisiert: `v0.9.5` -> `0.9.5`

Unterstuetzte Formate:
- `v0.9.5`, `V0.9.5` -> `0.9.5`
- `0.9.5` -> `0.9.5` (unveraendert)

## Release-Workflow

### Neues Release erstellen

```bash
# 1. Version in Plugin-Header und Konstante aktualisieren
# 2. Committen und taggen
git add -A
git commit -m "release: v0.9.5"
git tag v0.9.5
git push origin main --tags

# 3. GitHub Release erstellen (manuell oder gh CLI)
gh release create v0.9.5 --title "v0.9.5" --notes "## Changelog
- Feature X
- Fix Y
- Hardening Z"
```

### Release-Konventionen

- Tags: `v{major}.{minor}.{patch}` (z.B. `v0.9.5`)
- Release-Title: `v{version}`
- Release-Notes: Markdown-Format (## Ueberschriften, - Listen)
- ZIP-Download: Automatisch via `zipball_url`

## Sicherheit

- Nur oeffentliche GitHub API (kein Token, kein Secret)
- HTTPS fuer alle API-Calls
- WordPress HTTP API (`wp_remote_get`) mit User-Agent
- Changelog via `esc_html()` escaped
- Verzeichnisname-Fix nur fuer eigenes Plugin (Basename-Check)

## Fehlerbehandlung

- API-Timeout: 10 Sekunden
- Bei Fehler: 10-Minuten-Cache (verhindert API-Spam)
- Bei 404 (kein Release): Kein Update angezeigt
- Leerer Response: Ignoriert, kein Update
- `no_update` Eintrag: Verhindert WP.org-Fallback-Abfrage
