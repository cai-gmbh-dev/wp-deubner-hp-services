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
 *   POST /dhps/v1/services/{slug}/preview   -> Live-Preview (rate-limited, v0.15.3)
 *
 * v0.15.3 - Live-Preview-Erweiterung:
 *   App -> LivePreviewPanel -> LivePreviewControls + LivePreviewIframe + LivePreviewMeta
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
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;

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

	// --- Live-Preview (v0.15.3) ----------------------------------------------

	/**
	 * Service-Whitelist (9 Haupt-Services + 4 Sub-Shortcodes seit v0.15.4).
	 *
	 * Muss synchron mit ALLOWED_SERVICES in class-dhps-admin-rest.php gehalten
	 * werden. Backend rejected unbekannte Slugs zusaetzlich (Defense-in-Depth).
	 */
	var PREVIEW_SERVICES = [
		{ value: 'mio', label: 'MIO (Steuern)' },
		{ value: 'mio_termine', label: 'MIO Termine (Sub)' },
		{ value: 'lxmio', label: 'LXMIO (Recht)' },
		{ value: 'mmb', label: 'MMB (Merkblaetter)' },
		{ value: 'mil', label: 'MIL (Lohn)' },
		{ value: 'tp', label: 'TP (TaxPlain Videos)' },
		{ value: 'tpt', label: 'TPT (TP Teaser)' },
		{ value: 'tc', label: 'TC (Tax-Rechner)' },
		{ value: 'maes', label: 'MAES (Medizin)' },
		{ value: 'maes_videos', label: 'MAES Videos (Sub)' },
		{ value: 'maes_merkblaetter', label: 'MAES Merkblaetter (Sub)' },
		{ value: 'maes_aktuelles', label: 'MAES Aktuelles (Sub)' },
		{ value: 'lp', label: 'LP (LexPlain Videos)' },
	];

	/**
	 * Sub-Shortcodes (preview-faehig in v0.15.4 - eingeschraenkte Atts).
	 *
	 * Wird intern fuer Conditional-UI verwendet (z.B. section-Auswahl nur fuer
	 * maes_*-Sub-Shortcodes).
	 *
	 * @since v0.15.4
	 */
	var PREVIEW_SUB_SHORTCODES = [
		'mio_termine',
		'maes_videos',
		'maes_merkblaetter',
		'maes_aktuelles',
	];

	/**
	 * Prueft ob ein Service-Slug ein maes_*-Sub-Shortcode ist (oder MAES selbst).
	 *
	 * @param {string} slug Service-Slug.
	 * @returns {boolean} true wenn maes_*-Familie.
	 */
	function isMaesFamily( slug ) {
		return slug === 'maes' || ( typeof slug === 'string' && slug.indexOf( 'maes_' ) === 0 );
	}

	var PREVIEW_LAYOUTS = [
		{ value: 'default', label: 'default' },
		{ value: 'card', label: 'card' },
		{ value: 'compact', label: 'compact' },
	];

	var PREVIEW_MAES_SECTIONS = [
		{ value: '', label: '(alle)' },
		{ value: 'videos', label: 'videos' },
		{ value: 'merkblaetter', label: 'merkblaetter' },
		{ value: 'aktuelles', label: 'aktuelles' },
	];

	// --- Atts-Editor (v0.15.5) ------------------------------------------------

	/**
	 * Liefert das Schema-Object fuer einen Service aus der wp_localize_script-Bridge.
	 *
	 * Defensive-Read: Wenn dhpsAdminConfig.attsSchema fehlt (alter Cache, Bridge
	 * noch nicht ausgerollt), wird ein leeres Object zurueckgegeben - dann wird
	 * LivePreviewAttsForm nichts rendern und der Editor faellt auf v0.15.4-
	 * Verhalten zurueck (nur layout/class/section/cache via LivePreviewControls).
	 *
	 * @since v0.15.5
	 *
	 * @param {string} service Service-Slug.
	 * @returns {object} Schema-Map { attName: { type, default, ... } } oder {}.
	 */
	function getServiceSchema( service ) {
		var bridge = ( typeof window !== 'undefined' && window.dhpsAdminConfig )
			? window.dhpsAdminConfig
			: {};
		var schema = ( bridge && bridge.attsSchema && typeof bridge.attsSchema === 'object' )
			? bridge.attsSchema
			: {};
		return ( schema && service && schema[ service ] && typeof schema[ service ] === 'object' )
			? schema[ service ]
			: {};
	}

	/**
	 * Baut das Default-Atts-Object fuer einen Service aus dem Schema.
	 *
	 * Wird beim Service-Wechsel aufgerufen, damit alte service-spezifische Atts
	 * (z.B. 'einzelvideo' aus TP) nicht in den neuen Service-Kontext leaken und
	 * dort als "unknown att key" rejected werden.
	 *
	 * @since v0.15.5
	 *
	 * @param {string} service Service-Slug.
	 * @returns {object} Atts-Map mit Schema-Defaults.
	 */
	function buildDefaultAtts( service ) {
		var schema = getServiceSchema( service );
		var atts = {};
		Object.keys( schema ).forEach( function ( k ) {
			var def = schema[ k ] || {};
			atts[ k ] = ( def['default'] !== undefined ) ? def['default'] : '';
		} );
		// BC: Wenn das Schema layout/class/section nicht hat (sollte nie passieren),
		// trotzdem Defaults setzen.
		if ( atts.layout === undefined ) { atts.layout = 'default'; }
		if ( atts['class'] === undefined ) { atts['class'] = ''; }
		return atts;
	}

	/**
	 * AttFieldString - TextControl fuer type=string.
	 *
	 * Props:
	 *   name {string}     Att-Name.
	 *   def {object}      Schema-Definition (type, default, label, description, ...).
	 *   value {string}    Aktueller Wert.
	 *   onChange {function} (name, value) => void.
	 */
	function AttFieldString( props ) {
		var name = props.name;
		var def = props.def || {};
		var value = ( props.value !== undefined && props.value !== null ) ? String( props.value ) : '';
		var onChange = props.onChange || function () {};
		var label = def.label || name;

		return h( TextControl, {
			label: label,
			value: value,
			onChange: function ( val ) { onChange( name, val ); },
			help: def.description || null,
			'aria-label': label + ' (Att ' + name + ')',
		} );
	}

	/**
	 * AttFieldInt - TextControl mit type=number fuer type=int.
	 *
	 * Verwendet HTML-min/max-Attribute (Browser-Hinweis), das Backend
	 * validiert die Grenzen erneut via SERVICE_ATTS_SCHEMA.
	 *
	 * Props (siehe AttFieldString).
	 */
	function AttFieldInt( props ) {
		var name = props.name;
		var def = props.def || {};
		var defVal = ( def['default'] !== undefined ) ? def['default'] : 0;
		var raw = ( props.value !== undefined && props.value !== null ) ? props.value : defVal;
		var value = String( raw );
		var onChange = props.onChange || function () {};
		var label = def.label || name;
		var rangeHint = '';
		if ( typeof def.min === 'number' && typeof def.max === 'number' ) {
			rangeHint = ' (' + def.min + '..' + def.max + ')';
		}

		return h( TextControl, {
			label: label + rangeHint,
			type: 'number',
			min: ( typeof def.min === 'number' ) ? def.min : undefined,
			max: ( typeof def.max === 'number' ) ? def.max : undefined,
			value: value,
			onChange: function ( val ) {
				// Browser liefert string; wir geben string an Backend (PHP castet).
				onChange( name, val );
			},
			help: def.description || null,
			'aria-label': label + ' (Att ' + name + ')',
		} );
	}

	/**
	 * AttFieldBool - ToggleControl fuer type=bool.
	 *
	 * Backend erwartet '0' / '1' als String - das wird hier beim onChange
	 * explizit gemappt (siehe Schema-Vertrag Sektion 3.3 R10).
	 *
	 * Props (siehe AttFieldString).
	 */
	function AttFieldBool( props ) {
		var name = props.name;
		var def = props.def || {};
		var raw = props.value;
		// Akzeptiere true/false, '1'/'0', 1/0.
		var checked = ( raw === true || raw === '1' || raw === 1 );
		var onChange = props.onChange || function () {};
		var label = def.label || name;

		return h( ToggleControl, {
			label: label,
			checked: checked,
			onChange: function ( val ) {
				onChange( name, val ? '1' : '0' );
			},
			help: def.description || null,
		} );
	}

	/**
	 * AttFieldSelect - SelectControl fuer type=select.
	 *
	 * options sind im Schema-Vertrag (Sektion 3.1) immer Array von
	 * {value, label}-Objekten - kein flaches Array.
	 *
	 * Props (siehe AttFieldString).
	 */
	function AttFieldSelect( props ) {
		var name = props.name;
		var def = props.def || {};
		var defVal = ( def['default'] !== undefined ) ? String( def['default'] ) : '';
		var value = ( props.value !== undefined && props.value !== null ) ? String( props.value ) : defVal;
		var onChange = props.onChange || function () {};
		var label = def.label || name;
		var options = Array.isArray( def.options ) ? def.options : [];

		return h( SelectControl, {
			label: label,
			value: value,
			options: options,
			onChange: function ( val ) { onChange( name, val ); },
			help: def.description || null,
			'aria-label': label + ' (Att ' + name + ')',
		} );
	}

	/**
	 * renderAttField - dispatcher fuer einzelnen Att-Field-Render.
	 *
	 * @param {string} name      Att-Name.
	 * @param {object} def       Schema-Definition.
	 * @param {*}      value     Aktueller Wert.
	 * @param {function} onChange (name, val) => void.
	 * @returns {object|null} React-Element oder null.
	 */
	function renderAttField( name, def, value, onChange ) {
		if ( ! def || typeof def.type !== 'string' ) {
			return null;
		}
		var props = { key: name, name: name, def: def, value: value, onChange: onChange };
		switch ( def.type ) {
			case 'string':
				return h( AttFieldString, props );
			case 'int':
				return h( AttFieldInt, props );
			case 'bool':
				return h( AttFieldBool, props );
			case 'select':
				return h( AttFieldSelect, props );
			default:
				return null;
		}
	}

	/**
	 * LivePreviewAttsForm - Container fuer service-spezifische Atts.
	 *
	 * Liest das Schema aus window.dhpsAdminConfig.attsSchema (von
	 * wp_localize_script bereitgestellt) und rendert nur Felder mit
	 * group='service_specific'. Universal-Felder (layout/class/cache)
	 * bleiben in LivePreviewControls.
	 *
	 * Wenn Schema fehlt oder keine service_specific-Eintraege da sind
	 * (z.B. TC, maes_merkblaetter), rendert die Komponente null.
	 *
	 * Props:
	 *   service {string}     Aktueller Service-Slug.
	 *   atts {object}        Aktuelle Atts-Werte.
	 *   onAttChange {function} (name, value) => void.
	 *
	 * @since v0.15.5
	 */
	function LivePreviewAttsForm( props ) {
		var service = props.service;
		var atts = props.atts || {};
		var onAttChange = props.onAttChange || function () {};

		var schema = getServiceSchema( service );

		// Nur service_specific-Atts rendern.
		var serviceAttNames = Object.keys( schema ).filter( function ( key ) {
			var def = schema[ key ];
			return def && def.group === 'service_specific';
		} );

		if ( serviceAttNames.length === 0 ) {
			return null;
		}

		var fields = serviceAttNames.map( function ( attName ) {
			return renderAttField( attName, schema[ attName ], atts[ attName ], onAttChange );
		} ).filter( function ( node ) { return node !== null; } );

		if ( fields.length === 0 ) {
			return null;
		}

		return h( 'div', {
			className: 'dhps-react-atts-form',
			style: {
				marginTop: '12px',
				padding: '12px',
				border: '1px solid #e0e0e0',
				borderRadius: '4px',
				backgroundColor: '#fafafa',
			},
		},
			h( 'div', {
				style: {
					fontSize: '12px',
					fontWeight: '600',
					color: '#646970',
					marginBottom: '8px',
					textTransform: 'uppercase',
					letterSpacing: '0.5px',
				},
			}, __( 'Service-spezifische Atts (', 'deubner_hp_services' ) + service + ')' ),
			h( Flex, { wrap: true, gap: 4, align: 'flex-end' },
				fields.map( function ( field ) {
					return h( FlexItem, { key: field.key }, field );
				} )
			)
		);
	}

	/**
	 * LivePreviewControls - Service/Layout/Class/Section + Render-Button.
	 *
	 * Props:
	 *   service {string}                Aktueller Service-Slug.
	 *   atts {object}                   {layout, class, section}.
	 *   onServiceChange {function}      (slug) => void.
	 *   onAttsChange {function}         (atts) => void.
	 *   onRun {function}                () => void.
	 *   loading {boolean}               Render-Loading-State.
	 */
	function LivePreviewControls( props ) {
		var service = props.service || 'mio';
		var atts = props.atts || {};
		var onServiceChange = props.onServiceChange || function () {};
		var onAttsChange = props.onAttsChange || function () {};
		var onRun = props.onRun || function () {};
		var loading = !! props.loading;

		function patchAtts( patch ) {
			var next = {};
			Object.keys( atts ).forEach( function ( k ) { next[ k ] = atts[ k ]; } );
			Object.keys( patch ).forEach( function ( k ) { next[ k ] = patch[ k ]; } );
			onAttsChange( next );
		}

		// v0.15.4: section-Auswahl auch fuer maes_*-Sub-Shortcodes.
		var maesSectionRow = isMaesFamily( service )
			? h( FlexItem, { key: 'section' },
				h( SelectControl, {
					label: __( 'Section (nur MAES)', 'deubner_hp_services' ),
					value: atts.section || '',
					options: PREVIEW_MAES_SECTIONS,
					onChange: function ( val ) { patchAtts( { section: val } ); },
					'aria-label': __( 'MAES-Section auswaehlen', 'deubner_hp_services' ),
				} )
			)
			: null;

		// v0.15.4: Visueller Sub-Shortcode-Hinweis.
		var subShortcodeBadge = ( PREVIEW_SUB_SHORTCODES.indexOf( service ) !== -1 )
			? h( FlexItem, { key: 'sub-badge' },
				h( 'span', {
					style: {
						display: 'inline-block',
						padding: '2px 8px',
						fontSize: '11px',
						fontWeight: '600',
						color: '#1d4ed8',
						backgroundColor: '#dbeafe',
						borderRadius: '4px',
						border: '1px solid #93c5fd',
					},
					title: __( 'Modularer Sub-Shortcode - Atts eingeschraenkt (v0.15.4).', 'deubner_hp_services' ),
				}, __( 'Sub-Shortcode', 'deubner_hp_services' ) )
			)
			: null;

		// v0.15.5: Reset-Button setzt service-spezifische Atts auf Schema-Defaults
		// zurueck. Universal-Atts (layout/class/section/cache) bleiben unveraendert,
		// damit der User seinen Layout-Wechsel nicht verliert.
		var onResetAtts = props.onResetAtts || function () {};

		// v0.15.5: AttsForm-Callback - patcht einzelne Atts.
		function onAttFieldChange( name, value ) {
			var patch = {};
			patch[ name ] = value;
			patchAtts( patch );
		}

		return h( 'div', { className: 'dhps-react-preview-controls' },
			h( Flex, { wrap: true, gap: 4, align: 'flex-end' },
				h( FlexItem, {},
					h( SelectControl, {
						label: __( 'Service', 'deubner_hp_services' ),
						value: service,
						options: PREVIEW_SERVICES,
						onChange: function ( val ) { onServiceChange( val ); },
						'aria-label': __( 'Service fuer Live-Preview auswaehlen', 'deubner_hp_services' ),
					} )
				),
				h( FlexItem, {},
					h( SelectControl, {
						label: __( 'Layout', 'deubner_hp_services' ),
						value: atts.layout || 'default',
						options: PREVIEW_LAYOUTS,
						onChange: function ( val ) { patchAtts( { layout: val } ); },
						'aria-label': __( 'Layout-Variante auswaehlen', 'deubner_hp_services' ),
					} )
				),
				h( FlexItem, {},
					h( TextControl, {
						label: __( 'CSS-Class (optional)', 'deubner_hp_services' ),
						value: atts['class'] || '',
						onChange: function ( val ) { patchAtts( { 'class': val } ); },
						placeholder: 'meine-klasse',
						'aria-label': __( 'Optionale CSS-Class', 'deubner_hp_services' ),
					} )
				),
				maesSectionRow,
				subShortcodeBadge,
				h( FlexItem, {},
					h( Button, {
						variant: 'primary',
						onClick: onRun,
						isBusy: loading,
						disabled: loading,
						'aria-busy': loading ? 'true' : 'false',
						'aria-label': __( 'Live-Preview rendern', 'deubner_hp_services' ),
					}, loading
						? __( 'Rendere...', 'deubner_hp_services' )
						: __( 'Vorschau laden', 'deubner_hp_services' )
					)
				),
				h( FlexItem, {},
					h( Button, {
						variant: 'tertiary',
						onClick: onResetAtts,
						disabled: loading,
						'aria-label': __( 'Service-spezifische Atts auf Defaults zuruecksetzen', 'deubner_hp_services' ),
					}, __( 'Atts zuruecksetzen', 'deubner_hp_services' ) )
				)
			),
			// v0.15.5: Service-spezifische Atts (dynamisch aus Schema).
			h( LivePreviewAttsForm, {
				service: service,
				atts: atts,
				onAttChange: onAttFieldChange,
			} )
		);
	}

	/**
	 * Default-Hoehe (px) fuer das Preview-iframe bevor das postMessage-Event
	 * eintrifft. Identisch zum v0.15.3-Verhalten (Static-Fallback).
	 *
	 * @since v0.15.3
	 */
	var PREVIEW_IFRAME_DEFAULT_HEIGHT = 600;

	/**
	 * Maximal akzeptierte iframe-Hoehe (px). Identisch zum Backend-MAX_HEIGHT.
	 * DoS-Schutz gegen unendliche Resize-Messages (v0.15.4).
	 *
	 * @since v0.15.4
	 */
	var PREVIEW_IFRAME_MAX_HEIGHT = 4000;

	/**
	 * Erwarteter postMessage-Type fuer iframe-Resize.
	 *
	 * @since v0.15.4
	 */
	var PREVIEW_RESIZE_MESSAGE_TYPE = 'dhps-preview-resize';

	/**
	 * LivePreviewIframe - iframe srcdoc-Container mit dynamic-Resize (v0.15.4).
	 *
	 * postMessage-Security-Layer (Discovery 22 Sektion 5.3):
	 *   - event.origin ist 'null' fuer about:srcdoc - klassischer Origin-Check
	 *     ist nicht moeglich. Stattdessen:
	 *   - Strict-Type-Check (event.data.type === PREVIEW_RESIZE_MESSAGE_TYPE).
	 *   - Numeric-Bounds-Check (parseInt + isNaN-Filter).
	 *   - Max-Cap (PREVIEW_IFRAME_MAX_HEIGHT = 4000 px) gegen DoS.
	 *
	 * Worst-Case-Analyse: Wenn ein anderer iframe-srcdoc auf der Admin-Seite
	 * ein passendes Resize-Event sendet, waechst nur DIESE iframe-Hoehe -
	 * keine XSS- oder Daten-Leak-Vektor.
	 *
	 * Props:
	 *   html {string}     Vollstaendige HTML-Seite (DOCTYPE + html + head + body).
	 *   service {string}  Service-Slug fuer Title-Attribut + key.
	 */
	function LivePreviewIframe( props ) {
		var html = props.html || '';
		var service = props.service || 'unknown';

		var stateHeight = useState( PREVIEW_IFRAME_DEFAULT_HEIGHT );
		var iframeHeight = stateHeight[ 0 ];
		var setIframeHeight = stateHeight[ 1 ];

		useEffect( function () {
			// Reset auf Default bei jedem Preview-Wechsel.
			setIframeHeight( PREVIEW_IFRAME_DEFAULT_HEIGHT );

			function handleMessage( event ) {
				// Type-Check (Defense-Layer 1).
				if ( ! event || ! event.data || event.data.type !== PREVIEW_RESIZE_MESSAGE_TYPE ) {
					return;
				}
				// Numeric-Bounds-Check (Defense-Layer 2).
				var h = parseInt( event.data.height, 10 );
				if ( isNaN( h ) || h < 1 ) {
					return;
				}
				// Max-Cap-Check (Defense-Layer 3, DoS-Schutz).
				if ( h > PREVIEW_IFRAME_MAX_HEIGHT ) {
					h = PREVIEW_IFRAME_MAX_HEIGHT;
				}
				setIframeHeight( h );
			}

			window.addEventListener( 'message', handleMessage );
			return function () {
				window.removeEventListener( 'message', handleMessage );
			};
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ html, service ] );

		return h( 'div', { className: 'dhps-react-preview-iframe-wrap', style: { marginTop: '12px' } },
			h( 'iframe', {
				// key sorgt fuer kompletten Remount, damit srcdoc neu eingelesen wird.
				key: 'dhps-iframe-' + service + '-' + html.length,
				srcDoc: html,
				sandbox: 'allow-same-origin allow-scripts',
				title: __( 'DHPS Live-Preview: ', 'deubner_hp_services' ) + service,
				'aria-label': __( 'Live-Preview-Inhalt fuer Service ', 'deubner_hp_services' ) + service,
				style: {
					width: '100%',
					height: iframeHeight + 'px',
					maxHeight: PREVIEW_IFRAME_MAX_HEIGHT + 'px',
					border: '1px solid #ddd',
					background: '#fff',
					transition: 'height 200ms ease',
				},
			} )
		);
	}

	/**
	 * LivePreviewMeta - Metadaten zum letzten Render.
	 *
	 * Props:
	 *   meta {object}  { size_bytes, render_time_ms, shortcode,
	 *                    atts_applied, atts_rejected, api_cache_hit }
	 */
	function LivePreviewMeta( props ) {
		var meta = props.meta || {};

		// Defensives Schema-Reading (Belt-and-Suspenders, Discovery Sektion 9.6).
		var sizeBytes = ( meta.size_bytes !== undefined && meta.size_bytes !== null )
			? meta.size_bytes
			: ( meta.bytes || 0 );
		var renderTime = ( meta.render_time_ms !== undefined && meta.render_time_ms !== null )
			? meta.render_time_ms
			: ( meta.duration_ms || 0 );
		var cacheHit = ( meta.api_cache_hit !== undefined && meta.api_cache_hit !== null )
			? !! meta.api_cache_hit
			: !! meta.cache_hit;
		var shortcode = meta.shortcode || '';
		var attsApplied = meta.atts_applied || {};
		var attsRejected = meta.atts_rejected || [];

		// atts_rejected kann Array (Schema) oder Object (Drift-Schutz) sein.
		var rejectedList = [];
		if ( Array.isArray( attsRejected ) ) {
			rejectedList = attsRejected.slice();
		} else if ( attsRejected && typeof attsRejected === 'object' ) {
			rejectedList = Object.keys( attsRejected );
		}

		var appliedSummary = Object.keys( attsApplied ).map( function ( k ) {
			return k + '=' + attsApplied[ k ];
		} ).join( ' ' );

		return h( 'div', {
			className: 'dhps-react-preview-meta',
			role: 'status',
			'aria-live': 'polite',
			style: { marginTop: '12px' },
		},
			h( Flex, { wrap: true, gap: 4, style: { marginBottom: '8px' } },
				h( FlexItem, {},
					h( Text, { variant: 'muted' }, __( 'Render-Zeit', 'deubner_hp_services' ) ),
					h( 'div', { style: { fontSize: '16px', fontWeight: '600' } }, renderTime + ' ms' )
				),
				h( FlexItem, {},
					h( Text, { variant: 'muted' }, __( 'Groesse', 'deubner_hp_services' ) ),
					h( 'div', { style: { fontSize: '16px', fontWeight: '600' } }, formatBytes( sizeBytes ) )
				),
				h( FlexItem, {},
					h( Text, { variant: 'muted' }, __( 'API-Cache', 'deubner_hp_services' ) ),
					h( 'div', { style: { fontSize: '16px', fontWeight: '600' } },
						cacheHit
							? __( 'HIT', 'deubner_hp_services' )
							: __( 'MISS', 'deubner_hp_services' )
					)
				)
			),
			shortcode ? h( 'div', { style: { marginBottom: '6px', fontSize: '12px' } },
				h( 'span', {}, __( 'Shortcode: ', 'deubner_hp_services' ) ),
				h( 'code', {}, shortcode )
			) : null,
			appliedSummary ? h( 'div', { style: { marginBottom: '6px', fontSize: '12px', color: '#646970' } },
				h( 'span', {}, __( 'Atts angewendet: ', 'deubner_hp_services' ) ),
				h( 'code', {}, appliedSummary )
			) : null,
			rejectedList.length > 0
				? h( 'div', { role: 'status', style: { marginTop: '8px' } },
					h( Notice, {
						status: 'warning',
						isDismissible: false,
					}, __( 'Folgende Atts wurden ignoriert: ', 'deubner_hp_services' ) + rejectedList.join( ', ' ) )
				)
				: null,
			// v0.15.4 Ticket #3: 500-KB-Soft-Warning bei grossem Preview.
			sizeBytes > 500000
				? h( 'div', { role: 'status', style: { marginTop: '8px' } },
					h( Notice, {
						status: 'warning',
						isDismissible: false,
					}, __( 'Preview ist gross (', 'deubner_hp_services' ) + formatBytes( sizeBytes ) + __( '). Render-Zeit und Browser-Performance koennen leiden.', 'deubner_hp_services' ) )
				)
				: null
		);
	}

	/**
	 * LivePreviewPanel - Container fuer die Live-Preview (v0.15.3).
	 *
	 * State:
	 *   service (string)    Service-Slug.
	 *   atts (object)       {layout, class, section}.
	 *   html (string)       Vollstaendige Preview-HTML-Seite.
	 *   meta (object|null)  Render-Metadaten.
	 *   loading (boolean)
	 *   error (string|null)
	 *
	 * REST-Vertrag (Discovery Sektion 9.3) - 10 Felder:
	 *   service, format, html, size_bytes, render_time_ms, shortcode,
	 *   atts_applied, atts_rejected, api_cache_hit, rendered_at.
	 */
	function LivePreviewPanel() {
		// v0.15.5: Initial-Atts aus Schema (mit Defaults). Fallback auf
		// hartcodierte Defaults wenn Schema-Bridge fehlt.
		var initialService = 'mio';
		var initialAtts = ( function () {
			var schema = getServiceSchema( initialService );
			if ( Object.keys( schema ).length > 0 ) {
				return buildDefaultAtts( initialService );
			}
			// BC-Fallback v0.15.4: layout/class/section ohne Schema.
			return { layout: 'default', 'class': '', section: '' };
		} )();

		var stateService = useState( initialService );
		var service = stateService[ 0 ];
		var setService = stateService[ 1 ];

		var stateAtts = useState( initialAtts );
		var atts = stateAtts[ 0 ];
		var setAtts = stateAtts[ 1 ];

		// v0.15.5: Service-Wechsel resettet service-spezifische Atts auf
		// Schema-Defaults. Sonst landen alte Atts (z.B. 'einzelvideo' von TP)
		// im neuen Service-Kontext und werden als "unknown att key" rejected.
		var onServiceChange = useCallback( function ( newService ) {
			setService( newService );
			setAtts( buildDefaultAtts( newService ) );
		}, [] );

		// v0.15.5: Reset-Button - service-spezifische Atts auf Defaults.
		// Universal-Atts (layout/class/section) bleiben erhalten.
		var onResetAtts = useCallback( function () {
			var defaults = buildDefaultAtts( service );
			var schema = getServiceSchema( service );
			// Universal-Atts aus aktuellem State uebernehmen.
			var preserved = {};
			Object.keys( schema ).forEach( function ( k ) {
				var def = schema[ k ] || {};
				if ( def.group === 'universal' && atts[ k ] !== undefined ) {
					preserved[ k ] = atts[ k ];
				}
			} );
			// Merge: defaults + preserved universal.
			var next = {};
			Object.keys( defaults ).forEach( function ( k ) { next[ k ] = defaults[ k ]; } );
			Object.keys( preserved ).forEach( function ( k ) { next[ k ] = preserved[ k ]; } );
			setAtts( next );
		}, [ service, atts ] );

		var stateHtml = useState( '' );
		var html = stateHtml[ 0 ];
		var setHtml = stateHtml[ 1 ];

		var stateMeta = useState( null );
		var meta = stateMeta[ 0 ];
		var setMeta = stateMeta[ 1 ];

		var stateLoading = useState( false );
		var loading = stateLoading[ 0 ];
		var setLoading = stateLoading[ 1 ];

		var stateError = useState( null );
		var error = stateError[ 0 ];
		var setError = stateError[ 1 ];

		var runPreview = useCallback( function () {
			setLoading( true );
			setError( null );

			// Sende nur befuellte Atts (leere Strings koennen vom Backend wegfallen).
			var attsBody = {};
			Object.keys( atts ).forEach( function ( k ) {
				if ( atts[ k ] !== '' && atts[ k ] !== null && atts[ k ] !== undefined ) {
					attsBody[ k ] = atts[ k ];
				}
			} );

			apiFetch( {
				path: '/dhps/v1/services/' + encodeURIComponent( service ) + '/preview',
				method: 'POST',
				data: {
					atts: attsBody,
					format: 'iframe',
				},
			} ).then( function ( result ) {
				result = result || {};

				// Defensives Schema-Reading (Discovery Sektion 9.6).
				var resultHtml = ( typeof result.html === 'string' ) ? result.html : '';
				var resultMeta = {
					service: result.service || service,
					format: result.format || 'iframe',
					size_bytes: ( result.size_bytes !== undefined && result.size_bytes !== null )
						? result.size_bytes
						: ( result.bytes || 0 ),
					render_time_ms: ( result.render_time_ms !== undefined && result.render_time_ms !== null )
						? result.render_time_ms
						: ( result.duration_ms || 0 ),
					shortcode: result.shortcode || '',
					atts_applied: result.atts_applied || {},
					atts_rejected: result.atts_rejected || [],
					api_cache_hit: ( result.api_cache_hit !== undefined && result.api_cache_hit !== null )
						? !! result.api_cache_hit
						: !! result.cache_hit,
					rendered_at: result.rendered_at || Math.floor( Date.now() / 1000 ),
				};

				setHtml( resultHtml );
				setMeta( resultMeta );
			} ).catch( function ( err ) {
				// eslint-disable-next-line no-console
				console.error( '[dhps-admin-react] Live-Preview fehlgeschlagen:', service, err );
				setError( ( err && err.message ) ? err.message : __( 'Render-Fehler.', 'deubner_hp_services' ) );
			} ).finally( function () {
				setLoading( false );
			} );
		}, [ service, atts ] );

		var emptyHint = ( ! html && ! error && ! loading )
			? h( 'div', { style: { marginTop: '12px' } },
				h( Notice, {
					status: 'info',
					isDismissible: false,
				}, __( 'Klicken Sie auf "Vorschau laden", um den Live-Render zu starten.', 'deubner_hp_services' ) )
			)
			: null;

		var errorNode = error
			? h( 'div', { role: 'alert', style: { marginTop: '12px' } },
				h( Notice, {
					status: 'error',
					isDismissible: true,
					onRemove: function () { setError( null ); },
				}, error )
			)
			: null;

		return h( 'section', {
			'aria-labelledby': 'dhps-preview-panel-title',
			'aria-busy': loading ? 'true' : 'false',
			className: 'dhps-react-preview-panel',
		},
			h( Panel, {},
				h( PanelBody, {
					title: __( 'Live-Preview', 'deubner_hp_services' ),
					initialOpen: false,
				},
					h( 'h2', {
						id: 'dhps-preview-panel-title',
						className: 'screen-reader-text',
						style: { position: 'absolute', left: '-9999px' },
					}, __( 'Live-Preview', 'deubner_hp_services' ) ),
					h( LivePreviewControls, {
						service: service,
						atts: atts,
						onServiceChange: onServiceChange,
						onAttsChange: setAtts,
						onResetAtts: onResetAtts,
						onRun: runPreview,
						loading: loading,
					} ),
					errorNode,
					emptyHint,
					html ? h( LivePreviewIframe, { html: html, service: service } ) : null,
					meta ? h( LivePreviewMeta, { meta: meta } ) : null
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
			h( CacheStatsPanel, {} ),
			h( 'div', { style: { height: '16px' } } ),
			h( LivePreviewPanel, {} )
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
