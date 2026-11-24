// External media "click to load" in the browser (E-45a, AG-07 J-14; R0-10 §5.2.2/§5.3,
// R0-31 MC-1a/MC-3/MC-4/MC-5, MED-021/-022/-023/-024, PSD-022/-023).
//
// Usage: node tools/qa/e2e/external-media.js <dir>
//   <dir> holds external-media-youtube.html, external-media-file.html and
//   external-media-noremember.html written by
//   CCAP_DUMP_DIR=<dir> vendor/bin/phpunit -c phpunit-integration.xml.dist --filter ExternalMediaViewTest
// The pages are served from a local origin together with assets/js/external-media.js;
// every request to another host is recorded and answered locally (no real network).
const { launch } = require( './lib' );
const path = require( 'path' );
const fs = require( 'fs' );

const ORIGIN = 'http://learn.ccap.test';
const SCRIPT = path.resolve( __dirname, '../../../assets/js/external-media.js' );
const dir = process.argv[ 2 ];
const failures = [];

function check( ok, message ) {
	if ( ! ok ) {
		failures.push( message );
	}
	console.log( ( ok ? 'ok   ' : 'FAIL ' ) + message );
}

async function open( context, name, before ) {
	const page = await context.newPage();
	const foreign = [];
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
	await page.route( '**/*', ( route ) => {
		const url = route.request().url();
		if ( url.startsWith( ORIGIN + '/' ) ) {
			const file = url.slice( ORIGIN.length + 1 ).split( '?' )[ 0 ];
			if ( 'external-media.js' === file ) {
				return route.fulfill( { contentType: 'text/javascript', body: fs.readFileSync( SCRIPT, 'utf8' ) } );
			}
			// The site's own script tag (if printed by wp_footer) is replaced by the local copy.
			const html = fs.readFileSync( path.join( dir, path.basename( file ) ), 'utf8' )
				.replace( /<script[^>]*external-media\.js[^>]*><\/script>/g, '' )
				.replace( '</body>', '<script src="' + ORIGIN + '/external-media.js"></script></body>' );
			return route.fulfill( { contentType: 'text/html', body: before ? html.replace( '<head>', '<head><script>' + before + '</script>' ) : html } );
		}
		foreign.push( url );
		return route.fulfill( { contentType: 'text/html', body: '' } );
	} );
	await page.goto( ORIGIN + '/' + name );
	return { page, foreign, errors };
}

( async () => {
	const browser = await launch();
	const context = await browser.newContext();

	// MED-021/MED-022, PSD-022 (b): nothing before the click, only this medium after it, focus on the player.
	let { page, foreign, errors } = await open( context, 'external-media-youtube.html' );
	check( 0 === foreign.length, 'youtube: no foreign request before the click (' + foreign.join( ', ' ) + ')' );
	check( await page.isVisible( 'button[data-ccap-load]' ), 'youtube: "Load video" button visible with JavaScript' );
	check( ! ( await page.isChecked( '#ccap-media-remember' ) ), 'youtube: "always load" box not ticked (MC-3)' );
	await page.focus( 'button[data-ccap-load]' );
	await page.keyboard.press( 'Enter' );
	const src = await page.getAttribute( '.ccap-media-frame iframe', 'src' );
	check( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ' === src, 'youtube: iframe youtube-nocookie.com after the click (keyboard)' );
	check( await page.evaluate( () => 'IFRAME' === document.activeElement.tagName ), 'youtube: focus moved to the player' );
	check( ( await page.textContent( '[data-ccap-media-status]' ) ).includes( 'has been loaded' ), 'youtube: status message announced' );
	check( foreign.every( ( u ) => u.startsWith( 'https://www.youtube-nocookie.com/' ) ), 'youtube: only youtube-nocookie.com contacted (' + foreign.join( ', ' ) + ')' );
	check( null === await page.evaluate( () => window.localStorage.getItem( 'ccap_external_media' ) ), 'youtube: without the box nothing is stored (PSD-023)' );
	check( 0 === errors.length, 'youtube: no JavaScript errors ' + errors.join( '; ' ) );
	await page.close();

	// PSD-023: next page without the box shows the placeholder again.
	( { page, foreign } = await open( context, 'external-media-youtube.html' ) );
	check( 0 === foreign.length && await page.isVisible( 'button[data-ccap-load]' ), 'youtube: next page again placeholder' );
	// MED-023: box ticked → stored per service → next page loads directly; revocation works.
	await page.check( '#ccap-media-remember' );
	await page.click( 'button[data-ccap-load]' );
	check( '{"youtube":true}' === await page.evaluate( () => window.localStorage.getItem( 'ccap_external_media' ) ), 'remember: choice stored per service without personal data' );
	await page.close();
	( { page, foreign } = await open( context, 'external-media-youtube.html' ) );
	check( 1 === await page.locator( '.ccap-media-frame iframe' ).count(), 'remember: next page loads directly' );
	check( await page.isVisible( 'button[data-ccap-forget]' ), 'remember: revocation button shown (MC-4)' );
	await page.click( 'button[data-ccap-forget]' );
	check( null === await page.evaluate( () => window.localStorage.getItem( 'ccap_external_media' ) ), 'remember: revocation clears the choice' );
	await page.close();
	( { page, foreign } = await open( context, 'external-media-youtube.html' ) );
	check( 0 === foreign.length && 0 === await page.locator( 'iframe' ).count(), 'remember: after revocation placeholder again' );
	await page.close();

	// SET-114: `external_media_remember` off → no box, a stored choice is ignored.
	( { page, foreign } = await open( context, 'external-media-noremember.html', 'localStorage.setItem("ccap_external_media","{\\"vimeo\\":true}")' ) );
	check( 0 === foreign.length && 0 === await page.locator( 'iframe' ).count(), 'remember off: stored choice ignored (' + foreign.join( ', ' ) + ')' );
	check( 0 === await page.locator( '#ccap-media-remember' ).count(), 'remember off: no box' );
	await page.click( 'button[data-ccap-load]' );
	check( 'https://player.vimeo.com/video/76979871?dnt=1' === await page.getAttribute( 'iframe', 'src' ), 'vimeo: player with dnt=1 after the click' );
	await page.close();
	await context.clearCookies();

	// MED-022: video file; the subtitle track only together with the video.
	( { page, foreign } = await open( context, 'external-media-file.html' ) );
	check( 0 === await page.locator( 'video, track' ).count() && 0 === foreign.length, 'file: no video/track before the click' );
	await page.click( 'button[data-ccap-load]' );
	check( 'https://videos.example.test/clip.mp4' === await page.getAttribute( '.ccap-media-frame video', 'src' ), 'file: native player after the click' );
	check( 'https://videos.example.test/clip.vtt' === await page.getAttribute( '.ccap-media-frame video track', 'src' ), 'file: subtitles attached with the video' );
	await page.close();

	// MED-024 / MC-5: WP Consent API (category) and registered adapters load without a click.
	( { page, foreign } = await open( context, 'external-media-file.html', 'window.wp_has_consent=function(c){return "marketing"===c;};' ) );
	check( 1 === await page.locator( '.ccap-media-frame video' ).count(), 'consent API: granted category loads directly' );
	await page.close();
	( { page, foreign } = await open( context, 'external-media-file.html', 'window.wp_has_consent=function(){return false;};' ) );
	check( 0 === await page.locator( 'video' ).count() && 0 === foreign.length, 'consent API: refused keeps the placeholder' );
	await page.evaluate( () => window.courseCaptain.consent.register( ( service ) => 'videos.example.test' === service ) );
	check( 1 === await page.locator( '.ccap-media-frame video' ).count(), 'adapter: courseCaptain.consent.register loads' );
	await page.close();

	// PSD-027: without JavaScript only the link.
	const nojs = await browser.newContext( { javaScriptEnabled: false } );
	( { page, foreign } = await open( nojs, 'external-media-youtube.html' ) );
	check( ! ( await page.isVisible( 'button[data-ccap-load]' ) ) && await page.isVisible( '.ccap-media-link a' ) && 0 === foreign.length, 'no JavaScript: link only, no foreign request' );
	await browser.close();

	if ( failures.length ) {
		console.error( failures.length + ' check(s) failed' );
		process.exit( 1 );
	}
	console.log( 'all checks passed' );
} )();
