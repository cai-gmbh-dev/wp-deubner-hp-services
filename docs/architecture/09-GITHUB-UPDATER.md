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
    'cai-gmbh-dev',                                   // GitHub Owner
    'wp-deubner-hp-services',                         // Repository Name
    DEUBNER_HP_SERVICES_BASENAME,                     // Plugin Basename
    DEUBNER_HP_SERVICES_VERSION,                      // Aktuelle Version
    get_option( 'dhps_update_channel', 'stable' )     // Channel (seit 0.16.0)
);
```

### Update-Channel (seit v0.16.0)

Das Plugin unterstuetzt zwei Update-Kanaele:

| Channel | Sieht | Endpoint |
|---------|-------|----------|
| `stable` (Default) | Nur stable Releases | `/repos/{owner}/{repo}/releases/latest` |
| `beta` | Pre-Releases + Stable | `/repos/{owner}/{repo}/releases?per_page=30` (Liste, iteriert bis Version > current) |

**Konfiguration** ueber WP-Option `dhps_update_channel` (Admin-Dashboard -> Update-Channel-Block) oder per WP-CLI:

```bash
wp option update dhps_update_channel beta
```

**Sanitize**: `DHPS_GitHub_Updater::sanitize_channel( $value )` (public static) - Whitelist-Check via `DHPS_GitHub_Updater::ALLOWED_CHANNELS`, Fallback `stable`.

**Cache-Trennung**: Stable nutzt Transient `dhps_github_release`, Beta nutzt `dhps_github_release_beta`. `flush_release_cache()` leert beide.

**Trust-Decision T13**: Kein Auto-Downgrade ueber Channels - `version_compare > current` ist strikt. Wer auf einer Beta-Version steht und auf `stable` wechselt, sieht das naechste Stable erst, sobald es echt groesser ist (`v0.16.0 > v0.16.0-rc.3`).

**Verlinkung**: Release-Gate-Workflow siehe `docs/team-knowledge/07-RELEASE-CHECKLIST.md`. Tag-Format-Vertrag: Pre-Release MUSS mit Punkt-Suffix kommen (`v0.X.Y-rc.N`, nicht `v0.X.Y-rcN`).

### Hooks

| Hook | Methode | Zweck |
|------|---------|-------|
| `pre_set_site_transient_update_plugins` | `check_for_update()` | Version vergleichen |
| `plugins_api` | `plugin_info()` | Plugin-Details Dialog |
| `upgrader_source_selection` | `fix_directory_name()` | ZIP-Verzeichnis korrigieren |

### Caching

- **Transient (stable):** `dhps_github_release`
- **Transient (beta):** `dhps_github_release_beta` (seit v0.16.0)
- **TTL:** 10800 Sekunden (3 Stunden) - Code-Wahrheit `DHPS_GitHub_Updater::$cache_ttl`
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
