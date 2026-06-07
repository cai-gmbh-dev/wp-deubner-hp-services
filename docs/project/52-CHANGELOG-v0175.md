# Changelog v0.17.5 - Tech-Debt-Cleanup-Tranche

## Stand: 2026-06-07

## Mission

Nach v0.17.4 (9/9 Adapter komplett) waren 8 Tech-Debt-Tickets offen. v0.17.5
arbeitet davon **3 ab** (niedriges Risiko + hoher Wert), 5 verbleiben fuer
v0.17.6 / v0.18.0.

## Scope-Entscheidung

| Ticket | Status | Aufwand | Risiko |
|--------|--------|---------|--------|
| TD-V0173-1 [mio_termine]-Bridge | **v0.17.5** | S-M | NIEDRIG |
| TD-V0171-3 MMB-Search-AJAX Helper-Side-Channel | **v0.17.5** | M | NIEDRIG |
| TD-V0174-2 Sub-Shortcode-Pattern-Doku | **v0.17.5** | S | 0 |
| TD-V0171-2 MMB-Lazy-Akkordeon-AJAX | v0.17.6 | M-H | MITTEL |
| TD-V0174-1 MIO-News-Container-AJAX | v0.17.6 | H | MITTEL-H |
| TD-V0172-1 tp/compact.php | v0.18.0 | H | HOCH (JS) |
| TD-V0172-2 Datum-Normalisierung TP | v0.18.0 | M | NIEDRIG |
| TD-V0173-2 Datum-Normalisierung MIO | v0.18.0 | M | NIEDRIG |

**Pure Lead-Direct** (kein Specialist - Scope ~250 LOC + Tests).

## Hauptaenderungen

### TD-V0173-1: [mio_termine]-Bridge

`includes/class-dhps-steuertermine.php`:

- Neue Konstante `FORCE_LEGACY_ATTS = ['month', 'count']`
- Neue Methode `get_collection( array $atts, array $parsed_data ): ?DHPS_Content_Collection`
  - Force-Legacy bei `month != 'all'` ODER `count > 0` (Default-Wert-Toleranz beruecksichtigt)
  - Sonst: ruft `dhps_build_collection_for( 'mio', $parsed_data )` (Helper aus v0.17.1)
- Methode `render_template` um optionalen 4. Parameter `?DHPS_Content_Collection $collection = null` erweitert
- `render()` ruft `get_collection()` und reicht das Ergebnis an `render_template` weiter

`public/views/steuertermine/default.php`, `card.php`, `compact.php`, `inline.php` (4 Templates):

- Pseudo-Rebuild-Block am Kopf einfuegt
- Bei `$has_collection`: aus Collection per `dhps_mio_item_to_legacy_month()` (Helper aus v0.17.3) das `$data`-Array rekonstruieren
- Render-Code darunter **bytewise unveraendert** - identische BEM-Klassen, identische Iteration, identische `esc_html`-Aufrufe

### TD-V0171-3: MMB-Search-AJAX Helper-Side-Channel (Option D)

`includes/dhps-content-helpers.php`:

- Neue Funktion `dhps_mmb_search_to_collection( array $parsed_search, string $service ): ?DHPS_Content_Collection`
- Mappet `{results[], total_count, query}` -> `DHPS_Content_Collection` mit
  Items `type='document'`, Item-ID `{service}-search-doc-{idx-or-source-id}`,
  Item-meta `{result_index, source_id?, pdf_params?}`
- Collection-Meta `{total_count, query, is_search: true}`
- Defensive: Items ohne Title skip, fail-soft try/catch um Item-Konstruktion + Collection-Konstruktion

`includes/class-dhps-ajax-proxy.php` `handle_mmb_search`:

- Nach `parser->parse()` neuer Helper-Aufruf + Action-Hook
- **JSON-Response BYTEWISE UNVERAENDERT** (Frontend-JS-Vertrag erhalten)
- Action-Hook `dhps_mmb_search_collection( $collection, $parsed, $service_tag )` als
  Side-Channel fuer Plugins/Themes (Default-Verhalten unangetastet)

### TD-V0174-2: Sub-Shortcode-Pattern-Doku-Klarstellung

`docs/architecture/32-SUB-SHORTCODE-PATTERN.md` (NEU):

- **Final-Antwort** auf die unscharfe Frage "Sub-Adapter fuer Sub-Shortcodes?"
  -> **Nein, Anti-Pattern.**
- Inventar aller 4 aktuellen Sub-Shortcodes + AJAX-Sub-Pfade
- 3 Patterns dokumentiert:
  1. Template-Pattern (Pseudo-Rebuild via Helper)
  2. Sub-Shortcode-Bridge (`get_collection` mit Force-Legacy)
  3. AJAX-Helper-Side-Channel (Helper + Action-Hook)
- **Anti-Pattern-Liste** (was NICHT zu tun ist)
- Helper-Funktion-Inventar
- Roadmap

## Tests

`test-v0175-bridge.php` (Lead-Smoke):

- T1-T9 TD-V0173-1: Steuertermine `get_collection` + Force-Legacy-Logik
- T10-T13 TD-V0171-3: Helper `dhps_mmb_search_to_collection` mit leeren/3 Results + MIL-Service

**Resultat: 25 PASS / 0 FAIL**

## Backward Compatibility

**Vollstaendig BC**:

- 9 Adapter-Klassen unveraendert
- 9 Parser unveraendert
- AJAX-Handler-JSON-Response bytewise unveraendert (`wp_send_json_success($parsed)` unangetastet)
- Render-Code in 4 Steuertermine-Templates bytewise unveraendert (nur Pseudo-Rebuild oben einfuegt)
- `render_template`-Signatur erweitert um optionalen 4. Parameter (Default `null` - BC)
- 9 Service-Tags aktiv: mio/lxmio/tp/tpt/lp/mmb/mil/maes/tc

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/31-TECH-DEBT-CLEANUP-PLAN-v0175.md` | Discovery |
| `docs/architecture/32-SUB-SHORTCODE-PATTERN.md` | Architektur-Referenz (TD-V0174-2) |
| `docs/project/52-CHANGELOG-v0175.md` | (dieses Dokument) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.17.4 -> 0.17.5 |
| `README.md` | Version-Bump |
| `includes/class-dhps-steuertermine.php` | FORCE_LEGACY_ATTS + get_collection + render_template-Signatur (TD-V0173-1) |
| `includes/dhps-content-helpers.php` | Helper dhps_mmb_search_to_collection (TD-V0171-3) |
| `includes/class-dhps-ajax-proxy.php` | Helper-Call + Action-Hook in handle_mmb_search (TD-V0171-3) |
| `public/views/steuertermine/default.php` | Pseudo-Rebuild-Block |
| `public/views/steuertermine/card.php` | Pseudo-Rebuild-Block |
| `public/views/steuertermine/compact.php` | Pseudo-Rebuild-Block |
| `public/views/steuertermine/inline.php` | Pseudo-Rebuild-Block |
| `MEMORY.md` | MILESTONE 23 + 7 v0.17.5 Implementation-Notes |

## Verbleibende Tech-Debt-Tickets

| Ticket | Geplant |
|--------|---------|
| TD-V0171-2 MMB-Lazy-Akkordeon-AJAX | v0.17.6 |
| TD-V0174-1 MIO-News-Container-AJAX | v0.17.6 |
| TD-V0172-1 tp/compact.php (JS-Refactor) | v0.18.0 |
| TD-V0172-2 Datum-Normalisierung TP | v0.18.0 |
| TD-V0173-2 Datum-Normalisierung MIO | v0.18.0 |

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **v0.17.6** | MMB-Lazy-Akkordeon-AJAX + News-Container-AJAX (Helper-Side-Channel-Pattern wiederverwenden) |
| **v0.18.0** | **Legacy-Pfad in Templates entfernen** (else-Branches raus, Pipeline einzige Datenquelle) |

## Bilanz v0.17.5

- **3 Tech-Debt-Tickets erledigt** ohne neue Klassen/Brueche
- **25/25 Tests PASS**
- **0 BC-Bruch** (4 Templates Render-Code bytewise unveraendert)
- **AJAX-Response bytewise unveraendert** (Frontend-JS unangetastet)
- **Anti-Pattern dokumentiert** (Sub-Adapter-Frage final geklaert)
- Schema-Vertrag-Vorgehen **14x in Folge** ohne Critical-Drift
- **Cleanup-Pattern** etabliert: Helper-Side-Channel + Action-Hook fuer kuenftige AJAX-Migrationen
