<?php
/**
 * DTO: Einheitliches Content-Item fuer alle DHPS-Services (v0.17.0).
 *
 * Immutable Value-Object, das ein einzelnes Content-Item Service-uebergreifend
 * beschreibt (News-Artikel, Video, Merkblatt, Steuertermin, generischer Item).
 * Properties sind PHP 8.1 `readonly`, daher nach Construction nicht aenderbar.
 *
 * Schema-Vertrag siehe docs/architecture/26-EINHEITLICHES-DATENMODELL-PLAN-v0170.md
 * Sektion 5.1.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Includes
 * @since      0.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DHPS_Content_Item
 *
 * Immutable DTO fuer ein einzelnes Content-Item. Die Klasse ist `final`,
 * weil das DTO via Item-Type-Feld (news/video/document/tax_date/generic)
 * generisch bleibt - service-spezifische Daten leben im `$meta`-Hash und
 * brauchen keine Sub-Klassen-Hierarchie (Trust-Decision TD-3).
 *
 * Hinweis zur Benennung: Discovery-Doc (Sektion 2.1/10.4) nennt die
 * Klasse `DHPS_ContentItem` ohne Underscore. Hier ist sie als
 * `DHPS_Content_Item` (mit Underscore) gefuehrt, damit der bestehende
 * Plugin-Autoloader (Konvention `DHPS_Foo_Bar` -> `class-dhps-foo-bar.php`)
 * die Datei automatisch findet. Im Briefing-Filename
 * `class-dhps-content-item.php` ist der Underscore bereits angelegt -
 * Discovery- und Plugin-Konvention sind hier konsistent ueber die
 * Klassen-Spelling-Trust-Decision F1-TD-1.
 *
 * @since 0.17.0
 */
final class DHPS_Content_Item {

	/**
	 * Erlaubte Werte fuer das `$type`-Feld.
	 *
	 * @since 0.17.0
	 * @var array<int,string>
	 */
	public const ALLOWED_TYPES = array( 'news', 'video', 'document', 'tax_date', 'generic' );

	/**
	 * Erlaubte Service-Tags fuer das `$service`-Feld.
	 *
	 * Spiegelt die ALLOWED_SERVICES-Whitelist aus {@see DHPS_Admin_REST}, ohne
	 * zur Lade-Zeit eine harte Abhaengigkeit darauf zu erzeugen (Adapter-Foundation
	 * darf nicht auf REST-Klassen warten). Bei Schema-Erweiterungen muss diese
	 * Liste synchron zu DHPS_Admin_REST::ALLOWED_SERVICES gehalten werden.
	 *
	 * @since 0.17.0
	 * @var array<int,string>
	 */
	public const ALLOWED_SERVICES = array(
		// Hauptservices.
		'mio',
		'lxmio',
		'mmb',
		'mil',
		'tp',
		'tpt',
		'tc',
		'maes',
		'lp',
		// Sub-Shortcodes (preview-faehig seit v0.15.4).
		'mio_termine',
		'maes_videos',
		'maes_merkblaetter',
		'maes_aktuelles',
	);

	/**
	 * Maximale Laenge fuer ID-Strings (defensive Truncation).
	 *
	 * @since 0.17.0
	 * @var int
	 */
	private const MAX_ID_LENGTH = 128;

	/**
	 * Maximale Laenge fuer Service-Tags.
	 *
	 * @since 0.17.0
	 * @var int
	 */
	private const MAX_SERVICE_LENGTH = 32;

	/**
	 * Maximale Laenge fuer Titel-Strings.
	 *
	 * @since 0.17.0
	 * @var int
	 */
	private const MAX_TITLE_LENGTH = 500;

	/**
	 * Maximale Laenge fuer Category-Strings.
	 *
	 * @since 0.17.0
	 * @var int
	 */
	private const MAX_CATEGORY_LENGTH = 128;

	/**
	 * Maximale Laenge fuer einzelne Tag-Strings.
	 *
	 * @since 0.17.0
	 * @var int
	 */
	private const MAX_TAG_LENGTH = 50;

	/**
	 * Eindeutige Item-ID (z.B. "maes-video-3").
	 *
	 * @since 0.17.0
	 * @var string
	 */
	public readonly string $id;

	/**
	 * Service-Tag fuer Branding und Routing (z.B. "maes", "mio").
	 *
	 * @since 0.17.0
	 * @var string
	 */
	public readonly string $service;

	/**
	 * Item-Type: einer von ALLOWED_TYPES.
	 *
	 * @since 0.17.0
	 * @var string
	 */
	public readonly string $type;

	/**
	 * Item-Titel (Pflichtfeld, nicht leer).
	 *
	 * @since 0.17.0
	 * @var string
	 */
	public readonly string $title;

	/**
	 * Hauptinhalt - HTML ODER Plain-Text. Sanitization-Entscheidung liegt
	 * beim Adapter (Trust-Decision TD-10), Konstruktor schreibt unveraendert.
	 *
	 * @since 0.17.0
	 * @var string
	 */
	public readonly string $body;

	/**
	 * Kurzer Teaser, Plain-Text empfohlen.
	 *
	 * @since 0.17.0
	 * @var string|null
	 */
	public readonly ?string $excerpt;

	/**
	 * Optionales Bild-Asset.
	 * Shape: `array{url:string, alt:string, width?:int, height?:int}`.
	 *
	 * @since 0.17.0
	 * @var array|null
	 */
	public readonly ?array $image;

	/**
	 * Optionales Media-Asset (Video/PDF/Extern).
	 * Shape: `array{kind:string, slug?:string, url?:string, poster?:string, params?:array}`.
	 *
	 * @since 0.17.0
	 * @var array|null
	 */
	public readonly ?array $media;

	/**
	 * URL fuer "Mehr erfahren" oder Detail-Anker.
	 *
	 * @since 0.17.0
	 * @var string|null
	 */
	public readonly ?string $link;

	/**
	 * Datum als immutable Date-Object.
	 *
	 * @since 0.17.0
	 * @var DateTimeImmutable|null
	 */
	public readonly ?DateTimeImmutable $date;

	/**
	 * String-Tags zur Klassifikation. Tag-Strings werden im Konstruktor auf
	 * MAX_TAG_LENGTH truncated, Non-Strings entfernt.
	 *
	 * @since 0.17.0
	 * @var array<int,string>
	 */
	public readonly array $tags;

	/**
	 * Optionale Top-Level-Kategorie-ID (z.B. MMB-Kategorie-Slug).
	 *
	 * @since 0.17.0
	 * @var string|null
	 */
	public readonly ?string $category;

	/**
	 * Service-spezifische Extras als Fluchtweg (z.B. `pdf_params`, `share_links`,
	 * `v_modus`, `tax_entries`). Muss JSON-encode-faehig sein, damit
	 * `to_array()` / `from_array()`-Roundtrip funktioniert.
	 *
	 * @since 0.17.0
	 * @var array
	 */
	public readonly array $meta;

	/**
	 * Konstruktor mit Pflichtfeld-Validierung und stiller Truncation bei
	 * out-of-range Strings (Schema-Vertrag Sektion 5.1).
	 *
	 * @since 0.17.0
	 *
	 * @param string                 $id       Eindeutige Item-ID (Pflicht, nicht leer).
	 * @param string                 $service  Service-Tag (Pflicht, in ALLOWED_SERVICES).
	 * @param string                 $title    Item-Titel (Pflicht, nicht leer).
	 * @param string                 $type     Item-Type, default 'generic', in ALLOWED_TYPES.
	 * @param string                 $body     Hauptinhalt (HTML oder Plain), default ''.
	 * @param string|null            $excerpt  Kurzer Teaser, default null.
	 * @param array|null             $image    Bild-Asset-Shape, default null.
	 * @param array|null             $media    Media-Asset-Shape, default null.
	 * @param string|null            $link     URL/Anker fuer "Mehr erfahren", default null.
	 * @param DateTimeImmutable|null $date     Optionales Datum, default null.
	 * @param array                  $tags     String-Tags, default leeres Array.
	 * @param string|null            $category Optionale Kategorie-ID, default null.
	 * @param array                  $meta     Service-spezifische Extras, default leeres Array.
	 *
	 * @throws InvalidArgumentException Wenn $id, $title oder $service leer sind, oder
	 *                                  $service / $type nicht in der Whitelist liegen.
	 */
	public function __construct(
		string $id,
		string $service,
		string $title,
		string $type = 'generic',
		string $body = '',
		?string $excerpt = null,
		?array $image = null,
		?array $media = null,
		?string $link = null,
		?DateTimeImmutable $date = null,
		array $tags = array(),
		?string $category = null,
		array $meta = array()
	) {
		// --- Pflichtfeld-Validierung (Trust-Decision TD-11: frueher Fehlerfall). ---
		if ( '' === trim( $id ) ) {
			throw new InvalidArgumentException( 'DHPS_Content_Item: $id darf nicht leer sein.' );
		}
		if ( '' === trim( $title ) ) {
			throw new InvalidArgumentException( 'DHPS_Content_Item: $title darf nicht leer sein.' );
		}
		$service_normalized = sanitize_key( $service );
		if ( '' === $service_normalized ) {
			throw new InvalidArgumentException( 'DHPS_Content_Item: $service darf nicht leer sein.' );
		}
		if ( ! in_array( $service_normalized, self::ALLOWED_SERVICES, true ) ) {
			throw new InvalidArgumentException( sprintf(
				'DHPS_Content_Item: $service "%s" ist nicht in der Whitelist (siehe ALLOWED_SERVICES).',
				$service_normalized
			) );
		}
		if ( ! in_array( $type, self::ALLOWED_TYPES, true ) ) {
			throw new InvalidArgumentException( sprintf(
				'DHPS_Content_Item: $type "%s" ist nicht erlaubt (siehe ALLOWED_TYPES).',
				$type
			) );
		}

		// --- Stille Truncation auf Maximal-Laengen. ---
		$this->id      = mb_substr( $id, 0, self::MAX_ID_LENGTH );
		$this->service = mb_substr( $service_normalized, 0, self::MAX_SERVICE_LENGTH );
		$this->title   = mb_substr( $title, 0, self::MAX_TITLE_LENGTH );
		$this->type    = $type;
		$this->body    = $body;
		$this->excerpt = $excerpt;
		$this->image   = $image;
		$this->media   = $media;
		$this->link    = $link;
		$this->date    = $date;

		// Tags: nur String-Werte, Truncation auf MAX_TAG_LENGTH, leere ausfiltern.
		$normalized_tags = array();
		foreach ( $tags as $tag ) {
			if ( ! is_string( $tag ) ) {
				continue;
			}
			$trimmed = mb_substr( $tag, 0, self::MAX_TAG_LENGTH );
			if ( '' === trim( $trimmed ) ) {
				continue;
			}
			$normalized_tags[] = $trimmed;
		}
		$this->tags = array_values( $normalized_tags );

		$this->category = ( null === $category )
			? null
			: mb_substr( $category, 0, self::MAX_CATEGORY_LENGTH );

		$this->meta = $meta;
	}

	/**
	 * Liefert Props in der Shape, die die ContentCard-Component
	 * ({@see public/views/components/content-card.php}) versteht.
	 *
	 * Mapping-Besonderheiten:
	 * - `tax_date` und `generic` werden auf ContentCard-`type` gemappt,
	 *   weil die Component nur news|video|document kennt:
	 *     - `tax_date` -> `document` (Discovery 10.4)
	 *     - `generic`  -> `news` (defensive default-Annahme)
	 * - Video-Items bekommen `data_attrs` fuer Player-Selector-Hooks
	 *   (TP-Pattern seit v0.14.3).
	 * - `link` (falls vorhanden) wird zu einer primaeren Action mit
	 *   Label "Mehr erfahren".
	 *
	 * @since 0.17.0
	 *
	 * @return array ContentCard-Prop-Array (kein `id`, das setzt der Aufrufer).
	 */
	public function to_content_card_props(): array {
		$card_type = $this->type;
		if ( 'tax_date' === $card_type ) {
			$card_type = 'document';
		} elseif ( 'generic' === $card_type ) {
			$card_type = 'news';
		}

		$props = array(
			'type'    => $card_type,
			'title'   => $this->title,
			'teaser'  => null !== $this->excerpt ? $this->excerpt : '',
			'service' => $this->service,
		);

		if ( '' !== $this->body ) {
			$props['body_html'] = $this->body;
		}

		if ( null !== $this->image && isset( $this->image['url'] ) ) {
			$props['media_url'] = (string) $this->image['url'];
			$props['media_alt'] = isset( $this->image['alt'] ) ? (string) $this->image['alt'] : '';
		}

		if ( null !== $this->link && '' !== $this->link ) {
			$props['actions'] = array(
				array(
					'label'   => __( 'Mehr erfahren', 'deubner_hp_services' ),
					'href'    => $this->link,
					'primary' => true,
				),
			);
		}

		// Video-Sondercase: media-slug zu data-attrs durchreichen.
		if ( 'video' === $this->type && null !== $this->media ) {
			$data_attrs = array();
			if ( isset( $this->media['slug'] ) && '' !== (string) $this->media['slug'] ) {
				$data_attrs['video-slug'] = (string) $this->media['slug'];
			}
			if ( isset( $this->media['poster'] ) && '' !== (string) $this->media['poster'] ) {
				$data_attrs['poster-url'] = (string) $this->media['poster'];
			}
			if ( ! empty( $data_attrs ) ) {
				$props['data_attrs'] = $data_attrs;
			}
		}

		return $props;
	}

	/**
	 * Liefert ein assoz. Array (Roundtrip-faehig fuer Cache/Tests).
	 *
	 * `$date` wird zu ISO-8601-String konvertiert (Risiko R10),
	 * sodass `from_array()` ohne Custom-Serializer rehydraten kann.
	 *
	 * @since 0.17.0
	 *
	 * @return array Vollstaendige, JSON-encode-faehige Repraesentation.
	 */
	public function to_array(): array {
		return array(
			'id'       => $this->id,
			'service'  => $this->service,
			'type'     => $this->type,
			'title'    => $this->title,
			'body'     => $this->body,
			'excerpt'  => $this->excerpt,
			'image'    => $this->image,
			'media'    => $this->media,
			'link'     => $this->link,
			'date'     => null !== $this->date ? $this->date->format( DateTimeInterface::ATOM ) : null,
			'tags'     => $this->tags,
			'category' => $this->category,
			'meta'     => $this->meta,
		);
	}

	/**
	 * Re-Hydration aus Array (fuer L2-Cache-Restore oder Test-Roundtrips).
	 *
	 * Fehlende optionale Felder werden auf ihre Defaults gesetzt. `date` wird
	 * via `new DateTimeImmutable()` re-parsed - bei ungueltigem ISO-String
	 * wirft DateTimeImmutable selbst eine Exception (gewollt: lieber sichtbar
	 * scheitern als stillen Null-Loss).
	 *
	 * @since 0.17.0
	 *
	 * @param array $data Array aus {@see to_array()} oder kompatibler Form.
	 *
	 * @return self Neue ContentItem-Instanz.
	 *
	 * @throws InvalidArgumentException Wenn Pflichtfelder fehlen / leer sind.
	 */
	public static function from_array( array $data ): self {
		$date = null;
		if ( isset( $data['date'] ) && is_string( $data['date'] ) && '' !== $data['date'] ) {
			// DateTimeImmutable wirft Exception bei ungueltigem String - gewollt.
			$date = new DateTimeImmutable( $data['date'] );
		}

		// Named-Args bewusst nicht genutzt, weil PHP-named-args-Aenderungen
		// in der Klassen-Signatur die from_array() sonst still brechen wuerden.
		return new self(
			isset( $data['id'] ) ? (string) $data['id'] : '',
			isset( $data['service'] ) ? (string) $data['service'] : '',
			isset( $data['title'] ) ? (string) $data['title'] : '',
			isset( $data['type'] ) ? (string) $data['type'] : 'generic',
			isset( $data['body'] ) ? (string) $data['body'] : '',
			isset( $data['excerpt'] ) ? (string) $data['excerpt'] : null,
			isset( $data['image'] ) && is_array( $data['image'] ) ? $data['image'] : null,
			isset( $data['media'] ) && is_array( $data['media'] ) ? $data['media'] : null,
			isset( $data['link'] ) ? (string) $data['link'] : null,
			$date,
			isset( $data['tags'] ) && is_array( $data['tags'] ) ? $data['tags'] : array(),
			isset( $data['category'] ) ? (string) $data['category'] : null,
			isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array()
		);
	}
}
