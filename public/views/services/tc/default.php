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

$tc_html  = $data['html'] ?? '';
$is_empty = ! empty( $data['is_empty'] );
?>
<div class="dhps-service dhps-service--tc <?php echo esc_attr( $layout_class . $custom_class ); ?>">

	<?php if ( $is_empty ) : ?>
		<div class="dhps-tc__empty" role="status">
			<svg class="dhps-tc__empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<rect x="4" y="2" width="16" height="20" rx="2"/>
				<line x1="8" y1="6" x2="16" y2="6"/>
				<line x1="8" y1="10" x2="10" y2="10"/>
				<line x1="12" y1="10" x2="14" y2="10"/>
				<line x1="8" y1="14" x2="10" y2="14"/>
				<line x1="12" y1="14" x2="14" y2="14"/>
				<line x1="8" y1="18" x2="10" y2="18"/>
				<line x1="12" y1="18" x2="14" y2="18"/>
			</svg>
			<h4 class="dhps-tc__empty-title">
				<?php echo esc_html( 'Keine Steuer-Rechner verfuegbar' ); ?>
			</h4>
			<p class="dhps-tc__empty-text">
				<?php echo esc_html( 'Pruefen Sie die Tax-Rechner Kundennummer in den Plugin-Einstellungen oder kontaktieren Sie den Deubner Verlag.' ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="dhps-tc__container">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML aus vertrauenswuerdiger Deubner-API mit Akkordeon-JS.
			echo $tc_html;
			?>
		</div>
	<?php endif; ?>

</div>
