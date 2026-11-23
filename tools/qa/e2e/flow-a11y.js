// R1-18 B-4: automated accessibility checks of the core pages on the live site (after the flows).
// Learner pages (My learning, course room, text/assignment/exam lesson, invitation, certificate,
// public verification) and the plugin's admin regions (lesson/course meta boxes, groups screen).
//
// With AXE_PATH=<axe.min.js> axe-core runs on every page (tags wcag2a, wcag2aa, wcag21a, wcag21aa,
// wcag22aa; admin pages only inside the plugin's region). axe-core is not a dependency of the
// repository and is not downloaded here; without it the built-in DOM checks run: lang, title,
// one h1 and no skipped heading level, accessible names of form controls/links/buttons,
// alt texts, unique IDs, a main landmark, skip link first (learning area), no horizontal scrolling
// at 320 px (A11Y-050) and a visible focus outline on every tab stop of the learning area.
const { chromium } = require( 'playwright' );
const fs = require( 'fs' );
const path = require( 'path' );
const { BASE, OUT, area, assert, login, api } = require( './lib' );

const COURSE = JSON.parse( fs.readFileSync( path.join( OUT, 'reference-course.json' ), 'utf8' ) );
const AXE = process.env.AXE_PATH && fs.existsSync( process.env.AXE_PATH ) ? process.env.AXE_PATH : null;

// Built-in checks inside `scope` (CSS selector, default the whole document).
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
	for ( const el of root.querySelectorAll( '[id]' ) ) {
		ids[ el.id ] = ( ids[ el.id ] || 0 ) + 1;
	}
	for ( const [ id, n ] of Object.entries( ids ) ) {
		if ( n > 1 ) {
			problems.push( 'duplicate id "' + id + '" (' + n + ')' );
		}
	}
	return problems;
}

async function check( page, label, url, opts ) {
	const o = opts || {};
	if ( url ) {
		await page.goto( url );
	}
	if ( o.ready ) {
		await o.ready( page );
	}
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
		await page.setViewportSize( { width: 320, height: 640 } );
		const px = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		if ( px > 0 ) {
			problems.push( 'horizontal scrolling at 320 px: ' + px );
		}
		await page.setViewportSize( { width: 1280, height: 900 } );
	}
	let axe = null;
	if ( AXE ) {
		await page.addScriptTag( { path: AXE } );
		axe = await page.evaluate( async ( scope ) => {
			const res = await window.axe.run( scope ? { include: [ scope ] } : document, { runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] }, resultTypes: [ 'violations' ] } );
			return res.violations.map( ( v ) => 'axe ' + v.id + ' (' + v.nodes.length + '): ' + v.nodes.map( ( n ) => n.target.join( ' ' ) ).slice( 0, 3 ).join( ', ' ) );
		}, o.scope || null );
		problems.push( ...axe );
	}
	return { page: label, problems: [ ...new Set( problems ) ] };
}

( async () => {
	const browser = await chromium.launch();
	const report = [];
	try {
		const member = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
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
		const guest = await browser.newContext();
		const gp = await guest.newPage();
		report.push( await check( gp, 'L-16 verification (guest)', verify ) );
		await guest.close();

		const author = await browser.newContext( { viewport: { width: 1400, height: 1000 } } );
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
	console.log( 'a11y (' + ( AXE ? 'axe-core + DOM checks' : 'DOM checks, axe-core not available' ) + '):' );
	for ( const r of report ) {
		console.log( ( r.problems.length ? 'FAIL ' : 'ok   ' ) + r.page + ( r.problems.length ? ': ' + r.problems.join( ' | ' ) : '' ) );
	}
	if ( OUT ) {
		fs.writeFileSync( path.join( OUT, 'a11y.json' ), JSON.stringify( { axe: !! AXE, report }, null, 1 ) );
	}
	process.exitCode = failed.length ? 1 : 0;
} )();
