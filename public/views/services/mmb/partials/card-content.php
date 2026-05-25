<?php
/**
 * Partial: Fact-Sheet-Card-Grid einer einzelnen MMB-/MIL-Kategorie.
 *
 * Wird sowohl vom AJAX-Endpoint (DHPS_MMB_AJAX_Handler bei layout=card)
 * als auch vom Haupt-Template (mmb/card.php) via include genutzt, um
 * das `<div class="dhps-mmb-card-grid">` einer Rubrik konsistent zu
 * rendern.
 *
 * Erwartete Variablen:
 * - $category    (array)  Strukturiertes Kategorie-Array aus DHPS_MMB_Parser:
 *                         id, name, icon_slug, fact_sheets[].
 * - $service_tag (string) 'mmb' oder 'mil' (steuert Label und PDF-URL-Bildung).
 *
 * SICHERHEIT: Alle Texte werden escaped, PDF-Links laufen ueber den
 * serverseitigen AJAX-Proxy (kdnr bleibt serverseitig).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/MMB/Partials
 * @since      0.15.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $category ) || ! is_array( $category ) ) {
	return;
}

$service_tag    = isset( $service_tag ) ? (string) $service_tag : 'mmb';
$is_mil         = ( 'mil' === $service_tag );
$download_label = $is_mil ? 'Infografik herunterladen' : 'PDF herunterladen';

$fact_sheets = isset( $category['fact_sheets'] ) && is_array( $category['fact_sheets'] )
	? $category['fact_sheets']
	: array();

if ( empty( $fact_sheets ) ) {
	return;
}
?>
<div class="dhps-mmb-card-grid">
	<?php
	foreach ( $fact_sheets as $sheet ) :
		if ( ! is_array( $sheet ) || empty( $sheet['title'] ) ) {
			continue;
		}

		$sheet_id   = isset( $sheet['id'] ) ? (string) $sheet['id'] : '';
		$sheet_id_a = esc_attr( $sheet_id );
		$pdf_params = isset( $sheet['pdf_params'] ) && is_array( $sheet['pdf_params'] )
			? $sheet['pdf_params']
			: array();
		$description = isset( $sheet['description'] ) ? (string) $sheet['description'] : '';

		// PDF-URL bauen (analog default-Partial).
		if ( $is_mil && ! empty( $pdf_params['merkblatt'] ) ) {
			$pdf_href = 'https://www.deubner-online.de/einbau/mil/content/merkblaetter/'
				. rawurlencode( (string) $pdf_params['merkblatt'] ) . '.pdf';
		} else {
			$pdf_href = admin_url( 'admin-ajax.php' ) . '?' . http_build_query(
				array_merge(
					array(
						'action'  => 'dhps_mmb_pdf',
						'nonce'   => wp_create_nonce( 'dhps_mmb_nonce' ),
						'service' => $service_tag,
					),
					$pdf_params
				)
			);
		}
		?>
	<div class="dhps-mmb-card-item" data-dhps-mmb-item data-sheet-id="<?php echo $sheet_id_a; ?>">
		<div class="dhps-mmb-card-item__icon" aria-hidden="true">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c0392b" stroke-width="2">
				<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
				<polyline points="14 2 14 8 20 8"/>
				<line x1="16" y1="13" x2="8" y2="13"/>
				<line x1="16" y1="17" x2="8" y2="17"/>
				<polyline points="10 9 9 9 8 9"/>
			</svg>
		</div>
		<h4 class="dhps-mmb-card-item__title"><?php echo esc_html( $sheet['title'] ); ?></h4>
		<?php if ( '' !== $description ) : ?>
		<p class="dhps-mmb-card-item__desc">
			<?php echo esc_html( mb_strimwidth( $description, 0, 120, '...' ) ); ?>
		</p>
		<?php endif; ?>
		<a class="dhps-mmb-card-item__download"
		   href="<?php echo esc_url( $pdf_href ); ?>"
		   target="_blank"
		   rel="noopener"
		   data-dhps-mmb-pdf="<?php echo $sheet_id_a; ?>">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
				<polyline points="7 10 12 15 17 10"/>
				<line x1="12" y1="15" x2="12" y2="3"/>
			</svg>
			<?php echo esc_html( $download_label ); ?>
		</a>
	</div>
	<?php endforeach; ?>
</div>
