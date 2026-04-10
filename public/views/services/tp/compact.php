<?php
/**
 * Service-Template: TP Kompakt-Layout.
 *
 * Accordion-Sections pro Rubrik mit einzeiligen Video-Zeilen.
 * Kein Featured Video, kein Poster-Grid. Ideal fuer Seitenleisten.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/tp/compact.php
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/TP
 * @since      0.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$categories  = $data['categories'] ?? array();
$service_tag = $data['service_tag'] ?? 'tp';

wp_enqueue_script( 'dhps-tp-js' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>"
	 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>">

	<?php if ( ! empty( $categories ) ) : ?>
	<div class="dhps-tp-compact">
		<?php foreach ( $categories as $cat_index => $cat ) :
			$cat_id    = 'dhps-tp-compact-' . $cat_index;
			$is_first  = ( 0 === $cat_index );
			$vid_count = count( $cat['videos'] );
		?>
		<div class="dhps-tp-compact__section">
			<h3 class="dhps-tp-compact__header">
				<button type="button"
						class="dhps-tp-compact__trigger"
						aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>"
						aria-controls="<?php echo esc_attr( $cat_id ); ?>">
					<span class="dhps-tp-compact__name"><?php echo esc_html( $cat['name'] ); ?></span>
					<span class="dhps-tp-compact__count">(<?php echo esc_html( $vid_count ); ?>)</span>
					<svg class="dhps-tp-compact__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<polyline points="6 9 12 15 18 9"/>
					</svg>
				</button>
			</h3>

			<div class="dhps-tp-compact__content"
				 id="<?php echo esc_attr( $cat_id ); ?>"
				 <?php echo $is_first ? '' : 'aria-hidden="true"'; ?>>
				<ul class="dhps-tp-compact__list">
					<?php foreach ( $cat['videos'] as $video ) : ?>
					<li class="dhps-tp-compact__item"
						data-video-slug="<?php echo esc_attr( $video['video_slug'] ); ?>"
						data-poster-url="<?php echo esc_url( $video['poster_url'] ); ?>"
						data-v-modus="<?php echo esc_attr( $video['v_modus'] ?? '0' ); ?>">
						<button type="button" class="dhps-tp-compact__video-btn" aria-label="<?php echo esc_attr( 'Video abspielen: ' . $video['titel'] ); ?>">
							<svg class="dhps-tp-compact__play-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
								<polygon points="5,3 19,12 5,21"/>
							</svg>
							<span class="dhps-tp-compact__title"><?php echo esc_html( $video['titel'] ); ?></span>
						</button>
						<?php if ( ! empty( $video['datum'] ) ) : ?>
						<span class="dhps-tp-compact__date"><?php echo esc_html( DHPS_TP_Parser::format_datum( $video['datum'] ) ); ?></span>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

</div>
