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
	 * Whitelist erlaubter Boolean-Att-Werte fuer `cache`.
	 *
	 * @since 0.15.4
	 * @var array<int,string>
	 */
	private const ALLOWED_CACHE_VALUES = array( '0', '1' );

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

		// 1. Atts validieren + reconstructed Shortcode bauen.
		$atts_applied   = array();
		$atts_rejected  = array();
		$shortcode_atts = '';

		if ( isset( $atts['layout'] ) ) {
			$layout = (string) $atts['layout'];
			if ( in_array( $layout, self::ALLOWED_LAYOUTS, true ) ) {
				$atts_applied['layout'] = $layout;
				$shortcode_atts        .= ' layout="' . esc_attr( $layout ) . '"';
			} else {
				$atts_rejected['layout'] = 'value not in whitelist';
			}
		}

		if ( isset( $atts['class'] ) ) {
			$class_raw   = (string) $atts['class'];
			$class_clean = sanitize_html_class( $class_raw );
			// sanitize_html_class kann Strings mit Spaces nicht verarbeiten -
			// fallback: leere Klasse rejecten, sonst trimmen.
			if ( '' === $class_raw ) {
				// Leerer Wert wird nicht in Shortcode aufgenommen, aber auch
				// nicht rejected.
				$atts_applied['class'] = '';
			} elseif ( '' === $class_clean ) {
				$atts_rejected['class'] = 'invalid html-class chars';
			} else {
				$atts_applied['class'] = $class_clean;
				$shortcode_atts       .= ' class="' . esc_attr( $class_clean ) . '"';
			}
		}

		if ( isset( $atts['section'] ) ) {
			$section_raw = (string) $atts['section'];
			// v0.15.4: section ist auch fuer maes_*-Sub-Shortcodes erlaubt
			// (sie haengen logisch am MAES-Parent).
			$section_allowed = ( 'maes' === $service )
				|| ( isset( self::SUB_SHORTCODE_PARENTS[ $service ] )
					&& 'maes' === self::SUB_SHORTCODE_PARENTS[ $service ] );

			if ( ! $section_allowed ) {
				$atts_rejected['section'] = 'only allowed for maes';
			} elseif ( '' === $section_raw ) {
				$atts_applied['section'] = '';
			} else {
				$section_clean = sanitize_key( $section_raw );
				if ( in_array( $section_clean, self::ALLOWED_MAES_SECTIONS, true ) ) {
					$atts_applied['section'] = $section_clean;
					$shortcode_atts         .= ' section="' . esc_attr( $section_clean ) . '"';
				} else {
					$atts_rejected['section'] = 'value not in maes whitelist';
				}
			}
		}

		// v0.15.4: `cache`-Att (Boolean) wird durchgereicht, damit Sub-Shortcodes
		// optional ohne Cache rendern koennen. Atts-Whitelist im REST-Handler
		// hat den Wert bereits auf '0' / '1' normalisiert.
		if ( isset( $atts['cache'] ) ) {
			$cache_raw = (string) $atts['cache'];
			if ( in_array( $cache_raw, self::ALLOWED_CACHE_VALUES, true ) ) {
				$atts_applied['cache'] = $cache_raw;
				// Wir reichen den boolean-Wert als Shortcode-Att durch, damit
				// die jeweiligen Shortcode-Handler ihn auswerten koennen.
				$shortcode_atts .= ' cache="' . esc_attr( $cache_raw ) . '"';
			} else {
				$atts_rejected['cache'] = 'value not boolean (0|1)';
			}
		}

		// Unbekannte atts-Keys silent in rejected aufnehmen.
		$known_keys = array( 'layout', 'class', 'section', 'cache' );
		foreach ( $atts as $key => $val ) {
			if ( ! in_array( $key, $known_keys, true ) && ! isset( $atts_rejected[ $key ] ) ) {
				$atts_rejected[ (string) $key ] = 'unknown att key';
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
