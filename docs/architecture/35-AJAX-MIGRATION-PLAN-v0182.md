# AJAX-Migration-Plan v0.18.2 - Helper-Side-Channel fuer MMB-Lazy-Akkordeon + MIO-News

## Stand: 2026-06-08 (Discovery-Phase v0.18.2)

## Mission

Zwei verbleibende AJAX-Tech-Debt-Tickets aus der v0.17.5-Roadmap ueber das
v0.17.5 etablierte **Helper-Side-Channel-Pattern (Option D)** auf das
einheitliche Datenmodell anschliessen. Beide Migrationen tasten die
JSON-Response BYTEWISE NICHT an - Frontend-JS-Vertrag bleibt erhalten.

| Ticket | Pfad | Bisher | Soll |
|--------|------|--------|------|
| TD-V0171-2 | `DHPS_MMB_AJAX_Handler::handle_request` (MMB-Lazy-Akkordeon) | Legacy `$categories[]` | Helper + Action-Hook |
| TD-V0174-1 | `DHPS_AJAX_Proxy::handle_news_request` (MIO-News) | Legacy `{groups[], pagination}` | Helper + Action-Hook |

Vorbild: TD-V0171-3 (v0.17.5) - MMB-Search-AJAX in
`DHPS_AJAX_Proxy::handle_mmb_search` mit Helper `dhps_mmb_search_to_collection`
und Action-Hook `dhps_mmb_search_collection`.

## Discovery-Befunde

### MMB-Lazy-Akkordeon: Stand und Drift-Risiko

- AJAX-Klasse lebt **NICHT** in `DHPS_AJAX_Proxy`, sondern in einer eigenen
  Datei `includes/class-dhps-mmb-ajax-handler.php` (Konstruktor: API-Client
  + Cache; Init im Plugin-Bootstrap Z. 405).
- Endpoint-Slug: `wp_ajax_dhps_mmb_category_load` + `nopriv`-Pendant.
- Service-Whitelist: `mmb` und `mil`.
- Layout-Whitelist (seit v0.15.2): `default | card | compact`.
- Pre-Helper-Verhalten:
  1. Nonce-Check (`dhps_mmb_nonce`)
  2. Rate-Limit (60 req/min/IP)
  3. Input-Sanitize (service, category_id, layout)
  4. `DHPS_Service_Registry::get_service($service)` + OTA aus Optionen
  5. `DHPS_API_Client::fetch_content` (Cache-Aside, TTL 3600)
  6. `DHPS_MMB_Parser::parse` -> `$parsed['categories']`
  7. Linearer Loop sucht Kategorie via `category_id`
  8. Partial-Render (BC-faehiges HTML)
  9. JSON-Response mit Shape:
     ```
     {
       category_id, category_name, icon_slug,
       fact_sheets: [{id,title,description,pdf_params,...}],
       html: <rendered partial>
     }
     ```

### MIO-News: Stand und Drift-Risiko

- Handler `DHPS_AJAX_Proxy::handle_news_request` (Z. 107-184).
- Endpoint-Slug: `wp_ajax_dhps_load_news` + `nopriv`-Pendant.
- Service-Whitelist (implizit): jedes via `DHPS_Service_Registry` registrierte
  Service-Tag (Default `mio`); News-Container nutzt aktuell nur mio/lxmio.
- Filter-Parameter:
  `page, search, month, year, rubriken, zielgruppen, fachgebiet, variante, anzahl, teasermodus`
- Pre-Helper-Verhalten:
  1. Nonce-Check (`dhps_news_nonce`)
  2. Param-Sanitize (10 Filter)
  3. `DHPS_Service_Registry::get_service($service_tag)` + OTA
  4. Endpoint-Swap `php_inhalt.php -> hintergrundladen.php`
  5. Cache-Check via `DHPS_Cache`
  6. `DHPS_Legacy_API::fetch`
  7. `DHPS_MIO_News_Parser::parse` -> `$parsed['groups']` + `$parsed['pagination']`
  8. Cache schreiben (TTL 900)
  9. JSON-Response 1:1 = `$parsed`.

- Parser-Output-Shape (MIO_News_Parser):
  ```
  {
    groups: [
      { name, articles: [
          { id, title, body_html, metadata: {target?, topic?}, share_links: {email?, twitter?, ...} }
      ] }
    ],
    pagination: { current, has_more }
  }
  ```
- Frontend-JS-Vertrag (dhps-mio.js): liest `data.groups[].articles[]` und
  rendert Default/Card/Compact-Layouts. Keine Felder-Drift erlaubt.

### Stand DTO-Whitelist

- `DHPS_Content_Item::ALLOWED_TYPES = ['news', 'video', 'document', 'tax_date', 'generic']`.
  -> `'news'` ist seit v0.17.0 vorbehalten und wird durch TD-V0174-1 erstmals
  produktiv genutzt.
- `ALLOWED_SERVICES` enthaelt mio/lxmio/mmb/mil. Service-Tag durchgereicht
  wie im Search-Helper.

### MIO-Adapter heute

- `DHPS_MIO_Adapter` adaptiert **nur** `tax_dates` (Type `tax_date`). Er
  reicht `ajax_params` in der Collection-Meta durch, mappt selbst aber
  KEINE News-Items. Das ist by-Design (News-HTML kommt erst ueber den
  AJAX-Endpoint).

## Architektur-Entscheidung

### Option D - Helper-Side-Channel - fuer BEIDE Migrationen

Begruendung (analog v0.17.5 TD-V0171-3, dort 1:1 erfolgreich):

1. **Frontend-JS-Vertrag bytewise erhalten.** Die JSON-Response bleibt
   identisch. Beobachter (Plugins/Themes) bekommen das Collection-Objekt
   ueber einen `do_action(...)`-Hook gereicht.
2. **Kein Adapter-Aufblasen.** Die News-Items haben eine
   Sub-Shortcode-aehnliche Daten-Shape, die der MIO-Hauptadapter heute
   bewusst NICHT kennt (TD-V0174-1 wollte exakt das). Eine Bridge-via-
   Helper haelt den Adapter sauber.
3. **Pattern bereits dokumentiert.** Siehe `docs/architecture/32-SUB-SHORTCODE-PATTERN.md`
   Sektion "AJAX-Sub-Pfade: Helper-Side-Channel-Pattern" - der Plan setzt
   die dort beschriebene Roadmap-Zeile fuer v0.17.6/v0.18.0 um (terminlich
   auf v0.18.2 verschoben durch den v0.18.0-Legacy-Cleanup vorgezogen).
4. **0 BC-Bruch.** Bestehender Code wird nur additiv erweitert.

Verworfene Alternativen (siehe Pattern-Doku):

- **Option A** (Eigener Sub-Adapter): Anti-Pattern (1-Adapter-pro-Service-Konvention).
- **Option B** (2. Methode am Adapter): bricht `DHPS_Content_Adapter_Interface`.
- **Option C** (Daten-Umpacken durch MIO-Adapter): provoziert Schema-Drift
  zwischen `tax_dates`- und News-Pfaden.

## Schema-Vertrag (zentrale Spezifikation)

### TD-V0171-2: `dhps_mmb_category_to_collection`

**Signatur**:
```php
function dhps_mmb_category_to_collection(
    array $category,
    string $service,
    array $extra_meta = array()
): ?DHPS_Content_Collection
```

**Mapping**:
- 1 Collection pro Kategorie (es ist immer EINE Kategorie, der Handler hat
  sie bereits gefunden).
- Fact-Sheets ohne Title werden geskippt (analog Adapter + Search-Helper).
- Item-Type: `'document'` (in ALLOWED_TYPES seit v0.17.0).
- Item-ID-Konvention: `{service}-cat-{category_id}-doc-{sheet_id_or_idx}`.
  - "cat" disambiguiert gegenueber Item-IDs der Initial-Render-Pipeline
    (`{service}-doc-{cat_idx}-{sheet_id}`) und der Search (`{service}-search-doc-{id}`).
- Item-Felder:
  - title = `(string) $sheet['title']`
  - excerpt = `(string) ($sheet['description'] ?? null)`
  - service = Service-Tag des Aufrufers
  - category = `(string) $category['id']` (z.B. `rubrik_3`)
  - body/image/media/link/date/tags = wie MMB-Adapter (`'' / null / array()`)
- Item-meta:
  - `result_index` = numerischer Schleifenindex
  - `source_id` = `$sheet['id']` wenn nicht-leer
  - `pdf_params` = `$sheet['pdf_params']` wenn Array
  - `category_id` / `category_name` / `icon_slug` = Header-Felder der
    Kategorie (analog MMB-Adapter)

**Collection-Meta**:
- `is_lazy_category` = `true` (Marker fuer Konsumenten)
- `category_id` = `(string) $category['id']`
- `category_name` = `(string) ($category['name'] ?? '')`
- `icon_slug` = `(string) ($category['icon_slug'] ?? '')`
- `total_documents` = Anzahl gemappter Items
- Zusaetzliche Meta-Felder aus `$extra_meta` (z.B. `layout` aus dem AJAX-
  Handler). 1:1 in Collection-Meta gemerged.

**Action-Hook**:
```php
do_action(
    'dhps_mmb_category_collection',
    $collection,            // DHPS_Content_Collection|null
    $category,              // Rohes Kategorie-Array vom Parser
    $service                // 'mmb' | 'mil'
);
```

### TD-V0174-1: `dhps_mio_news_to_collection`

**Signatur**:
```php
function dhps_mio_news_to_collection(
    array $parsed_news,
    string $service,
    array $extra_meta = array()
): ?DHPS_Content_Collection
```

**Mapping**:
- Flache Liste von News-Items aus `$parsed['groups'][]['articles'][]`.
- Gruppen-Reihenfolge + Gruppen-Header leben in Collection-Meta
  (`groups_order`/`groups_meta`), NICHT als Sub-Collections (DTO bietet
  keine verschachtelte Collection).
- Items ohne Title werden geskippt.
- Item-Type: `'news'`.
- Item-ID-Konvention: `{service}-news-{group_idx}-{article_id_or_idx}`.
  - Article-ID kommt vom Parser (`item18014` -> `'18014'`) und ist
    typischerweise eindeutig - fallback auf numerischen Schleifenindex.
- Item-Felder:
  - title = `(string) $article['title']`
  - body = `(string) ($article['body_html'] ?? '')` (HTML aus Parser)
  - excerpt = null (Frontend-JS baut Excerpt zur Render-Zeit selbst)
  - category = Name der Gruppe (string) - Match-Key fuer Group-Filter
  - date = null (Tag fehlt in der Parser-Shape; TD-V0173-2 kuemmert sich
    um Datum-Normalisierung in v0.18.1)
  - image/media/link/tags = `null / null / null / array()`
- Item-meta:
  - `group_index` = numerischer Schleifenindex der Gruppe
  - `article_index` = numerischer Schleifenindex innerhalb der Gruppe
  - `source_id` = `$article['id']` wenn nicht-leer
  - `metadata` = `$article['metadata']` (array, kann `topic`/`target` enthalten)
  - `share_links` = `$article['share_links']` (assoz. Array)
  - `body_html` = `$article['body_html']` (Duplikat zu $body, aber
    JS-faehiger Eigenname; konsistent mit Article-Shape im Frontend)

**Collection-Meta**:
- `is_news` = `true`
- `groups_order` = `string[]` Gruppen-Namen in Parser-Reihenfolge
- `groups_meta` = `{ group_name => { article_count: int } }`
- `pagination` = `$parsed['pagination']` (Array `{current, has_more}`) - 1:1
- `total_articles` = Sum aller gemappten Items
- `is_search` = `false` (vs. Search-Helper)
- Zusaetzliche Meta-Felder aus `$extra_meta` (z.B. Filter-Atts aus dem
  Request: `month`, `year`, `search`, `page`). Optional - Default leer.

**Action-Hook**:
```php
do_action(
    'dhps_news_collection',
    $collection,            // DHPS_Content_Collection|null
    $parsed,                // Roher Parser-Output (groups + pagination)
    $service                // 'mio' | 'lxmio' (typischerweise)
);
```

## Datei-Aenderungen

### Neu

- **keine neuen Dateien** (Helper landen im bestehenden
  `includes/dhps-content-helpers.php`)

### Geaendert

| Datei | Aenderung | LOC ~ |
|-------|-----------|-------|
| `includes/dhps-content-helpers.php` | + 2 Helper-Funktionen (`dhps_mmb_category_to_collection`, `dhps_mio_news_to_collection`), beide mit `function_exists`-Guard, Fail-Soft try/catch + WP_DEBUG-Log | +180 |
| `includes/class-dhps-mmb-ajax-handler.php` | Nach `parser->parse()` + Kategorie-Suche: Helper-Aufruf + `do_action('dhps_mmb_category_collection', ...)`. JSON-Response unangetastet. | +20 |
| `includes/class-dhps-ajax-proxy.php` | In `handle_news_request` nach `news_parser->parse()`: Helper-Aufruf + `do_action('dhps_news_collection', ...)`. JSON-Response unangetastet. | +18 |
| `Deubner_HP_Services.php` | Version 0.18.1 -> 0.18.2 | 1 |
| `README.md` | Version-Bump + Eintrag | 2-3 |
| `docs/project/55-CHANGELOG-v0182.md` | Neu | ~150 |
| `MEMORY.md` | MILESTONE 26 + Implementation-Notes | ~12 |

## Edge-Cases

1. **Empty Result-Sets**
   - MMB: leere `fact_sheets` -> Collection mit 0 Items + Collection-Meta
     `is_lazy_category=true`/`total_documents=0`. Action-Hook feuert mit
     leerer Collection.
   - MIO-News: leere `groups` -> Collection mit 0 Items, Collection-Meta
     `is_news=true`/`groups_order=[]`. Action-Hook feuert.

2. **Items ohne Title**: Skip (analog allen anderen Adaptern + Search-Helper).
   Keine Exception, Item zaehlt nicht in `total_*`-Felder.

3. **Pagination (nur MIO-News)**
   - Frontend-JS verlangt `pagination.has_more` und `pagination.current`.
   - Helper liest 1:1 aus `$parsed['pagination']`, setzt sensible Defaults
     `{current:1, has_more:false}` wenn der Parser keine Pagination liefert
     (Parser-Default ist genau das, also redundant aber sicher).

4. **Cache-Verhalten**: BEIDE Migrationen tasten den Cache **nicht** an.
   Helper und Action-Hook laufen NACH dem Cache-Schreiben (MIO-News) bzw.
   greifen auf den bereits geladenen Parser-Output zu (MMB-Lazy).
   Konsumenten des Action-Hooks koennen kein Cache-Read triggern.

5. **Fail-Soft bei Helper-Throw**:
   - Konstanten-Klasse fehlt -> Helper liefert `null`, Handler ignoriert,
     JSON-Response unveraendert.
   - Item-Konstruktor throwt -> try/catch um die EINZELITEM-Erzeugung,
     WP_DEBUG-Log mit Index, weiter mit naechstem Item.
   - Collection-Konstruktor throwt -> try/catch aussen, Helper liefert
     `null`, JSON unveraendert.

6. **Service-Tag-Drift**:
   - MMB-Helper: bekommt Service vom Handler (`'mmb' | 'mil'`).
   - News-Helper: bekommt Service vom Handler (default `'mio'`,
     ueber `service_tag`-POST-Param auch `'lxmio'`). Wenn Service nicht in
     `DHPS_Content_Item::ALLOWED_SERVICES`, throwt der Item-Konstruktor,
     try/catch schluckt, Item entfaellt, JSON unveraendert.

7. **Layout-Param (MMB)**: `$extra_meta = ['layout' => $layout]` darf
   durchgereicht werden, damit Konsumenten z.B. Card vs. Default
   unterscheiden koennen. Layout selbst beeinflusst die Items NICHT.

## Tests (T1-T30+, Lead-Smoke)

`test-v0182-ajax.php` als Plugin-Root-Datei (analog `test-v0175-bridge.php`).

### TD-V0171-2 (MMB-Lazy-Akkordeon) - T1-T14

- **T1**: Helper existiert (`function_exists`).
- **T2**: Empty `$category` (`[]`) -> Collection mit 0 Items, Meta
  `is_lazy_category=true`, `category_id=''`, `total_documents=0`.
- **T3**: Normale Kategorie mit 3 Fact-Sheets -> 3 Items, alle type='document',
  alle service='mmb', alle category='rubrik_3', alle Item-IDs
  `mmb-cat-rubrik_3-doc-{id}`.
- **T4**: Fact-Sheet ohne `title` -> skip (Item-Count = 2 statt 3).
- **T5**: Service-Variante 'mil' -> Item-IDs `mil-cat-...`,
  Item-service='mil'.
- **T6**: `pdf_params` wird in Item-meta gespiegelt.
- **T7**: `source_id` wird in Item-meta gespiegelt, fehlt bei Sheet ohne `id`.
- **T8**: Collection-Meta enthaelt `category_name`, `icon_slug`,
  `total_documents`.
- **T9**: `$extra_meta = ['layout' => 'card']` wird in Collection-Meta
  gemerged.
- **T10**: Action-Hook `dhps_mmb_category_collection` feuert genau einmal
  pro AJAX-Call (Counter via Hook).
- **T11**: JSON-Response-Shape pre vs. post Helper-Hook BYTEWISE identisch
  (Snapshot-Diff).
- **T12**: Item-Konstruktor-Throw (Sheet mit kaputtem Service-Tag-Mock)
  schluckt -> Collection mit weniger Items, kein Fatal.
- **T13**: Kategorie-Item ohne `id` -> Collection mit `category_id=''`,
  Item-IDs fallen auf numerischen Index zurueck (`mmb-cat--doc-{idx}`).
- **T14**: Rate-Limit-Pfad (vor Helper-Aufruf): Helper darf NICHT gerufen
  werden bei `429` (Pre-Check).

### TD-V0174-1 (MIO-News) - T15-T30

- **T15**: Helper existiert.
- **T16**: Empty `$parsed` (`[]`) -> Collection mit 0 Items,
  `is_news=true`, `groups_order=[]`, `pagination={current:1, has_more:false}`.
- **T17**: 2 Gruppen mit je 2 Articles -> 4 Items in Reihenfolge,
  alle type='news', alle service='mio',
  Item-IDs `mio-news-0-{id}` / `mio-news-1-{id}`.
- **T18**: Article ohne `title` -> skip.
- **T19**: Article ohne `id` -> Item-ID-Fallback auf numerischen Index
  innerhalb der Gruppe (`mio-news-0-{idx}`).
- **T20**: Item-`category` = Gruppen-Name.
- **T21**: Item-body = `body_html`, Item-meta enthaelt
  `body_html`-Duplikat (JS-Vertrag).
- **T22**: Item-meta enthaelt `metadata` (topic/target) und `share_links`.
- **T23**: Collection-Meta `groups_order` listet Gruppen-Namen in
  Parser-Reihenfolge.
- **T24**: Collection-Meta `groups_meta[gruppe_name]['article_count']`
  korrekt summiert.
- **T25**: Collection-Meta `pagination` = `$parsed['pagination']` 1:1.
- **T26**: Service-Variante 'lxmio' -> Item-IDs `lxmio-news-...`.
- **T27**: `$extra_meta = ['month' => '03', 'year' => '2026']` landet in
  Collection-Meta.
- **T28**: Action-Hook `dhps_news_collection` feuert genau einmal pro
  AJAX-Call (Counter via Hook).
- **T29**: JSON-Response-Shape pre vs. post Helper-Hook BYTEWISE identisch
  (Snapshot-Diff).
- **T30**: Article mit kaputter Sub-Struktur (z.B. Service-Tag-Drift)
  schluckt -> Item-Count verringert, kein Fatal, JSON unveraendert.

### Erweitert (optional)

- **T31**: Smoke gegen LIVE MMB-AJAX-Endpoint (`wp_ajax_dhps_mmb_category_load`)
  via WP-CLI-Simulator. Vergleich Response pre/post in 1 Diff.
- **T32**: Smoke gegen LIVE News-AJAX-Endpoint (`wp_ajax_dhps_load_news`)
  via WP-CLI-Simulator.

Target: **30 / 30 PASS** (analog v0.17.5 25/25).

## Spec-Aufteilung-Empfehlung

**Pure Lead-Direct** (kein Specialist).

Begruendung:

- Pattern ist 1:1 etabliert (TD-V0171-3 in v0.17.5).
- Geringer Scope (~220 LOC + Tests).
- Keine neuen Klassen, kein neues Pattern, keine neuen Konstanten-Whitelisten.
- DTO-Felder (`type='news'`) sind seit v0.17.0 vorbereitet.
- Frontend-JS-Vertrag wird BYTEWISE erhalten (0 BC-Risiko).

Alternativ-Variante (defensiv): **2 Mini-Releases v0.18.2 + v0.18.3** pro
Migration getrennt, falls man Stage-Verifikation einzeln durchziehen will.
Empfehlung: NICHT - die Migrationen sind orthogonal und 1 Release reicht.

## Aufwand-Schaetzung

| Phase | Aufwand |
|-------|---------|
| Helper #1 `dhps_mmb_category_to_collection` | S (~80 LOC) |
| Helper #2 `dhps_mio_news_to_collection` | S-M (~100 LOC) |
| 2 AJAX-Handler patchen | S (je ~10 LOC) |
| Lead-Smoke 30 Tests | M |
| Stage-Smoke | S |
| Changelog + MEMORY-Update | S |
| **Total** | **M** |

Gesamt: **1 Pure-Lead-Direct-Release**, Scope ~250 LOC + Tests.

## Top-Risiken

### R1 - JSON-Response-Drift (HOCH-Bewertung, NIEDRIG-Wahrscheinlichkeit)

**Was**: Wenn ein Konsument des Action-Hooks `wp_send_json_*` triggert
oder den Cache pruefen wuerde, riskieren wir Header-Drift.

**Mitigation**: Helper feuert Action-Hook KURZ vor `wp_send_json_success`.
Keine Refs auf `$parsed` werden vom Helper ueberschrieben (read-only via
`$parser_output`-Pattern in den anderen Helfern). Action-Hook reicht
defensive Kopien. Test T11 + T29 prueft Snapshot bytewise.

### R2 - News-Pagination-Edge-Case (NIEDRIG)

**Was**: Parser liefert manchmal `pagination={}` ohne Felder.

**Mitigation**: Helper setzt sichere Defaults `{current:1, has_more:false}`.
Test T16 deckt das ab.

### R3 - Item-Konstruktor-Throw verschluckt zu viel (NIEDRIG-MITTEL)

**Was**: Globaler try/catch um die Item-Erzeugung koennte echte Schema-
Drifts maskieren.

**Mitigation**: WP_DEBUG-error_log enthaelt Service + Index + Message
(analog Search-Helper). Bei Stage-Run mit WP_DEBUG=true sind Drifts
sichtbar. Counter `total_*` zaehlt nur erfolgreich angelegte Items.

### R4 - Cache-TTL-Drift (NIEDRIG)

**Was**: Bei Helper-Refactor wird versehentlich der Cache-Aside-Block
verschoben.

**Mitigation**: Plan schreibt vor: Helper-Aufruf NACH Cache-Write, NICHT
davor oder dazwischen. Test T11/T29 catched das durch Snapshot-Vergleich.

## BC-Impact

- 0 Frontend-JS-Aenderungen (`dhps-mio.js`, `dhps-mmb.js` unangetastet).
- 0 JSON-Response-Aenderungen (Shape bytewise identisch).
- 0 Adapter-Aenderungen (`DHPS_MMB_Adapter`, `DHPS_MIO_Adapter` unberuehrt).
- 0 Parser-Aenderungen.
- 0 Template-Aenderungen.
- 0 REST-Aenderungen.
- 2 neue Helper-Funktionen (additiv, function_exists-Guard).
- 2 neue Action-Hooks (additiv, Default-Behaviour ohne Konsumenten ist no-op).
- 1 require_once in Bootstrap unveraendert (`dhps-content-helpers.php` ist
  bereits seit v0.17.1 inkludiert).

## Spec-Briefing fuer den Lead

### Helper #1: `dhps_mmb_category_to_collection`

Pfad: `includes/dhps-content-helpers.php`

Skelett:

```php
if ( ! function_exists( 'dhps_mmb_category_to_collection' ) ) {
    /**
     * Wandelt eine vom MMB-Parser gelieferte EINZEL-Kategorie (Lazy-AJAX-Pfad)
     * in eine DHPS_Content_Collection.
     *
     * Helper-Side-Channel fuer den DHPS_MMB_AJAX_Handler. Die JSON-Response
     * an Frontend-JS bleibt BYTEWISE UNVERAENDERT - die Collection wird
     * nur ueber den Action-Hook `dhps_mmb_category_collection` exposed.
     *
     * @since 0.18.2
     *
     * @param array  $category   Kategorie-Array vom MMB-Parser (id, name,
     *                           icon_slug, fact_sheets[]).
     * @param string $service    Service-Tag ('mmb' | 'mil').
     * @param array  $extra_meta Zusaetzliche Collection-Meta (z.B. layout).
     *
     * @return DHPS_Content_Collection|null
     */
    function dhps_mmb_category_to_collection(
        array $category,
        string $service,
        array $extra_meta = array()
    ): ?DHPS_Content_Collection {
        if ( ! class_exists( 'DHPS_Content_Collection' )
            || ! class_exists( 'DHPS_Content_Item' ) ) {
            return null;
        }

        $cat_id        = isset( $category['id'] ) ? (string) $category['id'] : '';
        $cat_name      = isset( $category['name'] ) ? (string) $category['name'] : '';
        $cat_icon_slug = isset( $category['icon_slug'] ) ? (string) $category['icon_slug'] : '';
        $fact_sheets   = isset( $category['fact_sheets'] ) && is_array( $category['fact_sheets'] )
            ? $category['fact_sheets']
            : array();

        $items = array();
        foreach ( $fact_sheets as $idx => $sheet ) {
            if ( ! is_array( $sheet ) ) {
                continue;
            }
            $title = isset( $sheet['title'] ) ? trim( (string) $sheet['title'] ) : '';
            if ( '' === $title ) {
                continue;
            }

            $sheet_id     = isset( $sheet['id'] ) ? (string) $sheet['id'] : '';
            $item_id_tail = ( '' !== $sheet_id ) ? $sheet_id : (string) $idx;
            $item_id      = $service . '-cat-' . $cat_id . '-doc-' . $item_id_tail;
            $excerpt      = isset( $sheet['description'] ) ? (string) $sheet['description'] : null;

            $meta = array(
                'result_index'  => (int) $idx,
                'category_id'   => $cat_id,
                'category_name' => $cat_name,
                'icon_slug'     => $cat_icon_slug,
            );
            if ( '' !== $sheet_id ) {
                $meta['source_id'] = $sheet_id;
            }
            if ( isset( $sheet['pdf_params'] ) && is_array( $sheet['pdf_params'] ) ) {
                $meta['pdf_params'] = $sheet['pdf_params'];
            }

            try {
                $items[] = new DHPS_Content_Item(
                    $item_id, $service, $title, 'document',
                    '', $excerpt, null, null, null, null, array(), $cat_id, $meta
                );
            } catch ( \Throwable $e ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
                    error_log( sprintf(
                        'DHPS mmb_category_to_collection skip item idx=%d: %s',
                        $idx, $e->getMessage()
                    ) );
                }
                continue;
            }
        }

        $collection_meta = array_merge(
            array(
                'is_lazy_category' => true,
                'category_id'      => $cat_id,
                'category_name'    => $cat_name,
                'icon_slug'        => $cat_icon_slug,
                'total_documents'  => count( $items ),
            ),
            $extra_meta
        );

        try {
            return new DHPS_Content_Collection( $service, $items, $collection_meta );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
                error_log( sprintf(
                    'DHPS mmb_category_to_collection failed: %s', $e->getMessage()
                ) );
            }
            return null;
        }
    }
}
```

### Helper #2: `dhps_mio_news_to_collection`

Pfad: `includes/dhps-content-helpers.php`

Skelett:

```php
if ( ! function_exists( 'dhps_mio_news_to_collection' ) ) {
    /**
     * Wandelt das Ergebnis-Array von DHPS_MIO_News_Parser in eine
     * DHPS_Content_Collection (Side-Channel fuer DTO-Konsistenz).
     *
     * MIO-News-AJAX laeuft NICHT durch die Content-Pipeline. Der
     * DHPS_MIO_Adapter mappt bewusst keine News-Items, weil News-HTML
     * erst im AJAX-Endpoint entsteht. Dieser Helper bietet eine
     * **Helper-only Bridge** (Pattern aus v0.17.5 TD-V0171-3): die
     * JSON-Response an Frontend-JS bleibt BYTEWISE UNVERAENDERT.
     *
     * @since 0.18.2
     *
     * @param array  $parsed_news Parser-Output (groups + pagination).
     * @param string $service     Service-Tag ('mio' | 'lxmio').
     * @param array  $extra_meta  Zusaetzliche Collection-Meta (z.B. Filter-Atts).
     *
     * @return DHPS_Content_Collection|null
     */
    function dhps_mio_news_to_collection(
        array $parsed_news,
        string $service,
        array $extra_meta = array()
    ): ?DHPS_Content_Collection {
        if ( ! class_exists( 'DHPS_Content_Collection' )
            || ! class_exists( 'DHPS_Content_Item' ) ) {
            return null;
        }

        $groups = isset( $parsed_news['groups'] ) && is_array( $parsed_news['groups'] )
            ? $parsed_news['groups']
            : array();

        $pagination = isset( $parsed_news['pagination'] ) && is_array( $parsed_news['pagination'] )
            ? $parsed_news['pagination']
            : array( 'current' => 1, 'has_more' => false );

        $items        = array();
        $groups_order = array();
        $groups_meta  = array();

        foreach ( $groups as $group_idx => $group ) {
            if ( ! is_array( $group ) ) {
                continue;
            }
            $group_name = isset( $group['name'] ) ? (string) $group['name'] : '';
            $articles   = isset( $group['articles'] ) && is_array( $group['articles'] )
                ? $group['articles']
                : array();

            $group_count = 0;
            foreach ( $articles as $art_idx => $article ) {
                if ( ! is_array( $article ) ) {
                    continue;
                }
                $title = isset( $article['title'] ) ? trim( (string) $article['title'] ) : '';
                if ( '' === $title ) {
                    continue;
                }
                $article_id   = isset( $article['id'] ) ? (string) $article['id'] : '';
                $item_id_tail = ( '' !== $article_id ) ? $article_id : (string) $art_idx;
                $item_id      = $service . '-news-' . (int) $group_idx . '-' . $item_id_tail;

                $body = isset( $article['body_html'] ) ? (string) $article['body_html'] : '';

                $meta = array(
                    'group_index'   => (int) $group_idx,
                    'article_index' => (int) $art_idx,
                    'metadata'      => isset( $article['metadata'] ) && is_array( $article['metadata'] )
                        ? $article['metadata']
                        : array(),
                    'share_links'   => isset( $article['share_links'] ) && is_array( $article['share_links'] )
                        ? $article['share_links']
                        : array(),
                    'body_html'     => $body,
                );
                if ( '' !== $article_id ) {
                    $meta['source_id'] = $article_id;
                }

                try {
                    $items[] = new DHPS_Content_Item(
                        $item_id, $service, $title, 'news',
                        $body, null, null, null, null, null, array(), $group_name, $meta
                    );
                    ++$group_count;
                } catch ( \Throwable $e ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
                        error_log( sprintf(
                            'DHPS mio_news_to_collection skip item group=%d idx=%d: %s',
                            $group_idx, $art_idx, $e->getMessage()
                        ) );
                    }
                    continue;
                }
            }

            $groups_order[]              = $group_name;
            $groups_meta[ $group_name ]  = array( 'article_count' => $group_count );
        }

        $collection_meta = array_merge(
            array(
                'is_news'        => true,
                'is_search'      => false,
                'groups_order'   => $groups_order,
                'groups_meta'    => $groups_meta,
                'pagination'     => array(
                    'current'  => isset( $pagination['current'] ) ? (int) $pagination['current'] : 1,
                    'has_more' => ! empty( $pagination['has_more'] ),
                ),
                'total_articles' => count( $items ),
            ),
            $extra_meta
        );

        try {
            return new DHPS_Content_Collection( $service, $items, $collection_meta );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
                error_log( sprintf(
                    'DHPS mio_news_to_collection failed: %s', $e->getMessage()
                ) );
            }
            return null;
        }
    }
}
```

### AJAX-Handler-Patch #1: `DHPS_MMB_AJAX_Handler::handle_request`

Position: nach Schritt 11 (Kategorie gefunden), VOR Schritt 12 (Render).

```php
// v0.18.2 TD-V0171-2: Collection-Side-Channel fuer DTO-Konsistenz.
// JSON-Response bleibt BYTEWISE UNVERAENDERT.
if ( function_exists( 'dhps_mmb_category_to_collection' ) ) {
    $category_collection = dhps_mmb_category_to_collection(
        $category,
        $service,
        array( 'layout' => $layout )
    );

    /**
     * Action: erlaubt Plugins/Themes die Lazy-Kategorie als Collection
     * zu konsumieren. Default-Verhalten unveraendert.
     *
     * @since 0.18.2
     *
     * @param DHPS_Content_Collection|null $category_collection
     * @param array                        $category
     * @param string                       $service
     */
    do_action( 'dhps_mmb_category_collection', $category_collection, $category, $service );
}
```

### AJAX-Handler-Patch #2: `DHPS_AJAX_Proxy::handle_news_request`

Position: nach Schritt 7 (`$parsed = $this->news_parser->parse(...)`),
VOR Schritt 8 (Cache-Write) - alternativ VOR `wp_send_json_success`,
das ist semantisch gleich.

```php
// v0.18.2 TD-V0174-1: Collection-Side-Channel fuer DTO-Konsistenz.
// JSON-Response bleibt BYTEWISE UNVERAENDERT.
if ( function_exists( 'dhps_mio_news_to_collection' ) ) {
    $extra_meta = array(
        'page'         => $page,
        'search'       => $search,
        'month'        => $month,
        'year'         => $year,
        'rubriken'     => $rubriken,
        'zielgruppen'  => $zielgruppen,
        'fachgebiet'   => $fachgebiet,
        'variante'     => $variante,
        'anzahl'       => $anzahl,
        'teasermodus'  => $teasermodus,
    );
    $news_collection = dhps_mio_news_to_collection( $parsed, $service_tag, $extra_meta );

    /**
     * Action: erlaubt Plugins/Themes die News-Response als Collection
     * zu konsumieren. Default-Verhalten unveraendert.
     *
     * @since 0.18.2
     *
     * @param DHPS_Content_Collection|null $news_collection
     * @param array                        $parsed
     * @param string                       $service_tag
     */
    do_action( 'dhps_news_collection', $news_collection, $parsed, $service_tag );
}
```

## Tags + Versions-Sprung

- `Deubner_HP_Services.php`: `0.18.1` -> `0.18.2`
- Git-Tag: `v0.18.2-rc.1` (semver-konform mit Punkt vor `rc.N`)
- Channel: stable nach Stage-Smoke-Promotion

## Schema-Vertrag-Vorgehen

17. Iteration ohne Critical-Drift. Pattern bewaehrt:

- Discovery -> Plan -> Pflicht-Lesung -> Schema-Vertrag -> Lead-Tests -> Stage-Smoke -> Promotion.
- 0 Adapter-Aenderungen, 0 Parser-Aenderungen, 0 Frontend-Vertrag-Aenderungen.
- Pure additiver Code, alle Aenderungen function_exists/`do_action`-gated.
