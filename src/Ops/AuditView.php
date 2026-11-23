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
 * text, filters by user, area, action, period, object ID and grant
 * reference (source reference or request key, GM-090), pages the
 * result, is restricted to `ccap_view_audit_log` and masks email
 * addresses (GM-138).
 */
final class AuditView {

	public const CAP      = 'ccap_view_audit_log';
	public const PER_PAGE = 50;
	public const CSV_MAX  = 5000;

	/** Course-level actions shown in the settings view besides the area `settings` (SET-CC-27). */
	public const SETTINGS_EXTRA = array( 'course_settings_saved', 'course_settings_reset' );

	/**
	 * Registry: action → [area, label].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function registry(): array {
		return array(
			'grant.manual_created'           => array( 'access', __( 'Access granted manually', 'course-captain' ) ),
			'grant.self_enrolled'            => array( 'access', __( 'Self-enrolled in a course', 'course-captain' ) ),
			'grant.extended'                 => array( 'access', __( 'Access extended', 'course-captain' ) ),
			'access_filter_ignored'          => array( 'access', __( 'An extension tried to grant access (ignored)', 'course-captain' ) ),
			'grant.source_created'           => array( 'access', __( 'Access granted by a source', 'course-captain' ) ),
			'grant.source_revoked'           => array( 'access', __( 'Access revoked by a source', 'course-captain' ) ),
			'grant.source_kept'              => array( 'access', __( 'Access kept after a source revocation (rule "keep")', 'course-captain' ) ),
			'group.created'                  => array( 'groups', __( 'Group created', 'course-captain' ) ),
			'group.token_rotated'            => array( 'groups', __( 'Group invitation link renewed', 'course-captain' ) ),
			'group.validity_changed'         => array( 'groups', __( 'Group validity changed', 'course-captain' ) ),
			'group.owner_changed'            => array( 'groups', __( 'Group owner changed', 'course-captain' ) ),
			'group.policy_changed'           => array( 'groups', __( 'Group invitation rule changed', 'course-captain' ) ),
			'group.renamed'                  => array( 'groups', __( 'Group renamed', 'course-captain' ) ),
			'progress_reset'                 => array( 'learner', __( 'Learning progress reset', 'course-captain' ) ),
			'attempt_graded'                 => array( 'attempt', __( 'Exam attempt re-evaluated', 'course-captain' ) ),
			'assignment_graded'              => array( 'attempt', __( 'Assignment graded', 'course-captain' ) ),
			'assignment_regraded'            => array( 'attempt', __( 'Assignment re-graded', 'course-captain' ) ),
			'assignment_returned'            => array( 'attempt', __( 'Assignment returned for revision', 'course-captain' ) ),
			'assignment_file_accessed'       => array( 'attempt', __( 'Assignment file opened by a grader', 'course-captain' ) ),
			'settings_saved'                 => array( 'settings', __( 'Settings saved', 'course-captain' ) ),
			'settings_reset'                 => array( 'settings', __( 'Settings reset', 'course-captain' ) ),
			'settings_lock_set'              => array( 'settings', __( 'Setting locked for all courses', 'course-captain' ) ),
			'settings_lock_removed'          => array( 'settings', __( 'Setting lock removed', 'course-captain' ) ),
			'settings_overrides_reset'       => array( 'settings', __( 'Course and exam overrides reset to inherit', 'course-captain' ) ),
			'settings_exported'              => array( 'settings', __( 'Settings exported', 'course-captain' ) ),
			'audit_exported'                 => array( 'ops', __( 'Audit log exported', 'course-captain' ) ),
			'course_settings_saved'          => array( 'content', __( 'Course settings saved', 'course-captain' ) ),
			'course_settings_reset'          => array( 'content', __( 'Course settings reset to the defaults', 'course-captain' ) ),
			'settings_imported'              => array( 'settings', __( 'Settings imported', 'course-captain' ) ),
			'role_caps_changed'              => array( 'settings', __( 'Role permissions changed', 'course-captain' ) ),
			'privacy_request_automated'      => array( 'privacy', __( 'Data request processed automatically', 'course-captain' ) ),
			'privacy_request_created'        => array( 'privacy', __( 'Data request filed by the person', 'course-captain' ) ),
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
			'import_link_downloaded'         => array( 'import', __( 'Import package downloaded from a link', 'course-captain' ) ),
			'rights_override_set'            => array( 'import', __( 'Rights status of a content item changed', 'course-captain' ) ),
			'rights_bulk_release'            => array( 'import', __( 'Unclear rights of an import released', 'course-captain' ) ),
			'media_quarantined'              => array( 'import', __( 'Disallowed legacy media files moved to the quarantine', 'course-captain' ) ),
			'hardening_state_changed'        => array( 'settings', __( 'Website hardening state changed', 'course-captain' ) ),
			'hardening_kill_switch_detected' => array( 'settings', __( 'Website hardening switched off by constant', 'course-captain' ) ),
			'maintenance_action'             => array( 'ops', __( 'Maintenance action', 'course-captain' ) ),
			'personal_data_erased'           => array( 'privacy', __( 'Personal data erased', 'course-captain' ) ),
			'retention_applied'              => array( 'ops', __( 'Retention applied', 'course-captain' ) ),
			'audit_pruned'                   => array( 'ops', __( 'Old audit entries removed', 'course-captain' ) ),
			'cron_task_failed'               => array( 'ops', __( 'Maintenance task failed', 'course-captain' ) ),
			'mail_template_saved'            => array( 'notifications', __( 'Mail template saved', 'course-captain' ) ),
			'mail_template_reset'            => array( 'notifications', __( 'Built-in mail text restored', 'course-captain' ) ),
			'mail_templates_imported'        => array( 'notifications', __( 'Mail texts imported', 'course-captain' ) ),
			'notification_optout_changed'    => array( 'notifications', __( 'Notification opt-out changed', 'course-captain' ) ),
			'notification_resent'            => array( 'notifications', __( 'Notification sent again', 'course-captain' ) ),
			'invitation_resent'              => array( 'groups', __( 'Group invitation sent again', 'course-captain' ) ),
			'notifications_released'         => array( 'notifications', __( 'Held notifications released', 'course-captain' ) ),
			'notifications_discarded'        => array( 'notifications', __( 'Held notifications discarded', 'course-captain' ) ),
			'course_published'               => array( 'content', __( 'Course revision published', 'course-captain' ) ),
			'course_enrollment_closed'       => array( 'content', __( 'Enrollment of a course closed', 'course-captain' ) ),
			'course_enrollment_opened'       => array( 'content', __( 'Enrollment of a course reopened', 'course-captain' ) ),
			'report_exported'                => array( 'learner', __( 'Progress report exported as CSV', 'course-captain' ) ),
			'question_saved'                 => array( 'content', __( 'Question saved', 'course-captain' ) ),
			'question_published'             => array( 'content', __( 'Question published', 'course-captain' ) ),
			'question_archived'              => array( 'content', __( 'Question archived', 'course-captain' ) ),
			'question_pool_changed'          => array( 'content', __( 'Questions of a module changed', 'course-captain' ) ),
			'exam_rule_saved'                => array( 'content', __( 'Exam saved', 'course-captain' ) ),
			'exam_rule_published'            => array( 'content', __( 'Exam published', 'course-captain' ) ),
			'grant.revoked_manually'         => array( 'access', __( 'Access ended manually', 'course-captain' ) ),
			'grant.suspended_manually'       => array( 'access', __( 'Access suspended manually', 'course-captain' ) ),
			'grant.resumed_manually'         => array( 'access', __( 'Access resumed manually', 'course-captain' ) ),
			'access_rule_changed'            => array( 'access', __( 'Access rule of a course changed', 'course-captain' ) ),
			'question_tags_changed'          => array( 'content', __( 'Question tags changed', 'course-captain' ) ),
			'certificate_issued'             => array( 'certificates', __( 'Certificate issued manually', 'course-captain' ) ),
			'certificate_corrected'          => array( 'certificates', __( 'Certificate corrected', 'course-captain' ) ),
			'certificate_revoked'            => array( 'certificates', __( 'Certificate revoked', 'course-captain' ) ),
			'certificates_exported'          => array( 'certificates', __( 'Certificate list exported', 'course-captain' ) ),
			'certificate_review_required'    => array( 'certificates', __( 'Certificate needs review after re-evaluation', 'course-captain' ) ),
			'job_retried'                    => array( 'ops', __( 'Job retried', 'course-captain' ) ),
			'job_discarded'                  => array( 'ops', __( 'Dead letters discarded', 'course-captain' ) ),
			'cache_flushed'                  => array( 'ops', __( 'Cache emptied', 'course-captain' ) ),
			'diagnostics_exported'           => array( 'ops', __( 'Diagnostics package exported', 'course-captain' ) ),
			'site_identity_confirmed'        => array( 'ops', __( 'Site identity confirmed after restore, move or copy', 'course-captain' ) ),
			'storage_moved'                  => array( 'ops', __( 'Protected folders moved', 'course-captain' ) ),
			'storage_move_failed'            => array( 'ops', __( 'Moving the protected folders failed', 'course-captain' ) ),
		);
	}

	/**
	 * Filtered, paged entries.
	 *
	 * @param Actor                $actor   Viewer.
	 * @param array<string, mixed> $filters `user`, `area`, `action`, `from`, `to`, `object_id`, `reference`.
	 * @param int                  $page    Page (1-based; 0 = all up to CSV_MAX for the export).
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
		if ( ! empty( $filters['reference'] ) ) {
			// Entries of the grants with this source reference (GM-090).
			$where[] = $db->prepare( "object_type = 'grant' AND object_id IN (SELECT CAST(id AS CHAR) FROM `" . $db->table( 'ccap_grant' ) . '` WHERE source_id = %s)', (string) $filters['reference'] );
		}
		$table = $db->table( 'ccap_audit_log' );
		$cond  = implode( ' AND ', $where );
		$total = (int) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE {$cond}" );
		$all   = 0 === $page;
		$page  = max( 1, $page );
		$rows  = $db->rows( $db->prepare( "SELECT * FROM `{$table}` WHERE {$cond} ORDER BY id DESC LIMIT %d OFFSET %d", $all ? self::CSV_MAX : self::PER_PAGE, $all ? 0 : ( $page - 1 ) * self::PER_PAGE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Conditions prepared above.
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
	 * Actions of the settings view: area `settings` plus course overrides.
	 *
	 * @return list<string>
	 */
	public static function settings_actions(): array {
		$actions = array_keys( array_filter( self::registry(), static fn( array $r ): bool => 'settings' === $r[0] ) );
		return array_values( array_merge( array_map( 'strval', $actions ), self::SETTINGS_EXTRA ) );
	}

	/**
	 * Settings view (SET-CC-27): one row per changed key with before and
	 * after. Values of `personal`/`secret` fields are never shown, also
	 * not for entries written before a field changed its class (only
	 * "changed"); emails are masked. Filters as {@see self::query()} plus
	 * `key` (`tab` or `tab.field`). Paged by audit entries.
	 *
	 * @param Actor                $actor   Viewer.
	 * @param array<string, mixed> $filters Filters.
	 * @param int                  $page    Page (1-based, 0 = all up to CSV_MAX entries).
	 * @return array{items: list<array{id: int, time: string, label: string, actor: string, via: string, object: string, key: string, before: string, after: string}>, total: int, pages: int}
	 * @throws CourseException Without the capability.
	 */
	public static function settings_changes( Actor $actor, array $filters = array(), int $page = 1 ): array {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, self::CAP ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to view the audit log.', 403 );
		}
		$db      = Database::current();
		$actions = self::settings_actions();
		if ( ! empty( $filters['action'] ) ) {
			$actions = array_values( array_intersect( $actions, array( (string) $filters['action'] ) ) );
		}
		$where = array( array() === $actions ? '1 = 0' : 'action IN (' . implode( ',', array_map( static fn( string $a ): string => $db->prepare( '%s', $a ), $actions ) ) . ')' );
		if ( ! empty( $filters['user'] ) ) {
			$where[] = $db->prepare( 'actor_user_id = %d', (int) $filters['user'] );
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
		$key = isset( $filters['key'] ) ? (string) $filters['key'] : '';
		if ( '' !== $key ) {
			$needle  = str_contains( $key, '.' ) ? substr( $key, (int) strpos( $key, '.' ) + 1 ) : $key;
			$where[] = $db->prepare( 'context LIKE %s', '%' . $db->wpdb()->esc_like( $needle ) . '%' );
		}
		$table = $db->table( 'ccap_audit_log' );
		$cond  = implode( ' AND ', $where );
		$total = (int) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE {$cond}" );
		$limit = $page > 0 ? self::PER_PAGE : self::CSV_MAX;
		$rows  = $db->rows( $db->prepare( "SELECT * FROM `{$table}` WHERE {$cond} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $page > 0 ? ( $page - 1 ) * self::PER_PAGE : 0 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Conditions prepared above.
		$items = array();
		foreach ( $rows as $row ) {
			foreach ( self::expand( $row ) as $change ) {
				if ( '' !== $key && $change['key'] !== $key && ! str_starts_with( $change['key'], $key . '.' ) ) {
					continue;
				}
				$items[] = $change;
			}
		}
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) max( 1, ceil( $total / self::PER_PAGE ) ),
		);
	}

	/**
	 * CSV export of the current filter (SET-CC-27): settings view or plain
	 * entries, at most CSV_MAX entries, formula-safe, UTF-8 with BOM;
	 * audited as `audit_exported`.
	 *
	 * @param Actor                $actor   Viewer.
	 * @param array<string, mixed> $filters Filters.
	 * @param bool                 $settings Settings view.
	 * @return string
	 * @throws CourseException Without the capability.
	 */
	public static function csv( Actor $actor, array $filters, bool $settings ): string {
		$cell = array( \CourseCaptain\Certificate\CertificateAdmin::class, 'csv_cell' );
		if ( $settings ) {
			$result  = self::settings_changes( $actor, $filters, 0 );
			$columns = array( 'time', 'label', 'actor', 'via', 'object', 'key', 'before', 'after' );
		} else {
			$result  = self::query( $actor, $filters, 0 );
			$columns = array( 'time', 'area', 'label', 'actor', 'object', 'details' );
		}
		$lines = array( implode( ',', $columns ) );
		foreach ( $result['items'] as $item ) {
			$lines[] = implode( ',', array_map( static fn( string $c ): string => (string) call_user_func( $cell, (string) ( $item[ $c ] ?? '' ) ), $columns ) );
		}
		\CourseCaptain\Event\AuditLog::write(
			Database::current(),
			$actor,
			'audit_exported',
			'audit',
			$settings ? 'settings' : 'entries',
			null,
			array(
				'count'   => count( $result['items'] ),
				'filters' => array_intersect_key( $filters, array_flip( array( 'user', 'area', 'action', 'from', 'to', 'object_id', 'key' ) ) ),
			)
		);
		return "\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Rows of one audit entry in the settings view.
	 *
	 * @param array<string, mixed> $row Audit row.
	 * @return list<array{id: int, time: string, label: string, actor: string, via: string, object: string, key: string, before: string, after: string}>
	 */
	private static function expand( array $row ): array {
		$registry = self::registry();
		$action   = (string) $row['action'];
		$context  = json_decode( (string) ( $row['context'] ?? '' ), true );
		$context  = is_array( $context ) ? $context : array();
		$user     = null === $row['actor_user_id'] ? null : get_userdata( (int) $row['actor_user_id'] );
		$base     = array(
			'id'     => (int) $row['id'],
			'time'   => (string) $row['created_at'],
			'label'  => ( $registry[ $action ] ?? array( '', $action ) )[1],
			'actor'  => $user instanceof \WP_User ? $user->display_name : ( 'user' === $row['actor_type'] ? __( 'Deleted user', 'course-captain' ) : __( 'System', 'course-captain' ) . ' (' . ( isset( $context['actor'] ) && is_string( $context['actor'] ) ? $context['actor'] : (string) $row['actor_type'] ) . ')' ),
			'via'    => isset( $context['context'] ) && is_string( $context['context'] ) ? $context['context'] : '',
			'object' => (string) $row['object_type'] . ' ' . (string) $row['object_id'],
		);
		$out      = array();
		$add      = static function ( string $key, string $before, string $after ) use ( &$out, $base ): void {
			$out[] = $base + array(
				'key'    => $key,
				'before' => self::mask( $before ),
				'after'  => self::mask( $after ),
			);
		};
		switch ( $action ) {
			case 'settings_saved':
			case 'settings_reset':
				$tab = (string) ( $context['tab'] ?? $row['object_id'] );
				foreach ( (array) ( $context['changed'] ?? array() ) as $field => $diff ) {
					$key = $tab . '.' . (string) $field;
					if ( ! is_array( $diff ) || ! self::shows_values( $tab, (string) $field ) ) {
						$add( $key, __( '(not shown)', 'course-captain' ), __( 'changed', 'course-captain' ) );
						continue;
					}
					$add( $key, self::value( $diff['before'] ?? null ), self::value( $diff['after'] ?? null ) );
				}
				break;
			case 'settings_lock_set':
				$key   = (string) ( $context['key'] ?? $row['object_id'] );
				$value = str_contains( $key, '.' ) && self::shows_values( ...explode( '.', $key, 2 ) ) ? self::value( $context['value'] ?? null ) : __( '(not shown)', 'course-captain' );
				/* translators: 1: locked value, 2: reason */
				$add( $key, __( 'not locked', 'course-captain' ), sprintf( __( 'locked to %1$s (%2$s)', 'course-captain' ), $value, (string) ( $context['reason'] ?? '' ) ) );
				break;
			case 'settings_lock_removed':
				$add( (string) ( $context['key'] ?? $row['object_id'] ), __( 'locked', 'course-captain' ), __( 'not locked', 'course-captain' ) );
				break;
			case 'settings_overrides_reset':
				$add( (string) ( $context['key'] ?? $row['object_id'] ), implode( ', ', array_map( 'strval', (array) ( $context['reset'] ?? array() ) ) ), __( 'inherit', 'course-captain' ) );
				break;
			case 'role_caps_changed':
				$add( 'roles.' . (string) ( $context['role'] ?? $row['object_id'] ), implode( ', ', array_map( 'strval', (array) ( $context['removed'] ?? array() ) ) ), implode( ', ', array_map( 'strval', (array) ( $context['added'] ?? array() ) ) ) );
				break;
			case 'course_settings_saved':
			case 'course_settings_reset':
				foreach ( (array) ( $context['keys'] ?? array() ) as $key ) {
					$add( (string) $key, __( '(own value of the course)', 'course-captain' ), 'course_settings_reset' === $action ? __( 'inherit', 'course-captain' ) : __( 'changed', 'course-captain' ) );
				}
				break;
			case 'settings_imported':
				foreach ( (array) ( $context['keys'] ?? array() ) as $tab => $fields ) {
					$add( (string) $tab, '', implode( ', ', array_map( 'strval', (array) $fields ) ) );
				}
				foreach ( (array) ( $context['surfaces'] ?? array() ) as $surface => $items ) {
					$add( (string) $surface, '', implode( ', ', array_map( 'strval', (array) $items ) ) );
				}
				break;
			default:
				$add( (string) $row['object_id'], '', self::details( (string) ( $row['context'] ?? '' ) ) );
		}
		return $out;
	}

	/**
	 * Whether the register allows showing values of a field (public/internal only).
	 *
	 * @param string $tab   Tab.
	 * @param string $field Field.
	 * @return bool
	 */
	private static function shows_values( string $tab, string $field = '' ): bool {
		$def = \CourseCaptain\Settings\SettingsSchema::fields()[ $tab ][ $field ] ?? null;
		return is_array( $def ) && in_array( $def['privacy'], array( 'public', 'internal' ), true );
	}

	/**
	 * A setting value as text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function value( $value ): string {
		if ( is_string( $value ) ) {
			return $value;
		}
		return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
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
