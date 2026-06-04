<?php
/**
 * MAES Videos Template (Default-Layout) - v0.14.1 Component-System.
 *
 * Nutzt ContentList + ContentCard (type='video', service='maes') statt
 * dupliziertem TP-Card-Markup. Behaelt den `.dhps-service--tp`-Wrapper
 * fuer Backward-Compat mit dhps-tp.js (Event-Delegation auf
 * [data-video-slug], Lazy-Load und Filter via `.dhps-tp-card`-Klasse).
 *
 * Verfuegbare Variablen:
 *   $videos       - Array der Video-Daten aus DHPS_MAES_Parser (Legacy).
 *   $collection   - DHPS_Content_Collection|null (seit v0.17.0, optional).
 *   $columns      - Grid-Spalten (1-4).
 *   $custom_class - Optionale CSS-Klasse.
 *   $video_mode   - 'inline' oder 'modal'.
 *   $style_preset - 'default', 'minimal', 'shadow'.
 *   $lazy_count   - Initiale Videos (0 = alle).
 *   $lazy_mode    - 'manual' oder 'auto'.
 *
 * v0.17.0: Bei vorhandener Collection (MAES-Adapter registriert) werden
 * die Video-Items per filter()+to_content_card_items() ausgelesen.
 * Andernfalls greift der Legacy-Pfad (manueller foreach), damit
 * BC garantiert bleibt - z.B. wenn ein Theme das Template direkt
 * aufruft oder ein Filter Pipeline-Daten austauscht.
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 * @since   0.14.1 Migration auf Component-System.
 * @since   0.17.0 Bidirektionaler Daten-Pfad (Collection + Legacy).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lazy_count   = isset( $lazy_count ) ? (int) $lazy_count : 0;
$lazy_mode    = isset( $lazy_mode ) && is_string( $lazy_mode ) ? $lazy_mode : 'manual';
$style_preset = isset( $style_preset ) && is_string( $style_preset ) ? $style_preset : 'default';
$video_mode   = isset( $video_mode ) && is_string( $video_mode ) ? $video_mode : 'inline';
$columns      = isset( $columns ) ? (int) $columns : 2;
$custom_class = isset( $custom_class ) && is_string( $custom_class ) ? $custom_class : '';
$videos       = isset( $videos ) && is_array( $videos ) ? $videos : array();

// TP-Player-Skript wird fuer die Click-Delegation auf [data-video-slug] benoetigt.
wp_enqueue_script( 'dhps-tp-js' );

// Eindeutige Listen-ID fuer ARIA / Alpine-Scope.
$list_id = 'maes-videos-' . wp_unique_id();

// --- Daten-Pfad waehlen: Collection wenn verfuegbar, sonst Legacy. ---
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

$items       = array();
$video_index = 0;

if ( $has_collection ) {
	// v0.17.0-Pfad: Collection -> Video-Items -> ContentCard-Props.
	$video_collection = $collection->filter(
		static function ( $item ) {
			return $item instanceof DHPS_Content_Item && 'video' === $item->type;
		}
	);

	foreach ( $video_collection as $item ) {
		/** @var DHPS_Content_Item $item */
		$slug = isset( $item->media['slug'] ) ? (string) $item->media['slug'] : '';
		if ( '' === $slug ) {
			continue;
		}

		$poster      = isset( $item->media['poster'] ) ? (string) $item->media['poster'] : '';
		$title       = $item->title;
		$description = null !== $item->excerpt ? $item->excerpt : '';

		// Lazy-Hidden-Status: erste $lazy_count Karten sichtbar, Rest hidden
		// (TP-JS uebernimmt das Aufblenden via Load-More-Button).
		$is_hidden = ( $lazy_count > 0 && $video_index >= $lazy_count );

		$extra_class  = 'dhps-tp-card';
		$extra_class .= $is_hidden ? ' dhps-tp-card--lazy-hidden' : '';

		$items[] = array(
			'type'       => 'video',
			'title'      => $title,
			'teaser'     => $description,
			'media_url'  => $poster,
			'media_alt'  => $title,
			'service'    => 'maes',
			'class'      => $extra_class,
			'actions'    => array(
				array(
					'label'   => __( 'Video abspielen', 'wp-deubner-hp-services' ),
					'href'    => '#play',
					'icon'    => 'play',
					'primary' => true,
				),
			),
			// Data-Attribute fuer den TP-Player (Event-Delegation auf [data-video-slug]).
			'data_attrs' => array(
				'video-slug'  => $slug,
				'poster-url'  => $poster,
				'v-modus'     => '0',
				'video-index' => (string) $video_index,
			),
		);

		++$video_index;
	}
} else {
	// Legacy-Pfad (vor v0.17.0): Parser-Array manuell in ContentCard-Props uebersetzen.
	foreach ( $videos as $video ) {
		if ( ! is_array( $video ) ) {
			continue;
		}

		$title       = isset( $video['title'] ) ? (string) $video['title'] : '';
		$description = isset( $video['description'] ) ? (string) $video['description'] : '';
		$slug        = isset( $video['video_slug'] ) ? (string) $video['video_slug'] : '';
		$poster      = isset( $video['poster_url'] ) ? (string) $video['poster_url'] : '';

		if ( '' === $slug ) {
			continue;
		}

		// Lazy-Hidden-Status: erste $lazy_count Karten sichtbar, Rest hidden
		// (TP-JS uebernimmt das Aufblenden via Load-More-Button).
		$is_hidden = ( $lazy_count > 0 && $video_index >= $lazy_count );

		// Zusatz-Klassen so gewaehlt, dass dhps-tp.js die Karten findet:
		// - `dhps-tp-card`           : Filter- und Lazy-Selektor in dhps-tp.js
		// - `dhps-tp-card--lazy-hidden` : Lazy-Load-Marker fuer initial versteckte Karten
		$extra_class  = 'dhps-tp-card';
		$extra_class .= $is_hidden ? ' dhps-tp-card--lazy-hidden' : '';

		$items[] = array(
			'type'       => 'video',
			'title'      => $title,
			// Teaser wird per CSS line-clamp gekuerzt (keine PHP-Truncation).
			'teaser'     => $description,
			'media_url'  => $poster,
			'media_alt'  => $title,
			'service'    => 'maes',
			'class'      => $extra_class,
			'actions'    => array(
				array(
					'label'   => __( 'Video abspielen', 'wp-deubner-hp-services' ),
					'href'    => '#play',
					'icon'    => 'play',
					'primary' => true,
				),
			),
			// Data-Attribute fuer den TP-Player (Event-Delegation auf [data-video-slug]).
			'data_attrs' => array(
				'video-slug'  => $slug,
				'poster-url'  => $poster,
				'v-modus'     => '0',
				'video-index' => (string) $video_index,
			),
		);

		++$video_index;
	}
}

// Empty-State: nichts zu rendern -> ContentList uebernimmt mit empty_state-Prop.
$wrapper_classes  = 'dhps-service dhps-service--tp dhps-service--maes-videos';
$wrapper_classes .= ' dhps-tp-style--' . sanitize_html_class( $style_preset );
$wrapper_classes .= ' dhps-layout--default';
if ( '' !== $custom_class ) {
	$wrapper_classes .= ' ' . $custom_class;
}
?>
<div class="<?php echo esc_attr( $wrapper_classes ); ?>"
	data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
	data-video-mode="<?php echo esc_attr( $video_mode ); ?>"
	data-service="maes"
	data-lazy-count="<?php echo esc_attr( (string) $lazy_count ); ?>"
	data-lazy-mode="<?php echo esc_attr( $lazy_mode ); ?>">

	<?php
	echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
		'content-list',
		array(
			'id'          => $list_id,
			'layout'      => 'grid',
			'columns'     => $columns,
			'items'       => $items,
			'item_type'   => 'video',
			'class'       => 'dhps-content-list--maes-videos',
			'empty_state' => array(
				'icon'  => 'video',
				'title' => __( 'Keine Video-Tipps verfuegbar', 'wp-deubner-hp-services' ),
			),
		)
	);
	?>

	<?php if ( $lazy_count > 0 && $video_index > $lazy_count ) : ?>
		<button class="dhps-tp-load-more" type="button">
			<?php esc_html_e( 'Weitere Videos laden', 'wp-deubner-hp-services' ); ?>
		</button>
	<?php endif; ?>

</div>
