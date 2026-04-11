<?php
/**
 * Template-Partial: Plugin-Header (Deubner-Branding).
 *
 * Rendert den gemeinsamen Header fuer alle Admin-Seiten des Plugins
 * im Deubner-Verlag-Stil: Gruener Balken mit Logo und Navigation.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Admin/Views/Partials
 * @since      0.4.0
 * @since      0.9.6 Deubner-Branding Redesign.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="dhps-db-header">
    <div class="dhps-db-header__inner">
        <div class="dhps-db-header__brand">
            <img src="<?php echo esc_url( DEUBNER_HP_SERVICES_URL . 'assets/images/dvicon.svg' ); ?>"
                 alt="<?php echo esc_attr( 'Deubner Verlag' ); ?>"
                 class="dhps-db-header__logo">
            <span class="dhps-db-header__title"><?php echo esc_html( 'Deubner Homepage Services' ); ?></span>
            <span class="dhps-db-header__version"><?php echo esc_html( 'v' . DEUBNER_HP_SERVICES_VERSION ); ?></span>
        </div>
        <nav class="dhps-db-header__nav">
            <a href="<?php echo esc_url( 'https://www.deubner-steuern.de/shop/homepage-services.html' ); ?>"
               class="dhps-db-header__link" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html( 'Shop' ); ?>
            </a>
            <a href="<?php echo esc_url( 'https://www.deubner-steuern.de/' ); ?>"
               class="dhps-db-header__link" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html( 'deubner-steuern.de' ); ?>
            </a>
        </nav>
    </div>
</div>
