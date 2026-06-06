<?php
/**
 * Adapter fuer den TC (Tax-Rechner) Service.
 *
 * TC ist konzeptuell anders als die anderen Services: der Parser liefert
 * keinen strukturierten Output, sondern ein Wrapper-Array mit Raw-HTML
 * + Empty-State-Flag. Der Adapter spiegelt dieses Pattern in das
 * einheitliche Datenmodell, ohne die `echo $tc_html` Trust-Decision
 * (v0.13.0/v0.14.4) zu brechen.
 *
 * Mapping-Strategie (Option C aus Discovery v0.17.4):
 * - Empty-State: leere Collection (0 Items), Collection-Meta haelt Diagnose-HTML
 * - Sonst: 1 Item type='generic' mit body='', HTML+Status leben im Collection-Meta
 *
 * **WICHTIG**: Der Adapter macht NULL HTML-Transformation. Inline-JS
 * (test_einblenden/test_ausblenden) bleibt 1:1 erhalten. Templates lesen
 * `$collection->get_meta('html')` und rendern via `echo $tc_html`
 * unveraendert.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class DHPS_TC_Adapter
 *
 * Wrapper-Adapter fuer TC. Reicht Raw-HTML 1:1 durch + setzt Status-Flags.
 *
 * @since 0.17.4
 */
final class DHPS_TC_Adapter implements DHPS_Content_Adapter_Interface {

    /**
     * Wandelt TC-Parser-Output in eine DHPS_Content_Collection.
     *
     * Schema-Vertrag (Discovery 30-TC-CLEANUP-PLAN-v0174 Sektion 6):
     *
     * Items:
     * - 0 Items wenn is_empty=true
     * - 1 Item type='generic', body='', title='TC Rechner', id='tc-calculators'
     *   wenn is_empty=false
     *
     * Collection-Meta:
     * - html      (string) Raw-HTML aus Parser-Output, NIE transformiert
     * - is_empty  (bool)   true = Empty-State, false = Content vorhanden
     *
     * @since 0.17.4
     *
     * @param array  $parser_output Output von DHPS_TC_Parser::parse().
     * @param string $service       Service-Tag (immer 'tc' - kein Service-Variant).
     *
     * @return DHPS_Content_Collection
     */
    public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {
        $raw_html = isset( $parser_output['html'] ) ? (string) $parser_output['html'] : '';
        $is_empty = ! empty( $parser_output['is_empty'] );

        // Defensive Hardening: leerer/whitespace-only HTML zaehlt als Empty
        // (Parser-Bug-Resilienz, siehe Discovery R3 / Sektion 10.1).
        if ( '' === trim( $raw_html ) ) {
            $is_empty = true;
        }

        $items = array();

        if ( ! $is_empty ) {
            $items[] = new DHPS_Content_Item(
                'tc-calculators',     // id (fix, TC ist Singleton)
                $service,             // service (immer 'tc')
                'TC Rechner',         // title (Pflicht non-empty, wird NIE gerendert)
                'generic',            // type (in ALLOWED_TYPES seit v0.17.0)
                '',                   // body (leer - HTML lebt im Collection-Meta)
                null,                 // excerpt
                null,                 // image
                null,                 // media
                null,                 // link
                null,                 // date
                array(),              // tags
                null,                 // category
                array()               // item-meta (leer - Status nur in Collection-Meta)
            );
        }

        $meta = array(
            'html'     => $raw_html,
            'is_empty' => $is_empty,
        );

        return new DHPS_Content_Collection( $service, $items, $meta );
    }
}
