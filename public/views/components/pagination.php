<?php
/**
 * Component: Pagination (stateful, Alpine.js)
 *
 * Drei Modi:
 *   - 'load-more': einzelner Button, dispatcht 'dhps:items-loaded'.
 *   - 'numeric':   Prev/Next + Seitenzahlen, aria-current="page" am aktiven Item.
 *   - 'infinite':  IntersectionObserver auf Sentinel triggert loadMore() automatisch.
 *
 * Props (per extract() im Registry-Renderer):
 *
 *   string      $mode          'load-more' | 'numeric' | 'infinite'  default 'load-more'
 *   int         $current_page  default 1
 *   int         $total_pages   default 1
 *   bool        $has_more      default true   (nur load-more / infinite)
 *   int         $page_size     default 10
 *   string|null $ajax_url      WP-Ajax URL (admin-ajax.php)          default null
 *   string|null $ajax_action   WP-Ajax Action-Name                   default null
 *   string|null $ajax_nonce    Nonce-String                          default null
 *   string      $label_more    default "Weitere laden"
 *   string      $label_page    default "Seite"
 *   string      $label_prev    default "Zurueck"
 *   string      $label_next    default "Weiter"
 *   string      $class         default ''
 *
 * A11y:
 *   - <nav aria-label="Seitennavigation"> umschliesst numeric/load-more.
 *   - aria-current="page" am aktiven Seitenlink.
 *   - aria-live="polite" Status fuer Loading/Error/Anzahl.
 *
 * @package Deubner Homepage-Service
 * @since   0.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- Prop-Normalisierung ----
$mode         = isset( $mode ) && is_string( $mode ) ? $mode : 'load-more';
$allowed_m    = array( 'load-more', 'numeric', 'infinite' );
if ( ! in_array( $mode, $allowed_m, true ) ) {
	$mode = 'load-more';
}
$current_page = isset( $current_page ) ? max( 1, (int) $current_page ) : 1;
$total_pages  = isset( $total_pages )  ? max( 1, (int) $total_pages )  : 1;
$has_more     = isset( $has_more ) ? (bool) $has_more : ( $current_page < $total_pages );
$page_size    = isset( $page_size ) ? max( 1, (int) $page_size ) : 10;
$ajax_url     = isset( $ajax_url )    && is_string( $ajax_url )    && '' !== $ajax_url    ? $ajax_url    : null;
$ajax_action  = isset( $ajax_action ) && is_string( $ajax_action ) && '' !== $ajax_action ? $ajax_action : null;
$ajax_nonce   = isset( $ajax_nonce )  && is_string( $ajax_nonce )  && '' !== $ajax_nonce  ? $ajax_nonce  : null;
$label_more   = isset( $label_more ) && is_string( $label_more ) ? $label_more : __( 'Weitere laden', 'wp-deubner-hp-services' );
$label_page   = isset( $label_page ) && is_string( $label_page ) ? $label_page : __( 'Seite', 'wp-deubner-hp-services' );
$label_prev   = isset( $label_prev ) && is_string( $label_prev ) ? $label_prev : __( 'Zurueck', 'wp-deubner-hp-services' );
$label_next   = isset( $label_next ) && is_string( $label_next ) ? $label_next : __( 'Weiter', 'wp-deubner-hp-services' );
$class        = isset( $class ) && is_string( $class ) ? $class : '';

// Alpine-Config als JSON.
$alpine_config = array(
	'mode'        => $mode,
	'currentPage' => $current_page,
	'totalPages'  => $total_pages,
	'hasMore'     => $has_more,
	'pageSize'    => $page_size,
	'ajaxUrl'     => $ajax_url,
	'ajaxAction'  => $ajax_action,
	'ajaxNonce'   => $ajax_nonce,
);
$alpine_json   = wp_json_encode( $alpine_config );
if ( false === $alpine_json ) {
	$alpine_json = '{}';
}

$root_classes = 'dhps-pagination dhps-pagination--' . $mode;
if ( '' !== $class ) {
	$root_classes .= ' ' . $class;
}

// Helper: bei numeric-Mode eine kompakte Seitenliste berechnen (1..total mit Ellipsis).
$page_window = array();
if ( 'numeric' === $mode ) {
	$max_visible = 7;
	if ( $total_pages <= $max_visible ) {
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			$page_window[] = $i;
		}
	} else {
		// Always: 1, ..., current-1, current, current+1, ..., last
		$page_window[] = 1;
		$start         = max( 2, $current_page - 1 );
		$end           = min( $total_pages - 1, $current_page + 1 );
		if ( $start > 2 ) {
			$page_window[] = 'ellipsis-start';
		}
		for ( $i = $start; $i <= $end; $i++ ) {
			$page_window[] = $i;
		}
		if ( $end < $total_pages - 1 ) {
			$page_window[] = 'ellipsis-end';
		}
		$page_window[] = $total_pages;
	}
}
?>
<nav
	class="<?php echo esc_attr( $root_classes ); ?>"
	x-data="dhpsPagination(<?php echo esc_attr( $alpine_json ); ?>)"
	x-cloak
	aria-label="<?php echo esc_attr__( 'Seitennavigation', 'wp-deubner-hp-services' ); ?>"
>
	<?php if ( 'load-more' === $mode ) : ?>
		<button
			type="button"
			class="dhps-pagination__load-more"
			x-show="hasMore"
			:disabled="loading"
			:aria-busy="loading ? 'true' : 'false'"
			x-on:click="loadMore()"
		>
			<span x-show="!loading"><?php echo esc_html( $label_more ); ?></span>
			<span x-show="loading" x-cloak><?php esc_html_e( 'Wird geladen...', 'wp-deubner-hp-services' ); ?></span>
		</button>

	<?php elseif ( 'numeric' === $mode ) : ?>
		<ul class="dhps-pagination__list">
			<li class="dhps-pagination__item dhps-pagination__item--prev">
				<button
					type="button"
					class="dhps-pagination__btn dhps-pagination__btn--prev"
					:disabled="currentPage <= 1 || loading"
					x-on:click="goToPage(currentPage - 1)"
					aria-label="<?php echo esc_attr( $label_prev ); ?>"
				>
					<?php echo esc_html( $label_prev ); ?>
				</button>
			</li>

			<?php foreach ( $page_window as $p ) :
				if ( 'ellipsis-start' === $p || 'ellipsis-end' === $p ) :
					?>
					<li class="dhps-pagination__item dhps-pagination__item--ellipsis" aria-hidden="true">
						<span>&hellip;</span>
					</li>
					<?php
					continue;
				endif;
				$is_current = ( (int) $p === $current_page );
				?>
				<li class="dhps-pagination__item">
					<button
						type="button"
						class="dhps-pagination__btn dhps-pagination__btn--page<?php echo $is_current ? ' is-current' : ''; ?>"
						x-on:click="goToPage(<?php echo (int) $p; ?>)"
						:aria-current="currentPage === <?php echo (int) $p; ?> ? 'page' : null"
						<?php if ( $is_current ) : ?>aria-current="page"<?php endif; ?>
						aria-label="<?php echo esc_attr( $label_page . ' ' . (int) $p ); ?>"
					>
						<?php echo (int) $p; ?>
					</button>
				</li>
			<?php endforeach; ?>

			<li class="dhps-pagination__item dhps-pagination__item--next">
				<button
					type="button"
					class="dhps-pagination__btn dhps-pagination__btn--next"
					:disabled="currentPage >= totalPages || loading"
					x-on:click="goToPage(currentPage + 1)"
					aria-label="<?php echo esc_attr( $label_next ); ?>"
				>
					<?php echo esc_html( $label_next ); ?>
				</button>
			</li>
		</ul>

	<?php else : /* infinite */ ?>
		<div
			class="dhps-pagination__sentinel"
			x-ref="sentinel"
			x-init="initInfinite()"
			aria-hidden="true"
		></div>
		<div class="dhps-pagination__infinite-status" x-show="loading" x-cloak>
			<?php esc_html_e( 'Wird geladen...', 'wp-deubner-hp-services' ); ?>
		</div>
	<?php endif; ?>

	<div
		class="dhps-pagination__status"
		role="status"
		aria-live="polite"
		x-text="statusText"
	></div>

	<div
		class="dhps-pagination__error"
		x-show="error"
		x-cloak
		role="alert"
		x-text="error"
	></div>
</nav>
