<?php
/**
 * Plugin Name: Deubner Homepage Services
 * Version: 0.9.8
 * Plugin URI: https://github.com/cai-gmbh-dev/wp-deubner-hp-services
 * Description: Integration der Deubner Homepage Services rund um die Themen Steuer und Recht via Shortcode
 * Based On: Frank Malburg
 * Author: Deubner Verlag, Köln, Deutschland
 * Author URI: https://deubner-verlag.de
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: deubner_hp_services
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Update URI: https://github.com/cai-gmbh-dev/wp-deubner-hp-services
 *
 * Developer: CAI GmbH, Hansestadt Wipperfuerth, Deutschland
 * Developer Author: Kai R. Emde
 *
 * @package Deubner Homepage-Service
 * @version 0.9.8
 * @author Deubner Verlag <mi-online-technik@deubner-verlag.de>
 * @copyright Copyright (c) 2004 - 2026, Deubner Verlag GmbH & Co. KG / CAI GmbH
 * @link https://www.deubner-online.de/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// Direkten Zugriff verhindern.
if ( ! defined( 'WPINC' ) ) {
    die();
}

/*
|--------------------------------------------------------------------------
| Plugin-Konstanten
|--------------------------------------------------------------------------
*/

/** @var string Plugin-Version. */
define( 'DEUBNER_HP_SERVICES_VERSION', '0.9.8' );

/** @var string Absoluter Pfad zum Plugin-Verzeichnis (mit trailing slash). */
define( 'DEUBNER_HP_SERVICES_PATH', plugin_dir_path( __FILE__ ) );

/** @var string URL zum Plugin-Verzeichnis (mit trailing slash). */
define( 'DEUBNER_HP_SERVICES_URL', plugin_dir_url( __FILE__ ) );

/** @var string Plugin-Basename fuer Hooks (z.B. 'wp-deubner-hp-services/Deubner_HP_Services.php'). */
define( 'DEUBNER_HP_SERVICES_BASENAME', plugin_basename( __FILE__ ) );

/** @var string API-Basis-URL fuer alle Deubner-Services. */
define( 'DEUBNER_HP_SERVICES_API_BASE', 'https://www.deubner-online.de/' );

/** @var string Nonce-Action fuer alle Admin-Formulare. */
define( 'DEUBNER_HP_SERVICES_NONCE_ACTION', 'dhps_save_settings' );

/*
|--------------------------------------------------------------------------
| Autoloader
|--------------------------------------------------------------------------
|
| Laedt DHPS_*-Klassen automatisch aus dem includes/-Verzeichnis.
| Konvention: DHPS_Foo_Bar -> includes/class-dhps-foo-bar.php
|
*/

spl_autoload_register( function ( $class_name ) {
    // Nur DHPS_-prefixed Klassen und das Interface autoloaden.
    if ( 0 !== strpos( $class_name, 'DHPS_' ) ) {
        return;
    }

    // Klassenname in Dateinamen umwandeln:
    // DHPS_API_Client -> dhps_api_client -> dhps-api-client -> class-dhps-api-client.php
    $file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

    // Suchpfade: includes/ und includes/parsers/.
    $search_paths = array(
        DEUBNER_HP_SERVICES_PATH . 'includes/' . $file_name,
        DEUBNER_HP_SERVICES_PATH . 'includes/parsers/' . $file_name,
    );

    foreach ( $search_paths as $file_path ) {
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
            return;
        }
    }
} );

/*
|--------------------------------------------------------------------------
| Aktivierung / Deaktivierung
|--------------------------------------------------------------------------
*/

register_activation_hook( __FILE__, 'dhps_activate' );
register_deactivation_hook( __FILE__, 'dhps_deactivate' );

/**
 * Plugin-Aktivierung: Standard-Optionen setzen und alte Keys migrieren.
 *
 * @since 0.3.0
 *
 * @return void
 */
function dhps_activate() {
    // Standard-Optionen nur setzen, wenn noch nicht vorhanden.
    $defaults = array(
        'dhps_ota_mio'        => '',
        'dhps_variante'       => '1',
        'dhps_anzahl'         => '10',
        'dhps_lxmio_ota'      => '',
        'dhps_lxmio_variante' => '1',
        'dhps_lxmio_anzahl'   => '10',
        'dhps_mmo_ota'        => '',
        'dhps_mil_ota'        => '',
        'dhps_ota_tp'         => '',
        'dhps_tp_kdnr'        => '',
        'dhps_tpt_ues'        => '',
        'dhps_tpt_teasertext' => '',
        'dhps_tpt_breite'     => '',
        'dhps_tpt_modus'      => 'standard',
        'dhps_tc_kdnr'        => '',
        'dhps_maes_kdnr'      => '',
        'dhps_lp_ota'         => '',
    );

    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) {
            add_option( $key, $value );
        }
    }

    // Migration: Alte Options-Keys (ohne Prefix, v0.2.0) auf neue migrieren.
    $migration_map = array(
        'ota_mio'        => 'dhps_ota_mio',
        'variante'       => 'dhps_variante',
        'filter'         => 'dhps_filter',
        'anzahl'         => 'dhps_anzahl',
        'lxmio_ota'      => 'dhps_lxmio_ota',
        'lxmio_variante' => 'dhps_lxmio_variante',
        'lxmio_filter'   => 'dhps_lxmio_filter',
        'lxmio_anzahl'   => 'dhps_lxmio_anzahl',
        'mmo_ota'        => 'dhps_mmo_ota',
        'mil_ota'        => 'dhps_mil_ota',
        'ota_tp'         => 'dhps_ota_tp',
        'tp_kdnr'        => 'dhps_tp_kdnr',
        'tpt_ues'        => 'dhps_tpt_ues',
        'tpt_teasertext' => 'dhps_tpt_teasertext',
        'tpt_breite'     => 'dhps_tpt_breite',
        'tpt_modus'      => 'dhps_tpt_modus',
        'tc_kdnr'        => 'dhps_tc_kdnr',
        'maes_kdnr'      => 'dhps_maes_kdnr',
        'lp_ota'         => 'dhps_lp_ota',
    );

    foreach ( $migration_map as $old_key => $new_key ) {
        $old_value = get_option( $old_key );
        if ( false !== $old_value && '' === get_option( $new_key, '' ) ) {
            update_option( $new_key, $old_value );
            delete_option( $old_key );
        }
    }
}

/**
 * Plugin-Deaktivierung: Transient-Cache leeren.
 *
 * @since 0.3.0
 *
 * @return void
 */
function dhps_deactivate() {
    // Cache-Klasse nutzen, falls verfuegbar.
    if ( class_exists( 'DHPS_Cache' ) ) {
        $cache = new DHPS_Cache();
        $cache->flush();
        return;
    }

    // Fallback: Direkte Datenbank-Bereinigung.
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_dhps_%',
            '_transient_timeout_dhps_%'
        )
    );
}

/*
|--------------------------------------------------------------------------
| Plugin-Bootstrap
|--------------------------------------------------------------------------
|
| Initialisiert alle Plugin-Komponenten nach dem Laden aller Plugins.
| Reihenfolge: API-Schicht -> Shortcodes (Frontend) -> Admin (Backend)
|
*/

add_action( 'plugins_loaded', 'dhps_init' );

/**
 * Initialisiert das Plugin.
 *
 * Erzeugt die API-Schicht (Legacy-API + Cache + Client + Renderer + Pipeline),
 * den Demo-Manager und registriert Shortcodes, WP-Widget und Elementor.
 *
 * @since 0.4.0
 * @since 0.5.0 Renderer, Widget und Elementor hinzugefuegt.
 * @since 0.6.0 Demo-Manager hinzugefuegt.
 * @since 0.9.0 Content-Pipeline, Parser-Registry und AJAX-Proxy hinzugefuegt.
 *
 * @return void
 */
function dhps_init() {
    // 1. API-Schicht aufbauen (Dependency Injection).
    $api      = new DHPS_Legacy_API();
    $cache    = new DHPS_Cache();
    $client   = new DHPS_API_Client( $api, $cache );
    $renderer = new DHPS_Renderer();

    // 2. Content-Pipeline aufbauen (Parser-Schicht).
    $pipeline = new DHPS_Content_Pipeline( $client, $renderer, $cache );

    // 3. Service-Parser registrieren.
    // MIO und LXMIO verwenden denselben Parser (gleiche HTML-Struktur).
    // Services ohne registrierten Parser fallen auf Raw-HTML-Rendering zurueck.
    $mio_parser = new DHPS_MIO_Parser();
    DHPS_Parser_Registry::register( 'mio', $mio_parser );
    DHPS_Parser_Registry::register( 'lxmio', $mio_parser );

    // MMB-Parser registrieren (Merkblaetter).
    $mmb_parser = new DHPS_MMB_Parser();
    DHPS_Parser_Registry::register( 'mmb', $mmb_parser );

    // TP-Parser registrieren (TaxPlain Videos).
    $tp_parser = new DHPS_TP_Parser();
    DHPS_Parser_Registry::register( 'tp', $tp_parser );

    // 4. AJAX-Proxy registrieren (serverseitige News-Anfragen).
    $ajax_proxy = new DHPS_AJAX_Proxy( $api, $cache );
    $ajax_proxy->register();

    // 5. Demo-Manager initialisieren und abgelaufene Demos pruefen.
    $demo_manager = new DHPS_Demo_Manager();
    $demo_manager->check_expired_demos();
    $renderer->set_demo_manager( $demo_manager );

    // 6. Shortcodes registrieren (Frontend + Backend-Preview).
    $shortcodes = new DHPS_Shortcodes( $client, $renderer, $pipeline );
    $shortcodes->register();

    // 7. WordPress-Widget registrieren.
    add_action( 'widgets_init', function () use ( $client, $renderer ) {
        register_widget( 'DHPS_Widget' );

        // Dependencies ueber Setter injizieren (WP_Widget erlaubt keinen Custom-Constructor).
        global $wp_widget_factory;
        if ( isset( $wp_widget_factory->widgets['DHPS_Widget'] ) ) {
            $wp_widget_factory->widgets['DHPS_Widget']->set_dependencies( $client, $renderer );
        }
    } );

    // 8. Elementor-Integration initialisieren (nur wenn Elementor geladen).
    $elementor = new DHPS_Elementor( $pipeline );
    $elementor->init();

    // 9. Admin-Bereich initialisieren (nur im Backend).
    if ( is_admin() ) {
        new DHPS_Admin( $demo_manager );
    }

    // 10. Frontend-Assets registrieren.
    add_action( 'wp_enqueue_scripts', 'dhps_enqueue_frontend_styles' );

    // 11. GitHub-Updater initialisieren (prueft auf neue Releases).
    $updater = new DHPS_GitHub_Updater(
        'cai-gmbh-dev',
        'wp-deubner-hp-services',
        DEUBNER_HP_SERVICES_BASENAME,
        DEUBNER_HP_SERVICES_VERSION
    );
    $updater->init();
}

/**
 * Registriert und enqueued die Frontend-Stylesheets und -Scripts.
 *
 * Laedt das Basis-Stylesheet (Elementor-Kompatibilitaet), das
 * Layout-Stylesheet (Container-Styling) und service-spezifische
 * JavaScript-Dateien.
 *
 * @since 0.4.0
 * @since 0.5.0 Layout-Stylesheet hinzugefuegt.
 * @since 0.9.0 MIO-JavaScript fuer AJAX-News hinzugefuegt.
 *
 * @return void
 */
function dhps_enqueue_frontend_styles() {
    // Design-Tokens (CSS Custom Properties fuer alle Stylesheets).
    wp_register_style(
        'dhps-design-tokens',
        DEUBNER_HP_SERVICES_URL . 'css/dhps-design-tokens.css',
        array(),
        DEUBNER_HP_SERVICES_VERSION,
        'all'
    );
    wp_enqueue_style( 'dhps-design-tokens' );

    // Basis-Stylesheet (Elementor-Kompatibilitaet).
    wp_register_style(
        'dhps-base-css',
        DEUBNER_HP_SERVICES_URL . 'css/dhps_base.css',
        array( 'dhps-design-tokens' ),
        DEUBNER_HP_SERVICES_VERSION,
        'all'
    );
    wp_enqueue_style( 'dhps-base-css' );

    // Layout-Stylesheet (Container-Styling fuer Layout-Templates).
    wp_register_style(
        'dhps-frontend-css',
        DEUBNER_HP_SERVICES_URL . 'css/dhps-frontend.css',
        array( 'dhps-base-css' ),
        DEUBNER_HP_SERVICES_VERSION,
        'all'
    );
    wp_enqueue_style( 'dhps-frontend-css' );

    // MIO-JavaScript (wird nur geladen wenn MIO/LXMIO-Parser aktiv).
    // Registrierung hier, Enqueue erfolgt conditional im Template.
    wp_register_script(
        'dhps-mio-js',
        DEUBNER_HP_SERVICES_URL . 'public/js/dhps-mio.js',
        array(),
        DEUBNER_HP_SERVICES_VERSION,
        true
    );

    // MMB-JavaScript (Merkblatt-Accordion + Suche).
    wp_register_script(
        'dhps-mmb-js',
        DEUBNER_HP_SERVICES_URL . 'public/js/dhps-mmb.js',
        array(),
        DEUBNER_HP_SERVICES_VERSION,
        true
    );

    // TP-JavaScript (Video Lazy Loading + Kategorie-Filter).
    wp_register_script(
        'dhps-tp-js',
        DEUBNER_HP_SERVICES_URL . 'public/js/dhps-tp.js',
        array(),
        DEUBNER_HP_SERVICES_VERSION,
        true
    );
}
