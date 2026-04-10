# TP Service Frontend-Redesign: Analyse & 3 Layout-Varianten

## Status: ENTWURF - Zur Freigabe durch Architekten

**Betrifft:** TaxPlain Videos (`[tp]`) - Zweiter Service in der Content-Pipeline
**Erstellt:** 2026-02-14
**Verantwortlich:** UI/UX Specialist

---

## 1. IST-Analyse: Aktuelle TP-Umsetzung

### 1.1 Content-Struktur (API-Response)

Die TP-Seite besteht aus **zwei Hauptbereichen**, die als Roh-HTML von
`deubner-online.de/einbau/taxplain/videopages/php_inhalt.php` geliefert werden.
Die Authentifizierung erfolgt ueber OTA (One-Time-Auth, OTA-2023182947).
Die Response umfasst ca. 260KB HTML und enthaelt ca. 30 eingebettete Videos.

```
+------------------------------------------------------------------+
| [PHP Deprecated Warning - Bug in API-Response!]                   |
+------------------------------------------------------------------+
| BEREICH 1: Aktueller Video-Tipp (Featured Video)                 |
| <h3 class="ues_akt_vt">Der aktuelle Video-Tipp</h3>             |
| +------------------------------------------------------+         |
| | <div class="aktuelles_video">                        |         |
| |   <div class="videoblock">                           |         |
| |     <h5 class="videotitel">Senkung der USt...</h5>   |         |
| |     <iframe class="inlinevideo"                      |         |
| |       src="mandantenvideo.de/...                     |         |
| |       ?video=Taxplain_...&poster=...                 |         |
| |       &kdnr=0010N00004uRDoV  <-- SICHERHEIT!        |         |
| |       &v_modus=0&service=taxplain"                   |         |
| |       width="500" height="291" />                    |         |
| |     <div class="teaser">Beschreibung... (11/25)</div>|         |
| |     <div class="sharebuttons-element">               |         |
| |       [Mail] [Facebook] [Twitter] [XING] [LinkedIn]  |         |
| |       (alle URLs enthalten kdnr!)                    |         |
| |     </div>                                           |         |
| |   </div>                                             |         |
| +------------------------------------------------------+         |
+------------------------------------------------------------------+
| <hr class="line_alle_videos">                                     |
+------------------------------------------------------------------+
| BEREICH 2: Alle Video-Tipps (nach Kategorie)                      |
| <h3 class="ues_alle_vt">Alle Video-Tipps</h3>                   |
|                                                                    |
| <h4 class="rubrik">Fuer alle Steuerzahler</h4>                   |
| +------------------------------------------------------+         |
| | <table><tr class="videozeile">                       |         |
| |   <td class="videosymbol"><img src="...png"></td>    |         |
| |   <td class="videotitel"><a id="L81">Titel</a></td> |         |
| | </tr></table>                                        |         |
| | <div class="videoblock_rubrik" id="81"               |         |
| |      style="display:none;">                          |         |
| |   [iframe + teaser + sharebuttons - wie oben]        |         |
| | </div>                                               |         |
| +------------------------------------------------------+         |
| | [Weiteres Video...]                                  |         |
| +------------------------------------------------------+         |
|                                                                    |
| <h4 class="rubrik">Fuer Arbeitgeber und Arbeitnehmer</h4>        |
| | [Videos dieser Kategorie...]                         |         |
|                                                                    |
| <h4 class="rubrik">Fuer GmbH-Gesellschafter/-GF</h4>             |
| | [Videos dieser Kategorie...]                         |         |
|                                                                    |
| <h4 class="rubrik">Fuer Unternehmer</h4>                          |
| | [Videos dieser Kategorie...]                         |         |
+------------------------------------------------------------------+
```

### 1.2 HTML-Struktur (Detail)

#### PHP-Warnung (Bug in API-Response)

Die API-Antwort beginnt mit einer PHP Deprecated Warning:

```
Deprecated: preg_replace(): Passing null to parameter #3 ($subject) of type array|string
is deprecated in .../einbau/taxplain/videopages/php_inhalt.php on line 560
```

Diese Warnung wird im Browser als Klartext vor dem eigentlichen Content angezeigt.

#### Featured Video (Aktueller Video-Tipp)

```html
<h3 class="ues_akt_vt">Der aktuelle Video-Tipp</h3>
<div class="aktuelles_video">
  <div class="videoblock">
    <h5 class="videotitel">Senkung der Umsatzsteuer in der Gastronomie</h5>
    <iframe class="inlinevideo" scrolling="no"
      src="https://www.mandantenvideo.de/commons/bin_videos/videoshow_simple.html
        ?video=Taxplain_Umsatzsteuer_Gastronomie_Movie121125
        &poster=https://www.deubner-online.de/taxplain/videopages/images/senkung_umsatzsteuer_gastro.png
        &kdnr=0010N00004uRDoV
        &v_modus=0
        &service=taxplain"
      width="500" height="291" frameborder="0"
      webkitallowfullscreen mozallowfullscreen allowfullscreen>
    </iframe>
    <div class="teaser">
      Die Umsatzsteuer in der Gastronomie wurde gesenkt... (11/25)
    </div>
    <div class="sharebuttons-element">
      <a href="mailto:?subject=...&body=...kdnr=0010N00004uRDoV...">Mail</a>
      <a href="https://www.facebook.com/sharer.php?u=...kdnr=0010N00004uRDoV...">Facebook</a>
      <a href="https://twitter.com/share?url=...kdnr=0010N00004uRDoV...">Twitter</a>
      <a href="https://www.xing.com/spi/shares/new?url=...kdnr=0010N00004uRDoV...">XING</a>
      <a href="https://www.linkedin.com/shareArticle?url=...kdnr=0010N00004uRDoV...">LinkedIn</a>
    </div>
  </div>
</div>
```

#### Rubrik-Videos (Alle Video-Tipps)

```html
<hr class="line_alle_videos">
<h3 class="ues_alle_vt">Alle Video-Tipps</h3>

<h4 class="rubrik">Fuer alle Steuerzahler</h4>

<!-- Video-Zeile (zusammengeklappt) -->
<table>
  <tr class="videozeile">
    <td class="videosymbol">
      <a href="javascript:toggleDiv('81')">
        <img src=".../videoicon.png" alt="Video">
      </a>
    </td>
    <td class="videotitel">
      <a href="javascript:toggleDiv('81')" id="L81">
        Senkung der Umsatzsteuer in der Gastronomie
      </a>
    </td>
  </tr>
</table>

<!-- Video-Block (ausgeklappt, initial versteckt) -->
<div class="videoblock_rubrik" id="81" style="display:none;">
  <h5 class="videotitel">Senkung der Umsatzsteuer in der Gastronomie</h5>
  <iframe class="inlinevideo" scrolling="no"
    src="https://www.mandantenvideo.de/commons/bin_videos/videoshow_simple.html
      ?video=Taxplain_Umsatzsteuer_Gastronomie_Movie121125
      &poster=https://www.deubner-online.de/.../senkung_umsatzsteuer_gastro.png
      &kdnr=0010N00004uRDoV
      &v_modus=0
      &service=taxplain"
    width="500" height="291" frameborder="0"
    webkitallowfullscreen mozallowfullscreen allowfullscreen>
  </iframe>
  <div class="teaser">Beschreibungstext... (11/25)</div>
  <div class="sharebuttons-element">
    [Mail] [Facebook] [Twitter] [XING] [LinkedIn]
  </div>
</div>

<!-- Naechste Rubrik -->
<h4 class="rubrik">Fuer Arbeitgeber und Arbeitnehmer</h4>
<!-- ... weitere Videos ... -->
```

#### Video-Elemente je Video (Datenmodell)

| Feld | Quelle | Beispiel |
|------|--------|---------|
| `video_id` | `id`-Attribut am `div.videoblock_rubrik` | `81` |
| `video_slug` | `?video=`-Parameter in iframe-src | `Taxplain_Umsatzsteuer_Gastronomie_Movie121125` |
| `poster_url` | `?poster=`-Parameter in iframe-src | `https://www.deubner-online.de/.../senkung_umsatzsteuer_gastro.png` |
| `titel` | `h5.videotitel` | `Senkung der Umsatzsteuer in der Gastronomie` |
| `teaser` | `div.teaser` (ohne Datums-Suffix) | `Die Umsatzsteuer in der Gastronomie wurde gesenkt...` |
| `datum` | Im Teaser als `(MM/YY)` | `(11/25)` = November 2025 |
| `iframe_src` | `iframe.inlinevideo[src]` (ENTHAELT kdnr!) | `https://www.mandantenvideo.de/commons/...` |
| `share_urls` | Links in `div.sharebuttons-element` (ENTHALTEN kdnr!) | 5 Social-Media-Links |
| `rubrik` | Uebergeordnetes `h4.rubrik` | `Fuer alle Steuerzahler` |
| `is_featured` | Innerhalb `div.aktuelles_video` | `true/false` |

### 1.3 Identifizierte Probleme

| # | Problem | Kategorie | Schweregrad |
|---|---------|-----------|-------------|
| 1 | **PHP Deprecated Warning** in API-Response (preg_replace null) | Bug | Kritisch |
| 2 | **kdnr im iframe-src** sichtbar im Browser-Quelltext | Security | Kritisch |
| 3 | **kdnr in allen Social-Share-URLs** (Mail, Facebook, Twitter, XING, LinkedIn) | Security | Kritisch |
| 4 | **Alle 30 iframes gleichzeitig geladen** - massive Performance-Belastung | Performance | Kritisch |
| 5 | **Table-Layout** fuer Videoliste statt CSS Grid/Flex | Layout | Hoch |
| 6 | **Inline Styles** (`style="display:none;"`) fuer Toggle-State | CSS | Hoch |
| 7 | **Inline onclick-Handler** (`javascript:toggleDiv(...)`) | Security/A11y | Hoch |
| 8 | **Kein ARIA** - keine Barrierefreiheit fuer Videobereich | A11y | Hoch |
| 9 | **Feste Breite** `width="500"` auf iframes - nicht responsive | Layout | Hoch |
| 10 | **Veraltete iframe-Attribute** (`frameborder`, `scrolling`) | Standards | Niedrig |
| 11 | **Veraltete Fullscreen-Prefixes** (`webkitallowfullscreen`, `mozallowfullscreen`) | Standards | Niedrig |
| 12 | **Kein Lazy Loading** - iframes laden auch im versteckten Zustand | Performance | Hoch |
| 13 | **Hardcodierte Farben** in Legacy-Klassen | Design | Mittel |
| 14 | **Kein BEM/Namespace** - generische Klassen wie `.videoblock`, `.teaser` | CSS | Mittel |
| 15 | **Social-Icons** als externe Bilder (Ladezeit) | Performance | Mittel |
| 16 | **Poster-Images nicht als Platzhalter genutzt** - iframe laedt sofort | UX | Hoch |

### 1.4 Was gut funktioniert (beibehalten)

- Featured-Video-Konzept ("Aktueller Video-Tipp") als Einstieg ist sinnvoll
- Kategorisierung in 4 Rubriken hilft beim Browsing
- Toggle-Mechanismus (aufklappen/zuklappen) spart Platz bei 30 Videos
- Social-Share-Optionen sind umfangreich (5 Kanaele)
- Poster-Images sind auf deubner-online.de gehostet (kein externes Tracking)
- Teaser-Texte mit Datumsangabe sind informativ
- Video-Hosting auf mandantenvideo.de (eigene Infrastruktur, kein YouTube)

---

## 2. Zielgruppen & Use Cases

### 2.1 Primaere Zielgruppe

**Mandanten von Steuerberatern/Rechtsanwaelten** die die Website besuchen.
- Nicht technikaffin, aeltere Altersstruktur (40-70)
- Erwartet professionelles, serioeses Erscheinungsbild
- Nutzt vor allem Desktop, zunehmend Tablet/Mobile
- Will verstaendliche Steuertipps als Video konsumieren
- Bevorzugt kurze, praegnante Erklaervideos

### 2.2 Sekundaere Zielgruppe

**Steuerberater/Anwaelte** die den Service auf ihrer Website einbinden.
- Erwartet dass der Video-Service sich nahtlos in ihr Website-Design einfuegt
- Will den Service ohne CSS-Anpassungen nutzen koennen
- Erwartet professionelle Darstellung fuer ihre Mandanten
- Moechte einzelne Videos oder Teaser gezielt einbetten koennen

### 2.3 Kern-Use-Cases

1. Aktuelles Video anschauen (Featured Video als Einstieg)
2. Videos nach Kategorie browsen (4 Rubriken)
3. Ein bestimmtes Video abspielen und Details lesen
4. Ein Video per Social Media oder E-Mail teilen
5. Einzelnes Video als Teaser auf Unterseite einbetten (via TPT-Shortcode)

---

## 3. Drei Layout-Varianten

### WICHTIG: Architektur-Vorteil der Content-Pipeline (v0.9.0)

Anders als beim initialen MIO-Redesign (Phase 0.8.x, CSS-Override-System)
wird TP direkt mit der neuen Content-Pipeline umgesetzt. Das bedeutet:

- Wir **generieren das HTML selbst** ueber Templates
- Das API-HTML wird durch `DHPS_TP_Parser` in strukturierte Daten umgewandelt
- Die Templates unter `public/views/services/tp/` erzeugen semantisches HTML
- Kein CSS-Override-Hack, sondern **sauberes BEM-CSS von Grund auf**
- Sicherheitsprobleme (kdnr-Exposure) werden **architektonisch** geloest

Die folgenden Varianten beschreiben daher **vollstaendige Template-Designs**,
nicht nur CSS-Overrides.

---

### 3.1 Variante A: "Clean Modern" (Empfohlen)

**Philosophie:** Featured Video prominent, darunter uebersichtliches
Kategorie-Browsing. Poster-Images als Platzhalter, Lazy-Loading der iframes.
Modern, viel Weissraum, klare Hierarchie.

```
+------------------------------------------------------------------+
|                                                                    |
|  Der aktuelle Video-Tipp                                           |
|  +------------------------------------------------------+         |
|  |                                                      |         |
|  |  +------------------------------------------+        |         |
|  |  |                                          |        |         |
|  |  |           [POSTER IMAGE]                 |        |         |
|  |  |              [ > PLAY ]                  |        |         |
|  |  |                                          |        |         |
|  |  +------------------------------------------+        |         |
|  |                                                      |         |
|  |  Senkung der Umsatzsteuer in der Gastronomie         |         |
|  |  Die Umsatzsteuer wurde gesenkt...     Nov. 2025     |         |
|  |                                                      |         |
|  |  [Mail] [Facebook] [Twitter] [XING] [LinkedIn]       |         |
|  +------------------------------------------------------+         |
|                                                                    |
+------------------------------------------------------------------+
|                                                                    |
|  Alle Video-Tipps                                                  |
|                                                                    |
|  [Alle] [Steuerzahler] [Arbeitg./Arbeitn.] [GmbH] [Unternehmer] |
|                                                                    |
|  +------------------------+ +------------------------+            |
|  | [POSTER]               | | [POSTER]               |            |
|  | [ > ]                  | | [ > ]                  |            |
|  |                        | |                        |            |
|  | Titel des Videos       | | Titel des Videos       |            |
|  | Nov. 2025              | | Okt. 2025              |            |
|  +------------------------+ +------------------------+            |
|  +------------------------+ +------------------------+            |
|  | [POSTER]               | | [POSTER]               |            |
|  | [ > ]                  | | [ > ]                  |            |
|  |                        | |                        |            |
|  | Titel des Videos       | | Titel des Videos       |            |
|  | Sep. 2025              | | Aug. 2025              |            |
|  +------------------------+ +------------------------+            |
|                                                                    |
+------------------------------------------------------------------+
```

**Klick auf ein Video oeffnet den Videoblock inline:**

```
+------------------------------------------------------------------+
|  +------------------------------------------------------+         |
|  | [POSTER expandiert -> iframe]                        |         |
|  |                                                      |         |
|  |  +------------------------------------------+        |         |
|  |  |         [VIDEO PLAYER / IFRAME]          |        |         |
|  |  |              (16:9 ratio)                |        |         |
|  |  +------------------------------------------+        |         |
|  |                                                      |         |
|  |  Senkung der Umsatzsteuer in der Gastronomie         |         |
|  |  Die Umsatzsteuer in der Gastronomie wurde gesenkt.  |         |
|  |  Ab dem 1. Juli gilt der reduzierte Satz von 7%...   |         |
|  |                                           Nov. 2025   |         |
|  |                                                      |         |
|  |  [Mail] [Facebook] [Twitter] [XING] [LinkedIn]       |         |
|  |                                   [Schliessen]       |         |
|  +------------------------------------------------------+         |
+------------------------------------------------------------------+
```

**Template-Charakteristiken:**
- Featured Video in prominenter Card mit grossem Poster-Image
- Poster-Image als Platzhalter; iframe erst bei Klick auf Play laden
- Kategorien als **Tab-/Pill-Navigation** (filternd, kein Neuladen)
- Video-Grid als **2-Spalten Responsive Grid** mit Poster-Thumbnails
- Inline-Expansion: Klick auf Video expandiert Card und laedt iframe
- Social-Share mit **einheitlichen SVG-Icons** (kein externer Bildabruf)
- Datum human-readable formatiert (z.B. "Nov. 2025" statt "(11/25)")

**Farbschema:**
- Primaer: `var(--dhps-color-steuern, #2e8a37)` (Deubner Steuern Gruen)
- Text: `var(--dhps-color-text, #1a1a1a)` auf `#ffffff`
- Meta/Sekundaer: `var(--dhps-color-meta, #737373)`
- Play-Button-Overlay: halbtransparentes Weiss auf Poster
- Hover/Links: `#2e8a37`
- Active Tab: `#2e8a37` mit weisser Schrift

**Responsive Verhalten:**
- Desktop (>768px): 2-Spalten-Grid, Featured Video volle Breite
- Tablet (480-768px): 2-Spalten-Grid, kleinere Thumbnails
- Mobile (<480px): 1-Spalte, Featured Video volle Breite, Cards stacked

---

### 3.2 Variante B: "Card-Based Gallery"

**Philosophie:** Alle Videos gleichberechtigt als Cards, kein hervorgehobenes
Featured Video. Magazin-Look mit visueller Betonung der Poster-Images.
Grid-Ansicht wie eine Video-Bibliothek.

```
+------------------------------------------------------------------+
|                                                                    |
|  TaxPlain Video-Tipps                                              |
|                                                                    |
|  [Alle] [Steuerzahler] [Arbeitg./Arbeitn.] [GmbH] [Unternehmer] |
|                                                                    |
|  +--------------------+ +--------------------+ +----------------+ |
|  | [POSTER]           | | [POSTER]           | | [POSTER]       | |
|  | [ > ]              | | [ > ]              | | [ > ]          | |
|  |                    | |                    | |                | |
|  | Titel des Videos   | | Titel des Videos   | | Titel          | |
|  | Beschreibung...    | | Beschreibung...    | | Beschr...      | |
|  |                    | |                    | |                | |
|  | [Steuerzahler]     | | [Arbeitnehmer]     | | [GmbH]         | |
|  | Nov. 2025          | | Okt. 2025          | | Sep. 2025      | |
|  +--------------------+ +--------------------+ +----------------+ |
|  +--------------------+ +--------------------+ +----------------+ |
|  | [POSTER]           | | [POSTER]           | | [POSTER]       | |
|  | [ > ]              | | [ > ]              | | [ > ]          | |
|  | ...                | | ...                | | ...            | |
|  +--------------------+ +--------------------+ +----------------+ |
|                                                                    |
|              [--- Weitere Videos laden ---]                        |
|                                                                    |
+------------------------------------------------------------------+
```

**Template-Charakteristiken:**
- Alle Videos in einem **3-Spalten-Grid** (kein Featured Video)
- Jede Card zeigt: Poster, Titel, Teaser (2 Zeilen, truncated), Kategorie-Badge, Datum
- Klick oeffnet **Overlay/Modal** mit Videoplayer statt Inline-Expansion
- Kategorie als **farbige Pill/Badge** auf jeder Card
- Optional: "Mehr laden"-Button (Pagination bei 30 Videos)
- Poster-Aspekt-Verhaeltnis: 16:9 (passend zum Videoformat)

**Farbschema:**
- Cards: Weiss mit `box-shadow`, `border-radius: 8px`
- Kategorie-Badges: Pastellfarben je Rubrik
- CTA/Play-Overlay: Deubner-Gruen

**Responsive Verhalten:**
- Desktop (>1024px): 3-Spalten-Grid
- Tablet (768-1024px): 2-Spalten-Grid
- Mobile (<768px): 1-Spalte

---

### 3.3 Variante C: "Kompakt"

**Philosophie:** Maximale Informationsdichte, minimaler Platzverbrauch.
Ideal fuer Einbettung in Seitenleisten oder als kompakte Video-Liste
auf Unterseiten. Kein visueller Overhead.

```
+------------------------------------------------------------------+
|                                                                    |
|  TaxPlain Video-Tipps                                              |
|                                                                    |
|  Fuer alle Steuerzahler (12)                               [+/-] |
|  ---------------------------------------------------------------- |
|  [>] Senkung der Umsatzsteuer in der Gastronomie    | Nov. 2025  |
|  [>] Energetische Sanierung: Steuerbonus nutzen      | Okt. 2025  |
|  [>] Inflationsausgleichspraemie richtig nutzen      | Sep. 2025  |
|  [>] Grundsteuer 2025: Was sich aendert              | Aug. 2025  |
|  ...                                                              |
|                                                                    |
|  Fuer Arbeitgeber und Arbeitnehmer (8)                     [+/-] |
|  ---------------------------------------------------------------- |
|  [>] Homeoffice-Pauschale: Steuerlich absetzen       | Nov. 2025  |
|  [>] Dienstwagen: 1%-Regelung oder Fahrtenbuch?     | Okt. 2025  |
|  ...                                                              |
|                                                                    |
|  Fuer GmbH-Gesellschafter/-GF (5)                          [+/-] |
|  ---------------------------------------------------------------- |
|  ...                                                              |
|                                                                    |
|  Fuer Unternehmer (5)                                       [+/-] |
|  ---------------------------------------------------------------- |
|  ...                                                              |
|                                                                    |
+------------------------------------------------------------------+
```

**Klick auf eine Video-Zeile oeffnet den Player inline:**

```
|  [>] Senkung der Umsatzsteuer in der Gastronomie    | Nov. 2025  |
|  +------------------------------------------------------+        |
|  |  +------------------------------------------+        |        |
|  |  |         [VIDEO PLAYER / IFRAME]          |        |        |
|  |  +------------------------------------------+        |        |
|  |  Beschreibungstext...                                |        |
|  |  [Mail] [Facebook] [Twitter] [XING] [LinkedIn]       |        |
|  +------------------------------------------------------+        |
|  [>] Energetische Sanierung: Steuerbonus nutzen      | Okt. 2025  |
```

**Template-Charakteristiken:**
- Kein Featured Video, kein Poster-Grid
- **Accordion-Sections** pro Rubrik mit Artikelanzahl
- Jedes Video als **einzeilige Zeile** (Titel + Datum)
- Klick expandiert inline: kompakter Videoplayer + Teaser + Share
- Kein visueller Overhead, sehr **platzsparend**
- Ideal als `layout="compact"` fuer Seitenleisten

**Farbschema:**
- Monochrom: Schwarz/Weiss/Grau
- Akzent nur bei Hover und aktivem Video (Deubner-Gruen)
- Kompakte Schriftgroessen

**Responsive Verhalten:**
- Einspaltig bei jeder Breite (inherent responsive)
- Datum-Spalte wird unter Titel verschoben bei sehr schmalen Viewports

---

## 4. Vergleichsmatrix

| Kriterium | A: Clean Modern | B: Card Gallery | C: Kompakt |
|-----------|:-:|:-:|:-:|
| **Professioneller Eindruck** | +++ | +++ | ++ |
| **Video-Fokus / Visuelle Wirkung** | +++ | +++ | + |
| **Informationsdichte** | ++ | + | +++ |
| **Mobile-Freundlichkeit** | +++ | ++ | +++ |
| **Theme-Kompatibilitaet** | +++ | ++ | +++ |
| **Performance (Lazy Loading)** | +++ | ++ | +++ |
| **Lesbarkeit** | +++ | +++ | ++ |
| **Barrierefreiheit** | +++ | ++ | ++ |
| **Deubner-Branding-Naehe** | +++ | ++ | + |
| **Poster-Image-Nutzung** | +++ | +++ | - |
| **Umsetzungsaufwand** | Mittel | Hoch | Niedrig |

**Empfehlung: Variante A "Clean Modern"** als Standard-Layout (`layout="default"`).
Variante C als `layout="compact"` fuer platzsparende Einbettungen.
Variante B als optionales `layout="card"` fuer visuell orientierte Seiten.

---

## 5. Parser-Implementierungsplan

### 5.1 DHPS_TP_Parser (`includes/parsers/class-dhps-tp-parser.php`)

Der Parser implementiert `DHPS_Parser_Interface` und transformiert das
rohe API-HTML in ein strukturiertes Array:

```php
class DHPS_TP_Parser implements DHPS_Parser_Interface {

    /**
     * Parst rohes TP-HTML in ein strukturiertes Array.
     *
     * @param string $html Rohes HTML aus der API-Antwort.
     * @return array Strukturiertes Array mit geparsten Daten.
     */
    public function parse( string $html ): array {
        // 1. PHP-Warnings aus Response entfernen.
        $html = $this->strip_php_warnings( $html );

        // 2. DOMDocument laden.
        $doc = new DOMDocument();
        $wrapped = '<html><head><meta charset="UTF-8"></head><body>'
                 . $html . '</body></html>';
        libxml_use_internal_errors( true );
        $doc->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();

        return array(
            'featured_video' => $this->parse_featured_video( $doc ),
            'categories'     => $this->parse_categories( $doc ),
            'service_tag'    => 'tp',
        );
    }
}
```

#### Parse-Ergebnis-Struktur

```php
array(
    'featured_video' => array(
        'video_id'   => '82',
        'video_slug' => 'Taxplain_Umsatzsteuer_Gastronomie_Movie121125',
        'poster_url' => 'https://www.deubner-online.de/.../senkung_umsatzsteuer_gastro.png',
        'titel'      => 'Senkung der Umsatzsteuer in der Gastronomie',
        'teaser'     => 'Die Umsatzsteuer in der Gastronomie wurde gesenkt...',
        'datum'      => '11/25',
        // KEIN iframe_src! KEIN kdnr! Wird template-seitig generiert.
    ),
    'categories' => array(
        array(
            'name'   => 'Fuer alle Steuerzahler',
            'videos' => array(
                array(
                    'video_id'   => '81',
                    'video_slug' => 'Taxplain_Umsatzsteuer_Gastronomie_Movie121125',
                    'poster_url' => 'https://www.deubner-online.de/.../image.png',
                    'titel'      => 'Senkung der Umsatzsteuer in der Gastronomie',
                    'teaser'     => 'Beschreibungstext...',
                    'datum'      => '11/25',
                ),
                // ... weitere Videos
            ),
        ),
        array(
            'name'   => 'Fuer Arbeitgeber und Arbeitnehmer',
            'videos' => array( /* ... */ ),
        ),
        array(
            'name'   => 'Fuer GmbH-Gesellschafter/-GF',
            'videos' => array( /* ... */ ),
        ),
        array(
            'name'   => 'Fuer Unternehmer',
            'videos' => array( /* ... */ ),
        ),
    ),
    'service_tag' => 'tp',
)
```

#### Zentrale Parser-Methoden

```php
/**
 * Entfernt PHP-Warnings/Notices aus der API-Response.
 * Erkennt Muster wie "Deprecated:", "Warning:", "Notice:".
 */
private function strip_php_warnings( string $html ): string {
    // Entferne alles vor dem ersten HTML-Tag.
    $html = preg_replace(
        '/^.*?(?=<[a-zA-Z])/s',
        '',
        $html
    );
    return $html;
}

/**
 * Extrahiert das Featured Video aus div.aktuelles_video.
 */
private function parse_featured_video( DOMDocument $doc ): ?array {
    // 1. div.aktuelles_video finden
    // 2. iframe-src parsen: video_slug und poster_url extrahieren
    // 3. kdnr aus den extrahierten Daten ENTFERNEN
    // 4. Titel aus h5.videotitel
    // 5. Teaser + Datum aus div.teaser
}

/**
 * Extrahiert alle Kategorien und deren Videos.
 */
private function parse_categories( DOMDocument $doc ): array {
    // 1. Alle h4.rubrik finden
    // 2. Fuer jede Rubrik: nachfolgende videoblock_rubrik-Divs sammeln
    // 3. Jedes Video parsen: iframe-src, Titel, Teaser, Datum
    // 4. kdnr aus allen Daten ENTFERNEN
}

/**
 * Parst eine iframe-src und extrahiert video_slug und poster_url.
 * ENTFERNT kdnr und andere sensitive Parameter.
 */
private function parse_iframe_src( string $src ): array {
    $parts = wp_parse_url( $src );
    parse_str( $parts['query'] ?? '', $query );

    return array(
        'video_slug' => $query['video'] ?? '',
        'poster_url' => $query['poster'] ?? '',
        // kdnr wird BEWUSST nicht extrahiert!
        // v_modus und service werden fuer den Proxy benoetigt.
        'v_modus'    => $query['v_modus'] ?? '0',
        'service'    => $query['service'] ?? 'taxplain',
    );
}

/**
 * Extrahiert Datum aus Teaser-Text.
 * Pattern: "(MM/YY)" am Ende des Textes.
 */
private function extract_datum( string $teaser ): array {
    $datum  = '';
    $clean  = $teaser;

    if ( preg_match( '/\((\d{1,2}\/\d{2})\)\s*$/', $teaser, $matches ) ) {
        $datum = $matches[1];
        $clean = trim( preg_replace( '/\(\d{1,2}\/\d{2}\)\s*$/', '', $teaser ) );
    }

    return array(
        'teaser' => $clean,
        'datum'  => $datum,
    );
}
```

### 5.2 AJAX-Proxy fuer Video-Embedding

Das zentrale Sicherheitsproblem: Die Video-iframes benoetigen die `kdnr`
als URL-Parameter, aber diese darf nicht im Browser-Quelltext sichtbar sein.

**Loesung: WordPress AJAX-Proxy**

```php
/**
 * AJAX-Handler: Generiert iframe-src mit serverseitig injizierter kdnr.
 *
 * Frontend sendet: video_slug, poster_url, v_modus, service
 * Backend ergaenzt: kdnr (aus Datenbank-Option dhps_ota_tp)
 * Rueckgabe: Vollstaendige iframe-src-URL
 *
 * ODER alternativ: Proxy leitet die Video-Anfrage direkt weiter
 * und streamt den Video-Player-HTML-Code ohne kdnr-Exposure.
 */
add_action( 'wp_ajax_nopriv_dhps_tp_video_src', 'dhps_tp_video_src_handler' );
add_action( 'wp_ajax_dhps_tp_video_src', 'dhps_tp_video_src_handler' );

function dhps_tp_video_src_handler() {
    check_ajax_referer( 'dhps_tp_nonce', 'nonce' );

    $video_slug = sanitize_text_field( $_POST['video_slug'] ?? '' );
    $poster_url = esc_url_raw( $_POST['poster_url'] ?? '' );
    $v_modus    = absint( $_POST['v_modus'] ?? 0 );

    if ( empty( $video_slug ) ) {
        wp_send_json_error( 'Missing video_slug' );
    }

    // kdnr serverseitig aus der Datenbank holen.
    $kdnr = get_option( 'dhps_tp_kdnr', '' );

    if ( empty( $kdnr ) ) {
        wp_send_json_error( 'Service not configured' );
    }

    // Vollstaendige iframe-URL zusammenbauen.
    $iframe_src = add_query_arg(
        array(
            'video'   => $video_slug,
            'poster'  => $poster_url,
            'kdnr'    => $kdnr,
            'v_modus' => $v_modus,
            'service' => 'taxplain',
        ),
        'https://www.mandantenvideo.de/commons/bin_videos/videoshow_simple.html'
    );

    wp_send_json_success( array( 'src' => $iframe_src ) );
}
```

**Alternativer Ansatz (bevorzugt): Signierte URLs**

Statt die vollstaendige URL per AJAX zu liefern, kann der Server eine
zeitlich begrenzte, signierte URL generieren:

```php
$signature = hash_hmac( 'sha256', $video_slug . $kdnr . time(), wp_salt() );
$proxy_url = add_query_arg(
    array(
        'action' => 'dhps_tp_embed',
        'video'  => $video_slug,
        'sig'    => $signature,
        'exp'    => time() + 3600,
    ),
    admin_url( 'admin-ajax.php' )
);
```

### 5.3 Template-Struktur

Drei Templates unter `public/views/services/tp/`:

#### `default.php` (Variante A - Clean Modern)

```php
<?php
/**
 * Service-Template: TP Standard-Layout.
 *
 * Rendert die geparsten TP-Daten (Featured Video, Kategorien mit Videos)
 * mit modernem, semantischem HTML und BEM-CSS-Klassen.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/tp/default.php
 *
 * Verfuegbare Variablen:
 * - $data          (array)  Strukturiertes Array aus DHPS_TP_Parser.
 * - $service_class (string) CSS-Klasse: 'dhps-service--tp'.
 * - $layout_class  (string) CSS-Klasse: 'dhps-layout--default'.
 * - $custom_class  (string) Optionale CSS-Klasse.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/TP
 * @since      0.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$featured    = $data['featured_video'] ?? null;
$categories  = $data['categories'] ?? array();
$service_tag = $data['service_tag'] ?? 'tp';

wp_enqueue_script( 'dhps-tp-js' );
wp_enqueue_style( 'dhps-tp-css' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' '
     . $layout_class . $custom_class ); ?>">

    <?php if ( $featured ) : ?>
    <!-- Featured Video -->
    <section class="dhps-tp-featured" aria-label="Aktueller Video-Tipp">
        <h3 class="dhps-tp-featured__heading">Der aktuelle Video-Tipp</h3>
        <div class="dhps-tp-video dhps-tp-video--featured"
             data-video-slug="<?php echo esc_attr( $featured['video_slug'] ); ?>"
             data-poster-url="<?php echo esc_url( $featured['poster_url'] ); ?>"
             data-v-modus="<?php echo esc_attr( $featured['v_modus'] ?? '0' ); ?>">
            <div class="dhps-tp-video__player">
                <div class="dhps-tp-video__poster" role="button" tabindex="0"
                     aria-label="Video abspielen: <?php echo esc_attr( $featured['titel'] ); ?>">
                    <img src="<?php echo esc_url( $featured['poster_url'] ); ?>"
                         alt="<?php echo esc_attr( $featured['titel'] ); ?>"
                         class="dhps-tp-video__poster-img"
                         loading="lazy" width="500" height="291">
                    <span class="dhps-tp-video__play-btn" aria-hidden="true">
                        <svg width="64" height="64" viewBox="0 0 64 64">
                            <circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.9)"/>
                            <polygon points="26,20 26,44 46,32" fill="#2e8a37"/>
                        </svg>
                    </span>
                </div>
                <!-- iframe wird per JS bei Klick eingefuegt -->
            </div>
            <div class="dhps-tp-video__info">
                <h4 class="dhps-tp-video__title">
                    <?php echo esc_html( $featured['titel'] ); ?>
                </h4>
                <p class="dhps-tp-video__teaser">
                    <?php echo esc_html( $featured['teaser'] ); ?>
                </p>
                <span class="dhps-tp-video__date">
                    <?php echo esc_html( $featured['datum'] ); ?>
                </span>
            </div>
            <div class="dhps-tp-video__share" data-dhps-share>
                <!-- Share-Links werden per Template generiert (kdnr serverseitig) -->
            </div>
        </div>
    </section>

    <hr class="dhps-divider">
    <?php endif; ?>

    <!-- Alle Video-Tipps -->
    <section class="dhps-tp-catalog" aria-label="Alle Video-Tipps">
        <h3 class="dhps-tp-catalog__heading">Alle Video-Tipps</h3>

        <!-- Kategorie-Filter -->
        <nav class="dhps-tp-catalog__filter" aria-label="Kategorien">
            <button class="dhps-tp-filter__btn dhps-tp-filter__btn--active"
                    data-filter="all" aria-pressed="true">
                Alle
            </button>
            <?php foreach ( $categories as $index => $cat ) : ?>
            <button class="dhps-tp-filter__btn"
                    data-filter="<?php echo esc_attr( $index ); ?>"
                    aria-pressed="false">
                <?php echo esc_html( $cat['name'] ); ?>
            </button>
            <?php endforeach; ?>
        </nav>

        <!-- Video-Grid -->
        <div class="dhps-tp-grid">
            <?php foreach ( $categories as $cat_index => $cat ) : ?>
                <?php foreach ( $cat['videos'] as $video ) : ?>
                <article class="dhps-tp-card"
                         data-category="<?php echo esc_attr( $cat_index ); ?>"
                         data-video-id="<?php echo esc_attr( $video['video_id'] ); ?>">
                    <div class="dhps-tp-card__poster" role="button" tabindex="0"
                         aria-label="Video abspielen: <?php
                             echo esc_attr( $video['titel'] ); ?>"
                         data-video-slug="<?php echo esc_attr( $video['video_slug'] ); ?>"
                         data-poster-url="<?php echo esc_url( $video['poster_url'] ); ?>"
                         data-v-modus="<?php echo esc_attr( $video['v_modus'] ?? '0' ); ?>">
                        <img src="<?php echo esc_url( $video['poster_url'] ); ?>"
                             alt="<?php echo esc_attr( $video['titel'] ); ?>"
                             class="dhps-tp-card__img"
                             loading="lazy" width="500" height="291">
                        <span class="dhps-tp-card__play-btn" aria-hidden="true">
                            <svg width="48" height="48" viewBox="0 0 64 64">
                                <circle cx="32" cy="32" r="30"
                                        fill="rgba(255,255,255,0.9)"/>
                                <polygon points="26,20 26,44 46,32" fill="#2e8a37"/>
                            </svg>
                        </span>
                    </div>
                    <div class="dhps-tp-card__body">
                        <h4 class="dhps-tp-card__title">
                            <?php echo esc_html( $video['titel'] ); ?>
                        </h4>
                        <span class="dhps-tp-card__date">
                            <?php echo esc_html( $video['datum'] ); ?>
                        </span>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </section>

</div>
```

#### `card.php` (Variante B - Card Gallery)

Wie `default.php`, aber:
- Kein separates Featured Video
- Alle Videos gleichberechtigt im Grid
- Card-Wrapper (`div.dhps-card`) fuer Box-Shadow/Padding
- 3-Spalten-Grid statt 2-Spalten

#### `compact.php` (Variante C - Kompakt)

Wie `default.php`, aber:
- Kein Featured Video, kein Poster-Grid
- Accordion-Sections pro Rubrik
- Einzeilige Video-Zeilen (Titel + Datum)
- Klick expandiert inline mit kompaktem Player

### 5.4 CSS (`css/dhps-frontend.css` - Erweiterung)

BEM-Klassen im `.dhps-tp-*` Namespace:

```css
/* ==========================================================================
   TP: Featured Video
   ========================================================================== */

.dhps-tp-featured {
    margin-bottom: 24px;
}

.dhps-tp-featured__heading {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--dhps-color-steuern, #2e8a37);
    margin: 0 0 16px;
}

/* ==========================================================================
   TP: Video-Player (Poster + iframe)
   ========================================================================== */

.dhps-tp-video__player {
    position: relative;
    width: 100%;
    padding-top: 58.2%;           /* 291/500 = 58.2% (API aspect ratio) */
    background: #000;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 12px;
}

.dhps-tp-video__poster {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.dhps-tp-video__poster-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dhps-tp-video__play-btn {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    transition: transform 0.2s ease, opacity 0.2s ease;
    opacity: 0.9;
}

.dhps-tp-video__poster:hover .dhps-tp-video__play-btn {
    transform: translate(-50%, -50%) scale(1.1);
    opacity: 1;
}

.dhps-tp-video__poster:focus-visible {
    outline: 3px solid var(--dhps-color-steuern, #2e8a37);
    outline-offset: 2px;
}

/* iframe (nach Klick eingefuegt) */
.dhps-tp-video__iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 0;
}

/* ==========================================================================
   TP: Video-Info
   ========================================================================== */

.dhps-tp-video__title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dhps-color-text, #1a1a1a);
    margin: 0 0 4px;
    line-height: 1.4;
}

.dhps-tp-video__teaser {
    font-size: 0.9375rem;
    color: var(--dhps-color-text, #1a1a1a);
    line-height: 1.6;
    margin: 0 0 8px;
}

.dhps-tp-video__date {
    font-size: 0.8125rem;
    color: var(--dhps-color-meta, #737373);
}

/* ==========================================================================
   TP: Kategorie-Filter (Tab-Navigation)
   ========================================================================== */

.dhps-tp-catalog__heading {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--dhps-color-text, #1a1a1a);
    margin: 0 0 16px;
}

.dhps-tp-catalog__filter {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
}

.dhps-tp-filter__btn {
    padding: 6px 14px;
    border: 1px solid #d0d0d0;
    border-radius: 20px;
    background: #fff;
    color: var(--dhps-color-text, #1a1a1a);
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.dhps-tp-filter__btn:hover {
    border-color: var(--dhps-color-steuern, #2e8a37);
    color: var(--dhps-color-steuern, #2e8a37);
}

.dhps-tp-filter__btn--active,
.dhps-tp-filter__btn[aria-pressed="true"] {
    background: var(--dhps-color-steuern, #2e8a37);
    border-color: var(--dhps-color-steuern, #2e8a37);
    color: #fff;
}

.dhps-tp-filter__btn:focus-visible {
    outline: 2px solid var(--dhps-color-steuern, #2e8a37);
    outline-offset: 2px;
}

/* ==========================================================================
   TP: Video-Grid
   ========================================================================== */

.dhps-tp-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

/* ==========================================================================
   TP: Video-Card (im Grid)
   ========================================================================== */

.dhps-tp-card {
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    border: 1px solid var(--dhps-color-border, #e0e0e0);
    transition: box-shadow 0.2s ease;
}

.dhps-tp-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.dhps-tp-card__poster {
    position: relative;
    width: 100%;
    padding-top: 58.2%;
    background: #f0f0f0;
    cursor: pointer;
    overflow: hidden;
}

.dhps-tp-card__img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.dhps-tp-card:hover .dhps-tp-card__img {
    transform: scale(1.03);
}

.dhps-tp-card__play-btn {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    opacity: 0;
    transition: opacity 0.2s ease;
}

.dhps-tp-card:hover .dhps-tp-card__play-btn {
    opacity: 1;
}

.dhps-tp-card__poster:focus-visible {
    outline: 3px solid var(--dhps-color-steuern, #2e8a37);
    outline-offset: -3px;
}

.dhps-tp-card__body {
    padding: 12px 16px;
}

.dhps-tp-card__title {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--dhps-color-text, #1a1a1a);
    margin: 0 0 4px;
    line-height: 1.4;
}

.dhps-tp-card__date {
    font-size: 0.8125rem;
    color: var(--dhps-color-meta, #737373);
}

/* ==========================================================================
   TP: Share-Buttons
   ========================================================================== */

.dhps-tp-video__share {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
}

.dhps-tp-share__link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    background: #f0f0f0;
    color: #666;
    text-decoration: none;
    transition: background 0.2s, color 0.2s;
}

.dhps-tp-share__link:hover {
    background: var(--dhps-color-steuern, #2e8a37);
    color: #fff;
}

.dhps-tp-share__link:focus-visible {
    outline: 2px solid var(--dhps-color-steuern, #2e8a37);
    outline-offset: 2px;
}

/* ==========================================================================
   TP: Expanded Video (nach Klick im Grid)
   ========================================================================== */

.dhps-tp-card--expanded {
    grid-column: 1 / -1;
}

.dhps-tp-card--expanded .dhps-tp-card__detail {
    padding: 16px;
}

.dhps-tp-card--expanded .dhps-tp-card__close {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 12px;
    padding: 6px 12px;
    border: 1px solid #d0d0d0;
    border-radius: 4px;
    background: #fff;
    color: var(--dhps-color-text, #1a1a1a);
    font-size: 0.8125rem;
    cursor: pointer;
    transition: border-color 0.2s;
}

.dhps-tp-card--expanded .dhps-tp-card__close:hover {
    border-color: var(--dhps-color-steuern, #2e8a37);
}

/* ==========================================================================
   TP: Responsive
   ========================================================================== */

@media (max-width: 768px) {
    .dhps-tp-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .dhps-tp-catalog__filter {
        gap: 6px;
    }

    .dhps-tp-filter__btn {
        font-size: 0.75rem;
        padding: 4px 10px;
    }
}

@media (max-width: 480px) {
    .dhps-tp-video__player {
        border-radius: 4px;
    }

    .dhps-tp-card {
        border-radius: 4px;
    }

    .dhps-tp-card__body {
        padding: 8px 12px;
    }
}
```

### 5.5 JavaScript (`public/js/dhps-tp.js`)

```javascript
/**
 * TaxPlain Videos - Frontend-Interaktionen.
 *
 * - Lazy Loading: iframes erst bei Klick auf Poster laden
 * - Kategorie-Filter: Videos nach Rubrik filtern
 * - Video-Toggle: Grid-Card expandieren/kollabieren
 * - Print: Einzelnes Video drucken
 *
 * @package Deubner Homepage-Service
 * @since   0.10.0
 */
(function () {
    'use strict';

    /**
     * Initialisiert alle TP-Video-Container auf der Seite.
     */
    function init() {
        document.querySelectorAll('.dhps-service--tp').forEach(function (container) {
            initLazyVideoLoading(container);
            initCategoryFilter(container);
            initVideoToggle(container);
        });
    }

    /**
     * Lazy Loading: Poster durch iframe ersetzen bei Klick.
     *
     * SICHERHEIT: Die iframe-src wird ueber einen AJAX-Proxy geladen,
     * damit die kdnr nicht im Quelltext sichtbar ist.
     */
    function initLazyVideoLoading(container) {
        container.querySelectorAll('[data-video-slug]').forEach(function (el) {
            el.addEventListener('click', handleVideoClick);
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleVideoClick.call(this, e);
                }
            });
        });
    }

    /**
     * Laedt den Video-iframe ueber den AJAX-Proxy.
     */
    function handleVideoClick(e) {
        var poster = this;
        var player = poster.closest('.dhps-tp-video__player')
                  || poster.closest('.dhps-tp-card__poster');

        if (!player || player.querySelector('iframe')) {
            return; // Bereits geladen.
        }

        var videoSlug = poster.dataset.videoSlug;
        var posterUrl = poster.dataset.posterUrl;
        var vModus    = poster.dataset.vModus || '0';

        // AJAX-Request an WordPress-Proxy.
        var formData = new FormData();
        formData.append('action', 'dhps_tp_video_src');
        formData.append('nonce', dhpsTpConfig.nonce);
        formData.append('video_slug', videoSlug);
        formData.append('poster_url', posterUrl);
        formData.append('v_modus', vModus);

        fetch(dhpsTpConfig.ajaxUrl, {
            method: 'POST',
            body: formData,
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success && data.data.src) {
                var iframe = document.createElement('iframe');
                iframe.className = 'dhps-tp-video__iframe';
                iframe.src = data.data.src;
                iframe.setAttribute('allowfullscreen', '');
                iframe.setAttribute('title', poster.getAttribute('aria-label') || '');

                // Poster ausblenden, iframe einblenden.
                var posterEl = player.querySelector('.dhps-tp-video__poster')
                            || player;
                posterEl.style.display = 'none';

                // iframe in den Player-Container einfuegen.
                var playerContainer = posterEl.parentElement;
                playerContainer.appendChild(iframe);
            }
        })
        .catch(function (error) {
            console.error('DHPS TP: Video konnte nicht geladen werden.', error);
        });
    }

    /**
     * Kategorie-Filter: Zeigt/versteckt Video-Cards basierend auf Kategorie.
     */
    function initCategoryFilter(container) {
        var buttons = container.querySelectorAll('.dhps-tp-filter__btn');
        var cards   = container.querySelectorAll('.dhps-tp-card');

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var filter = this.dataset.filter;

                // Button-States aktualisieren.
                buttons.forEach(function (b) {
                    b.classList.remove('dhps-tp-filter__btn--active');
                    b.setAttribute('aria-pressed', 'false');
                });
                this.classList.add('dhps-tp-filter__btn--active');
                this.setAttribute('aria-pressed', 'true');

                // Cards filtern.
                cards.forEach(function (card) {
                    if (filter === 'all' || card.dataset.category === filter) {
                        card.style.display = '';
                        card.removeAttribute('hidden');
                    } else {
                        card.style.display = 'none';
                        card.setAttribute('hidden', '');
                    }
                });
            });
        });
    }

    /**
     * Video-Toggle: Expandiert Card im Grid bei Klick auf Poster.
     */
    function initVideoToggle(container) {
        // Optional: Grid-Card expandiert sich bei Video-Wiedergabe.
        // Die expanded-Card nimmt die volle Grid-Breite ein.
    }

    // DOM Ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

---

## 6. Betroffene Dateien & Aufwand

| Datei | Aenderung | Aufwand |
|-------|-----------|---------|
| `includes/parsers/class-dhps-tp-parser.php` | **NEU** - TP-Parser (DOMDocument) | Hoch |
| `includes/class-dhps-parser-registry.php` | Parser-Registrierung fuer 'tp' ergaenzen | Klein |
| `public/views/services/tp/default.php` | **NEU** - Standard-Layout-Template | Mittel |
| `public/views/services/tp/card.php` | **NEU** - Card-Gallery-Layout-Template | Mittel |
| `public/views/services/tp/compact.php` | **NEU** - Kompakt-Layout-Template | Mittel |
| `css/dhps-frontend.css` | Ergaenzen um `.dhps-tp-*` BEM-Klassen | Mittel |
| `public/js/dhps-tp.js` | **NEU** - Lazy Loading, Filter, Toggle | Hoch |
| `includes/class-dhps-shortcodes.php` | JS/CSS conditional loading fuer TP | Klein |
| `includes/class-dhps-ajax-handler.php` | AJAX-Proxy fuer Video-iframe-src | Mittel |
| `Deubner_HP_Services.php` | Script/Style-Registration fuer TP | Klein |

**Geschaetzter Gesamtaufwand:** 3-4 Entwicklertage

### 6.1 Abgrenzung: TPT (TaxPlain Teaser)

Der Schwester-Service TPT (`[tpt]`) ist **nicht Teil dieses Redesigns**.
TPT wird in einem separaten Dokument behandelt, da:
- Eigener Shortcode mit eigenen Attributen
- Eigener Endpoint (`taxplain/videopages/teaser_php.php`)
- Auth via kdnr statt OTA
- Eigenes Rendering (Teaser-Modus)

TPT wird aber von der hier definierten Video-Embedding-Strategie und
den CSS-Klassen profitieren (gemeinsame Basis-Styles).

---

## 7. Sicherheitsanalyse

### 7.1 Kritische Befunde

| # | Befund | Risiko | Massnahme |
|---|--------|--------|-----------|
| 1 | **kdnr in iframe-src** - Kundennummer `0010N00004uRDoV` ist im Browser-Quelltext sichtbar | Hoch | iframe-src ueber AJAX-Proxy generieren; kdnr nur serverseitig injizieren |
| 2 | **kdnr in Social-Share-URLs** - Alle 5 Share-Links enthalten die kdnr | Hoch | Share-URLs serverseitig im Template generieren; kdnr nicht ins Frontend |
| 3 | **OTA-Nummer in HTML** - Kann in Kommentaren oder Scripts auftauchen | Mittel | Parser entfernt alle Inline-Scripts und HTML-Kommentare |
| 4 | **PHP Deprecated Warning** - Zeigt Server-Pfad und Dateistruktur | Mittel | Parser filtert alle PHP-Warnings/Notices aus dem Response |
| 5 | **Poster-URLs** auf deubner-online.de (kein kdnr enthalten) | Niedrig | Sicher - koennen direkt im Frontend verwendet werden |

### 7.2 Sicherheitsarchitektur

```
AKTUELL (unsicher):
Browser <--[kdnr sichtbar]--> mandantenvideo.de
Browser <--[kdnr sichtbar]--> Social-Share-URLs

NEU (sicher):
Browser <--[kein kdnr]--> WordPress (Template)
                               |
                               | AJAX-Proxy (wp_ajax)
                               |
                          [kdnr serverseitig injiziert]
                               |
                               v
                        mandantenvideo.de

Poster-Images (sicher):
Browser <--[direkt, kein kdnr]--> deubner-online.de/taxplain/images/
```

### 7.3 Implementierungsdetails

1. **Parser entfernt kdnr**: Beim Parsen der iframe-src wird `kdnr` explizit
   ausgelassen. Das geparste Array enthaelt nur `video_slug`, `poster_url`,
   `v_modus` und `service`.

2. **Template generiert keine kdnr**: Das Template rendert nur `data-video-slug`
   und `data-poster-url` als data-Attribute. Keine kdnr im HTML.

3. **AJAX-Proxy injiziert kdnr**: Erst beim Klick auf "Play" wird per AJAX
   die vollstaendige iframe-src mit kdnr generiert. Die kdnr kommt aus
   `get_option('dhps_tp_kdnr')`, nicht aus dem Frontend.

4. **Share-URLs serverseitig**: Die Social-Share-Links werden im Template
   ohne kdnr generiert. Die tatsaechliche Video-URL (mit kdnr) wird
   serverseitig in den `href` eingesetzt.

5. **Nonce-Validierung**: Jeder AJAX-Request wird per `wp_verify_nonce()`
   validiert, um Missbrauch des Proxy-Endpoints zu verhindern.

### 7.4 Beziehung zu TPT-Service

TPT (TaxPlain Teaser) teilt die gleiche `kdnr` (`dhps_tp_kdnr`).
Die Sicherheitsmassnahmen muessen konsistent sein:

- TPT verwendet ebenfalls den AJAX-Proxy fuer iframe-src
- Die kdnr-Option wird von beiden Services geteilt
- Eine Aenderung der kdnr in den Admin-Einstellungen wirkt auf beide Services

---

## 8. Video-Embedding-Strategie

### 8.1 Lazy Loading (Performance-kritisch)

Die API liefert ca. 30 Videos. Jeder Video-iframe wuerde einen eigenen
HTTP-Request starten und den Video-Player von mandantenvideo.de laden.
**30 gleichzeitige iframe-Loads sind inakzeptabel.**

**Strategie: Poster-First, iframe-on-Demand**

```
Phase 1: Initial Load
+------------------+    +------------------+
| [Poster-IMG]     |    | [Poster-IMG]     |    <- nur <img> Tags
|    [> Play]      |    |    [> Play]      |       ca. 30 Bilder
+------------------+    +------------------+       (lazy loaded)

Phase 2: User klickt auf Play
+------------------+    +------------------+
| [IFRAME/VIDEO]   |    | [Poster-IMG]     |    <- 1 iframe geladen
|  (playing...)    |    |    [> Play]      |       Rest bleibt Poster
+------------------+    +------------------+

Phase 3: User klickt anderes Video
+------------------+    +------------------+
| [Poster-IMG]     |    | [IFRAME/VIDEO]   |    <- vorheriges zurueck
|    [> Play]      |    |  (playing...)    |       zu Poster
+------------------+    +------------------+
```

**Regeln:**
- Maximal **1 iframe gleichzeitig** geladen (oder konfigurierbar)
- Poster-Images nutzen `loading="lazy"` (natives Browser-Lazy-Loading)
- iframe wird erst per AJAX-Proxy geladen (kdnr-Schutz + Performance)
- Beim Oeffnen eines neuen Videos wird das vorherige zurueckgesetzt

### 8.2 Responsive iframe (16:9 Aspect Ratio)

Die API liefert `width="500" height="291"` (ca. 1.72:1, nah an 16:9).
Wir verwenden die **Padding-Top-Technik** fuer responsive iframes:

```css
.dhps-tp-video__player {
    position: relative;
    width: 100%;
    padding-top: 58.2%;  /* 291/500 */
    overflow: hidden;
}

.dhps-tp-video__iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 0;
}
```

Alternative (moderne Browser): `aspect-ratio: 500 / 291;`

### 8.3 Accessibility

| Anforderung | Umsetzung |
|-------------|-----------|
| Video-Titel im iframe | `title`-Attribut auf `<iframe>` |
| Beschreibung | `aria-describedby` verweist auf Teaser-Text |
| Play-Button fokussierbar | `tabindex="0"` + `role="button"` auf Poster |
| Tastatursteuerung | Enter/Space oeffnet Video |
| Screen Reader | `aria-label="Video abspielen: [Titel]"` |
| Pause-Moeglichkeit | Video-Player auf mandantenvideo.de bietet Steuerung |
| Kategorie-Filter | `aria-pressed` auf Filter-Buttons |
| Reduzierte Bewegung | `prefers-reduced-motion: reduce` -> kein Autoplay |

### 8.4 Print-Unterstuetzung

```css
@media print {
    .dhps-tp-video__player {
        break-inside: avoid;
    }

    .dhps-tp-video__iframe {
        display: none;
    }

    .dhps-tp-video__poster {
        display: block !important;
    }

    .dhps-tp-video__share,
    .dhps-tp-filter__btn,
    .dhps-tp-card__play-btn {
        display: none;
    }
}
```

Beim Drucken werden die Poster-Images angezeigt, die iframes und
interaktiven Elemente ausgeblendet.

---

## 9. Umsetzungsreihenfolge

| Prio | Schritt | Beschreibung | Abhaengigkeit |
|------|---------|--------------|---------------|
| 1 | `DHPS_TP_Parser` | Parser implementieren und testen | Interface vorhanden |
| 2 | Parser-Registrierung | 'tp' in Parser-Registry eintragen | Parser fertig |
| 3 | AJAX-Proxy | Video-src-Endpoint implementieren | - |
| 4 | Template: `default.php` | Standard-Layout (Variante A) | Parser + Proxy |
| 5 | CSS: `.dhps-tp-*` | BEM-Styles in dhps-frontend.css | Template fertig |
| 6 | JavaScript: `dhps-tp.js` | Lazy Loading, Filter, Toggle | Template + Proxy |
| 7 | Conditional Loading | CSS/JS nur bei aktivem TP-Shortcode | JS/CSS fertig |
| 8 | Template: `compact.php` | Kompakt-Layout (Variante C) | Template default fertig |
| 9 | Template: `card.php` | Card-Gallery-Layout (Variante B) | Template default fertig |
| 10 | Responsive Testing | Alle Breakpoints testen | Alle Templates |
| 11 | Security Audit | kdnr-Exposure pruefen, Proxy testen | Alle Komponenten |

---

## 10. Hinweise fuer den Architekten

### 10.1 TPT-Beziehung beachten

TPT (TaxPlain Teaser, Shortcode `[tpt]`) und TP teilen:
- Die `kdnr` (`dhps_tp_kdnr`)
- Das Video-Hosting (mandantenvideo.de)
- Die Poster-Image-Quelle (deubner-online.de)

Die AJAX-Proxy-Logik (Abschnitt 5.2) muss fuer beide Services nutzbar sein.
Empfehlung: Den Proxy als generischen `dhps_video_src`-Handler implementieren,
der von TP und TPT gleichermassen genutzt wird.

### 10.2 Video-Embedding-Strategie

Die Entscheidung zwischen **AJAX-Proxy** und **signierten URLs** (Abschnitt 5.2)
hat weitreichende Auswirkungen:

- **AJAX-Proxy** ist einfacher zu implementieren, erfordert aber einen
  Server-Roundtrip bei jedem Video-Klick (ca. 50-100ms Latenz)
- **Signierte URLs** koennen im Template vorgerendert werden, haben aber
  eine Ablaufzeit und machen das HTML-Caching komplexer

Empfehlung: Mit AJAX-Proxy starten (einfacher), spaeter optional auf
signierte URLs migrieren wenn die Latenz stoert.

### 10.3 Performance: 30 iframes

**Niemals alle 30 iframes gleichzeitig laden.** Die Poster-First-Strategie
(Abschnitt 8.1) ist nicht optional, sondern Performance-kritisch.

Kennzahlen:
- 30 Poster-Images (lazy loaded): ca. 1-3MB total, progressiv
- 1 iframe (on-demand): ca. 200-500KB pro Video-Player
- 30 iframes (alle sofort): ca. 6-15MB + 30 HTTP-Verbindungen

Die initiale Seitenladung muss unter 3 Sekunden bleiben (LCP-Ziel).

### 10.4 Parser-Robustheit

Die API-Response ist instabil (PHP Deprecated Warning, moegliche
Strukturaenderungen). Der Parser muss:

1. PHP-Warnings/Notices herausfiltern bevor DOMDocument laedt
2. Graceful Degradation bei fehlenden Elementen (Featured Video optional)
3. Fallback auf Raw-HTML wenn Parser komplett fehlschlaegt
4. Logging bei unerwarteten Strukturen (aber kein harter Fehler)

### 10.5 Cache-Strategie

- **L1-Cache** (Raw HTML): 3600 Sekunden (Standard-TTL aus Shortcode-Attributen)
- **L2-Cache** (Parsed Data): Gleiche TTL wie L1
- **Poster-Images**: Werden direkt von deubner-online.de geladen (CDN-Cache)
- **Video-Player**: Kein Caching noetig (wird on-demand per iframe geladen)

Bei Aenderungen an der Videoliste (neues Video) wird der Cache erst nach
Ablauf der TTL aktualisiert. Das ist akzeptabel, da Videos selten
aktualisiert werden (max. monatlich).

### 10.6 Shortcode-Attribute und Video-Selektion

Die Service-Registry definiert spezifische Shortcode-Attribute fuer TP:

```
'teasermodus' => '0'      -> Beeinflusst API-Modus
'einzelvideo' => '0'      -> Einzelvideo-ID (fuer gezielte Einbettung)
'videoliste'  => ''       -> Komma-separierte Video-IDs
'layout'      => 'default' -> Layout-Variante (default/card/compact)
'class'       => ''       -> Zusaetzliche CSS-Klassen
'cache'       => '3600'   -> Cache-TTL in Sekunden
```

Der Parser muss `einzelvideo` und `videoliste` beruecksichtigen:
- `einzelvideo=82`: Nur das Video mit ID 82 anzeigen (kein Grid, kein Filter)
- `videoliste=82,81,79`: Nur die angegebenen Videos anzeigen

Diese Filterung kann im Template oder im Parser stattfinden.
Empfehlung: Im Parser, damit die L2-Cache-Keys korrekt sind.

---

## 11. Zusammenfassung

**Problem:** Der TaxPlain-Video-Service nutzt Legacy-HTML mit kritischen
Sicherheitsproblemen (kdnr-Exposure im Browser), katastrophaler Performance
(30 gleichzeitige iframes), veralteten Layout-Techniken (Tables, Inline-Styles)
und fehlender Barrierefreiheit.

**Loesung:** Integration in die Content-Pipeline (v0.9.0) mit eigenem Parser
(`DHPS_TP_Parser`), AJAX-Proxy fuer sichere Video-Einbettung, drei
Template-Varianten und Poster-First-Lazy-Loading.

**Empfehlung:** Variante A "Clean Modern" als Standard-Layout mit prominentem
Featured Video, Kategorie-Filter als Pill-Navigation und responsivem
2-Spalten-Video-Grid. Poster-Images als Platzhalter mit Lazy-iframe-Loading
bei Klick. Alle kdnr-Referenzen werden serverseitig ueber den AJAX-Proxy
injiziert.

**Sicherheit:** Die kdnr wird vollstaendig aus dem Frontend entfernt.
Video-iframes werden erst bei Benutzerinteraktion geladen, wobei die kdnr
serverseitig injiziert wird. Social-Share-URLs werden ebenfalls serverseitig
generiert. PHP-Warnings werden vom Parser gefiltert.

**Scope:** Parser + Templates + CSS + JavaScript + AJAX-Proxy. Der
Schwester-Service TPT wird in einem separaten Redesign behandelt, profitiert
aber von der gemeinsamen Video-Embedding-Infrastruktur.
