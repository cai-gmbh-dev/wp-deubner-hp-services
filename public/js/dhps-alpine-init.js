/**
 * DHPS Alpine.js Init
 *
 * Defensive Initialisierung der Alpine.js-Integration fuer das Plugin
 * "Deubner HP Services". Diese Datei laeuft NACH dem Vendor-Alpine
 * (siehe wp_register_script-Reihenfolge im Lead-Handover).
 *
 * Verantwortlichkeiten:
 *   - Namespace `window.dhpsAlpine` anlegen (zentrale Registry).
 *   - Pruefen, ob Theme oder anderes Plugin Alpine bereits geladen hat.
 *   - Den `alpine:init`-Hook bereitstellen, in dem spaetere Specialist-
 *     Files ihre Komponenten via `Alpine.data('dhpsXxx', ...)` anhaengen.
 *   - Stub fuer ARIA-Focus-Trap-Helper bereitstellen, der spaeter durch
 *     `@alpinejs/focus`-Plugin oder eigene Implementierung ersetzt wird.
 *
 * Konventionen:
 *   - Alle Komponenten/Stores/Magic-Properties tragen Prefix `dhps`.
 *   - Kein Auto-Start hier - Alpine v3 startet sich selbst nach `defer`.
 *   - ASCII-safe, keine Umlaute.
 */
( function () {
    'use strict';

    // Namespace-Registry. Spezialisten haengen ihre Komponenten an
    // `window.dhpsAlpine.components.dhpsXxx = factoryFn` an.
    window.dhpsAlpine = window.dhpsAlpine || {
        components: {},
        stores: {},
        version: '0.14.0-init'
    };

    // ARIA-Helper-Stub. Wird in spaeteren Files durch echte Focus-Trap-
    // Logik (idealerweise via `@alpinejs/focus`-Plugin) ersetzt.
    // Signatur: dhpsAlpine.focusTrap(el) -> { activate(), deactivate() }.
    window.dhpsAlpine.focusTrap = window.dhpsAlpine.focusTrap || function ( el ) {
        return {
            activate: function () { /* noop - placeholder */ },
            deactivate: function () { /* noop - placeholder */ }
        };
    };

    // Defensive Detection: Wenn KEIN Alpine vorhanden ist, koennen wir
    // hier nicht weitermachen. Lead-Handover stellt sicher, dass das
    // Vendor-Script vor diesem File geladen wurde - aber wir bleiben
    // robust gegen Fehl-Konfigurationen.
    if ( typeof window.Alpine === 'undefined' ) {
        // alpine:init feuert spaeter trotzdem, sobald Alpine bereit ist
        // (z.B. wenn Theme ein verzoegertes Alpine laedt). Wir registrieren
        // den Listener defensiv - er macht nichts, solange `components`
        // leer bleibt.
        document.addEventListener( 'alpine:init', registerComponents );
        return;
    }

    // Alpine ist erreichbar - Version-Sanity-Check.
    if ( window.Alpine.version && parseInt( window.Alpine.version, 10 ) < 3 ) {
        // Inkompatibel: Alpine v2 oder aelter. Wir brechen ab, ohne zu crashen.
        // (siehe Architektur-Report 13-alpinejs-integration-v0140.md, Sektion 7)
        if ( window.console && window.console.warn ) {
            window.console.warn( '[DHPS] Alpine.js v' + window.Alpine.version + ' erkannt, benoetigt v3+. Init abgebrochen.' );
        }
        return;
    }

    // Standard-Weg: alpine:init-Event abonnieren. Spezialisten haben
    // bis hierhin ihre Factories in `dhpsAlpine.components` registriert.
    document.addEventListener( 'alpine:init', registerComponents );

    /**
     * Iteriert ueber alle gesammelten Komponenten-Factories und meldet
     * sie bei Alpine an. Idempotent gegenueber doppeltem Event-Fire.
     */
    function registerComponents() {
        if ( ! window.Alpine || ! window.dhpsAlpine ) {
            return;
        }
        Object.keys( window.dhpsAlpine.components ).forEach( function ( name ) {
            var factory = window.dhpsAlpine.components[ name ];
            if ( typeof factory === 'function' ) {
                window.Alpine.data( name, factory );
            }
        } );
        // Stores analog (spaetere Phase).
        Object.keys( window.dhpsAlpine.stores ).forEach( function ( name ) {
            var store = window.dhpsAlpine.stores[ name ];
            if ( store ) {
                window.Alpine.store( name, store );
            }
        } );
    }
} )();
