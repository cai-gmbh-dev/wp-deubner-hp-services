/**
 * Deubner Homepage Services - MIO Frontend-JavaScript.
 *
 * Ersetzt die Legacy-Inline-Handler mit modernem Vanilla-JS.
 * - AJAX-News-Loading ueber WordPress admin-ajax.php (serverseitiger Proxy)
 * - Accessible Accordion fuer Artikel (ARIA-Attribute)
 * - Suchleiste mit Enter-Key und Dropdown-Change
 * - Druck-Funktion fuer einzelne Artikel
 * - Client-seitige Pagination mit "Mehr laden"-Button und IntersectionObserver
 *
 * Die Deubner-API liefert alle Artikel in einem einzigen Response.
 * Pagination erfolgt daher rein client-seitig: Daten werden einmal geladen,
 * dann in Scheiben (slices) von `data-anzahl` Artikeln gerendert.
 *
 * Keine externe Abhaengigkeit (kein jQuery).
 * OTA-Kundennummer wird NICHT im Client-Code verwendet.
 *
 * @package Deubner Homepage-Service
 * @since   0.9.0
 */

( function () {
	'use strict';

	/* =====================================================================
	   Init
	   ===================================================================== */

	/**
	 * Initialisiert alle MIO-Instanzen auf der Seite.
	 */
	function init() {
		var containers = document.querySelectorAll( '[data-dhps-news-container]' );

		containers.forEach( function ( container ) {
			initInstance( container );
		} );
	}

	/**
	 * Initialisiert eine einzelne MIO-Instanz.
	 *
	 * @param {HTMLElement} container Der News-Container.
	 */
	function initInstance( container ) {
		var config = {
			ajaxUrl:     container.getAttribute( 'data-ajax-url' ),
			nonce:       container.getAttribute( 'data-nonce' ),
			serviceTag:  container.getAttribute( 'data-service-tag' ),
			layout:      container.getAttribute( 'data-layout' ) || 'default',
			fachgebiet:  container.getAttribute( 'data-fachgebiet' ) || 'S',
			variante:    container.getAttribute( 'data-variante' ) || 'KATEGORIEN',
			anzahl:      parseInt( container.getAttribute( 'data-anzahl' ) || '10', 10 ),
			teasermodus: container.getAttribute( 'data-teasermodus' ) || '0',
		};

		var state = {
			search:       '',
			rubriken:     'alle Zielgruppen',
			zielgruppen:  '',
			month:        'alle',
			year:         'alle',
			loading:      false,
			fullData:     null,   // Vollstaendige API-Daten (einmalig geladen).
			totalArticles: 0,    // Gesamtanzahl Artikel in fullData.
			displayCount: 0,     // Aktuell angezeigte Artikel.
			hasMore:      false,
			topicFilter:  'all', // Aktiver Themen-Filter.
		};

		// Such-Formular finden (Geschwister-Element).
		var serviceWrapper = container.closest( '.dhps-service' );

		if ( ! serviceWrapper ) {
			return;
		}

		var searchForm   = serviceWrapper.querySelector( '[data-dhps-search]' );
		var rubrikenSel  = serviceWrapper.querySelector( '[data-dhps-rubriken]' );
		var searchInput  = serviceWrapper.querySelector( '[data-dhps-search-input]' );

		// Event-Listener fuer Suchformular.
		if ( searchForm ) {
			searchForm.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				state.search = searchInput ? searchInput.value : '';
				loadNews( container, config, state );
			} );
		}

		// Bei Dropdown-Aenderung sofort laden.
		if ( rubrikenSel ) {
			rubrikenSel.addEventListener( 'change', function () {
				state.rubriken = this.value;
				loadNews( container, config, state );
			} );
		}

		// "Mehr laden"-Trigger (rein client-seitig, kein AJAX).
		function showMore() {
			if ( ! state.hasMore || ! state.fullData ) {
				return;
			}

			var filteredData = filterDataByTopic( state.fullData, state.topicFilter );
			var slice = sliceArticles( filteredData, state.displayCount, config.anzahl );
			var sliceCount = countArticles( slice );

			if ( sliceCount === 0 ) {
				state.hasMore = false;
				updateLoadMoreVisibility( container, state );
				return;
			}

			state.displayCount += sliceCount;
			state.hasMore = state.displayCount < state.totalArticles;

			// Load-More voruebergehend entfernen.
			var loadMoreEl = container.querySelector( '[data-dhps-load-more]' );
			if ( loadMoreEl ) {
				loadMoreEl.remove();
			}

			appendNews( container, slice );

			getOrCreateLoadMore( container );
			updateLoadMoreVisibility( container, state );

			if ( state._io ) {
				state._io.reobserve();
			}
		}

		// Click-Handler fuer Load-More-Button (Event-Delegation).
		container.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-dhps-load-more-btn]' ) ) {
				showMore();
			}
		} );

		// IntersectionObserver fuer Auto-Scroll-Loading.
		state._io = setupIntersectionObserver( container, showMore );

		// Filter-Bar Click-Handler (Event-Delegation auf serviceWrapper).
		serviceWrapper.addEventListener( 'click', function ( e ) {
			var filterBtn = e.target.closest( '[data-dhps-mio-filter-bar] .dhps-filter-bar__btn' );
			if ( ! filterBtn ) {
				return;
			}

			var topic = filterBtn.getAttribute( 'data-filter' );
			state.topicFilter = topic;

			// Button-States aktualisieren.
			var allBtns = serviceWrapper.querySelectorAll( '[data-dhps-mio-filter-bar] .dhps-filter-bar__btn' );
			allBtns.forEach( function ( b ) {
				b.classList.remove( 'dhps-filter-bar__btn--active' );
				b.setAttribute( 'aria-pressed', 'false' );
			} );
			filterBtn.classList.add( 'dhps-filter-bar__btn--active' );
			filterBtn.setAttribute( 'aria-pressed', 'true' );

			// Daten filtern und erste Seite rendern.
			var filteredData    = filterDataByTopic( state.fullData, state.topicFilter );
			state.totalArticles = countArticles( filteredData );

			var firstSlice     = sliceArticles( filteredData, 0, config.anzahl );
			state.displayCount = countArticles( firstSlice );
			state.hasMore      = state.displayCount < state.totalArticles;

			renderNews( container, firstSlice );
			bindEvents( container );

			getOrCreateLoadMore( container );
			updateLoadMoreVisibility( container, state );

			if ( state._io ) {
				state._io.reobserve();
			}
		} );

		// Initiales Laden (einmaliger AJAX-Call).
		loadNews( container, config, state );
	}

	/* =====================================================================
	   AJAX Loading (einmalig pro Filter-Aenderung)
	   ===================================================================== */

	/**
	 * Laedt alle News einmalig ueber den AJAX-Proxy.
	 * Anschliessend wird nur die erste Scheibe (slice) gerendert.
	 *
	 * @param {HTMLElement} container Der News-Container.
	 * @param {Object}      config    AJAX-Konfiguration.
	 * @param {Object}      state     Aktueller Filter-Zustand.
	 */
	function loadNews( container, config, state ) {
		if ( state.loading ) {
			return;
		}

		state.loading       = true;
		state.fullData      = null;
		state.totalArticles = 0;
		state.displayCount  = 0;
		state.hasMore       = false;

		showLoading( container );

		var formData = new FormData();
		formData.append( 'action', 'dhps_load_news' );
		formData.append( 'nonce', config.nonce );
		formData.append( 'service_tag', config.serviceTag );
		formData.append( 'page', '1' );
		formData.append( 'search', state.search );
		formData.append( 'month', state.month );
		formData.append( 'year', state.year );
		formData.append( 'rubriken', state.rubriken );
		formData.append( 'zielgruppen', state.zielgruppen );
		formData.append( 'fachgebiet', config.fachgebiet );
		formData.append( 'variante', config.variante );
		formData.append( 'anzahl', String( config.anzahl ) );
		formData.append( 'teasermodus', config.teasermodus );

		fetch( config.ajaxUrl, {
			method: 'POST',
			body:   formData,
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				state.loading = false;

				if ( result.success && result.data ) {
					state.fullData      = result.data;
					state.totalArticles = countArticles( result.data );

					// Topic-Filter zuruecksetzen und Filter-Bar aufbauen.
					state.topicFilter = 'all';
					var topics = extractTopics( result.data );
					var wrapper = container.closest( '.dhps-service' );
					if ( wrapper ) {
						buildFilterBar( wrapper, topics );
					}

					// Erste Scheibe rendern.
					var firstSlice = sliceArticles( result.data, 0, config.anzahl );
					var firstCount = countArticles( firstSlice );

					state.displayCount = firstCount;
					state.hasMore      = state.displayCount < state.totalArticles;

					renderNews( container, firstSlice );
					bindEvents( container );

					getOrCreateLoadMore( container );
					updateLoadMoreVisibility( container, state );

					if ( state._io ) {
						state._io.reobserve();
					}
				} else {
					renderError( container, result.data && result.data.message ? result.data.message : 'Fehler beim Laden.' );
				}
			} )
			.catch( function () {
				state.loading = false;
				renderError( container, 'Verbindungsfehler. Bitte versuchen Sie es erneut.' );
			} );
	}

	/**
	 * Zeigt den Lade-Indikator an.
	 *
	 * @param {HTMLElement} container Der News-Container.
	 */
	function showLoading( container ) {
		container.innerHTML =
			'<div class="dhps-news__loading" data-dhps-loading>' +
			'<span class="dhps-news__spinner" aria-hidden="true"></span>' +
			'<span class="screen-reader-text">Nachrichten werden geladen...</span>' +
			'</div>';
	}

	/* =====================================================================
	   Client-seitige Pagination (Slicing)
	   ===================================================================== */

	/**
	 * Extrahiert eine Scheibe (slice) von Artikeln aus dem vollstaendigen Datensatz.
	 *
	 * Iteriert ueber Gruppen und Artikel, ueberspringt die ersten `from` Artikel
	 * und sammelt bis zu `count` Artikel ein. Die Gruppen-Struktur bleibt erhalten.
	 *
	 * @param {Object} fullData Vollstaendige API-Daten.
	 * @param {number} from     Start-Index (0-basiert, global ueber alle Gruppen).
	 * @param {number} count    Maximale Anzahl Artikel in der Scheibe.
	 * @return {Object} Datensatz mit gleicher Struktur, aber nur der Scheibe.
	 */
	function sliceArticles( fullData, from, count ) {
		var result = { groups: [] };
		var idx    = 0;
		var taken  = 0;

		if ( ! fullData || ! fullData.groups ) {
			return result;
		}

		fullData.groups.forEach( function ( group ) {
			var slicedArticles = [];

			group.articles.forEach( function ( article ) {
				if ( idx >= from && taken < count ) {
					slicedArticles.push( article );
					taken++;
				}
				idx++;
			} );

			if ( slicedArticles.length > 0 ) {
				result.groups.push( {
					name:     group.name,
					articles: slicedArticles,
				} );
			}
		} );

		return result;
	}

	/* =====================================================================
	   Render (Initial / Replace)
	   ===================================================================== */

	/**
	 * Rendert die geparsten News-Daten je nach Layout-Variante.
	 *
	 * @param {HTMLElement} container Der News-Container.
	 * @param {Object}      data      Geparste Daten (ggf. nur eine Scheibe).
	 */
	function renderNews( container, data ) {
		if ( ! data.groups || data.groups.length === 0 ) {
			container.innerHTML = '<p class="dhps-news__empty">Keine Nachrichten gefunden.</p>';
			return;
		}

		var layout = container.getAttribute( 'data-layout' ) || 'default';

		if ( layout === 'card' ) {
			renderNewsCard( container, data );
		} else if ( layout === 'compact' ) {
			renderNewsCompact( container, data );
		} else {
			renderNewsDefault( container, data );
		}
	}

	/**
	 * Rendert News im Standard-Layout (Accordion).
	 */
	function renderNewsDefault( container, data ) {
		var html = '';

		data.groups.forEach( function ( group ) {
			html += '<div class="dhps-news__group">';
			html += '<h3 class="dhps-news__group-title">' + escapeHtml( group.name ) + '</h3>';

			group.articles.forEach( function ( article ) {
				html += buildDefaultArticleHtml( article );
			} );

			html += '</div>';
		} );

		container.innerHTML = html;
	}

	/**
	 * Rendert News im Card-Layout (2-Spalten Article-Cards).
	 */
	function renderNewsCard( container, data ) {
		var html = '';
		var cols = container.getAttribute( 'data-card-columns' ) || '2';
		var gridClass = 'dhps-mio-card-grid dhps-mio-card-grid--' + cols + 'col';

		data.groups.forEach( function ( group ) {
			html += '<h3 class="dhps-news__group-title">' + escapeHtml( group.name ) + '</h3>';
			html += '<div class="' + gridClass + '">';

			group.articles.forEach( function ( article ) {
				html += buildCardArticleHtml( article );
			} );

			html += '</div>';
		} );

		container.innerHTML = html;
	}

	/**
	 * Rendert News im Compact-Layout (Tabellarische Zeilen).
	 */
	function renderNewsCompact( container, data ) {
		var html = '';

		data.groups.forEach( function ( group ) {
			html += '<div class="dhps-mio-compact__group">';
			html += '<div class="dhps-mio-compact__group-header">' +
				escapeHtml( group.name ) +
				' <span class="dhps-mio-compact__group-count">(' + group.articles.length + ')</span>' +
				' <span class="dhps-mio-compact__chevron" aria-hidden="true">&#9662;</span>' +
				'</div>';

			html += '<div class="dhps-mio-compact__articles">';
			group.articles.forEach( function ( article ) {
				html += buildCompactArticleHtml( article );
			} );
			html += '</div>';
			html += '</div>';
		} );

		container.innerHTML = html;
	}

	/* =====================================================================
	   Append (Client-seitige Pagination - naechste Scheibe)
	   ===================================================================== */

	/**
	 * Haengt weitere Artikel an den bestehenden Container an.
	 *
	 * @param {HTMLElement} container Der News-Container.
	 * @param {Object}      data      Naechste Scheibe der Daten.
	 */
	function appendNews( container, data ) {
		if ( ! data.groups || data.groups.length === 0 ) {
			return;
		}

		var layout = container.getAttribute( 'data-layout' ) || 'default';

		if ( layout === 'card' ) {
			appendNewsCard( container, data );
		} else if ( layout === 'compact' ) {
			appendNewsCompact( container, data );
		} else {
			appendNewsDefault( container, data );
		}
	}

	/**
	 * Haengt Artikel im Default-Layout an.
	 */
	function appendNewsDefault( container, data ) {
		data.groups.forEach( function ( group ) {
			var existingGroup = findGroupByName( container, '.dhps-news__group', '.dhps-news__group-title', group.name );

			var articlesHtml = '';
			group.articles.forEach( function ( article ) {
				articlesHtml += buildDefaultArticleHtml( article );
			} );

			if ( existingGroup ) {
				existingGroup.insertAdjacentHTML( 'beforeend', articlesHtml );
			} else {
				var groupHtml = '<div class="dhps-news__group">' +
					'<h3 class="dhps-news__group-title">' + escapeHtml( group.name ) + '</h3>' +
					articlesHtml +
					'</div>';
				container.insertAdjacentHTML( 'beforeend', groupHtml );
			}
		} );
	}

	/**
	 * Haengt Artikel im Card-Layout an.
	 */
	function appendNewsCard( container, data ) {
		var cols = container.getAttribute( 'data-card-columns' ) || '2';
		var gridClass = 'dhps-mio-card-grid dhps-mio-card-grid--' + cols + 'col';

		data.groups.forEach( function ( group ) {
			var existingGrid = null;
			var titles = container.querySelectorAll( '.dhps-news__group-title' );
			titles.forEach( function ( t ) {
				if ( t.textContent.trim() === group.name && t.nextElementSibling &&
					 t.nextElementSibling.classList.contains( 'dhps-mio-card-grid' ) ) {
					existingGrid = t.nextElementSibling;
				}
			} );

			var cardsHtml = '';
			group.articles.forEach( function ( article ) {
				cardsHtml += buildCardArticleHtml( article );
			} );

			if ( existingGrid ) {
				existingGrid.insertAdjacentHTML( 'beforeend', cardsHtml );
			} else {
				var fullHtml = '<h3 class="dhps-news__group-title">' + escapeHtml( group.name ) + '</h3>' +
					'<div class="' + gridClass + '">' + cardsHtml + '</div>';
				container.insertAdjacentHTML( 'beforeend', fullHtml );
			}
		} );
	}

	/**
	 * Haengt Artikel im Compact-Layout an.
	 */
	function appendNewsCompact( container, data ) {
		data.groups.forEach( function ( group ) {
			var existingGroup = null;
			var headers = container.querySelectorAll( '.dhps-mio-compact__group-header' );
			headers.forEach( function ( header ) {
				var headerName = header.childNodes[0] ? header.childNodes[0].textContent.trim() : '';
				if ( headerName === group.name ) {
					existingGroup = header.closest( '.dhps-mio-compact__group' );
				}
			} );

			var articlesHtml = '';
			group.articles.forEach( function ( article ) {
				articlesHtml += buildCompactArticleHtml( article );
			} );

			if ( existingGroup ) {
				var articlesContainer = existingGroup.querySelector( '.dhps-mio-compact__articles' );
				if ( articlesContainer ) {
					articlesContainer.insertAdjacentHTML( 'beforeend', articlesHtml );
					var countEl = existingGroup.querySelector( '.dhps-mio-compact__group-count' );
					if ( countEl ) {
						var newCount = articlesContainer.querySelectorAll( '.dhps-mio-compact__article' ).length;
						countEl.textContent = '(' + newCount + ')';
					}
				}
			} else {
				var groupHtml = '<div class="dhps-mio-compact__group">' +
					'<div class="dhps-mio-compact__group-header">' +
					escapeHtml( group.name ) +
					' <span class="dhps-mio-compact__group-count">(' + group.articles.length + ')</span>' +
					' <span class="dhps-mio-compact__chevron" aria-hidden="true">&#9662;</span>' +
					'</div>' +
					'<div class="dhps-mio-compact__articles">' + articlesHtml + '</div>' +
					'</div>';
				container.insertAdjacentHTML( 'beforeend', groupHtml );
			}
		} );
	}

	/* =====================================================================
	   HTML-Builder (einzelne Artikel)
	   ===================================================================== */

	/**
	 * Erzeugt HTML fuer einen Default-Artikel (Accordion-Zeile).
	 *
	 * @param {Object} article Artikel-Daten.
	 * @return {string} HTML-String.
	 */
	function buildDefaultArticleHtml( article ) {
		var bodyId = 'dhps-body-' + article.id;
		var html   = '';

		html += '<div class="dhps-news__article">';

		html += '<button type="button" class="dhps-news__title"' +
			' aria-expanded="false"' +
			' aria-controls="' + bodyId + '"' +
			' data-dhps-toggle="' + bodyId + '">' +
			escapeHtml( article.title ) +
			'</button>';

		html += '<div class="dhps-news__body" id="' + bodyId + '" aria-hidden="true">';

		if ( article.body_html ) {
			html += article.body_html;
		}

		html += buildMetaHtml( article );

		if ( article.share_links && Object.keys( article.share_links ).length > 0 ) {
			html += '<div class="dhps-news__share">';
			var platforms = {
				email:    { label: 'E-Mail', icon: 'sm_mail2.svg' },
				twitter:  { label: 'Twitter', icon: 'sm_twitter.svg' },
				facebook: { label: 'Facebook', icon: 'sm_fb.svg' },
				xing:     { label: 'XING', icon: 'sm_xing.svg' },
				linkedin: { label: 'LinkedIn', icon: 'sm_linkedin.svg' },
			};

			Object.keys( platforms ).forEach( function ( key ) {
				if ( article.share_links[ key ] ) {
					var p = platforms[ key ];
					html += '<a class="dhps-news__share-link" href="' + escapeAttr( article.share_links[ key ] ) + '"' +
						' title="' + p.label + '"' +
						( key !== 'email' ? ' target="_blank" rel="noopener noreferrer"' : '' ) +
						' aria-label="' + p.label + '">' +
						'<img src="https://www.deubner-online.de/einbau/taxplain/videopages/images/' + p.icon + '"' +
						' alt="' + p.label + '" width="18" height="18">' +
						'</a>';
				}
			} );

			html += '</div>';
		}

		html += '<div class="dhps-news__actions">';
		html += '<button type="button" class="dhps-news__action-link" data-dhps-print="' + bodyId + '">' +
			'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
			'<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>' +
			'<rect x="6" y="14" width="12" height="8"/></svg>' +
			' Drucken' +
			'</button>';

		html += '<button type="button" class="dhps-news__action-link" data-dhps-collapse="' + bodyId + '">' +
			'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
			'<polyline points="18 15 12 9 6 15"/></svg>' +
			' Ausblenden' +
			'</button>';
		html += '</div>';

		html += '</div>'; // .dhps-news__body
		html += '</div>'; // .dhps-news__article

		return html;
	}

	/**
	 * Erzeugt HTML fuer eine Card-Artikel-Kachel.
	 *
	 * @param {Object} article Artikel-Daten.
	 * @return {string} HTML-String.
	 */
	function buildCardArticleHtml( article ) {
		var bodyId = 'dhps-body-' + article.id;
		var html   = '';

		html += '<article class="dhps-mio-card-article" data-dhps-card-toggle="' + bodyId + '">';

		if ( article.metadata && article.metadata.topic ) {
			html += '<span class="dhps-mio-card-article__tag">' +
				escapeHtml( article.metadata.topic ) + '</span>';
		}

		html += '<h4 class="dhps-mio-card-article__title">' +
			escapeHtml( article.title ) + '</h4>';

		if ( article.body_html ) {
			var tmp = document.createElement( 'div' );
			tmp.innerHTML = article.body_html;
			var excerpt = tmp.textContent || '';
			if ( excerpt.length > 150 ) {
				excerpt = excerpt.substring( 0, 147 ) + '...';
			}
			html += '<p class="dhps-mio-card-article__excerpt">' +
				escapeHtml( excerpt ) + '</p>';
		}

		html += '<span class="dhps-mio-card-article__cta">Weiterlesen \u2192</span>';

		// Body INNERHALB der Card (expandiert ueber alle Spalten per CSS).
		html += '<div class="dhps-news__body dhps-mio-card-article__body" id="' + bodyId + '" aria-hidden="true">';
		if ( article.body_html ) {
			html += article.body_html;
		}
		html += buildMetaHtml( article );
		html += '<div class="dhps-news__actions">';
		html += '<button type="button" class="dhps-news__action-link" data-dhps-collapse="' + bodyId + '">' +
			'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
			'<polyline points="18 15 12 9 6 15"/></svg> Ausblenden</button>';
		html += '</div>';
		html += '</div>';

		html += '</article>';

		return html;
	}

	/**
	 * Erzeugt HTML fuer den Card-Detail-Body (ausserhalb des Grids).
	 *
	 * @param {Object} article Artikel-Daten.
	 * @return {string} HTML-String.
	 */
	function buildCardBodyHtml( article ) {
		var bodyId = 'dhps-body-' + article.id;
		var html   = '';

		html += '<div class="dhps-news__body dhps-mio-card-article__body" id="' + bodyId + '" aria-hidden="true">';
		if ( article.body_html ) {
			html += article.body_html;
		}
		html += buildMetaHtml( article );
		html += '<div class="dhps-news__actions">';
		html += '<button type="button" class="dhps-news__action-link" data-dhps-collapse="' + bodyId + '">' +
			'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
			'<polyline points="18 15 12 9 6 15"/></svg> Ausblenden</button>';
		html += '</div>';
		html += '</div>';

		return html;
	}

	/**
	 * Erzeugt HTML fuer eine Compact-Artikel-Zeile.
	 *
	 * @param {Object} article Artikel-Daten.
	 * @return {string} HTML-String.
	 */
	function buildCompactArticleHtml( article ) {
		var bodyId = 'dhps-body-' + article.id;
		var topicLabel = '';
		if ( article.metadata && article.metadata.topic ) {
			topicLabel = article.metadata.topic;
			if ( topicLabel.length > 8 ) {
				topicLabel = topicLabel.substring( 0, 6 ) + '.';
			}
		}

		var html = '';
		html += '<div class="dhps-mio-compact__article" data-dhps-compact-toggle="' + bodyId + '">';
		html += '<span class="dhps-mio-compact__article-title">' +
			escapeHtml( article.title ) + '</span>';
		if ( topicLabel ) {
			html += '<span class="dhps-mio-compact__article-topic">' +
				escapeHtml( topicLabel ) + '</span>';
		}
		html += '<span class="dhps-mio-compact__article-date">' +
			escapeHtml( extractDate( article ) ) + '</span>';

		// Inline-Body (versteckt, wird bei Klick geoeffnet).
		html += '<div class="dhps-news__body dhps-mio-compact__article-body" id="' + bodyId + '" aria-hidden="true">';
		if ( article.body_html ) {
			html += article.body_html;
		}
		html += buildMetaHtml( article );
		html += '<div class="dhps-news__actions">';
		html += '<button type="button" class="dhps-news__action-link" data-dhps-collapse="' + bodyId + '">' +
			'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
			'<polyline points="18 15 12 9 6 15"/></svg> Ausblenden</button>';
		html += '</div>';
		html += '</div>';

		html += '</div>';

		return html;
	}

	/**
	 * Erzeugt Metadaten-HTML (target, topic).
	 *
	 * @param {Object} article Artikel-Daten.
	 * @return {string} HTML-String (leer wenn keine Metadaten).
	 */
	function buildMetaHtml( article ) {
		if ( ! article.metadata || Object.keys( article.metadata ).length === 0 ) {
			return '';
		}
		var html = '<div class="dhps-news__meta">';
		if ( article.metadata.target ) {
			html += '<span class="dhps-news__meta-item">' +
				'<span class="dhps-news__meta-label">Information f\u00fcr:</span> ' +
				escapeHtml( article.metadata.target ) +
				'</span>';
		}
		if ( article.metadata.topic ) {
			html += '<span class="dhps-news__meta-item">' +
				'<span class="dhps-news__meta-label">Thema:</span> ' +
				escapeHtml( article.metadata.topic ) +
				'</span>';
		}
		html += '</div>';
		return html;
	}

	/* =====================================================================
	   Load-More UI
	   ===================================================================== */

	/**
	 * Erzeugt HTML fuer den "Mehr laden"-Bereich.
	 *
	 * @return {string} HTML-String.
	 */
	function createLoadMoreHtml() {
		return '<div class="dhps-news__load-more" data-dhps-load-more>' +
			'<button type="button" class="dhps-news__load-more-btn" data-dhps-load-more-btn>' +
			'Mehr laden' +
			'</button>' +
			'</div>';
	}

	/**
	 * Stellt sicher, dass das Load-More-Element am Ende des Containers existiert.
	 *
	 * @param {HTMLElement} container Der News-Container.
	 * @return {HTMLElement} Das Load-More-Element.
	 */
	function getOrCreateLoadMore( container ) {
		var existing = container.querySelector( '[data-dhps-load-more]' );
		if ( existing ) {
			return existing;
		}
		container.insertAdjacentHTML( 'beforeend', createLoadMoreHtml() );
		return container.querySelector( '[data-dhps-load-more]' );
	}

	/**
	 * Aktualisiert die Sichtbarkeit des Load-More-Buttons.
	 *
	 * @param {HTMLElement} container Der News-Container.
	 * @param {Object}      state     Aktueller Zustand.
	 */
	function updateLoadMoreVisibility( container, state ) {
		var el = container.querySelector( '[data-dhps-load-more]' );
		if ( ! el ) {
			return;
		}

		if ( state.hasMore ) {
			el.removeAttribute( 'hidden' );
		} else {
			el.setAttribute( 'hidden', '' );
		}
	}

	/**
	 * Richtet den IntersectionObserver fuer Auto-Scroll-Loading ein.
	 *
	 * @param {HTMLElement} container  Der News-Container.
	 * @param {Function}    showMore   Callback zum Anzeigen der naechsten Scheibe.
	 * @return {Object} Handle mit reobserve()-Methode.
	 */
	function setupIntersectionObserver( container, showMore ) {
		if ( ! ( 'IntersectionObserver' in window ) ) {
			return { reobserve: function () {} };
		}

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					showMore();
				}
			} );
		}, {
			root:       null,
			rootMargin: '0px 0px 200px 0px',
			threshold:  0,
		} );

		return {
			reobserve: function () {
				observer.disconnect();
				// Kurze Verzoegerung damit der Observer nicht sofort wieder feuert.
				setTimeout( function () {
					var el = container.querySelector( '[data-dhps-load-more]' );
					if ( el && ! el.hasAttribute( 'hidden' ) ) {
						observer.observe( el );
					}
				}, 100 );
			},
		};
	}

	/* =====================================================================
	   Hilfsfunktionen
	   ===================================================================== */

	/**
	 * Zaehlt die Gesamtanzahl der Artikel ueber alle Gruppen.
	 *
	 * @param {Object} data Geparste Daten.
	 * @return {number} Gesamtanzahl.
	 */
	function countArticles( data ) {
		var total = 0;
		if ( data.groups ) {
			data.groups.forEach( function ( group ) {
				total += group.articles.length;
			} );
		}
		return total;
	}

	/**
	 * Findet eine bestehende Gruppe anhand ihres Titels.
	 *
	 * @param {HTMLElement} container     Der Eltern-Container.
	 * @param {string}      groupSelector CSS-Selektor fuer Gruppen-Wrapper.
	 * @param {string}      titleSelector CSS-Selektor fuer Titel-Element.
	 * @param {string}      name          Gesuchter Gruppenname.
	 * @return {HTMLElement|null} Gruppen-Element oder null.
	 */
	function findGroupByName( container, groupSelector, titleSelector, name ) {
		var groups = container.querySelectorAll( groupSelector );
		var found  = null;
		groups.forEach( function ( el ) {
			var titleEl = el.querySelector( titleSelector );
			if ( titleEl && titleEl.textContent.trim() === name ) {
				found = el;
			}
		} );
		return found;
	}

	/**
	 * Extrahiert ein kurzes Datum aus dem Artikel (Fallback: leer).
	 *
	 * @param {Object} article Artikel-Daten.
	 * @return {string} Kurzes Datum.
	 */
	function extractDate( article ) {
		if ( article.title ) {
			var match = article.title.match( /(\d{1,2}\.\d{1,2}\.)/ );
			if ( match ) {
				return match[1];
			}
		}
		return '';
	}

	/**
	 * Extrahiert eindeutige Topic-Werte aus den vollstaendigen Daten.
	 *
	 * @param {Object} data Vollstaendige API-Daten.
	 * @return {string[]} Sortierte Liste eindeutiger Topics.
	 */
	function extractTopics( data ) {
		var seen   = {};
		var topics = [];

		if ( ! data || ! data.groups ) {
			return topics;
		}

		data.groups.forEach( function ( group ) {
			group.articles.forEach( function ( article ) {
				if ( article.metadata && article.metadata.topic ) {
					var topic = article.metadata.topic;
					if ( ! seen[ topic ] ) {
						seen[ topic ] = true;
						topics.push( topic );
					}
				}
			} );
		} );

		topics.sort();
		return topics;
	}

	/**
	 * Filtert die Daten nach einem bestimmten Topic.
	 *
	 * Gibt eine neue Datenstruktur zurueck, die nur Artikel mit dem
	 * angegebenen Topic enthaelt. Bei 'all' werden alle Artikel behalten.
	 *
	 * @param {Object} fullData Vollstaendige API-Daten.
	 * @param {string} topic    Topic-Filter ('all' fuer alle).
	 * @return {Object} Gefilterte Daten mit gleicher Struktur.
	 */
	function filterDataByTopic( fullData, topic ) {
		if ( ! fullData || ! fullData.groups || topic === 'all' ) {
			return fullData;
		}

		var result = { groups: [] };

		fullData.groups.forEach( function ( group ) {
			var filtered = group.articles.filter( function ( article ) {
				return article.metadata && article.metadata.topic === topic;
			} );

			if ( filtered.length > 0 ) {
				result.groups.push( {
					name:     group.name,
					articles: filtered,
				} );
			}
		} );

		return result;
	}

	/**
	 * Baut die Filter-Bar mit Pill-Buttons fuer die gefundenen Topics.
	 *
	 * Zeigt die Bar nur wenn mindestens 2 Topics vorhanden sind.
	 *
	 * @param {HTMLElement} serviceWrapper Der Service-Wrapper (.dhps-service).
	 * @param {string[]}    topics         Liste der Topics.
	 */
	function buildFilterBar( serviceWrapper, topics ) {
		var filterBar = serviceWrapper.querySelector( '[data-dhps-mio-filter-bar]' );

		if ( ! filterBar ) {
			return;
		}

		// Weniger als 2 Topics: Filter-Bar verstecken.
		if ( topics.length < 2 ) {
			filterBar.setAttribute( 'hidden', '' );
			filterBar.innerHTML = '';
			return;
		}

		var html = '<button class="dhps-filter-bar__btn dhps-filter-bar__btn--active"' +
			' data-filter="all" aria-pressed="true">Alle</button>';

		topics.forEach( function ( topic ) {
			html += '<button class="dhps-filter-bar__btn"' +
				' data-filter="' + escapeAttr( topic ) + '"' +
				' aria-pressed="false">' +
				escapeHtml( topic ) +
				'</button>';
		} );

		filterBar.innerHTML = html;
		filterBar.removeAttribute( 'hidden' );
	}

	/* =====================================================================
	   Event-Binding (einmalig pro Container)
	   ===================================================================== */

	/**
	 * Bindet alle Event-Listener einmalig per Event-Delegation.
	 *
	 * @param {HTMLElement} container Der News-Container.
	 */
	function bindEvents( container ) {
		if ( container.hasAttribute( 'data-dhps-events-bound' ) ) {
			return;
		}
		container.setAttribute( 'data-dhps-events-bound', 'true' );

		container.addEventListener( 'click', function ( e ) {
			// Default-Accordion: Toggle-Button.
			var toggleBtn = e.target.closest( '[data-dhps-toggle]' );
			if ( toggleBtn ) {
				toggleArticle( toggleBtn );
				return;
			}

			// Card-Layout: Klick auf Card oeffnet/schliesst den Body.
			// Nicht reagieren wenn der Klick innerhalb des Body oder auf Ausblenden war.
			var card = e.target.closest( '[data-dhps-card-toggle]' );
			if ( card && ! e.target.closest( '.dhps-news__body' ) && ! e.target.closest( '[data-dhps-collapse]' ) ) {
				var bodyId = card.getAttribute( 'data-dhps-card-toggle' );
				var body   = card.querySelector( '#' + bodyId ) || document.getElementById( bodyId );
				if ( body ) {
					var wasHidden = body.getAttribute( 'aria-hidden' ) === 'true';
					body.setAttribute( 'aria-hidden', wasHidden ? 'false' : 'true' );

					// Expanded-Klasse fuer Grid-Spanning (Fallback fuer :has()).
					card.classList.toggle( 'dhps-mio-card-article--expanded', wasHidden );
				}
				return;
			}

			// Compact-Layout: Klick auf Artikeltitel oeffnet den Volltext.
			var compactArticle = e.target.closest( '[data-dhps-compact-toggle]' );
			if ( compactArticle && ! e.target.closest( '.dhps-news__body' ) && ! e.target.closest( '[data-dhps-collapse]' ) ) {
				var compactBodyId = compactArticle.getAttribute( 'data-dhps-compact-toggle' );
				var compactBody   = document.getElementById( compactBodyId );
				if ( compactBody ) {
					var compactHidden = compactBody.getAttribute( 'aria-hidden' ) === 'true';
					compactBody.setAttribute( 'aria-hidden', compactHidden ? 'false' : 'true' );
				}
				return;
			}

			// Compact-Layout: Klick auf Gruppen-Header klappt Artikel auf/zu.
			var compactHeader = e.target.closest( '.dhps-mio-compact__group-header' );
			if ( compactHeader ) {
				var group    = compactHeader.closest( '.dhps-mio-compact__group' );
				var articles = group ? group.querySelector( '.dhps-mio-compact__articles' ) : null;
				var chevron  = compactHeader.querySelector( '.dhps-mio-compact__chevron' );

				if ( articles ) {
					var isOpen = articles.style.display !== 'none';
					articles.style.display = isOpen ? 'none' : '';

					if ( chevron ) {
						chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
					}
				}
				return;
			}

			// Ausblenden-Button.
			var collapseBtn = e.target.closest( '[data-dhps-collapse]' );
			if ( collapseBtn ) {
				var collapseId   = collapseBtn.getAttribute( 'data-dhps-collapse' );
				var collapseBody = document.getElementById( collapseId );
				if ( collapseBody ) {
					collapseBody.setAttribute( 'aria-hidden', 'true' );

					// Card-Expanded zuruecksetzen und zur Card scrollen.
					var parentCard = collapseBody.closest( '.dhps-mio-card-article' );
					if ( parentCard ) {
						parentCard.classList.remove( 'dhps-mio-card-article--expanded' );
						parentCard.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
					}

					// Compact-Artikel: zurueckscrollen.
					var parentCompact = collapseBody.closest( '.dhps-mio-compact__article' );
					if ( parentCompact ) {
						parentCompact.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
					}
				}
				var relatedToggle = container.querySelector( '[data-dhps-toggle="' + collapseId + '"]' );
				if ( relatedToggle && relatedToggle.getAttribute( 'aria-expanded' ) === 'true' ) {
					relatedToggle.setAttribute( 'aria-expanded', 'false' );
				}
				return;
			}

			// Drucken-Button.
			var printBtn = e.target.closest( '[data-dhps-print]' );
			if ( printBtn ) {
				printArticle( printBtn.getAttribute( 'data-dhps-print' ) );
			}
		} );
	}

	/**
	 * Toggled einen Artikel (Accordion).
	 *
	 * @param {HTMLElement} button Der Toggle-Button.
	 */
	function toggleArticle( button ) {
		var bodyId   = button.getAttribute( 'data-dhps-toggle' );
		var body     = document.getElementById( bodyId );
		var expanded = button.getAttribute( 'aria-expanded' ) === 'true';

		button.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );

		if ( body ) {
			body.setAttribute( 'aria-hidden', expanded ? 'true' : 'false' );
		}
	}

	/**
	 * Druckt einen einzelnen Artikel.
	 *
	 * @param {string} bodyId ID des Artikel-Body-Elements.
	 */
	function printArticle( bodyId ) {
		var body = document.getElementById( bodyId );

		if ( ! body ) {
			return;
		}

		var article = body.closest( '.dhps-news__article' );
		var title   = article ? article.querySelector( '.dhps-news__title' ) : null;
		var titleText = title ? title.textContent : '';

		var printWindow = window.open( '', '_blank', 'width=800,height=600' );

		if ( ! printWindow ) {
			return;
		}

		var content = body.cloneNode( true );
		var share   = content.querySelector( '.dhps-news__share' );
		var actions = content.querySelector( '.dhps-news__actions' );
		if ( share ) share.remove();
		if ( actions ) actions.remove();

		printWindow.document.write(
			'<!DOCTYPE html><html><head><meta charset="UTF-8">' +
			'<title>' + escapeHtml( titleText ) + '</title>' +
			'<style>body{font-family:Arial,sans-serif;max-width:700px;margin:40px auto;line-height:1.6;color:#333}' +
			'h1{font-size:18px;border-bottom:2px solid #333;padding-bottom:8px}' +
			'.dhps-news__meta{background:#f5f5f5;padding:8px 12px;margin-top:16px;font-size:13px}</style>' +
			'</head><body>' +
			'<h1>' + escapeHtml( titleText ) + '</h1>' +
			content.innerHTML +
			'</body></html>'
		);

		printWindow.document.close();
		printWindow.focus();
		printWindow.print();
	}

	/**
	 * Rendert eine Fehlermeldung.
	 *
	 * @param {HTMLElement} container Der News-Container.
	 * @param {string}      message   Fehlermeldung.
	 */
	function renderError( container, message ) {
		container.innerHTML =
			'<p class="dhps-news__error" role="alert">' +
			escapeHtml( message ) +
			'</p>';
	}

	/* =====================================================================
	   Escaping
	   ===================================================================== */

	/**
	 * Escaped HTML-Zeichen.
	 *
	 * @param {string} str Zu escapender String.
	 * @return {string} Escapeder String.
	 */
	function escapeHtml( str ) {
		var div       = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	/**
	 * Escaped einen String fuer die Verwendung in HTML-Attributen.
	 *
	 * @param {string} str Zu escapender String.
	 * @return {string} Escapeder String.
	 */
	function escapeAttr( str ) {
		return str
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	/* =====================================================================
	   Bootstrap
	   ===================================================================== */

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
