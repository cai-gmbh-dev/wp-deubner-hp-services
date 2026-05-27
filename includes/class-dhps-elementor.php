<?php
/**
 * Elementor-Loader fuer den Deubner Homepage Service.
 *
 * Registriert die Elementor-Widget-Kategorie und 9 service-spezifische
 * Elementor-Widgets, sobald Elementor geladen ist. Wird vom Plugin-Bootstrap
 * via dhps_init() instanziiert und initialisiert.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Elementor
 *
 * Elementor-Integrations-Loader. Prueft ob Elementor aktiv ist und
 * registriert die Widget-Kategorie sowie 9 service-spezifische Elementor-Widgets.
 *
 * @since   0.5.0
 * @package Deubner Homepage-Service
 */
class DHPS_Elementor {

	/**
	 * Mindest-Versionen der Elementor-Stack-Komponenten.
	 *
	 * Wird im Notice-Check (v0.16.1) und in der Doku
	 * `docs/team-knowledge/05-ELEMENTOR-4X-MIGRATION.md` referenziert.
	 *
	 * @since 0.16.1
	 * @since 0.16.2 Konstanten an Klassen-Anfang verschoben (QA M1).
	 */
	public const ELEMENTOR_MIN_VERSION = '4.1.0';

	/**
	 * Mindest-Version Elementor Pro (optional). Pro ist nicht zwingend, aber
	 * wenn aktiv: 4.1.0+ verlangt Free 4.1.0+.
	 *
	 * @since 0.16.1
	 * @since 0.16.2 Konstanten an Klassen-Anfang verschoben (QA M1).
	 */
	public const ELEMENTOR_PRO_MIN_VERSION = '4.1.0';

	/**
	 * Admin-Screen-IDs auf denen der Defensive-Version-Notice angezeigt wird.
	 *
	 * Beschraenkt die Notice auf relevante Kontexte. Andere Admin-Pages bleiben
	 * frei von der Warnung. Wirkt ueber `screen_id` aus `get_current_screen()`.
	 *
	 * @since 0.16.2 QA M3 - Notice-Scope-Beschraenkung.
	 */
	public const VERSION_NOTICE_SCREENS = array(
		'dashboard',                      // WP-Admin Startseite
		'plugins',                        // Plugins-Liste
		'toplevel_page_dhps_dashboard',   // DHPS Hauptmenue
		'deubner-verlag_page_dhps_dashboard', // DHPS Submenue
		'elementor_page_elementor-settings',  // Elementor-Settings (best-effort)
	);

	/**
	 * Content-Pipeline fuer Parsing und strukturiertes Rendering.
	 *
	 * @since 0.9.3
	 * @var DHPS_Content_Pipeline
	 */
	private $pipeline;

	/**
	 * API-Client fuer Steuertermine-Widget.
	 *
	 * @since 0.9.8
	 * @var DHPS_API_Client|null
	 */
	private $client;

	/**
	 * Cache fuer Steuertermine-Widget.
	 *
	 * @since 0.9.8
	 * @var DHPS_Cache|null
	 */
	private $cache;

	/**
	 * Konstruktor.
	 *
	 * @since 0.5.0
	 * @since 0.9.3 Ersetzt API-Client + Renderer durch Content-Pipeline.
	 * @since 0.9.8 Optionale Dependencies fuer Steuertermine-Widget.
	 *
	 * @param DHPS_Content_Pipeline    $pipeline Content-Pipeline-Instanz.
	 * @param DHPS_API_Client|null     $client   API-Client (optional, fuer Steuertermine).
	 * @param DHPS_Cache|null          $cache    Cache (optional, fuer Steuertermine).
	 */
	public function __construct( DHPS_Content_Pipeline $pipeline, ?DHPS_API_Client $client = null, ?DHPS_Cache $cache = null ) {
		$this->pipeline = $pipeline;
		$this->client   = $client;
		$this->cache    = $cache;
	}

	/**
	 * Initialisiert die Elementor-Integration.
	 *
	 * Prueft ob Elementor geladen ist und registriert die notwendigen
	 * Hooks fuer Widget-Kategorie und Widget-Registrierung.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Nur initialisieren wenn Elementor geladen ist.
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );

		// Defensive Version-Check (seit 0.16.1).
		add_action( 'admin_notices', array( $this, 'maybe_render_version_notice' ) );
	}

	/**
	 * Zeigt Admin-Notice falls Elementor-Versionen unter dem unterstuetzten
	 * Minimum liegen. Adressiert das User-Live-Symptom "klappt nicht mehr"
	 * bei Free/Pro-Mismatch.
	 *
	 * @since 0.16.1
	 * @since 0.16.2 Cap-Check auf `activate_plugins` (QA M2), Scope-Beschraenkung
	 *               auf relevante Admin-Screens (QA M3).
	 *
	 * @return void
	 */
	public function maybe_render_version_notice(): void {
		// activate_plugins ist semantisch genauer als manage_options - es geht
		// um den Aktor, der Plugin-Updates anstossen kann (QA M2).
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Scope auf relevante Admin-Screens beschraenken (QA M3).
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( null !== $screen
				&& ! in_array( $screen->id, self::VERSION_NOTICE_SCREENS, true ) ) {
				return;
			}
		}

		$messages = array();

		if ( defined( 'ELEMENTOR_VERSION' )
			&& version_compare( ELEMENTOR_VERSION, self::ELEMENTOR_MIN_VERSION, '<' ) ) {
			$messages[] = sprintf(
				/* translators: 1: aktuelle Free-Version, 2: Mindest-Version */
				esc_html__( 'Deubner HP Services: Elementor %1$s erkannt - empfohlen ist mindestens %2$s.', 'deubner_hp_services' ),
				esc_html( ELEMENTOR_VERSION ),
				esc_html( self::ELEMENTOR_MIN_VERSION )
			);
		}

		if ( defined( 'ELEMENTOR_PRO_VERSION' )
			&& version_compare( ELEMENTOR_PRO_VERSION, self::ELEMENTOR_PRO_MIN_VERSION, '<' ) ) {
			$messages[] = sprintf(
				/* translators: 1: aktuelle Pro-Version, 2: Mindest-Version */
				esc_html__( 'Deubner HP Services: Elementor Pro %1$s erkannt - empfohlen ist mindestens %2$s.', 'deubner_hp_services' ),
				esc_html( ELEMENTOR_PRO_VERSION ),
				esc_html( self::ELEMENTOR_PRO_MIN_VERSION )
			);
		}

		if ( empty( $messages ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			implode( '<br />', $messages ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each line already esc_html'd.
		);
	}

	/**
	 * Registriert die Deubner-Services-Kategorie im Elementor-Panel.
	 *
	 * @since 0.5.0
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor Elements-Manager.
	 *
	 * @return void
	 */
	public function register_category( $elements_manager ): void {
		$elements_manager->add_category(
			'dhps-services',
			array(
				'title' => 'Deubner Services',
				'icon'  => 'eicon-globe',
			)
		);
	}

	/**
	 * Registriert 9 service-spezifische Elementor-Widgets.
	 *
	 * Laedt die Widget-Dateien manuell (nicht ueber den Autoloader, da sie
	 * ausserhalb von includes/ liegen), injiziert die Abhaengigkeiten ueber
	 * die statische set_dependencies()-Methode und registriert alle Widgets.
	 *
	 * @since 0.5.0
	 * @since 0.7.0 Ersetzt einzelnes generisches Widget durch 9 service-spezifische Widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor Widgets-Manager.
	 *
	 * @return void
	 */
	public function register_widgets( $widgets_manager ): void {
		require_once DEUBNER_HP_SERVICES_PATH . 'widgets/elementor/class-dhps-elementor-widget-base.php';
		require_once DEUBNER_HP_SERVICES_PATH . 'widgets/elementor/class-dhps-elementor-service-widgets.php';
		require_once DEUBNER_HP_SERVICES_PATH . 'widgets/elementor/class-dhps-elementor-widget-steuertermine.php';
		require_once DEUBNER_HP_SERVICES_PATH . 'widgets/elementor/class-dhps-elementor-maes-widgets.php';

		DHPS_Elementor_Widget_Base::set_dependencies( $this->pipeline );

		// 9 Service-Widgets.
		$widget_classes = array(
			'DHPS_Elementor_Widget_MIO',
			'DHPS_Elementor_Widget_LXMIO',
			'DHPS_Elementor_Widget_MMB',
			'DHPS_Elementor_Widget_MIL',
			'DHPS_Elementor_Widget_TP',
			'DHPS_Elementor_Widget_TPT',
			'DHPS_Elementor_Widget_TC',
			'DHPS_Elementor_Widget_MAES',
			'DHPS_Elementor_Widget_LP',
		);

		foreach ( $widget_classes as $class ) {
			$widgets_manager->register( new $class() );
		}

		// Steuertermine-Widget (eigenstaendig, mit eigenen Dependencies).
		if ( null !== $this->client && null !== $this->cache ) {
			DHPS_Elementor_Widget_Steuertermine::set_dependencies( $this->client, $this->cache );
			$widgets_manager->register( new DHPS_Elementor_Widget_Steuertermine() );

			// MAES modulare Widgets (Videos, Merkblaetter, Aktuelles).
			DHPS_Elementor_MAES_Base::set_dependencies( $this->client, $this->cache );
			$widgets_manager->register( new DHPS_Elementor_Widget_MAES_Videos() );
			$widgets_manager->register( new DHPS_Elementor_Widget_MAES_Merkblaetter() );
			$widgets_manager->register( new DHPS_Elementor_Widget_MAES_Aktuelles() );
		}
	}
}
