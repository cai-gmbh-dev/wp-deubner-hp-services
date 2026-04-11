<?php
/**
 * Template: Generische Service-Konfigurationsseite (Deubner Redesign).
 *
 * Rendert ein Konfigurationsformular fuer einen Deubner-Service
 * im Deubner-Verlag-Branding passend zum Dashboard.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Admin/Views
 * @since      0.4.0
 * @since      0.9.7 Deubner-Branding Redesign.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$img_base       = DEUBNER_HP_SERVICES_URL . 'assets/images/products/';
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
$product_img = isset( $product_images[ $service_slug ] ) ? $product_images[ $service_slug ] : '';
?>
<div id="deubnerhpservice" class="dhps wrap dhps-dashboard-wrap">
	<h1 class="dhps-notice-hook-element"></h1>

	<div class="dhps-dashboard">

		<?php include __DIR__ . '/partials/header.php'; ?>

		<div class="dhps-content-area">

			<?php if ( $saved ) : ?>
				<div class="dhps-db-notice dhps-db-notice--success">
					<?php echo esc_html( 'Einstellungen gespeichert.' ); ?>
				</div>
			<?php endif; ?>

			<!-- Service-Header: Bild + Info -->
			<div class="dhps-cfg-header dhps-db-category--<?php echo esc_attr( $category ); ?>">
				<?php if ( ! empty( $product_img ) ) : ?>
				<div class="dhps-cfg-header__image">
					<img src="<?php echo esc_url( $product_img ); ?>"
						 alt="<?php echo esc_attr( $page_title ); ?>">
				</div>
				<?php endif; ?>
				<div class="dhps-cfg-header__info">
					<h2 class="dhps-cfg-header__title"><?php echo esc_html( $page_title ); ?></h2>
					<div class="dhps-cfg-header__meta">
						<?php if ( 'active' === $status ) : ?>
							<span class="dhps-db-card__badge dhps-db-card__badge--active" style="position:static;border-radius:0;">
								<?php echo esc_html( 'Aktiv' ); ?>
							</span>
						<?php elseif ( 'demo' === $status ) : ?>
							<span class="dhps-db-card__badge dhps-db-card__badge--demo" style="position:static;border-radius:0;">
								<?php echo esc_html( 'Demo' ); ?>
							</span>
						<?php else : ?>
							<span class="dhps-db-card__badge" style="position:static;border-radius:0;background:#e2e3e5;color:#6c757d;">
								<?php echo esc_html( 'Inaktiv' ); ?>
							</span>
						<?php endif; ?>
						<?php if ( ! empty( $shop_url ) ) : ?>
						<a href="<?php echo esc_url( $shop_url ); ?>"
						   class="dhps-db-btn dhps-db-btn--shop" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( 'Im Shop ansehen' ); ?>
						</a>
						<?php endif; ?>
					</div>
					<p class="dhps-cfg-header__shortcode">
						<?php echo esc_html( 'Shortcode: ' ); ?>
						<?php foreach ( $shortcodes as $index => $sc_name ) : ?>
							<code>[<?php echo esc_html( $sc_name ); ?>]</code><?php
							if ( $index < count( $shortcodes ) - 1 ) {
								echo ' ';
							}
						?><?php endforeach; ?>
					</p>
				</div>
			</div>

			<!-- Formular -->
			<div class="dhps-cfg-form">
				<form method="post" action="" id="<?php echo esc_attr( $service_slug ); ?>" class="validate">
					<?php wp_nonce_field( $nonce_action, $nonce_field ); ?>

					<h3 class="dhps-cfg-form__title"><?php echo esc_html( 'Konfiguration' ); ?></h3>

					<?php foreach ( $fields as $field ) :
						$field_name  = $field['field_name'];
						$field_label = $field['label'];
						$field_type  = $field['type'] ?? 'text';
						$field_value = isset( $values[ $field_name ] ) ? $values[ $field_name ] : '';
					?>
						<div class="dhps-cfg-field">
							<label class="dhps-cfg-field__label" for="<?php echo esc_attr( $field_name ); ?>">
								<?php echo esc_html( $field_label ); ?>
							</label>

							<?php if ( 'select' === $field_type && ! empty( $field['options'] ) ) : ?>
								<select name="<?php echo esc_attr( $field_name ); ?>"
										id="<?php echo esc_attr( $field_name ); ?>"
										class="dhps-cfg-field__select">
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
										  class="dhps-cfg-field__textarea"
										  rows="4"><?php echo esc_textarea( $field_value ); ?></textarea>

							<?php else : ?>
								<input type="text"
									   name="<?php echo esc_attr( $field_name ); ?>"
									   id="<?php echo esc_attr( $field_name ); ?>"
									   class="dhps-cfg-field__input"
									   value="<?php echo esc_attr( $field_value ); ?>">
							<?php endif; ?>

							<?php if ( ! empty( $field['description'] ) ) : ?>
								<p class="dhps-cfg-field__desc"><?php echo wp_kses_post( $field['description'] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<div class="dhps-cfg-form__actions">
						<input type="submit" name="submit"
							   class="dhps-db-btn dhps-db-btn--config"
							   value="<?php echo esc_attr( 'Speichern' ); ?>">
					</div>
				</form>
			</div>

			<?php
			// Extra-Sections (z.B. TaxPlain Teaser).
			if ( ! empty( $extra_sections ) ) :
				foreach ( $extra_sections as $section ) :
			?>
				<div class="dhps-cfg-form dhps-cfg-form--extra">

					<?php if ( $section['saved'] ) : ?>
						<div class="dhps-db-notice dhps-db-notice--success">
							<?php echo esc_html( 'Einstellungen gespeichert.' ); ?>
						</div>
					<?php endif; ?>

					<form method="post" action="" class="validate">
						<?php wp_nonce_field( $nonce_action, $section['nonce_field'] ); ?>

						<h3 class="dhps-cfg-form__title"><?php echo esc_html( $section['title'] ); ?></h3>

						<?php if ( ! empty( $section['shortcodes'] ) ) : ?>
						<p class="dhps-cfg-header__shortcode">
							<?php echo esc_html( 'Shortcode: ' ); ?>
							<?php foreach ( $section['shortcodes'] as $si => $sc ) : ?>
								<code>[<?php echo esc_html( $sc ); ?>]</code><?php
								if ( $si < count( $section['shortcodes'] ) - 1 ) {
									echo ' ';
								}
							?><?php endforeach; ?>
						</p>
						<?php endif; ?>

						<?php foreach ( $section['fields'] as $field ) :
							$field_name  = $field['field_name'];
							$field_label = $field['label'];
							$field_type  = $field['type'] ?? 'text';
							$field_value = isset( $section['values'][ $field_name ] ) ? $section['values'][ $field_name ] : '';
						?>
							<div class="dhps-cfg-field">
								<label class="dhps-cfg-field__label" for="<?php echo esc_attr( $field_name ); ?>">
									<?php echo esc_html( $field_label ); ?>
								</label>

								<?php if ( 'select' === $field_type && ! empty( $field['options'] ) ) : ?>
									<select name="<?php echo esc_attr( $field_name ); ?>"
											id="<?php echo esc_attr( $field_name ); ?>"
											class="dhps-cfg-field__select">
										<?php foreach ( $field['options'] as $opt_value => $opt_label ) : ?>
											<option value="<?php echo esc_attr( $opt_value ); ?>"
													<?php selected( $field_value, (string) $opt_value ); ?>>
												<?php echo esc_html( $opt_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<input type="text"
										   name="<?php echo esc_attr( $field_name ); ?>"
										   id="<?php echo esc_attr( $field_name ); ?>"
										   class="dhps-cfg-field__input"
										   value="<?php echo esc_attr( $field_value ); ?>">
								<?php endif; ?>

								<?php if ( ! empty( $field['description'] ) ) : ?>
									<p class="dhps-cfg-field__desc"><?php echo wp_kses_post( $field['description'] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>

						<div class="dhps-cfg-form__actions">
							<input type="submit"
								   name="<?php echo esc_attr( $section['submit_name'] ); ?>"
								   class="dhps-db-btn dhps-db-btn--config"
								   value="<?php echo esc_attr( 'Speichern' ); ?>">
						</div>
					</form>
				</div>
			<?php
				endforeach;
			endif;
			?>

			<!-- Hilfe -->
			<div class="dhps-db-info">
				<p>
					<?php echo wp_kses(
						'Fragen? <a href="mailto:mi-online-technik@deubner-verlag.de">mi-online-technik@deubner-verlag.de</a> oder 0221 / 93 70 18-28.',
						array( 'a' => array( 'href' => array() ) )
					); ?>
				</p>
			</div>

		</div>
	</div>
</div>
