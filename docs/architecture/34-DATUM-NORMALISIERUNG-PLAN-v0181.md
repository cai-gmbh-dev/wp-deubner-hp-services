# 34 - Datum-Normalisierung - Plan v0.18.1

**Status:** Discovery (2026-06-08)
**Aktuelle Plugin-Version:** v0.18.0 (Legacy-Pfad raus, Pipeline-only-Aera)
**Ziel-Version:** **v0.18.1** (Stabilisierungs-Release, KEINE BC-Brueche)
**Architekt-Auftrag:** Zwei offene Tech-Debt-Tickets zusammen:

- **TD-V0172-2:** TP/TPT/LP Datum MM/YY -> normalisierte Form
- **TD-V0173-2:** MIO Monat-Slug -> normalisierte Form

Discovery klaert welche Option (A/B/C aus v0.17.5 Discovery), wie BC-stabil
zu halten, und ob der Use-Case ueberhaupt real ist.

## Executive Summary

Empfehlung in einem Satz: **Option C (Status Quo + ISO-Beimaterial im
meta-Hash) als Pure Lead-Direct, ohne DTO-Aenderung, ohne Template-Aenderung,
ohne Helper-Anpassung an der Sichtbarkeit.**

### Begruendung (3 Saetze)

- Use-Case "sortierbares Datum" ist **nachweislich hypothetisch** - kein
  einziger Code-Pfad im Plugin (PHP + JS) sortiert nach Item.date, nach
  meta.datum, oder nach Monatstitel. Stage-Smoke seit v0.17.0 bestaetigt
  das 5 Releases lang.
- Option A (DateTimeImmutable + Default-Tag 01) liefert semantisch falsche
  Daten (User sieht "Oktober 2024", System haelt "01.10.2024") und ist
  damit gefaehrlicher als der Status Quo - jede zukuenftige date-Konsumenten-
  Logik (DateInterval-Diff, Wochentag-Anzeige) wuerde plausibel-aussehende
  aber falsche Resultate liefern.
- Option B (neues meta-Feld `date_partial` mit Format 'YM') ist sauber,
  bringt aber 0 Mehrwert ohne Konsumenten - der Helper-Side-Channel aus
  Option C liefert exakt das gleiche Beimaterial bei halbem Aufwand und
  ohne neue Schema-Permutation in der DTO-Test-Suite.

### Geschaetzter Aufwand

- **v0.18.1 (Option C):** **S (klein)**, 0.5-1 Mann-Tag netto.
  - 1 neuer Helper `dhps_partial_date_to_iso( string $input ): ?string`
    (DE-Monatsnamen + MM/YY-Parser, gemeinsam fuer TP und MIO)
  - 2 Adapter-Erweiterungen: TP-Adapter setzt `meta.date_iso`, MIO-Adapter
    setzt `meta.date_iso`
  - 0 Template-Aenderung, 0 DTO-Aenderung
  - Pure Lead-Direct
- **Option B (Alternative):** M (mittel), 1.5 Mann-Tage. Neues DTO-Schema-
  Feld `meta.date_partial` (Format-Konvention), Doku, Adapter-Tests,
  Schema-Drift-Test bestaetigt 6-Service-Meta. KEINE Empfehlung in v0.18.1.

---

## Sektion 1: Use-Case-Analyse - Brauchen wir wirklich sortierbares Datum?

### 1.1 Code-weiter Sortier-Bedarf-Scan

**Frage:** Welche Stellen im Plugin sortieren heute nach Datum?

Ergebnis aus systematischem Grep ueber `includes/`, `public/views/`, `public/js/`:

| Code-Pfad | Sortiert nach Datum? | Belege |
|-----------|----------------------|--------|
| TP-Templates (default/card/compact) | **NEIN** | iterieren ueber `$categories[].videos[]` in Parser-Reihenfolge (foreach) |
| TPT-Templates (default/card/compact) | **NEIN** | rendern 1 Featured-Video, kein Sort |
| MIO-Templates (default/card/compact) | **NEIN** | iterieren ueber `$tax_dates[]` in Parser-Reihenfolge (max 2 Monate) |
| Steuertermine-Templates | **NEIN** | Pre-Filter via `month`-Att (current/next/all) ohne Sort |
| MAES-Aktuelles | **NEIN** | nutzt News-Felder direkt (separate `date`-Quelle), kein Adapter-Sort |
| `DHPS_Content_Collection` | **NEIN** | bietet `filter()` und `group_by()`, **keine `sort_by_date()`-Methode** |
| `dhps-mio.js` | **NEIN** | `topics.sort()` ist alphabetischer Topic-Sort (Z. 997), kein Datum |
| `dhps-components-alpine.js` | **NEIN** | `this.sort` ist generischer Filter-Kontext-String (Z. 66ff), wird emitted aber nicht intern angewandt |
| AJAX-Endpoints | **NEIN** | Pagination kommt vom API-Server, Plugin sortiert nicht nach |
| Cache-Layer | **NEIN** | `ksort()` auf Cache-Key-Parametern, nicht auf Content |

**Befund:** **Null aktive Sortier-Konsumenten** ueber das gesamte Plugin. Die
Stage-Smoke-Test-Reihen v0.17.0 - v0.18.0 (5 Releases) haben null Sortier-
Tests, weil null Sortier-Use-Case existiert.

### 1.2 Konsumenten-Analyse fuer `Item.date`

Item.date wird heute von **0 Stellen** gelesen:

```
$ grep -r 'item->date\|item\["date"\]\|->date\b' includes/ public/ public/views/
includes/class-dhps-content-item.php:301:		$this->date    = $date;
includes/class-dhps-content-item.php:414:			'date' => null !== $this->date ? ... : null,
```

Nur die DTO-Klasse selbst (Konstruktor-Assignment + to_array-Serialisierung).
Keine externe Konsumenten-Stelle.

### 1.3 Hypothetische Use-Cases (was waere wenn?)

Falls in Zukunft sortierbar gebraucht wird:

- **Hypothese H1:** "Aktuelle Videos zuerst" - heute liefert das API
  bereits sortiert (neueste in `featured_video`, danach Categories nach
  Reihenfolge). Plugin sortiert nicht um.
- **Hypothese H2:** "Steuertermine chronologisch" - heute liefert das API
  2 Monatsspalten (current+next), die Reihenfolge ist API-Sache.
- **Hypothese H3:** "Filter nach Jahr/Monat in der UI" - heute nicht
  vorhanden, kein Roadmap-Item.

Alle drei Hypothesen sind: keine, keine, keine. Es gibt **kein Roadmap-
Item** das Datum-Sort braucht. Selbst der Voller-Atts-Editor (v0.15.5) hat
kein Sort-Att.

### 1.4 Schlussfolgerung Sektion 1

**Der Use-Case "sortierbares Datum" ist hypothetisch.**

Das verschiebt die Entscheidungs-Achse: nicht "welche Option liefert das
beste sortierbare Datum?", sondern **"welche Option erzeugt am wenigsten
neue Permutationen im DTO-Schema fuer 0 aktuellen Wert?"**

Antwort: **Option C** (Status Quo + Beimaterial-Helper).

---

## Sektion 2: Option-Bewertung A/B/C

### Vergleichs-Matrix

| Achse | Option A (DTI + Tag 01) | Option B (meta.date_partial) | Option C (meta.date_iso Beimaterial) |
|-------|--------------------------|-----------------------------|--------------------------------------|
| **Code-Aufwand** | M | M | S |
| **BC-Risiko** | NIEDRIG-MITTEL (semantische Falsch-Daten in Konsumenten) | NIEDRIG | 0 (additiv) |
| **Theme-Override-Impact** | 0 (meta.datum bleibt erhalten) | 0 | 0 |
| **Adapter-Aenderungen** | TP+MIO: `?DateTimeImmutable` setzen statt null + neuer Helper | TP+MIO: meta-Feld setzen + neuer Helper | TP+MIO: meta-Feld setzen + neuer Helper |
| **Template-Aenderungen** | 0 (Templates lesen weiter `meta.datum` via Helper) | 0 | 0 |
| **Helper-Aenderungen** | 1 neuer DTI-Builder | 1 neuer Format-YM-Builder | 1 neuer ISO-YM-Builder |
| **DTO-Aenderungen** | 0 (date-Feld existiert schon) | 0 (meta ist Fluchtweg) | 0 (meta ist Fluchtweg) |
| **Schema-Permutation** | aendert: 7 Services hatten date=null, jetzt 2 haben date!=null | KEINE neue DTO-Achse, aber neue meta-Konvention | KEINE neue DTO-Achse, aber neue meta-Konvention |
| **Semantik-Korrektheit** | **FALSCH** (Tag 01 erfunden) | **KORREKT** (YYYY-MM, kein Tag) | **KORREKT** (YYYY-MM, kein Tag) |
| **Sortierbar** | ja, via DTI compare | ja, via String-compare (lex == chrono fuer YYYY-MM) | ja, via String-compare |
| **Wert-fuer-Konsumenten** | Sortier-API + DateInterval | reine String-Convention, Sortier ohne Library | reine String-Convention, Sortier ohne Library |
| **Risiko-Konsumenten-Bugs** | HOCH (jeder DTI-Konsumer kann Tag-01-Bug bauen) | NIEDRIG | NIEDRIG |
| **Reversibilitaet** | sehr schwer (DTI-Werte wandern in Caches) | leicht (meta-Feld kann ignoriert werden) | leicht (meta-Feld kann ignoriert werden) |

### Empfehlung pro Option

**Option A (DTI + Tag 01)** - **VERWORFEN**.

- Die Falsch-Semantik ist Plugin-weiter Land-Mine. Sobald eine zukuenftige
  Konsumenten-Logik `$item->date->format('d.m.Y')` macht, sieht der User
  "01.10.2024" - das ist falsch und nicht erkennbar als Synthese.
- Cache-Persistenz macht Reversibilitaet teuer.
- Trust-Decision-Verletzung: TP-Adapter-Doku-Block Z. 17-22 sagt explizit
  "kein DateTimeImmutable, weil das API-Format keinen Tag liefert".

**Option B (meta.date_partial)** - **TECHNISCH OK**, aber overkill.

- Neue meta-Konvention erfordert: Doku, Format-Spec, Adapter-Tests, Schema-
  Drift-Test bestaetigt 6-Service-Meta. Aufwand M.
- Mehrwert gegenueber C: 0 (beide liefern YYYY-MM-String im meta).
- Optisch sauberer ("date_partial" klingt nach standardisiertem Vertrag).

**Option C (meta.date_iso Beimaterial)** - **EMPFOHLEN**.

- Additiv, kein Schema-Bruch.
- Helper-Funktion ist 1:1 das was Option B brauchen wuerde, aber ohne die
  "neue meta-Konvention"-Buerokratie.
- 0 Mehrwert gegenueber B, aber 50% weniger Aufwand und kein neuer Vertrag.
- Wenn spaeter ein echter Sortier-Konsumer kommt, kann meta.date_iso
  trivial in Option B umbenannt werden (Schema-Migration ohne Daten-Verlust).

**Schluss-Empfehlung:** **Option C**. Begruendung: gleicher Wert wie B,
halber Aufwand, null Schema-Risiko.

---

## Sektion 3: Schema-Vertrag (Option C)

### 3.1 Was setzt der TP-Adapter neu?

Aktuell (v0.18.0):

```php
$meta = array(
    'is_featured'            => $is_featured,
    'video_id'               => $video_id,
    'v_modus'                => $v_modus,
    'mandantenvideo_service' => $api_svc,
);
if ( '' !== $datum ) {
    $meta['datum'] = $datum;   // MM/YY, z.B. "10/24"
}
```

v0.18.1:

```php
$meta = array(
    'is_featured'            => $is_featured,
    'video_id'               => $video_id,
    'v_modus'                => $v_modus,
    'mandantenvideo_service' => $api_svc,
);
if ( '' !== $datum ) {
    $meta['datum'] = $datum;   // BLEIBT - Templates lesen das
    $iso = dhps_partial_date_to_iso( $datum, 'mm_yy' );
    if ( null !== $iso ) {
        $meta['date_iso'] = $iso;   // NEU - sortierbares Beimaterial, z.B. "2024-10"
    }
}
```

Pflicht: meta.datum bleibt BYTEWISE unveraendert. meta.date_iso ist additiv.

### 3.2 Was setzt der MIO-Adapter neu?

Aktuell (v0.18.0):

```php
$meta = array(
    'month_index' => $month_index,
    'entries'     => $entries,
);
if ( '' !== $footnote ) {
    $meta['footnote'] = $footnote;
}
```

v0.18.1:

```php
$meta = array(
    'month_index' => $month_index,
    'entries'     => $entries,
);
if ( '' !== $footnote ) {
    $meta['footnote'] = $footnote;
}
$iso = dhps_partial_date_to_iso( $raw_title, 'de_month_year' );
if ( null !== $iso ) {
    $meta['date_iso'] = $iso;   // NEU - "Juli 2025" -> "2025-07"
}
```

Pflicht: title bleibt BYTEWISE unveraendert. month_index bleibt erhalten.

### 3.3 Helper-Funktion

```php
// includes/dhps-date-helpers.php (NEU in v0.18.1):
//
// Konvertiert Partial-Date-Strings in ISO-Year-Month-Format (YYYY-MM).
//
// Unterstuetzte Eingabe-Formate:
// - 'mm_yy':          'MM/YY'    z.B. "10/24" -> "2024-10"
// - 'de_month_year':  'Monat YYYY' z.B. "Juli 2025" -> "2025-07"
//
// Returns null bei ungueltigem Input (defensive).
function dhps_partial_date_to_iso( string $input, string $format ): ?string {
    $input = trim( $input );
    if ( '' === $input ) {
        return null;
    }
    if ( 'mm_yy' === $format ) {
        return dhps_partial_date_mm_yy_to_iso( $input );
    }
    if ( 'de_month_year' === $format ) {
        return dhps_partial_date_de_month_year_to_iso( $input );
    }
    return null;
}
```

### 3.4 Template-Reads

**KEINE Aenderung.** Templates lesen weiterhin:

- TP: `$video['datum']` -> `DHPS_TP_Parser::format_datum()` -> "Okt. 2024"
- MIO: `$month['title']` -> direkte Anzeige "Juli 2025"

`meta.date_iso` wird **NICHT** in Templates gerendert. Es ist Beimaterial
fuer zukuenftige Adapter-Konsumenten (z.B. ein hypothetischer
`Collection::sort_by_date_iso()`-Helper in v0.19.0).

### 3.5 BC-Stabilitaet mit Theme-Overrides

- Theme-Overrides die `$video['datum']` lesen: **UNANGETASTET**.
- Theme-Overrides die `$month['title']` lesen: **UNANGETASTET**.
- Theme-Overrides die `meta.datum` direkt aus Item-Objekt lesen:
  **UNANGETASTET** (Feld bleibt).
- Theme-Overrides die `meta.date_iso` aus Item-Objekt nutzen wollen:
  **ZUSAETZLICH VERFUEGBAR** (additiv, optional).

### 3.6 Pseudo-Rebuild-Helper-Unveraendert

`dhps_tp_item_to_legacy_video()` und `dhps_mio_item_to_legacy_month()`
bleiben **bytewise unveraendert**. Sie lesen weiter `meta.datum` bzw.
`title`, ignorieren `meta.date_iso` (Add-Only-Pattern).

---

## Sektion 4: Format-Wahl

### Optionen fuer den ISO-String

**Variante 1: `meta.date_iso = '2024-10'`** (YYYY-MM-String)

- ISO 8601 Year-Month-Format
- Sortierbar via String-Compare (lex == chrono fuer YYYY-MM)
- Parseable durch beliebige Sprachen
- 7 Zeichen, kompakt

**Variante 2: `meta.date_partial = ['year' => 2024, 'month' => 10]`** (Array)

- Strukturiert, kein String-Parsen noetig
- Nicht direkt sortierbar (braucht Custom-Comparator)
- Mehr Speicher pro Item (~30 Byte vs 9 Byte)

**Variante 3: BEIDES**

- Doppelte Quelle = Drift-Risiko bei Cache-Restore
- Keine zusaetzliche Funktionalitaet

### Empfehlung

**Variante 1: `meta.date_iso` (YYYY-MM-String).**

Begruendung:

- ISO 8601 ist Industrie-Standard fuer Year-Month-Format
- String-Compare = chrono-Compare (lex-sortable)
- 7 Zeichen sind nichts in JSON/Cache
- Falls Konsumenten ein Array brauchen, koennen sie `[year, month] = explode('-', $iso)` machen

**Konvention dokumentieren** im Helper-Doc-Block + in `26-EINHEITLICHES-DATENMODELL-PLAN-v0170.md`
Update (Sektion 5.1 Item-Schema-Vertrag).

---

## Sektion 5: Edge-Cases

### 5.1 TP/TPT/LP - MM/YY-Parser

| Input | Expected ISO | Notiz |
|-------|--------------|-------|
| `"10/24"` | `"2024-10"` | Normalfall |
| `"1/24"` | `"2024-01"` | Einstellige Monate (Parser-Regex erlaubt `\d{1,2}`) |
| `"01/24"` | `"2024-01"` | Fuehrende Null bleibt eindeutig |
| `"10/99"` | `"2099-10"` | **Konvention: 20YY** (siehe DHPS_TP_Parser::format_datum Z. 383: `'20' . $parts[1]`) |
| `"10/00"` | `"2000-10"` | 20YY-Konvention |
| `"13/24"` | `null` | Monat > 12 -> ungueltig |
| `"0/24"` | `null` | Monat < 1 -> ungueltig |
| `""` | `null` | Leer-String |
| `"abc"` | `null` | Unparseable |
| `"10-24"` | `null` | Falscher Trenner |
| `"10/2024"` | `null` | YYYY statt YY (Parser-Format) |

**20YY-Konvention bewusst:** Plugin gibt es seit 2023, API liefert nie
historische Videos > 100 Jahre alt. `"10/99"` -> 2099 ist gewollt
(Forward-Compatibility). Wenn das je faelschlich `"99"` als 1999 meinen
soll: separate Discovery + DB-Migration noetig - nicht in v0.18.1.

### 5.2 MIO - DE-Monatsnamen-Mapping

Parser liefert Titel-Strings wie `"Juli 2026"`, `"August 2026"`. Mapping:

```php
const DE_MONTH_MAP = [
    'januar'    => 1,
    'februar'   => 2,
    'maerz'     => 3,   // ASCII-Form
    'märz'      => 3,   // UTF-8-Form (Parser kann beide liefern)
    'april'     => 4,
    'mai'       => 5,
    'juni'      => 6,
    'juli'      => 7,
    'august'    => 8,
    'september' => 9,
    'oktober'   => 10,
    'november'  => 11,
    'dezember'  => 12,
];
```

| Input | Expected ISO | Notiz |
|-------|--------------|-------|
| `"Juli 2025"` | `"2025-07"` | Normalfall |
| `"juli 2025"` | `"2025-07"` | Case-insensitive Match (`mb_strtolower`) |
| `"Maerz 2025"` | `"2025-03"` | ASCII-Form |
| `"März 2025"` | `"2025-03"` | UTF-8-Form |
| `"Juli2025"` | `null` | Kein Whitespace - unparseable (strict) |
| `"Juli 25"` | `null` | YY statt YYYY - unparseable |
| `"Juli"` | `null` | Kein Jahr |
| `"Monat 1"` | `null` | Fallback-Title (aus Adapter-Skip-Default `'Monat '.($idx+1)`) - **bewusst kein ISO-Match**, weil Tag-und-Monat unbekannt |
| `""` | `null` | Leerer Title (skip-Bedingung greift schon im Adapter) |
| `"  Juli   2025  "` | `"2025-07"` | Whitespace-Trim |

### 5.3 Robustheit beider Helper

- Defensive: Bei `null`-Returns wird `meta.date_iso` NICHT gesetzt (statt
  als leerer String oder false). Spart Bytes + macht `isset()`-Checks
  eindeutig.
- Adapter-Logging: bei `null`-Return und WP_DEBUG=true einmaliger
  error_log-Trace pro Adapter-Aufruf (NICHT pro Item - das wuerde Log
  fluten). **Discovery-Frage offen:** loggen JA/NEIN. Empfehlung: **NEIN**,
  weil 0 Konsumenten heute. Wenn Konsumenten kommen, koennen sie selbst
  loggen.

### 5.4 Theme-Override-Compat

- `meta.datum` wird **NICHT** beruehrt - alle TP-Templates rendern weiter
  via `format_datum()`.
- `meta.date_iso` ist **add-only** - Templates die es nicht kennen,
  ignorieren es.
- Plugin-Hooks `dhps_pipeline_data_{tag}` koennen `meta.date_iso`
  ueberschreiben/loeschen, wenn ein Plugin das braucht.

### 5.5 Cache-Roundtrip

`DHPS_Content_Item::to_array()` serialisiert das ganze `meta`-Array, also
auch `date_iso`. `from_array()` rehydrated es 1:1. Kein Custom-Serializer
noetig.

---

## Sektion 6: Tests

### F1 Unit-Tests (Helper)

- T1: `dhps_partial_date_to_iso( '10/24', 'mm_yy' )` returnt `'2024-10'`.
- T2: `dhps_partial_date_to_iso( '1/24', 'mm_yy' )` returnt `'2024-01'`.
- T3: `dhps_partial_date_to_iso( '01/24', 'mm_yy' )` returnt `'2024-01'`.
- T4: `dhps_partial_date_to_iso( '10/99', 'mm_yy' )` returnt `'2099-10'`.
- T5: `dhps_partial_date_to_iso( '13/24', 'mm_yy' )` returnt `null`.
- T6: `dhps_partial_date_to_iso( '0/24', 'mm_yy' )` returnt `null`.
- T7: `dhps_partial_date_to_iso( '', 'mm_yy' )` returnt `null`.
- T8: `dhps_partial_date_to_iso( 'abc', 'mm_yy' )` returnt `null`.
- T9: `dhps_partial_date_to_iso( '10-24', 'mm_yy' )` returnt `null`.
- T10: `dhps_partial_date_to_iso( '10/2024', 'mm_yy' )` returnt `null`.
- T11: `dhps_partial_date_to_iso( 'Juli 2025', 'de_month_year' )` returnt `'2025-07'`.
- T12: `dhps_partial_date_to_iso( 'juli 2025', 'de_month_year' )` returnt `'2025-07'`.
- T13: `dhps_partial_date_to_iso( 'Maerz 2025', 'de_month_year' )` returnt `'2025-03'`.
- T14: `dhps_partial_date_to_iso( 'März 2025', 'de_month_year' )` returnt `'2025-03'`.
- T15: `dhps_partial_date_to_iso( '  Juli   2025  ', 'de_month_year' )` returnt `'2025-07'`.
- T16: `dhps_partial_date_to_iso( 'Juli', 'de_month_year' )` returnt `null`.
- T17: `dhps_partial_date_to_iso( 'Juli 25', 'de_month_year' )` returnt `null`.
- T18: `dhps_partial_date_to_iso( 'Monat 1', 'de_month_year' )` returnt `null`.
- T19: `dhps_partial_date_to_iso( '10/24', 'unknown_format' )` returnt `null`.
- T20: `dhps_partial_date_to_iso( '10/24', '' )` returnt `null`.

### F1 Adapter-Tests (TP)

- T21: TP-Adapter mit Video-Datum "10/24" -> Item.meta['date_iso'] == '2024-10'.
- T22: TP-Adapter mit Video ohne datum -> Item.meta NICHT 'date_iso'-Key (isset==false).
- T23: TP-Adapter mit Video-Datum "13/24" -> Item.meta NICHT 'date_iso'-Key.
- T24: TP-Adapter mit Video-Datum "10/24" -> Item.meta['datum'] BLEIBT '10/24' bytewise.
- T25: LP-Service (gleicher Adapter) - identisches Verhalten T21-T24.
- T26: TPT-Adapter (separate Klasse) - analoges Verhalten fuer Single-Video.

### F1 Adapter-Tests (MIO)

- T27: MIO-Adapter mit Monat "Juli 2025" -> Item.meta['date_iso'] == '2025-07'.
- T28: MIO-Adapter mit Monat ohne Title (Fallback "Monat 1") -> Item.meta NICHT 'date_iso'.
- T29: MIO-Adapter mit Monat "März 2025" UTF-8 -> Item.meta['date_iso'] == '2025-03'.
- T30: MIO-Adapter - Item.title BLEIBT "Juli 2025" bytewise.
- T31: LXMIO-Service (gleicher Adapter) - identisches Verhalten T27-T30.

### F1 Roundtrip-Tests (Cache-BC)

- T32: TP-Item mit meta.date_iso -> to_array() -> from_array() -> Item.meta['date_iso'] bytewise restored.
- T33: MIO-Item mit meta.date_iso -> Roundtrip bytewise restored.

### Frontend-Smoke-Tests (Stage)

- T34: Page 6 (9 Hauptservices) - dhps-Klassen-Diff vor/nach v0.18.1 **LEER**.
- T35: TP-Template "Okt. 2024" wird angezeigt (format_datum unveraendert).
- T36: MIO-Template "Juli 2025" wird im H4 angezeigt (Title unveraendert).
- T37: Steuertermine-Templates (4) rendern unveraendert.

### Edge-Case-Tests

- T38: Adapter mit nicht-deutschem Parser-Output (z.B. "July 2025"): Item.meta NICHT 'date_iso'-Key (defensive null).
- T39: WP_DEBUG=true + Adapter mit ungueltigem Datum: kein error_log Spam (max 0 Eintraege pro Adapter-Aufruf in v0.18.1).

**Pflicht-Pass: T1-T37 (kein FAIL). T38-T39 als Spec-Verifikation.**

---

## Sektion 7: Spec-Aufteilung

**Empfehlung: Pure Lead-Direct.**

Begruendung:

- Scope = 1 neuer Helper (~80 LOC) + 2 Adapter-Patch (~10 LOC pro Adapter)
  + Test-Suite (~39 Tests, ~250 LOC).
- Keine Schema-Migration, kein BC-Bruch, keine User-faceing Aenderung.
- 1 Lead-Tag inkl. Tests und Stage-Smoke.

**Phase-Aufteilung im Single-Branch:**

1. **Phase 1:** Helper `dhps_date_helpers.php` schreiben + Unit-Tests T1-T20.
2. **Phase 2:** TP-Adapter `meta.date_iso` setzen + Tests T21-T26.
3. **Phase 3:** MIO-Adapter `meta.date_iso` setzen + Tests T27-T31.
4. **Phase 4:** Roundtrip-Tests T32-T33.
5. **Phase 5:** Stage-Smoke T34-T37 + Doku-Updates.
6. **Phase 6:** Release-Notes + CHANGELOG-v0181.md.

Phase-1-Gate: T1-T20 muessen alle PASS sein, **bevor** Adapter-Aenderungen
beginnen.

---

## Sektion 8: Risiken + Tech-Debt

### Top-3-Risiken

#### R1: UTF-8-Char-Match bei "März"

- **Wahrscheinlichkeit:** NIEDRIG (PHP mb_strtolower default-utf8)
- **Impact:** NIEDRIG (Helper returnt null statt falscher Wert)
- **Mitigation:**
  - Helper nutzt `mb_strtolower($input, 'UTF-8')` explizit
  - DE_MONTH_MAP enthaelt sowohl `'maerz'` (ASCII) als auch `'märz'` (UTF-8)
  - Test T13+T14 deckt beide Forms

#### R2: Parser-Drift in API-Datum-Format

- **Wahrscheinlichkeit:** NIEDRIG (API-Format stabil seit 2023)
- **Impact:** NULL (Helper returnt null -> meta.date_iso fehlt -> 0
  Konsumenten -> 0 User-faceing Wirkung)
- **Mitigation:**
  - Defensive Helper-Design (Whitelist statt Negativliste)
  - meta.datum + title bleiben bytewise erhalten
  - Adapter-Tests T22+T28 decken Default-Skip-Pfade

#### R3: Cache-Bloat durch zusaetzliches meta-Feld

- **Wahrscheinlichkeit:** SEHR NIEDRIG (7 Bytes pro Item, < 2 KB pro Service)
- **Impact:** NULL (L1+L2-Cache haben TB-Headroom)
- **Mitigation:** keine
- **Bewertung:** Vernachlaessigbar

### Tech-Debt nach v0.18.1

| Ticket | Stand | Vorschlag |
|--------|-------|-----------|
| TD-V0181-1: Collection::sort_by_date_iso() | NEU | v0.19.0 wenn Use-Case kommt |
| TD-V0181-2: News-Item-DateTimeImmutable (full date) | NEU | v0.19.0 wenn News-Adapter kommt (TD-V0174-1) |
| TD-V0181-3: Option-B-Migration (meta.date_partial-Vertrag) | NEU optional | v0.20.0 oder spaeter wenn DTO-Schema-Update kommt |

---

## Sektion 9: BC-Impact

### Fuer Theme-Entwickler

**Keine Aenderungen.** Templates rendern bytewise identisch.

- TP-Templates lesen weiter `$video['datum']` -> `format_datum()`.
- MIO-Templates lesen weiter `$month['title']`.
- meta.date_iso ist optional, additiv, kein Render-Konsument heute.

### Fuer Plugin-Entwickler

**Keine Aenderungen** an bestehenden Filter-Hooks. Neuer Nutzen:

- `meta.date_iso` ist im Item-Objekt verfuegbar (sortierbar).
- Plugins koennen `dhps_pipeline_data_{tag}` nutzen, um `meta.date_iso` zu
  patchen oder zu loeschen.

### Fuer Shortcode-Konsumenten

**Keine Aenderungen.** `[mio]`/[tp]/[tpt]/[lp]/... rendern bytewise unveraendert.

### Fuer Cache-Konsumenten

`from_array()` und `to_array()` bleiben kompatibel. Bestehende Cache-Eintraege
ohne `meta.date_iso` werden korrekt rehydrated (isset()-Check faengt das).

### Fuer Update-Channel-User

- `dhps_update_channel = stable` bekommt v0.18.1 nach Promote.
- `dhps_update_channel = beta` bekommt v0.18.1-rc.1 vorab.
- Stage-Smoke 24h Soak.

---

## Sektion 10: Spec-Briefing

### Spec-Auspraegung (Lead-Direct)

**Mission:** Datum-Normalisierung TP + MIO via `meta.date_iso`-Beimaterial
(Option C), ohne DTO-Schema-Aenderung, ohne Template-Aenderung.

**Pflicht-Reihenfolge:**

1. **Phase 1 (Helper):**
   - Neue Datei `includes/dhps-date-helpers.php`
   - Funktion `dhps_partial_date_to_iso( string $input, string $format ): ?string`
   - 2 private Helper: `dhps_partial_date_mm_yy_to_iso`, `dhps_partial_date_de_month_year_to_iso`
   - Bootstrap-`require_once` in `Deubner_HP_Services.php` (analog `dhps-content-helpers.php`)
   - Unit-Tests T1-T20

2. **Phase 2 (TP-Adapter):**
   - `class-dhps-tp-adapter.php::build_video_item()` Z. 290-292 (nach `$meta['datum']`)
   - Bedingung: `if ('' !== $datum)` schon vorhanden - innerhalb dieses Blocks zusaetzlich `meta.date_iso` setzen
   - Tests T21-T26

3. **Phase 3 (MIO-Adapter):**
   - `class-dhps-mio-adapter.php::adapt()` Z. 130 (nach Title-Fallback)
   - Bedingung: `dhps_partial_date_to_iso($raw_title, 'de_month_year')` mit null-Check
   - Tests T27-T31

4. **Phase 4 (Roundtrip):**
   - Tests T32-T33 (Cache-Roundtrip)

5. **Phase 5 (TPT-Adapter):**
   - `class-dhps-tpt-adapter.php` analog TP - Single-Video hat auch datum-Feld
   - Test T26

6. **Phase 6 (Stage-Smoke + Doku):**
   - Tests T34-T37 auf Stage
   - CHANGELOG-v0181.md
   - MIGRATION.md-Eintrag fuer v0.17 -> v0.18.1
   - `docs/architecture/26-EINHEITLICHES-DATENMODELL-PLAN-v0170.md` Sektion 5.1 erweitern (meta.date_iso-Konvention)
   - MEMORY.md MILESTONE 25 + Implementation-Notes

**Phase-1-Gate:** T1-T20 muessen alle PASS sein. Wenn auch nur 1 FAIL -> kein
Adapter-Patch ohne Helper-Korrektur.

**Phase-2-3-Gate:** Tests T21-T31 muessen alle PASS sein, bevor Phase 4
startet.

### Spec-Pflicht-Lektuere

1. Dieses Dokument (34-DATUM-NORMALISIERUNG-PLAN-v0181.md)
2. `docs/architecture/26-EINHEITLICHES-DATENMODELL-PLAN-v0170.md` (DTO-Schema 5.1)
3. `docs/architecture/31-TECH-DEBT-CLEANUP-PLAN-v0175.md` Sektion TD-V0172-2 + TD-V0173-2
4. `includes/class-dhps-content-item.php` (DTO-Konstruktor + ALLOWED_*)
5. `includes/class-dhps-tp-adapter.php` (Item-Build Z. 225-313)
6. `includes/class-dhps-mio-adapter.php` (adapt() Z. 99-178)
7. `includes/class-dhps-tpt-adapter.php` (analog TP)
8. `includes/dhps-tp-content-helpers.php` (Pseudo-Rebuild bleibt unangetastet)
9. `includes/dhps-mio-content-helpers.php` (Pseudo-Rebuild bleibt unangetastet)
10. `includes/parsers/class-dhps-tp-parser.php::extract_datum + format_datum`
11. `includes/parsers/class-dhps-mio-parser.php::parse_tax_dates` (Z. 74-137)

### Schema-Vertrag-Vorgehen Iteration 16

**Status:** 15x in Folge ohne Critical-Drift (v0.15.3 - v0.18.0).

**Sektion 3 dieses Docs ist verbindlicher Vertrag.** Lead bestaetigt **vor**
Code-Aenderung, dass meta.date_iso identisch im TP + MIO + TPT-Adapter
gesetzt wird (Schema-Drift-Smoke). Wenn ein Adapter abweicht: Discovery-Update
Pflicht **vor** Code-Aenderung.

### Test-Pflicht-Pass

T1-T37 (kein FAIL) als Release-Gate.

### Aufwand-Korridor

- Best-Case: 0.5 MT (Helper + 2 Adapter + Tests in einem Sitz)
- Realistic-Case: 0.8 MT (mit Stage-Smoke + Doku)
- Worst-Case: 1 MT (wenn DE-Monatsnamen-Edge-Cases auftauchen)

### Acceptance-Gate fuer Release

1. T1-T37 PASS lokal.
2. Stage-Smoke T34-T37 PASS.
3. Page 6 dhps-Klassen-Diff LEER (oder dokumentiert).
4. CHANGELOG-v0181.md geschrieben.
5. MEMORY.md MILESTONE 25 + Implementation-Notes.
6. Pre-Release-rc.1 -> Stage 24h Soak.
7. Promote zu Stable.

---

## Anhang A: Aktueller Stand v0.18.0

- 9/9 Adapter aktiv (mio/lxmio/tp/tpt/lp/mmb/mil/maes/tc)
- 22 Templates auf Pipeline-only (Legacy-else-Pfad raus)
- 3 Helper-Konsolidierungen (`dhps_collection_or_empty`,
  `dhps_mmb_collection_to_legacy_categories`, `dhps_tp_collection_to_legacy_categories`)
- Item.date bleibt **null** fuer alle 9 Services (kein Konsumer)
- meta.datum (TP/TPT/LP) als MM/YY-String erhalten
- title (MIO) als "Monat YYYY"-String erhalten

## Anhang B: Was NICHT in v0.18.1 gehoert

- DateTimeImmutable mit Default-Tag 01 (Option A - semantisch falsch, verworfen)
- DTO-Schema-Erweiterung `?DHPS_Content_Date`-Sub-DTO (overengineering)
- `Collection::sort_by_date_iso()`-Helper (kommt mit erstem Konsumer in v0.19.0)
- News-Item-DateTimeImmutable (TD-V0181-2, separate Achse)
- Voller `meta.date_partial`-Vertrag mit Doku-Spec (Option B - overkill ohne Konsumer)
- Template-Aenderungen (Konsumenten gibt es nicht)
- Sub-Shortcode-Filter-Atts auf date_iso (kein Use-Case)

## Anhang C: Antwort auf die wichtigste Frage

**Frage Architekt:** "Brauchen wir wirklich sortierbares Datum, oder ist das
hypothetisch? Welche Option (A/B/C) bringt den besten Wert/Risiko?"

**Antwort:**

- **Hypothetisch JA** - kein Code-Pfad sortiert heute nach Datum. 5 Releases
  ohne Sortier-Bedarf. Kein Roadmap-Item.
- **Option C** (Status Quo + meta.date_iso Beimaterial-Helper) bringt den
  besten Wert/Risiko-Quotient: 50% weniger Aufwand als Option B, gleicher
  funktionaler Wert, null neue Schema-Permutation, null BC-Risiko.
- **Option A** ist semantisch falsch und wird verworfen.
- **Option B** ist sauberer aber overkill ohne Konsumer.

**Schaetzung:** S (klein), 0.5-1 MT netto, Pure Lead-Direct.

**Wenn Architekt mehr Doku-Strenge will:** Option B als Plan in
`26-EINHEITLICHES-DATENMODELL-PLAN-v0170.md` verankern als "ab v0.19.0
optional"-Eintrag, ohne Code in v0.18.1. So gibt's einen klaren Migrations-
Pfad falls je ein Konsumer kommt.
