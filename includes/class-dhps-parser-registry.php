<?php
/**
 * Registry fuer Service-Content-Parser.
 *
 * Zentrale Registrierungsstelle fuer alle Service-Parser.
 * Folgt dem gleichen statischen Registry-Pattern wie DHPS_Service_Registry.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Parser_Registry
 *
 * Verwaltet die Zuordnung von Service-Tags zu ihren Parser-Instanzen.
 * Services ohne registrierten Parser fallen automatisch auf den
 * bestehenden Raw-HTML-Rendering-Pfad zurueck.
 *
 * @since 0.9.0
 */
class DHPS_Parser_Registry {

	/**
	 * Registrierte Parser, indexiert nach Service-Tag.
	 *
	 * @since 0.9.0
	 * @var array<string, DHPS_Parser_Interface>
	 */
	private static array $parsers = array();

	/**
	 * Registriert einen Parser fuer einen Service-Tag.
	 *
	 * @since 0.9.0
	 *
	 * @param string               $tag    Service-Tag (z.B. 'mio', 'lxmio').
	 * @param DHPS_Parser_Interface $parser Parser-Instanz.
	 *
	 * @return void
	 */
	public static function register( string $tag, DHPS_Parser_Interface $parser ): void {
		self::$parsers[ $tag ] = $parser;
	}

	/**
	 * Gibt den Parser fuer einen Service-Tag zurueck.
	 *
	 * @since 0.9.0
	 *
	 * @param string $tag Service-Tag (z.B. 'mio').
	 *
	 * @return DHPS_Parser_Interface|null Parser-Instanz oder null wenn nicht registriert.
	 */
	public static function get_parser( string $tag ): ?DHPS_Parser_Interface {
		return self::$parsers[ $tag ] ?? null;
	}

	/**
	 * Prueft ob ein Parser fuer den Service-Tag registriert ist.
	 *
	 * @since 0.9.0
	 *
	 * @param string $tag Service-Tag (z.B. 'mio').
	 *
	 * @return bool True wenn ein Parser registriert ist.
	 */
	public static function has_parser( string $tag ): bool {
		return isset( self::$parsers[ $tag ] );
	}

	/**
	 * Setzt die Registry zurueck (fuer Tests).
	 *
	 * @since 0.9.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$parsers = array();
	}
}
