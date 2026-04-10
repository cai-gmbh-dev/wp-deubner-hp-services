<?php
/**
 * Service-Template: MMB Card-Layout.
 *
 * Card-Wrapper mit Tab-Navigation fuer Rubriken und
 * Card-Grid fuer Merkblaetter.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/mmb/card.php
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
$service_tag   = $data['service_tag'] ?? 'mmb';

wp_enqueue_script( 'dhps-mmb-js' );
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>">
<div class="dhps-card">

	<?php if ( ! empty( $search_config['has_search'] ) ) : ?>
	<section class="dhps-mmb-search" aria-label="<?php echo esc_attr( 'Merkblatt-Suche' ); ?>">
		<form class="dhps-mmb-search__form" role="search" data-dhps-mmb-search>
			<div class="dhps-mmb-search__field dhps-mmb-search__field--grow">
				<label class="dhps-mmb-search__label screen-reader-text" for="dhps-mmb-suchbegriff-card-<?php echo esc_attr( $service_tag ); ?>">
					<?php echo esc_html( 'Suchbegriff' ); ?>
				</label>
				<input type="search"
					   class="dhps-mmb-search__input"
					   id="dhps-mmb-suchbegriff-card-<?php echo esc_attr( $service_tag ); ?>"
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
		</form>
	</section>
	<?php endif; ?>

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

	<div class="dhps-mmb-results" data-dhps-mmb-results hidden></div>

	<?php if ( ! empty( $categories ) ) : ?>
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
		<div class="dhps-mmb-category" data-dhps-mmb-category data-category="<?php echo esc_attr( $category['id'] ); ?>">
			<h3 class="dhps-mmb-category__header">
				<button type="button"
						class="dhps-mmb-category__trigger"
						aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>"
						aria-controls="dhps-mmb-card-<?php echo $cat_id; ?>"
						data-dhps-mmb-category-toggle>
					<span class="dhps-mmb-category__icon" aria-hidden="true">
						<?php echo esc_html( DHPS_MMB_Parser::get_category_icon( $category['icon_slug'] ) ); ?>
					</span>
					<span class="dhps-mmb-category__name">
						<?php echo esc_html( $category['name'] ); ?>
					</span>
					<span class="dhps-mmb-category__count">(<?php echo esc_html( $cat_count ); ?>)</span>
					<svg class="dhps-mmb-category__chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<polyline points="6 9 12 15 18 9"/>
					</svg>
				</button>
			</h3>

			<div class="dhps-mmb-category__content"
				 id="dhps-mmb-card-<?php echo $cat_id; ?>"
				 role="region"
				 <?php echo $is_first ? '' : 'aria-hidden="true"'; ?>>

				<?php if ( ! empty( $category['fact_sheets'] ) ) : ?>
				<div class="dhps-mmb-card-grid">
					<?php foreach ( $category['fact_sheets'] as $sheet ) :
						$sheet_id = esc_attr( $sheet['id'] );
					?>
					<div class="dhps-mmb-card-item">
						<div class="dhps-mmb-card-item__icon" aria-hidden="true">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c0392b" stroke-width="2">
								<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
								<polyline points="14 2 14 8 20 8"/>
								<line x1="16" y1="13" x2="8" y2="13"/>
								<line x1="16" y1="17" x2="8" y2="17"/>
								<polyline points="10 9 9 9 8 9"/>
							</svg>
						</div>
						<h4 class="dhps-mmb-card-item__title"><?php echo esc_html( $sheet['title'] ); ?></h4>
						<?php if ( ! empty( $sheet['description'] ) ) : ?>
						<p class="dhps-mmb-card-item__desc"><?php echo esc_html( mb_strimwidth( $sheet['description'], 0, 120, '...' ) ); ?></p>
						<?php endif; ?>
						<a class="dhps-mmb-card-item__download"
						   href="<?php echo esc_url( admin_url( 'admin-ajax.php' ) . '?' . http_build_query( array_merge(
							   array( 'action' => 'dhps_mmb_pdf', 'nonce' => wp_create_nonce( 'dhps_mmb_nonce' ) ),
							   $sheet['pdf_params']
						   ) ) ); ?>"
						   target="_blank" rel="noopener">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
								<polyline points="7 10 12 15 17 10"/>
								<line x1="12" y1="15" x2="12" y2="3"/>
							</svg>
							PDF herunterladen
						</a>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

			</div>
		</div>
		<?php endforeach; ?>

	</section>
	<?php endif; ?>

</div>
</div>
