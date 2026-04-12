<?php
/**
 * Service-Template: MAES Kompakt-Layout (Meine Aerzteseite).
 *
 * Videos als einfache Liste mit Play-Icons, Merkblaetter als
 * kompakte Einzeiler. Ideal fuer Seitenleisten.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/maes/compact.php
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

<?php if ( $show_videos && ! empty( $videos ) ) : ?>
<section class="dhps-maes-videos" aria-label="<?php echo esc_attr( 'Video-Tipps' ); ?>">
	<h3 class="dhps-maes-section__title"><?php echo esc_html( 'Video-Tipps' ); ?></h3>
	<ul class="dhps-tp-compact__list">
		<?php foreach ( $videos as $video ) : ?>
		<li class="dhps-tp-compact__item"
			data-video-slug="<?php echo esc_attr( $video['video_slug'] ); ?>"
			data-poster-url="<?php echo esc_url( $video['poster_url'] ); ?>"
			data-v-modus="0">
			<button type="button" class="dhps-tp-compact__video-btn"
					aria-label="<?php echo esc_attr( 'Video abspielen: ' . $video['title'] ); ?>">
				<svg class="dhps-tp-compact__play-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
					<polygon points="5,3 19,12 5,21"/>
				</svg>
				<span class="dhps-tp-compact__title"><?php echo esc_html( $video['title'] ); ?></span>
			</button>
		</li>
		<?php endforeach; ?>
	</ul>
</section>
<?php endif; ?>

<?php if ( $show_videos && $show_mb && ! empty( $videos ) && ! empty( $merkblaetter ) ) : ?>
<hr class="dhps-divider">
<?php endif; ?>

<?php if ( $show_mb && ! empty( $merkblaetter ) ) : ?>
<section class="dhps-maes-merkblaetter" aria-label="<?php echo esc_attr( 'Merkblaetter und Checklisten' ); ?>">
	<h3 class="dhps-maes-section__title"><?php echo esc_html( 'Merkblaetter und Checklisten' ); ?></h3>
	<ul class="dhps-mmb-list dhps-mmb-list--compact">
		<?php foreach ( $merkblaetter as $sheet ) : ?>
		<li class="dhps-mmb-item dhps-mmb-item--compact">
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
			<a class="dhps-mmb-item__download dhps-mmb-item__download--inline"
			   href="<?php echo esc_url( $pdf_href ); ?>"
			   target="_blank" rel="noopener">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
					<polyline points="7 10 12 15 17 10"/>
					<line x1="12" y1="15" x2="12" y2="3"/>
				</svg>
				<?php echo esc_html( $sheet['title'] ); ?>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>
</section>
<?php endif; ?>

</div>
