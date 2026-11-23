/**
 * Course Captain admin settings and setup wizard (R0-30 T-9, W-1, W-7, UD-1).
 *
 * Progressive enhancement only: every form works without JavaScript.
 * - Focuses the element marked with data-ccap-focus (message, error
 *   summary or the step heading), again after WordPress moved notices.
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

	// WordPress moves admin notices below the page heading after its own ready
	// handler; moving an element drops its focus. Focus again once the page has
	// loaded unless the person already moved on (SET-CC-21).
	function focusMarked() {
		var focus = document.querySelector( '[data-ccap-focus]' );
		var active = document.activeElement;
		if ( focus && active !== focus && ( ! active || active === document.body ) ) {
			focus.focus();
		}
	}

	window.addEventListener( 'load', function () {
		window.setTimeout( focusMarked, 0 );
	} );

	ready( function () {
		focusMarked();

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
