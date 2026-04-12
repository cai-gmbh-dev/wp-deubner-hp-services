<?php
/**
 * Service-Template: MMB Standard-Layout (Clean Modern).
 *
 * Rendert die geparsten MMB-Daten (Suchleiste, Rubriken mit Merkblaettern)
 * mit modernem, semantischem HTML und BEM-CSS-Klassen.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/mmb/default.php
 *
 * Verfuegbare Variablen:
 * - $data          (array)  Strukturiertes Array aus DHPS_MMB_Parser.
 * - $service_class (string) CSS-Klasse: 'dhps-service--mmb'.
 * - $layout_class  (string) CSS-Klasse: 'dhps-layout--default'.
 * - $custom_class  (string) Optionale CSS-Klasse.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/MMB
 * @since      0.9.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$categories    = $data['categories'] ?? array();
$search_config = $data['search_config'] ?? array();
$service_tag    = $data['service_tag'] ?? 'mmb';
$download_label = ( 'mil' === $service_tag ) ? 'Infografik herunterladen' : 'PDF herunterladen';
$is_mil         = ( 'mil' === $service_tag );

wp_enqueue_script( 'dhps-mmb-js' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>">

	<?php if ( ! empty( $search_config['has_search'] ) ) : ?>
	<!-- Suchleiste -->
	<section class="dhps-mmb-search" aria-label="<?php echo esc_attr( 'Merkblatt-Suche' ); ?>">
		<form class="dhps-mmb-search__form" role="search" data-dhps-mmb-search>
			<div class="dhps-mmb-search__field dhps-mmb-search__field--grow">
				<label class="dhps-mmb-search__label screen-reader-text" for="dhps-mmb-suchbegriff-<?php echo esc_attr( $service_tag ); ?>">
					<?php echo esc_html( 'Suchbegriff' ); ?>
				</label>
				<input type="search"
					   class="dhps-mmb-search__input"
					   id="dhps-mmb-suchbegriff-<?php echo esc_attr( $service_tag ); ?>"
					   name="suchbegriff"
					   placeholder="<?php echo esc_attr( $search_config['search_placeholder'] ?? 'Suchbegriff' ); ?>"
					   data-dhps-mmb-search-input>
			</div>

			<button type="submit" class="dhps-mmb-search__button" aria-label="<?php echo esc_attr( 'Suchen' ); ?>" data-dhps-mmb-search-submit>
				<svg class="dhps-mmb-search__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<circle cx="11" cy="11" r="8"/>
					<path d="m21 21-4.35-4.35"/>
				</svg>
			</button>

			<button type="button" class="dhps-mmb-search__reset" aria-label="<?php echo esc_attr( 'Suche zuruecksetzen' ); ?>" data-dhps-mmb-search-reset hidden>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<path d="M18 6L6 18M6 6l12 12"/>
				</svg>
			</button>
		</form>
	</section>
	<?php endif; ?>

	<!-- Suchergebnis-Container (wird per AJAX befuellt) -->
	<div class="dhps-mmb-results" data-dhps-mmb-results hidden>
		<div class="dhps-mmb-results__loading" data-dhps-mmb-loading>
			<span class="dhps-mmb-results__spinner" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php echo esc_html( 'Suchergebnisse werden geladen...' ); ?></span>
		</div>
	</div>

	<?php if ( ! empty( $categories ) ) : ?>
	<!-- Rubriken-Accordion -->
	<section class="dhps-mmb-categories"
			 aria-label="<?php echo esc_attr( 'Merkblatt-Kategorien' ); ?>"
			 data-dhps-mmb-categories
			 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_mmb_nonce' ) ); ?>"
			 data-service-tag="<?php echo esc_attr( $service_tag ); ?>">

		<?php foreach ( $categories as $index => $category ) :
			$cat_id    = esc_attr( $category['id'] );
			$cat_count = count( $category['fact_sheets'] );
			$is_first  = ( 0 === $index );
		?>
		<div class="dhps-mmb-category" data-dhps-mmb-category>

			<h3 class="dhps-mmb-category__header">
				<button type="button"
						class="dhps-mmb-category__trigger"
						aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>"
						aria-controls="dhps-mmb-<?php echo $cat_id; ?>"
						data-dhps-mmb-category-toggle>
					<span class="dhps-mmb-category__icon" aria-hidden="true">
						<?php echo esc_html( DHPS_MMB_Parser::get_category_icon( $category['icon_slug'] ) ); ?>
					</span>
					<span class="dhps-mmb-category__name">
						<?php echo esc_html( $category['name'] ); ?>
					</span>
					<span class="dhps-mmb-category__count" aria-label="<?php echo esc_attr( $cat_count . ' Merkblaetter' ); ?>">
						(<?php echo esc_html( $cat_count ); ?>)
					</span>
					<svg class="dhps-mmb-category__chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<polyline points="6 9 12 15 18 9"/>
					</svg>
				</button>
			</h3>

			<div class="dhps-mmb-category__content"
				 id="dhps-mmb-<?php echo $cat_id; ?>"
				 role="region"
				 aria-hidden="<?php echo $is_first ? 'false' : 'true'; ?>">

				<?php if ( ! empty( $category['fact_sheets'] ) ) : ?>
				<ul class="dhps-mmb-list">
					<?php foreach ( $category['fact_sheets'] as $sheet ) :
						$sheet_id = esc_attr( $sheet['id'] );
					?>
					<li class="dhps-mmb-item" data-dhps-mmb-item>
						<button type="button"
								class="dhps-mmb-item__title"
								aria-expanded="false"
								aria-controls="dhps-mmb-detail-<?php echo $sheet_id; ?>"
								data-dhps-mmb-item-toggle>
							<?php echo esc_html( $sheet['title'] ); ?>
						</button>

						<div class="dhps-mmb-item__detail"
							 id="dhps-mmb-detail-<?php echo $sheet_id; ?>"
							 aria-hidden="true">

							<?php if ( ! empty( $sheet['description'] ) ) : ?>
							<p class="dhps-mmb-item__description">
								<?php echo esc_html( $sheet['description'] ); ?>
							</p>
							<?php endif; ?>

							<div class="dhps-mmb-item__actions">
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
								<a class="dhps-mmb-item__download"
								   href="<?php echo esc_url( $pdf_href ); ?>"
								   target="_blank"
								   rel="noopener"
								   data-dhps-mmb-pdf="<?php echo $sheet_id; ?>">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
										<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
										<polyline points="7 10 12 15 17 10"/>
										<line x1="12" y1="15" x2="12" y2="3"/>
									</svg>
									<?php echo esc_html( $download_label ); ?>
								</a>

								<button type="button"
										class="dhps-mmb-item__collapse"
										data-dhps-mmb-item-collapse="dhps-mmb-detail-<?php echo $sheet_id; ?>">
									<?php echo esc_html( 'Einklappen' ); ?>
								</button>
							</div>
						</div>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>

			</div>
		</div>
		<?php endforeach; ?>

	</section>
	<?php endif; ?>

</div>
