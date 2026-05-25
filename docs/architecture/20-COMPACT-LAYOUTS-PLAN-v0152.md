# Compact-Layouts Lazy-Loading Plan v0.15.2 (Discovery)

## Stand: 2026-05-25
## Autor: Discovery-Specialist
## Scope: 2 verschobene Tech-Debt-Tickets aus v0.14.x
## Plan-Referenzen
- `docs/architecture/19-TECH-DEBT-TRIAGE-v0145.md` Sektion 2 (Risiko-Hotspots)
- `docs/project/21-CHANGELOG-v0143.md` (TP-Migration-Vorbild)
- `docs/project/12-CHANGELOG-v0140.md` (MMB-Lazy-Akkordeon-Vorbild)
- `.specialist-TP-1-handover.md` (TP-Migration-Pattern)

## Ziel

| Ticket | Source | Risiko (laut Triage) |
|--------|--------|----------------------|
| TP-1: `tp/compact.php` ContentCard-Migration + `initCompactAccordion` JS-Refactor | v0.14.3 Trust-Decision TD-2 | HOCH |
| MMB-1: `mmb/card.php` + `mmb/compact.php` Lazy-Akkordeon-Migration | v0.14.0 Pilot (nur default migriert) | MITTEL |

Beide Tickets wurden in v0.14.5 als "L (eigener Release)" klassifiziert
und auf einen kombinierten Release `v0.15.2 Compact-Layouts
Lazy-Loading` verschoben.

---

## Sektion 1: `initCompactAccordion`-Analyse (Code-Walk)

### Fundstelle

`public/js/dhps-tp.js` Z. 378-444 (Funktion `initCompactAccordion( container )`).

### Zerlegung in zwei Verantwortlichkeiten

#### Verantwortlichkeit A - Accordion-Toggle pro Rubrik (Z. 381-393)

```
triggers = container.querySelectorAll('.dhps-tp-compact__trigger');
triggers.forEach(trigger => {
    trigger.addEventListener('click', function () {
        expanded = this.getAttribute('aria-expanded') === 'true';
        contentId = this.getAttribute('aria-controls');
        content = document.getElementById(contentId);
        this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        if (content) content.setAttribute('aria-hidden', expanded ? 'true' : 'false');
    });
});
```

**Selektoren**: `.dhps-tp-compact__trigger`, `[aria-controls]`, `#{cat_id}`.
**Hooks**: Click-Handler je Trigger (kein Delegation, sondern direktes
`addEventListener`).
**Effekt**: Reines ARIA-Toggle, keine DOM-Mutation. Identisch zu MMB-default
`handleCategoryToggle` (vor Lazy-Erweiterung).

#### Verantwortlichkeit B - Player-Spawn beim Klick auf ein Video-Item (Z. 396-443)

```
container.addEventListener('click', function (e) {
    videoBtn = e.target.closest('.dhps-tp-compact__video-btn');
    if (!videoBtn) return;

    item = videoBtn.closest('.dhps-tp-compact__item');
    if (!item) return;

    videoSlug = item.getAttribute('data-video-slug');
    posterUrl = item.getAttribute('data-poster-url');
    vModus    = item.getAttribute('data-v-modus') || '0';

    if (!videoSlug) return;

    // Existierenden Player in dieser Liste entfernen
    list = item.closest('.dhps-tp-compact__list');
    if (list) {
        existing = list.querySelectorAll('.dhps-tp-compact__player');
        existing.forEach(el => el.remove());
    }

    // Neuen Player erstellen + INSERT als Geschwister-Element NACH dem <li>
    playerDiv = document.createElement('div');
    playerDiv.className = 'dhps-tp-compact__player';
    playerDiv.innerHTML =
        '<div class="dhps-tp-video__player">' +
            '<div class="dhps-tp-video__poster" data-video-slug="..."' +
                ' data-poster-url="..." data-v-modus="...">' +
                '<span class="dhps-news__loading">...</span>' +
            '</div>' +
        '</div>';

    item.after(playerDiv);

    posterEl = playerDiv.querySelector('.dhps-tp-video__poster');
    loadVideoIframe(playerDiv.querySelector('.dhps-tp-video__player'),
                    posterEl, videoSlug, posterUrl, vModus, config);
});
```

**Selektoren in Reihenfolge**:
1. Event-Delegation auf `container` (= `.dhps-service--tp`)
2. `e.target.closest('.dhps-tp-compact__video-btn')` - Click-Target
3. `videoBtn.closest('.dhps-tp-compact__item')` - liefert das `<li>` mit
   `data-video-slug|poster-url|v-modus`
4. `item.closest('.dhps-tp-compact__list')` - dient als Player-Cleanup-Scope
5. `list.querySelectorAll('.dhps-tp-compact__player')` - bestehende Player
   in dieser Rubrik
6. `item.after(playerDiv)` - **Geschwister-Insertion direkt unter `<li>`**

**Eigenheiten**:
- Player wird **nicht innerhalb** der Card/des Items gerendert, sondern
  als Geschwister-Element NACH dem `<li>` ins `<ul>` injiziert. Das ist
  rein visuell sinnvoll (Player-Streifen unter der Zeile) und nur durch
  CSS-Display erlaubt (eine `<div>` im `<ul>` ist HTML-invalide, wird
  aber von Browsern toleriert).
- Es werden Inline-Strings ueber `innerHTML` zusammengebaut, mit
  `escapeAttr()` als Sanitizer. Kein Inline-Style.
- `loadVideoIframe(playerContainer, posterEl, ...)` wird mit
  `.dhps-tp-video__player` als playerContainer und `.dhps-tp-video__poster`
  als posterEl aufgerufen. Beide Selektoren existieren NUR durch den
  Inline-Spawn (in compact.php sind sie nicht vorhanden).
- Die Branding-Farbe des Play-Icons im Item kommt aktuell via
  `<svg fill="currentColor">` im Button + CSS `.dhps-tp-compact__video-btn`
  (Default-Farbe). Kein Inline-Style mehr (war auch nie da).

### Verbindung zu Player-Cleanup-Semantik

- Nur EIN Player darf in einer Rubrik zur gleichen Zeit existieren.
- Beim Klick auf ein neues Video wird der bestehende Player in derselben
  Rubrik entfernt - dann der neue Player gespawnt.
- Zwischen Rubriken besteht keine Cleanup-Interaktion (jede Rubrik hat
  ihre eigene `<ul class="dhps-tp-compact__list">`).

### Konsequenzen fuer ContentCard-Migration

Die ContentCard-Komponente:
- rendert `<article class="dhps-content-card">` mit eigenem Markup
  (Body, Action-Footer, optional Media-Slot mit Lazy-Image).
- hat **keinen Inline-Play-Button-Modus** fuer Compact-Lists (das ist ein
  Sonderfall der Kompakt-Akkordeon-UX).
- hat **keinen Geschwister-Player-Slot** (das ist ein TP-spezifisches
  Anti-Pattern: Player liegt als `<div>` im `<ul>` neben dem `<li>`).
- erwartet, dass die Card als Root-Element selbst die `data-video-slug`
  trage (das passt) - aber die Player-Cleanup-Logik braucht einen
  Scope-Anker (heute: `.dhps-tp-compact__list`).

Die wichtigste Frage ist nicht "wie kann ContentCard das?", sondern:
**Soll der Player ueberhaupt noch als Geschwister-Element injected
werden?** Antwort siehe Sektion 2.

---

## Sektion 2: TP-Compact-Migration-Strategie

### Option A - Komplettes ContentCard-Refactor (Risiko: Hoch)

**Idee**: Jedes Video-Item wird zu einer kompakten ContentCard.
ContentCard wird um eine neue `variant='compact-row'` oder ein Service-
Hook erweitert. Player-Spawn wird auf neuen Slot innerhalb der Card
umgestellt.

**Pro**:
- Architektur-konsistent mit den anderen Services
- Eliminiert die `<div>` im `<ul>` HTML-Invaliditaet
- Komponenten-DRY

**Contra**:
- ContentCard ist heute eine **Box** (Body + Footer + optional Media).
  Eine "Compact-Row" ist eine **Zeile** (Single-Line mit Title +
  Datum + Play-Icon). Das ist visuell ein anderer Component-Typ.
- Erfordert neue ContentCard-Variante `compact-row` mit eigenem Markup
  (sonst Bloat: ContentCard rendert Body + Action ohne Inhalt).
- JS muss neu auf Card-Root-Selektor migriert werden:
  - Item-Anker: `e.target.closest('.dhps-content-card')`
  - Scope-Anker fuer Player-Cleanup: neuer Container-Selektor
    (`.dhps-tp-compact__list` haengt jetzt an einem `<ul>` ohne Cards
    bzw. an einer ContentList wenn ContentList genutzt wird)
- Theme-Overrides die `.dhps-tp-compact__item|video-btn|title|date`
  ueberschreiben, brechen ohne Kompatibilitaets-Wrapper.

**Aufwand**: 4-6h, JS-Refactor + ContentCard-Variant + neuer CSS-Block +
Theme-BC-Kompatibilitaets-Klassen.

### Option B - Hybrid (Markup minimal anpassen, data-attrs + Selektoren beibehalten) (Risiko: Niedrig)

**Idee**: `tp/compact.php` behaelt die heutige Akkordeon-Struktur
(`<section><h3><button>... <ul><li>...</li></ul></section>`), aber:
- Setzt Item-`<li>`-data-attrs unveraendert (`data-video-slug|poster-url|v-modus`).
- Ergaenzt **optional** auf jedem `<li>` zusaetzliche BEM-Hook-Klassen,
  damit Theme-CSS gleich bleibt.
- KEINE ContentCard-Aufrufe. KEINE Markup-Aenderung.
- JS-Refactor in `dhps-tp.js`:
  - Selektoren bleiben (`.dhps-tp-compact__trigger`,
    `.dhps-tp-compact__video-btn`, `.dhps-tp-compact__item`,
    `.dhps-tp-compact__list`, `.dhps-tp-compact__player`).
  - Nur Modernisierung: Player-Spawn nutzt `loadVideoIframe()` weiter,
    aber der Player wird **valide** innerhalb des `<li>` (nicht als
    Geschwister im `<ul>`) gerendert. Visuell mit `display: block`
    unter dem Button.
  - ODER: gar kein JS-Refactor noetig - der Player bleibt wie heute,
    nur ggf. `loadVideoIframe`-Aufruf wird gegen Drift abgesichert.

**Pro**:
- **Keine BC-Brueche** (Markup bleibt, Theme-Overrides funktionieren weiter).
- **Geringes Risiko** (testbar isoliert, kein Component-Vertrag noetig).
- Schnell umsetzbar.

**Contra**:
- **Architektur-Inkonsistenz bleibt** (tp/compact ist die einzige
  TP-Template-Variante die nicht via ContentCard rendert).
- Tech-Debt verschwindet nicht vollstaendig - es wird nur dokumentiert
  als "Compact-Row-Pattern bleibt service-eigen".

**Aufwand**: 1-2h, optionaler Mini-JS-Refactor (HTML-valide Player-Position).

### Option C - Neue ContentCard-Variante `compact-row` (Risiko: Mittel)

**Idee**: Eine neue Component `compact-row` wird in `public/views/components/`
eingefuehrt. Diese rendert eine kompakte Listenzeile + optional einen
Inline-Player-Slot. `tp/compact.php` ruft `compact-row` pro Video auf.
MAES und LP/TPT koennen das spaeter wiederverwenden.

**Pro**:
- Neue, **zweck-spezifische** Component statt ContentCard-Overload.
- Wiederverwendbar fuer kuenftige Compact-Layouts (MAES Compact gibt es,
  TPT Compact gibt es).
- Saubere Trennung "Box" (ContentCard) vs. "Row" (CompactRow).

**Contra**:
- **Neue Component = neuer Vertrag** = Discovery-Aufwand + Audit-Aufwand.
- ContentCard war eigentlich als universelle "card/video/document"-
  Component gedacht; eine `compact-row` wird parallel und konkurriert.
- BC-Hooks fuer Theme-Overrides muessen extra durch die Component
  gezogen werden.
- Risiko: andere Compact-Templates (MAES, TPT) muessten in Folge-
  Iterationen mit-migriert werden.

**Aufwand**: 3-4h (Component + 1 Migration + Audit-Surface), und der
"Component-Ueberbau" ist groesser als die Migration selbst.

### Empfehlung: Option B (Hybrid)

**Begruendung**:
1. **Risiko-Reduktion**: TP-Compact wird in Seitenleisten verwendet -
   Bruch ist visuell besonders gross.
2. **Discovery-Plan v0.14.3 hat tp/compact bewusst ausgenommen** mit
   genau der Begruendung "Player-Spawn-Risiko, JS-Refactor noetig".
   Das Risiko ist bis heute unveraendert.
3. **Markup-Eleganz hat hier weniger Nutzen** als bei den Card/Default-
   Layouts: TP-Compact ist eine schlanke Sidebar-Komponente, da macht
   ContentCard mit Body/Footer-Slot keinen Sinn.
4. **Architektur-Konsistenz lebt ohne tp/compact** - die anderen
   Compact-Templates (MAES, MIO, MMB) sind ebenfalls service-eigen.
5. **Geringer Aufwand**, klares Akzeptanz-Kriterium, kein neuer
   Component-Vertrag noetig.

**Konkretes Scope-Minimum fuer Spec-TP**:

1. `tp/compact.php` unveraendert lassen ODER:
   - Optional Aria-Controls saubern (heute schon ok).
   - Optional Inline-SVG-Play-Icon durch ein gemeinsames Icon-Helper
     ersetzen (nicht erforderlich).
   - **KEINE ContentCard-Aufrufe**.
2. `dhps-tp.js` `initCompactAccordion`:
   - Optional: Player-Insertion-Slot von `item.after(playerDiv)` auf
     **innerhalb** des `<li>` umstellen (`item.appendChild(playerDiv)`),
     damit `<ul><li>...<div></div></li></ul>` HTML-valide ist.
   - Optional: Cleanup-Scope auf `.dhps-tp-compact__section` heben,
     damit der Player auch dann sauber entfernt wird, wenn man einen
     `display: contents`-Twist in einer Theme-CSS einbaut.
   - **KEIN Selektor-Wechsel** (BC).
3. CSS-Pruefung in `dhps-components.css`:
   - Wenn Insertion in `<li>` umgestellt wird, ggf. `.dhps-tp-compact__item`
     auf `display: block` (default), `.dhps-tp-compact__player` als
     Block direkt darunter.
4. Tech-Debt-Doku in `docs/architecture/`: festhalten, dass
   "Compact-Row" eine bewusste service-eigene Pattern-Familie ist und
   keine ContentCard-Migration vorgesehen ist.

**Alternative bei Lead-Entscheidung "doch Architektur-Konsistenz"**:
Option C (neue `compact-row` Component). Erfordert ein zusaetzliches
Discovery + Spec. **NICHT empfohlen fuer v0.15.2**, kann aber als
v0.16+ Roadmap-Punkt aufgenommen werden.

---

## Sektion 3: MMB-Endpoint-Erweiterung

### Status Quo (verifiziert per Read)

`includes/class-dhps-mmb-ajax-handler.php` `render_category_html()`
Z. 352-369:

```
$template = trailingslashit(DEUBNER_HP_SERVICES_PATH)
    . 'public/views/services/mmb/partials/category-content.php';
```

**Hardcoded auf das default-Layout-Partial**, das ein
`<ul class="dhps-mmb-list">` mit `<li class="dhps-mmb-item">` (Akkordeon-
Pattern mit Item-Toggle) rendert.

In `mmb/card.php` Z. 113-155 wird ein **anderes Markup** erwartet:
- `<div class="dhps-mmb-card-grid">` mit `<div class="dhps-mmb-card-item">`
- Card-Item mit `__icon`, `__title` (h4), `__desc`, `__download`-Link
- **Kein** Item-Toggle / Detail-Akkordeon.

In `mmb/compact.php` Z. 107-144 wird ein **drittes Markup** erwartet:
- `<ul class="dhps-mmb-list dhps-mmb-list--compact">` mit
  `<li class="dhps-mmb-item dhps-mmb-item--compact">`
- Item mit `__row` (Title + PDF-Btn) + `__desc--compact`
- **Kein** Item-Toggle (Title direkt sichtbar, keine Expansion).

Wenn der Endpoint heute fuer card/compact aufgerufen wuerde, lieferte
er das default-Markup, das die card/compact-Klassen-Spezifitaeten nicht
trifft. Die UX waere fuer card kaputt (kein Grid), fuer compact
funktional aber visuell inkonsistent.

### Drei Optionen

#### Option A - Layout-Whitelist-Param am Endpoint

**Vertrag**:
- Endpoint nimmt zusaetzlich `&layout=default|card|compact` (default = `default`).
- Sanitize: `sanitize_key()` + Whitelist (`in_array(..., array('default', 'card', 'compact'), true)`).
- `render_category_html( $category, $service_tag, $layout )` waehlt das
  Partial:
  - `partials/category-content.php` (default, bestehend)
  - `partials/category-content-card.php` (NEU)
  - `partials/category-content-compact.php` (NEU)
- Frontend liest `data-layout` vom Akkordeon-Container und haengt den
  Param an die URL.

**Pro**:
- Saubere Erweiterung, BC-konform (default-Verhalten bleibt).
- Einheitlicher Sicherheits-Code (Nonce, Rate-Limit, Whitelist) zentral.
- Skaliert auf zukuenftige Layouts (`compact-row`, etc.).

**Contra**:
- Drei Partials zu pflegen (aber unvermeidbar, da Markup wirklich
  unterschiedlich ist).

#### Option B - Drei separate Endpoints

`dhps_mmb_category_load`, `dhps_mmb_category_load_card`,
`dhps_mmb_category_load_compact`.

**Pro**: keine.
**Contra**: 3x Sicherheits-Code (Nonce + Rate-Limit + Whitelist), neue
Action-Hooks, Asset-Localize muss alle URLs kennen, Cache-Keys
divergieren.

#### Option C - Einheitliches Partial mit Layout-Klassen

Ein Partial, das alle drei Layout-Markups in einem `switch`-Block oder
ueber CSS-Klassen-Variation rendert.

**Pro**: keine Partial-Vermehrung.
**Contra**:
- Markup zwischen card (Grid-Divs) und compact (Liste mit `__row`) ist
  **strukturell unterschiedlich**. Ein Partial mit 3 Modi waere
  Spaghetti.
- Verstoesst gegen das Single-Responsibility-Prinzip der Partials.

### Empfehlung: Option A

**Begruendung**:
1. Sauberer Vertrag: Layout-Whitelist im Endpoint = Erweiterung ohne
   neue Action-Hook-Permutationen.
2. Skalierbar fuer weitere Services (MIO hat Card+Compact, MAES hat
   Compact, etc.).
3. Drei Partials sind notwendig, weil die Layouts wirklich verschiedene
   HTML-Strukturen haben (Grid-Div vs. Akkordeon-Liste vs. Single-Line-
   Liste).
4. BC-konform: alte Frontend-Calls ohne `layout`-Param funktionieren
   weiter (default-Branch).

**Konkretes Scope-Minimum fuer Spec-MMB**:

1. `class-dhps-mmb-ajax-handler.php`:
   - Neue Konstante `ALLOWED_LAYOUTS = array('default', 'card', 'compact')`
   - `handle_request()`: liest `&layout`, sanitize_key, whitelist-check,
     default = `default`.
   - `render_category_html( $category, $service_tag, $layout )` waehlt
     Partial je `$layout`.
2. Neue Partials:
   - `partials/category-content-card.php` - rendert `<div class="dhps-mmb-card-grid">`
     mit `<div class="dhps-mmb-card-item">`-Eintraegen + PDF-Link.
   - `partials/category-content-compact.php` - rendert
     `<ul class="dhps-mmb-list dhps-mmb-list--compact">` mit
     `<li class="dhps-mmb-item dhps-mmb-item--compact">`-Eintraegen.
   - Beide Partials nutzen dasselbe `$category` + `$service_tag` Pattern,
     beide rufen am Ende `wp_kses_post()` ueber den Output ab (zentral
     im Handler, nicht im Partial).
3. `mmb/card.php` umbauen:
   - Container bekommt `data-dhps-mmb-categories` Attribute (analog
     mmb/default.php).
   - `data-layout="card"` als zusaetzliches Attribut.
   - Jedes `<div class="dhps-mmb-category">` bekommt `data-dhps-mmb-lazy-state="pending"`
     + Skeleton-Slot (mit Variante `card`).
   - `<noscript>`-Fallback mit voller Liste (analog default).
   - Optional Filter `dhps_mmb_card_prerender_first_category`.
4. `mmb/compact.php` analog umbauen mit `data-layout="compact"` und
   Skeleton-Variante `list` (kleiner Count).
5. `dhps-mmb.js`:
   - `loadCategorySheets()` liest `container.getAttribute('data-layout')`
     und haengt `&layout=...` an die URL (nur wenn != default).
   - `handleCategoryToggle()`: Lazy-Trigger funktioniert bereits ueber
     `data-dhps-mmb-lazy-state`. Keine Aenderung noetig wenn das Markup
     identische Akkordeon-Container-Patterns nutzt.
6. CSS:
   - `.dhps-mmb-category--lazy[data-dhps-mmb-lazy-state="pending"]` etc.
     greift bereits via `dhps-frontend.css`-State-Machine. Pruefen, dass
     der Skeleton-Slot in card und compact ebenso sichtbar wird.
   - Skeleton-Card-Variante `type='card'` existiert in
     `skeleton-loader.php` als Component-Prop (4 Varianten: card/list/
     video/accordion - siehe v0.14.0 Changelog).

---

## Sektion 4: MMB-Card Tab-Navigation-Kompatibilitaet

### Problem-Statement

`mmb/card.php` Z. 56-71 rendert eine Filter-Bar mit Buttons
`data-filter="all"` + `data-filter="{cat_id}"`. Die JS-Logik in
`dhps-mmb.js` Z. 386-452 zeigt/versteckt Kategorien per `display: none`
und oeffnet automatisch die erste / die gefilterte Kategorie.

Bei Lazy-Akkordeon-Migration ist **`data-filter="all"`** problematisch:
- Heute zeigt es **alle Kategorien gleichzeitig sichtbar** mit
  pre-rendered Content (oder die erste mit `aria-expanded="true"`).
- Bei Lazy waere "Alle" = "alle Skeletons sichtbar" - bei 5+ Kategorien
  ist das visuell schlecht (5 Skeleton-Blocks gleichzeitig + 5 parallele
  AJAX-Loads, wenn der Filter automatisch oeffnen wuerde).

### Loesungs-Optionen

#### Variante 1 - "Alle" oeffnet nur die erste Kategorie (default-Konsistenz)

`data-filter="all"`-Klick zeigt **alle Kategorie-Header**, oeffnet aber
**nur die erste** automatisch. Andere bleiben Skeleton-State `pending`.
- Konsistent mit mmb/default.php-Verhalten (dort ist initial nichts
  geoeffnet, ausser optional ueber Filter pre_render).
- User klickt einzelne Kategorien an, Lazy-Load greift normal.

**Empfohlen.** Sauberster UX-Verhalten, keine 5-parallel-Loads.

#### Variante 2 - "Alle" laedt alle Kategorien parallel

`data-filter="all"`-Klick triggert sequenziell oder parallel
`loadCategorySheets()` fuer alle pending-Kategorien.
- Pro: identisches Verhalten zur alten Card-View ("alles sehen").
- Contra: 5+ parallele AJAX-Calls, Rate-Limit-Risiko (60/min reicht
  zwar locker, aber UX-Spike), Loading-State-Sequenz unklar.

**NICHT empfohlen.**

#### Variante 3 - "Alle" ist initial-Selektor + erste Kategorie pre-rendered

`mmb/card.php` rendert die **erste Kategorie pre-rendered** (analog
`dhps_mmb_card_prerender_first_category` Filter, default true) und die
restlichen als Skeletons. Filter-Klick `data-filter="all"` zeigt alle
Header.

**Akzeptabel** als Default-Variante. Stellt sicher dass der Above-the-
Fold-Eindruck nicht leer ist (Card-View hat hoeheres "Wow-Faktor"-
Anspruch als Compact).

### Empfehlung

**Variante 1 + Variante 3 kombiniert**:

- `mmb/card.php` rendert die **erste Kategorie pre-rendered** (Filter
  `dhps_mmb_card_prerender_first_category`, **default `true`**, anders
  als default-Layout).
- "Alle"-Filter-Klick zeigt alle Kategorie-Header, expandiert nur die
  erste. Andere bleiben Skeleton-pending bis zum manuellen Klick.
- Einzelfilter-Klick (`data-filter="cat_id"`) versteckt andere
  Kategorien, expandiert die ausgewaehlte Kategorie und triggert
  Lazy-Load wenn `data-dhps-mmb-lazy-state="pending"`.

Bei `mmb/compact.php`:
- Default-Filter `dhps_mmb_compact_prerender_first_category` = `false`
  (Compact ist Sidebar-Layout, da nicht noetig).
- Verhalten ansonsten identisch.

**JS-Anpassungen in `initFilterBar()` (`dhps-mmb.js` Z. 386-452)**:

Aktuell oeffnet der Einzelfilter-Klick die ausgewaehlte Kategorie via
`aria-expanded="true"` + `aria-hidden="false"`. Das muss erweitert
werden:
- Nach dem `aria-expanded`-Set, pruefen ob
  `data-dhps-mmb-lazy-state="pending"` ist - falls ja,
  `loadCategorySheets()` aufrufen.

Effekt: Filter-Klick triggert Lazy-Load wenn noch nicht geladen.

**Tab-Navigation-Note**: In der heutigen `card.php` heisst es im
Kommentar "Tab-Navigation fuer Rubriken" - es ist aber technisch eine
Filter-Bar (Buttons mit `aria-pressed`, keine `role="tab"/"tabpanel"`).
Diese Inkonsistenz vom Discovery-Bezug nicht aufloesen - die Filter-Bar-
Logik bleibt erhalten, nur das Lazy-Verhalten wird ergaenzt.

---

## Sektion 5: BC-Strategie

### tp/compact.php Theme-Overrides

Pfad: `{theme}/dhps/services/tp/compact.php`.

**Bei Empfehlung Option B (Hybrid)**:
- **Kein BC-Bruch**. Markup-Struktur bleibt unveraendert.
- Falls Player-Insertion auf `item.appendChild` umgestellt wird, sind
  Theme-CSS-Regeln auf `.dhps-tp-compact__list > .dhps-tp-compact__player`
  betroffen (Player ist nicht mehr direkter `<ul>`-Child sondern
  `<li>`-Child). Mitigation: Selektor-Doku im Changelog.

**Bei Option A oder C**:
- BC-Bruch wahrscheinlich. Theme-Overrides mit `.dhps-tp-compact__item|video-btn|title|date|date`-Selektoren
  wuerden brechen (oder muessten mit Wrapper-Klassen abgesichert werden).
- Mitigation: BC-Hook-Klassen `dhps-tp-compact__item` zusaetzlich an die
  neue Component-Wrapper haengen (analog v0.14.3 TPT-Migration mit
  `dhps-tp-card dhps-tpt-card`).

### mmb/card.php Theme-Overrides

Pfad: `{theme}/dhps/services/mmb/card.php`.

**Bei Empfehlung Option A**:
- `dhps-mmb-card-grid` + `dhps-mmb-card-item` BEM-Selektoren bleiben
  erhalten (Markup wird ins Partial verschoben, aber identisch).
- Die Wrapper-Struktur (`<div class="dhps-mmb-category">`) bekommt
  ZUSAETZLICH `data-dhps-mmb-lazy-state="pending"` + Skeleton-Slot -
  das ist **additiv**, kein Bruch.
- Theme-Overrides der `.dhps-mmb-category__header|trigger|content` und
  Filter-Bar bleiben funktional.
- Theme-Overrides der `<div class="dhps-mmb-card-grid">` und
  `<div class="dhps-mmb-card-item">` muessen sich darauf einstellen,
  dass das Markup jetzt **per AJAX nachgeladen** wird (also `MutationObserver`
  oder lazy-Listener). Realistische Theme-Praxis: Theme-CSS wird auf
  die Klassen-Hooks zielen, die bleiben.

**Mitigation**:
- Filter `dhps_mmb_card_prerender_first_category` default `true` (erste
  Kategorie sofort sichtbar - Themes sehen sofort den Effekt).
- CHANGELOG-Eintrag: Theme-Overrides muessen ggf. nachgezogen werden,
  wenn sie pre-rendered DOM erwarten (analog v0.14.0 fuer mmb/default).

### mmb/compact.php Theme-Overrides

Pfad: `{theme}/dhps/services/mmb/compact.php`.

**Analog mmb/card.php**:
- Wrapper-Struktur (`<div class="dhps-mmb-category dhps-mmb-category--compact">`)
  bekommt `data-dhps-mmb-lazy-state="pending"`.
- Markup-Innenleben `<ul class="dhps-mmb-list dhps-mmb-list--compact">` wandert
  ins neue Partial.
- Skeleton-Slot statt pre-render.
- Theme-Overrides der Item-Klassen bleiben.

**Mitigation**: Default-Filter `dhps_mmb_compact_prerender_first_category`
= `false` (Compact ist Sidebar - kein Pre-Render noetig). Themes mit
Compact-Sidebar bekommen eine reduzierte initial-Bytes-Variante (das
ist der erwuenschte Effekt).

### CSS-Selektoren

Keine Aenderung an BEM-Klassen.

Ergaenzungen die brechen koennten:
- `[data-dhps-mmb-lazy-state="pending"] .dhps-mmb-card-grid { display: none; }`
  - **NEU**, nicht im Theme erwartet. Mitigation: State-Machine in
    `dhps-frontend.css`, vom Plugin definiert, BEM-Spezifitaet niedrig.
- `[data-dhps-mmb-lazy-state="loading"] .dhps-skeleton { display: block; }`
  - **NEU**, analog.

### Cache-Invalidations-Hinweis

Die alte `dhps_mmb_category_load`-Action liefert weiter das default-Partial,
wenn kein `&layout`-Param gesetzt ist. Bei Frontend-Caches (Page-Cache,
Edge-Cache) ist die alte URL also weiter aufrufbar. **Kein Cache-Reset
noetig.**

---

## Sektion 6: Spec-Aufteilung-Empfehlung

### File-Konflikte-Matrix

| Spec | tp/compact.php | dhps-tp.js | mmb/card.php | mmb/compact.php | dhps-mmb.js | class-dhps-mmb-ajax-handler.php | partials/category-content-card.php | partials/category-content-compact.php |
|------|----------------|------------|--------------|-----------------|-------------|----------------------------------|------------------------------------|---------------------------------------|
| TP-Compact (Option B) | (opt) | X | - | - | - | - | - | - |
| MMB-Card | - | - | X | - | X | X | X (NEU) | - |
| MMB-Compact | - | - | - | X | X | X | - | X (NEU) |

**File-Konflikte**:
- `dhps-mmb.js` und `class-dhps-mmb-ajax-handler.php` werden sowohl von
  MMB-Card als auch MMB-Compact angefasst.
- `partials/category-content-card.php` und `category-content-compact.php`
  sind neu und konfliktfrei zwischen einander.

### Drei Spec-Optionen

#### Option SA - 2 parallele Specs (TP + MMB-kombiniert)

| Spec | Files |
|------|-------|
| TP-Compact | `dhps-tp.js` |
| MMB-Card-Compact | `mmb/card.php`, `mmb/compact.php`, `dhps-mmb.js`, `class-dhps-mmb-ajax-handler.php`, beide neue Partials |

**Pro**: Klare Trennung TP vs MMB. Zwei parallele Specialists.
**Contra**: MMB-Spec wird gross (5 Files + 2 neue Partials = 7 Touch-
Points fuer einen Specialist).

#### Option SB - 3 parallele Specs (TP + MMB-Card + MMB-Compact)

| Spec | Files |
|------|-------|
| TP-Compact | `dhps-tp.js` |
| MMB-Card | `mmb/card.php`, neue Partial card |
| MMB-Compact | `mmb/compact.php`, neue Partial compact |
| ??? Wer macht `dhps-mmb.js` + Handler-Erweiterung? |

**Problem**: Shared Files (`dhps-mmb.js` + `class-dhps-mmb-ajax-handler.php`)
wuerden Konflikt erzeugen wenn beide MMB-Specs sie anfassen. Daher
braucht es entweder:
- Lead macht Shared Files (vor oder nach den Template-Specs).
- ODER MMB-Card und MMB-Compact werden seriell statt parallel.

**Contra**: Komplizierte Orchestrierung, Lead-Coordination-Overhead.

#### Option SC - 1 Spec MMB-Foundation + 2 parallele Specs Templates

| Phase | Spec | Files |
|-------|------|-------|
| P1 | MMB-Foundation | `class-dhps-mmb-ajax-handler.php` (Layout-Whitelist), `dhps-mmb.js` (Lazy-Trigger im Filter), 2 NEUE Partials |
| P2 parallel | TP-Compact | `dhps-tp.js` |
| P2 parallel | MMB-Card | `mmb/card.php` |
| P2 parallel | MMB-Compact | `mmb/compact.php` |

**Pro**: MMB-Foundation legt den Vertrag (Endpoint, Partials, JS-Hook),
danach koennen Templates **parallel** und **konfliktfrei** umgesetzt werden.

**Contra**: 4 Specs insgesamt (1 + 3), aber jeder klein.

### Empfehlung: **Option SA (2 parallele Specs)**

**Begruendung**:
1. **Kein Foundation-First-Bottleneck**: TP-Compact ist unabhaengig von
   MMB.
2. **MMB-Kombi-Spec ist machbar**: 7 Touch-Points sind ein normaler
   Spec-Umfang (v0.14.3 hatte vergleichbare Specs mit 3-5 Files).
3. **Klare Verantwortlichkeit**: TP-Specialist kennt TP-JS-Internals;
   MMB-Specialist kennt MMB-Endpoint + JS + Templates als kohaerentes
   Sub-System.
4. **Minimaler Orchestrierungs-Aufwand**: zwei parallele Spec-Briefings,
   Lead-Composition fuer CSS-Hooks und Smoke-Tests danach.

**Spec-Briefings (Skizze)**:

**Spec TP-Compact (klein)**:
- Scope: tp/compact unveraendert lassen ODER minimaler HTML-Validity-
  Fix in `dhps-tp.js` (`item.after` -> `item.appendChild`).
- Akzeptanz: Player spawnt korrekt, Cleanup funktioniert, kein Markup-
  Bruch in Theme-Overrides.

**Spec MMB-Card-Compact (mittel)**:
- Scope:
  - `class-dhps-mmb-ajax-handler.php` Layout-Whitelist + Partial-Switch
  - 2 neue Partials (card + compact)
  - `mmb/card.php` Lazy-Akkordeon-Umbau (data-dhps-mmb-lazy-state +
    Skeleton-Slot + noscript-Fallback + data-layout)
  - `mmb/compact.php` analog
  - `dhps-mmb.js` Layout-Param-Versand + Filter-Bar-Lazy-Trigger
  - 2 neue Filter-Hooks (`dhps_mmb_card_prerender_first_category`
    default true, `dhps_mmb_compact_prerender_first_category` default
    false)
- Akzeptanz:
  - `[mmb layout="card"]` und `[mmb layout="compact"]` rendern initial
    nur Header + Skeleton (ausser pre-render-Filter true)
  - Filter-Klick triggert Lazy-Load der ausgewaehlten Kategorie
  - noscript-Fallback liefert vollstaendige Markup-Variante des
    jeweiligen Layouts
  - MIL erbt via Template-Fallback (mil -> mmb)
  - Bytes-Magnitude: < 30 KB initial bei card, < 20 KB initial bei
    compact (qualitatives Ziel, vgl. mmb/default v0.14.0: ~16 KB)

**Spec-Konflikt-Frei-Garantie**:
- TP-Spec aendert nur `dhps-tp.js` -> kein MMB-Konflikt.
- MMB-Spec aendert alle MMB-Files in einer Hand -> kein Self-Konflikt.

### Alternative Lead-Entscheidung

Falls MMB-Combo-Spec als zu gross empfunden wird: **Option SC**
(Foundation-first), 4 Specs in 2 Wellen.

---

## Sektion 7: Risiken + Mitigation

### Risiko-Matrix

| # | Spec | Risiko | Wahrscheinlichkeit | Schwere | Mitigation |
|---|------|--------|--------------------|---------|-----------|
| R1 | TP-Compact | Player-Spawn bricht beim Umstellen `item.after` -> `item.appendChild` (CSS-Layout-Aenderung) | Niedrig | Mittel | Smoke gegen Demo-Page mit `[tp layout="compact"]`, visueller Vergleich vor/nach |
| R2 | TP-Compact | initCompactAccordion-Selector-Wechsel bricht Filter-Interaktion | Niedrig | Hoch | Bei Empfehlung Option B: kein Selector-Wechsel, Risiko minimal |
| R3 | MMB-Foundation | Layout-Whitelist nicht strikt -> Path-Traversal via `?layout=../../etc` | Niedrig | Hoch | `in_array($layout, ALLOWED_LAYOUTS, true)` strikt, default = `default` bei Mismatch |
| R4 | MMB-Foundation | Alte Frontend-Caches senden Request ohne `layout`-Param -> bekommen default-Partial -> bei card/compact-Container visuell falsch | Mittel | Niedrig | BC: Endpoint behandelt missing layout als `default`. Frontend-Cache laeuft nach 1h aus. Optional in CHANGELOG: "Cache invalidieren empfohlen." |
| R5 | MMB-Card | Filter-Bar "Alle" laedt nicht alle Kategorien parallel - User sieht leere Skeletons | Hoch | Niedrig | Variante 3 (erste Kategorie pre-rendered) + Hint im Skeleton "Klicken zum Laden" |
| R6 | MMB-Card | Theme-Override pre-rendered Card-Grid wird durch Lazy-Akkordeon ueberschrieben | Mittel | Mittel | `dhps_mmb_card_prerender_first_category` default true (Theme sieht erste Kategorie sofort). Doku in CHANGELOG. |
| R7 | MMB-Compact | Compact in Sidebar bekommt Skeleton-Hoehe > Pre-Render-Hoehe -> Layout-Jump | Niedrig | Niedrig | Skeleton-Count = `min(cat_count, 3)` statt 5 (kleinere visuelle Vorab-Hoehe) |
| R8 | MMB-* | noscript-Fallback bricht weil Partial-Pfad nicht resolved | Niedrig | Mittel | Partial-Existenz mit `file_exists()` pruefen (analog default.php Z. 171, 210) |
| R9 | MMB-Foundation | Rate-Limit greift bei "Alle Kategorien laden" wenn man variante 2 implementiert | Mittel | Niedrig | Variante 1 (nur erste Kategorie automatisch), Risiko entfaellt |
| R10 | MMB-* | `wp_kses_post` filtert SVG-Icons im Card-Markup raus | Mittel | Mittel | `wp_kses_post` erlaubt SVG nicht standardmaessig. Pruefen: Default-Partial nutzt schon kein SVG (PDF-Icon ist SVG aber funktioniert) - oder Filter `wp_kses_allowed_html` ergaenzen. **Acceptance-Test noetig**. |
| R11 | MMB-Card | Pre-Render-Filter `dhps_mmb_card_prerender_first_category` true bricht das v0.14.0-Bytes-Versprechen | Niedrig | Niedrig | Card hat hoehere Bytes-Toleranz als default. Akzeptiert in Sektion 4 Empfehlung. |
| R12 | TP-Compact | Theme-Overrides mit angepasstem JS (etwa eigener Player-Spawn-Listener) brechen wenn `dhps-tp.js` veraendert wird | Niedrig | Mittel | Diff-Doku in CHANGELOG. JS-Spec dokumentiert oeffentlich. |

### Allgemeine Risiko-Steuerung

1. **Beide Specs koennen einzeln released werden** wenn der jeweils
   andere ueberraschend nicht klappt. Tag-Cycle nicht gekoppelt.
2. **Live-Smoke nach Spec** auf Demo-Page mit allen 4 Layout-Kombinationen
   (TP-Compact, MMB-Default, MMB-Card, MMB-Compact) sowie MIL-Card,
   MIL-Compact (via Fallback).
3. **noscript-Test** mit deaktiviertem JavaScript - alle drei MMB-Layouts
   muessen vollstaendige Fact-Sheet-Listen anzeigen.
4. **A11y-Check** Lighthouse mit JS aktiv und inaktiv.
5. **Cache-Test**: Demo-Seite mit Page-Cache + Test, dass die Endpoint-
   Calls funktionieren.

---

## Sektion 8: Performance-Prognose (qualitativ)

### Baseline (qualitative Annahmen, da Bash-Messung blockiert)

| Shortcode | Aktuell (geschaetzt) | Anteil pre-rendered Markup |
|-----------|----------------------|----------------------------|
| `[tp layout="compact"]` | ~25-40 KB | 100% (alle Kategorien + alle Videos pre-rendered) |
| `[mmb layout="card"]` | ~80-150 KB | 100% (alle Kategorien + alle Fact-Sheets mit `<div class="dhps-mmb-card-item">` Block-Items) |
| `[mmb layout="compact"]` | ~60-120 KB | 100% (alle Kategorien + alle Fact-Sheets mit Description-Texten) |
| `[mmb layout="default"]` (Baseline) | ~16 KB initial (laut v0.14.0) | nur Header + Skeleton |

(Die Zahlen sind grobe Schaetzungen basierend auf v0.13.x-Daten aus dem
v0.14.0-Changelog und der Tatsache, dass MMB-Default vor Lazy 307 KB
hatte.)

### Erwartete Magnitude nach Migration

| Shortcode | Strategie | Erwartete Initial-Bytes | Erwarteter Delta |
|-----------|-----------|-------------------------|------------------|
| `[tp layout="compact"]` | Empfehlung Option B (Markup unveraendert) | ~25-40 KB | **0** bis **+5%** (nur durch ggf. zusaetzliche Klassen) |
| `[mmb layout="card"]` | Lazy + erste pre-rendered | ~25-40 KB | **-60% bis -80%** ggue. Baseline |
| `[mmb layout="compact"]` | Lazy + keine pre-rendered | ~15-25 KB | **-70% bis -85%** ggue. Baseline |
| `[mmb layout="default"]` | unveraendert | ~16 KB | 0% |
| `[mil layout="card"]` (via Fallback) | erbt MMB-Card-Aenderungen | analog | analog |
| `[mil layout="compact"]` (via Fallback) | erbt MMB-Compact-Aenderungen | analog | analog |

### Trade-off-Anmerkungen

**TP-Compact**:
- Option B = **kein Performance-Gewinn**, dafuer **kein Risiko**.
- Wenn Performance-Gewinn benoetigt: kuenftige Iteration mit Compact-
  Akkordeon-Lazy (analog MMB), das wuerde aber den `initCompactAccordion`-
  Refactor implizieren (= Option A/C). **NICHT** im aktuellen Scope.

**MMB-Card und MMB-Compact**:
- Erwartete **-60% bis -85% Bytes** im Initial-Render bei Sites mit
  > 5 Kategorien und > 50 Fact-Sheets total.
- Trade-off: Bei Filter-Wechsel ist 1 AJAX-Round-Trip noetig (vorher
  nullte Latenz weil alles inline war). Visuell mit Skeleton kompensiert.
- SEO bleibt erhalten via `<noscript>`-Fallback (analog mmb/default).
- Edge-Effekt: Wenn ein Theme die ContentList-Pagination-Komponente
  zusammen mit MMB-Card kombiniert (gibt es heute noch nicht), kann das
  Lazy-Akkordeon dem Theme die Sortierung erschweren - aber das ist
  ein theoretisches Szenario.

### Empfohlener Performance-Smoke

Nach beiden Specs gegen Demo-Page:

```
docker exec wp-deubner-hp-services-wordpress-1 wp eval '
    echo "tp_compact: ".strlen(do_shortcode("[tp layout=\"compact\"]")).PHP_EOL;
    echo "mmb_card: ".strlen(do_shortcode("[mmb layout=\"card\"]")).PHP_EOL;
    echo "mmb_compact: ".strlen(do_shortcode("[mmb layout=\"compact\"]")).PHP_EOL;
    echo "mmb_default: ".strlen(do_shortcode("[mmb]")).PHP_EOL;
    echo "mil_card: ".strlen(do_shortcode("[mil layout=\"card\"]")).PHP_EOL;
    echo "mil_compact: ".strlen(do_shortcode("[mil layout=\"compact\"]")).PHP_EOL;
'
```

In CHANGELOG dokumentieren wie in v0.14.0 (Browser-Initial vs Total-
Source-Tabelle).

---

## Anhang: Fundstellen-Index

- `public/views/services/tp/compact.php` Z. 1-77 (Akkordeon-Markup
  unveraendert seit v0.9.1, mit `data-video-slug|poster-url|v-modus`)
- `public/js/dhps-tp.js` Z. 22-37 (init-Selector `.dhps-service--tp, .dhps-service--lp`)
- `public/js/dhps-tp.js` Z. 378-444 (`initCompactAccordion` - Trigger-
  Toggle + Player-Spawn als Geschwister-Insertion)
- `public/views/services/mmb/default.php` Z. 116-190 (Lazy-Akkordeon-
  Vorbild mit Skeleton + noscript)
- `public/views/services/mmb/default.php` Z. 137-187 (Lazy-State-Setup
  `data-dhps-mmb-lazy-state` + pre-render-Filter)
- `public/views/services/mmb/card.php` Z. 75-162 (pre-rendered Card-
  Grid, ohne Lazy-State)
- `public/views/services/mmb/compact.php` Z. 71-151 (pre-rendered
  Compact-Liste, ohne Lazy-State)
- `public/views/services/mmb/partials/category-content.php` Z. 42-117
  (Default-Partial mit `<ul class="dhps-mmb-list">` und Item-Toggle)
- `public/js/dhps-mmb.js` Z. 18-52 (init + `initFilterBar` + `initSearch`)
- `public/js/dhps-mmb.js` Z. 61-99 (`initCategoryAccordion` +
  `handleCategoryToggle` mit Lazy-Trigger)
- `public/js/dhps-mmb.js` Z. 111-162 (`loadCategorySheets` - AJAX-URL-
  Build + State-Machine, hier `&layout`-Param ergaenzen)
- `public/js/dhps-mmb.js` Z. 386-452 (`initFilterBar` - hier Lazy-Trigger
  beim Einzelfilter ergaenzen)
- `includes/class-dhps-mmb-ajax-handler.php` Z. 46 (ALLOWED_SERVICES -
  Hier ALLOWED_LAYOUTS analog ergaenzen)
- `includes/class-dhps-mmb-ajax-handler.php` Z. 126-294 (handle_request
  - hier `&layout`-Param sanitize + whitelist)
- `includes/class-dhps-mmb-ajax-handler.php` Z. 352-369
  (render_category_html - hier $layout-Param + Partial-Switch)
- `public/views/components/skeleton-loader.php` (Varianten card/list/
  video/accordion - 4 Modi laut v0.14.0)
- `docs/architecture/19-TECH-DEBT-TRIAGE-v0145.md` Sektion 2 (Risiko-
  Hotspots, Quelle dieses Plans)
- `docs/project/12-CHANGELOG-v0140.md` Sektion 6 + 7 (MMB-AJAX-Endpoint-
  Vertrag + Lazy-Akkordeon-Pilot)
- `docs/project/21-CHANGELOG-v0143.md` Tech-Debt-Tickets (Ursprung
  Ticket TP-Compact)
- `.specialist-TP-1-handover.md` (Migrations-Pattern TP-default + card)
