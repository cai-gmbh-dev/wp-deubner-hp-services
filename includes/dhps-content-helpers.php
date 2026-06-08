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

if ( ! function_exists( 'dhps_collection_or_empty' ) ) {

	/**
	 * Sichert eine niemals-null DHPS_Content_Collection-Instanz zu.
	 *
	 * Hintergrund (v0.18.0 Legacy-Pfad-Entfernung): Templates verlassen sich
	 * darauf dass die Pipeline IMMER eine Collection liefert. Bei Adapter-
	 * Fehlern (Throw, fehlende Registrierung, Pipeline-Sandbox-Fallback)
	 * koennte `$collection` aber null sein. Dieser Helper haert das ab,
	 * damit die Templates keinen `else`-Branch mehr brauchen.
	 *
	 * Defense-in-Depth-Strategie 3.B:
	 *
	 * - Pipeline patcht null bereits auf leere Collection (Strategie 3.A)
	 * - Helper haert das nochmal ab als Belt-and-Braces (3.B)
	 * - WP_DEBUG-Log bei null-Input fuer Drift-Diagnose
	 *
	 * @since 0.18.0
	 *
	 * @param DHPS_Content_Collection|null $collection Mögliche Collection oder null.
	 * @param string                       $service    Service-Tag fuer die Fallback-Collection.
	 *
	 * @return DHPS_Content_Collection Garantiert nicht-null.
	 */
	function dhps_collection_or_empty( ?DHPS_Content_Collection $collection, string $service ): DHPS_Content_Collection {
		if ( $collection instanceof DHPS_Content_Collection ) {
			return $collection;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- diagnostisch, WP_DEBUG-gated.
			error_log( sprintf(
				'DHPS Template-Drift: collection_or_empty fallback fuer Service "%s" (Pipeline-Garantie 3.A muesste das verhindern)',
				$service
			) );
		}

		return new DHPS_Content_Collection( $service, array(), array() );
	}
}

if ( ! function_exists( 'dhps_mmb_collection_to_legacy_categories' ) ) {

	/**
	 * Rekonstruiert die Legacy-`$categories`-Shape aus einer MMB-Collection.
	 *
	 * Pseudo-Rebuild-Pattern aus v0.17.1 - extrahiert in einen Helper in
	 * v0.18.0 (Legacy-Pfad-Entfernung), damit die 3 MMB-Templates (default/
	 * card/compact) keine Duplikat-Logik im Header tragen.
	 *
	 * Erwartet eine Collection mit MMB-typischer Meta-Shape:
	 *
	 *   - meta['categories_order'] - Liste der Category-IDs (Reihenfolge)
	 *   - meta['categories_meta'] - Lookup category_id -> {name, icon_slug, ...}
	 *
	 * Liefert ein Array von Categories mit jeweils {id, name, icon_slug, fact_sheets[]}.
	 *
	 * @since 0.18.0
	 *
	 * @param DHPS_Content_Collection $collection MMB-Collection (oder MIL).
	 *
	 * @return array<int, array{id:string, name:string, icon_slug:string, fact_sheets:array}>
	 */
	function dhps_mmb_collection_to_legacy_categories( DHPS_Content_Collection $collection ): array {
		$categories_order_raw = $collection->get_meta( 'categories_order', array() );
		$categories_overview  = is_array( $categories_order_raw ) ? $categories_order_raw : array();
		$categories_meta      = (array) $collection->get_meta( 'categories_meta', array() );

		if ( empty( $categories_overview ) ) {
			return array();
		}

		// Items pro Kategorie sammeln (Pseudo-Rebuild der Legacy-Shape).
		$items_by_category = array();
		foreach ( $collection as $item ) {
			/** @var DHPS_Content_Item $item */
			$cat_id_item = $item->category ?? '';
			if ( '' === $cat_id_item ) {
				continue;
			}
			$items_by_category[ $cat_id_item ][] = array(
				'id'          => isset( $item->meta['source_id'] ) ? (string) $item->meta['source_id'] : '',
				'title'       => $item->title,
				'description' => null !== $item->excerpt ? $item->excerpt : '',
				'pdf_params'  => isset( $item->meta['pdf_params'] ) && is_array( $item->meta['pdf_params'] )
					? $item->meta['pdf_params']
					: array(),
			);
		}

		$categories = array();
		foreach ( $categories_overview as $cat_id_iter ) {
			$cat_meta_iter = isset( $categories_meta[ $cat_id_iter ] ) && is_array( $categories_meta[ $cat_id_iter ] )
				? $categories_meta[ $cat_id_iter ]
				: array();
			$categories[]  = array(
				'id'          => (string) $cat_id_iter,
				'name'        => isset( $cat_meta_iter['name'] ) ? (string) $cat_meta_iter['name'] : '',
				'icon_slug'   => isset( $cat_meta_iter['icon_slug'] ) ? (string) $cat_meta_iter['icon_slug'] : '',
				'fact_sheets' => isset( $items_by_category[ $cat_id_iter ] ) ? $items_by_category[ $cat_id_iter ] : array(),
			);
		}

		return $categories;
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

if ( ! function_exists( 'dhps_mmb_category_to_collection' ) ) {

	/**
	 * Wandelt eine MMB-Kategorie-Datenstruktur in eine DHPS_Content_Collection
	 * (Side-Channel fuer MMB-Lazy-Akkordeon-AJAX, v0.18.2 TD-V0171-2).
	 *
	 * Wie `dhps_mmb_search_to_collection` (Option D aus Discovery v0.17.5):
	 * der AJAX-Handler liefert die JSON-Response BYTEWISE unveraendert; die
	 * Collection ist nur ueber den Action-Hook `dhps_mmb_category_collection`
	 * fuer Plugins/Themes zugaenglich.
	 *
	 * Eingabe (Category-Shape aus DHPS_MMB_Parser):
	 *   {
	 *     'id'          => string,
	 *     'name'        => string,
	 *     'icon_slug'   => string,
	 *     'fact_sheets' => [ { id, title, description, pdf_params }, ... ],
	 *   }
	 *
	 * Mapping:
	 * - Items: type='document', Item-ID `{service}-cat-{cat_id}-doc-{sheet_id-or-idx}`
	 *   (disambiguiert gegen Initial-Render und Search-Items)
	 * - Item-meta: source_id, pdf_params, category_id
	 * - Collection-Meta: category_id, category_name, icon_slug, item_count, is_lazy_category=true
	 *
	 * @since 0.18.2 TD-V0171-2
	 * @since 0.18.3 Optionaler `$extra_meta`-Param fuer Aufruf-Kontext
	 *               (z.B. `['layout' => 'card']`). Wird in Collection-Meta
	 *               gemerged; Helper-Defaults gewinnen bei Key-Kollision.
	 *
	 * @param array  $category   Category-Array (id/name/icon_slug/fact_sheets).
	 * @param string $service    Service-Tag ('mmb' oder 'mil').
	 * @param array  $extra_meta Optionaler Aufruf-Kontext (Layout-Hint etc.).
	 *                           Wird in Collection-Meta gemerged.
	 *
	 * @return DHPS_Content_Collection|null Collection oder null bei Konstruktor-Drift.
	 */
	function dhps_mmb_category_to_collection( array $category, string $service, array $extra_meta = array() ): ?DHPS_Content_Collection {
		if ( ! class_exists( 'DHPS_Content_Collection' )
			|| ! class_exists( 'DHPS_Content_Item' ) ) {
			return null;
		}

		$category_id   = isset( $category['id'] ) ? (string) $category['id'] : '';
		$category_name = isset( $category['name'] ) ? (string) $category['name'] : '';
		$icon_slug     = isset( $category['icon_slug'] ) ? (string) $category['icon_slug'] : '';
		$fact_sheets   = isset( $category['fact_sheets'] ) && is_array( $category['fact_sheets'] )
			? $category['fact_sheets']
			: array();

		$items = array();
		foreach ( $fact_sheets as $idx => $sheet ) {
			if ( ! is_array( $sheet ) ) {
				continue;
			}
			$title = isset( $sheet['title'] ) ? trim( (string) $sheet['title'] ) : '';
			if ( '' === $title ) {
				continue;
			}

			$source_id = isset( $sheet['id'] ) ? (string) $sheet['id'] : '';
			$item_id   = $service . '-cat-' . $category_id . '-doc-'
				. ( '' !== $source_id ? $source_id : (string) $idx );
			$excerpt   = isset( $sheet['description'] ) ? (string) $sheet['description'] : null;

			$meta = array(
				'category_id'  => $category_id,
				'sheet_index'  => (int) $idx,
			);
			if ( '' !== $source_id ) {
				$meta['source_id'] = $source_id;
			}
			if ( isset( $sheet['pdf_params'] ) && is_array( $sheet['pdf_params'] ) ) {
				$meta['pdf_params'] = $sheet['pdf_params'];
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
					$category_id, // category
					$meta
				);
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- diagnostisch.
					error_log( sprintf(
						'DHPS mmb_category_to_collection skip item cat=%s idx=%d: %s',
						$category_id,
						$idx,
						$e->getMessage()
					) );
				}
				continue;
			}
		}

		// v0.18.3: $extra_meta-Merge fuer Aufruf-Kontext (Discovery 36
		// Sektion 1, Option B.1). Merge-Order: Aufrufer-Werte zuerst, dann
		// Helper-Defaults - so gewinnen Helper-Defaults bei Key-Kollision
		// und Side-Channel-Invarianten (`is_lazy_category` etc.) bleiben
		// erhalten.
		$collection_meta = array_merge(
			$extra_meta,
			array(
				'category_id'       => $category_id,
				'category_name'     => $category_name,
				'icon_slug'         => $icon_slug,
				'item_count'        => count( $items ),
				'is_lazy_category'  => true,
			)
		);

		try {
			return new DHPS_Content_Collection( $service, $items, $collection_meta );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- diagnostisch.
				error_log( sprintf( 'DHPS mmb_category_to_collection failed: %s', $e->getMessage() ) );
			}
			return null;
		}
	}
}

if ( ! function_exists( 'dhps_mio_news_to_collection' ) ) {

	/**
	 * Wandelt eine MIO-News-Parser-Antwort in eine DHPS_Content_Collection
	 * (Side-Channel fuer MIO-News-Container-AJAX, v0.18.2 TD-V0174-1).
	 *
	 * Wie die anderen Side-Channel-Helper (Option D aus v0.17.5 / v0.18.2):
	 * JSON-Response BYTEWISE unveraendert; Collection nur ueber Action-Hook
	 * `dhps_news_collection` zugaenglich.
	 *
	 * **Erste Produktivnutzung des `'news'`-Item-Types** (in ALLOWED_TYPES
	 * seit v0.17.0 vorbehalten - loest sich hier ein).
	 *
	 * Eingabe (DHPS_MIO_News_Parser Shape):
	 *   {
	 *     'groups'     => [ {
	 *         'name'     => string,
	 *         'articles' => [ {
	 *             'id'          => string,
	 *             'title'       => string,
	 *             'body_html'   => string,
	 *             'metadata'    => array,  // topic/target/etc.
	 *             'share_links' => array,  // email/twitter/etc.
	 *         }, ... ],
	 *     }, ... ],
	 *     'pagination' => { 'current' => int, 'has_more' => bool },
	 *   }
	 *
	 * Mapping:
	 * - Items: type='news', Item-ID `{service}-news-{group_idx}-{article_id-or-idx}`
	 * - Item-body: body_html (Roh-HTML aus Parser, Trust-Layer wie immer)
	 * - Item-meta: source_id, group_index, group_name, metadata (durchgereicht),
	 *   share_links (durchgereicht), body_html (Frontend-JS-Konvention)
	 * - Collection-Meta: groups_order, pagination (mit Defaults), is_news=true
	 *
	 * @since 0.18.2 TD-V0174-1
	 * @since 0.18.3 Optionaler `$extra_meta`-Param fuer Aufruf-Kontext
	 *               (z.B. Filter-Atts). Wird in Collection-Meta gemerged;
	 *               Helper-Defaults gewinnen bei Key-Kollision.
	 *
	 * @param array  $parsed_news Parser-Output (groups + pagination).
	 * @param string $service     Service-Tag ('mio' oder 'lxmio').
	 * @param array  $extra_meta  Optionaler Aufruf-Kontext (Filter-Atts etc.).
	 *
	 * @return DHPS_Content_Collection|null Collection oder null bei Drift.
	 */
	function dhps_mio_news_to_collection( array $parsed_news, string $service, array $extra_meta = array() ): ?DHPS_Content_Collection {
		if ( ! class_exists( 'DHPS_Content_Collection' )
			|| ! class_exists( 'DHPS_Content_Item' ) ) {
			return null;
		}

		$groups = isset( $parsed_news['groups'] ) && is_array( $parsed_news['groups'] )
			? $parsed_news['groups']
			: array();

		$pagination_raw = isset( $parsed_news['pagination'] ) && is_array( $parsed_news['pagination'] )
			? $parsed_news['pagination']
			: array();
		$pagination     = array(
			'current'  => isset( $pagination_raw['current'] ) ? (int) $pagination_raw['current'] : 1,
			'has_more' => ! empty( $pagination_raw['has_more'] ),
		);

		$items        = array();
		$groups_order = array();

		foreach ( $groups as $group_idx => $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$group_name = isset( $group['name'] ) ? (string) $group['name'] : '';
			$articles   = isset( $group['articles'] ) && is_array( $group['articles'] )
				? $group['articles']
				: array();

			$groups_order[] = array(
				'index' => (int) $group_idx,
				'name'  => $group_name,
				'count' => count( $articles ),
			);

			foreach ( $articles as $art_idx => $article ) {
				if ( ! is_array( $article ) ) {
					continue;
				}
				$title = isset( $article['title'] ) ? trim( (string) $article['title'] ) : '';
				if ( '' === $title ) {
					continue;
				}

				$source_id = isset( $article['id'] ) ? (string) $article['id'] : '';
				$item_id   = $service . '-news-' . (int) $group_idx . '-'
					. ( '' !== $source_id ? $source_id : (string) $art_idx );
				$body_html = isset( $article['body_html'] ) ? (string) $article['body_html'] : '';

				$meta = array(
					'group_index' => (int) $group_idx,
					'group_name'  => $group_name,
					'body_html'   => $body_html, // Duplikat fuer Frontend-JS-Konvention.
				);
				if ( '' !== $source_id ) {
					$meta['source_id'] = $source_id;
				}
				if ( isset( $article['metadata'] ) && is_array( $article['metadata'] ) ) {
					$meta['metadata'] = $article['metadata'];
				}
				if ( isset( $article['share_links'] ) && is_array( $article['share_links'] ) ) {
					$meta['share_links'] = $article['share_links'];
				}

				try {
					$items[] = new DHPS_Content_Item(
						$item_id,
						$service,
						$title,
						'news',
						$body_html, // body
						null,       // excerpt
						null,       // image
						null,       // media
						null,       // link
						null,       // date
						array(),    // tags
						$group_name, // category (Gruppen-Name als Category-Hint)
						$meta
					);
				} catch ( \Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- diagnostisch.
						error_log( sprintf(
							'DHPS mio_news_to_collection skip article group=%d idx=%d: %s',
							(int) $group_idx,
							(int) $art_idx,
							$e->getMessage()
						) );
					}
					continue;
				}
			}
		}

		// v0.18.3: $extra_meta-Merge (siehe MMB-Category-Helper Header).
		$collection_meta = array_merge(
			$extra_meta,
			array(
				'groups_order' => $groups_order,
				'pagination'   => $pagination,
				'is_news'      => true,
			)
		);

		try {
			return new DHPS_Content_Collection( $service, $items, $collection_meta );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- diagnostisch.
				error_log( sprintf( 'DHPS mio_news_to_collection failed: %s', $e->getMessage() ) );
			}
			return null;
		}
	}
}
