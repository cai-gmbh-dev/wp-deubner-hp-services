/**
 * Admin-Dashboard React-Komponenten fuer Deubner Homepage Services (v0.15.0).
 *
 * Architektur:
 * - React via wp.element (kein Build-Tool, kein JSX).
 * - UI via wp.components (Card, Button, Notice, Spinner, Panel, ToggleControl).
 * - REST-Calls via wp.apiFetch gegen Namespace `dhps/v1`.
 * - i18n via wp.i18n.__ in Textdomain `deubner_hp_services`.
 *
 * Mount-Point: <div id="dhps-admin-react-root"></div> (geliefert von dashboard.php).
 *
 * Komponenten-Inventar:
 *   App -> ServiceHealthList -> ServiceHealthCard (n=9)
 *       -> CacheStatsPanel
 *
 * REST-Endpoints (von F1 bereitgestellt):
 *   GET  /dhps/v1/services/health           -> Liste aller 9 Services
 *   GET  /dhps/v1/services/{slug}/health    -> Einzel-Service-Health
 *   POST /dhps/v1/services/{slug}/test      -> Test-Request (rate-limited)
 *   GET  /dhps/v1/cache/stats               -> Cache-Inventar
 *   POST /dhps/v1/cache/flush?service={s}   -> Cache leeren (optional gefiltert)
 *
 * @package    Deubner Homepage-Service
 * @subpackage Admin/JS/React
 * @since      0.15.0
 */

( function () {
	'use strict';

	// --- Defensive Bridges ----------------------------------------------------
	if ( typeof window === 'undefined' || typeof window.wp === 'undefined' ) {
		// Kein WP-Kontext - Skript ausserhalb von wp-admin geladen.
		return;
	}

	if ( ! window.wp.element || ! window.wp.components || ! window.wp.apiFetch || ! window.wp.i18n ) {
		// Fehlende Dependencies werden im DOMContentLoaded-Handler erneut geprueft.
		// Hier nicht abbrechen, damit der Error-Boundary unten greifen kann.
		// Aber: Komponenten-Definitionen unten benoetigen die Bridges. Wir loggen
		// hier, der DOMContentLoaded-Handler zeigt dann die Fallback-Notice.
		// eslint-disable-next-line no-console
		console.warn( '[dhps-admin-react] Erwartete WP-Bridges fehlen - Fallback wird gerendert.' );
	}

	var wp = window.wp;
	var el = wp.element || {};
	var components = wp.components || {};
	var i18n = wp.i18n || { __: function ( s ) { return s; } };
	var apiFetch = wp.apiFetch;

	var h = el.createElement;
	var useState = el.useState;
	var useEffect = el.useEffect;
	var useCallback = el.useCallback;
	var Fragment = el.Fragment;

	var Panel = components.Panel;
	var PanelBody = components.PanelBody;
	var Button = components.Button;
	var Notice = components.Notice;
	var Spinner = components.Spinner;
	var Card = components.Card;
	var CardBody = components.CardBody;
	var CardHeader = components.CardHeader;
	var Flex = components.Flex;
	var FlexItem = components.FlexItem;
	var Text = components.__experimentalText || components.Text || 'span';

	var __ = i18n.__;

	// --- Helpers --------------------------------------------------------------

	/**
	 * Formatiere Bytes als KB / MB.
	 * @param {number} bytes
	 * @returns {string}
	 */
	function formatBytes( bytes ) {
		if ( typeof bytes !== 'number' || bytes <= 0 ) {
			return '0 B';
		}
		if ( bytes < 1024 ) {
			return bytes + ' B';
		}
		if ( bytes < 1024 * 1024 ) {
			return ( bytes / 1024 ).toFixed( 1 ) + ' KB';
		}
		return ( bytes / ( 1024 * 1024 ) ).toFixed( 2 ) + ' MB';
	}

	/**
	 * Formatiere Sekunden als h:m:s oder Sekunden.
	 * @param {number} seconds
	 * @returns {string}
	 */
	function formatDuration( seconds ) {
		if ( typeof seconds !== 'number' || seconds < 0 ) {
			return '-';
		}
		if ( seconds < 60 ) {
			return seconds + ' s';
		}
		if ( seconds < 3600 ) {
			return Math.floor( seconds / 60 ) + ' min';
		}
		return Math.floor( seconds / 3600 ) + ' h';
	}

	/**
	 * Truncate URL fuer kompakte Anzeige.
	 * @param {string} url
	 * @param {number} max
	 * @returns {string}
	 */
	function truncate( url, max ) {
		if ( typeof url !== 'string' ) {
			return '';
		}
		max = max || 60;
		if ( url.length <= max ) {
			return url;
		}
		return url.substr( 0, max - 3 ) + '...';
	}

	/**
	 * Sekunden seit Timestamp.
	 * @param {number} ts UNIX-Timestamp.
	 * @returns {number}
	 */
	function secondsSince( ts ) {
		if ( ! ts ) {
			return 0;
		}
		return Math.max( 0, Math.floor( Date.now() / 1000 ) - Number( ts ) );
	}

	/**
	 * Kleiner Status-Punkt (gruen/rot/grau).
	 * @param {string} kind 'ok' | 'fail' | 'neutral'
	 * @param {string} label aria-label.
	 * @returns {object} React-Element
	 */
	function StatusDot( kind, label ) {
		var color = '#8c8f94'; // neutral / grau
		if ( kind === 'ok' ) {
			color = '#00a32a';
		} else if ( kind === 'fail' ) {
			color = '#d63638';
		}
		return h( 'span', {
			'aria-label': label || '',
			role: 'img',
			style: {
				display: 'inline-block',
				width: '10px',
				height: '10px',
				borderRadius: '50%',
				backgroundColor: color,
				marginRight: '6px',
				verticalAlign: 'middle',
			},
		} );
	}

	/**
	 * Branding-Farbe pro Service (kleiner Indikator).
	 * Fallback: grau.
	 * @param {string} slug Service-Slug.
	 * @returns {string} CSS-Farbe.
	 */
	function brandingColor( slug ) {
		var map = {
			mio: '#2e7d32',
			lxmio: '#0054A6',
			mmb: '#2e7d32',
			mil: '#2e7d32',
			tp: '#2e7d32',
			tpt: '#2e7d32',
			tc: '#2e7d32',
			maes: '#00897b',
			lp: '#0054A6',
		};
		return map[ slug ] || '#8c8f94';
	}

	// --- Komponenten ----------------------------------------------------------

	/**
	 * ServiceHealthCard - Karte fuer einen Service.
	 *
	 * Props:
	 *   service {object} - Service-Health-Object aus REST.
	 *       slug, name, ota_configured, ota_preview, api_reachable,
	 *       endpoint, demo_status, health_score, ...
	 */
	function ServiceHealthCard( props ) {
		var service = props.service || {};
		var slug = service.slug || 'unknown';

		var stateExpanded = useState( false );
		var expanded = stateExpanded[ 0 ];
		var setExpanded = stateExpanded[ 1 ];

		var stateTesting = useState( false );
		var testing = stateTesting[ 0 ];
		var setTesting = stateTesting[ 1 ];

		var stateResult = useState( null );
		var testResult = stateResult[ 0 ];
		var setTestResult = stateResult[ 1 ];

		var stateError = useState( null );
		var testError = stateError[ 0 ];
		var setTestError = stateError[ 1 ];

		var runTest = useCallback( function () {
			setTesting( true );
			setTestError( null );
			setTestResult( null );

			apiFetch( {
				path: '/dhps/v1/services/' + encodeURIComponent( slug ) + '/test',
				method: 'POST',
			} ).then( function ( result ) {
				setTestResult( result );
			} ).catch( function ( err ) {
				// eslint-disable-next-line no-console
				console.error( '[dhps-admin-react] Test-Request fehlgeschlagen:', slug, err );
				setTestError( ( err && err.message ) ? err.message : __( 'Unbekannter Fehler.', 'deubner_hp_services' ) );
			} ).finally( function () {
				setTesting( false );
			} );
		}, [ slug ] );

		var titleId = 'dhps-svc-card-title-' + slug;
		var serviceName = service.name || slug.toUpperCase();
		var otaConfigured = !! service.ota_configured;
		var apiReachable = !! service.api_reachable;
		var otaPreview = service.ota_preview || ( otaConfigured ? 'OTA-***' : __( 'Kein Token gesetzt', 'deubner_hp_services' ) );

		// Header: Branding-Punkt + Service-Name.
		var header = h( CardHeader, {},
			h( Flex, { align: 'center', justify: 'space-between' },
				h( FlexItem, {},
					h( 'span', {
						style: {
							display: 'inline-block',
							width: '12px',
							height: '12px',
							borderRadius: '2px',
							backgroundColor: brandingColor( slug ),
							marginRight: '8px',
							verticalAlign: 'middle',
						},
						'aria-hidden': 'true',
					} ),
					h( 'strong', { id: titleId }, serviceName ),
					h( 'span', {
						style: { marginLeft: '8px', color: '#646970', fontSize: '12px' },
					}, '(' + slug + ')' )
				),
				h( FlexItem, {},
					h( Button, {
						variant: 'tertiary',
						isSmall: true,
						onClick: function () { setExpanded( ! expanded ); },
						'aria-expanded': expanded ? 'true' : 'false',
						'aria-controls': 'dhps-svc-details-' + slug,
					}, expanded ? __( 'Weniger', 'deubner_hp_services' ) : __( 'Details', 'deubner_hp_services' ) )
				)
			)
		);

		// Body: OTA, API-Erreichbarkeit, Endpoint.
		var rows = [];

		// OTA-Status.
		rows.push( h( 'div', { key: 'ota', style: { marginBottom: '6px' } },
			StatusDot( otaConfigured ? 'ok' : 'fail', otaConfigured ? __( 'OTA gesetzt', 'deubner_hp_services' ) : __( 'OTA fehlt', 'deubner_hp_services' ) ),
			h( 'span', {}, __( 'OTA: ', 'deubner_hp_services' ) ),
			h( 'code', { style: { fontSize: '12px' } }, expanded && service.ota_full ? service.ota_full : otaPreview )
		) );

		// API-Erreichbarkeit.
		rows.push( h( 'div', { key: 'api', style: { marginBottom: '6px' } },
			StatusDot( apiReachable ? 'ok' : 'fail', apiReachable ? __( 'Erreichbar', 'deubner_hp_services' ) : __( 'Nicht erreichbar', 'deubner_hp_services' ) ),
			h( 'span', {}, apiReachable ? __( 'API erreichbar', 'deubner_hp_services' ) : __( 'API nicht erreichbar', 'deubner_hp_services' ) )
		) );

		// Endpoint (truncated).
		if ( service.endpoint ) {
			rows.push( h( 'div', { key: 'ep', style: { marginBottom: '6px', fontSize: '12px', color: '#646970' } },
				h( 'span', {}, __( 'Endpoint: ', 'deubner_hp_services' ) ),
				h( 'code', { title: service.endpoint }, truncate( service.endpoint, expanded ? 200 : 50 ) )
			) );
		}

		// Demo-Status (falls vorhanden).
		if ( service.demo_status ) {
			rows.push( h( 'div', { key: 'demo', style: { marginBottom: '6px', fontSize: '12px' } },
				h( 'span', {}, __( 'Status: ', 'deubner_hp_services' ) ),
				h( 'strong', {}, service.demo_status )
			) );
		}

		// Health-Score (falls vorhanden).
		if ( typeof service.health_score === 'number' ) {
			var scoreColor = '#d63638';
			if ( service.health_score >= 100 ) {
				scoreColor = '#00a32a';
			} else if ( service.health_score >= 50 ) {
				scoreColor = '#dba617';
			}
			rows.push( h( 'div', { key: 'score', style: { marginBottom: '6px' } },
				h( 'span', {}, __( 'Health: ', 'deubner_hp_services' ) ),
				h( 'strong', { style: { color: scoreColor } }, service.health_score )
			) );
		}

		// Test-Button + Loading.
		var testRow = h( 'div', { style: { marginTop: '12px' } },
			h( Button, {
				variant: 'secondary',
				onClick: runTest,
				isBusy: testing,
				disabled: testing,
				'aria-label': __( 'Test-Request senden fuer ', 'deubner_hp_services' ) + serviceName,
			}, testing
				? __( 'Teste...', 'deubner_hp_services' )
				: __( 'Test-Request senden', 'deubner_hp_services' )
			),
			testing ? h( 'span', { style: { marginLeft: '8px' } }, h( Spinner, {} ) ) : null
		);

		// Test-Result-Notice.
		var resultNode = null;
		if ( testResult ) {
			var ok = !! testResult.success;
			var summary = __( 'HTTP ', 'deubner_hp_services' ) + ( testResult.http_status || testResult.http_code || '-' ) +
				' | ' + formatBytes( testResult.bytes || 0 ) +
				' | ' + ( testResult.duration_ms || testResult.response_time_ms || 0 ) + ' ms' +
				' | ' + ( testResult.cache_hit ? __( 'Cache-Hit', 'deubner_hp_services' ) : __( 'Live', 'deubner_hp_services' ) );

			resultNode = h( 'div', { role: 'status', 'aria-live': 'polite', style: { marginTop: '10px' } },
				h( Notice, {
					status: ok ? 'success' : 'warning',
					isDismissible: true,
					onRemove: function () { setTestResult( null ); },
				}, summary )
			);
		}

		var errorNode = null;
		if ( testError ) {
			errorNode = h( 'div', { role: 'alert', style: { marginTop: '10px' } },
				h( Notice, {
					status: 'error',
					isDismissible: true,
					onRemove: function () { setTestError( null ); },
				}, testError )
			);
		}

		return h( 'section', {
			'aria-labelledby': titleId,
			'aria-busy': testing ? 'true' : 'false',
			className: 'dhps-react-svc-card dhps-react-svc-card--' + slug,
			style: { marginBottom: '12px' },
		},
			h( Card, {},
				header,
				h( CardBody, { id: 'dhps-svc-details-' + slug },
					rows,
					testRow,
					resultNode,
					errorNode
				)
			)
		);
	}

	/**
	 * ServiceHealthList - Container fuer alle Service-Karten.
	 */
	function ServiceHealthList() {
		var stateServices = useState( [] );
		var services = stateServices[ 0 ];
		var setServices = stateServices[ 1 ];

		var stateLoading = useState( true );
		var loading = stateLoading[ 0 ];
		var setLoading = stateLoading[ 1 ];

		var stateError = useState( null );
		var error = stateError[ 0 ];
		var setError = stateError[ 1 ];

		var stateRefresh = useState( 0 );
		var lastRefresh = stateRefresh[ 0 ];
		var setLastRefresh = stateRefresh[ 1 ];

		var load = useCallback( function () {
			setLoading( true );
			setError( null );

			apiFetch( {
				path: '/dhps/v1/services/health',
				method: 'GET',
			} ).then( function ( data ) {
				// Akzeptiere sowohl Array als auch { services: [...] }.
				var list = Array.isArray( data ) ? data : ( data && Array.isArray( data.services ) ? data.services : [] );
				setServices( list );
				setLastRefresh( Math.floor( Date.now() / 1000 ) );
			} ).catch( function ( err ) {
				// eslint-disable-next-line no-console
				console.error( '[dhps-admin-react] Health-Load fehlgeschlagen:', err );
				setError( ( err && err.message ) ? err.message : __( 'Health-Daten konnten nicht geladen werden.', 'deubner_hp_services' ) );
			} ).finally( function () {
				setLoading( false );
			} );
		}, [] );

		useEffect( function () {
			load();
		}, [ load ] );

		var header = h( Flex, { align: 'center', justify: 'space-between', style: { marginBottom: '12px' } },
			h( FlexItem, {},
				h( 'h2', { style: { margin: 0 } }, __( 'Service-Health', 'deubner_hp_services' ) ),
				lastRefresh ? h( 'small', { style: { color: '#646970', marginLeft: '8px' } },
					__( 'Geprueft vor ', 'deubner_hp_services' ) + secondsSince( lastRefresh ) + __( ' s', 'deubner_hp_services' )
				) : null
			),
			h( FlexItem, {},
				h( Button, {
					variant: 'secondary',
					onClick: load,
					disabled: loading,
					isBusy: loading,
					'aria-label': __( 'Service-Health neu laden', 'deubner_hp_services' ),
				}, loading ? __( 'Laedt...', 'deubner_hp_services' ) : __( 'Aktualisieren', 'deubner_hp_services' ) )
			)
		);

		var body;
		if ( loading && services.length === 0 ) {
			body = h( 'div', { 'aria-busy': 'true', style: { padding: '12px' } },
				h( Spinner, {} ),
				h( 'span', { style: { marginLeft: '8px' } }, __( 'Lade Service-Health...', 'deubner_hp_services' ) )
			);
		} else if ( error ) {
			body = h( 'div', { role: 'alert' },
				h( Notice, { status: 'error', isDismissible: false }, error )
			);
		} else if ( services.length === 0 ) {
			body = h( Notice, { status: 'warning', isDismissible: false },
				__( 'Keine Services gemeldet.', 'deubner_hp_services' )
			);
		} else {
			body = services.map( function ( svc ) {
				return h( ServiceHealthCard, { key: svc.slug || Math.random(), service: svc } );
			} );
		}

		return h( 'div', { className: 'dhps-react-health-list' },
			header,
			body
		);
	}

	/**
	 * CacheStatsPanel - Anzeige Cache-Metriken + Flush-Button.
	 */
	function CacheStatsPanel() {
		var stateStats = useState( null );
		var stats = stateStats[ 0 ];
		var setStats = stateStats[ 1 ];

		var stateLoading = useState( true );
		var loading = stateLoading[ 0 ];
		var setLoading = stateLoading[ 1 ];

		var stateError = useState( null );
		var error = stateError[ 0 ];
		var setError = stateError[ 1 ];

		var stateFlush = useState( false );
		var flushing = stateFlush[ 0 ];
		var setFlushing = stateFlush[ 1 ];

		var stateFlushNotice = useState( null );
		var flushNotice = stateFlushNotice[ 0 ];
		var setFlushNotice = stateFlushNotice[ 1 ];

		var stateChecked = useState( 0 );
		var lastChecked = stateChecked[ 0 ];
		var setLastChecked = stateChecked[ 1 ];

		var loadStats = useCallback( function () {
			setLoading( true );
			setError( null );

			apiFetch( {
				path: '/dhps/v1/cache/stats',
				method: 'GET',
			} ).then( function ( data ) {
				setStats( data || {} );
				setLastChecked( Math.floor( Date.now() / 1000 ) );
			} ).catch( function ( err ) {
				// eslint-disable-next-line no-console
				console.error( '[dhps-admin-react] Cache-Stats fehlgeschlagen:', err );
				setError( ( err && err.message ) ? err.message : __( 'Cache-Statistik konnte nicht geladen werden.', 'deubner_hp_services' ) );
			} ).finally( function () {
				setLoading( false );
			} );
		}, [] );

		useEffect( function () {
			loadStats();
		}, [ loadStats ] );

		var flushCache = useCallback( function () {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( __( 'Wirklich den gesamten Cache leeren? Diese Aktion kann nicht rueckgaengig gemacht werden.', 'deubner_hp_services' ) ) ) {
				return;
			}
			setFlushing( true );
			setFlushNotice( null );

			apiFetch( {
				path: '/dhps/v1/cache/flush',
				method: 'POST',
			} ).then( function () {
				setFlushNotice( { status: 'success', text: __( 'Cache wurde geleert.', 'deubner_hp_services' ) } );
				loadStats();
			} ).catch( function ( err ) {
				// eslint-disable-next-line no-console
				console.error( '[dhps-admin-react] Cache-Flush fehlgeschlagen:', err );
				setFlushNotice( {
					status: 'error',
					text: ( err && err.message ) ? err.message : __( 'Cache-Flush fehlgeschlagen.', 'deubner_hp_services' ),
				} );
			} ).finally( function () {
				setFlushing( false );
			} );
		}, [ loadStats ] );

		// Felder defensiv lesen (verschiedene Backend-Schemas).
		var totalEntries = stats ? ( stats.total_entries || stats.entries || 0 ) : 0;
		var totalBytes = stats ? ( stats.total_bytes || stats.bytes || 0 ) : 0;
		var humanSize = stats ? ( stats.human_size || formatBytes( totalBytes ) ) : formatBytes( 0 );
		var nextExpiry = stats ? ( stats.next_expiry_in || stats.next_expires_in || 0 ) : 0;

		var body;
		if ( loading && ! stats ) {
			body = h( 'div', { 'aria-busy': 'true' },
				h( Spinner, {} ),
				h( 'span', { style: { marginLeft: '8px' } }, __( 'Lade Cache-Statistik...', 'deubner_hp_services' ) )
			);
		} else if ( error ) {
			body = h( 'div', { role: 'alert' },
				h( Notice, { status: 'error', isDismissible: false }, error )
			);
		} else {
			body = h( Fragment, {},
				h( Flex, { wrap: true, gap: 4, style: { marginBottom: '12px' } },
					h( FlexItem, {},
						h( Text, { variant: 'muted' }, __( 'Transients aktiv', 'deubner_hp_services' ) ),
						h( 'div', { style: { fontSize: '20px', fontWeight: '600' } }, totalEntries )
					),
					h( FlexItem, {},
						h( Text, { variant: 'muted' }, __( 'Cache-Groesse', 'deubner_hp_services' ) ),
						h( 'div', { style: { fontSize: '20px', fontWeight: '600' } }, humanSize )
					),
					h( FlexItem, {},
						h( Text, { variant: 'muted' }, __( 'Letzte Pruefung', 'deubner_hp_services' ) ),
						h( 'div', { style: { fontSize: '14px' } },
							__( 'vor ', 'deubner_hp_services' ) + secondsSince( lastChecked ) + __( ' s', 'deubner_hp_services' )
						)
					),
					nextExpiry > 0 ? h( FlexItem, {},
						h( Text, { variant: 'muted' }, __( 'Naechster Ablauf in', 'deubner_hp_services' ) ),
						h( 'div', { style: { fontSize: '14px' } }, formatDuration( nextExpiry ) )
					) : null
				),
				h( Flex, { gap: 2 },
					h( FlexItem, {},
						h( Button, {
							variant: 'secondary',
							onClick: loadStats,
							disabled: loading,
							isBusy: loading,
							'aria-label': __( 'Cache-Statistik aktualisieren', 'deubner_hp_services' ),
						}, __( 'Aktualisieren', 'deubner_hp_services' ) )
					),
					h( FlexItem, {},
						h( Button, {
							variant: 'primary',
							isDestructive: true,
							onClick: flushCache,
							disabled: flushing,
							isBusy: flushing,
							'aria-label': __( 'Gesamten Cache leeren', 'deubner_hp_services' ),
						}, flushing ? __( 'Leere...', 'deubner_hp_services' ) : __( 'Cache leeren', 'deubner_hp_services' ) )
					)
				),
				flushNotice ? h( 'div', {
					role: flushNotice.status === 'error' ? 'alert' : 'status',
					'aria-live': flushNotice.status === 'error' ? 'assertive' : 'polite',
					style: { marginTop: '10px' },
				},
					h( Notice, {
						status: flushNotice.status,
						isDismissible: true,
						onRemove: function () { setFlushNotice( null ); },
					}, flushNotice.text )
				) : null
			);
		}

		return h( 'section', {
			'aria-labelledby': 'dhps-cache-panel-title',
			'aria-busy': ( loading || flushing ) ? 'true' : 'false',
			className: 'dhps-react-cache-panel',
		},
			h( Panel, {},
				h( PanelBody, {
					title: __( 'Cache-Statistik', 'deubner_hp_services' ),
					initialOpen: true,
				},
					h( 'h2', {
						id: 'dhps-cache-panel-title',
						className: 'screen-reader-text',
						style: { position: 'absolute', left: '-9999px' },
					}, __( 'Cache-Statistik', 'deubner_hp_services' ) ),
					body
				)
			)
		);
	}

	/**
	 * App - Root-Komponente.
	 */
	function App() {
		var stateView = useState( 'dashboard' );
		var view = stateView[ 0 ];
		// Aktuell nur 'dashboard' - Hook bleibt fuer spaetere Erweiterungen (Tabs).

		return h( 'div', {
			className: 'dhps-react-app dhps-react-app--' + view,
			'data-current-view': view,
		},
			h( Panel, { className: 'dhps-react-panel' },
				h( PanelBody, {
					title: __( 'Service-Health-Monitor', 'deubner_hp_services' ),
					initialOpen: true,
				},
					h( ServiceHealthList, {} )
				)
			),
			h( 'div', { style: { height: '16px' } } ),
			h( CacheStatsPanel, {} )
		);
	}

	// --- Bootstrap / Mount / Error-Boundary -----------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.getElementById( 'dhps-admin-react-root' );
		if ( ! root ) {
			// Nicht auf der Dashboard-Page - sauberer No-Op.
			return;
		}

		if ( typeof wp === 'undefined' || ! wp.element || ! wp.components || ! wp.apiFetch ) {
			// eslint-disable-next-line no-console
			console.error( '[dhps-admin-react] WordPress React-Bibliotheken nicht verfuegbar.' );
			root.innerHTML = '<div class="notice notice-error" role="alert"><p>' +
				'WordPress React-Bibliothek nicht verfuegbar. Bitte WordPress aktualisieren.' +
				'</p></div>';
			return;
		}

		// Optional: Nonce-Middleware fuer apiFetch konfigurieren, falls Localize vorhanden.
		try {
			if ( window.dhpsAdminConfig && window.dhpsAdminConfig.restNonce && wp.apiFetch.createNonceMiddleware ) {
				wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( window.dhpsAdminConfig.restNonce ) );
			}
			if ( window.dhpsAdminConfig && window.dhpsAdminConfig.restUrl && wp.apiFetch.createRootURLMiddleware ) {
				wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( window.dhpsAdminConfig.restUrl.replace( /dhps\/v1\/?$/, '' ) ) );
			}
		} catch ( e ) {
			// eslint-disable-next-line no-console
			console.warn( '[dhps-admin-react] apiFetch-Middleware konnte nicht initialisiert werden:', e );
		}

		try {
			// Marker fuer manuelles Browser-Testing.
			root.setAttribute( 'data-dhps-admin-react', 'mounted' );

			if ( typeof wp.element.render === 'function' ) {
				wp.element.render( h( App ), root );
			} else if ( wp.element.createRoot ) {
				// React 18 createRoot-Fallback.
				wp.element.createRoot( root ).render( h( App ) );
			} else {
				throw new Error( 'Weder wp.element.render noch wp.element.createRoot verfuegbar.' );
			}

			// eslint-disable-next-line no-console
			console.log( '[dhps-admin-react] Dashboard gemountet.' );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( '[dhps-admin-react] Render-Fehler:', err );
			root.setAttribute( 'data-dhps-admin-react', 'error' );
			root.innerHTML = '<div class="notice notice-error" role="alert"><p>' +
				'Fehler beim Laden des Dashboards. Details in der Browser-Konsole.' +
				'</p></div>';
		}
	} );

} )();
