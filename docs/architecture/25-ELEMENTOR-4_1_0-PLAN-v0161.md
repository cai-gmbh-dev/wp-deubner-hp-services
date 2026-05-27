# Elementor 4.1.0 Migration - Discovery-Plan v0.16.1

> **Status:** Discovery, keine Code-Aenderungen. Erstellt 2026-05-26.
> **Anlass:** User-Report "die Elementor Integration/Umsetzung klappt nicht mehr".
> **Plattform aktuell (MEMORY.md):** Elementor Free 4.0.1 + WP 6.9.4 + PHP 8.3.30.
> **Ziel:** Elementor Pro 4.1.0 (`related-infos/vs-nfd/elementor-pro-4.1.0.zip`) + voraussichtlich Elementor Free 4.1 oder hoeher (Free-ZIP im Repo ist 3.35.4, die das **Minimum** fuer Pro 4.1.0 ist).
> **Quellen dieses Discovery-Docs:** Lokale Plugin-Codebasis + Inhalt aus den entpackten ZIPs in `temp/` (NACH Discovery zu loeschen, bereits in `.gitignore` ergaenzt).

---

## Kurzfazit Architekt-TL;DR

**Es gibt keine eindeutigen Breaking-Changes in den klassischen Widget-APIs.** Das Plugin nutzt ausschliesslich Methoden und Konstanten, die in Free 3.35.4 und Pro 4.1.0 unveraendert vorhanden sind. Die Symptommeldung "klappt nicht mehr" ist daher wahrscheinlich entweder

a) eine **Version-Konstellation** (Free auf 3.x veraltet waehrend Pro 4.1.0 installiert wurde - das **bricht** wegen `Requires Elementor: 3.34` in Pro-readme + `ELEMENTOR_PRO_REQUIRED_CORE_VERSION = '3.35'` in elementor-pro.php),

b) eine **Atomic-Editor-Inkompatibilitaet** (V4-Editor verwendet ein neues UI - klassische Widgets erscheinen evtl. unter einer anderen Kategorie / Panel-Sektion),

c) ein **Inkrement-spezifischer Bug** in 4.1.0 (z.B. Loop-Item-Templates-Fatalfix laut Changelog 4.1.0 - User koennte einen verwandten Fehler sehen),

d) ein **Token-Bridge-Issue** (siehe `dhps_elementor_bridge_enabled` Option - Token-Namen koennten sich geaendert haben).

Konkret testbar siehe Sektion 4 (Symptom-Hypothesen).

---

## Sektion 1: ZIP-Inspektion

### 1.1 Elementor Pro 4.1.0

**Plugin-Header (`elementor-pro/elementor-pro.php`):**

```
Plugin Name: Elementor Pro
Version: 4.1.0
Requires PHP: 7.4
Requires at least: 6.7        (WordPress)
Requires Plugins: elementor
Elementor tested up to: 4.1.0
```

**PHP-Konstanten (Quelle):**

```php
ELEMENTOR_PRO_VERSION = '4.1.0';
ELEMENTOR_PRO_REQUIRED_CORE_VERSION   = '3.35';   // ABSOLUTES MINIMUM Free-Elementor
ELEMENTOR_PRO_RECOMMENDED_CORE_VERSION = '4.1';   // Empfohlen Free 4.1.x
```

**readme.txt:**

```
Requires at least: 6.8       (WP-Mindestversion)
Tested up to: 7.0
Requires Elementor: 3.34
```

**WP-Mindestversion-Hinweis:** Der Header nennt 6.7, die readme.txt 6.8. Aktuelle Plattform 6.9.4 erfuellt beide.

### 1.2 Elementor Free 3.35.4 (im Repo)

```
Plugin Name: Elementor
Version: 3.35.4
Requires PHP: 7.4
Requires at least: 6.6
Stable tag: 3.35.4
Beta tag: 3.35.0-beta4
```

> **Risiko:** Pro 4.1.0 nennt `Recommended 4.1` als Core. Wenn auf der Stage nur Free 3.35.4 installiert wird, laeuft Pro im "Required-aber-nicht-Empfohlen"-Modus. Das ist KEIN Fatal aber kann zu UI-Inkonsistenzen fuehren. Idealerweise sollte parallel Elementor Free 4.1.x bereitgestellt werden (nicht im Repo enthalten - extern beschaffen via wordpress.org / Elementor-Account).

### 1.3 Letzte 5 Versionen Pro Changelog (Auszug `changelog.txt`)

- **4.1.0 (2026-05-26)** - Atomic Form Erweiterungen (Radio, Select, Date, Time, File Upload, Webhook), WP-Min auf 6.8, Fix Loop-Item-Templates Fatal beim Paste.
- **4.0.4 (2026-04-28)** - Display-Conditions Fix Flexbox/Div-Block, RTL CSS-Editor.
- **4.0.3 (2026-04-20)** - Atomic Form Submit-Buttons in Popups, ACF Dynamic Tags.
- **4.0.2 (2026-04-13)** - Interactions auf alle Instanzen statt nur erstem Match.
- **4.0.1 (2026-04-01)** - Component-Eigenschaften-Bugfixes.
- **4.0.0 (2026-03-30)** - GA Atomic Forms + Interactions + Component-Erstellung fuer Pro.

> **Keine** Eintraege zu Breaking-Changes klassischer Widget-API in den letzten 5 Versionen. Saemtliche Neuerungen betreffen das **Atomic Editor (V4)** Modul.

### 1.4 Letzte Free-Changelog-Highlights (3.32 - 3.35)

- **3.35.0** - V4 Editor wird "Production-Ready" markiert, Inline Editing fuer Atomic Heading/Paragraph, Components GA.
- **3.34.0** - Atomic Tabs, Entrance Interactions, V3 Container-Wrapper durch V4 Wrapper ersetzt.
- **3.33.0** - Variables Manager, Blend Mode, Background Clipping (alle Editor V4).
- **3.32.0** - Transform-Controls 2D/3D, Transitions, Class Manager Filter (alle Editor V4).
- **3.31.0** - Custom HTML Attributes (Editor V4).

> Im klassischen Widget-Path: keine API-Brueche dokumentiert.

---

## Sektion 2: API-Diff zu Elementor 4.0.1

**Methode:** Inspektion der entpackten `temp/elementor-pro-4.1.0/elementor-pro/` plus partielles Extract von `temp/widget-base-3.35.4.php` + `temp/controls-stack-3.35.4.php` + `temp/controls-manager-3.35.4.php`.

### 2.1 `\Elementor\Widget_Base` (Free 3.35.4 = Pro 4.1.0 Mindest-Core)

- Klasse existiert weiter, namespace unveraendert (`namespace Elementor;`)
- `abstract class Widget_Base extends Element_Base` (Free `includes/base/widget-base.php` Zeile 22)
- Methode `get_categories()` weiterhin als oeffentliche Methode (Zeile 105)
- Methode `render_content()` unveraendert (Zeile 617)
- Atomic-Widget-API ist additiv:
  `temp/elementor-pro-4.1.0/elementor-pro/...` benutzt `\Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base extends \Elementor\Widget_Base` - das klassische `Widget_Base` bleibt Basis.

### 2.2 `register_controls()` vs `_register_controls()`

- `Controls_Stack::_register_controls()` ist **seit 3.1.0 deprecated** (Free `includes/base/controls-stack.php` Zeile 2221-2228) - laeuft aber weiter und ruft intern `$this->register_controls()` auf.
- `Controls_Stack::register_controls()` ist die offizielle Methode (Zeile 2243).
- **Unser Plugin nutzt bereits `register_controls()` ohne Underscore-Prefix:**
  - `widgets/elementor/class-dhps-elementor-widget-base.php:161` -> `protected function register_controls(): void {`
  - `widgets/elementor/class-dhps-elementor-widget-steuertermine.php:60` -> dito
  - `widgets/elementor/class-dhps-elementor-maes-widgets.php:87` -> dito

> **Befund: kein Breaking-Issue hier.**

### 2.3 `\Elementor\Controls_Manager` Konstanten

Inspiziert in `temp/controls-manager-3.35.4.php`. Alle vom Plugin benutzten Konstanten sind unveraendert vorhanden:

| Konstante | Plugin-Verwendung | Free 3.35.4 |
|-----------|-------------------|-------------|
| `TAB_CONTENT` | widget-base.php:173 | OK (Zeile 24) |
| `TAB_STYLE` | widget-base.php:370 | OK (Zeile 29) |
| `SELECT` | widget-base.php:204 | OK (Zeile 69) |
| `TEXT` | widget-base.php:215 | OK (Zeile 54) |
| `NUMBER` | widget-base.php:225 | OK (Zeile 59) |
| `SWITCHER` | widget-base.php:452 | OK (Zeile 74) |
| `DIVIDER` | widget-base.php:195 | OK (Zeile 134) |
| `COLOR` | widget-base.php:393 | OK (Zeile 139) |
| `SLIDER` | widget-base.php:405 | OK (Zeile 149) |
| `DIMENSIONS` | widget-base.php:556 | OK (Zeile 154) |
| `HEADING` | widget-base.php:708 | OK (Zeile 89) |

> **Befund: kein Breaking-Issue hier.**

### 2.4 Group_Controls

Benutzt: `Group_Control_Typography`, `Group_Control_Border`, `Group_Control_Text_Shadow`, `Group_Control_Box_Shadow`. Diese liegen in `includes/controls/groups/` und sind in 3.5+ stabil (siehe `05-ELEMENTOR-4X-MIGRATION.md`).

### 2.5 Hooks und Kategorie-Registration

- `elementor/loaded` Detection - vorhanden seit 1.0.0
- `elementor/widgets/register` (3.5+) - im Plugin `class-dhps-elementor.php:86`
- `elementor/elements/categories_registered` (3.5+) - im Plugin `class-dhps-elementor.php:87`
- `$widgets_manager->register( new ... )` (3.5+) - im Plugin `class-dhps-elementor.php:145`

> **Befund: kein Breaking-Issue hier.**

### 2.6 Klassische-Kategorie vs Atomic-Editor-Kategorie

> **Hypothese (nicht verifiziert):** Im V4-Editor (Editor V4 = Atomic Editor) koennten klassische Widgets in einem eigenen "Legacy"-Tab landen oder eine andere Sichtbarkeit haben.

Loesungspfad: Test im V4-Editor, ob `'dhps-services'`-Kategorie sichtbar ist. Falls nicht, muss Atomic-Editor-Sichtbarkeit explizit konfiguriert werden (Mechanismus erst in Stage-Test verifizieren).

### 2.7 AJAX und Frontend

Keine Aenderungen im Plugin-Code zu Elementor-AJAX gefunden. Plugin nutzt nur Standard WP-AJAX (`admin-ajax.php`) ueber die Service-Pipeline, **kein** Elementor-AJAX-Endpoint.

---

## Sektion 3: Plugin-Integration-Audit

**Methode:** Manuelle Durchsicht der 5 PHP-Files (2.816 LOC).

### 3.1 `includes/class-dhps-elementor.php` (160 LOC)

| Stelle | Aktueller Code | API | Severity | Anmerkung |
|--------|----------------|-----|----------|-----------|
| Zeile 82 | `did_action( 'elementor/loaded' )` | OK | - | Stabiles Pattern |
| Zeile 86 | `add_action( 'elementor/widgets/register', ... )` | OK | - | 3.5+ |
| Zeile 87 | `add_action( 'elementor/elements/categories_registered', ... )` | OK | - | 3.5+ |
| Zeile 100 | `$elements_manager->add_category( 'dhps-services', [...] )` | OK | - | API stabil |
| Zeile 145 | `$widgets_manager->register( new $class() )` | OK | - | 3.5+ |

**Keine Critical/Major-Findings.** Status: **GRUEN**.

### 3.2 `widgets/elementor/class-dhps-elementor-widget-base.php` (1408 LOC)

| Stelle | Aktueller Code | API | Severity | Anmerkung |
|--------|----------------|-----|----------|-----------|
| Zeile 37 | `extends \Elementor\Widget_Base` | OK | - | Vererbung weiterhin gueltig |
| Zeile 78 | `public function __construct( array $data = [], ?array $args = null )` | OK | - | Signatur paralel zu Atomic_Widget_Base |
| Zeile 161 | `protected function register_controls(): void` | OK | - | Korrekte Methode |
| Zeile 374-377 | `Group_Control_Typography` | OK | - | Stabil |
| Zeile 1274 | `protected function render(): void` | OK | - | Stabil |
| Zeile 1280 | `get_settings_for_display()` | OK | - | Stabil |
| Zeile 378 | Selektor `{{WRAPPER}}` | OK | - | Stabil |

**Keine Critical/Major-Findings.** Status: **GRUEN**.

> Minor: An keiner Stelle wird `content_template()` (JS-Twig-Template fuer Editor-Live-Preview) definiert. Bedeutet: Editor zeigt aktuell vermutlich nur den "Reload View"-Fallback / Server-Render. Das ist **kein neuer** Issue mit 4.1.0, war auch in 4.0.1 so. Im Atomic-Editor kann sich aber die Live-Preview-Erwartung verschaerfen.

### 3.3 `widgets/elementor/class-dhps-elementor-service-widgets.php` (317 LOC)

Reine Vererbungs-Subklassen ohne API-Calls. Status: **GRUEN**.

### 3.4 `widgets/elementor/class-dhps-elementor-maes-widgets.php` (721 LOC)

Eigene Basis `DHPS_Elementor_MAES_Base extends \Elementor\Widget_Base`. Methodische Struktur identisch zur Widget-Base. Status: **GRUEN**.

### 3.5 `widgets/elementor/class-dhps-elementor-widget-steuertermine.php` (208 LOC)

Eigenstaendiges Widget direkt von `\Elementor\Widget_Base`. Status: **GRUEN**.

### 3.6 Audit-Zusammenfassung

> **0 Critical, 0 Major, 0 Minor API-Findings im Plugin-Code.** Das Plugin nutzt durchgaengig moderne, stabile Elementor-APIs. Der Befund deckt sich mit der bestehenden Doku `docs/team-knowledge/05-ELEMENTOR-4X-MIGRATION.md` ("Kompatibilitaetsstatus: VOLL KOMPATIBEL").

---

## Sektion 4: Symptom-Hypothesen

Da der User nur "klappt nicht mehr" gemeldet hat, sind die folgenden Hypothesen **nach Wahrscheinlichkeit absteigend** geordnet. Jede ist konkret testbar.

### H1 (HOECHSTE WSK): Free/Pro Versions-Mismatch

**Annahme:** Pro 4.1.0 wurde installiert, Free ist noch auf einer < 3.35 Version.

**Wirkung:** Pro wird durch eigene Plugin-Check-Funktion in `elementor-pro.php` deaktiviert oder zeigt eine "Required version"-Warnung. **Alle DHPS-Widgets verschwinden aus dem Panel**, weil `elementor/widgets/register` nie feuert (Pro selbst registriert seine eigenen Widgets ueber dieselben Hooks, aber bootstrappt nicht, wenn Core-Mindestversion nicht erfuellt).

**Test:** WP-Admin -> Plugins-Seite -> nach gelben "Elementor Pro requires Elementor 3.35 or higher" Banner suchen.

**Fix:** Free auf >= 3.35.4 oder 4.1.x updaten.

### H2 (HOHE WSK): Atomic-Editor-Sichtbarkeit

**Annahme:** Im V4-Editor (Editor V4 = Atomic Editor, Default seit 3.35) erscheinen klassische Widgets evtl. nicht in derselben Sidebar.

**Wirkung:** "Deubner Services"-Kategorie ist im Panel nicht zu finden oder leer.

**Test:** Im Editor-Panel-Filter explizit nach "Deubner" suchen. Auch in **Settings -> Experiments** pruefen, ob "Editor V4" aktiv ist - falls ja, dann auf V3-Editor temporaer downgrade als Cross-Check.

**Fix:** Falls bestaetigt, in `register_widgets()` eine Kompatibilitaetsschicht ergaenzen oder dokumentieren, dass Klassik-Editor verwendet werden muss.

### H3 (MITTEL): Token-Bridge-Selektor-Inkompatibilitaet

**Annahme:** `dhps_base.css` mappt `.elementor-txcolor-primary` auf `--e-global-color-primary` (siehe `10-elementor4-atomic-research.md` Sektion 1). In 4.1.0 koennte sich der Token-Namespace zu `--e-a-*` geaendert haben.

**Wirkung:** Plugin-Output rendert farblos / mit Fallback-Tokens.

**Test:** Live-Seite mit dem [tp]-Shortcode im Editor pruefen. DevTools-Console: `getComputedStyle(document.documentElement)` und alle `--e-*` listen. Wenn `--e-global-color-primary` weg ist, ist die Bridge gebrochen.

**Fix:** Token-Bridge im `dhps_base.css` erweitern um neue Atomic-Token-Namen mit `var(--e-a-color-primary, var(--e-global-color-primary, ...))` Fallback-Kaskade.

### H4 (MITTEL): WordPress-Mindestversion / PHP-Mindestversion

**Annahme:** Pro 4.1.0 verlangt WP >= 6.8 (readme) bzw. 6.7 (Header) und PHP >= 7.4. Aktuelle Plattform: WP 6.9.4 + PHP 8.3.30 - sollte passen.

**Wirkung:** Sollte erfuellt sein. **Nur als Sanity-Check.**

**Test:** WP-Admin -> Site Health -> Info -> WP + PHP-Version visuell pruefen.

### H5 (NIEDRIG): Bekannte 4.1.0-Regressions

**Annahme:** Changelog 4.1.0 erwaehnt `Fix: Pasting content from another site may cause a fatal error with Loop Item Templates` - das deutet auf instabile Loop-Templates. Wenn der User irgendwo Loop-Item nutzt, koennte das troffen sein.

**Wirkung:** Fatal beim Editor-Laden, wenn Loop-Item-Template mit DHPS-Widget existiert.

**Test:** Existieren auf der Stage-Site Loop-Item-Templates (Pro-Feature, Theme Builder -> Loop)? Wenn nein: nicht relevant.

**Fix:** 4.1.0 hat diesen Fix bereits drin. Nur wenn auf einer Version dazwischen gestolpert.

---

## Sektion 5: Migration-Strategie

### Option A: 1-zu-1 API-Anpassung (minimal-invasiv)

**Empfehlung: JA.** Begruendung:
- Audit (Sektion 3) zeigt: 0 API-Findings.
- Pro 4.1.0 nutzt klassisches `Widget_Base` als Atomic-Basis - kein Migrations-Druck.
- Risiko fuer DB-stored Widget-Settings ist Null (Property-Namen bleiben gleich).

### Option B: Atomic-Widget-Migration

**Empfehlung: NEIN, nicht in v0.16.1.** Begruendung:
- Aktueller Code laeuft mit `Atomic_Widget_Base extends Widget_Base` - Atomic ist additiv.
- Eine vollstaendige Atomic-Migration wuerde alle 11 Widgets umbauen (1.408 LOC Basis + 4 Subklassen-Files), inkl. Atomic-Controls-Schema, Props-Schema-Definitionen, `define_atomic_controls()` Abstrakt-Methode.
- Aufwand: **L (4-7 Personentage)**, Risiko: hoch (DB-Schema-Bruch potenziell).
- Reserviert fuer v0.17.0+ wenn Atomic GA-stabil + User-Bedarf konkret.

### Option C: Conditional-Loading

**Empfehlung: NEIN, nicht noetig.** Begruendung:
- Keine API-Brueche -> kein konditionaler Code-Pfad noetig.
- WP-Version + Elementor-Version-Checks koennen in `class-dhps-elementor.php::init()` ergaenzt werden, falls Defense-in-Depth gewuenscht.

### Empfohlene Strategie

**Option A + Stage-Verifikation.** Konkrete Schritte:

1. **Stage hochfahren** mit aktueller Plattform (Elementor Free **4.1.x** + Pro **4.1.0**). Wenn nur Free 3.35.4 verfuegbar: damit testen, das ist Pro-Mindestversion.
2. **Saemtliche 11 DHPS-Widgets manuell smoke-testen** (Panel-Sichtbarkeit, Drop-to-Page, Editor-Preview, Frontend-Render).
3. **Wenn alle 11 gruen sind:** Doku-Update (`05-ELEMENTOR-4X-MIGRATION.md` + Plattform-Stand in MEMORY.md). Release v0.16.1 als "Compatibility Verified".
4. **Wenn Probleme:** Spezifisch nach Symptom debuggen (siehe Sektion 4 Hypothesen).

---

## Sektion 6: Spec-Aufteilung

### Empfehlung: 1 Specialist + Lead-Direct

#### F1 (Specialist - "ELEMENTOR-4_1_0-SMOKE")

**Aufgabe:** Stage mit Elementor Free 4.1.x + Pro 4.1.0 (idealerweise; minimal Free 3.35.4 + Pro 4.1.0) bereitstellen. Vollstaendiger Smoke-Test aller 11 DHPS-Widgets. Token-Bridge-Verifikation.

**Aufwand:** M (4-6 Stunden inkl. Stage-Setup + Smoke + Dokumentation).

**Deliverables:**
- Smoke-Report Markdown mit Screenshots
- Token-Inventar Dump (DevTools-Console)
- Falls Defekte: Issue-Liste mit File:Line + Reproduktion

#### Lead-Direct

**Aufgabe:** Doku-Update + ggf. defensive Code-Erweiterung.

- `docs/team-knowledge/05-ELEMENTOR-4X-MIGRATION.md` -> Plattform-Stand auf 4.1.0 hochziehen
- MEMORY.md -> Plattform-Notiz aktualisieren
- `temp/` cleanen (manuell nach Discovery-Abschluss)
- Optional: `class-dhps-elementor.php::init()` defensive Version-Checks (Free + Pro) als Defense-in-Depth

**Aufwand:** S (1-2 Stunden).

### Specialists NICHT noetig fuer

- F2 (urspruengliche "Migration"-Spec) - **entfaellt**, weil keine Migration noetig.
- F3 (urspruengliche "Atomic"-Spec) - **entfaellt**, weil Option B verworfen.

---

## Sektion 7: Schema-Vertrag

### 7.1 UNVERAENDERT bleiben muss

| Kategorie | Element | Begruendung |
|-----------|---------|-------------|
| Widget-Name | `dhps-mio`, `dhps-lxmio`, `dhps-mmb`, `dhps-mil`, `dhps-tp`, `dhps-tpt`, `dhps-tc`, `dhps-maes`, `dhps-lp`, `dhps-steuertermine`, `dhps-maes-videos`, `dhps-maes-merkblaetter`, `dhps-maes-aktuelles` | Elementor speichert diese in `_elementor_data` Post-Meta. Eine Aenderung wuerde alle bestehenden Pages mit DHPS-Widgets unbrauchbar machen. |
| Widget-Category | `dhps-services` | Theme-Builder / Template-Library kann Pages filtern nach Category. Aenderung bricht das. |
| Setting-Keys (Atts) | `layout`, `custom_class`, `cache_ttl`, `mio_columns`, `tp_columns`, `tp_lazy_count`, `tp_lazy_mode`, `tp_style`, `tp_video_mode`, `mio_style`, `termine_month`, `termine_count`, `termine_layout`, `einzelvideo`, `videoliste`, `columns`, `section`, ... | Diese Schluessel sind in `_elementor_data` JSON. Aenderung bricht User-Settings. |
| Widget-Class-Namen | `DHPS_Elementor_Widget_MIO`, `DHPS_Elementor_Widget_LXMIO`, ... | Werden ueber `new $class()` instanziiert. Theme-Override-Plugins koennten darauf reflectieren. |
| CSS-Selektoren | `.dhps-tp-card`, `.dhps-mmb-category__icon`, `.dhps-termine__month`, `.dhps-tp-grid`, `.dhps-tp-load-more`, `.dhps-filter-bar__btn` | Werden in `selectors`-Properties referenziert (z.B. widget-base.php:548, 559, 587). Aenderung bricht User-Style-Settings. |

### 7.2 Aenderbar (additiv)

- Neue Controls hinzufuegen (mit eindeutigen neuen Keys)
- Neue Style-Sections hinzufuegen
- Bestehende `default`-Werte aendern (mit Vorsicht)
- CSS-Inhalte / Style-Token-Mapping in `dhps_base.css`

### 7.3 Bewusst KEINE Atomic-Migration in v0.16.1

Falls in v0.17.0+ Atomic-Migration angegangen wird, MUSS ein Migrations-Pfad fuer bestehende DB-Daten implementiert werden (alte `_elementor_data` -> neues Atomic-Schema). Das ist explizit OUT-OF-SCOPE fuer v0.16.1.

---

## Sektion 8: Acceptance-Kriterien (T1-T15)

Diese sind **stage-test-tauglich** fuer den F1-Specialist.

| ID | Kriterium | Verifikation |
|----|-----------|--------------|
| T1 | WP 6.9.4 + PHP 8.3.30 + Elementor Free >= 3.35.4 + Pro 4.1.0 sind aktiviert, keine Fatal in `wp-content/debug.log` | Plugin-Liste + tail debug.log |
| T2 | "Deubner Services"-Kategorie ist im Elementor-Panel sichtbar | Editor-Sidebar Visual |
| T3 | Alle 9 Service-Widgets (MIO, LXMIO, MMB, MIL, TP, TPT, TC, MAES, LP) erscheinen unter der Kategorie | Editor-Panel Drag-Source |
| T4 | Steuertermine-Widget erscheint unter der Kategorie | dito |
| T5 | 3 MAES-Module-Widgets (Videos, Merkblaetter, Aktuelles) erscheinen | dito (nur wenn Steuertermine-Dependencies da, siehe Code Line 149) |
| T6 | Jedes der 13 Widgets laesst sich auf eine neue Test-Page ziehen, keine JS-Fehler in Browser-Console | Drag-Drop + DevTools |
| T7 | Editor-Preview rendert jedes Widget ohne sichtbaren PHP-Notice / Warning | Iframe-Inhalt visuell + Source-Tab |
| T8 | Frontend-Render (Page preview "View Page") zeigt jedes Widget korrekt | Browser visual |
| T9 | Bestehende Pre-4.1.0-Pages mit DHPS-Widgets bleiben funktional (Backwards-Test gegen Demo-Page falls vorhanden) | Demo-Page-Reload |
| T10 | Token-Bridge: Wenn `dhps_elementor_bridge_enabled` Option aktiv, sind `--dhps-color-primary` etc. ueber `--e-global-color-primary` befuellt | DevTools `getComputedStyle` |
| T11 | Atomic-Token-Inventar dump: ALLE `--e-*` Variablen aus `document.documentElement` extrahieren | Skript aus `10-elementor4-atomic-research.md` Sektion 1.3 |
| T12 | Service-Filter (z.B. TP `tp_lazy_mode = auto`) feuern im Widget-Render-Pfad korrekt - mind. 2 Filter pruefen | Browser visual + JS-Log |
| T13 | Anti-Leakage: Mehrere TP-Widgets auf einer Page haben jeweils ihre eigene Konfiguration (siehe `remove_all_filters` in widget-base.php:1399-1406) | Page mit 2x TP + unterschiedl. Spalten |
| T14 | CSP-konform: Keine neuen Inline-Styles oder eval-Patterns durch 4.1.0-Updates (siehe `14-CSP-COMPATIBILITY.md`) | DevTools Network + Source |
| T15 | Live-Preview im Admin-Dashboard (v0.15.3+) rendert Elementor-Output (NICHT direkt - das ist Shortcode-Preview, sollte unbeeinflusst sein - **Sanity-Check**) | DHPS-Dashboard "Live-Preview" |

---

## Sektion 9: Risiken + Tech-Debt

### Risiken

| ID | Risiko | Wahrscheinlichkeit | Auswirkung | Mitigation |
|----|--------|----|-----|------------|
| R1 | Free/Pro Versions-Mismatch auf Live-Site | mittel-hoch | Hoch (Plugins-Deaktivierung) | Site Health Check + Defensive Version-Check im Plugin-Bootstrap (Lead-Direct) |
| R2 | V4 Editor versteckt klassische Widgets | mittel | Mittel (UX-Verwirrung) | Stage-Test T2/T3, ggf. Doku-Hinweis "Klassik-Editor erforderlich" |
| R3 | Atomic-Token-Namespace bricht Bridge | gering-mittel | Niedrig (nur Visuell) | Token-Inventar Dump T11, Fallback-Kaskade in CSS |
| R4 | Loop-Item-Template Fatal (4.1.0 changelog Fix) | gering | Hoch (Editor-Crash) | Loop-Item-Pruefung in Stage |
| R5 | PHP-Notice-Storm durch deprecated Elementor-Methoden | gering | Niedrig | `WP_DEBUG=true` in Stage |
| R6 | Frontend-CSS-Regressions durch Atomic-Wrapper-Update (3.34: V3 Wrapper -> V4 Wrapper) | mittel | Mittel (Layout-Bruch) | Visuelle Smoke-Tests T6-T8 |
| R7 | Drittanbieter-Plugins (z.B. Custom-Code, Custom-CSS Editor V4 Features) interferieren | gering | Niedrig | Stage isoliert testen |
| R8 | WP 6.9 Compatibility Issues - Pro readme.txt sagt "Tested up to: 7.0", Free "Tested up to: 6.9" | gering | Niedrig | bereits WP 6.9.4 erfuellt Minimum |

### Tech-Debt (fuer v0.16.2+)

- **TD-1:** `content_template()` JS-Twig-Live-Preview fehlt. Im V4-Editor evtl. wichtiger als bisher. **Aufwand:** M.
- **TD-2:** Atomic-Widget-Migration als Option B - reservieren fuer v0.17.0 falls Atomic-GA und User-Bedarf.
- **TD-3:** Defensive Plugin-Version-Check (Free + Pro) im Bootstrap mit klarer Admin-Notice bei Mismatch. **Aufwand:** S, optional in v0.16.1.
- **TD-4:** Token-Bridge auf Atomic-Tokens (`--e-a-*`) erweitern, falls Sektion 4 H3 bestaetigt wird. **Aufwand:** S.
- **TD-5:** `get_keywords()` in der Widget-Base nicht implementiert - Subklassen koennten profitieren (Suchbarkeit im Panel). **Aufwand:** S.

---

## Sektion 10: Spec-Briefing-Material

### 10.1 Stage-Test-Setup

**Vorraussetzungen:** Live-Docker mit WP 6.9.4 + PHP 8.3.30 (entspricht aktuellem Stand).

**Pflicht-Files im `related-infos/vs-nfd/`:**

- `elementor-pro-4.1.0.zip` (3.8 MB) - Pro
- `elementor.3.35.4.zip` (19 MB) - Free, **dies ist die Mindest-Core-Version fuer Pro 4.1.0**

**Optimaler Zustand:** Free **4.1.x** statt 3.35.4 - aber 4.1.x ist nicht im Repo und muss extern beschafft werden (wordpress.org Plugin-Repo oder Elementor-Account-Download).

**Installation:**

```bash
# 1. Bestehende Elementor-Installationen entfernen
docker exec -i wp-deubner-hp-services-wordpress-1 wp plugin deactivate elementor elementor-pro --allow-root || true
docker exec -i wp-deubner-hp-services-wordpress-1 wp plugin delete elementor elementor-pro --allow-root || true

# 2. Free 3.35.4 installieren
docker cp related-infos/vs-nfd/elementor.3.35.4.zip wp-deubner-hp-services-wordpress-1:/tmp/
docker exec -i wp-deubner-hp-services-wordpress-1 wp plugin install /tmp/elementor.3.35.4.zip --activate --allow-root

# 3. Pro 4.1.0 installieren
docker cp related-infos/vs-nfd/elementor-pro-4.1.0.zip wp-deubner-hp-services-wordpress-1:/tmp/
docker exec -i wp-deubner-hp-services-wordpress-1 wp plugin install /tmp/elementor-pro-4.1.0.zip --activate --allow-root

# 4. Pruefen
docker exec -i wp-deubner-hp-services-wordpress-1 wp plugin list --allow-root | grep -i elementor

# 5. WP_DEBUG aktivieren falls noch nicht
docker exec -i wp-deubner-hp-services-wordpress-1 wp config set WP_DEBUG true --raw --allow-root
docker exec -i wp-deubner-hp-services-wordpress-1 wp config set WP_DEBUG_LOG true --raw --allow-root
```

> Auf Windows ggf. `MSYS_NO_PATHCONV=1` voranstellen.

### 10.2 Smoke-Test-Procedure

Pro Widget (insgesamt 13):

1. Editor neu laden (`/wp-admin/post.php?post=<id>&action=elementor`)
2. Sidebar-Suchfeld: "Deubner" eintippen -> alle 13 Widgets muessen erscheinen
3. Widget auf Canvas ziehen
4. Editor-Preview visuell pruefen (kein "Fehler beim Laden" + kein leerer Container)
5. Speichern, "View Page" -> Frontend pruefen
6. DevTools-Console: keine JS-Errors mit "elementor" im Stacktrace
7. `wp-content/debug.log` tail: keine neuen PHP-Notice/Fatal-Eintraege

### 10.3 Token-Inventar-Dump

Im Browser-DevTools-Console auf einer geladenen Page mit Elementor 4.1.0 aktiv:

```javascript
const cs = getComputedStyle(document.documentElement);
const tokens = [];
for (const prop of cs) {
  if (prop.startsWith('--e-')) {
    tokens.push([prop, cs.getPropertyValue(prop).trim()]);
  }
}
console.table(tokens);
```

Ergebnis im Smoke-Report dokumentieren.

### 10.4 Bridge-Test (T10)

```bash
# Bridge aktivieren
docker exec -i wp-deubner-hp-services-wordpress-1 wp option update dhps_elementor_bridge_enabled 1 --allow-root
```

Dann auf einer Page mit DHPS-Widget:

```javascript
const cs = getComputedStyle(document.documentElement);
console.log('--dhps-color-primary =', cs.getPropertyValue('--dhps-color-primary'));
console.log('--e-global-color-primary =', cs.getPropertyValue('--e-global-color-primary'));
// Erwartung mit aktiver Bridge: --dhps-color-primary uebernimmt den e-global Wert.
```

### 10.5 Multi-Widget-Anti-Leakage (T13)

Test-Page mit:
- 1x TP-Widget, `tp_columns=2`, `tp_video_mode=inline`
- 1x TP-Widget, `tp_columns=4`, `tp_video_mode=modal`

Beide muessen unabhaengig rendern. Falls beide das gleiche `tp_columns` zeigen -> Filter-Leakage-Bug (sollte durch `remove_all_filters` in widget-base.php:1399-1406 verhindert sein).

### 10.6 Hypothesen-Test-Matrix (aus Sektion 4)

| Hypothese | Test-Befehl / -Step | Erwartetes Ergebnis falls Hypothese WAHR |
|-----------|---------------------|--------------------------------|
| H1 | `wp plugin list --allow-root` -> Free + Pro Versionen | Free < 3.35 + Pro 4.1.0 = Mismatch |
| H2 | Editor-Sidebar Visual Inspection in V4-Editor | "dhps-services" Kategorie fehlt im Panel |
| H3 | Token-Inventar-Dump (10.3) | `--e-global-color-primary` fehlt oder ist leer |
| H4 | WP-Admin Site Health Info | WP < 6.8 oder PHP < 7.4 |
| H5 | Existieren Loop-Item-Templates? -> Theme-Builder -> Loop | Loop-Item mit DHPS-Widget vorhanden + Fatal beim Editor-Laden |

### 10.7 Beispiel-Snippets fuer defensive Version-Checks (Lead-Direct, optional TD-3)

In `class-dhps-elementor.php::init()` zwischen Zeile 82 und 86 einfuegbar:

```php
if ( ! did_action( 'elementor/loaded' ) ) {
    return;
}

// TD-3: Defense-in-Depth - Minimum Elementor + Pro versions
if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.35', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-warning"><p><strong>Deubner HP Services:</strong> Elementor &gt;= 3.35 empfohlen (aktuell ' . esc_html( ELEMENTOR_VERSION ) . ').</p></div>';
    } );
    // KEIN return - Plugin laeuft weiter, nur Warnung
}
if ( defined( 'ELEMENTOR_PRO_VERSION' ) && version_compare( ELEMENTOR_PRO_VERSION, '4.0', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-info"><p><strong>Deubner HP Services:</strong> Elementor Pro &gt;= 4.0 empfohlen.</p></div>';
    } );
}
```

> **Hinweis:** Plugin laeuft auch ohne Pro - Pro ist optional. Nur Warnungen, kein Block.

### 10.8 Aufraeumen `temp/`

Nach Discovery / Spec-Phase manuell:

```bash
rm -rf "D:/cai/Projekte/Development/cai-gmbh-development/wp-deubner-hp-services/temp/"
```

`.gitignore` hat `temp/` bereits drin (in dieser Discovery ergaenzt).

---

## Anhang A: Geprueftes Quellen-Material

- `temp/elementor-pro-4.1.0/elementor-pro/elementor-pro.php` - Plugin-Header
- `temp/elementor-pro-4.1.0/elementor-pro/changelog.txt` - 4.0-4.1 Eintraege
- `temp/elementor-pro-4.1.0/elementor-pro/readme.txt` - WP/PHP-Min
- `temp/elementor-pro-4.1.0/elementor-pro/modules/` - 40+ Module gelistet (atomic-widgets, atomic-form, interactions sind NEU)
- `temp/widget-base-3.35.4.php` (extrahiert) - Widget_Base unveraendert
- `temp/controls-stack-3.35.4.php` (extrahiert) - register_controls/_register_controls (deprecated seit 3.1.0)
- `temp/controls-manager-3.35.4.php` (extrahiert) - alle Konstanten unveraendert
- `temp/element-base-3.35.4.php` (extrahiert) - keine breaking changes
- `temp/elementor-pro-4.1.0/elementor-pro/modules/atomic-widgets/...` - Atomic_Widget_Base extends Widget_Base (additiv)

## Anhang B: Verifikation der bestehenden Doku

- `docs/team-knowledge/05-ELEMENTOR-4X-MIGRATION.md` ist **stand v0.14.0 fuer Elementor 4.0.1**, weiterhin **gueltig fuer 4.1.0**. Update-Empfehlung: Plattform-Tabelle auf `4.1.0` hochziehen, Liste der Pruef-Items beibehalten.
- `docs/architecture/10-elementor4-atomic-research.md` ist Research-Stand fuer Atomic-Tokens - keine Aenderungen noetig, da Bridge weiter additiv ist.
- `docs/architecture/07-WIDGET-SYSTEM.md` existiert (in der Doku-Liste, nicht im Detail gelesen) - sollte als Teil der v0.16.1-Doku-Update gegengelesen werden, ob Plattform-Stand erwaehnt wird.

---

**Discovery-Status:** ABGESCHLOSSEN.
**Empfehlung an Lead:** 1 Specialist F1 (Stage-Smoke) + Lead-Direct Doku-Update. Aufwand insgesamt M (1 Personentag).
