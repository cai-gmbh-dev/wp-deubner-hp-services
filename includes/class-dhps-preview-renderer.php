<?php
/**
 * Live-Preview-Renderer fuer das Admin-Dashboard.
 *
 * Wrapped das do_shortcode()-Output in ein vollstaendiges HTML-Document mit
 * Frontend-Stylesheets + Alpine + Service-JS, sodass es als srcdoc in
 * einem iframe sauber gerendert wird.
 *
 * Architektur-Hinweise (siehe docs/architecture/21-LIVE-PREVIEW-PLAN-v0153.md):
 *   - REST-Kontext: wp_enqueue_scripts feuert nicht, daher schreibt diese
 *     Klasse alle <link rel="stylesheet"> und <script src="..."> Tags
 *     manuell in das HTML-Document.
 *   - Die HTML aus do_shortcode() ist trusted (DHPS-Parser + DHPS-Templates
 *     escapen User-relevante Felder bereits). Inline-JS in TC ist Vendor-Code.
 *   - Cache-Hit-Detection: Wir probe vor do_shortcode(), ob der zugehoerige
 *     Transient bereits existiert (Heuristik).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.15.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Preview_Renderer
 *
 * @since 0.15.3
 */
class DHPS_Preview_Renderer {

	/**
	 * Whitelist erlaubter Layout-Werte (Hauptscope v0.15.3).
	 *
	 * @since 0.15.3
	 * @var array<int,string>
	 */
	private const ALLOWED_LAYOUTS = array( 'default', 'card', 'compact' );

	/**
	 * Whitelist erlaubter MAES-Sections.
	 *
	 * @since 0.15.3
	 * @var array<int,string>
	 */
	private const ALLOWED_MAES_SECTIONS = array( 'videos', 'merkblaetter', 'aktuelles' );

	/**
	 * Service-JS-Mapping (analog dhps_enqueue_frontend_styles()).
	 *
	 * MAES nutzt die TP-JS-Pipeline (Video-Lazy-Loading).
	 *
	 * Sub-Shortcodes erben ihr JS ueber SUB_SHORTCODE_PARENTS (v0.15.4).
	 *
	 * @since 0.15.3
	 * @var array<string,string>
	 */
	private const SERVICE_JS_MAP = array(
		'mio'   => 'public/js/dhps-mio.js',
		'lxmio' => 'public/js/dhps-mio.js',
		'mmb'   => 'public/js/dhps-mmb.js',
		'mil'   => 'public/js/dhps-mmb.js',
		'tp'    => 'public/js/dhps-tp.js',
		'tpt'   => 'public/js/dhps-tp.js',
		'lp'    => 'public/js/dhps-tp.js',
		'maes'  => 'public/js/dhps-tp.js',
	);

	/**
	 * Sub-Shortcode-zu-Parent-Service-Mapping (v0.15.4).
	 *
	 * Sub-Shortcodes sind NICHT in DHPS_Service_Registry registriert (sie
	 * haengen direkt an add_shortcode()). Damit Auth-Token + Endpoint + JS-Asset
	 * trotzdem aufgeloest werden koennen, weist diese Map jedem Sub-Shortcode
	 * seinen Parent zu.
	 *
	 * Public, damit DHPS_Admin_REST den Lookup teilen kann ohne eigene Map.
	 *
	 * @since 0.15.4
	 * @var array<string,string>
	 */
	public const SUB_SHORTCODE_PARENTS = array(
		'mio_termine'       => 'mio',
		'maes_videos'       => 'maes',
		'maes_merkblaetter' => 'maes',
		'maes_aktuelles'    => 'maes',
	);

	/**
	 * Whitelist erlaubter Boolean-Att-Werte fuer `cache` (Legacy v0.15.4).
	 *
	 * Seit v0.15.5 wird das Feld `cache` auf zwei verschiedene Arten interpretiert:
	 * 1. Aus dem REST-Handler kommt es bereits boolean-normalisiert ('0'/'1'),
	 *    weil dort filter_var( FILTER_VALIDATE_BOOLEAN ) angewendet wird.
	 * 2. Im SERVICE_ATTS_SCHEMA wird `cache` als `type=int` (0..86400) modelliert,
	 *    weil die zugrundeliegenden Shortcode-Handler den Wert via absint() als
	 *    Cache-TTL in Sekunden interpretieren.
	 *
	 * Diese Konstante bleibt fuer Rueckwaerts-Kompatibilitaet der frueheren
	 * boolean-Whitelist-Pruefung erhalten, wird aber von der neuen Validation-
	 * Pipeline NICHT mehr verwendet.
	 *
	 * @since      0.15.4
	 * @deprecated 0.15.5 Durch SERVICE_ATTS_SCHEMA + validate_att_value() ersetzt.
	 * @var array<int,string>
	 */
	private const ALLOWED_CACHE_VALUES = array( '0', '1' );

	/**
	 * Service-Atts-Schema (v0.15.5 - autoritativ).
	 *
	 * Schema-Vertrag pro Att-Entry (siehe docs/architecture/23-ATTS-EDITOR-PLAN-v0155.md
	 * Sektion 3, exakt 10 Felder, KEINE Aliases):
	 *
	 *   type        string  PFLICHT  Einer von 'string', 'int', 'bool', 'select'.
	 *   default     scalar  PFLICHT  Default-Wert (passend zu type, niemals null).
	 *   options     array   OPT      Bei type=select: Liste von {value, label}-Objekten.
	 *   min         int     OPT      Bei type=int: untere Grenze (inkl.) - PFLICHT.
	 *   max         int     OPT      Bei type=int: obere Grenze (inkl.) - PFLICHT.
	 *   pattern     string  OPT      Bei type=string: PCRE-Regex (KEINE / / Delimiter).
	 *   sanitize    string  OPT      Einer von 'text_field', 'html_class', 'key', 'csv_int'.
	 *   group       string  PFLICHT  Einer von 'universal', 'service_specific'.
	 *   label       string  OPT      UI-Label (Default = $att_name).
	 *   description string  OPT      UI-Hilfe-Text.
	 *
	 * Atts-Inventar: NUR jene Atts, die in den Shortcode-Handlern (Service-Registry,
	 * MAES-Modules, Steuertermine) TATSAECHLICH per shortcode_atts() definiert sind.
	 * Wishlist-Atts aus dem User-Briefing (z.B. MIO/count, kategorie, start_date)
	 * sind bewusst NICHT enthalten (erfordern Shortcode-Handler-Erweiterung in v0.16).
	 *
	 * Public, damit DHPS_Admin_REST + wp_localize_script darauf zugreifen koennen
	 * (Backend-zu-Frontend-Bridge, dhpsAdminConfig.attsSchema).
	 *
	 * @since 0.15.5
	 * @var array<string,array<string,array<string,mixed>>>
	 */
	public const SERVICE_ATTS_SCHEMA = array(

		// -----------------------------------------------------------------
		// 1. MIO - MI-Online Steuerrecht
		// -----------------------------------------------------------------
		'mio' => array(
			'teasermodus' => array(
				'type'    => 'select',
				'default' => '',
				'options' => array(
					array( 'value' => '',  'label' => '(default)' ),
					array( 'value' => '0', 'label' => 'Volle Liste' ),
					array( 'value' => '1', 'label' => 'Nur Teaser' ),
				),
				'group'   => 'service_specific',
				'label'   => 'Teaser-Modus',
			),
			'filter' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'text_field',
				'group'    => 'service_specific',
				'label'    => 'Filter (Volltext)',
			),
			'variante' => array(
				'type'    => 'select',
				'default' => '',
				'options' => array(
					array( 'value' => '',              'label' => '(default)' ),
					array( 'value' => 'tagesaktuell',  'label' => 'tagesaktuell' ),
					array( 'value' => 'kategorisiert', 'label' => 'kategorisiert' ),
				),
				'group'   => 'service_specific',
				'label'   => 'Variante',
			),
			'modus' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'key',
				'group'    => 'service_specific',
				'label'    => 'API-Modus',
			),
			'st_kategorie' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'text_field',
				'group'    => 'service_specific',
				'label'    => 'Steuer-Kategorie',
			),
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 2. LXMIO - MI-Online Recht (identisches Att-Set wie MIO)
		// -----------------------------------------------------------------
		'lxmio' => array(
			'teasermodus' => array(
				'type'    => 'select',
				'default' => '',
				'options' => array(
					array( 'value' => '',  'label' => '(default)' ),
					array( 'value' => '0', 'label' => 'Volle Liste' ),
					array( 'value' => '1', 'label' => 'Nur Teaser' ),
				),
				'group'   => 'service_specific',
				'label'   => 'Teaser-Modus',
			),
			'filter' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'text_field',
				'group'    => 'service_specific',
				'label'    => 'Filter (Volltext)',
			),
			'variante' => array(
				'type'    => 'select',
				'default' => '',
				'options' => array(
					array( 'value' => '',              'label' => '(default)' ),
					array( 'value' => 'tagesaktuell',  'label' => 'tagesaktuell' ),
					array( 'value' => 'kategorisiert', 'label' => 'kategorisiert' ),
				),
				'group'   => 'service_specific',
				'label'   => 'Variante',
			),
			'modus' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'key',
				'group'    => 'service_specific',
				'label'    => 'API-Modus',
			),
			'st_kategorie' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'text_field',
				'group'    => 'service_specific',
				'label'    => 'Rechts-Kategorie',
			),
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 3. MMB - Merkblaetter
		// -----------------------------------------------------------------
		'mmb' => array(
			'id_merkblatt' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'text_field',
				'pattern'  => '^[0-9]{0,12}$',
				'group'    => 'service_specific',
				'label'    => 'Merkblatt-ID',
			),
			'rubrik' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'text_field',
				'group'    => 'service_specific',
				'label'    => 'Rubrik',
			),
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 4. MIL - Infografiken (identisches Att-Set wie MMB)
		// -----------------------------------------------------------------
		'mil' => array(
			'id_merkblatt' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'text_field',
				'pattern'  => '^[0-9]{0,12}$',
				'group'    => 'service_specific',
				'label'    => 'Merkblatt-ID',
			),
			'rubrik' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'text_field',
				'group'    => 'service_specific',
				'label'    => 'Rubrik',
			),
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 5. TP - TaxPlain Videos
		// -----------------------------------------------------------------
		'tp' => array(
			'teasermodus' => array(
				'type'    => 'select',
				'default' => '0',
				'options' => array(
					array( 'value' => '0', 'label' => 'Volle Video-Liste' ),
					array( 'value' => '1', 'label' => 'Teaser-Modus' ),
				),
				'group'   => 'service_specific',
				'label'   => 'Teaser-Modus',
			),
			'einzelvideo' => array(
				'type'    => 'int',
				'default' => 0,
				'min'     => 0,
				'max'     => 999,
				'group'   => 'service_specific',
				'label'   => 'Einzelvideo (1-basiert, 0=alle)',
			),
			'videoliste' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'csv_int',
				'pattern'  => '^[0-9,]{0,128}$',
				'group'    => 'service_specific',
				'label'    => 'Video-Indizes (CSV, z.B. 1,3,5)',
			),
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 6. TPT - TaxPlain Teaser
		// -----------------------------------------------------------------
		'tpt' => array(
			'modus' => array(
				'type'    => 'select',
				'default' => '',
				'options' => array(
					array( 'value' => '',         'label' => '(Admin-Default)' ),
					array( 'value' => 'standard', 'label' => 'standard' ),
					array( 'value' => 'p',        'label' => 'p (nur Titelbild)' ),
					array( 'value' => 't',        'label' => 't (nur Titel)' ),
					array( 'value' => 'pt',       'label' => 'pt (Titel+Bild)' ),
				),
				'group'   => 'service_specific',
				'label'   => 'TPT-Modus',
			),
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 7. TC - Tax-Rechner (Wrapper, keine service-spezifischen Atts)
		// -----------------------------------------------------------------
		'tc' => array(
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 8. MAES - Meine Aerzteseite
		// -----------------------------------------------------------------
		'maes' => array(
			'section' => array(
				'type'    => 'select',
				'default' => '',
				'options' => array(
					array( 'value' => '',             'label' => '(alle)' ),
					array( 'value' => 'videos',       'label' => 'videos' ),
					array( 'value' => 'merkblaetter', 'label' => 'merkblaetter' ),
					array( 'value' => 'aktuelles',    'label' => 'aktuelles' ),
				),
				'group'   => 'service_specific',
				'label'   => 'Section',
			),
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 9. LP - Lexplain Videos
		// -----------------------------------------------------------------
		'lp' => array(
			'videoliste' => array(
				'type'     => 'string',
				'default'  => '0',
				'sanitize' => 'csv_int',
				'pattern'  => '^[0-9,]{0,128}$',
				'group'    => 'service_specific',
				'label'    => 'Video-Index oder CSV-Liste',
			),
			'teasermodus' => array(
				'type'    => 'select',
				'default' => '0',
				'options' => array(
					array( 'value' => '0', 'label' => 'Volle Liste' ),
					array( 'value' => '1', 'label' => 'Teaser-Modus' ),
				),
				'group'   => 'service_specific',
				'label'   => 'Teaser-Modus',
			),
			'show_teaser' => array(
				'type'    => 'select',
				'default' => '1',
				'options' => array(
					array( 'value' => '0', 'label' => 'Ohne Teaser' ),
					array( 'value' => '1', 'label' => 'Mit Teaser' ),
				),
				'group'   => 'service_specific',
				'label'   => 'Teaser anzeigen',
			),
			'filter' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'text_field',
				'group'    => 'service_specific',
				'label'    => 'Filter (Volltext)',
			),
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 10. mio_termine - MIO Steuertermine (Sub-Shortcode)
		// -----------------------------------------------------------------
		'mio_termine' => array(
			'count' => array(
				'type'    => 'int',
				'default' => 0,
				'min'     => 0,
				'max'     => 50,
				'group'   => 'service_specific',
				'label'   => 'Anzahl pro Monat (0=alle)',
			),
			'month' => array(
				'type'    => 'select',
				'default' => 'all',
				'options' => array(
					array( 'value' => 'current', 'label' => 'aktueller Monat' ),
					array( 'value' => 'next',    'label' => 'naechster Monat' ),
					array( 'value' => 'all',     'label' => 'alle' ),
				),
				'group'   => 'service_specific',
				'label'   => 'Monatsfilter',
			),
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'inline',  'label' => 'inline' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 11. maes_videos - MAES Videos (Sub-Shortcode)
		// -----------------------------------------------------------------
		'maes_videos' => array(
			'columns' => array(
				'type'    => 'int',
				'default' => 2,
				'min'     => 1,
				'max'     => 4,
				'group'   => 'service_specific',
				'label'   => 'Spalten',
			),
			'einzelvideo' => array(
				'type'    => 'int',
				'default' => 0,
				'min'     => 0,
				'max'     => 999,
				'group'   => 'service_specific',
				'label'   => 'Einzelvideo (1-basiert, 0=alle)',
			),
			'videoliste' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'csv_int',
				'pattern'  => '^[0-9,]{0,128}$',
				'group'    => 'service_specific',
				'label'    => 'Video-Indizes (CSV)',
			),
			'lazy_count' => array(
				'type'    => 'int',
				'default' => 0,
				'min'     => 0,
				'max'     => 50,
				'group'   => 'service_specific',
				'label'   => 'Lazy-Load initial sichtbar',
			),
			'lazy_mode' => array(
				'type'    => 'select',
				'default' => 'manual',
				'options' => array(
					array( 'value' => 'manual', 'label' => 'manual' ),
					array( 'value' => 'auto',   'label' => 'auto' ),
				),
				'group'   => 'service_specific',
				'label'   => 'Lazy-Trigger',
			),
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 12. maes_merkblaetter - MAES Merkblaetter (Sub-Shortcode)
		// -----------------------------------------------------------------
		'maes_merkblaetter' => array(
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),

		// -----------------------------------------------------------------
		// 13. maes_aktuelles - MAES Aktuelles (Sub-Shortcode)
		// -----------------------------------------------------------------
		'maes_aktuelles' => array(
			'columns' => array(
				'type'    => 'int',
				'default' => 2,
				'min'     => 1,
				'max'     => 4,
				'group'   => 'service_specific',
				'label'   => 'Spalten',
			),
			'layout' => array(
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					array( 'value' => 'default', 'label' => 'default' ),
					array( 'value' => 'card',    'label' => 'card' ),
					array( 'value' => 'compact', 'label' => 'compact' ),
				),
				'group'   => 'universal',
				'label'   => 'Layout',
			),
			'class' => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'html_class',
				'pattern'  => '^[a-zA-Z0-9_\- ]{0,64}$',
				'group'    => 'universal',
				'label'    => 'CSS-Klasse',
			),
			'cache' => array(
				'type'    => 'int',
				'default' => 3600,
				'min'     => 0,
				'max'     => 86400,
				'group'   => 'universal',
				'label'   => 'Cache-TTL (s)',
			),
		),
	);

	/**
	 * Optionaler Cache-Stats-Service (zur Cache-Hit-Probe).
	 *
	 * @since 0.15.3
	 * @var DHPS_Cache|null
	 */
	private ?DHPS_Cache $cache;

	/**
	 * Konstruktor.
	 *
	 * @since 0.15.3
	 *
	 * @param DHPS_Cache|null $cache Optionaler Cache-Service (fuer Cache-Hit-Probe).
	 */
	public function __construct( ?DHPS_Cache $cache = null ) {
		$this->cache = $cache;
	}

	/**
	 * Rendert eine Live-Preview fuer einen Service als kompletten HTML-Document.
	 *
	 * Rueckgabe-Schema (autoritativ, siehe Discovery Sektion 9):
	 *   - html             string  Kompletter HTML-Document fuer iframe srcdoc.
	 *   - shortcode        string  Reconstructed Shortcode-String (Debug).
	 *   - atts_applied     array   Atts die angewendet wurden.
	 *   - atts_rejected    array   Map key=>grund der abgelehnten Atts.
	 *   - api_cache_hit    bool    API-Cache-Hit-Status (Heuristik).
	 *   - render_time_ms   int     Render-Dauer in Millisekunden.
	 *
	 * @since 0.15.3
	 *
	 * @param string $service Service-Slug (mio, lxmio, ...).
	 * @param array  $atts    Sanitisierte Atts (layout/class/section).
	 *
	 * @return array Resultat (siehe Schema oben).
	 */
	public function render( string $service, array $atts = array() ): array {
		$start = microtime( true );

		// 1. Atts validieren via SERVICE_ATTS_SCHEMA (v0.15.5).
		//    Pipeline:
		//      - Lookup im Schema fuer den Service.
		//      - Pro Att: type-cast + bounds/options/pattern-Check + sanitize.
		//      - Unbekannte Keys -> atts_rejected mit reason='unknown att key'
		//        (bzw. 'not allowed for service' wenn das Att im Schema eines
		//        anderen Service existiert, hier aber nicht).
		$atts_applied   = array();
		$atts_rejected  = array();
		$shortcode_atts = '';

		$schema = isset( self::SERVICE_ATTS_SCHEMA[ $service ] )
			? self::SERVICE_ATTS_SCHEMA[ $service ]
			: array();

		// section-Sonderfall (BC v0.15.4): wenn section uebergeben aber Service
		// ist kein maes/maes_*, dann reject mit 'only allowed for maes'. Sonst
		// laeuft section ueber den regulaeren select-Pfad.
		$is_maes_family = ( 'maes' === $service )
			|| ( isset( self::SUB_SHORTCODE_PARENTS[ $service ] )
				&& 'maes' === self::SUB_SHORTCODE_PARENTS[ $service ] );

		foreach ( $atts as $key => $raw ) {
			$key_str = (string) $key;

			// Sonderfall section fuer Nicht-MAES (BC v0.15.4-Reason-String).
			if ( 'section' === $key_str && ! $is_maes_family ) {
				$atts_rejected[ $key_str ] = 'only allowed for maes';
				continue;
			}

			// Schema-Lookup: wenn Att fuer diesen Service nicht definiert ist,
			// pruefen wir ob es in einem ANDEREN Service-Schema existiert -
			// dann ist es 'not allowed for service', sonst 'unknown att key'.
			if ( ! isset( $schema[ $key_str ] ) ) {
				$atts_rejected[ $key_str ] = $this->is_known_att_anywhere( $key_str )
					? 'not allowed for service'
					: 'unknown att key';
				continue;
			}

			$def       = $schema[ $key_str ];
			$validated = self::validate_att_value( $raw, $def );

			if ( false === $validated['ok'] ) {
				$atts_rejected[ $key_str ] = $validated['reason'];
				continue;
			}

			$value = $validated['value'];

			// Leerer string-Wert: in atts_applied behalten (BC v0.15.4 fuer
			// class=''), aber NICHT in den Shortcode-String aufnehmen.
			$is_empty_string = ( 'string' === $def['type'] && '' === $value );

			$atts_applied[ $key_str ] = $value;

			if ( ! $is_empty_string ) {
				$shortcode_atts .= ' ' . $key_str . '="' . esc_attr( (string) $value ) . '"';
			}
		}

		$shortcode = '[' . $service . $shortcode_atts . ']';

		// 2. Cache-Hit-Probe (Heuristik): Anzahl Plugin-Transients vor/nach
		//    Render vergleichen. Steigt sie, war es ein Miss.
		$api_cache_hit  = false;
		$transients_pre = $this->count_plugin_transients();

		// 3. do_shortcode ausfuehren.
		$output = '';
		try {
			$output = (string) do_shortcode( $shortcode );
		} catch ( \Throwable $e ) {
			$output = '<!-- preview_render_failed: ' . esc_html( $e->getMessage() ) . ' -->';
		}

		$transients_post = $this->count_plugin_transients();
		// Wenn sich die Anzahl der Plugin-Transients NICHT geaendert hat,
		// hat der API-Client wahrscheinlich aus dem Cache geliefert.
		if ( null !== $transients_pre && null !== $transients_post ) {
			$api_cache_hit = ( $transients_pre === $transients_post );
		}

		$render_time_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

		// 4. HTML-Document-Wrapper bauen.
		$html = $this->build_html_document( $service, $output );

		return array(
			'html'           => $html,
			'shortcode'      => $shortcode,
			'atts_applied'   => $atts_applied,
			'atts_rejected'  => $atts_rejected,
			'api_cache_hit'  => (bool) $api_cache_hit,
			'render_time_ms' => $render_time_ms,
		);
	}

	/**
	 * Validiert einen Att-Wert gegen ein Schema-Entry (v0.15.5).
	 *
	 * Reject-Reasons (autoritativ, siehe 23-ATTS-EDITOR-PLAN-v0155 Sektion 3.3):
	 *   - 'value not in whitelist'        (type=select, value nicht in options)
	 *   - 'invalid html-class chars'      (type=string, sanitize=html_class strippt alles)
	 *   - 'value not boolean (0|1)'       (type=bool, FILTER_VALIDATE_BOOLEAN scheitert)
	 *   - 'out of bounds (min=N, max=M)'  (type=int, ausserhalb min/max)
	 *   - 'invalid type (expected int)'   (type=int, Wert nicht numerisch)
	 *   - 'pattern mismatch'              (type=string mit pattern, Regex matched nicht)
	 *
	 * Return-Form:
	 *   array{ok: bool, value?: mixed, reason?: string}
	 *
	 * @since 0.15.5
	 *
	 * @param mixed $raw Roher Att-Wert (scalar).
	 * @param array $def Schema-Definition (siehe SERVICE_ATTS_SCHEMA).
	 *
	 * @return array{ok: bool, value?: mixed, reason?: string}
	 */
	private static function validate_att_value( $raw, array $def ): array {
		$type = isset( $def['type'] ) ? (string) $def['type'] : 'string';

		switch ( $type ) {

			case 'string':
				$val      = is_scalar( $raw ) ? (string) $raw : '';
				$sanitize = isset( $def['sanitize'] ) ? (string) $def['sanitize'] : '';

				if ( 'html_class' === $sanitize ) {
					// sanitize_html_class strippt Spaces+Sonderzeichen, aber wir
					// erlauben Mehrfach-Klassen ('foo bar') - dafuer das pattern.
					// Erst Pattern (wenn vorhanden), DANN sanitize_html_class
					// auf das erste Wort. Bei leerem Input: durchwinken.
					if ( '' === $val ) {
						return array( 'ok' => true, 'value' => '' );
					}
					if ( isset( $def['pattern'] ) ) {
						$re = '/' . $def['pattern'] . '/';
						if ( ! preg_match( $re, $val ) ) {
							return array( 'ok' => false, 'reason' => 'pattern mismatch' );
						}
					}
					// sanitize_html_class fuer Multi-Class: pro Token sanitisieren,
					// leere Tokens raus. Wenn ALLES leer -> invalid html-class chars.
					$tokens = preg_split( '/\s+/', trim( $val ) );
					$clean_tokens = array();
					foreach ( $tokens as $tok ) {
						$c = sanitize_html_class( $tok );
						if ( '' !== $c ) {
							$clean_tokens[] = $c;
						}
					}
					if ( empty( $clean_tokens ) ) {
						return array( 'ok' => false, 'reason' => 'invalid html-class chars' );
					}
					return array( 'ok' => true, 'value' => implode( ' ', $clean_tokens ) );
				}

				if ( 'text_field' === $sanitize ) {
					$val = sanitize_text_field( $val );
				} elseif ( 'key' === $sanitize ) {
					$val = sanitize_key( $val );
				} elseif ( 'csv_int' === $sanitize ) {
					// Leerer Wert OK (BC: videoliste leer = "nicht filtern").
					if ( '' !== $val && ! preg_match( '/^[0-9,]{0,128}$/', $val ) ) {
						return array( 'ok' => false, 'reason' => 'pattern mismatch' );
					}
				}

				if ( isset( $def['pattern'] ) ) {
					$re = '/' . $def['pattern'] . '/';
					if ( ! preg_match( $re, $val ) ) {
						return array( 'ok' => false, 'reason' => 'pattern mismatch' );
					}
				}

				return array( 'ok' => true, 'value' => $val );

			case 'int':
				if ( ! is_numeric( $raw ) ) {
					return array( 'ok' => false, 'reason' => 'invalid type (expected int)' );
				}
				$val = (int) $raw;
				$min = isset( $def['min'] ) ? (int) $def['min'] : PHP_INT_MIN;
				$max = isset( $def['max'] ) ? (int) $def['max'] : PHP_INT_MAX;
				if ( $val < $min || $val > $max ) {
					return array(
						'ok'     => false,
						'reason' => sprintf( 'out of bounds (min=%d, max=%d)', $min, $max ),
					);
				}
				return array( 'ok' => true, 'value' => $val );

			case 'bool':
				$b = filter_var( $raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				if ( null === $b ) {
					return array( 'ok' => false, 'reason' => 'value not boolean (0|1)' );
				}
				return array( 'ok' => true, 'value' => $b ? '1' : '0' );

			case 'select':
				$allowed = array();
				if ( isset( $def['options'] ) && is_array( $def['options'] ) ) {
					foreach ( $def['options'] as $opt ) {
						if ( is_array( $opt ) && isset( $opt['value'] ) ) {
							$allowed[] = (string) $opt['value'];
						}
					}
				}
				$val = is_scalar( $raw ) ? (string) $raw : '';
				if ( ! in_array( $val, $allowed, true ) ) {
					return array( 'ok' => false, 'reason' => 'value not in whitelist' );
				}
				return array( 'ok' => true, 'value' => $val );
		}

		// Unbekannter type im Schema (sollte nie vorkommen, Defense-in-Depth).
		return array( 'ok' => false, 'reason' => 'unknown att key' );
	}

	/**
	 * Prueft, ob ein Att-Key in IRGENDEINEM Service-Schema existiert.
	 *
	 * Wird genutzt, um die Reject-Reason zu differenzieren:
	 *   - Existiert das Att irgendwo im Plugin -> 'not allowed for service'.
	 *   - Existiert es nirgends -> 'unknown att key'.
	 *
	 * @since 0.15.5
	 *
	 * @param string $att_key Att-Key.
	 * @return bool true, wenn Key in mindestens einem Service-Schema vorkommt.
	 */
	private function is_known_att_anywhere( string $att_key ): bool {
		foreach ( self::SERVICE_ATTS_SCHEMA as $svc_schema ) {
			if ( isset( $svc_schema[ $att_key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Zaehlt aktuell vorhandene Plugin-Transients (Heuristik fuer Cache-Hit).
	 *
	 * Nutzt $wpdb direkt, weil wp_cache_get_multiple() fuer Transients nicht
	 * sinnvoll ist. Bei Object-Cache (Redis/Memcached) liefert das u.U. 0 -
	 * dann wird api_cache_hit als true zurueckgegeben (gefaehrlich verfaelschend?).
	 * Wir geben in dem Fall null zurueck, dann bleibt api_cache_hit false.
	 *
	 * @since 0.15.3
	 *
	 * @return int|null Anzahl Transient-Rows oder null wenn nicht ermittelbar.
	 */
	private function count_plugin_transients(): ?int {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			return null;
		}

		// Bei Object-Cache landen Transients nicht in wp_options - dann
		// liefert die Query 0. Heuristik: wenn ein persistenter Cache aktiv
		// ist, ist die Probe unzuverlaessig -> null.
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			return null;
		}

		$like_pattern = $wpdb->esc_like( '_transient_dhps_' ) . '%';
		$count        = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like_pattern
			)
		);

		if ( null === $count ) {
			return null;
		}

		return (int) $count;
	}

	/**
	 * Baut ein vollstaendiges HTML-Document mit Frontend-CSS + JS.
	 *
	 * Pfad-Konvention:
	 *   - CSS liegt unter $plugin/css/*.css
	 *   - JS liegt unter $plugin/public/js/*.js
	 *   - Versions-Suffix via DEUBNER_HP_SERVICES_VERSION.
	 *
	 * @since 0.15.3
	 *
	 * @param string $service Service-Slug.
	 * @param string $body    Shortcode-Output (trusted).
	 *
	 * @return string Kompletter HTML-Document.
	 */
	private function build_html_document( string $service, string $body ): string {

		$plugin_url    = defined( 'DEUBNER_HP_SERVICES_URL' ) ? DEUBNER_HP_SERVICES_URL : '';
		$plugin_ver    = defined( 'DEUBNER_HP_SERVICES_VERSION' ) ? DEUBNER_HP_SERVICES_VERSION : '0.15.4';
		$service_label = esc_html( strtoupper( $service ) );

		// CSS-Files (Reihenfolge analog Frontend-Enqueue).
		$css_files = array(
			'css/dhps-design-tokens.css',
			'css/dhps_base.css',
			'css/dhps-frontend.css',
			'css/dhps-components.css',
		);

		$css_links = '';
		foreach ( $css_files as $css ) {
			$css_links .= '<link rel="stylesheet" href="' . esc_url( $plugin_url . $css ) . '?ver=' . rawurlencode( (string) $plugin_ver ) . '">' . "\n";
		}

		// JS-Files: Service-spezifisch + Alpine (defensiv unconditional).
		// v0.15.4: Sub-Shortcodes erben ihr JS vom Parent-Service.
		$js_lookup_slug = isset( self::SUB_SHORTCODE_PARENTS[ $service ] )
			? self::SUB_SHORTCODE_PARENTS[ $service ]
			: $service;

		$js_files = array();
		if ( isset( self::SERVICE_JS_MAP[ $js_lookup_slug ] ) ) {
			$js_files[] = self::SERVICE_JS_MAP[ $js_lookup_slug ];
		}
		// Alpine in der Order Vendor -> Init -> Components.
		$js_files[] = 'public/js/vendor/alpinejs-3.14.x.min.js';
		$js_files[] = 'public/js/dhps-alpine-init.js';
		$js_files[] = 'public/js/dhps-components-alpine.js';

		$js_tags = '';
		foreach ( $js_files as $js ) {
			$js_tags .= '<script defer src="' . esc_url( $plugin_url . $js ) . '?ver=' . rawurlencode( (string) $plugin_ver ) . '"></script>' . "\n";
		}

		$title = 'DHPS Live-Preview: ' . $service_label;

		// Inline-Reset-Styles, damit der iframe-Body nicht 0-margin hat.
		$inline_css = 'body{margin:16px;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#fff;color:#222;}'
			. 'html,body{min-height:0;}';

		// v0.15.4: postMessage-Resize-Snippet (Ticket 5 aus Tech-Debt-Triage).
		// Misst iframe-Hoehe und postet sie an Parent. KEINE User-Inputs - hartcodiert.
		// Security-Layer: Parent prueft type+bounds+max-cap, hier nur Mess-Logik.
		$resize_js = $this->get_postmessage_resize_snippet();

		$document  = '<!DOCTYPE html>' . "\n";
		$document .= '<html lang="de"><head>' . "\n";
		$document .= '<meta charset="utf-8">' . "\n";
		$document .= '<meta name="viewport" content="width=device-width,initial-scale=1">' . "\n";
		$document .= '<meta name="robots" content="noindex,nofollow">' . "\n";
		$document .= '<title>' . esc_html( $title ) . '</title>' . "\n";
		$document .= $css_links;
		$document .= '<style>' . $inline_css . '</style>' . "\n";
		$document .= '</head><body class="dhps-preview dhps-service--' . esc_attr( $service ) . '">' . "\n";
		// Body ist trusted (DHPS-Templates escapen schon, TC liefert bewusst Inline-JS).
		$document .= $body . "\n";
		$document .= $js_tags;
		$document .= $resize_js;
		$document .= '</body></html>';

		return $document;
	}

	/**
	 * Liefert das postMessage-Resize-Snippet fuer iframe-Hoehe (v0.15.4).
	 *
	 * Das Snippet ist hartcodiert (KEINE User-Inputs), misst die scrollHeight
	 * von body+documentElement und sendet sie an den Parent. Parent (React-
	 * Listener in dhps-admin-react.js) prueft type/bounds/max-cap.
	 *
	 * Security-Design:
	 *   - targetOrigin '*' ist akzeptabel: iframe srcdoc hat about:srcdoc-Origin,
	 *     ein klassischer Origin-Check ist hier nicht moeglich. Schutz liegt
	 *     beim Parent (type-Check + numeric-bounds + 4000px-Cap).
	 *   - DoS-Schutz: MAX_HEIGHT-Cap bei 4000px, damit ein praepariertes
	 *     iframe das Parent-Layout nicht unendlich aufblaehen kann.
	 *   - rate-limited via ResizeObserver-Throttle (Browser default).
	 *
	 * @since 0.15.4
	 *
	 * @return string Komplettes <script>-Tag.
	 */
	private function get_postmessage_resize_snippet(): string {
		return <<<'JS'
<script>
(function () {
	'use strict';
	var TYPE = 'dhps-preview-resize';
	var MAX_HEIGHT = 4000;
	var lastH = 0;

	function postHeight() {
		var h = Math.max(
			document.body ? document.body.scrollHeight : 0,
			document.documentElement ? document.documentElement.scrollHeight : 0
		);
		if (h > MAX_HEIGHT) { h = MAX_HEIGHT; }
		if (h < 1) { return; }
		if (h === lastH) { return; }
		lastH = h;
		try {
			window.parent.postMessage({ type: TYPE, height: h }, '*');
		} catch (e) {}
	}

	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		setTimeout(postHeight, 50);
	} else {
		document.addEventListener('DOMContentLoaded', postHeight);
	}
	window.addEventListener('load', postHeight);
	window.addEventListener('resize', postHeight);

	if (typeof ResizeObserver !== 'undefined') {
		try {
			var ro = new ResizeObserver(function () { postHeight(); });
			if (document.body) { ro.observe(document.body); }
		} catch (e) {}
	} else {
		setInterval(postHeight, 1000);
	}
})();
</script>
JS;
	}
}
