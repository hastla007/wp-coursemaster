// R1-18 live E2E smoke against a running site (started by tools/qa/e2e/live-smoke.sh).
// Learner U-L1 (`cc_fx_lea`): login → My learning → course room C-A → lesson L-A-01
// completed → learning question answered → exam ER-A started, answered, reloaded,
// submitted → certificate issued (outbox), opened by the learner → verified as guest.
// The fixture's correct answer is always option "Antwort A" (multi: A, B[, C]) in the
// source (R0-05 §2.3); delivery shuffles, so options are chosen by their text.
const { chromium } = require( 'playwright' );
const { execSync } = require( 'child_process' );
const crypto = require( 'crypto' );

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

// Correct option letters of a fixture question key (R0-05 §2.3, NeutralFixture::answers()).
function correctLetters( key, multi ) {
	if ( ! multi ) {
		return [ 'A' ];
	}
	if ( key === 'A2-015' || key === 'A3-009' ) {
		return [ 'A', 'B', 'C', 'D', 'E' ];
	}
	return [ 'A', 'B', 'C' ].slice( 0, 2 + ( parseInt( key.slice( -1 ), 10 ) % 2 ) );
}

async function api( page, nonce, method, path, body ) {
	return page.evaluate( async ( args ) => {
		const r = await fetch( args.url, {
			method: args.method,
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': args.nonce, 'Content-Type': 'application/json' },
			body: args.body ? JSON.stringify( args.body ) : undefined,
		} );
		return { status: r.status, data: await r.json().catch( () => null ) };
	}, { url: rest( path ), method, nonce, body } );
}

( async () => {
	const browser = await chromium.launch();
	const learner = await browser.newContext();
	const page = await learner.newPage();
	let nonce = '';
	let session = '';
	let verifyUrl = '';
	try {
		await step( 'learner logs in', async () => {
			await page.goto( BASE + '/wp-login.php' );
			await page.fill( '#user_login', 'cc_fx_lea' );
			await page.fill( '#user_pass', process.env.CCAP_E2E_PASS );
			await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
			nonce = await page.evaluate( async ( u ) => ( await fetch( u, { credentials: 'same-origin' } ) ).text(), BASE + '/wp-admin/admin-ajax.php?action=rest-nonce' );
			assert( /^[0-9a-f]{10}$/.test( nonce ), 'REST nonce for the logged-in learner' );
		} );

		await step( 'My learning lists Kurs Alpha', async () => {
			await page.goto( area( '' ) );
			assert( ( await page.title() ).length > 0, 'document title' );
			const link = page.locator( 'main a', { hasText: 'Kurs Alpha' } );
			assert( ( await link.count() ) === 1, 'one link to Kurs Alpha' );
			await Promise.all( [ page.waitForNavigation(), link.click() ] );
		} );

		await step( 'course room shows modules, lessons and the exam', async () => {
			assert( ( await page.locator( 'h1' ).innerText() ) === 'Kurs Alpha', 'course heading' );
			assert( ( await page.locator( '#lesson-' + SETUP.lesson_a_01 ).count() ) === 1, 'lesson L-A-01 listed' );
			assert( ( await page.locator( 'form button', { hasText: 'Start exam' } ).count() ) === 1, 'start form for ER-A' );
		} );

		await step( 'lesson L-A-01 completed', async () => {
			const r = await api( page, nonce, 'PUT', '/courses/' + SETUP.course_a_uid + '/lessons/' + SETUP.lesson_a_01 + '/progress', { kind: 'confirm' } );
			assert( r.status === 200, 'progress PUT ' + r.status + ' ' + JSON.stringify( r.data ) );
			assert( r.data.status === 'completed', 'lesson completed: ' + JSON.stringify( r.data ) );
		} );

		await step( 'learning question answered (no solution before, result after)', async () => {
			const q = await api( page, nonce, 'GET', '/courses/' + SETUP.course_a_uid + '/next-question' );
			assert( q.status === 200 && q.data && q.data.token, 'next question ' + q.status );
			assert( ! /is_correct|correct_ids/.test( JSON.stringify( q.data.question ) ), 'no solution in the delivery (GM-017)' );
			const key = ( q.data.question.text.match( /([AB]\d-\d{3})/ ) || [] )[ 1 ];
			const multi = q.data.question.type === 'multi_choice';
			let answer;
			if ( q.data.question.type === 'open_text' ) {
				answer = { known: true };
			} else {
				const letters = correctLetters( key, multi );
				answer = q.data.question.options.filter( ( o ) => letters.some( ( l ) => o.text.startsWith( 'Antwort ' + l + ' ' ) ) ).map( ( o ) => o.key );
			}
			const a = await api( page, nonce, 'POST', '/answers', { token: q.data.token, answer, idempotency_key: crypto.randomUUID() } );
			assert( a.status === 200 && a.data.correct === true, 'answer evaluated as correct: ' + a.status + ' ' + JSON.stringify( a.data ) );
		} );

		// @group GM-027
		// @group GM-028
		await step( 'exam started, answered, reloaded and submitted', async () => {
			await page.goto( area( 'kurs/' + SETUP.course_a_uid ) );
			await Promise.all( [ page.waitForNavigation(), page.click( 'form button:has-text("Start exam")' ) ] );
			session = ( page.url().match( /pruefung%2F(\d+)|pruefung\/(\d+)/ ) || [] ).slice( 1 ).find( Boolean );
			assert( session, 'redirected to the exam room: ' + page.url() );
			const sections = page.locator( 'section.ccap-question' );
			assert( ( await sections.count() ) === 30, '30 questions' );
			assert( ( await page.locator( '.ccap-feedback, .ccap-correct, .ccap-wrong' ).count() ) === 0, 'no feedback while running (GM-028)' );
			const open = [];
			for ( let i = 0; i < 30; i++ ) {
				const s = sections.nth( i );
				const key = ( ( await s.locator( '.ccap-qtext' ).innerText() ).match( /([AB]\d-\d{3})/ ) || [] )[ 1 ];
				const boxes = s.locator( 'input[type=checkbox][name^="answer"], input[type=radio]' );
				if ( ( await boxes.count() ) === 0 ) {
					open.push( i + 1 );
					continue;
				}
				const multi = ( await s.locator( 'input[type=checkbox][name^="answer"]' ).count() ) > 0;
				for ( const letter of correctLetters( key, multi ) ) {
					await s.locator( 'li', { hasText: 'Antwort ' + letter + ' zu ' + key } ).locator( 'input' ).check();
				}
			}
			await Promise.all( [ page.waitForNavigation(), page.click( 'button[name=ccap_do][value=save]' ) ] );
			const checked = await page.locator( 'section.ccap-question input:checked' ).count();
			await page.reload();
			assert( ( await page.locator( 'section.ccap-question input:checked' ).count() ) === checked && checked >= 25, 'answers kept after reload (' + checked + ')' );
			await page.goto( area( 'kurs/' + SETUP.course_a_uid ) );
			assert( ( await page.locator( 'a', { hasText: 'Continue exam' } ).count() ) === 1, 'course room offers "Continue exam" (resume, GM-027)' );
			await page.click( 'a:has-text("Continue exam")' );
			assert( page.url().includes( session ), 'same attempt resumed' );
			// Open questions have no control in the exam room without JavaScript (finding R1-18 §4):
			// the learner uses the exam API (reveal, then self-assessment).
			for ( const position of open ) {
				const rv = await api( page, nonce, 'POST', '/exam-sessions/' + session + '/answers/' + position + '/reveal' );
				assert( rv.status === 200, 'reveal ' + rv.status );
				const sv = await api( page, nonce, 'PUT', '/exam-sessions/' + session + '/answers/' + position, { answer: { known: true } } );
				assert( sv.status === 200, 'open answer ' + sv.status + ' ' + JSON.stringify( sv.data ) );
			}
			await page.check( '#ccap-confirm' );
			await Promise.all( [ page.waitForNavigation(), page.click( 'button[name=ccap_do][value=submit]' ) ] );
			const result = await page.locator( 'main h2' ).allInnerTexts();
			assert( result.includes( 'Passed' ), 'result page says Passed: ' + JSON.stringify( result ) );
		} );

		await step( 'certificate issued and listed for the learner', async () => {
			execSync( process.env.CCAP_E2E_DISPATCH, { stdio: 'inherit' } );
			const c = await api( page, nonce, 'GET', '/me/certificates' );
			assert( c.status === 200 && Array.isArray( c.data ) && c.data.length === 1, 'one certificate: ' + JSON.stringify( c.data ) );
			assert( c.data[ 0 ].course_title === 'Kurs Alpha' && c.data[ 0 ].status === 'valid', 'certificate of Kurs Alpha, valid: ' + JSON.stringify( c.data[ 0 ] ) );
			await page.goto( c.data[ 0 ].view_url );
			verifyUrl = await page.locator( '.ccap-verify a' ).getAttribute( 'href' );
			assert( verifyUrl && verifyUrl.startsWith( BASE ), 'verification link on the certificate page: ' + verifyUrl );
		} );

		await step( 'guest verifies the certificate', async () => {
			const guest = await browser.newContext();
			const g = await guest.newPage();
			const response = await g.goto( verifyUrl );
			assert( response.status() === 200, 'verification page 200, got ' + response.status() );
			const text = await g.locator( 'body' ).innerText();
			assert( text.includes( 'Kurs Alpha' ), 'course named on the verification page' );
			assert( ( await g.locator( '.ccap-status' ).count() ) === 1, 'status shown' );
			assert( ! text.includes( 'cc_fx_lea' ) && ! text.includes( 'fixture.test' ), 'no login or address on the public page' );
			const bad = await g.goto( verifyUrl.replace( /[0-9a-f]{4}$/, '0000' ) );
			assert( ! ( await g.locator( 'body' ).innerText() ).includes( 'Kurs Alpha' ), 'a wrong token reveals nothing (' + bad.status() + ')' );
			await guest.close();
		} );

		await step( 'My learning shows progress', async () => {
			await page.goto( area( '' ) );
			const value = await page.locator( 'progress' ).first().getAttribute( 'value' );
			assert( parseInt( value, 10 ) > 0, 'progress > 0 (' + value + ')' );
		} );
	} catch ( e ) {
		process.exitCode = 1;
	} finally {
		console.log( results.join( '\n' ) );
		await browser.close();
	}
} )();
