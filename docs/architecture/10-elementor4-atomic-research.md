# Elementor 4.x Atomic - Research Report (v0.14.0 Vorbereitung)

> **Status:** Research-only, keine Code-Aenderungen. Erstellt 2026-05-22.
> **Recherche-Einschraenkung:** WebSearch / WebFetch in dieser Sandbox blockiert.
> Externe Quellen konnten nicht live abgerufen werden. Findings basieren auf:
> 1. Lokaler Codebase (Plugin laeuft live gegen Elementor 4.0.1)
> 2. Wissen aus Trainings-Cutoff (Elementor v4 GA: Anfang 2026)
> 3. Plausibler Ableitung aus dem bestehenden `--e-global-*`-Namespace.
> Alle nicht direkt verifizierten Aussagen sind mit **(nicht verifiziert)** oder
> **(Annahme:)** markiert.

---

## Sektion 1: Atomic-Token-Inventar (--e-* Variablen)

### 1.1 In der Codebase nachweisbar verwendet

Aus `css/dhps_base.css`:

| Variable                       | Verwendung im Plugin              | Quelle |
|--------------------------------|------------------------------------|--------|
| `--e-global-color-primary`     | `.elementor-txcolor-primary`       | dhps_base.css |
| `--e-global-color-secondary`   | `.elementor-txcolor-secondary`     | dhps_base.css |
| `--e-global-color-accent`      | `.elementor-txcolor-accent`        | dhps_base.css |

Diese 3 Tokens sind seit Elementor 3.x stabil und in 4.x **unveraendert**
verfuegbar (siehe `docs/team-knowledge/05-ELEMENTOR-4X-MIGRATION.md`:
"Kompatibilitaetsstatus VOLL KOMPATIBEL").

### 1.2 Erwartetes vollstaendiges Token-Set (Annahme:)

Elementor 4.x fuehrt das "Atomic Design"-System ein. Auf Basis des bestehenden
`--e-global-*`-Schemas ist mit folgender Erweiterung zu rechnen
(**nicht verifiziert** - vor Implementierung gegen aktuelle dev-docs pruefen):

**Color-Tokens (Global Colors):**
```
--e-global-color-primary
--e-global-color-secondary
--e-global-color-accent
--e-global-color-text
--e-global-color-text-light          (Annahme:)
```

**Typography-Tokens (Global Fonts):**
```
--e-global-typography-primary-font-family
--e-global-typography-primary-font-size
--e-global-typography-primary-font-weight
--e-global-typography-primary-line-height
--e-global-typography-secondary-*
--e-global-typography-text-*
--e-global-typography-accent-*
```

**Atomic-spezifische Token-Klassen (Annahme: v4 NEU):**
```
--e-a-color-*          # Atomic Color
--e-a-bg-color-*       # Atomic Background
--e-a-spacing-*        # Atomic Spacing Scale
--e-a-radius-*         # Atomic Radius
```
Praefix `--e-a-` ist eine **plausible** Konvention fuer "elementor atomic",
**aber nicht aus der Codebase belegt**. Tatsaechliches Praefix bitte vor
Implementierung verifizieren (z.B. via `getComputedStyle(document.body)` im
Browser einer laufenden Elementor-4.x-Site).

### 1.3 Empfehlung Verifikations-Skript

Vor Implementierung von v0.14.0 ist es trivial, das echte Token-Inventar
zur Laufzeit zu extrahieren. Im Browser einer 4.x-Seite:

```javascript
// Im DevTools-Console der Live-Seite ausfuehren
const cs = getComputedStyle(document.documentElement);
const tokens = [];
for (const prop of cs) {
  if (prop.startsWith('--e-')) {
    tokens.push([prop, cs.getPropertyValue(prop).trim()]);
  }
}
console.table(tokens);
```

---

## Sektion 2: Container-System

### 2.1 Bekannter Stand

Das Plugin verwendet derzeit **keine** Elementor-Container-Klassen direkt -
unsere Widgets rendern eigenes Markup (BEM `.dhps-*`) und sitzen als
Block-Children **innerhalb** von Elementor-Containern, die der Site-Builder
gesetzt hat. Das ist der robuste Weg und sollte beibehalten werden.

### 2.2 Klassen-Hierarchie (verifiziert aus Codebase + Konvention)

Existierender Cascade in unseren Templates:

```
{{WRAPPER}}                                  <- Elementor-injiziert pro Widget-Instanz
  .dhps-service .dhps-service--{key}          <- Service-Branding-Hook
    .dhps-layout--{layout}                    <- Layout-Variante (card/compact/default)
      .dhps-{block}__{element}                <- Eigentlicher Inhalt (BEM)
```

`{{WRAPPER}}` ist seit 3.x stabil und in 4.x weiterhin **unveraendert** der
empfohlene Scoping-Mechanismus (siehe `class-dhps-elementor-widget-base.php`,
Zeilen 378, 396, 410 etc. - alle Selektoren werden korrekt mit `{{WRAPPER}}`
gescoped).

### 2.3 Container vs. Section/Column (Annahme:)

In Elementor 3.16+ wurde "Flexbox Container" eingefuehrt; in 4.x ist das
Default. Section/Column sind deprecated, aber ruecklauf-kompatibel.

Implikation fuer unser Plugin: **Wir muessen nichts aendern**, weil unsere
Widgets ihre eigene Box-Hierarchie liefern. Wer unser Widget in einen Container
oder in eine alte Section dropt - beides funktioniert.

### 2.4 Erkennbare Pflicht: kein eigenes Margin am Root

Atomic-Container erwarten Childs ohne externe Margins (Annahme:, Standard fuer
Flexbox-Container-Systeme). Unser Widget-Root `.dhps-service` sollte
`margin: 0` als Default haben, damit `gap` des Containers funktioniert.
Aktuell teilweise unklar; QA-Schritt fuer v0.14.0.

---

## Sektion 3: Empfohlene CSS-Variable-Bridge (--dhps-* -> --e-*)

### 3.1 Mapping-Tabelle

Quelle eigener Tokens: `css/dhps-design-tokens.css`.
Ziel: User-Theming via Elementor Global Settings soll durchgreifen.

| `--dhps-*` (eigen)              | -> Fallback-Kette                                                |
|----------------------------------|------------------------------------------------------------------|
| `--dhps-color-primary`           | `var(--e-global-color-primary, var(--dhps-color-steuern))`       |
| `--dhps-color-text`              | `var(--e-global-color-text, #333333)`                            |
| `--dhps-color-bg-white`          | `var(--e-global-color-secondary, #ffffff)` (Annahme:)            |
| `--dhps-font-family`             | `var(--e-global-typography-primary-font-family, "Lato",...)`     |
| `--dhps-fs-base`                 | `var(--e-global-typography-text-font-size, 0.875rem)`            |
| `--dhps-color-recht` (LXMIO/LP)  | **KEIN** Mapping - Branding-konstant, soll User-Theme ueberleben |
| `--dhps-color-steuern`           | **KEIN** Mapping - Brand-Token                                   |
| `--dhps-color-medizin` (MAES)    | **KEIN** Mapping - Brand-Token                                   |

**Entscheidungsregel:**
- Generische UI-Tokens (Text, BG, Hauptfont) -> Bridge zu `--e-global-*`
- Brand-Tokens (Steuern/Recht/Medizin) -> NICHT bridgen, sonst zerstoert
  User-Theming das Service-Branding.

### 3.2 Beispiel-Implementation (NUR Vorschlag, nicht angewendet)

```css
/* dhps-design-tokens.css - vorgeschlagene v0.14.0-Aenderung */
:root {
  /* Bridge: nimm Theme-Token, sonst eigener Fallback */
  --dhps-color-primary: var(--e-global-color-primary, #0AA245);
  --dhps-color-text:    var(--e-global-color-text,    #333333);
  --dhps-font-family:   var(
    --e-global-typography-primary-font-family,
    "Lato", "Helvetica Neue", Helvetica, Arial, sans-serif
  );

  /* Brand bleibt hart gesetzt */
  --dhps-color-steuern: #0AA245;
  --dhps-color-recht:   #0054A6;
  --dhps-color-medizin: #0097a7;
}
```

### 3.3 Risiko-Hinweis

`--e-global-color-secondary` ist in Elementor-Defaults oft **Schwarz/Dunkel**,
nicht Weiss. Mapping `--dhps-color-bg-white -> --e-global-color-secondary` ist
deshalb **falsch** und nur als Negativ-Beispiel aufgefuehrt. Backgrounds nicht
ans Theme koppeln.

---

## Sektion 4: 3-5 konkrete Empfehlungen fuer v0.14.0

### E1: Token-Verifikation als ERSTER Schritt
Vor jeder Code-Aenderung: Live-Inspect via DevTools auf einer aktuellen
4.0.1-Seite ausfuehren (Skript siehe 1.3). Ergebnis als
`docs/architecture/11-elementor4-tokens-verified.md` ablegen. Erst danach
die Bridge-Datei schreiben.

### E2: Bridge-Datei separat, nicht in dhps-design-tokens.css mergen
Neue Datei `css/dhps-elementor-bridge.css`, die NACH `dhps-design-tokens.css`
geladen wird und ausgewaehlte Tokens via `:root { --dhps-X: var(--e-X, fallback); }`
ueberschreibt. Vorteil: Bridge ist **abschaltbar** (z.B. fuer Sites ohne
Elementor) ohne dass Token-Defaults verloren gehen.

### E3: CSS @layer einfuehren - Plugin-Layer ZWISCHEN Theme und Elementor
**Annahme:** Elementor 4.x nutzt `@layer elementor` (nicht verifiziert).
Empfohlene Layer-Reihenfolge im Plugin-CSS:
```css
@layer reset, theme, elementor, dhps, dhps-overrides;
```
Unsere Komponenten-Styles in `@layer dhps`, kritische Korrekturen (z.B.
`.dhps-card { margin: 0 }`) in `@layer dhps-overrides`. Damit kann User-Theme
gezielt eingreifen, ohne `!important`-Krieg.
**Voraussetzung:** Layer-Annahme verifizieren - sonst Layer-Loesung
zurueckstellen.

### E4: Atomic-Widget-Konvention pruefen, Widgets ggf. kennzeichnen
Falls Elementor v4 ein Manifest-Feld wie `'atomic' => true` in
`get_widget_meta()` oder einen separaten `Atomic_Widget_Base` einfuehrt
(Annahme:, nicht verifiziert), unsere 9 Widgets entsprechend deklarieren.
Konkrete Schritte erst nach Doku-Verifikation auf
`developers.elementor.com/docs/v4/atomic-widgets/` festlegen.

### E5: Alpine.js mit Element-Scoping einfuehren, NICHT global
Alpine via `defer` laden, `Alpine.start()` selbst aufrufen, `x-data` nur an
unseren `.dhps-service`-Wurzeln verwenden. So kein Konflikt mit Elementors
eigenem JS-Runtime (jQuery + Swiper + Lottie in 4.x). Wichtige Anti-Pattern:
- KEIN `x-cloak` auf Body-Level
- KEIN Alpine auf Elementor-eigenen Elementen (`.elementor-*`)
- Alpine-Magic via `Alpine.magic()` mit Prefix `$dhps` (Vermeidung von Kollision)

---

## Sektion 5: Offene Fragen / TODO Verifikation

Diese Punkte muessen vor v0.14.0-Implementierung geklaert werden (alle
benoetigen Live-Zugriff oder Web-Recherche, der in dieser Sandbox blockiert war):

1. Echtes Atomic-Token-Praefix (`--e-a-*` vs `--e-atomic-*` vs anders) ?
2. Vollstaendige Liste der globalen Typography-Sub-Tokens (`-font-family`,
   `-font-size`, `-font-weight`, `-line-height`, `-letter-spacing`) ?
3. Nutzt Elementor 4.x `@layer` und falls ja mit welchem Layer-Namen ?
4. Gibt es eine eigene Basisklasse `\Elementor\Atomic_Widget_Base` oder ein
   Interface zum Opt-in als "atomic widget" ?
5. Default-Werte der globalen Colors in einem unangepassten 4.x-Setup
   (insbesondere `secondary` - schwarz oder weiss) ?

---

## Quellen-Liste

**In dieser Sandbox tatsaechlich konsultiert:**
- Lokale Datei: `css/dhps_base.css` (Plugin verwendet bereits
  `--e-global-color-primary/secondary/accent`)
- Lokale Datei: `css/dhps-design-tokens.css` (eigene `--dhps-*`-Tokens)
- Lokale Datei: `docs/team-knowledge/05-ELEMENTOR-4X-MIGRATION.md`
  (Kompatibilitaetsstatus mit 4.0.1)
- Lokale Datei: `docs/architecture/05-CSS-ARCHITECTURE.md` (Token-Layout)
- Lokale Datei: `docs/architecture/07-WIDGET-SYSTEM.md` (Widget-Architektur)
- Lokale Datei: `widgets/elementor/class-dhps-elementor-widget-base.php`
  (Selektoren via `{{WRAPPER}}`)

**Beabsichtigt, in dieser Sandbox nicht erreichbar (WebSearch/WebFetch blockiert):**
- `https://developers.elementor.com/docs/v4/`
- `https://developers.elementor.com/docs/atomic-widgets/`
- `https://github.com/elementor/elementor/releases` (v4.0.0, v4.0.1)
- `https://github.com/elementor/elementor` (Source fuer `--e-*`-Definitionen)

**Empfehlung:** Diese 4 URLs vor v0.14.0-Implementation manuell konsultieren
und Findings in `docs/architecture/11-elementor4-tokens-verified.md`
festhalten.
