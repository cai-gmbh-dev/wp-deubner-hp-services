<?php
/**
 * Layout-Template: Card.
 *
 * Wrapper mit innerer Card-Komponente fuer eine visuell abgesetzte
 * Darstellung mit Box-Shadow, Border-Radius und Padding.
 * Kann vom Theme ueberschrieben werden unter: {theme}/dhps/layout-card.php
 *
 * Verfuegbare Variablen:
 * - $content       (string) Der vorbereitete HTML-Inhalt aus der API.
 * - $service_class (string) CSS-Klasse fuer den Service-Typ (z.B. 'dhps-service--mio').
 * - $layout_class  (string) CSS-Klasse fuer das Layout (z.B. 'dhps-layout--card').
 * - $custom_class  (string) Optionale benutzerdefinierte CSS-Klasse (mit fuehrendem Leerzeichen oder leer).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views
 * @since      0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>">
	<div class="dhps-card">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Vorbereiteter API-Inhalt, bereits sanitized. ?>
	</div>
</div>
