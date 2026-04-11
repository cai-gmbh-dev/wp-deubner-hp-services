<?php
/**
 * Service-Template: TP Card-Layout (Gallery).
 *
 * Alle Videos gleichberechtigt im Card-Grid, kein Featured Video.
 * Card-Wrapper mit Box-Shadow.
 *
 * Konfigurierbar ueber WordPress-Filter:
 * - dhps_tp_grid_columns (int)    Spalten im Grid (Standard: 3).
 * - dhps_tp_lazy_count   (int)    Anzahl sichtbarer Cards (0 = alle).
 * - dhps_tp_lazy_mode    (string) 'manual' oder 'auto'.
 * - dhps_tp_style        (string) 'default', 'minimal' oder 'shadow'.
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

// Konfigurierbare Optionen via WordPress-Filter.
$grid_columns = absint( apply_filters( 'dhps_tp_grid_columns', 3 ) );
$lazy_count   = absint( apply_filters( 'dhps_tp_lazy_count', 0 ) );
$lazy_mode    = sanitize_key( apply_filters( 'dhps_tp_lazy_mode', 'manual' ) );
$tp_style     = sanitize_key( apply_filters( 'dhps_tp_style', 'default' ) );

// Sicherheitsgrenzen.
if ( $grid_columns < 1 || $grid_columns > 6 ) {
	$grid_columns = 3;
}
if ( ! in_array( $lazy_mode, array( 'manual', 'auto' ), true ) ) {
	$lazy_mode = 'manual';
}
if ( ! in_array( $tp_style, array( 'default', 'minimal', 'shadow' ), true ) ) {
	$tp_style = 'default';
}

wp_enqueue_script( 'dhps-tp-js' );

$video_index = 0;
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?> dhps-tp-style--<?php echo esc_attr( $tp_style ); ?>"
	 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
	 data-style="<?php echo esc_attr( $tp_style ); ?>"
	 data-columns="<?php echo esc_attr( $grid_columns ); ?>"
	 data-lazy-count="<?php echo esc_attr( $lazy_count ); ?>"
	 data-lazy-mode="<?php echo esc_attr( $lazy_mode ); ?>">
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

	<div class="dhps-tp-grid dhps-tp-grid--<?php echo esc_attr( $grid_columns ); ?>col">
		<?php foreach ( $all_videos as $video ) :
			$is_hidden = ( $lazy_count > 0 && $video_index >= $lazy_count );
		?>
		<article class="dhps-tp-card<?php echo $is_hidden ? ' dhps-tp-card--lazy-hidden' : ''; ?>"
				 <?php echo $is_hidden ? ' hidden' : ''; ?>
				 data-category="<?php echo esc_attr( $video['_category'] ?? '' ); ?>"
				 data-video-id="<?php echo esc_attr( $video['video_id'] ); ?>"
				 data-video-index="<?php echo esc_attr( $video_index ); ?>">
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
				<span class="dhps-tp-card__play-btn" aria-hidden="true" style="color: var(--dhps-color-steuern)">
					<svg width="48" height="48" viewBox="0 0 64 64">
						<circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.9)"/>
						<polygon points="26,20 26,44 46,32" fill="currentColor"/>
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
		<?php
			$video_index++;
		endforeach; ?>
	</div>

	<?php if ( $lazy_count > 0 ) : ?>
	<button class="dhps-tp-load-more dhps-btn dhps-btn--primary">
		<?php echo esc_html( 'Weitere Videos laden' ); ?>
	</button>
	<?php endif; ?>

</div>
</div>
