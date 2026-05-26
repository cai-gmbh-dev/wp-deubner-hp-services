# Content Security Policy (CSP) Compatibility

Stand: 2026-05-26 (v0.15.4 - aktualisiert um Live-Preview-iframe + postMessage-Resize)

## Zusammenfassung

Das Plugin "Deubner HP Services" funktioniert mit einer streng konfigurierten
Content Security Policy, benoetigt aber zwei Direktiven, damit Alpine.js v3.x
ordnungsgemaess laeuft. Diese Direktiven sind Framework-Limitationen, nicht
Plugin-spezifisch.

## Erforderliche CSP-Direktiven

### `script-src 'self' 'unsafe-eval'`

**Warum?** Alpine.js v3 verwendet die `Function`-Konstruktor-API, um die in
`x-data`, `x-show`, `x-on:`, `x-bind:` etc. enthaltenen JavaScript-Ausdruecke
auszuwerten. Das erfordert `unsafe-eval`.

Alternative: Alpine v4 (Stand 2026-05: in Beta) plant CSP-strict mode. Bis dahin
ist `unsafe-eval` Pflicht.

**Affected:**

- `public/js/vendor/alpinejs-3.14.x.min.js` (Vendor)
- `public/js/dhps-alpine-init.js` (Init)
- `public/js/dhps-components-alpine.js` (Stateful Components)

### `style-src 'self' 'unsafe-inline'`

**Warum?** Alpine.js setzt waehrend `x-show`/`x-transition` Inline-`style`-Attribute
zur Laufzeit. Browser werten diese als "Inline-Styles" und verlangen
`unsafe-inline`.

Plus: Drei Plugin-Templates verwenden statische Inline-Styles:

- `public/views/components/pagination.php` - `style="--current-page: 1"`
  (CSS-Custom-Property aus PHP-Int gecastet)
- `public/views/components/content-list.php` - `style="--cols: 2"` (CSS-Var
  aus PHP-Int gecastet)
- `public/views/components/lazy-image.php` - `style="background-image: url('LQIP')"`
  (LQIP Data-URL via esc_attr)

Alle Werte sind type-cast oder escaped (kein User-Input).

### `connect-src 'self'`

**Warum?** Der MMB-AJAX-Endpoint `dhps_mmb_category_load` wird via
`fetch()` aus `dhps-mmb.js` aufgerufen. Ziel-URL ist `admin-ajax.php`
auf derselben Domain.

### `frame-src 'self' about:` (nur Admin-Dashboard, seit v0.15.3)

**Warum?** Das Admin-Dashboard-Live-Preview (seit v0.15.3) zeigt
gerenderte Frontend-Vorschau in einem iframe mit `srcdoc`. Browser
behandeln `srcdoc`-iframes als `about:srcdoc`-Origin - mit striktem
CSP `frame-src 'self'` blockt der Browser den iframe.

Loesung: `frame-src 'self' about:` oder breiter. Wenn Live-Preview nicht
genutzt wird (oder CSP nur fuer Frontend gilt, nicht WP-Admin), kann diese
Direktive entfallen.

**Affected:** Nur `admin/js/dhps-admin-react.js` -> LivePreviewIframe-Component.

### postMessage-Resize (seit v0.15.4)

Das iframe sendet seine berechnete Hoehe via `postMessage` an den Parent.
Das ist KEINE CSP-Direktive, aber Information fuer Site-Admins:

- iframe-Origin: `about:srcdoc` -> `event.origin === 'null'` im Parent
- Origin-Check via `event.origin` ist NICHT moeglich (W3C-Spec)
- Mitigation: Type-Check (`event.data.type === 'dhps-preview-resize'`)
  + Bounds-Check (1-4000 px) + Max-Height-Cap
- Kein User-Input-Pfad (Hoehe wird im iframe-eigenen JS berechnet)

## Empfohlene CSP-Header

Fuer eine Site, die das Plugin im Frontend einsetzt:

```
Content-Security-Policy:
    default-src 'self';
    script-src 'self' 'unsafe-eval';
    style-src 'self' 'unsafe-inline';
    img-src 'self' https://*.deubner-online.de data:;
    connect-src 'self';
    font-src 'self' data:;
    frame-src https://www.youtube.com https://player.vimeo.com;
    base-uri 'self';
    form-action 'self';
```

### Admin-Kontext: zusaetzliche `frame-src about:` (seit v0.15.3)

Wenn CSP auch fuer `/wp-admin/*` greift und Live-Preview genutzt wird,
muss `frame-src` um `about:` erweitert werden (iframe-srcdoc-Origin):

```
Content-Security-Policy:
    default-src 'self';
    script-src 'self' 'unsafe-eval';
    style-src 'self' 'unsafe-inline';
    img-src 'self' https://*.deubner-online.de data:;
    connect-src 'self';
    font-src 'self' data:;
    frame-src 'self' about:;
    base-uri 'self';
    form-action 'self';
```

Ohne `about:` in `frame-src` zeigt der Browser den Live-Preview-iframe
NICHT an (DevTools-Console-Error: "Refused to frame ... because it
violates Content-Security-Policy"). Caveat C3 v0.15.4 GELOEST in v0.15.5.

Anpassungen je nach Site-Kontext (z.B. fuer Elementor-Editor weitere
Domains noetig, fuer YouTube-Video-Embeds in TP/MAES `frame-src` erweitern).

## Was das Plugin NICHT braucht

- `object-src` (das Plugin embedded keine `<object>` oder `<embed>`)
- `child-src` separat (frame-src reicht)
- `worker-src` (kein ServiceWorker)
- `font-src` von externen Domains (Fonts kommen vom Theme/Site, nicht vom Plugin)

## Vorhandene Defense-in-Depth-Massnahmen

Das Plugin ist defensiv konstruiert, sodass auch ohne strict CSP die folgenden
Angriffsvektoren mitigiert sind:

- **Keine Inline-`<script>`-Bloecke** in Templates (alle JS via wp_enqueue_script)
- **Keine eval()** in eigenen JS-Files (nur Alpine intern)
- **Component-Templates** escapen alle Outputs via esc_html / esc_attr /
  esc_url / wp_kses_post (Audit-Dokument 11-SECURITY-AUDIT-v0140.md)
- **AJAX-Endpoint** mit Nonce + Whitelist + Rate-Limit + serverseitiger Auth
- **Vendor-Pinning** Alpine.js-Hash in `.alpinejs-version` festgeschrieben

## Migration zu strikterem CSP in v0.15.0+

Geplante Verbesserungen:

- Wenn Alpine v4 stable und CSP-strict-Modus verfuegbar: Vendor-Update auf v4
  ermoeglicht Entfernung von `unsafe-eval`
- Inline-`style="--var: ..."` koennen mittels `class="dhps-cols-2"` Patterns
  durch Klassenvarianten ersetzt werden -> Entfernung von `unsafe-inline`

Bis dahin sind die o.g. Direktiven Plugin-Anforderung.

## Test der CSP-Konformitaet

```bash
# Browser DevTools -> Console: CSP-Violations werden geloggt.
# Test mit gesetztem strict CSP:
curl -I https://deine-site.tld | grep -i content-security-policy
```

Browser-Test: Mit Plugin-Shortcode auf der Seite und obiger CSP-Header sollten
ueblicherweise 0 Violations in der Console erscheinen. Falls doch: bitte
Issue oeffnen mit DevTools-Screenshot.
