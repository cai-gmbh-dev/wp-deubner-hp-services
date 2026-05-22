<?php
/**
 * Component: LazyImage
 *
 * Native loading=lazy + optionaler LQIP-Blurup.
 * Stateless. Kein Alpine. Intersection-Observer-Hook
 * via data-dhps-lazy-image attribute (optional zu enhancen).
 *
 * Props (per extract() im Registry-Renderer):
 *
 *   string      $src     finale Bild-URL (Pflicht; wenn leer -> nichts ausgeben)
 *   string      $alt     Alt-Text (Pflicht; bei leerem String wird alt="" gerendert
 *                        was als "dekorativ" interpretiert wird)
 *   int|null    $width   optional, fuer CLS
 *   int|null    $height  optional, fuer CLS
 *   string|null $lqip    Low-Quality-Image-Placeholder als Data-URL
 *   string      $class   Zusaetzliche CSS-Klassen   default ''
 *
 * @package Deubner Homepage-Service
 * @since   0.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- Prop-Normalisierung ----
$src    = isset( $src ) && is_string( $src ) ? trim( $src ) : '';
$alt    = isset( $alt ) && is_string( $alt ) ? $alt : '';
$width  = isset( $width ) ? (int) $width : 0;
$height = isset( $height ) ? (int) $height : 0;
$lqip   = isset( $lqip ) && is_string( $lqip ) && '' !== $lqip ? $lqip : null;
$class  = isset( $class ) && is_string( $class ) ? $class : '';

// Validierung: leerer src -> nichts ausgeben.
if ( '' === $src ) {
	return;
}

$root_classes = trim( 'dhps-lazy-image ' . $class );

// LQIP-Inline-Style nur wenn vorhanden.
$style_attr = '';
if ( null !== $lqip ) {
	// esc_url() fuer Data-URI: WP akzeptiert "data:" als zulaessiges Protokoll
	// in esc_url_raw, nicht aber in esc_url - wir filtern stattdessen via esc_attr.
	$style_attr = 'background-image: url(\'' . esc_attr( $lqip ) . '\');';
}
?>
<picture class="<?php echo esc_attr( $root_classes ); ?>" data-dhps-lazy-image>
	<img
		loading="lazy"
		decoding="async"
		src="<?php echo esc_url( $src ); ?>"
		alt="<?php echo esc_attr( $alt ); ?>"
		<?php if ( $width > 0 ) : ?>width="<?php echo (int) $width; ?>"<?php endif; ?>
		<?php if ( $height > 0 ) : ?>height="<?php echo (int) $height; ?>"<?php endif; ?>
		data-src="<?php echo esc_url( $src ); ?>"
		<?php if ( '' !== $style_attr ) : ?>style="<?php echo esc_attr( $style_attr ); ?>"<?php endif; ?>
	>
</picture>
