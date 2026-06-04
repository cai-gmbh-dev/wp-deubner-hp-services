<?php
/**
 * MAES-Adapter (v0.17.0): wandelt DHPS_MAES_Parser-Output in DHPS_Content_Collection.
 *
 * Pilot-Adapter im Rahmen des einheitlichen Datenmodells (Discovery-Doc
 * 26-EINHEITLICHES-DATENMODELL-PLAN-v0170.md, Sektion 5 + 10.6). Mappet die
 * drei MAES-Item-Strukturen (Videos / Merkblaetter / News) auf die
 * generischen ContentItem-Typen video / document / news. Sonderdaten
 * (z.B. mandantenvideo-Service, pdf_params) wandern in den $meta-Hash
 * des ContentItems (Trust-Decision TD-3 - Fluchtweg statt Sub-DTO).
 *
 * Robustheit:
 * - Fehlt eines der Top-Level-Arrays (videos/merkblaetter/news), wird
 *   das jeweilige Item-Sub-Array ueberlesen (keine Exception).
 * - Items ohne `title` werden uebersprungen (kein Throw, kein Bauch).
 * - Defensive Casts via (string)/(int) bei jedem Feldzugriff.
 * - Meta-Felder ohne Quelle werden NICHT mit null aufgefuellt.
 *
 * Hinweis zur Klassen-Spelling-Konvention: Discovery-Doc nennt den
 * Adapter `DHPS_MAES_Adapter`, die Datei liegt im includes/-Root (nicht
 * im im Discovery-Doc gefuehrten includes/adapters/-Unterordner), damit
 * der bestehende Autoloader (`includes/` + `includes/parsers/`) die
 * Datei automatisch findet (Trust-Decision F2-TD-1, analog zur
 * F1-TD-1-Begruendung in DHPS_Content_Item).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_MAES_Adapter
 *
 * Pilot-Adapter fuer den MAES-Service (Meine Aerzteseite).
 *
 * @since 0.17.0
 */
final class DHPS_MAES_Adapter implements DHPS_Content_Adapter_Interface {

	/**
	 * Wandelt MAES-Parser-Output in eine ContentCollection.
	 *
	 * Erwartet die Schluessel `videos`, `merkblaetter`, `news`, `overview`
	 * im Parser-Output (siehe {@see DHPS_MAES_Parser::parse()}). Alle vier
	 * sind optional - fehlende Schluessel werden als leeres Sub-Array
	 * behandelt.
	 *
	 * Item-Type-Mapping:
	 * - videos[]       -> type 'video'    (id maes-video-{idx})
	 * - merkblaetter[] -> type 'document' (id maes-doc-{idx})
	 * - news[]         -> type 'news'     (id maes-news-{idx} oder $news['id'])
	 *
	 * Collection-Meta:
	 * - overview            -> Parser-Overview-Array (1:1 weitergereicht)
	 * - total_videos        -> Anzahl gemappter Video-Items (nach Title-Skip)
	 * - total_merkblaetter  -> Anzahl gemappter Document-Items
	 * - total_news          -> Anzahl gemappter News-Items
	 *
	 * @since 0.17.0
	 *
	 * @param array  $parser_output Output von DHPS_MAES_Parser::parse().
	 * @param string $service       Service-Tag (i.d.R. 'maes', von der Pipeline gesetzt).
	 *
	 * @return DHPS_Content_Collection Typisierte Item-Collection.
	 */
	public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {
		$items = array();

		// --- 1) Videos -> Item-Type 'video' ----------------------------
		$videos       = isset( $parser_output['videos'] ) && is_array( $parser_output['videos'] )
			? $parser_output['videos']
			: array();
		$video_count  = 0;
		foreach ( $videos as $index => $video ) {
			if ( ! is_array( $video ) || empty( $video['title'] ) ) {
				continue;
			}

			$title       = (string) $video['title'];
			$description = isset( $video['description'] ) ? (string) $video['description'] : null;
			$slug        = isset( $video['video_slug'] ) ? (string) $video['video_slug'] : '';
			$poster      = isset( $video['poster_url'] ) ? (string) $video['poster_url'] : '';
			$mv_service  = isset( $video['service'] ) ? (string) $video['service'] : 'maes';

			// Media-Hash zusammensetzen - nur Felder die wirklich Werte haben.
			$media = array( 'kind' => 'video' );
			if ( '' !== $slug ) {
				$media['slug'] = $slug;
			}
			if ( '' !== $poster ) {
				$media['poster'] = $poster;
			}

			// Optionales Image-Asset (Poster) fuer ContentCard-Bridge.
			$image = null;
			if ( '' !== $poster ) {
				$image = array(
					'url' => $poster,
					'alt' => $title,
				);
			}

			$meta = array(
				'video_index'            => (int) $index,
				'mandantenvideo_service' => $mv_service,
			);

			$items[] = new DHPS_Content_Item(
				'maes-video-' . (int) $index,
				$service,
				$title,
				'video',
				'',
				$description,
				$image,
				$media,
				null,
				null,
				array(),
				null,
				$meta
			);
			++$video_count;
		}

		// --- 2) Merkblaetter -> Item-Type 'document' --------------------
		$merkblaetter = isset( $parser_output['merkblaetter'] ) && is_array( $parser_output['merkblaetter'] )
			? $parser_output['merkblaetter']
			: array();
		$mb_count     = 0;
		foreach ( $merkblaetter as $index => $mb ) {
			if ( ! is_array( $mb ) || empty( $mb['title'] ) ) {
				continue;
			}

			$title       = (string) $mb['title'];
			$description = isset( $mb['description'] ) ? (string) $mb['description'] : null;

			$meta = array( 'doc_index' => (int) $index );
			if ( isset( $mb['pdf_params'] ) && is_array( $mb['pdf_params'] ) ) {
				$meta['pdf_params'] = $mb['pdf_params'];
			}

			$items[] = new DHPS_Content_Item(
				'maes-doc-' . (int) $index,
				$service,
				$title,
				'document',
				'',
				$description,
				null,
				null,
				null,
				null,
				array(),
				null,
				$meta
			);
			++$mb_count;
		}

		// --- 3) News -> Item-Type 'news' --------------------------------
		$news       = isset( $parser_output['news'] ) && is_array( $parser_output['news'] )
			? $parser_output['news']
			: array();
		$news_count = 0;
		foreach ( $news as $index => $article ) {
			if ( ! is_array( $article ) || empty( $article['title'] ) ) {
				continue;
			}

			$title  = (string) $article['title'];
			$teaser = isset( $article['teaser'] ) ? (string) $article['teaser'] : null;

			// body_html wird vom Adapter via wp_kses_post sanitisiert (XSS-Defense
			// in Tiefe - der Konstruktor sanitisiert nicht, Trust-Decision TD-10).
			$body_raw  = isset( $article['body_html'] ) ? (string) $article['body_html'] : '';
			$body_html = '' !== $body_raw ? wp_kses_post( $body_raw ) : '';

			// ID: bevorzugt aus Parser, sonst maes-news-{idx} als Fallback.
			$item_id = ( isset( $article['id'] ) && '' !== (string) $article['id'] )
				? (string) $article['id']
				: 'maes-news-' . (int) $index;

			$items[] = new DHPS_Content_Item(
				$item_id,
				$service,
				$title,
				'news',
				$body_html,
				$teaser,
				null,
				null,
				null,
				null,
				array(),
				null,
				array( 'news_index' => (int) $index )
			);
			++$news_count;
		}

		// --- Collection-Meta zusammensetzen ----------------------------
		$overview = isset( $parser_output['overview'] ) && is_array( $parser_output['overview'] )
			? $parser_output['overview']
			: array();

		$meta = array(
			'overview'           => $overview,
			'total_videos'       => $video_count,
			'total_merkblaetter' => $mb_count,
			'total_news'         => $news_count,
		);

		return new DHPS_Content_Collection( $service, $items, $meta );
	}
}
