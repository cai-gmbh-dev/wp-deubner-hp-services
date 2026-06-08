<?php
/**
 * AJAX-Handler fuer MMB-Lazy-Akkordeon-Loading.
 *
 * Liefert die Fact-Sheets einer einzelnen MMB-/MIL-Kategorie als JSON,
 * sodass das Frontend nur die initial benoetigten Kategorie-Titel rendert
 * und die Inhalte beim Akkordeon-Open nachlaedt.
 *
 * Ablauf einer Anfrage:
 *   1. Nonce verifizieren (dhps_mmb_nonce)
 *   2. Rate-Limit pro IP pruefen (60 Requests / Minute)
 *   3. Input sanitisieren (service-Whitelist, category_id)
 *   4. API-Daten via DHPS_API_Client laden (Cache-Aside)
 *   5. HTML via DHPS_MMB_Parser strukturieren
 *   6. Gesuchte Kategorie extrahieren
 *   7. HTML via Partial-Template rendern
 *   8. JSON-Response (success oder error)
 *
 * SICHERHEIT: Es wird KEIN direkter HTTP-Outbound an externe URLs gemacht.
 * Der Endpoint nutzt ausschliesslich den DI-injizierten API-Client, der
 * selbst SSRF-safe ist. Das gerenderte HTML wird vor dem Senden mit
 * wp_kses_post() defensiv gefiltert (Defense in Depth).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_MMB_AJAX_Handler
 *
 * @since 0.14.0
 */
class DHPS_MMB_AJAX_Handler {

	/**
	 * Erlaubte Services fuer diesen Endpoint (strikte Whitelist).
	 *
	 * @since 0.14.0
	 * @var array<int,string>
	 */
	private const ALLOWED_SERVICES = array( 'mmb', 'mil' );

	/**
	 * Erlaubte Layouts fuer Partial-Auswahl (strikte Whitelist).
	 *
	 * Wird in handle_request() ueber sanitize_key() + in_array() strict
	 * gegen Path-Traversal abgesichert. Bei unbekanntem Layout wird
	 * auf 'default' zurueckgefallen (BC-konform).
	 *
	 * @since 0.15.2
	 * @var array<int,string>
	 */
	private const ALLOWED_LAYOUTS = array( 'default', 'card', 'compact' );

	/**
	 * Maximale Requests pro IP pro Minute.
	 *
	 * @since 0.14.0
	 * @var int
	 */
	private const RATE_LIMIT_PER_MINUTE = 60;

	/**
	 * WP-Nonce-Action.
	 *
	 * @since 0.14.0
	 * @var string
	 */
	private const NONCE_ACTION = 'dhps_mmb_nonce';

	/**
	 * Maximale Laenge einer category_id (Sanity-Limit, Defense in Depth).
	 *
	 * @since 0.14.0
	 * @var int
	 */
	private const CATEGORY_ID_MAX_LENGTH = 100;

	/**
	 * API-Client (kombiniert API + Cache, Cache-Aside).
	 *
	 * @since 0.14.0
	 * @var DHPS_API_Client
	 */
	private DHPS_API_Client $client;

	/**
	 * Cache fuer Rate-Limit-Transients (und ggf. eigene Lookups).
	 *
	 * @since 0.14.0
	 * @var DHPS_Cache
	 */
	private DHPS_Cache $cache;

	/**
	 * Konstruktor.
	 *
	 * @since 0.14.0
	 *
	 * @param DHPS_API_Client $client API-Client-Fassade.
	 * @param DHPS_Cache      $cache  Cache-Instanz.
	 */
	public function __construct( DHPS_API_Client $client, DHPS_Cache $cache ) {
		$this->client = $client;
		$this->cache  = $cache;
	}

	/**
	 * Registriert die AJAX-Hooks.
	 *
	 * @since 0.14.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_dhps_mmb_category_load', array( $this, 'handle_request' ) );
		add_action( 'wp_ajax_nopriv_dhps_mmb_category_load', array( $this, 'handle_request' ) );
	}

	/**
	 * Verarbeitet eine Kategorie-Load-Anfrage.
	 *
	 * Erwartete Request-Parameter (GET oder POST):
	 *   - action:      dhps_mmb_category_load
	 *   - service:     mmb | mil
	 *   - category_id: z.B. rubrik_3
	 *   - _wpnonce:    dhps_mmb_nonce
	 *
	 * @since 0.14.0
	 *
	 * @return void Gibt JSON aus und beendet die Ausfuehrung.
	 */
	public function handle_request(): void {
		// 1. Nonce-Check (akzeptiert sowohl _wpnonce als auch nonce).
		$nonce_raw = '';
		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce_raw = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		} elseif ( isset( $_REQUEST['nonce'] ) ) {
			$nonce_raw = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) );
		}

		if ( '' === $nonce_raw || ! wp_verify_nonce( $nonce_raw, self::NONCE_ACTION ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_nonce',
					'message' => 'Ungueltige oder abgelaufene Sicherheits-Token.',
				),
				403
			);
		}

		// 2. Rate-Limit (vor weiterer Verarbeitung, um Aufwand klein zu halten).
		if ( ! $this->check_rate_limit() ) {
			wp_send_json_error(
				array(
					'code'    => 'rate_limit_exceeded',
					'message' => 'Zu viele Anfragen. Bitte spaeter erneut versuchen.',
				),
				429
			);
		}

		// 3. Input sanitisieren.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce bereits geprueft.
		$service_raw     = isset( $_REQUEST['service'] ) ? wp_unslash( $_REQUEST['service'] ) : '';
		$category_id_raw = isset( $_REQUEST['category_id'] ) ? wp_unslash( $_REQUEST['category_id'] ) : '';
		$layout_raw      = isset( $_REQUEST['layout'] ) ? wp_unslash( $_REQUEST['layout'] ) : 'default';
		// phpcs:enable

		$service     = sanitize_key( (string) $service_raw );
		$category_id = sanitize_key( (string) $category_id_raw );
		$layout      = sanitize_key( (string) $layout_raw );

		// Layout-Whitelist (strict, Path-Traversal-Schutz). Default-Fallback bei Mismatch.
		if ( ! in_array( $layout, self::ALLOWED_LAYOUTS, true ) ) {
			$layout = 'default';
		}

		// 4. Service-Whitelist (strict).
		if ( ! in_array( $service, self::ALLOWED_SERVICES, true ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_service',
					'message' => 'Unbekannter Service.',
				),
				400
			);
		}

		// 5. category_id validieren.
		if ( '' === $category_id ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_category',
					'message' => 'Keine Kategorie angegeben.',
				),
				400
			);
		}

		if ( strlen( $category_id ) > self::CATEGORY_ID_MAX_LENGTH ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_category',
					'message' => 'Kategorie-ID zu lang.',
				),
				400
			);
		}

		// 6. Service-Konfiguration laden.
		$service_config = DHPS_Service_Registry::get_service( $service );

		if ( null === $service_config ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_service',
					'message' => 'Service nicht registriert.',
				),
				400
			);
		}

		// 7. Auth-Token serverseitig laden.
		$ota = get_option( $service_config['auth_option'], '' );

		if ( '' === $ota ) {
			wp_send_json_error(
				array(
					'code'    => 'service_not_configured',
					'message' => 'Service nicht konfiguriert.',
				),
				400
			);
		}

		// 8. API-Parameter zusammenbauen (analog zur Pipeline).
		$endpoint = isset( $service_config['endpoint'] ) ? (string) $service_config['endpoint'] : '';
		$defaults = isset( $service_config['default_params'] ) && is_array( $service_config['default_params'] )
			? $service_config['default_params']
			: array();

		$api_params = array_merge(
			$defaults,
			array(
				$service_config['auth_type'] => $ota,
				'ausgabe'                    => 1,
			)
		);

		// 9. HTML vom API-Client holen (Cache greift bereits hier).
		$html = $this->client->fetch_content( $endpoint, $api_params, 3600 );

		if ( '' === trim( $html ) ) {
			wp_send_json_error(
				array(
					'code'    => 'empty_response',
					'message' => 'Inhalte konnten nicht geladen werden.',
				),
				502
			);
		}

		// 10. HTML strukturieren.
		$parser = new DHPS_MMB_Parser();
		$parsed = $parser->parse( $html );

		$categories = isset( $parsed['categories'] ) && is_array( $parsed['categories'] )
			? $parsed['categories']
			: array();

		// 11. Kategorie suchen.
		$category = null;
		foreach ( $categories as $cat ) {
			if ( isset( $cat['id'] ) && (string) $cat['id'] === $category_id ) {
				$category = $cat;
				break;
			}
		}

		if ( null === $category ) {
			wp_send_json_error(
				array(
					'code'    => 'category_not_found',
					'message' => 'Kategorie nicht gefunden.',
				),
				404
			);
		}

		// 12. HTML rendern (Partial-Template, Layout-spezifisch seit 0.15.2).
		$rendered_html = $this->render_category_html( $category, $service, $layout );

		// 13. Response-Payload bauen.
		$fact_sheets = isset( $category['fact_sheets'] ) && is_array( $category['fact_sheets'] )
			? $category['fact_sheets']
			: array();

		$response = array(
			'category_id'   => (string) $category['id'],
			'category_name' => isset( $category['name'] ) ? (string) $category['name'] : '',
			'icon_slug'     => isset( $category['icon_slug'] ) ? (string) $category['icon_slug'] : '',
			'fact_sheets'   => $fact_sheets,
			'html'          => $rendered_html,
		);

		// v0.18.2 TD-V0171-2: Collection-Side-Channel fuer DTO-Konsistenz.
		// Frontend-JS-Vertrag bleibt BYTEWISE UNVERAENDERT - $response ist die
		// JSON-Response. Plugins/Themes koennen die Collection via Action-Hook
		// konsumieren (z.B. fuer eigene Akkordeon-Renders, Analytics).
		if ( function_exists( 'dhps_mmb_category_to_collection' ) ) {
			// v0.18.3: Aufruf-Kontext (Layout-Hint) als $extra_meta durchreichen.
			$category_collection = dhps_mmb_category_to_collection(
				$category,
				$service,
				array( 'layout' => $layout )
			);

			/**
			 * Action: erlaubt Plugins/Themes die Lazy-Akkordeon-Category-Daten
			 * als Collection zu konsumieren. Default-Verhalten unveraendert.
			 *
			 * @since 0.18.2
			 *
			 * @param DHPS_Content_Collection|null $category_collection Collection oder null.
			 * @param array                        $category            Rohes Category-Array.
			 * @param string                       $service             'mmb' oder 'mil'.
			 */
			do_action( 'dhps_mmb_category_collection', $category_collection, $category, $service );
		}

		wp_send_json_success( $response );
	}

	/**
	 * Prueft das Rate-Limit pro IP via Transient-Counter.
	 *
	 * Erhoeht den Counter bei jedem Aufruf und vergleicht gegen
	 * RATE_LIMIT_PER_MINUTE. Bei Ueberschreitung -> false.
	 *
	 * Implementierung: Wir nutzen ein Transient mit 60s TTL. Da WP-Transients
	 * keinen atomaren Increment kennen, akzeptieren wir Race-Conditions im
	 * Sub-Sekunden-Bereich (unkritisch fuer Anti-Misbrauch).
	 *
	 * @since 0.14.0
	 *
	 * @return bool true wenn unter Limit, false wenn ueberschritten.
	 */
	private function check_rate_limit(): bool {
		$ip_raw = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip     = sanitize_text_field( (string) $ip_raw );

		if ( '' === $ip ) {
			// Ohne IP koennen wir kein Rate-Limit durchsetzen, lassen aber durch.
			return true;
		}

		$key     = 'dhps_mmb_rate_' . md5( $ip );
		$current = get_transient( $key );

		if ( false === $current ) {
			set_transient( $key, 1, MINUTE_IN_SECONDS );
			return true;
		}

		$count = (int) $current;

		if ( $count >= self::RATE_LIMIT_PER_MINUTE ) {
			return false;
		}

		// Counter erhoehen; TTL wird beim ersten Schreibvorgang gesetzt
		// und laeuft natuerlich aus - kein erneutes Setzen, sonst rollt das Fenster.
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Rendert das Partial-Template fuer eine Kategorie.
	 *
	 * Waehlt das Layout-spezifische Partial anhand der ALLOWED_LAYOUTS-
	 * Whitelist. Bei unbekanntem Layout faellt der Aufruf auf 'default'
	 * zurueck (BC: alte AJAX-Calls ohne layout-Param liefern weiter das
	 * Default-Partial).
	 *
	 * Pfad-Map:
	 *   - default -> partials/category-content.php
	 *   - card    -> partials/card-content.php
	 *   - compact -> partials/compact-content.php
	 *
	 * Defense in Depth: Output wird abschliessend ueber wp_kses_post()
	 * gefiltert.
	 *
	 * @since 0.14.0
	 * @since 0.15.2 Layout-Whitelist + Partial-Switch (card/compact).
	 *
	 * @param array  $category    Strukturiertes Kategorie-Array (id, name, fact_sheets).
	 * @param string $service_tag 'mmb' oder 'mil'.
	 * @param string $layout      'default' | 'card' | 'compact' (bereits whitelisted).
	 *
	 * @return string Gerendertes, gefiltertes HTML.
	 */
	private function render_category_html( array $category, string $service_tag, string $layout = 'default' ): string {
		// Defense in Depth: Whitelist nochmal pruefen (falls jemand direkt aufruft).
		if ( ! in_array( $layout, self::ALLOWED_LAYOUTS, true ) ) {
			$layout = 'default';
		}

		// Path-Map zum Partial (strict, kein dynamischer String-Build).
		$partials = array(
			'default' => 'category-content.php',
			'card'    => 'card-content.php',
			'compact' => 'compact-content.php',
		);

		$partial_file = isset( $partials[ $layout ] ) ? $partials[ $layout ] : 'category-content.php';

		$template = trailingslashit( DEUBNER_HP_SERVICES_PATH )
			. 'public/views/services/mmb/partials/' . $partial_file;

		// Fallback auf Default-Partial wenn Layout-spezifisches Partial fehlt.
		if ( ! file_exists( $template ) ) {
			$template = trailingslashit( DEUBNER_HP_SERVICES_PATH )
				. 'public/views/services/mmb/partials/category-content.php';
		}

		if ( ! file_exists( $template ) ) {
			return '';
		}

		ob_start();
		// Variablen, die das Partial nutzt.
		$service_tag = $service_tag; // phpcs:ignore VariableAnalysis -- bewusst fuer Partial.
		$category    = $category;    // phpcs:ignore VariableAnalysis -- bewusst fuer Partial.
		include $template;
		$html = (string) ob_get_clean();

		// Defense in Depth: erlaubt nur die ueblichen HTML-Tags fuer Post-Content.
		return wp_kses_post( $html );
	}
}
