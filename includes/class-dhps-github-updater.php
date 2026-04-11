<?php
/**
 * GitHub-basierter Plugin-Updater fuer Deubner HP Services.
 *
 * Prueft GitHub Releases auf neue Versionen und integriert sich
 * nahtlos in den WordPress-Update-Mechanismus (Dashboard, CLI, API).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DHPS_GitHub_Updater
 *
 * Verbindet das Plugin mit GitHub Releases fuer automatische Updates.
 * Nutzt die GitHub REST API v3 (oeffentlich, kein Token noetig).
 *
 * Workflow:
 * 1. WordPress prueft regelmaessig auf Plugin-Updates (Transient).
 * 2. Diese Klasse hookt sich ein und fragt die GitHub API nach dem neuesten Release.
 * 3. Ist die GitHub-Version neuer, wird ein Update im Dashboard angezeigt.
 * 4. Der Download erfolgt direkt als ZIP von GitHub Releases.
 * 5. Nach dem Entpacken wird der Verzeichnisname korrigiert (GitHub-ZIP-Struktur).
 *
 * @since 0.9.5
 */
class DHPS_GitHub_Updater {

    /**
     * GitHub Repository Owner.
     *
     * @var string
     */
    private $owner;

    /**
     * GitHub Repository Name.
     *
     * @var string
     */
    private $repo;

    /**
     * Plugin-Basename (z.B. 'wp-deubner-hp-services/Deubner_HP_Services.php').
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Plugin-Slug (Verzeichnisname, z.B. 'wp-deubner-hp-services').
     *
     * @var string
     */
    private $plugin_slug;

    /**
     * Aktuelle Plugin-Version.
     *
     * @var string
     */
    private $current_version;

    /**
     * Gecachte GitHub-Release-Daten.
     *
     * @var array|null
     */
    private $github_data = null;

    /**
     * Cache-Transient-Name.
     *
     * @var string
     */
    private $transient_key = 'dhps_github_release';

    /**
     * Cache-TTL in Sekunden (12 Stunden).
     *
     * @var int
     */
    private $cache_ttl = 43200;

    /**
     * Konstruktor.
     *
     * @since 0.9.5
     *
     * @param string $owner           GitHub-Benutzername/Organisation.
     * @param string $repo            Repository-Name.
     * @param string $plugin_basename Plugin-Basename via plugin_basename(__FILE__).
     * @param string $current_version Aktuelle Plugin-Version.
     */
    public function __construct( string $owner, string $repo, string $plugin_basename, string $current_version ) {
        $this->owner           = $owner;
        $this->repo            = $repo;
        $this->plugin_basename = $plugin_basename;
        $this->plugin_slug     = dirname( $plugin_basename );
        $this->current_version = $current_version;
    }

    /**
     * Registriert alle WordPress-Hooks fuer den Update-Mechanismus.
     *
     * @since 0.9.5
     *
     * @return void
     */
    public function init(): void {
        // Primaerer Update-Check: WordPress 5.8+ Update URI Mechanismus.
        // Wird von WP fuer Plugins mit Update URI Header aufgerufen.
        add_filter( 'update_plugins_github.com', array( $this, 'check_update_uri' ), 10, 4 );

        // Fallback: Klassischer Update-Check via Transient-Filter.
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );

        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_source_selection', array( $this, 'fix_directory_name' ), 10, 4 );
    }

    /**
     * WordPress 5.8+ Update URI Handler.
     *
     * Wird von WordPress via 'update_plugins_github.com' aufgerufen
     * wenn das Plugin einen Update URI mit github.com hostname hat.
     *
     * @since 0.9.6
     *
     * @param array|false $update     Update-Daten oder false.
     * @param array       $plugin_data Plugin-Header-Daten.
     * @param string      $plugin_file Plugin-Datei relativ zu plugins/.
     * @param string[]    $locales     Installierte Locales.
     *
     * @return array|false Update-Daten oder false wenn kein Update.
     */
    public function check_update_uri( $update, array $plugin_data, string $plugin_file, array $locales ) {
        // Nur fuer dieses Plugin reagieren.
        if ( $plugin_file !== $this->plugin_basename ) {
            return $update;
        }

        $release = $this->get_latest_release();

        if ( null === $release || empty( $release['tag_name'] ) ) {
            return $update;
        }

        $latest_version = $this->normalize_version( $release['tag_name'] );

        if ( version_compare( $latest_version, $this->current_version, '>' ) ) {
            return array(
                'slug'         => $this->plugin_slug,
                'version'      => $latest_version,
                'url'          => $release['html_url'],
                'package'      => $release['zipball_url'],
                'requires'     => '6.0',
                'requires_php' => '8.0',
            );
        }

        return $update;
    }

    /**
     * Prueft ob ein Update auf GitHub verfuegbar ist.
     *
     * Wird von WordPress via 'pre_set_site_transient_update_plugins' aufgerufen.
     *
     * @since 0.9.5
     *
     * @param object $transient WordPress Update-Transient.
     *
     * @return object Modifizierter Transient mit Update-Info (falls verfuegbar).
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();

        if ( null === $release ) {
            return $transient;
        }

        $latest_version = $this->normalize_version( $release['tag_name'] );

        if ( version_compare( $latest_version, $this->current_version, '>' ) ) {
            $transient->response[ $this->plugin_basename ] = (object) array(
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin_basename,
                'new_version' => $latest_version,
                'url'         => $release['html_url'],
                'package'     => $release['zipball_url'],
                'tested'      => '',
                'requires'    => '6.0',
                'requires_php' => '8.0',
            );
        } else {
            // Kein Update - in no_update eintragen (verhindert WP.org-Abfrage).
            $transient->no_update[ $this->plugin_basename ] = (object) array(
                'slug'        => $this->plugin_slug,
                'plugin'      => $this->plugin_basename,
                'new_version' => $this->current_version,
                'url'         => 'https://github.com/' . $this->owner . '/' . $this->repo,
                'package'     => '',
            );
        }

        return $transient;
    }

    /**
     * Liefert Plugin-Informationen fuer den WordPress Plugin-Details-Dialog.
     *
     * Wird von WordPress via 'plugins_api' aufgerufen wenn der Nutzer
     * auf "Details ansehen" klickt.
     *
     * @since 0.9.5
     *
     * @param false|object|array $result  Vorheriges Ergebnis.
     * @param string             $action  API-Aktion ('plugin_information').
     * @param object             $args    Abfrage-Argumente (slug).
     *
     * @return false|object Plugin-Info oder false falls nicht zustaendig.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || $this->plugin_slug !== ( $args->slug ?? '' ) ) {
            return $result;
        }

        $release = $this->get_latest_release();

        if ( null === $release ) {
            return $result;
        }

        $latest_version = $this->normalize_version( $release['tag_name'] );

        return (object) array(
            'name'            => 'Deubner Homepage Services',
            'slug'            => $this->plugin_slug,
            'version'         => $latest_version,
            'author'          => '<a href="https://deubner-verlag.de">Deubner Verlag</a>',
            'homepage'        => 'https://github.com/' . $this->owner . '/' . $this->repo,
            'requires'        => '6.0',
            'requires_php'    => '8.0',
            'tested'          => '',
            'download_link'   => $release['zipball_url'],
            'trunk'           => $release['zipball_url'],
            'last_updated'    => $release['published_at'] ?? '',
            'sections'        => array(
                'description'  => 'Integration der Deubner Homepage Services (Steuerrecht, Recht, Medizin) via Shortcode, Widget und Elementor.',
                'changelog'    => $this->format_changelog( $release ),
            ),
        );
    }

    /**
     * Korrigiert den Verzeichnisnamen nach dem Entpacken des GitHub-ZIPs.
     *
     * GitHub-ZIPs entpacken zu '{owner}-{repo}-{hash}/', WordPress erwartet
     * aber den Plugin-Slug als Verzeichnisname.
     *
     * @since 0.9.5
     *
     * @param string       $source        Quellverzeichnis (entpackt).
     * @param string       $remote_source Remote-Quelle.
     * @param \WP_Upgrader $upgrader      Upgrader-Instanz.
     * @param array        $hook_extra    Zusaetzliche Hook-Daten.
     *
     * @return string|WP_Error Korrigierter Pfad oder WP_Error bei Fehler.
     */
    public function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra ) {
        // Nur fuer dieses Plugin eingreifen.
        if ( ! isset( $hook_extra['plugin'] ) || $this->plugin_basename !== $hook_extra['plugin'] ) {
            return $source;
        }

        global $wp_filesystem;

        $corrected_source = trailingslashit( $remote_source ) . $this->plugin_slug . '/';

        if ( $source === $corrected_source ) {
            return $source;
        }

        $moved = $wp_filesystem->move( $source, $corrected_source );

        if ( ! $moved ) {
            return new \WP_Error(
                'dhps_rename_failed',
                'Konnte das Plugin-Verzeichnis nicht umbenennen.'
            );
        }

        return $corrected_source;
    }

    /**
     * Holt die neueste Release-Information von GitHub (mit Caching).
     *
     * @since 0.9.5
     *
     * @return array|null Release-Daten oder null bei Fehler.
     */
    private function get_latest_release(): ?array {
        if ( null !== $this->github_data ) {
            return $this->github_data;
        }

        // Transient-Cache pruefen.
        $cached = get_transient( $this->transient_key );

        if ( false !== $cached && is_array( $cached ) && ! empty( $cached['tag_name'] ) ) {
            $this->github_data = $cached;
            return $this->github_data;
        }

        // GitHub API abfragen.
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->owner,
            $this->repo
        );

        $response = wp_remote_get( $url, array(
            'timeout'    => 10,
            'headers'    => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
            'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            // Bei Fehler kurzfristig cachen um API nicht zu ueberlasten.
            set_transient( $this->transient_key, array(), 600 );
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
            set_transient( $this->transient_key, array(), 600 );
            return null;
        }

        $this->github_data = $body;
        set_transient( $this->transient_key, $body, $this->cache_ttl );

        return $this->github_data;
    }

    /**
     * Normalisiert einen Git-Tag zu einer Versionsnummer.
     *
     * Entfernt fuehrende 'v' oder 'V' (z.B. 'v1.2.3' -> '1.2.3').
     *
     * @since 0.9.5
     *
     * @param string $tag Git-Tag (z.B. 'v0.9.5', '0.9.5').
     *
     * @return string Bereinigte Versionsnummer.
     */
    private function normalize_version( string $tag ): string {
        return ltrim( $tag, 'vV' );
    }

    /**
     * Formatiert die Release-Notes als HTML-Changelog.
     *
     * @since 0.9.5
     *
     * @param array $release GitHub-Release-Daten.
     *
     * @return string HTML-Changelog.
     */
    private function format_changelog( array $release ): string {
        $body = $release['body'] ?? '';

        if ( empty( $body ) ) {
            return '<p>Keine Release-Notes verfuegbar.</p>';
        }

        // Einfache Markdown-zu-HTML-Konvertierung fuer Changelogs.
        $html = esc_html( $body );
        $html = nl2br( $html );

        // Markdown-Listen erkennen (- item).
        $html = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html );
        $html = preg_replace( '/((?:<li>.+<\/li>\s*)+)/', '<ul>$1</ul>', $html );

        // Markdown-Ueberschriften (## Header).
        $html = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $html );
        $html = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $html );

        return $html;
    }
}
