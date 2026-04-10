<?php
/**
 * Content-Pipeline: Orchestrator fuer Parser, Cache und Renderer.
 *
 * Verbindet die HTML-Parsing-Schicht mit dem bestehenden API-Client
 * und Renderer. Services mit registriertem Parser werden geparst und
 * ueber Service-spezifische Templates gerendert. Services ohne Parser
 * fallen auf den bestehenden Raw-HTML-Pfad zurueck.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Content_Pipeline
 *
 * Implementiert das 2-Layer-Caching:
 * - L1: Raw HTML (bestehend, in DHPS_API_Client)
 * - L2: Parsed Data (neu, serialisierte Arrays)
 *
 * @since 0.9.0
 */
class DHPS_Content_Pipeline {

	/**
	 * Prefix fuer L2-Cache-Keys (geparste Daten).
	 *
	 * @since 0.9.0
	 * @var string
	 */
	private const PARSED_CACHE_PREFIX = 'dhps_p_';

	/**
	 * API-Client fuer den Abruf der Service-Inhalte.
	 *
	 * @since 0.9.0
	 * @var DHPS_API_Client
	 */
	private DHPS_API_Client $api_client;

	/**
	 * Renderer fuer die Template-Ausgabe.
	 *
	 * @since 0.9.0
	 * @var DHPS_Renderer
	 */
	private DHPS_Renderer $renderer;

	/**
	 * Cache-Instanz fuer L2-Caching der geparsten Daten.
	 *
	 * @since 0.9.0
	 * @var DHPS_Cache
	 */
	private DHPS_Cache $cache;

	/**
	 * Konstruktor.
	 *
	 * @since 0.9.0
	 *
	 * @param DHPS_API_Client $api_client API-Client-Instanz.
	 * @param DHPS_Renderer   $renderer   Renderer-Instanz.
	 * @param DHPS_Cache      $cache      Cache-Instanz fuer L2-Caching.
	 */
	public function __construct( DHPS_API_Client $api_client, DHPS_Renderer $renderer, DHPS_Cache $cache ) {
		$this->api_client = $api_client;
		$this->renderer   = $renderer;
		$this->cache      = $cache;
	}

	/**
	 * Rendert einen Service-Inhalt ueber die Content-Pipeline.
	 *
	 * Ablauf:
	 * 1. Raw HTML von API abrufen (L1 Cache via API Client)
	 * 2. Pruefen ob ein Parser fuer den Service existiert
	 *    - Nein: Fallback auf bestehenden Raw-HTML-Renderer
	 *    - Ja: Weiter mit Schritt 3
	 * 3. L2 Cache pruefen (geparste Daten)
	 * 4. Bei L2-Miss: HTML parsen und L2 cachen
	 * 5. Geparste Daten ueber Service-Template rendern
	 *
	 * @since 0.9.0
	 *
	 * @param string $tag       Service-Tag (z.B. 'mio', 'tp').
	 * @param string $endpoint  API-Endpoint-Pfad.
	 * @param array  $params    API-Parameter.
	 * @param int    $cache_ttl Cache-TTL in Sekunden.
	 * @param string $layout    Layout-Name (z.B. 'default', 'card').
	 * @param string $css_class Optionale CSS-Klasse(n).
	 *
	 * @return string Gerendertes HTML.
	 */
	public function render_service(
		string $tag,
		string $endpoint,
		array $params,
		int $cache_ttl,
		string $layout,
		string $css_class
	): string {
		// 1. Raw HTML von API abrufen (L1 Cache).
		$html = $this->api_client->fetch_content( $endpoint, $params, $cache_ttl );

		// Leeren Output oder Fehler-Kommentare direkt durchreichen.
		if ( '' === $html || 0 === strpos( trim( $html ), '<!-- DHPS:' ) ) {
			return $html;
		}

		// 2. Pruefen ob ein Parser fuer diesen Service existiert.
		$parser = DHPS_Parser_Registry::get_parser( $tag );

		if ( null === $parser ) {
			// Kein Parser registriert: Fallback auf bestehenden Renderer.
			return $this->renderer->render( $html, $tag, $layout, $css_class );
		}

		// 3. L2 Cache pruefen (geparste Daten).
		$l2_cache_key = $this->generate_parsed_cache_key( $endpoint, $params );
		$parsed_data  = $this->cache->get_data( $l2_cache_key );

		if ( null === $parsed_data ) {
			// 4. L2-Miss: HTML parsen.
			$parsed_data = $parser->parse( $html );

			// Service-Tag im Ergebnis setzen/ueberschreiben (fuer Template-Rendering).
			$parsed_data['service_tag'] = $tag;

			// Nur nicht-leere Ergebnisse cachen.
			if ( ! empty( $parsed_data ) ) {
				$this->cache->set_data( $l2_cache_key, $parsed_data, $cache_ttl );
			}
		}

		// 5. Geparste Daten ueber Service-Template rendern.
		return $this->renderer->render_parsed( $parsed_data, $tag, $layout, $css_class );
	}

	/**
	 * Generiert einen L2-Cache-Key fuer geparste Daten.
	 *
	 * Verwendet das gleiche Schema wie DHPS_Cache::generate_key(),
	 * aber mit dem Prefix 'dhps_p_' statt 'dhps_'.
	 *
	 * @since 0.9.0
	 *
	 * @param string $endpoint API-Endpoint-Pfad.
	 * @param array  $params   API-Parameter.
	 *
	 * @return string Cache-Key im Format 'dhps_p_{md5}'.
	 */
	private function generate_parsed_cache_key( string $endpoint, array $params ): string {
		ksort( $params );
		$raw = $endpoint . '|' . wp_json_encode( $params );

		return self::PARSED_CACHE_PREFIX . md5( $raw );
	}
}
