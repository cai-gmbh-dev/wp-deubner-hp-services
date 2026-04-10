# MMB Service Frontend-Redesign: Analyse & 3 Layout-Varianten

## Status: ENTWURF - Zur Freigabe durch Architekten

**Betrifft:** Merkblaetter (`[mmb]`) - Zweiter Service in der Content-Pipeline (nach MIO)
**Erstellt:** 2026-02-14
**Verantwortlich:** UI/UX Specialist

---

## 1. IST-Analyse: Aktuelle MMB-Umsetzung

### 1.1 Content-Struktur (API-Response)

Die MMB-Seite besteht aus **zwei Hauptbereichen**, die als Roh-HTML von
`deubner-online.de/einbau/mmo/merkblattpages/php_inhalt.php` geliefert werden:

```
+------------------------------------------------------------------+
| BEREICH 1: Suchleiste                                            |
| [_________Suchbegriff_________]  [Lupe]                          |
+------------------------------------------------------------------+
| BEREICH 2: Merkblatt-Liste (5 Rubriken, Accordion)               |
|                                                                  |
| +--------------------------------------------------------------+ |
| | [Icon] Alle Steuerzahler                              [+/-]  | |
| +--------------------------------------------------------------+ |
| | (eingeklappt)                                                | |
| |  > Merkblatt-Titel 1                                         | |
| |    [Beschreibung...]                                         | |
| |    [PDF herunterladen (kdnr in URL!)]                        | |
| |  > Merkblatt-Titel 2                                         | |
| |    ...                                                       | |
| +--------------------------------------------------------------+ |
|                                                                  |
| +--------------------------------------------------------------+ |
| | [Icon] Arbeitgeber/Arbeitnehmer                       [+/-]  | |
| +--------------------------------------------------------------+ |
| | (eingeklappt)                                                | |
| +--------------------------------------------------------------+ |
|                                                                  |
| +--------------------------------------------------------------+ |
| | [Icon] GmbH-Gesellschafter                            [+/-]  | |
| +--------------------------------------------------------------+ |
| | (eingeklappt)                                                | |
| +--------------------------------------------------------------+ |
|                                                                  |
| +--------------------------------------------------------------+ |
| | [Icon] Hausbesitzer                                   [+/-]  | |
| +--------------------------------------------------------------+ |
| | (eingeklappt)                                                | |
| +--------------------------------------------------------------+ |
|                                                                  |
| +--------------------------------------------------------------+ |
| | [Icon] Unternehmer                                    [+/-]  | |
| +--------------------------------------------------------------+ |
| | (eingeklappt)                                                | |
| +--------------------------------------------------------------+ |
|                                                                  |
| Gesamt: 177 Merkblaetter mit Titel, Beschreibung, PDF-Download  |
+------------------------------------------------------------------+
```

### 1.2 HTML-Struktur (Detail)

#### JavaScript-Block (Inline am Anfang)

```html
<script language="JavaScript">
  function toggleDiv(id) { /* ... */ }
  function toggleRubrikDiv(id, rubriknr, status) { /* ... */ }
  function toggleDoubleDiv(id, id_rubrik, rubriknr) { /* ... */ }

  function showResult(suchstr, kd_nr, header) {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.open("GET",
      "https://www.deubner-online.de/einbau/mmo/merkblattpages/hintergrundladen.php"
      + "?s=" + suchstr + "&kd_nr=" + kd_nr + "&header=" + header, true);
    // SICHERHEITSPROBLEM: kdnr im Klartext!
    xmlhttp.send();
  }

  function searchReset() { /* ... */ }

  document.onkeydown = function(event) {
    if (event.keyCode == 13) {
      showResult(
        document.getElementById('suchbegriff').value,
        '0010N00004uRDoV',  // <-- KDNR HARDCODED!
        '...'
      );
    }
  };
</script>
```

#### CSS-Block (Inline)

```html
<style>
  .dummy { /* OTA-2024186296, 0010N00004uRDoV - Credentials im Kommentar! */ }
  .rubrik_header { width:100%; height:40px; background:#f1f1f1; }
  .rubrikbild_hg { width:40px; height:40px; float:left; }
  .deubner-mmb .rubrik { float:left; padding:10px 0 0 5px; }
  h5 { display:inline; }
  li.mehr { /* Mehr-Links */ }
</style>
```

#### Suchleiste

```html
<div class="mmb_suchfeld">
  <input type="text" id="suchbegriff" placeholder="Suchbegriff">
  <a href="javascript:showResult(
    document.getElementById('suchbegriff').value,
    '0010N00004uRDoV',
    '...'
  )">
    <img id="lupe" alt="lupe"
         src="https://www.deubner-online.de/.../Lupe.png">
  </a>
</div>
```

#### Rubriken-Struktur (5 Kategorien)

```html
<div id="mmb_liste">
  <!-- Rubrik 1: Alle Steuerzahler -->
  <div class="rubrik_container">
    <div class="rubrik_header" id="rubrik_header1">
      <div class="rubrikbild_hg">
        <img class="rubrikbild" src=".../icon_alle_stz1.png">
      </div>
      <div class="rubrik">
        <a href="javascript:toggleRubrikDiv('rubrik_1', 1, 1);">
          <h5 class="rubrik_n">Alle Steuerzahler</h5>
        </a>
      </div>
    </div>

    <!-- Rubrik-Inhalt (toggle, initial hidden) -->
    <div id="rubrik_1" style="display:none;">
      <ul>
        <li>
          <a href="javascript:toggleDoubleDiv('item_4711', 'rubrik_1', 1)">
            Merkblatt-Titel hier
          </a>
        </li>

        <!-- Merkblatt-Detail (toggle, initial hidden) -->
        <div id="item_4711" style="display:none;">
          <div class="merkblatt_intro">
            Beschreibung des Merkblatts...
          </div>
          <div class="merkblatt_download">
            <a href="https://www.deubner-online.de/einbau/mmo/merkblattpages/
                      mbpdf.php?kd_nr=0010N00004uRDoV&id=4711&...">
              PDF herunterladen
            </a>
          </div>
        </div>

        <!-- Weitere Merkblaetter... -->
      </ul>
    </div>
  </div>

  <!-- Rubrik 2: Arbeitgeber/Arbeitnehmer -->
  <!-- Rubrik 3: GmbH-Gesellschafter -->
  <!-- Rubrik 4: Hausbesitzer -->
  <!-- Rubrik 5: Unternehmer -->
</div>
```

### 1.3 Identifizierte Probleme

| # | Problem | Kategorie | Schweregrad |
|---|---------|-----------|------------|
| 1 | **Kundennummer (kdnr) in 177+ PDF-Download-URLs** sichtbar im Browser-Quelltext | Security | **Kritisch** |
| 2 | **Kundennummer im JavaScript** (showResult-Aufruf, onkeydown-Handler) | Security | **Kritisch** |
| 3 | **OTA-Nummer + kdnr im CSS-Kommentar** (.dummy Klasse) | Security | **Kritisch** |
| 4 | **Raw XMLHttpRequest** statt fetch/WordPress AJAX | JS | Hoch |
| 5 | **Inline onclick-Handler** (`javascript:toggleRubrikDiv(...)`, `javascript:toggleDoubleDiv(...)`) | Security/A11y | Hoch |
| 6 | **`<script language="JavaScript">`** veraltetes Attribut | Standards | Niedrig |
| 7 | **Float-Layout** (rubrikbild_hg, rubrik) statt Flexbox/Grid | Layout | Hoch |
| 8 | **Inline Styles** ueberall (`style="display:none;"`) | CSS | Hoch |
| 9 | **Kein ARIA** - Accordion ohne aria-expanded, aria-controls | A11y | Hoch |
| 10 | **Hardcodierte Farben** (#f1f1f1, etc.) | Design | Mittel |
| 11 | **Kein BEM/Namespace** - generische Klassen wie `.rubrik`, `.rubrik_header` | CSS | Mittel |
| 12 | **Externe Bilder** (Lupe.png, Rubrik-Icons) von deubner-online.de | Performance | Mittel |
| 13 | **`<div>` innerhalb `<ul>`** - invalides HTML (Merkblatt-Details als div in ul) | Standards | Mittel |
| 14 | **document.onkeydown** globaler Handler ueberschreibt moeglicherweise andere | JS/A11y | Mittel |
| 15 | **Keine Ladeindikation** bei Suche (AJAX-Request) | UX | Mittel |
| 16 | **Kein Responsive Design** - fixe Breiten (40px Icons, float:left) | Layout | Hoch |

### 1.4 Was gut funktioniert (beibehalten)

- Rubrik-basierte Kategorisierung (5 Gruppen) ist logisch und verstaendlich
- Accordion-Pattern fuer Rubriken spart Platz bei 177 Merkblaettern
- Doppeltes Accordion (Rubrik -> Merkblatt) sinnvolle Informationshierarchie
- Suchfunktion vorhanden (Freitext ueber alle Merkblaetter)
- PDF-Download direkt verfuegbar (kurze Wege zum Dokument)
- Rubrik-spezifische Icons erhoehen die Orientierung

---

## 2. Zielgruppen & Use Cases

### 2.1 Primaere Zielgruppe
**Mandanten von Steuerberatern/Rechtsanwaelten** die die Website besuchen.
- Nicht technikaffin, aeltere Altersstruktur (40-70)
- Erwartet professionelles, serioeses Erscheinungsbild
- Nutzt vor allem Desktop, zunehmend Tablet/Mobile
- Will schnell ein passendes Merkblatt finden und als PDF herunterladen

### 2.2 Sekundaere Zielgruppe
**Steuerberater/Anwaelte** die den Service auf ihrer Website einbinden.
- Erwartet dass der Service sich nahtlos in ihr Website-Design einfuegt
- Will den Service ohne CSS-Anpassungen nutzen koennen
- Bietet Merkblaetter als Mehrwert fuer ihre Mandanten an

### 2.3 Kern-Use-Cases
1. Nach Rubrik browsen (z.B. "Alle Steuerzahler") und Merkblatt auswaehlen
2. Merkblatt-Beschreibung lesen und dann PDF herunterladen
3. Nach einem bestimmten Stichwort suchen (z.B. "Erbschaftsteuer")
4. Mehrere Merkblaetter einer Rubrik durchgehen und gezielt PDFs laden

---

## 3. Drei Layout-Varianten

### WICHTIG: Content-Pipeline v0.9.0 - Eigenes HTML-Rendering

Im Gegensatz zur MIO-Phase 0.8.x (CSS-only Override) kann MMB direkt
von der **Content-Pipeline profitieren**. Da MIO bereits als Pilot-Service
migriert wurde, steht die Parser-Infrastruktur bereit:

- `DHPS_Parser_Interface` definiert den Vertrag
- `DHPS_Parser_Registry` verwaltet Service-zu-Parser-Zuordnungen
- `DHPS_Content_Pipeline` orchestriert Parse -> Cache -> Render
- `DHPS_AJAX_Proxy` proxyt AJAX-Anfragen serverseitig

**Das bedeutet: Wir generieren das HTML SELBST.** Kein CSS-Override des
API-HTML, sondern vollstaendige Kontrolle ueber Markup, Klassen und Struktur.
Die drei Layout-Varianten werden als eigene PHP-Templates umgesetzt:

- `public/views/services/mmb/default.php` (Variante A)
- `public/views/services/mmb/card.php` (Variante B)
- `public/views/services/mmb/compact.php` (Variante C)

---

### 3.1 Variante A: "Clean Modern" (Empfohlen)

**Philosophie:** Minimalistisch, viel Weissraum, klare Hierarchie.
Fokus auf schnelles Finden und Herunterladen von Merkblaettern.

```
+------------------------------------------------------------------+
|                                                                  |
|  Merkblaetter                                                    |
|  ____________________________________________________________    |
|                                                                  |
|  [________Suchbegriff________]  [Suchen]     [Reset]            |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  [Icon]  ALLE STEUERZAHLER                           (42)  [v]  |
|  ================================================================|
|                                                                  |
|  > Abfindung: So muessen Sie Ihren Abfindungsanspruch            |
|    verhandeln                                                    |
|    --------------------------------------------------------      |
|    Wenn das Arbeitsverhaeltnis endet, stellt sich oft            |
|    die Frage nach einer Abfindung...                             |
|                                                                  |
|    [PDF herunterladen]                        [Einklappen]       |
|    --------------------------------------------------------      |
|                                                                  |
|  > Abgeltungsteuer: Was Kapitalanleger wissen muessen            |
|    Kapitalanleger, Steuererklaerung                              |
|    --------------------------------------------------------      |
|                                                                  |
|  > Arbeitszimmer: Steuerliche Anerkennung...                     |
|    Einkommensteuer, Werbungskosten                               |
|    --------------------------------------------------------      |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  [Icon]  ARBEITGEBER/ARBEITNEHMER                     (38)  [v]  |
|  ================================================================|
|  (eingeklappt)                                                   |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  [Icon]  GMBH-GESELLSCHAFTER                          (27)  [v]  |
|  ================================================================|
|  (eingeklappt)                                                   |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  [Icon]  HAUSBESITZER                                 (31)  [v]  |
|  ================================================================|
|  (eingeklappt)                                                   |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  [Icon]  UNTERNEHMER                                  (39)  [v]  |
|  ================================================================|
|  (eingeklappt)                                                   |
|                                                                  |
+------------------------------------------------------------------+
```

**HTML/CSS-Charakteristiken:**
- Suchleiste als **eigenstaendige Toolbar** mit Flex-Layout (gleicher Stil wie MIO)
- Rubriken als **Accordion-Sections** mit `aria-expanded` / `aria-controls`
- Rubrik-Header: Flex-Layout (Icon + Titel + Zaehler + Chevron)
- Rubrik-Titel als **Uppercase-Label** mit Deubner-Gruen-Akzent
- Merkblaetter als **verschachtelte Accordion-Items** (doppelte Tiefe)
- Beschreibung + Download-Button im ausgeklappten Bereich
- PDF-Download ueber **AJAX-Proxy** (kdnr unsichtbar!)
- **Anzahl-Badge** pro Rubrik zeigt die Merkblatt-Zahl
- Chevron-Icon rotiert bei Auf-/Zuklappen (CSS transition)

**Farbschema:**
- Primaer: `var(--dhps-color-steuern, #2e8a37)` (Deubner Steuern Gruen)
- Text: `var(--dhps-color-text, #1a1a1a)` auf `#ffffff`
- Meta/Sekundaer: `var(--dhps-color-meta, #737373)`
- Rubrik-Header-Hintergrund: `#f8f9fa`
- Hover/Links: `#2e8a37`
- PDF-Button: Gruen-Akzent mit Download-Icon

---

### 3.2 Variante B: "Card-Based"

**Philosophie:** Jedes Merkblatt als eigenstaendige Card. Visueller,
magazinartiger Look. Gut geeignet fuer wenige angezeigte Merkblaetter
pro Rubrik.

```
+------------------------------------------------------------------+
|                                                                  |
|  Merkblaetter                                                    |
|  [________Suchbegriff________]  [Suchen]                        |
|                                                                  |
|  [Alle Steuerzahler] [Arbeitg.] [GmbH] [Haus] [Unternehmer]    |
|                                                                  |
+------------------------------------------------------------------+
|                                                                  |
|  +------------------------+  +------------------------+          |
|  | [PDF-Icon]             |  | [PDF-Icon]             |          |
|  |                        |  |                        |          |
|  | Abfindung: So muessen  |  | Abgeltungsteuer:      |          |
|  | Sie Ihren Abfindungs-  |  | Was Kapitalanleger     |          |
|  | anspruch verhandeln    |  | wissen muessen         |          |
|  |                        |  |                        |          |
|  | Wenn das Arbeitsver-   |  | Kapitalanleger koennen |          |
|  | haeltnis endet...      |  | ihre Steuerlast...     |          |
|  |                        |  |                        |          |
|  | [PDF herunterladen ->] |  | [PDF herunterladen ->] |          |
|  +------------------------+  +------------------------+          |
|                                                                  |
|  +------------------------+  +------------------------+          |
|  | [PDF-Icon]             |  | [PDF-Icon]             |          |
|  | Arbeitszimmer:         |  | Eigenheimrente:        |          |
|  | Steuerliche Aner-      |  | Wohn-Riester...        |          |
|  | kennung...             |  |                        |          |
|  |                        |  |                        |          |
|  | [PDF herunterladen ->] |  | [PDF herunterladen ->] |          |
|  +------------------------+  +------------------------+          |
|                                                                  |
|  [Mehr laden...]                                                 |
|                                                                  |
+------------------------------------------------------------------+
```

**HTML/CSS-Charakteristiken:**
- Rubriken als **Tab-Navigation** oben statt Accordion
- Merkblaetter als **2-Spalten-Grid mit Cards**
- Jede Card hat ein **PDF-Icon** oben (SVG, nicht extern)
- **Titel + Kurzvorschau** des Beschreibungstexts (truncated, 3 Zeilen max)
- Expliziter **"PDF herunterladen"-CTA** als Button in jeder Card
- **Lazy Loading**: "Mehr laden"-Button bei vielen Merkblaettern
- Cards mit `box-shadow`, `border-radius: 8px`

**Farbschema:**
- Cards: Weiss mit `box-shadow: 0 1px 3px rgba(0,0,0,0.08)`
- Tab-Leiste: Deubner-Gruen fuer aktiven Tab
- CTA-Button: Deubner-Gruen
- PDF-Icon: `#c0392b` (Rot, PDF-Standard)

---

### 3.3 Variante C: "Kompakt & Funktional"

**Philosophie:** Maximale Informationsdichte, tabellarisch.
Optimal fuer Nutzer die viele Merkblaetter schnell scannen wollen.
Ideal fuer Seitenleisten oder schmale Einbettungen.

```
+------------------------------------------------------------------+
|  Merkblaetter            [__Suche__] [Go]                        |
+------------------------------------------------------------------+
|                                                                  |
|  Alle Steuerzahler (42)                                  [+/-]  |
|  ----------------------------------------------------------------|
|  Abfindung: So muessen Sie Ihren...         | [PDF]              |
|  Abgeltungsteuer: Was Kapitalanleger...     | [PDF]              |
|  Arbeitszimmer: Steuerliche Anerkennung...  | [PDF]              |
|  Eigenheimrente: Wohn-Riester optimal...    | [PDF]              |
|  Einspruch gegen den Steuerbescheid...      | [PDF]              |
|  ...                                                             |
|                                                                  |
|  Arbeitgeber/Arbeitnehmer (38)                           [+/-]  |
|  ----------------------------------------------------------------|
|  (eingeklappt)                                                   |
|                                                                  |
|  GmbH-Gesellschafter (27)                                [+/-]  |
|  ----------------------------------------------------------------|
|  (eingeklappt)                                                   |
|                                                                  |
|  Hausbesitzer (31)                                       [+/-]  |
|  ----------------------------------------------------------------|
|  (eingeklappt)                                                   |
|                                                                  |
|  Unternehmer (39)                                        [+/-]  |
|  ----------------------------------------------------------------|
|  (eingeklappt)                                                   |
+------------------------------------------------------------------+
```

**HTML/CSS-Charakteristiken:**
- **Einzeilige Merkblatt-Eintraege** (Titel + PDF-Button)
- Kein Beschreibungstext sichtbar (nur Titel)
- PDF-Download direkt in der Zeile (Icon-Button)
- Rubriken als **Collapsible Sections** mit Merkblatt-Anzahl
- **Kein doppeltes Accordion** - flache Struktur
- Sehr **platzsparend**, ideal fuer Seitenleisten
- Monochrome Farbgebung, minimale Dekorationen

**Farbschema:**
- Monochrom: Schwarz/Weiss/Grau
- Akzent nur bei Hover und PDF-Button
- Kompakte Schriftgroessen (13px/14px)

---

## 4. Vergleichsmatrix

| Kriterium | A: Clean Modern | B: Card-Based | C: Kompakt |
|-----------|:-:|:-:|:-:|
| **Professioneller Eindruck** | +++ | ++ | ++ |
| **Informationsdichte** | ++ | + | +++ |
| **Mobile-Freundlichkeit** | +++ | ++ | +++ |
| **Theme-Kompatibilitaet** | +++ | ++ | +++ |
| **Schneller PDF-Zugang** | ++ | ++ | +++ |
| **Lesbarkeit** | +++ | +++ | ++ |
| **Barrierefreiheit** | +++ | ++ | ++ |
| **Deubner-Branding-Naehe** | +++ | ++ | + |
| **Browsing (Entdecken)** | +++ | +++ | + |
| **Umsetzungsaufwand** | Mittel | Hoch | Niedrig |

**Empfehlung: Variante A "Clean Modern"** als Standard-Layout (`layout="default"`).
Variante C als `layout="compact"` Shortcode-Option fuer Seitenleisten.
Variante B als `layout="card"` fuer Nutzer die einen visuelleren Look bevorzugen.

---

## 5. Parser-Implementierungsplan

### 5.1 DHPS_MMB_Parser (`includes/parsers/class-dhps-mmb-parser.php`)

Implementiert `DHPS_Parser_Interface`. Parst das Roh-HTML der MMB-API
und extrahiert die strukturierten Daten.

```php
<?php
class DHPS_MMB_Parser implements DHPS_Parser_Interface {

    /**
     * Parst rohes MMB-HTML in ein strukturiertes Array.
     *
     * @param string $html Rohes HTML aus der API-Antwort.
     * @return array Strukturiertes Array mit den Schluesseln:
     *               - 'categories'    (array)  Rubrik-Daten mit Merkblaettern.
     *               - 'search_config' (array)  Such-Konfiguration.
     *               - 'service_tag'   (string) 'mmb'.
     */
    public function parse( string $html ): array {
        $doc = new DOMDocument();
        $wrapped = '<html><head><meta charset="UTF-8"></head><body>'
                 . $html . '</body></html>';

        libxml_use_internal_errors( true );
        $doc->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        return array(
            'categories'    => $this->parse_categories( $doc ),
            'search_config' => $this->parse_search_config( $doc ),
            'service_tag'   => 'mmb',
        );
    }

    /**
     * Parst alle 5 Rubriken mit ihren Merkblaettern.
     *
     * Iteriert ueber div.rubrik_container und extrahiert:
     * - Rubrik-Name (h5.rubrik_n)
     * - Rubrik-Icon-URL (img.rubrikbild src)
     * - Merkblaetter (li > a fuer Titel, div#item_XXXX fuer Details)
     *
     * SICHERHEIT: PDF-URLs werden NICHT uebernommen (kdnr enthalten).
     * Stattdessen wird nur die Merkblatt-ID extrahiert.
     */
    private function parse_categories( DOMDocument $doc ): array {
        $categories = array();
        $xpath      = new DOMXPath( $doc );

        // Alle Rubrik-Container finden.
        $containers = $xpath->query( '//div[contains(@class, "rubrik_container")]' );

        foreach ( $containers as $index => $container ) {
            $category = array(
                'id'          => 'rubrik_' . ( $index + 1 ),
                'name'        => '',
                'icon_slug'   => '',
                'fact_sheets' => array(),
            );

            // Rubrik-Name aus h5.rubrik_n extrahieren.
            $name_nodes = $xpath->query( './/h5[contains(@class, "rubrik_n")]', $container );
            if ( $name_nodes->length > 0 ) {
                $category['name'] = trim( $name_nodes->item( 0 )->textContent );
            }

            // Icon-Slug aus dem img.rubrikbild src ableiten.
            $icon_nodes = $xpath->query( './/img[contains(@class, "rubrikbild")]', $container );
            if ( $icon_nodes->length > 0 ) {
                $src = $icon_nodes->item( 0 )->getAttribute( 'src' );
                // z.B. "icon_alle_stz1.png" -> "alle_stz"
                if ( preg_match( '/icon_([a-z_]+)\d*\.png/i', $src, $m ) ) {
                    $category['icon_slug'] = $m[1];
                }
            }

            // Merkblaetter parsen.
            $category['fact_sheets'] = $this->parse_fact_sheets( $container, $xpath );

            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * Parst die Merkblaetter einer Rubrik.
     *
     * Extrahiert fuer jedes Merkblatt:
     * - ID (aus dem div#item_XXXX)
     * - Titel (aus dem Toggle-Link)
     * - Beschreibung (aus div.merkblatt_intro)
     * - PDF-ID (aus der Download-URL, OHNE kdnr)
     */
    private function parse_fact_sheets( DOMElement $container, DOMXPath $xpath ): array {
        $sheets = array();

        // Merkblatt-Titel-Links finden (innerhalb der ul).
        $title_links = $xpath->query( './/ul//li/a[contains(@href, "toggleDoubleDiv")]', $container );

        foreach ( $title_links as $link ) {
            $sheet = array(
                'id'          => '',
                'title'       => trim( $link->textContent ),
                'description' => '',
                'pdf_params'  => array(),
            );

            // ID aus dem href extrahieren: toggleDoubleDiv('item_4711', ...).
            $href = $link->getAttribute( 'href' );
            if ( preg_match( "/item_(\w+)/", $href, $m ) ) {
                $sheet['id'] = $m[1];
            }

            // Detail-Container suchen (Geschwister-div mit id="item_XXXX").
            if ( ! empty( $sheet['id'] ) ) {
                $detail = $xpath->query(
                    './/div[@id="item_' . $sheet['id'] . '"]',
                    $container
                );

                if ( $detail->length > 0 ) {
                    $detail_el = $detail->item( 0 );

                    // Beschreibung aus merkblatt_intro.
                    $intro = $xpath->query(
                        './/div[contains(@class, "merkblatt_intro")]',
                        $detail_el
                    );
                    if ( $intro->length > 0 ) {
                        $sheet['description'] = trim( $intro->item( 0 )->textContent );
                    }

                    // PDF-Parameter aus Download-Link extrahieren (OHNE kdnr).
                    $pdf_link = $xpath->query(
                        './/div[contains(@class, "merkblatt_download")]//a',
                        $detail_el
                    );
                    if ( $pdf_link->length > 0 ) {
                        $pdf_href = $pdf_link->item( 0 )->getAttribute( 'href' );
                        $sheet['pdf_params'] = $this->extract_pdf_params( $pdf_href );
                    }
                }
            }

            if ( ! empty( $sheet['title'] ) ) {
                $sheets[] = $sheet;
            }
        }

        return $sheets;
    }

    /**
     * Extrahiert PDF-Parameter aus der Download-URL.
     *
     * SICHERHEIT: Die kdnr wird NICHT extrahiert!
     * Sie wird spaeter serverseitig vom AJAX-Proxy injiziert.
     *
     * Beispiel-URL:
     * mbpdf.php?kd_nr=0010N00004uRDoV&id=4711&rubrik=1&header=...
     *
     * Extrahierte Parameter: id, rubrik, header (OHNE kd_nr).
     */
    private function extract_pdf_params( string $url ): array {
        $params = array();
        $query  = wp_parse_url( $url, PHP_URL_QUERY );

        if ( ! empty( $query ) ) {
            parse_str( $query, $parsed );

            // Nur sichere Parameter uebernehmen - KEINE kd_nr!
            $safe_keys = array( 'id', 'rubrik', 'header', 'modus' );
            foreach ( $safe_keys as $key ) {
                if ( isset( $parsed[ $key ] ) ) {
                    $params[ $key ] = sanitize_text_field( $parsed[ $key ] );
                }
            }
        }

        return $params;
    }

    /**
     * Parst die Such-Konfiguration.
     */
    private function parse_search_config( DOMDocument $doc ): array {
        return array(
            'search_placeholder' => 'Suchbegriff',
            'has_search'         => true,
        );
    }
}
```

**Ergebnis-Datenstruktur:**

```php
array(
    'categories' => array(
        array(
            'id'          => 'rubrik_1',
            'name'        => 'Alle Steuerzahler',
            'icon_slug'   => 'alle_stz',
            'fact_sheets' => array(
                array(
                    'id'          => '4711',
                    'title'       => 'Abfindung: So muessen Sie...',
                    'description' => 'Wenn das Arbeitsverhaeltnis endet...',
                    'pdf_params'  => array( 'id' => '4711', 'rubrik' => '1' ),
                ),
                // ... weitere Merkblaetter
            ),
        ),
        // ... weitere Rubriken (5 insgesamt)
    ),
    'search_config' => array(
        'search_placeholder' => 'Suchbegriff',
        'has_search'         => true,
    ),
    'service_tag' => 'mmb',
)
```

### 5.2 DHPS_MMB_Search_Parser (`includes/parsers/class-dhps-mmb-search-parser.php`)

Parst die AJAX-Suchergebnisse von `hintergrundladen.php`. Die Suchfunktion
gibt ein anderes HTML-Format zurueck als die initiale Seite.

```php
<?php
class DHPS_MMB_Search_Parser {

    /**
     * Parst die AJAX-Such-Response in ein strukturiertes Array.
     *
     * @param string $html Rohes HTML aus der AJAX-Search-Response.
     * @return array Strukturiertes Array mit Suchergebnissen.
     */
    public function parse( string $html ): array {
        $result = array(
            'results'     => array(),
            'total_count' => 0,
            'query'       => '',
        );

        if ( empty( trim( $html ) ) ) {
            return $result;
        }

        $doc = new DOMDocument();
        $wrapped = '<html><head><meta charset="UTF-8"></head><body>'
                 . $html . '</body></html>';

        libxml_use_internal_errors( true );
        $doc->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        $xpath = new DOMXPath( $doc );

        // Suchergebnis-Eintraege parsen.
        // Struktur abhaengig von der tatsaechlichen API-Response.
        // Muss nach API-Test angepasst werden.
        $items = $xpath->query( '//li/a' );

        foreach ( $items as $item ) {
            $entry = array(
                'id'          => '',
                'title'       => trim( $item->textContent ),
                'description' => '',
                'pdf_params'  => array(),
            );

            // ID und Details analog zum Hauptparser extrahieren.
            // ...

            if ( ! empty( $entry['title'] ) ) {
                $result['results'][] = $entry;
            }
        }

        $result['total_count'] = count( $result['results'] );

        return $result;
    }
}
```

### 5.3 AJAX-Proxy-Erweiterung

Der bestehende `DHPS_AJAX_Proxy` muss um zwei Endpoints erweitert werden:

1. **MMB-Suche** (`dhps_mmb_search`) - Proxyt Suchanfragen an `hintergrundladen.php`
2. **MMB-PDF-Download** (`dhps_mmb_pdf`) - Proxyt PDF-Downloads ueber `mbpdf.php`

```php
<?php
// Erweiterung in DHPS_AJAX_Proxy::register()

public function register(): void {
    // Bestehend: MIO News.
    add_action( 'wp_ajax_dhps_load_news', array( $this, 'handle_news_request' ) );
    add_action( 'wp_ajax_nopriv_dhps_load_news', array( $this, 'handle_news_request' ) );

    // NEU: MMB Suche.
    add_action( 'wp_ajax_dhps_mmb_search', array( $this, 'handle_mmb_search' ) );
    add_action( 'wp_ajax_nopriv_dhps_mmb_search', array( $this, 'handle_mmb_search' ) );

    // NEU: MMB PDF-Download (Proxy).
    add_action( 'wp_ajax_dhps_mmb_pdf', array( $this, 'handle_mmb_pdf_download' ) );
    add_action( 'wp_ajax_nopriv_dhps_mmb_pdf', array( $this, 'handle_mmb_pdf_download' ) );
}

/**
 * Verarbeitet MMB-Suchanfragen.
 *
 * Ablauf:
 * 1. Nonce pruefen
 * 2. Suchbegriff sanitizen
 * 3. kdnr serverseitig aus WordPress-Options laden
 * 4. API-Aufruf an hintergrundladen.php (mit kdnr serverseitig)
 * 5. Response parsen (DHPS_MMB_Search_Parser)
 * 6. JSON zurueckgeben (OHNE kdnr!)
 */
public function handle_mmb_search(): void {
    if ( ! check_ajax_referer( 'dhps_mmb_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Ungueltige Anfrage.' ), 403 );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $search = isset( $_POST['search'] )
        ? sanitize_text_field( wp_unslash( $_POST['search'] ) )
        : '';
    // phpcs:enable

    if ( '' === $search ) {
        wp_send_json_error( array( 'message' => 'Kein Suchbegriff angegeben.' ), 400 );
    }

    // kdnr serverseitig laden - NIEMALS vom Client!
    $service = DHPS_Service_Registry::get_service( 'mmb' );
    $ota     = get_option( $service['auth_option'], '' );

    if ( '' === $ota ) {
        wp_send_json_error( array( 'message' => 'Service nicht konfiguriert.' ), 400 );
    }

    $api_params = array(
        's'     => $search,
        'kd_nr' => $ota,    // kdnr nur serverseitig!
        'modus' => 'p',
    );

    $endpoint = 'einbau/mmo/merkblattpages/hintergrundladen.php';

    // Cache pruefen.
    $cache_key = $this->cache->generate_key( $endpoint, $api_params );
    $cached    = $this->cache->get_data( $cache_key );

    if ( null !== $cached ) {
        wp_send_json_success( $cached );
    }

    // API aufrufen.
    $response = $this->api->fetch( $endpoint, $api_params );

    if ( ! $response->is_success() ) {
        wp_send_json_error(
            array( 'message' => 'Suchergebnisse konnten nicht geladen werden.' ),
            502
        );
    }

    // Response parsen.
    $parser = new DHPS_MMB_Search_Parser();
    $parsed = $parser->parse( $response->get_body() );

    // Cachen (5 Minuten fuer Suchergebnisse).
    if ( ! empty( $parsed['results'] ) ) {
        $this->cache->set_data( $cache_key, $parsed, 300 );
    }

    wp_send_json_success( $parsed );
}

/**
 * Proxyt PDF-Downloads fuer Merkblaetter.
 *
 * KRITISCH: Die kdnr wird NIEMALS an den Client gesendet.
 * Der Proxy empfaengt nur die Merkblatt-ID und baut die
 * vollstaendige URL serverseitig zusammen.
 *
 * Ablauf:
 * 1. Nonce pruefen
 * 2. Merkblatt-Parameter sanitizen (nur ID, rubrik)
 * 3. kdnr serverseitig laden
 * 4. Redirect zur vollstaendigen PDF-URL (serverseitig)
 *    ODER: PDF-Stream an den Client weiterleiten
 */
public function handle_mmb_pdf_download(): void {
    if ( ! check_ajax_referer( 'dhps_mmb_nonce', 'nonce', false ) ) {
        wp_die( 'Ungueltige Anfrage.', 403 );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $merkblatt_id = isset( $_GET['id'] )
        ? sanitize_text_field( wp_unslash( $_GET['id'] ) )
        : '';
    $rubrik = isset( $_GET['rubrik'] )
        ? absint( $_GET['rubrik'] )
        : 0;
    // phpcs:enable

    if ( '' === $merkblatt_id ) {
        wp_die( 'Fehlende Merkblatt-ID.', 400 );
    }

    // kdnr serverseitig laden.
    $service = DHPS_Service_Registry::get_service( 'mmb' );
    $ota     = get_option( $service['auth_option'], '' );

    if ( '' === $ota ) {
        wp_die( 'Service nicht konfiguriert.', 400 );
    }

    // PDF-URL serverseitig zusammenbauen.
    $pdf_url = 'https://www.deubner-online.de/einbau/mmo/merkblattpages/mbpdf.php?'
             . http_build_query( array(
                   'kd_nr'  => $ota,
                   'id'     => $merkblatt_id,
                   'rubrik' => $rubrik,
                   'modus'  => 'p',
               ) );

    // PDF-Datei vom Server laden und an Client streamen.
    $response = wp_remote_get( $pdf_url, array( 'timeout' => 30 ) );

    if ( is_wp_error( $response ) ) {
        wp_die( 'PDF konnte nicht geladen werden.', 502 );
    }

    $body         = wp_remote_retrieve_body( $response );
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );

    header( 'Content-Type: ' . ( $content_type ?: 'application/pdf' ) );
    header( 'Content-Disposition: inline; filename="merkblatt_' . $merkblatt_id . '.pdf"' );
    header( 'Content-Length: ' . strlen( $body ) );

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $body;
    exit;
}
```

### 5.4 Template-Struktur

#### `public/views/services/mmb/default.php` (Variante A)

```php
<?php
/**
 * Service-Template: MMB Standard-Layout (Clean Modern).
 *
 * Rendert die geparsten MMB-Daten (Suchleiste, Rubriken mit Merkblaettern)
 * mit modernem, semantischem HTML und BEM-CSS-Klassen.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/mmb/default.php
 *
 * Verfuegbare Variablen:
 * - $data          (array)  Strukturiertes Array aus DHPS_MMB_Parser.
 * - $service_class (string) CSS-Klasse: 'dhps-service--mmb'.
 * - $layout_class  (string) CSS-Klasse: 'dhps-layout--default'.
 * - $custom_class  (string) Optionale CSS-Klasse.
 *
 * @since 0.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$categories    = $data['categories'] ?? array();
$search_config = $data['search_config'] ?? array();
$service_tag   = $data['service_tag'] ?? 'mmb';

// MMB-JavaScript enqueuen (conditional loading).
wp_enqueue_script( 'dhps-mmb-js' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>">

    <!-- Suchleiste -->
    <?php if ( ! empty( $search_config['has_search'] ) ) : ?>
    <section class="dhps-mmb-search" aria-label="<?php echo esc_attr( 'Merkblatt-Suche' ); ?>">
        <form class="dhps-mmb-search__form" role="search" data-dhps-mmb-search>
            <div class="dhps-mmb-search__field dhps-mmb-search__field--grow">
                <label class="dhps-mmb-search__label screen-reader-text"
                       for="dhps-mmb-suchbegriff">
                    <?php echo esc_html( 'Suchbegriff' ); ?>
                </label>
                <input type="search"
                       class="dhps-mmb-search__input"
                       id="dhps-mmb-suchbegriff"
                       name="suchbegriff"
                       placeholder="<?php echo esc_attr(
                           $search_config['search_placeholder'] ?? 'Suchbegriff'
                       ); ?>"
                       data-dhps-mmb-search-input>
            </div>

            <button type="submit"
                    class="dhps-mmb-search__button"
                    aria-label="<?php echo esc_attr( 'Suchen' ); ?>"
                    data-dhps-mmb-search-submit>
                <svg class="dhps-mmb-search__icon" width="20" height="20"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </button>

            <button type="button"
                    class="dhps-mmb-search__reset"
                    aria-label="<?php echo esc_attr( 'Suche zuruecksetzen' ); ?>"
                    data-dhps-mmb-search-reset
                    hidden>
                <svg width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     aria-hidden="true">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </form>
    </section>
    <?php endif; ?>

    <!-- Suchergebnis-Container (wird per AJAX befuellt) -->
    <div class="dhps-mmb-results" data-dhps-mmb-results hidden>
        <div class="dhps-mmb-results__loading" data-dhps-mmb-loading>
            <span class="dhps-mmb-results__spinner" aria-hidden="true"></span>
            <span class="screen-reader-text">
                <?php echo esc_html( 'Suchergebnisse werden geladen...' ); ?>
            </span>
        </div>
    </div>

    <!-- Rubriken-Accordion -->
    <?php if ( ! empty( $categories ) ) : ?>
    <section class="dhps-mmb-categories"
             aria-label="<?php echo esc_attr( 'Merkblatt-Kategorien' ); ?>"
             data-dhps-mmb-categories
             data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
             data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_mmb_nonce' ) ); ?>"
             data-service-tag="<?php echo esc_attr( $service_tag ); ?>">

        <?php foreach ( $categories as $index => $category ) :
            $cat_id    = esc_attr( $category['id'] );
            $cat_count = count( $category['fact_sheets'] );
            $is_first  = ( 0 === $index );
        ?>
        <div class="dhps-mmb-category" data-dhps-mmb-category>

            <!-- Rubrik-Header (Accordion-Trigger) -->
            <h3 class="dhps-mmb-category__header">
                <button type="button"
                        class="dhps-mmb-category__trigger"
                        aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>"
                        aria-controls="dhps-mmb-<?php echo $cat_id; ?>"
                        data-dhps-mmb-category-toggle>
                    <span class="dhps-mmb-category__icon" aria-hidden="true">
                        <?php echo esc_html( $this->get_category_icon( $category['icon_slug'] ) ); ?>
                    </span>
                    <span class="dhps-mmb-category__name">
                        <?php echo esc_html( $category['name'] ); ?>
                    </span>
                    <span class="dhps-mmb-category__count" aria-label="<?php
                        echo esc_attr( $cat_count . ' Merkblaetter' );
                    ?>">
                        (<?php echo esc_html( $cat_count ); ?>)
                    </span>
                    <svg class="dhps-mmb-category__chevron" width="20" height="20"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>
            </h3>

            <!-- Rubrik-Inhalt (Merkblatt-Liste) -->
            <div class="dhps-mmb-category__content"
                 id="dhps-mmb-<?php echo $cat_id; ?>"
                 role="region"
                 aria-labelledby="dhps-mmb-<?php echo $cat_id; ?>-header"
                 <?php echo $is_first ? '' : 'aria-hidden="true"'; ?>>

                <?php if ( ! empty( $category['fact_sheets'] ) ) : ?>
                <ul class="dhps-mmb-list">
                    <?php foreach ( $category['fact_sheets'] as $sheet ) :
                        $sheet_id = esc_attr( $sheet['id'] );
                    ?>
                    <li class="dhps-mmb-item" data-dhps-mmb-item>

                        <!-- Merkblatt-Titel (Accordion-Trigger) -->
                        <button type="button"
                                class="dhps-mmb-item__title"
                                aria-expanded="false"
                                aria-controls="dhps-mmb-detail-<?php echo $sheet_id; ?>"
                                data-dhps-mmb-item-toggle>
                            <?php echo esc_html( $sheet['title'] ); ?>
                        </button>

                        <!-- Merkblatt-Detail (eingeklappt) -->
                        <div class="dhps-mmb-item__detail"
                             id="dhps-mmb-detail-<?php echo $sheet_id; ?>"
                             aria-hidden="true">

                            <?php if ( ! empty( $sheet['description'] ) ) : ?>
                            <p class="dhps-mmb-item__description">
                                <?php echo esc_html( $sheet['description'] ); ?>
                            </p>
                            <?php endif; ?>

                            <div class="dhps-mmb-item__actions">
                                <a class="dhps-mmb-item__download"
                                   href="<?php echo esc_url( admin_url( 'admin-ajax.php' ) . '?' . http_build_query( array_merge(
                                       array( 'action' => 'dhps_mmb_pdf', 'nonce' => wp_create_nonce( 'dhps_mmb_nonce' ) ),
                                       $sheet['pdf_params']
                                   ) ) ); ?>"
                                   target="_blank"
                                   rel="noopener"
                                   data-dhps-mmb-pdf="<?php echo $sheet_id; ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor"
                                         stroke-width="2" aria-hidden="true">
                                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/>
                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                    <?php echo esc_html( 'PDF herunterladen' ); ?>
                                </a>

                                <button type="button"
                                        class="dhps-mmb-item__collapse"
                                        data-dhps-mmb-item-collapse="dhps-mmb-detail-<?php echo $sheet_id; ?>">
                                    <?php echo esc_html( 'Einklappen' ); ?>
                                </button>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>

    </section>
    <?php endif; ?>

</div>
```

#### `public/views/services/mmb/card.php` (Variante B)

Analoge Struktur, aber mit Tab-Navigation fuer Rubriken und
Card-Grid fuer Merkblaetter. CSS-Klassen: `dhps-mmb-tabs__*`,
`dhps-mmb-card__*`.

#### `public/views/services/mmb/compact.php` (Variante C)

Analoge Struktur, aber ohne verschachteltes Accordion.
Direkte PDF-Buttons pro Zeile. CSS-Klassen: `dhps-mmb-compact__*`.

### 5.5 CSS (BEM-Klassen)

Alle MMB-spezifischen Styles in `css/dhps-frontend.css` ergaenzen,
mit dem Namespace `.dhps-mmb-*`.

```css
/* ==========================================================================
   MMB: Merkblatt-Suche
   ========================================================================== */

.dhps-mmb-search {
    margin-bottom: 24px;
}

.dhps-mmb-search__form {
    display: flex;
    align-items: stretch;
    gap: 8px;
}

.dhps-mmb-search__field--grow {
    flex: 1;
    min-width: 0;
}

.dhps-mmb-search__input {
    display: block;
    width: 100%;
    height: 40px;
    padding: 0 12px;
    border: 1px solid var(--dhps-color-border, #ccc);
    border-radius: 4px;
    background: #fff;
    font-size: 14px;
    color: var(--dhps-color-text, #1a1a1a);
    box-sizing: border-box;
}

.dhps-mmb-search__input:focus {
    border-color: var(--dhps-color-steuern, #2e8a37);
    outline: none;
    box-shadow: 0 0 0 2px rgba(46, 138, 55, 0.2);
}

.dhps-mmb-search__button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    padding: 0;
    border: 1px solid var(--dhps-color-steuern, #2e8a37);
    border-radius: 4px;
    background: var(--dhps-color-steuern, #2e8a37);
    color: #fff;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s;
}

.dhps-mmb-search__button:hover {
    background: #257030;
}

.dhps-mmb-search__button:focus-visible {
    outline: 2px solid var(--dhps-color-steuern, #2e8a37);
    outline-offset: 2px;
}

.dhps-mmb-search__reset {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    padding: 0;
    border: 1px solid var(--dhps-color-border, #ccc);
    border-radius: 4px;
    background: #fff;
    color: var(--dhps-color-meta, #737373);
    cursor: pointer;
    flex-shrink: 0;
    transition: color 0.2s, border-color 0.2s;
}

.dhps-mmb-search__reset:hover {
    color: var(--dhps-color-text, #1a1a1a);
    border-color: var(--dhps-color-text, #1a1a1a);
}

/* ==========================================================================
   MMB: Suchergebnisse
   ========================================================================== */

.dhps-mmb-results {
    min-height: 60px;
    margin-bottom: 24px;
}

.dhps-mmb-results[hidden] {
    display: none;
}

.dhps-mmb-results__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 0;
}

.dhps-mmb-results__spinner {
    display: inline-block;
    width: 28px;
    height: 28px;
    border: 3px solid var(--dhps-color-border, #e0e0e0);
    border-top-color: var(--dhps-color-steuern, #2e8a37);
    border-radius: 50%;
    animation: dhps-spin 0.8s linear infinite;
}

/* ==========================================================================
   MMB: Kategorien (Accordion)
   ========================================================================== */

.dhps-mmb-categories {
    /* Kein zusaetzlicher Margin - Container reicht. */
}

.dhps-mmb-category {
    border: 1px solid var(--dhps-color-border, #e0e0e0);
    border-radius: 6px;
    margin-bottom: 8px;
    overflow: hidden;
}

.dhps-mmb-category:last-child {
    margin-bottom: 0;
}

.dhps-mmb-category__header {
    margin: 0;
}

.dhps-mmb-category__trigger {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 12px 16px;
    margin: 0;
    border: none;
    background: #f8f9fa;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: var(--dhps-color-text, #1a1a1a);
    text-align: left;
    transition: background 0.2s;
    gap: 10px;
}

.dhps-mmb-category__trigger:hover {
    background: #eef0f2;
}

.dhps-mmb-category__trigger:focus-visible {
    outline: 2px solid var(--dhps-color-steuern, #2e8a37);
    outline-offset: -2px;
}

.dhps-mmb-category__trigger[aria-expanded="true"] {
    border-bottom: 1px solid var(--dhps-color-border, #e0e0e0);
}

.dhps-mmb-category__icon {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.dhps-mmb-category__name {
    flex: 1;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.dhps-mmb-category__count {
    flex-shrink: 0;
    font-size: 13px;
    font-weight: 400;
    color: var(--dhps-color-meta, #737373);
}

.dhps-mmb-category__chevron {
    flex-shrink: 0;
    transition: transform 0.3s ease;
}

.dhps-mmb-category__trigger[aria-expanded="true"] .dhps-mmb-category__chevron {
    transform: rotate(180deg);
}

/* Rubrik-Inhalt */
.dhps-mmb-category__content {
    padding: 0;
}

.dhps-mmb-category__content[aria-hidden="true"] {
    display: none;
}

/* ==========================================================================
   MMB: Merkblatt-Liste
   ========================================================================== */

.dhps-mmb-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.dhps-mmb-item {
    border-bottom: 1px solid #f0f0f0;
}

.dhps-mmb-item:last-child {
    border-bottom: none;
}

.dhps-mmb-item__title {
    display: block;
    width: 100%;
    padding: 12px 16px;
    margin: 0;
    border: none;
    background: none;
    color: var(--dhps-color-text, #1a1a1a);
    font-size: 14px;
    font-weight: 500;
    line-height: 1.4;
    text-align: left;
    cursor: pointer;
    transition: color 0.2s, background 0.2s;
}

.dhps-mmb-item__title:hover {
    color: var(--dhps-color-steuern, #2e8a37);
    background: #fafbfc;
}

.dhps-mmb-item__title:focus-visible {
    outline: 2px solid var(--dhps-color-steuern, #2e8a37);
    outline-offset: -2px;
}

.dhps-mmb-item__title[aria-expanded="true"] {
    font-weight: 600;
    color: var(--dhps-color-steuern, #2e8a37);
    background: #fafbfc;
}

/* Merkblatt-Detail */
.dhps-mmb-item__detail {
    display: none;
    padding: 0 16px 16px;
    border-left: 3px solid var(--dhps-color-steuern, #2e8a37);
    margin: 0 16px 8px;
}

.dhps-mmb-item__detail[aria-hidden="false"] {
    display: block;
}

.dhps-mmb-item__description {
    margin: 0 0 12px;
    font-size: 14px;
    line-height: 1.6;
    color: #444;
}

.dhps-mmb-item__actions {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-top: 8px;
}

.dhps-mmb-item__download {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: var(--dhps-color-steuern, #2e8a37);
    color: #fff !important;
    text-decoration: none !important;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
    transition: background 0.2s;
}

.dhps-mmb-item__download:hover {
    background: #257030;
    color: #fff !important;
}

.dhps-mmb-item__download:focus-visible {
    outline: 2px solid var(--dhps-color-steuern, #2e8a37);
    outline-offset: 2px;
}

.dhps-mmb-item__collapse {
    background: none;
    border: none;
    padding: 0;
    color: var(--dhps-color-meta, #737373);
    font-size: 13px;
    cursor: pointer;
    text-decoration: underline;
}

.dhps-mmb-item__collapse:hover {
    color: var(--dhps-color-text, #1a1a1a);
}

/* ==========================================================================
   MMB: Responsive Anpassungen
   ========================================================================== */

@media (max-width: 768px) {

    .dhps-mmb-search__form {
        flex-wrap: wrap;
    }

    .dhps-mmb-search__field--grow {
        flex: 1 1 auto;
        min-width: 0;
    }

    .dhps-mmb-category__trigger {
        padding: 10px 12px;
        font-size: 13px;
    }

    .dhps-mmb-item__title {
        padding: 10px 12px;
        font-size: 13px;
    }

    .dhps-mmb-item__detail {
        margin: 0 12px 8px;
        padding: 0 12px 12px;
    }

    .dhps-mmb-item__actions {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}
```

### 5.6 JavaScript (`public/js/dhps-mmb.js`)

Modernes Vanilla-JS fuer die MMB-Interaktionen. Kein jQuery. Keine
Inline-Handler. OTA-Kundennummer wird NICHT im Client-Code verwendet.

```javascript
/**
 * Deubner Homepage Services - MMB Frontend-JavaScript.
 *
 * - Doppeltes Accordion (Rubrik -> Merkblatt) mit ARIA
 * - AJAX-Suche ueber WordPress admin-ajax.php (serverseitiger Proxy)
 * - PDF-Download ueber Proxy (keine kdnr im Client)
 * - Enter-Taste-Handling fuer Suchfeld
 *
 * Keine externe Abhaengigkeit (kein jQuery).
 * OTA-Kundennummer wird NICHT im Client-Code verwendet.
 *
 * @package Deubner Homepage-Service
 * @since   0.9.1
 */

( function () {
    'use strict';

    function init() {
        var containers = document.querySelectorAll( '[data-dhps-mmb-categories]' );

        containers.forEach( function ( container ) {
            initInstance( container );
        } );
    }

    /**
     * Initialisiert eine MMB-Instanz.
     */
    function initInstance( container ) {
        var config = {
            ajaxUrl:    container.getAttribute( 'data-ajax-url' ),
            nonce:      container.getAttribute( 'data-nonce' ),
            serviceTag: container.getAttribute( 'data-service-tag' ),
        };

        var serviceWrapper = container.closest( '.dhps-service' );
        if ( ! serviceWrapper ) {
            return;
        }

        // Rubrik-Accordion initialisieren.
        initCategoryAccordion( container );

        // Merkblatt-Accordion initialisieren (Event-Delegation).
        initItemAccordion( container );

        // Suchfunktion initialisieren.
        initSearch( serviceWrapper, container, config );
    }

    /**
     * Rubrik-Accordion: Auf-/Zuklappen der 5 Rubriken.
     */
    function initCategoryAccordion( container ) {
        var triggers = container.querySelectorAll( '[data-dhps-mmb-category-toggle]' );

        triggers.forEach( function ( trigger ) {
            trigger.addEventListener( 'click', function () {
                var expanded  = this.getAttribute( 'aria-expanded' ) === 'true';
                var contentId = this.getAttribute( 'aria-controls' );
                var content   = document.getElementById( contentId );

                this.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );

                if ( content ) {
                    content.setAttribute( 'aria-hidden', expanded ? 'true' : 'false' );
                }
            } );
        } );
    }

    /**
     * Merkblatt-Accordion: Auf-/Zuklappen einzelner Merkblaetter.
     * Nutzt Event-Delegation auf dem Container.
     */
    function initItemAccordion( container ) {
        container.addEventListener( 'click', function ( e ) {
            // Toggle-Button.
            var toggleBtn = e.target.closest( '[data-dhps-mmb-item-toggle]' );
            if ( toggleBtn ) {
                var expanded  = toggleBtn.getAttribute( 'aria-expanded' ) === 'true';
                var detailId  = toggleBtn.getAttribute( 'aria-controls' );
                var detail    = document.getElementById( detailId );

                toggleBtn.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );

                if ( detail ) {
                    detail.setAttribute( 'aria-hidden', expanded ? 'true' : 'false' );
                }
                return;
            }

            // Collapse-Button (im Detail-Bereich).
            var collapseBtn = e.target.closest( '[data-dhps-mmb-item-collapse]' );
            if ( collapseBtn ) {
                var bodyId = collapseBtn.getAttribute( 'data-dhps-mmb-item-collapse' );
                var body   = document.getElementById( bodyId );
                var toggler = container.querySelector(
                    '[aria-controls="' + bodyId + '"]'
                );

                if ( toggler ) {
                    toggler.setAttribute( 'aria-expanded', 'false' );
                }
                if ( body ) {
                    body.setAttribute( 'aria-hidden', 'true' );
                }
            }
        } );
    }

    /**
     * Such-Initialisierung.
     */
    function initSearch( wrapper, container, config ) {
        var searchForm  = wrapper.querySelector( '[data-dhps-mmb-search]' );
        var searchInput = wrapper.querySelector( '[data-dhps-mmb-search-input]' );
        var resetBtn    = wrapper.querySelector( '[data-dhps-mmb-search-reset]' );
        var resultsDiv  = wrapper.querySelector( '[data-dhps-mmb-results]' );
        var categoriesDiv = wrapper.querySelector( '[data-dhps-mmb-categories]' );

        if ( ! searchForm || ! searchInput ) {
            return;
        }

        searchForm.addEventListener( 'submit', function ( e ) {
            e.preventDefault();
            var query = searchInput.value.trim();

            if ( '' === query ) {
                return;
            }

            performSearch( query, config, resultsDiv, categoriesDiv, resetBtn );
        } );

        if ( resetBtn ) {
            resetBtn.addEventListener( 'click', function () {
                searchInput.value = '';
                resetBtn.hidden = true;

                if ( resultsDiv ) {
                    resultsDiv.hidden = true;
                    resultsDiv.innerHTML = '';
                }

                if ( categoriesDiv ) {
                    categoriesDiv.hidden = false;
                }
            } );
        }
    }

    /**
     * Fuehrt die AJAX-Suche durch.
     */
    function performSearch( query, config, resultsDiv, categoriesDiv, resetBtn ) {
        if ( ! resultsDiv ) {
            return;
        }

        // Kategorien ausblenden, Ergebnisse zeigen.
        if ( categoriesDiv ) {
            categoriesDiv.hidden = true;
        }

        resultsDiv.hidden = false;
        resultsDiv.innerHTML =
            '<div class="dhps-mmb-results__loading">' +
            '<span class="dhps-mmb-results__spinner" aria-hidden="true"></span>' +
            '<span class="screen-reader-text">Suchergebnisse werden geladen...</span>' +
            '</div>';

        if ( resetBtn ) {
            resetBtn.hidden = false;
        }

        var formData = new FormData();
        formData.append( 'action', 'dhps_mmb_search' );
        formData.append( 'nonce', config.nonce );
        formData.append( 'search', query );

        fetch( config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        } )
            .then( function ( response ) { return response.json(); } )
            .then( function ( result ) {
                if ( result.success && result.data ) {
                    renderSearchResults( resultsDiv, result.data, config );
                } else {
                    renderSearchError( resultsDiv,
                        result.data && result.data.message
                            ? result.data.message
                            : 'Fehler bei der Suche.' );
                }
            } )
            .catch( function () {
                renderSearchError( resultsDiv,
                    'Verbindungsfehler. Bitte versuchen Sie es erneut.' );
            } );
    }

    /**
     * Rendert die Suchergebnisse.
     */
    function renderSearchResults( container, data, config ) {
        if ( ! data.results || data.results.length === 0 ) {
            container.innerHTML =
                '<p class="dhps-mmb-results__empty">' +
                'Keine Merkblaetter gefunden.' +
                '</p>';
            return;
        }

        var html = '<div class="dhps-mmb-results__header">' +
            '<strong>' + data.total_count + ' Merkblaetter gefunden</strong>' +
            '</div>';

        html += '<ul class="dhps-mmb-list">';

        data.results.forEach( function ( item ) {
            var sheetId = escapeAttr( item.id );

            html += '<li class="dhps-mmb-item">';
            html += '<button type="button" class="dhps-mmb-item__title"' +
                ' aria-expanded="false"' +
                ' aria-controls="dhps-mmb-search-detail-' + sheetId + '"' +
                ' data-dhps-mmb-item-toggle>' +
                escapeHtml( item.title ) +
                '</button>';

            html += '<div class="dhps-mmb-item__detail"' +
                ' id="dhps-mmb-search-detail-' + sheetId + '"' +
                ' aria-hidden="true">';

            if ( item.description ) {
                html += '<p class="dhps-mmb-item__description">' +
                    escapeHtml( item.description ) + '</p>';
            }

            html += '<div class="dhps-mmb-item__actions">';
            html += '<a class="dhps-mmb-item__download"' +
                ' href="' + escapeAttr( config.ajaxUrl ) +
                '?action=dhps_mmb_pdf&nonce=' + escapeAttr( config.nonce ) +
                '&id=' + sheetId + '"' +
                ' target="_blank" rel="noopener">' +
                'PDF herunterladen</a>';
            html += '</div>';

            html += '</div>';
            html += '</li>';
        } );

        html += '</ul>';

        container.innerHTML = html;

        // Event-Delegation fuer Suchergebnis-Accordion.
        initItemAccordion( container );
    }

    /**
     * Zeigt eine Fehlermeldung in den Suchergebnissen.
     */
    function renderSearchError( container, message ) {
        container.innerHTML =
            '<p class="dhps-mmb-results__error" role="alert">' +
            escapeHtml( message ) +
            '</p>';
    }

    function escapeHtml( str ) {
        var div = document.createElement( 'div' );
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeAttr( str ) {
        return str
            .replace( /&/g, '&amp;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#39;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' );
    }

    // Initialisieren wenn DOM bereit.
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
```

---

## 6. Betroffene Dateien & Aufwand

| Datei | Aenderung | Aufwand |
|-------|-----------|---------|
| `includes/parsers/class-dhps-mmb-parser.php` | **NEU** - MMB-HTML-Parser (DOMDocument) | Mittel |
| `includes/parsers/class-dhps-mmb-search-parser.php` | **NEU** - Parser fuer AJAX-Suchergebnisse | Klein |
| `includes/class-dhps-ajax-proxy.php` | **ERWEITERN** - `handle_mmb_search()`, `handle_mmb_pdf_download()` | Mittel |
| `includes/class-dhps-parser-registry.php` | Keine Aenderung (bereits generisch) | - |
| `includes/class-dhps-content-pipeline.php` | Keine Aenderung (bereits generisch) | - |
| `Deubner_HP_Services.php` | MMB-Parser registrieren, JS/CSS enqueuen | Klein |
| `public/views/services/mmb/default.php` | **NEU** - Standard-Layout-Template (Variante A) | Mittel |
| `public/views/services/mmb/card.php` | **NEU** - Card-Layout-Template (Variante B) | Mittel |
| `public/views/services/mmb/compact.php` | **NEU** - Kompakt-Layout-Template (Variante C) | Klein |
| `public/js/dhps-mmb.js` | **NEU** - Accordion + Suche + PDF-Proxy-Integration | Mittel |
| `css/dhps-frontend.css` | **ERWEITERN** - MMB-spezifische BEM-Klassen | Mittel |

**Gesamtaufwand:** ca. 3-4 Entwicklertage

### 6.1 Wiederverwendung aus MIO

Folgende Patterns koennen direkt von MIO uebernommen werden:

- Content-Pipeline-Integration (identischer Ablauf)
- CSS-Variablen und Design-Tokens (gleiche Farben, Steuern-Kategorie)
- Suchleisten-Styling (nahezu identisch)
- Spinner/Loading-Indikator (identische Komponente)
- JS-Patterns: Event-Delegation, `escapeHtml()`, `escapeAttr()`, IIFE
- AJAX-Proxy-Pattern (Nonce, serverseitige OTA-Injektion)

---

## 7. Sicherheitsanalyse

### 7.1 Kritische Befunde im IST-Zustand

| # | Befund | Risiko | Massnahme |
|---|--------|--------|-----------|
| 1 | **kdnr `0010N00004uRDoV` in 177 PDF-Download-URLs** sichtbar im Seitenquelltext | **Kritisch** - Jeder Besucher sieht die Kundennummer | PDF-Downloads ueber AJAX-Proxy (`handle_mmb_pdf_download`). Template generiert nur Proxy-URLs mit Merkblatt-ID. kdnr wird serverseitig aus `wp_options` geladen. |
| 2 | **kdnr im JavaScript** (`showResult()`-Aufruf, `document.onkeydown`-Handler) | **Kritisch** - kdnr im Client-JS sichtbar | Inline-JS wird durch Parser komplett entfernt. Eigenes `dhps-mmb.js` nutzt AJAX-Proxy. kdnr existiert nicht mehr im Frontend. |
| 3 | **OTA-Nummer + kdnr im CSS-Kommentar** (`.dummy`-Klasse) | **Hoch** - Credentials im Quelltext | Inline-CSS wird durch Parser entfernt. Eigene BEM-Klassen in `dhps-frontend.css`. Keine API-Credentials im generierten HTML. |
| 4 | **kdnr im AJAX-Such-Request** (GET-Parameter, im Browser-Netzwerk-Tab sichtbar) | **Hoch** - kdnr bei jeder Suche exponiert | Such-Requests gehen an WordPress AJAX-Proxy. Client sendet nur den Suchbegriff. kdnr wird serverseitig injiziert. |

### 7.2 Sicherheitsarchitektur nach Migration

```
  VORHER (unsicher):
  +----------+    kdnr im GET    +------------------+
  | Browser  | ----------------> | deubner-online.de|
  |          | <-- PDF-Datei --- | /mbpdf.php       |
  +----------+                   +------------------+
       |
       | kdnr sichtbar in:
       | - HTML-Quelltext (177x)
       | - JavaScript-Code
       | - CSS-Kommentar
       | - Network-Tab (Suche)

  NACHHER (sicher):
  +----------+    nur MB-ID     +------------------+    kdnr serverseitig    +------------------+
  | Browser  | ---------------> | WordPress        | ----------------------> | deubner-online.de|
  |          | <-- PDF-Datei -- | AJAX-Proxy       | <---- PDF-Datei ------ | /mbpdf.php       |
  +----------+                  +------------------+                        +------------------+
       |                              |
       | kdnr NICHT sichtbar          | kdnr aus wp_options
       | - Kein Inline-JS             | (get_option('dhps_mmo_ota'))
       | - Kein Inline-CSS            |
       | - Proxy-URLs ohne kdnr       |
       | - AJAX mit Nonce-Schutz      |
```

### 7.3 Zusaetzliche Sicherheitsmassnahmen

- **Nonce-Validierung**: Alle AJAX-Requests (Suche + PDF) pruefen `dhps_mmb_nonce`
- **Input-Sanitization**: `sanitize_text_field()` fuer Suchbegriff, `absint()` fuer IDs
- **Output-Escaping**: `esc_html()`, `esc_attr()`, `esc_url()` in allen Templates
- **Rate-Limiting**: Ueberlegung fuer zukuenftige Versionen (viele PDF-Downloads)
- **Content-Disposition**: PDF wird mit korrektem Header gestreamt (`inline; filename=...`)

---

## 8. Umsetzungsreihenfolge

| Prio | Schritt | Beschreibung | Abhaengigkeit |
|------|---------|-------------|---------------|
| 1 | `DHPS_MMB_Parser` erstellen | HTML-Parser mit DOMDocument, parse_categories(), parse_fact_sheets() | Parser-Interface (besteht) |
| 2 | Parser in Registry registrieren | `DHPS_Parser_Registry::register( 'mmb', new DHPS_MMB_Parser() )` in Plugin-Bootstrap | Schritt 1 |
| 3 | `default.php` Template erstellen | Variante A "Clean Modern" mit BEM-Klassen und ARIA | Schritt 1 (Datenstruktur) |
| 4 | CSS ergaenzen | MMB-spezifische BEM-Klassen in `dhps-frontend.css` | Schritt 3 |
| 5 | AJAX-Proxy erweitern | `handle_mmb_search()` und `handle_mmb_pdf_download()` | Schritt 1 |
| 6 | `dhps-mmb.js` erstellen | Accordion-Logik, AJAX-Suche, PDF-Proxy-Integration | Schritt 3, 5 |
| 7 | `DHPS_MMB_Search_Parser` erstellen | Parser fuer AJAX-Suchergebnisse | Schritt 5 (API-Test noetig) |
| 8 | JS/CSS Conditional Loading | Nur bei aktivem `[mmb]`-Shortcode laden | Schritt 4, 6 |
| 9 | Responsive Testing | Alle Breakpoints testen (mobile, tablet, desktop) | Schritt 3, 4, 6 |
| 10 | `card.php` Template erstellen | Variante B mit Tab-Navigation | Schritt 3 (als Basis) |
| 11 | `compact.php` Template erstellen | Variante C mit flacher Struktur | Schritt 3 (als Basis) |
| 12 | Sicherheitstest | Verifizieren: keine kdnr im generierten HTML/JS/CSS | Schritt 1-6 |

---

## 9. Hinweise fuer den Architekten

### 9.1 Parser-Registrierung

In `Deubner_HP_Services.php` (Plugin-Bootstrap), analog zu MIO:

```php
// MMB-Parser registrieren.
require_once DEUBNER_HP_SERVICES_PATH . 'includes/parsers/class-dhps-mmb-parser.php';
DHPS_Parser_Registry::register( 'mmb', new DHPS_MMB_Parser() );
```

### 9.2 JS/CSS Conditional Loading

```php
// In Deubner_HP_Services.php oder DHPS_Shortcodes:
wp_register_script(
    'dhps-mmb-js',
    DEUBNER_HP_SERVICES_URL . 'public/js/dhps-mmb.js',
    array(),
    DEUBNER_HP_SERVICES_VERSION,
    true // In Footer laden.
);

// Das Script wird erst im Template per wp_enqueue_script() aktiviert.
```

### 9.3 PDF-Proxy: Stream vs. Redirect

Zwei Ansaetze fuer den PDF-Download-Proxy:

**Option A: Server-Stream (empfohlen)**
- WordPress laedt PDF und streamt es an den Client
- kdnr ist NIRGENDS sichtbar (auch nicht in der finalen URL)
- Hoehere Server-Last (Bandbreite)
- Im Code-Beispiel oben umgesetzt

**Option B: Signed-URL-Redirect**
- WordPress generiert eine signierte URL mit Zeitstempel
- Redirect an `mbpdf.php` mit kdnr (aber nur kurzlebig sichtbar)
- Niedrigere Server-Last
- kdnr kurz in der Browser-Adresszeile sichtbar (weniger sicher)

**Empfehlung: Option A** (Server-Stream), da die Sicherheit Prioritaet hat
und PDF-Dateien typischerweise klein sind (< 2 MB).

### 9.4 Doppeltes Accordion: Keyboard-Navigation

Das MMB-UI hat ein doppeltes Accordion (Rubrik -> Merkblatt).
Fuer korrekte Keyboard-Navigation (WAI-ARIA Accordion Pattern):

- `Tab`/`Shift+Tab`: Navigiert zwischen Accordion-Triggern
- `Enter`/`Space`: Toggled den aktuellen Accordion-Bereich
- `Home`/`End`: Springt zum ersten/letzten Accordion-Header (optional)
- Innere Merkblatt-Trigger werden erst per Tab erreichbar wenn
  die aeussere Rubrik geoffnet ist

### 9.5 Caching-Strategie

- **L1 Cache** (Raw HTML): TTL aus Shortcode-Attribut `cache="3600"` (1 Stunde)
- **L2 Cache** (Parsed Data): Gleiche TTL wie L1 (automatisch via Pipeline)
- **AJAX-Such-Cache**: 300 Sekunden (5 Minuten) - kuerzere TTL da dynamisch
- **PDF-Downloads**: Kein Caching (direkt durchstreamen)

### 9.6 CSS-Variablen und Kategorie-Farben

MMB gehoert zur Kategorie `steuern`. Die Primaerfarbe wird ueber
CSS-Variablen gesteuert:

```css
/* Bereits in Design Tokens definiert */
:root {
    --dhps-color-steuern: #2e8a37;
    --dhps-color-text: #1a1a1a;
    --dhps-color-meta: #737373;
    --dhps-color-border: #e0e0e0;
}
```

Falls ein Theme die Variablen ueberschreibt, passt sich das MMB-Styling
automatisch an. Die Fallback-Werte in den CSS-Regeln (`var(--dhps-color-steuern, #2e8a37)`)
stellen sicher, dass das Layout auch ohne CSS-Variablen funktioniert.

### 9.7 Suchfunktion: Fallback bei leerer API-Response

Die AJAX-Such-API (`hintergrundladen.php`) muss vor der Parser-Implementierung
manuell getestet werden, um das genaue HTML-Format zu verifizieren.
Die Struktur des `DHPS_MMB_Search_Parser` muss moeglicherweise angepasst werden.

Fallback: Wenn die Such-API ein unbekanntes Format liefert, wird das
rohe HTML als Fehlermeldung im Suchergebnis-Container angezeigt
(mit Hinweis an den Entwickler im Konsolen-Log).

---

## 10. Zusammenfassung

**Problem:** Die MMB-Seite (Merkblaetter) hat **kritische Sicherheitsprobleme**
(Kundennummer in 177+ URLs, im JavaScript und im CSS sichtbar) und nutzt
veraltetes Legacy-HTML (Float-Layout, Inline-Styles, kein ARIA, globale
Event-Handler).

**Loesung:** Vollstaendige Migration in die Content-Pipeline v0.9.0.
Ein `DHPS_MMB_Parser` extrahiert die 5 Rubriken mit 177 Merkblaettern
in strukturierte PHP-Arrays. Eigene Templates (`default.php`, `card.php`,
`compact.php`) rendern modernes, semantisches HTML mit BEM-Klassen und
ARIA-Attributen. PDF-Downloads laufen ueber einen serverseitigen AJAX-Proxy,
der die Kundennummer ausschliesslich auf dem Server injiziert.

**Empfehlung:** Variante A "Clean Modern" als Standard-Layout. Doppeltes
Accordion (Rubrik -> Merkblatt) mit prominenter Suchleiste und PDF-Download-
Buttons. Saubere Typografie, Deubner-Steuern-Gruen als Akzentfarbe,
responsive Design fuer alle Breakpoints.

**Sicherheitsgewinn:** Nach der Migration ist die Kundennummer
(`0010N00004uRDoV`) an **keiner Stelle** mehr im Browser-Quelltext,
im JavaScript oder in CSS-Kommentaren sichtbar. Alle API-Aufrufe
(Suche + PDF-Download) laufen ueber den WordPress-AJAX-Proxy mit
Nonce-Validierung.

**Scope:** Parser + Templates + JS + CSS + AJAX-Proxy-Erweiterung.
Geschaetzter Aufwand: 3-4 Entwicklertage. Baut auf der bestehenden
MIO-Pipeline-Infrastruktur auf und folgt den gleichen Architektur-Patterns.
