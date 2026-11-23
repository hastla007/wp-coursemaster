// R1-18 B-4 practice benchmark (Konzept 27.4, R0-26 UX-090 automated part, GM-187 browser side):
// reference course 10 lessons / 50 questions / 1 exam / 1 assignment, built in flow-author.js
// and learned in flow-group.js. This script samples the core learner requests on that course
// (CCAP_E2E_SAMPLES each, default 20, sequential, warm) and writes a report of all timings:
// $CCAP_E2E_OUT/benchmark.md and benchmark.json.
// The numbers come from `php -S` on a development container, not from a production stack;
// they are a baseline for comparison between runs, not a check of the R0-14 limits.
const { chromium } = require( 'playwright' );
const fs = require( 'fs' );
const path = require( 'path' );
const { OUT, area, login, api, percentile } = require( './lib' );

const COURSE = JSON.parse( fs.readFileSync( path.join( OUT, 'reference-course.json' ), 'utf8' ) );
const SAMPLES = parseInt( process.env.CCAP_E2E_SAMPLES || '20', 10 );

( async () => {
	const browser = await chromium.launch();
	const page = await ( await browser.newContext() ).newPage();
	const samples = {};
	const add = ( k, ms ) => ( samples[ k ] = samples[ k ] || [] ).push( ms );
	try {
		const n = await login( page, 'cc_e2e_ina' );
		const uid = COURSE.course_uid;
		const pages = {
			'page: My learning': area( '' ),
			'page: course room': area( 'kurs/' + uid ),
			'page: lesson': area( 'kurs/' + uid + '/lektion/' + COURSE.lessons.L02 ),
		};
		for ( let i = 0; i < SAMPLES; i++ ) {
			for ( const [ k, url ] of Object.entries( pages ) ) {
				await page.goto( url );
				add( k, await page.evaluate( () => {
					const t = performance.getEntriesByType( 'navigation' )[ 0 ];
					return t.responseEnd - t.requestStart;
				} ) );
			}
			add( 'api: GET course', ( await api( page, n, 'GET', '/courses/' + uid ) ).ms );
			add( 'api: GET next-question', ( await api( page, n, 'GET', '/courses/' + uid + '/next-question' ) ).ms );
			add( 'api: GET me/courses', ( await api( page, n, 'GET', '/me/courses' ) ).ms );
		}
	} finally {
		await browser.close();
	}
	const lines = fs.existsSync( path.join( OUT, 'timings.jsonl' ) ) ? fs.readFileSync( path.join( OUT, 'timings.jsonl' ), 'utf8' ).trim().split( '\n' ).filter( Boolean ).map( ( l ) => JSON.parse( l ) ) : [];
	const author = lines.filter( ( l ) => 'flow1-author' === l.flow );
	const sum = ( list ) => list.reduce( ( a, l ) => a + l.ms, 0 );
	const lessons = author.filter( ( l ) => /^lesson L\d\d/.test( l.step ) );
	const stats = ( list ) => ( { n: list.length, p50: Math.round( percentile( list, 50 ) ), p95: Math.round( percentile( list, 95 ) ), max: Math.round( Math.max( ...list ) ) } );
	const learner = {};
	for ( const l of lines.filter( ( x ) => 'learner-reference' === x.flow ) ) {
		( learner[ l.step ] = learner[ l.step ] || [] ).push( l.ms );
	}
	const result = {
		course: { lessons: Object.keys( COURSE.lessons ).length, questions: COURSE.bank.questions, exam: '20 of 50', assignment: 1 },
		author: {
			total_ms: sum( author ),
			editor_ms: sum( author.filter( ( l ) => ! /domain services|logs in/.test( l.step ) ) ),
			bank_ms: sum( author.filter( ( l ) => /domain services/.test( l.step ) ) ),
			per_lesson: stats( lessons.map( ( l ) => l.ms ) ),
			steps: author,
		},
		flows: Object.fromEntries( [ ...new Set( lines.map( ( l ) => l.flow ) ) ].map( ( f ) => [ f, sum( lines.filter( ( l ) => l.flow === f ) ) ] ) ),
		learner: Object.fromEntries( Object.entries( learner ).map( ( [ k, v ] ) => [ k, stats( v ) ] ) ),
		samples: Object.fromEntries( Object.entries( samples ).map( ( [ k, v ] ) => [ k, stats( v ) ] ) ),
	};
	const md = [
		'# Praxis-Benchmark (automatisiert) – Referenzkurs ' + result.course.lessons + ' Lektionen / ' + result.course.questions + ' Fragen / 1 Prüfung (' + result.course.exam + ') / 1 Aufgabe',
		'',
		'Flow 1 (Autor, Browser): gesamt ' + ( result.author.total_ms / 1000 ).toFixed( 1 ) + ' s, davon Editor ' + ( result.author.editor_ms / 1000 ).toFixed( 1 ) + ' s, Fragenbank + Prüfungsregel über Dienste ' + ( result.author.bank_ms / 1000 ).toFixed( 1 ) + ' s; je Lektion p50 ' + result.author.per_lesson.p50 + ' ms, p95 ' + result.author.per_lesson.p95 + ' ms.',
		'',
		'| Flow | Dauer (s) |', '|---|---:|',
		...Object.entries( result.flows ).map( ( [ f, ms ] ) => '| ' + f + ' | ' + ( ms / 1000 ).toFixed( 1 ) + ' |' ),
		'',
		'| Messpunkt | n | p50 (ms) | p95 (ms) | max (ms) |', '|---|---:|---:|---:|---:|',
		...Object.entries( Object.assign( {}, result.learner, result.samples ) ).map( ( [ k, s ] ) => '| ' + k + ' | ' + s.n + ' | ' + s.p50 + ' | ' + s.p95 + ' | ' + s.max + ' |' ),
		'',
	].join( '\n' );
	fs.writeFileSync( path.join( OUT, 'benchmark.json' ), JSON.stringify( result, null, 1 ) );
	fs.writeFileSync( path.join( OUT, 'benchmark.md' ), md );
	console.log( md );
} )();
