<?php
/**
 * Partial: Compact-Liste einer einzelnen MMB-/MIL-Kategorie.
 *
 * Wird sowohl vom AJAX-Endpoint (DHPS_MMB_AJAX_Handler bei layout=compact)
 * als auch vom Haupt-Template (mmb/compact.php) via include genutzt, um
 * die `<ul class="dhps-mmb-list dhps-mmb-list--compact">` einer Rubrik
 * konsistent zu rendern.
 *
 * Erwartete Variablen:
 * - $category    (array)  Strukturiertes Kategorie-Array aus DHPS_MMB_Parser.
 * - $service_tag (string) 'mmb' oder 'mil'.
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
<ul class="dhps-mmb-list dhps-mmb-list--compact">
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
	<li class="dhps-mmb-item dhps-mmb-item--compact" data-dhps-mmb-item data-sheet-id="<?php echo $sheet_id_a; ?>">
		<div class="dhps-mmb-item__row">
			<span class="dhps-mmb-item__title dhps-mmb-item__title--compact">
				<?php echo esc_html( $sheet['title'] ); ?>
			</span>
			<a class="dhps-mmb-item__pdf-btn"
			   href="<?php echo esc_url( $pdf_href ); ?>"
			   target="_blank"
			   rel="noopener"
			   title="<?php echo esc_attr( $download_label ); ?>"
			   data-dhps-mmb-pdf="<?php echo $sheet_id_a; ?>">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
					<polyline points="7 10 12 15 17 10"/>
					<line x1="12" y1="15" x2="12" y2="3"/>
				</svg>
			</a>
		</div>
		<?php if ( '' !== $description ) : ?>
		<p class="dhps-mmb-item__desc--compact">
			<?php echo esc_html( $description ); ?>
		</p>
		<?php endif; ?>
	</li>
	<?php endforeach; ?>
</ul>
