# Security Audit v0.14.4 - TC Empty-State Migration

> Auditor:   Security-Specialist (parallel zur QA)
> Stand:     2026-05-24
> Scope:     3 TC-Templates (default/card/compact) - Migration EmptyState-Markup
>            auf dhps_component('empty-state', ...) Component-Deduplikation.
>            DHPS_TC_Parser unveraendert. Keine neuen Klassen, kein neues JS.
> Foundation: Component-System v0.14.0 (audit-zertifiziert), EmptyState-Component
>            (Audit Sektion 1.1 in v0.14.0, Status OK + Info-1).
> Pendant:   docs/project/20-SECURITY-AUDIT-v0143.md (vorheriger Audit, TP/TPT/LP).
>            docs/project/07-CHANGELOG-v0130-TC.md (urspruengliche TC-Trust-Decision).

---

## Executive Summary

Die Migration der drei TC-Templates auf die EmptyState-Component reduziert Markup-
Duplizierung (~180 B Netto-Einsparung) und vereinheitlicht den Leerzustand mit den
uebrigen Plugin-Services. Sicherheitsrelevante Aenderungen gibt es nicht:

- Alle Component-Props sind statische Konstanten oder i18n-Strings (kein User-Input).
- Die in v0.13.0 dokumentierte TC-Trust-Decision (`echo $tc_html` ohne wp_kses)
  bleibt vollstaendig erhalten, inklusive der drei `phpcs:ignore`-Marker.
- Die EmptyState-Component (audit-zertifiziert in v0.14.0) escapt alle uebergebenen
  Werte intern (`esc_html`/`esc_attr`/`esc_url`) und liefert nur statische Inline-SVGs
  aus einer hartkodierten Slug->SVG-Map.
- DHPS_TC_Parser ist nicht angefasst (war in v0.13.0 ge-auditet).

| Kategorie                   | Anzahl |
|-----------------------------|-------:|
| Critical                    | 0      |
| High                        | 0      |
| Medium                      | 0      |
| Low                         | 0      |
| Info                        | 1      |
| Trust-Decisions akzeptiert  | 3      |

**Verdict: GO**. Die Migration ist eine reine Deduplikation; Trust-Boundaries und
Escape-Disziplin sind identisch zu v0.13.0 bzw. werden von der bereits zertifizierten
Component getragen. Kein Release-Blocker, keine Pre-Release-Fixes erforderlich.

---

## Section 1: EmptyState-Component Trust-Boundary

**Files:**
- `public/views/services/tc/default.php` (Zeile 35-45)
- `public/views/services/tc/card.php` (Zeile 24-34)
- `public/views/services/tc/compact.php` (Zeile 25-35)
- `public/views/components/empty-state.php` (Component, Audit v0.14.0 OK)

### 1.1 Props-Quelle Analyse

Alle drei TC-Templates rufen `dhps_component('empty-state', ...)` mit exakt vier
Props auf:

| Prop      | Wert (default.php)                           | Quelle                  |
|-----------|----------------------------------------------|-------------------------|
| `icon`    | `'calculator'`                               | Statische Konstante     |
| `title`   | `__( 'Keine Steuer-Rechner verfuegbar', ...)`| i18n-String (statisch)  |
| `hint`    | `__( 'Pruefen Sie ...', ...)`                | i18n-String (statisch)  |
| `class`   | `'dhps-tc__empty'`                           | Statische Konstante     |

Compact-Variante unterscheidet sich nur in:
- `class`: `'dhps-tc__empty dhps-tc__empty--compact'` (statische Verkettung)
- `hint`:  kuerzere i18n-Konstante.

**Kein einziger Prop-Wert kommt aus `$_GET`, `$_POST`, dem Parser, der API oder
einem User-Setting.** Vollstaendig statische Werte.

### 1.2 Component-interne Escape-Disziplin (Bestaetigung aus v0.14.0-Audit)

`public/views/components/empty-state.php`:

| Ort                         | Schutz                                              |
|-----------------------------|-----------------------------------------------------|
| Root-Wrapper-Klassen (L64)  | `esc_attr( $root_classes )`                         |
| Icon-Container (L65)        | Internes SVG aus Slug-Map (vertrauenswuerdig)       |
| Title (L68)                 | `esc_html( $title )`                                |
| Hint (L72)                  | `esc_html( $hint )`                                 |
| Action-URL (L76)            | `esc_url( $action_url )` - hier nicht genutzt       |
| Action-Label (L77)          | `esc_html( $action_label )` - hier nicht genutzt    |

### 1.3 Calculator-Icon Verifikation

`empty-state.php` Zeile 46:

```
'calculator' => '<svg viewBox="0 0 24 24" width="48" height="48" fill="none"
                  stroke="currentColor" stroke-width="1.6" ...
                  <rect x="4" y="2" width="16" height="20" rx="2"/>
                  <line x1="8" y1="6" .../> ...
                  </svg>'
```

- Vollstaendig hartkodierte SVG-Konstante in PHP-Quelltext.
- Keine `data:`/`xlink:href`-URIs, keine `<script>`, keine `<foreignObject>`.
- Stroke/Fill nutzen `currentColor` -> reagiert auf CSS-Color, kein XSS-Vektor.
- Auswahl ueber `isset( $icon_map[ $icon ] )` -> Caller mit Slug `'calculator'`
  trifft den vertrauenswuerdigen Map-Branch (NICHT den `wp_kses_post`-Fallback).

**Verdict Sektion 1:** Alle vier Props statisch, Component eskaped intern,
Calculator-SVG hartkodiert. **OK, keine Findings.**

---

## Section 2: echo $tc_html Trust-Decision

### 2.1 Erhaltung des phpcs:ignore-Markers an allen drei Stellen

| Datei          | Zeile | Status            |
|----------------|-------|-------------------|
| `tc/default.php` | 50    | `phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML aus vertrauenswuerdiger Deubner-API mit Akkordeon-JS.` |
| `tc/card.php`    | 39    | `phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML aus vertrauenswuerdiger Deubner-API mit Akkordeon-JS.` |
| `tc/compact.php` | 40    | `phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML aus vertrauenswuerdiger Deubner-API.` |

**Alle drei `phpcs:ignore`-Marker erhalten.** Die Begruendungs-Kommentare sind
verkuerzt aber inhaltlich identisch zu v0.13.0.

### 2.2 Begruendung (uebernommen aus v0.13.0 CHANGELOG)

- HTML kommt von authentifiziertem Deubner-API-Endpoint
  (`einbau/taxcalc/...`, HTTPS, kdnr-Auth ueber `dhps_tc_kdnr`).
- Inline-JS (`test_einblenden`/`test_ausblenden`) ist funktionale Anforderung -
  ohne diese funktioniert das Akkordeon nicht.
- `wp_kses()` wuerde Event-Handler-Attribute (`onclick`, etc.) und
  `<script>`-Bloecke entfernen, was den Service zerstoeren wuerde.
- Trust-Boundary ist identisch zu den anderen Deubner-API-Services
  (MIO/MMB/TP/MAES/LP rendern via Parser-Pipeline ebenfalls API-Inhalte).

### 2.3 Verhaltens-Aequivalenz Vorher -> Nachher

Im Non-Empty-Pfad (`is_empty === false`) ist die Migration **byte-identisch zur
v0.13.0-Logik**:

```
<div class="dhps-tc__container[ --compact ]">
    <?php
    // phpcs:ignore ...
    echo $tc_html;
    ?>
</div>
```

Das Akkordeon-Markup-Verhalten ist unveraendert.

**Verdict Sektion 2:** Trust-Decision in v0.14.4 **UNVERAENDERT**. `phpcs:ignore`
an allen drei Stellen erhalten. Inline-JS-Pfad bytewise identisch.
**OK, keine Findings.**

---

## Section 3: CSP-Implikation

### 3.1 Inline-`<script>`-Block aus API-Response

Der von der Deubner-API gelieferte `$tc_html`-Block enthaelt nach v0.13.0-Doku
Inline-Funktionen (`test_einblenden`/`test_ausblenden`). Im gerenderten Frontend
ergibt das:

```html
<div class="dhps-tc__container">
    <!-- Tax-Calculator-Markup -->
    <script>function test_einblenden(id) { ... }</script>
    <!-- weiteres TC-Markup -->
</div>
```

### 3.2 CSP-Kompatibilitaet

Inline-`<script>`-Bloecke ohne Nonce/Hash brechen die in
`docs/architecture/14-CSP-COMPATIBILITY.md` empfohlene Direktive
`script-src 'self' 'unsafe-eval'`. Eine TC-aktivierte Seite benoetigt mindestens
eine der folgenden CSP-Anpassungen:

| Option              | Effekt                                                |
|---------------------|-------------------------------------------------------|
| `'unsafe-inline'`   | Erlaubt alle Inline-`<script>` (weichere Site-CSP).   |
| Per-Script Hash     | SHA256-Hash der API-Inhalte - **instabil**, weil API-Inhalt sich aendern kann. |
| Nonce-Injection     | Server-side Inject-Nonce - nicht implementierbar ohne API-Aenderung. |

Praktischer Ausweg: TC-Sites verzichten auf die strikte `script-src`-Direktive
ODER setzen `'unsafe-inline'` mit der Begruendung "vertrauenswuerdige
Same-Origin-API-Quelle".

### 3.3 Nichts in v0.14.4 verschaerft das Problem

Die Migration aendert weder den API-Aufruf noch den Output-Pfad fuer den
Non-Empty-Branch. Das Inline-`<script>`-Issue ist **identisch zu v0.13.0** und
kann nur durch eine API-seitige Aenderung (Deubner Verlag) behoben werden.

Dokumentation: `docs/architecture/14-CSP-COMPATIBILITY.md` adressiert das
Plugin-Standardverhalten; eine kurze Notiz zur TC-Spezifik koennte dort ergaenzt
werden (siehe Info-1 unten).

**Verdict Sektion 3:** Trust-Decision unveraendert akzeptabel. Limitierung
liegt auf API-Seite, nicht im Plugin-Code. **OK, 1 Info-Finding (Doku-Hint).**

---

## Section 4: BC-Klassen-Erhaltung

### 4.1 Erhaltene Klassen

| Klasse                     | Wo                                | Funktion         |
|----------------------------|-----------------------------------|------------------|
| `dhps-tc__empty`           | alle 3 Templates (class-Prop)     | CSS-Selektor-BC  |
| `dhps-tc__empty--compact`  | compact.php (class-Prop)          | Modifier-Hook    |

### 4.2 Entfernte Klassen (Tech-Debt fuer v0.14.5)

| Klasse                  | Status |
|-------------------------|--------|
| `dhps-tc__empty-icon`   | weg - Component nutzt `.dhps-empty-state__icon`   |
| `dhps-tc__empty-title`  | weg - Component nutzt `.dhps-empty-state__title`  |
| `dhps-tc__empty-text`   | weg - Component nutzt `.dhps-empty-state__hint`   |

### 4.3 Security-Impact

- BC-Klassen sind reine CSS-Selektor-Hooks ohne Verhalten.
- Werden ueber den `class`-Prop der Component eingehaengt; Component eskaped
  diese via `esc_attr( $root_classes )` (Zeile 64 in empty-state.php).
- Keine User-Daten in den Klassen-Strings (statische Konstanten).

**Verdict Sektion 4:** Keine Security-Implikationen. Reine CSS-BC.
**OK, keine Findings.**

---

## Section 5: Compact-UX-Aenderung

### 5.1 Was sich aendert

- **v0.13.0 compact.php:** Empty = einzelner `<p>`-Tag mit Hinweis-Text.
- **v0.14.4 compact.php:** Empty = volle EmptyState-Component mit Icon + h3-Title
  + p-Hint.

### 5.2 XSS-Vektor-Analyse

Wie in Sektion 1 dokumentiert: alle Props sind statische i18n-Strings
(`__( '...', 'wp-deubner-hp-services' )`). Selbst bei einer kompromittierten
.mo-Datei wuerden Translations ueber `__()` und dann erneut ueber `esc_html()`
in der Component laufen - das ist Defense-in-Depth-konform mit den anderen
Plugin-Templates.

### 5.3 CSS-Override-Risiko (Tech-Debt, nicht Security)

Themes, die in v0.13.0 die Klassen `.dhps-tc__empty-icon|title|text`
ueberschrieben haben, verlieren ihre Selektoren in v0.14.4. Das ist eine
**rein visuelle** Regression und wird als v0.14.5-Tech-Debt im Handover
adressiert (siehe `.specialist-TC-1-handover.md` Sektion 6).

**Kein Security-Issue.**

### 5.4 A11y-Hinweis Headings (aus Handover)

- v0.13.0 nutzte `<h4>` fuer den Empty-Title in TC.
- v0.14.4 nutzt die Component-default `<h3>`.
- Heading-Level-Shift `h4 -> h3` ist UX-/A11y-Mache, kein Security-Issue.

**Verdict Sektion 5:** Keine neuen XSS-Vektoren. UX-Verbesserung mit
dokumentiertem CSS-Override-Tech-Debt. **OK, keine Findings.**

---

## Section 6: Trust-Decisions akzeptiert

| ID  | Trust-Decision                                          | Quelle / Status                       |
|-----|---------------------------------------------------------|---------------------------------------|
| T-1 | `echo $tc_html` ohne wp_kses                            | v0.13.0-Decision, **unveraendert**    |
| T-2 | Inline-`<script>` aus API bricht strict-CSP             | v0.13.0-Decision, **unveraendert**    |
| T-3 | EmptyState-Component-Aufruf mit statischen Konstanten   | NEU - aequivalent zu v0.14.0 ContentList-Calls |

Alle drei Decisions sind sicherheitsfachlich vertretbar:

- T-1: API ist HTTPS + kdnr-authentifiziert; `wp_kses` wuerde das Akkordeon
  zerstoeren. Identisch zur Trust-Logik aller anderen Deubner-Services.
- T-2: Limitation liegt im API-Markup; siehe Section 3.
- T-3: Die Component-API wurde in v0.14.0 als sicher zertifiziert; statische
  Props eliminieren jeden User-Input-Pfad.

---

## Section 7: ReDoS / Information Disclosure

### 7.1 ReDoS / Regex-Patterns

- **Keine** neuen `preg_*`-Patterns in den 3 TC-Templates (durchgaengig nur
  `if`/`echo`).
- **Keine** Regex-Aenderungen in `empty-state.php` Component
  (war in v0.14.0 audited, regex-frei).
- DHPS_TC_Parser ist **unveraendert** - die in v0.13.0 ge-auditeten
  Patterns (3 Empty-State-Detection-Patterns, alle linear) bleiben gueltig.

**Status: OK, kein ReDoS-Risiko.**

### 7.2 Information Disclosure

- TC-Templates geben in **keinem** Pfad Stack-Traces, Pfade, oder Debug-Informationen
  aus.
- Bei nicht-registrierter Component liefert `dhps_component()` lediglich:
  - In Production: leerer String (silent fail).
  - In `WP_DEBUG`: HTML-Kommentar mit `esc_html`-geschuetztem Namen.
- DHPS_TC_Parser leakt keine internen Strukturen am Output.

**Status: OK, kein Information-Disclosure.**

### 7.3 function_exists-Check Defense-in-Depth

Alle drei Templates wrappen den Component-Aufruf in
`if ( function_exists( 'dhps_component' ) )`. Bei (unrealistischem) Fehlen des
Helpers wuerde **gar nichts** ausgegeben - kein Fatal Error, kein Output-Leak.

**Status: OK (positive Praxis).**

---

## Findings-Liste

### Critical / High / Medium / Low

**Keine.**

### Info

#### I-1: CSP-Doku koennte TC-Spezifik explizit nennen

- **File:** `docs/architecture/14-CSP-COMPATIBILITY.md`
- **Beschreibung:** Die Doku diskutiert `script-src 'unsafe-eval'` (Alpine) und
  `style-src 'unsafe-inline'` (Alpine-Inline-Styles + Plugin-Inline-Styles).
  TC-spezifisches Inline-`<script>` aus der API wird nicht ausdruecklich
  adressiert. Sites, die TC nutzen, koennten ueber den Bedarf an
  `script-src 'unsafe-inline'` oder einer Hash-Strategie stolpern.
- **Empfehlung:** In v0.14.5-Doku-Update einen Absatz "TC-Service-Spezifik"
  ergaenzen (rein dokumentarisch).
- **Severity: Info** (Doku-Hint, kein Code-Aenderungsbedarf).

---

## Akzeptierte Trust-Decisions (Zusammenfassung)

Diese Decisions wurden bewusst getroffen und sind sicherheitsfachlich
vertretbar:

1. **echo $tc_html ohne wp_kses** (T-1) - Vertrauenswuerdige Deubner-API,
   funktionale JS-Abhaengigkeit, identisches Trust-Modell zu allen anderen
   Service-Parsern. v0.13.0-Decision unveraendert.

2. **Inline-`<script>` aus API bricht strict-script-src CSP** (T-2) - Limitation
   der API-Architektur, nicht durch das Plugin loesbar. v0.13.0-Decision
   unveraendert.

3. **EmptyState-Component-Calls mit statischen Konstanten** (T-3) - Component
   ist seit v0.14.0 audit-zertifiziert; alle vier Props in den TC-Templates
   sind statische i18n-Konstanten ohne User-Input-Pfad.

---

## Verdict

**GO** (ohne Pre-Release-Fixes).

- **0 Critical / 0 High / 0 Medium / 0 Low Findings**
- **1 Info-Finding** (rein dokumentarisch, v0.14.5-Backlog)
- **3 Trust-Decisions** (zwei unveraendert aus v0.13.0, eine analog v0.14.0)

Die Migration ist eine reine Deduplikation: keine neuen Datenflusspfade, keine
neuen Trust-Boundaries, keine neuen Klassen, kein neues JavaScript. Die
sicherheitsrelevante TC-Trust-Decision (`echo $tc_html`) ist bytewise erhalten
inklusive aller drei `phpcs:ignore`-Marker. Die EmptyState-Component wurde
bereits in v0.14.0 zertifiziert und wird hier ausschliesslich mit statischen
Konstanten gespeist.

**Empfehlung: v0.14.4 taggen.**

---

*Audit beendet 2026-05-24.*
