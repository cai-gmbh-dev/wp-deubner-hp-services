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
