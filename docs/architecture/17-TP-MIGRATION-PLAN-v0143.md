# TP + TPT + LP - Migration Plan v0.14.3

> Discovery-Specialist | Stand: 2026-05-23 | Foundation: Component-System (v0.14.0) +
> ContentCard `data_attrs` (v0.14.1) + Service-Branding-Token-Switch (v0.14.2)
> Research-only - keine Code-Aenderungen.

Ziel der dritten Migrations-Stufe: TP (TaxPlain Videos), TPT (TaxPlain Teaser)
und LP (LexPlain) auf das ContentList/ContentCard-System bringen, ohne die
695 LOC `dhps-tp.js` zu refaktorieren. Es gilt das **Hybrid-Pattern aus v0.14.1
(MAES)**: ContentCard rendert das Markup, behaelt aber Zusatz-Klassen
(`dhps-tp-card`) und `data_attrs` (`video-slug`, `poster-url`, `v-modus`), damit
das vorhandene TP-JS unveraendert weiterlaeuft.

Vorbild: [`docs/project/15-CHANGELOG-v0141.md`](../project/15-CHANGELOG-v0141.md)
+ [`.specialist-M1-handover.md`](../../.specialist-M1-handover.md) (MAES-Videos
Migration).

---

## 1. Status-Quo

### 1.1 Service-Familie TP/TPT/LP

ASCII-Diagramm (Vererbungs- und Asset-Beziehungen):

```
                +-----------------------+
                |   DHPS_TP_Parser      |
                |   parse_video_block() |  <-- protected, von Subklassen genutzt
                +-----------+-----------+
                            |
              +-------------+-------------+
              |                           |
   +----------v---------+      +----------v---------+
   | DHPS_TPT_Parser    |      | DHPS_LP_Parser     |
   | (Single-Video,     |      | (alle Videos,      |
   |  teasermodus=1)    |      |  service=lexplain) |
   +--------------------+      +--------------------+

  Templates:
    public/views/services/tp/{default,card,compact}.php   <-- echt eigene
    public/views/services/tpt/{default,card,compact}.php  <-- echt eigene
    public/views/services/lp/                              <-- NICHT vorhanden
                                                              Fallback via Filter
                                                              `dhps_template_fallbacks`
                                                              -> nutzt tp/*.php

  JS-Pipeline:
    public/js/dhps-tp.js  (695 LOC, single source of truth fuer alle drei)
       - initLazyVideoLoading  (click + keyboard auf [data-video-slug])
       - loadVideoIframe       (AJAX dhps_tp_video_src + Modal/Inline)
       - initCategoryFilter    (.dhps-filter-bar__btn / .dhps-tp-filter__btn)
       - initCompactAccordion  (.dhps-tp-compact__trigger + Player-Spawn)
       - initLazyLoadMore      (.dhps-tp-card--lazy-hidden / .dhps-tp-load-more)

  CSS-Branding:
    .dhps-service--tp / --tpt  -> Steuern-Gruen (CSS-Vars in dhps-frontend.css)
    .dhps-service--lp          -> KEIN expliziter Token-Switch im Frontend-CSS
                                  (heute reicht erstes-Video-`service`-Feld zur
                                  AJAX-Proxy-Auswahl. Visuelles Branding wird
                                  ueber Recht-CSS auf `.dhps-service--lp`-spezifische
                                  Selektoren gesetzt - siehe Sektion 5.)

  AJAX-Service-Tag (mandantenvideo.de):
    TP   -> service=taxplain (aus iframe-src extrahiert, Default)
    TPT  -> service=taxplain (geteilt, Wrapper haengt data-service="taxplain")
    LP   -> service=lexplain (LP_Parser overridet video['service'])
```

### 1.2 Template-Detailstand (Eigene Markup-Inseln)

| File | LoC | Eigenheiten |
|---|---|---|
| `tp/default.php` | ~190 | Featured-Video + Filter-Bar + Grid (`.dhps-tp-card` x60). Inline-`style="color: var(--dhps-color-steuern)"` auf `__play-btn`. Eigene `<svg>`-Play-Buttons in Featured + Card. `h3` (Section) / `h4` (Featured-Title) / `h4` (Card-Title). |
| `tp/card.php` | ~155 | Flacht Featured + Kategorien in eine Liste. Box-Shadow-Wrapper `dhps-card`. Sonst wie default. `mb_strimwidth(teaser, 0, 100)`. |
| `tp/compact.php` | ~78 | Accordion-Sektionen pro Kategorie. `<ul class="dhps-tp-compact__list">` mit `<li class="dhps-tp-compact__item">`. Eigene Inline-SVGs. Player wird **per JS dynamisch** in `<div class="dhps-tp-compact__player">` gespawnt. |
| `tpt/default.php` | ~87 | Single-Video. Wrapper `dhps-tpt-card`, aber Poster nutzt `dhps-tp-card__*`-Klassen (Markup-Mix). `get_option('dhps_tpt_ues')` und `get_option('dhps_tpt_teasertext')` **im Template** (Architektur-Bruch). Kein Empty-State (nur `return`). |
| `tpt/card.php` | ~79 | wie tpt/default + Box-Shadow-Wrapper. |
| `tpt/compact.php` | ~70 | Horizontal Layout mit kleinem Thumbnail (160x93). `<h5>`-Ueberschrift. |

### 1.3 LP-Inheritance heute

Es gibt **kein** `public/views/services/lp/`-Verzeichnis. Die Pipeline registriert
einen Template-Fallback `lp -> tp`, sodass beim Rendern eines LP-Services
unmittelbar `tp/default.php` etc. genutzt wird. Der `LP_Parser` setzt jedoch
das `service`-Feld pro Video auf `'lexplain'`, sodass der AJAX-Proxy-Aufruf
korrekt funktioniert. Der Wrapper im gerenderten HTML traegt `dhps-service--lp`
(Service-Class wird vom Pipeline-Layer gesetzt), nicht `dhps-service--tp`. Die
TP-JS-Init laeuft trotzdem - siehe Risiko in Sektion 7.1.

---

## 2. TP-JS-Selektor-Inventar

Quelle: vollstaendige Inspektion `public/js/dhps-tp.js` (695 LOC). Selektoren
sind die einzige Vertrags-Oberflaeche zwischen Templates und JS.

### 2.1 Pflicht-Selektoren (muessen erhalten bleiben)

| Selektor | Funktion | BC-Strategie |
|---|---|---|
| `.dhps-service--tp` (Wrapper) | `init()`: Top-Level-Container fuer alle drei Sub-Inits. **Auch TPT** haengt heute zusaetzlich `dhps-service--tp` am Root. **LP** bekommt es per Pipeline NICHT - die `.dhps-service--lp`-Wurzel wird durch das TP-JS-Init heute nicht erfasst. | Beibehalten am Root aller drei Services. Fuer LP -> in Pipeline `service_class` ergaenzen oder TP-JS um `.dhps-service--lp` erweitern (siehe Risiko 7.1). |
| `[data-video-slug]` | `initLazyVideoLoading()` Event-Delegation per `e.target.closest`. Spielt Video ab. | Beibehalten via ContentCard-`data_attrs => ['video-slug' => $slug]`. |
| `[data-poster-url]`, `[data-v-modus]` | Read auf Poster-Click - liefert `loadVideoIframe()` die noetigen Felder. | Beibehalten via ContentCard-`data_attrs`. |
| `.dhps-tp-video__poster` / `.dhps-tp-card__poster` / `.dhps-content-card__media` | `loadVideoIframe()` -> `posterEl` finden, ausblenden. **`.dhps-content-card__media` ist v0.14.1 erweitert (4 Zeilen Diff).** | Bleibt, kein weiterer Patch noetig. |
| `.dhps-tp-card` (Card-Root) | `initCategoryFilter()`: Liste der Cards, `card.style.display = 'none'`. Auch `resetLazyLoadAfterFilter()`. | Beibehalten als **Zusatz-Klasse** auf ContentCard (MAES-Pattern). |
| `.dhps-tp-card--lazy-hidden` | `getFilteredHiddenCards()`, `showNextBatch()`. | Beibehalten als Zusatz-Klasse je nach `$is_hidden`. |
| `.dhps-tp-load-more` | `initLazyLoadMore()` - Button-Click-Listener. | Beibehalten ausserhalb der ContentList. |
| `.dhps-tp-grid` / `.dhps-tp-cards` | `setupAutoLoad()` sucht Grid-Container fuer Sentinel-Einfuegung. | Beibehalten - ContentList rendert eigenen Wrapper, deshalb sollte das Template entweder den ContentList-Wrapper mit `.dhps-tp-grid`-Zusatzklasse versehen ODER der Sentinel manuell platziert werden. (Detail-Discovery noetig - aktuell wahrscheinlichste Loesung: Wrapper-Klasse via ContentList `class`-Prop.) |
| `.dhps-tp--playing` | `loadVideoIframe()` setzt State (deaktiviert Hover). | Beibehalten als Body/Card-Class - rein visueller Effekt. |
| `.dhps-tp-video__player` | `loadVideoIframe()`-Anker fuer iframe-Append (Inline-Modus). | **Nur im Featured-Video noetig.** Card-Variante haengt iframe an `parent` von `posterEl`. ContentCard hat keinen `__player`-Wrapper - im Inline-Modus geht der iframe an den `parent` des `.dhps-content-card__media`, also den ContentCard-Body. Funktioniert (MAES-Erfahrung). |
| `.dhps-filter-bar__btn` / `.dhps-tp-filter__btn` | `initCategoryFilter()`: Filter-Buttons. | Beibehalten in Filter-Nav (kein ContentList-Element, separate `<nav>`). |
| `.dhps-tp-compact__trigger` / `.dhps-tp-compact__list` / `.dhps-tp-compact__item` / `.dhps-tp-compact__video-btn` / `.dhps-tp-compact__player` | Compact-Accordion + dynamischer Player-Spawn. | **Kritisch**: ContentCard hat KEIN Accordion-Equivalent fuer die TP-Compact-Logik. Es gibt das `accordion`-Component, aber das spawnt keinen Video-Player. Empfehlung: Compact-Layout als **Hybrid** belassen oder spaeter migrieren (Sektion 4.3). |

### 2.2 Selektoren mit reiner CSS-Funktion (sicher ersetzbar)

| Selektor | Heute | Nach Migration |
|---|---|---|
| `.dhps-tp-card__poster` | Poster-Wrapper + Click-Handler-Target | ContentCard `.dhps-content-card__media` (Poster-Wrapper). `data-video-slug` an der Card-Root. Wegen 2.1 als Zusatzklasse beibehalten. |
| `.dhps-tp-card__img` | `<img class>` | `.dhps-content-card__image` |
| `.dhps-tp-card__play-btn` | Inline-Style-Anker (Steuern-Gruen) | `.dhps-content-card__play-overlay` (von ContentCard automatisch fuer `type=video` gerendert) - Branding via Service-Class. **Inline-Style entfaellt.** |
| `.dhps-tp-card__title` | `<h4>` mit Titel | `.dhps-content-card__title` (default `<h3>`, via Filter konfigurierbar) |
| `.dhps-tp-card__teaser` | `<p>` mit Teaser | `.dhps-content-card__teaser` (CSS line-clamp statt PHP `mb_strimwidth`) |
| `.dhps-tp-card__meta` / `__badge` / `__date` | Meta-Zeile | `.dhps-content-card__meta` + `.dhps-content-card__badges` |
| `.dhps-tp-card__body` | Body-Wrapper | `.dhps-content-card__body` |
| `.dhps-tpt-card`, `.dhps-tpt-card__heading`, `.dhps-tpt-card__body`, `.dhps-tpt-card__title`, `.dhps-tpt-card__teaser`, `.dhps-tpt-card__date` | TPT-spezifische Markup | Komplett durch ContentCard ersetzbar - TPT verhaelt sich wie ContentList mit 1 Item. |

### 2.3 Featured-Video (`__video__*`-Markup) - Sonderfall

| Selektor | Funktion | Migration |
|---|---|---|
| `.dhps-tp-featured` | Section-Container | Bleibt (oder als ContentList mit 1 Item plus `<h3>`-Heading davor) |
| `.dhps-tp-video` / `.dhps-tp-video--featured` | Wrapper Player+Info | Optional ersetzbar durch ContentCard mit `class="dhps-tp-featured"` Modifier |
| `.dhps-tp-video__player` | Player-Wrapper | **Selektor-relevant fuer Inline-iframe-Append.** Bei MAES war Card-only, Featured-Variante existiert nur in TP. Empfehlung: belassen oder ContentCard mit BC-Klasse versehen. |
| `.dhps-tp-video__poster` | Poster + Click-Anker | -> `.dhps-content-card__media` + `data_attrs` |
| `.dhps-tp-video__title` / `__teaser` / `__date` | Info-Sektion | -> `.dhps-content-card__title/__teaser/__meta` |
| `.dhps-tp-video__close` | Dynamisch erzeugter Close-Button (in JS) | Bleibt - wird im JS erzeugt. |
| `.dhps-tp-video__iframe` | Dynamisch erzeugtes iframe | Bleibt - wird im JS erzeugt. |

---

## 3. Component-Coverage-Matrix

| Template | ContentList | ContentCard (video) | ContentCard (Single) | Accordion (Compact) | FilterBar | LazyImage | EmptyState |
|---|---|---|---|---|---|---|---|
| `tp/default.php` | x (Grid 60 Videos) | x | x (Featured) | - | x (Kategorien) | x (via Card) | x |
| `tp/card.php` | x (Grid flach) | x | - | - | x | x | x |
| `tp/compact.php` | - (Custom-Markup bleibt) | - | - | x (`accordion`-Component pro Kategorie) | - | - | x |
| `tpt/default.php` | x (1 Item) ODER nackt | - | x (Single-Card) | - | - | x | x |
| `tpt/card.php` | x (1 Item) ODER nackt | - | x (Single-Card + Box-Shadow) | - | - | x | x |
| `tpt/compact.php` | - | - | x (compact variant) | - | - | x | x |

**Anmerkung:** `tp/compact.php` ist der kritischste Punkt - die JS-Logik
`initCompactAccordion` spawnt einen Player **dynamisch** unter dem `<li>`. Eine
1:1-Migration auf `accordion`+ContentCard waere moeglich, wuerde aber JS-Aenderungen
verlangen. **Empfehlung: Compact in v0.14.3 nicht migrieren** (Hybrid bleibt),
spaeter in eigener Iteration (v0.14.5?) zusammen mit JS-Refactor.

---

## 4. Implementierungs-Vorschlaege pro Service

### 4.1 TP-Templates

#### `tp/default.php`

1. **Wrapper-`<div class="dhps-service dhps-service--tp ...">` bleibt** mit allen
   `data-*`-Attributen (`data-ajax-url`, `data-nonce`, `data-video-mode`, `data-service`,
   `data-lazy-count`, `data-lazy-mode`). Diese sind Pflicht-Inputs fuer TP-JS.
2. **Featured-Video** als separate `<section class="dhps-tp-featured">` mit einem
   ContentCard(type='video', class='dhps-tp-card dhps-tp-video dhps-tp-video--featured').
   `data_attrs` fuer `video-slug`/`poster-url`/`v-modus`. Inline-`style="color:..."`
   entfaellt - Branding kommt automatisch ueber `.dhps-content-card--service-tp`-Hook
   (muss in `dhps-components.css` ergaenzt werden, siehe 4.5).
3. **Filter-Bar** bleibt als `<nav class="dhps-filter-bar dhps-tp-catalog__filter">`
   mit Buttons - **nicht** ueber FilterBar-Component, weil TP-JS-Selektoren noetig
   sind. Alternativ: FilterBar-Component verwenden, wenn der Component-Render
   identische Klassen `.dhps-filter-bar__btn` + `data-filter` ausgibt. **Discovery
   in Implementierungs-Phase noetig** (Vorlage: `public/views/components/filter-bar.php`).
4. **Grid** als ContentList(layout='grid', columns=$grid_columns, items=$items) mit
   `class='dhps-tp-grid dhps-tp-grid--{N}col'`. Items pre-gemappt: pro Video
   `{type:'video', title:..., teaser:'', media_url:poster_url, service:'tp',
   class: 'dhps-tp-card' . ($is_hidden ? ' dhps-tp-card--lazy-hidden' : ''),
   data_attrs: {video-slug, poster-url, v-modus, category, video-id, video-index}}`.
5. **Lazy-Count-Logik** bleibt im Template (genau wie MAES - Items werden mit
   `$is_hidden`-Status gerendert, TP-JS uebernimmt die DOM-Manipulation).
6. **Load-More-Button** unveraendert (`<button class="dhps-tp-load-more">`).
7. **`hidden`-Attribut bei lazy_hidden-Cards:** Erfordert dass ContentCard
   das `hidden`-HTML-Attribut akzeptiert. Heute nur `class` + `data_attrs`
   unterstuetzt - moeglicherweise Erweiterung der ContentCard-Prop-Liste
   um `attrs` (allgemeine HTML-Attribute) oder via CSS `[class~="dhps-tp-card--lazy-hidden"] { display: none; }` (Server-CSS-only Loesung). **Empfehlung CSS-only** -
   `hidden`-Attribut ist redundant mit `display:none`-CSS-Klasse.

#### `tp/card.php`

Analog default, aber kein Featured-Video. Box-Shadow-Wrapper `<div class="dhps-card">`
bleibt um den ContentList. `mb_strimwidth(teaser, 0, 100)` entfaellt - CSS
line-clamp uebernimmt (eventuell muss MAES-Pattern verifiziert werden:
`.dhps-content-card--video .dhps-content-card__teaser { line-clamp: 3 }`).

#### `tp/compact.php`

**KEINE Migration in v0.14.3** (siehe Sektion 7.2 Risiko). Belassen wie ist
oder minimaler Stil-Cleanup. Begruendung: Compact-Accordion spawnt Player
ueber JS, das ist nicht 1:1 ueber ContentCard abbildbar ohne JS-Refactor.

### 4.2 TPT-Templates

TPT hat 1 Video. ContentCard ist perfekt geeignet.

#### `tpt/default.php` und `tpt/card.php`

1. **Wrapper bleibt** mit `dhps-service--tp dhps-service--tpt` und allen
   `data-*`-Attributen.
2. **`get_option('dhps_tpt_ues')` und `get_option('dhps_tpt_teasertext')`**
   sollten **in den Parser/Pipeline** verschoben werden. Im Template-Code keine
   `get_option`-Calls (DI-Verletzung). Wenn das in v0.14.3 nicht passieren kann
   (z.B. weil der Pipeline-Layer ohne Service-Kontext-Daten arbeitet), dann
   wenigstens am Anfang des Templates in eine lokale Variable lesen und sofort
   sanitizen. **Praeferenz: TPT_Parser oder Modules-Layer.**
3. **ContentCard(type='video', class='dhps-tp-card dhps-tpt-card', service='tp')**
   mit `data_attrs` fuer slug/poster/v_modus. Heading-Ueberschrift `$ueberschrift`
   als `<h3>` separat vor der Card oder via ContentCard-Header.
4. **Empty-State:** statt `return` ein ContentList mit 0 Items + `empty_state`-Prop
   verwenden (Editor-Vorschau gewinnt sichtbare Komponente). **Major UX-Win**.
5. **Box-Shadow (card.php):** Wrapper `<div class="dhps-card">` bleibt.

#### `tpt/compact.php`

Horizontaler Single-Video-Layout mit kleinem Thumbnail. ContentCard
(type='video', class='dhps-tp-card dhps-tpt-card dhps-tpt-card--compact dhps-content-card--compact').
Branding-CSS muss `--compact`-Modifier kennen (existiert teilweise in
`dhps-components.css`, siehe MAES-Pattern). Eventuell `media-size`-Prop an
ContentCard fuer Thumbnail-Size-Steuerung noetig - alternativ ueber CSS
`.dhps-tpt-card--compact .dhps-content-card__image { width: 160px; }`.

### 4.3 LP

**Keine eigenen LP-Templates noetig.** Wenn TP-Templates auf ContentCard
migrieren, erbt LP automatisch via Template-Fallback und bekommt korrektes
Recht-Branding ueber `.dhps-content-card--service-lp`-Hooks
(bereits in `dhps-components.css` ab Zeile 943-950 fuer Action-Buttons gesetzt;
fuer Play-Overlay siehe Sektion 5).

**Aber:** Damit das funktioniert, muss das ContentCard-Item `service='lp'`
(NICHT `service='tp'`) bekommen. Logik im TP-Template:

```
$card_service = ( 'lp' === $service_tag ) ? 'lp' : 'tp';
```

oder service direkt aus `$video['service']` ableiten (`'lexplain'` -> `'lp'`).

---

## 5. LP-Inheritance-Validation

### 5.1 Branding-Token-Switch im CSS

**Status quo in `css/dhps-components.css`:**

```css
.dhps-content-card--service-lp,
.dhps-content-card--service-lxmio {
  --dhps-color-primary: var(--dhps-color-recht);
  --dhps-color-primary-hover: var(--dhps-color-recht-hover);
}
```

(Zeile 569-573) - **bereits vorhanden, schon fuer LP konfiguriert in v0.14.2.**
Plus zusaetzliche Action-Button-Selektoren in 943-950.

**Was fehlt:** ein Play-Overlay-Hook fuer `--service-lp` (analog MAES Zeile
930-932). Aktuell faerbt nur `--service-maes` das `.dhps-content-card__play-overlay`.
Fuer LP muss ergaenzt werden:

```css
.dhps-content-card--service-lp .dhps-content-card__play-overlay {
  color: var(--dhps-color-recht);
}
.dhps-content-card--service-tp .dhps-content-card__play-overlay {
  color: var(--dhps-color-steuern);
}
```

(Ersatz fuer das aktuelle TP-Inline-`style="color:..."`.)

### 5.2 Wrapper-Service-Klasse

Aktuell setzt die Pipeline `.dhps-service--lp` als `$service_class` und das
Template (`tp/default.php`) bekommt davon nichts mit - es haengt aktuell
**hartkodiert** `.dhps-service--tp` an, da `$service_class` zwar `lp` ist,
aber kein dynamisches Switch erfolgt. **Beim Render einer LP-Anfrage** ist
also der Wrapper bereits korrekt `dhps-service--lp` UND `dhps-service--tp`
(da `service_class` durchgereicht wird) - wenn die Pipeline so funktioniert
wie dokumentiert.

**Pflicht-Validierung in QA:** Demo-Page mit LP-Shortcode rendern, DOM
inspizieren, ob `.dhps-service--lp` UND TP-JS-Init laeuft.

### 5.3 Soll LP einen eigenen Token-Switch wie LXMIO bekommen?

LXMIO bekam in v0.14.2:
```css
.dhps-service--lxmio { --dhps-color-primary: var(--dhps-color-recht); ... }
```
Das ist ein **Wrapper-basierter** Switch (greift fuer alle Childs in dem
Service-Container). Fuer LP existiert das im Frontend-CSS heute **nicht**
explizit auf `.dhps-service--lp` - nur auf `.dhps-content-card--service-lp`
(per Card).

**Empfehlung:** Bei v0.14.3 zusaetzlich einen Wrapper-Token-Switch
`.dhps-service--lp { --dhps-color-primary: var(--dhps-color-recht); ... }`
in `css/dhps-frontend.css` oder besser `css/dhps-components.css` ergaenzen.
Konsistenz mit LXMIO + greift fuer Filter-Buttons, Load-More-Button etc.,
die nicht innerhalb von ContentCards leben.

---

## 6. UI-Audit-Findings: automatisch behoben vs. Extra-Aufwand

Bezug: [`11-uiux-audit-v0140.md`](11-uiux-audit-v0140.md) Sektion 2 (TP/LP).

| # | Finding | Auto-Behoben | Extra-Aufwand |
|---|---|---|---|
| 1 | Featured + Grid ALLER 60 Videos im Initial-HTML (Render-Volumen) | **Nein** - `lazy_count` Filter bleibt das Werkzeug. Bytes-Cost steigt sogar (MAES-Erfahrung: +95% bei Videos). | Pipeline-Cache (Transient) oder echte Pagination per ContentList (Out-of-Scope v0.14.3). |
| 2 | 484ms Render-Zeit (API + Parse) | **Nein** - Template-Rendering ist nicht der Bottleneck. ContentCard-Mapping addiert evtl. ~5-10ms bei 60 Items. | Server-side Transient-Cache `dhps_tp_payload_*` (gehoert in eigene Iteration). |
| 3 | Filter ohne URL-State (F5 verliert Filter) | **Nein** - Filter-Verhalten bleibt clientseitig in TP-JS. | `history.replaceState`-Hook waere JS-Refactor. Out-of-scope. |
| 4 | Heading-Hierarchie h3/h4 (Featured vs. Catalog) | **Teilweise** - ContentCard rendert default `<h3>`, via Filter `dhps_content_card_heading_level` konfigurierbar (pro `type`). | Im Template `add_filter` fuer `type=video` ggf. auf `<h4>` setzen, falls Section-Heading `<h3>` bleibt. Saubere Loesung erfordert Kontext-Filter (z.B. featured vs. card). |
| 5 | Inline-`style="color:..."` auf Play-Button (CSP-Bruch) | **JA, automatisch.** ContentCard rendert `.dhps-content-card__play-overlay` ohne Inline-Style; Branding via Service-Hook-CSS (Sektion 5.1). | Nur 1 Zeile CSS ergaenzen (`--service-tp` Play-Overlay-Color). |

**TPT-Findings (Sektion TPT im Audit):**

| # | Finding | Auto-Behoben | Extra-Aufwand |
|---|---|---|---|
| 1 | Single-Video, kein Render-Problem | n/a | n/a |
| 2 | `dhps-tpt-card` Wrapper + `dhps-tp-card__*` Klassen-Mix (Inkonsistenz) | **JA** - durch ContentCard wird konsolidiert. | - |
| 3 | `get_option`-Reads im Template (Architektur-Bruch) | **Nein** - bleibt bestehen, sofern nicht in Parser/Modules verschoben. | Parser-Erweiterung oder Modules-Layer-Datenanreicherung. Empfohlen aber nicht zwingend BC-relevant. |
| 4 | Kein Skeleton/Empty-State (Video=null) | **JA** - ContentList mit `empty_state`-Prop loest das. | - |

---

## 7. Risiken

### 7.1 TP-JS-Pipeline darf nicht brechen

**Hochstes Risiko der Migration.** 695 LOC vernetzte Selektoren. Schutzmassnahmen:

- **`.dhps-service--tp`-Klasse zwingend am Wrapper** (auch fuer TPT, das es
  heute schon hat). Fuer **LP-Render-Pfad**: Pipeline setzt `$service_class`
  auf `dhps-service--lp` - die TP-JS-Init (`document.querySelectorAll('.dhps-service--tp')`)
  greift dann **nicht**. **Validation noetig**: laeuft heute schon? Falls
  nein, ist das bereits ein bestehender Bug (LP-Videos waeren stumm). Falls
  ja, dann setzt das Template-Fallback offenbar BEIDE Klassen.
  - **Konservative Loesung**: TP-JS-Init um `.dhps-service--lp` erweitern
    (1 Zeile Diff): `document.querySelectorAll('.dhps-service--tp, .dhps-service--lp')`.
  - Alternativ: Wrapper im migrierten Template **immer** `dhps-service--tp` ergaenzen
    (zusaetzlich zu `dhps-service--lp` etc.), wie es TPT-Templates heute schon machen.

- **Zusatz-Klassen `dhps-tp-card` + `dhps-tp-card--lazy-hidden`** an jeder ContentCard
  zwingend. Filter und Load-More verlassen sich darauf.

- **`data_attrs => ['video-slug' => ..., 'poster-url' => ..., 'v-modus' => ...]`**
  zwingend pro Card. Vergessen = Klick spielt nichts ab.

- **`.dhps-tp-grid`-Klasse** am ContentList-Wrapper (via `class`-Prop)
  zwingend - sonst findet `setupAutoLoad` keinen Sentinel-Anker.

- **Lazy-Load-Selektor `.dhps-tp-card--lazy-hidden`**: heute haengt das Template
  auch noch das HTML-`hidden`-Attribut. ContentCard hat dafuer keinen Prop -
  entweder ContentCard erweitern (Prop `hidden`/`attrs`) oder CSS-only loesen
  via `.dhps-tp-card--lazy-hidden { display: none; }`.

### 7.2 Compact-Layout JS-Spawn-Logik

`initCompactAccordion` in `dhps-tp.js` erzeugt einen `dhps-tp-compact__player`
mitsamt eigenem `dhps-tp-video__poster`-Stub. Eine ContentCard-Migration des
Compact-Layouts wuerde **mindestens** den JS-Spawn-Code refaktoren muessen.
**Daher: Compact-Layout in v0.14.3 NICHT migrieren** (Hybrid). Stattdessen
in einer Folge-Iteration (v0.14.5?) angehen, wenn TP-JS ohnehin auf
ContentCard-First refaktored wird.

### 7.3 TPT teilt `dhps_ota_tp` mit TP

Funktional kein Migrationsrisiko, aber bei der Reihenfolge wichtig: Wenn
Live-Tests mit der OTA scheitern, ist es kein Migrations-Bug.

### 7.4 LP-Live-Test-Limitation

Memory: "LP-OTA und TC-kdnr Provisionierung fuer Live-Tests" steht offen.
LP-Test im Docker wird ggf. nur via Mock-Daten moeglich. **Akzeptabel:**
LP-Migration ist rein Inheritance-basiert - wenn TP funktioniert UND
`service_tag='lp'` korrekt LP-Branding triggert, ist LP de facto OK.

### 7.5 Bytes-Cost (MAES-Erfahrung)

MAES-Videos: +95%. TP mit 60 Videos ist 2x grosser Volumen-Anteil - bei
gleicher Wachstumsrate **~+95% absolute Bytes** trotz lazy_count
(hidden-HTML bleibt im Markup, nur via CSS display:none).
**Akzeptabel** (siehe MAES-Begruendung), aber QA sollte messen.

---

## 8. Specialist-Aufteilung-Empfehlung

### Empfohlene Variante: **2 parallele Specialists + 1 Composition-Lead**

**Begruendung:**
- TP und TPT haben **unterschiedliche Architektur** (Multi-Item Grid vs. Single-Item).
  Parallele Bearbeitung vermeidet Konflikte und nutzt Specialist-Expertise.
- TP und TPT **teilen `dhps-tp-card`-Klassen** - Lead muss Konsistenz sicherstellen.
- **LP braucht KEINEN eigenen Specialist** - reine Verifikation in QA-Phase
  (TP-Migration testen + LP-Demo-Page checken, dass Recht-Branding greift).

**Aufteilung:**

| Specialist | Files | Risiko | Vorbild |
|---|---|---|---|
| **TP-1** | `tp/default.php`, `tp/card.php` | Mittel-Hoch (Featured + Grid + Filter + LazyLoad) | MAES-Videos-Handover (`.specialist-M1-handover.md`) |
| **TPT-1** | `tpt/default.php`, `tpt/card.php`, `tpt/compact.php` | Niedrig (Single-Item, gut definiert) | MAES-Aktuelles-Pattern (Single ContentCard) |
| **Lead-Compose** | CSS-Branding-Hooks, optional TP-JS-Patch fuer LP-Init, Wrapper-Token-Switch fuer `.dhps-service--lp`, ggf. ContentCard-`hidden`-Prop | Hoch (Cross-Cutting) | v0.14.1 Composition |
| **(NICHT migrieren)** | `tp/compact.php` | - | siehe Risiko 7.2 |

### Alternative: 1 grosser Spec (TP+TPT zusammen)

Vorteil: Konsistenz garantiert. Nachteil: serielle Arbeit + Kontext-Switching.
Nur sinnvoll wenn beide Templates klein wirken - was sie nicht tun (TP-Default
hat 190 LoC plus Featured-Sonderfall).

### Sequenziell: TP -> TPT -> LP-Verifikation

Nicht empfohlen wegen Zeit-Verlust. Parallele Specialists laufen schneller,
solange Lead die Konsolidierung uebernimmt (CSS + Bridging).

---

## 9. Performance-Prognose (qualitativ, mit Empirie-Warnung)

**MAES-Disconnect-Erfahrung beruecksichtigt:** Discovery prognostizierte v0.14.1
`-25 bis -36%` und es kamen **+95% bis +176%** dabei heraus. Bytes-Cost der
ContentCard ist real groesser als das Wegfallen alter Markup-Pieces.

### Qualitative TP-Magnituden

| Layout | Erwartete Tendenz | Begruendung |
|---|---|---|
| `tp/default.php` (60 Videos, lazy_count=0) | **+50 bis +120% Bytes** | Pro Card: `data_attrs` (4 Attribute), LazyImage-Wrapper, Action-Footer, BEM-Klassen-Verkettung. Bei 60 Items akkumuliert sich das. Featured-Video bleibt klein. |
| `tp/default.php` (lazy_count=12) | **+30 bis +60% Bytes** | Hidden-Cards sind nur Markup (kein Render im Browser), gzip-komprimiert. |
| `tp/card.php` | **+50 bis +100% Bytes** | wie default, flacher. |
| `tp/compact.php` | **kein Delta** (nicht migriert) | - |
| `tpt/*.php` | **+10 bis +30% Bytes** | Single-Item, ContentList-Wrapper-Overhead ueberwiegt. |

### Empirie-Pflicht

**DISCOVERY DARF NUR MAGNITUDEN NENNEN.** Specialist-Lead soll vor finaler
Composition einen Smoke-Test machen (`wp eval 'echo strlen(do_shortcode(...))'`)
und in Changelog dokumentieren. Roadmap-Ziel `< 150 KB pro Page` darf nicht
gerissen werden. Bei 60 Videos a 2 KB Card = ~120 KB Grenzwert-nah.

### Wahrgenommene Performance (gz, FCP, LCP)

- gzip-Effizienz der BEM-Klassen-Repetition: 5-8x (MAES-Erfahrung). Real-World-
  Transfer-Wachstum ist deutlich geringer als die Source-Bytes-Statistik.
- LCP wird durch LazyImage **verbessert** (loading="lazy" automatisch).
- A11y: +Heading-Konsistenz (`<h3>` default), +Focus-Ringe (ContentCard hat
  `:focus-visible`), +ARIA-Labels (Service-konsistente Action-Labels).

---

## 10. Theme-Override-Migration-Pfad

### Aenderungs-Schmerz fuer Theme-Entwickler

Pfade bleiben gleich, aber HTML/CSS aendert sich substantiell.

| Theme-Override-Pfad | BC-Status nach v0.14.3 |
|---|---|
| `{theme}/dhps/services/tp/default.php` | **Markup geaendert.** Theme muss Template neu schreiben oder eigenes Markup beibehalten - Plugin-Default ist neu. |
| `{theme}/dhps/services/tp/card.php` | dito |
| `{theme}/dhps/services/tp/compact.php` | **Unveraendert** (nicht migriert in v0.14.3). |
| `{theme}/dhps/services/tpt/default.php` | **Markup geaendert.** |
| `{theme}/dhps/services/tpt/card.php` | dito |
| `{theme}/dhps/services/tpt/compact.php` | dito |
| `{theme}/dhps/services/lp/*` | **Optional** - LP nutzt Fallback auf tp/*. Themes koennten eigene LP-Overrides anlegen (selten genutzt). |

### CSS-Klassen-Breaking-Changes (Theme-CSS-Anpassung)

| Alte Klasse | Neue Klasse | Hinweis |
|---|---|---|
| `.dhps-tp-card__poster` | `.dhps-content-card__media` | Zusatz-Klasse `.dhps-tp-card` bleibt am Article, Selektoren auf `.dhps-tp-card .dhps-content-card__media` funktionieren. |
| `.dhps-tp-card__img` | `.dhps-content-card__image` | dito |
| `.dhps-tp-card__title` (h4) | `.dhps-content-card__title` (h3 default) | Heading-Level via Filter `dhps_content_card_heading_level` aenderbar |
| `.dhps-tp-card__teaser` | `.dhps-content-card__teaser` | mit `-webkit-line-clamp` |
| `.dhps-tp-card__body` | `.dhps-content-card__body` | |
| `.dhps-tp-card__play-btn` (mit Inline-style) | `.dhps-content-card__play-overlay` (Branding via Service-Hook) | Inline-Style entfaellt |
| `.dhps-tpt-card`, `__heading`, `__title`, `__teaser`, `__date`, `__body`, `--compact`, `--boxed`, `--poster--compact`, `__body--compact`, `__heading--compact`, `__title--compact`, `__teaser--compact` | Komplett ersetzt durch `.dhps-content-card--video` + `--compact`-Modifier | TPT komplett konsolidiert |

### CHANGELOG-Empfehlung

```
### Backward Compatibility
- Shortcodes + Option-Keys + Filter-Hooks bleiben stabil.
- HTML-Struktur in TP-/TPT-Templates hat sich geaendert. Theme-Overrides
  unter `{theme}/dhps/services/{tp,tpt}/{default,card}.php` muessen ggf.
  nachgezogen werden.
- `tp/compact.php` ist **unveraendert** (wegen JS-Spawn-Logik bewusst beibehalten).
- CSS-Klassen-Aenderungen: alte `.dhps-tp-card__*`- und `.dhps-tpt-card__*`-
  Selektoren in Theme-CSS sollten auf `.dhps-content-card__*` migriert
  werden. Zusatz-Klasse `.dhps-tp-card` bleibt jedoch am Card-Root fuer
  JS-Selektoren - Theme-CSS auf `.dhps-tp-card .dhps-content-card__media`
  etc. funktioniert.
- TP-JS-API (Shortcode-Daten-Attribute, JS-Events) ist unveraendert.
```

---

## Anhang A: ContentCard `attrs`/`hidden`-Prop-Diskussion

Heutige ContentCard akzeptiert: `class`, `service`, `data_attrs`. Das HTML
`hidden`-Attribut wird **nicht** durchgereicht. Optionen:

1. **CSS-only Loesung (empfohlen)**: `.dhps-tp-card--lazy-hidden { display: none; }`
   bereits in `dhps-frontend.css` vorhanden? Falls ja, kein Code-Change noetig.
   `hidden`-Attribut ist Browser-default-CSS - eine Klasse mit
   `display: none` deckt das voll ab.
2. **ContentCard-Erweiterung**: neue Prop `attrs` (Whitelist `hidden`, `tabindex`,
   etc.) analog zu `data_attrs`. Saubere Loesung, kleine Aenderung, aber
   ein weiterer Migrations-Touchpoint.

**Empfehlung Discovery: Option 1**, weil keine Component-Aenderung noetig und
das `hidden`-Attribut sowieso CSS-aequivalent ist.

## Anhang B: Filter-Bar-Component-Frage

Heutige Filter-Bar wird inline gerendert (16 Zeilen pro Template). Es gibt
ein `filter-bar`-Component-Template, das aber bisher (Stand 2026-05-23) in
**keinem** Service-Template eingesetzt wird (Grep-Befund).

**Empfehlung:** In v0.14.3 noch **NICHT** auf FilterBar-Component umsteigen.
Erstens muss validiert werden, dass FilterBar die TP-JS-Selektoren
(`.dhps-filter-bar__btn` + `data-filter`) korrekt ausgibt. Zweitens ist das
ein zusaetzlicher Migrations-Touchpoint, der das Risiko erhoeht. Inline-
Markup beibehalten und in v0.14.4/v0.14.5 separat angehen.

## Anhang C: Demo + Test-Befehle

```bash
# TP Default-Layout.
docker exec -it wp-deubner-hp-services-wordpress-1 \
  wp eval 'echo do_shortcode("[tp]");'

# TPT Default.
docker exec -it wp-deubner-hp-services-wordpress-1 \
  wp eval 'echo do_shortcode("[tpt]");'

# LP Default (greift TP-Templates via Fallback).
docker exec -it wp-deubner-hp-services-wordpress-1 \
  wp eval 'echo do_shortcode("[lp]");'

# Bytes-Smoke-Test pro Layout (vor + nach Migration vergleichen).
docker exec -it wp-deubner-hp-services-wordpress-1 \
  wp eval 'echo strlen(do_shortcode("[tp]"));'
docker exec -it wp-deubner-hp-services-wordpress-1 \
  wp eval 'echo strlen(do_shortcode("[tp layout=\"card\"]"));'
docker exec -it wp-deubner-hp-services-wordpress-1 \
  wp eval 'echo strlen(do_shortcode("[tpt]"));'

# Visuelle Pruefung:
# - Demo-Page mit [tp], [tpt], [lp] aufrufen
# - DOM-Inspect: erwarte <article class="dhps-content-card dhps-content-card--video
#   dhps-content-card--service-tp dhps-tp-card" data-video-slug="..." ...>
# - Klick auf Card: Network-Tab AJAX-Request action=dhps_tp_video_src
# - LP: data-service="lexplain" pruefen
# - LP: Recht-Blau-Branding sichtbar (Play-Overlay-Color)
```
