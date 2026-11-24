// R1-18 live E2E: shared helpers of the flow scripts (live-flows.sh).
// Steps are recorded with their duration; CCAP_E2E_OUT/timings.jsonl collects
// them for the benchmark report (one JSON object per line).
const fs = require( 'fs' );
const path = require( 'path' );
const playwright = require( 'playwright' );

const BASE = process.env.CCAP_E2E_URL;
const OUT = process.env.CCAP_E2E_OUT || '';
const area = ( p ) => BASE + '/?course_captain_app=' + encodeURIComponent( p || '' );
// Plain permalinks: the route is a query argument; a query string of the path is appended.
const rest = ( p ) => {
	const [ route, query ] = p.split( '?' );
	return BASE + '/?rest_route=' + encodeURIComponent( '/course-captain/v1' + route ) + ( query ? '&' + query : '' );
};

// UI-F7: browser engine from CCAP_E2E_BROWSER (chromium | firefox | webkit, default chromium).
const BROWSER = ( process.env.CCAP_E2E_BROWSER || 'chromium' ).toLowerCase();
if ( ! [ 'chromium', 'firefox', 'webkit' ].includes( BROWSER ) ) {
	throw new Error( 'CCAP_E2E_BROWSER must be chromium, firefox or webkit, not ' + BROWSER );
}
const MOBILE = 'mobile' === process.env.CCAP_E2E_DEVICE;
const PHONE_WIDTH = 390;

// WebKit (Playwright 1.63, Linux) drops a form submission now and then when it is triggered
// while the page still loads subresources right after `load` (core's login page loads zxcvbn
// asynchronously, for example): the submit event fires, but no request leaves the browser
// (about 1 in 30 submissions; 0 in 100 after waiting for network idle; UI-F7). A person never
// submits within milliseconds of the page load, so on WebKit every navigation of the scripts
// also waits for network idle (at most 5 s) before the next step.
function settleAfterNavigation( page ) {
	for ( const m of [ 'goto', 'reload', 'waitForNavigation' ] ) {
		const original = page[ m ].bind( page );
		page[ m ] = async ( ...args ) => {
			const result = await original( ...args );
			await page.waitForLoadState( 'networkidle', { timeout: 5000 } ).catch( () => {} );
			return result;
		};
	}
}

async function launch() {
	const browser = await playwright[ BROWSER ].launch();
	if ( 'webkit' === BROWSER ) {
		const newContext = browser.newContext.bind( browser );
		browser.newContext = async ( ...args ) => {
			const context = await newContext( ...args );
			context.on( 'page', settleAfterNavigation );
			return context;
		};
		browser.newPage = async ( ...args ) => {
			const context = await browser.newContext( ...args );
			const page = await context.newPage();
			return page;
		};
	}
	return browser;
}

// Context options of a phone at 390 px for the selected engine, from Playwright's device
// descriptors: Chromium `Pixel 7`, WebKit `iPhone 13` (both mobile + touch). Firefox has no
// mobile emulation (`isMobile` is not supported): viewport, touch and a Firefox-for-Android
// user agent with the launched version instead. The viewport is 390 × 844 everywhere
// (iPhone 12–15 width).
function phone( browser ) {
	const viewport = { width: PHONE_WIDTH, height: 844 };
	if ( 'firefox' === BROWSER ) {
		const v = ( browser && browser.version() ) || '140.0';
		return { viewport, screen: viewport, deviceScaleFactor: 3, hasTouch: true, userAgent: 'Mozilla/5.0 (Android 14; Mobile; rv:' + v + ') Gecko/' + v + ' Firefox/' + v };
	}
	const d = Object.assign( {}, playwright.devices[ 'webkit' === BROWSER ? 'iPhone 13' : 'Pixel 7' ] );
	delete d.defaultBrowserType;
	return Object.assign( d, { viewport, screen: viewport } );
}

// Context options: the phone with CCAP_E2E_DEVICE=mobile, otherwise the given desktop options.
function contextOptions( browser, desktop ) {
	return MOBILE ? phone( browser ) : ( desktop || {} );
}

// Label for results ("firefox, phone 390 px").
function where() {
	return BROWSER + ( MOBILE ? ', phone ' + PHONE_WIDTH + ' px' : ', desktop' );
}

// Screenshot of the current page into CCAP_E2E_SHOTS (a directory; nothing without it), named
// after the label, engine and device.
async function shot( page, label ) {
	const dir = process.env.CCAP_E2E_SHOTS;
	if ( ! dir ) {
		return;
	}
	fs.mkdirSync( dir, { recursive: true } );
	const name = ( BROWSER + '-' + ( MOBILE ? 'phone' : 'desktop' ) + '-' + label ).toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-|-$/g, '' );
	await page.screenshot( { path: path.join( dir, name + '.png' ), fullPage: true } ).catch( () => {} );
}

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

// Submit the WordPress login form (all scripts use this one helper).
async function submitLogin( page ) {
	await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
}

async function login( page, user ) {
	await page.goto( BASE + '/wp-login.php' );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', process.env.CCAP_E2E_PASS );
	await submitLogin( page );
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

module.exports = { BROWSER, MOBILE, PHONE_WIDTH, launch, phone, contextOptions, where, shot, submitLogin, BASE, OUT, area, rest, recorder, record, assert, login, nonce, api, referenceLetters, answerExam, percentile };
