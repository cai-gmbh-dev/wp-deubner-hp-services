<?php
/**
 * Plugin Name: DHPS Stage-Marker
 * Description: Sichtbares Erfolgs-Signal "ich bin auf Stage, nicht Live". Wird nur im Stage-Stack via mu-plugins-Mount geladen. Nicht im Plugin-Verzeichnis.
 * Version: 0.16.3
 * Author: CAI GmbH
 *
 * Aktivierungs-Bedingung: `DHPS_ENV_LABEL === 'STAGE'`. Die Konstante wird
 * im docker-compose.staging.yml via WORDPRESS_CONFIG_EXTRA gesetzt.
 *
 * Vier sichtbare Effekte:
 *   1. Roter Admin-Bar-Hintergrund (#b32d2e)
 *   2. "[ STAGE ]"-Praefix in 4 Title-Hooks (admin_bar site-name, admin_title,
 *      wp_title fallback, document_title_parts)
 *   3. Permanent angepinnter hellroter Banner unter dem Admin-Bar
 *      via body.wp-admin::before
 *   4. Aktivierungs-Gate: DHPS_ENV_LABEL === 'STAGE' (aus docker-compose.staging.yml)
 *
 * @package DHPS_Stage_Marker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'DHPS_ENV_LABEL' ) || 'STAGE' !== DHPS_ENV_LABEL ) {
    return;
}

/**
 * Roter Admin-Bar + Browser-Tab-Praefix.
 */
add_action( 'admin_head', static function (): void {
    ?>
    <style id="dhps-stage-marker">
        /* Roter Admin-Bar fuer Stage-Sichtbarkeit */
        #wpadminbar {
            background: #b32d2e !important;
        }
        #wpadminbar .ab-empty-item,
        #wpadminbar .ab-item,
        #wpadminbar a.ab-item,
        #wpadminbar > #wp-toolbar span.ab-label,
        #wpadminbar > #wp-toolbar span.noticon {
            color: #fff !important;
        }
        /* Stage-Banner unter dem Admin-Bar */
        body.wp-admin::before {
            content: '[ STAGE ] - Diese WordPress-Instanz ist nicht Live. Aenderungen wirken nur lokal.';
            display: block;
            background: #ffe5e5;
            border-bottom: 1px solid #b32d2e;
            color: #6c1d1e;
            font: bold 12px/2.4 -apple-system, BlinkMacSystemFont, sans-serif;
            text-align: center;
            position: sticky;
            top: 32px;
            z-index: 99998;
        }
    </style>
    <?php
} );

/**
 * Praefix im Admin-Bar-Title (oben links neben WP-Logo).
 */
add_action( 'admin_bar_menu', static function ( $wp_admin_bar ): void {
    if ( ! $wp_admin_bar instanceof WP_Admin_Bar ) {
        return;
    }
    $site_name = $wp_admin_bar->get_node( 'site-name' );
    if ( null !== $site_name && isset( $site_name->title ) ) {
        $wp_admin_bar->add_node( array(
            'id'    => 'site-name',
            'title' => '[ STAGE ] ' . $site_name->title,
        ) );
    }
}, 35 );

/**
 * Praefix im Browser-Tab-Title (Admin-Bereich).
 */
add_filter( 'admin_title', static function ( string $admin_title ): string {
    return '[ STAGE ] ' . $admin_title;
}, 10, 1 );

/**
 * Praefix im Frontend wp_title (Tab-Title).
 */
add_filter( 'wp_title', static function ( string $title ): string {
    return '[ STAGE ] ' . $title;
}, 10, 1 );

add_filter( 'document_title_parts', static function ( array $parts ): array {
    if ( isset( $parts['title'] ) ) {
        $parts['title'] = '[ STAGE ] ' . $parts['title'];
    }
    return $parts;
}, 10, 1 );
