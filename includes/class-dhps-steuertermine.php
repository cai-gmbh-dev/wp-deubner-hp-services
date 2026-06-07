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
     * Filter-Atts die den Adapter-Pfad umgehen und Force-Legacy triggern.
     *
     * Bei Sub-Shortcode-Aufruf mit aktivem `month=current|next` oder `count>0`
     * wird der Item-Set vor dem Template gefiltert. Der Adapter sieht das
     * gefilterte Array, aber die Collection-Items-Reihenfolge spiegelt nicht
     * die Filter-Semantik. Daher: Force-Legacy schuetzt vor Drift.
     *
     * Analog `DHPS_MAES_Modules::FORCE_LEGACY_ATTS` (v0.17.1).
     *
     * @since 0.17.5
     */
    private const FORCE_LEGACY_ATTS = array( 'month', 'count' );

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

        // Collection-Build (v0.17.5 TD-V0173-1): nur wenn keine Filter-Atts
        // aktiv sind. Force-Legacy bei month!=all oder count>0 schuetzt vor
        // Drift zwischen gefilterter Item-Liste und Collection-Items.
        $collection = $this->get_collection( $atts, $parsed );

        // Template rendern.
        $layout    = sanitize_key( $atts['layout'] );
        $css_class = sanitize_html_class( $atts['class'] );

        return $this->render_template( $tax_dates, $layout, $css_class, $collection );
    }

    /**
     * Liefert eine DHPS_Content_Collection fuer den Sub-Shortcode-Aufruf,
     * sofern keine Filter-Atts aktiv sind. Sonst null (Force-Legacy).
     *
     * Force-Legacy-Logik analog `DHPS_MAES_Modules::get_collection` aus v0.17.1:
     *
     * - `month` != 'all' (z.B. 'current'/'next') -> null
     * - `count` > 0 -> null
     * - Sonst: Helper `dhps_build_collection_for('mio', $parsed_data)`
     *
     * @since 0.17.5
     *
     * @param array $atts        Bereits per `shortcode_atts` aufgeloeste Atts.
     * @param array $parsed_data Geparste MIO-Daten (mit `tax_dates`-Schluessel).
     *
     * @return DHPS_Content_Collection|null Collection wenn Adapter da + keine Filter-Atts.
     */
    private function get_collection( array $atts, array $parsed_data ): ?DHPS_Content_Collection {
        foreach ( self::FORCE_LEGACY_ATTS as $att_name ) {
            if ( ! isset( $atts[ $att_name ] ) ) {
                continue;
            }
            $raw = trim( (string) $atts[ $att_name ] );
            // Default-Werte gelten als "nicht gesetzt":
            // month-Default 'all', count-Default '0' bzw. ''.
            if ( 'month' === $att_name && ( '' === $raw || 'all' === $raw ) ) {
                continue;
            }
            if ( 'count' === $att_name && ( '' === $raw || '0' === $raw ) ) {
                continue;
            }
            return null;
        }

        if ( ! function_exists( 'dhps_build_collection_for' ) ) {
            return null;
        }
        return dhps_build_collection_for( 'mio', $parsed_data );
    }

    /**
     * Laedt und rendert das Template fuer die Steuertermine.
     *
     * Template-Suche: Theme-Override -> Plugin-Template -> Default-Fallback.
     *
     * @since 0.9.8
     *
     * @since 0.17.5 Optionaler Parameter `$collection` fuer Adapter-Bridge.
     *
     * @param array                         $tax_dates  Array der Monats-Daten mit Eintraegen.
     * @param string                        $layout     Layout-Variante (default|card|inline|compact).
     * @param string                        $css_class  Zusaetzliche CSS-Klasse.
     * @param DHPS_Content_Collection|null  $collection Adapter-Collection (null = Force-Legacy).
     *
     * @return string HTML-Ausgabe.
     */
    private function render_template( array $tax_dates, string $layout, string $css_class, ?DHPS_Content_Collection $collection = null ): string {
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
        // $collection wird in den Template-Scope mit gereicht (kann null sein).

        ob_start();
        include $template;
        return ob_get_clean();
    }
}
