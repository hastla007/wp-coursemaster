// R1-18 live E2E: exam room in the browser (started by tools/qa/e2e/live-smoke.sh after live-smoke.js).
// Learner U-L2 (`cc_fx_ole`, grant G-2 on C-A) on a phone viewport:
// - GM-050: answers are saved while typing; offline at the fifth answer the answer stays in
//   localStorage (1 entry), a fixed notice is shown, the server keeps 4 answers; back online
//   the entry is sent and removed (0 entries), the server has 5 answers.
// - GM-051: "Cancel exam" asks first; confirming returns to the course room with the start
//   button and exactly one message (also none after a reload).
// - PRIV-018 (R1-18 h3): a new attempt with a buffered answer (plus a sessionStorage entry and a
//   key of another plugin); logging out removes every `ccap_exam_*` entry and the marker cookie,
//   the foreign key stays; the second account (U-L1) logging in on the same browser finds none.
const { launch, phone, submitLogin } = require( './lib' );

const BASE = process.env.CCAP_E2E_URL;
const SETUP = JSON.parse( process.env.CCAP_E2E_SETUP || '{}' );
const area = ( p ) => BASE + '/?course_captain_app=' + encodeURIComponent( p );
const rest = ( p ) => BASE + '/?rest_route=' + encodeURIComponent( '/course-captain/v1' + p );
const results = [];

async function step( name, fn ) {
	try {
		await fn();
		results.push( 'ok   ' + name );
	} catch ( e ) {
		results.push( 'FAIL ' + name + ': ' + e.message );
		throw e;
	}
}

function assert( cond, message ) {
	if ( ! cond ) {
		throw new Error( message );
	}
}

( async () => {
	const browser = await launch();
	const context = await browser.newContext( phone( browser ) );
	const page = await context.newPage();
	let nonce = '';
	let session = '';
	let storeKey = '';
	// Server state through the context's API client (shares the login cookie, not the page's network emulation).
	const answered = async () => {
		const r = await context.request.get( rest( '/exam-sessions/' + session ), { headers: { 'X-WP-Nonce': nonce } } );
		assert( r.status() === 200, 'session GET ' + r.status() );
		return ( await r.json() ).questions.filter( ( q ) => q.answered ).map( ( q ) => q.position );
	};
	const buffered = () => page.evaluate( ( key ) => Object.keys( JSON.parse( window.localStorage.getItem( key ) || '{}' ) ).length, storeKey );
	try {
		await step( 'learner U-L2 logs in and starts ER-A', async () => {
			await page.goto( BASE + '/wp-login.php' );
			await page.fill( '#user_login', 'cc_fx_ole' );
			await page.fill( '#user_pass', process.env.CCAP_E2E_PASS );
			await submitLogin( page );
			nonce = await page.evaluate( async ( u ) => ( await fetch( u, { credentials: 'same-origin' } ) ).text(), BASE + '/wp-admin/admin-ajax.php?action=rest-nonce' );
			assert( /^[0-9a-f]{10}$/.test( nonce ), 'REST nonce' );
			await page.goto( area( 'kurs/' + SETUP.course_a_uid ) );
			await Promise.all( [ page.waitForNavigation(), page.click( 'form button:has-text("Start exam")' ) ] );
			session = ( page.url().match( /pruefung%2F(\d+)|pruefung\/(\d+)/ ) || [] ).slice( 1 ).find( Boolean );
			assert( session, 'exam room: ' + page.url() );
			storeKey = 'ccap_exam_' + await page.getAttribute( 'form.ccap-exam-form', 'data-ccap-session' );
			assert( /^ccap_exam_[0-9a-f-]{36}$/.test( storeKey ), 'buffer key = session UID: ' + storeKey );
		} );

		// @group GM-050
		await step( 'GM-050 offline answer buffered, notice shown, re-sent', async () => {
			const sections = page.locator( 'section.ccap-question[data-ccap-type=single_choice], section.ccap-question[data-ccap-type=true_false]' );
			assert( ( await sections.count() ) >= 5, 'five single-choice questions' );
			const positions = [];
			for ( let i = 0; i < 5; i++ ) {
				positions.push( await sections.nth( i ).getAttribute( 'data-ccap-position' ) );
			}
			for ( const position of positions.slice( 0, 4 ) ) {
				await Promise.all( [
					page.waitForResponse( ( r ) => r.request().method() === 'PUT' && decodeURIComponent( r.url() ).includes( '/answers/' + position ) && r.status() === 200 ),
					page.locator( '#ccap-q-' + position + ' input[type=radio]' ).first().check(),
				] );
			}
			assert( ( await answered() ).length === 4, 'four answers on the server' );
			assert( ( await buffered() ) === 0, 'nothing buffered while online' );
			assert( await page.locator( '#ccap-offline' ).isHidden(), 'no notice while online' );

			await context.setOffline( true );
			await page.locator( '#ccap-q-' + positions[ 4 ] + ' input[type=radio]' ).first().check();
			await page.waitForSelector( '#ccap-offline', { state: 'visible' } );
			assert( ( await buffered() ) === 1, '1 localStorage entry while offline' );
			const position = await page.$eval( '#ccap-offline', ( el ) => window.getComputedStyle( el ).position );
			assert( position === 'fixed', 'notice is position:fixed on the phone, got ' + position );
			await page.waitForFunction( () => document.getElementById( 'ccap-exam-live' ).textContent === document.getElementById( 'ccap-offline' ).textContent, null, { timeout: 5000 } );
			assert( ( await answered() ).length === 4, 'server still has 4 answers' );

			await context.setOffline( false );
			await page.waitForFunction( ( key ) => null === window.localStorage.getItem( key ), storeKey, { timeout: 20000 } );
			await page.waitForSelector( '#ccap-offline', { state: 'hidden' } );
			const now = await answered();
			assert( now.length === 5 && now.includes( parseInt( positions[ 4 ], 10 ) ), 'fifth answer re-sent: ' + JSON.stringify( now ) );
			await page.reload();
			assert( ( await page.locator( '#ccap-q-' + positions[ 4 ] + ' input[type=radio]:checked' ).count() ) === 1, 'server state shown after reload' );
		} );

		// @group GM-051
		await step( 'GM-051 abort dialog, back to the course room, one message', async () => {
			await Promise.all( [ page.waitForNavigation(), page.click( 'button.ccap-button[name=ccap_do][value=cancel]' ) ] );
			const question = await page.locator( '#ccap-confirm-cancel-text' ).innerText();
			assert( question.includes( 'Do you really want to cancel the exam?' ), 'question text: ' + question );
			assert( ( await page.locator( 'a', { hasText: 'No, continue the exam' } ).count() ) === 1, 'way back offered' );
			await Promise.all( [ page.waitForNavigation(), page.click( 'button[name=ccap_do][value=cancel_confirmed]' ) ] );
			assert( page.url().includes( 'kurs' ) && page.url().includes( SETUP.course_a_uid ), 'course room: ' + page.url() );
			assert( ( await page.locator( 'form button', { hasText: 'Start exam' } ).count() ) === 1, 'start button back' );
			assert( ( await page.locator( 'text=The exam was cancelled.' ).count() ) === 1, 'exactly one message' );
			assert( null === await page.evaluate( ( key ) => window.localStorage.getItem( key ), storeKey ), 'no buffer left' );
			await page.reload();
			assert( ( await page.locator( 'text=The exam was cancelled.' ).count() ) === 0, 'no duplicate message after reload' );
			const state = await context.request.get( rest( '/exam-sessions/' + session ), { headers: { 'X-WP-Nonce': nonce } } );
			assert( ( await state.json() ).status === 'cancelled', 'attempt cancelled' );
		} );

		// @group PRIV-018
		await step( 'PRIV-018 logout empties the exam buffer, second account finds nothing', async () => {
			await Promise.all( [ page.waitForNavigation(), page.click( 'form button:has-text("Start exam")' ) ] );
			const key = 'ccap_exam_' + await page.getAttribute( 'form.ccap-exam-form', 'data-ccap-session' );
			assert( key !== storeKey, 'new attempt, new buffer key' );
			await page.evaluate( ( k ) => {
				window.localStorage.setItem( k, JSON.stringify( { 1: { a: 'x', t: Date.now() } } ) );
				window.sessionStorage.setItem( 'ccap_exam_probe', '1' );
				window.localStorage.setItem( 'other_plugin_key', 'keep' );
			}, key );
			const exam = () => page.evaluate( () => [ window.localStorage, window.sessionStorage ].reduce( ( n, s ) => n + Object.keys( s ).filter( ( k ) => 0 === k.indexOf( 'ccap_exam_' ) ).length, 0 ) );
			assert( ( await exam() ) === 2, 'two buffers before the logout' );
			await page.goto( BASE + '/wp-login.php?action=logout' );
			await Promise.all( [ page.waitForNavigation(), page.click( 'a[href*="action=logout"]' ) ] );
			assert( page.url().includes( 'loggedout=true' ), 'logged out: ' + page.url() );
			assert( ( await exam() ) === 0, 'no exam buffer after the logout' );
			assert( 'keep' === await page.evaluate( () => window.localStorage.getItem( 'other_plugin_key' ) ), 'keys of other plugins stay' );
			assert( ! ( await context.cookies() ).some( ( c ) => c.name === 'ccap_exam_purge' ), 'marker cookie removed' );
			await page.fill( '#user_login', 'cc_fx_lea' );
			await page.fill( '#user_pass', process.env.CCAP_E2E_PASS );
			await submitLogin( page );
			await page.goto( area( 'kurs/' + SETUP.course_a_uid ) );
			assert( ( await exam() ) === 0, 'second account: no buffer of the first' );
		} );
	} catch ( e ) {
		process.exitCode = 1;
	} finally {
		console.log( results.join( '\n' ) );
		await browser.close();
	}
} )();
