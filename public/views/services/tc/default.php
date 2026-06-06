<?php
/**
 * Service-Template: TC Standard-Layout (Tax-Rechner Akkordeon).
 *
 * Rendert das HTML der TC-API in einem Branding-Container.
 * Bei Empty-State wird ein Hinweis angezeigt, dass die kdnr
 * keine Rechner freigeschaltet hat.
 *
 * Verfuegbare Variablen:
 * - $data          (array)  Strukturiertes Array aus DHPS_TC_Parser:
 *                            - 'html'     (string) Original HTML mit Inline-JS
 *                            - 'is_empty' (bool)   Empty-State erkannt
 * - $service_class (string) 'dhps-service--tc'
 * - $layout_class  (string) 'dhps-layout--default'
 * - $custom_class  (string) Optionale CSS-Klasse
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/TC
 * @since      0.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Pseudo-Rebuild aus Collection wenn vorhanden (v0.17.4), sonst Legacy aus $data.
// echo $tc_html Trust-Decision (v0.13.0/v0.14.4) BLEIBT UNANGETASTET.
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
	$tc_html  = (string) $collection->get_meta( 'html', '' );
	$is_empty = (bool) $collection->get_meta( 'is_empty', true );
} else {
	$tc_html  = $data['html'] ?? '';
	$is_empty = ! empty( $data['is_empty'] );
}
?>
<div class="dhps-service dhps-service--tc <?php echo esc_attr( $layout_class . $custom_class ); ?>">

	<?php if ( $is_empty ) : ?>
		<?php
		// EmptyState via Component (v0.14.4-Migration, dedupliziert).
		// BC: zusaetzliche Klasse "dhps-tc__empty" haelt alte CSS-Selektoren funktional.
		if ( function_exists( 'dhps_component' ) ) {
			echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
				'empty-state',
				array(
					'icon'  => 'calculator',
					'title' => __( 'Keine Steuer-Rechner verfuegbar', 'wp-deubner-hp-services' ),
					'hint'  => __( 'Pruefen Sie die Tax-Rechner Kundennummer in den Plugin-Einstellungen oder kontaktieren Sie den Deubner Verlag.', 'wp-deubner-hp-services' ),
					'class' => 'dhps-tc__empty',
				)
			);
		}
		?>
	<?php else : ?>
		<div class="dhps-tc__container">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML aus vertrauenswuerdiger Deubner-API mit Akkordeon-JS.
			echo $tc_html;
			?>
		</div>
	<?php endif; ?>

</div>
