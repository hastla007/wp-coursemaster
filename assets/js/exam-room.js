/**
 * Exam room client (R1-16/R1-20, R0-05 GM-050): progressive enhancement of
 * the server-rendered exam form.
 *
 * - Every change of an answer is saved at once (`PUT …/answers/<position>`).
 * - Without a connection the answer stays in localStorage under
 *   `ccap_exam_<session uid>` (no solutions, only the learner's own answers,
 *   R0-17 T-04), a notice is shown, and the buffer is re-sent with its age
 *   (`age_ms`) when the browser is online again, on the next page load or
 *   every 15 seconds. An entry is removed only after the server answered;
 *   the result page removes the whole buffer (PRIV-018).
 * - The server stays authoritative: an older buffered state never
 *   overwrites a newer one (R0-08 §6.4), rejected answers are dropped.
 * The form keeps working without this script.
 */
( function () {
	'use strict';
	var form = document.querySelector( 'form.ccap-exam-form[data-ccap-session]' );
	if ( ! form || ! window.fetch || ! window.JSON ) {
		return;
	}
	var storeKey = 'ccap_exam_' + form.getAttribute( 'data-ccap-session' );
	var base = form.getAttribute( 'data-ccap-rest' );
	var nonce = form.getAttribute( 'data-ccap-nonce' );
	var notice = document.getElementById( 'ccap-offline' );
	var live = document.getElementById( 'ccap-exam-live' );
	var sending = false;
	var timer = null;

	function load() {
		try {
			var value = JSON.parse( window.localStorage.getItem( storeKey ) || '{}' );
			return value && 'object' === typeof value ? value : {};
		} catch ( e ) {
			return {};
		}
	}

	function store( queue ) {
		try {
			if ( Object.keys( queue ).length ) {
				window.localStorage.setItem( storeKey, JSON.stringify( queue ) );
			} else {
				window.localStorage.removeItem( storeKey );
			}
		} catch ( e ) {}
	}

	function say( text ) {
		if ( ! live || ! text ) {
			return;
		}
		live.textContent = '';
		window.setTimeout( function () {
			live.textContent = text;
		}, 50 );
	}

	function offline( on ) {
		if ( ! notice ) {
			return;
		}
		if ( on && notice.hidden ) {
			notice.hidden = false;
			say( notice.textContent );
		} else if ( ! on && ! notice.hidden ) {
			notice.hidden = true;
			say( form.getAttribute( 'data-ccap-synced' ) );
		}
	}

	function checked( section, selector ) {
		return Array.prototype.filter.call( section.querySelectorAll( selector ), function ( input ) {
			return input.checked;
		} );
	}

	// Answer of a question section in the format of the exam API; null = nothing to send.
	function answerOf( section ) {
		var type = section.getAttribute( 'data-ccap-type' );
		var position = section.getAttribute( 'data-ccap-position' );
		var name = 'answer[' + position + ']';
		var radios;
		switch ( type ) {
			case 'single_choice':
			case 'true_false':
				radios = checked( section, 'input[type=radio][name="' + name + '"]' );
				return radios.length ? radios[ 0 ].value : '';
			case 'multi_choice':
				return checked( section, 'input[type=checkbox][name="' + name + '[]"]' ).map( function ( input ) {
					return input.value;
				} );
			case 'numeric_tolerance':
				var field = section.querySelector( 'input[name="' + name + '"]' );
				return field ? field.value.replace( ',', '.' ).trim() : null;
			case 'matching':
				var map = {};
				Array.prototype.forEach.call( section.querySelectorAll( 'select' ), function ( select ) {
					var left = ( select.name.match( /\[([^\]]*)\]$/ ) || [] )[ 1 ];
					if ( left && select.value ) {
						map[ left ] = select.value;
					}
				} );
				return map;
			case 'ordering':
				// Send only a complete order (every position once) or an empty one; the form post reports gaps.
				var ranks = [];
				var selects = section.querySelectorAll( 'select' );
				Array.prototype.forEach.call( selects, function ( select ) {
					var key = ( select.name.match( /\[([^\]]*)\]$/ ) || [] )[ 1 ];
					if ( key && select.value ) {
						ranks.push( { key: key, rank: parseInt( select.value, 10 ) } );
					}
				} );
				if ( ! ranks.length ) {
					return [];
				}
				ranks.sort( function ( a, b ) {
					return a.rank - b.rank;
				} );
				for ( var i = 0; i < ranks.length; i++ ) {
					if ( ranks[ i ].rank !== i + 1 ) {
						return null;
					}
				}
				return ranks.length === selects.length ? ranks.map( function ( entry ) {
					return entry.key;
				} ) : null;
			case 'cloze':
				var gaps = {};
				Array.prototype.forEach.call( section.querySelectorAll( 'input[type=text]' ), function ( input ) {
					var gap = ( input.name.match( /\[([^\]]*)\]$/ ) || [] )[ 1 ];
					if ( gap && input.value.trim() ) {
						gaps[ gap ] = input.value.trim();
					}
				} );
				return gaps;
			case 'open_text':
				radios = checked( section, 'input[type=radio][name="' + name + '[known]"]' );
				return radios.length ? { known: '1' === radios[ 0 ].value } : null;
		}
		return null;
	}

	function retry() {
		window.clearTimeout( timer );
		timer = window.setTimeout( flush, 15000 );
	}

	function flush() {
		if ( sending ) {
			return;
		}
		var queue = load();
		var positions = Object.keys( queue );
		if ( ! positions.length ) {
			offline( false );
			return;
		}
		var position = positions[ 0 ];
		var entry = queue[ position ];
		sending = true;
		window.fetch( base + position, {
			method: 'PUT',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
			body: JSON.stringify( { answer: entry.a, age_ms: Math.max( 0, Date.now() - entry.t ) } ),
		} ).then( function ( response ) {
			sending = false;
			// Keep the entry while the server cannot take it (no session, rate limit, server error).
			if ( 401 === response.status || 403 === response.status || 429 === response.status || response.status >= 500 ) {
				throw new Error( 'retry' );
			}
			var now = load();
			if ( now[ position ] && now[ position ].t === entry.t ) {
				delete now[ position ];
				store( now );
			}
			flush();
		} ).catch( function () {
			sending = false;
			offline( true );
			retry();
		} );
	}

	form.addEventListener( 'change', function ( event ) {
		var target = event.target;
		if ( ! target || ! target.name || 0 === target.name.indexOf( 'flag[' ) || 'confirm' === target.name ) {
			return;
		}
		var section = target.closest ? target.closest( 'section.ccap-question' ) : null;
		if ( ! section ) {
			return;
		}
		var answer = answerOf( section );
		if ( null === answer ) {
			return;
		}
		var queue = load();
		queue[ section.getAttribute( 'data-ccap-position' ) ] = { a: answer, t: Date.now() };
		store( queue );
		flush();
	} );
	window.addEventListener( 'online', flush );
	window.addEventListener( 'offline', function () {
		if ( Object.keys( load() ).length ) {
			offline( true );
		}
	} );
	flush();
}() );
