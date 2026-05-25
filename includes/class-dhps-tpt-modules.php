<?php
/**
 * TPT-Modules-Layer: reichert $data um Admin-konfigurierte Texte/Settings an.
 *
 * Verschiebt die get_option('dhps_tpt_*')-Reads aus den Templates in einen
 * dedizierten Layer. Templates bekommen die Settings als Teil von $data
 * ueber den Renderer-Filter dhps_pipeline_data_tpt (eingefuehrt v0.14.5
 * in DHPS_Renderer::render_parsed).
 *
 * Hintergrund (Tech-Debt v0.14.3 / Triage v0.14.5 Ticket #2):
 *   Admin-konfigurierte Texte (Ueberschrift, Teasertext) sind keine
 *   Anreicherung von API-Payloads und gehoeren daher nicht in den
 *   DHPS_TPT_Parser. Stattdessen separater Modules-Layer, der via Filter
 *   das $data-Array um den Schluessel 'tpt_config' erweitert.
 *
 * Daten-Vertrag:
 *   $data['tpt_config'] = array(
 *       'ueberschrift' => string,  // default ''
 *       'teasertext'   => string,  // default ''
 *   );
 *
 * BC-Hinweis: Templates lesen 'tpt_config' mit Null-Coalescing-Fallback,
 * d.h. Theme-Overrides ohne Modules-Layer-Bindung funktionieren weiter
 * (leere Strings statt fehlender Werte).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.14.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_TPT_Modules
 *
 * Reichert TPT-Renderdaten um Admin-konfigurierte Texte an.
 *
 * @since 0.14.5
 */
class DHPS_TPT_Modules {

	/**
	 * Konstruktor: bindet den Filter-Hook an die enrich_data-Methode.
	 *
	 * @since 0.14.5
	 */
	public function __construct() {
		// Filter feuert in DHPS_Renderer::render_parsed() unmittelbar vor
		// dem include des Service-Templates.
		add_filter( 'dhps_pipeline_data_tpt', array( $this, 'enrich_data' ), 10, 2 );
	}

	/**
	 * Reichert das $data-Array eines TPT-Renders um Admin-Texte an.
	 *
	 * Liest die Admin-konfigurierten Optionen serverseitig und packt sie
	 * unter $data['tpt_config'], damit die TPT-Templates kein get_option()
	 * mehr aufrufen muessen.
	 *
	 * @since 0.14.5
	 *
	 * @param array  $data   Parser-Output (mit 'video' + 'service_tag').
	 * @param string $layout Aktuelles Layout (default|card|compact).
	 * @return array Angereichertes Data-Array.
	 */
	public function enrich_data( $data, $layout = 'default' ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$data['tpt_config'] = array(
			'ueberschrift' => (string) get_option( 'dhps_tpt_ues', '' ),
			'teasertext'   => (string) get_option( 'dhps_tpt_teasertext', '' ),
		);

		return $data;
	}
}
