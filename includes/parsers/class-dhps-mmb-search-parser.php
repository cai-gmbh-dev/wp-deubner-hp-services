<?php
/**
 * Parser fuer MMB-Suchergebnisse (AJAX-Response).
 *
 * Transformiert die AJAX-geladene Such-Response von
 * hintergrundladen.php in ein strukturiertes PHP-Array.
 *
 * Die Suchfunktion liefert ein anderes HTML-Format als die
 * initiale Seitenansicht. Suchergebnisse enthalten Merkblatt-Titel
 * als Links mit toggle-IDs und optionale Beschreibungen.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Parsers
 * @since      0.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_MMB_Search_Parser
 *
 * Parst die AJAX-Such-Response des MMB-Service. Das HTML enthaelt
 * Suchergebnis-Eintraege als li > a-Elemente mit toggle-Funktionalitaet
 * und optional Merkblatt-Details (Beschreibung, PDF-Links).
 *
 * @since 0.9.1
 */
class DHPS_MMB_Search_Parser {

	/**
	 * Parst die Such-Response in ein strukturiertes Array.
	 *
	 * @since 0.9.1
	 *
	 * @param string $html Rohes HTML aus der AJAX-Search-Response.
	 *
	 * @return array Strukturiertes Array mit Suchergebnissen.
	 */
	public function parse( string $html ): array {
		$result = array(
			'results'     => array(),
			'total_count' => 0,
			'query'       => '',
		);

		if ( empty( trim( $html ) ) ) {
			return $result;
		}

		$doc     = new DOMDocument();
		$wrapped = '<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';

		libxml_use_internal_errors( true );
		$doc->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $doc );

		// Suchergebnis-Eintraege parsen.
		// Struktur: ul > li > a (Merkblatt-Titel mit toggle)
		// Gefolgt von div (Merkblatt-Detail mit Beschreibung + PDF).
		$items = $xpath->query( '//li' );

		foreach ( $items as $item ) {
			$entry = $this->parse_search_item( $item, $xpath );

			if ( ! empty( $entry['title'] ) ) {
				$result['results'][] = $entry;
			}
		}

		$result['total_count'] = count( $result['results'] );

		return $result;
	}

	/**
	 * Parst ein einzelnes Suchergebnis-Element.
	 *
	 * @since 0.9.1
	 *
	 * @param DOMElement $item  Das li-Element.
	 * @param DOMXPath   $xpath XPath-Instanz.
	 *
	 * @return array Merkblatt-Daten.
	 */
	private function parse_search_item( DOMElement $item, DOMXPath $xpath ): array {
		$entry = array(
			'id'          => '',
			'title'       => '',
			'description' => '',
			'pdf_params'  => array(),
		);

		// Titel aus dem Link extrahieren.
		$links = $item->getElementsByTagName( 'a' );

		if ( $links->length > 0 ) {
			$link          = $links->item( 0 );
			$entry['title'] = trim( $link->textContent );

			// ID aus dem onclick/href-Pattern extrahieren.
			$onclick = $link->getAttribute( 'href' );
			if ( preg_match( '/item[_]?(\d+)/', $onclick, $matches ) ) {
				$entry['id'] = $matches[1];
			}
		}

		// Detail-Container suchen (div nach dem Link).
		$detail_divs = $xpath->query( './/div[contains(@class, "merkblatt_intro")]', $item );

		if ( $detail_divs->length > 0 ) {
			$entry['description'] = trim( $detail_divs->item( 0 )->textContent );
		}

		// PDF-Download-Link suchen und Parameter extrahieren (OHNE kdnr).
		$download_divs = $xpath->query( './/div[contains(@class, "merkblatt_download")]', $item );

		if ( $download_divs->length > 0 ) {
			$pdf_links = $download_divs->item( 0 )->getElementsByTagName( 'a' );

			if ( $pdf_links->length > 0 ) {
				$href = $pdf_links->item( 0 )->getAttribute( 'href' );
				$entry['pdf_params'] = $this->extract_safe_pdf_params( $href );
			}
		}

		return $entry;
	}

	/**
	 * Extrahiert sichere PDF-Parameter aus einer URL.
	 *
	 * SICHERHEIT: Filtert kd_nr und andere sensitive Parameter heraus.
	 * Nur erlaubte Keys werden uebernommen.
	 *
	 * @since 0.9.1
	 *
	 * @param string $url Die PDF-Download-URL.
	 *
	 * @return array Gefilterte Parameter (ohne kdnr).
	 */
	private function extract_safe_pdf_params( string $url ): array {
		$allowed_keys = array( 'id', 'rubrik', 'header', 'modus' );
		$params       = array();

		$query = wp_parse_url( $url, PHP_URL_QUERY );

		if ( empty( $query ) ) {
			return $params;
		}

		parse_str( $query, $parsed );

		foreach ( $allowed_keys as $key ) {
			if ( isset( $parsed[ $key ] ) ) {
				$params[ $key ] = sanitize_text_field( $parsed[ $key ] );
			}
		}

		return $params;
	}
}
