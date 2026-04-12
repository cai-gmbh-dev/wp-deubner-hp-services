<?php
/**
 * Steuertermine Standalone-Shortcode [mio_termine].
 *
 * Nutzt die bestehende Content-Pipeline (API-Client, Cache, MIO-Parser),
 * rendert aber ausschliesslich die Steuertermine-Sektion.
 *
 * @package Deubner Homepage-Service
 * @since   0.9.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DHPS_Steuertermine
 *
 * Registriert den [mio_termine] Shortcode und rendert Steuertermine
 * in verschiedenen Layout-Varianten (default, card, inline, compact).
 *
 * @since 0.9.8
 */
class DHPS_Steuertermine {

    /**
     * API-Client fuer Service-Anfragen.
     *
     * @var DHPS_API_Client
     */
    private DHPS_API_Client $client;

    /**
     * Cache-Instanz fuer L2-Caching der geparsten Daten.
     *
     * @var DHPS_Cache
     */
    private DHPS_Cache $cache;

    /**
     * Konstruktor: Injiziert Abhaengigkeiten und registriert den Shortcode.
     *
     * @since 0.9.8
     *
     * @param DHPS_API_Client $client API-Client-Instanz.
     * @param DHPS_Cache      $cache  Cache-Instanz.
     */
    public function __construct( DHPS_API_Client $client, DHPS_Cache $cache ) {
        $this->client = $client;
        $this->cache  = $cache;
        add_shortcode( 'mio_termine', array( $this, 'render' ) );
    }

    /**
     * Rendert den [mio_termine] Shortcode.
     *
     * @since 0.9.8
     *
     * @param array|string $atts Shortcode-Attribute.
     * @return string HTML-Ausgabe.
     */
    public function render( $atts ): string {
        $atts = shortcode_atts( array(
            'count'  => '0',       // 0=alle Eintraege, N=erste N Eintraege pro Monat.
            'month'  => 'all',     // current|next|all
            'layout' => 'default', // default|card|inline|compact
            'class'  => '',
            'cache'  => '3600',
        ), $atts, 'mio_termine' );

        // MIO-Service-Konfiguration holen.
        $service = DHPS_Service_Registry::get_service( 'mio' );
        if ( null === $service ) {
            return '';
        }

        // OTA-Token pruefen.
        $ota = get_option( $service['auth_option'], '' );
        if ( empty( $ota ) ) {
            return '<!-- DHPS: MIO nicht konfiguriert -->';
        }

        $params = array( 'ota' => $ota, 'modus' => 'p' );

        // L2-Cache fuer geparste Daten pruefen.
        $cache_key = 'dhps_p_' . md5( $service['endpoint'] . '|' . wp_json_encode( $params ) );
        $parsed    = $this->cache->get_data( $cache_key );

        if ( null === $parsed ) {
            $html = $this->client->fetch_content( $service['endpoint'], $params, absint( $atts['cache'] ) );
            if ( empty( $html ) || 0 === strpos( trim( $html ), '<!-- DHPS:' ) ) {
                return $html;
            }

            $parser = DHPS_Parser_Registry::get_parser( 'mio' );
            if ( null === $parser ) {
                return '';
            }

            $parsed                = $parser->parse( $html );
            $parsed['service_tag'] = 'mio';
            $this->cache->set_data( $cache_key, $parsed, absint( $atts['cache'] ) );
        }

        $tax_dates = $parsed['tax_dates'] ?? array();
        if ( empty( $tax_dates ) ) {
            return '';
        }

        // Nach Monat filtern.
        $month_filter = sanitize_key( $atts['month'] );
        if ( 'current' === $month_filter && isset( $tax_dates[0] ) ) {
            $tax_dates = array( $tax_dates[0] );
        } elseif ( 'next' === $month_filter && isset( $tax_dates[1] ) ) {
            $tax_dates = array( $tax_dates[1] );
        }
        // 'all' behaelt alle Monate.

        // Eintraege pro Monat begrenzen.
        $count = absint( $atts['count'] );
        if ( $count > 0 ) {
            foreach ( $tax_dates as &$month ) {
                $month['entries'] = array_slice( $month['entries'], 0, $count );
            }
            unset( $month );
        }

        // Template rendern.
        $layout    = sanitize_key( $atts['layout'] );
        $css_class = sanitize_html_class( $atts['class'] );

        return $this->render_template( $tax_dates, $layout, $css_class );
    }

    /**
     * Laedt und rendert das Template fuer die Steuertermine.
     *
     * Template-Suche: Theme-Override -> Plugin-Template -> Default-Fallback.
     *
     * @since 0.9.8
     *
     * @param array  $tax_dates  Array der Monats-Daten mit Eintraegen.
     * @param string $layout     Layout-Variante (default|card|inline|compact).
     * @param string $css_class  Zusaetzliche CSS-Klasse.
     * @return string HTML-Ausgabe.
     */
    private function render_template( array $tax_dates, string $layout, string $css_class ): string {
        $safe_layout = sanitize_file_name( $layout );

        // Theme-Override pruefen.
        $template = get_stylesheet_directory() . '/dhps/steuertermine/' . $safe_layout . '.php';
        if ( ! file_exists( $template ) ) {
            $template = DEUBNER_HP_SERVICES_PATH . 'public/views/steuertermine/' . $safe_layout . '.php';
        }
        if ( ! file_exists( $template ) ) {
            $template = DEUBNER_HP_SERVICES_PATH . 'public/views/steuertermine/default.php';
        }
        if ( ! file_exists( $template ) ) {
            return '';
        }

        $data         = $tax_dates;
        $custom_class = ! empty( $css_class ) ? ' ' . $css_class : '';

        ob_start();
        include $template;
        return ob_get_clean();
    }
}
