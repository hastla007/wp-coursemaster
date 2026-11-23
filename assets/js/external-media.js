/**
 * External media "click to load" (R0-10 §5.2.2/§5.3, R0-31 MC-1a…MC-8,
 * E-45a, E-246): progressive enhancement of the server-rendered placeholder.
 *
 * - The server output contains nothing that connects to the foreign host.
 *   This script reveals the "Load video" button; a click loads only this
 *   medium (iframe for YouTube/Vimeo, `<video>` for files) and attaches the
 *   subtitle track only then. Focus moves to the loaded player.
 * - Consent sources, in this order: WP Consent API (`wp_has_consent` with
 *   the configured category), adapters registered via
 *   `window.courseCaptain.consent.register( fn )` (re-checked on the event
 *   `course-captain:consent-changed`), then the click.
 * - "Always load external media from <service>" is offered only when the
 *   operator allows it, is never pre-ticked and stores the choice per
 *   service in localStorage (`ccap_external_media`, no personal data). A
 *   button next to an automatically loaded medium removes the choice.
 * Without JavaScript the placeholder keeps its link to the service.
 */
( function () {
	'use strict';
	var STORE = 'ccap_external_media';
	var boxes = document.querySelectorAll( '[data-ccap-external-media]' );
	var cc = window.courseCaptain = window.courseCaptain || {};
	var adapters = [];

	cc.consent = cc.consent || {};
	if ( 'function' !== typeof cc.consent.register ) {
		cc.consent.register = function ( fn ) {
			if ( 'function' === typeof fn ) {
				adapters.push( fn );
				checkAll();
			}
		};
	}

	function read() {
		try {
			var value = JSON.parse( window.localStorage.getItem( STORE ) || '{}' );
			return value && 'object' === typeof value ? value : {};
		} catch ( e ) {
			return {};
		}
	}

	function write( value ) {
		try {
			if ( Object.keys( value ).length ) {
				window.localStorage.setItem( STORE, JSON.stringify( value ) );
			} else {
				window.localStorage.removeItem( STORE );
			}
		} catch ( e ) {
			// Storage unavailable: the choice is simply not remembered.
		}
	}

	function remembered( box ) {
		return '1' === box.getAttribute( 'data-ccap-remember' ) && true === read()[ box.getAttribute( 'data-ccap-service' ) ];
	}

	function consented( box ) {
		var category = box.getAttribute( 'data-ccap-category' ) || 'marketing';
		var service = box.getAttribute( 'data-ccap-service' );
		try {
			if ( 'function' === typeof window.wp_has_consent && window.wp_has_consent( category ) ) {
				return true;
			}
		} catch ( e ) {
			// A failing consent tool never loads the medium.
		}
		for ( var i = 0; i < adapters.length; i++ ) {
			try {
				if ( true === adapters[ i ]( service, category ) ) {
					return true;
				}
			} catch ( e ) {
				// Ignore a failing adapter.
			}
		}
		return false;
	}

	function status( box, text ) {
		var node = box.querySelector( '[data-ccap-media-status]' );
		if ( node ) {
			node.textContent = text || '';
		}
	}

	function player( box ) {
		var src = box.getAttribute( 'data-ccap-src' );
		var el;
		if ( 'embed' === box.getAttribute( 'data-ccap-kind' ) ) {
			el = document.createElement( 'iframe' );
			el.className = 'ccap-embed';
			el.width = 560;
			el.height = 315;
			el.title = box.getAttribute( 'data-ccap-title' ) || '';
			el.setAttribute( 'allow', 'fullscreen; picture-in-picture; encrypted-media' );
			el.setAttribute( 'allowfullscreen', '' );
			el.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
			el.src = src;
			return el;
		}
		el = document.createElement( 'video' );
		el.controls = true;
		el.preload = 'metadata';
		el.src = src;
		var subtitles = box.getAttribute( 'data-ccap-subtitles' );
		if ( subtitles ) {
			var track = document.createElement( 'track' );
			track.kind = 'subtitles';
			track.default = true;
			track.label = box.getAttribute( 'data-ccap-subtitles-label' ) || '';
			track.src = subtitles;
			el.appendChild( track );
		}
		var download = document.createElement( 'a' );
		download.href = src;
		download.textContent = box.getAttribute( 'data-ccap-download-text' ) || '';
		el.appendChild( download );
		return el;
	}

	function showForget( box ) {
		var wrap = box.querySelector( '.ccap-media-forget' );
		var button = wrap && wrap.querySelector( '[data-ccap-forget]' );
		if ( ! button ) {
			return;
		}
		button.textContent = box.getAttribute( 'data-ccap-forget-text' ) || '';
		wrap.hidden = false;
	}

	function load( box, focus ) {
		if ( '1' === box.getAttribute( 'data-ccap-loaded' ) ) {
			return;
		}
		box.setAttribute( 'data-ccap-loaded', '1' );
		var frame = box.querySelector( '.ccap-media-frame' );
		var loader = box.querySelector( '.ccap-media-load' );
		var el = player( box );
		frame.appendChild( el );
		frame.hidden = false;
		if ( loader ) {
			loader.hidden = true;
		}
		status( box, box.getAttribute( 'data-ccap-loaded-text' ) );
		if ( focus ) {
			el.focus();
		}
	}

	function checkAll() {
		for ( var i = 0; i < boxes.length; i++ ) {
			if ( consented( boxes[ i ] ) ) {
				load( boxes[ i ], false );
			}
		}
	}

	function init( box ) {
		var loader = box.querySelector( '.ccap-media-load' );
		var button = box.querySelector( '[data-ccap-load]' );
		var remember = box.querySelector( '[data-ccap-remember-box]' );
		var forget = box.querySelector( '[data-ccap-forget]' );
		if ( ! loader || ! button ) {
			return;
		}
		if ( remember ) {
			remember.checked = false;
		}
		loader.hidden = false;
		button.addEventListener( 'click', function () {
			if ( remember && remember.checked && '1' === box.getAttribute( 'data-ccap-remember' ) ) {
				var choice = read();
				choice[ box.getAttribute( 'data-ccap-service' ) ] = true;
				write( choice );
				showForget( box );
			}
			load( box, true );
		} );
		if ( forget ) {
			forget.addEventListener( 'click', function () {
				var choice = read();
				delete choice[ box.getAttribute( 'data-ccap-service' ) ];
				write( choice );
				forget.parentNode.hidden = true;
				status( box, box.getAttribute( 'data-ccap-forgotten-text' ) );
			} );
		}
		if ( consented( box ) ) {
			load( box, false );
		} else if ( remembered( box ) ) {
			load( box, false );
			showForget( box );
		}
	}

	for ( var i = 0; i < boxes.length; i++ ) {
		init( boxes[ i ] );
	}
	document.addEventListener( 'course-captain:consent-changed', checkAll );
	document.addEventListener( 'wp_listen_for_consent_change', checkAll );
}() );
