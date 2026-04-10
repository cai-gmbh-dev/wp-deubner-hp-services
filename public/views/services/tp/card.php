<?php
/**
 * Service-Template: TP Card-Layout (Gallery).
 *
 * Alle Videos gleichberechtigt im Card-Grid, kein Featured Video.
 * Card-Wrapper mit Box-Shadow.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/tp/card.php
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/TP
 * @since      0.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$featured    = $data['featured_video'] ?? null;
$categories  = $data['categories'] ?? array();
$service_tag = $data['service_tag'] ?? 'tp';

// Alle Videos in eine flache Liste mergen (Featured + Kategorien).
$all_videos = array();
if ( $featured ) {
	$featured['_category'] = 'featured';
	$all_videos[] = $featured;
}
foreach ( $categories as $cat_index => $cat ) {
	foreach ( $cat['videos'] as $video ) {
		$video['_category'] = $cat_index;
		$video['_category_name'] = $cat['name'];
		$all_videos[] = $video;
	}
}

wp_enqueue_script( 'dhps-tp-js' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>"
	 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>">
<div class="dhps-card">

	<h3 class="dhps-tp-catalog__heading"><?php echo esc_html( 'TaxPlain Video-Tipps' ); ?></h3>

	<?php if ( ! empty( $categories ) ) : ?>
	<nav class="dhps-filter-bar dhps-tp-catalog__filter" aria-label="<?php echo esc_attr( 'Kategorien' ); ?>">
		<button class="dhps-filter-bar__btn dhps-tp-filter__btn dhps-filter-bar__btn--active dhps-tp-filter__btn--active" data-filter="all" aria-pressed="true">
			<?php echo esc_html( 'Alle' ); ?>
		</button>
		<?php foreach ( $categories as $index => $cat ) : ?>
		<button class="dhps-filter-bar__btn dhps-tp-filter__btn" data-filter="<?php echo esc_attr( $index ); ?>" aria-pressed="false">
			<?php echo esc_html( $cat['name'] ); ?>
		</button>
		<?php endforeach; ?>
	</nav>
	<?php endif; ?>

	<div class="dhps-tp-grid dhps-tp-grid--3col">
		<?php foreach ( $all_videos as $video ) : ?>
		<article class="dhps-tp-card"
				 data-category="<?php echo esc_attr( $video['_category'] ?? '' ); ?>"
				 data-video-id="<?php echo esc_attr( $video['video_id'] ); ?>">
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
					<svg width="48" height="48" viewBox="0 0 64 64">
						<circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.9)"/>
						<polygon points="26,20 26,44 46,32" fill="#2e8a37"/>
					</svg>
				</span>
			</div>
			<div class="dhps-tp-card__body">
				<h4 class="dhps-tp-card__title"><?php echo esc_html( $video['titel'] ); ?></h4>
				<?php if ( ! empty( $video['teaser'] ) ) : ?>
				<p class="dhps-tp-card__teaser"><?php echo esc_html( mb_strimwidth( $video['teaser'], 0, 100, '...' ) ); ?></p>
				<?php endif; ?>
				<div class="dhps-tp-card__meta">
					<?php if ( ! empty( $video['_category_name'] ) ) : ?>
					<span class="dhps-tp-card__badge"><?php echo esc_html( $video['_category_name'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $video['datum'] ) ) : ?>
					<span class="dhps-tp-card__date"><?php echo esc_html( DHPS_TP_Parser::format_datum( $video['datum'] ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php endforeach; ?>
	</div>

</div>
</div>
