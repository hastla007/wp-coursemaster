<?php
/**
 * Personal data export and erasure via the WordPress privacy tools
 * (R0-31 §2.1 RT-01…RT-45, R0-17 §5; R0-05 GM-102, GM-103, GM-172, GM-173).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Privacy;

use CourseCaptain\Certificate\CertificateService;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Every own table is classified (GM-173). Export groups contain only the
 * person's own rows, never hashes or tokens. The eraser removes learning
 * data, favorites, answers, exam attempts, certificates (RT-09/RT-10:
 * deleted, verification then `ccap_not_found`), manual/group grants, staff
 * assignments, locks, accommodations, notifications and assignment
 * submissions; seats are released (address and token emptied); owned
 * groups lose their owner (GM-102). Audit rows are kept (hash chain,
 * R0-13 §4.4); the erasure itself is audited without the person's ID.
 */
final class PrivacyService {

	public const EXPORTER = 'course-captain';
	public const ERASER   = 'course-captain';

	/**
	 * Classification of all own tables: personal (exported/erased), content, system.
	 *
	 * @var array<string, string>
	 */
	public const CLASSIFICATION = array(
		'ccap_lease'                 => 'system',
		'ccap_migration_step'        => 'system',
		'ccap_source_map'            => 'content',
		'ccap_course_module'         => 'content',
		'ccap_course_relation'       => 'content',
		'ccap_course_revision'       => 'content',
		'ccap_question'              => 'content',
		'ccap_question_revision'     => 'content',
		'ccap_question_tag'          => 'content',
		'ccap_answer'                => 'content',
		'ccap_module_question'       => 'content',
		'ccap_activity_question'     => 'content',
		'ccap_media'                 => 'content',
		'ccap_content_media'         => 'content',
		'ccap_exam_rule'             => 'content',
		'ccap_exam_blueprint'        => 'content',
		'ccap_exam_variant'          => 'content',
		'ccap_exam_variant_question' => 'content',
		'ccap_exam_session'          => 'personal',
		'ccap_exam_session_question' => 'personal',
		'ccap_attempt'               => 'personal',
		'ccap_attempt_answer'        => 'personal',
		'ccap_question_progress'     => 'personal',
		'ccap_activity_progress'     => 'personal',
		'ccap_course_progress'       => 'personal',
		'ccap_favorite'              => 'personal',
		'ccap_collection'            => 'personal',
		'ccap_collection_item'       => 'personal',
		'ccap_group'                 => 'personal',
		'ccap_group_course'          => 'content',
		'ccap_seat'                  => 'personal',
		'ccap_grant'                 => 'personal',
		'ccap_grant_event'           => 'personal',
		'ccap_access_rule'           => 'content',
		'ccap_assignment_submission' => 'personal',
		'ccap_assignment_file'       => 'personal',
		'ccap_assignment_grade'      => 'personal',
		'ccap_certificate'           => 'personal',
		'ccap_inbox_event'           => 'system',
		'ccap_outbox_event'          => 'system',
		'ccap_outbox_delivery'       => 'system',
		'ccap_import_batch'          => 'system',
		'ccap_import_item_log'       => 'system',
		'ccap_import_issue'          => 'system',
		'ccap_audit_log'             => 'personal',
		'ccap_override'              => 'content',
		'ccap_rate_limit'            => 'system',
		'ccap_request_idempotency'   => 'personal',
		'ccap_course_staff'          => 'personal',
		'ccap_learner_lock'          => 'personal',
		'ccap_accommodation'         => 'personal',
		'ccap_notification'          => 'personal',
		'ccap_reset_journal'         => 'personal',
	);

	/**
	 * Export groups: id → [label, table, user column, columns].
	 *
	 * @var array<string, array{0: string, 1: string, 2: string, 3: list<string>}>
	 */
	private const GROUPS = array(
		'cc-progress'      => array( 'Course progress', 'ccap_course_progress', 'user_id', array( 'course_id', 'status', 'completion_ratio', 'mastery_ratio', 'started_at', 'completed_at' ) ),
		'cc-activity'      => array( 'Activity progress', 'ccap_activity_progress', 'user_id', array( 'activity_id', 'course_id', 'activity_type', 'status', 'completed_at', 'last_access_at' ) ),
		'cc-questions'     => array( 'Question learning state', 'ccap_question_progress', 'user_id', array( 'question_id', 'attempts', 'correct_count', 'wrong_count', 'streak', 'learn_status', 'due_at', 'last_answered_at' ) ),
		'cc-answers'       => array( 'Learning answers', 'ccap_attempt', 'user_id', array( 'question_id', 'course_id', 'context', 'is_correct', 'self_assessment', 'created_at' ) ),
		'cc-favorite'      => array( 'Favorites', 'ccap_favorite', 'user_id', array( 'question_id', 'context', 'created_at' ) ),
		'cc-collections'   => array( 'Collections', 'ccap_collection', 'user_id', array( 'course_id', 'name', 'created_at' ) ),
		'cc-exams'         => array( 'Exam attempts', 'ccap_exam_session', 'user_id', array( 'course_id', 'mode', 'status', 'started_at', 'submitted_at', 'points_milli', 'max_points_milli', 'passed' ) ),
		'cc-grants'        => array( 'Course access', 'ccap_grant', 'user_id', array( 'course_id', 'module_id', 'status', 'source_type', 'valid_from', 'valid_until', 'created_at' ) ),
		'cc-seats'         => array( 'Group seats', 'ccap_seat', 'user_id', array( 'group_id', 'status', 'invited_at', 'claimed_at', 'removed_at' ) ),
		'cc-notifications' => array( 'Notifications', 'ccap_notification', 'recipient_user_id', array( 'type', 'status', 'created_at', 'sent_at' ) ),
		'cc-submissions'   => array( 'Assignment submissions', 'ccap_assignment_submission', 'user_id', array( 'course_id', 'activity_id', 'status', 'text_answer', 'submitted_at', 'graded_at' ) ),
	);

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter(
			'wp_privacy_personal_data_exporters',
			static function ( array $exporters ): array {
				$exporters[ self::EXPORTER ] = array(
					'exporter_friendly_name' => __( 'Course Captain', 'course-captain' ),
					'callback'               => array( self::class, 'export' ),
				);
				return $exporters;
			}
		);
		add_filter(
			'wp_privacy_personal_data_erasers',
			static function ( array $erasers ): array {
				$erasers[ self::ERASER ] = array(
					'eraser_friendly_name' => __( 'Course Captain', 'course-captain' ),
					'callback'             => array( self::class, 'erase' ),
				);
				return $erasers;
			}
		);
		add_action( 'delete_user', array( self::class, 'on_delete_user' ) );
		add_action( 'admin_init', array( self::class, 'policy_text' ) );
	}

	/**
	 * WordPress exporter callback.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array{data: list<array<string, mixed>>, done: bool}
	 */
	public static function export( $email, $page = 1 ): array {
		unset( $page );
		$user = get_user_by( 'email', (string) $email );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'data' => self::invitations( (string) $email ),
				'done' => true,
			);
		}
		$db   = Database::current();
		$data = array();
		foreach ( self::GROUPS as $group => $def ) {
			list( $label, $table, $column, $columns ) = $def;
			$rows                                     = $db->rows( $db->prepare( 'SELECT id, `' . implode( '`, `', $columns ) . '` FROM `' . $db->table( $table ) . "` WHERE `{$column}` = %d ORDER BY id LIMIT 5000", (int) $user->ID ) );
			foreach ( $rows as $row ) {
				$item = array();
				foreach ( $columns as $col ) {
					$item[] = array(
						'name'  => $col,
						'value' => null === $row[ $col ] ? '' : (string) $row[ $col ],
					);
				}
				$data[] = array(
					'group_id'    => $group,
					'group_label' => $label,
					'item_id'     => $group . '-' . (int) $row['id'],
					'data'        => $item,
				);
			}
		}
		$prefs = get_user_option( 'course_captain_notification_prefs', (int) $user->ID );
		if ( is_array( $prefs ) ) {
			$data[] = array(
				'group_id'    => 'cc-notification-prefs',
				'group_label' => 'Notification preferences',
				'item_id'     => 'cc-notification-prefs',
				'data'        => array(
					array(
						'name'  => 'optout',
						'value' => implode( ', ', array_map( 'strval', (array) ( $prefs['optout'] ?? array() ) ) ),
					),
				),
			);
		}
		// Certificates with their snapshot data, without hashes or tokens (R0-29 CF-12).
		$data = array_merge( $data, CertificateService::current()->export_items( (int) $user->ID ), self::invitations( (string) $user->user_email ) );
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * WordPress eraser callback.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public static function erase( $email, $page = 1 ): array {
		unset( $page );
		$user    = get_user_by( 'email', (string) $email );
		$removed = self::erase_invitations( (string) $email ) > 0;
		if ( $user instanceof \WP_User ) {
			$removed = self::erase_user( (int) $user->ID ) > 0 || $removed;
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => true,
			'messages'       => array( __( 'Course Captain keeps the audit log (tamper protection) and course content.', 'course-captain' ) ),
			'done'           => true,
		);
	}

	/**
	 * Account deletion (GM-102).
	 *
	 * @param mixed $user_id User.
	 * @return void
	 */
	public static function on_delete_user( $user_id ): void {
		$user = get_userdata( (int) $user_id );
		if ( $user instanceof \WP_User ) {
			self::erase_invitations( (string) $user->user_email );
		}
		self::erase_user( (int) $user_id );
	}

	/**
	 * Remove all personal rows of a user.
	 *
	 * @param int $user_id User.
	 * @return int Rows affected.
	 */
	public static function erase_user( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}
		$db     = Database::current();
		$count  = 0;
		$count += $db->transaction(
			static function ( Database $db ) use ( $user_id ): int {
				$n   = 0;
				$del = static function ( string $table, string $column ) use ( $db, $user_id, &$n ): void {
					$n += $db->execute( $db->prepare( 'DELETE FROM `' . $db->table( $table ) . "` WHERE `{$column}` = %d", $user_id ) );
				};
				// Children first where no cascading key exists.
				$n += $db->execute( $db->prepare( 'DELETE i FROM `' . $db->table( 'ccap_collection_item' ) . '` i JOIN `' . $db->table( 'ccap_collection' ) . '` c ON c.id = i.collection_id WHERE c.user_id = %d', $user_id ) );
				$n += $db->execute( $db->prepare( 'DELETE a FROM `' . $db->table( 'ccap_attempt_answer' ) . '` a JOIN `' . $db->table( 'ccap_attempt' ) . '` t ON t.id = a.attempt_id WHERE t.user_id = %d', $user_id ) );
				$n += $db->execute( $db->prepare( 'DELETE q FROM `' . $db->table( 'ccap_exam_session_question' ) . '` q JOIN `' . $db->table( 'ccap_exam_session' ) . '` s ON s.id = q.session_id WHERE s.user_id = %d', $user_id ) );
				$n += $db->execute( $db->prepare( 'DELETE e FROM `' . $db->table( 'ccap_grant_event' ) . '` e JOIN `' . $db->table( 'ccap_grant' ) . '` g ON g.id = e.grant_id WHERE g.user_id = %d', $user_id ) );
				foreach ( array( 'ccap_course_progress', 'ccap_activity_progress', 'ccap_question_progress', 'ccap_attempt', 'ccap_favorite', 'ccap_collection', 'ccap_exam_session', 'ccap_grant', 'ccap_course_staff', 'ccap_learner_lock', 'ccap_accommodation', 'ccap_request_idempotency', 'ccap_reset_journal' ) as $table ) {
					$del( $table, 'user_id' );
				}
				$del( 'ccap_notification', 'recipient_user_id' );
				// RT-09/RT-10: without `keep_evidence_on_erasure` (not yet registered) certificates are deleted.
				$n += CertificateService::erase( $db, $user_id );
				$n += $db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_seat' ) . "` SET status = IF(status = 'active', 'removed', status), removed_at = COALESCE(removed_at, %s), user_id = NULL, invite_email = NULL, claim_token_hash = NULL WHERE user_id = %d", $db->now(), $user_id ) );
				$n += $db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_group' ) . '` SET owner_user_id = 0 WHERE owner_user_id = %d', $user_id ) );
				return $n;
			}
		);
		// Per-site notification preferences (user option, R1-21).
		if ( false !== get_user_option( 'course_captain_notification_prefs', $user_id ) ) {
			delete_user_option( $user_id, 'course_captain_notification_prefs' );
			++$count;
		}
		if ( $count > 0 ) {
			AuditLog::write(
				$db,
				Actor::cron(),
				'personal_data_erased',
				'user',
				hash( 'sha256', 'user:' . $user_id . '|' . wp_salt( 'auth' ) ),
				null,
				array( 'rows' => $count )
			);
			\CourseCaptain\Cache\CacheStore::bump_user( $user_id );
			do_action( 'course_captain_policy_changed' );
		}
		return $count;
	}

	/**
	 * Invitations of an address without an account (GM-103), address masked for nobody else.
	 *
	 * @param string $email Email.
	 * @return list<array<string, mixed>>
	 */
	private static function invitations( string $email ): array {
		if ( '' === $email ) {
			return array();
		}
		$db  = Database::current();
		$out = array();
		foreach ( $db->rows( $db->prepare( 'SELECT id, group_id, status, invited_at FROM `' . $db->table( 'ccap_seat' ) . '` WHERE invite_email = %s', strtolower( $email ) ) ) as $row ) {
			$out[] = array(
				'group_id'    => 'cc-invitations',
				'group_label' => 'Group invitations',
				'item_id'     => 'cc-invitations-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => 'group_id',
						'value' => (string) $row['group_id'],
					),
					array(
						'name'  => 'status',
						'value' => (string) $row['status'],
					),
					array(
						'name'  => 'invited_at',
						'value' => (string) $row['invited_at'],
					),
				),
			);
		}
		return $out;
	}

	/**
	 * Remove invitation addresses (and tokens) of an email.
	 *
	 * @param string $email Email.
	 * @return int
	 */
	private static function erase_invitations( string $email ): int {
		if ( '' === $email ) {
			return 0;
		}
		$db = Database::current();
		return $db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_seat' ) . "` SET invite_email = NULL, claim_token_hash = NULL, status = IF(status = 'invited', 'removed', status), removed_at = IF(status = 'removed', COALESCE(removed_at, %s), removed_at) WHERE invite_email = %s", $db->now(), strtolower( $email ) ) );
	}

	/**
	 * Suggested privacy policy text with the effective retention values (RR-7).
	 *
	 * @return void
	 */
	public static function policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p>' . esc_html__( 'Course Captain stores learning progress, answers, exam attempts, course access and group memberships of logged-in learners. Learning answers are kept for 2 years after the end of a course, exam attempts for 10 years, ended access records for 10 years and the audit log for 4 years. Personal data can be exported and erased with the WordPress privacy tools.', 'course-captain' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Course Captain', 'course-captain' ), wp_kses_post( $text ) );
	}
}
