<?php
/**
 * Parser fuer den LP (Lexplain) Service.
 *
 * Erbt vom TP-Parser, da beide Services die identische API-HTML-Struktur
 * verwenden (mandantenvideo.de iframes, videoblock_rubrik, toggleDiv).
 * Ueberschreibt nur die service-spezifischen Konstanten.
 *
 * Unterschied zu TP:
 * - service_tag: 'lp' statt 'tp'
 * - iframe-service-default: 'lexplain' statt 'taxplain'
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Parsers
 * @since      0.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_LP_Parser
 *
 * @since 0.11.0
 */
class DHPS_LP_Parser extends DHPS_TP_Parser {

	/**
	 * Parst rohes LP-HTML in ein strukturiertes Array.
	 *
	 * Ruft die geerbte parse()-Methode auf und ersetzt das service_tag
	 * sowie service-Felder in featured_video und categories.
	 *
	 * @since 0.11.0
	 *
	 * @param string $html Rohes HTML aus der API-Antwort.
	 *
	 * @return array Strukturiertes Array mit service_tag='lp'.
	 */
	public function parse( string $html ): array {
		$data = parent::parse( $html );

		// service_tag auf 'lp' aendern.
		$data['service_tag'] = 'lp';

		// service-Feld in Featured Video auf 'lexplain' aendern.
		if ( ! empty( $data['featured_video'] ) && is_array( $data['featured_video'] ) ) {
			$data['featured_video']['service'] = 'lexplain';
		}

		// service-Feld in allen Video-Eintraegen der Categories aendern.
		if ( ! empty( $data['categories'] ) ) {
			foreach ( $data['categories'] as &$category ) {
				if ( ! empty( $category['videos'] ) ) {
					foreach ( $category['videos'] as &$video ) {
						$video['service'] = 'lexplain';
					}
					unset( $video );
				}
			}
			unset( $category );
		}

		return $data;
	}
}
