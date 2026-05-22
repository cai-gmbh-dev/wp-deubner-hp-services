# Content Security Policy (CSP) Compatibility

Stand: 2026-05-22 (v0.14.0)

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
