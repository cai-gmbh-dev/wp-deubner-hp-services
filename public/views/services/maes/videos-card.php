<?php
/**
 * MAES Videos Card Template - Nutzt TP-Infrastruktur.
 *
 * Card-Variante mit Box-Shadow-Wrapper und Style-Preset.
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
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$style_preset = $style_preset ?? 'medizin';
$video_mode   = $video_mode ?? 'inline';
?>
<div class="dhps-card">
	<div class="dhps-service dhps-service--tp dhps-service--maes-videos dhps-tp-style--<?php echo esc_attr( $style_preset ); ?><?php echo esc_attr( $custom_class ); ?>"
		 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
		 data-video-mode="<?php echo esc_attr( $video_mode ); ?>">

		<div class="dhps-tp-grid dhps-tp-grid--<?php echo esc_attr( $columns ); ?>col">
			<?php foreach ( $videos as $video ) : ?>
			<article class="dhps-tp-card">
				<div class="dhps-tp-card__poster" role="button" tabindex="0"
					 aria-label="<?php echo esc_attr( 'Video: ' . $video['title'] ); ?>"
					 data-video-slug="<?php echo esc_attr( $video['video_slug'] ); ?>"
					 data-poster-url="<?php echo esc_url( $video['poster_url'] ); ?>"
					 data-v-modus="0">
					<?php if ( ! empty( $video['poster_url'] ) ) : ?>
					<img src="<?php echo esc_url( $video['poster_url'] ); ?>"
						 alt="<?php echo esc_attr( $video['title'] ); ?>"
						 class="dhps-tp-card__img" loading="lazy" width="500" height="291">
					<?php endif; ?>
					<span class="dhps-tp-card__play-btn" aria-hidden="true" style="color: var(--dhps-color-medizin)">
						<svg width="48" height="48" viewBox="0 0 64 64">
							<circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.9)"/>
							<polygon points="26,20 26,44 46,32" fill="currentColor"/>
						</svg>
					</span>
				</div>
				<div class="dhps-tp-card__body">
					<h4 class="dhps-tp-card__title"><?php echo esc_html( $video['title'] ); ?></h4>
					<?php if ( ! empty( $video['description'] ) ) : ?>
					<p class="dhps-tp-card__teaser"><?php echo esc_html( mb_strimwidth( $video['description'], 0, 120, '...' ) ); ?></p>
					<?php endif; ?>
				</div>
			</article>
			<?php endforeach; ?>
		</div>

	</div>
</div>
