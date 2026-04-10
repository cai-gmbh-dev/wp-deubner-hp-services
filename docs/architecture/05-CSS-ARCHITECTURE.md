# CSS-Architektur

## Design Token System

Zentrale CSS Custom Properties in `css/dhps-design-tokens.css`:

### Farben
```css
--dhps-color-primary:   #1E73BE   /* Hauptfarbe */
--dhps-color-steuern:   #2e8a37   /* Steuerrecht (Gruen) */
--dhps-color-recht:     #1E73BE   /* Recht (Blau) */
--dhps-color-medizin:   #0097a7   /* Medizin (Teal) */
--dhps-color-success:   ...
--dhps-color-warning:   ...
--dhps-color-danger:    ...
```

### Typografie
```css
--dhps-fs-xs:   0.75rem
--dhps-fs-sm:   0.875rem
--dhps-fs-base: 1rem
--dhps-fs-md:   1.125rem
--dhps-fs-lg:   1.25rem
--dhps-fs-xl:   1.375rem
--dhps-fs-2xl:  1.5rem
```

### Spacing (8px-Basis)
```css
--dhps-space-xxs: 4px
--dhps-space-xs:  8px
--dhps-space-sm:  12px
--dhps-space-md:  16px
--dhps-space-lg:  24px
--dhps-space-xl:  32px
--dhps-space-2xl: 48px
```

### Weitere Tokens
```css
--dhps-radius:   8px
--dhps-shadow:   0 1px 3px rgba(0,0,0,0.08)
--dhps-grid-gap: var(--dhps-space-md)
```

## BEM-Namenskonvention

```
.dhps-{block}__{element}--{modifier}
```

### Beispiele
```css
.dhps-service                       /* Block */
.dhps-service--mio                  /* Block mit Modifier */
.dhps-layout--card                  /* Layout-Modifier */
.dhps-news__title                   /* Element */
.dhps-filter-bar__btn--active       /* Element mit Modifier */
.dhps-tp-video--featured            /* Block mit Modifier */
```

## CSS-Dateien

| Datei | Zweck | Laden |
|-------|-------|-------|
| `dhps-design-tokens.css` | CSS Custom Properties | Admin + Frontend |
| `dhps_base.css` | Resets, Elementor-Compat | Frontend |
| `dhps-frontend.css` | Alle Frontend-Komponenten | Frontend |
| `dhps-ui.css` | UI-Framework (Forms, Buttons) | Nur Admin |
| `dhps_admin.css` | Admin-Dashboard-Styles | Nur Admin |
| `dhps-dashboard.css` | Dashboard-spezifisch | Nur Admin (Dashboard) |

## CSS-Dependency-Chain

```
dhps-design-tokens (keine Deps)
     │
     ▼
dhps-base-css (depends: design-tokens)
     │
     ▼
dhps-frontend-css (depends: base-css)
```

## Bekannte Issues

- **Design Tokens nur Admin**: `dhps-design-tokens.css` wird im Frontend geladen, aber `dhps-frontend.css` hat 46 hardcodierte Hex-Werte statt CSS-Variablen (QA-Finding M1/M2)
- **Migration ausstehend**: Vollstaendige Migration der Frontend-Hardcodes auf `--dhps-*` Variablen steht noch aus

## Komponenten-Uebersicht

| Komponente | BEM-Block | Service |
|------------|-----------|---------|
| Such-Leiste | `.dhps-search-bar` | MIO, MMB |
| Akkordeon | `.dhps-news`, `.dhps-mmb-item` | MIO, MMB |
| Filter-Leiste | `.dhps-filter-bar` | TP, MMB |
| Video-Grid | `.dhps-tp-grid` | TP |
| Steuertermine | `.dhps-tax-dates` | MIO |
| Demo-Banner | `.dhps-demo-banner` | Alle |
| Card-Layout | `.dhps-card` | Layout |

## Accessibility

- ARIA: `aria-expanded`, `aria-hidden`, `aria-controls`, `aria-pressed`
- Semantisches HTML: `<section>`, `<button>`
- Screen-Reader: `.screen-reader-text`
- Keyboard: Enter/Space fuer Buttons, Tab-Navigation
