<?php
/**
 * Admin page "Settings" with one form per tab (R0-30 §3, T-1…T-12;
 * R0-27 S-1…S-10, §2.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Notification\OutboundMode;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Security\Hardening;
use CourseCaptain\Settings\RiskConfirm;
use CourseCaptain\Settings\RiskRules;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsHints;
use CourseCaptain\Settings\SettingsSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Every tab has its own URL, form, nonce `ccap_settings_save_<tab>` and
 * `_rev`; saving is admin-post → redirect → GET. Invalid input saves
 * nothing and comes back with an error summary and field-near messages
 * (input kept in a user transient for 10 minutes). Risk fields go through
 * a confirmation page with a one-time token (works without JavaScript).
 * Reset has its own form and confirmation page and fills the undo slot.
 */
final class SettingsScreen {

	public const SLUG        = 'course-captain-settings';
	public const ACTION      = 'ccap_settings_save';
	public const RESET       = 'ccap_settings_reset';
	public const UNDO        = 'ccap_settings_undo';
	public const HINT        = 'ccap_settings_hint_dismiss';
	public const MENU_CAP    = 'ccap_menu_settings';
	public const FORM_PREFIX = 'ccap_settings_form_';
	public const FORM_TTL    = 600;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				$hook = add_submenu_page( EditorScreens::MENU, __( 'Settings', 'course-captain' ), _x( 'Settings', 'admin menu', 'course-captain' ), self::MENU_CAP, self::SLUG, array( self::class, 'render' ) );
				AdminUi::settings_assets( $hook );
			},
			19
		);
		add_filter( 'map_meta_cap', array( self::class, 'map_meta_cap' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_save' ) );
		add_action( 'admin_post_' . self::RESET, array( self::class, 'handle_reset' ) );
		add_action( 'admin_post_' . self::UNDO, array( self::class, 'handle_undo' ) );
		add_action( 'admin_post_' . self::HINT, array( self::class, 'handle_hint' ) );
		CountryProfilePanel::register();
	}

	/**
	 * `ccap_menu_settings` ⇐ #19 ∨ (#24 ∧ `promote_users`) (R0-30 §1.2).
	 *
	 * @param array<int, string> $caps Primitive caps.
	 * @param string             $cap  Requested cap.
	 * @return array<int, string>
	 */
	public static function map_meta_cap( $caps, $cap ) {
		if ( self::MENU_CAP !== $cap ) {
			return $caps;
		}
		$user = wp_get_current_user();
		if ( $user->exists() && ! $user->has_cap( Settings::CAP ) && $user->has_cap( 'ccap_assign_staff' ) ) {
			return array( 'ccap_assign_staff', 'promote_users' );
		}
		return array( Settings::CAP );
	}

	/**
	 * Script for focus handling and unsaved-change warnings.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		wp_enqueue_script( 'ccap-admin-settings', plugins_url( 'assets/js/admin-settings.js', COURSE_CAPTAIN_FILE ), array(), COURSE_CAPTAIN_VERSION, true );
	}

	/**
	 * Tabs the user may open, in display order (SR-4, T-1).
	 *
	 * @param int $user_id User.
	 * @return list<string>
	 */
	public static function tabs_for( int $user_id ): array {
		$out = array();
		foreach ( SettingsFields::TAB_ORDER as $tab ) {
			if ( self::tab_allowed( $tab, $user_id ) ) {
				$out[] = $tab;
			}
		}
		return $out;
	}

	/**
	 * Whether a user may open a tab.
	 *
	 * @param string $tab     Tab.
	 * @param int    $user_id User.
	 * @return bool
	 */
	public static function tab_allowed( string $tab, int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( 'roles' === $tab ) {
			return (bool) apply_filters( 'course_captain_settings_roles_tab', false, $user_id );
		}
		if ( ! isset( SettingsSchema::TABS[ $tab ] ) || ! user_can( $user_id, Settings::CAP ) ) {
			return false;
		}
		return 'hardening' !== $tab || user_can( $user_id, 'manage_options' );
	}

	/**
	 * Page output.
	 *
	 * @return void
	 */
	public static function render(): void {
		echo self::html( $_GET, get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.NonceVerification.Recommended -- Escaped while building; read-only view parameters.
	}

	/**
	 * Admin-post: save a tab.
	 *
	 * @return void
	 */
	public static function handle_save(): void {
		self::redirect( self::process_save( wp_unslash( $_POST ), get_current_user_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process_save().
	}

	/**
	 * Admin-post: reset a tab.
	 *
	 * @return void
	 */
	public static function handle_reset(): void {
		self::redirect( self::process_reset( wp_unslash( $_POST ), get_current_user_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process_reset().
	}

	/**
	 * Admin-post: undo the last reset of a tab.
	 *
	 * @return void
	 */
	public static function handle_undo(): void {
		self::redirect( self::process_undo( wp_unslash( $_POST ), get_current_user_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process_undo().
	}

	/**
	 * Admin-post: hide a hint line for the current person (SET-CC-01).
	 *
	 * @return void
	 */
	public static function handle_hint(): void {
		self::redirect( self::process_hint( wp_unslash( $_POST ), get_current_user_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process_hint().
	}

	/**
	 * Testable core of hiding a hint line: nonce per person and line, the
	 * tab must be open to the person; only the own user option changes.
	 *
	 * @param array<string, mixed> $post    Unslashed POST.
	 * @param int                  $user_id User.
	 * @return array{tab: string, result: string, count: int}
	 */
	public static function process_hint( array $post, int $user_id ): array {
		$tab = isset( $post['tab'] ) ? sanitize_key( (string) $post['tab'] ) : '';
		$id  = isset( $post['hint'] ) ? sanitize_key( (string) $post['hint'] ) : '';
		$out = array(
			'tab'    => $tab,
			'result' => 'ccap_forbidden',
			'count'  => 0,
		);
		if ( ! self::tab_allowed( $tab, $user_id ) ) {
			return $out;
		}
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::HINT . '_' . $id ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		$hint = SettingsHints::all()[ $id ] ?? null;
		if ( null === $hint || $tab !== $hint['tab'] ) {
			$out['result'] = 'unchanged';
			return $out;
		}
		SettingsHints::dismiss( $user_id, $id );
		$out['result'] = 'hint_hidden';
		return $out;
	}

	/**
	 * Hint lines at the end of a tab (SET-CC-01, E-346 S1): grey text, no
	 * field; each with its own small form to hide it (works without JS).
	 *
	 * @param string $tab     Tab.
	 * @param int    $user_id User.
	 * @return string
	 */
	public static function hints( string $tab, int $user_id ): string {
		$hints = SettingsHints::for_tab( $tab, $user_id );
		if ( array() === $hints ) {
			return '';
		}
		$html = '<aside class="ccap-hints" aria-label="' . esc_attr__( 'Notes about further functions', 'course-captain' ) . '"><ul class="ccap-hint-list">';
		foreach ( $hints as $hint ) {
			$html .= '<li class="ccap-hint description"><strong>' . esc_html( $hint['title'] ) . '</strong> ' . esc_html( $hint['text'] );
			if ( '' !== $hint['link'] ) {
				/* translators: %s: title of the hint line */
				$html .= ' <a href="' . esc_url( $hint['link'] ) . '">' . esc_html__( 'Learn more', 'course-captain' ) . '<span class="screen-reader-text"> ' . esc_html( sprintf( __( 'about %s', 'course-captain' ), $hint['title'] ) ) . '</span></a>';
			}
			$html .= ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-inline-form"><input type="hidden" name="action" value="' . esc_attr( self::HINT ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><input type="hidden" name="hint" value="' . esc_attr( $hint['id'] ) . '">'
				. wp_nonce_field( self::HINT . '_' . $hint['id'], '_wpnonce', true, false )
				/* translators: %s: title of the hint line */
				. '<button type="submit" class="button-link">' . esc_html__( 'Hide', 'course-captain' ) . '<span class="screen-reader-text"> ' . esc_html( sprintf( __( 'note: %s', 'course-captain' ), $hint['title'] ) ) . '</span></button></form></li>';
		}
		return $html . '</ul></aside>';
	}

	/**
	 * Testable core of saving (S-1…S-8, T-4…T-8).
	 *
	 * @param array<string, mixed> $post    Unslashed POST.
	 * @param int                  $user_id User.
	 * @return array{tab: string, result: string, count: int}
	 */
	public static function process_save( array $post, int $user_id ): array {
		$tab = isset( $post['tab'] ) ? sanitize_key( (string) $post['tab'] ) : '';
		$out = array(
			'tab'    => $tab,
			'result' => 'ok',
			'count'  => 0,
		);
		if ( ! self::tab_allowed( $tab, $user_id ) || 'roles' === $tab ) {
			$out['result'] = 'roles' === $tab ? (string) apply_filters( 'course_captain_settings_roles_save', 'ccap_forbidden', $post, $user_id ) : 'ccap_forbidden';
			return $out;
		}
		$actor     = Actor::user( $user_id );
		$scope     = 'settings:' . $tab;
		$confirmed = array();
		if ( ! empty( $post['_ccap_confirm'] ) ) {
			if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), 'ccap_settings_confirm_' . $tab ) ) {
				$out['result'] = 'ccap_invalid_nonce';
				return $out;
			}
			$pending = RiskConfirm::pending( $user_id );
			RiskConfirm::drop( $user_id );
			$risks = is_array( $pending ) && ( $pending['scope'] ?? '' ) === $scope ? RiskConfirm::consume( $user_id, $scope, (string) ( $post['_ccap_token'] ?? '' ), (array) $pending['input'] ) : null;
			if ( null === $risks || ! is_array( $pending ) ) {
				$out['result'] = 'ccap_confirmation_expired';
				return $out;
			}
			$input     = (array) $pending['input'];
			$fields    = array_values( array_filter( (array) $pending['fields'], 'is_string' ) );
			$rev       = (int) $pending['rev'];
			$confirmed = $risks;
		} else {
			if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), 'ccap_settings_save_' . $tab ) ) {
				$out['result'] = 'ccap_invalid_nonce';
				return $out;
			}
			$keys    = self::rendered_keys( $tab, $post );
			$decoded = SettingsForm::decode( $tab, $keys, $post, Settings::values( $tab ) );
			$input   = $decoded['input'];
			$fields  = $decoded['fields'];
			$rev     = (int) ( $post['_rev'] ?? -1 );
			if ( 'general' === $tab && isset( $post[ Settings::CONFIRM_WORD_FIELD ] ) ) {
				$input[ Settings::CONFIRM_WORD_FIELD ] = sanitize_text_field( (string) $post[ Settings::CONFIRM_WORD_FIELD ] );
			}
			if ( array() !== $decoded['errors'] ) {
				self::keep_form( $user_id, $tab, $input, $decoded['errors'] );
				$out['result'] = 'invalid';
				return $out;
			}
		}
		try {
			$check = Settings::check( $actor, $tab, $input, $fields );
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
			return $out;
		}
		if ( array() !== $check['errors'] ) {
			self::keep_form( $user_id, $tab, $input, SettingsForm::with_params( $check['errors'], $input, $tab ) );
			$out['result'] = 'invalid';
			return $out;
		}
		if ( Settings::rev( $tab ) !== $rev ) {
			self::keep_form( $user_id, $tab, $input, array(), true );
			$out['result'] = 'conflict';
			return $out;
		}
		$risks = RiskRules::detect( $tab, $check['before'], $check['candidate'] );
		if ( array() !== array_diff( $risks, $confirmed ) ) {
			$token = RiskConfirm::issue( $user_id, $scope, $input, $risks );
			RiskConfirm::park(
				$user_id,
				array(
					'scope'  => $scope,
					'input'  => $input,
					'fields' => $fields,
					'rev'    => $rev,
					'risks'  => $risks,
					'token'  => $token,
					'before' => $check['before'],
					'after'  => $check['candidate'],
				)
			);
			$out['result'] = 'confirm';
			return $out;
		}
		try {
			$saved = Settings::save(
				$actor,
				$tab,
				$input,
				$fields,
				$rev,
				array(
					'context'         => 'settings',
					'confirmed_risks' => $confirmed,
				)
			);
		} catch ( CourseException $e ) {
			if ( 'ccap_settings_conflict' === $e->error_code ) {
				self::keep_form( $user_id, $tab, $input, array(), true );
				$out['result'] = 'conflict';
				return $out;
			}
			$out['result'] = $e->error_code;
			return $out;
		}
		$out['result'] = array() === $saved['changed'] ? 'unchanged' : 'saved';
		$out['count']  = count( $saved['changed'] );
		return $out;
	}

	/**
	 * Testable core of reset (T-10, RC-17, R0-27 §2.4).
	 *
	 * @param array<string, mixed> $post    Unslashed POST.
	 * @param int                  $user_id User.
	 * @return array{tab: string, result: string, count: int}
	 */
	public static function process_reset( array $post, int $user_id ): array {
		$tab = isset( $post['tab'] ) ? sanitize_key( (string) $post['tab'] ) : '';
		$out = array(
			'tab'    => $tab,
			'result' => 'ok',
			'count'  => 0,
		);
		if ( ! self::tab_allowed( $tab, $user_id ) || 'roles' === $tab ) {
			$out['result'] = 'ccap_forbidden';
			return $out;
		}
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), 'ccap_settings_reset_' . $tab ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		if ( empty( $post['confirm'] ) ) {
			$out['result'] = 'reset_confirm';
			return $out;
		}
		try {
			$out['count']  = count( Settings::reset( Actor::user( $user_id ), $tab ) );
			$out['result'] = 'reset';
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
		}
		return $out;
	}

	/**
	 * Testable core of undo.
	 *
	 * @param array<string, mixed> $post    Unslashed POST.
	 * @param int                  $user_id User.
	 * @return array{tab: string, result: string, count: int}
	 */
	public static function process_undo( array $post, int $user_id ): array {
		$tab = isset( $post['tab'] ) ? sanitize_key( (string) $post['tab'] ) : '';
		$out = array(
			'tab'    => $tab,
			'result' => 'ok',
			'count'  => 0,
		);
		if ( ! self::tab_allowed( $tab, $user_id ) || 'roles' === $tab ) {
			$out['result'] = 'ccap_forbidden';
			return $out;
		}
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), 'ccap_settings_undo_' . $tab ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		try {
			$out['result'] = Settings::undo( Actor::user( $user_id ), $tab ) ? 'undone' : 'undo_expired';
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
		}
		return $out;
	}

	/**
	 * Page HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id User.
	 * @return string
	 */
	public static function html( array $request, int $user_id ): string {
		$tabs = self::tabs_for( $user_id );
		if ( array() === $tabs ) {
			return '<div class="wrap"><h1>' . esc_html__( 'Settings', 'course-captain' ) . '</h1><p>' . esc_html__( 'You are not allowed to change settings.', 'course-captain' ) . '</p></div>';
		}
		$tab    = isset( $request['tab'] ) ? sanitize_key( (string) $request['tab'] ) : '';
		$tab    = in_array( $tab, $tabs, true ) ? $tab : $tabs[0];
		$result = isset( $request['ccap_result'] ) ? sanitize_key( (string) $request['ccap_result'] ) : '';
		$html   = '<div class="wrap ccap-settings"><h1>' . esc_html__( 'Settings', 'course-captain' ) . '</h1>';
		if ( user_can( $user_id, Settings::CAP ) ) {
			$html .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . SetupWizard::SLUG ) ) . '" data-ccap-leave="' . esc_attr( self::leave_text() ) . '">' . esc_html__( 'Open the setup assistant', 'course-captain' ) . '</a> · <a href="' . esc_url( admin_url( 'admin.php?page=' . SettingsBackup::SLUG ) ) . '" data-ccap-leave="' . esc_attr( self::leave_text() ) . '">' . esc_html__( 'Back up settings', 'course-captain' ) . '</a> · <a href="' . esc_url( admin_url( 'admin.php?page=' . SettingLocksScreen::SLUG ) ) . '" data-ccap-leave="' . esc_attr( self::leave_text() ) . '">' . esc_html__( 'Locks and course overrides', 'course-captain' ) . '</a></p>';
		}
		$html .= self::nav( $tabs, $tab );
		if ( 'confirm' === $result ) {
			return $html . self::confirm_page( $tab, $user_id ) . '</div>';
		}
		if ( 'roles' === $tab ) {
			$result = isset( $request['ccap_result'] ) ? sanitize_key( (string) $request['ccap_result'] ) : '';
			return $html . ( 'hint_hidden' === $result ? self::message( $tab, $result, 0, null ) : '' ) . (string) apply_filters( 'course_captain_settings_roles_html', '', $request, $user_id ) . self::hints( $tab, $user_id ) . '</div>';
		}
		if ( 'reset_confirm' === $result ) {
			return $html . self::reset_page( $tab, $user_id ) . '</div>';
		}
		$form   = self::take_form( $user_id, $tab );
		$html  .= self::message( $tab, $result, absint( $request['ccap_count'] ?? 0 ), $form );
		$html  .= self::tab_form( $tab, $user_id, $form );
		$status = '';
		if ( 'general' === $tab ) {
			// SET-CC-07: suggestions of the country profile with before/after; nothing is applied silently.
			$html .= CountryProfilePanel::html( 'settings', $request, $user_id );
		}
		if ( 'certificates' === $tab ) {
			// The base template is edited on the certificates screen (A-11), not a second form here.
			$html .= '<h3>' . esc_html__( 'Base template', 'course-captain' ) . '</h3><p><a href="' . esc_url( admin_url( 'admin.php?page=course-captain-certificates' ) ) . '" data-ccap-leave="' . esc_attr( self::leave_text() ) . '">' . esc_html__( 'Edit the base template under Certificates', 'course-captain' ) . '</a></p>';
		}
		if ( 'emails' === $tab ) {
			$html  .= '<h3>' . esc_html__( 'Mail texts', 'course-captain' ) . '</h3><p>' . esc_html__( 'Subject and text of every message, per language, with preview, test mail and export.', 'course-captain' ) . ' <a href="' . esc_url( MailTextsScreen::url() ) . '" data-ccap-leave="' . esc_attr( self::leave_text() ) . '">' . esc_html__( 'Edit mail texts', 'course-captain' ) . '</a></p>';
			$html  .= self::outbound_status();
			$html  .= '<h3>' . esc_html__( 'Test mail', 'course-captain' ) . '</h3>' . TestMail::form( $user_id, 'settings' );
			$status = TestMail::message( isset( $request['ccap_test_mail'] ) ? sanitize_key( (string) $request['ccap_test_mail'] ) : '' );
		}
		return $html . self::hints( $tab, $user_id ) . '<div role="status" aria-live="polite" id="ccap-status">' . esc_html( $status ) . '</div></div>';
	}

	/**
	 * Read-only test mode of outgoing mails (SET-CC-23, R0-27 §3.6): the
	 * constants in wp-config.php stay the only source.
	 *
	 * @return string
	 */
	public static function outbound_status(): string {
		$mode     = OutboundMode::current();
		$constant = defined( 'COURSE_CAPTAIN_OUTBOUND' );
		switch ( $mode['mode'] ) {
			case OutboundMode::ON:
				$text = __( 'On: mails go to the real recipients.', 'course-captain' );
				break;
			case OutboundMode::CATCH:
				/* translators: %s: catch address */
				$text = sprintf( __( 'Test mode: every mail goes only to %s, with “[catch]” in front of the subject. Learners receive nothing.', 'course-captain' ), $mode['catch_address'] );
				break;
			default:
				$text = 'invalid_catch_address' === $mode['warning']
					? __( 'Off: the catch address is not a valid email address, so no mails are sent at all.', 'course-captain' )
					: __( 'Off: no mails are sent at all.', 'course-captain' );
		}
		$source = $constant
			/* translators: %s: value of the constant */
			? sprintf( __( 'Set in wp-config.php: COURSE_CAPTAIN_OUTBOUND = %s', 'course-captain' ), (string) constant( 'COURSE_CAPTAIN_OUTBOUND' ) )
			/* translators: %s: environment type */
			: sprintf( __( 'Automatic for the environment type “%s”: on only in production, otherwise test mode.', 'course-captain' ), wp_get_environment_type() );
		$rows  = '<tr><th scope="row">' . esc_html__( 'Outgoing mails', 'course-captain' ) . '</th><td>' . esc_html( $text ) . '</td></tr>';
		$rows .= '<tr><th scope="row">' . esc_html__( 'Source', 'course-captain' ) . '</th><td>' . esc_html( $source ) . '</td></tr>';
		$rows .= '<tr><th scope="row">' . esc_html__( 'Catch address', 'course-captain' ) . '</th><td>' . esc_html( defined( 'COURSE_CAPTAIN_MAIL_CATCH_ADDRESS' ) ? (string) constant( 'COURSE_CAPTAIN_MAIL_CATCH_ADDRESS' ) : __( 'not set – the administration address of the website is used', 'course-captain' ) ) . '</td></tr>';
		if ( OutboundMode::is_held() ) {
			$rows .= '<tr><th scope="row">' . esc_html__( 'Hold mode', 'course-captain' ) . '</th><td>' . esc_html__( 'Active: the website seems to have moved or been restored. Mails are held until you confirm the website under System status.', 'course-captain' ) . '</td></tr>';
		}
		return '<h3>' . esc_html__( 'Test mode', 'course-captain' ) . '</h3><table class="widefat striped ccap-outbound" id="ccap-outbound-mode"><caption>' . esc_html__( 'Read only: change it with the constants COURSE_CAPTAIN_OUTBOUND (on, catch, off) and COURSE_CAPTAIN_MAIL_CATCH_ADDRESS in wp-config.php.', 'course-captain' ) . '</caption><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * Area navigation (M-3; links, `aria-current="page"`).
	 *
	 * @param array  $tabs    Allowed tabs.
	 * @param string $current Current tab.
	 * @phpstan-param list<string> $tabs
	 * @return string
	 */
	private static function nav( array $tabs, string $current ): string {
		$html = '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Settings: areas', 'course-captain' ) . '">';
		foreach ( $tabs as $tab ) {
			$url   = add_query_arg(
				array(
					'page' => self::SLUG,
					'tab'  => $tab,
				),
				admin_url( 'admin.php' )
			);
			$html .= '<a class="nav-tab' . ( $tab === $current ? ' nav-tab-active" aria-current="page' : '' ) . '" href="' . esc_url( $url ) . '" data-ccap-leave="' . esc_attr( self::leave_text() ) . '">' . esc_html( SettingsFields::tab_label( $tab ) ) . '</a>';
		}
		return $html . '</nav>';
	}

	/**
	 * UD-1 text for the unsaved-changes warning (JavaScript only).
	 *
	 * @return string
	 */
	public static function leave_text(): string {
		return __( 'You changed settings in this form but did not save them. Leave the page and discard the changes?', 'course-captain' );
	}

	/**
	 * Result message (T-4, T-6, T-7).
	 *
	 * @param string                    $tab    Tab.
	 * @param string                    $result Result code.
	 * @param int                       $count  Count.
	 * @param array<string, mixed>|null $form   Kept form.
	 * @return string
	 */
	private static function message( string $tab, string $result, int $count, ?array $form ): string {
		$label = SettingsFields::tab_label( $tab );
		$type  = 'success';
		switch ( $result ) {
			case 'saved':
				$warnings = count( self::warnings( $tab, Settings::values( $tab ) ) );
				$text     = 0 === $warnings
					/* translators: %s: tab name */
					? sprintf( __( 'Settings “%s” saved.', 'course-captain' ), $label )
					/* translators: 1: tab name, 2: number of warnings */
					: sprintf( _n( 'Settings “%1$s” saved, %2$d warning.', 'Settings “%1$s” saved, %2$d warnings.', $warnings, 'course-captain' ), $label, $warnings );
				break;
			case 'unchanged':
				$text = __( 'No changes – nothing saved.', 'course-captain' );
				$type = 'info';
				break;
			case 'reset':
				/* translators: %d: number of settings */
				$text = sprintf( _n( 'Tab reset: %d setting has its default value again. You can undo this for 30 days.', 'Tab reset: %d settings have their default value again. You can undo this for 30 days.', $count, 'course-captain' ), $count );
				break;
			case 'undone':
				$text = __( 'Reset undone.', 'course-captain' );
				break;
			case 'hint_hidden':
				$text = __( 'The note is hidden for you.', 'course-captain' );
				$type = 'info';
				break;
			case 'undo_expired':
				$text = __( 'There is nothing to undo any more.', 'course-captain' );
				$type = 'warning';
				break;
			case 'conflict':
				$writer = Settings::last_writer( $tab );
				$user   = get_userdata( $writer['user_id'] );
				$fields = array();
				foreach ( is_array( $form ) ? (array) $form['input'] : array() as $field => $value ) {
					$stored = Settings::values( $tab )[ $field ] ?? null;
					if ( $stored !== $value && null !== SettingsFields::schema( $tab, (string) $field ) ) {
						$fields[] = SettingsFields::ui( $tab, SettingsForm::first_key( (string) $field, self::all_keys( $tab ) ) )['label'];
					}
				}
				$text = sprintf(
					/* translators: 1: display name, 2: time, 3: list of fields */
					__( 'This tab was changed in the meantime by %1$s (%2$s). Affected: %3$s. Your input is still here. Check the new values and save again.', 'course-captain' ),
					$user instanceof \WP_User ? $user->display_name : __( 'someone else', 'course-captain' ),
					'' === $writer['at'] ? '-' : (string) wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), (int) strtotime( $writer['at'] ) ),
					array() === $fields ? '-' : implode( ', ', $fields )
				);
				$text .= ' <a href="' . esc_url( self::url( $tab ) ) . '">' . esc_html__( 'Load new values (discard my input)', 'course-captain' ) . '</a>';
				return '<div id="ccap-settings-message" class="notice notice-warning" tabindex="-1" data-ccap-focus="1"><p>' . wp_kses( $text, array( 'a' => array( 'href' => true ) ) ) . '</p></div>';
			case 'ccap_invalid_nonce':
				$text = __( 'The form has expired. Reload the page and try again.', 'course-captain' );
				$type = 'error';
				break;
			case 'ccap_forbidden':
				$text = __( 'You are not allowed to change these settings.', 'course-captain' );
				$type = 'error';
				break;
			case 'ccap_confirmation_expired':
				$text = __( 'This change needs a confirmation. Save again and confirm the dialog.', 'course-captain' );
				$type = 'error';
				break;
			default:
				return '';
		}
		return '<div id="ccap-settings-message" class="notice notice-' . esc_attr( $type ) . '" tabindex="-1" data-ccap-focus="1"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Form of a tab.
	 *
	 * @param string                    $tab     Tab.
	 * @param int                       $user_id User.
	 * @param array<string, mixed>|null $form    Kept form after an error.
	 * @return string
	 */
	private static function tab_form( string $tab, int $user_id, ?array $form ): string {
		$actor     = Actor::user( $user_id );
		$values    = Settings::values( $tab );
		$input     = is_array( $form ) ? (array) $form['input'] : array();
		$errors    = is_array( $form ) ? (array) $form['errors'] : array();
		$conflict  = is_array( $form ) && ! empty( $form['conflict'] );
		$keys      = self::all_keys( $tab );
		$read_only = 'hardening' === $tab && Hardening::kill_switch();
		$warnings  = self::warnings( $tab, $values );
		$status    = 'hardening' === $tab ? self::hardening_status() : array();
		$form_id   = 'cc-settings-' . $tab;
		$html      = SettingsForm::summary( $tab, $errors, $keys );
		$html     .= '<h2>' . esc_html( SettingsFields::tab_label( $tab ) ) . '</h2><p>' . esc_html( SettingsFields::tab_intro( $tab ) ) . '</p>';
		if ( 'hardening' === $tab ) {
			$html .= self::hardening_intro( $read_only );
		}
		$html  .= '<form id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-ccap-dirty-form="1" novalidate>';
		$html  .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><input type="hidden" name="_rev" value="' . esc_attr( (string) Settings::rev( $tab ) ) . '">';
		$html  .= wp_nonce_field( 'ccap_settings_save_' . $tab, '_wpnonce', true, false );
		$schema = SettingsSchema::fields()[ $tab ] ?? array();
		foreach ( SettingsFields::layout( $tab ) as $section ) {
			$rows = '';
			foreach ( $section[1] as $key ) {
				list( $f ) = explode( '.', $key, 2 );
				if ( ! isset( $schema[ $f ] ) || ! Settings::allowed( $actor, (string) $schema[ $f ]['cap'] ) ) {
					continue;
				}
				$value = array_key_exists( $f, $input ) ? SettingsForm::value_of( $input, $key ) : SettingsForm::value_of( $values, $key );
				$opts  = array( 'readonly' => $read_only );
				if ( isset( $warnings[ $f ] ) ) {
					$opts['warning'] = $warnings[ $f ];
				}
				if ( isset( $status[ $f ] ) ) {
					$opts['status'] = $status[ $f ];
				}
				if ( $conflict && array_key_exists( $f, $input ) && $input[ $f ] !== $values[ $f ] ) {
					/* translators: %s: current stored value */
					$opts['warning'] = sprintf( __( 'Changed in the meantime to: %s', 'course-captain' ), SettingsForm::display( SettingsFields::ui( $tab, $key ), (array) SettingsFields::schema( $tab, $key ), SettingsForm::value_of( $values, $key ) ) );
				}
				$reason = self::disabled_reason( $tab, $key, array_merge( $values, $input ) );
				if ( '' !== $reason ) {
					$opts['disabled_reason'] = $reason;
				}
				if ( 'learning' === $tab && 'mastery_algorithm' === $key && 1 === count( (array) $schema['mastery_algorithm']['values'] ) ) {
					$rows .= self::static_row( $tab, $key, SettingsFields::ui( $tab, $key )['options']['smarttrainer.v1'] );
					continue;
				}
				// SET-CC-02: every retention period carries the marker "Legal review needed"; the filter notice is field-near.
				if ( 'privacy' === $tab && str_starts_with( $key, 'retention.' ) ) {
					$sub            = substr( $key, 10 );
					$opts['status'] = RetentionPreview::status();
					$filter_note    = RetentionPreview::filter_warning( $sub );
					if ( '' !== $filter_note ) {
						$opts['warning'] = $filter_note;
					}
					if ( 'static' === SettingsFields::ui( $tab, $key )['element'] ) {
						$rows .= self::static_row( $tab, $key, RetentionPreview::value_text( $sub, null ), $opts['status'] );
						continue;
					}
				}
				$rows .= '<input type="hidden" name="_fields[]" value="' . esc_attr( $key ) . '">' . SettingsForm::field( $tab, $key, $value, SettingsForm::error_for( $errors, $key ), $opts );
				if ( 'general' === $tab && 'delete_data_on_uninstall' === $key && true !== $values[ $f ] ) {
					$rows .= self::confirm_word_field( $tab, isset( $errors[ $f ] ) );
				}
			}
			if ( '' !== $rows && 'privacy' === $tab && str_starts_with( (string) ( $section[1][0] ?? '' ), 'retention.' ) ) {
				$rows = RetentionPreview::intro() . $rows . RetentionPreview::table();
			}
			if ( '' !== $rows ) {
				$html .= '<h3>' . esc_html( $section[0] ) . '</h3>' . $rows;
			}
		}
		$html .= '</form>';
		if ( ! $read_only ) {
			$html .= '<p class="submit"><button type="submit" form="' . esc_attr( $form_id ) . '" class="button button-primary">' . esc_html__( 'Save changes', 'course-captain' ) . '</button></p>';
			$html .= self::reset_controls( $tab, $values );
		}
		return $html;
	}

	/**
	 * Reset and undo forms (T-10): own forms, never triggered by Enter in a field.
	 *
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $values Current values.
	 * @return string
	 */
	private static function reset_controls( string $tab, array $values ): string {
		$deviations = count( self::deviations( $tab, $values ) );
		$html       = '<form id="cc-reset-' . esc_attr( $tab ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-inline-form">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::RESET ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">'
			. wp_nonce_field( 'ccap_settings_reset_' . $tab, '_wpnonce', true, false );
		if ( 0 === $deviations ) {
			$html .= '<button type="button" class="button" aria-disabled="true" aria-describedby="cc-reset-' . esc_attr( $tab ) . '-hint">' . esc_html__( 'Reset tab …', 'course-captain' ) . '</button> <span id="cc-reset-' . esc_attr( $tab ) . '-hint">' . esc_html__( 'All values match the default.', 'course-captain' ) . '</span>';
		} else {
			$html .= '<button type="submit" class="button">' . esc_html__( 'Reset tab …', 'course-captain' ) . '</button>';
		}
		$html .= '</form>';
		$until = Settings::undo_until( $tab );
		if ( $until > 0 ) {
			$html .= ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-inline-form"><input type="hidden" name="action" value="' . esc_attr( self::UNDO ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">'
				. wp_nonce_field( 'ccap_settings_undo_' . $tab, '_wpnonce', true, false )
				/* translators: %s: date until which undo is possible */
				. '<button type="submit" class="button">' . esc_html( sprintf( __( 'Undo the reset (possible until %s)', 'course-captain' ), (string) wp_date( (string) get_option( 'date_format' ), $until ) ) ) . '</button></form>';
		}
		return $html;
	}

	/**
	 * Fields whose value differs from the default.
	 *
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $values Values.
	 * @return list<string>
	 */
	public static function deviations( string $tab, array $values ): array {
		$out = array();
		foreach ( SettingsSchema::fields()[ $tab ] ?? array() as $field => $def ) {
			if ( ( $values[ $field ] ?? null ) !== $def['default'] ) {
				$out[] = (string) $field;
			}
		}
		return $out;
	}

	/**
	 * Reset confirmation page (RC-17, D-7).
	 *
	 * @param string $tab     Tab.
	 * @param int    $user_id User.
	 * @return string
	 */
	private static function reset_page( string $tab, int $user_id ): string {
		if ( ! self::tab_allowed( $tab, $user_id ) ) {
			return '';
		}
		$values = Settings::values( $tab );
		$keys   = self::all_keys( $tab );
		$items  = '';
		$devs   = self::deviations( $tab, $values );
		foreach ( $devs as $field ) {
			$key    = SettingsForm::first_key( $field, $keys );
			$ui     = SettingsFields::ui( $tab, $key );
			$def    = (array) SettingsFields::schema( $tab, $field );
			$items .= '<li>' . esc_html( $ui['label'] . ': ' . SettingsForm::display( $ui, $def, $values[ $field ] ) . ' → ' . SettingsForm::display( $ui, $def, $def['default'] ?? null ) ) . '</li>';
		}
		/* translators: %s: tab name */
		$title = sprintf( __( 'Reset tab “%s”?', 'course-captain' ), SettingsFields::tab_label( $tab ) );
		/* translators: %d: number of settings */
		$text = sprintf( _n( 'This %d setting gets its default value again:', 'These %d settings get their default value again:', count( $devs ), 'course-captain' ), count( $devs ) );
		return '<section aria-labelledby="ccap-confirm-title"><h2 id="ccap-confirm-title" tabindex="-1" data-ccap-focus="1">' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p><ul>' . $items . '</ul>'
			. '<p>' . esc_html__( 'Not affected: other tabs, settings of single courses and exams, learning progress, access, groups, attempts and certificates. You can undo this for 30 days.', 'course-captain' ) . '</p>'
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::RESET ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><input type="hidden" name="confirm" value="1">'
			. wp_nonce_field( 'ccap_settings_reset_' . $tab, '_wpnonce', true, false )
			. '<button type="submit" class="button button-primary">' . esc_html__( 'Reset tab', 'course-captain' ) . '</button> <a class="button" href="' . esc_url( self::url( $tab ) ) . '">' . esc_html__( 'Cancel', 'course-captain' ) . '</a></form></section>';
	}

	/**
	 * Risk confirmation page (D-1…D-8 without JavaScript: D-7).
	 *
	 * @param string $tab     Tab.
	 * @param int    $user_id User.
	 * @return string
	 */
	private static function confirm_page( string $tab, int $user_id ): string {
		$pending = RiskConfirm::pending( $user_id );
		if ( ! is_array( $pending ) || ( $pending['scope'] ?? '' ) !== 'settings:' . $tab ) {
			return '<div class="notice notice-error" tabindex="-1" data-ccap-focus="1"><p>' . esc_html__( 'This change needs a confirmation. Save again and confirm the dialog.', 'course-captain' ) . '</p></div>' . self::tab_form( $tab, $user_id, null );
		}
		return RiskDialog::page(
			(array) $pending['risks'],
			(array) $pending['before'],
			(array) $pending['after'],
			array(
				'action' => self::ACTION,
				'nonce'  => 'ccap_settings_confirm_' . $tab,
				'token'  => (string) $pending['token'],
				'hidden' => array( 'tab' => $tab ),
				'cancel' => self::url( $tab ),
			)
		);
	}

	/**
	 * Warnings that do not block saving (T-6): F-62, F4.7, F-90, F-91, Δ-2, Δ-4.
	 *
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $values Values.
	 * @return array<string, string>
	 */
	public static function warnings( string $tab, array $values ): array {
		$out = array();
		switch ( $tab ) {
			case 'emails':
				$address = (string) ( $values['from_address'] ?? '' );
				$site    = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
				$domain  = strtolower( (string) substr( (string) strrchr( $address, '@' ), 1 ) );
				if ( '' !== $address && '' !== $domain && $domain !== $site && ! str_ends_with( $site, '.' . $domain ) ) {
					/* translators: %s: domain of the website */
					$out['from_address'] = sprintf( __( 'Warning: the address does not belong to the domain of this website (%s). Without matching SPF/DMARC records mails often land in spam.', 'course-captain' ), $site );
				}
				break;
			case 'enrollment':
				if ( true === ( $values['self_enrollment'] ?? false ) && ! get_option( 'users_can_register' ) ) {
					$out['self_enrollment'] = __( 'Warning: new people cannot register on this website. Only people with an existing account can enroll themselves.', 'course-captain' );
				}
				if ( (int) ( $values['invite_valid_days'] ?? 0 ) > 90 ) {
					$out['invite_valid_days'] = __( 'Warning: long validity keeps invitation links usable and invited addresses stored for longer.', 'course-captain' );
				}
				break;
			case 'privacy':
				if ( (int) ( $values['backup_max_age_days'] ?? 90 ) < 30 ) {
					$out['backup_max_age_days'] = __( 'Warning: deletion markers can expire before your backups are rotated.', 'course-captain' );
				}
				break;
			case 'hardening':
				if ( 'off' !== ( $values['login_limit'] ?? 'off' ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && array() === (array) Settings::get( 'privacy.trusted_proxies' ) ) {
					$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
					/* translators: %s: IP address of the proxy */
					$out['login_limit'] = sprintf( __( 'Warning: all requests seem to come from %s (probably a proxy or load balancer). Enter it under Privacy & security › Trusted proxies, otherwise a login lockout hits everyone at once.', 'course-captain' ), $ip );
				}
				if ( isset( Hardening::conflicts()['xmlrpc'] ) ) {
					/* translators: %s: plugin slug */
					$out['xmlrpc'] = sprintf( __( 'Warning: %s uses XML-RPC. After switching it off, parts of it no longer work.', 'course-captain' ), Hardening::conflicts()['xmlrpc'] );
				}
				break;
		}
		return $out;
	}

	/**
	 * Reason for `aria-disabled` of dependent fields (T-12).
	 *
	 * @param string               $tab    Tab.
	 * @param string               $key    Key.
	 * @param array<string, mixed> $values Values.
	 * @return string
	 */
	private static function disabled_reason( string $tab, string $key, array $values ): string {
		$ui = SettingsFields::ui( $tab, $key );
		if ( '' === $ui['depends'] ) {
			return '';
		}
		$dep = SettingsForm::value_of( $values, $ui['depends'] );
		if ( ( 'feedback.visibility' === $ui['depends'] && 'never' === $dep )
			|| ( 'max_attempts' === $ui['depends'] && null === $dep )
			|| ( 'external_media_mode' === $ui['depends'] && 'click_to_load' !== $dep )
			|| ( 'login_limit' === $ui['depends'] && 'off' === $dep )
			|| ( 'language' === $ui['depends'] && 'fixed' !== $dep ) ) {
			return __( 'Currently without effect because of the setting it depends on; the stored value is kept.', 'course-captain' );
		}
		return '';
	}

	/**
	 * Read-only row (single registered strategy, ADM-095).
	 *
	 * @param string $tab    Tab.
	 * @param string $key    Key.
	 * @param string $text   Text.
	 * @param string $status Status marker (e.g. "Legal review needed").
	 * @return string
	 */
	private static function static_row( string $tab, string $key, string $text, string $status = '' ): string {
		$ui   = SettingsFields::ui( $tab, $key );
		$id   = SettingsForm::id( $tab, $key );
		$meta = '';
		if ( '' !== $status ) {
			/* translators: 1: default value, 2: scope */
			$meta = '<p id="' . esc_attr( $id . '-meta' ) . '" class="description ccap-field-meta">' . esc_html( sprintf( __( 'Default: %1$s · Applies: %2$s', 'course-captain' ), $text, SettingsFields::scope_label( (string) $ui['scope'] ) ) ) . '</p>';
		}
		return '<div class="ccap-field"><p class="ccap-field-label"><strong>' . esc_html( $ui['label'] ) . '</strong></p><p>' . esc_html( $text ) . ( '' === $status ? '' : ' <span id="' . esc_attr( $id . '-status' ) . '" class="ccap-field-status">' . esc_html( $status ) . '</span>' ) . '</p><p id="' . esc_attr( $id . '-help' ) . '" class="description">' . esc_html( $ui['help'] ) . '</p>' . $meta . '</div>';
	}

	/**
	 * SET-CC-30 confirmation input (typed word, checked on the server).
	 *
	 * @param string $tab     Tab.
	 * @param bool   $invalid Invalid.
	 * @return string
	 */
	private static function confirm_word_field( string $tab, bool $invalid ): string {
		$id = SettingsForm::id( $tab, 'confirm-delete' );
		return '<div class="ccap-field"><label for="' . esc_attr( $id ) . '">' . esc_html( sprintf( /* translators: 1: German confirmation word, 2: English confirmation word (both typed literally) */ __( 'To switch it on, type %1$s or %2$s:', 'course-captain' ), 'LÖSCHEN', 'DELETE' ) ) . '</label> <input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( Settings::CONFIRM_WORD_FIELD ) . '" autocomplete="off" spellcheck="false" class="regular-text" aria-describedby="' . esc_attr( SettingsForm::id( $tab, 'delete_data_on_uninstall' ) ) . '-help"' . ( $invalid ? ' aria-invalid="true"' : '' ) . '></div>';
	}

	/**
	 * Detected security plugins, emergency notes, limits (R0-30 §3.3.10).
	 *
	 * @param bool $read_only Kill switch active.
	 * @return string
	 */
	private static function hardening_intro( bool $read_only ): string {
		$html = '';
		if ( $read_only ) {
			$html .= '<div class="notice notice-warning inline"><p>' . esc_html__( 'Website hardening is switched off in wp-config.php (COURSE_CAPTAIN_HARDENING). Changes here only take effect once the constant is removed.', 'course-captain' ) . '</p></div>';
		}
		$conflicts = array_unique( array_values( Hardening::conflicts() ) );
		$html     .= '<h3>' . esc_html__( 'Detected security plugins', 'course-captain' ) . '</h3>';
		$html     .= array() === $conflicts ? '<p>' . esc_html__( 'No security plugin detected.', 'course-captain' ) . '</p>' : '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $conflicts ) ) . '</li></ul>';
		$rows      = '';
		$labels    = array(
			'login_ip'         => __( 'Failed logins per address', 'course-captain' ),
			'login_account_ip' => __( 'Failed logins per account and address', 'course-captain' ),
			'login_account'    => __( 'Failed logins per account', 'course-captain' ),
			'reset_ip'         => __( 'Lost password per address', 'course-captain' ),
			'reset_account_ip' => __( 'Lost password per account and address', 'course-captain' ),
			'reset_account'    => __( 'Lost password per account', 'course-captain' ),
			'registration_ip'  => __( 'Registrations per address', 'course-captain' ),
		);
		foreach ( Hardening::limits() as $key => $limit ) {
			/* translators: 1: number of attempts, 2: minutes */
			$rows .= '<tr><th scope="row">' . esc_html( $labels[ $key ] ?? $key ) . '</th><td>' . esc_html( sprintf( __( '%1$d in %2$d min', 'course-captain' ), $limit[0], intdiv( $limit[1], 60 ) ) ) . '</td></tr>';
		}
		$html .= '<table class="widefat striped ccap-limits"><caption>' . esc_html__( 'Effective limits (the filter course_captain_hardening_limits overrides the values above)', 'course-captain' ) . '</caption><tbody>' . $rows . '</tbody></table>';
		$html .= '<h3>' . esc_html__( 'Emergency', 'course-captain' ) . '</h3><p>' . esc_html__( 'If you lock yourself out: add define( \'COURSE_CAPTAIN_HARDENING\', \'off\' ); to wp-config.php or run wp course-captain hardening disable.', 'course-captain' ) . '</p>';
		return $html;
	}

	/**
	 * Status text per hardening feature.
	 *
	 * @return array<string, string>
	 */
	public static function hardening_status(): array {
		$out = array();
		foreach ( Hardening::states() as $feature => $state ) {
			if ( 'active' === $state ) {
				$out[ $feature ] = __( 'Status: active', 'course-captain' );
			} elseif ( str_starts_with( $state, 'held:' ) ) {
				/* translators: %s: plugin slug */
				$out[ $feature ] = sprintf( __( 'Status: paused because of %s', 'course-captain' ), substr( $state, 5 ) );
			} elseif ( 'off:unconfirmed' === $state ) {
				$out[ $feature ] = __( 'Status: off until the tab is saved once', 'course-captain' );
			} elseif ( 'off:not_needed' === $state ) {
				$out[ $feature ] = __( 'Status: not needed (registration closed)', 'course-captain' );
			} else {
				$out[ $feature ] = __( 'Status: off', 'course-captain' );
			}
		}
		return $out;
	}

	/**
	 * All keys of a tab layout.
	 *
	 * @param string $tab Tab.
	 * @return list<string>
	 */
	public static function all_keys( string $tab ): array {
		$keys = array();
		foreach ( SettingsFields::layout( $tab ) as $section ) {
			$keys = array_merge( $keys, $section[1] );
		}
		return $keys;
	}

	/**
	 * Rendered keys of the POST (`_fields[]` ∩ layout, S-4).
	 *
	 * @param string               $tab  Tab.
	 * @param array<string, mixed> $post POST.
	 * @return list<string>
	 */
	private static function rendered_keys( string $tab, array $post ): array {
		$posted = is_array( $post['_fields'] ?? null ) ? array_map( 'strval', $post['_fields'] ) : array();
		return array_values( array_intersect( self::all_keys( $tab ), $posted ) );
	}

	/**
	 * Keep the form after an error (T-5: user transient, 10 minutes).
	 *
	 * @param int                  $user_id  User.
	 * @param string               $tab      Tab.
	 * @param array<string, mixed> $input    Input.
	 * @param array<string, mixed> $errors   Errors.
	 * @param bool                 $conflict Conflict.
	 * @return void
	 */
	private static function keep_form( int $user_id, string $tab, array $input, array $errors, bool $conflict = false ): void {
		unset( $input[ Settings::CONFIRM_WORD_FIELD ] );
		set_transient(
			self::FORM_PREFIX . $user_id,
			array(
				'tab'      => $tab,
				'input'    => $input,
				'errors'   => $errors,
				'conflict' => $conflict,
			),
			self::FORM_TTL
		);
	}

	/**
	 * Take the kept form of a tab (read once).
	 *
	 * @param int    $user_id User.
	 * @param string $tab     Tab.
	 * @return array<string, mixed>|null
	 */
	private static function take_form( int $user_id, string $tab ): ?array {
		$form = get_transient( self::FORM_PREFIX . $user_id );
		if ( ! is_array( $form ) || ( $form['tab'] ?? '' ) !== $tab ) {
			return null;
		}
		delete_transient( self::FORM_PREFIX . $user_id );
		return $form;
	}

	/**
	 * Tab URL.
	 *
	 * @param string               $tab  Tab.
	 * @param array<string, mixed> $args Extra args.
	 * @return string
	 */
	public static function url( string $tab, array $args = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::SLUG,
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Redirect after POST.
	 *
	 * @param array{tab: string, result: string, count: int} $out Result.
	 * @return void
	 */
	private static function redirect( array $out ): void {
		wp_safe_redirect(
			self::url(
				$out['tab'],
				array(
					'ccap_result' => $out['result'],
					'ccap_count'  => $out['count'],
				)
			)
		);
		exit;
	}
}
