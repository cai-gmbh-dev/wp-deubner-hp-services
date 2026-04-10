/**
 * Deubner Homepage Services - TaxPlain Videos Frontend-JavaScript.
 *
 * - Lazy Loading: iframes erst bei Klick auf Poster laden
 * - AJAX-Proxy: Video-src wird serverseitig mit kdnr generiert
 * - Kategorie-Filter: Videos nach Rubrik filtern
 * - Compact-Accordion: Auf-/Zuklappen der Rubriken
 *
 * Keine externe Abhaengigkeit (kein jQuery).
 * OTA-Kundennummer wird NICHT im Client-Code verwendet.
 *
 * @package Deubner Homepage-Service
 * @since   0.9.1
 */

( function () {
	'use strict';

	function init() {
		document.querySelectorAll( '.dhps-service--tp' ).forEach( function ( container ) {
			var config = {
				ajaxUrl: container.getAttribute( 'data-ajax-url' ),
				nonce:   container.getAttribute( 'data-nonce' ),
			};

			initLazyVideoLoading( container, config );
			initCategoryFilter( container );
			initCompactAccordion( container );
		} );
	}

	/**
	 * Lazy Loading: Poster durch iframe ersetzen bei Klick.
	 *
	 * SICHERHEIT: Die iframe-src wird ueber einen AJAX-Proxy geladen,
	 * damit die kdnr nicht im Quelltext sichtbar ist.
	 */
	function initLazyVideoLoading( container, config ) {
		container.addEventListener( 'click', function ( e ) {
			var poster = e.target.closest( '[data-video-slug]' );

			if ( ! poster ) {
				return;
			}

			// Pruefe ob es ein Button/Poster-Element ist.
			var playerContainer = poster.closest( '.dhps-tp-video__player' ) ||
				poster.closest( '.dhps-tp-card__poster' ) ||
				poster;

			// Bereits geladen?
			if ( playerContainer.querySelector( 'iframe' ) ) {
				return;
			}

			var videoSlug = poster.getAttribute( 'data-video-slug' );
			var posterUrl = poster.getAttribute( 'data-poster-url' );
			var vModus    = poster.getAttribute( 'data-v-modus' ) || '0';

			if ( ! videoSlug ) {
				return;
			}

			loadVideoIframe( playerContainer, poster, videoSlug, posterUrl, vModus, config );
		} );

		// Keyboard-Support.
		container.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Enter' && e.key !== ' ' ) {
				return;
			}

			var poster = e.target.closest( '[data-video-slug]' );
			if ( poster ) {
				e.preventDefault();
				poster.click();
			}
		} );
	}

	/**
	 * Laedt den Video-iframe ueber den AJAX-Proxy.
	 */
	function loadVideoIframe( playerContainer, poster, videoSlug, posterUrl, vModus, config ) {
		var formData = new FormData();
		formData.append( 'action', 'dhps_tp_video_src' );
		formData.append( 'nonce', config.nonce );
		formData.append( 'video_slug', videoSlug );
		formData.append( 'poster_url', posterUrl );
		formData.append( 'v_modus', vModus );

		fetch( config.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		} )
			.then( function ( response ) { return response.json(); } )
			.then( function ( data ) {
				if ( data.success && data.data && data.data.src ) {
					var iframe = document.createElement( 'iframe' );
					iframe.className = 'dhps-tp-video__iframe';
					iframe.src = data.data.src;
					iframe.setAttribute( 'allowfullscreen', '' );
					iframe.setAttribute( 'title', poster.getAttribute( 'aria-label' ) || '' );
					iframe.style.border = '0';

					// Poster ausblenden.
					var posterEl = playerContainer.querySelector( '.dhps-tp-video__poster' ) ||
						playerContainer.querySelector( '.dhps-tp-card__poster' ) ||
						poster;

					if ( posterEl.classList.contains( 'dhps-tp-video__poster' ) ||
						posterEl.classList.contains( 'dhps-tp-card__poster' ) ) {
						posterEl.style.display = 'none';
					}

					// iframe einfuegen.
					var parent = posterEl.parentElement || playerContainer;
					parent.appendChild( iframe );
				}
			} )
			.catch( function () {
				// Stille Fehlerbehandlung - Poster bleibt sichtbar.
			} );
	}

	/**
	 * Kategorie-Filter: Zeigt/versteckt Video-Cards basierend auf Kategorie.
	 */
	function initCategoryFilter( container ) {
		// Shared Filter-Bar Klasse bevorzugen, Fallback auf Legacy-Klasse.
		var buttons = container.querySelectorAll( '.dhps-filter-bar__btn' );
		if ( 0 === buttons.length ) {
			buttons = container.querySelectorAll( '.dhps-tp-filter__btn' );
		}
		var cards = container.querySelectorAll( '.dhps-tp-card' );

		if ( 0 === buttons.length || 0 === cards.length ) {
			return;
		}

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var filter = this.getAttribute( 'data-filter' );

				// Button-States aktualisieren (beide Klassennamen).
				buttons.forEach( function ( b ) {
					b.classList.remove( 'dhps-filter-bar__btn--active' );
					b.classList.remove( 'dhps-tp-filter__btn--active' );
					b.setAttribute( 'aria-pressed', 'false' );
				} );
				this.classList.add( 'dhps-filter-bar__btn--active' );
				this.classList.add( 'dhps-tp-filter__btn--active' );
				this.setAttribute( 'aria-pressed', 'true' );

				// Cards filtern.
				cards.forEach( function ( card ) {
					if ( filter === 'all' || card.getAttribute( 'data-category' ) === filter ) {
						card.style.display = '';
						card.removeAttribute( 'hidden' );
					} else {
						card.style.display = 'none';
						card.setAttribute( 'hidden', '' );
					}
				} );
			} );
		} );
	}

	/**
	 * Compact-Accordion: Auf-/Zuklappen der Rubriken im Kompakt-Layout.
	 */
	function initCompactAccordion( container ) {
		var triggers = container.querySelectorAll( '.dhps-tp-compact__trigger' );

		triggers.forEach( function ( trigger ) {
			trigger.addEventListener( 'click', function () {
				var expanded  = this.getAttribute( 'aria-expanded' ) === 'true';
				var contentId = this.getAttribute( 'aria-controls' );
				var content   = document.getElementById( contentId );

				this.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );

				if ( content ) {
					content.setAttribute( 'aria-hidden', expanded ? 'true' : 'false' );
				}
			} );
		} );

		// Compact video button clicks.
		container.addEventListener( 'click', function ( e ) {
			var videoBtn = e.target.closest( '.dhps-tp-compact__video-btn' );
			if ( ! videoBtn ) {
				return;
			}

			var item = videoBtn.closest( '.dhps-tp-compact__item' );
			if ( ! item ) {
				return;
			}

			var videoSlug = item.getAttribute( 'data-video-slug' );
			var posterUrl = item.getAttribute( 'data-poster-url' );
			var vModus    = item.getAttribute( 'data-v-modus' ) || '0';

			if ( ! videoSlug ) {
				return;
			}

			// Existierenden Video-Player in dieser Liste entfernen.
			var list = item.closest( '.dhps-tp-compact__list' );
			if ( list ) {
				var existing = list.querySelectorAll( '.dhps-tp-compact__player' );
				existing.forEach( function ( el ) { el.remove(); } );
			}

			// Neuen Video-Player unter dem Item erstellen.
			var playerDiv = document.createElement( 'div' );
			playerDiv.className = 'dhps-tp-compact__player';
			playerDiv.innerHTML =
				'<div class="dhps-tp-video__player">' +
				'<div class="dhps-tp-video__poster" data-video-slug="' + escapeAttr( videoSlug ) + '"' +
				' data-poster-url="' + escapeAttr( posterUrl ) + '"' +
				' data-v-modus="' + escapeAttr( vModus ) + '">' +
				'<span class="dhps-news__loading"><span class="dhps-news__spinner" aria-hidden="true"></span></span>' +
				'</div></div>';

			item.after( playerDiv );

			// iframe direkt laden.
			var config = {
				ajaxUrl: container.getAttribute( 'data-ajax-url' ),
				nonce:   container.getAttribute( 'data-nonce' ),
			};

			var posterEl = playerDiv.querySelector( '.dhps-tp-video__poster' );
			loadVideoIframe( playerDiv.querySelector( '.dhps-tp-video__player' ), posterEl, videoSlug, posterUrl, vModus, config );
		} );
	}

	function escapeAttr( str ) {
		return str
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	// Initialisieren wenn DOM bereit.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
