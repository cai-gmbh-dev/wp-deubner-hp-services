<?php
/**
 * MIO-Adapter (v0.17.3): wandelt DHPS_MIO_Parser-Output (auch LXMIO, das
 * denselben Parser teilt) in DHPS_Content_Collection.
 *
 * Vierter Adapter im einheitlichen Datenmodell (nach MAES v0.17.0,
 * MMB v0.17.1, TP/TPT v0.17.2). Mappet MIO-Tax-Dates auf ContentItems
 * vom Type 'tax_date' (in ALLOWED_TYPES seit v0.17.0 vorbehalten - loest
 * sich hier ein). Sub-Struktur (entries[]/taxes[]/footnote) wandert in
 * den $meta-Hash des ContentItems (Trust-Decision TD-3 - Fluchtweg statt
 * Sub-DTO).
 *
 * News-Container und Search-Form-Konfiguration werden in der Collection-
 * Meta abgelegt; News-Items selbst entstehen erst im AJAX-Endpoint
 * (DHPS_MIO_News_Parser) und sind nicht Teil dieses Adapters.
 *
 * Service-Tolerant: Wird sowohl fuer `mio` als auch `lxmio` registriert
 * (Discovery v0.17.3 Sektion 3, Option B aus MMB/MIL+TP/LP-Pattern).
 * Item-IDs sind `mio-taxdate-{idx}` bzw. `lxmio-taxdate-{idx}`.
 *
 * Sub-Shortcode `[mio_termine]` umgeht den Adapter BEWUSST in v0.17.3.
 * Der Standalone-Pfad (`DHPS_Steuertermine`) hat eigene Templates +
 * eigene Filter-Atts (`month`, `count`) - Migration auf Adapter-Bridge
 * ist Tech-Debt-Ticket TD-V0173-1 fuer v0.17.x-Abschluss.
 *
 * Robustheit:
 * - Fehlt `tax_dates`, wird eine leere Collection geliefert (kein Throw).
 * - Monate ohne Title UND ohne Entries werden skipped.
 * - Monate ohne Title aber mit Entries bekommen Fallback-Title
 *   `'Monat '.($idx+1)` (DHPS_Content_Item erzwingt non-empty title).
 * - Footnote wird NUR in meta gesetzt wenn non-empty (konsistent mit
 *   MMB-Adapter source_id-Pattern).
 * - Defensive `(string)`/`(int)`/`is_array()`-Checks bei jedem Feldzugriff.
 *
 * Wichtig: tax_date-Items duerfen NICHT als ContentCard gerendert werden
 * (to_content_card_props() mapped tax_date -> document, verliert Sub-
 * Struktur). Templates rendern via Pseudo-Rebuild zurueck zu
 * `$tax_dates`-Shape und das bestehende BEM-Markup (dhps-tax-dates__*)
 * wird unveraendert weiterverwendet (Discovery v0.17.3 Sektion 2.5
 * Frage 3, Risiko R2).
 *
 * Klassen-/Datei-Konvention: `DHPS_MIO_Adapter` -> `class-dhps-mio-adapter.php`,
 * Datei liegt im includes/-Root (Autoloader-Konvention, identisch zu
 * MAES/MMB/TP/TPT-Adapter).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_MIO_Adapter
 *
 * Vierter Adapter im DTO-Layer, deckt MIO + LXMIO ab. Sondertyp `tax_date`.
 *
 * @since 0.17.3
 */
final class DHPS_MIO_Adapter implements DHPS_Content_Adapter_Interface {

	/**
	 * Wandelt MIO-Parser-Output in eine ContentCollection.
	 *
	 * Erwartet die Schluessel `tax_dates` (Liste von Monatsspalten),
	 * `search_config` (Such-/Filter-Konfig) und `ajax_params` (News-AJAX-
	 * Parameter) im Parser-Output (siehe {@see DHPS_MIO_Parser::parse()}).
	 * Alle drei sind optional - fehlende Schluessel werden als leere
	 * Strukturen behandelt.
	 *
	 * Item-Type-Mapping:
	 * - tax_dates[] -> type 'tax_date'
	 *   (id `{service}-taxdate-{month_index}`)
	 *
	 * Item-Felder:
	 * - `title`    = (string) tax_dates[$idx]['title'] mit Fallback
	 *                'Monat '.($idx+1) wenn Parser-leer
	 * - `category` = null (Monate sind keine "Kategorien")
	 * - `date`     = null (Monatstitel hat keinen Tag - kein DateTimeImmutable,
	 *                Tech-Debt TD-V0173-2)
	 * - `meta`     = {month_index, entries[], footnote?}
	 *
	 * Collection-Meta:
	 * - `search_config` -> 1:1 aus Parser-search_config
	 * - `ajax_params`   -> 1:1 aus Parser-ajax_params (PFLICHT fuer News-AJAX)
	 * - `months_order`  -> int[], Parser-Reihenfolge der gemappten Monate
	 * - `total_months`  -> Sum aller gemappten Monate
	 * - `total_entries` -> Sum aller entries[] ueber alle gemappten Monate
	 *
	 * @since 0.17.3
	 *
	 * @param array  $parser_output Output von DHPS_MIO_Parser::parse().
	 * @param string $service       Service-Tag ('mio' oder 'lxmio', von der Pipeline gesetzt).
	 *
	 * @return DHPS_Content_Collection Typisierte Item-Collection.
	 */
	public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {
		$tax_dates = isset( $parser_output['tax_dates'] ) && is_array( $parser_output['tax_dates'] )
			? $parser_output['tax_dates']
			: array();

		$items         = array();
		$months_order  = array();
		$total_entries = 0;

		foreach ( $tax_dates as $idx => $month ) {
			if ( ! is_array( $month ) ) {
				continue;
			}

			$raw_title = isset( $month['title'] ) ? (string) $month['title'] : '';
			$entries   = isset( $month['entries'] ) && is_array( $month['entries'] )
				? $month['entries']
				: array();
			$footnote  = isset( $month['footnote'] ) ? (string) $month['footnote'] : '';

			// Skip-Bedingung: leerer Monat ohne Title UND ohne Entries.
			if ( '' === trim( $raw_title ) && empty( $entries ) ) {
				continue;
			}

			// Title-Fallback: DHPS_Content_Item erzwingt non-empty title.
			// $idx ist hier der numerische Parser-Index (Foreach ueber 0-basiertes
			// Array). Wir casten defensiv, falls Parser ein assoz. Array liefern wuerde.
			$month_index = (int) $idx;
			$title       = ( '' !== trim( $raw_title ) )
				? $raw_title
				: sprintf( 'Monat %d', $month_index + 1 );

			$item_id = $service . '-taxdate-' . $month_index;

			$meta = array(
				'month_index' => $month_index,
				'entries'     => $entries,
			);
			if ( '' !== $footnote ) {
				$meta['footnote'] = $footnote;
			}

			$items[] = new DHPS_Content_Item(
				$item_id,
				$service,
				$title,
				'tax_date',
				'',       // body (leer - Sub-Struktur lebt im meta).
				null,     // excerpt.
				null,     // image.
				null,     // media.
				null,     // link.
				null,     // date (Monat hat keinen Tag - kein DateTimeImmutable).
				array(),  // tags.
				null,     // category (Monate sind keine "Kategorien").
				$meta
			);

			$months_order[] = $month_index;
			$total_entries += count( $entries );
		}

		$search_config = isset( $parser_output['search_config'] ) && is_array( $parser_output['search_config'] )
			? $parser_output['search_config']
			: array();
		$ajax_params   = isset( $parser_output['ajax_params'] ) && is_array( $parser_output['ajax_params'] )
			? $parser_output['ajax_params']
			: array();

		$collection_meta = array(
			'search_config' => $search_config,
			'ajax_params'   => $ajax_params,
			'months_order'  => $months_order,
			'total_months'  => count( $items ),
			'total_entries' => $total_entries,
		);

		return new DHPS_Content_Collection( $service, $items, $collection_meta );
	}
}
