# Live-Migration auf Elementor 4.1.0 + DHPS v0.16.1

> **Zielgruppe:** User / Site-Admin der Live-Site, sowie Stage-Simulation-Specialist.
> **Status-Stand:** Discovery-Doc fuer v0.16.2, erstellt 2026-05-27.
> **Geschaetzte Migration-Dauer (User-Live):** 30 - 60 Minuten inkl. Backup + Verifikation.
> **Voraussetzung:** Diese Anleitung wurde auf der Stage simuliert + verifiziert
> bevor der User sie live anwendet (siehe Sektion 10).

---

## Sektion 1: Ausgangslage

### 1.1 Aktueller Live-Stand vs. Ziel

| Komponente | Live (vermutet) | Ziel | Anmerkung |
|------------|------|------|-----------|
| WordPress | 6.9.x | unveraendert | Bereits >= 6.8 (Pro-Minimum) |
| PHP | 8.3.x | unveraendert | Bereits >= 7.4 (Pro-Minimum) |
| Elementor Free | 3.35.x (vermutet) | **4.1.0** | wordpress.org/plugins/elementor |
| Elementor Pro | **4.0.1** (User-bestaetigt) | **4.1.0** | ZIP `related-infos/vs-nfd/elementor-pro-4.1.0.zip` |
| DHPS Plugin | v0.15.x oder v0.16.0 | **v0.16.1** | GitHub-Updater Channel = `stable` |

### 1.2 Was geht aktuell schief

- **Versions-Mismatch:** Pro 4.1.0 (Empfehlung) verlangt Free >= 3.35 (Minimum) bzw. 4.1.x (Empfohlen). Mit Pro 4.0.1 + Free 3.35.x laufen beide noch konsistent, aber das ist 1 Major-Version hinter aktuell.
- **DHPS v0.16.1 Defensive Notice (NEU):** Ab v0.16.1 zeigt das DHPS-Plugin im WP-Admin einen gelben Banner `Deubner HP Services: Elementor X.Y.Z erkannt - empfohlen ist mindestens 4.1.0` wenn Free oder Pro < 4.1.0. Das adressiert die Wurzel von "Elementor klappt nicht mehr".
- **Atomic Editor (V4):** Ab Free 3.35.0 ist der V4-Editor Production-Ready. Klassische Widgets (incl. DHPS) bleiben kompatibel, aber das Editor-UI hat sich teils umstrukturiert - User-Wahrnehmung "klappt nicht mehr" kann auch UI-Vertrautheit sein.

### 1.3 Was wird durch die Migration korrigiert

- Free + Pro auf gleiche 4.1.0er Version -> Empfehlungs-Konstellation erreicht
- DHPS v0.16.1 mit aktivem Defensive Notice -> verschwindet automatisch nach Schritt 3.6
- DHPS Stage-Smoke 11/11 effektiv OK -> Plugin verifiziert kompatibel mit 4.1.0
- BC-erhaltend: Keine DB-Schema-Aenderung, keine HTML-Aenderung, alle 13 Widgets bleiben funktional

---

## Sektion 2: Vorbereitung (Pre-Migration-Checkliste)

### 2.1 Full-Site-Backup (Pflicht VOR jeder Aktion)

**Variante A: Hosting-Provider-Backup (empfohlen)**

Die meisten Managed-WP-Hostings (WP-Engine, Kinsta, SiteGround, All-Inkl, Hosting-Pakete bei Strato/IONOS) bieten 1-Klick-Snapshots an. Erst Snapshot ziehen, dann fortfahren.

**Variante B: Manuell via SSH/WP-CLI**

```bash
# 1. Datenbank-Dump
ssh user@live-site
cd /pfad/zu/wordpress
wp db export "backup-pre-v0161-$(date +%F).sql"

# 2. wp-content/-Backup (Plugins + Themes + Uploads)
tar -czf "wp-content-backup-pre-v0161-$(date +%F).tar.gz" wp-content/

# 3. Backup-Files vom Server kopieren
exit
scp user@live-site:/pfad/zu/wordpress/backup-pre-v0161-*.sql ./
scp user@live-site:/pfad/zu/wordpress/wp-content-backup-pre-v0161-*.tar.gz ./
```

**Variante C: Plugin-basiert**

UpdraftPlus / Duplicator / All-in-One WP Migration. Backup OHNE Inkrement (volles Backup).

### 2.2 Wartungs-Modus aktivieren

**Variante A: WP-CLI (wenn verfuegbar):**

```bash
wp maintenance-mode activate
# Migration durchfuehren ...
wp maintenance-mode deactivate
```

**Variante B: Plugin:**

WP Maintenance Mode (free Plugin) -> aktivieren -> Custom-Page mit Hinweis "Wir aktualisieren, in 30 Min wieder da".

**Variante C: `.maintenance`-File:**

```bash
ssh user@live-site
cd /pfad/zu/wordpress
echo "<?php \$upgrading = time(); ?>" > .maintenance
# Migration ...
rm .maintenance
```

### 2.3 Test-URL bereithalten

Eine Live-URL die einen DHPS-Shortcode oder ein DHPS-Elementor-Widget enthaelt. Dient als Smoke-URL fuer Frontend-Verifikation.

- Wenn unbekannt, im WP-Admin: `wp-admin/edit.php?s=%5Btp%5D` -> sucht Pages mit `[tp]`-Shortcode
- Alternativ: WP-Admin -> Seiten -> nach "Steuerportal" / "MAES" / "Aktuelles" Tabs filtern
- URL notieren, in separatem Browser-Tab geoffnet halten

### 2.4 Browser-DevTools vorbereiten

- F12 oeffnen, Tab "Console" + "Network" sichtbar machen
- Filter auf "Errors" stellen
- Disable Cache aktivieren (in Network-Tab)
- Ein zweiter Browser-Tab fuer das WP-Admin daneben

### 2.5 Optional: Debug-Logging

```bash
ssh user@live-site
cd /pfad/zu/wordpress
wp config set WP_DEBUG true --raw
wp config set WP_DEBUG_LOG true --raw
wp config set WP_DEBUG_DISPLAY false --raw
# debug.log liegt dann unter wp-content/debug.log
tail -f wp-content/debug.log
```

Nach Migration WP_DEBUG_LOG ausschalten oder Log-File rotieren.

---

## Sektion 3: Migration-Reihenfolge

### 3.0 Empfohlene Reihenfolge: Option A

**Option A (EMPFOHLEN): Elementor Free -> Elementor Pro -> DHPS**

Begruendung:

1. **DHPS v0.15.x/v0.16.0 ist mit jedem Elementor 3.x/4.x kompatibel** dank klassischer `Widget_Base`-API. Code-Audit aus v0.16.1 ergab 0 API-Findings. -> DHPS kann zuletzt updaten ohne Probleme.
2. **Elementor Free ist Pro-Dependency.** Pro 4.1.0 hat `Requires Plugins: elementor` + `ELEMENTOR_PRO_REQUIRED_CORE_VERSION = '3.35'`. Free zuerst auf 4.1.0 zu bringen ist die offizielle Empfehlung und verhindert Mismatch-Period waehrend der Migration.
3. **DHPS v0.16.1 fuegt NUR einen Notice** hinzu (keine Widget-Code-Aenderung). Wenn Schritt 3.5 in einer Welt mit Free + Pro = 4.1.0 stattfindet, verschwindet der Notice sofort. -> Saubere Erfolgs-Signalisierung.
4. **Rollback-Granularitaet:** Bei Problem in Schritt 3.1 (Free) bleibt Pro + DHPS unangetastet. Bei Problem in 3.3 (Pro) bleibt DHPS unangetastet. Bei Problem in 3.5 (DHPS) sind Free + Pro bereits stabil neu - Rollback nur DHPS einfach.

**Verworfen:** Option B (DHPS zuerst). Risiko: DHPS v0.16.1 mit alten Elementor-Versionen waehrend Migration-Period generiert direkt den Defensive-Notice - User koennte das als "neues Problem durch Update" fehlinterpretieren.

**Verworfen:** Option C (parallel/mixed). Schwer rollbarbar, schwer debuggbar bei Problemen.

### 3.1 Elementor Free 3.35.x -> 4.1.0

**Variante A (empfohlen): WP-CLI**

```bash
ssh user@live-site
cd /pfad/zu/wordpress
wp plugin update elementor --version=4.1.0
# Falls Update-Server nicht antwortet: direktes Install ueber wordpress.org-ZIP
wp plugin install https://downloads.wordpress.org/plugin/elementor.4.1.0.zip --force --activate
wp plugin list --name=elementor
# Erwartung: Spalte "version" = 4.1.0
```

**Variante B: WP-Admin (Klick-Pfad)**

1. WP-Admin -> Plugins -> Elementor finden
2. "Update auf 4.1.0" klicken (sofern WP es als Update anbietet)
3. Bei Erfolg: gruener Hinweis, Plugin bleibt aktiv

**Variante C: Manueller ZIP-Upload (falls 4.1.0 nicht von wordpress.org abrufbar)**

1. ZIP von wordpress.org/plugins/elementor/advanced/ holen (Version 4.1.0)
2. WP-Admin -> Plugins -> Installieren -> Plugin hochladen -> ZIP waehlen
3. "Aktuelle durch hochgeladene ersetzen" klicken

### 3.2 Verifikation nach Schritt 3.1

**Pflicht-Checks:**

- [ ] `wp plugin list --name=elementor` zeigt Version `4.1.0`, Status `active`
- [ ] WP-Admin -> Aktualisierungen -> kein neuer Warning
- [ ] WP-Admin -> Elementor -> Settings laesst sich oeffnen (kein White-Screen)
- [ ] Live Smoke-URL (aus 2.3) im 2. Browser-Tab refreshen -> Page rendert, keine Layout-Bruche
- [ ] Browser-DevTools-Console: keine neuen JS-Errors
- [ ] `tail wp-content/debug.log` (falls 2.5 aktiv): keine neuen PHP-Fatal/Notice

**Wenn Probleme: -> Sektion 5.1 Rollback Schritt 3.1**

### 3.3 Elementor Pro 4.0.1 -> 4.1.0

Elementor Pro updated NICHT ueber wordpress.org (Pro-Plugin, eigener Updater).

**Variante A: Pro-eigener Updater (wenn Lizenz auf der Site aktiv)**

1. WP-Admin -> Elementor -> License -> "Check for Updates"
2. WP-Admin -> Plugins -> "Update Elementor Pro" Button klicken
3. Update laeuft durch, Plugin bleibt aktiv

**Variante B: ZIP-Upload (immer verfuegbar)**

1. ZIP-Datei beschaffen: `related-infos/vs-nfd/elementor-pro-4.1.0.zip` (3.8 MB, im Repo)
2. WP-Admin -> Plugins -> Installieren -> Plugin hochladen -> `elementor-pro-4.1.0.zip` waehlen
3. "Aktuelle durch hochgeladene ersetzen" klicken
4. Pro bleibt aktiv

```bash
# WP-CLI-Variante mit ZIP
scp related-infos/vs-nfd/elementor-pro-4.1.0.zip user@live-site:/tmp/
ssh user@live-site
cd /pfad/zu/wordpress
wp plugin install /tmp/elementor-pro-4.1.0.zip --force --activate
rm /tmp/elementor-pro-4.1.0.zip
```

### 3.4 Verifikation nach Schritt 3.3

**Pflicht-Checks:**

- [ ] `wp plugin list --name=elementor-pro` zeigt Version `4.1.0`, Status `active`
- [ ] WP-Admin -> Plugins -> Elementor + Pro beide active, keine roten Banner
- [ ] WP-Admin -> Aktualisierungen -> kein neuer Warning
- [ ] Smoke-URL refreshen -> Page rendert wie vorher
- [ ] Browser-DevTools-Console: keine neuen JS-Errors
- [ ] `wp-content/debug.log`: keine neuen PHP-Fatal/Notice
- [ ] WP-Site-Health (`Werkzeuge -> Webseite-Zustand`) zeigt keine Critical-Items

**Wenn Probleme: -> Sektion 5.2 Rollback Schritt 3.3**

### 3.5 DHPS v0.X.X -> v0.16.1

DHPS verwendet GitHub-Updater (`includes/class-dhps-github-updater.php`). User sieht das Update wie ein normales WordPress-Plugin-Update.

**Variante A (empfohlen): GitHub-Updater via WP-Admin**

1. WP-Admin -> Aktualisierungen -> Pruefen-Klicken (Trigger fuer Transient-Refresh)
2. "Deubner HP Services" sollte mit Update-Button auf 0.16.1 erscheinen
3. Update-Banner sagt "Deubner HP Services 0.16.1 verfuegbar"
4. "Aktualisieren" klicken
5. Plugin entpackt sich, GitHub-Updater korrigiert Verzeichnisnamen (`fix_directory_name`)
6. Plugin bleibt aktiv

**Variante B: WP-CLI Cache-Reset + Update**

```bash
ssh user@live-site
cd /pfad/zu/wordpress
# Cache leeren damit Updater sofort neu prueft
wp transient delete dhps_github_release
wp plugin update wp-deubner-hp-services
wp plugin list --name=wp-deubner-hp-services
# Erwartung: version = 0.16.1
```

**Variante C: Manueller ZIP-Upload (Notfall)**

```bash
# Vom GitHub-Release-Asset
wget https://github.com/cai-gmbh-dev/wp-deubner-hp-services/archive/refs/tags/v0.16.1.zip -O dhps-v0.16.1.zip
# WP-Admin -> Plugins -> Installieren -> ZIP -> hochladen -> ersetzen
```

> **Hinweis Cache-TTL:** Der DHPS_GitHub_Updater cached die Release-Info 3 Stunden (`$cache_ttl = 10800`). Wenn das Update nicht erscheint, manuell Transient leeren (siehe Variante B).

### 3.6 Verifikation nach Schritt 3.5

**Pflicht-Checks:**

- [ ] `wp plugin list --name=wp-deubner-hp-services` zeigt Version `0.16.1`, Status `active`
- [ ] WP-Admin -> Aktualisierungen -> kein neuer Warning fuer DHPS
- [ ] **DHPS Defensive Notice MUSS WEG SEIN** (weil Free + Pro = 4.1.0 erfuellen Mindest-Versions-Konstanten):
  - Wenn Notice noch sichtbar: -> Pruefe `wp plugin list --name=elementor` + `wp plugin list --name=elementor-pro` ob beide wirklich 4.1.0
- [ ] WP-Admin -> Deubner HP Services -> Dashboard laesst sich oeffnen
- [ ] Smoke-URL refreshen -> Page rendert, DHPS-Widgets sichtbar
- [ ] Browser-DevTools-Console: keine neuen JS-Errors
- [ ] `wp-content/debug.log`: keine neuen PHP-Fatal/Notice
- [ ] Wenn Live-Page Elementor-Widget hat: WP-Admin -> Page -> "Mit Elementor bearbeiten" -> alle 13 DHPS-Widgets unter "Deubner Services"-Kategorie sichtbar

**Wenn Probleme: -> Sektion 5.3 Rollback Schritt 3.5**

---

## Sektion 4: Verifikations-Checkliste (kondensiert)

Diese Checkliste wird nach **JEDEM** Migration-Schritt durchgegangen.

```
[ ] WP-Admin erreichbar (kein White-Screen, kein 500er)
[ ] WP-Admin -> Aktualisierungen ohne neue Warnings
[ ] Smoke-URL (siehe 2.3) rendert OK im Frontend
[ ] Browser-Konsole (F12) zeigt keine NEUEN JS-Errors
[ ] wp-content/debug.log: keine NEUEN PHP-Fatals/Notices
[ ] WP-Site-Health (Werkzeuge -> Webseite-Zustand) zeigt keine Critical
[ ] Nach Schritt 3.6 zusaetzlich: DHPS Defensive Notice ist WEG
```

**Wichtig:** "Neue" Fehler meint Fehler die VOR dem Migration-Schritt nicht da waren. Eine pre-existierende Notice ist toleriert.

---

## Sektion 5: Rollback (pro Schritt)

### 5.1 Rollback Schritt 3.1 (Elementor Free)

```bash
# WP-CLI-Variante (empfohlen)
wp plugin deactivate elementor
wp plugin delete elementor
wp plugin install elementor --version=3.35.4 --force --activate
wp plugin list --name=elementor
# Erwartung: version = 3.35.4

# Falls 3.35.4 nicht vom WP.org-Repo geliefert wird:
# 1. Backup von wp-content/plugins/elementor-3.35.4.zip aus 2.1 nutzen
# 2. ODER: vor der Migration ZIP von wordpress.org/plugins/elementor/advanced/ holen
```

**WP-Admin-Klickpfad:**
1. Plugins -> Elementor -> Deaktivieren -> Loeschen
2. Plugins -> Installieren -> Plugin hochladen -> elementor.3.35.4.zip (aus Backup)
3. Aktivieren

### 5.2 Rollback Schritt 3.3 (Elementor Pro)

```bash
# Pro 4.0.1-ZIP bereithalten (NICHT in Repo, Pro-Lizenz noetig)
# Quelle 1: Elementor-Account my.elementor.com -> Downloads -> 4.0.1
# Quelle 2: Backup aus 2.1 (wp-content/plugins/elementor-pro-*.zip)

wp plugin deactivate elementor-pro
wp plugin delete elementor-pro
wp plugin install /pfad/zu/elementor-pro-4.0.1.zip --force --activate
wp plugin list --name=elementor-pro
# Erwartung: version = 4.0.1
```

**WP-Admin-Klickpfad:**
1. Plugins -> Elementor Pro -> Deaktivieren -> Loeschen
2. Plugins -> Installieren -> Plugin hochladen -> elementor-pro-4.0.1.zip
3. Aktivieren

> **Warnung:** Pro-License-Key kann nach Rollback nicht-mehr-aktiv erscheinen. License-Reaktivierung via WP-Admin -> Elementor -> License -> Connect.

### 5.3 Rollback Schritt 3.5 (DHPS)

**Variante A: WP-CLI**

```bash
# Plugin deaktivieren
wp plugin deactivate wp-deubner-hp-services

# Verzeichnis loeschen
rm -rf /pfad/zu/wordpress/wp-content/plugins/wp-deubner-hp-services

# Alte Version installieren (vom GitHub-Release)
wp plugin install https://github.com/cai-gmbh-dev/wp-deubner-hp-services/archive/refs/tags/v0.15.5.zip --activate

# ODER aus Backup (2.1)
tar -xzf wp-content-backup-pre-v0161-*.tar.gz wp-content/plugins/wp-deubner-hp-services/
mv wp-content/plugins/wp-deubner-hp-services /pfad/zu/wordpress/wp-content/plugins/
wp plugin activate wp-deubner-hp-services
```

**Variante B: FTP/SSH manuell**

1. Per FTP: alte v0.15.5 oder v0.16.0 ZIP entpacken nach `wp-content/plugins/wp-deubner-hp-services/` (kompletter Replace)
2. WP-Admin -> Plugins -> aktivieren falls deaktiviert

> **DB-State:** DHPS hat KEIN DB-Schema in v0.16.1 geaendert (nur Defensive Notice ohne Optionen-Schreibvorgang). Rollback ist bytewise-revert ohne DB-Migration noetig.

### 5.4 Notfall: Maintenance-Mode + komplette Wiederherstellung

Wenn mehrere Schritte simultan fehlschlagen oder Site kaputt:

```bash
# Maintenance-Mode aktivieren (siehe 2.2)
wp maintenance-mode activate

# Komplettes Backup zurueckspielen
ssh user@live-site
cd /pfad/zu/wordpress

# wp-content zurueckspielen
rm -rf wp-content
tar -xzf wp-content-backup-pre-v0161-*.tar.gz

# Datenbank zurueckspielen
wp db import backup-pre-v0161-*.sql

# Maintenance-Mode aus
wp maintenance-mode deactivate
```

---

## Sektion 6: Erwartete UX-Aenderungen

### 6.1 Vor der Migration

- Live-Site funktioniert grundsaetzlich
- Bei Pro 4.0.1 + Free 3.35.x: evtl. Atomic-Editor (V4) Layout-Differenzen im Editor
- Frontend-Render unauffaellig
- Kein DHPS Defensive Notice (wenn noch auf v0.15.x oder v0.16.0)

### 6.2 Waehrend Schritt 3.5 (DHPS v0.16.1 aktiv, Free/Pro noch nicht 4.1.0)

Wenn der User aus irgendeinem Grund die Reihenfolge umkehrt (B statt A):

- DHPS Defensive Notice **ERSCHEINT** auf allen WP-Admin-Seiten als gelber Banner
- Frontend bleibt funktional, alle Widgets rendern
- Banner-Text: `Deubner HP Services: Elementor X.Y.Z erkannt - empfohlen ist mindestens 4.1.0.`
- Banner-Text (falls Pro alt): `Deubner HP Services: Elementor Pro X.Y.Z erkannt - empfohlen ist mindestens 4.1.0.`

### 6.3 Nach allen Schritten (Ziel-Zustand)

- DHPS Defensive Notice **WEG**
- Free + Pro 4.1.0 + DHPS v0.16.1
- 13 Widgets im Elementor-Panel sichtbar
- Frontend-HTML bytewise identisch zu vorher (v0.16.1 hat KEINE Widget-Code-Aenderung)
- WP-Admin-Dashboard hat keine neuen Warnings

### 6.4 Backwards-Compatibility-Garantie

v0.16.1 garantiert:
- Widget-Names unveraendert (`dhps-mio`, `dhps-tp`, ...)
- Widget-Category unveraendert (`dhps-services`)
- Setting-Keys/Atts unveraendert
- CSS-Selektoren unveraendert
- `_elementor_data` Post-Meta bytewise lesbar
- Frontend-HTML byte-identisch

---

## Sektion 7: Reihenfolge-Variante mit Beta-Channel (Optional)

Wenn der User vor v0.16.1-Promotion testen will (z.B. fuer v0.16.2 Pre-Release):

### 7.1 Beta-Channel aktivieren

**WP-Admin-Klickpfad:**
1. WP-Admin -> Deubner HP Services -> Dashboard
2. "Update-Channel"-Block -> auf `Beta` umschalten -> Speichern

**WP-CLI:**

```bash
wp option update dhps_update_channel beta
wp transient delete dhps_github_release         # alten Stable-Cache leeren
wp transient delete dhps_github_release_beta    # falls vorhanden
```

### 7.2 Pre-Release im WP-Admin pruefen

1. WP-Admin -> Aktualisierungen -> "Erneut pruefen"
2. Update-Banner zeigt `v0.16.X-rc.N` (Pre-Release)
3. "Aktualisieren" klicken
4. Smoke-Tests wie Sektion 3.6 durchgehen

### 7.3 Channel zurueck auf Stable

Wenn Pre-Release Tests OK + ein neues Stable-Release verfuegbar ist:

```bash
wp option update dhps_update_channel stable
wp transient delete dhps_github_release_beta
wp transient delete dhps_github_release
```

> **Trust-Decision T13:** Kein Auto-Downgrade. Wer auf Pre-Release sitzt und auf `stable` wechselt, sieht das Stable erst, wenn `version_compare( $latest_stable, $current_prerelease, '>' )` echt groesser ist. -> Pre-Release-Tag bleibt liegen, Stable mit gleichem Code-Stand muss neu geschnitten werden.

---

## Sektion 8: Polish-Findings aus v0.16.1 QA

Aus dem v0.16.1 QA-Bericht waren 3 Minor-Findings dokumentiert.

### M1: `public const` liegt NACH `init()`

**Befund:** In `includes/class-dhps-elementor.php` liegt die Konstanten-Deklaration `ELEMENTOR_MIN_VERSION` (Z. 100) und `ELEMENTOR_PRO_MIN_VERSION` (Z. 108) **nach** `init()` (Z. 80-91). WordPress-Coding-Standards (WPCS) empfehlen Konstanten am **Klassen-Anfang** (nach den Properties, vor dem ersten Constructor / nach Properties).

**Bewertung fuer v0.16.2:**
- Severity: **Trivial** (kein Funktionsbug, kein BC-Risiko)
- Aufwand: 2 Min (Verschieben der Konstanten an Position direkt nach `$cache`-Property Z. 51)
- Lead-Empfehlung: **JA in v0.16.2 mitnehmen**, da Refactor-Touch ohnehin minimal.

### M2: `current_user_can('manage_options')` vs `activate_plugins`

**Befund:** `maybe_render_version_notice()` Z. 120 nutzt `manage_options`. Semantisch geht es aber um eine Plugin-Verfuegbarkeit (Elementor-Stack-Konfiguration). `activate_plugins`-Capability waere genauer.

**Bewertung fuer v0.16.2:**
- Severity: **Trivial** (beide Caps sind in der Praxis fuer Administratoren gleich, nur Multisite-Spezialfaelle koennen abweichen)
- WordPress Coding Standards: `manage_options` ist semantisch fuer "Plugin/Theme-Settings", `activate_plugins` fuer "Plugin-Activation-State"
- Capa-Hierarchie: Beide Caps sind nur fuer Administrator-Role default, nicht fuer Editor / Author. -> Auswirkung identisch in Single-Site.
- Lead-Empfehlung: **JA in v0.16.2 anpassen** auf `activate_plugins`. Semantisch praeziser, kein BC-Risiko, kein Funktionswechsel.

### M3: Notice erscheint auf ALLEN Admin-Pages

**Befund:** Der Notice ist an Hook `admin_notices` registriert (Z. 90). Damit erscheint er auf **jeder** WP-Admin-Seite (Dashboard, Plugins, Posts, Settings, etc.). Bei einigen User-Workflows kann das nerven (z.B. wenn man stundenlang in Posts arbeitet).

**Mogliche Einschraenkungen:**
- Nur auf Plugin-Liste (`get_current_screen()->id === 'plugins'`)
- Nur auf Elementor-Settings + DHPS-Dashboard (2 Screens)
- Mit Dismiss-Button (per User-Meta merken)

**Bewertung fuer v0.16.2:**
- Severity: **Minor** (UX-Friction, kein Bug)
- Aufwand: S (10 Min mit `get_current_screen` Filter)
- Risiko: Wenn auf Plugin-Liste beschraenkt, ist die Sichtbarkeit auf Posts/Pages-View weg - User koennte den Notice gar nicht sehen (Plugin-Liste wird selten geoeffnet).
- Lead-Empfehlung: **JA mit Kompromiss**: Beschraenkung auf 3 Screens (Dashboard `'index'`, Plugins-Liste `'plugins'`, DHPS-Dashboard `'toplevel_page_dhps_dashboard'`) statt Hide-All oder Show-All. Optional Dismiss-Button via `update_user_meta('dhps_version_notice_dismissed_v0161', true)`.

### Polish-Empfehlung Gesamt fuer v0.16.2

Alle 3 sind triviale Quality-of-Life-Verbesserungen, kombinierter Aufwand < 30 Min Lead-Direct. Lead-Empfehlung: **ALLE 3 in v0.16.2 mitnehmen** als Cleanup-Sub-Mission neben der Live-Migration-Anleitung.

---

## Sektion 9: Acceptance fuer v0.16.2 (Stage-Simulation)

Diese Tests gehoeren in den Stage-Simulation-Specialist-Auftrag (Sektion 10).

| ID | Kriterium | Verifikation |
|----|-----------|--------------|
| T1 | Stage zurueckgesetzt auf User-Live-Stand: Free 3.35.4 + Pro 4.0.1 + DHPS v0.15.5, GitHub-Channel = `stable` | `wp plugin list` + `wp option get dhps_update_channel` |
| T2 | Migration Schritt 3.1 wirkt: `wp plugin update elementor --version=4.1.0` produziert Free 4.1.0 ohne Fehler | `wp plugin list --name=elementor` -> version 4.1.0 |
| T3 | Site funktional nach Schritt 3.1: Smoke-URL HTTP 200, debug.log clean, DevTools-Console clean | `curl -I` + `tail debug.log` |
| T4 | Migration Schritt 3.3 wirkt: Pro 4.0.1 -> 4.1.0 via ZIP-Install ohne Fehler | `wp plugin list --name=elementor-pro` -> version 4.1.0 |
| T5 | Site funktional nach Schritt 3.3: dito T3 | dito |
| T6 | Migration Schritt 3.5 wirkt: DHPS v0.15.5 -> v0.16.1 via WP-CLI `plugin update` | `wp plugin list --name=wp-deubner-hp-services` -> version 0.16.1 |
| T7 | Defensive Notice nach 3.6 ist WEG (Free + Pro = 4.1.0 erfuellen Min-Konstante) | WP-Admin-Screenshot oder cURL-grep auf `Deubner HP Services: Elementor` |
| T8 | 13 Widgets bleiben registriert + sichtbar im Elementor-Panel unter `dhps-services`-Kategorie | Browser-Visual (Editor-Sidebar) oder PHP-Smoke `class_exists` |
| T9 | Stage-Stack `dhps-stage-wordpress-1` debug.log am Ende ohne neue Fatals | `docker exec dhps-stage-wordpress-1 tail /var/www/html/wp-content/debug.log` |
| T10 | Stage-Cleanup: alle test-*.php files in `wp-content/plugins/wp-deubner-hp-services/` geloescht | `find . -name "test-*.php" -path "*/plugins/wp-deubner-hp-services/*"` zeigt 0 Hits |

---

## Sektion 10: Spec-Briefing fuer P2-Stage-Simulation-Specialist

### 10.1 Mission

**Aufgabe:** Diese Live-Migration-Anleitung 1-zu-1 auf der Stage `dhps-stage-wordpress-1` (Port 8086) simulieren. Die Anleitung muss reproduzierbar funktionieren bevor der User sie live ausfuehrt.

### 10.2 Stage-Reset auf User-Live-Stand

```bash
# 1. Stage-Stack hochfahren (falls down)
docker compose -p dhps-stage -f docker-compose.staging.yml up -d

# 2. Elementor-Stack auf 3.35.4 + 4.0.1 zuruecksetzen
#    Free 3.35.4 - aus related-infos/vs-nfd/elementor.3.35.4.zip
docker exec -i dhps-stage-wordpress-1 wp plugin deactivate elementor --allow-root || true
docker exec -i dhps-stage-wordpress-1 wp plugin delete elementor --allow-root || true
docker cp related-infos/vs-nfd/elementor.3.35.4.zip dhps-stage-wordpress-1:/tmp/
docker exec -i dhps-stage-wordpress-1 wp plugin install /tmp/elementor.3.35.4.zip --activate --allow-root

#    Pro 4.0.1 - falls vorhanden in related-infos/vs-nfd/elementor-pro-4.0.1.zip
#    Falls nicht: Mission-Hinweis im Bericht. Pro 4.0.1 muss vom User bereitgestellt werden
#    ODER (Notfall): Pro deinstallieren und Test ohne Pro durchfuehren (Test-Coverage reduziert, dokumentieren)

# 3. DHPS auf v0.15.5 (letzter Pre-v0.16.0 Stand) zuruecksetzen
docker exec -i dhps-stage-wordpress-1 wp plugin deactivate wp-deubner-hp-services --allow-root || true
docker exec -i dhps-stage-wordpress-1 rm -rf /var/www/html/wp-content/plugins/wp-deubner-hp-services
docker exec -i dhps-stage-wordpress-1 wp plugin install https://github.com/cai-gmbh-dev/wp-deubner-hp-services/archive/refs/tags/v0.15.5.zip --activate --allow-root

# 4. GitHub-Channel auf stable (User-Default)
docker exec -i dhps-stage-wordpress-1 wp option update dhps_update_channel stable --allow-root
docker exec -i dhps-stage-wordpress-1 wp transient delete dhps_github_release --allow-root || true
docker exec -i dhps-stage-wordpress-1 wp transient delete dhps_github_release_beta --allow-root || true

# 5. WP_DEBUG aktivieren falls noch nicht
docker exec -i dhps-stage-wordpress-1 wp config set WP_DEBUG true --raw --allow-root
docker exec -i dhps-stage-wordpress-1 wp config set WP_DEBUG_LOG true --raw --allow-root

# 6. debug.log truncen (sauberer Start)
docker exec -i dhps-stage-wordpress-1 sh -c "> /var/www/html/wp-content/debug.log"

# 7. Pre-State verifizieren
docker exec -i dhps-stage-wordpress-1 wp plugin list --allow-root | grep -E "(elementor|deubner)"
# Erwartung: elementor 3.35.4 active, elementor-pro 4.0.1 active, wp-deubner-hp-services 0.15.5 active
```

### 10.3 Migration Schritt 3.1 -> 3.6 sequentiell durchfuehren

Pro Schritt aus Sektion 3 dieser Doku:
1. Befehl ausfuehren
2. Verifikations-Checkliste aus Sektion 4 durchgehen
3. Befunde notieren (Screenshot wenn Visual)
4. `wp-content/debug.log` taillen
5. Bei Problem: STOP + Bericht erstellen, Lead konsultieren

### 10.4 Bericht-Struktur

```markdown
# Stage-Simulation v0.16.1 Live-Migration

## Pre-State
- Elementor Free: X.Y.Z
- Elementor Pro: X.Y.Z
- DHPS: X.Y.Z
- debug.log Size: N bytes

## Schritt 3.1 (Free 3.35.4 -> 4.1.0)
- Befehl ausgefuehrt: ja
- Exit-Code: 0
- Verifikations-Checkliste: [ alle Items ja/nein ]
- Befunde: ...
- debug.log Diff: ...

## Schritt 3.3 (Pro 4.0.1 -> 4.1.0)
... [analog]

## Schritt 3.5 (DHPS v0.15.5 -> v0.16.1)
... [analog]

## Post-State
- Elementor Free: 4.1.0 (erwartet)
- Elementor Pro: 4.1.0 (erwartet)
- DHPS: 0.16.1 (erwartet)
- Defensive Notice: WEG (erwartet)
- debug.log: clean (erwartet)

## Acceptance T1-T10
| ID | Pass/Fail | Notiz |
| T1 | PASS | ... |
...

## Cleanup
- [ ] /tmp/elementor.3.35.4.zip im Container geloescht
- [ ] /tmp/elementor-pro-4.0.1.zip im Container geloescht
- [ ] test-*.php im Plugin-Dir geloescht
- [ ] debug.log archiviert nach test-results/v0162/stage-debug.log
```

### 10.5 Cleanup nach Stage-Test

```bash
# Test-PHP-Files entfernen (falls erstellt)
docker exec -i dhps-stage-wordpress-1 find /var/www/html/wp-content/plugins/wp-deubner-hp-services -name "test-*.php" -delete

# ZIP-Files aus /tmp loeschen
docker exec -i dhps-stage-wordpress-1 rm -f /tmp/elementor.3.35.4.zip /tmp/elementor-pro-4.0.1.zip

# debug.log archivieren + truncen
docker exec -i dhps-stage-wordpress-1 cat /var/www/html/wp-content/debug.log > test-results/v0162/stage-debug.log
docker exec -i dhps-stage-wordpress-1 sh -c "> /var/www/html/wp-content/debug.log"
```

### 10.6 Gates fuer GO/NO-GO der Live-Migration

- T1-T9 muessen alle **PASS**
- Wenn T7 (Defensive Notice WEG) **FAIL** ist: ROOT-CAUSE-Analyse vor Live-Promotion
- Wenn T9 (debug.log clean) **WARNING** zeigt: Pre-vs-Post-Diff dokumentieren, mit Lead diskutieren
- T10 Cleanup ist Pflicht vor Live-Empfehlung

---

## Anhang A: Glossar

- **Channel**: `dhps_update_channel`-Option, Werte `stable` oder `beta`
- **Defensive Notice**: Admin-Banner ab v0.16.1 wenn Elementor < 4.1.0
- **Atomic Editor (V4)**: Elementor-Editor seit Free 3.35.0 Production-Ready
- **Pro 4.1.0 Recommended Core**: 4.1.x (siehe ELEMENTOR_PRO_RECOMMENDED_CORE_VERSION)
- **Pro 4.1.0 Required Core**: 3.35 (siehe ELEMENTOR_PRO_REQUIRED_CORE_VERSION)
- **GitHub-Updater**: DHPS_GitHub_Updater-Klasse, ueberlaedt WP-Update-Mechanik fuer das Plugin

## Anhang B: Referenzen

- `docs/team-knowledge/05-ELEMENTOR-4X-MIGRATION.md` - Plattform-Stand verifiziert auf 4.1.0
- `docs/team-knowledge/06-RELEASE-WORKFLOW.md` - Klassischer Release-Workflow
- `docs/team-knowledge/07-RELEASE-CHECKLIST.md` - Pre-Release-Gate (v0.16.0+)
- `docs/architecture/09-GITHUB-UPDATER.md` - Updater-Mechanik
- `docs/architecture/25-ELEMENTOR-4_1_0-PLAN-v0161.md` - Discovery-Doc v0.16.1
- `docs/project/44-CHANGELOG-v0161.md` - v0.16.1 Release-Notes
- `includes/class-dhps-elementor.php` Z. 80-160 - Defensive Notice Implementation
- `docker-compose.staging.yml` - Stage-Docker-Compose

---

**Doku-Status:** Discovery + Spec-Briefing, erstellt fuer v0.16.2.
**Naechster Schritt:** P2-Stage-Simulation-Specialist fuehrt die Acceptance-Tests T1-T10 aus.
