<?php
/**
 * MAES Videos Compact Template - Nutzt TP-Infrastruktur.
 *
 * Kompakte Listen-Variante: kleine Thumbnails mit Titel nebeneinander.
 *
 * Verfuegbare Variablen:
 *   $videos       - Array der Video-Daten aus DHPS_MAES_Parser.
 *   $custom_class - Optionale CSS-Klasse.
 *   $video_mode   - Video-Modus (default: 'inline').
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$video_mode = $video_mode ?? 'inline';
?>
<div class="dhps-service dhps-service--tp dhps-service--maes-videos dhps-tp-compact<?php echo esc_attr( $custom_class ); ?>"
	 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
	 data-video-mode="<?php echo esc_attr( $video_mode ); ?>">

	<ul class="dhps-tp-compact__list">
		<?php foreach ( $videos as $video ) : ?>
		<li class="dhps-tp-compact__item">
			<div class="dhps-tp-compact__poster" role="button" tabindex="0"
				 aria-label="<?php echo esc_attr( 'Video: ' . $video['title'] ); ?>"
				 data-video-slug="<?php echo esc_attr( $video['video_slug'] ); ?>"
				 data-poster-url="<?php echo esc_url( $video['poster_url'] ); ?>"
				 data-v-modus="0">
				<?php if ( ! empty( $video['poster_url'] ) ) : ?>
				<img src="<?php echo esc_url( $video['poster_url'] ); ?>"
					 alt="<?php echo esc_attr( $video['title'] ); ?>"
					 class="dhps-tp-compact__thumb" loading="lazy" width="80" height="47">
				<?php endif; ?>
				<span class="dhps-tp-card__play-btn dhps-tp-compact__play-btn" aria-hidden="true" style="color: var(--dhps-color-medizin)">
					<svg width="24" height="24" viewBox="0 0 64 64">
						<circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.9)"/>
						<polygon points="26,20 26,44 46,32" fill="currentColor"/>
					</svg>
				</span>
			</div>
			<span class="dhps-tp-compact__title"><?php echo esc_html( $video['title'] ); ?></span>
		</li>
		<?php endforeach; ?>
	</ul>

</div>
