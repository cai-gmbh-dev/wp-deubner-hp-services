<?php
/**
 * API-Client-Fassade fuer den Deubner Homepage Service.
 *
 * Vereint API-Zugriff und Caching in einer einzigen, einfach nutzbaren Klasse.
 * Dient als zentrale Anlaufstelle fuer alle API-Aufrufe des Plugins und
 * implementiert das Cache-Aside-Pattern (Cache pruefen, bei Miss von API laden,
 * Ergebnis cachen).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/API
 * @since      0.4.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class DHPS_API_Client
 *
 * Fassade, die eine API-Implementierung (DHPS_API_Interface) mit dem
 * Cache (DHPS_Cache) kombiniert. Shortcodes und andere Konsumenten
 * nutzen ausschliesslich diese Klasse zum Abruf von Inhalten.
 *
 * Durch die Injection des API-Interface im Konstruktor kann die konkrete
 * API-Implementierung jederzeit ausgetauscht werden (z.B. von Legacy-HTML
 * auf eine zukuenftige JSON-API), ohne den aufrufenden Code zu aendern.
 *
 * @since   0.4.0
 * @package Deubner Homepage-Service
 */
class DHPS_API_Client {

    /**
     * API-Implementierung fuer den Datenabruf.
     *
     * @since 0.4.0
     * @var DHPS_API_Interface
     */
    private DHPS_API_Interface $api;

    /**
     * Cache-Instanz fuer die Zwischenspeicherung.
     *
     * @since 0.4.0
     * @var DHPS_Cache
     */
    private DHPS_Cache $cache;

    /**
     * Konstruktor.
     *
     * @since 0.4.0
     *
     * @param DHPS_API_Interface $api   Konkrete API-Implementierung.
     * @param DHPS_Cache         $cache Cache-Instanz.
     */
    public function __construct( DHPS_API_Interface $api, DHPS_Cache $cache ) {
        $this->api   = $api;
        $this->cache = $cache;
    }

    /**
     * Ruft Inhalte von der API ab, mit transparentem Caching.
     *
     * Ablauf (Cache-Aside-Pattern):
     * 1. Cache-Key aus Endpoint und Params generieren
     * 2. Cache pruefen - bei Hit sofort zurueckgeben
     * 3. Bei Cache-Miss: API aufrufen
     * 4. Bei API-Erfolg: Ergebnis cachen und zurueckgeben
     * 5. Bei API-Fehler: HTML-Kommentar zurueckgeben (kein Caching von Fehlern)
     *
     * @since 0.4.0
     *
     * @param string $endpoint  Relativer API-Pfad (z.B. 'einbau/mio/bin/php_inhalt.php').
     * @param array  $params    Optionales assoziatives Array der Query-Parameter.
     * @param int    $cache_ttl Cache-Dauer in Sekunden. Standard: 3600 (1 Stunde).
     *
     * @return string HTML-Inhalt oder HTML-Fehlerkommentar.
     */
    public function fetch_content( string $endpoint, array $params = [], int $cache_ttl = 3600 ): string {
        // 1. Deterministischen Cache-Key generieren.
        $cache_key = $this->cache->generate_key( $endpoint, $params );

        // 2. Cache pruefen.
        $cached = $this->cache->get( $cache_key );

        if ( null !== $cached ) {
            return $cached;
        }

        // 3. Bei Cache-Miss: API aufrufen.
        $response = $this->api->fetch( $endpoint, $params );

        // 4. Ergebnis auswerten.
        if ( $response->is_success() ) {
            $html = $response->get_body();

            // Nur nicht-leere Erfolgsantworten cachen.
            if ( '' !== $html ) {
                $this->cache->set( $cache_key, $html, $cache_ttl );
            }

            return $html;
        }

        // 5. Bei Fehler: HTML-Kommentar zurueckgeben (Fehler werden nicht gecacht).
        return $response->get_html();
    }

    /**
     * Loescht den gesamten Plugin-Cache.
     *
     * Delegiert an die flush()-Methode der Cache-Instanz,
     * um alle DHPS-Transients zu entfernen.
     *
     * @since 0.4.0
     *
     * @return void
     */
    public function flush_cache(): void {
        $this->cache->flush();
    }
}
