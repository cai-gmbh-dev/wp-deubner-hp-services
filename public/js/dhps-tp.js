/**
 * Deubner Homepage Services - TaxPlain Videos Frontend-JavaScript.
 *
 * - Lazy Loading: iframes erst bei Klick auf Poster laden
 * - AJAX-Proxy: Video-src wird serverseitig mit kdnr generiert
 * - Kategorie-Filter: Videos nach Rubrik filtern
 * - Compact-Accordion: Auf-/Zuklappen der Rubriken
 * - Load More: Schrittweises Einblenden weiterer Video-Cards (manuell/auto)
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
			initLazyLoadMore( container );
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
	 *
	 * Wenn der uebergeordnete .dhps-service--tp Container das Attribut
	 * data-video-mode="modal" hat, wird das Video in einem zentrierten
	 * Fullscreen-Overlay-Modal geoeffnet statt inline den Poster zu ersetzen.
	 */
	function loadVideoIframe( playerContainer, poster, videoSlug, posterUrl, vModus, config ) {
		// Pruefen ob Modal-Modus aktiv ist.
		var serviceContainer = playerContainer.closest( '.dhps-service--tp' );
		var videoMode = serviceContainer ? serviceContainer.getAttribute( 'data-video-mode' ) : null;
		var useModal  = ( videoMode === 'modal' );

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
					if ( useModal ) {
						var title = poster.getAttribute( 'aria-label' ) || 'Video';
						openVideoModal( data.data.src, title );
						return;
					}

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

	/* ======================================================================
	   Video-Modal: Fullscreen-Overlay fuer Video-Wiedergabe.
	   ====================================================================== */

	/** @type {HTMLElement|null} Singleton-Modal-Element. */
	var videoModal = null;

	/** @type {HTMLElement|null} Element das vor dem Modal-Open Fokus hatte. */
	var previousFocus = null;

	/**
	 * Erstellt das Modal-DOM (einmalig) und gibt es zurueck.
	 *
	 * @return {HTMLElement} Das .dhps-video-modal Element.
	 */
	function getOrCreateModal() {
		if ( videoModal ) {
			return videoModal;
		}

		videoModal = document.createElement( 'div' );
		videoModal.className = 'dhps-video-modal';
		videoModal.setAttribute( 'role', 'dialog' );
		videoModal.setAttribute( 'aria-modal', 'true' );
		videoModal.setAttribute( 'aria-label', 'Video' );

		videoModal.innerHTML =
			'<div class="dhps-video-modal__overlay"></div>' +
			'<div class="dhps-video-modal__content">' +
				'<button class="dhps-video-modal__close" aria-label="Schliessen">&times;</button>' +
				'<div class="dhps-video-modal__player"></div>' +
			'</div>';

		document.body.appendChild( videoModal );

		// Schliessen per Klick auf Overlay.
		videoModal.querySelector( '.dhps-video-modal__overlay' ).addEventListener( 'click', closeVideoModal );

		// Schliessen per Close-Button.
		videoModal.querySelector( '.dhps-video-modal__close' ).addEventListener( 'click', closeVideoModal );

		// Keyboard: Escape + Focus-Trap.
		videoModal.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				e.stopPropagation();
				closeVideoModal();
				return;
			}

			// Focus-Trap: Tab innerhalb des Modals halten.
			if ( e.key === 'Tab' ) {
				var focusable = videoModal.querySelectorAll(
					'button, [href], iframe, [tabindex]:not([tabindex="-1"])'
				);
				if ( focusable.length === 0 ) {
					return;
				}
				var first = focusable[0];
				var last  = focusable[ focusable.length - 1 ];

				if ( e.shiftKey ) {
					if ( document.activeElement === first ) {
						e.preventDefault();
						last.focus();
					}
				} else {
					if ( document.activeElement === last ) {
						e.preventDefault();
						first.focus();
					}
				}
			}
		} );

		return videoModal;
	}

	/**
	 * Oeffnet das Video-Modal mit dem angegebenen iframe-src.
	 *
	 * @param {string} iframeSrc - URL fuer den Video-iframe.
	 * @param {string} title     - Titel fuer das iframe-title-Attribut.
	 */
	function openVideoModal( iframeSrc, title ) {
		var modal  = getOrCreateModal();
		var player = modal.querySelector( '.dhps-video-modal__player' );

		// Vorherigen iframe entfernen.
		player.innerHTML = '';

		var iframe = document.createElement( 'iframe' );
		iframe.src = iframeSrc;
		iframe.setAttribute( 'allowfullscreen', '' );
		iframe.setAttribute( 'title', title || 'Video' );
		player.appendChild( iframe );

		// Fokus merken und Modal anzeigen.
		previousFocus = document.activeElement;
		modal.classList.add( 'dhps-video-modal--active' );
		document.body.style.overflow = 'hidden';

		// Fokus auf Close-Button setzen.
		var closeBtn = modal.querySelector( '.dhps-video-modal__close' );
		if ( closeBtn ) {
			closeBtn.focus();
		}
	}

	/**
	 * Schliesst das Video-Modal und raeumt auf.
	 */
	function closeVideoModal() {
		if ( ! videoModal ) {
			return;
		}

		videoModal.classList.remove( 'dhps-video-modal--active' );
		document.body.style.overflow = '';

		// iframe entfernen (Video stoppen).
		var player = videoModal.querySelector( '.dhps-video-modal__player' );
		if ( player ) {
			player.innerHTML = '';
		}

		// Fokus zuruecksetzen.
		if ( previousFocus && typeof previousFocus.focus === 'function' ) {
			previousFocus.focus();
		}
		previousFocus = null;
	}

	/**
	 * Kategorie-Filter: Zeigt/versteckt Video-Cards basierend auf Kategorie.
	 *
	 * Nach dem Filtern wird der Lazy-Load-Zustand zurueckgesetzt:
	 * Von den sichtbaren (nicht ausgefilterten) Cards werden nur die
	 * ersten N angezeigt, der Rest wird wieder versteckt.
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

				// Lazy-Load-Zustand nach Filterwechsel zuruecksetzen.
				resetLazyLoadAfterFilter( container );
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

	/**
	 * Load More: Schrittweises Einblenden weiterer Video-Cards.
	 *
	 * Liest data-lazy-count und data-lazy-mode vom Container.
	 * - lazy-count=0 : Alle Cards sichtbar, nichts zu tun.
	 * - lazy-mode=manual : Button-Klick blendet naechste Charge ein.
	 * - lazy-mode=auto   : IntersectionObserver blendet automatisch ein.
	 */
	function initLazyLoadMore( container ) {
		var lazyCount = parseInt( container.getAttribute( 'data-lazy-count' ) || '0', 10 );
		var lazyMode  = container.getAttribute( 'data-lazy-mode' ) || 'manual';

		if ( lazyCount <= 0 ) {
			return; // Alle sichtbar, nichts zu tun.
		}

		var loadMoreBtn = container.querySelector( '.dhps-tp-load-more' );

		if ( lazyMode === 'manual' && loadMoreBtn ) {
			loadMoreBtn.addEventListener( 'click', function () {
				showNextBatch( container, lazyCount );
			} );
		}

		if ( lazyMode === 'auto' ) {
			// Button verstecken, IntersectionObserver nutzen.
			if ( loadMoreBtn ) {
				loadMoreBtn.style.display = 'none';
			}
			setupAutoLoad( container, lazyCount );
		}
	}

	/**
	 * Zeigt die naechste Charge versteckter Cards an.
	 *
	 * Sucht alle aktuell versteckten (lazy-hidden) Cards, die nicht
	 * durch den Kategorie-Filter ausgeblendet sind, und macht die
	 * naechsten N sichtbar.
	 *
	 * @param {Element} container - Der .dhps-service--tp Container.
	 * @param {number}  batchSize - Anzahl der Cards pro Charge.
	 */
	function showNextBatch( container, batchSize ) {
		var hiddenCards = getFilteredHiddenCards( container );
		var count       = Math.min( batchSize, hiddenCards.length );

		for ( var i = 0; i < count; i++ ) {
			hiddenCards[ i ].classList.remove( 'dhps-tp-card--lazy-hidden' );
			hiddenCards[ i ].removeAttribute( 'hidden' );
		}

		// Pruefen ob noch versteckte Cards uebrig sind.
		var remaining = getFilteredHiddenCards( container );
		if ( remaining.length === 0 ) {
			hideLoadMoreButton( container );
		}
	}

	/**
	 * Gibt alle lazy-hidden Cards zurueck, die nicht durch den
	 * Kategorie-Filter ausgeblendet sind.
	 *
	 * Cards, die durch den Filter display:none haben, werden
	 * uebersprungen - sie sind bereits kategorie-gefiltert.
	 *
	 * @param {Element} container - Der .dhps-service--tp Container.
	 * @return {Array} Array von lazy-hidden Card-Elementen.
	 */
	function getFilteredHiddenCards( container ) {
		var allHidden = container.querySelectorAll( '.dhps-tp-card--lazy-hidden' );
		var result    = [];

		allHidden.forEach( function ( card ) {
			// Nur Cards die nicht durch Kategorie-Filter ausgeblendet sind.
			if ( card.style.display !== 'none' ) {
				result.push( card );
			}
		} );

		return result;
	}

	/**
	 * Versteckt den "Weitere laden"-Button.
	 *
	 * @param {Element} container - Der .dhps-service--tp Container.
	 */
	function hideLoadMoreButton( container ) {
		var btn = container.querySelector( '.dhps-tp-load-more' );
		if ( btn ) {
			btn.style.display = 'none';
		}
	}

	/**
	 * Zeigt den "Weitere laden"-Button wieder an.
	 *
	 * @param {Element} container - Der .dhps-service--tp Container.
	 */
	function showLoadMoreButton( container ) {
		var lazyMode = container.getAttribute( 'data-lazy-mode' ) || 'manual';
		if ( lazyMode !== 'manual' ) {
			return; // Im Auto-Modus bleibt der Button versteckt.
		}

		var btn = container.querySelector( '.dhps-tp-load-more' );
		if ( btn ) {
			btn.style.display = '';
		}
	}

	/**
	 * Richtet den IntersectionObserver fuer den Auto-Modus ein.
	 *
	 * Beobachtet ein Sentinel-Element (.dhps-tp-lazy-sentinel) oder
	 * erstellt eines falls nicht vorhanden. Wenn die letzte sichtbare
	 * Card in den Viewport kommt, wird die naechste Charge geladen.
	 *
	 * @param {Element} container - Der .dhps-service--tp Container.
	 * @param {number}  batchSize - Anzahl der Cards pro Charge.
	 */
	function setupAutoLoad( container, batchSize ) {
		var sentinel = container.querySelector( '.dhps-tp-lazy-sentinel' );

		// Sentinel erstellen falls nicht vorhanden.
		if ( ! sentinel ) {
			sentinel = document.createElement( 'div' );
			sentinel.className = 'dhps-tp-lazy-sentinel';
			sentinel.setAttribute( 'aria-hidden', 'true' );

			var grid = container.querySelector( '.dhps-tp-grid' ) ||
				container.querySelector( '.dhps-tp-cards' );

			if ( grid ) {
				grid.parentNode.insertBefore( sentinel, grid.nextSibling );
			} else {
				container.appendChild( sentinel );
			}
		}

		// Observer speichern fuer spaeteres Disconnect bei Filter-Reset.
		if ( container._dhpsLazyObserver ) {
			container._dhpsLazyObserver.disconnect();
		}

		// Throttle: Nur einen Batch pro Intersection-Cycle laden.
		// Bekannte Limitation: Bei mehreren TP-Widgets auf einer Seite
		// kann der Auto-Modus durch gleichzeitige Observer-Triggers alle
		// Batches in schneller Folge laden. Empfehlung: Bei Mehrfachnutzung
		// den manuellen Modus verwenden.
		var isLoading = false;

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting || isLoading ) {
					return;
				}

				var remaining = getFilteredHiddenCards( container );
				if ( remaining.length === 0 ) {
					observer.disconnect();
					return;
				}

				isLoading = true;
				showNextBatch( container, batchSize );

				// Kurze Pause vor dem naechsten Batch (verhindert Burst-Loading).
				setTimeout( function () {
					isLoading = false;

					if ( getFilteredHiddenCards( container ).length === 0 ) {
						observer.disconnect();
					}
				}, 150 );
			} );
		}, {
			root: null,
			rootMargin: '100px',
			threshold: 0,
		} );

		observer.observe( sentinel );
		container._dhpsLazyObserver = observer;
	}

	/**
	 * Setzt den Lazy-Load-Zustand nach einem Kategorie-Filterwechsel zurueck.
	 *
	 * Alle Cards werden zunaechst als lazy-hidden markiert. Dann werden
	 * von den sichtbaren (nicht ausgefilterten) Cards die ersten N
	 * eingeblendet, der Rest bleibt versteckt.
	 *
	 * @param {Element} container - Der .dhps-service--tp Container.
	 */
	function resetLazyLoadAfterFilter( container ) {
		var lazyCount = parseInt( container.getAttribute( 'data-lazy-count' ) || '0', 10 );
		var lazyMode  = container.getAttribute( 'data-lazy-mode' ) || 'manual';

		if ( lazyCount <= 0 ) {
			return; // Kein Lazy Loading aktiv.
		}

		var allCards    = container.querySelectorAll( '.dhps-tp-card' );
		var visibleIdx  = 0;

		allCards.forEach( function ( card ) {
			// Ausgefilterte Cards ueberspringen (durch Kategorie-Filter).
			if ( card.style.display === 'none' ) {
				return;
			}

			visibleIdx++;

			if ( visibleIdx <= lazyCount ) {
				// Erste N sichtbare Cards einblenden.
				card.classList.remove( 'dhps-tp-card--lazy-hidden' );
				card.removeAttribute( 'hidden' );
			} else {
				// Rest wieder verstecken.
				card.classList.add( 'dhps-tp-card--lazy-hidden' );
				card.setAttribute( 'hidden', '' );
			}
		} );

		// Button-Zustand aktualisieren.
		var remaining = getFilteredHiddenCards( container );
		if ( remaining.length > 0 ) {
			showLoadMoreButton( container );
		} else {
			hideLoadMoreButton( container );
		}

		// Auto-Modus: Observer neu starten.
		if ( lazyMode === 'auto' && remaining.length > 0 ) {
			setupAutoLoad( container, lazyCount );
		}
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
