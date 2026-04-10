/**
 * Admin-JavaScript fuer Deubner Homepage Services.
 *
 * Handhabt AJAX-Requests fuer Demo-Modus Toggle.
 * Buttons mit der Klasse .dhps-btn--demo oder .dhps-btn--stop
 * loesen einen AJAX-Request aus, um den Demo-Modus fuer einen
 * Service zu aktivieren oder zu deaktivieren.
 *
 * @package    Deubner Homepage-Service
 * @subpackage Admin/JS
 * @since      0.6.0
 */

(function( $ ) {
	'use strict';

	$( document ).on( 'click', '.dhps-btn--demo, .dhps-btn--stop', function( e ) {
		e.preventDefault();

		var $button     = $( this );
		var service     = $button.data( 'service' );
		var actionType  = $button.data( 'action' );
		var nonce       = $( '#dhps_demo_nonce' ).val();
		var originalText = $button.text();

		// Button waehrend des Requests deaktivieren.
		$button.prop( 'disabled', true ).text( 'Bitte warten...' );

		$.ajax({
			url:      ajaxurl,
			type:     'POST',
			dataType: 'json',
			data: {
				action:      'dhps_toggle_demo',
				service:     service,
				action_type: actionType,
				nonce:       nonce
			},
			success: function( response ) {
				if ( response.success ) {
					location.reload();
				} else {
					var message = response.data && response.data.message
						? response.data.message
						: 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
					alert( message );
					$button.prop( 'disabled', false ).text( originalText );
				}
			},
			error: function() {
				alert( 'Die Verbindung zum Server ist fehlgeschlagen. Bitte versuchen Sie es erneut.' );
				$button.prop( 'disabled', false ).text( originalText );
			}
		});
	});

})( jQuery );
