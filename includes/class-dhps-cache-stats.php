<?php
/**
 * Cache-Statistik fuer das Admin-Dashboard.
 *
 * Liest das Transient-Inventar des Plugins (Prefix dhps_) direkt aus
 * wp_options aus und liefert aggregierte Metriken:
 *   - Anzahl Transients
 *   - Gesamt-Bytes
 *   - Aelteste Eintrag (in Sekunden)
 *   - Naechste TTL-Expiry (in Sekunden)
 *
 * Limitation v0.15.0: Stats sind PLUGIN-GLOBAL, nicht pro Service.
 * Cache-Keys folgen aktuell dem Schema dhps_{md5(endpoint|params)} und
 * lassen sich nicht eindeutig einem Service zuordnen. Eine Service-
 * Aufschluesselung wuerde eine BC-breaking Aenderung am Cache-Key-Schema
 * erfordern (vorgesehen fuer v0.15.1+).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Cache_Stats
 *
 * @since 0.15.0
 */
class DHPS_Cache_Stats {

	/**
	 * Gemeinsamer Prefix aller Plugin-Transients.
	 *
	 * @since 0.15.0
	 * @var string
	 */
	private const KEY_PREFIX = 'dhps_';

	/**
	 * Liefert aggregierte Cache-Statistik (plugin-global).
	 *
	 * @since 0.15.0
	 *
	 * @return array<string,int> Schema:
	 *   total_transients         => int
	 *   total_size_bytes         => int
	 *   oldest_transient_age_sec => int (0 wenn unbekannt)
	 *   next_expiry_in_sec       => int (0 wenn unbekannt)
	 *   checked_at               => int (Unix-Timestamp)
	 */
	public function collect(): array {
		global $wpdb;

		$now             = time();
		$count           = 0;
		$total_size      = 0;
		$oldest_age      = 0;
		$next_expiry     = PHP_INT_MAX;
		$oldest_expiry   = PHP_INT_MAX; // groesster "verbleibende Lebenszeit"-Wert, zur Altersbestimmung

		$prefix_value   = '_transient_' . self::KEY_PREFIX . '%';
		$prefix_timeout = '_transient_timeout_' . self::KEY_PREFIX . '%';

		// Eine Query holt beide Familien (Value-Row + Timeout-Row).
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, LENGTH(option_value) AS bytes
				 FROM {$wpdb->options}
				 WHERE option_name LIKE %s OR option_name LIKE %s",
				$prefix_value,
				$prefix_timeout
			)
		);

		if ( ! is_array( $rows ) ) {
			return array(
				'total_transients'         => 0,
				'total_size_bytes'         => 0,
				'oldest_transient_age_sec' => 0,
				'next_expiry_in_sec'       => 0,
				'checked_at'               => $now,
			);
		}

		foreach ( $rows as $row ) {
			$name = isset( $row->option_name ) ? (string) $row->option_name : '';

			if ( 0 === strpos( $name, '_transient_timeout_' ) ) {
				// Timeout-Eintrag: Wert ist Unix-Timestamp des Ablaufs.
				$expires_at = (int) $row->option_value;
				if ( $expires_at <= 0 ) {
					continue;
				}

				$remaining = $expires_at - $now;

				if ( $remaining > 0 && $remaining < $next_expiry ) {
					$next_expiry = $remaining;
				}

				// "Aelter" = naeher am Ablauf (kleines Remaining) -> wir suchen das KLEINSTE remaining
				// als Approximation fuer "aelteste TTL". Ohne Insert-Zeitpunkt ist das die beste
				// monotone Naeherung ohne Schema-Aenderung.
				if ( $remaining > 0 && $remaining < $oldest_expiry ) {
					$oldest_expiry = $remaining;
				}
			} else {
				// Value-Eintrag (eigentlicher Cache-Inhalt).
				++$count;
				$total_size += (int) $row->bytes;
			}
		}

		// Aelteste-Eintrag-Approximation: je geringer das Remaining, desto aelter (bei einheitlicher TTL).
		// Wir berechnen Alter als (DEFAULT_TTL - remaining), wenn remaining sinnvoll.
		if ( $oldest_expiry !== PHP_INT_MAX ) {
			$default_ttl = 3600; // entspricht DHPS_Cache::DEFAULT_TTL
			$age         = $default_ttl - $oldest_expiry;
			$oldest_age  = $age > 0 ? $age : 0;
		}

		$next_expiry_val = ( $next_expiry === PHP_INT_MAX ) ? 0 : $next_expiry;

		// QA-Fix v0.15.0 Critical-3: Alias-Keys fuer F2-Frontend (total_entries/total_bytes/next_expiry_in).
		// Additiv, BC-sicher - alte Keys bleiben fuer Backend-Kompatibilitaet.
		return array(
			'total_transients'         => $count,
			'total_entries'            => $count,                // F2-Alias
			'entries'                  => $count,                // F2-Alias (Kurzform)
			'total_size_bytes'         => $total_size,
			'total_bytes'              => $total_size,           // F2-Alias
			'bytes'                    => $total_size,           // F2-Alias (Kurzform)
			'oldest_transient_age_sec' => $oldest_age,
			'next_expiry_in_sec'       => $next_expiry_val,
			'next_expiry_in'           => $next_expiry_val,      // F2-Alias
			'next_expires_in'          => $next_expiry_val,      // F2-Alias
			'checked_at'               => $now,
		);
	}

	/**
	 * Loescht Plugin-Transients.
	 *
	 * v0.15.0-Limitation: Cache-Keys enthalten keine Service-Information.
	 * Bei uebergebenem $service wird daher trotzdem ALLES geleert
	 * (mit Hinweis ueber das Rueckgabesignal an die UI - siehe Doc-Block).
	 *
	 * @since 0.15.0
	 *
	 * @param string|null $service Optional Service-Slug (aktuell ohne Wirkung, siehe Limitation).
	 *
	 * @return int Anzahl geloeschter Rows (Value-Rows + Timeout-Rows zusammen).
	 */
	public function flush( ?string $service = null ): int {
		global $wpdb;

		// Hinweis: $service wird in v0.15.0 nicht ausgewertet, weil das aktuelle
		// Cache-Key-Schema (dhps_{md5}) keine Service-Zuordnung erlaubt.
		// Pseudo-Verwendung verhindert Lint-Warnungen + dokumentiert die Absicht.
		unset( $service );

		$pattern_value   = '_transient_' . self::KEY_PREFIX . '%';
		$pattern_timeout = '_transient_timeout_' . self::KEY_PREFIX . '%';

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE %s OR option_name LIKE %s",
				$pattern_value,
				$pattern_timeout
			)
		);

		return (int) $deleted;
	}
}
