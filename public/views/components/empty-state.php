<?php
/**
 * Component: EmptyState
 *
 * Konsistenter Leerzustand bei 0 Treffern, fehlender Lizenz oder Demo-Modus.
 * Stateless, rein HTML/CSS.
 *
 * Props (per extract() im Registry-Renderer):
 *
 *   string      $icon         SVG-Snippet ODER Slug:
 *                             'calculator' | 'document' | 'video' | 'inbox'
 *                             default 'inbox'
 *   string      $title        Hauptueberschrift                 default ''
 *   string      $hint         Zusatztext (optional, kann leer)  default ''
 *   string|null $action_label Button-Text                       default null
 *   string|null $action_url   URL fuer Action-Button            default null
 *   string      $class        Zusaetzliche CSS-Klassen          default ''
 *
 * @package Deubner Homepage-Service
 * @since   0.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- Prop-Normalisierung ----
$icon         = isset( $icon ) && is_string( $icon ) && '' !== $icon ? $icon : 'inbox';
$title        = isset( $title ) && is_string( $title ) ? $title : '';
$hint         = isset( $hint ) && is_string( $hint ) ? $hint : '';
$action_label = isset( $action_label ) && is_string( $action_label ) ? $action_label : null;
$action_url   = isset( $action_url ) && is_string( $action_url ) ? $action_url : null;
$class        = isset( $class ) && is_string( $class ) ? $class : '';

$root_classes = trim( 'dhps-empty-state ' . $class );

/*
 * Icon-Aufloesung:
 *   Wenn der Wert wie ein Slug aussieht (alphanumerisch, kein "<"),
 *   wird das interne SVG aus der Mapping-Tabelle verwendet.
 *   Andernfalls wird der String als (kontrolliertes) SVG-/HTML-Snippet
 *   gewertet und durch wp_kses_post gereinigt.
 */
$icon_map = array(
	'inbox'      => '<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/></svg>',
	'calculator' => '<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="8.01" y2="10"/><line x1="12" y1="10" x2="12.01" y2="10"/><line x1="16" y1="10" x2="16.01" y2="10"/><line x1="8" y1="14" x2="8.01" y2="14"/><line x1="12" y1="14" x2="12.01" y2="14"/><line x1="16" y1="14" x2="16.01" y2="14"/><line x1="8" y1="18" x2="12" y2="18"/></svg>',
	'document'   => '<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>',
	'video'      => '<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>',
);

$icon_html = '';
if ( isset( $icon_map[ $icon ] ) ) {
	// Internes SVG - vertrauenswuerdige Quelle.
	$icon_html = $icon_map[ $icon ];
} else {
	// Externer SVG-/HTML-String - durch wp_kses_post filtern.
	// (wp_kses_post laesst SVG-Elemente nicht standardmaessig durch,
	// aber unsere Mapping-Slugs sollten den Regelfall abdecken.)
	$icon_html = wp_kses_post( $icon );
}

$has_action = ! empty( $action_label ) && ! empty( $action_url );
?>
<div class="<?php echo esc_attr( $root_classes ); ?>" role="status">
	<div class="dhps-empty-state__icon" aria-hidden="true"><?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internes SVG bzw. via wp_kses_post gefiltert. ?></div>

	<?php if ( '' !== $title ) : ?>
		<h3 class="dhps-empty-state__title"><?php echo esc_html( $title ); ?></h3>
	<?php endif; ?>

	<?php if ( '' !== $hint ) : ?>
		<p class="dhps-empty-state__hint"><?php echo esc_html( $hint ); ?></p>
	<?php endif; ?>

	<?php if ( $has_action ) : ?>
		<a class="dhps-empty-state__action" href="<?php echo esc_url( $action_url ); ?>">
			<?php echo esc_html( $action_label ); ?>
		</a>
	<?php endif; ?>
</div>
