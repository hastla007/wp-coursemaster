// R1-18 live E2E: shared helpers of the flow scripts (live-flows.sh).
// Steps are recorded with their duration; CCAP_E2E_OUT/timings.jsonl collects
// them for the benchmark report (one JSON object per line).
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = process.env.CCAP_E2E_URL;
const OUT = process.env.CCAP_E2E_OUT || '';
const area = ( p ) => BASE + '/?course_captain_app=' + encodeURIComponent( p || '' );
// Plain permalinks: the route is a query argument; a query string of the path is appended.
const rest = ( p ) => {
	const [ route, query ] = p.split( '?' );
	return BASE + '/?rest_route=' + encodeURIComponent( '/course-captain/v1' + route ) + ( query ? '&' + query : '' );
};

function recorder( flow ) {
	const results = [];
	async function step( name, fn ) {
		const t0 = Date.now();
		try {
			await fn();
			const ms = Date.now() - t0;
			results.push( 'ok   ' + name + ' (' + ms + ' ms)' );
			record( { flow, step: name, ms } );
		} catch ( e ) {
			results.push( 'FAIL ' + name + ': ' + e.message );
			throw e;
		}
	}
	return { step, results };
}

function record( entry ) {
	if ( OUT ) {
		fs.appendFileSync( path.join( OUT, 'timings.jsonl' ), JSON.stringify( entry ) + '\n' );
	}
}

function assert( cond, message ) {
	if ( ! cond ) {
		throw new Error( message );
	}
}

async function login( page, user ) {
	await page.goto( BASE + '/wp-login.php' );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', process.env.CCAP_E2E_PASS );
	await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
	assert( ! page.url().includes( 'wp-login.php' ), 'logged in as ' + user );
	return nonce( page );
}

async function nonce( page ) {
	const n = await page.evaluate( async ( u ) => ( await fetch( u, { credentials: 'same-origin' } ) ).text(), BASE + '/wp-admin/admin-ajax.php?action=rest-nonce' );
	assert( /^[0-9a-f]{10}$/.test( n ), 'REST nonce' );
	return n;
}

// REST call from the page (cookie + nonce, like the learning area's scripts); returns status, data and duration.
async function api( page, n, method, p, body, headers ) {
	return page.evaluate( async ( args ) => {
		const t0 = performance.now();
		const r = await fetch( args.url, {
			method: args.method,
			credentials: 'same-origin',
			headers: Object.assign( { 'X-WP-Nonce': args.nonce, 'Content-Type': 'application/json' }, args.headers || {} ),
			body: args.body ? JSON.stringify( args.body ) : undefined,
		} );
		const data = await r.json().catch( () => null );
		return { status: r.status, data, ms: performance.now() - t0 };
	}, { url: rest( p ), method, nonce: n, body, headers } );
}

// Correct option letters of the reference course questions (live-setup.php `bank`).
function referenceLetters( key ) {
	return 0 === parseInt( key.slice( -3 ), 10 ) % 5 ? [ 'A', 'B' ] : [ 'A' ];
}

// Fill and submit an exam in the exam room form: every choice question by its key, open questions by reveal + self-assessment.
async function answerExam( page, letters ) {
	const sections = page.locator( 'section.ccap-question' );
	const total = await sections.count();
	const open = [];
	for ( let i = 0; i < total; i++ ) {
		const s = sections.nth( i );
		const key = ( ( await s.locator( '.ccap-qtext' ).innerText() ).match( /([A-Z]{1,2}\d?-(?:Q-)?\d{3})/ ) || [] )[ 1 ];
		const boxes = s.locator( 'input[type=checkbox][name^="answer"], input[type=radio]' );
		if ( ( await boxes.count() ) === 0 ) {
			open.push( await s.getAttribute( 'data-ccap-position' ) );
			continue;
		}
		const multi = ( await s.locator( 'input[type=checkbox][name^="answer"]' ).count() ) > 0;
		for ( const letter of letters( key, multi ) ) {
			await s.locator( 'li', { hasText: 'Antwort ' + letter + ' zu ' + key } ).locator( 'input' ).check();
		}
	}
	await Promise.all( [ page.waitForNavigation(), page.click( 'button.ccap-button[name=ccap_do][value=save]' ) ] );
	for ( const position of open ) {
		await Promise.all( [ page.waitForNavigation(), page.click( 'button[name=ccap_reveal][value="' + position + '"]' ) ] );
		await page.check( '#ccap-q-' + position + '-known-1' );
		await Promise.all( [ page.waitForNavigation(), page.click( 'button.ccap-button[name=ccap_do][value=save]' ) ] );
	}
	return total;
}

function percentile( values, p ) {
	const sorted = [ ...values ].sort( ( a, b ) => a - b );
	if ( ! sorted.length ) {
		return null;
	}
	return sorted[ Math.min( sorted.length - 1, Math.ceil( ( p / 100 ) * sorted.length ) - 1 ) ];
}

module.exports = { BASE, OUT, area, rest, recorder, record, assert, login, nonce, api, referenceLetters, answerExam, percentile };
