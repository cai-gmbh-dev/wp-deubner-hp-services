<?php
/**
 * Globale Helper-Funktionen fuer das einheitliche Datenmodell (v0.17.1).
 *
 * Diese Datei liegt bewusst NICHT als Klasse vor und folgt nicht der
 * `class-dhps-foo-bar.php`-Autoloader-Konvention. Sie wird im Plugin-
 * Bootstrap (Deubner_HP_Services.php) explizit via `require_once`
 * inkludiert - analog zu `dhps-component-helpers.php` (v0.15.5).
 *
 * Verwendungs-Kontext:
 * - Sub-Shortcode-Pfade, die nicht durch die DHPS_Content_Pipeline laufen,
 *   aber trotzdem ein {@see DHPS_Content_Collection}-Objekt an Templates
 *   uebergeben moechten (z.B. DHPS_MAES_Modules::render_videos).
 * - Modules-Layer-Klassen, die einen Parser-Output bereits gecached haben
 *   und die Adapter-Bridge mit minimalem Code-Duplikat aufrufen wollen.
 *
 * Die Funktion verhaelt sich Fail-Soft (analog Pipeline): bei Adapter-
 * Exception wird `null` zurueckgegeben und der WP_DEBUG-Conditional log
 * den Vorfall in error_log(). Sub-Shortcode-Templates muessen damit
 * klarkommen und auf den Legacy-Pfad zurueckfallen (`$has_collection`-
 * Pattern, seit v0.17.0 etabliert).
 *
 * Schema-Vertrag siehe docs/architecture/27-MMB-SUBSHORTCODES-ADAPTER-PLAN-v0171.md
 * Sektion 6 + 7.3.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dhps_build_collection_for' ) ) {

	/**
	 * Erzeugt eine ContentCollection fuer einen Service-Tag aus einem bereits
	 * geparsten Daten-Array.
	 *
	 * Schluesselt den DHPS_Content_Adapter_Registry-Lookup, faengt
	 * Adapter-Exceptions ab (Trust-Decision TD-9 - Fail-Soft) und gibt
	 * `null` zurueck, wenn entweder kein Adapter registriert ist oder
	 * der Adapter beim Mapping wirft.
	 *
	 * Logging: Der WP_DEBUG-Conditional-Log nutzt `error_log()`, weil
	 * das in Plugin-Pfaden ohne aktiven Debug-Logger sonst still wirken
	 * wuerde. Identische Konvention wie die Pipeline-Catch-Block-
	 * Diagnose (siehe DHPS_Content_Pipeline).
	 *
	 * @since 0.17.1
	 *
	 * @param string $service     Service-Tag (mio|mmb|tp|maes|...).
	 * @param array  $parsed_data Bereits geparstes Daten-Array (Parser-Output).
	 *
	 * @return DHPS_Content_Collection|null Collection wenn Adapter erfolgreich, sonst null.
	 */
	function dhps_build_collection_for( string $service, array $parsed_data ): ?DHPS_Content_Collection {
		if ( ! class_exists( 'DHPS_Content_Adapter_Registry' ) ) {
			return null;
		}

		$adapter = DHPS_Content_Adapter_Registry::for_service( $service );
		if ( null === $adapter ) {
			return null;
		}

		try {
			return $adapter->adapt( $parsed_data, $service );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- diagnostisch, WP_DEBUG-gated.
				error_log( sprintf(
					'DHPS sub-shortcode adapter failure for service "%s": %s',
					$service,
					$e->getMessage()
				) );
			}
			return null;
		}
	}
}
