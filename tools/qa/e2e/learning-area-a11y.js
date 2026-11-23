// R1-19: keyboard, focus, live regions, reflow, RTL, reduced motion and (optionally) axe-core
// on the server-rendered learning area (R0-15 A11Y-001/-002/-003/-006/-050/-060/-071/-100, I18N-007).
//
// Usage: node tools/qa/e2e/learning-area-a11y.js <dir>
//   <dir> holds home.html, room.html, exam.html and rtl.html written by
//   CCAP_DUMP_DIR=<dir> vendor/bin/phpunit -c phpunit-integration.xml.dist --filter A11yI18nTest
//   AXE_PATH=<path to axe.min.js> enables the axe-core run (tags wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa);
//   without it only the DOM/keyboard checks run (axe-core is not a repository dependency yet).
const { chromium } = require( 'playwright' );
const path = require( 'path' );
const fs = require( 'fs' );

const FOCUSABLE = 'a[href], button:not([hidden]), input:not([type=hidden]), select, textarea, [tabindex]:not([tabindex="-1"])';

async function open( page, dir, name ) {
	await page.goto( 'file://' + path.resolve( dir, name ) );
}

// Tab through the whole page; every stop must be visible, outlined, unobscured, in DOM order.
async function tabWalk( page, dir, name ) {
	// A radio group is one tab stop (its checked or first radio).
	const expected = await page.evaluate( ( sel ) => {
		const groups = {};
		return [ ...document.querySelectorAll( sel ) ].filter( ( e ) => {
			if ( e.type === 'radio' ) {
				const checked = document.querySelector( 'input[type=radio][name="' + e.name + '"]:checked' );
				if ( ( checked && checked !== e ) || ( ! checked && groups[ e.name ] ) ) {
					return false;
				}
				groups[ e.name ] = true;
			}
			return e.offsetParent !== null || e.className === 'ccap-skip';
		} ).map( ( e ) => e.id || e.getAttribute( 'href' ) || e.name || e.textContent.trim() );
	}, FOCUSABLE );
	const seen = [];
	const problems = [];
	await open( page, dir, name );
	for ( let i = 0; i < expected.length; i++ ) {
		await page.keyboard.press( 'Tab' );
		const info = await page.evaluate( () => {
			const el = document.activeElement;
			const r = el.getBoundingClientRect();
			const style = getComputedStyle( el );
			const bar = document.querySelector( '.ccap-exam-bar' );
			const b = bar && ! bar.contains( el ) ? bar.getBoundingClientRect() : null;
			// Obscured: the sticky bar is stuck at the top and overlaps the focused element (WCAG 2.4.11).
			const stuck = b && getComputedStyle( bar ).position === 'sticky' && b.top <= 1;
			return {
				key: el.id || el.getAttribute( 'href' ) || el.name || el.textContent.trim(),
				outline: style.outlineStyle !== 'none' && parseFloat( style.outlineWidth ) >= 2,
				visible: r.width > 0 && r.height > 0,
				obscured: !! stuck && r.top < b.bottom - 1 && r.bottom > b.top + 1,
			};
		} );
		seen.push( info.key );
		if ( ! info.outline ) {
			problems.push( 'no visible focus: ' + info.key );
		}
		if ( ! info.visible ) {
			problems.push( 'focused element not visible: ' + info.key );
		}
		if ( info.obscured ) {
			problems.push( 'focus obscured by sticky bar: ' + info.key );
		}
	}
	if ( JSON.stringify( seen ) !== JSON.stringify( expected ) ) {
		problems.push( 'tab order differs from DOM order' );
	}
	return { stops: seen.length, problems };
}

( async () => {
	const dir = process.argv[ 2 ];
	const axePath = process.env.AXE_PATH && fs.existsSync( process.env.AXE_PATH ) ? process.env.AXE_PATH : null;
	const browser = await chromium.launch();
	const context = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
	const page = await context.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
	await page.route( /^https?:/, ( route ) => route.abort() );
	const results = {};
	const pages = [ 'home.html', 'room.html', 'exam.html', 'rtl.html' ];

	for ( const name of pages ) {
		const r = { problems: [] };
		await open( page, dir, name );
		// Skip link first, visible on focus, moves focus to main (A11Y-002, K-02).
		await page.keyboard.press( 'Tab' );
		const skip = await page.evaluate( () => {
			const el = document.activeElement;
			const b = el.getBoundingClientRect();
			return { cls: el.className, visible: b.width > 1 && b.height > 1 && b.left >= 0 && b.right <= window.innerWidth };
		} );
		if ( skip.cls !== 'ccap-skip' || ! skip.visible ) {
			r.problems.push( 'skip link not first or not visible on focus' );
		}
		await page.keyboard.press( 'Enter' );
		const target = await page.evaluate( () => document.activeElement.id );
		if ( target !== 'ccap-main' ) {
			r.problems.push( 'skip link does not move focus to main (got ' + target + ')' );
		}
		r.tab = await tabWalk( page, dir, name );
		r.problems.push( ...r.tab.problems );

		// Reflow at 320 px (A11Y-050) and 390 px (R1-16).
		for ( const width of [ 320, 390 ] ) {
			await page.setViewportSize( { width, height: 640 } );
			const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
			if ( overflow > 0 ) {
				r.problems.push( 'horizontal scroll at ' + width + ' px: ' + overflow );
			}
		}
		await page.setViewportSize( { width: 1280, height: 900 } );

		// Reduced motion: no running animations (A11Y-060); forced colors keep a focus outline (A11Y-043).
		await page.emulateMedia( { reducedMotion: 'reduce', forcedColors: 'active' } );
		await open( page, dir, name );
		const motion = await page.evaluate( () => document.getAnimations().length );
		await page.keyboard.press( 'Tab' );
		await page.keyboard.press( 'Tab' );
		const forced = await page.evaluate( () => getComputedStyle( document.activeElement ).outlineStyle );
		if ( motion > 0 ) {
			r.problems.push( 'animations with reduced motion: ' + motion );
		}
		if ( forced === 'none' ) {
			r.problems.push( 'no focus outline in forced colors' );
		}
		await page.emulateMedia( { reducedMotion: 'no-preference', forcedColors: 'none' } );

		if ( axePath ) {
			await open( page, dir, name );
			await page.addScriptTag( { path: axePath } );
			const axe = await page.evaluate( async () => {
				const res = await window.axe.run( document, { runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] }, resultTypes: [ 'violations' ] } );
				return res.violations.map( ( v ) => v.id + ' (' + v.nodes.length + '): ' + v.nodes.map( ( n ) => n.target.join( ' ' ) ).slice( 0, 3 ).join( ', ' ) );
			} );
			r.axe = axe;
			r.problems.push( ...axe.map( ( v ) => 'axe: ' + v ) );
		}
		results[ name ] = r;
	}

	// RTL document direction (I18N-007).
	await open( page, dir, 'rtl.html' );
	const rtl = await page.evaluate( () => ( { dir: document.documentElement.dir, computed: getComputedStyle( document.body ).direction } ) );
	if ( rtl.dir !== 'rtl' || rtl.computed !== 'rtl' ) {
		results[ 'rtl.html' ].problems.push( 'document is not rtl' );
	}

	// Exam room: radios by keyboard, announce button, threshold announcement (A11Y-022/-071).
	await page.clock.install();
	await open( page, dir, 'exam.html' );
	const exam = results[ 'exam.html' ];
	await page.focus( '#ccap-q-1-A' );
	await page.keyboard.press( 'ArrowDown' );
	const radio = await page.evaluate( () => ( { focused: document.activeElement.id, checked: document.querySelector( '#ccap-q-1-B' ).checked } ) );
	if ( radio.focused !== 'ccap-q-1-B' || ! radio.checked ) {
		exam.problems.push( 'arrow keys do not move within the radio group' );
	}
	await page.keyboard.press( 'Space' );
	const visibleButton = await page.isVisible( '#ccap-timer-announce' );
	await page.focus( '#ccap-timer-announce' );
	await page.keyboard.press( 'Enter' );
	await page.clock.runFor( 200 );
	const announced = await page.textContent( '#ccap-exam-live' );
	if ( ! visibleButton || ! /\d+:\d\d/.test( announced || '' ) ) {
		exam.problems.push( 'announce button missing or no announcement: ' + announced );
	}
	const remaining = await page.getAttribute( '#ccap-timer', 'data-remaining' );
	await page.clock.runFor( ( parseInt( remaining, 10 ) - 299 ) * 1000 );
	const threshold = await page.textContent( '#ccap-exam-live' );
	const expectedThreshold = await page.getAttribute( '#ccap-timer', 'data-t300' );
	if ( threshold !== expectedThreshold ) {
		exam.problems.push( 'no 5-minute announcement: ' + threshold );
	}
	exam.timer = { announced, threshold, live: await page.getAttribute( '#ccap-timer', 'aria-live' ) };

	await browser.close();
	const ok = Object.values( results ).every( ( r ) => r.problems.length === 0 ) && errors.length === 0;
	console.log( JSON.stringify( { ok, axe: !! axePath, results, errors }, null, 1 ) );
	process.exit( ok ? 0 : 1 );
} )();
