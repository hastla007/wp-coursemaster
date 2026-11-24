// R1-16b / GM-143: rendered learning-area pages at 390 px (no overflow, skip link, focus, no JS errors).
// Usage: node tools/qa/e2e/learning-area-mobile.js <dir with room.html and home.html>
const { launch } = require( './lib' );
const path = require( 'path' );

( async () => {
	const dir = process.argv[ 2 ];
	const browser = await launch();
	const page = await browser.newPage( { viewport: { width: 390, height: 844 } } );
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
	// External WordPress scripts are not loaded in the static check.
	await page.route( /^https?:/, ( route ) => route.abort() );
	const results = {};
	for ( const name of [ 'home', 'room' ] ) {
		await page.goto( 'file://' + path.resolve( dir, name + '.html' ) );
		const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		await page.keyboard.press( 'Tab' );
		const skip = await page.evaluate( () => {
			const el = document.activeElement;
			const r = el.getBoundingClientRect();
			return { cls: el.className, visible: r.left >= 0 && r.width > 0, outline: getComputedStyle( el ).outlineStyle };
		} );
		const targets = await page.evaluate( () => [ ...document.querySelectorAll( '.ccap-button' ) ].map( ( b ) => b.getBoundingClientRect().height ) );
		results[ name ] = { overflow, skip, minTarget: targets.length ? Math.min( ...targets ) : null };
	}
	await browser.close();
	const ok = Object.values( results ).every( ( r ) => r.overflow <= 0 && r.skip.cls === 'ccap-skip' && r.skip.visible && ( r.minTarget === null || r.minTarget >= 44 ) ) && errors.length === 0;
	console.log( JSON.stringify( { ok, results, errors } ) );
	process.exit( ok ? 0 : 1 );
} )();
