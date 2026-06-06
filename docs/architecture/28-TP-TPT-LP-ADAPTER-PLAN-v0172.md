# 28 - TP/TPT/LP-Adapter - Plan v0.17.2

**Status:** Discovery (2026-06-06)
**Aktuelle Plugin-Version:** v0.17.1
**Ziel-Version:** v0.17.2
**Architekt-Auftrag:** Dritter Adapter-Block der inkrementellen Datenmodell-
Migration. Nach MAES (v0.17.0) und MMB/MIL (v0.17.1) jetzt die TP-Familie
(TaxPlain Videos + TaxPlain Teaser + LexPlain Videos).

**Discovery-Empfehlung vorab (Kurzfassung):**

- **Adapter-Strategie:** **Option C** - **EINE Adapter-Klasse `DHPS_TP_Adapter`**,
  fuer `tp` + `lp` registriert. **PLUS** **eigene `DHPS_TPT_Adapter`-Klasse**
  (kein extends, eigenes Schema "video"-Top-Level vs "categories[].videos[]").
  Begruendung in Sektion 4.
- **featured_video-Mapping:** **Item-Type `video` PLUS Item-meta `is_featured=true`**
  und **Collection-Meta `featured_video_id`** (Lookup-Key). KEIN eigener Item-
  Type. Begruendung in Sektion 2.4.
- **tpt_config-Mapping:** komplett ins **Collection-Meta** (`tpt_config`), 1:1
  Schluessel uebernommen. Templates lesen daraus, exakt wie heute aus `$data`.
- **Template-Migration:** **Pseudo-Rebuild-Pattern** (analog MMB v0.17.1) fuer
  `tp/default.php` + `tp/card.php` + alle 3 TPT-Templates. **`tp/compact.php`
  bleibt UNVERAENDERT** (Tech-Debt aus v0.14.3, JS-spawn-Risiko - Re-Bestaetigung
  als TD-V0172-1).
- **LP-Templates:** existieren NICHT - LP nutzt die TP-Templates via
  `dhps_template_fallbacks`-Filter -> kein eigener Template-Patch. Adapter wird
  durch Service-Registrierung automatisch aktiv (LP-Pipeline-Aufruf trifft den
  Adapter, der `$service='lp'` reicht durch).
- **Spec-Aufteilung:** **2 Specialists parallel** - F1 (TP-Adapter + 2 TP-
  Templates + LP-Registrierung + LP-Smoke) und F2 (TPT-Adapter + 3 TPT-Templates +
  tpt_config-Bridge). Begruendung in Sektion 8.
- **Aufwand:** M (mittel) - F1 ~280 LOC, F2 ~250 LOC, Lead ~60 LOC, Doku
  ~200 LOC = ca. 800 LOC.

---

## Sektion 1: Ausgangslage TP/TPT/LP-Parser

### 1.1 Parser-Output-Vergleich (Top-Level-Keys)

| Parser | Top-Level-Schluessel | Service-Tag | Vererbung |
|---|---|---|---|
| `DHPS_TP_Parser` | `featured_video` (array\|null), `categories` (array), `service_tag` (`'tp'`) | `tp` | - |
| `DHPS_TPT_Parser` | `video` (array\|null), `service_tag` (`'tpt'`) | `tpt` | `extends DHPS_TP_Parser` (nutzt nur `parse_video_block()`) |
| `DHPS_LP_Parser` | wie TP: `featured_video`, `categories`, `service_tag` (`'lp'`) | `lp` | `extends DHPS_TP_Parser` |

**Wichtig:** Die Pipeline ueberschreibt `service_tag` in `$parsed_data` mit dem
Tag des Aufrufers (s. v0.17.0-Architektur). Adapter darf sich daher NICHT auf
`$parser_output['service_tag']` verlassen - relevant ist der `$service`-Param.

### 1.2 TP-Parser: featured_video + categories[].videos[]

**`featured_video`** (single video, kann `null` sein) - Quelle:
`div.aktuelles_video` aus dem API-HTML.

**`categories[]`** (Liste der Rubriken) - Quelle: `h4.rubrik` + nachfolgende
`videoblock_rubrik`-Divs. Shape:
```
[
    [
        'name'   => 'Rubrik-Name',  // string
        'videos' => [ video_block, video_block, ... ]
    ],
    ...
]
```
**Wichtig:** Kategorien haben **KEINE ID** (anders als MMB). Sie werden im
Template ueber den **numerischen Index** referenziert (`data-filter="0"`,
`data-filter="1"`, ...).

### 1.3 Video-Shape im Detail

Aus `DHPS_TP_Parser::parse_video_block()` (Z. 240-292):

```
array(
    'video_id'   => '',           // string, aus container-id-Attribut (numerisch)
    'video_slug' => '',           // string, aus iframe-src ?video=
    'poster_url' => '',           // string, aus iframe-src ?poster=
    'titel'      => '',           // string, aus h5.videotitel
    'teaser'     => '',           // string, plain text aus div.teaser, mit datum entfernt
    'datum'      => '',           // string, "MM/YY"-Format (z.B. "11/25")
    'v_modus'    => '0',          // string, aus iframe-src ?v_modus= (default '0')
    'service'    => 'taxplain',   // string, aus iframe-src ?service= (TP) bzw. 'lexplain' (LP)
)
```

**LP-Override:** `DHPS_LP_Parser::parse()` ueberschreibt `service` in jedem
Video auf `'lexplain'` und `service_tag` auf `'lp'`.

### 1.4 TPT-Parser: einzelnes Video

```
array(
    'video'       => video_shape_oder_null,
    'service_tag' => 'tpt',
)
```

**Quirk:** TPT findet das erste `videoblock`-Element (NICHT
`videoblock_rubrik`), parsed es via `parent::parse_video_block()`. Das Video
hat dieselbe Shape wie TP-Videos. Bei leerem Input liefert TPT `'video' => null`.

### 1.5 LP-Parser: identisch zu TP mit Service-Patch

LP-Parser ruft `parent::parse()`, ersetzt dann:
- `$data['service_tag'] = 'lp'`
- `$data['featured_video']['service'] = 'lexplain'` (falls vorhanden)
- alle `$data['categories'][*]['videos'][*]['service'] = 'lexplain'`

LP-Parser-Output ist **strukturell identisch** zu TP, nur die `service`-
Properties tragen `'lexplain'` statt `'taxplain'`. Das hat KEINE Auswirkung auf
das Adapter-Mapping (nur das AJAX-Proxy-Routing).

### 1.6 TPT-Modules-Anreicherung

`DHPS_TPT_Modules::enrich_data()` registriert `add_filter(
'dhps_pipeline_data_tpt', ..., 10, 2 )`. Der Filter feuert **VOR** dem Adapter
(seit Pipeline-Patch v0.17.0, QA-Major-2). Im Adapter-Input ist daher
**immer** `$parsed_data['tpt_config']` mit:

```
array(
    'ueberschrift' => (string) get_option( 'dhps_tpt_ues', '' ),
    'teasertext'   => (string) get_option( 'dhps_tpt_teasertext', '' ),
)
```

**Wichtig:** `tpt_config` ist nur fuer TPT relevant. TP/LP haben kein
aequivalentes Modules-Layer (kein `dhps_pipeline_data_tp` Filter mit
Anreicherung).

---

## Sektion 2: Ziel-Datenmodell

### 2.1 TP/LP-Mapping-Tabelle

| Parser-Feld | DTO-Mapping | Begruendung |
|---|---|---|
| `featured_video` | -> 1 ContentItem `type='video'` mit `meta['is_featured']=true`, **PLUS** Collection-Meta `featured_video_id` (Lookup-Key) | Detail in 2.4 |
| `featured_video.video_id` | -> Item.`id` als `'{service}-video-featured-{video_id}'` (oder fallback `'{service}-video-featured'` wenn id leer) | Eindeutiger Lookup-Key plus Featured-Marker-Hint |
| `featured_video.titel` | -> Item.`title` (Pflichtfeld) | Direktes Mapping |
| `featured_video.teaser` | -> Item.`excerpt` | Plain-Text (DOM-bereinigt, kein wp_kses noetig) |
| `featured_video.poster_url` | -> Item.`image` als `{url, alt: title}` | poster_url ist Asset-URL |
| `featured_video.video_slug` + `v_modus` + `service` | -> Item.`media` als `{kind: 'video', slug, poster, params: {v_modus, mandantenvideo_service}}` | media-Asset-Vertrag |
| `featured_video.datum` | -> Item.`meta['datum']` (RAW "MM/YY") | Format-Decision laeuft via `DHPS_TP_Parser::format_datum()` im Template. KEIN `DateTimeImmutable` (Datum ist MM/YY ohne Tag - lassen sich nicht verlustfrei rehydraten) |
| `featured_video.video_id` | -> Item.`meta['video_id']` (Roh-ID fuer Pseudo-Rebuild) | Templates rekonstruieren ggf. Original-Shape |
| `categories[$idx].name` | -> Collection-Meta `categories_meta[$idx]['name']` | TP-Kategorien haben KEINE ID. Index ist der Bucket-Key |
| `categories[$idx].videos[]` | -> ContentItem `type='video'` mit `meta['is_featured']=false`, `category` = `(string) $idx` | Numerischer Index als category-Key |
| `categories[$idx].videos[].video_id` | -> Item.`meta['video_id']`, plus Item-`id` = `'{service}-video-{idx}-{sheet_id_or_running}'` | Eindeutige Item-ID |
| (alle Video-Felder analog) | wie featured_video | identische Video-Shape |

### 2.2 Collection-Meta-Felder (TP/LP)

```
array(
    'featured_video_id'  => string|null,         // Item.id des Featured-Videos, null wenn keins.
    'categories_order'   => array<int,string>,   // ['0', '1', '2'] - Bucket-Keys in Parser-Order.
    'categories_meta'    => array(
        '0' => array(
            'name'  => 'Rubrik-Name',
            'count' => 5,                        // Anzahl gemappter Videos (Skip-Bereinigt).
        ),
        // ...
    ),
    'total_videos'       => int,                 // alle gemappten Videos (inkl. Featured).
    'total_categories'   => int,                 // gemappte Kategorien (count > 0).
    'video_service'      => 'taxplain'|'lexplain', // aus erstem Video abgeleitet (AJAX-Proxy-Routing).
)
```

### 2.3 TPT-Mapping-Tabelle

| Parser-Feld | DTO-Mapping | Begruendung |
|---|---|---|
| `video` (single) | -> 1 ContentItem `type='video'` mit `meta['is_featured']=true`, `category=null` | TPT ist effektiv ein Featured-Video ohne Katalog |
| `video.video_id` | -> Item.`id` als `'{service}-video-teaser-{video_id}'` | Eindeutiger Marker fuer "TPT-Teaser" |
| (alle Video-Felder analog TP) | wie TP-featured_video | identische Video-Shape |
| `tpt_config.ueberschrift` | -> Collection-Meta `tpt_config['ueberschrift']` | Section-Heading vor der Card |
| `tpt_config.teasertext` | -> Collection-Meta `tpt_config['teasertext']` | Admin-Override des API-Teasers |

**TPT-Collection-Meta:**

```
array(
    'tpt_config'    => array(
        'ueberschrift' => '',  // string
        'teasertext'   => '',  // string
    ),
    'total_videos'  => 0|1,    // immer 0 oder 1
    'video_service' => 'taxplain',
)
```

Bei leerem `video` (kein Teaser-Video) liefert der Adapter eine LEERE Collection
mit `meta['tpt_config']` und `meta['total_videos']=0`. Templates sehen ihre
`$has_collection`-Bedingung erfuellt, finden aber `count()===0` und rendern den
EmptyState (analog Legacy-Verhalten ohne $video).

### 2.4 Wichtige Designentscheidungen

**Frage 1: featured_video als eigener Item-Type `featured`?**

Nein. Begruendung:

- `ALLOWED_TYPES` enthaelt aktuell `news, video, document, tax_date, generic`.
  Ein zusaetzlicher Type `featured` waere ein Schema-Bruch und wuerde
  Aenderungen in `DHPS_Content_Item::ALLOWED_TYPES` plus `DHPS_Content_Item::
  to_content_card_props()` erzwingen. Nicht noetig.
- ContentCard-Komponente kennt `featured` als CSS-Modifier
  (`dhps-tp-card--featured`), NICHT als type. Mapping ueber meta-Hash ist die
  saubere Loesung.

**Empfehlung:** Item-Type bleibt `video`, ein Marker im **Item-Meta**
(`is_featured: true`) und ein **Collection-Meta** `featured_video_id` (Lookup-
Key) sind die zwei orthogonalen Zugriffswege. Templates koennen sowohl per
`filter()` als auch per `featured_video_id` adressieren.

**Frage 2: Collection-Meta `is_featured=true` ODER `featured_video_id`-Lookup?**

**BEIDE.** Begruendung:

- `is_featured` auf Item-meta erlaubt Templates `$collection->filter(fn($i)
  => true === ($i->meta['is_featured'] ?? false))` - liefert die Featured-Items
  als Sub-Collection. Bei TP ist das genau 0-1 Item, bei zukuenftigen Multi-
  Featured-Services natuerlich skalierbar.
- `featured_video_id` als Collection-Meta erlaubt Templates direkten Lookup ohne
  Filter-Lauf - kanonisch fuer die Pseudo-Rebuild-Strategie.

**Frage 3: TP/LP - eine Klasse oder zwei?**

Eine. Begruendung:

- TP/LP-Parser haben STRUKTURELL identisches Output (LP-Parser ruft `parent::
  parse()`, patcht nur das `service`-Feld der Videos).
- Adapter ist service-agnostisch via `$service`-Param (analog MMB+MIL aus
  v0.17.1).
- Service-Tag `tp` vs `lp` entscheidet Branding-CSS-Wrapper, nicht Adapter-
  Logik.
- Spaetere Divergenz waere ueber Sub-Klasse `DHPS_LP_Adapter extends DHPS_TP_
  Adapter` jederzeit moeglich (Option B-Pfad offen).

**Frage 4: TPT - ueber TP-Adapter oder eigene Klasse?**

Eigene Klasse `DHPS_TPT_Adapter`. Begruendung:

- TPT-Parser-Output hat **strukturell anderen Top-Level** (`video` statt
  `featured_video` + `categories`).
- TPT hat `tpt_config`-Anreicherung, die TP/LP nicht hat.
- Eine gemeinsame Klasse braeuchte `if (isset($parser_output['video']))`-
  Verzweigungen - hoehere Komplexitaet, schlechtere Lesbarkeit.
- TPT-Klasse kann TP-Klasse fuer Video-Shape-Mapping nicht trivial wiederverwenden,
  weil `_video_to_item()`-Helpers entweder static werden muessen oder ueber ein
  Trait/Composition geteilt werden. Cost-Benefit-Analyse: kleine Duplikation
  (~30 LOC Video-Item-Bauteil) vs. saubere Typkonzept.

**Optional - aber empfohlen:** Beide Adapter teilen einen privaten
`build_video_item()`-Helper. Option fuer Implementation:

- **A)** TP-Adapter macht `protected function build_video_item()`, TPT-Adapter
  `extends DHPS_TP_Adapter` und ruft den Helper. - Eine Klassen-Hierarchie ohne
  Override der `adapt()`-Signatur.
- **B)** Beide Adapter implementieren eigenen `private build_video_item()`-
  Helper - Duplikation, aber 0 Coupling.

**Empfehlung:** **Option B (Duplikation)**. Begruendung:

- Adapter-Implementation in v0.17.0/v0.17.1 sind ALLE `final class` - kein
  extends. Konsistenz-Vorteil.
- Tests sind einfacher (kein Mock-Parent-Setup).
- Spaetere Divergenz (z.B. TPT bekommt Multi-Video-Support) braucht keinen
  Refactor.
- Duplikation ist ~30 LOC, beherrschbar.

Falls Lead den DRY-Vorteil staerker gewichtet, kann optional ein
**static helper** in einer dritten Klasse stehen:
`DHPS_TP_Video_Mapping::build_video_item( array $video_shape, string $service,
string $id_prefix ): DHPS_Content_Item`. Aber das ist Over-Engineering fuer
v0.17.2. **Default: Duplikation in Kauf nehmen.**

**Frage 5: Tax-Dates? News?**

Nein, TP/TPT/LP haben weder News noch Steuertermine. Mapping ist auf "video"-
Item-Type beschraenkt.

---

## Sektion 3: TPT-Modules-Anreicherung-Schema

### 3.1 Filter-Reihenfolge (Bestand seit v0.17.0)

Pipeline ruft `apply_filters( 'dhps_pipeline_data_tpt', $parsed_data, $layout )`
VOR dem Adapter-Aufruf. TPT-Modules registriert sich auf diesen Hook und packt
`tpt_config` in `$parsed_data`. Der Adapter sieht also bei jedem Aufruf:

```
$parser_output = array(
    'video'       => video_shape_or_null,
    'service_tag' => 'tpt',
    'tpt_config'  => array(
        'ueberschrift' => '',
        'teasertext'   => '',
    ),
);
```

### 3.2 Mapping-Strategie

**Empfehlung:** `tpt_config` 1:1 ins **Collection-Meta** unter dem gleichnamigen
Schluessel. Kein Mapping ins Item-Meta:

- `ueberschrift` ist ein Section-Heading **vor** der Card - das ist klar
  Collection-Level (1 Heading fuer N Items, hier eben 0-1 Items).
- `teasertext` ueberschreibt den API-Teaser - das koennte Item-Meta sein. Aber:
  TPT-Templates lesen den Wert direkt aus `$tpt_config['teasertext']` und
  setzen ihn am Item-Level (`$card_teaser`). Wenn der Wert ins Item-meta
  wandert, muss der Adapter entscheiden welches Item ihn bekommt - zusaetzliche
  Komplexitaet. Lieber im Collection-Meta belassen, Template entscheidet beim
  Card-Build.

### 3.3 Pseudo-Rebuild im Template

Template-Pseudo-Rebuild liest aus Collection-Meta:
```php
$tpt_config = (array) $collection->get_meta( 'tpt_config', array() );
$ueberschrift = (string) ( $tpt_config['ueberschrift'] ?? '' );
$teasertext   = (string) ( $tpt_config['teasertext'] ?? '' );
```

Das ist exakt das Pattern aus den heutigen TPT-Templates - 1:1 portierbar.

---

## Sektion 4: Strategie-Optionen TP/TPT/LP-Adapter

### 4.1 Optionen-Bewertung

**Option A: 3 separate Klassen**
`DHPS_TP_Adapter`, `DHPS_TPT_Adapter`, `DHPS_LP_Adapter`.

- Pro: maximale Trennung.
- Contra: 3 Klassen mit hoher Aehnlichkeit (TP+LP fast identisch), unnoetiger
  Footprint. LP-Adapter waere effektiv `class DHPS_LP_Adapter { /* delegates
  to TP_Adapter */ }`.

**Option B: 1 TP-Adapter + Sub-Klassen-Hierarchie**
`DHPS_TPT_Adapter extends DHPS_TP_Adapter`, `DHPS_LP_Adapter extends DHPS_TP_
Adapter`.

- Pro: code-reuse fuer Video-Item-Bauteil.
- Contra: TPT-Parser-Output hat anderes Top-Level - extends bringt nicht viel
  ausser geteiltem Helper. LP-Adapter ist sinnlos (kein Override notwendig).
  Adapter-Pattern in v0.17.0/v0.17.1 nutzen `final class` - Bruch der
  Konvention.

**Option C: 1 TP-Adapter fuer 2 Services (tp+lp), separater TPT-Adapter**

- Pro: konsistent mit MMB/MIL-Pattern aus v0.17.1 (Option B dort).
- Pro: TP-Adapter ist service-agnostisch -> LP ist 1 Zeile Registrierung.
- Pro: TPT bekommt eigene Klasse - reflektiert strukturellen Unterschied im
  Parser-Output.
- Pro: alle Klassen bleiben `final` (keine Vererbung).
- Contra: kleine Duplikation des Video-Item-Helpers zwischen TP/TPT-Adapter (~30
  LOC). Akzeptabel.

### 4.2 Empfehlung: **Option C**

**Begruendung:**

1. **Konsistenz mit v0.17.1:** Pattern "1 Adapter, N Services registriert"
   wurde gerade etabliert (MMB+MIL). TP+LP folgt demselben Muster sauber.
2. **Strukturelle Unterschiede:** TP/LP haben `featured_video + categories`,
   TPT hat `video` (single). Ein gemeinsamer Adapter wuerde Top-Level-Routing
   via `isset()` brauchen - Komplexitaet ohne Nutzen.
3. **`final`-Pattern:** Bestehende Adapter (MAES, MMB) sind `final class`. Sub-
   Klassen-Hierarchie waere Bruch.
4. **LP-Templates existieren NICHT:** LP nutzt TP-Templates ueber den
   `dhps_template_fallbacks`-Filter (lp -> tp). Wenn TP-Adapter aktiv ist,
   greift er automatisch fuer LP - nur eine Registry-Zeile.
5. **Tests:** Adapter-Klassen sind unabhaengig testbar, eigene Adapter-Datei
   liest sich klar.

### 4.3 Registrierung im Bootstrap

```php
// Nach den v0.17.1 MMB-Adapter-Calls in dhps_init():

// TP-Adapter (v0.17.2): wird sowohl fuer 'tp' als auch 'lp' registriert,
// weil LP den TP-Parser teilt (DHPS_LP_Parser extends DHPS_TP_Parser).
// Item-Service traegt 'tp' bzw. 'lp' entsprechend der Branding-Logik.
$tp_adapter = new DHPS_TP_Adapter();
DHPS_Content_Adapter_Registry::register( 'tp', $tp_adapter );
DHPS_Content_Adapter_Registry::register( 'lp', $tp_adapter );

// TPT-Adapter (v0.17.2): eigene Klasse, weil TPT-Parser-Output strukturell
// abweicht (single 'video' statt 'featured_video' + 'categories'). Adapter
// uebernimmt zusaetzlich das tpt_config aus dem dhps_pipeline_data_tpt-Filter
// ins Collection-Meta.
DHPS_Content_Adapter_Registry::register( 'tpt', new DHPS_TPT_Adapter() );
```

---

## Sektion 5: Template-Migration-Strategie

### 5.1 Mapping pro Template

| Template | Strategie | Begruendung |
|---|---|---|
| `tp/default.php` | **Pseudo-Rebuild** (analog MMB v0.17.1): aus Collection rekonstruiere `$featured` + `$categories`, danach Render-Code BYTEWISE unveraendert | Render-Code ist optimiert (Filter-Bar, Featured-Section, ContentList) - Risiko-arm |
| `tp/card.php` | **Pseudo-Rebuild** (analog): aus Collection rekonstruiere `$featured` + `$categories`, dann Render-Code unveraendert | wie default |
| `tp/compact.php` | **NICHT MIGRIEREN** (TD-V0172-1) | Bestaetigt Tech-Debt aus v0.14.3 - JS-spawn-Risiko (initCompactAccordion-Hook). Re-Bestaetigung in 5.2 |
| `tpt/default.php` | **Pseudo-Rebuild**: aus Collection `$video` + `$tpt_config` rekonstruieren | TPT-Templates sind kompakt (~150 Zeilen), saubere Migration |
| `tpt/card.php` | **Pseudo-Rebuild**: wie default | wie default |
| `tpt/compact.php` | **Pseudo-Rebuild**: wie default (anders als tp/compact KEIN JS-Spawn-Problem) | TPT-compact rendert nur 1 ContentCard ohne JS-Akkordeon |
| LP-Templates | **NICHT EXISTIEREND** | LP nutzt `dhps_template_fallbacks`-Filter (lp -> tp). Automatisch von TP-Patches abgedeckt |

### 5.2 TP-compact-Ausnahme-Begruendung (Re-Bestaetigung)

`tp/compact.php` rendert keine ContentCard - es baut **direkt** ein
`<ul.dhps-tp-compact__list>` mit `<li>`-Eintraegen, an denen JS-Selektoren wie
`.dhps-tp-compact__video-btn` und `data-video-slug`-Attribute haengen. Eine
Migration auf Collection mit Pseudo-Rebuild waere prinzipiell moeglich, aber:

- Der Render-Code ist anders strukturiert als default/card (keine ContentCard,
  kein ContentList).
- v0.14.3-Tech-Debt-Ticket war motiviert durch JS-Risiko bei initCompactAccordion-
  Refactor.
- v0.15.2 hat das Lazy-Akkordeon nicht-MMB-Templates verschoben (TP-compact ist
  *kein* Akkordeon-Service-Template wie MMB).

**Empfehlung:** TP-compact bleibt UNVERAENDERT, Tech-Debt-Ticket **TD-V0172-1**
fuer v0.17.x-Abschluss oder v0.18.0. Kein Risiko fuer v0.17.2-Release, weil
Templates ohne BC-Check funktionieren weiter (Collection wird ignoriert, Render
laeuft via `$data`).

### 5.3 Pseudo-Rebuild-Pattern fuer tp/default.php

Aktueller Kopf (Z. 47-49):
```php
$featured    = $data['featured_video'] ?? null;
$categories  = $data['categories'] ?? array();
$service_tag = $data['service_tag'] ?? 'tp';
```

Patch:
```php
// v0.17.2: Collection-Pfad wenn Adapter aktiv ist, sonst Legacy.
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
    // --- Featured-Video rekonstruieren. ---
    $featured             = null;
    $featured_id          = $collection->get_meta( 'featured_video_id', null );
    if ( null !== $featured_id ) {
        foreach ( $collection as $item ) {
            /** @var DHPS_Content_Item $item */
            if ( $item->id === $featured_id ) {
                $featured = self_rebuild_legacy_video_from_item( $item );
                break;
            }
        }
    }

    // --- Categories rekonstruieren in Parser-Order. ---
    $categories_order = (array) $collection->get_meta( 'categories_order', array() );
    $categories_meta  = (array) $collection->get_meta( 'categories_meta', array() );

    // Items je Bucket einsammeln (nur die Nicht-Featured-Videos).
    $items_by_category = array();
    foreach ( $collection as $item ) {
        /** @var DHPS_Content_Item $item */
        $is_featured = ! empty( $item->meta['is_featured'] );
        if ( $is_featured ) {
            continue;
        }
        $cat_idx = $item->category ?? '';
        if ( '' === $cat_idx ) {
            continue;
        }
        $items_by_category[ $cat_idx ][] = self_rebuild_legacy_video_from_item( $item );
    }

    $categories = array();
    foreach ( $categories_order as $cat_idx ) {
        $cat_meta = isset( $categories_meta[ $cat_idx ] ) && is_array( $categories_meta[ $cat_idx ] )
            ? $categories_meta[ $cat_idx ]
            : array();
        $categories[] = array(
            'name'   => isset( $cat_meta['name'] ) ? (string) $cat_meta['name'] : '',
            'videos' => isset( $items_by_category[ $cat_idx ] ) ? $items_by_category[ $cat_idx ] : array(),
        );
    }
} else {
    // Legacy-Pfad unveraendert.
    $featured   = $data['featured_video'] ?? null;
    $categories = $data['categories'] ?? array();
}

$service_tag = $data['service_tag'] ?? 'tp';
```

Plus eine **lokale** Helper-Funktion (oder Inline) zum Item -> Legacy-Video-Shape-
Rebuild. Spec sollte das so codieren, dass jedes Template eine private static
Helper-Funktion bekommt (oder eine kleine `dhps-tp-content-helpers.php`-Datei
neu legt mit gemeinsamem `dhps_tp_item_to_legacy_video()`-Helper).

**Empfehlung Spec:** **gemeinsamer Helper** in einer neuen Datei
`includes/dhps-tp-content-helpers.php`. 1 Funktion `dhps_tp_item_to_legacy_video(
DHPS_Content_Item $item ): array`. Nutzen: kein Code-Duplikat ueber 2 TP-Templates
+ 3 TPT-Templates. Datei wird im Bootstrap via `require_once` geladen (analog
`dhps-content-helpers.php`).

### 5.4 LP-Smoke (Trust-Decision)

LP-Templates existieren NICHT. LP nutzt TP-Templates via `dhps_template_
fallbacks`-Filter. Pipeline-Aufruf `render_service('lp', ...)` registriert TP-
Adapter -> Adapter ruft mit `$service='lp'`, schreibt Items mit `service='lp'`.
TP-Template rendert mit `card_service='lp'` (Z. 52: `'lp' === $service_tag ?
'lp' : 'tp'`). LP-Branding-CSS-Wrapper greift -> Recht-Blau.

**Pflichttest:** Frontend `[lp]` rendert HTML-bytewise zu v0.17.1.

---

## Sektion 6: Schema-Vertrag (verbindlich!)

Schema-Vertrag-Vorgehen ist 10x in Folge ohne Critical-Drift gelaufen.
**v0.17.2 = Iteration 11.** Disziplin halten.

### 6.1 TP-Adapter Item-Konstruktor-Signatur (featured_video)

```php
new DHPS_Content_Item(
    $id,             // '{service}-video-featured-{video_id_or_running}'
    $service,        // 'tp' ODER 'lp' (aus $service-Param)
    $title,          // (string) $featured['titel'], Pflicht
    'video',         // type
    '',              // body (leer)
    $excerpt,        // (string)|null aus $featured['teaser']
    $image,          // {url: poster_url, alt: title} ODER null
    $media,          // {kind: 'video', slug, poster, params: {v_modus, mandantenvideo_service}}
    null,            // link
    null,            // date (datum ist MM/YY - nicht DateTimeImmutable-faehig)
    array(),         // tags
    null,            // category (Featured-Videos haben keine Kategorie)
    $meta            // {is_featured: true, video_id, datum, v_modus, mandantenvideo_service}
);
```

### 6.2 TP-Adapter Item-Konstruktor-Signatur (categories[].videos[])

```php
new DHPS_Content_Item(
    $id,             // '{service}-video-{cat_idx}-{video_id_or_running}'
    $service,        // 'tp' ODER 'lp'
    $title,          // (string) $video['titel'], Pflicht
    'video',         // type
    '',              // body
    $excerpt,        // (string)|null aus $video['teaser']
    $image,          // {url: poster_url, alt: title} ODER null
    $media,          // {kind: 'video', slug, poster, params}
    null,            // link
    null,            // date
    array(),         // tags
    (string) $cat_idx, // category - numerischer Index als String
    $meta            // {is_featured: false, video_id, datum, v_modus, mandantenvideo_service, category_index, video_index}
);
```

### 6.3 TPT-Adapter Item-Konstruktor-Signatur (single video)

```php
new DHPS_Content_Item(
    $id,             // 'tpt-video-teaser-{video_id_or_running}'
    'tpt',           // service ('tpt' ist in ALLOWED_SERVICES)
    $title,          // (string) $video['titel']
    'video',         // type
    '',              // body
    $excerpt,        // (string)|null
    $image,          // {url, alt} ODER null
    $media,          // {kind: 'video', slug, poster, params}
    null,            // link
    null,            // date
    array(),         // tags
    null,            // category (TPT hat keine Kategorien)
    $meta            // {is_featured: true, video_id, datum, v_modus, mandantenvideo_service}
);
```

### 6.4 Meta-Felder-Vertrag (Items)

| Key | Typ | Pflicht | Quelle | Adapter |
|---|---|---|---|---|
| `is_featured` | bool | ja | true bei featured_video / tpt.video, false sonst | TP+TPT |
| `video_id` | string | ja | `$video['video_id']` (ggf. '') | TP+TPT |
| `datum` | string | nur wenn !== '' | `$video['datum']` (MM/YY) | TP+TPT |
| `v_modus` | string | ja | `$video['v_modus']` (default '0') | TP+TPT |
| `mandantenvideo_service` | string | ja | `$video['service']` (taxplain/lexplain) | TP+TPT |
| `category_index` | int | nur fuer kategoriebehaftete Items | `$cat_idx` | TP only |
| `video_index` | int | nur fuer kategoriebehaftete Items | running counter | TP only |

### 6.5 Collection-Meta-Felder fuer TP/LP

```php
array(
    'featured_video_id' => string|null,           // Lookup-Key oder null
    'categories_order'  => array<int,string>,     // ['0','1','2',...]
    'categories_meta'   => array(
        '0' => array( 'name' => '', 'count' => 0 ),
        // ...
    ),
    'total_videos'      => int,                   // Sum inkl. Featured
    'total_categories'  => int,                   // count > 0
    'video_service'     => string,                // 'taxplain' oder 'lexplain'
)
```

### 6.6 Collection-Meta-Felder fuer TPT

```php
array(
    'tpt_config'    => array(
        'ueberschrift' => string,
        'teasertext'   => string,
    ),
    'total_videos'  => int,                       // 0 oder 1
    'video_service' => string,                    // 'taxplain'
)
```

### 6.7 Force-Legacy fuer Edge-Cases?

**Keine Force-Legacy noetig** fuer TP/LP/TPT in v0.17.2. Begruendung:

- TP/LP haben keine Sub-Shortcode-Filter-Atts (`[mio_termine]`-aehnlich gibt
  es nicht).
- TPT hat keine Sub-Shortcode-Filter-Atts.
- Pipeline-Aufruf greift immer, Templates lesen via `$has_collection`-Check.

Falls in zukuenftigen Releases Sub-Shortcodes wie `[tp_videos kategorie="3"]`
eingefuehrt werden (Tech-Debt-Ticket bisher nicht existent), kommt das `Force-
Legacy`-Pattern aus v0.17.1 MAES-Modules dazu.

### 6.8 Adapter-Signatur (Standard)

```php
final class DHPS_TP_Adapter implements DHPS_Content_Adapter_Interface {
    public function adapt( array $parser_output, string $service ): DHPS_Content_Collection;
}

final class DHPS_TPT_Adapter implements DHPS_Content_Adapter_Interface {
    public function adapt( array $parser_output, string $service ): DHPS_Content_Collection;
}
```

Klassen-/Datei-Konvention (Autoloader-Pflicht):

| Klassenname | Dateipfad |
|---|---|
| `DHPS_TP_Adapter` | `includes/class-dhps-tp-adapter.php` |
| `DHPS_TPT_Adapter` | `includes/class-dhps-tpt-adapter.php` |

(Helper-Datei `includes/dhps-tp-content-helpers.php` folgt NICHT der Autoloader-
Konvention - explizit per `require_once` im Bootstrap geladen, analog zu
`dhps-content-helpers.php`.)

---

## Sektion 7: Acceptance-Kriterien T1-T15

### T1: TP-Adapter mit leerem Parser-Output

```php
$collection = ( new DHPS_TP_Adapter() )->adapt( array(), 'tp' );
```

Erwartet: `$collection->is_empty() === true`, `get_meta('total_videos') === 0`,
`get_meta('featured_video_id') === null`.

### T2: TP-Adapter mit featured_video allein

```php
$parsed = array(
    'featured_video' => array(
        'video_id' => '42', 'video_slug' => 'abc',
        'titel' => 'Featured', 'teaser' => 'desc',
        'poster_url' => 'http://p/1.jpg', 'datum' => '06/26',
        'v_modus' => '0', 'service' => 'taxplain',
    ),
    'categories' => array(),
);
$collection = ( new DHPS_TP_Adapter() )->adapt( $parsed, 'tp' );
```

Erwartet:
- `count() === 1`
- `first()->id === 'tp-video-featured-42'`
- `first()->type === 'video'`
- `first()->service === 'tp'`
- `first()->meta['is_featured'] === true`
- `first()->meta['video_id'] === '42'`
- `first()->category === null`
- `first()->media === ['kind'=>'video', 'slug'=>'abc', 'poster'=>'http://p/1.jpg', 'params'=>['v_modus'=>'0','mandantenvideo_service'=>'taxplain']]`
- `get_meta('featured_video_id') === 'tp-video-featured-42'`
- `get_meta('video_service') === 'taxplain'`

### T3: TP-Adapter mit categories

```php
$parsed = array(
    'featured_video' => null,
    'categories' => array(
        array(
            'name' => 'Rubrik A',
            'videos' => array(
                array( 'video_id' => '10', 'video_slug' => 's10', 'titel' => 'T10',
                       'teaser' => 'desc10', 'poster_url' => '', 'datum' => '',
                       'v_modus' => '0', 'service' => 'taxplain' ),
            ),
        ),
    ),
);
$collection = ( new DHPS_TP_Adapter() )->adapt( $parsed, 'tp' );
```

Erwartet:
- `count() === 1`
- `first()->category === '0'`
- `first()->meta['is_featured'] === false`
- `first()->meta['category_index'] === 0`
- `get_meta('categories_order') === ['0']`
- `get_meta('categories_meta')['0']['name'] === 'Rubrik A'`
- `get_meta('categories_meta')['0']['count'] === 1`

### T4: TP-Adapter mit LP-Service-Tag

Selber Parser-Output wie T3, aber `adapt($parsed, 'lp')`:

Erwartet:
- `first()->service === 'lp'`
- `first()->id === 'lp-video-0-10'`

### T5: TP-Adapter ueberspringt Videos ohne Title/Slug

Ein Video mit `'titel' => ''` UND `'video_slug' => ''` wird uebersprungen
(analog Parser-Filter Z. 287). Counter `total_videos` reflektiert das.

### T6: TPT-Adapter mit gueltigem Video

```php
$parsed = array(
    'video' => array(
        'video_id' => '99', 'video_slug' => 'tt', 'titel' => 'Teaser',
        'teaser' => '', 'poster_url' => '', 'datum' => '',
        'v_modus' => '0', 'service' => 'taxplain',
    ),
    'service_tag' => 'tpt',
    'tpt_config'  => array( 'ueberschrift' => 'UES', 'teasertext' => 'TEXT' ),
);
$collection = ( new DHPS_TPT_Adapter() )->adapt( $parsed, 'tpt' );
```

Erwartet:
- `count() === 1`
- `first()->id === 'tpt-video-teaser-99'`
- `first()->meta['is_featured'] === true`
- `get_meta('total_videos') === 1`
- `get_meta('tpt_config') === ['ueberschrift'=>'UES','teasertext'=>'TEXT']`

### T7: TPT-Adapter mit leerem Video (Empty-State)

```php
$collection = ( new DHPS_TPT_Adapter() )->adapt(
    array( 'video' => null, 'tpt_config' => array( 'ueberschrift' => '', 'teasertext' => '' ) ),
    'tpt'
);
```

Erwartet: `$collection->is_empty() === true`, `get_meta('tpt_config')` ist
gesetzt (Defaults).

### T8: TPT-Adapter ohne tpt_config (Fail-Soft)

```php
$collection = ( new DHPS_TPT_Adapter() )->adapt(
    array( 'video' => null /* kein tpt_config-Key */ ),
    'tpt'
);
```

Erwartet: `get_meta('tpt_config') === ['ueberschrift'=>'','teasertext'=>'']`
(Adapter setzt Defaults).

### T9: TP-Pipeline-Smoke - Frontend `[tp]` rendert HTML-bytewise

Vor- und nach-Migration: HTML-Diff zwischen v0.17.1 (ohne TP-Adapter) und
v0.17.2 (mit TP-Adapter aktiv) ist **0**. Pseudo-Rebuild im Template
garantiert das.

### T10: LP-Pipeline-Smoke - Frontend `[lp]` rendert HTML-bytewise

Wie T9 fuer LP. Bestaetigt dass LP-Pfad via geteiltem Adapter + Template-Fallback
klappt.

### T11: TPT-Pipeline-Smoke - Frontend `[tpt]` rendert HTML-bytewise

Wie T9 fuer TPT (alle 3 Layouts: default, card, compact).

### T12: TPT-Modules-Anreicherung sichtbar fuer Adapter

Spezial-Test: Verifiziere dass `$parser_output['tpt_config']` im TPT-Adapter
schon vorhanden ist (= Pipeline-Filter feuert VOR Adapter). Bei Aufruf mit Admin-
Texten gesetzt muss `get_meta('tpt_config')['ueberschrift']` der gesetzte Text
sein.

### T13: TP-compact UNVERAENDERT (BC-Smoke)

Frontend `[tp layout="compact"]` rendert bytewise identisch zu v0.17.1 (Template
nicht migriert). Trust-Decision TD-V0172-2 (= Re-Bestaetigung von TD aus
v0.14.3).

### T14: TP-Adapter mit Featured ohne video_id (Fallback-ID)

Featured-Video ohne `video_id`-Property: Item-ID muss `'tp-video-featured'`
(ohne `-{id}`) ODER `'tp-video-featured-0'` (Counter) sein - Spec waehlt einen
Ansatz. **Empfehlung:** `'tp-video-featured'` (kein Counter, weil immer max 1).

### T15: TP-Adapter mit Video ohne video_id (Fallback-ID per Counter)

Video in `categories[$idx]` ohne `video_id`: Item-ID muss
`'{service}-video-{cat_idx}-{running_counter}'` sein. Counter ist eindeutig
innerhalb des Bucket. Verhindert Item-ID-Kollisionen.

### Smoke-Tests fuer alle Layouts (Lead-Smoke)

- F1: Frontend `[tp]` (default) - bytewise gegen v0.17.1
- F2: Frontend `[tp layout="card"]` - bytewise gegen v0.17.1
- F3: Frontend `[tp layout="compact"]` - bytewise gegen v0.17.1 (NICHT
  migriert, aber Adapter-aktiv-Smoke pruefen)
- F4: Frontend `[lp]` (default) - bytewise gegen v0.17.1
- F5: Frontend `[lp layout="card"]` - bytewise gegen v0.17.1
- F6: Frontend `[tpt]` (default) - bytewise gegen v0.17.1
- F7: Frontend `[tpt layout="card"]` - bytewise gegen v0.17.1
- F8: Frontend `[tpt layout="compact"]` - bytewise gegen v0.17.1
- F9: Live-Preview im Admin-Dashboard fuer alle 3 Services + Layouts
- F10: AJAX-Proxy `dhps_get_tp_video_src` funktioniert unveraendert (TP+LP+TPT
  Video-Klick spawned iframe)

---

## Sektion 8: Spec-Aufteilung

### 8.1 Empfehlung: 2 Specialists + Lead-Direct

**Begruendung:** F1 (TP-Adapter + 2 TP-Templates + LP-Reg) und F2 (TPT-Adapter
+ 3 TPT-Templates) sind ENTKOPPELT - F2 braucht nichts von F1 ausser dem
gemeinsamen Helper `dhps-tp-content-helpers.php` (Lead-Direct als Phase-0-
Prerequisite). Beide Specialists nutzen die bereits gelandeten v0.17.0/v0.17.1-
Klassen (DHPS_Content_Item / DHPS_Content_Collection / DHPS_Content_Adapter_
Interface / DHPS_Content_Adapter_Registry).

### 8.2 F1: TP-Adapter + Template-Migration + LP-Registrierung

**Scope:**
- `includes/class-dhps-tp-adapter.php` (~150 LOC): TP_Adapter-Klasse.
- `public/views/services/tp/default.php` Pseudo-Rebuild (~40 LOC ergaenzt).
- `public/views/services/tp/card.php` Pseudo-Rebuild (~40 LOC ergaenzt).
- (TP-compact UNVERAENDERT - Tech-Debt-Bestaetigung.)
- Bootstrap-Patch `Deubner_HP_Services.php`: 3 Zeilen Adapter-Registry-Calls
  (`tp` + `lp`).
- F1-Tests: T1-T5, T9, T10, T13-T15.

**Pflicht-Lesematerial:**
- Discovery (dieses Doc, insbesondere Sektion 2 + 4 + 5 + 6).
- `includes/class-dhps-mmb-adapter.php` (Vorbild aus v0.17.1).
- `includes/class-dhps-content-item.php` + `class-dhps-content-collection.php`
  (Schema-Vertrag).
- `includes/parsers/class-dhps-tp-parser.php` + `class-dhps-lp-parser.php`.
- `public/views/services/tp/default.php` + `card.php` (heutige Templates).
- `public/views/services/mmb/default.php` (Pseudo-Rebuild-Vorbild).

**Aufwand:** M (mittel), ~280 LOC.

### 8.3 F2: TPT-Adapter + Template-Migration + tpt_config-Bridge

**Scope:**
- `includes/class-dhps-tpt-adapter.php` (~120 LOC): TPT_Adapter-Klasse.
- `public/views/services/tpt/default.php` Pseudo-Rebuild (~30 LOC ergaenzt).
- `public/views/services/tpt/card.php` Pseudo-Rebuild (~30 LOC ergaenzt).
- `public/views/services/tpt/compact.php` Pseudo-Rebuild (~30 LOC ergaenzt).
- Bootstrap-Patch `Deubner_HP_Services.php`: 1 Zeile Adapter-Registry-Call
  (`tpt`).
- F2-Tests: T6-T8, T11, T12.

**Pflicht-Lesematerial:**
- Discovery (Sektion 2.3 + 3 + 5).
- `includes/class-dhps-tpt-modules.php` (Filter-Anreicherung verstehen).
- `includes/parsers/class-dhps-tpt-parser.php`.
- `public/views/services/tpt/default.php` + `card.php` + `compact.php`.
- F1-Adapter-Resultat (fuer Konsistenz-Vergleich).

**Aufwand:** M (mittel-leicht), ~250 LOC.

### 8.4 Lead-Direct

- **Phase 0 (Lead, vor F1+F2):** `includes/dhps-tp-content-helpers.php`
  schreiben (~30 LOC, `dhps_tp_item_to_legacy_video()`-Helper). Bootstrap-Patch:
  `require_once`. Diese Datei ist Prerequisite fuer F1+F2.
- Bootstrap-Registration der 2 Adapter (3 Zeilen).
- Version-Bump `Deubner_HP_Services.php`, `README.md`.
- `docs/project/49-CHANGELOG-v0172.md`.
- MEMORY.md Milestone 20 + Implementation-Notes.

### 8.5 Phasen-Reihenfolge

```
Phase 0 (Lead):     dhps-tp-content-helpers.php (gemeinsamer Helper)
Phase 1 (parallel): F1 (TP-Adapter + tp/* + LP-Reg) + F2 (TPT-Adapter + tpt/*)
Phase 2 (Lead):     Bootstrap-Patches mergen (Adapter-Registry-Calls)
Phase 3 (parallel): QA-Smoke (T1-T15 + F1-F10) + SEC-Audit
Phase 4 (Lead):     Stage-Smoke, CHANGELOG, MEMORY, RC-Tag
```

### 8.6 Alternative: 1 grosser Spec (F12)

Nicht empfohlen. F1 und F2 sind unterschiedliche Adapter-Klassen mit
unterschiedlichen Parser-Inputs. Eng-Kopplung nur ueber den Helper (Phase 0).
Spec-Doc-Trennung erlaubt saubere Abnahme + Parallelisierung.

---

## Sektion 9: Risiken + Tech-Debt

### 9.1 Risiken-Matrix

| ID | Risiko | Severity | Mitigation |
|---|---|---|---|
| R1 | **Pseudo-Rebuild im Template fuehrt zu HTML-Drift** wenn der Adapter eine Video-Property vergisst (z.B. `v_modus`) | HIGH | Acceptance T9+T10+T11 bytewise. Sektion 6.1+6.2 sperrt das Item-Schema explizit. Helper `dhps_tp_item_to_legacy_video()` ist die EINZIGE Stelle wo das Rebuild lebt -> Eintrag testbar in isolation |
| R2 | **Featured-Video-Lookup via `featured_video_id`** schlaegt fehl wenn ID-Generierung im Adapter und im Template divergiert | MED | Adapter setzt `featured_video_id = $featured_item->id`. Template macht `$collection->get_meta('featured_video_id')` + Lookup. ID-String wird *nicht* im Template rekonstruiert. SCHEMA: ID-Generation lebt nur im Adapter |
| R3 | **TPT-`tpt_config` von einem Theme-Override des Filters geleert** | LOW | Pipeline-Filter wirkt VOR Adapter. Theme darf den Filter ueberschreiben -> Adapter sieht das leere Array -> Collection-Meta `tpt_config = ['','']`. Template-Render leer-toleriert (analog v0.14.5) |
| R4 | **TP-compact wird ueber Pipeline mit Adapter-Aktiv aufgerufen** und ignoriert `$collection` weil nicht migriert -> Adapter laeuft unnoetig | LOW | Performance-Impact mikrosekunden. Acceptance T13 bytewise-Smoke beweist BC. Tech-Debt TD-V0172-1 |
| R5 | **LP-Pipeline-Aufruf trifft TP-Templates** ueber template_fallbacks-Filter - wenn der Filter ausgeschaltet ist, knallt LP weil keine eigenen Templates existieren | LOW | Filter ist Bestand (seit v0.11.0). Smoke-Test F4+F5 verifiziert |
| R6 | **LP-Adapter erzeugt Items mit service='lp'** waehrend mandantenvideo_service='lexplain' bleibt - Verwirrungsrisiko | LOW | Sind 2 logische Felder. `service` ist Plugin-Branding, `mandantenvideo_service` ist API-Routing. Dokumentation im Adapter-Header |
| R7 | **Video-Datum als MM/YY-String** ohne DateTimeImmutable - schraenkt zukuenftige Sortier-/Filter-Operationen ein | LOW | Tech-Debt-Ticket TD-V0172-3 fuer v0.17.x-Abschluss (Datum als ISO-String mit Tag=1 normalisieren) |
| R8 | **TP-Adapter ID-Kollision** bei mehreren Videos ohne `video_id` in derselben Kategorie | LOW | Fallback per Running-Counter pro Bucket. Acceptance T15 |
| R9 | **MMB-Style-Refactor Tech-Debt TD-V0171-2 noch offen** - AJAX-Handler nutzt Parser-Output statt Collection. TP hat KEINEN AJAX-Handler -> Risiko irrelevant fuer v0.17.2 | n/a | TP-AJAX-Endpoint dhps_get_tp_video_src ist ein Iframe-Source-Resolver, kein Item-Render-Endpoint. Kein Vergleichsproblem |
| R10 | **Helper `dhps_tp_item_to_legacy_video()` global** = Namespace-Risiko bei Plugin-Konflikten | LOW | Funktion-Name hat `dhps_tp_`-Prefix, kollidiert nicht. Konsistent mit `dhps_build_collection_for()` v0.17.1 |

### 9.2 Tech-Debt-Tickets fuer v0.17.3+

| Ticket | Beschreibung | Zielversion |
|---|---|---|
| TD-V0172-1 | **TP-compact-Migration**: `tp/compact.php` auf Collection-Pseudo-Rebuild bringen (heute UNVERAENDERT). JS-spawn-Risiko muss geprueft werden | v0.17.x-Abschluss |
| TD-V0172-2 | **Datum-Normalisierung**: `'MM/YY'` -> ISO-8601-String (`YYYY-MM-01`), damit Adapter `DateTimeImmutable`-Konstruktor nutzen kann + Templates `DHPS_TP_Parser::format_datum()` nicht brauchen | v0.17.x-Abschluss |
| TD-V0172-3 | **MIO-Adapter** (v0.17.3 Roadmap) - News + Tax-Dates Sondertyp | v0.17.3 |
| TD-V0172-4 | **TC-Adapter** (v0.17.4 Roadmap) - Wrapper-Adapter, leere Collection + meta['html'] | v0.17.4 |
| TD-V0172-5 | **Static helper class `DHPS_TP_Video_Mapping`** statt globaler Funktion - falls Code-Style Audit es priorisiert | optional |
| TD-V0172-6 | **TPT-Adapter Filter `dhps_pipeline_collection_tpt`** wenn Themes die Collection-Anreicherung modifizieren wollen (analog TD-V0170-1) | v0.18.0 |

### 9.3 Autoloader-Notiz (Lehre aus v0.17.0+v0.17.1)

Klassen-/Datei-Konvention im Plugin: `class-dhps-foo-bar.php` -> `DHPS_Foo_Bar`.
F1 muss den Adapter als `DHPS_TP_Adapter` benennen und die Datei
`includes/class-dhps-tp-adapter.php` legen. F2 analog `DHPS_TPT_Adapter` ->
`includes/class-dhps-tpt-adapter.php`.

**Helper-Datei** `includes/dhps-tp-content-helpers.php`: Folgt NICHT der
Klassen-Konvention, daher Autoloader greift nicht. Lead-Phase-0 muss im
Bootstrap explizit `require_once DEUBNER_HP_SERVICES_PATH . 'includes/dhps-tp-content-helpers.php';`
hinzufuegen. Empfohlene Position: direkt nach dem `require_once` fuer
`dhps-content-helpers.php` (Z. 127 in `Deubner_HP_Services.php`).

---

## Sektion 10: Spec-Briefing-Material

### 10.1 Dateipfade fuer Neuschoepfung (F1)

```
includes/class-dhps-tp-adapter.php
```

### 10.2 Dateipfade fuer Neuschoepfung (F2)

```
includes/class-dhps-tpt-adapter.php
```

### 10.3 Dateipfade fuer Neuschoepfung (Lead Phase 0)

```
includes/dhps-tp-content-helpers.php
```

### 10.4 Dateipfade fuer Anpassung (Lead + F1 + F2)

```
Deubner_HP_Services.php
    - Version-Bump 0.17.1 -> 0.17.2 (Lead)
    - require_once includes/dhps-tp-content-helpers.php (Lead Phase 0)
    - DHPS_Content_Adapter_Registry::register( 'tp', $tp_adapter ) (Lead Phase 2)
    - DHPS_Content_Adapter_Registry::register( 'lp', $tp_adapter ) (Lead Phase 2)
    - DHPS_Content_Adapter_Registry::register( 'tpt', new DHPS_TPT_Adapter() ) (Lead Phase 2)
public/views/services/tp/default.php   (F1: Pseudo-Rebuild)
public/views/services/tp/card.php      (F1: Pseudo-Rebuild)
public/views/services/tpt/default.php  (F2: Pseudo-Rebuild)
public/views/services/tpt/card.php     (F2: Pseudo-Rebuild)
public/views/services/tpt/compact.php  (F2: Pseudo-Rebuild)
README.md                              (Lead: Version-Bump)
docs/project/49-CHANGELOG-v0172.md     (Lead: Release-Doku, NEU)
MEMORY.md                              (Lead: MILESTONE 20)
```

### 10.5 Bootstrap-Diff-Beispiel (Lead)

```php
// In Plugin-Bootstrap, Phase 0 (vor dhps_init), nach Z. 127:
require_once DEUBNER_HP_SERVICES_PATH . 'includes/dhps-tp-content-helpers.php';
```

```php
// In dhps_init(), nach Z. 321 (MMB+MIL-Adapter-Registrierung):

// TP-Adapter (v0.17.2): wird sowohl fuer 'tp' als auch 'lp' registriert,
// weil LP den TP-Parser teilt (DHPS_LP_Parser extends DHPS_TP_Parser).
// Der Adapter ist service-agnostisch - Item-Service traegt 'tp' bzw. 'lp'
// entsprechend des $service-Params, was Branding-CSS-Wrapper korrekt triggert.
$tp_adapter = new DHPS_TP_Adapter();
DHPS_Content_Adapter_Registry::register( 'tp', $tp_adapter );
DHPS_Content_Adapter_Registry::register( 'lp', $tp_adapter );

// TPT-Adapter (v0.17.2): eigene Klasse, weil der TPT-Parser-Output strukturell
// abweicht (single 'video' statt 'featured_video'+'categories'). Konsumiert das
// 'tpt_config'-Array, das das DHPS_TPT_Modules-Layer ueber den Pipeline-Filter
// 'dhps_pipeline_data_tpt' bereits eingespielt hat (Filter feuert VOR Adapter
// seit Pipeline-Patch v0.17.0).
DHPS_Content_Adapter_Registry::register( 'tpt', new DHPS_TPT_Adapter() );
```

### 10.6 Snippet: dhps_tp_item_to_legacy_video() Helper

```php
<?php
/**
 * Helper: rekonstruiert ein TP/TPT-Video-Legacy-Array aus einem ContentItem.
 *
 * Gegen-Mapping des TP/TPT-Adapter-Itemschemas. Wird in den Template-
 * Pseudo-Rebuild-Bloecken genutzt, damit der eigentliche Render-Code
 * unter der Linie BYTEWISE unveraendert bleibt.
 *
 * Schema-Vertrag siehe docs/architecture/28-TP-TPT-LP-ADAPTER-PLAN-v0172.md
 * Sektion 6.1-6.4.
 *
 * @since 0.17.2
 *
 * @param DHPS_Content_Item $item Item aus DHPS_TP_Adapter/DHPS_TPT_Adapter.
 *
 * @return array Legacy-Video-Shape (s. DHPS_TP_Parser::parse_video_block()).
 */
function dhps_tp_item_to_legacy_video( DHPS_Content_Item $item ): array {
    $media = is_array( $item->media ) ? $item->media : array();
    $params = isset( $media['params'] ) && is_array( $media['params'] ) ? $media['params'] : array();
    $meta   = is_array( $item->meta ) ? $item->meta : array();

    return array(
        'video_id'   => isset( $meta['video_id'] ) ? (string) $meta['video_id'] : '',
        'video_slug' => isset( $media['slug'] ) ? (string) $media['slug'] : '',
        'poster_url' => isset( $media['poster'] ) ? (string) $media['poster'] : '',
        'titel'      => $item->title,
        'teaser'     => null !== $item->excerpt ? $item->excerpt : '',
        'datum'      => isset( $meta['datum'] ) ? (string) $meta['datum'] : '',
        'v_modus'    => isset( $params['v_modus'] ) ? (string) $params['v_modus'] : '0',
        'service'    => isset( $params['mandantenvideo_service'] )
            ? (string) $params['mandantenvideo_service']
            : 'taxplain',
    );
}
```

### 10.7 Snippet: TP-Adapter Skeleton

```php
<?php
final class DHPS_TP_Adapter implements DHPS_Content_Adapter_Interface {

    public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {
        $items              = array();
        $categories_order   = array();
        $categories_meta    = array();
        $featured_video_id  = null;
        $total_videos       = 0;
        $video_service      = ( 'lp' === $service ) ? 'lexplain' : 'taxplain';
        $first_video_seen   = false;

        // --- Featured-Video. ---
        $featured = isset( $parser_output['featured_video'] ) && is_array( $parser_output['featured_video'] )
            ? $parser_output['featured_video']
            : null;

        if ( null !== $featured && ( ! empty( $featured['titel'] ) || ! empty( $featured['video_slug'] ) ) ) {
            $item = $this->build_video_item( $featured, $service, true, null, 0, 0 );
            if ( null !== $item ) {
                $items[]            = $item;
                $featured_video_id  = $item->id;
                ++$total_videos;
                if ( ! $first_video_seen ) {
                    $video_service    = isset( $featured['service'] ) && '' !== $featured['service']
                        ? (string) $featured['service']
                        : $video_service;
                    $first_video_seen = true;
                }
            }
        }

        // --- Categories. ---
        $categories = isset( $parser_output['categories'] ) && is_array( $parser_output['categories'] )
            ? $parser_output['categories']
            : array();

        foreach ( $categories as $cat_idx => $category ) {
            if ( ! is_array( $category ) ) {
                continue;
            }
            $cat_name = isset( $category['name'] ) ? (string) $category['name'] : '';
            $videos   = isset( $category['videos'] ) && is_array( $category['videos'] )
                ? $category['videos']
                : array();
            $cat_idx_str = (string) $cat_idx;

            $bucket_count = 0;
            foreach ( $videos as $video_idx => $video ) {
                if ( ! is_array( $video ) ) {
                    continue;
                }
                $item = $this->build_video_item(
                    $video, $service, false, $cat_idx_str, (int) $cat_idx, (int) $video_idx
                );
                if ( null !== $item ) {
                    $items[] = $item;
                    ++$bucket_count;
                    ++$total_videos;
                    if ( ! $first_video_seen ) {
                        $video_service    = isset( $video['service'] ) && '' !== $video['service']
                            ? (string) $video['service']
                            : $video_service;
                        $first_video_seen = true;
                    }
                }
            }

            if ( $bucket_count > 0 ) {
                $categories_order[]              = $cat_idx_str;
                $categories_meta[ $cat_idx_str ] = array(
                    'name'  => $cat_name,
                    'count' => $bucket_count,
                );
            }
        }

        $meta = array(
            'featured_video_id' => $featured_video_id,
            'categories_order'  => $categories_order,
            'categories_meta'   => $categories_meta,
            'total_videos'      => $total_videos,
            'total_categories'  => count( $categories_order ),
            'video_service'     => $video_service,
        );

        return new DHPS_Content_Collection( $service, $items, $meta );
    }

    private function build_video_item(
        array $video, string $service, bool $is_featured,
        ?string $category, int $cat_idx, int $video_idx
    ): ?DHPS_Content_Item {

        $title = isset( $video['titel'] ) ? (string) $video['titel'] : '';
        $slug  = isset( $video['video_slug'] ) ? (string) $video['video_slug'] : '';
        if ( '' === $title && '' === $slug ) {
            return null; // Parser-Konsistenz: leere Items skip.
        }
        if ( '' === $title ) {
            // ContentItem braucht non-empty Title - Fallback auf Slug oder Generic.
            $title = $slug;
        }

        $video_id  = isset( $video['video_id'] ) ? (string) $video['video_id'] : '';
        $poster    = isset( $video['poster_url'] ) ? (string) $video['poster_url'] : '';
        $teaser    = isset( $video['teaser'] ) ? (string) $video['teaser'] : '';
        $datum     = isset( $video['datum'] ) ? (string) $video['datum'] : '';
        $v_modus   = isset( $video['v_modus'] ) ? (string) $video['v_modus'] : '0';
        $api_svc   = isset( $video['service'] ) && '' !== $video['service']
            ? (string) $video['service']
            : ( 'lp' === $service ? 'lexplain' : 'taxplain' );

        // ID-Generation.
        if ( $is_featured ) {
            $id_tail = ( '' !== $video_id ) ? $video_id : 'main';
            $item_id = $service . '-video-featured-' . $id_tail;
        } else {
            $id_tail = ( '' !== $video_id ) ? $video_id : (string) $video_idx;
            $item_id = $service . '-video-' . $cat_idx . '-' . $id_tail;
        }

        $image = ( '' !== $poster )
            ? array( 'url' => $poster, 'alt' => $title )
            : null;

        $media = array(
            'kind'   => 'video',
            'slug'   => $slug,
            'poster' => $poster,
            'params' => array(
                'v_modus'                => $v_modus,
                'mandantenvideo_service' => $api_svc,
            ),
        );

        $meta = array(
            'is_featured'            => $is_featured,
            'video_id'               => $video_id,
            'v_modus'                => $v_modus,
            'mandantenvideo_service' => $api_svc,
        );
        if ( '' !== $datum ) {
            $meta['datum'] = $datum;
        }
        if ( ! $is_featured ) {
            $meta['category_index'] = $cat_idx;
            $meta['video_index']    = $video_idx;
        }

        return new DHPS_Content_Item(
            $item_id, $service, $title, 'video',
            '', ( '' !== $teaser ? $teaser : null ),
            $image, $media, null, null, array(),
            $category, $meta
        );
    }
}
```

### 10.8 Snippet: TPT-Adapter Skeleton

```php
<?php
final class DHPS_TPT_Adapter implements DHPS_Content_Adapter_Interface {

    public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {
        // tpt_config aus dem Pipeline-Filter (gesetzt VOR Adapter-Lauf).
        $tpt_config_raw = isset( $parser_output['tpt_config'] ) && is_array( $parser_output['tpt_config'] )
            ? $parser_output['tpt_config']
            : array();
        $tpt_config = array(
            'ueberschrift' => isset( $tpt_config_raw['ueberschrift'] ) ? (string) $tpt_config_raw['ueberschrift'] : '',
            'teasertext'   => isset( $tpt_config_raw['teasertext'] ) ? (string) $tpt_config_raw['teasertext'] : '',
        );

        $items         = array();
        $total_videos  = 0;
        $video_service = 'taxplain';

        $video = isset( $parser_output['video'] ) && is_array( $parser_output['video'] )
            ? $parser_output['video']
            : null;

        if ( null !== $video && ( ! empty( $video['titel'] ) || ! empty( $video['video_slug'] ) ) ) {
            $item = $this->build_teaser_item( $video, $service );
            if ( null !== $item ) {
                $items[]      = $item;
                ++$total_videos;
                $video_service = isset( $video['service'] ) && '' !== $video['service']
                    ? (string) $video['service']
                    : 'taxplain';
            }
        }

        $meta = array(
            'tpt_config'    => $tpt_config,
            'total_videos'  => $total_videos,
            'video_service' => $video_service,
        );

        return new DHPS_Content_Collection( $service, $items, $meta );
    }

    private function build_teaser_item( array $video, string $service ): ?DHPS_Content_Item {
        $title = isset( $video['titel'] ) ? (string) $video['titel'] : '';
        $slug  = isset( $video['video_slug'] ) ? (string) $video['video_slug'] : '';
        if ( '' === $title && '' === $slug ) {
            return null;
        }
        if ( '' === $title ) {
            $title = $slug;
        }

        $video_id = isset( $video['video_id'] ) ? (string) $video['video_id'] : '';
        $poster   = isset( $video['poster_url'] ) ? (string) $video['poster_url'] : '';
        $teaser   = isset( $video['teaser'] ) ? (string) $video['teaser'] : '';
        $datum    = isset( $video['datum'] ) ? (string) $video['datum'] : '';
        $v_modus  = isset( $video['v_modus'] ) ? (string) $video['v_modus'] : '0';
        $api_svc  = isset( $video['service'] ) && '' !== $video['service']
            ? (string) $video['service']
            : 'taxplain';

        $id_tail = ( '' !== $video_id ) ? $video_id : 'main';
        $item_id = 'tpt-video-teaser-' . $id_tail;

        $image = ( '' !== $poster )
            ? array( 'url' => $poster, 'alt' => $title )
            : null;

        $media = array(
            'kind'   => 'video',
            'slug'   => $slug,
            'poster' => $poster,
            'params' => array(
                'v_modus'                => $v_modus,
                'mandantenvideo_service' => $api_svc,
            ),
        );

        $meta = array(
            'is_featured'            => true,
            'video_id'               => $video_id,
            'v_modus'                => $v_modus,
            'mandantenvideo_service' => $api_svc,
        );
        if ( '' !== $datum ) {
            $meta['datum'] = $datum;
        }

        return new DHPS_Content_Item(
            $item_id, $service, $title, 'video',
            '', ( '' !== $teaser ? $teaser : null ),
            $image, $media, null, null, array(),
            null, $meta
        );
    }
}
```

### 10.9 Snippet: tp/default.php Pseudo-Rebuild

```php
// In tp/default.php direkt nach Z. 46 (vor $featured/$categories-Zuweisung):

$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
    $featured_id      = $collection->get_meta( 'featured_video_id', null );
    $categories_order = (array) $collection->get_meta( 'categories_order', array() );
    $categories_meta  = (array) $collection->get_meta( 'categories_meta', array() );

    $featured = null;
    $items_by_category = array();

    foreach ( $collection as $item ) {
        /** @var DHPS_Content_Item $item */
        if ( null !== $featured_id && $item->id === $featured_id ) {
            $featured = dhps_tp_item_to_legacy_video( $item );
            continue;
        }
        $cat_idx_item = $item->category ?? '';
        if ( '' === $cat_idx_item ) {
            continue;
        }
        $items_by_category[ $cat_idx_item ][] = dhps_tp_item_to_legacy_video( $item );
    }

    $categories = array();
    foreach ( $categories_order as $cat_idx_iter ) {
        $cat_meta_iter = isset( $categories_meta[ $cat_idx_iter ] ) && is_array( $categories_meta[ $cat_idx_iter ] )
            ? $categories_meta[ $cat_idx_iter ]
            : array();
        $categories[] = array(
            'name'   => isset( $cat_meta_iter['name'] ) ? (string) $cat_meta_iter['name'] : '',
            'videos' => isset( $items_by_category[ $cat_idx_iter ] ) ? $items_by_category[ $cat_idx_iter ] : array(),
        );
    }
} else {
    $featured   = $data['featured_video'] ?? null;
    $categories = $data['categories'] ?? array();
}

$service_tag = $data['service_tag'] ?? 'tp';

// AB HIER UNVERAENDERT (Z. 50+):
$card_service = ( 'lp' === $service_tag ) ? 'lp' : 'tp';
// ... bestehender Render-Code unveraendert ...
```

---

## Anhang: Lead-Briefing-Zusammenfassung

| Frage | Antwort |
|---|---|
| **Adapter-Strategie** | **Option C** - `DHPS_TP_Adapter` fuer (tp+lp), **separater** `DHPS_TPT_Adapter` fuer (tpt). Beide `final class`, kein extends. Konsistent mit MMB/MIL-Pattern aus v0.17.1 |
| **featured_video-Mapping** | **Item-Type `video` + Item-meta `is_featured=true`** PLUS **Collection-Meta `featured_video_id`** (Lookup-Key). Kein eigener Item-Type, ContentCard-Modifier bleibt `dhps-tp-card--featured` |
| **tpt_config-Mapping** | komplett ins Collection-Meta (`tpt_config`), 1:1 Schluessel. Templates lesen daraus, exakt wie heute aus `$data['tpt_config']`. Item-meta bleibt clean |
| **Spec-Aufteilung** | **2 Specialists parallel** - F1 (TP-Adapter + tp/default+card + LP-Reg) und F2 (TPT-Adapter + tpt/3 Templates). PLUS Lead-Phase-0 fuer gemeinsamen Helper `dhps_tp_item_to_legacy_video()` |
| **Templates** | `tp/default+card` migrieren, `tp/compact` UNVERAENDERT (Re-Bestaetigung TD aus v0.14.3), alle 3 TPT-Templates migrieren, LP nutzt TP-Templates ueber Fallback (automatisch mit) |
| **Top-3-Risiken** | R1 (Pseudo-Rebuild HTML-Drift), R2 (Featured-Lookup-ID-Divergenz Adapter/Template), R4 (TP-compact ignoriert Collection, bytewise BC pflichtig) |
| **Geschaetzter Aufwand** | **M (mittel)** - F1 ~280 LOC, F2 ~250 LOC, Lead Phase 0 ~30 LOC Helper + Phase 2 ~10 LOC Bootstrap + Doku ~200 LOC = ca. 800 LOC. 1 Discovery-Doc (dieses), 2 Spec-Docs, QA+SEC parallel |
| **Schema-Vertrag-Status** | Sektion 6 ist verbindlich. **11. Iteration in Folge** - Disziplin halten |
| **PHP-Minimum** | bleibt 8.1 (seit v0.17.0). Keine Aenderung |

**Risiko-Gegenmittel-Map:**

- R1: Bytewise-HTML-Smoke (T9+T10+T11+F1-F8) + Schema-Vertrag in Sektion 6.1+6.2
  sperrt das Item-Schema. Helper `dhps_tp_item_to_legacy_video()` ist die
  EINZIGE Rebuild-Stelle - isoliert testbar
- R2: ID-Generation lebt EXKLUSIV im Adapter (Sektion 10.7). Template macht
  `$collection->get_meta('featured_video_id')` und Lookup - rekonstruiert nie
  selbst eine ID. Acceptance T2 + T14 verifizieren
- R4: Acceptance T13 fordert TP-compact bytewise. Pseudo-Rebuild laeuft nicht
  (Template ignoriert `$collection`). Adapter-Performance-Overhead pro Aufruf
  laut v0.17.0-Acceptance T14 < 3ms - vertretbar

**Reihenfolge fuer Implementation:**

1. Phase 0 Lead: `dhps-tp-content-helpers.php` schreiben + Bootstrap-require.
2. Phase 1 parallel: F1 TP-Adapter + 2 Templates / F2 TPT-Adapter + 3 Templates.
3. Phase 2 Lead: Bootstrap-Patches mergen (3 Registry-Zeilen).
4. Phase 3 parallel: QA-Smoke (T1-T15 + F1-F10) + SEC-Audit (Schema-Drift-
   Check Frontend vs Live-Preview, Helper-XSS-Diagnose).
5. Phase 4 Lead: Stage-Smoke, CHANGELOG, MEMORY, RC-Tag.

**Wichtigste Frage beantwortet:** **EINE TP-Adapter-Klasse fuer 2 Services
(tp+lp), separate TPT-Adapter-Klasse**. Vererbungshierarchie wird **nicht**
gespiegelt - Adapter-Layer hat eigene Trennung nach **Parser-Output-Shape**,
nicht nach Parser-Klassen-Vererbung.

**Ende Discovery v0.17.2.**
