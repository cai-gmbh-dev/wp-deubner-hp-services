<?php
/**
 * Adapter-Interface fuer das einheitliche Datenmodell (v0.17.0).
 *
 * Jeder Service-Adapter implementiert dieses Interface und uebersetzt
 * den heutigen, service-spezifischen Parser-Output (Legacy-Array) in
 * eine typisierte {@see DHPS_Content_Collection}. Damit wird der Bruch
 * zwischen historisch gewachsenen Parser-Shapes und dem neuen DTO-Layer
 * an genau einer Stelle pro Service gekapselt.
 *
 * Vertrag (siehe docs/architecture/26-EINHEITLICHES-DATENMODELL-PLAN-v0170.md
 * Sektion 5.3):
 * - Adapter LIEST das Parser-Output, schreibt es NICHT zurueck.
 * - Adapter ist deterministisch fuer gegebenen Input (cache-frei).
 * - Exceptions werden von der Pipeline gefangen (Fail-Soft, Sektion 5.4).
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface DHPS_Content_Adapter_Interface
 *
 * Adapter-Vertrag zwischen Parser und DTO-Layer.
 *
 * @since 0.17.0
 */
interface DHPS_Content_Adapter_Interface {

	/**
	 * Wandelt einen Parser-Output (Legacy-Array) in eine ContentCollection.
	 *
	 * Implementierer DUERFEN nicht davon ausgehen, dass alle Felder im
	 * Parser-Output vorhanden sind - defensives `isset()`/`empty()` ist Pflicht.
	 * Der uebergebene $service-Tag wird als Item-Service-Feld eingetragen
	 * und ueberschreibt den im Parser-Output ggf. abweichenden `service_tag`
	 * (Pipeline-Hoheit, siehe MEMORY "Pipeline ueberschreibt service_tag").
	 *
	 * @since 0.17.0
	 *
	 * @param array  $parser_output Output von DHPS_Parser_Interface::parse().
	 * @param string $service       Service-Tag (aus Pipeline gesetzt).
	 *
	 * @return DHPS_Content_Collection Typisierte Item-Collection.
	 */
	public function adapt( array $parser_output, string $service ): DHPS_Content_Collection;
}
