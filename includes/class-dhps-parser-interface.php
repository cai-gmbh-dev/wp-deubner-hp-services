<?php
/**
 * Interface fuer Service-Content-Parser.
 *
 * Definiert den Vertrag fuer alle Service-spezifischen Parser,
 * die rohes API-HTML in strukturierte PHP-Arrays umwandeln.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface DHPS_Parser_Interface
 *
 * Jeder Service-Parser implementiert dieses Interface.
 * Die parse()-Methode nimmt rohes HTML und gibt ein
 * strukturiertes Array zurueck, das von Service-Templates
 * gerendert werden kann.
 *
 * @since 0.9.0
 */
interface DHPS_Parser_Interface {

	/**
	 * Parst rohes API-HTML in ein strukturiertes Array.
	 *
	 * Die Struktur des zurueckgegebenen Arrays ist service-spezifisch.
	 * Beispiel fuer MIO:
	 * [
	 *   'tax_dates'     => [...],
	 *   'search_config' => [...],
	 *   'service_tag'   => 'mio',
	 * ]
	 *
	 * @since 0.9.0
	 *
	 * @param string $html Rohes HTML aus der API-Antwort.
	 *
	 * @return array Strukturiertes Array mit geparsten Daten.
	 */
	public function parse( string $html ): array;
}
