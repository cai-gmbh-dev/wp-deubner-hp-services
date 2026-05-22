<?php
/**
 * Component: FilterBar (stateful, Alpine.js)
 *
 * Kombiniert Search-Input, Tag-Chips (Multi-Select) und Sort-Dropdown.
 * Emittiert das CustomEvent `dhps:filter-changed` an $root (= naechster
 * Container nach oben), das von einer ContentList aufgefangen werden kann.
 *
 * Props (per extract() im Registry-Renderer):
 *
 *   string $target              CSS-Selector des zu filternden Containers
 *                               (z.B. "[data-dhps-list='mmb-items']").
 *                               Wird in Alpine-Config gegeben - dient als
 *                               Hint fuer ContentList-Bindung. default ''
 *   string $search_placeholder  default "Suchen..."
 *   array  $tags                [ ['id'=>slug, 'label'=>string, 'count'=>int|null] ]
 *   array  $sorts               [ ['id'=>slug, 'label'=>string, 'default'=>bool] ]
 *   int    $debounce_ms         default 300
 *   int    $min_chars           default 2
 *   string $label_search        ARIA-Label / visually-hidden Label  default "Suchen"
 *   string $label_sort          Label fuer Sort-Select              default "Sortierung"
 *   string $label_reset         Reset-Button                        default "Zuruecksetzen"
 *   string $class               Zusaetzliche CSS-Klassen            default ''
 *
 * A11y:
 *   - <label class="screen-reader-text"> fuer Search/Sort.
 *   - Chip-Buttons mit aria-pressed.
 *   - aria-live="polite" Status-Region fuer Treffer-Anzahl.
 *
 * @package Deubner Homepage-Service
 * @since   0.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- Prop-Normalisierung ----
$target             = isset( $target ) && is_string( $target ) ? $target : '';
$search_placeholder = isset( $search_placeholder ) && is_string( $search_placeholder ) ? $search_placeholder : __( 'Suchen...', 'wp-deubner-hp-services' );
$tags               = isset( $tags ) && is_array( $tags ) ? $tags : array();
$sorts              = isset( $sorts ) && is_array( $sorts ) ? $sorts : array();
$debounce_ms        = isset( $debounce_ms ) ? max( 0, (int) $debounce_ms ) : 300;
$min_chars          = isset( $min_chars ) ? max( 0, (int) $min_chars ) : 2;
$label_search       = isset( $label_search ) && is_string( $label_search ) ? $label_search : __( 'Suchen', 'wp-deubner-hp-services' );
$label_sort         = isset( $label_sort ) && is_string( $label_sort ) ? $label_sort : __( 'Sortierung', 'wp-deubner-hp-services' );
$label_reset        = isset( $label_reset ) && is_string( $label_reset ) ? $label_reset : __( 'Zuruecksetzen', 'wp-deubner-hp-services' );
$class              = isset( $class ) && is_string( $class ) ? $class : '';

// Default-Sort bestimmen.
$default_sort = '';
foreach ( $sorts as $s ) {
	if ( is_array( $s ) && ! empty( $s['default'] ) && ! empty( $s['id'] ) ) {
		$default_sort = (string) $s['id'];
		break;
	}
}

// Alpine-Config als JSON (im x-data-Aufruf benutzt).
$alpine_config = array(
	'target'      => $target,
	'minChars'    => $min_chars,
	'debounceMs'  => $debounce_ms,
	'defaultSort' => $default_sort,
);
$alpine_json   = wp_json_encode( $alpine_config );
if ( false === $alpine_json ) {
	$alpine_json = '{}';
}

$root_classes  = 'dhps-filter-bar';
$root_classes .= ! empty( $tags )  ? ' dhps-filter-bar--has-tags'  : '';
$root_classes .= ! empty( $sorts ) ? ' dhps-filter-bar--has-sort'  : '';
if ( '' !== $class ) {
	$root_classes .= ' ' . $class;
}

$instance_id = 'dhps-filter-bar-' . wp_unique_id();

// Search-Icon SVG inline.
$search_icon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
?>
<div
	class="<?php echo esc_attr( $root_classes ); ?>"
	id="<?php echo esc_attr( $instance_id ); ?>"
	x-data="dhpsFilterBar(<?php echo esc_attr( $alpine_json ); ?>)"
	x-cloak
	role="search"
>
	<div class="dhps-filter-bar__row">

		<div class="dhps-filter-bar__search">
			<label class="screen-reader-text" for="<?php echo esc_attr( $instance_id ); ?>-search">
				<?php echo esc_html( $label_search ); ?>
			</label>
			<span class="dhps-filter-bar__search-icon" aria-hidden="true"><?php echo $search_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Statisches SVG. ?></span>
			<input
				id="<?php echo esc_attr( $instance_id ); ?>-search"
				class="dhps-filter-bar__search-input"
				type="search"
				placeholder="<?php echo esc_attr( $search_placeholder ); ?>"
				autocomplete="off"
				x-model.debounce.<?php echo (int) $debounce_ms; ?>ms="query"
				:aria-describedby="query.length > 0 && query.length &lt; minChars ? '<?php echo esc_attr( $instance_id ); ?>-hint' : null"
			>
			<span
				id="<?php echo esc_attr( $instance_id ); ?>-hint"
				class="dhps-filter-bar__hint screen-reader-text"
				x-show="query.length > 0 && query.length < minChars"
				x-cloak
			>
				<?php
				/* translators: %d: minimum number of characters required for the search query. */
				printf( esc_html__( 'Mindestens %d Zeichen eingeben.', 'wp-deubner-hp-services' ), (int) $min_chars );
				?>
			</span>
		</div>

		<?php if ( ! empty( $sorts ) ) : ?>
			<div class="dhps-filter-bar__sort">
				<label class="dhps-filter-bar__sort-label" for="<?php echo esc_attr( $instance_id ); ?>-sort">
					<?php echo esc_html( $label_sort ); ?>
				</label>
				<select
					id="<?php echo esc_attr( $instance_id ); ?>-sort"
					class="dhps-filter-bar__sort-select"
					x-model="sort"
				>
					<?php foreach ( $sorts as $s ) :
						if ( ! is_array( $s ) || empty( $s['id'] ) || empty( $s['label'] ) ) {
							continue;
						}
						?>
						<option value="<?php echo esc_attr( (string) $s['id'] ); ?>">
							<?php echo esc_html( (string) $s['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<button
			type="button"
			class="dhps-filter-bar__reset"
			x-on:click="reset()"
			x-show="hasActiveFilters()"
			x-cloak
		>
			<?php echo esc_html( $label_reset ); ?>
		</button>
	</div>

	<?php if ( ! empty( $tags ) ) : ?>
		<ul class="dhps-filter-bar__chips" role="group" aria-label="<?php echo esc_attr__( 'Filter-Kategorien', 'wp-deubner-hp-services' ); ?>">
			<?php foreach ( $tags as $t ) :
				if ( ! is_array( $t ) || empty( $t['id'] ) || empty( $t['label'] ) ) {
					continue;
				}
				$t_id    = (string) $t['id'];
				$t_label = (string) $t['label'];
				$t_count = isset( $t['count'] ) && null !== $t['count'] ? (int) $t['count'] : null;
				?>
				<li class="dhps-filter-bar__chips-item">
					<button
						type="button"
						class="dhps-filter-bar__chip"
						:class="isTagActive('<?php echo esc_js( $t_id ); ?>') ? 'is-active' : ''"
						x-on:click="toggleTag('<?php echo esc_js( $t_id ); ?>')"
						:aria-pressed="isTagActive('<?php echo esc_js( $t_id ); ?>') ? 'true' : 'false'"
					>
						<span class="dhps-filter-bar__chip-label"><?php echo esc_html( $t_label ); ?></span>
						<?php if ( null !== $t_count ) : ?>
							<span class="dhps-filter-bar__chip-count" aria-hidden="true"><?php echo (int) $t_count; ?></span>
						<?php endif; ?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<div
		class="dhps-filter-bar__status"
		role="status"
		aria-live="polite"
		x-text="statusText"
	></div>
</div>
