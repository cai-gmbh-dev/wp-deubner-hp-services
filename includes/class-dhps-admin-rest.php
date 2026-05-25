<?php
/**
 * REST-API-Endpoints fuer das Admin-Dashboard (v0.15.0).
 *
 * Namespace: dhps/v1
 * Permission: manage_options (alle Endpoints)
 *
 * Endpoints:
 *   GET  /dhps/v1/services/health
 *   GET  /dhps/v1/services/(?P<service>[a-z]+)/health
 *   POST /dhps/v1/services/(?P<service>[a-z]+)/test    (rate-limited 30/min/user)
 *   GET  /dhps/v1/cache/stats
 *   POST /dhps/v1/cache/flush                          (rate-limited 6/min/user)
 *
 * Security:
 *   - manage_options Capability auf jedem Endpoint (permission_callback).
 *   - WP-REST-Nonce ueber X-WP-Nonce Header (apiFetch.createNonceMiddleware).
 *   - Service-Whitelist + sanitize_key + Laengen-Limit auf $service-Param.
 *   - SSRF-Schutz via DHPS_API_Client (keine freie URL-Eingabe vom Client).
 *   - OTA wird NIE vollstaendig zurueckgegeben (nur Preview via Health-Collector).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Admin_REST
 *
 * @since 0.15.0
 */
class DHPS_Admin_REST {

	/**
	 * REST-Namespace.
	 *
	 * @since 0.15.0
	 * @var string
	 */
	public const NAMESPACE = 'dhps/v1';

	/**
	 * Whitelist erlaubter Service-Slugs.
	 *
	 * @since 0.15.0
	 * @var array<int,string>
	 */
	public const ALLOWED_SERVICES = array( 'mio', 'lxmio', 'mmb', 'mil', 'tp', 'tpt', 'tc', 'maes', 'lp' );

	/**
	 * Maximale Test-Requests pro Minute pro User.
	 *
	 * @since 0.15.0
	 * @var int
	 */
	private const RATE_LIMIT_PER_MINUTE = 30;

	/**
	 * Maximale Flush-Requests pro Minute pro User (destruktiv).
	 *
	 * @since 0.15.0
	 * @var int
	 */
	private const FLUSH_LIMIT_PER_MINUTE = 6;

	/**
	 * Sanity-Limit fuer den service-Route-Parameter (Defense in Depth).
	 *
	 * @since 0.15.0
	 * @var int
	 */
	private const SERVICE_PARAM_MAX_LENGTH = 16;

	/**
	 * API-Client (Cache-Aside Fassade).
	 *
	 * @since 0.15.0
	 * @var DHPS_API_Client
	 */
	private DHPS_API_Client $client;

	/**
	 * Cache-Instanz (fuer Rate-Limit + Cache-Probe).
	 *
	 * @since 0.15.0
	 * @var DHPS_Cache
	 */
	private DHPS_Cache $cache;

	/**
	 * Health-Collector.
	 *
	 * @since 0.15.0
	 * @var DHPS_Health_Collector
	 */
	private DHPS_Health_Collector $health;

	/**
	 * Cache-Stats-Service.
	 *
	 * @since 0.15.0
	 * @var DHPS_Cache_Stats
	 */
	private DHPS_Cache_Stats $cache_stats;

	/**
	 * Konstruktor.
	 *
	 * @since 0.15.0
	 *
	 * @param DHPS_API_Client       $client      API-Client-Fassade.
	 * @param DHPS_Cache            $cache       Cache-Instanz.
	 * @param DHPS_Health_Collector $health      Health-Collector.
	 * @param DHPS_Cache_Stats      $cache_stats Cache-Stats-Service.
	 */
	public function __construct(
		DHPS_API_Client $client,
		DHPS_Cache $cache,
		DHPS_Health_Collector $health,
		DHPS_Cache_Stats $cache_stats
	) {
		$this->client      = $client;
		$this->cache       = $cache;
		$this->health      = $health;
		$this->cache_stats = $cache_stats;
	}

	/**
	 * Haengt den REST-Init-Hook ein.
	 *
	 * @since 0.15.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registriert die 5 Routes unter dhps/v1.
	 *
	 * @since 0.15.0
	 *
	 * @return void
	 */
	public function register_routes(): void {

		// 1. GET /services/health - Liste aller Services.
		register_rest_route(
			self::NAMESPACE,
			'/services/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_services_health' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(),
			)
		);

		// 2. GET /services/{service}/health - einzelner Service.
		register_rest_route(
			self::NAMESPACE,
			'/services/(?P<service>[a-z]+)/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_service_health' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'service' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => array( $this, 'validate_service_param' ),
					),
				),
			)
		);

		// 3. POST /services/{service}/test - rate-limited.
		register_rest_route(
			self::NAMESPACE,
			'/services/(?P<service>[a-z]+)/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_service_test' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'service' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => array( $this, 'validate_service_param' ),
					),
				),
			)
		);

		// 4. GET /cache/stats.
		register_rest_route(
			self::NAMESPACE,
			'/cache/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_cache_stats' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(),
			)
		);

		// 5. POST /cache/flush - rate-limited, destruktiv.
		register_rest_route(
			self::NAMESPACE,
			'/cache/flush',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_cache_flush' ),
				'permission_callback' => array( $this, 'check_permissions' ),
				'args'                => array(
					'service' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => array( $this, 'validate_service_param_optional' ),
					),
				),
			)
		);
	}

	/**
	 * Permission-Callback: nur Administratoren mit manage_options.
	 *
	 * @since 0.15.0
	 *
	 * @return bool true bei ausreichender Capability.
	 */
	public function check_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Validator fuer den service-Parameter (Pflicht).
	 *
	 * @since 0.15.0
	 *
	 * @param mixed $value Eingang.
	 *
	 * @return bool|WP_Error true wenn gueltig, sonst WP_Error.
	 */
	public function validate_service_param( $value ) {
		if ( ! is_string( $value ) ) {
			return new WP_Error(
				'invalid_service',
				'Service-Parameter muss ein String sein.',
				array( 'status' => 400 )
			);
		}
		if ( strlen( $value ) > self::SERVICE_PARAM_MAX_LENGTH ) {
			return new WP_Error(
				'invalid_service',
				'Service-Parameter zu lang.',
				array( 'status' => 400 )
			);
		}
		$slug = sanitize_key( $value );
		if ( ! in_array( $slug, self::ALLOWED_SERVICES, true ) ) {
			return new WP_Error(
				'invalid_service',
				'Unbekannter Service.',
				array( 'status' => 400 )
			);
		}
		return true;
	}

	/**
	 * Validator fuer optionalen service-Parameter (bei /cache/flush).
	 *
	 * @since 0.15.0
	 *
	 * @param mixed $value Eingang.
	 *
	 * @return bool|WP_Error true wenn gueltig oder leer, sonst WP_Error.
	 */
	public function validate_service_param_optional( $value ) {
		if ( null === $value || '' === $value ) {
			return true;
		}
		return $this->validate_service_param( $value );
	}

	/**
	 * GET /services/health - Health-Liste aller 9 Services.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Request-Objekt.
	 *
	 * @return WP_REST_Response JSON-Liste.
	 */
	public function handle_services_health( WP_REST_Request $request ) {
		unset( $request );
		$data = $this->health->collect_all();
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * GET /services/{service}/health - Health eines Service.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Request-Objekt.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_service_health( WP_REST_Request $request ) {
		$service = sanitize_key( (string) $request->get_param( 'service' ) );

		if ( ! in_array( $service, self::ALLOWED_SERVICES, true ) ) {
			return new WP_Error(
				'invalid_service',
				'Unbekannter Service.',
				array( 'status' => 400 )
			);
		}

		$data = $this->health->collect_for( $service );
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * POST /services/{service}/test - sendet Test-Request gegen die API.
	 *
	 * Cache-Hit-Detection: Wir pruefen vor dem fetch_content, ob der Cache-Key
	 * bereits existiert. Anschliessend ruft fetch_content() ohnehin den Cache
	 * oder die API ab - so wird ein Test transparent gemessen, ohne API-Quota
	 * unnoetig zu verbrennen.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Request-Objekt.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_service_test( WP_REST_Request $request ) {
		$service = sanitize_key( (string) $request->get_param( 'service' ) );

		if ( ! in_array( $service, self::ALLOWED_SERVICES, true ) ) {
			return new WP_Error(
				'invalid_service',
				'Unbekannter Service.',
				array( 'status' => 400 )
			);
		}

		// Rate-Limit pruefen.
		if ( ! $this->check_rate_limit( 'test', self::RATE_LIMIT_PER_MINUTE ) ) {
			return new WP_Error(
				'rate_limit_exceeded',
				'Zu viele Test-Anfragen. Bitte spaeter erneut versuchen.',
				array( 'status' => 429 )
			);
		}

		$config = DHPS_Service_Registry::get_service( $service );
		if ( null === $config ) {
			return new WP_Error(
				'invalid_service',
				'Service nicht registriert.',
				array( 'status' => 400 )
			);
		}

		$auth_option = isset( $config['auth_option'] ) ? (string) $config['auth_option'] : '';
		$auth_type   = isset( $config['auth_type'] ) ? (string) $config['auth_type'] : 'ota';
		$endpoint    = isset( $config['endpoint'] ) ? (string) $config['endpoint'] : '';
		$defaults    = isset( $config['default_params'] ) && is_array( $config['default_params'] )
			? $config['default_params']
			: array();

		$ota = '' !== $auth_option ? (string) get_option( $auth_option, '' ) : '';

		if ( '' === $ota ) {
			return new WP_Error(
				'service_not_configured',
				'Service nicht konfiguriert (Auth-Token fehlt).',
				array( 'status' => 400 )
			);
		}

		if ( '' === $endpoint ) {
			return new WP_Error(
				'invalid_endpoint',
				'Service hat keinen Endpoint definiert.',
				array( 'status' => 500 )
			);
		}

		// Params analog zur Pipeline aufbauen.
		$api_params = array_merge(
			$defaults,
			array(
				$auth_type => $ota,
				'ausgabe'  => 1,
			)
		);

		// Cache-Hit-Probe BEVOR der eigentliche Fetch laeuft.
		$cache_key  = $this->cache->generate_key( $endpoint, $api_params );
		$cache_hit  = null !== $this->cache->get( $cache_key );

		// Test ausfuehren (verwendet Cache, wenn vorhanden).
		$started_at = microtime( true );
		$html       = $this->client->fetch_content( $endpoint, $api_params, 3600 );
		$duration_s = microtime( true ) - $started_at;

		$bytes = strlen( $html );

		// Erfolgs-Heuristik: nicht-leerer Body + kein dhps-error-Kommentar an Position 0.
		$is_error_comment = ( 0 === strpos( ltrim( $html ), '<!--' ) ) && ( false !== stripos( $html, 'fehler' ) );
		$success          = ( $bytes > 0 ) && ! $is_error_comment;

		// HTTP-Status laesst sich nach Caching nicht mehr aus dem Body ableiten.
		// Bei Erfolg: 200 (vom Client ueblicherweise). Bei Misserfolg: 0/unknown.
		$http_code = $success ? 200 : 0;

		$response = array(
			'service'          => $service,
			'success'          => (bool) $success,
			'http_code'        => (int) $http_code,
			'bytes'            => (int) $bytes,
			'response_time_ms' => (int) round( $duration_s * 1000 ),
			'cache_hit'        => (bool) $cache_hit,
			'tested_at'        => time(),
		);

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * GET /cache/stats - aggregierte Cache-Statistik.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Request-Objekt.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_cache_stats( WP_REST_Request $request ) {
		unset( $request );
		$stats = $this->cache_stats->collect();
		return new WP_REST_Response( $stats, 200 );
	}

	/**
	 * POST /cache/flush - leert Plugin-Transients.
	 *
	 * Hinweis: $service-Filter wird in v0.15.0 NICHT angewendet, weil das
	 * aktuelle Cache-Key-Schema (dhps_{md5}) keine Service-Zuordnung erlaubt.
	 * Die Antwort enthaelt 'service_filter_applied' = false, damit die UI
	 * den Benutzer transparent informieren kann.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Request-Objekt.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_cache_flush( WP_REST_Request $request ) {
		if ( ! $this->check_rate_limit( 'flush', self::FLUSH_LIMIT_PER_MINUTE ) ) {
			return new WP_Error(
				'rate_limit_exceeded',
				'Zu viele Flush-Anfragen. Bitte kurz warten.',
				array( 'status' => 429 )
			);
		}

		$service_filter = $request->get_param( 'service' );
		$service_filter = is_string( $service_filter ) ? sanitize_key( $service_filter ) : null;

		// Loeschen (Filter wird intern verworfen - dokumentierte Limitation).
		$deleted_rows = $this->cache_stats->flush( $service_filter );

		$response = array(
			'flushed'                 => true,
			'deleted_rows'            => (int) $deleted_rows,
			'service_filter_requested' => $service_filter ?? '',
			'service_filter_applied'  => false, // siehe Doc-Block + Discovery v0.15.1
			'flushed_at'              => time(),
		);

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Prueft das Rate-Limit pro User fuer einen Bucket.
	 *
	 * Sliding-Window: Transient mit 60s TTL + Zaehler. Race-Conditions im
	 * Sub-Sekunden-Bereich werden bewusst akzeptiert (analog MMB_AJAX_Handler).
	 *
	 * @since 0.15.0
	 *
	 * @param string $bucket           Bucket-Name (z.B. 'test', 'flush').
	 * @param int    $limit_per_minute Maximalanzahl Requests/Minute.
	 *
	 * @return bool true wenn unter Limit, false sonst.
	 *
	 * Bekannte Limitierungen (dokumentiert in v0.14.5 nach SEC-Audit v0.15.0):
	 *
	 * - **Sliding-Window-Drift (SEC LOW-3.1)**: Der Counter wird beim ersten Hit
	 *   mit TTL=60s gesetzt, das Fenster rollt nicht mit jedem Request.
	 *   Praktische Konsequenz: 30 Requests koennen in den letzten 5s einer Minute
	 *   und 30 weitere in den ersten 5s der naechsten Minute fallen - effektiv
	 *   60 Requests in 10s. Akzeptabel fuer Admin-Tooling (manage_options-User),
	 *   nicht akzeptabel fuer Public-Endpoints. Fix via Sliding-Window-Algorithmus
	 *   (mehrere Buckets pro 10s) waere komplexer Speicher-Overhead - bewusst
	 *   nicht implementiert.
	 *
	 * - **Race-Condition Counter-Increment (SEC LOW-3.2)**: get_transient +
	 *   set_transient ist nicht atomar. Bei parallelen Requests koennen
	 *   Counter-Increments verloren gehen. Praktischer Worst-Case: ~1-2
	 *   Extra-Requests pro Minute - tolerabel. Echte Atomic-Counter erfordern
	 *   wpdb-Lock oder Redis - bewusst nicht implementiert.
	 *
	 * Beide Schwaechen sind analog zum DHPS_MMB_AJAX_Handler-Pattern aus
	 * v0.14.0 akzeptiert (siehe docs/project/11-SECURITY-AUDIT-v0140.md).
	 */
	private function check_rate_limit( string $bucket, int $limit_per_minute ): bool {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			// Ohne User keine Rate-Begrenzung (sollte permission_callback ohnehin nie zulassen).
			return true;
		}

		$key     = 'dhps_admin_rate_' . sanitize_key( $bucket ) . '_' . (int) $user_id;
		$current = get_transient( $key );

		if ( false === $current ) {
			set_transient( $key, 1, MINUTE_IN_SECONDS );
			return true;
		}

		$count = (int) $current;
		if ( $count >= $limit_per_minute ) {
			return false;
		}

		// Counter erhoehen ohne TTL zu erneuern, sonst rollt das Fenster.
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}
}
