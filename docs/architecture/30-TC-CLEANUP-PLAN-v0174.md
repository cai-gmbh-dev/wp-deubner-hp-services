# 30 - TC-Adapter (+ v0.17.x-Abschluss-Cleanup) - Plan v0.17.4

**Status:** Discovery (2026-06-06)
**Aktuelle Plugin-Version:** v0.17.3
**Ziel-Version:** v0.17.4
**Architekt-Auftrag:** Fuenfter und letzter Adapter-Block der inkrementellen
Datenmodell-Migration. Nach MAES (v0.17.0), MMB/MIL (v0.17.1), TP/TPT/LP
(v0.17.2) und MIO/LXMIO (v0.17.3) jetzt TC. Damit sind **9/9 Hauptservices**
auf das einheitliche Datenmodell migriert. v0.17.4 ist auch der natuerliche
Cleanup-Release des v0.17.x-Zyklus: ein bis zwei der offenen Tech-Debt-
Tickets werden mit erledigt.

**Discovery-Empfehlung vorab (Kurzfassung):**

- **Strategie:** **Option C - Sehr duenner Wrapper-Adapter**. TC ist ein
  Wrapper-Service ohne strukturierten Output. Adapter liefert IMMER eine
  Collection mit **maximal 1 generischem Item** (type='generic'), das HTML
  und is_empty in Item-meta haelt. Templates rendern via Pseudo-Rebuild
  zurueck zu `$tc_html` + `$is_empty` und behalten das `echo $tc_html`-
  Pattern (Trust-Decision v0.13.0/v0.14.4 **unangetastet**).
- **Empty-State-Mapping:** Bei `is_empty=true` enthaelt die Collection
  **0 Items** (leere Collection mit Meta `is_empty=true`, `html`'). Bei
  `is_empty=false` enthaelt sie **1 Item** vom Type 'generic' mit
  `meta['html']=$html`, `meta['is_empty']=false`. Konsistent mit anderen
  Adaptern: leere Collection = Empty-State, gefuellte Collection = Content.
- **Adapter-Strategie:** **EINE Adapter-Klasse `DHPS_TC_Adapter`**,
  registriert NUR fuer `tc`. Keine Mehrfach-Registrierung (anders als
  MMB+MIL / TP+LP / MIO+LXMIO) - TC hat keinen Service-Verwandten, eigene
  Lizenz `dhps_tc_kdnr`, eigene API-Endpoint-Klasse.
- **Sub-Shortcode:** **Keiner**. TC hat nur `[tc]`. Maximaler Einfach-Fall.
- **Helper:** **Kein eigener Helper noetig**. Pseudo-Rebuild ist 6 Zeilen
  pro Template (`$tc_html = $item->meta['html'] ?? ''; $is_empty = ...`),
  zu trivial fuer eine separate `dhps-tc-content-helpers.php`. Adapter
  setzt die Meta-Felder, Templates lesen sie direkt. Lessons aus v0.17.0
  MAES-Pilot (Inline-Pattern bei trivialen Mappings ist akzeptabel).
- **Template-Migration:** **Alle 3 TC-Templates** (default, card, compact)
  bekommen das `$has_collection`-Pseudo-Rebuild-Pattern. Render-Code (inkl.
  `echo $tc_html`) bleibt BYTEWISE unveraendert.
- **`echo $tc_html` Trust-Decision:** **NICHT ANGETASTET.** Pseudo-Rebuild
  rekonstruiert `$tc_html` aus Collection-Meta vor dem `echo`. Das
  bestehende `phpcs:ignore`-Pragma + die Begruendung im Komment bleiben
  unveraendert. Sicherheits-Premise (kdnr-Auth + HTTPS-API) gilt weiter.
- **Cleanup-Scope:** **TC + 1 minimaler Cleanup**. Empfehlung: TD-V0173-2
  (Datum-Normalisierung Monat) ist v0.18.0-Scope, NICHT v0.17.4.
  Empfehlung TD-V0171-2 (MMB-AJAX-Handler auf Adapter) sowie TD-V0173-1
  (`[mio_termine]`-Sub-Shortcode-Bridge) **NICHT** in v0.17.4 mit
  reinnehmen - Big-Cleanup waere v0.17.5 / v0.18.0-Discovery. Stattdessen
  einen **Migrations-Abschluss-Report** als Doku-Cleanup mitnehmen
  (kein Code-Risiko, hoher Wert fuer den User).
- **Spec-Aufteilung:** **Pure Lead-Direct (kein Specialist)**. Begruendung
  in Sektion 9 - der Scope ist so klein (Adapter ~80 LOC + 3x ~10 LOC
  Templates), dass 1 saubere Lead-Iteration billiger ist als ein
  Specialist-Briefing. Lehre aus 4 vorherigen Adapter-Releases.
- **Schema-Vertrag:** Sektion 6 ist verbindlich (13x Schema-Vertrag-
  Vorgehen ohne Critical-Drift; v0.17.4 = Iteration 14).
- **Aufwand:** **S (klein)** - Adapter ~80 LOC, 3 Templates ~30 LOC
  Patches, Bootstrap ~3 Zeilen, Doku ~Sektion + CHANGELOG. Total ~250 LOC.

---

## Sektion 1: Ausgangslage TC-Parser

### 1.1 Bestandsaufnahme `DHPS_TC_Parser::parse()`

Aus `includes/parsers/class-dhps-tc-parser.php` (Z. 47-55):

```
array(
    'html'        => string,    // Original-HTML (Inline-JS test_einblenden/
                                //   test_ausblenden bleibt erhalten)
    'is_empty'    => bool,      // Empty-State erkannt
    'service_tag' => 'tc',      // wird Pipeline-uebersteuert
)
```

Keine weiteren Felder. **Maximale Simplizitaet.**

### 1.2 Empty-State-Detection (Z. 70-96)

Drei Patterns:

1. Exakter leerer Container `<div class="taxcalc"><p class="sm_buttons"></p></div>` -> empty
2. `calc_area` vorhanden -> NICHT empty
3. Weder `calc_area` noch `webcalc` -> empty
4. Stripped Content < 50 Zeichen -> empty (Fallback)

**Konsequenz fuer Adapter:** `is_empty` ist die einzige Logik-Quelle, die
der Adapter konsumiert. Bei `is_empty=true` wird **kein Item** angelegt.

### 1.3 Inline-JS bleibt als String im HTML

Die API liefert HTML + `<script>`-Tags mit `test_einblenden`/`test_ausblenden`
zusammen aus. Parser extrahiert nichts daraus - HTML wird 1:1
weitergereicht. Adapter muss das HTML **unveraendert** durchreichen, sonst
brechen die Inline-JS-Hooks beim Akkordeon-Klick. Item-meta['html'] ist
exakt `$parsed_data['html']`.

### 1.4 TC-Templates (default, card, compact)

Alle 3 Templates haben **identische Struktur** (siehe
`public/views/services/tc/*.php`):

1. Header-Variablen: `$tc_html  = $data['html'] ?? '';` und
   `$is_empty = ! empty( $data['is_empty'] );`
2. Service-Wrapper-DIV mit `dhps-service--tc`
3. `if ( $is_empty )` -> `dhps_component( 'empty-state', ... )` mit
   icon='calculator', title und service-spezifischem hint
4. `else` -> `<div class="dhps-tc__container">` + `echo $tc_html`

**Unterschiede pro Layout:**

| Template | Wrapper | Empty-State-Hint | Container-Modifier |
|---|---|---|---|
| `default.php` | nur `dhps-service--tc` | 2-Satz-Hint | `dhps-tc__container` |
| `card.php` | + innerer `<div class="dhps-card">` | 1-Satz-Hint | `dhps-tc__container` |
| `compact.php` | + Klasse `dhps-service--tc-compact` | 1-Satz-Hint, `--compact`-Modifier | `dhps-tc__container--compact` |

Render-Code-Komplexitaet: **trivial.** Pseudo-Rebuild ist 6 Zeilen pro
Template (Header-Variablen rekonstruieren).

### 1.5 Es gibt KEINEN Sub-Shortcode

TC hat nur `[tc]`. Kein `[tc_rechner]`, kein `[tc_einkommensteuer]` o.ae.
Die 25+ Rechner sind durch die API als 1 Akkordeon ausgeliefert,
clientside via Inline-JS aufklapp-/zuklapp-bar. Adapter muss keine
Sub-Shortcode-Bridge bereitstellen (Sektion 5 entfaellt).

### 1.6 TC-Sicherheits-Premise (Trust-Decision v0.13.0/v0.14.4)

```php
// public/views/services/tc/default.php Z. 50-52:
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped --
// HTML aus vertrauenswuerdiger Deubner-API mit Akkordeon-JS.
echo $tc_html;
```

Begruendung der Trust-Decision (dokumentiert in v0.13.0-CHANGELOG):
- TC-API erfordert `dhps_tc_kdnr` (eigene Kundennummer, nicht von anderen
  Services geteilt)
- API-Endpoint nur ueber HTTPS erreichbar
- Inline-JS (`test_einblenden`/`test_ausblenden`) ist Bestandteil der
  Response - Sanitization (z.B. `wp_kses_post`) wuerde die JS-Hooks
  entfernen und das Akkordeon zerstoeren
- Alternative waere ein vollstaendiger Rewrite zu strukturiertem JSON
  (gibt es API-seitig nicht)

**v0.17.4 Konsequenz:** `echo $tc_html` MUSS unveraendert bleiben. Der
Adapter darf das HTML NICHT escapen, NICHT modifizieren, NICHT durch
`wp_kses_post` jagen. 1:1-Passthrough.

---

## Sektion 2: Ziel-Datenmodell TC

### 2.1 Drei Mapping-Optionen

**Option A: 1 generisches Item, type='generic'**, `body=$html` (raw HTML).

- Pro: Klare DTO-Semantik (1 Render-Block = 1 Item).
- Pro: ContentItem-`body` ist explizit als HTML-Slot vorgesehen
  (Konstruktor-Doc Z. 156: "HTML ODER Plain-Text").
- Contra: ContentItem-`body` waere ein riesiges HTML-Blob (Trust-Decision
  v0.13.0). `to_content_card_props()` wuerde das in `body_html` mappen -
  WAS ABER IRRELEVANT IST, weil TC NIE ueber ContentCard rendert (analog
  MIO tax_dates Sektion 2.5 Frage 3 aus v0.17.3-Plan).
- Contra: `body`-Feld ist semantisch "Hauptinhalt", aber TC-HTML ist ein
  ganzes Subsystem mit Inline-JS. Etwas verzerrend.
- Contra: PHP-Reflection-Tooling (Debug-Inspectoren) sehen `body` als
  Content-Preview - bei TC waere das ein 50-300 KB-Blob inklusive
  Script-Tags. Stoerend in `var_dump`/`print_r`.

**Option B: Leere Collection bei is_empty + 1 Item sonst** mit
`meta['html']` (HTML in Meta-Hash).

- Pro: HTML lebt in `meta` (explizit als Fluchtweg gedacht,
  Trust-Decision TD-3 aus v0.17.0-Plan).
- Pro: ContentItem-`body=''` haelt das DTO clean (kein 50KB-Blob im
  semantischen Hauptfeld).
- Pro: Konsistent mit MIO-Adapter (Tax-Dates haben auch `body=''`,
  Sub-Struktur in `meta['entries']`). Konsistent mit MMB-Adapter
  (Fact-Sheets haben `body=''`, `pdf_params` in `meta`).
- Pro: Empty-State = leere Collection (Item-Count=0), Content-State =
  1-Item-Collection. **Genau wie Trust-Decision TD-5 aus v0.17.0-Plan
  ("Empty-State = leere Collection, nicht null")**.
- Contra: Templates muessen `$item->meta['html']` lesen statt `$item->body`.
  Pseudo-Rebuild ist aber sowieso noetig (kann nicht Item direkt
  rendern). 1 Zeile mehr, keine Auswirkung.

**Option C: Adapter ohne Items, nur Collection-Meta**.

- Adapter liefert IMMER eine leere Collection, das HTML wandert in
  Collection-Meta `meta['html']` + `meta['is_empty']`.
- Pro: Maximal duenn (kein Item nodig).
- Contra: Inkonsistent zu allen anderen Adaptern (die liefern IMMER Items
  fuer Content). Collection-Meta ist semantisch fuer "uebergreifende
  Konfig" (search_config, ajax_params, pagination), nicht fuer den
  Render-Inhalt selbst.
- Contra: `count() === 0` bedeutet immer "Empty-State" - aber bei
  Option C waere ein gefuellter TC-Service auch immer count=0. Bricht die
  Aussagekraft von `is_empty()`-Helper.

### 2.2 Empfehlung: **Option B** (Empty-Collection oder 1-Item-Collection)

**Begruendung:**

1. **Trust-Decision TD-5 v0.17.0** ("Empty-State = leere Collection")
   loest sich genau hier ein: TC ist der erste Service mit echtem
   binaeren Empty-State (entweder kdnr funktioniert oder nicht). Die
   Collection-API ist genau dafuer da.
2. **Konsistenz mit MIO-Adapter:** Tax-Dates haben `body=''`,
   Sub-Struktur in `meta['entries']`. TC-HTML ist genau so ein
   "Sub-Struktur-Blob, der nicht in 'body' semantisch passt".
3. **Konsistenz mit MMB-Adapter:** Fact-Sheets haben `body=''`,
   `pdf_params` in `meta`. Pattern aus 4 vorherigen Adaptern bewaehrt.
4. **`to_content_card_props()` ist auch hier verlustbehaftet** (generic
   wird auf `news` gemappt, HTML-Sub-Struktur verloren) - aber **das ist
   OK**, weil TC NIE ueber ContentCard rendert (Tabellen-/Akkordeon-Layout,
   nicht Card-Layout). Templates lesen `$item->meta['html']` direkt.
5. **Item-`type='generic'`** ist in ALLOWED_TYPES seit v0.17.0 und wurde
   bislang nicht genutzt - TC ist der natuerliche erste Anwendungsfall
   (Discovery v0.17.0 Z. 48: ALLOWED_TYPES enthaelt 'generic' als
   "default-Annahme fuer unspezifizierte Items").
6. **DTO-Disziplin haelt:** `body` bleibt semantisch "Hauptinhalt",
   `meta` ist der Fluchtweg-Slot. Bei zukuenftiger Cache-Roundtrip-Logik
   (`to_array`/`from_array`) ist `meta` ohnehin der Slot fuer
   "JSON-encode-faehige Extras" (Klassen-Doc Z. 220-227).

**Lead-Direct-Entscheidung:** Option B wird die Default. Option A waere
auch technisch korrekt, aber Sektion 2.3 zeigt: Option B ist konsistenter
mit den vier vorherigen Adaptern und mit dem v0.17.0-Trust-Decision-Set.

### 2.3 Mapping-Tabelle (Option B)

| Parser-Feld | Mapping | Begruendung |
|---|---|---|
| `is_empty === true` | -> **0 Items** in Collection, Collection-Meta `is_empty=true`, `html=$html` | Empty-State = leere Collection (TD-5) |
| `is_empty === false` | -> **1 Item** vom Type 'generic'; meta['html']=$html, meta['is_empty']=false | 1 Render-Block = 1 Item |
| `html` | -> Item.meta['html'] (bei !is_empty) ODER Collection.meta['html'] (bei is_empty - zur Diagnose, ungenutzt im Template) | HTML als Fluchtweg-Meta |
| `service_tag` | -> ignoriert (Pipeline ueberschreibt) | Standard fuer alle Adapter |
| Item.title (bei !is_empty) | -> Fix-Wert `'TC Rechner'` (i18n-Schluessel) | DHPS_Content_Item erzwingt non-empty title. Wert ist Anzeige-Text, der NIE gerendert wird (Templates ignorieren `$item->title`, lesen direkt `meta['html']`) |
| Item.id (bei !is_empty) | -> Fix-Wert `'tc-calculators'` | Eindeutiger Identifier; TC hat keine ID-Granularitaet (1 API-Response = 1 Item) |
| Item.body | -> `''` (leer - HTML lebt in meta) | DTO-Disziplin: body ist "semantischer Hauptinhalt", nicht "Sub-Struktur-Blob" |
| Item.service | -> `$service` (aus Param) | Standard |
| Item.category | -> null | TC hat keine Kategorien |
| Item.date | -> null | TC hat kein Datum |

### 2.4 Collection-Meta-Felder

```
array(
    'is_empty' => bool,    // 1:1 aus Parser - Schluesselindikator fuer Template
    'html'     => string,  // 1:1 aus Parser - DEFENSIVES Spiegeln, damit
                           //   Template einen einheitlichen Ort hat zum Lesen
                           //   (egal ob Items leer sind oder nicht)
)
```

**Hinweis zu defensivem Spiegeln:** Collection.meta['html'] und
Item.meta['html'] sind redundant bei Content-State (gleicher Wert).
Begruendung: Pseudo-Rebuild liest aus EINEM einheitlichen Slot
(Collection.meta), unabhaengig vom Item-Count. Vereinfacht das Pseudo-
Rebuild zu 2 Zeilen ohne if/foreach:

```php
$tc_html  = $collection->get_meta( 'html', '' );
$is_empty = (bool) $collection->get_meta( 'is_empty', false );
```

**Performance:** TC-HTML ist 0-300 KB. Doppelt-Referenzieren im
Item.meta + Collection.meta ist 0 Byte Mehr-Speicher (PHP-CoW). Bei
Cache-Roundtrip wuerde das HTML 2x serialisiert - aber Adapter laeuft
nicht durch L2-Cache (TD-7 aus v0.17.0-Plan: "L2-Cache cached nur
parsed_data"), also irrelevant.

### 2.5 Wichtige Designentscheidungen

**Frage 1: Item-Title bei !is_empty - was setzen?**

`DHPS_Content_Item` erzwingt `title !== ''`. TC hat aber keinen "Titel".

**Empfehlung:** Fix-Wert `'TC Rechner'` (deutsch, kurz, semantisch
sinnvoll wenn jemand das Item via `to_content_card_props()` doch mal
rendert). Wird in Templates NIE gerendert (Templates lesen
`$collection->get_meta('html')`).

**Frage 2: Item-Anzahl bei is_empty - 0 oder 1?**

**0 Items.** Grund: Trust-Decision TD-5 aus v0.17.0-Plan. Empty-State =
leere Collection. Konsistent mit MAES (`empty news[]` -> 0 Items),
MMB (`empty categories[]` -> 0 Items), TP (`empty videos[]` -> 0 Items),
MIO (`empty tax_dates[]` -> 0 Items).

**Konsequenz fuer Template:** Pseudo-Rebuild liest **NUR Collection-Meta**
(nicht Items). Item-Count irrelevant fuer Render-Pfad.

**Frage 3: Was wenn `html` leer ist UND `is_empty` ist false?**

Defensiv. Adapter setzt: `is_empty || '' === $html` -> 0 Items.
Begruendung: HTML-leer + is_empty=false ist ein Parser-Bug; Adapter
verhaelt sich dann "as if empty" (UI zeigt Empty-State). Defensive
Hardening, kein Drift-Risiko (Pseudo-Rebuild liest `is_empty` aus
Collection-Meta, das auch bei diesem Fallback `true` ist).

**Frage 4: ContentCard-Bridge fuer TC-Items?**

`to_content_card_props()` mapped `generic` -> `news` (DTO-Klasse Z. 347).
Das ist eine **Verlust-Bridge**: ContentCard kennt keine Inline-JS-
Akkordeons, der HTML-Blob wuerde als `body_html` in eine Card geschoben.

**Konsequenz:** Templates **duerfen NICHT** `dhps_component('content-list', ...)`
fuer TC-Items aufrufen. Stattdessen: Pseudo-Rebuild zu `$tc_html` und
das **bestehende `echo $tc_html`-Pattern** wird unveraendert
weiterverwendet (analog MIO tax_dates und MMB Lazy-Akkordeon).

---

## Sektion 3: Branding (kein Service-Variant)

### 3.1 TC ist Standalone

- TC hat **keinen Service-Verwandten** (kein "LXTC" oder aehnliches).
- TC nutzt Steuern-Gruen-Branding (analog MIO/MMB/TP/MAES nicht-LP-
  nicht-LXMIO).
- Branding wird ueber `dhps-service--tc`-Wrapper-Klasse in den Templates
  gesetzt. Wrapper-Token-Switch (wie bei LXMIO `.dhps-service--lxmio`)
  ist NICHT notwendig - TC nutzt die Defaults.

### 3.2 Adapter-Registrierung: EIN Service-Tag

```php
DHPS_Content_Adapter_Registry::register( 'tc', new DHPS_TC_Adapter() );
```

Keine Mehrfach-Registrierung. Anders als MMB/MIL, TP/LP, MIO/LXMIO -
TC ist ein Singleton-Service-Tag.

### 3.3 ALLOWED_SERVICES-Check

`DHPS_Content_Item::ALLOWED_SERVICES` enthaelt:
- `'tc'` (Z. 69) - greift

Keine Anpassung an der DTO-Foundation noetig.

---

## Sektion 4: Sub-Shortcode? Keiner.

TC hat **keinen Sub-Shortcode**. Nur `[tc]`. Maximale Einfachheit.

**Konsequenz:**
- Keine Bridge-Helper-Funktion noetig (anders als MAES `[maes_videos]`
  oder MIO `[mio_termine]`).
- Keine Module-Klasse noetig (anders als `DHPS_MAES_Modules` oder
  `DHPS_TPT_Modules`).
- Keine Force-Legacy-Logik noetig (es gibt keine Filter-Atts auf
  Adapter-Ebene).
- Live-Preview-Endpoint kennt nur `[tc]` (kein Sub-Shortcode-Mapping
  notwendig).

---

## Sektion 5: Template-Migration-Strategie

### 5.1 Mapping pro Template

| Template | Strategie | Begruendung |
|---|---|---|
| `tc/default.php` | **Pseudo-Rebuild** (Header-Variablen aus Collection-Meta) | Render-Code (Service-Wrapper, EmptyState-Component, `echo $tc_html`) bytewise unveraendert |
| `tc/card.php` | **Pseudo-Rebuild** (analog default, plus innerer `dhps-card`-Wrapper) | wie default |
| `tc/compact.php` | **Pseudo-Rebuild** (analog default, plus `--compact`-Modifier) | wie default |

### 5.2 Pseudo-Rebuild-Pattern (am Beispiel default.php)

**Aktueller Kopf (Z. 26-27):**

```php
$tc_html  = $data['html'] ?? '';
$is_empty = ! empty( $data['is_empty'] );
```

**Patch:**

```php
// v0.17.4: Collection-Pfad wenn Adapter aktiv ist, sonst Legacy.
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
    // Defensives Spiegeln: HTML + is_empty leben in Collection-Meta
    // (Adapter setzt sie zentral, Items-Count ist irrelevant fuer Render).
    $tc_html  = (string) $collection->get_meta( 'html', '' );
    $is_empty = (bool) $collection->get_meta( 'is_empty', false );
} else {
    // Legacy-Pfad UNVERAENDERT
    $tc_html  = $data['html'] ?? '';
    $is_empty = ! empty( $data['is_empty'] );
}

// AB HIER UNVERAENDERT (Z. 28+):
// Service-Wrapper-DIV, EmptyState-Component, `echo $tc_html` bytewise unangetastet.
```

**Erkenntnis:** Migration ist **maximal trivial**. Der Render-Code ab
Z. 29 (Service-Wrapper bis `echo $tc_html`) bleibt buchstaeblich
byte-identisch. Der Collection-Pfad rekonstruiert dieselben 2 Variablen
aus Collection-Meta, sodass das gerenderte HTML unveraendert bleibt
(Smoke-Garantie).

### 5.3 `echo $tc_html` Trust-Decision: explizit bestaetigt UNVERAENDERT

Die kritische Zeile (Z. 50-52 in `default.php`, Z. 38-41 in `card.php`,
Z. 39-42 in `compact.php`):

```php
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped --
// HTML aus vertrauenswuerdiger Deubner-API mit Akkordeon-JS.
echo $tc_html;
?>
```

bleibt **bytewise unangetastet**. Pseudo-Rebuild liefert `$tc_html` als
String genau wie Legacy. PHPCS-Pragma bleibt. Begruendungs-Komment bleibt.

**Spec-Anforderung:** Spec MUSS explizit dokumentieren, dass `echo $tc_html`
nicht angetastet wird. Lead-Smoke-Test: `diff` der `echo $tc_html`-Zeilen
zwischen v0.17.3 und v0.17.4 = **0 Aenderungen**.

### 5.4 Helper-Bedarf? Nein.

Anders als MIO (`dhps_mio_item_to_legacy_month`) oder TP
(`dhps_tp_item_to_legacy_video`) braucht TC **keinen Helper**. Begruendung:

1. **Trivialitaet:** 2 Variablen-Reads aus `$collection->get_meta()`
   sind ZU klein fuer eine separate Funktion. Funktions-Call-Overhead +
   Mental-Cost > Inline-2-Liner.
2. **Single-Stelle:** Die 2 Zeilen wiederholen sich 3x (1x pro Template).
   3 Wiederholungen ist die Grenze, ab der ein Helper sich lohnt - aber
   bei 2 Zeilen ist Inline noch billiger.
3. **Keine Transformation:** Im Gegensatz zu MIO (Item -> Legacy-Monats-
   Array) und TP (Item -> Legacy-Video-Array) gibt es bei TC **keine
   Transformation**. Die Werte sind direkt aus Collection-Meta lesbar.

Falls Lead spaeter doch einen Helper ziehen will (z.B. fuer Tests):
`dhps_tc_collection_to_legacy_data( DHPS_Content_Collection $coll ): array`
mit 2-Felder-Output `{html, is_empty}`. Aktuell **NICHT empfohlen**.

---

## Sektion 6: Schema-Vertrag (verbindlich!)

Schema-Vertrag-Vorgehen ist 13x in Folge ohne Critical-Drift gelaufen.
**v0.17.4 = Iteration 14.** Disziplin halten - auch wenn der Scope klein ist.

### 6.1 TC-Adapter Item-Konstruktor-Signatur (1 Item bei !is_empty)

```php
new DHPS_Content_Item(
    'tc-calculators',  // id (Fix-Wert, eindeutig)
    $service,          // 'tc' (aus $service-Param)
    'TC Rechner',      // title (Fix-Wert, niemals gerendert, Pflicht-Fuell)
    'generic',         // type (in ALLOWED_TYPES seit v0.17.0)
    '',                // body (leer - HTML lebt im meta)
    null,              // excerpt
    null,              // image
    null,              // media
    null,              // link
    null,              // date
    array(),           // tags
    null,              // category (TC hat keine Kategorien)
    array(             // meta:
        'html'     => $html,
        'is_empty' => false,
    )
);
```

### 6.2 Meta-Felder-Vertrag (Item, nur bei !is_empty)

| Key | Typ | Pflicht | Quelle | Begruendung |
|---|---|---|---|---|
| `html` | string | ja | 1:1 aus `$parser_output['html']` | HTML-Blob (kann Inline-JS enthalten) als Fluchtweg-Meta |
| `is_empty` | bool | ja (immer false hier) | hardcoded false | Konsistenz mit Collection-Meta-Schluessel |

### 6.3 Collection-Meta-Felder fuer TC

```php
array(
    'is_empty' => bool,    // 1:1 aus Parser - Render-Schalter
    'html'     => string,  // 1:1 aus Parser - HTML-Quelle (defensiv gespiegelt
                           //   bei !is_empty; bei is_empty Original-HTML zur
                           //   Diagnose)
)
```

**Vertraglich:** Beide Felder sind IMMER gesetzt (auch bei leerer
Collection). `is_empty=true` und `html=''` ist ein gueltiger State
(Parser hat Empty-State erkannt, Original-HTML war ein leerer
Container).

### 6.4 Adapter-Signatur

```php
final class DHPS_TC_Adapter implements DHPS_Content_Adapter_Interface {
    public function adapt( array $parser_output, string $service ): DHPS_Content_Collection;
}
```

Klassen-/Datei-Konvention (Autoloader-Pflicht):

| Klassenname | Dateipfad |
|---|---|
| `DHPS_TC_Adapter` | `includes/class-dhps-tc-adapter.php` |

### 6.5 Adapter-Logik (Pseudo-Code)

```php
public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {
    $html     = isset( $parser_output['html'] ) ? (string) $parser_output['html'] : '';
    $is_empty = ! empty( $parser_output['is_empty'] );

    // Defensive Hardening: leerer HTML-String impliziert empty,
    // auch wenn $parser_output['is_empty'] false sagen wuerde.
    if ( '' === trim( $html ) ) {
        $is_empty = true;
    }

    $collection_meta = array(
        'is_empty' => $is_empty,
        'html'     => $html,
    );

    if ( $is_empty ) {
        // Empty-State: 0 Items, Collection-Meta haelt das HTML zur Diagnose.
        return new DHPS_Content_Collection( $service, array(), $collection_meta );
    }

    // Content-State: 1 generisches Item, HTML in Item.meta.
    $item = new DHPS_Content_Item(
        'tc-calculators',
        $service,
        'TC Rechner',
        'generic',
        '',
        null,
        null,
        null,
        null,
        null,
        array(),
        null,
        array(
            'html'     => $html,
            'is_empty' => false,
        )
    );

    return new DHPS_Content_Collection( $service, array( $item ), $collection_meta );
}
```

### 6.6 Pseudo-Rebuild-Schema (Template-Vertrag)

Template-Pseudo-Rebuild ZURUECK in 2 Header-Variablen:

```php
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
    $tc_html  = (string) $collection->get_meta( 'html', '' );
    $is_empty = (bool) $collection->get_meta( 'is_empty', false );
} else {
    $tc_html  = $data['html'] ?? '';
    $is_empty = ! empty( $data['is_empty'] );
}
```

**Iteration ueber Items**: NICHT noetig. Pseudo-Rebuild liest direkt aus
Collection-Meta. Item-Count ist irrelevant fuer Render.

### 6.7 ALLOWED_TYPES + ALLOWED_SERVICES sind bereit

- `'generic'` in `DHPS_Content_Item::ALLOWED_TYPES` (Z. 48) -> greift
- `'tc'` in `ALLOWED_SERVICES` (Z. 69) -> greift

Keine Anpassung an der DTO-Foundation noetig. **0 Schema-Drift-Risiko**
auf Foundation-Ebene.

---

## Sektion 7: Cleanup-Themen (v0.17.x-Abschluss)

### 7.1 Tech-Debt-Stand vor v0.17.4

| Ticket | Beschreibung | Quelle | Status |
|---|---|---|---|
| TD-V0171-2 | MMB-AJAX-Handler auf Adapter | v0.17.1 | offen |
| TD-V0171-3 | MMB-Search-Endpoint auf Adapter | v0.17.1 | offen |
| TD-V0172-1 | tp/compact.php Refactor (initCompactAccordion-JS-Risiko) | v0.17.2 | offen |
| TD-V0172-2 | Datum-Normalisierung MM/YY -> ISO (TP) | v0.17.2 | offen |
| TD-V0173-1 | `[mio_termine]`-Sub-Shortcode auf Adapter-Bridge | v0.17.3 | offen |
| TD-V0173-2 | Datum-Normalisierung Monat (`'Juli 2026'` -> ISO) | v0.17.3 | offen |
| TD-V0173-4 | MIO-News-AJAX-Endpoint auf Adapter | v0.17.3 | offen |
| TD-V0173-5 | Live-Preview-Schema-Erweiterung pruefen | v0.17.3 | offen |

### 7.2 Empfehlung: TC + 1 minimaler Cleanup

**v0.17.4 ist primaer TC-Adapter.** Cleanup-Scope sollte minimal sein,
um das Risiko zu halten und den Abschluss-Release sauber zu halten.

**Empfehlung:** Statt eines Code-Cleanups ein **Migrations-Abschluss-
Report** als Doku-Mitnahme:

- Neue Datei `docs/project/51-MIGRATION-COMPLETE-v0174.md` (oder als
  Sektion im 52-CHANGELOG-v0174.md)
- Inhalte:
  - Bilanz "9/9 Services migriert" mit Tabelle (Adapter / Templates /
    LOC pro Service)
  - 14 Trust-Decisions kumulativ (v0.17.0 TD-1 bis TD-14, v0.17.1 TD-MMB,
    v0.17.2 TD-TP/TPT/LP, v0.17.3 TD-MIO, v0.17.4 TD-TC) als zentrale
    Uebersicht
  - Offene Tech-Debt-Tickets fuer v0.17.x-Abschluss (oben Liste 7.1)
  - Roadmap v0.18.0 (Legacy-Pfad in Templates entfernen)

**Begruendung gegen Code-Cleanup in v0.17.4:**

- TD-V0171-2 + TD-V0171-3 (MMB-AJAX/Search): substanzieller Scope
  (~300 LOC), eigene Discovery noetig (z.B. AJAX-Handler bekommt 2.
  Pfad?). Risiko-Versteckmoeglichkeit (Live-Search bricht still).
- TD-V0173-1 (`[mio_termine]`-Bridge): mittlerer Scope, ABER Filter-Atts
  (`month`, `count`) machen den Adapter-Pfad weitestgehend nutzlos
  (Force-Legacy fast immer aktiv). Aufwand/Nutzen-Ratio schlecht.
- TD-V0172-1 (tp/compact.php): bekannter JS-Risiko-Refactor, eigene
  Discovery + Smoke-Tests nodig. Zu gross fuer Abschluss-Release.
- TD-V0172-2 + TD-V0173-2 (Datum-Normalisierung): erfordert Sprach-
  Lokalisierungs-Entscheidung ("Juli" -> 7). Kein Quick-Win.
- TD-V0173-4 (MIO-News-AJAX): substanzieller Scope (zweiter Adapter
  `DHPS_MIO_News_Adapter` mit eigenem Schema).

**Empfehlung Lead-Beschluss:**
- TC-Adapter implementieren (Sektion 6 ist Schema-Vertrag)
- Migrations-Abschluss-Report als Sektion im v0.17.4-CHANGELOG (kein
  separates Doc - bleibt im Kontext)
- Big-Cleanup verschoben auf v0.17.5 oder v0.18.0-Discovery (eigene
  Tranche)

### 7.3 Live-Preview-Schema (TD-V0173-5) - kurzer Check

`DHPS_Preview_Renderer::SERVICE_ATTS_SCHEMA` enthaelt 13 Services mit
70 Atts. TC-Atts sind bereits enthalten (Live-Preview funktioniert seit
v0.15.4 fuer alle 9 Hauptservices). Adapter-Aenderung wirkt sich NICHT
auf das Atts-Schema aus (Adapter konsumiert Parser-Output, Atts steuern
Shortcode-Behavior **vor** dem Parser).

**Lead-Pflicht v0.17.4:** Kurzes `grep "tc"` in
`includes/class-dhps-preview-renderer.php`-Schema verifizieren, dass keine
Schema-Aenderung noetig ist. Ergebnis im CHANGELOG dokumentieren (Caveat C0
"Live-Preview-Schema unveraendert").

---

## Sektion 8: Acceptance-Kriterien T1-T15

### T1: TC-Adapter mit empty Parser-Output

```php
$collection = ( new DHPS_TC_Adapter() )->adapt( array(), 'tc' );
```

Erwartet:
- `$collection->is_empty() === true` (0 Items)
- `$collection->get_meta('is_empty') === true`
- `$collection->get_meta('html') === ''`

### T2: TC-Adapter mit Empty-State (parser is_empty=true)

```php
$parsed = array(
    'html'        => '<div class="taxcalc"><p class="sm_buttons"></p></div>',
    'is_empty'    => true,
    'service_tag' => 'tc',
);
$collection = ( new DHPS_TC_Adapter() )->adapt( $parsed, 'tc' );
```

Erwartet:
- `$collection->count() === 0` (kein Item bei empty)
- `$collection->is_empty() === true`
- `$collection->get_meta('is_empty') === true`
- `$collection->get_meta('html') === '<div class="taxcalc"><p class="sm_buttons"></p></div>'` (Original-HTML zur Diagnose)

### T3: TC-Adapter mit Content-State (parser is_empty=false)

```php
$html_blob = '<div class="taxcalc"><div class="calc_area">...rechner...</div><script>function test_einblenden(){}</script></div>';
$parsed = array(
    'html'        => $html_blob,
    'is_empty'    => false,
    'service_tag' => 'tc',
);
$collection = ( new DHPS_TC_Adapter() )->adapt( $parsed, 'tc' );
```

Erwartet:
- `$collection->count() === 1`
- `$collection->is_empty() === false`
- `$collection->get_meta('is_empty') === false`
- `$collection->get_meta('html') === $html_blob`
- `$collection->first()->id === 'tc-calculators'`
- `$collection->first()->type === 'generic'`
- `$collection->first()->service === 'tc'`
- `$collection->first()->title === 'TC Rechner'`
- `$collection->first()->body === ''`
- `$collection->first()->meta['html'] === $html_blob`
- `$collection->first()->meta['is_empty'] === false`

### T4: TC-Adapter defensive Hardening - HTML leer aber is_empty=false

```php
$parsed = array(
    'html'        => '',
    'is_empty'    => false,  // Parser-Bug
    'service_tag' => 'tc',
);
$collection = ( new DHPS_TC_Adapter() )->adapt( $parsed, 'tc' );
```

Erwartet: Adapter verhaelt sich als waere is_empty=true:
- `$collection->count() === 0`
- `$collection->get_meta('is_empty') === true`
- `$collection->get_meta('html') === ''`

### T5: TC-Adapter mit whitespace-only HTML

```php
$parsed = array(
    'html'        => "   \n\t  ",
    'is_empty'    => false,
    'service_tag' => 'tc',
);
$collection = ( new DHPS_TC_Adapter() )->adapt( $parsed, 'tc' );
```

Erwartet: Adapter erkennt whitespace-only via `trim()`-Check:
- `$collection->count() === 0`
- `$collection->get_meta('is_empty') === true`

### T6: TC-Adapter ignoriert service_tag-Override im Parser-Output

Parser-Output mit `service_tag => 'evil'`, $service-Param='tc':

Erwartet: Item.service === 'tc' (aus Param), nicht 'evil'. Konsistent
mit allen Adaptern (Pipeline-Param wins).

### T7: TC-Item via to_content_card_props() liefert generic->news-Mapping

```php
$item = $collection->first();
$props = $item->to_content_card_props();
```

Erwartet (siehe DHPS_Content_Item Z. 347):
- `$props['type'] === 'news'`
- `$props['title'] === 'TC Rechner'`
- `$props['service'] === 'tc'`
- `$props['body_html']` NICHT gesetzt (Item.body ist leer)

**Hinweis:** Templates rufen das NIE auf - Test dokumentiert nur die
ContentCard-Bridge-Verluste (Sub-Struktur im meta wird nicht erfasst).

### T8: TC-Pipeline-Smoke - Frontend `[tc]` rendert HTML-bytewise

Vor- und nach-Migration: HTML-Diff zwischen v0.17.3 (ohne TC-Adapter) und
v0.17.4 (mit TC-Adapter aktiv) ist **0**. Pseudo-Rebuild im Template
garantiert das.

Smoke-Variation:
- `[tc]` mit gueltiger kdnr (Content-State)
- `[tc]` mit ungueltiger kdnr (Empty-State)

### T9: TC-Card-Layout - HTML-bytewise

Frontend `[tc layout="card"]` rendert bytewise identisch zu v0.17.3.

### T10: TC-Compact-Layout - HTML-bytewise

Frontend `[tc layout="compact"]` rendert bytewise identisch zu v0.17.3.

### T11: `echo $tc_html` Trust-Decision unangetastet

`diff` der `echo $tc_html`-Zeile (inkl. PHPCS-Pragma und Begruendungs-
Komment) zwischen v0.17.3 und v0.17.4 = **0 Aenderungen**. Smoke ueber
3 Templates (default, card, compact).

### T12: Inline-JS test_einblenden / test_ausblenden funktioniert weiter

Browser-Test: TC-Akkordeon klick-aufklapp-bar (Inline-JS-Funktionen sind
im `$tc_html` enthalten, werden durch `echo` ausgegeben, Browser
executet sie). Verifiziert: Adapter modifiziert das HTML NICHT.

### T13: Adapter-Exception ist Fail-Soft

Bei manipuliertem TC-Parser-Output, der den Adapter zum Werfen bringt
(unwahrscheinlich, da Adapter sehr defensiv ist - aber via Filter-Hook
moeglich), faengt die Pipeline ab. `$collection === null`, Template
faellt auf Legacy-Pfad zurueck. Smoke: kein PHP-Fatal, TC-Frontend
rendert ueber `$data`.

### T14: Bootstrap-Registration - TC-Adapter via Registry abrufbar

```php
$adapter = DHPS_Content_Adapter_Registry::for_service( 'tc' );
```

Erwartet: `$adapter instanceof DHPS_TC_Adapter`.

### T15: Live-Preview-Endpoint funktioniert unveraendert

Live-Preview im Admin-Dashboard fuer `[tc]` rendert bytewise identisch
zu v0.17.3. Beweist dass Preview-Renderer-Pfad keinen Adapter-Drift bekommt.

### Smoke-Tests (Lead-Smoke)

- F1: Frontend `[tc]` mit gueltiger kdnr (Content) - bytewise gegen v0.17.3
- F2: Frontend `[tc]` mit ungueltiger kdnr (Empty) - bytewise gegen v0.17.3
- F3: Frontend `[tc layout="card"]` Content + Empty - bytewise gegen v0.17.3
- F4: Frontend `[tc layout="compact"]` Content + Empty - bytewise gegen v0.17.3
- F5: TC-Akkordeon-Klick (Inline-JS) - Smoke im Browser
- F6: Live-Preview `[tc]` im Admin-Dashboard - bytewise gegen v0.17.3
- F7: Pipeline-Smoke: 80 dhps-Klassen unveraendert (`curl | grep "class=\"dhps-"`-Diff = 0)

---

## Sektion 9: Spec-Aufteilung

### 9.1 Empfehlung: Pure Lead-Direct

**Begruendung:**

Im Vergleich zu den 4 vorherigen Adapter-Releases:

| Release | Scope | Specialists |
|---|---|---|
| v0.17.0 MAES | ~700 LOC, 3 Item-Typen, neue DTO-Foundation | 2 (F1+F2) |
| v0.17.1 MMB | ~280 LOC, 1 Item-Typ + Categories-Meta, MMB+MIL, Sub-Shortcodes | 2 (F1+F2) |
| v0.17.2 TP/LP+TPT | ~450 LOC, 2 Adapter, TP+LP+TPT, 5 Templates | 2 (F1+F2) |
| v0.17.3 MIO/LXMIO | ~200 LOC, 1 Item-Typ (tax_date), MIO+LXMIO, 3 Templates | 1 (F1) + Lead Phase-0 |
| **v0.17.4 TC** | **~80 LOC Adapter + 3x ~10 LOC Templates + 3 LOC Bootstrap** | **0 (pure Lead)** |

Trend ist klar: jeder Release wurde kleiner. v0.17.4 ist das logische
Endziel. Eine Spec-Aufteilung waere kuenstlich:

1. **Scope ist trivial:** ~80 LOC Adapter + 3 Pseudo-Rebuild-Bloecke je
   6 Zeilen = ~100 LOC Production-Code. Kleiner als jede einzelne
   Specialist-Spec, die wir je gemacht haben.
2. **Mapping ist konzeptionell so duenn**, dass eine Specialist-Spec
   mehr Setup-Overhead waere als die Implementation.
3. **Keine Helper-Datei noetig** (Sektion 5.4). Keine Phase-0 noetig.
4. **Schema-Vertrag** (Sektion 6) gibt dem Lead alle notwendigen
   Entscheidungen vorab. Der Lead schreibt direkt nach Discovery-
   Approval.

**Alternative: 1 Specialist (NICHT empfohlen)**

Eine Specialist-Spec wuerde fuer ein so kleines Stueck Code mehr
Koordinations-Overhead schaffen als Wert liefern. Lessons aus v0.17.3
(1 Specialist + Lead-Phase-0 hat noch gut funktioniert, aber war schon
grenzwertig). v0.17.4 ist ueber die Grenze - pure Lead ist sauberer.

### 9.2 Lead-Direct-Scope

**Code:**
- `includes/class-dhps-tc-adapter.php` (NEU, ~80 LOC) - Adapter-Klasse
- `Deubner_HP_Services.php` (3 Zeilen Patch, Adapter-Registry-Call)
- `public/views/services/tc/default.php` (Pseudo-Rebuild-Block ~10 LOC)
- `public/views/services/tc/card.php` (Pseudo-Rebuild-Block ~10 LOC)
- `public/views/services/tc/compact.php` (Pseudo-Rebuild-Block ~10 LOC)

**Doku:**
- `docs/project/52-CHANGELOG-v0174.md` (NEU)
- `MEMORY.md` (MILESTONE 22 + v0.17.4 Implementation-Notes)
- `README.md` (Version-Bump 0.17.3 -> 0.17.4)
- `Deubner_HP_Services.php` (Version-Constant 0.17.3 -> 0.17.4)

**Tests:**
- Inline-Test-Skript `test-tc-adapter.php` (T1-T7, Unit-Style)
- Stage-Smoke (F1-F7)

**Total Aufwand:** ~250 LOC Code+Doku, 0 Specialist-Briefings.

### 9.3 Phasen-Reihenfolge

```
Phase 1 (Lead):     Adapter-Klasse schreiben + Tests T1-T7
Phase 2 (Lead):     3 Template-Patches (Pseudo-Rebuild)
Phase 3 (Lead):     Bootstrap-Registration + Version-Bump
Phase 4 (parallel): Stage-Smoke (F1-F7) + Live-Preview-Check
Phase 5 (Lead):     CHANGELOG, MIGRATION-COMPLETE-Sektion, MEMORY, RC-Tag
```

---

## Sektion 10: Risiken + Tech-Debt

### 10.1 Top-3-Risiken (Lead-Briefing)

| ID | Risiko | Severity | Mitigation |
|---|---|---|---|
| **R1** | **`echo $tc_html` Trust-Decision wird unbeabsichtigt modifiziert** (z.B. Lead fuegt `wp_kses_post` "zur Sicherheit" hinzu) | **HIGH** | T11 (bytewise-Diff der echo-Zeile) ist Pflicht-Smoke. Spec-Sektion 5.3 dokumentiert das explizit. CHANGELOG erwaehnt Trust-Decision unveraendert |
| **R2** | **Adapter modifiziert HTML** (z.B. Adapter "normalisiert" Whitespace, "entfernt" Script-Tags) und bricht Inline-JS-Akkordeon | **HIGH** | T12 (Browser-Smoke Inline-JS) + Spec-Sektion 1.3 dokumentiert 1:1-Passthrough. Adapter-Code ist absichtlich nur `(string)` ohne weitere Sanitization |
| **R3** | **Pseudo-Rebuild liest aus falscher Quelle** (z.B. Lead liest `$collection->first()->meta['html']` statt `$collection->get_meta('html')`) und schlaegt bei Empty-State fehl (count=0, kein `first()`) | MED | Schema-Vertrag Sektion 6.6 sperrt das. Empty-State-Smoke (F2) prueft das |

### 10.2 Vollstaendige Risiken-Matrix

| ID | Risiko | Severity | Mitigation |
|---|---|---|---|
| R1 | Trust-Decision `echo $tc_html` modifiziert | HIGH | T11 + Spec-Sektion 5.3 + Code-Review-Check |
| R2 | Adapter modifiziert HTML | HIGH | T12 + Spec-Sektion 1.3 + Adapter ist `(string)`-Cast ohne weitere Aktionen |
| R3 | Pseudo-Rebuild aus falscher Quelle | MED | Schema-Vertrag 6.6 + F2 Empty-State-Smoke |
| R4 | `'generic'`-Type ist erster Anwendungsfall - `to_content_card_props()` mapped generic->news, ContentCard wuerde TC-HTML als `body_html` einer Card setzen | LOW | TC-Templates rufen das NIE auf (Sektion 2.5 Frage 4). Spec dokumentiert die Verlust-Bridge |
| R5 | Item.title='TC Rechner' wird unbeabsichtigt gerendert (z.B. via ContentCard) | LOW | Templates rufen ContentCard NIE auf - Pseudo-Rebuild liest direkt aus Collection-Meta. Falls Theme den Adapter via Filter-Hook abfaengt und ContentCard nutzt, ist der Title sichtbar - aber das ist Theme-Verantwortung |
| R6 | Filter-Hook `dhps_content_adapter_for_service` ueberschreibt den TC-Adapter mit etwas, das `is_empty`/`html`-Meta nicht setzt | LOW | Templates haben Default-Werte (`get_meta('is_empty', false)`, `get_meta('html', '')`). Defensives Reading |
| R7 | TC-API liefert in Zukunft strukturiertes JSON statt HTML-Blob | n/a | Aktuell HTML-only, keine bekannte API-Roadmap. Adapter waere dann zu erweitern (mehrere Items, type='document' pro Rechner). Out of scope v0.17.4 |
| R8 | Performance bei 300 KB HTML - 2x Referenz (Item.meta + Collection.meta) | LOW | PHP-CoW: 0 Byte Mehr-Speicher. L2-Cache cached nur Parser-Output (TD-7), nicht Collection. Smoke T14 bestaetigt < 3ms Adapter-Overhead |
| R9 | DTO `body=''` als Trennung von HTML-Blob - jemand erwartet Default `body=$html` | LOW | Spec-Sektion 2.2 + 6.1 dokumentieren Entscheidung explizit. CHANGELOG erwaehnt es als Trust-Decision |

### 10.3 Tech-Debt-Tickets fuer v0.17.x-Abschluss / v0.18.0

| Ticket | Beschreibung | Zielversion |
|---|---|---|
| TD-V0174-1 | Legacy-Pfad in Templates entfernen (alle 9 Services). Erfordert: PHP-Mindestversion 8.1 garantiert, alle Sites auf v0.17.4 migriert | v0.18.0 |
| TD-V0174-2 | TC-API JSON-Migration (falls Deubner-API jemals strukturiertes JSON liefert) - aktuell out of scope | v0.18.x |
| TD-V0174-3 | ContentCard-Branche fuer `tax_date` und `generic`-Types ueberdenken (heute beide auf news/document gemappt - verlustbehaftet) | v0.18.0 |
| Verschoben | Big-Cleanup-Tranche: TD-V0171-2/3 (MMB-AJAX/Search), TD-V0172-1 (tp/compact), TD-V0172-2 (Datum), TD-V0173-1 ([mio_termine]), TD-V0173-2 (Datum Monat), TD-V0173-4 (MIO-News-AJAX) | v0.17.5 oder v0.18.0-Discovery |

### 10.4 Trust-Decisions v0.17.4 (neu)

| ID | Annahme | Begruendung |
|---|---|---|
| TD-TC-1 | **Option B** (Empty-Coll oder 1-Item-Coll mit meta['html']) statt Option A (body=$html) | DTO-Disziplin: body ist semantischer Hauptinhalt, meta ist Fluchtweg-Slot. Konsistent mit MIO/MMB-Adaptern |
| TD-TC-2 | **`echo $tc_html` Trust-Decision unangetastet** | Sicherheits-Premise v0.13.0/v0.14.4 gilt weiter (kdnr-Auth + HTTPS). Adapter veraendert HTML NICHT |
| TD-TC-3 | **Kein Helper noetig** (anders als MIO/TP) | Pseudo-Rebuild ist 2-Zeilen-Inline-Lesung aus Collection-Meta. Helper waere Overhead |
| TD-TC-4 | **Defensive Hardening**: leerer/whitespace-only HTML wird als is_empty behandelt auch wenn Parser-Flag false sagt | Parser-Bug-Resilienz. Pseudo-Rebuild liest is_empty aus Collection-Meta, das auch in diesem Fall true ist |
| TD-TC-5 | **Item-Title `'TC Rechner'` fix** | DHPS_Content_Item erzwingt non-empty title. Wert ist NIE gerendert (Templates lesen meta['html']) |

---

## Sektion 11: Spec-Briefing-Material

Da v0.17.4 als **Pure Lead-Direct** umgesetzt wird, ersetzt diese Sektion
das traditionelle Specialist-Briefing.

### 11.1 Dateipfade fuer Neuschoepfung (Lead)

```
includes/class-dhps-tc-adapter.php
docs/project/52-CHANGELOG-v0174.md
```

### 11.2 Dateipfade fuer Anpassung (Lead)

```
Deubner_HP_Services.php                    # Version 0.17.3 -> 0.17.4, 3 Zeilen Adapter-Registry
README.md                                  # Version-Bump
public/views/services/tc/default.php       # Pseudo-Rebuild-Block (~10 LOC)
public/views/services/tc/card.php          # Pseudo-Rebuild-Block (~10 LOC)
public/views/services/tc/compact.php       # Pseudo-Rebuild-Block (~10 LOC)
MEMORY.md                                  # MILESTONE 22 + Implementation-Notes
```

### 11.3 Code-Skelett: TC-Adapter

```php
<?php
/**
 * TC-Adapter (v0.17.4): wandelt DHPS_TC_Parser-Output in DHPS_Content_Collection.
 *
 * Fuenfter und letzter Adapter im einheitlichen Datenmodell. Nach
 * MAES (v0.17.0), MMB/MIL (v0.17.1), TP/TPT/LP (v0.17.2) und MIO/LXMIO
 * (v0.17.3) jetzt TC. Damit sind 9 von 9 Hauptservices migriert.
 *
 * TC ist konzeptuell der einfachste Service:
 * - Wrapper-Parser ohne strukturierten Output (HTML + is_empty als
 *   einzige Felder)
 * - Inline-JS (test_einblenden / test_ausblenden) ist Bestandteil des HTML
 * - 25+ Steuer-Rechner als Akkordeon
 *
 * Mapping-Strategie (Option B aus Discovery 30-TC-CLEANUP-PLAN-v0174
 * Sektion 2.2):
 * - is_empty=true  -> 0 Items, Collection-Meta `is_empty=true, html=$html` (Diagnose)
 * - is_empty=false -> 1 generisches Item, meta['html']=$html, plus
 *                     Collection-Meta `is_empty=false, html=$html` (defensiv gespiegelt)
 *
 * KRITISCH (Trust-Decision TD-TC-2): Das HTML wird NICHT modifiziert,
 * NICHT sanitized, NICHT escaped. Inline-JS ist Bestandteil des Akkordeons
 * und MUSS erhalten bleiben. Sicherheits-Premise v0.13.0/v0.14.4 (TC-API
 * mit eigener kdnr-Auth ueber HTTPS) gilt weiter.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DHPS_TC_Adapter
 *
 * Fuenfter und letzter Adapter im DTO-Layer, deckt TC ab.
 *
 * @since 0.17.4
 */
final class DHPS_TC_Adapter implements DHPS_Content_Adapter_Interface {

    public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {
        $html     = isset( $parser_output['html'] ) ? (string) $parser_output['html'] : '';
        $is_empty = ! empty( $parser_output['is_empty'] );

        // Defensive Hardening (TD-TC-4): leerer/whitespace-only HTML wird
        // als empty behandelt, auch wenn Parser-Flag false sagen wuerde.
        if ( '' === trim( $html ) ) {
            $is_empty = true;
        }

        $collection_meta = array(
            'is_empty' => $is_empty,
            'html'     => $html,
        );

        if ( $is_empty ) {
            return new DHPS_Content_Collection( $service, array(), $collection_meta );
        }

        $item = new DHPS_Content_Item(
            'tc-calculators',
            $service,
            'TC Rechner',
            'generic',
            '',
            null,
            null,
            null,
            null,
            null,
            array(),
            null,
            array(
                'html'     => $html,
                'is_empty' => false,
            )
        );

        return new DHPS_Content_Collection( $service, array( $item ), $collection_meta );
    }
}
```

### 11.4 Code-Skelett: Bootstrap-Patch

`Deubner_HP_Services.php` nach Block "3a-5. MIO-Adapter" (Z. 378
nach `register( 'lxmio', $mio_adapter )`):

```php
// 3a-6. TC-Adapter (v0.17.4): registriert NUR fuer 'tc'. Kein Service-
//       Verwandter (anders als MMB+MIL, TP+LP, MIO+LXMIO). TC ist
//       Wrapper-Service mit eigener kdnr-Lizenz und eigener API. Item
//       ist 'generic' type, HTML lebt in meta (DTO-Disziplin) und in
//       Collection-Meta (Pseudo-Rebuild-Quelle). Trust-Decision
//       echo $tc_html aus v0.13.0/v0.14.4 unangetastet.
//       Mit TC sind 9/9 Hauptservices auf das einheitliche Datenmodell
//       migriert (Migrations-Abschluss).
DHPS_Content_Adapter_Registry::register( 'tc', new DHPS_TC_Adapter() );
```

### 11.5 Code-Skelett: Template-Patch (default.php)

`public/views/services/tc/default.php` Z. 22-28 ersetzen:

**Vor:**
```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tc_html  = $data['html'] ?? '';
$is_empty = ! empty( $data['is_empty'] );
?>
```

**Nach:**
```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// v0.17.4: Collection-Pfad wenn TC-Adapter aktiv, sonst Legacy.
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
    // Pseudo-Rebuild: HTML + is_empty leben in Collection-Meta (TC-Adapter
    // setzt sie zentral, unabhaengig vom Item-Count).
    $tc_html  = (string) $collection->get_meta( 'html', '' );
    $is_empty = (bool) $collection->get_meta( 'is_empty', false );
} else {
    // Legacy-Pfad UNVERAENDERT
    $tc_html  = $data['html'] ?? '';
    $is_empty = ! empty( $data['is_empty'] );
}
?>
```

**Render-Code ab Z. 29 (`<div class="dhps-service...">`)**: BYTEWISE
UNVERAENDERT. Insbesondere `echo $tc_html` (Z. 50-52 im Original) bleibt
unangetastet.

### 11.6 Test-Skript-Skelett

```php
<?php
// test-tc-adapter.php (in Plugin-Root, lokal ausgefuehrt)

require_once __DIR__ . '/wp-load-stubs.php';
require_once __DIR__ . '/includes/class-dhps-content-item.php';
require_once __DIR__ . '/includes/class-dhps-content-collection.php';
require_once __DIR__ . '/includes/class-dhps-content-adapter-interface.php';
require_once __DIR__ . '/includes/class-dhps-tc-adapter.php';

$tests_passed = 0;
$tests_failed = 0;

function assert_true( $cond, $msg ) {
    global $tests_passed, $tests_failed;
    if ( $cond ) { $tests_passed++; echo "PASS: $msg\n"; }
    else { $tests_failed++; echo "FAIL: $msg\n"; }
}

// T1: Empty Input
$coll = ( new DHPS_TC_Adapter() )->adapt( array(), 'tc' );
assert_true( $coll->is_empty(), 'T1: Empty input -> empty collection' );
assert_true( true === $coll->get_meta('is_empty'), 'T1: Meta is_empty=true' );
assert_true( '' === $coll->get_meta('html'), 'T1: Meta html=""' );

// T2: Parser is_empty=true
$parsed = array( 'html' => '<div class="taxcalc"><p class="sm_buttons"></p></div>', 'is_empty' => true );
$coll = ( new DHPS_TC_Adapter() )->adapt( $parsed, 'tc' );
assert_true( 0 === $coll->count(), 'T2: Empty-State -> 0 Items' );
assert_true( true === $coll->get_meta('is_empty'), 'T2: Meta is_empty=true' );

// T3: Content-State
$html_blob = '<div class="taxcalc"><div class="calc_area">x</div></div>';
$parsed = array( 'html' => $html_blob, 'is_empty' => false );
$coll = ( new DHPS_TC_Adapter() )->adapt( $parsed, 'tc' );
assert_true( 1 === $coll->count(), 'T3: Content-State -> 1 Item' );
assert_true( 'tc-calculators' === $coll->first()->id, 'T3: Item.id = tc-calculators' );
assert_true( 'generic' === $coll->first()->type, 'T3: Item.type = generic' );
assert_true( $html_blob === $coll->first()->meta['html'], 'T3: Item.meta.html unmodified' );

// T4: Defensive Hardening (html leer, is_empty=false)
$coll = ( new DHPS_TC_Adapter() )->adapt( array( 'html' => '', 'is_empty' => false ), 'tc' );
assert_true( 0 === $coll->count(), 'T4: HTML leer -> as if empty' );
assert_true( true === $coll->get_meta('is_empty'), 'T4: Meta is_empty force-true' );

echo "\n--- Tests: $tests_passed passed, $tests_failed failed ---\n";
```

### 11.7 Migrations-Abschluss-Report (Sektion im CHANGELOG)

Im `52-CHANGELOG-v0174.md` als eigene Sektion. Vorlage:

```markdown
## Migrations-Abschluss v0.17.x

### Bilanz 9/9 Services migriert

| Service | Adapter | Templates | LOC Adapter | Migrations-Release |
|---|---|---|---|---|
| MAES | DHPS_MAES_Adapter | 3 (videos/merkblaetter/aktuelles) | ~150 | v0.17.0 |
| MMB | DHPS_MMB_Adapter | 3 (default/card/compact) | ~180 | v0.17.1 |
| MIL | (geteilt) DHPS_MMB_Adapter | erbt MMB | - | v0.17.1 |
| TP | DHPS_TP_Adapter | 2 (default/card) | ~140 | v0.17.2 |
| LP | (geteilt) DHPS_TP_Adapter | erbt TP | - | v0.17.2 |
| TPT | DHPS_TPT_Adapter | 3 (default/card/compact) | ~120 | v0.17.2 |
| MIO | DHPS_MIO_Adapter | 3 (default/card/compact) | ~175 | v0.17.3 |
| LXMIO | (geteilt) DHPS_MIO_Adapter | erbt MIO | - | v0.17.3 |
| TC | DHPS_TC_Adapter | 3 (default/card/compact) | ~80 | v0.17.4 |

Gesamt: 5 Adapter-Klassen + 1 DTO-Foundation, 9 Service-Tags, 17
Templates auf Collection-Pfad migriert, 14+ Trust-Decisions kumulativ.

### Offene Tech-Debt-Tickets fuer v0.17.5 / v0.18.0

- TD-V0171-2: MMB-AJAX-Handler auf Adapter
- TD-V0171-3: MMB-Search-Endpoint auf Adapter
- TD-V0172-1: tp/compact.php JS-Refactor
- TD-V0172-2: TP Datum-Normalisierung MM/YY -> ISO
- TD-V0173-1: [mio_termine] Sub-Shortcode auf Adapter-Bridge
- TD-V0173-2: MIO Datum-Normalisierung Monat -> ISO
- TD-V0173-4: MIO-News-AJAX-Endpoint auf Adapter
- TD-V0174-1: Legacy-Pfad in Templates entfernen (v0.18.0 Big-Migration)
- TD-V0174-3: ContentCard-Branche fuer tax_date/generic verbessern

### Roadmap v0.18.0

- Legacy-`$data`-Pfad in allen Templates entfernen (Adapter ist Pflicht)
- PHP-Mindestversion explizit auf 8.1 (laeuft seit v0.17.0)
- Optional: Big-Cleanup-Tranche der oben gelisteten Tech-Debt-Tickets

### Schema-Vertrag-Vorgehen

14x in Folge ohne Critical-Schema-Drift (v0.15.0, v0.15.3, v0.15.4,
v0.15.5, v0.16.0, v0.16.1, v0.16.2, v0.16.3, v0.17.0, v0.17.1, v0.17.2,
v0.17.3, v0.17.4 + 1 weitere). Methodisch sauber, Konvention im
Plugin-Lifecycle eingebrannt.
```

---

## Anhang: Lead-Briefing-Zusammenfassung

| Frage | Antwort |
|---|---|
| **Mapping-Entscheidung** | **Option B** - Wrapper-Adapter, Empty-Collection bei is_empty=true, 1 generisches Item bei is_empty=false mit `meta['html']` (DTO-Disziplin: body bleibt leer). |
| **Helper-Bedarf** | **Nein.** Pseudo-Rebuild ist 2-Zeilen-Inline-Lesung aus Collection-Meta. Helper waere Overhead. |
| **Cleanup-Scope-Empfehlung** | **TC + Migrations-Abschluss-Report als CHANGELOG-Sektion**. Kein Code-Cleanup. Big-Cleanup-Tranche verschoben auf v0.17.5/v0.18.0. |
| **Spec-Aufteilung** | **Pure Lead-Direct (kein Specialist)**. Scope so klein (~80 LOC Adapter + 3 Templates) dass Spec-Overhead groesser waere als Wert. |
| **Top-3-Risiken** | R1 (Trust-Decision `echo $tc_html` unbeabsichtigt modifiziert) - HIGH; R2 (Adapter modifiziert HTML) - HIGH; R3 (Pseudo-Rebuild aus falscher Quelle) - MED. Mitigation in Sektion 10.1 |
| **Geschaetzter Aufwand** | **S (klein)** - ~80 LOC Adapter, 3x ~10 LOC Templates, 3 LOC Bootstrap, CHANGELOG/MEMORY. Total ~250 LOC. **Kleinster Adapter-Release im v0.17.x-Zyklus.** |
| **Schema-Vertrag-Status** | Sektion 6 ist verbindlich. ALLOWED_TYPES enthaelt `'generic'`, ALLOWED_SERVICES enthaelt `'tc'`. Keine Foundation-Aenderung noetig. |
| **Trust-Decision `echo $tc_html`** | **UNVERAENDERT.** Spec-Sektion 5.3 + T11 garantieren bytewise-Diff = 0 auf der `echo`-Zeile. Sicherheits-Premise v0.13.0/v0.14.4 gilt weiter |

**Risiko-Gegenmittel-Map:**

- R1: T11 (bytewise-Diff der `echo`-Zeile) + Spec-Sektion 5.3 explizit
- R2: T12 (Browser-Smoke Inline-JS Akkordeon) + Adapter ist `(string)`-Cast ohne weitere Aktionen
- R3: Schema-Vertrag Sektion 6.6 + F2 Empty-State-Smoke

**Konventionen:**

- Klassen-Name: `DHPS_TC_Adapter` (mit Underscore!), Datei
  `class-dhps-tc-adapter.php` (Autoloader-Konvention).
- ASCII-safe (keine Umlaute im Code).
- IDE-Diagnostics zu fehlenden WP-Funktionen sind irrelevant.
- Markdown-Linter-Warnings ignorieren.
- Schema-Vertrag-Vorgehen 13x in Folge ohne Critical-Drift - v0.17.4 =
  Iteration 14. Disziplin halten auch bei kleinem Scope.

**Bilanz nach v0.17.4:**

- 5 Adapter-Klassen + 1 DTO-Foundation
- 9 Service-Tags (MAES, MMB, MIL, TP, TPT, LP, MIO, LXMIO, TC)
- 17 Templates auf Collection-Pfad
- 14+ Trust-Decisions kumulativ
- 0 BC-Brueche im gesamten v0.17.x-Zyklus
- Schema-Vertrag-Vorgehen methodisch bewaehrt

**Ende Discovery v0.17.4.**
