/**
 * DHPS Components - Alpine.js Factories
 *
 * Registriert 4 stateful Component-Factories an `window.dhpsAlpine.components`,
 * die in dhps-alpine-init.js am `alpine:init`-Event abgeholt und in
 * `Alpine.data()` umgewandelt werden.
 *
 * Komponenten:
 *   - dhpsContentCard  : Toggle fuer expandable body (open/close).
 *   - dhpsFilterBar    : Search + Tag-Chips + Sort, emittiert dhps:filter-changed.
 *   - dhpsPagination   : load-more / numeric / infinite, fetch via fetch().
 *   - dhpsContentList  : Container, lauscht auf filter-changed + items-loaded.
 *
 * Event-Schema:
 *   dhps:filter-changed -> detail { query, tags[], sort }   (bubbles)
 *   dhps:items-loaded   -> detail { html, has_more, page }  (bubbles)
 *
 * Konventionen:
 *   - ASCII-safe (keine Umlaute im Code, Strings nur im UI-Text als Slug).
 *   - Keine externen Dependencies ausser Alpine selbst.
 *   - Keine DOM-Mutationen ausserhalb der eigenen Wurzel.
 *
 * @package Deubner Homepage-Service
 * @since   0.14.0
 */

( function () {
	'use strict';

	// Namespace defensiv anlegen, falls dhps-alpine-init.js noch nicht lief.
	window.dhpsAlpine = window.dhpsAlpine || { components: {}, stores: {} };

	/* ===================================================================
	 *  1. dhpsContentCard
	 *  Toggle-Logik fuer expandable Card-Body (collapsible=true).
	 * =================================================================== */

	window.dhpsAlpine.components.dhpsContentCard = function () {
		return {
			open: false,

			/** Toggle den Open-State. */
			toggle: function () {
				this.open = ! this.open;
			},

			/** Convenience-Getter fuer aria-expanded. */
			get ariaExpanded() {
				return this.open ? 'true' : 'false';
			}
		};
	};

	/* ===================================================================
	 *  2. dhpsFilterBar
	 *  Search + Tag-Chips (Multi) + Sort. Emittiert dhps:filter-changed
	 *  bei jedem State-Wechsel an this.$root (Custom-Event mit detail).
	 * =================================================================== */

	window.dhpsAlpine.components.dhpsFilterBar = function ( config ) {
		config = config || {};

		return {
			query: '',
			activeTags: [],
			sort: config.defaultSort || '',
			minChars: typeof config.minChars === 'number' ? config.minChars : 2,
			debounceMs: typeof config.debounceMs === 'number' ? config.debounceMs : 300,
			target: config.target || '',
			statusText: '',
			_initialSort: config.defaultSort || '',

			init: function () {
				var self = this;

				// Optionales URL-Hash-Deeplink: #q=foo&tag=bar1,bar2&sort=date_desc
				try {
					var hash = window.location.hash.replace( /^#/, '' );
					if ( hash.length > 0 ) {
						var params = new URLSearchParams( hash );
						if ( params.has( 'q' ) ) {
							self.query = params.get( 'q' );
						}
						if ( params.has( 'tag' ) ) {
							self.activeTags = params.get( 'tag' ).split( ',' ).filter( Boolean );
						}
						if ( params.has( 'sort' ) ) {
							self.sort = params.get( 'sort' );
						}
					}
				} catch ( e ) {
					// Hash-Parsing fehlgeschlagen - ignorieren.
				}

				// Reaktive Beobachter: bei jedem Wert-Wechsel Event dispatchen.
				this.$watch( 'query', function () { self._emit(); } );
				this.$watch( 'activeTags', function () { self._emit(); } );
				this.$watch( 'sort', function () { self._emit(); } );

				// Einmaliger Initial-Emit, damit ContentList den Startzustand kennt.
				this._emit();
			},

			/** Schaltet Tag-ID in activeTags-Array an/aus. */
			toggleTag: function ( id ) {
				if ( ! id ) { return; }
				var idx = this.activeTags.indexOf( id );
				if ( idx === -1 ) {
					this.activeTags.push( id );
				} else {
					this.activeTags.splice( idx, 1 );
				}
			},

			/** True wenn das Tag aktuell aktiv ist. */
			isTagActive: function ( id ) {
				return this.activeTags.indexOf( id ) !== -1;
			},

			/** True wenn irgendein Filter gesetzt ist (Reset-Button-Anzeige). */
			hasActiveFilters: function () {
				return (
					( this.query && this.query.length > 0 ) ||
					this.activeTags.length > 0 ||
					( this.sort && this.sort !== this._initialSort )
				);
			},

			/** Setzt alle Filter auf Defaults zurueck. */
			reset: function () {
				this.query = '';
				this.activeTags = [];
				this.sort = this._initialSort;
				this.statusText = '';
			},

			/**
			 * Privates Event-Dispatch. Effektive Query nur wenn >= minChars.
			 * Sonst leerer String, damit ContentList alle Items zeigt.
			 */
			_emit: function () {
				var effectiveQuery = ( this.query && this.query.length >= this.minChars ) ? this.query : '';

				var detail = {
					query: effectiveQuery,
					tags: this.activeTags.slice(),
					sort: this.sort,
					raw: { query: this.query }
				};

				try {
					this.$root.dispatchEvent( new CustomEvent( 'dhps:filter-changed', {
						detail: detail,
						bubbles: true
					} ) );
				} catch ( e ) {
					if ( window.console && window.console.warn ) {
						window.console.warn( '[DHPS FilterBar] dispatch failed', e );
					}
				}
			}
		};
	};

	/* ===================================================================
	 *  3. dhpsPagination
	 *  Load-more / numeric / infinite. Fetch via fetch() an WP-AJAX.
	 *  Emittiert dhps:items-loaded mit den geladenen Daten.
	 * =================================================================== */

	window.dhpsAlpine.components.dhpsPagination = function ( config ) {
		config = config || {};

		return {
			mode: config.mode || 'load-more',
			currentPage: config.currentPage || 1,
			totalPages: config.totalPages || 1,
			hasMore: config.hasMore !== false,
			pageSize: config.pageSize || 10,
			loading: false,
			error: null,
			statusText: '',
			_io: null,

			init: function () {
				this._updateStatus();
			},

			/** Setup IntersectionObserver fuer infinite-Mode. */
			initInfinite: function () {
				if ( this.mode !== 'infinite' ) { return; }
				if ( ! ( 'IntersectionObserver' in window ) ) { return; }
				if ( ! this.$refs || ! this.$refs.sentinel ) { return; }

				var self = this;
				this._io = new IntersectionObserver( function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( entry.isIntersecting && self.hasMore && ! self.loading ) {
							self.loadMore();
						}
					} );
				}, { rootMargin: '200px' } );
				this._io.observe( this.$refs.sentinel );
			},

			/** Springt zu einer beliebigen Seite (numeric-Mode). */
			goToPage: function ( page ) {
				if ( this.loading ) { return; }
				page = parseInt( page, 10 );
				if ( isNaN( page ) || page < 1 || page > this.totalPages ) { return; }
				if ( page === this.currentPage ) { return; }
				this._fetchPage( page, false );
			},

			/** Laedt naechste Seite und appended (load-more / infinite). */
			loadMore: function () {
				if ( ! this.hasMore || this.loading ) { return; }
				this._fetchPage( this.currentPage + 1, true );
			},

			/** Privates fetch + dispatch. append=true -> emittiert items-loaded. */
			_fetchPage: function ( targetPage, append ) {
				var self = this;
				if ( ! config.ajaxUrl || ! config.ajaxAction ) {
					this.error = 'AJAX-Konfiguration fehlt.';
					return;
				}

				this.loading = true;
				this.error = null;
				this.statusText = '';

				var url = config.ajaxUrl
					+ ( config.ajaxUrl.indexOf( '?' ) === -1 ? '?' : '&' )
					+ 'action=' + encodeURIComponent( config.ajaxAction )
					+ '&page=' + encodeURIComponent( targetPage );

				if ( config.ajaxNonce ) {
					url += '&_wpnonce=' + encodeURIComponent( config.ajaxNonce );
				}

				fetch( url, {
					method: 'GET',
					credentials: 'same-origin',
					headers: { 'Accept': 'application/json' }
				} )
				.then( function ( r ) {
					if ( ! r.ok ) {
						throw new Error( 'HTTP ' + r.status );
					}
					return r.json();
				} )
				.then( function ( data ) {
					// WP-Standard-Wrapper { success, data } pruefen.
					var payload = ( data && typeof data === 'object' && 'data' in data ) ? data.data : data;

					if ( data && data.success === false ) {
						throw new Error( ( payload && payload.message ) || 'Fehler beim Laden.' );
					}

					self.currentPage = targetPage;
					if ( payload && typeof payload.has_more !== 'undefined' ) {
						self.hasMore = !! payload.has_more;
					} else if ( payload && typeof payload.total_pages !== 'undefined' ) {
						self.totalPages = parseInt( payload.total_pages, 10 ) || self.totalPages;
						self.hasMore = self.currentPage < self.totalPages;
					}

					// Event nur dispatchen, wenn appended (sonst soll List-Container neu rendern).
					try {
						self.$root.dispatchEvent( new CustomEvent( 'dhps:items-loaded', {
							detail: {
								html: payload && payload.html ? payload.html : '',
								items: payload && payload.items ? payload.items : null,
								has_more: self.hasMore,
								page: self.currentPage,
								append: !! append
							},
							bubbles: true
						} ) );
					} catch ( e ) { /* noop */ }

					self._updateStatus();
				} )
				.catch( function ( err ) {
					self.error = err && err.message ? err.message : 'Unbekannter Fehler.';
				} )
				.finally( function () {
					self.loading = false;
				} );
			},

			_updateStatus: function () {
				if ( 'numeric' === this.mode ) {
					this.statusText = 'Seite ' + this.currentPage + ' von ' + this.totalPages;
				} else if ( this.hasMore ) {
					this.statusText = '';
				} else {
					this.statusText = 'Alle Eintraege geladen.';
				}
			}
		};
	};

	/* ===================================================================
	 *  4. dhpsContentList
	 *  Container; lauscht auf dhps:filter-changed (von FilterBar)
	 *  und dhps:items-loaded (von Pagination). Hide/Show via .is-hidden,
	 *  Append via innerHTML/insertAdjacentHTML.
	 * =================================================================== */

	window.dhpsAlpine.components.dhpsContentList = function ( config ) {
		config = config || {};

		return {
			id: config.id || '',
			layout: config.layout || 'grid',
			columns: config.columns || 2,
			itemType: config.itemType || 'news',
			visibleCount: 0,
			totalCount: 0,

			init: function () {
				var self = this;
				var container = this._getContainer();
				if ( container ) {
					this.totalCount = container.querySelectorAll( '[data-dhps-list-item]' ).length;
					this.visibleCount = this.totalCount;
				}

				// Wir haengen die Listener am $root (= ContentList-Wurzel),
				// nicht am Document - damit andere Listen auf der Seite
				// ihre eigenen Events erhalten.
				this.$root.addEventListener( 'dhps:filter-changed', function ( e ) {
					self.applyFilter( e.detail );
				} );
				this.$root.addEventListener( 'dhps:items-loaded', function ( e ) {
					self.appendItems( e.detail );
				} );
			},

			/** Wendet Filter (query, tags, sort) auf die Items an. */
			applyFilter: function ( detail ) {
				detail = detail || {};
				var query = ( detail.query || '' ).toLowerCase().trim();
				var tags  = detail.tags || [];
				var container = this._getContainer();
				if ( ! container ) { return; }

				var items = container.querySelectorAll( '[data-dhps-list-item]' );
				var shown = 0;

				items.forEach( function ( el ) {
					var matches = true;

					// Volltext-Filter: prueft data-search ODER textContent.
					if ( query.length > 0 ) {
						var hay = ( el.getAttribute( 'data-search' ) || el.textContent || '' ).toLowerCase();
						if ( hay.indexOf( query ) === -1 ) {
							matches = false;
						}
					}

					// Tag-Filter: data-tags="tagA,tagB" - mindestens ein Treffer reicht.
					if ( matches && tags.length > 0 ) {
						var itemTagsAttr = ( el.getAttribute( 'data-tags' ) || '' ).toLowerCase();
						var itemTags = itemTagsAttr.split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
						var hit = false;
						for ( var i = 0; i < tags.length; i++ ) {
							if ( itemTags.indexOf( String( tags[ i ] ).toLowerCase() ) !== -1 ) {
								hit = true;
								break;
							}
						}
						if ( ! hit ) {
							matches = false;
						}
					}

					if ( matches ) {
						el.classList.remove( 'is-hidden' );
						el.removeAttribute( 'hidden' );
						shown++;
					} else {
						el.classList.add( 'is-hidden' );
						el.setAttribute( 'hidden', '' );
					}
				} );

				this.visibleCount = shown;
				// FilterBar-Status updaten (falls vorhanden im DOM-Tree).
				this._broadcastStatus( shown, items.length );
			},

			/** Haengt neue Items an (von Pagination geliefert). */
			appendItems: function ( detail ) {
				detail = detail || {};
				var container = this._getContainer();
				if ( ! container ) { return; }

				if ( detail.html && typeof detail.html === 'string' ) {
					// Wrapper-Pruefung: stellt sicher, dass appendedItems data-dhps-list-item haben.
					var tmp = document.createElement( 'div' );
					tmp.innerHTML = detail.html;
					var nodes = tmp.children;
					while ( nodes.length > 0 ) {
						container.appendChild( nodes[ 0 ] );
					}
				}

				this.totalCount = container.querySelectorAll( '[data-dhps-list-item]' ).length;
				this.visibleCount = container.querySelectorAll( '[data-dhps-list-item]:not(.is-hidden)' ).length;
			},

			/** Findet den .dhps-content-list__container innerhalb $root. */
			_getContainer: function () {
				if ( ! this.$root ) { return null; }
				return this.$root.querySelector( '[data-dhps-list="' + this.id + '"]' )
					|| this.$root.querySelector( '.dhps-content-list__container' );
			},

			/** Setzt FilterBar.statusText falls FilterBar im Sub-Tree existiert. */
			_broadcastStatus: function ( shown, total ) {
				// Versuch via Alpine $data: nur best-effort.
				try {
					var bar = this.$root.querySelector( '.dhps-filter-bar' );
					if ( bar && bar._x_dataStack && bar._x_dataStack[ 0 ] ) {
						var msg = shown + ' von ' + total + ' sichtbar';
						bar._x_dataStack[ 0 ].statusText = msg;
					}
				} catch ( e ) { /* noop */ }
			}
		};
	};

} )();
