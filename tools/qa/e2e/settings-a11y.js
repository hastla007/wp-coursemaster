// SET-CC-21 / SET-CC-P5: accessibility acceptance of the settings surfaces on a live site
// (started by settings-a11y.sh) in Chromium:
//
//   - every settings tab, the setup assistant (every step), the course settings box in the
//     course editor, mail texts (overview, editor), locks and course overrides (overview, one
//     lock page), settings backup and the audit log's settings view;
//   - per page: DOM checks (headings without skipped levels, one h1, accessible names, unique IDs),
//     field checks (label per control, aria-describedby targets exist, no positive tabindex),
//     keyboard walk (visible focus on every stop, tab order follows the DOM), axe-core when
//     AXE_PATH points to axe.min.js;
//   - error state: one invalid value per settings tab, in the assistant and in the mail text
//     editor is submitted; the page must come back with a focused error summary
//     (role=alert), aria-invalid on the field and aria-describedby pointing at a visible
//     "Error:" text (R0-30 T-5);
//   - mobile (SET-CC-P5): no horizontal scrolling at 320 and 390 px on every settings page and on
//     the learner pages My learning, course room, lesson and exam start.
//
// Env: CCAP_E2E_URL, CCAP_E2E_PASS, CCAP_E2E_SETUP (live-setup.php `setup` JSON), CCAP_E2E_OUT
// (optional: settings-a11y.json with the full report), AXE_PATH (optional).
const { chromium } = require( 'playwright' );
const fs = require( 'fs' );
const path = require( 'path' );
const { AXE, domChecks, fieldChecks, focusWalk, overflow, runAxe } = require( './a11y-checks' );

const BASE = process.env.CCAP_E2E_URL;
const OUT = process.env.CCAP_E2E_OUT || '';
const SETUP = JSON.parse( process.env.CCAP_E2E_SETUP || '{}' );
const admin = ( q ) => BASE + '/wp-admin/admin.php?' + q;
const area = ( p ) => BASE + '/?course_captain_app=' + encodeURIComponent( p || '' );
const WRAP = '#wpbody-content .wrap';
const TABS = [ 'general', 'enrollment', 'learning', 'exams', 'certificates', 'emails', 'display', 'privacy', 'advanced', 'hardening' ];
const STEPS = [ 'purpose_language', 'addresses', 'enrollment', 'learning_exams', 'mail_privacy', 'hardening', 'first_course', 'readiness' ];

async function login( page, user ) {
	await page.goto( BASE + '/wp-login.php' );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', process.env.CCAP_E2E_PASS );
	await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
	if ( page.url().includes( 'wp-login.php' ) ) {
		throw new Error( 'login failed: ' + user );
	}
}

// Full check of the page currently loaded (or `url`).
async function check( page, label, url, opts ) {
	const o = opts || {};
	if ( url ) {
		await page.goto( url );
	}
	if ( o.ready ) {
		await o.ready( page );
	}
	const scope = o.scope || WRAP;
	const problems = await page.evaluate( domChecks, scope );
	if ( ! o.noH1 ) {
		const h1 = await page.evaluate( ( s ) => [ ...document.querySelectorAll( s + ' h1' ) ].length, scope );
		if ( 1 !== h1 ) {
			problems.push( h1 + ' h1 in ' + scope );
		}
	}
	const fields = await page.evaluate( fieldChecks, scope );
	problems.push( ...fields.problems );
	let stops = [];
	if ( ! o.noWalk ) {
		const walk = await focusWalk( page, scope, o.walk || 60 );
		problems.push( ...walk.problems );
		stops = walk.stops;
	}
	problems.push( ...( await runAxe( page, scope ) ) );
	const mobile = {};
	if ( ! o.noMobile ) {
		const size = page.viewportSize();
		for ( const width of [ 320, 390 ] ) {
			await page.setViewportSize( { width, height: 800 } );
			mobile[ width ] = await overflow( page );
			if ( mobile[ width ] > 0 ) {
				problems.push( 'horizontal scrolling at ' + width + ' px: ' + mobile[ width ] + ' px' );
			}
		}
		await page.setViewportSize( size );
	}
	return { page: label, problems: [ ...new Set( problems ) ], controls: fields.stats.controls, stops: stops.length, mobile };
}

// Puts one invalid value into the first suitable control of the form and returns its id.
function breakOne( formSel ) {
	const form = document.querySelector( formSel );
	if ( ! form ) {
		return null;
	}
	const usable = ( el ) => ! el.disabled && 'true' !== el.getAttribute( 'aria-disabled' ) && ( el.offsetWidth || el.offsetHeight );
	const pick = ( sel ) => [ ...document.querySelectorAll( sel ) ].filter( ( el ) => ( el.form === form || form.contains( el ) ) && usable( el ) );
	const num = pick( 'input[type=number][max]' ).find( ( el ) => '' !== el.max );
	if ( num ) {
		// A nullable number needs its "value" mode switched on.
		const mode = document.getElementById( num.id.replace( /-number$/, '-value' ) );
		if ( mode && mode !== num ) {
			mode.checked = true;
		}
		num.value = String( parseInt( num.max, 10 ) + 1 );
		return num.id;
	}
	const email = pick( 'input[type=email]' )[ 0 ];
	if ( email ) {
		email.value = 'not-an-address';
		return email.id;
	}
	const pct = pick( 'input[inputmode=decimal]' )[ 0 ];
	if ( pct ) {
		pct.value = '101';
		return pct.id;
	}
	const code = pick( 'textarea.code' )[ 0 ];
	if ( code ) {
		code.value = 'https://not a host/';
		return code.id;
	}
	const text = pick( 'input[type=text]' )[ 0 ];
	if ( text ) {
		text.value = '-' + 'x'.repeat( 300 );
		return text.id;
	}
	return null;
}

// Submits the broken form and checks the error state that comes back.
async function errorState( page, label, formSel, submitSel, fieldId ) {
	const problems = [];
	await Promise.all( [ page.waitForNavigation(), page.click( submitSel ) ] );
	await page.waitForLoadState( 'load' );
	const state = await page.evaluate( ( id ) => {
		const summary = document.querySelector( '#ccap-error-summary, .notice-error[role=alert][data-ccap-focus]' );
		const el = document.getElementById( id );
		const described = el ? ( el.getAttribute( 'aria-describedby' ) || '' ).split( /\s+/ ).filter( Boolean ) : [];
		const err = described.map( ( d ) => document.getElementById( d ) ).find( ( e ) => e && /^\s*(Error|Fehler):/.test( e.textContent ) );
		return {
			summary: !! summary,
			role: summary ? summary.getAttribute( 'role' ) : '',
			focused: !! summary && document.activeElement === summary,
			links: summary ? [ ...summary.querySelectorAll( 'a[href^="#"]' ) ].map( ( a ) => a.getAttribute( 'href' ).slice( 1 ) ) : [],
			invalid: el ? el.getAttribute( 'aria-invalid' ) : null,
			error: err ? err.textContent.trim() : '',
			confirm: !! document.querySelector( '.ccap-risk-dialog, #ccap-risk-confirm' ),
		};
	}, fieldId );
	if ( ! state.summary ) {
		problems.push( 'no error summary after invalid input (' + fieldId + ( state.confirm ? ', risk confirmation instead' : '' ) + ')' );
	} else {
		if ( 'alert' !== state.role ) {
			problems.push( 'error summary without role=alert' );
		}
		if ( ! state.focused ) {
			problems.push( 'error summary not focused' );
		}
		if ( ! state.links.length ) {
			problems.push( 'error summary without links to fields' );
		}
	}
	if ( 'true' !== state.invalid ) {
		problems.push( 'field ' + fieldId + ' without aria-invalid=true' );
	}
	if ( ! state.error ) {
		problems.push( 'field ' + fieldId + ' does not reference an "Error:" text via aria-describedby' );
	}
	const fields = await page.evaluate( fieldChecks, WRAP );
	problems.push( ...fields.problems );
	problems.push( ...( await runAxe( page, WRAP ) ) );
	return { page: label + ' (error state)', problems: [ ...new Set( problems ) ], field: fieldId, message: state.error };
}

( async () => {
	const browser = await chromium.launch();
	const report = [];
	const record = ( r ) => {
		report.push( r );
		console.log( ( r.problems.length ? 'FAIL ' : 'ok   ' ) + r.page + ( r.problems.length ? ': ' + r.problems.join( ' | ' ) : '' ) );
	};
	try {
		const ctx = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
		const page = await ctx.newPage();
		page.on( 'dialog', ( d ) => d.accept() );
		await login( page, 'cc_e2e_admin' );

		// Settings: every tab, then the error state.
		for ( const tab of TABS ) {
			record( await check( page, 'settings tab ' + tab, admin( 'page=course-captain-settings&tab=' + tab ) ) );
		}
		for ( const tab of TABS ) {
			await page.goto( admin( 'page=course-captain-settings&tab=' + tab ) );
			const form = '#cc-settings-' + tab;
			const id = await page.evaluate( breakOne, form );
			if ( ! id ) {
				record( { page: 'settings tab ' + tab + ' (error state)', problems: [], field: null, message: 'no control that can take an invalid value' } );
				continue;
			}
			record( await errorState( page, 'settings tab ' + tab, form, 'button[form="cc-settings-' + tab + '"]', id ) );
		}

		// Setup assistant: every step, error state in "Addresses & pages".
		for ( const step of STEPS ) {
			record( await check( page, 'setup assistant step ' + step, admin( 'page=course-captain-setup&step=' + step ) ) );
		}
		await page.goto( admin( 'page=course-captain-setup&step=addresses' ) );
		await page.fill( '#ccap-field-general-learning-area-slug', 'wp-admin' );
		record( await errorState( page, 'setup assistant step addresses', '#cc-step', '#cc-step button.button-primary[type=submit]', 'ccap-field-general-learning-area-slug' ) );

		// Mail texts: overview, editor of the first message, error state (unknown placeholder).
		record( await check( page, 'mail texts overview', admin( 'page=course-captain-mail-texts' ) ) );
		const edit = await page.locator( WRAP + ' a[href*="type="]' ).first().getAttribute( 'href' );
		record( await check( page, 'mail text editor', edit ) );
		await page.fill( '#ccap-mail-subject', 'Hello {{no_such_placeholder}}' );
		record( await errorState( page, 'mail text editor', '#ccap-mail-form', '#ccap-mail-form button[name=do][value=save]', 'ccap-mail-subject' ) );

		// Locks and course overrides: overview and the first lock page.
		record( await check( page, 'locks overview', admin( 'page=course-captain-setting-locks' ) ) );
		const lock = await page.locator( WRAP + ' a[href*="view="]' ).first().getAttribute( 'href' ).catch( () => null );
		if ( lock ) {
			record( await check( page, 'lock page', lock ) );
		}
		record( await check( page, 'settings backup', admin( 'page=course-captain-settings-backup' ) ) );
		record( await check( page, 'audit log: settings view', admin( 'page=course-captain-audit&area=settings' ) ) );

		// Course settings box in the block editor (meta box region).
		await page.goto( BASE + '/wp-admin/post.php?post=' + SETUP.course_a + '&action=edit' );
		// The block editor's welcome guide is a modal dialog on the first visit; close it.
		const guide = page.locator( '.edit-post-welcome-guide' );
		if ( await guide.waitFor( { timeout: 10000 } ).then( () => true, () => false ) ) {
			await page.keyboard.press( 'Escape' );
			await guide.waitFor( { state: 'detached' } );
		}
		const toggle = page.getByRole( 'button', { name: /Meta Boxes|Metaboxen/ } );
		await toggle.waitFor( { timeout: 30000 } );
		if ( ( await toggle.getAttribute( 'aria-expanded' ) ) !== 'true' ) {
			await toggle.press( 'Enter' );
		}
		await page.locator( '#ccap-course-settings' ).waitFor();
		record( await check( page, 'course editor: settings box', null, { scope: '#ccap-course-settings', noH1: true, noMobile: true } ) );

		// Learner pages on the phone widths (SET-CC-P5).
		const lctx = await browser.newContext( { viewport: { width: 390, height: 844 } } );
		const lp = await lctx.newPage();
		await login( lp, 'cc_fx_lea' );
		for ( const [ label, p ] of [
			[ 'My learning', '' ],
			[ 'course room C-A', 'kurs/' + SETUP.course_a_uid ],
			[ 'lesson L-A-01', 'kurs/' + SETUP.course_a_uid + '/lektion/' + SETUP.lesson_a_01 ],
		] ) {
			await lp.goto( area( p ) );
			const problems = [];
			const mobile = {};
			for ( const width of [ 320, 390 ] ) {
				await lp.setViewportSize( { width, height: 800 } );
				mobile[ width ] = await overflow( lp );
				if ( mobile[ width ] > 0 ) {
					problems.push( 'horizontal scrolling at ' + width + ' px: ' + mobile[ width ] + ' px' );
				}
			}
			problems.push( ...( await lp.evaluate( domChecks, null ) ) );
			record( { page: 'learning area ' + label + ' (mobile)', problems, mobile } );
		}
		await lctx.close();
	} catch ( e ) {
		record( { page: 'run', problems: [ 'error: ' + e.message ] } );
	} finally {
		await browser.close();
	}
	const failed = report.filter( ( r ) => r.problems.length );
	console.log( 'settings a11y (' + ( AXE ? 'axe-core ' + AXE.replace( /.*\//, '' ) + ' + DOM checks' : 'DOM checks, axe-core not available' ) + '): ' + ( report.length - failed.length ) + '/' + report.length + ' pages ok' );
	if ( OUT ) {
		fs.writeFileSync( path.join( OUT, 'settings-a11y.json' ), JSON.stringify( { axe: !! AXE, report }, null, 1 ) );
	}
	process.exitCode = failed.length ? 1 : 0;
} )();
