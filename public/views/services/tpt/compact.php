<?php
/**
 * Service-Template: TPT Kompakt-Layout - v0.14.3.
 *
 * Horizontale Anordnung: kleines Thumbnail, Titel und (optional) Teaser
 * rechts. Migration auf Component-System: ContentCard (type='video',
 * service='tp') mit `dhps-content-card--compact`-Modifier. Ideal fuer
 * Sidebars/Footer.
 *
 * Admin-Texte (Ueberschrift, Teasertext) kommen seit v0.14.5 ueber
 * $data['tpt_config'] (Modules-Layer DHPS_TPT_Modules via Filter
 * dhps_pipeline_data_tpt). Theme-Overrides ohne Modules-Layer-Bindung
 * erhalten leere Strings (Null-Coalescing-Fallback).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/TPT
 * @since      0.12.0
 * @since      0.14.3 Migration auf Component-System (ContentCard + EmptyState).
 * @since      0.14.5 Admin-Texte via $data['tpt_config'] (DHPS_TPT_Modules).
 * @since      0.17.2 BC-Pseudo-Rebuild aus DHPS_Content_Collection (Adapter-Bridge).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// v0.18.0: Pipeline-Garantie (siehe MMB/default.php Header).
$collection = dhps_collection_or_empty( $collection, 'tpt' );
$tpt_config = (array) $collection->get_meta( 'tpt_config', array() );
$video      = null;
foreach ( $collection as $item ) {
	$legacy = dhps_tp_item_to_legacy_video( $item );
	if ( ! empty( $legacy ) ) {
		$video = $legacy;
		break;
	}
}

$layout_class = isset( $layout_class ) && is_string( $layout_class ) ? $layout_class : 'dhps-layout--compact';
$custom_class = isset( $custom_class ) && is_string( $custom_class ) ? $custom_class : '';

wp_enqueue_script( 'dhps-tp-js' );

$wrapper_classes  = 'dhps-service dhps-service--tp dhps-service--tpt ';
$wrapper_classes .= sanitize_html_class( $layout_class );
if ( '' !== $custom_class ) {
	$wrapper_classes .= ' ' . $custom_class;
}
?>
<div class="<?php echo esc_attr( $wrapper_classes ); ?>"
	data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
	data-video-mode="inline"
	data-service="taxplain">

	<?php
	if ( null === $video || empty( $video['video_slug'] ) ) {
		echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
			'empty-state',
			array(
				'icon'  => 'video',
				'title' => __( 'Kein Teaser-Video verfuegbar', 'wp-deubner-hp-services' ),
			)
		);
		?>
	</div>
		<?php
		return;
	}

	// Admin-konfigurierte Texte: $tpt_config wurde oben bereits aus
	// Collection (v0.17.2) bzw. $data (Legacy) vorbereitet.
	$ueberschrift = (string) ( $tpt_config['ueberschrift'] ?? '' );
	$teasertext   = (string) ( $tpt_config['teasertext'] ?? '' );

	$card_title  = isset( $video['titel'] ) ? (string) $video['titel'] : '';
	$card_slug   = (string) $video['video_slug'];
	$card_poster = isset( $video['poster_url'] ) ? (string) $video['poster_url'] : '';
	$card_vmodus = isset( $video['v_modus'] ) ? (string) $video['v_modus'] : '0';

	// Compact: kuerzeren Teaser bevorzugen (admin-konfigurierter Text);
	// falls leer, bewusst ohne Teaser fuer minimalen Footprint.
	$card_teaser = ( '' !== $teasertext ) ? $teasertext : '';

	// Optional: Ueberschrift als kleines Heading vor der Card.
	if ( '' !== $ueberschrift ) :
		?>
		<h5 class="dhps-tpt-card__heading dhps-tpt-card__heading--compact"><?php echo esc_html( $ueberschrift ); ?></h5>
		<?php
	endif;

	echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
		'content-card',
		array(
			'type'       => 'video',
			'service'    => 'tp',
			'title'      => $card_title,
			'teaser'     => $card_teaser,
			'media_url'  => $card_poster,
			'media_alt'  => $card_title,
			// `dhps-content-card--compact` triggert kleines Thumbnail + reduziertes Padding (CSS).
			'class'      => 'dhps-tp-card dhps-tpt-card dhps-tpt-card--compact dhps-content-card--compact',
			'data_attrs' => array(
				'video-slug' => $card_slug,
				'poster-url' => $card_poster,
				'v-modus'    => $card_vmodus,
			),
			'actions'    => array(
				array(
					'label'   => __( 'Video abspielen', 'wp-deubner-hp-services' ),
					'href'    => '#play',
					'icon'    => 'play',
					'primary' => true,
				),
			),
		)
	);
	?>

</div>
