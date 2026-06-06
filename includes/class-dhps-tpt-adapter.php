<?php
/**
 * TPT-Adapter (v0.17.2): wandelt DHPS_TPT_Parser-Output in DHPS_Content_Collection.
 *
 * Dritter Adapter im einheitlichen Datenmodell (nach MAES v0.17.0 und MMB/MIL
 * v0.17.1). Mappet die TPT-Single-Video-Struktur in eine Collection mit
 * maximal einem ContentItem (type='video', meta['is_featured']=true). Der
 * Admin-konfigurierte Block (Ueberschrift, Teasertext) aus DHPS_TPT_Modules
 * landet 1:1 im Collection-Meta unter 'tpt_config'.
 *
 * Discovery-Vertrag (siehe docs/architecture/28-TP-TPT-LP-ADAPTER-PLAN-v0172.md
 * Sektion 6.3 + 6.6): Eigene Klasse statt extends DHPS_TP_Adapter, weil TPT-
 * Parser-Output strukturell vom TP-Output abweicht (Top-Level 'video' single
 * statt 'featured_video' + 'categories'). Alle Adapter-Klassen bleiben `final`,
 * konsistent zu MAES/MMB/TP. Kleine Duplikation des Video-Item-Bauteils ggue.
 * TP-Adapter ist akzeptiert (Trust-Decision F2-TD-1).
 *
 * Pipeline-Reihenfolge (Bestand seit v0.17.0): Der Filter dhps_pipeline_data_tpt
 * feuert VOR dem Adapter-Aufruf. DHPS_TPT_Modules registriert sich auf diesen
 * Hook und packt 'tpt_config' in das $parser_output-Array. Der Adapter darf
 * sich darauf verlassen, dass 'tpt_config' bei jedem Aufruf vorhanden ist -
 * faellt aber fail-soft auf Defaults zurueck, wenn ein Theme den Filter
 * deaktiviert oder ueberschreibt (Risiko R3 aus Discovery).
 *
 * Robustheit:
 * - Fehlt 'video' oder ist leer -> leere Collection (kein Throw).
 * - Video ohne 'titel' -> leere Collection (Adapter wirft sonst im
 *   ContentItem-Konstruktor, also defensive Pre-Check).
 * - Fehlt 'tpt_config' -> Collection-Meta mit leeren Default-Strings.
 * - Defensive (string)-Casts bei jedem Feldzugriff.
 *
 * Klassen-/Datei-Konvention: `DHPS_TPT_Adapter` -> `class-dhps-tpt-adapter.php`,
 * Datei liegt im includes/-Root (Autoloader-Konvention, identisch zu MAES/MMB).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_TPT_Adapter
 *
 * Dritter Adapter im DTO-Layer, deckt TPT ab (TaxPlain-Teaser - 1 Video).
 *
 * @since 0.17.2
 */
final class DHPS_TPT_Adapter implements DHPS_Content_Adapter_Interface {

	/**
	 * Wandelt TPT-Parser-Output in eine ContentCollection.
	 *
	 * Erwartet die Schluessel 'video' (Single-Video-Array oder null) und
	 * 'tpt_config' (Admin-Texte via Filter-Anreicherung) im Parser-Output.
	 * Beide sind optional - fehlende Schluessel werden als leere Strukturen
	 * behandelt.
	 *
	 * Item-Type-Mapping (Discovery 6.3):
	 * - video (single) -> type 'video', meta['is_featured']=true,
	 *   id 'tpt-video-teaser-{video_id_or_fallback}', category=null
	 *
	 * Item-Felder:
	 * - excerpt = video['teaser'] (Plain-Text, DOM-bereinigt).
	 * - image   = { url: video['poster_url'], alt: video['titel'] } oder null.
	 * - media   = { kind:'video', slug, poster, params:{v_modus,
	 *              mandantenvideo_service} }.
	 * - meta    = { is_featured:true, video_id, datum, v_modus,
	 *              mandantenvideo_service }.
	 *
	 * Collection-Meta (Discovery 6.6):
	 * - tpt_config    -> 1:1 aus parser_output['tpt_config'] mit Default-Backfill
	 *                   ({ueberschrift:'', teasertext:''}).
	 * - total_videos  -> 0 oder 1.
	 * - video_service -> 'taxplain' (TPT-Konstante - das Service-Feld traegt
	 *                   den mandantenvideo.de-Service-Tag, nicht den Plugin-
	 *                   Service-Tag).
	 *
	 * Bei leerem video oder Video ohne Title liefert der Adapter eine LEERE
	 * Collection mit Meta gesetzt. Templates sehen ihre $has_collection-
	 * Bedingung erfuellt, finden aber count()===0 und rendern den EmptyState
	 * (analog Legacy-Verhalten ohne $video).
	 *
	 * @since 0.17.2
	 *
	 * @param array  $parser_output Output von DHPS_TPT_Parser::parse() PLUS
	 *                              Filter-Anreicherung 'tpt_config'.
	 * @param string $service       Service-Tag (von Pipeline gesetzt, typisch 'tpt').
	 *
	 * @return DHPS_Content_Collection Typisierte Item-Collection.
	 */
	public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {
		// --- Collection-Meta vorbereiten (immer gesetzt, auch bei leerer Coll). ---
		$tpt_config_raw = isset( $parser_output['tpt_config'] ) && is_array( $parser_output['tpt_config'] )
			? $parser_output['tpt_config']
			: array();

		$tpt_config = array(
			'ueberschrift' => isset( $tpt_config_raw['ueberschrift'] ) ? (string) $tpt_config_raw['ueberschrift'] : '',
			'teasertext'   => isset( $tpt_config_raw['teasertext'] ) ? (string) $tpt_config_raw['teasertext'] : '',
		);

		$video = isset( $parser_output['video'] ) && is_array( $parser_output['video'] )
			? $parser_output['video']
			: null;

		$items         = array();
		$total_videos  = 0;

		// --- Video mappen, wenn vorhanden + Title nicht leer. ---
		if ( null !== $video ) {
			$item = $this->build_video_item( $video, $service );
			if ( null !== $item ) {
				$items[]      = $item;
				$total_videos = 1;
			}
		}

		$meta = array(
			'tpt_config'    => $tpt_config,
			'total_videos'  => $total_videos,
			'video_service' => 'taxplain',
		);

		return new DHPS_Content_Collection( $service, $items, $meta );
	}

	/**
	 * Baut ein ContentItem aus einem TPT-Video-Shape.
	 *
	 * Defensive: liefert null, wenn das Video kein Title-Feld hat - der
	 * ContentItem-Konstruktor wuerde sonst eine InvalidArgumentException
	 * werfen (Pflichtfeld-Validierung). Item-IDs folgen dem Schema
	 * 'tpt-video-teaser-{video_id_or_fallback}', wobei der Fallback
	 * lediglich 'tpt-video-teaser' ist (TPT hat per Definition max 1
	 * Video pro Render -> kein Counter noetig).
	 *
	 * @since 0.17.2
	 *
	 * @param array  $video   Video-Shape aus DHPS_TPT_Parser::parse_video_block().
	 * @param string $service Service-Tag (von Pipeline, typisch 'tpt').
	 *
	 * @return DHPS_Content_Item|null Item oder null, wenn Title fehlt.
	 */
	private function build_video_item( array $video, string $service ): ?DHPS_Content_Item {
		$title = isset( $video['titel'] ) ? (string) $video['titel'] : '';
		if ( '' === trim( $title ) ) {
			return null;
		}

		$video_id   = isset( $video['video_id'] ) ? (string) $video['video_id'] : '';
		$slug       = isset( $video['video_slug'] ) ? (string) $video['video_slug'] : '';
		$poster_url = isset( $video['poster_url'] ) ? (string) $video['poster_url'] : '';
		$teaser     = isset( $video['teaser'] ) ? (string) $video['teaser'] : '';
		$datum      = isset( $video['datum'] ) ? (string) $video['datum'] : '';
		$v_modus    = isset( $video['v_modus'] ) ? (string) $video['v_modus'] : '0';
		$mvs        = isset( $video['service'] ) ? (string) $video['service'] : 'taxplain';

		// Item-ID: 'tpt-video-teaser-{video_id}' oder Fallback ohne Suffix.
		$id_tail = ( '' !== $video_id ) ? '-' . $video_id : '';
		$item_id = 'tpt-video-teaser' . $id_tail;

		// Image-Asset nur wenn Poster vorhanden.
		$image = null;
		if ( '' !== $poster_url ) {
			$image = array(
				'url' => $poster_url,
				'alt' => $title,
			);
		}

		// Media-Asset: immer gesetzt, weil 'kind:video' der DTO-Vertrag
		// fuer Video-Items ist. Slug kann leer sein, Templates rebuilden
		// aus Item-meta wenn noetig (dhps_tp_item_to_legacy_video).
		$media = array(
			'kind'   => 'video',
			'slug'   => $slug,
			'poster' => $poster_url,
			'params' => array(
				'v_modus'                => $v_modus,
				'mandantenvideo_service' => $mvs,
			),
		);

		// Item-Meta: Vollstaendiger Rebuild-Vertrag fuer
		// dhps_tp_item_to_legacy_video(). Pflichtfelder gemaess Discovery 6.4.
		$item_meta = array(
			'is_featured'            => true,
			'video_id'               => $video_id,
			'v_modus'                => $v_modus,
			'mandantenvideo_service' => $mvs,
		);
		if ( '' !== $datum ) {
			$item_meta['datum'] = $datum;
		}

		// Excerpt nur wenn Teaser != '', sonst null (DTO erlaubt null).
		$excerpt = ( '' !== $teaser ) ? $teaser : null;

		return new DHPS_Content_Item(
			$item_id,
			$service,
			$title,
			'video',  // type.
			'',       // body (leer - Teaser landet in excerpt).
			$excerpt,
			$image,
			$media,
			null,     // link.
			null,     // date (MM/YY ist nicht DateTimeImmutable-faehig, Datum lebt in meta).
			array(),  // tags.
			null,     // category (TPT hat keine Kategorien).
			$item_meta
		);
	}
}
