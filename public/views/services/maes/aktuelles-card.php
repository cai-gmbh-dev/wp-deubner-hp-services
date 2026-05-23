<?php
/**
 * MAES Aktuelles Card Template - News als Karten-Grid (v0.14.1 Component-System).
 *
 * Modernisiert: nutzt ContentList (grid) + ContentCard (collapsible=true).
 * Outer-`.dhps-card` Wrapper bleibt aus BC-Gruenden erhalten, damit
 * bestehende Theme-Overrides + CSS-Selektoren nicht brechen.
 * Inline-Script (CSP-Bruch) entfaellt - Alpine uebernimmt den Toggle.
 *
 * Verfuegbare Variablen:
 *   $news         - Array der News-Artikel aus DHPS_MAES_Parser.
 *   $custom_class - Optionale CSS-Klasse.
 *   $show_teaser  - Teaser anzeigen (default: true).
 *   $first_open   - Reserviert (ContentCard v0.14.0 unterstuetzt kein
 *                   initial-open).
 *   $columns      - Spaltenzahl 1-4 (default: 2).
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 * @version 0.14.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_teaser  = isset( $show_teaser ) ? (bool) $show_teaser : true;
$first_open   = isset( $first_open ) ? (bool) $first_open : false;
$custom_class = isset( $custom_class ) && is_string( $custom_class ) ? $custom_class : '';
$columns      = isset( $columns ) ? absint( $columns ) : 2;
if ( $columns < 1 || $columns > 4 ) {
	$columns = 2;
}

$news_items = array();
if ( ! empty( $news ) && is_array( $news ) ) {
	foreach ( $news as $article ) {
		if ( ! is_array( $article ) || empty( $article['title'] ) ) {
			continue;
		}
		$item = array(
			'type'        => 'news',
			'service'     => 'maes',
			'title'       => (string) $article['title'],
			'body_html'   => isset( $article['body_html'] ) ? (string) $article['body_html'] : '',
			'collapsible' => true,
		);
		if ( $show_teaser && ! empty( $article['teaser'] ) ) {
			$item['teaser'] = (string) $article['teaser'];
		}
		$news_items[] = $item;
	}
}

$wrapper_class = 'dhps-service dhps-service--maes dhps-service--maes-aktuelles dhps-service--maes-aktuelles-card';
if ( '' !== $custom_class ) {
	$wrapper_class .= ' ' . $custom_class;
}
?>
<div class="dhps-card">
	<div class="<?php echo esc_attr( $wrapper_class ); ?>">
		<?php
		echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
			'content-list',
			array(
				'id'          => 'maes-aktuelles-card-' . wp_unique_id(),
				'layout'      => 'grid',
				'columns'     => $columns,
				'items'       => $news_items,
				'item_type'   => 'news',
				'class'       => 'dhps-content-list--maes-aktuelles',
				'empty_state' => array(
					'icon'  => 'inbox',
					'title' => __( 'Keine aktuellen Nachrichten', 'wp-deubner-hp-services' ),
					'hint'  => __( 'Sobald neue Nachrichten verfuegbar sind, erscheinen sie hier.', 'wp-deubner-hp-services' ),
				),
			)
		);
		?>
	</div>
</div>
