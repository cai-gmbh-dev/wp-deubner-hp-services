<?php
/**
 * Component: ContentList (stateful, Alpine.js)
 *
 * Container, der eine Liste von ContentCards rendert und optional
 * FilterBar (oben) und Pagination (unten) komponiert. Lauscht auf
 * Custom-Events 'dhps:filter-changed' und 'dhps:items-loaded' und
 * passt sichtbare Items entsprechend an.
 *
 * Props (per extract() im Registry-Renderer):
 *
 *   string      $id           Unique-ID (Alpine-Scope, ARIA)            Pflicht
 *   string      $layout       'grid' | 'list' | 'masonry'    default 'grid'
 *   int         $columns      1|2|3|4                        default 2
 *   bool        $filterable   default false (nur Info, FilterBar wird ueber $filter_bar geliefert)
 *   bool        $searchable   default false
 *   bool        $sortable     default false
 *   array       $items        Liste von Card-Props (jeweils Array)
 *   string      $item_type    'news'|'video'|'document' fuer alle Karten default 'news'
 *   array|null  $empty_state  Props fuer empty-state component wenn 0 Items
 *   array|null  $pagination   Props fuer pagination component
 *   array|null  $filter_bar   Props fuer filter-bar component
 *   string      $class        Zusaetzliche CSS-Klassen
 *
 * A11y:
 *   - role="region" + aria-labelledby (wenn Heading verfuegbar).
 *   - aria-live region in FilterBar (separater Component).
 *   - data-dhps-list-item Marker an jeder Karte fuer JS-Hide/Show.
 *
 * @package Deubner Homepage-Service
 * @since   0.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- Prop-Normalisierung ----
$id          = isset( $id ) && is_string( $id ) && '' !== $id ? sanitize_html_class( $id ) : 'dhps-list-' . wp_unique_id();
$layout      = isset( $layout ) && is_string( $layout ) ? $layout : 'grid';
$allowed_l   = array( 'grid', 'list', 'masonry' );
if ( ! in_array( $layout, $allowed_l, true ) ) {
	$layout = 'grid';
}
$columns     = isset( $columns ) ? (int) $columns : 2;
if ( $columns < 1 || $columns > 4 ) {
	$columns = 2;
}
$filterable  = isset( $filterable ) ? (bool) $filterable : false;
$searchable  = isset( $searchable ) ? (bool) $searchable : false;
$sortable    = isset( $sortable ) ? (bool) $sortable : false;
$items       = isset( $items ) && is_array( $items ) ? $items : array();
$item_type   = isset( $item_type ) && is_string( $item_type ) ? $item_type : 'news';
$empty_state = isset( $empty_state ) && is_array( $empty_state ) ? $empty_state : null;
$pagination  = isset( $pagination ) && is_array( $pagination ) ? $pagination : null;
$filter_bar  = isset( $filter_bar ) && is_array( $filter_bar ) ? $filter_bar : null;
$class       = isset( $class ) && is_string( $class ) ? $class : '';

// Sonderfall: keine Items + EmptyState konfiguriert -> nur EmptyState rendern.
if ( empty( $items ) && null !== $empty_state ) {
	if ( function_exists( 'dhps_component' ) ) {
		echo dhps_component( 'empty-state', $empty_state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
	}
	return;
}

// Alpine-Config.
$alpine_config = array(
	'id'         => $id,
	'layout'     => $layout,
	'columns'    => $columns,
	'filterable' => $filterable,
	'searchable' => $searchable,
	'sortable'   => $sortable,
	'itemType'   => $item_type,
);
$alpine_json   = wp_json_encode( $alpine_config );
if ( false === $alpine_json ) {
	$alpine_json = '{}';
}

$root_classes  = 'dhps-content-list';
$root_classes .= ' dhps-content-list--' . $layout;
$root_classes .= ' dhps-content-list--cols-' . $columns;
if ( '' !== $class ) {
	$root_classes .= ' ' . $class;
}

$label_id = $id . '-label';
?>
<div
	class="<?php echo esc_attr( $root_classes ); ?>"
	id="<?php echo esc_attr( $id ); ?>"
	x-data="dhpsContentList(<?php echo esc_attr( $alpine_json ); ?>)"
	x-cloak
	role="region"
	aria-labelledby="<?php echo esc_attr( $label_id ); ?>"
	style="--cols: <?php echo (int) $columns; ?>;"
>
	<span id="<?php echo esc_attr( $label_id ); ?>" class="screen-reader-text">
		<?php
		/* translators: %s: Inhalts-Liste / Layout. */
		printf( esc_html__( 'Inhaltsliste (%s)', 'wp-deubner-hp-services' ), esc_html( $layout ) );
		?>
	</span>

	<?php if ( null !== $filter_bar && function_exists( 'dhps_component' ) ) : ?>
		<div class="dhps-content-list__toolbar">
			<?php
			// Target-Selector setzen, wenn nicht explizit gegeben.
			if ( ! isset( $filter_bar['target'] ) || '' === $filter_bar['target'] ) {
				$filter_bar['target'] = '[data-dhps-list="' . $id . '"]';
			}
			echo dhps_component( 'filter-bar', $filter_bar ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
			?>
		</div>
	<?php endif; ?>

	<div
		class="dhps-content-list__container"
		data-dhps-list="<?php echo esc_attr( $id ); ?>"
		:data-visible-count="visibleCount"
	>
		<?php
		if ( empty( $items ) ) {
			// Fallback Inline-Empty wenn nichts konfiguriert wurde.
			?>
			<p class="dhps-content-list__empty"><?php esc_html_e( 'Keine Eintraege vorhanden.', 'wp-deubner-hp-services' ); ?></p>
			<?php
		} else {
			foreach ( $items as $item ) :
				if ( ! is_array( $item ) ) {
					continue;
				}
				// Item-Typ aus Liste oder Default.
				$card_props = array_merge(
					array( 'type' => $item_type ),
					$item
				);
				// Wrapper-Klasse, damit ContentList-JS schnell selektieren kann.
				$card_props['class'] = isset( $card_props['class'] ) && is_string( $card_props['class'] )
					? trim( $card_props['class'] . ' dhps-content-list__item' )
					: 'dhps-content-list__item';
				if ( function_exists( 'dhps_component' ) ) {
					// Verpacke jede Karte in einen data-dhps-list-item-Marker (fuer Filter-JS).
					?>
					<div class="dhps-content-list__item-wrap" data-dhps-list-item>
						<?php echo dhps_component( 'content-card', $card_props ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML. ?>
					</div>
					<?php
				}
			endforeach;
		}
		?>
	</div>

	<?php if ( null !== $pagination && function_exists( 'dhps_component' ) ) : ?>
		<div class="dhps-content-list__pagination">
			<?php echo dhps_component( 'pagination', $pagination ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML. ?>
		</div>
	<?php endif; ?>
</div>
