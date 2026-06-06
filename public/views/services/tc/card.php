<?php
/**
 * Service-Template: TC Card-Layout (mit dhps-card Wrapper).
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
	<div class="dhps-card">

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
						'hint'  => __( 'Pruefen Sie die Tax-Rechner Kundennummer in den Plugin-Einstellungen.', 'wp-deubner-hp-services' ),
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
</div>
