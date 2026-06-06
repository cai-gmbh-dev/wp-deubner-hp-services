<?php
/**
 * TP-Adapter (v0.17.2): wandelt DHPS_TP_Parser-Output (auch LP-Parser, der
 * von TP erbt) in DHPS_Content_Collection.
 *
 * Dritter Adapter im einheitlichen Datenmodell (nach MAES v0.17.0 und
 * MMB v0.17.1). Mappet TP/LP-Video-Strukturen auf eine flache ContentItem-
 * Collection mit zwei Item-Klassen:
 *   - Featured-Video (max. 1) - Item.meta.is_featured = true, Item.category=null.
 *   - Categories[].videos[]    - Item.meta.is_featured = false, Item.category
 *     ist der numerische Kategorie-Index als String (Parser-Reihenfolge).
 *
 * Service-Tolerant: Wird sowohl fuer `tp` als auch `lp` registriert
 * (Discovery v0.17.2 Sektion 4, Option C). Der Adapter ist agnostisch -
 * der Service-Tag wird vom Pipeline-Aufrufer als $service-Param uebergeben
 * und 1:1 in jedes Item geschrieben (inklusive Item-ID-Prefix). Item-IDs
 * sind daher `tp-video-...` bzw. `lp-video-...`.
 *
 * Datum-Konvention: Datum bleibt MM/YY-String im Item-meta (kein
 * DateTimeImmutable), weil das API-Format keinen Tag liefert und ein
 * Re-Parse zu verlustbehafteter Repraesentation fuehren wuerde
 * (Tech-Debt-Ticket TD-V0172-2 fuer v0.17.x-Abschluss).
 *
 * Robustheit:
 * - Fehlt `featured_video` und `categories`, wird eine leere Collection
 *   geliefert (kein Throw).
 * - Videos ohne `titel` UND ohne `video_slug` werden skipped (analog
 *   DHPS_TP_Parser::parse_video_block(), Z. 287).
 * - Defensive `(string)`/`(int)`-Casts bei jedem Feldzugriff.
 * - Categories ohne gemappte Videos werden NICHT in `categories_order`
 *   eingetragen (Bucket-Count > 0 ist Voraussetzung).
 *
 * Klassen-/Datei-Konvention: `DHPS_TP_Adapter` -> `class-dhps-tp-adapter.php`,
 * Datei liegt im includes/-Root (Autoloader-Konvention, identisch zu
 * MAES- und MMB-Adapter, Discovery v0.17.2 Sektion 6.8).
 *
 * Hinweis zum doppelten Service-Feld pro Video: `Item.service` ist der
 * Plugin-Branding-Tag (`tp` oder `lp`), `Item.meta.mandantenvideo_service`
 * ist der API-Routing-Tag (`taxplain` oder `lexplain`). Beide bewusst
 * orthogonal (Discovery v0.17.2 Risiko R6).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_TP_Adapter
 *
 * Dritter Adapter im DTO-Layer, deckt TP + LP ab.
 *
 * @since 0.17.2
 */
final class DHPS_TP_Adapter implements DHPS_Content_Adapter_Interface {

	/**
	 * Wandelt TP/LP-Parser-Output in eine ContentCollection.
	 *
	 * Erwartet die Schluessel `featured_video` (array|null) und `categories`
	 * (array) im Parser-Output (siehe {@see DHPS_TP_Parser::parse()}). Beide
	 * sind optional - fehlende Schluessel werden als leere Strukturen
	 * behandelt.
	 *
	 * Item-Type-Mapping:
	 * - featured_video      -> type 'video', meta.is_featured=true,
	 *                          id '{service}-video-featured-{video_id_or_main}'
	 * - categories[].videos -> type 'video', meta.is_featured=false,
	 *                          category=(string)$cat_idx,
	 *                          id '{service}-video-{cat_idx}-{video_id_or_idx}'
	 *
	 * Item-Felder:
	 * - `excerpt` = $video['teaser'] (Plain-Text, kein wp_kses)
	 * - `image`   = {url: poster_url, alt: titel} (nur wenn poster_url != '')
	 * - `media`   = {kind:'video', slug, poster, params:{v_modus, mandantenvideo_service}}
	 * - `meta`    = {is_featured, video_id, datum?, v_modus, mandantenvideo_service,
	 *                 category_index?, video_index?}
	 *
	 * Collection-Meta:
	 * - `featured_video_id` -> Item-ID des Featured-Videos oder null
	 * - `categories_order`  -> string[], Parser-Reihenfolge der Bucket-Keys
	 * - `categories_meta`   -> `{cat_idx => {name, count}}`
	 * - `total_videos`      -> Sum aller gemappten Videos inkl. Featured
	 * - `total_categories`  -> Anzahl Kategorien mit count > 0
	 * - `video_service`     -> 'taxplain' oder 'lexplain' (aus erstem Video)
	 *
	 * @since 0.17.2
	 *
	 * @param array  $parser_output Output von DHPS_TP_Parser::parse() bzw.
	 *                              DHPS_LP_Parser::parse().
	 * @param string $service       Service-Tag ('tp' oder 'lp', von der Pipeline gesetzt).
	 *
	 * @return DHPS_Content_Collection Typisierte Item-Collection.
	 */
	public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {
		$items             = array();
		$categories_order  = array();
		$categories_meta   = array();
		$featured_video_id = null;
		$total_videos      = 0;

		// Default-Routing-Service abhaengig vom Branding-Tag.
		$video_service    = ( 'lp' === $service ) ? 'lexplain' : 'taxplain';
		$first_video_seen = false;

		// --- 1) Featured-Video. ----------------------------------------
		$featured = isset( $parser_output['featured_video'] ) && is_array( $parser_output['featured_video'] )
			? $parser_output['featured_video']
			: null;

		if ( null !== $featured ) {
			$item = $this->build_video_item( $featured, $service, true, null, 0, 0 );
			if ( null !== $item ) {
				$items[]           = $item;
				$featured_video_id = $item->id;
				++$total_videos;

				if ( ! $first_video_seen ) {
					$video_service = isset( $featured['service'] ) && '' !== (string) $featured['service']
						? (string) $featured['service']
						: $video_service;
					$first_video_seen = true;
				}
			}
		}

		// --- 2) Categories[].videos[]. ---------------------------------
		$categories = isset( $parser_output['categories'] ) && is_array( $parser_output['categories'] )
			? $parser_output['categories']
			: array();

		foreach ( $categories as $cat_idx => $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}

			$cat_name    = isset( $category['name'] ) ? (string) $category['name'] : '';
			$videos      = isset( $category['videos'] ) && is_array( $category['videos'] )
				? $category['videos']
				: array();
			$cat_idx_str = (string) $cat_idx;

			$bucket_count = 0;
			foreach ( $videos as $video_idx => $video ) {
				if ( ! is_array( $video ) ) {
					continue;
				}

				$item = $this->build_video_item(
					$video,
					$service,
					false,
					$cat_idx_str,
					(int) $cat_idx,
					(int) $video_idx
				);
				if ( null === $item ) {
					continue;
				}

				$items[] = $item;
				++$bucket_count;
				++$total_videos;

				if ( ! $first_video_seen ) {
					$video_service = isset( $video['service'] ) && '' !== (string) $video['service']
						? (string) $video['service']
						: $video_service;
					$first_video_seen = true;
				}
			}

			// Nur Kategorien mit gemappten Videos in categories_order eintragen.
			if ( $bucket_count > 0 ) {
				$categories_order[]              = $cat_idx_str;
				$categories_meta[ $cat_idx_str ] = array(
					'name'  => $cat_name,
					'count' => $bucket_count,
				);
			}
		}

		$meta = array(
			'featured_video_id' => $featured_video_id,
			'categories_order'  => $categories_order,
			'categories_meta'   => $categories_meta,
			'total_videos'      => $total_videos,
			'total_categories'  => count( $categories_order ),
			'video_service'     => $video_service,
		);

		return new DHPS_Content_Collection( $service, $items, $meta );
	}

	/**
	 * Baut ein einzelnes Video-ContentItem aus dem Legacy-Video-Array.
	 *
	 * Wird sowohl fuer das Featured-Video als auch fuer die Category-Videos
	 * genutzt. Items ohne `titel` UND ohne `video_slug` werden skipped (return
	 * null), analog zum Parser-Filter in DHPS_TP_Parser::parse_video_block().
	 * Items ohne `titel` aber mit `video_slug` bekommen den Slug als Fallback-
	 * Titel, weil DHPS_Content_Item einen non-empty Title als Pflichtfeld
	 * verlangt.
	 *
	 * ID-Generation (Schema-Vertrag Discovery v0.17.2 Sektion 6.1+6.2):
	 * - Featured: `{service}-video-featured-{video_id}` bzw.
	 *             `{service}-video-featured-main` als Fallback.
	 * - Category: `{service}-video-{cat_idx}-{video_id}` bzw.
	 *             `{service}-video-{cat_idx}-{video_idx}` als Fallback.
	 *
	 * @since 0.17.2
	 *
	 * @param array       $video       Legacy-Video-Shape (siehe parse_video_block()).
	 * @param string      $service     Service-Tag ('tp' oder 'lp').
	 * @param bool        $is_featured True fuer Featured-Video.
	 * @param string|null $category    Category-Key fuer Item.category (null bei featured).
	 * @param int         $cat_idx     Numerischer Kategorie-Index (fuer ID + meta).
	 * @param int         $video_idx   Position im Bucket (fuer Fallback-ID + meta).
	 *
	 * @return DHPS_Content_Item|null Item oder null bei Skip.
	 */
	private function build_video_item(
		array $video,
		string $service,
		bool $is_featured,
		?string $category,
		int $cat_idx,
		int $video_idx
	): ?DHPS_Content_Item {
		$title = isset( $video['titel'] ) ? (string) $video['titel'] : '';
		$slug  = isset( $video['video_slug'] ) ? (string) $video['video_slug'] : '';

		// Parser-Konsistenz: leere Items skip (siehe Parser Z. 287).
		if ( '' === $title && '' === $slug ) {
			return null;
		}
		// Content-Item verlangt non-empty Title - Slug als Fallback.
		if ( '' === $title ) {
			$title = $slug;
		}

		$video_id = isset( $video['video_id'] ) ? (string) $video['video_id'] : '';
		$poster   = isset( $video['poster_url'] ) ? (string) $video['poster_url'] : '';
		$teaser   = isset( $video['teaser'] ) ? (string) $video['teaser'] : '';
		$datum    = isset( $video['datum'] ) ? (string) $video['datum'] : '';
		$v_modus  = isset( $video['v_modus'] ) ? (string) $video['v_modus'] : '0';

		$api_default = ( 'lp' === $service ) ? 'lexplain' : 'taxplain';
		$api_svc     = isset( $video['service'] ) && '' !== (string) $video['service']
			? (string) $video['service']
			: $api_default;

		// ID-Generation.
		if ( $is_featured ) {
			$id_tail = ( '' !== $video_id ) ? $video_id : 'main';
			$item_id = $service . '-video-featured-' . $id_tail;
		} else {
			$id_tail = ( '' !== $video_id ) ? $video_id : (string) $video_idx;
			$item_id = $service . '-video-' . $cat_idx . '-' . $id_tail;
		}

		// Image-Asset: nur wenn poster_url vorhanden ist.
		$image = ( '' !== $poster )
			? array(
				'url' => $poster,
				'alt' => $title,
			)
			: null;

		// Media-Asset: Slug + Poster + AJAX-Params fuer Iframe-Spawn.
		$media = array(
			'kind'   => 'video',
			'slug'   => $slug,
			'poster' => $poster,
			'params' => array(
				'v_modus'                => $v_modus,
				'mandantenvideo_service' => $api_svc,
			),
		);

		$meta = array(
			'is_featured'            => $is_featured,
			'video_id'               => $video_id,
			'v_modus'                => $v_modus,
			'mandantenvideo_service' => $api_svc,
		);
		if ( '' !== $datum ) {
			$meta['datum'] = $datum;
		}
		if ( ! $is_featured ) {
			$meta['category_index'] = $cat_idx;
			$meta['video_index']    = $video_idx;
		}

		return new DHPS_Content_Item(
			$item_id,
			$service,
			$title,
			'video',
			'',                                  // body (leer - Teaser landet in excerpt).
			( '' !== $teaser ? $teaser : null ), // excerpt.
			$image,
			$media,
			null,                                // link (TP-Videos nutzen JS-Player, kein Mehr-erfahren-Link).
			null,                                // date (Datum als String in meta, MM/YY kein DateTimeImmutable).
			array(),                             // tags.
			$category,
			$meta
		);
	}
}
