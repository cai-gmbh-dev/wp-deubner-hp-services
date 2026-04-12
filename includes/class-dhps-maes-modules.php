<?php
/**
 * MAES modulare Shortcodes: Videos, Merkblaetter, Aktuelles.
 *
 * Drei eigenstaendige Shortcodes die den MAES-Parser nutzen aber
 * die Daten ueber die bestehenden TP/MMB/MIO-Templates rendern.
 *
 * - [maes_videos]       -> TP-Video-Grid mit MAES-Videodaten
 * - [maes_merkblaetter] -> MMB-Akkordeon mit MAES-Merkblaettern
 * - [maes_aktuelles]    -> Uebersichts-News aus MAES
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.10.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_MAES_Modules
 *
 * Registriert drei Shortcodes fuer modulare MAES-Einbindung.
 *
 * @since 0.10.1
 */
class DHPS_MAES_Modules {

	/** @var DHPS_API_Client */
	private DHPS_API_Client $client;

	/** @var DHPS_Cache */
	private DHPS_Cache $cache;

	/**
	 * @param DHPS_API_Client $client API-Client.
	 * @param DHPS_Cache      $cache  Cache-Instanz.
	 */
	public function __construct( DHPS_API_Client $client, DHPS_Cache $cache ) {
		$this->client = $client;
		$this->cache  = $cache;

		add_shortcode( 'maes_videos', array( $this, 'render_videos' ) );
		add_shortcode( 'maes_merkblaetter', array( $this, 'render_merkblaetter' ) );
		add_shortcode( 'maes_aktuelles', array( $this, 'render_aktuelles' ) );
	}

	/**
	 * Holt und cached die MAES-Daten.
	 *
	 * @param int $cache_ttl Cache-TTL.
	 *
	 * @return array|null Parsed data oder null bei Fehler.
	 */
	private function get_data( int $cache_ttl = 3600 ): ?array {
		$service = DHPS_Service_Registry::get_service( 'maes' );
		if ( null === $service ) {
			return null;
		}

		$kdnr = get_option( $service['auth_option'], '' );
		if ( empty( $kdnr ) ) {
			return null;
		}

		$params    = array( 'kdnr' => $kdnr, 'modus' => 'p' );
		$cache_key = 'dhps_p_' . md5( $service['endpoint'] . '|' . wp_json_encode( $params ) );
		$parsed    = $this->cache->get_data( $cache_key );

		if ( null === $parsed ) {
			$html = $this->client->fetch_content( $service['endpoint'], $params, $cache_ttl );
			if ( empty( $html ) || 0 === strpos( trim( $html ), '<!-- DHPS:' ) ) {
				return null;
			}

			$parser = DHPS_Parser_Registry::get_parser( 'maes' );
			if ( null === $parser ) {
				return null;
			}

			$parsed = $parser->parse( $html );
			$parsed['service_tag'] = 'maes';
			$this->cache->set_data( $cache_key, $parsed, $cache_ttl );
		}

		return $parsed;
	}

	/**
	 * [maes_videos] - Rendert MAES-Videos im TP-Stil.
	 *
	 * @param array $atts Shortcode-Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_videos( $atts ): string {
		$atts = shortcode_atts( array(
			'layout'      => 'default',
			'columns'     => '2',
			'einzelvideo' => '0',
			'videoliste'  => '',
			'lazy_count'  => '0',
			'lazy_mode'   => 'manual',
			'class'       => '',
			'cache'       => '3600',
		), $atts, 'maes_videos' );

		$data = $this->get_data( absint( $atts['cache'] ) );
		if ( null === $data || empty( $data['videos'] ) ) {
			return '';
		}

		$videos        = $data['videos'];

		// Video-Filter: Einzelvideo oder Videoliste.
		$einzelvideo = absint( $atts['einzelvideo'] );
		$videoliste  = trim( $atts['videoliste'] );

		if ( $einzelvideo > 0 && isset( $videos[ $einzelvideo - 1 ] ) ) {
			$videos = array( $videos[ $einzelvideo - 1 ] );
		} elseif ( ! empty( $videoliste ) ) {
			$indices  = array_map( 'absint', explode( ',', $videoliste ) );
			$filtered = array();
			foreach ( $indices as $idx ) {
				if ( $idx > 0 && isset( $videos[ $idx - 1 ] ) ) {
					$filtered[] = $videos[ $idx - 1 ];
				}
			}
			if ( ! empty( $filtered ) ) {
				$videos = $filtered;
			}
		}

		$columns       = absint( $atts['columns'] );
		$layout        = sanitize_key( $atts['layout'] );
		$custom_class  = ! empty( $atts['class'] ) ? ' ' . sanitize_html_class( $atts['class'] ) : '';
		$video_mode    = 'inline';
		$style_preset  = 'default';
		$lazy_count    = absint( $atts['lazy_count'] );
		$lazy_mode     = sanitize_key( $atts['lazy_mode'] );

		if ( $columns < 1 || $columns > 4 ) {
			$columns = 2;
		}

		$layout_suffix = ( 'default' !== $layout ) ? '-' . $layout : '';
		$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/videos' . $layout_suffix . '.php';
		if ( ! file_exists( $template ) ) {
			$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/videos.php';
		}
		if ( ! file_exists( $template ) ) {
			return '';
		}

		wp_enqueue_script( 'dhps-tp-js' );

		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * [maes_merkblaetter] - Rendert MAES-Merkblaetter im MMB-Stil.
	 *
	 * @param array $atts Shortcode-Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_merkblaetter( $atts ): string {
		$atts = shortcode_atts( array(
			'layout' => 'default',
			'class'  => '',
			'cache'  => '3600',
		), $atts, 'maes_merkblaetter' );

		$data = $this->get_data( absint( $atts['cache'] ) );
		if ( null === $data || empty( $data['merkblaetter'] ) ) {
			return '';
		}

		$merkblaetter     = $data['merkblaetter'];
		$layout           = sanitize_key( $atts['layout'] );
		$custom_class     = ! empty( $atts['class'] ) ? ' ' . sanitize_html_class( $atts['class'] ) : '';
		$show_description = true;
		$accordion_open   = true;

		$layout_suffix = ( 'default' !== $layout ) ? '-' . $layout : '';
		$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/merkblaetter' . $layout_suffix . '.php';
		if ( ! file_exists( $template ) ) {
			$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/merkblaetter.php';
		}
		if ( ! file_exists( $template ) ) {
			return '';
		}

		wp_enqueue_script( 'dhps-mmb-js' );

		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * [maes_aktuelles] - Rendert MAES-Uebersicht/Aktuelles.
	 *
	 * @param array $atts Shortcode-Attribute.
	 *
	 * @return string HTML.
	 */
	public function render_aktuelles( $atts ): string {
		$atts = shortcode_atts( array(
			'layout'  => 'default',
			'columns' => '2',
			'class'   => '',
			'cache'   => '3600',
		), $atts, 'maes_aktuelles' );

		$data = $this->get_data( absint( $atts['cache'] ) );
		if ( null === $data || empty( $data['news'] ) ) {
			return '';
		}

		$news         = $data['news'];
		$layout       = sanitize_key( $atts['layout'] );
		$columns      = absint( $atts['columns'] );
		$custom_class = ! empty( $atts['class'] ) ? ' ' . sanitize_html_class( $atts['class'] ) : '';
		$show_teaser  = true;
		$first_open   = false;

		$layout_suffix = ( 'default' !== $layout ) ? '-' . $layout : '';
		$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/aktuelles' . $layout_suffix . '.php';
		if ( ! file_exists( $template ) ) {
			$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/aktuelles.php';
		}
		if ( ! file_exists( $template ) ) {
			return '';
		}

		ob_start();
		include $template;
		return ob_get_clean();
	}
}
