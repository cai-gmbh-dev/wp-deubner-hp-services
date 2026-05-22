# Changelog v0.13.0 - TC Tax-Rechner (Legacy-Migration komplett)

## Stand: 2026-05-22

## Mission Accomplished

Mit v0.13.0 ist die Modernisierung **aller 9 Services** abgeschlossen.
Alle Services nutzen jetzt die Content-Pipeline mit Parser + Templates.

| Service | Parser | Templates | Status |
|---------|--------|-----------|--------|
| MIO | DHPS_MIO_Parser | mio/* | Modern |
| LXMIO | shared MIO_Parser | fallback mio | Modern |
| MMB | DHPS_MMB_Parser | mmb/* | Modern |
| MIL | shared MMB_Parser | fallback mmb | Modern |
| TP | DHPS_TP_Parser | tp/* | Modern |
| TPT | DHPS_TPT_Parser (erbt TP) | tpt/* | Modern |
| **TC** | **DHPS_TC_Parser (NEU)** | **tc/* (NEU)** | **Modern** |
| MAES | DHPS_MAES_Parser | maes/* | Modern |
| LP | DHPS_LP_Parser (erbt TP) | fallback tp | Modern |

## TC (Tax-Rechner) - Architektur

TC ist konzeptuell anders als andere Services:
- **Nicht Content** sondern interaktive Rechner (25+ Steuer-Rechner)
- **Akkordeon-UI** mit Inline-JS (`test_einblenden`/`test_ausblenden`)
- **Kein strukturierter Output** extrahierbar - HTML + JS sind eine Einheit

### Wrapper-Parser-Ansatz

DHPS_TC_Parser ist ein "Wrapper-Parser" der:
1. **Kein extrahieren** versucht (keine Videos/Artikel/Termine)
2. **Empty-State erkennt** (kdnr leer oder keine Rechner freigeschaltet)
3. **Raw-HTML durchreicht** (mit Inline-JS fuer Akkordeon-Funktion)

### Empty-State Detection (3 Patterns)

```php
1. <div class="taxcalc"><p class="sm_buttons"></p></div>    -> empty
2. calc_area vorhanden                                       -> NICHT empty (Prioritaet)
3. weder calc_area noch webcalc                              -> empty
4. content < 50 Zeichen nach script/style-Strip              -> empty
```

## Templates

### default.php
- Standard-Container mit Border + 20px Padding
- Branding-Container drumherum (Schriftfamilie vererbt)
- TC-Inline-Styles bleiben isoliert

### card.php
- Wie default + dhps-card Wrapper (Box-Shadow)

### compact.php
- 12px Padding, kein Border (fuer Sidebar-Embed)

Alle 3 Templates haben einen identischen **Empty-State** mit Icon und Hinweis.

## CSS

```
.dhps-service--tc                      Container
.dhps-tc__container                    Inhalt-Wrapper
.dhps-tc__container--compact           Kompakte Variante
.dhps-tc__empty                        Empty-State Block
.dhps-tc__empty-icon                   Calculator-Icon (SVG)
.dhps-tc__empty-title                  "Keine Rechner verfuegbar"
.dhps-tc__empty-text                   Hinweis-Text
```

**Anti-Konflikt**: TC bringt eigene CSS via API mit (webcalc.css).
Plugin-Container vererbt nur Schriftfamilie, ueberschreibt nichts.

## Daten-Struktur

```php
DHPS_TC_Parser::parse() returns array(
    'html'        => string,  // Original HTML mit Inline-JS
    'is_empty'    => bool,    // Empty-State erkannt
    'service_tag' => 'tc',
)
```

## Sicherheit

**Audit-Ergebnis**: 0 Critical, 0 High, 0 Medium

### Akzeptierte Trust-Entscheidung
TC-Templates rendern `echo $tc_html` ohne Escaping (mit `phpcs:ignore` dokumentiert).

**Begruendung**:
- HTML kommt von authentifiziertem Deubner-API-Endpoint (HTTPS, kdnr-Auth)
- Inline-JS (`test_einblenden`/`test_ausblenden`) ist **funktionale Anforderung**
- `wp_kses()` wuerde Event-Handler entfernen und das Akkordeon zerstoeren
- Trust-Boundary identisch zu allen anderen Deubner-API-Services

### Defense in Depth
- `custom_class` wird zweimal sanitisiert: `sanitize_html_class()` (Renderer) + `esc_attr()` (Template)
- Empty-State-Texte: `esc_html()` (defensiv, auch fuer statische Strings)
- Parser-Regex: keine ReDoS-Risiken (alle Patterns linear)

## QA-Ergebnisse

| Test | Status |
|------|--------|
| Alle 5 PHP-Dateien Syntax OK | OK |
| TC-Parser registriert | OK |
| Empty-State Test 1: Leerer Container | empty=YES |
| Empty-State Test 2: Nur Scripts | empty=YES |
| Empty-State Test 3: Mit calc_area Content | empty=NO |
| `[tc]` rendert Empty-State (kdnr leer) | OK 884 bytes |
| `[tc layout="card"]` mit dhps-card | OK 893 bytes |
| `[tc layout="compact"]` kompakt | OK 287 bytes |
| Andere 8 Services unbeeintraechtigt | OK |
| **9/9 Services modernisiert** | **OK** |

## Geaenderte Dateien

| Datei | Aenderung |
|-------|-----------|
| `includes/parsers/class-dhps-tc-parser.php` | NEU - Wrapper-Parser |
| `public/views/services/tc/default.php` | NEU |
| `public/views/services/tc/card.php` | NEU |
| `public/views/services/tc/compact.php` | NEU |
| `public/views/services/tc/index.php` | NEU (Silence) |
| `css/dhps-frontend.css` | TC-Section (~70 Zeilen) |
| `Deubner_HP_Services.php` | TC-Parser Registrierung |

## Bekannte Limitierung

- `dhps_tc_kdnr` aktuell leer - keine Live-Tests mit echten Rechnern moeglich
- Sobald TC-Lizenz hinterlegt ist, aktiviert sich der Service vollstaendig
- Empty-State-UI zeigt aktuell den Admin-Hinweis korrekt an

## Bilanz: 9/9 Services modernisiert

Mit diesem Release ist die Modernisierung **aller** Deubner-Services abgeschlossen:
- Steuern: MIO, MMB, MIL, TP, TPT, TC
- Recht: LXMIO, LP
- Medizin: MAES

Naechste mogliche Schritte (optional):
- Einheitliches Datenmodell (User-Wunsch aus fruherer Conversation)
- LP-OTA + TC-kdnr Provisionierung fuer Live-Tests
- Performance-Audit ueber alle Services
- WP-CLI-Integration fuer Service-Management
