// Shared accessibility checks of the live E2E scripts (flow-a11y.js, settings-a11y.js).
//
// domChecks()   runs in the page (page.evaluate): lang, title, main landmark, one h1, no skipped
//               heading level, accessible names, alt texts, unique IDs; with a scope only inside it.
// fieldChecks() runs in the page: every form control in the scope has a label, every
//               aria-describedby target exists, no positive tabindex, invalid controls reference a
//               visible "Error:" text, error summaries link to existing targets (R0-15, R0-30 T-5).
// focusWalk()   walks the tab order inside a scope: visible focus on every stop, order follows the
//               DOM (no positive tabindex jumps), no stop outside the viewport width.
// overflow()    horizontal scrolling of the document at the current viewport.
// runAxe()      axe-core (tags wcag2a/aa, wcag21a/aa, wcag22aa) when AXE_PATH points to axe.min.js.
const fs = require( 'fs' );

const AXE = process.env.AXE_PATH && fs.existsSync( process.env.AXE_PATH ) ? process.env.AXE_PATH : null;

function domChecks( scope ) {
	const root = scope ? document.querySelector( scope ) : document;
	const problems = [];
	if ( ! root ) {
		return [ 'scope not found: ' + scope ];
	}
	const name = ( el ) => {
		const labelled = ( el.getAttribute( 'aria-labelledby' ) || '' ).split( /\s+/ ).filter( Boolean ).map( ( id ) => ( document.getElementById( id ) || {} ).textContent || '' ).join( ' ' );
		const labels = el.labels ? [ ...el.labels ].map( ( l ) => l.textContent ).join( ' ' ) : '';
		const img = [ ...el.querySelectorAll( 'img[alt]' ) ].map( ( i ) => i.alt ).join( ' ' );
		return ( el.getAttribute( 'aria-label' ) || labelled || labels || el.getAttribute( 'title' ) || ( [ 'INPUT', 'SELECT', 'TEXTAREA' ].includes( el.tagName ) ? '' : el.textContent ) || img || ( 'submit' === el.type || 'button' === el.type ? el.value : '' ) || '' ).trim();
	};
	const visible = ( el ) => !! ( el.offsetWidth || el.offsetHeight || el.getClientRects().length ) && 'hidden' !== getComputedStyle( el ).visibility;
	if ( ! scope ) {
		if ( ! document.documentElement.lang ) {
			problems.push( 'html without lang' );
		}
		if ( ! document.title.trim() ) {
			problems.push( 'empty title' );
		}
		if ( ! document.querySelector( 'main, [role=main]' ) ) {
			problems.push( 'no main landmark' );
		}
		const h1 = [ ...document.querySelectorAll( 'h1' ) ].filter( visible ).length;
		if ( 1 !== h1 ) {
			problems.push( h1 + ' visible h1' );
		}
	}
	let last = scope ? 1 : 0;
	for ( const h of root.querySelectorAll( 'h1, h2, h3, h4, h5, h6' ) ) {
		if ( ! visible( h ) ) {
			continue;
		}
		const level = parseInt( h.tagName.slice( 1 ), 10 );
		if ( last && level > last + 1 ) {
			problems.push( 'heading level skipped: ' + h.tagName + ' "' + h.textContent.trim().slice( 0, 40 ) + '"' );
		}
		last = level;
	}
	for ( const el of root.querySelectorAll( 'input:not([type=hidden]), select, textarea, button, a[href]' ) ) {
		if ( visible( el ) && ! name( el ) ) {
			problems.push( 'no accessible name: ' + el.outerHTML.slice( 0, 100 ) );
		}
	}
	for ( const img of root.querySelectorAll( 'img:not([alt])' ) ) {
		problems.push( 'img without alt: ' + img.outerHTML.slice( 0, 80 ) );
	}
	const ids = {};
	// Hidden inputs are left out: WordPress' wp_nonce_field() gives every nonce input id="_wpnonce";
	// they are not in the accessibility tree and never referenced by ARIA attributes.
	for ( const el of root.querySelectorAll( '[id]:not(input[type=hidden])' ) ) {
		ids[ el.id ] = ( ids[ el.id ] || 0 ) + 1;
	}
	for ( const [ id, n ] of Object.entries( ids ) ) {
		if ( n > 1 ) {
			problems.push( 'duplicate id "' + id + '" (' + n + ')' );
		}
	}
	return problems;
}

function fieldChecks( scope ) {
	const root = document.querySelector( scope ) || document;
	const problems = [];
	const stats = { controls: 0, described: 0, invalid: 0 };
	const visible = ( el ) => !! ( el.offsetWidth || el.offsetHeight || el.getClientRects().length ) && 'hidden' !== getComputedStyle( el ).visibility;
	for ( const el of root.querySelectorAll( '[tabindex]' ) ) {
		if ( parseInt( el.getAttribute( 'tabindex' ), 10 ) > 0 ) {
			problems.push( 'positive tabindex: ' + el.outerHTML.slice( 0, 80 ) );
		}
	}
	for ( const el of root.querySelectorAll( 'input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea' ) ) {
		if ( ! visible( el ) ) {
			continue;
		}
		stats.controls++;
		const what = el.tagName.toLowerCase() + ( el.id ? '#' + el.id : '' ) + ( el.name ? '[' + el.name + ']' : '' );
		const labelled = ( el.labels && el.labels.length ) || el.getAttribute( 'aria-label' ) || el.getAttribute( 'aria-labelledby' );
		if ( ! labelled ) {
			problems.push( 'control without label: ' + what );
		}
		const described = ( el.getAttribute( 'aria-describedby' ) || '' ).split( /\s+/ ).filter( Boolean );
		if ( described.length ) {
			stats.described++;
		}
		for ( const id of described ) {
			if ( ! document.getElementById( id ) ) {
				problems.push( 'aria-describedby target missing: ' + what + ' -> ' + id );
			}
		}
		if ( 'true' === el.getAttribute( 'aria-invalid' ) ) {
			stats.invalid++;
			const err = described.map( ( id ) => document.getElementById( id ) ).find( ( e ) => e && /^\s*(Error|Fehler):/.test( e.textContent ) && visible( e ) );
			if ( ! err ) {
				problems.push( 'aria-invalid without a referenced visible error text: ' + what );
			}
		}
	}
	for ( const field of root.querySelectorAll( '.ccap-field-invalid' ) ) {
		if ( ! field.querySelector( '[aria-invalid=true]' ) ) {
			problems.push( 'invalid field without aria-invalid: ' + field.outerHTML.slice( 0, 80 ) );
		}
	}
	for ( const a of root.querySelectorAll( '.ccap-error-summary a[href^="#"]' ) ) {
		if ( ! document.getElementById( a.getAttribute( 'href' ).slice( 1 ) ) ) {
			problems.push( 'error summary link without target: ' + a.getAttribute( 'href' ) );
		}
	}
	return { problems, stats };
}

// Tab through the page until the focus is inside `scope`, then up to `max` stops while it stays inside.
async function focusWalk( page, scope, max ) {
	const problems = [];
	const stops = [];
	await page.evaluate( () => {
		if ( document.activeElement ) {
			document.activeElement.blur();
		}
		window.scrollTo( 0, 0 );
	} );
	let inside = false;
	for ( let i = 0; i < 80 && ! inside; i++ ) {
		await page.keyboard.press( 'Tab' );
		inside = await page.evaluate( ( s ) => !! ( document.activeElement && document.activeElement.closest( s ) ), scope );
	}
	if ( ! inside ) {
		return { problems: [ 'focus never reached ' + scope ], stops };
	}
	await page.evaluate( () => {
		window.ccapPrev = null;
	} );
	for ( let i = 0; i < ( max || 60 ); i++ ) {
		// Measure after the next frames: the browser scrolls the focused element into view
		// (also inside scrolling regions) before painting; WebKit does it after the key event.
		await page.evaluate( () => new Promise( ( r ) => requestAnimationFrame( () => requestAnimationFrame( r ) ) ) );
		const f = await page.evaluate( ( s ) => {
			const el = document.activeElement;
			if ( ! el || ! el.closest( s ) ) {
				return null;
			}
			const st = getComputedStyle( el );
			const r = el.getBoundingClientRect();
			const prev = window.ccapPrev;
			if ( prev === el ) {
				// Tab left the document (browser UI): Firefox keeps the last element as activeElement.
				return { end: true };
			}
			const forward = ! prev || !! ( prev.compareDocumentPosition( el ) & Node.DOCUMENT_POSITION_FOLLOWING );
			window.ccapPrev = el;
			return {
				what: el.tagName + ( el.id ? '#' + el.id : '' ) + ( el.name ? '[' + el.name + ']' : '' ) + ( 'A' === el.tagName ? '"' + el.textContent.trim().slice( 0, 30 ) + '"' : '' ),
				outline: 'none' !== st.outlineStyle && parseFloat( st.outlineWidth ) > 0,
				shadow: 'none' !== st.boxShadow,
				forward,
				offscreen: r.right > document.documentElement.clientWidth + 1 || r.left < -1,
			};
		}, scope );
		if ( ! f || f.end ) {
			break;
		}
		stops.push( f.what );
		if ( ! f.outline && ! f.shadow ) {
			problems.push( 'no visible focus on ' + f.what );
		}
		if ( ! f.forward ) {
			problems.push( 'tab order goes backwards at ' + f.what );
		}
		if ( f.offscreen ) {
			problems.push( 'focused element outside the viewport width: ' + f.what );
		}
		await page.keyboard.press( 'Tab' );
	}
	return { problems, stops };
}

async function overflow( page ) {
	return page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
}

async function runAxe( page, scope ) {
	if ( ! AXE ) {
		return [];
	}
	await page.addScriptTag( { path: AXE } );
	return page.evaluate( async ( s ) => {
		const res = await window.axe.run( s ? { include: [ s ] } : document, { runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] }, resultTypes: [ 'violations' ] } );
		return res.violations.map( ( v ) => 'axe ' + v.id + ' (' + v.nodes.length + '): ' + v.nodes.map( ( n ) => n.target.join( ' ' ) ).slice( 0, 3 ).join( ', ' ) );
	}, scope || null );
}

module.exports = { AXE, domChecks, fieldChecks, focusWalk, overflow, runAxe };
