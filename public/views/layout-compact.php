<?php
/**
 * Layout-Template: Kompakt.
 *
 * Minimales Wrapping mit reduziertem Padding und ohne visuelle
 * Dekorationen (kein Shadow, kein Border-Radius, kein Hintergrund).
 * Kann vom Theme ueberschrieben werden unter: {theme}/dhps/layout-compact.php
 *
 * Verfuegbare Variablen:
 * - $content       (string) Der vorbereitete HTML-Inhalt aus der API.
 * - $service_class (string) CSS-Klasse fuer den Service-Typ (z.B. 'dhps-service--mio').
 * - $layout_class  (string) CSS-Klasse fuer das Layout (z.B. 'dhps-layout--compact').
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
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Vorbereiteter API-Inhalt, bereits sanitized. ?>
</div>
