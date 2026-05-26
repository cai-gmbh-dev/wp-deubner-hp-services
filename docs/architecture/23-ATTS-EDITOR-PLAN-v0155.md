# Atts-Editor-Plan v0.15.5 (Discovery)

## Stand: 2026-05-26
## Status: Architektur-Vorschlag - umzusetzen in v0.15.5
## Zielversion: v0.15.5 - Voller Atts-Editor + 4 Nice-to-Have-Caveats (C1-C4)
## Plattform: WP 6.9.4, Elementor 4.0.1, PHP 8.3.30, React 18 (wp.element)
## Vorgaenger-Plan: docs/architecture/21-LIVE-PREVIEW-PLAN-v0153.md
## Triage-Quelle: docs/architecture/22-TECH-DEBT-TRIAGE-v0154.md
## Lehre v0.15.0/v0.15.3: Schema-Vertrag MUSS Pflicht-Bestandteil JEDES Specs sein.

---

## Executive Summary

v0.15.4 hat die 4 Sub-Shortcodes (mio_termine, maes_videos, maes_merkblaetter,
maes_aktuelles) preview-faehig gemacht - jedoch nur mit den 4 generischen Atts
(`layout`, `class`, `section`, `cache`). Die service-spezifischen Atts wie
`einzelvideo`, `videoliste`, `columns`, `count`, `month`, `id_merkblatt`,
`rubrik`, `teasermodus`, `filter`, `variante` etc. werden aktuell silent
rejected (in `atts_rejected` als "unknown att key").

Ticket 7 (gross) erweitert das Atts-Editor-Konzept um service-spezifische
Atts, dynamische UI-Generierung im Frontend, Schema-Endpoint im Backend
und Validation-Whitelist-Erweiterung. Caveats C1-C4 sind Easy-/Medium-Wins.

**Empfohlene Spec-Aufteilung**: 1 kombinierter F1+F2-Spec mit Pflicht-
Schema-Vertrag (analog v0.15.4-Lehre) + Lead-Easy-Wins fuer C2/C3/C4.
C1 wird in F1 mit-erledigt (gemeinsame Atts-Schema-Registry).

**Schema-Endpoint-Pattern**: **Option A (Statisches Atts-Schema in PHP-Const)
+ Wp_localize_script-Bridge** - bestes Verhaeltnis aus Performance, BC und
Wartbarkeit. Schema einmal pro Pageload als JSON ans Frontend, kein
zusaetzlicher REST-Roundtrip noetig.

---

## Sektion 1: Service-Atts-Inventar

### 1.1 Hauptservices (aus DHPS_Service_Registry::get_services())

#### MIO (Service-Slug: `mio`)

| Att | Typ | Default | Erlaubte Werte | Beschreibung |
|-----|-----|---------|----------------|--------------|
| `teasermodus` | string | `''` | `''`, `0`, `1` | Teaser-Anzeige (1 = nur Teaser, 0 = volle Liste) |
| `filter` | string | `''` | freier Text (sanitize_text_field) | Volltext-Filter fuer News-Liste |
| `variante` | select | `''` | `tagesaktuell`, `kategorisiert`, `''` | Anzeigevariante (nur wenn dhps_variante=0) |
| `modus` | string | `''` | `p`, `t` | API-Modus (selten in Shortcode noetig) |
| `st_kategorie` | string | `''` | freier Text | Steuer-Kategorie-Filter |
| `layout` | select | `default` | `default`, `card`, `compact` | Layout-Variante (universal) |
| `class` | string | `''` | sanitize_html_class | Optionale CSS-Klasse (universal) |
| `cache` | int-as-string | `3600` | 0..86400 | Cache-TTL in Sekunden (universal) |

#### LXMIO (Service-Slug: `lxmio`)

Identisch zu MIO (gleiches `shortcode_atts`-Array). Branding `recht`.

#### MMB (Service-Slug: `mmb`)

| Att | Typ | Default | Erlaubte Werte | Beschreibung |
|-----|-----|---------|----------------|--------------|
| `id_merkblatt` | string | `''` | numerische ID oder leer | Einzelnes Merkblatt anzeigen |
| `rubrik` | string | `''` | freier Text | Rubrik-Filter (sanitize_text_field) |
| `layout` | select | `default` | `default`, `card`, `compact` | universal |
| `class` | string | `''` | sanitize_html_class | universal |
| `cache` | int-as-string | `3600` | 0..86400 | universal |

#### MIL (Service-Slug: `mil`)

Identisch zu MMB (`id_merkblatt`, `rubrik`, `layout`, `class`, `cache`).

#### TP (Service-Slug: `tp`)

| Att | Typ | Default | Erlaubte Werte | Beschreibung |
|-----|-----|---------|----------------|--------------|
| `teasermodus` | string | `0` | `0`, `1` | Teaser-Modus (1 = TPT-aehnlicher Teaser) |
| `einzelvideo` | string | `0` | 0..999 (Index) | Einzelnes Video (1-basiert) |
| `videoliste` | string | `''` | CSV von Indizes (z.B. `1,3,5`) | Mehrere Videos selektieren |
| `layout` | select | `default` | `default`, `card`, `compact` | universal |
| `class` | string | `''` | sanitize_html_class | universal |
| `cache` | int-as-string | `3600` | 0..86400 | universal |

#### TPT (Service-Slug: `tpt`)

| Att | Typ | Default | Erlaubte Werte | Beschreibung |
|-----|-----|---------|----------------|--------------|
| `layout` | select | `default` | `default`, `card`, `compact` | universal |
| `modus` | select | `''` | `standard`, `p`, `t`, `pt`, `''` | TPT-Anzeigemodus (Admin-Default greift wenn leer) |
| `class` | string | `''` | sanitize_html_class | universal |
| `cache` | int-as-string | `3600` | 0..86400 | universal |

Hinweis: `breite` ist Admin-Option (`dhps_tpt_breite`), kein Shortcode-Att.

#### TC (Service-Slug: `tc`)

| Att | Typ | Default | Erlaubte Werte | Beschreibung |
|-----|-----|---------|----------------|--------------|
| `layout` | select | `default` | `default`, `card`, `compact` | universal |
| `class` | string | `''` | sanitize_html_class | universal |
| `cache` | int-as-string | `3600` | 0..86400 | universal |

Hinweis: TC ist Wrapper-Parser, hat keine sinnvollen service-spezifischen
Atts (Rechner-Liste kommt aus API). Nur generische Atts.

#### MAES (Service-Slug: `maes`)

| Att | Typ | Default | Erlaubte Werte | Beschreibung |
|-----|-----|---------|----------------|--------------|
| `section` | select | `''` | `''`, `videos`, `merkblaetter`, `aktuelles` | Sub-Sektion oder Vollanzeige |
| `layout` | select | `default` | `default`, `card`, `compact` | universal |
| `class` | string | `''` | sanitize_html_class | universal |
| `cache` | int-as-string | `3600` | 0..86400 | universal |

#### LP (Service-Slug: `lp`)

| Att | Typ | Default | Erlaubte Werte | Beschreibung |
|-----|-----|---------|----------------|--------------|
| `videoliste` | int-as-string | `0` | 0..999 oder CSV | Video-Index oder CSV-Liste |
| `teasermodus` | int-as-string | `0` | `0`, `1` | Teaser-Modus |
| `show_teaser` | int-as-string | `1` | `0`, `1` | Teaser-Anzeige aktivieren |
| `filter` | string | `''` | freier Text | Volltext-Filter |
| `layout` | select | `default` | `default`, `card`, `compact` | universal |
| `class` | string | `''` | sanitize_html_class | universal |
| `cache` | int-as-string | `3600` | 0..86400 | universal |

### 1.2 Sub-Shortcodes (aus class-dhps-steuertermine.php + class-dhps-maes-modules.php)

#### mio_termine

| Att | Typ | Default | Erlaubte Werte | Beschreibung |
|-----|-----|---------|----------------|--------------|
| `count` | int-as-string | `0` | 0..50 | 0 = alle Eintraege, N = erste N pro Monat |
| `month` | select | `all` | `current`, `next`, `all` | Monatsfilter |
| `layout` | select | `default` | `default`, `card`, `inline`, `compact` | (zusaetzlich `inline`) |
| `class` | string | `''` | sanitize_html_class | universal |
| `cache` | int-as-string | `3600` | 0..86400 | universal |

#### maes_videos

| Att | Typ | Default | Erlaubte Werte | Beschreibung |
|-----|-----|---------|----------------|--------------|
| `columns` | int-as-string | `2` | 1..4 | Spaltenanzahl im Grid |
| `einzelvideo` | int-as-string | `0` | 0..999 | Einzelvideo (1-basiert) |
| `videoliste` | string | `''` | CSV von Indizes | Video-Index-Liste |
| `lazy_count` | int-as-string | `0` | 0..50 | Initial sichtbare Videos vor Lazy-Load |
| `lazy_mode` | select | `manual` | `manual`, `auto` | Lazy-Trigger-Mode |
| `layout` | select | `default` | `default`, `card`, `compact` | universal |
| `class` | string | `''` | sanitize_html_class | universal |
| `cache` | int-as-string | `3600` | 0..86400 | universal |

#### maes_merkblaetter

| Att | Typ | Default | Erlaubte Werte | Beschreibung |
|-----|-----|---------|----------------|--------------|
| `layout` | select | `default` | `default`, `card`, `compact` | universal |
| `class` | string | `''` | sanitize_html_class | universal |
| `cache` | int-as-string | `3600` | 0..86400 | universal |

Hinweis: keine service-spezifischen Atts (User-Request listete `kategorie`,
`count` - das ist Wunschliste, im Code nicht implementiert).

#### maes_aktuelles

| Att | Typ | Default | Erlaubte Werte | Beschreibung |
|-----|-----|---------|----------------|--------------|
| `columns` | int-as-string | `2` | 1..4 | Spaltenanzahl im Grid |
| `layout` | select | `default` | `default`, `card`, `compact` | universal |
| `class` | string | `''` | sanitize_html_class | universal |
| `cache` | int-as-string | `3600` | 0..86400 | universal |

Hinweis: User-Request listete `count`, `show_teaser` - im Code nicht
implementiert (interne Code-Defaults `show_teaser=true`, `first_open=false`).

### 1.3 Abweichungs-Notiz (User-Briefing vs Code)

Das User-Briefing erwaehnt:
- MIO: `count`, `kategorie`, `start_date`, `end_date` - **NICHT im Code**. MIO hat `teasermodus`, `filter`, `variante`, `modus`, `st_kategorie`.
- MMB: `kategorie`, `suche` - **NICHT im Code**. MMB hat `id_merkblatt`, `rubrik`.
- TP: `kategorie`, `featured_id`, `video_mode (inline/modal)` - **NICHT im Code**. TP hat `teasermodus`, `einzelvideo`, `videoliste`.
- TPT: `teaser_id`, `breite` - **NICHT im Code als Shortcode-Att**. `breite` ist Admin-Option.
- mio_termine: `monat`, `jahr` - **NICHT im Code**. Code hat `count`, `month`.
- maes_videos: `lazy_count`, `lazy_mode` - **IM Code vorhanden** ✓
- maes_merkblaetter: `kategorie`, `count` - **NICHT im Code**.
- maes_aktuelles: `count`, `show_teaser` - **NICHT im Code** (intern hardcoded).

**Entscheidung v0.15.5**: Nur die im Code TATSAECHLICH unterstuetzten Atts
ins Schema aufnehmen. Wishlist-Atts erfordern erst Shortcode-Handler-
Erweiterung (Ticket fuer v0.16.0 - "einheitliches Datenmodell").

---

## Sektion 2: Schema-Endpoint-Pattern (A/B/C Empfehlung)

### 2.1 Option A: Statisches Atts-Schema in PHP-Const

```php
class DHPS_Preview_Renderer {
    public const SERVICE_ATTS_SCHEMA = array(
        'mio' => array(
            'teasermodus' => array( 'type' => 'select', 'default' => '', 'options' => array('', '0', '1') ),
            'filter'      => array( 'type' => 'string', 'default' => '', 'sanitize' => 'text_field' ),
            // ...
        ),
        // ...
    );
}
```

**PRO:**
- Eindeutige Source-of-Truth im Backend.
- Keine Runtime-Database-Reads.
- Statische Analyse moeglich (IDE-Hints).
- Via `wp_localize_script` an Frontend exposed -> kein REST-Roundtrip.
- Direkter Lookup `SERVICE_ATTS_SCHEMA[$service]` ohne Service-Registry-Mutation.

**CON:**
- Liste ist hartcodiert (aber das ist auch SUB_SHORTCODE_PARENTS und ALLOWED_LAYOUTS).
- Aenderungen erfordern Code-Deploy (akzeptiert - Atts aendern sich selten).

### 2.2 Option B: Dynamisches Schema via REST-Endpoint

```
GET /dhps/v1/services/{service}/atts
```

**PRO:**
- Trennung Backend/Frontend ueber REST-Vertrag.
- Frontend kann lazy-load (nur Schema fuer aktiven Service).

**CON:**
- Zusaetzlicher REST-Roundtrip bei jedem Service-Wechsel.
- Spuerbare Latenz im UI (50-150ms zusaetzlich).
- Mehr Code (neue Route + Rate-Limit + Cache).
- Bei 13 Services + jedes Mal Service-Wechsel: viele Roundtrips.
- Schema-Drift-Risiko erhoeht sich (Liste ist Runtime-getrennt vom Validator).

### 2.3 Option C: Atts-Schema im Health-Endpoint mitliefern

```json
{
  "service": "mio",
  "label": "...",
  "ota_set": true,
  "atts_schema": { ... }  // NEU
}
```

**PRO:**
- Kein neuer Endpoint.
- Schema kommt zusammen mit Health.

**CON:**
- Mischt Concerns (Health != Schema).
- Health-Response wird ~2x groesser.
- Schema fuer ALLE Services kommt bei jedem Health-Refresh - Overhead.

### 2.4 Empfehlung: **Option A** (Statisch + wp_localize_script-Bridge)

**Begruendung:**
1. **Performance**: Schema kommt EINMAL pro Pageload (mit React-Bundle), kein REST-Roundtrip bei Service-Wechsel.
2. **DRY**: Selbe Konstante wird vom Validator (Backend) UND als JSON-Brigde (Frontend) genutzt -> 0 Schema-Drift.
3. **Konsistent mit bestehender Architektur**: `ALLOWED_LAYOUTS`, `ALLOWED_MAES_SECTIONS`, `SUB_SHORTCODE_PARENTS`, `SERVICE_JS_MAP` sind alle hartcodiert.
4. **Synergie mit Caveat C4**: wp_localize_script-Bridge wird ohnehin gebaut (siehe Sektion 7.4) - Schema kann einfach mitfliegen.
5. **BC**: Vollstaendig additiv - Frontend kann das Schema-Objekt schrittweise konsumieren.

**Implementierungs-Skizze:**

```php
// includes/class-dhps-preview-renderer.php
public const SERVICE_ATTS_SCHEMA = array(
    'mio' => array(
        'teasermodus' => array(
            'type'    => 'select',
            'default' => '',
            'options' => array(
                array( 'value' => '',  'label' => '(default)' ),
                array( 'value' => '0', 'label' => 'Volle Liste' ),
                array( 'value' => '1', 'label' => 'Nur Teaser' ),
            ),
            'group'   => 'service_specific',
        ),
        // ...
    ),
    // ...
    'maes_videos' => array(
        'columns' => array(
            'type'    => 'int',
            'default' => 2,
            'min'     => 1,
            'max'     => 4,
            'group'   => 'service_specific',
        ),
        'einzelvideo' => array(
            'type'    => 'int',
            'default' => 0,
            'min'     => 0,
            'max'     => 999,
            'group'   => 'service_specific',
        ),
        // ...
    ),
);

// Deubner_HP_Services.php (Enqueue-Funktion)
wp_localize_script( 'dhps-admin-react', 'dhpsAdminBridge', array(
    'services'    => DHPS_Admin_REST::ALLOWED_SERVICES,
    'attsSchema'  => DHPS_Preview_Renderer::SERVICE_ATTS_SCHEMA,
    'subParents'  => DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS,
    'restNonce'   => wp_create_nonce( 'wp_rest' ),
    'restRoot'    => esc_url_raw( rest_url() ),
    'version'     => DEUBNER_HP_SERVICES_VERSION,
) );
```

---

## Sektion 3: Schema-Vertrag (KRITISCH - 10 Felder + 5 Error-Codes)

### 3.1 Atts-Schema-Response-Vertrag (autoritativ, KEINE Synonyme)

`DHPS_Preview_Renderer::SERVICE_ATTS_SCHEMA[$service][$att_name]` ist ein
assoziatives Array mit exakt diesen Feldern (Schema-Vertrag-Pflicht).

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|--------------|
| `type` | string | ja | Einer von `string`, `int`, `bool`, `select` |
| `default` | scalar | ja | Default-Wert (passend zu `type`) |
| `options` | array | nein | Bei `type=select`: Liste von `{value, label}`-Objekten |
| `min` | int | nein | Bei `type=int`: Untere Grenze (inkl.) |
| `max` | int | nein | Bei `type=int`: Obere Grenze (inkl.) |
| `pattern` | string | nein | Bei `type=string`: Regex-Validation (PHP-Notation) |
| `sanitize` | string | nein | Sanitize-Hint (`text_field`, `html_class`, `key`, `csv_int`) |
| `group` | string | ja | `universal` oder `service_specific` (UI-Gruppierung) |
| `label` | string | nein | Human-readable Label (Default = `att_name`) |
| `description` | string | nein | Tooltip-/Hilfe-Text |

**Verbindlich:**
- `type` ist immer einer der 4 Strings.
- `default` ist immer gesetzt, niemals null.
- `group` ist immer `universal` ODER `service_specific`.
- `options` ist ein Array von Objekten `{ "value": string, "label": string }`,
  niemals ein flaches Array von Strings.
- Bei `type=int`: `min` und `max` sind verbindlich (Bounds-Pflicht).

**KEINE Aliases erlaubt** (nicht: `kind`/`fieldType`, nicht: `dflt`/`def`,
nicht: `vals`/`choices`, nicht: `bounds`).

### 3.2 Beispiel-Response (autoritativ)

```json
{
  "mio": {
    "teasermodus": {
      "type": "select",
      "default": "",
      "options": [
        { "value": "",  "label": "(default)" },
        { "value": "0", "label": "Volle Liste" },
        { "value": "1", "label": "Nur Teaser" }
      ],
      "group": "service_specific",
      "label": "Teaser-Modus"
    },
    "filter": {
      "type": "string",
      "default": "",
      "sanitize": "text_field",
      "group": "service_specific",
      "label": "Filter (Volltext)"
    },
    "layout": {
      "type": "select",
      "default": "default",
      "options": [
        { "value": "default", "label": "default" },
        { "value": "card",    "label": "card" },
        { "value": "compact", "label": "compact" }
      ],
      "group": "universal"
    },
    "class": {
      "type": "string",
      "default": "",
      "sanitize": "html_class",
      "pattern": "^[a-zA-Z0-9_\\- ]{0,64}$",
      "group": "universal"
    },
    "cache": {
      "type": "int",
      "default": 3600,
      "min": 0,
      "max": 86400,
      "group": "universal"
    }
  }
}
```

### 3.3 Atts-Reject-Vertrag (autoritativ, unveraendert seit v0.15.4)

`atts_rejected` bleibt Object{key: reason} (Schema-Drift v0.15.4 dokumentiert
in T4 unveraendert). Neue Reject-Reasons fuer v0.15.5:

| Reject-Reason | Bedeutung |
|----------------|-----------|
| `value not in whitelist` | (bestehend) Select-Option ausserhalb erlaubter Werte |
| `invalid html-class chars` | (bestehend) class-Att hat ungueltige Zeichen |
| `only allowed for maes` | (bestehend) section-Att fuer Nicht-MAES |
| `value not boolean (0\|1)` | (bestehend) cache-Att kein Boolean |
| `unknown att key` | (bestehend) Att-Key nicht in SERVICE_ATTS_SCHEMA |
| `out of bounds (min=N, max=M)` | (NEU) int-Att ausserhalb min/max |
| `invalid type (expected int)` | (NEU) Wert nicht numerisch bei type=int |
| `pattern mismatch` | (NEU) string-Att matched `pattern`-Regex nicht |
| `not allowed for service` | (NEU) Att existiert im Schema, aber nicht fuer diesen Service |

### 3.4 Error-Codes (REST-Antwort) - 6 Codes (bisherige 5 + 1 NEU)

Standard WP_Error-JSON:

```json
{
  "code": "invalid_service",
  "message": "Unbekannter Service.",
  "data": { "status": 400 }
}
```

| Code | HTTP | Bedeutung |
|------|------|-----------|
| `invalid_service` | 400 | Slug nicht in ALLOWED_SERVICES |
| `service_not_configured` | 400 | OTA/kdnr fehlt |
| `invalid_endpoint` | 404 | Service hat keinen Endpoint in Registry |
| `invalid_format` | 400 | Format != "iframe" (seit v0.15.4) |
| `rate_limit_exceeded` | 429 | 30-Requests/min-Bucket voll |
| `preview_render_failed` | 500 | do_shortcode-Exception oder leerer Output |

Keine neuen Codes in v0.15.5 (Atts-Reject ist non-fatal: rejected-Atts
landen in `atts_rejected`, Preview-Rendering geht weiter).

### 3.5 Compliance-Check vor Release (Pflicht)

Pre-Release-Smoke:
1. `SERVICE_ATTS_SCHEMA` hat Eintrag fuer ALLE 13 Slugs in ALLOWED_SERVICES.
2. Jeder Att-Eintrag hat die 3 Pflichtfelder `type`, `default`, `group`.
3. Bei `type=select`: `options` ist Array von Objekten, NICHT flache Strings.
4. Bei `type=int`: `min` und `max` sind definiert.
5. `wp_localize_script` exposed `dhpsAdminBridge.attsSchema` mit identischem Inhalt.
6. Frontend-Smoke: `console.log(window.dhpsAdminBridge.attsSchema.mio.layout)` liefert `{ type: "select", default: "default", options: [...], group: "universal" }`.
7. Smoke-Test fuer Bounds: `POST /preview` mit `atts.count=200` -> Response enthaelt `atts_rejected.count = "out of bounds (min=0, max=50)"`.

---

## Sektion 4: Frontend-UI-Generierung (Komponenten-Aufteilung)

### 4.1 Bestehender LivePreviewControls (v0.15.4)

`LivePreviewControls` rendert aktuell hartcodiert:
- Service-Dropdown (SelectControl)
- Layout-Dropdown (SelectControl, statisch PREVIEW_LAYOUTS)
- CSS-Class TextControl
- MAES-Section-Dropdown (conditional, statisch PREVIEW_MAES_SECTIONS)
- Run-Button

### 4.2 Erweiterung in v0.15.5 (additiv)

Neue Sub-Komponente: `LivePreviewAttsForm`. Wird VOR dem Run-Button gerendert,
unterhalb der universal-Atts.

```
LivePreviewControls
+-- (Service-Dropdown)               [bestehend, unveraendert]
+-- (universal-Atts: layout, class)  [bestehend, unveraendert]
+-- (cache-Toggle)                   [NEU - v0.15.5]
+-- LivePreviewAttsForm              [NEU - v0.15.5, service-spezifisch]
|   +-- AttFieldString               [TextControl]
|   +-- AttFieldInt                  [TextControl + parseInt]
|   +-- AttFieldBool                 [ToggleControl]
|   +-- AttFieldSelect               [SelectControl]
+-- (MAES-Section-Dropdown)          [bestehend, conditional]
+-- (Sub-Shortcode-Badge)            [bestehend, conditional]
+-- (Run-Button + Reset-Button NEU)  [Reset zurueck auf Defaults]
```

### 4.3 LivePreviewAttsForm-Logik

```js
function LivePreviewAttsForm( props ) {
    var service = props.service;
    var atts = props.atts;
    var onChange = props.onChange;

    // Schema aus localized Bridge.
    var schema = ( window.dhpsAdminBridge
                && window.dhpsAdminBridge.attsSchema
                && window.dhpsAdminBridge.attsSchema[ service ] )
        || {};

    // Nur service_specific-Atts hier rendern - universal sind extern.
    var serviceAtts = Object.keys( schema ).filter( function ( key ) {
        return schema[ key ].group === 'service_specific';
    } );

    if ( serviceAtts.length === 0 ) {
        return null; // tc, maes_merkblaetter -> keine service-spezifischen Atts
    }

    return h( 'div', { className: 'dhps-react-atts-form' },
        serviceAtts.map( function ( attName ) {
            var attDef = schema[ attName ];
            return renderAttField( attName, attDef, atts[ attName ], onChange );
        } )
    );
}

function renderAttField( name, def, value, onChange ) {
    var current = ( value !== undefined ) ? value : def.default;
    var label = def.label || name;

    switch ( def.type ) {
        case 'string':
            return h( TextControl, {
                key: name,
                label: label,
                value: String( current || '' ),
                onChange: function ( val ) { onChange( name, val ); },
                help: def.description || null,
            } );
        case 'int':
            return h( TextControl, {
                key: name,
                label: label + ' (' + def.min + '..' + def.max + ')',
                type: 'number',
                min: def.min,
                max: def.max,
                value: String( current || def.default ),
                onChange: function ( val ) { onChange( name, val ); },
                help: def.description || null,
            } );
        case 'bool':
            return h( ToggleControl, {
                key: name,
                label: label,
                checked: !! current,
                onChange: function ( val ) { onChange( name, val ? '1' : '0' ); },
                help: def.description || null,
            } );
        case 'select':
            return h( SelectControl, {
                key: name,
                label: label,
                value: String( current || '' ),
                options: def.options,
                onChange: function ( val ) { onChange( name, val ); },
                help: def.description || null,
            } );
        default:
            return null;
    }
}
```

### 4.4 Service-Wechsel-Logik (Atts-Reset)

Wenn der Admin den Service wechselt, MUESSEN service-spezifische Atts auf
Defaults zurueckgesetzt werden (sonst landen alte Atts im neuen Schema und
werden als "unknown att key" rejected).

```js
function onServiceChange( newService ) {
    setService( newService );
    var schema = window.dhpsAdminBridge.attsSchema[ newService ] || {};
    var newAtts = {};
    Object.keys( schema ).forEach( function ( k ) {
        newAtts[ k ] = schema[ k ].default;
    } );
    setAtts( newAtts );
}
```

### 4.5 Reset-Button

Neuer Button rechts neben "Vorschau laden":
- Label: `Atts zuruecksetzen`
- variant: `tertiary`
- onClick: ruft `onServiceChange( service )` mit aktuellem Service -> resettet auf Defaults.

### 4.6 UI-Mockup

```
+---------------------------------------------------------------+
| Live-Preview                                                  |
+---------------------------------------------------------------+
| Service:  [Dropdown: mio v]   Layout: [default v]             |
| CSS-Class: [_____________]    Cache: [x]                      |
+---------------------------------------------------------------+
| Service-spezifische Atts (mio):                               |
|   Teaser-Modus: [(default) v]                                 |
|   Filter (Volltext): [_____________]                          |
|   Variante: [(default) v]                                     |
|   Modus: [_____________]                                      |
|   Steuer-Kategorie: [_____________]                           |
+---------------------------------------------------------------+
| [Vorschau laden]  [Atts zuruecksetzen]                        |
+---------------------------------------------------------------+
```

---

## Sektion 5: Backend-Atts-Validierung (sanitize, Type-Cast, Bounds)

### 5.1 Bisherige Whitelist (v0.15.4)

`handle_service_preview()` haelt eine `$known_top_keys = array( 'layout',
'class', 'section', 'cache' )` Whitelist und verarbeitet jeden Att-Key.
Unbekannte Keys landen ungeprueft im `$sanitized_atts`-Array als String.

`DHPS_Preview_Renderer::render()` validiert die 4 bekannten Atts gegen
`ALLOWED_LAYOUTS`, `ALLOWED_MAES_SECTIONS`, `ALLOWED_CACHE_VALUES` und
nimmt unbekannte Keys in `$atts_rejected` als "unknown att key" auf.

### 5.2 Neue Validation-Pipeline (v0.15.5)

`DHPS_Preview_Renderer::render()` wird um eine zentrale Schema-Lookup-
Schleife erweitert. Pseudo-Code:

```php
$schema = self::SERVICE_ATTS_SCHEMA[ $service ] ?? array();
$atts_applied  = array();
$atts_rejected = array();
$shortcode_atts = '';

foreach ( $atts as $key => $raw ) {
    if ( ! isset( $schema[ $key ] ) ) {
        $atts_rejected[ $key ] = 'unknown att key';
        continue;
    }

    $def = $schema[ $key ];
    $validated = self::validate_att_value( $raw, $def );

    if ( false === $validated['ok'] ) {
        $atts_rejected[ $key ] = $validated['reason'];
        continue;
    }

    $atts_applied[ $key ] = $validated['value'];
    $shortcode_atts .= ' ' . $key . '="' . esc_attr( (string) $validated['value'] ) . '"';
}
```

### 5.3 validate_att_value() - Type-Cast + Bounds

```php
private static function validate_att_value( $raw, array $def ): array {
    $type = $def['type'];

    switch ( $type ) {
        case 'string':
            $val = (string) $raw;
            $sanitize = $def['sanitize'] ?? 'text_field';
            if ( 'html_class' === $sanitize ) {
                $clean = sanitize_html_class( $val );
                if ( '' === $clean && '' !== $val ) {
                    return array( 'ok' => false, 'reason' => 'invalid html-class chars' );
                }
                $val = $clean;
            } elseif ( 'text_field' === $sanitize ) {
                $val = sanitize_text_field( $val );
            } elseif ( 'key' === $sanitize ) {
                $val = sanitize_key( $val );
            } elseif ( 'csv_int' === $sanitize ) {
                // "1,3,5" -> behalten, aber sanitize-checken
                if ( ! preg_match( '/^[0-9,]{0,128}$/', $val ) ) {
                    return array( 'ok' => false, 'reason' => 'pattern mismatch' );
                }
            }
            if ( isset( $def['pattern'] ) ) {
                $re = '/' . $def['pattern'] . '/';
                if ( ! preg_match( $re, $val ) ) {
                    return array( 'ok' => false, 'reason' => 'pattern mismatch' );
                }
            }
            return array( 'ok' => true, 'value' => $val );

        case 'int':
            if ( ! is_numeric( $raw ) ) {
                return array( 'ok' => false, 'reason' => 'invalid type (expected int)' );
            }
            $val = (int) $raw;
            $min = $def['min'] ?? PHP_INT_MIN;
            $max = $def['max'] ?? PHP_INT_MAX;
            if ( $val < $min || $val > $max ) {
                return array(
                    'ok'     => false,
                    'reason' => sprintf( 'out of bounds (min=%d, max=%d)', $min, $max ),
                );
            }
            return array( 'ok' => true, 'value' => $val );

        case 'bool':
            $b = filter_var( $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
            if ( null === $b ) {
                return array( 'ok' => false, 'reason' => 'value not boolean (0|1)' );
            }
            return array( 'ok' => true, 'value' => $b ? '1' : '0' );

        case 'select':
            $allowed_values = array();
            foreach ( $def['options'] as $opt ) {
                $allowed_values[] = (string) $opt['value'];
            }
            $val = (string) $raw;
            if ( ! in_array( $val, $allowed_values, true ) ) {
                return array( 'ok' => false, 'reason' => 'value not in whitelist' );
            }
            return array( 'ok' => true, 'value' => $val );
    }

    return array( 'ok' => false, 'reason' => 'unknown att key' );
}
```

### 5.4 REST-Handler-Vereinfachung

`handle_service_preview()` reicht das ROHE atts-Array (nach scalar-Check)
direkt an `$preview_renderer->render( $service, $atts_raw )` durch. Die
Validation wandert komplett in den Renderer.

```php
// Vereinfacht (v0.15.5):
$atts_raw = isset( $body['atts'] ) && is_array( $body['atts'] ) ? $body['atts'] : array();

// Nur scalar-Filter (Anti-XSS-Wall).
$atts_clean = array();
foreach ( $atts_raw as $k => $v ) {
    $k_str = is_string( $k ) ? sanitize_key( $k ) : '';
    if ( '' === $k_str ) continue;
    if ( ! is_scalar( $v ) ) continue;
    $atts_clean[ $k_str ] = $v;
}

$rendered = $this->preview_renderer->render( $service, $atts_clean );
```

### 5.5 Defense-in-Depth bleibt erhalten

- Layer 1: REST-Route-Regex `[a-z_]+` (unveraendert)
- Layer 2: sanitize_key auf service-slug (unveraendert)
- Layer 3: ALLOWED_SERVICES-Whitelist (unveraendert)
- Layer 4: Atts-Key sanitize_key + scalar-Check (verschaerft)
- Layer 5: Schema-Lookup `SERVICE_ATTS_SCHEMA[$service][$key]` (NEU)
- Layer 6: Type-Validation + Bounds + Pattern (NEU)
- Layer 7: esc_attr beim Shortcode-String-Bau (unveraendert)

### 5.6 OWASP-relevante Trade-offs

- KEIN SQL/NoSQL hier (Atts gehen via do_shortcode in DHPS-Parser).
- KEIN file-system-Zugriff (Atts gehen nicht in Pfade).
- XSS-Wall: `esc_attr` auf jeden Wert, `sanitize_html_class`, `sanitize_text_field`.
- DoS-Schutz: max-Bounds bei int-Werten (z.B. count <= 50).
- ReDoS-Schutz: pattern-Regex muessen linear sein (keine Nested-Quantifier).
  Pre-Release-Smoke: alle `pattern`-Eintraege manuell pruefen.

---

## Sektion 6: BC-Strategie

### 6.1 LivePreviewControls darf nicht brechen

Bestehendes Verhalten:
- Service-Dropdown (13 Eintraege) - unveraendert
- Layout-Dropdown (3 Optionen) - unveraendert
- CSS-Class TextControl - unveraendert
- MAES-Section-Dropdown - unveraendert (conditional)
- Sub-Shortcode-Badge - unveraendert
- Run-Button - unveraendert

Neu (additiv):
- `cache`-Toggle (ToggleControl) - oberhalb des Sub-Shortcode-Badges
- `LivePreviewAttsForm` (Service-spezifische Atts) - unterhalb der universal-Atts
- `Reset-Button` - rechts neben Run-Button

### 6.2 Universal-Atts bleiben unveraendert

`layout`, `class`, `section`, `cache` werden weiterhin in den
`shortcode_atts`-Werten der Registry akzeptiert. Schema markiert sie als
`group: universal` -> Frontend rendert sie in der Top-Sektion (nicht im
LivePreviewAttsForm).

### 6.3 Service-spezifische Atts kommen ADDITIV

- Wenn Frontend `atts: { count: 5 }` POST sendet UND Service `mio_termine` ein `count`-Att im Schema hat -> wird in Shortcode `[mio_termine count="5"]` eingebaut.
- Wenn Frontend `atts: { count: 5 }` POST sendet UND Service `tc` KEIN `count`-Att im Schema hat -> `atts_rejected.count = "unknown att key"`.

### 6.4 Schema-Bridge ist optional

Frontend pruft `if ( window.dhpsAdminBridge && window.dhpsAdminBridge.attsSchema )`.
Falls Schema-Bridge fehlt (z.B. veraltetes Cache), faellt Frontend auf
v0.15.4-Verhalten zurueck (nur layout/class/section/cache).

### 6.5 REST-API-Vertrag bleibt

POST /dhps/v1/services/{service}/preview Request-/Response-Schema bleibt
identisch:
- Request: `{ atts: {...}, format: "iframe" }`
- Response: 10 Felder (service, format, html, size_bytes, render_time_ms, shortcode, atts_applied, atts_rejected, api_cache_hit, rendered_at)

Neu sind nur **neue Reject-Reason-Strings** in `atts_rejected` (siehe 3.3),
das ist ein additiver String-Bestand der bereits flexibel ist.

### 6.6 13/13 Regression OK Pflicht

Pre-Release-Smoke: Alle 13 Shortcodes (9 Haupt + 4 Sub) rendern weiterhin
korrekt im Frontend (Frontend-Pfad ist unangetastet).

---

## Sektion 7: 4 Caveats-Triage (C1-C4 mit Implementation-Snippets)

### 7.1 Caveat C1: Health-Collector kennt Sub-Shortcodes nicht (S/M)

**Status quo**: `DHPS_Health_Collector::collect_for( 'maes_videos' )` liefert
einen Null-Record (keine Service-Registry-Entry, keine OTA-Map).

**Aufwand**: M (Medium, ~1-1.5h).

**Loesung**: Health-Collector erweitern um SUB_SHORTCODE_PARENTS-Lookup.
Sub-Shortcodes erben Auth-Status + Branding vom Parent.

**Implementation-Snippet** (`class-dhps-health-collector.php`):

```php
private const SERVICES = array(
    'mio', 'lxmio', 'mmb', 'mil', 'tp', 'tpt', 'tc', 'maes', 'lp',
    // Sub-Shortcodes (v0.15.5).
    'mio_termine', 'maes_videos', 'maes_merkblaetter', 'maes_aktuelles',
);

private function get_parent_slug( string $service ): string {
    $sub_parents = DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS;
    return isset( $sub_parents[ $service ] ) ? $sub_parents[ $service ] : $service;
}

public function collect_for( string $service ): array {
    $service = sanitize_key( $service );
    $lookup  = $this->get_parent_slug( $service );

    // Alle internen Lookups (Label, OTA-Map, Branding, API-URL) gegen $lookup,
    // aber 'service'/'slug' im Output bleibt $service.
    $label     = $this->get_label( $lookup );
    $ota_set   = $this->is_ota_set( $lookup );
    $api_url   = $this->get_api_url( $lookup );
    // ...

    return array(
        'service'             => $service,        // Original (z.B. 'maes_videos')
        'slug'                => $service,
        'parent_service'      => $lookup,         // NEU - dokumentiert Resolution
        'is_sub_shortcode'    => ( $lookup !== $service ),
        'label'               => $label,
        // ... rest unveraendert
    );
}
```

**Gelieferte Felder (NEU)**:
- `parent_service` (string) - Parent-Slug (z.B. 'maes' fuer 'maes_videos')
- `is_sub_shortcode` (bool) - true wenn Sub-Shortcode

Schema-Drift-Schutz: Frontend `ServiceHealthCard` ignoriert unbekannte Felder
(defensive Reading bewaehrt).

**Entscheidung**: In F1-Backend-Spec mit-erledigen (gemeinsame Schema-Registry).

---

### 7.2 Caveat C2: Doc-Block invalid_format in Error-Codes-Liste (S)

**Status quo**: `class-dhps-admin-rest.php` Zeile 529-534 listet 5 Error-Codes,
aber `invalid_format` fehlt explizit (steht nur als Inline-Kommentar Zeile 616).

**Aufwand**: S (Small, 5 Min Doc-Edit).

**Implementation-Snippet** (`class-dhps-admin-rest.php` Doc-Block):

```php
/**
 * ...
 *
 * Error-Codes:
 *   - invalid_service         (400)
 *   - service_not_configured  (400)
 *   - invalid_endpoint        (404)
 *   - invalid_format          (400)  // NEU v0.15.5 - explizit aufgefuehrt
 *   - rate_limit_exceeded     (429)
 *   - preview_render_failed   (500)
 *
 * @since 0.15.3
 * @since 0.15.4 invalid_format Error-Code hinzugefuegt (war zuvor implizit).
 */
```

**Entscheidung**: Lead-Easy-Win, kein eigener Spec noetig.

---

### 7.3 Caveat C3: CSP-Header-Beispiel mit frame-src about: (S)

**Status quo**: `docs/architecture/14-CSP-COMPATIBILITY.md` Zeile 80-91
zeigt einen empfohlenen CSP-Header ohne `frame-src about:`. Admins die das
Plugin frontend nutzen UND ein striktes CSP fahren, koennten den Live-
Preview-iframe im Backend blocken.

**Aufwand**: S (Small, 5 Min Doc-Edit).

**Implementation-Snippet** (`docs/architecture/14-CSP-COMPATIBILITY.md`):

```markdown
## Empfohlener CSP-Header (mit Live-Preview-Admin-Support)

```
Content-Security-Policy:
  default-src 'self';
  script-src 'self' 'unsafe-inline' deubner-online.de;
  style-src 'self' 'unsafe-inline';
  img-src 'self' data: deubner-online.de;
  frame-src 'self' about: www.youtube.com player.vimeo.com;
  connect-src 'self' deubner-online.de;
```

**Hinweis zu `frame-src about:`**: Notwendig wenn der WP-Admin das
Live-Preview-Feature des Plugins nutzt. Der Preview-iframe verwendet
`srcdoc` und hat Origin `about:srcdoc` - ohne `about:` im frame-src
wuerde der Browser das iframe blocken.

Wenn das Plugin NUR im Frontend zum Einsatz kommt (kein Admin-Live-
Preview), kann `about:` entfallen.
```

**Entscheidung**: Lead-Easy-Win, kein eigener Spec noetig.

---

### 7.4 Caveat C4: wp_localize_script-Bridge (M)

**Status quo**: Frontend `PREVIEW_SERVICES` (13 Eintraege) und Backend
`ALLOWED_SERVICES` (13 Eintraege) muessen manuell synchron gehalten werden.

**Aufwand**: M (Medium, ~1h - aber Synergie mit Ticket 7 Atts-Schema).

**Loesung**: `wp_localize_script` als zentrale Backend-zu-Frontend-Bridge.
Frontend liest aus `window.dhpsAdminBridge.services` statt eigener Konstante.

**Implementation-Snippet** (`Deubner_HP_Services.php` Enqueue-Funktion):

```php
function dhps_enqueue_admin_dashboard( $hook_suffix ) {
    // ... bestehender Code ...

    wp_localize_script(
        'dhps-admin-react',
        'dhpsAdminBridge',
        array(
            // Service-Liste (Single Source of Truth, sync mit ALLOWED_SERVICES).
            'services'   => array_map(
                function ( $slug ) {
                    return array(
                        'value' => $slug,
                        'label' => dhps_get_service_admin_label( $slug ),
                    );
                },
                DHPS_Admin_REST::ALLOWED_SERVICES
            ),
            // Atts-Schema (NEU - v0.15.5 Ticket 7).
            'attsSchema' => DHPS_Preview_Renderer::SERVICE_ATTS_SCHEMA,
            // Sub-Shortcode-Parents (Frontend braucht Maes-Family-Check).
            'subParents' => DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS,
            // Version (Debugging-Anchor).
            'version'    => DEUBNER_HP_SERVICES_VERSION,
        )
    );
}

/**
 * Liefert das Anzeige-Label fuer einen Service-Slug im Admin-Dropdown.
 *
 * @since 0.15.5
 *
 * @param string $slug Service-Slug.
 * @return string Anzeige-Label.
 */
function dhps_get_service_admin_label( string $slug ): string {
    $config = DHPS_Service_Registry::get_service( $slug );
    if ( null !== $config ) {
        return ( $config['admin_title'] ?? $slug ) . ' (' . strtoupper( $slug ) . ')';
    }

    // Sub-Shortcode: Label aus Map.
    $sub_labels = array(
        'mio_termine'       => 'MIO Termine (Sub)',
        'maes_videos'       => 'MAES Videos (Sub)',
        'maes_merkblaetter' => 'MAES Merkblaetter (Sub)',
        'maes_aktuelles'    => 'MAES Aktuelles (Sub)',
    );
    return $sub_labels[ $slug ] ?? $slug;
}
```

**Frontend-Anpassung** (`admin/js/dhps-admin-react.js`):

```js
// Statt hartcodierter PREVIEW_SERVICES-Konstante:
var PREVIEW_SERVICES = ( window.dhpsAdminBridge && Array.isArray( window.dhpsAdminBridge.services ) )
    ? window.dhpsAdminBridge.services
    : [ /* Fallback fuer alte Caches */ ];
```

**Entscheidung**: In F1+F2-Spec mit-erledigen (synergie mit Atts-Schema-Bridge).

---

## Sektion 8: Spec-Aufteilung-Empfehlung

### 8.1 Lehre v0.15.3 und v0.15.4

- **v0.15.3**: 2 parallele Specs F1 (Backend) + F2 (Frontend) - Schema-Drift trat trotz Schema-Vertrag auf. 3 QA-Fixes noetig.
- **v0.15.4**: 1 kombinierter F1+F2-Spec mit Schema-Vertrag. 0 Drift. Lead-Direct fuer Easy-Wins.

### 8.2 Empfehlung v0.15.5: **1 kombinierter F1+F2-Spec + Lead-Easy-Wins**

| # | Spec | Scope | Aufwand |
|---|------|-------|---------|
| **F12** | Backend+Frontend-Spec (kombiniert) | Atts-Schema-Konstante in DHPS_Preview_Renderer + Validation-Pipeline + REST-Handler-Vereinfachung + wp_localize_script-Bridge + LivePreviewAttsForm + AttFieldString/Int/Bool/Select + Service-Wechsel-Reset + Health-Collector C1-Erweiterung | L (~3-4 Tage Wall-Clock) |
| **L1** | Lead-Easy-Wins (parallel zu F12) | C2 Doc-Block invalid_format + C3 CSP-Doku frame-src about: + Version-Bump + Smoke 13/13 | S (~0.5 Tage) |
| **Q1** | QA-Spec (nach F12+L1) | 28+5 Acceptance-Checks, BC-Regression, Atts-Schema-Compliance, Schema-Drift-Check | M (~0.5 Tage) |
| **S1** | Security-Spec (parallel zu Q1) | OWASP-Check Atts-Validation, Bounds-Test, Pattern-ReDoS-Check, neuer Reject-Reason-Wall, T10 dokumentieren | M (~0.5 Tage) |

**Wall-Clock-Gesamt**: 4-5 Tage.

### 8.3 Warum 1 kombinierter Spec?

1. **Atts-Schema ist Backend-und-Frontend ENG gekoppelt** - Drift fast garantiert bei Trennung.
2. **wp_localize_script-Bridge** muss Backend+Frontend in einem PR landen (sonst broken Frontend).
3. **LivePreviewAttsForm** liest direkt aus dem Schema das im Backend definiert wird.
4. **Lehre v0.15.4**: 1 kombinierter F1+F2-Spec hat 0 Drift produziert.
5. **Token-Budget** fuer 1 Spec ist handhabbar (~6000-8000 Tokens Briefing, ~15000 Code-Output).

### 8.4 Warum NICHT 2 parallele Specs?

- Schema-Drift-Risiko trotz Schema-Vertrag (v0.15.3-Lehre).
- 5 neue Reject-Reason-Strings -> Synonym-Risiko (z.B. "out of bounds" vs "bounds violation").
- wp_localize_script muss in EINEM PR landen (sonst Frontend kaputt).

### 8.5 Warum NICHT 1 Discovery + 1 Spec?

- Diese Discovery (v0.15.5) ist bereits dieser Plan -> kein zweiter Discovery-Schritt.
- Schema ist nicht so komplex dass es einen vorgelagerten Schema-Spec braucht (Lehre: Schema-Vertrag-Sektion im F12-Spec reicht).

### 8.6 Briefing-Pflichten fuer F12

Der F12-Spec MUSS folgende Sektionen aus diesem Plan als verbindlichen
Anhang erhalten:

1. **Sektion 1**: Service-Atts-Inventar (komplette Tabelle)
2. **Sektion 3**: Schema-Vertrag (10 Felder + 5 Error-Codes + 9 Reject-Reasons)
3. **Sektion 4.3**: validate_att_value() Pseudo-Code
4. **Sektion 5.3**: Type-Cast + Bounds-Validation
5. **Sektion 6**: BC-Strategie (was NICHT brechen darf)

Eindeutige Feldnamen, KEINE Aliases, KEINE Optionalitaeten ohne Default.

---

## Sektion 9: Risiken + Mitigation

| # | Risiko | Wahrscheinlichkeit | Auswirkung | Mitigation |
|---|--------|--------------------|------------|------------|
| R1 | Schema-Drift Backend/Frontend (Reject-Reason-Strings) | mittel | inkonsistente UI-Anzeige | Schema-Vertrag-Sektion 3.3 mit autoritativen Strings. F12-Spec hat sie als Pflicht-Anhang. |
| R2 | Atts-Schema-Konstante wird zu gross (alle 13 Services in PHP-Const) | gering | ~100-200 Zeilen PHP-Code, kein Performance-Impact | Akzeptabel. Alternative (per-Service-Methoden) waere mehr Code. |
| R3 | wp_localize_script payload zu gross | gering | leichte Pageload-Latenz im Admin | Schema ist ~5-10 KB JSON, vernachlaessigbar gegen React-Bundle (~150 KB). |
| R4 | LivePreviewAttsForm rendert leeres Formular fuer tc/maes_merkblaetter | sicher | UX-Stille | Bedingung `if ( serviceAtts.length === 0 ) return null;` rendert kein leeres Form-Element. |
| R5 | Service-Wechsel ohne Atts-Reset -> "unknown att key"-Spam | mittel | Verwirrendes UI | onServiceChange resettet atts auf neue Schema-Defaults (Sektion 4.4). |
| R6 | Pattern-Regex erzeugt ReDoS | gering | DoS bei Validation | Pre-Release-Smoke prueft alle pattern-Eintraege manuell. Aktuell nur 1 Pattern (`class`). |
| R7 | Health-Collector erweitern bricht Frontend-Cache | gering | ServiceHealthCard zeigt stale Daten | Health-Records sind Transient mit TTL 60s - max 60s stale. |
| R8 | Reset-Button verliert User-Eingaben ohne Confirm | mittel | UX-Unfall | Akzeptiert: Reset ist offensichtlich beschriftet. Bei Bedarf in v0.16 Confirmation-Dialog. |
| R9 | TextControl type=number akzeptiert auch float | mittel | Backend rejected wegen "invalid type (expected int)" | Frontend parseInt vor onChange. Pseudo-Code Sektion 4.3 koennte ergaenzt werden. |
| R10 | ToggleControl liefert true/false, Backend erwartet '0'/'1' | mittel | Backend rejected | Pseudo-Code Sektion 4.3 mappt explizit `val ? '1' : '0'`. |
| R11 | Sub-Shortcodes Health-Erweiterung bricht bestehende ServiceHealthCard | gering | UI-Glitch | Neue Felder sind ADDITIV. Frontend liest defensiv. |
| R12 | maes_videos `videoliste="1,3,5"` faellt durch Pattern-Validation | mittel | User-Frust | Pattern `^[0-9,]{0,128}$` deckt CSV ab. Tests im Smoke. |
| R13 | LP-OTA leer (oft empty in Production) -> service_not_configured | sicher (bekannt) | Preview broken fuer LP | Akzeptabel: Health-Card zeigt OTA leer. User muss konfigurieren. |
| R14 | TC-kdnr leer (oft empty) -> service_not_configured | sicher (bekannt) | Preview broken fuer TC | Wie R13. |
| R15 | Token-Budget des F12-Specs zu klein | gering | Spec laesst Felder weg | Briefing ist ~6-8 KB, Output-Budget grosszuegig (~50 KB). |
| R16 | dhpsAdminBridge fehlt bei veraltetem Browser-Cache | gering | Frontend faellt auf Legacy-Konstanten zurueck | Fallback implementiert (Sektion 7.4 Frontend-Snippet). |
| R17 | Atts-Schema-Aenderungen erfordern Code-Deploy | sicher | keine Live-Aenderung moeglich | Akzeptiert: Atts aendern sich selten (Shortcode-Atts sind WP-API). |

### 9.1 Akzeptierte Trade-offs v0.15.5

- Schema ist hartcodiert in PHP-Const (Code-Deploy bei Aenderung).
- Wishlist-Atts aus User-Briefing (z.B. MIO `count`/`kategorie`) NICHT umgesetzt - erfordert Shortcode-Handler-Erweiterung in v0.16.0.
- Reset-Button ohne Confirm-Dialog.
- TC und maes_merkblaetter haben kein service-spezifisches Atts-Form (leeres Schema).
- maes_aktuelles `count`/`show_teaser` NICHT umgesetzt (Code-Hardcoded).

### 9.2 Trust-Decisions (kumulativ T1-T10)

| # | Decision | Begruendung |
|---|----------|-------------|
| T1-T9 | (aus v0.15.4 unveraendert) | siehe 39-CHANGELOG-v0154.md |
| **T10 (NEU)** | **Atts-Schema als PHP-Const + wp_localize_script-Bridge** | Statisches Schema, kein REST-Roundtrip noetig, 0 Schema-Drift Backend/Frontend. Sicherheits-Wall durch Type+Bounds+Pattern. Per-Service-Atts-Whitelist greift mit Layer 4-7 Defense-in-Depth. |

---

## Sektion 10: Naechste Schritte

1. Plan-Review durch Architekt.
2. F12-Spec-Briefing erstellen mit Pflicht-Anhang (Sektion 1, 3, 4.3, 5.3, 6).
3. L1-Lead-Easy-Wins-Briefing (C2, C3, Version-Bump).
4. Parallele Bearbeitung F12 + L1.
5. Composition durch Lead.
6. QA + Security parallel (Q1 + S1).
7. CHANGELOG-v0155 + Version-Bump + Tag.

---

## Quellen

- `docs/architecture/21-LIVE-PREVIEW-PLAN-v0153.md` - Schema-Vertrag-Vorbild
- `docs/architecture/22-TECH-DEBT-TRIAGE-v0154.md` - Discovery 9 Tickets
- `docs/project/37-QA-REPORT-v0154.md` - 4 Nice-to-Have-Caveats (C1-C4)
- `docs/project/38-SECURITY-AUDIT-v0154.md` - Trust-Decisions T6-T9
- `docs/project/39-CHANGELOG-v0154.md` - v0.15.4 Bilanz
- `includes/class-dhps-shortcodes.php` - generischer Handler
- `includes/class-dhps-maes-modules.php` - 3 MAES-Sub-Shortcodes-Atts
- `includes/class-dhps-steuertermine.php` - mio_termine-Atts
- `includes/class-dhps-service-registry.php` - 9 Hauptservice-shortcode_atts
- `includes/class-dhps-admin-rest.php` - Preview-Handler, Atts-Whitelist
- `includes/class-dhps-preview-renderer.php` - SUB_SHORTCODE_PARENTS, ALLOWED_LAYOUTS
- `includes/class-dhps-health-collector.php` - Health-Collector mit 9 Services
- `admin/js/dhps-admin-react.js` - LivePreviewControls, PREVIEW_SERVICES-Konstante
