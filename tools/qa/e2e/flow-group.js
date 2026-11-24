// R1-18 B-4 · R0-11 Flow 3 (Gruppenverwalter lädt ein) and the reference course from the
// learner's side (E-168: with assignment); browser from CCAP_E2E_BROWSER, CCAP_E2E_DEVICE=mobile = phone.
// - Group manager `cc_e2e_author` (Course Manager) creates a group with 2 seats for the
//   reference course of Flow 1 (A-10), invites ina@example.test; the outbox/notification
//   queue runs (WP-Cron), the invitation mail lands in the mail sink.
// - The invited person opens the link as a guest (no group data), logs in, comes back to
//   the invitation (L-17), accepts; "My learning" lists the course; the manager sees the
//   member as active, 1 of 2 seats used. A renewed group link invalidates the old one (F3.3).
// - The new member works through the course: lesson marked as read, 5 practice questions,
//   assignment written and submitted (REST: no learning-area UI yet), graded by the manager
//   (REST: no grading screen yet), exam 20 of 50 passed in the exam room, certificate listed.
// Requires $CCAP_E2E_OUT/reference-course.json from flow-author.js.
const { execSync } = require( 'child_process' );
const crypto = require( 'crypto' );
const fs = require( 'fs' );
const path = require( 'path' );
const { launch, contextOptions, submitLogin, BASE, OUT, area, recorder, record, assert, login, api, referenceLetters, answerExam } = require( './lib' );

const { step, results } = recorder( 'flow3-group' );
const COURSE = JSON.parse( fs.readFileSync( path.join( OUT, 'reference-course.json' ), 'utf8' ) );
const MAILS = process.env.CCAP_E2E_MAIL;
const GROUPS = BASE + '/wp-admin/admin.php?page=course-captain-groups';
const COURSE_TITLE = 'Referenzkurs Zeitmanagement (E2E)';

function mails() {
	return fs.existsSync( MAILS ) ? fs.readFileSync( MAILS, 'utf8' ).trim().split( '\n' ).filter( Boolean ).map( ( l ) => JSON.parse( l ) ) : [];
}

( async () => {
	const browser = await launch();
	const manager = await browser.newContext( contextOptions( browser, { viewport: { width: 1280, height: 900 } } ) );
	const mpage = await manager.newPage();
	const invitee = await browser.newContext( contextOptions( browser, { viewport: { width: 1280, height: 900 } } ) );
	const ipage = await invitee.newPage();
	const timings = [];
	let mnonce = '';
	let inonce = '';
	let groupUrl = '';
	let claimUrl = '';
	try {
		await step( 'administrator switches groups on (Settings › Enrollment; default off)', async () => {
			const admin = await browser.newContext( contextOptions( browser ) );
			const apage = await admin.newPage();
			await login( apage, 'cc_e2e_admin' );
			await apage.goto( BASE + '/wp-admin/admin.php?page=course-captain-settings&tab=enrollment' );
			await apage.check( '#ccap-field-enrollment-groups-enabled' );
			await Promise.all( [ apage.waitForNavigation(), apage.getByRole( 'button', { name: 'Save changes' } ).click() ] );
			assert( await apage.isChecked( '#ccap-field-enrollment-groups-enabled' ), 'groups switched on: ' + ( await apage.locator( '.notice' ).allInnerTexts() ).join( ' | ' ) );
			await admin.close();
		} );

		await step( 'group manager creates a group with 2 seats for the reference course (A-10)', async () => {
			mnonce = await login( mpage, 'cc_e2e_author' );
			await mpage.goto( GROUPS );
			await mpage.fill( '#ccap-new-name', 'Team Zeitplan' );
			await mpage.fill( '#ccap-new-owner', 'cc_e2e_author' );
			await mpage.fill( '#ccap-new-seats', '2' );
			await mpage.check( 'input[name="courses[]"][value="' + COURSE.course + '"]' );
			const until = new Date( Date.now() + 30 * 86400000 ).toISOString().slice( 0, 10 );
			await mpage.fill( '#ccap-new-valid', until );
			await Promise.all( [ mpage.waitForNavigation(), mpage.getByRole( 'button', { name: 'Create group' } ).click() ] );
			const notice = await mpage.locator( '.wrap .notice' ).first().innerText();
			assert( notice.startsWith( 'Saved.' ), 'created: ' + notice );
			assert( /group=\d+/.test( mpage.url() ), 'detail page of the new group: ' + mpage.url() );
			groupUrl = mpage.url().replace( /&ccap_group_[a-z]+=[^&]*/g, '' );
			assert( ( await mpage.locator( 'dd', { hasText: '0 of 2 used' } ).count() ) === 1, 'seats 0 of 2' );
		} );

		await step( 'manager invites by email; the invitation mail carries the link', async () => {
			await mpage.fill( '#ccap-invite', 'ina@example.test' );
			await Promise.all( [ mpage.waitForNavigation(), mpage.getByRole( 'button', { name: 'Invite' } ).click() ] );
			const notice = await mpage.locator( '.wrap .notice' ).first().innerText();
			assert( /Saved\. Affected entries: 1\./.test( notice ), 'invited: ' + notice );
			assert( ( await mpage.locator( 'td', { hasText: 'ina@example.test' } ).count() ) >= 1, 'invitation listed' );
			execSync( process.env.CCAP_E2E_DISPATCH, { stdio: 'inherit' } );
			const mail = mails().find( ( m ) => [].concat( m.to ).includes( 'ina@example.test' ) );
			assert( mail, 'invitation mail in the sink: ' + JSON.stringify( mails().map( ( m ) => m.to ) ) );
			// HTML mail: the link of the button, entities decoded.
			claimUrl = ( ( mail.message.match( /href="([^"]*ccap_claim=[^"]+)"/ ) || [] )[ 1 ] || '' ).replace( /&#0?38;|&amp;/g, '&' );
			assert( claimUrl && claimUrl.startsWith( BASE ), 'claim link in the mail: ' + mail.message.slice( 0, 300 ) );
		} );

		await step( 'invited person opens the link as a guest, logs in and returns to the invitation (L-17)', async () => {
			await ipage.goto( claimUrl );
			// The learning area sends guests to the login (L-03) with the invitation as target;
			// a site with a public learning area shows the hint page of the invitation first.
			if ( ! ipage.url().includes( 'wp-login.php' ) ) {
				const guest = await ipage.locator( 'main' ).innerText();
				assert( guest.includes( 'You have been invited to a group' ), 'invitation hint for guests' );
				await Promise.all( [ ipage.waitForNavigation(), ipage.locator( 'main a', { hasText: 'Log in' } ).click() ] );
			}
			assert( decodeURIComponent( ipage.url() ).includes( 'ccap_claim=' ), 'login keeps the invitation as target: ' + ipage.url() );
			assert( ! ( await ipage.locator( 'body' ).innerText() ).includes( 'Team Zeitplan' ), 'guests see no group data' );
			await ipage.fill( '#user_login', 'cc_e2e_ina' );
			await ipage.fill( '#user_pass', process.env.CCAP_E2E_PASS );
			await submitLogin( ipage );
			assert( ipage.url().includes( 'ccap_claim=' ), 'back at the invitation: ' + ipage.url() );
			assert( ( await ipage.locator( 'main' ).innerText() ).includes( 'You have been invited to the group "Team Zeitplan".' ), 'group named after login' );
		} );

		await step( 'invited person accepts; My learning lists the course', async () => {
			await Promise.all( [ ipage.waitForNavigation(), ipage.getByRole( 'button', { name: 'Accept invitation' } ).click() ] );
			assert( ! ipage.url().includes( 'ccap_status' ), 'accepted without error: ' + ipage.url() );
			const link = ipage.locator( 'main a', { hasText: COURSE_TITLE } );
			assert( ( await link.count() ) === 1, 'course in My learning: ' + ( await ipage.locator( 'main' ).innerText() ).slice( 0, 300 ) );
			inonce = await ( require( './lib' ).nonce )( ipage );
		} );

		await step( 'manager sees the member active, 1 of 2 seats used', async () => {
			await mpage.goto( groupUrl );
			assert( ( await mpage.locator( 'dd', { hasText: '1 of 2 used' } ).count() ) === 1, 'seats 1 of 2: ' + ( await mpage.locator( 'dl' ).innerText() ) );
			const row = ( await mpage.locator( 'table.widefat tbody tr' ).allInnerTexts() ).join( ' // ' );
			assert( /^member\s+Ina E2E/m.test( row ) && ! /invited/.test( row ), 'invitation became an active member: ' + row );
		} );

		await step( 'renewed group link: the old link is invalid (F3.3)', async () => {
			const linkForm = mpage.locator( 'form.ccap-group-form', { has: mpage.locator( 'input[name=op][value=link]' ) } );
			assert( ( await linkForm.count() ) === 1, 'group link form' );
			await Promise.all( [ mpage.waitForNavigation(), linkForm.locator( 'button' ).click() ] );
			const first = await mpage.inputValue( '#ccap-group-link' );
			await mpage.reload();
			assert( ( await mpage.locator( '#ccap-group-link' ).count() ) === 0, 'link shown only once' );
			await Promise.all( [ mpage.waitForNavigation(), mpage.locator( 'form.ccap-group-form', { has: mpage.locator( 'input[name=op][value=link]' ) } ).locator( 'button' ).click() ] );
			const second = await mpage.inputValue( '#ccap-group-link' );
			assert( first !== second && /ccap_claim=ccg_/.test( second ), 'new group link' );
			await ipage.goto( first );
			const text = await ipage.locator( 'main' ).innerText();
			assert( ! text.includes( 'Team Zeitplan' ) && ( await ipage.getByRole( 'button', { name: 'Accept invitation' } ).count() ) === 0, 'old link reveals nothing and cannot be accepted: ' + text.slice( 0, 200 ) );
		} );

		const uid = COURSE.course_uid;
		const lessons = COURSE.lessons;
		const timed = async ( name, fn ) => {
			const t0 = Date.now();
			const out = await fn();
			timings.push( { name, ms: Date.now() - t0 } );
			return out;
		};

		await step( 'member reads lesson L01 and marks it as read (L-06)', async () => {
			await timed( 'page: course room', () => ipage.goto( area( 'kurs/' + uid ) ) );
			const listed = await ipage.locator( 'main a[href*="lektion"], main a[href*="lesson"]' ).count();
			assert( listed >= 10, '10 lessons listed in the course room: ' + listed );
			await timed( 'page: lesson', () => ipage.goto( area( 'kurs/' + uid + '/lektion/' + lessons.L01 ) ) );
			const button = ipage.getByRole( 'button', { name: /Mark as (read|completed)/ } );
			assert( ( await button.count() ) === 1, 'completion button' );
			await timed( 'form: mark as read', () => Promise.all( [ ipage.waitForNavigation(), button.click() ] ) );
			assert( ( await ipage.locator( '.ccap-lesson-state' ).innerText() ).includes( 'completed' ), 'lesson completed' );
		} );

		await step( 'member answers 5 practice questions (L-08)', async () => {
			for ( let i = 0; i < 5; i++ ) {
				const q = await api( ipage, inonce, 'GET', '/courses/' + uid + '/next-question' );
				timings.push( { name: 'api: next-question', ms: q.ms } );
				assert( q.status === 200 && q.data.token, 'next question ' + q.status + ' ' + JSON.stringify( q.data ).slice( 0, 200 ) );
				const key = ( q.data.question.text.match( /(RK-Q-\d{3})/ ) || [] )[ 1 ];
				const letters = referenceLetters( key );
				const answer = q.data.question.options.filter( ( o ) => letters.some( ( l ) => o.text.startsWith( 'Antwort ' + l + ' ' ) ) ).map( ( o ) => o.key );
				const a = await api( ipage, inonce, 'POST', '/answers', { token: q.data.token, answer: q.data.question.type === 'single_choice' ? answer[ 0 ] : answer, idempotency_key: crypto.randomUUID() } );
				timings.push( { name: 'api: answer', ms: a.ms } );
				assert( a.status === 200 && a.data.correct === true, 'answer correct: ' + a.status + ' ' + JSON.stringify( a.data ).slice( 0, 200 ) );
			}
		} );

		let submission = '';
		await step( 'member writes and submits the assignment (E-168, REST; no learning-area form yet)', async () => {
			await ipage.goto( area( 'kurs/' + uid + '/lektion/' + lessons.L07 ) );
			assert( ( await ipage.locator( 'main' ).innerText() ).includes( 'Beschreibe deinen Wochenplan' ), 'instructions shown in the lesson' );
			const d = await api( ipage, inonce, 'PUT', '/courses/' + uid + '/assignments/' + lessons.L07 + '/draft', { text: 'Montag plane ich die Woche, Freitag schaue ich zurück. Pufferzeiten nach dem Mittag.' } );
			timings.push( { name: 'api: assignment draft', ms: d.ms } );
			assert( d.status === 200 && d.data.submission_uid, 'draft saved: ' + d.status + ' ' + JSON.stringify( d.data ) );
			submission = d.data.submission_uid;
			const s = await api( ipage, inonce, 'POST', '/assignment-submissions/' + submission + '/submit' );
			timings.push( { name: 'api: assignment submit', ms: s.ms } );
			assert( s.status === 200 && s.data.status === 'submitted', 'submitted: ' + s.status + ' ' + JSON.stringify( s.data ) );
		} );

		await step( 'manager grades the submission (REST; no grading screen yet)', async () => {
			const q = await api( mpage, mnonce, 'GET', '/grading/queue?course=' + uid );
			assert( q.status === 200, 'grading queue ' + q.status + ' ' + JSON.stringify( q.data ) );
			const items = Array.isArray( q.data ) ? q.data : ( q.data.items || [] );
			assert( items.some( ( i ) => i.submission_uid === submission || i.uid === submission ), 'submission in the queue: ' + JSON.stringify( q.data ).slice( 0, 300 ) );
			const g = await api( mpage, mnonce, 'PUT', '/assignment-submissions/' + submission + '/grade', { action: 'grade', points_milli: 8000, feedback: 'Gut geplant.' }, { 'Idempotency-Key': crypto.randomUUID() } );
			assert( g.status === 200 && g.data.status === 'graded', 'graded: ' + g.status + ' ' + JSON.stringify( g.data ) );
			const v = await api( ipage, inonce, 'GET', '/courses/' + uid + '/assignments/' + lessons.L07 );
			assert( JSON.stringify( v.data ).includes( 'graded' ), 'member sees the grade: ' + JSON.stringify( v.data ).slice( 0, 300 ) );
		} );

		await step( 'member passes the exam 20 of 50 in the exam room (L-12…L-14)', async () => {
			await ipage.goto( area( 'kurs/' + uid ) );
			await timed( 'form: start exam', () => Promise.all( [ ipage.waitForNavigation(), ipage.click( 'form button:has-text("Start exam")' ) ] ) );
			const count = await ipage.locator( 'section.ccap-question' ).count();
			assert( count === 20, '20 questions: ' + count );
			await timed( 'form: answer + save 20 questions', () => answerExam( ipage, ( key ) => referenceLetters( key ) ) );
			await ipage.check( '#ccap-confirm' );
			await timed( 'form: submit exam', () => Promise.all( [ ipage.waitForNavigation(), ipage.click( 'button.ccap-button[name=ccap_do][value=submit]' ) ] ) );
			const result = await ipage.locator( 'main h2' ).allInnerTexts();
			assert( result.includes( 'Passed' ), 'Passed: ' + JSON.stringify( result ) );
		} );

		await step( 'certificate of the reference course listed for the member', async () => {
			execSync( process.env.CCAP_E2E_DISPATCH, { stdio: 'inherit' } );
			const c = await api( ipage, inonce, 'GET', '/me/certificates' );
			assert( c.status === 200 && c.data.some( ( x ) => x.course_title === COURSE_TITLE && x.status === 'valid' ), 'certificate: ' + JSON.stringify( c.data ) );
		} );
	} catch ( e ) {
		process.exitCode = 1;
		await ipage.screenshot( { path: path.join( OUT, 'flow3-failure-member.png' ) } ).catch( () => {} );
		await mpage.screenshot( { path: path.join( OUT, 'flow3-failure-manager.png' ) } ).catch( () => {} );
	} finally {
		for ( const t of timings ) {
			record( { flow: 'learner-reference', step: t.name, ms: Math.round( t.ms ) } );
		}
		console.log( results.join( '\n' ) );
		await browser.close();
	}
} )();
