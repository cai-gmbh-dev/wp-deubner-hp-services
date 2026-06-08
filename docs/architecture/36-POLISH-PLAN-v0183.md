# Polish-Plan v0.18.3 - $extra_meta-Bridge + Pattern-Doc-Konsolidierung

## Stand: 2026-06-08 (Discovery-Phase v0.18.3)

## Mission

Zwei Info-Findings aus dem v0.18.2-QA-Report sauber abarbeiten, ohne
BC-Bruch und ohne neue Komplexitaet einzufuehren:

- **F1**: Helper-Signaturen weichen vom Discovery-Plan 35 ab
  (`$extra_meta` fehlt in der Implementation der beiden v0.18.2-Helper -
  und konsequenterweise auch in den beiden anderen Side-Channel-Helfern
  ohne den Param: `dhps_mmb_search_to_collection` aus v0.17.5).
- **F4**: Naming-Drift `sheet_index` (v0.18.2 MMB-Category-Helper) vs.
  `result_index` (v0.17.5 MMB-Search-Helper). Beide sind in Side-Channels
  vergraben (kein produktiver Konsument), aber Cross-Helper-Konvention
  fehlt.

Ziel: **18. Schema-Vertrag-Iteration ohne Critical-Drift**.

## Empfehlung: Option C (Hybrid) - eingeschraenkt auf 2 Helfer

| Action | Helfer | Begruendung |
|--------|--------|-------------|
| `$extra_meta`-Param NACHRUESTEN | `dhps_mmb_category_to_collection` (v0.18.2) | Plan-35 nennt explizit `['layout' => $layout]` als Use-Case (sichtbar im AJAX-Handler-Code-Block). |
| `$extra_meta`-Param NACHRUESTEN | `dhps_mio_news_to_collection` (v0.18.2) | Plan-35 nennt explizit Filter-Atts als Use-Case (`['page', 'search', 'month', 'year', ...]`). |
| `$extra_meta`-Param **NICHT** nachruesten | `dhps_mmb_search_to_collection` (v0.17.5) | Hat den Search-Query bereits im Collection-Meta (`query`), kein zweiter Aufrufer in Sicht. Beibehalten = weniger Surface. |
| `$extra_meta`-Param **NICHT** nachruesten | `dhps_build_collection_for` (v0.17.1) | Ist ein Registry-Dispatcher, nicht ein Mapping-Helper. Adapter macht Collection-Meta selbst. |
| Doku in Pattern-Doc | alle 4 Side-Channel-Helfer | Cross-Helper-Naming-Konvention + 10-Helper-Inventar + 3-Hook-Inventar. |
| Naming-Drift F4 | Doku-only, KEIN Code-Refactor | `sheet_index` und `result_index` sind beide semantisch sinnvoll. Refactor 1:1 austauschen waere kosmetisch ohne Beobachter. |

**Aufwand**: S (~50 LOC Code-Edit + ~80 Zeilen Doku-Update + ~8 Tests).

**Kein neuer Helfer, kein neuer Hook, kein neues Pattern**.

## Sektion 1: F1 Analyse - $extra_meta-Param

### Use-Case-Analyse

`$extra_meta` macht Sinn, wenn der AJAX-Handler **Kontext** besitzt, den
weder der Parser noch der Helper allein kennt (z.B. Request-Parameter,
Layout-Hint). Ohne `$extra_meta` muesste der Handler entweder:

1. Den Collection-Meta-Eintrag NACH dem Helper-Aufruf nachpatchen
   (Anti-Pattern: Collection ist Lead-Direct unveraenderlich nach
   Konstruktion). Aktuell **NICHT moeglich** ohne Reflection.
2. Den Hook-Konsumenten zwingen, sich die Atts aus dem `$category`-Array
   bzw. `$_POST` selbst zu fischen (Brittle: Konsument ist auf den
   Side-Channel angewiesen, hat aber die Request-Parameter nicht).

Plan-35-Beispiele:

```php
// MMB-Lazy-Akkordeon
$category_collection = dhps_mmb_category_to_collection(
    $category,
    $service,
    array( 'layout' => $layout )   // <-- $extra_meta
);

// MIO-News-Container
$news_collection = dhps_mio_news_to_collection(
    $parsed,
    $service_tag,
    array(
        'page' => $page, 'search' => $search,
        'month' => $month, 'year' => $year,
        'rubriken' => $rubriken, /* ... */
    )
);
```

**Konkreter Nutzen** fuer Hook-Konsumenten:

- Analytics-Plugin schreibt `$collection->get_meta('layout')` direkt
  in Tracker-Payload, ohne Request-Inspection.
- Theme-Override-Renderer entscheidet Card vs. Default per
  `$collection->get_meta('layout')`.
- Search-Audit-Logger persistiert die Suchparameter zusammen mit dem
  Treffer-Count (`total_articles`).

### Optionen-Vergleich

| Variante | Wirkung | Bewertung |
|----------|---------|-----------|
| **B.1** (Collection-Meta-Merge) | `$extra_meta` flach in `meta`-Array gemerged | **EMPFEHLUNG**. Aufruf-Kontext ist Collection-Level-Info. Kostet 1 `array_merge`. |
| B.2 (Item-Meta-Merge) | `$extra_meta` in jedes Item-meta gemerged | Verworfen: 100x-Duplizierung bei 100 Items, semantisch falsch (Layout ist nicht item-spezifisch). |
| B.3 (Beides) | Beide gemerged | Verworfen: doppelte Schreibarbeit, Doppel-Quelle-of-Truth. |

**Empfehlung B.1**: `$extra_meta` wird VOR dem `is_lazy_category`/`is_news`-
Default in die Collection-Meta-Map gemerged. Eigene Helper-Defaults
gewinnen bei Key-Kollision (defensive Reservierung des Schluessel-Namens
`is_lazy_category`/`is_news`/`groups_order`/`pagination`/...).

```php
$collection_meta = array_merge(
    $extra_meta,  // 1) Aufruf-Kontext zuerst
    array(        // 2) Helper-Defaults dominieren
        'is_lazy_category' => true,
        // ...
    )
);
```

**Sicherheits-Aspekt** (B.1):

- Bei Key-Kollision (`$extra_meta = ['is_news' => false]` versucht zu
  ueberschreiben) gewinnen die Helper-Defaults. **Side-Channel-Invariante
  bleibt erhalten.**
- JSON-Response ist NICHT betroffen (`$extra_meta` landet NUR in der
  Collection-Meta, nicht in der Response). Frontend-JS-Vertrag bleibt
  bytewise stabil.

## Sektion 2: F4 Analyse - Naming-Drift

| Helfer | Schluessel | Semantik |
|--------|-----------|----------|
| `dhps_mmb_search_to_collection` | `result_index` | "Index in der Search-Result-Liste" |
| `dhps_mmb_category_to_collection` | `sheet_index` | "Index im fact_sheets-Array dieser Kategorie" |
| `dhps_mio_news_to_collection` | `article_index` + `group_index` | "Index innerhalb der Gruppe" + "Index der Gruppe" |

### Konvention-Optionen

| Option | Aktion | Bewertung |
|--------|--------|-----------|
| F4.A | Refactor: alle Helfer auf `result_index` umstellen | Verworfen: `article_index`/`group_index` sind semantisch klarer als ein generisches `result_index`. |
| **F4.B** | Doku: "Kontext-spezifischer Name" ist offizielle Konvention | **EMPFEHLUNG**. Pattern-Doc-Klausel. Kein Code-Touch. |
| F4.C | Nichts dokumentieren (Status Quo) | Verworfen: naechste Iteration laeuft in dieselbe Frage. |

**Empfehlung F4.B**: Konvention im Pattern-Doc 32 festhalten:

> Item-Meta-Schluessel fuer Schleifen-Indices folgen dem **Kontext-Namen**
> der iterierten Source-Liste:
> - `result_index` bei Search-Result-Listen
> - `sheet_index` bei Fact-Sheets pro Kategorie
> - `article_index` bei Articles innerhalb einer News-Gruppe
> - `group_index` bei Gruppen-Loop in flacher Item-Liste
>
> Konsumenten sollten den jeweiligen Namen aus dem Helper-Docblock lesen.
> Ein generisches `result_index` wuerde Semantik kaschieren.

**BC-Impact F4**: 0. Kein Konsument liest heute `sheet_index` oder
`result_index` (interner Side-Channel-Schluessel). Dokumentation ist
forward-looking.

## Sektion 3: Pattern-Doku-Update (32-SUB-SHORTCODE-PATTERN.md)

Insertions in der Reihenfolge der Doku:

1. **Sektion "Inventar der Helper-Pattern"** auf 10 Helfer erweitern:
   `dhps_collection_or_empty`, `dhps_mmb_search_to_collection`,
   `dhps_mmb_category_to_collection`, `dhps_mio_news_to_collection`,
   `dhps_mmb_collection_to_legacy_categories`,
   `dhps_tp_collection_to_legacy_categories`,
   `dhps_partial_date_to_iso` zusaetzlich zu den 3 bereits dokumentierten.

2. **NEUE Sektion "Action-Hook-Inventar (Side-Channels)"** mit den 3
   produktiven Hooks (`dhps_mmb_search_collection`,
   `dhps_mmb_category_collection`, `dhps_news_collection`) plus Signatur
   und Seit-Version.

3. **NEUE Sektion "Konvention: $extra_meta-Param fuer Side-Channel-Helfer"**:
   - Param-Position: immer 3. Param, Default `array()`.
   - Merge-Order B.1: `array_merge( $extra_meta, $defaults )` -
     Helper-Defaults gewinnen.
   - Wann anbieten: wenn der AJAX-Handler Request-Kontext besitzt
     (Layout-Hint, Filter-Atts) den der Parser/Helper nicht aus den
     Daten herleiten kann.
   - Wann NICHT anbieten: bei reinen Lookup-Helfern, die schon alle
     relevanten Felder aus dem Parser-Output lesen
     (`dhps_mmb_search_to_collection`).

4. **NEUE Sektion "Konvention: Item-Meta-Indices"** wie in Sektion 2.B
   oben skizziert.

5. **Roadmap-Tabelle aktualisieren**: TD-V0171-2 und TD-V0174-1 auf
   "v0.18.2 done" setzen. Neue Zeile fuer F1/F4-Polish-Status.

Geschaetzte Doku-Aenderung: **~80 Zeilen** additiv, keine Loeschungen.

## Sektion 4: Tests (T1-T8)

`test-v0183-polish.php` als Plugin-Root-Smoke (analog v0.18.2).

- **T1**: `dhps_mmb_category_to_collection( $cat, 'mmb', array( 'layout' => 'card' ) )`
  -> Collection-Meta `layout === 'card'`.
- **T2**: `dhps_mmb_category_to_collection( $cat, 'mmb', array() )`
  -> Collection-Meta enthaelt alle bisherigen Defaults + KEIN `layout`-Key.
- **T3**: `dhps_mmb_category_to_collection( $cat, 'mmb', array( 'is_lazy_category' => false ) )`
  -> Collection-Meta `is_lazy_category === true` (Helper-Default
  gewinnt, Defensive-Invariante).
- **T4**: `dhps_mio_news_to_collection( $parsed, 'mio', array( 'page' => 3, 'month' => '03' ) )`
  -> Collection-Meta `page === 3`, `month === '03'`.
- **T5**: `dhps_mio_news_to_collection( $parsed, 'mio', array() )`
  -> Collection-Meta unveraendert ggue. v0.18.2 (BC).
- **T6**: `dhps_mio_news_to_collection( $parsed, 'mio', array( 'pagination' => array( 'fake' => true ) ) )`
  -> Collection-Meta `pagination === { current, has_more }` (Helper-Default
  gewinnt - Side-Channel-Invariante).
- **T7**: JSON-Response-Shape pre vs. post Patch BYTEWISE identisch fuer
  MMB-Lazy-Akkordeon-Handler (Smoke gegen Stage).
- **T8**: JSON-Response-Shape pre vs. post Patch BYTEWISE identisch fuer
  MIO-News-Handler (Smoke gegen Stage).

**Target: 8 / 8 PASS**.

## Sektion 5: Spec-Aufteilung

**Pure Lead-Direct** (KEIN Specialist).

Begruendung:

- Scope < 50 LOC Code + 80 Zeilen Doku.
- Etabliertes Pattern (Helper-Side-Channel) - 4. Iteration.
- BC additiv: neue Default-Wert-Argumente, keine Signatur-Breaks.
- Kein neuer Hook, keine neue Klasse, kein neuer Test-Stack.
- Pattern-Doku-Edit ist 1-Datei-Touch, kein Cross-File-Refactor.

Sub-Phasen (sequentiell):

- **P1** (~10 LOC): Helper-Signaturen erweitern (2 Helfer), Docblock-Update.
- **P2** (~20 LOC): AJAX-Handler-Aufrufe aktualisieren (MMB-Lazy mit `layout`,
  News-Handler mit Filter-Atts).
- **P3** (~80 Zeilen): Pattern-Doku-Update (10-Helper-Inventar +
  3-Hook-Inventar + 2 neue Konvention-Sektionen).
- **P4**: `test-v0183-polish.php` mit T1-T6 (Lead-Smoke).
- **P5**: Stage-Smoke T7+T8 (Snapshot-Diff JSON pre/post).
- **P6**: Changelog v0.18.3 + Version-Bump + MEMORY-Update.

## Sektion 6: Risiken + BC

### Top-3-Risiken

#### R1 - Key-Kollision in $extra_meta-Merge (NIEDRIG)

**Was**: Wenn `$extra_meta = ['is_news' => false]` an `dhps_mio_news_to_collection`
gereicht wird, koennte die Side-Channel-Invariante (`is_news === true`
markiert News-Collections) brechen.

**Mitigation**: `array_merge`-Order B.1 stellt sicher dass Helper-Defaults
gewinnen (`$extra_meta` zuerst, Defaults danach). Test T3 + T6 decken den
Kollision-Pfad explizit ab. Defensive-Doc im Pattern-Doc-Sektion klaert
das.

#### R2 - JSON-Response-Drift (NIEDRIG)

**Was**: Wenn der Patch versehentlich `$response['extra']` mitsendet,
bricht der Frontend-JS-Vertrag.

**Mitigation**: `$extra_meta` landet AUSSCHLIESSLICH in der Collection-Meta
(Side-Channel). `wp_send_json_success( $parsed )` bzw.
`wp_send_json_success( $response )` bleiben unangetastet. Tests T7+T8
catchen Drift bytewise.

#### R3 - Naming-Drift entkommt der Doku (NIEDRIG-MITTEL)

**Was**: Naechste Iteration baut Helfer Nr. 5 mit `item_index`-Schluessel,
ignoriert die F4.B-Konvention.

**Mitigation**: Pattern-Doc-Sektion mit Beispielen + explizitem
"Anti-Pattern: generisches `result_index` ohne Kontext-Hint". Stage-Smoke
und Lead-Tests koennen es nicht catchen - Bewusstseinsbildung allein
muss reichen. Risiko-Toleranz akzeptiert: Naming-Drift hat 0 BC-Impact
da Side-Channel nicht oeffentlich konsumiert wird.

### BC-Impact

- 0 Frontend-JS-Aenderungen.
- 0 JSON-Response-Aenderungen.
- 0 Adapter-Aenderungen.
- 0 Parser-Aenderungen.
- 0 Template-Aenderungen.
- 0 REST-Aenderungen.
- 2 Helper-Signaturen erweitert (3. Param optional mit Default).
  `function_exists`-Guards in den AJAX-Handlern bleiben - aber Helfer
  sind keine Plugin-API mit externen Konsumenten (interner Side-Channel).
- 0 Bootstrap-Aenderungen.

## Sektion 7: Spec-Briefing fuer den Lead

### Helper #1: `dhps_mmb_category_to_collection` erweitern

`includes/dhps-content-helpers.php` Z. 343.

**Vorher**:

```php
function dhps_mmb_category_to_collection( array $category, string $service ): ?DHPS_Content_Collection {
```

**Nachher**:

```php
function dhps_mmb_category_to_collection(
    array $category,
    string $service,
    array $extra_meta = array()
): ?DHPS_Content_Collection {
```

Im Body, am Ende vor `try { return new DHPS_Content_Collection(...) }`:

**Vorher** (Z. 412-418):

```php
$collection_meta = array(
    'category_id'       => $category_id,
    'category_name'     => $category_name,
    'icon_slug'         => $icon_slug,
    'item_count'        => count( $items ),
    'is_lazy_category'  => true,
);
```

**Nachher**:

```php
$collection_meta = array_merge(
    $extra_meta,
    array(
        'category_id'      => $category_id,
        'category_name'    => $category_name,
        'icon_slug'        => $icon_slug,
        'item_count'       => count( $items ),
        'is_lazy_category' => true,
    )
);
```

Docblock-Erweiterung:

```php
 * @param array  $extra_meta Optionaler Aufruf-Kontext, der in das Collection-Meta
 *                           gemerged wird (z.B. `['layout' => 'card']`). Helper-Defaults
 *                           (is_lazy_category, category_id, ...) ueberschreiben bei
 *                           Key-Kollision den $extra_meta-Wert (defensive Invariante).
```

### Helper #2: `dhps_mio_news_to_collection` erweitern

`includes/dhps-content-helpers.php` Z. 474.

Analog Helper #1:

```php
function dhps_mio_news_to_collection(
    array $parsed_news,
    string $service,
    array $extra_meta = array()
): ?DHPS_Content_Collection {
```

Body-Ende (Z. 570-574 -> erweitert):

```php
$collection_meta = array_merge(
    $extra_meta,
    array(
        'groups_order' => $groups_order,
        'pagination'   => $pagination,
        'is_news'      => true,
    )
);
```

### AJAX-Handler-Patch #1: MMB-Lazy-Akkordeon

`includes/class-dhps-mmb-ajax-handler.php` Z. 316-330.

**Vorher**:

```php
$category_collection = dhps_mmb_category_to_collection( $category, $service );
```

**Nachher**:

```php
$category_collection = dhps_mmb_category_to_collection(
    $category,
    $service,
    array( 'layout' => $layout )
);
```

### AJAX-Handler-Patch #2: MIO-News-Container

`includes/class-dhps-ajax-proxy.php` Z. 182-196.

**Vorher**:

```php
$news_collection = dhps_mio_news_to_collection( $parsed, 'mio' );
```

**Nachher**:

```php
$news_collection = dhps_mio_news_to_collection(
    $parsed,
    'mio',
    array(
        'page'        => $page,
        'search'      => $search,
        'month'       => $month,
        'year'        => $year,
        'rubriken'    => $rubriken,
        'zielgruppen' => $zielgruppen,
        'fachgebiet'  => $fachgebiet,
        'variante'    => $variante,
        'anzahl'      => $anzahl,
        'teasermodus' => $teasermodus,
    )
);
```

### Pattern-Doku-Update

`docs/architecture/32-SUB-SHORTCODE-PATTERN.md`:

- Tabelle "Inventar der Helper-Pattern" erweitern auf 10 Zeilen
- NEUE Sektion "Action-Hook-Inventar (Side-Channels)" (3 Hooks)
- NEUE Sektion "Konvention: $extra_meta-Param fuer Side-Channel-Helfer"
- NEUE Sektion "Konvention: Item-Meta-Indices"
- Roadmap-Tabelle: TD-V0171-2 und TD-V0174-1 auf v0.18.2 done

### Version-Sprung

- `Deubner_HP_Services.php`: `0.18.2` -> `0.18.3`
- `README.md`: Version-Bump
- Git-Tag: `v0.18.3-rc.1`

## Schema-Vertrag-Vorgehen

18. Iteration ohne Critical-Drift erwartet.

Pattern bewaehrt:

- Discovery -> Plan -> Pflicht-Lesung -> Schema-Vertrag -> Lead-Tests -> Stage-Smoke -> Promotion.
- 0 Adapter-Aenderungen, 0 Parser-Aenderungen, 0 Frontend-Vertrag-Aenderungen.
- Pure additiver Code, neue Default-Parameter mit BC-Garantie.

## Aufwand-Schaetzung

| Phase | Aufwand |
|-------|---------|
| P1 Helper-Signatur-Erweiterung (2 Helfer) | XS (~10 LOC) |
| P2 AJAX-Handler-Patches (2 Aufrufe) | XS (~14 LOC) |
| P3 Pattern-Doku-Update (5 Inserts) | S (~80 Zeilen) |
| P4 Lead-Smoke T1-T6 | S |
| P5 Stage-Smoke T7-T8 | S |
| P6 Changelog + MEMORY + Version-Bump | S |
| **Total** | **S** |

Gesamt: **1 Pure-Lead-Direct-Release**, Scope ~25 LOC Code + ~80 Zeilen
Doku + ~6 Lead-Tests + 2 Stage-Snapshots.

## Antwort auf die Architekt-Frage

> Ist `$extra_meta`-Support echter Mehrwert oder reine Discovery-Doku-Drift?
> Was bringt es Konsumenten?

**Echter Mehrwert, aber begrenzt** auf 2 von 4 Side-Channel-Helfern.

Konkreter Konsumenten-Nutzen:

1. **Layout-Hint** (MMB-Lazy-Akkordeon): Theme-Override-Renderer und
   Analytics-Plugins koennen ohne Request-Inspection auf den AJAX-Layout-
   Param zugreifen.
2. **Filter-Atts** (MIO-News): Audit-Logger und Tracker bekommen die
   Such-/Filter-Kontext-Information VOR der Render-Layer. Heute haetten
   sie keine Quelle dafuer (Request-Globals waeren brittle).
3. **Plan-35-Kontinuitaet**: Discovery-Plan 35 hat den Param semantisch
   vorgesehen. Die v0.18.2-Implementation hat ihn entfernt, ohne den Plan
   formal zu aktualisieren - das ist klassische **Discovery-Doc-Spec-Drift**
   die jetzt korrigiert wird.

Es ist **keine reine Doku-Drift**, weil die Helpers ohne den Param keine
Bruecke zwischen Handler-Request-Kontext und Collection-Konsumenten
bieten. Mit Param ist die Bruecke explizit, dokumentiert und 1-Loc-bequem
zu nutzen.

**ABER**: `$extra_meta` ist eine **defensive Extension-API** ohne aktiven
internen Konsumenten heute. Wert ist forward-looking. Wer das ablehnt,
kann auch Option A (Doku-only) machen - dann ist F1 reine Discovery-Drift
und der Plan-35 wird formal zur Implementation v0.18.2 hin angepasst.

**Empfehlung bleibt C-eingeschraenkt** (B fuer 2 von 4 Helfern + A fuer
Pattern-Doc), weil:

- Aufwand S (~25 LOC) und 0 BC-Risiko
- Plan-35 hat die Intention klar formuliert
- Pattern-Doku-Update braucht das Inventar sowieso
- Future-Proofing fuer Hook-Konsumenten
- Konsistent mit `array_merge( $extra_meta, $defaults )`-Pattern der
  v0.17.0-Adapter (`DHPS_Content_Item::to_content_card_props` macht es
  schon so)
