<?php
/**
 * MAES Aktuelles Compact Template - Minimale News-Liste.
 *
 * Kompakte Variante fuer Sidebar: nur Titel, Klick klappt auf.
 *
 * Verfuegbare Variablen:
 *   $news         - Array der News-Artikel aus DHPS_MAES_Parser.
 *   $custom_class - Optionale CSS-Klasse.
 *   $show_teaser  - Optionaler Teaser unter Titel (default: false).
 *   $first_open   - Ersten Artikel geoeffnet (default: false).
 *
 * @package Deubner Homepage-Service
 * @since   0.10.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $news ) ) {
	return;
}

$show_teaser = $show_teaser ?? false;
$first_open  = $first_open ?? false;
?>
<div class="dhps-service dhps-service--maes-aktuelles dhps-service--maes-aktuelles-compact<?php echo esc_attr( $custom_class ); ?>">

	<ul class="dhps-news__compact-list">
		<?php foreach ( $news as $idx => $article ) :
			$body_id = 'dhps-' . esc_attr( $article['id'] );
			$is_open = $first_open && 0 === $idx;
		?>
		<li class="dhps-news__compact-item">
			<button type="button"
					class="dhps-news__title dhps-news__compact-title"
					aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
					aria-controls="<?php echo esc_attr( $body_id ); ?>"
					data-dhps-toggle="<?php echo esc_attr( $body_id ); ?>">
				<?php echo esc_html( $article['title'] ); ?>
			</button>

			<?php if ( $show_teaser && ! empty( $article['teaser'] ) ) : ?>
			<span class="dhps-news__compact-teaser"><?php echo esc_html( $article['teaser'] ); ?></span>
			<?php endif; ?>

			<div class="dhps-news__body dhps-news__compact-body"
				 id="<?php echo esc_attr( $body_id ); ?>"
				 aria-hidden="<?php echo $is_open ? 'false' : 'true'; ?>">
				<?php if ( ! empty( $article['body_html'] ) ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML aus vertrauenswuerdiger Deubner-API.
				echo $article['body_html'];
				?>
				<?php endif; ?>
				<div class="dhps-news__actions">
					<button type="button" class="dhps-news__action-link" data-dhps-collapse="<?php echo esc_attr( $body_id ); ?>">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<polyline points="18 15 12 9 6 15"/>
						</svg>
						<?php echo esc_html( 'Ausblenden' ); ?>
					</button>
				</div>
			</div>
		</li>
		<?php endforeach; ?>
	</ul>

</div>
<script>
(function(){
	document.querySelectorAll('.dhps-service--maes-aktuelles-compact').forEach(function(c){
		c.addEventListener('click',function(e){
			var t=e.target.closest('[data-dhps-toggle]');
			if(t){var id=t.getAttribute('data-dhps-toggle'),b=document.getElementById(id);
				if(b){var x=t.getAttribute('aria-expanded')==='true';
					t.setAttribute('aria-expanded',x?'false':'true');
					b.setAttribute('aria-hidden',x?'true':'false');}return;}
			var col=e.target.closest('[data-dhps-collapse]');
			if(col){var cid=col.getAttribute('data-dhps-collapse'),cb=document.getElementById(cid);
				if(cb){cb.setAttribute('aria-hidden','true');
					var rt=c.querySelector('[data-dhps-toggle="'+cid+'"]');
					if(rt)rt.setAttribute('aria-expanded','false');
					rt&&rt.scrollIntoView({behavior:'smooth',block:'nearest'});}return;}
		});
	});
})();
</script>
