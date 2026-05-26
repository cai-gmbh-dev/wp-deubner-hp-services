# Release-Checkliste (Release-Gate seit v0.16.0)

Diese Checkliste implementiert das Release-Gate-Verfahren aus
`docs/architecture/24-DEV-STRECKE-PLAN-v0160.md` Sektion 2.4 / 3.4.

Ablauf in 4 Phasen:

```
Pre-Release-Kriterien  ->  Stage-Test  ->  Promotion  ->  (bei Bedarf) Rollback
```

Tag-Format-Vertrag (Trust-Decision T14, Pflicht):

```
Stable:      v{major}.{minor}.{patch}              z.B. v0.16.0
Pre-Release: v{major}.{minor}.{patch}-rc.{n}       z.B. v0.16.0-rc.1
             v{major}.{minor}.{patch}-beta.{n}     z.B. v0.16.0-beta.1
```

Wichtig: Der Suffix MUSS einen Punkt enthalten (`-rc.1`, NICHT `-rc1`).
PHPs `version_compare` sortiert sonst falsch (siehe Risiko R1 in der Discovery).

---

## 1. Pre-Release-Kriterien

Bevor `gh release create v0.X.Y-rc.N --prerelease` ausgefuehrt wird, muessen
alle folgenden Punkte erfuellt sein:

- [ ] Lokal Plugin-Aktivierung ohne PHP-Fatal in Dev-Stack (`docker compose up -d`, `http://localhost:8082/wp-admin/plugins.php`)
- [ ] Alle 9 Hauptservice-Shortcodes rendern auf einer Testseite ohne Fehler:
  - [ ] `[mio]`
  - [ ] `[lxmio]`
  - [ ] `[mmb]`
  - [ ] `[mil]`
  - [ ] `[tp]`
  - [ ] `[tpt]`
  - [ ] `[tc]`
  - [ ] `[maes]`
  - [ ] `[lp]`
- [ ] Alle 4 Sub-Shortcodes rendern (sofern relevant):
  - [ ] `[mio_termine]`
  - [ ] `[maes_videos]`
  - [ ] `[maes_merkblaetter]`
  - [ ] `[maes_aktuelles]`
- [ ] Version-Bump in 3 Stellen vollzogen (`Deubner_HP_Services.php`):
  - [ ] Plugin-Header `Version: X.Y.Z`
  - [ ] PHPDoc `@version X.Y.Z`
  - [ ] Konstante `DEUBNER_HP_SERVICES_VERSION`
- [ ] `README.md` Version-Zeile aktualisiert
- [ ] Aenderungs-Notes als Markdown-Datei vorbereitet (fuer `gh release create --notes-file`)
- [ ] Tag-Format-Vertrag eingehalten: Pre-Release-Suffix mit Punkt (`v0.X.Y-rc.N`, nicht `v0.X.Y-rcN`)
- [ ] `dhps_update_channel = beta` ist auf der Stage-Site bereits gesetzt
  (sonst sieht die Stage das Pre-Release nicht)

---

## 2. Stage-Test-Schritte

Nachdem das Pre-Release auf GitHub sichtbar ist, fuehrt der Stage-Admin folgende
Schritte auf `http://localhost:8086` durch:

- [ ] Stage-Stack ist hochgefahren:
  ```bash
  docker compose -p dhps-stage -f docker-compose.staging.yml up -d
  ```
- [ ] WP-Admin -> Aktualisierungen -> "Erneut pruefen" klicken
  (oder alternativ max. 3h Cache-TTL abwarten - `DHPS_GitHub_Updater::$cache_ttl`)
- [ ] Update-Banner erscheint mit korrekter Version (z.B. `0.16.0-rc.1`)
- [ ] "Aktualisieren" klicken
- [ ] Plugin-Entpacken laeuft ohne Fehler durch (kein `fix_directory_name`-Problem,
  kein PHP-Fatal, Plugin bleibt aktiv)
- [ ] Admin-Dashboard oeffnen -> Service-Health-Monitor zeigt alle Services
- [ ] Live-Preview-Endpoint (seit v0.15.3) ausprobieren: ein Shortcode laesst sich
  in der Vorschau rendern
- [ ] Voller Atts-Editor (seit v0.15.5) zeigt alle Schema-Felder pro Service
- [ ] Alle 9 Service-Shortcodes auf einer Testseite anzeigen (siehe Liste oben)
- [ ] DB-State pruefen (via phpMyAdmin auf `http://localhost:8087`):
  - [ ] `dhps_update_channel = beta`
  - [ ] Alle bisherigen `dhps_*`-Options unveraendert
  - [ ] Keine neuen unerwarteten Options-Eintraege
- [ ] Browser-Konsole zeigt keine JS-Fehler
- [ ] `wp-content/debug.log` (Container-Pfad `/var/www/html/wp-content/debug.log`)
  zeigt keine neuen PHP-Notices/Warnings

### Workaround: ZIP-Test ohne GitHub-Release (Risiko R3)

Lokal vor dem Pre-Release laesst sich die Update-Pipeline simulieren, ohne ein
echtes GitHub-Release schneiden zu muessen:

```bash
# 1. ZIP aus aktuellem HEAD bauen (gleicher Inhalt wie GitHub-zipball_url)
git archive HEAD -o test.zip --prefix=wp-deubner-hp-services/

# 2. Im WP-Admin auf der Stage:
#    Plugins -> Installieren -> Plugin hochladen -> test.zip waehlen -> Installieren
#    -> Bei "Plugin existiert bereits" auf "Aktuelle durch hochgeladene ersetzen" klicken

# 3. Plugin entpackt -> kein fix_directory_name-Aufruf noetig (Prefix matched)
#    -> Aktiv bleiben, Smoke-Tests laufen weiter
```

---

## 3. Promotion-Kriterien

Bevor ein Pre-Release zu Stable promoted wird, muessen alle folgenden Punkte
erfuellt sein:

- [ ] Stage-Smoke-Tests alle gruen (Sektion 2)
- [ ] Mindestens 24h Beobachtungs-Zeit auf der Stage-Site ohne Folgefehler
  (oder kuerzer mit expliziter Begruendung in den Release-Notes)
- [ ] Keine offenen Critical/High-Issues im aktuellen Sprint
- [ ] Doku-Updates committet (CHANGELOG, MEMORY, betroffene `docs/`-Dateien)

Aktion fuer Promotion (Trust-Decision: NEUEN Tag schneiden, NICHT Pre-Release-Tag editieren):

```bash
# Empfohlen: neuer Tag fuer Stable
gh release create v0.X.Y --notes-file release-notes-v0.X.Y.md --latest

# NICHT empfohlen (Risiko R7): Pre-Release-Tag in-place promoten
# gh release edit v0.X.Y-rc.N --prerelease=false --latest=true
# Begruendung: Wenn der Pre-Release-Tag bestehen bleibt, koennen Beta-Sites
# das Stable als "schon installiert" sehen. NEUER Tag mit gleichem Code-Stand
# ist sauberer.
```

Nach Promotion:

- [ ] Stable-Channel-Sites bekommen das Update innerhalb max. 3h (Cache-TTL)
- [ ] Beobachtungs-Phase auf Live-Sites weitere 24h
- [ ] Pre-Release-Tag liegen lassen (sichtbare Historie der Beta-Phase)

---

## 4. Rollback-Strategie

Wenn ein Stable-Release in der Praxis bricht (PHP-Fatal, Daten-Korruption,
Funktions-Regression), gibt es DREI Optionen:

### Option A (Standard): Hotfix-Patch

Vorherige Version re-publizieren funktioniert NICHT - der Updater filtert sie
via `version_compare( $latest, $current, '>' )` weg.

Stattdessen Hotfix-Patch mit Revert schneiden:

```bash
# 1. Auf main den fehlerhaften Commit reverten
git revert <bad-commit-sha>

# 2. Version bumpen v0.X.Y -> v0.X.Y+1 (siehe Pre-Release-Kriterien)

# 3. Direkt Stable (kein Pre-Release noetig bei Hotfix - Trust-Decision Risiko R8)
gh release create v0.X.Y+1 --notes "Hotfix: Revert of bad-commit-sha"

# 4. Falls Beta-Channel-Sites parallel den Hotfix brauchen, zusaetzlich:
gh release create v0.X.Y+1-rc.1 --prerelease --notes "Hotfix mirror for beta"
```

### Option B (Notfall): Manuelle Wiederherstellung

Wenn der Hotfix nicht schnell genug fertig wird:

```bash
# 1. Im WP-Admin: Plugins -> Deubner HP Services -> Deaktivieren
# 2. Per FTP/SSH:
ssh user@live-site
cd /pfad/zu/wp-content/plugins/
rm -rf wp-deubner-hp-services
# 3. Alten Stand aus Backup zurueckspielen ODER:
git clone --branch v0.X.Y-1 https://github.com/cai-gmbh-dev/wp-deubner-hp-services
# 4. Im WP-Admin: Plugin wieder aktivieren
```

### Option C: Pre-Release-Tag-Cleanup

Wenn nur ein Pre-Release zurueckgezogen werden muss (kein Promotion erfolgt):

```bash
# Release auf GitHub loeschen (Tag bleibt erhalten als Sicht-Schutz)
gh release delete v0.X.Y-rc.N

# Optional auch den Git-Tag entfernen
git tag -d v0.X.Y-rc.N
git push --delete origin v0.X.Y-rc.N
```

---

## 5. Schreibrechte-Hinweis (Risiko R5)

Auf Windows-Hosts kann das WP-Update auf der Stage-Site an Permissions
scheitern, weil das Plugin-Verzeichnis von der Host-Seite ueber den
Docker-Bind-Mount gehalten wird und der Container-User `www-data` ggf.
keine Schreibrechte hat.

Symptome:
- "Aktualisieren" laeuft scheinbar durch, aber Plugin-Dateien sind unveraendert
- WordPress meldet "Could not move files"

Workaround:

```bash
# Im Container Schreibrechte fuer www-data setzen
docker exec dhps-stage-wordpress-1 \
  chown -R www-data:www-data /var/www/html/wp-content/plugins/wp-deubner-hp-services

# Alternativ: Plugin-Mount kurz auf chmod 777 setzen (NICHT in Produktion)
docker exec dhps-stage-wordpress-1 \
  chmod -R 777 /var/www/html/wp-content/plugins/wp-deubner-hp-services
```

Hinweis: Der Container-Name `dhps-stage-wordpress-1` ergibt sich aus dem
Compose-Project-Name `dhps-stage` (siehe Start-Befehl in
`docker-compose.staging.yml`). Konkreten Namen mit `docker ps` verifizieren.

---

## 6. Referenzen

- `docs/architecture/24-DEV-STRECKE-PLAN-v0160.md` - Discovery + Schema-Vertrag
- `docs/team-knowledge/06-RELEASE-WORKFLOW.md` - Klassischer Release-Workflow
- `docs/team-knowledge/01-ENTWICKLUNGSUMGEBUNG.md` - Dev + Stage Docker-Setup
- `docs/architecture/09-GITHUB-UPDATER.md` - Updater-Mechanik
- `docker-compose.staging.yml` - Stage-Docker-Compose
