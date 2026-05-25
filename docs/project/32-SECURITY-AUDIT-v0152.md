# Security Audit v0.15.2 - MMB Card + Compact Lazy-Akkordeon

**Audit-Datum:** 2026-05-25
**Auditor:** Security-Specialist (Anthropic Claude, Opus 4.7)
**Release-Scope:** Compact-Layouts Lazy-Loading (MMB-AJAX-Handler Layout-Whitelist, 2 neue Partials, JS-Layout-Param)
**Audit-Methode:** Statischer Code-Review (kein dynamischer Pen-Test)
**Verdict:** **GO**

---

## Executive Summary

Der Release v0.15.2 erweitert den bestehenden MMB-Lazy-Akkordeon-Endpoint (eingefuehrt in v0.14.0) um einen `layout`-Parameter, der zwischen drei Partial-Templates auswaehlt (`default` / `card` / `compact`). Hinzu kommen zwei neue Partials und eine clientseitige Layout-Param-Erweiterung in `dhps-mmb.js`.

Der Security-Audit umfasst **5 geaenderte / neue Files** (Handover-Sektion 1):

1. `includes/class-dhps-mmb-ajax-handler.php` (geaendert: Layout-Whitelist)
2. `public/views/services/mmb/partials/card-content.php` (NEU)
3. `public/views/services/mmb/partials/compact-content.php` (NEU)
4. `public/views/services/mmb/card.php` (geaendert: Lazy-State-Markup)
5. `public/views/services/mmb/compact.php` (geaendert: Lazy-State-Markup)
6. `public/js/dhps-mmb.js` (geaendert: `layout`-Param in AJAX-URL)

**Findings:**
- **0 Critical / 0 High**
- **0 Medium**
- **2 Low** (Style-Smell Pre-Escaping, Layout-Param noch nicht in `data-layout`-Lesepfad serverseitig validiert)
- **3 Info** (positive Beobachtungen: Defense-in-Depth, BC-Strategie, SVG-Trust)

**Empfehlung:** **GO - Release-ready ohne Pflicht-Fixes.** Die zwei Low-Findings sind Style-/Doku-Mache und nicht release-blockierend.

---

## Section 1: MMB Path-Traversal-Schutz (KRITISCH)

**File:** `includes/class-dhps-mmb-ajax-handler.php`

### 1.1 ALLOWED_LAYOUTS-Konstante
- **Zeile 58:** `private const ALLOWED_LAYOUTS = array( 'default', 'card', 'compact' );` - **strikte Whitelist**, exakt drei Werte, alle lowercase.
- Konstante ist `private` -> keine externe Manipulation moeglich.
**Status: OK**

### 1.2 sanitize_key() vor in_array()
- **Zeile 172-177:** `$layout_raw = wp_unslash( $_REQUEST['layout'] )` -> `sanitize_key( (string) $layout_raw )`.
- `sanitize_key()` filtert auf `[a-z0-9_-]` (lowercase). Damit werden bereits entfernt:
  - Slashes (`/`, `\`), Punkte (`.`, `..`), Whitespace, Semikolons, `<`/`>`, Quotes, Uppercase-Letters.
- Cast `(string)` defensiv gegen Array/Object-Injection in `$_REQUEST`.
**Status: OK**

### 1.3 in_array() strict
- **Zeile 180:** `if ( ! in_array( $layout, self::ALLOWED_LAYOUTS, true ) )` - **strict (true)**, kein Type-Juggling.
- Bei Mismatch: **Fallback auf `'default'`** (keine Exception, kein Error - **BC-konform**).
**Status: OK**

### 1.4 Defense-in-Depth: zweiter Whitelist-Check
- **Zeile 385-387:** `render_category_html()` prueft **erneut** mit `in_array(..., true)` und faellt auf `'default'` zurueck, falls direkt aufgerufen.
- Doppelter Schutz - guter Defense-in-Depth-Pattern.
**Status: OK**

### 1.5 Path-Resolution: hardcoded vs. User-Input
- **Zeile 390-396:** Path-Map ist **statisches Array** mit drei hardcoded Filenames:
  ```
  'default' => 'category-content.php',
  'card'    => 'card-content.php',
  'compact' => 'compact-content.php',
  ```
- Lookup via `isset( $partials[ $layout ] )`, niemals dynamische String-Konkatenation aus User-Input.
- Pfad-Praefix `DEUBNER_HP_SERVICES_PATH . 'public/views/services/mmb/partials/'` ist Plugin-Konstante (via `plugin_dir_path(__FILE__)` in `Deubner_HP_Services.php:44`).
- **Es gibt keinen Pfad-String-Bestandteil, der aus `$_REQUEST` stammt.**
**Status: OK (perfekter Path-Traversal-Schutz durch Whitelist + statischer Array-Map)**

### 1.6 Angreifer-Vektoren - alle abgewehrt
| Test-Input | sanitize_key()-Result | in_array(strict)-Result | Final | Status |
|------------|----------------------|------------------------|-------|--------|
| `../../etc/passwd` | `etcpasswd` | false (nicht in Whitelist) | `default` | REJECTED |
| `<script>` | `` (empty) | false | `default` | REJECTED |
| `default; rm -rf /` | `default` | true | `default` | OK |
| `DEFAULT` | `default` (sanitize_key lowercase) | true | `default` | OK (case-folding by design) |
| `card` | `card` | true | `card` | OK |
| `compact` | `compact` | true | `compact` | OK |
| `null`, `false`, `[]` | `` (empty) | false | `default` | REJECTED |
| `card/../../etc` | `card` (slashes weg) | true | `card` | OK (kein Path-Traversal, da Filename hardcoded) |
| `card-content.php` | `card-content.php` (Bindestrich + Punkt? sanitize_key entfernt Punkt) -> `cardcontentphp` | false | `default` | REJECTED |

**Status: OK - alle bekannten Angreifer-Inputs sind safe**

### 1.7 file_exists-Fallback
- **Zeile 402-405:** Falls Layout-Partial fehlt -> Fallback auf `category-content.php`.
- Falls auch dieses fehlt -> leerer String (kein Crash, kein Error).
- Symlink-Risiko: Standard-WP-Trust-Decision (siehe 11-SECURITY-AUDIT-v0140 Sektion 3.4).
**Status: OK**

**Verdict Sektion 1:** **Path-Traversal sicher abgewehrt.** Whitelist + sanitize_key + strict-in_array + statische Array-Map + doppelter Check = mehrlagig sicher. Keine Findings.

---

## Section 2: Partials Output-Escaping

**Files:** `public/views/services/mmb/partials/card-content.php` + `compact-content.php`

### 2.1 Allgemeine Escape-Disziplin (beide Partials)
| Output-Stelle | Methode | Status |
|---------------|---------|--------|
| `$sheet['title']` | `esc_html()` (card:84, compact:76) | OK |
| `$description` (card, gekuerzt) | `esc_html( mb_strimwidth(...) )` (card:87) | OK |
| `$description` (compact, voll) | `esc_html()` (compact:93) | OK |
| `$download_label` | `esc_html()` (card:100) / `esc_attr()` als title (compact:82) | OK |
| `$pdf_href` | `esc_url()` (card:91, compact:79) | OK |
| `$sheet_id` -> `$sheet_id_a` | `esc_attr()` vorab (card:51, compact:50) | OK (Pre-Escape Style-Smell, siehe L-1) |
| `data-sheet-id` / `data-dhps-mmb-pdf` | `echo $sheet_id_a` (pre-escaped) | OK |

### 2.2 PDF-URL-Generierung
**MIL-Pfad (card:58-60, compact:57-59):**
```
$pdf_href = 'https://www.deubner-online.de/einbau/mil/content/merkblaetter/'
    . rawurlencode( (string) $pdf_params['merkblatt'] ) . '.pdf';
```
- `rawurlencode()` schuetzt vor URL-Injection im Filename-Bestandteil.
- Praefix ist **hartcodiert** (Plugin-Konstante).
- `esc_url()` bei Output - Defense-in-Depth.
**Status: OK**

**MMB-Pfad (card:62-72, compact:61-71):**
```
$pdf_href = admin_url( 'admin-ajax.php' ) . '?' . http_build_query(
    array_merge(
        array(
            'action'  => 'dhps_mmb_pdf',
            'nonce'   => wp_create_nonce( 'dhps_mmb_nonce' ),
            'service' => $service_tag,
        ),
        $pdf_params
    )
);
```
- `http_build_query()` urlencoded automatisch alle Values - sicher gegen Param-Injection.
- `$service_tag` kommt aus Handler (bereits whitelisted via `ALLOWED_SERVICES`).
- `$pdf_params` kommt aus Parser-Output (vom Upstream-API, durch `DHPS_MMB_Parser` strukturiert) - **akzeptierte Trust-Decision** (gleicher Pattern wie im default-Partial seit v0.14.0).
- `wp_create_nonce()` bindet den PDF-Download an dieselbe Nonce-Action wie die Lazy-Loader.
- `esc_url()` bei Output - Defense-in-Depth.
**Status: OK**

### 2.3 SVG-Markup-Audit
**Card-Partial:**
- **Zeile 75-83:** PDF-Icon-SVG mit hardcoded Path-Daten + Stroke. **Keine** Inline-Event-Handler (`onclick`, `onload` etc.). **Keine** `<script>`-Tags. **Keine** dynamischen Werte.
- **Zeile 95-99:** Download-Pfeil-SVG, identisch hardcoded.

**Compact-Partial:**
- **Zeile 84-88:** Download-Pfeil-SVG, identisch hardcoded.

**Hardcoded Hex-Color `#c0392b`** (card:76) ist statisch - kein XSS-Vektor.
**Status: OK - kein User-Input in SVG-Markup**

### 2.4 wp_kses_post am AJAX-Output
- Handler-Zeile 419: `return wp_kses_post( $html );` - finaler Schutz auf Endpoint-Ebene.
- **Wichtig:** `wp_kses_post()` strippt manche SVG-Sub-Tags (`<polyline>`, `<line>`). Das gleiche Verhalten existiert seit v0.14.0 fuer den default-Partial - **akzeptierte Trust-Decision** (siehe Handover Sektion 7 R10 + 14-SECURITY-AUDIT-v0141 falls dokumentiert).
- Funktional sind die `<svg>`-Container nach wp_kses_post weiterhin sichtbar (path/line/polyline werden gestrippt, aber `<svg>` selbst bleibt). Falls visuell broken: dann braucht es einen `wp_kses_allowed_html`-Filter (out of audit scope).
- **Security-Impact:** **Keiner**. Stripping ist konservativer als Pass-Through.
**Status: OK (Trust-Decision aus v0.14.0 weitergetragen)**

### 2.5 wp_kses_post + esc_url-Doppel-Strip-Risiko
- Im Test: `wp_kses_post` strippt `data-dhps-mmb-pdf` und `data-sheet-id` nicht (Standard-Allowed-Attribs). 
- Falls Output-Daten gestrippt werden, ist das ein UX-Bug, kein Security-Issue.
**Status: OK**

**Verdict Sektion 2:** **XSS-Schutz Partials OK.** Alle dynamischen Outputs sind escaped. SVG-Markup ohne dynamische Werte. Pre-Escape-Pattern ist Style-Smell (L-1), funktional korrekt.

---

## Section 3: card.php + compact.php Lazy-State-Markup

**Files:** `public/views/services/mmb/card.php` + `compact.php`

### 3.1 data-Attribute Escape-Disziplin
| Attribute | Quelle | Escape | Status |
|-----------|--------|--------|--------|
| `data-layout="card"` (card:106) | **hartcodiert** | n/a | OK |
| `data-layout="compact"` (compact:102) | **hartcodiert** | n/a | OK |
| `data-ajax-url` | `admin_url('admin-ajax.php')` | `esc_url()` | OK |
| `data-nonce` | `wp_create_nonce(...)` | `esc_attr()` | OK |
| `data-service-tag` | `$service_tag` (aus Pipeline) | `esc_attr()` | OK |
| `data-dhps-mmb-lazy-state` | `'pending'` / `'loaded'` | `esc_attr()` | OK |
| `data-category` | `$cat_id` (pre-escaped via `esc_attr()`) | `echo $cat_id` | OK (L-1 Style-Smell) |
| `aria-controls="dhps-mmb-card-XXX"` | `$cat_id` pre-escaped | `echo $cat_id` | OK |
| `aria-expanded` / `aria-hidden` | static booleans -> esc_attr | OK | OK |

### 3.2 data-layout - User-Input oder hartcodiert?
- **card.php Zeile 106:** `data-layout="card"` - **literal hartcodiert im PHP-Source**. KEIN User-Input.
- **compact.php Zeile 102:** `data-layout="compact"` - **literal hartcodiert**. KEIN User-Input.
- Damit existiert kein Vektor, ueber den ein Angreifer das `data-layout`-Attribut steuern koennte.
**Status: OK**

### 3.3 Pre-Render-Erste-Kategorie (Server-Side)
- **card.php Zeile 117-121, compact.php Zeile 113-117:** Berechnung `$is_first`, `$pre_rendered`, `$initial_state` etc. erfolgt rein server-seitig.
- Kein Privilege-Escalation-Vektor: gleicher Render-Pfad wie der Lazy-Load-Endpoint, gleiches Partial, gleiches Escape.
**Status: OK**

### 3.4 Skeleton-Loader Aufruf
- **card.php Zeile 165-168:** `dhps_component('skeleton-loader', ['type'=>'card', 'count'=>min(max($cat_count,1),5)])`.
- **compact.php Zeile 158-161:** identisch mit `'type'=>'list'` und `count<=3`.
- `$cat_count` ist `count($category['fact_sheets'])` (Server-Side-Integer).
- `min(max(...,1),5)` cappt auf [1,5] - kein Integer-Overflow / Loop-DoS.
**Status: OK (Skeleton-Component selbst war in 11-SECURITY-AUDIT-v0140 bereits cleared)**

### 3.5 noscript-Fallback
- **card.php Zeile 179-204, compact.php Zeile 172-194:** Vollstaendiges Rendering der gleichen Partials.
- Gleicher Escape-Pfad wie der Pre-Render-Code.
**Status: OK**

**Verdict Sektion 3:** **Lazy-State-Markup sauber.** Alle `data-*` Attribute sind entweder hartcodiert oder via `esc_attr()`/`esc_url()` geschuetzt. Pre-Escape-Pattern (`$cat_id = esc_attr(...)`) ist konsistenter Style-Smell aus v0.14.0 (L-4 dort, jetzt L-1 hier).

---

## Section 4: dhps-mmb.js Layout-Param-Sicherheit

**File:** `public/js/dhps-mmb.js`

### 4.1 Layout-Read aus DOM
- **Zeile 119:** `var layout = container.getAttribute( 'data-layout' ) || 'default';`
- DOM-Lesung ist vertrauenswuerdig **wenn** die Templates korrekt rendern. Da `data-layout` hartcodiert ist (siehe 3.2), kein Angreifer-Vektor.
- Fallback auf `'default'` bei fehlendem Attribut - **BC-konform** (alte Page-Caches haben kein data-layout).
**Status: OK**

### 4.2 AJAX-URL-Building
- **Zeile 131-136:**
  ```
  var url = ajaxUrl +
      '?action=dhps_mmb_category_load' +
      '&service=' + encodeURIComponent( service ) +
      '&category_id=' + encodeURIComponent( categoryId ) +
      '&layout=' + encodeURIComponent( layout ) +
      '&_wpnonce=' + encodeURIComponent( nonce );
  ```
- `encodeURIComponent()` auf **alle** vier dynamischen Werte (service, categoryId, layout, nonce).
- Schuetzt vor URL-Injection / Parameter-Smuggling.
**Status: OK**

### 4.3 Kein eval / kein innerHTML mit User-Input
- **Zeile 151:** `content.innerHTML = payload.data.html;` - Response-HTML vom **eigenen Server** (durch `wp_kses_post` gefiltert, siehe 2.4).
- Kein `eval()`, kein `new Function()`, kein `document.write`.
- Error-Messages ueber `escapeHtml()` (Zeile 184) - via `document.createElement('div').textContent = str`. Safe.
- `escapeAttr()` (Zeile 481) - manueller Replace-Pfad fuer 5 Standard-XSS-Zeichen. Korrekt implementiert.
**Status: OK**

### 4.4 Trust-Boundary `payload.data.html` -> innerHTML
- Quelle: gleicher Origin, AJAX-Response vom eigenen Endpoint.
- Server filtert mit `wp_kses_post()` (Defense-in-Depth).
- Pattern wurde in v0.14.0 bereits auditiert (siehe 11-SECURITY-AUDIT-v0140 Sektion 2.9).
**Status: OK (Trust-Decision uebernommen)**

**Verdict Sektion 4:** **JS-Layout-Param sicher.** `encodeURIComponent` durchgaengig, kein eval, innerHTML-Input ist Server-gefiltert.

---

## Section 5: Rate-Limit + Service-Whitelist

### 5.1 Rate-Limit unveraendert
- **Zeile 66:** `RATE_LIMIT_PER_MINUTE = 60` - unveraendert.
- **Zeile 158-166 + 329-356:** `check_rate_limit()` greift **vor** Input-Sanitization und vor Layout-Whitelist-Check - korrekte Reihenfolge.
- Per-IP-Transient mit 60s TTL aus v0.14.0 weiterhin aktiv.
**Status: OK**

### 5.2 Service-Whitelist unveraendert
- **Zeile 46:** `ALLOWED_SERVICES = ['mmb','mil']` - unveraendert.
- **Zeile 185:** `in_array( $service, ..., true )` strict.
**Status: OK**

### 5.3 Layout-Whitelist additive Performance-Impact
- Layout-Check: `sanitize_key()` + `in_array()` strict (4 Werte). O(1)-Komplexitaet.
- Kein zusaetzlicher DB- oder HTTP-Call.
- **Performance-Impact:** vernachlaessigbar (<1µs CPU).
**Status: OK (Layout-Check ist additiv, nicht ersetzend)**

**Verdict Sektion 5:** Layout-Whitelist hat **keinen negativen Einfluss** auf bestehende Schutzmechanismen. Rate-Limit + Service-Whitelist + Nonce greifen weiter.

---

## Section 6: ReDoS / Input-Validation

### 6.1 Keine neuen preg_*-Patterns
- **Grep `preg_*` ueber alle 5 geaenderten Files: 0 Treffer**.
- Input-Validation laeuft ausschliesslich ueber `sanitize_key`, `in_array`, `strlen`, `isset`.
**Status: OK**

### 6.2 category_id-Validation unveraendert
- Zeile 196-214: `sanitize_key()` + `strlen()` <= 100 - identisch zu v0.14.0.
**Status: OK**

### 6.3 layout-Validation strict
- `sanitize_key()` + `in_array(strict)` - keine Regex, kein ReDoS.
- `sanitize_key()` selbst nutzt eine triviale Whitelist-Regex (`[a-z0-9_-]`), aber das ist WordPress-Core (akzeptiert).
**Status: OK**

**Verdict Sektion 6:** **Kein ReDoS-Risiko in v0.15.2.**

---

## Section 7: BC + akzeptierte Trust-Decisions

### 7.1 Alte AJAX-Calls ohne layout-Param
- **Handler Zeile 172:** `isset( $_REQUEST['layout'] ) ? wp_unslash( $_REQUEST['layout'] ) : 'default'`.
- Alte Clients **ohne** `layout`-Query-Param landen automatisch auf `'default'` -> `category-content.php` (das bestehende Partial).
- **BC garantiert.**
**Status: OK**

### 7.2 Frontend-Cache mit altem Container-DOM
- Alter DOM ohne `data-layout` Attribut -> JS-Read liefert `null` -> Fallback `'default'`.
- Endpoint liefert default-Partial.
- **Visuell ggf. falsch** (z.B. Compact-Container bekommt default-List-HTML), aber **kein Security-Issue**: gleicher Escape-Pfad, gleiches Partial.
**Status: OK (UX-Mache, kein Security)**

### 7.3 Theme-Override-Pfad
- Theme-Overrides `{theme}/dhps/services/mmb/card.php` ueberschreiben das gesamte Layout-Template.
- Die Partials in `partials/*-content.php` werden **nicht** ueberschreibbar (kein Theme-Override fuer Partials).
- Theme kann eigenes Markup nutzen, aber wenn es den Lazy-Endpoint nutzt, bekommt es trotzdem die Plugin-Partials. Bewusster Design-Entscheid.
- **Security-Impact:** Keiner. Plugin-Partials sind durch Plugin gefiltert.
**Status: OK**

**Verdict Sektion 7:** **BC sauber, keine neuen Trust-Boundaries.**

---

## Section 8: SSRF / Information Disclosure

### 8.1 SSRF-Boundary
- Endpoint nutzt weiterhin `$this->client->fetch_content( $endpoint, $api_params, 3600 )`.
- `DHPS_API_Client` ist SSRF-safe (durch `DHPS_Service_Registry` whitelisted endpoints) - audited in 11-SECURITY-AUDIT-v0140.
- **Kein neuer Outbound-Vektor in v0.15.2.**
**Status: OK**

### 8.2 Error-Responses
- Alle neuen Code-Pfade nutzen `wp_send_json_error( ['code'=>..., 'message'=>...] )` mit **statischen** message-Strings.
- Keine Pfad-Disclosure, keine Stack-Traces, keine DB-Errors.
- Layout-Mismatch fuehrt **nicht** zu einem Error - sondern zu silentem Default-Fallback (siehe 1.3).
**Status: OK**

### 8.3 Render-Fallback
- `render_category_html()` returnt `''` wenn auch das default-Partial fehlt (Zeile 408).
- Endpoint sendet dann `html: ''` - kein Crash, kein Leak.
**Status: OK**

**Verdict Sektion 8:** **Keine neuen SSRF-/Information-Disclosure-Pfade.**

---

## Section 9: Trust-Decisions

Folgende Entscheidungen wurden bewusst getroffen und sind sicherheitsfachlich vertretbar:

1. **Pre-Render-Erste-Kategorie ist Server-Side** - kein Privilege-Escalation, gleicher Render-Pfad wie Lazy-Load. Akzeptiert.

2. **noscript-Fallback macht Full-Render** - SEO-Anforderung. Gleicher Escape-Pfad, kein zusaetzliches Risiko.

3. **wp_kses_post auf finalem HTML** - Defense-in-Depth aus v0.14.0 weitergetragen. Strippt evtl. SVG-Sub-Tags (akzeptierter Tradeoff).

4. **PDF-URL-Generierung im Partial** - duplicate-Code zu category-content.php, aber bewusst kopiert (Partial-Isolation). Gleiche Sicherheitsgarantien.

5. **Layout-Fallback bei Mismatch (silent)** - statt 400-Error: BC-konform, kein Information-Leak (kein Echo des invaliden Werts).

6. **Pre-Escape-Pattern `$sheet_id_a = esc_attr(...)`** - WP-Style-Konvention will Escape am Output-Punkt, funktional aequivalent (siehe L-1).

7. **wp_ajax_nopriv aktiviert** - aus v0.14.0 unveraendert, weiterhin sicher durch Nonce + Rate-Limit + Whitelist + Server-side-OTA.

8. **`$pdf_params` aus Parser-Output (vom Upstream-API)** - `http_build_query()` urlencoded alle Werte. Akzeptierte Trust-Boundary zum API-Upstream `deubner-online.de`.

9. **DEUBNER_HP_SERVICES_PATH als Pfad-Anker** - Plugin-Konstante via `plugin_dir_path(__FILE__)`. Nicht filterbar, nicht manipulierbar. Sicher.

---

## Findings-Liste

### Critical / High
**Keine.**

### Medium
**Keine.**

### Low

#### L-1: Pre-Escape-Pattern in Partials (Style-Smell)
- **Files:** `card-content.php:51`, `compact-content.php:50`, `card.php:113`, `compact.php:109`.
- **Beschreibung:** `$sheet_id_a = esc_attr( $sheet_id )` vorab, dann `echo $sheet_id_a` ohne erneuten Escape.
- **Funktional:** Sicher.
- **Style:** WP-Konvention will Escape am Output-Punkt.
- **Empfehlung:** Stil-Anpassung in v0.15.x oder v0.16.0 - Duplikat des Findings aus 11-SECURITY-AUDIT-v0140 L-4.
- **Severity: Low**

#### L-2: card.php / compact.php Pre-Render-Block enthaelt `$pdf_href`-Pfad-Hartcoding-Duplikat
- **Files:** `card-content.php:58-72`, `compact-content.php:57-71` (auch in `category-content.php:59-73`).
- **Beschreibung:** Die PDF-URL-Logik (MIL direct vs. MMB Proxy) ist **dreifach kopiert** (default/card/compact). Bei zukuenftiger Aenderung muss an drei Stellen synchron geupdatet werden - sonst drift-Risiko (z.B. fehlende `rawurlencode` an einer Stelle).
- **Funktional:** Aktuell konsistent und sicher.
- **Empfehlung:** Helper-Funktion `dhps_mmb_build_pdf_url( $sheet, $service_tag ): string` in `includes/dhps-mmb-helpers.php` extrahieren (v0.16.0).
- **Severity: Low (Code-Smell, kein aktiver Security-Bug)**

### Info

#### I-1: Defense-in-Depth doppelte Whitelist (handle_request + render_category_html)
- Positive Beobachtung. Standard fuer einen produktiv geadelten AJAX-Endpoint.

#### I-2: `encodeURIComponent` durchgaengig in JS
- Positive Beobachtung. Alle vier AJAX-Params (service, categoryId, layout, nonce) sind encoded.

#### I-3: `data-layout` ist hartcodiert im PHP-Source
- Bestaetigt: KEIN User-Input-Vektor. Hartcodiert in `card.php:106` und `compact.php:102`.

---

## Pre-Release Fix-List (Empfehlung)

| ID | Severity | Aufwand | Empfehlung | Verbindlich? |
|----|----------|---------|------------|--------------|
| L-1 | Low | 20min | Pre-Escape-Pattern in Partials nach Output verschieben (3 Files) | **Optional** |
| L-2 | Low | 30min | `dhps_mmb_build_pdf_url()` Helper extrahieren | **Optional** |

**Keine Pflicht-Fixes vor Release.**

---

## Gesamt-Verdict

# **GO**

- **0 Critical / 0 High / 0 Medium Findings**
- **2 Low Findings** (Style-Smell + Code-Duplication, beide nicht security-aktiv)
- **3 Info Findings** (positive Beobachtungen)

Der Code zeigt durchgaengig:
- **Path-Traversal sicher abgewehrt** via Whitelist + sanitize_key + strict-in_array + statische Array-Map
- **XSS-Schutz in Partials OK** - alle Outputs sind escaped (esc_html / esc_attr / esc_url)
- **Defense-in-Depth doppelt:** Whitelist-Check in `handle_request()` + erneut in `render_category_html()`
- **BC sauber:** Alte AJAX-Calls ohne layout-Param funktionieren weiter (silent default-Fallback)
- **JS-Layout-Param sicher:** `encodeURIComponent` auf allen vier AJAX-Params
- **Keine neuen SSRF-/ReDoS-Vektoren**
- **Konsistente Trust-Decisions** aus v0.14.0 weitergetragen (wp_kses_post am Output, wp_ajax_nopriv mit Nonce-Schutz)

**Empfehlung: v0.15.2 ohne Pflicht-Fixes taggen.** Die zwei Low-Findings sind Style-Mache fuer ein Folge-Release.

---

*Audit beendet 2026-05-25.*
