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

$tc_html  = $data['html'] ?? '';
$is_empty = ! empty( $data['is_empty'] );
?>
<div class="dhps-service dhps-service--tc dhps-service--tc-compact <?php echo esc_attr( $layout_class . $custom_class ); ?>">

	<?php if ( $is_empty ) : ?>
		<div class="dhps-tc__empty dhps-tc__empty--compact" role="status">
			<p class="dhps-tc__empty-text">
				<?php echo esc_html( 'Keine Steuer-Rechner verfuegbar - bitte Kundennummer pruefen.' ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="dhps-tc__container dhps-tc__container--compact">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML aus vertrauenswuerdiger Deubner-API.
			echo $tc_html;
			?>
		</div>
	<?php endif; ?>

</div>
