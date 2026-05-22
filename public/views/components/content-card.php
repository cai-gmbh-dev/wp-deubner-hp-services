<?php
/**
 * Component: ContentCard (stateful via Alpine optional)
 *
 * Universelle Karte fuer News / Video / Document mit optionalem
 * Expand-Toggle (collapsible body). Wenn $collapsible=true gesetzt ist,
 * wird die Alpine-Komponente dhpsContentCard initialisiert (open/toggle).
 * Andernfalls reines stateless HTML.
 *
 * Props (per extract() im Registry-Renderer):
 *
 *   string      $type        'news' | 'video' | 'document'   default 'news'
 *   string      $title       Hauptueberschrift               Pflicht
 *   string      $teaser      Kurzbeschreibung                default ''
 *   string|null $body_html   Erweiterter Inhalt (HTML)       default null
 *   string|null $media_url   Bild/Video-Poster-URL           default null
 *   string      $media_alt   Alt-Text fuer Media             default ''
 *   array       $badges      [ ['label'=>..., 'variant'=>'neu'|'aktion'|'top'] ]
 *   array       $meta        [ ['icon'=>slug, 'text'=>string] ]
 *   array       $actions     [ ['label'=>..., 'href'=>..., 'icon'=>slug, 'primary'=>bool] ]
 *   bool        $collapsible default false; wenn true: Alpine-Toggle fuer body_html
 *   string      $class       Zusaetzliche Root-Klassen       default ''
 *   string      $service     Optionaler Service-Slug (Branding-Hook) default ''
 *
 * Aufruf-Beispiel:
 *
 *   echo dhps_component( 'content-card', array(
 *       'type'  => 'video',
 *       'title' => 'Video XY',
 *       'media_url' => 'https://.../poster.jpg',
 *       'badges' => array( array( 'label'=>'NEU', 'variant'=>'neu' ) ),
 *       'actions' => array( array( 'label'=>'Abspielen', 'href'=>'#', 'primary'=>true ) ),
 *   ) );
 *
 * A11y:
 *   - Titel als h3 (Heading-Level konfigurierbar via Filter dhps_content_card_heading_level).
 *   - Toggle-Button: aria-expanded an open gebunden, aria-controls referenziert Detail-ID.
 *   - Meta-Icons aria-hidden (Text bleibt sichtbar fuer Screen-Reader).
 *   - Image-Alt vom Prop $media_alt (leerer String => decorative).
 *
 * @package Deubner Homepage-Service
 * @since   0.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---- Prop-Normalisierung ----
$type        = isset( $type ) && is_string( $type ) ? $type : 'news';
$allowed_t   = array( 'news', 'video', 'document' );
if ( ! in_array( $type, $allowed_t, true ) ) {
	$type = 'news';
}
$title       = isset( $title ) && is_string( $title ) ? $title : '';
$teaser      = isset( $teaser ) && is_string( $teaser ) ? $teaser : '';
$body_html   = isset( $body_html ) && is_string( $body_html ) ? $body_html : '';
$media_url   = isset( $media_url ) && is_string( $media_url ) && '' !== $media_url ? $media_url : '';
$media_alt   = isset( $media_alt ) && is_string( $media_alt ) ? $media_alt : '';
$badges      = isset( $badges ) && is_array( $badges ) ? $badges : array();
$meta        = isset( $meta ) && is_array( $meta ) ? $meta : array();
$actions     = isset( $actions ) && is_array( $actions ) ? $actions : array();
$collapsible = isset( $collapsible ) ? (bool) $collapsible : false;
$class       = isset( $class ) && is_string( $class ) ? $class : '';
$service     = isset( $service ) && is_string( $service ) ? sanitize_html_class( $service ) : '';

// Pflichtfeld pruefen.
if ( '' === $title ) {
	return;
}

// Filterbare Heading-Stufe (default h3).
$heading_tag = apply_filters( 'dhps_content_card_heading_level', 'h3', $type );
$allowed_h   = array( 'h2', 'h3', 'h4', 'h5', 'h6' );
if ( ! in_array( $heading_tag, $allowed_h, true ) ) {
	$heading_tag = 'h3';
}

// Klassen-Aufbau.
$root_classes = 'dhps-content-card dhps-content-card--' . $type;
if ( '' !== $service ) {
	$root_classes .= ' dhps-content-card--service-' . $service;
}
if ( '' !== $class ) {
	$root_classes .= ' ' . $class;
}

$has_body         = '' !== $body_html;
$body_id          = 'dhps-card-body-' . wp_unique_id();
$use_collapsible  = $collapsible && $has_body;

// Icon-Map fuer Meta/Action-Icons (klein gehalten).
$meta_icons = array(
	'calendar' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
	'clock'    => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
	'file'     => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
	'download' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
	'play'     => '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" stroke="none" aria-hidden="true" focusable="false"><polygon points="6 4 20 12 6 20 6 4"/></svg>',
	'link'     => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
);

// Badge-Variant-Whitelist.
$badge_variants = array( 'neu', 'aktion', 'top', 'default' );

// Alpine-Attribute (nur wenn collapsible).
// Statisch zusammengesetzter String aus zwei konstanten Literalen, KEINE User-Daten,
// KEINE dynamischen Interpolationen. Kein Escape noetig (siehe Audit S-2).
$alpine_attrs = '';
if ( $use_collapsible ) {
	$alpine_attrs = ' x-data="dhpsContentCard()" x-cloak';
}
?>
<article class="<?php echo esc_attr( $root_classes ); ?>"<?php echo $alpine_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $alpine_attrs ist konstante Literal-Verkettung (siehe oben), keine User-Daten interpolierbar. ?>>

	<?php if ( '' !== $media_url ) : ?>
		<div class="dhps-content-card__media">
			<?php
			// Versuche LazyImage zu nutzen, falls verfuegbar; Fallback: einfaches <img>.
			if ( function_exists( 'dhps_component' ) && class_exists( 'DHPS_Component_Registry' ) && DHPS_Component_Registry::is_registered( 'lazy-image' ) ) {
				echo dhps_component( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Component liefert escapten HTML.
					'lazy-image',
					array(
						'src'   => $media_url,
						'alt'   => $media_alt,
						'class' => 'dhps-content-card__image',
					)
				);
			} else {
				?>
				<img
					class="dhps-content-card__image"
					loading="lazy"
					decoding="async"
					src="<?php echo esc_url( $media_url ); ?>"
					alt="<?php echo esc_attr( $media_alt ); ?>"
				>
				<?php
			}
			if ( 'video' === $type ) :
				?>
				<span class="dhps-content-card__play-overlay" aria-hidden="true">
					<?php echo $meta_icons['play']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internes SVG. ?>
				</span>
				<?php
			endif;
			?>
		</div>
	<?php endif; ?>

	<div class="dhps-content-card__body">
		<header class="dhps-content-card__header">
			<<?php echo esc_attr( $heading_tag ); ?> class="dhps-content-card__title">
				<?php echo esc_html( $title ); ?>
			</<?php echo esc_attr( $heading_tag ); ?>>

			<?php if ( ! empty( $badges ) ) : ?>
				<ul class="dhps-content-card__badges">
					<?php foreach ( $badges as $b ) :
						if ( ! is_array( $b ) || empty( $b['label'] ) ) {
							continue;
						}
						$b_label   = (string) $b['label'];
						$b_variant = isset( $b['variant'] ) ? (string) $b['variant'] : 'default';
						if ( ! in_array( $b_variant, $badge_variants, true ) ) {
							$b_variant = 'default';
						}
						?>
						<li class="dhps-content-card__badge dhps-content-card__badge--<?php echo esc_attr( $b_variant ); ?>">
							<?php echo esc_html( $b_label ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</header>

		<?php if ( '' !== $teaser ) : ?>
			<p class="dhps-content-card__teaser"><?php echo esc_html( $teaser ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $meta ) ) : ?>
			<ul class="dhps-content-card__meta">
				<?php foreach ( $meta as $m ) :
					if ( ! is_array( $m ) || empty( $m['text'] ) ) {
						continue;
					}
					$m_text = (string) $m['text'];
					$m_icon = isset( $m['icon'] ) ? (string) $m['icon'] : '';
					?>
					<li class="dhps-content-card__meta-item">
						<?php if ( '' !== $m_icon && isset( $meta_icons[ $m_icon ] ) ) : ?>
							<span class="dhps-content-card__meta-icon" aria-hidden="true"><?php echo $meta_icons[ $m_icon ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internes SVG. ?></span>
						<?php endif; ?>
						<span class="dhps-content-card__meta-text"><?php echo esc_html( $m_text ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $has_body && $use_collapsible ) : ?>
			<button
				type="button"
				class="dhps-content-card__toggle"
				x-on:click="toggle()"
				:aria-expanded="open ? 'true' : 'false'"
				aria-controls="<?php echo esc_attr( $body_id ); ?>"
			>
				<span class="dhps-content-card__toggle-label" x-text="open ? '<?php echo esc_js( __( 'Weniger anzeigen', 'wp-deubner-hp-services' ) ); ?>' : '<?php echo esc_js( __( 'Mehr erfahren', 'wp-deubner-hp-services' ) ); ?>'">
					<?php esc_html_e( 'Mehr erfahren', 'wp-deubner-hp-services' ); ?>
				</span>
				<svg class="dhps-content-card__toggle-chevron" :class="open ? 'is-open' : ''" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg>
			</button>
			<div
				id="<?php echo esc_attr( $body_id ); ?>"
				class="dhps-content-card__detail"
				x-show="open"
				x-transition.duration.200ms
				x-cloak
			>
				<?php echo wp_kses_post( $body_html ); ?>
			</div>
		<?php elseif ( $has_body ) : ?>
			<div class="dhps-content-card__detail">
				<?php echo wp_kses_post( $body_html ); ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $actions ) ) : ?>
			<footer class="dhps-content-card__actions">
				<?php foreach ( $actions as $a ) :
					if ( ! is_array( $a ) || empty( $a['label'] ) ) {
						continue;
					}
					$a_label   = (string) $a['label'];
					$a_href    = isset( $a['href'] ) ? (string) $a['href'] : '#';
					$a_icon    = isset( $a['icon'] ) ? (string) $a['icon'] : '';
					$a_primary = ! empty( $a['primary'] );
					$a_target  = ! empty( $a['target'] ) ? (string) $a['target'] : '';
					$a_classes = 'dhps-content-card__action';
					$a_classes .= $a_primary ? ' dhps-content-card__action--primary' : ' dhps-content-card__action--secondary';
					?>
					<a
						class="<?php echo esc_attr( $a_classes ); ?>"
						href="<?php echo esc_url( $a_href ); ?>"
						<?php if ( '_blank' === $a_target ) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
					>
						<?php if ( '' !== $a_icon && isset( $meta_icons[ $a_icon ] ) ) : ?>
							<span class="dhps-content-card__action-icon" aria-hidden="true"><?php echo $meta_icons[ $a_icon ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internes SVG. ?></span>
						<?php endif; ?>
						<span class="dhps-content-card__action-label"><?php echo esc_html( $a_label ); ?></span>
					</a>
				<?php endforeach; ?>
			</footer>
		<?php endif; ?>
	</div>
</article>
