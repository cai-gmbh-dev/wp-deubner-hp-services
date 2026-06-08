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

// v0.18.0: Pipeline-Garantie (siehe MMB/default.php Header).
$collection = dhps_collection_or_empty( $collection, 'mio' );
$tax_dates  = array();
foreach ( $collection as $item ) {
	/** @var DHPS_Content_Item $item */
	$legacy_month = dhps_mio_item_to_legacy_month( $item );
	if ( ! empty( $legacy_month ) ) {
		$tax_dates[] = $legacy_month;
	}
}
$search_config = (array) $collection->get_meta( 'search_config', array() );
$ajax_params   = (array) $collection->get_meta( 'ajax_params', array() );


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
			 data-layout="compact"
			 data-service-tag="<?php echo esc_attr( $service_tag ); ?>"
			 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_news_nonce' ) ); ?>"
			 data-fachgebiet="<?php echo esc_attr( $ajax_params['fachgebiet'] ?? 'S' ); ?>"
			 data-variante="<?php echo esc_attr( $ajax_params['variante'] ?? 'KATEGORIEN' ); ?>"
			 data-anzahl="<?php echo esc_attr( $ajax_params['anzahl'] ?? '10' ); ?>"
			 data-teasermodus="<?php echo esc_attr( $ajax_params['teasermodus'] ?? '0' ); ?>">
		<!-- Skeleton-Slot fuer Load-More und Live-Search (seit 0.14.2). Wird von dhps-mio.js getoggled. -->
		<div class="dhps-mio-skeleton-slot" data-dhps-mio-skeleton hidden>
			<?php echo dhps_component( 'skeleton-loader', array(
				'type'  => 'list',
				'count' => 3,
			) ); ?>
		</div>
		<div class="dhps-news__loading" data-dhps-loading>
			<span class="dhps-news__spinner" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php echo esc_html( 'Nachrichten werden geladen...' ); ?></span>
		</div>
	</section>

</div>
