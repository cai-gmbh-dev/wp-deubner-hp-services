<?php
/**
 * MAES Videos Card Template - v0.14.1 Component-System.
 *
 * Card-Variante: Wie default-Layout, aber mit dhps-layout--card-Wrapper
 * (Box-Shadow) und Style-Preset 'medizin'. Nutzt ContentList + ContentCard
 * unter der Haube.
 *
 * Verfuegbare Variablen:
 *   $videos       - Array der Video-Daten aus DHPS_MAES_Parser.
 *   $columns      - Grid-Spalten (1-4).
 *   $custom_class - Optionale CSS-Klasse.
 *   $style_preset - Style-Preset (default: 'medizin').
 *   $video_mode   - Video-Modus (default: 'inline').
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 * @since   0.14.1 Migration auf Component-System.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$style_preset = isset( $style_preset ) && is_string( $style_preset ) ? $style_preset : 'medizin';
$video_mode   = isset( $video_mode ) && is_string( $video_mode ) ? $video_mode : 'inline';
$columns      = isset( $columns ) ? (int) $columns : 2;
$custom_class = isset( $custom_class ) && is_string( $custom_class ) ? $custom_class : '';
$videos       = isset( $videos ) && is_array( $videos ) ? $videos : array();

wp_enqueue_script( 'dhps-tp-js' );

$list_id = 'maes-videos-card-' . wp_unique_id();

$items = array();
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

	$items[] = array(
		'type'       => 'video',
		'title'      => $title,
		'teaser'     => $description, // CSS line-clamp uebernimmt Kuerzung.
		'media_url'  => $poster,
		'media_alt'  => $title,
		'service'    => 'maes',
		// `dhps-tp-card`-Klasse fuer TP-JS-Kompatibilitaet.
		'class'      => 'dhps-tp-card',
		'actions'    => array(
			array(
				'label'   => __( 'Video abspielen', 'wp-deubner-hp-services' ),
				'href'    => '#play',
				'icon'    => 'play',
				'primary' => true,
			),
		),
		'data_attrs' => array(
			'video-slug' => $slug,
			'poster-url' => $poster,
			'v-modus'    => '0',
		),
	);
}

$wrapper_classes  = 'dhps-service dhps-service--tp dhps-service--maes-videos';
$wrapper_classes .= ' dhps-tp-style--' . sanitize_html_class( $style_preset );
$wrapper_classes .= ' dhps-layout--card';
if ( '' !== $custom_class ) {
	$wrapper_classes .= ' ' . $custom_class;
}
?>
<div class="dhps-card dhps-layout--card-wrap">
	<div class="<?php echo esc_attr( $wrapper_classes ); ?>"
		data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
		data-video-mode="<?php echo esc_attr( $video_mode ); ?>"
		data-service="maes">

		<?php
		echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
			'content-list',
			array(
				'id'          => $list_id,
				'layout'      => 'grid',
				'columns'     => $columns,
				'items'       => $items,
				'item_type'   => 'video',
				'class'       => 'dhps-content-list--maes-videos dhps-content-list--card',
				'empty_state' => array(
					'icon'  => 'video',
					'title' => __( 'Keine Video-Tipps verfuegbar', 'wp-deubner-hp-services' ),
				),
			)
		);
		?>

	</div>
</div>
