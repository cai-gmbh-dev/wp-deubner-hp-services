<?php
/**
 * Template-Partial: Plugin-Header.
 *
 * Rendert den gemeinsamen Header fuer alle Admin-Seiten des Plugins.
 * Enthaelt das Deubner-Logo, den Plugin-Titel sowie Links zu den
 * Deubner-Shops und zur Dokumentation.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Admin/Views/Partials
 * @since      0.4.0
 * @since      0.8.0 Shop-Links aufgeteilt in Recht-Shop und Steuern-Shop.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="dhps-header-container">
    <div class="dhps-header">
        <img
            alt="<?php echo esc_attr( 'Deubner Homepage Service' ); ?>"
            src="<?php echo esc_url( DEUBNER_HP_SERVICES_URL . 'assets/images/dvicon.svg' ); ?>"
        >
        <div class="dhps-header-left">
            <div class="dhps-header-title">
                <?php echo esc_html( 'Deubner Verlag Homepage Services' ); ?>
            </div>
        </div>
        <div class="dhps-header-right">
            <a href="<?php echo esc_url( 'https://www.deubner-steuern.de/shop/' ); ?>"
               class="dhps-header-link" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html( 'Steuern-Shop' ); ?>
            </a>
            <a href="<?php echo esc_url( 'https://www.deubner-recht.de/shop/' ); ?>"
               class="dhps-header-link" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html( 'Recht-Shop' ); ?>
            </a>
            <a href="<?php echo esc_url( 'https://deubner-online.de/docs/' ); ?>"
               class="dhps-header-link" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html( 'Dokumentation' ); ?>
            </a>
        </div>
    </div>
</div>
