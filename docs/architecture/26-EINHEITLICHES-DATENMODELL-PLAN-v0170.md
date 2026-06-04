# 26 - Einheitliches Datenmodell - Plan v0.17.0

**Status:** Discovery (2026-06-04)
**Aktuelle Plugin-Version:** v0.16.3
**Ziel-Version:** v0.17.0
**Architekt-Auftrag:** Lang gehegter User-Wunsch seit Plugin-Anfang - jeder Parser liefert
heute eine service-spezifische Array-Struktur, Templates kennen jeweils nur "ihre" Struktur.
Das ist Tech-Debt aus den ersten Versionen. v0.17.0 fuehrt ein einheitliches DTO-System ein.

**Discovery-Empfehlung vorab (Kurzfassung):**

- **Strategie:** Option C - Adapter-Layer ueber bestehende Parser, BIDIREKTIONALER Datenfluss
  (Templates bekommen Legacy `$data` + neue `$collection`)
- **Pilot v0.17.0:** MAES (modernster Parser, nutzt ContentCard schon, hat 3 Sub-Shortcodes als
  Strukturtest, klare Item-Typen)
- **Spec-Aufteilung:** 2 Specialists - F1 DTO-Foundation + F2 MAES-Pilot
- **PHP-Minimum:** auf 8.1 anheben fuer `readonly`-Properties (siehe Sektion 8 + Lead-Briefing)
- **Aufwand:** M (mittel) - Foundation 250-400 LOC, Pilot 200-300 LOC, Pipeline 5-10 LOC

---

## Sektion 1: Ausgangsbasis (Bestandsaufnahme)

### 1.1 Parser-Inventar mit Top-Level-Keys

Bestandsaufnahme aller 9 produktiven Parser (LP/MAES/MIO/MMB/TC/TP/TPT) plus 2 AJAX-Parser
(MIO-News, MMB-Search). Reihenfolge: Top-Level-Keys -> Beispiel-Item-Struktur.

| Parser | Top-Level-Keys | Itemtyp pro Liste |
|---|---|---|
| `DHPS_MIO_Parser` | `tax_dates`, `search_config`, `ajax_params`, `service_tag` | `tax_dates[]`: `{title, entries: [{date, taxes: []}], footnote}` |
| `DHPS_MMB_Parser` | `categories`, `search_config`, `service_tag` | `categories[]`: `{id, name, icon_slug, fact_sheets: [{id, title, description, pdf_params}]}` |
| `DHPS_TP_Parser` | `featured_video`, `categories`, `service_tag` | `featured_video`: video-shape, `categories[]`: `{name, videos: [...]}` |
| `DHPS_TPT_Parser` (erbt TP) | `video`, `service_tag` | Einzelvideo - siehe TP-Videoshape |
| `DHPS_LP_Parser` (erbt TP) | wie TP, aber `service_tag=lp` und video.service=lexplain | wie TP |
| `DHPS_MAES_Parser` | `news`, `videos`, `merkblaetter`, `overview`, `service_tag` | `videos[]`: `{title, description, video_slug, poster_url, service}`, `merkblaetter[]`: `{title, description, pdf_params}`, `news[]`: `{id, title, teaser, body_html}`, `overview[]`: `{title}` |
| `DHPS_TC_Parser` | `html`, `is_empty`, `service_tag` | Wrapper - kein strukturierter Output |
| `DHPS_MIO_News_Parser` (AJAX) | `groups`, `pagination` | `groups[]`: `{name, articles: [{id, title, body_html, metadata, share_links}]}` |
| `DHPS_MMB_Search_Parser` (AJAX) | `results`, `total_count`, `query` | `results[]`: `{id, title, description, pdf_params}` |

**Video-Shape im Detail (TP/TPT/LP):**
```
['video_id', 'video_slug', 'poster_url', 'titel', 'teaser', 'datum', 'v_modus', 'service']
```

### 1.2 Felder die in MEHREREN Services vorkommen (DTO-Kandidaten)

| Logisches Feld | Quellen | Phys. Keys (heute) |
|---|---|---|
| **Titel** | alle ausser TC | `title` (MAES/MMB/News), `titel` (TP/TPT/LP), `name` (MMB-Kategorie), `month.title` (MIO) |
| **Body/Teaser-Text** | MAES, TP/TPT/LP, MMB, MIO-News | `description`, `teaser`, `body_html` |
| **Media (Video/PDF)** | MAES, TP/TPT/LP, MMB | `video_slug` + `poster_url` (Videos); `pdf_params` (Dokumente) |
| **ID** | MMB, MAES-News, MIO-News, TP | `id`, `video_id` |
| **Service-Slug** | implizit alle | `service_tag` (top-level), `service` (per Item bei TP/MAES) |
| **Datum** | TP/TPT/LP | `datum` ("MM/YY"-Format) |

### 1.3 Felder die service-spezifisch sind (Meta-Kandidaten)

| Feld | Service | Begruendung |
|---|---|---|
| `tax_dates[].entries[].taxes[]` | MIO | Steuertermin-Listen sind ein eigener Datentyp (Datum + Steuerarten) |
| `month.footnote` | MIO | Monats-Fussnote |
| `icon_slug` | MMB | Rubrik-Icon-Slug fuer Emoji-Mapping |
| `pdf_params` (merkblatt/header) | MMB, MAES | PDF-Download via AJAX-Proxy |
| `share_links` (email/twitter/facebook/...) | MIO-News | Social-Sharing |
| `metadata` (target/topic) | MIO-News | Zielgruppe + Thema |
| `v_modus` | TP-Videos | mandantenvideo.de Player-Modus |
| `is_empty`, `html` | TC | Wrapper-Parser ohne strukturierte Items |
| `ajax_params` | MIO | News-Container braucht AJAX-Params |
| `overview` | MAES | Reine Section-Titel ohne Body |

### 1.4 Wie Templates die Daten heute nutzen

**Konkrete Patterns:**

1. **MIO-Default** (`public/views/services/mio/default.php` Z. 37-77): direkter Array-Zugriff
   `$tax_dates`, `$ajax_params`, baut manuell `<dl>`-Markup, Steuertermine als
   `dhps-tax-dates__*` BEM-Klassen. Keine ContentCard-Nutzung (Items sind keine
   "Content-Karten"). News-Container ist nur AJAX-Mountpoint - clientseitiges Rendering.
2. **MAES-Default** (`public/views/services/maes/default.php`): Orchestrator-Shim,
   delegiert an Sub-Templates `videos.php`, `merkblaetter.php`, `aktuelles.php` via
   `include`. Sub-Templates transformieren manuell die Parser-Items in ContentCard-Props
   (siehe `videos.php` Z. 42-96: `foreach ($videos as $video) { $items[] = [...]; }`).
3. **MMB-Default** (`public/views/services/mmb/default.php` Z. 33-189): Lazy-Akkordeon mit
   `$categories`-Schleife, Markup ist BEM `.dhps-mmb-category__*`, baut Filter-Bar manuell
   (NICHT ueber Component `filter-bar`). Pre-Render-Filter `dhps_mmb_default_prerender_first_category`.
4. **TP/TPT/LP**: nutzen ContentCard (Component) via vergleichbarer manueller
   Transformations-Schleife wie MAES.
5. **TC**: gibt `echo $tc_html` direkt aus (Trust-Decision v0.13.0 + v0.14.4 unveraendert).

**Beobachtung:** In 4 von 9 Services existiert bereits die Transformation
*ParserOutput -> ContentCard-Props* als manuelle Schleife im Template. Das ist genau die
Stelle, die ein Adapter-Layer abstrahieren wuerde.

### 1.5 Wie weit ist das ContentCard-System schon ein DTO?

Das ContentCard-Prop-Schema (`public/views/components/content-card.php` Z. 11-26) ist
**bereits ein quasi-DTO**, allerdings als loses PHP-Array statt typisierte Klasse:

```
type, title, teaser, body_html, media_url, media_alt,
badges[], meta[], actions[], collapsible, class, service, data_attrs[]
```

**Bewertung:** Das ContentCard-Schema deckt das **Item-Level** schon zu ~70 % ab. Was fehlt:
- Kein typsicherer Vertrag (assoziatives Array statt Klasse)
- Keine **Collection**-Ebene (nur einzelne Items, kein Container mit Suche/Pagination/Service-Meta)
- Sondertypen wie `tax_dates` (MIO) oder `groups+articles` (MIO-News) passen nicht ins
  ContentCard-Korsett
- Service-Meta (z.B. `ajax_params`, `search_config`) hat keinen Platz

**Schlussfolgerung:** Das einheitliche Datenmodell muss zwei Ebenen liefern:
1. **`DHPS_ContentItem`** als typisierte Verallgemeinerung der ContentCard-Props
2. **`DHPS_ContentCollection`** als Container fuer Items + Service-/Collection-Meta

---

## Sektion 2: Ziel-Datenmodell

### 2.1 `DHPS_ContentItem` (DTO, immutable)

```php
final class DHPS_ContentItem {
    // --- Identitaet ---
    public readonly string $id;            // eindeutige Item-ID (z.B. "maes-video-3")
    public readonly string $service;       // Service-Tag fuer Branding (mio|mmb|tp|...)
    public readonly string $type;          // 'news' | 'video' | 'document' | 'tax_date' | 'generic'

    // --- Inhaltsdaten ---
    public readonly string $title;
    public readonly string $body;          // sanitized HTML (wp_kses_post) ODER plain
    public readonly ?string $excerpt;      // kurzer Teaser, plain text

    // --- Media ---
    public readonly ?array $image;         // {url, alt, width?, height?}
    public readonly ?array $media;         // {kind: video|pdf|external, slug?, url?, poster?, params?}

    // --- Verlinkung + Datum ---
    public readonly ?string $link;         // URL fuer "Mehr erfahren" oder Detail-Anker
    public readonly ?DateTimeImmutable $date;

    // --- Klassifizierung ---
    public readonly array $tags;           // string[]
    public readonly ?string $category;     // optionale Top-Level-Kategorie-ID

    // --- Service-spezifisches (Fluchtweg) ---
    public readonly array $meta;           // assoz. Array - service-spezifische Extras
    //                                       z.B. {pdf_params, share_links, v_modus, tax_entries}

    public function __construct(
        string $id,
        string $service,
        string $title,
        string $type = 'generic',
        string $body = '',
        ?string $excerpt = null,
        ?array $image = null,
        ?array $media = null,
        ?string $link = null,
        ?DateTimeImmutable $date = null,
        array $tags = [],
        ?string $category = null,
        array $meta = []
    ) { /* assign with validation */ }

    /**
     * Liefert Props in der Shape, die die ContentCard-Component
     * (public/views/components/content-card.php) versteht.
     */
    public function to_content_card_props(): array;

    /**
     * Liefert ein assoz. Array (Roundtrip-faehig fuer Cache/Tests).
     */
    public function to_array(): array;

    /**
     * Re-Hydration aus Array (fuer L2-Cache-Restore).
     */
    public static function from_array(array $data): self;
}
```

### 2.2 `DHPS_ContentCollection` (Collection, IteratorAggregate)

```php
final class DHPS_ContentCollection implements IteratorAggregate, Countable {
    public readonly string $service;       // Collection-weiter Service-Tag
    public readonly array $meta;           // Collection-Meta (search_config, ajax_params, pagination, ...)

    /** @var DHPS_ContentItem[] */
    private array $items;

    public function __construct(string $service, array $items = [], array $meta = []);

    public function add(DHPS_ContentItem $item): self;       // returns NEW instance (immutable)
    public function count(): int;
    public function is_empty(): bool;
    public function get_items(): array;                       // gibt Items as-is zurueck
    public function first(): ?DHPS_ContentItem;
    public function filter(callable $predicate): self;        // returns NEW instance
    public function group_by(string $key): array;             // [category|service|type|tag => Collection]
    public function get_meta(string $key, mixed $default = null): mixed;

    public function getIterator(): ArrayIterator;

    /**
     * Bequemlichkeits-Helper: alle Items als ContentCard-Props.
     */
    public function to_content_card_items(): array;

    public function to_array(): array;
    public static function from_array(array $data): self;
}
```

### 2.3 Wichtige Designentscheidungen

1. **Immutability via `readonly`**: Properties nicht aenderbar nach Construction. Methoden
   wie `add()` / `filter()` geben NEUE Instanzen zurueck.
2. **Meta-Hash als Fluchtweg** statt Sub-Klassen-Explosion fuer service-spezifische Daten
   (`pdf_params`, `tax_entries`, `share_links`, `v_modus`).
3. **`type`-Feld an Item-Level** ermoeglicht Templates "Was bist du?" zu fragen ohne
   Subklassen-Checks: `if ('video' === $item->type) ...`.
4. **`from_array()`/`to_array()`** erlaubt L2-Cache-Roundtrip ohne Custom-Serializer.
5. **`to_content_card_props()`** ist die Brueckenmethode zum bestehenden Component-System
   (Sektion 1.5).

---

## Sektion 3: Migration-Strategie - 4 Optionen

### 3.1 Optionen-Matrix

| Option | Beschreibung | BC-Risiko | Aufwand v0.17.0 | Endzustand-Qualitaet |
|---|---|---|---|---|
| **A) Big-Bang** | Alle 9 Parser + Templates auf einmal umstellen | **HOCH** (9 Services, 50+ Templates) | XL | Sauberster Endzustand |
| **B) Strict Universal DTO** | Rigid fixed-fields fuer alle Services | Hoch (Sonderdaten wie tax_dates passen nicht) | L | Hohe Konsistenz, aber Daten-Verlust |
| **C) Adapter-Layer (Inkrementell)** | Parser unveraendert, Adapter wandelt Output, Pipeline reicht beides weiter | **NIEDRIG** | M | Inkrementell, 2 Pfade temporaer parallel |
| **D) Hybrid mit Sub-DTOs** | Basis ContentItem + VideoItem extends + MerkblattItem extends ... | Mittel | L (mehr Klassen) | Type-safe, aber over-engineered |

### 3.2 Empfehlung: **Option C - Adapter-Layer**

**Begruendung:**

1. **Bewaehrtes Vorgehen:** Plugin hat seit v0.14.0 mit jedem Release inkrementell migriert
   (MAES -> MIO/LXMIO -> TP/TPT/LP -> TC). Die Erfolgsbilanz von 7 Release-Zyklen ohne BC-Bruch
   spricht fuer dieses Muster.
2. **0 BC-Bruch sofort:** Die bestehenden 9 Parser bleiben byte-identisch. Die bestehenden
   ~50 Templates bekommen weiterhin `$data`. Neues `$collection` kommt zusaetzlich dazu.
3. **Risiko kalkulierbar:** Im Pilot-Service (v0.17.0) zeigt sich ob das DTO-Modell
   alle Real-World-Strukturen abdeckt. Falls nicht - Anpassung VOR Roll-out auf weitere
   Services.
4. **Schema-Vertrag-Vorgehen passt:** Wie in v0.15.3-v0.15.5 - DTO-Schema vorab in
   Discovery sperren, beiden Specialists als Pflicht mitgeben. 0 Critical-Drift-Risiko.
5. **Option A** ist VS, hat aber das hoechste Risiko: 9 Parser + 50+ Templates auf einmal
   = enormes Test-Volumen.
6. **Option B** scheitert an MIO-`tax_dates` (Datum + Steuerarten ist kein "ContentItem")
   und MIO-News (`groups + articles`-Pagination).
7. **Option D** ist over-engineered fuer 7 Item-Typen - das `type`-Feld + `meta`-Hash
   reichen voellig aus, ohne 7 zusaetzliche Klassen.

### 3.3 Inkrementelle Roadmap nach Option C

| Release | Scope |
|---|---|
| **v0.17.0** | DTO-Foundation + Pilot MAES (3 Sub-Shortcodes: Videos, Merkblaetter, Aktuelles) |
| v0.17.1 | Pilot 2: MMB (Categories + Search) |
| v0.17.2 | Pilot 3: TP/TPT/LP (Videos + Featured) |
| v0.17.3 | MIO News + MIO-Tax-Dates (komplex - Tax-Dates evtl. eigener Item-Type) |
| v0.17.4 | TC (minimal - Wrapper-Adapter), Restelegacy-Cleanup |
| v0.18.0 | Optional: Legacy-Array-Pfad deprecated mit Notice |

---

## Sektion 4: Pilot-Service-Auswahl fuer v0.17.0

### 4.1 Kandidaten-Vergleich

| Kandidat | Pro | Contra |
|---|---|---|
| **MAES** | Modernste Parser-Klasse, schon ContentCard-Nutzung, 3 Sub-Templates testen Collection-Switch, klare Item-Typen (video/document/news), wp_kses_post bereits drin | Kein Search/Filter-Test fuer Collection-Meta |
| **MMB** | Search + Categories als Collection-Meta-Test, Lazy-Akkordeon-Sonderfall | Lazy-AJAX-Sonderfall macht Pilot komplex, MMB-Search-Parser ist eigene Klasse (extra Mapping) |
| **MIO** | Heterogenste Daten (tax_dates + news + search) - bester Stress-Test | tax_dates ist Sondertyp -> braucht entweder eigenen Item-Type oder Meta-Fluchtweg. News-Parser ist AJAX-only (extra Code-Pfad). Hohes Risiko, dass DTO-Schema sich noch aendern muss |

### 4.2 Empfehlung: **MAES als Pilot**

**Begruendung:**

1. **MAES nutzt schon ContentCard** - die Manuelle Transformation
   *parser-output -> card-props* in `videos.php` Z. 42-96 ist der ideale Ort, wo sich
   Adapter-Foundation bewaehrt: Bestehender Code wird ZU `to_content_card_props()`.
2. **3 Sub-Shortcodes (videos, merkblaetter, aktuelles)** sind ein gestaffeltes Stresstesting:
   - `videos[]` -> Item-Type `video` mit media{slug,poster}
   - `merkblaetter[]` -> Item-Type `document` mit media{pdf_params}
   - `news[]` -> Item-Type `news` mit body_html
   Drei verschiedene Item-Strukturen, alle in einem Parser-Output. Wenn die Foundation MAES
   verkraftet, sind die anderen Services strukturell einfacher.
3. **Klare Type-Felder im Item** - alle drei sind echte ContentItems mit
   title/body/media/link. Keine Sonderdaten wie `tax_dates`.
4. **MAES hat schon `id`** (z.B. `maes-news-{$index}`) - eindeutige IDs sind Pflichtfeld
   des DTOs, MAES erfuellt das schon.
5. **BC-Risiko minimal** - nur 3 Templates beruehrt (videos.php, merkblaetter.php, aktuelles.php).
   Default.php bleibt unveraendert als Orchestrator-Shim.

**MIO ist KEIN guter Pilot fuer v0.17.0** weil `tax_dates` womoeglich einen eigenen Item-Type
`tax_date` oder einen Meta-Fluchtweg braucht, dessen Schema noch nicht gesichert ist.
MIO sollte erst spaeter portiert werden, NACHDEM die DTO-Foundation in MAES validiert wurde.

---

## Sektion 5: Schema-Vertrag (verbindlich)

**WICHTIG:** Schema-Vertrag-Vorgehen ist 7x in Folge ohne Critical-Drift (v0.15.0/v0.15.3/
v0.15.4/v0.15.5/v0.16.0/v0.16.1/v0.16.2) gelaufen - hier MUSS es klappen, weil das DTO
**das Vertragselement** ist.

### 5.1 `DHPS_ContentItem` Feld-Vertrag

| Feld | Typ | Nullable | Default | Validierung im Konstruktor |
|---|---|---|---|---|
| `id` | `string` | nein | - | Pflicht, nicht leer, max 128 Zeichen |
| `service` | `string` | nein | - | Pflicht, `sanitize_key()`, max 32 Zeichen, in ALLOWED_SERVICES |
| `title` | `string` | nein | - | Pflicht, nicht leer (sonst InvalidArgumentException), max 500 Zeichen |
| `type` | `string` | nein | `'generic'` | in `{news, video, document, tax_date, generic}` |
| `body` | `string` | nein | `''` | wp_kses_post wird **NICHT** im Konstruktor gemacht - Erzeuger ist verantwortlich |
| `excerpt` | `?string` | ja | `null` | plain text |
| `image` | `?array` | ja | `null` | shape `{url: string, alt: string, width?: int, height?: int}` |
| `media` | `?array` | ja | `null` | shape `{kind: video\|pdf\|external, slug?: string, url?: string, poster?: string, params?: array}` |
| `link` | `?string` | ja | `null` | esc_url-fähige URL ODER Anchor (`#xyz`) |
| `date` | `?DateTimeImmutable` | ja | `null` | - |
| `tags` | `array` | nein | `[]` | string[], jedes Tag max 50 Zeichen |
| `category` | `?string` | ja | `null` | max 128 Zeichen |
| `meta` | `array` | nein | `[]` | beliebige assoz. Keys, JSON-encode-fähig |

**Konstruktor-Validierung:**
- `InvalidArgumentException` bei leerem `$id`, leerem `$title`, leerem `$service`
- `InvalidArgumentException` bei unbekanntem `$type`
- Stille Korrektur bei out-of-range Strings (truncate auf max-Laenge)

### 5.2 `DHPS_ContentCollection` Method-Signaturen

```php
public function __construct(string $service, array $items = [], array $meta = []);
public function add(DHPS_ContentItem $item): self;        // returns NEUE Instanz
public function count(): int;                              // = count($this->items)
public function is_empty(): bool;                          // = 0 === count
public function first(): ?DHPS_ContentItem;
public function get_items(): array;                        // gibt ContentItem[] zurueck
public function get_meta(string $key, mixed $default = null): mixed;
public function filter(callable $predicate): self;         // returns NEUE Instanz
public function group_by(string $key): array;              // ['category-x' => Collection, ...]
public function to_content_card_items(): array;            // [['type'=>...,'title'=>...], ...]
public function to_array(): array;                         // ['service'=>..., 'items'=>[], 'meta'=>[]]
public static function from_array(array $data): self;
public function getIterator(): ArrayIterator;
```

**`group_by()` erlaubte Keys:** `'category'`, `'type'`, `'service'` (Whitelist - sonst
InvalidArgumentException).

### 5.3 `DHPS_Content_Adapter_Interface`

```php
interface DHPS_Content_Adapter_Interface {

    /**
     * Wandelt Parser-Output in eine ContentCollection.
     *
     * @param array  $parsed_data  Parser-Output (Legacy-Array).
     * @param string $service_tag  Service-Tag fuer Item-Service-Feld.
     * @return DHPS_ContentCollection
     */
    public function adapt(array $parsed_data, string $service_tag): DHPS_ContentCollection;
}
```

Zusaetzlich: Static Factory-Klasse fuer Resolver:

```php
class DHPS_Content_Adapter_Registry {
    /** @var array<string, DHPS_Content_Adapter_Interface> */
    private static array $adapters = [];

    public static function register(string $service_tag, DHPS_Content_Adapter_Interface $adapter): void;
    public static function get(string $service_tag): ?DHPS_Content_Adapter_Interface;
    public static function has(string $service_tag): bool;
    public static function for_service(string $service_tag): ?DHPS_Content_Adapter_Interface; // Alias get()
    public static function reset(): void;  // Tests
}
```

### 5.4 Pipeline-Integration

**Anpassung in `class-dhps-content-pipeline.php`** (Punkt 4-5 der `render_service()`-Methode):

```php
// Nach Parser-Lauf (oder L2-Cache-Hit):
$parsed_data['service_tag'] = $tag;

// NEU v0.17.0: Optionaler Adapter -> ContentCollection
$collection = null;
$adapter    = DHPS_Content_Adapter_Registry::for_service( $tag );
if ( null !== $adapter ) {
    try {
        $collection = $adapter->adapt( $parsed_data, $tag );
    } catch ( Throwable $e ) {
        // Fail-Soft: Adapter-Fehler darf Legacy-Pfad nicht brechen.
        $collection = null;
        // Optional: error_log fuer Debug.
    }
}

// Renderer bekommt beides.
return $this->renderer->render_parsed( $parsed_data, $tag, $layout, $css_class, $collection );
```

**Renderer-Signatur erweitern** (`class-dhps-renderer.php`):

```php
public function render_parsed(
    array $data,
    string $tag,
    string $layout,
    string $css_class,
    ?DHPS_ContentCollection $collection = null   // NEU, optional, BC-erhaltend
): string;
```

Im Renderer-Template-Include werden BEIDE Variablen verfuegbar gemacht:
```php
// Template-Scope:
// - $data        (array)  - Legacy-Pfad, immer vorhanden
// - $collection  (DHPS_ContentCollection|null) - NEU, kann null sein
// - $service_class, $layout_class, $custom_class - wie bisher
```

### 5.5 Template-Iteration ueber Collection

```php
<?php if ( null !== $collection && ! $collection->is_empty() ) : ?>
    <ul class="dhps-list">
        <?php foreach ( $collection as $item ) : /* @var DHPS_ContentItem $item */ ?>
            <li><?php echo esc_html( $item->title ); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
```

**Zugriff auf service-Meta:**
```php
$ajax_params = $collection->get_meta( 'ajax_params', [] );
```

**Brueckenmethode zu ContentCard:**
```php
echo dhps_component( 'content-list', [
    'id'    => 'maes-videos',
    'items' => $collection->to_content_card_items(),
    // ...
] );
```

### 5.6 Backward-Compatibility

| Szenario | Verhalten |
|---|---|
| Template nutzt nur `$data` (alle bestehenden Templates) | Funktioniert unveraendert |
| Template nutzt `$collection`, aber Service hat keinen Adapter | `$collection === null`, Template muss `null`-Check machen |
| Adapter wirft Exception | Pipeline faengt ab (`try/catch`), `$collection = null`, Legacy-Pfad funktioniert |
| Filter `dhps_pipeline_data_{tag}` (bestehend) | Wirkt weiterhin auf `$data` VOR Adapter-Lauf - additiv kompatibel |
| L2-Cache | cached weiterhin **nur** `$parsed_data` (Legacy-Array). Adapter laeuft auf jedem Request frisch (CPU-cheap, vermeidet Cache-Schema-Migration) |

**Begruendung Cache-Strategie:** Adapter ist deterministisch fuer gegebene Input-Daten,
daher nicht-cachen unkritisch (mikrosekunden-Operation). Cachen wuerde **Schema-Migration**
fuer alle Bestands-Caches erzwingen.

---

## Sektion 6: Acceptance-Kriterien

| ID | Test | Erwartet |
|---|---|---|
| T1 | `new DHPS_ContentItem('id', 'maes', 'Title')` | Instanz erfolgreich, `type === 'generic'` |
| T2 | `new DHPS_ContentItem('', 'maes', 'Title')` | `InvalidArgumentException` (id leer) |
| T3 | `new DHPS_ContentItem('id', 'maes', '')` | `InvalidArgumentException` (title leer) |
| T4 | `new DHPS_ContentItem('id', 'maes', 'T', 'badtype')` | `InvalidArgumentException` (type ungueltig) |
| T5 | `$item->title = 'new'` (oder via Reflection-Bypass) | TypeError (PHP-8.1-readonly) |
| T6 | `DHPS_ContentCollection` mit 3 Items + Iteration | foreach iteriert 3-mal, $item ist ContentItem |
| T7 | `DHPS_Content_Adapter_Registry::for_service('maes')` | Liefert `DHPS_MAES_Adapter`-Instanz |
| T8 | `DHPS_Content_Adapter_Registry::for_service('unknown')` | `null` |
| T9 | `$adapter->adapt($parsed_data, 'maes')` mit MAES-Output | `DHPS_ContentCollection` mit `count() === videos+merkblaetter+news` |
| T10 | Pipeline liefert sowohl `$data` als auch `$collection` an Template | beide Variablen im Template-Scope verfuegbar |
| T11 | Pilot-Template `maes/videos.php` rendert via `$collection->to_content_card_items()` | HTML strukturell identisch zur Legacy-Version |
| T12 | BC: nicht-migriertes Template (`mio/default.php`) unveraendert lauffaehig | rendert wie vor v0.17.0 |
| T13 | HTML-Output bytewise identisch fuer **nicht** migrierte Templates (Diff: 0) | Snapshot-Test |
| T14 | Performance: Render-Zeit pro Service-Render < 3 ms zusaetzlich fuer Adapter-Lauf | gemessen via `microtime()` in Pipeline |
| T15 | Memory: Adapter-Output deallokiert sich nach Request (kein Leak) | Memory-Profile-Diff < 50 KB |
| T16 | `$item->to_array()` -> `DHPS_ContentItem::from_array()` Roundtrip ist verlustfrei | Equality-Check |
| T17 | `$collection->group_by('type')` liefert `['video'=>Coll, 'document'=>Coll, 'news'=>Coll]` | Korrekte Gruppierung mit 3 Sub-Collections |
| T18 | Adapter wirft Exception -> Pipeline-Try/Catch greift -> `$collection === null` | Template ohne Crash |

**Smoke-Tests fuer MAES-Pilot:**
- F1: Frontend `[maes_videos]` rendert bytegleich zu v0.16.3
- F2: Frontend `[maes_merkblaetter]` rendert bytegleich zu v0.16.3
- F3: Frontend `[maes_aktuelles]` rendert bytegleich zu v0.16.3
- F4: Frontend `[maes]` (default) rendert bytegleich zu v0.16.3
- F5: Live-Preview im Admin-Dashboard funktioniert fuer alle 4 MAES-Shortcodes

---

## Sektion 7: Spec-Aufteilung Implementation

### 7.1 Empfehlung: 2 Specialists + Lead-Direct

#### **F1: DTO-Foundation** (250-400 LOC)

**Scope:**
- `includes/class-dhps-content-item.php` (~150 LOC): DTO-Klasse, Konstruktor + Validierung,
  `to_content_card_props()`, `to_array()`, `from_array()`
- `includes/class-dhps-content-collection.php` (~120 LOC): Collection-Klasse,
  IteratorAggregate, alle Methoden aus Sektion 5.2
- `includes/class-dhps-content-adapter-interface.php` (~30 LOC): Interface aus Sektion 5.3
- `includes/class-dhps-content-adapter-registry.php` (~80 LOC): Static Registry, register/get/has/reset

**Pflicht-Lesematerial:**
- Sektion 5 dieses Dokuments (Schema-Vertrag)
- `includes/class-dhps-parser-registry.php` (Vorbild-Pattern fuer Registry)
- `includes/class-dhps-component-registry.php` (zweites Registry-Vorbild)

**Acceptance:** T1-T8, T16, T17 aus Sektion 6.

#### **F2: MAES-Pilot-Adapter + Template-Migration** (200-300 LOC)

**Scope:**
- `includes/adapters/class-dhps-maes-adapter.php` (~150 LOC): Implementiert
  `DHPS_Content_Adapter_Interface`, wandelt MAES-Parser-Output (`videos[]`, `merkblaetter[]`,
  `news[]`) in ContentCollection mit gemischten Item-Types
- Registration in Plugin-Bootstrap (`Deubner_HP_Services.php`) - 5-10 LOC
- Migration `public/views/services/maes/videos.php` auf `$collection`-Pfad mit Fallback
  zu `$data` falls null (~50 LOC angepasst)
- Migration `public/views/services/maes/merkblaetter.php` analog (~50 LOC)
- Migration `public/views/services/maes/aktuelles.php` analog (~50 LOC)
- Snapshot-Tests `tests/test-maes-adapter.php` (Roundtrip + Bytewise-HTML-Diff vs Legacy)

**Pflicht-Lesematerial:**
- F1-Output (DTO-Klassen)
- `includes/parsers/class-dhps-maes-parser.php`
- `public/views/services/maes/videos.php` (Legacy-Transformation als Vorbild)
- Sektion 5 dieses Dokuments

**Acceptance:** T9-T15, T18 + Smoke F1-F5 aus Sektion 6.

#### **Lead-Direct**

- Pipeline-Patch `includes/class-dhps-content-pipeline.php` (~10 LOC, Sektion 5.4)
- Renderer-Patch `includes/class-dhps-renderer.php` (~5 LOC, Signatur-Erweiterung)
- Bootstrap-Registration der Adapter-Registry-Klassen
- `docs/architecture/02-CONTENT-PIPELINE.md` Update mit DTO-Layer
- `docs/team-knowledge/CHANGELOG-v0170.md` + Trust-Decisions
- MEMORY.md Update

### 7.2 Alternative: 1 grosser Spec

Nur empfohlen wenn Lead F1+F2 als eng gekoppelt bewertet. Risiko: schwerer zu QA-en,
1 Specialist hat beide Verantwortungen. **Empfehlung bleibt 2 Specialists** wegen sauberer
Abnahme-Punkte und Parallelisierungs-Potenzial (F1 parallel zu Pipeline-Patch im Lead).

### 7.3 Phasen-Reihenfolge

```
Phase 1 (parallel):  F1 (DTO-Foundation)  +  Lead (Pipeline-Patch in temp branch)
Phase 2 (sequenz.):  F2 (MAES-Adapter+Templates) - benoetigt F1-Output
Phase 3 (parallel):  QA + SEC-Audit
Phase 4 (Lead):      Doku + Release-RC
```

---

## Sektion 8: Trust-Decisions

| ID | Annahme | Begruendung | Akzeptiert? |
|---|---|---|---|
| TD-1 | **Immutable DTO via `readonly`** statt Setter/Getter | PHP 8.1 hat `readonly`; Eliminiert Mutations-Bugs; Type-System-erzwungen | ja, **erfordert PHP-8.1-Bump** |
| TD-2 | **PHP-Minimum auf 8.1 anheben** (von 8.0) | PHP 8.0 EOL war 2023-11, kein realistischer Block; Plugin laeuft schon auf 8.3.30 | empfohlen, Lead-Entscheidung |
| TD-3 | **Meta als untypiertes Array** statt typed Sub-DTOs | Fluchtweg fuer service-spezifische Daten (pdf_params, share_links); over-engineering vermeiden | ja |
| TD-4 | **Collection ist eager** (kein Generator) | Items 60-300 pro Service - klein genug fuer eager; Generators verkomplizieren `count()`, `group_by()`, `first()` | ja |
| TD-5 | **Empty-State = leere Collection** (nicht null) | Konsistente API: Template kann immer `$collection->is_empty()` aufrufen | ja |
| TD-6 | **Adapter pro Service** (Klasse), nicht via Filter-Hook | Vorbild: `DHPS_Parser_Registry`; Filter-Hook waere lose, Klassen sind testbar | ja |
| TD-7 | **L2-Cache cached nur `$parsed_data`** (Legacy-Array) | Adapter ist cheap, vermeidet Cache-Schema-Migration | ja |
| TD-8 | **Pipeline reicht beides weiter** (`$data` UND `$collection`) | Inkrementelle Migration ohne BC-Bruch; Templates entscheiden selbst | ja |
| TD-9 | **Adapter-Exception ist Fail-Soft** | Try/Catch in Pipeline, `$collection = null`, Legacy-Pfad rettet die Seite | ja |
| TD-10 | **`body`-HTML wird NICHT vom Konstruktor gesanitized** | Sanitization-Entscheidung gehoert in den Adapter; Konstruktor bleibt seiteneffekt-frei | ja |
| TD-11 | **Pflichtfelder via Konstruktor-Exception** (nicht Soft-Default) | Frueher Fehlerfall ist besser als unbemerkte leere Items im Frontend | ja |
| TD-12 | **`group_by()` mit Whitelist** | category/type/service - sonst Exception. Verhindert beliebige Meta-Key-Zugriffe | ja |
| TD-13 | **Pilot MAES** statt MIO oder MMB | MAES hat 3 Item-Typen + nutzt ContentCard schon - bester Erstvalidator | ja |
| TD-14 | **`type=tax_date` als Item-Type fuer MIO-Tax-Dates VORBEHALTEN** | Erst in v0.17.3 entscheiden, wenn MIO migriert wird; Foundation aendern wenn noetig | ja (offen bis v0.17.3) |

---

## Sektion 9: Risiken + Tech-Debt

| ID | Risiko | Severity | Mitigation |
|---|---|---|---|
| R1 | **BC-Bruch wenn migriertes Template `$data` verliert** und das ausserhalb des Adapters genutzte Felder erwartet | HIGH | Pipeline reicht IMMER `$data` mit; Adapter LIEST nur, ueberschreibt nichts |
| R2 | **Performance bei grossen Collections** (TP/MAES bis 60 Videos) | MED | Acceptance T14: < 3ms zusaetzlich. Adapter ist O(n) ohne IO. Wenn ueberschreitet -> Lazy-Generator-Option ueberdenken |
| R3 | **Memory-Doppel-Allocation** (Legacy-Array + Collection) | LOW | Items sind klein (~1 KB), 60 Items = ~60 KB Overhead. Acceptance T15: < 50 KB Diff (Items teilen sich `service`-String) |
| R4 | **Adapter-Falschmapping** (z.B. `tax_date` als generic ContentItem mit date-Feld?) | MED | Acceptance T17 Snapshot-Test; Pilot ohne tax_dates; tax_dates erst in v0.17.3 |
| R5 | **Template-Migration vergisst Edge-Case** (z.B. empty videos) | MED | Acceptance T11: Bytewise-HTML-Diff vs Legacy. Wenn Diff -> Template-Bug |
| R6 | **Filter-Hooks `dhps_pipeline_data_{tag}`** wirken weiterhin auf Legacy-Array, **nicht** auf Collection | MED | Doku-Hinweis im Hook-DocBlock + Trust-Decision T6 dokumentieren. Adapter laeuft NACH Filter -> bekommt gefilterte Daten |
| R7 | **PHP-8.0-Sites broken nach v0.17.0** (wenn `readonly` genutzt) | MED | Lead-Entscheidung: PHP-Minimum-Bump auf 8.1 explizit in CHANGELOG + Update-Notice. Live-Sites laut MEMORY.md auf 8.3.30 |
| R8 | **L2-Cache-Schema drift** falls Future-Release Collection cached | LOW | TD-7 dokumentiert: aktuell NICHT gecacht; bei spaeterer Aenderung Cache-Version bumpen |
| R9 | **Adapter-Exception silent-swallow versteckt Bugs** | LOW | Optional: `error_log()` im catch + WP_DEBUG-Conditional. Tech-Debt-Ticket fuer v0.17.1: Admin-Notice bei wiederholtem Adapter-Fail |
| R10 | **`from_array()` Re-Hydration verliert DateTimeImmutable-Type** wenn naiv via JSON | LOW | `to_array()` schreibt `date` als ISO-8601-String; `from_array()` parsed via `new DateTimeImmutable($iso)` |
| R11 | **Service-Tag-Whitelist** im Konstruktor: was wenn neuer Service ohne Whitelist-Update? | LOW | TD: dynamisch via `DHPS_Service_Registry::get_all()` lookup statt harte Konstante. Service-Registry existiert (siehe Plugin-Files) |
| R12 | **TC-Wrapper-Parser passt nicht ins DTO-Modell** | LOW | TC-Adapter ist Trivial-Adapter: liefert leere Collection + `meta['html']`. Erst in v0.17.4 portieren |

**Tech-Debt nach v0.17.0:**
- TD-V0170-1: `dhps_pipeline_data_{tag}` Filter braucht Collection-Aequivalent (Hook
  `dhps_pipeline_collection_{tag}`) - in v0.17.1+
- TD-V0170-2: Live-Preview-System (v0.15.3+) muss `$collection` an Templates weiterreichen
  oder Legacy-Pfad nutzen (Discovery noetig)
- TD-V0170-3: WP-CLI-Adapter-Inspection-Command fuer Admin-Debug (optional)

---

## Sektion 10: Spec-Briefing-Material

### 10.1 Dateipfade fuer Neuschoepfung (F1)

```
includes/class-dhps-content-item.php
includes/class-dhps-content-collection.php
includes/class-dhps-content-adapter-interface.php
includes/class-dhps-content-adapter-registry.php
```

### 10.2 Dateipfade fuer Neuschoepfung (F2)

```
includes/adapters/class-dhps-maes-adapter.php
includes/adapters/index.php   # WP-Style "Silence is golden"
tests/test-content-item.php   # Unit-Tests fuer DTO
tests/test-maes-adapter.php   # Adapter + Snapshot-Test
```

### 10.3 Dateipfade fuer Anpassung (Lead + F2)

```
Deubner_HP_Services.php                       # Bootstrap-Registration (Lead)
includes/class-dhps-content-pipeline.php      # Pipeline-Patch (Lead)
includes/class-dhps-renderer.php              # render_parsed() Signatur (Lead)
public/views/services/maes/videos.php         # Pilot-Template (F2)
public/views/services/maes/merkblaetter.php   # Pilot-Template (F2)
public/views/services/maes/aktuelles.php      # Pilot-Template (F2)
docs/architecture/02-CONTENT-PIPELINE.md      # Architektur-Doku-Update (Lead)
docs/project/{NN}-CHANGELOG-v0170.md          # Release-Doku (Lead)
MEMORY.md                                     # Memory-Update (Lead)
```

### 10.4 Beispiel-Snippet: DTO-Klassen-Skelett

```php
<?php
/**
 * Class DHPS_ContentItem
 *
 * Immutable Value-Object fuer ein einzelnes Content-Item.
 *
 * @since 0.17.0
 */
final class DHPS_ContentItem {

    public const ALLOWED_TYPES = [ 'news', 'video', 'document', 'tax_date', 'generic' ];

    public readonly string $id;
    public readonly string $service;
    public readonly string $title;
    public readonly string $type;
    public readonly string $body;
    public readonly ?string $excerpt;
    public readonly ?array $image;
    public readonly ?array $media;
    public readonly ?string $link;
    public readonly ?DateTimeImmutable $date;
    public readonly array $tags;
    public readonly ?string $category;
    public readonly array $meta;

    public function __construct(
        string $id,
        string $service,
        string $title,
        string $type = 'generic',
        string $body = '',
        ?string $excerpt = null,
        ?array $image = null,
        ?array $media = null,
        ?string $link = null,
        ?DateTimeImmutable $date = null,
        array $tags = [],
        ?string $category = null,
        array $meta = []
    ) {
        if ( '' === trim( $id ) ) {
            throw new InvalidArgumentException( 'DHPS_ContentItem: $id darf nicht leer sein.' );
        }
        if ( '' === trim( $service ) ) {
            throw new InvalidArgumentException( 'DHPS_ContentItem: $service darf nicht leer sein.' );
        }
        if ( '' === trim( $title ) ) {
            throw new InvalidArgumentException( 'DHPS_ContentItem: $title darf nicht leer sein.' );
        }
        if ( ! in_array( $type, self::ALLOWED_TYPES, true ) ) {
            throw new InvalidArgumentException( sprintf(
                'DHPS_ContentItem: $type "%s" ist nicht erlaubt.', $type
            ) );
        }

        $this->id       = mb_substr( $id, 0, 128 );
        $this->service  = sanitize_key( $service );
        $this->title    = mb_substr( $title, 0, 500 );
        $this->type     = $type;
        $this->body     = $body;
        $this->excerpt  = $excerpt;
        $this->image    = $image;
        $this->media    = $media;
        $this->link     = $link;
        $this->date     = $date;
        $this->tags     = array_values( array_filter( $tags, 'is_string' ) );
        $this->category = $category;
        $this->meta     = $meta;
    }

    public function to_content_card_props(): array {
        $props = [
            'type'    => 'tax_date' === $this->type ? 'document' : $this->type, // tax_date -> document fuer ContentCard
            'title'   => $this->title,
            'teaser'  => $this->excerpt ?? '',
            'service' => $this->service,
        ];
        if ( '' !== $this->body ) {
            $props['body_html'] = $this->body;
        }
        if ( null !== $this->image && isset( $this->image['url'] ) ) {
            $props['media_url'] = $this->image['url'];
            $props['media_alt'] = $this->image['alt'] ?? '';
        }
        if ( null !== $this->link ) {
            $props['actions'] = [
                [ 'label' => __( 'Mehr erfahren', 'wp-deubner-hp-services' ),
                  'href' => $this->link, 'primary' => true ],
            ];
        }
        // Video-Sondercase: media-slug zu data-attrs durchreichen.
        if ( 'video' === $this->type && null !== $this->media && isset( $this->media['slug'] ) ) {
            $props['data_attrs'] = [
                'video-slug' => $this->media['slug'],
                'poster-url' => $this->media['poster'] ?? '',
            ];
        }
        return $props;
    }

    public function to_array(): array { /* ... */ }
    public static function from_array( array $data ): self { /* ... */ }
}
```

### 10.5 Beispiel-Snippet: Collection-Skelett

```php
<?php
/**
 * Class DHPS_ContentCollection
 *
 * @since 0.17.0
 */
final class DHPS_ContentCollection implements IteratorAggregate, Countable {

    public const ALLOWED_GROUP_KEYS = [ 'category', 'type', 'service' ];

    public readonly string $service;
    public readonly array $meta;

    /** @var DHPS_ContentItem[] */
    private array $items;

    public function __construct( string $service, array $items = [], array $meta = [] ) {
        $this->service = sanitize_key( $service );
        // Defensive: nur DHPS_ContentItem-Instanzen.
        $this->items   = array_values( array_filter(
            $items,
            static fn( $i ) => $i instanceof DHPS_ContentItem
        ) );
        $this->meta    = $meta;
    }

    public function add( DHPS_ContentItem $item ): self {
        $next = $this->items;
        $next[] = $item;
        return new self( $this->service, $next, $this->meta );
    }

    public function count(): int { return count( $this->items ); }
    public function is_empty(): bool { return 0 === count( $this->items ); }
    public function first(): ?DHPS_ContentItem { return $this->items[0] ?? null; }
    public function get_items(): array { return $this->items; }
    public function get_meta( string $key, mixed $default = null ): mixed {
        return $this->meta[ $key ] ?? $default;
    }

    public function filter( callable $predicate ): self {
        return new self(
            $this->service,
            array_values( array_filter( $this->items, $predicate ) ),
            $this->meta
        );
    }

    public function group_by( string $key ): array {
        if ( ! in_array( $key, self::ALLOWED_GROUP_KEYS, true ) ) {
            throw new InvalidArgumentException( "Unbekannter group_by-Key: $key" );
        }
        $buckets = [];
        foreach ( $this->items as $item ) {
            $bucket_key = match ( $key ) {
                'category' => $item->category ?? '_uncategorized',
                'type'     => $item->type,
                'service'  => $item->service,
            };
            $buckets[ $bucket_key ][] = $item;
        }
        $out = [];
        foreach ( $buckets as $k => $items ) {
            $out[ $k ] = new self( $this->service, $items, $this->meta );
        }
        return $out;
    }

    public function to_content_card_items(): array {
        return array_map(
            static fn( DHPS_ContentItem $i ) => $i->to_content_card_props(),
            $this->items
        );
    }

    public function to_array(): array { /* ... */ }
    public static function from_array( array $data ): self { /* ... */ }

    public function getIterator(): ArrayIterator {
        return new ArrayIterator( $this->items );
    }
}
```

### 10.6 Beispiel-Snippet: MAES-Adapter

```php
<?php
/**
 * Class DHPS_MAES_Adapter
 *
 * Wandelt DHPS_MAES_Parser-Output in DHPS_ContentCollection.
 *
 * @since 0.17.0
 */
final class DHPS_MAES_Adapter implements DHPS_Content_Adapter_Interface {

    public function adapt( array $parsed_data, string $service_tag ): DHPS_ContentCollection {
        $items = [];

        // 1) Videos -> Item-Type 'video'
        $videos = $parsed_data['videos'] ?? [];
        foreach ( $videos as $index => $video ) {
            if ( ! is_array( $video ) || empty( $video['title'] ) ) {
                continue;
            }
            $items[] = new DHPS_ContentItem(
                id:      'maes-video-' . $index,
                service: $service_tag,
                title:   (string) $video['title'],
                type:    'video',
                excerpt: isset( $video['description'] ) ? (string) $video['description'] : null,
                image:   ! empty( $video['poster_url'] )
                    ? [ 'url' => $video['poster_url'], 'alt' => $video['title'] ]
                    : null,
                media:   [
                    'kind'   => 'video',
                    'slug'   => $video['video_slug'] ?? '',
                    'poster' => $video['poster_url'] ?? '',
                ],
                meta:    [ 'video_index' => $index, 'mandantenvideo_service' => $video['service'] ?? 'maes' ]
            );
        }

        // 2) Merkblaetter -> Item-Type 'document'
        $merkblaetter = $parsed_data['merkblaetter'] ?? [];
        foreach ( $merkblaetter as $index => $mb ) {
            if ( ! is_array( $mb ) || empty( $mb['title'] ) ) {
                continue;
            }
            $items[] = new DHPS_ContentItem(
                id:      'maes-mb-' . $index,
                service: $service_tag,
                title:   (string) $mb['title'],
                type:    'document',
                excerpt: isset( $mb['description'] ) ? (string) $mb['description'] : null,
                meta:    [ 'pdf_params' => $mb['pdf_params'] ?? [] ]
            );
        }

        // 3) News -> Item-Type 'news'
        $news = $parsed_data['news'] ?? [];
        foreach ( $news as $index => $n ) {
            if ( ! is_array( $n ) || empty( $n['title'] ) ) {
                continue;
            }
            $items[] = new DHPS_ContentItem(
                id:      ! empty( $n['id'] ) ? (string) $n['id'] : 'maes-news-' . $index,
                service: $service_tag,
                title:   (string) $n['title'],
                type:    'news',
                body:    isset( $n['body_html'] ) ? (string) $n['body_html'] : '',
                excerpt: isset( $n['teaser'] ) ? (string) $n['teaser'] : null
            );
        }

        return new DHPS_ContentCollection(
            $service_tag,
            $items,
            [
                'overview' => $parsed_data['overview'] ?? [],
            ]
        );
    }
}
```

### 10.7 Beispiel-Snippet: Pipeline-Patch

```php
// In class-dhps-content-pipeline.php, render_service(), nach L2-Cache-Hit/Miss:

// NEU v0.17.0: Optionaler Adapter -> ContentCollection
$collection = null;
$adapter    = DHPS_Content_Adapter_Registry::for_service( $tag );
if ( null !== $adapter ) {
    try {
        $collection = $adapter->adapt( $parsed_data, $tag );
    } catch ( Throwable $e ) {
        $collection = null;
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[DHPS] Adapter "%s" warf Exception: %s',
                $tag, $e->getMessage()
            ) );
        }
    }
}

return $this->renderer->render_parsed( $parsed_data, $tag, $layout, $css_class, $collection );
```

### 10.8 Beispiel-Snippet: Template-Migration MAES-Videos

```php
<?php
// public/views/services/maes/videos.php nach Migration:

$columns      = isset( $columns ) ? (int) $columns : 2;
$custom_class = isset( $custom_class ) && is_string( $custom_class ) ? $custom_class : '';

// --- BC-Pfad: $collection wird vom Renderer geliefert, kann null sein ---
if ( null !== $collection && ! $collection->is_empty() ) {
    // NEUER PFAD: Collection -> ContentCard-Items
    $video_collection = $collection->filter(
        static fn( DHPS_ContentItem $item ) => 'video' === $item->type
    );
    $items = $video_collection->to_content_card_items();
} else {
    // LEGACY-PFAD: $data['videos'] manuell transformieren (wie bisher)
    $videos = isset( $data['videos'] ) && is_array( $data['videos'] ) ? $data['videos'] : array();
    $items = []; // wie bisher per foreach befuellen
    foreach ( $videos as $video ) {
        // ... bestehender Transformations-Code bleibt als Fallback
    }
}

// Ab hier identisch zum Legacy-Template: $items wird an content-list gegeben.
echo dhps_component( 'content-list', [
    'id'          => $list_id,
    'layout'      => 'grid',
    'columns'     => $columns,
    'items'       => $items,
    'item_type'   => 'video',
    'class'       => 'dhps-content-list--maes-videos',
    'empty_state' => [ /* ... */ ],
] );
```

### 10.9 Test-Skript-Skelett

```php
<?php
// tests/test-content-item.php

class Test_DHPS_ContentItem extends WP_UnitTestCase {

    public function test_construct_minimal(): void {
        $item = new DHPS_ContentItem( 'id-1', 'maes', 'Title 1' );
        $this->assertSame( 'id-1', $item->id );
        $this->assertSame( 'maes', $item->service );
        $this->assertSame( 'Title 1', $item->title );
        $this->assertSame( 'generic', $item->type );
    }

    public function test_empty_id_throws(): void {
        $this->expectException( InvalidArgumentException::class );
        new DHPS_ContentItem( '', 'maes', 'Title' );
    }

    public function test_invalid_type_throws(): void {
        $this->expectException( InvalidArgumentException::class );
        new DHPS_ContentItem( 'id', 'maes', 'Title', 'badtype' );
    }

    public function test_readonly_blocks_mutation(): void {
        $item = new DHPS_ContentItem( 'id', 'maes', 'Title' );
        $this->expectException( Error::class ); // PHP 8.1: ReadonlyPropertyError
        $item->title = 'mutated';
    }

    public function test_to_array_from_array_roundtrip(): void {
        $original = new DHPS_ContentItem(
            'id', 'maes', 'Title', 'video',
            body: '<p>body</p>', excerpt: 'short',
            image: [ 'url' => 'http://x/img.jpg', 'alt' => 'alt' ],
            media: [ 'kind' => 'video', 'slug' => 'abc' ]
        );
        $restored = DHPS_ContentItem::from_array( $original->to_array() );
        $this->assertEquals( $original->to_array(), $restored->to_array() );
    }
}

// tests/test-maes-adapter.php
class Test_DHPS_MAES_Adapter extends WP_UnitTestCase {

    public function test_adapt_videos_to_collection(): void {
        $adapter   = new DHPS_MAES_Adapter();
        $collection = $adapter->adapt( [
            'videos' => [
                [ 'title' => 'V1', 'description' => 'D1', 'video_slug' => 'v1',
                  'poster_url' => 'http://p/1.jpg' ],
            ],
        ], 'maes' );
        $this->assertSame( 1, $collection->count() );
        $first = $collection->first();
        $this->assertSame( 'video', $first->type );
        $this->assertSame( 'V1', $first->title );
    }

    public function test_adapter_handles_empty_parser_output(): void {
        $collection = ( new DHPS_MAES_Adapter() )->adapt( [], 'maes' );
        $this->assertTrue( $collection->is_empty() );
    }
}
```

---

## Anhang: Lead-Briefing-Zusammenfassung

| Frage | Antwort |
|---|---|
| **Migration-Strategie** | Option C - Adapter-Layer |
| **Pilot-Service** | MAES (3 Item-Typen, nutzt ContentCard schon, klare Struktur) |
| **Spec-Aufteilung** | 2 Specialists: F1 DTO-Foundation + F2 MAES-Adapter+Templates; Lead-Direct fuer Pipeline-Patch |
| **Top-3-Risiken** | R1 (BC-Bruch durch Template-`$data`-Verlust), R2 (Performance bei 60+ Items), R7 (PHP-8.0-Break wenn `readonly` genutzt) |
| **Geschaetzter Aufwand** | M (mittel) - 450-700 LOC gesamt, 1 Discovery-Doc (diese), 1 Spec-Doc je Specialist, QA/SEC parallel |
| **PHP-Version-Empfehlung** | **Auf 8.1 anheben** fuer `readonly`-Properties. Begruendung: PHP 8.0 EOL 2023-11; Live-Sites auf 8.3.30; Workaround mit private+Getter waere over-engineered |

**Risiko-Gegenmittel-Map:**
- R1: Adapter LIEST nur `$data`, Pipeline reicht IMMER beides weiter, Template-Migration ist
  defensiv mit Fallback-Pfad (Sektion 10.8)
- R2: Acceptance T14 mit < 3ms Budget, ggf. Lazy-Generator-Pivot in v0.17.1
- R7: Explizite PHP-8.1-Bump-Doku in CHANGELOG + Plugin-Header `Requires PHP: 8.1`,
  Live-Notice bei Inkompatibilitaet (Pattern bereits verwendet fuer Elementor-Version-Check
  in v0.16.1)

**Schema-Vertrag-Status:** Sektion 5 ist verbindlich, alle DTO-Felder/Methoden/Signaturen
fixiert. Wird beiden Spec-Docs (F1+F2) als Pflicht-Sektion mitgegeben.

**Ende Discovery.**
