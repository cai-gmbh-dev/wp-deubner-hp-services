<?php
/**
 * MAES Aktuelles Template - News-Akkordeon.
 *
 * Zeigt Nachrichten als aufklappbare Artikel (wie MIO-News).
 *
 * Verfuegbare Variablen:
 *   $news         - Array der News-Artikel aus DHPS_MAES_Parser.
 *   $custom_class - Optionale CSS-Klasse.
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $news ) ) {
	return;
}

// Konditionales Enqueue des ausgelagerten Akkordeon-Scripts (seit 0.13.1).
wp_enqueue_script( 'dhps-maes-aktuelles-js' );
?>
<div class="dhps-service dhps-service--maes-aktuelles<?php echo esc_attr( $custom_class ); ?>">

	<?php
	// Defaults fuer Shortcode-Nutzung (Elementor setzt diese Variablen).
	$show_teaser = $show_teaser ?? true;
	$first_open  = $first_open ?? false;
	?>

	<?php foreach ( $news as $idx => $article ) :
		$body_id  = 'dhps-' . esc_attr( $article['id'] );
		$is_open  = $first_open && 0 === $idx;
	?>
	<div class="dhps-news__article">
		<button type="button"
				class="dhps-news__title"
				aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
				aria-controls="<?php echo esc_attr( $body_id ); ?>"
				data-dhps-toggle="<?php echo esc_attr( $body_id ); ?>">
			<?php echo esc_html( $article['title'] ); ?>
			<?php if ( $show_teaser && ! empty( $article['teaser'] ) ) : ?>
			<span class="dhps-news__teaser-hint"><?php echo esc_html( $article['teaser'] ); ?></span>
			<?php endif; ?>
		</button>

		<div class="dhps-news__body"
			 id="<?php echo esc_attr( $body_id ); ?>"
			 aria-hidden="<?php echo $is_open ? 'false' : 'true'; ?>">
			<?php if ( ! empty( $article['body_html'] ) ) : ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML aus vertrauenswuerdiger Deubner-API.
			echo $article['body_html'];
			?>
			<?php endif; ?>
			<div class="dhps-news__actions">
				<button type="button" class="dhps-news__action-link" data-dhps-collapse="<?php echo esc_attr( $body_id ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<polyline points="18 15 12 9 6 15"/>
					</svg>
					<?php echo esc_html( 'Ausblenden' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php endforeach; ?>

</div>
