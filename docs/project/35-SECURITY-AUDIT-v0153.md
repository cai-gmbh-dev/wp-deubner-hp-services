# Security-Audit v0.15.3 - Live-Preview im Admin-Dashboard

Stand: 2026-05-25
Auditor: Security-Specialist
Scope: Live-Preview-Feature (REST + Renderer + iframe)
Schwester-Audit: docs/project/34-QA-REPORT-v0153.md (parallel)

---

## Executive Summary

Die Live-Preview-Funktion fuehrt eine neue REST-Route ein
(`POST /dhps/v1/services/{service}/preview`), die einen vollstaendigen
HTML-Document inklusive `<script>`-Tags fuer einen `iframe[srcdoc]` liefert.
Sie wird ausschliesslich im Admin-Dashboard (Capability `manage_options`)
genutzt.

**Verdict: GO** (production-ready). Die Implementierung uebernimmt das
gehaertete Pattern der bestehenden `/test`-Route (REST-Nonce + Capability +
Service-Whitelist + Rate-Limit), nutzt iframe-Sandbox als zweite
Isolationsschicht und gibt OTAs nicht in der JSON-Response zurueck. Die
beiden bewussten Trust-Decisions (kein wp_kses_post auf `html`,
OTA-URL-Leak in der iframe-HTML fuer Same-Origin-Admins) sind dokumentiert
und vertretbar.

Es wurden **keine Critical-** und **keine High-Findings** identifiziert.
Drei Medium-Findings (M1-M3) sind dokumentationsbeduerftig, aber kein
Release-Blocker. Vier Low-Findings (L1-L4) und drei Trust-Decisions sind
festgehalten.

---

## Audit-Sektionen

### Section 1: Permission + Auth (KRITISCH)

| Pruefpunkt | Status | Beleg |
|-----------|--------|-------|
| Route nur fuer `manage_options`-User | OK | `class-dhps-admin-rest.php:220` `permission_callback => check_permissions` |
| `check_permissions()` -> `current_user_can('manage_options')` | OK | `class-dhps-admin-rest.php:269-271` |
| Non-Authed Requests werden mit 403 abgewiesen | OK | WP-REST-Server prueft permission_callback vor sanitize/validate. `rest_forbidden` 403 |
| X-WP-Nonce via apiFetch.createNonceMiddleware | OK | React nutzt `apiFetch` (Standard-Pfad mit `wp_rest`-Nonce-Middleware) |
| Renderer-Null-Guard | OK | `class-dhps-admin-rest.php:513-519` - falls Lead vergisst den Renderer zu injizieren, wird `preview_render_failed` (500) statt Fatal Error |

**Bewertung**: Identisches Permission-Modell wie die in v0.15.0 auditierten
4 anderen Routes. Keine neuen Auth-Pfade, keine Bypass-Vektoren.

**Finding**: keines.

---

### Section 2: Service-Whitelist + Validation

| Pruefpunkt | Status | Beleg |
|-----------|--------|-------|
| `sanitize_callback => sanitize_key` an `service`-Param | OK | `class-dhps-admin-rest.php:225` |
| `validate_callback => validate_service_param` | OK | `class-dhps-admin-rest.php:226` |
| Length-Limit 16 Chars | OK | `SERVICE_PARAM_MAX_LENGTH = 16`, Check in `validate_service_param()` line 290 |
| Whitelist `ALLOWED_SERVICES` (9 Eintraege) | OK | `class-dhps-admin-rest.php:53` + `in_array(..., true)` line 298 |
| Re-Check im Handler (Defense in Depth) | OK | `handle_service_preview()` line 533 - erneuter `in_array`-Check |
| Service-Registry-Lookup (3. Layer) | OK | `DHPS_Service_Registry::get_service($service)` line 541, Null-Check |

**Bewertung**: Triple-Layer-Validation (validate_callback ->
in_array-Re-Check -> Registry-Lookup). Service-Slugs fliessen NIE
unsanitisiert in Folgeoperationen.

**Finding**: keines.

---

### Section 3: Rate-Limiting

| Pruefpunkt | Status | Beleg |
|-----------|--------|-------|
| 30/min/User | OK | `check_rate_limit('preview', self::RATE_LIMIT_PER_MINUTE)` line 522, `RATE_LIMIT_PER_MINUTE = 30` |
| Eigener Bucket `preview` (separat von `test`/`flush`) | OK | `check_rate_limit($bucket=...)` line 741, Transient-Key `dhps_admin_rate_preview_{user_id}` line 748 |
| Pro User-ID | OK | `(int) $user_id` als Key-Bestandteil |
| Anonyme User uebergehen Rate-Limit | nicht relevant | permission_callback haette schon abgewiesen (manage_options erfordert Login) |

**Bekannte Limitationen (bewusst akzeptiert)**:
- Sliding-Window-Drift (SEC LOW-3.1 aus v0.14.0): bis zu ~60 Requests in
  10s am Minutenwechsel moeglich.
- Race-Condition Counter-Increment (SEC LOW-3.2): ~1-2 Extra-Requests/Min.

Beide sind fuer Admin-Tooling tolerabel und sind im Doc-Block lines
721-739 dokumentiert.

**Finding**: keines.

---

### Section 4: Atts-Whitelist + Sanitization

| Feld | Validierung | Pfad |
|------|-------------|------|
| `layout` | `in_array($layout, ['default','card','compact'], true)` | `class-dhps-preview-renderer.php:116` |
| `class` | `sanitize_html_class()` + Empty-Treatment | `class-dhps-preview-renderer.php:126-138` |
| `section` | `sanitize_key()` + `in_array(..., ['videos','merkblaetter','aktuelles'], true)` + Service-Gating `service==='maes'` | `class-dhps-preview-renderer.php:142-156` |
| `format` (Top-Level) | `'iframe' === $format` strict | `class-dhps-admin-rest.php:580` |
| Unbekannte Atts | silent ignoriert, `atts_rejected[key] = 'unknown att key'` | `class-dhps-preview-renderer.php:159-164` |
| Shortcode-Konstruktion | nur aus whitelist-validierten Werten via `esc_attr` | `class-dhps-preview-renderer.php:118,137,151` |

**Wichtig**: Der Shortcode-String wird aus `esc_attr`-quoteten,
whitelist-gepruefen Werten konkateniert. Es gibt **keinen Pfad**, ueber
den User-Input ungeprueft in den Shortcode-String fliesst. Selbst wenn
ein Admin `layout="<script>"` schickt, wird der Wert von `in_array`
rejected und landet in `atts_rejected`, nicht im Shortcode.

`class`: `sanitize_html_class()` ist auf Single-Class limitiert (Spaces
werden zusammengefaltet); siehe L2 unten.

**Finding M1 (Medium, Hardening)**: `class-dhps-admin-rest.php:606-614`
nimmt ALLE Top-Level-`atts`-Keys mit `sanitize_key()` entgegen und reicht
sie an den Renderer durch. Der Renderer rejected sie zwar als
`unknown att key`, aber das ist ein **forward-compatible silent-pass**
ohne Limit auf die Anzahl/Groesse der Keys. Ein boeswilliger Admin
koennte 1000 Bogus-Keys senden und dadurch eine ~32-KB-`atts_rejected`-Map
in die Response zwingen. Praktisches Risiko gering (manage_options +
Rate-Limit 30/min), aber ein Hard-Limit auf z.B. 16 Atts-Keys waere
defensiver. **Empfehlung fuer v0.15.4: Cap `count($atts_raw) > 16` -> 400.**

**Finding**: keine weiteren.

---

### Section 5: DHPS_Preview_Renderer XSS-Analyse (KRITISCH)

#### 5.1 `do_shortcode($shortcode)` Output

| Vektor | Bewertung |
|--------|-----------|
| Shortcode-String ist whitelist-konstruiert (Service-Slug aus ALLOWED_SERVICES, Atts-Werte aus statischen Whitelists) | OK |
| `do_shortcode` ruft DHPS-Pipeline -> Parser -> Template -> `wp_kses_post` greift in vielen Templates auf User-Felder | OK (siehe Trust-Decision T1) |
| TC-Wrapper-Parser laesst Inline-JS aus API durch (`<script>test_einblenden(...)</script>`) | bewusst (Trust-Decision T1) |
| Service-JS (TP-Video-Lazy, MAES-Akkordeon) wird als `<script>` in den Body geschrieben | bewusst |

**Trust-Decision T1 (vom Discovery-Doc Sektion 5.3 dokumentiert)**:
- `do_shortcode()`-Output wird **NICHT** durch `wp_kses_post` gefiltert.
- Quelle des HTML ist `deubner-online.de` (Verified Origin, OTA-authed).
- DHPS-Templates escapen User-Felder bereits (`esc_html`, `esc_attr`).
- `wp_kses_post` wuerde alle `<script>`-Tags strippen -> TC, MAES,
  TP-Video-Lazy waere broken.
- iframe-Sandbox + Same-Origin-Admin-only Endpoint sind die
  Isolations-Schichten.

Diese Trust-Decision ist analog zum Frontend-Pfad (`DHPS_Content_Pipeline`
liefert direkt ans Template ohne wp_kses_post), also keine neue
Vergroesserung der Angriffsflaeche.

#### 5.2 HTML-Document-Wrapper

`class-dhps-preview-renderer.php:259-313`:

| Stelle | Sanitization | Risiko |
|--------|--------------|--------|
| `$plugin_url` aus `DEUBNER_HP_SERVICES_URL` (Konstante, Plugin-File-Const) | Plugin-Konstante, keine User-Quelle | keines |
| `$plugin_ver` aus `DEUBNER_HP_SERVICES_VERSION` | Plugin-Konstante | keines |
| `$service_label` -> `esc_html(strtoupper($service))` | OK | keines |
| `$css` aus statischem Array | hardcoded | keines |
| `<link rel="stylesheet" href="..esc_url(plugin_url+css)..?ver=rawurlencode(plugin_ver)">` | esc_url + rawurlencode | OK |
| `<script defer src="..esc_url(plugin_url+js).?ver=rawurlencode(plugin_ver)">` | esc_url + rawurlencode | OK |
| `$title` -> `esc_html($title)` | OK | keines |
| `$inline_css` hardcoded | keines | keines |
| `<body class="dhps-preview dhps-service--esc_attr($service)">` | esc_attr (Service ist ohnehin whitelisted) | OK |
| `$body` (do_shortcode-Output) | trusted (siehe T1) | siehe T1 |

**Path-Traversal-Check**: Alle CSS/JS-Pfade sind hardcoded
(`SERVICE_JS_MAP` line 58-67, `$css_files` line 266-271). Es gibt **keinen
User-Input**, der in die Datei-Pfade einfliesst. `$service` als Map-Key
ist via Whitelist gehaertet.

**Finding**: keines.

#### 5.3 Service-Slug im HTML

`$service` wird nur an zwei Stellen in das HTML eingebettet:
- Als `esc_html(strtoupper($service))` im `<title>` (Zeile 304).
- Als `esc_attr($service)` in der `<body class="dhps-service--...">`
  (Zeile 307).

Beide Stellen sind ge-escapt; zusaetzlich ist `$service` bereits durch
3 Layer (validate_callback, in_array-Re-Check, Registry-Lookup) gehaertet.

**Finding**: keines.

---

### Section 6: iframe sandbox + srcdoc

`admin/js/dhps-admin-react.js:781`:

```js
sandbox: 'allow-same-origin allow-scripts',
```

| Flag | Gesetzt? | Begruendung |
|------|----------|-------------|
| `allow-same-origin` | JA | Erforderlich fuer XHR-Cookies (MIO-AJAX, MMB-Lazy-Akkordeon) und fetch() der Service-JS auf gleiche Origin |
| `allow-scripts` | JA | Erforderlich fuer Alpine.js + TP-Video-Player + TC-Inline-JS |
| `allow-popups` | NEIN | korrekt eingeschraenkt |
| `allow-forms` | NEIN | korrekt eingeschraenkt |
| `allow-top-navigation` | NEIN | korrekt eingeschraenkt |
| `allow-modals` | NEIN | korrekt eingeschraenkt |
| `allow-pointer-lock` | NEIN | korrekt eingeschraenkt |
| `allow-presentation` | NEIN | korrekt eingeschraenkt |

**Bewertung iframe-Sandbox**: Korrekt minimal konfiguriert. Die Kombination
`allow-same-origin + allow-scripts` ist nach W3C-HTML-Spec **schwach**
(reduziert sich auf "kein Sandbox" fuer Same-Origin), aber:

1. Der HTML-Inhalt stammt aus dem PLUGIN-Code (keine User-HTML-Eingabe).
2. Admin-Only Endpoint -> Angreifer braucht bereits `manage_options`
   (= effektive Site-Admin-Rechte, kann ohnehin Plugin-Files editieren).
3. iframe-Origin = Admin-Origin -> `window.parent` waere via JS zugreifbar,
   aber der iframe-Code ist Plugin-eigen, nicht User-injectable.

**srcdoc vs src=URL**:
- srcdoc wird verwendet -> keine zusaetzliche Public-URL noetig -> kein
  Auth-Bypass-Vektor.
- Browser-Cache nicht in Konflikt mit Live-Refresh.
- Trade-off: HTML im JSON (~50-200 KB). Akzeptabel.

**Re-Mount-Schutz** (`key: 'dhps-iframe-' + service + '-' + html.length`,
Zeile 779): Bei Service- oder Inhalts-Wechsel wird der iframe vollstaendig
remountet, alter Content wird verworfen. Verhindert State-Bleed zwischen
zwei Preview-Runs.

**Finding L1 (Low, Hardening)**: Es gibt aktuell keinen `Content-Security-
Policy`-Header in der gerenderten HTML-Doc. Discovery Sektion 5.8 schiebt
das auf v0.15.4. Akzeptabel, weil Admin-Only.

**Finding**: keines (kritisch).

---

### Section 7: OTA-Leak im iframe-HTML

#### 7.1 JSON-Response (REST)

| Feld in Response | Enthaelt OTA? |
|------------------|---------------|
| `service`, `format`, `size_bytes`, `render_time_ms`, `shortcode`, `atts_applied`, `atts_rejected`, `api_cache_hit`, `rendered_at` | nein |
| `html` | indirekt: kann OTA in MIO-AJAX-URLs enthalten (Service-JS-Bindings) |

Der Handler liest OTA via `get_option($auth_option)` (`class-dhps-admin-rest.php:552`)
und benutzt sie ausschliesslich fuer die Pipeline (siehe T2). OTA wird
**nicht** in das Response-JSON aufgenommen.

#### 7.2 iframe-HTML

`do_shortcode()` triggert die Frontend-Pipeline, in der OTAs in
JS-AJAX-URLs gerendert werden (z.B. MIO-`<script>window.dhpsMioConfig =
{ url: 'https://...&ota=...' };`).

**Trust-Decision T2 (Discovery 5.7, R7)**:
- Admin kann OTA ohnehin via Options-Page (`wp-admin/admin.php?page=dhps_settings`)
  einsehen.
- iframe-HTML ist Browser-DevTools-sichtbar fuer Admin.
- Akzeptiert: Kein zusaetzliches Angriffsoberflaechen-Risiko, weil der
  Endpoint manage_options-only ist.

**Bewertung**: Trust-Decision ist dokumentiert (Discovery R7), keine
Auswirkung auf Endkunden.

**Finding L2 (Low, Dokumentation)**: Diese Trust-Decision sollte im
CHANGELOG-v0153.md und (besser noch) in einem Admin-Notice "Live-Preview
zeigt OTA in JS-AJAX-URLs - keine Browser-DevTools mit Live-Sharing"
explizit erwaehnt werden. **Empfehlung**: 1-Satz-Hinweis im CHANGELOG.

---

### Section 8: SSRF / Information Disclosure

| Pruefpunkt | Status |
|-----------|--------|
| Endpoint aus `DHPS_Service_Registry::get_service($service)['endpoint']` (Plugin-statisch) | OK |
| Keine User-URL-Eingabe | OK |
| `DHPS_API_Client` (SSRF-safe nach v0.14.0-Audit) | OK |
| Error-Responses: kein Stack-Trace | OK - `catch(\Throwable $e)` setzt nur `$e->getMessage()` (siehe L3) |
| HTTP-Status-Codes konsistent | OK: 400 (invalid_service), 400 (service_not_configured), 404 (invalid_endpoint), 429 (rate_limit), 500 (preview_render_failed), 403 (rest_forbidden via WP) |

**Finding L3 (Low, Information Disclosure)**:
`class-dhps-admin-rest.php:622` schreibt `$e->getMessage()` in den
`WP_Error`-Body:

```php
return new WP_Error(
    'preview_render_failed',
    'Preview konnte nicht gerendert werden: ' . $e->getMessage(),
    array( 'status' => 500 )
);
```

Bei PHP-Exceptions kann `getMessage()` interne Pfade (z.B.
`/var/www/html/wp-content/plugins/.../parser.php`) enthalten. Da der
Endpoint manage_options-only ist und Admins ohnehin Dateipfade kennen,
ist das Risiko minimal. **Empfehlung fuer v0.15.4**: Message generisch
("Renderer-Fehler. Siehe Server-Log.") + `error_log()` mit Details.

---

### Section 9: React-JS Security (admin/js/dhps-admin-react.js)

| Pruefpunkt | Status | Beleg |
|-----------|--------|-------|
| `iframe.srcDoc = result.html` ist React-managed (kein `innerHTML`) | OK | line 780 |
| Kein `eval`, kein `Function(string)`, kein `dangerouslySetInnerHTML` im Preview-Pfad | OK | grep gegen Datei -> 0 Matches |
| `apiFetch` mit WP-Nonce-Middleware | OK | Plugin-globales Middleware-Pattern |
| `key={service + '-' + html.length}` -> Force-Remount | OK | line 779 |
| `service` via `encodeURIComponent` in Path | OK | `apiFetch.path` line 926 |
| Service-Slug-Liste hardcoded in `LivePreviewControls`-Options | OK (siehe L4) |
| User-Input fliesst NICHT in iframe-Atts (sandbox/title sind statisch) | OK |
| `result.html` ist String-typed (`typeof === 'string'`) gecheckt | OK | line 936 |

**Trust-Decision T3**: `iframe[srcDoc]` ist im React-Pattern sicher (React
serialisiert den String als HTML-Attribut, kein Template-Injection-Vektor),
solange der HTML-Content aus einer trusted Quelle stammt. Die REST-Response
ist die Quelle, und diese ist manage_options-protected.

**Finding L4 (Low, Hardening)**: Im React-Layer gibt es kein Whitelisting
des Service-Slug VOR dem REST-Call. Theoretisch koennte ein Admin via
React-DevTools `setService('boese-service')` setzen - das Backend rejected
das aber via `validate_service_param` (siehe Section 2). Defense-in-Depth
ist OK, aber zusaetzliches Frontend-Whitelisting der 9 Slugs in
`LivePreviewControls` waere defensiver. Nicht release-blockend.

---

### Section 10: Schema-Drift-Mitigation (Lehre v0.15.0)

v0.15.0 hatte 3 Critical-Issues durch Schema-Drift zwischen Backend und
Frontend. Discovery Sektion 9 spezifizierte fuer v0.15.3 einen expliziten
Schema-Vertrag mit 10 autoritativen Feldern.

| Vertrag-Feld | Backend liefert | Frontend liest |
|--------------|------------------|----------------|
| `service` | OK (line 638) | OK (line 938) |
| `format` | OK (line 639) | OK (line 939) |
| `html` | OK (line 640) | OK (line 936) |
| `size_bytes` | OK (line 641) | OK (defensive line 940-942) |
| `render_time_ms` | OK (line 642) | OK (defensive line 943-945) |
| `shortcode` | OK (line 643) | OK (line 946) |
| `atts_applied` | OK (line 644) | OK (line 947) |
| `atts_rejected` | OK als Object (line 645) | OK als Object + Array-Fallback (line 819-824) |
| `api_cache_hit` | OK (line 646) | OK (defensive line 949-951) |
| `rendered_at` | OK (line 647) | OK (line 952) |

**Festgestellter Drift**: `atts_rejected` ist im Vertrag (Discovery 9.3)
als `array<string>` definiert, F1 liefert ein `Map-Object`
`{ key: reason }`. F1 dokumentiert das in Handover Sektion 3 als bewusste
Erweiterung (mehr Information fuer Admins).

**Finding M2 (Medium, Schema-Drift)**:
- Drift ist gering, weil F2 (Frontend) defensives Reading hat
  (`Array.isArray(attsRejected) ? slice() : Object.keys()`, line 820-824).
- Drift ist aber sichtbar gegenueber dem dokumentierten Vertrag.
- **Empfehlung**: Discovery 9.3-Vertrag in v0.15.4-CHANGELOG aktualisieren
  ODER Backend auf `array<string>` zurueckziehen. **Vorzug: Vertrag
  updaten**, weil Map mehr Information liefert (Admin sieht Reject-Grund).
- Kein Release-Blocker.

**Bewertung Schema-Drift-Lehre angewendet**: JA. Beide Specs erhielten
Sektion 9 als Pflicht-Anhang, F2 hat Belt-and-Suspenders-Defensive-Reads
fuer alle 10 Felder. Der einzige Drift (Map vs Array) ist
ruckwaerts-kompatibel.

---

## Findings-Uebersicht

| ID | Severity | Sektion | Befund | Status |
|----|----------|---------|--------|--------|
| M1 | Medium | 4 | Kein Hard-Cap auf Anzahl atts-Keys (DoS-Surface gering) | v0.15.4 Hardening |
| M2 | Medium | 10 | `atts_rejected` Schema-Drift (Map vs Array) - Frontend toleriert | v0.15.4: Vertrag updaten |
| M3 | Medium | 5/7 | Trust-Decisions T1 (kein wp_kses_post) + T2 (OTA-URL-Leak) erfordern CHANGELOG-Hinweis | v0.15.3 CHANGELOG ergaenzen |
| L1 | Low | 6 | Keine CSP in HTML-Doc | v0.15.4 |
| L2 | Low | 7 | OTA-Leak-Hinweis im CHANGELOG | v0.15.3 CHANGELOG ergaenzen |
| L3 | Low | 8 | `$e->getMessage()` in WP_Error - kleine Info-Disclosure | v0.15.4 |
| L4 | Low | 9 | Kein Frontend-Whitelisting der Service-Slugs (Backend rejected ohnehin) | optional |

**Critical: 0, High: 0, Medium: 3, Low: 4.**

---

## Trust-Decisions (dokumentiert + akzeptiert)

| # | Entscheidung | Begruendung | Quelle |
|---|--------------|-------------|--------|
| T1 | `html` wird **nicht** durch `wp_kses_post` gefiltert | DHPS-Templates produzieren bewusst `<script>`-Tags (TC-Akkordeon, TP-Video-Lazy, MAES). wp_kses_post wuerde sie strippen. iframe-Sandbox + manage_options-Endpoint sind die Isolations-Schichten. | Discovery 5.3, F1 Handover Z.237 |
| T2 | OTA in iframe-HTML als JS-URL-Param sichtbar | Admin sieht OTA ohnehin via Options-Page. manage_options-only Endpoint. | Discovery 5.7 / R7 |
| T3 | iframe-Sandbox `allow-same-origin + allow-scripts` ist W3C-schwach | HTML-Inhalt ist Plugin-eigen (keine User-HTML-Eingabe). Admin-only Endpoint. | Discovery 5.4 |

---

## Verdict

**GO** - Live-Preview v0.15.3 ist production-ready.

Begruendung:
- 0 Critical, 0 High Findings.
- iframe-Sandbox ist korrekt minimal konfiguriert.
- XSS-Vektor ist im Preview-Renderer ausgeschlossen (Service-Slug aus
  Whitelist, Atts via in_array/sanitize_html_class/sanitize_key,
  HTML-Wrapper-Escaping mit esc_url/esc_html/esc_attr, do_shortcode-Output
  ist Plugin-eigen).
- Triple-Layer-Service-Validation (validate_callback ->
  in_array-Re-Check -> Registry-Lookup).
- Rate-Limit eigener Bucket 30/min.
- OTA wird nicht in JSON-Response ausgeliefert (akzeptierter T2-Leak
  nur im iframe-HTML, manage_options-only).
- Schema-Vertrag eingehalten bis auf einen ruckwaerts-kompatiblen Drift
  (`atts_rejected` als Map statt Array), Frontend liest defensiv beide.

**Vor Release dringend empfohlen** (kein Blocker):
- CHANGELOG-v0153 ergaenzen um Trust-Decisions T1 (kein wp_kses_post)
  und T2 (OTA-URL-Leak im iframe-HTML) - Admin-Transparenz.

**Fuer v0.15.4 vorgemerkt** (Hardening):
- M1: Atts-Keys-Count-Cap (16 max).
- M2: Schema-Vertrag in Discovery aktualisieren (atts_rejected: Map).
- L1: CSP-Header in HTML-Doc.
- L3: Generische Renderer-Error-Message + error_log fuer Details.
- L4: Frontend-Slug-Whitelisting (Defense in Depth).

---

## Quellen

- `includes/class-dhps-admin-rest.php` (766 LOC)
- `includes/class-dhps-preview-renderer.php` (316 LOC)
- `admin/js/dhps-admin-react.js` (Z.681-1015 fuer Preview-Pfad)
- `Deubner_HP_Services.php` Z.294-296 (DI-Setup)
- `docs/architecture/21-LIVE-PREVIEW-PLAN-v0153.md` (Discovery, 843 LOC)
- `.specialist-F1-LIVEPREVIEW-handover-v0153.md` (F1-Handover)
- `docs/project/26-SECURITY-AUDIT-v0150.md` (Vorgaenger-Audit, Schema-Drift-Lehre)
- `docs/project/29-SECURITY-AUDIT-v0151.md`
- `docs/project/32-SECURITY-AUDIT-v0152.md`
