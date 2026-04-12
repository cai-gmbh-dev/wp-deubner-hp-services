<?php
/**
 * Service-Template: MIO Kompakt-Layout.
 *
 * Reduziertes Layout mit weniger Abstaenden.
 * Zeigt Steuertermine und Suchleiste kompakter an.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/mio/compact.php
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/MIO
 * @since      0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tax_dates     = $data['tax_dates'] ?? array();
$search_config = $data['search_config'] ?? array();
$ajax_params   = $data['ajax_params'] ?? array();
$service_tag   = $data['service_tag'] ?? 'mio';

wp_enqueue_script( 'dhps-mio-js' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>"
	 data-style="compact">

	<?php if ( ! empty( $tax_dates ) ) : ?>
	<section class="dhps-compact-dates" aria-label="<?php echo esc_attr( 'Steuertermine' ); ?>">
		<?php foreach ( $tax_dates as $month ) : ?>
		<div class="dhps-compact-dates__item">
			<?php if ( ! empty( $month['title'] ) ) : ?>
			<strong><?php echo esc_html( $month['title'] ); ?>:</strong>
			<?php endif; ?>
			<?php if ( ! empty( $month['entries'] ) ) : ?>
			<?php
			$inline_entries = array();
			foreach ( $month['entries'] as $entry ) {
				$inline_entries[] = $entry['date'] . ' ' . implode( ', ', $entry['taxes'] );
			}
			echo esc_html( implode( ' · ', $inline_entries ) );
			?>
			<?php endif; ?>
			<?php if ( ! empty( $month['footnote'] ) ) : ?>
			<span class="dhps-compact-dates__footnote"> · <?php echo esc_html( $month['footnote'] ); ?></span>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</section>
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
			 data-layout="compact"
			 data-service-tag="<?php echo esc_attr( $service_tag ); ?>"
			 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_news_nonce' ) ); ?>"
			 data-fachgebiet="<?php echo esc_attr( $ajax_params['fachgebiet'] ?? 'S' ); ?>"
			 data-variante="<?php echo esc_attr( $ajax_params['variante'] ?? 'KATEGORIEN' ); ?>"
			 data-anzahl="<?php echo esc_attr( $ajax_params['anzahl'] ?? '10' ); ?>"
			 data-teasermodus="<?php echo esc_attr( $ajax_params['teasermodus'] ?? '0' ); ?>">
		<div class="dhps-news__loading" data-dhps-loading>
			<span class="dhps-news__spinner" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php echo esc_html( 'Nachrichten werden geladen...' ); ?></span>
		</div>
	</section>

</div>
