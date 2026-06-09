<?php
/**
 * Component-Registry fuer wiederverwendbare UI-Bausteine.
 *
 * Verwaltet die Registrierung von Components, sucht ihre Templates
 * in Theme-Override-Hierarchie und markiert Used-State fuer
 * Conditional Asset-Enqueue.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Component_Registry
 *
 * Zentrale statische Registry fuer alle UI-Components (Skeleton, ContentCard,
 * FilterBar, ContentList, LazyImage, EmptyState, Pagination, Accordion).
 *
 * Pattern analog zu {@see DHPS_Parser_Registry}: rein statische API ohne
 * Instanzierung. Templates werden zur Render-Zeit lazy aufgeloest, mit
 * Theme-Override-Hierarchie (Child-Theme -> Parent-Theme -> Plugin-Default).
 *
 * Filter-Hooks:
 * - `dhps_component_template_path` (string $path, string $name, array $props):
 *   erlaubt komplette Path-Override durch Plugins/Themes.
 * - `dhps_component_props` (array $props, string $name):
 *   erlaubt Props-Mutation vor Render (wird in dhps_component() angewendet).
 *
 * @since 0.14.0
 */
class DHPS_Component_Registry {

	/**
	 * Registrierte Components, indexiert nach Component-Name.
	 *
	 * Jeder Eintrag enthaelt die Config (z.B. `default_props`), die bei
	 * Registrierung uebergeben wurde.
	 *
	 * @since 0.14.0
	 * @var array<string, array<string, mixed>>
	 */
	private static array $components = array();

	/**
	 * Used-Tracking: Components, die auf der aktuellen Seite gerendert wurden.
	 *
	 * Wird von Asset-Enqueue-Logik abgefragt, um nur tatsaechlich benoetigte
	 * CSS/JS-Dateien zu laden (Performance-Optimierung).
	 *
	 * @since 0.14.0
	 * @var array<string, bool>
	 */
	private static array $used = array();

	/**
	 * Registriert eine Component.
	 *
	 * @since 0.14.0
	 *
	 * @param string               $name   Component-Name (z.B. 'content-card', 'filter-bar').
	 *                                     Wird als Dateiname (`{name}.php`) genutzt.
	 * @param array<string, mixed> $config Optionale Config. Unterstuetzte Keys:
	 *                                     - `default_props` (array): Default-Werte fuer Props.
	 *                                     - `assets` (array): Assets-Handles (CSS/JS) zum
	 *                                       Conditional-Enqueue (fuer F6/F7 reserviert).
	 *
	 * @return void
	 */
	public static function register( string $name, array $config = array() ): void {
		self::$components[ $name ] = $config;
	}

	/**
	 * Prueft, ob eine Component registriert ist.
	 *
	 * @since 0.14.0
	 *
	 * @param string $name Component-Name.
	 *
	 * @return bool True wenn registriert.
	 */
	public static function is_registered( string $name ): bool {
		return isset( self::$components[ $name ] );
	}

	/**
	 * Gibt die Config einer registrierten Component zurueck.
	 *
	 * @since 0.14.0
	 *
	 * @param string $name Component-Name.
	 *
	 * @return array<string, mixed>|null Config-Array oder null wenn nicht registriert.
	 */
	public static function get_config( string $name ): ?array {
		return self::$components[ $name ] ?? null;
	}

	/**
	 * Loest den Template-Pfad fuer eine Component auf.
	 *
	 * Sucht in der folgenden Reihenfolge:
	 * 1. Child-Theme:  `wp-content/themes/{child}/dhps/components/{name}.php`
	 * 2. Parent-Theme: `wp-content/themes/{parent}/dhps/components/{name}.php`
	 * 3. Plugin:       `wp-content/plugins/wp-deubner-hp-services/public/views/components/{name}.php`
	 *
	 * Der `dhps_component_template_path`-Filter erlaubt es Drittanbietern,
	 * den aufgeloesten Pfad zu ueberschreiben (z.B. fuer Tests oder
	 * Mu-Plugin-Overrides).
	 *
	 * @since 0.14.0
	 *
	 * @param string               $name  Component-Name.
	 * @param array<string, mixed> $props Props (an Filter weitergereicht, optional).
	 *
	 * @return string Absoluter Pfad zum Template, oder leerer String wenn nicht gefunden.
	 */
	public static function get_template_path( string $name, array $props = array() ): string {
		$relative_path = 'dhps/components/' . $name . '.php';
		$resolved      = '';

		// 1. Child-Theme (wenn aktives Theme ein Child-Theme ist).
		if ( function_exists( 'get_stylesheet_directory' ) ) {
			$child_path = trailingslashit( get_stylesheet_directory() ) . $relative_path;
			if ( file_exists( $child_path ) ) {
				$resolved = $child_path;
			}
		}

		// 2. Parent-Theme (wenn kein Child-Theme-Treffer + Parent != Child).
		if ( '' === $resolved && function_exists( 'get_template_directory' ) ) {
			$parent_path = trailingslashit( get_template_directory() ) . $relative_path;
			if ( file_exists( $parent_path ) ) {
				$resolved = $parent_path;
			}
		}

		// 3. Plugin-Default.
		if ( '' === $resolved ) {
			$plugin_path = DEUBNER_HP_SERVICES_PATH . 'public/views/components/' . $name . '.php';
			if ( file_exists( $plugin_path ) ) {
				$resolved = $plugin_path;
			}
		}

		/**
		 * Filter: erlaubt Override des aufgeloesten Component-Template-Pfads.
		 *
		 * @since 0.14.0
		 *
		 * @param string               $resolved Aufgeloester Pfad (leer wenn nicht gefunden).
		 * @param string               $name     Component-Name.
		 * @param array<string, mixed> $props    Props, mit denen die Component aufgerufen wird.
		 */
		$resolved = (string) apply_filters( 'dhps_component_template_path', $resolved, $name, $props );

		// v0.20.0 Defense-in-Depth (Audit M-2 schliessen): Realpath-Whitelist.
		// Wenn der Filter einen Pfad ausserhalb der zugelassenen Roots
		// zurueckgibt (Plugin-/Theme-/Child-Theme-Roots), wird der Pfad
		// verworfen. Schuetzt vor Path-Traversal via boswillige Filter.
		if ( '' !== $resolved ) {
			$real = realpath( $resolved );
			if ( false === $real ) {
				return '';
			}
			$allowed_roots = array(
				realpath( DEUBNER_HP_SERVICES_PATH . 'public/views/components/' ),
			);
			if ( function_exists( 'get_stylesheet_directory' ) ) {
				$allowed_roots[] = realpath( get_stylesheet_directory() . '/dhps/components/' );
			}
			if ( function_exists( 'get_template_directory' ) ) {
				$allowed_roots[] = realpath( get_template_directory() . '/dhps/components/' );
			}

			/**
			 * Filter: erlaubt zusaetzliche Root-Verzeichnisse fuer die Realpath-
			 * Whitelist (Escape-Hatch fuer ungewoehnliche Setups, z.B. Mu-Plugin-
			 * Component-Pools).
			 *
			 * @since 0.20.0
			 *
			 * @param array<int, string|false> $allowed_roots Liste realer Root-Pfade.
			 */
			$allowed_roots = (array) apply_filters( 'dhps_component_allowed_roots', $allowed_roots );

			$is_within = false;
			foreach ( $allowed_roots as $root ) {
				if ( is_string( $root ) && '' !== $root && 0 === strpos( $real, $root ) ) {
					$is_within = true;
					break;
				}
			}
			if ( ! $is_within ) {
				return '';
			}
		}

		return $resolved;
	}

	/**
	 * Gibt alle registrierten Components zurueck.
	 *
	 * @since 0.14.0
	 *
	 * @return array<string, array<string, mixed>> Map name => config.
	 */
	public static function get_all(): array {
		return self::$components;
	}

	/**
	 * Markiert eine Component als "auf der aktuellen Seite verwendet".
	 *
	 * Wird vom Renderer (siehe `dhps_component()` in
	 * `dhps-component-helpers.php`) automatisch aufgerufen. Nutzbar von
	 * Asset-Enqueue-Hooks (F6/F7), um nur benoetigte CSS/JS zu laden.
	 *
	 * @since 0.14.0
	 *
	 * @param string $name Component-Name.
	 *
	 * @return void
	 */
	public static function mark_used( string $name ): void {
		self::$used[ $name ] = true;
	}

	/**
	 * Prueft, ob eine Component auf der aktuellen Seite verwendet wurde.
	 *
	 * @since 0.14.0
	 *
	 * @param string $name Component-Name.
	 *
	 * @return bool True wenn bereits gerendert (oder explizit markiert).
	 */
	public static function was_used( string $name ): bool {
		return ! empty( self::$used[ $name ] );
	}

	/**
	 * Gibt alle als verwendet markierten Components zurueck.
	 *
	 * @since 0.14.0
	 *
	 * @return array<string> Liste der Component-Namen.
	 */
	public static function get_used(): array {
		return array_keys( array_filter( self::$used ) );
	}

	/**
	 * Setzt die Registry vollstaendig zurueck (fuer Tests).
	 *
	 * Loescht sowohl Registrierungen als auch das Used-Tracking.
	 *
	 * @since 0.14.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$components = array();
		self::$used       = array();
	}

	/**
	 * Setzt nur das Used-Tracking zurueck (z.B. zwischen Requests).
	 *
	 * @since 0.14.0
	 *
	 * @return void
	 */
	public static function reset_used(): void {
		self::$used = array();
	}
}
