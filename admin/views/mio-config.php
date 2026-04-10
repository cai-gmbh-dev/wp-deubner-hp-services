<?php
/**
 * Template: MI-Online-Konfigurationsseite (Spezialseite).
 *
 * Rendert zwei Formulare nebeneinander im Grid-Layout:
 * - Links:  Steuerrecht-Konfiguration (Shortcode [mio])
 * - Rechts: Recht-Konfiguration (Shortcode [lxmio])
 *
 * Erwartet folgende Variablen (gesetzt durch DHPS_Admin::render_mio_page()):
 * - array  $mio_values      Aktuelle Werte des Steuerrecht-Formulars
 * - array  $lxmio_values    Aktuelle Werte des Recht-Formulars
 * - bool   $mio_saved       True wenn das Steuerrecht-Formular gerade gespeichert wurde
 * - bool   $lxmio_saved     True wenn das Recht-Formular gerade gespeichert wurde
 * - array  $mio_fields      Feld-Definitionen fuer Steuerrecht (aus Registry)
 * - array  $lxmio_fields    Feld-Definitionen fuer Recht (aus Registry)
 * - string $mio_category    Kategorie fuer Steuerrecht: 'steuern' | 'recht' | 'medizin'
 * - string $mio_shop_url    Shop-URL fuer Steuerrecht-Produkt
 * - string $mio_icon        Dashicon-Klasse fuer Steuerrecht
 * - string $mio_status      Status Steuerrecht: 'active' | 'demo' | 'inactive'
 * - string $lxmio_category  Kategorie fuer Recht: 'steuern' | 'recht' | 'medizin'
 * - string $lxmio_shop_url  Shop-URL fuer Recht-Produkt
 * - string $lxmio_icon      Dashicon-Klasse fuer Recht
 * - string $lxmio_status    Status Recht: 'active' | 'demo' | 'inactive'
 *
 * @package    Deubner Homepage-Service
 * @subpackage Admin/Views
 * @since      0.4.0
 * @since      0.8.0 Config-Header mit Kategorie, Status-Badges und Shop-Links;
 *                    Formular-Felder mit .dhps-form-* CSS-Klassen;
 *                    Inline-Styles entfernt zugunsten CSS-Klassen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="deubnerhpservice" class="dhps wrap">
	<h1 class="dhps-notice-hook-element"></h1>

	<?php if ( $mio_saved ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( 'Steuerrecht-Einstellungen gespeichert.' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $lxmio_saved ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( 'Recht-Einstellungen gespeichert.' ); ?></p></div>
	<?php endif; ?>

	<div class="dhps-dashboard">

		<?php include __DIR__ . '/partials/header.php'; ?>

		<div class="dhps-content-area">

			<!-- Gemeinsamer Config-Header fuer MI-Online -->
			<div class="dhps-config-header dhps-category--steuern">
				<div class="dhps-config-header__info">
					<span class="dashicons dashicons-media-text"></span>
					<h3><?php echo esc_html( 'MI-Online-Konfiguration' ); ?></h3>
				</div>
			</div>

			<div id="dhpsui-wrap" class="dhpsui-row">

				<!-- Steuerrecht-Formular (links) -->
				<div class="dhpsui-col-lg-6">
					<div class="dhps-config-header dhps-category--<?php echo esc_attr( $mio_category ); ?>">
						<div class="dhps-config-header__info">
							<span class="dashicons <?php echo esc_attr( $mio_icon ); ?>"></span>
							<h3><?php echo esc_html( 'Steuerrecht' ); ?></h3>
							<?php if ( 'active' === $mio_status ) : ?>
								<span class="dhps-badge dhps-badge--active"><?php echo esc_html( 'Aktiv' ); ?></span>
							<?php elseif ( 'demo' === $mio_status ) : ?>
								<span class="dhps-badge dhps-badge--demo"><?php echo esc_html( 'Demo' ); ?></span>
							<?php else : ?>
								<span class="dhps-badge dhps-badge--inactive"><?php echo esc_html( 'Inaktiv' ); ?></span>
							<?php endif; ?>
						</div>
						<a href="<?php echo esc_url( $mio_shop_url ); ?>"
						   class="dhps-btn--shop" target="_blank" rel="noopener noreferrer">
							<span class="dashicons dashicons-cart"></span>
							<?php echo esc_html( 'Produkt im Shop ansehen' ); ?>
						</a>
					</div>

					<form method="post" action="" id="mio" class="validate">
						<?php wp_nonce_field( DEUBNER_HP_SERVICES_NONCE_ACTION, 'dhps_nonce' ); ?>

						<h2><?php echo esc_html( 'MI-Online-Parameter Steuerrecht:' ); ?></h2>

						<?php foreach ( $mio_fields as $field ) :
							$field_name  = $field['field_name'];
							$field_label = $field['label'];
							$field_type  = $field['type'] ?? 'text';
							$field_value = isset( $mio_values[ $field_name ] ) ? $mio_values[ $field_name ] : '';
						?>
							<div class="dhps-form-group">
								<label class="dhps-form-label" for="<?php echo esc_attr( $field_name ); ?>">
									<?php echo esc_html( $field_label ); ?>
								</label>

								<?php if ( 'select' === $field_type && ! empty( $field['options'] ) ) : ?>
									<select name="<?php echo esc_attr( $field_name ); ?>"
											id="<?php echo esc_attr( $field_name ); ?>"
											class="dhps-form-select">
										<?php foreach ( $field['options'] as $opt_value => $opt_label ) : ?>
											<option value="<?php echo esc_attr( $opt_value ); ?>"
													<?php selected( $field_value, (string) $opt_value ); ?>>
												<?php echo esc_html( $opt_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>

								<?php elseif ( 'textarea' === $field_type ) : ?>
									<textarea name="<?php echo esc_attr( $field_name ); ?>"
											  id="<?php echo esc_attr( $field_name ); ?>"
											  class="dhps-form-textarea"
											  rows="5"><?php echo esc_textarea( $field_value ); ?></textarea>

								<?php else : ?>
									<input type="text"
										   name="<?php echo esc_attr( $field_name ); ?>"
										   id="<?php echo esc_attr( $field_name ); ?>"
										   class="dhps-form-input"
										   value="<?php echo esc_attr( $field_value ); ?>">
								<?php endif; ?>

								<?php if ( ! empty( $field['description'] ) ) : ?>
									<p class="dhps-form-description"><?php echo wp_kses_post( $field['description'] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>

						<p class="submit">
							<input type="submit" name="submit" id="submit"
								   class="dhps-btn--primary"
								   value="<?php echo esc_attr( 'Speichern' ); ?>">
						</p>
					</form>
				</div>

				<!-- Recht-Formular (rechts) -->
				<div class="dhpsui-col-lg-6">
					<div class="dhps-config-header dhps-category--<?php echo esc_attr( $lxmio_category ); ?>">
						<div class="dhps-config-header__info">
							<span class="dashicons <?php echo esc_attr( $lxmio_icon ); ?>"></span>
							<h3><?php echo esc_html( 'Recht' ); ?></h3>
							<?php if ( 'active' === $lxmio_status ) : ?>
								<span class="dhps-badge dhps-badge--active"><?php echo esc_html( 'Aktiv' ); ?></span>
							<?php elseif ( 'demo' === $lxmio_status ) : ?>
								<span class="dhps-badge dhps-badge--demo"><?php echo esc_html( 'Demo' ); ?></span>
							<?php else : ?>
								<span class="dhps-badge dhps-badge--inactive"><?php echo esc_html( 'Inaktiv' ); ?></span>
							<?php endif; ?>
						</div>
						<a href="<?php echo esc_url( $lxmio_shop_url ); ?>"
						   class="dhps-btn--shop" target="_blank" rel="noopener noreferrer">
							<span class="dashicons dashicons-cart"></span>
							<?php echo esc_html( 'Produkt im Shop ansehen' ); ?>
						</a>
					</div>

					<form method="post" action="" id="lxmio" class="validate">
						<?php wp_nonce_field( DEUBNER_HP_SERVICES_NONCE_ACTION, 'dhps_lxmio_nonce' ); ?>

						<h2><?php echo esc_html( 'MI-Online-Parameter Recht:' ); ?></h2>

						<?php foreach ( $lxmio_fields as $field ) :
							$field_name  = $field['field_name'];
							$field_label = $field['label'];
							$field_type  = $field['type'] ?? 'text';
							$field_value = isset( $lxmio_values[ $field_name ] ) ? $lxmio_values[ $field_name ] : '';
						?>
							<div class="dhps-form-group">
								<label class="dhps-form-label" for="<?php echo esc_attr( $field_name ); ?>">
									<?php echo esc_html( $field_label ); ?>
								</label>

								<?php if ( 'select' === $field_type && ! empty( $field['options'] ) ) : ?>
									<select name="<?php echo esc_attr( $field_name ); ?>"
											id="<?php echo esc_attr( $field_name ); ?>"
											class="dhps-form-select">
										<?php foreach ( $field['options'] as $opt_value => $opt_label ) : ?>
											<option value="<?php echo esc_attr( $opt_value ); ?>"
													<?php selected( $field_value, (string) $opt_value ); ?>>
												<?php echo esc_html( $opt_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>

								<?php elseif ( 'textarea' === $field_type ) : ?>
									<textarea name="<?php echo esc_attr( $field_name ); ?>"
											  id="<?php echo esc_attr( $field_name ); ?>"
											  class="dhps-form-textarea"
											  rows="5"><?php echo esc_textarea( $field_value ); ?></textarea>

								<?php else : ?>
									<input type="text"
										   name="<?php echo esc_attr( $field_name ); ?>"
										   id="<?php echo esc_attr( $field_name ); ?>"
										   class="dhps-form-input"
										   value="<?php echo esc_attr( $field_value ); ?>">
								<?php endif; ?>

								<?php if ( ! empty( $field['description'] ) ) : ?>
									<p class="dhps-form-description"><?php echo wp_kses_post( $field['description'] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>

						<p class="submit">
							<input type="submit" name="lxmio_submit" id="lxmio_submit"
								   class="dhps-btn--primary"
								   value="<?php echo esc_attr( 'Speichern' ); ?>">
						</p>
					</form>
				</div>

			</div>

			<div class="dhps-welcome-box">
				<strong><?php echo esc_html( 'Beschreibung:' ); ?></strong>
				<ul>
					<li><?php echo esc_html( 'Schritt 1: Definieren Sie bitte zuerst die Parameter im oberen Teil dieser Seite' ); ?></li>
					<li>
						<?php echo wp_kses(
							'Schritt 2: Geben Sie einfach den Shortcode <code>[mio]</code> bzw. <code>[lxmio]</code> in einem Artikel oder in einer Seite ein.',
							array( 'code' => array() )
						); ?>
					</li>
					<li>
						<?php echo wp_kses(
							'Ggf. Schritt 3: Haben Sie Fragen? Bitte senden Sie eine Mail an <a href="mailto:mi-online-technik@deubner-verlag.de">mi-online-technik@deubner-verlag.de</a> oder klingeln Sie kurz auf der 0221 / 93 70 18-28 durch.',
							array( 'a' => array( 'href' => array() ) )
						); ?>
					</li>
				</ul>
			</div>

		</div>
	</div>
</div>
