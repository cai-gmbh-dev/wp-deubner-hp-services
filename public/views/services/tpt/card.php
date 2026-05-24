<?php
/**
 * Service-Template: TPT Card-Layout (Card-Wrapper mit Schatten) - v0.14.3.
 *
 * Wie Standard, aber in einer dhps-card-Box mit Box-Shadow. Migration auf
 * Component-System: ContentCard (type='video', service='tp') eingebettet in
 * `<div class="dhps-card">`. EmptyState bei fehlendem Video.
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
$layout_class = isset( $layout_class ) && is_string( $layout_class ) ? $layout_class : 'dhps-layout--card';
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
				'hint'  => __( 'Bitte spaeter erneut pruefen oder Lizenz/Auth-Daten kontrollieren.', 'wp-deubner-hp-services' ),
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

	$card_teaser = '';
	if ( '' !== $teasertext ) {
		$card_teaser = $teasertext;
	} elseif ( ! empty( $video['teaser'] ) ) {
		$card_teaser = (string) $video['teaser'];
	}

	$card_meta = array();
	if ( ! empty( $video['datum'] ) ) {
		$card_meta[] = array(
			'icon' => 'calendar',
			'text' => DHPS_TP_Parser::format_datum( (string) $video['datum'] ),
		);
	}
	?>

	<div class="dhps-card">
		<?php if ( '' !== $ueberschrift ) : ?>
			<h3 class="dhps-tpt-card__heading"><?php echo esc_html( $ueberschrift ); ?></h3>
		<?php endif; ?>

		<?php
		echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
			'content-card',
			array(
				'type'       => 'video',
				'service'    => 'tp',
				'title'      => $card_title,
				'teaser'     => $card_teaser,
				'media_url'  => $card_poster,
				'media_alt'  => $card_title,
				'class'      => 'dhps-tp-card dhps-tpt-card dhps-tpt-card--boxed',
				'meta'       => $card_meta,
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

</div>
