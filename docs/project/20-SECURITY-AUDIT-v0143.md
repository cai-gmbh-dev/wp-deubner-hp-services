# Security Audit v0.14.3 - TP + TPT + LP Migration

> Auditor:   Security-Specialist (parallel zur QA)
> Stand:     2026-05-23
> Scope:     2 TP-Templates, 3 TPT-Templates, dhps-tp.js Selektor-Erweiterung,
>            dhps-frontend.css LP-Wrapper-Token-Switch,
>            dhps-components.css LP- + TP-Play-Overlay-Hooks
> Foundation: Component-System v0.14.0, ContentCard data_attrs v0.14.1 (audit-zertifiziert),
>            Service-Branding-Token-Switch v0.14.2
> Pendant: docs/project/17-SECURITY-AUDIT-v0142.md (vorheriger Audit, MAES-Modular)

---

## Executive Summary

Die Migration der TP- und TPT-Templates auf das ContentList/ContentCard-Komponenten-
System fuehrt **kein neues Critical- oder High-Severity-Risiko** ein. Das Audit
konzentriert sich auf das mutmasslich riskanteste Aenderungs-Cluster:

- ContentCard `data_attrs` mit ungetrusted Werten aus dem Parser
  (`video-slug`, `poster-url`, `v-modus`, `video-index`, `category`, `video-id`).
- dhps-tp.js Selektor-Erweiterung `.dhps-service--tp, .dhps-service--lp` an 10 Stellen.
- CSS-Branding-Hooks (Spezifitaet, Token-Switch, Theme-Vertraeglichkeit).
- Empty-State-Komponente in TPT (statische Strings).
- TPT-Template-Architektur-Bruch durch direkte `get_option`-Reads (Tech-Debt).
- `tp/compact.php` bewusst unveraendert (initCompactAccordion-JS-Risiko).

**Verdict: GO**. Keine Aenderungs-Anforderungen, keine geforderten Trust-Decisions
die nicht bereits in den Handovern dokumentiert sind. Die Migration uebernimmt die
Schutzwirkung des v0.14.1-Audits fuer `data_attrs` 1:1 und ergaenzt sie um saubere
ContentCard-Klassen-Hygiene.

| Kategorie | Anzahl |
|---|---:|
| Critical | 0 |
| High | 0 |
| Medium | 0 |
| Low | 2 (kosmetisch / Tech-Debt-Hinweise) |
| Trust-Decisions akzeptiert | 4 |

---

## Section 1: ContentCard `data_attrs` Security in Video-Migration

### Befund
TP/TPT-Templates uebergeben Werte aus dem Parser-Output an die ContentCard-Prop
`data_attrs`. Folgende Schluessel werden befuellt:

| Schluessel | TP default | TP card | TPT default/card/compact | Quelle |
|---|:---:|:---:|:---:|---|
| `video-slug` | x | x | x | Parser `video_slug` (String, Domaenen-Slug) |
| `poster-url` | x | x | x | Parser `poster_url` (HTTPS-URL, mandantenvideo.de) |
| `v-modus` | x | x | x | Parser `v_modus` (Numeric-String, default `'0'`) |
| `video-index` | x | x | - | Template-erzeugt (`(string) $video_index`) |
| `category` | x | x | - | Template-erzeugt (`(string) $cat_index` bzw. Featured-Marker) |
| `video-id` | x | x | - | Parser `video_id` |

### Schutzkette (ContentCard intern, Quelle: `public/views/components/content-card.php` Z. 119-126)
```php
foreach ( $data_attrs as $key => $value ) {
    $safe_key = sanitize_key( (string) $key );
    if ( '' === $safe_key ) { continue; }
    $data_attr_str .= ' data-' . $safe_key . '="' . esc_attr( (string) $value ) . '"';
}
```
- `sanitize_key` filtert Keys auf `[a-z0-9_\-]` (Bindestrich erlaubt) - keine
  HTML-Attribut-Injection moeglich.
- `esc_attr` HTML-encodet Werte - `"`-Breakout unmoeglich.

### Pruefung
- Templates casten alle Werte sauber via `(string)` und `isset`-Guards vor
  Uebergabe an `data_attrs` (Defense-in-Depth, obwohl ContentCard ohnehin
  cast + escape).
- Pflichtfelder (`slug`, `titel`) werden explizit per `'' === $slug || '' === $titel`-
  Continue ausgesondert -> keine leere Card.
- `video-id` ist neu in `data_attrs` (vorher als separates `data-video-id`).
  Wert kommt aus dem Parser, wird per `esc_attr` escaped. **Kein zusaetzliches
  Validierungs-Bedarf** - Server-Roundtrip per AJAX-Proxy nutzt die `video-id`
  nicht, sie ist rein clientseitige Markierung.
- `category` ist ein Integer-String (`(string) $cat_index`) oder das Literal
  `'featured'` (in card.php). Kein User-Input -> sicher.
- `video-index` ist `(string) $video_index` (selbst-inkrementiert) -> sicher.

### Severity
**Keine.** Die Schutzkette ist identisch zur in v0.14.1 audit-zertifizierten
MAES-Migration. Die Migration uebernimmt das Pattern 1:1.

---

## Section 2: dhps-tp.js Selektor-Erweiterung

### Aenderung (10 Stellen)
`'.dhps-service--tp'` -> `'.dhps-service--tp, .dhps-service--lp'`.

Betroffene Stellen (laut Grep, alle in `public/js/dhps-tp.js`):
1. Z. 26 - `init()`-`querySelectorAll`.
2. Z. 59 - `initLazyVideoLoading()`-Modal-Check.
3. Z. 102 - `loadVideoIframe()`-Service-Container-Suche.
4. (Plus 7 weitere JSDoc-Verweise auf `.dhps-service--tp`-Container in Param-
   Beschreibungen - rein dokumentarisch, keine Funktions-Aenderung.)

### Pruefung
- **Kein XSS-Vektor**: reiner JS-Selektor-Aenderung, kein DOM-Inject, kein
  `innerHTML`-Bezug.
- **Edge-Case "beide Klassen am gleichen Element"**: `querySelectorAll`
  liefert das Element nur einmal (Mengen-Semantik per Spec) - `forEach` laeuft
  nicht doppelt. **Safe**.
- **closest()-Verhalten**: findet jetzt auch `.dhps-service--lp`-Wrapper. Der
  AJAX-Aufruf nutzt das `data-service`-Attribut vom Wrapper (`taxplain` bzw.
  `lexplain`). Das ist eine **Whitelist im PHP-Backend** (siehe `handle_tp_video_src`
  - audit-zertifiziert seit v0.11.0). Keine neue Angriffsflaeche.
- **CSRF-Schutz**: Nonce kommt vom Wrapper-`data-nonce` (`dhps_tp_nonce`). LP-
  Template (tp/default.php) erzeugt dieselbe Nonce -> serverseitige Verifikation
  ist transparent.
- **Document-Weit-Scoping**: keine `document.querySelector`-Calls neu eingefuehrt.
  `serviceContainer = poster.closest(...)` ist auf den DOM-Aufstieg vom Klick-
  Target beschraenkt. Keine Cross-Service-Pollution.

### Severity
**Keine.** Selektor-Erweiterung ist additiv und semantisch identisch zur
Pre-Migration-Behavior (LP wurde bisher per Template-Fallback bereits mit
`dhps-service--tp`-Wrapper-Klasse gerendert? **Bestaetigt nicht durch das
Audit** - die Pipeline setzt `$service_class` und das Template uebernimmt es;
LP haette also keinen `.dhps-service--tp`-Wrapper gehabt, sondern nur
`.dhps-service--lp`. Vor v0.14.3 lief LP-Click vermutlich nicht durch
dhps-tp.js. Mit der jetzigen Aenderung **erstmals funktional**.)

---

## Section 3: CSS-Branding-Hooks Security

### Neue Selektoren in `css/dhps-components.css`
```css
/* Z. 952-958: LP-Play-Overlay + LP-Badge (analog MAES). */
.dhps-content-card--service-lp .dhps-content-card__play-overlay { color: var(--dhps-color-recht); }
.dhps-content-card--service-lp .dhps-content-card__badge--top   { background: var(--dhps-color-recht-light); color: var(--dhps-color-recht); }

/* Z. 961-964: TP-Play-Overlay (Steuern-Gruen-Akzent). */
.dhps-content-card--service-tp .dhps-content-card__play-overlay { color: var(--dhps-color-steuern); }
```

### Neue Selektoren in `css/dhps-frontend.css`
```css
/* Z. 1897-1901: LP-Wrapper-Token-Switch (analog LXMIO). */
.dhps-service--lxmio,
.dhps-service--lp {
    --dhps-color-primary:       var(--dhps-color-recht, #0054A6);
    --dhps-color-primary-hover: var(--dhps-color-recht-hover, #003A73);
}
```

### Pruefung
- **Spezifitaets-Hijacking**: nicht moeglich. Selektoren sind klar gescoped
  auf `.dhps-content-card--service-*` (Klassen-Spezifitaet `0,2,0`) bzw.
  `.dhps-service--lp` (`0,1,0`). Theme-Custom-CSS mit hoeherer Spezifitaet
  oder `!important` ueberschreibt weiterhin.
- **CSS-Variablen-Konflikte**: `--dhps-color-recht-light` existiert in
  `css/dhps-design-tokens.css` (Z. 30, `#e8f0f8`) - keine `undefined`-Var.
- **Theme-Override-Bruch**: Das Token-Switch-Pattern ist **additiv**.
  Bestehende Themes, die `.dhps-service--lp` selber stylen, behalten Vorrang
  (gleiche Spezifitaet, Source-Order entscheidet, Plugin laedt vor Theme).
  Sollte ein Theme `--dhps-color-primary` global ueberschreiben, gewinnt
  die naehere Scope-Definition (CSS-Var-Cascade). **Akzeptabel**.
- **Brechen User-Theme-Customizings**: nur, wenn das Theme aktuell auf
  `.dhps-service--lp` als Wrapper-Klasse keine Primary-Token-Bindung erwartet
  hat. Wird im Changelog dokumentiert (Sektion 5 unten).

### Severity
**Keine.** Standard-CSS-Cascade, kein Security-Impact.

---

## Section 4: Empty-State Security (TPT)

### Befund
Wenn `$video === null` oder leer: alle 3 TPT-Templates rufen
`dhps_component( 'empty-state', array(...) )` auf mit statischen, ueber `__()`
i18n-uebersetzten Strings:

```php
echo dhps_component( 'empty-state', array(
    'icon'  => 'video',
    'title' => __( 'Kein Teaser-Video verfuegbar', 'wp-deubner-hp-services' ),
    'hint'  => __( 'Bitte spaeter erneut pruefen oder Lizenz/Auth-Daten kontrollieren.', 'wp-deubner-hp-services' ),
) );
```

### Schutzkette (EmptyState-Component, Quelle: `public/views/components/empty-state.php`)
- `title` -> `esc_html` (Z. 68).
- `hint` -> `esc_html` (Z. 72).
- `icon` als Slug -> Mapping-Tabelle `$icon_map['video']` -> internes SVG
  (vertrauenswuerdige Quelle, Z. 51-60).
- Kein User-Input, keine `get_option`/Datenbank-Reads im Empty-State-Pfad.

### Severity
**Keine.** Statische uebersetzte Strings, vollstaendig escaped.

---

## Section 5: Theme-Override Migration

### Befund
Die WordPress-Filter `dhps_template_paths` / `dhps_template_fallbacks` bleiben
unveraendert. Theme-Override-Pfade `{theme}/dhps/services/tp/default.php` etc.
funktionieren weiterhin (Plugin-Default kommt nur, wenn Theme keine Override-
Datei hat).

### Aenderungen mit Theme-Impact
- Markup in TP/TPT-Templates ist substantiell anders (ContentCard-BEM statt
  Custom-Markup).
- CSS-Klassen-Migration:
  - alte `.dhps-tp-card__poster`/`__img`/`__title`/`__teaser`/`__play-btn`/`__body`
    werden in den Plugin-Defaults nicht mehr gerendert.
  - Root-Klasse `.dhps-tp-card` bleibt erhalten (Zusatz-Klasse fuer TP-JS-Selektor)
    -> Theme-CSS auf `.dhps-tp-card .dhps-content-card__*` funktioniert.
  - `.dhps-tpt-card`-Root bleibt; `__heading` (h3 vor Card) bleibt; `__body/__title/__teaser/__date`
    entfaellt.

### Pflicht: CHANGELOG-Doku
- `19-CHANGELOG-v0143.md` (vom Lead zu erzeugen) muss den BC-Hinweis enthalten:
  "Theme-Overrides unter `{theme}/dhps/services/{tp,tpt}/*.php` sind nach v0.14.3
  inkompatibel mit dem neuen Default-Markup. Theme-CSS auf
  `.dhps-tp-card__*`-Selektoren muss auf `.dhps-content-card__*` migriert werden."
- `tp/compact.php` als **explizit ausgenommen** dokumentieren.

### Severity
**Low** (kosmetisch, kein Security-Impact, nur Theme-DX). Dokumentationspflicht
erfuellt durch Migration-Plan + Specialist-Handovers, finale CHANGELOG-Sektion
liegt beim Lead.

---

## Section 6: tp/compact.php Risiko-Bewertung

### Befund
`tp/compact.php` wurde bewusst NICHT migriert.

### Begruendung (aus Migration-Plan Sektion 7.2)
- `initCompactAccordion` in `dhps-tp.js` (Z. 378-444) spawnt einen Player
  **dynamisch** unter dem Item via `document.createElement` + `innerHTML`.
- Das DOM-Template fuer den dynamisch eingefuegten Player nutzt
  `dhps-tp-video__player` / `dhps-tp-video__poster` (Legacy-Klassen).
- Eine ContentCard-Migration des Compact-Layouts wuerde **mindestens** den
  JS-Spawn-Code refaktoren muessen.

### Security-Implikation
- `tp/compact.php` nutzt weiterhin Legacy-Markup mit Inline-SVG (Z. 44, 60-62).
  **Keine Inline-CSS-Styles** (geprueft per Grep) -> CSP-Konformitaet bleibt
  erhalten.
- Daten werden weiterhin per `esc_attr`/`esc_html`/`esc_url` escaped (Z. 56-66).
- `escapeAttr()`-Helper im JS escaped slug/poster/v-modus vor `innerHTML`-
  Inject (Z. 686-693). **Vermeidet XSS** im dynamisch erzeugten Player-Stub.

### Trust-Decision
**Akzeptabel.** Compact-Layout funktioniert sicher mit Legacy-Markup. Folge-
Iteration (v0.14.5?) sollte die ContentCard-Migration zusammen mit dem
JS-Refactor angehen.

### Severity
**Low** (Tech-Debt, kein Security-Impact).

---

## Section 7: TPT get_option-Reads

### Befund
Alle 3 TPT-Templates lesen weiterhin direkt:
```php
$ueberschrift = (string) get_option( 'dhps_tpt_ues', '' );
$teasertext   = (string) get_option( 'dhps_tpt_teasertext', '' );
```

### Security-Implikation
- `get_option()` ist eine sichere WP-API (cached, sanitized beim Schreiben
  durch Admin-Registrierung).
- Werte werden per `esc_html` (in der `<h3>`-Ausgabe Z. 82) bzw. an die
  ContentCard `teaser`-Prop uebergeben (die intern per `esc_html` rendert,
  siehe Z. 192 content-card.php).
- Keine Code-Execution moeglich, keine SQL-Injection (`get_option` nutzt
  Prepared Statements intern).

### Architektur-Bruch
- DI-Verletzung: Template kennt die Option-Schluessel direkt.
- Saubere Loesung waere ein `DHPS_TPT_Modules`-Layer oder
  `$data['tpt_admin_texts']` aus der Pipeline.

### Trust-Decision
**Akzeptabel** als Tech-Debt fuer v0.14.3. Risiko-Bewertung niedriger als
parallele Pipeline-Aenderungen waehrend einer Migration. Folge-Ticket fuer
v0.14.4+ empfohlen.

### Severity
**Low** (Tech-Debt, kein Security-Impact - `get_option` + `esc_html` ist
WP-konform).

---

## Section 8: Trust-Decisions Liste

| # | Trust-Decision | Begruendung | Risiko |
|---|---|---|---|
| TD-1 | `dhps-tp.js` bleibt Vanilla (kein Component-System-Refactor) | Hybrid-Strategie analog MAES v0.14.1, vermeidet 695 LOC JS-Refactor in einer Template-Migration | Niedrig - Selektor-Vertrag bleibt stabil |
| TD-2 | `tp/compact.php` unveraendert | `initCompactAccordion` spawnt Player dynamisch; ContentCard hat dafuer kein Aequivalent | Niedrig - Compact-Layout funktioniert weiter, kein neuer Inline-Style |
| TD-3 | TPT `get_option`-Reads bleiben im Template | Pipeline-Aenderungen waehrend Migration risikoerhoehend; Werte werden korrekt escaped | Niedrig - WP-API sicher, esc_html greift |
| TD-4 | LP erbt automatisch via Template-Fallback (kein eigenes LP-Verzeichnis) | Service-Branding ueber `card_service`-Variable + CSS-Hooks; weniger Code-Duplikation | Niedrig - LP-Branding bestaetigt durch CSS-Grep |

ContentCard `data_attrs` ist **seit v0.14.1 audit-zertifiziert** (siehe
14-SECURITY-AUDIT-v0141.md S-3). Diese Migration nutzt die zertifizierte
Schutzkette ohne Aenderung.

---

## Section 9: ReDoS / Information Disclosure

### Pruefung
- **Keine neuen `preg_*`-Patterns** in den migrierten Templates oder Components.
  Grep auf Migration-Files bestaetigt: kein `preg_match`/`preg_replace`.
- **Empty-State-Fallback** leakt keine sensitive Information:
  - Hinweis-Text "Bitte spaeter erneut pruefen oder Lizenz/Auth-Daten kontrollieren"
    nennt keine konkreten Option-Keys, keine Pfade, keine Stacktraces.
  - `icon='video'` ist statischer Slug.
- **AJAX-Fehler-Pfad** in dhps-tp.js (`.catch( function () { /* still */ } )`,
  Z. 188-190): Poster bleibt sichtbar, keine Fehlermeldung an User. **Keine
  Information Disclosure** ueber Backend-Status.

### Severity
**Keine.**

---

## Section 10: Backward Compatibility - CSP

### Befund
Pruefung auf Inline-Styles in TP/TPT-Templates:

```
Grep 'style=' in public/views/services/tp/:
  default.php:159: data-style="..."   (HTML data-Attribut, NICHT inline CSS)
  card.php:172:    data-style="..."   (HTML data-Attribut, NICHT inline CSS)

Grep 'style=' in public/views/services/tpt/:
  (keine Treffer)
```

**Inline-Style auf Play-Button ist vollstaendig entfernt** (Migration vom
UI-Audit Finding F5 erfolgreich umgesetzt). Branding kommt jetzt ueber:
- `.dhps-content-card--service-tp .dhps-content-card__play-overlay { color: var(--dhps-color-steuern); }`
- `.dhps-content-card--service-lp .dhps-content-card__play-overlay { color: var(--dhps-color-recht); }`

### CSP-Status
- **`style-src 'self'`** ohne `'unsafe-inline'`: KONFORM (kein Inline-CSS-Style mehr in TP/TPT).
- LazyImage-Component nutzt nur `style=` fuer LQIP, das aber **per Prop opt-in**
  ist - die TP/TPT-Templates uebergeben kein `lqip` -> kein Inline-Style.
- Wrapper-`data-*`-Attribute (`data-ajax-url`, `data-nonce`, etc.) sind keine
  Inline-Styles und nicht CSP-relevant.

### Resterisiko
- `tp/compact.php` ist unveraendert; pruefe per Grep auf Inline-Style:
  **kein `style=`-Treffer** in `tp/compact.php` -> CSP-konform.
- JS-erzeugte Elemente (`iframe.style.border = '0'` in Z. 138, `card.style.display`
  in Z. 149/173/361/364/494/521 etc.) - das ist **CSSOM-Manipulation, nicht Inline-
  Style-Injection**. CSP `style-src` greift NICHT auf CSSOM-Property-Sets per JS.
  **Akzeptabel** (Standard-Browser-Verhalten).

### Severity
**Keine.** CSP-Fix erfolgreich umgesetzt.

---

## Final-Findings Liste

| # | Severity | Sektion | Beschreibung | Empfehlung |
|---|---|---|---|---|
| F-1 | Low | 5 | CHANGELOG muss Theme-Override-Migration dokumentieren | Lead-Composition |
| F-2 | Low | 6/7 | Tech-Debt-Tickets fuer tp/compact + TPT-Modules anlegen | Folge-Iteration v0.14.4+ |

---

## Verdict

**GO**.

Begruendung:
- 0 Critical, 0 High, 0 Medium - nur 2 Low-Findings (kosmetisch / Tech-Debt).
- ContentCard `data_attrs` ist audit-zertifiziert (v0.14.1).
- Selektor-Erweiterung in dhps-tp.js ist additiv, safe, kein neuer XSS-Vektor.
- CSP-Inline-Style-Bruch ist vollstaendig behoben.
- LP-Inheritance via Template-Fallback + dynamisches `card_service` ist
  konzeptionell sauber gelost (kein dupliziertes LP-Verzeichnis).
- Alle 4 Trust-Decisions sind dokumentiert und vom Specialist-Team explizit
  begruendet.

### Action-Items fuer Lead

1. CHANGELOG-Sektion "Theme-Override Migration" ergaenzen (Section 5).
2. Tech-Debt-Tickets anlegen:
   - `tp/compact.php` ContentCard-Migration (zusammen mit JS-Spawn-Refactor).
   - TPT-Modules-Layer fuer `dhps_tpt_ues`/`dhps_tpt_teasertext` (Pipeline-Anreicherung).
3. QA-Pflicht-Tests:
   - `[lp]` Demo-Page: Wrapper `.dhps-service--lp` UND `data-service="lexplain"`?
   - Click auf LP-Card-Poster: AJAX `action=dhps_tp_video_src&service=lexplain` im Network-Tab?
   - Play-Overlay-Farbe: TP Steuern-Gruen, LP Recht-Blau, MAES Medizin-Teal.
   - `[tpt]` ohne OTA: EmptyState sichtbar statt stummes return?
4. Bytes-Smoke-Tests (Discovery prognostiziert +50 bis +120% bei TP-Default mit
   60 Videos) durchfuehren und in CHANGELOG dokumentieren.

### Reference-Audits
- v0.14.0 (Component-System-Foundation): `docs/project/11-SECURITY-AUDIT-v0140.md`
- v0.14.1 (MAES-Videos + ContentCard `data_attrs`): `docs/project/14-SECURITY-AUDIT-v0141.md`
- v0.14.2 (MAES-Modular): `docs/project/17-SECURITY-AUDIT-v0142.md`
