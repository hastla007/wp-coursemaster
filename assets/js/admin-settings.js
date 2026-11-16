/**
 * Course Captain admin settings and setup wizard (R0-30 T-9, W-1, W-7, UD-1).
 *
 * Progressive enhancement only: every form works without JavaScript.
 * - Focuses the element marked with data-ccap-focus (message, error
 *   summary or the step heading).
 * - Tracks unsaved changes per form (data-ccap-dirty-form) and warns on
 *   leaving through links marked data-ccap-leave and on closing the tab.
 */
( function () {
	'use strict';

	var dirty = false;

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var focus = document.querySelector( '[data-ccap-focus]' );
		if ( focus ) {
			focus.focus();
		}

		var forms = document.querySelectorAll( 'form[data-ccap-dirty-form]' );
		Array.prototype.forEach.call( forms, function ( form ) {
			var mark = function () {
				dirty = true;
			};
			form.addEventListener( 'input', mark );
			form.addEventListener( 'change', mark );
			form.addEventListener( 'submit', function () {
				dirty = false;
			} );
		} );

		var leave = document.querySelectorAll( '[data-ccap-leave]' );
		Array.prototype.forEach.call( leave, function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				if ( dirty && ! window.confirm( link.getAttribute( 'data-ccap-leave' ) ) ) {
					event.preventDefault();
				} else {
					dirty = false;
				}
			} );
		} );

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );
	} );
}() );
