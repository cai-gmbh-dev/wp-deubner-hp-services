# 29 - MIO-Adapter (+ LXMIO + Steuertermine-Sub-Shortcode) - Plan v0.17.3

**Status:** Discovery (2026-06-06)
**Aktuelle Plugin-Version:** v0.17.2
**Ziel-Version:** v0.17.3
**Architekt-Auftrag:** Vierter Adapter-Block der inkrementellen Datenmodell-
Migration. Nach MAES (v0.17.0), MMB/MIL (v0.17.1) und TP/TPT/LP (v0.17.2)
jetzt MIO + LXMIO. MIO ist der erste Service mit dem ALLOWED_TYPES-Sondertyp
`tax_date` (in v0.17.0 vorbehalten - hier loest er sich ein). Plus:
Standalone-Shortcode `[mio_termine]` lebt in einer EIGENEN Klasse
`DHPS_Steuertermine` mit EIGENEM Template-Pfad (`public/views/steuertermine/`).

**Discovery-Empfehlung vorab (Kurzfassung):**

- **tax_dates-Mapping:** **Option A** - **1 Item pro Monat** mit
  `type='tax_date'`, `meta['entries']` enthaelt die vollstaendige Sub-Struktur
  inkl. `taxes[]` pro Datum. Begruendung in Sektion 2.4 - Discovery v0.17.0
  hat `tax_date` als Item-Type **vorbehalten** und ALLOWED_TYPES bereits
  freigeschaltet, jetzt loest sich das ein.
- **Adapter-Strategie:** **EINE Adapter-Klasse `DHPS_MIO_Adapter`**, fuer
  `mio` + `lxmio` registriert (konsistent mit MMB+MIL aus v0.17.1 und
  TP+LP aus v0.17.2). LXMIO hat keine eigenen Templates - nutzt MIO-Templates
  via Filter `dhps_template_fallbacks` (`lxmio` -> `mio`, seit v0.9.0).
- **Sub-Shortcode `[mio_termine]`-Bridge:** **Force-Legacy in v0.17.3**
  (kein get_collection-Wrapper). Begruendung in Sektion 4 - der Shortcode
  lebt in einer eigenen Klasse mit eigenem Template-Pfad und eigenem
  L2-Cache-Key (identisch zu Pipeline-Cache). Eine Migration der
  `public/views/steuertermine/`-Templates auf $collection waere ein
  separater Refactor - **Tech-Debt-Ticket TD-V0173-1 fuer v0.17.x-Abschluss**.
- **Template-Migration:** **Alle 3 MIO-Templates** (default, card, compact)
  bekommen das Pseudo-Rebuild-Pattern fuer den Steuertermin-Block. Die
  News-Container-Bloecke und die Search-Form sind **AJAX-Mountpoints** /
  clientside-rendered und werden NICHT migriert (Adapter sieht keine
  News-Items, nur tax_dates + search_config + ajax_params).
- **Spec-Aufteilung:** **1 Specialist + Lead-Direct**. Begruendung in
  Sektion 8 - MIO ist konzeptionell anders genug (Tax-Dates), aber der
  Scope ist klein genug fuer EINE saubere Iteration. Helper-Bedarf ist
  klein (`dhps_mio_item_to_legacy_month`-Helper analog v0.17.2).
- **Schema-Vertrag:** Sektion 6 ist verbindlich (12x Schema-Vertrag-Vorgehen
  ohne Critical-Drift, v0.17.3 = Nummer 13).
- **Aufwand:** **S (klein)** - F1 ~150 LOC, Lead ~60 LOC (Helper +
  Bootstrap + Doku) = ca. 400 LOC total (mit Doku).

---

## Sektion 1: Ausgangslage MIO-Parser

### 1.1 Top-Level-Keys (aus DHPS_MIO_Parser::parse(), Z. 53-58)

```
array(
    'tax_dates'     => array(),     // Steuertermine als monats-sortierte Liste
    'search_config' => array(),     // Such-/Filter-Konfiguration
    'ajax_params'   => array(),     // News-Container AJAX-Parameter
    'service_tag'   => 'mio',       // wird Pipeline-uebersteuert auf Aufrufer-Tag
)
```

### 1.2 tax_dates-Schema (aus parse_tax_dates, Z. 84-134)

Maximal **2 Monatsspalten** (steuertermin1, steuertermin2), jeweils:

```
array(
    'title'    => 'Juli 2026',              // string, h4.ueb_steuertermine
    'entries'  => array(                    // Liste der Datums-Eintraege
        array(
            'date'  => '10.07.',            // string, td:first-child
            'taxes' => array(               // Liste der Steuerarten
                'Umsatzsteuer',
                'Lohnsteuer',
                // ...
            ),
        ),
        // ...
    ),
    'footnote' => 'Schonfrist ...',         // string, beitrag_steuertermine > p
)
```

### 1.3 search_config-Schema (aus parse_search_config, Z. 189-217)

```
array(
    'target_groups'      => array(          // Zielgruppen aus select#rubriken
        'Unternehmer',
        'Privatpersonen',
        // ...
    ),
    'search_placeholder' => 'Suchbegriff',  // input#suchbegriff[placeholder]
)
```

### 1.4 ajax_params-Schema (aus extract_ajax_params, Z. 232-260)

```
array(
    'fachgebiet'   => 'S',                  // string, aus showResult-Regex
    'variante'     => 'KATEGORIEN',         // string
    'anzahl'       => '10',                 // string (kein int!)
    'teasermodus'  => '0',                  // string (optional)
)
```

OTA-Nummer wird **bewusst NICHT** extrahiert (serverseitig injiziert).

### 1.5 News-Container: clientside, kein Adapter-Bedarf

Die News-Items (`groups + articles` aus DHPS_MIO_News_Parser) sind eine
**komplett separate AJAX-Response** (`/wp-admin/admin-ajax.php` mit Action
`dhps_news_request`). Der News-Parser wird in einem Background-Pfad
ausgefuehrt, der nichts mit dem MIO-Parser-Output zu tun hat. Templates
rendern nur den `<section.dhps-news data-dhps-news-container>`-Mountpoint
mit data-Attributen, der von `public/js/dhps-mio.js` (Live-Search +
Load-More) befuellt wird.

**Schlussfolgerung:** MIO-Adapter sieht **keine News-Items**. Die
Collection enthaelt ausschliesslich `tax_date`-Items + Service-Meta.

### 1.6 MIO-Templates (default, card, compact)

Alle 3 Templates rendern die gleichen 4 Bloecke:

1. **Steuertermine** als BEM-Tabellen (`dhps-tax-dates__*` in default+card,
   `dhps-compact-dates__*` in compact). Direkt aus `$tax_dates`-Array,
   verschachtelte foreach-Schleifen month -> entries -> taxes.
2. **Search-Form** ueber `include __DIR__ . '/partials/search-form.php'`
   mit `$search_config` + `$service_tag` als Scope-Variablen.
3. **News-Container** als AJAX-Mountpoint mit `data-ajax-url`,
   `data-fachgebiet`, `data-variante`, `data-anzahl`, `data-teasermodus`,
   `data-service-tag`, `data-nonce`.
4. **Skeleton-Slot** als Loading-State, hidden by default.

**Pro Template:**
- `default.php` Z. 37-110: Steuertermine in 2-Spalten-Grid, search-form,
  news-section mit Skeleton.
- `card.php` Z. 25-101: gleicher Aufbau in `<div.dhps-card>`-Wrapper.
- `compact.php` Z. 20-87: Steuertermine als Inline-Listen
  (`dhps-compact-dates__*` mit "·"-Separator), kompaktere News-Section.

### 1.7 [mio_termine]-Shortcode: separater Pfad

`includes/class-dhps-steuertermine.php` registriert `[mio_termine]` und
ruft den MIO-Parser DIREKT auf (nicht via Pipeline). Nutzt:
- Eigenen L2-Cache-Key (`dhps_p_md5(endpoint|params)`) - **identisch zur
  Pipeline-Cache-Generation** (Cache-Sharing intendiert, Hit-Effizienz).
- Eigene Template-Suchhierarchie: `public/views/steuertermine/{layout}.php`
  mit 4 Layouts (default/card/inline/compact) + Theme-Override unter
  `{theme}/dhps/steuertermine/{layout}.php`.
- Eigene Shortcode-Atts: `count`, `month` (current/next/all), `layout`,
  `class`, `cache`.
- Eigene Filter-Logik: Monats-Filter (`current`/`next`) + Entries-Begrenzung
  pro Monat (`count`).

**Wichtig:** Die `[mio_termine]`-Templates konsumieren `$data` =
`array<int, month_shape>` direkt (kein `$service_class`, kein
`$collection`). Sie sind voellig isoliert vom Pipeline-Render-Pfad.

---

## Sektion 2: Ziel-Datenmodell MIO

### 2.1 Drei Mapping-Optionen fuer tax_dates

**Option A: 1 Item pro Monat** mit `type='tax_date'`, `meta['entries']`.
- Pro: schoene Aggregation pro Monat, Template-Pseudo-Rebuild trivial
  (1 Item = 1 Monat-Spalte), 1-N Items pro Render (typisch 2).
- Pro: tax_date wurde in v0.17.0 ALLOWED_TYPES genau dafuer reserviert.
- Pro: Cardinalitaet stimmt mit der UI-Aggregation ueberein
  (Monatsspalten in default/card, Inline-Items in compact).
- Contra: Item-`title` ist Monatsname, Item-meta enthaelt komplexe
  Sub-Struktur. Aber: `meta` ist explizit als Fluchtweg gedacht
  (Trust-Decision TD-3).

**Option B: 1 Item pro Datum** (Tag-Detail) mit `meta['taxes']` + `meta['month']`.
- Pro: granularer Zugriff (`first()->date` = naechstes Datum).
- Contra: Items sind keine "Inhalts-Karten" - sie sind Atom-Datenpunkte.
- Contra: typisch 5-10 Items pro Monat = 10-20 Items pro Render.
  Aggregations-Logik wandert ins Template zurueck.
- Contra: Item-`title` waere "10.07." (ein Datum) - das ist semantisch
  schwach.

**Option C: 1 Item pro Steuerart** (deepest).
- Pro: feinst-granular.
- Contra: 100+ Items pro Render. Keine sinnvolle UI-Aggregation als
  flache Liste. Aggregation muss komplett rekursiv via `group_by()` +
  manuellen Reduktor passieren.
- Contra: Item-`title` = "Umsatzsteuer" - rein semantisch, aber das
  identisch wiederholte Datum wird nicht erfasst.

### 2.2 Empfehlung: **Option A** (1 Item pro Monat)

**Begruendung:**

1. **Schema-Vorbehalt loest sich ein:** Discovery v0.17.0 hat
   `type='tax_date'` explizit fuer MIO vorgesehen (Trust-Decision TD-14
   "VORBEHALTEN bis v0.17.3"). ALLOWED_TYPES enthaelt den Type bereits.
2. **Cardinalitaet passt zur UI:** Templates rendern pro Monat eine
   Spalte/Inline-Item. 1 Item = 1 UI-Block ist natuerlich.
3. **Pseudo-Rebuild trivial:** Adapter -> Items, Template iteriert ueber
   `$collection` (in Parser-Order!), rekonstruiert `$tax_dates` als
   array<int, month_shape>. Pseudo-Rebuild ist ~15 LOC pro Template.
4. **Konsistent mit MMB/TP/MAES:** Items haben sinnvolle Titel
   (Monatsname), Body bleibt leer (Tabellen-Struktur lebt in `meta`),
   Meta enthaelt Service-spezifische Sub-Daten - exakt das Pattern aus
   MMB-`pdf_params` und TP-`v_modus`.
5. **`to_content_card_props()` ist verlustbehaftet** (tax_date -> document
   gemappt, Sub-Struktur verloren), aber **das ist OK** - tax_dates werden
   NICHT als ContentCard gerendert (Tabellen-Layout). Templates greifen
   direkt auf `$item->title` + `$item->meta['entries']` zu, nicht ueber
   die ContentCard-Bridge.

**Lead-Direct-Entscheidung:** Option A wird die default, Lead darf bei
Bedarf B/C verschieben - aber das wuerde den ganzen Discovery-Plan auf
den Kopf stellen. **Empfehlung: A**, klar dokumentiert.

### 2.3 Mapping-Tabelle (Option A)

| Parser-Feld | DTO-Mapping | Begruendung |
|---|---|---|
| `tax_dates[$idx].title` | -> Item.`title` | Monatsname "Juli 2026" ist sinnvoller Anzeige-Titel |
| `tax_dates[$idx].entries` | -> Item.`meta['entries']` (volle Sub-Struktur inkl. `date` + `taxes[]`) | Aggregierte Sub-Daten, nicht atomar |
| `tax_dates[$idx].footnote` | -> Item.`meta['footnote']` | Fussnote als Sub-Daten |
| `tax_dates[$idx]` (Index) | -> Item.`meta['month_index']` + Item.`category=null` | Monats-Index zur Rekonstruktion in Parser-Order |
| `tax_dates[$idx]` (Index) | -> Item.`id` als `'mio-taxdate-{idx}'` (bzw. `lxmio-taxdate-{idx}`) | Eindeutiger Identifier mit Service-Prefix |
| (alle anderen Felder) | Item: `type='tax_date'`, body=`''`, excerpt=`null`, image=`null`, media=`null`, link=`null`, date=`null` (kein DateTimeImmutable - Monat hat keinen Tag), tags=`[]`, category=`null` | tax_date-Items haben nur die Aggregations-Meta |
| `search_config` | -> Collection-Meta `search_config` (1:1 Sub-Array) | Collection-Level-Konfig, kein Item-Level |
| `ajax_params` | -> Collection-Meta `ajax_params` (1:1 Sub-Array) | dito |
| `service_tag` | -> ignoriert (Pipeline ueberschreibt sowieso) | Standard-Verhalten alle Adapter |

### 2.4 Collection-Meta-Felder

```
array(
    'search_config'  => array(),                    // 1:1 aus Parser
    'ajax_params'    => array(),                    // 1:1 aus Parser
    'months_order'   => array(),                    // int[], z.B. [0, 1] - Parser-Order
    'total_months'   => 2,                          // int, Anzahl gemappter Monate
    'total_entries'  => 18,                         // int, Sum aller entries[] ueber alle Monate
)
```

**Hinweis zu `months_order`:** TP/MMB nutzen `categories_order` als
String-Liste. MIO-Months sind aber **numerisch indiziert** (0, 1), und
ein Item.category bleibt **null** (Monate sind keine "Kategorien"). Der
Order-Key `months_order` ist primaer fuer Pseudo-Rebuild-Templates da -
diese iterieren ueber `$collection` (in Parser-Order angefuegt) und
rekonstruieren `$tax_dates` als 0-indiziertes Array. **`months_order`
ist DEFENSIV** (falls Templates die Liste vor Render schon brauchen ohne
ueber Items iterieren zu wollen).

### 2.5 Wichtige Designentscheidungen

**Frage 1: Title-Pflicht und leerer Monat?**

`DHPS_Content_Item` erzwingt `title !== ''`. Was wenn ein Monat keinen
Title hat (Parser-Edge-Case: `h4.ueb_steuertermine` fehlt)?

**Empfehlung:** Adapter ueberspringt Monate ohne Title UND ohne Entries
(analog MAES/MMB "title-skip"). Falls Title fehlt aber Entries da sind:
Fallback-Title `'Monat ' . ($idx + 1)` (Numerierungs-String, immer
non-empty). Spec dokumentiert das.

**Frage 2: Tax_dates ohne Entries (leerer Monat)?**

Parser liefert nur Monate mit gueltigem Tabellen-Body. Theoretisch
moeglich: Title ist da, Entries leer. Mapping liefert weiter ein Item -
das Pseudo-Rebuild-Template rendert dann eine leere `dl` (nur Header +
ggf. Footnote). Das ist BC zum Legacy-Verhalten (Template Z. 58-69 hat
`if ( ! empty( $month['entries'] ) )`-Check).

**Frage 3: ContentCard-Bridge fuer tax_date-Items?**

`to_content_card_props()` mapped `tax_date` -> `document` (Z. 344
`elseif ( 'generic' === $card_type )`). Das ist eine **Verlust-Bridge**:
ContentCard kennt keine Tabellen, die Sub-Struktur (entries[]/taxes[])
wird **NICHT** in card-props uebernommen.

**Konsequenz:** Templates **duerfen NICHT** `dhps_component('content-list', ...)`
fuer tax_date-Items aufrufen - sie verlieren die Sub-Struktur. Stattdessen:
Pseudo-Rebuild zu `$tax_dates` und das **bestehende BEM-Markup**
(`.dhps-tax-dates__*`) wird unveraendert weiterverwendet.

**Frage 4: ajax_params + search_config im Item-meta ODER Collection-Meta?**

Klar **Collection-Meta**. Begruendung: beide sind **Collection-wide**
(eine Konfig pro Render-Aufruf, gilt fuer alle Items + den News-Container).
Im Item-meta waeren sie pro Item dupliziert.

---

## Sektion 3: LXMIO-Strategie (analog LP+TP aus v0.17.2)

### 3.1 LXMIO-Eigenheiten

- **LXMIO hat keinen eigenen Parser** - nutzt den `DHPS_MIO_Parser`
  (Z. 292: `DHPS_Parser_Registry::register( 'lxmio', $mio_parser )`).
- **LXMIO hat keine eigenen Templates** - nutzt MIO-Templates via
  `dhps_template_fallbacks`-Filter (Z. 333: `'lxmio' => 'mio'`).
- **Eigene Auth-Option:** `dhps_lxmio_ota` (Service-Registry Z. 148).
- **Eigenes Branding:** Recht-Blau via Wrapper-Token-Switch
  (`.dhps-service--lxmio` -> Recht-Blau, gleiches Muster wie LP in TP-Templates).
- **Service-Tag im Item:** `lxmio` (analog `lp` bei TP-Adapter).

### 3.2 Adapter-Strategie: EIN Adapter, ZWEI Registrierungen

```php
$mio_adapter = new DHPS_MIO_Adapter();
DHPS_Content_Adapter_Registry::register( 'mio', $mio_adapter );
DHPS_Content_Adapter_Registry::register( 'lxmio', $mio_adapter );
```

Adapter ist Service-agnostisch (Pattern aus MMB/MIL + TP/LP). Der
Service-Tag kommt vom Pipeline-Aufrufer (`$service`-Param), landet 1:1 in:
- `Item.service` (lxmio bei LXMIO, mio bei MIO)
- Item-ID-Prefix (`mio-taxdate-...` bzw. `lxmio-taxdate-...`)

LXMIO-Pipeline-Aufruf trifft den Adapter automatisch. Das Template ist
das MIO-Template (via Fallback) - das Pseudo-Rebuild ist
service-agnostisch (liest aus `$collection`, schreibt in `$tax_dates`).

### 3.3 Branding-CSS bleibt unbeeinflusst

Der Wrapper-Token-Switch `.dhps-service--lxmio` wird in den **Templates**
gesetzt (`$service_class = 'dhps-service--lxmio'` aus dem Pipeline-
Renderer, der `$tag` zu `service_class` mapped). Adapter beruehrt nichts
am Branding.

### 3.4 ALLOWED_SERVICES-Check

`DHPS_Content_Item::ALLOWED_SERVICES` enthaelt:
- `'mio'` (Z. 63) - greift
- `'lxmio'` (Z. 64) - greift

Keine Anpassung an der DTO-Foundation noetig.

---

## Sektion 4: Sub-Shortcode `[mio_termine]`-Bridge

### 4.1 Aktueller Pfad (Bestand)

`[mio_termine]` -> `DHPS_Steuertermine::render`:

1. Holt MIO-Service aus Registry.
2. Liest OTA-Option (`dhps_ota_mio`).
3. Cache-Lookup via `dhps_p_md5(endpoint|params)` - **identisch zur
   Pipeline-Cache-Generation**. Cache-Sharing intendiert (Hit-Effizienz).
4. Bei Miss: Parser-Aufruf direkt (`DHPS_Parser_Registry::get_parser('mio')`).
5. Filter-Logik: Monats-Filter (`current`/`next`/`all`) + Entries-Begrenzung
   (`count`).
6. Eigene Template-Suchhierarchie: `public/views/steuertermine/{layout}.php`.

**Atts:** `count`, `month`, `layout`, `class`, `cache`. Davon **mindestens**
`month != 'all'` und `count > 0` sind **Filter-Atts** (modifizieren
`$tax_dates` vor Render).

### 4.2 Optionen-Bewertung

**Option A: Force-Legacy ALWAYS** (kein get_collection-Wrapper).

- Pro: 0 Aenderung an DHPS_Steuertermine.
- Pro: 0 Aenderung an den 4 Steuertermine-Templates.
- Pro: keine Filter-Atts-Konsistenz-Sorge (Adapter sieht nichts).
- Contra: Die `[mio_termine]`-Templates bleiben Collection-blind. Aber:
  diese Templates sind **eigene** Templates (`public/views/steuertermine/`),
  nicht die MIO-Templates - sie sind nicht Teil der Pipeline-Migration.
- Contra: Live-Preview-Endpoint fuer `[mio_termine]` (in v0.15.4
  preview-faehig gemacht) gibt keine Collection - aber er gab auch nie
  eine (Preview rendert nur das Shortcode-Output via do_shortcode).

**Option B: get_collection-Wrapper analog v0.17.1 MAES_Modules**.

- Pro: Konsistenz mit MAES.
- Contra: erfordert auch eine Migration der Steuertermine-Templates auf
  `$has_collection`-Pattern. **2 weitere Templates** (default + card +
  compact + inline = 4 Templates) zusaetzlich migrieren.
- Contra: Filter-Atts `month` (current/next/all) und `count` (slice
  pro Monat) sind eindeutig Item-modifizierende Atts. Force-Legacy waere
  fast immer aktiv. Echter Vorteil minimal.

**Option C: Mini-Bridge ohne Template-Migration**.

- Helper-Methode `DHPS_Steuertermine::get_collection(?array $atts)` baut
  die Collection NUR WENN keine Filter-Atts aktiv sind, gibt sonst null.
- Aber: Templates sind nicht migriert - sie ignorieren `$collection`.
- Effektiv identisch zu Option A, nur mit toten Code-Pfaden.

### 4.3 Empfehlung: **Option A** (Force-Legacy in v0.17.3)

**Begruendung:**

1. **Klare Trennung der Verantwortlichkeiten:** `[mio_termine]` ist ein
   STANDALONE-Shortcode mit eigenem Subsystem (eigene Klasse, eigene
   Templates, eigene Atts, eigene Filter-Logik). Die MIO-Pipeline-
   Migration in v0.17.3 deckt den **Pipeline-Pfad** ab; der
   Standalone-Pfad ist eine separate Migration.
2. **Cache-Sharing bleibt erhalten:** Beide Pfade nutzen denselben L2-Cache-
   Key. Wenn die Pipeline gerade einen Cache-Hit hatte, hat
   `[mio_termine]` ihn auch.
3. **0 BC-Risiko:** keine Code-Aenderung in `DHPS_Steuertermine` und keine
   in den 4 Steuertermine-Templates.
4. **Tech-Debt explizit dokumentieren:** Ticket **TD-V0173-1**
   `[mio_termine]`-Templates auf Collection-Bridge bringen. Zielversion:
   v0.17.x-Abschluss oder v0.18.0.

**Konsequenz fuer Live-Preview:** Live-Preview-Endpoint fuer `[mio_termine]`
laeuft ueber `do_shortcode`, das die DHPS_Steuertermine-Klasse aufruft -
die wiederum den Adapter UMGEHT. Preview-Output bleibt bytewise identisch
zu v0.17.2.

### 4.4 Was passiert technisch?

Pipeline-Pfad fuer `[mio]`/`[lxmio]`:
- Adapter laeuft, Collection an Template.
- Pseudo-Rebuild im MIO-Template rekonstruiert `$tax_dates`.

Standalone-Pfad fuer `[mio_termine]`:
- Adapter laeuft NICHT (kein Registry-Lookup in DHPS_Steuertermine).
- `$data = $tax_dates` (gefiltert), Template rendert direkt.

**Beide Pfade liefern HTML-bytewise zum jeweiligen Vor-Release-Stand.**

---

## Sektion 5: Template-Migration-Strategie

### 5.1 Mapping pro Template

| Template | Strategie | Begruendung |
|---|---|---|
| `mio/default.php` | **Pseudo-Rebuild** (analog MMB v0.17.1 / TP v0.17.2): aus Collection rekonstruiere `$tax_dates`, danach Render-Code BYTEWISE unveraendert | Render-Code ist optimiert (Grid + dl/dt/dd + News-Mountpoint) - Risiko-arm |
| `mio/card.php` | **Pseudo-Rebuild** (analog default, anderes Wrapper) | wie default |
| `mio/compact.php` | **Pseudo-Rebuild** (analog default, andere CSS-Klassen) | wie default |
| `mio/partials/search-form.php` | **NICHT MIGRIEREN** | Partial konsumiert `$search_config` als rohes Array. Wird via `include` mit Scope-Variablen aufgerufen. Aenderung bricht die Funktion - lieber UNVERAENDERT lassen und das Search-Config-Array im Pseudo-Rebuild-Block des Eltern-Templates aus Collection-Meta wiederherstellen |
| `steuertermine/*.php` (4 Layouts) | **NICHT MIGRIEREN** (TD-V0173-1) | Standalone-Pfad ueber DHPS_Steuertermine umgeht Pipeline + Adapter. Templates konsumieren `$data` als rohes Array. Force-Legacy in v0.17.3 |
| LXMIO-Templates | **NICHT EXISTIEREND** | LXMIO nutzt MIO-Templates via Fallback `dhps_template_fallbacks` (lxmio -> mio). Automatisch von MIO-Patches abgedeckt |

### 5.2 Pseudo-Rebuild-Pattern (am Beispiel default.php)

Aktueller Kopf (Z. 37-40):
```php
$tax_dates     = $data['tax_dates'] ?? array();
$search_config = $data['search_config'] ?? array();
$ajax_params   = $data['ajax_params'] ?? array();
$service_tag   = $data['service_tag'] ?? 'mio';
```

Patch:
```php
// v0.17.3: Collection-Pfad wenn Adapter aktiv ist, sonst Legacy.
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
    // --- Tax-Dates rekonstruieren in Parser-Order. ---
    $tax_dates = array();
    foreach ( $collection as $item ) {
        /** @var DHPS_Content_Item $item */
        if ( 'tax_date' !== $item->type ) {
            continue; // defensiv - sollte heute alle Items treffen
        }
        $tax_dates[] = dhps_mio_item_to_legacy_month( $item );
    }

    // --- Collection-Meta lesen. ---
    $search_config = (array) $collection->get_meta( 'search_config', array() );
    $ajax_params   = (array) $collection->get_meta( 'ajax_params', array() );
} else {
    $tax_dates     = $data['tax_dates'] ?? array();
    $search_config = $data['search_config'] ?? array();
    $ajax_params   = $data['ajax_params'] ?? array();
}

$service_tag = $data['service_tag'] ?? 'mio';

// AB HIER UNVERAENDERT (Z. 42+):
// wp_enqueue_script( 'dhps-mio-js' );
// ... bestehender Render-Code bytewise unveraendert ...
```

**Erkenntnis:** Migration ist DEFENSIV. Der Render-Code ab Z. 44 (Service-
Wrapper bis Skeleton-Slot) bleibt buchstaeblich byte-identisch. Der
Collection-Pfad rekonstruiert dieselbe `$tax_dates`-Shape, sodass das
gerenderte HTML unveraendert bleibt (Smoke-Garantie).

### 5.3 Helper-Bedarf: `dhps_mio_item_to_legacy_month()`

Analog v0.17.2 `dhps_tp_item_to_legacy_video()`. Eine neue Helper-Datei
`includes/dhps-mio-content-helpers.php` mit einer einzelnen Funktion:

```php
function dhps_mio_item_to_legacy_month( DHPS_Content_Item $item ): array {
    $meta = is_array( $item->meta ) ? $item->meta : array();

    return array(
        'title'    => $item->title,
        'entries'  => isset( $meta['entries'] ) && is_array( $meta['entries'] )
            ? $meta['entries']
            : array(),
        'footnote' => isset( $meta['footnote'] ) ? (string) $meta['footnote'] : '',
    );
}
```

Diese Helper-Funktion ist die EINZIGE Stelle, an der das Rebuild lebt -
3 Templates rufen sie, isoliert testbar.

**Autoloader-Notiz:** `dhps-mio-content-helpers.php` folgt NICHT der
Klassen-Konvention. Bootstrap muss `require_once` explizit setzen
(analog `dhps-content-helpers.php` / `dhps-tp-content-helpers.php`).

---

## Sektion 6: Schema-Vertrag (verbindlich!)

Schema-Vertrag-Vorgehen ist 12x in Folge ohne Critical-Drift gelaufen.
**v0.17.3 = Iteration 13.** Disziplin halten.

### 6.1 MIO-Adapter Item-Konstruktor-Signatur (1 Monat = 1 Item)

```php
new DHPS_Content_Item(
    $id,             // '{service}-taxdate-{month_index}' (mio oder lxmio)
    $service,        // 'mio' ODER 'lxmio' (aus $service-Param)
    $title,          // (string) $month['title'] - Pflicht, non-empty;
                     //   Fallback 'Monat '.($idx+1) wenn parser-leer
    'tax_date',      // type (in ALLOWED_TYPES seit v0.17.0)
    '',              // body (leer - Sub-Struktur lebt im meta)
    null,            // excerpt
    null,            // image
    null,            // media
    null,            // link
    null,            // date (Monat hat keinen Tag - kein DateTimeImmutable)
    array(),         // tags
    null,            // category (Monate sind keine "Kategorien")
    $meta            // {month_index, entries, footnote?}
);
```

### 6.2 Meta-Felder-Vertrag (Items)

| Key | Typ | Pflicht | Quelle | Begruendung |
|---|---|---|---|---|
| `month_index` | int | ja | Schleifen-Index `$idx` aus tax_dates-Loop | Order-Rekonstruktion fuer Pseudo-Rebuild |
| `entries` | array | ja (kann leer sein) | 1:1 aus `tax_dates[$idx]['entries']` | Sub-Struktur (date + taxes[]) bleibt rohes Array |
| `footnote` | string | nur wenn !== '' | `tax_dates[$idx]['footnote']` | Optionale Monats-Fussnote |

**Wichtig zum `entries`-Sub-Schema (bleibt unveraendert, Parser-Shape):**
```
array(
    array(
        'date'  => string,    // z.B. '10.07.'
        'taxes' => array(),   // z.B. ['Umsatzsteuer', 'Lohnsteuer']
    ),
    // ...
)
```

### 6.3 Collection-Meta-Felder fuer MIO/LXMIO

```php
array(
    'search_config' => array(),     // 1:1 aus Parser
    'ajax_params'   => array(),     // 1:1 aus Parser
    'months_order'  => array(),     // int[], z.B. [0, 1] (Parser-Indices)
    'total_months'  => int,         // count(items)
    'total_entries' => int,         // Sum aller entries[] ueber alle Monate
)
```

### 6.4 Adapter-Signatur

```php
final class DHPS_MIO_Adapter implements DHPS_Content_Adapter_Interface {
    public function adapt( array $parser_output, string $service ): DHPS_Content_Collection;
}
```

Klassen-/Datei-Konvention (Autoloader-Pflicht):

| Klassenname | Dateipfad |
|---|---|
| `DHPS_MIO_Adapter` | `includes/class-dhps-mio-adapter.php` |

(Helper-Datei `includes/dhps-mio-content-helpers.php` folgt NICHT der
Klassen-Konvention - explizit per `require_once` im Bootstrap geladen,
analog zu `dhps-content-helpers.php` und `dhps-tp-content-helpers.php`.)

### 6.5 Force-Legacy: nicht im Adapter noetig

MIO hat **keine** Sub-Shortcode-Filter-Atts auf Adapter-Ebene. Der
Adapter wird IMMER mit dem vollen Parser-Output gerufen. Die
Filter-Logik fuer `[mio_termine]` (Monats-Filter `current`/`next`/`all`,
Entries-Begrenzung `count`) lebt in der Standalone-Klasse
`DHPS_Steuertermine` und umgeht den Adapter komplett (Sektion 4).

### 6.6 Pseudo-Rebuild-Schema (Template-Vertrag)

Template-Pseudo-Rebuild ZURUECK in `$tax_dates`-Shape:

```php
foreach ( $collection as $item ) {
    // ueber dhps_mio_item_to_legacy_month():
    $tax_dates[] = array(
        'title'    => $item->title,
        'entries'  => $item->meta['entries'] ?? array(),
        'footnote' => $item->meta['footnote'] ?? '',
    );
}
```

Iteration ist in Parser-Order (Items werden in Parser-Order angefuegt).
`months_order` wird NICHT vom Pseudo-Rebuild gelesen - es ist defensiv
fuer Templates, die ohne foreach pre-allocieren wollen (z.B. das Default-
Template mit 2-Spalten-Grid-Modifier `--single` bei nur 1 Monat).

### 6.7 ALLOWED_TYPES + ALLOWED_SERVICES sind bereit

- `'tax_date'` in `DHPS_Content_Item::ALLOWED_TYPES` (Z. 48) -> greift
- `'mio'`, `'lxmio'`, `'mio_termine'` in `ALLOWED_SERVICES` (Z. 63, 64, 73) -> greift

Keine Anpassung an der DTO-Foundation noetig. **0 Schema-Drift-Risiko**
auf Foundation-Ebene.

---

## Sektion 7: Acceptance-Kriterien T1-T15

### T1: MIO-Adapter mit leerem Parser-Output

```php
$collection = ( new DHPS_MIO_Adapter() )->adapt( array(), 'mio' );
```

Erwartet: `$collection->is_empty() === true`,
`get_meta('total_months') === 0`, `get_meta('total_entries') === 0`,
`get_meta('search_config') === []`, `get_meta('ajax_params') === []`.

### T2: MIO-Adapter mit minimaler tax_date-Struktur

```php
$parsed = array(
    'tax_dates' => array(
        array(
            'title'    => 'Juli 2026',
            'entries'  => array(
                array(
                    'date'  => '10.07.',
                    'taxes' => array( 'Umsatzsteuer', 'Lohnsteuer' ),
                ),
            ),
            'footnote' => 'Schonfrist 14.07.',
        ),
    ),
    'search_config' => array( 'search_placeholder' => 'Suchen...' ),
    'ajax_params'   => array( 'fachgebiet' => 'S', 'variante' => 'KATEGORIEN', 'anzahl' => '10' ),
);
$collection = ( new DHPS_MIO_Adapter() )->adapt( $parsed, 'mio' );
```

Erwartet:
- `count() === 1`
- `first()->id === 'mio-taxdate-0'`
- `first()->type === 'tax_date'`
- `first()->service === 'mio'`
- `first()->title === 'Juli 2026'`
- `first()->category === null`
- `first()->meta['month_index'] === 0`
- `first()->meta['entries'][0]['date'] === '10.07.'`
- `first()->meta['entries'][0]['taxes'] === ['Umsatzsteuer', 'Lohnsteuer']`
- `first()->meta['footnote'] === 'Schonfrist 14.07.'`
- `get_meta('total_months') === 1`
- `get_meta('total_entries') === 1`
- `get_meta('months_order') === [0]`
- `get_meta('search_config') === ['search_placeholder' => 'Suchen...']`
- `get_meta('ajax_params') === ['fachgebiet' => 'S', 'variante' => 'KATEGORIEN', 'anzahl' => '10']`

### T3: MIO-Adapter mit LXMIO-Service-Tag

Selber Parser-Output wie T2, aber `adapt($parsed, 'lxmio')`:

Erwartet:
- `first()->service === 'lxmio'`
- `first()->id === 'lxmio-taxdate-0'`

### T4: MIO-Adapter mit zwei Monaten

Zwei Monate, jeweils 2 Entries. Erwartet:
- `count() === 2`
- `get_meta('total_months') === 2`
- `get_meta('total_entries') === 4`
- `get_meta('months_order') === [0, 1]`
- Items in Parser-Order (Monat-1 zuerst, dann Monat-2)

### T5: MIO-Adapter ueberspringt Monate ohne Title UND ohne Entries

Monat mit `'title' => ''` UND `'entries' => array()`: wird uebersprungen.
`get_meta('total_months')` reflektiert das.

### T6: MIO-Adapter Fallback-Title bei leerem Title aber gueltigen Entries

Monat mit `'title' => ''` ABER gueltigen `entries`: bekommt Fallback-Title
`'Monat 1'` (bzw. `'Monat ' . ($idx + 1)`).

```php
$parsed = array(
    'tax_dates' => array(
        array(
            'title'    => '',
            'entries'  => array( array( 'date' => '10.07.', 'taxes' => array( 'USt' ) ) ),
            'footnote' => '',
        ),
    ),
);
$item = ( new DHPS_MIO_Adapter() )->adapt( $parsed, 'mio' )->first();
```

Erwartet: `$item->title === 'Monat 1'` (1-basiert, sprechbar).

### T7: MIO-Adapter Footnote leer wird NICHT in meta gespiegelt

Monat ohne Footnote: `$item->meta['footnote']` ist **nicht gesetzt**
(Adapter setzt nur, wenn non-empty). Test:
`isset($item->meta['footnote']) === false`. Begruendung: konsistent mit
MMB-Adapter Z. 134-136 (`source_id`-Optional-Pattern).

### T8: search_config + ajax_params fehlen im Parser-Output

Parser-Output ohne `search_config` ODER `ajax_params`-Schluessel:
Collection-Meta enthaelt **leere Arrays** als Default.

### T9: dhps_mio_item_to_legacy_month() liefert Parser-kompatible Shape

```php
$item = ( new DHPS_MIO_Adapter() )->adapt( $parsed_t2, 'mio' )->first();
$legacy_month = dhps_mio_item_to_legacy_month( $item );
```

Erwartet:
- `$legacy_month` hat exakt die Schluessel `'title', 'entries', 'footnote'`
  (Parser-Shape)
- `$legacy_month['title']` === Item.title
- `$legacy_month['entries']` ist Item.meta['entries'] (1:1)
- `$legacy_month['footnote']` ist Item.meta['footnote'] ODER `''`

### T10: MIO-Pipeline-Smoke - Frontend `[mio]` rendert HTML-bytewise

Vor- und nach-Migration: HTML-Diff zwischen v0.17.2 (ohne MIO-Adapter) und
v0.17.3 (mit MIO-Adapter aktiv) ist **0**. Pseudo-Rebuild im Template
garantiert das.

### T11: LXMIO-Pipeline-Smoke - Frontend `[lxmio]` rendert HTML-bytewise

Wie T10 fuer LXMIO. Bestaetigt dass LXMIO-Pfad via geteiltem Adapter +
Template-Fallback klappt.

### T12: MIO-Card + Compact - HTML-bytewise

Frontend `[mio layout="card"]` und `[mio layout="compact"]` rendern
bytewise identisch zu v0.17.2 (alle 3 MIO-Templates migriert).

### T13: `[mio_termine]` Standalone-Shortcode UNVERAENDERT (BC-Smoke)

Frontend `[mio_termine]`, `[mio_termine month="current"]`,
`[mio_termine count="3"]`, `[mio_termine layout="compact"]` rendern
bytewise identisch zu v0.17.2. Beweist dass Standalone-Pfad keinen
Collection-Drift bekommt (Force-Legacy, Sektion 4).

### T14: News-Container weiter funktional (AJAX-Mountpoint)

MIO-Templates rendern weiter den `<section.dhps-news data-dhps-news-container>`
mit allen data-Attributen. Die AJAX-News laden im Browser. Beweist dass
`ajax_params` ueber Collection-Meta korrekt durchgereicht werden.

### T15: Adapter-Exception ist Fail-Soft

Bei manipuliertem MIO-Parser-Output, der den Adapter zum Werfen bringt
(z.B. `tax_dates[0]['entries']` als String statt Array), faengt die
Pipeline ab. `$collection === null`, Template faellt auf Legacy-Pfad
zurueck. Smoke: kein PHP-Fatal, MIO-Frontend rendert ueber `$data`.

### Smoke-Tests fuer alle Layouts (Lead-Smoke)

- F1: Frontend `[mio]` (default) - bytewise gegen v0.17.2
- F2: Frontend `[mio layout="card"]` - bytewise gegen v0.17.2
- F3: Frontend `[mio layout="compact"]` - bytewise gegen v0.17.2
- F4: Frontend `[lxmio]` (default) - bytewise gegen v0.17.2
- F5: Frontend `[lxmio layout="card"]` - bytewise gegen v0.17.2
- F6: Frontend `[lxmio layout="compact"]` - bytewise gegen v0.17.2
- F7: Frontend `[mio_termine]` (default) - bytewise gegen v0.17.2
- F8: Frontend `[mio_termine layout="card"]` - bytewise gegen v0.17.2
- F9: Frontend `[mio_termine layout="compact"]` - bytewise gegen v0.17.2
- F10: Frontend `[mio_termine layout="inline"]` - bytewise gegen v0.17.2
- F11: Frontend `[mio_termine month="current" count="3"]` - bytewise gegen v0.17.2
- F12: Live-Preview im Admin-Dashboard fuer MIO + LXMIO + alle 3 Layouts
- F13: AJAX-News-Endpoint funktioniert unveraendert (`dhps_news_request`)

---

## Sektion 8: Spec-Aufteilung

### 8.1 Empfehlung: 1 Specialist + Lead-Direct (Phase 0)

**Begruendung:** Anders als v0.17.2 (2 Specialists F1+F2 fuer TP+TPT-
Trennung) ist v0.17.3 strukturell **einfacher**:

1. **EINE Adapter-Klasse** (MIO+LXMIO teilen sich den Adapter, kein TPT-
   aequivalent mit eigener Shape).
2. **EINE Item-Type** (`tax_date` - kein video+document+news-Mix wie MAES,
   kein featured+kategorie-Split wie TP).
3. **3 Templates** (default/card/compact) - kleiner Scope.
4. **Sub-Shortcode-Bridge bewusst Force-Legacy** (kein Modules-Layer-
   Refactor wie v0.17.1 MAES_Modules).

Eine F1+F2-Aufteilung wuerde nur kuenstliche Trennung schaffen. Eine
EINE Specialist-Iteration mit Lead-Phase-0 fuer den Helper ist sauber.

### 8.2 F1: MIO-Adapter + Template-Migration + LXMIO-Registrierung

**Scope:**
- `includes/class-dhps-mio-adapter.php` (~120 LOC): MIO_Adapter-Klasse.
- `public/views/services/mio/default.php` Pseudo-Rebuild (~25 LOC ergaenzt).
- `public/views/services/mio/card.php` Pseudo-Rebuild (~25 LOC ergaenzt).
- `public/views/services/mio/compact.php` Pseudo-Rebuild (~25 LOC ergaenzt).
- Bootstrap-Patch `Deubner_HP_Services.php`: 3 Zeilen Adapter-Registry-Calls
  (`mio` + `lxmio`).
- F1-Tests: T1-T15 + F1-F13 Smoke.

**Pflicht-Lesematerial:**
- Discovery (dieses Doc, insbesondere Sektion 2 + 5 + 6).
- `includes/class-dhps-mmb-adapter.php` (Vorbild Service-agnostischer Adapter).
- `includes/class-dhps-tp-adapter.php` (Vorbild komplexes meta + Helper).
- `includes/class-dhps-content-item.php` + `class-dhps-content-collection.php`
  (Schema-Vertrag, insbesondere ALLOWED_TYPES enthaelt `tax_date`).
- `includes/parsers/class-dhps-mio-parser.php`.
- `public/views/services/mio/default.php` + `card.php` + `compact.php`.
- `public/views/services/mmb/default.php` (Pseudo-Rebuild-Vorbild aus v0.17.1).
- `public/views/services/tp/default.php` (Pseudo-Rebuild-Vorbild aus v0.17.2).

**Aufwand:** S (klein-mittel), ~200 LOC.

### 8.3 Lead-Direct

- **Phase 0 (Lead, vor F1):** `includes/dhps-mio-content-helpers.php`
  schreiben (~25 LOC, `dhps_mio_item_to_legacy_month()`-Helper). Bootstrap-
  Patch: `require_once`. Diese Datei ist Prerequisite fuer F1.
- Bootstrap-Registration der 2 Service-Tags (3 Zeilen, analog v0.17.2).
- Version-Bump `Deubner_HP_Services.php`, `README.md`.
- `docs/project/50-CHANGELOG-v0173.md`.
- MEMORY.md Milestone 21 + Implementation-Notes.

### 8.4 Phasen-Reihenfolge

```
Phase 0 (Lead):     dhps-mio-content-helpers.php (Helper)
Phase 1 (F1):       MIO-Adapter + 3 Templates + Bootstrap-Patch
Phase 2 (Lead):     Bootstrap-Registration mergen (3 Zeilen)
Phase 3 (parallel): QA-Smoke (T1-T15 + F1-F13) + SEC-Audit
Phase 4 (Lead):     Stage-Smoke, CHANGELOG, MEMORY, RC-Tag
```

### 8.5 Alternative: 2 Specialists (NICHT empfohlen)

Eine kuenstliche Trennung waere:
- F1: Adapter-Klasse + Tests
- F2: Template-Migration

Aber: F2 braucht F1-Output sowieso (kein Parallelisierungs-Vorteil), und
beide Aufgaben sind klein. EIN Spezialist haelt den Kontext zusammen,
spart eine Spec-Doc, vermeidet Coordination-Overhead.

---

## Sektion 9: Risiken + Tech-Debt

### 9.1 Risiken-Matrix

| ID | Risiko | Severity | Mitigation |
|---|---|---|---|
| R1 | **Pseudo-Rebuild im Template fuehrt zu HTML-Drift** wenn der Adapter ein Feld vergisst (z.B. `footnote`) | HIGH | Acceptance T10-T12 bytewise. Sektion 6.1+6.2 sperrt das Item-Schema explizit. Helper `dhps_mio_item_to_legacy_month()` ist die EINZIGE Stelle wo das Rebuild lebt -> isoliert testbar |
| R2 | **tax_date als Item-Type war in v0.17.0 vorbehalten** - to_content_card_props() mapped auf 'document'. Wenn ein Template versehentlich die ContentCard-Bridge nutzt, verliert es Sub-Struktur | MED | Templates rendern tax_dates NICHT als ContentCard (Discovery Sektion 2.5 Frage 3). Pseudo-Rebuild rekonstruiert direkt das BEM-Markup. Doku-Hinweis im Adapter-Header |
| R3 | **Adapter-Aufruf fuer `[mio_termine]` umgangen** - inkonsistent zu MIO-Pipeline-Pfad. Wenn Themes per Filter etwas am Adapter machen wollen, greift das fuer `[mio_termine]` nicht | LOW | Bewusste Entscheidung (Sektion 4). Tech-Debt-Ticket TD-V0173-1 fuer v0.17.x-Abschluss. Aktueller Pfad ist BC-sicher |
| R4 | **News-Container haengt an ajax_params-data-Attributen** - wenn Pseudo-Rebuild ajax_params nicht aus Collection liest, sind die data-Attribute leer und AJAX-News brechen | HIGH | Acceptance T14 prueft das. Schema-Vertrag Sektion 6.3 sperrt `ajax_params` als Collection-Meta-Pflichtfeld |
| R5 | **search_config ueber include __DIR__ . '/partials/search-form.php'** - Partial konsumiert `$search_config` als Scope-Variable. Wenn Pseudo-Rebuild $search_config nicht setzt, hat das Partial leeres Array (Search-Form-Defaults greifen) | MED | Pseudo-Rebuild MUSS `$search_config` setzen aus `$collection->get_meta('search_config')`. Acceptance F1+F4 deckt das ab |
| R6 | **Title-Pflichtfeld bei leerem Monat** - DHPS_Content_Item wirft InvalidArgumentException bei title='' | MED | Fallback-Title `'Monat '.($idx+1)` (Acceptance T6). Sektion 2.5 Frage 1 dokumentiert |
| R7 | **2 Monate in 1 Render** - Adapter haengt in Parser-Order an. Template-Pseudo-Rebuild iteriert ueber `$collection` (geht in Parser-Order). Wenn ein Filter die Order aendert, drift | LOW | DHPS_Content_Collection iteriert deterministisch (ArrayIterator). 0 Risiko in Standard-Pfad. Doku im Adapter-Header |
| R8 | **`months_order`-Defensivfeld** wird vom Pseudo-Rebuild NICHT gelesen - ist es dann ueberhaupt noetig? | LOW | Defensiv fuer Templates die ohne foreach pre-allocieren wollen (z.B. Grid-Modifier `--single` bei nur 1 Monat). Wird in default.php genutzt: `$grid_modifier = ( 1 === count( $tax_dates ) ) ? ' dhps-termine__grid--single' : '';`. Steht in steuertermine/default.php Z. 17, NICHT in services/mio/default.php. Trotzdem als saubere Convention beibehalten |
| R9 | **tax_dates ist max 2-elementig** (Parser-Hardcode: for $i=1;$i<=2;$i++) - Items sind nie mehr als 2. Performance-Sorge entfaellt | n/a | Hardgecoded im Parser. Adapter ist trotzdem O(n) ohne Limits, falls Parser sich aendert |
| R10 | **Helper `dhps_mio_item_to_legacy_month()` global** = Namespace-Risiko bei Plugin-Konflikten | LOW | Funktion-Name hat `dhps_mio_`-Prefix, kollidiert nicht. Konsistent mit `dhps_tp_item_to_legacy_video()` aus v0.17.2 und `dhps_build_collection_for()` aus v0.17.1 |
| R11 | **DateTimeImmutable-Lockruf** - Monatstitel hat Datum-Info ('Juli 2026'), aber kein Tag. Wenn Adapter `new DateTimeImmutable('Juli 2026')` versuchen wuerde, knallt es | LOW | Adapter setzt $date = null (Sektion 6.1). DateTimeImmutable in v0.17.x-Abschluss als Tech-Debt-Ticket TD-V0173-2 (`YYYY-MM-01`-Normalisierung) |

### 9.2 Tech-Debt-Tickets fuer v0.17.4+

| Ticket | Beschreibung | Zielversion |
|---|---|---|
| TD-V0173-1 | **`[mio_termine]`-Standalone-Shortcode auf Adapter-Bridge bringen**: DHPS_Steuertermine bekommt `get_collection()`-Methode + die 4 Steuertermine-Templates (`public/views/steuertermine/*.php`) bekommen das Pseudo-Rebuild-Pattern. Force-Legacy bei `month != 'all'` ODER `count > 0` (Filter-Atts), sonst Collection-Bridge | v0.17.x-Abschluss |
| TD-V0173-2 | **Datum-Normalisierung Monat -> YYYY-MM-01**: Adapter setzt Item.$date = DateTimeImmutable von Monatsanfang. Erfordert Monatsnamen-Parsing ('Juli 2026' -> 2026-07-01). Risiko: Sprach-Lokalisierung | v0.17.x-Abschluss / v0.18.0 |
| TD-V0173-3 | **TC-Adapter** (v0.17.4 Roadmap) - Wrapper-Adapter mit Empty-State-Check, leere Collection + meta['html'] | v0.17.4 |
| TD-V0173-4 | **MIO-News-AJAX-Endpoint auf Adapter-Bridge**: DHPS_MIO_News_Parser-Output ist `{groups + articles}` - ein zweiter Adapter `DHPS_MIO_News_Adapter` koennte die News-Items als ContentItems vom Type 'news' liefern. Aktuell rendert clientside JS direkt aus AJAX-HTML | v0.17.x-Abschluss / v0.18.0 |
| TD-V0173-5 | **Live-Preview-Schema-Erweiterung**: SERVICE_ATTS_SCHEMA in DHPS_Preview_Renderer kennt heute MIO-Atts. Pruefen ob ueber den Adapter neue Atts/Felder sichtbar werden muessen | v0.17.x-Abschluss |

### 9.3 Autoloader-Notiz (Lehre aus v0.17.0+v0.17.1+v0.17.2)

Klassen-/Datei-Konvention im Plugin: `class-dhps-foo-bar.php` -> `DHPS_Foo_Bar`.
F1 muss den Adapter als `DHPS_MIO_Adapter` benennen und die Datei
`includes/class-dhps-mio-adapter.php` legen (Autoloader greift).

**Helper-Datei** `includes/dhps-mio-content-helpers.php`: Folgt NICHT der
Klassen-Konvention, daher Autoloader greift nicht. Lead-Phase-0 muss im
Bootstrap explizit `require_once DEUBNER_HP_SERVICES_PATH . 'includes/dhps-mio-content-helpers.php';`
hinzufuegen. Empfohlene Position: direkt nach dem `require_once` fuer
`dhps-tp-content-helpers.php` (Z. 137 in `Deubner_HP_Services.php`).

### 9.4 Discovery-Lessons fuer Specialist

1. **Klassen-Name check**: `DHPS_MIO_Adapter` (mit `_`, alle 3 Buchstaben
   Underscore-frei weil Abkuerzung), Datei `class-dhps-mio-adapter.php`.
2. **Bytewise-Smoke-Test mit `curl | diff`** vom Host (NICHT aus dem
   Container - v0.17.0-Lehre 2).
3. **ALLOWED_TYPES hat `tax_date`** bereits seit v0.17.0 - keine Foundation-
   Aenderung noetig. ALLOWED_SERVICES hat `mio`+`lxmio`+`mio_termine` bereits.
4. **Pseudo-Rebuild-Pattern** ist bewaehrt (v0.17.1 + v0.17.2) - 1:1
   uebernehmen, Helper-Function konzentriert das Rebuild-Wissen.
5. **News-Container clientside** = Adapter sieht keine News-Items. Schema
   nur `tax_date`-Items + Service-Meta.

---

## Sektion 10: Spec-Briefing-Material

### 10.1 Dateipfade fuer Neuschoepfung (F1)

```
includes/class-dhps-mio-adapter.php
```

### 10.2 Dateipfade fuer Neuschoepfung (Lead Phase 0)

```
includes/dhps-mio-content-helpers.php
```

### 10.3 Dateipfade fuer Anpassung (Lead + F1)

```
Deubner_HP_Services.php
    - Version-Bump 0.17.2 -> 0.17.3 (Lead)
    - require_once includes/dhps-mio-content-helpers.php (Lead Phase 0)
    - DHPS_Content_Adapter_Registry::register( 'mio', $mio_adapter ) (Lead Phase 2)
    - DHPS_Content_Adapter_Registry::register( 'lxmio', $mio_adapter ) (Lead Phase 2)
public/views/services/mio/default.php   (F1: Pseudo-Rebuild)
public/views/services/mio/card.php      (F1: Pseudo-Rebuild)
public/views/services/mio/compact.php   (F1: Pseudo-Rebuild)
README.md                               (Lead: Version-Bump)
docs/project/50-CHANGELOG-v0173.md      (Lead: Release-Doku, NEU)
MEMORY.md                               (Lead: MILESTONE 21)
```

### 10.4 Bootstrap-Diff-Beispiel (Lead)

```php
// In Plugin-Bootstrap, Phase 0 (Datei-Top, nach Z. 137):

/*
|--------------------------------------------------------------------------
| MIO-Content-Helpers (v0.17.3)
|--------------------------------------------------------------------------
|
| Geteilter Item-zu-Legacy-Month-Helper fuer MIO-Templates (3 Templates
| nutzen den Helper im Pseudo-Rebuild-Pfad).
*/
require_once DEUBNER_HP_SERVICES_PATH . 'includes/dhps-mio-content-helpers.php';
```

```php
// In dhps_init(), nach Z. 355 (TPT-Adapter-Registrierung):

// 3a-5. MIO-Adapter (v0.17.3): wird sowohl fuer 'mio' als auch 'lxmio'
//       registriert, weil LXMIO den MIO-Parser teilt und keine eigenen
//       Templates hat (Fallback dhps_template_fallbacks: lxmio -> mio).
//       Der Adapter ist Service-agnostisch (Discovery v0.17.3 Sektion 3,
//       Option B aus MMB/MIL+TP/LP-Pattern). Item-IDs sind
//       'mio-taxdate-{idx}' bzw. 'lxmio-taxdate-{idx}'. Sondertyp 'tax_date'
//       (in ALLOWED_TYPES seit v0.17.0 vorbehalten - loest sich hier ein).
//       Der Sub-Shortcode [mio_termine] umgeht den Adapter bewusst
//       (Tech-Debt TD-V0173-1 fuer v0.17.x-Abschluss).
$mio_adapter = new DHPS_MIO_Adapter();
DHPS_Content_Adapter_Registry::register( 'mio', $mio_adapter );
DHPS_Content_Adapter_Registry::register( 'lxmio', $mio_adapter );
```

### 10.5 Snippet: dhps_mio_item_to_legacy_month() Helper

```php
<?php
/**
 * MIO-Content-Helpers (v0.17.3): Item-zu-Legacy-Month-Helper.
 *
 * Pseudo-Rebuild-Helfer fuer die MIO-Templates. Wandelt ein ContentItem
 * vom Type 'tax_date' (Output von DHPS_MIO_Adapter) zurueck in die
 * Legacy-Monats-Shape, die der DHPS_MIO_Parser liefert. Damit bleiben
 * die existierenden MIO-Templates (default/card/compact) im Render-Code
 * bytewise unveraendert - lediglich der Daten-Zugriff am Kopf der
 * Templates wandelt sich.
 *
 * Schema-Vertrag siehe docs/architecture/29-MIO-ADAPTER-PLAN-v0173.md
 * Sektion 6.1-6.6.
 *
 * Klassen-Konvention: Datei folgt NICHT class-dhps-foo-bar.php (Helper
 * ist Function, nicht Klasse). Bootstrap muss explizit `require_once`
 * setzen, analog dhps-content-helpers.php / dhps-tp-content-helpers.php.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'dhps_mio_item_to_legacy_month' ) ) {

    /**
     * Rekonstruiert ein MIO-Tax-Date-Monats-Legacy-Array aus einem ContentItem.
     *
     * Gegen-Mapping des MIO-Adapter-Itemschemas. Wird in den Template-
     * Pseudo-Rebuild-Bloecken (default.php / card.php / compact.php)
     * genutzt, damit der eigentliche Render-Code BYTEWISE unveraendert
     * bleibt (Smoke-Garantie).
     *
     * Item-Typ-Vertrag: Item.type MUSS 'tax_date' sein. Bei anderem Typ
     * liefert die Funktion einen leeren Monats-Stub - das ist
     * defensives Verhalten, der Adapter sollte aber nie Items eines
     * anderen Typs in MIO-Collections legen.
     *
     * @since 0.17.3
     *
     * @param DHPS_Content_Item $item Item aus DHPS_MIO_Adapter (Type 'tax_date').
     *
     * @return array Legacy-Monats-Shape mit Schluesseln `title`, `entries`, `footnote`.
     */
    function dhps_mio_item_to_legacy_month( DHPS_Content_Item $item ): array {
        $meta = is_array( $item->meta ) ? $item->meta : array();

        return array(
            'title'    => $item->title,
            'entries'  => isset( $meta['entries'] ) && is_array( $meta['entries'] )
                ? $meta['entries']
                : array(),
            'footnote' => isset( $meta['footnote'] ) ? (string) $meta['footnote'] : '',
        );
    }
}
```

### 10.6 Snippet: MIO-Adapter Skeleton

```php
<?php
/**
 * MIO-Adapter (v0.17.3): wandelt DHPS_MIO_Parser-Output (auch LXMIO, das
 * denselben Parser teilt) in DHPS_Content_Collection.
 *
 * Vierter Adapter im einheitlichen Datenmodell (nach MAES v0.17.0,
 * MMB v0.17.1, TP/TPT v0.17.2). Mappet MIO-Tax-Dates auf ContentItems
 * vom Type 'tax_date' (in ALLOWED_TYPES seit v0.17.0 vorbehalten - loest
 * sich hier ein). Sub-Struktur (entries[]/taxes[]/footnote) wandert in
 * den $meta-Hash des ContentItems (Trust-Decision TD-3 - Fluchtweg statt
 * Sub-DTO).
 *
 * News-Container und Search-Form-Konfiguration werden in der Collection-
 * Meta abgelegt; News-Items selbst entstehen erst im AJAX-Endpoint
 * (DHPS_MIO_News_Parser) und sind nicht Teil dieses Adapters.
 *
 * Service-Tolerant: Wird sowohl fuer `mio` als auch `lxmio` registriert
 * (Discovery v0.17.3 Sektion 3, Option B aus MMB/MIL+TP/LP-Pattern).
 * Item-IDs sind `mio-taxdate-{idx}` bzw. `lxmio-taxdate-{idx}`.
 *
 * Sub-Shortcode `[mio_termine]` umgeht den Adapter BEWUSST in v0.17.3.
 * Der Standalone-Pfad (`DHPS_Steuertermine`) hat eigene Templates +
 * eigene Filter-Atts (`month`, `count`) - Migration auf Adapter-Bridge
 * ist Tech-Debt-Ticket TD-V0173-1 fuer v0.17.x-Abschluss.
 *
 * Robustheit:
 * - Fehlt `tax_dates`, wird eine leere Collection geliefert (kein Throw).
 * - Monate ohne Title UND ohne Entries werden skipped.
 * - Monate ohne Title aber mit Entries bekommen Fallback-Title
 *   `'Monat '.($idx+1)` (DHPS_Content_Item erzwingt non-empty title).
 * - Footnote wird NUR in meta gesetzt wenn non-empty (konsistent mit
 *   MMB-Adapter source_id-Pattern).
 * - Defensive `(string)`/`(int)`/`is_array()`-Checks bei jedem Feldzugriff.
 *
 * Klassen-/Datei-Konvention: `DHPS_MIO_Adapter` -> `class-dhps-mio-adapter.php`,
 * Datei liegt im includes/-Root (Autoloader-Konvention, identisch zu
 * MAES/MMB/TP/TPT-Adapter).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DHPS_MIO_Adapter implements DHPS_Content_Adapter_Interface {

    public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {
        $tax_dates = isset( $parser_output['tax_dates'] ) && is_array( $parser_output['tax_dates'] )
            ? $parser_output['tax_dates']
            : array();

        $items         = array();
        $months_order  = array();
        $total_entries = 0;

        foreach ( $tax_dates as $idx => $month ) {
            if ( ! is_array( $month ) ) {
                continue;
            }

            $raw_title = isset( $month['title'] ) ? (string) $month['title'] : '';
            $entries   = isset( $month['entries'] ) && is_array( $month['entries'] )
                ? $month['entries']
                : array();
            $footnote  = isset( $month['footnote'] ) ? (string) $month['footnote'] : '';

            // Skip-Bedingung: leerer Monat ohne Title UND ohne Entries.
            if ( '' === trim( $raw_title ) && empty( $entries ) ) {
                continue;
            }

            // Title-Fallback: DHPS_Content_Item erzwingt non-empty title.
            $title = ( '' !== trim( $raw_title ) )
                ? $raw_title
                : sprintf( 'Monat %d', (int) $idx + 1 );

            $item_id = $service . '-taxdate-' . (int) $idx;

            $meta = array(
                'month_index' => (int) $idx,
                'entries'     => $entries,
            );
            if ( '' !== $footnote ) {
                $meta['footnote'] = $footnote;
            }

            $items[] = new DHPS_Content_Item(
                $item_id,
                $service,
                $title,
                'tax_date',
                '',       // body
                null,     // excerpt
                null,     // image
                null,     // media
                null,     // link
                null,     // date (Monat hat keinen Tag - kein DateTimeImmutable)
                array(),  // tags
                null,     // category
                $meta
            );

            $months_order[] = (int) $idx;
            $total_entries += count( $entries );
        }

        $search_config = isset( $parser_output['search_config'] ) && is_array( $parser_output['search_config'] )
            ? $parser_output['search_config']
            : array();
        $ajax_params   = isset( $parser_output['ajax_params'] ) && is_array( $parser_output['ajax_params'] )
            ? $parser_output['ajax_params']
            : array();

        $meta = array(
            'search_config' => $search_config,
            'ajax_params'   => $ajax_params,
            'months_order'  => $months_order,
            'total_months'  => count( $items ),
            'total_entries' => $total_entries,
        );

        return new DHPS_Content_Collection( $service, $items, $meta );
    }
}
```

### 10.7 Snippet: mio/default.php Pseudo-Rebuild

```php
// In mio/default.php direkt nach Z. 36 (vor $tax_dates/$search_config-Zuweisung):

$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
    // --- Tax-Dates rekonstruieren in Parser-Order. ---
    $tax_dates = array();
    foreach ( $collection as $item ) {
        /** @var DHPS_Content_Item $item */
        if ( 'tax_date' !== $item->type ) {
            continue; // defensiv - sollte heute alle Items treffen
        }
        $tax_dates[] = dhps_mio_item_to_legacy_month( $item );
    }

    // --- Collection-Meta lesen. ---
    $search_config = (array) $collection->get_meta( 'search_config', array() );
    $ajax_params   = (array) $collection->get_meta( 'ajax_params', array() );
} else {
    $tax_dates     = $data['tax_dates'] ?? array();
    $search_config = $data['search_config'] ?? array();
    $ajax_params   = $data['ajax_params'] ?? array();
}

$service_tag = $data['service_tag'] ?? 'mio';

// AB HIER UNVERAENDERT (Z. 42+):
// wp_enqueue_script( 'dhps-mio-js' );
// ... bestehender Render-Code bytewise unveraendert ...
```

### 10.8 Test-Skript-Skelett

```php
<?php
// tests/test-mio-adapter.php

class Test_DHPS_MIO_Adapter extends WP_UnitTestCase {

    public function test_empty_parser_output(): void {
        $collection = ( new DHPS_MIO_Adapter() )->adapt( array(), 'mio' );
        $this->assertTrue( $collection->is_empty() );
        $this->assertSame( 0, $collection->get_meta( 'total_months' ) );
        $this->assertSame( 0, $collection->get_meta( 'total_entries' ) );
    }

    public function test_minimal_tax_date(): void {
        $parsed = array(
            'tax_dates' => array(
                array(
                    'title'    => 'Juli 2026',
                    'entries'  => array(
                        array( 'date' => '10.07.', 'taxes' => array( 'USt', 'LSt' ) ),
                    ),
                    'footnote' => 'Schonfrist 14.07.',
                ),
            ),
            'search_config' => array( 'search_placeholder' => 'Suchen...' ),
            'ajax_params'   => array( 'fachgebiet' => 'S', 'variante' => 'KAT', 'anzahl' => '10' ),
        );
        $collection = ( new DHPS_MIO_Adapter() )->adapt( $parsed, 'mio' );

        $this->assertSame( 1, $collection->count() );
        $first = $collection->first();
        $this->assertSame( 'mio-taxdate-0', $first->id );
        $this->assertSame( 'tax_date', $first->type );
        $this->assertSame( 'mio', $first->service );
        $this->assertSame( 'Juli 2026', $first->title );
        $this->assertNull( $first->category );
        $this->assertSame( 0, $first->meta['month_index'] );
        $this->assertSame( '10.07.', $first->meta['entries'][0]['date'] );
        $this->assertSame( array( 'USt', 'LSt' ), $first->meta['entries'][0]['taxes'] );
        $this->assertSame( 'Schonfrist 14.07.', $first->meta['footnote'] );
    }

    public function test_lxmio_service_tag(): void {
        $parsed = array(
            'tax_dates' => array(
                array( 'title' => 'M', 'entries' => array( array( 'date' => 'D', 'taxes' => array( 'T' ) ) ), 'footnote' => '' ),
            ),
        );
        $first = ( new DHPS_MIO_Adapter() )->adapt( $parsed, 'lxmio' )->first();
        $this->assertSame( 'lxmio', $first->service );
        $this->assertSame( 'lxmio-taxdate-0', $first->id );
    }

    public function test_skip_empty_month(): void {
        $parsed = array(
            'tax_dates' => array(
                array( 'title' => '', 'entries' => array(), 'footnote' => '' ),
                array( 'title' => 'Aug', 'entries' => array(), 'footnote' => '' ),
            ),
        );
        $collection = ( new DHPS_MIO_Adapter() )->adapt( $parsed, 'mio' );
        $this->assertSame( 1, $collection->count() );
        $this->assertSame( 'Aug', $collection->first()->title );
    }

    public function test_fallback_title(): void {
        $parsed = array(
            'tax_dates' => array(
                array( 'title' => '', 'entries' => array( array( 'date' => 'X', 'taxes' => array( 'T' ) ) ), 'footnote' => '' ),
            ),
        );
        $first = ( new DHPS_MIO_Adapter() )->adapt( $parsed, 'mio' )->first();
        $this->assertSame( 'Monat 1', $first->title );
    }

    public function test_helper_roundtrip(): void {
        $parsed = array(
            'tax_dates' => array(
                array(
                    'title'    => 'Juli',
                    'entries'  => array( array( 'date' => 'D', 'taxes' => array( 'T' ) ) ),
                    'footnote' => 'Note',
                ),
            ),
        );
        $first  = ( new DHPS_MIO_Adapter() )->adapt( $parsed, 'mio' )->first();
        $legacy = dhps_mio_item_to_legacy_month( $first );

        $this->assertSame(
            array( 'title' => 'Juli', 'entries' => array( array( 'date' => 'D', 'taxes' => array( 'T' ) ) ), 'footnote' => 'Note' ),
            $legacy
        );
    }
}
```

---

## Anhang: Lead-Briefing-Zusammenfassung

| Frage | Antwort |
|---|---|
| **tax_dates-Mapping** | **Option A** - 1 Item pro Monat mit `type='tax_date'`, `meta['entries']` enthaelt die Sub-Struktur (date+taxes[]). Loest den v0.17.0-Vorbehalt ein |
| **Adapter-Strategie** | EINE Adapter-Klasse `DHPS_MIO_Adapter`, fuer `mio` + `lxmio` registriert. Konsistent mit MMB/MIL (v0.17.1) und TP/LP (v0.17.2). LXMIO nutzt MIO-Templates via Fallback |
| **Sub-Shortcode `[mio_termine]`** | **Force-Legacy** in v0.17.3 - der Standalone-Shortcode (DHPS_Steuertermine) umgeht den Adapter bewusst. Eigene Templates (`public/views/steuertermine/`), eigene Filter-Atts (`month`, `count`). Tech-Debt-Ticket TD-V0173-1 fuer v0.17.x-Abschluss |
| **Templates** | Alle 3 MIO-Templates (default/card/compact) bekommen Pseudo-Rebuild. Search-Form-Partial UNVERAENDERT (konsumiert `$search_config` als Scope-Variable). LXMIO nutzt automatisch ueber Fallback. Steuertermine-Templates NICHT migriert (Force-Legacy) |
| **Spec-Aufteilung** | **1 Specialist + Lead-Phase-0** - MIO ist klein genug (kein TP+TPT-Split). Lead schreibt den Helper, F1 macht Adapter + 3 Templates + Tests |
| **Top-3-Risiken** | R1 (Pseudo-Rebuild HTML-Drift), R4 (News-Container ajax_params nicht durchgereicht), R6 (Title-Pflichtfeld bei leerem Monat - Fallback dokumentiert) |
| **Geschaetzter Aufwand** | **S (klein)** - F1 ~200 LOC, Lead Phase 0 ~25 LOC Helper + Phase 2 ~5 LOC Bootstrap + Doku ~200 LOC = ca. 430 LOC total. 1 Discovery-Doc (dieses), 1 Spec-Doc, QA+SEC parallel |
| **Schema-Vertrag-Status** | Sektion 6 ist verbindlich. **13. Iteration in Folge** - Disziplin halten |
| **PHP-Minimum** | bleibt 8.1 (seit v0.17.0). Keine Aenderung |
| **DTO-Foundation-Aenderungen** | **0** - ALLOWED_TYPES enthaelt `tax_date` (Z. 48), ALLOWED_SERVICES enthaelt `mio`+`lxmio`+`mio_termine` (Z. 63, 64, 73). Foundation ist bereit |

**Risiko-Gegenmittel-Map:**

- R1: Bytewise-HTML-Smoke (T10+T11+T12+F1-F6) + Schema-Vertrag in Sektion
  6.1+6.2 sperrt das Item-Schema. Helper `dhps_mio_item_to_legacy_month()`
  ist die EINZIGE Rebuild-Stelle - isoliert testbar (T9)
- R4: News-Container haengt an data-Attributen mit `ajax_params`. Schema-
  Vertrag Sektion 6.3 sperrt ajax_params als Pflichtfeld in Collection-Meta.
  Acceptance T14 prueft Funktion
- R6: Title-Fallback `'Monat '.($idx+1)` (Sektion 2.5 Frage 1, Acceptance T6).
  Vermeidet DHPS_Content_Item-InvalidArgumentException bei Edge-Case

**Reihenfolge fuer Implementation:**

1. Phase 0 Lead: `dhps-mio-content-helpers.php` schreiben + Bootstrap-require.
2. Phase 1 F1: MIO-Adapter + 3 Templates + Tests.
3. Phase 2 Lead: Bootstrap-Registry-Patches mergen (3 Zeilen).
4. Phase 3 parallel: QA-Smoke (T1-T15 + F1-F13) + SEC-Audit.
5. Phase 4 Lead: Stage-Smoke, CHANGELOG, MEMORY, RC-Tag.

**Wichtigste Frage beantwortet:** **Option A** - 1 Item pro Monat mit
`type='tax_date'`, Sub-Struktur (`entries[]`/`taxes[]`/`footnote`) wandert
in `Item.meta`. Der Adapter ist Service-agnostisch fuer MIO + LXMIO. Der
Sub-Shortcode `[mio_termine]` bekommt **bewusst keine Bridge** in v0.17.3 -
er bleibt ueber `DHPS_Steuertermine` standalone (Force-Legacy, Tech-Debt-
Ticket TD-V0173-1 fuer spaeter).

**Ende Discovery v0.17.3.**
