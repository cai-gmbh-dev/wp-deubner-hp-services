<?php
/**
 * MAES Videos Template - Nutzt TP-Infrastruktur.
 *
 * Verfuegbare Variablen:
 *   $videos       - Array der Video-Daten aus DHPS_MAES_Parser.
 *   $columns      - Grid-Spalten (1-4).
 *   $custom_class - Optionale CSS-Klasse.
 *   $video_mode   - 'inline' oder 'modal'.
 *   $style_preset - 'default', 'minimal', 'shadow'.
 *   $lazy_count   - Initiale Videos (0 = alle).
 *   $lazy_mode    - 'manual' oder 'auto'.
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lazy_count   = $lazy_count ?? 0;
$lazy_mode    = $lazy_mode ?? 'manual';
$style_preset = $style_preset ?? 'default';
$video_mode   = $video_mode ?? 'inline';

wp_enqueue_script( 'dhps-tp-js' );

$video_index = 0;
?>
<div class="dhps-service dhps-service--tp dhps-service--maes-videos dhps-tp-style--<?php echo esc_attr( $style_preset ); ?><?php echo esc_attr( $custom_class ); ?>"
	 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
	 data-video-mode="<?php echo esc_attr( $video_mode ); ?>"
	 data-lazy-count="<?php echo esc_attr( $lazy_count ); ?>"
	 data-lazy-mode="<?php echo esc_attr( $lazy_mode ); ?>">

	<div class="dhps-tp-grid dhps-tp-grid--<?php echo esc_attr( $columns ); ?>col">
		<?php foreach ( $videos as $video ) :
			$is_hidden = ( $lazy_count > 0 && $video_index >= $lazy_count );
		?>
		<article class="dhps-tp-card<?php echo $is_hidden ? ' dhps-tp-card--lazy-hidden' : ''; ?>"
				 <?php echo $is_hidden ? 'hidden' : ''; ?>
				 data-video-index="<?php echo esc_attr( $video_index ); ?>">
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
		<?php $video_index++; endforeach; ?>
	</div>

	<?php if ( $lazy_count > 0 && $video_index > $lazy_count ) : ?>
	<button class="dhps-tp-load-more" type="button">
		<?php echo esc_html( 'Weitere Videos laden' ); ?>
	</button>
	<?php endif; ?>

</div>
