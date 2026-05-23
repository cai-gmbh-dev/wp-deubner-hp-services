<?php
/**
 * MAES Merkblaetter Card Template - v0.14.1 Component-System.
 *
 * Card-Variante: Grid aus Document-Cards (ContentCard type='document')
 * mit Service-Branding 'maes'. Beschreibung wird via CSS line-clamp
 * gekuerzt (kein PHP-Truncate).
 *
 * Verfuegbare Variablen:
 *   $merkblaetter     - Array der Merkblatt-Daten aus DHPS_MAES_Parser.
 *   $custom_class     - Optionale CSS-Klasse.
 *   $show_description - Beschreibung anzeigen (default: true).
 *   $columns          - Spaltenzahl 1-4 (default: 2).
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 * @version 0.14.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$custom_class     = isset( $custom_class ) && is_string( $custom_class ) ? $custom_class : '';
$merkblaetter     = isset( $merkblaetter ) && is_array( $merkblaetter ) ? $merkblaetter : array();
$show_description = isset( $show_description ) ? (bool) $show_description : true;
$columns          = isset( $columns ) ? absint( $columns ) : 2;
if ( $columns < 1 || $columns > 4 ) {
	$columns = 2;
}

// Items aus Parser-Output in ContentCard-Props transformieren.
$mb_items = array();
foreach ( $merkblaetter as $index => $sheet ) {
	if ( ! is_array( $sheet ) || empty( $sheet['title'] ) ) {
		continue;
	}

	$pdf_href = admin_url( 'admin-ajax.php' ) . '?' . http_build_query(
		array_merge(
			array(
				'action'  => 'dhps_mmb_pdf',
				'nonce'   => wp_create_nonce( 'dhps_mmb_nonce' ),
				'service' => 'maes',
			),
			isset( $sheet['pdf_params'] ) && is_array( $sheet['pdf_params'] ) ? $sheet['pdf_params'] : array()
		)
	);

	$mb_items[] = array(
		'type'    => 'document',
		'service' => 'maes',
		'title'   => (string) $sheet['title'],
		'teaser'  => $show_description && ! empty( $sheet['description'] ) ? (string) $sheet['description'] : '',
		'meta'    => array(
			array( 'icon' => 'file', 'text' => 'PDF' ),
		),
		'actions' => array(
			array(
				'label'   => 'Herunterladen',
				'icon'    => 'download',
				'href'    => $pdf_href,
				'target'  => '_blank',
				'primary' => true,
			),
		),
	);
}

$wrapper_class = trim( 'dhps-service dhps-service--maes dhps-service--maes-merkblaetter dhps-layout--card ' . $custom_class );
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>">
	<?php
	echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
		'content-list',
		array(
			'id'          => 'maes-merkblaetter-card-' . wp_unique_id(),
			'layout'      => 'grid',
			'columns'     => $columns,
			'items'       => $mb_items,
			'item_type'   => 'document',
			'class'       => 'dhps-content-list--maes-merkblaetter dhps-content-list--card',
			'empty_state' => array(
				'icon'  => 'document',
				'title' => 'Keine Merkblaetter verfuegbar',
				'hint'  => 'Aktuell sind keine Merkblaetter in dieser Kategorie hinterlegt.',
			),
		)
	);
	?>
</div>
