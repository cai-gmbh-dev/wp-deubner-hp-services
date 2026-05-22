<?php
/**
 * Parser fuer den TC (Tax-Rechner / TaxCalc) Service.
 *
 * TC ist konzeptuell anders als die anderen Services:
 * - Liefert kein strukturiertes Content-Format (News, Videos, Merkblaetter)
 * - Sondern: 25+ interaktive Rechner als Akkordeon mit Inline-JS
 * - Die Akkordeon-Logik (test_einblenden/test_ausblenden) ist Bestandteil der Response
 *
 * Daher: Dieser "Parser" extrahiert nicht, sondern erkennt nur den Empty-State
 * und reicht das HTML strukturiert ans Template durch.
 *
 * Empty-State: <div class="taxcalc"><p class="sm_buttons"></p></div>
 * -> Bedeutet: kdnr ungueltig oder keine Rechner freigeschaltet
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Parsers
 * @since      0.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_TC_Parser
 *
 * Wrapper-Parser fuer TC. Extrahiert kein strukturiertes Content-Modell,
 * sondern liefert nur Status-Flags + Raw-HTML fuer Template-Rendering.
 *
 * @since 0.13.0
 */
class DHPS_TC_Parser implements DHPS_Parser_Interface {

	/**
	 * Parst rohes TC-HTML.
	 *
	 * @since 0.13.0
	 *
	 * @param string $html Rohes HTML aus der API-Antwort.
	 *
	 * @return array Strukturiertes Array:
	 *               - 'html'        (string)  Original-HTML (Inline-JS bleibt erhalten)
	 *               - 'is_empty'    (bool)    True bei Empty-State (keine Rechner)
	 *               - 'service_tag' (string)  'tc'
	 */
	public function parse( string $html ): array {
		$is_empty = $this->detect_empty_state( $html );

		return array(
			'html'        => $html,
			'is_empty'    => $is_empty,
			'service_tag' => 'tc',
		);
	}

	/**
	 * Erkennt Empty-State der TC-API-Response.
	 *
	 * Die TC-API liefert bei ungueltigem kdnr / nicht freigeschalteten
	 * Rechnern einen leeren Container:
	 * <div class="taxcalc"><p class="sm_buttons"></p></div>
	 *
	 * @since 0.13.0
	 *
	 * @param string $html Raw HTML.
	 *
	 * @return bool True wenn leer.
	 */
	private function detect_empty_state( string $html ): bool {
		// Pattern 1: Komplett leerer Container -> definitiv empty.
		if ( preg_match( '#<div class="taxcalc">\s*<p class="sm_buttons">\s*</p>\s*</div>#', $html ) ) {
			return true;
		}

		// Wenn calc_area-Elemente vorhanden sind, gibt es definitiv Rechner -> NICHT empty.
		if ( false !== strpos( $html, 'calc_area' ) ) {
			return false;
		}

		// Pattern 2: Weder calc_area noch webcalc -> empty.
		if ( false === strpos( $html, 'webcalc' ) ) {
			return true;
		}

		// Pattern 3: HTML zu kurz fuer echten Content (nur Scripts/Styles, kein Body).
		$content_only = preg_replace( '#<script[^>]*>.*?</script>#s', '', $html );
		$content_only = preg_replace( '#<style[^>]*>.*?</style>#s', '', $content_only );
		$content_only = trim( strip_tags( $content_only ) );

		if ( strlen( $content_only ) < 50 ) {
			return true;
		}

		return false;
	}
}
