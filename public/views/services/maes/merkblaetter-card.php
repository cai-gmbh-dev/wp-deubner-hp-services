<?php
/**
 * MAES Merkblaetter Card Template - Nutzt MMB-Infrastruktur.
 *
 * Card-Variante: Grid aus Merkblatt-Karten mit Titel, Beschreibung und Download.
 *
 * Verfuegbare Variablen:
 *   $merkblaetter     - Array der Merkblatt-Daten aus DHPS_MAES_Parser.
 *   $custom_class     - Optionale CSS-Klasse.
 *   $show_description - Beschreibung anzeigen (default: true).
 *   $accordion_open   - Nicht verwendet in Card-Layout, nur Kompatibilitaet.
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_description = $show_description ?? true;
$accordion_open   = $accordion_open ?? false;
$columns          = isset( $columns ) ? absint( $columns ) : 2;
$download_label   = 'Herunterladen';

wp_enqueue_script( 'dhps-mmb-js' );
if ( $columns < 1 || $columns > 4 ) { $columns = 2; }
?>
<div class="dhps-card">
	<div class="dhps-service dhps-service--mmb dhps-service--maes-merkblaetter<?php echo esc_attr( $custom_class ); ?>">

		<section class="dhps-mmb-categories"
				 data-dhps-mmb-categories
				 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
				 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_mmb_nonce' ) ); ?>"
				 data-service-tag="maes">

			<div class="dhps-mmb-card-grid dhps-mmb-card-grid--<?php echo esc_attr( $columns ); ?>col">
				<?php foreach ( $merkblaetter as $index => $sheet ) :
					$sheet_id = sanitize_title( $sheet['pdf_params']['merkblatt'] ?? 'mb-' . $index );
					$pdf_href = admin_url( 'admin-ajax.php' ) . '?' . http_build_query( array_merge(
						array(
							'action'  => 'dhps_mmb_pdf',
							'nonce'   => wp_create_nonce( 'dhps_mmb_nonce' ),
							'service' => 'maes',
						),
						$sheet['pdf_params']
					) );
				?>
				<div class="dhps-mmb-card-grid__item" data-dhps-mmb-item>
					<h4 class="dhps-mmb-card-grid__title"><?php echo esc_html( $sheet['title'] ); ?></h4>

					<?php if ( $show_description && ! empty( $sheet['description'] ) ) : ?>
					<p class="dhps-mmb-card-grid__desc">
						<?php echo esc_html( mb_strimwidth( $sheet['description'], 0, 140, '...' ) ); ?>
					</p>
					<?php endif; ?>

					<div class="dhps-mmb-card-grid__actions">
						<a class="dhps-mmb-item__download"
						   href="<?php echo esc_url( $pdf_href ); ?>"
						   target="_blank" rel="noopener">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
								<polyline points="7 10 12 15 17 10"/>
								<line x1="12" y1="15" x2="12" y2="3"/>
							</svg>
							<?php echo esc_html( $download_label ); ?>
						</a>
					</div>
				</div>
				<?php endforeach; ?>
			</div>

		</section>

	</div>
</div>
