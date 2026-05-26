# Live-Preview-Plan v0.15.3 (Discovery)

## Stand: 2026-05-25 (urspruenglich), Sektionen 9.3+9.4 nachgepflegt in v0.15.4 (2026-05-26)
## Status: Architektur-Vorschlag - umgesetzt in v0.15.3, Schema-Drift in v0.15.4 dokumentiert
## Zielversion: v0.15.3 - Live-Preview im Admin-Dashboard
## Plattform: WP 6.9.4, Elementor 4.0.1, PHP 8.3.30, React 18 (wp.element)

## v0.15.4 Sync-Notizen (QA-Findings nachgepflegt):
## - **Sektion 9.3 atts_rejected**: Discovery sagte `array<string>`, Implementation liefert `Object{key:reason}` (semantisch reicher, da Ablehnungs-Grund mitgeliefert). F2 toleriert beides via `Array.isArray()`-Check + `Object.keys()`-Fallback. Akzeptiert als bewusster Schema-Drift (Trust-Decision T4 v0.15.3).
## - **Sektion 9.4 HTTP-Status**: Discovery erwaehnte `invalid_endpoint` als 500, Implementation liefert 404 (semantisch korrekter weil "Endpoint nicht gefunden" = 404). Akzeptiert als Schema-Sync.
## - **Sektion 5.5 iframe Re-Mount-Pattern**: F2 hat `key={service + '-' + html.length}`-Pattern als Standard etabliert (war nicht in Discovery dokumentiert). Erzwingt React-Remount des iframe bei neuem srcdoc + verhindert State-Bleed zwischen Renders. Empfehlung: in kuenftigen Discovery-Plaenen mit srcdoc-iframes verpflichtend dokumentieren.

---

## 1. Status-Quo (was v0.15.0 schon hat)

### 1.1 Bestehende Dashboard-Architektur

| Baustein | Datei | LOC | Funktion |
|----------|-------|-----|----------|
| REST-Backend | `includes/class-dhps-admin-rest.php` | ~553 | 5 Routes unter `dhps/v1` (Health, Test, Cache-Stats, Cache-Flush) |
| Health-Collector | `includes/class-dhps-health-collector.php` | ~280 | OTA, Branding, Endpoint, API-Reachable |
| Cache-Stats | `includes/class-dhps-cache-stats.php` | ~150 | Plugin-global Transient-Inventar |
| React-Bundle | `admin/js/dhps-admin-react.js` | 725 | App + ServiceHealthList + ServiceHealthCard + CacheStatsPanel |
| Mount | `admin/views/dashboard.php` | +1 Zeile | `<div id="dhps-admin-react-root">` |
| Enqueue | `Deubner_HP_Services.php::dhps_enqueue_admin_dashboard()` | ~50 | conditional auf Hook-Suffix `dhps_dashboard` |

### 1.2 Verfuegbare React-Bridges (im Admin geladen)

- `wp.element.createElement` (`h`), `useState`, `useEffect`, `useCallback`, `Fragment`
- `wp.components`: Card, CardBody, CardHeader, Button, Notice, Spinner, Panel, PanelBody, Flex, FlexItem, Text
- `wp.apiFetch` mit Nonce-Middleware (`wp_rest`)
- `wp.i18n.__` in Textdomain `deubner_hp_services`

### 1.3 Bestehende REST-Routes (unangetastet)

```
GET  /dhps/v1/services/health
GET  /dhps/v1/services/{slug}/health
POST /dhps/v1/services/{slug}/test       (Rate-Limit 30/min)
GET  /dhps/v1/cache/stats
POST /dhps/v1/cache/flush                (Rate-Limit 6/min)
```

### 1.4 Bridge zur Service-Registry

`DHPS_Service_Registry::get_service($slug)` liefert pro Shortcode:
- `shortcode_atts` (Defaults pro Shortcode-Parameter)
- `default_params` (API-Defaults)
- `endpoint` (Service-URL)
- `supports_video`
- `admin_options` (Service-Konfigurations-Keys)

13 registrierte Shortcodes ueber `DHPS_Service_Registry::get_shortcode_names()`:
`mio`, `mio_termine`, `lxmio`, `mmb`, `mil`, `tp`, `tpt`, `tc`, `maes`,
`maes_videos`, `maes_merkblaetter`, `maes_aktuelles`, `lp`.

### 1.5 Frontend-CSS / -JS (was im Admin NICHT geladen ist)

Registriert in `dhps_enqueue_frontend_styles()` (Hook `wp_enqueue_scripts`):
- `dhps-design-tokens.css`, `dhps_base.css`, `dhps-frontend.css`, `dhps-components.css`
- Conditional: `dhps-elementor-bridge.css`
- JS: `dhps-mio.js`, `dhps-mmb.js`, `dhps-tp.js` (registriert, on-demand enqueued via Template)
- Alpine.js Vendor + Init (conditional, via `dhps_maybe_enqueue_alpine()`)

**Wichtig:** Diese Assets werden ueber Hook `wp_enqueue_scripts` (Frontend) eingebunden -
im `admin_enqueue_scripts`-Kontext sind sie NICHT verfuegbar.

### 1.6 Discovery v0.15.0 Sektion 7.2 (Begruendung Verschiebung)

> Live-Preview pro Service: Risiko: Frontend-CSS-Loading im Admin -> Konflikt
> mit wp-admin-Styles. Loesung erfordert iframe + isolierter Render-Context.
> Eigene Iteration mit eigenem Sicherheitsmodell (Capability-Check + CSP-Header).

Diese Empfehlung steht weiterhin. Der vorliegende Plan setzt sie um.

---

## 2. Strategie-Vergleich (Option A iframe / B Inline / C Headless)

### 2.1 Option A: iframe mit Plugin-eigener Preview-URL

**Mechanik:**
- Neue REST-Route GET `/dhps/v1/services/{slug}/preview` liefert komplette
  HTML-Seite (`<!DOCTYPE html><html>...<head>` mit Frontend-Stylesheets +
  Alpine-JS, `<body>` mit `do_shortcode("[{slug} ...]")`).
- React-Component setzt `<iframe srcdoc="...">` ODER `<iframe src="rest-url">`.
- iframe-Resize via `postMessage` (Preview-HTML sendet Hoehe) ODER fixed CSS-Hoehe.

**PRO:**
- Vollstaendige Isolation: kein CSS-Konflikt mit `wp-admin` oder
  `wp-components`.
- Frontend-CSS, Service-JS, Alpine.js laden natuerlich (gleiches Verhalten
  wie auf einer normalen Frontend-Seite).
- Akkordeon (MAES-Aktuelles), Video-Lazy-Loading (TP, MAES), Filter (MMB)
  funktionieren wie im Produktiv-Betrieb -> "echte" Live-Preview.
- iframe ist Standard fuer Preview-Pattern (Customizer, Block-Editor).

**CON:**
- iframe-Resize-Logik fuer dynamische Inhalte komplex (Alpine kann Hoehe
  nachtraeglich aendern, z.B. beim Akkordeon-Oeffnen).
- Wenn `src=rest-url` genutzt: zusaetzlicher HTTP-Round-Trip + REST-Permission
  fuer iframe-Loading (kein Cookie-basierter Login fuer X-Frame-Options).
- Wenn `srcdoc=html` genutzt: HTML wird vollstaendig im JSON transportiert
  (groessere Response, ~50-200 KB).
- iframe-Innenleben hat keine Bridge zu React-Outer-State (postMessage noetig).

### 2.2 Option B: Inline (do_shortcode -> div)

**Mechanik:**
- Neue REST-Route POST `/dhps/v1/services/{slug}/preview` liefert nur den
  HTML-Body via `do_shortcode()`.
- React injiziert via `dangerouslySetInnerHTML` ins Admin-DOM.
- Frontend-CSS muss explizit im Admin enqueued werden (analog Dashboard-CSS).

**PRO:**
- Einfacher zu implementieren (kein iframe, kein Resize).
- Keine zusaetzliche Public-URL.

**CON:**
- **Kritisch**: `wp-components` setzt globale Resets (`*, *::before, *::after { box-sizing: border-box }`,
  `button { background: none }`, ...). DHPS-Frontend-CSS erwartet eigene Box-Model-Annahmen.
- `wp-admin` injiziert eigene `.notice`, `.button`, `<h1>`, `<h2>`-Styles
  (gelb-violetter Admin-Header-Bereich).
- DHPS-Frontend-CSS hat Klassen wie `.dhps-news__group-title`, die u.U.
  in der Admin-Seite kollidieren mit `wp-admin` `.dhps-db-*`-Klassen oder
  `.dhps-react-*`-Bereich (eigene React-Komponenten).
- Alpine.js wird im Admin nicht geladen -> Akkordeon (MAES-Aktuelles),
  Filter (MMB), Tab-Switcher tot.
- `has_shortcode`-Detection (z.B. fuer Alpine-Conditional-Enqueue) greift
  im Admin nicht (Admin-Seite hat kein Post-Content).
- Service-JS muesste auch im Admin enqueued werden (TP-Video-Player, MIO-AJAX-Loader).

### 2.3 Option C: Headless (statisches HTML-Snippet)

**Mechanik:**
- REST liefert HTML-Output von `do_shortcode()` ohne JS-Bindings.
- Frontend-CSS wird via `<link>` im Preview-Container referenziert (so
  dass keine globalen Resets greifen).

**PRO:**
- Minimal-invasiv, kein iframe, kein JS-Enqueue.

**CON:**
- KEINE Interaktivitaet (Filter, Akkordeon, Video-Lazy-Load tot).
- TC ist faktisch unmoeglich: TC liefert HTML + Inline-JS als Einheit.
- MAES-Videos Lazy-Loading funktioniert nicht.
- MMB-Suche funktioniert nicht.
- Weniger "Live"-Preview, mehr "Static-Snapshot".

### 2.4 Vergleich

| Kriterium | A iframe | B Inline | C Headless |
|-----------|----------|----------|------------|
| CSS-Isolation | komplett | gar nicht | partiell |
| JS-Funktionalitaet | komplett | nur wenn enqueued | gar nicht |
| TC funktional | ja | nein (Inline-JS) | nein |
| Akkordeon (MAES) | ja | nur wenn Alpine enqueued | nein |
| Video-Lazy (TP) | ja | nur wenn dhps-tp.js enqueued | nein |
| Implementierungs-Aufwand | Mittel-Hoch | Mittel | Niedrig |
| Wartungs-Aufwand | Niedrig (iframe-isoliert) | Hoch (CSS-Konflikte) | Niedrig (statisch) |
| Sicherheits-Aufwand | Mittel (iframe-Permission) | Niedrig (REST-only) | Niedrig (REST-only) |
| Realitaetsnaehe | hoch | mittel | gering |

### 2.5 Empfehlung: Option A (iframe mit srcdoc)

**Begruendung:**
1. **TC kann nur via Option A live preview werden** (das Inline-JS aus dem
   API-Body wuerde im Admin-Inline-Pfad mit Admin-eigenem JS kollidieren -
   z.B. Funktionsname `test_einblenden` koennte mit Admin-Globals kollidieren).
2. **CSS-Konflikt-Risiko bei Option B ist hoch** und nicht abschaetzbar -
   wp-components verwendet aggressive Resets, Frontend-CSS nimmt eigene
   Box-Model-Annahmen an.
3. **Alpine.js Conditional-Loading basiert auf `has_shortcode()`** - im
   Admin-Kontext ist das nicht reproduzierbar ohne explizite Enqueue-Logik;
   die ist im iframe (eigene Seite) trivial.
4. **Standard-Pattern**: WP Customizer, Block-Editor-Preview, Gutenberg
   `ServerSideRender` (welcher iframe-aehnliche Isolation nutzt) - alle
   loesen das per iframe.
5. **srcdoc vs src=URL**: Empfehlung **srcdoc**, weil:
   - Kein zusaetzlicher Auth-Layer (REST liefert HTML im JSON, iframe rendert
     es ohne neuen Request).
   - Keine zusaetzliche Public-URL noetig (kein Capability-Check auf einem
     Frontend-Pfad noetig).
   - Browser-Cache nicht in Konflikt mit Live-Refresh.
   - Trade-off: HTML wird im JSON transportiert (~50-200 KB pro Service).
   - Wenn das zu gross wird: Migration auf `src=URL` als v0.15.4-Ticket
     (Hybrid-Endpoint kann beides).

---

## 3. REST-Endpoint-Design

### 3.1 Neuer Endpoint

```
POST /dhps/v1/services/{service}/preview
```

**Begruendung POST statt GET:**
- Body enthaelt User-Inputs (Atts) - POST sauberer fuer Mutation-freie aber
  parametrisierte Requests.
- POST faellt nicht in HTTP-Caches (Browser, Reverse-Proxy) - gewuenscht
  fuer Live-Preview.
- Konsistent mit `/test`-Endpoint (auch POST).

### 3.2 Request-Schema

```json
{
  "atts": {
    "layout": "default",
    "class": "",
    "section": "videos"
  },
  "format": "iframe"
}
```

| Feld | Typ | Default | Whitelist |
|------|-----|---------|-----------|
| `atts` | object | `{}` | Pro-Service-Whitelist (siehe 3.4) |
| `atts.layout` | string | `default` | `default`, `card`, `compact` |
| `atts.class` | string | `''` | Regex `[a-z0-9_\- ]{0,64}` |
| `atts.section` | string | `''` | Pro-Service-Whitelist (nur MAES: `videos`, `merkblaetter`, `aktuelles`) |
| `format` | enum | `iframe` | `iframe`, `inline`, `headless` (v0.15.3: nur `iframe`) |

**Atts-Whitelist pro Service (v0.15.3):**

Nur 3 Felder sind admin-konfigurierbar; alle anderen `shortcode_atts`
(z.B. `id_merkblatt`, `videoliste`, `teasermodus`) werden in v0.15.3
NICHT exposed (verschoben auf v0.15.4 "Atts-Editor").

Begruendung:
- `layout` ist global (alle Services) und sicher.
- `class` braucht sanitize_html_class()-Filter, ist textuell.
- `section` ist MAES-spezifisch und in v0.10.x bewaehrt.
- Andere Atts (z.B. `videoliste="3,5,7"`) erfordern Service-spezifisches
  Validierungs-Schema - Aufwand erst in v0.15.4 sinnvoll.

### 3.3 Response-Schema

```json
{
  "service": "tp",
  "format": "iframe",
  "html": "<!DOCTYPE html><html lang=\"de\"><head>...</head><body>...</body></html>",
  "size_bytes": 24817,
  "render_time_ms": 142,
  "shortcode": "[tp layout=\"card\"]",
  "atts_applied": { "layout": "card", "class": "" },
  "atts_rejected": [],
  "api_cache_hit": true,
  "rendered_at": 1716620000
}
```

Felder:
- `html` (string, **wird KEIN wp_kses_post angewendet** - siehe Sektion 5.3
  Trust-Modell).
- `size_bytes` = `strlen($html)`.
- `render_time_ms` = `microtime(true)` Delta.
- `shortcode` = Debug-Hilfe, zeigt aufgeloesten Shortcode-String.
- `atts_applied` = nach Sanitization aktiv genutzte Atts.
- `atts_rejected` = Liste der nicht in Whitelist enthaltenen Atts-Keys
  (transparenter Hinweis fuer Admins).
- `api_cache_hit` = ob Cache-Aside-Layer einen Hit hatte.

### 3.4 Rate-Limit

Analog `/test`: 30/min pro User-ID.

Begruendung: Preview triggert `do_shortcode()` -> `DHPS_API_Client::fetch_content()`.
Mit Cache-Hit ist Last gering, aber Worst-Case (Cache-Miss bei 13 Services)
kann jeweils 1-5s API-Latenz erzeugen. 30/min schuetzt vor Versehen
(Klick-Spam) und API-Quota-Abbau.

Rate-Limit-Bucket: `dhps_admin_rate_preview_{user_id}` (eigener Bucket,
nicht geteilt mit `test`).

### 3.5 Security

Identisch zu `/test`-Endpoint:
- `permission_callback = manage_options`.
- Service-Slug via `validate_service_param()` (Whitelist + sanitize_key + Length-Limit).
- `atts.layout` in Whitelist `[default, card, compact]`.
- `atts.class` via `sanitize_html_class()` + Regex `[a-z0-9_\- ]{0,64}`.
- `atts.section` via Whitelist pro Service.
- Andere Atts-Keys werden **ignoriert** (kein Reject mit Fehler -
  defensive Forward-Compatibility).
- Cache-TTL = 3600s (Standard) - keine Stale-Cache-Bypass-Option fuer Admins
  (verhindert Live-Hammering).

### 3.6 Caching

- API-Cache-Layer (Transients via `DHPS_API_Client`) bleibt aktiv.
- Output-Caching (gerenderter HTML im Transient) wird **NICHT** implementiert
  in v0.15.3 - Preview soll Aenderungen sofort zeigen.
- HTTP-Response-Header: `Cache-Control: no-store` (REST-typisch, WP-Default).

---

## 4. React-Komponente-Design

### 4.1 Neue Komponente: LivePreviewPanel

**Position:** Eigene PanelBody-Section in `App` (unter `CacheStatsPanel`).
KEIN Tab-System in v0.15.3 (`App` hat `view`-State der noch nicht genutzt
wird, fuer v0.16.x reserviert).

### 4.2 Komponenten-Hierarchie

```
App
+-- Panel "Service-Health-Monitor"
+-- CacheStatsPanel
+-- LivePreviewPanel                     (NEU)
    +-- LivePreviewControls              (Service-Dropdown + Atts-Form + Run-Button)
    +-- LivePreviewIframe                (iframe srcdoc)
    +-- LivePreviewMeta                  (size/duration/cache-hit)
```

### 4.3 Props / State (LivePreviewPanel)

State (alle `useState`):
- `serviceSlug` (string, Default: `'mio'`)
- `atts` (object, `{ layout: 'default', class: '', section: '' }`)
- `loading` (boolean)
- `error` (string|null)
- `result` (object|null - REST-Response)
- `lastRun` (number - Unix-Timestamp)
- `autoReload` (boolean, Default `false` - in v0.15.3 KEIN auto-refresh)

Aktionen:
- `runPreview()` -> POST `/preview`, setzt `result`/`error`/`loading`.
- `resetAtts()` -> setzt `atts` auf Default.
- `clearPreview()` -> `result = null`.

### 4.4 UI-Layout

```
+---------------------------------------------------------------+
| Live-Preview                                                  |
+---------------------------------------------------------------+
| Service:  [Dropdown: mio v]   Layout: [default v]             |
| CSS-Class: [_____________]    Section: [v MAES-only]          |
| [Vorschau laden]  [Atts zuruecksetzen]                        |
+---------------------------------------------------------------+
| Status: gerendert vor 3s | 24 KB | 142 ms | Cache: HIT       |
+---------------------------------------------------------------+
| Shortcode: [tp layout="card"]                                 |
+---------------------------------------------------------------+
|                                                               |
|   <iframe srcdoc="..." style="width:100%; height:600px">      |
|                                                               |
|                                                               |
+---------------------------------------------------------------+
```

### 4.5 Komponenten-Details

#### LivePreviewControls

- `SelectControl` (wp.components) fuer Service-Slug
  - Optionen: Liste aus 9 Haupt-Services (siehe Sektion 6.1)
- `SelectControl` fuer Layout (default/card/compact)
- `TextControl` fuer Custom-CSS-Class (sanitize on submit, nicht onChange)
- `SelectControl` fuer Section (nur sichtbar wenn `serviceSlug === 'maes'`)
- `Button` "Vorschau laden" (variant=primary, isBusy=loading)
- `Button` "Atts zuruecksetzen" (variant=tertiary)

#### LivePreviewIframe

- `<iframe srcdoc={result.html}>` mit:
  - `style={ width: '100%', height: '600px', border: '1px solid #ccc' }`
  - `sandbox="allow-same-origin allow-scripts"` (siehe Sektion 5.4)
  - `title="Live-Preview {service}"` (a11y)
- Resize in v0.15.3: **fixed 600px** (kein dynamic resize via postMessage -
  verschoben auf v0.15.4).
- Empty-State: wenn `result === null`, zeige Notice "Klicken Sie auf
  'Vorschau laden'".

#### LivePreviewMeta

- 4-Item-Flex-Row: Size, Duration, Cache-Status, Rendered-At.
- Bei `result.atts_rejected.length > 0`: zusaetzliches `Notice` "Folgende
  Atts wurden ignoriert: [Liste]".

### 4.6 Wp.components-Bausteine (Reuse)

| Komponente | wp.components |
|-----------|---------------|
| Service-Dropdown | `SelectControl` |
| Layout-Dropdown | `SelectControl` |
| CSS-Class-Input | `TextControl` |
| Section-Dropdown (MAES) | `SelectControl` |
| Run-Button | `Button variant=primary` |
| Reset-Button | `Button variant=tertiary` |
| Container | `Panel`, `PanelBody` |
| Notice | `Notice` |
| Spinner | `Spinner` |
| Meta-Row | `Flex`, `FlexItem` |

### 4.7 i18n

Alle Strings via `wp.i18n.__('...', 'deubner_hp_services')` (identisch zum
Bestand). Hardcoded-Deutsch ist akzeptiert, weil POT-File noch leer ist
(Inherited Trade-off aus v0.15.0).

### 4.8 Defensives Reading

Analog zum bestehenden Pattern (`stats.total_entries || stats.entries || 0`):
- `result.size_bytes || result.bytes || 0`
- `result.render_time_ms || result.duration_ms || 0`
- `result.api_cache_hit || result.cache_hit || false`

Begruendung: Lehre aus v0.15.0 - Schema-Drift zwischen Backend/Frontend
trat trotz Plan auf. Trotz Schema-Vertrag (Sektion 9) defensives Reading
als Belt-and-Suspenders.

---

## 5. Security-Anforderungen

### 5.1 Permission-Modell

- Endpoint `permission_callback`: `current_user_can('manage_options')` (analog v0.15.0).
- iframe braucht keine separate Capability, weil HTML via `srcdoc` aus
  JSON-Response stammt (kein eigener URL-Loader).
- Wenn spaeter `src=URL`-Variante kommt (v0.15.4): zusaetzlich Capability-Check
  auf der iframe-URL erforderlich.

### 5.2 Input-Sanitization (vom Admin)

| Feld | Sanitization |
|------|--------------|
| `service` (Route) | `sanitize_key` + Whitelist (`ALLOWED_SERVICES`) + Length-Limit 16 |
| `atts.layout` | Whitelist `[default, card, compact]` - alles andere -> `default` |
| `atts.class` | `sanitize_html_class()` + Regex `[a-z0-9_\- ]{0,64}` |
| `atts.section` | Whitelist pro Service (MAES: `[videos, merkblaetter, aktuelles]`) |
| Unbekannte Atts-Keys | Werden silent ignoriert, in `atts_rejected` aufgelistet |

### 5.3 Output-Trust-Modell

**HTML aus do_shortcode() ist TRUSTED**, weil:
1. DHPS-Parser produzieren das HTML aus API-Responses von
   `deubner-online.de` (verified Origin).
2. DHPS-Templates escapen User-relevante Felder (Titles, Bodies via
   `esc_html`, `esc_attr`, `wp_kses_post`).
3. Inline-JS in TC ist Vendor-Code (Deubner-Verlag), nicht User-Input.
4. iframe-Sandbox bietet eine zweite Isolations-Schicht.

**KEINE `wp_kses_post()`-Filterung** auf `result.html`, weil:
- Service-Templates (TP, MAES) liefern bewusst `<script>`-Tags fuer
  Video-Player und Akkordeon-Bindings.
- `wp_kses_post` wuerde diese strippen -> Preview waere broken.
- Akzeptierter Trade-off (analog Frontend-Render).

### 5.4 iframe-Sandbox

`sandbox="allow-same-origin allow-scripts"`:
- `allow-same-origin`: Erforderlich damit DHPS-Service-JS Cookie-Headers
  fuer XHR setzen kann (z.B. MIO-AJAX-News).
- `allow-scripts`: Erforderlich fuer Alpine.js + Service-JS.
- **NICHT** `allow-popups`, `allow-top-navigation`, `allow-forms` (kein
  Bedarf, reduziert Angriffsflaeche).

Akzeptiertes Risiko: Mit `allow-same-origin + allow-scripts` ist Sandbox
schwach (gleichwertig zu kein-Sandbox bei Same-Origin). Aber:
- Quelle der HTML ist Plugin-eigen (kein User-HTML).
- iframe-Inhalt hat Admin-Cookie-Kontext nicht in JSON-Response.
- Admin-only Endpoint -> Angreifer braucht bereits `manage_options`.

### 5.5 Rate-Limit

- 30/min pro User-ID, eigener Bucket `preview` (siehe 3.4).
- Sliding-Window-Drift + Race-Condition akzeptiert (dokumentiert in v0.15.0).

### 5.6 SSRF-Schutz

- Endpoint ist aus Registry (`$service['endpoint']`), nicht User-Input.
- Identisches Pattern wie `/test`-Endpoint.

### 5.7 OTA-Leak

- OTA wird via `get_option()` in der `do_shortcode()`-Pipeline geladen.
- OTA wird NICHT in der Response exposed (Pipeline schreibt OTA nur als
  POST-Param zu deubner-online.de, nie in den HTML-Body).
- iframe-HTML enthaelt jedoch potenziell API-URLs mit OTA (z.B. MIO-AJAX-URL).
  **Risiko mittel**: Wenn Admin Browser-DevTools oeffnet, sieht er die OTA.
  Akzeptabel, weil Admin ohnehin in `dhps_ota_*`-Option den Wert sehen
  koennte.

### 5.8 CSP-Header

Optional fuer v0.15.4: `Content-Security-Policy` Header in der iframe-HTML
(z.B. `script-src 'self' deubner-online.de`). In v0.15.3 nicht implementiert,
weil:
- Admin-Only Endpoint (Angriffsflaeche klein).
- DHPS-Templates haben kein striktes CSP-Modell (Inline-Styles, Inline-Scripts).

---

## 6. Scope-Empfehlung v0.15.3 vs Verschoben

### 6.1 Pflicht (v0.15.3)

| # | Feature | Aufwand | Begruendung |
|---|---------|---------|-------------|
| 1 | REST-Endpoint `POST /preview` | 0.5 Tage | Standard-Erweiterung von `DHPS_Admin_REST` |
| 2 | Preview-Renderer-Helper (HTML-Wrapper) | 0.5 Tage | `<!DOCTYPE> + frontend-css + do_shortcode + alpine` |
| 3 | LivePreviewPanel React-Component | 1 Tag | 3 Sub-Components + State + iframe |
| 4 | Service-Dropdown (9 Haupt-Services) | inkl. | mio, lxmio, mmb, mil, tp, tpt, tc, maes, lp |
| 5 | Layout-Dropdown (default/card/compact) | inkl. | nicht alle Layouts existieren fuer alle Services - graceful |
| 6 | CSS-Class-Input | inkl. | sanitize_html_class |
| 7 | MAES-Section-Dropdown (conditional) | inkl. | nur bei `serviceSlug === 'maes'` |
| 8 | Rate-Limit-Bucket `preview` | 0.25 Tage | wiederverwendet `check_rate_limit()` |
| 9 | Doc + CHANGELOG | 0.5 Tage | docs/project/28-CHANGELOG-v0153.md |

**Gesamt: 2.75 - 3.5 Arbeitstage** - knapp 1 Woche bei einem Spec, 3 Tage bei 2 Specs.

### 6.2 9 Haupt-Services (nicht alle 13 Shortcodes)

| Shortcode | v0.15.3 | Begruendung |
|-----------|---------|-------------|
| `mio` | ja | Haupt-Service |
| `mio_termine` | nein | Sub-Shortcode (Termine-Liste). Preview wenig wertvoll - in v0.15.4 |
| `lxmio` | ja | Haupt-Service |
| `mmb` | ja | Haupt-Service |
| `mil` | ja | Haupt-Service |
| `tp` | ja | Haupt-Service |
| `tpt` | ja | Haupt-Service |
| `tc` | ja | Haupt-Service (Wrapper, aber funktional) |
| `maes` | ja | Haupt-Service |
| `maes_videos` | nein | Sub-Shortcode - via `section`-Att in `[maes]` simuliert |
| `maes_merkblaetter` | nein | Sub-Shortcode - via `section`-Att in `[maes]` simuliert |
| `maes_aktuelles` | nein | Sub-Shortcode - via `section`-Att in `[maes]` simuliert |
| `lp` | ja | Haupt-Service |

### 6.3 Verschoben (v0.15.4 oder spaeter)

| Feature | Begruendung |
|---------|-------------|
| Atts-Editor (alle `shortcode_atts`) | Service-spezifische Validierungs-Schemata - eigene Iteration |
| Dynamic-Iframe-Resize (postMessage) | Optional, fixed 600px reicht initial |
| Side-by-side-Compare (Frontend vs Demo) | Eigene Iteration, braucht Demo-Toggle-Bridge |
| 4 Sub-Shortcodes (`mio_termine`, `maes_*`) | Per-Service-Atts-Form noetig - eigene Iteration |
| Output-Cache fuer Preview-HTML | Premature Optimization in v0.15.3 |
| Auto-Refresh (Polling) | Nice-to-have, kein Pflicht |
| Preview-URL (`src=URL` statt `srcdoc`) | Migration wenn srcdoc-Groesse zum Problem wird |
| CSP-Header in Preview-HTML | DHPS-Templates haben kein striktes CSP-Modell |
| Tab-System in App (Health / Cache / Preview) | UX-Optimization fuer v0.16.x |

### 6.4 Out-of-Scope fuer v0.15.x komplett

- WP-CLI Live-Preview (`wp dhps preview mio`).
- E2E-Tests fuer Preview (Browser-Automation - Iteration 7+).
- Preview-Sharing (Public-URL mit Token).

---

## 7. BC-Strategie

### 7.1 Was bleibt unangetastet

| Element | Begruendung |
|---------|-------------|
| Bestehende 5 REST-Routes (`/services/health`, `/services/{slug}/health`, `/services/{slug}/test`, `/cache/stats`, `/cache/flush`) | Neue Route additiv |
| `App`, `ServiceHealthList`, `ServiceHealthCard`, `CacheStatsPanel` | Neue Komponente additiv |
| `DHPS_Health_Collector`, `DHPS_Cache_Stats` | Keine Aenderung |
| `DHPS_Shortcodes::handle_shortcode()` | Wird unveraendert via `do_shortcode()` aufgerufen |
| `DHPS_Content_Pipeline::render_service()` | Wird unveraendert genutzt |
| Frontend-Enqueue (`dhps_enqueue_frontend_styles`) | KEINE neuen Hooks im Frontend |
| Demo-Toggle-AJAX | Unangetastet |
| 8 Admin-Pages | Unangetastet |

### 7.2 Was ist neu (additiv)

| Element | BC-Risiko |
|---------|-----------|
| Route `POST /dhps/v1/services/{slug}/preview` | KEIN (neue Route) |
| Methode `DHPS_Admin_REST::handle_service_preview()` | KEIN (neue Methode) |
| Methode `DHPS_Admin_REST::build_preview_html()` (oder eigene Helper-Klasse) | KEIN (neuer Code) |
| React-Component `LivePreviewPanel` in `dhps-admin-react.js` | KEIN (additiv im App-Root) |
| Rate-Limit-Bucket `preview` | KEIN (eigener Transient-Key) |

### 7.3 Optionale Helper-Klasse

Empfehlung: **eigene Klasse** `DHPS_Preview_Renderer`:
- `build_html_document(string $service, array $atts): string` -> liefert
  komplette `<!DOCTYPE>...`-Seite.
- Liest registrierte Frontend-Style-Handles und expandiert sie zu
  `<link rel="stylesheet">`-Tags (statt sie zu enqueuen).
- Loopt Alpine-Init + Service-JS (mio/mmb/tp) via `<script src="...">`-Tags
  in den `<head>`.

Begruendung:
- Trennung von Concerns: REST-Endpoint hat nur HTTP-Routing-Logik, Helper
  hat HTML-Rendering.
- Testbarkeit (in v0.15.4+ koennte ein QA-Spec den Helper isoliert testen).
- Wiederverwendbarkeit fuer v0.15.4 (Atts-Editor, andere Preview-Kontexte).

Datei: `includes/class-dhps-preview-renderer.php` (~150-200 LOC).

### 7.4 Versteckte BC-Risiken

| Risiko | Mitigation |
|--------|------------|
| `do_shortcode()` triggert Frontend-Hooks (z.B. `wp_enqueue_scripts`) im Admin-Kontext | iframe-Helper isoliert via Output-Buffer, `wp_enqueue_scripts` wird im REST-Request-Kontext nicht ausgefuehrt - Helper muss CSS/JS-Tags manuell schreiben |
| `add_filter('dhps_maes_section')` in `DHPS_Shortcodes` setzt globalen Filter | Filter wird nach `do_shortcode()` via `remove_all_filters('dhps_maes_section')` entfernt (besteht bereits) - in Preview-Kontext sicher |
| `$_GET['video']` wird in Shortcode geprueft | Im REST-Kontext ist `$_GET['video']` nicht gesetzt - Default `0` greift |
| `DHPS_Content_Pipeline` schreibt evtl. in WP-Cache | Cache-TTL ist Admin-konfiguriert (`shortcode_atts['cache']`), Preview nutzt Default `3600` - schreibt regulaer in Cache (Beabsichtigt, Preview nutzt selben Cache wie Frontend) |
| Iframe-rendering von HTML mit `<script>` triggert Browser-Side-Effects | Akzeptiert - das ist gewuenschtes "Live"-Verhalten |
| Preview-HTML enthaelt Inline-JS aus TC, das `document.querySelector` aufruft | Sandbox isoliert (verschiedene `window`) - greift nicht auf Admin-DOM zu |

---

## 8. Specialist-Aufteilung

### 8.1 Empfehlung: **2 parallele Specs + 1 Lead**

| # | Spec | Scope | Files |
|---|------|-------|-------|
| **F1** | Backend-Preview-Spec | `DHPS_Admin_REST::handle_service_preview()` + `DHPS_Preview_Renderer`-Klasse + Atts-Sanitization + Rate-Limit-Bucket + Unit-Smoke | 1 neue Klasse + 1 neue Methode in REST-Klasse |
| **F2** | Frontend-Preview-Spec | `LivePreviewPanel`, `LivePreviewControls`, `LivePreviewIframe`, `LivePreviewMeta` Components + Wiring in `App` | `admin/js/dhps-admin-react.js` (Extension) |
| **L1** | Composition-Lead (sequentiell, nach F1+F2) | Wire-Up F1+F2, Smoke-Test im Browser, Schema-Verify | iterative Smokes |
| **Q1** | QA + Security-Spec (parallel, nach L1) | A11y-Check iframe, XSS-Test iframe, Rate-Limit-Test, BC-Regression auf 5 alten REST-Routes | Reports |

### 8.2 Warum nicht 1 grosser Spec?

- Backend und Frontend sind **orthogonal genug** (klare REST-Vertrag-Schnittstelle).
- Schema-Drift-Lehre v0.15.0: trotz 3 Fixes ist parallel-Pattern bewaehrt.
- Token-Budget pro Spec bleibt handhabbar (1 grosser Spec waere ~2x so lang).

### 8.3 Warum nicht 3 Specs (analog v0.15.0)?

- v0.15.0 hatte separates F3 fuer PHP-Foundation (DI, Enqueue, Mount-Point).
- In v0.15.3 ist die Foundation bereits da - es gibt nichts zu enqueuen
  (REST-Endpoint registriert sich selbst, React-Bundle ist schon im
  Dashboard).
- DI fuer `DHPS_Preview_Renderer` kann in F1-Backend mit-erledigt werden.

### 8.4 Schema-Vertrag-Pflicht (siehe Sektion 9)

Wegen v0.15.0-Lehre: BEIDE Specs erhalten denselben Schema-Vertrag-Anhang
als Briefing. Eindeutige Feldnamen, keine Synonyme.

### 8.5 Aufwand-Schaetzung

| Phase | Aufwand | Parallelisierbar |
|-------|---------|------------------|
| F1 Backend-Spec | 1.5 Tage | mit F2 |
| F2 Frontend-Spec | 1.5 Tage | mit F1 |
| L1 Composition | 0.5 Tage | nein |
| Q1 QA + Security | 0.5 Tage | parallel zu sich selbst (2 Sub-Specs) |
| Doku + Release | 0.5 Tage | nach Q1 |

**Gesamt-Wall-Clock: 3-4 Tage** bei paralleler F1/F2-Bearbeitung.

---

## 9. Schema-Vertrag (Lehre v0.15.0)

### 9.1 Pflicht-Bestandteil JEDES Specs

Beide Specialist-Briefings (F1 + F2) erhalten dieses Schema als
**eindeutige, autoritative** Sektion. KEINE Synonyme, KEINE
Optionalitaeten ohne Default.

### 9.2 REST-Request-Schema (POST /dhps/v1/services/{service}/preview)

```json
{
  "atts": {
    "layout": "default",
    "class": "",
    "section": ""
  },
  "format": "iframe"
}
```

**Verbindlich:**
- `atts` ist immer ein object, niemals null.
- `atts.layout` ist immer string, Default `"default"`.
- `atts.class` ist immer string, Default `""`.
- `atts.section` ist immer string, Default `""` (nur bei MAES nicht-leer).
- `format` ist immer string, Default `"iframe"` (in v0.15.3 nur dieser Wert
  erlaubt).

**Reject-Verhalten:**
- Unbekannte Top-Level-Keys: silent ignoriert.
- Unbekannte `atts`-Keys: silent ignoriert, in `atts_rejected` aufgefuehrt.
- Atts-Werte ausserhalb Whitelist: auf Default geclamped, in `atts_rejected`
  aufgefuehrt.

### 9.3 REST-Response-Schema (Success)

```json
{
  "service": "tp",
  "format": "iframe",
  "html": "<!DOCTYPE html>...",
  "size_bytes": 24817,
  "render_time_ms": 142,
  "shortcode": "[tp layout=\"card\"]",
  "atts_applied": { "layout": "card", "class": "" },
  "atts_rejected": [],
  "api_cache_hit": true,
  "rendered_at": 1716620000
}
```

**Feld-Vertrag (autoritativ - KEINE Synonyme):**

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|--------------|
| `service` | string | ja | Service-Slug aus Request |
| `format` | string | ja | "iframe" in v0.15.3 |
| `html` | string | ja | komplette HTML-Seite (DOCTYPE + html + head + body) |
| `size_bytes` | int | ja | `strlen($html)` |
| `render_time_ms` | int | ja | `microtime`-Delta * 1000, gerundet |
| `shortcode` | string | ja | Debug: aufgeloester Shortcode-String |
| `atts_applied` | object | ja | aktive Atts nach Sanitization |
| `atts_rejected` | array<string> | ja | Liste der ignorierten Atts-Keys, leeres Array wenn keine |
| `api_cache_hit` | boolean | ja | true bei Cache-Hit im API-Client |
| `rendered_at` | int | ja | Unix-Timestamp |

**KEINE Aliases erlaubt** (nicht: `bytes`/`size`, nicht: `duration_ms`,
nicht: `cache_hit`, nicht: `at`).

### 9.4 REST-Response-Schema (Error)

Standard WP_Error-JSON:

```json
{
  "code": "invalid_service",
  "message": "Unbekannter Service.",
  "data": { "status": 400 }
}
```

Codes:
- `invalid_service` (400) - Slug nicht in Whitelist
- `service_not_configured` (400) - OTA fehlt
- `invalid_endpoint` (500) - Service-Endpoint leer in Registry
- `rate_limit_exceeded` (429) - Bucket voll
- `rest_forbidden` (403) - Permission denied (WP-Standard)
- `preview_render_failed` (500) - do_shortcode lieferte leeren String oder Exception

### 9.5 React-State-Schema (Frontend-intern, nicht REST)

```js
{
  serviceSlug: "mio",        // string, Whitelist 9 Services
  atts: {
    layout: "default",       // string, Whitelist
    class: "",               // string
    section: ""              // string (nur MAES)
  },
  loading: false,            // boolean
  error: null,               // string|null
  result: null,              // null oder REST-Response-Object
  lastRun: 0,                // Unix-Timestamp
  autoReload: false          // boolean, v0.15.3 immer false
}
```

### 9.6 Defensive-Reading-Mapping (zusaetzliche Sicherheit)

Auch wenn Vertrag eindeutig ist:

```js
// Im Frontend defensiv lesen, falls Drift auftritt:
const sizeBytes = result.size_bytes ?? result.bytes ?? 0;
const renderMs = result.render_time_ms ?? result.duration_ms ?? 0;
const cacheHit = result.api_cache_hit ?? result.cache_hit ?? false;
```

Begruendung: Belt-and-Suspenders. Vertrag ist autoritativ, aber defensive
Reads schuetzen vor Future-Drift bei v0.15.4-Erweiterungen.

### 9.7 Compliance-Check vor Release

Pre-Release-Smoke:
1. REST-Response in Browser-DevTools inspizieren - alle 10 Felder vorhanden?
2. React-State in React-DevTools inspizieren - alle Felder vorhanden?
3. Field-Names exakt match? (Pruefung gegen 9.3-Tabelle.)
4. Atts-Whitelist greift? (Test mit `atts.layout="boese-injection"` -> rejected.)

---

## 10. Risiken + Mitigation

| # | Risiko | Wahrscheinlichkeit | Auswirkung | Mitigation |
|---|--------|--------------------|------------|------------|
| R1 | iframe srcdoc wird zu gross (>500 KB) bei Services mit vielen Videos | mittel | langsamer JSON-Transfer, langsamer iframe-Mount | Heuristik: nach Render `size_bytes` pruefen, ab 500 KB Notice "Preview gross - moeglicherweise langsam". Echte Loesung in v0.15.4 (URL-basierter iframe). |
| R2 | Frontend-CSS aus `wp-content` muss explizit referenziert werden (kein wp_enqueue_scripts im REST-Kontext) | sicher | wenn vergessen, Preview ungestylt | `DHPS_Preview_Renderer` ist Helper, der explizit `<link rel="stylesheet" href="..."/>` Tags schreibt - statt `wp_enqueue_style`. URLs ueber `DEUBNER_HP_SERVICES_URL`. |
| R3 | Alpine.js wird normalerweise via `dhps_maybe_enqueue_alpine` conditional geladen - das greift im REST nicht | sicher | MAES-Akkordeon, MMB-Filter ohne Funktion | Preview-Renderer baut Alpine + Init unconditional in das HTML-Document ein (analog Frontend-Pfad mit Force-Enqueue). |
| R4 | Service-JS (dhps-tp.js etc.) werden im Frontend conditional via Template geladen - Pfad greift im REST nicht direkt | sicher | TP-Video-Lazy-Load, MIO-AJAX-News ohne Funktion | Preview-Renderer schreibt fuer den ausgewaehlten Service den passenden `<script src="...">` Tag (Whitelist: mio->mio.js, mmb->mmb.js, tp->tp.js+tpt, maes->Service-JS). |
| R5 | TC laedt API-Inline-JS (test_einblenden), das mit Window-Globals kollidieren koennte | mittel | TC-Akkordeon broken bei mehrfacher Vorschau | iframe-Isolation greift (eigene Window-Scope). Akzeptiert. |
| R6 | `wp_enqueue_scripts`-Hook wird in `do_shortcode()` im REST-Kontext gefeuert und versucht zu enqueuen | mittel | WP-Warnings im REST-Output | Im REST-Kontext laeuft `did_action('wp_enqueue_scripts')` als false - WP setzt Styles dann in `wp_print_styles`-Globals. Preview-Renderer NUTZT diese nicht (manuelles `<link>`). Akzeptabel - keine WP-Warnings im Output, weil JSON-Antwort separat. |
| R7 | OTA wird im Preview-HTML als URL-Param sichtbar (z.B. MIO-AJAX-URL) | mittel | Admin sieht OTA im Browser-DevTools | Akzeptiert: Admin kann OTA ohnehin via Options-Page sehen. Doku-Hinweis im CHANGELOG. |
| R8 | Schema-Drift trotz Schema-Vertrag (Lehre v0.15.0) | gering-mittel | Cards rendern "UNKNOWN" | Sektion 9 ist Pflicht-Briefing-Bestandteil. F1 autoritativ, F2 muss matchen. + defensive Reading. |
| R9 | Preview-API verbraucht Lizenz-Quota durch Admin-Tests | mittel | API-Quota schneller weg | Rate-Limit 30/min. Cache-Hit reduziert echte API-Calls. Doku-Hinweis. |
| R10 | `do_shortcode()` triggert globalen `$wp_query`-State im REST-Kontext | gering | unerwartete Query-Side-Effects | REST-Init-Kontext hat `$wp_query` nicht voll initialisiert - DHPS-Shortcodes nutzen `$wp_query` nicht direkt. Akzeptiert. |
| R11 | iframe-Resize nicht implementiert -> Content abgeschnitten oder unnoetig Scroll | sicher (UX-Limit) | suboptimale UX | Fixed 600px Hoehe. Hinweis im CHANGELOG: "Content > 600px wird scrollbar". v0.15.4 fuegt postMessage-Resize hinzu. |
| R12 | React-Component-Tree wird bei jedem Preview-Run neu gemounted (iframe-srcdoc reflow) | gering | leichter Performance-Hit | Akzeptiert. Alternative `key=` + force-remount waere komplexer. |
| R13 | Admin-Component-CSS (`wp-components`) wird durch iframe nicht beeinflusst - aber Admin selbst kann durch iframe-CSS beeinflusst werden, wenn iframe-Sandbox bricht | gering | UI-Glitch | `sandbox`-Attr ist gesetzt. CSS leakt nicht aus iframe. |
| R14 | Preview-Helper-Klasse `DHPS_Preview_Renderer` ist neue Klasse - DI noetig | gering | wenn vergessen, Class-Not-Found | Plugin-Main `dhps_init()` muss um Instantiierung erweitert werden. F1-Spec hat das im Scope. |
| R15 | `do_shortcode()` kann WP-Notices in Output schreiben (z.B. fehlende Auth) | gering | Notice-HTML im iframe-Body | Akzeptiert: ist genau das, was Admin sehen will (debugging-tauglich). |
| R16 | `LivePreviewPanel` wird unter `CacheStatsPanel` platziert -> seitliches Wachstum -> Layout-Verschiebung der bestehenden Cards | gering | UX-Aenderung | Akzeptiert: Cards bleiben oben, Preview ist optional sichtbar (collapsible PanelBody). |

### 10.1 Akzeptierte Trade-offs

- HTML-Vollseite im JSON (kein src=URL).
- Fixed iframe-Hoehe 600px.
- Keine Output-Cache-Schicht.
- Keine Sub-Shortcode-Preview (`mio_termine`, `maes_videos` etc.).
- Keine Atts-Editor (nur layout + class + section).
- OTA-URL-Leak im iframe akzeptiert.

---

## 11. Naechste Schritte

1. Plan-Review durch Architekt.
2. Specialist-Briefings F1 (Backend) + F2 (Frontend) erstellen.
3. Briefings enthalten Sektion 9 (Schema-Vertrag) als verbindlichen Anhang.
4. Parallele Implementierung F1 + F2.
5. Composition-Lead L1.
6. QA + Security Q1.
7. CHANGELOG-v0153 + Version-Bump + Tag.

---

## 12. Quellen

- `docs/architecture/18-ADMIN-DASHBOARD-PLAN-v0150.md` - Sektion 7 "Live-Preview verschoben"
- `docs/project/27-CHANGELOG-v0150.md` - Bestehende Dashboard-Bilanz + Tech-Debt Ticket 2
- `admin/js/dhps-admin-react.js` (725 LOC) - React-Patterns + defensives Reading
- `includes/class-dhps-admin-rest.php` (553 LOC) - REST-Pattern + Rate-Limit
- `includes/class-dhps-shortcodes.php` - `handle_shortcode()` als do_shortcode-Eintritt
- `includes/class-dhps-service-registry.php` - shortcode_atts pro Service
- `Deubner_HP_Services.php` `dhps_enqueue_frontend_styles()` - Frontend-Asset-Pfade
- `docs/architecture/13-alpinejs-integration-v0140.md` - Conditional Alpine-Loading Pattern
