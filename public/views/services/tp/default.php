<?php
/**
 * Service-Template: TP Standard-Layout (v0.14.3 Component-System).
 *
 * Rendert die geparsten TP-Daten (Featured Video + Video-Katalog mit
 * Kategorie-Filter) ueber das ContentList/ContentCard-Component-System.
 * Wird via Template-Fallback `lp -> tp` auch fuer LP-Services verwendet -
 * die Service-Prop wird dynamisch aus `$service_tag` abgeleitet, damit
 * TP-Karten Steuern-Gruen und LP-Karten Recht-Blau erhalten.
 *
 * Hybrid-Strategie (siehe docs/architecture/17-TP-MIGRATION-PLAN-v0143.md):
 * - ContentCard rendert das Markup.
 * - Zusatz-Klasse `dhps-tp-card` + Lazy-Marker bleiben fuer dhps-tp.js.
 * - `data_attrs` liefern `video-slug`, `poster-url`, `v-modus`,
 *   `video-index` an die Card-Root.
 * - Filter-Bar bleibt inline (TP-JS-Selektoren `.dhps-filter-bar__btn` +
 *   `data-filter`).
 * - Featured-Video bleibt eigene `<section class="dhps-tp-featured">` mit
 *   einer ContentCard, die Modifier-Klasse `dhps-tp-card--featured`
 *   traegt (visueller Unterschied per CSS).
 *
 * Konfigurierbar ueber WordPress-Filter:
 * - dhps_tp_grid_columns (int)    Spalten im Grid (Standard: 3, max. 4 wg. ContentList-Cap).
 * - dhps_tp_lazy_count   (int)    Anzahl sichtbarer Cards (0 = alle).
 * - dhps_tp_lazy_mode    (string) 'manual' oder 'auto'.
 * - dhps_tp_style        (string) 'default', 'minimal' oder 'shadow'.
 *
 * Kann vom Theme ueberschrieben werden unter:
 * {theme}/dhps/services/tp/default.php
 *
 * Verfuegbare Variablen:
 * - $data          (array)  Strukturiertes Array aus DHPS_TP_Parser (bzw. LP-Parser).
 * - $service_class (string) CSS-Klasse: 'dhps-service--tp' oder 'dhps-service--lp'.
 * - $layout_class  (string) CSS-Klasse: 'dhps-layout--default'.
 * - $custom_class  (string) Optionale CSS-Klasse.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/TP
 * @since      0.9.1
 * @since      0.14.3 Migration auf ContentList/ContentCard-Component-System.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// v0.18.0: Pipeline-Garantie (siehe MMB/default.php Header). Pseudo-Rebuild
// via Helper dhps_tp_collection_to_legacy_categories liefert {featured, categories}.
$collection = dhps_collection_or_empty( $collection, 'tp' );
$rebuilt    = dhps_tp_collection_to_legacy_categories( $collection );
$featured   = $rebuilt['featured'];
$categories = $rebuilt['categories'];


// service_tag bestimmt das Card-Branding ('tp' -> Steuern-Gruen, 'lp' -> Recht-Blau).
$card_service = ( 'lp' === $service_tag ) ? 'lp' : 'tp';

// Konfigurierbare Optionen via WordPress-Filter.
$grid_columns = absint( apply_filters( 'dhps_tp_grid_columns', 3 ) );
$lazy_count   = absint( apply_filters( 'dhps_tp_lazy_count', 0 ) );
$lazy_mode    = sanitize_key( apply_filters( 'dhps_tp_lazy_mode', 'manual' ) );
$tp_style     = sanitize_key( apply_filters( 'dhps_tp_style', 'default' ) );
$video_mode   = sanitize_key( apply_filters( 'dhps_tp_video_mode', 'inline' ) );

// Sicherheitsgrenzen. ContentList akzeptiert max. 4 Spalten.
if ( $grid_columns < 1 || $grid_columns > 6 ) {
	$grid_columns = 3;
}
$list_columns = min( $grid_columns, 4 );

if ( ! in_array( $lazy_mode, array( 'manual', 'auto' ), true ) ) {
	$lazy_mode = 'manual';
}
if ( ! in_array( $tp_style, array( 'default', 'minimal', 'shadow' ), true ) ) {
	$tp_style = 'default';
}

wp_enqueue_script( 'dhps-tp-js' );

// service-Parameter fuer AJAX-Proxy (taxplain/lexplain): aus erstem Video ableiten.
$video_service = 'taxplain';
if ( ! empty( $featured['service'] ) ) {
	$video_service = $featured['service'];
} elseif ( ! empty( $categories[0]['videos'][0]['service'] ) ) {
	$video_service = $categories[0]['videos'][0]['service'];
}

// Items fuer den Video-Katalog bauen (Featured ist separat).
$items       = array();
$video_index = 0;
foreach ( $categories as $cat_index => $cat ) {
	if ( empty( $cat['videos'] ) || ! is_array( $cat['videos'] ) ) {
		continue;
	}
	$cat_name = isset( $cat['name'] ) ? (string) $cat['name'] : '';
	foreach ( $cat['videos'] as $video ) {
		if ( ! is_array( $video ) ) {
			continue;
		}
		$slug   = isset( $video['video_slug'] ) ? (string) $video['video_slug'] : '';
		$titel  = isset( $video['titel'] ) ? (string) $video['titel'] : '';
		$poster = isset( $video['poster_url'] ) ? (string) $video['poster_url'] : '';
		if ( '' === $slug || '' === $titel ) {
			continue;
		}

		// Lazy-Hidden-Status: erste $lazy_count Karten sichtbar, Rest hidden.
		// Marker-Klasse genuegt: CSS `.dhps-tp-card--lazy-hidden { display: none; }`.
		$is_hidden    = ( $lazy_count > 0 && $video_index >= $lazy_count );
		$extra_class  = 'dhps-tp-card';
		$extra_class .= $is_hidden ? ' dhps-tp-card--lazy-hidden' : '';

		// Meta-Eintrag fuer Datum, wenn vorhanden.
		$meta = array();
		if ( ! empty( $video['datum'] ) ) {
			$meta[] = array(
				'icon' => 'calendar',
				'text' => DHPS_TP_Parser::format_datum( (string) $video['datum'] ),
			);
		}

		$items[] = array(
			'type'       => 'video',
			'service'    => $card_service,
			'title'      => $titel,
			// Teaser wird per CSS line-clamp gekuerzt (keine PHP-Truncation).
			'teaser'     => isset( $video['teaser'] ) ? (string) $video['teaser'] : '',
			'media_url'  => $poster,
			'media_alt'  => $titel,
			'class'      => $extra_class,
			'meta'       => $meta,
			'actions'    => array(
				array(
					'label'   => __( 'Video abspielen', 'wp-deubner-hp-services' ),
					'href'    => '#play',
					'icon'    => 'play',
					'primary' => true,
				),
			),
			// Data-Attribute fuer dhps-tp.js (Event-Delegation auf [data-video-slug]).
			'data_attrs' => array(
				'video-slug'  => $slug,
				'poster-url'  => $poster,
				'v-modus'     => isset( $video['v_modus'] ) ? (string) $video['v_modus'] : '0',
				'video-index' => (string) $video_index,
				'category'    => (string) $cat_index,
				'video-id'    => isset( $video['video_id'] ) ? (string) $video['video_id'] : '',
			),
		);

		++$video_index;
	}
}

$list_id = 'dhps-tp-catalog-' . wp_unique_id();

$wrapper_classes  = 'dhps-service ' . $service_class . ' ' . $layout_class . $custom_class;
$wrapper_classes .= ' dhps-tp-style--' . $tp_style;
?>
<div class="<?php echo esc_attr( $wrapper_classes ); ?>"
	 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	 data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
	 data-style="<?php echo esc_attr( $tp_style ); ?>"
	 data-columns="<?php echo esc_attr( (string) $grid_columns ); ?>"
	 data-lazy-count="<?php echo esc_attr( (string) $lazy_count ); ?>"
	 data-lazy-mode="<?php echo esc_attr( $lazy_mode ); ?>"
	 data-video-mode="<?php echo esc_attr( $video_mode ); ?>"
	 data-service="<?php echo esc_attr( $video_service ); ?>">

	<?php if ( $featured && ! empty( $featured['video_slug'] ) ) :
		$featured_slug   = (string) $featured['video_slug'];
		$featured_title  = isset( $featured['titel'] ) ? (string) $featured['titel'] : '';
		$featured_poster = isset( $featured['poster_url'] ) ? (string) $featured['poster_url'] : '';
		$featured_meta   = array();
		if ( ! empty( $featured['datum'] ) ) {
			$featured_meta[] = array(
				'icon' => 'calendar',
				'text' => DHPS_TP_Parser::format_datum( (string) $featured['datum'] ),
			);
		}
	?>
	<section class="dhps-tp-featured" aria-label="<?php echo esc_attr__( 'Aktueller Video-Tipp', 'wp-deubner-hp-services' ); ?>">
		<h3 class="dhps-tp-featured__heading"><?php esc_html_e( 'Der aktuelle Video-Tipp', 'wp-deubner-hp-services' ); ?></h3>
		<?php
		echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
			'content-card',
			array(
				'type'       => 'video',
				'service'    => $card_service,
				'title'      => $featured_title,
				'teaser'     => isset( $featured['teaser'] ) ? (string) $featured['teaser'] : '',
				'media_url'  => $featured_poster,
				'media_alt'  => $featured_title,
				// `dhps-tp-card` + Featured-Modifier; Video-Player-Wrapper-Klassen
				// `dhps-tp-video dhps-tp-video--featured` fuer Inline-iframe-Append.
				'class'      => 'dhps-tp-card dhps-tp-card--featured dhps-tp-video dhps-tp-video--featured',
				'meta'       => $featured_meta,
				'actions'    => array(
					array(
						'label'   => __( 'Video abspielen', 'wp-deubner-hp-services' ),
						'href'    => '#play',
						'icon'    => 'play',
						'primary' => true,
					),
				),
				'data_attrs' => array(
					'video-slug' => $featured_slug,
					'poster-url' => $featured_poster,
					'v-modus'    => isset( $featured['v_modus'] ) ? (string) $featured['v_modus'] : '0',
				),
			)
		);
		?>
	</section>

	<hr class="dhps-divider">
	<?php endif; ?>

	<?php if ( ! empty( $categories ) ) : ?>
	<section class="dhps-tp-catalog" aria-label="<?php echo esc_attr__( 'Alle Video-Tipps', 'wp-deubner-hp-services' ); ?>">
		<h3 class="dhps-tp-catalog__heading"><?php esc_html_e( 'Alle Video-Tipps', 'wp-deubner-hp-services' ); ?></h3>

		<!-- Kategorie-Filter (Markup unveraendert - TP-JS-Selektoren).
		     aria-controls verweist auf die ContentList-Region (A11y-Fix v0.14.5). -->
		<nav class="dhps-filter-bar dhps-tp-catalog__filter" aria-label="<?php echo esc_attr__( 'Kategorien', 'wp-deubner-hp-services' ); ?>">
			<button class="dhps-filter-bar__btn dhps-tp-filter__btn dhps-filter-bar__btn--active dhps-tp-filter__btn--active"
					data-filter="all" aria-pressed="true" type="button"
					aria-controls="<?php echo esc_attr( $list_id ); ?>">
				<?php esc_html_e( 'Alle', 'wp-deubner-hp-services' ); ?>
			</button>
			<?php foreach ( $categories as $index => $cat ) : ?>
			<button class="dhps-filter-bar__btn dhps-tp-filter__btn"
					data-filter="<?php echo esc_attr( (string) $index ); ?>"
					aria-pressed="false" type="button"
					aria-controls="<?php echo esc_attr( $list_id ); ?>">
				<?php echo esc_html( isset( $cat['name'] ) ? (string) $cat['name'] : '' ); ?>
			</button>
			<?php endforeach; ?>
		</nav>

		<?php
		// ContentList rendert das Grid. Die `dhps-tp-grid`-Klasse bleibt am
		// Wrapper erhalten, damit `setupAutoLoad` in dhps-tp.js den Sentinel-
		// Anker findet. Spaltenzahl-Suffix wird ebenfalls vergeben.
		echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
			'content-list',
			array(
				'id'          => $list_id,
				'layout'      => 'grid',
				'columns'     => $list_columns,
				'items'       => $items,
				'item_type'   => 'video',
				'class'       => 'dhps-tp-grid dhps-tp-grid--' . $grid_columns . 'col',
				'empty_state' => array(
					'icon'  => 'video',
					'title' => __( 'Keine Video-Tipps verfuegbar', 'wp-deubner-hp-services' ),
				),
			)
		);
		?>

		<?php if ( $lazy_count > 0 && $video_index > $lazy_count ) : ?>
		<button class="dhps-tp-load-more dhps-btn dhps-btn--primary" type="button">
			<?php esc_html_e( 'Weitere Videos laden', 'wp-deubner-hp-services' ); ?>
		</button>
		<?php endif; ?>
	</section>
	<?php endif; ?>

</div>
