<?php
/**
 * Service-Registry fuer den Deubner Homepage Service.
 *
 * Zentrale, deklarative Definition aller 9 Deubner-Services.
 * Wird von der Shortcode-Klasse und den Admin-Seiten konsumiert,
 * um Code-Duplikation zu vermeiden.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DHPS_Service_Registry
 *
 * Stellt die zentrale Service-Registry bereit. Jeder Service ist als
 * assoziatives Array definiert mit Endpoint, Auth-Typ, Shortcode-Attributen,
 * Admin-Optionen und weiteren Metadaten.
 *
 * @since   0.4.0
 * @package Deubner Homepage-Service
 */
class DHPS_Service_Registry {

    /**
     * Gecachte Service-Definitionen.
     *
     * @since 0.4.0
     * @var array|null
     */
    private static $services = null;

    /**
     * Gibt alle Service-Definitionen als assoziatives Array zurueck.
     *
     * Der Array-Key ist der Shortcode-Name (z.B. 'mio', 'lxmio').
     * Jeder Eintrag enthaelt die vollstaendige Konfiguration eines Service.
     *
     * @since 0.4.0
     *
     * @return array Assoziatives Array aller Service-Definitionen.
     */
    public static function get_services(): array {
        if ( null !== self::$services ) {
            return self::$services;
        }

        self::$services = array(

            /*
             * ---------------------------------------------------------------
             * 1. MI-Online Steuerrecht
             * ---------------------------------------------------------------
             */
            'mio' => array(
                'name'           => 'MI-Online Steuerrecht',
                'endpoint'       => 'einbau/mio/bin/php_inhalt.php',
                'auth_type'      => 'ota',
                'auth_option'    => 'dhps_ota_mio',
                'shortcode_atts' => array(
                    'teasermodus'  => '',
                    'filter'       => '',
                    'variante'     => '',
                    'modus'        => '',
                    'st_kategorie' => '',
                    'layout'       => 'default',
                    'class'        => '',
                    'cache'        => '3600',
                ),
                'admin_options'  => array(
                    'dhps_variante' => 'variante_switch',
                    'dhps_anzahl'   => 'anzahl',
                ),
                'supports_video' => false,
                'default_params' => array(),
                'admin_page'     => 'dhps_mio_page',
                'admin_title'    => 'MI-Online Steuerrecht',
                'category'       => 'steuern',
                'shop_url'       => 'https://www.deubner-steuern.de/shop/',
                'icon'           => 'dashicons-media-text',
                'admin_fields'   => array(
                    array(
                        'option_key' => 'dhps_ota_mio',
                        'field_name' => 'ota_mio',
                        'label'      => 'OTA-Nummer',
                        'type'       => 'text',
                    ),
                    array(
                        'option_key' => 'dhps_variante',
                        'field_name' => 'variante',
                        'label'      => 'Variante',
                        'type'       => 'select',
                        'options'    => array(
                            '1' => 'tagesaktuell',
                            '2' => 'kategorisiert',
                            '0' => 'Definiert ueber Parameter',
                        ),
                    ),
                    array(
                        'option_key' => 'dhps_anzahl',
                        'field_name' => 'anzahl',
                        'label'      => 'Anzahl der Eintraege je Seite (nur bei tagesaktueller Darstellung wirksam)',
                        'type'       => 'text',
                    ),
                ),
            ),

            /*
             * ---------------------------------------------------------------
             * 2. MI-Online Recht
             * ---------------------------------------------------------------
             * Nutzt denselben Endpoint wie MIO, aber eigene OTA-Option.
             */
            'lxmio' => array(
                'name'           => 'MI-Online Recht',
                'endpoint'       => 'einbau/mio/bin/php_inhalt.php',
                'auth_type'      => 'ota',
                'auth_option'    => 'dhps_lxmio_ota',
                'shortcode_atts' => array(
                    'teasermodus'  => '',
                    'filter'       => '',
                    'variante'     => '',
                    'modus'        => '',
                    'st_kategorie' => '',
                    'layout'       => 'default',
                    'class'        => '',
                    'cache'        => '3600',
                ),
                'admin_options'  => array(
                    'dhps_lxmio_variante' => 'variante_switch',
                    'dhps_lxmio_anzahl'   => 'anzahl',
                ),
                'supports_video' => false,
                'default_params' => array(),
                'admin_page'     => 'dhps_mio_page',
                'admin_title'    => 'MI-Online Recht',
                'category'       => 'recht',
                'shop_url'       => 'https://www.deubner-recht.de/shop/',
                'icon'           => 'dashicons-media-text',
                'admin_fields'   => array(
                    array(
                        'option_key' => 'dhps_lxmio_ota',
                        'field_name' => 'lxmio_ota',
                        'label'      => 'OTA-Nummer',
                        'type'       => 'text',
                    ),
                    array(
                        'option_key' => 'dhps_lxmio_variante',
                        'field_name' => 'lxmio_variante',
                        'label'      => 'Variante',
                        'type'       => 'select',
                        'options'    => array(
                            '1' => 'tagesaktuell',
                            '2' => 'kategorisiert',
                            '0' => 'Definiert ueber Parameter',
                        ),
                    ),
                    array(
                        'option_key' => 'dhps_lxmio_anzahl',
                        'field_name' => 'lxmio_anzahl',
                        'label'      => 'Anzahl der Eintraege je Seite (nur bei tagesaktueller Darstellung wirksam)',
                        'type'       => 'text',
                    ),
                ),
            ),

            /*
             * ---------------------------------------------------------------
             * 3. Merkblaetter (Fact Sheets)
             * ---------------------------------------------------------------
             */
            'mmb' => array(
                'name'           => 'Merkblaetter',
                'endpoint'       => 'einbau/mmo/merkblattpages/php_inhalt.php',
                'auth_type'      => 'ota',
                'auth_option'    => 'dhps_mmo_ota',
                'shortcode_atts' => array(
                    'id_merkblatt' => '',
                    'rubrik'       => '',
                    'layout'       => 'default',
                    'class'        => '',
                    'cache'        => '3600',
                ),
                'admin_options'  => array(),
                'supports_video' => false,
                'default_params' => array(
                    'modus' => 'p',
                ),
                'admin_page'     => 'dhps_mmb_page',
                'admin_title'    => 'Merkblaetter',
                'category'       => 'steuern',
                'shop_url'       => 'https://www.deubner-steuern.de/shop/',
                'icon'           => 'dashicons-media-document',
                'admin_fields'   => array(
                    array(
                        'option_key' => 'dhps_mmo_ota',
                        'field_name' => 'mmo_ota',
                        'label'      => 'OTA-Nummer',
                        'type'       => 'text',
                    ),
                ),
            ),

            /*
             * ---------------------------------------------------------------
             * 4. Infografiken
             * ---------------------------------------------------------------
             */
            'mil' => array(
                'name'           => 'Infografiken',
                'endpoint'       => 'einbau/mil/bin/php_inhalt.php',
                'auth_type'      => 'ota',
                'auth_option'    => 'dhps_mil_ota',
                'shortcode_atts' => array(
                    'id_merkblatt' => '',
                    'rubrik'       => '',
                    'layout'       => 'default',
                    'class'        => '',
                    'cache'        => '3600',
                ),
                'admin_options'  => array(),
                'supports_video' => false,
                'default_params' => array(
                    'modus' => 'p',
                ),
                'admin_page'     => 'dhps_mil_page',
                'admin_title'    => 'Infografiken',
                'category'       => 'steuern',
                'shop_url'       => 'https://www.deubner-steuern.de/shop/',
                'icon'           => 'dashicons-chart-bar',
                'admin_fields'   => array(
                    array(
                        'option_key' => 'dhps_mil_ota',
                        'field_name' => 'mil_ota',
                        'label'      => 'OTA-Nummer',
                        'type'       => 'text',
                    ),
                ),
            ),

            /*
             * ---------------------------------------------------------------
             * 5. TaxPlain (Videos)
             * ---------------------------------------------------------------
             */
            'tp' => array(
                'name'           => 'TaxPlain Videos',
                'endpoint'       => 'einbau/taxplain/videopages/php_inhalt.php',
                'auth_type'      => 'ota',
                'auth_option'    => 'dhps_ota_tp',
                'shortcode_atts' => array(
                    'teasermodus' => '0',
                    'einzelvideo' => '0',
                    'videoliste'  => '',
                    'layout'      => 'default',
                    'class'       => '',
                    'cache'       => '3600',
                ),
                'admin_options'  => array(),
                'supports_video' => true,
                'default_params' => array(
                    'modus' => 'p',
                ),
                'admin_page'     => 'dhps_tp_page',
                'admin_title'    => 'TaxPlain Videos',
                'category'       => 'steuern',
                'shop_url'       => 'https://www.deubner-steuern.de/shop/',
                'icon'           => 'dashicons-video-alt3',
                'admin_fields'   => array(
                    array(
                        'option_key' => 'dhps_ota_tp',
                        'field_name' => 'ota_tp',
                        'label'      => 'OTA-Nummer',
                        'type'       => 'text',
                    ),
                ),
                'extra_sections' => array(
                    array(
                        'title'        => 'TaxPlain Teaser',
                        'shortcodes'   => array( 'tpt' ),
                        'nonce_field'  => 'dhps_tpt_nonce',
                        'submit_name'  => 'tpt_submit',
                        'admin_fields' => array(
                            array(
                                'option_key' => 'dhps_tp_kdnr',
                                'field_name' => 'tp_kdnr',
                                'label'      => 'Kundennummer',
                                'type'       => 'text',
                            ),
                            array(
                                'option_key' => 'dhps_tpt_ues',
                                'field_name' => 'tpt_ues',
                                'label'      => 'Ueberschrift (wird nur im Standard-Modus verwendet)',
                                'type'       => 'text',
                            ),
                            array(
                                'option_key' => 'dhps_tpt_teasertext',
                                'field_name' => 'tpt_teasertext',
                                'label'      => 'Teasertext (wird nur im Standard-Modus verwendet)',
                                'type'       => 'text',
                            ),
                            array(
                                'option_key' => 'dhps_tpt_breite',
                                'field_name' => 'tpt_breite',
                                'label'      => 'Breite',
                                'type'       => 'text',
                            ),
                            array(
                                'option_key' => 'dhps_tpt_modus',
                                'field_name' => 'tpt_modus',
                                'label'      => 'Modus',
                                'type'       => 'text',
                            ),
                        ),
                        'description'  => 'Folgende Modi stehen Ihnen zur Verfuegung: standard (Monitor mit wechselnden Video-Titelbildern inkl. Ueberschrift und Teasertext), p (nur Titelbild), t (nur Titel), pt (Titel und Titelbild)',
                    ),
                ),
            ),

            /*
             * ---------------------------------------------------------------
             * 6. TaxPlain Teaser
             * ---------------------------------------------------------------
             * Alle servicespezifischen Parameter werden im Admin konfiguriert.
             * Der Shortcode [tpt] unterstuetzt die universellen Attribute (layout, class, cache).
             */
            'tpt' => array(
                'name'           => 'TaxPlain Teaser',
                'endpoint'       => 'taxplain/videopages/teaser_php.php',
                'auth_type'      => 'kdnr',
                'auth_option'    => 'dhps_tp_kdnr',
                'shortcode_atts' => array(
                    'layout' => 'default',
                    'class'  => '',
                    'cache'  => '3600',
                ),
                'admin_options'  => array(
                    'dhps_tpt_ues'        => 'ueberschrift',
                    'dhps_tpt_teasertext' => 'teasertext',
                    'dhps_tpt_breite'     => 'breite',
                    'dhps_tpt_modus'      => 'modus',
                ),
                'supports_video' => false,
                'default_params' => array(),
                'admin_page'     => 'dhps_tp_page',
                'admin_title'    => 'TaxPlain Teaser',
                'category'       => 'steuern',
                'shop_url'       => 'https://www.deubner-steuern.de/shop/',
                'icon'           => 'dashicons-format-video',
                'admin_fields'   => array(
                    array(
                        'option_key' => 'dhps_tp_kdnr',
                        'field_name' => 'tp_kdnr',
                        'label'      => 'Kundennummer',
                        'type'       => 'text',
                    ),
                    array(
                        'option_key' => 'dhps_tpt_ues',
                        'field_name' => 'tpt_ues',
                        'label'      => 'Ueberschrift (wird nur im Standard-Modus verwendet)',
                        'type'       => 'text',
                    ),
                    array(
                        'option_key' => 'dhps_tpt_teasertext',
                        'field_name' => 'tpt_teasertext',
                        'label'      => 'Teasertext (wird nur im Standard-Modus verwendet)',
                        'type'       => 'text',
                    ),
                    array(
                        'option_key' => 'dhps_tpt_breite',
                        'field_name' => 'tpt_breite',
                        'label'      => 'Breite',
                        'type'       => 'text',
                    ),
                    array(
                        'option_key'  => 'dhps_tpt_modus',
                        'field_name'  => 'tpt_modus',
                        'label'       => 'Modus',
                        'type'        => 'text',
                        'description' => '<strong>standard</strong> &rarr; Monitor mit wechselnden Video-Titelbildern (einschl. Ueberschrift und Teasertext), <strong>p</strong> &rarr; nur das Titelbild des aktuellen Videos, <strong>t</strong> &rarr; nur der Titel des aktuellen Videos, <strong>pt</strong> &rarr; Titel und Titelbild des aktuellen Videos',
                    ),
                ),
            ),

            /*
             * ---------------------------------------------------------------
             * 7. Tax-Rechner (TaxCalc)
             * ---------------------------------------------------------------
             */
            'tc' => array(
                'name'           => 'Tax-Rechner',
                'endpoint'       => 'webcalc/bin/php_inhalt_v2.php',
                'auth_type'      => 'kdnr',
                'auth_option'    => 'dhps_tc_kdnr',
                'shortcode_atts' => array(
                    'layout' => 'default',
                    'class'  => '',
                    'cache'  => '3600',
                ),
                'admin_options'  => array(),
                'supports_video' => false,
                'default_params' => array(
                    'modus' => 'p',
                ),
                'admin_page'     => 'dhps_tc_page',
                'admin_title'    => 'Tax-Rechner',
                'category'       => 'steuern',
                'shop_url'       => 'https://www.deubner-steuern.de/shop/',
                'icon'           => 'dashicons-calculator',
                'admin_fields'   => array(
                    array(
                        'option_key' => 'dhps_tc_kdnr',
                        'field_name' => 'tc_kdnr',
                        'label'      => 'Kundennummer',
                        'type'       => 'text',
                    ),
                ),
            ),

            /*
             * ---------------------------------------------------------------
             * 8. Meine Aerzteseite (InfoKombi)
             * ---------------------------------------------------------------
             */
            'maes' => array(
                'name'           => 'Meine Aerzteseite',
                'endpoint'       => 'infokombi/bin/infokombi.php',
                'auth_type'      => 'kdnr',
                'auth_option'    => 'dhps_maes_kdnr',
                'shortcode_atts' => array(
                    'section' => '',
                    'layout'  => 'default',
                    'class'   => '',
                    'cache'   => '3600',
                ),
                'admin_options'  => array(),
                'supports_video' => true,
                'default_params' => array(
                    'modus' => 'p',
                ),
                'admin_page'     => 'dhps_maes_page',
                'admin_title'    => 'Meine Aerzteseite',
                'category'       => 'medizin',
                'shop_url'       => 'https://www.deubner-steuern.de/shop/',
                'icon'           => 'dashicons-heart',
                'admin_fields'   => array(
                    array(
                        'option_key' => 'dhps_maes_kdnr',
                        'field_name' => 'maes_kdnr',
                        'label'      => 'Kundennummer',
                        'type'       => 'text',
                    ),
                ),
            ),

            /*
             * ---------------------------------------------------------------
             * 9. Lexplain (Rechts-Videos)
             * ---------------------------------------------------------------
             */
            'lp' => array(
                'name'           => 'Lexplain',
                'endpoint'       => 'lexplain/bin/php_inhalt.php',
                'auth_type'      => 'ota',
                'auth_option'    => 'dhps_lp_ota',
                'shortcode_atts' => array(
                    'videoliste'  => 0,
                    'teasermodus' => 0,
                    'show_teaser' => 1,
                    'filter'      => '',
                    'layout'      => 'default',
                    'class'       => '',
                    'cache'       => '3600',
                ),
                'admin_options'  => array(),
                'supports_video' => false,
                'default_params' => array(
                    'modus' => 'p',
                ),
                'admin_page'      => 'dhps_lp_spage',
                'admin_title'     => 'Lexplain',
                'category'        => 'recht',
                'shop_url'        => 'https://www.deubner-recht.de/shop/',
                'icon'            => 'dashicons-video-alt2',
                'shortcode_hint'  => 'Optional koennen Sie noch den Parameter <code>videoliste</code> eingeben, wenn nur bestimmte Videos angezeigt werden sollen.',
                'admin_fields'    => array(
                    array(
                        'option_key' => 'dhps_lp_ota',
                        'field_name' => 'lp_ota',
                        'label'      => 'OTA-Nummer',
                        'type'       => 'text',
                    ),
                ),
            ),

        );

        return self::$services;
    }

    /**
     * Gibt die Definition eines einzelnen Service zurueck.
     *
     * @since 0.4.0
     *
     * @param string $shortcode Der Shortcode-Name (z.B. 'mio', 'tp').
     *
     * @return array|null Service-Definition als Array oder null wenn nicht gefunden.
     */
    public static function get_service( string $shortcode ): ?array {
        $services = self::get_services();

        return $services[ $shortcode ] ?? null;
    }

    /**
     * Gibt alle registrierten Shortcode-Namen zurueck.
     *
     * @since 0.4.0
     *
     * @return array Numerisches Array der Shortcode-Namen.
     */
    public static function get_shortcode_names(): array {
        return array_keys( self::get_services() );
    }
}
