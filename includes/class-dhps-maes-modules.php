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

	/**
	 * Atts-Whitelist, die einen Force-Legacy auf der Collection-Bridge
	 * (v0.17.1) ausloest. Wenn einer dieser Atts in einem Sub-Shortcode
	 * gesetzt ist, wird die Filter-Semantik nur auf $parsed_data (Legacy-
	 * Array) angewendet - der Adapter kennt diese Filter NICHT und wuerde
	 * sonst eine Collection liefern, die nicht zum gefilterten Legacy-
	 * Datensatz passt (Sub-Shortcode-Template-Drift).
	 *
	 * Tech-Debt TD-V0171-1: Sub-Shortcode-Collection-Filter ist als
	 * Folge-Release-Aufgabe (v0.17.2+) angedacht, sobald Item-Index-
	 * Tracking via Item.meta.video_index in Adapter und Templates
	 * konsistent vorliegt.
	 *
	 * Quellen-Recherche (Discovery 27 Sektion 6.3): heutige MAES-Sub-
	 * Shortcode-Filter sind `einzelvideo` + `videoliste` ([maes_videos]).
	 * `kategorie` und `rubrik` sind defensiv vorab eingetragen, falls
	 * spaetere Modules-Erweiterungen sie nutzen - sie sind heute Inert
	 * (keiner der 3 Sub-Shortcodes liest sie).
	 *
	 * @since 0.17.1
	 * @var array<int,string>
	 */
	private const FORCE_LEGACY_ATTS = array( 'einzelvideo', 'videoliste', 'kategorie', 'rubrik' );

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
	 * Liefert die MAES-Daten als {@see DHPS_Content_Collection} fuer den
	 * Sub-Shortcode-Pfad (v0.17.1-Brueckenmethode).
	 *
	 * Diese Methode umgeht die Content-Pipeline (die fuer die Top-Level-
	 * Shortcodes wie [maes] zustaendig ist) und nutzt stattdessen den
	 * Helper {@see dhps_build_collection_for()}, um aus dem bereits
	 * gecached'en Parser-Output direkt eine Collection zu erzeugen.
	 *
	 * Force-Legacy: Wenn der Aufrufer Filter-Atts uebergibt (z.B.
	 * `einzelvideo` oder `videoliste`), wird `null` zurueckgegeben.
	 * Begruendung: der Adapter weiss nichts von Sub-Shortcode-Filter-
	 * Atts. Eine Collection wuerde alle Items enthalten, das Legacy-
	 * Array hingegen den gefilterten Subset - Template-Drift. Sicherer
	 * Default: Force-Legacy ueber {@see FORCE_LEGACY_ATTS}.
	 *
	 * @since 0.17.1
	 *
	 * @param string $section Logische Sektion ('videos'|'merkblaetter'|'aktuelles').
	 *                        Aktuell nur fuer Debug-Logging und zukuenftige
	 *                        Selektivitaet vorgesehen; der Adapter liefert
	 *                        die volle Collection und Templates filtern per
	 *                        $item->type.
	 * @param array  $atts    Sub-Shortcode-Atts. Wenn FORCE_LEGACY_ATTS
	 *                        gesetzt sind, returnt die Methode null
	 *                        (Force-Legacy).
	 *
	 * @return DHPS_Content_Collection|null Collection wenn moeglich, null bei
	 *                                       fehlender Daten / Force-Legacy /
	 *                                       Adapter-Fehler.
	 */
	public function get_collection( string $section, array $atts = array() ): ?DHPS_Content_Collection {
		unset( $section ); // aktuell nur dokumentarisch, koennte spaeter zur Selektion dienen.

		// Force-Legacy bei Filter-Atts (Discovery 27 Sektion 6.3 Caveat 1).
		foreach ( self::FORCE_LEGACY_ATTS as $force_att ) {
			if ( ! isset( $atts[ $force_att ] ) ) {
				continue;
			}
			$value = $atts[ $force_att ];
			if ( is_string( $value ) && '' !== trim( $value ) && '0' !== trim( $value ) ) {
				return null;
			}
			if ( is_int( $value ) && 0 !== $value ) {
				return null;
			}
		}

		$data = $this->get_data();
		if ( null === $data ) {
			return null;
		}

		return dhps_build_collection_for( 'maes', $data );
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

		// v0.18.0 Sub-Shortcode-Filter-Migration (Discovery 33 Sektion 2.1
		// Option B): Filter wirken auf $data VOR Adapter-Build. Damit liefert
		// der Adapter eine Collection mit gefilterten Items - Templates haben
		// keinen else-Branch mehr und sehen IMMER eine Collection.
		$filtered_data = $data;
		$filtered_data['videos'] = $videos;
		$collection = dhps_build_collection_for( 'maes', $filtered_data );

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

		// Seit v0.14.1: Die modernisierten Merkblaetter-Templates nutzen das
		// Component-System (ContentList + ContentCard mit type=document) und
		// brauchen kein eigenes MMB-Akkordeon-JS mehr. Spart ~10 KB JS bei
		// [maes_merkblaetter]-only-Seiten.

		// v0.18.0: Collection IMMER bauen (analog render_videos). [maes_merkblaetter]
		// hat heute keine Filter-Atts, daher gefiltertes $data === volles $data.
		$filtered_data = $data;
		$filtered_data['merkblaetter'] = $merkblaetter;
		$collection = dhps_build_collection_for( 'maes', $filtered_data );

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

		// v0.18.0: Collection IMMER bauen (analog render_videos). [maes_aktuelles]
		// hat heute keine Filter-Atts, daher gefiltertes $data === volles $data.
		$filtered_data = $data;
		$filtered_data['news'] = $news;
		$collection = dhps_build_collection_for( 'maes', $filtered_data );

		ob_start();
		include $template;
		return ob_get_clean();
	}
}
