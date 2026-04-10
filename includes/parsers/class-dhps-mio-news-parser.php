<?php
/**
 * Parser fuer MIO-News (AJAX-Response).
 *
 * Transformiert die AJAX-geladene News-HTML-Response von
 * hintergrundladen.php in ein strukturiertes PHP-Array.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Parsers
 * @since      0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_MIO_News_Parser
 *
 * Parst die AJAX-News-Response des MIO-Service. Das HTML enthaelt:
 * - Zielgruppen-Ueberschriften (h3.zielgruppe)
 * - Artikel (article) mit Toggle-Titeln und verborgenen Inhalten
 * - Metadaten (Tabelle), Social-Share-Links, Druck-/Ausblenden-Controls
 *
 * @since 0.9.0
 */
class DHPS_MIO_News_Parser {

	/**
	 * Parst die News-HTML-Response in ein strukturiertes Array.
	 *
	 * @since 0.9.0
	 *
	 * @param string $html Rohes HTML aus der AJAX-Response.
	 *
	 * @return array Strukturiertes Array mit Gruppen und Artikeln.
	 */
	public function parse( string $html ): array {
		$result = array(
			'groups'     => array(),
			'pagination' => array(
				'current'  => 1,
				'has_more' => false,
			),
		);

		if ( empty( trim( $html ) ) ) {
			return $result;
		}

		$doc = new DOMDocument();

		// UTF-8 Encoding sicherstellen.
		$wrapped = '<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';

		libxml_use_internal_errors( true );
		$doc->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $doc );
		$body  = $doc->getElementsByTagName( 'body' )->item( 0 );

		if ( null === $body ) {
			return $result;
		}

		$current_group = null;

		// Durch die Top-Level-Kinder des Body iterieren.
		foreach ( $body->childNodes as $node ) {
			if ( XML_ELEMENT_NODE !== $node->nodeType ) {
				continue;
			}

			// Zielgruppen-Ueberschrift erkennen.
			if ( 'h3' === $node->nodeName && false !== strpos( $node->getAttribute( 'class' ), 'zielgruppe' ) ) {
				// Neue Gruppe starten.
				if ( null !== $current_group ) {
					$result['groups'][] = $current_group;
				}

				$current_group = array(
					'name'     => trim( $node->textContent ),
					'articles' => array(),
				);
				continue;
			}

			// Artikel parsen.
			if ( 'article' === $node->nodeName && null !== $current_group ) {
				$article = $this->parse_article( $node, $xpath );

				if ( ! empty( $article['title'] ) ) {
					$current_group['articles'][] = $article;
				}
			}
		}

		// Letzte Gruppe hinzufuegen.
		if ( null !== $current_group ) {
			$result['groups'][] = $current_group;
		}

		return $result;
	}

	/**
	 * Parst einen einzelnen Artikel.
	 *
	 * @since 0.9.0
	 *
	 * @param DOMElement $article Das article-Element.
	 * @param DOMXPath   $xpath   XPath-Instanz.
	 *
	 * @return array Artikel-Daten.
	 */
	private function parse_article( DOMElement $article, DOMXPath $xpath ): array {
		$data = array(
			'id'          => '',
			'title'       => '',
			'body_html'   => '',
			'metadata'    => array(),
			'share_links' => array(),
		);

		// Titel aus a.newstitel extrahieren.
		$title_links = $xpath->query( './/a[contains(@class, "newstitel")]', $article );

		if ( $title_links->length > 0 ) {
			$title_link  = $title_links->item( 0 );
			$data['title'] = trim( $title_link->textContent );

			// ID aus dem onclick-Pattern extrahieren: toggleDoubleDiv('item18014', ...).
			$id_attr = $title_link->getAttribute( 'id' );
			if ( preg_match( '/newstitel(\d+)/', $id_attr, $matches ) ) {
				$data['id'] = $matches[1];
			}
		}

		// Content-Container suchen.
		$content_divs = $xpath->query( './/div[contains(@class, "mio_msg_content")]', $article );

		if ( $content_divs->length > 0 ) {
			$content_div = $content_divs->item( 0 );

			// Body-Paragraphen extrahieren (alle <P> und <p>).
			$body_parts = array();
			$paragraphs = $content_div->getElementsByTagName( 'p' );

			foreach ( $paragraphs as $p ) {
				// Nur Content-Paragraphen, nicht sm_section oder item_navigation.
				$class = $p->getAttribute( 'class' );
				if ( false !== strpos( $class, 'sm_section' ) || false !== strpos( $class, 'item_navigation' ) ) {
					continue;
				}

				$text = trim( $p->textContent );
				if ( '' !== $text ) {
					$body_parts[] = '<p>' . esc_html( $text ) . '</p>';
				}
			}

			// Auch <P> (Grossbuchstabe) - DOMDocument normalisiert das meist.
			$data['body_html'] = implode( "\n", $body_parts );

			// Metadaten aus der Tabelle extrahieren.
			$data['metadata'] = $this->parse_metadata( $content_div, $xpath );

			// Social-Share-Links extrahieren.
			$data['share_links'] = $this->parse_share_links( $content_div, $xpath );
		}

		return $data;
	}

	/**
	 * Parst die Metadaten aus der Tabelle im Artikel.
	 *
	 * Extrahiert "Information für" und "zum Thema" aus der Metadaten-Tabelle.
	 *
	 * @since 0.9.0
	 *
	 * @param DOMElement $container Der Content-Container.
	 * @param DOMXPath   $xpath     XPath-Instanz.
	 *
	 * @return array Assoziatives Array mit Metadaten.
	 */
	private function parse_metadata( DOMElement $container, DOMXPath $xpath ): array {
		$metadata = array();

		$tables = $container->getElementsByTagName( 'table' );

		foreach ( $tables as $table ) {
			$rows = $table->getElementsByTagName( 'tr' );

			foreach ( $rows as $row ) {
				$cells = $row->getElementsByTagName( 'td' );

				if ( $cells->length >= 2 ) {
					$label = trim( str_replace( ':', '', $cells->item( 0 )->textContent ) );
					$value = trim( $cells->item( 1 )->textContent );

					if ( false !== stripos( $label, 'Information' ) ) {
						$metadata['target'] = $value;
					} elseif ( false !== stripos( $label, 'Thema' ) ) {
						$metadata['topic'] = $value;
					}
				}
			}
		}

		return $metadata;
	}

	/**
	 * Parst die Social-Share-Links aus dem Artikel.
	 *
	 * @since 0.9.0
	 *
	 * @param DOMElement $container Der Content-Container.
	 * @param DOMXPath   $xpath     XPath-Instanz.
	 *
	 * @return array Assoziatives Array mit Share-Plattformen und URLs.
	 */
	private function parse_share_links( DOMElement $container, DOMXPath $xpath ): array {
		$links = array();

		$share_sections = $xpath->query( './/p[contains(@class, "sm_section")]', $container );

		if ( $share_sections->length > 0 ) {
			$anchors = $share_sections->item( 0 )->getElementsByTagName( 'a' );

			foreach ( $anchors as $anchor ) {
				$href  = $anchor->getAttribute( 'href' );
				$title = strtolower( $anchor->getAttribute( 'title' ) );

				// Plattform aus dem Title-Attribut ableiten.
				if ( false !== strpos( $title, 'mail' ) ) {
					$links['email'] = $href;
				} elseif ( false !== strpos( $title, 'twitter' ) || false !== strpos( $title, 'twittern' ) ) {
					$links['twitter'] = $href;
				} elseif ( false !== strpos( $title, 'facebook' ) ) {
					$links['facebook'] = $href;
				} elseif ( false !== strpos( $title, 'xing' ) ) {
					$links['xing'] = $href;
				} elseif ( false !== strpos( $title, 'linkedin' ) ) {
					$links['linkedin'] = $href;
				} elseif ( false !== strpos( $title, 'whatsapp' ) ) {
					$links['whatsapp'] = $href;
				}
			}
		}

		return $links;
	}
}
