<?php
/**
 * Template: Generische Service-Konfigurationsseite.
 *
 * Rendert ein Standard-Konfigurationsformular fuer einen Deubner-Service.
 * Unterstuetzt Text-Inputs, Select-Felder und Textarea-Felder.
 * Wird fuer alle Service-Seiten verwendet ausser MI-Online (eigenes Template).
 *
 * Erwartet folgende Variablen (gesetzt durch DHPS_Admin::render_page()):
 * - string $page_title     Seitentitel (z.B. "Merkblaetter-Konfiguration")
 * - string $service_slug   Service-Slug (z.B. "mmb")
 * - array  $fields         Array der Feld-Definitionen aus der Registry
 * - array  $values         Assoziatives Array der aktuellen Werte (field_name => value)
 * - bool   $saved          True wenn das Hauptformular gerade gespeichert wurde
 * - string $nonce_action   Nonce-Action (DEUBNER_HP_SERVICES_NONCE_ACTION)
 * - string $nonce_field    Nonce-Feldname (z.B. "dhps_nonce")
 * - array  $shortcodes     Liste der Shortcode-Namen fuer den Hilfe-Text
 * - array  $extra_sections Optionale Extra-Sections (z.B. TaxPlain Teaser)
 * - string $shortcode_hint Optionaler zusaetzlicher Hinweis zum Shortcode
 * - string $category       Kategorie: 'steuern' | 'recht' | 'medizin'
 * - string $shop_url       URL zum Produkt im Shop
 * - string $icon           Dashicon-Klasse (z.B. 'dashicons-media-text')
 * - string $status         Status: 'active' | 'demo' | 'inactive'
 *
 * @package    Deubner Homepage-Service
 * @subpackage Admin/Views
 * @since      0.4.0
 * @since      0.8.0 Config-Header mit Kategorie, Status-Badge und Shop-Link;
 *                    Formular-Felder mit .dhps-form-* CSS-Klassen;
 *                    Inline-Styles entfernt zugunsten CSS-Klassen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="deubnerhpservice" class="dhps wrap">
	<h1 class="dhps-notice-hook-element"></h1>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( 'Einstellungen gespeichert.' ); ?></p></div>
	<?php endif; ?>

	<div class="dhps-dashboard">

		<?php include __DIR__ . '/partials/header.php'; ?>

		<div class="dhps-content-area">

			<!-- Config-Header mit Service-Info und Shop-Link -->
			<div class="dhps-config-header dhps-category--<?php echo esc_attr( $category ); ?>">
				<div class="dhps-config-header__info">
					<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
					<h3><?php echo esc_html( $page_title ); ?></h3>
					<?php if ( 'active' === $status ) : ?>
						<span class="dhps-badge dhps-badge--active"><?php echo esc_html( 'Aktiv' ); ?></span>
					<?php elseif ( 'demo' === $status ) : ?>
						<span class="dhps-badge dhps-badge--demo"><?php echo esc_html( 'Demo' ); ?></span>
					<?php else : ?>
						<span class="dhps-badge dhps-badge--inactive"><?php echo esc_html( 'Inaktiv' ); ?></span>
					<?php endif; ?>
				</div>
				<a href="<?php echo esc_url( $shop_url ); ?>"
				   class="dhps-btn--shop" target="_blank" rel="noopener noreferrer">
					<span class="dashicons dashicons-cart"></span>
					<?php echo esc_html( 'Produkt im Shop ansehen' ); ?>
				</a>
			</div>

			<div class="dhps-welcome-box">
				<strong><?php echo esc_html( 'Kurzbeschreibung:' ); ?></strong>
				<ul>
					<li><?php echo esc_html( 'Schritt 1: Definieren Sie bitte zuerst die Parameter im unteren Teil dieser Seite' ); ?></li>
					<li>
						<?php echo esc_html( 'Schritt 2: Geben Sie einfach den Shortcode ' ); ?>
						<?php foreach ( $shortcodes as $index => $sc_name ) : ?>
							<code>[<?php echo esc_html( $sc_name ); ?>]</code><?php
							if ( $index < count( $shortcodes ) - 1 ) {
								echo esc_html( ' bzw. ' );
							}
						?><?php endforeach; ?>
						<?php echo esc_html( ' in einem Artikel oder in einer Seite ein.' ); ?>
						<?php if ( ! empty( $shortcode_hint ) ) : ?>
							<br><?php echo wp_kses( $shortcode_hint, array( 'code' => array() ) ); ?>
						<?php endif; ?>
					</li>
					<li>
						<?php echo wp_kses(
							'Ggf. Schritt 3: Haben Sie Fragen? Bitte senden Sie eine Mail an <a href="mailto:mi-online-technik@deubner-verlag.de">mi-online-technik@deubner-verlag.de</a> oder klingeln Sie kurz auf der 0221 / 93 70 18-28 durch.',
							array( 'a' => array( 'href' => array() ) )
						); ?>
					</li>
				</ul>
			</div>

			<form method="post" action="" id="<?php echo esc_attr( $service_slug ); ?>" class="validate">
				<?php wp_nonce_field( $nonce_action, $nonce_field ); ?>

				<h2><?php echo esc_html( 'Parameter:' ); ?></h2>

				<?php foreach ( $fields as $field ) :
					$field_name  = $field['field_name'];
					$field_label = $field['label'];
					$field_type  = $field['type'] ?? 'text';
					$field_value = isset( $values[ $field_name ] ) ? $values[ $field_name ] : '';
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

			<?php
			/**
			 * Extra-Sections (z.B. TaxPlain Teaser-Konfiguration).
			 * Jede Section ist ein eigenstaendiges Formular mit eigenem Nonce.
			 *
			 * @since 0.4.0
			 * @since 0.8.0 Formular-Felder mit .dhps-form-* CSS-Klassen;
			 *              Beschreibungs-Box mit .dhps-welcome-box.
			 */
			if ( ! empty( $extra_sections ) ) :
				foreach ( $extra_sections as $section ) :
			?>
				<hr>

				<?php if ( $section['saved'] ) : ?>
					<div class="notice notice-success"><p><?php echo esc_html( 'Einstellungen gespeichert.' ); ?></p></div>
				<?php endif; ?>

				<h3><?php echo esc_html( $section['title'] ); ?></h3>

				<?php if ( ! empty( $section['shortcodes'] ) ) : ?>
					<div class="dhps-welcome-box">
						<strong><?php echo esc_html( 'Kurzbeschreibung:' ); ?></strong>
						<ul>
							<li><?php echo esc_html( 'Schritt 1: Definieren Sie bitte zuerst die Parameter im unteren Teil dieser Seite' ); ?></li>
							<li>
								<?php echo esc_html( 'Schritt 2: Geben Sie einfach den Shortcode ' ); ?>
								<?php foreach ( $section['shortcodes'] as $si => $sc ) : ?>
									<code>[<?php echo esc_html( $sc ); ?>]</code><?php
									if ( $si < count( $section['shortcodes'] ) - 1 ) {
										echo esc_html( ' bzw. ' );
									}
								?><?php endforeach; ?>
								<?php echo esc_html( ' in einem Artikel oder in einer Seite ein.' ); ?>
							</li>
							<li>
								<?php echo wp_kses(
									'Ggf. Schritt 3: Haben Sie Fragen? Bitte senden Sie eine Mail an <a href="mailto:mi-online-technik@deubner-verlag.de">mi-online-technik@deubner-verlag.de</a> oder klingeln Sie kurz auf der 0221 / 93 70 18-28 durch.',
									array( 'a' => array( 'href' => array() ) )
								); ?>
							</li>
						</ul>
					</div>
				<?php endif; ?>

				<form method="post" action="" class="validate">
					<?php wp_nonce_field( $nonce_action, $section['nonce_field'] ); ?>

					<h2><?php echo esc_html( 'Parameter:' ); ?></h2>

					<?php foreach ( $section['fields'] as $field ) :
						$field_name  = $field['field_name'];
						$field_label = $field['label'];
						$field_type  = $field['type'] ?? 'text';
						$field_value = isset( $section['values'][ $field_name ] ) ? $section['values'][ $field_name ] : '';
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
						<input type="submit"
							   name="<?php echo esc_attr( $section['submit_name'] ); ?>"
							   class="dhps-btn--primary"
							   value="<?php echo esc_attr( 'Speichern' ); ?>">
					</p>
				</form>

			<?php
				endforeach;
			endif;
			?>

		</div>
	</div>
</div>
