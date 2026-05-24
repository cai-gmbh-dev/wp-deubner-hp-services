<?php
/**
 * Service-Template: TPT Kompakt-Layout - v0.14.3.
 *
 * Horizontale Anordnung: kleines Thumbnail, Titel und (optional) Teaser
 * rechts. Migration auf Component-System: ContentCard (type='video',
 * service='tp') mit `dhps-content-card--compact`-Modifier. Ideal fuer
 * Sidebars/Footer.
 *
 * Tech-Debt (laut Audit, Plan v0.14.3 Sektion 6 / TPT-#3):
 *   `get_option('dhps_tpt_ues')` und `get_option('dhps_tpt_teasertext')` werden
 *   weiterhin direkt im Template gelesen. Folge-Ticket fuer Verschiebung in
 *   Parser/Modules-Layer.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/TPT
 * @since      0.12.0
 * @since      0.14.3 Migration auf Component-System (ContentCard + EmptyState).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$video        = isset( $data['video'] ) && is_array( $data['video'] ) ? $data['video'] : null;
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

	$ueberschrift = (string) get_option( 'dhps_tpt_ues', '' );
	$teasertext   = (string) get_option( 'dhps_tpt_teasertext', '' );

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
