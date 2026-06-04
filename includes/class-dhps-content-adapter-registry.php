<?php
/**
 * Adapter-Registry: statische Verwaltung von Service-Adaptern (v0.17.0).
 *
 * Pattern analog zu {@see DHPS_Parser_Registry} und {@see DHPS_Component_Registry}:
 * rein statische API, keine Instanzierung notwendig. Adapter werden im
 * Plugin-Bootstrap registriert (in v0.17.0 macht das F2 fuer MAES) und
 * von der Content-Pipeline ueber {@see for_service()} abgefragt.
 *
 * Erweiterbar via WordPress-Filter `dhps_content_adapter_for_service`,
 * damit Plugins/Themes Adapter pro Service ueberschreiben oder ergaenzen
 * koennen, ohne den Bootstrap zu beruehren.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Content_Adapter_Registry
 *
 * Statische Service-Tag => Adapter-Registry.
 *
 * @since 0.17.0
 */
final class DHPS_Content_Adapter_Registry {

	/**
	 * Registrierte Adapter, Schluessel ist der Service-Tag.
	 *
	 * @since 0.17.0
	 * @var array<string,DHPS_Content_Adapter_Interface>
	 */
	private static array $adapters = array();

	/**
	 * Registriert einen Adapter fuer einen Service-Tag.
	 *
	 * Mehrfach-Registrierung fuer denselben Tag ueberschreibt den
	 * bestehenden Adapter (letzter gewinnt, identisch zur Parser-Registry).
	 *
	 * @since 0.17.0
	 *
	 * @param string                          $service Service-Tag (z.B. 'maes').
	 * @param DHPS_Content_Adapter_Interface  $adapter Adapter-Instanz.
	 *
	 * @return void
	 */
	public static function register( string $service, DHPS_Content_Adapter_Interface $adapter ): void {
		$key = sanitize_key( $service );
		if ( '' === $key ) {
			return;
		}
		self::$adapters[ $key ] = $adapter;
	}

	/**
	 * Liefert den Adapter fuer einen Service-Tag oder null.
	 *
	 * Vor der Rueckgabe wird der Filter `dhps_content_adapter_for_service`
	 * angewendet (siehe {@see filter_adapter()}), sodass externer Code
	 * Adapter ueberschreiben oder hinzufuegen kann.
	 *
	 * @since 0.17.0
	 *
	 * @param string $service Service-Tag.
	 *
	 * @return DHPS_Content_Adapter_Interface|null Adapter oder null.
	 */
	public static function for_service( string $service ): ?DHPS_Content_Adapter_Interface {
		$key     = sanitize_key( $service );
		$adapter = self::$adapters[ $key ] ?? null;

		/**
		 * Filter: erlaubt Plugins/Themes, einen anderen Adapter fuer einen
		 * Service-Tag zu liefern oder einen sonst nicht registrierten
		 * Adapter spaet zu injizieren.
		 *
		 * Der Filter MUSS entweder null oder eine
		 * DHPS_Content_Adapter_Interface-Instanz zurueckgeben - andere
		 * Werte werden defensiv auf null gemappt.
		 *
		 * @since 0.17.0
		 *
		 * @param DHPS_Content_Adapter_Interface|null $adapter Aktueller Adapter (oder null).
		 * @param string                              $service Service-Tag (sanitized).
		 */
		$filtered = apply_filters( 'dhps_content_adapter_for_service', $adapter, $key );

		if ( $filtered instanceof DHPS_Content_Adapter_Interface ) {
			return $filtered;
		}

		// SEC-MEDIUM-2 (v0.17.0): Diagnose-Log wenn ein Filter etwas anderes
		// als null oder Interface-Instanz zurueckliefert. Defensive Mapping
		// auf null haelt Frontend funktional, aber Entwickler muss wissen
		// dass sein Filter still gedroppt wird.
		if ( null !== $filtered && function_exists( '_doing_it_wrong' ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Filter dhps_content_adapter_for_service muss null oder DHPS_Content_Adapter_Interface liefern.', 'deubner_hp_services' ),
				'0.17.0'
			);
		}

		return null;
	}

	/**
	 * Prueft, ob ein Adapter fuer einen Service-Tag registriert ist.
	 *
	 * Beruecksichtigt NICHT den Filter `dhps_content_adapter_for_service`
	 * - prueft ausschliesslich die statische Registry. Fuer Filter-aware
	 * Lookups bitte {@see for_service()} nutzen und auf null pruefen.
	 *
	 * @since 0.17.0
	 *
	 * @param string $service Service-Tag.
	 *
	 * @return bool True wenn registriert.
	 */
	public static function has( string $service ): bool {
		$key = sanitize_key( $service );
		return isset( self::$adapters[ $key ] );
	}

	/**
	 * Leert die Registry. Primaer fuer Tests gedacht.
	 *
	 * @since 0.17.0
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$adapters = array();
	}

	/**
	 * Liefert die Liste aller registrierten Service-Tags (Diagnose).
	 *
	 * @since 0.17.0
	 *
	 * @return array<int,string> Service-Tags in Registrierungs-Reihenfolge.
	 */
	public static function get_registered_services(): array {
		return array_keys( self::$adapters );
	}
}
