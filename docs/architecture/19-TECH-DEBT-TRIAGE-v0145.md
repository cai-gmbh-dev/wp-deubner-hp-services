# Tech-Debt-Triage v0.14.5

## Stand: 2026-05-25
## Autor: Discovery-Specialist
## Scope: 6 v0.14.x-Tech-Debt-Tickets + 3 v0.15.0-Low-Severity-Polish-Items
## Ziel: Klarer Scope fuer v0.14.5 (Tech-Debt-Cleanup), Empfehlung welche
## Tickets ggf. nach v0.14.6 oder v0.15.1+ verschoben werden.

---

## Sektion 1: Triage-Tabelle (9 Tickets x Aufwand/Risiko/BC/Platzierung)

| # | Ticket | Aufwand | Risiko | BC-Impact | Empfohlene Platzierung |
|---|--------|---------|--------|-----------|------------------------|
| 1 | tp/compact.php ContentCard-Migration + JS-Refactor `initCompactAccordion` | L (mehrere h) | Hoch | Theme-Override (Markup), API (data-attrs auf TP-Compact-Player) | v0.14.6 (eigener Release) |
| 2 | TPT-Modules-Layer (Admin-Texte via `$data` durchreichen) | M (~1h) | Niedrig | Keine (Template-API stabil, nur Datenquelle wechselt) | v0.14.5 |
| 3 | MMB-card/compact Lazy-Akkordeon-Migration | L (mehrere h) | Mittel | Theme-Override (Markup-Wechsel), AJAX-Endpoint geteilt | v0.14.6 (eigener Release) |
| 4 | TC-CSS-Cleanup `.dhps-tc__empty-icon|title|text` toter Code | S (< 30 min) | Niedrig | Theme-Override (BC-Hook-Klassen) | v0.14.5 |
| 5 | TC-Compact-Icon-Groesse via `.dhps-empty-state--compact` Modifier | S (< 30 min) | Niedrig | Keine (rein additive CSS) | v0.14.5 |
| 6 | aria-controls auf TP-Filter-Buttons | S (< 30 min) | Niedrig | Keine (additive A11y-Attribute) | v0.14.5 |
| 7 | OTA-Preview-Edge-Case `<= 6` Zeichen (`***` statt Full+...) | S (< 30 min) | Niedrig | Keine (Admin-only, Display-Veraenderung in Edge-Case) | v0.14.5 |
| 8 | Rate-Limiter Sliding-Window-Drift dokumentieren | S (< 30 min) | Niedrig | Keine (reine Doku) | v0.14.5 |
| 9 | Rate-Limiter Race-Condition Counter-Increment dokumentieren | S (< 30 min) | Niedrig | Keine (reine Doku) | v0.14.5 |

### Klassifikation

- **5 x S (Easy-Win)**: #4, #5, #6, #7, #8+#9 (Doku-Doppel)
- **1 x M**: #2 (TPT-Modules-Layer)
- **2 x L (Eigener Release noetig)**: #1 (tp/compact), #3 (MMB-card/compact)

---

## Sektion 2: Risiko-Hotspots

### 2.1 tp/compact.php Refactor + `initCompactAccordion` (Ticket #1) - HOCH-RISIKO

#### Was tut `initCompactAccordion` heute?

Inspiziert in `public/js/dhps-tp.js` Z. 378-444. Die Funktion macht **zwei
Dinge**, die fest aneinandergekoppelt sind:

**(A) Accordion-Toggle pro Rubrik** (Z. 381-393):
- Findet alle `.dhps-tp-compact__trigger`-Buttons im Container
- Toggle `aria-expanded` auf Trigger + `aria-hidden` auf Content-Container
- Keine ContentCard-Abhaengigkeit, rein BEM-strukturiert

**(B) Video-Player-Spawn beim Klick auf ein Video-Item** (Z. 396-443):
- Event-Delegation auf `.dhps-tp-compact__video-btn`-Klicks
- Liest `data-video-slug`, `data-poster-url`, `data-v-modus` vom `<li
  class="dhps-tp-compact__item">`
- Entfernt bestehende `.dhps-tp-compact__player` aus der Liste
- Erzeugt dynamisch `<div class="dhps-tp-compact__player"><div
  class="dhps-tp-video__player"><div class="dhps-tp-video__poster"
  data-...>...</div></div></div>`
- Inserted via `item.after( playerDiv )` direkt unter das geklickte LI
- Ruft `loadVideoIframe()` mit dem frisch erzeugten Poster-Element

#### Warum ContentCard-Migration brechen wuerde

Die ContentCard-Komponente rendert eine `<article
class="dhps-content-card">`-Struktur mit eigenem Markup (Title in `<h3>`,
Action-Footer mit Button etc.). Wenn `tp/compact.php` auf ContentCard
migriert wird, brauchen folgende Anpassungen einen sauberen Vertrag:

1. **Selektor-Wechsel**: `.dhps-tp-compact__video-btn` -> ContentCard
   liefert standardmaessig keinen Play-Button mit `data-video-slug`.
   Entweder ContentCard erweitern (Prop fuer Inline-Play-Click), oder
   data-attrs am Card-Root einsetzen (analog `tpt/default.php` v0.14.3:
   `data_attrs => array('video-slug' => ..., 'poster-url' => ...,
   'v-modus' => ...)`) und JS-Selektor auf Card-Root umstellen.

2. **Player-Insertion-Anchor**: `item.after( playerDiv )` insertet
   geschwister-mode neben dem `<li>`. Wenn ContentCard ein `<article>` ist,
   muss das JS den richtigen Wrapper finden (`closest('.dhps-tp-compact__item')`
   wuerde nicht mehr existieren). Loesung: Neuer Anchor wie
   `closest('.dhps-content-card')` oder bewusste Wrapper-Klasse
   `dhps-tp-compact__item` zusaetzlich an die ContentCard haengen (BC-Hack
   analog `dhps-tp-card dhps-tpt-card` in TPT-Migration v0.14.3).

3. **Player-Cleanup**: `list.querySelectorAll('.dhps-tp-compact__player')`
   sucht alle bestehenden Player in derselben Akkordeon-Rubrik. Wenn die
   Struktur durch ContentCard veraendert wird, muss die `closest('.dhps-tp-compact__list')`-Logik
   auf einen neuen Container-Anchor migriert werden.

4. **Click-Konflikt mit ContentCard-Action**: ContentCard rendert in der
   Action-Footer-Slot einen `<button class="dhps-content-card__action"
   href="#play">`-Link. Dieser kollidiert ggf. mit der bestehenden
   `.dhps-tp-compact__video-btn`-Event-Delegation. Loesung: entweder
   ContentCard-Action ignorieren (keine `href`-Action setzen, nur
   data-attrs am Root) oder neue dispatch-event-Logik einfuehren.

#### Empfehlung fuer #1

**NICHT in v0.14.5 anpacken.** Ticket #1 ist mehr-h-Arbeit mit echtem
JS-Refactor-Risiko + Test-Surface (Player-Spawn in Sidebar, Filter
+ Lazy-Load-Interaktion). Verdient einen eigenen Release v0.14.6 mit:

- Discovery-Spec (Migrations-Plan analog v0.14.3)
- 1 Spec fuer Template + JS-Refactor
- QA + Sec-Audit
- Smoke gegen ein Theme mit TP-Compact-Override

Aufwand: ~4-6h Lead + 1 Discovery + 1 Spec + 2 Audits. **Eigener Release
v0.14.6.**

### 2.2 MMB-card/compact Lazy-Akkordeon-Migration (Ticket #3) - MITTEL-RISIKO

#### Status Quo

Inspiziert `public/views/services/mmb/{card,compact}.php`:

- **card.php** rendert pro Kategorie ein **inline pre-rendered Card-Grid**:
  alle Fact-Sheets sofort als `<div class="dhps-mmb-card-item">` im DOM
  (ohne data-dhps-mmb-lazy-state).
- **compact.php** rendert pro Kategorie ein **inline pre-rendered
  `<ul class="dhps-mmb-list--compact">`** mit Compact-Items.
- **default.php** (v0.14.0) nutzt das Lazy-Akkordeon-Pattern mit
  `data-dhps-mmb-lazy-state="pending"` + Skeleton-Slot + AJAX-Endpoint
  `dhps_mmb_category_load`.

#### Muss derselbe AJAX-Endpoint genutzt werden?

**JA, mit Anpassung am Partial.** Aktueller Endpoint nutzt
`render_category_html()` in `class-dhps-mmb-ajax-handler.php` Z. 352-369,
das fest auf `public/views/services/mmb/partials/category-content.php`
verweist. Dieses Partial rendert **nur das `<ul class="dhps-mmb-list">`-Pattern**
(default-Layout-Struktur). Fuer card/compact gibt es zwei Optionen:

**Option A: Layout-Parameter im Endpoint**
- Endpoint erweitern um `&layout=card|compact|default`-Param
  (sanitize_key + Whitelist)
- `render_category_html()` waehlt das passende Partial:
  - `partials/category-content.php` (default, bestehend)
  - `partials/category-content-card.php` (NEU, Card-Grid)
  - `partials/category-content-compact.php` (NEU, Compact-List)
- JS in `dhps-mmb.js` ergaenzt URL-Param aus
  `container.getAttribute('data-layout')`
- BC: default ohne Layout-Param verhaelt sich wie bisher

**Option B: Drei separate Endpoints**
- `dhps_mmb_category_load`, `dhps_mmb_category_load_card`,
  `dhps_mmb_category_load_compact`
- Nachteil: 3x Sicherheits-Code (Nonce, Rate-Limit, Whitelist) zu
  pflegen. **Nicht empfohlen.**

**Empfehlung: Option A.** Layout-Parameter ist die saubere Loesung.
Erfordert 2 neue Partials + 1 JS-Anpassung + Endpoint-Erweiterung.

#### Empfehlung fuer #3

**NICHT in v0.14.5 anpacken.** Erfordert:
- 2 neue Partials (card + compact-Struktur, plus deren `<li>`-vs-`<div>`
  vs `<ul>`-Markups exakt aequivalent zur initial-Render-Variante)
- Endpoint-Erweiterung (Layout-Whitelist)
- JS-Anpassung (`data-layout`-Attribut + URL-Param)
- Migration der card.php + compact.php Initial-Render (Skeleton-Slots
  statt inline pre-render)
- Achtung: card.php hat **Tab-Navigation** (Filter-Bar) - die muss mit
  Lazy-Loading-State zusammenarbeiten (`data-filter="all"` zeigt heute
  alle Kategorien; bei Lazy waeren das alle Skeletons - UX-Frage)

Aufwand: ~4-5h Lead + 1 Discovery + 1 Spec + 2 Audits. **Eigener Release
v0.14.6.** Kann mit Ticket #1 zu einer kombinierten Release v0.14.6
"Layout-Lazy + Compact-Player-Refactor" kombiniert werden.

---

## Sektion 3: Easy-Wins (Lead-Direkt-Liste)

Folgende Tickets sind so klein, dass der Lead sie ohne Specialist-
Orchestrierung direkt umsetzen kann:

| # | Ticket | Files | Geschaetzt |
|---|--------|-------|------------|
| 4 | TC-CSS-Cleanup `.dhps-tc__empty-icon|title|text` toter Code | `css/dhps-frontend.css` Z. 1978-1996 entfernen | 5 min |
| 5 | TC-Compact-Icon `.dhps-empty-state--compact` Modifier | `css/dhps-components.css` (neue Regel) | 10 min |
| 6 | aria-controls auf TP-Filter-Buttons | `tp/default.php` + `tp/card.php` (4 Stellen) | 15 min |
| 7 | OTA-Preview-Edge-Case `<= 6` Zeichen | `class-dhps-health-collector.php` Z. 229-231 (1 Methode) | 5 min |
| 8 | Sliding-Window-Drift dokumentieren | `class-dhps-admin-rest.php` Doc-Block ueber `check_rate_limit` | 5 min |
| 9 | Race-Condition Counter dokumentieren | `class-dhps-admin-rest.php` Doc-Block (selbe Stelle) | 5 min |

**Gesamt: ~45 min Lead-Arbeit** fuer 6 Tickets. Smoke-Test pro Ticket
trivial (CSS-Reload bzw. Single-Endpoint-Test).

---

## Sektion 4: Spec-Aufteilung-Empfehlung

### Empfehlung: 1 Spec + Lead-Direkt-Pass

**v0.14.5 Specialist-Plan:**

| Phase | Aktivitaet | Wer |
|-------|------------|-----|
| P1 Lead-Direct | 6 Easy-Wins (Tickets #4-#9) seriell, Smoke pro Aenderung | Lead |
| P2 Spec | TPT-Modules-Layer (Ticket #2) | 1 Specialist |
| P3 QA + Sec | Knapper QA (regression auf TPT + Admin-Dashboard) + Sec (OTA-Preview-Edge-Case) | 2 parallel |
| P4 Release | CHANGELOG + Memory + Tag | Lead |

**Begruendung:**

- Tickets #4-#9 sind **so klein**, dass Spec-Orchestrierungs-Overhead
  groesser waere als die Arbeit selbst.
- Ticket #2 (TPT-Modules-Layer) ist **das einzige strukturelle Ticket**
  in v0.14.5 - hier lohnt sich ein Spec mit klarem Vertrag (Modules-
  Layer-API analog zu anderen Services).
- Tickets #1 und #3 werden auf **v0.14.6** verschoben (siehe Sektion 5).

### Alternative bei Zeitdruck: 0 Specs

Wenn nur Easy-Wins gemacht werden sollen und TPT-Modules-Layer auf
v0.14.6/v0.15.0 wartet:
- **6 Tickets Lead-Direct**, ~45 min, plus 30 min QA-Smoke + 15 min
  Sec-Verify = **1.5h Gesamt** fuer v0.14.5.
- Vorteil: minimaler Overhead, schneller Release.
- Nachteil: TPT-Modules-Layer-Tech-Debt bleibt bestehen.

### Empfohlene Wahl: 1-Spec-Plan

TPT-Modules-Layer ist im Audit-Report v0.14.3 explizit als
"Tech-Debt-Ticket" markiert. Wenn v0.14.5 als "Tech-Debt-Cleanup-
Release" auftritt, sollte mindestens der einzige strukturelle Tech-Debt
mitkommen. 1 Spec ist sehr ueberschaubar.

---

## Sektion 5: Scope-Empfehlung v0.14.5

### MUSS in v0.14.5 (7 Tickets)

| # | Ticket | Begruendung |
|---|--------|-------------|
| 2 | TPT-Modules-Layer | Einziges strukturelles Tech-Debt-Ticket, sauber abgrenzbar (1 neue Klasse + Template-Cleanup) |
| 4 | TC-CSS-Cleanup toter Code | Trivial, CSS-Hygiene |
| 5 | TC-Compact-Icon-Modifier | Trivial, UX-Polish (QA-Caveat aus v0.14.4) |
| 6 | aria-controls auf TP-Filter-Buttons | Trivial, A11y-Pflicht (Audit-Finding v0.14.3) |
| 7 | OTA-Preview-Edge-Case Fix | Trivial, Security-Hygiene (LOW-4.1 aus v0.15.0-Audit) |
| 8 | Sliding-Window-Drift Doku | Trivial, Code-Doku (LOW-3.1) |
| 9 | Race-Condition Counter Doku | Trivial, Code-Doku (LOW-3.2) |

**Erwarteter Aufwand: ~2.5-3h Lead + 1 Spec (~1h) + 0.5h QA/Sec = ~4h
Gesamt.**

### RAUS aus v0.14.5 (2 Tickets) - auf v0.14.6 verschieben

| # | Ticket | Verschoben nach | Begruendung |
|---|--------|-----------------|-------------|
| 1 | tp/compact.php ContentCard-Migration + JS-Refactor | v0.14.6 | Mehrere h Aufwand, JS-Refactor-Risiko (Player-Spawn), Theme-Override-BC-Break am Compact-Markup, eigenes Discovery noetig |
| 3 | MMB-card/compact Lazy-Akkordeon-Migration | v0.14.6 | Mehrere h Aufwand, neuer Endpoint-Vertrag (Layout-Param), 2 neue Partials, Tab-Navigation-Interaktion mit Lazy-State zu klaeren |

**Empfehlung: Tickets #1 und #3 koennen in einem kombinierten v0.14.6
"Compact-Layouts Lazy-Loading"-Release gebuendelt werden** - beide
beruehren Compact-Layout-Patterns mit Performance-Charakter, beide
brauchen Discovery + Spec + 2 Audits, beide brauchen einen
abgestimmten Tag-Cycle. Falls v0.14.6 zu gross wird, kann #1 in v0.14.6
und #3 in v0.14.7 getrennt werden.

### Optional verschiebbar nach v0.15.1 (falls v0.15.0 vorher kommt)

- Tickets #7, #8, #9 koennten alternativ auch nach v0.15.1 verschoben
  werden, falls die Reihenfolge "v0.15.0 Backend-Admin-Dashboard zuerst,
  dann Tech-Debt" bevorzugt wird. Da das Admin-Dashboard aber laut
  Audit "GO" hat und v0.14.5 als reiner Tech-Debt-Release positioniert
  ist, ist v0.14.5 die natuerliche Heimat.

---

## Sektion 6: Konkrete Implementierungs-Snippets

### Ticket #4 - TC-CSS-Cleanup

**Datei:** `css/dhps-frontend.css` Z. 1978-1996

**Aktion:** Entferne die Bloecke `.dhps-tc__empty-icon`,
`.dhps-tc__empty-title`, `.dhps-tc__empty-text` (BEM-Children sind nach
v0.14.4-Migration nicht mehr im Markup). **Behalte** `.dhps-tc__empty`
(BC-Hook-Klasse, Wrapper-Styling). **Behalte** `.dhps-tc__empty--compact`
(siehe Ticket #5).

**Spezial-Hinweis:** Den Block `.dhps-tc__empty--compact .dhps-tc__empty-text`
(Z. 2004-2006) auch entfernen - der greift auf den toten Children-
Selektor. Stattdessen muss `.dhps-tc__empty--compact .dhps-empty-state__hint`
verwendet werden (siehe #5).

### Ticket #5 - TC-Compact-Icon-Groesse

**Datei:** `css/dhps-components.css` - neue Regel nach der
EmptyState-Standard-Definition.

Konzept (NICHT als Code committen, nur als Discovery-Output):

```
.dhps-empty-state--compact .dhps-empty-state__icon > svg {
    width: 32px;
    height: 32px;
}
.dhps-empty-state--compact .dhps-empty-state__title {
    font-size: 0.9375rem;
}
.dhps-empty-state--compact .dhps-empty-state__hint {
    font-size: 0.8125rem;
}
```

**Wichtig:** TC-Compact ruft EmptyState heute mit
`'class' => 'dhps-tc__empty dhps-tc__empty--compact'` auf (siehe
`tc/compact.php` Z. 32). Damit der Modifier `.dhps-empty-state--compact`
greift, MUSS der Class-String in tc/compact.php auf
`'class' => 'dhps-tc__empty dhps-tc__empty--compact dhps-empty-state--compact'`
erweitert werden (oder konsequent: Component-Prop fuer Modifier
einfuehren). Discovery-Empfehlung: **erstmal nur die Klasse anhaengen,
spaeter ggf. als Prop kanonisieren**.

### Ticket #6 - aria-controls auf TP-Filter-Buttons

**Dateien:** `tp/default.php` Z. 221-231, `tp/card.php` Z. 185-195.

Aktuell rendert das Filter-Bar-Markup:

```
<button class="dhps-filter-bar__btn ..." data-filter="all" aria-pressed="true" type="button">Alle</button>
<button class="dhps-filter-bar__btn ..." data-filter="0" aria-pressed="false" type="button">Erbschaftsteuer</button>
```

**Empfehlung:** `aria-controls` auf das `dhps-tp-grid`-Container-ID
zeigen (das `$list_id`-Variable in default.php existiert bereits als
Container-ID). Konzept:

```
aria-controls="<?php echo esc_attr( $list_id ); ?>"
```

an alle Filter-Buttons in beiden Dateien. **NICHT** auf einzelne Cards
zeigen (waere semantisch falsch, der Filter steuert die Liste als Ganzes).

### Ticket #7 - OTA-Preview-Edge-Case

**Datei:** `includes/class-dhps-health-collector.php` Z. 229-231.

Aktuell:

```
if ( strlen( $value ) <= 6 ) {
    return $value . '...';
}
```

**Empfehlung:** Bei `<= 6` Zeichen niemals den Wert preisgeben:

```
if ( strlen( $value ) <= 6 ) {
    return '***';
}
```

Doc-Block der Methode anpassen ("Bei sehr kurzen OTAs Maskierung statt
Preview").

### Ticket #8 + #9 - Rate-Limiter-Trade-offs dokumentieren

**Datei:** `includes/class-dhps-admin-rest.php` `check_rate_limit()`-Method-
Doc-Block erweitern.

Empfohlener Doc-Block-Inhalt (Konzept):

```
/**
 * ...
 *
 * Trade-off 1 (Sliding-Window-Drift, akzeptiert):
 *   set_transient erneuert die TTL bei jedem Increment. Konsequenz:
 *   Bei kontinuierlicher Last unter dem Limit rollt das Fenster. Bei
 *   Erreichen des Limits laeuft die TTL natuerlich aus (ca. 60s).
 *   Akzeptabel, weil das maximale Abuse-Fenster bei ~60s liegt.
 *
 * Trade-off 2 (Race-Condition bei Concurrent-Requests, akzeptiert):
 *   Zwei parallele Requests koennen denselben $count lesen und beide
 *   $count+1 schreiben - eine Erhoehung geht verloren. Bei
 *   RATE_LIMIT_PER_MINUTE=30 ist die Toleranz ~1-2 Extra-Requests/min.
 *   Analog DHPS_MMB_AJAX_Handler-Pattern, kein wpdb-Atomic-Lock noetig.
 */
```

### Ticket #2 - TPT-Modules-Layer (SPEC-Aufgabe, kein Snippet im Triage-Plan)

**Discovery-Hinweis fuer den Spec:**

Aktuell liest `tpt/default.php` Z. 76-77 direkt:

```
$ueberschrift = (string) get_option( 'dhps_tpt_ues', '' );
$teasertext   = (string) get_option( 'dhps_tpt_teasertext', '' );
```

Im Spec gewuenscht:

- Neue Klasse `DHPS_TPT_Module` (oder Erweiterung des TPT-Parsers, je
  nach Architektur-Entscheidung)
- Liest die Admin-Optionen serverseitig vor dem Template-Render
- Reicht sie in `$data['admin_texts']['ueberschrift']` und
  `$data['admin_texts']['teasertext']` durch
- Template liest `$data['admin_texts']['ueberschrift'] ?? ''` statt
  `get_option(...)`
- Wo wird die Modules-Layer-Logik eingehaengt? Vermutlich in der
  Content-Pipeline (`includes/class-dhps-content-pipeline.php` o.ae.),
  wo die anderen Services-Parser bereits laufen.

**Spec-Briefing-Punkte (NICHT Triage-Aufgabe):**
- Bestehende Service-Modules pruefen (gibt es schon einen Layer? MIO/MMB?)
- Pattern wiederverwenden oder neu einfuehren?
- BC: TPT-Template-Aufruf darf nicht brechen (alle Plugin-User mit
  TPT-Shortcode)
- Theme-Override: Wenn ein Theme tpt/default.php ueberschreibt, muss es
  auch noch `get_option(...)` als Fallback nutzen koennen (`$data['admin_texts'] ?? array()`)

---

## Sektion 7: Erwartete Gesamt-Aufwand v0.14.5

### Aufschluesselung

| Phase | Aufwand | Bemerkung |
|-------|---------|-----------|
| Lead-Direct (6 Easy-Wins) | ~45 min | CSS-Reload + Single-Endpoint-Smoke pro Ticket |
| Spec TPT-Modules-Layer (#2) | ~1h | 1 Spec, klar abgegrenzt |
| QA-Specialist | ~30 min | Knapper Regression-Lauf (TPT + Admin-Dashboard + TC-Compact + TP-Filter-A11y) |
| Sec-Specialist | ~20 min | OTA-Preview-Verify + Rate-Limiter-Doku-Verify |
| Release (Lead) | ~30 min | CHANGELOG + Memory + Tag + Smoke |
| **Gesamt** | **~3.5-4h** | **1 Spec + Lead-Direct + 2 parallele Audits** |

### Vergleich zu vorherigen v0.14.x-Releases

| Release | Gesamt-Aufwand | Specs |
|---------|----------------|-------|
| v0.14.0 | gross (Foundation + MMB-Pilot) | 9 Specs |
| v0.14.1 | mittel (MAES Stresstest) | 4-5 Specs |
| v0.14.2 | mittel (MIO/LXMIO) | 3 Specs |
| v0.14.3 | mittel (TP/TPT/LP) | 4 Specs |
| v0.14.4 | klein (TC Empty-State-Dedup) | 1 Spec |
| **v0.14.5 (vorgeschlagen)** | **klein (Tech-Debt-Cleanup)** | **1 Spec** |

v0.14.5 waere der **kleinste Release der v0.14.x-Reihe** - passend zum
"Cleanup-Charakter".

### Risiken

| Risiko | Wahrscheinlichkeit | Mitigation |
|--------|---------------------|------------|
| TPT-Modules-Layer braucht doch Architektur-Entscheidung (Modules-Layer-Pattern fehlt komplett im Plugin) | Mittel | Discovery-Punkt fuer den Spec: erst Pattern-Inventur, dann Implementierung |
| TC-Compact-Modifier-Klasse-Migration bricht Theme-Override | Niedrig | BC-Hook-Klassen `.dhps-tc__empty` + `.dhps-tc__empty--compact` bleiben - additive Klasse, kein Marking |
| aria-controls-Ziel-ID existiert nicht (typo in `$list_id`) | Niedrig | Lead-Smoke nach Edit (View-Source + DOM-Inspect) |
| OTA-Preview-Fix wird in einer anderen Methode gespiegelt | Niedrig | grep nach `substr( $value, 0, 6 )` zur Sicherheit |

### Empfohlene Reihenfolge in v0.14.5

1. **Lead-Direct erst** (Tickets #4-#9, ~45 min): minimal-invasiv, schnell
   verifizierbar, kein Spec-Wartezeit. Gibt fruehen Commit-Punkt.
2. **Spec TPT-Modules-Layer** (Ticket #2, ~1h): waehrenddessen kann Lead
   parallel den Spec-Brief schreiben oder den Release-Cycle vorbereiten.
3. **QA + Sec parallel** nach Spec-Abschluss.
4. **Release-Tag + Memory + CHANGELOG** durch Lead.

### Konflikt-Analyse (fuer Parallel-Specs)

Falls Lead spaeter doch mit 2-3 parallelen Specs arbeiten will:

| Spec | Files | Konflikt-frei zu |
|------|-------|------------------|
| TPT-Modules-Layer | `includes/class-dhps-tpt-*.php` (NEU), `tpt/default.php`, `tpt/card.php`, `tpt/compact.php` (Template-Reads-Cleanup), ggf. `includes/class-dhps-content-pipeline.php` | TC-CSS-Cleanup, TC-Compact-Modifier, aria-controls, OTA-Preview, Rate-Limiter-Doku |
| TC-CSS-Cleanup + Modifier | `css/dhps-frontend.css`, `css/dhps-components.css`, `tc/compact.php` (1 Klasse anhaengen) | Alle anderen |
| TP-aria-controls | `tp/default.php`, `tp/card.php` | Alle anderen |
| OTA-Preview + Rate-Limiter-Doku | `class-dhps-health-collector.php`, `class-dhps-admin-rest.php` | Alle anderen |

**Alles konflikt-frei.** Kein Schema-Vertrag notwendig (Lehre aus
v0.15.0-Schluesselnamen-Mismatch betraf REST-Localize-Bridge, die hier
nicht beruehrt wird).

### Schema-Vertrag fuer #2 (TPT-Modules-Layer)

**Empfehlung:** Spec-Briefing soll den Daten-Vertrag explizit als
Markdown-Abschnitt halten:

```
$data['admin_texts'] = array(
    'ueberschrift' => string,  // default ''
    'teasertext'   => string,  // default ''
);
```

Template-Konsum:

```
$admin_texts = $data['admin_texts'] ?? array();
$ueberschrift = (string) ( $admin_texts['ueberschrift'] ?? '' );
$teasertext   = (string) ( $admin_texts['teasertext'] ?? '' );
```

So bleibt der Template-Vertrag stabil, und ein Theme-Override mit altem
`get_option(...)` funktioniert weiter (BC).

---

## Anhang: Fundstellen-Index

- `public/views/services/tp/compact.php` Z. 1-77 (komplettes Template,
  6 Stellen mit BEM-Selektoren die das JS braucht)
- `public/js/dhps-tp.js` Z. 378-444 (`initCompactAccordion` mit Toggle +
  Player-Spawn)
- `public/views/services/mmb/card.php` Z. 1-165 (inline pre-rendered
  Card-Grid)
- `public/views/services/mmb/compact.php` Z. 1-154 (inline pre-rendered
  Compact-List)
- `public/views/services/mmb/default.php` Z. 116-190 (Lazy-Akkordeon-
  Vorbild)
- `public/views/services/mmb/partials/category-content.php` Z. 1-118
  (default-Layout-Partial - braucht Geschwister fuer card/compact)
- `includes/class-dhps-mmb-ajax-handler.php` Z. 320-369 (Endpoint +
  Render-Methode - braucht Layout-Param-Erweiterung)
- `public/views/services/tpt/default.php` Z. 76-77 (`get_option`-Reads
  im Template - Tech-Debt #2)
- `public/views/services/tc/compact.php` Z. 28-34 (EmptyState-Component-
  Aufruf mit Class-String fuer Modifier-Erweiterung)
- `css/dhps-frontend.css` Z. 1978-1996, 2004-2006 (toter Code Tickets
  #4 + Cross-Selektor mit Tickets #5)
- `css/dhps-components.css` Z. 182-192 (EmptyState-Component-Styles -
  Anker fuer #5-Modifier)
- `public/views/components/empty-state.php` Z. 35, 65, 68, 72 (Component-
  Markup - `.dhps-empty-state__icon|title|hint`-BEM)
- `includes/class-dhps-health-collector.php` Z. 220-233 (`get_ota_preview`
  fuer Ticket #7)
- `includes/class-dhps-admin-rest.php` `check_rate_limit`-Doc-Block (#8 + #9)
- `docs/project/26-SECURITY-AUDIT-v0150.md` Sektion 3 + 4 (Ursprung
  Tickets #7-#9)
- `docs/project/24-CHANGELOG-v0144.md` Tech-Debt-Tabelle (Ursprung
  Tickets #1-#6)
- `public/views/services/tp/default.php` Z. 220-231 + `tp/card.php`
  Z. 185-195 (Filter-Buttons fuer Ticket #6)
