<?php
/**
 * Cache-Schicht fuer den Deubner Homepage Service.
 *
 * Kapselt die WordPress Transients API in einer eigenstaendigen Klasse.
 * Bietet deterministische Cache-Key-Generierung, CRUD-Operationen
 * und einen selektiven Flush aller Plugin-spezifischen Transients.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes/Cache
 * @since      0.4.0
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class DHPS_Cache
 *
 * Wrapper um die WordPress Transients API, der alle Caching-Operationen
 * des Plugins zentralisiert. Alle Transient-Keys erhalten automatisch
 * das Prefix 'dhps_', um Kollisionen mit anderen Plugins zu vermeiden.
 *
 * @since   0.4.0
 * @package Deubner Homepage-Service
 */
class DHPS_Cache {

    /**
     * Prefix fuer alle Transient-Keys dieses Plugins.
     *
     * @since 0.4.0
     * @var string
     */
    private const KEY_PREFIX = 'dhps_';

    /**
     * Standard-TTL fuer Cache-Eintraege in Sekunden (1 Stunde).
     *
     * @since 0.4.0
     * @var int
     */
    private const DEFAULT_TTL = 3600;

    /**
     * Liest einen Wert aus dem Cache.
     *
     * @since 0.4.0
     *
     * @param string $key Cache-Key (bereits mit Prefix versehen).
     *
     * @return string|null Gespeicherter Wert oder null wenn nicht vorhanden/abgelaufen.
     */
    public function get( string $key ): ?string {
        $value = get_transient( $key );

        if ( false === $value ) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Speichert einen Wert im Cache.
     *
     * @since 0.4.0
     *
     * @param string $key   Cache-Key (bereits mit Prefix versehen).
     * @param string $value Zu speichernder Wert.
     * @param int    $ttl   Time-To-Live in Sekunden. Standard: 3600 (1 Stunde).
     *
     * @return bool True bei Erfolg, false bei Fehler.
     */
    public function set( string $key, string $value, int $ttl = self::DEFAULT_TTL ): bool {
        return set_transient( $key, $value, $ttl );
    }

    /**
     * Loescht einen einzelnen Cache-Eintrag.
     *
     * @since 0.4.0
     *
     * @param string $key Cache-Key (bereits mit Prefix versehen).
     *
     * @return bool True bei Erfolg, false wenn der Key nicht existierte.
     */
    public function delete( string $key ): bool {
        return delete_transient( $key );
    }

    /**
     * Loescht alle DHPS-Transients aus der Datenbank.
     *
     * Entfernt sowohl die Transient-Werte als auch deren Timeout-Eintraege.
     * Nutzt einen direkten Datenbank-Query, da WordPress keine native
     * Moeglichkeit zum Massen-Loeschen von Transients nach Prefix bietet.
     *
     * @since 0.4.0
     *
     * @return void
     */
    public function flush(): void {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . self::KEY_PREFIX . '%',
                '_transient_timeout_' . self::KEY_PREFIX . '%'
            )
        );
    }

    /**
     * Liest ein serialisiertes Array aus dem Cache.
     *
     * Wie get(), aber deserialisiert den Wert mit maybe_unserialize().
     * Wird fuer L2-Cache (geparste Daten) verwendet.
     *
     * @since 0.9.0
     *
     * @param string $key Cache-Key (bereits mit Prefix versehen).
     *
     * @return array|null Gespeichertes Array oder null wenn nicht vorhanden/abgelaufen.
     */
    public function get_data( string $key ): ?array {
        $value = get_transient( $key );

        if ( false === $value ) {
            return null;
        }

        $data = maybe_unserialize( $value );

        return is_array( $data ) ? $data : null;
    }

    /**
     * Speichert ein Array serialisiert im Cache.
     *
     * Wie set(), aber serialisiert den Wert mit maybe_serialize().
     * Wird fuer L2-Cache (geparste Daten) verwendet.
     *
     * @since 0.9.0
     *
     * @param string $key   Cache-Key (bereits mit Prefix versehen).
     * @param array  $data  Zu speicherndes Array.
     * @param int    $ttl   Time-To-Live in Sekunden. Standard: 3600 (1 Stunde).
     *
     * @return bool True bei Erfolg, false bei Fehler.
     */
    public function set_data( string $key, array $data, int $ttl = self::DEFAULT_TTL ): bool {
        return set_transient( $key, maybe_serialize( $data ), $ttl );
    }

    /**
     * Generiert einen deterministischen Cache-Key aus Endpoint und Parametern.
     *
     * Der Key wird aus einem MD5-Hash der Kombination von Endpoint und
     * sortierten Parametern erzeugt und mit dem Plugin-Prefix versehen.
     * Identische Eingaben erzeugen garantiert denselben Key.
     *
     * @since 0.4.0
     *
     * @param string $endpoint API-Endpoint-Pfad.
     * @param array  $params   Assoziatives Array der Query-Parameter.
     *
     * @return string Deterministischer Cache-Key im Format 'dhps_{md5}'.
     */
    public function generate_key( string $endpoint, array $params = [] ): string {
        // Parameter sortieren fuer deterministische Key-Erzeugung.
        ksort( $params );

        $raw = $endpoint . '|' . wp_json_encode( $params );

        return self::KEY_PREFIX . md5( $raw );
    }
}
