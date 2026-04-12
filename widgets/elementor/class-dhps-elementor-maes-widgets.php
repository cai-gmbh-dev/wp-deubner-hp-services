<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drei eigenstaendige Elementor-Widgets fuer MAES (Meine Aerzteseite).
 *
 * Jedes Widget hat volle Elementor-Pro-Style-Controls:
 * Typografie, Farben, Border, Box-Shadow, Padding, Normal/Hover Tabs.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Widgets/Elementor
 * @since      0.10.1
 */

/**
 * Basis fuer MAES-Module (gemeinsame Dependencies + Style-Helper).
 */
abstract class DHPS_Elementor_MAES_Base extends \Elementor\Widget_Base {

	/** @var DHPS_API_Client|null */
	protected static $client = null;

	/** @var DHPS_Cache|null */
	protected static $cache = null;

	public static function set_dependencies( DHPS_API_Client $client, DHPS_Cache $cache ): void {
		self::$client = $client;
		self::$cache  = $cache;
	}

	public function get_categories(): array {
		return array( 'dhps-services' );
	}

	protected function get_maes_data(): ?array {
		if ( null === self::$client || null === self::$cache ) {
			return null;
		}

		$service = DHPS_Service_Registry::get_service( 'maes' );
		if ( null === $service ) {
			return null;
		}

		$kdnr = get_option( $service['auth_option'], '' );
		if ( empty( $kdnr ) ) {
			return null;
		}

		$params    = array( 'kdnr' => $kdnr, 'modus' => 'p' );
		$cache_key = 'dhps_p_' . md5( $service['endpoint'] . '|' . wp_json_encode( $params ) );
		$parsed    = self::$cache->get_data( $cache_key );

		if ( null === $parsed ) {
			$html = self::$client->fetch_content( $service['endpoint'], $params, 3600 );
			if ( empty( $html ) || 0 === strpos( trim( $html ), '<!-- DHPS:' ) ) {
				return null;
			}

			$parser = DHPS_Parser_Registry::get_parser( 'maes' );
			if ( null === $parser ) {
				return null;
			}

			$parsed = $parser->parse( $html );
			$parsed['service_tag'] = 'maes';
			self::$cache->set_data( $cache_key, $parsed, 3600 );
		}

		return $parsed;
	}
}

/* =========================================================================
   MAES Videos - Volle Controls wie TP
   ========================================================================= */

class DHPS_Elementor_Widget_MAES_Videos extends DHPS_Elementor_MAES_Base {

	public function get_name(): string { return 'dhps-maes-videos'; }
	public function get_title(): string { return 'MAES Videos'; }
	public function get_icon(): string { return 'eicon-play'; }
	public function get_keywords(): array { return array( 'maes', 'video', 'aerzte', 'heilberufe' ); }

	protected function register_controls(): void {

		// --- Inhalt ---
		$this->start_controls_section( 'section_content', array(
			'label' => 'Inhalt',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'layout', array(
			'label'   => 'Layout',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'default' => 'Standard', 'card' => 'Card', 'compact' => 'Kompakt' ),
			'default' => 'default',
		) );

		$this->add_control( 'columns', array(
			'label'   => 'Spalten',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
			'default' => '2',
		) );

		$this->add_control( 'lazy_count', array(
			'label'       => 'Initiale Videos',
			'description' => '0 = alle anzeigen',
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'default'     => 0, 'min' => 0, 'max' => 50,
		) );

		$this->add_control( 'lazy_mode', array(
			'label'     => 'Nachladen',
			'type'      => \Elementor\Controls_Manager::SELECT,
			'options'   => array( 'manual' => 'Manuell (Button)', 'auto' => 'Automatisch (Scroll)' ),
			'default'   => 'manual',
			'condition' => array( 'lazy_count!' => 0 ),
		) );

		$this->add_control( 'video_mode', array(
			'label'   => 'Video-Wiedergabe',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'inline' => 'Inline', 'modal' => 'Popup (zentriert)' ),
			'default' => 'inline',
		) );

		$this->add_control( 'style_preset', array(
			'label'   => 'Stil',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'default' => 'Standard', 'minimal' => 'Minimal', 'shadow' => 'Schatten' ),
			'default' => 'default',
		) );

		$this->add_control( 'separator_universal', array(
			'type' => \Elementor\Controls_Manager::DIVIDER,
		) );

		$this->add_control( 'custom_class', array(
			'label' => 'CSS-Klasse', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '',
		) );

		$this->add_control( 'cache_ttl', array(
			'label' => 'Cache (Sekunden)', 'type' => \Elementor\Controls_Manager::NUMBER,
			'default' => 3600, 'min' => 0, 'max' => 86400,
		) );

		$this->end_controls_section();

		// --- Stil: Ueberschriften ---
		$this->start_controls_section( 'section_style_heading', array(
			'label' => 'Ueberschriften', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name' => 'heading_typo', 'selector' => '{{WRAPPER}} .dhps-maes-section__title, {{WRAPPER}} h3',
		) );

		$this->add_control( 'heading_color', array(
			'label' => 'Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-maes-section__title, {{WRAPPER}} h3' => 'color: {{VALUE}};' ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Text_Shadow::get_type(), array(
			'name' => 'heading_shadow', 'selector' => '{{WRAPPER}} .dhps-maes-section__title, {{WRAPPER}} h3',
		) );

		$this->end_controls_section();

		// --- Stil: Cards ---
		$this->start_controls_section( 'section_style_card', array(
			'label' => 'Cards', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name' => 'card_border', 'selector' => '{{WRAPPER}} .dhps-tp-card',
		) );

		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .dhps-tp-card',
		) );

		$this->add_control( 'card_border_radius', array(
			'label' => 'Eckenradius', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors' => array( '{{WRAPPER}} .dhps-tp-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->add_control( 'card_gap', array(
			'label' => 'Abstand', 'type' => \Elementor\Controls_Manager::SLIDER,
			'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'selectors' => array( '{{WRAPPER}} .dhps-tp-grid' => 'gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		// --- Stil: Text ---
		$this->start_controls_section( 'section_style_text', array(
			'label' => 'Text', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name' => 'title_typo', 'label' => 'Titel', 'selector' => '{{WRAPPER}} .dhps-tp-card__title',
		) );

		$this->add_control( 'title_color', array(
			'label' => 'Titel-Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-tp-card__title' => 'color: {{VALUE}};' ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name' => 'teaser_typo', 'label' => 'Teaser', 'selector' => '{{WRAPPER}} .dhps-tp-card__teaser',
		) );

		$this->add_control( 'teaser_color', array(
			'label' => 'Teaser-Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-tp-card__teaser' => 'color: {{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	protected function render(): void {
		$data = $this->get_maes_data();
		if ( null === $data || empty( $data['videos'] ) ) { return; }

		$settings      = $this->get_settings_for_display();
		$videos        = $data['videos'];
		$columns       = absint( $settings['columns'] ?? 2 );
		$custom_class  = ! empty( $settings['custom_class'] ) ? ' ' . sanitize_html_class( $settings['custom_class'] ) : '';
		$video_mode    = $settings['video_mode'] ?? 'inline';
		$layout        = sanitize_key( $settings['layout'] ?? 'default' );
		$style_preset  = sanitize_key( $settings['style_preset'] ?? 'default' );
		$lazy_count    = absint( $settings['lazy_count'] ?? 0 );
		$lazy_mode     = sanitize_key( $settings['lazy_mode'] ?? 'manual' );

		if ( $columns < 1 || $columns > 4 ) { $columns = 2; }

		wp_enqueue_script( 'dhps-tp-js' );

		// Layout-Template: videos.php (default), videos-card.php, videos-compact.php.
		$layout_suffix = ( 'default' !== $layout ) ? '-' . $layout : '';
		$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/videos' . $layout_suffix . '.php';
		if ( ! file_exists( $template ) ) {
			$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/videos.php';
		}
		if ( file_exists( $template ) ) { include $template; }
	}
}

/* =========================================================================
   MAES Merkblaetter - Volle Controls wie MMB
   ========================================================================= */

class DHPS_Elementor_Widget_MAES_Merkblaetter extends DHPS_Elementor_MAES_Base {

	public function get_name(): string { return 'dhps-maes-merkblaetter'; }
	public function get_title(): string { return 'MAES Merkblaetter'; }
	public function get_icon(): string { return 'eicon-document-file'; }
	public function get_keywords(): array { return array( 'maes', 'merkblatt', 'checkliste', 'aerzte' ); }

	protected function register_controls(): void {

		// --- Inhalt ---
		$this->start_controls_section( 'section_content', array(
			'label' => 'Inhalt', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'layout', array(
			'label'   => 'Layout',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'default' => 'Standard', 'card' => 'Card', 'compact' => 'Kompakt' ),
			'default' => 'default',
		) );

		$this->add_control( 'columns', array(
			'label'     => 'Spalten (Card)',
			'type'      => \Elementor\Controls_Manager::SELECT,
			'options'   => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
			'default'   => '2',
			'condition' => array( 'layout' => 'card' ),
		) );

		$this->add_control( 'show_description', array(
			'label'   => 'Beschreibung anzeigen',
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => 'yes',
		) );

		$this->add_control( 'accordion_open', array(
			'label'   => 'Erste Kategorie offen',
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => 'yes',
		) );

		$this->add_control( 'custom_class', array(
			'label' => 'CSS-Klasse', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '',
		) );

		$this->add_control( 'cache_ttl', array(
			'label' => 'Cache (Sekunden)', 'type' => \Elementor\Controls_Manager::NUMBER,
			'default' => 3600, 'min' => 0, 'max' => 86400,
		) );

		$this->end_controls_section();

		// --- Stil: Kategorie-Header ---
		$this->start_controls_section( 'section_style_header', array(
			'label' => 'Kategorie-Header', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name' => 'header_typo', 'selector' => '{{WRAPPER}} .dhps-mmb-category__trigger',
		) );

		$this->add_control( 'header_color', array(
			'label' => 'Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-mmb-category__trigger' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'header_bg', array(
			'label' => 'Hintergrund', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-mmb-category__trigger' => 'background-color: {{VALUE}};' ),
		) );

		$this->end_controls_section();

		// --- Stil: Merkblatt-Eintraege ---
		$this->start_controls_section( 'section_style_items', array(
			'label' => 'Merkblatt-Eintraege', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name' => 'item_title_typo', 'label' => 'Titel', 'selector' => '{{WRAPPER}} .dhps-mmb-item__title',
		) );

		$this->add_control( 'item_title_color', array(
			'label' => 'Titel-Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-mmb-item__title' => 'color: {{VALUE}};' ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name' => 'item_desc_typo', 'label' => 'Beschreibung', 'selector' => '{{WRAPPER}} .dhps-mmb-item__description',
		) );

		$this->add_control( 'item_desc_color', array(
			'label' => 'Beschreibung-Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-mmb-item__description' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'detail_border_color', array(
			'label' => 'Detail-Randfarbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-mmb-item__detail' => 'border-left-color: {{VALUE}};' ),
		) );

		$this->end_controls_section();

		// --- Stil: Container/Box ---
		$this->start_controls_section( 'section_style_box', array(
			'label' => 'Container', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'box_bg', array(
			'label' => 'Hintergrund', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .dhps-mmb-category, {{WRAPPER}} .dhps-mmb-card-grid__item, {{WRAPPER}} .dhps-mmb-item' => 'background-color: {{VALUE}};',
			),
		) );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name' => 'box_border',
			'selector' => '{{WRAPPER}} .dhps-mmb-category, {{WRAPPER}} .dhps-mmb-card-grid__item, {{WRAPPER}} .dhps-mmb-item',
		) );

		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array(
			'name' => 'box_shadow',
			'selector' => '{{WRAPPER}} .dhps-mmb-category, {{WRAPPER}} .dhps-mmb-card-grid__item, {{WRAPPER}} .dhps-mmb-item',
		) );

		$this->add_control( 'box_border_radius', array(
			'label' => 'Eckenradius', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', '%' ),
			'selectors' => array(
				'{{WRAPPER}} .dhps-mmb-category, {{WRAPPER}} .dhps-mmb-card-grid__item, {{WRAPPER}} .dhps-mmb-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->add_control( 'box_padding', array(
			'label' => 'Innenabstand', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors' => array(
				'{{WRAPPER}} .dhps-mmb-category__content, {{WRAPPER}} .dhps-mmb-card-grid__item, {{WRAPPER}} .dhps-mmb-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			),
		) );

		$this->end_controls_section();

		// --- Stil: Download-Button ---
		$this->start_controls_section( 'section_style_btn', array(
			'label' => 'Download-Button', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name' => 'btn_typo', 'selector' => '{{WRAPPER}} .dhps-mmb-item__download',
		) );

		$this->add_control( 'btn_color', array(
			'label' => 'Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-mmb-item__download' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'btn_bg', array(
			'label' => 'Hintergrund', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-mmb-item__download' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
			'name' => 'btn_border', 'selector' => '{{WRAPPER}} .dhps-mmb-item__download',
		) );

		$this->add_control( 'btn_padding', array(
			'label' => 'Innenabstand', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors' => array( '{{WRAPPER}} .dhps-mmb-item__download' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	protected function render(): void {
		$data = $this->get_maes_data();
		if ( null === $data || empty( $data['merkblaetter'] ) ) { return; }

		$settings         = $this->get_settings_for_display();
		$merkblaetter     = $data['merkblaetter'];
		$custom_class     = ! empty( $settings['custom_class'] ) ? ' ' . sanitize_html_class( $settings['custom_class'] ) : '';
		$layout           = sanitize_key( $settings['layout'] ?? 'default' );
		$columns          = absint( $settings['columns'] ?? 2 );
		$show_description = ( $settings['show_description'] ?? 'yes' ) === 'yes';
		$accordion_open   = ( $settings['accordion_open'] ?? 'yes' ) === 'yes';

		wp_enqueue_script( 'dhps-mmb-js' );

		$layout_suffix = ( 'default' !== $layout ) ? '-' . $layout : '';
		$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/merkblaetter' . $layout_suffix . '.php';
		if ( ! file_exists( $template ) ) {
			$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/merkblaetter.php';
		}
		if ( file_exists( $template ) ) { include $template; }
	}
}

/* =========================================================================
   MAES Aktuelles - Volle Controls fuer News-Akkordeon
   ========================================================================= */

class DHPS_Elementor_Widget_MAES_Aktuelles extends DHPS_Elementor_MAES_Base {

	public function get_name(): string { return 'dhps-maes-aktuelles'; }
	public function get_title(): string { return 'MAES Aktuelles'; }
	public function get_icon(): string { return 'eicon-post-list'; }
	public function get_keywords(): array { return array( 'maes', 'aktuelles', 'news', 'aerzte', 'heilberufe' ); }

	protected function register_controls(): void {

		// --- Inhalt ---
		$this->start_controls_section( 'section_content', array(
			'label' => 'Inhalt', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'layout', array(
			'label'   => 'Layout',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array( 'default' => 'Standard', 'card' => 'Card', 'compact' => 'Kompakt' ),
			'default' => 'default',
		) );

		$this->add_control( 'columns', array(
			'label'     => 'Spalten (Card)',
			'type'      => \Elementor\Controls_Manager::SELECT,
			'options'   => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
			'default'   => '2',
			'condition' => array( 'layout' => 'card' ),
		) );

		$this->add_control( 'max_articles', array(
			'label'       => 'Max. Artikel',
			'description' => '0 = alle anzeigen',
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'default'     => 0,
			'min'         => 0,
			'max'         => 50,
		) );

		$this->add_control( 'show_teaser', array(
			'label'   => 'Teaser-Hinweis anzeigen',
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => 'yes',
		) );

		$this->add_control( 'first_open', array(
			'label'   => 'Ersten Artikel offen',
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => '',
		) );

		$this->add_control( 'custom_class', array(
			'label' => 'CSS-Klasse', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '',
		) );

		$this->add_control( 'cache_ttl', array(
			'label' => 'Cache (Sekunden)', 'type' => \Elementor\Controls_Manager::NUMBER,
			'default' => 3600, 'min' => 0, 'max' => 86400,
		) );

		$this->end_controls_section();

		// --- Stil: Artikel-Titel ---
		$this->start_controls_section( 'section_style_title', array(
			'label' => 'Artikel-Titel', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name' => 'news_title_typo', 'selector' => '{{WRAPPER}} .dhps-news__title',
		) );

		$this->start_controls_tabs( 'title_tabs' );

		$this->start_controls_tab( 'title_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'title_color', array(
			'label' => 'Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-news__title' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'title_bg', array(
			'label' => 'Hintergrund', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-news__title' => 'background-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();

		$this->start_controls_tab( 'title_hover', array( 'label' => 'Hover' ) );
		$this->add_control( 'title_color_hover', array(
			'label' => 'Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-news__title:hover' => 'color: {{VALUE}};' ),
		) );
		$this->add_control( 'title_bg_hover', array(
			'label' => 'Hintergrund', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-news__title:hover' => 'background-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control( 'title_padding', array(
			'label' => 'Innenabstand', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ), 'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .dhps-news__title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		// --- Stil: Teaser-Hinweis ---
		$this->start_controls_section( 'section_style_teaser', array(
			'label' => 'Teaser-Hinweis', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name' => 'teaser_typo', 'selector' => '{{WRAPPER}} .dhps-news__teaser-hint',
		) );

		$this->add_control( 'teaser_color', array(
			'label' => 'Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-news__teaser-hint' => 'color: {{VALUE}};' ),
		) );

		$this->end_controls_section();

		// --- Stil: Artikel-Body ---
		$this->start_controls_section( 'section_style_body', array(
			'label' => 'Artikel-Inhalt', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name' => 'body_typo', 'selector' => '{{WRAPPER}} .dhps-news__body',
		) );

		$this->add_control( 'body_color', array(
			'label' => 'Textfarbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-news__body' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'body_border_color', array(
			'label' => 'Randfarbe (links)', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-news__body' => 'border-left-color: {{VALUE}};' ),
		) );

		$this->add_control( 'body_padding', array(
			'label' => 'Innenabstand', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors' => array( '{{WRAPPER}} .dhps-news__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		// --- Stil: Artikel-Rahmen ---
		$this->start_controls_section( 'section_style_article', array(
			'label' => 'Artikel-Rahmen', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'article_border_color', array(
			'label' => 'Trennlinie-Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-news__article' => 'border-bottom-color: {{VALUE}};' ),
		) );

		$this->add_control( 'article_border_width', array(
			'label' => 'Trennlinie-Breite', 'type' => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range' => array( 'px' => array( 'min' => 0, 'max' => 5 ) ),
			'selectors' => array( '{{WRAPPER}} .dhps-news__article' => 'border-bottom-width: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'article_border_style', array(
			'label' => 'Trennlinie-Typ', 'type' => \Elementor\Controls_Manager::SELECT,
			'options' => array( '' => 'Standard', 'solid' => 'Durchgezogen', 'dashed' => 'Gestrichelt', 'dotted' => 'Gepunktet', 'none' => 'Keine' ),
			'selectors' => array( '{{WRAPPER}} .dhps-news__article' => 'border-bottom-style: {{VALUE}};' ),
		) );

		$this->end_controls_section();

		// --- Stil: Ausblenden-Button ---
		$this->start_controls_section( 'section_style_collapse_btn', array(
			'label' => 'Ausblenden-Button', 'tab' => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
			'name' => 'collapse_btn_typo', 'selector' => '{{WRAPPER}} .dhps-news__action-link',
		) );

		$this->add_control( 'collapse_btn_color', array(
			'label' => 'Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-news__action-link' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'collapse_btn_color_hover', array(
			'label' => 'Hover-Farbe', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-news__action-link:hover' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'collapse_btn_bg', array(
			'label' => 'Hintergrund', 'type' => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .dhps-news__action-link' => 'background-color: {{VALUE}};' ),
		) );

		$this->add_control( 'collapse_btn_padding', array(
			'label' => 'Innenabstand', 'type' => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors' => array( '{{WRAPPER}} .dhps-news__action-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	protected function render(): void {
		$data = $this->get_maes_data();
		if ( null === $data || empty( $data['news'] ) ) { return; }

		$settings      = $this->get_settings_for_display();
		$news          = $data['news'];
		$custom_class  = ! empty( $settings['custom_class'] ) ? ' ' . sanitize_html_class( $settings['custom_class'] ) : '';
		$layout        = sanitize_key( $settings['layout'] ?? 'default' );
		$columns       = absint( $settings['columns'] ?? 2 );
		$max_articles  = absint( $settings['max_articles'] ?? 0 );
		$show_teaser   = ( $settings['show_teaser'] ?? 'yes' ) === 'yes';
		$first_open    = ( $settings['first_open'] ?? '' ) === 'yes';

		if ( $max_articles > 0 ) {
			$news = array_slice( $news, 0, $max_articles );
		}

		$layout_suffix = ( 'default' !== $layout ) ? '-' . $layout : '';
		$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/aktuelles' . $layout_suffix . '.php';
		if ( ! file_exists( $template ) ) {
			$template = DEUBNER_HP_SERVICES_PATH . 'public/views/services/maes/aktuelles.php';
		}
		if ( file_exists( $template ) ) { include $template; }
	}
}
