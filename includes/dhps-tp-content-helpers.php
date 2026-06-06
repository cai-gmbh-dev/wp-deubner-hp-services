<?php
/**
 * Helper-Funktionen fuer TP/TPT/LP-Templates rund um das Datenmodell.
 *
 * Geteilter Rebuild-Pfad fuer Pseudo-Rebuild-Pattern in 2 TP-Templates +
 * 3 TPT-Templates (insgesamt 5 Stellen). Helper verhindert Code-Duplikation
 * im BC-Pfad und ist die EINZIGE Stelle, an der die Item-zur-Legacy-Video-
 * Shape-Konvertierung lebt - das macht Schema-Drift-Tests einfach.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'dhps_tp_item_to_legacy_video' ) ) {
    /**
     * Wandelt ein DHPS_Content_Item (type=video) in die Legacy-Video-Array-
     * Shape, die TP- und TPT-Templates seit v0.9.0 erwarten.
     *
     * Legacy-Shape (siehe DHPS_TP_Parser::parse_video_block):
     *   array(
     *     'video_id'   => string,
     *     'titel'      => string,
     *     'teaser'     => string,
     *     'datum'      => string ('MM/YY'),
     *     'video_slug' => string,
     *     'poster_url' => string,
     *     'v_modus'    => string,
     *     'service'    => string ('taxplain'|'lexplain'),
     *   )
     *
     * Diese Funktion ist OHNE Side-Effects + idempotent. Items vom Typ != 'video'
     * werden auf eine leere Array zurueckgegeben (defensiv, kein Throw).
     *
     * @since 0.17.2
     *
     * @param DHPS_Content_Item $item Item aus DHPS_Content_Collection.
     *
     * @return array<string, string> Legacy-Video-Shape oder leeres Array.
     */
    function dhps_tp_item_to_legacy_video( DHPS_Content_Item $item ): array {
        if ( 'video' !== $item->type ) {
            return array();
        }

        $meta  = $item->meta;
        $media = is_array( $item->media ) ? $item->media : array();
        $image = is_array( $item->image ) ? $item->image : array();

        $media_params = is_array( $media['params'] ?? null ) ? $media['params'] : array();

        return array(
            'video_id'   => isset( $meta['video_id'] ) ? (string) $meta['video_id'] : '',
            'titel'      => (string) $item->title,
            'teaser'     => null !== $item->excerpt ? (string) $item->excerpt : '',
            'datum'      => isset( $meta['datum'] ) ? (string) $meta['datum'] : '',
            'video_slug' => isset( $media['slug'] ) ? (string) $media['slug'] : '',
            'poster_url' => isset( $image['url'] ) ? (string) $image['url'] : '',
            'v_modus'    => isset( $media_params['v_modus'] ) ? (string) $media_params['v_modus']
                            : ( isset( $meta['v_modus'] ) ? (string) $meta['v_modus'] : '' ),
            'service'    => isset( $media_params['mandantenvideo_service'] ) ? (string) $media_params['mandantenvideo_service']
                            : ( isset( $meta['mandantenvideo_service'] ) ? (string) $meta['mandantenvideo_service'] : '' ),
        );
    }
}
