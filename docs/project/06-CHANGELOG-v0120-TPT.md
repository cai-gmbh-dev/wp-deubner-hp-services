# Changelog v0.12.0 - TPT TaxPlain Teaser

## Stand: 2026-05-22

## Neue Funktionen

### TPT (TaxPlain Teaser) Service - aktiviert
- **NEU**: `DHPS_TPT_Parser` (erbt von `DHPS_TP_Parser`)
- Extrahiert nur einen einzelnen `videoblock` aus der API-Response
- 3 Templates: `default`, `card`, `compact` (Sidebar-tauglich)
- Reuses TP-Infrastruktur: AJAX-Proxy, dhps-tp-js, mandantenvideo.de iframes

### Bugfix: Service-Registry korrigiert
**Vorher** (defekt):
- Endpoint: `taxplain/videopages/teaser_php.php` -> 404
- Auth: `kdnr` mit leerem `dhps_tp_kdnr`
- `supports_video: false` (falsch deklariert)
- Keine `default_params`

**Nachher** (korrekt):
- Endpoint: `einbau/taxplain/videopages/php_inhalt.php` (gleicher wie TP)
- Auth: `ota` mit `dhps_ota_tp` (geteilt mit TP)
- `supports_video: true`
- `default_params: ['teasermodus' => '1']` (Schluessel-Parameter)

Die Magie: `teasermodus=1` weist die API an, statt der vollen Galerie (60 Videos)
nur den einzelnen aktuellen Video-Block zurueckzuliefern.

### TP-Parser: parse_video_block protected
- Methode war `private`, jetzt `protected`
- Ermoeglicht Wiederverwendung in TPT_Parser (und zukuenftig LP-Subklassen)
- Security-Audit bestaetigt: Keine neue Angriffsflaeche

### TPT Elementor Widget Controls
- Spalten/Lazy-Loading **ausgeblendet** fuer TPT (Single Video, irrelevant)
- Style-Preset + Video-Wiedergabe (inline/modal) bleiben verfuegbar
- Admin-Felder: ueberschrift, teasertext, breite, modus (aus Service-Registry)

### TPT CSS - Single-Card-Styling
- `.dhps-service--tpt` max-width 500px, zentriert
- `.dhps-tpt-card` - Single-Card mit Hover-Shadow
- `.dhps-tpt-card--compact` - Horizontal-Layout (160px Poster + Body)
- Responsive: Compact wird auf <480px vertikal
- 3 Layout-Varianten: default / card (mit dhps-card Wrapper) / compact

## Datenmodell

```php
DHPS_TPT_Parser::parse() returns array(
    'video'       => array|null,  // Single Video-Eintrag
    'service_tag' => 'tpt',
)

// $data['video'] enthaelt:
// - video_id (string)
// - video_slug (string)
// - poster_url (string)
// - titel (string)
// - teaser (string)
// - datum (string)
// - v_modus (string)
// - service ('taxplain')
```

## Shortcode-Beispiele

```
[tpt]                       Standard-Layout
[tpt layout="card"]         Card mit Schatten
[tpt layout="compact"]      Horizontal (Sidebar)
```

## Geaenderte Dateien

| Datei | Aenderung |
|-------|-----------|
| `includes/parsers/class-dhps-tpt-parser.php` | NEU |
| `includes/parsers/class-dhps-tp-parser.php` | `parse_video_block` private -> protected |
| `includes/class-dhps-service-registry.php` | TPT Endpoint/Auth/Defaults korrigiert |
| `widgets/elementor/class-dhps-elementor-widget-base.php` | TPT-spezifische Control-Bedingungen |
| `public/views/services/tpt/default.php` | NEU |
| `public/views/services/tpt/card.php` | NEU |
| `public/views/services/tpt/compact.php` | NEU |
| `public/views/services/tpt/index.php` | NEU (Silence) |
| `css/dhps-frontend.css` | TPT-Section (~130 Zeilen) |
| `Deubner_HP_Services.php` | TPT-Parser Registrierung |

## Sicherheit

**Audit-Ergebnis**: 0 Critical, 0 High, 0 Medium, 0 Low

- Alle Templates: durchgaengiges Escaping (`esc_html`, `esc_attr`, `esc_url`)
- Admin-Optionen (Ueberschrift, Teasertext): nur via `esc_html` ausgegeben
- `parse_video_block`-Sichtbarkeitsaenderung: kein neuer Angriffsvektor
- SSRF-Schutz aus v0.11.0 greift fuer TPT automatisch (gemeinsamer AJAX-Proxy)

## QA-Ergebnisse

| Test | Status |
|------|--------|
| Alle 8 PHP-Dateien Syntax OK | OK |
| TPT-Parser registriert | OK |
| TPT-Parser erbt von TP | OK |
| Service-Registry korrigiert | OK |
| `[tpt]` rendert 1533 bytes | OK |
| `[tpt layout="card"]` rendert mit dhps-card | OK |
| `[tpt layout="compact"]` rendert Compact-Variante | OK |
| Andere Services unbeeintraechtigt | OK (mio/lxmio/mmb/mil/tp/maes/lp) |
| Sample-Parser-Test: alle Felder extrahiert | OK |

## Verbleibend

Nur noch ein Legacy-Service: **TC (Tax-Rechner)**.
Architektonisch anders (interaktive Rechner statt Content), daher
eigene Behandlung in einer kuenftigen Version.
