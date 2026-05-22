<?php
/**
 * Parser fuer den TPT (TaxPlain Teaser) Service.
 *
 * TPT nutzt denselben Endpoint wie TP, aber mit teasermodus=1 - liefert
 * nur einen einzelnen Video-Block (statt der vollen Galerie). Wird typisch
 * als Sidebar-/Footer-/Startseiten-Teaser eingebettet, der zur vollen
 * TP-Galerie verlinkt.
 *
 * HTML-Struktur (Subset von TP):
 * - div.videotipps.videoteaser (Wrapper)
 *   - div.videoblock (genau 1x - das aktuelle Video)
 *     - h5.videotitel
 *     - iframe.inlinevideo (mandantenvideo.de)
 *     - div.teaser (Beschreibung)
 *     - Share-Buttons
 *
 * Im Gegensatz zu TP fehlen:
 * - div.aktuelles_video (Wrapper)
 * - h3.ues_akt_vt (Featured-Header)
 * - Mehrere videoblocks / Kategorisierung
 *
 * SICHERHEIT: kdnr wird NICHT extrahiert (kommt serverseitig via AJAX-Proxy).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Parsers
 * @since      0.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_TPT_Parser
 *
 * Erbt von DHPS_TP_Parser und ueberschreibt nur parse(), um den einzelnen
 * Video-Block zu extrahieren statt der Featured/Kategorien-Struktur.
 *
 * @since 0.12.0
 */
class DHPS_TPT_Parser extends DHPS_TP_Parser {

	/**
	 * Parst rohes TPT-HTML in ein strukturiertes Array.
	 *
	 * @since 0.12.0
	 *
	 * @param string $html Rohes HTML aus der API-Antwort.
	 *
	 * @return array Strukturiertes Array mit:
	 *               - 'video'       (array|null) Das einzelne Teaser-Video.
	 *               - 'service_tag' (string)     'tpt'.
	 */
	public function parse( string $html ): array {
		$doc = new DOMDocument();

		$wrapped_html = '<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';

		libxml_use_internal_errors( true );
		$doc->loadHTML( $wrapped_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $doc );

		// Den einzelnen videoblock finden.
		$blocks = $xpath->query( '//div[contains(@class, "videoblock")]' );
		$video  = null;

		if ( $blocks && $blocks->length > 0 ) {
			$first_block = $blocks->item( 0 );
			if ( $first_block instanceof DOMElement ) {
				$video = $this->parse_video_block( $first_block, $xpath, false );
			}
		}

		return array(
			'video'       => $video,
			'service_tag' => 'tpt',
		);
	}
}
