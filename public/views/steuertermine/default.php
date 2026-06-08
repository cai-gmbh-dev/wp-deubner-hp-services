<?php
/**
 * Steuertermine Template: Default (2-Spalten-Grid).
 *
 * Verfuegbare Variablen:
 *   $data         - Array der Monats-Daten (title, entries[], footnote).
 *   $custom_class - Zusaetzliche CSS-Klasse (mit fuehrendem Leerzeichen oder leer).
 *
 * @package Deubner Homepage-Service
 * @since   0.9.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// v0.18.0: Modules-Layer baut Collection IMMER aus pre-gefilterten Items
// (siehe DHPS_Steuertermine::render). Kein has_collection-Pattern mehr.
$collection = dhps_collection_or_empty( $collection, 'mio' );
$rebuilt    = array();
foreach ( $collection as $item ) {
    $legacy_month = dhps_mio_item_to_legacy_month( $item );
    if ( ! empty( $legacy_month ) ) {
        $rebuilt[] = $legacy_month;
    }
}
if ( ! empty( $rebuilt ) ) {
    $data = $rebuilt;
}

$grid_modifier = ( 1 === count( $data ) ) ? ' dhps-termine__grid--single' : '';
?>
<div class="dhps-termine<?php echo esc_attr( $custom_class ); ?>">
    <div class="dhps-termine__grid<?php echo esc_attr( $grid_modifier ); ?>">
        <?php foreach ( $data as $month ) : ?>
            <div class="dhps-termine__month">
                <?php if ( ! empty( $month['title'] ) ) : ?>
                    <h4 class="dhps-termine__title"><?php echo esc_html( $month['title'] ); ?></h4>
                <?php endif; ?>

                <dl class="dhps-termine__list">
                    <?php foreach ( $month['entries'] as $entry ) : ?>
                        <div class="dhps-termine__entry">
                            <dt class="dhps-termine__date"><?php echo esc_html( $entry['date'] ); ?></dt>
                            <dd class="dhps-termine__taxes"><?php echo esc_html( implode( ', ', $entry['taxes'] ) ); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>

                <?php if ( ! empty( $month['footnote'] ) ) : ?>
                    <div class="dhps-termine__footnote"><?php echo esc_html( $month['footnote'] ); ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
