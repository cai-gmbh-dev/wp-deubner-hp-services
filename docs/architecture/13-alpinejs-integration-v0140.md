# Alpine.js Integration - Architektur-Report v0.14.0

Status: Research / Architektur-Vorschlag (keine Code-Aenderungen)
Datum: 2026-05-22
Zielversion: v0.14.0 (Mikro-Framework-Adoption)
Plattform: WP 6.9.4, Elementor 4.0.1, PHP 8.3
Bestehende JS-Files: `public/js/dhps-mio.js` (~1247 LOC), `dhps-mmb.js` (~369 LOC), `dhps-tp.js` (~696 LOC). Alle Vanilla, IIFE-gekapselt, kein jQuery.

---

## 1. Alpine.js v3.x Status quo

| Aspekt        | Wert                                                              |
|---------------|-------------------------------------------------------------------|
| Major-Version | v3.x (stabil, langjaehrig gepflegt, kein v4 angekuendigt)         |
| Empfehlung    | aktuelle v3.x.x als Minor pinnen (Stand 2026-05: 3.14.x-Reihe)   |
| Bundle (min+gz)| ca. 14-16 KB Core                                                |
| Lizenz        | MIT                                                               |
| Persist-Plugin| ~1 KB (optional, fuer localStorage-Filter-State)                  |
| Focus-Plugin  | ~2 KB (optional, fuer Focus-Trap im Video-Modal)                  |
| CDN           | `https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js`     |

Empfohlene exakte Pin-Strategie: Version in einer PHP-Konstante (`DHPS_ALPINE_VERSION`) hinterlegen und in `wp_register_script()` als Version-Argument verwenden. Damit ist die Browser-Cache-Buster-Logik identisch zur Plugin-Versionierung.

---

## 2. Loading-Strategie

Empfehlung: **lokal bundled (kein CDN), defer, conditional enqueued**.

Gruende:
- **Kein CDN:** DSGVO-Konformitaet (keine Drittanbieter-Requests ohne Einwilligung), keine externe Abhaengigkeit, kein SRI-Hash-Pflege-Aufwand.
- **`defer` statt `type=module`:** Alpine selbst nutzt kein ES-Modul-Format in der CDN-Build; `defer` reicht und ist mit allen Themes kompatibel.
- **Conditional:** Alpine nur enqueuen wenn ein `[dhps_*]`-Shortcode tatsaechlich gerendert wurde (Flag im Shortcode-Handler setzen, im `wp_enqueue_scripts` Prio 20 ausfuehren). Spart ~16 KB auf allen Seiten ohne Deubner-Services.

```
Alpine-Datei-Layout:
  public/js/vendor/alpinejs-3.x.x.min.js   (lokal, ~16 KB)
  public/js/dhps-alpine-init.js            (Plugin-Init, ~1 KB)
```

Performance-Effekt: Alpine ist parse-zeit-relevant (defer entkoppelt vom Critical Path). Beim ersten Verwendungsbild (z.B. MAES-Video-Filter) wird Alpine ein Mal pro Page-Load initialisiert und bleibt aktiv.

---

## 3. WordPress-Enqueue-Pattern

Erweiterung von `dhps_enqueue_frontend_styles()` in `Deubner_HP_Services.php` (Stand v0.13.0, Zeile 325-383). Vorschlag fuer v0.14.0:

```php
// Neue Konstante oben im Plugin-File.
define( 'DHPS_ALPINE_VERSION', '3.14.0' );

// Flag-Setter: Shortcode-Handler ruft dies bei Bedarf auf.
function dhps_request_alpine() {
    if ( ! did_action( 'wp_enqueue_scripts' ) ) {
        $GLOBALS['dhps_needs_alpine'] = true;
        return;
    }
    dhps_enqueue_alpine_now();
}

function dhps_enqueue_alpine_now() {
    if ( wp_script_is( 'dhps-alpine', 'enqueued' ) ) {
        return;
    }
    wp_register_script(
        'dhps-alpine',
        DEUBNER_HP_SERVICES_URL . 'public/js/vendor/alpinejs-' . DHPS_ALPINE_VERSION . '.min.js',
        array(),
        DHPS_ALPINE_VERSION,
        true  // im Footer
    );

    // Wichtig: defer-Attribut + spezifischer Namespace (siehe Sektion 4).
    wp_register_script(
        'dhps-alpine-init',
        DEUBNER_HP_SERVICES_URL . 'public/js/dhps-alpine-init.js',
        array( 'dhps-alpine' ),
        DEUBNER_HP_SERVICES_VERSION,
        true
    );

    wp_enqueue_script( 'dhps-alpine-init' );
}

// defer-Attribut nachruesten.
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
    if ( in_array( $handle, array( 'dhps-alpine', 'dhps-alpine-init' ), true ) ) {
        return str_replace( ' src=', ' defer src=', $tag );
    }
    return $tag;
}, 10, 2 );

// Im Shortcode-Handler (DHPS_Shortcodes) bei Bedarf aufrufen:
//   dhps_request_alpine();
// Spaeter im wp_footer / wp_print_footer_scripts:
add_action( 'wp_footer', function () {
    if ( ! empty( $GLOBALS['dhps_needs_alpine'] ) ) {
        dhps_enqueue_alpine_now();
    }
}, 5 );
```

Footer-Print: Da Alpine via `defer` laedt, ist die Reihenfolge garantiert (Spec-konform: defer-Scripts fuehren in Dokument-Reihenfolge nach Parsen aus). Templates koennen ihre `x-data`-Markup direkt einsetzen.

---

## 4. Namespace / Konflikt-Strategie

**Problem:** Theme oder anderes Plugin laedt evtl. eigenes Alpine; doppeltes `Alpine.start()` kann Komponenten doppelt initialisieren oder Magic-Properties ueberschreiben.

**Strategie:**

1. **Detection vor Init.** `dhps-alpine-init.js` prueft `window.Alpine`:
   ```js
   document.addEventListener( 'alpine:init', function () {
       window.Alpine.data( 'dhpsVideoFilter', dhpsVideoFilter );
       window.Alpine.data( 'dhpsMmbAccordion', dhpsMmbAccordion );
   } );
   ```
   Das `alpine:init`-Event feuert auch bei einer fremden Alpine-Instanz - wir registrieren defensiv nur unsere `dhps*`-Komponenten.

2. **Naming-Konvention.** Alle Alpine-Components, -Stores und -Magic-Properties bekommen Prefix `dhps`:
   ```html
   <div x-data="dhpsVideoFilter()" x-cloak>
     <button @click="$store.dhpsUi.openModal(slug)">Play</button>
   </div>
   ```
   Stores: `Alpine.store('dhpsUi', { ... })`. Damit kollidieren wir nicht mit `theme*` oder `wp*`-Konventionen.

3. **`x-cloak`-CSS.** In `dhps-frontend.css` ergaenzen:
   ```css
   [x-cloak] { display: none !important; }
   ```
   Verhindert FOUC (Flash of Uninitialized Content) bis Alpine bereit ist.

4. **Doppelte Alpine-Erkennung (Theme-konflikt).** Wenn vor unserem Script bereits `window.Alpine` existiert, geben wir Warnung im Konsolen-Log aus und ueberspringen die zweite Auto-Start-Phase. In der Praxis ist Alpine v3 idempotent gegenueber `start()`, aber wir wollen keine doppelte Initialisierung.

5. **Kein Auto-Init beim Block-Editor.** Im Gutenberg-Editor sollte Alpine nicht laufen (Bearbeitungs-View). Loesung: `is_admin()` blockt `dhps_enqueue_alpine_now()` bereits implizit, da `wp_enqueue_scripts` im Frontend feuert. Fuer Elementor-Editor-Preview gilt dasselbe (Elementor-Editor zeigt Frontend-Iframe, dort ist Alpine erwuenscht - keine Aenderung noetig).

---

## 5. Migrations-Plan bestehender JS-Files

**Prinzip:** Keine Big-Bang-Migration. Alpine-Adoption laeuft inkrementell, Vanilla-Files bleiben funktional. Neue Features (MAES-Video-Filter v2, Live-Suchen, einklappbare Termine) werden in Alpine implementiert.

| File           | LOC  | Strategie v0.14.0 | Strategie v0.15.0+ |
|----------------|------|-------------------|---------------------|
| `dhps-mio.js`  | 1247 | **Behalten.** Komplexe AJAX+Pagination+Render-Pipeline; Alpine-Rewrite waere Hochrisiko. | Optional: Filter-Bar-Logik (Topic-Pills) nach Alpine `dhpsTopicFilter`. |
| `dhps-mmb.js`  | 369  | **Behalten.** Accordion + AJAX-Suche stabil. | Filter-Bar + Accordion-Block koennen schrittweise nach Alpine. |
| `dhps-tp.js`   | 696  | **Hybrid.** Video-Lazy + Modal bleiben Vanilla (komplexe Focus-Trap-Logik). Category-Filter + Compact-Accordion nach Alpine. | Modal nach Alpine + `@alpinejs/focus`-Plugin. |
| `dhps-maes-videos.js` | n/a (existiert noch nicht als separates File - Logik in TP) | **Greenfield.** Neue Video-Filter-Komponente in Alpine bauen. | — |

**Migrations-Test-Strategie:** Vor jeder Portierung Visual-Regression-Snapshot (Screenshot) + manueller A11y-Test (Tab-Navigation + Screenreader-Stichprobe). Erst nach Bestaetigung Old-File entfernen.

---

## 6. A11y-Patterns mit Alpine (5 konkrete Beispiele)

### 6.1 ARIA-Expanded am Accordion

```html
<div x-data="{ open: false }">
  <button type="button"
          @click="open = !open"
          :aria-expanded="open.toString()"
          aria-controls="rubrik-1">
    Rubrik 1
  </button>
  <div id="rubrik-1" x-show="open" :aria-hidden="(!open).toString()" x-cloak>
    ...
  </div>
</div>
```

### 6.2 Live-Region fuer Suchergebnisse (`aria-live`)

```html
<div x-data="dhpsSearch()" x-init="init()">
  <input type="search" x-model.debounce.300ms="query" @input="run">
  <p class="dhps-sr-only" aria-live="polite" x-text="statusMsg"></p>
  <ul x-show="results.length">
    <template x-for="r in results" :key="r.id">
      <li x-text="r.title"></li>
    </template>
  </ul>
</div>
```
`statusMsg` z.B. `'12 Merkblaetter gefunden'` - Screenreader bekommt das ohne Fokus-Sprung.

### 6.3 Focus-Trap im Video-Modal (mit `@alpinejs/focus`)

```html
<div x-data="{ open: false }"
     @keydown.escape.window="open = false">
  <button @click="open = true">Video oeffnen</button>

  <div x-show="open"
       x-trap.inert.noscroll="open"
       role="dialog" aria-modal="true" aria-label="Video"
       x-cloak>
    <button @click="open = false" aria-label="Schliessen">&times;</button>
    <iframe :src="open ? videoUrl : ''" allowfullscreen></iframe>
  </div>
</div>
```
`x-trap` (Focus-Plugin) ersetzt die ~30 LOC Focus-Trap-Logik in `dhps-tp.js` (Zeile 228-258).

### 6.4 `prefers-reduced-motion` als Store

```js
// dhps-alpine-init.js
document.addEventListener( 'alpine:init', function () {
    Alpine.store( 'dhpsUi', {
        reducedMotion: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches,
        init() {
            window.matchMedia( '(prefers-reduced-motion: reduce)' )
                .addEventListener( 'change', e => this.reducedMotion = e.matches );
        }
    } );
} );
```

```html
<div x-data
     x-transition:enter="$store.dhpsUi.reducedMotion ? '' : 'dhps-fade-in'"
     x-show="open">
  ...
</div>
```

### 6.5 Roving-Tabindex fuer Filter-Pills

```html
<div role="toolbar" aria-label="Themen-Filter"
     x-data="dhpsRovingFilter(['alle','steuer','recht'])"
     @keydown.right.prevent="next()"
     @keydown.left.prevent="prev()">
  <template x-for="(t, i) in topics" :key="t">
    <button type="button"
            :tabindex="i === active ? 0 : -1"
            :aria-pressed="(i === active).toString()"
            @click="active = i; $dispatch('dhps-filter', { topic: t })"
            @focus="active = i"
            x-text="t"></button>
  </template>
</div>
```
Pfeil-Navigation analog zu WAI-ARIA Authoring Practices "Toolbar"-Pattern.

---

## 7. Risiken + Mitigation

| Risiko | Wahrscheinlichkeit | Auswirkung | Mitigation |
|--------|--------------------|------------|------------|
| Theme laedt eigenes Alpine v2 (inkompatibel) | gering | Beide Alpines crashen | Detection: Wenn `window.Alpine.version` < 3, Warning loggen, eigene Init abbrechen, Vanilla-Fallback weiterlaufen lassen. |
| Elementor Pro JS-Optimization minifiziert Alpine doppelt | mittel | Selektoren brechen | `dhps-alpine`-Handle in Elementor-Optimierung-Ausschlussliste dokumentieren; oder Build mit fixem Hash deployen. |
| CSP-Header verbietet inline `x-data` | gering | Alpine startet, aber `x-data="{...}"` schlaegt fehl wegen `unsafe-eval` | Komponenten ausschliesslich ueber `Alpine.data('dhpsFoo', ...)` definieren; in HTML nur `x-data="dhpsFoo()"`-Funktionsreferenz. Damit kein `eval`. |
| Pagebuilder-Drag-and-Drop dupliziert `x-data`-Subtree | gering | Doppelt initialisierte Komponente | Alpine v3 erkennt `_x_dataStack` und initialisiert nicht doppelt - keine zusaetzliche Massnahme. |
| Bundle waechst durch unbedacht viele Plugins | mittel | TTI verschlechtert | Nur Core + `focus`-Plugin in v0.14.0. `persist`, `intersect`, `morph` nur auf konkreten Bedarf hin. Budget: max 25 KB gz total. |
| Migration bricht bestehende Vanilla-Selektoren | mittel | Layout-/Funktion-Regression | Hybrid-Strategie (Sektion 5) - kein bestehender Code wird angefasst, nur neue Bereiche bekommen Alpine. Visual-Regression-Test bei jedem Schritt. |
| Suchmaschinen-Crawler sehen `x-cloak`-leere Container | gering | SEO-Impact bei kritischen Inhalten | Service-Content ist bereits serverseitig (Pipeline -> Renderer -> Template) gerendert; Alpine ist nur fuer Interaktivitaet. Initial-HTML bleibt vollstaendig. |
| `defer` greift bei aelteren Themes ohne `wp_head`/`wp_footer` nicht | gering | Alpine laedt synchron | `script_loader_tag`-Filter (siehe Sektion 3) erzwingt `defer`. |

**Akzeptierte Trade-offs:**
- ~16 KB zusaetzliches JS auf Seiten mit DHPS-Shortcodes (DSGVO-konform, lokal gehosted).
- Lernkurve fuer das Team (Alpine-Direktiven sind aber nahe an HTML-Standard).

---

## Quellen

- Alpine.js Offizielle Dokumentation: https://alpinejs.dev/start-here
- Alpine.js Plugins (Focus, Persist, Intersect): https://alpinejs.dev/plugins
- WAI-ARIA Authoring Practices Toolbar: https://www.w3.org/WAI/ARIA/apg/patterns/toolbar/
- WordPress Plugin Handbook - Including JavaScript: https://developer.wordpress.org/plugins/javascript/
- `wp_enqueue_script()` Referenz: https://developer.wordpress.org/reference/functions/wp_enqueue_script/
- MDN `defer`-Attribut-Semantik: https://developer.mozilla.org/en-US/docs/Web/HTML/Element/script
- Eigenes Plugin: `Deubner_HP_Services.php` (Zeile 325-383), `public/js/dhps-mio.js`, `public/js/dhps-mmb.js`, `public/js/dhps-tp.js`
