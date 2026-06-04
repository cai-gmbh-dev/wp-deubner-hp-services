<?php
/**
 * MAES Merkblaetter Template - v0.14.1 Component-System.
 *
 * Default-Layout. Nutzt ContentList + ContentCard(type='document') mit
 * Service-Branding 'maes' (Medizin-Teal). Das frueher vorhandene Outer-
 * Akkordeon (Kategorie "Merkblaetter und Checklisten") entfaellt - MAES
 * hat nur EINE Kategorie, der Wrapper war redundant (Discovery R-2).
 * Beschreibung wird via CSS line-clamp gekuerzt, nicht via mb_strimwidth.
 *
 * Verfuegbare Variablen:
 *   $merkblaetter - Array der Merkblatt-Daten aus DHPS_MAES_Parser (Legacy).
 *   $collection   - DHPS_Content_Collection|null (seit v0.17.0, optional).
 *   $custom_class - Optionale CSS-Klasse.
 *
 * v0.17.0: Bei vorhandener Collection (MAES-Adapter registriert) werden
 * die Document-Items per filter() ausgelesen. Andernfalls greift der
 * Legacy-Pfad - BC-Garantie analog zu videos.php.
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 * @version 0.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$custom_class = isset( $custom_class ) && is_string( $custom_class ) ? $custom_class : '';
$merkblaetter = isset( $merkblaetter ) && is_array( $merkblaetter ) ? $merkblaetter : array();

// --- Daten-Pfad waehlen: Collection wenn verfuegbar, sonst Legacy. ---
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

$mb_items = array();

if ( $has_collection ) {
	// v0.17.0-Pfad: Collection -> Document-Items -> ContentCard-Props.
	$doc_collection = $collection->filter(
		static function ( $item ) {
			return $item instanceof DHPS_Content_Item && 'document' === $item->type;
		}
	);

	foreach ( $doc_collection as $item ) {
		/** @var DHPS_Content_Item $item */
		$pdf_params = isset( $item->meta['pdf_params'] ) && is_array( $item->meta['pdf_params'] )
			? $item->meta['pdf_params']
			: array();

		$pdf_href = admin_url( 'admin-ajax.php' ) . '?' . http_build_query(
			array_merge(
				array(
					'action'  => 'dhps_mmb_pdf',
					'nonce'   => wp_create_nonce( 'dhps_mmb_nonce' ),
					'service' => 'maes',
				),
				$pdf_params
			)
		);

		$mb_items[] = array(
			'type'    => 'document',
			'service' => 'maes',
			'title'   => $item->title,
			'teaser'  => null !== $item->excerpt ? $item->excerpt : '',
			'meta'    => array(
				array( 'icon' => 'file', 'text' => 'PDF' ),
			),
			'actions' => array(
				array(
					'label'   => 'Merkblatt herunterladen',
					'icon'    => 'download',
					'href'    => $pdf_href,
					'target'  => '_blank',
					'primary' => true,
				),
			),
		);
	}
} else {
	// Legacy-Pfad (vor v0.17.0): Parser-Array manuell durchlaufen.
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
			'teaser'  => isset( $sheet['description'] ) ? (string) $sheet['description'] : '',
			'meta'    => array(
				array( 'icon' => 'file', 'text' => 'PDF' ),
			),
			'actions' => array(
				array(
					'label'   => 'Merkblatt herunterladen',
					'icon'    => 'download',
					'href'    => $pdf_href,
					'target'  => '_blank',
					'primary' => true,
				),
			),
		);
	}
}

$wrapper_class = trim( 'dhps-service dhps-service--maes dhps-service--maes-merkblaetter ' . $custom_class );
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>">
	<?php
	echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
		'content-list',
		array(
			'id'          => 'maes-merkblaetter-' . wp_unique_id(),
			'layout'      => 'list',
			'columns'     => 1,
			'items'       => $mb_items,
			'item_type'   => 'document',
			'class'       => 'dhps-content-list--maes-merkblaetter',
			'empty_state' => array(
				'icon'  => 'document',
				'title' => 'Keine Merkblaetter verfuegbar',
				'hint'  => 'Aktuell sind keine Merkblaetter in dieser Kategorie hinterlegt.',
			),
		)
	);
	?>
</div>
