<?php
/**
 * Audit view with event registry (R0-13 §4; R0-05 GM-138, GM-139).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Every audit action has a label and an area (GM-139). The view shows plain
 * text, filters by user, area, action, period and object ID, pages the
 * result, is restricted to `ccap_view_audit_log` and masks email
 * addresses (GM-138).
 */
final class AuditView {

	public const CAP      = 'ccap_view_audit_log';
	public const PER_PAGE = 50;

	/**
	 * Registry: action → [area, label].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function registry(): array {
		return array(
			'grant.manual_created'           => array( 'access', __( 'Access granted manually', 'course-captain' ) ),
			'grant.extended'                 => array( 'access', __( 'Access extended', 'course-captain' ) ),
			'group.created'                  => array( 'groups', __( 'Group created', 'course-captain' ) ),
			'group.token_rotated'            => array( 'groups', __( 'Group invitation link renewed', 'course-captain' ) ),
			'attempt_graded'                 => array( 'attempt', __( 'Exam attempt re-evaluated', 'course-captain' ) ),
			'settings_saved'                 => array( 'settings', __( 'Settings saved', 'course-captain' ) ),
			'settings_reset'                 => array( 'settings', __( 'Settings reset', 'course-captain' ) ),
			'outbox.replayed'                => array( 'ops', __( 'Event delivery replayed', 'course-captain' ) ),
			'import_approved'                => array( 'import', __( 'Import approved', 'course-captain' ) ),
			'import_published'               => array( 'import', __( 'Import published', 'course-captain' ) ),
			'import_rollback_started'        => array( 'import', __( 'Import rollback started', 'course-captain' ) ),
			'import_resumed'                 => array( 'import', __( 'Import resumed', 'course-captain' ) ),
			'import_auto_resumed'            => array( 'import', __( 'Import resumed automatically', 'course-captain' ) ),
			'import_cancel_requested'        => array( 'import', __( 'Import cancellation requested', 'course-captain' ) ),
			'import_threshold_exceeded'      => array( 'import', __( 'Import stopped: too many failed objects', 'course-captain' ) ),
			'import_course_held'             => array( 'import', __( 'Imported course held back by the course check', 'course-captain' ) ),
			'import_discarded'               => array( 'import', __( 'Import discarded', 'course-captain' ) ),
			'import_report_exported'         => array( 'import', __( 'Import report exported', 'course-captain' ) ),
			'hardening_state_changed'        => array( 'settings', __( 'Website hardening state changed', 'course-captain' ) ),
			'hardening_kill_switch_detected' => array( 'settings', __( 'Website hardening switched off by constant', 'course-captain' ) ),
			'maintenance_action'             => array( 'ops', __( 'Maintenance action', 'course-captain' ) ),
			'personal_data_erased'           => array( 'privacy', __( 'Personal data erased', 'course-captain' ) ),
			'retention_applied'              => array( 'ops', __( 'Retention applied', 'course-captain' ) ),
			'cron_task_failed'               => array( 'ops', __( 'Maintenance task failed', 'course-captain' ) ),
			'mail_template_saved'            => array( 'notifications', __( 'Mail template saved', 'course-captain' ) ),
			'notification_optout_changed'    => array( 'notifications', __( 'Notification opt-out changed', 'course-captain' ) ),
			'notification_resent'            => array( 'notifications', __( 'Notification sent again', 'course-captain' ) ),
			'invitation_resent'              => array( 'groups', __( 'Group invitation sent again', 'course-captain' ) ),
			'notifications_released'         => array( 'notifications', __( 'Held notifications released', 'course-captain' ) ),
			'notifications_discarded'        => array( 'notifications', __( 'Held notifications discarded', 'course-captain' ) ),
			'course_published'               => array( 'content', __( 'Course revision published', 'course-captain' ) ),
			'certificate_issued'             => array( 'certificates', __( 'Certificate issued manually', 'course-captain' ) ),
			'certificate_corrected'          => array( 'certificates', __( 'Certificate corrected', 'course-captain' ) ),
			'certificate_revoked'            => array( 'certificates', __( 'Certificate revoked', 'course-captain' ) ),
			'certificate_review_required'    => array( 'certificates', __( 'Certificate needs review after re-evaluation', 'course-captain' ) ),
			'job_retried'                    => array( 'ops', __( 'Job retried', 'course-captain' ) ),
			'job_discarded'                  => array( 'ops', __( 'Dead letters discarded', 'course-captain' ) ),
			'cache_flushed'                  => array( 'ops', __( 'Cache emptied', 'course-captain' ) ),
			'diagnostics_exported'           => array( 'ops', __( 'Diagnostics package exported', 'course-captain' ) ),
			'site_identity_confirmed'        => array( 'ops', __( 'Site identity confirmed after restore, move or copy', 'course-captain' ) ),
		);
	}

	/**
	 * Filtered, paged entries.
	 *
	 * @param Actor                $actor   Viewer.
	 * @param array<string, mixed> $filters `user`, `area`, `action`, `from`, `to`, `object_id`.
	 * @param int                  $page    Page (1-based).
	 * @return array{items: list<array{id: int, time: string, area: string, label: string, actor: string, object: string, details: string}>, total: int, pages: int}
	 * @throws CourseException Without the capability.
	 */
	public static function query( Actor $actor, array $filters = array(), int $page = 1 ): array {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, self::CAP ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to view the audit log.', 403 );
		}
		$db       = Database::current();
		$where    = array( '1 = 1' );
		$registry = self::registry();
		if ( ! empty( $filters['user'] ) ) {
			$where[] = $db->prepare( '(actor_user_id = %d OR subject_user_id = %d)', (int) $filters['user'], (int) $filters['user'] );
		}
		if ( ! empty( $filters['action'] ) ) {
			$where[] = $db->prepare( 'action = %s', (string) $filters['action'] );
		}
		if ( ! empty( $filters['area'] ) ) {
			$actions = array_keys( array_filter( $registry, static fn( array $r ): bool => $r[0] === $filters['area'] ) );
			$where[] = array() === $actions ? '1 = 0' : 'action IN (' . implode( ',', array_map( static fn( string $a ): string => $db->prepare( '%s', $a ), $actions ) ) . ')';
		}
		if ( ! empty( $filters['from'] ) ) {
			$where[] = $db->prepare( 'created_at >= %s', (string) $filters['from'] );
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[] = $db->prepare( 'created_at <= %s', (string) $filters['to'] );
		}
		if ( ! empty( $filters['object_id'] ) ) {
			$where[] = $db->prepare( 'object_id = %s', (string) $filters['object_id'] );
		}
		$table = $db->table( 'ccap_audit_log' );
		$cond  = implode( ' AND ', $where );
		$total = (int) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE {$cond}" );
		$page  = max( 1, $page );
		$rows  = $db->rows( $db->prepare( "SELECT * FROM `{$table}` WHERE {$cond} ORDER BY id DESC LIMIT %d OFFSET %d", self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Conditions prepared above.
		$items = array();
		foreach ( $rows as $row ) {
			$entry   = $registry[ (string) $row['action'] ] ?? array( 'other', (string) $row['action'] );
			$user    = null === $row['actor_user_id'] ? null : get_userdata( (int) $row['actor_user_id'] );
			$items[] = array(
				'id'      => (int) $row['id'],
				'time'    => (string) $row['created_at'],
				'area'    => $entry[0],
				'label'   => $entry[1],
				'actor'   => $user instanceof \WP_User ? $user->display_name : ( 'user' === $row['actor_type'] ? __( 'Deleted user', 'course-captain' ) : __( 'System', 'course-captain' ) ),
				'object'  => (string) $row['object_type'] . ' ' . (string) $row['object_id'],
				'details' => self::details( (string) ( $row['context'] ?? '' ) ),
			);
		}
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) max( 1, ceil( $total / self::PER_PAGE ) ),
		);
	}

	/**
	 * Context as plain text with masked emails.
	 *
	 * @param string $json Context JSON.
	 * @return string
	 */
	public static function details( string $json ): string {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$parts = array();
		foreach ( $data as $key => $value ) {
			$text    = is_scalar( $value ) || null === $value ? (string) wp_json_encode( $value ) : (string) wp_json_encode( $value );
			$text    = trim( $text, '"' );
			$parts[] = $key . ': ' . self::mask( $text );
		}
		return implode( '; ', $parts );
	}

	/**
	 * Mask email addresses (`a***@example.org`).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function mask( string $text ): string {
		return (string) preg_replace_callback( '/([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/', static fn( array $m ): string => $m[1] . '***@' . $m[2], $text );
	}
}
