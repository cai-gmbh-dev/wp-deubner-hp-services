<?php
/**
 * MAES Merkblaetter Compact Template - Nutzt MMB-Infrastruktur.
 *
 * Kompakte Listen-Variante: Titel + Download-Icon pro Zeile.
 *
 * Verfuegbare Variablen:
 *   $merkblaetter     - Array der Merkblatt-Daten aus DHPS_MAES_Parser.
 *   $custom_class     - Optionale CSS-Klasse.
 *   $show_description - Optionale Untertitel-Zeile (default: false).
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_description = $show_description ?? false;
?>
<div class="dhps-service dhps-service--mmb dhps-service--maes-merkblaetter dhps-mmb-compact<?php echo esc_attr( $custom_class ); ?>">

	<section class="dhps-mmb-categories"
			 data-dhps-mmb-categories
			 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_mmb_nonce' ) ); ?>"
			 data-service-tag="maes">

		<ul class="dhps-mmb-compact__list">
			<?php foreach ( $merkblaetter as $index => $sheet ) :
				$pdf_href = admin_url( 'admin-ajax.php' ) . '?' . http_build_query( array_merge(
					array(
						'action'  => 'dhps_mmb_pdf',
						'nonce'   => wp_create_nonce( 'dhps_mmb_nonce' ),
						'service' => 'maes',
					),
					$sheet['pdf_params']
				) );
			?>
			<li class="dhps-mmb-compact__item" data-dhps-mmb-item>
				<div class="dhps-mmb-compact__content">
					<span class="dhps-mmb-compact__title"><?php echo esc_html( $sheet['title'] ); ?></span>
					<?php if ( $show_description && ! empty( $sheet['description'] ) ) : ?>
					<span class="dhps-mmb-compact__subtitle"><?php echo esc_html( $sheet['description'] ); ?></span>
					<?php endif; ?>
				</div>
				<a class="dhps-mmb-item__download dhps-mmb-compact__download"
				   href="<?php echo esc_url( $pdf_href ); ?>"
				   target="_blank" rel="noopener"
				   aria-label="<?php echo esc_attr( $sheet['title'] . ' herunterladen' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
						<polyline points="7 10 12 15 17 10"/>
						<line x1="12" y1="15" x2="12" y2="3"/>
					</svg>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>

	</section>

</div>
