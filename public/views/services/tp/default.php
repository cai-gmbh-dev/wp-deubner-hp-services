<?php
/**
 * Service-Template: TP Standard-Layout (Clean Modern).
 *
 * Rendert die geparsten TP-Daten (Featured Video, Kategorien mit Videos)
 * mit modernem, semantischem HTML und BEM-CSS-Klassen.
 *
 * Videos werden als Poster-Images angezeigt. Der iframe wird erst
 * bei Klick ueber den AJAX-Proxy geladen (kdnr-Schutz + Performance).
 *
 * Konfigurierbar ueber WordPress-Filter:
 * - dhps_tp_grid_columns (int)    Spalten im Grid (Standard: 3).
 * - dhps_tp_lazy_count   (int)    Anzahl sichtbarer Cards (0 = alle).
 * - dhps_tp_lazy_mode    (string) 'manual' oder 'auto'.
 * - dhps_tp_style        (string) 'default', 'minimal' oder 'shadow'.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/tp/default.php
 *
 * Verfuegbare Variablen:
 * - $data          (array)  Strukturiertes Array aus DHPS_TP_Parser.
 * - $service_class (string) CSS-Klasse: 'dhps-service--tp'.
 * - $layout_class  (string) CSS-Klasse: 'dhps-layout--default'.
 * - $custom_class  (string) Optionale CSS-Klasse.
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

	<?php if ( $featured ) : ?>
	<!-- Featured Video -->
	<section class="dhps-tp-featured" aria-label="<?php echo esc_attr( 'Aktueller Video-Tipp' ); ?>">
		<h3 class="dhps-tp-featured__heading"><?php echo esc_html( 'Der aktuelle Video-Tipp' ); ?></h3>
		<div class="dhps-tp-video dhps-tp-video--featured">
			<div class="dhps-tp-video__player">
				<div class="dhps-tp-video__poster" role="button" tabindex="0"
					 aria-label="<?php echo esc_attr( 'Video abspielen: ' . $featured['titel'] ); ?>"
					 data-video-slug="<?php echo esc_attr( $featured['video_slug'] ); ?>"
					 data-poster-url="<?php echo esc_url( $featured['poster_url'] ); ?>"
					 data-v-modus="<?php echo esc_attr( $featured['v_modus'] ?? '0' ); ?>">
					<?php if ( ! empty( $featured['poster_url'] ) ) : ?>
					<img src="<?php echo esc_url( $featured['poster_url'] ); ?>"
						 alt="<?php echo esc_attr( $featured['titel'] ); ?>"
						 class="dhps-tp-video__poster-img"
						 loading="lazy" width="500" height="291">
					<?php endif; ?>
					<span class="dhps-tp-video__play-btn" aria-hidden="true" style="color: var(--dhps-color-steuern)">
						<svg width="64" height="64" viewBox="0 0 64 64">
							<circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.9)"/>
							<polygon points="26,20 26,44 46,32" fill="currentColor"/>
						</svg>
					</span>
				</div>
			</div>
			<div class="dhps-tp-video__info">
				<h4 class="dhps-tp-video__title"><?php echo esc_html( $featured['titel'] ); ?></h4>
				<?php if ( ! empty( $featured['teaser'] ) ) : ?>
				<p class="dhps-tp-video__teaser"><?php echo esc_html( $featured['teaser'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $featured['datum'] ) ) : ?>
				<span class="dhps-tp-video__date"><?php echo esc_html( DHPS_TP_Parser::format_datum( $featured['datum'] ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<hr class="dhps-divider">
	<?php endif; ?>

	<?php if ( ! empty( $categories ) ) : ?>
	<!-- Alle Video-Tipps -->
	<section class="dhps-tp-catalog" aria-label="<?php echo esc_attr( 'Alle Video-Tipps' ); ?>">
		<h3 class="dhps-tp-catalog__heading"><?php echo esc_html( 'Alle Video-Tipps' ); ?></h3>

		<!-- Kategorie-Filter -->
		<nav class="dhps-filter-bar dhps-tp-catalog__filter" aria-label="<?php echo esc_attr( 'Kategorien' ); ?>">
			<button class="dhps-filter-bar__btn dhps-tp-filter__btn dhps-filter-bar__btn--active dhps-tp-filter__btn--active"
					data-filter="all" aria-pressed="true">
				<?php echo esc_html( 'Alle' ); ?>
			</button>
			<?php foreach ( $categories as $index => $cat ) : ?>
			<button class="dhps-filter-bar__btn dhps-tp-filter__btn"
					data-filter="<?php echo esc_attr( $index ); ?>"
					aria-pressed="false">
				<?php echo esc_html( $cat['name'] ); ?>
			</button>
			<?php endforeach; ?>
		</nav>

		<!-- Video-Grid -->
		<div class="dhps-tp-grid dhps-tp-grid--<?php echo esc_attr( $grid_columns ); ?>col">
			<?php foreach ( $categories as $cat_index => $cat ) : ?>
				<?php foreach ( $cat['videos'] as $video ) :
					$is_hidden = ( $lazy_count > 0 && $video_index >= $lazy_count );
				?>
				<article class="dhps-tp-card<?php echo $is_hidden ? ' dhps-tp-card--lazy-hidden' : ''; ?>"
						 <?php echo $is_hidden ? ' hidden' : ''; ?>
						 data-category="<?php echo esc_attr( $cat_index ); ?>"
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
						<?php if ( ! empty( $video['datum'] ) ) : ?>
						<span class="dhps-tp-card__date"><?php echo esc_html( DHPS_TP_Parser::format_datum( $video['datum'] ) ); ?></span>
						<?php endif; ?>
					</div>
				</article>
				<?php
					$video_index++;
				endforeach; ?>
			<?php endforeach; ?>
		</div>

		<?php if ( $lazy_count > 0 ) : ?>
		<button class="dhps-tp-load-more dhps-btn dhps-btn--primary">
			<?php echo esc_html( 'Weitere Videos laden' ); ?>
		</button>
		<?php endif; ?>
	</section>
	<?php endif; ?>

</div>
