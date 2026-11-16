<?php
/**
 * Admin page "System status" with safe repair actions (R0-13 §2.1, §3;
 * R0-30 S-22 `course-captain-status`, M-3; R0-34 §3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Admin\EditorScreens;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Reading needs `ccap_view_system_status` (#22), every action button
 * `ccap_run_maintenance` (#23) and is only rendered for it. Actions are
 * admin-post forms (work without JavaScript) with one nonce per action,
 * a request key against double submits, a mandatory reason where the
 * catalog asks for one and a confirmation checkbox for destructive actions.
 */
final class OpsScreen {

	public const SLUG   = 'course-captain-status';
	public const ACTION = 'ccap_ops';
	public const AREAS  = array( 'status', 'jobs', 'integrations', 'diagnostics' );

	/**
	 * Actions: op => [area, reason required, confirmation required].
	 *
	 * @var array<string, array{0: string, 1: bool, 2: bool}>
	 */
	public const OPS = array(
		'migration_retry' => array( 'status', false, false ),
		'outbox_retry'    => array( 'jobs', false, false ),
		'outbox_discard'  => array( 'jobs', true, true ),
		'held_release'    => array( 'jobs', true, true ),
		'held_discard'    => array( 'jobs', true, true ),
		'cache_flush'     => array( 'jobs', false, false ),
	);

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				add_submenu_page( EditorScreens::MENU, __( 'System status', 'course-captain' ), __( 'System status', 'course-captain' ), OpsStatus::CAP, self::SLUG, array( self::class, 'render' ) );
			},
			21
		);
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_post_ccap_ops_diagnostics', array( self::class, 'handle_diagnostics' ) );
	}

	/**
	 * Page output.
	 *
	 * @return void
	 */
	public static function render(): void {
		echo self::html( $_GET ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.NonceVerification.Recommended -- Escaped while building; read-only view parameters.
	}

	/**
	 * Admin-post entry point of all repair actions.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$out = self::process( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process().
		$url = add_query_arg(
			array(
				'page'            => self::SLUG,
				'area'            => $out['area'],
				'ccap_ops'        => $out['op'],
				'ccap_ops_result' => $out['result'],
				'ccap_ops_count'  => $out['count'],
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Admin-post download of the diagnostics package.
	 *
	 * @return void
	 */
	public static function handle_diagnostics(): void {
		check_admin_referer( 'ccap_ops_diagnostics' );
		try {
			$package = Diagnostics::current()->export( Actor::user( get_current_user_id() ) );
		} catch ( CourseException $e ) {
			wp_die( esc_html__( 'You are not allowed to export diagnostics.', 'course-captain' ), '', array( 'response' => (int) $e->status ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $package['filename'] . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $package['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		exit;
	}

	/**
	 * Testable core of the admin-post handler.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @param int                  $user_id Current user.
	 * @return array{op: string, area: string, result: string, count: int}
	 */
	public static function process( array $post, int $user_id ): array {
		$op  = isset( $post['op'] ) ? sanitize_key( (string) $post['op'] ) : '';
		$out = array(
			'op'     => $op,
			'area'   => self::OPS[ $op ][0] ?? 'status',
			'result' => 'ok',
			'count'  => 0,
		);
		if ( ! isset( self::OPS[ $op ] ) ) {
			$out['result'] = 'ccap_not_found';
			return $out;
		}
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), 'ccap_ops_' . $op ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		$actor = Actor::user( $user_id );
		if ( ! RepairService::can( $actor ) ) {
			$out['result'] = 'ccap_forbidden';
			return $out;
		}
		if ( self::OPS[ $op ][2] && empty( $post['confirm'] ) ) {
			$out['result'] = 'ccap_confirmation_required';
			return $out;
		}
		$reason  = isset( $post['reason'] ) ? sanitize_textarea_field( (string) $post['reason'] ) : '';
		$key     = isset( $post['request_key'] ) ? sanitize_text_field( (string) $post['request_key'] ) : '';
		$service = RepairService::current();
		try {
			switch ( $op ) {
				case 'migration_retry':
					$run           = $service->retry_migration( $actor, null, $key );
					$out['result'] = in_array( $run['status'], array( 'done', 'none' ), true ) ? 'ok' : 'migration_' . sanitize_key( $run['status'] );
					break;
				case 'outbox_retry':
					$out['count'] = $service->retry_deliveries( $actor, $key )['count'];
					break;
				case 'outbox_discard':
					$out['count'] = $service->discard_deliveries( $actor, $reason, $key )['count'];
					break;
				case 'held_release':
					$out['count'] = $service->release_held( $actor, $reason, $key )['count'];
					break;
				case 'held_discard':
					$out['count'] = $service->discard_held( $actor, $reason, $key )['count'];
					break;
				case 'cache_flush':
					$service->flush_cache( $actor, absint( $post['user'] ?? 0 ), absint( $post['course'] ?? 0 ) );
					break;
			}
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
		}
		return $out;
	}

	/**
	 * Page HTML.
	 *
	 * @param array<string, mixed> $request Raw request (GET).
	 * @return string
	 * @throws CourseException Without `ccap_view_system_status`.
	 */
	public static function html( array $request ): string {
		$actor = Actor::user( get_current_user_id() );
		OpsStatus::assert_can_view( $actor );
		$area = isset( $request['area'] ) ? sanitize_key( (string) $request['area'] ) : 'status';
		$area = in_array( $area, self::AREAS, true ) ? $area : 'status';
		$html = '<div class="wrap"><h1>' . esc_html__( 'System status', 'course-captain' ) . '</h1>' . self::nav( $area ) . self::notice( $request );
		switch ( $area ) {
			case 'jobs':
				$html .= self::jobs( $actor );
				break;
			case 'integrations':
				$html .= self::integrations( $actor, isset( $request['filter'] ) && 'all' === $request['filter'] );
				break;
			case 'diagnostics':
				$html .= self::diagnostics();
				break;
			default:
				$html .= self::status( $actor );
		}
		return $html . '</div>';
	}

	/**
	 * Area navigation (R0-30 M-3: links, not an ARIA tab widget).
	 *
	 * @param string $current Current area.
	 * @return string
	 */
	private static function nav( string $current ): string {
		$labels = array(
			'status'       => __( 'Status', 'course-captain' ),
			'jobs'         => __( 'Jobs', 'course-captain' ),
			'integrations' => __( 'Integrations', 'course-captain' ),
			'diagnostics'  => __( 'Diagnostics', 'course-captain' ),
		);
		$html   = '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'System status: areas', 'course-captain' ) . '">';
		foreach ( $labels as $area => $label ) {
			$url   = add_query_arg(
				array(
					'page' => self::SLUG,
					'area' => $area,
				),
				admin_url( 'admin.php' )
			);
			$html .= '<a class="nav-tab' . ( $area === $current ? ' nav-tab-active" aria-current="page' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		return $html . '</nav>';
	}

	/**
	 * Result notice after an action.
	 *
	 * @param array<string, mixed> $request Request.
	 * @return string
	 */
	private static function notice( array $request ): string {
		if ( empty( $request['ccap_ops_result'] ) ) {
			return '';
		}
		$code     = sanitize_key( (string) $request['ccap_ops_result'] );
		$count    = absint( $request['ccap_ops_count'] ?? 0 );
		$messages = array(
			'ccap_invalid_nonce'         => __( 'The form has expired. Reload the page and try again.', 'course-captain' ),
			'ccap_forbidden'             => __( 'You are not allowed to run this action.', 'course-captain' ),
			'ccap_confirmation_required' => __( 'This action needs a confirmation. Tick the confirmation box and submit again.', 'course-captain' ),
			'ccap_reason_required'       => __( 'Reason: required, at least 10 characters (for example the ticket number and the cause).', 'course-captain' ),
			'ccap_not_found'             => __( 'The user, course or action was not found.', 'course-captain' ),
			'ccap_idempotency_conflict'  => __( 'This form was already used for another action. Reload the page.', 'course-captain' ),
			'ccap_request_in_progress'   => __( 'The action is still running. Reload the page in a moment.', 'course-captain' ),
		);
		if ( 'ok' === $code ) {
			/* translators: %d: number of affected entries */
			$text = sprintf( __( 'Done. Affected entries: %d.', 'course-captain' ), $count );
			$type = 'success';
		} elseif ( str_starts_with( $code, 'migration_' ) ) {
			/* translators: %s: migration result */
			$text = sprintf( __( 'The database update did not finish (result: %s). See the status below.', 'course-captain' ), substr( $code, 10 ) );
			$type = 'error';
		} else {
			$text = $messages[ $code ] ?? __( 'The action failed.', 'course-captain' );
			$type = 'error';
		}
		return '<div class="notice notice-' . esc_attr( $type ) . '" role="status"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Area "Status": check list and migration.
	 *
	 * @param Actor $actor Viewer.
	 * @return string
	 */
	private static function status( Actor $actor ): string {
		$report = OpsStatus::report( $actor );
		$html   = '<h2>' . esc_html__( 'Checks', 'course-captain' ) . '</h2>';
		/* translators: %s: overall status word */
		$html .= '<p role="status">' . esc_html( sprintf( __( 'Overall: %s', 'course-captain' ), OpsStatus::status_label( $report['overall'] ) ) ) . '</p>';
		$html .= '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Checks', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Check', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Result', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'What to do', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'IDs', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $report['checks'] as $check ) {
			$html .= '<tr class="ccap-ops-' . esc_attr( $check['status'] ) . '"><td>' . esc_html( OpsStatus::status_label( $check['status'] ) ) . '</td><td>' . esc_html( $check['id'] ) . '</td><td>' . esc_html( $check['label'] ) . '</td><td>' . esc_html( $check['hint'] ) . '</td><td><code>' . esc_html( implode( ', ', $check['ids'] ) ) . '</code></td></tr>';
		}
		$html .= '</tbody></table>';

		$migration = $report['migration'];
		$html     .= '<h2>' . esc_html__( 'Database update', 'course-captain' ) . '</h2>';
		/* translators: 1: state, 2: stored schema version, 3: code schema version */
		$html   .= '<p>' . esc_html( sprintf( __( 'State: %1$s. Schema: %2$s (code %3$s).', 'course-captain' ), (string) $migration['state'], (string) ( $migration['stored_version'] ?? '-' ), (string) $migration['code_version'] ) ) . '</p>';
		$problem = array();
		foreach ( (array) $migration['steps'] as $id => $step ) {
			if ( 'done' !== $step['status'] ) {
				$problem[] = '<li><code>' . esc_html( (string) $id ) . '</code> ' . esc_html( $step['status'] . ( null === $step['blocked_reason'] ? '' : ' / ' . $step['blocked_reason'] ) ) . ( null === $step['last_error'] ? '' : ': ' . esc_html( mb_substr( Diagnostics::scrub( (string) $step['last_error'] ), 0, 300 ) ) ) . '</li>';
			}
		}
		if ( array() !== $problem ) {
			$html .= '<ul>' . implode( '', $problem ) . '</ul>';
		}
		if ( RepairService::can( $actor ) && in_array( $migration['state'], array( 'failed', 'blocked', 'pending' ), true ) ) {
			$html .= self::form( 'migration_retry', __( 'Retry now', 'course-captain' ), __( 'Runs the database update immediately, ignores the waiting time and tries a blocked step again (like "wp course-captain migrate").', 'course-captain' ) );
		}
		return $html;
	}

	/**
	 * Area "Jobs": deliveries, held mails, cache.
	 *
	 * @param Actor $actor Viewer.
	 * @return string
	 */
	private static function jobs( Actor $actor ): string {
		$report = OpsStatus::report( $actor );
		$can    = RepairService::can( $actor );
		$html   = '<h2>' . esc_html__( 'Internal deliveries', 'course-captain' ) . '</h2><table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Deliveries by status', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Number', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $report['outbox'] as $status => $count ) {
			$html .= '<tr><td><code>' . esc_html( (string) $status ) . '</code></td><td>' . esc_html( number_format_i18n( $count ) ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		foreach ( $report['checks'] as $check ) {
			if ( 'H-27' === $check['id'] && array() !== $check['ids'] ) {
				$html .= '<p>' . esc_html( $check['label'] ) . ' <code>' . esc_html( implode( ', ', $check['ids'] ) ) . '</code></p>';
			}
		}
		if ( $can && $report['outbox']['dead'] > 0 ) {
			$html .= self::form( 'outbox_retry', __( 'Retry dead letters', 'course-captain' ), __( 'Delivers the events again with the same event ID. Fix the cause first.', 'course-captain' ) );
			/* translators: %s: number of dead letters */
			$html .= self::form( 'outbox_discard', __( 'Discard dead letters', 'course-captain' ), sprintf( _n( '%s dead letter is marked as discarded and no longer delivered. The event stays in the log.', '%s dead letters are marked as discarded and no longer delivered. The events stay in the log.', (int) $report['outbox']['dead'], 'course-captain' ), number_format_i18n( (int) $report['outbox']['dead'] ) ) );
		}

		$notes = $report['notifications'];
		$html .= '<h2>' . esc_html__( 'Messages', 'course-captain' ) . '</h2>';
		/* translators: 1: held, 2: failed in 24 h, 3: unknown, 4: waiting */
		$html .= '<p>' . esc_html( sprintf( __( 'Held: %1$s · failed in 24 hours: %2$s · outcome unknown: %3$s · waiting: %4$s', 'course-captain' ), number_format_i18n( (int) $notes['held'] ), number_format_i18n( (int) $notes['failed'] ), number_format_i18n( (int) $notes['unknown'] ), number_format_i18n( (int) $notes['queued'] ) ) ) . '</p>';
		if ( $can && $notes['held'] > 0 ) {
			/* translators: %s: number of held messages */
			$html .= self::form( 'held_release', __( 'Send held messages', 'course-captain' ), sprintf( _n( '%s held message is sent. Only do this if the site identity is confirmed (same site).', '%s held messages are sent. Only do this if the site identity is confirmed (same site).', (int) $notes['held'], 'course-captain' ), number_format_i18n( (int) $notes['held'] ) ) );
			/* translators: %s: number of held messages */
			$html .= self::form( 'held_discard', __( 'Discard held messages', 'course-captain' ), sprintf( _n( '%s held message is discarded and never sent.', '%s held messages are discarded and never sent.', (int) $notes['held'], 'course-captain' ), number_format_i18n( (int) $notes['held'] ) ) );
		}

		if ( $can ) {
			$html .= '<h2>' . esc_html__( 'Cache', 'course-captain' ) . '</h2>';
			$html .= self::form( 'cache_flush', __( 'Empty cache', 'course-captain' ), __( 'Starts a new cache generation. Access decisions are never cached, so this is always safe. Optionally limit it to one user or course.', 'course-captain' ) );
		}
		return $html;
	}

	/**
	 * Area "Integrations" (read-only, R0-34 §3).
	 *
	 * @param Actor $actor Viewer.
	 * @param bool  $all   Show all known partners.
	 * @return string
	 */
	private static function integrations( Actor $actor, bool $all ): string {
		$records = IntegrationStatus::all( $actor, ! $all );
		$html    = '<p>' . esc_html__( 'Here you see which plugins Course Captain detects and whether a data exchange is set up. Course Captain does not install or activate other plugins.', 'course-captain' ) . '</p>';
		$base    = array(
			'page' => self::SLUG,
			'area' => 'integrations',
		);
		$html   .= '<p><a href="' . esc_url( add_query_arg( $base, admin_url( 'admin.php' ) ) ) . '"' . ( $all ? '' : ' aria-current="page"' ) . '>' . esc_html__( 'Only detected', 'course-captain' ) . '</a> | <a href="' . esc_url( add_query_arg( $base + array( 'filter' => 'all' ), admin_url( 'admin.php' ) ) ) . '"' . ( $all ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'All known', 'course-captain' ) . '</a></p>';
		if ( array() === $records ) {
			return $html . '<p>' . esc_html__( 'No partner plugin is installed. Course Captain works without them.', 'course-captain' ) . '</p>';
		}
		$html .= '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Integrations', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Name', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'State', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Version found / required', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Contract', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Privacy class', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $records as $record ) {
			$texts   = IntegrationStatus::texts( $record );
			$partner = (array) $record['partner'];
			$html   .= '<tr><td>' . esc_html( (string) $record['label'] ) . '</td><td><strong>' . esc_html( $texts['badge'] ) . '</strong> <code>' . esc_html( $record['state'] . '/' . $record['state_reason'] ) . '</code><br>' . esc_html( $texts['text'] ) . '</td><td>' . esc_html( (string) ( $partner['version'] ?? '-' ) . ' / ' . ( '' === $partner['required'] ? '-' : (string) $partner['required'] ) ) . '</td><td>' . esc_html( (string) ( $partner['contract_version'] ?? '-' ) ) . '</td><td>' . esc_html__( 'No personal data. Course Captain only checks version and contract.', 'course-captain' ) . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Area "Diagnostics".
	 *
	 * @return string
	 */
	private static function diagnostics(): string {
		$html  = '<p>' . esc_html__( 'The diagnostics package helps the technical operator or the Course Captain support without an account on this site. It contains versions, settings of the environment, check results, failed jobs and imports with error codes.', 'course-captain' ) . '</p>';
		$html .= '<p>' . esc_html__( 'Never included: names, email addresses, logins, user IDs, IP addresses, answers, course contents, audit entries, secrets, tokens, file paths and the domain in plain text. Error messages are redacted.', 'course-captain' ) . '</p>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ccap_ops_diagnostics">' . wp_nonce_field( 'ccap_ops_diagnostics', '_wpnonce', true, false ) . '<button class="button button-primary">' . esc_html__( 'Download diagnostics package', 'course-captain' ) . '</button></form>';
		return $html;
	}

	/**
	 * Action form.
	 *
	 * @param string $op     Action.
	 * @param string $button Button text.
	 * @param string $effect Effect in plain text.
	 * @return string
	 */
	private static function form( string $op, string $button, string $effect ): string {
		$id    = 'ccap-ops-' . str_replace( '_', '-', $op );
		$html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-ops-form"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="op" value="' . esc_attr( $op ) . '"><input type="hidden" name="request_key" value="' . esc_attr( wp_generate_uuid4() ) . '">' . wp_nonce_field( 'ccap_ops_' . $op, '_wpnonce', true, false );
		$html .= '<p id="' . esc_attr( $id ) . '-effect">' . esc_html( $effect ) . '</p>';
		if ( 'cache_flush' === $op ) {
			$html .= '<p><label for="' . esc_attr( $id ) . '-user">' . esc_html__( 'User ID (optional)', 'course-captain' ) . '</label> <input type="number" min="0" id="' . esc_attr( $id ) . '-user" name="user"> <label for="' . esc_attr( $id ) . '-course">' . esc_html__( 'Course ID (optional)', 'course-captain' ) . '</label> <input type="number" min="0" id="' . esc_attr( $id ) . '-course" name="course"></p>';
		}
		if ( self::OPS[ $op ][1] ) {
			$html .= '<p><label for="' . esc_attr( $id ) . '-reason">' . esc_html__( 'Reason (required, at least 10 characters)', 'course-captain' ) . '</label><br><textarea id="' . esc_attr( $id ) . '-reason" name="reason" rows="2" cols="60" required minlength="10" maxlength="500" aria-describedby="' . esc_attr( $id ) . '-hint"></textarea><br><span id="' . esc_attr( $id ) . '-hint" class="description">' . esc_html__( 'Ticket number and cause. No health data, passwords or payment data.', 'course-captain' ) . '</span></p>';
		}
		if ( self::OPS[ $op ][2] ) {
			$html .= '<p><input type="checkbox" id="' . esc_attr( $id ) . '-confirm" name="confirm" value="1" required> <label for="' . esc_attr( $id ) . '-confirm">' . esc_html__( 'I have read the effect and want to continue.', 'course-captain' ) . '</label></p>';
		}
		return $html . '<p><button class="button' . ( self::OPS[ $op ][2] ? '' : ' button-primary' ) . '" aria-describedby="' . esc_attr( $id ) . '-effect">' . esc_html( $button ) . '</button></p></form>';
	}
}
