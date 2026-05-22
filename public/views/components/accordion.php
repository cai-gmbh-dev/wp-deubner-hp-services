<?php
/**
 * Component: Accordion (CSS-only)
 *
 * Wrapper fuer FAQ-aehnliche Inhalte mittels nativem <details>/<summary>.
 * A11y-konform, keyboard-zugaenglich, ohne JS.
 *
 * "Exclusive" Mode (multi=false) wird via name-Attribut auf <details>
 * realisiert (Open-State eines Items schliesst die anderen automatisch -
 * HTML Living Standard).
 *
 * Props (per extract() im Registry-Renderer):
 *
 *   string $id     Unique ID fuer ARIA-Referenzen (Pflicht; Default 'dhps-accordion')
 *   array  $items  Liste von Items, je
 *                  [ 'title' => string, 'content_html' => string, 'open' => bool ]
 *   bool   $multi  mehrere gleichzeitig offen?  default false
 *   string $class  Zusaetzliche CSS-Klassen     default ''
 *
 * Content-HTML wird durch wp_kses_post gefiltert.
 *
 * @package Deubner Homepage-Service
 * @since   0.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- Prop-Normalisierung ----
$id    = isset( $id ) && is_string( $id ) && '' !== $id ? sanitize_html_class( $id ) : 'dhps-accordion-' . wp_unique_id();
$items = isset( $items ) && is_array( $items ) ? $items : array();
$multi = isset( $multi ) ? (bool) $multi : false;
$class = isset( $class ) && is_string( $class ) ? $class : '';

if ( empty( $items ) ) {
	return;
}

$root_classes = trim( 'dhps-accordion ' . $class );

// Exclusive group name (nur wenn !multi). Ein gemeinsamer Name -> nur eines offen.
$group_name = ! $multi ? $id . '-group' : '';

// Chevron-SVG (inline, klein).
$chevron_svg = '<svg class="dhps-accordion__chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg>';
?>
<div class="<?php echo esc_attr( $root_classes ); ?>" id="<?php echo esc_attr( $id ); ?>">
	<?php
	foreach ( $items as $i => $item ) :
		if ( ! is_array( $item ) ) {
			continue;
		}

		$item_title   = isset( $item['title'] ) && is_string( $item['title'] ) ? $item['title'] : '';
		$item_content = isset( $item['content_html'] ) && is_string( $item['content_html'] ) ? $item['content_html'] : '';
		$item_open    = ! empty( $item['open'] );
		$item_id      = $id . '-item-' . (int) $i;

		if ( '' === $item_title && '' === $item_content ) {
			continue;
		}
		?>
		<details
			class="dhps-accordion__item"
			id="<?php echo esc_attr( $item_id ); ?>"
			<?php if ( $group_name ) : ?>name="<?php echo esc_attr( $group_name ); ?>"<?php endif; ?>
			<?php echo $item_open ? 'open' : ''; ?>
		>
			<summary class="dhps-accordion__trigger">
				<span class="dhps-accordion__title"><?php echo esc_html( $item_title ); ?></span>
				<?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Statisches Inline-SVG. ?>
			</summary>
			<div class="dhps-accordion__body">
				<?php echo wp_kses_post( $item_content ); ?>
			</div>
		</details>
		<?php
	endforeach;
	?>
</div>
