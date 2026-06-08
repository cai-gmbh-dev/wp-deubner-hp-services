# 33 - Legacy-Pfad-Entfernung - Plan v0.18.0

**Status:** Discovery (2026-06-08)
**Aktuelle Plugin-Version:** v0.17.5
**Ziel-Version:** **v0.18.0** (MAJOR-Bump, bewusste BC-Brueche zugelassen)
**Architekt-Auftrag:** Nach 9/9 Adapter-Migration die Legacy-`else`-Branches aus
allen migrierten Templates entfernen, sodass die Pipeline-Collection die
**einzige** Datenquelle ist. Plus offene v0.17.x-Tech-Debt-Tickets fuer
tp/compact.php und Datum-Normalisierung.

## Executive Summary

Empfehlung in einem Satz: **Templates-Entfernung in v0.18.0 JA + tp/compact.php
JA + Datum-Normalisierung NEIN (auf v0.18.1 schieben)**, Pipeline-Garantie ueber
**Strategie 3.B (Defensive Empty-State im Template via shared Helper)**.

### Begruendung (3 Saetze)

- Templates-Cleanup ist mechanisch (~21 Templates, ca. -600 LOC nach Loesung
  der Pipeline-Garantie), aber MAJOR-Sprung wegen bewusster BC-Brueche bei
  Theme-Overrides und Plugin-Hooks die heute auf `$data` lesen.
- tp/compact.php gehoert mit rein, weil sie das letzte unmigrierte Standard-
  Template ist - ohne sie waere "Pipeline ist einzige Datenquelle" eine Luege.
- Datum-Normalisierung (TD-V0172-2 + TD-V0173-2) ist eigene Achse (DTO-Erweiterung
  + Item-Schema-Aenderung), gehoert NICHT in einen Cleanup-Release - Risiko
  beider Achsen zusammen ist groesser als die Summe.

### Geschaetzter Aufwand

- **v0.18.0 (Cleanup-only):** **M (mittel)** - 1.5 Mann-Tage netto, 21 Templates
  + 1 Helper + Pipeline-Patch + Tests + Doku. Pure Lead-Direct moeglich, alternativ
  1 F1-Specialist fuer die Template-Welle.
- **v0.18.1 (Datum-Normalisierung):** L-Schaetzung verschoben (eigene Discovery).

---

## Sektion 1: Template-Inventar

Alle 21 Templates mit aktuellem Status, geordnet nach Service.

### 1.1 MAES (3 Templates) - v0.17.0

| Template | Pattern-Status | else-Branch | Helper genutzt | Render-Code unter Block |
|----------|---------------|-------------|----------------|-------------------------|
| `maes/videos.php` | $has_collection | **eigen-Logik** (zweimal Items bauen) | keiner | NICHT bytewise - die ContentList-Item-Konstruktion ist im if **UND** im else dupliziert |
| `maes/merkblaetter.php` | $has_collection | **eigen-Logik** | keiner | NICHT bytewise - mb_items im if + else dupliziert |
| `maes/aktuelles.php` | $has_collection | **eigen-Logik** | keiner | NICHT bytewise - news_items im if + else dupliziert |

**Quirk:** MAES-Templates haben **KEIN Pseudo-Rebuild** (Legacy-Form rebauen),
sondern bauen `$items[]` zweimal vom Anfang an. Bei Removal des else verschwinden
die Legacy-Helpers (`$videos`, `$merkblaetter`, `$news` als Eingabe). Risiko:
Sub-Shortcode-Handler in `class-dhps-maes-modules.php` filtern aktuell
`$videos = filter($videos, ...)` im Force-Legacy-Pfad - wenn Collection da ist,
wird das Filtern komplett umgangen. Das ist aber by-design (`get_collection()`
returnt null bei Filter-Atts, Templates rendern dann den `else`-Pfad).

### 1.2 MMB (3 Templates) - v0.17.1

| Template | Pattern-Status | else-Branch | Helper genutzt | Render-Code unter Block |
|----------|---------------|-------------|----------------|-------------------------|
| `mmb/default.php` | $has_collection mit Pseudo-Rebuild | **rebuild $categories aus $data** | inline-Rebuild | **bytewise unveraendert** (Lazy-Akkordeon-Markup) |
| `mmb/card.php` | $has_collection mit Pseudo-Rebuild | analog | inline-Rebuild | **bytewise unveraendert** |
| `mmb/compact.php` | $has_collection mit Pseudo-Rebuild | analog | inline-Rebuild | **bytewise unveraendert** |

**Quirk:** Inline-Pseudo-Rebuild von ca. 35 LOC ist in allen 3 Templates
**dupliziert**. Bei v0.18.0-Cleanup: a) entweder Helper extrahieren
(`dhps_mmb_collection_to_legacy_categories`), b) oder Templates lesen direkt
Collection statt Pseudo-Rebuild. Empfehlung in Sektion 2.

### 1.3 MIO (3 Templates) - v0.17.3

| Template | Pattern-Status | else-Branch | Helper genutzt | Render-Code unter Block |
|----------|---------------|-------------|----------------|-------------------------|
| `mio/default.php` | $has_collection mit Pseudo-Rebuild | rebuild $tax_dates | `dhps_mio_item_to_legacy_month()` | **bytewise unveraendert** |
| `mio/card.php` | analog | analog | analog | **bytewise unveraendert** |
| `mio/compact.php` | analog | analog | analog | **bytewise unveraendert** |

**Quirk:** ajax_params + search_config kommen aus Collection-Meta - falls
Collection null, brechen die in den Render-Code eingebetteten News-Container-
data-Attribute. Heute aus `else`-Branch via `$data['ajax_params'] ?? array()`.

### 1.4 TP (2 von 3 Templates) - v0.17.2 + Tech-Debt-Special

| Template | Pattern-Status | else-Branch | Helper genutzt | Render-Code unter Block |
|----------|---------------|-------------|----------------|-------------------------|
| `tp/default.php` | $has_collection mit Pseudo-Rebuild | rebuild $featured + $categories | `dhps_tp_item_to_legacy_video()` | **bytewise unveraendert** |
| `tp/card.php` | analog | analog | analog | **bytewise unveraendert** |
| **`tp/compact.php`** | **KEIN $has_collection!** | direkt `$data['categories']` | keiner | Tech-Debt TD-V0172-1 |

**Spezialfall tp/compact.php:** Liest direkt `$categories = $data['categories'] ?? array();`
ohne Collection-Pfad. Wenn v0.18.0 die Pipeline-Garantie liefert (`$data` bleibt
gefuellt), kann tp/compact einfach so bleiben. **ABER:** das widerspricht der
Mission "Pipeline ist einzige Datenquelle". Detailliert in Sektion 4 + Frage 1.

### 1.5 TPT (3 Templates) - v0.17.2

| Template | Pattern-Status | else-Branch | Helper genutzt | Render-Code unter Block |
|----------|---------------|-------------|----------------|-------------------------|
| `tpt/default.php` | $has_collection mit Pseudo-Rebuild | rebuild $video + $tpt_config | `dhps_tp_item_to_legacy_video()` | **bytewise unveraendert** |
| `tpt/card.php` | analog | analog | analog | **bytewise unveraendert** |
| `tpt/compact.php` | analog | analog | analog | **bytewise unveraendert** |

**Quirk:** `$tpt_config` kommt aus Collection-Meta. Falls Collection null, faellt
heute `else` auf `$data['tpt_config']` (DHPS_TPT_Modules-Layer).

### 1.6 TC (3 Templates) - v0.17.4

| Template | Pattern-Status | else-Branch | Helper genutzt | Render-Code unter Block |
|----------|---------------|-------------|----------------|-------------------------|
| `tc/default.php` | $has_collection mit Meta-Lesen | `$tc_html = $data['html']` | keiner | **bytewise unveraendert (echo $tc_html unangetastet)** |
| `tc/card.php` | analog | analog | keiner | **bytewise unveraendert** |
| `tc/compact.php` | analog | analog | keiner | **bytewise unveraendert** |

**Quirk:** echo $tc_html Trust-Decision (v0.13.0/v0.14.4) bleibt unangetastet -
auch nach v0.18.0. Adapter ist Passthrough, Collection-Meta haelt das HTML.

### 1.7 Steuertermine (4 Templates) - v0.17.5

| Template | Pattern-Status | else-Branch | Helper genutzt | Render-Code unter Block |
|----------|---------------|-------------|----------------|-------------------------|
| `steuertermine/default.php` | $has_collection **OHNE else** | (nichts - $data bleibt erhalten) | `dhps_mio_item_to_legacy_month()` | bytewise unveraendert |
| `steuertermine/card.php` | analog | (nichts) | analog | bytewise unveraendert |
| `steuertermine/compact.php` | analog | (nichts) | analog | bytewise unveraendert |
| `steuertermine/inline.php` | analog | (nichts) | analog | bytewise unveraendert |

**Wichtiger Befund:** Steuertermine-Templates haben **keinen `else`-Branch** -
sie ueberschreiben `$data` nur bei vorhandener Collection und behalten sonst
das via `render_template($tax_dates, ...)` durchgereichte Array. Diese 4
Templates **sind nicht Teil des v0.18.0-Cleanups** im engen Sinn - es gibt
keinen Legacy-Branch zu entfernen.

**Konsequenz fuer v0.18.0-Scope: 17 Templates, NICHT 21.**

---

## Sektion 2: Optionen pro Template - was passiert wenn else weg?

### 2.1 MAES (3 Templates) - Risiko: NIEDRIG-MITTEL

**Was passiert wenn else weg?**

- Sub-Shortcodes (Force-Legacy bei einzelvideo/videoliste) brechen, weil die
  Filter-Atts NICHT auf der Collection wirken, sondern nur auf `$videos`-Array
  im Legacy-`else`-Branch des MAES-Modules-Handlers.
- Theme-Overrides die direkt auf `$videos`/`$merkblaetter`/`$news` lesen,
  rendern leer.

**Optionen fuer Cleanup:**

- **A)** Templates lesen NUR noch Collection, Sub-Shortcode-Handler muss
  Filter direkt im Adapter-Aufruf nachbauen (Collection-Filter via
  `$collection->filter(...)` mit Index-Tracking). **Erfordert TD-V0171-1
  (Sub-Shortcode-Adapter-Atts).** Aufwand: HOCH.
- **B)** Templates lesen Collection, Sub-Shortcode-Handler bei Filter-Atts
  baut die Collection **selbst** (statt `dhps_build_collection_for`) durch
  Pre-Filtering der Items. Sub-Shortcode-Handler kennt das Adapter-Klassen-
  Output-Format. Aufwand: MITTEL.
- **C)** Templates lesen Collection, Force-Legacy fliegt raus, Sub-Shortcodes
  unterstuetzen Filter-Atts nicht mehr - **BC-Bruch**, in MAJOR-Bump
  vertretbar wenn dokumentiert. Aufwand: NIEDRIG.

**Empfehlung Sektion 2.1: Option B**. Begruendet in Sektion 4.

### 2.2 MMB (3 Templates) - Risiko: NIEDRIG

**Was passiert wenn else weg?**

- MMB-Adapter ist bereits service-agnostisch, MIL teilt sich die Instanz.
- Pseudo-Rebuild bleibt im Template (ca. 35 LOC pro Template, 3x dupliziert).
- Lazy-Akkordeon-Markup unveraendert.

**Optionen fuer Cleanup:**

- **A)** Inline-Pseudo-Rebuild belassen, nur else entfernen. Aufwand: NIEDRIG.
  Code-Duplikation bleibt (Tech-Debt).
- **B)** Pseudo-Rebuild in Helper extrahieren (`dhps_mmb_collection_to_legacy_categories()`).
  Reduziert 3x35 LOC -> 3x5 LOC. Aufwand: NIEDRIG-MITTEL.
- **C)** Templates direkt auf Collection umstellen (foreach $collection statt
  rebuilt $categories), Lazy-Akkordeon-Markup re-strukturieren. Aufwand: HOCH,
  AJAX-Handler-Refactor-Risiko.

**Empfehlung Sektion 2.2: Option B** (Helper extrahieren). Bringt Code-Reduktion
in den Cleanup-Release, ohne den Render-Code zu beruehren.

### 2.3 MIO (3 Templates) - Risiko: NIEDRIG

**Was passiert wenn else weg?**

- MIO-Adapter liefert `ajax_params`/`search_config`/`tax_dates` via Items+Meta.
- Helper `dhps_mio_item_to_legacy_month()` existiert.

**Optionen:** A) inline lassen, B) eigenen Wrapper-Helper bauen analog MMB.

**Empfehlung Sektion 2.3: Option A** (inline lassen, weil Helper schon
existiert und der Pseudo-Rebuild ist ein 6-Zeilen-foreach). Keine LOC-Ersparnis
durch weiteren Helper.

### 2.4 TP (2 von 3 Templates) - Risiko: NIEDRIG

Analog MIO. Helper `dhps_tp_item_to_legacy_video()` existiert. Pseudo-Rebuild ist
~15 LOC pro Template, 2x dupliziert. Inline lassen ist OK.

### 2.5 TPT (3 Templates) - Risiko: NIEDRIG

Analog. Pseudo-Rebuild ist ~10 LOC pro Template, 3x dupliziert. Inline lassen.

### 2.6 TC (3 Templates) - Risiko: 0

Trivial - nur 2 Variable aus Meta lesen. Inline lassen.

---

## Sektion 3: Pipeline-Garantie-Strategie

Kern-Frage: **Wenn `else`-Branch raus ist, wie garantieren wir dass die
Templates NIE ein null-Collection erleben?**

### 3.1 Aktueller Stand (Risiko-Analyse)

Pipeline ruft Adapter auf, wenn registriert. Adapter-Aufruf ist in try/catch
gewrapped (SEC-MEDIUM-1 v0.17.0). Bei Throw -> $collection = null.

**Heutige Fail-Modi die zu `$collection = null` fuehren:**

1. Adapter wirft Throwable (`adapt()`-Implementierung-Bug, Parser-Output-Drift)
2. Adapter-Filter `dhps_content_adapter_for_service` returnt non-Interface
3. Kein Adapter registriert fuer den Service-Tag
4. `class_exists('DHPS_Content_Adapter_Registry')` ist false (Bootstrap-Race)

Heute fangen Templates das durch den else-Branch ab. Nach v0.18.0: FEHLER (PHP-
Notice + leere Items + ggf. Fatal in den Pseudo-Rebuild-Stellen die `(array)
$collection->get_meta(...)` machen).

### 3.2 Optionen

#### 3.A) Im Adapter Throw verbieten + Pipeline garantiert Collection

**Implementierung:**

- Adapter-Interface bekommt Promise im Doc-Block: "darf NICHT throwen, MUSS
  Collection liefern (auch wenn leer)".
- Pipeline-`try/catch` bleibt als Defense-in-Depth, aber im catch wird eine
  **leere Default-Collection** statt null gesetzt.
- Filter-Drift wird in der Registry abgefangen (heute schon SEC-MEDIUM-2).
- Pipeline garantiert: **Templates sehen IMMER eine Collection** (notfalls leer).

**Vorteil:** Templates muessen keine Defensive haben. Schlank.

**Nachteil:** Wenn Adapter throwt UND wir geben leere Collection -> Templates
rendern Empty-State - User sieht "keine Daten" obwohl der Service eigentlich
Daten haben sollte. Diagnose-Logging muss laut sein.

**Aufwand:** S - Pipeline-Patch ~10 LOC, Doku-Update.

#### 3.B) Templates haben Defensive-Fallback via shared Helper

**Implementierung:**

- Neue globale Funktion `dhps_collection_or_empty( ?DHPS_Content_Collection $c, string $service ): DHPS_Content_Collection`.
- Im Template oben: `$collection = dhps_collection_or_empty( $collection ?? null, $service_tag );`
- Render-Code lesen auf garantierter Collection.

**Vorteil:** Symmetrisch. Templates haben EINE Zeile Defensive (statt 15+
Zeilen else-Branch). Helper kann zentrale Diagnose machen (error_log "Template
X ohne Collection aufgerufen").

**Nachteil:** 1 Zeile Boilerplate pro Template. Aber: das passt auch zu Theme-
Overrides - der Override hat den Helper-Aufruf auch.

**Aufwand:** S - 1 Helper-Funktion + 17 Template-Lines-Patch.

#### 3.C) Pipeline hat Strict-Mode der bei Adapter-Drift fatal wirft

**Implementierung:**

- WP-Option `dhps_strict_adapter_mode` (default off).
- Strict-on: Throwable im Adapter-Aufruf wird re-thrown statt swallowed.
- Strict-off: heutiger Fail-Soft.

**Vorteil:** Diagnose-Hilfe in Dev/Stage.

**Nachteil:** Loest die Production-Frage nicht. Fail-Soft bleibt der Default,
also brauchen Templates trotzdem entweder 3.A oder 3.B.

**Aufwand:** S - Pipeline-Patch + Admin-Toggle.

### 3.3 Empfehlung

**3.B (Defensive Helper) als Pflicht + 3.A (Pipeline-Garantie) als Belt-and-
Braces.**

Begruendung:

- **3.B** allein reicht funktional. Templates haben 1 Zeile Defense, Theme-
  Overrides erben das wenn sie das aktuelle Plugin-Template kopieren.
- **3.A** zusaetzlich macht den Helper-Aufruf zu einer No-Op - sicher gegen
  Render-Bugs in der Pipeline. Defense-in-Depth-Prinzip aus v0.17.0 SEC.
- **3.C** ist nice-to-have, gehoert nicht in v0.18.0 - Diagnose-Werkzeug fuer
  spaeter.

**Spec-Auspraegung:**

```php
// includes/dhps-content-helpers.php (NEU in v0.18.0):
function dhps_collection_or_empty(
    ?DHPS_Content_Collection $collection,
    string $service_tag
): DHPS_Content_Collection {
    if ( $collection instanceof DHPS_Content_Collection ) {
        return $collection;
    }
    if ( function_exists( 'error_log' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf(
            'DHPS: Template fuer Service "%s" wurde ohne Collection aufgerufen - faelle auf leere Collection zurueck.',
            $service_tag
        ) );
    }
    return new DHPS_Content_Collection( $service_tag, array() );
}
```

```php
// Pipeline-Patch (3.A Belt-and-Braces):
// im catch: $collection = new DHPS_Content_Collection( $tag, array() ); statt null.
// Falls Registry kein Adapter findet: ebenfalls leere Collection statt null
// (NUR wenn alle 9 Services migriert - heute der Fall).
```

---

## Sektion 4: Scope-Empfehlung v0.18.0

### 4.1 Was rein kommt (Empfehlung)

| Item | Status | Begruendung |
|------|--------|-------------|
| 17 Templates Legacy-else raus (3 MAES + 3 MMB + 3 MIO + 2 TP + 3 TPT + 3 TC) | **IN** | Mission-Kern |
| Steuertermine 4 Templates | **OUT** (technisch schon clean) | kein else-Branch zu entfernen |
| tp/compact.php Migration | **IN** | siehe 4.2 unten |
| Pipeline-Garantie 3.B + 3.A | **IN** | Pflicht fuer den else-Removal |
| Helper `dhps_collection_or_empty()` | **IN** | Pflicht fuer 3.B |
| Helper `dhps_mmb_collection_to_legacy_categories()` | **IN** | Code-Reduktion 3x35 -> 3x5 LOC |
| MAES Sub-Shortcode-Filter-Migration | **IN** (Option B aus 2.1) | Pflicht weil sonst Force-Legacy-Fallback fehlt |
| TD-V0172-2 Datum-Normalisierung TP | **OUT** | siehe 4.3 unten |
| TD-V0173-2 Datum-Normalisierung MIO | **OUT** | siehe 4.3 unten |
| TD-V0174-1 News-Container AJAX | **OUT** | nicht-template-bezogen, eigener Release |
| Doku: BC-Impact-Liste fuer Theme/Plugin-Entwickler | **IN** | MAJOR-Bump-Pflicht |
| MIGRATION.md `v0.17 -> v0.18` | **IN** | MAJOR-Bump-Pflicht |

### 4.2 tp/compact.php - Empfehlung A (mit rein)

**3 Optionen aus Briefing:**

- A) tp/compact migrieren als Teil von v0.18.0 (gross + JS-Risiko) - **EMPFOHLEN**
- B) tp/compact ausnehmen - bleibt Legacy-Special-Case, dokumentiert
- C) tp/compact in v0.18.1 separater Release

**Begruendung A:**

- Wenn v0.18.0 "Pipeline ist einzige Datenquelle" auf die Fahne schreibt, muss
  tp/compact mit. Sonst kollidiert die Mission mit der Realitaet.
- JS-Refactor-Risiko (initCompactAccordion) ist nicht gross fuer reines
  Pseudo-Rebuild-Pattern - der Markup-Output ist bytewise unveraendert,
  initCompactAccordion findet die gleichen Selektoren.
- Pseudo-Rebuild ist analog tp/default + tp/card (Helper `dhps_tp_item_to_legacy_video`
  existiert). Aufwand: ~25 LOC im Template, kein JS-Anpacken.
- Stage-Smoke pflicht - tp/compact in einer Page mit `[tp layout=compact]` testen.

**Risiko-Hedge:** Wenn der Pseudo-Rebuild T1-T5 Tests bricht (Stage), wird
tp/compact rausgenommen und v0.18.0 dokumentiert es als bekannte Tech-Debt.
Pre-Release-Smoke ist Pflicht.

### 4.3 Datum-Normalisierung TD-V0172-2 + TD-V0173-2 - Empfehlung B (raus)

**Optionen aus Briefing:**

- A) Ja, alle drei zusammen in v0.18.0
- B) Nein, separater Release v0.18.1 fuer Datum - **EMPFOHLEN**
- C) Datum komplett auf v0.19.0 schieben

**Begruendung B:**

- Datum-Normalisierung beruehrt das **DTO-Schema** (`DHPS_Content_Item::$date`
  ist heute ein optionales Feld - bei Datum-Normalisierung wird es zur
  Pflicht-Quelle und der MM/YY-String wandert raus aus `$meta['datum']`).
- Das ist eine **Schema-Migration**, kein Cleanup. Zwei DTO-Versionen
  parallel zu fahren (alte Templates lesen meta, neue lesen $date) blaeht
  jeden Adapter auf.
- Ein eigener Release v0.18.1 hat Platz fuer eine eigene Discovery (Helper
  `dhps_normalize_date_to_iso`, Test-Suite, Fallback-Logik).
- v0.18.0 ist BC-Bruch fuer Templates - eine weitere Achse (DTO-Schema) macht
  Stage-Smoke unzumutbar.

**Risiko-Hedge:** Wenn beide Tickets schnell sind (S), koennen sie spaeter doch
in v0.18.0 mit rein. Aber: separate Discovery fuer Datum ist Pflicht, dann
entscheidet die naechste Iteration.

### 4.4 Aufwand-Aufstellung

| Item | Aufwand |
|------|---------|
| 17 Templates else-Branch raus | S (ca. -350 LOC, ~30 min pro Template Test) |
| Helper `dhps_collection_or_empty` | XS (~25 LOC + Tests) |
| Helper `dhps_mmb_collection_to_legacy_categories` | S (~40 LOC + Tests + 3 Templates patchen) |
| MAES Sub-Shortcode-Filter-Migration | M (~80 LOC im Handler, Filter im Adapter-Aufruf) |
| Pipeline-Patch 3.A | XS (~10 LOC) |
| tp/compact.php migrieren | S (~30 LOC im Template + Smoke-Test) |
| Doku BC-Impact-Liste + MIGRATION.md | S |
| Stage-Smoke 9 Services x 3 Layouts | M (~2h Test-Walk) |
| **Gesamt v0.18.0** | **M (mittel)**, 1.5 MT netto |

---

## Sektion 5: Schema-Vertrag (Mindest-Pipeline-Vertrag)

Wenn Templates nur noch Collection lesen, ist der Mindest-Vertrag der Pipeline
ans Template:

### 5.1 Pflicht-Variablen im Template-Scope

| Variable | Typ | Pflicht | Quelle |
|----------|-----|---------|--------|
| `$collection` | `DHPS_Content_Collection` (NIE null nach Helper) | **JA** | Pipeline-Adapter + dhps_collection_or_empty |
| `$service_class` | `string` | **JA** | Renderer (`dhps-service--{tag}`) |
| `$layout_class` | `string` | **JA** | Renderer (`dhps-layout--{layout}`) |
| `$custom_class` | `string` (mit fuehrendem Leerzeichen oder leer) | **JA** | Renderer |
| `$service_tag` | `string` | empfohlen | aus `$collection->service` ableitbar |

### 5.2 Kann-Variablen (Tech-Debt offen)

| Variable | Status v0.18.0 |
|----------|----------------|
| `$data` | **DEPRECATED** (bleibt aber als Param gefuellt fuer BC mit Theme-Overrides die noch nicht migriert sind, siehe 5.4) |

### 5.3 Collection-Schema-Vertrag

Pro Service muss der Adapter mindestens diese Meta-Keys liefern (sonst brechen
Templates):

| Service | Pflicht-Meta | Pflicht-Items |
|---------|--------------|---------------|
| maes | overview, total_videos, total_merkblaetter, total_news | 0..N items mit type in {video, document, news} |
| mmb/mil | categories_order, categories_meta, search_config | 0..N items mit type=document, category=category_id |
| tp/lp | featured_video_id (oder null), categories_order, categories_meta, video_service | 0..N items mit type=video, category=cat_idx |
| tpt | tpt_config (ueberschrift+teasertext), video_service | 0..1 items mit type=video |
| mio/lxmio | search_config, ajax_params, months_order | 0..N items mit type=tax_date, meta.entries, meta.footnote |
| tc | html (string), is_empty (bool) | 0..1 items mit type=generic |

**Discovery-Pflicht: Diesen Vertrag in Sektion 9 der Spec verankern und in F1
beim Schema-Drift-Test bestaetigen lassen. Schema-Vertrag-Vorgehen Iteration 15.**

### 5.4 Frage 4 Antwort: `$data`-Parameter

Aktuell reicht Pipeline `$data` **UND** `$collection` an Templates. Frage: nach
v0.18.0 noch noetig?

**Antwort: JA, BLEIBT.** Begruendung:

- Tech-Debt-Risiko: Theme-Overrides die heute `$data['service_tag']` oder
  andere Felder lesen, brechen sofort wenn `$data` wegfaellt. MAJOR-Bump ist
  zwar BC-Bruch-zugelassen, aber freiwillig $data als "Diagnose-Variable"
  beibehalten ist billig (0 LOC-Aenderung in der Renderer-Signatur).
- Filter `dhps_pipeline_data_{tag}` braucht $data weiterhin als Eingabe.
- Pipeline ruft Adapter mit `$parsed_data` (= $data) -> $data muss
  weiterhin durchgereicht werden.
- `$data` wird im Template-Scope **dokumentarisch** als "Legacy-Diagnose,
  bevorzugt $collection lesen" gekennzeichnet.

Kein Patch noetig. **`$data` bleibt im Template-Scope, wird aber nicht mehr
gelesen.** Doku-Update Pflicht.

### 5.5 Frage 5 Antwort: BC-Strategie

v0.18.0 = MAJOR-Bump = BC-Bruch zugelassen. Aber inkrementell:

| BC-Achse | Status | Doku |
|----------|--------|------|
| Theme-Overrides die `$data['categories']` lesen | **BRECHEN** wenn nicht aktualisiert | BC-Impact-Liste Eintrag 1 |
| Theme-Overrides die `$videos`/`$merkblaetter`/`$news` lesen (MAES) | **BRECHEN** | BC-Impact-Liste Eintrag 2 |
| Plugin-Hooks die `dhps_pipeline_data_{tag}` nutzen | **OK** (Filter bleibt) | n/a |
| API-Konsumenten | **OK** (kein externes API geaendert) | n/a |
| MMB-AJAX-Endpoint-Konsumenten | **OK** (Endpoint nicht angetastet) | n/a |
| Shortcode-Konsumenten ([mio]/[tp]/...) | **OK** (Shortcode-Vertrag stabil) | n/a |
| Sub-Shortcodes ([maes_videos] mit Filter-Atts) | **OK** sofern Option B aus 2.1 umgesetzt | n/a |

---

## Sektion 6: Acceptance T1-T20+

### F1 Unit-Tests (Helper)

- T1: `dhps_collection_or_empty(null, 'mio')` returnt leere Collection mit service='mio'.
- T2: `dhps_collection_or_empty($coll, 'mio')` returnt $coll unveraendert (spl_object_id-Check).
- T3: `dhps_collection_or_empty(null, 'mio')` mit WP_DEBUG=true loggt eine Zeile, sonst nicht.
- T4: `dhps_mmb_collection_to_legacy_categories($coll)` mit 2 Categories + 5 Items returnt
  Legacy-Shape mit `categories[]` und korrekten `fact_sheets[]`-Counts.
- T5: gleicher Helper mit leerer Collection returnt leeres Array `[]`.

### F1 Pipeline-Garantie-Tests

- T6: Pipeline mit registriertem MAES-Adapter rendert Templates mit Collection.
- T7: Pipeline mit Adapter der throwt - Templates rendern Empty-State, kein Fatal.
- T8: Pipeline ohne Adapter-Registry-Klasse (simulated) - Templates rendern Empty-State.
- T9: Filter `dhps_content_adapter_for_service` returnt Garbage - Templates rendern Empty-State,
  `_doing_it_wrong` wird ausgeloest.

### F1 Template-BC-Tests (per-Template-Smoke)

- T10: maes/videos.php mit Collection von 3 Videos rendert 3 ContentCard.
- T11: maes/merkblaetter.php mit Collection von 2 Documents rendert 2 ContentCard.
- T12: maes/aktuelles.php mit Collection von 1 News-Item rendert 1 collapsible Card.
- T13: mmb/default.php mit 2 Categories rendert 2 Lazy-Akkordeon-Headers.
- T14: mmb/card.php analog.
- T15: mmb/compact.php analog.
- T16: mio/default.php mit 6 Months rendert 6 dhps-tax-dates__column.
- T17: mio/card.php analog.
- T18: mio/compact.php analog.
- T19: tp/default.php mit Featured + 3 Categories rendert dhps-tp-featured + dhps-tp-catalog.
- T20: tp/card.php analog.
- T21: tp/compact.php (NEU MIGRIERT) mit 3 Categories rendert dhps-tp-compact-Sections.
- T22: tpt/default.php mit 1 Video rendert 1 ContentCard.
- T23: tpt/card.php analog.
- T24: tpt/compact.php analog.
- T25: tc/default.php mit HTML + is_empty=false rendert dhps-tc__container.
- T26: tc/card.php analog mit dhps-card-Wrapper.
- T27: tc/compact.php analog.

### Frontend-Smoke pro Service (Stage)

- T28: Page 6 (9 Hauptservices) - dhps-Klassen-Diff vor/nach v0.18.0 leer (oder
  dokumentiert in BC-Impact-Liste).
- T29: Page 7 (Sub-Shortcodes mit MAES + Filter-Atts) - 28 dhps-Klassen plus
  ContentCard-Klassen rendern korrekt.
- T30: Lazy-Akkordeon MMB - Klick auf Category-Header laedt Fact-Sheets.
- T31: MMB-Search-AJAX - Suche liefert Results-JSON (Side-Channel-Action-Hook
  feuert die Collection).
- T32: MIO-News-Container - data-Attribute fuer AJAX rendern korrekt.
- T33: TP-Player - Klick auf Featured-Video startet iframe-Player.
- T34: TPT-Modules-Filter - Custom-Ueberschrift kommt im rendered HTML an.
- T35: TC-Akkordeon - Klick auf Rechner expandiert/collapses (Inline-JS funktional).
- T36: Steuertermine (NICHT migriert in v0.18.0) - rendert unveraendert.

### Edge-Cases

- T37: Adapter throwt fuer mio -> Page 6 zeigt 8 Services + Empty-State fuer MIO.
- T38: Theme-Override fuer `mmb/default.php` das `$data['categories']` liest
  (Legacy-Theme-Pattern) - rendert leer mit Notice (dokumentiert in BC-Impact).
- T39: Page mit `[maes_videos einzelvideo=2]` - Force-Legacy greift, Template
  rendert 1 Video (das zweite).

**Pflicht-Pass: T1-T36 (kein FAIL). T37-T39 als Spec-Verifikation.**

---

## Sektion 7: Spec-Aufteilung

### Empfehlung: Lead-Direct mit 1 optionalem F1-Specialist

**Begruendung:**

- Mechanisch (17 Templates loeschen else-Branch ist 17x copy-paste-pattern).
- Pipeline-Garantie ist 1 Helper + 1 Pipeline-Patch.
- MAES Sub-Shortcode-Filter-Migration ist die einzige Stelle mit Erfindungs-
  Aufwand (Option B aus 2.1) - ~80 LOC.
- tp/compact.php ist 1 Template, analog tp/default + tp/card.
- Test-Suite 39 Tests ist umfangreich aber strikt mechanisch.

**Variante A: Lead-Direct (empfohlen wenn 2 Mann-Tage frei)**

- 1 Lead macht alles in einem Branch
- ca. 1.5 MT
- 1 Stage-Smoke
- 1 Pre-Release-rc.1

**Variante B: 1 F1-Specialist + Lead-Composition**

- F1: 17 Templates else-Removal + MAES Sub-Shortcode-Filter-Migration + Tests
- Lead: Pipeline-Patch + 2 Helpers + tp/compact + Doku + Composition
- Parallel moeglich ohne Coordination-Overhead (Lead-Patch + F1-Patch sind
  unabhaengig)
- ca. 1 MT mit Parallelisierung

**Architekt-Entscheidung empfohlen: Variante A** (Lead-Direct). Begruendung:
mechanisch, 1 Person hat Gesamt-Ueberblick, der Stage-Smoke 9 Services x 3
Layouts braucht den Gesamt-Kontext sowieso.

---

## Sektion 8: Risiken + Tech-Debt

### Top-3-Risiken

#### R1: Theme-Overrides mit Legacy-`$data`-Lesen brechen ohne Notice

- **Wahrscheinlichkeit:** MITTEL (keine Telemetrie wie viele User Theme-Overrides nutzen)
- **Impact:** HOCH (User sieht leere Seite, ggf. Fatal wenn `$data['categories']` als foreach genutzt)
- **Mitigation:**
  - BC-Impact-Liste in MIGRATION.md auflisten (Sektion 9 dieses Docs)
  - Pre-Release-Stage-Smoke mit absichtlichem alten Theme-Override-Pattern, Fail-Mode dokumentieren
  - Plugin-Header `Requires Plugin: 0.18.0` -> User sieht Update-Hinweis in WP-Admin
  - Stage-Branding-Marker (v0.16.3) hilft beim Test
  - Eventuell ein **Filter-Hook `dhps_legacy_data_present`** der Theme-Overrides erlaubt
    auf `$data` zu lesen (Tech-Debt-Ticket fuer v0.18.x).

#### R2: MAES Sub-Shortcode-Filter-Migration bricht Filter-Logik

- **Wahrscheinlichkeit:** MITTEL (Filter sind heute auf Array-Index, Collection-Items
  haben anderen Index)
- **Impact:** HOCH (User sieht falsches Video bei `[maes_videos einzelvideo=2]`)
- **Mitigation:**
  - Eigene Test-Suite T39 dedicated fuer Sub-Shortcode-Filter (3+ Filter-Atts-Kombinationen)
  - Option B (Sub-Shortcode-Handler baut Collection selbst mit Pre-Filter) ist
    transparent zur Test-Suite
  - Fallback-Option C bereit halten: Sub-Shortcodes verlieren Filter-Atts in
    v0.18.0, dokumentiert als BC-Bruch (akzeptabel im MAJOR-Bump)

#### R3: tp/compact.php Lazy-State-Bug

- **Wahrscheinlichkeit:** NIEDRIG-MITTEL (initCompactAccordion ist alter Code)
- **Impact:** MITTEL (Akkordeon-Toggle-Bug, kein Render-Bug)
- **Mitigation:**
  - tp/compact NICHT zwingend in v0.18.0 - rausnehmen ist Notfall-Plan
  - Pre-Release-Smoke mit `[tp layout=compact]` pflicht
  - Markup ist bytewise unveraendert (Pseudo-Rebuild) -> initCompactAccordion
    sollte unveraendert funktionieren
  - Test T21 dedicated

### Tech-Debt nach v0.18.0

| Ticket | Stand | Vorschlag |
|--------|-------|-----------|
| TD-V0172-2 Datum-Normalisierung TP | **OFFEN** | v0.18.1 eigene Discovery |
| TD-V0173-2 Datum-Normalisierung MIO | **OFFEN** | v0.18.1 mit TD-V0172-2 |
| TD-V0174-1 News-Container AJAX | **OFFEN** | v0.18.2 oder v0.19.0 |
| TD-V0171-2 MMB-AJAX-Lazy-Akkordeon | **OFFEN** (von v0.17.5 verschoben) | v0.18.2 oder v0.19.0 |
| **NEU TD-V0180-1: $data-Parameter abschaffen** | NEU | v0.19.0 (nach BC-Karenz-Zeit) |
| **NEU TD-V0180-2: dhps_legacy_data_present Filter** | NEU optional | v0.18.x falls Live-User-Feedback BC-Bruch zeigt |

---

## Sektion 9: BC-Impact-Liste (User-Doku)

### Fuer Theme-Entwickler

Wenn dein Theme eines der folgenden Templates ueberschreibt:

```
{theme}/dhps/services/maes/{videos,merkblaetter,aktuelles}.php
{theme}/dhps/services/mmb/{default,card,compact}.php
{theme}/dhps/services/mio/{default,card,compact}.php
{theme}/dhps/services/tp/{default,card,compact}.php
{theme}/dhps/services/tpt/{default,card,compact}.php
{theme}/dhps/services/tc/{default,card,compact}.php
```

**MUSST du nach v0.18.0:**

1. Den Plugin-Template-Code nochmal aus `wp-deubner-hp-services/public/views/services/{service}/{layout}.php`
   uebernehmen (oder den `$has_collection`-Block und else-Branch entfernen wie in v0.18.0).
2. Lese-Pfad von `$videos`/`$merkblaetter`/`$news`/`$data['categories']` etc.
   ersetzen durch `$collection` (siehe DTO-Doku).
3. Helper-Aufruf `dhps_collection_or_empty($collection ?? null, $service_tag)` am
   Template-Anfang einfuegen (Defense-in-Depth).
4. Test mit deinem Theme auf Stage.

**KANN-Migrations-Doku:**

- `dhps_tp_item_to_legacy_video($item)` weiterhin verfuegbar fuer Pseudo-Rebuild.
- `dhps_mio_item_to_legacy_month($item)` weiterhin verfuegbar.
- `dhps_mmb_collection_to_legacy_categories($collection)` neu in v0.18.0.

**Erkennungsmuster: was im Theme-Override BRICHT:**

```php
// VORHER (Theme-Override-Pattern aus Plugin v0.17.x):
$videos = $videos ?? array();
foreach ( $videos as $video ) { ... }

// NACHHER (Plugin v0.18.0):
$videos in scope nicht mehr garantiert - greift auf $collection zu.
```

### Fuer Plugin-Entwickler

WordPress-Filter die weiter funktionieren:

- `dhps_pipeline_data_{tag}` (filtert `$parsed_data` VOR Adapter)
- `dhps_content_adapter_for_service` (filtert Adapter-Instance pro Service)
- `dhps_template_fallbacks` (Service-Template-Fallback wie `lp -> tp`)
- `dhps_component_template_path` / `dhps_component_props`
- `dhps_mmb_default_prerender_first_category` + analoge MMB-Filter
- `dhps_tp_grid_columns` / `dhps_tp_lazy_*` / `dhps_tp_style` etc.
- `dhps_mio_grid_columns` / `dhps_mio_style`
- `dhps_mmb_search_collection` (Action-Hook aus v0.17.5)

Neue Hooks in v0.18.0:

- `dhps_collection_or_empty_fallback` (filter, optional) - erlaubt Plugins die
  Default-Empty-Collection durch eine custom Collection zu ersetzen (z.B. fuer
  "Service in Wartung"-Banner).

### Fuer Shortcode-Konsumenten

**Keine Aenderungen.** `[mio]`/`[mmb]`/`[tp]`/`[tpt]`/`[tc]`/`[maes]`/`[lp]`/`[lxmio]`/`[mil]`
und Sub-Shortcodes `[mio_termine]`/`[maes_*]` rendern weiterhin den gleichen HTML-
Output bytewise. **Pflicht-Stage-Smoke** bestaetigt das.

**Ausnahme - dokumentiert als bewusster BC-Bruch:**

- Wenn Sub-Shortcode-Option C aus 2.1 implementiert wird (Filter-Atts wirken
  nicht mehr), brechen `[maes_videos einzelvideo=N]` und `[maes_videos
  videoliste=...]` Filter. **Empfohlen Option B**, die Filter erhaelt.

### Fuer API-Konsumenten

**Keine Aenderungen.** REST-Endpoints `/dhps/v1/services/{service}/preview`
liefern unveraenderten Preview-HTML.

### Fuer GitHub-Updater-Channel-User

- `dhps_update_channel = stable` bekommt v0.18.0 nach Promote.
- `dhps_update_channel = beta` bekommt v0.18.0-rc.1 vorab.
- **Pflicht-Pre-Release** auf Stage testen mit Theme-Override-Beispielen.

---

## Sektion 10: Spec-Briefing-Material

### Spec-Auftraege

#### Lead-Direct-Spec (Variante A empfohlen)

**Mission:** Legacy-`else`-Branches aus 17 Templates entfernen, Pipeline-Garantie
ueber Helper + Pipeline-Patch herstellen, MAES Sub-Shortcode-Filter-Migration,
tp/compact.php migrieren, Doku BC-Impact + MIGRATION.md.

**Pflicht-Reihenfolge:**

1. **Phase 1 (Foundation):** `dhps_collection_or_empty` + Pipeline-Patch (3.A
   Belt-and-Braces). Tests T1-T9 PASS.
2. **Phase 2 (Helper):** `dhps_mmb_collection_to_legacy_categories` + MMB-3-Template-Patch.
   Tests T13-T15 PASS, MMB-Smoke OK.
3. **Phase 3 (MAES):** Sub-Shortcode-Filter-Migration im MAES-Modules-Handler
   (Option B aus 2.1). Templates Legacy-Pfad entfernen. Tests T10-T12 + T39 PASS.
4. **Phase 4 (MIO + TP + TPT + TC):** Templates Legacy-Pfad entfernen.
   Tests T16-T18, T19-T20, T22-T27 PASS.
5. **Phase 5 (tp/compact.php):** Migrieren (~25 LOC). Test T21 PASS.
6. **Phase 6 (Doku):** BC-Impact-Liste + MIGRATION.md + CHANGELOG.
7. **Phase 7 (Stage-Smoke):** Pre-Release-rc.1 -> Stage Tests T28-T36.

**Phase-7-Gate:** wenn rc.1 Stage-Smoke FAIL -> Phase 5 rollback (tp/compact
raus), neue rc.2.

#### F1-Specialist-Spec (Variante B optional)

Phase 2 + 3 + 4 parallel zur Lead-Phase 1 (Foundation). Lead macht Phase 5-7.

### Spec-Pflicht-Lektuere fuer Spec

1. Dieses Dokument (33-LEGACY-CLEANUP-PLAN-v0180.md)
2. `docs/architecture/26-EINHEITLICHES-DATENMODELL-PLAN-v0170.md` (DTO-Schema)
3. `docs/architecture/32-SUB-SHORTCODE-PATTERN.md` (Sub-Shortcode-Bridges)
4. CHANGELOG v0.17.0 - v0.17.5
5. `includes/class-dhps-content-pipeline.php` (Pipeline-Patch-Stelle)
6. `includes/class-dhps-renderer.php` (`render_parsed`-Signatur)
7. `includes/class-dhps-content-collection.php` (Konstruktor-Vertrag)
8. `includes/dhps-content-helpers.php` (`dhps_build_collection_for`-Pattern)

### Schema-Vertrag-Vorgehen Iteration 15

**Status:** 14x in Folge ohne Critical-Drift (v0.15.3 - v0.17.5).

**Sektion 5 dieses Docs ist verbindlicher Vertrag.** F1/Lead bestaetigt
**vor** Code-Aenderung, dass die 6-Service-Meta-Schemata in Sektion 5.3
**identisch** zu den heutigen Adapter-Outputs sind (Schema-Drift-Smoke). Wenn
NICHT identisch: Discovery-Update Pflicht **vor** Code-Aenderung.

### Test-Pflicht-Pass

T1-T36 (kein FAIL) als Release-Gate.

### Aufwand-Korridor

- Best-Case: 1 MT (Variante B parallel)
- Realistic-Case: 1.5 MT (Variante A)
- Worst-Case: 2 MT (Variante A + Stage-Smoke iteration)

### Acceptance-Gate fuer Release

1. T1-T36 PASS auf Stage.
2. Page 6 dhps-Klassen-Diff dokumentiert (LEER oder per BC-Impact erklart).
3. MIGRATION.md und BC-Impact-Liste in Doku.
4. CHANGELOG-v0180.md geschrieben.
5. MEMORY.md MILESTONE 24 + Implementation-Notes.
6. Pre-Release-rc.1 -> Stage 24h Soak.
7. Promote zu Stable.

---

## Anhang A: Aktueller Plugin-Status (Stand v0.17.5)

- **9/9 Adapter** aktiv (mio, lxmio, tp, tpt, lp, mmb, mil, maes, tc)
- **17 Templates** mit `$has_collection`-Pattern (3 MAES + 3 MMB + 3 MIO + 2 TP +
  3 TPT + 3 TC)
- **4 Steuertermine-Templates** mit `$has_collection`-Pattern OHNE else
  (sind bereits "clean"-Pattern, brauchen kein v0.18.0-Patch)
- **3 Helpers:** `dhps_build_collection_for`, `dhps_tp_item_to_legacy_video`,
  `dhps_mio_item_to_legacy_month`
- **2 Bridge-Klassen:** `DHPS_MAES_Modules::get_collection`, `DHPS_Steuertermine::get_collection`
- **PHP-Mindest:** 8.1 (readonly properties)

## Anhang B: Was NICHT in v0.18.0 gehoert

- Datum-Normalisierung (DTO-Schema-Migration, eigene Achse)
- MMB-AJAX-Lazy-Akkordeon auf Adapter (Backend-Refactor, eigene Achse)
- MMB-Search-AJAX hat schon Helper-Side-Channel (v0.17.5), kein Bedarf
- MIO-News-Container AJAX-Endpoint auf Adapter (Backend-Refactor)
- Voller Theme-Override-Migration-Wizard (CLI/Admin-Tool) - optional v0.18.x
- Schema-Drift-Detection-Tool in Admin-Dashboard - nice-to-have v0.19.0

## Anhang C: Antwort auf die wichtigste Frage

**Frage Architekt:** "Welcher Subset der 21 Templates + tp/compact + Datum-
Normalisierung gehoert wirklich in v0.18.0, und wie garantieren wir dass Adapter
NIE null liefert?"

**Antwort:**

- **17 Templates** else-Branch raus (NICHT 21 - Steuertermine sind clean).
- **tp/compact.php JA** (Mission "einzige Datenquelle" erfordert es, JS-Risiko hedged).
- **Datum-Normalisierung NEIN** (eigene Achse, eigene Discovery, eigener Release v0.18.1).
- **Pipeline-Garantie:** 3.B Defensive-Helper im Template + 3.A Belt-and-Braces
  in der Pipeline. Adapter darf nicht throwen (Convention), aber Templates
  ueberleben es trotzdem ueber leere Default-Collection.

**Schaetzung:** M (mittel), 1.5 MT netto, Variante A (Lead-Direct).
