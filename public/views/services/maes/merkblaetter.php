<?php
/**
 * MAES Merkblaetter Template - Nutzt MMB-Infrastruktur.
 *
 * Verfuegbare Variablen:
 *   $merkblaetter - Array der Merkblatt-Daten aus DHPS_MAES_Parser.
 *   $custom_class - Optionale CSS-Klasse.
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$download_label = 'Merkblatt herunterladen';

wp_enqueue_script( 'dhps-mmb-js' );
?>
<div class="dhps-service dhps-service--mmb dhps-service--maes-merkblaetter<?php echo esc_attr( $custom_class ); ?>">

	<section class="dhps-mmb-categories"
			 data-dhps-mmb-categories
			 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_mmb_nonce' ) ); ?>"
			 data-service-tag="maes">

		<div class="dhps-mmb-category" data-dhps-mmb-category>
			<h3 class="dhps-mmb-category__header">
				<button type="button"
						class="dhps-mmb-category__trigger"
						aria-expanded="true"
						aria-controls="dhps-maes-mb-list"
						data-dhps-mmb-category-toggle>
					<span class="dhps-mmb-category__name">
						<?php echo esc_html( 'Merkblaetter und Checklisten' ); ?>
					</span>
					<span class="dhps-mmb-category__count">(<?php echo esc_html( count( $merkblaetter ) ); ?>)</span>
					<svg class="dhps-mmb-category__chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<polyline points="6 9 12 15 18 9"/>
					</svg>
				</button>
			</h3>

			<div class="dhps-mmb-category__content"
				 id="dhps-maes-mb-list"
				 aria-hidden="false">

				<ul class="dhps-mmb-list">
					<?php foreach ( $merkblaetter as $index => $sheet ) :
						$sheet_id = sanitize_title( $sheet['pdf_params']['merkblatt'] ?? 'mb-' . $index );
					?>
					<li class="dhps-mmb-item" data-dhps-mmb-item>
						<button type="button"
								class="dhps-mmb-item__title"
								aria-expanded="false"
								aria-controls="dhps-maes-detail-<?php echo esc_attr( $sheet_id ); ?>"
								data-dhps-mmb-item-toggle>
							<?php echo esc_html( $sheet['title'] ); ?>
						</button>

						<div class="dhps-mmb-item__detail"
							 id="dhps-maes-detail-<?php echo esc_attr( $sheet_id ); ?>"
							 aria-hidden="true">

							<?php if ( ! empty( $sheet['description'] ) ) : ?>
							<p class="dhps-mmb-item__description">
								<?php echo esc_html( $sheet['description'] ); ?>
							</p>
							<?php endif; ?>

							<div class="dhps-mmb-item__actions">
								<?php
								$pdf_href = admin_url( 'admin-ajax.php' ) . '?' . http_build_query( array_merge(
									array(
										'action'  => 'dhps_mmb_pdf',
										'nonce'   => wp_create_nonce( 'dhps_mmb_nonce' ),
										'service' => 'maes',
									),
									$sheet['pdf_params']
								) );
								?>
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
					</li>
					<?php endforeach; ?>
				</ul>

			</div>
		</div>

	</section>

</div>
