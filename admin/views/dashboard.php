<?php
/**
 * Template: Dashboard-Seite (Redesign).
 *
 * Zeigt die Uebersichtsseite des Plugins im WordPress-Admin.
 * Inkludiert den gemeinsamen Header, eine Willkommens-Box,
 * nach Kategorie gruppierte Service-Cards mit Status-Badges,
 * Demo-Toggle-Buttons sowie eine Info-Box mit Kontaktdaten.
 *
 * Erwartet folgende Variablen (gesetzt durch den Controller):
 * - array $statuses      Ergebnis von DHPS_Demo_Manager::get_all_statuses()
 * - int   $demo_duration Demo-Dauer in Tagen
 *
 * @package    Deubner Homepage-Service
 * @subpackage Admin/Views
 * @since      0.6.0
 * @since      0.8.0 Kategorie-Gruppierung und BEM-basierte Service-Cards.
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
?>
<div id="deubnerhpservice" class="dhps wrap">
	<h1 class="dhps-notice-hook-element"></h1>
	<div class="dhps-dashboard">

		<?php include __DIR__ . '/partials/header.php'; ?>

		<div class="dhps-content-area">

			<!-- Willkommens-Box -->
			<div class="dhps-welcome-box">
				<h3><?php echo esc_html( 'Willkommen bei den Deubner Homepage Services' ); ?></h3>
				<p>
					<?php echo esc_html( 'Hier sehen Sie eine Uebersicht aller verfuegbaren Services. Sie koennen einzelne Services als Demo testen oder direkt zur Konfiguration wechseln.' ); ?>
				</p>
			</div>

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

				<h2 class="dhps-section-title"><?php echo esc_html( $cat_title ); ?></h2>

				<div class="dhpsui-wrap">
					<div class="dhpsui-row">

						<?php foreach ( $cat_services as $slug => $info ) :
							$service        = $services[ $slug ];
							$service_name   = $info['name'];
							$status         = $info['status'];
							$days_remaining = $info['days_remaining'];
							$admin_page     = isset( $service['admin_page'] ) ? $service['admin_page'] : '';
							$shop_url       = isset( $service['shop_url'] ) ? $service['shop_url'] : '';
							$icon           = isset( $service['icon'] ) ? $service['icon'] : 'dashicons-admin-generic';
							$category       = isset( $service['category'] ) ? $service['category'] : '';
						?>
						<div class="dhpsui-col-lg-4">
							<div class="dhps-service-card dhps-category--<?php echo esc_attr( $category ); ?>">

								<!-- Card-Header mit Icon + Titel -->
								<div class="dhps-service-card__header">
									<div class="dhps-service-card__icon">
										<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
									</div>
									<h4 class="dhps-service-card__title">
										<?php echo esc_html( $service_name ); ?>
									</h4>
								</div>

								<!-- Status-Badge + Shortcode -->
								<div class="dhps-service-card__meta">
									<?php if ( 'active' === $status ) : ?>
										<span class="dhps-badge dhps-badge--active">
											<?php echo esc_html( 'Aktiv' ); ?>
										</span>
									<?php elseif ( 'demo' === $status ) : ?>
										<span class="dhps-badge dhps-badge--demo">
											<?php
											printf(
												/* translators: %d: Anzahl verbleibender Demo-Tage */
												esc_html( 'Demo (%d Tage)' ),
												(int) $days_remaining
											);
											?>
										</span>
									<?php else : ?>
										<span class="dhps-badge dhps-badge--inactive">
											<?php echo esc_html( 'Inaktiv' ); ?>
										</span>
									<?php endif; ?>

									<div class="dhps-service-card__shortcode">
										<span class="small-text"><?php echo esc_html( 'Shortcode:' ); ?></span>
										<code>[<?php echo esc_html( $slug ); ?>]</code>
									</div>
								</div>

								<!-- Aktions-Buttons -->
								<div class="dhps-service-card__actions">
									<?php if ( 'inactive' === $status ) : ?>
										<button
											type="button"
											class="button dhps-btn dhps-btn--demo"
											data-service="<?php echo esc_attr( $slug ); ?>"
											data-action="activate"
										>
											<?php echo esc_html( 'Demo starten' ); ?>
										</button>
										<?php if ( ! empty( $shop_url ) ) : ?>
											<a
												href="<?php echo esc_url( $shop_url ); ?>"
												class="button dhps-btn--shop"
												target="_blank"
												rel="noopener noreferrer"
											>
												<?php echo esc_html( 'Freischalten' ); ?>
											</a>
										<?php endif; ?>

									<?php elseif ( 'demo' === $status ) : ?>
										<button
											type="button"
											class="button dhps-btn dhps-btn--stop"
											data-service="<?php echo esc_attr( $slug ); ?>"
											data-action="deactivate"
										>
											<?php echo esc_html( 'Demo beenden' ); ?>
										</button>
										<?php if ( ! empty( $admin_page ) ) : ?>
											<a
												href="<?php echo esc_url( admin_url( 'admin.php?page=' . $admin_page ) ); ?>"
												class="button"
											>
												<?php echo esc_html( 'Konfigurieren' ); ?>
											</a>
										<?php endif; ?>
										<?php if ( ! empty( $shop_url ) ) : ?>
											<a
												href="<?php echo esc_url( $shop_url ); ?>"
												class="button dhps-btn--shop"
												target="_blank"
												rel="noopener noreferrer"
											>
												<?php echo esc_html( 'Jetzt freischalten' ); ?>
											</a>
										<?php endif; ?>

									<?php elseif ( 'active' === $status ) : ?>
										<?php if ( ! empty( $admin_page ) ) : ?>
											<a
												href="<?php echo esc_url( admin_url( 'admin.php?page=' . $admin_page ) ); ?>"
												class="button"
											>
												<?php echo esc_html( 'Konfigurieren' ); ?>
											</a>
										<?php endif; ?>
									<?php endif; ?>
								</div>

							</div>
						</div>
						<?php endforeach; ?>

					</div>
				</div>

			<?php endforeach; ?>

			<!-- Info-Box -->
			<div class="dhps-info-box">
				<h4>
					<?php echo esc_html( 'Hinweise zum Demo-Modus' ); ?>
				</h4>
				<p>
					<?php
					printf(
						/* translators: %d: Demo-Dauer in Tagen */
						esc_html( 'Jeder Service kann fuer %d Tage kostenlos im Demo-Modus getestet werden. Nach Ablauf der Demo wird der Service automatisch deaktiviert.' ),
						(int) $demo_duration
					);
					?>
				</p>
				<p>
					<?php
					echo wp_kses(
						'Haben Sie Fragen? Senden Sie eine Mail an <a href="mailto:mi-online-technik@deubner-verlag.de">mi-online-technik@deubner-verlag.de</a> oder rufen Sie uns an unter 0221 / 93 70 18-28.',
						array( 'a' => array( 'href' => array() ) )
					);
					?>
				</p>
			</div>

		</div>
	</div>
</div>
