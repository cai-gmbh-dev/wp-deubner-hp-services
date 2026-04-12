<?php
/**
 * AJAX-Proxy fuer serverseitige API-Aufrufe.
 *
 * Leitet Frontend-AJAX-Anfragen serverseitig an die Deubner-API weiter.
 * Verhindert, dass OTA-Kundennummern im Browser-Quelltext sichtbar sind,
 * indem die Authentifizierung ausschliesslich serverseitig erfolgt.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_AJAX_Proxy
 *
 * Registriert WordPress-AJAX-Endpoints, die als Proxy zwischen dem
 * Frontend-JavaScript und der Deubner-Legacy-API fungieren.
 *
 * @since 0.9.0
 */
class DHPS_AJAX_Proxy {

	/**
	 * Legacy-API-Instanz fuer direkte API-Aufrufe.
	 *
	 * @since 0.9.0
	 * @var DHPS_Legacy_API
	 */
	private DHPS_Legacy_API $api;

	/**
	 * Cache-Instanz.
	 *
	 * @since 0.9.0
	 * @var DHPS_Cache
	 */
	private DHPS_Cache $cache;

	/**
	 * News-Parser-Instanz.
	 *
	 * @since 0.9.0
	 * @var DHPS_MIO_News_Parser
	 */
	private DHPS_MIO_News_Parser $news_parser;

	/**
	 * Konstruktor.
	 *
	 * @since 0.9.0
	 *
	 * @param DHPS_Legacy_API $api   API-Instanz fuer HTTP-Requests.
	 * @param DHPS_Cache      $cache Cache-Instanz.
	 */
	public function __construct( DHPS_Legacy_API $api, DHPS_Cache $cache ) {
		$this->api          = $api;
		$this->cache        = $cache;
		$this->news_parser  = new DHPS_MIO_News_Parser();
	}

	/**
	 * Registriert die AJAX-Hooks.
	 *
	 * @since 0.9.0
	 * @since 0.9.1 MMB-Suche, MMB-PDF und TP-Video-Proxy hinzugefuegt.
	 *
	 * @return void
	 */
	public function register(): void {
		// MIO News.
		add_action( 'wp_ajax_dhps_load_news', array( $this, 'handle_news_request' ) );
		add_action( 'wp_ajax_nopriv_dhps_load_news', array( $this, 'handle_news_request' ) );

		// MMB Suche.
		add_action( 'wp_ajax_dhps_mmb_search', array( $this, 'handle_mmb_search' ) );
		add_action( 'wp_ajax_nopriv_dhps_mmb_search', array( $this, 'handle_mmb_search' ) );

		// MMB PDF-Download (Proxy).
		add_action( 'wp_ajax_dhps_mmb_pdf', array( $this, 'handle_mmb_pdf_download' ) );
		add_action( 'wp_ajax_nopriv_dhps_mmb_pdf', array( $this, 'handle_mmb_pdf_download' ) );

		// TP Video-iframe-src (Proxy).
		add_action( 'wp_ajax_dhps_tp_video_src', array( $this, 'handle_tp_video_src' ) );
		add_action( 'wp_ajax_nopriv_dhps_tp_video_src', array( $this, 'handle_tp_video_src' ) );
	}

	/**
	 * Verarbeitet eine News-AJAX-Anfrage.
	 *
	 * Ablauf:
	 * 1. Nonce verifizieren
	 * 2. Parameter sanitizen
	 * 3. OTA serverseitig aus WordPress-Options laden
	 * 4. API-Aufruf an hintergrundladen.php
	 * 5. Response parsen
	 * 6. JSON zurueckgeben
	 *
	 * @since 0.9.0
	 *
	 * @return void Gibt JSON aus und beendet die Ausfuehrung.
	 */
	public function handle_news_request(): void {
		// 1. Nonce pruefen.
		if ( ! check_ajax_referer( 'dhps_news_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Ungueltige Anfrage.' ), 403 );
		}

		// 2. Parameter sanitizen.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce bereits geprueft.
		$service_tag = isset( $_POST['service_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['service_tag'] ) ) : 'mio';
		$page        = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$search      = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$month       = isset( $_POST['month'] ) ? sanitize_text_field( wp_unslash( $_POST['month'] ) ) : 'alle';
		$year        = isset( $_POST['year'] ) ? sanitize_text_field( wp_unslash( $_POST['year'] ) ) : 'alle';
		$rubriken    = isset( $_POST['rubriken'] ) ? sanitize_text_field( wp_unslash( $_POST['rubriken'] ) ) : 'alle Zielgruppen';
		$zielgruppen = isset( $_POST['zielgruppen'] ) ? sanitize_text_field( wp_unslash( $_POST['zielgruppen'] ) ) : '';
		$fachgebiet  = isset( $_POST['fachgebiet'] ) ? sanitize_text_field( wp_unslash( $_POST['fachgebiet'] ) ) : 'S';
		$variante    = isset( $_POST['variante'] ) ? sanitize_text_field( wp_unslash( $_POST['variante'] ) ) : 'KATEGORIEN';
		$anzahl      = isset( $_POST['anzahl'] ) ? absint( $_POST['anzahl'] ) : 10;
		$teasermodus = isset( $_POST['teasermodus'] ) ? absint( $_POST['teasermodus'] ) : 0;
		// phpcs:enable

		// 3. OTA serverseitig aus WordPress-Options laden.
		$service = DHPS_Service_Registry::get_service( $service_tag );

		if ( null === $service ) {
			wp_send_json_error( array( 'message' => 'Unbekannter Service.' ), 400 );
		}

		$ota = get_option( $service['auth_option'], '' );

		if ( '' === $ota ) {
			wp_send_json_error( array( 'message' => 'Service nicht konfiguriert.' ), 400 );
		}

		// 4. API-Parameter zusammenbauen.
		$endpoint = str_replace( 'php_inhalt.php', 'hintergrundladen.php', $service['endpoint'] );

		$api_params = array(
			'page'         => $page,
			's'            => $search,
			'm'            => $month,
			'j'            => $year,
			'rubriken'     => $rubriken,
			'zielgruppen'  => $zielgruppen,
			'teasermodus'  => $teasermodus,
			'anzahl'       => $anzahl,
			'fachgebiet'   => $fachgebiet,
			'variante'     => $variante,
			$service['auth_type'] => $ota,
			'ausgabe'      => 1,
		);

		// 5. Cache pruefen.
		$cache_key = $this->cache->generate_key( $endpoint, $api_params );
		$cached    = $this->cache->get_data( $cache_key );

		if ( null !== $cached ) {
			wp_send_json_success( $cached );
		}

		// 6. API aufrufen.
		$response = $this->api->fetch( $endpoint, $api_params );

		if ( ! $response->is_success() ) {
			wp_send_json_error( array( 'message' => 'Inhalte konnten nicht geladen werden.' ), 502 );
		}

		// 7. Response parsen.
		$parsed = $this->news_parser->parse( $response->get_body() );

		// 8. Ergebnis cachen (15 Minuten fuer AJAX-Anfragen).
		if ( ! empty( $parsed['groups'] ) ) {
			$this->cache->set_data( $cache_key, $parsed, 900 );
		}

		// 9. JSON zurueckgeben.
		wp_send_json_success( $parsed );
	}

	/**
	 * Verarbeitet MMB-Suchanfragen.
	 *
	 * Ablauf:
	 * 1. Nonce pruefen
	 * 2. Suchbegriff sanitizen
	 * 3. kdnr serverseitig aus WordPress-Options laden
	 * 4. API-Aufruf an hintergrundladen.php (mit kdnr serverseitig)
	 * 5. Response parsen (DHPS_MMB_Search_Parser)
	 * 6. JSON zurueckgeben (OHNE kdnr!)
	 *
	 * @since 0.9.1
	 *
	 * @return void Gibt JSON aus und beendet die Ausfuehrung.
	 */
	public function handle_mmb_search(): void {
		if ( ! check_ajax_referer( 'dhps_mmb_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Ungueltige Anfrage.' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce bereits geprueft.
		$search      = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$service_tag = isset( $_POST['service_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['service_tag'] ) ) : 'mmb';
		// phpcs:enable

		if ( '' === $search ) {
			wp_send_json_error( array( 'message' => 'Kein Suchbegriff angegeben.' ), 400 );
		}

		// kdnr serverseitig laden - NIEMALS vom Client!
		$service = DHPS_Service_Registry::get_service( $service_tag );

		if ( null === $service ) {
			$service = DHPS_Service_Registry::get_service( 'mmb' );
		}

		$ota = get_option( $service['auth_option'], '' );

		if ( '' === $ota ) {
			wp_send_json_error( array( 'message' => 'Service nicht konfiguriert.' ), 400 );
		}

		$api_params = array(
			's'     => $search,
			'kd_nr' => $ota,
			'modus' => 'p',
		);

		$endpoint = 'einbau/mmo/merkblattpages/hintergrundladen.php';

		// Cache pruefen.
		$cache_key = $this->cache->generate_key( $endpoint, $api_params );
		$cached    = $this->cache->get_data( $cache_key );

		if ( null !== $cached ) {
			wp_send_json_success( $cached );
		}

		// API aufrufen.
		$response = $this->api->fetch( $endpoint, $api_params );

		if ( ! $response->is_success() ) {
			wp_send_json_error( array( 'message' => 'Suchergebnisse konnten nicht geladen werden.' ), 502 );
		}

		// Response parsen.
		$parser = new DHPS_MMB_Search_Parser();
		$parsed = $parser->parse( $response->get_body() );

		// Cachen (5 Minuten fuer Suchergebnisse).
		if ( ! empty( $parsed['results'] ) ) {
			$this->cache->set_data( $cache_key, $parsed, 300 );
		}

		wp_send_json_success( $parsed );
	}

	/**
	 * Proxyt PDF-Downloads fuer Merkblaetter.
	 *
	 * KRITISCH: Die kdnr wird NIEMALS an den Client gesendet.
	 * Der Proxy empfaengt nur die Merkblatt-ID und baut die
	 * vollstaendige URL serverseitig zusammen.
	 *
	 * @since 0.9.1
	 *
	 * @return void Streamt PDF und beendet die Ausfuehrung.
	 */
	public function handle_mmb_pdf_download(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET-Parameter, Nonce in Query.
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'dhps_mmb_nonce' ) ) {
			wp_die( 'Ungueltige Anfrage.', 'Fehler', array( 'response' => 403 ) );
		}

		$merkblatt   = isset( $_GET['merkblatt'] ) ? sanitize_text_field( wp_unslash( $_GET['merkblatt'] ) ) : '';
		$header      = isset( $_GET['header'] ) ? sanitize_text_field( wp_unslash( $_GET['header'] ) ) : '';
		$service_tag = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( $_GET['service'] ) ) : 'mmb';
		// Fallback: Legacy-Parameter 'id' akzeptieren.
		if ( '' === $merkblatt && isset( $_GET['id'] ) ) {
			$merkblatt = sanitize_text_field( wp_unslash( $_GET['id'] ) );
		}
		// phpcs:enable

		if ( '' === $merkblatt ) {
			wp_die( 'Fehlende Merkblatt-ID.', 'Fehler', array( 'response' => 400 ) );
		}

		// Erlaubte Services fuer PDF-Download (Whitelist).
		$allowed_services = array( 'mmb', 'mil', 'maes' );
		if ( ! in_array( $service_tag, $allowed_services, true ) ) {
			$service_tag = 'mmb';
		}

		// kdnr serverseitig laden.
		$service = DHPS_Service_Registry::get_service( $service_tag );
		$ota     = get_option( $service['auth_option'], '' );

		if ( '' === $ota ) {
			wp_die( 'Service nicht konfiguriert.', 'Fehler', array( 'response' => 400 ) );
		}

		// Download-Endpoint pro Service (mmo fuer MMB, mil fuer MIL).
		$endpoint_map = array(
			'mmb'  => 'einbau/mmo/controllers/download.php',
			'mil'  => 'einbau/mil/controllers/download.php',
			'maes' => 'einbau/infokombi/controllers/download.php',
		);
		$endpoint = $endpoint_map[ $service_tag ] ?? $endpoint_map['mmb'];

		// PDF-URL serverseitig zusammenbauen.
		$pdf_params = array(
			'kdnr'      => $ota,
			'merkblatt' => $merkblatt,
		);

		if ( '' !== $header ) {
			$pdf_params['header'] = $header;
		}

		$pdf_url = DEUBNER_HP_SERVICES_API_BASE . $endpoint . '?'
			. http_build_query( $pdf_params );

		// PDF-Datei vom Server laden und an Client streamen.
		$response = wp_remote_get( $pdf_url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $response ) ) {
			wp_die( 'PDF konnte nicht geladen werden.', 'Fehler', array( 'response' => 502 ) );
		}

		$body         = wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$status_code  = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code || empty( $body ) ) {
			wp_die( 'PDF konnte nicht geladen werden.', 'Fehler', array( 'response' => 502 ) );
		}

		// Sicherheitscheck: Nur PDF-Content durchlassen.
		$is_pdf = ( false !== strpos( $content_type, 'application/pdf' ) )
			|| ( '%PDF-' === substr( $body, 0, 5 ) );

		if ( ! $is_pdf ) {
			wp_die( 'Ungueltiges Antwortformat vom Server.', 'Fehler', array( 'response' => 502 ) );
		}

		header( 'Content-Type: application/pdf' );
		$file_prefix = ( 'mil' === $service_tag ) ? 'infografik_' : 'merkblatt_';
		header( 'Content-Disposition: inline; filename="' . $file_prefix . sanitize_file_name( $merkblatt ) . '.pdf"' );
		header( 'Content-Length: ' . strlen( $body ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF data.
		echo $body;
		exit;
	}

	/**
	 * Generiert die iframe-src-URL fuer TaxPlain-Videos.
	 *
	 * KRITISCH: Die kdnr wird serverseitig aus der Datenbank geladen
	 * und in die iframe-URL eingesetzt. Der Client sendet nur den
	 * video_slug und poster_url.
	 *
	 * @since 0.9.1
	 *
	 * @return void Gibt JSON mit iframe-src aus und beendet die Ausfuehrung.
	 */
	public function handle_tp_video_src(): void {
		if ( ! check_ajax_referer( 'dhps_tp_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Ungueltige Anfrage.' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce bereits geprueft.
		$video_slug = isset( $_POST['video_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['video_slug'] ) ) : '';
		$poster_url = isset( $_POST['poster_url'] ) ? esc_url_raw( wp_unslash( $_POST['poster_url'] ) ) : '';
		$v_modus    = isset( $_POST['v_modus'] ) ? absint( $_POST['v_modus'] ) : 0;
		// phpcs:enable

		if ( '' === $video_slug ) {
			wp_send_json_error( array( 'message' => 'Fehlender Video-Slug.' ), 400 );
		}

		// Authentifizierung serverseitig aus der Datenbank holen.
		// Primaer: kdnr (TaxPlain Teaser). Fallback: OTA (TaxPlain Videos).
		$kdnr = get_option( 'dhps_tp_kdnr', '' );

		if ( '' === $kdnr ) {
			$kdnr = get_option( 'dhps_ota_tp', '' );
		}

		if ( '' === $kdnr ) {
			wp_send_json_error( array( 'message' => 'Service nicht konfiguriert.' ), 400 );
		}

		// Vollstaendige iframe-URL zusammenbauen.
		$iframe_src = add_query_arg(
			array(
				'video'   => $video_slug,
				'poster'  => $poster_url,
				'kdnr'    => $kdnr,
				'v_modus' => $v_modus,
				'service' => 'taxplain',
			),
			'https://www.mandantenvideo.de/commons/bin_videos/videoshow_simple.html'
		);

		wp_send_json_success( array( 'src' => $iframe_src ) );
	}
}
