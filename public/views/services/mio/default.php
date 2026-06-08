<?php
/**
 * Service-Template: MIO Standard-Layout.
 *
 * Rendert die geparsten MIO-Daten (Steuertermine, Suchleiste, News-Container)
 * mit modernem, semantischem HTML und BEM-CSS-Klassen.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/mio/default.php
 *
 * Verfuegbare Variablen:
 * - $data          (array)  Strukturiertes Array aus DHPS_MIO_Parser.
 * - $service_class (string) CSS-Klasse: 'dhps-service--mio'.
 * - $layout_class  (string) CSS-Klasse: 'dhps-layout--default'.
 * - $custom_class  (string) Optionale CSS-Klasse (mit fuehrendem Leerzeichen oder leer).
 *
 * Datenstruktur ($data):
 * - 'tax_dates'     => [['title', 'entries' => [['date', 'taxes']], 'footnote'], ...]
 * - 'search_config' => ['target_groups' => [...], 'search_placeholder' => '...']
 * - 'ajax_params'   => ['fachgebiet', 'variante', 'anzahl', ...]
 * - 'service_tag'   => 'mio'
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

// v0.18.0: Pipeline-Garantie (siehe MMB/default.php Header). Pseudo-Rebuild
// rekonstruiert $tax_dates 1:1 ueber dhps_mio_item_to_legacy_month-Helper.
// Search-Config + AJAX-Params kommen aus Collection-Meta (PFLICHT, sonst
// News-Container bricht!).
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

$service_tag = $data['service_tag'] ?? 'mio';

// MIO-JavaScript fuer AJAX-News enqueuen (conditional loading).
wp_enqueue_script( 'dhps-mio-js' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . ' dhps-mio-style--' . $mio_style . $custom_class ); ?>"
	 data-style="<?php echo esc_attr( $mio_style ); ?>">

	<?php if ( ! empty( $tax_dates ) ) : ?>
	<!-- Steuertermine -->
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
				<p class="dhps-tax-dates__footnote">
					<?php echo esc_html( $month['footnote'] ); ?>
				</p>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
	</section>

	<hr class="dhps-divider">
	<?php endif; ?>

	<!-- Such- und Filterleiste (Partial seit 0.14.2). -->
	<?php include __DIR__ . '/partials/search-form.php'; ?>

	<!-- News-Container (wird per AJAX befuellt) -->
	<section class="dhps-news"
			 aria-label="<?php echo esc_attr( 'Aktuelle Nachrichten' ); ?>"
			 aria-live="polite"
			 data-dhps-news-container
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
