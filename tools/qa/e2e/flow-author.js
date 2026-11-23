// R1-18 B-4 · R0-11 Flow 1 (Autor erstellt und veröffentlicht einen Kurs) in the WordPress admin,
// driven like a person in Chromium (block editor + the plugin's meta boxes, no REST shortcuts):
// Course Manager `cc_e2e_author` creates 3 modules, the course and adds the modules (two
// saves without reload: the stale-form case), tries to publish too early → validator
// blockers (F1.1), follows the jump link to the module; question bank + exam rule come from
// the domain services (`live-setup.php bank`, no A-07/A-08 editor yet), the quiz's inline
// questions likewise (`live-setup.php quiz`); then 10 lessons
// (text, quiz, assignment E-168, exam) with module, type and settings in the lesson editor,
// including a rejected save with invalid settings; finally the course is published
// (revision 1) after the validator shows 0 blockers.
// Writes the created IDs to $CCAP_E2E_OUT/reference-course.json.
const { chromium } = require( 'playwright' );
const { execSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );
const { BASE, OUT, recorder, assert, login } = require( './lib' );

const CLI = process.env.CCAP_E2E_CLI; // php tools/qa/e2e/live-setup.php <site>
const { step, results } = recorder( 'flow1-author' );

const LESSONS = [
	{ key: 'L01', module: 0, title: 'Warum Zeitplanung hilft', type: 'lesson' },
	{ key: 'L02', module: 0, title: 'Ziele formulieren', type: 'lesson' },
	{ key: 'L03', module: 0, title: 'Übung: Ziele', type: 'quiz', settings: { source: 'inline' } },
	{ key: 'L04', module: 0, title: 'Prioritäten setzen', type: 'lesson' },
	{ key: 'L05', module: 1, title: 'Wochenplan anlegen', type: 'lesson' },
	{ key: 'L06', module: 1, title: 'Pufferzeiten', type: 'lesson' },
	{ key: 'L07', module: 1, title: 'Aufgabe: eigener Wochenplan', type: 'assignment', rule: 'graded', settings: { submission_type: 'text', max_points: 10, passing_points: 6, instructions: 'Beschreibe deinen Wochenplan in einigen Sätzen.' } },
	{ key: 'L08', module: 2, title: 'Störungen begrenzen', type: 'lesson' },
	{ key: 'L09', module: 2, title: 'Rückblick', type: 'lesson' },
	{ key: 'L10', module: 2, title: 'Abschlussprüfung', type: 'exam', rule: 'passed' },
];

async function openMetaBoxes( page ) {
	const toggle = page.getByRole( 'button', { name: 'Meta Boxes' } );
	await toggle.waitFor();
	if ( ( await toggle.getAttribute( 'aria-expanded' ) ) !== 'true' ) {
		// The resize handle overlaps the toggle for the pointer; the keyboard works.
		await toggle.press( 'Enter' );
	}
}

async function newPost( page, type, title, body ) {
	await page.goto( BASE + '/wp-admin/post-new.php?post_type=' + type );
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	const titleBox = canvas.getByRole( 'textbox', { name: 'Add title' } );
	await titleBox.waitFor( { timeout: 30000 } );
	await titleBox.fill( title );
	if ( body ) {
		await titleBox.press( 'Enter' );
		await page.keyboard.type( body );
	}
}

const postId = ( page ) => page.evaluate( () => window.wp.data.select( 'core/editor' ).getCurrentPostId() );
const postStatus = ( page ) => page.evaluate( () => window.wp.data.select( 'core/editor' ).getCurrentPostAttribute( 'status' ) );

// Save the post with the editor's own buttons; waits for the post request and, with
// meta boxes, for their form post (`meta-box-loader=1`).
async function save( page, publish, metaBoxes ) {
	const waits = [ page.waitForResponse( ( r ) => /wp\/v2\/|wp%2Fv2%2F/.test( r.url() ) && [ 'POST', 'PUT' ].includes( r.request().method() ), { timeout: 60000 } ) ];
	if ( metaBoxes ) {
		waits.push( page.waitForResponse( ( r ) => r.url().includes( 'meta-box-loader=1' ), { timeout: 60000 } ) );
	}
	if ( publish ) {
		await page.locator( '.editor-header__settings' ).getByRole( 'button', { name: 'Publish', exact: true } ).click();
		const panel = page.locator( '.editor-post-publish-panel' );
		await panel.getByRole( 'button', { name: 'Publish', exact: true } ).click();
	} else {
		await page.getByRole( 'button', { name: 'Save draft' } ).click();
	}
	const responses = await Promise.all( waits );
	assert( responses.every( ( r ) => r.status() < 400 ), 'save requests ok: ' + responses.map( ( r ) => r.status() ).join( ',' ) );
}

// Error notice of the editor (the plugin's save messages, editor-notices.js).
async function editorError( page, pattern ) {
	const notice = page.locator( '.components-notice.is-error', { hasText: pattern } );
	await notice.first().waitFor( { timeout: 15000 } );
	return notice.first().innerText();
}

async function editPage( page, id ) {
	await page.goto( BASE + '/wp-admin/post.php?post=' + id + '&action=edit' );
	await openMetaBoxes( page );
}

( async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage( { viewport: { width: 1400, height: 1000 } } );
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( String( e.stack || e ) ) );
	const state = { modules: [], lessons: {} };
	try {
		await step( 'author logs in (Course Manager)', async () => {
			await login( page, 'cc_e2e_author' );
		} );

		await step( 'menu offers "Add course", the screen opens', async () => {
			await page.goto( BASE + '/wp-admin/edit.php?post_type=ccap_course' );
			const link = page.locator( '#adminmenu a', { hasText: 'Add course' } );
			assert( ( await link.count() ) === 1, 'menu entry "Add course"' );
			const r = await page.goto( await link.getAttribute( 'href' ).then( ( h ) => new URL( h, BASE + '/wp-admin/' ).href ) );
			assert( r.status() === 200, 'post-new.php for a Course Manager: ' + r.status() );
		} );

		for ( const [ i, title ] of [ 'Grundlagen', 'Planung', 'Alltag' ].entries() ) {
			await step( 'module ' + ( i + 1 ) + ' "' + title + '" created and published', async () => {
				await newPost( page, 'ccap_module', 'Modul ' + ( i + 1 ) + ': ' + title );
				await save( page, true, false );
				await page.waitForFunction( () => window.wp.data.select( 'core/editor' ).getCurrentPostAttribute( 'status' ) === 'publish', null, { timeout: 30000 } );
				state.modules.push( await postId( page ) );
			} );
		}

		await step( 'course draft with description and two modules (two saves, no reload)', async () => {
			await newPost( page, 'ccap_course', 'Referenzkurs Zeitmanagement (E2E)', 'Ein kurzer Kurs zum Planen der eigenen Woche.' );
			await openMetaBoxes( page );
			await page.selectOption( '#ccap-add-module', String( state.modules[ 0 ] ) );
			await save( page, false, true );
			state.course = await postId( page );
			// The block editor keeps the meta box of the first page load (stale form).
			await page.selectOption( '#ccap-add-module', String( state.modules[ 1 ] ) );
			await save( page, false, true );
			await editPage( page, state.course );
			const rows = await page.locator( '#ccap-course-structure tbody tr' ).allInnerTexts();
			assert( rows.length === 2 && rows[ 0 ].includes( 'Grundlagen' ) && rows[ 1 ].includes( 'Planung' ), 'both modules kept: ' + JSON.stringify( rows ) );
			await page.selectOption( '#ccap-add-module', String( state.modules[ 2 ] ) );
			await save( page, false, true );
		} );

		await step( 'publishing too early is refused, validator lists blockers with jump links (F1.1)', async () => {
			await editPage( page, state.course );
			const summary = await page.locator( '.ccap-validation strong' ).innerText();
			assert( /Check: [1-9]\d* blockers/.test( summary ), 'blockers before lessons: ' + summary );
			await page.check( 'input[name=ccap_publish_revision]' );
			await save( page, true, true );
			const message = await editorError( page, /Not (saved|published)/ );
			assert( /VAL-020|blockers/.test( message ), 'author is told why at once: ' + message );
			await editPage( page, state.course );
			assert( ( await postStatus( page ) ) === 'draft', 'course stays a draft' );
			const box = await page.locator( '#ccap-course-publish' ).innerText();
			assert( box.includes( 'Not published yet.' ), 'no revision published: ' + box );
			const items = await page.locator( '.ccap-validation li' ).allInnerTexts();
			assert( items.some( ( t ) => t.includes( 'VAL-020' ) ), 'empty module named: ' + JSON.stringify( items ) );
			const jump = page.locator( '.ccap-validation a.ccap-validation-jump' ).first();
			const href = await jump.getAttribute( 'href' );
			assert( href && href.includes( 'post=' + state.modules[ 0 ] ), 'jump link to module 1: ' + href );
			await Promise.all( [ page.waitForNavigation(), jump.click() ] );
			const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
			await canvas.getByRole( 'textbox', { name: 'Add title' } ).waitFor();
			assert( ( await canvas.getByRole( 'textbox', { name: 'Add title' } ).innerText() ).includes( 'Grundlagen' ), 'module editor opened' );
		} );

		await step( 'question bank (50) and exam rule 20 of 50 via domain services (no A-07/A-08 editor)', async () => {
			const out = execSync( CLI + ' bank ' + state.course + ' ' + state.modules.join( ',' ) ).toString();
			state.bank = JSON.parse( out.trim().split( '\n' ).pop() );
			assert( state.bank.questions === 50 && /^[0-9a-f-]{36}$/.test( state.bank.rule_uid ), 'bank: ' + out );
		} );

		await step( 'invalid activity settings are rejected with a notice', async () => {
			await newPost( page, 'ccap_lesson', 'Abschlussprüfung', 'Die Prüfung schließt den Kurs ab.' );
			await openMetaBoxes( page );
			await page.selectOption( '#ccap-lesson-module', String( state.modules[ 2 ] ) );
			await page.selectOption( '#ccap-activity-type', 'exam' );
			await page.fill( '#ccap-activity-settings', '{}' );
			await save( page, false, true );
			state.examDraft = await postId( page );
			const message = await editorError( page, 'Not saved' );
			assert( message.includes( 'rule_uid' ), 'error names the missing exam rule: ' + message );
		} );

		for ( const lesson of LESSONS ) {
			await step( 'lesson ' + lesson.key + ' (' + lesson.type + ') created in module ' + ( lesson.module + 1 ), async () => {
				if ( lesson.type === 'exam' ) {
					await editPage( page, state.examDraft );
				} else {
					await newPost( page, 'ccap_lesson', lesson.title, 'Lektionstext zu „' + lesson.title + '“. Plane deine Woche bewusst.' );
					await openMetaBoxes( page );
				}
				await page.selectOption( '#ccap-lesson-module', String( state.modules[ lesson.module ] ) );
				await page.selectOption( '#ccap-activity-type', lesson.type );
				const settings = lesson.type === 'exam' ? { rule_uid: state.bank.rule_uid } : ( lesson.settings || {} );
				await page.fill( '#ccap-activity-settings', JSON.stringify( settings ) );
				await page.fill( '#ccap-activity-rule', lesson.rule || '' );
				await save( page, true, true );
				state.lessons[ lesson.key ] = await postId( page );
				await editPage( page, state.lessons[ lesson.key ] );
				assert( ( await page.locator( '.components-notice.is-error' ).count() ) === 0, 'saved without error notice' );
				assert( ( await page.inputValue( '#ccap-lesson-module' ) ) === String( state.modules[ lesson.module ] ), 'module kept' );
				assert( ( await page.inputValue( '#ccap-activity-type' ) ) === lesson.type, 'type kept' );
				if ( lesson.type === 'quiz' ) {
					// Inline questions: no editor yet (A-07), attached through live-setup.php.
					const out = execSync( CLI + ' quiz ' + state.lessons[ lesson.key ] + ' ' + state.modules[ lesson.module ] + ' 5' ).toString();
					assert( JSON.parse( out.trim().split( '\n' ).pop() ).inline === 5, 'quiz with 5 questions: ' + out );
				}
			} );
		}

		await step( 'course published as revision 1 after the check shows 0 blockers', async () => {
			await editPage( page, state.course );
			const rows = await page.locator( '#ccap-course-structure tbody tr' ).allInnerTexts();
			assert( rows.length === 3 && rows[ 0 ].includes( '4 lessons' ) && rows[ 1 ].includes( '3 lessons' ) && rows[ 2 ].includes( '3 lessons' ), 'structure 4/3/3: ' + JSON.stringify( rows ) );
			const summary = await page.locator( '.ccap-validation strong' ).innerText();
			assert( /Check: 0 blockers/.test( summary ), 'no blockers: ' + summary + ' ' + JSON.stringify( await page.locator( '.ccap-validation li' ).allInnerTexts() ) );
			for ( const box of await page.locator( 'input[name="ccap_ack[]"]' ).all() ) {
				await box.check();
			}
			await page.check( 'input[name=ccap_publish_revision]' );
			await save( page, true, true );
			await editPage( page, state.course );
			assert( ( await postStatus( page ) ) === 'publish', 'course published' );
			assert( ( await page.locator( '#ccap-course-publish' ).innerText() ).includes( 'Published revision: 1' ), 'revision 1' );
			state.course_uid = execSync( CLI + ' uid ' + state.course ).toString().trim().split( '\n' ).pop();
		} );

		const ours = errors.filter( ( e ) => /course-captain/.test( e ) );
		assert( ours.length === 0, 'no script errors of the plugin: ' + ours.join( ' | ' ) );
		state.editor_errors = errors.length;
		if ( OUT ) {
			fs.writeFileSync( path.join( OUT, 'reference-course.json' ), JSON.stringify( state, null, 1 ) );
		}
	} catch ( e ) {
		process.exitCode = 1;
		if ( OUT ) {
			await page.screenshot( { path: path.join( OUT, 'flow1-failure.png' ) } ).catch( () => {} );
		}
	} finally {
		console.log( results.join( '\n' ) );
		if ( errors.length ) {
			console.log( 'editor page errors (core, not failing): ' + [ ...new Set( errors.map( ( e ) => e.split( '\n' )[ 0 ] ) ) ].join( ' | ' ) );
		}
		await browser.close();
	}
} )();
