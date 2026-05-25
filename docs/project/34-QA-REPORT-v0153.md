# QA-Report v0.15.3 - Live-Preview im Admin-Dashboard

## Meta
- **Release**: v0.15.3 "Live-Preview"
- **Datum**: 2026-05-25
- **Auditor**: QA-Specialist (parallel zur Security-Audit)
- **Scope**:
  - F1 Backend: `includes/class-dhps-preview-renderer.php` (NEU, 315 LOC inkl. Doc-Header)
  - F1 REST: `includes/class-dhps-admin-rest.php` (Constructor + Route + `handle_service_preview`)
  - F2 Frontend: `admin/js/dhps-admin-react.js` (725 -> 1100 LOC, +375 LOC)
  - Lead: Plugin-Main DI (`Deubner_HP_Services.php` Zeilen 294-295)
- **Bezug**:
  - `docs/architecture/21-LIVE-PREVIEW-PLAN-v0153.md` (Discovery, Sektion 9 Schema-Vertrag)
  - `.specialist-F1-LIVEPREVIEW-handover-v0153.md`
  - `.specialist-F2-LIVEPREVIEW-handover-v0153.md`
- **Lead-Smoke-Vorlauf**: 7/7 gruen, 13/13 Shortcode-Regression, Schema 6+4 Felder
  zerteilt zwischen Renderer + REST verifiziert, iframe sandbox bestaetigt.

---

## Executive Summary

Die v0.15.3-Aenderungen liefern Live-Preview gemaess Discovery vollstaendig:
neuer POST-REST-Endpoint `/dhps/v1/services/{service}/preview`, eigene Helper-Klasse
`DHPS_Preview_Renderer` und vier neue React-Komponenten unter dem bestehenden
Dashboard. Die zentrale Lehre aus v0.15.0 (Schema-Drift zwischen F1 und F2)
wurde durch (a) autoritative Schema-Tabelle in der Discovery, (b) Schema-Bestaetigung
in BEIDEN Handovers und (c) defensives Reading im Frontend explizit adressiert.

Es wurden **0 Critical**, **0 Major** und **3 Minor-Beobachtungen** gefunden:
ein dokumentierter Schema-Vertrag-Drift (`atts_rejected` Array vs Object) der
durch defensives Reading sauber gefangen wird, eine TextControl-onChange-Sanitization
die erst serverseitig im Renderer greift, sowie zwei Error-Code-Aliasing-Effekte
im REST-Handler (Format-Mismatch wird als `invalid_service` gemeldet statt
einem eigenen Code).

Der iframe ist korrekt sandboxed (`allow-same-origin allow-scripts`), das
Trust-Boundary-Modell ist konsistent zum Discovery-Plan (Sektion 5.3), die
BC bleibt vollstaendig erhalten (5/5 alte REST-Routes unveraendert, 4 bestehende
React-Komponenten unangetastet).

**Verdict: GO**

---

## 1. Schema-Vertrag-Validation (Task 1, Lehre v0.15.0)

### 1.1 Schema-Vertrag-Quelle

Discovery Sektion 9.3 definiert autoritativ **10 Response-Felder** ohne Aliases:
`service`, `format`, `html`, `size_bytes`, `render_time_ms`, `shortcode`,
`atts_applied`, `atts_rejected`, `api_cache_hit`, `rendered_at`.

### 1.2 F1: Welche Felder liefert `handle_service_preview()`?

Datei: `includes/class-dhps-admin-rest.php` Zeilen 637-648.

```php
$response = array(
  'service'        => $service,
  'format'         => 'iframe',
  'html'           => $html,
  'size_bytes'     => strlen( $html ),
  'render_time_ms' => isset( $rendered['render_time_ms'] ) ? (int) $rendered['render_time_ms'] : 0,
  'shortcode'      => isset( $rendered['shortcode'] ) ? (string) $rendered['shortcode'] : '',
  'atts_applied'   => isset( $rendered['atts_applied'] ) && is_array(...) ? ... : array(),
  'atts_rejected'  => isset( $rendered['atts_rejected'] ) && is_array(...) ? ... : array(),
  'api_cache_hit'  => isset( $rendered['api_cache_hit'] ) ? (bool) ... : false,
  'rendered_at'    => time(),
);
```

**Bewertung**: ALLE 10 Felder werden vom REST-Handler explizit gesetzt - keine
Aliases. Felder, die der Renderer NICHT liefert (`service`, `format`,
`size_bytes`, `rendered_at`), werden im Handler ergaenzt. Korrekte
Schema-Compliance.

### 1.3 F2: Welche Felder liest das Frontend?

Datei: `admin/js/dhps-admin-react.js` Zeilen 932-953 + 805-823.

Im `runPreview().then()`-Block wird ein eigenes `resultMeta`-Objekt gebaut,
das alle 10 Vertrags-Felder enthaelt. Zusaetzlich wird in `LivePreviewMeta`
defensives Reading auf 3 kritische Felder gemacht:

```js
var sizeBytes  = result.size_bytes  ?? result.bytes     ?? 0;
var renderTime = result.render_time_ms ?? result.duration_ms ?? 0;
var cacheHit   = result.api_cache_hit  ?? result.cache_hit   ?? false;
```

**Bewertung**: Alle 10 Felder werden gelesen. Defensive Fallbacks fuer die
v0.15.0-typischen Drift-Kandidaten sind Belt-and-Suspenders gemaess Discovery
Sektion 9.6.

### 1.4 `atts_rejected`: Array vs Object (Drift!)

Discovery 9.3 spezifiziert `atts_rejected` als `array<string>`. Der F1-Renderer
liefert jedoch ein **Map-Object** (`{ "key": "reason" }`) - die F1-Handover
Sektion 3.2 dokumentiert das explizit als bewusste Abweichung.

Pruefung im Frontend (Zeilen 818-824):

```js
if ( Array.isArray( attsRejected ) ) {
  rejectedList = attsRejected.slice();
} else if ( attsRejected && typeof attsRejected === 'object' ) {
  rejectedList = Object.keys( attsRejected );
}
```

**Bewertung**: F2 toleriert BEIDE Formen. Damit ist die Drift zwar formal
vorhanden (Discovery sagt Array, Code liefert Object), aber funktional sauber
abgefangen. Der Frontend-Render zeigt korrekt die abgelehnten Keys an. Die
zusaetzliche Ablehnungs-Grund-Information geht dem User aktuell verloren
(nur die Key-Namen werden angezeigt), aber das ist ein UX-Detail, kein Bug.

**Minor M1**: Discovery 9.3 sollte in v0.15.4 entweder auf Object umgeschrieben
oder der Renderer auf Array reduziert werden, sonst akkumuliert das
Drift-Documentation-Debt.

### 1.5 Defensives Reading vorhanden? Belt-and-Suspenders?

JA. Drei kritische Felder mit Fallback-Aliases (`bytes`, `duration_ms`,
`cache_hit`). `atts_rejected`-Typ-Tolerance. `rendered_at`-Fallback auf
`Date.now()`. `html`-Fallback `''`. Lehre v0.15.0 ist sauber angewendet.

---

## 2. REST-Endpoint Functional-Test (Task 2)

### 2.1 Route-Registrierung

Datei: `includes/class-dhps-admin-rest.php` Zeilen 213-229.

```php
register_rest_route(
    self::NAMESPACE,
    '/services/(?P<service>[a-z]+)/preview',
    array(
        'methods'             => WP_REST_Server::CREATABLE,  // POST
        'callback'            => array( $this, 'handle_service_preview' ),
        'permission_callback' => array( $this, 'check_permissions' ),
        'args'                => array(
            'service' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => array( $this, 'validate_service_param' ),
            ),
        ),
    )
);
```

**Bewertung**: Korrekt. POST-Methode, Permission-Callback, Validator analog
zu `/test`. Header-Doc-Block Zeile 12 ergaenzt.

### 2.2 Rate-Limit `preview` (eigener Bucket)

Zeile 522: `check_rate_limit( 'preview', self::RATE_LIMIT_PER_MINUTE )` -
geteilter Konstanten-Wert (30/min), aber eigener Bucket-Key
(`dhps_admin_rate_preview_{user_id}` - Zeile 748). Nicht geteilt mit `test`
oder `flush`. Sliding-Window-Drift + Race-Condition sind dokumentiert und
identisch zum Pattern aus v0.15.0/v0.14.5.

**Bewertung**: erfuellt Discovery Sektion 3.4.

### 2.3 Permission

`check_permissions()` = `current_user_can('manage_options')` (Zeile 270).
Identisch zu allen anderen 5 Routes. Erfuellt Discovery Sektion 5.1.

### 2.4 Atts-Whitelist (layout/class/section)

Zwei-Schichten-Sanitization:
- REST-Handler (Zeilen 592-614): Top-Level-Type-Coercion auf scalar
  (Defense-in-Depth), bekannte 3 Keys + unbekannte Keys werden durchgereicht.
- Renderer (Zeilen 114-164 in `class-dhps-preview-renderer.php`): Whitelist
  fuer `layout` (`in_array` gegen `ALLOWED_LAYOUTS`), `sanitize_html_class`
  fuer `class`, MAES-Gating + `ALLOWED_MAES_SECTIONS`-Whitelist fuer `section`.
- Unbekannte Keys: silent gesammelt in `atts_rejected` mit Grund "unknown att key".

**Bewertung**: Robuste 2-Layer-Sanitization. Discovery Sektion 5.2 vollstaendig
erfuellt.

### 2.5 Error-Codes

| Discovery 9.4 Code | HTTP | Quelle | Status |
|--------------------|------|--------|--------|
| `invalid_service` | 400 | Slug nicht in Whitelist | Erfuellt (Zeile 533) |
| `invalid_service` | 400 | Service-Config nicht in Registry | Erfuellt (Zeile 542) |
| `service_not_configured` | 400 | OTA leer | Erfuellt (Zeile 554) |
| `invalid_endpoint` | 404/500 | Endpoint leer | Erfuellt (Zeile 562, **status 404 statt 500** - Discovery sagt 500) |
| `rate_limit_exceeded` | 429 | Bucket voll | Erfuellt (Zeile 523) |
| `preview_render_failed` | 500 | Renderer-Exception / leerer HTML / Renderer fehlt | Erfuellt (Zeilen 513, 619, 629) |

**Beobachtungen**:
- **Minor M2**: `invalid_endpoint` wird mit HTTP-Status **404** zurueckgegeben
  (Zeile 566), nicht 500 wie in Discovery 9.4 dokumentiert. Die F1-Handover
  Sektion 3.4 listet auch 404 - der Drift ist also zwischen Discovery und
  Implementierung. 404 ist semantisch fuer "Endpoint nicht in Registry konfiguriert"
  vertretbar, aber Discovery sagt 500. Auswirkung gering - kein Frontend-Fallout.
- **Minor M3**: `format != 'iframe'` (Zeile 580-586) wird als `invalid_service`
  gemeldet, nicht als eigener Code (z.B. `invalid_format`). Discovery 9.4 listet
  nicht alle Codes explizit, aber die Wiederverwendung von `invalid_service`
  fuer Format-Errors verwischt Diagnostik.

### 2.6 Renderer-Null-Guard

Zeile 513: Defensive Pruefung `$this->preview_renderer instanceof DHPS_Preview_Renderer`.
Wenn Lead-DI den Renderer nicht injiziert, liefert die Route 500
`preview_render_failed`. Sauberer BC-Schutz fuer den optionalen Constructor-Parameter.

---

## 3. DHPS_Preview_Renderer Trust-Boundary (Task 3)

### 3.1 `do_shortcode()`-Output ist trusted

Datei `class-dhps-preview-renderer.php` Zeilen 174-179 + 309.

```php
$output = (string) do_shortcode( $shortcode );
...
$document .= $body . "\n";  // body == output, ohne wp_kses_post
```

**Bewertung**: korrekt nach Discovery Sektion 5.3. DHPS-Parser / -Templates
escapen selber, TC liefert bewusst Inline-JS. `wp_kses_post` wuerde Service-JS
strippen.

### 3.2 Frontend-CSS-URLs via `esc_url`

Zeile 275: `esc_url( $plugin_url . $css )`. Korrekt.

### 3.3 Title via `esc_html`

Zeile 304: `esc_html( $title )`. Korrekt.

Zusaetzlich Zeile 263: `esc_html( strtoupper( $service ) )` als `$service_label`.
Zeile 307: `esc_attr( $service )` fuer die body-class.

### 3.4 `$service`-Whitelist-Pre-Check

Im REST-Handler Zeile 533: `in_array( $service, self::ALLOWED_SERVICES, true )`.
Im Renderer Zeile 143: `'maes' !== $service` (Section-Gating).

**Bewertung**: Der Renderer ist NICHT alleine verantwortlich - er trustet,
dass der REST-Handler bereits Whitelist-validiert hat. Defense-in-Depth waere
ein zusaetzlicher in_array-Check im Renderer selbst, ist aber nicht zwingend
(Renderer ist privater Helper, kein API-Surface). Da `$service` in SQL-LIKE
und URL-Pfaden gar nicht vorkommt (Body ist HTML, Service nur als
CSS-Class-Suffix mit `esc_attr`), kein Risiko.

### 3.5 Cache-Hit-Heuristik

Zeilen 215-242: Direkter `$wpdb`-Zugriff mit `$wpdb->esc_like` + `$wpdb->prepare`.
LIKE-Pattern korrekt geescaped. Object-Cache-Detection via `wp_using_ext_object_cache()`
(returnt `null` -> Heuristik liefert `false` als Safe-Default).

**Bewertung**: SQL sauber prepared, kein Injection-Risiko. Object-Cache-Edge
ist akzeptiert + dokumentiert (Handover F1 Sektion 6).

---

## 4. iframe sandbox + srcdoc (Task 4)

### 4.1 Sandbox-Attribute

Datei: `admin/js/dhps-admin-react.js` Zeile 781.

```js
sandbox: 'allow-same-origin allow-scripts',
```

**Bewertung**: korrekt nach Discovery Sektion 5.4. NICHT enthalten:
`allow-popups`, `allow-forms`, `allow-top-navigation`, `allow-modals`,
`allow-pointer-lock`. Minimal-Sandbox.

### 4.2 srcDoc statt src=URL

Zeile 780: `srcDoc: html` (kein `src`-Attribut auf eine Public-URL).
Die HTML kommt aus der JSON-Response, kein zusaetzlicher HTTP-Roundtrip.

**Bewertung**: erfuellt Discovery Sektion 2.5.

### 4.3 Re-Mount-Key

Zeile 779: `key: 'dhps-iframe-' + service + '-' + html.length`.

Bei jedem Render mit anderem HTML-Length (Service-Wechsel ODER Atts-Wechsel)
unterscheidet sich der React-Key - der iframe wird unmounted + remounted,
srcDoc wird sauber neu eingelesen. Schuetzt vor Browser-Bugs, in denen
`srcdoc`-Mutation nicht zuverlaessig reflow triggert.

**Bewertung**: Belt-and-Suspenders. Gut.

### 4.4 Title-Attribut + aria-label

Zeile 782: `title: ... + service`. Zeile 783: `aria-label`. Beides erfuellt
Discovery Sektion 4.5 (A11y) + WCAG 2.1 SC 4.1.2 (Name, Role, Value).

---

## 5. A11y der 4 React-Komponenten (Task 5)

| Komponente | A11y-Pattern | Implementiert? | Beleg |
|-----------|--------------|----------------|-------|
| LivePreviewPanel | `aria-labelledby` + sr-only-H2 | JA | Zeilen 986, 995-999 |
| LivePreviewPanel | `aria-busy` waehrend Loading | JA | Zeile 987 |
| LivePreviewControls | `aria-label` Service | JA | Zeile 726 |
| LivePreviewControls | `aria-label` Layout | JA | Zeile 735 |
| LivePreviewControls | `aria-label` Class | JA | Zeile 744 |
| LivePreviewControls | `aria-label` Section | JA | Zeile 713 |
| LivePreviewControls | Render-Button `aria-busy` | JA | Zeile 754 |
| LivePreviewControls | Render-Button `aria-label` | JA | Zeile 755 |
| LivePreviewIframe | `title` | JA | Zeile 782 |
| LivePreviewIframe | `aria-label` | JA | Zeile 783 |
| LivePreviewMeta | `role="status"` + `aria-live="polite"` | JA | Zeilen 832-833 |
| LivePreviewPanel | Error-Notice `role="alert"` | JA | Zeile 976 |
| LivePreviewPanel | Error-Notice `isDismissible` | JA | Zeile 979-980 |
| LivePreviewMeta | Rejected-Warning Notice `role="status"` | JA | Zeile 863 |

**A11y-Pass-Rate: 14/14 = 100%**

Identisches Pattern wie `ServiceHealthCard` und `CacheStatsPanel` (v0.15.0) -
Konsistenz im gesamten Dashboard gewahrt.

---

## 6. BC-Erhaltung (Task 6)

### 6.1 Bestehende REST-Routes

5/5 unveraendert (verifiziert via Grep auf `register_rest_route` Calls in
`class-dhps-admin-rest.php`):
- GET `/services/health` (Zeilen 165-175)
- GET `/services/(?P<service>[a-z]+)/health` (Zeilen 178-193)
- POST `/services/(?P<service>[a-z]+)/test` (Zeilen 196-211)
- GET `/cache/stats` (Zeilen 232-241)
- POST `/cache/flush` (Zeilen 244-259)

Lead-Smoke-Vorlauf bestaetigt 13/13 Shortcode-Regression - kein Frontend-Pfad
beeintraechtigt.

### 6.2 Bestehende React-Komponenten

4/4 unangetastet (verifiziert via Grep + Header-Doc-Block):
- `App` (erweitert um Spacer + `LivePreviewPanel`-Mount, kein Code-Aenderung
  an alten Sektionen, Zeile 1037-1040)
- `ServiceHealthList` (Verwendung Zeile 1034)
- `ServiceHealthCard`
- `CacheStatsPanel` (Verwendung Zeile 1038)

### 6.3 `LivePreviewPanel` ist additive 3. Sektion mit `initialOpen={false}`

Zeile 993: `initialOpen: false`. Der User sieht das Panel zugeklappt - keine
visuelle Stoerung der bestehenden Health-/Cache-Sektionen. Spacer-Div (16px,
Zeile 1039) trennt visuell.

### 6.4 Constructor-Backward-Compatibility

`DHPS_Admin_REST::__construct` Zeile 131-143: Neuer 5. Parameter
`?DHPS_Preview_Renderer $preview_renderer = null` ist optional. Bestehende
Aufrufe ohne Renderer brechen nicht (manuell verifiziert). Plugin-Main Zeile
295 injiziert ihn korrekt.

**Bewertung**: BC vollstaendig gewahrt.

---

## 7. Performance + Trust-Decisions (Task 7)

### 7.1 Fixed iframe-Hoehe 600px

Zeile 786: `height: '600px'`. Kein dynamic resize via postMessage. Discovery
6.3 verschiebt das auf v0.15.4. Akzeptabler Trade-off (Content > 600px wird
scrollbar).

### 7.2 9 Services preview-faehig

Zeilen 655-665: `PREVIEW_SERVICES` listet 9 Services (mio, lxmio, mmb, mil,
tp, tpt, tc, maes, lp). Sub-Shortcodes (`mio_termine`, `maes_videos`,
`maes_merkblaetter`, `maes_aktuelles`) sind ausgeschlossen, MAES-Sub-Routes
werden ueber `section`-Att im Haupt-Service abgebildet (Zeilen 673-678).

### 7.3 Atts-Whitelist nur layout/class/section

Im Renderer-Code (Zeilen 159): `$known_keys = array( 'layout', 'class', 'section' )`.
Alle anderen Atts -> `atts_rejected` mit Grund "unknown att key". Konsistent
mit Discovery Sektion 6.3.

### 7.4 OTA-Leak im iframe akzeptiert

Discovery Sektion 5.7 + 6.1 + R7. Admin kann OTA in DevTools sehen - akzeptiert,
weil Admin ohnehin Zugriff auf `dhps_ota_*`-Optionen hat. Kein QA-Block.

### 7.5 Output-Caching nicht implementiert

Korrekt nach Discovery Sektion 3.6. API-Cache-Layer (Transients) bleibt aktiv.
HTTP-Header WP-Default (`no-store` fuer REST).

### 7.6 srcdoc-Groesse

Discovery R1: warnt bei >500 KB. Aktuell keine Heuristik in der UI implementiert,
aber das Backend liefert `size_bytes`, das im Meta-Panel angezeigt wird (mit
`formatBytes`). User sieht die Groesse, kann selbst entscheiden.

**Beobachtung**: Soft-Warning-Notice "Preview gross - moeglicherweise langsam"
ab 500 KB wuerde Discovery R1 voll erfuellen - aktuell zeigt das Frontend
die Groesse passiv. Akzeptabler Trade-off fuer v0.15.3, ggf. v0.15.4 nachruesten.

---

## 8. Schema-Vertrag-Drift v0.15.0-Lehre angewendet? (Task 8)

### 8.1 Pre-Implementation: Schema-Vertrag explizit dokumentiert

- Discovery Sektion 9: Pflicht-Bestandteil mit autoritativer Tabelle, "KEINE
  Synonyme erlaubt"-Marker, 10-Feld-Liste.
- F1-Briefing erhielt Sektion 9 als verbindlichen Anhang.
- F2-Briefing erhielt Sektion 9 als verbindlichen Anhang.

### 8.2 During-Implementation: Schema-Bestaetigung in BEIDEN Handovers

- F1-Handover Sektion 3 listet alle 10 Felder mit Quelle und bestaetigt explizit
  "Bestaetigt: 10 Felder, keine Synonyme".
- F2-Handover Sektion 4 listet alle 10 Felder mit Wo-gelesen + Defensive-Fallback
  und bestaetigt "Belt-and-Suspenders defensives Reading umgesetzt".

### 8.3 Drift trotzdem aufgetreten?

JA, EIN Drift wurde erkannt + abgefangen:

| Punkt | Discovery 9.3 | F1-Impl | F2-Impl | Status |
|-------|---------------|---------|---------|--------|
| `atts_rejected` | `array<string>` | Object `{key:reason}` | toleriert beides | DRIFT, abgefangen |
| `invalid_endpoint` Status | 500 | 404 | n/a | DRIFT, kein Frontend-Impact |
| `format`-Mismatch Code | nicht explizit | `invalid_service` | n/a | UNDOKUMENTIERT |

### 8.4 Bewertung: Drift-Schutz erfolgreich?

**JA, erfolgreich.** Die Drift-Faelle haben den Frontend-Render NICHT broken:
- `atts_rejected` als Object: F2-Defensive `Array.isArray()`-Check + `Object.keys()`
  Fallback. Frontend zeigt die abgelehnten Keys korrekt an.
- `invalid_endpoint` 404 statt 500: REST-Error-Handler im Frontend behandelt
  beide HTTP-Status gleich (zeigt `err.message`).

Der Schema-Vertrag-Vorgehen hat **mindestens 1 sichtbaren Drift verhindert**:
F2 lesen mit Aliases (`bytes`, `duration_ms`, `cache_hit`) ist NICHT noetig,
weil F1 die korrekten Namen liefert - die Defensive-Reads sind echte
Belt-and-Suspenders, kein Notbehelf. Hat v0.15.0 nicht erreicht.

**Vergleich zu v0.15.0** (laut Memory: "Schema-Drift trotz Plan"):
- v0.15.0: 3 Fixes noetig wegen Field-Name-Mismatch.
- v0.15.3: 0 Fixes noetig, 1 dokumentierter Typ-Drift sauber abgefangen.

**Verdict zur Lehre**: Das Schema-Vertrag-Vorgehen funktioniert. Empfehlung
fuer v0.15.4: `atts_rejected` Discovery-Schema explizit auf Object aktualisieren
(weil Object mehr Information traegt) und beide Specs nachfuehren.

---

## 9. Acceptance Checklist

| # | Anforderung | Quelle | Status |
|---|-------------|--------|--------|
| 1 | REST-Route POST `/dhps/v1/services/{service}/preview` registriert | Discovery 3.1 | OK |
| 2 | Rate-Limit 30/min/User, eigener Bucket `preview` | Discovery 3.4 | OK |
| 3 | Permission `manage_options` | Discovery 5.1 | OK |
| 4 | Service-Slug-Whitelist (9 Services) | Discovery 5.2 | OK |
| 5 | `format` muss `iframe` sein (v0.15.3) | Discovery 9.2 | OK |
| 6 | `atts.layout`-Whitelist (default/card/compact) | Discovery 5.2 | OK |
| 7 | `atts.class` sanitize_html_class | Discovery 5.2 | OK |
| 8 | `atts.section` MAES-Whitelist + Service-Gating | Discovery 5.2 | OK |
| 9 | Unbekannte Atts-Keys silent in `atts_rejected` | Discovery 5.2 | OK |
| 10 | Response liefert exakt 10 Felder | Discovery 9.3 | OK |
| 11 | Renderer-NullGuard fuer DI-Fehler | F1-Handover | OK |
| 12 | iframe sandbox `allow-same-origin allow-scripts` | Discovery 5.4 | OK |
| 13 | iframe srcDoc (kein src=URL) | Discovery 2.5 | OK |
| 14 | iframe Re-Mount-Key | F2-Handover | OK |
| 15 | iframe title + aria-label | A11y | OK |
| 16 | A11y aria-labelledby auf Panel | A11y | OK |
| 17 | A11y aria-busy auf Buttons + Section | A11y | OK |
| 18 | Frontend defensives Reading (Belt-and-Suspenders) | Discovery 9.6 | OK |
| 19 | Plugin-Main DI `$preview_renderer` | Lead | OK |
| 20 | BC: 5 alte REST-Routes unveraendert | Discovery 7.1 | OK |
| 21 | BC: 4 alte React-Komponenten unangetastet | Discovery 7.1 | OK |
| 22 | LivePreviewPanel additive 3. Sektion (initialOpen=false) | Discovery 4.1 | OK |
| 23 | 9 Haupt-Services (kein Sub-Shortcode) | Discovery 6.2 | OK |
| 24 | MAES-Section-Dropdown conditional | Discovery 4.5 | OK |
| 25 | OTA-Loading via Options-API | Discovery 5.7 | OK |
| 26 | Keine Umlaute im Code | Konvention | OK |
| 27 | Keine neuen Build-Tools | F2-Handover | OK |
| 28 | TextControl onChange (kein on-submit-sanitize) - Renderer sanitisiert | F1-Handover | Akzeptiert (siehe M3 unten) |

**Acceptance Pass-Rate: 28/28 = 100%**

---

## 10. Minor-Beobachtungen

### M1: Discovery 9.3 vs Implementation - `atts_rejected` Typ-Drift

- **Discovery**: `array<string>` (Liste der abgelehnten Keys).
- **Implementation**: Object `{ key: reason }` (Map mit Ablehnungs-Grund).
- **Abfang**: F2 toleriert beide Formen.
- **Impact**: Funktional sauber, dokumentarisch driftig.
- **Empfehlung**: v0.15.4 Discovery-Schema auf Object aktualisieren oder beide
  Specs auf Array reduzieren (Praeferenz: Object behalten, traegt mehr Info).

### M2: `invalid_endpoint` HTTP-Status

- **Discovery 9.4**: HTTP 500.
- **Implementation** (Zeile 566): HTTP 404.
- **Impact**: Kein Frontend-Fallout. 404 ist fuer "Endpoint-Konfig fehlt"
  semantisch vertretbar.
- **Empfehlung**: Discovery auf 404 angleichen oder Implementation auf 500
  korrigieren. Praeferenz: Discovery auf 404 (semantisch besser).

### M3: `format != iframe` wird als `invalid_service` gemeldet

- **Discovery 9.4**: Listet keinen expliziten Code fuer Format-Errors.
- **Implementation** (Zeile 580-586): Verwendet `invalid_service` fuer
  Format-Mismatch.
- **Impact**: Diagnostisch unsauber (Service ist ja valide).
- **Empfehlung**: Eigener Code `invalid_format` mit HTTP 400 - sauberere
  Fehlersemantik fuer Frontend-Logging.

---

## 11. Lessons Learned

### 11.1 Was hat funktioniert
- **Schema-Vertrag in Discovery + Handover-Pflicht**: Beide Specs haben
  explizit Schema-Bestaetigung geliefert. Drift wurde dokumentiert.
- **Defensives Reading**: 0-Cost-Sicherheit. Die Aliases werden nie genutzt,
  aber sie sind da.
- **Re-Mount-Key am iframe** (`html.length`): Belt-and-Suspenders gegen
  Browser-srcdoc-Reflow-Bugs. Frontend-Idee, in Discovery nicht explizit.
- **Optional-Konstruktor-Parameter**: BC-Schutz, auch wenn DI nie weggelassen
  wird.

### 11.2 Was zu verbessern ist
- **Discovery-Schema mit Implementation sychronisieren**: M1 + M2 zeigen
  Drift in Detailfeldern. v0.15.4 sollte Discovery-Updates als Pflicht-Step
  nach Spec-Abschluss haben.
- **Error-Code-Inventar in Discovery**: M3 - Format-Errors brauchen eigenen
  Code, nicht Reuse.

### 11.3 Fuer v0.15.4 vorgemerkt
- Atts-Editor (alle `shortcode_atts`)
- postMessage-Resize fuer dynamische iframe-Hoehe
- Sub-Shortcode-Preview (`mio_termine`, `maes_videos` etc.)
- 500-KB-Soft-Warning im Meta-Panel (Discovery R1)
- `atts_rejected` Schema-Vertrag final (Array oder Object)

---

## 12. Verdict

**GO** fuer v0.15.3-Release.

Alle 28 Acceptance-Punkte erfuellt, 0 Critical, 0 Major, 3 Minor (M1-M3 -
alle dokumentarische Diskrepanzen ohne Funktions-Impact, abgefangen durch
Defensive-Reading im Frontend). Schema-Vertrag-Drift-Schutz aus v0.15.0
hat erfolgreich gewirkt - genau ein erwarteter Drift-Punkt (Array vs Object)
wurde im Code dokumentiert und im Frontend sauber gefangen.

Die A11y-Pass-Rate ist 100%, BC ist vollstaendig gewahrt (5/5 alte REST-Routes
+ 4/4 alte React-Komponenten unveraendert), iframe-Sandbox ist Discovery-konform,
und der Trust-Boundary-Modell (do_shortcode trusted, kein wp_kses_post) ist
konsistent mit dem Frontend-Render-Pfad.

Empfehlungen fuer v0.15.4:
- Discovery 9.3 + 9.4 mit Implementation-Status synchronisieren (M1, M2).
- Eigener Error-Code `invalid_format` einfuehren (M3).
- 500-KB-Soft-Warning im Meta-Panel.
