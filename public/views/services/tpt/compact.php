<?php
/**
 * Service-Template: TPT Kompakt-Layout (horizontal, kleines Thumbnail).
 *
 * Horizontale Anordnung: kleines Thumbnail links, Titel und Teaser rechts.
 * Ideal fuer Sidebars oder schmale Footer-Spalten.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/TPT
 * @since      0.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$video = $data['video'] ?? null;

if ( null === $video || empty( $video['video_slug'] ) ) {
	return;
}

$ueberschrift = get_option( 'dhps_tpt_ues', '' );
$teasertext   = get_option( 'dhps_tpt_teasertext', '' );

wp_enqueue_script( 'dhps-tp-js' );
?>
<div class="dhps-service dhps-service--tp dhps-service--tpt <?php echo esc_attr( $layout_class . $custom_class ); ?>"
	 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
	 data-video-mode="inline"
	 data-service="taxplain">

	<article class="dhps-tpt-card dhps-tpt-card--compact">

		<div class="dhps-tp-card__poster dhps-tpt-card__poster--compact" role="button" tabindex="0"
			 aria-label="<?php echo esc_attr( 'Video abspielen: ' . $video['titel'] ); ?>"
			 data-video-slug="<?php echo esc_attr( $video['video_slug'] ); ?>"
			 data-poster-url="<?php echo esc_url( $video['poster_url'] ); ?>"
			 data-v-modus="<?php echo esc_attr( $video['v_modus'] ?? '0' ); ?>">
			<?php if ( ! empty( $video['poster_url'] ) ) : ?>
			<img src="<?php echo esc_url( $video['poster_url'] ); ?>"
				 alt="<?php echo esc_attr( $video['titel'] ); ?>"
				 class="dhps-tp-card__img"
				 loading="lazy" width="160" height="93">
			<?php endif; ?>
			<span class="dhps-tp-card__play-btn" aria-hidden="true">
				<svg width="32" height="32" viewBox="0 0 64 64">
					<circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.9)"/>
					<polygon points="26,20 26,44 46,32" fill="currentColor"/>
				</svg>
			</span>
		</div>

		<div class="dhps-tpt-card__body dhps-tpt-card__body--compact">
			<?php if ( ! empty( $ueberschrift ) ) : ?>
			<h5 class="dhps-tpt-card__heading dhps-tpt-card__heading--compact"><?php echo esc_html( $ueberschrift ); ?></h5>
			<?php endif; ?>

			<h4 class="dhps-tpt-card__title dhps-tpt-card__title--compact"><?php echo esc_html( $video['titel'] ); ?></h4>

			<?php if ( ! empty( $teasertext ) ) : ?>
			<p class="dhps-tpt-card__teaser dhps-tpt-card__teaser--compact"><?php echo esc_html( $teasertext ); ?></p>
			<?php endif; ?>
		</div>

	</article>

</div>
