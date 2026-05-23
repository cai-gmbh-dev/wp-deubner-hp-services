# Security Audit v0.14.1 - MAES Component-Migration

**Audit-Datum:** 2026-05-23
**Auditor:** Security-Specialist (Anthropic Claude, Opus 4.7 / 1M)
**Release-Scope:** MAES-Migration auf v0.14.0-Component-System (Videos, Merkblaetter, Aktuelles), ContentCard-Erweiterung um `data_attrs`, Medizin/Recht-Branding-Hooks, dhps-tp.js Selector-Patch, Loeschung `dhps-maes-aktuelles.js`.
**Audit-Methode:** Statischer Code-Review (kein dynamischer Pen-Test, kein Penetration in laufende Instanz)
**Verdict:** **GO** (0 Critical, 0 High, 0 Medium - nur Info-Notizen)

---

## Executive Summary

Der Release v0.14.1 migriert die drei MAES-Sub-Templates (Videos, Merkblaetter, Aktuelles) inkl. ihrer Card- und Compact-Layouts auf das v0.14.0-Component-System (ContentList + ContentCard). Drei Orchestrator-Shims (`default.php`, `card.php`, `compact.php`) loesen die alte monolithische Renderlogik per `include`-Delegation auf. ContentCard wird um die optionale Prop `data_attrs` erweitert, damit die Templates dem TP-Video-Player-JS die benoetigten `data-video-slug`/`data-poster-url`/`data-v-modus`-Attribute mitgeben koennen, ohne dass jedes Template eigenes Markup duplizieren muss. CSS-Branding-Hooks (`.dhps-content-card--service-{slug}`) liefern die Service-Akzentfarbe pro Familie (Medizin-Teal fuer MAES, Recht-Blau fuer LXMIO/LP). Schliesslich wird das ehemalige `dhps-maes-aktuelles.js` (v0.13.1 Akkordeon-Toggle) zugunsten der Alpine-basierten `dhpsContentCard().toggle()` entfernt - ein Net-Gewinn fuer CSP-Strenge.

Es wurden **0 Critical**, **0 High**, **0 Medium**, **0 Low**, **5 Info** Findings identifiziert. Vier explizit akzeptierte Trust-Decisions werden unten dokumentiert. Der Release ist sicherheitsseitig freigegeben.

---

## Section 1: data_attrs-Erweiterung in ContentCard

**File:** `public/views/components/content-card.php` (Zeilen 117-128)

### Code-Audit

```php
$data_attr_str = '';
foreach ( $data_attrs as $key => $value ) {
    $safe_key = sanitize_key( (string) $key );
    if ( '' === $safe_key ) {
        continue;
    }
    $data_attr_str .= ' data-' . $safe_key . '="' . esc_attr( (string) $value ) . '"';
}
```

Output: `<article ... <?php echo $alpine_attrs . $data_attr_str; ?>>` (Zeile 128, mit `phpcs:ignore`-Kommentar und Begruendung).

### Threat-Modell

| Vektor | Mitigation | Status |
|--------|-----------|--------|
| HTML-Injection ueber Key (z.B. Key=`x" onerror="alert(1)`) | `sanitize_key` lowercased + entfernt alle Zeichen ausser `[a-z0-9_-]` | **Mitigated** - Quote-Breakout unmoeglich |
| HTML-Injection ueber Value (z.B. Value=`" onerror="...`) | `esc_attr` HTML-encoded `"`, `'`, `<`, `>`, `&` | **Mitigated** - Quote-Breakout unmoeglich |
| Leerer Key nach `sanitize_key` (z.B. Key=`"---"` oder Key=`"  "`) | `if ( '' === $safe_key ) { continue; }` | **Mitigated** - kein degeneriertes `data-=""` |
| Nicht-string Key (numerischer Index, Object, Array) | `(string)` Cast vor `sanitize_key` | **Mitigated** - keine TypeError-Exception |
| Nicht-string Value (Array/Object/Bool/Null) | `(string)` Cast vor `esc_attr` (Array wird `"Array"`, Object zu `__toString()` oder Notice) | **Defense-in-Depth ok** - keine XSS-Konsequenz, da `esc_attr` immer noch greift; Notice ist Info-Finding I-1 |
| Reserviertes Attribut (`data-` Praefix wird hardgecoded; kann Key `class` Override des Class-Attributes erzwingen?) | Nein - `sanitize_key` laesst nur `[a-z0-9_-]` durch, der hartkodierte `' data-'`-Praefix verhindert Kollision mit existierenden Attributen | **Mitigated** |
| Duplicate-Attribute (z.B. zweimal `data-foo`) | HTML5 spezifiziert: spaetere wins. Kein XSS, nur Logik-Quirk | **Acceptable** (nicht im Audit-Scope) |

### Findings

**I-1 (Info):** Wenn ein Caller versehentlich ein Array/Object als Value uebergibt, kommt es zu PHP-Notice (`Array to string conversion`) bzw. `__toString()` mit potenziellem unerwartetem Inhalt. KEIN Security-Issue (XSS bleibt durch `esc_attr` blockiert), aber Robustness-Schwaeche. Empfehlung als Folge-Issue: optionaler `is_scalar($value)`-Check mit `continue` bei non-scalar.

**Bewertung:** Die `data_attrs`-Erweiterung ist defensiv gebaut. Ein nicht-vertrauenswuerdiger Caller (z.B. ein zukuenftiger Shortcode-Handler mit `$_GET`-Input) kann weder Attribute-Breakout noch HTML-Injection erreichen.

---

## Section 2: Sub-Template-Include-Pattern

**Files:** `default.php`, `card.php`, `compact.php` in `public/views/services/maes/`

### Code-Audit

```php
$base_path = trailingslashit( DEUBNER_HP_SERVICES_PATH )
    . 'public/views/services/maes/';
// ...
include $base_path . 'videos.php';
include $base_path . 'aktuelles.php';
include $base_path . 'merkblaetter.php';
```

### Threat-Modell

| Vektor | Mitigation | Status |
|--------|-----------|--------|
| Local File Inclusion (LFI) via dynamischem Pfad | `$base_path` ausschliesslich aus `DEUBNER_HP_SERVICES_PATH` (Plugin-Konstante aus Main-File) - kein User-Input | **Mitigated** |
| Path Traversal via Sub-Template-Name | Sub-Template-Namen sind hartkodierte String-Literale (`'videos.php'` etc.) - keine Variable interpolation | **Mitigated** |
| Race Condition bei Theme-Override | Sub-Templates werden NICHT durch `dhps_template_fallbacks`/`get_stylesheet_directory` aufgeloest - sie sind explizit nur Plugin-intern | **Acceptable** (siehe Trust-Decision T-2 unten) |
| Variable-Pollution via `include`-Scope (`$news`, `$videos`, `$merkblaetter` werden von Outer-Scope ererbt) | Alle Variablen kommen aus `$data[...]` (Pipeline-Ouput, der seinerseits aus `DHPS_MAES_Parser::parse()` stammt) - keine ungetrustete Quelle | **Mitigated** (Trust-Boundary klar bei Parser) |
| `wp_unique_id()` Kollision bei Multi-Instance | WP-Core garantiert prozess-eindeutige IDs - kein Security-Issue | **Mitigated** |

**Bewertung:** Das Include-Pattern hat kein File-Inclusion-Risiko. Die Trust-Decision "Sub-Templates erben Scope vom Orchestrator" ist explizit gewollt (Performance + Einfachheit) und sicher, da die Daten-Quelle der Parser ist.

---

## Section 3: Trust-Boundary in Sub-Templates

### 3.1 videos.php / videos-card.php / videos-compact.php

| Output-Site | Escape-Funktion | Bewertung |
|-------------|-----------------|-----------|
| `$wrapper_classes` (root div) | `esc_attr` | OK |
| `admin_url( 'admin-ajax.php' )` | `esc_url` | OK |
| `wp_create_nonce( 'dhps_tp_nonce' )` | `esc_attr` | OK |
| `$video_mode`, `$service` ('maes' literal) | `esc_attr` | OK |
| `$lazy_count`, `$lazy_mode` | `esc_attr( (string) (int) ... )` und `esc_attr` | OK |
| `style_preset` (User-Param) | `sanitize_html_class` | OK |
| `$title`, `$slug`, `$poster` als ContentCard-Props | Werden in ContentCard durch `esc_html`/`esc_attr`/`esc_url` weiterverarbeitet | **Pass-through OK** |
| `video-slug`, `poster-url`, `v-modus`, `video-index` in `data_attrs` | sanitize_key + esc_attr (siehe Section 1) | OK |

### 3.2 merkblaetter.php / merkblaetter-card.php / merkblaetter-compact.php

| Output-Site | Escape-Funktion | Bewertung |
|-------------|-----------------|-----------|
| `$wrapper_class` (root div) | `esc_attr` | OK |
| `$pdf_href` (admin-ajax.php URL mit `http_build_query`) | Wird in ContentCard via `esc_url` weiterverarbeitet | **Pass-through OK** |
| `nonce`, `service`, `action` Query-Params | `http_build_query` URL-encoded zusaetzlich zum spaeteren `esc_url` | **Defense-in-Depth OK** |
| `pdf_params` aus Parser (`isset( $sheet['pdf_params'] ) && is_array(...)`) | Wird per `http_build_query` URL-encoded | OK |
| `(string) $sheet['title']`, `$sheet['description']` | Via ContentCard `esc_html` (title/teaser) | **Pass-through OK** |

### 3.3 aktuelles.php / aktuelles-card.php / aktuelles-compact.php

| Output-Site | Escape-Funktion | Bewertung |
|-------------|-----------------|-----------|
| `$wrapper_class` (root div) | `esc_attr` | OK |
| `$article['title']` | Via ContentCard `esc_html` | **Pass-through OK** |
| `$article['teaser']` | Via ContentCard `esc_html` | **Pass-through OK** |
| **`$article['body_html']`** | Via ContentCard `wp_kses_post` (Zeile 234/238 content-card.php) | **Security-Improvement vs. v0.13.x** - siehe T-1 |

### Findings

**I-2 (Info):** Vor v0.14.1 hat das alte MAES-Aktuelles-Template Body-HTML direkt aus dem Parser geechoed (mit minimalem KSES-Profil oder ungeschuetzt - je nach Layout). Die Migration durch ContentCard fuehrt nun **konsistent** `wp_kses_post` auf body_html aus, was die `<script>`/`onerror`-Vektoren gegen ein hypothetisch kompromittiertes API zuverlaessig blockt. **Diese Migration ist eine Security-Verbesserung.**

**I-3 (Info):** `mb_strimwidth`-Truncation wurde durch CSS `line-clamp` ersetzt. Dies ist kein Security-Issue, aber im Migration-Plan dokumentiert. Konsequenz: Der **gesamte** Teaser-Text wird im DOM ausgegeben, nicht eine PHP-getrimmte Variante. Visuelle Truncation greift im Browser. Bei besonders langem Teaser (>10 KB) waere DOM-Bloat moeglich - aber Parser cappt API-Daten implizit ueber DOM-Parsing.

**Bewertung:** Alle Sub-Templates verlassen sich konsequent auf ContentCard's Escape-Pipeline. Kein Output-Site umgeht das Component-System. Body-HTML aus untrusted API wird zuverlaessig durch `wp_kses_post` gefiltert.

---

## Section 4: dhps-tp.js Selector-Patch

**File:** `public/js/dhps-tp.js` (Zeilen 136-145)

### Aenderung

```javascript
// Vorher (v0.14.0):
var posterEl = playerContainer.querySelector( '.dhps-tp-video__poster' ) ||
    playerContainer.querySelector( '.dhps-tp-card__poster' ) ||
    poster;

// Nachher (v0.14.1):
var posterEl = playerContainer.querySelector( '.dhps-tp-video__poster' ) ||
    playerContainer.querySelector( '.dhps-tp-card__poster' ) ||
    playerContainer.querySelector( '.dhps-content-card__media' ) ||  // <- NEU
    poster;

if ( posterEl.classList.contains( 'dhps-tp-video__poster' ) ||
    posterEl.classList.contains( 'dhps-tp-card__poster' ) ||
    posterEl.classList.contains( 'dhps-content-card__media' ) ) {    // <- NEU
    posterEl.style.display = 'none';
}
```

### Threat-Modell

| Vektor | Bewertung |
|--------|-----------|
| Selector greift falsches Element (DOM-Pollution) | **Mitigated** - `.dhps-content-card__media` ist **plugin-eigene BEM-Klasse** und nur innerhalb von ContentCard im DOM. Keine Theme/Site verwendet diese Klasse. |
| OR-Kette: erstes Match gewinnt - bricht alte Layouts? | **Mitigated** - die alten Layouts (TP/LP) verwenden `.dhps-tp-video__poster` oder `.dhps-tp-card__poster`, die in der OR-Kette **vor** dem neuen Selektor stehen. Reihenfolge erhaelt Backward-Compat. |
| `style.display = 'none'` direkt im JS gesetzt | Nur auf das durch die OR-Kette gefundene Element. Kein XSS-Vektor. Element bleibt im DOM, kein detach. |
| Multi-Instance: 2 ContentCards im selben player-container | `querySelector` (nicht `All`) - nimmt das erste. Aber `playerContainer` ist der Card-Player-Scope. **Kein Cross-Card-Leakage.** |
| Modal-Modus (MAES default) ueberspringt diesen Code-Pfad | Korrekt - `openVideoModal()` nutzt den OR-Selektor gar nicht (siehe Zeile 122-126) | OK |

### Findings

**I-4 (Info):** Der `playerContainer.closest( '.dhps-content-card' )` wird **nicht** zusaetzlich behandelt fuer den `card.classList.add( 'dhps-tp--playing' )`-Block (Zeile 148-149). Konsequenz: Wenn MAES-Videos im **inline-Modus** statt modal abgespielt wuerden (was sie aktuell **nicht** sind - Wrapper hat `data-video-mode="modal"`), waere der Playing-State-Class-Switch ein No-Op fuer den ContentCard-Wrapper. KEIN Security-Issue, sondern Funktionsluecke - relevant erst, wenn MAES jemals auf Inline-Modus umgestellt wird. Empfehlung als Folge-Issue.

**Bewertung:** Der Selector-Patch ist minimal-invasiv, schlaegt die richtige Reihenfolge (alte Klassen first, neue Klasse als Fallback) und kann keine fremden DOM-Elemente treffen. Keine Security-Implikation.

---

## Section 5: CSS-Branding-Hooks

**File:** `css/dhps-components.css` (Zeilen 928-950)

### Selektoren

```css
.dhps-content-card--service-maes .dhps-content-card__play-overlay,
.dhps-content-card--service-maes .dhps-content-card__action--primary {
    color: var(--dhps-color-medizin);
}
.dhps-content-card--service-maes .dhps-content-card__action--primary:hover {
    color: var(--dhps-color-medizin);
    filter: brightness(0.85);
}
.dhps-content-card--service-maes .dhps-content-card__badge--top {
    background: var(--dhps-color-medizin-light);
    color: var(--dhps-color-medizin);
}
.dhps-content-card--service-lxmio .dhps-content-card__action--primary,
.dhps-content-card--service-lp .dhps-content-card__action--primary {
    color: var(--dhps-color-recht);
}
.dhps-content-card--service-lxmio .dhps-content-card__action--primary:hover,
.dhps-content-card--service-lp .dhps-content-card__action--primary:hover {
    color: var(--dhps-color-recht-hover);
}
```

### Bewertung

| Kriterium | Status |
|-----------|--------|
| Selektoren spezifisch genug (2-Klassen-Selector statt globaler Override) | **Pass** - alle Selektoren sind compound `.dhps-content-card--service-X .dhps-content-card__Y` (Specificity 0,2,0). Keine `*`-Wildcards. |
| Innerhalb `@layer dhps-components` (Zeile 27 oeffnet, Zeile 952 schliesst) | **Pass** - korrekte Cascade-Stufe. Theme-Overrides koennen ueber `@layer dhps-overrides` problemlos drueberschreiben. |
| Keine `!important` (ausser Skeleton an Zeile 908, nicht im Scope) | **Pass** |
| Brand-Tokens (`--dhps-color-medizin`, `--dhps-color-recht`) NICHT durch elementor-bridge.css gebridged (Trust-Decision v0.14.0) | **Confirmed** - `dhps-elementor-bridge.css` Zeile 17/40 dokumentiert explizit: "NICHT die Brand-Tokens --dhps-color-steuern/recht/medizin" |
| Override unbeteiligter Site-CSS? | **Nein** - `.dhps-content-card--service-X` Praefix wirkt ausschliesslich auf Plugin-eigene ContentCard-Instanzen. Themes ohne diese Klasse werden nicht beeinflusst. |
| Branding-Token-Switch (Zeile 568-576) als zusaetzlicher Hook | OK - setzt `--dhps-color-primary` lokal um, sodass alle Component-internen Verwendungen automatisch die Brand-Farbe nehmen. Saubere Token-Architektur. |

### Findings

Keine.

**Bewertung:** CSS-Hooks sind defensiv platziert (innerhalb `@layer dhps-components`), spezifisch genug (zweistufige Klassen), und beruehren keine Token-Bridge fuer Brand-Farben. Conformance zur v0.14.0-Architektur ist erhalten.

---

## Section 6: Section-Filter-Bugfix

**Files:** `default.php`, `card.php`, `compact.php` (Zeilen 38-47)

### Code-Audit

```php
$section          = sanitize_key( apply_filters( 'dhps_maes_section', 'all' ) );
$allowed_sections = array( 'all', 'videos', 'merkblaetter', 'aktuelles' );
if ( ! in_array( $section, $allowed_sections, true ) ) {
    $section = 'all';
}
```

### Bewertung

| Kriterium | Status |
|-----------|--------|
| `sanitize_key` VOR `in_array`-Check | **Pass** - korrekte Reihenfolge: erst normalisieren, dann gegen Whitelist pruefen |
| Default-Fallback bei nicht-erlaubter Section | **Pass** - `$section = 'all'` ist sichere Default-Wahl (zeigt alles, kein versehentliches Hide-by-Default-Vulnerability) |
| Strict-Compare in `in_array` | **Pass** - dritter Parameter `true` verhindert Type-Juggling |
| `'aktuelles'` in Whitelist | **Pass** - Bugfix dokumentiert in default.php Zeile 16-18 |
| Apply-Filter erlaubt externe Modifikation | **Acceptable** - dies ist WP-Standard-Pattern. Ein boesartiger Plugin/Theme koennte zwar `'all'` zu `'malicious'` aendern, aber Whitelist faengt das ab. |

### Findings

Keine.

**Bewertung:** Bugfix ist defensiv umgesetzt: Whitelist + Default-Fallback + Strict-Compare.

---

## Section 7: dhps-maes-aktuelles.js Cleanup

### Audit-Schritte

1. **File-Existenz pruefen:**
   - `Glob` auf `**/dhps-maes-aktuelles*` -> **0 Files gefunden** (geloescht).

2. **Enqueue-Block in Plugin-Main:**
   - `Grep` auf `dhps-maes-aktuelles-js` / `wp_enqueue_script` / `wp_register_script` mit `aktuelles` in `Deubner_HP_Services.php` -> **0 Treffer**.
   - Einziger Treffer "aktuelles" in der Main-Datei (Zeile 638) ist die Shortcode-Registrierung `maes_aktuelles` (vom JS-Handle abweichend).

3. **Template-Referenzen auf JS-Handle:**
   - `Grep` auf `dhps-maes-aktuelles` ueber alle `.php` Files -> nur 1 Treffer: `widgets/elementor/class-dhps-elementor-maes-widgets.php` Zeile 494 als **Elementor-Widget-Name** (`get_name(): string { return 'dhps-maes-aktuelles'; }`). Das ist die Widget-Slug-Identitaet, NICHT das JS-Handle. **Keine Auswirkung.**

4. **Doc-Referenzen:**
   - Verbleibende Referenzen in `docs/architecture/15-MAES-MIGRATION-PLAN-v0141.md` und `docs/project/09-CHANGELOG-v0131-QuickWins.md` sind historische Doku - kein laufender Code.

### Findings

Keine.

**Bewertung:** Cleanup vollstaendig. Kein dangling Reference, das einen fatalen `wp_enqueue_script` mit nicht-existentem Handle ausloesen wuerde. CSP-Benefit erreicht (kein inline-toggle-Script mehr).

---

## Section 8: ReDoS / Information Disclosure

| Kriterium | Status |
|-----------|--------|
| Neue `preg_*`-Patterns | **Keine** - kein Code-Pfad mit neuen Regex-Pattern. Migration nutzt ausschliesslich `sanitize_key`, `esc_attr`, `wp_kses_post`, `http_build_query`. |
| Stack-Traces in Fehlerantworten | **Keine** - Sub-Templates haben kein `try/catch`/`error_log`-Pfad. ContentCard `return`t still wenn `$title` leer (kein Output statt Fehler). |
| Verbose-Error in `data_attrs`-Loop | **Acceptable** - bei nicht-string Value wird PHP-Notice geworfen (Info-Finding I-1 oben), aber keine sensiblen Daten geleakt (Notice-Output haengt von `WP_DEBUG_DISPLAY` ab; in Production nicht angezeigt). |
| Cache-Poisoning ueber `wp_unique_id` | Nein - `wp_unique_id` ist prozesslokal und nicht persistiert | OK |
| Nonce-Lifetime aus AJAX-URL in HTML | Standard 24h - bekanntes Behavior, dokumentiert in v0.14.0-Audit | Acceptable |

### Findings

Keine.

---

## Section 9: Akzeptierte Trust-Decisions

| ID | Trust-Decision | Begruendung |
|----|----------------|-------------|
| **T-1** | ContentCard `wp_kses_post( $body_html )` ist ausreichend fuer untrusted Parser-Output | v0.14.0-Audit hat das Profil von `wp_kses_post` bewertet. `<script>`/Event-Handler werden gestrippt. MAES-Aktuelles profitiert nun von dieser Decision (Migration = Improvement). |
| **T-2** | ContentCard `data_attrs` per `sanitize_key` + `esc_attr` keine HTML-Injection-Vektoren | `sanitize_key` blockiert Spezialzeichen, `esc_attr` blockiert HTML in Values. Quote-Breakout doppelt unmoeglich. (Section 1 Beweis) |
| **T-3** | Sub-Template-Include via hartcodiertem Pfad (`include $base_path . 'videos.php'`) - kein File-Inclusion-Risiko | `$base_path` deriviert von Plugin-Konstante, Sub-Template-Namen sind String-Literale. Kein User-Input-Pfad. (Section 2 Beweis) |
| **T-4** | TP-JS-Selektor-Erweiterung (`.dhps-content-card__media`) trifft nur Plugin-eigene Elemente | BEM-Klasse ist plugin-exklusiv. Kein DOM-Cross-Bleed in fremde Themes/Sites. (Section 4 Beweis) |
| **T-5** | Brand-Tokens (`--dhps-color-medizin/recht`) bewusst NICHT durch elementor-bridge.css gebridged | v0.14.0 Trust-Decision dokumentiert in `dhps-elementor-bridge.css` Zeile 17/40. Verhindert ungewollte Brand-Vermischung in Elementor-Atomic-Settings. |

---

## Findings-Liste

| ID | Severity | Section | Beschreibung | Empfehlung |
|----|----------|---------|--------------|------------|
| I-1 | Info | Section 1 | Non-scalar `data_attrs`-Value loest PHP-Notice (Array-to-String), kein XSS | Folge-Issue: optionaler `is_scalar`-Check |
| I-2 | Info | Section 3 | `wp_kses_post`-Filterung auf MAES-Aktuelles body_html ist **Verbesserung** vs. v0.13.x | Keine Action - Improvement-Notiz |
| I-3 | Info | Section 3 | CSS `line-clamp` ersetzt PHP-Truncation, voller Teaser im DOM | Keine Action - in Migration-Plan dokumentiert |
| I-4 | Info | Section 4 | Playing-State-Class wird nicht auf `.dhps-content-card`-Wrapper gesetzt (Inline-Modus-Edge-Case) | Folge-Issue: nur relevant wenn MAES jemals inline statt modal abspielen soll |
| I-5 | Info | Section 8 | Doc-Referenzen auf `dhps-maes-aktuelles.js` sind historisch, kein laufender Code | Keine Action |

**Summe:** 0 Critical, 0 High, 0 Medium, 0 Low, 5 Info

---

## Final-Verdict

**GO** - Release v0.14.1 ist sicherheitsseitig freigegeben.

**Begruendung:**

1. Keine Critical/High/Medium/Low Findings.
2. Die `data_attrs`-Erweiterung in ContentCard ist defensiv gebaut: doppelte Sanitisierung (`sanitize_key` auf Key, `esc_attr` auf Value) macht HTML-Injection unmoeglich.
3. Sub-Template-Includes haben kein LFI-Risiko (hartcodierte Pfade).
4. Body-HTML-Filterung durch `wp_kses_post` ist konsistent ueber alle 9 modernisierten Templates.
5. CSS-Branding-Hooks sind in der korrekten `@layer`-Stufe und verwenden zweistufige Selektoren - keine Site-Wide-Override-Gefahr.
6. JS-Selector-Patch ist minimal-invasiv und greift nur plugin-eigene DOM-Klassen.
7. Cleanup von `dhps-maes-aktuelles.js` ist vollstaendig: 0 Code-Referenzen im aktiven PHP-Pfad. CSP-Compliance verbessert.
8. Section-Filter mit `aktuelles` in Whitelist (Bugfix) ist defensiv (sanitize_key + Whitelist + Default-Fallback + Strict-Compare).
9. 5 akzeptierte Trust-Decisions sind dokumentiert und auf v0.14.0-Vorbild-Audit zurueckfuehrbar.

Die Migration ist netto eine **Security-Verbesserung**: Konsolidierung der Output-Escape-Pipeline ueber ContentCard, Wegfall des Inline-Toggle-Scripts (CSP-Plus), und Trust-Boundary klar an der Parser-Grenze.

---

**Sign-off:** Security-Specialist (Claude Opus 4.7 / 1M, Anthropic)
**Folge-Releases im Scope:** v0.14.2 MIO/LXMIO, v0.14.3 TP/TPT/LP, v0.14.4 TC - jeweils analoge Audit-Tiefe empfohlen.
