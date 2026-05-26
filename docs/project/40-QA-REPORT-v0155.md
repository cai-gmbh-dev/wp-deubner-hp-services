# QA-Report v0.15.5 (Voller Atts-Editor + Tech-Debt-Abschluss)

## Stand: 2026-05-26
## QA-Lead: QA-Specialist (parallel zu Security-Audit)
## Spec-Quelle: docs/architecture/23-ATTS-EDITOR-PLAN-v0155.md
## Handover: .specialist-F12-v0155-handover.md
## Verdict: **GO**

---

## Executive Summary

v0.15.5 liefert Ticket 7 (Voller Atts-Editor) + die 4 Caveats C1-C4 aus dem
v0.15.4-Backlog. Die kombinierte Specialist-Implementation F12 (Backend +
Frontend in einem Spec, Lehre v0.15.4) hat ZERO Schema-Drift produziert.

Die kritische Lehre aus v0.15.0/v0.15.3 (Schema-Vertrag MUSS Pflicht-Bestand-
teil JEDES Specs sein) wurde EXAKT eingehalten:

- **10/10 Schema-Felder** pro Att-Eintrag (type/default/options/min/max/pattern/sanitize/group/label/description) ohne Aliases.
- **9/9 Reject-Reasons** als exakte Strings im Code.
- **6/6 Error-Codes** unveraendert (kein Bruch der REST-API).
- **13/13 Services** im Schema (9 Haupt + 4 Sub).
- **70/70 Att-Eintraege** ausschliesslich aus echtem Shortcode-Code (keine
  Wishlist).
- **wp_localize_script-Bridge** liefert 3 neue Keys (services / attsSchema /
  subShortcodeParents) zusaetzlich zur bestehenden Konfig.
- **5/5 React-Komponenten** (LivePreviewAttsForm + 4 Field-Komponenten) BC-
  sicher additiv neben unveraendertem LivePreviewControls.
- **Health-Collector C1** liefert parent_service + is_sub_shortcode additiv
  fuer alle 13 Services.

Critical: 0. Major: 0. Minor/Observation: 3 (siehe Sektion 8).

---

## Sektion 1: Schema-Vertrag-Einhaltung (kritisch)

### 1.1 10 Felder pro Att-Eintrag (Pflicht/Optional)

Die Konstante `DHPS_Preview_Renderer::SERVICE_ATTS_SCHEMA` (Zeile 139-841 in
`includes/class-dhps-preview-renderer.php`) implementiert den Schema-Vertrag
aus Discovery Sektion 3.1 EXAKT:

| Feld | Status | Beobachtung |
|------|--------|-------------|
| `type` | OK | Alle 70 Eintraege haben `type` (string/int/bool/select). |
| `default` | OK | Alle 70 Eintraege haben `default` (scalar, niemals null). |
| `options` | OK | Nur bei `type=select` gesetzt. Immer Array von Objekten `{value, label}` - KEINE flachen String-Arrays. |
| `min` | OK | Bei ALLEN `type=int`-Eintraegen gesetzt (cache, columns, count, einzelvideo, lazy_count). |
| `max` | OK | Bei ALLEN `type=int`-Eintraegen gesetzt. |
| `pattern` | OK | Genutzt bei `class` (`^[a-zA-Z0-9_\\- ]{0,64}$`), `videoliste` (`^[0-9,]{0,128}$`), `id_merkblatt` (`^[0-9]{0,12}$`). |
| `sanitize` | OK | Genutzt mit den 4 Bezeichnern aus dem Vertrag: `text_field`, `html_class`, `key`, `csv_int`. |
| `group` | OK | Alle 70 Eintraege haben `group=universal` ODER `group=service_specific`. |
| `label` | OK | Praktisch ueberall gesetzt (deutsche UI-Texte). |
| `description` | OK | Optional verwendet (selten - akzeptabel laut Vertrag). |

**KEINE Aliases im Code verwendet** (geprueft: keine `kind`, `dflt`, `def`,
`vals`, `choices`, `bounds`-Begriffe).

### 1.2 9 Reject-Reasons als exakte Strings

`validate_att_value()` und `render()` produzieren EXAKT die 9 Reject-Strings
aus dem Vertrag (Discovery Sektion 3.3). Code-Stellen in
`includes/class-dhps-preview-renderer.php`:

| Reject-Reason | Code-Location | Status |
|---------------|---------------|--------|
| `value not in whitelist` | validate_att_value() case select Zeile 1095 | OK |
| `invalid html-class chars` | validate_att_value() case string Zeile 1037 | OK |
| `only allowed for maes` | render() Sonderfall section Zeile 910 | OK |
| `value not boolean (0\|1)` | validate_att_value() case bool Zeile 1080 | OK |
| `unknown att key` | render() Schema-Lookup Zeile 918-920 + Defense Zeile 1101 | OK |
| `out of bounds (min=N, max=M)` | validate_att_value() case int Zeile 1072 (sprintf) | OK |
| `invalid type (expected int)` | validate_att_value() case int Zeile 1064 | OK |
| `pattern mismatch` | validate_att_value() Zeile 1023, 1049, 1056 | OK |
| `not allowed for service` | render() ueber is_known_att_anywhere() Zeile 919 | OK |

Alle Strings sind hartcodiert in den genannten Zeilen, keine i18n-Wrapper
(diese Strings sind interne Debug-Diagnostik, nicht UI-Text).

### 1.3 6 Error-Codes unveraendert

`DHPS_Admin_REST::handle_service_preview()` (Doc-Block Zeile 529-535, Code
Zeile 550-690 in `includes/class-dhps-admin-rest.php`) listet exakt 6
Error-Codes:

| Code | HTTP | Status | Code-Location |
|------|------|--------|---------------|
| `invalid_service` | 400 | OK | Zeile 575, 589 |
| `service_not_configured` | 400 | OK | Zeile 601 |
| `invalid_endpoint` | 404 | OK | Zeile 609 |
| `invalid_format` | 400 | OK | Zeile 627 (seit v0.15.4) |
| `rate_limit_exceeded` | 429 | OK | Zeile 564 |
| `preview_render_failed` | 500 | OK | Zeile 555, 661, 670 |

KEINE neuen REST-Error-Codes in v0.15.5 (Atts-Reject ist non-fatal, landet
in `atts_rejected`).

### 1.4 Verdict Schema-Vertrag

**PASS** - 10/10 Felder, 9/9 Reject-Reasons, 6/6 Error-Codes. Keine
Aliases. Lehre v0.15.0/v0.15.3 strikt eingehalten.

---

## Sektion 2: SERVICE_ATTS_SCHEMA Inhalt

### 2.1 13 Service-Slugs

Top-Level-Keys in SERVICE_ATTS_SCHEMA (geprueft visuell + grep):

1. mio (Zeile 144)
2. lxmio (Zeile 220)
3. mmb (Zeile 296)
4. mil (Zeile 344)
5. tp (Zeile 392)
6. tpt (Zeile 451)
7. tc (Zeile 497)
8. maes (Zeile 530)
9. lp (Zeile 575)
10. mio_termine (Zeile 643)
11. maes_videos (Zeile 696)
12. maes_merkblaetter (Zeile 771)
13. maes_aktuelles (Zeile 804)

**13/13 OK**. Identisch zu `DHPS_Admin_REST::ALLOWED_SERVICES` und
`DHPS_Health_Collector::SERVICES`.

### 2.2 70 Atts total (Verteilung pro Service)

Grep-Count `^\s+'(teasermodus|filter|...|cache)'\s+=>` liefert exakt 70
Vorkommen.

| Service | service_specific | universal | Total | Erwartet (Handover) |
|---------|------------------|-----------|-------|---------------------|
| mio | 5 | 3 | 8 | 8 OK |
| lxmio | 5 | 3 | 8 | 8 OK |
| mmb | 2 | 3 | 5 | 5 OK |
| mil | 2 | 3 | 5 | 5 OK |
| tp | 3 | 3 | 6 | 6 OK |
| tpt | 1 | 3 | 4 | 4 OK |
| tc | 0 | 3 | 3 | 3 OK |
| maes | 1 | 3 | 4 | 4 OK |
| lp | 4 | 3 | 7 | 7 OK |
| mio_termine | 2 | 3 | 5 | 5 OK |
| maes_videos | 5 | 3 | 8 | 8 OK |
| maes_merkblaetter | 0 | 3 | 3 | 3 OK |
| maes_aktuelles | 1 | 3 | 4 | 4 OK |
| **GESAMT** | **31** | **39** | **70** | **70 OK** |

### 2.3 Nur Atts die im Code existieren (keine Wishlist)

Stichproben gegen den Discovery-Plan Sektion 1.3 ("Abweichungs-Notiz"):

- MIO: enthaelt teasermodus/filter/variante/modus/st_kategorie. KEINE
  Wishlist-Atts (count/kategorie/start_date/end_date). OK
- MMB: enthaelt id_merkblatt/rubrik. KEINE Wishlist (kategorie/suche). OK
- TP: enthaelt teasermodus/einzelvideo/videoliste. KEINE Wishlist
  (kategorie/featured_id/video_mode). OK
- TPT: enthaelt modus. KEINE Wishlist (teaser_id/breite). OK
- mio_termine: enthaelt count/month. KEINE Wishlist (monat/jahr). OK
- maes_aktuelles: enthaelt columns. KEINE Wishlist (count/show_teaser). OK
- maes_merkblaetter: keine service_specific. KEINE Wishlist
  (kategorie/count). OK

Disziplin gegen User-Briefing-Wishlist eingehalten. Aufnahme erst nach
Shortcode-Handler-Erweiterung in v0.16.0 (akzeptierte Trade-off T10).

### 2.4 Generische Atts konsistent

Universal-Atts (`layout`, `class`, `cache`) sind in allen 13 Services
strukturell IDENTISCH:

- `layout`: select, default='default', options=[default/card/compact],
  group=universal. Ausnahme: mio_termine hat zusaetzlich `inline` (BC mit
  v0.10.x Steuertermine-Layout).
- `class`: string, default='', sanitize=html_class, pattern=
  `^[a-zA-Z0-9_\\- ]{0,64}$`, group=universal.
- `cache`: int, default=3600, min=0, max=86400, group=universal.

Sub-Shortcode-spezifisch: `section`-Att existiert NUR fuer maes (Hauptservice),
nicht fuer maes_videos/maes_merkblaetter/maes_aktuelles (das sind direkte
Section-Aliase). Korrekt modelliert.

### 2.5 Verdict SERVICE_ATTS_SCHEMA

**PASS** - 13/13 Services, 70/70 Atts, Wishlist-Disziplin, generische Atts
konsistent.

---

## Sektion 3: Atts-Validation-Pipeline

### 3.1 validate_att_value() - 4 Typen

Implementation in `class-dhps-preview-renderer.php` Zeile 1003-1102. Pruefe
Typ-Handling:

| Typ | Handling | Status |
|-----|----------|--------|
| `string` | Sanitize (text_field/html_class/key/csv_int) + optional pattern. Multi-Class-Support fuer html_class via Token-Split. Leerer Wert OK. | OK |
| `int` | is_numeric-Check, dann (int)-Cast, dann min/max-Bounds. Sprintf-Format fuer Reject-Reason. | OK |
| `bool` | filter_var(FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE), explizites Mapping auf '0'/'1' (kein true/false-Leak). | OK |
| `select` | in_array gegen def.options[*].value (strict ===). | OK |

### 3.2 Sanitize-Callbacks

| Sanitize-Hint | PHP-Callback | Korrekt |
|---------------|--------------|---------|
| `text_field` | `sanitize_text_field()` | OK Zeile 1043 |
| `html_class` | `sanitize_html_class()` (per Token nach Whitespace-Split) | OK Zeile 1031 |
| `key` | `sanitize_key()` | OK Zeile 1045 |
| `csv_int` | Inline-Regex `^[0-9,]{0,128}$` | OK Zeile 1048 |

### 3.3 Bounds + Whitelist + Pattern

- **Bounds-Check fuer int**: Zeile 1067-1074 - `min` ueber `$def['min']` mit
  Fallback `PHP_INT_MIN`, `max` analog. Strict `<`/`>` Vergleich. OK
- **Options-Whitelist fuer select**: Zeile 1085-1094 - strikte `in_array`
  Vergleich gegen def.options[*].value. OK
- **Pattern-Match fuer string**: Zeile 1020-1024 (in html_class-Branch) und
  Zeile 1053-1057 (generischer Pattern-Check nach Sanitize). Delimiter
  `/.../` werden vom Renderer hinzugefuegt - im Schema KEINE Delimiter
  speichern (entspricht Handover-Sektion 8). OK

### 3.4 atts_rejected mit korrekten Reject-Reasons

Stichproben gegen Handover-Sektion 7.2 Smoke-Test (Erwartung vs
Implementation):

| Test | Erwartung | Code-Pfad | Status |
|------|-----------|-----------|--------|
| count=200 (mio_termine) | "out of bounds (min=0, max=50)" | Zeile 1072 sprintf | OK |
| videoliste="1;2;3" | "pattern mismatch" | Zeile 1049 (csv_int) oder Zeile 1056 | OK |
| foobar=x | "unknown att key" | Zeile 920 (Schema fehlt + nicht-anywhere) | OK |
| einzelvideo=5 fuer mmb | "not allowed for service" | Zeile 919 (is_known_att_anywhere=true) | OK |
| lazy_mode=turbo | "value not in whitelist" | Zeile 1095 | OK |
| columns=foo | "invalid type (expected int)" | Zeile 1064 | OK |
| section=videos fuer mio | "only allowed for maes" | Zeile 910 | OK |

Note: Lead-Smoke hat das Functional confirmed: `atts_rejected={"nonexistent_att":"unknown att key"}`.

### 3.5 REST-Handler-Vereinfachung

`handle_service_preview()` Zeile 633-654 - die alte Top-Level-Whitelist
(`$known_top_keys = array('layout','class','section','cache')`) ist ENTFERNT.
Statt dessen nur Layer-4-Defense:

1. sanitize_key auf Att-Key (a-z0-9_).
2. is_scalar-Wall (Anti-XSS, blockt Array/Object).
3. Durchreichung als `$sanitized_atts` an `$preview_renderer->render()`.

Die volle Schema-Validation (type/bounds/options/pattern/sanitize) liegt
ausschliesslich im Renderer. Saubere SoC-Trennung.

### 3.6 Verdict Atts-Validation-Pipeline

**PASS** - 4 Typen vollstaendig, alle 4 Sanitize-Callbacks korrekt, Bounds/
Options/Pattern vollstaendig, atts_rejected exakte Reason-Strings.

---

## Sektion 4: Health-Collector C1

### 4.1 SERVICES-Konstante erweitert

`DHPS_Health_Collector::SERVICES` (Zeile 43-48 in
`includes/class-dhps-health-collector.php`) enthaelt jetzt 13 Eintraege:

```
mio, lxmio, mmb, mil, tp, tpt, tc, maes, lp,
mio_termine, maes_videos, maes_merkblaetter, maes_aktuelles
```

Lead-Smoke confirmed: `Health-Collector SERVICES: 13 (mit Sub-Shortcodes)`.
OK.

### 4.2 collect_for() Parent-Resolution

Zeile 136-194:

- Sub-Shortcode-Resolution via `DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS`
  Lookup (Zeile 139-147).
- Alle internen Lookups (label/ota_set/available/api_url) gegen
  `$parent_slug` (Zeile 155-158).
- Defensive `class_exists`-Check fuer Loading-Reihenfolge.

Lead-Smoke confirmed: `maes_videos` -> `parent_service=maes`,
`is_sub_shortcode=true`, `branding=medizin`. OK.

### 4.3 parent_service + is_sub_shortcode-Felder additiv

Zeile 176-193 - das Health-Record-Schema enthaelt JETZT zusaetzlich:

- `parent_service` (string, NEU)
- `is_sub_shortcode` (bool, NEU)

Bestehende Felder (slug/name/ota_set/ota_configured/ota_preview/branding/
available/api_reachable/...) unveraendert. ADDITIV - BC-sicher.

### 4.4 Sub-Shortcode-Label-Suffix "(Sub)"

Zeile 161-171:

```
mio_termine        -> "MIO Termine (Sub)"
maes_videos        -> "MAES Videos (Sub)"
maes_merkblaetter  -> "MAES Merkblaetter (Sub)"
maes_aktuelles     -> "MAES Aktuelles (Sub)"
```

OK.

### 4.5 Branding-Lookup ueber Parent

Zeile 187 `'branding' => $this->get_branding( $parent_slug )` - korrekt
gegen Parent. Lead-Smoke confirmed: `branding=medizin` fuer `maes_videos`.

### 4.6 Verdict Health-Collector C1

**PASS** - 4 Sub-Shortcodes integriert, Parent-Resolution funktioniert,
2 neue Felder additiv (BC), Label-Suffix korrekt, Branding ueber Parent.

---

## Sektion 5: React Atts-Editor (5 Komponenten)

### 5.1 LivePreviewAttsForm Container

`admin/js/dhps-admin-react.js` Zeile 927-978:

- Liest Schema via `getServiceSchema(service)` aus
  `window.dhpsAdminConfig.attsSchema`.
- Filtert auf `group === 'service_specific'` (Zeile 935-938).
- Rendert `null` wenn keine service_specific-Atts (Zeile 940 - tc,
  maes_merkblaetter).
- Visuelle Gruppe: Flex-Container, Border, Background-Fade (Zeile 952-960).
- Header: `"Service-spezifische Atts ({service})"` (Zeile 971).

OK.

### 5.2 AttFieldString (TextControl)

Zeile 775-789:
- Mappt def.label, def.description -> TextControl props.
- Reicht Roh-String an `onChange(name, val)`.
- aria-label fuer Accessibility (Zeile 787).

OK.

### 5.3 AttFieldInt (TextControl type=number)

Zeile 799-825:
- TextControl mit `type: 'number'`, HTML-min/max-Attribute.
- Range-Hint im Label `"Spalten (1..4)"`.
- Reicht String an Backend (PHP castet via (int)).

OK.

### 5.4 AttFieldBool (ToggleControl)

Zeile 835-852:
- ToggleControl mit defensiver Read-Logik (akzeptiert true/false/'1'/'0'/1/0).
- onChange mapped EXPLIZIT auf `'0'`/`'1'` (Risiko R10 Discovery aufgeloest).

OK.

### 5.5 AttFieldSelect (SelectControl)

Zeile 862-879:
- SelectControl mit options aus Schema (Array von {value, label}-Objekten).
- Default-Fallback wenn props.value undefined.

OK.

### 5.6 Service-Wechsel-Reset auf Defaults

`LivePreviewPanel` Zeile 1336-1339:

```js
var onServiceChange = useCallback( function ( newService ) {
    setService( newService );
    setAtts( buildDefaultAtts( newService ) );
}, [] );
```

`buildDefaultAtts(service)` (Zeile 752-764) baut aus dem Schema die
Default-Werte. Plus BC-Fallback fuer fehlendes layout/class. OK.

### 5.7 Gruppierung nach group-Feld

LivePreviewAttsForm filtert NUR `group === 'service_specific'` (Zeile 937).
Die `universal`-Atts (layout/class/section/cache) bleiben in `LivePreviewControls`
(Zeile 1053-1078).

Sauberer Cut.

### 5.8 BC: LivePreviewControls bleibt unangetastet

LivePreviewControls (Zeile 991-1110) behaelt:
- Service-Dropdown (Zeile 1053)
- Layout-Dropdown (Zeile 1062)
- CSS-Class TextControl (Zeile 1071)
- MAES-Section-Dropdown (Zeile 1009, conditional)
- Sub-Shortcode-Badge (Zeile 1021, conditional)
- Run-Button (Zeile 1082)

NEU additiv:
- Reset-Button (Zeile 1095, tertiary)
- LivePreviewAttsForm-Mount (Zeile 1104)

KEINE Aenderungen an Layout-Liste, Section-Liste oder Visual-Style. BC-sicher.

### 5.9 Verdict React Atts-Editor

**PASS** - 5/5 Komponenten implementiert, Service-Wechsel-Reset funktional,
Gruppierung korrekt, LivePreviewControls BC-sicher.

---

## Sektion 6: wp_localize_script-Bridge

### 6.1 3 neue Keys

`Deubner_HP_Services.php` Zeile 841-853:

```php
wp_localize_script(
    'dhps-admin-react',
    'dhpsAdminConfig',
    array(
        'restUrl'    => esc_url_raw( rest_url( 'dhps/v1/' ) ),
        'restNonce'  => wp_create_nonce( 'wp_rest' ),
        'i18nDomain' => 'deubner_hp_services',
        // Seit v0.15.5 (Caveat C4 + Ticket 7):
        'services'             => DHPS_Admin_REST::ALLOWED_SERVICES,
        'attsSchema'           => DHPS_Preview_Renderer::SERVICE_ATTS_SCHEMA,
        'subShortcodeParents'  => DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS,
    )
);
```

3/3 neue Keys vorhanden:
- `services` (13 Service-Slugs)
- `attsSchema` (komplettes 13x Atts-Schema)
- `subShortcodeParents` (4 Sub->Parent Mappings)

Lead-Smoke confirmed: `wp_localize_script-Bridge: 3/3 keys`.

### 6.2 JSON-encoded

`wp_localize_script()` ruft intern `wp_json_encode()`. PHP-Konstanten sind
beide `public const` (geprueft in `class-dhps-preview-renderer.php`
Zeile 84 + 139), kein Visibility-Fix noetig.

### 6.3 React liest aus window.dhpsAdminConfig

`admin/js/dhps-admin-react.js`:
- `getServiceSchema()` Zeile 728-738 liest aus `window.dhpsAdminConfig.attsSchema`.
- Fallback auf `{}` wenn Bridge fehlt.
- REST-Nonce + URL: Zeile 1529-1533 ueber gleiche `dhpsAdminConfig` (BC).

Konsistent.

### 6.4 BC: bestehende Keys unveraendert

Die 3 Keys aus v0.15.4 (`restUrl`, `restNonce`, `i18nDomain`) bleiben am
gleichen Var-Namen und gleicher Struktur. KEINE Umbenennung des
JS-Objects (z.B. NICHT `dhpsAdminBridge` wie im Discovery skizziert,
sondern bewusst `dhpsAdminConfig` wie v0.15.4).

Diese Diff vom Discovery ist im Handover Sektion 3 dokumentiert und nicht
problematisch (BC-Schutz).

### 6.5 Verdict wp_localize_script-Bridge

**PASS** - 3/3 neue Keys, JSON-encoded automatisch, React liest defensiv,
bestehende Keys unangetastet.

---

## Sektion 7: Caveats C2 + C3

### 7.1 Caveat C2 - Doc-Block invalid_format

`DHPS_Admin_REST::handle_service_preview()` Doc-Block in
`class-dhps-admin-rest.php` Zeile 529-535:

```
Error-Codes (6 total):
  - invalid_service         (400)
  - service_not_configured  (400)
  - invalid_endpoint        (404)
  - invalid_format          (400, seit v0.15.4) - format != 'iframe'
  - rate_limit_exceeded     (429)
  - preview_render_failed   (500)
```

`invalid_format` ist EXPLIZIT in der Liste (vorher nur als Inline-Kommentar
Zeile 624 zu finden). OK.

@since-Tag-Eintrag in Zeile 543:
`@since 0.15.4 invalid_format Error-Code dokumentiert (QA-Caveat C2).`

Achtung Minor M1 (Observation): Im @since-Tag wird C2 als v0.15.4-Caveat
attribuiert, faktisch wurde der Doc-Block-Eintrag aber in v0.15.5 nachgezogen
(da v0.15.4 ihn nur als Inline-Kommentar fuehrte - siehe Discovery-Plan
Sektion 7.2). Akzeptabel als historische Notiz. KEIN Bug.

### 7.2 Caveat C3 - CSP-Doku frame-src 'self' about:

`docs/architecture/14-CSP-COMPATIBILITY.md`:

- Zeile 52: Section-Header `### \`frame-src 'self' about:\` (nur Admin-Dashboard, seit v0.15.3)`
- Zeile 93-109: Admin-CSP-Beispiel-Block mit `frame-src 'self' about:;` (Zeile 106).
- Zeile 111-113: Erklaerung warum about: noetig (DevTools-Console-Error).
- Zeile 113: `Caveat C3 v0.15.4 GELOEST in v0.15.5.` - explizit referenziert.

OK.

### 7.3 Verdict Caveats C2 + C3

**PASS** - C2 Doc-Block enthaelt invalid_format. C3 CSP-Doku enthaelt
frame-src 'self' about: mit Erklaerung.

---

## Sektion 8: BC + Regression

### 8.1 13/13 Shortcode-Regression

Lead-Smoke confirmed: `13/13 Shortcode-Regression`. Frontend-Pfad ist
unangetastet:
- `class-dhps-shortcodes.php` (generischer Handler) - keine Aenderungen
- `class-dhps-maes-modules.php` (3 Sub-Shortcode-Handler) - keine Aenderungen
- `class-dhps-steuertermine.php` (mio_termine-Handler) - keine Aenderungen
- `class-dhps-service-registry.php` (shortcode_atts-Defaults) - keine Aenderungen

Die SERVICE_ATTS_SCHEMA-Konstante ist NUR fuer Preview-Renderer + Frontend-
Editor relevant - die Shortcode-Handler im Frontend nutzen weiter ihre
nativen `shortcode_atts()`-Calls.

### 8.2 REST-API-Vertrag (10 Felder)

Response-Struktur in `handle_service_preview()` Zeile 677-688 enthaelt
EXAKT die 10 Felder aus dem v0.15.3-Vertrag:

```
service, format, html, size_bytes, render_time_ms, shortcode,
atts_applied, atts_rejected, api_cache_hit, rendered_at
```

KEINE Aenderung am Top-Level-Response-Schema. Sub-Object `atts_rejected`
enthaelt jetzt 9 moegliche Reject-Reason-Strings statt der 4 aus v0.15.4
(neue: out of bounds / invalid type / pattern mismatch / not allowed for
service). Das ist additive String-Vielfalt im flexiblen Sub-Object - kein
Vertragsbruch.

### 8.3 Atts-Validation BC

`layout`-Att: Validation gegen SCHEMA[$service]['layout'].options - identisch
zu v0.15.4 ALLOWED_LAYOUTS (default/card/compact, plus inline fuer
mio_termine).

`class`-Att: Validation via sanitize_html_class + pattern. v0.15.4 hat
nur sanitize_html_class strikt geprueft. v0.15.5 erlaubt Multi-Class
("foo bar") via Token-Split (siehe Renderer Zeile 1028-1039). Eine kleine
BC-Erweiterung (NICHT-Bruch).

`section`-Att: Sonderfall Zeile 909-912 reproduziert v0.15.4-Verhalten
("only allowed for maes"). Bei MAES-Family laeuft section ueber regulaeren
select-Pfad mit options [''/videos/merkblaetter/aktuelles]. BC korrekt.

`cache`-Att: Migration von type=bool (v0.15.4) zu type=int (v0.15.5). Im
Discovery Plan + Handover Sektion 9 O2 explizit dokumentiert. Schema-
Vertrag setzt jetzt cache als TTL in Sekunden (0..86400). Acceptable
Trade-off T10.

### 8.4 Verdict BC + Regression

**PASS** - 13/13 Shortcodes, REST-Vertrag intakt, Atts-Validation
BC-konform. 1 dokumentierte (akzeptierte) BC-Erweiterung: cache als int
statt bool (siehe O2).

---

## Sektion 9: Beobachtungen / Minor (3 Items)

### O1 - cache-Att Typ-Migration (bool -> int)

`cache`-Att wurde vom Typ `bool` (v0.15.4) auf `int` (v0.15.5, 0..86400)
geaendert (Schema-Vertrag konsistent mit TTL-Semantik des Shortcode-Handlers).

**Risiko**: Externe Clients die `cache="true"` senden, bekommen
`atts_rejected.cache = "invalid type (expected int)"`.

**Mitigation**: 
1. React-Frontend sendet ohnehin numerische Strings (AttFieldInt).
2. Akzeptabler Trade-off T10 im Discovery Plan.
3. Erwaegung fuer v0.16: zusaetzlich Bool-Tolerance via filter_var
   FALLBACK (z.B. `true/false` -> 1/0 cast).

**Schweregrad**: Minor (Observation). Dokumentiert in Handover-Sektion 9 O2.

### O2 - @since-Tag-Attribution C2 (Doc-Block)

`@since 0.15.4 invalid_format Error-Code dokumentiert (QA-Caveat C2).`
ist historisch leicht irrefuehrend - die explizite Liste wurde erst in
v0.15.5 nachgezogen.

**Schweregrad**: Minor (Dokumentations-Detail). KEIN funktionaler Bug.

### O3 - dhpsAdminBridge vs dhpsAdminConfig Naming

Discovery-Plan-Skizze (Sektion 2.4) verwendete `dhpsAdminBridge` als
JS-Object-Namen. Implementation verwendet `dhpsAdminConfig` (BC mit
v0.15.4-Frontend).

**Schweregrad**: Minor (Naming). Im Handover-Sektion 3 explizit
dokumentiert ("gleicher Var-Name wie bisher"). KEIN Bug, sondern bewusste
BC-Entscheidung.

---

## Acceptance Checklist

| # | Check | Status |
|---|-------|--------|
| AC1 | SERVICE_ATTS_SCHEMA hat exakt 13 Top-Level-Keys | OK |
| AC2 | Alle 70 Att-Eintraege haben type/default/group (Pflicht) | OK |
| AC3 | Alle type=select-Eintraege haben options als Array von {value, label}-Objekten | OK |
| AC4 | Alle type=int-Eintraege haben min UND max | OK |
| AC5 | wp_localize_script exposed services + attsSchema + subShortcodeParents | OK |
| AC6 | 9 Reject-Reason-Strings exakt wie spezifiziert | OK |
| AC7 | 6 Error-Codes unveraendert | OK |
| AC8 | Health-Collector SERVICES enthaelt 4 Sub-Shortcodes | OK |
| AC9 | Health-Record liefert parent_service + is_sub_shortcode additiv | OK |
| AC10 | Sub-Shortcode-Label "(Sub)"-Suffix | OK |
| AC11 | LivePreviewAttsForm + 4 Field-Komponenten implementiert | OK |
| AC12 | Service-Wechsel-Reset auf Defaults | OK |
| AC13 | LivePreviewAttsForm rendert null bei tc/maes_merkblaetter | OK |
| AC14 | LivePreviewControls bleibt strukturell BC | OK |
| AC15 | Reset-Button funktional (preserved universal-Atts) | OK |
| AC16 | Doc-Block invalid_format in 6 Error-Codes-Liste | OK |
| AC17 | CSP-Doku enthaelt frame-src 'self' about: mit Erklaerung | OK |
| AC18 | 13/13 Frontend-Shortcode-Regression | OK (Lead-Smoke) |
| AC19 | REST-Response-Schema (10 Felder) unveraendert | OK |
| AC20 | Atts-Validation Layer 4-7 Defense-in-Depth | OK |
| AC21 | Keine Aliases im Schema-Vertrag | OK |
| AC22 | Keine Wishlist-Atts (User-Briefing-Disziplin) | OK |
| AC23 | Version 0.15.5 in Plugin-Header + Const | OK |
| AC24 | KEINE Umlaute im Code (ASCII-safe) | OK |
| AC25 | Pattern-Regex linear (kein ReDoS) - 3 Patterns geprueft: `^[a-zA-Z0-9_\\- ]{0,64}$`, `^[0-9,]{0,128}$`, `^[0-9]{0,12}$` - alle linear | OK |

**25/25 OK**.

---

## Verdict

# GO

v0.15.5 ist freigabe-bereit. Schema-Vertrag-Disziplin EXAKT eingehalten,
0 Critical/Major-Findings, 3 Minor-Observations (alle dokumentiert und
akzeptiert).

Die kombinierte Specialist-Implementation (F12) hat die Lehre aus v0.15.0
(Schema-Drift bei 2 parallelen Specs) und v0.15.3 (Synonym-Risiko) erfolg-
reich umgesetzt: 0 Drift zwischen Backend-Schema, Validation-Code, REST-
Handler, wp_localize_script-Bridge und React-Frontend-Komponenten.

Die 4 Caveats C1-C4 sind alle vollstaendig adressiert:
- C1: Health-Collector kennt 4 Sub-Shortcodes (parent_service/is_sub_shortcode)
- C2: invalid_format in 6-Error-Codes-Doc-Block-Liste
- C3: CSP-Doku enthaelt frame-src 'self' about: mit Erklaerung
- C4: wp_localize_script-Bridge mit 3 neuen Keys

Empfehlung an Release-Lead: Tag v0.15.5 + Release-Notes basierend auf
Discovery-Plan Sektion 8 + dieser Acceptance-Liste. Security-Audit
Parallel-Pfad sollte vor Tag-Push abgeschlossen sein.

---

## Quellen

- `docs/architecture/23-ATTS-EDITOR-PLAN-v0155.md` - Discovery
- `.specialist-F12-v0155-handover.md` - Specialist-Handover
- `includes/class-dhps-preview-renderer.php` - SERVICE_ATTS_SCHEMA + Validation
- `includes/class-dhps-admin-rest.php` - REST-Handler + Error-Codes-Doc-Block
- `includes/class-dhps-health-collector.php` - C1 Sub-Shortcode-Support
- `admin/js/dhps-admin-react.js` - 5 React-Komponenten + Service-Reset
- `Deubner_HP_Services.php` - wp_localize_script-Bridge
- `docs/architecture/14-CSP-COMPATIBILITY.md` - C3 frame-src about:
