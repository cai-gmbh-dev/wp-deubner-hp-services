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
 * Tech-Debt (laut Audit, Plan v0.14.3 Sektion 6 / TPT-#3):
 *   `get_option('dhps_tpt_ues')` und `get_option('dhps_tpt_teasertext')` werden
 *   weiterhin direkt im Template gelesen. Eine Verschiebung in Parser/Modules
 *   wuerde den TPT_Parser ohne API-Daten-Aufgabe erweitern (admin-konfigurierte
 *   Texte sind keine Anreicherung von API-Payloads). Folge-Ticket noetig.
 *
 * Verfuegbare Variablen:
 *   $data          (array)  Strukturiertes Array aus DHPS_TPT_Parser:
 *                            - 'video'       (array|null) Das einzelne Video
 *                            - 'service_tag' (string)     'tpt'
 *   $service_class (string) 'dhps-service--tpt'
 *   $layout_class  (string) 'dhps-layout--default'
 *   $custom_class  (string) Optionale CSS-Klasse
 *
 * @package    Deubner Homepage-Service
 * @subpackage Public/Views/Services/TPT
 * @since      0.12.0
 * @since      0.14.3 Migration auf Component-System (ContentCard + EmptyState).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$video         = isset( $data['video'] ) && is_array( $data['video'] ) ? $data['video'] : null;
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

	// Admin-konfigurierte Texte (Tech-Debt: get_option im Template, s. Header-Kommentar).
	$ueberschrift = (string) get_option( 'dhps_tpt_ues', '' );
	$teasertext   = (string) get_option( 'dhps_tpt_teasertext', '' );

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
