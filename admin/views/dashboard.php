<?php
/**
 * Template: Dashboard-Seite (Deubner Redesign).
 *
 * Zeigt die Uebersichtsseite des Plugins im WordPress-Admin
 * im Deubner-Verlag-Branding: Weisser Hintergrund, scharfe Kanten,
 * Produktbilder, Service-Cards mit Status und Aktions-Buttons.
 *
 * Erwartet folgende Variablen (gesetzt durch den Controller):
 * - array $statuses      Ergebnis von DHPS_Demo_Manager::get_all_statuses()
 * - int   $demo_duration Demo-Dauer in Tagen
 *
 * @package    Deubner Homepage-Service
 * @subpackage Admin/Views
 * @since      0.6.0
 * @since      0.8.0 Kategorie-Gruppierung und BEM-basierte Service-Cards.
 * @since      0.9.6 Deubner-Branding, Produktbilder, kein Shortcode im Dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$services = DHPS_Service_Registry::get_services();

$categories = array(
	'steuern' => 'Steuerrecht & Steuern',
	'recht'   => 'Recht',
	'medizin' => 'Medizin',
);

/**
 * Produktbilder (lokal, von deubner-steuern.de / deubner-recht.de).
 * Aktualisierung erfolgt mit Plugin-Releases.
 */
$img_base = DEUBNER_HP_SERVICES_URL . 'assets/images/products/';

$product_images = array(
	'mio'   => $img_base . 'mio.png',
	'lxmio' => $img_base . 'lxmio.png',
	'mmb'   => $img_base . 'mmb.png',
	'mil'   => $img_base . 'mil.png',
	'tp'    => $img_base . 'tp.jpg',
	'tpt'   => $img_base . 'tp.jpg',
	'tc'    => $img_base . 'tc.png',
	'maes'  => $img_base . 'maes.png',
	'lp'    => $img_base . 'lp.jpg',
);
?>
<div id="deubnerhpservice" class="dhps wrap dhps-dashboard-wrap">
	<h1 class="dhps-notice-hook-element"></h1>
	<div class="dhps-dashboard">

		<?php include __DIR__ . '/partials/header.php'; ?>

		<div class="dhps-content-area">

			<!-- Nonce fuer AJAX Demo-Toggle -->
			<div id="dhps-demo-nonce-container" class="hidden">
				<?php wp_nonce_field( 'dhps_demo_toggle', 'dhps_demo_nonce' ); ?>
			</div>

			<!-- Service-Cards gruppiert nach Kategorie -->
			<?php foreach ( $categories as $cat_slug => $cat_title ) : ?>
				<?php
				$cat_services = array();

				foreach ( $statuses as $slug => $info ) {
					$svc = isset( $services[ $slug ] ) ? $services[ $slug ] : null;

					if ( $svc && isset( $svc['category'] ) && $svc['category'] === $cat_slug ) {
						$cat_services[ $slug ] = $info;
					}
				}

				if ( empty( $cat_services ) ) {
					continue;
				}
				?>

				<div class="dhps-db-category dhps-db-category--<?php echo esc_attr( $cat_slug ); ?>">
					<h2 class="dhps-db-category__title"><?php echo esc_html( $cat_title ); ?></h2>

					<div class="dhps-db-grid">
						<?php foreach ( $cat_services as $slug => $info ) :
							$service        = $services[ $slug ];
							$service_name   = $info['name'];
							$status         = $info['status'];
							$days_remaining = $info['days_remaining'];
							$admin_page     = isset( $service['admin_page'] ) ? $service['admin_page'] : '';
							$shop_url       = isset( $service['shop_url'] ) ? $service['shop_url'] : '';
							$image_url      = isset( $product_images[ $slug ] ) ? $product_images[ $slug ] : '';
						?>
						<div class="dhps-db-card">
							<!-- Status-Badge -->
							<?php if ( 'active' === $status ) : ?>
								<span class="dhps-db-card__badge dhps-db-card__badge--active">
									<?php echo esc_html( 'Aktiv' ); ?>
								</span>
							<?php elseif ( 'demo' === $status ) : ?>
								<span class="dhps-db-card__badge dhps-db-card__badge--demo">
									<?php
									printf( esc_html( 'Demo %d T.' ), (int) $days_remaining );
									?>
								</span>
							<?php endif; ?>

							<!-- Produktbild -->
							<?php if ( ! empty( $image_url ) ) : ?>
							<div class="dhps-db-card__image">
								<img src="<?php echo esc_url( $image_url ); ?>"
									 alt="<?php echo esc_attr( $service_name ); ?>"
									 loading="lazy">
							</div>
							<?php endif; ?>

							<!-- Content -->
							<div class="dhps-db-card__content">
								<h4 class="dhps-db-card__title"><?php echo esc_html( $service_name ); ?></h4>
							</div>

							<!-- Actions -->
							<div class="dhps-db-card__actions">
								<?php if ( 'inactive' === $status ) : ?>
									<button type="button"
											class="dhps-db-btn dhps-db-btn--demo"
											data-service="<?php echo esc_attr( $slug ); ?>"
											data-action="activate">
										<?php echo esc_html( 'Demo starten' ); ?>
									</button>
									<?php if ( ! empty( $shop_url ) ) : ?>
									<a href="<?php echo esc_url( $shop_url ); ?>"
									   class="dhps-db-btn dhps-db-btn--shop"
									   target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( 'Freischalten' ); ?>
									</a>
									<?php endif; ?>

								<?php elseif ( 'demo' === $status ) : ?>
									<?php if ( ! empty( $admin_page ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $admin_page ) ); ?>"
									   class="dhps-db-btn dhps-db-btn--config">
										<?php echo esc_html( 'Konfigurieren' ); ?>
									</a>
									<?php endif; ?>
									<button type="button"
											class="dhps-db-btn dhps-db-btn--stop"
											data-service="<?php echo esc_attr( $slug ); ?>"
											data-action="deactivate">
										<?php echo esc_html( 'Demo beenden' ); ?>
									</button>

								<?php elseif ( 'active' === $status ) : ?>
									<?php if ( ! empty( $admin_page ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $admin_page ) ); ?>"
									   class="dhps-db-btn dhps-db-btn--config">
										<?php echo esc_html( 'Konfigurieren' ); ?>
									</a>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				</div>

			<?php endforeach; ?>

			<!-- Info-Box -->
			<div class="dhps-db-info">
				<p>
					<?php
					printf(
						esc_html( 'Jeder Service kann %d Tage kostenlos getestet werden. Nach Ablauf wird er automatisch deaktiviert.' ),
						(int) $demo_duration
					);
					?>
					<?php
					echo wp_kses(
						'Fragen? <a href="mailto:mi-online-technik@deubner-verlag.de">mi-online-technik@deubner-verlag.de</a> oder 0221 / 93 70 18-28.',
						array( 'a' => array( 'href' => array() ) )
					);
					?>
				</p>
			</div>

		</div>
	</div>
</div>
