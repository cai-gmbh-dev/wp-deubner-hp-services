<?php
/**
 * Service-Template: MMB Kompakt-Layout.
 *
 * Einzeilige Merkblatt-Eintraege mit Beschreibung und PDF-Button.
 * Ideal fuer Seitenleisten und schmale Einbettungen.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/MMB
 * @since      0.9.1
 * @since      0.9.9 Beschreibungstext, aria-hidden Fix, MIL-Support.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$categories     = $data['categories'] ?? array();
$search_config  = $data['search_config'] ?? array();
$service_tag    = $data['service_tag'] ?? 'mmb';
$download_label = ( 'mil' === $service_tag ) ? 'Infografik herunterladen' : 'PDF herunterladen';
$is_mil         = ( 'mil' === $service_tag );

wp_enqueue_script( 'dhps-mmb-js' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>">

	<?php if ( ! empty( $search_config['has_search'] ) ) : ?>
	<section class="dhps-mmb-search dhps-mmb-search--compact" aria-label="<?php echo esc_attr( 'Suche' ); ?>">
		<form class="dhps-mmb-search__form" role="search" data-dhps-mmb-search>
			<div class="dhps-mmb-search__field dhps-mmb-search__field--grow">
				<input type="search"
					   class="dhps-mmb-search__input"
					   placeholder="<?php echo esc_attr( 'Suche...' ); ?>"
					   data-dhps-mmb-search-input>
			</div>
			<button type="submit" class="dhps-mmb-search__button" aria-label="<?php echo esc_attr( 'Suchen' ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<circle cx="11" cy="11" r="8"/>
					<path d="m21 21-4.35-4.35"/>
				</svg>
			</button>
		</form>
	</section>
	<?php endif; ?>

	<!-- Such-Ergebnisse -->
	<div class="dhps-mmb-results" data-dhps-mmb-results hidden>
		<div class="dhps-mmb-results__loading" data-dhps-mmb-loading>
			<span class="dhps-news__spinner" aria-hidden="true"></span>
		</div>
	</div>

	<?php if ( count( $categories ) > 1 ) : ?>
	<!-- Kategorie-Filter -->
	<nav class="dhps-filter-bar" data-dhps-mmb-filter-bar aria-label="<?php echo esc_attr( 'Kategorie-Filter' ); ?>">
		<button class="dhps-filter-bar__btn dhps-filter-bar__btn--active"
				data-filter="all" aria-pressed="true">
			<?php echo esc_html( 'Alle' ); ?>
		</button>
		<?php foreach ( $categories as $category ) : ?>
		<button class="dhps-filter-bar__btn"
				data-filter="<?php echo esc_attr( $category['id'] ); ?>"
				aria-pressed="false">
			<?php echo esc_html( $category['name'] ); ?>
		</button>
		<?php endforeach; ?>
	</nav>
	<?php endif; ?>

	<?php if ( ! empty( $categories ) ) : ?>
	<div class="dhps-mmb-categories dhps-mmb-categories--compact"
		 data-dhps-mmb-categories
		 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_mmb_nonce' ) ); ?>"
		 data-service-tag="<?php echo esc_attr( $service_tag ); ?>">

		<?php foreach ( $categories as $index => $category ) :
			$cat_id    = esc_attr( $category['id'] );
			$cat_count = count( $category['fact_sheets'] );
			$is_first  = ( 0 === $index );
		?>
		<div class="dhps-mmb-category dhps-mmb-category--compact"
			 data-dhps-mmb-category
			 data-category="<?php echo esc_attr( $category['id'] ); ?>">

			<h3 class="dhps-mmb-category__header">
				<button type="button"
						class="dhps-mmb-category__trigger"
						aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>"
						aria-controls="dhps-mmb-compact-<?php echo $cat_id; ?>"
						data-dhps-mmb-category-toggle>
					<span class="dhps-mmb-category__name">
						<?php echo esc_html( $category['name'] ); ?>
					</span>
					<span class="dhps-mmb-category__count">(<?php echo esc_html( $cat_count ); ?>)</span>
					<svg class="dhps-mmb-category__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<polyline points="6 9 12 15 18 9"/>
					</svg>
				</button>
			</h3>

			<div class="dhps-mmb-category__content"
				 id="dhps-mmb-compact-<?php echo $cat_id; ?>"
				 aria-hidden="<?php echo $is_first ? 'false' : 'true'; ?>">

				<?php if ( ! empty( $category['fact_sheets'] ) ) : ?>
				<ul class="dhps-mmb-list dhps-mmb-list--compact">
					<?php foreach ( $category['fact_sheets'] as $sheet ) : ?>
					<li class="dhps-mmb-item dhps-mmb-item--compact">
						<div class="dhps-mmb-item__row">
							<span class="dhps-mmb-item__title dhps-mmb-item__title--compact">
								<?php echo esc_html( $sheet['title'] ); ?>
							</span>
							<?php
							if ( $is_mil && ! empty( $sheet['pdf_params']['merkblatt'] ) ) {
								$pdf_href = 'https://www.deubner-online.de/einbau/mil/content/merkblaetter/' . $sheet['pdf_params']['merkblatt'] . '.pdf';
							} else {
								$pdf_href = admin_url( 'admin-ajax.php' ) . '?' . http_build_query( array_merge(
									array( 'action' => 'dhps_mmb_pdf', 'nonce' => wp_create_nonce( 'dhps_mmb_nonce' ), 'service' => $service_tag ),
									$sheet['pdf_params']
								) );
							}
							?>
							<a class="dhps-mmb-item__pdf-btn"
							   href="<?php echo esc_url( $pdf_href ); ?>"
							   target="_blank" rel="noopener"
							   title="<?php echo esc_attr( $download_label ); ?>">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
									<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
									<polyline points="7 10 12 15 17 10"/>
									<line x1="12" y1="15" x2="12" y2="3"/>
								</svg>
							</a>
						</div>
						<?php if ( ! empty( $sheet['description'] ) ) : ?>
						<p class="dhps-mmb-item__desc--compact">
							<?php echo esc_html( $sheet['description'] ); ?>
						</p>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>

			</div>
		</div>
		<?php endforeach; ?>

	</div>
	<?php endif; ?>

</div>
