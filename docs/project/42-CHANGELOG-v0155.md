# Changelog v0.15.5 - Voller Atts-Editor + Tech-Debt-Abschluss

## Stand: 2026-05-26

## Mission

Letzter Tech-Debt-Release der v0.15.x-Serie. **Ticket 7 (Voller Atts-Editor)
aus v0.15.4 abgearbeitet** + 4 Nice-to-Have-Caveats (C1-C4). Schema-Vertrag-
Lehre aus v0.15.0/v0.15.3 strikt umgesetzt - **0 Schema-Drift**.

## Hauptaenderungen

### Ticket 7 - Voller Atts-Editor

Service-spezifische Atts fuer Live-Preview im Admin-Dashboard. Plugin-Admin
kann nun Service-Atts wie `variante`, `anzahl`, `lazy_count`, `breite`
etc. interaktiv setzen.

**Backend** (`class-dhps-preview-renderer.php`):
- Neue `public const SERVICE_ATTS_SCHEMA`: **13 Services / 70 Atts**
  (nur Atts die im Code tatsaechlich unterstuetzt sind, kein Wishlist)
- Neue Methode `validate_att_value()`: Schema-basierte Validation-Pipeline
  fuer 4 Typen (string/int/bool/select)
- Type-Casts: `intval`, `FILTER_VALIDATE_BOOLEAN`
- Bounds-Check fuer int (min/max)
- Options-Whitelist fuer select (strict `in_array`)
- Pattern-Match fuer string (3 hartcodierte Patterns, 0 ReDoS-Risiko)
- Sanitize-Callbacks: `sanitize_html_class`, `sanitize_key`,
  `sanitize_text_field`, `csv_int`
- `is_known_att_anywhere()` fuer differenzierte Reject-Reason

**Backend** (`class-dhps-admin-rest.php`):
- REST-Handler vereinfacht: Top-Level-Atts-Whitelist entfernt, gesamte
  Schema-Validation im Renderer
- Doc-Block aktualisiert um v0.15.5-Pipeline

**Frontend** (`admin/js/dhps-admin-react.js`):
- Neue Komponenten:
  - `LivePreviewAttsForm` (Container, Service-spezifische Atts)
  - `AttFieldString` (TextControl)
  - `AttFieldInt` (TextControl type=number)
  - `AttFieldBool` (ToggleControl)
  - `AttFieldSelect` (SelectControl mit options)
- `getServiceSchema()` + `buildDefaultAtts()` Helper
- Service-Wechsel reset alle Atts auf Schema-Defaults
- Gruppierung nach `group`-Feld (universal vs service_specific)

**Schema-Vertrag** (10 Felder pro Att, Discovery Sektion 3):
- `type`, `default`, `options`, `min`, `max`, `pattern`,
- `sanitize`, `group`, `label`, `description`

**9 Reject-Reasons** als exakte Strings:
- 5 bestehende aus v0.15.4
- 4 neue: `out of bounds (min=N, max=M)`, `invalid type (expected int)`,
  `pattern mismatch`, `not allowed for service`

### Caveat C1 - Health-Collector Sub-Shortcodes

`class-dhps-health-collector.php`:
- SERVICES-Konstante erweitert: 9 -> 13 (4 Sub-Shortcodes ergaenzt)
- `collect_for()`: Parent-Resolution via `SUB_SHORTCODE_PARENTS`
- Neue Felder ADDITIV (BC-sicher):
  - `parent_service` (Parent-Slug, bei Hauptservices identisch mit slug)
  - `is_sub_shortcode` (bool)
- Sub-Shortcode-Auto-Label "(Sub)" Suffix
- Auth-Status + Branding + API-URL geerbt vom Parent

### Caveat C2 - Doc-Block invalid_format

`class-dhps-admin-rest.php::handle_service_preview` Doc-Block enthaelt
jetzt explizit `invalid_format (400, seit v0.15.4)` in Error-Codes-Liste.

### Caveat C3 - CSP-Header-Beispiel

`docs/architecture/14-CSP-COMPATIBILITY.md` ergaenzt um konkretes
CSP-Header-Beispiel mit `frame-src 'self' about:;` fuer Admin-Kontext
mit Live-Preview.

### Caveat C4 - wp_localize_script-Bridge

`Deubner_HP_Services.php`: `wp_localize_script('dhps-admin-react', 'dhpsAdminConfig')`
erweitert um 3 neue Keys:
- `services` = `DHPS_Admin_REST::ALLOWED_SERVICES` (13 Slugs)
- `attsSchema` = `DHPS_Preview_Renderer::SERVICE_ATTS_SCHEMA` (13 / 70 Atts)
- `subShortcodeParents` = `DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS`

**Selbe PHP-Konstante als Backend-Validator UND Frontend-Bridge** ->
Schema-Drift-Risiko = 0.

## QA + Security

### QA-Specialist Ergebnis

**Verdict**: GO

- 0 Critical, 0 Major
- 25/25 Acceptance-Checks PASS
- 3 Minor (alle akzeptiert):
  - O1: `cache`-Att-Typ-Migration bool -> int (T10 Trade-off)
  - O2: `@since 0.15.4`-Tag fuer C2 historisch leicht irrefuehrend
  - O3: `dhpsAdminConfig` statt `dhpsAdminBridge` (BC-Entscheidung)
- 13/13 Shortcodes Regression OK
- **Schema-Vertrag-Einhaltung 10/9/6 STRIKT** (Lehre v0.15.0/v0.15.3 bestaetigt)

Detail: [docs/project/40-QA-REPORT-v0155.md](40-QA-REPORT-v0155.md)

### Security-Audit Ergebnis

**Verdict**: GO

- 0 Critical, 0 High, 0 Medium
- 1 Low (akzeptiert): Exception-Message in `preview_render_failed`
  kann Server-Path leaken (Admin-only-Endpoint, kein Leak an non-Admins)
- **9-Layer-Defense-in-Depth** fuer Atts-Validation
- **0 ReDoS-Risiken** (3 Patterns linear + Hard-Cap)
- 3 neue Trust-Decisions (T10-T12)

Detail: [docs/project/41-SECURITY-AUDIT-v0155.md](41-SECURITY-AUDIT-v0155.md)

## 3 Neue Trust-Decisions (kumulativ T1-T12)

| # | Decision | Begruendung |
|---|----------|-------------|
| T1-T9 | (aus v0.15.3 + v0.15.4) | siehe Vor-CHANGELOGs |
| T10 | SERVICE_ATTS_SCHEMA als Single-Source-of-Truth | PHP-Konstante als Backend-Validator + Frontend-Bridge (via wp_localize_script) |
| T11 | wp_localize_script-Bridge exposed Schema | Admin-only (manage_options), keine Secrets, keine OTAs |
| T12 | Health-Collector Sub-Shortcode-Parent-Resolution | Auth-Lookup via Map, Sub-Shortcodes erben Parent-Status |

## Backward Compatibility

**Vollstaendig BC**:
- Generische Atts (layout/class/section/cache) unveraendert
- Service-spezifische Atts strikt validiert (unbekannte Keys ->
  `atts_rejected` mit Reject-Reason)
- Existing layout/class/section/cache funktioniert weiter
- Bestehende 5 REST-Endpoints unangetastet
- 13/13 Shortcodes Regression OK
- 4 existing React-Komponenten (App, ServiceHealthList, ServiceHealthCard,
  CacheStatsPanel, LivePreviewPanel/Controls/Iframe/Meta) unangetastet
- LivePreviewAttsForm ist ADDITIV (BC-sicher)
- Health-Collector liefert `parent_service` + `is_sub_shortcode` zusaetzlich
  (alte Frontend-Versionen ignorieren defensiv)

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/23-ATTS-EDITOR-PLAN-v0155.md` | Discovery + Schema-Vertrag |
| `docs/project/40-QA-REPORT-v0155.md` | QA-Report |
| `docs/project/41-SECURITY-AUDIT-v0155.md` | Security-Audit |
| `docs/project/42-CHANGELOG-v0155.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.15.4 -> 0.15.5, wp_localize_script-Bridge +3 Keys (C4) |
| `README.md` | Version-Bump |
| `includes/class-dhps-preview-renderer.php` | SERVICE_ATTS_SCHEMA (13/70), validate_att_value(), is_known_att_anywhere(), render()-Pipeline-Umbau |
| `includes/class-dhps-admin-rest.php` | Atts-Whitelist entfernt, Doc-Block invalid_format (C2) |
| `includes/class-dhps-health-collector.php` | SERVICES 9->13, collect_for() Parent-Resolution, parent_service+is_sub_shortcode-Felder (C1) |
| `admin/js/dhps-admin-react.js` | 5 neue Komponenten (LivePreviewAttsForm + 4 AttField*), getServiceSchema+buildDefaultAtts Helper |
| `docs/architecture/14-CSP-COMPATIBILITY.md` | Admin-Kontext CSP-Header-Beispiel mit frame-src 'self' about: (C3) |

## Specialist-Team-Pattern (Iteration 11)

| Phase | Specialists |
|-------|-------------|
| P1 Discovery | 1 (Triage Ticket 7 + 4 Caveats, Schema-Vertrag, Spec-Aufteilung) |
| P2 Implementation | 1 Spec F12 kombiniert (Backend + Frontend + C1) + Lead-Direct (C2 + C3 + C4-Bridge) |
| P3 Composition | Lead (Version-Bump + Smoke 13/13 + Functional-Tests) |
| P4 QA + Sec | 2 parallel |
| P5 Release | Lead |

**Lehre v0.15.5** (Bestaetigung von v0.15.3/v0.15.4):

Bei eng-gekoppelten Backend+Frontend-Aenderungen ist 1 kombinierter F12-Spec
mit Pflicht-Schema-Vertrag effizienter als 2 parallele Specs. v0.15.0 hatte
3 Critical-Schema-Drift-Fixes erfordert; v0.15.3, v0.15.4 und v0.15.5 alle
**0 Critical-Schema-Drift-Fixes** dank Schema-Vertrag-Vorgehen + kombinierter
F12-Spec-Strategie.

## v0.15.x ABSCHLUSS

Mit v0.15.5 ist die v0.15.x-Reihe **komplett**:

- **v0.15.0**: Backend-Admin-Dashboard (5 REST-Endpoints, 4 React-Komponenten)
- **v0.15.1**: Tech-Debt-Cleanup (7 von 9 v0.14.x-Tickets)
- **v0.15.2**: Compact-Layouts Lazy-Loading (letzte 2 v0.14.x-Tickets)
- **v0.15.3**: Live-Preview im Admin-Dashboard (Schema-Vertrag-Vorgehen etabliert)
- **v0.15.4**: Tech-Debt-Cleanup (8 von 9 v0.15.3-Tickets + Sub-Shortcodes)
- **v0.15.5**: Voller Atts-Editor + 4 Caveats (alle v0.15.4-Tickets geloest)

**v0.15.x-Bilanz**:
- 18 Tech-Debt-Tickets abgearbeitet (alle v0.14.x + alle v0.15.3 + alle v0.15.4 Caveats)
- 12 Trust-Decisions dokumentiert
- 0 Critical-Schema-Drift-Fixes (Lehre etabliert!)
- 6 REST-Endpoints im Admin-Dashboard (+1 Preview)
- 8+ React-Komponenten im Admin-Dashboard
- A11y 14/14 = 100% (durchgaengig)

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.16.0** | **Einheitliches Datenmodell** (User-Wunsch seit Anfang, gross) |
| **v0.15.6** | Optional: weitere Polish (4 Nice-to-Have aus v0.15.4 sind alle in v0.15.5 erledigt) |

## Bilanz v0.15.5

- **Voller Atts-Editor** funktional (13 Services / 70 Atts via Schema)
- **Health-Collector Sub-Shortcodes** mit Parent-Resolution
- **wp_localize_script-Bridge** verhindert Schema-Drift
- **9-Layer-Defense-in-Depth** Atts-Validation
- **0 ReDoS-Risiken** (alle Patterns linear + Hard-Cap)
- **0 Critical-Schema-Drift-Fixes** (Lehre 3x bestaetigt)
- **A11y unangetastet** (LivePreviewAttsForm folgt Pattern)
- **13/13 Shortcodes** Regression OK
- **v0.15.x-Reihe komplett abgeschlossen**
