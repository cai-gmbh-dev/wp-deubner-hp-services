/**
 * Deubner Homepage Services - MMB Frontend-JavaScript.
 *
 * - Doppeltes Accordion (Rubrik -> Merkblatt) mit ARIA
 * - AJAX-Suche ueber WordPress admin-ajax.php (serverseitiger Proxy)
 * - PDF-Download ueber Proxy (keine kdnr im Client)
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
		var containers = document.querySelectorAll( '[data-dhps-mmb-categories]' );

		containers.forEach( function ( container ) {
			initInstance( container );
		} );
	}

	/**
	 * Initialisiert eine MMB-Instanz.
	 */
	function initInstance( container ) {
		var config = {
			ajaxUrl:    container.getAttribute( 'data-ajax-url' ),
			nonce:      container.getAttribute( 'data-nonce' ),
			serviceTag: container.getAttribute( 'data-service-tag' ),
		};

		var serviceWrapper = container.closest( '.dhps-service' );
		if ( ! serviceWrapper ) {
			return;
		}

		// Rubrik-Accordion initialisieren.
		initCategoryAccordion( container );

		// Merkblatt-Accordion initialisieren (Event-Delegation).
		initItemAccordion( container );

		// Suchfunktion initialisieren.
		initSearch( serviceWrapper, container, config );

		// Filter-Bar initialisieren (nur in card/compact, nicht default).
		initFilterBar( serviceWrapper, container );
	}

	/**
	 * Rubrik-Accordion: Auf-/Zuklappen der Rubriken.
	 */
	function initCategoryAccordion( container ) {
		var triggers = container.querySelectorAll( '[data-dhps-mmb-category-toggle]' );

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
	}

	/**
	 * Merkblatt-Accordion: Auf-/Zuklappen einzelner Merkblaetter.
	 * Nutzt Event-Delegation auf dem Container.
	 */
	function initItemAccordion( container ) {
		container.addEventListener( 'click', function ( e ) {
			var toggleBtn = e.target.closest( '[data-dhps-mmb-item-toggle]' );
			if ( toggleBtn ) {
				var expanded = toggleBtn.getAttribute( 'aria-expanded' ) === 'true';
				var detailId = toggleBtn.getAttribute( 'aria-controls' );
				var detail   = document.getElementById( detailId );

				toggleBtn.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );

				if ( detail ) {
					detail.setAttribute( 'aria-hidden', expanded ? 'true' : 'false' );
				}
				return;
			}

			var collapseBtn = e.target.closest( '[data-dhps-mmb-item-collapse]' );
			if ( collapseBtn ) {
				var bodyId  = collapseBtn.getAttribute( 'data-dhps-mmb-item-collapse' );
				var body    = document.getElementById( bodyId );
				var toggler = container.querySelector( '[aria-controls="' + bodyId + '"]' );

				if ( toggler ) {
					toggler.setAttribute( 'aria-expanded', 'false' );
				}
				if ( body ) {
					body.setAttribute( 'aria-hidden', 'true' );
				}
			}
		} );
	}

	/**
	 * Such-Initialisierung.
	 */
	function initSearch( wrapper, container, config ) {
		var searchForm  = wrapper.querySelector( '[data-dhps-mmb-search]' );
		var searchInput = wrapper.querySelector( '[data-dhps-mmb-search-input]' );
		var resetBtn    = wrapper.querySelector( '[data-dhps-mmb-search-reset]' );
		var resultsDiv  = wrapper.querySelector( '[data-dhps-mmb-results]' );
		var filterBar   = wrapper.querySelector( '[data-dhps-mmb-filter-bar]' );

		if ( ! searchForm || ! searchInput ) {
			return;
		}

		searchForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var query = searchInput.value.trim();

			if ( '' === query ) {
				return;
			}

			// Filter-Bar waehrend Suche ausblenden.
			if ( filterBar ) {
				filterBar.hidden = true;
			}

			performSearch( query, config, resultsDiv, container, resetBtn );
		} );

		if ( resetBtn ) {
			resetBtn.addEventListener( 'click', function () {
				searchInput.value = '';
				resetBtn.hidden = true;

				if ( resultsDiv ) {
					resultsDiv.hidden = true;
					resultsDiv.innerHTML = '';
				}

				container.hidden = false;

				// Filter-Bar wieder einblenden.
				if ( filterBar ) {
					filterBar.hidden = false;
				}
			} );
		}
	}

	/**
	 * Fuehrt die AJAX-Suche durch.
	 */
	function performSearch( query, config, resultsDiv, categoriesDiv, resetBtn ) {
		if ( ! resultsDiv ) {
			return;
		}

		categoriesDiv.hidden = true;
		resultsDiv.hidden = false;
		resultsDiv.innerHTML =
			'<div class="dhps-mmb-results__loading">' +
			'<span class="dhps-mmb-results__spinner" aria-hidden="true"></span>' +
			'<span class="screen-reader-text">Suchergebnisse werden geladen...</span>' +
			'</div>';

		if ( resetBtn ) {
			resetBtn.hidden = false;
		}

		var formData = new FormData();
		formData.append( 'action', 'dhps_mmb_search' );
		formData.append( 'nonce', config.nonce );
		formData.append( 'search', query );

		fetch( config.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		} )
			.then( function ( response ) { return response.json(); } )
			.then( function ( result ) {
				if ( result.success && result.data ) {
					renderSearchResults( resultsDiv, result.data, config );
				} else {
					renderSearchError( resultsDiv,
						result.data && result.data.message
							? result.data.message
							: 'Fehler bei der Suche.' );
				}
			} )
			.catch( function () {
				renderSearchError( resultsDiv,
					'Verbindungsfehler. Bitte versuchen Sie es erneut.' );
			} );
	}

	/**
	 * Rendert die Suchergebnisse.
	 */
	function renderSearchResults( container, data, config ) {
		if ( ! data.results || data.results.length === 0 ) {
			container.innerHTML =
				'<p class="dhps-mmb-results__empty">Keine Merkblaetter gefunden.</p>';
			return;
		}

		var html = '<div class="dhps-mmb-results__header">' +
			'<strong>' + escapeHtml( String( data.total_count ) ) + ' Merkblaetter gefunden</strong>' +
			'</div>';

		html += '<ul class="dhps-mmb-list">';

		data.results.forEach( function ( item ) {
			var sheetId = escapeAttr( item.id );

			html += '<li class="dhps-mmb-item">';
			html += '<button type="button" class="dhps-mmb-item__title"' +
				' aria-expanded="false"' +
				' aria-controls="dhps-mmb-search-detail-' + sheetId + '"' +
				' data-dhps-mmb-item-toggle>' +
				escapeHtml( item.title ) +
				'</button>';

			html += '<div class="dhps-mmb-item__detail"' +
				' id="dhps-mmb-search-detail-' + sheetId + '"' +
				' aria-hidden="true">';

			if ( item.description ) {
				html += '<p class="dhps-mmb-item__description">' +
					escapeHtml( item.description ) + '</p>';
			}

			html += '<div class="dhps-mmb-item__actions">';
			html += '<a class="dhps-mmb-item__download"' +
				' href="' + escapeAttr( config.ajaxUrl ) +
				'?action=dhps_mmb_pdf&nonce=' + escapeAttr( config.nonce ) +
				'&id=' + sheetId + '"' +
				' target="_blank" rel="noopener">' +
				'PDF herunterladen</a>';
			html += '</div>';

			html += '</div>';
			html += '</li>';
		} );

		html += '</ul>';

		container.innerHTML = html;

		// Event-Delegation fuer Suchergebnis-Accordion.
		initItemAccordion( container );
	}

	/**
	 * Zeigt eine Fehlermeldung in den Suchergebnissen.
	 */
	function renderSearchError( container, message ) {
		container.innerHTML =
			'<p class="dhps-mmb-results__error" role="alert">' +
			escapeHtml( message ) +
			'</p>';
	}

	/**
	 * Filter-Bar: Zeigt/versteckt Kategorien basierend auf Filter-Button.
	 * Nur vorhanden in Card- und Compact-Layouts.
	 */
	function initFilterBar( wrapper, container ) {
		var filterBar = wrapper.querySelector( '[data-dhps-mmb-filter-bar]' );
		if ( ! filterBar ) {
			return;
		}

		filterBar.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.dhps-filter-bar__btn' );
			if ( ! btn ) {
				return;
			}

			var filter = btn.getAttribute( 'data-filter' );

			// Button-States aktualisieren.
			var allBtns = filterBar.querySelectorAll( '.dhps-filter-bar__btn' );
			allBtns.forEach( function ( b ) {
				b.classList.remove( 'dhps-filter-bar__btn--active' );
				b.setAttribute( 'aria-pressed', 'false' );
			} );
			btn.classList.add( 'dhps-filter-bar__btn--active' );
			btn.setAttribute( 'aria-pressed', 'true' );

			// Kategorien filtern.
			var categories = container.querySelectorAll( '[data-dhps-mmb-category]' );
			categories.forEach( function ( cat ) {
				var isVisible = ( filter === 'all' || cat.getAttribute( 'data-category' ) === filter );

				if ( isVisible ) {
					cat.style.display = '';
					cat.removeAttribute( 'hidden' );

					// Bei Einzelfilter: Kategorie automatisch oeffnen.
					if ( filter !== 'all' ) {
						var trigger = cat.querySelector( '[data-dhps-mmb-category-toggle]' );
						var content = trigger ? document.getElementById( trigger.getAttribute( 'aria-controls' ) ) : null;

						if ( trigger ) {
							trigger.setAttribute( 'aria-expanded', 'true' );
						}
						if ( content ) {
							content.setAttribute( 'aria-hidden', 'false' );
						}
					}
				} else {
					cat.style.display = 'none';
					cat.setAttribute( 'hidden', '' );
				}
			} );

			// Bei "Alle": Erste Kategorie oeffnen, Rest schliessen.
			if ( filter === 'all' ) {
				categories.forEach( function ( cat, idx ) {
					var trigger = cat.querySelector( '[data-dhps-mmb-category-toggle]' );
					var content = trigger ? document.getElementById( trigger.getAttribute( 'aria-controls' ) ) : null;
					var shouldOpen = ( idx === 0 );

					if ( trigger ) {
						trigger.setAttribute( 'aria-expanded', shouldOpen ? 'true' : 'false' );
					}
					if ( content ) {
						content.setAttribute( 'aria-hidden', shouldOpen ? 'false' : 'true' );
					}
				} );
			}
		} );
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	function escapeAttr( str ) {
		return str
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
