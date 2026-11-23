<?php
/**
 * R1-21: notifications and mail templates (R0-27 §3, NTF-*; R0-05 GM-159 batch part).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\GroupService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\Outbox;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Notification\MailTemplates;
use CourseCaptain\Notification\NotificationPrefs;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Notification\NotificationTypes;
use CourseCaptain\Notification\OutboundMode;
use CourseCaptain\Notification\TemplateError;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Privacy\RetentionService;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

/**
 * Real tables; mails go into a sink via `pre_wp_mail` (NTF-007), never out.
 *
 * @group R1-21
 */
final class NotificationTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Unique suffix of the fixture addresses (accounts survive the committed test transactions).
	 *
	 * @var string
	 */
	private string $tag = '';

	/**
	 * Mail sink (wp_mail attributes).
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $sink = array();

	/**
	 * What the sink returns (true = accepted, false = transport refused).
	 *
	 * @var bool
	 */
	private bool $accept = true;

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->tag     = strtolower( wp_generate_password( 8, false ) );
		$this->admin   = (int) self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'admin-ntf-' . $this->tag . '@example.org',
			)
		);
		$this->learner = (int) self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'user_email'   => 'learner-ntf-' . $this->tag . '@example.org',
				'display_name' => 'Lea Learner',
			)
		);
		$this->course  = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'Course N',
				'post_author' => $this->admin,
			)
		);
		PolicyService::flush();
		$this->sink   = array();
		$this->accept = true;
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );
	}

	/**
	 * Cleanup.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
		remove_all_filters( 'course_captain_outbound_mode' );
		remove_all_filters( 'course_captain_notification_hold' );
		remove_all_filters( 'course_captain_mail_template_locales' );
		parent::tear_down();
	}

	/**
	 * Mail sink (MailSink, NTF-007).
	 *
	 * @param mixed                $value Short-circuit value.
	 * @param array<string, mixed> $atts  wp_mail attributes.
	 * @return bool
	 */
	public function catch_mail( $value, $atts ): bool {
		unset( $value );
		$this->sink[] = (array) $atts;
		return $this->accept;
	}

	/**
	 * Deliver outbox events, then run the send queue.
	 *
	 * @return array<string, int>
	 */
	private function run_all(): array {
		OutboxDispatcher::current()->dispatch();
		return NotificationService::current()->process_queue();
	}

	/**
	 * Notification rows.
	 *
	 * @param string $where Condition.
	 * @return list<array<string, mixed>>
	 */
	private static function rows( string $where = '1 = 1' ): array {
		$db = Database::current();
		return $db->rows( 'SELECT * FROM `' . $db->table( 'ccap_notification' ) . "` WHERE {$where} ORDER BY id" );
	}

	/**
	 * Grant for the learner.
	 *
	 * @param array<string, mixed> $options Options.
	 * @return int
	 */
	private function grant( array $options = array() ): int {
		return GrantService::current()->grant_manual( Actor::user( $this->admin ), $this->learner, $this->course, wp_generate_uuid4(), 'x', $options );
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 */
	private function assertRejected( callable $call, string $code ): void {
		try {
			$call();
			self::fail( 'expected ' . $code );
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code );
		}
	}

	/**
	 * Insert a row directly (queue fixtures).
	 *
	 * @param array<string, mixed> $data Columns.
	 * @return int
	 */
	private static function insert_row( array $data ): int {
		$db = Database::current();
		return $db->insert(
			'ccap_notification',
			$data + array(
				'uid'        => wp_generate_uuid4(),
				'dedupe_key' => hash( 'sha256', wp_generate_uuid4() ),
				'event_uuid' => wp_generate_uuid4(),
				'status'     => 'queued',
			),
			array( 'created_at' )
		);
	}

	/**
	 * Outbound mode filter.
	 *
	 * @param string $mode    Mode.
	 * @param string $address Catch address.
	 */
	private static function mode( string $mode, string $address = '' ): void {
		remove_all_filters( 'course_captain_outbound_mode' );
		add_filter( 'course_captain_outbound_mode', static fn(): array => array( 'mode' => $mode, 'catch_address' => $address, 'warning' => '' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	}

	/**
	 * Group with one invitation.
	 *
	 * @param int $seats Seats.
	 * @return array{group: int, seat_id: int, token: string}
	 */
	private function invitation( int $seats = 2 ): array {
		$group  = GroupService::current()->create( Actor::user( $this->admin ), $this->admin, 'Team <b>Blue</b>', $seats, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
		$invite = (array) GroupService::current()->invite( Actor::user( $this->admin ), $group, 'invitee-ntf@example.org' );
		return array(
			'group'   => $group,
			'seat_id' => (int) $invite['seat_id'],
			'token'   => (string) $invite['token'],
		);
	}

	/**
	 * Record `course.completed` for the learner (progress row completed).
	 *
	 * @return void
	 */
	private function complete_course(): void {
		$db = Database::current();
		$db->insert(
			'ccap_course_progress',
			array(
				'user_id'      => $this->learner,
				'course_id'    => $this->course,
				'status'       => 'completed',
				'completed_at' => $db->now(),
			),
			array( 'updated_at' )
		);
		$uid = (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_source_map' ) . "` WHERE object_type = 'course' AND object_id = %d", $this->course ) );
		Outbox::record(
			$db,
			'course.completed',
			'course_completion',
			$this->course . ':' . $this->learner . ':1',
			1,
			array( 'completed_at' => $db->now() ),
			array(
				'user_id'    => $this->learner,
				'course_uid' => '' === $uid ? null : $uid,
			)
		);
	}

	/**
	 * NTF-001 / NTF-008 / NTF-007: invitation, claim and completion each send exactly one mail; a replayed event creates nothing new; sender inherited.
	 *
	 * @group NTF-001
	 * @group NTF-007
	 * @group NTF-008
	 */
	public function test_NTF_001_008_one_mail_per_event_and_replay(): void {
		$invite = $this->invitation();
		$this->run_all();
		self::assertCount( 1, $this->sink );
		self::assertSame( 'invitee-ntf@example.org', $this->sink[0]['to'] );
		self::assertStringContainsString( 'Team', (string) $this->sink[0]['subject'] );
		foreach ( (array) $this->sink[0]['headers'] as $header ) {
			self::assertStringStartsNotWith( 'From:', (string) $header, 'sender inherited from WordPress' );
			self::assertStringStartsNotWith( 'List-Unsubscribe', (string) $header, 'required mail without unsubscribe' );
		}
		// The token is created in the send step (E-112): the one returned by invite() is no longer valid.
		self::assertSame( 1, preg_match( '/ccap_claim=(cci_[A-Za-z0-9_-]+)/', (string) $this->sink[0]['message'], $m ) );
		self::assertNotSame( $invite['token'], $m[1] );
		$db = Database::current();
		self::assertStringNotContainsString( $m[1], (string) wp_json_encode( self::rows() ), 'no token in the log' );

		$seat = GroupService::current()->claim( Actor::user( $this->learner ), $m[1] );
		self::assertSame( $invite['seat_id'], $seat );
		$this->run_all();
		self::assertCount( 2, $this->sink, 'seat_claimed only; the seat grant does not add an access mail' );
		self::assertSame( 'learner-ntf-' . $this->tag . '@example.org', $this->sink[1]['to'] );

		$this->complete_course();
		$this->run_all();
		self::assertCount( 3, $this->sink );
		self::assertSame( array( 'seat_invitation', 'seat_claimed', 'course_completed' ), array_column( self::rows(), 'type' ) );
		self::assertSame( array( 'sent', 'sent', 'sent' ), array_column( self::rows(), 'status' ) );

		// NTF-008: replay of every event with the same ID.
		$db->execute( 'UPDATE `' . $db->table( 'ccap_outbox_delivery' ) . "` SET status = 'pending', next_attempt_at = NULL WHERE subscriber_id = 'notification'" );
		$this->run_all();
		self::assertCount( 3, self::rows(), 'one row per business key' );
		self::assertCount( 3, $this->sink, 'no second mail' );
		$dump = (string) wp_json_encode( self::rows() );
		self::assertStringNotContainsString( 'example.org', $dump, 'no address in the log (N-7)' );
	}

	/**
	 * Access granted (N-01) with unsubscribe headers; NTF-010 revoked before send.
	 *
	 * @group NTF-010
	 * @group NTF-014
	 */
	public function test_access_granted_and_NTF_010_state_changed(): void {
		$grant = $this->grant();
		$this->run_all();
		self::assertSame( array( 'access_granted' ), array_column( self::rows(), 'type' ) );
		self::assertCount( 1, $this->sink );
		$headers = implode( "\n", (array) $this->sink[0]['headers'] );
		self::assertStringContainsString( 'List-Unsubscribe: <', $headers );
		self::assertStringContainsString( 'List-Unsubscribe-Post: List-Unsubscribe=One-Click', $headers );
		self::assertStringContainsString( 'Course N', (string) $this->sink[0]['subject'] );

		// Second course: event processed, then revoked before the send run.
		$second = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'Course M',
			)
		);
		$other  = GrantService::current()->grant_manual( Actor::user( $this->admin ), $this->learner, $second, wp_generate_uuid4(), 'x' );
		OutboxDispatcher::current()->dispatch();
		GrantService::current()->transition( Actor::user( $this->admin ), $other, 'revoked', 'refund', true );
		NotificationService::current()->process_queue();
		$row = self::rows( "status = 'suppressed'" );
		self::assertCount( 1, $row );
		self::assertSame( 'state_changed', $row[0]['reason_code'] );
		self::assertCount( 1, $this->sink, 'no mail after revocation' );
		self::assertGreaterThan( 0, $grant );
	}

	/**
	 * NTF-002: access expired is off by default; switched on: exactly one mail after expiry, no buy link, no reminder before.
	 *
	 * @group NTF-002
	 */
	public function test_NTF_002_access_expired(): void {
		$db    = Database::current();
		$grant = $this->grant( array( 'valid_until' => $db->now( 3600 ) ) );
		$this->run_all();
		$this->sink = array();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . '` SET valid_until = %s WHERE id = %d', $db->now( -60 ), $grant ) );
		GrantService::current()->expire_due();
		$this->run_all();
		self::assertSame( array(), $this->sink, 'default off (E-217)' );
		self::assertSame( 'type_disabled', self::rows( "type = 'access_expired'" )[0]['reason_code'] );

		$types                   = (array) Settings::get( 'emails.types' );
		$types['access_expired'] = true;
		Settings::save( Actor::user( $this->admin ), 'emails', array( 'types' => $types ), array( 'types' ), Settings::rev( 'emails' ) );
		$again = $this->grant( array( 'valid_until' => $db->now( 3600 ) ) );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . '` SET valid_until = %s WHERE id = %d', $db->now( -60 ), $again ) );
		GrantService::current()->expire_due();
		$this->run_all();
		$expired = array_values( array_filter( $this->sink, static fn( array $m ): bool => str_contains( (string) $m['subject'], 'ended' ) ) );
		self::assertCount( 1, $expired );
		self::assertStringNotContainsStringIgnoringCase( 'buy', (string) $expired[0]['message'] );
		self::assertStringNotContainsStringIgnoringCase( 'renew', (string) $expired[0]['message'] );
	}

	/**
	 * NTF-003 / NTF-013 / NTF-012: transport errors retry with backoff, no double mail after success; 5 failures → failed, health; invalid address → failed at once.
	 *
	 * @group NTF-003
	 * @group NTF-012
	 * @group NTF-013
	 */
	public function test_NTF_003_012_013_errors_and_retry(): void {
		$this->accept = false;
		$id           = self::insert_row(
			array(
				'type'              => 'test_mail',
				'recipient_user_id' => $this->learner,
			)
		);
		$service      = NotificationService::current();
		$service->process_queue();
		$row = self::rows( 'id = ' . $id )[0];
		self::assertSame( 'retry_wait', $row['status'] );
		self::assertSame( 'short_circuit', $row['last_error_class'], 'pre_wp_mail false counts as transport' );
		self::assertSame( '1', (string) $row['attempts'] );
		self::assertGreaterThan( gmdate( 'Y-m-d H:i:s', time() + 250 ), (string) $row['next_attempt_at'], 'backoff 5 min' );
		$service->process_queue();
		self::assertCount( 1, $this->sink, 'not before the backoff' );

		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_notification' ) . '` SET next_attempt_at = %s WHERE id = %d', $db->now( -1 ), $id ) );
		$this->accept = true;
		$service->process_queue();
		$service->process_queue();
		self::assertSame( 'sent', self::rows( 'id = ' . $id )[0]['status'] );
		self::assertCount( 2, $this->sink, 'exactly one successful delivery' );

		// Five failures in a row.
		$this->accept = false;
		$bad          = self::insert_row(
			array(
				'type'              => 'test_mail',
				'recipient_user_id' => $this->admin,
			)
		);
		for ( $i = 0; $i < 6; $i++ ) {
			$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_notification' ) . '` SET next_attempt_at = %s WHERE id = %d', $db->now( -1 ), $bad ) );
			$service->process_queue();
		}
		$row = self::rows( 'id = ' . $bad )[0];
		self::assertSame( 'failed', $row['status'] );
		self::assertSame( '5', (string) $row['attempts'] );
		self::assertSame( 'recommended', NotificationService::test_mail_health()['status'] );
		for ( $i = 0; $i < 9; $i++ ) {
			self::insert_row(
				array(
					'type'   => 'test_mail',
					'status' => 'failed',
				)
			);
		}
		self::assertSame( 'critical', NotificationService::test_mail_health()['status'], 'critical from 10 failed in 24 h' );

		// NTF-012: invalid address.
		$this->accept = true;
		$db->execute( $db->prepare( "UPDATE {$db->wpdb()->users} SET user_email = 'not-an-address' WHERE ID = %d", $this->learner ) );
		clean_user_cache( $this->learner );
		$invalid = self::insert_row(
			array(
				'type'              => 'test_mail',
				'recipient_user_id' => $this->learner,
			)
		);
		$service->process_queue();
		$row = self::rows( 'id = ' . $invalid )[0];
		self::assertSame( 'failed', $row['status'] );
		self::assertSame( 'invalid_recipient', $row['last_error_class'] );
		self::assertSame( '1', (string) $row['attempts'], 'no retry' );
	}

	/**
	 * NTF-004 / NTF-037 / NTF-015 / NTF-014: opt-out stops informational mails, required ones continue; only the person or a valid link changes it.
	 *
	 * @group NTF-004
	 * @group NTF-014
	 * @group NTF-015
	 * @group NTF-037
	 */
	public function test_NTF_004_014_015_opt_out(): void {
		$url = NotificationPrefs::unsubscribe_url( $this->learner, 'access_granted' );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $params );
		$params = array_map( 'strval', $params );

		$get = NotificationPrefs::handle( 'GET', $params, 0 );
		self::assertSame( 200, $get['status'] );
		self::assertStringContainsString( '<button type="submit"', $get['html'] );
		self::assertFalse( $get['changed'] );
		self::assertSame( array(), NotificationPrefs::optouts( $this->learner ), 'GET has no effect (link scanners)' );

		$wrong = NotificationPrefs::handle( 'POST', array( 's' => str_repeat( 'a', 64 ) ) + $params, 0 );
		self::assertSame( 403, $wrong['status'] );
		self::assertSame( array(), NotificationPrefs::optouts( $this->learner ), 'wrong signature changes nothing' );

		$post = NotificationPrefs::handle( 'POST', $params, 0 );
		self::assertTrue( $post['changed'] );
		self::assertSame( array( 'access_granted' ), NotificationPrefs::optouts( $this->learner ) );

		// NTF-015: the operator cannot lift it; required types cannot be unsubscribed.
		$this->assertRejected( fn() => NotificationPrefs::set( Actor::user( $this->admin ), $this->learner, 'access_granted', false, 'account' ), 'ccap_forbidden' );
		$this->assertRejected( fn() => NotificationPrefs::set( Actor::cron(), $this->learner, 'access_granted', false, 'link' ), 'ccap_forbidden' );
		$this->assertRejected( fn() => NotificationPrefs::set( Actor::user( $this->learner ), $this->learner, 'seat_invitation', true, 'account' ), 'ccap_invalid_param' );
		self::assertTrue( NotificationPrefs::set( Actor::user( $this->learner ), $this->learner, 'course_completed', true, 'account' ) );
		$audit = Database::current()->scalar( 'SELECT COUNT(*) FROM `' . Database::current()->table( 'ccap_audit_log' ) . "` WHERE action = 'notification_optout_changed'" );
		self::assertSame( '2', $audit );

		$this->grant();
		$this->complete_course();
		self::insert_row(
			array(
				'type'              => 'test_mail',
				'recipient_user_id' => $this->learner,
			)
		);
		$this->run_all();
		self::assertSame( array( 'opt_out', 'opt_out' ), array_column( self::rows( "status = 'suppressed'" ), 'reason_code' ) );
		self::assertCount( 1, $this->sink, 'required mail still delivered' );

		$account = NotificationPrefs::handle( 'GET', array(), $this->learner );
		self::assertStringContainsString( 'name="keep_access_granted"', $account['html'] );
		self::assertStringNotContainsString( 'keep_seat_invitation', $account['html'], 'required types not offered' );
	}

	/**
	 * NTF-005: invitation mails per group are limited to max(20, 2 × seats) per 24 h, fail-closed.
	 *
	 * @group NTF-005
	 */
	public function test_NTF_005_group_invitation_limit(): void {
		$invite = $this->invitation( 10 );
		$db     = Database::current();
		for ( $i = 0; $i < 20; $i++ ) {
			$seat = $db->insert(
				'ccap_seat',
				array(
					'group_id' => $invite['group'],
					'status'   => 'removed',
				),
				array( 'created_at', 'updated_at' )
			);
			self::insert_row(
				array(
					'type'          => 'seat_invitation',
					'recipient_ref' => 'seat:' . $seat,
					'status'        => 'sent',
					'sent_at'       => $db->now(),
				)
			);
		}
		$this->run_all();
		self::assertSame( array(), $this->sink );
		self::assertSame( 'rate_limited', self::rows( "recipient_ref = 'seat:" . $invite['seat_id'] . "'" )[0]['reason_code'] );
	}

	/**
	 * NTF-006 / NTF-017 / NTF-016: catch, off and hold mode; held mails are discarded by default, released on request, expired after 30 days.
	 *
	 * @group NTF-006
	 * @group NTF-016
	 * @group NTF-017
	 */
	public function test_NTF_006_016_017_outbound_modes(): void {
		self::assertSame( 'catch', OutboundMode::resolve( null, 'staging', null, 'admin@example.org' )['mode'] );
		self::assertSame( 'on', OutboundMode::resolve( null, 'production', null, 'admin@example.org' )['mode'] );
		self::assertSame( 'admin@example.org', OutboundMode::resolve( 'catch', 'production', null, 'admin@example.org' )['catch_address'] );
		$invalid = OutboundMode::resolve( 'catch', 'production', 'nope', 'admin@example.org' );
		self::assertSame( array( 'off', 'invalid_catch_address' ), array( $invalid['mode'], $invalid['warning'] ) );
		self::assertSame( 'off', OutboundMode::resolve( 'off', 'staging', null, 'admin@example.org' )['mode'] );

		self::mode( 'catch', 'catch-ntf@example.org' );
		$this->grant();
		$this->run_all();
		self::assertCount( 1, $this->sink );
		self::assertSame( 'catch-ntf@example.org', $this->sink[0]['to'], 'only the catch address' );
		self::assertStringStartsWith( '[catch] ', (string) $this->sink[0]['subject'] );
		self::assertContains( 'X-CC-Recipient-Ref: user:' . $this->learner, (array) $this->sink[0]['headers'] );
		self::assertSame( 'caught', self::rows()[0]['status'] );

		self::mode( 'off' );
		$off = self::insert_row(
			array(
				'type'              => 'test_mail',
				'recipient_user_id' => $this->learner,
			)
		);
		NotificationService::current()->process_queue();
		self::assertSame( 'outbound_off', self::rows( 'id = ' . $off )[0]['reason_code'] );
		self::assertCount( 1, $this->sink );

		// Hold mode: 3 mails held, nothing rendered.
		self::mode( 'on' );
		add_filter( 'course_captain_notification_hold', '__return_true' );
		for ( $i = 0; $i < 3; $i++ ) {
			self::insert_row(
				array(
					'type'              => 'test_mail',
					'recipient_user_id' => $this->learner,
				)
			);
		}
		NotificationService::current()->process_queue();
		self::assertCount( 3, self::rows( "status = 'held'" ) );
		self::assertCount( 1, $this->sink );
		remove_all_filters( 'course_captain_notification_hold' );
		$this->assertRejected( fn() => NotificationService::current()->discard_held( Actor::user( $this->learner ) ), 'ccap_forbidden' );
		self::assertSame( 3, NotificationService::current()->discard_held( Actor::user( $this->admin ) ), 'default action discards (E-88)' );

		add_filter( 'course_captain_notification_hold', '__return_true' );
		$release = self::insert_row(
			array(
				'type'              => 'test_mail',
				'recipient_user_id' => $this->learner,
			)
		);
		$old     = self::insert_row(
			array(
				'type'              => 'test_mail',
				'recipient_user_id' => $this->learner,
			)
		);
		NotificationService::current()->process_queue();
		remove_all_filters( 'course_captain_notification_hold' );
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_notification' ) . '` SET created_at = %s WHERE id = %d', $db->now( -31 * DAY_IN_SECONDS ), $old ) );
		NotificationService::current()->hourly();
		self::assertSame( 'held_expired', self::rows( 'id = ' . $old )[0]['reason_code'] );
		self::assertSame( 1, NotificationService::current()->release_held( Actor::user( $this->admin ) ) );
		NotificationService::current()->process_queue();
		self::assertSame( 'sent', self::rows( 'id = ' . $release )[0]['status'] );
		self::assertCount( 2, $this->sink );
	}

	/**
	 * NTF-009: lease expired after wp_mail → unknown, no automatic second mail; admin re-send creates a new row.
	 *
	 * @group NTF-009
	 */
	public function test_NTF_009_unknown_and_resend(): void {
		$db = Database::current();
		$id = self::insert_row(
			array(
				'type'              => 'test_mail',
				'recipient_user_id' => $this->learner,
				'status'            => 'sending',
				'lock_owner'        => 'crashed',
				'locked_until'      => $db->now( -1 ),
			)
		);
		NotificationService::current()->process_queue();
		self::assertSame( 'unknown', self::rows( 'id = ' . $id )[0]['status'] );
		NotificationService::current()->process_queue();
		self::assertSame( array(), $this->sink, 'no automatic re-send' );

		$this->assertRejected( fn() => NotificationService::current()->resend( Actor::user( $this->learner ), $id ), 'ccap_forbidden' );
		$new = NotificationService::current()->resend( Actor::user( $this->admin ), $id );
		NotificationService::current()->process_queue();
		self::assertSame( (string) $id, (string) self::rows( 'id = ' . $new )[0]['resend_of'] );
		self::assertSame( 'sent', self::rows( 'id = ' . $new )[0]['status'] );
		self::assertCount( 1, $this->sink );
		$this->assertRejected( fn() => NotificationService::current()->resend( Actor::user( $this->admin ), $new ), 'ccap_conflict' );
	}

	/**
	 * NTF-011: recipient deleted before sending → rows erased, no mail.
	 *
	 * @group NTF-011
	 */
	public function test_NTF_011_recipient_deleted(): void {
		$this->grant();
		OutboxDispatcher::current()->dispatch();
		self::assertCount( 1, self::rows() );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $this->learner );
		NotificationService::current()->process_queue();
		self::assertSame( array(), self::rows() );
		self::assertSame( array(), $this->sink );
	}

	/**
	 * NTF-018: test mail only to the own address; sixth in an hour refused.
	 *
	 * @group NTF-018
	 */
	public function test_NTF_018_test_mail(): void {
		$service = NotificationService::current();
		$this->assertRejected( fn() => $service->send_test_mail( Actor::user( $this->learner ) ), 'ccap_forbidden' );
		for ( $i = 0; $i < 5; $i++ ) {
			self::assertSame( 'sent', $service->send_test_mail( Actor::user( $this->admin ) )['status'] );
		}
		self::assertSame( array( 'admin-ntf-' . $this->tag . '@example.org' ), array_values( array_unique( array_column( $this->sink, 'to' ) ) ) );
		$this->assertRejected( fn() => $service->send_test_mail( Actor::user( $this->admin ) ), 'ccap_rate_limited' );
	}

	/**
	 * NTF-019 / NTF-020: recipient language, lang/dir, locale restored after an error; variant resolution.
	 *
	 * @group NTF-019
	 * @group NTF-020
	 */
	public function test_NTF_019_020_locales(): void {
		add_filter( 'course_captain_mail_template_locales', static fn(): array => array( 'en_US', 'de_DE', 'ar' ) );
		$admin = Actor::user( $this->admin );
		MailTemplates::save( $admin, 'test_mail', 'de_DE', 'Testnachricht von {{site_name}}', '<p>Hallo {{recipient_display_name}}</p>' );
		MailTemplates::save( $admin, 'test_mail', 'ar', 'رسالة', '<p>مرحبا {{recipient_display_name}}</p>' );

		self::assertSame( 'de_DE', MailTemplates::variant( 'test_mail', 'de_CH' )[0] ?? null, 'same language' );
		self::assertNull( MailTemplates::variant( 'test_mail', 'fr_FR' ), 'no variant: built-in text' );
		MailTemplates::save( $admin, 'test_mail', 'en_US', 'Operator test', '<p>Operator {{site_name}}</p>' );
		self::assertSame( 'en_US', MailTemplates::variant( 'test_mail', 'fr_FR' )[0] ?? null, 'operator default variant' );

		$service = NotificationService::current();
		$before  = determine_locale();
		$vars    = MailTemplates::site_vars() + array( 'recipient_display_name' => 'Lea' );
		$de      = $service->render( 'test_mail', 'de_DE', $this->learner, $vars );
		self::assertSame( array( 'de-DE', 'ltr' ), array( $de['lang'], $de['dir'] ) );
		self::assertStringContainsString( 'Testnachricht', $de['subject'] );
		update_option(
			MailTemplates::OPTION_PREFIX . 'grading_completed',
			array(
				'en_US' => array(
					'subject'   => 'x {{evil}}',
					'body_html' => '<p>x</p>',
				),
			)
		);
		try {
			$service->render( 'grading_completed', 'en_US', $this->learner, $vars );
			self::fail( 'template error expected' );
		} catch ( TemplateError $e ) {
			self::assertSame( $before, determine_locale(), 'locale restored after the exception' );
		}
		$ar = $service->render( 'test_mail', 'ar', $this->learner, $vars );
		self::assertSame( array( 'ar', 'rtl' ), array( $ar['lang'], $ar['dir'] ) );
		self::assertStringContainsString( 'lang="ar" dir="rtl"', $ar['html'] );
		self::assertSame( $before, determine_locale() );
		self::assertSame( 'en_US', $service->render( 'test_mail', 'en_US', $this->learner, $vars, true )['locale'], 'built-in fallback' );
	}

	/**
	 * NTF-021 / NTF-023 / NTF-022: hostile course titles stay inert; HTML and text are content-equal; no points in grading mails.
	 *
	 * @group NTF-021
	 * @group NTF-022
	 * @group NTF-023
	 */
	public function test_NTF_021_022_023_content(): void {
		$db = Database::current();
		$db->execute( $db->prepare( "UPDATE {$db->wpdb()->posts} SET post_title = %s WHERE ID = %d", "<b>Evil</b>\r\nBcc: spy@example.org", $this->course ) );
		clean_post_cache( $this->course );
		$this->grant();
		$this->run_all();
		self::assertCount( 1, $this->sink );
		$mail = $this->sink[0];
		self::assertStringNotContainsString( "\n", (string) $mail['subject'] );
		self::assertStringNotContainsString( "\r", (string) $mail['subject'] );
		self::assertStringNotContainsString( '<b>', (string) $mail['message'] );
		foreach ( (array) $mail['headers'] as $header ) {
			// A Bcc header name, also after an injected line break; not the letters "bcc" inside a token (List-Unsubscribe HMAC).
			self::assertDoesNotMatchRegularExpression( '/^\s*bcc\s*:/im', (string) $header );
		}
		self::assertStringContainsString( '<bdi>', (string) $mail['message'] );

		// NTF-023: multipart parts carry the same content.
		$vars     = MailTemplates::site_vars() + array(
			'recipient_display_name' => 'Lea & Co',
			'course_title'           => 'C <i>x</i>',
			'course_url'             => 'https://example.org/lernen/kurs/abc/',
			'unsubscribe_url'        => 'https://example.org/lernen/benachrichtigungen/?u=1',
		);
		$rendered = MailTemplates::render( 'access_granted', 'en_US', $vars );
		$norm     = static fn( string $s ): string => trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( $s, ENT_QUOTES, 'UTF-8' ) ) );
		preg_match( '#<div[^>]*>(.*)</div>#s', $rendered['html'], $body );
		self::assertSame( $norm( $rendered['text'] ), $norm( wp_strip_all_tags( str_replace( '</p>', '</p> ', (string) ( $body[1] ?? '' ) ) ) ) );
		self::assertSame( $rendered['text'], wp_strip_all_tags( $rendered['text'] ), 'text part without markup' );

		// NTF-022: grading mail without points or answers.
		$db->execute( 'SET FOREIGN_KEY_CHECKS = 0' );
		$session = wp_generate_uuid4();
		$db->insert(
			'ccap_exam_session',
			array(
				'uid'              => $session,
				'user_id'          => $this->learner,
				'course_id'        => $this->course,
				'rule_id'          => 1,
				'mode'             => 'exam',
				'form_key'         => 'f',
				'status'           => 'evaluated',
				'start_token'      => wp_generate_uuid4(),
				'snapshot'         => '{}',
				'snapshot_hash'    => 'sha256:' . str_repeat( '0', 64 ),
				'evaluation_no'    => 2,
				'started_at'       => $db->now(),
				'points_milli'     => 7345,
				'max_points_milli' => 9000,
			)
		);
		$db->execute( 'SET FOREIGN_KEY_CHECKS = 1' );
		Outbox::record(
			$db,
			'exam.reevaluated',
			'exam_session',
			$session,
			2,
			array(
				'session_uid'      => $session,
				'points_milli'     => 7345,
				'max_points_milli' => 9000,
				'evaluation_no'    => 2,
			),
			array( 'user_id' => $this->learner )
		);
		$this->run_all();
		$grading = end( $this->sink );
		self::assertIsArray( $grading );
		self::assertStringContainsString( 'graded', (string) $grading['subject'] );
		// Signatures and UIDs in links are random hex and may contain digit runs: strip them before checking.
		$text = (string) preg_replace( '/[0-9a-f]{8}-[0-9a-f-]{27}|[0-9a-f]{32,}/', '', (string) $grading['message'] . (string) $grading['subject'] );
		foreach ( array( '7345', '7.3', '9000', '81.6', '81 %' ) as $forbidden ) {
			self::assertStringNotContainsString( $forbidden, $text );
		}
	}

	/**
	 * NTF-025: eleven mails to one recipient in 24 h: informational suppressed, required deferred.
	 *
	 * @group NTF-025
	 */
	public function test_NTF_025_per_recipient_limit(): void {
		$db = Database::current();
		for ( $i = 0; $i < 10; $i++ ) {
			self::insert_row(
				array(
					'type'              => 'course_completed',
					'recipient_user_id' => $this->learner,
					'status'            => 'sent',
					'sent_at'           => $db->now( -3600 ),
				)
			);
		}
		$this->grant();
		$this->run_all();
		self::assertSame( 'rate_limited', self::rows( "type = 'access_granted'" )[0]['reason_code'] );

		$invite = $this->invitation();
		for ( $i = 0; $i < 10; $i++ ) {
			self::insert_row(
				array(
					'type'          => 'seat_invitation',
					'recipient_ref' => 'seat:' . $invite['seat_id'],
					'status'        => 'sent',
					'sent_at'       => $db->now( -3600 ),
				)
			);
		}
		$this->run_all();
		$row = self::rows( "type = 'seat_invitation' AND status <> 'sent'" )[0];
		self::assertSame( array( 'retry_wait', 'rate_deferred' ), array( $row['status'], $row['reason_code'] ) );
		self::assertGreaterThan( $db->now( 20 * HOUR_IN_SECONDS ), (string) $row['next_attempt_at'], 'next window' );
		self::assertSame( array(), $this->sink );
	}

	/**
	 * NTF-026 / NTF-033 / GM-159 (batch part): 50 recipients per run, continuation, no double mail, a failing recipient does not stop the run.
	 *
	 * @group NTF-026
	 * @group NTF-033
	 * @group GM-159
	 */
	public function test_NTF_026_batches(): void {
		$users = self::factory()->user->create_many( 3 );
		for ( $i = 0; $i < 60; $i++ ) {
			self::insert_row(
				array(
					'type'              => 'test_mail',
					'recipient_user_id' => 0 === $i ? 999999 : (int) $users[ $i % 3 ],
				)
			);
		}
		$first = NotificationService::current()->process_queue();
		self::assertSame( 49, $first['sent'] );
		self::assertSame( 1, $first['failed'], 'deleted account fails alone' );
		self::assertSame( 10, $first['remaining'] );
		self::assertNotFalse( wp_next_scheduled( CronHooks::TASK_CONTINUE, array( NotificationService::TASK, 0 ) ), 'continuation planned' );
		NotificationService::run_task( NotificationService::TASK, 0 );
		self::assertCount( 59, $this->sink );
		self::assertSame( 0, NotificationService::current()->process_queue()['sent'], 'nothing twice' );
		self::assertCount( 59, self::rows( "status = 'sent'" ) );
	}

	/**
	 * NTF-028: operator switches course_completed off; a manipulated save cannot switch the invitation off.
	 *
	 * @group NTF-028
	 * @group SET-102
	 */
	public function test_NTF_028_types_setting(): void {
		$admin = Actor::user( $this->admin );
		$this->assertRejected( fn() => Settings::save( $admin, 'emails', array( 'types' => array( 'seat_invitation' => false ) ), array( 'types' ), Settings::rev( 'emails' ) ), 'ccap_validation_failed' );
		Settings::save( $admin, 'emails', array( 'types' => array( 'course_completed' => false ) ), array( 'types' ), Settings::rev( 'emails' ) );
		self::assertFalse( Settings::get( 'emails.types' )['course_completed'] );
		self::assertTrue( Settings::get( 'emails.types' )['access_granted'], 'missing key keeps its default' );
		$this->complete_course();
		$this->invitation();
		$this->run_all();
		self::assertSame( 'type_disabled', self::rows( "type = 'course_completed'" )[0]['reason_code'] );
		self::assertSame( array( 'invitee-ntf@example.org' ), array_column( $this->sink, 'to' ) );
		self::assertSame( NotificationTypes::informational(), array_keys( NotificationTypes::settings_field()['fields'] ) );
	}

	/**
	 * NTF-029: re-sending an invitation: third time allowed, fourth refused; each re-send rotates the token.
	 *
	 * @group NTF-029
	 */
	public function test_NTF_029_invitation_resend(): void {
		$invite  = $this->invitation();
		$service = NotificationService::current();
		$this->run_all();
		$tokens = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$service->resend_invitation( Actor::user( $this->admin ), $invite['seat_id'] );
			$service->process_queue();
		}
		$this->assertRejected( fn() => $service->resend_invitation( Actor::user( $this->admin ), $invite['seat_id'] ), 'ccap_rate_limited' );
		// Foreign groups are hidden (IDOR: 404 instead of 403).
		$this->assertRejected( fn() => $service->resend_invitation( Actor::user( $this->learner ), $invite['seat_id'] ), 'ccap_not_found' );
		foreach ( $this->sink as $mail ) {
			preg_match( '/ccap_claim=(cci_[A-Za-z0-9_-]+)/', (string) $mail['message'], $m );
			$tokens[] = $m[1] ?? '';
		}
		self::assertCount( 4, array_unique( $tokens ) );
		$this->assertRejected( fn() => GroupService::current()->claim( Actor::user( $this->learner ), $tokens[0] ), 'ccap_invalid_token' );
		self::assertSame( $invite['seat_id'], GroupService::current()->claim( Actor::user( $this->learner ), $tokens[3] ) );
		self::assertSame( '3', Database::current()->scalar( 'SELECT COUNT(*) FROM `' . Database::current()->table( 'ccap_audit_log' ) . "` WHERE action = 'invitation_resent'" ) );
	}

	/**
	 * NTF-030 / NTF-032: export (type, status, time, preferences), eraser, retention 90 days.
	 *
	 * @group NTF-030
	 * @group NTF-032
	 */
	public function test_NTF_030_032_privacy_and_retention(): void {
		NotificationPrefs::set( Actor::user( $this->learner ), $this->learner, 'access_granted', true, 'account' );
		$old = self::insert_row(
			array(
				'type'              => 'course_completed',
				'recipient_user_id' => $this->learner,
				'status'            => 'sent',
			)
		);
		self::insert_row(
			array(
				'type'              => 'seat_claimed',
				'recipient_user_id' => $this->learner,
				'status'            => 'sent',
			)
		);
		$export = PrivacyService::export( 'learner-ntf-' . $this->tag . '@example.org' );
		$groups = array_column( $export['data'], 'group_id' );
		self::assertContains( 'cc-notifications', $groups );
		self::assertContains( 'cc-notification-prefs', $groups );
		$names = array();
		foreach ( $export['data'] as $item ) {
			if ( 'cc-notifications' === $item['group_id'] ) {
				$names = array_column( $item['data'], 'name' );
			}
		}
		self::assertSame( array( 'type', 'status', 'created_at', 'sent_at' ), $names );

		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_notification' ) . '` SET created_at = %s WHERE id = %d', $db->now( -91 * DAY_IN_SECONDS ), $old ) );
		self::assertSame( 1, RetentionService::run()['RT-30_notifications'] );
		self::assertCount( 1, self::rows() );

		PrivacyService::erase( 'learner-ntf-' . $this->tag . '@example.org' );
		self::assertSame( array(), self::rows() );
	}

	/**
	 * NTF-031: sender override only for Course Captain mails (real wp_mail with the test mailer).
	 *
	 * @group NTF-031
	 */
	public function test_NTF_031_sender_override(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
		reset_phpmailer_instance();
		Settings::save(
			Actor::user( $this->admin ),
			'emails',
			array(
				'from_name'    => 'Akademie',
				'from_address' => 'akademie@example.org',
			),
			array( 'from_name', 'from_address' ),
			Settings::rev( 'emails' )
		);
		self::assertSame( 'sent', NotificationService::current()->send_test_mail( Actor::user( $this->admin ) )['status'] );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer API.
		$mailer = tests_retrieve_phpmailer_instance();
		self::assertSame( 'akademie@example.org', $mailer->From );
		self::assertSame( 'Akademie', $mailer->FromName );
		self::assertNotSame( '', $mailer->AltBody, 'multipart: text part' );
		reset_phpmailer_instance();
		wp_mail( 'other@example.org', 'Other plugin', 'x' );
		self::assertNotSame( 'akademie@example.org', tests_retrieve_phpmailer_instance()->From, 'other mails keep the WordPress sender' );
		// phpcs:enable
	}

	/**
	 * NTF-034 / SET-103: template save with allowlist, kses, audit; preview with sample data.
	 *
	 * @group NTF-034
	 * @group SET-103
	 */
	public function test_NTF_034_templates(): void {
		$admin = Actor::user( $this->admin );
		$this->assertRejected( fn() => MailTemplates::save( Actor::user( $this->learner ), 'access_granted', 'en_US', 'S', '<p>B</p>' ), 'ccap_forbidden' );
		try {
			MailTemplates::save( $admin, 'access_granted', 'en_US', 'Hi {{course_title}}', '<p>{{score}}</p>' );
			self::fail( 'unknown placeholder' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_failed', $e->error_code );
			self::assertStringContainsString( 'score', $e->getMessage() );
			self::assertStringContainsString( 'course_url', $e->getMessage(), 'allowed list in the message' );
		}
		$this->assertRejected( fn() => MailTemplates::save( $admin, 'access_granted', 'xx_XX', 'S', '<p>B</p>' ), 'ccap_invalid_param' );
		$this->assertRejected( fn() => MailTemplates::save( $admin, 'seat_invitation', 'en_US', 'S {{recipient_display_name}}', '<p>B</p>' ), 'ccap_validation_failed' );
		self::assertSame( 1, MailTemplates::save( $admin, 'access_granted', 'en_US', "Welcome\r\nBcc: x {{course_title}}", '<p>Go: <a href="{{course_url}}">{{course_title}}</a></p><script>alert(1)</script><img src="x">' ) );
		$stored = MailTemplates::stored( 'access_granted' )['en_US'];
		self::assertStringNotContainsString( '<script', $stored['body_html'] );
		self::assertStringNotContainsString( '<img', $stored['body_html'] );
		self::assertStringNotContainsString( "\n", $stored['subject'] );
		self::assertSame( 1, MailTemplates::save( $admin, 'access_granted', 'en_US', $stored['subject'], $stored['body_html'] ), 'unchanged text keeps the revision' );
		$db = Database::current();
		self::assertSame( '1', $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'mail_template_saved'" ) );

		$preview = MailTemplates::preview( $admin, 'access_granted', 'en_US' );
		self::assertStringContainsString( 'Sample course', $preview['subject'] );
		self::assertStringContainsString( 'href="http://example.org/"', $preview['html'] );
		self::assertSame( 1, $preview['template_rev'] );
		$builtin = MailTemplates::preview( $admin, 'seat_invitation', 'en_US' );
		self::assertNull( $builtin['template_rev'] );
		self::assertStringContainsString( 'Sample group', $builtin['subject'] );
		foreach ( NotificationTypes::all() as $type ) {
			self::assertNotSame( '', MailTemplates::preview( $admin, $type, 'en_US' )['text'], $type );
		}
	}
}
