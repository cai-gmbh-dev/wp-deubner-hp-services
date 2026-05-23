<?php
/**
 * Plugin Name: Deubner Homepage Services
 * Version: 0.14.2
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
 * @version 0.14.2
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
define( 'DEUBNER_HP_SERVICES_VERSION', '0.14.2' );

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

/** @var string Alpine.js-Vendor-Version (lokal gebundled, seit 0.14.2). */
if ( ! defined( 'DHPS_ALPINE_VERSION' ) ) {
    define( 'DHPS_ALPINE_VERSION', '3.14.9' );
}

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
| Component-System-Helpers (v0.14.2)
|--------------------------------------------------------------------------
|
| Die Klasse DHPS_Component_Registry wird via Autoloader geladen.
| Die globalen Renderer-Funktionen (dhps_component, dhps_render_component)
| sind keine Klasse und werden hier manuell inkludiert.
*/

require_once DEUBNER_HP_SERVICES_PATH . 'includes/dhps-component-helpers.php';

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
    // MIL (Infografiken) verwendet denselben Parser (gleiche HTML-Struktur).
    $mmb_parser = new DHPS_MMB_Parser();
    DHPS_Parser_Registry::register( 'mmb', $mmb_parser );
    DHPS_Parser_Registry::register( 'mil', $mmb_parser );

    // TP-Parser registrieren (TaxPlain Videos).
    $tp_parser = new DHPS_TP_Parser();
    DHPS_Parser_Registry::register( 'tp', $tp_parser );

    // LP-Parser registrieren (Lexplain - erbt von TP, Recht-Branding).
    $lp_parser = new DHPS_LP_Parser();
    DHPS_Parser_Registry::register( 'lp', $lp_parser );

    // TPT-Parser registrieren (TaxPlain Teaser - erbt von TP, einzelner Video-Block).
    $tpt_parser = new DHPS_TPT_Parser();
    DHPS_Parser_Registry::register( 'tpt', $tpt_parser );

    // TC-Parser registrieren (Tax-Rechner - Wrapper mit Empty-State-Erkennung).
    $tc_parser = new DHPS_TC_Parser();
    DHPS_Parser_Registry::register( 'tc', $tc_parser );

    // MAES-Parser registrieren (Meine Aerzteseite).
    $maes_parser = new DHPS_MAES_Parser();
    DHPS_Parser_Registry::register( 'maes', $maes_parser );

    // 3b. Component-Registry: UI-Bausteine registrieren (v0.14.2).
    dhps_register_components();

    // 4. AJAX-Proxy registrieren (serverseitige News-Anfragen).
    $ajax_proxy = new DHPS_AJAX_Proxy( $api, $cache );
    $ajax_proxy->register();

    // 4b. MMB-Lazy-Akkordeon AJAX-Endpoint (v0.14.2).
    $mmb_ajax = new DHPS_MMB_AJAX_Handler( $client, $cache );
    $mmb_ajax->register();

    // 5. Demo-Manager initialisieren und abgelaufene Demos pruefen.
    $demo_manager = new DHPS_Demo_Manager();
    $demo_manager->check_expired_demos();
    $renderer->set_demo_manager( $demo_manager );

    // 6. Shortcodes registrieren (Frontend + Backend-Preview).
    $shortcodes = new DHPS_Shortcodes( $client, $renderer, $pipeline );
    $shortcodes->register();

    // 7. Standalone-Shortcodes.
    new DHPS_Steuertermine( $client, $cache );
    new DHPS_MAES_Modules( $client, $cache );

    // 8. WordPress-Widget registrieren.
    add_action( 'widgets_init', function () use ( $client, $renderer ) {
        register_widget( 'DHPS_Widget' );

        // Dependencies ueber Setter injizieren (WP_Widget erlaubt keinen Custom-Constructor).
        global $wp_widget_factory;
        if ( isset( $wp_widget_factory->widgets['DHPS_Widget'] ) ) {
            $wp_widget_factory->widgets['DHPS_Widget']->set_dependencies( $client, $renderer );
        }
    } );

    // 9. Elementor-Integration initialisieren (nur wenn Elementor geladen).
    $elementor = new DHPS_Elementor( $pipeline, $client, $cache );
    $elementor->init();

    // 10. Admin-Bereich initialisieren (nur im Backend).
    if ( is_admin() ) {
        new DHPS_Admin( $demo_manager );
    }

    // 11. Frontend-Assets registrieren.
    add_action( 'wp_enqueue_scripts', 'dhps_enqueue_frontend_styles' );

    // 12. GitHub-Updater initialisieren (prueft auf neue Releases).
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

    // Component-Stylesheet (Shared Components - Skeleton, EmptyState, LazyImage, Accordion, ...).
    // Seit 0.14.2. Wird sofort geladen, weil die Components ueberall potentiell verwendet werden.
    wp_register_style(
        'dhps-components-css',
        DEUBNER_HP_SERVICES_URL . 'css/dhps-components.css',
        array( 'dhps-design-tokens', 'dhps-frontend-css' ),
        DEUBNER_HP_SERVICES_VERSION,
        'all'
    );
    wp_enqueue_style( 'dhps-components-css' );

    // Elementor-Atomic-Token-Bridge (seit 0.14.2, optional, Default aus).
    // Bridget generische UI-Tokens an Elementor-Globals; Brand-Tokens bleiben isoliert.
    wp_register_style(
        'dhps-elementor-bridge-css',
        DEUBNER_HP_SERVICES_URL . 'css/dhps-elementor-bridge.css',
        array( 'dhps-design-tokens' ),
        DEUBNER_HP_SERVICES_VERSION,
        'all'
    );
    if ( '1' === (string) get_option( 'dhps_elementor_bridge_enabled', '0' ) ) {
        wp_enqueue_style( 'dhps-elementor-bridge-css' );
    }

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

    // MAES-Aktuelles JS-Block in v0.13.1 -> v0.14.2 ausgelagert,
    // in v0.14.2 obsolet: ContentCard's Alpine-Toggle uebernimmt die
    // Akkordeon-Funktion. File geloescht, Enqueue entfernt.

    // Alpine.js v3.x Vendor (lokal gebundled, defer, conditional enqueue, seit 0.14.2).
    wp_register_script(
        'dhps-alpinejs-vendor',
        DEUBNER_HP_SERVICES_URL . 'public/js/vendor/alpinejs-3.14.x.min.js',
        array(),
        DHPS_ALPINE_VERSION,
        true
    );

    // Alpine-Init: registriert dhps*-Komponenten am alpine:init-Event.
    wp_register_script(
        'dhps-alpine-init',
        DEUBNER_HP_SERVICES_URL . 'public/js/dhps-alpine-init.js',
        array( 'dhps-alpinejs-vendor' ),
        DEUBNER_HP_SERVICES_VERSION,
        true
    );

    // 4 Stateful Alpine-Komponenten (ContentCard, FilterBar, Pagination, ContentList).
    wp_register_script(
        'dhps-components-alpine',
        DEUBNER_HP_SERVICES_URL . 'public/js/dhps-components-alpine.js',
        array( 'dhps-alpine-init' ),
        DEUBNER_HP_SERVICES_VERSION,
        true
    );
}

/*
|--------------------------------------------------------------------------
| Component-Registry-Bootstrap (v0.14.2)
|--------------------------------------------------------------------------
|
| Registriert alle Plugin-internen UI-Components. Spaetere Specialists
| (F5-F7) erweitern hier ihre Components, oder externe Plugins haengen
| sich an die Action `dhps_register_components` an.
*/

/**
 * Registriert die Plugin-Components in der Component-Registry.
 *
 * @since 0.14.2
 *
 * @return void
 */
function dhps_register_components() {
    // 4 Stateless Components (F4 - keine Alpine-Dependency).
    DHPS_Component_Registry::register( 'skeleton-loader', array(
        'default_props' => array(
            'type'  => 'card',
            'count' => 3,
            'class' => '',
        ),
        'stateless'     => true,
    ) );

    DHPS_Component_Registry::register( 'empty-state', array(
        'default_props' => array(
            'icon'         => 'inbox',
            'title'        => '',
            'hint'         => '',
            'action_label' => null,
            'action_url'   => null,
            'class'        => '',
        ),
        'stateless'     => true,
    ) );

    DHPS_Component_Registry::register( 'lazy-image', array(
        'default_props' => array(
            'src'    => '',
            'alt'    => '',
            'width'  => 0,
            'height' => 0,
            'lqip'   => null,
            'class'  => '',
        ),
        'stateless'     => true,
    ) );

    DHPS_Component_Registry::register( 'accordion', array(
        'default_props' => array(
            'id'    => '',
            'items' => array(),
            'multi' => false,
            'class' => '',
        ),
        'stateless'     => true,
    ) );

    // 4 Stateful Components (F5 - mit Alpine.js fuer Interaktion).
    DHPS_Component_Registry::register( 'content-card', array(
        'default_props'   => array(
            'type'        => 'news',
            'title'       => '',
            'teaser'      => '',
            'body_html'   => '',
            'media_url'   => '',
            'media_alt'   => '',
            'badges'      => array(),
            'meta'        => array(),
            'actions'     => array(),
            'collapsible' => false,
            'class'       => '',
            'service'     => '',
            'data_attrs'  => array(),  // seit 0.14.2 - data-* fuer Player-Selectoren etc.
        ),
        'stateful'        => true,
        'requires_alpine' => 'conditional',
    ) );

    DHPS_Component_Registry::register( 'filter-bar', array(
        'default_props'   => array(
            'target'             => '',
            'search_placeholder' => '',
            'tags'               => array(),
            'sorts'              => array(),
            'debounce_ms'        => 300,
            'min_chars'          => 2,
            'class'              => '',
        ),
        'stateful'        => true,
        'requires_alpine' => true,
    ) );

    DHPS_Component_Registry::register( 'pagination', array(
        'default_props'   => array(
            'mode'         => 'load-more',
            'current_page' => 1,
            'total_pages'  => 1,
            'has_more'     => true,
            'page_size'    => 10,
            'ajax_url'     => null,
            'ajax_action'  => null,
            'ajax_nonce'   => null,
            'class'        => '',
        ),
        'stateful'        => true,
        'requires_alpine' => true,
    ) );

    DHPS_Component_Registry::register( 'content-list', array(
        'default_props'   => array(
            'id'          => '',
            'layout'      => 'grid',
            'columns'     => 2,
            'filterable'  => false,
            'searchable'  => false,
            'sortable'    => false,
            'items'       => array(),
            'item_type'   => 'news',
            'empty_state' => null,
            'pagination'  => null,
            'filter_bar'  => null,
            'class'       => '',
        ),
        'stateful'        => true,
        'requires_alpine' => true,
    ) );

    /**
     * Action: erlaubt Dritten (Specialists F5-F7, Theme, andere Plugins),
     * eigene Components zu registrieren.
     *
     * @since 0.14.2
     */
    do_action( 'dhps_register_components' );
}

/*
|--------------------------------------------------------------------------
| Alpine.js Conditional Loading (v0.14.2)
|--------------------------------------------------------------------------
|
| Alpine wird nur geladen, wenn ein DHPS-Shortcode auf der aktuellen Seite
| rendert. Drei-stufige Detection: Pre-Detect via has_shortcode(), Late-
| Trigger via dhps_request_alpine() aus Shortcode-Handlern, Footer-Catchup.
*/

/**
 * Frueh-Erkennung: durchsucht den Post-Content nach DHPS-Shortcodes
 * und setzt das Bedarf-Flag.
 *
 * @since 0.14.2
 *
 * @return void
 */
function dhps_detect_alpine_need() {
    if ( is_admin() ) {
        return;
    }

    global $post;
    if ( ! ( $post instanceof WP_Post ) ) {
        return;
    }

    $dhps_shortcodes = array(
        'mio', 'mio_termine',
        'lxmio',
        'mmb', 'mil',
        'tp', 'tpt', 'tc',
        'maes', 'maes_videos', 'maes_merkblaetter', 'maes_aktuelles',
        'lp',
    );

    $content = $post->post_content;
    foreach ( $dhps_shortcodes as $sc ) {
        if ( has_shortcode( $content, $sc ) ) {
            $GLOBALS['dhps_needs_alpine'] = true;
            return;
        }
    }
}
add_action( 'wp', 'dhps_detect_alpine_need', 20 );

/**
 * Wird aus Shortcode-Handlern aufgerufen, wenn Alpine benoetigt wird.
 * Setzt Flag oder enqueued direkt, je nachdem ob wp_enqueue_scripts
 * schon gelaufen ist.
 *
 * @since 0.14.2
 *
 * @return void
 */
function dhps_request_alpine() {
    if ( wp_script_is( 'dhps-alpine-init', 'enqueued' ) ) {
        return;
    }
    if ( did_action( 'wp_enqueue_scripts' ) ) {
        dhps_enqueue_alpine_now();
    } else {
        $GLOBALS['dhps_needs_alpine'] = true;
    }
}

/**
 * Enqueued Alpine sofort (idempotent).
 *
 * @since 0.14.2
 *
 * @return void
 */
function dhps_enqueue_alpine_now() {
    if ( wp_script_is( 'dhps-alpine-init', 'enqueued' ) ) {
        return;
    }
    // Vendor wird via Dependency mitgezogen.
    wp_enqueue_script( 'dhps-alpine-init' );
}

/**
 * Footer-Catchup: enqueued Alpine wenn das Bedarf-Flag gesetzt wurde.
 *
 * @since 0.14.2
 *
 * @return void
 */
function dhps_maybe_enqueue_alpine() {
    if ( ! empty( $GLOBALS['dhps_needs_alpine'] ) ) {
        dhps_enqueue_alpine_now();
    }
}
add_action( 'wp_enqueue_scripts', 'dhps_maybe_enqueue_alpine', 20 );

/**
 * Footer-Catchup fuer das Stateful-Components-JS.
 *
 * Enqueued dhps-components-alpine NUR wenn mindestens eine der 4 stateful
 * Components auf der Seite gerendert wurde. Greift auf das mark_used()
 * Pattern der DHPS_Component_Registry zurueck.
 *
 * @since 0.14.2
 *
 * @return void
 */
function dhps_maybe_enqueue_components_js() {
    if ( ! class_exists( 'DHPS_Component_Registry' ) ) {
        return;
    }
    $stateful_components = array( 'content-card', 'filter-bar', 'pagination', 'content-list' );
    foreach ( $stateful_components as $cmp ) {
        if ( DHPS_Component_Registry::was_used( $cmp ) ) {
            // Alpine via Helper anfordern (Vendor + Init werden als Deps mitgezogen).
            dhps_request_alpine();
            wp_enqueue_script( 'dhps-components-alpine' );
            return;
        }
    }
}
add_action( 'wp_enqueue_scripts', 'dhps_maybe_enqueue_components_js', 30 );

/**
 * Registriert die Plugin-Optionen via WP-Settings-API (Defense-in-Depth, Sanitize-Callbacks).
 *
 * Seit 0.14.2. Aktuell nur Bridge-Toggle, weitere Optionen wandern bei
 * Gelegenheit (z.B. v0.15.0 Admin-Dashboard) hierher.
 *
 * @since 0.14.2
 *
 * @return void
 */
function dhps_register_options() {
    register_setting(
        'dhps_settings_group',
        'dhps_elementor_bridge_enabled',
        array(
            'type'              => 'string',
            'sanitize_callback' => function ( $value ) {
                return '1' === (string) $value ? '1' : '0';
            },
            'default'           => '0',
            'show_in_rest'      => false,
        )
    );
}
add_action( 'admin_init', 'dhps_register_options' );

/**
 * Setzt das defer-Attribut auf Alpine-Scripts.
 *
 * @since 0.14.2
 *
 * @param string $tag    Original Script-Tag-HTML.
 * @param string $handle Script-Handle.
 * @return string Modifiziertes Tag.
 */
function dhps_defer_alpine_scripts( $tag, $handle ) {
    $defer_handles = array( 'dhps-alpinejs-vendor', 'dhps-alpine-init', 'dhps-components-alpine' );
    if ( ! in_array( $handle, $defer_handles, true ) ) {
        return $tag;
    }
    // Doppel-Defer vermeiden.
    if ( false !== strpos( $tag, ' defer ' ) || false !== strpos( $tag, ' defer>' ) ) {
        return $tag;
    }
    return str_replace( ' src=', ' defer src=', $tag );
}
add_filter( 'script_loader_tag', 'dhps_defer_alpine_scripts', 10, 2 );
