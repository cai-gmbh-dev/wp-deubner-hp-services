<?php
/**
 * Shortcode-Handler fuer den Deubner Homepage Service.
 *
 * Generische Shortcode-Klasse, die alle 9 Services ueber die zentrale
 * Service-Registry verarbeitet. Ersetzt die vorherigen individuellen
 * Shortcode-Methoden und eliminiert Code-Duplikation.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DHPS_Shortcodes
 *
 * Registriert alle Shortcodes aus der Service-Registry und verarbeitet
 * sie ueber einen einzigen generischen Handler.
 *
 * Der Handler baut fuer jeden Shortcode-Aufruf das korrekte Parameter-Array
 * auf, bestehend aus:
 * 1. Auth-Parameter (ota oder kdnr aus der WordPress-Option)
 * 2. Default-Parameter des Service
 * 3. Admin-Optionen (mit Spezialbehandlung fuer den Variante-Switch)
 * 4. Shortcode-Attribute (nur wenn nicht leer/default)
 * 5. URL-Parameter (z.B. $_GET['video'] via absint())
 *
 * @since   0.4.0
 * @package Deubner Homepage-Service
 */
class DHPS_Shortcodes {

    /**
     * API-Client fuer den Abruf der Service-Inhalte.
     *
     * @since 0.4.0
     * @var DHPS_API_Client
     */
    private $api_client;

    /**
     * Renderer fuer die Ausgabe der Service-Inhalte.
     *
     * @since 0.5.0
     * @var DHPS_Renderer
     */
    private $renderer;

    /**
     * Content-Pipeline fuer Parsing und strukturiertes Rendering.
     *
     * @since 0.9.0
     * @var DHPS_Content_Pipeline
     */
    private $pipeline;

    /**
     * Constructor.
     *
     * @since 0.4.0
     * @since 0.5.0 Renderer-Parameter hinzugefuegt.
     * @since 0.9.0 Content-Pipeline-Parameter hinzugefuegt.
     *
     * @param DHPS_API_Client       $api_client API-Client-Instanz fuer HTTP-Requests.
     * @param DHPS_Renderer         $renderer   Renderer-Instanz fuer die HTML-Ausgabe.
     * @param DHPS_Content_Pipeline $pipeline   Content-Pipeline fuer Parsing.
     */
    public function __construct( DHPS_API_Client $api_client, DHPS_Renderer $renderer, DHPS_Content_Pipeline $pipeline ) {
        $this->api_client = $api_client;
        $this->renderer   = $renderer;
        $this->pipeline   = $pipeline;
    }

    /**
     * Registriert alle Shortcodes aus der Service-Registry.
     *
     * Nutzt den 3. Parameter von add_shortcode(), um den Tag-Namen
     * an den generischen Handler zu uebergeben.
     *
     * @since 0.4.0
     *
     * @return void
     */
    public function register(): void {
        $shortcode_names = DHPS_Service_Registry::get_shortcode_names();

        foreach ( $shortcode_names as $shortcode ) {
            add_shortcode( $shortcode, array( $this, 'handle_shortcode' ) );
        }
    }

    /**
     * Generischer Shortcode-Handler fuer alle Deubner-Services.
     *
     * Wird von WordPress fuer jeden registrierten Shortcode aufgerufen.
     * Ermittelt anhand des Tag-Namens die Service-Definition aus der Registry
     * und baut die API-Parameter in der korrekten Reihenfolge auf.
     *
     * Parameter-Aufbau-Reihenfolge:
     * 1. Auth-Parameter (ota/kdnr)
     * 2. Default-Params des Service (z.B. modus=p)
     * 3. Admin-Options (Variante-Switch, Anzahl, Teaser-Einstellungen)
     * 4. Shortcode-Attribute (ueberschreiben ggf. vorherige Werte)
     * 5. URL-Parameter ($_GET['video'] bei supports_video)
     *
     * @since 0.4.0
     *
     * @param array|string $atts    Shortcode-Attribute aus dem Editor.
     * @param string       $content Eingeschlossener Content (nicht verwendet).
     * @param string       $tag     Name des Shortcodes (z.B. 'mio', 'tp').
     *
     * @return string Gerenderter HTML-Inhalt oder leerer String bei unbekanntem Service.
     */
    public function handle_shortcode( $atts, string $content, string $tag ): string {
        $service = DHPS_Service_Registry::get_service( $tag );

        if ( null === $service ) {
            return '';
        }

        // Shortcode-Attribute mit Defaults mergen.
        $attributes = shortcode_atts( $service['shortcode_atts'], $atts, $tag );

        // Universelle Parameter extrahieren und aus den API-Attributen entfernen.
        $layout    = isset( $attributes['layout'] ) ? $attributes['layout'] : 'default';
        $css_class = isset( $attributes['class'] ) ? $attributes['class'] : '';
        $cache_ttl = isset( $attributes['cache'] ) ? absint( $attributes['cache'] ) : 3600;

        unset( $attributes['layout'], $attributes['class'], $attributes['cache'] );

        // Parameter-Array aufbauen.
        $params = array();

        // 1. Auth-Parameter (ota oder kdnr).
        $auth_value = get_option( $service['auth_option'], '' );
        $params[ $service['auth_type'] ] = $auth_value;

        // 2. Default-Params des Service (z.B. modus => 'p').
        foreach ( $service['default_params'] as $key => $value ) {
            $params[ $key ] = $value;
        }

        // 3. Admin-Options verarbeiten.
        $this->apply_admin_options( $params, $service, $attributes );

        // 4. Shortcode-Attribute (nur nicht-leere/nicht-default Werte).
        $this->apply_shortcode_attributes( $params, $attributes, $service );

        // 5. URL-Parameter: $_GET['video'] bei Services mit Video-Support.
        if ( ! empty( $service['supports_video'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Oeffentlicher Frontend-Parameter, kein Formular.
            $videonr = isset( $_GET['video'] ) ? absint( $_GET['video'] ) : 0;
            $params['video'] = $videonr;
        }

        // Inhalt ueber die Content-Pipeline abrufen, parsen und rendern.
        // Die Pipeline prueft ob ein Parser registriert ist und faellt
        // bei nicht-migrierten Services auf den Raw-HTML-Renderer zurueck.
        return $this->pipeline->render_service( $tag, $service['endpoint'], $params, $cache_ttl, $layout, $css_class );
    }

    /**
     * Verarbeitet die Admin-Optionen eines Service und fuegt sie den Parametern hinzu.
     *
     * Behandelt den Spezialfall 'variante_switch', bei dem der Admin-Wert
     * in einen der Variante-Strings uebersetzt wird:
     * - '1' => 'tagesaktuell'
     * - '2' => 'kategorisiert'
     * - '0' => Variante wird ueber Shortcode-Parameter gesteuert
     *
     * Alle anderen Admin-Options werden direkt als Parameter uebernommen,
     * sofern der Wert nicht leer ist.
     *
     * @since 0.4.0
     *
     * @param array  $params     Referenz auf das Parameter-Array (wird modifiziert).
     * @param array  $service    Service-Definition aus der Registry.
     * @param array  $attributes Gemergte Shortcode-Attribute.
     *
     * @return void
     */
    private function apply_admin_options( array &$params, array $service, array $attributes ): void {
        foreach ( $service['admin_options'] as $option_key => $param_type ) {
            $option_value = get_option( $option_key, '' );

            if ( 'variante_switch' === $param_type ) {
                $this->apply_variante_switch( $params, $option_value, $attributes );
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
     * Der Admin kann zwischen drei Modi waehlen:
     * - '1': Festgelegt auf 'tagesaktuell'
     * - '2': Festgelegt auf 'kategorisiert'
     * - '0': Die Variante wird ueber den Shortcode-Parameter gesteuert
     *
     * Im Modus '0' wird der Shortcode-Attribut-Wert 'variante' nur dann
     * uebernommen, wenn er nicht leer ist.
     *
     * @since 0.4.0
     *
     * @param array  $params       Referenz auf das Parameter-Array (wird modifiziert).
     * @param string $switch_value Admin-Option-Wert ('0', '1' oder '2').
     * @param array  $attributes   Gemergte Shortcode-Attribute.
     *
     * @return void
     */
    private function apply_variante_switch( array &$params, string $switch_value, array $attributes ): void {
        switch ( $switch_value ) {
            case '1':
                $params['variante'] = 'tagesaktuell';
                break;

            case '2':
                $params['variante'] = 'kategorisiert';
                break;

            case '0':
                // Variante wird ueber Shortcode-Parameter gesteuert.
                if ( isset( $attributes['variante'] ) && '' !== $attributes['variante'] ) {
                    $params['variante'] = $attributes['variante'];
                }
                break;
        }
    }

    /**
     * Uebertraegt die Shortcode-Attribute in das Parameter-Array.
     *
     * Nur Attribute, deren Wert vom Default abweicht (nicht leer bzw. nicht
     * der Default-Wert), werden als API-Parameter uebernommen. Attribute,
     * die bereits durch die Admin-Options oder den Variante-Switch gesetzt
     * wurden, werden dabei uebersprungen bzw. ergaenzt.
     *
     * Spezialbehandlung:
     * - Bei Services mit 'variante_switch' in den Admin-Options wird das
     *   Shortcode-Attribut 'variante' hier uebersprungen (bereits in
     *   apply_variante_switch behandelt).
     * - Numerische Defaults (z.B. 0 bei 'teasermodus' in [tp]) werden
     *   korrekt mit ihrem Default-Wert verglichen.
     *
     * @since 0.4.0
     *
     * @param array $params     Referenz auf das Parameter-Array (wird modifiziert).
     * @param array $attributes Gemergte Shortcode-Attribute.
     * @param array $service    Service-Definition aus der Registry.
     *
     * @return void
     */
    private function apply_shortcode_attributes( array &$params, array $attributes, array $service ): void {
        // Pruefen ob dieser Service einen Variante-Switch hat.
        $has_variante_switch = in_array( 'variante_switch', $service['admin_options'], true );

        foreach ( $attributes as $key => $value ) {
            // Variante wird durch den Switch gesteuert, nicht hier.
            if ( 'variante' === $key && $has_variante_switch ) {
                continue;
            }

            $default = $service['shortcode_atts'][ $key ] ?? '';

            // Nur nicht-default Werte als API-Parameter uebernehmen.
            // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- Absichtlich loose, da Shortcode-Werte immer Strings sind aber Defaults auch int sein koennen.
            if ( $value != $default ) {
                $params[ $key ] = $value;
            }
        }
    }
}
