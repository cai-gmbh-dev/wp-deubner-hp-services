<?php
/**
 * Datum-Normalisierungs-Helper (v0.18.1).
 *
 * Wandelt Teil-Datums-Angaben (MM/YY-Strings, "Juli 2025"-Strings) in das
 * ISO-Year-Month-Format `YYYY-MM`. Adapter (TP, TPT, MIO) setzen das
 * Ergebnis als `meta.date_iso` Beimaterial-Feld an Items.
 *
 * Option C aus Discovery 34-DATUM-NORMALISIERUNG-PLAN-v0181:
 * additiver Schreib-Pfad in Adapter, KEINE Template-Aenderung. Templates
 * lesen weiter `meta.datum` (TP/TPT) bzw. `$month['title']` (MIO) fuer
 * Anzeige. `meta.date_iso` ist NUR fuer kuenftige Sortier-/Filter-
 * Konsumenten (z.B. v0.19.0 Collection-Sort-Hooks).
 *
 * Helper liegen bewusst in eigener Datei (analog dhps-content-helpers.php),
 * werden via require_once im Bootstrap geladen. function_exists-Guards
 * verhindern Redeclare-Fehler bei Mehrfach-Includes.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.18.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dhps_partial_date_to_iso' ) ) {

	/**
	 * Dispatcher: wandelt ein Teil-Datum im angegebenen Format in ISO YYYY-MM.
	 *
	 * Format-Whitelist:
	 *
	 * - `mm_yy`         : "10/24" -> "2024-10" (20YY-Konvention analog
	 *                     DHPS_TP_Parser::format_datum)
	 * - `de_month_year` : "Juli 2025" -> "2025-07" (DE-Monatsnamen + Jahr)
	 *
	 * Returnt null bei leerem Input, unbekanntem Format, Parse-Fehler oder
	 * defensiv-erkanntem Garbage. Konsumenten muessen mit null umgehen.
	 *
	 * @since 0.18.1
	 *
	 * @param string $input  Zu konvertierender Eingabe-String.
	 * @param string $format 'mm_yy' | 'de_month_year'.
	 *
	 * @return string|null ISO YYYY-MM oder null.
	 */
	function dhps_partial_date_to_iso( string $input, string $format ): ?string {
		$input = trim( $input );
		if ( '' === $input ) {
			return null;
		}
		if ( 'mm_yy' === $format ) {
			return dhps_partial_date_mm_yy_to_iso( $input );
		}
		if ( 'de_month_year' === $format ) {
			return dhps_partial_date_de_month_year_to_iso( $input );
		}
		return null;
	}
}

if ( ! function_exists( 'dhps_partial_date_mm_yy_to_iso' ) ) {

	/**
	 * Wandelt MM/YY-String in ISO YYYY-MM.
	 *
	 * Konvention (analog DHPS_TP_Parser::format_datum, seit v0.9.0):
	 * - Jahr-Suffix wird mit Praefix "20" zu Vier-Stellen-Jahr expandiert
	 *   ("24" -> "2024", "99" -> "2099")
	 * - Monat muss 01..12 sein, sonst null
	 * - Strikte Form MM/YY mit Slash (kein Punkt, kein Bindestrich)
	 * - Fuehrende Null im Monat optional (TP-Parser liefert sie)
	 *
	 * @since 0.18.1
	 *
	 * @param string $input Bereits trim'ter MM/YY-String.
	 *
	 * @return string|null ISO YYYY-MM oder null.
	 */
	function dhps_partial_date_mm_yy_to_iso( string $input ): ?string {
		if ( 1 !== preg_match( '#^(\d{1,2})/(\d{2})$#', $input, $matches ) ) {
			return null;
		}
		$month = (int) $matches[1];
		if ( $month < 1 || $month > 12 ) {
			return null;
		}
		$year = '20' . $matches[2];
		return sprintf( '%s-%02d', $year, $month );
	}
}

if ( ! function_exists( 'dhps_partial_date_de_month_year_to_iso' ) ) {

	/**
	 * Wandelt "Juli 2025"-Strings in ISO YYYY-MM.
	 *
	 * Format-Annahme: "MONATNAME JAHR" mit Whitespace-Trennung. Monatsnamen
	 * sind DE und werden case-insensitive matched (mb_strtolower mit UTF-8).
	 * Sowohl ASCII-Form ('maerz') als auch UTF-8-Form ('märz') werden gemappt
	 * (MIO-Parser kann beide Forms liefern, je nach API-Response).
	 *
	 * Jahr muss vierstellig sein. Bei Mehrfach-Whitespace toleriert preg_split.
	 *
	 * @since 0.18.1
	 *
	 * @param string $input Bereits trim'ter String wie "Juli 2025".
	 *
	 * @return string|null ISO YYYY-MM oder null.
	 */
	function dhps_partial_date_de_month_year_to_iso( string $input ): ?string {
		$parts = preg_split( '#\s+#', $input );
		if ( ! is_array( $parts ) || count( $parts ) !== 2 ) {
			return null;
		}
		$month_word = function_exists( 'mb_strtolower' )
			? mb_strtolower( $parts[0], 'UTF-8' )
			: strtolower( $parts[0] );
		$year_str   = $parts[1];

		if ( 1 !== preg_match( '#^\d{4}$#', $year_str ) ) {
			return null;
		}

		$map = array(
			'januar'    => 1,
			'februar'   => 2,
			'maerz'     => 3,
			'märz'      => 3,
			'april'     => 4,
			'mai'       => 5,
			'juni'      => 6,
			'juli'      => 7,
			'august'    => 8,
			'september' => 9,
			'oktober'   => 10,
			'november'  => 11,
			'dezember'  => 12,
		);
		if ( ! isset( $map[ $month_word ] ) ) {
			return null;
		}

		return sprintf( '%s-%02d', $year_str, $map[ $month_word ] );
	}
}
