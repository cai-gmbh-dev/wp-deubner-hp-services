# Changelog v0.20.0 - Component-System v1-Stabilitaets-Release

## Stand: 2026-06-08

## Mission

Component-System v1 ist seit v0.14.0 ausgereift. v0.20.0 markiert es **als FINAL**.

Discovery 39 hat die Frage "brauchen wir Component-System v2?" untersucht und **mit Nein
beantwortet**: 0 User-Druck, 0 Pain-Points die ein v2 rechtfertigen, alle "grossen Refactoring"-
Optionen waren HOCH-Risiko bei 0 User-Wert.

**Option Zeta-Plus** (klein bleiben + 3 Polish-Massnahmen) ist das gewaehlte v0.20.0.

## Strategie

| Optionen | Verdikt |
|----------|---------|
| Alpha (Modernisierung Component-v1) | viel Code, wenig User-Wert |
| Beta (Render-Layer-Refactor) | 0 User-Wert, HOCH BC-Risiko |
| Gamma (Headless-Render-API) | 0 User-Wert, SEHR HOCH BC-Risiko |
| Delta (Web-Components-Integration) | 0 User-Wert, MITTEL-HOCH BC-Risiko |
| Epsilon (Hooks-System) | wenig User-Wert |
| **Zeta (klein bleiben)** | **EMPFOHLEN** mit 3 Polish-Massnahmen |

## Hauptaenderungen

### M1: Defense-in-Depth (schliesst v0.14.0 Security-Audit M-1 + M-2)

`includes/dhps-component-helpers.php` `dhps_component()`:

```php
// v0.20.0 Defense-in-Depth: Sanity-Check auf Component-Name.
if ( 1 !== preg_match( '/^[a-z][a-z0-9-]*$/', $name ) ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        return '<!-- dhps_component: ungueltiger Name "' . esc_html( $name ) . '" -->';
    }
    return '';
}
```

`includes/class-dhps-component-registry.php` `get_template_path()`:

```php
// v0.20.0 Defense-in-Depth: Realpath-Whitelist nach Filter.
if ( '' !== $resolved ) {
    $real = realpath( $resolved );
    $allowed_roots = array(
        realpath( DEUBNER_HP_SERVICES_PATH . 'public/views/components/' ),
        realpath( get_stylesheet_directory() . '/dhps/components/' ),
        realpath( get_template_directory() . '/dhps/components/' ),
    );
    $allowed_roots = (array) apply_filters( 'dhps_component_allowed_roots', $allowed_roots );

    // is_within-Check + return '' bei Reject
}
```

- **Strict-Reject** bei Regex-Mismatch (kein fail-soft, klarer Misuse-Signal)
- **Path-Traversal-Schutz**: Theme-Filter kann keine `/etc/passwd`-Pfade injizieren
- **Escape-Hatch**: neuer Filter `dhps_component_allowed_roots` fuer MU-Plugin-Component-Pools

### M2: SVG-Icon-Helper

`includes/dhps-component-helpers.php`:

```php
function dhps_get_component_icon( string $slug, int $size = 14, float $stroke_width = 2.0 ): string {
    // 10-Slug-Whitelist + Slug-zu-SVG-Body-Map zentralisiert
}
```

- **10 Icons**: 6 klein (calendar/clock/file/download/play/link, size=14, stroke=2) +
  4 gross (inbox/calculator/document/video, size=48, stroke=1.6)
- **Play-Icon Sonder-Style**: `fill="currentColor"` (gefuellt), alle anderen stroke-only
- **Neuer Filter** `dhps_component_icon_svg` erlaubt komplette SVG-Substitution pro Slug
- **Templates noch nicht migriert**: Helper ist Vorbereitung, Migration optional in v0.20.x

### M3: API-Stabilitaets-Doku

`docs/architecture/40-COMPONENT-API-V1-STABLE.md` (NEU):

- **Expliziter Stabilitaets-Vertrag**: "v1 ist final, kein v2 geplant"
- BC-Versprechen bis v1.0 (SemVer)
- 8-Components-Inventar mit Seit-Spalte
- 4-Filter-Hook-Inventar (`dhps_component_props` + `dhps_component_template_path` +
  `dhps_component_allowed_roots` + `dhps_component_icon_svg`)
- Theme-Override-Beispiel
- Was ist NICHT stabil (HTML-Struktur, CSS-Klassen, Default-Props-Werte)

## Tests

`test-v0200-component.php` (Lead-Smoke):

- T1-T6 Regex-Sanity-Check (calculator/Bad Name/../path/empty/Camel/123start)
- T7-T8 Realpath-Whitelist (/etc/passwd reject + Plugin-Default OK)
- T9-T15 Icon-Helper (10 Slugs + Size + Stroke + Play-Sonder-Style + Filter)

**Resultat: 27 PASS / 0 FAIL**

## Backward Compatibility

**Vollstaendig BC**:

- HTML-Render bytewise unveraendert
- Plugin-Templates (alle 22 + 8 Components) unangetastet
- Bestehende Component-Aufrufe weiter funktional
- 9 Adapter unangetastet
- 9 Parser unangetastet
- Pipeline unveraendert
- AJAX-JSON-Responses unangetastet
- 4 bestehende Hooks (dhps_component_props, dhps_component_template_path, etc.) unveraendert
- 0 Plugin-Template-Touches
- Stage-Smoke Page 6/7/8 = 76/8/91 bytewise stabil

**Theoretischer BC-Bruch**:

- Theme-Code der bisher fail-soft Component-Namen mit Whitespace/Sonderzeichen rendern wollte,
  bekommt jetzt Strict-Reject. Das ist API-Misuse-Sanitization, kein produktiver Use-Case.

## Geaenderte Dateien

### Neu

| Datei | Zweck |
|-------|-------|
| `docs/architecture/39-COMPONENT-V2-PLAN-v0200.md` | Discovery (Option Zeta-Plus) |
| `docs/architecture/40-COMPONENT-API-V1-STABLE.md` | M3 Stabilitaets-Vertrag |
| `docs/project/59-CHANGELOG-v0200.md` | (dieses Dokument; Nummer korrekt = 59) |

### Geaendert

| Datei | Aenderung |
|-------|-----------|
| `Deubner_HP_Services.php` | Version 0.19.1 -> 0.20.0 |
| `README.md` | Version-Bump |
| `includes/dhps-component-helpers.php` | M1 Regex-Sanity-Check + M2 Icon-Helper |
| `includes/class-dhps-component-registry.php` | M1 Realpath-Whitelist + `dhps_component_allowed_roots`-Filter |
| `MEMORY.md` | MILESTONE 30 + 7 v0.20.0 Implementation-Notes |

## Bilanz v0.20.0

- **Component-System v1 als FINAL markiert** (kein v2 geplant)
- **2 Security-Audit-Tickets** geschlossen (M-1 + M-2 aus v0.14.0)
- **1 neuer Helper** (`dhps_get_component_icon`)
- **2 neue Hooks** (`dhps_component_allowed_roots`, `dhps_component_icon_svg`)
- **Lead-Tests 27/27 PASS**
- **0 BC-Bruch** im Render-Output
- **0 Plugin-Template-Touches**
- Schema-Vertrag-Vorgehen **21x in Folge** ohne Critical-Drift

## Naechste Optionen

| Option | Scope |
|--------|-------|
| **Pause** | Stabilisierungs-Aera erfolgreich abgeschlossen |
| **v0.20.1** | Polish: Templates auf Icon-Helper umstellen wenn Bedarf entsteht |

## Aera-Bilanz

**DTO-Foundation-Aera** (v0.17.0 -> v0.19.1) + **Component-Stabilisierungs-Release** (v0.20.0)
sind erfolgreich abgeschlossen.

| Metric | Seit v0.17.0 |
|--------|--------------|
| Pre-Release-Promotions | 17 |
| Schema-Vertrag ohne Critical-Drift | 21x in Folge |
| BC-Brueche im HTML-Render | 0 |
| MAJOR-Versionen | 2 (v0.18.0 + v0.19.0) |
| Plugin-Templates auf Pipeline-only | 22 |
| Helper-Funktionen | 11 (10 + dhps_get_component_icon) |
| Action/Filter-Hooks | 5 (3 Side-Channels + 2 Component-Defense) |
| Discovery-Plan-Docs | 40 |
