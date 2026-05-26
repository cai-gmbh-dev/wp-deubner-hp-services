# Tech-Debt-Triage v0.15.4 (Discovery)

## Stand: 2026-05-26
## Status: Architektur-Vorschlag (KEINE Code-Aenderungen)
## Zielversion: v0.15.4 - Tech-Debt-Cleanup nach v0.15.3 Live-Preview
## Plattform: WP 6.9.4, Elementor 4.0.1, PHP 8.3.30, React 18 (wp.element)

---

## 0. Mission

v0.15.3 lieferte das Live-Preview-Feature mit GO-Verdict in QA und Security,
hinterliess aber 9 dokumentierte Tech-Debt-Tickets (3 QA-Minor, 4 SEC-Low,
2 Discovery-Restscope). Diese Discovery triagiert sie nach Aufwand, Risiko,
BC-Impact und Platzierung und empfiehlt einen klar geschnittenen v0.15.4-Scope
plus eine Spec-Aufteilung.

Die zentrale Beobachtung: 4 Tickets sind reine Doku-/Mini-Code-Aenderungen
(< 15 min), 2 sind klar abgegrenzte Backend-/Frontend-Features (~ 1 h), 2 sind
substantielle Architektur-Erweiterungen (mehrere Stunden), 1 ist ein
Hybrid-Schnitt aus Backend + Frontend.

---

## 1. Triage-Tabelle (9 Tickets x Aufwand/Risiko/BC/Platzierung)

| # | Ticket | Aufwand | Risiko | BC-Impact | Beste Platzierung |
|---|--------|---------|--------|-----------|--------------------|
| 1 | Discovery 9.3/9.4 mit Implementation synchronisieren (atts_rejected Object, HTTP 404) | S | Niedrig | Keine | v0.15.4 - Lead-Direct |
| 2 | Eigener Error-Code `invalid_format` | S | Niedrig | REST-API additiv | v0.15.4 - Lead-Direct |
| 3 | 500-KB-Soft-Warning im Meta-Panel | S | Niedrig | Keine | v0.15.4 - Lead-Direct |
| 4 | iframe Re-Mount-Key Pattern dokumentieren | S | Niedrig | Keine | v0.15.4 - Lead-Direct |
| 5 | Dynamic iframe-Resize via postMessage | M | Mittel | Keine (additiv) | v0.15.4 - Frontend-Spec |
| 6 | 4 Sub-Shortcodes preview-faehig (mio_termine, maes_videos, maes_merkblaetter, maes_aktuelles) | M | Mittel | REST-API additiv (Whitelist erweitern), Frontend-State | v0.15.4 - Backend+Frontend-Spec |
| 7 | Voller Atts-Editor (service-spezifische Atts) | L | Mittel-Hoch | REST-API additiv (Schema erweitert), Frontend deutlich | v0.15.5 - eigene Iteration |
| 8 | CSP-Hinweis fuer Plugin-Doku | S | Niedrig | Keine (nur Doku) | v0.15.4 - Lead-Direct |
| 9 | Frontend-Service-Slug-Whitelist als Konstante | S | Niedrig | Keine (JS-interne Refaktur) | v0.15.4 - Lead-Direct |

### 1.1 Legende

- **Aufwand**: S = < 15 min, M = ~ 1 h, L = mehrere h
- **Risiko**: Niedrig / Mittel / Hoch (Wahrscheinlichkeit fuer Regressionen)
- **BC-Impact**: Keine / Theme-Override / REST-API / Frontend-State
- **Beste Platzierung**: v0.15.4 / v0.15.5 / verschoben

### 1.2 Kategorie-Verteilung

- **6 Easy-Wins** (Ticket 1, 2, 3, 4, 8, 9): Aufwand S, Risiko niedrig.
- **2 Medium-Features** (Ticket 5, 6): Aufwand M, definierter Scope.
- **1 Heavy-Feature** (Ticket 7): Aufwand L, eigene Iteration.

---

## 2. Easy-Wins-Liste (Lead-Direct)

Diese 6 Tickets sind so klein, dass eine eigene Spezialist-Spec
Overhead-lastig waere. Lead arbeitet sie direkt ab, batch-weise in einer
Sitzung.

### 2.1 Ticket 1 - Schema-Sync Discovery <-> Code

**Was**: Discovery 21-LIVE-PREVIEW-PLAN-v0153.md Sektion 9.3 und 9.4 mit
v0.15.3-Implementierung in Einklang bringen.

**Konkret**:
- Sektion 9.3: `atts_rejected` von `array<string>` auf `object {key: reason}`
  (T4 aus CHANGELOG v0153 erklaert das bereits als bewussten Trust-Decision).
- Sektion 9.4: `invalid_endpoint` von HTTP 500 auf HTTP 404 angleichen.
- Hinweis im Doku-Header: "Schema-Sync abgeschlossen in v0.15.4".

**Empfehlung**: Discovery anpassen, NICHT Implementation - Object ist
semantisch reicher, 404 ist semantisch sauber fuer "Endpoint-Konfig fehlt".

**Aufwand**: 10 min. **Risiko**: keiner (reine Doku).

### 2.2 Ticket 2 - Error-Code `invalid_format`

**Was**: In `class-dhps-admin-rest.php` Zeile 580-586 wird `format != iframe`
aktuell als `invalid_service` (HTTP 400) gemeldet. Eigener Code einfuehren.

**Konkret**:
- Neuen Error-Code `invalid_format` (HTTP 400) im REST-Handler.
- Discovery 9.4 um diesen Code ergaenzen.
- v0.15.3-CHANGELOG-Tech-Debt Ticket 2 als erledigt markieren.

**BC-Impact**: REST-API additiv - Frontend liest `err.message`, nicht den
Code, also kein Frontend-Fallout.

**Aufwand**: 10 min. **Risiko**: niedrig.

### 2.3 Ticket 3 - 500-KB-Soft-Warning im Meta-Panel

**Was**: Discovery R1 verlangt Warning ab Preview-Groesse > 500 KB.

**Konkret** (in `admin/js/dhps-admin-react.js` `LivePreviewMeta`):
- Schwellwert-Konstante `PREVIEW_SIZE_WARN_BYTES = 500 * 1024`.
- Bei `sizeBytes > PREVIEW_SIZE_WARN_BYTES` zusaetzliches `Notice status=warning`:
  "Preview-Groesse ueber 500 KB - moeglicherweise langsam im Browser."
- Position: unter dem Meta-Flex-Row, vor dem `rejectedList`-Notice.

**BC-Impact**: keine - rein additive React-Notice.

**Aufwand**: 10 min. **Risiko**: niedrig.

### 2.4 Ticket 4 - iframe Re-Mount-Key Pattern dokumentieren

**Was**: F2 hat `key='dhps-iframe-' + service + '-' + html.length` eingefuehrt
(QA Sektion 4.3, SEC Sektion 6 + 9). Pattern soll als Standard fuer kuenftige
iframe/srcdoc-Komponenten dokumentiert werden.

**Konkret**:
- Update in `docs/architecture/21-LIVE-PREVIEW-PLAN-v0153.md` Sektion 4.5
  oder neuer Anhang (Pattern-Beschreibung + Begruendung
  Browser-srcdoc-Reflow-Bugs).
- Memory-Eintrag (User-Auto-Memory) - kurz erwaehnen als bewaehrtes Pattern.

**BC-Impact**: keiner.

**Aufwand**: 10 min. **Risiko**: keiner.

### 2.5 Ticket 8 - CSP-Hinweis fuer Plugin-Doku

**Was**: Live-Preview-iframe braucht in der gerenderten srcdoc-HTML aktuell:
`'unsafe-inline'` (Inline-Style-Block fuer Reset) und `'unsafe-eval'`
(Alpine.js v3.x). Doku-Update.

**Konkret**:
- `docs/architecture/14-CSP-COMPATIBILITY.md` um neue Sektion "Live-Preview
  (Admin)" ergaenzen:
  - srcdoc-iframe ist eine separate Origin im Browser-Sinne
    (`about:srcdoc`), bekommt nicht den Parent-CSP-Header.
  - Falls Parent-Site striktes CSP hat: `frame-src 'self' about:`
    (oder breiter `frame-src *`) wird benoetigt, damit srcdoc-iframe ueberhaupt
    geladen werden darf.
  - Inline-Style-Block im Renderer (`build_html_document` Zeile 296-297)
    laeuft in srcdoc als Inline-Style - kein CSP-Konflikt, da srcdoc keinen
    Parent-CSP erbt (Browser-Standard, dokumentieren).
- Hinweis: Live-Preview ist Admin-only, daher keine Public-CSP-Anforderung.

**BC-Impact**: keiner.

**Aufwand**: 15 min. **Risiko**: keiner.

### 2.6 Ticket 9 - Frontend-Service-Slug-Whitelist als Konstante

**Was**: `PREVIEW_SERVICES`-Array in `admin/js/dhps-admin-react.js` Zeilen
655-665 ist eine Whitelist mit 9 Eintraegen. Das Array existiert nur einmal
und wird nur dort genutzt (1 SelectControl). SEC L4 forderte zusaetzlich
ein Whitelist-Check VOR dem REST-Call.

**Konkret**:
- `PREVIEW_SERVICES` umbenennen / dokumentieren als
  `DHPS_PREVIEW_SERVICE_SLUGS` (Konstante-Charakter).
- In `LivePreviewPanel.runPreview` zusaetzlich `validSlugs.includes(service)`
  vor `apiFetch` pruefen - bei Fail zeigt React Notice
  "Ungueltiger Service-Slug" (Defense in Depth, Backend rejected sowieso).
- Doku-Kommentar: "Whitelist muss mit ALLOWED_SERVICES in
  `class-dhps-admin-rest.php` synchron gehalten werden."

**BC-Impact**: keiner - rein additive Frontend-Validierung.

**Aufwand**: 15 min. **Risiko**: niedrig.

### 2.7 Easy-Wins-Bilanz

| Ticket | Aufwand | Bereich |
|--------|---------|---------|
| 1 | 10 min | Doku (Discovery v0153) |
| 2 | 10 min | Backend (REST) |
| 3 | 10 min | Frontend (React) |
| 4 | 10 min | Doku (Discovery + Memory) |
| 8 | 15 min | Doku (CSP) |
| 9 | 15 min | Frontend (React) |
| **Summe** | **~70 min** | gemischt |

Lead arbeitet alle 6 Tickets in einer einzigen Sitzung ab, ohne Spec-Overhead.

---

## 3. Medium-Spec-Aufteilung

Die 2 verbleibenden Tickets (5 + 6) brauchen jeweils mehr Kontext und sollten
in einer Spec gebuendelt werden, die strukturell der v0.15.3-Aufteilung folgt
(Backend + Frontend in einer Iteration).

### 3.1 Empfehlung: 1 Spec mit zwei klar getrennten Teilen

| # | Spec | Scope | Files |
|---|------|-------|-------|
| **F1** | Backend-Erweiterung (Sub-Shortcodes + postMessage-Wrapper) | `DHPS_Admin_REST::ALLOWED_SERVICES` erweitern, `DHPS_Preview_Renderer` Atts-Validation pro Sub-Shortcode + postMessage-JS-Snippet im HTML-Document-Wrapper | `class-dhps-admin-rest.php`, `class-dhps-preview-renderer.php` |
| **F2** | Frontend-Erweiterung (Sub-Shortcodes-Dropdown + iframe-Resize-Listener) | `PREVIEW_SERVICES` um 4 Eintraege erweitern, conditional Atts-Fields (z.B. `einzelvideo` nur bei `maes_videos`), postMessage-Listener im iframe-Wrapper-Komponente | `admin/js/dhps-admin-react.js` |

**Warum nicht 2 Specs (analog v0.15.3)?**

- Sub-Shortcodes-Logik ist sehr eng: Backend definiert Whitelist, Frontend
  zeigt sie. Ein-Spec-Pattern reduziert Schema-Drift-Risiko (Lehre v0.15.0
  ist ohnehin bestaetigt; Discovery 9.3 wird im Easy-Win-Ticket 1 angepasst).
- postMessage ist klein genug (Backend liefert <script>-Snippet, Frontend
  hat einen useEffect-Listener) - kein eigener F-Slot noetig.
- Token-Budget: Ein Spec mit Backend + Frontend bleibt unter dem v0.15.0
  Spec-Volumen.

**Trade-off**: Ein Spec heisst weniger Parallelitaet. Bei ~ 2 h Gesamt-Aufwand
ist das vertretbar.

### 3.2 Aufwand-Schaetzung

| Phase | Aufwand | Parallelisierbar |
|-------|---------|------------------|
| Easy-Wins (Lead-Direct, 6 Tickets) | 70 min | nein |
| Spec F1+F2 Implementation (Sub-Shortcodes + postMessage) | 2-3 h | F1+F2-intern parallel oder sequenziell |
| Lead-Composition + Smoke | 30 min | nein |
| QA (regression auf bestehende 9 Services) | 30 min | parallel zu Doku |
| CHANGELOG + Version-Bump + Tag | 30 min | nach QA |
| **Gesamt-Wall-Clock** | **4-5 h** | bei zuegigem Lead |

---

## 4. Sub-Shortcodes-Implementation-Details (Ticket 6)

### 4.1 Die 4 Sub-Shortcodes

| Shortcode | Datei | Atts (aus `shortcode_atts`) |
|-----------|-------|------------------------------|
| `mio_termine` | `includes/class-dhps-steuertermine.php` Zeile 63-69 | `count`, `month`, `layout`, `class`, `cache` |
| `maes_videos` | `includes/class-dhps-maes-modules.php` Zeile 98-107 | `layout`, `columns`, `einzelvideo`, `videoliste`, `lazy_count`, `lazy_mode`, `class`, `cache` |
| `maes_merkblaetter` | `includes/class-dhps-maes-modules.php` Zeile 171-175 | `layout`, `class`, `cache` |
| `maes_aktuelles` | `includes/class-dhps-maes-modules.php` Zeile 215-220 | `layout`, `columns`, `class`, `cache` |

**Wichtig**: Diese 4 Shortcodes sind NICHT ueber die Service-Registry
registriert, sondern direkt via `add_shortcode()`. Sie haben damit auch keinen
Eintrag in `DHPS_Service_Registry::get_service()`.

### 4.2 Whitelist erweitern

```
ALLOWED_SERVICES = [
  'mio', 'lxmio', 'mmb', 'mil', 'tp', 'tpt', 'tc', 'maes', 'lp',
  // NEU in v0.15.4:
  'mio_termine', 'maes_videos', 'maes_merkblaetter', 'maes_aktuelles',
]
```

**Problem**: Das aktuelle Whitelist-Regex `/services/(?P<service>[a-z]+)/preview`
matcht nur `[a-z]+` - Unterstrich faellt durch. Regex muss zu
`[a-z_]+` erweitert werden.

**Side-Effect**: Wenn die Regex erweitert wird, betrifft das alle 4 Routes,
die `(?P<service>[a-z]+)` nutzen (`/health`, `/test`, `/preview`,
`/cache/flush` indirekt). Defense-in-Depth: `validate_service_param()` greift
weiter via Whitelist - kein Regression-Risiko.

### 4.3 Service-Registry-Bypass

Die 4 Sub-Shortcodes haben keinen Eintrag in `DHPS_Service_Registry`.
Im aktuellen `handle_service_preview()` (Zeile 541) wird `get_service($service)`
aufgerufen und prueft auf `null` -> `invalid_service`.

**Loesungs-Optionen**:

A) **Sub-Shortcode-zu-Parent-Mapping** (empfohlen): Eine statische Map
   `SUB_SHORTCODE_PARENTS = [ 'mio_termine' => 'mio', 'maes_videos' => 'maes',
   'maes_merkblaetter' => 'maes', 'maes_aktuelles' => 'maes' ]`. Fuer
   Auth-Token und Endpoint wird der Parent verwendet (z.B. MAES-kdnr fuer
   alle `maes_*`). Keine Registry-Aenderung noetig.

B) **Registry erweitern**: Sub-Shortcodes als eigene `services`-Eintraege
   registrieren. Bricht aber das bestehende Konzept (Service = Haupt-Pipeline).

**Empfehlung A**, weil:
- Sub-Shortcodes haben dieselben OTAs/kdnr wie ihre Parents (MIO-OTA fuer
  `mio_termine`, MAES-kdnr fuer alle `maes_*`).
- Kein Architektur-Bruch.
- Reduziert Aufwand erheblich.

### 4.4 Atts-Handling pro Sub-Shortcode

Aktuell sanitisiert Renderer nur `layout`, `class`, `section`. Sub-Shortcodes
brauchen mehr:

**Minimal-Scope v0.15.4** (NICHT der volle Atts-Editor von Ticket 7):
- Generisch: `layout`, `class`, `cache` (alle 4 Sub-Shortcodes haben das).
- Spezifisch in v0.15.4 NICHT exposed (gehoeren zu Ticket 7):
  - `mio_termine`: `count`, `month`
  - `maes_videos`: `columns`, `einzelvideo`, `videoliste`, `lazy_count`,
    `lazy_mode`
  - `maes_aktuelles`: `columns`

Damit ist die Sub-Shortcode-Preview funktional, aber nicht voll
parametrisierbar. Voll-Parametrisierung kommt in v0.15.5 (Ticket 7).

### 4.5 Renderer-Aenderung

In `class-dhps-preview-renderer.php` `render()`:
- `section`-Att wird fuer Sub-Shortcodes irrelevant.
- `SUB_SHORTCODE_PARENTS`-Map fuer Service-JS-Selection
  (`maes_videos` -> nutzt `tp.js`-Pipeline wie der MAES-Parent).
- `service_label` und body-class auf Sub-Shortcode-Slug setzen
  (`dhps-service--maes_videos`).

### 4.6 Frontend-Erweiterung

- `PREVIEW_SERVICES`-Array um 4 Eintraege erweitern.
- Im `LivePreviewControls` conditional: wenn `service.indexOf('_') !== -1`,
  dann ist es ein Sub-Shortcode, dann (a) Section-Dropdown ausblenden,
  (b) ggf. "Sub-Shortcode"-Badge anzeigen.
- Sortierung in Dropdown: Haupt-Services oben, dann Trenner, dann Sub-Shortcodes
  (oder Gruppierung `optgroup`).

### 4.7 Risiko

- **Sub-Shortcodes ohne Auth schlagen fehl**: Wenn `mio_termine` getestet wird
  aber `dhps_ota_mio` leer ist, kommt `service_not_configured`. Das ist OK,
  zeigt nur die Realitaet.
- **MAES-Sub-Shortcodes brauchen alle MAES-kdnr** - `maes_videos` ohne kdnr =
  leerer Output, kein Fehler. Frontend zeigt "leere Preview" - akzeptabel.

---

## 5. postMessage-Resize-Implementation-Details (Ticket 5)

### 5.1 Konzept

iframe-Inhalt misst seine eigene Hoehe (`document.body.scrollHeight`) und
sendet sie via `window.parent.postMessage({ type: 'dhps-preview-resize',
height: N })` an den Parent-Frame. Parent-React-Component hoert via
`window.addEventListener('message', ...)` zu und setzt die iframe-Hoehe.

### 5.2 Backend-Erweiterung (in `DHPS_Preview_Renderer`)

JS-Snippet in `build_html_document()` vor `</body>`:

```text
<script>
(function () {
  var lastH = 0;
  function send() {
    var h = Math.max(
      document.documentElement.scrollHeight,
      document.body.scrollHeight
    );
    if (h !== lastH) {
      lastH = h;
      window.parent.postMessage({
        type: 'dhps-preview-resize',
        height: h
      }, '*');
    }
  }
  window.addEventListener('load', send);
  window.addEventListener('resize', send);
  // ResizeObserver fuer Alpine-getriebene Hoehen-Aenderungen.
  if (window.ResizeObserver) {
    new ResizeObserver(send).observe(document.body);
  } else {
    setInterval(send, 1000);
  }
})();
</script>
```

**Sicherheits-Hinweise**:
- `targetOrigin = '*'` ist akzeptabel, weil:
  - iframe ist same-origin (sandbox `allow-same-origin`).
  - Nachricht enthaelt nur eine Zahl.
  - Admin-only Endpoint.
- Alternativ `targetOrigin = window.location.origin` (sauberer).
- ResizeObserver-Fallback via setInterval(1000ms) - low CPU.

### 5.3 Frontend-Listener (in `LivePreviewIframe`)

```text
function LivePreviewIframe( props ) {
  var html = props.html || '';
  var service = props.service || 'unknown';
  var stateHeight = useState( 600 );
  var height = stateHeight[ 0 ];
  var setHeight = stateHeight[ 1 ];

  useEffect( function () {
    function onMessage( event ) {
      // Security: Origin-Check
      if ( event.origin !== window.location.origin ) return;
      var data = event.data || {};
      if ( data.type !== 'dhps-preview-resize' ) return;
      var h = parseInt( data.height, 10 );
      if ( ! isNaN( h ) && h > 0 && h < 10000 ) {
        setHeight( h );
      }
    }
    window.addEventListener( 'message', onMessage );
    return function () {
      window.removeEventListener( 'message', onMessage );
    };
  }, [] );

  return h( 'iframe', {
    key: ...,
    srcDoc: html,
    sandbox: 'allow-same-origin allow-scripts',
    style: {
      width: '100%',
      height: height + 'px',
      maxHeight: '4000px',  // Schutz vor unendlicher Hoehe
      ...
    }
  } );
}
```

**Security-Hardening (Pflicht)**:
- `event.origin !== window.location.origin` Check.
- `event.data.type === 'dhps-preview-resize'` strict-equal.
- `parseInt(data.height, 10)` mit Bounds-Check (1-9999).
- Max-Hoehe-Cap (4000 px) gegen unendlich-Resize-DoS.

### 5.4 BC-Impact

- iframe-Hoehe wird dynamisch statt fixed - keine Funktions-Aenderung,
  nur UX-Verbesserung.
- Bei Sandbox-Browser-Edge-Case (postMessage blocked): bleibt initial 600 px,
  scrollbar - identisches Verhalten wie v0.15.3.

### 5.5 Aufwand-Schaetzung

| Stelle | Aufwand |
|--------|---------|
| Backend JS-Snippet in Renderer | 15 min |
| Frontend useEffect + State | 20 min |
| QA: Cross-Service-Smoke | 15 min |
| **Summe** | **~ 50 min** |

---

## 6. Atts-Editor-Komplexitaet (Ticket 7) - Empfehlung Scope

### 6.1 Problem

Aktuell ist die Atts-Whitelist auf 3 Felder hardcoded (`layout`, `class`,
`section`). Voller Atts-Editor heisst: pro Service alle in der jeweiligen
`shortcode_atts()`-Definition deklarierten Atts mit passendem UI-Control
(Text / Number / Select / Checkbox).

### 6.2 Komplexitaets-Analyse

#### 6.2.1 Service-Atts-Inventar (Auszug)

| Service | Atts | Typ-Sample |
|---------|------|------------|
| `mio` | layout, class, modus, anzahl, cache | mix Select/Number/Text |
| `mio_termine` | count, month, layout, class, cache | Number, Select, Text |
| `mmb` | layout, class, kategorie, lazy_count, cache | Select, Text, Number |
| `tp` | layout, class, kategorie, anzahl, cache | mix |
| `tpt` | layout, class, teasermodus, anzahl, cache | mix |
| `tc` | layout, class, cache, rechner_id (?) | Select |
| `maes` | layout, class, section, cache | Select |
| `maes_videos` | layout, columns, einzelvideo, videoliste, lazy_count, lazy_mode, class, cache | komplex |
| `maes_merkblaetter` | layout, class, cache | simpel |
| `maes_aktuelles` | layout, columns, class, cache | simpel |
| `lxmio`, `mil`, `lp` | analog Parent (mio/mmb/tp) | analog |

13 Shortcodes, ca 5-10 Atts pro Shortcode, total ~ 80-100 Atts mit
unterschiedlicher Validierungs-Semantik.

#### 6.2.2 Implementierungs-Patterns

**Pattern A: Statische Atts-Map pro Service**
- Pro Service Atts-Definition fuer Frontend (Typ, Label, Default, Whitelist).
- Backend hat eigene parallele Whitelist (Schema-Drift-Risiko hoch).
- Aufwand: ~ 6-8 h fuer Mapping + Validation + UI.

**Pattern B: Dynamische Atts-Discovery**
- Backend exposed `GET /dhps/v1/services/{slug}/atts-schema` mit Typ +
  Default + Whitelist.
- Frontend rendert dynamisch.
- Aufwand: ~ 10-14 h, hoehere Architektur-Investition.

**Pattern C: Free-Text-Editor mit Backend-Sanitization**
- Frontend zeigt ein Textarea "Atts (key=value pro Zeile)".
- Backend sanitisiert via `shortcode_atts()`-Parser.
- Aufwand: ~ 1-2 h.
- Trade-off: keine UX-Hilfe (User muss Atts-Namen kennen).

### 6.3 Empfehlung Scope

**Ticket 7 nicht in v0.15.4 aufnehmen.** Begruendung:

1. **Aufwand-Mismatch**: v0.15.4 ist als Tech-Debt-Cleanup-Iteration positioniert,
   nicht als neue Feature-Iteration. Ticket 7 mit ~ 6-14 h sprengt das.
2. **Schema-Drift-Gefahr**: Pattern A mit doppelter Whitelist (Backend + Frontend)
   ist das Schema-Drift-Setup, das wir aus v0.15.0 schmerzhaft gelernt haben.
   Pattern B mit dynamischer Schema-Endpoint braucht eigene Discovery.
3. **Atts-Editor profitiert von einheitlichem Datenmodell**: Im Memory ist
   "Einheitliches Datenmodell" als User-Wunsch fuer v1.0 vermerkt. Atts-Editor
   sollte erst dann gebaut werden, wenn das Datenmodell konsolidiert ist
   (sonst muss er zweimal gebaut werden).

**Vorschlag**:
- **v0.15.4**: Sub-Shortcodes (Ticket 6) MIT nur den generischen Atts
  (`layout`, `class`, `cache`) - das ist 95 % vom Wert mit 20 % vom Aufwand.
- **v0.15.5**: Eigene Discovery "Atts-Editor v1" mit Pattern B (Schema-Endpoint),
  weil das Pattern langfristig die richtige Investition ist.
- **v0.16.0+**: Wenn einheitliches Datenmodell kommt, Atts-Editor v2.

### 6.4 Kurz-Antwort an Architekt

| Frage | Antwort |
|-------|---------|
| Pattern A oder B? | B (Schema-Endpoint) - Schema-Drift-Vermeidung |
| In v0.15.4? | NEIN - in v0.15.5 als eigene Iteration |
| UI-Bausteine? | `SelectControl`, `TextControl`, `CheckboxControl`, `NumberControl` aus `wp.components` |
| Bei v0.15.4 minimal? | Sub-Shortcodes mit `layout`/`class`/`cache` reicht - das ist Ticket 6, nicht Ticket 7 |

---

## 7. Scope-Empfehlung v0.15.4 vs Verschoben

### 7.1 v0.15.4 (Tech-Debt-Cleanup)

**Pflicht-Tickets (8 von 9)**:

| # | Ticket | Wer | Aufwand |
|---|--------|-----|---------|
| 1 | Schema-Sync Discovery <-> Code | Lead-Direct | 10 min |
| 2 | Error-Code `invalid_format` | Lead-Direct | 10 min |
| 3 | 500-KB-Soft-Warning | Lead-Direct | 10 min |
| 4 | iframe Re-Mount-Pattern dokumentieren | Lead-Direct | 10 min |
| 5 | postMessage-Resize | Spec F1+F2 | 50 min |
| 6 | Sub-Shortcodes preview-faehig (mit minimalen Atts) | Spec F1+F2 | 2-3 h |
| 8 | CSP-Hinweis Doku | Lead-Direct | 15 min |
| 9 | Frontend-Service-Slug-Whitelist Konstante | Lead-Direct | 15 min |

### 7.2 Verschoben auf v0.15.5+

**1 Ticket**:

| # | Ticket | Begruendung Verschiebung | Ziel |
|---|--------|--------------------------|------|
| 7 | Voller Atts-Editor | Aufwand ~ 6-14 h, Schema-Drift-Risiko, profitiert von einheitlichem Datenmodell | v0.15.5 eigene Iteration mit Discovery |

### 7.3 Out-of-Scope (v0.15.x komplett)

- Einheitliches Datenmodell (User-Wunsch, v1.0).
- Preview-URL `src=URL` statt `srcdoc` (nur wenn srcdoc-Groesse > 1 MB Problem wird).
- Auto-Refresh / Polling (Nice-to-have).
- Tab-System im App-Component.
- WP-CLI Live-Preview.

---

## 8. Erwarteter Gesamt-Aufwand

### 8.1 v0.15.4-Wall-Clock

| Phase | Aufwand | Parallelisierbar |
|-------|---------|------------------|
| **Discovery-Review** (dieses Dokument) | 30 min | nein |
| **Lead-Direct Easy-Wins** (6 Tickets) | 70 min | nein |
| **Spec F1+F2 Sub-Shortcodes + postMessage** | 2-3 h | F1+F2-intern parallel |
| **Lead-Composition + Smoke** | 30 min | nein |
| **QA (Regression 9+4=13 Services)** | 30 min | parallel zu Doku |
| **CHANGELOG + Version-Bump + Tag** | 30 min | nach QA |
| **Gesamt** | **4-5 h** | bei zuegigem Lead |

### 8.2 Aufwand-Verteilung

```
Easy-Wins (Lead-Direct)   : 70 min  (24 %)
Sub-Shortcodes + Resize   : 150 min (51 %)
Composition + QA + Release: 90 min  (25 %)
                            ----
                            ~ 290 min (4.8 h)
```

### 8.3 Vergleich zu Vorgaenger-Iterationen

| Version | Wall-Clock | Specs | Tickets |
|---------|-----------|-------|---------|
| v0.15.0 | ~ 3 Tage | 3 (F1/F2/F3) | 1 Major (Dashboard) |
| v0.15.1 | ~ 1 Tag | 1 | Tech-Debt |
| v0.15.2 | ~ 1 Tag | 1 | Tech-Debt + Layouts |
| v0.15.3 | ~ 3 Tage | 2 (F1/F2) | 1 Major (Live-Preview) |
| **v0.15.4** | **~ 5 h** | **1** | **8 (gemischt)** |

v0.15.4 ist eine **Mini-Iteration**: konsolidiert Tech-Debt aus v0.15.3
und liefert 2 funktionale Erweiterungen (Sub-Shortcodes, postMessage-Resize)
ohne neue Architektur.

### 8.4 Risiko-Puffer

- **Regex-Aenderung in REST-Route**: Wenn das Whitelist-Regex `[a-z]+` ->
  `[a-z_]+` regression auf bestehende Routes verursacht, +30 min Debug.
- **postMessage-Cross-Browser**: ResizeObserver-Edge-Cases, +30 min.
- **Realer Puffer**: 5-6 h Wall-Clock.

---

## 9. Naechste Schritte

1. **Discovery-Review durch Architekt** (Stop-Gate vor Implementation).
2. **Lead-Direct** Easy-Wins-Batch (Tickets 1, 2, 3, 4, 8, 9) in einer Sitzung.
3. **Spec F1+F2** Sub-Shortcodes + postMessage-Resize (Tickets 5 + 6).
4. **Lead-Composition + Smoke** (manueller Browser-Test 13 Services).
5. **QA** Regression-Smoke + A11y-Spot-Check (ist v0.15.3-Set noch
   14/14 PASS?).
6. **CHANGELOG v0.15.4** + Version-Bump 0.15.3 -> 0.15.4 + Git-Tag.
7. **Memory-Update**: iframe Re-Mount-Key-Pattern + Schema-Vertrag-Erfolg
   nochmal bestaetigen.

---

## 10. Quellen

- `docs/architecture/21-LIVE-PREVIEW-PLAN-v0153.md` (Discovery v0.15.3)
- `docs/project/34-QA-REPORT-v0153.md` (QA-Report v0.15.3, 3 Minor M1-M3)
- `docs/project/35-SECURITY-AUDIT-v0153.md` (SEC-Audit v0.15.3, 3 Medium + 4 Low)
- `docs/project/36-CHANGELOG-v0153.md` (CHANGELOG v0.15.3, Tech-Debt-Sektion)
- `includes/class-dhps-admin-rest.php` (Zeilen 213-229, 510-651, 741-)
- `includes/class-dhps-preview-renderer.php` (315 LOC)
- `admin/js/dhps-admin-react.js` (Zeilen 655-1015, 4 LivePreview-Components)
- `includes/class-dhps-steuertermine.php` (Zeilen 51, 63-69) - mio_termine
- `includes/class-dhps-maes-modules.php` (Zeilen 44-46, 98-107, 171-175, 215-220)
  - maes_videos, maes_merkblaetter, maes_aktuelles
- `docs/architecture/14-CSP-COMPATIBILITY.md` (CSP-Doku, zu erweitern)
