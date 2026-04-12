<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor-Widget: Steuertermine.
 *
 * Eigenstaendiges Widget zur Anzeige von Steuerterminen auf
 * Startseiten, in Sidebars oder Headern. Nutzt den bestehenden
 * MIO-Parser und die DHPS_Steuertermine-Klasse.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Widgets/Elementor
 * @since      0.9.8
 */
class DHPS_Elementor_Widget_Steuertermine extends \Elementor\Widget_Base {

    /**
     * @var DHPS_API_Client|null
     */
    private static $client = null;

    /**
     * @var DHPS_Cache|null
     */
    private static $cache = null;

    /**
     * Setzt die Abhaengigkeiten (statisch, vor Widget-Registrierung).
     *
     * @param DHPS_API_Client $client API-Client.
     * @param DHPS_Cache      $cache  Cache-Instanz.
     */
    public static function set_dependencies( DHPS_API_Client $client, DHPS_Cache $cache ): void {
        self::$client = $client;
        self::$cache  = $cache;
    }

    public function get_name(): string {
        return 'dhps-steuertermine';
    }

    public function get_title(): string {
        return 'Steuertermine';
    }

    public function get_icon(): string {
        return 'eicon-calendar';
    }

    public function get_categories(): array {
        return array( 'dhps-services' );
    }

    public function get_keywords(): array {
        return array( 'steuer', 'termine', 'deubner', 'mio' );
    }

    protected function register_controls(): void {

        // Content.
        $this->start_controls_section( 'section_content', array(
            'label' => 'Inhalt',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ) );

        $this->add_control( 'termine_month', array(
            'label'   => 'Monat',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => array(
                'all'     => 'Beide Monate',
                'current' => 'Aktueller Monat',
                'next'    => 'Naechster Monat',
            ),
            'default' => 'all',
        ) );

        $this->add_control( 'termine_count', array(
            'label'       => 'Anzahl Termine',
            'description' => '0 = alle Termine des Monats anzeigen',
            'type'        => \Elementor\Controls_Manager::NUMBER,
            'default'     => 0,
            'min'         => 0,
            'max'         => 20,
        ) );

        $this->add_control( 'termine_layout', array(
            'label'   => 'Layout',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => array(
                'default' => 'Standard (Grid)',
                'card'    => 'Card',
                'inline'  => 'Inline (einzeilig)',
                'compact' => 'Kompakt',
            ),
            'default' => 'default',
        ) );

        $this->add_control( 'termine_class', array(
            'label'   => 'CSS-Klasse',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ) );

        $this->add_control( 'termine_cache', array(
            'label'   => 'Cache-Dauer (Sekunden)',
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 3600,
            'min'     => 0,
            'max'     => 86400,
        ) );

        $this->end_controls_section();

        // Stil.
        $this->start_controls_section( 'section_style', array(
            'label' => 'Stil',
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'heading_color', array(
            'label'     => 'Titel-Farbe',
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .dhps-termine__title' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'heading_typography',
            'label'    => 'Titel-Typografie',
            'selector' => '{{WRAPPER}} .dhps-termine__title',
        ) );

        $this->add_control( 'date_color', array(
            'label'     => 'Datum-Farbe',
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .dhps-termine__date' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'taxes_color', array(
            'label'     => 'Steuerarten-Farbe',
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .dhps-termine__taxes' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
            'name'     => 'card_border',
            'label'    => 'Rahmen',
            'selector' => '{{WRAPPER}} .dhps-termine__month',
        ) );

        $this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'card_shadow',
            'label'    => 'Schatten',
            'selector' => '{{WRAPPER}} .dhps-termine__month',
        ) );

        $this->add_control( 'card_padding', array(
            'label'      => 'Innenabstand',
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .dhps-termine__month' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'grid_gap', array(
            'label'      => 'Abstand zwischen Monaten',
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array(
                'px' => array( 'min' => 0, 'max' => 60 ),
            ),
            'selectors'  => array(
                '{{WRAPPER}} .dhps-termine__grid' => 'gap: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();
    }

    protected function render(): void {
        if ( null === self::$client || null === self::$cache ) {
            return;
        }

        $settings = $this->get_settings_for_display();

        $atts = array(
            'month'  => $settings['termine_month'] ?? 'all',
            'count'  => $settings['termine_count'] ?? 0,
            'layout' => $settings['termine_layout'] ?? 'default',
            'class'  => $settings['termine_class'] ?? '',
            'cache'  => $settings['termine_cache'] ?? 3600,
        );

        $steuertermine = new DHPS_Steuertermine( self::$client, self::$cache );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $steuertermine->render( $atts );
    }
}
