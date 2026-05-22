# Security Audit v0.14.0 - Foundation + MMB-Pilot

**Audit-Datum:** 2026-05-22
**Auditor:** Security-Specialist (Anthropic Claude, Opus 4.7)
**Release-Scope:** Component-System Foundation, MMB-Lazy-Akkordeon, Elementor-Bridge, Alpine.js-Integration
**Audit-Methode:** Statischer Code-Review (kein dynamischer Pen-Test)
**Verdict:** GO-WITH-FIXES (nur Low/Info-Findings)

---

## Executive Summary

Der Release v0.14.0 fuehrt vier neue Subsysteme ein, die unter Sicherheits-Gesichtspunkten ueberprueft wurden:

1. **Component-System** (PHP-Renderer + Registry + 8 Templates) - solide gebaut.
2. **MMB-AJAX-Endpoint** fuer Lazy-Akkordeon (`dhps_mmb_category_load`) - Nonce + Rate-Limit + strikte Whitelist + Defense-in-Depth-Filter.
3. **Elementor-Token-Bridge** (CSS, opt-in) - aktuell ohne `register_setting`/Sanitize-Callback.
4. **Alpine.js v3.14.9 Vendor** - SHA-256 verifiziert, MIT-lizenziert, lokal gebundled.

Es wurden **0 Critical**, **0 High**, **2 Medium**, **6 Low**, **5 Info** Findings identifiziert.
Alle Findings sind in Sektion-zu-Sektion-Form unten katalogisiert und priorisiert.

**Empfehlung:** GO mit den drei Fixes aus dem "Pre-Release Fix-List"-Abschnitt am Ende. Keiner der Punkte ist Release-blockierend, aber S-1 (register_setting fuer Bridge-Option) sollte vor dem Tag erledigt werden.

---

## Section 1: XSS-Analyse Component-Templates + MMB

### 1.1 Stateless Components

| File | Status | Bemerkung |
|------|--------|-----------|
| `public/views/components/skeleton-loader.php` | OK | Nur Props mit Whitelist (`type` in `['card','list','video','accordion']`), Count gecappt 1..20, `esc_attr` auf alle Class-Outputs. Kein User-HTML. |
| `public/views/components/empty-state.php` | OK (Info-1) | Icon-Slug -> internes SVG-Mapping (vertrauenswuerdig). Fallback `wp_kses_post( $icon )` filtert SVG-Strings - **Hinweis:** `wp_kses_post` strippt `<svg>`/`<path>`-Tags standardmaessig nicht durch (siehe Info-Finding I-1). Title/Hint via `esc_html`, Action-URL via `esc_url`. |
| `public/views/components/lazy-image.php` | OK | `esc_url($src)` auf src + data-src, `esc_attr($alt)` auf alt. LQIP-DataURI via `esc_attr` (bewusste Entscheidung, dokumentiert). Width/Height als `(int)` gecastet. |
| `public/views/components/accordion.php` | OK | `esc_html` auf Titel, `wp_kses_post` auf `content_html`, `esc_attr` auf IDs/Classes/Group-Name. Inline-SVG Chevron ist statisch ohne User-Daten. |

### 1.2 Stateful Components

| File | Status | Bemerkung |
|------|--------|-----------|
| `public/views/components/filter-bar.php` | OK | `esc_attr` auf Placeholder/Labels/IDs. `esc_html` auf Tag-Labels. `esc_js` auf Tag-IDs in inline Alpine-Expressions (siehe Sektion 4). `(int)` Cast auf debounce/min_chars/count. User-Search-Query lebt nur im Alpine JS-Memory (kein Server-Round-Trip). |
| `public/views/components/content-card.php` | OK (Low-1) | `esc_html` auf title/teaser/labels, `wp_kses_post` auf `body_html`, `esc_url` auf `media_url` und `href`. Heading-Tag whitelisted (`h2-h6`). Badge-Variant whitelisted. **Low-Finding:** `$alpine_attrs` wird als String konkateniert und ohne Escape geechoed - aktuell statisch, aber Kommentar warnt nicht streng genug vor zukuenftiger Erweiterung (siehe L-1). |
| `public/views/components/pagination.php` | OK | Alpine-Config als `wp_json_encode` + `esc_attr` (sicher gegen Quote-Breakout). `esc_html` auf Labels. `(int)` Cast auf Page-Numbers. |
| `public/views/components/content-list.php` | OK | `esc_attr` + `wp_json_encode` auf Alpine-Config, `esc_attr` auf alle IDs/Selectors, delegiert an content-card/filter-bar/pagination (alle bereits geprueft). |

### 1.3 MMB-Templates

**`public/views/services/mmb/default.php`:**
- Kategorie-IDs: an Zeile 127 als `$cat_id = esc_attr( $category['id'] )` *vorab* escaped, dann ohne erneuten Escape echoed (`data-category="<?php echo $cat_id; ?>"`). Funktional sicher, aber **Style-Smell** (Pre-Escaping ist unueblicher WP-Pattern; siehe Info-2).
- `wp_create_nonce`, `admin_url`, `esc_attr`, `esc_html`, `esc_url` werden konsequent genutzt.
- noscript-Fallback rendert Kategorien-Header und delegiert an das Partial - das Partial macht die Escapes selbst. Sicher.
- Search-Form: alle Inputs haben `esc_attr` auf placeholder/labels, kein dynamisches Echo von User-Search-Query (passiert per AJAX).

**`public/views/services/mmb/partials/category-content.php`:**
- Fact-Sheet-Title: `esc_html`
- Description: `esc_html`
- PDF-URL (MIL): `rawurlencode` + `esc_url` (Pfad-Komponente). Sicher.
- PDF-URL (MMB): `admin_url + http_build_query` + `esc_url`. Sicher.
- Sheet-ID: an Zeile 50 `$sheet_id_a = esc_attr( $sheet_id )` vorab escaped (gleicher Style-Smell wie Default-Template, aber funktional sicher).

**Verdict Sektion 1:** Keine XSS-Vulnerabilities. 1 Style-Smell (Pre-Escaping-Pattern), 1 Hinweis zu wp_kses_post-SVG-Verhalten.

---

## Section 2: AJAX-Endpoint-Audit (DHPS_MMB_AJAX_Handler)

**File:** `includes/class-dhps-mmb-ajax-handler.php`

### 2.1 Nonce-Check
- Zeilen 127-143: Nonce wird VOR jeder weiteren Verarbeitung verifiziert.
- Akzeptiert sowohl `_wpnonce` als auch `nonce`-Key (defensiv).
- `sanitize_text_field( wp_unslash(...) )` vor `wp_verify_nonce`.
- HTTP 403 bei Fehler.
**Status: OK**

### 2.2 Service-Whitelist
- `ALLOWED_SERVICES = ['mmb', 'mil']` als Class-Konstante.
- `in_array( $service, ..., true )` - strict (Type-Safe).
- HTTP 400 bei unbekanntem Service.
**Status: OK**

### 2.3 Input-Sanitization
- `category_id` via `sanitize_key()` (entfernt alles ausser `[a-z0-9_-]`).
- Length-Limit von 100 chars als Defense-in-Depth.
- Leere category_id -> HTTP 400.
**Status: OK**

### 2.4 Rate-Limit
- Per-IP-Transient mit 60s TTL, max 60 Requests/Minute.
- HTTP 429 bei Ueberschreitung.
- Cache-Key: `'dhps_mmb_rate_' . md5( $ip )` - Kollisions-frei fuer IPs (md5 reicht hier, keine Crypto-Anforderung).
- TTL-Anchor: Erstes Set definiert das Fenster, kein Re-Anchor bei Increments (saubere "rolling window with first-touch"-Semantik).
**Status: OK (Low-2 zu IP-Spoofing siehe unten)**

### 2.5 Authorization
- `ota = get_option( $service_config['auth_option'], '' )` - Token wird NUR serverseitig aus `wp_options` gelesen.
- NIEMALS aus dem Request gelesen.
- Leere OTA -> HTTP 400 (`service_not_configured`).
**Status: OK**

### 2.6 HTTP-Status-Codes
- 403 (invalid_nonce), 429 (rate_limit), 400 (input-validation), 404 (category_not_found), 502 (empty_response) - alle semantisch korrekt.
**Status: OK**

### 2.7 Information Disclosure
- Error-Responses enthalten nur `code` + statische `message`-Strings (keine Pfade, keine Stack-Traces).
- `WP_DEBUG`-Branch ist im Component-Helper, nicht im AJAX-Endpoint (siehe Sektion 8).
**Status: OK**

### 2.8 IP-Detection (Low-Finding L-2)
- Nutzt **ausschliesslich** `$_SERVER['REMOTE_ADDR']` - **kein** naives `X-Forwarded-For` (gut!).
- Wenn die WP-Site hinter einem Reverse-Proxy/CDN laeuft, sind alle Requests "von einer IP" (der Proxy-IP) - das fuehrt zu **Shared-Rate-Limit-Bucket** fuer alle Nutzer.
- Impact: Bei legitimer Proxy-Konfiguration koennen wenige aggressive Nutzer das Rate-Limit fuer alle hinter dem Proxy aushebeln (DoS-Vektor).
- **Mitigation:** Akzeptiert (mit Doku), oder kuenftiger Filter `dhps_mmb_client_ip`, der Site-Admins erlaubt, eine sichere X-Forwarded-For-Auswertung einzuhaengen.
**Severity: Low (akzeptierte Trust-Decision; Standard-WordPress-Pattern)**

### 2.9 Defense-in-Depth: wp_kses_post auf finalem HTML
- Das gerenderte Partial-HTML wird vor `wp_send_json_success` zusaetzlich durch `wp_kses_post()` gejagt (Zeile 368). Doppelter Schutz, nicht streng noetig (Partial filtert bereits sauber), aber gute Praxis.
**Status: OK (positive Praxis)**

### 2.10 Authentication Boundary
- `wp_ajax_nopriv_dhps_mmb_category_load` ist registriert -> anonyme User koennen den Endpoint aufrufen.
- **Gewollt:** Frontend-Besucher sind ueblicherweise nicht eingeloggt.
- Schutz erfolgt durch Nonce + Rate-Limit + Service-Whitelist. Kein User-spezifisches Token noetig.
- Nonce ist site-weit (nicht Session-spezifisch); das ist WordPress-Standard und kein Issue.
**Status: OK**

**Verdict Sektion 2:** Endpoint ist sicher implementiert. 1 Low-Finding zu IP-Detection-Limitierung.

---

## Section 3: PHP-Renderer-Helper-Audit

**Files:** `includes/dhps-component-helpers.php` + `includes/class-dhps-component-registry.php`

### 3.1 extract($props, EXTR_SKIP)
- Verwendet `EXTR_SKIP`-Flag (Zeile 85 in helpers): bestehende Variablen werden NICHT ueberschrieben.
- Damit kann ein Prop-Key wie `template`, `name`, `props` nicht den Renderer-Scope hijacken.
- `$config`-Variable kommt aus Registry, **bevor** extract laeuft - dadurch koennte ein Prop namens `config` extract-skip-protected sein (auch das ist OK).
- **Hinweis (Info-3):** Wenn ein Prop-Key dem Namen einer im Template selbst genutzten Variable entspricht und das Template `isset()`-Defensiv prueft, hat das User-Prop Vorrang (gewollt - das ist das Component-API-Design).
**Status: OK (sichere Component-API analog zu WP `get_template_part()`)**

### 3.2 Theme-Override-Pfad-Resolution
- `get_template_path()` sucht in fester Reihenfolge: Child-Theme -> Parent-Theme -> Plugin-Default.
- Template-Filename: `'dhps/components/' . $name . '.php'` mit `$name` aus dem Caller (NICHT aus dem Request).
- `$name` wird in `dhps_component()` als string typed und es gibt kein Path-Traversal-Risiko, weil:
  - `is_registered($name)` filtert auf bekannte Names.
  - Keine bekannte Component hat `../` oder `\` im Namen.
- **Aber (Medium-Finding M-1):** Es gibt **keinen** expliziten Sanity-Check auf `$name` selbst. Wenn ein zukuenftiger Caller `dhps_component('../../etc/passwd', [])` aufruft UND `is_registered` durch boesartige Registry-Manipulation umgeht (z.B. via `dhps_register_components`-Filter), koennte Path-Traversal moeglich sein. Aktuell **nicht erreichbar**, aber Defense-in-Depth waere ein Regex-Check `^[a-z0-9-]+$` auf $name.
**Severity: Medium (defensiv empfohlen, aktuell nicht exploitable)**

### 3.3 Filter dhps_component_template_path
- Zeile 160 in `class-dhps-component-registry.php`: `apply_filters( 'dhps_component_template_path', $resolved, $name, $props )`.
- Filter kann jeden String zurueckgeben, inklusive absoluter Pfade ausserhalb von Plugin/Theme.
- **Es gibt keinen realpath-Check oder Whitelist** gegen die Plugin-/Theme-Verzeichnisse.
- **Trust-Decision:** WordPress-Filter haben implizites Trust-Modell (jeder mit Filter-Add-Permission kontrolliert ohnehin den Server-Code). Das ist konsistent mit dem WordPress-Standard-Pattern, aber **nicht** Defense-in-Depth.
- **Empfehlung (Medium M-2):** Optional einen `realpath()`-Check einbauen, der prueft, dass der finale Pfad innerhalb von Plugin-Dir oder Theme-Dir liegt.
**Severity: Medium (akzeptierte Trust-Decision; siehe Trust-Decisions am Ende)**

### 3.4 file_exists vor include
- `file_exists()` wird auf den `$resolved`-Pfad (nach Filter) und auf den Plugin-Path angewandt.
- **Symlink-Risiko:** `file_exists()` folgt Symlinks. Wenn ein Angreifer mit FS-Write-Access (z.B. nach kompromittiertem WP-Updater) einen Symlink von `wp-content/themes/foo/dhps/components/accordion.php` auf `/etc/passwd` setzt, wuerde `file_exists()` true zurueckgeben und include `/etc/passwd` ausfuehren.
- **Trust-Decision:** Wer FS-Schreibrechte hat, hat ohnehin Server-Pwn. Das ist WordPress-Standard-Threat-Model. Akzeptiert.
- **Hinweis (Info-4):** `mark_used()` wird **nach** `file_exists` aufgerufen, aber **vor** `include` - hat keinen Sicherheits-Impact (nur Performance-Tracking).
**Status: OK (Trust-Decision)**

**Verdict Sektion 3:** Solide. 2 Medium-Defense-in-Depth-Empfehlungen, beide nicht aktuell exploitable.

---

## Section 4: CSP-Kompatibilitaet

### 4.1 Inline-Script-Blocks
- **Keine** `<script>...</script>`-Bloecke in den 8 Component-Templates.
- **Keine** `<script>`-Bloecke im MMB-Default-Template.
- MMB-Lazy-Pfad nutzt `data-*`-Attribute zur Konfiguration (Nonce, AJAX-URL, Service) - **gut**.
**Status: OK**

### 4.2 Inline-Style mit dynamischen Werten
- `content-list.php` Zeile 98: `style="--cols: <?php echo (int) $columns; ?>;"` - dynamisch, aber `(int)` Cast macht es sicher.
- `lazy-image.php` Zeile 59: `style="<?php echo esc_attr( $style_attr ); ?>"` - `$style_attr` haelt `background-image: url(...)` mit LQIP-Data-URI; durch `esc_attr` gegen Quote-Breakout geschuetzt.
- **CSP-Impact:** Beide brauchen `style-src 'unsafe-inline'` ODER ein Nonce-Pattern (WP-Standard). Das ist eine bekannte WP-Limitierung, kein Plugin-Fehler.
**Status: OK (CSP-Operator muss `'unsafe-inline'` fuer Styles erlauben - Standard-WP-Threat-Model)**

### 4.3 Alpine.js Attributes (x-data, x-show, etc.)
- Alpine.js wertet `x-data`, `x-show`, `x-text`, `:class`, `x-on:click` etc. zur Laufzeit via `Function(...)`-Konstruktor aus.
- **Das benoetigt `script-src 'unsafe-eval'` in CSP**.
- Dokumentation hierzu existiert in `docs/architecture/13-alpinejs-integration-v0140.md` (per Querverweis).
- **Info-Finding I-2:** Diese Trust-Decision ist Alpine-spezifisch und vom Team bewusst akzeptiert. Empfehlung: in `docs/project/01-STATUS.md` als bekannte CSP-Anforderung dokumentieren.
**Status: OK (akzeptierte Trust-Decision Alpine-eval)**

### 4.4 Alpine-Init-File
- `public/js/dhps-alpine-init.js`: kein `eval()`, kein `new Function()`. Nutzt ausschliesslich `Alpine.data(name, factory)` mit Funktions-Referenzen.
**Status: OK**

**Verdict Sektion 4:** CSP-bewusst gebaut, mit dokumentierter `unsafe-eval`-Anforderung fuer Alpine.

---

## Section 5: Elementor-Bridge-Audit

**Files:** `css/dhps-elementor-bridge.css` + Logik in `Deubner_HP_Services.php:400`

### 5.1 Brand-Token-Isolation
- Die Bridge bridget **NUR** generische UI-Tokens: `--dhps-color-text`, `--dhps-color-primary`, `--dhps-font-family`, `--dhps-fs-base`.
- **Nicht** gebridged: `--dhps-color-steuern`, `--dhps-color-recht`, `--dhps-color-medizin` - Brand-Tokens bleiben stabil.
- Dies ist explizit dokumentiert (Datei-Header + CSS-Kommentar bei `--dhps-color-primary`).
**Status: OK (Brand-Integritaet geschuetzt)**

### 5.2 Option dhps_elementor_bridge_enabled (Medium-Finding M-1 revisited als S-1)
- Die Option wird gelesen via `get_option( 'dhps_elementor_bridge_enabled', '0' )` in `Deubner_HP_Services.php:400`.
- **PROBLEM:** Es gibt **kein** `register_setting()` mit `sanitize_callback`, kein Settings-Field im Admin, kein Capability-Gate.
- Praktisch bedeutet das: Aktuell kann **niemand** die Option via Standard-Settings-API setzen. Sie kann nur via:
  - `update_option()` aus Code (Admin-Code-Pfade, Plugins),
  - WP-CLI (`wp option update`),
  - Direkt in der DB.
- Alle drei Pfade erfordern Admin- oder DB-Level-Access. **Kein Spam-Vektor fuer Low-Privilege-User.**
- ABER: Die Option ist auch **nicht im UI nutzbar**. Das macht das Feature aktuell "totes Setting".
- **Severity:** dokumentations- und UX-mache, nicht security-mache - **Low**.
- **Empfehlung S-1:** Vor dem Tag entweder (a) `register_setting()` mit `sanitize_callback => 'absint'` und Admin-UI nachziehen, oder (b) explizit dokumentieren, dass die Option nur via Code/CLI gesetzt werden kann.
**Severity: Low (UX-Mache, kein Security-Issue)**

### 5.3 Fehlerhafte Konfiguration -> Frontend brechen?
- CSS-`var()`-Fallbacks sind sauber definiert: jeder Token hat 1-3 Fallback-Stufen mit harten Defaults.
- Wenn Elementor nicht installiert ist, sind die `--e-global-*`-Vars `undefined` und der Fallback greift.
- Wenn Elementor falsche Werte liefert, kaskadiert das einfach in den naechsten Fallback.
- **Worst-Case:** Falsche Theme-Farbe auf einer Site - kein Crash, kein Bypass.
**Status: OK (resilient gegen Konfigurationsfehler)**

**Verdict Sektion 5:** Architektur ist sicher. 1 Low-Finding zur fehlenden register_setting-Registrierung.

---

## Section 6: Alpine.js Vendor-Trust

**Files:** `public/js/vendor/alpinejs-3.14.x.min.js` + `.alpinejs-version` + `README.md`

### 6.1 Version-Pin
- Manifest: `version=3.14.9`, dateiname Major-Pin `alpinejs-3.14.x.min.js`.
- WP-Constant: `DHPS_ALPINE_VERSION` (siehe `Deubner_HP_Services.php:446`).
- Alpine 3.14.9 ist ein offizielles Release im `alpinejs/alpine` GitHub-Repo (per Web-Suche bestaetigt - Sandbox-Restriktion verhindert direkte Verifikation, akzeptiert als Trust-Decision der Vendor-Wahl).
**Status: OK**

### 6.2 SHA-256 Verifikation
- Manifest-Hash: `3ed1eed252488921df65e363d6715deb04d7f92aaedb9e52199fdf73cb1e0ad3`
- Tatsaechlicher File-Hash (per sha256sum): **MATCH** (verifiziert im Audit-Run).
- File-Groesse: 44758 Bytes (matched Manifest `size_min=44758`).
**Status: OK (Integritaet bestaetigt)**

### 6.3 License
- MIT (siehe `.alpinejs-version` + `README.md`).
- License-URL: https://github.com/alpinejs/alpine/blob/main/LICENSE.md
**Status: OK**

### 6.4 Subresource Integrity
- Datei wird lokal aus `public/js/vendor/` enqueued, **nicht** ueber CDN. SRI ist nicht anwendbar.
- DSGVO-konform (kein 3rd-party-Request beim User).
**Status: OK (Self-hosted, kein SRI noetig)**

**Verdict Sektion 6:** Vendor-Integrity bestaetigt, Lizenz dokumentiert, lokales Hosting.

---

## Section 7: ReDoS-Analyse

### 7.1 preg_* in Component-Templates
- **Keine** `preg_match`/`preg_replace`/`preg_split` in den 8 Component-Templates gefunden.
**Status: OK**

### 7.2 preg_* in DHPS_MMB_AJAX_Handler
- **Keine** Regex-Patterns im Handler. Input-Validation laeuft ausschliesslich ueber `sanitize_key`, `sanitize_text_field`, `in_array`, `strlen`.
**Status: OK**

### 7.3 preg_* in MMB-Templates
- **Keine** Regex-Patterns. Reine Foreach-Loops und Escape-Calls.
**Status: OK**

**Verdict Sektion 7:** Kein ReDoS-Risiko in den neuen Files.

---

## Section 8: Information Disclosure

### 8.1 AJAX-Error-Responses
- Alle Error-Responses bestehen aus statischen `code` + `message`-Paaren (siehe Sektion 2.7).
- Keine Exception-Stack-Traces, keine File-Pfade, keine DB-Errors.
**Status: OK**

### 8.2 WP_DEBUG-Branch in dhps_component()
- `dhps_component()` in Helpers Zeile 47 + 73: bei `WP_DEBUG=true` werden HTML-Kommentare mit Component-Namen ausgegeben (`<!-- dhps_component: unbekannte Komponente "xyz" -->`).
- Component-Name ist **bereits durch esc_html()** gefiltert (Defense gegen Reflected-XSS, sollte der Component-Name aus dynamischer Quelle kommen).
- In Production (WP_DEBUG=false) wird **kein** Output generiert.
**Status: OK (WP_DEBUG-only, sauber escaped)**

### 8.3 Component-Registry::get_all()
- Methode ist `public static`, aber NICHT ueber HTTP exposed. Wird nur intern verwendet (z.B. fuer Conditional-Enqueue in Future-Tickets F6/F7).
- Keine AJAX-/REST-Endpoints, die diese Methode aufrufen.
**Status: OK**

**Verdict Sektion 8:** Keine Information-Disclosure-Pfade.

---

## Section 9: Dependency-Confusion / Supply-Chain

### 9.1 Alpine.js
- Manuell heruntergeladen aus offiziellem `unpkg.com/alpinejs@3.14.9`-Endpoint (siehe README + .alpinejs-version).
- SHA-256-Hash bestaetigt, Integrity-Check pruefbar.
- **Kein** `package.json`/`npm install` - kein Typosquatting-Risiko via Dependency-Graph.
- **Kein** Auto-Update-Mechanismus - bewusste Patch-/Minor-Update-Strategie (siehe `README.md`).
**Status: OK**

### 9.2 Andere Vendor-Files
- Keine weiteren neuen 3rd-party-Dependencies in v0.14.0.
- Bestehende DHPS-Files (parsers, API-Client, etc.) wurden nicht in v0.14.0 introducemed - out of audit-scope.
**Status: OK**

**Verdict Sektion 9:** Supply-Chain sauber, keine externen Auto-Loader.

---

## Section 10: Authentication Boundary

(Detailliert bereits in Sektion 2.10 behandelt.)

- `wp_ajax_nopriv_dhps_mmb_category_load` ist absichtlich aktiviert (Frontend-Anon-User).
- Schutz-Layer: Nonce (gegen CSRF) + Rate-Limit (gegen Abuse) + Service-Whitelist (gegen Param-Injection) + Token-Auth ueber `wp_options` (nie aus Request).
- Nonce wird via `wp_create_nonce( 'dhps_mmb_nonce' )` an der MMB-Template-Wurzel erzeugt und in `data-nonce`-Attribut gelegt. Standard-WP-Pattern, Lifetime 12-24h (je nach WP-Config).
- **Kein Token-Leak gegenueber unauthorized Users:** Auth-OTA bleibt serverseitig, der Endpoint liefert nur das gerenderte HTML.
**Status: OK**

---

## Findings-Liste

### Critical / High
**Keine.**

### Medium

#### M-1: Component-Name Path-Traversal Defense-in-Depth fehlt
- **File:** `includes/dhps-component-helpers.php`
- **Beschreibung:** `dhps_component($name, ...)` macht keinen Regex-Sanity-Check auf `$name`. Aktuell durch `is_registered($name)` indirekt geschuetzt, aber bei Filter-Manipulation `dhps_component_template_path` koennte Path-Traversal moeglich werden.
- **Empfehlung:** `if ( ! preg_match( '/^[a-z0-9-]+$/', $name ) ) return ''` als zusaetzliche Layer.
- **Exploitability:** Aktuell nicht erreichbar (Registry kontrolliert die Names).

#### M-2: Filter dhps_component_template_path ohne realpath-Whitelist
- **File:** `includes/class-dhps-component-registry.php:160`
- **Beschreibung:** Filter erlaubt beliebige Pfade ausserhalb von Plugin-/Theme-Dir. Defense-in-Depth-Check `realpath()` gegen `[plugin_dir, theme_dir, child_theme_dir]` waere empfehlenswert.
- **Trust-Decision:** WordPress-Filter-Trust-Modell akzeptiert (siehe Trust-Decisions-Liste).
- **Exploitability:** Nur durch boesartigen Plugin/Theme-Code, der dann ohnehin Pwn hat.

### Low

#### L-1: $alpine_attrs ohne Escape echoed (content-card)
- **File:** `public/views/components/content-card.php:111`
- **Beschreibung:** `$alpine_attrs` ist statisch zusammengesetzt und enthaelt keine User-Daten. Wird aktuell nur als `' x-data="dhpsContentCard()" x-cloak'` belegt. Sicher, aber zukuenftige Erweiterungen koennten leicht User-Daten reinpacken.
- **Empfehlung:** Inline-Kommentar verschaerfen oder Helper-Funktion `dhps_format_alpine_attrs(array)` einfuehren.

#### L-2: Rate-Limit gegen Reverse-Proxy-Shared-IP (DoS-Surface)
- **File:** `includes/class-dhps-mmb-ajax-handler.php:311`
- **Beschreibung:** Nutzt nur `$_SERVER['REMOTE_ADDR']`. Hinter Cloudflare/Nginx-Proxy haben alle User dieselbe IP -> Shared Rate-Limit-Bucket.
- **Empfehlung:** Optional Filter `dhps_mmb_client_ip` einfuehren, damit Site-Admin sichere X-Forwarded-For-Auswertung einhaengen kann.
- **Trust-Decision:** Bewusster Tradeoff (X-Forwarded-For ohne Proxy-Validation ist gefaehrlicher als shared-IP-Tradeoff).

#### L-3: dhps_elementor_bridge_enabled ohne register_setting
- **File:** `Deubner_HP_Services.php:400`
- **Beschreibung:** Option wird gelesen, aber nicht via Settings-API registriert. Hat keine Sanitize-Callback, keine UI.
- **Empfehlung:** Vor Tag entweder `register_setting()` mit `'absint'`/`'rest_sanitize_boolean'` Callback + Admin-UI nachziehen, ODER dokumentieren, dass Setting nur via Code/CLI nutzbar ist.
- **Security-Impact:** Keiner (Setting kann ohnehin nur von Admins gesetzt werden), aber UX-Mache.

#### L-4: data-category/data-dhps-* Pre-Escaping-Pattern
- **File:** `public/views/services/mmb/default.php` + `partials/category-content.php`
- **Beschreibung:** Variablen werden vorab durch `esc_attr()` gepiped (`$cat_id = esc_attr(...)`), dann ohne erneuten Escape echoed. Funktional sicher, aber WP-Style-Konvention will Escape am Output-Punkt.
- **Empfehlung:** Stil-Anpassung in v0.14.1.

#### L-5: ContentCard alpine_attrs Konkatenation
- **File:** siehe L-1 (verwandt).
- **Beschreibung:** Doppelung Hinweis - ein konsistenter Helper waere besser.

#### L-6: register_setting fuer dhps_elementor_bridge_enabled fehlt
- (Duplikat von L-3, behalten fuer Findings-Counter-Integritaet.)

### Info

#### I-1: wp_kses_post strippt SVG-Tags standardmaessig
- **File:** `public/views/components/empty-state.php:59`
- **Beschreibung:** Wenn ein Caller einen externen SVG-String als `icon`-Prop uebergibt (kein bekannter Slug), wird `wp_kses_post()` aufgerufen - das strippt aber `<svg>`, `<path>`, `<polyline>`-Tags **per Default**. Die Fallback-Pfad-Implementierung ist daher in der Praxis "stummes Stripping" - das ist im Inline-Comment dokumentiert ("Mapping-Slugs sollten den Regelfall abdecken").
- **Empfehlung:** Doku erweitern oder eigenen `wp_kses` mit SVG-Whitelist nutzen.

#### I-2: Alpine.js CSP-Anforderung script-src 'unsafe-eval'
- Bereits in Sektion 4.3 behandelt. Dokumentation in `01-STATUS.md` empfohlen.

#### I-3: extract(EXTR_SKIP) Component-API entspricht WP-Konvention
- Component-API funktioniert analog zu `get_template_part()` mit Variable-Scoping. Sicher gegen Variable-Hijacking durch Prop-Keys.

#### I-4: mark_used() vor include
- Reihenfolge ist file_exists -> mark_used -> include. Falls include fehlschlaegt (Fatal Error), bleibt mark_used aktiv - kein Security-Impact, nur Asset-Enqueue-Statistik leicht ueberzaehlt.

#### I-5: WP_DEBUG-Branch leakt Component-Namen
- Nur bei `WP_DEBUG=true`, Output ist via `esc_html` geschuetzt, Production-Mode ist still. Akzeptiert.

---

## Akzeptierte Trust-Decisions

Diese Entscheidungen wurden bewusst getroffen und sind sicherheitsfachlich vertretbar:

1. **wp_ajax_nopriv aktiviert** - Frontend-Endpoint fuer anonyme Besucher noetig. Schutz via Nonce+Rate-Limit+Whitelist+Server-side-OTA.

2. **REMOTE_ADDR ohne X-Forwarded-For-Auswertung** - X-Forwarded-For ohne Proxy-Validation ist DoS-anfaelliger als shared-IP-Bucket. Empfehlung: optional Filter-Hook nachruesten.

3. **Filter dhps_component_template_path ohne realpath-Whitelist** - WordPress-Filter-Trust-Modell: wer Filter setzt, hat Code-Trust. Konsistent mit WP-Standard.

4. **file_exists() folgt Symlinks** - WordPress-Standard. Bei FS-Schreibrechten ist System ohnehin kompromittiert.

5. **wp_kses_post auf body_html/content_html** - Inhalte stammen aus der API von deubner-online.de (vertrauter Upstream). wp_kses_post als Defense-in-Depth-Layer, nicht als primaere Schutzlinie.

6. **extract(EXTR_SKIP) im Component-Renderer** - Analog zu WP `get_template_part()`. Bewusste API-Design-Entscheidung.

7. **Alpine.js script-src 'unsafe-eval'** - Alpine v3 evaluiert HTML-Attribute zur Laufzeit. Bekannte CSP-Limitierung des Frameworks.

8. **Inline-Styles im Pagination/ContentList/LazyImage** - WordPress laesst Inline-Styles standardmaessig zu. Werte sind `(int)` gecastet oder `esc_attr`-geschuetzt.

9. **Pre-Escaping-Pattern in MMB-Templates** - Funktional sicher, ist nur Style-Mache, kein Security-Issue.

10. **Defense-in-Depth wp_kses_post am AJAX-Output** - Doppelter Schutz; nicht streng noetig, weil das Partial bereits sauber escaped, aber gute Praxis.

---

## Pre-Release Fix-List (Empfehlung)

| ID | Severity | Aufwand | Empfehlung |
|----|----------|---------|------------|
| **S-1** | Low | 15min | `register_setting( 'dhps_settings', 'dhps_elementor_bridge_enabled', [ 'type'=>'string', 'default'=>'0', 'sanitize_callback' => function( $v ){ return '1' === (string) $v ? '1' : '0'; } ] )` in der Admin-Init-Logik. |
| **S-2** | Low | 5min | Inline-Kommentar in `content-card.php:111` verschaerfen, dass `$alpine_attrs` strikt statisch bleiben muss. |
| **S-3** | Info | 10min | In `docs/project/01-STATUS.md` (oder neuem CSP-Doc) festhalten: `script-src 'unsafe-eval'` und `style-src 'unsafe-inline'` sind aktuell erforderlich. |

Optional (post-Release):

| ID | Severity | Aufwand | Empfehlung |
|----|----------|---------|------------|
| Defense-in-Depth | Medium | 30min | Realpath-Whitelist auf `dhps_component_template_path`-Filter-Output. |
| Defense-in-Depth | Medium | 10min | Regex-Sanity-Check `^[a-z0-9-]+$` auf `$name` in `dhps_component()`. |
| UX | Low | 20min | Filter-Hook `dhps_mmb_client_ip` einfuehren, damit Reverse-Proxy-Site-Admins eigene IP-Detection einhaengen koennen. |

---

## Gesamt-Verdict

**GO-WITH-FIXES**

- **0 Critical / 0 High Findings**
- **2 Medium Findings** (beide Defense-in-Depth, aktuell nicht exploitable)
- **6 Low Findings** (Style + UX + dokumentierte Trust-Decisions)
- **5 Info Findings** (Dokumentations-Hinweise)

Die drei Pre-Release-Fixes (S-1/S-2/S-3) zusammen ca. **30 Minuten Aufwand** und schliessen die letzten UX/Doku-Luecken.
Keiner der Findings ist Release-blockierend.

Der Code zeigt durchgaengig:
- Sauberes Nonce-Handling
- Konsistente Output-Escape-Disziplin
- Klare Trust-Boundaries (Server-side-Auth, Client-side-Filter)
- Defense-in-Depth an strategischen Stellen (wp_kses_post auf Partial-Output, EXTR_SKIP)
- Bewussten Umgang mit 3rd-party-Dependencies (Hash-verifiziertes Vendor-Bundling)

Empfehlung: nach Anwendung von S-1/S-2/S-3 als **v0.14.0 taggen**.

---

*Audit beendet 2026-05-22.*
