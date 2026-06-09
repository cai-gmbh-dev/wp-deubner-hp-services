<?php
/**
 * Globale Component-Renderer-Helper.
 *
 * Stellt zwei prozedurale Funktionen bereit, die als oeffentliche API
 * fuer Service-Templates dienen:
 *
 * - `dhps_component( $name, $props )`        - liefert HTML als String.
 * - `dhps_render_component( $name, $props )` - echoed HTML direkt (WP-Convention).
 *
 * Beide Funktionen delegieren an die {@see DHPS_Component_Registry} fuer
 * Path-Resolution und Used-Tracking und kapseln die Output-Buffer-Logik.
 *
 * @package Deubner Homepage-Service
 * @since   0.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dhps_get_component_icon' ) ) {

	/**
	 * Liefert ein vordefiniertes SVG-Icon als HTML-String.
	 *
	 * Zentralisierte Icon-Map fuer Component-Templates. Slugs aus dieser
	 * Whitelist sind die einzigen, die der Helper kennt - alles andere wird
	 * als leerer String zurueckgegeben (fail-soft).
	 *
	 * Verfuegbare Slugs:
	 * - Klein (default size=14, stroke=2):  `calendar`, `clock`, `file`,
	 *   `download`, `play`, `link`
	 * - Gross (default size=48, stroke=1.6): `inbox`, `calculator`,
	 *   `document`, `video`
	 *
	 * Templates die ihre eigene Groesse brauchen, koennen `$size` und
	 * `$stroke_width` ueberschreiben. Filter `dhps_component_icon_svg`
	 * erlaubt komplette SVG-Inhalt-Substitution pro Slug.
	 *
	 * @since 0.20.0
	 *
	 * @param string $slug         Icon-Slug aus der Whitelist.
	 * @param int    $size         Pixel-Width und Pixel-Height fuer das SVG.
	 * @param float  $stroke_width SVG stroke-width-Attribut.
	 *
	 * @return string Komplettes SVG-Tag als HTML, oder leerer String bei
	 *                unbekanntem Slug.
	 */
	function dhps_get_component_icon( string $slug, int $size = 14, float $stroke_width = 2.0 ): string {
		// Inhalt jedes Icons (ohne <svg>-Wrapper). Die <svg>-Hilfswerte
		// (viewBox/size/stroke/fill) werden zentral unten zusammengebaut.
		$icon_bodies = array(
			// Klein, stroke=2.
			'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
			'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
			'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
			'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
			'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
			// Klein, fill=currentColor (Play hat Sonder-Style).
			'play'     => '<polygon points="6 4 20 12 6 20 6 4"/>',
			// Gross, stroke=1.6.
			'inbox'      => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/>',
			'calculator' => '<rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="8.01" y2="10"/><line x1="12" y1="10" x2="12.01" y2="10"/><line x1="16" y1="10" x2="16.01" y2="10"/><line x1="8" y1="14" x2="8.01" y2="14"/><line x1="12" y1="14" x2="12.01" y2="14"/><line x1="16" y1="14" x2="16.01" y2="14"/><line x1="8" y1="18" x2="12" y2="18"/>',
			'document'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/>',
			'video'      => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
		);

		if ( ! isset( $icon_bodies[ $slug ] ) ) {
			return '';
		}

		$body = $icon_bodies[ $slug ];

		// Play-Icon hat fill=currentColor (gefuellt), alle anderen stroke-only.
		$is_filled = ( 'play' === $slug );
		$fill      = $is_filled ? 'currentColor' : 'none';
		$stroke    = $is_filled ? 'none' : 'currentColor';

		$svg_open = sprintf(
			'<svg viewBox="0 0 24 24" width="%1$d" height="%1$d" fill="%2$s" stroke="%3$s" stroke-width="%4$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">',
			max( 1, $size ),
			$fill,
			$stroke,
			number_format( $stroke_width, 1, '.', '' )
		);

		$svg = $svg_open . $body . '</svg>';

		/**
		 * Filter: Erlaubt komplette SVG-Substitution pro Slug.
		 *
		 * @since 0.20.0
		 *
		 * @param string $svg          Komplettes SVG-Tag.
		 * @param string $slug         Icon-Slug aus dem Aufruf.
		 * @param int    $size         Pixel-Groesse aus dem Aufruf.
		 * @param float  $stroke_width Stroke-Width aus dem Aufruf.
		 */
		return (string) apply_filters( 'dhps_component_icon_svg', $svg, $slug, $size, $stroke_width );
	}
}

if ( ! function_exists( 'dhps_component' ) ) {

	/**
	 * Rendert eine Component und gibt den HTML-String zurueck.
	 *
	 * Ablauf:
	 * 1. Pruefe Registrierung (fail-soft in Production, HTML-Kommentar in WP_DEBUG).
	 * 2. Merge `default_props` aus der Registrierung mit den uebergebenen Props.
	 * 3. Wende den `dhps_component_props`-Filter an (Plugins koennen Props mutieren).
	 * 4. Loese Template-Pfad via Theme-Override-Hierarchie auf.
	 * 5. Markiere Component als verwendet (Conditional-Enqueue-Hook).
	 * 6. Inkludiere Template im Output-Buffer mit Props als lokale Variablen.
	 *
	 * @since 0.14.0
	 *
	 * @param string               $name  Component-Name (z.B. 'content-card', 'filter-bar').
	 * @param array<string, mixed> $props Props fuer das Template.
	 *
	 * @return string Rendered HTML, oder leerer String (bzw. HTML-Kommentar in WP_DEBUG)
	 *                wenn Component unbekannt oder Template fehlt.
	 */
	function dhps_component( string $name, array $props = array() ): string {
		// v0.20.0 Defense-in-Depth (Audit M-1 schliessen): Sanity-Check auf
		// Component-Name. Whitelist via Regex `^[a-z][a-z0-9-]*$`. Strict-Reject
		// statt fail-soft, damit Theme-Code mit Typos (Whitespace, Sonderzeichen,
		// Uppercase) klar wegen Component-API-Misuse failed.
		if ( 1 !== preg_match( '/^[a-z][a-z0-9-]*$/', $name ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- dhps_component: ungueltiger Name "' . esc_html( $name ) . '" (erwartet [a-z][a-z0-9-]*) -->';
			}
			return '';
		}

		// 1. Existenz-Check - fail-soft.
		if ( ! DHPS_Component_Registry::is_registered( $name ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- dhps_component: unbekannte Komponente "' . esc_html( $name ) . '" -->';
			}
			return '';
		}

		// 2. Default-Props aus Registry-Config mit User-Props mergen.
		$config        = DHPS_Component_Registry::get_config( $name ) ?? array();
		$default_props = isset( $config['default_props'] ) && is_array( $config['default_props'] )
			? $config['default_props']
			: array();
		$props         = array_merge( $default_props, $props );

		/**
		 * Filter: erlaubt Modification der Props vor Render.
		 *
		 * @since 0.14.0
		 *
		 * @param array<string, mixed> $props Props (inkl. gemergter Defaults).
		 * @param string               $name  Component-Name.
		 */
		$props = (array) apply_filters( 'dhps_component_props', $props, $name );

		// 3. Template-Pfad aufloesen (Theme-Override-Hierarchie).
		$template = DHPS_Component_Registry::get_template_path( $name, $props );
		if ( '' === $template || ! file_exists( $template ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- dhps_component: Template fuer "' . esc_html( $name ) . '" nicht gefunden -->';
			}
			return '';
		}

		// 4. Mark used (fuer Conditional Asset-Enqueue).
		DHPS_Component_Registry::mark_used( $name );

		// 5. Output-Buffer + Template-Inkludierung mit Props im Scope.
		ob_start();
		// Props ins Template-Scope. EXTR_SKIP schuetzt vor Variable-Hijacking
		// (z.B. wenn ein Prop 'template' oder 'name' heisst).
		extract( $props, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Bewusste Component-API analog zu get_template_part().
		include $template;

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'dhps_render_component' ) ) {

	/**
	 * Echo-Variante von `dhps_component()`.
	 *
	 * Analog zu WordPress-Convention (`get_template_part()` vs.
	 * `the_content()`): String-Variante hat keinen Praefix, Echo-Variante
	 * ist explizit benannt.
	 *
	 * Component-Templates sind kontrolliert und kuemmern sich selbst um
	 * Output-Escaping ihrer Props - daher kein doppeltes esc_html() hier.
	 *
	 * @since 0.14.0
	 *
	 * @param string               $name  Component-Name.
	 * @param array<string, mixed> $props Props fuer das Template.
	 *
	 * @return void
	 */
	function dhps_render_component( string $name, array $props = array() ): void {
		echo dhps_component( $name, $props ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component-Templates sind kontrolliert + selbst-escaping.
	}
}
