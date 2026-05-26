# Security-Audit v0.15.4 - Tech-Debt-Cleanup + Sub-Shortcodes + postMessage-Resize

Stand: 2026-05-26
Auditor: Security-Specialist
Scope: 8 Tickets aus 22-TECH-DEBT-TRIAGE-v0154 (Easy-Wins + F1/F2 Sub-Shortcodes + postMessage-Resize)
Schwester-Audit: docs/project/37-QA-REPORT-v0154.md (parallel)

---

## Executive Summary

v0.15.4 ist eine **Polish-Release** ohne neue Architektur. Die security-
relevanten Aenderungen sind klar abgegrenzt:

1. **REST-Route-Regex** `[a-z]+` -> `[a-z_]+` auf 3 Routes (health/test/preview),
   damit Sub-Shortcode-Slugs (mio_termine, maes_videos, maes_merkblaetter,
   maes_aktuelles) matchen.
2. **ALLOWED_SERVICES-Whitelist** um 4 Sub-Shortcodes erweitert (13 Eintraege total).
3. **SERVICE_PARAM_MAX_LENGTH** 16 -> 32 (`maes_merkblaetter` = 17 Zeichen).
4. **SUB_SHORTCODE_PARENTS-Map** (public const) im Renderer fuer Auth/Endpoint/JS-Lookup
   ueber den Parent-Service.
5. **postMessage-Resize**: Backend-injiziertes JS-Snippet (hartcodiert) + Frontend-
   useEffect-Listener (Type-Check + Bounds + Max-Cap 4000px).
6. **PREVIEW_SERVICES-Konstante** (Frontend, 13 Eintraege, DRY-Refactor).
7. **Atts-Whitelist** um `cache`-Boolean erweitert.

**Verdict: GO** (production-ready). 0 Critical, 0 High, 0 Medium, 1 Low.

Die REST-Route-Regex-Erweiterung ist durch die Whitelist-Defense-in-Depth
abgesichert. Das postMessage-Resize-Feature hat einen sauberen 3-Layer-
Defense im Frontend, das Worst-Case-Risiko ist auf "Iframe-Hoehe geaendert"
beschraenkt - kein XSS-, kein Daten-Leak-Vektor. Alle Aenderungen sind
additive Whitelist-Erweiterungen ohne neuen Attack-Surface.

---

## Audit-Sektionen

### Section 1: REST-Route-Regex-Erweiterung (Defense-in-Depth)

**Pruefling**: `includes/class-dhps-admin-rest.php` Zeilen 210, 229, 248.

Drei Path-Routes wurden auf `(?P<service>[a-z_]+)` erweitert:

| Route | Regex (v0.15.3) | Regex (v0.15.4) | Method |
|-------|-----------------|------------------|--------|
| /services/{service}/health  | `[a-z]+` | `[a-z_]+` | GET |
| /services/{service}/test    | `[a-z]+` | `[a-z_]+` | POST |
| /services/{service}/preview | `[a-z]+` | `[a-z_]+` | POST |

Route 4 (`/cache/flush`) hat `service` als optionalen Body-Parameter (kein
Path-Capture) - nicht betroffen.

#### 1.1 Defense-in-Depth-Layer

| Layer | Check | Pfad |
|-------|-------|------|
| 1 | Regex `[a-z_]+` matched Pfad-Segment | register_rest_route() |
| 2 | sanitize_key() normalisiert Input (Lowercase + Underscore + Digits) | args.service.sanitize_callback |
| 3 | validate_service_param() prueft Laenge <= 32 | Zeilen 322-328 |
| 4 | validate_service_param() prueft in_array gegen ALLOWED_SERVICES | Zeilen 330-336 |
| 5 | Handler re-checkt in_array (Belt-and-Suspenders) | Zeilen 565-571 (preview), analog test/health |
| 6 | Service-Registry-Lookup mit Parent-Resolution -> Null-Check | Zeilen 575-585 |

**Theoretischer Bypass-Test** (Input-Werte die das neue Regex matched):

| Input | Regex | Whitelist | Resultat |
|-------|-------|-----------|----------|
| `mio_termine` | MATCHED | in ALLOWED_SERVICES | 200 OK (gewuenscht) |
| `_test_` | MATCHED | NICHT in ALLOWED_SERVICES | 400 invalid_service |
| `____` | MATCHED | NICHT in ALLOWED_SERVICES | 400 invalid_service |
| `__construct` | MATCHED | NICHT in ALLOWED_SERVICES | 400 invalid_service |
| `_` | MATCHED | NICHT in ALLOWED_SERVICES | 400 invalid_service |
| `aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa` (33 chars) | MATCHED | Laenge > 32 | 400 invalid_service |
| `MIO` (Uppercase) | NICHT MATCHED (Regex case-sensitive) | n/a | 404 rest_no_route |
| `mio-termine` (Hyphen) | NICHT MATCHED | n/a | 404 rest_no_route |
| `../etc/passwd` | NICHT MATCHED | n/a | 404 rest_no_route |

**Bewertung**: Die Whitelist (Layer 4 + 5) ist die effektive Sicherheitsbarriere.
Das Regex erfuellt nur eine Filter-Funktion fuer offensichtlich malformed Inputs
(reduziert WAF-Noise). Es gibt **keinen Pfad**, ueber den ein Service-Slug
die Whitelist umgeht.

#### 1.2 SERVICE_PARAM_MAX_LENGTH 32

`maes_merkblaetter` hat 17 Zeichen. Der alte Cap von 16 haette diesen Slug
fa-lschlich rejected. Neuer Cap 32 deckt den laengsten in ALLOWED_SERVICES
mit 100% Margin ab.

**Sanity-Check**: Laengster aktueller Slug = 17 (`maes_merkblaetter`).
32/17 = 88% Reserve - ausreichend gegen Slug-Bloat.

**Finding**: keines.

---

### Section 2: SUB_SHORTCODE_PARENTS-Map Security

**Pruefling**: `includes/class-dhps-preview-renderer.php` Zeilen 84-89.

```php
public const SUB_SHORTCODE_PARENTS = array(
    'mio_termine'       => 'mio',
    'maes_videos'       => 'maes',
    'maes_merkblaetter' => 'maes',
    'maes_aktuelles'    => 'maes',
);
```

#### 2.1 Sichtbarkeit + Immutabilitaet

| Aspekt | Status |
|--------|--------|
| `public const` (immutable) | OK - keine Runtime-Mutation moeglich |
| Lookup nur fuer 4 Sub-Shortcodes (keine Haupt-Service-Umleitung) | OK - 4 Eintraege exakt |
| Reverse-Direction nicht moeglich (nur sub -> parent, nie parent -> sub) | OK |

#### 2.2 Auth-Lookup-Isolation

Im REST-Handler (Zeilen 575-578):

```php
$sub_parents = DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS;
$lookup_slug = isset( $sub_parents[ $service ] ) ? $sub_parents[ $service ] : $service;
$config = DHPS_Service_Registry::get_service( $lookup_slug );
```

**Verifikation**: Wenn `$service = 'maes_videos'`:
- `$lookup_slug = 'maes'` (via Map).
- Registry liefert MAES-Config -> OTA-Option `dhps_maes_kdnr`.
- Keine Moeglichkeit, einen abweichenden Auth-Token-Slug zu erzwingen (Map ist hartcodiert).

Wenn `$service = 'maes'` (Haupt-Service):
- `$lookup_slug = 'maes'` (Pass-Through, kein Map-Eintrag).
- Identisches Verhalten wie v0.15.3.

**Sub-Shortcode kann keinen anderen Parent-OTA stehlen**: Map ist 1-zu-N
(N Subs zeigen auf 1 Parent), aber NIE crossover (z.B. `mio_termine` zeigt
nicht auf `tp`).

#### 2.3 JS-Asset-Lookup-Isolation

Im Renderer `build_html_document()` Zeilen 331-333:

```php
$js_lookup_slug = isset( self::SUB_SHORTCODE_PARENTS[ $service ] )
    ? self::SUB_SHORTCODE_PARENTS[ $service ]
    : $service;
```

`$js_lookup_slug` -> `SERVICE_JS_MAP[$js_lookup_slug]` (statische Map mit
ASCII-Slugs als Keys). Beide Maps sind hartcodiert, kein User-Input fliesst ein.

**Path-Traversal-Check**: `SERVICE_JS_MAP` enthaelt nur statische
String-Konstanten (`public/js/dhps-*.js`). Kein Pfad-Input vom User.

**Finding**: keines.

---

### Section 3: postMessage-Resize Sicherheits-Analyse (KRITISCH)

#### 3.1 Backend-Snippet (DHPS_Preview_Renderer)

**Pruefling**: `includes/class-dhps-preview-renderer.php` Zeilen 397-439
(`get_postmessage_resize_snippet()`).

Eigenschaften:
- **Nowdoc-HEREDOC** (`<<<'JS'`): KEINE Variablen-Interpolation moeglich.
  Selbst wenn der Renderer durch Bug einen User-Input bekaeme, kann er
  nicht in das Snippet hineingelangen.
- **Hartcodierte Konstanten**: `TYPE = 'dhps-preview-resize'`,
  `MAX_HEIGHT = 4000`.
- **Backend-side MAX_HEIGHT-Cap (4000)**: Selbst wenn iframe-Inhalte
  document.body.scrollHeight = 100000 messen, sendet das Snippet nur
  `height: 4000`.
- **dedupe via `lastH`**: Send-Spam wird vermieden, sendet nur bei
  Hoehen-Wechsel.
- **targetOrigin='*'**: Notwendig, weil iframe srcdoc-Origin
  `about:srcdoc` hat. Klassischer same-origin-Check ist hier nicht moeglich.

**Threat-Vektor**: Koennte ein DHPS-Template `</script>` injizieren und
das Snippet brechen? Antwort: NEIN - das Resize-Snippet wird HINTER
`$body` geschrieben. Selbst wenn der Body inkonsistente Tags hat, ist das
Snippet ein eigener `<script>`-Block. Im Worst-Case wird der Body-Parsing
broken (DOM-Auto-Repair), aber das Resize-Snippet bleibt isoliert.

#### 3.2 Frontend-Listener (LivePreviewIframe)

**Pruefling**: `admin/js/dhps-admin-react.js` Zeilen 859-913.

3-Layer-Defense:

| Layer | Check | Ablehnungsverhalten |
|-------|-------|----------------------|
| 1 | `event.data.type === 'dhps-preview-resize'` (Strict-Equality) | Funktion returnt frueh |
| 2 | `parseInt(event.data.height, 10)`, `isNaN(h) \|\| h < 1` | Funktion returnt frueh |
| 3 | `h > PREVIEW_IFRAME_MAX_HEIGHT (4000)` -> Cap auf 4000 | Hoehe wird auf 4000 reduziert |

**Origin-Check**: `event.origin === 'null'` fuer about:srcdoc-Quellen
(Browser-Standard, RFC 6454). Ein klassischer
`event.origin === window.location.origin`-Check waere immer falsy und
wuerde die Funktion brechen. Stattdessen wird der Origin-Check bewusst
weggelassen und die 3-Layer-Defense uebernimmt.

#### 3.3 Threat-Modell: Wer kann ein Resize-Event senden?

Eingehende `postMessage`-Events koennen nur aus Browser-Kontexten kommen
(Frames, Worker, gleicher Window). Mögliche Quellen:

| Quelle | Realistisch im Admin? | Mitigation |
|--------|------------------------|------------|
| Unser eigenes preview-iframe (srcdoc) | JA - genau das ist der Use-Case | Type+Bounds+Cap |
| Anderes Plugin-iframe im Admin (z.B. WordPress-Embeds) | Theoretisch | Type-Check filtert |
| Browser-Extension (Content-Script) | JA, aber benoetigt User-Install + User-Trust | Type+Bounds+Cap |
| Externer Tab via `window.opener.postMessage` | NEIN - Admin-Page hat keine `noopener`-Public-Links | n/a |
| XSS in Admin-Page (anderswo) | XSS waere unabhaengig groesseres Problem | n/a (out of scope) |

**Worst-Case-Analyse**: Eine boeswillige Quelle sendet
`{ type: 'dhps-preview-resize', height: 999999 }`:

- Type-Check passt.
- Numeric-Bounds-Check passt (999999 > 0, kein NaN).
- Max-Cap-Check reduziert auf 4000.
- iframe-Hoehe wird auf 4000px gesetzt.

Impact: **iframe-Hoehe geaendert**. Kein XSS, kein Daten-Leak, kein DoS
(maximaler Speicher-Footprint = iframe x 4000px).

#### 3.4 Akzeptable Trust-Decision?

**JA**. Begruendung:

1. Admin-only Endpoint (manage_options-Capability erforderlich).
2. iframe-Sandbox + manage_options ist die primaere Isolation.
3. Worst-Case "iframe waechst auf 4000px" ist UX-Detail, kein Sicherheitsvorfall.
4. Origin-Check ist technisch nicht moeglich (about:srcdoc -> origin='null').
5. Max-Cap garantiert begrenzten Layout-Impact.

**Finding L1 (Low, akzeptiert)**: targetOrigin='*' im Backend-Snippet.
Begruendung dokumentiert in Trust-Decision T8.

---

### Section 4: ALLOWED_SERVICES-Erweiterung

**Pruefling**: `includes/class-dhps-admin-rest.php` Zeilen 62-78.

13 Eintraege total (9 Haupt + 4 Sub):

| Slug | Existenz verifiziert? | Health-Lookup-Verhalten |
|------|------------------------|-------------------------|
| mio_termine | Lead-Smoke bestaetigt (`includes/class-dhps-steuertermine.php` add_shortcode) | Health-Collector unkown - returnt leere Felder |
| maes_videos | Lead-Smoke bestaetigt (`includes/class-dhps-maes-modules.php` add_shortcode) | wie oben |
| maes_merkblaetter | Lead-Smoke bestaetigt | wie oben |
| maes_aktuelles | Lead-Smoke bestaetigt | wie oben |

#### 4.1 Health-Lookup fuer Sub-Shortcodes

`DHPS_Health_Collector::collect_for($service)` wird auf jeden Sub-Shortcode-
Slug via `/services/{slug}/health` aufgerufen.

| Feld | Wert fuer Sub-Shortcode (z.B. maes_videos) | Sensitiver Inhalt? |
|------|---------------------------------------------|--------------------|
| `service`, `slug` | `'maes_videos'` (Echo) | nein |
| `label`, `name` | `''` (Registry kennt Slug nicht) | nein |
| `ota_set`, `ota_configured` | `false` (ota_option_key-Map kennt Slug nicht -> empty key) | nein |
| `ota_preview` | `''` (empty key -> '') | nein |
| `ota_key` | `''` | nein |
| `branding` | `''` | nein |
| `available`, `api_reachable` | `false` (probe braucht api_url, das ist '') | nein |
| `api_url`, `endpoint` | `''` | nein |

**Bewertung**: Health-Endpoint liefert fuer Sub-Shortcodes einen "neutralen
Null-Record". Keine Information-Leak, kein Crash. Dokumentierter Trade-off
im Handover (Section 7): Health-Collector wird in Folge-Iteration optional
um Parent-Resolution erweitert. Aktuell akzeptabel.

**Trust-Decision T9** (siehe Section 6): Auth-Lookup via Parent ist KEIN
Token-Leak. Der Sub-Shortcode `maes_videos` nutzt ohnehin MAES-kdnr (das
ist die designed Funktionalitaet, da maes_videos eine MAES-Pipeline-Wrapper
ist).

**Finding**: keines.

---

### Section 5: PREVIEW_SERVICES-Konstante (Frontend)

**Pruefling**: `admin/js/dhps-admin-react.js` Zeilen 658-672.

13 Eintraege (9 Haupt + 4 Sub) hartcodiert. Keine User-Input-Interpolation.

#### 5.1 Backend/Frontend-Redundanz

Backend `ALLOWED_SERVICES` (13) und Frontend `PREVIEW_SERVICES` (13) sind
redundant. Synchron-Halten ist Verantwortung des Lead-Workflows (kommentiert
in Zeile 655-656).

**Security-Implikation**: Selbst wenn die Listen drifteten (z.B. Frontend
hat mehr Eintraege), wuerde der Backend-Whitelist-Check (Section 1) jeden
unbekannten Slug rejecten. Defense-in-Depth greift.

#### 5.2 Frontend-Whitelist-Check VOR REST-Call?

Ticket 9 aus dem Triage-Doc verlangte einen expliziten
`PREVIEW_SERVICES.includes(service)`-Check VOR `apiFetch`. Im aktuellen
Code (Zeile 1043-1094) gibt es diesen expliziten Check **nicht**, jedoch
ist `service` aus dem `SelectControl` mit hardcoded `options=PREVIEW_SERVICES`
gebunden - de-facto whitelisted.

**Theoretischer Bypass**: Admin via React-DevTools setzt
`setService('boese-service')`. Folge:
- Frontend sendet `POST /dhps/v1/services/boese-service/preview`.
- Backend Regex `[a-z_]+` matched.
- Backend Whitelist rejected -> 400 invalid_service.
- React zeigt Error-Notice via catch-Block.

**Bewertung**: Backend-Defense greift, der Frontend-Check waere reine
Defense-in-Depth (kein Sicherheits-Mehrwert, nur UX-Latenz-Verbesserung).
Nicht release-blockend. Optional fuer v0.15.5+.

**Finding**: keines (L4 aus v0.15.3 verbleibt als optionales Hardening).

---

### Section 6: Trust-Decisions (kumulativ)

| # | Entscheidung | Begruendung | Status |
|---|--------------|-------------|--------|
| T1 | `html` wird NICHT durch wp_kses_post gefiltert | TC-Inline-JS + TP-Lazy-Video + MAES-Akkordeon brauchen `<script>`-Tags. iframe-Sandbox + manage_options-Isolation. | v0.15.3, unveraendert |
| T2 | OTA in iframe-HTML als JS-URL-Param sichtbar | Admin sieht OTA ohnehin via Options-Page. | v0.15.3, unveraendert |
| T3 | iframe-Sandbox `allow-same-origin + allow-scripts` ist W3C-schwach | HTML ist Plugin-eigen (keine User-HTML-Eingabe). Admin-only. | v0.15.3, unveraendert |
| T4 | atts_rejected als Map statt Array (Schema-Drift) | Mehr Information fuer Admins. Frontend liest defensiv. | v0.15.3, unveraendert |
| T5 | Sliding-Window-Drift + Race-Condition im Rate-Limit | Tolerabel fuer Admin-Tooling (max ~60 Requests in 10s). | v0.14.0/v0.15.0, unveraendert |
| **T6 (NEU)** | **REST-Route-Regex `[a-z_]+`** | Notwendig fuer Sub-Shortcodes mit Underscore. Whitelist-Defense-in-Depth in `validate_service_param()` + Handler-Re-Check + Registry-Lookup. | v0.15.4 |
| **T7 (NEU)** | **SERVICE_PARAM_MAX_LENGTH 16 -> 32** | `maes_merkblaetter` hat 17 Zeichen. 32 = 88% Reserve. | v0.15.4 |
| **T8 (NEU)** | **postMessage targetOrigin='*'** | iframe-srcdoc hat origin='null' - kein klassischer Origin-Check moeglich. Mitigation: 3-Layer-Defense im Listener (Type+Bounds+Max-Cap 4000px). Worst-Case = iframe-Hoehe geaendert. | v0.15.4 |
| **T9 (NEU)** | **Auth-Lookup via Parent fuer Sub-Shortcodes** | Sub-Shortcodes (maes_videos etc.) sind Wrapper um MAES-Pipeline und nutzen designed-by-architecture den MAES-kdnr. `mio_termine` nutzt MIO-OTA. Kein Token-Leak. Map ist hartcodiert (`public const`), 1-zu-N (kein Crossover). | v0.15.4 |

---

### Section 7: Information Disclosure

#### 7.1 Sub-Shortcode-Preview leakt OTA?

`do_shortcode('[maes_videos]')` -> MAES-Pipeline -> Service-JS-Bindings mit
OTA in JS-AJAX-URLs. **Identisches Verhalten wie v0.15.3** (Trust-Decision
T2). Admin-only-Endpoint, kein neuer Vektor.

OTA-Preview im Health-Collector wird seit v0.15.1 mit `'***'` maskiert
(< 6 Zeichen) bzw. `substr(0,6) . '...'`. Sub-Shortcode-Slugs sind ohnehin
nicht in der Health-Map -> leerer String, kein Leak.

#### 7.2 Atts-Whitelist erweitert um `cache` boolean

**Pruefling**: `includes/class-dhps-admin-rest.php` Zeilen 632, 646-657 +
`includes/class-dhps-preview-renderer.php` Zeilen 92-97, 197-207.

Validation:
- Backend: `filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)`
  -> normalisiert auf '0' oder '1' (oder rejected).
- Renderer: `in_array($cache_raw, ['0','1'], true)` (strict).
- Shortcode-String: `' cache="' . esc_attr($cache_raw) . '"'` -> nur '0' oder '1'.

**Bewertung**: Keine User-Input-Injection moeglich. `cache` ist ein
Boolean-Toggle ohne Sicherheits-Implikation (steuert nur ob der jeweilige
Shortcode-Handler den Cache nutzt).

**Finding**: keines.

#### 7.3 Sub-Shortcode-spezifische Atts (count, columns, einzelvideo, etc.)

Diese Atts werden in v0.15.4 NICHT exposed (out of scope, gehoeren zu
Ticket 7 / v0.15.5). Falls ein Admin sie dennoch im Body sendet, landen
sie via REST `foreach`-Loop in `$sanitized_atts` als generische String-Werte,
werden vom Renderer als `unknown att key` in `atts_rejected` markiert und
fliessen NICHT in den Shortcode-String. Kein Injection-Vektor.

**Finding**: keines.

---

### Section 8: ReDoS / Injection

#### 8.1 Regex-ReDoS

Das geaenderte Regex `[a-z_]+` ist linear (kein Backtracking-Pattern, keine
Nested-Quantifier). Maximum-Match-Komplexitaet ist O(n) wo n = Path-Segment-
Laenge (max 32 wegen SERVICE_PARAM_MAX_LENGTH-Cap). Kein ReDoS-Vektor.

#### 8.2 Neue preg_*-Patterns

Grep gegen geaenderte Files: **keine neuen preg_match/preg_replace/preg_split**
in v0.15.4. Alle Regex-Operationen sind bestehende (`sanitize_key`,
`sanitize_html_class`) - bereits in v0.15.3 auditiert.

#### 8.3 String-Konstruktion

- Shortcode-String aus `esc_attr`-quoteten Whitelist-Werten (`layout`,
  `class_clean`, `section_clean`, `cache_raw`).
- HTML-Document aus `esc_url`/`esc_html`/`esc_attr`-gewrappten Konstanten.
- postMessage-Snippet hartcodiert (Nowdoc-HEREDOC).

**Kein** Pfad, ueber den User-Input ungeprueft in eine Output-Konstruktion
fliesst.

**Finding**: keines.

---

## Findings-Uebersicht

| ID | Severity | Sektion | Befund | Status |
|----|----------|---------|--------|--------|
| L1 | Low (akzeptiert) | 3.4 | postMessage targetOrigin='*' (technisch notwendig wegen about:srcdoc-Origin='null', mitigiert via 3-Layer-Listener-Defense + Max-Cap 4000px) | Trust-Decision T8 |

**Critical: 0, High: 0, Medium: 0, Low: 1.**

Restliche v0.15.3-Tickets (M1 Atts-Keys-Cap, M2 Schema-Vertrag, L1 CSP,
L2 OTA-Hinweis, L3 Renderer-Error, L4 Frontend-Whitelist-Check) sind
**nicht im v0.15.4-Scope**. Tickets 2/3/8 aus dem Triage-Doc adressieren
Teilaspekte (invalid_format, 500-KB-Warning, CSP-Doku) - kein neuer
Security-Vektor.

---

## Verdict

**GO** - v0.15.4 ist production-ready aus Security-Sicht.

Begruendung:
- 0 Critical, 0 High, 0 Medium, 1 Low (akzeptiert).
- REST-Route-Regex-Erweiterung ist sicher: 6-Layer-Defense-in-Depth, kein
  Pfad umgeht die ALLOWED_SERVICES-Whitelist.
- postMessage-Resize ist sicher: 3-Layer-Frontend-Listener-Defense
  (Type-Check + Bounds + Max-Cap 4000px), Worst-Case ist iframe-Hoehe-
  Manipulation - kein XSS, kein Daten-Leak, kein DoS.
- SUB_SHORTCODE_PARENTS-Map ist hartcodiert (public const, immutable,
  1-zu-N ohne Crossover) - kein Auth-Token-Theft-Vektor.
- SERVICE_PARAM_MAX_LENGTH 32 deckt aktuellen Slug-Bestand mit 88% Reserve.
- ALLOWED_SERVICES-Erweiterung um 4 Sub-Shortcodes ist additive Whitelist.
- Health-Lookup fuer Sub-Shortcodes liefert leere/neutrale Felder - kein
  Information-Disclosure.
- Atts-Whitelist um `cache` boolean ist defensive (`FILTER_VALIDATE_BOOLEAN`
  + Renderer-Whitelist `['0','1']`).
- PREVIEW_SERVICES-Konstante (Frontend) ist redundant zur Backend-Whitelist
  - kein neuer Vektor, nur DRY-Refactor.

**Vor Release kein Blocker**, aber dokumentationshalber:
- T6-T9 in CHANGELOG-v0154 dokumentieren (Trust-Decision-Transparenz).
- Optional: Frontend `PREVIEW_SERVICES.includes(service)`-Check
  expliziter machen (L4 aus v0.15.3, weiterhin optional).

**Fuer v0.15.5+ vorgemerkt** (Backlog, nicht release-blockend):
- Health-Collector um Parent-Resolution erweitern (Sub-Shortcodes liefern
  aktuell leere Felder - akzeptabel, aber unschoen UX).
- Voller Atts-Editor (Ticket 7, eigene Iteration).
- L4 Frontend-Slug-Whitelisting expliziter.

---

## Quellen

- `includes/class-dhps-admin-rest.php` (Zeilen 53-78, 99-106, 210, 229, 248, 322-336, 565-585, 632-669)
- `includes/class-dhps-preview-renderer.php` (Zeilen 84-89, 92-97, 171-207, 331-333, 397-439)
- `includes/class-dhps-health-collector.php` (Zeilen 36, 110-140, 220-235, 248-262)
- `admin/js/dhps-admin-react.js` (Zeilen 658-697, 824-913, 1043-1094)
- `docs/architecture/22-TECH-DEBT-TRIAGE-v0154.md` (Discovery)
- `.specialist-v0154-F12-handover.md` (F1+F2-Handover)
- `docs/project/35-SECURITY-AUDIT-v0153.md` (Vorgaenger-Audit, T1-T5)
