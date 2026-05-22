/**
 * MAES-Aktuelles - Akkordeon-Toggle fuer News-Container.
 *
 * Reagiert auf data-dhps-toggle und data-dhps-collapse Attribute innerhalb
 * von .dhps-service--maes-aktuelles Containern. Setzt aria-expanded /
 * aria-hidden korrekt und scrollt beim Einklappen zur Trigger-Position.
 *
 * Ersetzt das frueher in maes/aktuelles.php inline gerenderte <script>.
 * Vorteile: CSP 'self'-konform, Browser-cacheable, testbar.
 *
 * @package Deubner Homepage-Service
 * @since   0.13.1
 */
(function () {
	'use strict';

	function initContainer( container ) {
		if ( container.dataset.dhpsMaesAktuellesBound === '1' ) {
			return;
		}
		container.dataset.dhpsMaesAktuellesBound = '1';

		container.addEventListener( 'click', function ( event ) {
			var toggle = event.target.closest( '[data-dhps-toggle]' );
			if ( toggle && container.contains( toggle ) ) {
				var targetId = toggle.getAttribute( 'data-dhps-toggle' );
				var body     = document.getElementById( targetId );
				if ( body ) {
					var isOpen = toggle.getAttribute( 'aria-expanded' ) === 'true';
					toggle.setAttribute( 'aria-expanded', isOpen ? 'false' : 'true' );
					body.setAttribute( 'aria-hidden', isOpen ? 'true' : 'false' );
				}
				return;
			}

			var collapse = event.target.closest( '[data-dhps-collapse]' );
			if ( collapse && container.contains( collapse ) ) {
				var collapseId = collapse.getAttribute( 'data-dhps-collapse' );
				var collapseBody = document.getElementById( collapseId );
				if ( collapseBody ) {
					collapseBody.setAttribute( 'aria-hidden', 'true' );
					var relatedToggle = container.querySelector(
						'[data-dhps-toggle="' + collapseId + '"]'
					);
					if ( relatedToggle ) {
						relatedToggle.setAttribute( 'aria-expanded', 'false' );
						relatedToggle.scrollIntoView( {
							behavior: 'smooth',
							block: 'nearest'
						} );
					}
				}
			}
		} );
	}

	function init() {
		document.querySelectorAll( '.dhps-service--maes-aktuelles' ).forEach( initContainer );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
