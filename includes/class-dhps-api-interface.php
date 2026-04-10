<?php
/**
 * API-Interface fuer den Deubner Homepage Service.
 *
 * Definiert den Vertrag, den jede API-Implementierung erfuellen muss.
 * Ermoeglicht den Austausch der konkreten API-Anbindung (Legacy-HTML, zukuenftig JSON)
 * ohne Aenderungen am aufrufenden Code.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/API
 * @since      0.4.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Interface DHPS_API_Interface
 *
 * Vertrag fuer alle API-Implementierungen des Deubner Homepage Service Plugins.
 * Jede konkrete Klasse (Legacy-HTML-API, zukuenftige JSON-API) muss dieses
 * Interface implementieren, um ueber den DHPS_API_Client austauschbar zu sein.
 *
 * @since   0.4.0
 * @package Deubner Homepage-Service
 */
interface DHPS_API_Interface {

    /**
     * Ruft Inhalte von einem API-Endpoint ab.
     *
     * @since 0.4.0
     *
     * @param string $endpoint Relativer API-Pfad (z.B. 'einbau/mio/bin/php_inhalt.php').
     * @param array  $params   Optionales assoziatives Array der Query-Parameter.
     *
     * @return DHPS_API_Response Response-Objekt mit Erfolgs-/Fehlerstatus und Body.
     */
    public function fetch( string $endpoint, array $params = [] ): DHPS_API_Response;

    /**
     * Gibt die Basis-URL der API zurueck.
     *
     * @since 0.4.0
     *
     * @return string Vollstaendige Basis-URL (inkl. abschliessendem Slash).
     */
    public function get_base_url(): string;

    /**
     * Prueft, ob die API aktuell erreichbar ist.
     *
     * Fuehrt einen leichtgewichtigen Request (z.B. HEAD) durch,
     * um die Verfuegbarkeit der API zu testen.
     *
     * @since 0.4.0
     *
     * @return bool True wenn die API erreichbar ist, sonst false.
     */
    public function is_available(): bool;
}
