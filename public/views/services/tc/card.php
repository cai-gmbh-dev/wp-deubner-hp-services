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

$tc_html  = $data['html'] ?? '';
$is_empty = ! empty( $data['is_empty'] );
?>
<div class="dhps-service dhps-service--tc <?php echo esc_attr( $layout_class . $custom_class ); ?>">
	<div class="dhps-card">

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
					<?php echo esc_html( 'Pruefen Sie die Tax-Rechner Kundennummer in den Plugin-Einstellungen.' ); ?>
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
</div>
