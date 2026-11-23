<?php
/**
 * R1-18 live E2E: load `cc-fixture-neutral-v1` into the test site and
 * prepare the reference flows (live-flows.sh).
 *
 * Usage: php tools/qa/e2e/live-setup.php <site dir> <command> [args]
 *
 * - `setup` (default, plugin active) loads the fixture, sets the fixed E2E
 *   password of `cc_fx_lea` and `cc_fx_ole` from CCAP_E2E_PASS (R0-05 §2.7:
 *   only E2E accounts get fixed test passwords), switches
 *   `certificates.auto_issue` on and prints the IDs as JSON.
 * - `dispatch` runs the outbox and the notification queue (what WP-Cron
 *   does on a real site).
 * - `users` creates the flow accounts with the E2E password: `cc_e2e_author`
 *   (Course Manager: Flow 1 author and Flow 3 group manager), `cc_e2e_mia`
 *   (learner with a manual grant on C-A: Flow 2 on the phone) and
 *   `cc_e2e_ina` (invited person of Flow 3, no access yet).
 * - `bank <course id> <module ids, comma separated>` creates the question
 *   bank and the exam rule of the reference course as the author through
 *   the domain services: 50 questions (40 single choice, 10 multiple
 *   choice; correct is always "Antwort A …" resp. "Antwort A/B …"), spread
 *   17/17/16 over the module pools, and a published exam rule "20 from 50"
 *   (7/7/6), pass mark 70 %, 30 minutes. There is no question bank or exam
 *   rule editor in the admin yet (R0-11 A-07/A-08, R1-05 A-5); this step
 *   stands in for it and is timed separately.
 * - `quiz <lesson id> <module id> [n]` attaches the first n (5) questions of
 *   the module pool to a quiz lesson as inline questions (no editor or
 *   service for that yet). A quiz with `source: module_pool` draws its
 *   questions from the module pool instead (UI-F5, `Question\QuizPool`).
 * - `uid <post id>` prints the object UID of a course.
 *
 * @package CourseCaptain
 */

use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Tests\Support\Fixtures\NeutralFixture;

if ( PHP_SAPI !== 'cli' || ! isset( $argv[1] ) ) {
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = 'localhost';
require rtrim( $argv[1], '/' ) . '/wp-load.php';
$ccap_command = $argv[2] ?? 'setup';
if ( 'dispatch' === $ccap_command ) {
	\CourseCaptain\Event\OutboxDispatcher::current()->dispatch();
	\CourseCaptain\Event\OutboxDispatcher::current()->dispatch();
	// Mails of the notification queue (live-flows.sh captures wp_mail in a must-use plugin).
	\CourseCaptain\Notification\NotificationService::current()->process_queue();
	exit( 0 );
}
require_once dirname( __DIR__, 3 ) . '/tests/support/Fixtures/NeutralFixture.php';

if ( 'uid' === $ccap_command ) {
	echo ( new \CourseCaptain\Course\ObjectIdentity( \CourseCaptain\Storage\Database::current() ) )->ensure( get_post( (int) ( $argv[3] ?? 0 ) ) );
	exit( 0 );
}

if ( 'users' === $ccap_command ) {
	$ccap_ids = array();
	foreach ( array(
		'author' => array( Roles::COURSE_MANAGER, 'author@example.test' ),
		'mia'    => array( 'subscriber', 'mia@example.test' ),
		'ina'    => array( 'subscriber', 'ina@example.test' ),
	) as $ccap_key => $ccap_def ) {
		$ccap_ids[ $ccap_key ] = (int) wp_insert_user(
			array(
				'user_login'   => 'cc_e2e_' . $ccap_key,
				'user_email'   => $ccap_def[1],
				'user_pass'    => (string) getenv( 'CCAP_E2E_PASS' ),
				'role'         => $ccap_def[0],
				'display_name' => ucfirst( $ccap_key ) . ' E2E',
			)
		);
	}
	PolicyService::flush();
	$ccap_admin = get_user_by( 'login', 'cc_fx_admin' );
	\CourseCaptain\Access\GrantService::current()->grant_manual( Actor::user( (int) $ccap_admin->ID ), $ccap_ids['mia'], (int) NeutralFixture::object( 'course', 'cc-fx-course-a' ), wp_generate_uuid4(), 'E2E mobile learner' );
	// The author starts the editor without the welcome guide (a per-user preference, not product state).
	update_user_meta( $ccap_ids['author'], $GLOBALS['wpdb']->get_blog_prefix() . 'persisted_preferences', array( 'core/edit-post' => array( 'welcomeGuide' => false ), 'core' => array( 'enableChoosePatternModal' => false ), '_modified' => gmdate( 'c' ) ) );
	echo wp_json_encode( $ccap_ids );
	exit( 0 );
}

if ( 'bank' === $ccap_command ) {
	$ccap_course  = (int) ( $argv[3] ?? 0 );
	$ccap_modules = array_map( 'intval', explode( ',', (string) ( $argv[4] ?? '' ) ) );
	$ccap_author  = Actor::user( (int) get_user_by( 'login', 'cc_e2e_author' )->ID );
	wp_set_current_user( $ccap_author->user_id );
	PolicyService::flush();
	$ccap_repo   = QuestionRepository::current();
	$ccap_counts = array( 17, 17, 16 );
	$ccap_n      = 0;
	foreach ( $ccap_modules as $ccap_i => $ccap_module ) {
		for ( $ccap_j = 0; $ccap_j < $ccap_counts[ $ccap_i ]; $ccap_j++ ) {
			++$ccap_n;
			$ccap_key   = sprintf( 'RK-Q-%03d', $ccap_n );
			$ccap_multi = 0 === $ccap_n % 5;
			$ccap_q     = $ccap_repo->create(
				$ccap_author,
				array(
					'question_type' => $ccap_multi ? QuestionTypes::MULTI : QuestionTypes::SINGLE,
					'question_text' => $ccap_key . ': ' . ( $ccap_multi ? 'Welche Aussagen treffen zu?' : 'Welche Aussage trifft zu?' ),
					'answers'       => array_map(
						static fn( string $l ): array => array(
							'key'     => $l,
							'text'    => 'Antwort ' . $l . ' zu ' . $ccap_key,
							'correct' => 'A' === $l || ( $ccap_multi && 'B' === $l ),
						),
						array( 'A', 'B', 'C', 'D' )
					),
				)
			);
			$ccap_repo->publish( $ccap_author, $ccap_q );
			$ccap_repo->add_to_pool( $ccap_author, $ccap_module, $ccap_q );
		}
	}
	$ccap_rules = ExamRules::current();
	$ccap_rule  = $ccap_rules->create(
		$ccap_author,
		$ccap_course,
		'rk-e2e-v1',
		array(
			'min_ratio' => array(
				'num' => 14,
				'den' => 20,
			),
		),
		1800
	);
	foreach ( array( 7, 7, 6 ) as $ccap_i => $ccap_count ) {
		$ccap_rules->set_blueprint( $ccap_author, $ccap_rule, $ccap_modules[ $ccap_i ], $ccap_count );
	}
	$ccap_rules->publish( $ccap_author, $ccap_rule );
	echo wp_json_encode(
		array(
			'questions' => $ccap_n,
			'rule_id'   => $ccap_rule,
			'rule_uid'  => (string) $ccap_rules->get( $ccap_rule )['uid'],
		)
	);
	exit( 0 );
}

if ( 'quiz' === $ccap_command ) {
	// Inline questions of a quiz lesson: the first n questions of a module pool. There is no
	// editor and no service for this yet (only the import writes `ccap_activity_question`).
	$ccap_lesson = (int) ( $argv[3] ?? 0 );
	$ccap_db     = \CourseCaptain\Storage\Database::current();
	foreach ( array_slice( QuestionRepository::current()->pool( (int) ( $argv[4] ?? 0 ) ), 0, (int) ( $argv[5] ?? 5 ) ) as $ccap_i => $ccap_q ) {
		$ccap_db->insert(
			'ccap_activity_question',
			array(
				'activity_id' => $ccap_lesson,
				'question_id' => $ccap_q,
				'sort_order'  => $ccap_i + 1,
			)
		);
	}
	\CourseCaptain\Cache\StructureCache::invalidate();
	echo wp_json_encode( array( 'inline' => count( \CourseCaptain\Question\QuestionPresenter::current()->inline_questions( $ccap_lesson ) ) ) );
	exit( 0 );
}

$ccap_dir = rtrim( $argv[1], '/' ) . '/ccap-fixture';
wp_mkdir_p( $ccap_dir );
$ccap_fx = NeutralFixture::load( $ccap_dir );
wp_set_password( (string) getenv( 'CCAP_E2E_PASS' ), $ccap_fx['users']['lea'] );
// U-L2 drives the exam room cases GM-050/GM-051 (tools/qa/e2e/exam-room.js).
wp_set_password( (string) getenv( 'CCAP_E2E_PASS' ), $ccap_fx['users']['ole'] );
Settings::save( Actor::user( $ccap_fx['users']['admin'] ), 'certificates', array( 'auto_issue' => true ), array( 'auto_issue' ), Settings::rev( 'certificates' ) );
echo wp_json_encode(
	array(
		'course_a_uid' => ( new \CourseCaptain\Course\ObjectIdentity( \CourseCaptain\Storage\Database::current() ) )->ensure( get_post( $ccap_fx['courses']['C-A'] ) ),
		'course_a'     => $ccap_fx['courses']['C-A'],
		'lesson_a_01'  => NeutralFixture::object( 'lesson', 'cc-fx-l-a-01' ),
		'rule_er_a'    => $ccap_fx['rules']['ER-A'],
	)
);
