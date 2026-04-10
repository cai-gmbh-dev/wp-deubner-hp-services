<?php
/**
 * Parser fuer den MMB (Merkblaetter) Service.
 *
 * Transformiert das rohe API-HTML des MMB-Service in ein strukturiertes
 * PHP-Array. Extrahiert Rubriken mit Merkblaettern, Such-Konfiguration
 * und PDF-Parameter aus dem Legacy-HTML.
 *
 * SICHERHEIT: Kundennummern (kdnr) werden NICHT extrahiert.
 * PDF-Downloads laufen ueber den serverseitigen AJAX-Proxy.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Parsers
 * @since      0.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_MMB_Parser
 *
 * Parst das MMB-API-HTML mit DOMDocument/DOMXPath. Das HTML enthaelt:
 * - Suchleiste (div.mmb_suchfeld): Freitext-Suchfeld
 * - Merkblatt-Liste (div#mmb_liste): 5 Rubriken mit Accordion-Merkblaettern
 * - Inline-Scripts mit kdnr (werden entfernt)
 * - Inline-CSS mit Credentials (wird entfernt)
 *
 * @since 0.9.1
 */
class DHPS_MMB_Parser implements DHPS_Parser_Interface {

	/**
	 * Parst rohes MMB-HTML in ein strukturiertes Array.
	 *
	 * @since 0.9.1
	 *
	 * @param string $html Rohes HTML aus der API-Antwort.
	 *
	 * @return array Strukturiertes Array mit den Schluesseln:
	 *               - 'categories'    (array)  Rubrik-Daten mit Merkblaettern.
	 *               - 'search_config' (array)  Such-Konfiguration.
	 *               - 'service_tag'   (string) 'mmb'.
	 */
	public function parse( string $html ): array {
		$doc = new DOMDocument();

		$wrapped_html = '<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';

		libxml_use_internal_errors( true );
		$doc->loadHTML( $wrapped_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		return array(
			'categories'    => $this->parse_categories( $doc ),
			'search_config' => $this->parse_search_config( $doc ),
			'service_tag'   => 'mmb',
		);
	}

	/**
	 * Parst alle Rubriken mit ihren Merkblaettern.
	 *
	 * Iteriert ueber div.rubrik_container und extrahiert:
	 * - Rubrik-Name (h5.rubrik_n)
	 * - Rubrik-Icon-Slug (img.rubrikbild src)
	 * - Merkblaetter (Titel, Beschreibung, PDF-Parameter)
	 *
	 * SICHERHEIT: PDF-URLs werden NICHT komplett uebernommen.
	 * Nur die Merkblatt-ID wird extrahiert.
	 *
	 * @since 0.9.1
	 *
	 * @param DOMDocument $doc Das geparste HTML-Dokument.
	 *
	 * @return array Liste der Rubriken mit Merkblaettern.
	 */
	private function parse_categories( DOMDocument $doc ): array {
		$categories = array();
		$xpath      = new DOMXPath( $doc );

		$containers = $xpath->query( '//div[contains(@class, "rubrik_container")]' );

		if ( false === $containers ) {
			return $categories;
		}

		foreach ( $containers as $index => $container ) {
			$category = array(
				'id'          => 'rubrik_' . ( $index + 1 ),
				'name'        => '',
				'icon_slug'   => '',
				'fact_sheets' => array(),
			);

			// Rubrik-Name aus h5.rubrik_n extrahieren.
			$name_nodes = $xpath->query( './/h5[contains(@class, "rubrik_n")]', $container );
			if ( $name_nodes->length > 0 ) {
				$category['name'] = trim( $name_nodes->item( 0 )->textContent );
			}

			// Icon-Slug aus dem img.rubrikbild src ableiten.
			$icon_nodes = $xpath->query( './/img[contains(@class, "rubrikbild")]', $container );
			if ( $icon_nodes->length > 0 ) {
				$src = $icon_nodes->item( 0 )->getAttribute( 'src' );
				if ( preg_match( '/icon_([a-z_]+)\d*\.png/i', $src, $m ) ) {
					$category['icon_slug'] = $m[1];
				}
			}

			// Merkblaetter parsen.
			$category['fact_sheets'] = $this->parse_fact_sheets( $container, $xpath );

			$categories[] = $category;
		}

		return $categories;
	}

	/**
	 * Parst die Merkblaetter einer Rubrik.
	 *
	 * Extrahiert fuer jedes Merkblatt:
	 * - ID (aus dem div#item_XXXX)
	 * - Titel (aus dem Toggle-Link)
	 * - Beschreibung (aus div.merkblatt_intro)
	 * - PDF-Parameter (aus der Download-URL, OHNE kdnr)
	 *
	 * @since 0.9.1
	 *
	 * @param DOMElement $container Der Rubrik-Container.
	 * @param DOMXPath   $xpath     XPath-Instanz.
	 *
	 * @return array Liste der Merkblaetter.
	 */
	private function parse_fact_sheets( DOMElement $container, DOMXPath $xpath ): array {
		$sheets = array();

		// Merkblatt-Titel-Links finden.
		$title_links = $xpath->query( './/ul//li/a', $container );

		if ( false === $title_links ) {
			return $sheets;
		}

		foreach ( $title_links as $link ) {
			$sheet = array(
				'id'          => '',
				'title'       => trim( $link->textContent ),
				'description' => '',
				'pdf_params'  => array(),
			);

			// ID aus dem href extrahieren: toggleDoubleDiv('item_4711', ...).
			$href = $link->getAttribute( 'href' );
			if ( preg_match( "/item_(\w+)/", $href, $m ) ) {
				$sheet['id'] = $m[1];
			}

			// Detail-Container suchen.
			if ( ! empty( $sheet['id'] ) ) {
				$detail = $xpath->query(
					'.//div[@id="item_' . $sheet['id'] . '"]',
					$container
				);

				if ( $detail->length > 0 ) {
					$detail_el = $detail->item( 0 );

					// Beschreibung aus merkblatt_intro.
					$intro = $xpath->query(
						'.//div[contains(@class, "merkblatt_intro")]',
						$detail_el
					);
					if ( $intro->length > 0 ) {
						$sheet['description'] = trim( $intro->item( 0 )->textContent );
					}

					// PDF-Parameter aus Download-Link extrahieren (OHNE kdnr).
					$pdf_link = $xpath->query(
						'.//div[contains(@class, "merkblatt_download")]//a',
						$detail_el
					);
					if ( $pdf_link->length > 0 ) {
						$pdf_href            = $pdf_link->item( 0 )->getAttribute( 'href' );
						$sheet['pdf_params'] = $this->extract_pdf_params( $pdf_href );
					}
				}
			}

			if ( ! empty( $sheet['title'] ) ) {
				$sheets[] = $sheet;
			}
		}

		return $sheets;
	}

	/**
	 * Extrahiert PDF-Parameter aus der Download-URL.
	 *
	 * SICHERHEIT: Die kdnr wird NICHT extrahiert!
	 * Sie wird spaeter serverseitig vom AJAX-Proxy injiziert.
	 *
	 * @since 0.9.1
	 *
	 * @param string $url Die PDF-Download-URL.
	 *
	 * @return array Sichere PDF-Parameter (ohne kdnr).
	 */
	private function extract_pdf_params( string $url ): array {
		$params = array();
		$query  = wp_parse_url( $url, PHP_URL_QUERY );

		if ( ! empty( $query ) ) {
			parse_str( $query, $parsed );

			// Nur sichere Parameter uebernehmen - KEINE kd_nr!
			$safe_keys = array( 'id', 'rubrik', 'header', 'modus' );
			foreach ( $safe_keys as $key ) {
				if ( isset( $parsed[ $key ] ) ) {
					$params[ $key ] = sanitize_text_field( $parsed[ $key ] );
				}
			}
		}

		return $params;
	}

	/**
	 * Parst die Such-Konfiguration.
	 *
	 * @since 0.9.1
	 *
	 * @param DOMDocument $doc Das geparste HTML-Dokument.
	 *
	 * @return array Such-Konfiguration.
	 */
	private function parse_search_config( DOMDocument $doc ): array {
		$config = array(
			'search_placeholder' => 'Suchbegriff',
			'has_search'         => true,
		);

		$search_input = $doc->getElementById( 'suchbegriff' );

		if ( null !== $search_input && $search_input->hasAttribute( 'placeholder' ) ) {
			$config['search_placeholder'] = $search_input->getAttribute( 'placeholder' );
		}

		return $config;
	}

	/**
	 * Gibt ein Emoji-Icon fuer eine Rubrik zurueck.
	 *
	 * Mappt den Icon-Slug aus der API auf ein Unicode-Emoji
	 * als Fallback fuer die Template-Darstellung.
	 *
	 * @since 0.9.1
	 *
	 * @param string $slug Icon-Slug (z.B. 'alle_stz', 'arbeitg').
	 *
	 * @return string Unicode-Emoji.
	 */
	public static function get_category_icon( string $slug ): string {
		$icons = array(
			'alle_stz'    => "\xF0\x9F\x91\xA4",
			'arbeitg'     => "\xF0\x9F\x92\xBC",
			'gmbh'        => "\xF0\x9F\x8F\xA2",
			'hausbesitzer' => "\xF0\x9F\x8F\xA0",
			'unternehmer' => "\xF0\x9F\x93\x8A",
		);

		foreach ( $icons as $key => $icon ) {
			if ( false !== strpos( $slug, $key ) ) {
				return $icon;
			}
		}

		return "\xF0\x9F\x93\x84";
	}
}
