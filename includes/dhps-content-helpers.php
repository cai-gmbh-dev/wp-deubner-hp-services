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

if ( ! function_exists( 'dhps_mmb_search_to_collection' ) ) {

	/**
	 * Wandelt das Ergebnis-Array von DHPS_MMB_Search_Parser in eine
	 * DHPS_Content_Collection (Side-Channel fuer DTO-Konsistenz).
	 *
	 * MMB-Search-AJAX laeuft NICHT durch die Content-Pipeline und kann
	 * daher den `DHPS_MMB_Adapter` nicht ueber `Registry::for_service` nutzen.
	 * Dieser Helper bietet eine **Helper-only Bridge** (Option D aus
	 * Discovery v0.17.5 Sektion 4.2): die JSON-Response-Shape an Frontend-JS
	 * bleibt BYTEWISE UNVERAENDERT - die Collection ist nur ueber den
	 * `dhps_mmb_search_collection`-Action-Hook fuer Plugins/Themes zugaenglich.
	 *
	 * Search-Parser liefert:
	 *   {
	 *     'results'     => [ { id, title, description, pdf_params }, ... ],
	 *     'total_count' => int,
	 *     'query'       => string,
	 *   }
	 *
	 * Helper mappet:
	 * - Items: type='document', service='mmb' (oder 'mil'), Item-ID
	 *   `{service}-search-doc-{idx-or-result-id}`
	 * - Item-meta: pdf_params, source_id, result_index
	 * - Collection-Meta: total_count, query, is_search=true
	 *
	 * Fail-Soft: bei Adapter-Exception oder fehlender Registry liefert
	 * der Helper null. Defensiv: bei fehlenden Schluesseln werden leere
	 * Defaults gesetzt, kein Throw.
	 *
	 * @since 0.17.5 TD-V0171-3
	 *
	 * @param array  $parsed_search Parser-Output (results/total_count/query).
	 * @param string $service       Service-Tag ('mmb' oder 'mil').
	 *
	 * @return DHPS_Content_Collection|null Collection oder null bei Adapter-Drift.
	 */
	function dhps_mmb_search_to_collection( array $parsed_search, string $service ): ?DHPS_Content_Collection {
		if ( ! class_exists( 'DHPS_Content_Collection' )
			|| ! class_exists( 'DHPS_Content_Item' ) ) {
			return null;
		}

		$results     = isset( $parsed_search['results'] ) && is_array( $parsed_search['results'] )
			? $parsed_search['results']
			: array();
		$total_count = isset( $parsed_search['total_count'] ) ? (int) $parsed_search['total_count'] : count( $results );
		$query       = isset( $parsed_search['query'] ) ? (string) $parsed_search['query'] : '';

		$items = array();
		foreach ( $results as $idx => $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}
			$title = isset( $result['title'] ) ? trim( (string) $result['title'] ) : '';
			if ( '' === $title ) {
				continue;
			}

			$source_id = isset( $result['id'] ) ? (string) $result['id'] : '';
			$item_id   = $service . '-search-doc-' . ( '' !== $source_id ? $source_id : (string) $idx );
			$excerpt   = isset( $result['description'] ) ? (string) $result['description'] : null;

			$meta = array(
				'result_index' => (int) $idx,
			);
			if ( '' !== $source_id ) {
				$meta['source_id'] = $source_id;
			}
			if ( isset( $result['pdf_params'] ) && is_array( $result['pdf_params'] ) ) {
				$meta['pdf_params'] = $result['pdf_params'];
			}

			try {
				$items[] = new DHPS_Content_Item(
					$item_id,
					$service,
					$title,
					'document',
					'',         // body
					$excerpt,
					null,       // image
					null,       // media
					null,       // link
					null,       // date
					array(),    // tags
					null,       // category
					$meta
				);
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- diagnostisch.
					error_log( sprintf(
						'DHPS mmb_search_to_collection skip item idx=%d: %s',
						$idx,
						$e->getMessage()
					) );
				}
				continue;
			}
		}

		$collection_meta = array(
			'total_count' => $total_count,
			'query'       => $query,
			'is_search'   => true,
		);

		try {
			return new DHPS_Content_Collection( $service, $items, $collection_meta );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- diagnostisch.
				error_log( sprintf( 'DHPS mmb_search_to_collection failed: %s', $e->getMessage() ) );
			}
			return null;
		}
	}
}
