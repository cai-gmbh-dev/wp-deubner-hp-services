# 31 - Tech-Debt-Cleanup - Plan v0.17.5

**Status:** Discovery (2026-06-07)
**Aktuelle Plugin-Version:** v0.17.4
**Ziel-Version:** v0.17.5
**Architekt-Auftrag:** Tech-Debt-Sammelrelease nach Abschluss der 9/9-Adapter-
Migration (v0.17.0-v0.17.4). Aus 8 offenen Tickets die 2-4 wertvollsten/risiko-
aermsten in v0.17.5 abarbeiten, der Rest geht in v0.17.6 / v0.18.0.

## Discovery-Empfehlung vorab (Kurzfassung)

**v0.17.5 Scope (Empfehlung): 3 Tickets**

| ID | Titel | Aufwand | Risiko |
|----|-------|---------|--------|
| **TD-V0173-1** | `[mio_termine]`-Sub-Shortcode auf Collection-Bridge | S-M | NIEDRIG |
| **TD-V0171-3** | MMB-Search-AJAX auf Adapter (Force-Legacy-Variante) | M | NIEDRIG-MITTEL |
| **TD-V0174-2** | Sub-Adapter-Whitelist (Doku/Klarstellung, kein Code) | S | NIEDRIG |

**v0.17.6 verschieben:**

- TD-V0171-2 (MMB-AJAX-Lazy-Akkordeon) - mittlerer Aufwand, niedriges Risiko, aber besser als eigener Release wegen Smoke-Aufwand
- TD-V0172-2 + TD-V0173-2 (Datum-Normalisierung) - kombiniert, weil sie eine gemeinsame DTO-Erweiterung benoetigen

**v0.18.0 verschieben:**

- TD-V0172-1 (tp/compact.php) - braucht JS-Refactor (initCompactAccordion), gehoert zu Legacy-Pfad-Cleanup
- TD-V0174-1 (MIO-News-Container AJAX) - der einzige uebrig gebliebene cross-cutting Refactor

**Spec-Aufteilung-Empfehlung:** Pure Lead-Direct fuer alle 3 v0.17.5-Tickets (Scope < 350 LOC total). Begruendung in Sektion 8.

**Schema-Vertrag-Vorgehen:** Sektion 6 ist verbindlich (13x Schema-Vertrag-Vorgehen ohne Critical-Drift; v0.17.5 = Iteration 14).

**Aufwand v0.17.5 Gesamt:** **M (mittel)** - ca. 280-350 LOC Code + 60-80 LOC Tests + Doku.

---

## Sektion 1: Triage je Ticket

### TD-V0171-2: MMB-AJAX-Handler (Lazy-Akkordeon) auf Adapter

**Klar?** Ja. Heute ruft `DHPS_MMB_AJAX_Handler::handle_request` (Z. 270-272) direkt `new DHPS_MMB_Parser()` -> `parse($html)` -> sucht die gewuenschte Category aus `$parsed['categories']`, rendert dann via `mmb/partials/{layout}-content.php`. Adapter wird nie aufgerufen.

**Wert:** NIEDRIG-MITTEL. Konsistenz-Win, aber AJAX-Response-Shape ist Frontend-API (data-category, html, fact_sheets) - der Frontend-JS-Code muss unveraendert bleiben. Adapter wuerde die Daten transformieren, Partial wuerde sie via Pseudo-Rebuild zurueck shapen - 1:1-Roundtrip ohne Mehrwert ausser DTO-Consistency.

**Risiko:** NIEDRIG-MITTEL. Bestehende Wege (Parser direkt + Partial) sind stabil. Adapter wuerde indirekten Pfad einbauen. Wenn der Adapter spaeter erweitert wird (z.B. Filter-Hook), bekommt der AJAX-Handler dann das Update automatisch - das ist der eigentliche Punkt.

**Aufwand:** MITTEL. ~80-120 LOC + AJAX-Test-Suite (Stage-Container).

**Empfehlung:** **Aufschieben auf v0.17.6**. Begruendung: hat keinen Endkunden-Wert, Konsistenz-Win ist alleinstehend kein Release wert. v0.17.6 koennte beide MMB-AJAX-Tickets (TD-V0171-2 + TD-V0171-3) plus TD-V0174-1 als "AJAX-Pfad-Adapter-Konsolidierung" buendeln.

### TD-V0171-3: MMB-Search-AJAX auf Adapter

**Klar?** Teilweise. Heute parst `DHPS_AJAX_Proxy::handle_mmb_search` (Z. 252-253) via `DHPS_MMB_Search_Parser` (ein **anderer** Parser als der Top-Level-`DHPS_MMB_Parser`!) und gibt das Parser-Output direkt als JSON zurueck.

WICHTIG: `DHPS_MMB_Search_Parser` liefert eine andere Output-Shape:

```
array(
    'results'     => array[],  // [{id, title, description, pdf_params}]
    'total_count' => int,
    'query'       => string,
)
```

Das ist NICHT die `categories[]/fact_sheets[]`-Shape, die der `DHPS_MMB_Adapter` heute erwartet. Ein 1:1-Adapter-Aufruf scheitert.

**Optionen:**

- **A)** Eigener `DHPS_MMB_Search_Adapter` (neue Adapter-Klasse) - reinrassig DTO-konform, aber neuer Sub-Adapter (siehe TD-V0174-2).
- **B)** `DHPS_MMB_Adapter::adapt_search($parser_output)` als zweite Methode am bestehenden Adapter - bricht das `Adapter_Interface`-Single-Method-Pattern aus v0.17.0.
- **C)** Im AJAX-Handler die Search-Results in eine `categories[]`-Pseudo-Shape (1 Container mit den results als fact_sheets) umpacken und durch den bestehenden `DHPS_MMB_Adapter` jagen - **billigster Workaround**, aber Daten-Shape-Drift.
- **D)** Adapter-Bridge analog v0.17.1 MAES-Pattern: ein **Helper** `dhps_mmb_search_results_to_collection` (in `dhps-content-helpers.php`) erzeugt direkt eine `DHPS_Content_Collection` aus den Search-Results, ohne Adapter zu involvieren. Sub-Shortcode-Bridge-Pattern auf AJAX-Pfad uebertragen. **Empfohlen.**

**Wert:** NIEDRIG-MITTEL. Wie TD-V0171-2 ein Konsistenz-Win, aber die Search-Response geht direkt zum Frontend-JS und das JS rendert die Liste - kein Template-Pfad. Der DTO-Output wuerde nirgendwo "ankommen" ausser im JSON-Response.

**Risiko:** NIEDRIG. Frontend-JS-Vertrag (results/total_count/query) muss unveraendert bleiben - Option D laesst das voellig unangetastet.

**Empfehlung:** **In v0.17.5 in-scope mit Option D (Helper-only, kein Adapter)**. Begruendung: Wir brauchen den Helper sowieso fuer die zukuenftige DTO-Erweiterung (z.B. wenn Search-Results in Templates eingebunden werden sollen). Helper ist ~40 LOC, AJAX-Patch ~5 Zeilen, Tests ~30 LOC. KEINE Frontend-Aenderung.

ALTERNATIVE (auch akzeptabel): **Auf v0.17.6 verschieben** zusammen mit TD-V0171-2, falls Lead lieber "alles MMB-AJAX in einem Schritt" macht.

### TD-V0172-1: tp/compact.php Collection-Migration (initCompactAccordion-Refactor)

**Klar?** Ja, aber das JS-Risiko ist real. `dhps-tp.js::initCompactAccordion` (Z. 378-445) liest `data-video-slug` / `data-poster-url` / `data-v-modus` aus den `<li>`-Items und spawnt einen Video-Player dynamisch. Wenn die `<li>`-Datenstruktur durch Collection-Migration aenderte, wuerde das JS still brechen.

**Wert:** SEHR NIEDRIG. Das Template funktioniert. v0.18.0 (Legacy-Pfad-Entfernung) braucht das `$has_collection`-Pattern dann sowieso, aber heute ist es als "Last-Template ohne Pattern" markiert.

**Risiko:** HOCH (JS-Spawn-Risiko + Selector-Drift `'.dhps-service--tp, .dhps-service--lp'` an 3 Stellen). Code-Review fuer JS-only-Aenderungen ist teuer.

**Empfehlung:** **v0.18.0 schieben**. Begruendung: Im v0.18.0-Block ("Legacy-Pfad in Templates entfernen") muss tp/compact.php sowieso refactored werden (else-Branch entfernen). Es ist effizienter, das im selben Schritt zu machen. v0.17.5 muss nichts erzwingen.

### TD-V0172-2: Datum-Normalisierung MM/YY -> ISO im DTO

**Klar?** Ja. TP/TPT-Parser liefern `datum` als MM/YY-String (z.B. "10/24"). Im Adapter wandern sie als `meta.datum` (String). `DHPS_Content_Item::date` ist `?DateTimeImmutable` und bleibt `null`, weil ohne Tag keine eindeutige Datums-Rehydration moeglich.

**Optionen:**

- **A)** DateTimeImmutable mit Default-Tag 01. - "10/24" -> 2024-10-01T00:00:00. Bringt sortierbares Datum. Risiko: Falsche Semantik (User sieht "Oktober 2024", System haelt "01. Oktober 2024").
- **B)** Item-meta-Feld `date_partial` mit `format='YM'` und Wert `'2024-10'` - sortierbar via String-Compare, semantisch korrekt (kein Tag), aber neues DTO-Feld noetig.
- **C)** Bleibt im meta als String, kein Refactor - Status Quo.

**Wert:** NIEDRIG. Heute wird das Datum nur fuer Anzeige genutzt (`DHPS_TP_Parser::format_datum()`). Sortier-Use-Case ist hypothetisch.

**Risiko:** NIEDRIG (Option A) bis MITTEL (Option B - DTO-Erweiterung). Aber Option B fuegt der Schema-Stabilitaet eine neue Permutation hinzu.

**Empfehlung:** **v0.17.6 oder v0.18.0**. Kombinieren mit TD-V0173-2 (MIO Monat-Slug -> DTI) als gemeinsamer "Datum-Normalisierungs-Block". v0.17.5 hat keinen User-Wert dafuer. Alternative: Wenn Lead unbedingt ein Doku-Win in v0.17.5 will, kann Option B als verbindlicher Plan ("`date_partial`-Feld in v0.18.0 oder v0.18.x") in Sektion 8 des v0.18.0-Discovery dokumentiert werden, OHNE Code.

### TD-V0173-1: `[mio_termine]` Sub-Shortcode auf Collection-Bridge

**Klar?** Ja - **perfekter v0.17.5-Kandidat**, weil das Pattern aus v0.17.1 MAES-Sub-Shortcodes 1:1 uebertragbar ist.

**Aktueller Stand:**

- `DHPS_Steuertermine` ist Standalone-Klasse (`includes/class-dhps-steuertermine.php`)
- Eigene Filter-Atts `month` (`current|next|all`) + `count` (N pro Monat)
- Eigene Templates `public/views/steuertermine/{default,card,inline,compact}.php`
- Templates haben **KEIN** `$has_collection`-Pattern (das ist anders als bei den `mio/*`-Templates!) - sie iterieren ueber `$data` (Array von Monaten).
- Cache-Sharing mit MIO-Pipeline ueber identischen Cache-Key (`dhps_p_` + Hash). Adapter wird via MIO-Pipeline NIE getriggert vom `[mio_termine]`-Pfad.

**Migration analog v0.17.1 MAES-Bridge:**

1. Neue Methode `DHPS_Steuertermine::get_collection($atts): ?DHPS_Content_Collection`
2. Force-Legacy bei aktiven `month`/`count`-Atts mit nicht-Default-Werten (analog `FORCE_LEGACY_ATTS` aus `DHPS_MAES_Modules`)
3. Helper `dhps_build_collection_for('mio', $parsed_data)` (bereits vorhanden!)
4. Templates patchen: `$has_collection`-Pattern hinzufuegen + Pseudo-Rebuild aus Collection zu `$data` (Monatsliste)

**Wert:** MITTEL-HOCH. Loest den letzten Sub-Shortcode-Pfad, der heute den Adapter umgeht. Bestaetigt das Sub-Shortcodes-Bridge-Pattern fuer ALLE Sub-Shortcode-Klassen. Reinrassiger Cleanup-Win.

**Risiko:** NIEDRIG. Bestehendes BC-Pattern (v0.17.1 MAES) ist 14x bewaehrt. Templates sind klein und uebersichtlich (default = 42 Zeilen). Force-Legacy bei Filter-Atts schuetzt vor jeglicher Filter-Drift.

**Empfehlung:** **In v0.17.5 in-scope.** Detailliert in Sektion 3.

### TD-V0173-2: MIO Monat-Slug -> DateTimeImmutable

**Klar?** Teilweise. Was genau zu DateTimeImmutable gewandelt werden soll, ist nicht eindeutig. MIO-Adapter setzt `item->date = null` (siehe class-dhps-mio-adapter.php Z. 152), weil der Monatstitel keinen Tag enthaelt.

**Optionen:** Analog TD-V0172-2.

**Wert:** SEHR NIEDRIG. Use-Case ist hypothetisch (sortierbares Datum).

**Empfehlung:** **v0.17.6 oder v0.18.0**, gemeinsam mit TD-V0172-2. KEINE Aktion in v0.17.5.

### TD-V0174-1: MIO News-Container AJAX-Endpoint auf Adapter

**Klar?** Ja - aber GROSS. Heute (`DHPS_AJAX_Proxy::handle_news_request` Z. 107-184):

1. Parameter sanitisieren
2. OTA serverseitig laden
3. API-Aufruf an `hintergrundladen.php`
4. `DHPS_MIO_News_Parser::parse($html)` -> `{groups[], pagination{}}`
5. JSON-Response direkt

`DHPS_MIO_News_Parser` liefert eine eigene Shape (`groups[]` mit `articles[]` darunter) die mit der `DHPS_MIO_Adapter`-Shape (`tax_dates[]`) NICHTS gemein hat. **News-Items sind ein eigener DTO-Typ** (heute nicht in ALLOWED_TYPES - `'news'` ist drin, aber nicht genutzt).

**Optionen:**

- **A)** Eigener `DHPS_MIO_News_Adapter` mit type='news'. Bringt News in den DTO-Layer. **Beste Loesung**, aber neue Adapter-Klasse + Item-Mapping fuer News-Article-Schema (titel, teaser, html_body, datum, autor, social-share-meta).
- **B)** Helper-only `dhps_mio_news_results_to_collection` (analog Empfehlung fuer TD-V0171-3).
- **C)** AJAX-Handler benutzt den bestehenden `DHPS_MIO_Adapter` mit umgepackten Daten - nein, Adapter erwartet `tax_dates`, das passt nicht.

**Wert:** MITTEL. News sind ein wichtiger MIO-Use-Case. DTO-Konsistenz waere ein Plus.

**Risiko:** HOCH. `handle_news_request` ist mit 78 LOC der laengste AJAX-Pfad und hat 10+ Sanity-Params. News-Article-Schema ist groesser als TaxDate (HTML-Body, Sozial-Share-Meta, Print-Controls).

**Aufwand:** L (gross). Neue Adapter-Klasse (~150 LOC), News-Article-DTO-Mapping (15+ Felder), neuer Pseudo-Rebuild-Helper, AJAX-Test-Suite.

**Empfehlung:** **v0.18.0 schieben**. Begruendung: Das ist eigentlich kein Tech-Debt sondern ein **Feature**-Block (News als DTO-Typ). Macht im Zuge der Legacy-Pfad-Entfernung mehr Sinn.

### TD-V0174-2: Sub-Adapter fuer mio_termine/maes_videos/etc Whitelist

**Klar?** **NEIN.** Aus dem v0.17.4 Changelog: "Sub-Adapter fuer mio_termine/maes_videos/etc Whitelist-Aufnahme". Lese das so:

- **Interpretation A:** ALLOWED_SERVICES im `DHPS_Content_Item`-DTO (heute 13 Eintraege) um Sub-Shortcode-Namen erweitern (`maes_videos`, `mio_termine`, etc), sodass Items separate `service`-Tags pro Sub-Shortcode bekommen koennen.
- **Interpretation B:** Eine eigene Sub-Adapter-Klasse-Familie (`DHPS_MAES_Videos_Adapter`, `DHPS_Steuertermine_Adapter` etc), die parallel zu den Haupt-Adaptern leben und Sub-Shortcode-spezifische Schemas mappen.
- **Interpretation C:** Eine Doku-Klarstellung, dass Sub-Shortcodes weiterhin den Haupt-Adapter nutzen und KEINE Sub-Adapter-Klassen brauchen.

**Empfehlung:** **Neufassen als Doku-Ticket in v0.17.5**.

Interpretation B (eigene Sub-Adapter-Klassen) ist **architektonisch problematisch** - jeder Sub-Shortcode laeuft heute durch den Haupt-Adapter (z.B. `[maes_videos]` -> `DHPS_MAES_Adapter`), und das ist sauber: ein Service hat eine Adapter-Klasse, Sub-Shortcodes sind nur andere Konsumenten.

Interpretation A (Service-Whitelist erweitern) ist auch nicht noetig - Sub-Shortcodes nutzen den `service`-Tag des Haupt-Services (`maes_videos` Items haben `service='maes'`).

Praezisierter Vorschlag:

- **TD-V0174-2 in v0.17.5 wird zu**: "Doku-Klarstellung Sub-Shortcode-Pattern". Eine kurze Sektion in der `docs/team-knowledge/03-PATTERNS.md` (oder neue `13-SUB-SHORTCODE-BRIDGE-PATTERN.md`), die das v0.17.1 MAES-Pattern + v0.17.5 [mio_termine]-Pattern als verbindlich dokumentiert und Sub-Adapter-Klassen explizit als Anti-Pattern markiert.
- Aufwand: ~40 LOC Doku.
- Wert: HOCH (Architektur-Klarheit fuer alle zukuenftigen Releases).
- Risiko: 0.

**ALTERNATIVE:** Lead-Entscheidung, das Ticket als "unklar, neu formulieren wenn echter Use-Case auftaucht" zu schliessen (== verwerfen).

---

## Sektion 2: v0.17.5 Scope-Empfehlung

### Empfohlener Scope: 3 Tickets

| ID | Scope | Aufwand | Risiko |
|----|-------|---------|--------|
| TD-V0173-1 | `[mio_termine]`-Bridge analog MAES-v0.17.1 | S-M (~120-150 LOC) | NIEDRIG |
| TD-V0171-3 | MMB-Search-AJAX via Helper (Option D) | M (~80 LOC) | NIEDRIG |
| TD-V0174-2 | Sub-Shortcode-Bridge-Pattern Doku-Klarstellung | S (~40 LOC) | 0 |

**Total:** ca. 240-270 LOC Code + 60-80 LOC Tests + Doku-Sektion.

### Begruendung

1. **TD-V0173-1** ist das eindeutig hoechste Wert/Risiko-Verhaeltnis. Pattern bewaehrt, Code minimal, Cleanup-Win echt.
2. **TD-V0171-3** mit Option D (Helper-only, kein Adapter) ist niedrig-risikant und etabliert das Helper-Pattern fuer AJAX-Pfade (Vorlage fuer spaetere TD-V0171-2 + TD-V0174-1).
3. **TD-V0174-2** als Doku-Klarstellung verhindert kuenftige Mehrdeutigkeit. Kostet fast nichts, klaert architektonische Frage.

### Was NICHT in v0.17.5

- **TD-V0171-2** (MMB-AJAX-Lazy-Akkordeon): Pure Konsistenz-Win, kein User-Wert. v0.17.6 als "MMB-AJAX-Cleanup-Release" mit TD-V0171-3 + TD-V0174-1.
- **TD-V0172-1** (tp/compact.php): HOCH-Risiko JS, gehoert zu v0.18.0 Legacy-Cleanup.
- **TD-V0172-2 + TD-V0173-2** (Datum-Normalisierung): Hypothetischer Use-Case, am besten v0.17.6 oder v0.18.0 als gemeinsamer Datum-Block.
- **TD-V0174-1** (News-Container AJAX): Eigentlich ein Feature (News-DTO-Typ), Scope L, gehoert zu v0.18.0.

### Alternative Minimal-Scope (falls Lead konservativer fahren will)

| ID | Begruendung |
|----|-------------|
| TD-V0173-1 + TD-V0174-2 | 2 Tickets, ~160-190 LOC. TD-V0171-3 + alle anderen auf v0.17.6 schieben. |

### Alternative Maximal-Scope (falls Lead Tempo halten will)

| ID | Begruendung |
|----|-------------|
| TD-V0173-1 + TD-V0171-3 + TD-V0174-2 + TD-V0171-2 | 4 Tickets, ~360-450 LOC. TD-V0171-2 ist mittlerer Aufwand aber gleicher AJAX-Pfad wie -3 - Smoke-Synergie. |

---

## Sektion 3: TD-V0173-1 [mio_termine]-Bridge Detail

### 3.1 Aktueller Stand

`DHPS_Steuertermine::render($atts)`:

1. `shortcode_atts` mit 5 Atts (count, month, layout, class, cache)
2. MIO-Service-Config + OTA laden
3. Cache-Lookup oder API-Aufruf
4. Parser-Aufruf (`DHPS_MIO_Parser::parse`)
5. Cache schreiben
6. `$tax_dates = $parsed['tax_dates']` - DIRECT EXTRACT
7. Filter-Logik: `$month` (`current|next|all`) + `$count` (N pro Monat)
8. `render_template($tax_dates, $layout, $css_class)`

Templates (`public/views/steuertermine/{default,card,compact,inline}.php`):

- Iterieren ueber `$data` (= `$tax_dates`-Array)
- Pro Monat: `$month['title']`, `$month['entries'][]`, `$month['footnote']`
- KEIN `$has_collection`-Pattern

### 3.2 Force-Legacy-Atts

Genau analog `DHPS_MAES_Modules::FORCE_LEGACY_ATTS`:

```php
private const FORCE_LEGACY_ATTS = array( 'month', 'count' );
```

Force-Legacy-Bedingung (analog MAES, leicht angepasst):

- `month` != 'all' (z.B. 'current' oder 'next') -> Force-Legacy
- `count` > 0 (absint) -> Force-Legacy

WICHTIG: `month='all'` UND `count=0` (Defaults) -> Collection darf gebaut werden.

### 3.3 Helper-Wiederverwendung

KEIN neuer Helper. `dhps_build_collection_for('mio', $parsed_data)` aus v0.17.1 ist bereits vorhanden und liefert via `DHPS_MIO_Adapter` die Collection mit `tax_date`-Items.

### 3.4 Template-Migration

**4 Templates:** `default.php`, `card.php`, `compact.php`, `inline.php`

Pseudo-Rebuild-Pattern (analog `public/views/services/mio/default.php`):

```php
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
    $data = array();
    foreach ( $collection as $item ) {
        if ( 'tax_date' !== $item->type ) {
            continue;
        }
        $entries  = isset( $item->meta['entries'] ) && is_array( $item->meta['entries'] ) ? $item->meta['entries'] : array();
        $footnote = isset( $item->meta['footnote'] ) ? (string) $item->meta['footnote'] : '';
        $month = array(
            'title'   => $item->title,
            'entries' => $entries,
        );
        if ( '' !== $footnote ) {
            $month['footnote'] = $footnote;
        }
        $data[] = $month;
    }
}
// Wenn ! $has_collection, ist $data bereits durch DHPS_Steuertermine::render_template gesetzt (Legacy).
// AB HIER: bestehender Render-Code bytewise unveraendert
```

WICHTIG: Templates erhalten heute `$data` als Variable (siehe `render_template` Z. 162). Pseudo-Rebuild ueberschreibt `$data` NUR wenn Collection da ist. Sonst ist Legacy-Pfad UNVERAENDERT.

ALTERNATIV (cleaner): Helper-Datei `includes/dhps-mio-content-helpers.php` exportiert bereits `dhps_mio_item_to_legacy_month($item)`. Templates koennen direkt via `dhps_mio_item_to_legacy_month` rebuilten. Vorteil: KEINE Mapping-Logik dupliziert, ein zentraler Helper.

```php
if ( $has_collection ) {
    $data = array();
    foreach ( $collection as $item ) {
        $legacy = dhps_mio_item_to_legacy_month( $item );
        if ( ! empty( $legacy ) ) {
            $data[] = $legacy;
        }
    }
}
```

**Empfehlung: Helper-Wiederverwendung (5 Zeilen pro Template statt 15).**

### 3.5 Sub-Shortcode-Handler-Patch

`DHPS_Steuertermine::render`:

Nach Schritt 5 (Cache-Schreibe), VOR Schritt 6 (Filter-Logik):

```php
$collection = $this->get_collection( $atts, $parsed );

// Filter-Logik bleibt UNVERAENDERT auf $tax_dates (Legacy)
// ...

return $this->render_template( $tax_dates, $layout, $css_class, $collection );
```

`render_template` bekommt 4. Parameter `?DHPS_Content_Collection $collection = null`, das in den Template-Scope durchgereicht wird (analog `DHPS_Renderer::render_parsed()` aus v0.17.0).

### 3.6 get_collection-Methode

```php
public const FORCE_LEGACY_ATTS = array( 'month', 'count' );

private function get_collection( array $atts, array $parsed_data ): ?DHPS_Content_Collection {
    // Force-Legacy bei aktiven Filter-Atts.
    $month_raw = isset( $atts['month'] ) ? (string) $atts['month'] : 'all';
    $count_raw = isset( $atts['count'] ) ? (int) $atts['count'] : 0;

    if ( 'all' !== sanitize_key( $month_raw ) ) {
        return null;
    }
    if ( $count_raw > 0 ) {
        return null;
    }

    return dhps_build_collection_for( 'mio', $parsed_data );
}
```

### 3.7 Tests

Stage-Container, 8-10 Tests:

- T1: Defaults (`month=all`, `count=0`) -> Collection nicht-null, 12 Items (oder API-Anzahl)
- T2: `month=current` -> Collection null (Force-Legacy)
- T3: `month=next` -> Collection null
- T4: `count=3` -> Collection null
- T5: `count=0` + `month=all` -> Collection nicht-null
- T6: Render-Smoke `[mio_termine]` -> HTML unveraendert vs v0.17.4-Snapshot
- T7: Render-Smoke `[mio_termine month="current"]` -> Force-Legacy-Pfad rendert korrekt
- T8: Render-Smoke `[mio_termine count="3"]` -> Force-Legacy rendert nur 3 Entries pro Monat
- T9: Page-Smoke: keine PHP-Warnings im debug.log
- T10: Layout-Variants (`default`/`card`/`compact`/`inline`) je 1 Render-Test

---

## Sektion 4: TD-V0171-3 MMB-Search-AJAX Detail

### 4.1 Aktueller Stand

`DHPS_AJAX_Proxy::handle_mmb_search` (Z. 201-261):

1. Nonce + Param-Sanitize
2. Service-Lookup + OTA-Laden
3. API-Call `hintergrundladen.php` mit `s=$search`
4. Cache-Check
5. `DHPS_MMB_Search_Parser::parse($html)` -> `{results[], total_count, query}`
6. Cache-Set bei `! empty(results)`
7. `wp_send_json_success($parsed)`

`DHPS_MMB_Search_Parser` liefert per-result: `{id, title, description, pdf_params}`.

Frontend-JS (`dhps-mmb.js`) konsumiert die Response und rendert die Suchergebnisse.

### 4.2 Optionen-Analyse

**Option A: Eigener Search-Adapter** (`DHPS_MMB_Search_Adapter`)

- Nimmt `{results[], total_count, query}` -> Collection von `type='document'`-Items
- Pro Item: title, excerpt (description), meta (pdf_params)
- Collection-Meta: total_count, query

PRO: DTO-konform. CONTRA: Neue Adapter-Klasse, eigene Registry-Reg-Frage (`mmb_search`?), das ist die "Sub-Adapter-Whitelist"-Frage aus TD-V0174-2.

**Option B: 2. Methode am Adapter** - bricht Interface, verworfen.

**Option C: Daten-Umpacken durch bestehenden Adapter** - Search-Results in Pseudo-Categories packen, dann durch `DHPS_MMB_Adapter`. CONTRA: Daten-Shape-Drift, semantisch falsch.

**Option D: Helper-only Collection-Bridge** (EMPFOHLEN)

Neuer Helper in `includes/dhps-content-helpers.php`:

```php
function dhps_mmb_search_to_collection( array $search_results, string $service = 'mmb' ): DHPS_Content_Collection {
    $items = array();
    $idx   = 0;
    foreach ( $search_results['results'] ?? array() as $result ) {
        if ( empty( $result['title'] ) ) {
            continue;
        }
        $item_id = $service . '-search-' . ( $result['id'] ?? (string) $idx );
        $meta    = array(
            'source_id' => isset( $result['id'] ) ? (string) $result['id'] : '',
        );
        if ( ! empty( $result['pdf_params'] ) && is_array( $result['pdf_params'] ) ) {
            $meta['pdf_params'] = $result['pdf_params'];
        }
        $items[] = new DHPS_Content_Item(
            $item_id,
            $service,
            (string) $result['title'],
            'document',
            '',
            isset( $result['description'] ) ? (string) $result['description'] : null,
            null, null, null, null, array(), null, $meta
        );
        ++$idx;
    }
    $collection_meta = array(
        'total_count' => (int) ( $search_results['total_count'] ?? count( $items ) ),
        'query'       => (string) ( $search_results['query'] ?? '' ),
        'is_search'   => true,
    );
    return new DHPS_Content_Collection( $service, $items, $collection_meta );
}
```

PRO: KEIN neuer Adapter, Helper hat Single-Responsibility, semantisch korrekt (Search != Category-Listing).

### 4.3 AJAX-Handler-Patch

In `DHPS_AJAX_Proxy::handle_mmb_search` nach Schritt 5:

```php
// Response parsen.
$parser = new DHPS_MMB_Search_Parser();
$parsed = $parser->parse( $response->get_body() );

// v0.17.5: Collection-Bridge fuer DTO-Konsistenz.
// Frontend-JS-Vertrag UNVERAENDERT: $parsed bleibt die Response-Shape.
// Collection wird via dhps_mmb_search_results-Filter exposed (Future-Use).
$collection = dhps_mmb_search_to_collection( $parsed, $service_tag );

/**
 * Filter: erlaubt Plugins/Themes die Search-Collection vor dem Cachen
 * zu inspizieren. Default-Verhalten unveraendert (Collection wird nicht
 * in der JSON-Response ausgegeben).
 *
 * @since 0.17.5
 *
 * @param DHPS_Content_Collection $collection Search-Results als Collection.
 * @param array                   $parsed     Roher Parser-Output (kanonisch).
 * @param string                  $service_tag Service ('mmb' | 'mil').
 */
do_action( 'dhps_mmb_search_collection', $collection, $parsed, $service_tag );

// Cachen (5 Minuten fuer Suchergebnisse).
if ( ! empty( $parsed['results'] ) ) {
    $this->cache->set_data( $cache_key, $parsed, 300 );
}

wp_send_json_success( $parsed );  // Vertrag unveraendert!
```

### 4.4 Wichtig: Frontend-JS-Vertrag

Die JSON-Response (`{results, total_count, query}`) bleibt **BYTEWISE UNVERAENDERT**. Der Helper-Pfad ist eine **SIDE-CHANNEL** Collection fuer DTO-Konsistenz. Plugins/Themes koennen via Action-Hook `dhps_mmb_search_collection` zugreifen.

### 4.5 Tests

- T1: Helper `dhps_mmb_search_to_collection` mit leeren Results -> Collection mit 0 Items, Meta korrekt
- T2: Helper mit 3 Results -> Collection mit 3 Items, jedes hat `type='document'`, `service='mmb'`
- T3: Items haben source_id im meta wenn vorhanden
- T4: AJAX-Endpoint Response-Shape bytewise unveraendert vs v0.17.4
- T5: Action-Hook `dhps_mmb_search_collection` feuert mit korrekten Parametern
- T6: Cache-Verhalten unveraendert
- T7: `mil`-Service-Tag ebenfalls funktional (analog mmb)

---

## Sektion 5: TD-V0172-2 / TD-V0173-2 Datum-Normalisierung

**Empfehlung: NICHT in v0.17.5.** Doku-Eintrag in v0.18.0-Discovery (zu erstellen wenn v0.17.5/v0.17.6 fertig sind).

Begruendung: Heute kein konkreter Use-Case (Sortierung). Beide Tickets brauchen eine DTO-Erweiterung oder Default-Tag-Convention - das ist semantisch interessant aber nicht dringend.

Wenn der Architekt v0.17.5 einen Vorschuss-Eintrag will: Sektion 8 von einem ggf. zu schreibenden v0.18.0-Discovery vorab-skizzieren - 0 Code.

---

## Sektion 6: Schema-Vertrag

### 6.1 TD-V0173-1 [mio_termine]-Bridge

**Adapter-Aenderungen:** **KEINE.** `DHPS_MIO_Adapter` bleibt bytewise unveraendert.

**Helper-Aenderungen:** **KEINE.** `dhps_build_collection_for` aus v0.17.1 wird wiederverwendet. `dhps_mio_item_to_legacy_month` aus v0.17.3 wird wiederverwendet.

**Klassen-Aenderungen:** `DHPS_Steuertermine`

- Neue private const `FORCE_LEGACY_ATTS = array('month', 'count')`
- Neue private Methode `get_collection(array $atts, array $parsed_data): ?DHPS_Content_Collection`
- `render()` ruft `get_collection` nach Cache-Schreibe + reicht an `render_template` durch
- `render_template` bekommt 4. Param `?DHPS_Content_Collection $collection = null`, exponiert es in Template-Scope

**Template-Aenderungen:** 4 Templates (`steuertermine/{default,card,compact,inline}.php`)

- Pseudo-Rebuild-Block via `dhps_mio_item_to_legacy_month` ganz oben
- Bestehender Render-Code BYTEWISE unveraendert

**Bootstrap-Aenderungen:** **KEINE.** MIO-Adapter ist seit v0.17.3 registriert.

**BC-Garantien:**

- `[mio_termine]` ohne Atts (`month=all, count=0`): nutzt Collection -> Pseudo-Rebuild -> Render identisch zu v0.17.4
- `[mio_termine month=current]`: Force-Legacy -> Render identisch zu v0.17.4
- `[mio_termine count=3]`: Force-Legacy -> Render identisch zu v0.17.4
- Theme-Overrides unter `{theme}/dhps/steuertermine/*.php`: bleiben funktional (nutzen Legacy-`$data` wenn nicht migriert, was OK ist - `$collection` ist nullable im Scope)

### 6.2 TD-V0171-3 MMB-Search-AJAX-Bridge

**Helper-Aenderungen:** Neue Funktion `dhps_mmb_search_to_collection` in `includes/dhps-content-helpers.php`.

**Klassen-Aenderungen:** `DHPS_AJAX_Proxy::handle_mmb_search` bekommt 2 neue Zeilen (Collection-Build + Action-Hook).

**Adapter-Aenderungen:** **KEINE.**

**Frontend-JS-Aenderungen:** **KEINE.** JSON-Response-Shape bytewise unveraendert.

**Neuer Action-Hook:** `dhps_mmb_search_collection`

| Param | Typ | Beschreibung |
|-------|-----|--------------|
| `$collection` | `DHPS_Content_Collection` | Search-Results als Collection (read-only) |
| `$parsed` | `array` | Roher Parser-Output |
| `$service_tag` | `string` | 'mmb' oder 'mil' |

**BC-Garantien:**

- AJAX-Response-JSON-Shape (`{results, total_count, query}`) bytewise unveraendert
- Cache-Behavior unveraendert
- Frontend-JS funktioniert ohne Aenderung
- Rate-Limit-Verhalten unveraendert

### 6.3 TD-V0174-2 Doku-Klarstellung

**Code-Aenderungen:** **0 LOC.**

**Doku-Aenderungen:** Neue Sektion in `docs/team-knowledge/03-PATTERNS.md` ODER neue Datei `docs/team-knowledge/13-SUB-SHORTCODE-BRIDGE-PATTERN.md`:

- Anti-Pattern: Sub-Adapter-Klassen (TD-V0174-2-Interpretation B verworfen)
- Pattern: Sub-Shortcode-Klassen rufen Haupt-Adapter via `dhps_build_collection_for($haupt_service, $parsed_data)`
- Force-Legacy bei Filter-Atts (v0.17.1 MAES, v0.17.5 mio_termine)
- Force-Legacy-Atts werden als `private const FORCE_LEGACY_ATTS` deklariert
- Templates haben `$has_collection`-Pattern + Pseudo-Rebuild aus zentralem Helper
- Frontend-JS-Vertrag bleibt unangetastet (AJAX-Pfade: Collection als Side-Channel via Action-Hook)

---

## Sektion 7: Acceptance-Kriterien

### Pro Ticket

**TD-V0173-1:**

- [ ] `DHPS_Steuertermine::get_collection()` existiert und ist privat
- [ ] FORCE_LEGACY_ATTS const enthaelt 'month' und 'count'
- [ ] Bei `month='all'` UND `count=0`: Collection-Build, sonst null
- [ ] 4 Templates haben `$has_collection`-Pattern oben
- [ ] Templates nutzen `dhps_mio_item_to_legacy_month` (Helper-Wiederverwendung)
- [ ] Render-Code ab Pseudo-Rebuild-Block bytewise unveraendert
- [ ] Theme-Overrides bleiben funktional
- [ ] T1-T10 Tests PASS
- [ ] Page-Smoke `[mio_termine]` HTML-Diff vor/nach: leer

**TD-V0171-3:**

- [ ] Helper `dhps_mmb_search_to_collection` in `includes/dhps-content-helpers.php`
- [ ] AJAX-Handler ruft Helper + Action-Hook
- [ ] JSON-Response-Shape bytewise unveraendert (vergleichen via curl + jq diff)
- [ ] Action-Hook `dhps_mmb_search_collection` mit 3 Params feuert
- [ ] T1-T7 Tests PASS
- [ ] Frontend-JS `dhps-mmb.js` rendert unveraendert (Stage-Smoke)
- [ ] Rate-Limit-Verhalten unveraendert
- [ ] Cache-TTL 300 unveraendert

**TD-V0174-2:**

- [ ] Doku-Sektion existiert (entweder `03-PATTERNS.md` Sektion oder neue `13-*.md`)
- [ ] Anti-Pattern "Sub-Adapter-Klassen" dokumentiert
- [ ] Force-Legacy-Pattern dokumentiert
- [ ] Helper-Wiederverwendungs-Pattern dokumentiert
- [ ] AJAX-Side-Channel-Pattern dokumentiert (TD-V0171-3-Spezialfall)
- [ ] Cross-Links zu CHANGELOG-v0171 + CHANGELOG-v0175

### Release-Wide

- [ ] Versions-Bump 0.17.4 -> 0.17.5 in 3 Stellen (Deubner_HP_Services.php Header + DEFINE + README.md)
- [ ] CHANGELOG-v0175.md geschrieben (Format analog v0.17.4)
- [ ] MEMORY.md erhaelt MILESTONE 23-Eintrag
- [ ] Schema-Vertrag-Vorgehen-Counter aufgerueckt: 14x in Folge
- [ ] Pre-Release-Smoke: alle 9 Services + alle Sub-Shortcodes rendern unveraendert
- [ ] debug.log clean (nur erwartete Throw-Sims im Test-Setup)

---

## Sektion 8: Spec-Aufteilung

### Empfehlung: Pure Lead-Direct fuer alle 3 Tickets

**Begruendung:**

- TD-V0173-1: ~120-150 LOC, das v0.17.1-MAES-Pattern ist die direkte Vorlage. Lead hat es 14x bewiesen. Specialist-Briefing waere Overhead.
- TD-V0171-3: ~80 LOC, Helper-only + AJAX-Patch. Tests sind die Hauptarbeit. Lead-Direct billiger.
- TD-V0174-2: ~40 LOC Doku. Specialist macht keinen Sinn fuer Doku.

**Total Lead-Aufwand:** ca. 4-6 Lead-Iterationen, jede klein.

### Alternative: 1 Specialist fuer TD-V0173-1

Falls Lead Bandbreite fuer Parallelisierung hat, koennte TD-V0173-1 als F1-Specialist laufen waehrend Lead TD-V0171-3 + TD-V0174-2 selbst macht. Specialist-Brief waere kurz (Verweis auf v0.17.1 MAES-Bridge als Vorlage). Nur sinnvoll wenn Architekt das Tempo halten will.

### NICHT Empfohlen: 3 Specialists

Scope ist zu klein fuer 3 parallele Specialists. Coordination-Overhead waere groesser als die Implementation.

---

## Sektion 9: Risiken

### Top-3 Risiken v0.17.5

**R1: BC-Bruch bei `[mio_termine]`-Theme-Overrides** (TD-V0173-1)

- **Wahrscheinlichkeit:** NIEDRIG
- **Impact:** MITTEL (User-Custom-Templates muessten neu validiert werden)
- **Mitigation:** Pseudo-Rebuild als opt-in (`$has_collection`-Check). Wenn Theme-Override nicht migriert ist, nutzt es `$data` wie bisher. Collection ist optional im Scope.

**R2: Frontend-JS-Vertrag-Drift bei MMB-Search** (TD-V0171-3)

- **Wahrscheinlichkeit:** SEHR NIEDRIG
- **Impact:** HOCH (Search bricht im Browser, schlechte UX)
- **Mitigation:** JSON-Response-Shape ist BYTEWISE unveraendert (Helper-Pfad ist Side-Channel via Action-Hook). Curl-Smoke vor/nach mit jq-diff im Pre-Release-Test obligatorisch.

**R3: Sub-Shortcode-Rendering-Drift bei aktivem Force-Legacy** (TD-V0173-1)

- **Wahrscheinlichkeit:** NIEDRIG
- **Impact:** NIEDRIG (Render rendert leicht anders bei `month=current` oder `count=3`)
- **Mitigation:** Force-Legacy-Pfad LAESST den Render-Code UNVERAENDERT - Pseudo-Rebuild greift nur wenn `$has_collection` true ist. Filter-Logik in `DHPS_Steuertermine::render` bleibt vor dem `render_template`-Aufruf, also wirkt auf `$tax_dates` wie bisher. Tests T2-T4 + T7-T8 prufen diesen Pfad explizit.

### Weitere Risiken pro Ticket

**TD-V0173-1:**

- R-1a: `$collection` ist als 4. Param optional, alte Theme-Overrides die `render_template` direkt aufrufen wuerden funktionieren weiter (default null). KEIN BC-Bruch.
- R-1b: `dhps_mio_item_to_legacy_month` returnt empty array bei type != 'tax_date'. Wenn ein anderer Adapter beim MIO-Service Items mit type='news' liefern wuerde, wuerden die ueberspringt. Heute kein Use-Case (MIO-Adapter liefert nur tax_date).

**TD-V0171-3:**

- R-2a: Helper-Filter `wp_kses_post` oder aehnlich auf description? NEIN - im Helper wird kein HTML-Escaping gemacht, weil die Daten parser-side bereits clean sind. Konsistent mit MMB-Adapter v0.17.1.
- R-2b: Item-ID-Konflikt mit MMB-Top-Level-Items? NEIN - Search-Items haben Praefix `{service}-search-{id}` waehrend Top-Level `{service}-doc-{cat_idx}-{id}` haben. Disjunkt.

**TD-V0174-2:**

- R-3a: Doku wird nicht gelesen, kuenftige Entwickler erfinden Sub-Adapter-Klassen trotzdem. Akzeptables Risiko.

---

## Sektion 10: Spec-Briefing

### TD-V0173-1 Lead-Direct-Brief

**Mission:** `[mio_termine]` Sub-Shortcode auf Collection-Bridge analog v0.17.1 MAES-Pattern.

**Vorlage:** `DHPS_MAES_Modules::get_collection()` (v0.17.1, `includes/class-dhps-maes-modules.php` Z. 145-168).

**Schritte:**

1. `DHPS_Steuertermine::FORCE_LEGACY_ATTS = array('month', 'count')` const hinzufuegen
2. `get_collection(array $atts, array $parsed_data): ?DHPS_Content_Collection` als private Methode hinzufuegen
3. `render($atts)`: nach Cache-Set `$collection = $this->get_collection($atts, $parsed);` aufrufen
4. `render_template(array $tax_dates, string $layout, string $css_class, ?DHPS_Content_Collection $collection = null): string` - 4. Param hinzufuegen
5. Im `render_template`: `$collection`-Variable wird in den Template-Scope durchgereicht (analog `$data` heute)
6. 4 Templates `public/views/steuertermine/{default,card,compact,inline}.php` patchen mit `$has_collection`-Block + `dhps_mio_item_to_legacy_month`-Rebuild
7. Tests T1-T10 (siehe Sektion 3.7)

**Schema-Vertrag:** Sektion 6.1.

**Verbot:** KEINE Aenderung am `DHPS_MIO_Adapter` oder `dhps_mio_item_to_legacy_month`. KEINE Aenderung am Pseudo-Rebuild-Code IN den Templates ausser dem neuen Top-Block.

### TD-V0171-3 Lead-Direct-Brief

**Mission:** MMB-Search-AJAX bekommt Collection-Side-Channel (Helper + Action-Hook) ohne JSON-Vertrag-Aenderung.

**Schritte:**

1. Helper `dhps_mmb_search_to_collection( array $search_results, string $service = 'mmb' ): DHPS_Content_Collection` in `includes/dhps-content-helpers.php` hinzufuegen
2. `DHPS_AJAX_Proxy::handle_mmb_search` Z. 252-260 patchen:
   - Nach `$parsed = $parser->parse(...)`: `$collection = dhps_mmb_search_to_collection( $parsed, $service_tag );`
   - `do_action( 'dhps_mmb_search_collection', $collection, $parsed, $service_tag );`
   - Cache + wp_send_json_success UNVERAENDERT
3. Action-Hook im Doc-Block dokumentieren (analog v0.17.1-Doc-Style)
4. Tests T1-T7 (siehe Sektion 4.5)
5. Stage-Smoke: curl auf AJAX-Endpoint vor/nach, jq-diff muss leer sein

**Schema-Vertrag:** Sektion 6.2.

**Verbot:** KEINE Aenderung an JSON-Response-Shape. KEINE Aenderung am Frontend-JS. KEINE Aenderung am `DHPS_MMB_Search_Parser`.

### TD-V0174-2 Lead-Direct-Brief

**Mission:** Sub-Shortcode-Bridge-Pattern dokumentieren als verbindliches Architektur-Pattern.

**Output:** Neue Sektion in `docs/team-knowledge/03-PATTERNS.md` ODER neue Datei `docs/team-knowledge/13-SUB-SHORTCODE-BRIDGE-PATTERN.md` (Lead waehlt).

**Inhalt:**

- Pattern-Name + Kontext (Sub-Shortcodes die nicht durch DHPS_Content_Pipeline laufen)
- Vorlage: v0.17.1 MAES (`DHPS_MAES_Modules::get_collection`) + v0.17.5 mio_termine (`DHPS_Steuertermine::get_collection`)
- Force-Legacy bei Filter-Atts: `private const FORCE_LEGACY_ATTS` + early-return null
- Helper-Wiederverwendung: `dhps_build_collection_for($haupt_service, $parsed_data)` + `dhps_{service}_item_to_legacy_*`
- Template-Pattern: `$has_collection`-Check + Pseudo-Rebuild + Render-Code bytewise unveraendert
- AJAX-Side-Channel-Spezialfall (v0.17.5 TD-V0171-3): Frontend-JS-Vertrag unangetastet, Collection via Action-Hook
- Anti-Pattern: eigene Sub-Adapter-Klassen ("`DHPS_MAES_Videos_Adapter`" etc) - Begruendung: Service hat 1 Adapter, Sub-Shortcodes sind Konsumenten

**Verbot:** Keine Code-Aenderungen.

---

## Sektion 11: Aufschiebe-Plan v0.17.6 / v0.18.0

### v0.17.6 Vorschlag: "MMB-AJAX-Konsolidierung + Datum-Block"

| Ticket | Aufwand |
|--------|---------|
| TD-V0171-2 (MMB-Lazy-Akkordeon) | M |
| TD-V0174-1 (MIO-News-Container AJAX) | L oder verschieben |
| TD-V0172-2 + TD-V0173-2 kombiniert (Datum-Normalisierung) | M |

### v0.18.0 Vorschlag: "Legacy-Pfad-Entfernung"

| Ticket | Aufwand |
|--------|---------|
| Else-Branches in allen 17 migrierten Templates entfernen | L |
| TD-V0172-1 (tp/compact.php Refactor) im selben Schritt | M |
| TD-V0174-1 falls noch offen | L |
| Pipeline-Patch v0.17.0 als einzige Datenquelle erzwingen | M |

---

## Anhang: Ticket-Status-Map nach v0.17.5

| Ticket | Status nach v0.17.5 | Naechstes Ziel |
|--------|---------------------|-----------------|
| TD-V0171-2 | OFFEN | v0.17.6 |
| TD-V0171-3 | ERLEDIGT (Side-Channel) | - |
| TD-V0172-1 | OFFEN | v0.18.0 |
| TD-V0172-2 | OFFEN | v0.17.6 (Datum-Block) |
| TD-V0173-1 | ERLEDIGT | - |
| TD-V0173-2 | OFFEN | v0.17.6 (Datum-Block) |
| TD-V0174-1 | OFFEN | v0.18.0 (oder v0.17.6 wenn Aufwand passt) |
| TD-V0174-2 | ERLEDIGT (Doku) | - |

**3 von 8 erledigt** in v0.17.5, **5 offen** verteilt auf v0.17.6 + v0.18.0.

---

## Schluss-Bilanz

v0.17.5 ist ein **defensiver Polish-Release**: 3 Tickets mit niedrigem Risiko und klarem Wert. Schema-Vertrag-Vorgehen wird zum 14. Mal angewandt. Pure Lead-Direct ist genug, weil das v0.17.1-Pattern bewiesen ist und der Scope klein bleibt.

Die wichtigste strategische Botschaft: **v0.17.x ist DONE**, sobald v0.17.5 raus ist. Alles weitere gehoert in v0.17.6 (Konsistenz) oder v0.18.0 (Legacy-Cleanup). Nicht alle 8 Tickets brauchen einen eigenen Release - das waere Overhead ohne User-Wert.
