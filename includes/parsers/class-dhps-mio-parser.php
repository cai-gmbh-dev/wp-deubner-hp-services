<?php
/**
 * Parser fuer den MIO (Mandanteninformationen-Online) Service.
 *
 * Transformiert das rohe API-HTML des MIO-Service in ein strukturiertes
 * PHP-Array. Extrahiert Steuertermine, Such-/Filter-Konfiguration und
 * AJAX-Parameter aus dem Legacy-HTML.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Parsers
 * @since      0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_MIO_Parser
 *
 * Parst das MIO-API-HTML mit DOMDocument. Das HTML enthaelt:
 * - Steuertermine (div#steuertermine): Zwei Monatsspalten mit Tabellen
 * - Such-/Filterleiste (div.mio_head): Dropdown, Suchfeld, Filterbox
 * - News-Container (div.mio_standard): AJAX-Platzhalter mit Inline-Script
 *
 * @since 0.9.0
 */
class DHPS_MIO_Parser implements DHPS_Parser_Interface {

	/**
	 * Parst rohes MIO-HTML in ein strukturiertes Array.
	 *
	 * @since 0.9.0
	 *
	 * @param string $html Rohes HTML aus der API-Antwort.
	 *
	 * @return array Strukturiertes Array mit den Schlüsseln:
	 *               - 'tax_dates'     (array)  Steuertermin-Daten.
	 *               - 'search_config' (array)  Such-/Filter-Konfiguration.
	 *               - 'service_tag'   (string) Service-Kennung.
	 */
	public function parse( string $html ): array {
		// DOMDocument mit Fehlerunterdrückung laden (Legacy-HTML ist nicht valide).
		$doc = new DOMDocument();

		// UTF-8 Encoding sicherstellen.
		$wrapped_html = '<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';

		libxml_use_internal_errors( true );
		$doc->loadHTML( $wrapped_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		return array(
			'tax_dates'     => $this->parse_tax_dates( $doc ),
			'search_config' => $this->parse_search_config( $doc ),
			'ajax_params'   => $this->extract_ajax_params( $doc ),
			'service_tag'   => 'mio',
		);
	}

	/**
	 * Parst die Steuertermine aus dem HTML.
	 *
	 * Sucht div#steuertermine und extrahiert aus jeder Monatsspalte
	 * (div#steuertermin1, div#steuertermin2) den Titel, die Termine
	 * und die Fussnote.
	 *
	 * @since 0.9.0
	 *
	 * @param DOMDocument $doc Das geparste HTML-Dokument.
	 *
	 * @return array Liste der Steuertermin-Monate mit Eintraegen.
	 */
	private function parse_tax_dates( DOMDocument $doc ): array {
		$tax_dates = array();

		// Steuertermin-Container suchen.
		$container = $doc->getElementById( 'steuertermine' );

		if ( null === $container ) {
			return $tax_dates;
		}

		// Beide Monatsspalten durchgehen (steuertermin1, steuertermin2).
		for ( $i = 1; $i <= 2; $i++ ) {
			$column = $doc->getElementById( 'steuertermin' . $i );

			if ( null === $column ) {
				continue;
			}

			$month_data = array(
				'title'    => '',
				'entries'  => array(),
				'footnote' => '',
			);

			// Titel aus h4.ueb_steuertermine extrahieren.
			$h4_elements = $column->getElementsByTagName( 'h4' );
			if ( $h4_elements->length > 0 ) {
				$month_data['title'] = trim( $h4_elements->item( 0 )->textContent );
			}

			// Tabellen-Zeilen parsen (Datum + Steuerarten).
			$rows = $column->getElementsByTagName( 'tr' );
			foreach ( $rows as $row ) {
				$cells = $row->getElementsByTagName( 'td' );
				if ( $cells->length >= 2 ) {
					$date  = trim( $cells->item( 0 )->textContent );
					$taxes = $this->extract_taxes_from_cell( $cells->item( 1 ) );

					if ( '' !== $date ) {
						$month_data['entries'][] = array(
							'date'  => $date,
							'taxes' => $taxes,
						);
					}
				}
			}

			// Fussnote aus dem p-Element nach der Tabelle extrahieren.
			$beitrag = $column->getElementsByTagName( 'div' );
			foreach ( $beitrag as $div ) {
				if ( false !== strpos( $div->getAttribute( 'class' ), 'beitrag_steuertermine' ) ) {
					$paragraphs = $div->getElementsByTagName( 'p' );
					if ( $paragraphs->length > 0 ) {
						$month_data['footnote'] = trim( $paragraphs->item( 0 )->textContent );
					}
					break;
				}
			}

			$tax_dates[] = $month_data;
		}

		return $tax_dates;
	}

	/**
	 * Extrahiert einzelne Steuerarten aus einer Tabellenzelle.
	 *
	 * Die Legacy-HTML-Zelle enthaelt Steuerarten getrennt durch <br/>.
	 *
	 * @since 0.9.0
	 *
	 * @param DOMElement $cell Die td-Zelle mit den Steuerarten.
	 *
	 * @return array Liste der Steuerart-Strings.
	 */
	private function extract_taxes_from_cell( DOMElement $cell ): array {
		$taxes = array();
		$text  = '';

		foreach ( $cell->childNodes as $node ) {
			if ( XML_ELEMENT_NODE === $node->nodeType && 'br' === strtolower( $node->nodeName ) ) {
				$trimmed = trim( $text );
				if ( '' !== $trimmed ) {
					$taxes[] = $trimmed;
				}
				$text = '';
			} elseif ( XML_TEXT_NODE === $node->nodeType ) {
				$text .= $node->textContent;
			} elseif ( XML_ELEMENT_NODE === $node->nodeType ) {
				$text .= $node->textContent;
			}
		}

		// Letztes Element nach dem letzten <br/>.
		$trimmed = trim( $text );
		if ( '' !== $trimmed ) {
			$taxes[] = $trimmed;
		}

		return $taxes;
	}

	/**
	 * Parst die Such-/Filter-Konfiguration aus dem HTML.
	 *
	 * Extrahiert die Zielgruppen aus dem Select-Element und die
	 * Filter-Optionen aus der versteckten Filterbox.
	 *
	 * @since 0.9.0
	 *
	 * @param DOMDocument $doc Das geparste HTML-Dokument.
	 *
	 * @return array Such-Konfiguration mit Zielgruppen und Platzhalter.
	 */
	private function parse_search_config( DOMDocument $doc ): array {
		$config = array(
			'target_groups'      => array(),
			'search_placeholder' => 'Suchbegriff',
		);

		// Zielgruppen aus dem Select-Element #rubriken extrahieren.
		$select = $doc->getElementById( 'rubriken' );

		if ( null !== $select ) {
			$options = $select->getElementsByTagName( 'option' );

			foreach ( $options as $option ) {
				$value = trim( $option->textContent );
				if ( '' !== $value ) {
					$config['target_groups'][] = $value;
				}
			}
		}

		// Suchfeld-Placeholder extrahieren.
		$search_input = $doc->getElementById( 'suchbegriff' );

		if ( null !== $search_input && $search_input->hasAttribute( 'placeholder' ) ) {
			$config['search_placeholder'] = $search_input->getAttribute( 'placeholder' );
		}

		return $config;
	}

	/**
	 * Extrahiert AJAX-Parameter aus den Inline-Scripts.
	 *
	 * Das Legacy-HTML enthaelt showResult()-Aufrufe mit Parametern wie
	 * OTA-Nummer, Fachgebiet, Variante und Anzahl. Diese werden hier
	 * extrahiert (ohne die OTA-Nummer, die serverseitig injiziert wird).
	 *
	 * @since 0.9.0
	 *
	 * @param DOMDocument $doc Das geparste HTML-Dokument.
	 *
	 * @return array AJAX-Parameter fuer den Proxy-Aufruf.
	 */
	private function extract_ajax_params( DOMDocument $doc ): array {
		$params = array(
			'fachgebiet' => 'S',
			'variante'   => 'KATEGORIEN',
			'anzahl'     => '10',
		);

		// showResult()-Aufrufe im Script-Tag suchen.
		$scripts = $doc->getElementsByTagName( 'script' );

		foreach ( $scripts as $script ) {
			$content = $script->textContent;

			// showResult(..., '0', '10', 'S', 'KATEGORIEN', 'OTA-xxx', 1) Pattern matchen.
			if ( preg_match(
				"/showResult\([^)]*'(\d+)'\s*,\s*'(\d+)'\s*,\s*'([A-Z])'\s*,\s*'([A-Z]+)'\s*,\s*'(OTA-[^']+)'/",
				$content,
				$matches
			) ) {
				$params['teasermodus'] = $matches[1];
				$params['anzahl']     = $matches[2];
				$params['fachgebiet'] = $matches[3];
				$params['variante']   = $matches[4];
				// OTA-Nummer wird NICHT extrahiert - wird serverseitig injiziert.
				break;
			}
		}

		return $params;
	}
}
