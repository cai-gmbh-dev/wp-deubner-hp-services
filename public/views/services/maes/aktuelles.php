<?php
/**
 * MAES Aktuelles Template - News-Liste (v0.14.1 Component-System).
 *
 * Modernisiert: nutzt ContentList + ContentCard (collapsible=true)
 * statt eigenem Akkordeon-Markup. Alpine.js uebernimmt den Toggle
 * via dhpsContentCard() - kein eigenes JS mehr noetig.
 *
 * Verfuegbare Variablen:
 *   $news         - Array der News-Artikel aus DHPS_MAES_Parser (Legacy).
 *                   Erwartet pro Artikel: id, title, teaser, body_html.
 *   $collection   - DHPS_Content_Collection|null (seit v0.17.0, optional).
 *   $custom_class - Optionale CSS-Klasse.
 *   $show_teaser  - Teaser als zweite Zeile anzeigen (default: true).
 *   $first_open   - Reserviert (ContentCard v0.14.0 unterstuetzt kein
 *                   initial-open; ContentCard-Erweiterung tracked).
 *
 * Heading-Hierarchie: h3 (Default) ueber Filter
 * `dhps_content_card_heading_level` ueberschreibbar.
 *
 * v0.17.0: Bei vorhandener Collection (MAES-Adapter registriert) werden
 * die News-Items per filter() ausgelesen. Andernfalls greift der
 * Legacy-Pfad - BC-Garantie analog zu videos.php / merkblaetter.php.
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 * @version 0.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Defaults fuer Shortcode-Nutzung (Elementor setzt diese Variablen).
$show_teaser  = isset( $show_teaser ) ? (bool) $show_teaser : true;
$first_open   = isset( $first_open ) ? (bool) $first_open : false;
$custom_class = isset( $custom_class ) && is_string( $custom_class ) ? $custom_class : '';

// --- Daten-Pfad waehlen: Collection wenn verfuegbar, sonst Legacy. ---
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

$news_items = array();

if ( $has_collection ) {
	// v0.17.0-Pfad: Collection -> News-Items -> ContentCard-Props.
	$news_collection = $collection->filter(
		static function ( $item ) {
			return $item instanceof DHPS_Content_Item && 'news' === $item->type;
		}
	);

	foreach ( $news_collection as $item ) {
		/** @var DHPS_Content_Item $item */
		$news_item = array(
			'type'        => 'news',
			'service'     => 'maes',
			'title'       => $item->title,
			'body_html'   => $item->body,
			'collapsible' => true,
		);
		if ( $show_teaser && null !== $item->excerpt && '' !== $item->excerpt ) {
			$news_item['teaser'] = $item->excerpt;
		}
		$news_items[] = $news_item;
	}
} else {
	// Legacy-Pfad (vor v0.17.0): Parser-Array manuell durchlaufen.
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
}

$wrapper_class = 'dhps-service dhps-service--maes dhps-service--maes-aktuelles';
if ( '' !== $custom_class ) {
	$wrapper_class .= ' ' . $custom_class;
}
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>">
	<?php
	echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
		'content-list',
		array(
			'id'          => 'maes-aktuelles-' . wp_unique_id(),
			'layout'      => 'list',
			'columns'     => 1,
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
