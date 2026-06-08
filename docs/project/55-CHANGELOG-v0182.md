# Changelog v0.18.2 - AJAX-Migrationen (Helper-Side-Channel)

## Stand: 2026-06-08

## Mission

Letzte 2 offene AJAX-Tech-Debt-Tickets aus v0.17.x:

- **TD-V0171-2**: MMB-Lazy-Akkordeon-AJAX-Handler auf Helper-Side-Channel
- **TD-V0174-1**: MIO-News-Container-AJAX-Endpoint auf Helper-Side-Channel

**Helper-Side-Channel-Pattern aus v0.17.5 1:1 wiederverwendet** (3. Iteration).

## Strategie: Option D (Helper-only Side-Channel)

Discovery 35-AJAX-MIGRATION-PLAN-v0182 bestaetigt die in v0.17.5 etablierte
Option D als richtige Wahl:

- JSON-Response BYTEWISE UNVERAENDERT (Frontend-JS-Vertrag)
- Helper liefert Collection als Side-Channel
- Action-Hook fuer Plugins/Themes-Konsumenten
- Pure Lead-Direct (kein Specialist - Pattern ausgereift)

## Hauptaenderungen

### TD-V0171-2: MMB-Lazy-Akkordeon-AJAX

`includes/dhps-content-helpers.php`:

- Neuer Helper `dhps_mmb_category_to_collection( array $category, string $service ): ?DHPS_Content_Collection`
- Mappet MMB-Category-Shape -> Collection mit Items type='document'
- Item-ID `{service}-cat-{cat_id}-doc-{sheet_id-or-idx}` (disambiguiert gegen Search-Items)
- Item-meta: source_id, pdf_params, category_id, sheet_index
- Collection-Meta: category_id, category_name, icon_slug, item_count, is_lazy_category=true
- Fail-Soft try/catch um Item+Collection-Konstruktor

`includes/class-dhps-mmb-ajax-handler.php` `handle_category_load`:

- Nach Response-Build neuer Helper-Aufruf + Action-Hook
- **JSON-Response BYTEWISE UNVERAENDERT**
- Action-Hook `dhps_mmb_category_collection( $collection, $category, $service_tag )` als Side-Channel

### TD-V0174-1: MIO-News-Container-AJAX

`includes/dhps-content-helpers.php`:

- Neuer Helper `dhps_mio_news_to_collection( array $parsed_news, string $service ): ?DHPS_Content_Collection`
- **Erste Produktivnutzung des `'news'`-Item-Types** (in ALLOWED_TYPES seit v0.17.0 vorbehalten)
- Mappet `{groups[], pagination{}}` -> Collection mit Items type='news'
- Item-ID `{service}-news-{group_idx}-{article_id-or-idx}`
- Item-body = body_html (Roh-HTML aus Parser, Trust-Layer wie immer)
- Item-meta: group_index, group_name, body_html (Duplikat fuer Frontend-JS-Konvention),
  source_id, metadata (topic/target/etc.), share_links (durchgereicht)
- Collection-Meta: groups_order, pagination (mit Defaults `{current:1, has_more:false}`), is_news=true

`includes/class-dhps-ajax-proxy.php` `handle_news_request`:

- Nach `news_parser->parse()` neuer Helper-Aufruf + Action-Hook
- **JSON-Response BYTEWISE UNVERAENDERT**
- Action-Hook `dhps_news_collection( $collection, $parsed, $service_tag )` als Side-Channel
- Service-Tag immer 'mio' (LXMIO-News laeuft NICHT durch diesen Endpoint)

## Tests

`test-v0182-ajax.php` (Lead-Smoke):

- T1-T4 TD-V0171-2: MMB-Category-Helper mit empty/3-sheets + MIL-Service-Tag
- T5-T11 TD-V0174-1: MIO-News-Helper mit empty/3-articles + Pagination-Defaults +
  Article-Skip + LXMIO-Service-Tag

**Resultat: 46 PASS / 0 FAIL**

## Backward Compatibility

**Vollstaendig BC**:

- JSON-Responses BEIDE bytewise unveraendert (verifiziert durch Code-Inspektion + Stage-Smoke)
- Frontend-JS-Vertraege unangetastet
- Cache-Verhalten unangetastet
- Nonce-Checks unveraendert
- Input-Sanitisierung unveraendert
- 9 Adapter-Klassen unveraendert
- 9 Parser unveraendert
- Templates unveraendert
- Pipeline unveraendert
- 9 Service-Tags aktiv: mio/lxmio/tp/tpt/lp/mmb/mil/maes/tc
- `echo $tc_html` Trust-Decision unangetastet
- Stage-Smoke Page 6 = 76 dhps-Klassen bytewise stabil

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/35-AJAX-MIGRATION-PLAN-v0182.md` | Discovery |
| `docs/project/55-CHANGELOG-v0182.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.18.1 -> 0.18.2 |
| `README.md` | Version-Bump |
| `includes/dhps-content-helpers.php` | 2 neue Helper-Funktionen (~285 LOC additiv) |
| `includes/class-dhps-mmb-ajax-handler.php` | Helper-Call + Action-Hook in handle_category_load |
| `includes/class-dhps-ajax-proxy.php` | Helper-Call + Action-Hook in handle_news_request |
| `MEMORY.md` | MILESTONE 26 + 7 v0.18.2 Implementation-Notes |

## Helper-Pool nach v0.18.2

| Helper | Pfad | Verwendung |
|--------|------|------------|
| `dhps_build_collection_for($service, $parsed)` | content-helpers | Sub-Shortcode-Bridges |
| `dhps_collection_or_empty($col, $service)` | content-helpers | Pipeline-Garantie 3.B |
| `dhps_mmb_search_to_collection($parsed, $service)` | content-helpers | v0.17.5 MMB-Search |
| **`dhps_mmb_category_to_collection($cat, $service)`** | content-helpers | **v0.18.2 NEU** |
| **`dhps_mio_news_to_collection($parsed, $service)`** | content-helpers | **v0.18.2 NEU** |
| `dhps_mmb_collection_to_legacy_categories($col)` | content-helpers | MMB-Templates |
| `dhps_tp_collection_to_legacy_categories($col)` | tp-content-helpers | TP-Templates |
| `dhps_tp_item_to_legacy_video($item)` | tp-content-helpers | TP/TPT/LP-Templates |
| `dhps_mio_item_to_legacy_month($item)` | mio-content-helpers | MIO/Steuertermine-Templates |
| `dhps_partial_date_to_iso($input, $format)` | date-helpers | v0.18.1 Adapter-Beimaterial |

**10 Helper-Funktionen** in 4 Helper-Files.

## Action-Hook-Inventar (Side-Channels)

| Hook | Seit | Trigger | Parameter |
|------|------|---------|-----------|
| `dhps_mmb_search_collection` | v0.17.5 | MMB/MIL-Search-AJAX | $col, $parsed, $service_tag |
| **`dhps_mmb_category_collection`** | **v0.18.2** | **MMB/MIL-Lazy-Akkordeon-AJAX** | $col, $category, $service_tag |
| **`dhps_news_collection`** | **v0.18.2** | **MIO-News-Container-AJAX** | $col, $parsed, 'mio' |

## Verbleibende Tech-Debt-Tickets

**KEINE** offenen Tech-Debt-Tickets aus dem v0.17.x-Cleanup-Block mehr. Alle 8
in v0.17.4 dokumentierten Tickets sind erledigt:

| Ticket | Release |
|--------|---------|
| TD-V0171-2 MMB-Lazy-Akkordeon-AJAX | **v0.18.2** |
| TD-V0171-3 MMB-Search-AJAX | v0.17.5 |
| TD-V0172-1 tp/compact.php | v0.18.0 |
| TD-V0172-2 Datum-Normalisierung TP | v0.18.1 |
| TD-V0173-1 [mio_termine] Bridge | v0.17.5 |
| TD-V0173-2 Datum-Normalisierung MIO | v0.18.1 |
| TD-V0174-1 MIO-News-Container-AJAX | **v0.18.2** |
| TD-V0174-2 Sub-Shortcode-Pattern-Doku | v0.17.5 |

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.19.0** | `$data`-Param aus Template-Scope entfernen + evtl. Collection::sort_by_date_iso() Hook |
| **v0.18.3** | Polish / weitere kleine Tech-Debt-Tickets |

## Bilanz v0.18.2

- **2 Tech-Debt-Tickets erledigt** (TD-V0171-2 + TD-V0174-1)
- **8/8 v0.17.4-Tech-Debt-Tickets** komplett erledigt
- **Lead-Tests 46/46 PASS**
- **0 BC-Bruch** (2 JSON-Responses bytewise unveraendert, alle anderen Layer unangetastet)
- **2 neue Helper-Funktionen** (Helper-Pool nun 10 Funktionen)
- **2 neue Action-Hooks** (3 Side-Channels total)
- **'news' Item-Type erstmals produktiv** (DTO-Whitelist-Reservierung aus v0.17.0 eingeloest)
- Schema-Vertrag-Vorgehen **17x in Folge** ohne Critical-Drift
- **Helper-Side-Channel-Pattern** 3x produktiv (ausgereift)
