<?php
/**
 * Deubner Homepage Services - Uninstall Handler.
 *
 * Wird ausgefuehrt wenn das Plugin ueber das WordPress-Admin-Interface
 * deinstalliert wird. Entfernt alle Plugin-Optionen aus der Datenbank.
 *
 * @package Deubner Homepage-Service
 * @since 0.3.0
 */

// Sicherheitscheck: Nur ausfuehren wenn WordPress die Deinstallation auslöst
if (! defined('WP_UNINSTALL_PLUGIN')) {
    die();
}

// Alle Plugin-Optionen (mit dhps_ Prefix) entfernen
$dhps_options = array(
    'dhps_ota_mio',
    'dhps_variante',
    'dhps_filter',
    'dhps_anzahl',
    'dhps_lxmio_ota',
    'dhps_lxmio_variante',
    'dhps_lxmio_filter',
    'dhps_lxmio_anzahl',
    'dhps_mmo_ota',
    'dhps_mil_ota',
    'dhps_ota_tp',
    'dhps_tp_kdnr',
    'dhps_tpt_ues',
    'dhps_tpt_teasertext',
    'dhps_tpt_breite',
    'dhps_tpt_modus',
    'dhps_tc_kdnr',
    'dhps_maes_kdnr',
    'dhps_lp_ota',
);

foreach ($dhps_options as $option) {
    delete_option($option);
}

// Alte Options-Keys (ohne Prefix, aus v0.2.0) ebenfalls entfernen
$legacy_options = array(
    'ota_mio',
    'variante',
    'filter',
    'anzahl',
    'lxmio_ota',
    'lxmio_variante',
    'lxmio_filter',
    'lxmio_anzahl',
    'mmo_ota',
    'mil_ota',
    'ota_tp',
    'tp_kdnr',
    'tpt_ues',
    'tpt_teasertext',
    'tpt_breite',
    'tpt_modus',
    'tc_kdnr',
    'maes_kdnr',
    'lp_ota',
);

foreach ($legacy_options as $option) {
    delete_option($option);
}

// Alle DHPS-Transients entfernen
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_dhps_%',
        '_transient_timeout_dhps_%'
    )
);
