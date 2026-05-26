<?php
/**
 * Health-Collector fuer das Admin-Dashboard.
 *
 * Sammelt Health-Metadaten pro Service (OTA gesetzt, OTA-Preview, Branding,
 * API-Erreichbarkeit, API-URL) und liefert sie als deterministisches Array.
 *
 * Erreichbarkeit (probe_availability) wird in einem 60s-Transient gecached,
 * damit ein 5s-HEAD-Request das Admin-UI nicht blockiert.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Health_Collector
 *
 * @since 0.15.0
 */
class DHPS_Health_Collector {

	/**
	 * Service-Slugs, die der Collector kennt.
	 *
	 * Bewusst hier dupliziert (DHPS_Admin_REST haelt die kanonische Liste),
	 * damit der Collector unabhaengig instanziierbar bleibt.
	 *
	 * Seit v0.15.5 enthaelt die Liste auch die 4 Sub-Shortcodes
	 * (mio_termine, maes_videos, maes_merkblaetter, maes_aktuelles), damit
	 * collect_all() vollstaendige Health-Records inkl. Sub-Shortcodes liefert.
	 * Sub-Shortcodes erben Auth-Status + Branding + API-URL vom Parent
	 * (Caveat C1, siehe DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS).
	 *
	 * @since 0.15.0
	 * @since 0.15.5 Sub-Shortcodes (mio_termine, maes_videos, maes_merkblaetter, maes_aktuelles).
	 * @var array<int,string>
	 */
	private const SERVICES = array(
		// Hauptservices (seit v0.15.0).
		'mio', 'lxmio', 'mmb', 'mil', 'tp', 'tpt', 'tc', 'maes', 'lp',
		// Sub-Shortcodes (seit v0.15.5, Caveat C1).
		'mio_termine', 'maes_videos', 'maes_merkblaetter', 'maes_aktuelles',
	);

	/**
	 * TTL fuer den Availability-Probe-Cache in Sekunden.
	 *
	 * @since 0.15.0
	 * @var int
	 */
	private const AVAIL_CACHE_TTL = 60;

	/**
	 * Timeout fuer den HEAD-Probe-Request in Sekunden.
	 *
	 * @since 0.15.0
	 * @var int
	 */
	private const PROBE_TIMEOUT = 5;

	/**
	 * API-Client (wird aktuell nicht zwingend benoetigt, aber via DI gehalten,
	 * damit zukuenftige Erweiterungen einen echten API-Call probieren koennen).
	 *
	 * @since 0.15.0
	 * @var DHPS_API_Client
	 */
	private DHPS_API_Client $client;

	/**
	 * Cache (DI, fuer ggf. zukuenftige Hit/Miss-Statistik).
	 *
	 * @since 0.15.0
	 * @var DHPS_Cache
	 */
	private DHPS_Cache $cache;

	/**
	 * Konstruktor.
	 *
	 * @since 0.15.0
	 *
	 * @param DHPS_API_Client $client API-Client-Fassade.
	 * @param DHPS_Cache      $cache  Cache-Instanz.
	 */
	public function __construct( DHPS_API_Client $client, DHPS_Cache $cache ) {
		$this->client = $client;
		$this->cache  = $cache;
	}

	/**
	 * Sammelt Health-Daten fuer alle bekannten Services.
	 *
	 * @since 0.15.0
	 *
	 * @return array<int,array<string,mixed>> Liste von Health-Records.
	 */
	public function collect_all(): array {
		$out = array();

		foreach ( self::SERVICES as $slug ) {
			$out[] = $this->collect_for( $slug );
		}

		return $out;
	}

	/**
	 * Sammelt Health-Daten fuer einen einzelnen Service.
	 *
	 * Seit v0.15.5 (Caveat C1): Bei Sub-Shortcodes (mio_termine, maes_videos,
	 * maes_merkblaetter, maes_aktuelles) werden alle internen Lookups
	 * (Label, OTA-Status, API-URL, Branding) gegen den Parent-Service
	 * aufgeloest. Der Output behaelt aber das Original-Slug, damit die
	 * UI den Sub-Shortcode korrekt identifiziert. Zusaetzlich kommen
	 * zwei neue Felder:
	 *   - parent_service   string  Parent-Slug (z.B. 'maes' fuer 'maes_videos').
	 *                              Bei Hauptservices: identisch mit slug.
	 *   - is_sub_shortcode bool    true wenn Sub-Shortcode (Parent != slug).
	 *
	 * Diese Felder sind ADDITIV (BC-sicher) - bestehende ServiceHealthCard
	 * ignoriert unbekannte Felder defensiv.
	 *
	 * @since 0.15.0
	 * @since 0.15.5 Sub-Shortcode-Support (Caveat C1).
	 *
	 * @param string $service Service-Slug (z.B. 'mio', 'maes_videos').
	 *
	 * @return array<string,mixed> Health-Record gemaess Schema.
	 */
	public function collect_for( string $service ): array {
		$service = sanitize_key( $service );

		// Sub-Shortcode-Resolution: lookup via SUB_SHORTCODE_PARENTS.
		// Falls Klasse nicht geladen ist (Defensive), Fallback auf $service.
		$parent_slug = $service;
		if ( class_exists( 'DHPS_Preview_Renderer' ) ) {
			$sub_parents = DHPS_Preview_Renderer::SUB_SHORTCODE_PARENTS;
			if ( isset( $sub_parents[ $service ] ) ) {
				$parent_slug = $sub_parents[ $service ];
			}
		}
		$is_sub = ( $parent_slug !== $service );

		// Availability-Cache: gegen Parent (sonst doppelt geprueft je Sub).
		$avail_cache_key = $this->get_avail_cache_key( $parent_slug );
		$cached_at       = (int) get_option( $avail_cache_key . '_ts', 0 );

		// Alle internen Lookups gegen $parent_slug, Output behaelt $service.
		$label     = $this->get_label( $parent_slug );
		$ota_set   = $this->is_ota_set( $parent_slug );
		$available = $this->is_available_cached( $parent_slug );
		$api_url   = $this->get_api_url( $parent_slug );

		// Sub-Shortcode-Label-Suffix fuer UI-Klarheit.
		if ( $is_sub ) {
			$sub_labels = array(
				'mio_termine'       => 'MIO Termine (Sub)',
				'maes_videos'       => 'MAES Videos (Sub)',
				'maes_merkblaetter' => 'MAES Merkblaetter (Sub)',
				'maes_aktuelles'    => 'MAES Aktuelles (Sub)',
			);
			if ( isset( $sub_labels[ $service ] ) ) {
				$label = $sub_labels[ $service ];
			}
		}

		// QA-Fix v0.15.0 Critical-2: Alias-Keys fuer F2-Frontend (slug/name/ota_configured/api_reachable/endpoint).
		// F1+F2 wurden parallel entwickelt und haben unterschiedliche Schluesselnamen gewaehlt -
		// jetzt liefern wir beide Varianten additiv (BC-sicher).
		return array(
			'service'             => $service,
			'slug'                => $service,            // F2-Alias
			'parent_service'      => $parent_slug,        // NEU v0.15.5 (C1)
			'is_sub_shortcode'    => $is_sub,             // NEU v0.15.5 (C1)
			'label'               => $label,
			'name'                => $label,              // F2-Alias
			'ota_set'             => $ota_set,
			'ota_configured'      => $ota_set,            // F2-Alias
			'ota_preview'         => $this->get_ota_preview( $parent_slug ),
			'ota_key'             => $this->get_ota_option_key( $parent_slug ),
			'branding'            => $this->get_branding( $parent_slug ),
			'available'           => $available,
			'api_reachable'       => $available,          // F2-Alias
			'available_cached_at' => $cached_at,
			'api_url'             => $api_url,
			'endpoint'            => $api_url,            // F2-Alias
		);
	}

	/**
	 * Liefert das Label/den Anzeigenamen eines Service.
	 *
	 * @since 0.15.0
	 *
	 * @param string $service Service-Slug.
	 *
	 * @return string Anzeigename oder leerer String, wenn unbekannt.
	 */
	private function get_label( string $service ): string {
		$config = DHPS_Service_Registry::get_service( $service );
		if ( null === $config ) {
			return '';
		}
		return isset( $config['admin_title'] ) ? (string) $config['admin_title']
			: ( isset( $config['name'] ) ? (string) $config['name'] : '' );
	}

	/**
	 * Branding-Klassifikation pro Service.
	 *
	 * Ergebnisse: 'steuern' | 'recht' | 'medizin' | ''.
	 *
	 * @since 0.15.0
	 *
	 * @param string $service Service-Slug.
	 *
	 * @return string Branding-Slug.
	 */
	private function get_branding( string $service ): string {
		$config = DHPS_Service_Registry::get_service( $service );
		if ( null === $config ) {
			return '';
		}
		$category = isset( $config['category'] ) ? (string) $config['category'] : '';

		// Mapping Service-Category -> Branding-Slug.
		switch ( $category ) {
			case 'steuern':
				return 'steuern';
			case 'recht':
				return 'recht';
			case 'medizin':
				return 'medizin';
			default:
				return '';
		}
	}

	/**
	 * Prueft, ob fuer den Service eine OTA/kdnr gesetzt ist.
	 *
	 * @since 0.15.0
	 *
	 * @param string $service Service-Slug.
	 *
	 * @return bool true, wenn Option nicht-leer.
	 */
	private function is_ota_set( string $service ): bool {
		$key = $this->get_ota_option_key( $service );
		if ( '' === $key ) {
			return false;
		}
		$value = (string) get_option( $key, '' );
		return '' !== trim( $value );
	}

	/**
	 * Liefert eine gekuerzte OTA-Vorschau (Audit-Trail-Schutz).
	 *
	 * Erste 6 Zeichen + "...". Vollstaendige OTA wird NIE im API-Output preisgegeben.
	 *
	 * @since 0.15.0
	 *
	 * @param string $service Service-Slug.
	 *
	 * @return string OTA-Preview oder leerer String.
	 */
	private function get_ota_preview( string $service ): string {
		$key = $this->get_ota_option_key( $service );
		if ( '' === $key ) {
			return '';
		}
		$value = (string) get_option( $key, '' );
		if ( '' === $value ) {
			return '';
		}
		// QA-Fix v0.14.5 (SEC LOW-4.1): bei OTA-Werten <=6 Zeichen NICHT den
		// Full-Wert + "..." leaken, sondern komplett maskieren.
		if ( strlen( $value ) <= 6 ) {
			return '***';
		}
		return substr( $value, 0, 6 ) . '...';
	}

	/**
	 * Liefert den wp_options-Key der Auth-Konfiguration.
	 *
	 * Spezialfall: TPT teilt sich den Token mit TP (dhps_ota_tp).
	 *
	 * @since 0.15.0
	 *
	 * @param string $service Service-Slug.
	 *
	 * @return string Option-Key oder leerer String.
	 */
	private function get_ota_option_key( string $service ): string {
		// Statische Map - explizit, damit das Shared-Token-Mapping fuer TPT sichtbar bleibt.
		$map = array(
			'mio'   => 'dhps_ota_mio',
			'lxmio' => 'dhps_lxmio_ota',
			'mmb'   => 'dhps_mmo_ota',
			'mil'   => 'dhps_mil_ota',
			'tp'    => 'dhps_ota_tp',
			'tpt'   => 'dhps_ota_tp', // geteilt mit TP
			'tc'    => 'dhps_tc_kdnr',
			'maes'  => 'dhps_maes_kdnr',
			'lp'    => 'dhps_lp_ota',
		);
		return isset( $map[ $service ] ) ? $map[ $service ] : '';
	}

	/**
	 * Liefert die vollstaendige API-URL eines Service (Base + Endpoint).
	 *
	 * @since 0.15.0
	 *
	 * @param string $service Service-Slug.
	 *
	 * @return string Voll-URL oder leerer String.
	 */
	private function get_api_url( string $service ): string {
		$config = DHPS_Service_Registry::get_service( $service );
		if ( null === $config ) {
			return '';
		}
		$endpoint = isset( $config['endpoint'] ) ? (string) $config['endpoint'] : '';
		if ( '' === $endpoint ) {
			return '';
		}
		$base = defined( 'DEUBNER_HP_SERVICES_API_BASE' ) ? (string) DEUBNER_HP_SERVICES_API_BASE : '';
		if ( '' === $base ) {
			return $endpoint;
		}
		return trailingslashit( $base ) . ltrim( $endpoint, '/' );
	}

	/**
	 * Erreichbarkeits-Pruefung mit 60s-Caching, damit UI nicht haengt.
	 *
	 * Resultate werden zusaetzlich mit einem _ts-Suffix gespeichert, damit das
	 * Frontend den letzten Pruefzeitpunkt anzeigen kann.
	 *
	 * @since 0.15.0
	 *
	 * @param string $service Service-Slug.
	 *
	 * @return bool true, wenn API-Basis erreichbar.
	 */
	private function is_available_cached( string $service ): bool {
		$transient_key = $this->get_avail_cache_key( $service );
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return '1' === (string) $cached || 1 === (int) $cached;
		}

		$available = $this->probe_availability( $service );

		// Resultat 60s cachen + Timestamp persistieren.
		set_transient( $transient_key, $available ? '1' : '0', self::AVAIL_CACHE_TTL );
		update_option( $transient_key . '_ts', time(), false );

		return $available;
	}

	/**
	 * Effektive HEAD-Probe gegen die API-Basis.
	 *
	 * Kein vollstaendiger Endpoint-Hit, damit OTA nicht verschossen wird; reine
	 * Erreichbarkeits-Pruefung der Domain.
	 *
	 * @since 0.15.0
	 *
	 * @param string $service Service-Slug (zur Logging-Kontextualisierung).
	 *
	 * @return bool true bei HTTP < 500, false bei Fehler/Timeout/5xx.
	 */
	private function probe_availability( string $service ): bool {
		unset( $service ); // aktuell nur Logging-Kontext.

		$base = defined( 'DEUBNER_HP_SERVICES_API_BASE' ) ? (string) DEUBNER_HP_SERVICES_API_BASE : '';
		if ( '' === $base ) {
			return false;
		}

		$response = wp_remote_head(
			$base,
			array(
				'timeout'     => self::PROBE_TIMEOUT,
				'sslverify'   => true,
				'redirection' => 3,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		return $code > 0 && $code < 500;
	}

	/**
	 * Cache-Key-Helfer fuer den Availability-Transient.
	 *
	 * @since 0.15.0
	 *
	 * @param string $service Service-Slug.
	 *
	 * @return string Transient-Key.
	 */
	private function get_avail_cache_key( string $service ): string {
		return 'dhps_health_avail_' . $service;
	}
}
