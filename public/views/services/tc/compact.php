<?php
/**
 * Service-Template: TC Kompakt-Layout (minimale Container-Padding).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/TC
 * @since      0.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// v0.18.0: Pipeline-Garantie (siehe MMB/default.php Header).
// echo $tc_html Trust-Decision (v0.13.0/v0.14.4) BLEIBT UNANGETASTET.
$collection = dhps_collection_or_empty( $collection, 'tc' );
$tc_html    = (string) $collection->get_meta( 'html', '' );
$is_empty   = (bool) $collection->get_meta( 'is_empty', true );
?>
<div class="dhps-service dhps-service--tc dhps-service--tc-compact <?php echo esc_attr( $layout_class . $custom_class ); ?>">

	<?php if ( $is_empty ) : ?>
		<?php
		// EmptyState via Component (v0.14.4-Migration).
		// Compact-Variante: Modifier-Klasse "dhps-tc__empty--compact" fuer
		// optionale CSS-Anpassung (z.B. kleineres Icon) in v0.14.5+.
		// BC: "dhps-tc__empty" haelt alte Selektoren funktional.
		if ( function_exists( 'dhps_component' ) ) {
			echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
				'empty-state',
				array(
					'icon'  => 'calculator',
					'title' => __( 'Keine Steuer-Rechner verfuegbar', 'wp-deubner-hp-services' ),
					'hint'  => __( 'Bitte Kundennummer pruefen.', 'wp-deubner-hp-services' ),
					'class' => 'dhps-tc__empty dhps-tc__empty--compact',
				)
			);
		}
		?>
	<?php else : ?>
		<div class="dhps-tc__container dhps-tc__container--compact">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML aus vertrauenswuerdiger Deubner-API.
			echo $tc_html;
			?>
		</div>
	<?php endif; ?>

</div>
