<?php
/**
 * Service-Template: MAES Card-Layout (Meine Aerzteseite).
 *
 * Card-Wrapper mit Box-Shadow. Videos im 2-Spalten-Grid,
 * Merkblaetter als Accordion-Liste.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/maes/card.php
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/MAES
 * @since      0.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$videos       = $data['videos'] ?? array();
$merkblaetter = $data['merkblaetter'] ?? array();
$service_tag  = $data['service_tag'] ?? 'maes';

// Section-Filter: 'all', 'videos', 'merkblaetter'.
$section = sanitize_key( apply_filters( 'dhps_maes_section', 'all' ) );

$show_videos = in_array( $section, array( 'all', 'videos' ), true );
$show_mb     = in_array( $section, array( 'all', 'merkblaetter' ), true );

wp_enqueue_script( 'dhps-tp-js' );
wp_enqueue_script( 'dhps-mmb-js' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>"
	 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
	 data-video-mode="inline">
<div class="dhps-card">

<?php if ( $show_videos && ! empty( $videos ) ) : ?>
<section class="dhps-maes-videos" aria-label="<?php echo esc_attr( 'Video-Tipps' ); ?>">
	<h3 class="dhps-maes-section__title"><?php echo esc_html( 'Video-Tipps' ); ?></h3>
	<div class="dhps-tp-grid dhps-tp-grid--2col">
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
</section>
<?php endif; ?>

<?php if ( $show_videos && $show_mb && ! empty( $videos ) && ! empty( $merkblaetter ) ) : ?>
<hr class="dhps-divider">
<?php endif; ?>

<?php if ( $show_mb && ! empty( $merkblaetter ) ) : ?>
<section class="dhps-maes-merkblaetter" aria-label="<?php echo esc_attr( 'Merkblaetter und Checklisten' ); ?>">
	<h3 class="dhps-maes-section__title"><?php echo esc_html( 'Merkblaetter und Checklisten' ); ?></h3>
	<ul class="dhps-mmb-list">
		<?php foreach ( $merkblaetter as $sheet ) : ?>
		<li class="dhps-mmb-item" data-dhps-mmb-item>
			<button type="button"
					class="dhps-mmb-item__title"
					aria-expanded="false"
					aria-controls="dhps-maes-mb-<?php echo esc_attr( sanitize_title( $sheet['pdf_params']['merkblatt'] ?? '' ) ); ?>"
					data-dhps-mmb-item-toggle>
				<?php echo esc_html( $sheet['title'] ); ?>
			</button>
			<div class="dhps-mmb-item__detail"
				 id="dhps-maes-mb-<?php echo esc_attr( sanitize_title( $sheet['pdf_params']['merkblatt'] ?? '' ) ); ?>"
				 aria-hidden="true">
				<?php if ( ! empty( $sheet['description'] ) ) : ?>
				<p class="dhps-mmb-item__description"><?php echo esc_html( $sheet['description'] ); ?></p>
				<?php endif; ?>
				<div class="dhps-mmb-item__actions">
					<?php
					$pdf_href = admin_url( 'admin-ajax.php' ) . '?' . http_build_query( array_merge(
						array(
							'action'  => 'dhps_mmb_pdf',
							'nonce'   => wp_create_nonce( 'dhps_mmb_nonce' ),
							'service' => 'maes',
						),
						$sheet['pdf_params']
					) );
					?>
					<a class="dhps-mmb-item__download"
					   href="<?php echo esc_url( $pdf_href ); ?>"
					   target="_blank" rel="noopener">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
							<polyline points="7 10 12 15 17 10"/>
							<line x1="12" y1="15" x2="12" y2="3"/>
						</svg>
						<?php echo esc_html( 'Merkblatt herunterladen' ); ?>
					</a>
				</div>
			</div>
		</li>
		<?php endforeach; ?>
	</ul>
</section>
<?php endif; ?>

</div>
</div>
