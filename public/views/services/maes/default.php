<?php
/**
 * Service-Template: MAES Standard-Layout (Meine Aerzteseite).
 *
 * Orchestrator-Shim (seit 0.14.1): delegiert an die modularen Sub-
 * Templates videos.php / merkblaetter.php / aktuelles.php, die mit
 * dem v0.14.0 Component-System (ContentList + ContentCard) arbeiten.
 *
 * Section-Filter via WordPress-Filter 'dhps_maes_section' oder
 * Shortcode-Attribut section:
 *   - 'all'          Alle Sektionen (Videos + Merkblaetter + Aktuelles)
 *   - 'videos'       Nur Video-Tipps
 *   - 'merkblaetter' Nur Merkblaetter / Checklisten
 *   - 'aktuelles'    Nur Aktuelle Nachrichten
 *
 * Bugfix v0.14.1: 'aktuelles' war zuvor nicht in der Section-Liste und
 * wurde im Default-Layout nicht gerendert. Korrigiert: 'all' rendert
 * nun alle 3 Sektionen in dieser Reihenfolge.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/maes/default.php
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

// Section-Filter: 'all', 'videos', 'merkblaetter', 'aktuelles'.
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
		<h2 class="screen-reader-text"><?php esc_html_e( 'Video-Tipps', 'wp-deubner-hp-services' ); ?></h2>
		<?php
		// Sub-Template videos.php (modernisiert in v0.14.1 - ContentList + ContentCard).
		include $base_path . 'videos.php';
		?>
	<?php endif; ?>

	<?php if ( $show_aktuelles && ! empty( $news ) ) : ?>
		<h2 class="screen-reader-text"><?php esc_html_e( 'Aktuelle Nachrichten', 'wp-deubner-hp-services' ); ?></h2>
		<?php
		// Sub-Template aktuelles.php (modernisiert in v0.14.1).
		include $base_path . 'aktuelles.php';
		?>
	<?php endif; ?>

	<?php if ( $show_mb && ! empty( $merkblaetter ) ) : ?>
		<h2 class="screen-reader-text"><?php esc_html_e( 'Merkblaetter und Checklisten', 'wp-deubner-hp-services' ); ?></h2>
		<?php
		// Sub-Template merkblaetter.php (modernisiert in v0.14.1).
		// Variable-Bridge: Sub-Template erwartet $merkblaetter.
		include $base_path . 'merkblaetter.php';
		?>
	<?php endif; ?>

</div>
