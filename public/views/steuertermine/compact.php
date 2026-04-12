<?php
/**
 * Steuertermine Template: Compact (minimale Liste ohne Grid).
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
?>
<div class="dhps-termine dhps-termine--compact<?php echo esc_attr( $custom_class ); ?>">
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
