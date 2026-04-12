<?php
/**
 * Service-Template: MIO Card-Layout.
 *
 * Wie das Standard-Layout, aber mit Card-Wrapper (.dhps-card)
 * fuer Box-Shadow und Padding.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/mio/card.php
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/MIO
 * @since      0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grid_columns = absint( apply_filters( 'dhps_mio_grid_columns', 2 ) );
if ( $grid_columns < 1 || $grid_columns > 4 ) { $grid_columns = 2; }
$mio_style = sanitize_key( apply_filters( 'dhps_mio_style', 'default' ) );
if ( ! in_array( $mio_style, array( 'default', 'minimal', 'shadow' ), true ) ) { $mio_style = 'default'; }

$tax_dates     = $data['tax_dates'] ?? array();
$search_config = $data['search_config'] ?? array();
$ajax_params   = $data['ajax_params'] ?? array();
$service_tag   = $data['service_tag'] ?? 'mio';

wp_enqueue_script( 'dhps-mio-js' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . ' dhps-mio-style--' . $mio_style . $custom_class ); ?>"
	 data-style="<?php echo esc_attr( $mio_style ); ?>">
<div class="dhps-card">

	<?php if ( ! empty( $tax_dates ) ) : ?>
	<section class="dhps-tax-dates" aria-label="<?php echo esc_attr( 'Steuertermine' ); ?>">
		<div class="dhps-tax-dates__grid">
			<?php foreach ( $tax_dates as $month ) : ?>
			<div class="dhps-tax-dates__column">
				<?php if ( ! empty( $month['title'] ) ) : ?>
				<h4 class="dhps-tax-dates__title"><?php echo esc_html( $month['title'] ); ?></h4>
				<?php endif; ?>

				<?php if ( ! empty( $month['entries'] ) ) : ?>
				<dl class="dhps-tax-dates__list">
					<?php foreach ( $month['entries'] as $entry ) : ?>
					<div class="dhps-tax-dates__entry">
						<dt class="dhps-tax-dates__date"><?php echo esc_html( $entry['date'] ); ?></dt>
						<dd class="dhps-tax-dates__taxes">
							<?php echo esc_html( implode( ', ', $entry['taxes'] ) ); ?>
						</dd>
					</div>
					<?php endforeach; ?>
				</dl>
				<?php endif; ?>

				<?php if ( ! empty( $month['footnote'] ) ) : ?>
				<p class="dhps-tax-dates__footnote"><?php echo esc_html( $month['footnote'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
	</section>

	<hr class="dhps-divider">
	<?php endif; ?>

	<section class="dhps-search-bar" aria-label="<?php echo esc_attr( 'Suche und Filter' ); ?>">
		<form class="dhps-search-bar__form" role="search" data-dhps-search>
			<?php if ( ! empty( $search_config['target_groups'] ) ) : ?>
			<div class="dhps-search-bar__field">
				<label class="dhps-search-bar__label screen-reader-text" for="dhps-rubriken-<?php echo esc_attr( $service_tag ); ?>">
					<?php echo esc_html( 'Zielgruppe' ); ?>
				</label>
				<select class="dhps-search-bar__select"
						id="dhps-rubriken-<?php echo esc_attr( $service_tag ); ?>"
						name="rubriken"
						data-dhps-rubriken>
					<?php foreach ( $search_config['target_groups'] as $group ) : ?>
					<option value="<?php echo esc_attr( $group ); ?>"><?php echo esc_html( $group ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php endif; ?>

			<div class="dhps-search-bar__field dhps-search-bar__field--grow">
				<label class="dhps-search-bar__label screen-reader-text" for="dhps-suchbegriff-<?php echo esc_attr( $service_tag ); ?>">
					<?php echo esc_html( 'Suchbegriff' ); ?>
				</label>
				<input type="search"
					   class="dhps-search-bar__input"
					   id="dhps-suchbegriff-<?php echo esc_attr( $service_tag ); ?>"
					   name="suchbegriff"
					   placeholder="<?php echo esc_attr( $search_config['search_placeholder'] ?? 'Suchbegriff' ); ?>"
					   data-dhps-search-input>
			</div>

			<button type="submit" class="dhps-search-bar__button" aria-label="<?php echo esc_attr( 'Suchen' ); ?>" data-dhps-search-submit>
				<svg class="dhps-search-bar__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<circle cx="11" cy="11" r="8"/>
					<path d="m21 21-4.35-4.35"/>
				</svg>
			</button>
		</form>
	</section>

	<!-- Themen-Filter (dynamisch per JS befuellt nach AJAX-Laden) -->
	<nav class="dhps-filter-bar" data-dhps-mio-filter-bar
		 aria-label="<?php echo esc_attr( 'Themen-Filter' ); ?>" hidden>
	</nav>

	<section class="dhps-news"
			 aria-label="<?php echo esc_attr( 'Aktuelle Nachrichten' ); ?>"
			 aria-live="polite"
			 data-dhps-news-container
			 data-layout="card"
			 data-card-columns="<?php echo esc_attr( $grid_columns ); ?>"
			 data-service-tag="<?php echo esc_attr( $service_tag ); ?>"
			 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_news_nonce' ) ); ?>"
			 data-fachgebiet="<?php echo esc_attr( $ajax_params['fachgebiet'] ?? 'S' ); ?>"
			 data-variante="<?php echo esc_attr( $ajax_params['variante'] ?? 'KATEGORIEN' ); ?>"
			 data-anzahl="<?php echo esc_attr( $ajax_params['anzahl'] ?? '4' ); ?>"
			 data-teasermodus="<?php echo esc_attr( $ajax_params['teasermodus'] ?? '0' ); ?>">
		<div class="dhps-news__loading" data-dhps-loading>
			<span class="dhps-news__spinner" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php echo esc_html( 'Nachrichten werden geladen...' ); ?></span>
		</div>
	</section>

</div>
</div>
