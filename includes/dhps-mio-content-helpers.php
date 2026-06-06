<?php
/**
 * Helper-Funktionen fuer MIO/LXMIO-Templates rund um das Datenmodell.
 *
 * Geteilter Rebuild-Pfad fuer Pseudo-Rebuild-Pattern in 3 MIO-Templates
 * (default, card, compact). Helper verhindert Code-Duplikation im BC-Pfad
 * und ist die EINZIGE Stelle, an der die Item-zur-Legacy-Monatsspalten-
 * Shape-Konvertierung lebt - das macht Schema-Drift-Tests einfach.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'dhps_mio_item_to_legacy_month' ) ) {
    /**
     * Wandelt ein DHPS_Content_Item (type=tax_date) in die Legacy-Monats-
     * Array-Shape, die MIO-Templates seit v0.9.0 erwarten.
     *
     * Legacy-Shape (siehe DHPS_MIO_Parser::parse_tax_dates):
     *   array(
     *     'title'    => string,      // 'Juli 2026'
     *     'entries'  => array(       // Liste von Datums-Eintraegen
     *       array(
     *         'date'  => string,     // '10.07.'
     *         'taxes' => array<int, string>,  // ['Umsatzsteuer', ...]
     *       ),
     *       ...
     *     ),
     *     'footnote' => string,      // 'Schonfrist ...'
     *   )
     *
     * OHNE Side-Effects + idempotent. Items vom Typ != 'tax_date' werden
     * auf ein leeres Array zurueckgegeben (defensiv, kein Throw).
     *
     * @since 0.17.3
     *
     * @param DHPS_Content_Item $item Item aus DHPS_Content_Collection.
     *
     * @return array<string, mixed> Legacy-Monats-Shape oder leeres Array.
     */
    function dhps_mio_item_to_legacy_month( DHPS_Content_Item $item ): array {
        if ( 'tax_date' !== $item->type ) {
            return array();
        }

        $meta = is_array( $item->meta ) ? $item->meta : array();

        return array(
            'title'    => (string) $item->title,
            'entries'  => isset( $meta['entries'] ) && is_array( $meta['entries'] )
                ? $meta['entries']
                : array(),
            'footnote' => isset( $meta['footnote'] ) ? (string) $meta['footnote'] : '',
        );
    }
}
