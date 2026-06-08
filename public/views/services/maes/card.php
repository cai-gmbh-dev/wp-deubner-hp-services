<?php
/**
 * Service-Template: MAES Card-Layout (Meine Aerzteseite).
 *
 * Orchestrator-Shim mit dhps-card Wrapper (Box-Shadow). Delegiert
 * an die modularen Sub-Templates videos-card.php / merkblaetter-card.php
 * / aktuelles-card.php (alle modernisiert mit ContentList + ContentCard
 * in v0.14.1).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/MAES
 * @since      0.10.0
 * @version    0.14.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// v0.19.1: Empty-Guards via Collection-Filter.
$collection_safe = dhps_collection_or_empty( $collection, 'maes' );
$has_videos       = $collection_safe->filter( static fn( $i ) => 'video' === $i->type )->count() > 0;
$has_merkblaetter = $collection_safe->filter( static fn( $i ) => 'document' === $i->type )->count() > 0;
$has_news         = $collection_safe->filter( static fn( $i ) => 'news' === $i->type )->count() > 0;

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
	<div class="dhps-card">

		<?php if ( $show_videos && $has_videos ) : ?>
			<?php include $base_path . 'videos-card.php'; ?>
		<?php endif; ?>

		<?php if ( $show_aktuelles && $has_news ) : ?>
			<?php include $base_path . 'aktuelles-card.php'; ?>
		<?php endif; ?>

		<?php if ( $show_mb && $has_merkblaetter ) : ?>
			<?php include $base_path . 'merkblaetter-card.php'; ?>
		<?php endif; ?>

	</div>
</div>
