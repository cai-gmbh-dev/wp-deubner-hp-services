<?php
/**
 * Service-Template: TPT Standard-Layout (Single Video Teaser).
 *
 * Rendert das einzelne TaxPlain-Teaser-Video als kompakte Card mit Poster,
 * Titel und Teaser-Text. Klick auf Poster laedt iframe ueber AJAX-Proxy.
 *
 * Verfuegbare Variablen:
 * - $data          (array)  Strukturiertes Array aus DHPS_TPT_Parser:
 *                            - 'video'       (array|null) Das einzelne Video
 *                            - 'service_tag' (string)     'tpt'
 * - $service_class (string) 'dhps-service--tpt'
 * - $layout_class  (string) 'dhps-layout--default'
 * - $custom_class  (string) Optionale CSS-Klasse
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

// Admin-Konfigurierte Texte aus Optionen.
$ueberschrift = get_option( 'dhps_tpt_ues', '' );
$teasertext   = get_option( 'dhps_tpt_teasertext', '' );

wp_enqueue_script( 'dhps-tp-js' );
?>
<div class="dhps-service dhps-service--tp dhps-service--tpt <?php echo esc_attr( $layout_class . $custom_class ); ?>"
	 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
	 data-video-mode="inline"
	 data-service="taxplain">

	<article class="dhps-tpt-card">

		<?php if ( ! empty( $ueberschrift ) ) : ?>
		<h3 class="dhps-tpt-card__heading"><?php echo esc_html( $ueberschrift ); ?></h3>
		<?php endif; ?>

		<div class="dhps-tp-card__poster" role="button" tabindex="0"
			 aria-label="<?php echo esc_attr( 'Video abspielen: ' . $video['titel'] ); ?>"
			 data-video-slug="<?php echo esc_attr( $video['video_slug'] ); ?>"
			 data-poster-url="<?php echo esc_url( $video['poster_url'] ); ?>"
			 data-v-modus="<?php echo esc_attr( $video['v_modus'] ?? '0' ); ?>">
			<?php if ( ! empty( $video['poster_url'] ) ) : ?>
			<img src="<?php echo esc_url( $video['poster_url'] ); ?>"
				 alt="<?php echo esc_attr( $video['titel'] ); ?>"
				 class="dhps-tp-card__img"
				 loading="lazy" width="500" height="291">
			<?php endif; ?>
			<span class="dhps-tp-card__play-btn" aria-hidden="true">
				<svg width="56" height="56" viewBox="0 0 64 64">
					<circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.9)"/>
					<polygon points="26,20 26,44 46,32" fill="currentColor"/>
				</svg>
			</span>
		</div>

		<div class="dhps-tpt-card__body">
			<h4 class="dhps-tpt-card__title"><?php echo esc_html( $video['titel'] ); ?></h4>

			<?php if ( ! empty( $teasertext ) ) : ?>
			<p class="dhps-tpt-card__teaser"><?php echo esc_html( $teasertext ); ?></p>
			<?php elseif ( ! empty( $video['teaser'] ) ) : ?>
			<p class="dhps-tpt-card__teaser"><?php echo esc_html( $video['teaser'] ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $video['datum'] ) ) : ?>
			<span class="dhps-tpt-card__date">
				<?php echo esc_html( DHPS_TP_Parser::format_datum( $video['datum'] ) ); ?>
			</span>
			<?php endif; ?>
		</div>

	</article>

</div>
