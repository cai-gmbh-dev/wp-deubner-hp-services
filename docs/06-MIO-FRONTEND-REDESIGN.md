# MIO Service Frontend-Redesign: Analyse & 3 Layout-Varianten

## Status: ENTWURF - Zur Freigabe durch Architekten

**Betrifft:** MI-Online Steuerrecht (`[mio]`) - Pilotservice fuer alle 9 Services
**Erstellt:** 2026-02-14
**Verantwortlich:** UI/UX Specialist

---

## 1. IST-Analyse: Aktuelle MIO-Umsetzung

### 1.1 Content-Struktur (API-Response)

Die MIO-Seite besteht aus **drei Hauptbereichen**, die als Roh-HTML von
`deubner-online.de/einbau/mio/bin/php_inhalt.php` geliefert werden:

```
+------------------------------------------------------------------+
| BEREICH 1: Steuertermine (statisch im initialen HTML)            |
| +-----------------------------+  +-----------------------------+ |
| | Steuertermine Maerz 2026   |  | Steuertermine April 2026   | |
| | 10.03. Umsatzsteuer*       |  | 10.04. Umsatzsteuer        | |
| |        Lohnsteuer*          |  |        Lohnsteuer*          | |
| |        Solidaritaetszuschl.|  |        Solidaritaetszuschl.  | |
| |        Kirchenlohnsteuer   |  |        Kirchenlohnsteuer     | |
| |        EK/KSt**            |  |                             | |
| | Zahlungsschonfrist: ...    |  | Zahlungsschonfrist: ...     | |
| +-----------------------------+  +-----------------------------+ |
+------------------------------------------------------------------+
| <hr> Trennlinie                                                 |
+------------------------------------------------------------------+
| BEREICH 2: Such-/Filterleiste                                    |
| [Dropdown: Zielgruppen]  [Suchfeld: Suchbegriff]  [Lupe-Icon]  |
|                                                                  |
| (Versteckt: Erweiterte Filter mit Checkboxen, Monat, Jahr)      |
+------------------------------------------------------------------+
| BEREICH 3: Nachrichten (AJAX-geladen in #txtHint)                |
| -- Gruppiert nach Zielgruppe --                                  |
|                                                                  |
| <h3> Informationen fuer alle Steuerzahler </h3>                 |
| > Artikel-Titel 1 (toggle-Link)                                 |
|   [Volltext ausgeklappt: Paragraphen, Metadaten, Social-Share]  |
| > Artikel-Titel 2 (toggle-Link)                                 |
| > Artikel-Titel 3 (toggle-Link)                                 |
|                                                                  |
| <h3> Informationen fuer Arbeitgeber und Arbeitnehmer </h3>      |
| > Artikel-Titel 4 (toggle-Link)                                 |
| ...                                                              |
+------------------------------------------------------------------+
```

### 1.2 HTML-Struktur (Detail)

#### Steuertermine

```html
<div id="steuertermine">
  <div id="steuertermin1">              <!-- float:left, width:400px -->
    <h4 class="ueb_steuertermine">Steuertermine Maerz 2026</h4>
    <div class="beitrag_steuertermine">
      <table>
        <tr valign="top">
          <td width="50">10.03.</td>
          <td>Umsatzsteuer*<br/>Lohnsteuer*<br/>...</td>
        </tr>
      </table>
      <p>Zahlungsschonfrist: bis zum 13.03.2026. ...</p>
    </div>
  </div>
  <div id="steuertermin2">              <!-- float:left, width:400px -->
    <h4>Steuertermine April 2026</h4>
    <div class="beitrag_steuertermine">...</div>
  </div>
  <div style="clear:left;"></div>
</div>
```

#### Suchleiste

```html
<div class="mio_head">
  <div id="auswahlStandard">
    <form id="eingabe_suchbegriff">
      <select id="rubriken">
        <option selected>alle Zielgruppen</option>
        <option>Informationen fuer alle Steuerzahler</option>
        <option>Informationen fuer Arbeitgeber und Arbeitnehmer</option>
        <option>Informationen fuer Freiberufler</option>
        <option>Informationen fuer GmbH-Gesellschafter/-GF</option>
        <option>Informationen fuer Hausbesitzer</option>
        <option>Informationen fuer Kapitalanleger</option>
        <option>Informationen fuer Unternehmer</option>
      </select>
      <input type="text" id="suchbegriff" placeholder="Suchbegriff">
    </form>
    <div id="suchsteuerung">
      <a href="javascript:..." onclick="showResult(...);">
        <img id="lupe" alt="lupe" src=".../Lupe.png">
      </a>
    </div>
  </div>
</div>
```

#### Nachrichten (AJAX-Content)

```html
<!-- Gruppenueberschrift -->
<h3 class="zielgruppe">Informationen fuer alle Steuerzahler</h3>

<article>
  <!-- Titel-Link (toggle) -->
  <a href="javascript:toggleDoubleDiv(...)" class="newstitel" id="newstitel18014">
    Zweck verfehlt: Zuwendungen an eine Landesstiftung...
  </a>

  <!-- Ausgeklappter Inhalt (display:none) -->
  <div class="mio_msg_content" id="item18014" style="display:none;">
    <div class="news_open">
      [Titel nochmal]
      <a href="..." class="toggle controls_top">[X ausblenden]</a>
    </div>
    <P>Inhalt Absatz 1...</P>
    <P>Inhalt Absatz 2...</P>
    <table border="0">
      <tr><td><em>Information fuer:</em></td><td><em>alle</em></td></tr>
      <tr><td><em>zum Thema:</em></td><td><em>Erbschaft-/Schenkungsteuer</em></td></tr>
    </table>
    <div class="no_print">
      <p class="sm_section">
        [Social-Share: Mail, Twitter, Facebook, Xing, LinkedIn, WhatsApp]
      </p>
      <p class="item_navigation">
        [Drucken] [Ausblenden]
      </p>
      <hr class="news_trenner">
    </div>
  </div>
</article>
```

### 1.3 Identifizierte Probleme

| # | Problem | Kategorie | Schweregrad |
|---|---------|-----------|------------|
| 1 | **Table-Layout** fuer Steuertermine statt CSS Grid/Flex | Layout | Hoch |
| 2 | **Float-Layout** (width:400px) nicht responsive | Layout | Hoch |
| 3 | **Inline Styles** ueberall (display:none, clear:left, etc.) | CSS | Hoch |
| 4 | **Raw XMLHttpRequest** statt fetch/WordPress AJAX | JS | Hoch |
| 5 | **Inline onclick-Handler** (`javascript:toggleDiv(...)`) | Security/A11y | Hoch |
| 6 | **OTA-Credentials im JavaScript** sichtbar im Quelltext | Security | Kritisch |
| 7 | **`<script language="JavaScript">`** veraltetes Attribut | Standards | Niedrig |
| 8 | **Kein ARIA** - keine Barrierefreiheit | A11y | Hoch |
| 9 | **Hardcodierte Farben** (#720D02, #ddd, #aaa) | Design | Mittel |
| 10 | **Kein BEM/Namespace** - Klassen wie `.mio`, `.rubrik`, `.news_open` | CSS | Mittel |
| 11 | **Social-Icons** sind `<img>` mit externen URLs (Ladezeit) | Performance | Mittel |
| 12 | **Druckfunktion** ueberschreibt body.innerHTML | UX/Quality | Mittel |
| 13 | **document.write** fuer WhatsApp-Erkennung | Standards | Mittel |
| 14 | **Keine Pagination-UI** - Seiten werden via JS gesteuert | UX | Mittel |
| 15 | **Kein Ladeindikator** beim AJAX-Request | UX | Mittel |

### 1.4 Was gut funktioniert (beibehalten)

- Inhaltliche Struktur (Termine + Suche + Nachrichten) ist logisch
- Zielgruppen-Filterung ist nuetzlich fuer Endnutzer
- Accordion-Pattern fuer Nachrichtendetails spart Platz
- Social-Share-Optionen sind umfangreich
- Print-Funktion ist vorhanden (wenn auch schlecht umgesetzt)

---

## 2. Zielgruppen & Use Cases

### 2.1 Primaere Zielgruppe
**Mandanten von Steuerberatern/Rechtsanwaelten** die die Website besuchen.
- Nicht technikaffin, aeltere Altersstruktur (40-70)
- Erwartet professionelles, serioeses Erscheinungsbild
- Nutzt vor allem Desktop, zunehmend Tablet/Mobile
- Will schnell relevante Steuerinformationen finden

### 2.2 Sekundaere Zielgruppe
**Steuerberater/Anwaelte** die den Service auf ihrer Website einbinden.
- Erwartet dass der Service sich nahtlos in ihr Website-Design einfuegt
- Will den Service ohne CSS-Anpassungen nutzen koennen
- Erwartet professionelle Darstellung fuer ihre Mandanten

### 2.3 Kern-Use-Cases
1. Naechste Steuertermine pruefen (Quick-Scan)
2. Aktuelle Nachrichten nach Kategorie browsen
3. Einen bestimmten Beitrag lesen + teilen/drucken
4. Nach einem Stichwort suchen

---

## 3. Drei Layout-Varianten

### WICHTIG: Architektur-Einschraenkung (Phase 0.8.x)

Da die API aktuell **Roh-HTML** liefert und kein strukturiertes JSON,
muessen die Layout-Varianten **ueber CSS** realisiert werden. Das HTML
bleibt unveraendert (wird 1:1 von der API eingefuegt).

Erst in Phase 5 (v1.0.0) mit HTML-Parsing/eigener Rendering-Engine
koennen wir das HTML selbst generieren.

Die folgenden Varianten beschreiben daher ein **CSS-Override-System**,
das die bestehenden Legacy-Klassen (`.mio`, `.mio_head`, etc.) ueberschreibt.

---

### 3.1 Variante A: "Clean Modern" (Empfohlen)

**Philosophie:** Minimalistisch, viel Weissraum, klare Hierarchie.
Orientiert am Deubner-Corporate-Design.

```
+------------------------------------------------------------------+
|                                                                  |
|  Steuertermine                                                   |
|  +---------------------------+  +---------------------------+    |
|  |  MAERZ 2026              |  |  APRIL 2026              |    |
|  |  ========================|  |  ========================|    |
|  |  10.03.                  |  |  10.04.                  |    |
|  |  - Umsatzsteuer*         |  |  - Umsatzsteuer          |    |
|  |  - Lohnsteuer*           |  |  - Lohnsteuer*           |    |
|  |  - Solidaritaetszuschlag*|  |  - Solidaritaetszuschl.* |    |
|  |  - Kirchenlohnsteuer     |  |  - Kirchenlohnsteuer     |    |
|  |  - EK/KSt**              |  |                          |    |
|  |                          |  |                          |    |
|  |  Schonfrist: 13.03.2026  |  |  Schonfrist: 13.04.2026  |    |
|  +---------------------------+  +---------------------------+    |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  [Alle Zielgruppen v]  [______Suchbegriff______]  [Suchen]     |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  INFORMATIONEN FUER ALLE STEUERZAHLER                           |
|  ____________________________________________________________   |
|                                                                  |
|  > Zweck verfehlt: Zuwendungen an eine Landesstiftung...        |
|    Erbschaft-/Schenkungsteuer                      14.02.2026   |
|  --------------------------------------------------------       |
|  > Teure Erbschaft: Strenge Voraussetzungen...                  |
|    Erbschaft-/Schenkungsteuer                      14.02.2026   |
|  --------------------------------------------------------       |
|                                                                  |
|  INFORMATIONEN FUER ARBEITGEBER UND ARBEITNEHMER                |
|  ____________________________________________________________   |
|  ...                                                             |
+------------------------------------------------------------------+
```

**CSS-Charakteristiken:**
- Steuertermine in **Cards mit leichtem Schatten**, side-by-side via `display: flex`
- Monat als **prominenter Card-Header** (fett, leicht groesser)
- Suchleiste in einer **einheitlichen Toolbar** mit Flex-Layout
- Zielgruppen-Ueberschriften als **Uppercase-Labels mit Unterstrichen**
- Nachrichtentitel mit **dezenter Trennlinie** statt Pfeil-Icon
- Themenkategorie + Datum als **Meta-Tags unter dem Titel** (small, grau)
- Ausgeklappter Artikel: **weisser Hintergrund, subtiler linker Rand**
- Social-Icons: **Einheitliche SVG-Icons** statt externe Bilder

**Farbschema:**
- Primaer: `#2e8a37` (Deubner Steuern Gruen) fuer aktive Elemente
- Text: `#1a1a1a` auf `#ffffff`
- Meta/Sekundaer: `#737373`
- Hover/Links: `#2e8a37` statt `#720D02`

---

### 3.2 Variante B: "Card-Based Magazine"

**Philosophie:** Jeder Nachrichtenartikel als eigenstaendige Card.
Magazin-Look, visuell aufwendiger, aehnlich wie der Deubner-Shop.

```
+------------------------------------------------------------------+
|  +---------------------------+  +---------------------------+    |
|  | [Kalender]  MAERZ 2026   |  | [Kalender]  APRIL 2026   |    |
|  | 10.03. Umsatzsteuer*     |  | 10.04. Umsatzsteuer      |    |
|  | ...                      |  | ...                      |    |
|  +---------------------------+  +---------------------------+    |
+------------------------------------------------------------------+
|                                                                  |
|  [Alle Zielgruppen v]  [______Suche______]  [Suchen]            |
|                                                                  |
|  +-- Alle Steuerzahler --+                                      |
|                                                                  |
|  +------------------------+ +------------------------+          |
|  | [Tag]  Erbschaft-/     | | [Tag]  uebrige         |          |
|  |        Schenkungsteuer | |        Steuerarten     |          |
|  |                        | |                        |          |
|  | Zweck verfehlt:        | | Klageerhebung durch   |          |
|  | Zuwendungen an eine    | | Steuerberater: Wirrwarr|          |
|  | Landesstiftung...      | | bei elektronischer... |          |
|  |                        | |                        |          |
|  | [Lesen ->]             | | [Lesen ->]             |          |
|  +------------------------+ +------------------------+          |
|                                                                  |
|  +-- Arbeitgeber & Arbeitnehmer --+                             |
|  ...                                                             |
+------------------------------------------------------------------+
```

**CSS-Charakteristiken:**
- Nachrichten als **2-Spalten-Grid mit Cards**
- Jede Card hat **Themen-Tag oben** (farbige Pills/Badges)
- **Kurzvorschau** des Artikels (2-3 Zeilen, truncated)
- Expliziter **"Lesen"-CTA** statt Toggle-Link
- Kategorie-Tabs statt Scroll-Accordion
- Steuertermine mit **Kalender-Icon** links

**Farbschema:**
- Cards: Weiss mit `box-shadow`, `border-radius: 8px`
- Tags: Kategorie-abhaengige Pastellfarben
- CTA: Deubner-Gruen

---

### 3.3 Variante C: "Kompakt & Funktional"

**Philosophie:** Maximale Informationsdichte, datengetrieben.
Optimal fuer Nutzer die viele Nachrichten scannen wollen.

```
+------------------------------------------------------------------+
|  Steuertermine Maerz 2026        | Steuertermine April 2026      |
|  10.03. USt, LSt, SolZ, KiLSt,  | 10.04. USt, LSt, SolZ, KiLSt |
|         EK/KSt                   | Schonfrist: 13.04.            |
|  Schonfrist: 13.03.              |                               |
+------------------------------------------------------------------+
|  [v Zielgruppe] [__Suche__] [Go] | Erw. Filter                   |
+------------------------------------------------------------------+
|                                                                  |
|  Alle Steuerzahler (3)                                   [+/-]  |
|  ----------------------------------------------------------------|
|  > Zweck verfehlt: Zuwendungen...   | ErbSch  | 14.02. | [>]   |
|  > Teure Erbschaft: Strenge Vor...  | ErbSch  | 14.02. | [>]   |
|  > Klageerhebung durch Steuerbe...  | Sonstig | 14.02. | [>]   |
|                                                                  |
|  Arbeitgeber und Arbeitnehmer (2)                        [+/-]  |
|  ----------------------------------------------------------------|
|  > Neue Regelungen zur Kurzarbeit   | Lohnst  | 13.02. | [>]   |
|  > Homeoffice-Pauschale 2026        | Lohnst  | 12.02. | [>]   |
|                                                                  |
+------------------------------------------------------------------+
```

**CSS-Charakteristiken:**
- **Tabellarisches Layout** fuer Nachrichten (Grid mit Spalten: Titel, Thema, Datum, Action)
- Steuertermine **einzeilig kompakt**
- Zielgruppen als **Collapsible Sections** mit Artikelanzahl
- Abgekuerzte Themen-Labels (ErbSch, Lohnst, etc.)
- **Keine Cards** - reines Linien-/Borderlayout
- Sehr **platzsparend**, ideal fuer Seitenleisten oder schmale Einbettungen

**Farbschema:**
- Monochrom: Schwarz/Weiss/Grau
- Akzent nur bei Hover und aktivem Zustand
- Kompakte Schriftgroessen

---

## 4. Vergleichsmatrix

| Kriterium | A: Clean Modern | B: Card Magazine | C: Kompakt |
|-----------|:-:|:-:|:-:|
| **Professioneller Eindruck** | +++ | ++ | ++ |
| **Informationsdichte** | ++ | + | +++ |
| **Mobile-Freundlichkeit** | +++ | ++ | ++ |
| **Theme-Kompatibilitaet** | +++ | ++ | +++ |
| **CSS-Only umsetzbar** | +++ | ++ | +++ |
| **Lesbarkeit** | +++ | +++ | ++ |
| **Barrierefreiheit** | +++ | ++ | ++ |
| **Deubner-Branding-Naehe** | +++ | ++ | + |
| **Umsetzungsaufwand** | Mittel | Hoch | Niedrig |

**Empfehlung: Variante A "Clean Modern"** als Standard-Layout.
Variante C kann als `layout="compact"` Shortcode-Option angeboten werden.

---

## 5. CSS-Implementierungsplan (Phase 0.8.x)

### 5.1 Neues Stylesheet: `css/dhps-mio-override.css`

Da wir das API-HTML nicht aendern koennen, ueberlagern wir die Legacy-Styles.
Das Stylesheet wird nur geladen wenn ein MIO-Shortcode auf der Seite aktiv ist.

```css
/* === Targeting: Nur innerhalb unseres Wrappers === */
.dhps-service--mio .mio { ... }

/* === Steuertermine: Flexbox statt Float === */
.dhps-service--mio #steuertermine {
    display: flex;
    gap: 24px;
    margin-bottom: 32px;
}

.dhps-service--mio #steuertermin1,
.dhps-service--mio #steuertermin2 {
    float: none !important;
    width: auto !important;
    flex: 1;
    background: #fff;
    border: 1px solid var(--dhps-color-border, #e0e0e0);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}

/* === Suchleiste: Moderne Toolbar === */
.dhps-service--mio #auswahlStandard {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    margin-bottom: 24px;
}

.dhps-service--mio #auswahlStandard select,
.dhps-service--mio #auswahlStandard input[type="text"] {
    height: 40px;
    padding: 8px 12px;
    border: 1px solid #d0d0d0;
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
}

/* === Nachrichten: Saubere Typografie === */
.dhps-service--mio h3.zielgruppe {
    font-size: 0.8125rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: var(--dhps-color-steuern, #2e8a37);
    border-bottom: 2px solid var(--dhps-color-steuern, #2e8a37);
    padding-bottom: 8px;
    margin: 32px 0 16px;
}

.dhps-service--mio a.newstitel {
    color: var(--dhps-color-text, #1a1a1a) !important;
    background: none !important;
    padding-left: 0 !important;
    font-size: 1rem;
    font-weight: 500;
    line-height: 1.5;
    text-decoration: none;
    display: block;
    padding: 12px 0;
    border-bottom: 1px solid #eee;
    transition: color 0.2s;
}

.dhps-service--mio a.newstitel:hover {
    color: var(--dhps-color-steuern, #2e8a37) !important;
}

/* === Ausgeklappter Artikel === */
.dhps-service--mio .mio_msg_content {
    padding: 16px 0 16px 16px;
    border-left: 3px solid var(--dhps-color-steuern, #2e8a37);
    margin: 8px 0 16px;
}

.dhps-service--mio .mio_msg_content P {
    font-size: 0.9375rem;
    line-height: 1.7;
    color: #333;
    margin-bottom: 12px;
}

/* === Social-Share: Kompakter === */
.dhps-service--mio .sm_section {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.dhps-service--mio .sm_icon {
    width: 20px !important;
    height: 20px !important;
    opacity: 0.6;
    transition: opacity 0.2s;
}

.dhps-service--mio .sm_icon:hover {
    opacity: 1;
}

/* === Responsive === */
@media (max-width: 768px) {
    .dhps-service--mio #steuertermine {
        flex-direction: column;
    }
}
```

### 5.2 Layout-Variante "compact" Override

```css
/* Compact-Variante fuer [mio layout="compact"] */
.dhps-layout--compact .mio h3.zielgruppe {
    font-size: 0.75rem;
    margin: 16px 0 8px;
}

.dhps-layout--compact .mio a.newstitel {
    font-size: 0.875rem;
    padding: 6px 0;
}

.dhps-layout--compact .mio #steuertermine {
    gap: 12px;
    margin-bottom: 16px;
}

.dhps-layout--compact .mio #steuertermin1,
.dhps-layout--compact .mio #steuertermin2 {
    padding: 12px;
}
```

### 5.3 Layout-Variante "card" Override

```css
/* Card-Variante fuer [mio layout="card"] */
.dhps-layout--card .mio article {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    padding: 16px;
    margin-bottom: 12px;
}

.dhps-layout--card .mio a.newstitel {
    border-bottom: none;
}
```

---

## 6. Betroffene Dateien & Aufwand

| Datei | Aenderung | Aufwand |
|-------|-----------|---------|
| `css/dhps-mio-override.css` | **NEU** - MIO-spezifisches CSS Override | Mittel |
| `includes/class-dhps-admin.php` | CSS nur bei MIO-Shortcode laden (conditional) | Klein |
| `includes/class-dhps-shortcodes.php` | CSS-Enqueue wenn MIO aktiv | Klein |
| `css/dhps-frontend.css` | Ergaenzen um generische Service-Content-Styles | Klein |

### 6.1 Spaetere Erweiterung (Phase 5 / v1.0.0)

Sobald HTML-Parsing (DOMDocument) implementiert ist, koennen wir:

1. Das API-HTML **parsen** und in strukturierte Daten umwandeln
2. **Eigene Templates** fuer die drei Bereiche rendern
3. **Inline-Scripts entfernen** und durch eigene JS-Module ersetzen
4. **AJAX-Requests proxyen** ueber den WordPress-Server (keine OTA-Exposure)
5. **Server-Side Rendering** statt Client-Side AJAX

Das ist die saubere Loesung, erfordert aber erheblichen Aufwand und
eine stabile API-Struktur-Analyse aller 9 Services.

---

## 7. Umsetzungsreihenfolge

| Prio | Schritt | Beschreibung |
|------|---------|-------------|
| 1 | `dhps-mio-override.css` erstellen | Variante A CSS-Overrides |
| 2 | Conditional CSS Loading | Stylesheet nur bei aktivem MIO-Shortcode laden |
| 3 | Responsive Testing | Alle Breakpoints testen (mobile, tablet, desktop) |
| 4 | Weitere Services | Override-CSS fuer MMB, MIL, TP, TC, LP, MAES, TPT |
| 5 | Compact-Variante | `layout="compact"` CSS fuer alle Services |
| 6 | Card-Variante | `layout="card"` CSS fuer alle Services |

---

## 8. Hinweise fuer den Architekten

### 8.1 CSS-Loading-Strategie

Das Override-CSS sollte **nur geladen werden wenn der jeweilige Shortcode
auf der Seite aktiv ist**. Vorschlag:

```php
// In DHPS_Shortcodes::handle_shortcode()
wp_enqueue_style(
    'dhps-mio-override',
    DEUBNER_HP_SERVICES_URL . 'css/dhps-mio-override.css',
    array( 'dhps-frontend-css' ),
    DEUBNER_HP_SERVICES_VERSION
);
```

### 8.2 Spezifitaet beachten

Da die API-HTML-Styles teils mit Inline-CSS kommen (`style="display:none;"`),
muessen unsere Overrides teilweise `!important` verwenden. Dies ist in
diesem Fall akzeptabel, da es sich um ein bewusstes Override-System handelt.

### 8.3 CSS-Variablen nutzen

Alle Override-Farben nutzen `var(--dhps-color-*)` aus den Design Tokens.
So passt sich das Styling automatisch an, wenn ein Theme die Variablen
ueberschreibt. Fuer den Recht-Bereich (`[lxmio]`) wird automatisch
`--dhps-color-recht` statt `--dhps-color-steuern` verwendet.

### 8.4 Kein JavaScript aendern

In Phase 0.8.x aendern wir **kein JavaScript**. Die AJAX-Logik, das
Toggle-System und die Suchfunktion bleiben wie sie sind. Nur das
visuelle Erscheinungsbild wird per CSS modernisiert.

Das JavaScript-Refactoring ist Teil von Phase 5 (v1.0.0).

---

## 9. Zusammenfassung

**Problem:** Die MIO-Seite nutzt Legacy-CSS (Tabellen, Floats, Inline-Styles,
hardcodierte Farben) und wirkt visuell veraltet.

**Loesung:** CSS-Override-System das die bestehenden API-HTML-Elemente
modern styled, ohne das HTML oder JavaScript zu aendern.

**Empfehlung:** Variante A "Clean Modern" als Standard, mit Flexbox-Layout
fuer Steuertermine, modernisierter Suchleiste und sauberer Typografie.
Die Farben nutzen das Deubner-Steuern-Gruen und die neuen Design Tokens.

**Scope:** Nur CSS, kein HTML/JS. JavaScript-Refactoring und eigene
Templates kommen in Phase 5 (v1.0.0) mit HTML-Parsing.
