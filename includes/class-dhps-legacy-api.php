<?php
/**
 * Legacy-API-Implementierung fuer den Deubner Homepage Service.
 *
 * Konkrete Implementierung des DHPS_API_Interface fuer die bestehende
 * Deubner-HTML-API. Nutzt WordPress' HTTP-API (wp_remote_get) zum Abruf
 * von HTML-Fragmenten von deubner-online.de.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/API
 * @since      0.4.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class DHPS_Legacy_API
 *
 * Implementiert den API-Zugriff auf die bestehende Deubner-HTML-API.
 * Diese Klasse extrahiert die bisherige fetch_remote_content()-Logik
 * aus der Shortcode-Klasse in eine eigenstaendige, austauschbare Schicht.
 *
 * @since   0.4.0
 * @package Deubner Homepage-Service
 */
class DHPS_Legacy_API implements DHPS_API_Interface {

    /**
     * Basis-URL der Deubner-API.
     *
     * @since 0.4.0
     * @var string
     */
    private string $base_url;

    /**
     * Timeout fuer HTTP-Requests in Sekunden.
     *
     * @since 0.4.0
     * @var int
     */
    private int $timeout;

    /**
     * Konstruktor.
     *
     * @since 0.4.0
     *
     * @param string $base_url Optionale Basis-URL. Standard: DEUBNER_HP_SERVICES_API_BASE.
     * @param int    $timeout  Optionaler Timeout in Sekunden. Standard: 30.
     */
    public function __construct( string $base_url = '', int $timeout = 30 ) {
        $this->base_url = $base_url !== ''
            ? trailingslashit( $base_url )
            : trailingslashit( DEUBNER_HP_SERVICES_API_BASE );

        $this->timeout = $timeout;
    }

    /**
     * Ruft Inhalte von der Legacy-HTML-API ab.
     *
     * Baut die vollstaendige URL aus Basis-URL, Endpoint und Query-Parametern zusammen.
     * Leere Parameter werden vor dem Aufruf herausgefiltert.
     *
     * @since 0.4.0
     *
     * @param string $endpoint Relativer API-Pfad (z.B. 'einbau/mio/bin/php_inhalt.php').
     * @param array  $params   Optionales assoziatives Array der Query-Parameter.
     *
     * @return DHPS_API_Response Response-Objekt mit HTML-Body oder Fehlermeldung.
     */
    public function fetch( string $endpoint, array $params = [] ): DHPS_API_Response {
        // Leere und null-Parameter herausfiltern.
        $params = array_filter( $params, static function ( $value ) {
            return $value !== '' && $value !== null;
        } );

        // URL sicher zusammenbauen.
        $url = $this->base_url . ltrim( $endpoint, '/' );

        if ( ! empty( $params ) ) {
            $url = add_query_arg( array_map( 'urlencode', $params ), $url );
        }

        // WordPress HTTP-API nutzen.
        $response = wp_remote_get( $url, array(
            'timeout'   => $this->timeout,
            'sslverify' => true,
        ) );

        // WP_Error pruefen (Netzwerkfehler, DNS-Fehler, Timeout, etc.).
        if ( is_wp_error( $response ) ) {
            return DHPS_API_Response::error(
                sprintf(
                    'Fehler beim Laden der Inhalte: %s',
                    $response->get_error_message()
                )
            );
        }

        // HTTP-Statuscode auswerten.
        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        if ( $status_code < 200 || $status_code >= 300 ) {
            return DHPS_API_Response::error(
                sprintf( 'API-Fehler (HTTP %d)', $status_code ),
                $status_code
            );
        }

        return DHPS_API_Response::success( $body, $status_code );
    }

    /**
     * Gibt die Basis-URL der Legacy-API zurueck.
     *
     * @since 0.4.0
     *
     * @return string Basis-URL mit abschliessendem Slash.
     */
    public function get_base_url(): string {
        return $this->base_url;
    }

    /**
     * Prueft die Erreichbarkeit der Legacy-API via HEAD-Request.
     *
     * Fuehrt einen leichtgewichtigen HEAD-Request mit kurzem Timeout durch,
     * um festzustellen, ob die API grundsaetzlich erreichbar ist.
     *
     * @since 0.4.0
     *
     * @return bool True wenn die API erreichbar ist (HTTP 2xx), sonst false.
     */
    public function is_available(): bool {
        $response = wp_remote_head( $this->base_url, array(
            'timeout'   => 5,
            'sslverify' => true,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );

        return $status_code >= 200 && $status_code < 400;
    }
}
