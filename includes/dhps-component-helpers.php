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
