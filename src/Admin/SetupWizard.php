<?php
/**
 * Setup assistant (R0-30 §2, A-02; W-1…W-8; ADM-020…ADM-049).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Security\Hardening;
use CourseCaptain\Settings\RiskConfirm;
use CourseCaptain\Settings\RiskRules;
use CourseCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Eight steps, each its own page (works without JavaScript). "Save and
 * continue" writes only the fields of the step through the settings
 * service (partial save, all-or-nothing across the tabs of the step,
 * `_rev`, risk confirmation, audit `settings_saved` with
 * `context = setup` and the step key). "Skip" saves nothing. Leaving
 * without saving changes nothing. Step 6 saves the hardening tab, which
 * is the confirmation of the hardening module (HM-0, E-260).
 */
final class SetupWizard {

	public const SLUG     = 'course-captain-setup';
	public const ACTION   = 'ccap_setup_step';
	public const SKIP     = 'ccap_setup_skip';
	public const COMPLETE = 'ccap_setup_complete';
	public const DISMISS  = 'ccap_setup_dismiss';
	public const FORM_KEY = 'ccap_setup_form_';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				// Hidden page (no menu entry, R0-30 §1.3).
				$hook = add_submenu_page( '', __( 'Setup', 'course-captain' ), __( 'Setup', 'course-captain' ), Settings::CAP, self::SLUG, array( self::class, 'render' ) );
				if ( is_string( $hook ) ) {
					add_action( 'admin_print_scripts-' . $hook, array( SettingsScreen::class, 'enqueue' ) );
				}
			},
			19
		);
		add_action( 'admin_notices', array( self::class, 'notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_step' ) );
		add_action( 'admin_post_' . self::SKIP, array( self::class, 'handle_skip' ) );
		add_action( 'admin_post_' . self::COMPLETE, array( self::class, 'handle_complete' ) );
		add_action( 'admin_post_' . self::DISMISS, array( self::class, 'handle_dismiss' ) );
	}

	/**
	 * Step titles.
	 *
	 * @param string $step Step.
	 * @return string
	 */
	public static function title( string $step ): string {
		$titles = array(
			'purpose_language' => __( 'Purpose & language', 'course-captain' ),
			'addresses'        => __( 'Addresses & pages', 'course-captain' ),
			'enrollment'       => __( 'Enrollment & groups', 'course-captain' ),
			'learning_exams'   => __( 'Learning & exams', 'course-captain' ),
			'mail_privacy'     => __( 'Email & privacy', 'course-captain' ),
			'hardening'        => __( 'Website hardening', 'course-captain' ),
			'first_course'     => __( 'First course', 'course-captain' ),
			'readiness'        => __( 'Readiness check', 'course-captain' ),
		);
		return $titles[ $step ] ?? $step;
	}

	/**
	 * Short description of a step.
	 *
	 * @param string $step Step.
	 * @return string
	 */
	private static function intro( string $step ): string {
		$intro = array(
			'purpose_language' => __( 'What do you use Course Captain for? Your answer fills the next steps with matching suggestions. You can change every suggestion.', 'course-captain' ),
			'addresses'        => __( 'Choose the address under which learners find their courses.', 'course-captain' ),
			'enrollment'       => __( 'How do learners get into a course? Access always comes from an enrollment; without one, guests see at most the free part of a course.', 'course-captain' ),
			'learning_exams'   => __( 'These values are suggestions for new courses and exams. You can set every exam individually later. Running attempts never change.', 'course-captain' ),
			'mail_privacy'     => __( 'Course Captain sends mails via WordPress. Choose the sender and how external videos are loaded.', 'course-captain' ),
			'hardening'        => __( 'Course Captain can protect your whole website against frequent attacks. If a security plugin already does this, Course Captain holds back for that function.', 'course-captain' ),
			'first_course'     => __( 'Create an empty course skeleton so you can start right away. You write the content yourself or import it.', 'course-captain' ),
			'readiness'        => __( 'Course Captain checks whether everything is ready. Fix blockers before your first course.', 'course-captain' ),
		);
		return $intro[ $step ] ?? '';
	}

	/**
	 * Register fields per tab of a step.
	 *
	 * @param string $step Step.
	 * @return array<string, list<string>>
	 */
	public static function step_fields( string $step ): array {
		switch ( $step ) {
			case 'purpose_language':
				return array( 'general' => array( 'use_profile_locale' ) );
			case 'addresses':
				return array( 'general' => array( 'learning_area_slug', 'course_slug', 'catalog_page_id', 'learner_home_page_id' ) );
			case 'enrollment':
				return array( 'enrollment' => array( 'self_enrollment', 'default_access_days', 'groups_enabled', 'invite_valid_days' ) );
			case 'learning_exams':
				return array(
					'learning' => array( 'feedback.visibility' ),
					'exams'    => array( 'max_attempts', 'passing_ratio_proposal', 'time_limit_minutes', 'timer_mode', 'feedback.show_correct_answers_when' ),
				);
			case 'mail_privacy':
				return array(
					'emails'  => array( 'from_name', 'from_address' ),
					'privacy' => array( 'external_media_mode', 'auto_export_on_confirm' ),
				);
			case 'hardening':
				$keys = array( 'login_limit', 'known_device', 'reset_limit' );
				if ( get_option( 'users_can_register' ) ) {
					$keys[] = 'registration_limit';
				}
				return array( 'hardening' => array_merge( $keys, array( 'xmlrpc', 'site_headers' ) ) );
		}
		return array();
	}

	/**
	 * Admin notice (§2.1): only for #19, only on dashboard, plugins and own pages.
	 *
	 * @return void
	 */
	public static function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = null === $screen ? '' : (string) $screen->id;
		if ( ! in_array( $id, array( 'dashboard', 'plugins' ), true ) && ! str_contains( $id, 'course-captain' ) ) {
			return;
		}
		echo self::notice_html( get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while building.
	}

	/**
	 * Notice HTML for a user ('' when not shown).
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	public static function notice_html( int $user_id ): string {
		$state = SetupState::get();
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) || in_array( $state['status'], array( 'completed', 'completed_with_issues' ), true ) || in_array( $user_id, $state['dismissed_by'], true ) ) {
			return '';
		}
		$url = add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );
		if ( 'in_progress' === $state['status'] ) {
			/* translators: %d: step number */
			$text = sprintf( __( 'The setup stopped at step %d of 8.', 'course-captain' ), SetupState::number( $state['current_step'] ) );
			$link = __( 'Continue setup', 'course-captain' );
		} else {
			$text = __( 'Course Captain is ready. Set up the learning area in about 10 minutes.', 'course-captain' );
			$link = __( 'Start setup', 'course-captain' );
		}
		return '<div class="notice notice-info"><p>' . esc_html( $text ) . ' <a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html( $link ) . '</a></p>'
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::DISMISS ) . '">' . wp_nonce_field( self::DISMISS, '_wpnonce', true, false )
			. '<button type="submit" class="button-link">' . esc_html__( 'Hide notice', 'course-captain' ) . '</button></form></div>';
	}

	/**
	 * Page output.
	 *
	 * @return void
	 */
	public static function render(): void {
		try {
			echo self::html( $_GET, get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.NonceVerification.Recommended -- Escaped while building; read-only view parameters.
		} catch ( CourseException $e ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'course-captain' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Admin-post entry points.
	 *
	 * @return void
	 */
	public static function handle_step(): void {
		self::redirect( self::process_step( wp_unslash( $_POST ), get_current_user_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process_step().
	}

	/**
	 * Admin-post: skip.
	 *
	 * @return void
	 */
	public static function handle_skip(): void {
		self::redirect( self::process_skip( wp_unslash( $_POST ), get_current_user_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process_skip().
	}

	/**
	 * Admin-post: complete.
	 *
	 * @return void
	 */
	public static function handle_complete(): void {
		self::redirect( self::process_complete( wp_unslash( $_POST ), get_current_user_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process_complete().
	}

	/**
	 * Admin-post: hide the notice for this user.
	 *
	 * @return void
	 */
	public static function handle_dismiss(): void {
		check_admin_referer( self::DISMISS );
		self::dismiss( get_current_user_id() );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Hide the notice for a user (`dismissed_by`).
	 *
	 * @param int $user_id User.
	 * @return void
	 */
	public static function dismiss( int $user_id ): void {
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) ) {
			return;
		}
		$state = SetupState::get();
		if ( ! in_array( $user_id, $state['dismissed_by'], true ) ) {
			$state['dismissed_by'][] = $user_id;
			SetupState::write( $state, $user_id, $state['_rev'] );
		}
	}

	/**
	 * Testable core of "Save and continue".
	 *
	 * @param array<string, mixed> $post    Unslashed POST.
	 * @param int                  $user_id User.
	 * @return array{step: string, result: string}
	 */
	public static function process_step( array $post, int $user_id ): array {
		$step = isset( $post['step'] ) ? sanitize_key( (string) $post['step'] ) : '';
		$out  = array(
			'step'   => $step,
			'result' => 'saved',
		);
		if ( ! in_array( $step, SetupState::STEPS, true ) || 'readiness' === $step ) {
			$out['result'] = 'ccap_not_found';
			return $out;
		}
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) ) {
			$out['result'] = 'ccap_forbidden';
			return $out;
		}
		$actor     = Actor::user( $user_id );
		$scope     = 'setup:' . $step;
		$confirmed = array();
		if ( ! empty( $post['_ccap_confirm'] ) ) {
			if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), 'ccap_setup_confirm_' . $step ) ) {
				$out['result'] = 'ccap_invalid_nonce';
				return $out;
			}
			$pending = RiskConfirm::pending( $user_id );
			RiskConfirm::drop( $user_id );
			$risks = is_array( $pending ) && ( $pending['scope'] ?? '' ) === $scope ? RiskConfirm::consume( $user_id, $scope, (string) ( $post['_ccap_token'] ?? '' ), (array) $pending['inputs'] ) : null;
			if ( null === $risks || ! is_array( $pending ) ) {
				$out['result'] = 'ccap_confirmation_expired';
				return $out;
			}
			$inputs    = (array) $pending['inputs'];
			$fields    = (array) $pending['fields'];
			$revs      = (array) $pending['revs'];
			$extra     = (array) $pending['extra'];
			$confirmed = $risks;
		} else {
			if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), 'ccap_setup_step_' . $step ) ) {
				$out['result'] = 'ccap_invalid_nonce';
				return $out;
			}
			$inputs = array();
			$fields = array();
			$revs   = array();
			$errors = array();
			foreach ( self::step_fields( $step ) as $tab => $keys ) {
				$sub     = is_array( $post['t'][ $tab ] ?? null ) ? $post['t'][ $tab ] : array();
				$decoded = SettingsForm::decode( $tab, $keys, $sub, Settings::values( $tab ) );
				if ( 'hardening' === $tab ) {
					$decoded['input'] += array_intersect_key( Settings::values( 'hardening' ), array_flip( Hardening::FEATURES ) );
					$decoded['fields'] = array_keys( $decoded['input'] );
				}
				$inputs[ $tab ] = $decoded['input'];
				$fields[ $tab ] = $decoded['fields'];
				$revs[ $tab ]   = (int) ( $post['_rev'][ $tab ] ?? -1 );
				if ( array() !== $decoded['errors'] ) {
					$errors[ $tab ] = $decoded['errors'];
				}
			}
			$extra = self::step_extra( $step, $post, $errors );
			if ( array() !== $errors ) {
				self::keep_form( $user_id, $step, $inputs, $errors, $extra );
				$out['result'] = 'invalid';
				return $out;
			}
		}
		$before = array();
		$after  = array();
		$risks  = array();
		$errors = array();
		foreach ( $inputs as $tab => $input ) {
			try {
				$check = Settings::check( $actor, (string) $tab, (array) $input, array_values( (array) $fields[ $tab ] ) );
			} catch ( CourseException $e ) {
				$out['result'] = $e->error_code;
				return $out;
			}
			if ( array() !== $check['errors'] ) {
				$errors[ $tab ] = SettingsForm::with_params( $check['errors'], (array) $input );
			}
			if ( Settings::rev( (string) $tab ) !== (int) $revs[ $tab ] ) {
				self::keep_form( $user_id, $step, $inputs, array(), $extra );
				$out['result'] = 'conflict';
				return $out;
			}
			$before = array_merge( $before, $check['before'] );
			$after  = array_merge( $after, $check['candidate'] );
			$risks  = array_merge( $risks, RiskRules::detect( (string) $tab, $check['before'], $check['candidate'], true ) );
		}
		if ( array() !== $errors ) {
			self::keep_form( $user_id, $step, $inputs, $errors, $extra );
			$out['result'] = 'invalid';
			return $out;
		}
		$risks = array_values( array_unique( $risks ) );
		if ( array() !== array_diff( $risks, $confirmed ) ) {
			$token = RiskConfirm::issue( $user_id, $scope, $inputs, $risks );
			RiskConfirm::park(
				$user_id,
				array(
					'scope'  => $scope,
					'inputs' => $inputs,
					'fields' => $fields,
					'revs'   => $revs,
					'extra'  => $extra,
					'risks'  => $risks,
					'token'  => $token,
					'before' => $before,
					'after'  => $after,
				)
			);
			$out['result'] = 'confirm';
			return $out;
		}
		$state = SetupState::get();
		if ( 'addresses' === $step && ! empty( $extra['create_catalog'] ) ) {
			$page = self::catalog_page( $user_id, $state );
			if ( $page > 0 ) {
				$inputs['general']['catalog_page_id'] = $page;
				$extra['created']                     = array_merge( (array) $state['created'], array( 'catalog_page_id' => $page ) );
			}
		}
		if ( 'first_course' === $step && 'create' === ( $extra['choice'] ?? '' ) ) {
			$course = self::first_course( $actor, (string) $extra['course_title'], $state );
			if ( $course > 0 ) {
				$extra['created'] = array_merge( (array) $state['created'], array( 'first_course_id' => $course ) );
			}
		}
		foreach ( $inputs as $tab => $input ) {
			try {
				Settings::save(
					$actor,
					(string) $tab,
					(array) $input,
					array_values( (array) $fields[ $tab ] ),
					(int) $revs[ $tab ],
					array(
						'context'         => 'setup',
						'step'            => $step,
						'confirmed_risks' => $confirmed,
					)
				);
			} catch ( CourseException $e ) {
				$out['result'] = 'ccap_settings_conflict' === $e->error_code ? 'conflict' : $e->error_code;
				return $out;
			}
		}
		$state_extra = array_intersect_key( $extra, array_flip( array( 'purpose', 'created', 'first_course_choice' ) ) );
		if ( ! SetupState::mark( $step, 'saved', $user_id, $state_extra ) ) {
			$out['result'] = 'conflict';
		}
		return $out;
	}

	/**
	 * Step values outside the register (purpose, catalog page, first course).
	 *
	 * @param string                              $step   Step.
	 * @param array<string, mixed>                $post   POST.
	 * @param array<string, array<string, mixed>> $errors Errors (out).
	 * @return array<string, mixed>
	 */
	private static function step_extra( string $step, array $post, array &$errors ): array {
		$extra = array();
		if ( 'purpose_language' === $step ) {
			$purpose          = isset( $post['purpose'] ) ? sanitize_key( (string) $post['purpose'] ) : '';
			$extra['purpose'] = in_array( $purpose, SetupState::PURPOSES, true ) ? $purpose : null;
		}
		if ( 'addresses' === $step ) {
			$extra['create_catalog'] = ! empty( $post['create_catalog'] );
		}
		if ( 'first_course' === $step ) {
			$choice                       = isset( $post['choice'] ) ? sanitize_key( (string) $post['choice'] ) : 'create';
			$extra['choice']              = in_array( $choice, array( 'create', 'import', 'skip' ), true ) ? $choice : 'create';
			$extra['first_course_choice'] = $extra['choice'];
			$extra['course_title']        = isset( $post['course_title'] ) ? sanitize_text_field( (string) $post['course_title'] ) : '';
			$exists                       = (int) SetupState::get()['created']['first_course_id'];
			if ( 'create' === $extra['choice'] && ( $exists <= 0 || null === get_post( $exists ) ) ) {
				if ( '' === $extra['course_title'] ) {
					$errors['first_course']['course_title'] = array(
						'rule'   => 'title_required',
						'params' => array(),
					);
				} elseif ( mb_strlen( $extra['course_title'] ) > 200 ) {
					$errors['first_course']['course_title'] = array(
						'rule'   => 'title_length',
						'params' => array(),
					);
				}
			}
		}
		return $extra;
	}

	/**
	 * Create the catalogue page once (ADM-029: never twice).
	 *
	 * @param int                  $user_id User.
	 * @param array<string, mixed> $state   State.
	 * @return int Page ID.
	 */
	private static function catalog_page( int $user_id, array $state ): int {
		$existing = (int) $state['created']['catalog_page_id'];
		if ( $existing > 0 && null !== get_post( $existing ) && 'trash' !== get_post_status( $existing ) ) {
			return $existing;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Courses', 'course-captain' ),
				'post_content' => '<!-- wp:course-captain/course-catalog /-->',
				'post_author'  => $user_id,
			),
			true
		);
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Create the empty course draft once (ADM-034): course, one module, one lesson; nothing published.
	 *
	 * @param Actor                $actor Actor.
	 * @param string               $title Title.
	 * @param array<string, mixed> $state State.
	 * @return int Course ID.
	 */
	private static function first_course( Actor $actor, string $title, array $state ): int {
		$existing = (int) $state['created']['first_course_id'];
		if ( $existing > 0 && null !== get_post( $existing ) ) {
			return $existing;
		}
		$course = wp_insert_post(
			array(
				'post_type'   => Relations::COURSE_TYPE,
				'post_status' => 'draft',
				'post_title'  => $title,
				'post_author' => $actor->user_id,
			),
			true
		);
		if ( is_wp_error( $course ) ) {
			return 0;
		}
		$module = wp_insert_post(
			array(
				'post_type'   => Relations::MODULE_TYPE,
				'post_status' => 'draft',
				'post_title'  => __( 'Module 1', 'course-captain' ),
				'post_author' => $actor->user_id,
			),
			true
		);
		if ( ! is_wp_error( $module ) ) {
			try {
				CourseStructure::current()->assign_module( $actor, (int) $course, (int) $module );
			} catch ( \Throwable $e ) {
				unset( $e );
			}
			wp_insert_post(
				array(
					'post_type'   => Relations::LESSON_TYPE,
					'post_status' => 'draft',
					'post_title'  => __( 'Lesson 1', 'course-captain' ),
					'post_parent' => (int) $module,
					'post_author' => $actor->user_id,
				)
			);
		}
		return (int) $course;
	}

	/**
	 * Testable core of "Skip": saves nothing, marks the step `skipped`.
	 *
	 * @param array<string, mixed> $post    POST.
	 * @param int                  $user_id User.
	 * @return array{step: string, result: string}
	 */
	public static function process_skip( array $post, int $user_id ): array {
		$step = isset( $post['step'] ) ? sanitize_key( (string) $post['step'] ) : '';
		if ( ! in_array( $step, SetupState::STEPS, true ) || 'readiness' === $step ) {
			return array(
				'step'   => $step,
				'result' => 'ccap_not_found',
			);
		}
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) ) {
			return array(
				'step'   => $step,
				'result' => 'ccap_forbidden',
			);
		}
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), 'ccap_setup_skip_' . $step ) ) {
			return array(
				'step'   => $step,
				'result' => 'ccap_invalid_nonce',
			);
		}
		$state = SetupState::get();
		if ( 'saved' !== $state['steps'][ $step ]['status'] ) {
			SetupState::mark( $step, 'skipped', $user_id );
		} else {
			$state['current_step'] = SetupState::next_after( $step, $state );
			SetupState::write( $state, $user_id, $state['_rev'] );
		}
		return array(
			'step'   => $step,
			'result' => 'skipped',
		);
	}

	/**
	 * Testable core of "Finish setup" (E-266: always possible).
	 *
	 * @param array<string, mixed> $post    POST.
	 * @param int                  $user_id User.
	 * @return array{step: string, result: string}
	 */
	public static function process_complete( array $post, int $user_id ): array {
		$out = array(
			'step'   => 'readiness',
			'result' => 'completed',
		);
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) ) {
			$out['result'] = 'ccap_forbidden';
			return $out;
		}
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::COMPLETE ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		$blockers                    = Readiness::blockers( Readiness::run() );
		$state                       = SetupState::get();
		$state['status']             = 0 === $blockers ? 'completed' : 'completed_with_issues';
		$state['open_blockers']      = $blockers;
		$state['steps']['readiness'] = array(
			'status' => 'saved',
			'at'     => gmdate( 'c' ),
			'by'     => $user_id,
		);
		$state['current_step']       = 'readiness';
		SetupState::write( $state, $user_id, $state['_rev'] );
		$out['result'] = 0 === $blockers ? 'completed' : 'completed_with_issues';
		return $out;
	}

	/**
	 * Page HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id User.
	 * @return string
	 * @throws CourseException Without #19 (403, ADM-021).
	 */
	public static function html( array $request, int $user_id ): string {
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to run the setup.', 403 );
		}
		$state  = SetupState::get();
		$step   = isset( $request['step'] ) ? sanitize_key( (string) $request['step'] ) : '';
		$step   = in_array( $step, SetupState::STEPS, true ) ? $step : $state['current_step'];
		$result = isset( $request['ccap_result'] ) ? sanitize_key( (string) $request['ccap_result'] ) : '';
		$number = SetupState::number( $step );
		/* translators: 1: step number, 2: step title */
		$heading = sprintf( __( 'Setup · Step %1$d of 8: %2$s', 'course-captain' ), $number, self::title( $step ) );
		$html    = '<div class="wrap ccap-setup"><h1 tabindex="-1"' . ( '' === $result ? ' data-ccap-focus="1"' : '' ) . '>' . esc_html( $heading ) . '</h1>';
		$html   .= self::step_list( $state, $step );
		if ( 'confirm' === $result ) {
			return $html . self::confirm_page( $step, $user_id ) . '</div>';
		}
		$form  = self::take_form( $user_id, $step );
		$html .= self::message( $step, $result, $state );
		$html .= '<p>' . esc_html( self::intro( $step ) ) . '</p>';
		if ( 'readiness' === $step ) {
			$html .= self::readiness_html();
		} else {
			$html .= self::step_form( $step, $state, $form );
		}
		$html .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . SettingsScreen::SLUG ) ) . '" data-ccap-leave="' . esc_attr( SettingsScreen::leave_text() ) . '">' . esc_html__( 'Leave setup – your progress is kept', 'course-captain' ) . '</a></p>';
		return $html . '<div class="screen-reader-text" role="status" aria-live="polite" id="ccap-status">' . ( 'saved' === $result ? esc_html( self::saved_text( $state ) ) : esc_html( TestMail::message( isset( $request['ccap_test_mail'] ) ? sanitize_key( (string) $request['ccap_test_mail'] ) : '' ) ) ) . '</div></div>';
	}

	/**
	 * Status text after saving (W-8).
	 *
	 * @param array<string, mixed> $state State.
	 * @return string
	 */
	private static function saved_text( array $state ): string {
		$last = '';
		$time = '';
		foreach ( $state['steps'] as $key => $entry ) {
			if ( 'saved' === ( $entry['status'] ?? '' ) && (string) ( $entry['at'] ?? '' ) >= $time ) {
				$time = (string) ( $entry['at'] ?? '' );
				$last = (string) $key;
			}
		}
		/* translators: %d: step number */
		return sprintf( __( 'Step %d saved.', 'course-captain' ), SetupState::number( '' === $last ? 'purpose_language' : $last ) );
	}

	/**
	 * Step list (W-2): ordered list, `aria-current="step"`, status as text.
	 *
	 * @param array<string, mixed> $state   State.
	 * @param string               $current Current step.
	 * @return string
	 */
	private static function step_list( array $state, string $current ): string {
		$html = '<nav aria-label="' . esc_attr__( 'Setup steps', 'course-captain' ) . '"><ol class="ccap-steps">';
		foreach ( SetupState::STEPS as $step ) {
			$status = (string) ( $state['steps'][ $step ]['status'] ?? 'pending' );
			if ( $step === $current ) {
				$text = __( 'current step', 'course-captain' );
			} elseif ( 'saved' === $status ) {
				$text = __( 'saved', 'course-captain' );
			} elseif ( 'skipped' === $status ) {
				$text = __( 'skipped', 'course-captain' );
			} else {
				$text = __( 'open', 'course-captain' );
			}
			$symbol = 'saved' === $status ? '✓' : ( 'skipped' === $status ? '↷' : '○' );
			$url    = add_query_arg(
				array(
					'page' => self::SLUG,
					'step' => $step,
				),
				admin_url( 'admin.php' )
			);
			$html  .= '<li><a href="' . esc_url( $url ) . '"' . ( $step === $current ? ' aria-current="step"' : '' ) . ' data-ccap-leave="' . esc_attr( SettingsScreen::leave_text() ) . '"><span aria-hidden="true">' . esc_html( $symbol ) . '</span> ' . esc_html( self::title( $step ) . ' – ' . $text ) . '</a></li>';
		}
		$html .= '</ol>';
		$who   = get_userdata( (int) ( $state['updated_by'] ?? 0 ) );
		if ( $who instanceof \WP_User && ! empty( $state['updated_at'] ) ) {
			/* translators: 1: display name, 2: date and time */
			$html .= '<p class="description">' . esc_html( sprintf( __( 'Last edited by %1$s, %2$s', 'course-captain' ), $who->display_name, (string) wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), (int) strtotime( (string) $state['updated_at'] ) ) ) ) . '</p>';
		}
		return $html . '</nav>';
	}

	/**
	 * Result message.
	 *
	 * @param string               $step   Step.
	 * @param string               $result Result.
	 * @param array<string, mixed> $state  State.
	 * @return string
	 */
	private static function message( string $step, string $result, array $state ): string {
		$type = 'success';
		switch ( $result ) {
			case 'completed':
				$text = __( 'Setup completed.', 'course-captain' );
				break;
			case 'completed_with_issues':
				$open = (int) ( $state['open_blockers'] ?? 0 );
				/* translators: %d: number of open points */
				$text = sprintf( _n( 'Setup completed. %d point is still open.', 'Setup completed. %d points are still open.', $open, 'course-captain' ), $open );
				$type = 'warning';
				break;
			case 'conflict':
				$text = __( 'These settings were changed in the meantime by someone else. Your input is still here. Check the values and save again.', 'course-captain' );
				$type = 'warning';
				break;
			case 'ccap_invalid_nonce':
				$text = __( 'The form has expired. Reload the page and try again.', 'course-captain' );
				$type = 'error';
				break;
			case 'ccap_confirmation_expired':
				$text = __( 'This change needs a confirmation. Save again and confirm the dialog.', 'course-captain' );
				$type = 'error';
				break;
			case 'ccap_forbidden':
				$text = __( 'You are not allowed to change these settings.', 'course-captain' );
				$type = 'error';
				break;
			default:
				return '';
		}
		unset( $step );
		return '<div class="notice notice-' . esc_attr( $type ) . '" tabindex="-1" data-ccap-focus="1"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Step form (W-3: implicit submission = "Save and continue").
	 *
	 * @param string                    $step  Step.
	 * @param array<string, mixed>      $state State.
	 * @param array<string, mixed>|null $form  Kept form.
	 * @return string
	 */
	private static function step_form( string $step, array $state, ?array $form ): string {
		$errors     = is_array( $form ) ? (array) $form['errors'] : array();
		$all_errors = array();
		foreach ( $errors as $tab => $tab_errors ) {
			foreach ( (array) $tab_errors as $field => $error ) {
				$all_errors[ $tab . '|' . $field ] = array(
					'tab'   => (string) $tab,
					'field' => (string) $field,
				);
			}
		}
		$html = '';
		if ( array() !== $all_errors ) {
			$items = '';
			foreach ( $all_errors as $entry ) {
				$key    = 'first_course' === $entry['tab'] ? 'course_title' : SettingsForm::first_key( $entry['field'], self::step_fields( $step )[ $entry['tab'] ] ?? array() );
				$label  = 'first_course' === $entry['tab'] ? __( 'Course title', 'course-captain' ) : SettingsFields::ui( $entry['tab'], $key )['label'];
				$items .= '<li><a href="#' . esc_attr( SettingsForm::id( $entry['tab'], $key ) ) . '">' . esc_html( $label ) . '</a></li>';
			}
			/* translators: %d: number of fields with errors */
			$html .= '<div class="notice notice-error" role="alert" tabindex="-1" data-ccap-focus="1"><p><strong>' . esc_html( sprintf( _n( '%d entry must be corrected:', '%d entries must be corrected:', count( $all_errors ), 'course-captain' ), count( $all_errors ) ) ) . '</strong></p><ul>' . $items . '</ul></div>';
		}
		$html   .= '<form id="cc-step" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-ccap-dirty-form="1" novalidate>';
		$html   .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="step" value="' . esc_attr( $step ) . '">' . wp_nonce_field( 'ccap_setup_step_' . $step, '_wpnonce', true, false );
		$html   .= self::step_extra_html( $step, $state, $form );
		$suggest = self::suggestions( $step, $state );
		foreach ( self::step_fields( $step ) as $tab => $keys ) {
			$values = Settings::values( $tab );
			$input  = is_array( $form ) ? (array) ( $form['inputs'][ $tab ] ?? array() ) : array();
			$html  .= '<input type="hidden" name="_rev[' . esc_attr( $tab ) . ']" value="' . esc_attr( (string) Settings::rev( $tab ) ) . '">';
			$status = 'hardening' === $tab ? SettingsScreen::hardening_status() : array();
			$warn   = SettingsScreen::warnings( $tab, $values );
			foreach ( $keys as $key ) {
				list( $f ) = explode( '.', $key, 2 );
				$value     = array_key_exists( $f, $input ) ? SettingsForm::value_of( $input, $key ) : ( array_key_exists( $tab . '.' . $key, $suggest ) ? $suggest[ $tab . '.' . $key ] : SettingsForm::value_of( $values, $key ) );
				$opts      = array( 'root' => 't[' . $tab . ']' );
				if ( array_key_exists( $tab . '.' . $key, $suggest ) && ! array_key_exists( $f, $input ) ) {
					$opts['extra_help'] = __( 'Suggestion for your purpose.', 'course-captain' );
				}
				if ( isset( $status[ $f ] ) ) {
					$opts['status'] = $status[ $f ];
				}
				if ( isset( $warn[ $f ] ) ) {
					$opts['warning'] = $warn[ $f ];
				}
				$html .= '<input type="hidden" name="t[' . esc_attr( $tab ) . '][_fields][]" value="' . esc_attr( $key ) . '">' . SettingsForm::field( $tab, $key, $value, $errors[ $tab ][ $f ] ?? null, $opts );
			}
		}
		if ( 'hardening' === $step ) {
			$html .= '<p>' . esc_html__( 'If you skip this step, hardening stays off.', 'course-captain' ) . ' ' . esc_html__( 'If you lock yourself out: add define( \'COURSE_CAPTAIN_HARDENING\', \'off\' ); to wp-config.php or run wp course-captain hardening disable.', 'course-captain' ) . '</p>';
		}
		$html .= '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save and continue', 'course-captain' ) . '</button></p></form>';
		$prev  = SetupState::number( $step ) - 2;
		if ( $prev >= 0 ) {
			$html .= '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => self::SLUG, 'step' => SetupState::STEPS[ $prev ] ), admin_url( 'admin.php' ) ) ) . '" data-ccap-leave="' . esc_attr( SettingsScreen::leave_text() ) . '">' . esc_html__( 'Back', 'course-captain' ) . '</a> '; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
		}
		if ( 'mail_privacy' === $step ) {
			$html .= ' ' . TestMail::form( get_current_user_id(), 'setup' );
		}
		$html .= '<form id="cc-skip" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-inline-form"><input type="hidden" name="action" value="' . esc_attr( self::SKIP ) . '"><input type="hidden" name="step" value="' . esc_attr( $step ) . '">' . wp_nonce_field( 'ccap_setup_skip_' . $step, '_wpnonce', true, false ) . '<button type="submit" class="button">' . esc_html__( 'Skip', 'course-captain' ) . '</button></form>';
		return $html;
	}

	/**
	 * Fields outside the register.
	 *
	 * @param string                    $step  Step.
	 * @param array<string, mixed>      $state State.
	 * @param array<string, mixed>|null $form  Kept form.
	 * @return string
	 */
	private static function step_extra_html( string $step, array $state, ?array $form ): string {
		$extra = is_array( $form ) ? (array) ( $form['extra'] ?? array() ) : array();
		switch ( $step ) {
			case 'purpose_language':
				$labels  = array(
					'internal_training' => __( 'Train employees or members', 'course-captain' ),
					'customer_courses'  => __( 'Give courses to customers', 'course-captain' ),
					'exam_prep'         => __( 'Prepare for an exam', 'course-captain' ),
					'other'             => __( 'Other', 'course-captain' ),
				);
				$current = $extra['purpose'] ?? $state['purpose'];
				$html    = '<fieldset class="ccap-field" id="ccap-field-setup-purpose"><legend>' . esc_html__( 'Purpose', 'course-captain' ) . '</legend>';
				foreach ( $labels as $value => $label ) {
					$html .= '<label class="ccap-choice"><input type="radio" name="purpose" value="' . esc_attr( $value ) . '"' . checked( $current, $value, false ) . ' aria-describedby="ccap-purpose-help"> ' . esc_html( $label ) . '</label>';
				}
				$html .= '<p id="ccap-purpose-help" class="description">' . esc_html__( 'Only used for suggestions in the assistant and stored nowhere else.', 'course-captain' ) . '</p></fieldset>';
				$tz    = (string) get_option( 'timezone_string' );
				$html .= '<dl class="ccap-wp-values"><dt>' . esc_html__( 'Website language', 'course-captain' ) . '</dt><dd>' . esc_html( get_locale() ) . '</dd><dt>' . esc_html__( 'Time zone', 'course-captain' ) . '</dt><dd>' . esc_html( '' === $tz ? wp_timezone_string() : $tz ) . '</dd></dl>';
				$html .= '<p class="description">' . esc_html__( 'Course Captain takes these values from WordPress.', 'course-captain' ) . '</p>';
				if ( '' === $tz ) {
					$html .= '<p class="ccap-field-warning">' . esc_html__( 'Warning: the time zone is set as a fixed offset. Deadlines then shift with daylight saving time. Choose a city in WordPress, for example Berlin.', 'course-captain' ) . '</p>';
				}
				return $html;
			case 'addresses':
				$page = (int) $state['created']['catalog_page_id'];
				if ( $page > 0 && null !== get_post( $page ) && 'trash' !== get_post_status( $page ) ) {
					/* translators: %s: page title */
					return '<p>' . esc_html( sprintf( __( 'Page “%s” was already created.', 'course-captain' ), get_the_title( $page ) ) ) . ' <a href="' . esc_url( (string) get_permalink( $page ) ) . '">' . esc_html__( 'View', 'course-captain' ) . '</a></p>';
				}
				return '<div class="ccap-field"><label><input type="checkbox" name="create_catalog" value="1" aria-describedby="ccap-create-catalog-help"' . checked( ! empty( $extra['create_catalog'] ), true, false ) . '> ' . esc_html__( 'Create a new page “Courses”', 'course-captain' ) . '</label><p id="ccap-create-catalog-help" class="description">' . esc_html__( 'Creates a published page with the “Course catalogue” block. Existing pages are never overwritten.', 'course-captain' ) . '</p></div>';
			case 'mail_privacy':
				$policy = (int) get_option( 'wp_page_for_privacy_policy' );
				$text   = $policy > 0 && 'publish' === get_post_status( $policy )
					/* translators: %s: page title */
					? sprintf( __( 'Privacy policy: “%s”.', 'course-captain' ), get_the_title( $policy ) )
					: __( 'Warning: no privacy policy is set.', 'course-captain' );
				return '<p>' . esc_html( $text ) . ' ' . esc_html__( 'Text modules for Course Captain are in the WordPress guide for the privacy policy.', 'course-captain' ) . '</p>'
					. '<p>' . esc_html__( 'Retention periods are changeable suggestions; have them checked legally before you collect learning data. All periods: Settings › Privacy & security.', 'course-captain' ) . '</p>';
			case 'hardening':
				$conflicts = array_unique( array_values( Hardening::conflicts() ) );
				return '<h2>' . esc_html__( 'Detected security plugins', 'course-captain' ) . '</h2>' . ( array() === $conflicts ? '<p>' . esc_html__( 'No security plugin detected.', 'course-captain' ) . '</p>' : '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $conflicts ) ) . '</li></ul>' ) . '<h2>' . esc_html__( 'Protective functions', 'course-captain' ) . '</h2>';
			case 'first_course':
				$course = (int) $state['created']['first_course_id'];
				if ( $course > 0 && null !== get_post( $course ) ) {
					/* translators: %s: course title */
					return '<p>' . esc_html( sprintf( __( 'Course draft “%s” was already created.', 'course-captain' ), get_the_title( $course ) ) ) . ' <a href="' . esc_url( (string) get_edit_post_link( $course, 'url' ) ) . '">' . esc_html__( 'Open', 'course-captain' ) . '</a></p><input type="hidden" name="choice" value="skip">';
				}
				$choice = (string) ( $extra['choice'] ?? 'create' );
				$err    = is_array( $form ) ? ( $form['errors']['first_course']['course_title'] ?? null ) : null;
				$id     = SettingsForm::id( 'first_course', 'course_title' );
				$html   = '<fieldset class="ccap-field"><legend>' . esc_html__( 'How do you want to start?', 'course-captain' ) . '</legend>';
				foreach (
					array(
						'create' => __( 'Create an empty course skeleton (one module, one lesson; nothing is published)', 'course-captain' ),
						'import' => __( 'Import content after the setup', 'course-captain' ),
						'skip'   => __( 'Skip – I create courses later', 'course-captain' ),
					) as $value => $label
				) {
					$html .= '<label class="ccap-choice"><input type="radio" name="choice" value="' . esc_attr( $value ) . '"' . checked( $choice, $value, false ) . '> ' . esc_html( $label ) . '</label>';
				}
				$html .= '</fieldset><div class="ccap-field"><label for="' . esc_attr( $id ) . '">' . esc_html__( 'Course title (required for the skeleton)', 'course-captain' ) . '</label> <input type="text" id="' . esc_attr( $id ) . '" name="course_title" maxlength="250" class="regular-text" value="' . esc_attr( (string) ( $extra['course_title'] ?? '' ) ) . '"' . ( is_array( $err ) ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-error"' : '' ) . '>';
				if ( is_array( $err ) ) {
					$html .= '<p id="' . esc_attr( $id ) . '-error" class="ccap-field-error"><strong>' . esc_html( SettingsFields::message( __( 'Course title', 'course-captain' ), (string) $err['rule'], array() ) ) . '</strong></p>';
				}
				return $html . '</div>';
		}
		return '';
	}

	/**
	 * Suggestions (purpose, hardening) for steps not yet saved; never stored without saving.
	 *
	 * @param string               $step  Step.
	 * @param array<string, mixed> $state State.
	 * @return array<string, mixed> `<tab>.<key>` => value
	 */
	public static function suggestions( string $step, array $state ): array {
		if ( 'pending' !== ( $state['steps'][ $step ]['status'] ?? 'pending' ) ) {
			return array();
		}
		$purpose = $state['purpose'];
		switch ( $step ) {
			case 'enrollment':
				return 'internal_training' === $purpose ? array( 'enrollment.self_enrollment' => true ) : array();
			case 'learning_exams':
				if ( 'internal_training' === $purpose ) {
					return array(
						'learning.feedback.visibility' => 'always',
						'exams.max_attempts'           => null,
					);
				}
				if ( 'exam_prep' === $purpose ) {
					return array(
						'exams.time_limit_minutes' => 60,
						'exams.timer_mode'         => 'strict',
						'exams.feedback.show_correct_answers_when' => 'no_attempts_left_or_passed',
					);
				}
				return array();
			case 'hardening':
				if ( Hardening::confirmed() ) {
					return array();
				}
				$conflicts = Hardening::conflicts();
				$out       = array();
				foreach ( Hardening::FEATURES as $feature ) {
					$out[ 'hardening.' . $feature ] = isset( $conflicts[ $feature ] ) ? 'off' : 'auto';
				}
				return $out;
		}
		return array();
	}

	/**
	 * Readiness check (step 8).
	 *
	 * @return string
	 */
	private static function readiness_html(): string {
		$rows   = Readiness::run();
		$groups = array(
			'blocker' => array(),
			'warning' => array(),
			'info'    => array(),
			'ok'      => array(),
		);
		foreach ( $rows as $row ) {
			$groups[ $row['severity'] ][] = $row;
		}
		$summary = sprintf(
			/* translators: 1: blockers, 2: warnings, 3: OK checks */
			__( 'Check finished: %1$d blockers, %2$d warnings, %3$d OK.', 'course-captain' ),
			count( $groups['blocker'] ),
			count( $groups['warning'] ),
			count( $groups['ok'] )
		);
		$html  = '<p role="status">' . esc_html( $summary ) . '</p>';
		$heads = array(
			'blocker' => __( 'Blockers', 'course-captain' ),
			'warning' => __( 'Warnings', 'course-captain' ),
			'info'    => __( 'Notes', 'course-captain' ),
			'ok'      => __( 'OK', 'course-captain' ),
		);
		foreach ( $groups as $severity => $items ) {
			if ( array() === $items ) {
				continue;
			}
			$list = '<ul>';
			foreach ( $items as $row ) {
				$list .= '<li data-ccap-check="' . esc_attr( $row['id'] ) . '"><strong>' . esc_html( Readiness::word( $severity ) ) . '</strong> ' . esc_html( $row['message'] ) . ( '' === $row['fix'] ? '' : ' ' . esc_html( $row['fix'] ) ) . '</li>';
			}
			$list .= '</ul>';
			/* translators: 1: group heading, 2: count */
			$title = sprintf( __( '%1$s (%2$d)', 'course-captain' ), $heads[ $severity ], count( $items ) );
			$html .= 'ok' === $severity ? '<details><summary>' . esc_html( $title ) . '</summary>' . $list . '</details>' : '<h2>' . esc_html( $title ) . '</h2>' . $list;
		}
		$html .= '<p><a class="button" href="' . esc_url( add_query_arg( array( 'page' => self::SLUG, 'step' => 'readiness' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Check again', 'course-captain' ) . '</a></p>'; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::COMPLETE ) . '">' . wp_nonce_field( self::COMPLETE, '_wpnonce', true, false ) . '<button type="submit" class="button button-primary">' . esc_html__( 'Finish setup', 'course-captain' ) . '</button></form>';
		return $html;
	}

	/**
	 * Risk confirmation page of a step.
	 *
	 * @param string $step    Step.
	 * @param int    $user_id User.
	 * @return string
	 */
	private static function confirm_page( string $step, int $user_id ): string {
		$pending = RiskConfirm::pending( $user_id );
		if ( ! is_array( $pending ) || ( $pending['scope'] ?? '' ) !== 'setup:' . $step ) {
			return '<div class="notice notice-error"><p>' . esc_html__( 'This change needs a confirmation. Save again and confirm the dialog.', 'course-captain' ) . '</p></div>';
		}
		return RiskDialog::page(
			(array) $pending['risks'],
			(array) $pending['before'],
			(array) $pending['after'],
			array(
				'action' => self::ACTION,
				'nonce'  => 'ccap_setup_confirm_' . $step,
				'token'  => (string) $pending['token'],
				'hidden' => array( 'step' => $step ),
				'cancel' => add_query_arg(
					array(
						'page' => self::SLUG,
						'step' => $step,
					),
					admin_url( 'admin.php' )
				),
			)
		);
	}

	/**
	 * Keep the form after an error (10 minutes).
	 *
	 * @param int                  $user_id User.
	 * @param string               $step    Step.
	 * @param array<string, mixed> $inputs  Inputs per tab.
	 * @param array<string, mixed> $errors  Errors per tab.
	 * @param array<string, mixed> $extra   Extra values.
	 * @return void
	 */
	private static function keep_form( int $user_id, string $step, array $inputs, array $errors, array $extra ): void {
		set_transient(
			self::FORM_KEY . $user_id,
			array(
				'step'   => $step,
				'inputs' => $inputs,
				'errors' => $errors,
				'extra'  => $extra,
			),
			SettingsScreen::FORM_TTL
		);
	}

	/**
	 * Take the kept form of a step.
	 *
	 * @param int    $user_id User.
	 * @param string $step    Step.
	 * @return array<string, mixed>|null
	 */
	private static function take_form( int $user_id, string $step ): ?array {
		$form = get_transient( self::FORM_KEY . $user_id );
		if ( ! is_array( $form ) || ( $form['step'] ?? '' ) !== $step ) {
			return null;
		}
		delete_transient( self::FORM_KEY . $user_id );
		return $form;
	}

	/**
	 * Redirect after POST: next step after saving/skipping, else the same step.
	 *
	 * @param array{step: string, result: string} $out Result.
	 * @return void
	 */
	private static function redirect( array $out ): void {
		$step = in_array( $out['result'], array( 'saved', 'skipped' ), true ) ? SetupState::get()['current_step'] : $out['step'];
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::SLUG,
					'step'        => $step,
					'ccap_result' => $out['result'],
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
