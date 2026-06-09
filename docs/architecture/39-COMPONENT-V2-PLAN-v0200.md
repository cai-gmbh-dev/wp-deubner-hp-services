# Component-System v2 - Discovery & Plan v0.20.0

> Status: Discovery | Zielversion: v0.20.0 | Stand: 2026-06-08
> Owner: Architektur-Team (Discovery-Specialist) | Reviewer: Lead, Frontend, Security

## TL;DR (Lead-Briefing)

| Aspekt | Empfehlung |
|--------|------------|
| **Empfehlung** | **Option Zeta-Plus** = "klein bleiben" mit drei gezielten Polish-Massnahmen |
| **Aufwand** | S (Lead-Direct, ~120-180 LOC + Doku) |
| **BC-Risiko** | NIEDRIG (additive Filter + Defense-in-Depth, kein Marker-Bruch) |
| **Discovery-Only?** | **NEIN, kleine Implementation moeglich** |
| **Alternative** | **JA verschieben** auf v0.20.1 oder spaeter, falls Roadmap-Druck herrscht |
| **Top-Frage** | Brauchen wir v2 wirklich? **Antwort: Nein.** Component-System v1 ist solide. |

**Strategische Kernaussage:** Component-System v1 ist nach v0.14.0 + v0.15.5
(13x Schema-Vertrag-Iteration, 0 Critical-Drift) ausgereift. Eine grosse
v2-Refactoring (Beta/Gamma/Delta) waere ein **Theme-Override-Risiko ohne
proportionalen User-Wert**. Die DTO-Foundation-Aera (v0.17-v0.19.1) hat
das Daten-Modell stabilisiert - das **Render-Modell** ist bereits stabil.

---

## 1. Component-System v1 Inventar (Status quo)

### 1.1 Architektur-Ueberblick

**Drei Bausteine:**

1. `includes/class-dhps-component-registry.php` (241 LOC):
   - Statische Klasse, Pattern analog zu `DHPS_Parser_Registry`
   - Speichert: `register/is_registered/get_config/get_template_path/get_all`
   - Asset-Tracking: `mark_used/was_used/get_used/reset/reset_used`
   - Filter-Hooks: `dhps_component_template_path`, `dhps_component_props`
   - Theme-Override-Hierarchie: Child-Theme -> Parent-Theme -> Plugin-Default
2. `includes/dhps-component-helpers.php` (114 LOC):
   - `dhps_component( $name, $props ): string`
   - `dhps_render_component( $name, $props ): void`
   - Output-Buffer + `extract( EXTR_SKIP )` + `wp_kses`-Defaults im Template
3. `public/views/components/{name}.php` (8 Templates, 1113 LOC total):
   - Stateless: `skeleton-loader`, `empty-state`, `lazy-image`, `accordion`
   - Stateful (Alpine): `content-card`, `content-list`, `filter-bar`, `pagination`

**CSS:** `css/dhps-components.css` (966 LOC, `@layer`-Strategie)

**JS:** Alpine.js via Vendor + `dhps-components-alpine` (Conditional-Enqueue
via `was_used()`)

### 1.2 Registrierte Components (`dhps_register_components()` in `Deubner_HP_Services.php:614-736`)

| # | Component | Stateful | Default-Props | Used-In (Templates) |
|---|-----------|----------|---------------|---------------------|
| 1 | `skeleton-loader` | nein | type=card, count=3 | mio/* (3), mmb/* (3) |
| 2 | `empty-state` | nein | icon=inbox | via content-list `empty_state`-Prop |
| 3 | `lazy-image` | nein | src/alt/width/height/lqip | via content-card-`media_url` |
| 4 | `accordion` | nein | id/items/multi=false | nicht direkt genutzt (kein Service ruft auf) |
| 5 | `content-card` | ja | type=news, badges/meta/actions, data_attrs | content-list, ~16 Services |
| 6 | `filter-bar` | ja | tags/sorts/debounce_ms=300 | content-list via `filter_bar`-Prop |
| 7 | `pagination` | ja | mode=load-more | content-list via `pagination`-Prop |
| 8 | `content-list` | ja | items, empty_state, pagination | maes/* (7), tpt/* (3), tc/* (3), tp/* (2) |

### 1.3 Usage-Statistik (grep `dhps_component(` ueber `public/views/`)

- **Service-Templates rufen `dhps_component()`:** 16 Templates an 33 Stellen
- **Container-Composition** (content-list -> content-card/empty-state/filter-bar/pagination): 4 Aufrufe innerhalb der Component-Templates
- **Direkte Service-Nutzung:** maes (7x), tpt (5x), tp (3x), tc (3x), mio (3x), mmb (3x)
- **Nicht abgedeckt:** mio/mmb-Service-Templates rendern eigene Akkordeon-Markup (kein `dhps_component('accordion')`-Aufruf - Mismatch zur Discovery-Doku)

### 1.4 BEM-Konventionen

Konsequent durchgehalten:

- Block: `.dhps-content-card`, `.dhps-content-list`, `.dhps-filter-bar`,
  `.dhps-pagination`, `.dhps-empty-state`, `.dhps-skeleton`, `.dhps-lazy-img`
- Modifier: `--news/--video/--document`, `--cols-N`, `--grid/--list/--masonry`,
  `--service-{slug}`, `--has-tags/--has-sort`
- Element: `__media`, `__body`, `__title`, `__teaser`, `__meta`, `__actions`,
  `__badge`, `__detail`, `__toggle`, `__container`, etc.

### 1.5 Filter-Hook-Inventar

| Hook | Wo | Zweck | Used externally? |
|------|-----|-------|------------------|
| `dhps_component_template_path` | Registry::get_template_path() | Theme-Override-Path-Patch | Nein (Stand v0.19.1) |
| `dhps_component_props` | helpers::dhps_component() | Props-Mutation pre-Render | Nein |
| `dhps_register_components` | helpers::dhps_register_components() | Third-Party-Register-Action | Nein |
| `dhps_content_card_heading_level` | content-card.php | h2-h6 fuer SEO-Hierarchie | Nein |

**Fazit:** Filter sind vorhanden, aber **niemand nutzt sie extern**.
Theme-Override-API existiert auf dem Papier - kein produktives Beispiel
im Codebase.

---

## 2. Pain-Points im aktuellen System

### 2.1 Reale Pain-Points (objektiv)

| # | Pain | Evidenz | Severity |
|---|------|---------|----------|
| P1 | Prop-Validierung pro Template dupliziert (`isset/is_string/is_array`-Blocks) | content-card.php:52-69, content-list.php:38-56, jedem Template ~15-25 LOC | LOW (boilerplate, sicher) |
| P2 | Default-Props zweimal definiert: Registry-Config + Template-Normalisierung | Beide Stellen koennen drift; Registry-Defaults werden de-facto im Template nochmal gesetzt | LOW (kein Bug, Doppel-Coverage) |
| P3 | Keine zentrale Type-Hint/Schema-Definition | Props sind dokumentiert im PHPDoc, nicht maschinen-lesbar | MEDIUM (IDE-Autocomplete schwer) |
| P4 | Path-Traversal-Defense-in-Depth fehlt | Audit M-1 v0.14.0, akzeptiert, aber 5min Fix | LOW (nicht erreichbar) |
| P5 | Inline-Style fuer CSS-Custom-Properties | `style="--cols: 2;"` in content-list.php:98, pagination.php | LOW (CSP `style-src 'unsafe-inline'` ohnehin durch Alpine noetig) |
| P6 | `accordion`-Component registriert aber unused | mmb/mio-Templates rendern eigene Akkordeons | LOW (Dead-Code, vielleicht nicht gewollt) |
| P7 | Inline-SVG-Icons dupliziert (content-card hat `meta_icons`-Map, empty-state hat eigene Map) | Beide haben `play/document/calendar/clock/etc.` separat | LOW (~50 LOC Duplikation) |
| P8 | `dhps_component()` echoed nicht direkt - jedes Template muss `echo dhps_component( ... )` mit phpcs:ignore-Kommentar wrappen | 33 Stellen mit `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped` | LOW (Noise im Code) |

### 2.2 Pain-Points die NICHT existieren (oft genannt, hier nicht relevant)

- ~~"Slot-System fehlt"~~ - Components verschachteln per `dhps_component()`-Aufruf in Templates. Praktisch sind die 8 Components self-contained, keine Slot-Anforderung bekannt.
- ~~"Theme-Override-Mechanismus fehlt"~~ - existiert (Child/Parent/Plugin-Hierarchie), wird nicht genutzt aber bereit.
- ~~"Testing fehlt"~~ - Lead-Tests sind Render-basiert (Snapshot via Smoke-Tests), nicht Component-Unit-Tests; v1.0 koennte das wollen, v0.20.0 nicht.
- ~~"Type-Safety fehlt"~~ - PHP-Templates sind by-design loose typed. Schema-Validation existiert fuer Live-Preview (`SERVICE_ATTS_SCHEMA`), nicht fuer Components - aber das ist OK weil Components nicht User-Input bekommen.
- ~~"Performance-Probleme"~~ - Conditional-Enqueue funktioniert seit v0.15.5, was_used() bewaehrt.

### 2.3 Pain-Points die theoretisch existieren (KEIN realer Bedarf)

- "Komponenten-Library als externes Package" - YAGNI, Plugin ist Monolith fuer eine Use-Case-Domain
- "JSX-Style-Templates" - WordPress-Convention ist PHP-Templates
- "Headless-API" - Niemand fragt nach Headless-Frontend
- "Web-Components" - Browser-Support OK, aber Maintenance-Aufwand >> Wert
- "Vue/React-Migration" - explizit Non-Goal seit v0.14.0 Architektur-Doc

---

## 3. Optionen-Analyse

### Option Alpha - Modernisierung Component-System v1

**Was:** Type-Hints, Trait-System, Slot-System, Schema-Registry-Centralisation,
Component-Registry-Class statt prozedurale Funktion.

| Kriterium | Bewertung |
|-----------|-----------|
| Aufwand | **L** (~600-1000 LOC + 8 Template-Patches) |
| BC-Risiko | **HOCH** (Templates `extract()`-Variablen-Namen sind Vertrag; Theme-Overrides koennten brechen) |
| User-Wert | NIEDRIG (User merkt nichts) |
| Dev-Wert | MEDIUM (besseres DX) |
| Migrations-Bedarf | 8 Component-Templates + 16 Service-Templates |
| Strategische Passung | Schwach - DTO-Aera war ueber Daten-Modell, nicht ueber Render-API |

**Verdikt:** Nicht empfohlen ohne klaren Pain-Point. Schema-Centralisation
(P2) ist der einzige solide Treiber.

### Option Beta - Render-Layer-Refactor (Templates rendern Components statt PHP-Includes)

**Was:** Service-Templates werden zu reinen Component-Composition-Files
(z.B. nur 1 `dhps_component('service-page', $args)`-Aufruf). Markup wandert
komplett in Components.

| Kriterium | Bewertung |
|-----------|-----------|
| Aufwand | **XL** (~2000-3000 LOC, alle 22 Service-Templates refactor) |
| BC-Risiko | **SEHR HOCH** (Theme-Override-Templates sind Vertrag) |
| User-Wert | KEINER (gleiches Markup) |
| Dev-Wert | MEDIUM (DRY-Code) |
| Tech-Debt-Reduktion | viele Doppelungen verschwinden, aber neue Komplexitaet |
| Strategische Passung | Sehr schwach - bedeutet zweiter Massen-Migrate nach v0.18.0 |

**Verdikt:** **Nein.** v0.18.0 hat 22 Templates angepackt. Direkte
Wiederholung mit hoeherem Risiko gefaehrdet Schema-Vertrag-Streak.

### Option Gamma - Headless-Render-API (Components via REST)

**Was:** REST-Endpoint `/wp-json/dhps/v1/component/{name}` liefert
gerenderten HTML-String fuer externe Konsumenten.

| Kriterium | Bewertung |
|-----------|-----------|
| Aufwand | **M** (~400-600 LOC + Security-Audit) |
| BC-Risiko | NIEDRIG (additiv) |
| User-Wert | **0** (kein Headless-Konsument bekannt) |
| Dev-Wert | NIEDRIG (LP-Live-Preview hat eigene REST-Bridge seit v0.15.3) |
| Strategische Passung | Sehr schwach - Plugin ist WordPress-Frontend, kein Headless |

**Verdikt:** YAGNI. Kein Konsument im Markt. Falls je gebraucht: gibt es
bereits `DHPS_Preview_Renderer` als Vorlage.

### Option Delta - Web-Components / Custom-Elements

**Was:** SSR-Fallback + Client-Side `<dhps-content-card>`-Custom-Elements.

| Kriterium | Bewertung |
|-----------|-----------|
| Aufwand | **XL** (~3000+ LOC, JS-Build-Setup, ESM-Bundling) |
| BC-Risiko | NIEDRIG (additiv parallel zu PHP-Render) |
| User-Wert | NIEDRIG (Alpine.js erfuellt Interaktivitaet bereits) |
| Dev-Wert | LOW (Wartung von 2 Render-Pfaden ist Anti-Pattern) |
| Strategische Passung | Konflikt mit "kein Vendor-JS-Framework"-Prinzip aus v0.14.0 |

**Verdikt:** **Nein.** Verstoesst gegen Plugin-Architektur-Prinzipien.

### Option Epsilon - Hooks-System fuer Render-Pipeline

**Was:** Pre/Post-Render-Filter, Slot-Hooks (`dhps_component_before_render`,
`dhps_component_after_render`, `dhps_component_slot_{name}_{slot}`).

| Kriterium | Bewertung |
|-----------|-----------|
| Aufwand | **M** (~300-400 LOC + Filter-Doku) |
| BC-Risiko | NIEDRIG (additive Hooks) |
| User-Wert | NIEDRIG (Theme-Entwickler-Tool) |
| Dev-Wert | MEDIUM (sauberere Erweiterungspunkte) |
| Strategische Passung | Mittel - WordPress-Idiomatisch |
| Riskoabschaetzung | **GEFAEHR**: Hooks ohne dokumentierten Use-Case fuehren zu API-Sucht ("dead Hooks", die nie verschwinden duerfen) |

**Verdikt:** Vorerst nicht. Filter zuruecksetzen wenn konkreter
Theme-Entwickler-Bedarf entsteht. **YAGNI-Filter.**

### Option Zeta - Klein bleiben (Polish + Doku)

**Was:** Nur 3-5 gezielte Polish-Massnahmen, keine API-Aenderung.

| Kriterium | Bewertung |
|-----------|-----------|
| Aufwand | **S** (~120-180 LOC + Doku) |
| BC-Risiko | **MINIMAL** (Filter-Validation, keine API-Aenderung) |
| User-Wert | 0 (transparent) |
| Dev-Wert | LOW-MEDIUM (Defense-in-Depth, bessere Dokumentation) |
| Tech-Debt-Reduktion | klein, aber sauber abgrenzbar |
| Strategische Passung | Sehr stark - "Foundation festklopfen, nicht erweitern" |

**Verdikt:** **EMPFOHLEN.** Siehe Sektion 6.

---

## 4. Empfehlung: Option Zeta-Plus

**Zeta-Plus** = "klein bleiben" + 3 gezielt-konkrete Verbesserungen:

1. **Defense-in-Depth (Audit M-1 schliessen)**: Regex-Sanity-Check
   `/^[a-z0-9-]+$/` auf `$name` in `dhps_component()` + Realpath-Whitelist
   im `dhps_component_template_path`-Filter (~30 LOC).
2. **Icon-Deduplikation**: SVG-Icon-Map aus `content-card.php` +
   `empty-state.php` in einen Helper `dhps_get_component_icon( $slug )`
   ziehen (~50 LOC, -50 LOC im Template = netto -0 LOC, aber DRY).
3. **Component-API-Doku**: Neue Doku
   `docs/architecture/40-COMPONENT-API-V1-STABLE.md` als
   **expliziter Stabilitaets-Vertrag** ("v1 ist final, kein v2 geplant").
   Inkl. Theme-Override-Beispiel und Filter-Hook-Use-Cases.

**Optional (vielleicht weglassen):**

4. **Accordion-Component pruefen**: Entweder fuer MMB-Lazy-Akkordeon
   nutzen ODER deregistrieren. **Empfehlung: deregistrieren** (Tech-Debt
   senken, MMB-Markup ist Spezial-Fall mit AJAX-Lazy-Load).
5. **`dhps_component`-Echo-Convenience**: `dhps_component_e()` als kurze
   Echo-Variante zum Auto-Escape-Vermerk. **Eher Noise als Wert** -
   weglassen.

**Empfohlener Scope v0.20.0:** Massnahmen 1+2+3, **ohne 4+5**.

---

## 5. Wenn Option Alpha (was wuerde man machen?) - nur fuer Vollstaendigkeit

Falls die Lead doch Option Alpha will, hier der minimal-invasive Pfad:

1. **Schema-Centralisation:** Default-Props aus Registry-Config in
   `Component_Schema`-Klasse mit `validate( $props )`-Methode.
2. **PHPDoc generieren:** `@template`-Tags fuer IDE.
3. **`content-card.php` Heavy-Refactor:** 270 LOC -> kleinere Helper-Funktionen.

**Aber:** Theme-Override-Templates werden brechen, wenn Schema-Validation
strict ist (loose-validation kein Fortschritt vs. v1). Schema-Vertrag-
Vorgehen waere 21. Iteration - Streak weniger gefaehrdet als bei v0.17.x,
aber Render-API-Bruch ist heikler als Daten-Bruch.

**Empfehlung gegen Alpha:** zu wenig Wert, zu viel Risiko.

---

## 6. Wenn Option Zeta-Plus (was poliert man dann?)

### 6.1 Phase 1 - Defense-in-Depth (~30 LOC, ~30min)

`includes/dhps-component-helpers.php` (vor Existenz-Check):

```php
// Name-Sanity (Defense-in-Depth, Audit M-1).
if ( ! preg_match( '/^[a-z][a-z0-9-]*$/', $name ) ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        return '<!-- dhps_component: ungueltiger Name "' . esc_html( $name ) . '" -->';
    }
    return '';
}
```

`includes/class-dhps-component-registry.php` (`get_template_path()`,
nach Filter-Apply):

```php
// Realpath-Whitelist (Defense-in-Depth, Audit M-2).
if ( '' !== $resolved ) {
    $real = realpath( $resolved );
    $allowed_roots = array(
        realpath( DEUBNER_HP_SERVICES_PATH . 'public/views/components/' ),
        realpath( get_stylesheet_directory() . '/dhps/components/' ),
        realpath( get_template_directory() . '/dhps/components/' ),
    );
    $is_within = false;
    foreach ( $allowed_roots as $root ) {
        if ( false !== $root && false !== $real && 0 === strpos( $real, $root ) ) {
            $is_within = true;
            break;
        }
    }
    if ( ! $is_within ) {
        $resolved = '';  // Reject ausserhalb-Whitelist
    }
}
```

### 6.2 Phase 2 - Icon-Deduplikation (~50 LOC neuer Helper, -50 LOC Template)

Neuer Helper `dhps_get_component_icon( string $slug, int $size = 14 ): string`
in `includes/dhps-component-helpers.php`. SVG-Map zentral.
`content-card.php` + `empty-state.php` rufen den Helper.

### 6.3 Phase 3 - Doku-Stabilitaets-Vertrag

Neue Doku `docs/architecture/40-COMPONENT-API-V1-STABLE.md`:

- "Component-API ist seit v0.14.0 stabil"
- "Kein v2 geplant"
- "Erweiterung via 3 Filter-Hooks moeglich"
- Beispiel: Theme-Override fuer `content-card.php`
- Beispiel: `dhps_component_props`-Filter fuer service-spezifische Anreicherung
- **Schema-Vertrag-Versprechen**: Component-Props sind additiv erweiterbar,
  nie zu loeschen

---

## 7. BC-Strategie

**Component-API ist Theme-Override-Schnittstelle - BC-Bruch riskant.**

### 7.1 Was MUSS BC-stabil bleiben

- `dhps_component( $name, $props )`-Signatur
- `dhps_render_component( $name, $props )`-Signatur
- `DHPS_Component_Registry::register/is_registered/get_config/get_template_path`-Signaturen
- Component-Template-Pfade `public/views/components/{name}.php`
- BEM-CSS-Klassen aller bestehenden 8 Components (Theme-CSS-Selectors!)
- Alpine-Component-Names `dhpsContentCard`, `dhpsContentList`, etc.
- 4 Filter-Hooks + 1 Action

### 7.2 Was darf sich aendern (additiv)

- Neue Filter-Hooks
- Neue Helper-Funktionen
- Neue Default-Props (additiv)
- Neue Component-Templates

### 7.3 BC-Sicherheit der Zeta-Plus-Massnahmen

| Massnahme | BC-Sicher? |
|-----------|-----------|
| Regex-Sanity-Check `$name` | JA - aktuelle 8 Names matchen `[a-z][a-z0-9-]*` |
| Realpath-Whitelist | JA - bestehende Pfade liegen alle innerhalb der Whitelist |
| Icon-Helper-Funktion | JA - additiv, alte Maps koennen bleiben |
| Doku-Stabilitaets-Vertrag | JA - reine Doku |

---

## 8. Spec-Aufteilung

### Empfehlung: **Lead-Direct ohne Specialist-Team**

**Gruende:**

- Scope ist klein (~120-180 LOC)
- Keine Render-Logik-Aenderung
- Keine Template-Migrationsmasse
- Keine Adapter-Pipeline-Beruehrung
- Schema-Vertrag-Vorgehen trivial (kein Service-Atts-Schema betroffen)

**Phase-Aufteilung Lead-Direct:**

| Phase | Inhalt | Aufwand |
|-------|--------|---------|
| P1 | Defense-in-Depth (M-1 + M-2 Audit) | 30min |
| P2 | Icon-Deduplikation + Helper-Tests | 60min |
| P3 | Stabilitaets-Vertrag-Doku | 45min |
| P4 | Stage-Smoke (8 Component-Render-Smoke + 22 Service-Smoke) | 30min |
| P5 | CHANGELOG + MEMORY-Update | 15min |

**Gesamt:** ~3h Lead-Direct.

### Discovery-Only-Alternative

Falls Lead Zeta-Plus zu wenig findet:

- v0.20.0 = **Discovery-Only** (nur diese Doku)
- v0.20.1 = Implementation (Lead-Direct nach Lead-Bewertung)
- v0.21.0 = naechster echter Roadmap-Punkt (z.B. Datum-Normalisierung,
  AJAX-Migration, oder MAES-Modules-Filter-Move-out)

**Vorteil Discovery-Only:** Schema-Vertrag-Streak unbeeinflusst.
**Nachteil:** kein User-sichtbarer Fortschritt fuer v0.20.0.

---

## 9. Risiken + Tech-Debt

### 9.1 Risiken Zeta-Plus

| ID | Risiko | Wahrscheinlichkeit | Impact | Mitigation |
|----|--------|---------------------|--------|------------|
| R1 | Realpath-Whitelist blockiert legitimen Theme-Override | NIEDRIG | MITTEL | Whitelist umfasst alle 3 Theme-Roots; Filter `dhps_component_allowed_roots` einfuehren als Escape-Hatch |
| R2 | Regex `^[a-z][a-z0-9-]*$` zu strict fuer zukuenftige Names | NIEDRIG | NIEDRIG | Decken alle 8 bestehende Names ab; Filter waere YAGNI |
| R3 | Icon-Helper-Aufruf schlaegt fehl bei `function_exists`-Race | SEHR NIEDRIG | NIEDRIG | Helper in dhps-component-helpers.php (mit dem Registry zusammen geladen) |
| R4 | Doku-Stabilitaets-Vertrag verspricht zu viel | NIEDRIG | MITTEL | Vorsichtig formulieren: "BC bis v1.0", nicht "ewig" |

### 9.2 Tech-Debt-Tickets fuer spaeter

| Ticket | Beschreibung | Ziel-Version |
|--------|--------------|--------------|
| TD-V0200-1 | `accordion`-Component pruefen: nutzen oder deregistrieren | v0.20.1 |
| TD-V0200-2 | MMB-Lazy-Akkordeon auf `accordion`-Component migrieren (sehr riskant - AJAX-Spezialitaet) | v1.0 / NIE |
| TD-V0200-3 | Component-Snapshot-Tests via WP_Mock (Vorschlag aus v0.14.0 Anhang B) | v1.0 / OPTIONAL |
| TD-V0200-4 | `dhps-components.css` Splitting (966 LOC fuer 8 Components) - per-Component CSS-File mit Conditional-Enqueue | OPTIONAL |
| TD-V0200-5 | Filter `dhps_component_allowed_roots` einfuehren (siehe R1) | OPTIONAL |

### 9.3 NICHT-Tech-Debt (bewusst akzeptiert)

- Prop-Normalisierung pro Template (P1 oben) - explizite Defensiv-Patterns
- Inline-Style fuer CSS-Custom-Properties (P5) - Alpine erfordert ohnehin `'unsafe-inline'`
- phpcs:ignore-Kommentare an `echo dhps_component()` (P8) - dokumentierte Trust-Decision

---

## 10. Spec-Briefing (Lead-Direct, falls Zeta-Plus implementiert)

### 10.1 Mission

Component-System v1 als Stabil-Vertrag besiegeln. 2 Defense-in-Depth-Patches
+ 1 Helper-Konsolidierung + 1 Stabilitaets-Doku.

### 10.2 Implementation-Reihenfolge

1. `includes/dhps-component-helpers.php` - Regex-Check
2. `includes/class-dhps-component-registry.php` - Realpath-Whitelist
3. `includes/dhps-component-helpers.php` - Icon-Helper neu
4. `public/views/components/content-card.php` - Icon-Map durch Helper-Aufrufe ersetzen
5. `public/views/components/empty-state.php` - Icon-Map durch Helper-Aufrufe ersetzen
6. Doku `docs/architecture/40-COMPONENT-API-V1-STABLE.md`
7. CHANGELOG `docs/project/58-CHANGELOG-v0200.md`

### 10.3 Tests (Lead-Manuell)

| # | Test | Erwartung |
|---|------|-----------|
| T1 | `dhps_component( '../../etc/passwd', [] )` | leer (Regex-Reject) |
| T2 | `dhps_component( 'content-card', [...gueltig...] )` | rendert wie bisher |
| T3 | Filter `dhps_component_template_path` setzt Pfad ausserhalb Whitelist | leer (Realpath-Reject) |
| T4 | Filter setzt legitimen Child-Theme-Pfad | rendert Theme-Override |
| T5 | Stage-Smoke: 22 Service-Templates rendern bytewise identisch zu v0.19.1 | 0 Diff |
| T6 | Stage-Smoke: ContentCard-SVG-Icons (play/document/calendar/clock/file/download/link) sind identisch | 0 Diff |
| T7 | EmptyState-Icons (inbox/calculator/document/video) sind identisch | 0 Diff |
| T8 | WP_DEBUG: ungueltiger Name liefert HTML-Kommentar | sichtbar |

### 10.4 Akzeptanzkriterien

- 0 BC-Bruch
- 0 Schema-Drift (Schema-Vertrag-Streak: 21. Iteration unauffaellig)
- Stage-Smoke 22/22 Templates identisch
- Doku-Stabilitaets-Vertrag definiert klare Erweiterungs-Hierarchie

---

## 11. Antwort auf die strategische Frage

> **Brauchen wir wirklich Component-System v2?**

**Nein.** Component-System v1 ist nach v0.14.0 + v0.15.5 ausgereift. Die
DTO-Foundation-Aera (v0.17-v0.19.1) hat die **Daten-Schicht** angepackt -
die Render-Schicht war seit v0.15.5 nicht der Engpass. **Kein User
fragt nach neuen Component-Features.** Kein Theme nutzt heute die
Override-Filter.

> **Wenn ja, was bringt es konkret?**

Konkrete v2-Verbesserungen waeren:

- Schema-Centralisation (Tech-Debt P2)
- Slot-System (kein realer Use-Case)
- Hooks-Pipeline (kein Konsument)

Alle diese Punkte loesen Probleme, die niemand hat.

> **Wenn nein, was machen wir stattdessen mit v0.20.0?**

**Option A: Zeta-Plus implementieren** (~3h Lead-Direct)
**Option B: v0.20.0 ueberspringen, v0.19.2 als Polish-Release** (Datum-Normalisierung oder andere offene Punkte)
**Option C: v0.20.0 = Discovery-Only (nur diese Doku)** + v0.20.1 ist
naechster echter Inhalt
**Option D: v0.20.0 = anderer struktureller Bruch**, z.B. Parser-Layer-
Modernisierung, falls Lead andere Roadmap-Priority sieht

**Empfehlung: Option A (Zeta-Plus implementieren).**

---

## Anhang A - Component-Templates LOC-Bilanz

| Datei | LOC | Notiz |
|-------|-----|-------|
| `content-card.php` | 270 | komplexester Template, Icon-Map ~50 LOC |
| `pagination.php` | 202 | Load-More + Numeric-Pages |
| `filter-bar.php` | 187 | Search + Chips + Sort |
| `content-list.php` | 162 | Komposiert 3 Sub-Components |
| `accordion.php` | 81 | CSS-only via `<details>` |
| `empty-state.php` | 80 | Icon-Map ~30 LOC (Duplikation mit content-card) |
| `skeleton-loader.php` | 70 | Shimmer-Pattern |
| `lazy-image.php` | 61 | IntersectionObserver-Stub |
| **Summe Templates** | **1113** | |
| `class-dhps-component-registry.php` | 241 | |
| `dhps-component-helpers.php` | 114 | |
| `css/dhps-components.css` | 966 | |
| **Summe System** | **2434** | |

## Anhang B - Filter-Hook-Reference (Stand v0.19.1)

| Hook | Signatur | Wann | Default-Behavior |
|------|----------|------|------------------|
| `dhps_component_template_path` | `(string $path, string $name, array $props)` | In `Registry::get_template_path()` nach Hierarchie-Aufloesung | Path-as-is |
| `dhps_component_props` | `(array $props, string $name)` | In `dhps_component()` nach Default-Merge | Props-as-is |
| `dhps_content_card_heading_level` | `(string $tag, string $type)` | In `content-card.php` fuer Titel-Tag | `h3` |
| `dhps_register_components` | `()` | Action am Ende von `dhps_register_components()` | nichts |

## Anhang C - Schema-Vertrag-Vorgehen

Diese Discovery folgt dem etablierten Schema-Vertrag-Vorgehen (20x in Folge
ohne Critical-Drift seit v0.15.0). 21. Iteration:

1. Inventory zuerst (Sektion 1)
2. Pain-Points objektiv katalogisieren (Sektion 2)
3. Optionen-Spektrum aufspannen (Sektion 3)
4. Empfehlung mit Begruendung (Sektion 4)
5. BC-Risiko explizit ausweisen (Sektion 7)
6. Tech-Debt-Tickets benennen (Sektion 9.2)
7. Spec-Briefing mit konkreten Tests (Sektion 10)

Result: 0 Annahmen ohne Beleg.
