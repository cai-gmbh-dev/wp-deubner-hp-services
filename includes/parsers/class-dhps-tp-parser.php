<?php
/**
 * Parser fuer den TP (TaxPlain Videos) Service.
 *
 * Transformiert das rohe API-HTML des TP-Service in ein strukturiertes
 * PHP-Array. Extrahiert das Featured Video, Kategorien mit Videos,
 * Poster-URLs und Video-Slugs.
 *
 * SICHERHEIT: kdnr wird NICHT extrahiert. Video-iframes werden
 * ueber den serverseitigen AJAX-Proxy geladen.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Parsers
 * @since      0.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_TP_Parser
 *
 * Parst das TP-API-HTML mit DOMDocument/DOMXPath. Das HTML enthaelt:
 * - PHP-Warnings (werden gefiltert)
 * - Featured Video (div.aktuelles_video): Einzelnes hervorgehobenes Video
 * - Rubriken (h4.rubrik): 4 Kategorien mit je mehreren Videos
 * - Videos (div.videoblock_rubrik): Titel, iframe, Teaser, Share-Links
 * - Inline-Scripts mit kdnr (werden entfernt)
 *
 * @since 0.9.1
 */
class DHPS_TP_Parser implements DHPS_Parser_Interface {

	/**
	 * Parst rohes TP-HTML in ein strukturiertes Array.
	 *
	 * @since 0.9.1
	 *
	 * @param string $html Rohes HTML aus der API-Antwort.
	 *
	 * @return array Strukturiertes Array mit den Schluesseln:
	 *               - 'featured_video' (array|null) Aktueller Video-Tipp.
	 *               - 'categories'     (array)      Rubriken mit Videos.
	 *               - 'service_tag'    (string)     'tp'.
	 */
	public function parse( string $html ): array {
		// PHP-Warnings aus der API-Response entfernen.
		$html = $this->strip_php_warnings( $html );

		$doc = new DOMDocument();

		$wrapped_html = '<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';

		libxml_use_internal_errors( true );
		$doc->loadHTML( $wrapped_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		return array(
			'featured_video' => $this->parse_featured_video( $doc ),
			'categories'     => $this->parse_categories( $doc ),
			'service_tag'    => 'tp',
		);
	}

	/**
	 * Entfernt PHP-Warnings/Notices aus der API-Response.
	 *
	 * @since 0.9.1
	 *
	 * @param string $html Rohes HTML (moeglicherweise mit PHP-Warnings).
	 *
	 * @return string Bereinigtes HTML.
	 */
	private function strip_php_warnings( string $html ): string {
		// Entferne Zeilen mit PHP Deprecated/Warning/Notice.
		$html = preg_replace( '/^.*?(?=<[a-zA-Z])/s', '', $html );

		if ( null === $html ) {
			return '';
		}

		return $html;
	}

	/**
	 * Extrahiert das Featured Video aus div.aktuelles_video.
	 *
	 * @since 0.9.1
	 *
	 * @param DOMDocument $doc Das geparste HTML-Dokument.
	 *
	 * @return array|null Video-Daten oder null wenn nicht vorhanden.
	 */
	private function parse_featured_video( DOMDocument $doc ): ?array {
		$xpath = new DOMXPath( $doc );

		$featured_container = $xpath->query( '//div[contains(@class, "aktuelles_video")]' );

		if ( 0 === $featured_container->length ) {
			return null;
		}

		$container = $featured_container->item( 0 );

		return $this->parse_video_block( $container, $xpath, true );
	}

	/**
	 * Parst alle Kategorien und deren Videos.
	 *
	 * Sucht h4.rubrik-Elemente und sammelt die nachfolgenden
	 * Video-Bloecke bis zur naechsten Rubrik.
	 *
	 * @since 0.9.1
	 *
	 * @param DOMDocument $doc Das geparste HTML-Dokument.
	 *
	 * @return array Liste der Kategorien mit Videos.
	 */
	private function parse_categories( DOMDocument $doc ): array {
		$categories = array();
		$xpath      = new DOMXPath( $doc );

		// Alle Rubrik-Ueberschriften finden.
		$rubriken = $xpath->query( '//h4[contains(@class, "rubrik")]' );

		if ( false === $rubriken || 0 === $rubriken->length ) {
			return $categories;
		}

		foreach ( $rubriken as $rubrik ) {
			$category = array(
				'name'   => trim( $rubrik->textContent ),
				'videos' => array(),
			);

			// Alle nachfolgenden videoblock_rubrik-Divs bis zur naechsten Rubrik sammeln.
			$sibling = $rubrik->nextSibling;
			while ( null !== $sibling ) {
				// Stopp bei naechster Rubrik-Ueberschrift.
				if ( $sibling instanceof DOMElement ) {
					if ( 'h4' === $sibling->nodeName || 'h3' === $sibling->nodeName ) {
						break;
					}

					// HR (line_alle_videos) ebenfalls als Trenner.
					if ( 'hr' === $sibling->nodeName ) {
						break;
					}

					// Video-Block parsen.
					if ( false !== strpos( $sibling->getAttribute( 'class' ), 'videoblock_rubrik' ) ) {
						$video = $this->parse_video_block( $sibling, $xpath, false );
						if ( null !== $video ) {
							$category['videos'][] = $video;
						}
					}

					// Table mit Video-Titel (Zusammengeklappte Ansicht).
					if ( 'table' === $sibling->nodeName ) {
						// Titel aus td.videotitel extrahieren fuer Video-ID-Zuordnung.
						$video_titles = $xpath->query( './/td[contains(@class, "videotitel")]//a', $sibling );
						if ( $video_titles->length > 0 ) {
							$title_link = $video_titles->item( 0 );
							$video_id   = '';

							// ID aus dem Link: toggleDiv('81').
							$href = $title_link->getAttribute( 'href' );
							if ( preg_match( "/toggleDiv\('(\d+)'\)/", $href, $m ) ) {
								$video_id = $m[1];
							}

							// ID-Attribut als Fallback.
							if ( empty( $video_id ) && $title_link->hasAttribute( 'id' ) ) {
								$id_attr = $title_link->getAttribute( 'id' );
								if ( preg_match( '/^L(\d+)$/', $id_attr, $m ) ) {
									$video_id = $m[1];
								}
							}

							// Zugehoerigen videoblock_rubrik suchen.
							if ( ! empty( $video_id ) ) {
								$video_block = $doc->getElementById( $video_id );
								if ( null !== $video_block ) {
									$video = $this->parse_video_block( $video_block, $xpath, false );
									if ( null !== $video ) {
										// Video-ID setzen falls noch leer.
										if ( empty( $video['video_id'] ) ) {
											$video['video_id'] = $video_id;
										}
										$category['videos'][] = $video;
									}
								}
							}
						}
					}
				}

				$sibling = $sibling->nextSibling;
			}

			// Duplikate entfernen (gleiche video_id).
			$seen = array();
			$unique_videos = array();
			foreach ( $category['videos'] as $v ) {
				$vid = $v['video_id'] ?? '';
				if ( ! empty( $vid ) && isset( $seen[ $vid ] ) ) {
					continue;
				}
				$seen[ $vid ] = true;
				$unique_videos[] = $v;
			}
			$category['videos'] = $unique_videos;

			if ( ! empty( $category['videos'] ) ) {
				$categories[] = $category;
			}
		}

		return $categories;
	}

	/**
	 * Parst einen einzelnen Video-Block.
	 *
	 * Extrahiert Video-Slug, Poster-URL, Titel, Teaser und Datum
	 * aus einem videoblock/videoblock_rubrik-Element.
	 *
	 * SICHERHEIT: kdnr wird NICHT extrahiert.
	 *
	 * @since 0.9.1
	 *
	 * @param DOMElement $container Der Video-Block-Container.
	 * @param DOMXPath   $xpath     XPath-Instanz.
	 * @param bool       $is_featured Ob es sich um das Featured Video handelt.
	 *
	 * @return array|null Video-Daten oder null bei Fehlern.
	 */
	private function parse_video_block( DOMElement $container, DOMXPath $xpath, bool $is_featured ): ?array {
		$video = array(
			'video_id'   => '',
			'video_slug' => '',
			'poster_url' => '',
			'titel'      => '',
			'teaser'     => '',
			'datum'      => '',
			'v_modus'    => '0',
			'service'    => 'taxplain',
		);

		// Video-ID aus dem Container-ID-Attribut.
		$container_id = $container->getAttribute( 'id' );
		if ( ! empty( $container_id ) && preg_match( '/^\d+$/', $container_id ) ) {
			$video['video_id'] = $container_id;
		}

		// Titel aus h5.videotitel.
		$title_nodes = $xpath->query( './/h5[contains(@class, "videotitel")]', $container );
		if ( $title_nodes->length > 0 ) {
			$video['titel'] = trim( $title_nodes->item( 0 )->textContent );
		}

		// iframe-src parsen fuer video_slug und poster_url.
		$iframes = $xpath->query( './/iframe[contains(@class, "inlinevideo")]', $container );
		if ( $iframes->length > 0 ) {
			$iframe_src = $iframes->item( 0 )->getAttribute( 'src' );
			$parsed     = $this->parse_iframe_src( $iframe_src );

			$video['video_slug'] = $parsed['video_slug'];
			$video['poster_url'] = $parsed['poster_url'];
			$video['v_modus']    = $parsed['v_modus'];
			$video['service']    = $parsed['service'];
		}

		// Teaser + Datum aus div.teaser.
		$teaser_nodes = $xpath->query( './/div[contains(@class, "teaser")]', $container );
		if ( $teaser_nodes->length > 0 ) {
			$raw_teaser    = trim( $teaser_nodes->item( 0 )->textContent );
			$teaser_parsed = $this->extract_datum( $raw_teaser );

			$video['teaser'] = $teaser_parsed['teaser'];
			$video['datum']  = $teaser_parsed['datum'];
		}

		// Mindestens Titel oder Video-Slug muss vorhanden sein.
		if ( empty( $video['titel'] ) && empty( $video['video_slug'] ) ) {
			return null;
		}

		return $video;
	}

	/**
	 * Parst eine iframe-src und extrahiert video_slug und poster_url.
	 *
	 * SICHERHEIT: kdnr wird NICHT extrahiert!
	 *
	 * @since 0.9.1
	 *
	 * @param string $src Die iframe-src-URL.
	 *
	 * @return array Extrahierte Parameter (ohne kdnr).
	 */
	private function parse_iframe_src( string $src ): array {
		$result = array(
			'video_slug' => '',
			'poster_url' => '',
			'v_modus'    => '0',
			'service'    => 'taxplain',
		);

		$parts = wp_parse_url( $src );
		if ( ! isset( $parts['query'] ) ) {
			return $result;
		}

		parse_str( $parts['query'], $query );

		$result['video_slug'] = $query['video'] ?? '';
		$result['poster_url'] = $query['poster'] ?? '';
		$result['v_modus']    = $query['v_modus'] ?? '0';
		$result['service']    = $query['service'] ?? 'taxplain';

		// kdnr wird BEWUSST nicht extrahiert!

		return $result;
	}

	/**
	 * Extrahiert Datum aus Teaser-Text.
	 *
	 * Das Datum steht als "(MM/YY)" am Ende des Teaser-Textes.
	 *
	 * @since 0.9.1
	 *
	 * @param string $teaser Roher Teaser-Text mit optionalem Datum.
	 *
	 * @return array Array mit 'teaser' (bereinigt) und 'datum'.
	 */
	private function extract_datum( string $teaser ): array {
		$datum = '';
		$clean = $teaser;

		if ( preg_match( '/\((\d{1,2}\/\d{2})\)\s*$/', $teaser, $matches ) ) {
			$datum = $matches[1];
			$clean = trim( preg_replace( '/\(\d{1,2}\/\d{2}\)\s*$/', '', $teaser ) );
		}

		return array(
			'teaser' => $clean,
			'datum'  => $datum,
		);
	}

	/**
	 * Formatiert ein Datum von "MM/YY" in ein lesbares Format.
	 *
	 * @since 0.9.1
	 *
	 * @param string $datum Datum im Format "MM/YY" (z.B. "11/25").
	 *
	 * @return string Formatiertes Datum (z.B. "Nov. 2025") oder Original.
	 */
	public static function format_datum( string $datum ): string {
		if ( empty( $datum ) ) {
			return '';
		}

		$months = array(
			'1'  => 'Jan.', '2'  => 'Feb.', '3'  => 'Mrz.',
			'4'  => 'Apr.', '5'  => 'Mai',  '6'  => 'Jun.',
			'7'  => 'Jul.', '8'  => 'Aug.', '9'  => 'Sep.',
			'10' => 'Okt.', '11' => 'Nov.', '12' => 'Dez.',
		);

		$parts = explode( '/', $datum );
		if ( 2 !== count( $parts ) ) {
			return $datum;
		}

		$month_num = ltrim( $parts[0], '0' );
		$year      = '20' . $parts[1];

		return ( $months[ $month_num ] ?? $datum ) . ' ' . $year;
	}
}
