<?php
/**
 * Service-Template: MAES Kompakt-Layout (Meine Aerzteseite).
 *
 * Orchestrator-Shim fuer Sidebar-Einsatz. Delegiert an die modularen
 * Sub-Templates videos-compact.php / merkblaetter-compact.php /
 * aktuelles-compact.php (alle modernisiert mit ContentList +
 * ContentCard in v0.14.1).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/MAES
 * @since      0.10.0
 * @version    0.14.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$videos       = $data['videos'] ?? array();
$merkblaetter = $data['merkblaetter'] ?? array();
$news         = $data['news'] ?? array();
$service_tag  = $data['service_tag'] ?? 'maes';

$section          = sanitize_key( apply_filters( 'dhps_maes_section', 'all' ) );
$allowed_sections = array( 'all', 'videos', 'merkblaetter', 'aktuelles' );
if ( ! in_array( $section, $allowed_sections, true ) ) {
	$section = 'all';
}

$show_videos    = in_array( $section, array( 'all', 'videos' ), true );
$show_mb        = in_array( $section, array( 'all', 'merkblaetter' ), true );
$show_aktuelles = in_array( $section, array( 'all', 'aktuelles' ), true );

$base_path = trailingslashit( DEUBNER_HP_SERVICES_PATH )
	. 'public/views/services/maes/';
?>
<div class="dhps-service <?php echo esc_attr( $service_class . ' ' . $layout_class . $custom_class ); ?>"
	 data-video-mode="modal">

	<?php if ( $show_videos && ! empty( $videos ) ) : ?>
		<?php include $base_path . 'videos-compact.php'; ?>
	<?php endif; ?>

	<?php if ( $show_aktuelles && ! empty( $news ) ) : ?>
		<?php include $base_path . 'aktuelles-compact.php'; ?>
	<?php endif; ?>

	<?php if ( $show_mb && ! empty( $merkblaetter ) ) : ?>
		<?php include $base_path . 'merkblaetter-compact.php'; ?>
	<?php endif; ?>

</div>
