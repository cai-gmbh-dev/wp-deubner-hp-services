<?php
/**
 * Widget fuer den Deubner Homepage Service.
 *
 * Natives WordPress-Widget, das einen einzelnen Deubner-Service
 * in Sidebars und Footer-Widget-Bereichen anzeigt. Der Service,
 * das Layout und die Cache-Dauer sind ueber das Widget-Formular
 * im Admin konfigurierbar.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Widget
 *
 * Erweitert WP_Widget und stellt einen konfigurierbaren Deubner-Service
 * als Widget bereit. Die Abhaengigkeiten (API-Client und Renderer) werden
 * ueber die Setter-Methode set_dependencies() injiziert, da WP_Widget
 * keinen Custom-Constructor mit zusaetzlichen Parametern erlaubt.
 *
 * Verfuegbare Einstellungen im Admin-Formular:
 * - Titel (optionaler Widget-Titel)
 * - Service (Dropdown aller registrierten Services)
 * - Layout (Dropdown der verfuegbaren Layout-Varianten)
 * - CSS-Klasse (optionale zusaetzliche Klasse)
 * - Cache-Dauer (in Sekunden, Standard: 3600)
 *
 * @since   0.5.0
 * @package Deubner Homepage-Service
 */
class DHPS_Widget extends WP_Widget {

	/**
	 * API-Client-Instanz fuer den Abruf der Service-Inhalte.
	 *
	 * @since 0.5.0
	 * @var DHPS_API_Client|null
	 */
	private $api_client = null;

	/**
	 * Renderer-Instanz fuer das Layout-Wrapping.
	 *
	 * @since 0.5.0
	 * @var DHPS_Renderer|null
	 */
	private $renderer = null;

	/**
	 * Konstruktor.
	 *
	 * Registriert das Widget mit ID, Titel und Beschreibung.
	 *
	 * @since 0.5.0
	 */
	public function __construct() {
		parent::__construct(
			'dhps_service_widget',
			'Deubner Homepage Service',
			array( 'description' => 'Zeigt einen Deubner-Service an (MI-Online, Merkblaetter, Videos, etc.)' )
		);
	}

	/**
	 * Setzt die Abhaengigkeiten fuer API-Zugriff und Rendering.
	 *
	 * Wird vom Plugin-Bootstrap aufgerufen, da WP_Widget keinen
	 * Custom-Constructor mit zusaetzlichen Parametern erlaubt.
	 *
	 * @since 0.5.0
	 *
	 * @param DHPS_API_Client $client   API-Client-Instanz.
	 * @param DHPS_Renderer   $renderer Renderer-Instanz.
	 *
	 * @return void
	 */
	public function set_dependencies( DHPS_API_Client $client, DHPS_Renderer $renderer ): void {
		$this->api_client = $client;
		$this->renderer   = $renderer;
	}

	/**
	 * Frontend-Ausgabe des Widgets.
	 *
	 * Ruft den konfigurierten Service ueber den API-Client ab,
	 * wrappt das Ergebnis durch den Renderer und gibt es innerhalb
	 * des Widget-Wrappers aus.
	 *
	 * Parameter-Aufbau (analog zu Shortcodes, aber ohne shortcode_atts):
	 * 1. Auth-Parameter (ota oder kdnr)
	 * 2. Default-Params des Service
	 * 3. Admin-Options (mit Variante-Switch-Behandlung)
	 *
	 * @since 0.5.0
	 *
	 * @param array $args     Widget-Argumente (before_widget, after_widget, etc.).
	 * @param array $instance Gespeicherte Widget-Einstellungen.
	 *
	 * @return void
	 */
	public function widget( $args, $instance ): void {
		// Abhaengigkeiten pruefen.
		if ( null === $this->api_client || null === $this->renderer ) {
			return;
		}

		// Defaults setzen.
		$defaults = array(
			'service' => 'mio',
			'layout'  => 'default',
			'class'   => '',
			'cache'   => '3600',
			'title'   => '',
		);

		$instance = wp_parse_args( $instance, $defaults );

		// Service-Definition aus der Registry holen.
		$service = DHPS_Service_Registry::get_service( $instance['service'] );

		if ( null === $service ) {
			return;
		}

		// API-Parameter aufbauen (auth + default_params + admin_options).
		$params = array();

		// 1. Auth-Parameter (ota oder kdnr).
		$auth_value = get_option( $service['auth_option'], '' );
		$params[ $service['auth_type'] ] = $auth_value;

		// 2. Default-Params des Service (z.B. modus => 'p').
		foreach ( $service['default_params'] as $key => $value ) {
			$params[ $key ] = $value;
		}

		// 3. Admin-Options verarbeiten.
		$this->apply_admin_options( $params, $service );

		// HTML-Inhalt von der API abrufen.
		$cache_ttl = absint( $instance['cache'] );
		$html      = $this->api_client->fetch_content( $service['endpoint'], $params, $cache_ttl );

		// Durch Renderer wrappen.
		$output = $this->renderer->render( $html, $instance['service'], $instance['layout'], $instance['class'] );

		// Widget-Wrapper ausgeben.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- before_widget/after_widget werden von WordPress generiert.
		echo $args['before_widget'];

		// Titel ausgeben, falls gesetzt.
		if ( ! empty( $instance['title'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- before_title/after_title werden von WordPress generiert.
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title'];
		}

		// Service-Inhalt ausgeben.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML-Inhalt kommt von der vertrauenswuerdigen Deubner-API.
		echo $output;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- after_widget wird von WordPress generiert.
		echo $args['after_widget'];
	}

	/**
	 * Admin-Formular fuer die Widget-Konfiguration.
	 *
	 * Zeigt Eingabefelder fuer Titel, Service-Auswahl, Layout,
	 * CSS-Klasse und Cache-Dauer an. Nutzt get_field_id() und
	 * get_field_name() fuer korrekte Widget-Formular-IDs.
	 *
	 * @since 0.5.0
	 *
	 * @param array $instance Aktuelle Widget-Einstellungen.
	 *
	 * @return void
	 */
	public function form( $instance ): void {
		$defaults = array(
			'service' => 'mio',
			'layout'  => 'default',
			'class'   => '',
			'cache'   => '3600',
			'title'   => '',
		);

		$instance = wp_parse_args( $instance, $defaults );

		$services = DHPS_Service_Registry::get_services();
		$layouts  = DHPS_Renderer::get_available_layouts();
		?>

		<!-- Titel -->
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Titel:', 'deubner_hp_services' ); ?>
			</label>
			<input
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $instance['title'] ); ?>"
			/>
		</p>

		<!-- Service -->
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'service' ) ); ?>">
				<?php esc_html_e( 'Service:', 'deubner_hp_services' ); ?>
			</label>
			<select
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'service' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'service' ) ); ?>"
			>
				<?php foreach ( $services as $key => $service_def ) : ?>
					<option
						value="<?php echo esc_attr( $key ); ?>"
						<?php selected( $instance['service'], $key ); ?>
					>
						<?php echo esc_html( $service_def['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<!-- Layout -->
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'layout' ) ); ?>">
				<?php esc_html_e( 'Layout:', 'deubner_hp_services' ); ?>
			</label>
			<select
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'layout' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'layout' ) ); ?>"
			>
				<?php foreach ( $layouts as $layout_key => $layout_label ) : ?>
					<option
						value="<?php echo esc_attr( $layout_key ); ?>"
						<?php selected( $instance['layout'], $layout_key ); ?>
					>
						<?php echo esc_html( $layout_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<!-- CSS-Klasse -->
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'class' ) ); ?>">
				<?php esc_html_e( 'CSS-Klasse (optional):', 'deubner_hp_services' ); ?>
			</label>
			<input
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'class' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'class' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $instance['class'] ); ?>"
			/>
		</p>

		<!-- Cache-Dauer -->
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'cache' ) ); ?>">
				<?php esc_html_e( 'Cache-Dauer (Sekunden):', 'deubner_hp_services' ); ?>
			</label>
			<input
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'cache' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'cache' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $instance['cache'] ); ?>"
			/>
		</p>

		<?php
	}

	/**
	 * Validiert und sanitized die Widget-Einstellungen beim Speichern.
	 *
	 * Prueft Service und Layout gegen die Registry bzw. verfuegbare
	 * Layouts und sanitized alle Text-Felder.
	 *
	 * @since 0.5.0
	 *
	 * @param array $new_instance Neue Einstellungen aus dem Formular.
	 * @param array $old_instance Vorherige Einstellungen.
	 *
	 * @return array Bereinigte Einstellungen zum Speichern.
	 */
	public function update( $new_instance, $old_instance ): array {
		$instance = array();

		// Titel sanitizen.
		$instance['title'] = sanitize_text_field( $new_instance['title'] ?? '' );

		// Service gegen Registry validieren.
		$service_key = sanitize_text_field( $new_instance['service'] ?? 'mio' );
		$valid_services = array_keys( DHPS_Service_Registry::get_services() );

		$instance['service'] = in_array( $service_key, $valid_services, true )
			? $service_key
			: 'mio';

		// Layout gegen verfuegbare Layouts validieren.
		$layout_key = sanitize_text_field( $new_instance['layout'] ?? 'default' );
		$valid_layouts = array_keys( DHPS_Renderer::get_available_layouts() );

		$instance['layout'] = in_array( $layout_key, $valid_layouts, true )
			? $layout_key
			: 'default';

		// CSS-Klasse sanitizen.
		$instance['class'] = sanitize_text_field( $new_instance['class'] ?? '' );

		// Cache-Dauer sanitizen (muss numerisch sein).
		$instance['cache'] = sanitize_text_field( $new_instance['cache'] ?? '3600' );

		return $instance;
	}

	/**
	 * Verarbeitet die Admin-Optionen eines Service und fuegt sie den API-Parametern hinzu.
	 *
	 * Behandelt den Spezialfall 'variante_switch', bei dem der Admin-Wert
	 * in einen der Variante-Strings uebersetzt wird:
	 * - '1' => 'tagesaktuell'
	 * - '2' => 'kategorisiert'
	 * - '0' => Variante wird nicht gesetzt (kein Shortcode-Attribut im Widget)
	 *
	 * Alle anderen Admin-Options werden direkt als API-Parameter uebernommen,
	 * sofern der Wert nicht leer ist.
	 *
	 * @since 0.5.0
	 *
	 * @param array $params  Referenz auf das Parameter-Array (wird modifiziert).
	 * @param array $service Service-Definition aus der Registry.
	 *
	 * @return void
	 */
	private function apply_admin_options( array &$params, array $service ): void {
		foreach ( $service['admin_options'] as $option_key => $param_type ) {
			$option_value = get_option( $option_key, '' );

			if ( 'variante_switch' === $param_type ) {
				$this->apply_variante_switch( $params, $option_value );
				continue;
			}

			// Regulaere Admin-Option: direkt als API-Parameter uebernehmen.
			if ( '' !== $option_value ) {
				$params[ $param_type ] = $option_value;
			}
		}
	}

	/**
	 * Behandelt die Variante-Switch-Logik fuer MIO und LXMIO.
	 *
	 * Im Widget-Kontext gibt es keine Shortcode-Attribute, daher
	 * wird bei Modus '0' keine Variante gesetzt.
	 *
	 * @since 0.5.0
	 *
	 * @param array  $params       Referenz auf das Parameter-Array (wird modifiziert).
	 * @param string $switch_value Admin-Option-Wert ('0', '1' oder '2').
	 *
	 * @return void
	 */
	private function apply_variante_switch( array &$params, string $switch_value ): void {
		switch ( $switch_value ) {
			case '1':
				$params['variante'] = 'tagesaktuell';
				break;

			case '2':
				$params['variante'] = 'kategorisiert';
				break;

			// Bei '0' kein Shortcode-Attribut verfuegbar - Variante bleibt ungesetzt.
		}
	}
}
