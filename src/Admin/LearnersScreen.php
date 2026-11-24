<?php
/**
 * Admin screen A-09 "Learners", area "Access" (R0-11 §2, R0-30 §1.2 Pos 5, E-27, E-81).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\GrantTransitions;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Per course: the access list (learner, scope, status, validity, source)
 * with the course progress next to it, a manual grant form (whole course
 * or selected modules, one grant per module, E-27; reason required) and
 * "end access" with confirmation. The learner detail shows the progress
 * per module and lesson. Addresses are masked (E-81). Granting needs
 * M-14 (`ccap_manage_grants`), ending M-13; viewing needs #13
 * (`ccap_view_learner_details`) and the learner object rule.
 */
final class LearnersScreen {

	public const SLUG   = 'course-captain-learners';
	public const ACTION = 'ccap_learner_admin';
	public const CAP    = 'ccap_view_learner_details';
	public const OPS    = array( 'grant', 'revoke', 'enrollment', 'suspend', 'resume', 'extend', 'reset' );

	/** Confirmation words of the progress reset (typed literally, either one; UI-F8). */
	public const RESET_WORDS = array( 'ZURÜCKSETZEN', 'RESET' );

	/** Admin-post action of the report CSV download (SET-CC-26). */
	public const EXPORT_ACTION = 'ccap_report_export';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				add_submenu_page( EditorScreens::MENU, __( 'Learners', 'course-captain' ), _x( 'Learners', 'admin menu', 'course-captain' ), self::CAP, self::SLUG, array( self::class, 'render' ), 5 );
			},
			20
		);
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( self::class, 'handle_export' ) );
		add_action( 'admin_post_' . AccessRulesPanel::ACTION, array( AccessRulesPanel::class, 'handle' ) );
	}

	/**
	 * Admin-post: progress report CSV (SET-CC-26).
	 *
	 * @return void
	 */
	public static function handle_export(): void {
		$out = self::export( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in export().
		if ( '' !== $out['error'] ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => self::SLUG,
						'course'      => $out['course'],
						'ccap_result' => $out['error'],
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="progress-' . (int) $out['course'] . '-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $out['csv']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, cells quoted and neutralized.
		exit;
	}

	/**
	 * Testable core of the CSV download.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data (`course`, `columns[]`, `pseudonymize`).
	 * @param int                  $user_id Viewer.
	 * @return array{course: int, csv: string, error: string}
	 */
	public static function export( array $post, int $user_id ): array {
		$course = absint( $post['course'] ?? 0 );
		$out    = array(
			'course' => $course,
			'csv'    => '',
			'error'  => '',
		);
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::EXPORT_ACTION . ':' . $course ) ) {
			$out['error'] = 'ccap_invalid_nonce';
			return $out;
		}
		$columns = is_array( $post['columns'] ?? null ) ? array_values( array_filter( $post['columns'], 'is_string' ) ) : array();
		try {
			$out['csv'] = \CourseCaptain\Progress\CourseReport::current()->csv( Actor::user( $user_id ), $course, $columns, ! empty( $post['pseudonymize'] ) );
		} catch ( CourseException $e ) {
			$out['error'] = 'ccap_validation_failed' === $e->error_code ? 'ccap_columns_required' : $e->error_code;
		}
		return $out;
	}

	/**
	 * Page output.
	 *
	 * @return void
	 */
	public static function render(): void {
		echo self::html( wp_unslash( $_GET ), get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.NonceVerification.Recommended -- Escaped while building; read-only view parameters.
	}

	/**
	 * Admin-post entry point.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$out  = self::process( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process().
		$args = array(
			'page'        => self::SLUG,
			'course'      => $out['course'],
			'ccap_result' => $out['result'],
			'ccap_count'  => $out['count'],
		);
		if ( in_array( $out['op'], array( 'suspend', 'resume', 'extend' ), true ) ) {
			$args['grant'] = absint( $_POST['object'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process(); display only.
		} elseif ( 'reset' === $out['op'] ) {
			$args['learner'] = absint( $_POST['object'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process(); display only.
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Testable core of the handler.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @param int                  $user_id Manager.
	 * @return array{op: string, course: int, result: string, count: int}
	 * @throws CourseException Never; domain errors become the result code.
	 */
	public static function process( array $post, int $user_id ): array {
		$op     = sanitize_key( (string) ( $post['op'] ?? '' ) );
		$course = absint( $post['course'] ?? 0 );
		$out    = array(
			'op'     => $op,
			'course' => $course,
			'result' => 'ok',
			'count'  => 0,
		);
		if ( ! in_array( $op, self::OPS, true ) ) {
			$out['result'] = 'ccap_not_found';
			return $out;
		}
		if ( ! AdminUi::verify( self::ACTION, $post ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		$actor = Actor::user( $user_id );
		try {
			if ( 'grant' === $op ) {
				$out['count'] = self::grant( $actor, $course, $post );
			} elseif ( 'enrollment' === $op ) {
				// UI-F2 / E-108: close or reopen the enrollment of a course.
				$closed       = 'closed' === (string) ( $post['state'] ?? '' );
				$out['count'] = (int) \CourseCaptain\Access\SelfEnrollment::current()->set_closed( $actor, $course, $closed );
			} elseif ( in_array( $op, array( 'suspend', 'resume', 'extend' ), true ) ) {
				$out['count'] = self::change_grant( $actor, $op, $course, absint( $post['object'] ?? 0 ), $post );
			} elseif ( 'reset' === $op ) {
				$out['count'] = self::reset( $actor, $course, absint( $post['object'] ?? 0 ), $post );
			} else {
				$grant = absint( $post['object'] ?? 0 );
				if ( empty( $post['confirm'] ) ) {
					throw new CourseException( 'ccap_confirmation_required', 'confirm', 422 );
				}
				$reason = trim( sanitize_text_field( (string) ( $post['reason'] ?? '' ) ) );
				if ( '' === $reason ) {
					throw new CourseException( 'ccap_reason_required', 'reason', 422 );
				}
				CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::MANAGE_GRANT, array( $grant ), array( 'operation' => 'change' ) ), 'Not allowed to change access.' );
				if ( GrantTransitions::REVOKED !== GrantService::current()->transition( $actor, $grant, GrantTransitions::REVOKED, 'manual_revoked', true ) ) {
					throw new CourseException( 'ccap_conflict', 'state', 409 );
				}
				AuditLog::write( Database::current(), $actor, 'grant.revoked_manually', 'grant', (string) $grant, $reason, array( 'course_id' => $course ) );
				$out['count'] = 1;
			}
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
		}
		return $out;
	}

	/**
	 * Manual grant: whole course or one grant per selected module (E-27).
	 *
	 * @param Actor                $actor  Manager.
	 * @param int                  $course Course.
	 * @param array<string, mixed> $post   POST data.
	 * @return int Number of grants.
	 * @throws CourseException On unknown learner, missing reason or rights.
	 */
	private static function grant( Actor $actor, int $course, array $post ): int {
		$learner = self::user_id( trim( sanitize_text_field( (string) ( $post['learner'] ?? '' ) ) ) );
		if ( 0 === $learner ) {
			throw new CourseException( 'ccap_learner_unknown', 'learner', 422 );
		}
		$key = (string) ( $post['request_key'] ?? '' );
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/', $key ) ) {
			throw new CourseException( 'ccap_invalid_param', 'request_key', 400 );
		}
		$options = array();
		$until   = trim( (string) ( $post['valid_until'] ?? '' ) );
		if ( '' !== $until ) {
			if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ) {
				throw new CourseException( 'ccap_invalid_param', 'valid_until', 422 );
			}
			$options['valid_until'] = $until . ' 23:59:59';
		}
		$modules = array();
		if ( 'modules' === ( $post['scope'] ?? 'course' ) ) {
			$allowed = array_map( static fn( array $l ): int => $l['module_id'], CourseStructure::current()->modules( $course ) );
			$modules = array_values( array_intersect( array_map( 'intval', (array) ( $post['modules'] ?? array() ) ), $allowed ) );
			if ( array() === $modules ) {
				throw new CourseException( 'ccap_modules_required', 'modules', 422 );
			}
		}
		$reason = (string) ( $post['reason'] ?? '' );
		$count  = 0;
		foreach ( array() === $modules ? array( 0 ) : $modules as $module ) {
			// One idempotency key per grant, derived from the form key.
			$hash = md5( $key . ':' . $module );
			$sub  = 0 === $module ? $key : substr( $hash, 0, 8 ) . '-' . substr( $hash, 8, 4 ) . '-' . substr( $hash, 12, 4 ) . '-' . substr( $hash, 16, 4 ) . '-' . substr( $hash, 20, 12 );
			GrantService::current()->grant_manual( $actor, $learner, $course, $sub, $reason, $options + array( 'module_id' => $module ) );
			++$count;
		}
		return $count;
	}

	/**
	 * Suspend, resume or extend one access (UI-F8): reason required, audited,
	 * through {@see GrantService} (state machine, CAS, outbox).
	 *
	 * @param Actor                $actor  Manager (M-13).
	 * @param string               $op     suspend|resume|extend.
	 * @param int                  $course Course of the screen.
	 * @param int                  $grant  Grant.
	 * @param array<string, mixed> $post   POST data.
	 * @return int 1 on change.
	 * @throws CourseException `ccap_reason_required`, `ccap_invalid_param`, `ccap_conflict`, `ccap_not_found`, `ccap_forbidden`.
	 */
	private static function change_grant( Actor $actor, string $op, int $course, int $grant, array $post ): int {
		$db  = Database::current();
		$row = $db->row( $db->prepare( 'SELECT id, user_id, course_id, status FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $grant ) );
		if ( null === $row || (int) $row['course_id'] !== $course ) {
			throw new CourseException( 'ccap_not_found', 'grant', 404 );
		}
		$reason = trim( sanitize_text_field( (string) ( $post['reason'] ?? '' ) ) );
		if ( '' === $reason ) {
			throw new CourseException( 'ccap_reason_required', 'reason', 422 );
		}
		CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::MANAGE_GRANT, array( $grant ), array( 'operation' => 'change' ) ), 'Not allowed to change access.' );
		$service = GrantService::current();
		if ( 'extend' === $op ) {
			$until = trim( (string) ( $post['valid_until'] ?? '' ) );
			if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) || false === strtotime( $until ) || $until . ' 23:59:59' <= $db->now() ) {
				throw new CourseException( 'ccap_date_invalid', 'valid_until', 422 );
			}
			$service->extend( $actor, $grant, $until . ' 23:59:59', $reason );
			return 1;
		}
		$to = 'suspend' === $op ? GrantTransitions::SUSPENDED : GrantTransitions::ACTIVE;
		if ( $to !== $service->transition( $actor, $grant, $to, 'suspend' === $op ? 'manual_suspended' : 'manual_resumed', true ) ) {
			throw new CourseException( 'ccap_conflict', 'state', 409 );
		}
		if ( 'suspend' === $op ) {
			AuditLog::write( $db, $actor, 'grant.suspended_manually', 'grant', (string) $grant, $reason, array( 'course_id' => $course ), (int) $row['user_id'] );
		} else {
			AuditLog::write( $db, $actor, 'grant.resumed_manually', 'grant', (string) $grant, $reason, array( 'course_id' => $course ), (int) $row['user_id'] );
		}
		return 1;
	}

	/**
	 * Reset the course progress of a learner (UI-F8, E-70, R0-23 §3.3):
	 * reason and typed confirmation word required; the previous state goes
	 * to the reset journal (E-138), the audit entry names the row counts.
	 * Exam attempts, answers and certificates stay; the question learning
	 * states stay unless explicitly included (E-106).
	 *
	 * @param Actor                $actor   Manager (M-12).
	 * @param int                  $course  Course.
	 * @param int                  $learner Learner.
	 * @param array<string, mixed> $post    POST data.
	 * @return int Number of removed progress rows.
	 * @throws CourseException `ccap_reason_required`, `ccap_confirm_word`, rights and lookup errors.
	 */
	private static function reset( Actor $actor, int $course, int $learner, array $post ): int {
		$reason = trim( sanitize_text_field( (string) ( $post['reason'] ?? '' ) ) );
		if ( '' === $reason ) {
			throw new CourseException( 'ccap_reason_required', 'reason', 422 );
		}
		$word = mb_strtoupper( trim( sanitize_text_field( (string) ( $post['confirm_word'] ?? '' ) ) ) );
		if ( ! in_array( $word, self::RESET_WORDS, true ) ) {
			throw new CourseException( 'ccap_confirm_word', 'confirm_word', 422 );
		}
		CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::VIEW_LEARNER, array( $learner, $course ) ), 'Not allowed to see this learner.' );
		$result = \CourseCaptain\Progress\ProgressReset::current()->reset_course( $actor, $learner, $course, $reason, ! empty( $post['questions'] ) );
		return (int) array_sum( $result['rows'] );
	}

	/**
	 * Account from ID, login or address.
	 *
	 * @param string $value Input.
	 * @return int 0 when unknown.
	 */
	private static function user_id( string $value ): int {
		if ( '' === $value ) {
			return 0;
		}
		$user = ctype_digit( $value ) ? get_userdata( (int) $value ) : ( is_email( $value ) ? get_user_by( 'email', $value ) : get_user_by( 'login', $value ) );
		return $user instanceof \WP_User ? (int) $user->ID : 0;
	}

	/**
	 * Masked address (E-81): first letter and domain.
	 *
	 * @param string $email Address.
	 * @return string
	 */
	public static function mask( string $email ): string {
		$at = strpos( $email, '@' );
		if ( false === $at || $at < 1 ) {
			return '';
		}
		return mb_substr( $email, 0, 1 ) . '…' . substr( $email, $at );
	}

	/**
	 * Page HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 */
	public static function html( array $request, int $user_id ): string {
		$html = '<div class="wrap"><h1>' . esc_html__( 'Learners', 'course-captain' ) . '</h1>';
		if ( ! user_can( $user_id, self::CAP ) ) {
			return $html . '<p>' . esc_html__( 'You are not allowed to see learner details.', 'course-captain' ) . '</p></div>';
		}
		$count   = absint( $request['ccap_count'] ?? 0 );
		$course  = absint( $request['course'] ?? 0 );
		$learner = absint( $request['learner'] ?? 0 );
		$grant   = absint( $request['grant'] ?? 0 );
		if ( $learner > 0 ) {
			/* translators: %d: number of stored progress entries */
			$ok = sprintf( _n( 'Progress reset. %d entry was moved to the reset journal (kept for 30 days).', 'Progress reset. %d entries were moved to the reset journal (kept for 30 days).', $count, 'course-captain' ), $count );
		} elseif ( $grant > 0 ) {
			$ok = __( 'Access changed. The change is recorded with your reason.', 'course-captain' );
		} else {
			/* translators: %d: number of grants */
			$ok = sprintf( _n( 'Saved. %d access changed.', 'Saved. %d accesses changed.', $count, 'course-captain' ), $count );
		}
		if ( 'rules' === ( $request['view'] ?? '' ) && $course > 0 && null !== get_post( $course ) ) {
			return $html . AccessRulesPanel::html( $request, $user_id, $course ) . '</div>';
		}
		$html .= AdminUi::notice(
			sanitize_key( (string) ( $request['ccap_result'] ?? '' ) ),
			array(
				'ccap_learner_unknown'       => __( 'No account was found for this name, address or number.', 'course-captain' ),
				'ccap_reason_required'       => __( 'Please enter a reason.', 'course-captain' ),
				'ccap_modules_required'      => __( 'Choose at least one module or "whole course".', 'course-captain' ),
				'ccap_confirmation_required' => __( 'Please confirm the action (tick the confirmation box).', 'course-captain' ),
				'ccap_access_exists'         => __( 'This learner already has this access.', 'course-captain' ),
				'ccap_conflict'              => __( 'The access was changed in the meantime. Reload the page.', 'course-captain' ),
				'ccap_columns_required'      => __( 'Choose at least one column for the download.', 'course-captain' ),
				'ccap_date_invalid'          => __( 'Enter a date in the future. To end an access, use "End access".', 'course-captain' ),
				'ccap_confirm_word'          => __( 'Not reset: type the confirmation word exactly as shown.', 'course-captain' ),
			),
			$ok
		);
		$html .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><p><label for="ccap-l-course">' . esc_html__( 'Course', 'course-captain' ) . '</label> ' . AdminUi::course_select( 'ccap-l-course', 'course', $course, $user_id, __( '— choose a course —', 'course-captain' ) ) . ' <button type="submit" class="button">' . esc_html__( 'Show', 'course-captain' ) . '</button></p></form>';
		if ( $course <= 0 || null === get_post( $course ) ) {
			return $html . '<p>' . esc_html__( 'Choose a course to see its learners and their progress.', 'course-captain' ) . '</p></div>';
		}
		if ( $learner > 0 ) {
			return $html . self::learner_detail( $user_id, $course, $learner ) . '</div>';
		}
		if ( $grant > 0 ) {
			return $html . self::grant_detail( $user_id, $course, $grant ) . '</div>';
		}
		return $html . self::enrollment_form( $user_id, $course ) . self::access_list( $user_id, $course ) . self::grant_form( $user_id, $course ) . self::report_form( $user_id, $course ) . '</div>';
	}

	/**
	 * Enrollment state of the course (UI-F2, E-108): open or closed for new
	 * enrollments; existing learners keep learning until their access ends.
	 *
	 * @param int $user_id Viewer.
	 * @param int $course  Course.
	 * @return string
	 */
	private static function enrollment_form( int $user_id, int $course ): string {
		$closed = \CourseCaptain\Access\SelfEnrollment::closed( $course );
		$html   = '<h2>' . esc_html__( 'Enrollment', 'course-captain' ) . '</h2><p>' . esc_html( $closed ? __( 'Enrollment is closed: nobody can enroll themselves. Existing learners keep learning until their access ends.', 'course-captain' ) : __( 'Enrollment is open.', 'course-captain' ) ) . '</p>';
		if ( ! user_can( $user_id, 'edit_post', $course ) ) {
			return $html;
		}
		$fields = '<fieldset><legend>' . esc_html__( 'New enrollments', 'course-captain' ) . '</legend>'
			. '<label for="ccap-enr-open" class="ccap-choice"><input type="radio" id="ccap-enr-open" name="state" value="open"' . checked( $closed, false, false ) . '> ' . esc_html__( 'Open', 'course-captain' ) . '</label> '
			. '<label for="ccap-enr-closed" class="ccap-choice"><input type="radio" id="ccap-enr-closed" name="state" value="closed"' . checked( $closed, true, false ) . '> ' . esc_html__( 'Closed (existing access stays)', 'course-captain' ) . '</label></fieldset>'
			. '<input type="hidden" name="course" value="' . (int) $course . '">';
		return $html . AdminUi::form( self::ACTION, 'enrollment', $course, $fields, __( 'Save enrollment state', 'course-captain' ) );
	}

	/**
	 * CSV download of the progress report (SET-CC-26).
	 *
	 * @param int $user_id Viewer.
	 * @param int $course  Course.
	 * @return string
	 */
	private static function report_form( int $user_id, int $course ): string {
		if ( ! user_can( $user_id, 'ccap_view_reports' ) ) {
			return '';
		}
		$chosen = (array) \CourseCaptain\Settings\Settings::get( 'advanced.report_csv_columns' );
		$forced = \CourseCaptain\Privacy\Pseudonym::exports_enabled();
		$boxes  = '';
		foreach ( \CourseCaptain\Progress\CourseReport::column_labels() as $key => $label ) {
			$id     = 'ccap-rc-' . $key;
			$boxes .= '<label for="' . esc_attr( $id ) . '" class="ccap-choice"><input type="checkbox" id="' . esc_attr( $id ) . '" name="columns[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $chosen, true ), true, false ) . '> ' . esc_html( $label ) . '</label> ';
		}
		$html  = '<h2>' . esc_html__( 'Download progress report', 'course-captain' ) . '</h2>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::EXPORT_ACTION ) . '"><input type="hidden" name="course" value="' . (int) $course . '">' . wp_nonce_field( self::EXPORT_ACTION . ':' . $course, '_wpnonce', true, false );
		$html .= '<fieldset><legend>' . esc_html__( 'Columns', 'course-captain' ) . '</legend>' . $boxes . '</fieldset>';
		$html .= '<p><label for="ccap-rc-pseudo"><input type="checkbox" id="ccap-rc-pseudo" name="pseudonymize" value="1"' . checked( $forced, true, false ) . ( $forced ? ' disabled aria-describedby="ccap-rc-pseudo-hint"' : '' ) . '> ' . esc_html__( 'Pseudonymise (pseudonym instead of name and user ID)', 'course-captain' ) . '</label>' . ( $forced ? ' <span id="ccap-rc-pseudo-hint" class="description">' . esc_html__( 'Always on: the website setting pseudonymises every export.', 'course-captain' ) . '</span>' : '' ) . '</p>';
		return $html . '<p><button type="submit" class="button">' . esc_html__( 'Download CSV', 'course-captain' ) . '</button></p></form>';
	}

	/**
	 * Access list with progress.
	 *
	 * @param int $user_id Viewer.
	 * @param int $course  Course.
	 * @return string
	 */
	private static function access_list( int $user_id, int $course ): string {
		$db     = Database::current();
		$grants = $db->rows( $db->prepare( 'SELECT id, user_id, module_id, status, source_type, valid_until FROM `' . $db->table( 'ccap_grant' ) . "` WHERE course_id = %d AND user_id IS NOT NULL AND status IN ('active','suspended','pending_confirmation','pending_claim','expired') ORDER BY user_id, module_id, id", $course ) );
		$viewer = Actor::user( $user_id );
		$can    = user_can( $user_id, 'ccap_manage_grants' );
		/* translators: %s: course */
		$title = sprintf( __( 'Access to %s', 'course-captain' ), (string) get_the_title( $course ) );
		$html  = '<h2>' . esc_html( $title ) . '</h2>';
		$html .= '<p><a href="' . esc_url(
			add_query_arg(
				array(
					'page'   => self::SLUG,
					'course' => $course,
					'view'   => 'rules',
				),
				admin_url( 'admin.php' )
			)
		) . '">' . esc_html__( 'Access rules of this course (free access, drip, partial access)', 'course-captain' ) . '</a></p>';
		$html .= '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Learners with access', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Learner', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Access to', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Valid until (UTC)', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Source', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Progress', 'course-captain' ) . '</th>' . ( $can ? '<th scope="col">' . esc_html__( 'Actions', 'course-captain' ) . '</th>' : '' ) . '</tr></thead><tbody>';
		$shown = 0;
		foreach ( $grants as $grant ) {
			$learner = (int) $grant['user_id'];
			if ( ! PolicyService::instance()->decide( $viewer, PolicyService::VIEW_LEARNER, array( $learner, $course ) )->allowed ) {
				continue;
			}
			$user = get_userdata( $learner );
			if ( false === $user ) {
				continue;
			}
			++$shown;
			$progress = \CourseCaptain\Progress\CourseProgress::current()->get( $learner, $course );
			$percent  = null === $progress ? 0 : (int) floor( (float) $progress['completion_ratio'] * 100 );
			$detail   = add_query_arg(
				array(
					'page'    => self::SLUG,
					'course'  => $course,
					'learner' => $learner,
				),
				admin_url( 'admin.php' )
			);
			$html    .= '<tr><th scope="row"><a href="' . esc_url( $detail ) . '">' . esc_html( $user->display_name ) . '</a> <span class="description">' . esc_html( self::mask( (string) $user->user_email ) ) . '</span></th>';
			$html    .= '<td>' . esc_html( 0 === (int) $grant['module_id'] ? __( 'whole course', 'course-captain' ) : sprintf( /* translators: %s: module */ __( 'module %s', 'course-captain' ), (string) get_the_title( (int) $grant['module_id'] ) ) ) . '</td>';
			$html    .= '<td>' . esc_html( self::grant_label( (string) $grant['status'] ) ) . '</td><td>' . esc_html( null === $grant['valid_until'] ? __( 'unlimited', 'course-captain' ) : (string) $grant['valid_until'] ) . '</td><td>' . esc_html( self::source_label( (string) $grant['source_type'] ) ) . '</td>';
			$html    .= '<td>' . esc_html( sprintf( /* translators: 1: percent, 2: status */ __( '%1$d %% (%2$s)', 'course-captain' ), $percent, self::progress_label( null === $progress ? 'not_started' : (string) $progress['status'] ) ) ) . ' <a href="' . esc_url( $detail ) . '">' . esc_html( sprintf( /* translators: %s: learner */ __( 'Progress of %s', 'course-captain' ), $user->display_name ) ) . '</a></td>';
			if ( $can ) {
				$manage = add_query_arg(
					array(
						'page'   => self::SLUG,
						'course' => $course,
						'grant'  => (int) $grant['id'],
					),
					admin_url( 'admin.php' )
				);
				$html  .= '<td>' . ( 'active' === $grant['status'] || 'suspended' === $grant['status'] ? '<p><a href="' . esc_url( $manage ) . '">' . esc_html( sprintf( /* translators: %s: learner */ __( 'Suspend, resume or extend the access of %s', 'course-captain' ), $user->display_name ) ) . '</a></p>' . AdminUi::form( self::ACTION, 'revoke', (int) $grant['id'], '<input type="hidden" name="course" value="' . (int) $course . '"><p><label for="ccap-rv-' . (int) $grant['id'] . '">' . esc_html__( 'Reason', 'course-captain' ) . '</label> <input id="ccap-rv-' . (int) $grant['id'] . '" type="text" name="reason"> <label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Confirm: end this access (the learning history stays)', 'course-captain' ) . '</label></p>', __( 'End access', 'course-captain' ) ) : '' ) . '</td>';
			}
			$html .= '</tr>';
		}
		if ( 0 === $shown ) {
			$html .= '<tr><td colspan="' . ( $can ? 7 : 6 ) . '">' . esc_html__( 'Nobody has access to this course yet.', 'course-captain' ) . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Manual grant form.
	 *
	 * @param int $user_id Viewer.
	 * @param int $course  Course.
	 * @return string
	 */
	private static function grant_form( int $user_id, int $course ): string {
		if ( ! PolicyService::instance()->decide( Actor::user( $user_id ), PolicyService::CREATE_GRANT, array( $course ) )->allowed ) {
			return '';
		}
		$fields  = '<input type="hidden" name="course" value="' . (int) $course . '"><input type="hidden" name="request_key" value="' . esc_attr( AdminUi::request_key() ) . '">';
		$fields .= '<p><label for="ccap-g-learner">' . esc_html__( 'Learner (user name, email address or user ID)', 'course-captain' ) . '</label><br><input id="ccap-g-learner" type="text" name="learner" class="regular-text" required></p>';
		$fields .= '<fieldset><legend>' . esc_html__( 'Access to', 'course-captain' ) . '</legend><label><input type="radio" name="scope" value="course" checked> ' . esc_html__( 'the whole course', 'course-captain' ) . '</label><br><label><input type="radio" name="scope" value="modules"> ' . esc_html__( 'only these modules:', 'course-captain' ) . '</label><br>';
		foreach ( CourseStructure::current()->modules( $course ) as $link ) {
			$fields .= '<label class="ccap-indent"><input type="checkbox" name="modules[]" value="' . (int) $link['module_id'] . '"> ' . esc_html( (string) get_the_title( $link['module_id'] ) ) . '</label><br>';
		}
		$fields .= '</fieldset><p><label for="ccap-g-until">' . esc_html__( 'Valid until (date, empty = unlimited)', 'course-captain' ) . '</label><br><input id="ccap-g-until" type="date" name="valid_until"></p>';
		$fields .= '<p><label for="ccap-g-reason">' . esc_html__( 'Reason (required, recorded)', 'course-captain' ) . '</label><br><input id="ccap-g-reason" type="text" name="reason" class="large-text" required></p>';
		return '<h2>' . esc_html__( 'Give access', 'course-captain' ) . '</h2>' . AdminUi::form( self::ACTION, 'grant', 0, $fields, __( 'Give access', 'course-captain' ), '1' );
	}

	/**
	 * Progress of one learner per module and lesson.
	 *
	 * @param int $user_id Viewer.
	 * @param int $course  Course.
	 * @param int $learner Learner.
	 * @return string
	 */
	private static function learner_detail( int $user_id, int $course, int $learner ): string {
		$back = '<p><a href="' . esc_url(
			add_query_arg(
				array(
					'page'   => self::SLUG,
					'course' => $course,
				),
				admin_url( 'admin.php' )
			)
		) . '">' . esc_html__( 'Back to the learner list', 'course-captain' ) . '</a></p>';
		$user = get_userdata( $learner );
		if ( false === $user || ! PolicyService::instance()->decide( Actor::user( $user_id ), PolicyService::VIEW_LEARNER, array( $learner, $course ) )->allowed ) {
			return $back . '<p>' . esc_html__( 'This learner does not exist or you may not see their details.', 'course-captain' ) . '</p>';
		}
		$db       = Database::current();
		$progress = \CourseCaptain\Progress\CourseProgress::current()->get( $learner, $course );
		$states   = array();
		foreach ( $db->rows( $db->prepare( 'SELECT activity_id, status, completed_at, last_access_at FROM `' . $db->table( 'ccap_activity_progress' ) . '` WHERE user_id = %d AND course_id = %d', $learner, $course ) ) as $row ) {
			$states[ (int) $row['activity_id'] ] = $row;
		}
		$html  = $back . '<h2>' . esc_html( sprintf( /* translators: 1: learner, 2: course */ __( 'Progress of %1$s in %2$s', 'course-captain' ), $user->display_name, (string) get_the_title( $course ) ) ) . '</h2>';
		$html .= '<p>' . esc_html(
			null === $progress ? __( 'Not started yet.', 'course-captain' ) : sprintf(
				/* translators: 1: percent, 2: status, 3: date */
				__( '%1$d %% done (%2$s), last activity %3$s (UTC)', 'course-captain' ),
				(int) floor( (float) $progress['completion_ratio'] * 100 ),
				self::progress_label( (string) $progress['status'] ),
				(string) ( $progress['last_activity_at'] ?? '–' )
			)
		) . '</p>';
		// Kap. 28: worked through, mastered and evidenced side by side (same read model as the REST route).
		$html .= \CourseCaptain\Learner\ProgressPanel::html( \CourseCaptain\Progress\ProgressDimensions::current()->build( $learner, $course ), 'ccap-progress-' . $learner, 3 );
		foreach ( CourseStructure::current()->modules( $course ) as $link ) {
			$html .= '<h3>' . esc_html( (string) get_the_title( $link['module_id'] ) ) . '</h3><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Lesson', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Completed (UTC)', 'course-captain' ) . '</th></tr></thead><tbody>';
			foreach ( CourseStructure::current()->lessons( $link['module_id'] ) as $lesson ) {
				$state = $states[ (int) $lesson->ID ] ?? null;
				$html .= '<tr><th scope="row">' . esc_html( $lesson->post_title ) . '</th><td>' . esc_html( null === $state ? __( 'not opened', 'course-captain' ) : ( 'completed' === $state['status'] ? __( 'completed', 'course-captain' ) : __( 'in progress', 'course-captain' ) ) ) . '</td><td>' . esc_html( (string) ( $state['completed_at'] ?? '–' ) ) . '</td></tr>';
			}
			$html .= '</tbody></table>';
		}
		return $html . self::reset_form( $user_id, $course, $learner );
	}

	/**
	 * Reset form of the learner detail (UI-F8): reason, typed confirmation
	 * word, optional question learning states; what stays is said up front.
	 *
	 * @param int $user_id Viewer.
	 * @param int $course  Course.
	 * @param int $learner Learner.
	 * @return string
	 */
	private static function reset_form( int $user_id, int $course, int $learner ): string {
		if ( ! PolicyService::instance()->decide( Actor::user( $user_id ), PolicyService::MANAGE_PROGRESS, array( $learner ) )->allowed || $user_id === $learner ) {
			return '';
		}
		$id      = 'ccap-reset-' . $learner;
		$fields  = '<input type="hidden" name="course" value="' . (int) $course . '">';
		$fields .= '<p id="' . esc_attr( $id ) . '-what">' . esc_html__( 'The course progress and the lesson progress of this course start again. Exam attempts, answers and certificates stay. The previous state is kept in the reset journal for 30 days.', 'course-captain' ) . '</p>';
		$fields .= '<p><label for="' . esc_attr( $id ) . '-reason">' . esc_html__( 'Reason (required, recorded)', 'course-captain' ) . '</label><br><input id="' . esc_attr( $id ) . '-reason" type="text" name="reason" class="large-text" required></p>';
		$fields .= '<p><label><input type="checkbox" name="questions" value="1"> ' . esc_html__( 'Also reset the learning stages of the questions in this course (smart trainer)', 'course-captain' ) . '</label></p>';
		/* translators: 1: German confirmation word, 2: English confirmation word (both typed literally) */
		$fields .= '<p><label for="' . esc_attr( $id ) . '-word">' . esc_html( sprintf( __( 'To confirm, type %1$s or %2$s:', 'course-captain' ), self::RESET_WORDS[0], self::RESET_WORDS[1] ) ) . '</label> <input id="' . esc_attr( $id ) . '-word" type="text" name="confirm_word" autocomplete="off" spellcheck="false" class="regular-text" aria-describedby="' . esc_attr( $id ) . '-what" required></p>';
		return '<h3>' . esc_html__( 'Reset progress', 'course-captain' ) . '</h3>' . AdminUi::form( self::ACTION, 'reset', $learner, $fields, __( 'Reset progress in this course', 'course-captain' ) );
	}

	/**
	 * Manage one access (UI-F8): suspend or resume, extend, end.
	 *
	 * @param int $user_id Viewer.
	 * @param int $course  Course.
	 * @param int $grant   Grant.
	 * @return string
	 */
	private static function grant_detail( int $user_id, int $course, int $grant ): string {
		$back = '<p><a href="' . esc_url(
			add_query_arg(
				array(
					'page'   => self::SLUG,
					'course' => $course,
				),
				admin_url( 'admin.php' )
			)
		) . '">' . esc_html__( 'Back to the learner list', 'course-captain' ) . '</a></p>';
		$db   = Database::current();
		$row  = $db->row( $db->prepare( 'SELECT id, user_id, course_id, module_id, status, valid_until, manual_override FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d AND course_id = %d', $grant, $course ) );
		$user = null === $row ? false : get_userdata( (int) $row['user_id'] );
		if ( null === $row || false === $user || ! PolicyService::instance()->decide( Actor::user( $user_id ), PolicyService::VIEW_LEARNER, array( (int) $row['user_id'], $course ) )->allowed ) {
			return $back . '<p>' . esc_html__( 'This access does not exist or you may not see it.', 'course-captain' ) . '</p>';
		}
		$scope = 0 === (int) $row['module_id'] ? __( 'whole course', 'course-captain' ) : sprintf( /* translators: %s: module */ __( 'module %s', 'course-captain' ), (string) get_the_title( (int) $row['module_id'] ) );
		/* translators: 1: learner, 2: course or module */
		$html  = $back . '<h2>' . esc_html( sprintf( __( 'Access of %1$s: %2$s', 'course-captain' ), $user->display_name, $scope ) ) . '</h2>';
		$html .= '<p>' . esc_html(
			sprintf(
				/* translators: 1: status, 2: end date */
				__( 'Status: %1$s · valid until (UTC): %2$s', 'course-captain' ),
				self::grant_label( (string) $row['status'] ),
				null === $row['valid_until'] ? __( 'unlimited', 'course-captain' ) : (string) $row['valid_until']
			)
		) . '</p>';
		if ( ! user_can( $user_id, 'ccap_manage_grants' ) || ! in_array( (string) $row['status'], array( 'active', 'suspended' ), true ) ) {
			return $html;
		}
		$reason = static fn( string $op ): string => '<input type="hidden" name="course" value="' . (int) $course . '"><p><label for="ccap-' . $op . '-reason">' . esc_html__( 'Reason (required, recorded)', 'course-captain' ) . '</label><br><input id="ccap-' . $op . '-reason" type="text" name="reason" class="large-text" required></p>';
		if ( 'active' === $row['status'] ) {
			$html .= '<h3>' . esc_html__( 'Suspend access', 'course-captain' ) . '</h3><p>' . esc_html__( 'The learner cannot learn until the access is resumed. Progress and results stay; the end date does not change.', 'course-captain' ) . '</p>';
			$html .= AdminUi::form( self::ACTION, 'suspend', $grant, $reason( 'suspend' ), __( 'Suspend access', 'course-captain' ) );
			$html .= '<h3>' . esc_html__( 'Extend access', 'course-captain' ) . '</h3>';
			$html .= AdminUi::form( self::ACTION, 'extend', $grant, $reason( 'extend' ) . '<p><label for="ccap-extend-until">' . esc_html__( 'New end date (valid until the end of this day, UTC)', 'course-captain' ) . '</label><br><input id="ccap-extend-until" type="date" name="valid_until" required></p>', __( 'Extend access', 'course-captain' ) );
		} else {
			$html .= '<h3>' . esc_html__( 'Resume access', 'course-captain' ) . '</h3><p>' . esc_html( 'suspended' === $row['manual_override'] ? __( 'The access was suspended by hand and stays suspended until you resume it.', 'course-captain' ) : __( 'The access was suspended by its source (for example a payment). Resuming it here overrides the source.', 'course-captain' ) ) . '</p>';
			$html .= AdminUi::form( self::ACTION, 'resume', $grant, $reason( 'resume' ), __( 'Resume access', 'course-captain' ) );
		}
		return $html;
	}

	/**
	 * Grant status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function grant_label( string $status ): string {
		$labels = array(
			'active'               => __( 'active', 'course-captain' ),
			'suspended'            => __( 'suspended', 'course-captain' ),
			'expired'              => __( 'expired', 'course-captain' ),
			'pending_confirmation' => __( 'waiting for confirmation', 'course-captain' ),
			'pending_claim'        => __( 'invitation not accepted yet', 'course-captain' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Source label.
	 *
	 * @param string $source Source type.
	 * @return string
	 */
	private static function source_label( string $source ): string {
		$labels = array(
			'manual'          => __( 'given by hand', 'course-captain' ),
			'free_enrollment' => __( 'self-enrolment', 'course-captain' ),
			'group_seat'      => __( 'group', 'course-captain' ),
		);
		return $labels[ $source ] ?? $source;
	}

	/**
	 * Course progress label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function progress_label( string $status ): string {
		$labels = array(
			'not_started' => __( 'not started', 'course-captain' ),
			'in_progress' => __( 'in progress', 'course-captain' ),
			'completed'   => __( 'completed', 'course-captain' ),
		);
		return $labels[ $status ] ?? $status;
	}
}
