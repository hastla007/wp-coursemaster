<?php
/**
 * R1-18 live E2E: load `cc-fixture-neutral-v1` into the test site.
 *
 * Usage: php tools/qa/e2e/live-setup.php <site dir> [setup|dispatch]
 * `setup` (default, plugin active) loads the fixture, sets the fixed E2E
 * password of `cc_fx_lea` from CCAP_E2E_PASS (R0-05 §2.7: only E2E
 * accounts get fixed test passwords), switches `certificates.auto_issue`
 * on and prints the IDs as JSON. `dispatch` runs the outbox once (what
 * WP-Cron does on a real site).
 *
 * @package CourseCaptain
 */

use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Tests\Support\Fixtures\NeutralFixture;

if ( PHP_SAPI !== 'cli' || ! isset( $argv[1] ) ) {
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = 'localhost';
require rtrim( $argv[1], '/' ) . '/wp-load.php';
if ( 'dispatch' === ( $argv[2] ?? 'setup' ) ) {
	\CourseCaptain\Event\OutboxDispatcher::current()->dispatch();
	\CourseCaptain\Event\OutboxDispatcher::current()->dispatch();
	exit( 0 );
}
require_once dirname( __DIR__, 3 ) . '/tests/support/Fixtures/NeutralFixture.php';

$ccap_dir = rtrim( $argv[1], '/' ) . '/ccap-fixture';
wp_mkdir_p( $ccap_dir );
$ccap_fx = NeutralFixture::load( $ccap_dir );
wp_set_password( (string) getenv( 'CCAP_E2E_PASS' ), $ccap_fx['users']['lea'] );
Settings::save( Actor::user( $ccap_fx['users']['admin'] ), 'certificates', array( 'auto_issue' => true ), array( 'auto_issue' ), Settings::rev( 'certificates' ) );
echo wp_json_encode(
	array(
		'course_a_uid' => ( new \CourseCaptain\Course\ObjectIdentity( \CourseCaptain\Storage\Database::current() ) )->ensure( get_post( $ccap_fx['courses']['C-A'] ) ),
		'course_a'     => $ccap_fx['courses']['C-A'],
		'lesson_a_01'  => NeutralFixture::object( 'lesson', 'cc-fx-l-a-01' ),
		'rule_er_a'    => $ccap_fx['rules']['ER-A'],
	)
);
