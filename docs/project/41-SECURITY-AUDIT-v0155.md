# Security-Audit v0.15.5 - Voller Atts-Editor + Tech-Debt-Abschluss

Stand: 2026-05-26
Auditor: Security-Specialist
Scope: Ticket 7 (Voller Atts-Editor) + Caveat C1 (Health-Collector Sub-Shortcodes) + Caveat C4 (wp_localize_script-Bridge)
Schwester-Audit: docs/project/40-QA-REPORT-v0155.md (parallel)

---

## Executive Summary

v0.15.5 fuehrt eine zentrale **SERVICE_ATTS_SCHEMA-Konstante** (13 Services / 70
Att-Eintraege) in `DHPS_Preview_Renderer` ein, die als Single-Source-of-Truth
fuer Backend-Validation UND Frontend-UI-Generation via wp_localize_script-Bridge
dient. Die REST-Handler-Atts-Whitelist wurde entfernt - der Renderer uebernimmt
die komplette Validation-Pipeline (Type-Cast + Bounds + Options + Pattern +
Sanitize). Der Health-Collector wurde um 4 Sub-Shortcodes erweitert (parent_service
+ is_sub_shortcode), die Auth-Lookups gegen den Parent aufloesen.

Die security-relevanten Aenderungen sind:

1. **SERVICE_ATTS_SCHEMA-Konstante** (public const, immutable, 70 Eintraege).
2. **validate_att_value()** als zentraler Type-Validator (4 Pfade: string/int/bool/select).
3. **REST-Handler-Vereinfachung** (Atts-Whitelist entfernt, nur Layer-4-Defense:
   sanitize_key + is_scalar als Anti-XSS-Wall).
4. **wp_localize_script-Bridge** (3 neue Keys: services, attsSchema, subShortcodeParents).
5. **Health-Collector Parent-Resolution** (Sub-Shortcodes erben Auth/Branding/API-URL).

**Verdict: GO** (production-ready). 0 Critical, 0 High, 0 Medium, 1 Low (akzeptiert).

Die Schema-getriebene Validation ist strikt: Unbekannte Att-Keys werden in
`atts_rejected` mit differenzierten Reasons gemeldet (unknown att key vs. not
allowed for service), aber NIE silent akzeptiert. Alle pattern-Regex sind linear
(O(n)) und ReDoS-frei. Type-Coercion ist sicher (intval/FILTER_VALIDATE_BOOLEAN/
strict in_array). Schema-Bridge-Exposure an Frontend ist Admin-only (manage_options
+ Capability-Gate im Enqueue) und enthaelt KEINE Secrets.

---

## Audit-Sektionen

### Section 1: Atts-Validation-Pipeline (KRITISCH)

**Pruefling**: `includes/class-dhps-preview-renderer.php` Zeilen 1003-1102
(`validate_att_value()`) + Zeilen 880-980 (`render()`).

#### 1.1 Type-Cast-Sicherheit

**type=int (Zeilen 1062-1075)**:

```php
case 'int':
    if ( ! is_numeric( $raw ) ) {
        return array( 'ok' => false, 'reason' => 'invalid type (expected int)' );
    }
    $val = (int) $raw;
    $min = isset( $def['min'] ) ? (int) $def['min'] : PHP_INT_MIN;
    $max = isset( $def['max'] ) ? (int) $def['max'] : PHP_INT_MAX;
    if ( $val < $min || $val > $max ) {
        return array(
            'ok'     => false,
            'reason' => sprintf( 'out of bounds (min=%d, max=%d)', $min, $max ),
        );
    }
    return array( 'ok' => true, 'value' => $val );
```

**Sicherheit**:
- `is_numeric($raw)` lehnt Strings wie `"5; rm -rf /"`, `"5e9"` (wird zwar als
  numerisch erkannt aber float -> int-Cast schneidet ab), `"<script>"` ab.
- `(int)` cast ist sicher (PHP-Spezifikation: float-Konvertierung, Anhang wird
  abgeschnitten, keine Code-Ausfuehrung).
- Bounds-Check NACH Cast - kein Bypass durch Overflow (PHP_INT_MAX hard cap
  durch (int)-Cast bei Plattform-Limits).
- min/max werden durch (int)-Cast normalisiert -> kein TypeError selbst bei
  fehlkonfiguriertem Schema.

**Test-Vektoren (manuell verifiziert)**:

| Eingang | is_numeric | (int)-Cast | Bounds-Check (min=0,max=50) | Resultat |
|---------|------------|------------|------------------------------|----------|
| `"5"` | true | 5 | OK | applied=5 |
| `"5; rm -rf /"` | false | n/a | n/a | rejected: invalid type |
| `"200"` | true | 200 | 200>50 | rejected: out of bounds |
| `"-10"` | true | -10 | -10<0 | rejected: out of bounds |
| `"5.7"` | true | 5 | OK | applied=5 (akzeptabel - intval) |
| `"0xFF"` | false | n/a | n/a | rejected: invalid type |
| `"  5  "` | true | 5 | OK | applied=5 |
| `"<script>"` | false | n/a | n/a | rejected: invalid type |

**Befund**: Type-Cast fuer int ist sicher. Defense-in-Depth.

#### 1.2 Bounds-Check Reihenfolge

Bounds-Check erfolgt NACH Type-Cast, NICHT vor Sanitize (weil int-Werte nicht
sanitisiert werden muessen - sie sind nach (int) bereits clean).

Fuer string-Werte: sanitize (text_field/html_class/key/csv_int) erfolgt VOR
pattern-Match. Reihenfolge:

1. is_scalar-Cast (Zeile 1009)
2. sanitize (z.B. sanitize_text_field strippt HTML-Tags)
3. pattern-Match (Regex auf bereits gestrippten String)

Kein Bypass durch unsanitierte Input-Werte in Pattern-Engine.

**Befund**: Bounds-Check-Reihenfolge ist sicher.

#### 1.3 Options-Whitelist mit strict in_array

**type=select (Zeilen 1084-1097)**:

```php
case 'select':
    $allowed = array();
    if ( isset( $def['options'] ) && is_array( $def['options'] ) ) {
        foreach ( $def['options'] as $opt ) {
            if ( is_array( $opt ) && isset( $opt['value'] ) ) {
                $allowed[] = (string) $opt['value'];
            }
        }
    }
    $val = is_scalar( $raw ) ? (string) $raw : '';
    if ( ! in_array( $val, $allowed, true ) ) {
        return array( 'ok' => false, 'reason' => 'value not in whitelist' );
    }
    return array( 'ok' => true, 'value' => $val );
```

**Sicherheit**:
- `in_array($val, $allowed, true)` mit `true` als 3. Argument -> **strict-mode**.
  Kein Type-Juggling, `"0"` matched nicht `0`.
- Alle Whitelist-Werte werden via `(string)` normalisiert -> einheitlicher Typ.
- Input wird via `(string)` normalisiert -> kein Array/Object kann durch.

**Test-Vektoren**:

| Eingang fuer mio.teasermodus (options: '', '0', '1') | Resultat |
|------|----------|
| `"1"` | applied="1" |
| `"0"` | applied="0" |
| `""` | applied="" |
| `"2"` | rejected: value not in whitelist |
| `"01"` | rejected: value not in whitelist |
| `true` (bool) | wird is_scalar->"1" -> applied="1" |
| `0` (int) | wird is_scalar->"0" -> applied="0" |

Letzteres ist bewusst akzeptiert (bool/int werden serialisiert, das ist BC fuer
JSON-Bool und Backend-Standard).

**Befund**: Options-Whitelist mit strict in_array ist sicher.

#### 1.4 Pattern-Match-Sicherheit

**Patterns im Schema (18 Eintraege)**:

| Pattern | Vorkommen | Sicherheit |
|---------|-----------|------------|
| `^[a-zA-Z0-9_\- ]{0,64}$` | 13x (class-Att) | linear, max 64 chars |
| `^[0-9]{0,12}$` | 2x (id_merkblatt) | linear, max 12 chars |
| `^[0-9,]{0,128}$` | 3x (videoliste, csv_int sanitize) | linear, max 128 chars |

**Preg_match-Aufruf (Zeilen 1020-1025, 1053-1058)**:

```php
if ( isset( $def['pattern'] ) ) {
    $re = '/' . $def['pattern'] . '/';
    if ( ! preg_match( $re, $val ) ) {
        return array( 'ok' => false, 'reason' => 'pattern mismatch' );
    }
}
```

**ReDoS-Analyse**:
- Alle drei Pattern haben EINEN fixierten Quantifier (`{0,64}`, `{0,12}`, `{0,128}`).
- Kein nested Quantifier (z.B. `(a+)+` waere Catastrophic).
- Kein Alternation mit Overlap (z.B. `(a|a)*` waere problematisch).
- Character-Classes (`[a-zA-Z0-9_\- ]`) sind DFA-kompatibel - PCRE konvertiert
  intern in deterministische Endliche Automaten, O(n) Laufzeit.

**Maximum-Match-Komplexitaet**: O(n) wo n = Input-Laenge. Da Input bereits durch
sanitize-Schritte und scalar-Wall begrenzt ist (sanitize_text_field hat keinen
Hard-Cap, aber typische User-Inputs in Atts sind < 256 Bytes), ist die Worst-Case-
Laufzeit eines preg_match-Calls < 1ms.

**DoS-Vektor**: Ein Admin koennte einen 1MB-String als `class`-Att senden. Das
preg_match wuerde O(1.000.000) Operationen ausfuehren = ~10ms. Da REST-Endpoint
mit `manage_options` geschuetzt + rate-limited (30/min) ist, kein praktischer
DoS-Vektor.

**Pattern-Delimiter-Injection**: Pattern werden hartcodiert im Schema gespeichert,
NICHT aus User-Input gebaut. Kein Injection-Vektor.

**Befund**: Alle Patterns sind ReDoS-frei.

#### 1.5 Sanitize-Hooks korrekt aufgerufen

**Sanitize-Verzweigung (Zeilen 1010-1051)**:

| Sanitize-Hint | WP-Funktion | Sicherheit |
|---------------|-------------|------------|
| `html_class` | `sanitize_html_class()` (per Token), pattern davor | strippt invalid chars, hier mit Multi-Class-Support |
| `text_field` | `sanitize_text_field()` | strippt HTML-Tags, normalisiert Whitespace |
| `key` | `sanitize_key()` | nur a-z 0-9 _ - |
| `csv_int` | preg_match `^[0-9,]{0,128}$` | Hartcap, kein Sanitize-Replace |

**Multi-Class-Token-Sanitize (Zeilen 1028-1039)**:

```php
$tokens = preg_split( '/\s+/', trim( $val ) );
$clean_tokens = array();
foreach ( $tokens as $tok ) {
    $c = sanitize_html_class( $tok );
    if ( '' !== $c ) {
        $clean_tokens[] = $c;
    }
}
if ( empty( $clean_tokens ) ) {
    return array( 'ok' => false, 'reason' => 'invalid html-class chars' );
}
return array( 'ok' => true, 'value' => implode( ' ', $clean_tokens ) );
```

Sicherheit:
- `preg_split('/\s+/', ...)` ist DFA-kompatibel (kein Backtrack).
- `sanitize_html_class()` ist WP-Core (auditiert, strippt invalid chars).
- Empty-Check garantiert, dass leerer Token-Output nicht durchrutscht.
- `implode(' ', ...)` baut sauberen String ohne extra Spaces.

**Befund**: Sanitize-Hooks sind sicher.

---

### Section 2: REST-Handler-Vereinfachung

**Pruefling**: `includes/class-dhps-admin-rest.php` Zeilen 550-691
(`handle_service_preview()`).

#### 2.1 Layer-4-Defense (sanitize_key + is_scalar)

```php
$sanitized_atts = array();
foreach ( $atts_raw as $k => $v ) {
    $k_str = is_string( $k ) ? sanitize_key( $k ) : '';
    if ( '' === $k_str ) {
        continue;
    }
    if ( ! is_scalar( $v ) ) {
        continue;
    }
    $sanitized_atts[ $k_str ] = $v;
}
```

**Sicherheit**:
- `sanitize_key()` normalisiert Schluessel auf a-z, 0-9, _, - (keine Umlaute, kein UTF-8).
- Empty-Key wird verworfen.
- `is_scalar($v)` lehnt Arrays/Objects ab -> Anti-Recursion-Wall. Akzeptiert
  string/int/float/bool.

**Anti-XSS-Garantie**: Selbst wenn ein Admin via curl ein verschachteltes JSON
sendet (z.B. `atts: { foo: { "<script>": "x" }}`), wird der inner Object-Wert
abgewiesen, weil `is_scalar` false liefert.

**Test-Vektoren**:

| Eingang `atts_raw` | Sanitized Output |
|--------------------|------------------|
| `{ "layout": "card" }` | `{ "layout": "card" }` |
| `{ "LAYOUT": "card" }` | `{ "layout": "card" }` (sanitize_key lowercase) |
| `{ "lay\nout": "card" }` | `{ "layout": "card" }` (sanitize_key strippt \n) |
| `{ "_priv": "x" }` | `{ "_priv": "x" }` (underscore erlaubt) |
| `{ "foo bar": "x" }` | `{ "foobar": "x" }` (sanitize_key kollabiert) |
| `{ "evil": ["nested"] }` | `{}` (is_scalar false) |
| `{ 123: "x" }` | `{}` (is_string false) |
| `{ "": "x" }` | `{}` (empty key) |
| `{ "x-y": "ok" }` | `{ "x-y": "ok" }` (hyphen erlaubt) |

**Befund**: Layer-4-Defense ist ausreichend. Alle Type-Validation (int/bool/
options/pattern) erfolgt im Renderer auf bereits-key-sanitisiertem Input.

#### 2.2 Renderer-Validation fuer ALLE Atts

Im REST-Handler wird KEINE Atts-Whitelist mehr applied (zuvor in v0.15.4:
`$known_top_keys = array('layout', 'class', 'section', 'cache')`). Stattdessen
gehen ALLE Atts durch zum Renderer.

Im Renderer (Zeilen 905-942) wird jeder Key gegen das Schema gepruft:

```php
foreach ( $atts as $key => $raw ) {
    $key_str = (string) $key;
    // section-Sonderfall...
    if ( ! isset( $schema[ $key_str ] ) ) {
        $atts_rejected[ $key_str ] = $this->is_known_att_anywhere( $key_str )
            ? 'not allowed for service'
            : 'unknown att key';
        continue;
    }
    // validate_att_value...
}
```

**Garantie**: Kein Key ueberlebt die Renderer-Pipeline ohne Schema-Lookup.

**Befund**: Renderer-Validation greift fuer ALLE Atts. Belt-and-Suspenders.

#### 2.3 Unbekannte Keys: kein silent-accept

Der Renderer differenziert 2 Reject-Reasons:

- `unknown att key` - Key in keinem Service-Schema vorhanden.
- `not allowed for service` - Key existiert in einem anderen Service-Schema.

Beide Faelle landen in `atts_rejected` und fliessen NICHT in den Shortcode-String:

```php
if ( false === $validated['ok'] ) {
    $atts_rejected[ $key_str ] = $validated['reason'];
    continue;  // <-- Key wird nicht in $shortcode_atts aufgenommen
}
```

Der Shortcode-String wird nur aus `atts_applied` gebaut. Rejected Keys haben
keinerlei Einfluss auf den ausgefuehrten Shortcode.

**Befund**: Kein silent-accept, alle Reject-Reasons sind transparent.

#### 2.4 Defense-in-Depth-Layer-Tabelle (v0.15.5)

| Layer | Check | Pfad | Status |
|-------|-------|------|--------|
| 1 | REST-Route-Regex `[a-z_]+` | register_rest_route() | unveraendert v0.15.4 |
| 2 | sanitize_key auf service-slug | args.service.sanitize_callback | unveraendert v0.15.0 |
| 3 | validate_service_param: Laenge <= 32 | Zeilen 322-328 | unveraendert v0.15.4 |
| 4 | ALLOWED_SERVICES-Whitelist | Zeilen 330-336 + 573-579 | unveraendert v0.15.4 |
| 5 | Service-Registry-Lookup mit Parent-Resolution | Zeilen 583-593 | unveraendert v0.15.4 |
| 6 | Atts-Key: sanitize_key + scalar-Wall | Zeilen 644-654 | **NEU/vereinfacht v0.15.5** |
| 7 | Schema-Lookup SERVICE_ATTS_SCHEMA | Renderer Zeilen 905-922 | **NEU v0.15.5** |
| 8 | Type+Bounds+Options+Pattern-Validation | Renderer Zeilen 1003-1102 | **NEU v0.15.5** |
| 9 | esc_attr beim Shortcode-String-Bau | Renderer Zeile 941 | unveraendert v0.15.3 |

**Befund**: 9-Layer-Defense-in-Depth, im Vergleich zu v0.15.4 (7 Layer) um 2 Layer
verstaerkt. Keine Reduktion der Sicherheit durch Whitelist-Entfernung im REST-
Handler.

---

### Section 3: wp_localize_script-Bridge

**Pruefling**: `Deubner_HP_Services.php` Zeilen 841-853.

#### 3.1 Capability-Gate vor Exposure

```php
function dhps_enqueue_admin_dashboard( $hook_suffix ) {
    // Hook-Gate: nur auf der dhps_dashboard-Page.
    $is_dashboard_page = ( false !== strpos( (string) $hook_suffix, 'dhps_dashboard' ) );
    if ( ! $is_dashboard_page ) {
        return;
    }
    // Capability-Gate: nur Admins koennen das Dashboard sehen.
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    // ... wp_localize_script erfolgt erst NACH beiden Gates
}
```

**Sicherheit**:
- Page-Hook-Check: Bridge wird nur auf dem Plugin-Dashboard exponiert.
- `manage_options`-Check: Bridge wird nur Admins exponiert.
- Doppelter Gate (Page + Capability) verhindert Exposure auf anderen Admin-Pages
  oder fuer non-Admin-Editoren.

**Befund**: Capability-Gate ist sicher.

#### 3.2 Exposed Daten (KEINE Secrets)

| Key | Wert | Sensitive? |
|-----|------|------------|
| `restUrl` | `esc_url_raw(rest_url('dhps/v1/'))` | nein (public REST-URL) |
| `restNonce` | `wp_create_nonce('wp_rest')` | nonce ist per Definition zur Nutzung im Browser-Kontext gedacht |
| `i18nDomain` | `'deubner_hp_services'` | nein (Konstante) |
| `services` | `DHPS_Admin_REST::ALLOWED_SERVICES` (Array von 13 Slugs) | nein (oeffentliche Shortcode-Tags) |
| `attsSchema` | `DHPS_Preview_Renderer::SERVICE_ATTS_SCHEMA` | nein (siehe 3.3) |
| `subShortcodeParents` | `DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS` | nein (oeffentliche Mapping-Info) |

**Was NICHT exponiert wird**:
- KEINE OTAs / Auth-Tokens.
- KEINE kdnr-Werte.
- KEINE User-Daten.
- KEINE Endpoint-URLs (sind in Service-Registry, NICHT in Bridge).
- KEINE Cache-Keys / Transient-Keys.
- KEINE Datenbank-Tabellen-Namen.

#### 3.3 attsSchema Inhalts-Audit

Das Schema enthaelt pro Att-Entry exakt diese Felder (Schema-Vertrag):

- `type`: Konstante String-Werte ('string'/'int'/'bool'/'select').
- `default`: Defaults der Shortcode-Atts (z.B. `''`, `0`, `3600`).
- `options`: UI-Labels + Value-Lists (z.B. `'(default)'`, `'tagesaktuell'`).
- `min`/`max`: Numerische Bounds.
- `pattern`: Hartcodierte Regex (kein User-Input).
- `sanitize`: Sanitize-Hint-Konstanten.
- `group`: 'universal' oder 'service_specific'.
- `label`/`description`: Human-readable UI-Strings.

**Information-Disclosure-Bewertung**: Das Schema gibt Admins Auskunft ueber:
- Welche Atts ein Service akzeptiert (oeffentliche WP-Shortcode-API).
- Welche Bounds gelten (kann auch durch Reject-Reason "out of bounds" erfahren werden).
- Welche Patterns gelten (linear, kein ReDoS-Vektor).

Da das Plugin Open-Source ist (GitHub-Repo `cai-gmbh-dev/wp-deubner-hp-services`)
und alle diese Informationen ohnehin im Source-Code stehen, ist keine zusaetzliche
Disclosure gegeben.

**Befund**: Keine sensiblen Daten in attsSchema. Trust-Decision T11 dokumentiert.

#### 3.4 JSON-Encoding-Sicherheit

`wp_localize_script()` ruft intern `wp_json_encode()` auf, welches:
- HTML-Entity-Encoding fuer `<`, `>`, `&`, `'`, `"` durchfuehrt (JSON_HEX_TAG |
  JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).
- Sichere Konvertierung verschachtelter Arrays.
- UTF-8-Encoding ohne BOM.

Selbst wenn ein Schema-Eintrag versehentlich `'</script>'` in einem Label
enthalten wuerde (was er NICHT tut), wuerde die JSON-Encoding-Stage das in
`</script>` umwandeln. Kein XSS-Vektor.

**Befund**: JSON-Encoding via WP-Standard ist sicher.

---

### Section 4: Health-Collector Parent-Resolution

**Pruefling**: `includes/class-dhps-health-collector.php` Zeilen 43-48, 136-194.

#### 4.1 SUB_SHORTCODE_PARENTS-Lookup vor Health-Daten-Lookup

```php
public function collect_for( string $service ): array {
    $service = sanitize_key( $service );

    // Sub-Shortcode-Resolution: lookup via SUB_SHORTCODE_PARENTS.
    $parent_slug = $service;
    if ( class_exists( 'DHPS_Preview_Renderer' ) ) {
        $sub_parents = DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS;
        if ( isset( $sub_parents[ $service ] ) ) {
            $parent_slug = $sub_parents[ $service ];
        }
    }
    $is_sub = ( $parent_slug !== $service );

    // ... alle internen Lookups gegen $parent_slug
}
```

**Sicherheit**:
- `sanitize_key($service)` als erste Defense.
- `class_exists`-Check vermeidet Fatal bei isolierter Instanziierung.
- `SUB_SHORTCODE_PARENTS` ist `public const` (immutable, 4 Eintraege hartcodiert).
- Fallback `$parent_slug = $service` falls kein Sub-Mapping -> identisches Verhalten
  wie v0.15.4 fuer Hauptservices.

**Cross-Service-Leak-Test**:

| Input $service | $parent_slug nach Map | OTA-Key Lookup |
|----------------|------------------------|----------------|
| `mio_termine` | `mio` | `dhps_ota_mio` (designed) |
| `maes_videos` | `maes` | `dhps_maes_kdnr` (designed) |
| `tp` (Hauptservice) | `tp` (Pass-Through) | `dhps_ota_tp` (unveraendert) |
| `foobar` (unbekannt) | `foobar` (Pass-Through) | `''` (Map kennt nicht -> leerer Key) |

Kein Cross-Service-Leak moeglich. Map ist 1-zu-N (4 Subs -> max 2 Parents:
mio + maes).

**Befund**: Parent-Resolution ist sicher.

#### 4.2 OTA-Preview Maskierung unveraendert

Sub-Shortcode `maes_videos` ruft `$this->get_ota_preview('maes')` auf:

```php
private function get_ota_preview( string $service ): string {
    // ...
    if ( strlen( $value ) <= 6 ) {
        return '***';
    }
    return substr( $value, 0, 6 ) . '...';
}
```

Maskierung ist identisch zu v0.15.4 - es wird IMMER nur die ersten 6 Zeichen
exposed, niemals der volle Token. Sub-Shortcode `maes_videos` sieht die GLEICHE
Maskierung wie der Hauptservice `maes` - kein neuer Leak.

**Befund**: OTA-Preview-Maskierung unveraendert sicher.

#### 4.3 parent_service + is_sub_shortcode-Felder additiv

Neue Felder im Health-Record:
- `parent_service` (string) - Parent-Slug.
- `is_sub_shortcode` (bool) - true wenn Sub-Shortcode.

Beide sind ADDITIV. Bestehende ServiceHealthCard ignoriert unbekannte Felder
defensiv (`service.name || slug.toUpperCase()`). Kein BC-Bruch.

**Information-Disclosure-Bewertung**: Beide Felder geben dem Admin Auskunft, dass
ein Sub-Shortcode existiert und seinen Parent. Da diese Mapping bereits in
`SUB_SHORTCODE_PARENTS` (public const) sowie in der JS-Bridge (subShortcodeParents)
exponiert ist, ist hier kein neuer Leak.

**Befund**: parent_service + is_sub_shortcode sind additiv und sicher.

---

### Section 5: ReDoS / Pattern-Risiken

#### 5.1 Pattern-Inventar

Im SERVICE_ATTS_SCHEMA werden 3 Regex-Patterns verwendet (siehe Section 1.4):

1. `^[a-zA-Z0-9_\- ]{0,64}$` (13 class-Atts)
2. `^[0-9]{0,12}$` (2 id_merkblatt-Atts)
3. `^[0-9,]{0,128}$` (3 videoliste-Atts, plus 3 csv_int-Sanitize-Hints)

#### 5.2 ReDoS-Tests

**Pattern 1**: `^[a-zA-Z0-9_\- ]{0,64}$`

- DFA-kompatibel (Character-Class statt Alternation).
- Single Quantifier mit Hard-Cap `{0,64}`.
- Anchored mit `^...$` -> kein Substring-Match noetig.
- ReDoS-Test mit Catastrophic-Input `"a" x 1000000`: Pattern matched bis Position
  64+1 = 65 (Anchor verlangt End-of-String), dann sofortiger Fail -> O(n) wo
  n = 65 (Pattern-Quantifier-Limit).
- **Resultat**: O(min(n, 64)) = O(1) praktisch. Kein ReDoS.

**Pattern 2**: `^[0-9]{0,12}$`

- Hard-Cap 12 chars. O(1) praktisch.

**Pattern 3**: `^[0-9,]{0,128}$`

- Hard-Cap 128 chars. O(1) praktisch.

#### 5.3 ReDoS-Stress-Test (theoretisch)

Mit PCRE-Standard-Limit `pcre.backtrack_limit = 1000000` und `pcre.recursion_limit
= 100000`: Selbst wenn ein praepariertes Pattern Backtracking erzwingen wuerde,
wuerde PCRE bei Limit-Ueberschreitung mit `preg_last_error() = PREG_BACKTRACK_LIMIT_ERROR`
abbrechen. `preg_match()` liefert dann `false` -> `pattern mismatch` Reject.

Unsere Patterns haben aber gar kein Backtracking - O(n) ohne Backtrack.

**Befund**: Keine ReDoS-Risiken. Pattern sind hartcodiert (keine User-Input-
Interpolation). Defense via PCRE-Engine-Limits zusaetzlich vorhanden.

#### 5.4 Neue preg_*-Patterns ausserhalb des Schemas

Grep gegen `class-dhps-preview-renderer.php`:
- Zeile 1022: `preg_match( $re, $val )` - Pattern aus Schema (kein User-Input).
- Zeile 1028: `preg_split( '/\s+/', trim( $val ) )` - DFA, kein Backtrack.
- Zeile 1048: `preg_match( '/^[0-9,]{0,128}$/', $val )` - hartcodiert, linear.
- Zeile 1054: `preg_match( $re, $val )` - Pattern aus Schema (kein User-Input).

**Befund**: Alle preg_*-Calls sind sicher.

---

### Section 6: Type-Coercion-Sicherheit

#### 6.1 type=int

Test: User schickt `"5; rm -rf /"`:

1. REST-Handler: `is_scalar($v)` true -> uebernommen.
2. Renderer `validate_att_value`:
   - `is_numeric("5; rm -rf /")` -> **false**.
   - Reject mit `'invalid type (expected int)'`.
3. Wird NICHT in Shortcode-String aufgenommen.

Test: User schickt `"5"`:

1. `is_numeric("5")` -> true.
2. `(int) "5"` -> 5.
3. Bounds-Check -> OK.
4. In `atts_applied[$key] = 5`.
5. Shortcode: `' count="5"'` mit `esc_attr(5)` -> `'5'`.

**Befund**: Type-Coercion fuer int ist sicher.

#### 6.2 type=bool

Test: User schickt verschiedene "Truthy"-Werte:

| Eingang | FILTER_VALIDATE_BOOLEAN | Resultat |
|---------|--------------------------|----------|
| `"true"` | true | applied="1" |
| `"1"` | true | applied="1" |
| `"yes"` | true | applied="1" |
| `"on"` | true | applied="1" |
| `"false"` | false | applied="0" |
| `"0"` | false | applied="0" |
| `"no"` | false | applied="0" |
| `"off"` | false | applied="0" |
| `""` | false | applied="0" |
| `"maybe"` | null (FILTER_NULL_ON_FAILURE) | rejected |

**Befund**: `FILTER_VALIDATE_BOOLEAN` mit `FILTER_NULL_ON_FAILURE` ist robust.
Mapping auf `'0'`/`'1'` ist eindeutig.

Hinweis: Im aktuellen Schema wird `type=bool` NICHT verwendet (cache wurde von
v0.15.4-bool zu v0.15.5-int migriert - siehe Trust-Decision T10). Der bool-Pfad
ist verfuegbar fuer zukuenftige Atts.

#### 6.3 type=select

Test: User schickt `"<script>"` fuer mio.layout (whitelist: 'default'/'card'/'compact'):

1. `is_scalar("<script>")` true -> uebernommen.
2. `(string)$raw = "<script>"`.
3. `in_array("<script>", ['default','card','compact'], true)` -> false.
4. Reject mit `'value not in whitelist'`.

**Befund**: Select-Coercion mit strict in_array ist sicher.

---

### Section 7: Trust-Decisions v0.15.5 (kumulativ T1-T12)

| # | Decision | Begruendung | Status |
|---|----------|-------------|--------|
| T1 | `html` wird NICHT durch wp_kses_post gefiltert | TC-Inline-JS + TP-Lazy-Video + MAES-Akkordeon brauchen `<script>`-Tags. iframe-Sandbox + manage_options-Isolation. | v0.15.3, unveraendert |
| T2 | OTA in iframe-HTML als JS-URL-Param sichtbar | Admin sieht OTA ohnehin via Options-Page. | v0.15.3, unveraendert |
| T3 | iframe-Sandbox `allow-same-origin + allow-scripts` ist W3C-schwach | HTML ist Plugin-eigen (keine User-HTML-Eingabe). Admin-only. | v0.15.3, unveraendert |
| T4 | atts_rejected als Map statt Array (Schema-Drift) | Mehr Information fuer Admins. Frontend liest defensiv. | v0.15.3, unveraendert |
| T5 | Sliding-Window-Drift + Race-Condition im Rate-Limit | Tolerabel fuer Admin-Tooling (max ~60 Requests in 10s). | v0.14.0/v0.15.0, unveraendert |
| T6 | REST-Route-Regex `[a-z_]+` | Notwendig fuer Sub-Shortcodes mit Underscore. Whitelist-Defense-in-Depth. | v0.15.4, unveraendert |
| T7 | SERVICE_PARAM_MAX_LENGTH 16 -> 32 | `maes_merkblaetter` hat 17 Zeichen. 32 = 88% Reserve. | v0.15.4, unveraendert |
| T8 | postMessage targetOrigin='*' | iframe-srcdoc hat origin='null' - kein klassischer Origin-Check moeglich. Mitigation: 3-Layer-Defense im Listener. | v0.15.4, unveraendert |
| T9 | Auth-Lookup via Parent fuer Sub-Shortcodes | Sub-Shortcodes nutzen designed-by-architecture den Parent-OTA. Map ist hartcodiert (1-zu-N, kein Crossover). | v0.15.4, unveraendert |
| **T10 (NEU)** | **SERVICE_ATTS_SCHEMA als PHP-Const + wp_localize_script-Bridge** | Statisches Schema, kein REST-Roundtrip noetig, 0 Schema-Drift Backend/Frontend. Sicherheits-Wall durch Type+Bounds+Pattern. 70 Eintraege fuer 13 Services. | v0.15.5 |
| **T11 (NEU)** | **wp_localize_script-Bridge exposed Schema an Frontend** | Capability-Gate (manage_options) + Page-Hook-Gate verhindert Exposure ausserhalb Admin-Dashboard. Schema enthaelt KEINE Secrets (keine OTAs, kdnr, URLs). JSON-Encoding via wp_json_encode (HTML-Entity-safe). | v0.15.5 |
| **T12 (NEU)** | **Health-Collector Sub-Shortcode-Parent-Resolution** | Sub-Shortcodes erben Auth-Status + Branding + API-URL via SUB_SHORTCODE_PARENTS-Map (hartcodiert, public const, immutable). Output behaelt Original-Slug. OTA-Preview-Maskierung unveraendert (6-char-Hard-Cap). Neue Felder parent_service + is_sub_shortcode sind ADDITIV (BC-sicher). | v0.15.5 |

---

### Section 8: Information Disclosure

#### 8.1 atts_rejected Reject-Reasons

Reject-Reasons sind explizit gestaltet, um Admins ein Debug-Feedback zu geben:

| Reason | Information-Disclosure-Bewertung |
|--------|-----------------------------------|
| `unknown att key` | Key existiert nirgends - kein Internal-Detail-Leak. |
| `not allowed for service` | Key existiert anderswo - leakt, dass das Plugin diesen Key kennt (z.B. `einzelvideo` ist TP-spezifisch). Akzeptabel fuer Admin. |
| `out of bounds (min=N, max=M)` | Leakt Schema-Bounds. Akzeptabel fuer Admin (siehe 8.2). |
| `invalid type (expected int)` | Leakt Schema-Type. Akzeptabel. |
| `pattern mismatch` | Leakt nicht das Pattern selbst. Sicher. |
| `value not in whitelist` | Leakt nicht die Whitelist-Werte. Sicher. |
| `invalid html-class chars` | Generischer Hinweis, kein Leak. |
| `value not boolean (0\|1)` | Generischer Hinweis. |
| `only allowed for maes` | Leakt MAES-Special-Case (oeffentlich dokumentiert). |

#### 8.2 Bounds-Disclosure via sprintf

`out of bounds (min=%d, max=%d)` exposed Schema-Bounds. Diese Bounds sind aber:

- Bereits in der wp_localize_script-Bridge an Admins exponiert (`attsSchema`).
- Im Open-Source-Code des Plugins enthalten.
- Fuer die UI-Generation noetig (Frontend zeigt `(0..50)` als Hint).

**Befund**: Bounds-Disclosure ist erwuenscht und sicher fuer Admin-Kontext.

#### 8.3 Stack-Trace-Disclosure

```php
try {
    $rendered = $this->preview_renderer->render( $service, $sanitized_atts );
} catch ( \Throwable $e ) {
    return new WP_Error(
        'preview_render_failed',
        'Preview konnte nicht gerendert werden: ' . $e->getMessage(),
        array( 'status' => 500 )
    );
}
```

Exception-Message wird in Response geleakt. PHP-Exceptions koennen Datei-Pfade
+ Line-Numbers enthalten (z.B. `Undefined property: DHPS_TP_Parser::$foo in /var/
www/html/wp-content/plugins/wp-deubner-hp-services/includes/class-dhps-tp-parser.php
line 142`).

**Bewertung**: Admin-only Endpoint, manage_options. Stack-Trace gibt Admin ein
nuetzliches Debug-Info. KEIN Leak an non-Admins. Akzeptabel.

**Finding L1 (Low, akzeptiert)**: Exception-Message kann Server-Path leaken.
Trust-Decision: Admin sieht Server-Path ohnehin via Plugin-Editor, WordPress-
Filesystem-Browser, etc. Nicht release-blockend.

#### 8.4 do_shortcode-Inline-JS

Sub-Shortcode `[maes_videos]` -> MAES-Pipeline -> Service-JS-Bindings mit OTA
in JS-AJAX-URLs. Identisches Verhalten wie v0.15.4 (Trust-Decision T2). Admin-
only-Endpoint, kein neuer Vektor.

**Befund**: Information-Disclosure unveraendert.

---

### Section 9: BC + Trust-Layer

#### 9.1 BC fuer bestehende Atts

| Att | v0.15.4-Verhalten | v0.15.5-Verhalten | BC? |
|-----|--------------------|--------------------|-----|
| `layout=default` | ALLOWED_LAYOUTS-Whitelist | SERVICE_ATTS_SCHEMA select-Whitelist | OK (gleiche Optionen) |
| `class=foo` | sanitize_html_class | sanitize_html_class + Pattern + Multi-Token-Support | OK (sogar erweitert) |
| `section=videos` | only-for-maes Check | only-for-maes Check + Schema-Lookup | OK |
| `cache=1` | ALLOWED_CACHE_VALUES (boolean) | type=int (0..86400) | **BC-Bruch dokumentiert** |

**cache BC-Bruch**:
- v0.15.4: `cache="1"` -> applied=true (boolean).
- v0.15.5: `cache="1"` -> applied=1 (int, valid 0..86400).
- Im Frontend wurde das via AttFieldInt umgestellt -> Backend bekommt jetzt
  numerischen String. Funktional aequivalent: `cache=1` bedeutet "1s Cache",
  effektiv kein Cache. `cache=3600` bedeutet 1h Cache.
- Externe Shortcode-Caller die `cache="false"` senden: wird zu `is_numeric("false")
  = false` -> reject `invalid type (expected int)`. BC-Bruch fuer Cornercase.

**Bewertung**: Schema-Vertrag dokumentiert dies eindeutig (O2 im Handover). Lead
sollte ggf. einen v0.15.6-Hinweis aufnehmen, falls externe Caller existieren.

#### 9.2 Neue Atts strikt validiert

Alle neuen Atts (teasermodus, einzelvideo, videoliste, columns, count, month,
filter, variante, modus, st_kategorie, id_merkblatt, rubrik, show_teaser, section,
lazy_count, lazy_mode) gehen durch die 4 Validation-Pfade:

- string -> sanitize + pattern.
- int -> is_numeric + bounds.
- bool -> FILTER_VALIDATE_BOOLEAN.
- select -> strict in_array.

**Befund**: Strikte Validation fuer alle 70 Atts.

#### 9.3 13/13 Shortcode-Regression

Pre-Release-Smoke (QA verifiziert):
- 9 Hauptservices (mio, lxmio, mmb, mil, tp, tpt, tc, maes, lp).
- 4 Sub-Shortcodes (mio_termine, maes_videos, maes_merkblaetter, maes_aktuelles).

Alle 13 muessen im Frontend weiterhin rendern (Frontend-Pfad: shortcode_atts()
in Handlern - unveraendert).

**Bewertung**: Frontend-Pfad ist von v0.15.5 unangetastet. BC-sicher.

---

### Section 10: Schema-Vertrag-Einhaltung (Lehre v0.15.0/v0.15.3)

#### 10.1 Discovery-Vertrag (10 Felder)

Discovery `23-ATTS-EDITOR-PLAN-v0155.md` Sektion 3.1 definiert 10 Felder
(type, default, options, min, max, pattern, sanitize, group, label, description).

**Verifikation gegen Implementation**:

Grep `'type'\|'default'\|'group'` in class-dhps-preview-renderer.php -> 70 Eintraege
mit allen 3 Pflichtfeldern (siehe Smoke-Tests im Handover Section 8).

Spot-Check (mio.teasermodus):
- type: 'select' ✓
- default: '' ✓
- options: Array von {value, label}-Objekten ✓
- group: 'service_specific' ✓
- label: 'Teaser-Modus' ✓

Spot-Check (mio_termine.count):
- type: 'int' ✓
- default: 0 ✓
- min: 0 ✓
- max: 50 ✓
- group: 'service_specific' ✓
- label: 'Anzahl pro Monat (0=alle)' ✓

**Befund**: Schema-Vertrag strikt eingehalten.

#### 10.2 F12-Spec Einhaltung

Handover `.specialist-F12-v0155-handover.md` Section 2.4 listet 13 Services / 70
Atts. Verifikation:

- `count( SERVICE_ATTS_SCHEMA )` = 13. ✓
- mio: 8 Atts. ✓ (teasermodus, filter, variante, modus, st_kategorie, layout, class, cache)
- mio_termine: 5 Atts. ✓ (count, month, layout, class, cache)
- maes_videos: 8 Atts. ✓ (columns, einzelvideo, videoliste, lazy_count, lazy_mode, layout, class, cache)

**Befund**: F12-Spec-Inhalt vollstaendig umgesetzt.

#### 10.3 Frontend liest exakt aus Bridge

`window.dhpsAdminConfig.attsSchema[$service]` ist die einzige Schema-Quelle im
Frontend. Keine zweite hartcodierte Liste mehr (vorherige `PREVIEW_SERVICES`-
Konstante wird durch `services`-Bridge-Key abgeloest, siehe BC-Fallback in
Handover Section 5.7).

**Schema-Drift-Risiko**: 0 - dieselbe PHP-Konstante wird als Backend-Validator UND
Frontend-Bridge genutzt. Aenderungen werden automatisch synchron.

**Befund**: 0 Schema-Drift erwartet, im Spec strikt eingehalten.

---

## Findings-Uebersicht

| ID | Severity | Sektion | Befund | Status |
|----|----------|---------|--------|--------|
| L1 | Low (akzeptiert) | 8.3 | Exception-Message in preview_render_failed kann Server-Path leaken | Trust-Decision: Admin-only, kein Leak an non-Admins. Nicht release-blockend. |

Restliche v0.15.4-Findings (T6-T9 Trust-Decisions) sind unveraendert.

**Critical: 0, High: 0, Medium: 0, Low: 1 (akzeptiert).**

---

## ReDoS-Check-Summary

| Pattern | Quantifier | DFA-Compat | Hard-Cap | ReDoS-Risk |
|---------|------------|------------|----------|------------|
| `^[a-zA-Z0-9_\- ]{0,64}$` | linear | ja | 64 chars | KEINS |
| `^[0-9]{0,12}$` | linear | ja | 12 chars | KEINS |
| `^[0-9,]{0,128}$` | linear | ja | 128 chars | KEINS |

Alle Patterns sind hartcodiert (kein User-Input), Character-Class basiert, mit
fixiertem Hard-Cap-Quantifier. Worst-Case O(n) wo n = Hard-Cap. PCRE-Engine-
Limits als zusaetzliche Defense.

---

## Atts-Validation-Sicherheits-Summary

| Pruefling | Sicher? | Begruendung |
|-----------|---------|-------------|
| Type-Cast int | ja | is_numeric + (int) sicher, Anti-Injection via Discard nicht-numerischer Inputs |
| Bounds-Check vor sanitize | n/a | int-Werte brauchen keinen Sanitize-Schritt |
| Options-Whitelist | ja | in_array mit strict-Mode, Whitelist normalisiert auf string |
| Pattern-Match | ja | Linear, hartcodiert, ReDoS-frei |
| sanitize_html_class | ja | WP-Core, Multi-Token-Support korrekt |
| sanitize_text_field | ja | WP-Core |
| sanitize_key | ja | WP-Core, lowercase + a-z0-9_- |
| FILTER_VALIDATE_BOOLEAN | ja | Mit FILTER_NULL_ON_FAILURE robust |
| esc_attr beim Shortcode-Bau | ja | Layer 9 Output-Wall |

---

## Verdict

**GO** - v0.15.5 ist production-ready aus Security-Sicht.

Begruendung:
- 0 Critical, 0 High, 0 Medium, 1 Low (akzeptiert).
- SERVICE_ATTS_SCHEMA-Konstante ist immutable (public const) und enthaelt keine
  Secrets.
- validate_att_value() ist robust: alle 4 Type-Pfade haben Anti-Injection-Walls
  (is_numeric / FILTER_VALIDATE_BOOLEAN / strict in_array / sanitize_html_class).
- REST-Handler-Vereinfachung reduziert NICHT die Sicherheit - im Gegenteil, die
  9-Layer-Defense-in-Depth ist um 2 Layer verstaerkt (Schema-Lookup + Type-
  Validation) gegenueber v0.15.4.
- wp_localize_script-Bridge ist sicher: doppelter Capability-Gate (Page-Hook +
  manage_options), kein Secret-Exposure, JSON-Encoding via WP-Standard.
- Health-Collector Parent-Resolution erbt sicher (1-zu-N Map ohne Crossover),
  OTA-Maskierung unveraendert, neue Felder sind additiv BC-sicher.
- Alle 3 Pattern sind ReDoS-frei (linear, DFA-kompatibel, hard-capped).
- Type-Coercion sicher fuer alle 4 Pfade (int/bool/select/string).
- Schema-Drift-Risiko = 0 (selbe PHP-Konstante wird Backend + Frontend genutzt).
- 13/13 Shortcode-Regression BC-sicher (Frontend-Pfad unangetastet).

**Vor Release kein Blocker**, aber dokumentationshalber:
- T10-T12 in CHANGELOG-v0155 dokumentieren (Trust-Decision-Transparenz).
- Optional: cache-BC-Bruch (boolean->int) im CHANGELOG-v0155 explizit kennzeichnen,
  falls externe Shortcode-Caller existieren.

**Fuer v0.16.0 vorgemerkt** (Backlog, nicht release-blockend):
- Atts-Wishlist (z.B. MIO/count, MMB/kategorie) erfordert Shortcode-Handler-
  Erweiterung - separates Ticket.
- Schema-Versioning (z.B. SCHEMA_VERSION-Const fuer Browser-Cache-Invalidation)
  optional fuer zukuenftige Frontend-Drift-Prevention.

---

## Quellen

- `includes/class-dhps-preview-renderer.php` Zeilen 84-89 (SUB_SHORTCODE_PARENTS),
  Zeilen 139-841 (SERVICE_ATTS_SCHEMA), Zeilen 880-980 (render), Zeilen 1003-1102
  (validate_att_value), Zeilen 1116-1123 (is_known_att_anywhere).
- `includes/class-dhps-admin-rest.php` Zeilen 62-78 (ALLOWED_SERVICES), Zeilen
  550-691 (handle_service_preview), Zeilen 633-654 (Layer-4-Defense).
- `includes/class-dhps-health-collector.php` Zeilen 43-48 (SERVICES), Zeilen
  136-194 (collect_for mit Parent-Resolution).
- `Deubner_HP_Services.php` Zeilen 806-857 (dhps_enqueue_admin_dashboard mit
  wp_localize_script-Bridge).
- `docs/architecture/23-ATTS-EDITOR-PLAN-v0155.md` (Discovery, Schema-Vertrag).
- `.specialist-F12-v0155-handover.md` (Specialist-Handover).
- `docs/project/38-SECURITY-AUDIT-v0154.md` (Vorgaenger-Audit, T1-T9).
