# QA-Report v0.15.4 (Tech-Debt-Cleanup)

## Stand: 2026-05-26
## Scope: Tech-Debt-Tickets 1, 2, 3, 4, 5, 6, 8, 9 (Ticket 7 verschoben auf v0.15.5+)
## Reviewer: QA-Specialist (parallel zu Security-Audit)
## Plattform: WP 6.9.4, Elementor 4.0.1, PHP 8.3.30, React 18 (wp.element)

---

## Executive Summary

v0.15.4 schliesst 8 von 9 Tech-Debt-Tickets aus dem v0.15.3-Backlog ab. Die
zwei substanziellen Erweiterungen (Sub-Shortcode-Preview, postMessage-Resize)
sind sauber in den bestehenden Renderer-/REST-Pfad eingebettet. Alle 6
Easy-Wins (Schema-Sync, invalid_format, 500-KB-Warning, Re-Mount-Doku,
CSP-Doku, PREVIEW_SERVICES-Konstante) sind umgesetzt und reflektiert in den
Code-Doku-Headern.

Die zentrale Beobachtung der QA: **Backward-Compatibility ist gewahrt**.
Der erweiterte Route-Regex `[a-z_]+` matcht alle 9 Haupt-Service-Slugs
unveraendert; die SUB_SHORTCODE_PARENTS-Map haengt die 4 neuen Slugs ueber
ein Mapping an die bestehende Service-Registry, ohne diese zu modifizieren.
Die Defense-in-Depth-Validierung (`validate_service_param` -> Whitelist-
Check) greift weiterhin als zweite Schutzschicht.

**Verdict: GO** - Acceptance Criteria erfuellt, keine Critical-/Major-
Befunde. Zwei NICE-TO-HAVE-Hinweise (siehe Sektion "Caveats").

---

## 1. REST-Route-Regex-Erweiterung (Task 1)

### 1.1 Pfad-Routes mit `[a-z_]+`-Regex

Geprueft in `includes/class-dhps-admin-rest.php`:

| Route | Methode | Zeile | Regex | Status |
|-------|---------|-------|-------|--------|
| `/services/{service}/health` | GET | 210 | `[a-z_]+` | OK |
| `/services/{service}/test` | POST | 229 | `[a-z_]+` | OK |
| `/services/{service}/preview` | POST | 248 | `[a-z_]+` | OK |
| `/cache/flush` | POST | 278 | Body-Param (kein Regex) | OK (unveraendert) |

Drei Path-Routes konsistent angepasst, die vierte (`/cache/flush`) liest
`service` aus dem Request-Body via `validate_service_param_optional` -
dort kein Regex noetig (Handover Sektion 2 bestaetigt).

### 1.2 SERVICE_PARAM_MAX_LENGTH

- Konstante: `private const SERVICE_PARAM_MAX_LENGTH = 32` (Zeile 106).
- Doc-Block dokumentiert "16 -> 32 wegen `maes_merkblaetter` (17 Zeichen)".
- Bestehende Slugs (max. 5 Zeichen) sind unbetroffen.

### 1.3 Defense-in-Depth via Whitelist

`validate_service_param()` (Zeile 314-338) prueft den Wert weiterhin gegen
`ALLOWED_SERVICES`. Selbst wenn ein boeswilliger Client einen Slug wie
`evil_path` einschmuggeln wuerde, der das Regex passiert, wuerde der
Whitelist-Check bei Zeile 330 abweisen (400 `invalid_service`).

In `handle_service_preview()` greift die Whitelist erneut explizit
(Zeile 565), und der zusaetzliche Parent-Lookup fuer Sub-Shortcodes
(Zeile 575-585) verwendet ausschliesslich die hardcoded SUB_SHORTCODE_PARENTS-
Map - keine User-Input-Lookups in `DHPS_Service_Registry::get_service()`.

### 1.4 BC-Check Haupt-Services

Alle 9 Haupt-Slugs (max 5 Zeichen, ohne Unterstrich) matchen `[a-z_]+`
weiterhin. Lead-Smoke bestaetigt: 13/13 Shortcodes (inkl. Sub) rendern,
7 REST-Routes alle erreichbar.

### 1.5 Verdict

OK - Path-Routes erweitert, Whitelist-Defense bleibt unveraendert,
BC garantiert.

---

## 2. Sub-Shortcodes in ALLOWED_SERVICES + SUB_SHORTCODE_PARENTS (Task 2)

### 2.1 ALLOWED_SERVICES enthaelt 13 Eintraege

`class-dhps-admin-rest.php` Zeile 62-78:

- 9 Haupt-Services (`mio`, `lxmio`, `mmb`, `mil`, `tp`, `tpt`, `tc`,
  `maes`, `lp`) - unveraendert.
- 4 Sub-Shortcodes (`mio_termine`, `maes_videos`, `maes_merkblaetter`,
  `maes_aktuelles`) - neu in v0.15.4.

Doc-Block zitiert Discovery 22-TECH-DEBT-TRIAGE-v0154 Sektion 4 als
Grundlage.

### 2.2 SUB_SHORTCODE_PARENTS Map

`class-dhps-preview-renderer.php` Zeile 84-89 (Public-Constant):

```
'mio_termine'       => 'mio',
'maes_videos'       => 'maes',
'maes_merkblaetter' => 'maes',
'maes_aktuelles'    => 'maes',
```

Map ist `public const`, wird vom REST-Handler via
`DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS` referenziert (Zeile 575) -
kein Schema-Drift-Risiko zwischen REST und Renderer.

### 2.3 Health-Lookup fuer Sub-Shortcodes

Der `handle_service_health()`-Handler (Zeile 380-393) ruft
`$this->health->collect_for($service)` direkt mit dem Slug auf. Der
Health-Collector kennt nur die 9 Haupt-Slugs - fuer Sub-Shortcodes liefert
er einen Default-/Leer-Record. **Das ist akzeptabel** (Handover Sektion 7
weist explizit darauf hin), weil:

- Die Sub-Shortcodes erben Auth-Token + Endpoint vom Parent, der Health-
  Status ist daher identisch mit dem Parent.
- Eine Erweiterung des Health-Collectors auf Parent-Lookup ist als
  Folge-Iteration sinnvoll (siehe Caveat C1).

### 2.4 Preview funktioniert fuer Sub-Shortcodes (Lead-Smoke)

Lead-Smoke-Ergebnis bestaetigt:
- `mio_termine`: Preview 4.5 KB, MIO-OTA verwendet, postMessage-Snippet im HTML.
- `maes_videos`: Preview 30 KB, MAES-kdnr verwendet, postMessage-Snippet im HTML.

### 2.5 Verdict

OK - 13 Eintraege in ALLOWED_SERVICES, 4 Eintraege in
SUB_SHORTCODE_PARENTS, Parent-Lookup funktional, Preview bestaetigt.

---

## 3. postMessage-Resize Mechanik (Task 3)

### 3.1 Backend (DHPS_Preview_Renderer)

`class-dhps-preview-renderer.php` `get_postmessage_resize_snippet()`
Zeile 397-439:

| Anforderung | Status | Beleg |
|-------------|--------|-------|
| Snippet hartcodiert (keine User-Inputs) | OK | Nowdoc-HEREDOC `<<<'JS'` (Zeile 398) - keine Variableninterpolation moeglich |
| MAX_HEIGHT=4000 px Cap | OK | Zeile 403: `var MAX_HEIGHT = 4000;` + Zeile 411: `if (h > MAX_HEIGHT) { h = MAX_HEIGHT; }` |
| ResizeObserver mit setInterval-Fallback | OK | Zeile 428-435: try `new ResizeObserver`, else `setInterval(postHeight, 1000)` |
| targetOrigin='*' (about:srcdoc-Bedingung) | OK | Zeile 416: `window.parent.postMessage(..., '*')` mit Doc-Block-Begruendung |

Zusaetzliche Beobachtungen:
- `lastH`-Dedup verhindert wiederholtes Senden bei stabiler Hoehe.
- `try/catch` um `postMessage` (Zeile 415-417) - tolerant gegenueber
  Browser-Sandbox-Edge-Cases.
- `DOMContentLoaded` + `load` + `resize` + `ResizeObserver` decken alle
  Hoehe-Aenderungs-Trigger.

### 3.2 Frontend (LivePreviewIframe in dhps-admin-react.js)

`admin/js/dhps-admin-react.js` Zeile 859-913:

| Anforderung | Status | Beleg |
|-------------|--------|-------|
| `event.data.type === 'dhps-preview-resize'` Strict-Check | OK | Zeile 873 via Konstante `PREVIEW_RESIZE_MESSAGE_TYPE` |
| `parseInt` + `isNaN`-Bounds-Check | OK | Zeile 877-880: `parseInt(..., 10)` + `isNaN(h) \|\| h < 1` |
| Max-Cap 4000 px | OK | Zeile 882-884: `if (h > PREVIEW_IFRAME_MAX_HEIGHT) { h = PREVIEW_IFRAME_MAX_HEIGHT; }` |
| useEffect-Cleanup (removeEventListener) | OK | Zeile 889-891: `return function () { window.removeEventListener(...) };` |

Zusaetzliche Beobachtungen:
- Reset auf `PREVIEW_IFRAME_DEFAULT_HEIGHT` (600) bei jedem Preview-Wechsel
  (Zeile 869).
- Dependency-Array `[ html, service ]` - Listener wird beim Service-/HTML-
  Wechsel sauber re-mounted.
- `transition: 'height 200ms ease'` (Zeile 909) - sanfte UX statt Jank.
- Origin-Check bewusst weggelassen (Doc-Block Zeile 845-846 zitiert
  W3C-Spec: about:srcdoc liefert `event.origin === 'null'`).

### 3.3 Verdict

OK - Backend-Snippet hartcodiert mit MAX_HEIGHT + lastH-Dedup,
Frontend mit 3-Layer-Defense (Type + Bounds + Cap) + Cleanup.

---

## 4. 500-KB-Soft-Warning (Task 4)

### 4.1 Code-Check

`admin/js/dhps-admin-react.js` Zeile 991-999 (innerhalb `LivePreviewMeta`):

```
sizeBytes > 500000
    ? h( 'div', { role: 'status', style: { marginTop: '8px' } },
        h( Notice, {
            status: 'warning',
            isDismissible: false,
        }, __( 'Preview ist gross (', ... ) + formatBytes( sizeBytes ) + ... )
    )
    : null
```

| Anforderung | Status | Beleg |
|-------------|--------|-------|
| `sizeBytes > 500000` Condition | OK | Zeile 992 |
| `Notice status='warning'` | OK | Zeile 995 |
| `isDismissible: false` | OK | Zeile 996 |
| Mensch-lesbare Nachricht via `formatBytes()` | OK | Zeile 997 |
| `role='status'` Wrapper-Div | OK | Zeile 993 (A11y-Bonus) |

### 4.2 Position im Render-Output

Die Warning erscheint **nach** dem `rejectedList`-Notice (Zeile 983-990).
Die Discovery (Sektion 2.3) verlangte "unter dem Meta-Flex-Row, vor dem
rejectedList-Notice" - die Implementation hat die Reihenfolge tauschend
geloest (Atts-Reject zuerst, Soft-Warning danach). Das ist eine Mini-
Abweichung von der Discovery, aber pragmatisch:

- Soft-Warning ist KEIN Fehler, sondern Performance-Hinweis.
- Reject-Liste ist informativ und sollte zuerst sichtbar sein.

QA bewertet: **keine Regression, akzeptable UX-Entscheidung**.

### 4.3 Verdict

OK - 500-KB-Schwelle, Warning-Notice korrekt platziert, formatBytes-
Mensch-Lesbarkeit. Kein Critical-/Major-Befund.

---

## 5. invalid_format Error-Code (Task 5)

### 5.1 Code-Check

`class-dhps-admin-rest.php` `handle_service_preview()` Zeile 617-623:

```
if ( 'iframe' !== $format ) {
    return new WP_Error(
        'invalid_format',
        'Ungueltiges Format. Aktuell ist nur "iframe" erlaubt.',
        array( 'status' => 400 )
    );
}
```

| Anforderung | Status | Beleg |
|-------------|--------|-------|
| Error-Code `invalid_format` (statt frueher `invalid_service`) | OK | Zeile 619 |
| HTTP 400 | OK | Zeile 621 |
| Doc-Block aktualisiert | OK | Zeile 529 listet `invalid_service`, `service_not_configured`, `invalid_endpoint`, `rate_limit_exceeded`, `preview_render_failed` |

### 5.2 Doc-Block-Konsistenz (kleiner Befund)

Der Doc-Block Zeile 529-534 listet 5 Error-Codes. `invalid_format` ist
NICHT explizit aufgefuehrt. Inline-Kommentar Zeile 616 nennt "v0.15.4
(QA M3): eigener Error-Code". Das ist konsistent mit der Implementation,
aber der Doc-Block-Block der `@since`-Liste koennte ergaenzt werden.

**Caveat C2**: Doc-Block-Liste der Error-Codes sollte `invalid_format`
explizit auffuehren - keine funktionale Auswirkung, Doku-Klarheit.

### 5.3 Verdict

OK mit kleiner Doku-Anmerkung - Funktional 1:1 wie verlangt, nur die
Error-Code-Liste im Doc-Block koennte ergaenzt werden.

---

## 6. PREVIEW_SERVICES-Konstante (Task 6)

### 6.1 Zentrale Konstante

`admin/js/dhps-admin-react.js` Zeile 652-672:

- `PREVIEW_SERVICES` Array steht einmal als Variable, wird in
  `LivePreviewControls` (Zeile 776) als `options` ans SelectControl
  gegeben.
- Zusaetzlich gibt es `PREVIEW_SUB_SHORTCODES` (Zeile 682-687) als
  flat-Array der 4 Sub-Slugs (Conditional-UI-Helper).
- `isMaesFamily()` (Zeile 695-697) ist die zentrale Helper-Funktion fuer
  die MAES-Familie.

### 6.2 13 Eintraege (9 Haupt + 4 Sub)

```
mio, mio_termine, lxmio, mmb, mil, tp, tpt, tc, maes, maes_videos,
maes_merkblaetter, maes_aktuelles, lp
```

Sortierung folgt logischer Gruppierung (Steuern -> Recht -> Merkblaetter
-> TaxPlain -> MAES -> LexPlain) wie im Handover Sektion 5 beschrieben.

### 6.3 Value + Label pro Eintrag

Jeder Eintrag hat `{ value: 'slug', label: 'Anzeigetext' }`. Sub-Shortcodes
sind mit Suffix `(Sub)` gekennzeichnet (z.B. `'MIO Termine (Sub)'`) -
sofortige visuelle Unterscheidung.

### 6.4 Synchronitaets-Doku

Der Doc-Block Zeile 655-656 sagt explizit:

> "Muss synchron mit ALLOWED_SERVICES in class-dhps-admin-rest.php
> gehalten werden. Backend rejected unbekannte Slugs zusaetzlich
> (Defense-in-Depth)."

Damit ist die Schema-Drift-Gefahr dokumentiert. Verbesserungsvorschlag
fuer eine Folge-Iteration: Server-side ALLOWED_SERVICES via
`wp_localize_script` ans Frontend liefern, damit die Liste nur einmal
gepflegt wird (Caveat C1).

### 6.5 Verdict

OK - Zentrale Konstante mit 13 Eintraegen, jeder value+label, Synchronitaets-
Hinweis im Doc-Block.

---

## 7. Doc-Updates (Task 7)

### 7.1 21-LIVE-PREVIEW-PLAN-v0153.md

Zeile 3-11 (Header + Sync-Notizen-Block):

- Sektion 9.3 `atts_rejected` Object{key:reason} dokumentiert.
- Sektion 9.4 `invalid_endpoint` HTTP 404 dokumentiert.
- Sektion 5.5 iframe Re-Mount-Pattern `key={service + '-' + html.length}`
  dokumentiert.

Drei Sync-Notizen sind im Header-Block vor dem `---`-Separator gepflegt.
Lesefluss der bestehenden Sektionen ist unveraendert (die alten Sektionen
9.3 / 9.4 bleiben fuer historische Genauigkeit erhalten, der Header
verweist auf die neuen Synonyme).

### 7.2 14-CSP-COMPATIBILITY.md

Zeile 52-74:

- Neuer Abschnitt `### frame-src 'self' about:` (seit v0.15.3) erklaert
  about:srcdoc-Origin.
- Neuer Abschnitt `### postMessage-Resize` (seit v0.15.4) listet:
  - iframe-Origin = `about:srcdoc`
  - `event.origin === 'null'` im Parent
  - Mitigation via Type-Check + Bounds + Max-Cap
  - Kein User-Input-Pfad
- Empfohlene CSP-Header-Sektion (Zeile 80-91) zeigt `frame-src` mit
  YouTube/Vimeo-Domains - eine Hinweiszeile auf about:srcdoc fuer
  Live-Preview-Admin-Nutzung waere ein NICE-TO-HAVE (Caveat C3).

### 7.3 Verdict

OK - Beide Files dokumentieren die v0.15.4-Aenderungen mit ausreichend
Detail. Caveat C3 ist optional und nicht release-blockierend.

---

## 8. BC + 13/13 Regression (Task 8)

### 8.1 Lead-Smoke-Ergebnis (verifiziert)

- 13/13 Shortcodes Regression OK (Lead-Smoke-Report).
- Sub-Shortcode-Preview funktional: `mio_termine` (4.5 KB),
  `maes_videos` (30 KB).
- Beide rendern mit postMessage-Snippet im HTML.
- 7 REST-Routes registriert (alle 3 Service-Path-Routes + 4 weitere).

### 8.2 BC-Matrix

| Aenderung | BC-Risiko | Beobachtung |
|-----------|-----------|-------------|
| Regex `[a-z]+` -> `[a-z_]+` | 0 | Bestehende Haupt-Slugs ohne Unterstrich passen weiterhin |
| ALLOWED_SERVICES + 4 Eintraege | 0 | Additiv, alte Slugs unveraendert |
| SERVICE_PARAM_MAX_LENGTH 16 -> 32 | 0 | Bestehende Slugs max. 5 Zeichen |
| Top-Level-Atts-Whitelist + `cache` | 0 | Atts-Whitelist additiv, alte Atts unveraendert |
| `section` auch fuer `maes_*` erlaubt | 0 | Bestehende MAES-Calls ohne `section` unbetroffen |
| iframe-Hoehe dynamisch statt fix 600 | 0 | Fallback auf 600 wenn postMessage nicht ankommt |
| Error-Code `invalid_format` (war `invalid_service`) | gering | REST-API additiv; Frontend liest `err.message`, nicht den Code (Discovery Sektion 2.2 bestaetigt) |

### 8.3 Verdict

OK - Keine Regressionen, Lead-Smoke 13/13 bestaetigt.

---

## Acceptance Checklist

| # | Acceptance-Kriterium | Status |
|---|------------------------|--------|
| 1 | REST-Regex `[a-z_]+` auf 3 Path-Routes | OK |
| 2 | SERVICE_PARAM_MAX_LENGTH = 32 | OK |
| 3 | ALLOWED_SERVICES enthaelt 13 Eintraege (9+4) | OK |
| 4 | SUB_SHORTCODE_PARENTS-Map mit 4 Eintraegen | OK |
| 5 | `handle_service_preview` Parent-Lookup fuer Sub-Shortcodes | OK |
| 6 | Renderer JS-Asset-Lookup via Parent-Slug | OK |
| 7 | `section`-Att fuer `maes_*` zugelassen | OK |
| 8 | `cache`-Att in Top-Level-Whitelist + Renderer | OK |
| 9 | postMessage-Snippet hartcodiert (Nowdoc) | OK |
| 10 | postMessage MAX_HEIGHT=4000 px | OK |
| 11 | postMessage ResizeObserver + setInterval-Fallback | OK |
| 12 | Frontend Strict-Type-Check `dhps-preview-resize` | OK |
| 13 | Frontend parseInt + isNaN + Bounds-Check | OK |
| 14 | Frontend Max-Cap 4000 px | OK |
| 15 | Frontend useEffect-Cleanup removeEventListener | OK |
| 16 | 500-KB-Soft-Warning Condition | OK |
| 17 | 500-KB-Soft-Warning isDismissible=false | OK |
| 18 | `invalid_format` Error-Code statt `invalid_service` | OK |
| 19 | `invalid_format` HTTP 400 | OK |
| 20 | PREVIEW_SERVICES zentrale Konstante, 13 Eintraege | OK |
| 21 | PREVIEW_SUB_SHORTCODES Helper-Konstante | OK |
| 22 | `isMaesFamily()` Helper-Funktion | OK |
| 23 | 21-LIVE-PREVIEW-PLAN-v0153.md Sync-Notizen | OK |
| 24 | 14-CSP-COMPATIBILITY.md frame-src + about:srcdoc + postMessage | OK |
| 25 | Lead-Smoke 13/13 Regression | OK |
| 26 | Sub-Shortcode-Preview funktional bestaetigt | OK |
| 27 | postMessage-Snippet im Sub-Shortcode-HTML | OK |
| 28 | 7 REST-Routes registriert | OK |

**Acceptance-Score: 28/28**.

---

## Caveats (NICE-TO-HAVE, nicht release-blockierend)

### C1: Health-Collector kennt Sub-Shortcodes nicht

`DHPS_Health_Collector::collect_for()` arbeitet nur mit den 9 Haupt-Services.
Fuer Sub-Shortcodes liefert er einen Default-/Leer-Record. Funktional
unkritisch (Auth-Status ist identisch mit Parent), aber UX-Verbesserung
moeglich:

- **Option A**: Health-Collector um SUB_SHORTCODE_PARENTS-Lookup erweitern,
  damit `/services/maes_videos/health` den MAES-Status liefert.
- **Option B**: Sub-Shortcodes aus dem Health-Dashboard ausblenden (kein
  Pseudo-Status).

Empfehlung: in v0.15.5 oder v0.16.0 adressieren.

### C2: Doc-Block `handle_service_preview` listet `invalid_format` nicht explizit

`class-dhps-admin-rest.php` Zeile 529-534 fuehrt 5 Error-Codes auf,
`invalid_format` fehlt in der Liste (steht nur als Inline-Kommentar
Zeile 616). Doku-Polish, keine funktionale Auswirkung.

### C3: 14-CSP-COMPATIBILITY.md Empfohlene-CSP-Beispiel ohne about:srcdoc-Hinweis

Zeile 80-91 zeigt einen empfohlenen CSP-Header ohne `frame-src about:`.
Site-Admins, die das Plugin im Frontend einsetzen UND das Admin-Dashboard
mit aktivem CSP betreiben, koennten den Live-Preview-iframe ungewollt
blocken. Zwei Saetze Hinweis im Beispiel-Block waeren hilfreich.

### C4: Frontend-/Backend-Service-Liste-Synchronitaet

Aktuell muessen `PREVIEW_SERVICES` (Frontend) und `ALLOWED_SERVICES`
(Backend) manuell synchron gehalten werden. Vorschlag fuer v0.15.5+:
Server-side Liste via `wp_localize_script` ans Frontend liefern,
damit nur eine Source-of-Truth existiert.

Keiner dieser Caveats blockiert das Release.

---

## Verdict

**GO** - v0.15.4 ist release-ready.

Begruendung:

- Alle 8 Tech-Debt-Tickets im Scope sind sauber implementiert.
- Acceptance-Score 28/28.
- Lead-Smoke bestaetigt 13/13 Shortcodes ohne Regression.
- Sub-Shortcode-Preview funktional (`mio_termine`, `maes_videos`,
  `maes_merkblaetter`, `maes_aktuelles`).
- postMessage-Resize beidseitig (Backend + Frontend) mit Defense-in-Depth
  abgesichert.
- Keine Critical-/Major-Befunde.
- 4 NICE-TO-HAVE-Caveats sind dokumentiert, nicht release-blockierend.

---

## Quellen

- `docs/architecture/22-TECH-DEBT-TRIAGE-v0154.md` (Discovery)
- `.specialist-v0154-F12-handover.md` (Spec-Handover)
- `includes/class-dhps-admin-rest.php` (REST-Backend, 820 LOC)
- `includes/class-dhps-preview-renderer.php` (Preview-Renderer, 440 LOC)
- `admin/js/dhps-admin-react.js` (Admin-React, 1170+ LOC)
- `docs/architecture/21-LIVE-PREVIEW-PLAN-v0153.md` (Live-Preview-Plan)
- `docs/architecture/14-CSP-COMPATIBILITY.md` (CSP-Doku)
- `docs/project/34-QA-REPORT-v0153.md` (Vorgaenger-QA-Report)
- `docs/project/36-CHANGELOG-v0153.md` (Vorgaenger-Changelog)
