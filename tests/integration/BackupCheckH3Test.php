<?php
/**
 * R1-18 follow-up (group h3): complete read-only `backup-check` (R0-16
 * §1.4), script block of the media folder and "Check again" for the
 * storage self test (R0-10 §4.4, R1-38 pattern), exam buffer emptied on
 * logout (PRIV-018).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Activity\MediaLibrary;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\SiteIdentity;
use CourseCaptain\Learner\ExamBufferPurge;
use CourseCaptain\Ops\OpsCommand;
use CourseCaptain\Ops\OpsScreen;
use CourseCaptain\Ops\RepairService;
use CourseCaptain\Ops\RestoreCheck;
use CourseCaptain\Ops\StorageSelfTest;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Migrator;
use CourseCaptain\Storage\Schema;
use CourseCaptain\Tests\Support\Fixtures\NeutralFixture;

/**
 * Each test loads the fixture itself (idempotent, D-03).
 *
 * @group R1-18
 */
final class BackupCheckH3Test extends SchemaTestCase {

	/**
	 * Loaded fixture.
	 *
	 * @var array{users: array<string, int>, courses: array<string, int>, modules: array<string, int>, rules: array<string, int>, variants: array<string, int>, media: array<string, int>, pages: array<string, int>, groups: array<string, int>}
	 */
	private array $fx;

	/**
	 * Own storage base (snapshot).
	 *
	 * @var string
	 */
	private string $base = '';

	/**
	 * Scratch directories and files removed after the test.
	 *
	 * @var list<string>
	 */
	private array $dirs = array();

	/**
	 * Filters added by the test: hook => callbacks.
	 *
	 * @var list<array{0: string, 1: callable, 2: int}>
	 */
	private array $filters = array();

	/**
	 * Load the fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		// Own base inside the uploads folder: isolated from other runs, still below the web root.
		$uploads      = wp_upload_dir( null, false );
		$own          = untrailingslashit( (string) $uploads['basedir'] ) . '/ccap-h3-' . strtolower( wp_generate_password( 8, false, false ) );
		$this->dirs[] = $own;
		wp_mkdir_p( $own );
		add_filter( 'course_captain_private_dir', static fn(): string => $own );
		$dir          = get_temp_dir() . 'ccap-h3-fx-' . strtolower( wp_generate_password( 8, false, false ) );
		$this->dirs[] = $dir;
		wp_mkdir_p( $dir );
		$this->fx = NeutralFixture::load( $dir );
		ProtectedStorage::dir( 'protected' );
		ProtectedStorage::dir( 'packages' );
		$this->base = ProtectedStorage::base();
		delete_transient( StorageSelfTest::TRANSIENT );
		PolicyService::flush();
	}

	/**
	 * Remove filters, cookies and scratch folders.
	 */
	public function tear_down(): void {
		foreach ( $this->filters as $filter ) {
			remove_filter( $filter[0], $filter[1], $filter[2] );
		}
		remove_all_filters( 'course_captain_private_dir' );
		delete_transient( StorageSelfTest::TRANSIENT );
		unset( $_COOKIE[ ExamBufferPurge::COOKIE ] );
		foreach ( $this->dirs as $dir ) {
			is_dir( $dir ) ? self::remove_dir( $dir ) : ( is_file( $dir ) ? unlink( $dir ) : null ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		$GLOBALS['ccap_test_http_requests'] = array();
		parent::tear_down();
	}

	/**
	 * Every area of R0-16 §1.4 reaches each of its specified results
	 * (ok/warn/fail), and every run leaves files, options and tables
	 * exactly as they were (strictly read only, g3 H-3).
	 */
	public function test_backup_check_every_area_ok_warn_fail_read_only(): void {
		$db       = Database::current();
		$kept     = $this->protected_attachment( 'kept' );
		$baseline = $this->run_check();
		self::assertSame( 'ok', $baseline['status'], (string) wp_json_encode( $baseline ) );
		self::assertSame( array( 'schema', 'tables', 'foreign_keys', 'options', 'storage', 'package', 'files', 'grants', 'jobs', 'identity' ), array_column( $baseline['checks'], 'id' ) );
		foreach ( $baseline['checks'] as $check ) {
			self::assertSame( 'ok', $check['status'], $check['id'] );
		}
		self::assertSame( 0, OpsCommand::execute( 'backup-check', array(), array(), Actor::cli() )['exit'] );

		// Schema: newer than the code / missing → fail; migration due / backfill open → warn.
		$version = (string) get_option( Migrator::DB_VERSION );
		update_option( Migrator::DB_VERSION, '99.0.0+0' );
		self::assertSame( 'fail', $this->area( 'schema' )['status'] );
		delete_option( Migrator::DB_VERSION );
		self::assertSame( 'fail', $this->area( 'schema' )['status'] );
		update_option( Migrator::DB_VERSION, $version );
		$fingerprint = get_option( Migrator::FINGERPRINT );
		update_option( Migrator::FINGERPRINT, 'outdated' );
		$due = $this->area( 'schema' );
		self::assertSame( 'warn', $due['status'] );
		self::assertStringContainsString( 'pending', $due['message'] );
		update_option( Migrator::FINGERPRINT, $fingerprint );
		update_option( Migrator::BACKFILL_OPEN, array( 'core:9999_probe' ) );
		self::assertSame( array( 'core:9999_probe' ), $this->area( 'schema', 'warn' )['items'] );
		delete_option( Migrator::BACKFILL_OPEN );
		self::assertSame( 'ok', $this->area( 'schema' )['status'] );

		// Tables: missing or not InnoDB → fail (information_schema answer rewritten, no DDL).
		$this->add_filter(
			'query',
			static function ( string $sql ) use ( $db ): string {
				if ( str_contains( $sql, 'information_schema.TABLES' ) ) {
					$sql = str_replace( 'ENGINE AS engine', "IF(TABLE_NAME = '" . $db->table( 'ccap_course_module' ) . "', 'MyISAM', ENGINE) AS engine", $sql ) . " AND TABLE_NAME <> '" . $db->table( 'ccap_audit_log' ) . "'";
				}
				return $sql;
			}
		);
		$tables = $this->area( 'tables', 'fail' );
		self::assertContains( 'ccap_audit_log (missing)', $tables['items'] );
		self::assertContains( 'ccap_course_module (MyISAM)', $tables['items'] );
		$this->drop_filters();
		self::assertSame( 'ok', $this->area( 'tables' )['status'] );

		// Foreign keys: fewer than expected → warn (never fail).
		update_option( Migrator::FK_ISSUES, array( 'ccap_import_issue.batch_id' => 'errno 150' ) );
		self::assertSame( array( 'ccap_import_issue.batch_id' ), $this->area( 'foreign_keys', 'warn' )['items'] );
		delete_option( Migrator::FK_ISSUES );
		$this->add_filter(
			'query',
			static fn( string $sql ): string => str_contains( $sql, 'information_schema.KEY_COLUMN_USAGE' ) ? $sql . ' AND 1 = 0' : $sql
		);
		$fks = $this->area( 'foreign_keys', 'warn' );
		self::assertCount( min( RestoreCheck::MAX_ITEMS, count( Schema::foreign_keys() ) ), $fks['items'] );
		self::assertStringContainsString( '0 of ' . count( Schema::foreign_keys() ), $fks['message'] );
		$this->drop_filters();

		// Options: no UUID recorded (installation before E-392) → ok with a note (R1-18 h3 F-1); unreadable UUID or missing secret → fail.
		delete_option( RestoreCheck::INSTALLATION_UUID );
		self::assertSame( array( 'No installation UUID is recorded (not created by this version).' ), $this->area( 'options', 'ok' )['items'] );
		update_option( RestoreCheck::INSTALLATION_UUID, 'not-a-uuid', false );
		self::assertSame( array( 'The installation UUID is not readable.' ), $this->area( 'options', 'fail' )['items'] );
		update_option( RestoreCheck::INSTALLATION_UUID, wp_generate_uuid4(), false );
		self::assertSame( array(), $this->area( 'options', 'ok' )['items'] );
		delete_option( RestoreCheck::INSTALLATION_UUID );
		$secret = (string) get_option( ProtectedStorage::SECRET_OPTION );
		delete_option( ProtectedStorage::SECRET_OPTION );
		self::assertSame( 'fail', RestoreCheck::run()['status'], 'missing folder secret' );
		self::assertSame( array( 'The folder secret is missing.' ), array_column( RestoreCheck::run()['checks'], null, 'id' )['options']['items'] );
		self::assertFalse( get_option( ProtectedStorage::SECRET_OPTION ), 'secret not recreated' );
		update_option( ProtectedStorage::SECRET_OPTION, $secret );

		// Storage: configured private folder missing → fail; foreign folder → warn.
		$blocker      = get_temp_dir() . 'ccap-h3-file-' . strtolower( wp_generate_password( 8, false, false ) );
		$this->dirs[] = $blocker;
		file_put_contents( $blocker, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->add_filter( 'course_captain_private_dir', static fn(): string => $blocker . '/private' );
		self::assertSame( 'fail', $this->area( 'storage' )['status'] );
		self::assertFalse( is_dir( $blocker . '/private' ), 'not created' );
		$this->drop_filters();
		$foreign      = ProtectedStorage::base() . '/packages-zzzzzzzzzzzzzzzzzzzzh3h3';
		$this->dirs[] = $foreign;
		wp_mkdir_p( $foreign );
		self::assertStringContainsString( 'packages-…h3h3', implode( ' ', $this->area( 'storage', 'warn' )['items'] ) );
		self::remove_dir( $foreign );

		// Active package: copy missing → warn; no published batch for imported content → fail.
		$batch = $db->row( 'SELECT id, storage_path FROM `' . $db->table( 'ccap_import_batch' ) . "` WHERE status = 'published' ORDER BY id DESC LIMIT 1" );
		self::assertNotNull( $batch, 'fixture package is published' );
		$copy = (string) $batch['storage_path'];
		rename( $copy, $copy . '.away' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		self::assertSame( array( 'import:' . (int) $batch['id'] ), $this->area( 'package', 'warn' )['items'] );
		rename( $copy . '.away', $copy ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_import_batch' ) . "` SET status = 'superseded' WHERE id = %d", (int) $batch['id'] ) );
		self::assertNotSame( array(), $this->area( 'package', 'fail' )['items'] );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_import_batch' ) . "` SET status = 'published' WHERE id = %d", (int) $batch['id'] ) );

		// Media files: changed bytes → warn (sample and --full); unreferenced → warn; missing → fail.
		$path     = (string) get_attached_file( $kept['attachment'], true );
		$original = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		file_put_contents( $path, $original . 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		self::assertSame( array( $kept['uid'] . ': size or SHA-256 differs' ), $this->area( 'files', 'warn' )['items'] );
		$full = OpsCommand::execute( 'backup-check', array(), array( 'full' => true ), Actor::cli() );
		self::assertSame( 2, $full['exit'], 'warn → exit 2' );
		self::assertStringContainsString( 'size or SHA-256 differs', implode( "\n", $full['lines'] ) );
		file_put_contents( $path, $original ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$stray        = ProtectedStorage::dir( 'protected' ) . '/assignments/' . wp_generate_uuid4();
		$this->dirs[] = $stray;
		wp_mkdir_p( $stray );
		file_put_contents( $stray . '/' . str_repeat( 'a', 32 ), 'left over' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$unreferenced = $this->area( 'files', 'warn' );
		self::assertSame( array( 'Unreferenced: assignments/' . basename( $stray ) . '/' . str_repeat( 'a', 32 ) ), $unreferenced['items'] );
		self::assertStringNotContainsString( ProtectedStorage::secret(), (string) wp_json_encode( $unreferenced ) );
		self::remove_dir( $stray );
		rename( $path, $path . '.away' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		self::assertSame( array( $kept['uid'] ), $this->area( 'files', 'fail' )['items'] );
		rename( $path . '.away', $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename

		// Grants: without user account / access check says no → warn (never fail).
		$orphan = $this->grant( 987654321, $this->fx['courses']['C-A'] );
		$denied = $this->grant( $this->fx['users']['newcomer'], $this->fx['courses']['C-A'], $this->fx['courses']['C-B'] );
		$grants = $this->area( 'grants', 'warn' );
		self::assertContains( 'grant:' . $orphan . ' without user account', $grants['items'] );
		self::assertContains( 'grant:' . $denied . ' access check says no (no_access)', $grants['items'] );
		$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_grant' ) . '` WHERE uid IN (%s, %s)', $orphan, $denied ) );

		// Running jobs: import without heartbeat, overdue exam, expired lease → warn.
		$delivery = $this->dispatching_delivery();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_import_batch' ) . "` SET status = 'activating', heartbeat_at = %s WHERE id = %d", $db->now( -3600 ), (int) $batch['id'] ) );
		$jobs = $this->area( 'jobs', 'warn' );
		self::assertContains( 'delivery:' . $delivery, $jobs['items'] );
		self::assertContains( 'import:' . (int) $batch['id'], $jobs['items'] );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_import_batch' ) . "` SET status = 'published' WHERE id = %d", (int) $batch['id'] ) );
		$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_outbox_delivery' ) . '` WHERE id = %d', $delivery ) );
		$session = ExamSessions::current()->start( Actor::user( $this->fx['users']['lea'] ), $this->fx['rules']['ER-A'], null, wp_generate_uuid4(), new Randomizer( '20300115' ) );
		$uid     = (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		self::assertSame( 'ok', $this->area( 'jobs' )['status'], 'running exam within its time' );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . '` SET deadline_at = %s WHERE id = %d', $db->now( -60 ), $session ) );
		self::assertSame( array( 'exam:' . $uid ), $this->area( 'jobs', 'warn' )['items'] );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . "` SET status = 'cancelled' WHERE id = %d", $session ) );
		self::assertSame( 'ok', $this->area( 'jobs' )['status'] );

		// Site identity: differs → warn (hold mode); recorded and unchanged → ok.
		SiteIdentity::check();
		self::assertSame( 'The site identity is unchanged.', $this->area( 'identity', 'ok' )['message'] );
		$stored                        = get_option( SiteIdentity::OPTION );
		$changed                       = $stored;
		$changed['components']['home'] = 'restored-elsewhere';
		update_option( SiteIdentity::OPTION, $changed, false );
		self::assertSame( array( 'home' ), $this->area( 'identity', 'warn' )['items'] );
		update_option( SiteIdentity::OPTION, $stored, false );

		// Back to the baseline; JSON format and exit codes (fail → 1).
		self::assertSame( 'ok', $this->run_check()['status'] );
		update_option( RestoreCheck::INSTALLATION_UUID, 'broken', false );
		$cli = OpsCommand::execute( 'backup-check', array(), array( 'format' => 'json' ), Actor::cli() );
		self::assertSame( 1, $cli['exit'] );
		self::assertSame( 'fail', json_decode( $cli['lines'][0], true )['status'] );
	}

	/**
	 * Script block of the public media folder (R0-10 §4.4 row 3): an
	 * `.html` probe answered with the token → critical with the nginx rule
	 * for scripts; refused → ok; private base → no request.
	 */
	public function test_media_script_block_self_test(): void {
		$answer = array(
			'txt'  => 403,
			'html' => 200,
		);
		$this->stub_http( $answer );
		$test = StorageSelfTest::run( true );
		self::assertSame( 'exposed', $test['state'] );
		self::assertSame( 'exposed', $test['scripts'] );
		self::assertSame(
			array(
				'protected' => 'ok',
				'packages'  => 'ok',
			),
			$test['folders']
		);
		$health = StorageSelfTest::health();
		self::assertSame( 'critical', $health['status'] );
		self::assertSame( 'The web server delivers script files from the Course Captain media folder.', $health['label'] );
		self::assertStringContainsString( '/media/.*\.(php[0-9]?|phtml|phar|html?|svgz?|js|cgi|pl|py|sh)$ { deny all; }', $health['description'] );
		self::assertStringNotContainsString( '(packages|protected)-', $health['description'] );
		self::assertSame( array(), glob( ProtectedStorage::dir( 'media' ) . '/ccap-selftest-*' ), 'probe file removed' );
		self::assertStringContainsString( 'FilesMatch', (string) file_get_contents( ProtectedStorage::dir( 'media' ) . '/.htaccess' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$answer['html'] = 403;
		$this->stub_http( $answer );
		self::assertSame( 'ok', StorageSelfTest::run( true )['scripts'] );
		self::assertSame( 'good', StorageSelfTest::health()['status'] );

		$answer['html'] = 0;
		$this->stub_http( $answer );
		$unknown = StorageSelfTest::run( true );
		self::assertSame( 'unknown', $unknown['scripts'] );
		self::assertSame( 'unknown', $unknown['state'] );

		$private      = get_temp_dir() . 'ccap-h3-private-' . strtolower( wp_generate_password( 8, false, false ) );
		$this->dirs[] = $private;
		wp_mkdir_p( $private );
		$this->add_filter( 'course_captain_private_dir', static fn(): string => $private );
		$GLOBALS['ccap_test_http_requests'] = array();
		$outside                            = StorageSelfTest::run( true );
		self::assertSame( 'private', $outside['scripts'] );
		self::assertSame( 'private', $outside['state'] );
		self::assertSame( array(), $GLOBALS['ccap_test_http_requests'], 'no request for folders without public URL' );
	}

	/**
	 * "Check again" on the system status page (R1-38 pattern): nonce, #23,
	 * request key against double submits, audit `job_retried`; the page
	 * shows the cached result without a request while rendering.
	 */
	public function test_storage_recheck_repair_action(): void {
		wp_roles()->for_site( get_current_blog_id() );
		$admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$support = (int) self::factory()->user->create( array( 'role' => Roles::SUPPORT ) );
		PolicyService::flush();
		$requests = $this->stub_http(
			array(
				'txt'  => 403,
				'html' => 403,
			)
		);
		$audits   = static fn(): int => (int) Database::current()->scalar( 'SELECT COUNT(*) FROM `' . Database::current()->table( 'ccap_audit_log' ) . "` WHERE action = 'job_retried' AND object_type = 'storage'" );

		wp_set_current_user( $support );
		$html = OpsScreen::html( array() );
		self::assertStringContainsString( 'Storage self test', $html );
		self::assertStringContainsString( 'has not run yet', $html );
		self::assertStringNotContainsString( 'value="storage_recheck"', $html, 'no button without #23' );
		self::assertSame( 0, $requests->count, 'rendering sends no request' );
		$denied = OpsScreen::process(
			array(
				'op'       => 'storage_recheck',
				'_wpnonce' => wp_create_nonce( 'ccap_ops_storage_recheck' ),
			),
			$support
		);
		self::assertSame( 'ccap_forbidden', $denied['result'] );
		self::assertSame( 0, $requests->count );
		self::assertFalse( get_transient( StorageSelfTest::TRANSIENT ) );

		wp_set_current_user( $admin );
		self::assertSame(
			'ccap_invalid_nonce',
			OpsScreen::process(
				array(
					'op'       => 'storage_recheck',
					'_wpnonce' => 'wrong',
				),
				$admin
			)['result']
		);
		self::assertSame( 0, $requests->count );
		self::assertStringContainsString( 'value="storage_recheck"', OpsScreen::html( array() ) );

		// A stale "exposed" result is replaced by the fresh run.
		set_transient(
			StorageSelfTest::TRANSIENT,
			array(
				'state'      => 'exposed',
				'folders'    => array(
					'protected' => 'exposed',
					'packages'  => 'ok',
				),
				'scripts'    => 'ok',
				'checked_at' => time() - 3600,
			),
			HOUR_IN_SECONDS
		);
		$key  = wp_generate_uuid4();
		$post = array(
			'op'          => 'storage_recheck',
			'_wpnonce'    => wp_create_nonce( 'ccap_ops_storage_recheck' ),
			'request_key' => $key,
		);
		$done = OpsScreen::process( $post, $admin );
		self::assertSame( 'storage_ok', $done['result'] );
		self::assertSame( 'status', $done['area'] );
		self::assertSame( 3, $requests->count, 'protected, packages, media' );
		self::assertSame( 'ok', StorageSelfTest::cached()['state'] ?? '' );
		self::assertSame( 1, $audits() );
		$entry = Database::current()->row( 'SELECT actor_ref, object_id, context FROM `' . Database::current()->table( 'ccap_audit_log' ) . "` WHERE action = 'job_retried' AND object_type = 'storage'" );
		self::assertSame( $admin, \CourseCaptain\Event\AuditIdentity::user_of( Database::current(), $entry['actor_ref'] ) );
		self::assertSame( 'selftest', $entry['object_id'] );
		$details = json_decode( (string) $entry['context'], true );
		self::assertSame( 'exposed', $details['before'] );
		self::assertSame( 'ok', $details['state'] );
		self::assertStringNotContainsString( ProtectedStorage::secret(), (string) $entry['context'] );

		// Same form again: first result, no second probe, no second audit entry.
		self::assertSame( 'storage_ok', OpsScreen::process( $post, $admin )['result'] );
		self::assertSame( 3, $requests->count );
		self::assertSame( 1, $audits() );

		$page = OpsScreen::html(
			array(
				'ccap_ops_result' => 'storage_ok',
				'ccap_ops'        => 'storage_recheck',
			)
		);
		self::assertStringContainsString( 'The storage self test ran again. Result: blocked.', $page );
		self::assertStringContainsString( 'Scripts in the media folder: blocked', $page );
		self::assertSame( 3, $requests->count );

		// Service and CLI actor follow the same rule.
		try {
			RepairService::current()->recheck_storage( Actor::user( $support ) );
			self::fail( 'support must not run the self test' );
		} catch ( \CourseCaptain\Course\CourseException $e ) {
			self::assertSame( 'ccap_forbidden', $e->error_code );
		}
		self::assertSame( 'ok', RepairService::current()->recheck_storage( Actor::cli() )['state'] );
		self::assertSame( 2, $audits() );
	}

	/**
	 * PRIV-018: logout sets the marker cookie; the login page always and
	 * front-end pages after a logout print the purge script, which removes
	 * only `ccap_exam_*` keys; the exam room script honours the marker.
	 *
	 * @group PRIV-018
	 */
	public function test_PRIV_018_logout_empties_exam_buffer(): void {
		$cookies = ExamBufferPurge::cookies();
		self::assertNotEmpty( $cookies );
		foreach ( $cookies as $cookie ) {
			self::assertFalse( $cookie['httponly'], 'the script removes the marker' );
			self::assertSame( 'Lax', $cookie['samesite'] );
			self::assertGreaterThan( time(), $cookie['expires'] );
			self::assertStringStartsWith( '/', $cookie['path'] );
		}
		self::assertNotFalse( has_action( 'wp_logout', array( ExamBufferPurge::class, 'on_logout' ) ) );
		self::assertNotFalse( has_action( 'login_footer', array( ExamBufferPurge::class, 'print_script' ) ) );

		// Without a logout no front-end page carries the script.
		unset( $_COOKIE[ ExamBufferPurge::COOKIE ] );
		self::assertSame( '', $this->capture( 'wp_footer' ) );

		// Logout of a learner: marker set for this request and the following pages.
		$user = $this->fx['users']['ole'];
		wp_set_current_user( 0 );
		do_action( 'wp_logout', $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
		self::assertSame( '1', sanitize_key( wp_unslash( $_COOKIE[ ExamBufferPurge::COOKIE ] ?? '' ) ) );
		self::assertSame( 0, get_current_user_id() );
		$footer = $this->capture( 'wp_footer' );
		self::assertStringContainsString( '"ccap_exam_"', $footer );
		self::assertStringContainsString( 'localStorage', $footer );
		self::assertStringContainsString( 'sessionStorage', $footer );
		self::assertStringContainsString( 'ccap_exam_purge=; Max-Age=0', $footer );

		// The login page always carries it; it acts only with the marker (cached pages).
		unset( $_COOKIE[ ExamBufferPurge::COOKIE ] );
		$login = $this->capture( 'login_footer' );
		self::assertStringContainsString( ExamBufferPurge::script(), $login );
		self::assertStringContainsString( 'indexOf(";ccap_exam_purge=")', ExamBufferPurge::script() );
		self::assertStringNotContainsString( 'cc_fx_ole', $login, 'no personal data' );

		// The exam room script empties all buffers before it reads or sends one.
		$js     = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/exam-room.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$marker = strpos( $js, "';ccap_exam_purge='" );
		self::assertNotFalse( $marker );
		self::assertLessThan( strpos( $js, 'function load()' ), $marker );
	}

	/**
	 * Output of an action.
	 *
	 * @param string $hook Hook.
	 * @return string
	 */
	private function capture( string $hook ): string {
		$callbacks = array(
			'wp_footer'    => array( ExamBufferPurge::class, 'maybe_print_script' ),
			'login_footer' => array( ExamBufferPurge::class, 'print_script' ),
		);
		ob_start();
		call_user_func( $callbacks[ $hook ] );
		return (string) ob_get_clean();
	}

	/**
	 * Run the check and assert it changed nothing.
	 *
	 * @return array{status: string, checks: list<array{id: string, status: string, message: string, items: list<string>}>}
	 */
	private function run_check(): array {
		$before = $this->snapshot();
		$report = RestoreCheck::run();
		self::assertSame( $before, $this->snapshot(), 'backup-check is read only' );
		self::assertStringNotContainsString( ProtectedStorage::secret(), (string) wp_json_encode( $report ) );
		return $report;
	}

	/**
	 * One area of a read-only run.
	 *
	 * @param string      $id     Area.
	 * @param string|null $status Expected status.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private function area( string $id, ?string $status = null ): array {
		$checks = array_column( $this->run_check()['checks'], null, 'id' );
		self::assertArrayHasKey( $id, $checks );
		if ( null !== $status ) {
			self::assertSame( $status, $checks[ $id ]['status'], (string) wp_json_encode( $checks[ $id ] ) );
		}
		return $checks[ $id ];
	}

	/**
	 * Files below the storage base, own options and transients, all own tables.
	 *
	 * @return array<string, mixed>
	 */
	private function snapshot(): array {
		global $wpdb;
		$files = array();
		$base  = $this->base;
		foreach ( array( 'protected-' . ProtectedStorage::secret(), 'packages-' . ProtectedStorage::secret(), 'media' ) as $folder ) {
			$files[ $folder ] = is_dir( $base . '/' . $folder ) ? 'dir' : 'none';
			if ( ! is_dir( $base . '/' . $folder ) ) {
				continue;
			}
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $base . '/' . $folder, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST );
			foreach ( $it as $entry ) {
				$files[ (string) $entry ] = $entry->isFile() ? md5_file( (string) $entry ) : 'dir';
			}
		}
		ksort( $files );
		$db     = Database::current();
		$tables = array();
		foreach ( Schema::table_names() as $table ) {
			$tables[ $table ] = md5( (string) wp_json_encode( $db->rows( 'SELECT * FROM `' . $db->table( $table ) . '` ORDER BY 1' ) ) );
		}
		return array(
			'files'   => $files,
			'options' => $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '%course\\_captain%' OR option_name LIKE '%ccap%' ORDER BY option_name", ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'tables'  => $tables,
			'meta'    => md5( (string) wp_json_encode( $wpdb->get_results( "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_N ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test snapshot; SQL MD5() does not exist on MySQL 9.7.
		);
	}

	/**
	 * Active grant inserted directly (backup state).
	 *
	 * @param int $user       User (may not exist).
	 * @param int $course     Course.
	 * @param int $depends_on Base course (0 = none).
	 * @return string Grant UID.
	 */
	private function grant( int $user, int $course, int $depends_on = 0 ): string {
		$db  = Database::current();
		$uid = $db->uuid();
		$db->insert(
			'ccap_grant',
			array(
				'uid'                  => $uid,
				'user_id'              => $user,
				'course_id'            => $course,
				'depends_on_course_id' => $depends_on,
				'status'               => 'active',
				'source_type'          => 'manual',
				'source_id'            => 'h3-' . $uid,
			),
			array( 'created_at', 'updated_at' )
		);
		return $uid;
	}

	/**
	 * Delivery `dispatching` with an expired lease.
	 *
	 * @return int Delivery ID.
	 */
	private function dispatching_delivery(): int {
		$db   = Database::current();
		$uuid = $db->uuid();
		$db->insert(
			'ccap_outbox_event',
			array(
				'event_uuid'       => $uuid,
				'event_type'       => 'access.changed',
				'schema_version'   => '1.0',
				'contract_version' => '0.1.0',
				'object_type'      => 'grant',
				'object_id'        => $db->uuid(),
				'idempotency_key'  => 'k:' . $uuid,
				'payload'          => '{}',
				'created_at'       => $db->now(),
			)
		);
		return $db->insert(
			'ccap_outbox_delivery',
			array(
				'event_uuid'    => $uuid,
				'subscriber_id' => 'cache',
				'status'        => 'dispatching',
				'locked_until'  => $db->now( -600 ),
				'lock_owner'    => 'h3',
				'created_at'    => $db->now(),
			)
		);
	}

	/**
	 * A protected PDF attachment registered as course medium.
	 *
	 * @param string $name File name part.
	 * @return array{attachment: int, media: int, uid: string}
	 */
	private function protected_attachment( string $name ): array {
		$admin  = Actor::user( $this->fx['users']['admin'] );
		$upload = wp_upload_bits( 'h3-' . $name . '-' . wp_generate_password( 6, false ) . '.pdf', null, "%PDF-1.4\n" . str_repeat( 'h', 64 ) );
		$id     = (int) wp_insert_attachment(
			array(
				'post_title'     => $name,
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		$media  = MediaLibrary::current()->register_attachment( $admin, $id );
		ProtectedStorage::protect_attachment( $id, $media );
		$this->dirs[] = (string) get_attached_file( $id, true );
		$db           = Database::current();
		return array(
			'attachment' => $id,
			'media'      => $media,
			'uid'        => (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_media' ) . '` WHERE id = %d', $media ) ),
		);
	}

	/**
	 * Answer the self-test probes in-process: status code by file extension
	 * (0 = no answer); 200 returns the probe file. Returns a request counter.
	 *
	 * @param array<string, int> $codes Extension => status code.
	 * @return object{count: int}
	 */
	private function stub_http( array $codes ): object {
		$this->drop_filters( 'pre_http_request' );
		$counter = new class() {
			/**
			 * Requests.
			 *
			 * @var int
			 */
			public int $count = 0;
		};
		$this->add_filter(
			'pre_http_request',
			static function ( $pre, array $args, string $url ) use ( $codes, $counter ) {
				unset( $pre, $args );
				++$counter->count;
				$code = $codes[ pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ] ?? 0;
				if ( 0 === $code ) {
					return new \WP_Error( 'http_request_failed', 'timeout' );
				}
				$uploads = wp_upload_dir( null, false );
				$file    = untrailingslashit( (string) $uploads['basedir'] ) . substr( (string) wp_parse_url( $url, PHP_URL_PATH ), strlen( (string) wp_parse_url( (string) $uploads['baseurl'], PHP_URL_PATH ) ) );
				return array(
					'headers'  => array(),
					'body'     => 200 === $code ? (string) file_get_contents( $file ) : 'Forbidden', // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					'response' => array(
						'code'    => $code,
						'message' => '',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);
		return $counter;
	}

	/**
	 * Add a filter removed after the test.
	 *
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted arguments.
	 */
	private function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_filter( $hook, $callback, $priority, $args );
		$this->filters[] = array( $hook, $callback, $priority );
	}

	/**
	 * Remove the filters added so far (optionally of one hook).
	 *
	 * @param string $hook Hook or empty for all.
	 */
	private function drop_filters( string $hook = '' ): void {
		foreach ( $this->filters as $i => $filter ) {
			if ( '' === $hook || $filter[0] === $hook ) {
				remove_filter( $filter[0], $filter[1], $filter[2] );
				unset( $this->filters[ $i ] );
			}
		}
		$this->filters = array_values( $this->filters );
	}

	/**
	 * Remove a directory recursively (no symlink targets).
	 *
	 * @param string $dir Directory.
	 */
	private static function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) || is_link( $dir ) ) {
			return;
		}
		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				self::remove_dir( $path );
			} else {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
