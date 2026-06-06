<?php
/**
 * MMB-Adapter (v0.17.1): wandelt DHPS_MMB_Parser-Output in DHPS_Content_Collection.
 *
 * Zweiter Adapter im einheitlichen Datenmodell (nach MAES, v0.17.0). Mappet
 * die MMB-Category-Fact-Sheet-Struktur in eine flache ContentItem-Collection,
 * wo jedes Item via $item->category einer Rubrik (`rubrik_N`) zugeordnet ist.
 * Header-Daten (Name, Icon, Count) leben in Collection-Meta unter
 * `categories_meta[category_id]`. Damit koennen Templates ueber
 * `categories_order` in Parser-Reihenfolge iterieren und je Bucket via
 * Item-Filter die Fact-Sheets nachladen, ohne `group_by()` aufrufen zu muessen.
 *
 * Service-Tolerant: Wird sowohl fuer `mmb` als auch `mil` registriert
 * (Discovery v0.17.1 Sektion 5, Option B). Der Adapter ist agnostisch -
 * der Service-Tag wird vom Pipeline-Aufrufer als $service-Param uebergeben
 * und 1:1 in jedes Item geschrieben (inklusive Item-ID-Prefix). Item-IDs
 * sind daher `mmb-doc-{cat_idx}-{sheet_id_or_idx}` bzw. `mil-doc-...`.
 *
 * Robustheit:
 * - Fehlt `categories`, wird eine leere Collection geliefert (kein Throw).
 * - Fact-Sheets ohne `title` werden skipped (analog MAES-Adapter).
 * - Defensive `(string)`/`(int)`-Casts bei jedem Feldzugriff.
 * - Categories ohne ID UND ohne Fact-Sheets werden skipped.
 *
 * Klassen-/Datei-Konvention: `DHPS_MMB_Adapter` -> `class-dhps-mmb-adapter.php`,
 * Datei liegt im includes/-Root (Autoloader-Konvention, identisch zum
 * MAES-Adapter und Discovery v0.17.1 Sektion 10.3).
 *
 * Hinweis zum Filter `dhps_content_adapter_for_service`: ein Filter darf
 * den MMB-Adapter ueberschreiben, muss aber ein
 * DHPS_Content_Adapter_Interface zurueckliefern (defensives Reading in der
 * Registry, siehe SEC-MEDIUM-2-Fix v0.17.0).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_MMB_Adapter
 *
 * Zweiter Adapter im DTO-Layer, deckt MMB + MIL ab.
 *
 * @since 0.17.1
 */
final class DHPS_MMB_Adapter implements DHPS_Content_Adapter_Interface {

	/**
	 * Wandelt MMB-Parser-Output in eine ContentCollection.
	 *
	 * Erwartet die Schluessel `categories` (Liste der Rubriken) und
	 * `search_config` im Parser-Output (siehe {@see DHPS_MMB_Parser::parse()}).
	 * Beide sind optional - fehlende Schluessel werden als leere Strukturen
	 * behandelt.
	 *
	 * Item-Type-Mapping:
	 * - categories[].fact_sheets[] -> type 'document'
	 *   (id `{service}-doc-{cat_idx}-{sheet_id_or_idx}`)
	 *
	 * Item-Felder:
	 * - `category` = `categories[].id` (z.B. 'rubrik_1') fuer Tab-Filter-Match
	 * - `excerpt`  = `fact_sheets[].description` (Plain-Text, kein wp_kses)
	 * - `meta`     = `{category_index, doc_index, source_id?, pdf_params?}`
	 *
	 * Collection-Meta:
	 * - `search_config`    -> 1:1 aus Parser-search_config
	 * - `categories_order` -> string[], Parser-Reihenfolge der Bucket-IDs
	 * - `categories_meta`  -> `{cat_id => {name, icon_slug, count}}`
	 * - `total_documents`  -> Sum aller gemappten Fact-Sheets
	 *
	 * @since 0.17.1
	 *
	 * @param array  $parser_output Output von DHPS_MMB_Parser::parse().
	 * @param string $service       Service-Tag ('mmb' oder 'mil', von der Pipeline gesetzt).
	 *
	 * @return DHPS_Content_Collection Typisierte Item-Collection.
	 */
	public function adapt( array $parser_output, string $service ): DHPS_Content_Collection {
		$categories = isset( $parser_output['categories'] ) && is_array( $parser_output['categories'] )
			? $parser_output['categories']
			: array();

		$items            = array();
		$categories_order = array();
		$categories_meta  = array();
		$total_documents  = 0;

		foreach ( $categories as $cat_idx => $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}

			$cat_id        = isset( $category['id'] ) ? (string) $category['id'] : '';
			$cat_name      = isset( $category['name'] ) ? (string) $category['name'] : '';
			$cat_icon_slug = isset( $category['icon_slug'] ) ? (string) $category['icon_slug'] : '';
			$fact_sheets   = isset( $category['fact_sheets'] ) && is_array( $category['fact_sheets'] )
				? $category['fact_sheets']
				: array();

			// Kategorie ohne ID UND ohne Sheets: skippen (Robustheit, Discovery R3).
			if ( '' === $cat_id && empty( $fact_sheets ) ) {
				continue;
			}

			$bucket_count = 0;
			foreach ( $fact_sheets as $sheet_idx => $sheet ) {
				if ( ! is_array( $sheet ) || empty( $sheet['title'] ) ) {
					continue;
				}

				$title       = (string) $sheet['title'];
				$description = isset( $sheet['description'] ) ? (string) $sheet['description'] : null;
				$sheet_id    = isset( $sheet['id'] ) ? (string) $sheet['id'] : '';

				// ID-Konvention: '{service}-doc-{cat_idx}-{sheet_id_or_idx}'.
				// cat_idx (numerischer Schleifen-Index) statt cat_id (rubrik_N),
				// damit der String kuerzer bleibt und konsistent zu MAES
				// (`maes-doc-N`) aufgebaut ist. Discovery v0.17.1 Sektion 3.
				$item_id_tail = ( '' !== $sheet_id ) ? $sheet_id : (string) $sheet_idx;
				$item_id      = $service . '-doc-' . (int) $cat_idx . '-' . $item_id_tail;

				$meta = array(
					'category_index' => (int) $cat_idx,
					'doc_index'      => (int) $sheet_idx,
					'category_id'    => $cat_id,
					'category_name'  => $cat_name,
					'icon_slug'      => $cat_icon_slug,
				);
				if ( '' !== $sheet_id ) {
					// Bewahrt die Parser-Original-ID fuer Pseudo-Rebuild im Template.
					$meta['source_id'] = $sheet_id;
				}
				if ( isset( $sheet['pdf_params'] ) && is_array( $sheet['pdf_params'] ) ) {
					$meta['pdf_params'] = $sheet['pdf_params'];
				}

				$items[] = new DHPS_Content_Item(
					$item_id,
					$service,
					$title,
					'document',
					'',           // body (leer - Description landet in excerpt).
					$description, // excerpt (Plain-Text aus DOM-Parser).
					null,         // image.
					null,         // media (kein PDF-URL-Item, AJAX-Proxy haelt kdnr fern).
					null,         // link.
					null,         // date.
					array(),      // tags.
					$cat_id,      // category - Match-Key fuer Tab-Filter.
					$meta
				);

				++$bucket_count;
				++$total_documents;
			}

			// Categories-Meta nur eintragen, wenn wir eine ID haben - sonst
			// koennen Templates die Header-Daten nicht via Item.$category
			// nachschlagen (Pseudo-Rebuild im Template wuerde leerlaufen).
			if ( '' !== $cat_id ) {
				$categories_order[]         = $cat_id;
				$categories_meta[ $cat_id ] = array(
					'name'      => $cat_name,
					'icon_slug' => $cat_icon_slug,
					'count'     => $bucket_count,
				);
			}
		}

		$search_config = isset( $parser_output['search_config'] ) && is_array( $parser_output['search_config'] )
			? $parser_output['search_config']
			: array();

		$meta = array(
			'search_config'    => $search_config,
			'categories_order' => $categories_order,
			'categories_meta'  => $categories_meta,
			'total_documents'  => $total_documents,
		);

		return new DHPS_Content_Collection( $service, $items, $meta );
	}
}
