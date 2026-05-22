<?php
/**
 * Component: SkeletonLoader
 *
 * Stateless CSS-only Shimmer-Placeholder.
 * Wird waehrend AJAX-Hydration oder pre-render Phase angezeigt.
 *
 * Props (per extract() im Registry-Renderer):
 *
 *   string $type   'card' | 'list' | 'video' | 'accordion'   default 'card'
 *   int    $count  Anzahl Skeleton-Items                     default 3, max 20
 *   string $class  Zusaetzliche CSS-Klassen                  default ''
 *
 * Output: stateless HTML. Kein Alpine, kein Inline-JS.
 *
 * @package Deubner Homepage-Service
 * @since   0.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- Prop-Normalisierung ----
$type  = isset( $type ) && is_string( $type ) ? $type : 'card';
$count = isset( $count ) ? (int) $count : 3;
$class = isset( $class ) && is_string( $class ) ? $class : '';

$allowed_types = array( 'card', 'list', 'video', 'accordion' );
if ( ! in_array( $type, $allowed_types, true ) ) {
	$type = 'card';
}

// Cap auf max 20, min 1
if ( $count < 1 ) {
	$count = 1;
}
if ( $count > 20 ) {
	$count = 20;
}

$root_classes = trim( 'dhps-skeleton dhps-skeleton--' . $type . ' ' . $class );
?>
<div class="<?php echo esc_attr( $root_classes ); ?>" aria-busy="true" aria-live="polite">
	<span class="screen-reader-text"><?php echo esc_html__( 'Inhalt wird geladen ...', 'deubner-hp-services' ); ?></span>

	<?php for ( $i = 0; $i < $count; $i++ ) : ?>
		<div class="dhps-skeleton__item dhps-skeleton__item--<?php echo esc_attr( $type ); ?>" aria-hidden="true">
			<?php if ( 'card' === $type ) : ?>
				<div class="dhps-skeleton__media"></div>
				<div class="dhps-skeleton__title"></div>
				<div class="dhps-skeleton__line dhps-skeleton__line--full"></div>
				<div class="dhps-skeleton__line dhps-skeleton__line--80"></div>
			<?php elseif ( 'list' === $type ) : ?>
				<div class="dhps-skeleton__icon"></div>
				<div class="dhps-skeleton__list-body">
					<div class="dhps-skeleton__title"></div>
					<div class="dhps-skeleton__meta"></div>
				</div>
			<?php elseif ( 'video' === $type ) : ?>
				<div class="dhps-skeleton__poster">
					<div class="dhps-skeleton__duration"></div>
				</div>
				<div class="dhps-skeleton__title"></div>
			<?php elseif ( 'accordion' === $type ) : ?>
				<div class="dhps-skeleton__trigger"></div>
			<?php endif; ?>
		</div>
	<?php endfor; ?>
</div>
