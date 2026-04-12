<?php
/**
 * Parser fuer den MMB (Merkblaetter) Service.
 *
 * Transformiert das rohe API-HTML des MMB-Service in ein strukturiertes
 * PHP-Array. Extrahiert Rubriken mit Merkblaettern, Such-Konfiguration
 * und PDF-Parameter aus dem Legacy-HTML.
 *
 * API-HTML-Struktur (Stand 2026):
 * - div#rubrik_headerN -> Rubrik-Name (h5.rubrik_n)
 * - div#rubrik_N -> ul > li.merkblattzeile -> Merkblaetter
 *   - a.merkblatt -> Titel (href: toggleDiv('mb_NNN'))
 *   - div#mb_NNN.mb_teaser -> Beschreibung + Download
 *     - div.mb_link > a.mmb_download -> PDF-URL
 *
 * SICHERHEIT: Kundennummern (kdnr) werden NICHT extrahiert.
 * PDF-Downloads laufen ueber den serverseitigen AJAX-Proxy.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Parsers
 * @since      0.9.1
 * @since      0.9.9 Angepasst an neue API-HTML-Struktur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_MMB_Parser
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
	 * @return array Strukturiertes Array.
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
	 * Iteriert ueber div#rubrik_headerN / div#rubrik_N Paare.
	 *
	 * @since 0.9.9
	 *
	 * @param DOMDocument $doc Das geparste HTML-Dokument.
	 *
	 * @return array Liste der Rubriken mit Merkblaettern.
	 */
	private function parse_categories( DOMDocument $doc ): array {
		$categories = array();
		$xpath      = new DOMXPath( $doc );

		// Rubriken durchnummeriert: rubrik_header1..N + rubrik_1..N.
		for ( $i = 1; $i <= 20; $i++ ) {
			$header_el = $doc->getElementById( 'rubrik_header' . $i );
			$list_el   = $doc->getElementById( 'rubrik_' . $i );

			if ( null === $header_el && null === $list_el ) {
				break;
			}

			$category = array(
				'id'          => 'rubrik_' . $i,
				'name'        => '',
				'icon_slug'   => '',
				'fact_sheets' => array(),
			);

			// Rubrik-Name aus dem Header extrahieren.
			if ( null !== $header_el ) {
				// Versuche h5.rubrik_n, Fallback auf Text des Headers.
				$name_nodes = $xpath->query( './/h5', $header_el );
				if ( $name_nodes->length > 0 ) {
					$category['name'] = trim( $name_nodes->item( 0 )->textContent );
				} else {
					$category['name'] = trim( $header_el->textContent );
				}

				// Icon-Slug aus img src.
				$icon_nodes = $xpath->query( './/img', $header_el );
				if ( $icon_nodes->length > 0 ) {
					$src = $icon_nodes->item( 0 )->getAttribute( 'src' );
					if ( preg_match( '/icon_([a-z_]+)/i', $src, $m ) ) {
						$category['icon_slug'] = strtolower( $m[1] );
					}
				}
			}

			// Merkblaetter aus der Liste parsen.
			if ( null !== $list_el ) {
				$category['fact_sheets'] = $this->parse_fact_sheets( $list_el, $xpath );
			}

			if ( ! empty( $category['name'] ) || ! empty( $category['fact_sheets'] ) ) {
				$categories[] = $category;
			}
		}

		return $categories;
	}

	/**
	 * Parst die Merkblaetter einer Rubrik.
	 *
	 * Neue Struktur:
	 * - li.merkblattzeile > a.merkblatt -> Titel (href: toggleDiv('mb_NNN'))
	 * - div#mb_NNN.mb_teaser -> Beschreibung
	 * - div.mb_link > a.mmb_download -> PDF-URL
	 *
	 * @since 0.9.9
	 *
	 * @param DOMElement $list_el  Der Rubrik-Listen-Container (div#rubrik_N).
	 * @param DOMXPath   $xpath    XPath-Instanz.
	 *
	 * @return array Liste der Merkblaetter.
	 */
	private function parse_fact_sheets( DOMElement $list_el, DOMXPath $xpath ): array {
		$sheets = array();

		// Merkblatt-Titel-Links: a.merkblatt.
		$title_links = $xpath->query( './/a[contains(@class, "merkblatt")]', $list_el );

		if ( false === $title_links || 0 === $title_links->length ) {
			return $sheets;
		}

		foreach ( $title_links as $link ) {
			// Anker-Links (a.anker) ueberspringen.
			if ( false !== strpos( $link->getAttribute( 'class' ), 'anker' ) ) {
				continue;
			}

			$sheet = array(
				'id'          => '',
				'title'       => trim( $link->textContent ),
				'description' => '',
				'pdf_params'  => array(),
			);

			// ID aus href extrahieren: toggleDiv('mb_201') oder toggleDiv('ig_282').
			$href = $link->getAttribute( 'href' );
			$id_prefix = 'mb';
			if ( preg_match( "/(mb|ig)_(\w+)/", $href, $m ) ) {
				$id_prefix   = $m[1];
				$sheet['id'] = $m[2];
			}

			// Eltern-LI fuer Fallback-Suche.
			$parent_li = $link->parentNode;

			// Detail-Container: div#mb_NNN oder div#ig_NNN.
			if ( ! empty( $sheet['id'] ) ) {
				$detail_el = $link->ownerDocument->getElementById( $id_prefix . '_' . $sheet['id'] );

				if ( null !== $detail_el ) {
					// Beschreibung: Text aus p.mb_teaser oder p.ig_teaser.
					$desc_nodes = $xpath->query( './/p[contains(@class, "teaser")]', $detail_el );
					if ( $desc_nodes->length > 0 ) {
						$desc_text = '';
						foreach ( $desc_nodes->item( 0 )->childNodes as $child ) {
							if ( $child instanceof DOMText ) {
								$desc_text .= $child->textContent;
							}
						}
						$sheet['description'] = trim( $desc_text );
					}

					// PDF-Parameter aus Download-Link im Detail-Container.
					$pdf_links = $xpath->query( './/a[contains(@class, "mmb_download")]', $detail_el );
					if ( $pdf_links->length > 0 ) {
						$pdf_href            = $pdf_links->item( 0 )->getAttribute( 'href' );
						$sheet['pdf_params'] = $this->extract_pdf_params( $pdf_href );
					}
				}
			}

			// Fallback: Download-Link direkt im LI suchen.
			if ( empty( $sheet['pdf_params'] ) && $parent_li instanceof DOMElement ) {
				$fallback_links = $xpath->query( './/a[contains(@class, "mmb_download")]', $parent_li );
				if ( $fallback_links->length > 0 ) {
					$pdf_href            = $fallback_links->item( 0 )->getAttribute( 'href' );
					$sheet['pdf_params'] = $this->extract_pdf_params( $pdf_href );
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
	 * SICHERHEIT: kdnr/kd_nr werden NICHT extrahiert!
	 *
	 * URL-Format: download.php?kdnr=...&merkblatt=...&header=...
	 *
	 * @since 0.9.1
	 * @since 0.9.9 Unterstuetzt neuen Endpoint (merkblatt + header).
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

			// Nur sichere Parameter - KEINE kdnr/kd_nr!
			$safe_keys = array( 'merkblatt', 'header', 'id', 'rubrik', 'modus' );
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
	 * @since 0.9.1
	 *
	 * @param string $slug Icon-Slug.
	 *
	 * @return string Unicode-Emoji.
	 */
	public static function get_category_icon( string $slug ): string {
		$icons = array(
			'alle_stz'     => "\xF0\x9F\x91\xA4",
			'arbeitg'      => "\xF0\x9F\x92\xBC",
			'gmbh'         => "\xF0\x9F\x8F\xA2",
			'hausbesitzer' => "\xF0\x9F\x8F\xA0",
			'unternehmer'  => "\xF0\x9F\x93\x8A",
		);

		foreach ( $icons as $key => $icon ) {
			if ( false !== strpos( $slug, $key ) ) {
				return $icon;
			}
		}

		return "\xF0\x9F\x93\x84";
	}
}
