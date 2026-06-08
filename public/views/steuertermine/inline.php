<?php
/**
 * Steuertermine Template: Inline (einzeilig fuer Header/Footer).
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

// v0.18.0: Collection IMMER (siehe default.php Header + Steuertermine::render).
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
?>
<span class="dhps-termine dhps-termine--inline<?php echo esc_attr( $custom_class ); ?>">
    <?php foreach ( $data as $m_index => $month ) : ?>
        <?php if ( $m_index > 0 ) : ?>
            <span class="dhps-termine__separator">&middot;</span>
        <?php endif; ?>
        <?php if ( ! empty( $month['title'] ) ) : ?>
            <span class="dhps-termine__month-label"><?php echo esc_html( $month['title'] ); ?>:</span>
        <?php endif; ?>
        <?php foreach ( $month['entries'] as $e_index => $entry ) : ?>
            <?php if ( $e_index > 0 ) : ?>
                <span class="dhps-termine__separator">&middot;</span>
            <?php endif; ?>
            <span class="dhps-termine__inline-entry"><?php echo esc_html( $entry['date'] ); ?> <?php echo esc_html( implode( ', ', $entry['taxes'] ) ); ?></span>
        <?php endforeach; ?>
    <?php endforeach; ?>
</span>
