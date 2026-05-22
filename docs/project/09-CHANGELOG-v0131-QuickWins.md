# Changelog v0.13.1 - Quick-Wins (A11y + CSP + Feature-Parity)

## Stand: 2026-05-22

## Zweck

Patch-Release mit drei isolierten Verbesserungen aus dem UI/UX-Audit
zu v0.14.0. Werden vorab gemerged, damit Live-Sites sofort profitieren
und v0.14.0 sich auf die Architektur-Foundation konzentrieren kann.

## 1. QW1 - A11y-Baseline plugin-eigen definieren

**Problem (UI/UX-Audit Finding 3.4)**: Das Plugin verwendet `.screen-reader-text`
in Templates, definiert die Klasse aber selbst nicht. Bei minimalistischen
Themes (ohne WP-Core-Styles) werden alle Screen-Reader-Labels visuell
sichtbar. Zusaetzlich fehlten `:focus-visible` und `prefers-reduced-motion`
auf Plugin-Ebene.

**Loesung**: Neuer A11y-Baseline-Block am Anfang von
[css/dhps-frontend.css](../../css/dhps-frontend.css), gescoped auf
`.dhps-service`:

- `.screen-reader-text` + `.dhps-sr-only` (visually-hidden mit Focus-Reveal)
- `:focus-visible` Outline mit `--dhps-color-primary` fuer alle
  interaktiven Elemente (a, button, input, select, textarea, [tabindex])
- `@media (prefers-reduced-motion: reduce)` deaktiviert Animationen,
  Transitions und Smooth-Scroll

**Aufwand**: ~70 Zeilen CSS, kein BC-Bruch.

## 2. QW2 - MAES-Aktuelles Inline-Script auslagern

**Problem (Audit Finding 3.5)**: Das Template
[maes/aktuelles.php](../../public/views/services/maes/aktuelles.php)
enthielt am Ende einen `<script>`-Block mit minified Vanilla-JS fuer das
Akkordeon-Toggle. Bricht CSP `'script-src self'`, blockt Browser-Caching,
schwer testbar.

**Loesung**:
- Neue Datei [public/js/dhps-maes-aktuelles.js](../../public/js/dhps-maes-aktuelles.js)
  (2.1 KB, IIFE, kein jQuery, ARIA-konform)
- Registrierung in [Deubner_HP_Services.php](../../Deubner_HP_Services.php)
  als `dhps-maes-aktuelles-js`
- Conditional Enqueue im Template via `wp_enqueue_script()`
- Idempotenz-Marker `data-dhps-maes-aktuelles-bound` verhindert
  Doppel-Bindung bei Mehrfach-Initialisierung

**Aufwand**: 1 neue JS-Datei + 2 Edits (Template + Plugin-Main).

## 3. QW3 - MMB-Default Filter-Bar nachruesten

**Problem (Audit Finding 2.2)**: Die `dhps-filter-bar` ist in
[mmb/compact.php](../../public/views/services/mmb/compact.php) und
[mmb/card.php](../../public/views/services/mmb/card.php) implementiert,
aber im Default-Layout
[mmb/default.php](../../public/views/services/mmb/default.php)
fehlte sie. Inkonsistente Feature-Parity zwischen den Layouts. Das
JavaScript `dhps-mmb.js` ist bereits darauf vorbereitet, blieb aber
ohne Markup wirkungslos.

**Loesung**:
- Filter-Bar-Markup analog zu compact.php nach der Such-Sektion
- `data-category` Attribut auf `.dhps-mmb-category` ergaenzt
- Condition `count( $categories ) > 1` (kein Filter wenn nur 1 Kategorie)

**Aufwand**: ~18 Zeilen HTML/PHP, kein BC-Bruch.

## QA-Ergebnisse

```
=== DHPS v0.13.1 SMOKE ===
Plugin-Version: 0.13.1
----------------------------------------------------------------------
QW1 .screen-reader-text Klasse definiert: YES
QW1 :focus-visible Baseline: YES
QW1 prefers-reduced-motion: YES
QW2 Inline-Script entfernt aus aktuelles.php: YES
QW2 dhps-maes-aktuelles.js existiert: YES (2124 bytes)
QW2 Enqueue-Call im Template: YES
QW3 MMB-Default Filter-Bar Markup: YES
QW3 data-category Attribut auf Kategorien: YES
----------------------------------------------------------------------
Shortcodes: 13 OK, 0 ERR
```

**Render-Bytes-Vergleich (vor / nach):**

| Shortcode | v0.13.0 | v0.13.1 | Delta |
|-----------|---------|---------|-------|
| `[maes_aktuelles]` | 27.794 | 26.934 | -860 (Inline-Script raus) |
| `[mmb]` | 306.756 | 307.859 | +1.103 (Filter-Bar dazu) |
| `[mil]` | 303.967 | 305.221 | +1.254 (Filter-Bar dazu) |
| Andere 10 | unveraendert | unveraendert | 0 |

Die +1 KB bei MMB/MIL werden in v0.14.0 durch AJAX-on-Demand massiv
ueberkompensiert (Ziel: < 50 KB initial).

## Geaenderte Dateien

| Datei | Aenderung |
|-------|-----------|
| `css/dhps-frontend.css` | +A11y-Baseline-Block (~70 Zeilen) |
| `public/js/dhps-maes-aktuelles.js` | NEU (2.1 KB) |
| `public/views/services/maes/aktuelles.php` | Inline-`<script>` raus, `wp_enqueue_script` rein |
| `public/views/services/mmb/default.php` | +Filter-Bar-Markup, +data-category |
| `Deubner_HP_Services.php` | Version-Bump + Script-Registrierung |
| `README.md` | Version-Bump |

## Sicherheit

| Check | Ergebnis |
|-------|----------|
| CSP `'self'`-Konformitaet | verbessert (1 Inline-Script weniger) |
| XSS-Vektoren in neuem Markup | keine (alle Outputs via esc_attr/esc_html) |
| ReDoS-Risiken | keine |
| Neue Dependencies | keine |

## Bekannte Limitierung

- Filter-Bar im MMB-Default zeigt **Buttons**, die noch nicht filtern -
  die JS-Logik in `dhps-mmb.js` reagiert auf `data-dhps-mmb-filter-bar`,
  aber die Filter-Funktion gegen die Default-Accordion-Struktur ist
  in v0.14.0 (ContentList-Component) vorgesehen. In Compact/Card
  filtert sie bereits, in Default ist sie zunaechst visuell.

## Naechste Schritte

v0.14.0 - Foundation + MMB/MIL-Pilot mit Alpine.js + Component-System.
Siehe [08-ROADMAP-v0140-Frontend-Modernisierung.md](08-ROADMAP-v0140-Frontend-Modernisierung.md).
