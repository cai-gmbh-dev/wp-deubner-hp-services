<?php
/**
 * MAES Aktuelles Compact Template - Minimale News-Liste (v0.14.1).
 *
 * Kompakte Sidebar-Variante: nur Titel + Klick klappt auf. Nutzt
 * ContentList (list-Layout, 1 Spalte) mit collapsible ContentCards.
 * Inline-Script (CSP-Bruch) entfaellt - Alpine uebernimmt den Toggle.
 *
 * Verfuegbare Variablen:
 *   $news         - Array der News-Artikel aus DHPS_MAES_Parser.
 *   $custom_class - Optionale CSS-Klasse.
 *   $show_teaser  - Optionaler Teaser unter Titel (default: false).
 *   $first_open   - Reserviert (ContentCard v0.14.0 unterstuetzt kein
 *                   initial-open).
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 * @version 0.14.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_teaser  = isset( $show_teaser ) ? (bool) $show_teaser : false;
$first_open   = isset( $first_open ) ? (bool) $first_open : false;
$custom_class = isset( $custom_class ) && is_string( $custom_class ) ? $custom_class : '';

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

$wrapper_class = 'dhps-service dhps-service--maes dhps-service--maes-aktuelles dhps-service--maes-aktuelles-compact';
if ( '' !== $custom_class ) {
	$wrapper_class .= ' ' . $custom_class;
}
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>">
	<?php
	echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
		'content-list',
		array(
			'id'          => 'maes-aktuelles-compact-' . wp_unique_id(),
			'layout'      => 'list',
			'columns'     => 1,
			'items'       => $news_items,
			'item_type'   => 'news',
			'class'       => 'dhps-content-list--maes-aktuelles dhps-content-list--compact',
			'empty_state' => array(
				'icon'  => 'inbox',
				'title' => __( 'Keine aktuellen Nachrichten', 'wp-deubner-hp-services' ),
			),
		)
	);
	?>
</div>
