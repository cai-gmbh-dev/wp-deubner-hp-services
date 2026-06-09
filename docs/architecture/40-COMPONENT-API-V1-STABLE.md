# Component-API v1 - Stabilitaets-Vertrag

## Stand: 2026-06-08 (seit v0.20.0)

## Mission

Component-System v1 ist seit v0.14.0 produktiv und seit v0.20.0 **als final
markiert**. Dieses Dokument ist der **Stabilitaets-Vertrag** der Component-API
gegenueber Theme-Entwicklern und Plugin-Erweiterungen.

**Kurze Antwort auf die Frage "v2 geplant?"**: nein.

## Component-API v1 - Was ist stabil?

### `dhps_component( string $name, array $props = [] ): string`

- **Stabil seit**: v0.14.0
- **API-Vertrag**: BC bis mindestens v1.0 (nach SemVer Convention)
- **Inputs**:
  - `$name`: Component-Name aus Whitelist `[a-z][a-z0-9-]*` (seit v0.20.0
    Defense-in-Depth via Regex-Sanity-Check). Beispiele: `content-card`,
    `content-list`, `empty-state`, `filter-bar`.
  - `$props`: Assoziatives Array mit Component-spezifischen Properties.
    Werden ueber `dhps_component_props`-Filter durchgereicht und im
    Template-Scope via `extract( $props, EXTR_SKIP )` ausgepackt.
- **Output**: HTML-String. Bei unbekanntem Name -> `''` (bzw. HTML-Kommentar
  in WP_DEBUG-Mode). Bei fehlendem Template -> `''`.

### `dhps_get_component_icon( string $slug, int $size, float $stroke_width ): string`

- **Stabil seit**: v0.20.0
- **API-Vertrag**: BC bis mindestens v1.0
- **Inputs**:
  - `$slug`: Aus 10-Slug-Whitelist (`calendar`/`clock`/`file`/`download`/
    `play`/`link`/`inbox`/`calculator`/`document`/`video`).
  - `$size`: Pixel-Width und Pixel-Height, Default 14.
  - `$stroke_width`: SVG stroke-width-Attribut, Default 2.0.
- **Output**: Komplettes SVG-Tag, oder `''` bei unbekanntem Slug.

### Action-/Filter-Hooks

| Hook | Seit | Typ | Stabilitaet |
|------|------|-----|-------------|
| `dhps_component_props` | v0.14.0 | Filter | BC bis v1.0 |
| `dhps_component_template_path` | v0.14.0 | Filter | BC bis v1.0 |
| `dhps_component_allowed_roots` | v0.20.0 | Filter | BC bis v1.0 |
| `dhps_component_icon_svg` | v0.20.0 | Filter | BC bis v1.0 |

## Theme-Override-Mechanismus

Theme-Entwickler koennen einzelne Components ueberschreiben:

```
{your-theme}/dhps/components/{component-name}.php
```

Such-Reihenfolge:

1. Child-Theme (`get_stylesheet_directory()` + `/dhps/components/`)
2. Parent-Theme (`get_template_directory()` + `/dhps/components/`)
3. Plugin-Default (`{plugin}/public/views/components/`)

**Defense-in-Depth (seit v0.20.0)**:

- Component-Name muss `[a-z][a-z0-9-]*` matchen (Regex-Sanity-Check)
- Aufgeloester Pfad MUSS innerhalb der Whitelist-Roots liegen
  (`realpath`-Vergleich) - Path-Traversal-Schutz
- Filter `dhps_component_allowed_roots` als Escape-Hatch fuer
  ungewoehnliche Setups (Mu-Plugin-Component-Pools etc.)

### Theme-Override-Beispiel (Content-Card)

```php
// {your-theme}/dhps/components/content-card.php
<?php
// $title, $teaser, $service usw. sind im Scope (Props extracted)
?>
<article class="my-theme-card my-theme-card--<?php echo esc_attr( $service ); ?>">
    <h3 class="my-theme-card__title"><?php echo esc_html( $title ); ?></h3>
    <p class="my-theme-card__teaser"><?php echo esc_html( $teaser ); ?></p>
</article>
```

Der Plugin-Default wird komplett ersetzt. Das Theme uebernimmt 100% der
Render-Verantwortung fuer diese Component.

### Filter-Hook-Beispiel (Props-Mutation)

```php
add_filter( 'dhps_component_props', function( $props, $name ) {
    if ( 'content-card' === $name && isset( $props['teaser'] ) ) {
        $props['teaser'] = str_replace( 'foo', 'bar', $props['teaser'] );
    }
    return $props;
}, 10, 2 );
```

## Verfuegbare Components (Inventar)

Tatsaechlich registrierte Components (siehe `dhps_register_components()` in
`Deubner_HP_Services.php`):

| Component | Use-Case | Stabil seit |
|-----------|----------|-------------|
| `content-card` | Vereinheitlichtes Card-Markup fuer Items (Video/Document/News) | v0.14.0 |
| `content-list` | Grid/List-Container fuer Card-Sammlungen | v0.14.0 |
| `empty-state` | Fallback-UI bei leerem Container | v0.14.0 |
| `filter-bar` | UI-Element fuer Tab-/Filter-Navigation | v0.14.0 |
| `skeleton-loader` | Loading-Placeholder waehrend Lazy-Load | v0.14.2 |
| `pagination` | Seiten-Navigation | v0.14.x |
| `lazy-image` | Lazy-Loading-Image-Wrapper | v0.14.x |
| `accordion` | Akkordeon-Container fuer Lazy-Akkordeons | v0.14.x |

Insgesamt **8 Components** im System.

## Was ist NICHT stabil (kein Vertrag)?

- **Component-Templates intern**: HTML-Struktur, CSS-Klassen-Namen,
  Attribute koennen sich aendern. Theme-Overrides verlassen sich auf
  die Component-API (`dhps_component`-Aufruf + Props), nicht auf den
  Default-Template-HTML-Output.
- **Default-Props-Werte**: koennen sich aendern.
- **Component-CSS**: BEM-Klassen koennen sich aendern.

## Roadmap

| Geplant | Was |
|---------|-----|
| keine v2 | Component-System v1 ist final |
| v0.21.x | Falls Bedarf entsteht: Slot-System fuer komplexere Layouts |
| **Pause** | DTO-Foundation- und Component-Stabilisierungs-Aera abgeschlossen |

## Schema-Vertrag-Vorgehen

**21. Iteration** ohne Critical-Drift. Component-System v1 ist seit
v0.14.0 stabil, v0.20.0 polish-haerted.
