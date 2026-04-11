<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Abstrakte Basis-Klasse fuer Deubner Elementor-Widgets.
 *
 * Stellt die gemeinsame Funktionalitaet fuer alle 9 service-spezifischen
 * Elementor-Widgets bereit. Jede Subklasse definiert nur den Service-Key
 * und optional ein eigenes Icon.
 *
 * Die Abhaengigkeiten (API-Client, Renderer) werden ueber statische
 * Properties injiziert, da Elementor Widgets intern mit
 * `new WidgetClass($data, $args)` instanziiert und keine Custom-DI
 * im Konstruktor erlaubt.
 *
 * HINWEIS: Diese Datei liegt ausserhalb von includes/ und wird NICHT
 * vom Autoloader geladen. Sie wird manuell von DHPS_Elementor::register_widgets()
 * eingebunden. Daher entfaellt der ABSPATH-Check.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Widgets/Elementor
 * @since      0.7.0
 */

/**
 * Class DHPS_Elementor_Widget_Base
 *
 * Abstrakte Basis-Klasse fuer alle Deubner Elementor-Widgets.
 * Enthaelt die gemeinsame Control-Registrierung, Rendering-Logik
 * und statische Dependency-Injection.
 *
 * @since   0.7.0
 * @package Deubner Homepage-Service
 */
abstract class DHPS_Elementor_Widget_Base extends \Elementor\Widget_Base {

	/**
	 * Content-Pipeline fuer Parsing und strukturiertes Rendering.
	 *
	 * Statisch, da Elementor Widgets mit ($data, $args) instanziiert
	 * und keine zusaetzlichen Konstruktor-Parameter erlaubt.
	 *
	 * @since 0.9.3
	 * @var DHPS_Content_Pipeline|null
	 */
	private static $pipeline = null;

	/**
	 * Setzt die gemeinsamen Abhaengigkeiten fuer alle Widget-Instanzen.
	 *
	 * Muss einmalig vor der Widget-Registrierung aufgerufen werden,
	 * typischerweise in DHPS_Elementor::register_widgets().
	 *
	 * @since 0.7.0
	 * @since 0.9.3 Ersetzt API-Client + Renderer durch Content-Pipeline.
	 *
	 * @param DHPS_Content_Pipeline $pipeline Content-Pipeline-Instanz.
	 *
	 * @return void
	 */
	public static function set_dependencies( DHPS_Content_Pipeline $pipeline ): void {
		self::$pipeline = $pipeline;
	}

	/**
	 * Konstruktor.
	 *
	 * Standard-Elementor-Signatur ohne Custom-Parameter.
	 * Leitet alle Argumente an den Parent-Konstruktor weiter.
	 *
	 * @since 0.7.0
	 *
	 * @param array      $data Widget-Daten (Elementor-intern).
	 * @param array|null $args Widget-Argumente (Elementor-intern).
	 */
	public function __construct( array $data = array(), ?array $args = null ) {
		parent::__construct( $data, $args );
	}

	/**
	 * Gibt den Service-Key der konkreten Subklasse zurueck.
	 *
	 * Muss von jeder Subklasse implementiert werden und den
	 * Registry-Schluessel des jeweiligen Service liefern (z.B. 'mio', 'tp').
	 *
	 * @since 0.7.0
	 *
	 * @return string Service-Key aus der DHPS_Service_Registry.
	 */
	abstract protected function get_service_key(): string;

	/**
	 * Gibt den internen Widget-Namen zurueck.
	 *
	 * Wird aus dem Service-Key generiert mit 'dhps-' Prefix.
	 *
	 * @since 0.7.0
	 *
	 * @return string Widget-Name (z.B. 'dhps-mio').
	 */
	public function get_name(): string {
		return 'dhps-' . $this->get_service_key();
	}

	/**
	 * Gibt den Anzeige-Titel des Widgets zurueck.
	 *
	 * Holt den Service-Namen aus der Registry und stellt 'Deubner: ' voran.
	 *
	 * @since 0.7.0
	 *
	 * @return string Widget-Titel (z.B. 'Deubner: MI-Online Steuerrecht').
	 */
	public function get_title(): string {
		$service = DHPS_Service_Registry::get_service( $this->get_service_key() );

		if ( null === $service ) {
			return 'Deubner: ' . strtoupper( $this->get_service_key() );
		}

		return 'Deubner: ' . $service['name'];
	}

	/**
	 * Gibt das Icon fuer das Elementor-Panel zurueck.
	 *
	 * Standard-Icon, wird von Subklassen ueberschrieben.
	 *
	 * @since 0.7.0
	 *
	 * @return string Elementor-Icon-Klasse.
	 */
	public function get_icon(): string {
		return 'eicon-code';
	}

	/**
	 * Gibt die Kategorien zurueck, in denen das Widget erscheint.
	 *
	 * @since 0.7.0
	 *
	 * @return array Liste der Kategorie-Slugs.
	 */
	public function get_categories(): array {
		return array( 'dhps-services' );
	}

	/**
	 * Registriert die Elementor-Controls fuer das Widget.
	 *
	 * Definiert zwei Sections:
	 * - Inhalt: Service-spezifische Controls, Layout, CSS-Klasse, Cache-Dauer
	 * - Darstellung: Innenabstand (nur bei Card-Layout), Eckenradius
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$service = DHPS_Service_Registry::get_service( $this->get_service_key() );

		/*
		 * ---------------------------------------------------------------
		 * Section 1: Inhalt
		 * ---------------------------------------------------------------
		 */
		$this->start_controls_section(
			'section_content',
			array(
				'label' => 'Inhalt',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		// Service-spezifische Controls aus der Registry generieren.
		if ( null !== $service ) {
			$skip_keys = array( 'layout', 'class', 'cache' );

			foreach ( $service['shortcode_atts'] as $att_key => $att_default ) {
				if ( in_array( $att_key, $skip_keys, true ) ) {
					continue;
				}

				$control_config = $this->get_control_config( $att_key, $att_default );
				$this->add_control( $att_key, $control_config );
			}
		}

		// Separator vor den universellen Controls.
		$this->add_control(
			'separator_universal',
			array(
				'type' => \Elementor\Controls_Manager::DIVIDER,
			)
		);

		// Layout-Auswahl aus dem Renderer.
		$this->add_control(
			'layout',
			array(
				'label'   => 'Layout',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => DHPS_Renderer::get_available_layouts(),
				'default' => 'default',
			)
		);

		// Optionale CSS-Klasse.
		$this->add_control(
			'custom_class',
			array(
				'label'   => 'CSS-Klasse',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '',
			)
		);

		// Cache-Dauer in Sekunden.
		$this->add_control(
			'cache_ttl',
			array(
				'label'   => 'Cache-Dauer (Sekunden)',
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
			)
		);

		$this->end_controls_section();

		/*
		 * ---------------------------------------------------------------
		 * Section 2: Video-Layout (nur fuer TP/TPT)
		 * ---------------------------------------------------------------
		 */
		$video_services = array( 'tp', 'tpt' );
		if ( in_array( $this->get_service_key(), $video_services, true ) ) {
			$this->start_controls_section(
				'section_video_layout',
				array(
					'label' => 'Video-Layout',
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			// Grid-Spalten (1-4).
			$this->add_control( 'tp_columns', array(
				'label'   => 'Spalten',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'1' => '1 Spalte',
					'2' => '2 Spalten',
					'3' => '3 Spalten',
					'4' => '4 Spalten',
				),
				'default' => '3',
			) );

			// Lazy Loading Count.
			$this->add_control( 'tp_lazy_count', array(
				'label'       => 'Initiale Videos',
				'description' => 'Anzahl der sofort sichtbaren Videos (0 = alle)',
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'default'     => 0,
				'min'         => 0,
				'max'         => 100,
			) );

			// Lazy Loading Mode.
			$this->add_control( 'tp_lazy_mode', array(
				'label'     => 'Nachladen',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => array(
					'manual' => 'Manuell (Button)',
					'auto'   => 'Automatisch (Scroll)',
				),
				'default'   => 'manual',
				'condition' => array( 'tp_lazy_count!' => 0 ),
			) );

			// Style Preset.
			$this->add_control( 'tp_style', array(
				'label'   => 'Stil',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'default' => 'Standard',
					'minimal' => 'Minimal',
					'shadow'  => 'Schatten',
				),
				'default' => 'default',
			) );

			$this->end_controls_section();
		}

		/*
		 * ---------------------------------------------------------------
		 * Section 3: Stil - Ueberschriften
		 * ---------------------------------------------------------------
		 */
		$this->start_controls_section(
			'section_style_heading',
			array(
				'label' => 'Ueberschriften',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_color',
			array(
				'label'     => 'Farbe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dhps-tp-featured__heading, {{WRAPPER}} .dhps-tp-catalog__heading' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'heading_font_size',
			array(
				'label'      => 'Schriftgroesse',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px' => array( 'min' => 10, 'max' => 48 ),
					'em' => array( 'min' => 0.5, 'max' => 3, 'step' => 0.1 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .dhps-tp-featured__heading, {{WRAPPER}} .dhps-tp-catalog__heading' => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'heading_font_weight',
			array(
				'label'   => 'Schriftstaerke',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					''    => 'Standard',
					'400' => 'Normal (400)',
					'500' => 'Medium (500)',
					'600' => 'Semibold (600)',
					'700' => 'Bold (700)',
				),
				'selectors' => array(
					'{{WRAPPER}} .dhps-tp-featured__heading, {{WRAPPER}} .dhps-tp-catalog__heading' => 'font-weight: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'heading_margin_bottom',
			array(
				'label'      => 'Abstand unten',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 60 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .dhps-tp-featured__heading, {{WRAPPER}} .dhps-tp-catalog__heading' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/*
		 * ---------------------------------------------------------------
		 * Section 4: Stil - Cards
		 * ---------------------------------------------------------------
		 */
		$this->start_controls_section(
			'section_style_card',
			array(
				'label' => 'Video-Cards',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'card_background',
			array(
				'label'     => 'Hintergrund',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dhps-tp-card' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'card_border_color',
			array(
				'label'     => 'Rahmenfarbe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dhps-tp-card' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'card_border_radius',
			array(
				'label'      => 'Eckenradius',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .dhps-tp-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'card_padding',
			array(
				'label'      => 'Innenabstand (Body)',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .dhps-tp-card__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'card_gap',
			array(
				'label'      => 'Abstand zwischen Cards',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 60 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .dhps-tp-grid' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/*
		 * ---------------------------------------------------------------
		 * Section 5: Stil - Text
		 * ---------------------------------------------------------------
		 */
		$this->start_controls_section(
			'section_style_text',
			array(
				'label' => 'Text',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => 'Titel-Farbe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dhps-tp-card__title, {{WRAPPER}} .dhps-tp-video__title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'title_font_size',
			array(
				'label'      => 'Titel-Groesse',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array(
					'px' => array( 'min' => 10, 'max' => 32 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .dhps-tp-card__title, {{WRAPPER}} .dhps-tp-video__title' => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'teaser_color',
			array(
				'label'     => 'Teaser-Farbe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dhps-tp-card__teaser, {{WRAPPER}} .dhps-tp-video__teaser' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'date_color',
			array(
				'label'     => 'Datum-Farbe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dhps-tp-card__date, {{WRAPPER}} .dhps-tp-video__date' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		/*
		 * ---------------------------------------------------------------
		 * Section 6: Stil - Button
		 * ---------------------------------------------------------------
		 */
		$this->start_controls_section(
			'section_style_button',
			array(
				'label' => 'Buttons',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'btn_heading_filter',
			array(
				'label' => 'Filter-Buttons',
				'type'  => \Elementor\Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'filter_btn_color',
			array(
				'label'     => 'Textfarbe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dhps-filter-bar__btn' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'filter_btn_active_bg',
			array(
				'label'     => 'Aktiv: Hintergrund',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dhps-filter-bar__btn--active, {{WRAPPER}} .dhps-filter-bar__btn[aria-pressed="true"]' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'filter_btn_active_color',
			array(
				'label'     => 'Aktiv: Textfarbe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dhps-filter-bar__btn--active, {{WRAPPER}} .dhps-filter-bar__btn[aria-pressed="true"]' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'filter_btn_border_radius',
			array(
				'label'      => 'Eckenradius',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 30 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .dhps-filter-bar__btn' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'btn_heading_loadmore',
			array(
				'label'     => 'Laden-Button',
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'loadmore_btn_bg',
			array(
				'label'     => 'Hintergrund',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dhps-tp-load-more' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'loadmore_btn_color',
			array(
				'label'     => 'Textfarbe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .dhps-tp-load-more' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'loadmore_btn_border_radius',
			array(
				'label'      => 'Eckenradius',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 30 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .dhps-tp-load-more' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/*
		 * ---------------------------------------------------------------
		 * Section 7: Stil - Container (Card-Layout)
		 * ---------------------------------------------------------------
		 */
		$this->start_controls_section(
			'section_style_container',
			array(
				'label'     => 'Container',
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array(
					'layout' => 'card',
				),
			)
		);

		$this->add_control(
			'container_padding',
			array(
				'label'      => 'Innenabstand',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .dhps-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'container_border_radius',
			array(
				'label'      => 'Eckenradius',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .dhps-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Gibt die Elementor-Control-Konfiguration fuer ein Shortcode-Attribut zurueck.
	 *
	 * Bestimmt anhand des Attribut-Namens den passenden Control-Typ:
	 * - Select-Felder: variante, teasermodus, show_teaser, modus
	 * - Number-Felder: einzelvideo, anzahl
	 * - Text-Felder: alles andere
	 *
	 * @since 0.7.0
	 *
	 * @param string $key     Attribut-Name (z.B. 'variante', 'filter').
	 * @param mixed  $default Standard-Wert aus der Service-Registry.
	 *
	 * @return array Elementor-Control-Konfigurationsarray.
	 */
	private function get_control_config( string $key, $default ): array {
		$label = $this->get_control_label( $key );

		// Select-Felder mit vordefinierten Optionen.
		$select_fields = array(
			'variante'   => array(
				''              => '(Admin-Einstellung)',
				'tagesaktuell'  => 'Tagesaktuell',
				'kategorisiert' => 'Kategorisiert',
			),
			'teasermodus' => array(
				''  => '(Standard)',
				'0' => 'Aus',
				'1' => 'An',
			),
			'show_teaser' => array(
				''  => '(Standard)',
				'0' => 'Aus',
				'1' => 'An',
			),
			'modus'       => array(
				''         => '(Admin-Einstellung)',
				'standard' => 'Standard',
				'p'        => 'Nur Titelbild',
				't'        => 'Nur Titel',
				'pt'       => 'Titel und Titelbild',
			),
		);

		if ( isset( $select_fields[ $key ] ) ) {
			return array(
				'label'   => $label,
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $select_fields[ $key ],
				'default' => '',
			);
		}

		// Number-Felder.
		$number_fields = array( 'einzelvideo', 'anzahl' );

		if ( in_array( $key, $number_fields, true ) ) {
			return array(
				'label'   => $label,
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => '',
			);
		}

		// Text-Felder (Fallback).
		return array(
			'label'   => $label,
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => '',
		);
	}

	/**
	 * Gibt das deutsche Label fuer ein Shortcode-Attribut zurueck.
	 *
	 * Verwendet eine Mapping-Tabelle fuer bekannte Attribute und
	 * generiert fuer unbekannte einen lesbaren Fallback.
	 *
	 * @since 0.7.0
	 *
	 * @param string $key Attribut-Name (z.B. 'variante', 'st_kategorie').
	 *
	 * @return string Deutsches Label fuer das Elementor-Control.
	 */
	private function get_control_label( string $key ): string {
		$labels = array(
			'variante'     => 'Variante',
			'modus'        => 'Modus',
			'st_kategorie' => 'Steuer-Kategorie',
			'filter'       => 'Filter',
			'teasermodus'  => 'Teaser-Modus',
			'einzelvideo'  => 'Einzelvideo-ID',
			'videoliste'   => 'Videoliste',
			'id_merkblatt' => 'Merkblatt-ID',
			'rubrik'       => 'Rubrik',
			'show_teaser'  => 'Teaser anzeigen',
			'anzahl'       => 'Anzahl',
		);

		if ( isset( $labels[ $key ] ) ) {
			return $labels[ $key ];
		}

		// Fallback: Unterstriche durch Leerzeichen ersetzen, erster Buchstabe gross.
		return ucfirst( str_replace( '_', ' ', $key ) );
	}

	/**
	 * Rendert die Widget-Ausgabe im Frontend und in der Elementor-Vorschau.
	 *
	 * Baut die API-Parameter analog zur Shortcode-Klasse auf:
	 * 1. Auth-Parameter (ota/kdnr)
	 * 2. Default-Params des Service
	 * 3. Admin-Options (inkl. variante_switch-Logik)
	 * 4. Shortcode-Defaults und Widget-Overrides
	 * 5. Ausgabe ueber die Content-Pipeline (Fetch, Parse, Render)
	 *
	 * @since 0.7.0
	 * @since 0.9.3 Verwendet Content-Pipeline statt Raw-HTML-Renderer.
	 *
	 * @return void
	 */
	protected function render(): void {
		// Guard: Abhaengigkeiten muessen gesetzt sein.
		if ( null === self::$pipeline ) {
			return;
		}

		$settings    = $this->get_settings_for_display();
		$service_key = $this->get_service_key();
		$service     = DHPS_Service_Registry::get_service( $service_key );

		if ( null === $service ) {
			return;
		}

		// Widget-Einstellungen extrahieren.
		$layout       = $settings['layout'];
		$custom_class = $settings['custom_class'] ?? '';
		$cache_ttl    = absint( $settings['cache_ttl'] ?? 3600 );

		// Parameter-Array aufbauen.
		$params = array();

		// 1. Auth-Parameter (ota oder kdnr).
		$params[ $service['auth_type'] ] = get_option( $service['auth_option'], '' );

		// 2. Default-Params des Service (z.B. modus => 'p').
		foreach ( $service['default_params'] as $key => $value ) {
			$params[ $key ] = $value;
		}

		// 3. Admin-Options verarbeiten.
		foreach ( $service['admin_options'] as $option_key => $param_type ) {
			$option_value = get_option( $option_key, '' );

			if ( 'variante_switch' === $param_type ) {
				// Variante-Switch-Logik: Admin-Wert in Variante-String uebersetzen.
				switch ( $option_value ) {
					case '1':
						$params['variante'] = 'tagesaktuell';
						break;

					case '2':
						$params['variante'] = 'kategorisiert';
						break;
				}
				continue;
			}

			// Regulaere Admin-Option: direkt als API-Parameter uebernehmen.
			if ( '' !== $option_value ) {
				$params[ $param_type ] = $option_value;
			}
		}

		// 4. Shortcode-Defaults und Widget-Overrides.
		$skip_keys = array( 'layout', 'class', 'cache' );

		foreach ( $service['shortcode_atts'] as $att_key => $att_default ) {
			if ( in_array( $att_key, $skip_keys, true ) ) {
				continue;
			}

			// Default-Wert aus der Registry setzen, wenn nicht leer.
			if ( '' !== (string) $att_default && ! isset( $params[ $att_key ] ) ) {
				$params[ $att_key ] = $att_default;
			}

			// Widget-Setting drueberschreiben, wenn nicht leer.
			$widget_value = $settings[ $att_key ] ?? '';
			if ( '' !== (string) $widget_value ) {
				$params[ $att_key ] = $widget_value;
			}
		}

		// TP-spezifische Settings als Filter setzen (werden im Template ausgelesen).
		$video_services = array( 'tp', 'tpt' );
		if ( in_array( $service_key, $video_services, true ) ) {
			$tp_columns    = $settings['tp_columns'] ?? '3';
			$tp_lazy_count = $settings['tp_lazy_count'] ?? 0;
			$tp_lazy_mode  = $settings['tp_lazy_mode'] ?? 'manual';
			$tp_style      = $settings['tp_style'] ?? 'default';

			add_filter( 'dhps_tp_grid_columns', function () use ( $tp_columns ) { return $tp_columns; } );
			add_filter( 'dhps_tp_lazy_count', function () use ( $tp_lazy_count ) { return $tp_lazy_count; } );
			add_filter( 'dhps_tp_lazy_mode', function () use ( $tp_lazy_mode ) { return $tp_lazy_mode; } );
			add_filter( 'dhps_tp_style', function () use ( $tp_style ) { return $tp_style; } );
		}

		// 5. Inhalt ueber die Content-Pipeline abrufen, parsen und rendern.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML stammt vom Deubner-API-Endpoint und wird ueber die Pipeline verarbeitet.
		echo self::$pipeline->render_service( $service_key, $service['endpoint'], $params, $cache_ttl, $layout, $custom_class );

		// TP-Filter wieder entfernen, damit sie nicht in andere Widget-Instanzen leaken.
		if ( in_array( $service_key, $video_services, true ) ) {
			remove_all_filters( 'dhps_tp_grid_columns' );
			remove_all_filters( 'dhps_tp_lazy_count' );
			remove_all_filters( 'dhps_tp_lazy_mode' );
			remove_all_filters( 'dhps_tp_style' );
		}
	}
}
