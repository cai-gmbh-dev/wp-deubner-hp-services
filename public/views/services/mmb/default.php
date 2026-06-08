<?php
/**
 * Service-Template: MMB Standard-Layout (Lazy-Akkordeon, seit 0.14.0).
 *
 * Rendert MMB-/MIL-Kategorien als Lazy-Akkordeon. Initial werden nur die
 * Kategorie-Header + Counts geladen (~5-10 KB). Die Fact-Sheets einer
 * Kategorie werden beim ersten Open per AJAX nachgeladen
 * (Endpoint: action=dhps_mmb_category_load).
 *
 * SEO-Schutz: Innerhalb eines <noscript>-Blocks wird die vollstaendige
 * Fact-Sheet-Liste fuer Crawler ohne JS gerendert (kein Verlust an
 * indexierbarem Content).
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
 * @since      0.14.0 Lazy-Akkordeon + AJAX-on-Demand + noscript-Fallback.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// v0.18.0: Pipeline garantiert eine DHPS_Content_Collection (kein else-Branch
// mehr). $collection ist NIE null - bei Adapter-Drift liefert die Pipeline eine
// leere Collection. Helper rebuilded die Legacy-`$categories`-Shape, das
// Lazy-Akkordeon-Markup ist bytewise unveraendert zum v0.17.1-Pfad.
$collection    = dhps_collection_or_empty( $collection, 'mmb' );
$categories    = dhps_mmb_collection_to_legacy_categories( $collection );
$search_config = (array) $collection->get_meta( 'search_config', array() );

$service_tag    = $data['service_tag'] ?? 'mmb';
$download_label = ( 'mil' === $service_tag ) ? 'Infografik herunterladen' : 'PDF herunterladen';
$is_mil         = ( 'mil' === $service_tag );

/**
 * Filter: erlaubt das initiale Vorrendern der ersten Kategorie (Above-the-fold).
 *
 * Default: false (maximale Initial-Bytes-Ersparnis). Sites mit weniger
 * Performance-Druck koennen dies via Filter aktivieren.
 *
 * @since 0.14.0
 *
 * @param bool $pre_render_first true wenn die erste Kategorie initial gerendert werden soll.
 */
$pre_render_first = (bool) apply_filters( 'dhps_mmb_default_prerender_first_category', false );

wp_enqueue_script( 'dhps-mmb-js' );

// Partial-Pfad fuer noscript-Fallback und Pre-Render (Pfadberechnung einmalig).
$partial_path = trailingslashit( DEUBNER_HP_SERVICES_PATH )
	. 'public/views/services/mmb/partials/category-content.php';
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

	<?php if ( count( $categories ) > 1 ) : ?>
	<!-- Kategorie-Filter (Feature-Parity mit compact/card-Layout, seit 0.13.1). -->
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
	<!-- Rubriken-Accordion (Lazy-Loading, seit 0.14.0) -->
	<section class="dhps-mmb-categories"
			 aria-label="<?php echo esc_attr( 'Merkblatt-Kategorien' ); ?>"
			 data-dhps-mmb-categories
			 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_mmb_nonce' ) ); ?>"
			 data-service-tag="<?php echo esc_attr( $service_tag ); ?>">

		<?php
		foreach ( $categories as $index => $category ) :
			$cat_id    = esc_attr( $category['id'] );
			$cat_count = isset( $category['fact_sheets'] ) && is_array( $category['fact_sheets'] )
				? count( $category['fact_sheets'] )
				: 0;
			$is_first         = ( 0 === $index );
			$pre_rendered     = ( $is_first && $pre_render_first );
			$initial_state    = $pre_rendered ? 'loaded' : 'pending';
			$initial_expanded = $pre_rendered ? 'true' : 'false';
			$initial_hidden   = $pre_rendered ? 'false' : 'true';
		?>
		<div class="dhps-mmb-category dhps-mmb-category--lazy"
			 data-dhps-mmb-category
			 data-category="<?php echo $cat_id; ?>"
			 data-dhps-mmb-lazy-state="<?php echo esc_attr( $initial_state ); ?>">

			<h3 class="dhps-mmb-category__header">
				<button type="button"
						class="dhps-mmb-category__trigger"
						aria-expanded="<?php echo esc_attr( $initial_expanded ); ?>"
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
				 aria-hidden="<?php echo esc_attr( $initial_hidden ); ?>">

				<?php if ( $pre_rendered ) : ?>
					<?php
					// Optional vorgerenderte erste Kategorie (Filter dhps_mmb_default_prerender_first_category).
					if ( file_exists( $partial_path ) ) {
						include $partial_path;
					}
					?>
				<?php else : ?>
					<?php
					// Skeleton-Loader-Slot, sichtbar nur waehrend state="loading"
					// (CSS-State-Machine in dhps-frontend.css steuert Sichtbarkeit).
					echo dhps_component( 'skeleton-loader', array(
						'type'  => 'list',
						'count' => min( max( $cat_count, 1 ), 5 ),
					) );
					?>
				<?php endif; ?>

			</div>
		</div>
		<?php endforeach; ?>

	</section>

	<!-- noscript-Fallback: volle Liste fuer Crawler / JS-Disabled (SEO-Schutz). -->
	<noscript>
		<section class="dhps-mmb-categories dhps-mmb-categories--noscript"
				 aria-label="<?php echo esc_attr( 'Merkblatt-Kategorien (ohne JavaScript)' ); ?>">
			<?php foreach ( $categories as $category ) :
				$cat_id_ns = esc_attr( $category['id'] );
			?>
			<div class="dhps-mmb-category" data-category="<?php echo $cat_id_ns; ?>">
				<h3 class="dhps-mmb-category__header">
					<span class="dhps-mmb-category__icon" aria-hidden="true">
						<?php echo esc_html( DHPS_MMB_Parser::get_category_icon( $category['icon_slug'] ) ); ?>
					</span>
					<span class="dhps-mmb-category__name">
						<?php echo esc_html( $category['name'] ); ?>
					</span>
				</h3>
				<div class="dhps-mmb-category__content" role="region">
					<?php
					if ( file_exists( $partial_path ) ) {
						include $partial_path;
					}
					?>
				</div>
			</div>
			<?php endforeach; ?>
		</section>
	</noscript>
	<?php endif; ?>

</div>
