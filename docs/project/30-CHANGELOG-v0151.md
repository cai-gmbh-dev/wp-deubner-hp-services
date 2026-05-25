# Changelog v0.15.1 - Tech-Debt-Cleanup

## Stand: 2026-05-25

## Mission

Polish-Release. 7 von 9 Tech-Debt-Tickets aus v0.14.x + v0.15.0
abgearbeitet (2 verschoben auf v0.15.2 wegen Risiko-Hotspots).

**Versionierung-Anmerkung**: Ursprueglich als v0.14.5 geplant (siehe Discovery
und Triage-Plan). Bei der Implementation wurde erkannt dass v0.14.5 nach
v0.15.0 semver-bricht (GitHub-Updater sieht v0.14.5 als alter an als bereits
installierte v0.15.0). Korrigiert auf **v0.15.1** = Patch auf v0.15.0.

## 7 Tickets in v0.15.1

### Ticket #2 - TPT-Modules-Layer

**Problem**: TPT-Templates lasen `get_option('dhps_tpt_*')` direkt im
Template (Architektur-Bruch, dokumentiert seit v0.14.3).

**Loesung**:
- Neue Klasse `DHPS_TPT_Modules` (80 LOC) reichert `$data` um Admin-Texte an
- Neuer generischer Filter `dhps_pipeline_data_{$tag}` im Renderer
  (steht auch kuenftigen Modules-Layern fuer MIO/MMB/etc. zur Verfuegung)
- 3 TPT-Templates lesen jetzt `$data['tpt_config']` statt `get_option`
- 6 get_option-Reads entfernt (2 pro Template)
- BC: Theme-Overrides funktionieren weiter

### Ticket #4 - TC-CSS-Cleanup

**Problem**: `.dhps-tc__empty-icon|title|text` waren seit v0.14.4 totes
CSS-Markup (TC-Templates nutzen jetzt EmptyState-Component mit
`.dhps-empty-state__*` Klassen).

**Loesung**:
- Tote Selektoren entfernt
- BC-Wrapper `.dhps-tc__empty` + `.dhps-tc__empty--compact` bleiben fuer
  Theme-Overrides

### Ticket #5 - TC-Compact-Icon-Modifier

**Problem**: EmptyState-Icon im TC-Compact-Layout war zu gross fuer
Sidebar-Einsatz (dokumentiert seit v0.14.4).

**Loesung**: Neuer CSS-Block in dhps-frontend.css greift auf die
EmptyState-Component-Klasse durch:
```css
.dhps-tc__empty--compact .dhps-empty-state__icon svg {
    width: 28px;
    height: 28px;
}
.dhps-tc__empty--compact .dhps-empty-state__title { font-size: 0.9375rem; }
.dhps-tc__empty--compact .dhps-empty-state__hint  { font-size: 0.8125rem; }
```

### Ticket #6 - aria-controls auf TP-Filter-Buttons

**Problem**: Filter-Buttons in tp/default.php + tp/card.php hatten
`aria-pressed` aber kein `aria-controls` (Screen-Reader-User konnte den
Bezug zur ContentList-Region nicht herstellen).

**Loesung**: `aria-controls="$list_id"` auf allen Filter-Buttons in beiden
Templates. ContentList rendert bereits `id="$list_id"` + `role="region"`.

### Ticket #7 - OTA-Preview Edge-Case (SEC LOW-4.1 GELOEST)

**Problem**: Bei OTA-Werten `<=6` Zeichen lieferte `get_ota_preview()` den
Full-Wert + `...` (z.B. "ABC..."). Length-Detection-Leak.

**Loesung**: `return '***'` bei `<=6` Zeichen (Length-agnostic-Maske).

```php
if ( strlen( $value ) <= 6 ) {
    return '***';
}
return substr( $value, 0, 6 ) . '...';
```

### Ticket #8 + #9 - Rate-Limit-Dokumentation (SEC LOW-3.1/3.2)

**Problem**: Beide Schwaechen waren dokumentiert in v0.15.0-Audit aber
nicht im Code selbst.

**Loesung**: Doc-Block in `DHPS_Admin_REST::check_rate_limit()` dokumentiert
beide Limitierungen mit Worst-Case-Beispielen + Trust-Decision-Begruendung:
- **Sliding-Window-Drift**: 30+30 Requests in 10s moeglich (TTL rollt nicht)
- **Race-Condition Counter-Increment**: ~1-2 Extra-Requests/min bei parallelen Calls

Beide bewusst akzeptiert weil Admin-Tooling (manage_options-User). Verweis
auf `11-SECURITY-AUDIT-v0140.md`.

## VERSCHOBEN auf v0.15.2+ (NICHT in v0.15.1)

| # | Ticket | Grund |
|---|--------|-------|
| 1 | `tp/compact.php` ContentCard-Migration | `initCompactAccordion`-Funktion macht Accordion-Toggle + Player-Spawn (gekoppelt). ContentCard-Migration wuerde 4 Selektor-Anker brechen. JS-Refactor noetig - mehrere h. Risiko-Hotspot. |
| 3 | MMB-card/compact Lazy-Akkordeon | AJAX-Endpoint `render_category_html()` ist auf default-Layout-Partial hardcoded - braucht Layout-Whitelist-Param + 2 neue Partials. Card-Tab-Navigation muss mit Lazy-State-Machine kompatibel werden. Mehrere h. |

Empfehlung: **Beide in einem kombinierten v0.15.2 "Compact-Layouts Lazy-Loading" buendeln** (gemeinsamer JS-Refactor sinnvoll).

## QA + Security

### QA-Specialist Ergebnis

**Verdict**: GO

- 0 Critical, 0 Major, 0 Minor
- 21/21 Acceptance-Checks PASS
- BC vollstaendig erhalten (TPT-Overrides, TC-Wrapper, Renderer-Filter additiv)
- 13/13 Shortcodes Regression OK

Detail: [docs/project/28-QA-REPORT-v0151.md](28-QA-REPORT-v0151.md)

### Security-Audit Ergebnis

**Verdict**: GO

- 0 Critical, 0 High, 0 Medium, 0 Low (alle neu)
- 3 Info-Findings (Forward-Looking-Empfehlungen)
- **LOW-4.1 GELOEST** (OTA-Edge-Case mit `***`-Maske)
- 2 neue Trust-Decisions (#12 generischer Filter-Hook, #13 tpt_config nicht im L2-Cache)

Detail: [docs/project/29-SECURITY-AUDIT-v0151.md](29-SECURITY-AUDIT-v0151.md)

## Backward Compatibility

**Vollstaendig BC**:
- TPT-Theme-Overrides funktionieren weiter (Filter feuert, Daten doppelt verfuegbar)
- TC-CSS-Wrapper-Klassen erhalten (Theme-Overrides auf `.dhps-tc__empty` greifen weiter)
- Renderer-Filter additiv (ohne Subscriber identisches Verhalten)
- 13/13 Frontend-Shortcodes-Regression OK
- 8 Admin-Pages + 5 REST-Endpoints + Dashboard unangetastet
- Keine neuen DB-Tabellen, keine bestehenden Schemas geaendert

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `includes/class-dhps-tpt-modules.php` | TPT-Modules-Layer (80 LOC) |
| `docs/architecture/19-TECH-DEBT-TRIAGE-v0145.md` | Discovery (urspruengl. als v0.14.5 geplant) |
| `docs/project/28-QA-REPORT-v0151.md` | QA-Report |
| `docs/project/29-SECURITY-AUDIT-v0151.md` | Security-Audit |
| `docs/project/30-CHANGELOG-v0151.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.15.0 -> 0.15.1, `new DHPS_TPT_Modules()` in dhps_init() |
| `README.md` | Version-Bump |
| `includes/class-dhps-renderer.php` | Neuer generischer Filter `dhps_pipeline_data_{$tag}` (+14 LOC, additiv) |
| `includes/class-dhps-health-collector.php` | OTA-Edge-Case Fix (`<=6` -> `***`) |
| `includes/class-dhps-admin-rest.php` | Rate-Limit Doc-Block (LOW-3.1 + LOW-3.2) |
| `public/views/services/tpt/default.php` | get_option-Reads -> $data['tpt_config'] |
| `public/views/services/tpt/card.php` | analog |
| `public/views/services/tpt/compact.php` | analog |
| `public/views/services/tp/default.php` | aria-controls auf Filter-Buttons |
| `public/views/services/tp/card.php` | analog |
| `css/dhps-frontend.css` | TC-CSS-Cleanup + Compact-Icon-Modifier |

## Specialist-Team-Pattern (Iteration 7)

| Phase | Specialists |
|-------|-------------|
| P1 Discovery | 1 (Triage der 9 Tech-Debt-Tickets) |
| P2 Implementation | 1 Spec (TPT-Modules-Layer) + Lead (6 Easy-Wins parallel) |
| P3 Composition | Lead (DI + Smoke + Version-Korrektur v0.14.5 -> v0.15.1) |
| P4 QA + Sec | 2 parallel |
| P5 Release | Lead (CHANGELOG + Memory + Tag) |

**Lehre v0.15.1**: Bei Tech-Debt-Releases sind Easy-Wins (CSS-Cleanup, Doc-Blocks,
1-Line-Edits) effizienter direkt vom Lead - Specialist-Overhead nicht
gerechtfertigt. Spec nur fuer das eine nicht-triviale Ticket (TPT-Modules).

## Tech-Debt-Status (kumulativ nach v0.15.1)

### Offen fuer v0.15.2+
| # | Ticket | Quelle |
|---|--------|--------|
| 1 | `tp/compact.php` ContentCard-Migration + JS-Refactor | v0.14.3 |
| 3 | MMB-card/compact Lazy-Akkordeon-Migration | v0.14.0 |

### Optional fuer v0.15.1+ (aus v0.15.0 Discovery)
| # | Ticket |
|---|--------|
| - | Cache-Stats pro Service (BC-Break Cache-Key-Schema) |
| - | Live-Preview pro Service (iframe oder Inline) |
| - | Last-API-Response-Tracking |
| - | Echter Cache-Hit/Miss-Counter |
| - | Health-History (Trend ueber Zeit) |

## Bilanz v0.15.1

- **7 Tech-Debt-Tickets** abgearbeitet (6 Lead-Direct + 1 Spec)
- **2 Tickets verschoben** auf v0.15.2 (kombinierter Compact-Layouts-Release)
- **TPT-Architektur sauber**: keine get_option-Reads mehr in Templates
- **A11y verbessert**: aria-controls auf TP-Filter-Buttons
- **OTA-Edge-Case GELOEST**: Length-Detection nicht mehr moeglich
- **Generischer Renderer-Filter** verfuegbar (kuenftige Module-Layer haben Pattern)
- **0 Critical/High/Medium/Low** Security-Issues neu
- **13/13 Shortcodes** Regression OK
- **Versionierung korrigiert**: v0.14.5 -> v0.15.1 (semver-konform)

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.15.2** | Kombinierter Compact-Layouts-Release (tp/compact + MMB-card/compact) |
| **v0.16.0** | Einheitliches Datenmodell (User-Wunsch seit Anfang) |
| **v0.15.3** | Live-Preview im Admin-Dashboard |
