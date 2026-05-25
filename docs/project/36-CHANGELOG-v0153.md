# Changelog v0.15.3 - Live-Preview im Admin-Dashboard

## Stand: 2026-05-25

## Mission

Erweitert das in v0.15.0 eingefuehrte React-Admin-Dashboard um eine
Live-Preview-Funktion: Plugin-Admin kann pro Service in Echtzeit sehen,
wie der Service auf der Frontend-Seite gerendert wird - ohne separate
Test-Seite anlegen zu muessen.

## Hauptaenderungen

### 1. Neuer REST-Endpoint POST /dhps/v1/services/{service}/preview

- Rate-Limit: 30/min/User (eigener Bucket `preview`)
- Permission: `manage_options`
- Whitelist: 9 Haupt-Services (mio, lxmio, mmb, mil, tp, tpt, tc, maes, lp)
- Atts-Whitelist: `layout`, `class`, `section` (nur fuer MAES)

### 2. Neue Klasse DHPS_Preview_Renderer (~270 LOC)

Wrapped das do_shortcode()-Output in ein vollstaendiges HTML-Document mit:
- Frontend-CSS-Links (dhps-design-tokens, dhps-base, dhps-frontend, dhps-components)
- Service-spezifisches JS (mio/mmb/tp je nach Service)
- Alpine.js Vendor + Init + Components-Alpine

Damit funktioniert das iframe als komplett gerenderte Frontend-Seite.

### 3. 4 neue React-Komponenten in admin/js/dhps-admin-react.js (+375 LOC)

- **LivePreviewPanel** (Container, State, runPreview)
- **LivePreviewControls** (Service-Dropdown + Atts-Form mit Layout/Class/Section)
- **LivePreviewIframe** (srcdoc + sandbox + fixed 600px Hoehe)
- **LivePreviewMeta** (Render-Time, Bytes, Cache-Hit, Shortcode, Atts-Applied)

App-Component erweitert: LivePreviewPanel als 3. Sektion (initialOpen=false).

### 4. iframe-Sandbox + srcdoc-Strategie (Discovery Option A)

- `sandbox="allow-same-origin allow-scripts"` (minimal eingeschraenkt)
- KEINE allow-popups/forms/top-navigation/modals/pointer-lock
- `srcdoc={html}` statt `src=URL` (keine zusaetzliche Public-URL noetig)
- `key={service + '-' + html.length}` erzwingt iframe-Re-Mount bei neuem Render
- Fixed iframe-Hoehe 600px (dynamic Resize via postMessage in v0.15.4)

### 5. Schema-Vertrag-Vorgehen (Drift-Schutz Lehre v0.15.0)

Discovery-Plan Sektion 9 definierte EXPLIZIT alle 10 Response-Felder
und 5 Error-Codes vorab. Schema-Vertrag war Pflichtbestandteil beider
Specialist-Briefings. Plus F2 hat defensives Reading (Belt-and-Suspenders).

**Resultat (QA bestaetigt)**: 0 Schema-Drift-Critical-Fixes noetig
(verglichen mit v0.15.0 wo 3 Critical-Bugs durch Schema-Mismatch
entstanden waren). **Schema-Vertrag-Vorgehen erfolgreich.**

## Schema-Vertrag (autoritativ)

### Request
```json
POST /dhps/v1/services/{service}/preview
Body: {
  "atts": { "layout": "card", "class": "...", "section": "..." },
  "format": "iframe"
}
```

### Response 200 (10 Felder, EXAKT)
```json
{
  "service": "mio",
  "format": "iframe",
  "html": "<!DOCTYPE html>...",
  "size_bytes": 4567,
  "render_time_ms": 234,
  "shortcode": "[mio layout=\"card\"]",
  "atts_applied": { "layout": "card" },
  "atts_rejected": { "invalid_key": "..." },
  "api_cache_hit": false,
  "rendered_at": 1716548400
}
```

### Error-Codes
- `invalid_service` (400)
- `service_not_configured` (400)
- `invalid_endpoint` (404)
- `rate_limit_exceeded` (429)
- `preview_render_failed` (500)

## QA + Security

### QA-Specialist Ergebnis

**Verdict**: GO

- 0 Critical, 0 Major
- 3 Minor (alle dokumentarische Diskrepanzen ohne Funktions-Impact):
  - M1: `atts_rejected` als Object{key:reason} statt array<string> (F2 toleriert beides via Array.isArray-Check)
  - M2: `invalid_endpoint` liefert HTTP 404 statt 500
  - M3: `format != iframe` wird als `invalid_service` statt `invalid_format` gemeldet
- 28/28 Acceptance-Checks PASS
- **A11y 14/14 = 100%** (WCAG 2.1 AA)
- 13/13 Shortcodes Regression OK
- **Schema-Drift-Schutz erfolgreich** (0 Fixes statt 3 wie in v0.15.0)

Detail: [docs/project/34-QA-REPORT-v0153.md](34-QA-REPORT-v0153.md)

### Security-Audit Ergebnis

**Verdict**: GO

- 0 Critical, 0 High
- 3 Medium (non-blocking, alle akzeptiert):
  - M1: Atts-Keys-DoS-Surface (gering)
  - M2: Schema-Drift atts_rejected Map vs Array (Frontend toleriert)
  - M3: Trust-Decisions im CHANGELOG dokumentieren
- 4 Low (CSP-Hinweise, OTA-Documentation, Error-Message-Polish, Frontend-Slug-Whitelist)
- iframe-Sandbox sicher konfiguriert (minimal eingeschraenkt, T3 Trust-Decision dokumentiert)
- **XSS-Vektor im Preview-Renderer ausgeschlossen** (dreischichtig: Service-Whitelist + Atts-Whitelist + esc_*)
- 5 Trust-Decisions dokumentiert

Detail: [docs/project/35-SECURITY-AUDIT-v0153.md](35-SECURITY-AUDIT-v0153.md)

## Trust-Decisions (akzeptiert)

| # | Decision | Begruendung |
|---|----------|-------------|
| T1 | `do_shortcode()` Output wird ohne erneute Escape ins iframe-HTML geschrieben | DHPS-Parser-Output ist via wp_kses_post bereits gefiltert (v0.14.0 Defense-in-Depth) |
| T2 | OTA-URL-Leak im iframe-HTML akzeptiert | Admin sieht OTA ohnehin via Options-Page |
| T3 | iframe-sandbox `allow-same-origin allow-scripts` (W3C-Schwaeche) | HTML ist Plugin-eigen + manage_options-only User |
| T4 | atts_rejected als Object statt array<string> | F2 toleriert beides, semantisch reicher (Grund pro Ablehnung) |
| T5 | Fixed iframe-Hoehe 600px | postMessage-Resize verschoben auf v0.15.4 |

## Backward Compatibility

**Vollstaendig BC**:

- Constructor von DHPS_Admin_REST hat optional `?DHPS_Preview_Renderer = null`
  Parameter - bestehende Aufrufe brechen NICHT
- 5 bestehende REST-Routes funktionieren weiter
- 4 existing React-Komponenten (App, ServiceHealthList, ServiceHealthCard,
  CacheStatsPanel) unangetastet
- LivePreviewPanel ist additive 3. Sektion (initialOpen=false)
- 13/13 Shortcodes Regression OK
- Keine neuen DB-Tabellen

## Performance

- Preview-Renderer Smoke (TC): 158 ms, 2.397 bytes HTML
- iframe-Bundle: HTML-Document + 4 CSS-Files + 4 JS-Files
- Typische Preview: 30-150 KB HTML-Document
- 60s Transient-Cache fuer HEAD-Probes (v0.15.0)

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `includes/class-dhps-preview-renderer.php` | HTML-Document-Wrapper (~270 LOC) |
| `docs/architecture/21-LIVE-PREVIEW-PLAN-v0153.md` | Discovery + Schema-Vertrag |
| `docs/project/34-QA-REPORT-v0153.md` | QA-Report |
| `docs/project/35-SECURITY-AUDIT-v0153.md` | Security-Audit |
| `docs/project/36-CHANGELOG-v0153.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.15.2 -> 0.15.3, DI `$preview_renderer` -> DHPS_Admin_REST |
| `README.md` | Version-Bump |
| `includes/class-dhps-admin-rest.php` | Neue Route `/preview` + handle_service_preview() + Constructor-Erweiterung (optional Parameter, BC) |
| `admin/js/dhps-admin-react.js` | +375 LOC (4 neue React-Komponenten + App-Erweiterung) |

## Specialist-Team-Pattern (Iteration 9)

| Phase | Specialists |
|-------|-------------|
| P1 Discovery | 1 (Strategie A/B/C, Schema-Vertrag, Spec-Aufteilung) |
| P2 Implementation | 2 parallel (F1 Backend + F2 Frontend, mit Pflicht-Schema-Vertrag) |
| P3 Composition | Lead (DI + Version-Bump + Smoke 7/7) |
| P4 QA + Sec | 2 parallel |
| P5 Release | Lead |

**Lehre v0.15.3** (Bestaetigung der v0.15.0-Lehre):

Schema-Vertrag-Vorgehen vor parallelem F1/F2-Spec-Start verhindert
Schema-Drift erfolgreich. Discovery-Sektion 9 als Pflichtbestandteil
beider Briefings + defensives Reading im Frontend = **0 Critical-Fixes
in v0.15.3** statt 3 wie in v0.15.0. **Pattern etabliert** und in
Memory + Discovery-Vorlagen aufnehmen.

## Tech-Debt fuer v0.15.4

| # | Ticket | Quelle |
|---|--------|--------|
| 1 | Discovery 9.3/9.4 mit Implementation synchronisieren (atts_rejected, HTTP-Status) | QA M1+M2 |
| 2 | Eigener Error-Code `invalid_format` | QA M3 |
| 3 | 500-KB-Soft-Warning im Meta-Panel | Discovery R1 |
| 4 | iframe Re-Mount-Key via `html.length` als Standard-Pattern dokumentieren | F2 Innovation |
| 5 | Dynamic iframe-Resize via postMessage | Discovery T5 |
| 6 | 4 Sub-Shortcodes preview-faehig (mio_termine, maes_videos, maes_merkblaetter, maes_aktuelles) | Discovery Scope |
| 7 | Voller Atts-Editor (mehr als nur layout/class/section) | Discovery Scope |
| 8 | CSP-Hinweis fuer Plugin-Doku | SEC Low |
| 9 | Frontend-Service-Slug-Whitelist als Konstante (statt Hardcode in 9 Stellen) | SEC Low |

## Bilanz v0.15.3

- **Live-Preview-Feature** vollstaendig integriert (Backend + Frontend)
- **9 Services** preview-faehig im Admin-Dashboard
- **iframe-Sandbox** sicher konfiguriert (3-schichtiger XSS-Schutz)
- **Schema-Vertrag-Vorgehen** validiert (0 Critical-Fixes statt 3 in v0.15.0)
- **A11y 14/14 = 100%** WCAG 2.1 AA
- **0 Critical/High** in beiden Audits
- **13/13 Shortcodes** Regression OK
- **5 Trust-Decisions** dokumentiert
- **9 Tech-Debt-Tickets** fuer v0.15.4

## Naechste Schritte

- **v0.15.4**: Sub-Shortcodes preview-faehig + Tech-Debt + postMessage-Resize
- **v0.16.0**: Einheitliches Datenmodell (User-Wunsch seit Anfang)
