# UI/UX Redesign-Plan: Deubner Homepage Services Backend

## Status: ENTWURF - Zur Freigabe durch Architekten

**Version:** v0.7.0
**Erstellt:** 2026-02-12
**Verantwortlich:** UI/UX Specialist

---

## 1. Design-Analyse der Deubner-Websites

### 1.1 Corporate Design (deubner-verlag.de)

| Eigenschaft | Wert |
|---|---|
| Stil | Corporate Minimalist, professionell, B2B |
| Logo | Graustufen-Variante (grey.svg) + Farbvarianten (blue.svg, green.svg) |
| Layout-Prinzip | Card-basiert, Bild + Text-Paarungen |
| Sektionen | "Recht & Praxis", "Steuern & Praxis", "Akademie" |
| Navigation | Footer-heavy, Breadcrumbs, klare Hierarchie |

### 1.2 Shop "Recht" (deubner-recht.de/shop/)

| Eigenschaft | Wert |
|---|---|
| Primaerfarbe | **Blau** (Logo: `/blue.svg`) |
| Produktlayout | 3-Spalten Grid, Karten mit Bild oben |
| Karteninhalt | Produktbild (786x600px), Titel (verlinkt), Kurzbeschreibung, Preis + MwSt.-Hinweis |
| Typografie | Sans-Serif (Adobe Typekit), hierarchische Groessen |
| CTA | Implizit ueber Titel-Link, kein expliziter "Kaufen"-Button in der Uebersicht |
| Preisformat | `198,00 EUR zzgl. Versand und USt` / `19,95 EUR mtl. zzgl. USt` |

### 1.3 Shop "Steuern" (deubner-steuern.de/shop/)

| Eigenschaft | Wert |
|---|---|
| Primaerfarbe | **Gruen** (Logo: `/green.svg`) |
| Produktlayout | Identisch zu Recht (3-Spalten Grid) |
| Karteninhalt | Identische Struktur wie Recht |
| Abgrenzung | Farblich durch Gruen statt Blau differenziert |

### 1.4 Farbschema-Ableitung

```
Deubner Recht:     Blau   -> Services: mio, lxmio, mmb, mil, lp
Deubner Steuern:   Gruen  -> Services: tp, tpt, tc
Neutral/Medizin:   Teal   -> Services: maes
```

---

## 2. IST-Zustand des Backends (v0.6.0)

### 2.1 Identifizierte Probleme

| # | Problem | Schweregrad |
|---|---------|------------|
| P1 | CSS verwendet `--rsp-*` Prefix (Fremdplugin-Relikte) statt `--dhps-*` | Hoch |
| P2 | Primaerfarbe `#29b6f6` (helles Blau) stimmt nicht mit Deubner-Branding ueberein | Hoch |
| P3 | Header-Shop-Link zeigt auf `deubner-online.de/shop` (generisch) statt spezifische Shops | Hoch |
| P4 | Service-Cards im Dashboard haben keine Zuordnung zu Recht/Steuern-Bereich | Mittel |
| P5 | Kein visueller Unterschied zwischen Recht- und Steuer-Services | Mittel |
| P6 | "Freischalten"-Links fuehren alle zur gleichen URL | Hoch |
| P7 | Konfigurationsseiten haben keine Shop-Links zu den jeweiligen Produkten | Hoch |
| P8 | Inline-Styles (`style="margin-bottom: 20px;"`) statt CSS-Klassen in Templates | Mittel |
| P9 | Formulardesign ist sehr schlicht (Standard-WordPress-Look) | Mittel |
| P10 | Keine Service-Icons/Thumbnails fuer visuelle Unterscheidung | Niedrig |

### 2.2 Was bereits gut funktioniert

- Grid-System (`dhpsui-row`, `dhpsui-col-lg-*`) ist responsive
- Status-Badges (aktiv/demo/inaktiv) sind klar erkennbar
- Demo-Toggle-Mechanismus (AJAX) funktioniert technisch einwandfrei
- Gemeinsamer Header-Partial reduziert Duplikation
- Service-Registry-Pattern erlaubt einfache Erweiterung

---

## 3. SOLL-Zustand: Design-System "Deubner Backend"

### 3.1 Neue CSS Custom Properties

Ersetze alle `--rsp-*` Variablen durch `--dhps-*` mit Deubner-Branding:

```css
:root {
  /* Deubner Primaerfarben */
  --dhps-color-recht:         #1E73BE;    /* Deubner Recht Blau */
  --dhps-color-recht-light:   #ebf2f9;    /* Recht Hintergrund */
  --dhps-color-steuern:       #2e8a37;    /* Deubner Steuern Gruen */
  --dhps-color-steuern-light: #ecf4ed;    /* Steuern Hintergrund */
  --dhps-color-medizin:       #0097a7;    /* Medizin Teal */
  --dhps-color-medizin-light: #e0f2f1;    /* Medizin Hintergrund */

  /* Semantische Farben */
  --dhps-color-primary:       var(--dhps-color-recht);
  --dhps-color-success:       #2e8a37;
  --dhps-color-warning:       #f4bf3e;
  --dhps-color-danger:        #D7263D;
  --dhps-color-info:          #1E73BE;

  /* Neutrale Farben */
  --dhps-color-text:          #1a1a1a;
  --dhps-color-text-light:    #454552;
  --dhps-color-text-muted:    #737373;
  --dhps-color-bg:            #f0f0f1;
  --dhps-color-bg-white:      #ffffff;
  --dhps-color-border:        #dfdfdf;

  /* Typografie */
  --dhps-font-family:         -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  --dhps-fs-xs:               0.75rem;    /* 12px */
  --dhps-fs-sm:               0.8125rem;  /* 13px */
  --dhps-fs-base:             0.875rem;   /* 14px */
  --dhps-fs-md:               1rem;       /* 16px */
  --dhps-fs-lg:               1.125rem;   /* 18px */
  --dhps-fs-xl:               1.25rem;    /* 20px */
  --dhps-fs-2xl:              1.5rem;     /* 24px */

  /* Spacing */
  --dhps-space-xs:            4px;
  --dhps-space-sm:            8px;
  --dhps-space-md:            16px;
  --dhps-space-lg:            24px;
  --dhps-space-xl:            32px;
  --dhps-space-2xl:           48px;

  /* Radien & Schatten */
  --dhps-radius:              8px;
  --dhps-radius-lg:           12px;
  --dhps-shadow:              0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
  --dhps-shadow-md:           0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
}
```

### 3.2 Service-Kategorie-Zuordnung

Neue Property `category` und `shop_url` in der Service-Registry:

```php
// Neue Properties pro Service in DHPS_Service_Registry
'mio' => array(
    // ... bestehende Properties ...
    'category'  => 'recht',          // NEU: recht | steuern | medizin
    'shop_url'  => 'https://www.deubner-recht.de/shop/',   // NEU
    'icon'      => 'dashicons-media-text',                  // NEU
),
'tp' => array(
    // ... bestehende Properties ...
    'category'  => 'steuern',
    'shop_url'  => 'https://www.deubner-steuern.de/shop/',
    'icon'      => 'dashicons-video-alt3',
),
'maes' => array(
    // ... bestehende Properties ...
    'category'  => 'medizin',
    'shop_url'  => 'https://www.deubner-steuern.de/shop/',
    'icon'      => 'dashicons-heart',
),
```

**Vollstaendige Zuordnung:**

| Service | Slug | Kategorie | Shop-URL | Icon |
|---------|------|-----------|----------|------|
| MI-Online Steuerrecht | `mio` | steuern | deubner-steuern.de/shop/ | dashicons-media-text |
| MI-Online Recht | `lxmio` | recht | deubner-recht.de/shop/ | dashicons-media-text |
| Merkblaetter | `mmb` | steuern | deubner-steuern.de/shop/ | dashicons-media-document |
| Infografiken | `mil` | steuern | deubner-steuern.de/shop/ | dashicons-chart-bar |
| TaxPlain Videos | `tp` | steuern | deubner-steuern.de/shop/ | dashicons-video-alt3 |
| TaxPlain Teaser | `tpt` | steuern | deubner-steuern.de/shop/ | dashicons-format-video |
| Tax-Rechner | `tc` | steuern | deubner-steuern.de/shop/ | dashicons-calculator |
| Meine Aerzteseite | `maes` | medizin | deubner-steuern.de/shop/ | dashicons-heart |
| Lexplain | `lp` | recht | deubner-recht.de/shop/ | dashicons-video-alt2 |

---

## 4. Umsetzungsplan

### 4.1 Schritt 1: CSS-Variablen-Migration (Design Tokens)

**Datei:** `css/dhps_admin.css`

**Aufgabe:**
- Alle `--rsp-*` Variablen durch `--dhps-*` ersetzen
- Deubner-Branding-Farben einsetzen
- Kategorie-spezifische CSS-Klassen definieren

**Neue CSS-Klassen:**

```css
/* Kategorie-Farben fuer Service-Cards */
.dhps-category--recht     { --dhps-category-color: var(--dhps-color-recht); }
.dhps-category--steuern   { --dhps-category-color: var(--dhps-color-steuern); }
.dhps-category--medizin   { --dhps-category-color: var(--dhps-color-medizin); }

/* Kategorie-Akzent am oberen Rand der Card */
.dhps-service-card {
    border-top: 3px solid var(--dhps-category-color, var(--dhps-color-border));
}

/* Shop-Link-Button im Deubner-Stil */
.dhps-btn--shop {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    background: var(--dhps-category-color);
    color: #fff;
    border: none;
    border-radius: var(--dhps-radius);
    font-size: var(--dhps-fs-sm);
    font-weight: 600;
    text-decoration: none;
    transition: opacity 0.2s;
}

.dhps-btn--shop:hover {
    opacity: 0.9;
    color: #fff;
}
```

**Betroffene Dateien:**
- `css/dhps_admin.css` (Hauptaenderung)
- `css/dhps-ui.css` (Variablen-Referenzen)
- `css/dhps-frontend.css` (minimale Anpassung)

**Geschaetzter Aufwand:** ~120 Variablen-Ersetzungen

---

### 4.2 Schritt 2: Service-Registry erweitern

**Datei:** `includes/class-dhps-service-registry.php`

**Aufgabe:**
- `category` Property hinzufuegen (recht/steuern/medizin)
- `shop_url` Property hinzufuegen (spezifischer Shop-Link pro Service)
- `icon` Property hinzufuegen (WordPress Dashicon Klasse)

**Aenderung ist minimal und rueckwaertskompatibel** - nur neue Array-Keys.

---

### 4.3 Schritt 3: Dashboard-Redesign

**Datei:** `admin/views/dashboard.php`

**Aufgabe:**

#### A) Service-Cards mit Kategorie-Akzent

```php
<!-- Vorher -->
<div class="dhpsui-col-lg-4" style="margin-bottom: 20px;">
    <div class="dhpsui-box" style="padding: 20px; height: 100%;">

<!-- Nachher -->
<div class="dhpsui-col-lg-4">
    <div class="dhps-service-card dhps-category--<?php echo esc_attr( $service['category'] ); ?>">
        <div class="dhps-service-card__icon">
            <span class="dashicons <?php echo esc_attr( $service['icon'] ); ?>"></span>
        </div>
```

#### B) Shop-Links pro Service

```php
<!-- Vorher: Einheitlicher Shop-Link -->
<a href="<?php echo esc_url( $shop_url ); ?>" class="button" target="_blank">
    Freischalten
</a>

<!-- Nachher: Service-spezifischer Shop-Link -->
<a href="<?php echo esc_url( $service['shop_url'] ); ?>"
   class="dhps-btn--shop"
   target="_blank" rel="noopener noreferrer">
    <span class="dashicons dashicons-cart"></span>
    Im Shop ansehen
</a>
```

#### C) Kategorie-Gruppierung im Dashboard

```
+--------------------------------------------------+
| Willkommen bei den Deubner Homepage Services     |
+--------------------------------------------------+

--- Steuerrecht & Steuern ---
[MIO Card] [MMB Card] [MIL Card]
[TP Card]  [TPT Card] [TC Card]

--- Recht ---
[LXMIO Card] [LP Card]

--- Medizin ---
[MAES Card]

+--------------------------------------------------+
| Hinweise zum Demo-Modus                          |
+--------------------------------------------------+
```

#### D) Inline-Styles entfernen

Alle `style="..."` Attribute durch CSS-Klassen ersetzen.

---

### 4.4 Schritt 4: Header modernisieren

**Datei:** `admin/views/partials/header.php`

**Aufgabe:**
- Shop-Link aufteilen in Recht-Shop + Steuern-Shop
- "Hol dir die Premium-Version" ersetzen durch "Zum Shop"
- Optionale Breadcrumb-Navigation hinzufuegen

```php
<!-- Nachher -->
<div class="dhps-header-right">
    <a href="https://www.deubner-recht.de/shop/" class="dhps-header-link" target="_blank">
        Recht-Shop
    </a>
    <a href="https://www.deubner-steuern.de/shop/" class="dhps-header-link" target="_blank">
        Steuern-Shop
    </a>
    <a href="https://deubner-online.de/docs/" class="dhps-header-link" target="_blank">
        Dokumentation
    </a>
</div>
```

---

### 4.5 Schritt 5: Konfigurationsseiten mit Shop-Links

**Dateien:** `admin/views/service-config.php`, `admin/views/mio-config.php`

**Aufgabe:**
- Pro Service einen "Im Shop ansehen" Link einbauen
- Anzeige des aktiven/Demo/Inaktiv-Status auf Konfigurationsseiten
- Link zum Dashboard zurueck

```php
<!-- Neuer Abschnitt am Seitenanfang -->
<div class="dhps-config-header dhps-category--<?php echo esc_attr( $category ); ?>">
    <div class="dhps-config-header__info">
        <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
        <h3><?php echo esc_html( $page_title ); ?></h3>
        <span class="dhps-badge dhps-badge--<?php echo esc_attr( $status ); ?>">
            <?php echo esc_html( $status_label ); ?>
        </span>
    </div>
    <a href="<?php echo esc_url( $shop_url ); ?>"
       class="dhps-btn--shop" target="_blank">
        <span class="dashicons dashicons-cart"></span>
        Produkt im Shop ansehen
    </a>
</div>
```

---

### 4.6 Schritt 6: Formular-Styling verbessern

**Datei:** `css/dhps_admin.css`

**Aufgabe:**
- Formularfelder mit Label-Abstand und Feldgruppen-Styling
- Konsistentes Input-Design (Hoehe, Padding, Border-Radius)
- Save-Button als Primary-Button stylen
- Beschreibungstexte visuell absetzen

```css
/* Formular-Verbesserungen */
.dhps-form-group {
    margin-bottom: var(--dhps-space-lg);
}

.dhps-form-label {
    display: block;
    margin-bottom: var(--dhps-space-xs);
    font-weight: 600;
    font-size: var(--dhps-fs-sm);
    color: var(--dhps-color-text);
}

.dhps-form-input,
.dhps-form-select,
.dhps-form-textarea {
    width: 100%;
    max-width: 500px;
    padding: var(--dhps-space-sm) var(--dhps-space-md);
    border: 1px solid var(--dhps-color-border);
    border-radius: var(--dhps-radius);
    font-size: var(--dhps-fs-base);
    transition: border-color 0.2s;
}

.dhps-form-input:focus,
.dhps-form-select:focus,
.dhps-form-textarea:focus {
    border-color: var(--dhps-color-primary);
    outline: none;
    box-shadow: 0 0 0 2px rgba(30, 115, 190, 0.15);
}

.dhps-form-description {
    margin-top: var(--dhps-space-xs);
    font-size: var(--dhps-fs-xs);
    color: var(--dhps-color-text-muted);
}

.dhps-btn--primary {
    padding: var(--dhps-space-sm) var(--dhps-space-lg);
    background: var(--dhps-color-primary);
    color: #fff;
    border: none;
    border-radius: var(--dhps-radius);
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s;
}
```

---

### 4.7 Schritt 7: Betroffene Template-Dateien anpassen

Alle Templates muessen die neuen CSS-Klassen verwenden und die zusaetzlichen
Variablen aus der Registry nutzen:

| Datei | Aenderung |
|-------|-----------|
| `admin/views/dashboard.php` | Kategorie-Gruppierung, Shop-Links, Cards mit Icons |
| `admin/views/partials/header.php` | Zwei Shop-Links, modernisierter Look |
| `admin/views/service-config.php` | Config-Header mit Status + Shop-Link, Formular-Klassen |
| `admin/views/mio-config.php` | Config-Header mit Status + Shop-Link, Formular-Klassen |
| `includes/class-dhps-service-registry.php` | +category, +shop_url, +icon Properties |
| `includes/class-dhps-admin.php` | Neue Template-Variablen bereitstellen (category, shop_url, status) |
| `css/dhps_admin.css` | CSS-Variablen-Migration, neue Komponenten |

---

## 5. Wireframes

### 5.1 Dashboard (Neues Layout)

```
+----------------------------------------------------------------------+
| [DV Logo]  Deubner Verlag Homepage Services                          |
|                                 Recht-Shop | Steuern-Shop | Doku     |
+----------------------------------------------------------------------+

+----------------------------------------------------------------------+
| Willkommen bei den Deubner Homepage Services                         |
| Hier sehen Sie eine Uebersicht aller verfuegbaren Services.         |
+----------------------------------------------------------------------+

  Steuerrecht & Steuern
  +-----------------------+  +-----------------------+  +-----------------------+
  | ===== GRUEN ========= |  | ===== GRUEN ========= |  | ===== GRUEN ========= |
  | [icon] MI-Online      |  | [icon] Merkblaetter   |  | [icon] Infografiken   |
  |        Steuerrecht    |  |                       |  |                       |
  | [Aktiv]               |  | [Demo (28 Tage)]      |  | [Inaktiv]             |
  | Shortcode: [mio]      |  | Shortcode: [mmb]      |  | Shortcode: [mil]      |
  |                       |  |                       |  |                       |
  | [Konfigurieren]       |  | [Demo beenden]        |  | [Demo starten]        |
  | [Im Shop ansehen ->]  |  | [Im Shop ansehen ->]  |  | [Im Shop ansehen ->]  |
  +-----------------------+  +-----------------------+  +-----------------------+

  +-----------------------+  +-----------------------+  +-----------------------+
  | ===== GRUEN ========= |  | ===== GRUEN ========= |  | ===== GRUEN ========= |
  | [icon] TaxPlain       |  | [icon] TaxPlain       |  | [icon] Tax-Rechner    |
  |        Videos         |  |        Teaser         |  |                       |
  | ...                   |  | ...                   |  | ...                   |
  +-----------------------+  +-----------------------+  +-----------------------+

  Recht
  +-----------------------+  +-----------------------+
  | ===== BLAU ========== |  | ===== BLAU ========== |
  | [icon] MI-Online      |  | [icon] Lexplain       |
  |        Recht          |  |                       |
  | ...                   |  | ...                   |
  +-----------------------+  +-----------------------+

  Medizin
  +-----------------------+
  | ===== TEAL ========== |
  | [icon] Meine          |
  |        Aerzteseite    |
  | ...                   |
  +-----------------------+

+----------------------------------------------------------------------+
| Hinweise zum Demo-Modus                                              |
| Jeder Service kann fuer 30 Tage kostenlos getestet werden.           |
| Kontakt: mi-online-technik@deubner-verlag.de | 0221 / 93 70 18-28   |
+----------------------------------------------------------------------+
```

### 5.2 Konfigurationsseite (Neues Layout)

```
+----------------------------------------------------------------------+
| [DV Logo]  Deubner Verlag Homepage Services                          |
|                                 Recht-Shop | Steuern-Shop | Doku     |
+----------------------------------------------------------------------+

+----------------------------------------------------------------------+
| [icon] TaxPlain Videos                 [Aktiv]                       |
|                                        [Produkt im Shop ansehen ->]  |
+----------------------------------------------------------------------+
| ===== GRUENER AKZENT-BORDER ======================================== |

  Kurzbeschreibung:
  +--------------------------------------------------------------------+
  | 1. Definieren Sie die Parameter unten                              |
  | 2. Shortcode [tp] in Seite/Artikel einbauen                       |
  | 3. Fragen? mi-online-technik@deubner-verlag.de                    |
  +--------------------------------------------------------------------+

  Parameter:
  +--------------------------------------------------------------------+
  | OTA-Nummer                                                         |
  | [________________________]                                          |
  |                                                                    |
  | [Speichern]                                                        |
  +--------------------------------------------------------------------+
```

---

## 6. Priorisierung und Reihenfolge

| Prio | Schritt | Aufwand | Abhaengigkeit |
|------|---------|---------|---------------|
| 1 | Service-Registry erweitern (category, shop_url, icon) | Klein | Keine |
| 2 | CSS-Variablen Migration (--rsp-* -> --dhps-*) | Mittel | Keine |
| 3 | Dashboard-Redesign (Gruppierung, Shop-Links, Icons) | Mittel | 1 + 2 |
| 4 | Header modernisieren (zwei Shop-Links) | Klein | 2 |
| 5 | Konfigurationsseiten (Status, Shop-Link, Config-Header) | Mittel | 1 + 2 |
| 6 | Formular-Styling verbessern | Mittel | 2 |
| 7 | Inline-Styles entfernen | Klein | 2 |

---

## 7. Nicht im Scope (bewusst ausgeklammert)

- Frontend-Rendering der API-Inhalte (bleibt bei v1.0.0 / Phase 5)
- Neue API-Anbindung
- Custom Fonts (Typekit-Lizenz waere noetig)
- Dark-Mode
- Mehrsprachigkeit (i18n) - bereits vorbereitet aber nicht aktiviert

---

## 8. Technische Hinweise fuer den Architekten

### 8.1 Rueckwaertskompatibilitaet

- Neue Registry-Properties (`category`, `shop_url`, `icon`) sind additiv
- Bestehende CSS-Klassen bleiben als Fallback bestehen
- Keine PHP-API-Aenderungen noetig
- Templates erhalten neue Variablen ueber `DHPS_Admin`

### 8.2 Benoedigte Aenderungen in DHPS_Admin

Die Methoden `render_page()` und `render_mio_page()` muessen zusaetzliche
Template-Variablen bereitstellen:

```php
// Neue Variablen fuer service-config.php
$category = $service['category'] ?? 'steuern';
$shop_url = $service['shop_url'] ?? 'https://www.deubner-steuern.de/shop/';
$icon     = $service['icon']     ?? 'dashicons-admin-generic';
$status   = /* aus Demo-Manager ermitteln */;
```

### 8.3 CSS-Strategie

- `css/dhps_admin.css`: Komplett migrieren (--rsp-* -> --dhps-*)
- `css/dhps-ui.css`: Nur die :root Variablen anpassen (grosse Datei, schrittweise)
- Neue Datei `css/dhps-design-tokens.css` nur fuer Variablen-Definitionen
- Alte Variablen als Alias beibehalten bis vollstaendige Migration

### 8.4 Testplan

1. Dashboard: Alle 9 Services korrekt kategorisiert und farbcodiert
2. Shop-Links: Jeder Service verlinkt auf korrekten Shop (Recht vs. Steuern)
3. Konfigurationsseiten: Status-Badge + Shop-Link sichtbar
4. Responsive: Grid bricht korrekt um (4 -> 2 -> 1 Spalte)
5. Demo-Toggle: Funktioniert weiterhin nach CSS-Migration
6. Browser: Chrome, Firefox, Safari, Edge (aktuelle Versionen)

---

## 9. Zusammenfassung der Kern-Aenderungen

1. **Design Tokens**: Eigenes `--dhps-*` Variablen-System mit Deubner-Farben
2. **Kategorisierung**: Services visuell in Recht (blau), Steuern (gruen), Medizin (teal) unterteilt
3. **Shop-Links**: Jeder Service verlinkt direkt auf den passenden Deubner-Shop
4. **Dashboard**: Gruppierte Service-Cards mit farbigen Akzenten und Icons
5. **Header**: Separate Links zu Recht-Shop und Steuern-Shop
6. **Formulare**: Moderneres Styling mit besserem Spacing und Focus-States
7. **Konfigurationsseiten**: Status-Anzeige und direkter Shop-Link pro Service
