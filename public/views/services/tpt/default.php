<?php
/**
 * Service-Template: TPT Standard-Layout (Single Video Teaser) - v0.14.3.
 *
 * Migration auf Component-System: rendert das einzelne TaxPlain-Teaser-Video
 * als ContentCard (type='video', service='tp'). Branding/Markup werden vom
 * Component geliefert; TP-JS bleibt unveraendert, weil `dhps-tp-card`-
 * Zusatzklasse + `data_attrs` (video-slug/poster-url/v-modus) am Card-Root
 * verbleiben.
 *
 * Empty-State: wenn $video leer, rendert EmptyState-Component (sichtbare
 * Editor-Vorschau statt stummem return).
 *
 * Admin-Texte (Ueberschrift, Teasertext) kommen seit v0.14.5 ueber
 * $data['tpt_config'] (Modules-Layer DHPS_TPT_Modules via Filter
 * dhps_pipeline_data_tpt). Theme-Overrides ohne Modules-Layer-Bindung
 * erhalten leere Strings (Null-Coalescing-Fallback).
 *
 * Verfuegbare Variablen:
 *   $data          (array)  Strukturiertes Array aus DHPS_TPT_Parser:
 *                            - 'video'       (array|null) Das einzelne Video
 *                            - 'service_tag' (string)     'tpt'
 *                            - 'tpt_config'  (array)      ['ueberschrift' => string,
 *                                                          'teasertext'   => string]
 *   $service_class (string) 'dhps-service--tpt'
 *   $layout_class  (string) 'dhps-layout--default'
 *   $custom_class  (string) Optionale CSS-Klasse
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/TPT
 * @since      0.12.0
 * @since      0.14.3 Migration auf Component-System (ContentCard + EmptyState).
 * @since      0.14.5 Admin-Texte via $data['tpt_config'] (DHPS_TPT_Modules).
 * @since      0.17.2 BC-Pseudo-Rebuild aus DHPS_Content_Collection (Adapter-Bridge).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// v0.17.2: Collection-Pfad wenn Adapter aktiv, sonst Legacy. Bytewise-BC
// durch Pseudo-Rebuild: aus Collection wird $video + $tpt_config in der
// alten Shape rekonstruiert. Render-Code unterhalb bleibt UNVERAENDERT.
$has_collection = isset( $collection ) && $collection instanceof DHPS_Content_Collection;

if ( $has_collection ) {
	$tpt_config = (array) $collection->get_meta( 'tpt_config', array() );
	$video      = null;
	foreach ( $collection as $item ) {
		$legacy = dhps_tp_item_to_legacy_video( $item );
		if ( ! empty( $legacy ) ) {
			$video = $legacy;
			break;
		}
	}
} else {
	// Legacy-Pfad (Pre-v0.17.2 BC).
	$tpt_config = isset( $data['tpt_config'] ) && is_array( $data['tpt_config'] ) ? $data['tpt_config'] : array();
	$video      = isset( $data['video'] ) && is_array( $data['video'] ) ? $data['video'] : null;
}

$layout_class  = isset( $layout_class ) && is_string( $layout_class ) ? $layout_class : 'dhps-layout--default';
$custom_class  = isset( $custom_class ) && is_string( $custom_class ) ? $custom_class : '';

// TP-Player-Skript fuer Click-Delegation auf [data-video-slug].
wp_enqueue_script( 'dhps-tp-js' );

// Wrapper-Klassen: `dhps-service--tp` ist Pflicht fuer dhps-tp.js-Init.
$wrapper_classes  = 'dhps-service dhps-service--tp dhps-service--tpt ';
$wrapper_classes .= sanitize_html_class( $layout_class );
if ( '' !== $custom_class ) {
	$wrapper_classes .= ' ' . $custom_class;
}
?>
<div class="<?php echo esc_attr( $wrapper_classes ); ?>"
	data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'dhps_tp_nonce' ) ); ?>"
	data-video-mode="inline"
	data-service="taxplain">

	<?php
	// Empty-State: kein Video verfuegbar -> sichtbarer Leerzustand statt return.
	if ( null === $video || empty( $video['video_slug'] ) ) {
		echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
			'empty-state',
			array(
				'icon'  => 'video',
				'title' => __( 'Kein Teaser-Video verfuegbar', 'wp-deubner-hp-services' ),
				'hint'  => __( 'Bitte spaeter erneut pruefen oder Lizenz/Auth-Daten kontrollieren.', 'wp-deubner-hp-services' ),
			)
		);
		?>
	</div>
		<?php
		return;
	}

	// Admin-konfigurierte Texte: $tpt_config wurde oben bereits aus
	// Collection (v0.17.2) bzw. $data (Legacy) vorbereitet.
	$ueberschrift = (string) ( $tpt_config['ueberschrift'] ?? '' );
	$teasertext   = (string) ( $tpt_config['teasertext'] ?? '' );

	// Optional: Section-Heading vor der Card (admin-konfiguriert).
	if ( '' !== $ueberschrift ) :
		?>
		<h3 class="dhps-tpt-card__heading"><?php echo esc_html( $ueberschrift ); ?></h3>
		<?php
	endif;

	// Card-Props bauen.
	$card_title  = isset( $video['titel'] ) ? (string) $video['titel'] : '';
	$card_slug   = (string) $video['video_slug'];
	$card_poster = isset( $video['poster_url'] ) ? (string) $video['poster_url'] : '';
	$card_vmodus = isset( $video['v_modus'] ) ? (string) $video['v_modus'] : '0';

	// Teaser: admin-konfigurierter Text hat Vorrang vor API-Teaser.
	$card_teaser = '';
	if ( '' !== $teasertext ) {
		$card_teaser = $teasertext;
	} elseif ( ! empty( $video['teaser'] ) ) {
		$card_teaser = (string) $video['teaser'];
	}

	// Meta (Datum) als ContentCard-Meta-Eintrag mit Calendar-Icon.
	$card_meta = array();
	if ( ! empty( $video['datum'] ) ) {
		$card_meta[] = array(
			'icon' => 'calendar',
			'text' => DHPS_TP_Parser::format_datum( (string) $video['datum'] ),
		);
	}

	echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
		'content-card',
		array(
			'type'       => 'video',
			'service'    => 'tp',
			'title'      => $card_title,
			'teaser'     => $card_teaser,
			'media_url'  => $card_poster,
			'media_alt'  => $card_title,
			// Beide Klassen: `dhps-tp-card` fuer TP-JS-Selektor, `dhps-tpt-card` fuer BC.
			'class'      => 'dhps-tp-card dhps-tpt-card',
			'meta'       => $card_meta,
			'data_attrs' => array(
				'video-slug' => $card_slug,
				'poster-url' => $card_poster,
				'v-modus'    => $card_vmodus,
			),
			'actions'    => array(
				array(
					'label'   => __( 'Video abspielen', 'wp-deubner-hp-services' ),
					'href'    => '#play',
					'icon'    => 'play',
					'primary' => true,
				),
			),
		)
	);
	?>

</div>
