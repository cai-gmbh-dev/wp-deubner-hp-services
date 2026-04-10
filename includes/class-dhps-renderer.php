<?php
/**
 * Renderer-Engine fuer Layout-Templates.
 *
 * Wrappt den rohen API-HTML-Output in gestylte Container-Templates.
 * Unterstuetzt Theme-Overrides und verschiedene Layout-Varianten
 * (default, card, compact).
 *
 * Ab v0.9.0 zusaetzlich: Rendering von geparsten Daten ueber
 * Service-spezifische Templates (public/views/services/{tag}/{layout}.php).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Renderer
 *
 * Rendert API-Inhalte in Layout-Templates mit konfigurierbaren
 * CSS-Klassen fuer Service-Typ, Layout-Variante und Custom-Klassen.
 *
 * Template-Suchreihenfolge:
 * 1. Theme-Override: {theme}/dhps/layout-{name}.php
 * 2. Plugin-Template: {plugin}/public/views/layout-{name}.php
 *
 * @since   0.5.0
 * @package Deubner Homepage-Service
 */
class DHPS_Renderer {

	/**
	 * Demo-Manager-Instanz fuer Demo-Badge-Anzeige.
	 *
	 * @since 0.6.0
	 * @var DHPS_Demo_Manager|null
	 */
	private $demo_manager = null;

	/**
	 * Setzt den Demo-Manager fuer Demo-Badge-Anzeige.
	 *
	 * @since 0.6.0
	 *
	 * @param DHPS_Demo_Manager $demo_manager
	 *
	 * @return void
	 */
	public function set_demo_manager( DHPS_Demo_Manager $demo_manager ): void {
		$this->demo_manager = $demo_manager;
	}

	/**
	 * Rendert HTML-Inhalt innerhalb eines Layout-Templates.
	 *
	 * Gibt den rohen HTML-String unveraendert zurueck, wenn er leer ist
	 * oder einen Fehler-Kommentar (<!-- DHPS:) enthaelt. Andernfalls
	 * wird das passende Layout-Template geladen und mit den uebergebenen
	 * CSS-Klassen gerendert.
	 *
	 * @since 0.5.0
	 *
	 * @param string $html      Der HTML-String aus der API-Antwort.
	 * @param string $tag       Shortcode-Tag (z.B. 'mio', 'tp') fuer die Service-Klasse.
	 * @param string $layout    Layout-Name (z.B. 'default', 'card', 'compact').
	 * @param string $css_class Optionale zusaetzliche CSS-Klasse(n).
	 *
	 * @return string Gerendertes HTML mit Layout-Wrapper oder unveraenderter Input.
	 */
	public function render( string $html, string $tag, string $layout = 'default', string $css_class = '' ): string {
		// Leeren Output oder Fehler-Kommentare direkt durchreichen.
		if ( '' === $html || 0 === strpos( trim( $html ), '<!-- DHPS:' ) ) {
			return $html;
		}

		// Layout-Template suchen.
		$template_file = $this->locate_template( $layout );

		if ( null === $template_file ) {
			// Fallback auf default-Layout wenn angefordertes nicht existiert.
			$template_file = $this->locate_template( 'default' );
		}

		// Wenn auch kein default-Template gefunden wird, rohen Output zurueckgeben.
		if ( null === $template_file ) {
			return $html;
		}

		// Template-Variablen vorbereiten.
		$content       = $html;
		$service_class = 'dhps-service--' . sanitize_html_class( $tag );
		$layout_class  = 'dhps-layout--' . sanitize_html_class( $layout );
		$custom_class  = '' !== $css_class ? ' ' . sanitize_html_class( $css_class ) : '';

		// Template rendern via Output-Buffering.
		ob_start();
		include $template_file;
		$output = ob_get_clean();

		// Demo-Badge hinzufuegen wenn Demo aktiv.
		if ( null !== $this->demo_manager && $this->demo_manager->is_demo_active( $tag ) ) {
			$output = $this->wrap_demo_badge( $output, $tag );
		}

		return $output;
	}

	/**
	 * Rendert geparste Daten ueber ein Service-spezifisches Template.
	 *
	 * Sucht ein Template unter public/views/services/{tag}/{layout}.php
	 * (bzw. Theme-Override) und stellt dem Template die geparsten Daten
	 * sowie die CSS-Klassen-Variablen zur Verfuegung.
	 *
	 * Verfuegbare Template-Variablen:
	 * - $data          (array)  Strukturiertes Array aus dem Parser.
	 * - $service_class (string) CSS-Klasse: 'dhps-service--{tag}'.
	 * - $layout_class  (string) CSS-Klasse: 'dhps-layout--{layout}'.
	 * - $custom_class  (string) Optionale CSS-Klasse (mit fuehrendem Leerzeichen oder leer).
	 *
	 * @since 0.9.0
	 *
	 * @param array  $data      Strukturiertes Array aus dem Parser.
	 * @param string $tag       Shortcode-Tag (z.B. 'mio').
	 * @param string $layout    Layout-Name (z.B. 'default', 'card').
	 * @param string $css_class Optionale CSS-Klasse(n).
	 *
	 * @return string Gerendertes HTML oder leerer String bei fehlendem Template.
	 */
	public function render_parsed( array $data, string $tag, string $layout = 'default', string $css_class = '' ): string {
		// Service-Template suchen mit Fallback-Kette.
		$template_file = $this->locate_service_template( $tag, $layout );

		if ( null === $template_file ) {
			// Fallback 1: Default-Layout des gleichen Service.
			$template_file = $this->locate_service_template( $tag, 'default' );
		}

		if ( null === $template_file ) {
			// Fallback 2: Template-Gruppe aus Registry pruefen.
			// Ermoeglicht z.B. lxmio -> mio Fallback.
			$fallback_tag = $this->get_template_fallback_tag( $tag );
			if ( null !== $fallback_tag ) {
				$template_file = $this->locate_service_template( $fallback_tag, $layout );
				if ( null === $template_file ) {
					$template_file = $this->locate_service_template( $fallback_tag, 'default' );
				}
			}
		}

		if ( null === $template_file ) {
			return '';
		}

		// Template-Variablen vorbereiten.
		$service_class = 'dhps-service--' . sanitize_html_class( $tag );
		$layout_class  = 'dhps-layout--' . sanitize_html_class( $layout );
		$custom_class  = '' !== $css_class ? ' ' . sanitize_html_class( $css_class ) : '';

		// Template rendern via Output-Buffering.
		ob_start();
		include $template_file;
		$output = ob_get_clean();

		// Demo-Badge hinzufuegen wenn Demo aktiv.
		if ( null !== $this->demo_manager && $this->demo_manager->is_demo_active( $tag ) ) {
			$output = $this->wrap_demo_badge( $output, $tag );
		}

		return $output;
	}

	/**
	 * Sucht ein Service-spezifisches Template.
	 *
	 * Suchreihenfolge:
	 * 1. Theme-Override: {theme}/dhps/services/{tag}/{layout}.php
	 * 2. Plugin-Template: {plugin}/public/views/services/{tag}/{layout}.php
	 *
	 * @since 0.9.0
	 *
	 * @param string $tag    Service-Tag (z.B. 'mio').
	 * @param string $layout Layout-Name (z.B. 'default').
	 *
	 * @return string|null Absoluter Pfad zum Template oder null wenn nicht gefunden.
	 */
	public function locate_service_template( string $tag, string $layout ): ?string {
		$safe_tag  = sanitize_file_name( $tag );
		$safe_name = sanitize_file_name( $layout );
		$rel_path  = 'services/' . $safe_tag . '/' . $safe_name . '.php';

		// 1. Theme-Override pruefen.
		$theme_template = get_stylesheet_directory() . '/dhps/' . $rel_path;

		if ( file_exists( $theme_template ) ) {
			return $theme_template;
		}

		// 2. Plugin-Template pruefen.
		$plugin_template = DEUBNER_HP_SERVICES_PATH . 'public/views/' . $rel_path;

		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return null;
	}

	/**
	 * Wrappt den Inhalt mit einem Demo-Badge-Overlay.
	 *
	 * Zeigt einen dezenten Banner ueber dem Service-Inhalt, der darauf
	 * hinweist, dass es sich um Demo-Inhalte handelt, mit einem Link
	 * zum Shop fuer die Freischaltung.
	 *
	 * @since 0.6.0
	 *
	 * @param string $html Der gerenderte HTML-Inhalt.
	 * @param string $tag  Shortcode-Tag des Service.
	 *
	 * @return string HTML mit Demo-Badge.
	 */
	private function wrap_demo_badge( string $html, string $tag ): string {
		$shop_url = 'https://www.deubner-steuern.de/shop/homepage-services.html';

		$badge = '<div class="dhps-demo-banner">'
			. '<span class="dhps-demo-banner__text">'
			. esc_html__( 'Demo-Inhalt', 'deubner_hp_services' )
			. '</span>'
			. '<a href="' . esc_url( $shop_url ) . '" class="dhps-demo-banner__link" target="_blank" rel="noopener">'
			. esc_html__( 'Jetzt freischalten', 'deubner_hp_services' )
			. '</a>'
			. '</div>';

		// Demo-Badge VOR dem Inhalt, innerhalb des Service-Containers.
		// Suche das erste '>' nach der Opening-Tag-Klammer des dhps-service Divs.
		$pos = strpos( $html, '>' );
		if ( false !== $pos ) {
			return substr( $html, 0, $pos + 1 ) . $badge . substr( $html, $pos + 1 );
		}

		return $badge . $html;
	}

	/**
	 * Sucht das passende Layout-Template.
	 *
	 * Prueft zuerst ob ein Theme-Override existiert unter
	 * {stylesheet_directory}/dhps/layout-{name}.php, dann das
	 * Plugin-eigene Template unter public/views/layout-{name}.php.
	 *
	 * @since 0.5.0
	 *
	 * @param string $layout Layout-Name (z.B. 'default', 'card').
	 *
	 * @return string|null Absoluter Pfad zum Template oder null wenn nicht gefunden.
	 */
	public function locate_template( string $layout ): ?string {
		$file_name = 'layout-' . sanitize_file_name( $layout ) . '.php';

		// 1. Theme-Override pruefen.
		$theme_template = get_stylesheet_directory() . '/dhps/' . $file_name;

		if ( file_exists( $theme_template ) ) {
			return $theme_template;
		}

		// 2. Plugin-Template pruefen.
		$plugin_template = DEUBNER_HP_SERVICES_PATH . 'public/views/' . $file_name;

		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return null;
	}

	/**
	 * Gibt die Liste der verfuegbaren Layout-Varianten zurueck.
	 *
	 * Wird im Admin-Bereich fuer Dropdown-Selects und zur Validierung
	 * der Layout-Auswahl verwendet.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, string> Assoziatives Array: slug => Anzeigename.
	 */
	public static function get_available_layouts(): array {
		return array(
			'default' => 'Standard',
			'card'    => 'Card',
			'compact' => 'Kompakt',
		);
	}

	/**
	 * Gibt den Template-Fallback-Tag fuer einen Service zurueck.
	 *
	 * Ermoeglicht Services, die Templates eines anderen Service zu verwenden.
	 * Beispiel: lxmio faellt auf mio-Templates zurueck, wenn keine
	 * eigenen lxmio-Templates existieren.
	 *
	 * @since 0.9.0
	 *
	 * @param string $tag Service-Tag (z.B. 'lxmio').
	 *
	 * @return string|null Fallback-Tag oder null wenn kein Fallback definiert.
	 */
	private function get_template_fallback_tag( string $tag ): ?string {
		/**
		 * Filtert die Template-Fallback-Zuordnung.
		 *
		 * @since 0.9.0
		 *
		 * @param array $fallbacks Assoziatives Array: Service-Tag => Fallback-Tag.
		 */
		$fallbacks = apply_filters( 'dhps_template_fallbacks', array(
			'lxmio' => 'mio',
		) );

		return $fallbacks[ $tag ] ?? null;
	}
}
