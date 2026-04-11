<?php
/**
 * Admin-Klasse fuer den Deubner Homepage Service.
 *
 * Verwaltet die Registrierung von Admin-Menues, das Enqueuing von
 * Stylesheets und das Rendering aller Admin-Seiten. Delegiert die
 * Formularverarbeitung an den DHPS_Admin_Page_Handler.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Admin
 * @since      0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DHPS_Admin
 *
 * Zentrale Admin-Klasse des Plugins. Registriert Hooks fuer
 * admin_enqueue_scripts und admin_menu, verwaltet die Plugin-eigenen
 * CSS-Stylesheets und rendert alle Admin-Seiten ueber Templates.
 *
 * @since   0.4.0
 * @package Deubner Homepage-Service
 */
class DHPS_Admin {

    /**
     * Liste der Plugin-eigenen Admin-Seiten-Slugs.
     *
     * Wird benutzt, um CSS nur auf diesen Seiten zu laden
     * und die Screen-ID-Pruefung durchzufuehren.
     *
     * @since 0.4.0
     * @var array
     */
    private static $plugin_pages = array(
        'dhps_dashboard',
        'dhps_mio_page',
        'dhps_mmb_page',
        'dhps_mil_page',
        'dhps_tp_page',
        'dhps_tc_page',
        'dhps_maes_page',
        'dhps_lp_spage',
    );

    /**
     * Page-Handler-Instanz fuer Formulardaten-Verarbeitung.
     *
     * @since 0.4.0
     * @var DHPS_Admin_Page_Handler
     */
    private $page_handler;

    /**
     * Demo-Manager-Instanz fuer Demo-Modus-Verwaltung.
     *
     * @since 0.6.0
     * @var DHPS_Demo_Manager
     */
    private $demo_manager;

    /**
     * Konstruktor: Registriert Admin-Hooks.
     *
     * @since 0.4.0
     * @since 0.6.0 Demo-Manager-Parameter hinzugefuegt.
     *
     * @param DHPS_Demo_Manager $demo_manager Demo-Manager-Instanz.
     */
    public function __construct( DHPS_Demo_Manager $demo_manager ) {
        $this->demo_manager = $demo_manager;
        $this->page_handler = new DHPS_Admin_Page_Handler();

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'wp_ajax_dhps_toggle_demo', array( $this, 'handle_demo_toggle' ) );
    }

    /**
     * Registriert und enqueued CSS-Stylesheets auf Plugin-eigenen Seiten.
     *
     * Registriert zwei Stylesheets:
     * - dhps-admin-css: Allgemeine Admin-Styles (css/dhps_admin.css)
     * - dhps-ui-css:    UI-Framework-Styles (css/dhps-ui.css)
     *
     * Die Styles werden nur auf DHPS-Admin-Seiten geladen, um die
     * Performance auf allen anderen WordPress-Admin-Seiten nicht zu belasten.
     *
     * @since 0.4.0
     *
     * @return void
     */
    public function enqueue_scripts(): void {
        $current_screen = '';

        if ( function_exists( 'get_current_screen' ) ) {
            $current_screen = get_current_screen();
        }

        // Design-Tokens (CSS Custom Properties).
        // @since 0.8.0
        wp_register_style(
            'dhps-design-tokens',
            DEUBNER_HP_SERVICES_URL . 'css/dhps-design-tokens.css',
            array(),
            DEUBNER_HP_SERVICES_VERSION,
            'all'
        );

        // CSS registrieren.
        wp_register_style(
            'dhps-admin-css',
            DEUBNER_HP_SERVICES_URL . 'css/dhps_admin.css',
            array(),
            DEUBNER_HP_SERVICES_VERSION,
            'all'
        );
        wp_register_style(
            'dhps-ui-css',
            DEUBNER_HP_SERVICES_URL . 'css/dhps-ui.css',
            array(),
            DEUBNER_HP_SERVICES_VERSION,
            'all'
        );

        // Dashboard-CSS und Admin-JS registrieren.
        wp_register_style(
            'dhps-dashboard-css',
            DEUBNER_HP_SERVICES_URL . 'css/dhps-dashboard.css',
            array(),
            DEUBNER_HP_SERVICES_VERSION,
            'all'
        );
        wp_register_script(
            'dhps-admin-js',
            DEUBNER_HP_SERVICES_URL . 'admin/js/dhps-admin.js',
            array( 'jquery' ),
            DEUBNER_HP_SERVICES_VERSION,
            true
        );

        // Nur auf DHPS-Admin-Seiten enqueuen.
        if ( isset( $current_screen->id ) && $this->is_dhps_page( $current_screen->id ) ) {
            wp_enqueue_style( 'dhps-design-tokens' );
            wp_enqueue_style( 'dhps-admin-css' );
            wp_enqueue_style( 'dhps-ui-css' );

            // Dashboard + Service-Config Styles (Deubner-Branding).
            wp_enqueue_style( 'dhps-dashboard-css' );
            wp_enqueue_script( 'dhps-admin-js' );
        }
    }

    /**
     * Registriert das Top-Level-Menue und alle Submenu-Seiten im WordPress-Admin.
     *
     * Erstellt das "Deubner Verlag"-Menue mit einem SVG-Icon (base64 data-URI)
     * und registriert alle 8 Unterseiten (Dashboard + 7 Service-Konfigurationen).
     *
     * @since 0.4.0
     *
     * @return void
     */
    public function register_menu(): void {
        // SVG-Icon fuer das Admin-Menue.
        $icon_svg = $this->get_menu_icon_svg();

        add_menu_page(
            'Deubner Verlag',
            'Deubner Verlag',
            'manage_options',
            'deubner_hp_services',
            false,
            'data:image/svg+xml;base64,' . base64_encode( $icon_svg ),
            5
        );

        // Dummy-Eintrag entfernen (verhindert doppelten Top-Level-Eintrag).
        add_submenu_page(
            'deubner_hp_services',
            'Deubner Verlag',
            'Deubner Verlag',
            'manage_options',
            'deubner_hp_services',
            '__return_null'
        );
        remove_submenu_page( 'deubner_hp_services', 'deubner_hp_services' );

        // Dashboard.
        add_submenu_page(
            'deubner_hp_services',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'dhps_dashboard',
            array( $this, 'render_dashboard' )
        );

        // Mi-Online (Spezialseite mit 2 Formularen).
        add_submenu_page(
            'deubner_hp_services',
            'Mi-Online',
            'Mi-Online',
            'manage_options',
            'dhps_mio_page',
            function () {
                $this->render_page( 'mio' );
            }
        );

        // Merkblaetter.
        add_submenu_page(
            'deubner_hp_services',
            'Merkblaetter',
            'Merkblaetter',
            'manage_options',
            'dhps_mmb_page',
            function () {
                $this->render_page( 'mmb' );
            }
        );

        // Infografiken.
        add_submenu_page(
            'deubner_hp_services',
            'Infografiken',
            'Infografiken',
            'manage_options',
            'dhps_mil_page',
            function () {
                $this->render_page( 'mil' );
            }
        );

        // Tax-Videos.
        add_submenu_page(
            'deubner_hp_services',
            'Tax-Videos',
            'Tax-Videos',
            'manage_options',
            'dhps_tp_page',
            function () {
                $this->render_page( 'tp' );
            }
        );

        // Tax-Rechner.
        add_submenu_page(
            'deubner_hp_services',
            'Tax-Rechner',
            'Tax-Rechner',
            'manage_options',
            'dhps_tc_page',
            function () {
                $this->render_page( 'tc' );
            }
        );

        // Aerzte-Info.
        add_submenu_page(
            'deubner_hp_services',
            'Aerzte-Info',
            'Aerzte-Info',
            'manage_options',
            'dhps_maes_page',
            function () {
                $this->render_page( 'maes' );
            }
        );

        // Lexplain.
        add_submenu_page(
            'deubner_hp_services',
            'Lexplain',
            'Lexplain',
            'manage_options',
            'dhps_lp_spage',
            function () {
                $this->render_page( 'lp' );
            }
        );
    }

    /**
     * Rendert die Dashboard-Seite.
     *
     * Bereitet die Service-Statusse und Demo-Konfiguration auf
     * und uebergibt sie an das Dashboard-Template.
     *
     * @since 0.4.0
     * @since 0.6.0 Demo-Status-Variablen hinzugefuegt.
     *
     * @return void
     */
    public function render_dashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sie haben keine Berechtigung fuer diese Seite.', 'deubner_hp_services' ) );
        }

        // Template-Variablen fuer das Dashboard.
        $statuses      = $this->demo_manager->get_all_statuses();
        $demo_duration = $this->demo_manager->get_demo_duration();

        $template_path = $this->get_template_path( 'dashboard' );

        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }

    /**
     * Generischer Page-Renderer fuer Service-Konfigurationsseiten.
     *
     * Prueft die Berechtigung, verarbeitet ggf. POST-Daten (save)
     * mit Nonce-Pruefung und laedt das passende Template.
     *
     * Erkennt automatisch Sibling-Services, die dieselbe Admin-Seite teilen
     * (z.B. 'tp' und 'tpt' auf dhps_tp_page) und rendert diese als
     * Extra-Sections mit eigenem Formular.
     *
     * @since 0.4.0
     *
     * @param string $page_slug Service-Slug (z.B. 'mmb', 'mil', 'tp').
     *
     * @return void
     */
    public function render_page( string $page_slug ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sie haben keine Berechtigung fuer diese Seite.', 'deubner_hp_services' ) );
        }

        $service = DHPS_Service_Registry::get_service( $page_slug );

        if ( null === $service ) {
            wp_die( esc_html__( 'Unbekannter Service.', 'deubner_hp_services' ) );
        }

        // MI-Online hat ein spezielles Template mit 2 Formularen.
        if ( 'mio' === $page_slug ) {
            $this->render_mio_page();
            return;
        }

        // POST-Verarbeitung: Hauptformular.
        $saved = $this->page_handler->save_settings( $page_slug );

        // Template-Daten laden.
        $page_data = $this->page_handler->get_page_data( $page_slug );

        // Variablen fuer das Template bereitstellen.
        $page_title   = $service['admin_title'] ?? $service['name'];
        $service_slug = $page_slug;
        $fields       = $service['admin_fields'];
        $values       = $page_data;
        $nonce_action = DEUBNER_HP_SERVICES_NONCE_ACTION;
        $nonce_field  = 'dhps_nonce';
        $shortcodes   = array( $page_slug );

        // Sibling-Services erkennen (z.B. 'tpt' teilt admin_page mit 'tp').
        $extra_sections = $this->find_sibling_sections( $page_slug, $service );

        // Shortcode-Hint (z.B. Lexplain hat zusaetzlichen Hinweis).
        $shortcode_hint = $service['shortcode_hint'] ?? '';

        // UI/UX Redesign: Zusaetzliche Template-Variablen.
        // @since 0.8.0
        $category = $service['category'] ?? 'steuern';
        $shop_url = $service['shop_url'] ?? 'https://www.deubner-steuern.de/shop/';
        $icon     = $service['icon'] ?? 'dashicons-admin-generic';
        $status   = $this->demo_manager->get_service_status( $page_slug );

        $template_path = $this->get_template_path( 'service-config' );

        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }

    /**
     * Rendert die MI-Online-Spezialseite mit zwei Formularen nebeneinander.
     *
     * @since 0.4.0
     *
     * @return void
     */
    private function render_mio_page(): void {
        $mio_service   = DHPS_Service_Registry::get_service( 'mio' );
        $lxmio_service = DHPS_Service_Registry::get_service( 'lxmio' );

        // POST-Verarbeitung: Steuerrecht-Formular.
        $mio_saved = false;
        if ( isset( $_POST['submit'] ) ) {
            $mio_saved = $this->page_handler->save_mio_form( 'mio' );
        }

        // POST-Verarbeitung: Recht-Formular.
        $lxmio_saved = false;
        if ( isset( $_POST['lxmio_submit'] ) ) {
            $lxmio_saved = $this->page_handler->save_mio_form( 'lxmio' );
        }

        // Aktuelle Werte laden.
        $mio_values   = $this->page_handler->get_mio_form_data( 'mio' );
        $lxmio_values = $this->page_handler->get_mio_form_data( 'lxmio' );

        // Felder aus der Registry.
        $mio_fields   = $mio_service['admin_fields'];
        $lxmio_fields = $lxmio_service['admin_fields'];

        // UI/UX Redesign: Zusaetzliche Template-Variablen.
        // @since 0.8.0
        $mio_category   = $mio_service['category'] ?? 'steuern';
        $mio_shop_url   = $mio_service['shop_url'] ?? 'https://www.deubner-steuern.de/shop/';
        $mio_icon       = $mio_service['icon'] ?? 'dashicons-admin-generic';
        $mio_status     = $this->demo_manager->get_service_status( 'mio' );
        $lxmio_category = $lxmio_service['category'] ?? 'recht';
        $lxmio_shop_url = $lxmio_service['shop_url'] ?? 'https://www.deubner-recht.de/shop/';
        $lxmio_icon     = $lxmio_service['icon'] ?? 'dashicons-admin-generic';
        $lxmio_status   = $this->demo_manager->get_service_status( 'lxmio' );

        $template_path = $this->get_template_path( 'mio-config' );

        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
    }

    /**
     * Findet Sibling-Services, die dieselbe Admin-Seite teilen.
     *
     * Z.B. teilen 'tp' (TaxPlain Videos) und 'tpt' (TaxPlain Teaser)
     * die Admin-Seite 'dhps_tp_page'. Diese Methode findet 'tpt' als
     * Sibling von 'tp' und bereitet es als Extra-Section auf.
     *
     * @since 0.4.0
     *
     * @param string $page_slug Haupt-Service-Slug.
     * @param array  $service   Service-Definition des Haupt-Service.
     *
     * @return array Array der Extra-Sections mit Feldern, Werten und Speicher-Status.
     */
    private function find_sibling_sections( string $page_slug, array $service ): array {
        $sections     = array();
        $all_services = DHPS_Service_Registry::get_services();
        $admin_page   = $service['admin_page'] ?? '';

        if ( empty( $admin_page ) ) {
            return $sections;
        }

        foreach ( $all_services as $sc_name => $sc_def ) {
            // Nur Siblings (gleiche admin_page, aber anderer Shortcode-Name).
            if ( $sc_name === $page_slug ) {
                continue;
            }

            if ( ! isset( $sc_def['admin_page'] ) || $sc_def['admin_page'] !== $admin_page ) {
                continue;
            }

            // Nonce-Feld und Submit-Name fuer Sibling-Formulare.
            $nonce_field = 'dhps_' . $sc_name . '_nonce';
            $submit_name = $sc_name . '_submit';

            // POST-Verarbeitung fuer diesen Sibling.
            $sibling_saved = false;
            if ( isset( $_POST[ $submit_name ] ) ) {
                $sibling_saved = $this->page_handler->save_sibling_form( $sc_name, $nonce_field );
            }

            // Aktuelle Werte laden.
            $section_values = array();
            foreach ( $sc_def['admin_fields'] as $field ) {
                $section_values[ $field['field_name'] ] = get_option( $field['option_key'], '' );
            }

            $sections[] = array(
                'title'       => $sc_def['admin_title'] ?? $sc_def['name'],
                'shortcodes'  => array( $sc_name ),
                'nonce_field' => $nonce_field,
                'submit_name' => $submit_name,
                'fields'      => $sc_def['admin_fields'],
                'values'      => $section_values,
                'saved'       => $sibling_saved,
                'description' => '',
            );
        }

        return $sections;
    }

    /**
     * AJAX-Handler fuer Demo-Modus-Toggle.
     *
     * Verarbeitet AJAX-Requests zum Aktivieren/Deaktivieren des Demo-Modus
     * fuer einen einzelnen Service. Prueft Nonce und Capability.
     *
     * @since 0.6.0
     *
     * @return void (sendet JSON-Response und terminiert)
     */
    public function handle_demo_toggle(): void {
        // Nonce pruefen.
        if ( ! check_ajax_referer( 'dhps_demo_toggle', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Sicherheitspruefung fehlgeschlagen.' ) );
        }

        // Capability pruefen.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        }

        $service     = isset( $_POST['service'] ) ? sanitize_text_field( wp_unslash( $_POST['service'] ) ) : '';
        $action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';

        if ( empty( $service ) || empty( $action_type ) ) {
            wp_send_json_error( array( 'message' => 'Fehlende Parameter.' ) );
        }

        if ( 'activate' === $action_type ) {
            $result = $this->demo_manager->activate_demo( $service );

            if ( $result ) {
                wp_send_json_success( array( 'message' => 'Demo aktiviert.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Demo konnte nicht aktiviert werden.' ) );
            }
        } elseif ( 'deactivate' === $action_type ) {
            $this->demo_manager->deactivate_demo( $service );
            wp_send_json_success( array( 'message' => 'Demo deaktiviert.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Unbekannte Aktion.' ) );
        }
    }

    /**
     * Gibt den absoluten Pfad zu einem Template zurueck.
     *
     * @since 0.4.0
     *
     * @param string $template Template-Name ohne .php Endung.
     *
     * @return string Absoluter Pfad zum Template.
     */
    private function get_template_path( string $template ): string {
        return plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/' . $template . '.php';
    }

    /**
     * Prueft, ob eine Screen-ID zu einer DHPS-Admin-Seite gehoert.
     *
     * WordPress generiert Screen-IDs im Format "deubner-verlag_page_{slug}".
     * Diese Methode prueft, ob der Slug-Teil der Screen-ID in der
     * Plugin-Pages-Liste enthalten ist.
     *
     * @since 0.4.0
     *
     * @param string $screen_id Aktuelle Screen-ID.
     *
     * @return bool True wenn es sich um eine DHPS-Seite handelt.
     */
    private function is_dhps_page( string $screen_id ): bool {
        foreach ( self::$plugin_pages as $page_slug ) {
            if ( false !== strpos( $screen_id, $page_slug ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gibt das SVG-Icon fuer das Admin-Menue zurueck.
     *
     * Das Icon wird inline als SVG-String bereitgestellt und im
     * register_menu() als base64-kodierte data-URI verwendet.
     *
     * @since 0.4.0
     *
     * @return string SVG-Markup fuer das Deubner-Verlag-Icon.
     */
    private function get_menu_icon_svg(): string {
        return '<svg xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="svg3409" x="0px" y="0px" viewBox="0 0 240.3 156.2" style="enable-background:new 0 0 240.3 156.2;" xml:space="preserve"><g id="layer1" transform="translate(-78.140625,-446.91352)"> <path fill="currentColor" d="M301.9,446.9c-9.1,0-16.5,7.4-16.5,16.5s7.4,16.5,16.5,16.5c9.1,0,16.5-7.4,16.5-16.5 S311,446.9,301.9,446.9z M138.9,447.7c-6.6,0-13.3,6.7-13.3,13.3c0,2.4,0,69.4,0,94.8c0,0,46.9,0,46.9,0v-61.1h48.2 c6.6,0,13.3,6.7,13.3,13.4v34.4c0,6.6-6.7,13.3-13.3,13.3l-48.2-0.1c0,0,0,47.3,0,47.4h39.8c40.7,0,74-22.9,74-79.5 c0-62.6-49-75.9-73.9-75.9L138.9,447.7L138.9,447.7z M125.6,555.8H78.1v36.5c0,5.4,5.4,10.8,10.8,10.8h36.6V555.8z M301.9,449.2 c7.9,0,13.9,6.3,13.9,14.3s-5.9,14.3-13.9,14.3s-13.9-6.3-13.9-14.3S294,449.2,301.9,449.2z M295.7,453.7v19.6h2.7v-8.7h3.8 l5.3,8.7h3l-5.5-8.7c2.3-0.5,4.5-2,4.5-5.5c0-3.5-2-5.4-6.4-5.4H295.7z M298.4,455.9h4.3c2.1,0,4.3,0.4,4.3,3.2 c0,3.4-2.9,3.3-5.5,3.3h-3.1V455.9z"></path> </g> </svg>';
    }

    /**
     * Gibt die Liste der Plugin-Seiten-Slugs zurueck.
     *
     * @since 0.4.0
     *
     * @return array Plugin-Seiten-Slugs.
     */
    public static function get_plugin_pages(): array {
        return self::$plugin_pages;
    }
}
