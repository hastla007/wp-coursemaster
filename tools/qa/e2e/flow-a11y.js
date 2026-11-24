// R1-18 B-4: automated accessibility checks of the core pages on the live site (after the flows).
// Learner pages (My learning, course room, text/assignment/exam lesson, invitation, certificate,
// public verification) and the plugin's admin regions (lesson/course meta boxes, groups screen).
//
// With AXE_PATH=<axe.min.js> axe-core runs on every page (tags wcag2a, wcag2aa, wcag21a, wcag21aa,
// wcag22aa; admin pages only inside the plugin's region). axe-core is not a dependency of the
// repository and is not downloaded here; without it the built-in DOM checks run: lang, title,
// one h1 and no skipped heading level, accessible names of form controls/links/buttons,
// alt texts, unique IDs, a main landmark, skip link first (learning area), no horizontal scrolling
// at 320 px (A11Y-050) and a visible focus outline on every tab stop of the learning area; the
// other pages (certificate, verification, editor boxes, groups) at 390 px (UI-F7).
const fs = require( 'fs' );
const path = require( 'path' );
const { launch, contextOptions, where, shot, PHONE_WIDTH, BASE, OUT, area, assert, login, api } = require( './lib' );

const COURSE = JSON.parse( fs.readFileSync( path.join( OUT, 'reference-course.json' ), 'utf8' ) );
const { AXE, domChecks, runAxe } = require( './a11y-checks' );

async function check( page, label, url, opts ) {
	const o = opts || {};
	if ( url ) {
		await page.goto( url );
	}
	if ( o.ready ) {
		await o.ready( page );
	}
	await shot( page, label );
	const problems = await page.evaluate( domChecks, o.scope || null );
	if ( o.learner ) {
		// The WordPress toolbar of logged-in users brings its own "Skip to toolbar" link first;
		// the learning area's skip link must be the first stop of the page itself.
		let first = '';
		for ( let i = 0; i < 2; i++ ) {
			await page.keyboard.press( 'Tab' );
			first = String( await page.evaluate( () => document.activeElement.className ) );
			if ( ! first.includes( 'screen-reader-shortcut' ) ) {
				break;
			}
		}
		if ( ! first.includes( 'ccap-skip' ) ) {
			problems.push( 'skip link is not the first tab stop of the page (' + first + ')' );
		}
		for ( let i = 0; i < 25; i++ ) {
			const f = await page.evaluate( () => {
				const el = document.activeElement;
				const s = getComputedStyle( el );
				return { what: el.tagName + ( el.id ? '#' + el.id : '' ) + ( el.name ? '[' + el.name + ']' : '' ), outline: 'none' !== s.outlineStyle && parseFloat( s.outlineWidth ) > 0, shadow: 'none' !== s.boxShadow, body: el === document.body, toolbar: !! el.closest( '#wpadminbar' ) };
			} );
			if ( f.body || f.toolbar ) {
				break;
			}
			if ( ! f.outline && ! f.shadow ) {
				problems.push( 'no visible focus on ' + f.what );
			}
			await page.keyboard.press( 'Tab' );
		}
		const size = page.viewportSize();
		await page.setViewportSize( { width: 320, height: 640 } );
		const px = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		if ( px > 0 ) {
			problems.push( 'horizontal scrolling at 320 px: ' + px );
		}
		await page.setViewportSize( size );
	}
	if ( ! o.learner ) {
		// UI-F7: admin and other pages reflow at a phone width too (WCAG 1.4.10); on a phone the
		// page is already 390 px wide. Found: the groups tables scrolled the whole page.
		const size = page.viewportSize();
		if ( size.width > PHONE_WIDTH ) {
			await page.setViewportSize( { width: PHONE_WIDTH, height: 844 } );
		}
		const px = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		if ( px > 0 ) {
			problems.push( 'horizontal scrolling at ' + PHONE_WIDTH + ' px: ' + px );
		}
		if ( size.width > PHONE_WIDTH ) {
			await page.setViewportSize( size );
		}
	}
	problems.push( ...( await runAxe( page, o.scope || null ) ) );
	return { page: label, problems: [ ...new Set( problems ) ] };
}

( async () => {
	const browser = await launch();
	const report = [];
	try {
		const member = await browser.newContext( contextOptions( browser, { viewport: { width: 1280, height: 900 } } ) );
		const mp = await member.newPage();
		const n = await login( mp, 'cc_e2e_ina' );
		const uid = COURSE.course_uid;
		report.push( await check( mp, 'L-04 My learning', area( '' ), { learner: true } ) );
		report.push( await check( mp, 'L-05 course room', area( 'kurs/' + uid ), { learner: true } ) );
		report.push( await check( mp, 'L-06 lesson (text)', area( 'kurs/' + uid + '/lektion/' + COURSE.lessons.L02 ), { learner: true } ) );
		report.push( await check( mp, 'L-06 lesson (assignment)', area( 'kurs/' + uid + '/lektion/' + COURSE.lessons.L07 ), { learner: true } ) );
		report.push( await check( mp, 'L-06 lesson (exam)', area( 'kurs/' + uid + '/lektion/' + COURSE.lessons.L10 ), { learner: true } ) );
		const certs = await api( mp, n, 'GET', '/me/certificates' );
		const cert = ( certs.data || [] ).find( ( c ) => c.course_title.includes( 'Referenzkurs' ) );
		assert( cert, 'certificate of the reference course for the A11Y check' );
		report.push( await check( mp, 'L-15 certificate', cert.view_url ) );
		const verify = await mp.locator( '.ccap-verify a' ).getAttribute( 'href' );
		const guest = await browser.newContext( contextOptions( browser ) );
		const gp = await guest.newPage();
		report.push( await check( gp, 'L-16 verification (guest)', verify ) );
		await guest.close();

		const author = await browser.newContext( contextOptions( browser, { viewport: { width: 1400, height: 1000 } } ) );
		const ap = await author.newPage();
		await login( ap, 'cc_e2e_author' );
		const metaBoxes = async ( p ) => {
			const toggle = p.getByRole( 'button', { name: 'Meta Boxes' } );
			await toggle.waitFor();
			if ( ( await toggle.getAttribute( 'aria-expanded' ) ) !== 'true' ) {
				await toggle.press( 'Enter' );
			}
		};
		report.push( await check( ap, 'A-04 lesson editor: Activity box', BASE + '/wp-admin/post.php?post=' + COURSE.lessons.L07 + '&action=edit', { scope: '#ccap-lesson-activity', ready: metaBoxes } ) );
		report.push( await check( ap, 'A-04 course editor: Modules box', BASE + '/wp-admin/post.php?post=' + COURSE.course + '&action=edit', { scope: '#ccap-course-structure', ready: metaBoxes } ) );
		report.push( await check( ap, 'A-04 course editor: Publish revision box', null, { scope: '#ccap-course-publish' } ) );
		report.push( await check( ap, 'A-10 groups', BASE + '/wp-admin/admin.php?page=course-captain-groups', { scope: '#wpbody-content .wrap' } ) );
	} catch ( e ) {
		report.push( { page: 'run', problems: [ 'error: ' + e.message ] } );
	} finally {
		await browser.close();
	}
	const failed = report.filter( ( r ) => r.problems.length );
	console.log( 'a11y [' + where() + '] (' + ( AXE ? 'axe-core + DOM checks' : 'DOM checks, axe-core not available' ) + '):' );
	for ( const r of report ) {
		console.log( ( r.problems.length ? 'FAIL ' : 'ok   ' ) + r.page + ( r.problems.length ? ': ' + r.problems.join( ' | ' ) : '' ) );
	}
	if ( OUT ) {
		fs.writeFileSync( path.join( OUT, 'a11y.json' ), JSON.stringify( { axe: !! AXE, report }, null, 1 ) );
	}
	process.exitCode = failed.length ? 1 : 0;
} )();
