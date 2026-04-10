<?php
/**
 * API-Response Value-Object fuer den Deubner Homepage Service.
 *
 * Kapselt das Ergebnis eines API-Aufrufs in einem unveraenderlichen Objekt.
 * Bietet statische Factory-Methoden fuer Erfolgs- und Fehler-Responses
 * sowie einen einheitlichen Zugriff auf den HTML-Inhalt.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/API
 * @since      0.4.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class DHPS_API_Response
 *
 * Immutables Value-Object, das das Ergebnis eines API-Aufrufs repraesentiert.
 * Wird von allen API-Implementierungen zurueckgegeben und vom API-Client
 * weiterverarbeitet.
 *
 * @since   0.4.0
 * @package Deubner Homepage-Service
 */
class DHPS_API_Response {

    /**
     * Ob der API-Aufruf erfolgreich war.
     *
     * @since 0.4.0
     * @var bool
     */
    private bool $success;

    /**
     * Response-Body (HTML-Fragment oder leerer String bei Fehler).
     *
     * @since 0.4.0
     * @var string
     */
    private string $body;

    /**
     * HTTP-Statuscode der Antwort (0 bei Verbindungsfehlern).
     *
     * @since 0.4.0
     * @var int
     */
    private int $status_code;

    /**
     * Fehlermeldung (leer bei erfolgreicher Response).
     *
     * @since 0.4.0
     * @var string
     */
    private string $error_message;

    /**
     * Privater Konstruktor - Instanziierung nur ueber Factory-Methoden.
     *
     * @since 0.4.0
     *
     * @param bool   $success       Ob der Aufruf erfolgreich war.
     * @param string $body          Response-Body.
     * @param int    $status_code   HTTP-Statuscode.
     * @param string $error_message Fehlermeldung (leer bei Erfolg).
     */
    private function __construct( bool $success, string $body, int $status_code, string $error_message ) {
        $this->success       = $success;
        $this->body          = $body;
        $this->status_code   = $status_code;
        $this->error_message = $error_message;
    }

    /**
     * Erstellt eine erfolgreiche API-Response.
     *
     * @since 0.4.0
     *
     * @param string $body        Der Response-Body (HTML-Fragment).
     * @param int    $status_code HTTP-Statuscode (Standard: 200).
     *
     * @return self Neue DHPS_API_Response-Instanz mit Erfolgsstatus.
     */
    public static function success( string $body, int $status_code = 200 ): self {
        return new self( true, $body, $status_code, '' );
    }

    /**
     * Erstellt eine Fehler-API-Response.
     *
     * @since 0.4.0
     *
     * @param string $message     Beschreibende Fehlermeldung.
     * @param int    $status_code HTTP-Statuscode (Standard: 0 fuer Verbindungsfehler).
     *
     * @return self Neue DHPS_API_Response-Instanz mit Fehlerstatus.
     */
    public static function error( string $message, int $status_code = 0 ): self {
        return new self( false, '', $status_code, $message );
    }

    /**
     * Prueft, ob der API-Aufruf erfolgreich war.
     *
     * @since 0.4.0
     *
     * @return bool True bei Erfolg, false bei Fehler.
     */
    public function is_success(): bool {
        return $this->success;
    }

    /**
     * Gibt den Response-Body zurueck.
     *
     * @since 0.4.0
     *
     * @return string Response-Body (leer bei Fehler).
     */
    public function get_body(): string {
        return $this->body;
    }

    /**
     * Gibt den HTTP-Statuscode zurueck.
     *
     * @since 0.4.0
     *
     * @return int HTTP-Statuscode (0 bei Verbindungsfehlern).
     */
    public function get_status_code(): int {
        return $this->status_code;
    }

    /**
     * Gibt die Fehlermeldung zurueck.
     *
     * @since 0.4.0
     *
     * @return string Fehlermeldung (leer bei Erfolg).
     */
    public function get_error_message(): string {
        return $this->error_message;
    }

    /**
     * Gibt den HTML-Inhalt zurueck.
     *
     * Bei einer erfolgreichen Response wird der Body zurueckgegeben.
     * Bei einem Fehler wird ein HTML-Kommentar mit der Fehlermeldung generiert,
     * sodass der Fehler im Quelltext sichtbar, aber fuer den Besucher unsichtbar ist.
     *
     * @since 0.4.0
     *
     * @return string HTML-Body oder HTML-Fehlerkommentar.
     */
    public function get_html(): string {
        if ( $this->success ) {
            return $this->body;
        }

        return '<!-- DHPS: ' . esc_html( $this->error_message ) . ' -->';
    }
}
