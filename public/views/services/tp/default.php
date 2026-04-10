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

wp_enqueue_script( 'dhps-tp-js' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>"
	 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>">

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
					<span class="dhps-tp-video__play-btn" aria-hidden="true">
						<svg width="64" height="64" viewBox="0 0 64 64">
							<circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.9)"/>
							<polygon points="26,20 26,44 46,32" fill="#2e8a37"/>
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
		<div class="dhps-tp-grid">
			<?php foreach ( $categories as $cat_index => $cat ) : ?>
				<?php foreach ( $cat['videos'] as $video ) : ?>
				<article class="dhps-tp-card"
						 data-category="<?php echo esc_attr( $cat_index ); ?>"
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
						<?php if ( ! empty( $video['datum'] ) ) : ?>
						<span class="dhps-tp-card__date"><?php echo esc_html( DHPS_TP_Parser::format_datum( $video['datum'] ) ); ?></span>
						<?php endif; ?>
					</div>
				</article>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

</div>
