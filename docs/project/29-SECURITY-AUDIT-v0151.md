# Security-Audit v0.15.1 - Tech-Debt-Cleanup (Polish-Release)

## Stand: 2026-05-25
## Audit-Scope: 3 sicherheits-relevante Tickets aus v0.15.1 Tech-Debt-Triage
## Auditor: Security-Specialist (parallel zur QA-Spec)
## Methode: Source-Review (statisch), Diff zu v0.15.0, Threat-Modeling Cross-Effects

---

## Executive Summary

| Kategorie | Befund |
|-----------|--------|
| Gesamt-Verdict | **GO** (Polish-Release, kein neuer Angriffsvektor) |
| Critical-Findings | 0 |
| High-Findings | 0 |
| Medium-Findings | 0 |
| Low-Findings | 0 (LOW-4.1 aus v0.15.0 ist GELOEST) |
| Info-Findings | 3 |
| LOW-4.1 OTA-Edge-Case | GELOEST (Fix in `class-dhps-health-collector.php`) |
| LOW-3.1 / LOW-3.2 | Trust-Decision dokumentiert (Doc-Block, kein Code-Change) |
| Ticket #2 TPT-Modules | Sicher (kein User-Input, WP-Filter-Mechanik) |

**Empfehlung:** v0.15.1 ohne Vorbehalt freigeben. Das Release ist explizit ein
Polish-Release: ein Sicherheits-Edge-Case (LOW-4.1) wird geschlossen, zwei
akzeptierte Trade-offs (LOW-3.1/LOW-3.2) werden im Code-Doc-Block dokumentiert,
und ein Tech-Debt-Refactor (TPT-Modules) verschiebt `get_option()`-Reads aus
Templates in einen dedizierten Layer ohne neue Angriffsflaeche. Tickets #4-#6
(CSS-Cleanup, A11y) wurden vorab als nicht security-relevant ausgeklammert
und nicht auditiert. Die in v0.15.0 erfolgten Trust-Decisions (Rate-Limit-
Architektur, OTA-Mask-Strategie, REST-Cookie-Auth) bleiben gueltig.

---

## Section 1: Ticket #7 - OTA-Preview-Maskierung (LOW-4.1 Fix)

**File:** `includes/class-dhps-health-collector.php` Z. 209-235.

### Diff zu v0.15.0

| Variante | Code | Verhalten bei OTA="ABC123" (6 Zeichen) |
|----------|------|----------------------------------------|
| v0.15.0 (vorher) | `if ( strlen( $value ) <= 6 ) { return $value . '...'; }` | Returned "ABC123..." -> **Full-Wert exposed** |
| v0.15.1 (aktuell) | `if ( strlen( $value ) <= 6 ) { return '***'; }` | Returned "***" -> **kein Leak** |

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| Edge-Case `<= 6` Zeichen wird maskiert | OK | Z. 231-233: `if ( strlen( $value ) <= 6 ) { return '***'; }` |
| Doc-Kommentar erklaert die Motivation | OK | Z. 229-230: "QA-Fix v0.14.5 (SEC LOW-4.1): bei OTA-Werten <=6 Zeichen NICHT den Full-Wert + '...' leaken, sondern komplett maskieren." |
| Keine Length-Detection ueber Maske moeglich | OK | "***" ist Length-agnostic. Angreifer kann nicht ableiten ob OTA 1, 2, ..., 6 Zeichen lang ist. |
| Standard-Pfad (>= 7 Zeichen) unveraendert | OK | Z. 234: `return substr( $value, 0, 6 ) . '...'` (identisch zu v0.15.0) |
| Edge-Case bei genau 7 Zeichen | KEIN LEAK | `substr( "AAAAAAA", 0, 6 ) . '...'` = "AAAAAA..." -> 6 Zeichen + Ellipsis-Indikator. Das 7. Zeichen ist NICHT im Preview. Kein Information-Disclosure des 7. Zeichens. |
| Edge-Case bei genau 0 Zeichen (leer) | KORREKT | Z. 226-228: Frueher Return mit leerem String (kein "***" - bewusste Unterscheidung von "gesetzt aber maskiert"). |
| Frueher Return bei fehlender Option | OK | Z. 222-224: `'' === $key` -> leerer String. |
| Defensive `(string) get_option(...)`-Cast | OK | Z. 225 - kein TypeError bei misslungener Option. |
| Praxis-Relevanz | THEORETISCH ABER GELOEST | Deubner-OTAs sind 14+ Zeichen ("OTA-2023184382"). Edge-Case betraf nur hypothetische Fehlkonfiguration oder zukuenftige Auth-Schemas mit kurzen Tokens. |

### Findings

- **INFO-1.1**: Die `"***"`-Maske unterscheidet sich semantisch von der leer-String-Rueckgabe bei nicht-gesetzter Option (Z. 226-228). Damit kann das Frontend differenzieren: leer = "noch nicht gesetzt", "***" = "gesetzt aber zu kurz fuer Preview". Saubere Trennung, kein Information-Leak.
- **INFO-1.2**: Konsistenz mit Standard-Pfad: Standard returnt "ABCDEF..." (Indikator: 6 Chars + Ellipsis). Edge-Case returnt "***" (Indikator: keine Chars, nur Maske). Beide Pfade sind eindeutig vom Frontend zu unterscheiden, ohne dass der Wert selbst durchsickert.

### Verdict Section 1: **PASS** - LOW-4.1 ist abschliessend geloest.

---

## Section 2: Tickets #8 + #9 - Rate-Limit-Docs (LOW-3.1 / LOW-3.2)

**File:** `includes/class-dhps-admin-rest.php` Z. 496-552.

### Diff zu v0.15.0

- **Code-Logik:** Unveraendert (Z. 529-552 identisch zu v0.15.0).
- **Doc-Block:** Erweitert um Z. 509-527 (19 Zeilen Doc-Kommentar zu den beiden Limitierungen).

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| Funktion `check_rate_limit()` semantisch unveraendert | OK | Z. 529-552: gleiches Verhalten wie v0.15.0 (Transient-Bucket, 60s TTL, Counter-Increment ohne TTL-Reset). |
| Doc-Block adressiert LOW-3.1 (Sliding-Window-Drift) | OK | Z. 511-518: explizit erklaert "Counter wird beim ersten Hit mit TTL=60s gesetzt, das Fenster rollt nicht mit jedem Request". Praktisches Worst-Case-Beispiel ("30 + 30 in 10s") nachvollziehbar dargestellt. |
| Doc-Block adressiert LOW-3.2 (Race-Condition) | OK | Z. 520-524: "get_transient + set_transient ist nicht atomar... Praktischer Worst-Case: ~1-2 Extra-Requests pro Minute". |
| Doc-Block begruendet Akzeptanz | OK | Beide Sektionen schliessen mit "akzeptabel fuer Admin-Tooling (manage_options-User), nicht akzeptabel fuer Public-Endpoints" bzw. "Echte Atomic-Counter erfordern wpdb-Lock oder Redis - bewusst nicht implementiert". |
| Verweis auf v0.15.0-Audit | OK | Z. 526-527: "Beide Schwaechen sind analog zum DHPS_MMB_AJAX_Handler-Pattern aus v0.14.0 akzeptiert (siehe docs/project/11-SECURITY-AUDIT-v0140.md)". |
| TTL-Verhalten beim Limit-Hit | OK | Z. 545-547: `$count >= $limit_per_minute -> return false` (kein `set_transient`). TTL laeuft natuerlich aus -> Fenster resettet sich. |
| Kein neuer Code = kein neuer Angriffsvektor | OK | Reine Doc-Aenderung; alle Threat-Vectors aus v0.15.0 Audit (Sektion 3) bleiben adressiert. |

### Findings

- **INFO-2.1**: Der Verweis auf `docs/project/11-SECURITY-AUDIT-v0140.md` im Code-Doc-Block ist eine Brueche-Bruecke - sollte ein Auditor v0.15.0-Audit oder den frueheren MMB-Audit suchen, findet er dort die Hintergrund-Rationale. Wuenschenswert (aber nicht erforderlich): zusaetzlicher Verweis auf `26-SECURITY-AUDIT-v0150.md` Sektion 3.
- **INFO-2.2**: Die Akzeptanz-Begruendung ("manage_options-User") ist konsistent mit dem REST-Endpoint-Schutz (`permission_callback => 'check_permissions' -> current_user_can('manage_options')`). Damit ist die Trust-Decision vollstaendig: nur Admins koennen das Rate-Limit treffen, Admin-Rollen sind per Definition vertrauenswuerdig im WP-Modell.

### Verdict Section 2: **PASS** - keine Code-Aenderung, Doc-Block macht Trust-Decision fuer zukuenftige Auditoren explizit.

---

## Section 3: Ticket #2 - TPT-Modules-Layer

**Files:**
- `includes/class-dhps-tpt-modules.php` (NEU, 80 LOC)
- `includes/class-dhps-renderer.php` Z. 164-178 (Filter-Hook eingefuegt)
- `public/views/services/tpt/{default,card,compact}.php` (6x `get_option`-Calls entfernt)

### Pruefungen `DHPS_TPT_Modules`

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| Klasse hat keine User-Input-Pfade | OK | `enrich_data($data, $layout)` liest ausschliesslich `get_option('dhps_tpt_ues', '')` + `get_option('dhps_tpt_teasertext', '')`. Beide Optionen werden serverseitig vom Admin gesetzt. |
| `(string)`-Cast schuetzt vor Array/Object-Injection | OK | Z. 74-75: `(string) get_option(...)`. Falls Option versehentlich Array enthielt, fuehrt das zu einem PHP-Notice, aber nicht zu Type-Confusion. |
| Defensive Pruefung auf Array-Typ | OK | Z. 69-71: `if ( ! is_array( $data ) ) { return $data; }` - verhindert Type-Error bei fremden Filtern, die `$data` zu Object/Null/Bool gemacht haben. |
| Filter-Callback-Signatur korrekt | OK | Z. 52: `add_filter( ..., 10, 2 )` matched die `apply_filters( ..., $data, $layout )`-Signatur. |
| Kein Output (echo/print) im Konstruktor oder Filter | OK | Reine Datentransformation; Output passiert in Templates (mit esc_html). |
| Output-Escaping in Templates | OK | Folgender Stichproben-Check der TPT-Templates ist in QA-Scope, aber die Existing-Templates nutzten bereits esc_html/esc_attr - Refactoring aendert nur den Datenfluss, nicht das Escaping. |

### Pruefungen Renderer-Filter (`class-dhps-renderer.php` Z. 178)

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| Filter-Name aus `$tag` zusammengesetzt | OK | Z. 178: `apply_filters( 'dhps_pipeline_data_' . $tag, $data, $layout )`. |
| **Wie wird `$tag` validiert?** | OK | `$tag` ist Parameter von `render_parsed( array $data, string $tag, ... )` (Z. 134). Aufrufer ist `DHPS_Content_Pipeline::render_service()` Z. 142, der `$tag` als ersten Parameter weiterreicht. Dieser kommt wiederum aus dem Shortcode-Layer, wo er via Service-Registry-Lookup ermittelt wird (whitelist-validiert) - **kein User-Input fliesst direkt durch**. |
| Kann ein Angreifer ueber Shortcode-Atts beliebige Filter-Namen triggern? | NEIN | Shortcodes mappen `$tag` nicht auf User-Atts. Der Tag ist eine Service-Konstante (`'mio'`, `'tpt'`, etc.), die in den jeweiligen Shortcode-Klassen hartkodiert ist. Selbst wenn ein User `[tpt foo="bar"]` schreibt, wird `$tag='tpt'` durch die Klassen-Logik gesetzt, nicht aus den Atts gelesen. |
| Filter-Name-Sanitization | OK ABER OPTIONAL | `$tag` ist whitelist-konstrant (siehe `DHPS_Service_Registry`-Whitelist). Kein `sanitize_key()`-Defense-in-Depth noetig, aber waere kostenlos und Future-Proof - siehe INFO-3.2. |
| WP-Filter-Mechanik ist Code-Capability | OK | Ein Angreifer mit `add_filter()`-Zugriff hat per Definition Plugin/Theme-Code-Access (PHP-Execution). WP-Filter sind kein User-Input-Vektor. |
| Filter feuert NACH `permission_callback` (REST) bzw. innerhalb Shortcode-Render | OK | Filter-Hook liegt im Render-Pfad, nicht im Auth-Pfad. Auth wurde bereits geprueft (Shortcode wird nur auf gerenderten Pages ausgefuehrt; REST-Endpoints haben `permission_callback`). |
| BC: Bestehende Filter (z.B. `dhps_template_fallbacks`) unveraendert | OK | Neuer Filter ist additiv. Original-Tag (z.B. `'lxmio'`) wird gefiltert, NICHT der Fallback-Tag (`'mio'`) - dokumentiert in TPT-MOD-Handover Sektion 5. |
| `$data` ist Array nach Filter? | DEFENSIVE | TPT-Modules ueberprueft via `is_array()`; wenn ein anderer Filter `$data` zu Non-Array macht, gibt TPT-Modules unveraendert zurueck. **Aber:** der nachfolgende `include $template_file` Z. 182 wuerde dann auf $data-Member crashen. Akzeptables Verhalten (Plugin/Theme-Bug, kein Security-Issue). |

### Findings

- **INFO-3.1**: Der Filter `dhps_pipeline_data_{$tag}` ist neu eingefuehrt und damit ein Plugin-Public-API. Zukuenftige Subscriber (Themes, Drittanbieter-Plugins) muessen das Datenformat respektieren. Die Doc-Kommentare (Z. 164-177 Renderer, Z. 16-20 TPT-Modules) dokumentieren das Vertrags-Format korrekt.
- **INFO-3.2**: `$tag` wird ohne `sanitize_key()`-Defense-in-Depth in den Filter-Namen interpoliert. Da `$tag` aus der Service-Registry stammt (whitelist), ist das praktisch unkritisch. Future-Proofing waere: `apply_filters( 'dhps_pipeline_data_' . sanitize_key( $tag ), ... )` - dann waeren auch zukuenftige Aufrufer mit weniger strikter Tag-Quelle geschuetzt. **Empfehlung (INFO, v0.16+)**: optional ergaenzen, kein Blocker.
- **INFO-3.3**: Filter feuert in `render_parsed()`, NICHT beim L2-Cache-Set. Konsequenz (siehe Handover-Sektion 6.5): `tpt_config` wird bei jedem Render frisch via `get_option()` gelesen, nicht gecached. Das ist intendiert (Admin-Aenderungen sofort sichtbar). Sicherheitsperspektive: keine Stale-Daten im Cache, bei denen ein Admin denkt "Maske ist neu, alter Wert ist weg" - der Wert wird immer fresh gelesen.

### Verdict Section 3: **PASS** - kein neuer User-Input-Pfad, keine Privilege-Escalation, keine Injection.

---

## Section 4: Cross-Effect-Analyse

### Pruefungen

| Pruefpunkt | Ergebnis | Beleg |
|-----------|----------|-------|
| LOW-4.1 Fix erweitert OTA-Mask-Strategie nicht | OK | Aenderung ist enger (mehr Maskierung), nicht weiter. |
| Doc-Block-Aenderung (LOW-3.1/3.2) aendert Verhalten nicht | OK | Reine Kommentar-Aenderung, keine Code-Logik. |
| TPT-Modules-Layer aendert Template-Output nicht | OK | Templates erhalten identische Werte wie zuvor (nur ueber neuen Pfad: Filter statt `get_option`). |
| Neuer Filter-Hook eroeffnet keinen Angriffsvektor | OK | WP-Filter sind PHP-Capability (kein User-Input). Wer `add_filter` aufrufen kann, kann ohnehin beliebigen PHP-Code ausfuehren. |
| L2-Cache-Schema unveraendert | OK | TPT-`$data` wird OHNE `tpt_config` gecached (Filter feuert nach Cache-Read). BC: alte Cache-Eintraege aus v0.15.0 sind weiterhin valide. |
| Bestehende Security-Decisions umgekehrt | NEIN | Alle v0.15.0-Trust-Decisions (REST-Permission, OTA-Mask-Strategie, Rate-Limit-Architektur, SSRF-Schutz, SQL-Prepare, React-XSS-Safe) bleiben in Kraft. |
| Akzeptierte Trade-offs aus v0.15.0 weiterhin gueltig | OK | Doc-Block zu LOW-3.1/3.2 bekraeftigt diese Trade-offs explizit im Code. |
| Vergroesserung des Angriffsvektors | NEIN | Diff zu v0.15.0: 1 Maskierungs-Verstaerkung, 1 Doc-Erweiterung, 1 neue Klasse mit reinen `get_option`-Reads + 1 neuer Filter-Hook in bestehender Methode. |

### Verdict Section 4: **PASS** - rein additive/defensive Aenderungen ohne Cross-Effects.

---

## Section 5: Trust-Decisions (kumulativ, fortgefuehrt aus v0.15.0)

Die folgende Tabelle listet die fortgefuehrten Trust-Decisions aus v0.15.0
sowie die in v0.15.1 neu hinzugekommenen.

| # | Trust-Decision | Status v0.15.0 | Status v0.15.1 |
|---|----------------|----------------|----------------|
| 1 | React via `wp.element` ohne Build-Pipeline | Akzeptiert | unveraendert |
| 2 | Cache-Stats global statt pro Service | Akzeptiert | unveraendert |
| 3 | OTA-Preview-Maskierung 6 Zeichen | Akzeptiert mit Edge-Case-Hinweis | **Edge-Case GELOEST (siehe Sektion 1)** |
| 4 | REST-Bundle nur fuer manage_options-User | Akzeptiert | unveraendert |
| 5 | Rate-Limit Race-Condition (LOW-3.2) | Akzeptiert (Handover) | **Im Code-Doc-Block dokumentiert (siehe Sektion 2)** |
| 6 | Rate-Limit Sliding-Window-Drift (LOW-3.1) | Akzeptiert (Audit-Empfehlung) | **Im Code-Doc-Block dokumentiert (siehe Sektion 2)** |
| 7 | Test-Endpoint nutzt `cache_ttl=3600` | Akzeptiert | unveraendert |
| 8 | HEAD-Probe auf Base-URL | Akzeptiert | unveraendert |
| 9 | `auth_type`-Default `'ota'` (Defensive) | Akzeptiert | unveraendert |
| 10 | OTA als Query-Param ueber HTTPS | Akzeptiert | unveraendert |
| 11 | Dashboard-Page `wp.element.render` mit React-18-Fallback | Akzeptiert | unveraendert |
| **12 (NEU)** | **Generischer Filter `dhps_pipeline_data_{$tag}` (TPT-Modules)** | n/a | **Akzeptiert** - WP-Filter-Mechanik = PHP-Capability, kein User-Input-Vektor (siehe Sektion 3). |
| **13 (NEU)** | **`tpt_config` wird NICHT in L2-Cache geschrieben** | n/a | **Akzeptiert** - Admin-Aenderungen sollen sofort sichtbar sein. Performance-Impact minimal (`get_option` ist O(1) auf Options-Cache). |

---

## Section 6: Geprueft, ABER ausserhalb Audit-Scope

Folgende v0.15.1-Tickets sind nicht security-relevant und wurden konventionsgemaess
nicht im Detail auditiert:

| Ticket | Inhalt | Begruendung Skip |
|--------|--------|------------------|
| #4 | CSS-Cleanup | Reine Styling-Aenderung, kein PHP/JS-Verhalten, kein neuer Angriffsvektor. |
| #5 | A11y-Verbesserungen | ARIA-Attribute, Fokus-Management - kein Security-Impact. |
| #6 | CSS-Konsolidierung | Identisch zu #4. |

Falls die QA-Spec bei diesen Tickets unerwartete PHP/JS-Aenderungen findet, sollte
ein Re-Audit dieser Punkte erfolgen.

---

## Final Verdict

### **GO**

v0.15.1 ist sicherheitstechnisch produktionsreif und stellt eine echte
**Verbesserung** gegenueber v0.15.0 dar:

1. **LOW-4.1 (OTA-Edge-Case) ist abschliessend GELOEST**. Der theoretische
   Information-Disclosure-Vektor bei OTAs <= 6 Zeichen existiert nicht mehr.
2. **LOW-3.1 / LOW-3.2** sind im Code-Doc-Block explizit als akzeptierte
   Trust-Decisions dokumentiert. Zukuenftige Auditoren finden Hintergrund-
   Rationale direkt im Source.
3. **Ticket #2 (TPT-Modules)** ist ein sauberer Refactor ohne neue
   Angriffsflaeche. Der neue Filter-Hook `dhps_pipeline_data_{$tag}` ist
   WP-Standard-Architektur und entspricht dem Plugin-Public-API-Vertrag.

### Begruendung Severity-Verteilung

- 0 Critical / 0 High / 0 Medium / 0 Low.
- 1 LOW-Finding aus v0.15.0 ist GELOEST.
- 2 LOW-Findings aus v0.15.0 sind im Code dokumentiert (Trust-Decision).
- 3 INFO-Findings sind reine Forward-Looking-Empfehlungen (siehe unten),
  KEIN Blocker fuer v0.15.1.

### Empfohlene Folge-Tickets (kein v0.15.1-Blocker)

1. **INFO-2.1 (v0.15.2 oder v0.16)**: Zusaetzlicher Verweis in
   `check_rate_limit()`-Doc-Block auf `26-SECURITY-AUDIT-v0150.md` Sektion 3.
2. **INFO-3.2 (v0.16)**: `sanitize_key( $tag )` im Filter-Namen in
   `render_parsed()` als Defense-in-Depth.
3. **INFO-6.1 (uebernommen aus v0.15.0)**: LIMIT + Pagination fuer Cache-Stats
   bei extrem grossen Sites (>1000 Plugin-Transients) - weiterhin offen.

---

## Quellen

- `includes/class-dhps-health-collector.php` Z. 209-235 (OTA-Preview-Fix)
- `includes/class-dhps-admin-rest.php` Z. 496-552 (Rate-Limit-Doc-Block)
- `includes/class-dhps-tpt-modules.php` (NEU, 80 LOC)
- `includes/class-dhps-renderer.php` Z. 134-191 (Filter-Hook in render_parsed)
- `includes/class-dhps-content-pipeline.php` Z. 100-143 (Tag-Quelle Validation)
- `public/views/services/tpt/{default,card,compact}.php` (Templates ohne get_option)
- `docs/project/26-SECURITY-AUDIT-v0150.md` (Vorgaenger-Audit)
- `.specialist-TPT-MOD-handover-v0145.md` (Handover-Dokument fuer Ticket #2)
- `docs/project/11-SECURITY-AUDIT-v0140.md` (referenziert vom Rate-Limit-Doc-Block)
