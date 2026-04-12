<?php
/**
 * Parser fuer den MAES (Meine Aerzteseite) Service.
 *
 * Transformiert das rohe API-HTML des MAES-Service (InfoKombi) in ein
 * strukturiertes PHP-Array. Extrahiert Videos, Merkblaetter und die
 * Uebersicht aus den 5 Fieldsets innerhalb von div#tabs_data.
 *
 * Fieldset-Struktur:
 * - 0: Uebersicht (Teasers mit Rubriken)
 * - 1: Leistungen (leer/hidden)
 * - 2: Aktuelles (derzeit leer)
 * - 3: Video-Tipps (iframes von mandantenvideo.de)
 * - 4: Merkblaetter/Checklisten (PDF-Downloads via ik_merkblatt_link)
 *
 * SICHERHEIT: kdnr wird NICHT extrahiert. PDF-Downloads und Video-iframes
 * werden ueber den serverseitigen AJAX-Proxy geladen.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Parsers
 * @since      0.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_MAES_Parser
 *
 * Parst das MAES-API-HTML mit DOMDocument/DOMXPath.
 *
 * @since 0.10.0
 */
class DHPS_MAES_Parser implements DHPS_Parser_Interface {

	/**
	 * Parst rohes MAES-HTML in ein strukturiertes Array.
	 *
	 * @since 0.10.0
	 *
	 * @param string $html Rohes HTML aus der API-Antwort.
	 *
	 * @return array Strukturiertes Array mit den Schluesseln:
	 *               - 'videos'       (array)  Video-Tipps aus Fieldset 3.
	 *               - 'merkblaetter' (array)  Merkblaetter/Checklisten aus Fieldset 4.
	 *               - 'overview'     (array)  Uebersicht-Rubriken aus Fieldset 0.
	 *               - 'service_tag'  (string) 'maes'.
	 */
	public function parse( string $html ): array {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML(
			'<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		$xpath     = new DOMXPath( $doc );
		$fieldsets = $xpath->query( '//div[@id="tabs_data"]/fieldset' );

		return array(
			'news'         => $this->parse_news( $fieldsets->item( 2 ), $xpath ),
			'videos'       => $this->parse_videos( $fieldsets->item( 3 ), $xpath ),
			'merkblaetter' => $this->parse_merkblaetter( $fieldsets->item( 4 ), $xpath ),
			'overview'     => $this->parse_overview( $fieldsets->item( 0 ), $xpath ),
			'service_tag'  => 'maes',
		);
	}

	/**
	 * Parst Video-Tipps aus Fieldset 3.
	 *
	 * Jedes Video befindet sich in einer Tabelle mit einem iframe
	 * (mandantenvideo.de) und beschreibendem Text.
	 *
	 * SICHERHEIT: kdnr wird NICHT extrahiert.
	 *
	 * @since 0.10.0
	 *
	 * @param DOMNode|null $fieldset Fieldset-Element (Index 3) oder null.
	 * @param DOMXPath     $xpath    XPath-Instanz.
	 *
	 * @return array Liste der Video-Daten.
	 */
	private function parse_videos( ?DOMNode $fieldset, DOMXPath $xpath ): array {
		if ( null === $fieldset ) {
			return array();
		}

		$videos = array();
		$tables = $xpath->query( './/table', $fieldset );

		foreach ( $tables as $table ) {
			$iframe = $xpath->query( './/iframe', $table );
			if ( 0 === $iframe->length ) {
				continue;
			}

			$src   = $iframe->item( 0 )->getAttribute( 'src' );
			$query = wp_parse_url( $src, PHP_URL_QUERY ) ?? '';
			// &amp;-Encoding aus dem HTML korrigieren.
			$query = str_replace( '&amp;', '&', $query );
			parse_str( $query, $params );

			// Beschreibung: gesamten Text aus der Tabelle extrahieren.
			$text = trim( $table->textContent );
			$text = preg_replace( '/\s+/', ' ', $text );

			// Titel/Beschreibung trennen: Titel ist meist der erste fette Text.
			$title = '';
			$desc  = $text;

			$strongs = $xpath->query( './/b | .//strong', $table );
			if ( $strongs->length > 0 ) {
				$title = trim( $strongs->item( 0 )->textContent );
				$desc  = trim( str_replace( $title, '', $text ) );
			} else {
				// Fallback: erster Satz als Titel.
				$parts = preg_split( '/(?<=\.)[\s]/', $text, 2 );
				if ( count( $parts ) >= 2 ) {
					$title = $parts[0];
					$desc  = $parts[1];
				}
			}

			$videos[] = array(
				'title'       => $title,
				'description' => $desc,
				'video_slug'  => $params['video'] ?? '',
				'poster_url'  => $params['poster'] ?? '',
				'service'     => 'maes',
			);
		}

		return $videos;
	}

	/**
	 * Parst Merkblaetter/Checklisten aus Fieldset 4.
	 *
	 * Jedes Merkblatt hat einen Link mit der CSS-Klasse ik_merkblatt_link,
	 * dessen href die Download-Parameter (kdnr, merkblatt, header) enthaelt.
	 *
	 * SICHERHEIT: kdnr wird NICHT extrahiert. Der PDF-Download erfolgt
	 * ueber den serverseitigen AJAX-Proxy.
	 *
	 * @since 0.10.0
	 *
	 * @param DOMNode|null $fieldset Fieldset-Element (Index 4) oder null.
	 * @param DOMXPath     $xpath    XPath-Instanz.
	 *
	 * @return array Liste der Merkblatt-Daten.
	 */
	private function parse_merkblaetter( ?DOMNode $fieldset, DOMXPath $xpath ): array {
		if ( null === $fieldset ) {
			return array();
		}

		$sheets = array();
		$links  = $xpath->query( './/a[contains(@class, "ik_merkblatt_link")]', $fieldset );

		foreach ( $links as $link ) {
			$href  = $link->getAttribute( 'href' );
			$query = wp_parse_url( $href, PHP_URL_QUERY ) ?? '';
			parse_str( $query, $params );

			// Titel und Beschreibung aus dem Eltern-Container extrahieren.
			$parent = $link->parentNode;
			$text   = trim( $parent->textContent );
			$text   = str_replace( 'Merkblatt ansehen', '', $text );
			$text   = preg_replace( '/\s+/', ' ', trim( $text ) );

			// Titel ist der fette Text, Rest ist Beschreibung.
			$title = '';
			$desc  = $text;

			$strongs = $xpath->query( './/b | .//strong', $parent );
			if ( $strongs->length > 0 ) {
				$title = trim( $strongs->item( 0 )->textContent );
				$desc  = trim( str_replace( $title, '', $text ) );
			}

			// Fallback: Merkblatt-Slug als Titel verwenden.
			if ( empty( $title ) && ! empty( $params['merkblatt'] ) ) {
				$title = str_replace(
					array( 'Merkblatt_', '_' ),
					array( '', ' ' ),
					$params['merkblatt']
				);
			}

			$sheets[] = array(
				'title'       => $title,
				'description' => $desc,
				'pdf_params'  => array(
					'merkblatt' => sanitize_text_field( $params['merkblatt'] ?? '' ),
					'header'    => sanitize_text_field( $params['header'] ?? '' ),
				),
			);
		}

		return $sheets;
	}

	/**
	 * Parst die Uebersicht aus Fieldset 0.
	 *
	 * Extrahiert Rubrik-Ueberschriften (h2.rubrik) als Navigationseintraege.
	 *
	 * @since 0.10.0
	 *
	 * @param DOMNode|null $fieldset Fieldset-Element (Index 0) oder null.
	 * @param DOMXPath     $xpath    XPath-Instanz.
	 *
	 * @return array Liste der Uebersichts-Sektionen.
	 */
	/**
	 * Parst Nachrichten/Aktuelles aus Fieldset 2.
	 *
	 * Struktur: div.news_item > h5.ik_news_titel + p.ik_news_teaser + div.beitrag
	 *
	 * @since 0.10.1
	 *
	 * @param DOMNode|null $fieldset Fieldset-Element (Index 2) oder null.
	 * @param DOMXPath     $xpath    XPath-Instanz.
	 *
	 * @return array Liste der News-Artikel.
	 */
	private function parse_news( ?DOMNode $fieldset, DOMXPath $xpath ): array {
		if ( null === $fieldset ) {
			return array();
		}

		$articles = array();
		$items    = $xpath->query( './/div[contains(@class, "news_item")]', $fieldset );

		foreach ( $items as $index => $item ) {
			$title_el  = $xpath->query( './/h5', $item );
			$teaser_el = $xpath->query( './/p[contains(@class, "ik_news_teaser")]', $item );
			$body_el   = $xpath->query( './/div[contains(@class, "beitrag")]', $item );

			$title  = $title_el->length > 0 ? trim( $title_el->item( 0 )->textContent ) : '';
			$teaser = '';
			if ( $teaser_el->length > 0 ) {
				$teaser = trim( $teaser_el->item( 0 )->textContent );
				$teaser = str_replace( 'mehr...', '', $teaser );
				$teaser = trim( $teaser );
			}

			$body_html = '';
			if ( $body_el->length > 0 ) {
				// Inneres HTML des Body-Divs extrahieren.
				$body_node = $body_el->item( 0 );
				$inner     = '';
				foreach ( $body_node->childNodes as $child ) {
					$inner .= $body_node->ownerDocument->saveHTML( $child );
				}
				$body_html = trim( $inner );
			}

			if ( ! empty( $title ) ) {
				$articles[] = array(
					'id'        => 'maes-news-' . $index,
					'title'     => $title,
					'teaser'    => $teaser,
					'body_html' => $body_html,
				);
			}
		}

		return $articles;
	}

	private function parse_overview( ?DOMNode $fieldset, DOMXPath $xpath ): array {
		if ( null === $fieldset ) {
			return array();
		}

		$sections = array();
		$rubriken = $xpath->query( './/h2[contains(@class, "rubrik")]', $fieldset );

		foreach ( $rubriken as $rubrik ) {
			$sections[] = array(
				'title' => trim( $rubrik->textContent ),
			);
		}

		return $sections;
	}
}
