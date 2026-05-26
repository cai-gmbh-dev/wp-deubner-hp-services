# Changelog v0.15.4 - Live-Preview Tech-Debt-Cleanup

## Stand: 2026-05-26

## Mission

Polish-Release auf v0.15.3 Live-Preview. **8 von 9 dokumentierten
Tech-Debt-Tickets** abgearbeitet (Ticket 7 "Voller Atts-Editor"
verschoben auf v0.15.5+ wegen eigenem Discovery-Bedarf).

## 8 Tickets in v0.15.4

### Ticket 1+4 - Discovery-Sync (Doc)

`docs/architecture/21-LIVE-PREVIEW-PLAN-v0153.md` bekommt **Sync-Notizen v0.15.4**:
- Sektion 9.3: `atts_rejected` ist Object{key:reason} statt array<string>
  (bewusster Schema-Drift, F2 toleriert beides)
- Sektion 9.4: `invalid_endpoint` liefert HTTP 404 statt 500
- Sektion 5.5 (neu): iframe Re-Mount-Pattern `key={service + '-' + html.length}`
  als Standard etabliert

### Ticket 2 - invalid_format Error-Code

`class-dhps-admin-rest.php` Zeile 580-586: `format != 'iframe'` liefert
jetzt eigenen Error-Code `invalid_format` (HTTP 400) statt
`invalid_service` - semantisch korrekt fuer Diagnose.

### Ticket 3 - 500-KB-Soft-Warning

`admin/js/dhps-admin-react.js` LivePreviewMeta-Component: zeigt
Notice mit status='warning' wenn `sizeBytes > 500000`. Mensch-lesbare
Bytes-Anzeige via `formatBytes()`.

### Ticket 5 - postMessage-Resize (Dynamic iframe-Hoehe)

**Backend** (`class-dhps-preview-renderer.php` Zeile 397-439):
- Hartcodiertes JS-Snippet via Nowdoc-HEREDOC `<<<'JS'`
  (keine User-Input-Interpolation moeglich)
- `MAX_HEIGHT = 4000` Cap (DoS-Schutz)
- `lastH`-Dedup verhindert ueberfluessige Messages
- ResizeObserver mit `setInterval(1000)`-Fallback
- `targetOrigin='*'` notwendig (about:srcdoc -> kein klassischer Origin-Check)
- `try/catch` gegen Sandbox-Edge-Cases

**Frontend** (`admin/js/dhps-admin-react.js` LivePreviewIframe Zeile 859-913):
- 3-Layer-Defense:
  1. Strict-Type-Check `event.data.type === 'dhps-preview-resize'`
  2. Numeric-Bounds `parseInt + isNaN + >= 1`
  3. Max-Cap 4000px
- `useEffect`-Cleanup mit `removeEventListener` (Memory-Leak-Schutz)
- Reset auf `PREVIEW_IFRAME_DEFAULT_HEIGHT = 600` bei Preview-Wechsel
- `transition: 'height 200ms ease'` fuer sanfte UX

Worst-Case Threat-Modell: boeswillige Quelle sendet matching
Resize-Message -> iframe-Hoehe max 4000px -> **KEIN XSS, KEIN
Daten-Leak, KEIN DoS**.

### Ticket 6 - 4 Sub-Shortcodes preview-faehig

**Backend** (`class-dhps-admin-rest.php`):
- ALLOWED_SERVICES erweitert um 4 Eintraege: `mio_termine`,
  `maes_videos`, `maes_merkblaetter`, `maes_aktuelles` (jetzt 13 Eintraege)
- REST-Route-Regex von `[a-z]+` auf `[a-z_]+` (3 Routes: health, test, preview)
- SERVICE_PARAM_MAX_LENGTH erhoeht von 16 auf 32 (`maes_merkblaetter` hat 17)

**Renderer** (`class-dhps-preview-renderer.php`):
- Neue `SUB_SHORTCODE_PARENTS` public const Map:
  ```
  mio_termine       => mio
  maes_videos       => maes
  maes_merkblaetter => maes
  maes_aktuelles    => maes
  ```
- Lookup im `handle_service_preview()` fuer Auth/Endpoint
- JS-Asset-Selection via Parent-Slug
- `section`-Att jetzt fuer MAES UND `maes_*` Sub-Shortcodes erlaubt
- Neuer `cache`-Att in Top-Level-Whitelist (boolean via FILTER_VALIDATE_BOOLEAN)

**Frontend** (`admin/js/dhps-admin-react.js`):
- Service-Dropdown erweitert auf 13 Eintraege (9 Haupt + 4 Sub)
- Sub-Eintraege markiert mit "(Sub)" Label-Suffix

**Begrenzungen v0.15.4** (Discovery-Trust-Decision):
- Nur generische Atts (layout/class/section/cache) - Vollparametrisierung
  (z.B. einzelvideo/videoliste/columns fuer maes_videos) ist Ticket 7
  -> v0.15.5+
- Health-Collector kennt Sub-Shortcodes nicht - liefert Null-Record
  fuer `/services/maes_videos/health` (kein Information-Leak, dokumentierter
  Trade-off)

### Ticket 8 - CSP-Doku-Update

`docs/architecture/14-CSP-COMPATIBILITY.md` erweitert um:
- `frame-src 'self' about:` (Live-Preview iframe-srcdoc)
- postMessage-Resize Security-Note
- `about:srcdoc`-Origin-Hinweis

### Ticket 9 - PREVIEW_SERVICES-Konstante (Frontend-Whitelist)

`admin/js/dhps-admin-react.js`: Neue Konstante `PREVIEW_SERVICES` mit
13 Eintraegen (value + label). Zentrale Service-Whitelist statt
hardcoded an mehreren Stellen.

## VERSCHOBEN auf v0.15.5+

**Ticket 7 - Voller Atts-Editor**: Aufwand ~6-14h, profitiert von
einheitlichem Datenmodell (User-Wunsch v1.0). Eigene Discovery
erforderlich mit Schema-Endpoint-Pattern (verhindert Schema-Drift
wie in v0.15.0).

## QA + Security

### QA-Specialist Ergebnis

**Verdict**: GO

- 0 Critical, 0 Major
- 28/28 Acceptance-Checks PASS
- 4 Nice-to-Have-Caveats (alle non-blocking, fuer v0.15.5+):
  - C1: Health-Collector kennt Sub-Shortcodes nicht
  - C2: Doc-Block `invalid_format` nicht explizit in Error-Codes-Liste
  - C3: CSP-Header-Beispiel sollte `frame-src about:` zeigen
  - C4: Frontend/Backend-Service-Liste synchron halten (wp_localize_script-Bridge)
- 13/13 Shortcodes Regression OK
- Sub-Shortcode-Preview funktional verifiziert (mio_termine 4.5KB, maes_videos 30KB)

Detail: [docs/project/37-QA-REPORT-v0154.md](37-QA-REPORT-v0154.md)

### Security-Audit Ergebnis

**Verdict**: GO

- 0 Critical, 0 High, 0 Medium
- 1 Low (akzeptiert als Trust-Decision T8 - postMessage targetOrigin='*')
- REST-Route-Regex-Erweiterung mit **6-Layer Defense-in-Depth**:
  1. Regex `[a-z_]+`
  2. sanitize_key
  3. Laengen-Cap 32
  4. validate_service_param Whitelist-Check
  5. Handler-Re-Check
  6. Registry-Lookup
- postMessage-Resize 3-Layer-Frontend-Defense
- Backend-Snippet via Nowdoc-HEREDOC (keine User-Input-Interpolation)
- Worst-Case-Analyse: kein XSS, kein Daten-Leak, kein DoS moeglich

Detail: [docs/project/38-SECURITY-AUDIT-v0154.md](38-SECURITY-AUDIT-v0154.md)

## Neue Trust-Decisions (kumulativ T1-T9)

| # | Decision | Begruendung |
|---|----------|-------------|
| T1-T5 | (aus v0.15.3 unveraendert) | siehe 36-CHANGELOG-v0153.md |
| T6 | REST-Route-Regex `[a-z_]+` erweitert | 6-Layer Defense-in-Depth, Whitelist-Check greift |
| T7 | SERVICE_PARAM_MAX_LENGTH 16 -> 32 | notwendig fuer `maes_merkblaetter` (17), 88% Reserve |
| T8 | postMessage `targetOrigin='*'` | about:srcdoc -> kein klassischer Origin-Check moeglich; mitigiert via Type-Check + Bounds + Max-Cap |
| T9 | Auth-Lookup via Parent fuer Sub-Shortcodes | Sub-Shortcodes haben keinen eigenen API-Auth; SUB_SHORTCODE_PARENTS-Map isoliert |

## Backward Compatibility

**Vollstaendig BC**:

- Constructor von DHPS_Admin_REST unveraendert (optional Parameter)
- 5 bestehende REST-Routes funktionieren weiter (alte Service-Slugs durch erweiterten Regex)
- Bestehende React-Komponenten unangetastet
- LivePreviewPanel ist additive 3. Sektion (initialOpen=false)
- 13/13 Shortcodes Regression OK
- Keine neuen DB-Tabellen
- alte AJAX-Calls ohne layout-Param liefern default-Partial (v0.15.2 BC)

## Performance

- Sub-Shortcode-Preview: mio_termine 4.5KB / 149ms, maes_videos 30KB / 391ms
- postMessage-Resize: ResizeObserver triggert nur bei tatsaechlicher Aenderung
  (`lastH`-Dedup), setInterval-Fallback nur fuer Browser ohne ResizeObserver
- 500-KB-Warning ist UI-Hint (kein Block)

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/22-TECH-DEBT-TRIAGE-v0154.md` | Discovery + Triage 9 Tickets |
| `docs/project/37-QA-REPORT-v0154.md` | QA-Report |
| `docs/project/38-SECURITY-AUDIT-v0154.md` | Security-Audit |
| `docs/project/39-CHANGELOG-v0154.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.15.3 -> 0.15.4 |
| `README.md` | Version-Bump |
| `includes/class-dhps-admin-rest.php` | Ticket 2 (invalid_format) + Ticket 6 (Regex `[a-z_]+` + ALLOWED_SERVICES +4 + MAX_LENGTH 32) |
| `includes/class-dhps-preview-renderer.php` | Ticket 5 (postMessage-Snippet) + Ticket 6 (SUB_SHORTCODE_PARENTS Map + cache-Att + section-Att fuer maes_*) |
| `admin/js/dhps-admin-react.js` | Ticket 3 (500-KB-Soft-Warning) + Ticket 5 (postMessage-Listener) + Ticket 6 (Service-Dropdown 13 Eintraege) + Ticket 9 (PREVIEW_SERVICES-Konstante) |
| `docs/architecture/14-CSP-COMPATIBILITY.md` | Ticket 8 (frame-src + about:srcdoc + postMessage) |
| `docs/architecture/21-LIVE-PREVIEW-PLAN-v0153.md` | Ticket 1+4 (Sync-Notizen) |

## Specialist-Team-Pattern (Iteration 10)

| Phase | Specialists |
|-------|-------------|
| P1 Discovery | 1 (Triage 9 Tickets, Scope-Festlegung) |
| P2 Implementation | 1 Spec (F1+F2 kombiniert fuer Tickets 5+6) + Lead-Direct (6 Easy-Wins parallel) |
| P3 Composition | Lead (Version-Bump + Smoke 11/11) |
| P4 QA + Sec | 2 parallel |
| P5 Release | Lead |

**Lehre**: Bei Tech-Debt-Releases ist Lead-Direct fuer Easy-Wins
effizienter als Specialist-Spawn. 1 kombinierter F1+F2-Spec fuer
eng-gekoppelte Backend/Frontend-Tickets vermeidet Schema-Drift
(Lehre v0.15.0 bestaetigt). Discovery + Triage spart Implementations-Zeit.

## Tech-Debt-Status (kumulativ nach v0.15.4)

### Offen fuer v0.15.5+
- **Ticket 7** (verschoben): Voller Atts-Editor (Service-spezifische Atts wie einzelvideo/videoliste/columns)
- **C1**: Health-Collector um Sub-Shortcodes erweitern
- **C2**: Doc-Block invalid_format in Error-Codes-Liste
- **C3**: CSP-Header-Beispiel mit frame-src about:
- **C4**: wp_localize_script-Bridge fuer Frontend/Backend-Service-Liste-Sync

### Aus v0.15.0 Discovery (optional)
- Cache-Stats pro Service (BC-Break Cache-Key-Schema)
- Last-API-Response-Tracking
- Echter Cache-Hit/Miss-Counter
- Health-History (Trend ueber Zeit)

## Bilanz v0.15.4

- **8 von 9 Tech-Debt-Tickets aus v0.15.3** abgearbeitet
- **4 Sub-Shortcodes** jetzt preview-faehig (3+1 Pattern: 3 MAES-Subs + 1 MIO-Termine)
- **postMessage-Resize** funktional + sicher (3-Layer-Defense)
- **REST-Route-Regex** erweitert mit 6-Layer Defense-in-Depth
- **0 Critical/High/Medium** Security-Issues
- **A11y-Pattern** aus v0.15.3 unveraendert (14/14 = 100%)
- **13/13 Shortcodes** Regression OK
- **4 neue Trust-Decisions** dokumentiert (T6-T9)
- **CSP-Doku** erweitert um iframe-srcdoc + postMessage

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.15.5** | Ticket 7 (Voller Atts-Editor) + 4 Nice-to-Have-Caveats |
| **v0.16.0** | Einheitliches Datenmodell (User-Wunsch seit Anfang, gross) |
