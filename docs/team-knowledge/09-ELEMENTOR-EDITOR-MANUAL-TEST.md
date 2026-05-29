# Elementor Editor - Manueller Sichtbarkeits-Test

## Stand: 2026-05-29 (v0.16.3)

## Zweck

Code-Smoke-Tests verifizieren die **Backend-Registrierung** der 13 DHPS-Widgets
(`elementor/widgets/register`-Hook + Klassen-Existenz + Render ohne Fatal).
Die **UI-Sichtbarkeit** im Elementor-Editor laesst sich aber nicht headless
testen - V4 Atomic-Editor kann klassische Widgets unter neuer UI-Sektion
zeigen, ausserdem sind Drag-and-Drop + Settings-Panels rein visuelle Tests.

Diese Doku ist die **manuelle Pflicht-Checkliste** vor jedem Pre-Release wenn
Elementor-Code beruehrt wurde.

## Voraussetzungen

- Stage-Stack hochgefahren (`docker compose -p dhps-stage -f docker-compose.staging.yml up -d`)
- Stage erreichbar auf `http://localhost:8086`
- Login: `admin` / `stage_pass`
- Elementor Free + Pro 4.1.0+ aktiv
- DHPS Plugin aktiv (Version egal, ab v0.13.0)
- Test-Page vorhanden (siehe unten "Setup")

## Setup einer Test-Page

Falls noch keine Editor-Test-Page existiert:

```bash
docker exec dhps-stage-wordpress-1 wp post create \
    --post_type=page \
    --post_title='DHPS Elementor-Editor Test' \
    --post_status=publish \
    --post_content='<p>Editor-Test-Ziel fuer DHPS-Widgets.</p>' \
    --allow-root --path=/var/www/html
```

Liefert Page-ID, z.B. `9`. URL zum Editor:

```
http://localhost:8086/wp-admin/post.php?post=9&action=elementor
```

## Test-Checkliste (T1-T15)

### Setup

- [ ] **T1** Browser oeffnen auf Editor-URL der Test-Page
  Erwartet: Elementor-Editor laedt ohne Fehler, linkes Panel sichtbar

- [ ] **T2** Browser-DevTools-Console oeffnen
  Erwartet: keine roten JS-Errors waehrend Editor-Load (Warnings OK)

### Widget-Panel-Sichtbarkeit

- [ ] **T3** Im linken Panel oben Suchfeld -> "Deubner" eingeben
  Erwartet: mindestens **9 Hauptservice-Widgets** in den Ergebnissen
  (MIO, LXMIO, MMB, MIL, TP, TPT, TC, MAES, LP)
  Plus: Steuertermine + 3 MAES-Sub-Widgets (Videos, Merkblaetter, Aktuelles)

- [ ] **T4** Suchfeld zuruecksetzen -> Kategorie "Deubner Services" oeffnen
  Erwartet: alle 13 Widgets in der Kategorie gruppiert

- [ ] **T5** Jedes Widget hat das `eicon-globe`-Icon (Default des Plugins)

### Widget-Platzierung

- [ ] **T6** MIO-Widget per Drag-and-Drop in den leeren Canvas-Bereich ziehen
  Erwartet: Widget rendert, kein PHP-Notice unten in der Statuszeile

- [ ] **T7** Settings-Panel des Widgets erscheint links
  Erwartet: alle Settings-Tabs verfuegbar (Inhalt, Stil, Erweitert)

- [ ] **T8** Widget-Settings durchklicken (Layout, Variante, Anzahl)
  Erwartet: Live-Preview rechts aktualisiert sich bei jeder Aenderung

### Hauptservice-Smoke

Fuer jedes der 9 Hauptservice-Widgets:

- [ ] **T9.1** MIO platzieren -> rendert Empty-State oder echte Daten
- [ ] **T9.2** LXMIO platzieren -> Recht-Blau-Branding sichtbar (Empty-State akzeptabel weil OTA leer)
- [ ] **T9.3** MMB platzieren -> Merkblaetter-Liste (oder Empty-State)
- [ ] **T9.4** MIL platzieren -> Mandanten-Infoletter
- [ ] **T9.5** TP platzieren -> TaxPlain-Videos
- [ ] **T9.6** TPT platzieren -> TaxPlain-Teaser (1 Video)
- [ ] **T9.7** TC platzieren -> Steuerrechner-Akkordeon
- [ ] **T9.8** MAES platzieren -> Medizin-Teal-Branding
- [ ] **T9.9** LP platzieren -> Recht-Blau-Branding (LexPlain-Videos)

### Sub-Widget-Smoke

- [ ] **T10** Steuertermine-Widget platzieren -> Termine-Tabelle (oder Empty-State)
- [ ] **T11** MAES Videos / Merkblaetter / Aktuelles separat platzieren -> jeweils eigener Render

### Speichern + Frontend

- [ ] **T12** "Aktualisieren" oder "Veroeffentlichen" oben rechts klicken
  Erwartet: erfolgreich gespeichert, kein 500/PHP-Fatal

- [ ] **T13** "Vorschau" oeffnen -> Frontend-View laedt
  Erwartet: alle Widgets rendern wie im Editor

- [ ] **T14** Frontend ohne Elementor-Editor anzeigen (Inkognito-Tab oder anderer Browser)
  Erwartet: gleiche Darstellung, keine Editor-spezifischen Artifakte sichtbar

### Atomic-Editor-V4-spezifisch

- [ ] **T15** Falls V4 Atomic-Editor verfuegbar (rechte obere Ecke "Mit V4 bearbeiten" o.ae.):
  Klassische DHPS-Widgets sollten weiterhin **sichtbar** sein, auch wenn ggf.
  in einer separaten UI-Sektion ("Klassische Widgets" / "Legacy")

## Bewertung

| Ergebnis | Bedeutung | Aktion |
|----------|-----------|--------|
| T1-T14 alle OK | Plugin voll kompatibel mit Editor | Release Stable promoten |
| T3 oder T4 NOK (Widgets fehlen) | Hook-Registrierung gebrochen | Bug-Fix Pflicht, kein Release |
| T6 oder T8 NOK (Drag/Settings) | Widget-Klassen-Bug | Bug-Fix Pflicht |
| T15 NOK | V4 Atomic-UI versteckt Widgets | Tech-Debt fuer Atomic-Migration |
| Einzelne T9.x NOK (Empty-State Erwartung) | Service-spezifisch, evtl. OTA fehlt | OTA pruefen, dann Bewertung |

## Token-Bridge zusaetzlich pruefen

Wenn auf der Test-Site die Bridge aktiv ist (`dhps_elementor_bridge_enabled = 1`):

```bash
docker exec dhps-stage-wordpress-1 wp dhps elementor-tokens --allow-root --path=/var/www/html
```

Erwartet: "Bridge aktiv: JA" - dann sind `--dhps-color-*`-Tokens auf
`--e-global-*` gemapped und Elementor-Atomic-Container koennen die DHPS-
Brand-Farben via Global-Style nutzen.

## Visuelle Stage-Marker (v0.16.3)

Beim Login auf der Stage erscheinen 3 Marker als sichtbares Erfolgs-Signal
"ich bin nicht auf Live":

1. **Roter Admin-Bar** statt schwarz/grau
2. **"[ STAGE ]"-Praefix** vor dem Site-Namen in Admin-Bar
3. **"[ STAGE ]"-Praefix** im Browser-Tab-Title
4. **Hellroter Banner** unter dem Admin-Bar mit Warntext

Wenn diese Marker fehlen: pruefen ob `docker-compose.staging.yml` das
mu-plugin-Mount enthaelt und `DHPS_ENV_LABEL = 'STAGE'` definiert ist.

## Bezug zu Discovery-Hypothesen v0.16.1

| Hypothese | Manueller Test deckt ab |
|-----------|--------------------------|
| H1 Free/Pro Versions-Mismatch | wird durch v0.16.1 Defensive Notice abgedeckt, nicht hier |
| H2 V4 Atomic versteckt klassische Widgets | T15 (direkter Sichtbarkeits-Test) |
| H3 Token-Bridge bricht Atomic-Tokens | T9.x Branding-Verifikation + `wp dhps elementor-tokens` |

## Cleanup

Test-Page nach Test loeschen (oder behalten als Smoke-Vorlage):

```bash
docker exec dhps-stage-wordpress-1 wp post delete 9 --force --allow-root --path=/var/www/html
```
