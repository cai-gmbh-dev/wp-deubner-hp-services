<?php
/**
 * WP-CLI-Commands fuer Deubner HP Services.
 *
 * Wird nur registriert wenn WordPress in der CLI-Umgebung laeuft
 * (`defined('WP_CLI') && WP_CLI === true`). Im normalen Web-Request
 * passiert nichts.
 *
 * Aktuell verfuegbar:
 *   wp dhps elementor-tokens     Token-Inventar fuer Elementor-Bridge-Debugging
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.16.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI ) {
    return;
}

/**
 * Class DHPS_CLI_Commands
 *
 * @since 0.16.3
 */
class DHPS_CLI_Commands {

    /**
     * Pfad zur Design-Tokens-CSS-Datei.
     *
     * @since 0.16.3
     */
    public const DESIGN_TOKENS_CSS = 'css/dhps-design-tokens.css';

    /**
     * Pfad zur Elementor-Bridge-CSS-Datei.
     *
     * @since 0.16.3
     */
    public const BRIDGE_CSS = 'css/dhps-elementor-bridge.css';

    /**
     * Token-Inventar fuer Elementor-Bridge-Debugging.
     *
     * Listet alle definierten `--dhps-color-*`-Tokens aus
     * `css/dhps-design-tokens.css`, plus den aktuellen Bridge-Status
     * und Elementor-Stack-Versionen.
     *
     * ## EXAMPLES
     *
     *     wp dhps elementor-tokens
     *     wp dhps elementor-tokens --format=json
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output-Format: table (default), json, csv, yaml.
     *
     * @when after_wp_load
     *
     * @since 0.16.3
     *
     * @param array<int, string>  $args       Positionale Argumente (ungenutzt).
     * @param array<string, mixed> $assoc_args Assoziative Argumente.
     *
     * @return void
     */
    public static function elementor_tokens( $args, $assoc_args ): void {
        $format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

        // Bridge-Status + Elementor-Stack.
        $bridge_enabled = (bool) get_option( 'dhps_elementor_bridge_enabled', false );
        $elementor_ver  = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'nicht geladen';
        $pro_ver        = defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : 'nicht geladen';

        WP_CLI::log( '' );
        WP_CLI::log( '=== DHPS Elementor Token-Inventar ===' );
        WP_CLI::log( 'Plugin:           ' . DEUBNER_HP_SERVICES_VERSION );
        WP_CLI::log( 'Elementor:        ' . $elementor_ver );
        WP_CLI::log( 'Elementor Pro:    ' . $pro_ver );
        WP_CLI::log( 'Bridge aktiv:     ' . ( $bridge_enabled ? 'JA' : 'NEIN (Default)' ) );
        WP_CLI::log( 'Min Elementor:    ' . DHPS_Elementor::ELEMENTOR_MIN_VERSION );
        WP_CLI::log( '' );

        // CSS-Tokens parsen.
        $tokens_path = DEUBNER_HP_SERVICES_PATH . self::DESIGN_TOKENS_CSS;
        $tokens      = self::parse_css_tokens( $tokens_path );

        if ( empty( $tokens ) ) {
            WP_CLI::warning(
                sprintf(
                    'Keine Tokens in %s gefunden.',
                    esc_html( self::DESIGN_TOKENS_CSS )
                )
            );
            return;
        }

        WP_CLI::log( sprintf( 'Geparst aus %s:', self::DESIGN_TOKENS_CSS ) );
        WP_CLI::log( sprintf( '%d Tokens gefunden.', count( $tokens ) ) );
        WP_CLI::log( '' );

        // Items fuer WP_CLI\Utils\format_items aufbereiten.
        $items = array();
        foreach ( $tokens as $name => $value ) {
            $items[] = array(
                'token' => $name,
                'value' => $value,
                'group' => self::classify_token( $name ),
            );
        }

        WP_CLI\Utils\format_items( $format, $items, array( 'token', 'value', 'group' ) );

        if ( ! $bridge_enabled ) {
            WP_CLI::log( '' );
            WP_CLI::log( 'Hinweis: Token-Bridge ist INAKTIV. Brand-Tokens werden NICHT auf' );
            WP_CLI::log( 'Elementor-Atomic-Tokens (--e-global-*) gemappt. Aktivieren ueber:' );
            WP_CLI::log( '  wp option update dhps_elementor_bridge_enabled 1' );
        }
    }

    /**
     * Parst CSS-Custom-Properties aus einer Datei.
     *
     * Sucht alle `--name: value;`-Deklarationen ungeachtet des umgebenden
     * Selektors. Sehr toleranter Parser, ASCII-only, keine
     * Komment-Behandlung weil wir nur Diagnose-Output liefern.
     *
     * @since 0.16.3
     *
     * @param string $path Absoluter Pfad zur CSS-Datei.
     *
     * @return array<string, string> Token-Name => Token-Wert.
     */
    private static function parse_css_tokens( string $path ): array {
        if ( ! is_readable( $path ) ) {
            return array();
        }

        $contents = (string) file_get_contents( $path );
        if ( '' === $contents ) {
            return array();
        }

        // Match: --token-name: value;
        $regex = '/(--[a-z0-9-]+)\s*:\s*([^;]+);/i';
        if ( false === preg_match_all( $regex, $contents, $matches, PREG_SET_ORDER ) ) {
            return array();
        }

        $tokens = array();
        foreach ( $matches as $match ) {
            $name  = (string) $match[1];
            $value = trim( (string) $match[2] );

            // Erstwert gewinnt (definiert vor Override).
            if ( ! isset( $tokens[ $name ] ) ) {
                $tokens[ $name ] = $value;
            }
        }

        return $tokens;
    }

    /**
     * Gruppiert einen Token-Namen in eine logische Kategorie.
     *
     * @since 0.16.3
     *
     * @param string $name Token-Name inkl. `--`-Praefix.
     *
     * @return string Kategorie-Slug.
     */
    private static function classify_token( string $name ): string {
        if ( false !== strpos( $name, 'steuern' ) ) {
            return 'brand-steuern';
        }
        if ( false !== strpos( $name, 'recht' ) ) {
            return 'brand-recht';
        }
        if ( false !== strpos( $name, 'medizin' ) ) {
            return 'brand-medizin';
        }
        if ( false !== strpos( $name, 'badge' ) ) {
            return 'badge';
        }
        if ( false !== strpos( $name, 'text' ) || false !== strpos( $name, 'meta' ) ) {
            return 'text';
        }
        if ( false !== strpos( $name, 'bg' ) || false !== strpos( $name, 'border' ) ) {
            return 'layout';
        }
        if ( false !== strpos( $name, 'primary' )
            || false !== strpos( $name, 'success' )
            || false !== strpos( $name, 'warning' )
            || false !== strpos( $name, 'danger' )
            || false !== strpos( $name, 'info' ) ) {
            return 'semantic';
        }
        return 'misc';
    }
}

WP_CLI::add_command( 'dhps elementor-tokens', array( DHPS_CLI_Commands::class, 'elementor_tokens' ) );
