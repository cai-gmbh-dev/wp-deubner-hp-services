<?php
/**
 * DTO-Collection: Container fuer {@see DHPS_Content_Item}-Instanzen (v0.17.0).
 *
 * Immutable Collection mit Service-weitem Tag und Meta-Hash fuer
 * Collection-uebergreifende Daten wie Search-Config, AJAX-Params, Pagination.
 * Mutationen (`add`, `filter`) liefern NEUE Instanzen statt In-Place-Aenderung.
 *
 * Schema-Vertrag siehe docs/architecture/26-EINHEITLICHES-DATENMODELL-PLAN-v0170.md
 * Sektion 5.2.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Content_Collection
 *
 * Eager-Collection (Trust-Decision TD-4) ueber {@see DHPS_Content_Item}.
 * Implementiert IteratorAggregate fuer `foreach`-Iteration und Countable
 * fuer `count()`.
 *
 * Hinweis zur Benennung: Discovery-Doc nennt die Klasse `DHPS_ContentCollection`
 * ohne Underscore. Hier ist sie als `DHPS_Content_Collection` (mit
 * Underscore) gefuehrt, damit der bestehende Plugin-Autoloader die
 * Datei automatisch findet (siehe Trust-Decision F1-TD-1 im
 * DHPS_Content_Item-Doc-Block).
 *
 * @since 0.17.0
 */
final class DHPS_Content_Collection implements IteratorAggregate, Countable {

	/**
	 * Erlaubte Schluessel fuer {@see group_by()} (Trust-Decision TD-12).
	 *
	 * @since 0.17.0
	 * @var array<int,string>
	 */
	public const ALLOWED_GROUP_KEYS = array( 'category', 'type', 'service' );

	/**
	 * Bucket-Key fuer items ohne `category`-Feld (bei group_by('category')).
	 *
	 * @since 0.17.0
	 * @var string
	 */
	public const UNCATEGORIZED_BUCKET = '_uncategorized';

	/**
	 * Service-Tag der gesamten Collection (z.B. 'maes', 'mio').
	 *
	 * Wird sanitize_key()-normalisiert. KEINE Whitelist-Validierung an
	 * dieser Stelle - Collection-Service kann sich von Item-Services
	 * unterscheiden (z.B. Sub-Shortcode-Parent), die Item-Whitelist
	 * greift im ContentItem-Konstruktor.
	 *
	 * @since 0.17.0
	 * @var string
	 */
	public readonly string $service;

	/**
	 * Collection-uebergreifende Meta-Daten.
	 * Beispiele: `search_config`, `ajax_params`, `pagination`, `overview`.
	 *
	 * @since 0.17.0
	 * @var array
	 */
	public readonly array $meta;

	/**
	 * Items in Insertion-Order. Private, damit Mutations-Methoden
	 * konsistent eine neue Instanz liefern (Immutability).
	 *
	 * @since 0.17.0
	 * @var array<int,DHPS_Content_Item>
	 */
	private array $items;

	/**
	 * Konstruktor.
	 *
	 * Akzeptiert beliebige Items - filtert defensiv via instanceof, damit
	 * fehlerhafte Adapter nicht die ganze Pipeline brechen koennen.
	 *
	 * @since 0.17.0
	 *
	 * @param string $service Service-Tag (wird via sanitize_key normalisiert).
	 * @param array  $items   Liste, sollten DHPS_Content_Item-Instanzen sein.
	 *                        Non-ContentItem-Eintraege werden stillschweigend
	 *                        verworfen.
	 * @param array  $meta    Collection-Meta-Daten, default leeres Array.
	 */
	public function __construct( string $service, array $items = array(), array $meta = array() ) {
		$this->service = sanitize_key( $service );

		$valid_items = array();
		foreach ( $items as $item ) {
			if ( $item instanceof DHPS_Content_Item ) {
				$valid_items[] = $item;
			}
		}
		$this->items = array_values( $valid_items );

		$this->meta = $meta;
	}

	/**
	 * Fuegt ein Item hinzu und liefert eine NEUE Collection-Instanz.
	 *
	 * Bewahrt Immutability (Trust-Decision TD-1) - die aufrufende Stelle
	 * MUSS das Return-Value verwenden.
	 *
	 * @since 0.17.0
	 *
	 * @param DHPS_Content_Item $item Neues Item.
	 *
	 * @return self Neue Collection mit dem zusaetzlichen Item.
	 */
	public function add( DHPS_Content_Item $item ): self {
		$next   = $this->items;
		$next[] = $item;
		return new self( $this->service, $next, $this->meta );
	}

	/**
	 * Anzahl der Items in der Collection.
	 *
	 * @since 0.17.0
	 *
	 * @return int Anzahl, 0 bei leerer Collection.
	 */
	public function count(): int {
		return count( $this->items );
	}

	/**
	 * Prueft, ob die Collection leer ist.
	 *
	 * @since 0.17.0
	 *
	 * @return bool True wenn keine Items vorhanden.
	 */
	public function is_empty(): bool {
		return 0 === count( $this->items );
	}

	/**
	 * Liefert alle Items als Array (Insertion-Order).
	 *
	 * @since 0.17.0
	 *
	 * @return array<int,DHPS_Content_Item>
	 */
	public function get_items(): array {
		return $this->items;
	}

	/**
	 * Liefert das erste Item oder null bei leerer Collection.
	 *
	 * @since 0.17.0
	 *
	 * @return DHPS_Content_Item|null
	 */
	public function first(): ?DHPS_Content_Item {
		return $this->items[0] ?? null;
	}

	/**
	 * Filtert die Collection und liefert eine NEUE Instanz.
	 *
	 * Das uebergebene Predicate erhaelt jedes Item als einziges Argument
	 * und muss truthy/falsy zurueckgeben.
	 *
	 * @since 0.17.0
	 *
	 * @param callable $predicate Filter-Callback (DHPS_Content_Item => bool).
	 *
	 * @return self Neue Collection mit den gefilterten Items, Service+Meta
	 *              bleiben erhalten.
	 */
	public function filter( callable $predicate ): self {
		return new self(
			$this->service,
			array_values( array_filter( $this->items, $predicate ) ),
			$this->meta
		);
	}

	/**
	 * Gruppiert die Collection nach einem Item-Feld.
	 *
	 * Erlaubte Keys: 'category', 'type', 'service' (Trust-Decision TD-12).
	 * Items ohne `category`-Wert landen im Bucket {@see UNCATEGORIZED_BUCKET}.
	 *
	 * @since 0.17.0
	 *
	 * @param string $key Group-Schluessel aus {@see ALLOWED_GROUP_KEYS}.
	 *
	 * @return array<string,DHPS_Content_Collection> Sub-Collections je Bucket.
	 *
	 * @throws InvalidArgumentException Wenn $key nicht in der Whitelist liegt.
	 */
	public function group_by( string $key ): array {
		if ( ! in_array( $key, self::ALLOWED_GROUP_KEYS, true ) ) {
			throw new InvalidArgumentException( sprintf(
				'DHPS_Content_Collection::group_by(): "%s" nicht erlaubt (siehe ALLOWED_GROUP_KEYS).',
				$key
			) );
		}

		$buckets = array();
		foreach ( $this->items as $item ) {
			switch ( $key ) {
				case 'category':
					$bucket_key = $item->category ?? self::UNCATEGORIZED_BUCKET;
					break;
				case 'type':
					$bucket_key = $item->type;
					break;
				case 'service':
					$bucket_key = $item->service;
					break;
				default:
					// Unreachable wegen Whitelist-Check oben.
					$bucket_key = self::UNCATEGORIZED_BUCKET;
			}
			if ( ! isset( $buckets[ $bucket_key ] ) ) {
				$buckets[ $bucket_key ] = array();
			}
			$buckets[ $bucket_key ][] = $item;
		}

		$out = array();
		foreach ( $buckets as $bucket_key => $bucket_items ) {
			$out[ $bucket_key ] = new self( $this->service, $bucket_items, $this->meta );
		}
		return $out;
	}

	/**
	 * Liest einen Meta-Wert oder liefert den Default.
	 *
	 * @since 0.17.0
	 *
	 * @param string $key     Meta-Schluessel.
	 * @param mixed  $default Default-Wert, wenn Key nicht vorhanden.
	 *
	 * @return mixed Wert oder $default.
	 */
	public function get_meta( string $key, mixed $default = null ): mixed {
		return $this->meta[ $key ] ?? $default;
	}

	/**
	 * IteratorAggregate-Implementierung fuer `foreach`-Iteration.
	 *
	 * Liefert einen ArrayIterator ueber die Items (Insertion-Order).
	 *
	 * @since 0.17.0
	 *
	 * @return ArrayIterator
	 */
	public function getIterator(): ArrayIterator {
		return new ArrayIterator( $this->items );
	}

	/**
	 * Bequemlichkeits-Helper: alle Items als ContentCard-Props.
	 *
	 * @since 0.17.0
	 *
	 * @return array<int,array> Array von to_content_card_props()-Resultaten.
	 */
	public function to_content_card_items(): array {
		return array_map(
			static fn( DHPS_Content_Item $item ) => $item->to_content_card_props(),
			$this->items
		);
	}

	/**
	 * Liefert ein assoz. Array (Roundtrip-faehig fuer Cache/Tests).
	 *
	 * Shape: `['service' => string, 'items' => array[], 'meta' => array]`.
	 *
	 * @since 0.17.0
	 *
	 * @return array
	 */
	public function to_array(): array {
		$items_arr = array();
		foreach ( $this->items as $item ) {
			$items_arr[] = $item->to_array();
		}
		return array(
			'service' => $this->service,
			'items'   => $items_arr,
			'meta'    => $this->meta,
		);
	}

	/**
	 * Re-Hydration aus Array.
	 *
	 * @since 0.17.0
	 *
	 * @param array $data Array aus {@see to_array()} oder kompatibler Form.
	 *
	 * @return self Neue Collection.
	 *
	 * @throws InvalidArgumentException Wenn einzelne Items im Konstruktor scheitern.
	 */
	public static function from_array( array $data ): self {
		$service = isset( $data['service'] ) ? (string) $data['service'] : '';
		$meta    = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array();

		$items = array();
		if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $item_data ) {
				if ( is_array( $item_data ) ) {
					$items[] = DHPS_Content_Item::from_array( $item_data );
				}
			}
		}

		return new self( $service, $items, $meta );
	}
}
