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

// v0.17.3: Collection-Pfad wenn MIO-Adapter aktiv ist, sonst Legacy.
// Pseudo-Rebuild rekonstruiert $tax_dates 1:1 in Parser-Order ueber den
// Helper dhps_mio_item_to_legacy_month(). Search-Config + AJAX-Params
// kommen aus Collection-Meta (PFLICHT, sonst News-Container bricht!).
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
	$tax_dates = array();
	foreach ( $collection as $item ) {
		/** @var DHPS_Content_Item $item */
		$legacy_month = dhps_mio_item_to_legacy_month( $item );
		if ( ! empty( $legacy_month ) ) {
			$tax_dates[] = $legacy_month;
		}
	}
	$search_config = (array) $collection->get_meta( 'search_config', array() );
	$ajax_params   = (array) $collection->get_meta( 'ajax_params', array() );
} else {
	$tax_dates     = $data['tax_dates'] ?? array();
	$search_config = $data['search_config'] ?? array();
	$ajax_params   = $data['ajax_params'] ?? array();
}

$service_tag = $data['service_tag'] ?? 'mio';

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

	<!-- Such- und Filterleiste (Partial seit 0.14.2). -->
	<?php include __DIR__ . '/partials/search-form.php'; ?>

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
		<!-- Skeleton-Slot fuer Load-More und Live-Search (seit 0.14.2). Wird von dhps-mio.js getoggled. -->
		<div class="dhps-mio-skeleton-slot" data-dhps-mio-skeleton hidden>
			<?php echo dhps_component( 'skeleton-loader', array(
				'type'  => 'card',
				'count' => 3,
			) ); ?>
		</div>
		<div class="dhps-news__loading" data-dhps-loading>
			<span class="dhps-news__spinner" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php echo esc_html( 'Nachrichten werden geladen...' ); ?></span>
		</div>
	</section>

</div>
</div>
