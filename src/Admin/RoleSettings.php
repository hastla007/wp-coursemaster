<?php
/**
 * Tab "Roles & permissions" (R0-30 §3.3.6, R0-31 §3.3, ADM-100/101,
 * PSD-034…037).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\RoleCaps;
use CourseCaptain\Settings\RiskConfirm;

defined( 'ABSPATH' ) || exit;

/**
 * Overview table plus one `<details>` per role with three fieldsets; every
 * checkbox is named "{Role}: {Capability}" and high-impact capabilities
 * carry the word "high impact" as text. One form per role; granting a
 * high-impact capability to a role goes through RC-07. Reset and undo are
 * own forms.
 */
final class RoleSettings {

	public const RESET = 'ccap_roles_reset';
	public const UNDO  = 'ccap_roles_undo';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'course_captain_settings_roles_tab', static fn( $allowed, $user_id ): bool => RoleCaps::can_manage( (int) $user_id ), 10, 2 );
		add_filter( 'course_captain_settings_roles_html', static fn( $html, $request, $user_id ): string => self::html( (array) $request, (int) $user_id ), 10, 3 );
		add_filter( 'course_captain_settings_roles_save', static fn( $result, $post, $user_id ): string => self::process_save( (array) $post, (int) $user_id ), 10, 3 );
		add_filter( 'course_captain_risk_text', array( self::class, 'risk_text' ), 10, 4 );
		add_action( 'admin_post_' . self::RESET, array( self::class, 'handle_reset' ) );
		add_action( 'admin_post_' . self::UNDO, array( self::class, 'handle_undo' ) );
	}

	/**
	 * Groups of the 24 capabilities (R0-07 §3.1).
	 *
	 * @return array<string, list<string>>
	 */
	public static function groups(): array {
		$caps = Capabilities::PRIMITIVE;
		return array(
			__( 'Courses and content', 'course-captain' ) => array_slice( $caps, 0, 8 ),
			__( 'Learners and access', 'course-captain' ) => array_slice( $caps, 8, 10 ),
			__( 'Operation and settings', 'course-captain' ) => array_slice( $caps, 18 ),
		);
	}

	/**
	 * Plain-language capability names.
	 *
	 * @param string $cap Capability.
	 * @return string
	 */
	public static function label( string $cap ): string {
		$labels = array(
			'ccap_edit_courses'            => __( 'Edit own courses', 'course-captain' ),
			'ccap_edit_others_courses'     => __( 'Edit courses of others', 'course-captain' ),
			'ccap_publish_courses'         => __( 'Publish courses', 'course-captain' ),
			'ccap_delete_courses'          => __( 'Delete courses', 'course-captain' ),
			'ccap_manage_question_bank'    => __( 'Manage the question bank across courses', 'course-captain' ),
			'ccap_import_content'          => __( 'Import content', 'course-captain' ),
			'ccap_export_content'          => __( 'Export courses', 'course-captain' ),
			'ccap_release_rights'          => __( 'Release rights of imported content', 'course-captain' ),
			'ccap_manage_grants'           => __( 'Enroll learners and manage access', 'course-captain' ),
			'ccap_manage_access_rules'     => __( 'Manage access rules', 'course-captain' ),
			'ccap_manage_groups'           => __( 'Manage groups', 'course-captain' ),
			'ccap_view_reports'            => __( 'View reports', 'course-captain' ),
			'ccap_view_learner_details'    => __( 'View learner details', 'course-captain' ),
			'ccap_view_attempt_details'    => __( 'Read exam answers of others (always logged)', 'course-captain' ),
			'ccap_grade_attempts'          => __( 'Grade attempts', 'course-captain' ),
			'ccap_manage_learner_progress' => __( 'Correct learning progress', 'course-captain' ),
			'ccap_manage_certificates'     => __( 'Manage certificates', 'course-captain' ),
			'ccap_lock_learners'           => __( 'Lock learners', 'course-captain' ),
			'ccap_manage_settings'         => __( 'Manage settings', 'course-captain' ),
			'ccap_manage_integrations'     => __( 'Manage integrations', 'course-captain' ),
			'ccap_view_audit_log'          => __( 'View the audit log', 'course-captain' ),
			'ccap_view_system_status'      => __( 'View the system status', 'course-captain' ),
			'ccap_run_maintenance'         => __( 'Run repairs', 'course-captain' ),
			'ccap_assign_staff'            => __( 'Assign staff to courses', 'course-captain' ),
		);
		return $labels[ $cap ] ?? $cap;
	}

	/**
	 * Testable save core: one role per form.
	 *
	 * @param array<string, mixed> $post    Unslashed POST.
	 * @param int                  $user_id User.
	 * @return string Result code.
	 */
	public static function process_save( array $post, int $user_id ): string {
		if ( ! RoleCaps::can_manage( $user_id ) ) {
			return 'ccap_forbidden';
		}
		$actor     = Actor::user( $user_id );
		$confirmed = array();
		if ( ! empty( $post['_ccap_confirm'] ) ) {
			if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), 'ccap_settings_confirm_roles' ) ) {
				return 'ccap_invalid_nonce';
			}
			$pending = RiskConfirm::pending( $user_id );
			RiskConfirm::drop( $user_id );
			$risks = is_array( $pending ) && 'settings:roles' === ( $pending['scope'] ?? '' ) ? RiskConfirm::consume( $user_id, 'settings:roles', (string) ( $post['_ccap_token'] ?? '' ), (array) $pending['input'] ) : null;
			if ( null === $risks || ! is_array( $pending ) ) {
				return 'ccap_confirmation_expired';
			}
			$role      = (string) $pending['input']['role'];
			$caps      = array_values( array_map( 'strval', (array) $pending['input']['caps'] ) );
			$confirmed = $risks;
		} else {
			if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), 'ccap_settings_save_roles' ) ) {
				return 'ccap_invalid_nonce';
			}
			$role = isset( $post['role'] ) ? sanitize_key( (string) $post['role'] ) : '';
			$caps = is_array( $post['caps'] ?? null ) ? array_values( array_map( 'sanitize_key', array_map( 'strval', $post['caps'] ) ) ) : array();
		}
		try {
			$check = RoleCaps::check( $actor, $role, $caps );
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
		if ( array() !== $check['errors'] ) {
			set_transient(
				SettingsScreen::FORM_PREFIX . $user_id,
				array(
					'tab'    => 'roles',
					'input'  => array(
						'role' => $role,
						'caps' => $caps,
					),
					'errors' => $check['errors'],
				),
				SettingsScreen::FORM_TTL
			);
			return 'invalid';
		}
		if ( array() !== $check['high_risk'] && array() === $confirmed ) {
			$input = array(
				'role' => $role,
				'caps' => $caps,
			);
			RiskConfirm::park(
				$user_id,
				array(
					'scope'  => 'settings:roles',
					'input'  => $input,
					'risks'  => array( 'RC-07' ),
					'token'  => RiskConfirm::issue( $user_id, 'settings:roles', $input, array( 'RC-07' ) ),
					'before' => array( 'role' => $role ),
					'after'  => array(
						'role'      => $role,
						'high_risk' => $check['high_risk'],
					),
				)
			);
			return 'confirm';
		}
		$done = RoleCaps::apply( $actor, $role, $caps, $confirmed );
		return array() === $done['added'] && array() === $done['removed'] ? 'unchanged' : 'saved';
	}

	/**
	 * RC-07 text with role, accounts and consequences.
	 *
	 * @param array<string, string> $text   Text.
	 * @param string                $code   Code.
	 * @param array<string, mixed>  $before Before.
	 * @param array<string, mixed>  $after  After.
	 * @return array<string, string>
	 */
	public static function risk_text( $text, $code, $before, $after ) {
		if ( 'RC-07' !== $code ) {
			return $text;
		}
		unset( $before );
		$role  = (string) ( $after['role'] ?? '' );
		$names = RoleCaps::roles();
		$count = RoleCaps::counts()[ $role ] ?? 0;
		$caps  = implode( '; ', array_map( array( self::class, 'label' ), array_map( 'strval', (array) ( $after['high_risk'] ?? array() ) ) ) );
		return array(
			'title'  => __( 'Grant high-impact permissions?', 'course-captain' ),
			/* translators: 1: role name, 2: number of accounts, 3: list of permissions */
			'text'   => sprintf( _n( 'The role “%1$s” (%2$d account) receives: %3$s. The change applies immediately to all accounts with this role.', 'The role “%1$s” (%2$d accounts) receives: %3$s. The change applies immediately to all accounts with this role.', $count, 'course-captain' ), $names[ $role ] ?? $role, $count, $caps ),
			'action' => __( 'Grant permissions', 'course-captain' ),
		);
	}

	/**
	 * Admin-post: reset.
	 *
	 * @return void
	 */
	public static function handle_reset(): void {
		$result = self::process_reset( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process_reset().
		wp_safe_redirect( SettingsScreen::url( 'roles', array( 'ccap_result' => $result ) ) );
		exit;
	}

	/**
	 * Admin-post: undo.
	 *
	 * @return void
	 */
	public static function handle_undo(): void {
		$result = self::process_undo( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process_undo().
		wp_safe_redirect( SettingsScreen::url( 'roles', array( 'ccap_result' => $result ) ) );
		exit;
	}

	/**
	 * Testable reset core (needs the confirmation checkbox).
	 *
	 * @param array<string, mixed> $post    POST.
	 * @param int                  $user_id User.
	 * @return string
	 */
	public static function process_reset( array $post, int $user_id ): string {
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::RESET ) ) {
			return 'ccap_invalid_nonce';
		}
		if ( empty( $post['confirm'] ) ) {
			return 'roles_reset_confirm';
		}
		try {
			RoleCaps::reset( Actor::user( $user_id ) );
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
		return 'reset';
	}

	/**
	 * Testable undo core.
	 *
	 * @param array<string, mixed> $post    POST.
	 * @param int                  $user_id User.
	 * @return string
	 */
	public static function process_undo( array $post, int $user_id ): string {
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::UNDO ) ) {
			return 'ccap_invalid_nonce';
		}
		try {
			return RoleCaps::undo( Actor::user( $user_id ) ) ? 'undone' : 'undo_expired';
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
	}

	/**
	 * Tab HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id User.
	 * @return string
	 */
	public static function html( array $request, int $user_id ): string {
		if ( ! RoleCaps::can_manage( $user_id ) ) {
			return '';
		}
		$result = isset( $request['ccap_result'] ) ? sanitize_key( (string) $request['ccap_result'] ) : '';
		$form   = get_transient( SettingsScreen::FORM_PREFIX . $user_id );
		$form   = is_array( $form ) && 'roles' === ( $form['tab'] ?? '' ) ? $form : null;
		if ( null !== $form ) {
			delete_transient( SettingsScreen::FORM_PREFIX . $user_id );
		}
		$html  = self::message( $result, $form );
		$html .= '<h2>' . esc_html( SettingsFields::tab_label( 'roles' ) ) . '</h2><p>' . esc_html( SettingsFields::tab_intro( 'roles' ) ) . '</p>';
		if ( 'roles_reset_confirm' === $result ) {
			return $html . self::reset_page();
		}
		$roles  = RoleCaps::roles();
		$counts = RoleCaps::counts();
		$rows   = '';
		foreach ( $roles as $slug => $name ) {
			$caps  = RoleCaps::current( $slug );
			$rows .= '<tr><th scope="row">' . esc_html( $name ) . '</th><td>' . esc_html( (string) ( $counts[ $slug ] ?? 0 ) ) . '</td><td>' . esc_html( array() === $caps ? __( 'none', 'course-captain' ) : implode( ', ', array_map( array( self::class, 'label' ), $caps ) ) ) . '</td></tr>';
		}
		$html .= '<h3>' . esc_html__( 'Overview', 'course-captain' ) . '</h3><table class="widefat striped"><caption>' . esc_html__( 'Permissions per role (display only)', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Role', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Accounts', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Permissions', 'course-captain' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>';
		$html .= '<h3>' . esc_html__( 'Edit roles', 'course-captain' ) . '</h3>';
		$admin = user_can( $user_id, 'manage_options' );
		foreach ( $roles as $slug => $name ) {
			$current = null !== $form && ( $form['input']['role'] ?? '' ) === $slug ? array_map( 'strval', (array) $form['input']['caps'] ) : RoleCaps::current( $slug );
			$errors  = null !== $form && ( $form['input']['role'] ?? '' ) === $slug ? (array) $form['errors'] : array();
			/* translators: 1: role name, 2: number of accounts */
			$html .= '<details' . ( array() !== $errors ? ' open' : '' ) . '><summary>' . esc_html( sprintf( _n( '%1$s (%2$d account)', '%1$s (%2$d accounts)', (int) ( $counts[ $slug ] ?? 0 ), 'course-captain' ), $name, (int) ( $counts[ $slug ] ?? 0 ) ) ) . '</summary>';
			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-ccap-dirty-form="1"><input type="hidden" name="action" value="' . esc_attr( SettingsScreen::ACTION ) . '"><input type="hidden" name="tab" value="roles"><input type="hidden" name="role" value="' . esc_attr( $slug ) . '">' . wp_nonce_field( 'ccap_settings_save_roles', '_wpnonce', true, false );
			foreach ( self::groups() as $legend => $caps ) {
				$html .= '<fieldset><legend>' . esc_html( $legend ) . '</legend>';
				foreach ( $caps as $cap ) {
					$id       = 'ccap-role-' . $slug . '-' . $cap;
					$locked   = ! user_can( $user_id, $cap ) || ( in_array( $cap, RoleCaps::ADMIN_ONLY, true ) && ! $admin );
					$high     = in_array( $cap, RoleCaps::HIGH_RISK, true );
					$err      = $errors[ $cap ] ?? null;
					$describe = array();
					if ( $high ) {
						$describe[] = $id . '-risk';
					}
					if ( null !== $err ) {
						$describe[] = $id . '-error';
					}
					/* translators: 1: role name, 2: permission */
					$html .= '<div class="ccap-choice"><input type="checkbox" id="' . esc_attr( $id ) . '" name="caps[]" value="' . esc_attr( $cap ) . '"' . checked( in_array( $cap, $current, true ), true, false ) . ( $locked ? ' aria-disabled="true"' : '' ) . ( array() !== $describe ? ' aria-describedby="' . esc_attr( implode( ' ', $describe ) ) . '"' : '' ) . ( null !== $err ? ' aria-invalid="true"' : '' ) . '> <label for="' . esc_attr( $id ) . '">' . esc_html( sprintf( __( '%1$s: %2$s', 'course-captain' ), $name, self::label( $cap ) ) ) . '</label>';
					if ( $high ) {
						$html .= ' <span id="' . esc_attr( $id ) . '-risk" class="ccap-high-risk">' . esc_html__( 'high impact', 'course-captain' ) . '</span>';
					}
					if ( null !== $err ) {
						$html .= '<p id="' . esc_attr( $id ) . '-error" class="ccap-field-error"><strong>' . esc_html( SettingsFields::message( self::label( $cap ), (string) $err, array() ) ) . '</strong></p>';
					}
					$html .= '</div>';
				}
				$html .= '</fieldset>';
			}
			/* translators: %s: role name */
			$html .= '<p><button type="submit" class="button button-primary">' . esc_html( sprintf( __( 'Save role “%s”', 'course-captain' ), $name ) ) . '</button></p></form></details>';
		}
		$html   .= '<p>' . esc_html__( 'Administrator: always has all permissions and cannot be changed here.', 'course-captain' ) . '</p>';
		$default = (string) get_option( 'default_role', 'subscriber' );
		/* translators: %s: role name */
		$html .= '<p>' . esc_html( sprintf( __( 'Default role of new accounts: %s (set in WordPress).', 'course-captain' ), RoleCaps::roles()[ $default ] ?? $default ) ) . '</p>';
		$html .= '<p class="description">' . esc_html__( 'Authors and trainers are assigned to courses in the course itself, not here.', 'course-captain' ) . '</p>';
		if ( $admin ) {
			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-inline-form"><input type="hidden" name="action" value="' . esc_attr( self::RESET ) . '">' . wp_nonce_field( self::RESET, '_wpnonce', true, false ) . '<button type="submit" class="button">' . esc_html__( 'Reset tab …', 'course-captain' ) . '</button></form>';
			$undo  = get_option( RoleCaps::UNDO_OPTION );
			if ( is_array( $undo ) && (int) ( $undo['expires_at'] ?? 0 ) >= time() ) {
				$html .= ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-inline-form"><input type="hidden" name="action" value="' . esc_attr( self::UNDO ) . '">' . wp_nonce_field( self::UNDO, '_wpnonce', true, false ) . '<button type="submit" class="button">' . esc_html__( 'Undo the reset', 'course-captain' ) . '</button></form>';
			}
		}
		return $html;
	}

	/**
	 * Reset confirmation (RC-17 for roles, RB-8).
	 *
	 * @return string
	 */
	private static function reset_page(): string {
		return '<section aria-labelledby="ccap-confirm-title"><h3 id="ccap-confirm-title" tabindex="-1" data-ccap-focus="1">' . esc_html__( 'Reset roles?', 'course-captain' ) . '</h3><p>' . esc_html__( 'The Course Captain roles get their default permissions again. Permissions Course Captain granted to other roles are removed; permissions from role editor plugins and the administrator role stay unchanged. You can undo this for 30 days.', 'course-captain' ) . '</p>'
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::RESET ) . '"><input type="hidden" name="confirm" value="1">' . wp_nonce_field( self::RESET, '_wpnonce', true, false )
			. '<button type="submit" class="button button-primary">' . esc_html__( 'Reset roles', 'course-captain' ) . '</button> <a class="button" href="' . esc_url( SettingsScreen::url( 'roles' ) ) . '">' . esc_html__( 'Cancel', 'course-captain' ) . '</a></form></section>';
	}

	/**
	 * Result message.
	 *
	 * @param string                    $result Result.
	 * @param array<string, mixed>|null $form   Kept form.
	 * @return string
	 */
	private static function message( string $result, ?array $form ): string {
		$type = 'success';
		switch ( $result ) {
			case 'saved':
				$text = __( 'Role saved.', 'course-captain' );
				break;
			case 'unchanged':
				$text = __( 'No changes – nothing saved.', 'course-captain' );
				$type = 'info';
				break;
			case 'reset':
				$text = __( 'Roles reset. You can undo this for 30 days.', 'course-captain' );
				break;
			case 'undone':
				$text = __( 'Reset undone.', 'course-captain' );
				break;
			case 'invalid':
				/* translators: %d: number of permissions with errors */
				$text = sprintf( _n( '%d permission could not be changed:', '%d permissions could not be changed:', count( (array) ( $form['errors'] ?? array() ) ), 'course-captain' ), count( (array) ( $form['errors'] ?? array() ) ) );
				return '<div class="notice notice-error" role="alert" tabindex="-1" data-ccap-focus="1"><p>' . esc_html( $text ) . '</p></div>';
			case 'ccap_forbidden':
				$text = __( 'You are not allowed to change these settings.', 'course-captain' );
				$type = 'error';
				break;
			case 'ccap_invalid_nonce':
				$text = __( 'The form has expired. Reload the page and try again.', 'course-captain' );
				$type = 'error';
				break;
			case 'ccap_confirmation_expired':
				$text = __( 'This change needs a confirmation. Save again and confirm the dialog.', 'course-captain' );
				$type = 'error';
				break;
			default:
				return '';
		}
		return '<div class="notice notice-' . esc_attr( $type ) . '" tabindex="-1" data-ccap-focus="1"><p>' . esc_html( $text ) . '</p></div>';
	}
}
