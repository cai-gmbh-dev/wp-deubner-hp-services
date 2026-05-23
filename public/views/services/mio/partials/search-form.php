<?php
/**
 * Partial: MIO/LXMIO Search-Form.
 *
 * Geteiltes Such- und Filterformular fuer alle 3 MIO-Layouts
 * (default, card, compact) sowie LXMIO via Template-Fallback.
 *
 * Erwartete Variablen (per `include` aus dem Eltern-Template):
 * - $service_tag   (string) 'mio' oder 'lxmio'. Pflicht fuer Field-IDs.
 * - $search_config (array)  ['target_groups' => [...], 'search_placeholder' => '...']. Optional.
 * - $placeholder   (string) Optional. Ueberschreibt search_config-Placeholder, Default 'Suchbegriff'.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/MIO/Partials
 * @since      0.14.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Defensiv: Variablen muessen existieren, falls Partial isoliert eingebunden wird.
$service_tag   = isset( $service_tag ) ? (string) $service_tag : 'mio';
$search_config = isset( $search_config ) && is_array( $search_config ) ? $search_config : array();
$placeholder   = isset( $placeholder ) && '' !== $placeholder
	? (string) $placeholder
	: ( isset( $search_config['search_placeholder'] ) ? (string) $search_config['search_placeholder'] : 'Suchbegriff' );
?>
<section class="dhps-search-bar" aria-label="<?php echo esc_attr( 'Suche und Filter' ); ?>">
	<form class="dhps-search-bar__form" role="search" data-dhps-search>
		<?php if ( ! empty( $search_config['target_groups'] ) ) : ?>
		<div class="dhps-search-bar__field">
			<label class="dhps-search-bar__label screen-reader-text" for="dhps-rubriken-<?php echo esc_attr( $service_tag ); ?>">
				<?php echo esc_html( 'Zielgruppe' ); ?>
			</label>
			<select class="dhps-search-bar__select"
					id="dhps-rubriken-<?php echo esc_attr( $service_tag ); ?>"
					name="rubriken"
					data-dhps-rubriken>
				<?php foreach ( $search_config['target_groups'] as $group ) : ?>
				<option value="<?php echo esc_attr( $group ); ?>"><?php echo esc_html( $group ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php endif; ?>

		<div class="dhps-search-bar__field dhps-search-bar__field--grow">
			<label class="dhps-search-bar__label screen-reader-text" for="dhps-suchbegriff-<?php echo esc_attr( $service_tag ); ?>">
				<?php echo esc_html( 'Suchbegriff' ); ?>
			</label>
			<input type="search"
				   class="dhps-search-bar__input"
				   id="dhps-suchbegriff-<?php echo esc_attr( $service_tag ); ?>"
				   name="suchbegriff"
				   placeholder="<?php echo esc_attr( $placeholder ); ?>"
				   data-dhps-search-input
				   data-dhps-live-search-min="3">
		</div>

		<button type="submit" class="dhps-search-bar__button" aria-label="<?php echo esc_attr( 'Suchen' ); ?>" data-dhps-search-submit>
			<svg class="dhps-search-bar__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<circle cx="11" cy="11" r="8"/>
				<path d="m21 21-4.35-4.35"/>
			</svg>
		</button>
	</form>
</section>
