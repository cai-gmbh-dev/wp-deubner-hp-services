<?php
/**
 * Deprecated-Data-Proxy (v0.19.0).
 *
 * Wraps das Legacy-`$data`-Array im Template-Scope. Bei jedem Lese-Zugriff
 * wird ein WP_DEBUG-error_log + `_doing_it_wrong`-Notice ausgegeben, damit
 * Theme-Entwickler eine Migrations-Warning bekommen.
 *
 * Hintergrund (v0.19.0 MAJOR):
 *
 * Nach v0.18.0 (Pipeline einzige Datenquelle) lesen 0 Plugin-Templates noch
 * Service-spezifische Daten via `$data['categories']`/`$data['tax_dates']`/
 * etc. - alle haben auf `$collection` umgestellt. **Theme-Overrides** koennten
 * aber noch auf `$data['...']` zugreifen.
 *
 * Strategie (Discovery 37 Option B):
 *
 * - Renderer setzt im Template-Scope `$data = new DHPS_Deprecated_Data_Proxy(...)`
 *   statt das echte Array.
 * - Theme-Overrides die `$data['foo']` lesen funktionieren weiter (Proxy
 *   liefert den Wert), bekommen aber `_doing_it_wrong`-Notice.
 * - In v0.19.1 oder v0.20.0 kann `$data` komplett raus.
 *
 * Implementations-Hinweise:
 *
 * - `isset($data)` und `is_object($data)` muessen truthy bleiben **ohne**
 *   Deprecation-Notice (sonst feuert Notice schon beim Existenz-Check).
 * - Deprecation-Notice nur bei `offsetGet`, `offsetExists` (auf Item-Level),
 *   `count`, `getIterator`.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Deprecated_Data_Proxy
 *
 * @since 0.19.0
 */
final class DHPS_Deprecated_Data_Proxy implements ArrayAccess, Countable, IteratorAggregate {

	/**
	 * Echte Daten-Array (Parser-Output).
	 *
	 * @var array
	 */
	private array $data;

	/**
	 * Service-Tag fuer Diagnostik-Logging.
	 *
	 * @var string
	 */
	private string $service_tag;

	/**
	 * Layout-Name fuer Diagnostik-Logging.
	 *
	 * @var string
	 */
	private string $layout;

	/**
	 * Set damit Notice pro Key nur einmal feuert (verhindert Spam-Logs
	 * bei foreach ueber Items).
	 *
	 * @var array<string, true>
	 */
	private static array $notified_keys = array();

	/**
	 * Konstruktor.
	 *
	 * @since 0.19.0
	 *
	 * @param array  $data        Original-Daten-Array.
	 * @param string $service_tag Service-Tag fuer Diagnostik.
	 * @param string $layout      Layout-Name fuer Diagnostik.
	 */
	public function __construct( array $data, string $service_tag, string $layout = 'default' ) {
		$this->data        = $data;
		$this->service_tag = $service_tag;
		$this->layout      = $layout;
	}

	/**
	 * Emittiert Deprecation-Notice fuer einen Key (einmalig pro Service+Key).
	 *
	 * @since 0.19.0
	 *
	 * @param string $key Schluessel-Name, der gelesen wurde.
	 */
	private function deprecate_read( string $key ): void {
		$notice_id = $this->service_tag . '::' . $key;
		if ( isset( self::$notified_keys[ $notice_id ] ) ) {
			return;
		}
		self::$notified_keys[ $notice_id ] = true;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- diagnostisch, WP_DEBUG-gated.
			error_log( sprintf(
				'DHPS deprecated: $data[\'%s\'] gelesen in Template "%s" (Service "%s"). Nutzen Sie $collection oder $service_tag. Wird in einem kommenden Release entfernt.',
				$key,
				$this->layout,
				$this->service_tag
			) );
		}

		if ( function_exists( '_doing_it_wrong' ) ) {
			_doing_it_wrong(
				'DHPS Template',
				sprintf(
					/* translators: 1: Array-Schluessel-Name */
					esc_html__( 'Der Zugriff auf $data[\'%1$s\'] in DHPS-Templates ist deprecated. Nutzen Sie $collection oder $service_tag.', 'wp-deubner-hp-services' ),
					esc_html( $key )
				),
				'0.19.0'
			);
		}
	}

	// --- ArrayAccess ---

	/**
	 * Prueft ob Schluessel existiert.
	 *
	 * Loggt Deprecation-Notice (Theme-Override liest via `isset( $data[...] )`).
	 *
	 * @since 0.19.0
	 *
	 * @param mixed $offset Schluessel.
	 * @return bool
	 */
	public function offsetExists( $offset ): bool {
		$this->deprecate_read( (string) $offset );
		return isset( $this->data[ $offset ] );
	}

	/**
	 * Liefert Wert eines Schluessels.
	 *
	 * @since 0.19.0
	 *
	 * @param mixed $offset Schluessel.
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		$this->deprecate_read( (string) $offset );
		return $this->data[ $offset ] ?? null;
	}

	/**
	 * Set ist NICHT erlaubt - Proxy ist read-only.
	 *
	 * @since 0.19.0
	 *
	 * @param mixed $offset Schluessel.
	 * @param mixed $value  Wert.
	 */
	public function offsetSet( $offset, $value ): void {
		if ( function_exists( '_doing_it_wrong' ) ) {
			_doing_it_wrong(
				'DHPS Template',
				esc_html__( '$data in DHPS-Templates ist read-only und wird in einem kommenden Release entfernt.', 'wp-deubner-hp-services' ),
				'0.19.0'
			);
		}
	}

	/**
	 * Unset ist NICHT erlaubt - Proxy ist read-only.
	 *
	 * @since 0.19.0
	 *
	 * @param mixed $offset Schluessel.
	 */
	public function offsetUnset( $offset ): void {
		if ( function_exists( '_doing_it_wrong' ) ) {
			_doing_it_wrong(
				'DHPS Template',
				esc_html__( '$data in DHPS-Templates ist read-only und wird in einem kommenden Release entfernt.', 'wp-deubner-hp-services' ),
				'0.19.0'
			);
		}
	}

	// --- Countable ---

	/**
	 * Liefert Anzahl Schluessel.
	 *
	 * @since 0.19.0
	 *
	 * @return int
	 */
	public function count(): int {
		$this->deprecate_read( '<count>' );
		return count( $this->data );
	}

	// --- IteratorAggregate ---

	/**
	 * Iterator ueber Schluessel-Wert-Paare.
	 *
	 * @since 0.19.0
	 *
	 * @return Traversable
	 */
	public function getIterator(): Traversable {
		$this->deprecate_read( '<foreach>' );
		return new ArrayIterator( $this->data );
	}
}
