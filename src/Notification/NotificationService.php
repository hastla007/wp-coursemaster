<?php
/**
 * Event-driven notifications (R0-27 §3, E-187; T27-7).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Notification;

use CourseCaptain\Admin\Notices;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\StorageException;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Internal outbox subscriber `notification`. Per event, type and
 * recipient at most one row in `ccap_notification` (UNIQUE `dedupe_key` =
 * sha256(type:recipient_ref:business_key)); the row holds metadata only,
 * never address, subject or text (N-7, §3.3).
 *
 * Sending (§3.3–§3.8): CAS `queued/retry_wait → sending` with a 10 min
 * lease before `wp_mail` (V-3); a lease that runs out without result
 * becomes `unknown` and is never re-sent automatically (V-4). Before each
 * attempt the state is checked again (N-2): hold mode → `held`, outbound
 * `off` → `suppressed (outbound_off)`, state/recipient/opt-out/type
 * switched off/rate limit → `suppressed` or `failed`. Transport errors
 * retry after 5 min, 30 min, 2 h, 12 h and end `failed` after 5 attempts
 * (E-218). Runs handle at most 50 recipients or 20 s and continue after
 * 60 s via `ccap_task_continue` (LB INV-B-070); one failing recipient never
 * stops the run.
 */
final class NotificationService {

	public const SUBSCRIBER          = 'notification';
	public const EVENTS              = array( 'access.changed', 'grant.expired', 'seat.invited', 'seat.claimed', 'course.completed', 'exam.reevaluated', 'assignment.submitted', 'assignment.returned', 'assignment.graded' );
	public const TASK                = 'notification_send';
	public const LEASE_SECONDS       = 600;
	public const BATCH               = 50;
	public const TIME_BUDGET         = 20;
	public const CONTINUE_AFTER      = 60;
	public const MAX_ATTEMPTS        = 5;
	public const PER_RECIPIENT_24H   = 10;
	public const TEST_MAILS_PER_HOUR = 5;
	public const INVITE_RESENDS      = 3;
	public const HELD_DAYS           = 30;
	public const FAILED_CRITICAL     = 10;
	public const STATE_OPTION        = 'course_captain_mail_state';
	private const BACKOFF            = array( 300, 1800, 7200, 43200 );

	/**
	 * Constructor.
	 *
	 * @param Database $db Database access.
	 */
	public function __construct( private Database $db ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current() );
	}

	/**
	 * Hooks: outbox subscriber and handler, batch continuation, Site Health, admin notice.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter(
			'course_captain_internal_subscribers',
			static function ( $subscribers ) {
				if ( is_array( $subscribers ) ) {
					$subscribers[ self::SUBSCRIBER ] = self::EVENTS;
				}
				return $subscribers;
			}
		);
		add_filter(
			'course_captain_internal_subscriber_handlers',
			static function ( $handlers ) {
				if ( is_array( $handlers ) ) {
					$handlers[ self::SUBSCRIBER ] = array( self::class, 'handle_event' );
				}
				return $handlers;
			}
		);
		add_action( CronHooks::TASK_CONTINUE, array( self::class, 'run_task' ), 10, 2 );
		add_filter( 'site_status_tests', array( self::class, 'health_tests' ) );
		add_action( 'admin_init', array( self::class, 'admin_notice' ) );
		NotificationPrefs::register();
	}

	/**
	 * Outbox handler: create the notification rows of an event (no sending here).
	 *
	 * @param array<string, mixed> $envelope Event envelope v1.
	 * @return void
	 */
	public static function handle_event( array $envelope ): void {
		if ( self::current()->record_event( $envelope ) > 0 ) {
			self::schedule( 5 );
		}
	}

	/**
	 * `ccap_task_continue` handler for task `notification_send`.
	 *
	 * @param mixed $task   Task.
	 * @param mixed $cursor Unused.
	 * @return void
	 */
	public static function run_task( $task, $cursor = 0 ): void {
		unset( $cursor );
		if ( self::TASK === $task ) {
			self::current()->process_queue();
		}
	}

	/**
	 * Schedule a send run once.
	 *
	 * @param int $delay Seconds.
	 * @return void
	 */
	public static function schedule( int $delay ): void {
		if ( false === wp_next_scheduled( CronHooks::TASK_CONTINUE, array( self::TASK, 0 ) ) ) {
			wp_schedule_single_event( time() + $delay, CronHooks::TASK_CONTINUE, array( self::TASK, 0 ) );
		}
	}

	/**
	 * Rows for an event (V-1: a repeated event creates nothing new).
	 *
	 * @param array<string, mixed> $envelope Envelope.
	 * @return int New rows.
	 */
	public function record_event( array $envelope ): int {
		$type    = (string) ( $envelope['event_type'] ?? '' );
		$uuid    = (string) ( $envelope['event_id'] ?? '' );
		$payload = (array) ( $envelope['payload'] ?? array() );
		$user    = 1 === preg_match( '/^user:(\d+)$/', (string) ( $envelope['user_ref'] ?? '' ), $m ) ? (int) $m[1] : 0;
		$course  = (string) ( $envelope['course_uid'] ?? $envelope['object_id'] ?? '' );
		$rows    = array();
		switch ( $type ) {
			case 'access.changed':
				if ( $user <= 0 ) {
					break;
				}
				// Business key: course + the access change event (object_version of access.changed is constant in R1-13).
				if ( ! empty( $payload['has_access'] ) && 'activated' === $this->access_cause( $user, $this->course_id( $envelope ) ) ) {
					$rows[] = array( 'access_granted', $user, null, $course . ':' . $uuid );
				}
				break;
			case 'grant.expired':
				// Expiry by time never flips `access.changed` (access already ended before the cron transition):
				// N-12 follows the grant event; "still no access" is checked before sending.
				if ( $user > 0 ) {
					$rows[] = array( 'access_expired', $user, null, (string) ( $envelope['object_id'] ?? '' ) . ':' . (int) ( $envelope['object_version'] ?? 0 ) );
				}
				break;
			case 'seat.invited':
				$seat = (int) ( $payload['seat_id'] ?? 0 );
				if ( $seat > 0 ) {
					$rows[] = array( 'seat_invitation', null, 'seat:' . $seat, $seat . ':0' );
				}
				break;
			case 'seat.claimed':
				$seat = (int) ( $payload['seat_id'] ?? 0 );
				if ( $user > 0 && $seat > 0 ) {
					$rows[] = array( 'seat_claimed', $user, null, (string) $seat );
				}
				break;
			case 'course.completed':
				if ( $user > 0 ) {
					$rows[] = array( 'course_completed', $user, null, $course . ':' . (string) ( $payload['completed_at'] ?? '' ) );
				}
				break;
			case 'exam.reevaluated':
				if ( $user > 0 ) {
					$rows[] = array( 'grading_completed', $user, null, (string) ( $payload['session_uid'] ?? '' ) . ':' . (int) ( $payload['evaluation_no'] ?? 0 ) );
				}
				break;
			case 'assignment.submitted':
				// N-14 (E-290): each assigned trainer of the course covering the learner, once per version (NTF-035).
				$sub = (string) ( $payload['submission_uid'] ?? '' );
				foreach ( $this->assignment_trainers( (int) ( $payload['course_id'] ?? 0 ), $user ) as $trainer ) {
					$rows[] = array( 'assignment_submitted', $trainer, null, $sub . ':' . (int) ( $payload['submission_no'] ?? 0 ) );
				}
				break;
			case 'assignment.returned':
				if ( $user > 0 ) {
					$rows[] = array( 'assignment_returned', $user, null, (string) ( $payload['submission_uid'] ?? '' ) . ':returned' );
				}
				break;
			case 'assignment.graded':
				if ( $user > 0 ) {
					$rows[] = array( 'assignment_graded', $user, null, (string) ( $payload['submission_uid'] ?? '' ) . ':' . (int) ( $payload['evaluation_no'] ?? 0 ) );
				}
				break;
		}
		$created = 0;
		foreach ( $rows as $row ) {
			list( $ntype, $recipient, $ref, $business ) = $row;
			$locale                                     = null !== $recipient ? get_user_locale( $recipient ) : $this->inviter_locale( $envelope );
			$created                                   += null === $this->insert( $ntype, $uuid, (string) ( $envelope['correlation_id'] ?? '' ), $recipient, $ref, $business, $locale ) ? 0 : 1;
		}
		return $created;
	}

	/**
	 * Insert a row unless its dedupe key exists.
	 *
	 * @param string      $type        Type.
	 * @param string      $event_uuid  Event.
	 * @param string      $correlation Correlation ID.
	 * @param int|null    $user        Recipient user.
	 * @param string|null $ref         Recipient reference (`seat:<id>`).
	 * @param string      $business    Business key.
	 * @param string      $locale      Requested locale.
	 * @param int|null    $resend_of   Original row.
	 * @return int|null New ID, null for a duplicate.
	 * @throws StorageException On other database errors.
	 */
	private function insert( string $type, string $event_uuid, string $correlation, ?int $user, ?string $ref, string $business, string $locale, ?int $resend_of = null ): ?int {
		$recipient = null !== $user ? 'user:' . $user : (string) $ref;
		$quiet     = $this->db->wpdb()->suppress_errors( true );
		try {
			return $this->db->insert(
				'ccap_notification',
				array(
					'uid'               => $this->db->uuid(),
					'type'              => $type,
					'dedupe_key'        => hash( 'sha256', $type . ':' . $recipient . ':' . $business ),
					'event_uuid'        => '' === $event_uuid ? $this->db->uuid() : $event_uuid,
					'correlation_id'    => '' === $correlation ? null : $correlation,
					'recipient_user_id' => $user,
					'recipient_ref'     => $ref,
					'status'            => 'queued',
					'locale_requested'  => substr( $locale, 0, 20 ),
					'resend_of'         => $resend_of,
				),
				array( 'created_at' )
			);
		} catch ( StorageException $e ) {
			if ( ! $e->is_duplicate() ) {
				throw $e;
			}
			return null;
		} finally {
			$this->db->wpdb()->suppress_errors( $quiet );
		}
	}

	/**
	 * One send run: lease, check, render, send; at most 50 rows / 20 s.
	 *
	 * @param int $budget Time budget in seconds.
	 * @param int $batch  Rows per run.
	 * @return array{sent: int, caught: int, suppressed: int, held: int, failed: int, retry: int, unknown: int, remaining: int}
	 */
	public function process_queue( int $budget = self::TIME_BUDGET, int $batch = self::BATCH ): array {
		$stats = array(
			'sent'       => 0,
			'caught'     => 0,
			'suppressed' => 0,
			'held'       => 0,
			'failed'     => 0,
			'retry'      => 0,
			'unknown'    => $this->mark_unknown(),
			'remaining'  => 0,
		);
		$start = microtime( true );
		$table = $this->db->table( 'ccap_notification' );
		$ids   = $this->db->wpdb()->get_col( $this->db->prepare( "SELECT id FROM `{$table}` WHERE status IN ('queued', 'retry_wait') AND (next_attempt_at IS NULL OR next_attempt_at <= %s) ORDER BY id LIMIT %d", $this->db->now(), $batch ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		foreach ( $ids as $id ) {
			if ( microtime( true ) - $start > $budget ) {
				break;
			}
			$status = $this->send_one( (int) $id );
			if ( null === $status ) {
				continue;
			}
			$key = 'retry_wait' === $status ? 'retry' : $status;
			if ( in_array( $key, array( 'sent', 'caught', 'suppressed', 'held', 'failed', 'retry' ), true ) ) {
				++$stats[ $key ];
			}
		}
		if ( $stats['sent'] > 0 ) {
			// Irreversible outbound effect: a later restore of an older DB is detected (R1-23, R0-17 §5.5).
			\CourseCaptain\Kernel\SiteIdentity::bump_epoch();
		}
		$stats['remaining'] = (int) $this->db->scalar( $this->db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE status IN ('queued', 'retry_wait') AND (next_attempt_at IS NULL OR next_attempt_at <= %s)", $this->db->now() ) );
		if ( $stats['remaining'] > 0 ) {
			self::schedule( self::CONTINUE_AFTER );
		}
		return $stats;
	}

	/**
	 * Claim (CAS with lease) and process one row.
	 *
	 * @param int $id Row.
	 * @return string|null Final status of this attempt, null when another run had it.
	 */
	public function send_one( int $id ): ?string {
		$table = $this->db->table( 'ccap_notification' );
		$owner = substr( str_replace( '-', '', $this->db->uuid() ), 0, 32 );
		$now   = $this->db->now();
		$won   = $this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET status = 'sending', lock_owner = %s, locked_until = %s WHERE id = %d AND status IN ('queued', 'retry_wait') AND (next_attempt_at IS NULL OR next_attempt_at <= %s)", $owner, $this->db->now( self::LEASE_SECONDS ), $id, $now ) );
		if ( 1 !== $won ) {
			return null;
		}
		$row = (array) $this->db->row( $this->db->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) );
		try {
			return $this->attempt( $row, $owner );
		} catch ( Throwable $e ) {
			// One recipient never stops the run; unexpected errors count as transport errors.
			return $this->transport_failure( $row, $owner, 'transport' );
		}
	}

	/**
	 * One attempt of a claimed row.
	 *
	 * @param array<string, mixed> $row   Row.
	 * @param string               $owner Lease owner.
	 * @return string
	 */
	private function attempt( array $row, string $owner ): string {
		$type = (string) $row['type'];
		if ( OutboundMode::is_held() ) {
			return $this->finish( $row, $owner, 'held', array() );
		}
		$mode = OutboundMode::current();
		if ( OutboundMode::OFF === $mode['mode'] ) {
			return $this->finish( $row, $owner, 'suppressed', array( 'reason_code' => 'outbound_off' ) );
		}
		$ctx = $this->context( $row );
		if ( isset( $ctx['fail'] ) ) {
			return $this->finish(
				$row,
				$owner,
				'failed',
				array(
					'last_error_class' => (string) $ctx['fail'],
					'attempts'         => (int) $row['attempts'] + 1,
				)
			);
		}
		if ( isset( $ctx['suppress'] ) ) {
			return $this->finish( $row, $owner, 'suppressed', array( 'reason_code' => (string) $ctx['suppress'] ) );
		}
		$user = (int) ( $row['recipient_user_id'] ?? 0 );
		if ( NotificationTypes::is_informational( $type ) ) {
			$enabled = (array) Settings::get( 'emails.types' );
			if ( empty( $enabled[ $type ] ) ) {
				return $this->finish( $row, $owner, 'suppressed', array( 'reason_code' => 'type_disabled' ) );
			}
			if ( $user > 0 && NotificationPrefs::is_opted_out( $user, $type ) ) {
				return $this->finish( $row, $owner, 'suppressed', array( 'reason_code' => 'opt_out' ) );
			}
		}
		$window = $this->recipient_window( $row );
		if ( $window['count'] >= self::PER_RECIPIENT_24H && 'test_mail' !== $type ) {
			if ( NotificationTypes::is_informational( $type ) ) {
				return $this->finish( $row, $owner, 'suppressed', array( 'reason_code' => 'rate_limited' ) );
			}
			return $this->finish(
				$row,
				$owner,
				'retry_wait',
				array(
					'reason_code'     => 'rate_deferred',
					'next_attempt_at' => gmdate( 'Y-m-d H:i:s', (int) strtotime( $window['oldest'] . ' UTC' ) + DAY_IN_SECONDS ),
				)
			);
		}
		if ( 'seat_invitation' === $type ) {
			if ( ! $this->group_invite_budget( (int) $ctx['seat_id'], (int) $row['id'] ) ) {
				return $this->finish( $row, $owner, 'suppressed', array( 'reason_code' => 'rate_limited' ) );
			}
			// The token is created in the send step; only its hash is stored (E-112).
			$ctx['vars']['claim_url'] = $this->rotate_claim_token( (int) $ctx['seat_id'] );
		}
		$locale = (string) $row['locale_requested'];
		try {
			$mail = $this->render( $type, $locale, $user, (array) $ctx['vars'], $this->builtin_fallback( $row ) );
		} catch ( TemplateError $e ) {
			update_option(
				self::STATE_OPTION,
				array(
					'template_error' => array(
						'type'   => $type,
						'locale' => $locale,
						'time'   => time(),
					),
				),
				false
			);
			return $this->finish(
				$row,
				$owner,
				'failed',
				array(
					'last_error_class' => 'template_error',
					'attempts'         => (int) $row['attempts'] + 1,
				)
			);
		}
		$recipient_ref = $user > 0 ? 'user:' . $user : (string) $row['recipient_ref'];
		$result        = $this->deliver( (string) $ctx['email'], $mail, $type, $user, $recipient_ref, $mode );
		if ( true === $result ) {
			return $this->finish(
				$row,
				$owner,
				OutboundMode::CATCH === $mode['mode'] ? 'caught' : 'sent',
				array(
					'attempts'         => (int) $row['attempts'] + 1,
					'sent_at'          => $this->db->now(),
					'locale_resolved'  => substr( $mail['locale'], 0, 20 ),
					'template_rev'     => $mail['template_rev'],
					'last_error_class' => null,
					'reason_code'      => null,
				)
			);
		}
		return $this->transport_failure( $row, $owner, $result );
	}

	/**
	 * Render in the recipient locale; the previous locale is restored in any case (§3.9).
	 *
	 * @param string                $type    Type.
	 * @param string                $locale  Requested locale.
	 * @param int                   $user    Recipient user (0 = address).
	 * @param array<string, string> $vars    Variables.
	 * @param bool                  $builtin Built-in text only.
	 * @return array{subject: string, html: string, text: string, locale: string, template_rev: int|null, lang: string, dir: string}
	 */
	public function render( string $type, string $locale, int $user, array $vars, bool $builtin = false ): array {
		$switched = $user > 0 ? switch_to_user_locale( $user ) : ( '' !== $locale && switch_to_locale( $locale ) );
		try {
			return MailTemplates::render( $type, '' === $locale ? get_locale() : $locale, $vars, $builtin );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * Hand the mail to `wp_mail` (only transport, N-6).
	 *
	 * @param string                                                      $to            Address.
	 * @param array{subject: string, html: string, text: string}          $mail          Rendered mail.
	 * @param string                                                      $type          Type.
	 * @param int                                                         $user          Recipient user.
	 * @param string                                                      $recipient_ref Reference for the catch header.
	 * @param array{mode: string, catch_address: string, warning: string} $mode          Outbound mode.
	 * @return true|string True or the error class (`transport`, `short_circuit`).
	 */
	private function deliver( string $to, array $mail, string $type, int $user, string $recipient_ref, array $mode ) {
		$multipart = 'multipart' === Settings::get( 'emails.send_format' );
		$subject   = $mail['subject'];
		$headers   = array( 'Content-Type: ' . ( $multipart ? 'text/html' : 'text/plain' ) . '; charset=UTF-8' );
		if ( NotificationTypes::is_informational( $type ) && $user > 0 ) {
			$headers[] = 'List-Unsubscribe: <' . esc_url_raw( NotificationPrefs::unsubscribe_url( $user, $type ) ) . '>';
			$headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
		}
		if ( OutboundMode::CATCH === $mode['mode'] ) {
			$to        = $mode['catch_address'];
			$subject   = '[catch] ' . $subject;
			$headers[] = 'X-CC-Recipient-Ref: ' . preg_replace( '/[^a-z0-9:]/', '', $recipient_ref );
		}
		$from_name    = (string) Settings::get( 'emails.from_name' );
		$from_address = (string) Settings::get( 'emails.from_address' );
		$filters      = array();
		if ( '' !== $from_name ) {
			$filters['wp_mail_from_name'] = static fn(): string => $from_name;
		}
		if ( '' !== $from_address ) {
			$filters['wp_mail_from'] = static fn(): string => $from_address;
		}
		if ( $multipart ) {
			$text                      = $mail['text'];
			$filters['phpmailer_init'] = static function ( $phpmailer ) use ( $text ): void {
				$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer API.
			};
		}
		$short                  = false;
		$filters['pre_wp_mail'] = static function ( $value ) use ( &$short ) {
			$short = false === $value;
			return $value;
		};
		foreach ( $filters as $hook => $callback ) {
			add_filter( $hook, $callback, PHP_INT_MAX );
		}
		try {
			$ok = wp_mail( $to, $subject, $multipart ? $mail['html'] : $mail['text'], $headers );
		} catch ( Throwable $e ) {
			$ok = false;
		} finally {
			foreach ( $filters as $hook => $callback ) {
				remove_filter( $hook, $callback, PHP_INT_MAX );
			}
		}
		if ( true === $ok ) {
			return true;
		}
		return $short ? 'short_circuit' : 'transport';
	}

	/**
	 * Transport error: retry with backoff, `failed` after 5 attempts (§3.5).
	 *
	 * @param array<string, mixed> $row   Row.
	 * @param string               $owner Lease owner.
	 * @param string               $error_class Error class.
	 * @return string
	 */
	private function transport_failure( array $row, string $owner, string $error_class ): string {
		$attempts = (int) $row['attempts'] + 1;
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			return $this->finish(
				$row,
				$owner,
				'failed',
				array(
					'attempts'         => $attempts,
					'last_error_class' => $error_class,
				)
			);
		}
		return $this->finish(
			$row,
			$owner,
			'retry_wait',
			array(
				'attempts'         => $attempts,
				'last_error_class' => $error_class,
				'next_attempt_at'  => $this->db->now( self::BACKOFF[ $attempts - 1 ] ),
			)
		);
	}

	/**
	 * Close a claimed row (only with our lease).
	 *
	 * @param array<string, mixed> $row    Row.
	 * @param string               $owner  Lease owner.
	 * @param string               $status Status.
	 * @param array<string, mixed> $fields Extra columns.
	 * @return string The status.
	 */
	private function finish( array $row, string $owner, string $status, array $fields ): string {
		$sets   = array( 'status = %s', 'locked_until = NULL', 'lock_owner = NULL' );
		$values = array( $status );
		foreach ( $fields as $column => $value ) {
			Database::assert_identifier( $column );
			if ( null === $value ) {
				$sets[] = "`{$column}` = NULL";
				continue;
			}
			$sets[]   = "`{$column}` = " . ( is_int( $value ) ? '%d' : '%s' );
			$values[] = $value;
		}
		$values[] = (int) $row['id'];
		$values[] = $owner;
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_notification' ) . '` SET ' . implode( ', ', $sets ) . ' WHERE id = %d AND lock_owner = %s', ...$values ) );
		return $status;
	}

	/**
	 * V-4: leases that ran out without result become `unknown` (no automatic re-send).
	 *
	 * @return int Rows.
	 */
	public function mark_unknown(): int {
		return $this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_notification' ) . "` SET status = 'unknown', lock_owner = NULL, locked_until = NULL WHERE status = 'sending' AND locked_until < %s", $this->db->now() ) );
	}

	/**
	 * State re-check and variables per type (N-2).
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed> `fail` or `suppress` with a code, else `email`, `vars` (and `seat_id`).
	 */
	private function context( array $row ): array {
		$type      = (string) $row['type'];
		$relations = new Relations( $this->db );
		$vars      = MailTemplates::site_vars();
		if ( 'seat_invitation' === $type ) {
			$seat_id = 1 === preg_match( '/^seat:(\d+)$/', (string) $row['recipient_ref'], $m ) ? (int) $m[1] : 0;
			$seat    = $seat_id > 0 ? $relations->row( 'ccap_seat', $seat_id ) : null;
			$group   = null === $seat ? null : $relations->row( 'ccap_group', (int) $seat['group_id'] );
			if ( null === $seat || null === $group || 'invited' !== $seat['status'] || (string) $seat['claim_expires_at'] <= $this->db->now() || 'active' !== $group['status'] ) {
				return array( 'suppress' => 'state_changed' );
			}
			$email = (string) $seat['invite_email'];
			if ( false === is_email( $email ) ) {
				return array( 'fail' => 'invalid_recipient' );
			}
			$owner = get_userdata( (int) $group['owner_user_id'] );
			return array(
				'email'   => $email,
				'seat_id' => $seat_id,
				'vars'    => $vars + array(
					'group_name'           => (string) $group['name'],
					'inviter_display_name' => $owner instanceof \WP_User ? (string) $owner->display_name : (string) $vars['site_name'],
					'invite_expires_at'    => self::date( (string) $seat['claim_expires_at'] ),
				),
			);
		}
		$user = get_userdata( (int) ( $row['recipient_user_id'] ?? 0 ) );
		if ( ! $user instanceof \WP_User || false === is_email( (string) $user->user_email ) ) {
			return array( 'fail' => 'invalid_recipient' );
		}
		$vars['recipient_display_name'] = (string) $user->display_name;
		if ( NotificationTypes::is_informational( $type ) ) {
			$vars['unsubscribe_url'] = NotificationPrefs::unsubscribe_url( (int) $user->ID, $type );
		}
		$out = array(
			'email' => (string) $user->user_email,
			'vars'  => $vars,
		);
		if ( 'test_mail' === $type ) {
			return $out;
		}
		$envelope = $this->envelope( (string) $row['event_uuid'] );
		if ( null === $envelope ) {
			return array( 'suppress' => 'context_missing' );
		}
		$uid     = (int) $user->ID;
		$course  = $this->course_id( $envelope );
		$payload = (array) ( $envelope['payload'] ?? array() );
		switch ( $type ) {
			case 'access_granted':
				$ok = 'ok' === $relations->effective_access( $uid, $course, 0, 'view' ) && ! $relations->is_locked( $uid );
				break;
			case 'access_expired':
				$ok                           = $course > 0 && 'ok' !== $relations->effective_access( $uid, $course, 0, 'view' );
				$end                          = (string) $this->db->scalar( $this->db->prepare( 'SELECT MAX(valid_until) FROM `' . $this->db->table( 'ccap_grant' ) . '` WHERE user_id = %d AND course_id = %d', $uid, $course ) );
				$out['vars']['access_end_at'] = '' === $end ? '' : self::date( $end );
				break;
			case 'seat_claimed':
				$seat   = $relations->row( 'ccap_seat', (int) ( $payload['seat_id'] ?? 0 ) );
				$ok     = null !== $seat && 'active' === $seat['status'] && (int) $seat['user_id'] === $uid;
				$course = null === $seat ? 0 : (int) $this->db->scalar( $this->db->prepare( 'SELECT course_id FROM `' . $this->db->table( 'ccap_group_course' ) . '` WHERE group_id = %d ORDER BY id LIMIT 1', (int) $seat['group_id'] ) );
				break;
			case 'course_completed':
				$ok = 'completed' === $this->db->scalar( $this->db->prepare( 'SELECT status FROM `' . $this->db->table( 'ccap_course_progress' ) . '` WHERE user_id = %d AND course_id = %d', $uid, $course ) );
				break;
			case 'grading_completed':
				$session = $this->db->row( $this->db->prepare( 'SELECT course_id, status FROM `' . $this->db->table( 'ccap_exam_session' ) . '` WHERE uid = %s AND user_id = %d', (string) ( $payload['session_uid'] ?? '' ), $uid ) );
				$ok      = null !== $session && 'evaluated' === $session['status'];
				$course  = null === $session ? 0 : (int) $session['course_id'];
				break;
			case 'assignment_submitted':
			case 'assignment_returned':
			case 'assignment_graded':
				// N-14…N-16: never text, comment or points in the mail, only the link (R0-27 §3.2).
				$sub    = $this->db->row( $this->db->prepare( 'SELECT id, user_id, course_id, status FROM `' . $this->db->table( 'ccap_assignment_submission' ) . '` WHERE uid = %s', (string) ( $payload['submission_uid'] ?? '' ) ) );
				$want   = array(
					'assignment_submitted' => 'submitted',
					'assignment_returned'  => 'returned',
					'assignment_graded'    => 'graded',
				)[ $type ];
				$ok     = null !== $sub && $want === $sub['status'] && ( 'assignment_submitted' === $type
					? \CourseCaptain\Policy\PolicyService::instance()->decide( \CourseCaptain\Policy\Actor::user( $uid ), \CourseCaptain\Policy\PolicyService::GRADE_SUBMISSION, array( (int) $sub['id'] ) )->allowed
					: (int) $sub['user_id'] === $uid );
				$course = null === $sub ? 0 : (int) $sub['course_id'];
				break;
			default:
				return array( 'suppress' => 'not_wired' );
		}
		if ( ! $ok ) {
			return array( 'suppress' => 'state_changed' );
		}
		if ( $course > 0 ) {
			$course_uid                  = (string) $this->db->scalar( $this->db->prepare( 'SELECT uid FROM `' . $this->db->table( 'ccap_source_map' ) . "` WHERE object_type = 'course' AND object_id = %d", $course ) );
			$out['vars']['course_title'] = wp_strip_all_tags( (string) get_post_field( 'post_title', $course ) );
			$out['vars']['course_url']   = '' === $course_uid ? LearningArea::url() : LearningArea::url( 'kurs/' . $course_uid . '/' );
			// Never points or answers in the mail (N-5): only a link to the learning area.
			$out['vars']['attempt_title'] = $out['vars']['course_title'];
			$out['vars']['result_url']    = $out['vars']['course_url'];
		}
		return $out;
	}

	/**
	 * Trainers of a course (`ccap_course_staff`, role `trainer`) whose scope covers the learner.
	 *
	 * @param int $course  Course.
	 * @param int $learner Learner (never a recipient).
	 * @return list<int>
	 */
	private function assignment_trainers( int $course, int $learner ): array {
		if ( $course <= 0 || $learner <= 0 ) {
			return array();
		}
		$relations = new Relations( $this->db );
		$ids       = array_map( 'intval', array_column( $this->db->rows( $this->db->prepare( 'SELECT DISTINCT user_id FROM `' . $this->db->table( 'ccap_course_staff' ) . "` WHERE course_id = %d AND role = 'trainer' ORDER BY user_id", $course ) ), 'user_id' ) );
		return array_values( array_filter( $ids, static fn( int $id ): bool => $id !== $learner && $relations->trainer_covers_learner( $id, $learner, $course ) ) );
	}

	/**
	 * Envelope of the triggering event (kept 90 days by the outbox).
	 *
	 * @param string $uuid Event UUID.
	 * @return array<string, mixed>|null
	 */
	private function envelope( string $uuid ): ?array {
		$json = $this->db->scalar( $this->db->prepare( 'SELECT payload FROM `' . $this->db->table( 'ccap_outbox_event' ) . '` WHERE event_uuid = %s', $uuid ) );
		$data = null === $json ? null : json_decode( $json, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Course ID of an envelope (course UID, numeric ID or `course:user:cycle`).
	 *
	 * @param array<string, mixed> $envelope Envelope.
	 * @return int
	 */
	private function course_id( array $envelope ): int {
		$uid = (string) ( $envelope['course_uid'] ?? '' );
		if ( '' !== $uid ) {
			$id = (int) $this->db->scalar( $this->db->prepare( 'SELECT object_id FROM `' . $this->db->table( 'ccap_source_map' ) . "` WHERE object_type = 'course' AND uid = %s", $uid ) );
			if ( $id > 0 ) {
				return $id;
			}
		}
		$object = (string) ( $envelope['object_id'] ?? '' );
		if ( 'grant' === ( $envelope['object_type'] ?? '' ) ) {
			return (int) $this->db->scalar( $this->db->prepare( 'SELECT course_id FROM `' . $this->db->table( 'ccap_grant' ) . '` WHERE uid = %s', $object ) );
		}
		return 1 === preg_match( '/^(\d+)(?::|$)/', $object, $m ) ? (int) $m[1] : 0;
	}

	/**
	 * Cause of an access flip from the newest grant event (N-01): `activated`, `expired` or `other`
	 * (resume after suspension, retry, reconcile, revocation: no mail, E-136; seat grants: N-03 instead).
	 *
	 * @param int $user   Learner.
	 * @param int $course Course.
	 * @return string
	 */
	private function access_cause( int $user, int $course ): string {
		$event = $this->db->row( $this->db->prepare( 'SELECT e.from_status, e.to_status, e.reason_code, g.source_type FROM `' . $this->db->table( 'ccap_grant_event' ) . '` e JOIN `' . $this->db->table( 'ccap_grant' ) . "` g ON g.id = e.grant_id WHERE g.user_id = %d AND g.course_id = %d AND e.event_type IN ('created', 'status_changed') ORDER BY e.id DESC LIMIT 1", $user, $course ) );
		if ( null === $event ) {
			return 'other';
		}
		$from = (string) ( $event['from_status'] ?? '' );
		if ( 'expired' === $event['to_status'] ) {
			return 'expired';
		}
		// Seat grants are announced by `seat_claimed` (N-03), not a second access mail.
		if ( 'active' === $event['to_status'] && 'group_seat' !== $event['source_type'] && ( '' === $from || str_starts_with( $from, 'pending' ) ) && ! str_contains( (string) $event['reason_code'], 'reconcil' ) ) {
			return 'activated';
		}
		return 'other';
	}

	/**
	 * Locale of an invitation: inviting person → site (§3.9).
	 *
	 * @param array<string, mixed> $envelope Envelope.
	 * @return string
	 */
	private function inviter_locale( array $envelope ): string {
		return 1 === preg_match( '/^user:(\d+)$/', (string) ( $envelope['actor_ref'] ?? '' ), $m ) && (int) $m[1] > 0 ? get_user_locale( (int) $m[1] ) : get_locale();
	}

	/**
	 * Mails to this recipient in the last 24 h (§3.8, fail-open).
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array{count: int, oldest: string}
	 */
	private function recipient_window( array $row ): array {
		try {
			$where = null !== $row['recipient_user_id'] ? $this->db->prepare( 'recipient_user_id = %d', (int) $row['recipient_user_id'] ) : $this->db->prepare( 'recipient_ref = %s', (string) $row['recipient_ref'] );
			$data  = (array) $this->db->row( $this->db->prepare( 'SELECT COUNT(*) AS n, MIN(sent_at) AS oldest FROM `' . $this->db->table( 'ccap_notification' ) . "` WHERE {$where} AND status IN ('sent', 'caught') AND sent_at > %s", $this->db->now( -DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared above.
			return array(
				'count'  => (int) ( $data['n'] ?? 0 ),
				'oldest' => (string) ( $data['oldest'] ?? '' ),
			);
		} catch ( Throwable $e ) {
			return array(
				'count'  => 0,
				'oldest' => '',
			);
		}
	}

	/**
	 * Invitations per group: max(20, 2 × seats) per 24 h (§3.8, fail-closed).
	 *
	 * @param int $seat_id Seat.
	 * @param int $id      Current row.
	 * @return bool
	 */
	private function group_invite_budget( int $seat_id, int $id ): bool {
		try {
			$seats = $this->db->table( 'ccap_seat' );
			$group = (array) $this->db->row( $this->db->prepare( 'SELECT g.id, g.seats FROM `' . $this->db->table( 'ccap_group' ) . "` g JOIN `{$seats}` s ON s.group_id = g.id WHERE s.id = %d", $seat_id ) );
			$limit = max( 20, 2 * (int) ( $group['seats'] ?? 0 ) );
			$used  = (int) $this->db->scalar( $this->db->prepare( 'SELECT COUNT(*) FROM `' . $this->db->table( 'ccap_notification' ) . "` n JOIN `{$seats}` s ON n.recipient_ref = CONCAT('seat:', s.id) WHERE s.group_id = %d AND n.type = 'seat_invitation' AND n.id <> %d AND n.status IN ('sent', 'caught', 'sending') AND n.created_at > %s", (int) ( $group['id'] ?? 0 ), $id, $this->db->now( -DAY_IN_SECONDS ) ) );
			return $used < $limit;
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * New claim token for an invited seat; the previous one stops working.
	 *
	 * @param int $seat_id Seat.
	 * @return string Claim URL (only in this mail).
	 */
	private function rotate_claim_token( int $seat_id ): string {
		$token = 'cci_' . rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Token encoding (R0-09 §1.2).
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_seat' ) . "` SET claim_token_hash = %s, updated_at = %s WHERE id = %d AND status = 'invited'", hash( 'sha256', $token ), $this->db->now(), $seat_id ) );
		return add_query_arg( 'ccap_claim', $token, LearningArea::url() );
	}

	/**
	 * Re-send after `template_error` uses the built-in text (§3.5).
	 *
	 * @param array<string, mixed> $row Row.
	 * @return bool
	 */
	private function builtin_fallback( array $row ): bool {
		if ( null === $row['resend_of'] ) {
			return false;
		}
		return 'template_error' === $this->db->scalar( $this->db->prepare( 'SELECT last_error_class FROM `' . $this->db->table( 'ccap_notification' ) . '` WHERE id = %d', (int) $row['resend_of'] ) );
	}

	/**
	 * Admin "re-send" for `failed`, `unknown`, `caught`: a new row with `resend_of`, new checks.
	 *
	 * @param Actor $actor Admin.
	 * @param int   $id    Row.
	 * @return int New row.
	 * @throws CourseException `ccap_forbidden`, `ccap_not_found`, `ccap_conflict`, `ccap_rate_limited`.
	 */
	public function resend( Actor $actor, int $id ): int {
		self::assert_admin( $actor );
		$row = $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_notification' ) . '` WHERE id = %d', $id ) );
		if ( null === $row ) {
			throw new CourseException( 'ccap_not_found', 'Unknown notification.', 404 );
		}
		if ( ! in_array( $row['status'], array( 'failed', 'unknown', 'caught' ), true ) ) {
			throw new CourseException( 'ccap_conflict', 'Only failed, unknown or caught messages can be sent again.', 409 );
		}
		if ( 'seat_invitation' === $row['type'] ) {
			$this->assert_invite_resend_budget( (string) $row['recipient_ref'] );
		}
		$new = (int) $this->insert( (string) $row['type'], (string) $row['event_uuid'], (string) ( $row['correlation_id'] ?? '' ), null === $row['recipient_user_id'] ? null : (int) $row['recipient_user_id'], $row['recipient_ref'], 'resend:' . $id . ':' . $this->db->uuid(), (string) $row['locale_requested'], $id );
		AuditLog::write( $this->db, $actor, 'notification_resent', 'notification', (string) $row['uid'], null, array( 'type' => $row['type'] ) );
		self::schedule( 5 );
		return $new;
	}

	/**
	 * Owner/manager re-sends an invitation: at most 3 times per seat, new token each time (NTF-029).
	 *
	 * @param Actor $actor   Owner or manager.
	 * @param int   $seat_id Seat.
	 * @return int New row.
	 * @throws CourseException `ccap_forbidden`, `ccap_conflict`, `ccap_rate_limited`.
	 */
	public function resend_invitation( Actor $actor, int $seat_id ): int {
		$seat = ( new Relations( $this->db ) )->row( 'ccap_seat', $seat_id );
		if ( null === $seat ) {
			throw new CourseException( 'ccap_not_found', 'Unknown seat.', 404 );
		}
		CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::MANAGE_GROUP, array( (int) $seat['group_id'] ), array( 'write' => true ) ), 'Not allowed to manage this group.' );
		if ( 'invited' !== $seat['status'] ) {
			throw new CourseException( 'ccap_conflict', 'The invitation is no longer open.', 409 );
		}
		$ref      = 'seat:' . $seat_id;
		$revision = $this->assert_invite_resend_budget( $ref ) + 1;
		$new      = (int) $this->insert( 'seat_invitation', '', '', null, $ref, $seat_id . ':' . $revision, $actor->user_id > 0 ? get_user_locale( $actor->user_id ) : get_locale() );
		AuditLog::write( $this->db, $actor, 'invitation_resent', 'seat', (string) $seat_id, null, array( 'revision' => $revision ) );
		self::schedule( 5 );
		return $new;
	}

	/**
	 * Re-sends of an invitation so far; throws at the limit (fail-closed).
	 *
	 * @param string $ref `seat:<id>`.
	 * @return int Re-sends so far.
	 * @throws CourseException `ccap_rate_limited`.
	 */
	private function assert_invite_resend_budget( string $ref ): int {
		$count   = (int) $this->db->scalar( $this->db->prepare( 'SELECT COUNT(*) FROM `' . $this->db->table( 'ccap_notification' ) . "` WHERE type = 'seat_invitation' AND recipient_ref = %s", $ref ) );
		$resends = max( 0, $count - 1 );
		if ( $resends >= self::INVITE_RESENDS ) {
			throw new CourseException( 'ccap_rate_limited', 'This invitation was already sent again three times.', 429 );
		}
		return $resends;
	}

	/**
	 * Test mail to the admin's own profile address (N-13; 5 per admin and hour, fail-closed).
	 *
	 * @param Actor $actor Admin.
	 * @return array{status: string, error_class: string}
	 * @throws CourseException `ccap_forbidden`, `ccap_rate_limited`.
	 */
	public function send_test_mail( Actor $actor ): array {
		self::assert_admin( $actor );
		$table = $this->db->table( 'ccap_notification' );
		$used  = (int) $this->db->scalar( $this->db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE type = 'test_mail' AND recipient_user_id = %d AND created_at > %s", $actor->user_id, $this->db->now( -HOUR_IN_SECONDS ) ) );
		if ( $used >= self::TEST_MAILS_PER_HOUR ) {
			throw new CourseException( 'ccap_rate_limited', 'At most five test messages per hour.', 429 );
		}
		$id     = (int) $this->insert( 'test_mail', '', '', $actor->user_id, null, $this->db->uuid(), get_user_locale( $actor->user_id ) );
		$status = (string) $this->send_one( $id );
		$row    = (array) $this->db->row( $this->db->prepare( "SELECT last_error_class FROM `{$table}` WHERE id = %d", $id ) );
		return array(
			'status'      => $status,
			'error_class' => (string) ( $row['last_error_class'] ?? '' ),
		);
	}

	/**
	 * Release held mails (hold mode confirmed "same site").
	 *
	 * @param Actor       $actor  Admin.
	 * @param string|null $reason Justification (operations screen, R1-38).
	 * @return int Rows.
	 */
	public function release_held( Actor $actor, ?string $reason = null ): int {
		self::assert_admin( $actor );
		$count = $this->db->execute( 'UPDATE `' . $this->db->table( 'ccap_notification' ) . "` SET status = 'queued' WHERE status = 'held'" );
		AuditLog::write( $this->db, $actor, 'notifications_released', 'notification', 'held', $reason, array( 'count' => $count ) );
		self::schedule( 5 );
		return $count;
	}

	/**
	 * Discard held mails (default action, E-88).
	 *
	 * @param Actor       $actor  Admin.
	 * @param string|null $reason Justification (operations screen, R1-38).
	 * @return int Rows.
	 */
	public function discard_held( Actor $actor, ?string $reason = null ): int {
		self::assert_admin( $actor );
		$count = $this->db->execute( 'UPDATE `' . $this->db->table( 'ccap_notification' ) . "` SET status = 'discarded', reason_code = 'held_discarded' WHERE status = 'held'" );
		AuditLog::write( $this->db, $actor, 'notifications_discarded', 'notification', 'held', $reason, array( 'count' => $count ) );
		return $count;
	}

	/**
	 * Hourly step: leases → `unknown`, held mails past `held_mail_days` → `discarded`, send run.
	 *
	 * @return array<string, int>
	 */
	public function hourly(): array {
		$discarded = $this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_notification' ) . "` SET status = 'discarded', reason_code = 'held_expired' WHERE status = 'held' AND created_at < %s", $this->db->now( -self::HELD_DAYS * DAY_IN_SECONDS ) ) );
		return array( 'discarded' => $discarded ) + $this->process_queue();
	}

	/**
	 * Site Health test `course_captain_mail`.
	 *
	 * @param mixed $tests Tests.
	 * @return array<string, mixed>
	 */
	public static function health_tests( $tests ): array {
		$tests                                  = (array) $tests;
		$tests['direct']['course_captain_mail'] = array(
			'label' => __( 'Course Captain mail delivery', 'course-captain' ),
			'test'  => array( self::class, 'test_mail_health' ),
		);
		return $tests;
	}

	/**
	 * Mail health: critical from 10 failed in 24 h or a queue older than 1 h.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_mail_health(): array {
		$db     = Database::current();
		$table  = $db->table( 'ccap_notification' );
		$failed = (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE status = 'failed' AND created_at > %s", $db->now( -DAY_IN_SECONDS ) ) );
		$stale  = (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE status IN ('queued', 'retry_wait') AND COALESCE(next_attempt_at, created_at) < %s", $db->now( -HOUR_IN_SECONDS ) ) );
		$mode   = OutboundMode::current();
		$status = 'good';
		/* translators: %s: outbound mode on, catch or off */
		$label = sprintf( __( 'Course Captain mails are delivered (mode: %s).', 'course-captain' ), $mode['mode'] );
		if ( $failed >= self::FAILED_CRITICAL || $stale > 0 ) {
			$status = 'critical';
			/* translators: 1: failed mails, 2: waiting mails */
			$label = sprintf( __( 'Course Captain mail delivery has problems: %1$s failed in 24 hours, %2$s waiting longer than one hour.', 'course-captain' ), number_format_i18n( $failed ), number_format_i18n( $stale ) );
		} elseif ( $failed > 0 || '' !== $mode['warning'] ) {
			$status = 'recommended';
			$label  = '' !== $mode['warning'] ? __( 'The catch address for Course Captain mails is invalid; outbound mails are switched off.', 'course-captain' ) : sprintf(
				/* translators: %s: number of failed mails */
				_n( '%s Course Captain mail failed in the last 24 hours.', '%s Course Captain mails failed in the last 24 hours.', $failed, 'course-captain' ),
				number_format_i18n( $failed )
			);
		}
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Course Captain', 'course-captain' ),
				'color' => 'good' === $status ? 'blue' : 'orange',
			),
			'description' => '<p>' . esc_html__( 'Mails are handed to WordPress (wp_mail); an SMTP plugin stays in charge of delivery.', 'course-captain' ) . '</p>',
			'actions'     => '',
			'test'        => 'course_captain_mail',
		);
	}

	/**
	 * Admin notice while mails failed (errors come back after 24 h snooze, §3.5).
	 *
	 * @return void
	 */
	public static function admin_notice(): void {
		if ( ! current_user_can( Settings::CAP ) ) {
			return;
		}
		try {
			$db     = Database::current();
			$failed = (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_notification' ) . "` WHERE status = 'failed' AND created_at > %s", $db->now( -DAY_IN_SECONDS ) ) );
		} catch ( Throwable $e ) {
			return;
		}
		if ( $failed > 0 ) {
			/* translators: %s: number of failed mails */
			Notices::add( 'ccap_mail_failed', 'error', sprintf( _n( '%s Course Captain message could not be sent in the last 24 hours. Check the mail settings or your SMTP plugin.', '%s Course Captain messages could not be sent in the last 24 hours. Check the mail settings or your SMTP plugin.', $failed, 'course-captain' ), number_format_i18n( $failed ) ) );
		}
	}

	/**
	 * Admin capability check.
	 *
	 * @param Actor $actor Actor.
	 * @return void
	 * @throws CourseException Without `ccap_manage_settings`.
	 */
	private static function assert_admin( Actor $actor ): void {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, Settings::CAP ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to manage notifications.', 403 );
		}
	}

	/**
	 * Date with time zone in the frame locale (E-124).
	 *
	 * @param string $utc UTC datetime.
	 * @return string
	 */
	private static function date( string $utc ): string {
		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T', (int) strtotime( $utc . ' UTC' ) );
	}
}
