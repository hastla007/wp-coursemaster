<?php
/**
 * R1-18 (group g5): Golden-Master cases GM-162, GM-167, GM-174, GM-175,
 * GM-176, GM-189 and GM-190 (R0-05 §1.14–§1.16, §1.20).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Admin\MigrationNotice;
use CourseCaptain\Admin\Notices;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\FaqService;
use CourseCaptain\Course\StarterContent;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Privacy\DataExport;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Privacy\RetentionService;
use CourseCaptain\Privacy\SelfService;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Lease;
use CourseCaptain\Storage\Migrator;

/**
 * Real tables.
 *
 * @group R1-18
 */
final class GmOpsPrivacyFaqTest extends SchemaTestCase {

	/**
	 * Fixed test time T0 (2030-01-15, R0-05 §2.1).
	 */
	private const T0 = 1894665600;

	/**
	 * Administrator.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * U-L1 (learner).
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Address of U-L1 (unique per test: schema tests commit, D-07).
	 *
	 * @var string
	 */
	private string $email;

	/**
	 * C-A (published course).
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$suffix        = strtolower( wp_generate_password( 6, false ) );
		$this->email   = 'lea-' . $suffix . '@fixture.test';
		$this->learner = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_login' => 'cc_fx_lea_' . $suffix,
				'user_email' => $this->email,
			)
		);
		$this->course  = $this->course( 'Kurs Alpha' );
		PolicyService::flush();
		Notices::reset();
		reset_phpmailer_instance();
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook.
	}

	/**
	 * Clean up files and the request queue.
	 */
	public function tear_down(): void {
		Notices::reset();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Published course.
	 *
	 * @param string $title Title.
	 * @return int
	 */
	private function course( string $title ): int {
		return (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => $title,
			)
		);
	}

	/**
	 * REST call as the current user.
	 *
	 * @param string               $method Method.
	 * @param string               $path   Path below the namespace.
	 * @param array<string, mixed> $params Parameters.
	 * @return \WP_REST_Response
	 */
	private static function call( string $method, string $path, array $params = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/course-captain/v1' . $path );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	/**
	 * Error code of a REST response.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @return string
	 */
	private static function code( \WP_REST_Response $response ): string {
		$data = (array) $response->get_data();
		return (string) ( $data['code'] ?? '' );
	}

	/**
	 * Count of audit rows (optionally of one action).
	 *
	 * @param string|null $action Action.
	 * @return int
	 */
	private static function audit_count( ?string $action = null ): int {
		$db  = Database::current();
		$sql = 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . '`';
		return (int) $db->scalar( null === $action ? $sql : $db->prepare( $sql . ' WHERE action = %s', $action ) );
	}

	/**
	 * GM-162 (RT-23, R0-13 §4.4, E-44): 6 000 entries from T0 − 5 years and
	 * 10 from T0 − 1 year; the first run removes 5 000, the second 1 000, the
	 * young 10 stay; each run leaves one `audit_pruned` summary.
	 *
	 * @group GM-162
	 */
	public function test_GM_162_audit_retention_in_batches(): void {
		$db    = Database::current();
		$table = $db->table( 'ccap_audit_log' );
		$db->execute( "DELETE FROM `{$table}`" );
		$old   = gmdate( 'Y-m-d H:i:s', (int) strtotime( '-5 years', self::T0 ) );
		$young = gmdate( 'Y-m-d H:i:s', (int) strtotime( '-1 year', self::T0 ) );
		for ( $chunk = 0; $chunk < 12; $chunk++ ) {
			$values = array();
			for ( $i = 0; $i < 500; $i++ ) {
				$values[] = $db->prepare( "('system', 'grant_manual_created', 'grant', %s, %s, %s)", (string) ( $chunk * 500 + $i ), $old, hash( 'sha256', 'old' . $chunk . ':' . $i ) );
			}
			$db->execute( "INSERT INTO `{$table}` (actor_type, action, object_type, object_id, created_at, row_hash) VALUES " . implode( ',', $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared rows.
		}
		for ( $i = 0; $i < 10; $i++ ) {
			$db->execute( $db->prepare( "INSERT INTO `{$table}` (actor_type, action, object_type, object_id, created_at, row_hash) VALUES ('system', 'group.created', 'group', %s, %s, %s)", 'young-' . $i, $young, hash( 'sha256', 'young' . $i ) ) );
		}
		self::assertSame( 6010, self::audit_count() );

		$first = RetentionService::run( self::T0 );
		self::assertSame( 5000, $first['RT-23_audit'] );
		self::assertSame( 1000, (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE created_at = %s", $old ) ) );
		$summary = (array) json_decode( (string) $db->scalar( "SELECT context FROM `{$table}` WHERE action = 'audit_pruned' ORDER BY id DESC LIMIT 1" ), true );
		self::assertSame( 5000, $summary['count'] );
		self::assertSame( $old, $summary['oldest'] );
		self::assertSame( $old, $summary['newest'] );
		self::assertSame( 64, strlen( (string) $summary['anchor'] ), 'anchor = hash of the last removed row' );

		$second = RetentionService::run( self::T0 );
		self::assertSame( 1000, $second['RT-23_audit'] );
		self::assertSame( 0, (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE created_at = %s", $old ) ) );
		self::assertSame( 10, (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE created_at = %s", $young ) ), 'the young 10 stay' );
		self::assertSame( 2, self::audit_count( 'audit_pruned' ) );

		self::assertSame( 0, RetentionService::run( self::T0 )['RT-23_audit'], 'third run: nothing left' );
		self::assertSame( 2, self::audit_count( 'audit_pruned' ) );
		self::assertSame( 4, RetentionService::AUDIT_YEARS );
		self::assertSame( 5000, RetentionService::AUDIT_BATCH );
	}

	/**
	 * GM-167 (R0-37 §4.5): failed update steps give exactly one admin notice;
	 * the database message is masked; only `manage_options` sees it.
	 *
	 * @group GM-167
	 */
	public function test_GM_167_one_masked_notice_for_admins(): void {
		$db    = Database::current();
		$steps = $db->table( 'ccap_migration_step' );
		$ids   = array_slice( array_keys( Migrator::for_current_site()->report()['steps'] ), 0, 2 );
		self::assertCount( 2, $ids );
		$secret = "ALTER command denied to user 'cc'@'db.internal' for table 'wptests_ccap_seat'";
		foreach ( $ids as $id ) {
			$db->execute( $db->prepare( "UPDATE `{$steps}` SET status = 'failed', last_error = %s WHERE step_id = %s", $secret, $id ) );
		}
		$migrator = Migrator::for_current_site();
		self::assertSame( 'failed', $migrator->report()['state'] );

		// Subscriber and editor: no notice.
		foreach ( array( $this->learner, (int) self::factory()->user->create( array( 'role' => 'editor' ) ) ) as $user ) {
			wp_set_current_user( $user );
			self::assertFalse( MigrationNotice::maybe_add( $migrator ) );
			self::assertSame( array(), Notices::visible( $user ) );
		}

		wp_set_current_user( $this->admin );
		self::assertTrue( MigrationNotice::maybe_add( $migrator ) );
		self::assertTrue( MigrationNotice::maybe_add( $migrator ), 'second trigger in the same request' );
		$visible = Notices::visible( $this->admin );
		self::assertSame( array( MigrationNotice::CODE ), array_keys( $visible ), 'exactly one notice for two failed steps' );
		self::assertSame( 'error', $visible[ MigrationNotice::CODE ]['severity'] );
		ob_start();
		Notices::render();
		$html = (string) ob_get_clean();
		self::assertSame( 1, substr_count( $html, 'The database update failed' ) );
		self::assertSame( 1, substr_count( $html, '<div class="notice' ) );
		foreach ( $ids as $id ) {
			self::assertStringContainsString( $id, $html );
		}
		foreach ( array( 'denied', 'db.internal', 'wptests_ccap_seat', "'cc'" ) as $leak ) {
			self::assertStringNotContainsString( $leak, $html, 'database message is masked' );
		}

		// Repaired: no notice any more.
		$db->execute( "UPDATE `{$steps}` SET status = 'done', last_error = NULL" );
		Notices::reset();
		self::assertFalse( MigrationNotice::maybe_add( Migrator::for_current_site() ) );
		self::assertSame( array(), Notices::visible( $this->admin ) );
	}

	/**
	 * GM-174 (R0-19 R-48, E-43, RB-9): request + mail; a second request
	 * creates no new one; GET never erases; POST erases (with the opt-in);
	 * staff accounts cannot request their own erasure.
	 *
	 * @group GM-174
	 */
	public function test_GM_174_self_service_requests(): void {
		wp_set_current_user( $this->learner );
		$created = self::call( 'POST', '/me/privacy-requests', array( 'type' => 'export' ) );
		self::assertSame( 202, $created->get_status() );
		self::assertSame( array( 'status' => 'created' ), $created->get_data() );
		$mailer = tests_retrieve_phpmailer_instance();
		self::assertSame( $this->email, $mailer->get_recipient( 'to' )->address );
		self::assertStringContainsString( 'action=' . SelfService::ACTION_CONFIRM, (string) $mailer->get_sent()->body );
		self::assertStringNotContainsString( 'confirmaction', (string) $mailer->get_sent()->body, 'link goes to the own confirmation page' );

		reset_phpmailer_instance();
		$again = self::call( 'POST', '/me/privacy-requests', array( 'type' => 'export' ) );
		self::assertSame( array( 'status' => 'resent' ), $again->get_data() );
		self::assertSame( 1, count( get_posts( array( 'post_type' => 'user_request', 'post_status' => 'any', 'fields' => 'ids', 'title' => $this->email, 'name' => 'export_personal_data' ) ) ), 'no new request' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
		self::assertStringContainsString( $this->email, (string) tests_retrieve_phpmailer_instance()->get_recipient( 'to' )->address );
		self::assertSame( 400, self::call( 'POST', '/me/privacy-requests', array( 'type' => 'everything' ) )->get_status() );

		// Erasure with the opt-in (E-43): confirmation page by GET changes nothing, POST erases.
		Settings::save( Actor::user( $this->admin ), 'privacy', array( 'auto_erase_on_confirm' => true ), array( 'auto_erase_on_confirm' ), Settings::rev( 'privacy' ) );
		Database::current()->insert(
			'ccap_course_progress',
			array(
				'user_id'    => $this->learner,
				'course_id'  => $this->course,
				'status'     => 'in_progress',
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		wp_set_current_user( $this->learner );
		reset_phpmailer_instance();
		$result = SelfService::request( Actor::user( $this->learner ), 'erase' );
		self::assertSame( 'created', $result['status'] );
		$body = (string) tests_retrieve_phpmailer_instance()->get_sent()->body;
		self::assertSame( 1, preg_match( '/confirm_key=([^&\s]+)/', $body, $match ) );
		$key = rawurldecode( html_entity_decode( $match[1] ) );
		wp_set_current_user( 0 );

		$page = SelfService::confirm_page( $result['request_id'], $key );
		self::assertStringContainsString( '<form method="post"', $page );
		self::assertStringContainsString( 'Confirm erasure', $page );
		// The page as actually printed (handle_confirm output filter) keeps the working form.
		$printed = SelfService::page_html( $page );
		self::assertStringContainsString( '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">', $printed );
		self::assertStringContainsString( '<input type="hidden" name="action" value="' . SelfService::ACTION_CONFIRM . '">', $printed );
		self::assertStringContainsString( '<input type="hidden" name="request_id" value="' . $result['request_id'] . '">', $printed );
		self::assertStringContainsString( 'name="confirm_key" value="', $printed );
		self::assertStringContainsString( '<button type="submit">', $printed );
		self::assertStringNotContainsString( '<script', SelfService::page_html( $page . '<script>alert(1)</script>' ) );
		self::assertSame( 'request-pending', wp_get_user_request( $result['request_id'] )->status, 'GET: still pending' );
		self::assertNotFalse( get_userdata( $this->learner ), 'GET never erases' );
		self::assertSame( 'invalid', SelfService::confirm( $result['request_id'], 'wrong-key' ) );
		self::assertNotFalse( get_userdata( $this->learner ) );

		self::assertSame( 'confirmed', SelfService::confirm( $result['request_id'], $key ) );
		$db = Database::current();
		self::assertSame( 0, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_course_progress' ) . '` WHERE user_id = %d', $this->learner ) ), 'POST erases the learning data' );
		if ( ! is_multisite() ) {
			self::assertFalse( get_userdata( $this->learner ), 'POST deletes the account (opt-in)' );
		}
		self::assertSame( 'invalid', SelfService::confirm( $result['request_id'], $key ), 'link works once' );

		// U-SM: staff role without self-erasure; export stays possible.
		$manager = (int) self::factory()->user->create(
			array(
				'role'       => Roles::COURSE_MANAGER,
				'user_email' => 'manager-' . strtolower( wp_generate_password( 6, false ) ) . '@fixture.test',
			)
		);
		wp_set_current_user( $manager );
		$denied = self::call( 'POST', '/me/privacy-requests', array( 'type' => 'erase' ) );
		self::assertSame( 403, $denied->get_status() );
		self::assertSame( 'ccap_forbidden', self::code( $denied ) );
		self::assertSame( array(), get_posts( array( 'post_type' => 'user_request', 'post_status' => 'any', 'title' => get_userdata( $manager )->user_email, 'name' => 'remove_personal_data' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
		self::assertSame( 202, self::call( 'POST', '/me/privacy-requests', array( 'type' => 'export' ) )->get_status() );
		self::assertNotFalse( get_userdata( $manager ) );

		// Guests: 401; limit 5 per day.
		wp_set_current_user( 0 );
		self::assertSame( 401, self::call( 'POST', '/me/privacy-requests', array( 'type' => 'export' ) )->get_status() );
		wp_set_current_user( $manager );
		for ( $i = 0; $i < 4; $i++ ) {
			self::call( 'POST', '/me/privacy-requests', array( 'type' => 'export' ) );
		}
		$limited = self::call( 'POST', '/me/privacy-requests', array( 'type' => 'export' ) );
		self::assertSame( 429, $limited->get_status() );
		self::assertSame( 'ccap_rate_limited', self::code( $limited ) );
		self::assertSame( 3, self::audit_count( 'privacy_request_created' ), 'created requests are audited, resent ones not' );
		self::assertStringNotContainsString( '@', (string) Database::current()->scalar( 'SELECT GROUP_CONCAT(COALESCE(context, \'\')) FROM `' . Database::current()->table( 'ccap_audit_log' ) . "` WHERE action = 'privacy_request_created'" ), 'audit without address' );
	}

	/**
	 * GM-175 (R0-19 §3): asynchronous export: `pending` → `ready`; 409 before;
	 * owner only; expired → 410 and the file is deleted; failure → `failed`/410.
	 *
	 * @group GM-175
	 */
	public function test_GM_175_async_export(): void {
		$db       = Database::current();
		$question = $db->insert(
			'ccap_question',
			array(
				'uid'    => $db->uuid(),
				'status' => 'published',
			),
			array( 'created_at', 'updated_at' )
		);
		$db->insert(
			'ccap_favorite',
			array(
				'user_id'     => $this->learner,
				'question_id' => $question,
				'created_at'  => $db->now(),
			)
		);
		wp_set_current_user( $this->learner );
		$created = self::call( 'POST', '/me/data-exports' );
		self::assertSame( 202, $created->get_status() );
		$job = (array) $created->get_data();
		self::assertSame( DataExport::PENDING, $job['status'] );
		self::assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', (string) $job['uid'] );
		self::assertNotFalse( wp_next_scheduled( CronHooks::PRIVACY_EXPORT, array( 0, $this->learner ) ), 'built in the background' );
		self::assertSame( $job['uid'], self::call( 'POST', '/me/data-exports' )->get_data()['uid'], 'a pending job is not duplicated' );

		$early = self::call( 'GET', '/me/data-exports/' . $job['uid'] );
		self::assertSame( 409, $early->get_status() );
		self::assertSame( 'ccap_export_not_ready', self::code( $early ) );

		do_action( CronHooks::PRIVACY_EXPORT, 0, $this->learner ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Own hook constant.
		self::assertSame( DataExport::READY, DataExport::job( $this->learner )['status'] );
		$ready = self::call( 'GET', '/me/data-exports/' . $job['uid'] );
		self::assertSame( 200, $ready->get_status() );
		$data = (array) $ready->get_data();
		self::assertSame( 'ready', $data['status'] );
		self::assertArrayHasKey( 'cc-favorite', $data['data']['groups'] );
		self::assertSame( 'private, no-store', $ready->get_headers()['Cache-Control'] );

		// Owner only: another person gets 404 for the same UID.
		$other = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $other );
		$foreign = self::call( 'GET', '/me/data-exports/' . $job['uid'] );
		self::assertSame( 404, $foreign->get_status() );
		self::assertSame( 'ccap_not_found', self::code( $foreign ) );
		wp_set_current_user( 0 );
		self::assertSame( 401, self::call( 'GET', '/me/data-exports/' . $job['uid'] )->get_status() );

		// Expired after 24 h: 410, file deleted.
		$stored = (array) DataExport::job( $this->learner );
		$path   = (string) DataExport::path( $stored );
		self::assertFileExists( $path );
		try {
			DataExport::get( Actor::user( $this->learner ), (string) $job['uid'], (int) $stored['expires_at'] + 1 );
			self::fail( 'expected 410' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_export_expired', $e->error_code );
			self::assertSame( 410, $e->status );
		}
		self::assertFileDoesNotExist( $path );
		wp_set_current_user( $this->learner );
		self::assertSame( 'ccap_export_expired', self::code( self::call( 'GET', '/me/data-exports/' . $job['uid'] ) ) );

		// Failure while building (export folder not writable) → failed / 410, no partial file.
		$second = DataExport::request( Actor::user( $this->learner ) );
		self::assertTrue( $second['created'] );
		$uid = (string) $second['export']['uid'];
		self::assertNotSame( $job['uid'], $uid );
		$dir = \CourseCaptain\Access\ProtectedStorage::dir( 'packages' ) . '/exports';
		foreach ( (array) glob( $dir . '/*' ) as $leftover ) {
			wp_delete_file( (string) $leftover );
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		file_put_contents( $dir, 'blocker' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		self::assertSame( DataExport::FAILED, DataExport::generate( $this->learner ) );
		wp_delete_file( $dir );
		$failed = self::call( 'GET', '/me/data-exports/' . $uid );
		self::assertSame( 410, $failed->get_status() );
		self::assertSame( 'ccap_export_failed', self::code( $failed ) );
		self::assertSame( 'none', DataExport::generate( $this->learner ), 'a failed job is not rebuilt' );

		// A new request after the failure builds again.
		$third = DataExport::request( Actor::user( $this->learner ) );
		self::assertSame( DataExport::READY, DataExport::generate( $this->learner ) );
		self::assertSame( 200, self::call( 'GET', '/me/data-exports/' . $third['export']['uid'] )->get_status() );

		// RT-36: hourly retention removes old export files.
		$dir  = \CourseCaptain\Access\ProtectedStorage::dir( 'packages' ) . '/exports';
		$file = $dir . '/' . wp_generate_uuid4() . '.json';
		file_put_contents( $file, '{}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		touch( $file, time() - DataExport::TTL - 60 );
		self::assertSame( 1, RetentionService::run()['RT-36_data_exports'] );
		self::assertFileDoesNotExist( $file );
	}

	/**
	 * GM-176 (RR-7, RO:223): suggested policy section with the periods, no external links.
	 *
	 * @group GM-176
	 */
	public function test_GM_176_privacy_policy_text(): void {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
		// Core accepts suggested texts only during `admin_init` (the hook PrivacyService uses).
		set_current_screen( 'dashboard' );
		$GLOBALS['wp_current_filter'][] = 'admin_init';
		PrivacyService::policy_text();
		array_pop( $GLOBALS['wp_current_filter'] );
		$GLOBALS['current_screen'] = null;
		$sections                  = array_values(
			array_filter(
				\WP_Privacy_Policy_Content::get_suggested_policy_text(),
				static fn( array $section ): bool => 'Course Captain' === $section['plugin_name']
			)
		);
		self::assertCount( 1, $sections, 'one section' );
		$text = (string) $sections[0]['policy_text'];
		foreach ( array( '2 years after the end of a course', '10 years', 'audit log for 4 years', 'notification log for 90 days' ) as $period ) {
			self::assertStringContainsString( $period, $text );
		}
		self::assertSame( 0, preg_match_all( '/<a\s|https?:\/\//i', $text ), '0 external links' );
		self::assertSame( PrivacyService::policy_html(), $text );
	}

	/**
	 * GM-189 (E-16): starter FAQ FX-FAQ-1…5 seeded once; an update run adds
	 * no duplicates; visitor requests never seed; the lock is released.
	 *
	 * @group GM-189
	 */
	public function test_GM_189_starter_content_seeder(): void {
		$items = array();
		for ( $n = 1; $n <= 5; $n++ ) {
			$items[] = array(
				'key'      => 'FX-FAQ-' . $n,
				'course'   => $this->course,
				'question' => 'Neutral question ' . $n,
				'answer'   => 'Neutral answer ' . $n . '.',
				'order'    => $n,
			);
		}
		$count = fn(): int => count( get_posts( array( 'post_type' => 'ccap_faq', 'post_status' => 'any', 'post_parent' => $this->course, 'posts_per_page' => -1, 'fields' => 'ids' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
		$lease = static fn(): int => (int) Database::current()->scalar( Database::current()->prepare( 'SELECT COUNT(*) FROM `' . Database::current()->table( 'ccap_lease' ) . '` WHERE name = %s', StarterContent::LEASE ) );

		foreach ( array( 'frontend', 'rest', 'ajax' ) as $visitor ) {
			self::assertSame( 'skipped_context', StarterContent::seed( $items, $visitor )['status'], $visitor );
		}
		self::assertSame( 0, $count(), 'visitor requests never import' );

		$first = StarterContent::seed( $items, 'admin' );
		self::assertSame( 'done', $first['status'] );
		self::assertSame( 5, $first['created'] );
		self::assertSame( 5, $count() );
		self::assertSame( 0, $lease(), 'lock released' );

		$again = StarterContent::seed( $items, 'cron' );
		self::assertSame( array( 'status' => 'done', 'created' => 0, 'updated' => 0, 'unchanged' => 5, 'skipped' => 0 ), $again ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
		self::assertSame( 5, $count(), 'update run without duplicates' );

		// Changed definition: updated in place; an entry edited on the site stays as edited.
		$items[1]['answer'] = 'Revised neutral answer 2.';
		$items[2]['answer'] = 'Revised neutral answer 3.';
		$db                 = Database::current();
		$edited             = (int) $db->scalar( $db->prepare( 'SELECT object_id FROM `' . $db->table( 'ccap_source_map' ) . "` WHERE source_system = 'starter' AND source_id = %s", 'FX-FAQ-3' ) );
		wp_update_post(
			array(
				'ID'           => $edited,
				'post_content' => 'Edited by the site.',
			)
		);
		$update = StarterContent::seed( $items, 'cli' );
		self::assertSame( 1, $update['updated'] );
		self::assertSame( 1, $update['skipped'] );
		self::assertSame( 3, $update['unchanged'] );
		self::assertSame( 5, $count() );
		self::assertSame( 'Edited by the site.', get_post( $edited )->post_content );

		// Held lock: the run skips and leaves the foreign lock alone.
		$held = Lease::acquire( $db, StarterContent::LEASE, 300, false );
		self::assertNotNull( $held );
		self::assertSame( 'skipped_locked', StarterContent::seed( $items, 'admin' )['status'] );
		self::assertSame( 1, $lease() );
		$held->release();
		self::assertSame( 0, $lease() );

		// R1 ships no starter texts (E-16): the automatic trigger does nothing.
		self::assertSame( array(), StarterContent::ITEMS );
		self::assertNull( StarterContent::maybe_run( 'admin' ) );
		self::assertSame( 'frontend', StarterContent::context() );
	}

	/**
	 * GM-190 (R-38): changes are visible at once (cache bumped); no drafts;
	 * a gated section shows the notice instead of the section; guests get 401.
	 *
	 * @group GM-190
	 */
	public function test_GM_190_faq_cache_rest_gate(): void {
		$gated_course = $this->course( 'Kurs Beta' );
		$faq          = static fn( int $course, string $title, string $content, string $status = 'publish', int $order = 0 ): int => (int) wp_insert_post(
			array(
				'post_type'    => 'ccap_faq',
				'post_status'  => $status,
				'post_parent'  => $course,
				'post_title'   => $title,
				'post_content' => $content,
				'menu_order'   => $order,
			)
		);
		$faq( $this->course, 'FX-FAQ-1', 'Plain answer.', 'publish', 1 );
		$faq3         = $faq( $this->course, 'FX-FAQ-3', 'Open part. [ccap_protected course="' . $gated_course . '"]Secret part for Beta.[/ccap_protected]', 'publish', 3 );
		$faq( $this->course, 'FX-FAQ-DRAFT', 'Draft answer.', 'draft', 2 );
		GrantService::current()->grant_manual( Actor::user( $this->admin ), $this->learner, $this->course, wp_generate_uuid4(), 'Fixture' );
		PolicyService::flush();
		\CourseCaptain\Access\ContentProtection::flush();

		// Guest: 401 without any FAQ data (no drafts, no answers).
		wp_set_current_user( 0 );
		$guest = self::call( 'GET', '/courses/' . $this->course . '/faqs' );
		self::assertSame( 401, $guest->get_status() );
		self::assertStringNotContainsString( 'FX-FAQ', (string) wp_json_encode( $guest->get_data() ) );

		wp_set_current_user( $this->learner );
		$list = self::call( 'GET', '/courses/' . $this->course . '/faqs' );
		self::assertSame( 200, $list->get_status() );
		$items = (array) $list->get_data();
		self::assertSame( array( 'FX-FAQ-1', 'FX-FAQ-3' ), array_column( $items, 'question' ), 'no drafts' );
		self::assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', (string) $items[0]['uid'] );
		self::assertStringContainsString( 'Open part.', (string) $items[1]['answer_html'] );
		self::assertStringNotContainsString( 'Secret part', (string) $items[1]['answer_html'], 'gate: notice instead of the section' );
		self::assertStringContainsString( 'ccap-locked', (string) $items[1]['answer_html'] );

		// Change is visible at once (same request, warm cache).
		wp_update_post(
			array(
				'ID'         => $faq3,
				'post_title' => 'FX-FAQ-3 (revised)',
			)
		);
		self::assertSame( array( 'FX-FAQ-1', 'FX-FAQ-3 (revised)' ), array_column( FaqService::for_course( Actor::user( $this->learner ), $this->course ), 'question' ) );
		$new = $faq( $this->course, 'FX-FAQ-4', 'Fresh.', 'publish', 4 );
		self::assertCount( 3, FaqService::for_course( Actor::user( $this->learner ), $this->course ) );
		wp_trash_post( $new );
		self::assertCount( 2, FaqService::for_course( Actor::user( $this->learner ), $this->course ) );

		// With access to the gated course the section shows.
		GrantService::current()->grant_manual( Actor::user( $this->admin ), $this->learner, $gated_course, wp_generate_uuid4(), 'Fixture' );
		PolicyService::flush();
		\CourseCaptain\Access\ContentProtection::flush();
		self::assertStringContainsString( 'Secret part for Beta.', (string) FaqService::for_course( Actor::user( $this->learner ), $this->course )[1]['answer_html'] );

		// Without access to the course itself: 403; unknown course: 404.
		$stranger = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $stranger );
		$locked = self::call( 'GET', '/courses/' . $this->course . '/faqs' );
		self::assertSame( 403, $locked->get_status() );
		self::assertSame( 'ccap_content_locked', self::code( $locked ) );
		self::assertSame( 404, self::call( 'GET', '/courses/999999/faqs' )->get_status() );
	}
}
