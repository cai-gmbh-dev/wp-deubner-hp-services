# Security-Audit v0.15.0 - Backend-Admin-Dashboard

## Stand: 2026-05-24
## Audit-Scope: REST-API + React-Frontend des neuen Admin-Dashboards
## Auditor: Security-Specialist (parallel zur QA-Spec)
## Methode: Source-Review (statisch), Schema-Abgleich, Threat-Modeling

---

## Executive Summary

| Kategorie | Befund |
|-----------|--------|
| Gesamt-Verdict | GO-WITH-FIXES (alle 4 Fixes Low/Info, kein Blocker) |
| Critical-Findings | 0 |
| High-Findings | 0 |
| Medium-Findings | 1 (Localize-Schluesselnamen) |
| Low-Findings | 3 (i18n-Domain, Race-Conditions, OTA-Length-Edge-Case) |
| Info-Findings | 4 |
| OTA-Maskierung sicher | Ja (mit Edge-Case-Anmerkung, siehe Sektion 4) |
| Rate-Limiting korrekt | Ja (per User-ID, mit dokumentierter Race-Toleranz) |
| SSRF-Schutz | Ja (kein freies URL-Inject, Registry-driven) |
| Permissions | Ja (manage_options auf jedem Endpoint) |
| Nonces | Ja (WP-REST-native, X-WP-Nonce) |
| SQL-Injection | Ja, $wpdb->prepare auf allen Queries |
| XSS-Risiko (React) | Niedrig (kein dangerouslySetInnerHTML, kein eval) |

**Empfehlung:** v0.15.0 freigeben. Der einzige Medium-Befund (Sektion 8) ist ein
Schluesselnamen-Mismatch zwischen `wp_localize_script` und JS-Erwartung
(`restBase` vs. `restUrl`, `nonce` vs. `restNonce`). Das ist primaer ein
Funktions-Bug, kein Security-Risiko (das React-Bundle laeuft auch ohne Localize,
weil `apiFetch` mit `wp-api-fetch`-Handle die Nonce automatisch setzt). Sollte
trotzdem in v0.15.1 behoben werden.

---

## Section 1: REST-API Permissions

**Files:** `includes/class-dhps-admin-rest.php` Zeilen 150-229, 238-240.

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| Jeder Endpoint hat `permission_callback` | OK | Alle 5 `register_rest_route()`-Calls setzen `array( $this, 'check_permissions' )` (Z. 159, 171, 189, 207, 219). |
| `check_permissions()` prueft `current_user_can( 'manage_options' )` | OK | Z. 238-240. |
| Unauthenticated Requests werden vor dem Callback geblockt | OK | WP-REST-Framework ruft `permission_callback` VOR dem Callback. Bei `false` -> HTTP 401 `rest_forbidden`. |
| Nonce-Check ist WP-REST-nativ | OK | `apiFetch.createNonceMiddleware()` setzt `X-WP-Nonce`-Header; WP-REST validiert gegen `wp_rest`-Action. Eigener Nonce-Code waere ein Anti-Pattern. |
| POST-Endpoints haben CSRF-Schutz | OK | Identisch zum Nonce-Mechanismus oben. WP-REST verlangt fuer Cookie-Auth ZWINGEND einen gueltigen `X-WP-Nonce`. |
| Capability-Bypass bei `validate_callback`-Fehler moeglich? | NEIN | `permission_callback` laeuft VOR `validate_callback`. Reihenfolge: permission -> validate -> sanitize -> callback. |

### Findings

- **INFO-1.1**: `check_permissions()` ist statisch-aequivalent (ohne Request-Kontext), das ist OK fuer rein-rollenbasierte Endpoints. Keine pro-Resource-ACL noetig (alle Endpoints sind Admin-only).

### Verdict Section 1: PASS

---

## Section 2: Service-Whitelist + Input-Sanitization

**Files:** `includes/class-dhps-admin-rest.php` Z. 52, 167, 185, 251-291, 317-326, 346-355.

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| Whitelist enthaelt nur die 9 erwarteten Services | OK | `ALLOWED_SERVICES = array( 'mio', 'lxmio', 'mmb', 'mil', 'tp', 'tpt', 'tc', 'maes', 'lp' )` (Z. 52). |
| `sanitize_key` wird auf service-Param angewendet | OK | `sanitize_callback => 'sanitize_key'` in `register_rest_route` (Z. 175, 193, 223). |
| Defense-in-Depth: nochmals `sanitize_key` im Handler | OK | `sanitize_key( (string) $request->get_param( 'service' ) )` (Z. 318, 347). |
| Laengen-Limit aktiv | OK | `SERVICE_PARAM_MAX_LENGTH = 16` (Z. 76), gepruft in `validate_service_param` (Z. 259-265). |
| `validate_callback` in `register_rest_route` | OK | Z. 176, 194, 224. |
| Regex `(?P<service>[a-z]+)` schliesst Sonderzeichen aus | OK | Route-Regex erlaubt nur a-z (kein Underscore noetig - die 9 Slugs sind alle pure a-z). |
| Whitelist-Bypass via Case-Manipulation moeglich? | NEIN | `sanitize_key` lowercased. Whitelist-Eintraege sind lowercase. |
| Optional-Param bei `/cache/flush` korrekt validiert | OK | `validate_service_param_optional` akzeptiert null/leeren String, sonst delegate an Strict-Validator (Z. 286-291). |

### Findings

- **INFO-2.1**: Triple-Sanitization (Route-Regex + `sanitize_callback` + Handler-`sanitize_key`) ist Defense-in-Depth und korrekt. Keine Doublure als Performance-Issue, weil `sanitize_key` ein O(n)-String-Op ist.
- **INFO-2.2**: Maximalalter aller Slugs = 5 Zeichen ("lxmio"). Max-Length 16 ist generoes - das ist beabsichtigt, weil bei zukuenftigen Services kein Refactor noetig wird.

### Verdict Section 2: PASS

---

## Section 3: Rate-Limiting

**Files:** `includes/class-dhps-admin-rest.php` Z. 60-68, 358-364, 471-477, 509-532.

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| POST `/services/{s}/test`: 30/min/User | OK | `RATE_LIMIT_PER_MINUTE = 30`, `check_rate_limit( 'test', 30 )` (Z. 358). |
| POST `/cache/flush`: 6/min/User | OK | `FLUSH_LIMIT_PER_MINUTE = 6`, `check_rate_limit( 'flush', 6 )` (Z. 471). |
| Pro User-ID (nicht IP) | OK | `$user_id = get_current_user_id()` (Z. 510). User-basiert, weil pre-Auth via `manage_options` ohnehin User noetig. |
| Transient-Key-Schema isolierbar | OK | `dhps_admin_rate_{bucket}_{user_id}` (Z. 516). Bucket trennt Test/Flush. User-ID trennt User. |
| Was wenn User logged out aber valid REST-Nonce? | KANN NICHT PASSIEREN | `permission_callback` blockt vorher. `get_current_user_id() <= 0` ist nur Theorie -> dann gibt rate_limit `true` zurueck und der `permission_callback` haette ohnehin geblockt. |
| HTTP 429 mit `code = rate_limit_exceeded` | OK | `WP_Error('rate_limit_exceeded', '...', array('status' => 429))` (Z. 360-363, 473-476). |
| Counter erhoeht TTL? | NEIN | `set_transient( $key, $count+1, MINUTE_IN_SECONDS )` setzt TTL neu - HIER IST ABER eine Sliding-Window-Eigenschaft. Siehe Finding LOW-3.1. |

### Findings

- **LOW-3.1 (Sliding-Window-Drift)**: Das aktuelle Schema setzt TTL bei jedem `set_transient` auf 60s zurueck. Das ist KEIN echtes Sliding-Window und auch kein Fixed-Window. Konkret: Wenn ein User durchgehend 30 Requests/60s macht, "rollt" das Fenster, wenn er weniger als 30/min macht. Aber die TTL-Erneuerung passiert nur SO LANGE der Counter unter dem Limit liegt - sobald das Limit erreicht ist, wird `set_transient` nicht mehr gerufen (early-return Z. 525-527), die TTL laeuft aus, Counter resettet. Das ist akzeptabel und vergleichbar mit `DHPS_MMB_AJAX_Handler`-Pattern. **Empfehlung: dokumentieren ("Fenster ist ~60s und wird beim Erreichen des Limits ausgeritten").**
- **LOW-3.2 (Race-Condition bei Concurrent-Requests)**: Zwei parallele Test-Requests koennen beide `$count = (int) $current` lesen, beide `+1` schreiben - effektiv geht eine Erhoehung verloren. Bei 30/min ist das tolerabel (max ~1-2 Extra-Requests/min). Auskommentiert "akzeptiert" in der F1-Handover (Z. 499-501 Doc-Block). **Empfehlung: belassen, dokumentiert.**
- **INFO-3.3**: 6/min Flush ist sehr konservativ - bewusst, weil destruktiv. Korrekt.

### Verdict Section 3: PASS (mit dokumentierten Trade-offs)

---

## Section 4: OTA-Preview-Maskierung (KRITISCH)

**Files:** `includes/class-dhps-health-collector.php` Z. 197-220, 110-127.

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| `get_ota_preview()` returns nur erste 6 Zeichen + "..." | OK (mit Edge-Case) | `return substr( $value, 0, 6 ) . '...'` (Z. 219). |
| Edge-Case: OTA < 6 Zeichen | KORREKT BEHANDELT, ABER EXPOSED | Z. 216-218: `if ( strlen( $value ) <= 6 ) { return $value . '...'; }` - bei OTA "ABC" wird "ABC..." zurueckgegeben (vollstaendiges Geheimnis!). |
| Andere Endpoints leak'en OTA komplett? | NEIN | `handle_service_test` greift `$ota` aus get_option (Z. 382), uebergibt es als Param an `fetch_content` - die Response-Daten enthalten KEINEN OTA. Body wird via `strlen($html)` nur fuer Bytes-Count benutzt. |
| Request-Logging des Test-Endpoints leakt OTA? | NEIN | Es wird nichts geloggt. Der OTA geht via Query-Param in den `wp_remote_get`-Call (in `DHPS_Legacy_API`), wird also via HTTPS-Body verschluesselt. |
| `collect_for()` exposed OTA-Full irgendwo? | NEIN | Z. 116-127: Returned `ota_set`, `ota_preview`, `ota_key` (= wp_option-Schluesselname, kein Wert). Niemals den Full-Wert. |
| `get_ota_option_key` korrekt fuer alle 9 Services | OK | Z. 235-246. TPT teilt korrekt `dhps_ota_tp` mit TP. |

### Findings

- **LOW-4.1 (OTA-Length-Edge-Case)**: `if ( strlen( $value ) <= 6 ) { return $value . '...'; }` exposed den vollstaendigen OTA-Wert wenn er <= 6 Zeichen hat. In der Praxis sind Deubner-OTAs 16+ Zeichen lang ("OTA-2023184382" = 14 Zeichen, "OTA-2024186296" = 14 Zeichen). Damit ist das ein theoretischer Edge-Case. Aber: ein OTA "12345" wuerde als "12345..." preisgegeben. **Empfehlung (Low-Prio, kann in v0.15.1)**: Bei `<= 6` Zeichen `"***"` oder `"OTA-***"` zurueckgeben, NICHT den Wert. Aktuelle Implementierung war Discovery-konform, aber sicherheitstechnisch suboptimal.
- **INFO-4.2**: `ota_key` (z.B. "dhps_ota_mio") als Bestandteil der Response ist OK - es ist nur der Options-Name, kein Geheimnis. Hilft dem Frontend dem User zu erklaeren WO der OTA gespeichert ist.
- **INFO-4.3**: Die Maskierung ist konsistent mit der Spec ("erste 6 Zeichen + ..."). Audit-Trail-Schutz funktioniert fuer ueblich-grosse OTAs.

### Verdict Section 4: PASS (mit Empfehlung fuer Edge-Case-Fix in v0.15.1)

---

## Section 5: SSRF / API-Test-Endpoint

**Files:** `includes/class-dhps-admin-rest.php` Z. 346-439, `includes/class-dhps-health-collector.php` Z. 315-338, `includes/class-dhps-legacy-api.php` Z. 89-92, 140-143.

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| URL kommt aus Service-Registry (kein User-Input) | OK | Z. 366-377: `DHPS_Service_Registry::get_service($service)['endpoint']` - reiner Registry-Lookup. |
| Endpoint ist relative Pfad, wird mit `DEUBNER_HP_SERVICES_API_BASE` zusammengesetzt | OK | Konstante = `https://www.deubner-online.de/` (Plugin-Main Z. 53). Kein User-modifizierbar. |
| Freies URL-Inject ueber Service-Param ausgeschlossen | OK | Service-Param ist Whitelist-validiert. Service-Registry ist statisch (Code-defined, nicht via Option). |
| HTTP-Timeout gesetzt | OK | `DHPS_Legacy_API`-Konstruktor: `$timeout = 30` (Z. 54), HEAD-Probe: `5` Sekunden (Z. 141). |
| HTTP-Methode beschraenkt | OK | `fetch_content` -> `wp_remote_get` (GET ohne Body); `probe_availability` -> `wp_remote_head` (HEAD ohne Body). Kein POST gegen externe URL. |
| SSL-Verify aktiv | OK | `sslverify => true` in beiden Calls. |
| Redirect-Follow limitiert | OK | Default in WP `wp_remote_get` = 5; `probe_availability` setzt `redirection => 3` (Z. 328). Kein unbounded Follow. |
| Could DNS-Rebinding to internal IPs sneak past base URL? | NEIN (zumindest nicht via dieses Plugin) | Base URL ist hardcoded auf `deubner-online.de`. wp_remote_get nutzt WP-HTTP, das selbst kein SSRF-Filtering hat - aber Deubner-Verlag-DNS ist authoritativ und liefert nur Public-IPs. Akzeptables Trust-Model fuer Admin-only Endpoint. |

### Findings

- **INFO-5.1**: Test-Endpoint nutzt `cache_ttl=3600` (Z. 415) - das heisst, ein Test-Request kann auch einen Cache-Hit erzeugen, anstatt einen Live-Request abzusetzen. Das schuetzt die API-Quota, ist aber ein semantisches Issue (User koennte irritiert sein wenn "Test" nur den Cache liest). **Cache-Hit-Detektion vor `fetch_content` (Z. 410-411)** loest das UI-seitig - das Response-Feld `cache_hit: true` informiert den User korrekt.
- **INFO-5.2**: Bei `cache_hit=true` wird `http_code=200` zurueckgegeben (Z. 426), obwohl kein realer HTTP-Call passierte. Das ist semantisch unsauber, aber kein Sicherheits-Issue. Dokumentiert im Code-Kommentar (Z. 424-425).

### Verdict Section 5: PASS

---

## Section 6: Cache-Stats DB-Queries

**Files:** `includes/class-dhps-cache-stats.php` Z. 54-132, 147-168.

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| `$wpdb->prepare()` auf LIKE-Patterns | OK | Z. 68-76 (collect), Z. 158-165 (flush). Beide nutzen `prepare` mit `%s`-Placeholder. |
| Direkter Eingang von User-Input in SQL | NEIN | Patterns sind Konstanten (`'_transient_dhps_%'`). Kein User-Input fliesst in die Query. |
| Performance bei sehr grossen Sites | RISIKO BEI EXTREMER GROESSE | Bei 100 MB+ option_value-Total kann `LENGTH(option_value)` ueber alle Rows langsam werden. Bei der aktuellen Plugin-Nutzung (geschaetzte 27-100 Transients, max 2 MB) ist das unkritisch. **Empfehlung (Future-Proof, INFO-6.1)**: LIMIT-Clause + Pagination bei Grossen Sites - nicht Blocker fuer v0.15.0. |
| Flush-DELETE kann Tabelle locken | THEORETISCH JA, PRAKTISCH UNCRITICAL | InnoDB Row-Level-Lock + Index `option_name`. Bei ~100 Rows < 100 ms. Concurrent Reads sind blockiert fuer wenige ms - akzeptabel. |
| Race-Condition bei concurrent Flushes | MITIGATED durch Rate-Limit | 6/min/User + manage_options begrenzt Concurrent-Calls effektiv auf wenige. Doppelte DELETEs sind idempotent (zweite findet keine Rows mehr). |
| SQL-Injection moeglich? | NEIN | Komplette Queries sind preparierte Statements mit konstanten Patterns. |

### Findings

- **INFO-6.1**: Bei pathologisch grossen Sites (100k+ Plugin-Transients) waere LIMIT + Pagination sinnvoll. Out-of-Scope fuer v0.15.0.
- **INFO-6.2**: `option_value` wird komplett geladen (statt nur `LENGTH(option_value)`) - das `option_value`-Feld ist im Code aber NICHT verwendet ausser fuer Timeout-Rows (`$expires_at = (int) $row->option_value`). Bei normaler Plugin-Groesse vernachlaessigbar. Mikro-Optimierung: nur fuer Timeout-Rows option_value laden via UNION-Query.
- **INFO-6.3**: `oldest_transient_age_sec`-Berechnung basiert auf Default-TTL=3600 (hardcoded Z. 120). Wenn DHPS_Cache mal mit anderer TTL gerufen wird, wird das Alter falsch. Dokumentierte Approximation, OK.

### Verdict Section 6: PASS

---

## Section 7: React-JS Security

**File:** `admin/js/dhps-admin-react.js`.

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| Kein `eval()` im Code | OK | Grep-frei. |
| Kein `innerHTML` mit User-Input | OK MIT AUSNAHME | Z. 681-683 + Z. 718-721 setzen `innerHTML` auf STATISCHE Error-Strings (Fallback-Path) - das ist hartkodiertes HTML, kein User-Input. Sicher. |
| `dangerouslySetInnerHTML` verwendet | NEIN | Grep-frei. |
| `apiFetch` nutzt WP-Standard-Nonce-Middleware | OK | Z. 689-691: `wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( window.dhpsAdminConfig.restNonce ) )` - aber Nullable: laeuft nur wenn `dhpsAdminConfig` gesetzt. `wp-api-fetch` setzt Nonce ohnehin automatisch via `_wpnonce`-Query-Param. **Doppelung ist defensiv, korrekt.** |
| Confirm-Dialog vor Flush | OK | Z. 526: `window.confirm(...)`. UX-Schutz, kein Security-Layer (Backend muss/tut auch ohne pruefen). |
| `localStorage`-Verwendung | NEIN | Grep-frei. Kein Geheimnis im Browser persistiert. |
| `sessionStorage`-Verwendung | NEIN | Grep-frei. |
| Werte aus REST-Response sind React-managed (kein direct DOM-Injection) | OK | Alle `service.name`, `service.endpoint`, `testResult.bytes` etc. werden als React-Children gerendert -> React HTML-encoded automatisch. |
| URL-Encoding fuer Path-Param | OK | Z. 224: `encodeURIComponent( slug )`. Defense-in-Depth (Backend whitelistet, aber Frontend tut sein Teil). |
| XSS via service.endpoint im title-Attribut | OK | Z. 296: `h( 'code', { title: service.endpoint }, ... )` - React HTML-encodet Attribute. |

### Findings

- **INFO-7.1**: Das hardcoded `innerHTML` in den Error-Fallbacks (Z. 681-683, 718-721) waere besser via `textContent` + DOM-Createelement-Pattern. Praktisch ist beides sicher, weil die Strings nicht aus Variablen kommen. Akzeptabel.
- **INFO-7.2**: `Math.random()` als Key-Fallback in Z. 463 ist UX-suboptimal (Re-Renders) aber kein Security-Issue.

### Verdict Section 7: PASS

---

## Section 8: wp_localize_script Bridge

**File:** `Deubner_HP_Services.php` Z. 825-833.

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| Nur nicht-sensible Daten exposed | OK | `restBase` (URL), `nonce` (User-spezifisch), `i18nDomain` (String). Kein OTA, kein DB-Wert, keine User-Daten. |
| Nonce regeneriert pro Page-Load | OK | `wp_create_nonce('wp_rest')` ist tied an aktuellen User + Action. WP-Nonce-Lifetime 12-24h, Generierung pro Render. Korrekt. |
| Admin-spezifische Daten an Nicht-Admins | NEIN | Z. 803-810: Hook-Gate `toplevel_page_dhps_dashboard` + Capability-Gate `manage_options`. Nicht-Admins bekommen das Bundle gar nicht. |
| Nonce-Action ist `wp_rest` (WP-Standard) | OK | Z. 831. Korrekt fuer apiFetch-Middleware. |
| Korrekte URL-Validierung | OK | `esc_url_raw( rest_url( 'dhps/v1/' ) )` (Z. 829). |

### Findings

- **MEDIUM-8.1 (Schluesselnamen-Mismatch)**: Plugin-Main exposed Bridge unter folgenden Keys:
  - PHP: `restBase`, `nonce`, `i18nDomain`
  - JS-Erwartung: `restUrl`, `restNonce` (Z. 689, 692 in dhps-admin-react.js)

  Konsequenz: `window.dhpsAdminConfig.restNonce` ist UNDEFINED, `window.dhpsAdminConfig.restUrl` ist UNDEFINED. Die `if ( ... && window.dhpsAdminConfig.restNonce ... )`-Checks in Z. 689-694 sind dann `false`, die NonceMiddleware wird NICHT explizit installiert.
  
  **Warum kein Security-Blocker:** `wp-api-fetch` (WP-Handle, als Dependency enqueued) setzt die Nonce-Middleware bereits automatisch beim Load auf, sofern eine globale `wpApiSettings.nonce` existiert (von WP gesetzt wenn `wp-api`-Handle geladen ist) ODER der `_wpnonce`-Query-Param via Cookie-Auth funktioniert. In der Praxis funktioniert das Dashboard, ohne dass die explizite Middleware greift.
  
  **Empfehlung (v0.15.1):** Schluesselnamen synchronisieren. Entweder JS auf `restBase`/`nonce` umstellen oder PHP auf `restUrl`/`restNonce` anpassen. F2-Handover (Sektion 2) hat `restNonce` als Konvention vorgeschlagen.
  
  **Severity:** Medium - funktional-bug, kein direktes Security-Loch, weil WP-eigene Mechanik die Nonce-Pruefung trotzdem erzwingt (sonst HTTP 403 `rest_cookie_invalid_nonce`).

- **LOW-8.2 (i18nDomain-Wert)**: `i18nDomain => 'wp-deubner-hp-services'` (Z. 831) entspricht NICHT der Plugin-Header-`Text Domain: deubner_hp_services` (Z. 12 Plugin-Main). Das React-Bundle nutzt direkt `'deubner_hp_services'` als Textdomain-String (z.B. Z. 231 in JS). Konsistenz-Bug, kein Security-Issue.

### Verdict Section 8: PASS (mit MEDIUM-Fix-Empfehlung)

---

## Section 9: Information Disclosure

**Files:** alle 3 PHP-Klassen + React.

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| REST-Error-Responses leaken Stack-Traces | NEIN | Alle Errors via `new WP_Error(code, msg, ['status' => N])` - WP serialisiert das zu sauberem JSON, kein Trace. |
| HTTP-Status-Codes korrekt | OK | 200 (Success), 400 (Validation), 429 (Rate-Limit), 500 (interner Endpoint-Fehler). 403 wird implizit von permission_callback gesetzt (`rest_forbidden`). 401 fuer unauth ebenso. |
| Test-Response leakt Deubner-API-Internals | NEIN | Response enthaelt nur Aggregate: `bytes`, `response_time_ms`, `http_code`, `cache_hit`. KEIN Body, keine Headers, keine OTA. |
| Cache-Stats leaken Cache-Keys (potentiell sensibel) | NEIN | Response enthaelt nur Aggregate (`total_transients`, `total_size_bytes`, etc.). Keine Key-Liste, kein Value-Excerpt. |
| `flush`-Response leakt geloeschte Items | NEIN | Nur `deleted_rows` als Integer. |
| Health-Response leakt OTA-Full | NEIN | Nur Preview (siehe Sektion 4). |
| `api_url` in Health-Response leakt Geheimnis | NEIN | API-URL ist oeffentlich bekannte Deubner-Endpoint-URL. Kein Geheimnis. |
| Verbose-PHP-Errors koennten in JSON-Body landen | NEIN | WP-REST verwendet eigenen Output-Buffer; PHP-Notices haetten Display-Errors aus. Bei `WP_DEBUG_DISPLAY=false` ohnehin unsichtbar. |

### Findings

- **INFO-9.1**: `available_cached_at` ist ein Unix-Timestamp - das ist eine harmlose Info-Disclosure (wann zuletzt geprueft wurde). OK fuer Admin-UI.

### Verdict Section 9: PASS

---

## Section 10: Trust-Decisions (akzeptiert)

Die folgenden Trade-offs wurden im Discovery-Plan (`docs/architecture/18-ADMIN-DASHBOARD-PLAN-v0150.md`) und Handover-Dokumenten begruendet und werden hier als bewusste Architektur-Entscheidungen verifiziert.

| # | Trust-Decision | Begruendung | Verifikation |
|---|----------------|-------------|--------------|
| 1 | React via `wp.element` ohne Build-Pipeline (Bundle nicht minifiziert, kein Tree-Shaking) | Plan-Sektion 2.4 Hard Requirement | OK fuer Admin-Bundle (kleiner Footprint). |
| 2 | Cache-Stats global statt pro Service (Discovery v0.15.1) | Cache-Key-Schema-BC-Break vermeiden | OK - dokumentiert in Response (`service_filter_applied: false`). |
| 3 | OTA-Preview-Maskierung 6 Zeichen (nicht vollstaendig versteckt) | Admin-Debug-UI braucht eindeutige Vorschau | OK fuer admin-only Endpoint. Edge-Case bei sehr kurzen OTAs siehe LOW-4.1. |
| 4 | REST-Bundle nur fuer manage_options-User | Dashboard ist Admin-Only | OK - doppelt geschuetzt (Hook-Gate + Capability-Gate + REST permission_callback). |
| 5 | Rate-Limit-Race-Condition akzeptiert (max ~1-2 Extra-Requests/min) | Analog MMB_AJAX_Handler | OK - in F1-Handover (Z. 499-501) dokumentiert. |
| 6 | Test-Endpoint nutzt `cache_ttl=3600` (kann Cache-Hits zurueckliefern) | Quota-Schutz fuer Deubner-API | OK - `cache_hit`-Flag informiert User transparent. |
| 7 | HEAD-Probe auf Base-URL (nicht Endpoint-URL) | Verschwendet keine Lizenz | OK - korrekte Strategie. |
| 8 | `auth_type`-Default `'ota'` (falls Registry-Feld fehlt) | Backward-Compat | OK - alle 9 Services haben explizites `auth_type`, Fallback ist nur Defensive. |
| 9 | OTA fliesst als Query-Param in API-Request (Z. 401-407) | API-Vertrag Deubner | OK - HTTPS verschluesselt Query-Param. Kein Logging im Plugin. WP-HTTP loggt nicht standardmaessig. |
| 10 | Dashboard-Page nutzt `wp.element.render` (deprecated in React 18) mit `createRoot`-Fallback | WP 6.0 Kompatibilitaet | OK - Z. 704-711. |

---

## Final Verdict

### GO-WITH-FIXES

Das v0.15.0-Admin-Dashboard ist sicherheitstechnisch produktionsreif. Keine
kritischen oder hohen Befunde. Die Implementation folgt allen Vorgaben des
Discovery-Plans und des F1/F2-Handovers konsequent.

### Begruendung

- 0 Critical / 0 High Findings.
- Permissions: WP-REST-native, korrekt via `permission_callback` + `manage_options`.
- Nonces: WP-REST-native, X-WP-Nonce ueber apiFetch.
- Rate-Limits: konservativ (30/min Test, 6/min Flush), pro User.
- OTA-Schutz: niemals Full-Wert exponiert (modulo Edge-Case LOW-4.1).
- SSRF: keine User-URLs, Registry-driven, sslverify, Timeout.
- SQL: 100% prepared statements, statische Patterns.
- React: kein eval, kein dangerouslySetInnerHTML, React-natives Auto-Escaping.

### Empfohlene Folge-Tickets (kein v0.15.0-Blocker)

1. **MEDIUM-8.1 (v0.15.1)**: Schluesselnamen `restBase/nonce` <-> `restUrl/restNonce` synchronisieren.
2. **LOW-4.1 (v0.15.1)**: `get_ota_preview()` bei OTA-Laenge <= 6 auf `"***"` statt Full-Wert + "..." setzen.
3. **LOW-8.2 (v0.15.1)**: i18nDomain in localize-Bridge auf `deubner_hp_services` korrigieren (oder ueberhaupt entfernen, da JS sie nicht liest).
4. **INFO-6.1 (v0.15.2)**: LIMIT + Pagination fuer Cache-Stats bei Sites mit > 1000 Plugin-Transients.

### Nicht behoben in dieser Iteration (Roadmap)

- Service-spezifischer Cache-Flush (BC-Break am Cache-Key-Schema) -> v0.15.1+
- Health-History / Trend-Tracking -> v0.16.x+
- WP-CLI-Integration -> v0.16.x+

---

## Quellen

- `includes/class-dhps-admin-rest.php` (533 LOC)
- `includes/class-dhps-health-collector.php` (353 LOC)
- `includes/class-dhps-cache-stats.php` (169 LOC)
- `admin/js/dhps-admin-react.js` (725 LOC)
- `Deubner_HP_Services.php` Z. 290-295, 801-837
- `admin/views/dashboard.php` Z. 187-191
- `includes/class-dhps-api-client.php` Z. 78-110 (fetch_content + cache-aside)
- `includes/class-dhps-legacy-api.php` Z. 89-91, 140-143 (HTTP-Calls + sslverify)
- `includes/class-dhps-service-registry.php` Z. 62-483 (Service-Definitionen)
- `.specialist-F1-handover-v0150.md` (Section 5 + 7)
- `.specialist-F2-handover.md` (Section 6 + 7)
- `docs/architecture/18-ADMIN-DASHBOARD-PLAN-v0150.md` (Sections 10 + 11)
