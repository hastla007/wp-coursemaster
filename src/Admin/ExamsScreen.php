<?php
/**
 * Admin screen A-08 "Quizzes & exams" (R0-11 §2, R0-30 §1.2 Pos 4, R0-08 §4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Area "Exams": exam rules per course with one editor form for version,
 * pass mark (percent; stored as ratio), part pass marks over module groups
 * (E-331), blueprint (questions per module and points), time limit,
 * attempts, shuffling and feedback (register-backed overrides, R0-28),
 * then "publish". The blueprint shows the deliverable pool per module and
 * "pool too small (n of m)" instead of a silent reduction (A-08 Z-F).
 * Published rules never change (`ccap_rule_frozen`): "new version"
 * copies them into a draft.
 *
 * Fixed exam forms (UI-F8): per rule the author chooses "random draw" or
 * "fixed variants" (draft only). Variants belong to the course
 * (`ccap_exam_variant`): create a draft, set its questions from the
 * course pools by position, freeze it (never changes again) or retire it.
 * Variant forms use the variant ID as nonce object and carry the rule for
 * the way back.
 *
 * Area "Grading" ({@see GradingArea}): open assignment submissions of the
 * grader (Free grading is part of this menu item, R0-30 §1.2).
 *
 * Page cap `ccap_menu_exams` ⇐ #1 ∨ #15 (`ccap_grade_attempts`); every
 * area checks its own cap (M-2).
 */
final class ExamsScreen {

	public const SLUG     = 'course-captain-exams';
	public const ACTION   = 'ccap_exam_admin';
	public const MENU_CAP = 'ccap_menu_exams';
	public const CAP      = 'ccap_edit_courses';
	public const OPS      = array( 'create', 'save', 'publish', 'new_version', 'variant_create', 'variant_save', 'variant_freeze', 'variant_retire' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				add_submenu_page( EditorScreens::MENU, __( 'Quizzes & exams', 'course-captain' ), _x( 'Quizzes & exams', 'admin menu', 'course-captain' ), self::MENU_CAP, self::SLUG, array( self::class, 'render' ), 4 );
			},
			20
		);
		add_filter( 'map_meta_cap', array( self::class, 'map_meta_cap' ), 10, 3 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_post_' . GradingArea::ACTION, array( GradingArea::class, 'handle' ) );
	}

	/**
	 * `ccap_menu_exams` ⇐ `ccap_edit_courses` ∨ `ccap_grade_attempts`.
	 *
	 * @param array<int, string> $caps    Primitive caps.
	 * @param string             $cap     Requested cap.
	 * @param int                $user_id User.
	 * @return array<int, string>
	 */
	public static function map_meta_cap( $caps, $cap, $user_id ) {
		if ( self::MENU_CAP !== $cap ) {
			return $caps;
		}
		return array( ! user_can( (int) $user_id, self::CAP ) && user_can( (int) $user_id, GradingArea::CAP ) ? GradingArea::CAP : self::CAP );
	}

	/**
	 * Areas the user may open (M-2).
	 *
	 * @param int $user_id User.
	 * @return array<string, string>
	 */
	public static function areas( int $user_id ): array {
		$out = array();
		if ( user_can( $user_id, self::CAP ) ) {
			$out['exams'] = __( 'Exams', 'course-captain' );
		}
		if ( \CourseCaptain\Settings\Modules::enabled( \CourseCaptain\Settings\Modules::ASSIGNMENTS ) && ( user_can( $user_id, GradingArea::CAP ) || user_can( $user_id, self::CAP ) ) ) {
			$out['grading'] = __( 'Grading', 'course-captain' );
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
	 * Page HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 */
	public static function html( array $request, int $user_id ): string {
		$areas = self::areas( $user_id );
		if ( array() === $areas ) {
			return '<div class="wrap"><h1>' . esc_html__( 'Quizzes & exams', 'course-captain' ) . '</h1><p>' . esc_html__( 'You are not allowed to open this page.', 'course-captain' ) . '</p></div>';
		}
		$area = sanitize_key( (string) ( $request['area'] ?? '' ) );
		$area = isset( $areas[ $area ] ) ? $area : (string) array_key_first( $areas );
		$html = '<div class="wrap"><h1>' . esc_html__( 'Quizzes & exams', 'course-captain' ) . '</h1>' . AdminUi::areas( __( 'Quizzes & exams: areas', 'course-captain' ), $areas, $area, self::SLUG );
		if ( 'grading' === $area ) {
			return $html . GradingArea::html( $request, $user_id ) . '</div>';
		}
		$rule = absint( $request['rule'] ?? 0 );
		return $html . ( $rule > 0 ? self::detail( $request, $user_id, $rule ) : self::listing( $request, $user_id ) ) . '</div>';
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
			'area'        => 'exams',
			'ccap_result' => $out['result'],
		);
		if ( $out['rule'] > 0 ) {
			$args['rule'] = $out['rule'];
		} elseif ( $out['course'] > 0 ) {
			$args['course'] = $out['course'];
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Testable core of the handler.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @param int                  $user_id Current user.
	 * @return array{op: string, rule: int, course: int, result: string, messages: list<string>}
	 * @throws CourseException Never; domain errors become the result code.
	 */
	public static function process( array $post, int $user_id ): array {
		$op      = sanitize_key( (string) ( $post['op'] ?? '' ) );
		$rule    = absint( $post['object'] ?? 0 );
		$variant = 0;
		if ( str_starts_with( $op, 'variant_' ) && 'variant_create' !== $op ) {
			$variant = $rule;
			$rule    = absint( $post['rule'] ?? 0 );
		}
		$out   = array(
			'op'       => $op,
			'rule'     => 'create' === $op ? 0 : $rule,
			'course'   => absint( $post['course'] ?? 0 ),
			'result'   => 'ok',
			'messages' => array(),
		);
		$rules = ExamRules::current();
		if ( ! in_array( $op, self::OPS, true ) ) {
			$out['result'] = 'ccap_not_found';
			return $out;
		}
		if ( ! AdminUi::verify( self::ACTION, $post ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		if ( ! user_can( $user_id, self::CAP ) ) {
			$out['result'] = 'ccap_forbidden';
			return $out;
		}
		$actor = Actor::user( $user_id );
		$db    = Database::current();
		try {
			switch ( $op ) {
				case 'create':
					$ratio = AdminUi::ratio( (string) ( $post['pass_percent'] ?? '' ) );
					if ( null === $ratio ) {
						$out['messages'][] = __( 'Enter the pass mark as a percentage between 1 and 100.', 'course-captain' );
						throw new CourseException( 'ccap_validation_failed', 'pass', 422 );
					}
					$version     = trim( sanitize_text_field( (string) ( $post['version'] ?? '' ) ) );
					$out['rule'] = $rules->create( $actor, $out['course'], '' === $version ? __( 'Exam', 'course-captain' ) : $version, array( 'min_ratio' => $ratio ) );
					AuditLog::write( $db, $actor, 'exam_rule_saved', 'exam_rule', (string) $out['rule'], null, array( 'course_id' => $out['course'] ) );
					break;
				case 'save':
					$out['messages'] = self::save( $actor, $rule, $post );
					if ( array() !== $out['messages'] ) {
						$out['result'] = 'ccap_validation_failed';
					}
					break;
				case 'publish':
					$messages = self::publish_blockers( $rule );
					if ( array() !== $messages ) {
						$out['messages'] = $messages;
						$out['result']   = 'ccap_exam_not_ready';
						break;
					}
					$rules->publish( $actor, $rule );
					AuditLog::write( $db, $actor, 'exam_rule_published', 'exam_rule', (string) $rule );
					break;
				case 'new_version':
					$out['rule'] = self::copy( $actor, $rule );
					AuditLog::write( $db, $actor, 'exam_rule_saved', 'exam_rule', (string) $out['rule'], null, array( 'copied_from' => $rule ) );
					break;
				case 'variant_create':
				case 'variant_save':
				case 'variant_freeze':
				case 'variant_retire':
					$out['messages'] = self::variant_op( $actor, $op, $rule, $variant, $post );
					if ( array() !== $out['messages'] ) {
						$out['result'] = 'ccap_validation_failed';
					}
					break;
			}
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
			if ( 'ccap_setting_locked' === $e->error_code ) {
				/* translators: %s: setting key */
				$out['messages'][] = sprintf( __( 'This setting is locked for all courses: %s', 'course-captain' ), $e->getMessage() );
			}
		}
		if ( array() !== $out['messages'] ) {
			set_transient( 'ccap_exam_msg_' . $user_id, $out['messages'], 600 );
		}
		if ( 'ok' !== $out['result'] && 'save' === $op ) {
			set_transient( 'ccap_exam_form_' . $user_id, $post, 600 );
		}
		return $out;
	}

	/**
	 * Save the editor form of a draft rule.
	 *
	 * @param Actor                $actor Author.
	 * @param int                  $rule  Draft rule.
	 * @param array<string, mixed> $post  POST data.
	 * @return list<string> Messages for the author (empty = saved).
	 * @throws CourseException On frozen rules, rights or rejected overrides.
	 */
	private static function save( Actor $actor, int $rule, array $post ): array {
		$rules = ExamRules::current();
		$row   = $rules->get( $rule );
		if ( null === $row ) {
			throw new CourseException( 'ccap_not_found', 'rule', 404 );
		}
		$messages = array();
		$ratio    = AdminUi::ratio( (string) ( $post['pass_percent'] ?? '' ) );
		if ( null === $ratio ) {
			$messages[] = __( 'Enter the pass mark as a percentage between 1 and 100.', 'course-captain' );
		}
		$modules   = array_map( static fn( array $l ): int => $l['module_id'], CourseStructure::current()->modules( (int) $row['course_id'] ) );
		$blueprint = array();
		foreach ( (array) ( $post['blueprint'] ?? array() ) as $module => $entry ) {
			$entry = (array) $entry;
			$count = trim( (string) ( $entry['count'] ?? '' ) );
			if ( ! in_array( (int) $module, $modules, true ) ) {
				continue;
			}
			if ( '' === $count || '0' === $count ) {
				$blueprint[ (int) $module ] = null;
				continue;
			}
			$points = trim( (string) ( $entry['points'] ?? '1' ) );
			if ( ! ctype_digit( $count ) || ! ctype_digit( '' === $points ? '1' : $points ) || (int) $points > 100 || ( '' !== $points && (int) $points < 1 ) ) {
				/* translators: %s: module title */
				$messages[] = sprintf( __( 'Module %s: enter whole numbers (questions at least 1, points 1 to 100).', 'course-captain' ), (string) get_the_title( (int) $module ) );
				continue;
			}
			$blueprint[ (int) $module ] = array( (int) $count, '' === $points ? 1 : (int) $points );
		}
		$parts = array();
		foreach ( array_values( (array) ( $post['parts'] ?? array() ) ) as $i => $part ) {
			$part     = (array) $part;
			$sections = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $part['modules'] ?? array() ) ), static fn( int $m ): bool => in_array( $m, $modules, true ) ) ) );
			$percent  = trim( (string) ( $part['percent'] ?? '' ) );
			$label    = trim( sanitize_text_field( (string) ( $part['label'] ?? '' ) ) );
			if ( array() === $sections && '' === $percent && '' === $label ) {
				continue;
			}
			$part_ratio = AdminUi::ratio( $percent );
			if ( array() === $sections || null === $part_ratio ) {
				/* translators: %d: part number */
				$messages[] = sprintf( __( 'Part %d: choose at least one module and enter a pass mark between 1 and 100 %%.', 'course-captain' ), $i + 1 );
				continue;
			}
			sort( $sections );
			$entry = array(
				'key'       => 'P' . ( count( $parts ) + 1 ),
				'sections'  => $sections,
				'min_ratio' => $part_ratio,
			);
			if ( '' !== $label ) {
				$entry['label'] = $label;
			}
			$parts[] = $entry;
		}
		$overrides = self::overrides_from_post( $post, $messages );
		if ( array() !== $messages || null === $ratio ) {
			return $messages;
		}
		$passing = array( 'min_ratio' => $ratio );
		if ( array() !== $parts ) {
			$passing['parts'] = $parts;
		}
		$version = trim( sanitize_text_field( (string) ( $post['version'] ?? '' ) ) );
		$rules->update_draft( $actor, $rule, '' === $version ? (string) $row['version'] : $version, $passing );
		foreach ( $blueprint as $module => $entry ) {
			if ( null === $entry ) {
				$rules->remove_blueprint( $actor, $rule, $module );
			} else {
				$rules->set_blueprint( $actor, $rule, $module, $entry[0], $entry[1] );
			}
		}
		if ( array() !== $overrides ) {
			$rules->set_overrides( $actor, $rule, $overrides );
		}
		$mode = sanitize_key( (string) ( $post['form_mode'] ?? '' ) );
		if ( in_array( $mode, ExamRules::FORM_MODES, true ) ) {
			$rules->set_form_mode( $actor, $rule, $mode );
		}
		AuditLog::write( Database::current(), $actor, 'exam_rule_saved', 'exam_rule', (string) $rule );
		return array();
	}

	/**
	 * Register-backed overrides from the form (`inherit` = site default).
	 *
	 * @param array<string, mixed> $post     POST data.
	 * @param array                $messages Messages (appended).
	 * @phpstan-param list<string> $messages
	 * @return array<string, mixed>
	 */
	private static function overrides_from_post( array $post, array &$messages ): array {
		$out = array();
		foreach ( array(
			'time'     => array( 'exams.time_limit_minutes', 1, 1440, __( 'Enter the time limit in whole minutes (1 to 1440).', 'course-captain' ) ),
			'attempts' => array( 'exams.max_attempts', 1, 100, __( 'Enter the number of attempts as a whole number (1 to 100).', 'course-captain' ) ),
		) as $field => $spec ) {
			$mode = sanitize_key( (string) ( $post[ $field . '_mode' ] ?? 'inherit' ) );
			if ( 'inherit' === $mode ) {
				$out[ $spec[0] ] = EffectiveSettings::INHERIT;
			} elseif ( 'none' === $mode ) {
				$out[ $spec[0] ] = null;
			} else {
				$value = trim( (string) ( $post[ $field ] ?? '' ) );
				if ( ! ctype_digit( $value ) || (int) $value < $spec[1] || (int) $value > $spec[2] ) {
					$messages[] = $spec[3];
					continue;
				}
				$out[ $spec[0] ] = (int) $value;
			}
		}
		$shuffle                        = sanitize_key( (string) ( $post['shuffle_questions'] ?? 'inherit' ) );
		$out['exams.shuffle_questions'] = in_array( $shuffle, array( 'none', 'within_section', 'all' ), true ) ? $shuffle : EffectiveSettings::INHERIT;
		$feedback                       = sanitize_key( (string) ( $post['feedback'] ?? 'inherit' ) );
		$map                            = array(
			'full'  => array( 'after_submit', true, true ),
			'score' => array( 'after_submit', true, false ),
			'never' => array( 'never', false, false ),
		);
		$out['exams.feedback']          = isset( $map[ $feedback ] ) ? array(
			'visibility'                => $map[ $feedback ][0],
			'show_score'                => $map[ $feedback ][1],
			'show_correct_answers'      => $map[ $feedback ][2],
			'show_correct_answers_when' => 'no_attempts_left_or_passed',
		) : EffectiveSettings::INHERIT;
		return $out;
	}

	/**
	 * Reasons a draft cannot be published yet (VAL-073…VAL-087 subset in words).
	 *
	 * @param int $rule_id Rule.
	 * @return list<string>
	 */
	public static function publish_blockers( int $rule_id ): array {
		$rule = ExamRules::current()->get( $rule_id );
		if ( null === $rule ) {
			return array( __( 'The exam was not found.', 'course-captain' ) );
		}
		$out = array();
		if ( array() !== ExamRules::validate_passing_rule( $rule['passing_rule'] ) ) {
			$out[] = __( 'The pass mark is missing or invalid.', 'course-captain' );
		}
		$variants = 'variants' === ExamRules::form_mode( $rule );
		if ( $variants && array() === array_filter( ExamRules::current()->variants( (int) $rule['course_id'], false ), static fn( array $v ): bool => 'published' === $v['status'] ) ) {
			$out[] = __( 'The exam uses fixed exam forms: freeze at least one exam form first.', 'course-captain' );
		}
		if ( ! $variants && array() === $rule['blueprints'] ) {
			$out[] = __( 'Choose at least one module and the number of questions drawn from it.', 'course-captain' );
		}
		$sections = array_map( static fn( array $b ): int => (int) $b['module_id'], $rule['blueprints'] );
		foreach ( ExamRules::parts( (array) $rule['passing_rule'] ) as $part ) {
			if ( array() !== array_diff( $part['sections'], $sections ) ) {
				/* translators: %s: part name */
				$out[] = sprintf( __( 'Part %s uses a module from which the exam draws no questions.', 'course-captain' ), $part['label'] ?? $part['key'] );
			}
		}
		foreach ( $rule['blueprints'] as $bp ) {
			$pool = count( QuestionRepository::current()->pool( (int) $bp['module_id'] ) );
			if ( $pool < (int) $bp['question_count'] ) {
				/* translators: 1: module title, 2: deliverable questions, 3: questions drawn */
				$out[] = sprintf( __( 'Module %1$s: pool too small (%2$d of %3$d questions). Publish more questions or draw fewer.', 'course-captain' ), (string) get_the_title( (int) $bp['module_id'] ), $pool, (int) $bp['question_count'] );
			}
		}
		return $out;
	}

	/**
	 * New draft version from a rule (passing rule, overrides, blueprint).
	 *
	 * @param Actor $actor   Author.
	 * @param int   $rule_id Source rule.
	 * @return int New rule.
	 * @throws CourseException When unknown.
	 */
	private static function copy( Actor $actor, int $rule_id ): int {
		$rules = ExamRules::current();
		$rule  = $rules->get( $rule_id );
		if ( null === $rule ) {
			throw new CourseException( 'ccap_not_found', 'rule', 404 );
		}
		$stored = (array) $rule['settings_stored'];
		$own    = array_intersect_key( $stored, ExamRules::own_defaults() );
		$over   = (array) ( $stored['overrides'] ?? array() );
		if ( null !== $rule['time_limit_column'] && ! array_key_exists( 'exams.time_limit_minutes', $over ) ) {
			$over['exams.time_limit_minutes'] = (int) ceil( $rule['time_limit_column'] / 60 );
		}
		/* translators: %s: version label */
		$new = $rules->create( $actor, (int) $rule['course_id'], sprintf( __( '%s (new version)', 'course-captain' ), (string) $rule['version'] ), (array) $rule['passing_rule'], null, $own + array( 'overrides' => $over ) );
		foreach ( $rule['blueprints'] as $bp ) {
			$points = (array) json_decode( (string) $bp['rules'], true );
			$rules->set_blueprint( $actor, $new, (int) $bp['module_id'], (int) $bp['question_count'], (int) ( $points['points'] ?? 1 ) );
		}
		if ( '' !== ExamRules::form_mode( $rule ) ) {
			$rules->set_form_mode( $actor, $new, ExamRules::form_mode( $rule ) );
		}
		return $new;
	}

	/**
	 * One-line summary of a rule (also used by the learner preview).
	 *
	 * @param array<string, mixed> $rule Rule from {@see ExamRules::get()}.
	 * @return string
	 */
	public static function summary( array $rule ): string {
		$ratio = (array) ( $rule['passing_rule']['min_ratio'] ?? array() );
		$count = array_sum( array_map( static fn( array $b ): int => (int) $b['question_count'], (array) $rule['blueprints'] ) );
		$limit = $rule['time_limit_seconds'];
		$tries = $rule['settings']['attempt_policy']['max_attempts'] ?? null;
		$mins  = (int) ceil( (int) $limit / 60 );
		$time  = __( 'none', 'course-captain' );
		if ( null !== $limit ) {
			/* translators: %d: minutes */
			$time = sprintf( _n( '%d minute', '%d minutes', $mins, 'course-captain' ), $mins );
		}
		$times = null === $tries ? __( 'unlimited', 'course-captain' ) : (string) (int) $tries;
		$pass  = isset( $ratio['num'], $ratio['den'] ) ? AdminUi::percent( (int) $ratio['num'], (int) $ratio['den'] ) : '–';
		/* translators: 1: version, 2: questions, 3: pass mark, 4: time limit, 5: attempts */
		return sprintf( __( '%1$s: %2$d questions, pass mark %3$s %%, time limit %4$s, attempts %5$s', 'course-captain' ), (string) $rule['version'], $count, $pass, $time, $times );
	}

	/**
	 * Notice with kept messages.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 */
	private static function notice( array $request, int $user_id ): string {
		$code     = sanitize_key( (string) ( $request['ccap_result'] ?? '' ) );
		$messages = get_transient( 'ccap_exam_msg_' . $user_id );
		$extra    = '';
		if ( is_array( $messages ) && '' !== $code ) {
			delete_transient( 'ccap_exam_msg_' . $user_id );
			$extra = '<ul>' . implode( '', array_map( static fn( $m ): string => '<li>' . esc_html( (string) $m ) . '</li>', $messages ) ) . '</ul>';
		}
		return AdminUi::notice(
			$code,
			array(
				'ccap_rule_frozen'           => __( 'A published exam never changes. Create a new version to make changes.', 'course-captain' ),
				'ccap_variant_frozen'        => __( 'A frozen exam form never changes. Create a new exam form instead.', 'course-captain' ),
				'ccap_confirmation_required' => __( 'Please confirm the action (tick the confirmation box).', 'course-captain' ),
				'ccap_exam_not_ready'        => __( 'Not published yet:', 'course-captain' ),
				'ccap_setting_locked'        => __( 'Not saved: a setting is locked for all courses.', 'course-captain' ),
				'ccap_setting_not_stricter'  => __( 'Not saved: a setting may only be stricter than the site default.', 'course-captain' ),
			),
			__( 'Saved.', 'course-captain' ),
			$extra
		);
	}

	/**
	 * Rules per course and "new exam".
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 */
	private static function listing( array $request, int $user_id ): string {
		$course = absint( $request['course'] ?? 0 );
		$html   = self::notice( $request, $user_id );
		$html  .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="area" value="exams"><p><label for="ccap-e-course">' . esc_html__( 'Course', 'course-captain' ) . '</label> ' . AdminUi::course_select( 'ccap-e-course', 'course', $course, $user_id, __( 'All courses', 'course-captain' ) ) . ' <button type="submit" class="button">' . esc_html__( 'Show', 'course-captain' ) . '</button></p></form>';
		$db     = Database::current();
		$ids    = array_map( static fn( \WP_Post $p ): int => (int) $p->ID, AdminUi::courses( $user_id ) );
		$ids    = $course > 0 ? array_values( array_intersect( $ids, array( $course ) ) ) : $ids;
		$rows   = array() === $ids ? array() : $db->rows( 'SELECT id FROM `' . $db->table( 'ccap_exam_rule' ) . '` WHERE course_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ") AND status <> 'archived' ORDER BY course_id, id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Integer list.
		$html  .= '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Exams', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Exam', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Course', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th></tr></thead><tbody>';
		if ( array() === $rows ) {
			$html .= '<tr><td colspan="3">' . esc_html__( 'No exams yet.', 'course-captain' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$rule = ExamRules::current()->get( (int) $row['id'] );
			if ( null === $rule ) {
				continue;
			}
			$url   = add_query_arg(
				array(
					'page' => self::SLUG,
					'area' => 'exams',
					'rule' => (int) $rule['id'],
				),
				admin_url( 'admin.php' )
			);
			$html .= '<tr><th scope="row"><a href="' . esc_url( $url ) . '">' . esc_html( self::summary( $rule ) ) . '</a></th><td>' . esc_html( (string) get_the_title( (int) $rule['course_id'] ) ) . '</td><td>' . esc_html( self::status_label( (string) $rule['status'] ) ) . '</td></tr>';
		}
		$html   .= '</tbody></table><h2>' . esc_html__( 'New exam', 'course-captain' ) . '</h2>';
		$fields  = '<p><label for="ccap-new-course">' . esc_html__( 'Course', 'course-captain' ) . '</label><br>' . AdminUi::course_select( 'ccap-new-course', 'course', $course, $user_id ) . '</p>';
		$fields .= '<p><label for="ccap-new-version">' . esc_html__( 'Name of the exam', 'course-captain' ) . '</label><br><input id="ccap-new-version" type="text" name="version" class="regular-text" value="' . esc_attr__( 'Final exam', 'course-captain' ) . '"></p>';
		// SET-CC-P1: prefilled with `exams.passing_ratio_proposal` (a suggestion; every exam stores its own threshold).
		$proposal = (array) \CourseCaptain\Settings\Settings::get( 'exams.passing_ratio_proposal' );
		$fields  .= '<p><label for="ccap-new-pass">' . esc_html__( 'Pass mark in percent', 'course-captain' ) . '</label><br><input id="ccap-new-pass" type="text" inputmode="decimal" name="pass_percent" size="6" value="' . esc_attr( AdminUi::percent( (int) ( $proposal['num'] ?? 7 ), max( 1, (int) ( $proposal['den'] ?? 10 ) ) ) ) . '" required aria-describedby="ccap-new-pass-help"> % <span id="ccap-new-pass-help" class="description">' . esc_html__( 'For example 60 for 60 % of the points.', 'course-captain' ) . '</span></p>';
		return $html . AdminUi::form( self::ACTION, 'create', 0, $fields, __( 'Create exam draft', 'course-captain' ), '1' );
	}

	/**
	 * Rule editor / read-only view.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @param int                  $rule_id Rule.
	 * @return string
	 */
	private static function detail( array $request, int $user_id, int $rule_id ): string {
		$rule = ExamRules::current()->get( $rule_id );
		if ( null === $rule || ! user_can( $user_id, 'edit_post', (int) $rule['course_id'] ) ) {
			return '<p>' . esc_html__( 'This exam does not exist or you may not edit it.', 'course-captain' ) . '</p>';
		}
		$back  = add_query_arg(
			array(
				'page'   => self::SLUG,
				'area'   => 'exams',
				'course' => (int) $rule['course_id'],
			),
			admin_url( 'admin.php' )
		);
		$html  = '<p><a href="' . esc_url( $back ) . '">' . esc_html__( 'Back to the exams', 'course-captain' ) . '</a></p>' . self::notice( $request, $user_id );
		$html .= '<h2>' . esc_html( sprintf( /* translators: 1: exam name, 2: course */ __( '%1$s (course: %2$s)', 'course-captain' ), (string) $rule['version'], (string) get_the_title( (int) $rule['course_id'] ) ) ) . '</h2>';
		$html .= '<p>' . esc_html( sprintf( /* translators: %s: status */ __( 'Status: %s', 'course-captain' ), self::status_label( (string) $rule['status'] ) ) ) . ' · ' . esc_html( self::summary( $rule ) ) . '</p>';
		$kept  = get_transient( 'ccap_exam_form_' . $user_id );
		$input = array();
		if ( is_array( $kept ) && absint( $kept['object'] ?? 0 ) === $rule_id && '' !== (string) ( $request['ccap_result'] ?? '' ) ) {
			delete_transient( 'ccap_exam_form_' . $user_id );
			$input = $kept;
		}
		if ( 'draft' === $rule['status'] ) {
			$html .= AdminUi::form( self::ACTION, 'save', $rule_id, self::editor_fields( $rule, $input ), __( 'Save exam', 'course-captain' ), '1', ' data-ccap-draft="exam-' . $rule_id . '"' );
			$block = self::publish_blockers( $rule_id );
			$html .= '<h3>' . esc_html__( 'Publish', 'course-captain' ) . '</h3>';
			if ( array() !== $block ) {
				$html .= '<div class="notice notice-warning inline" role="status"><p>' . esc_html__( 'Before publishing:', 'course-captain' ) . '</p><ul>' . implode( '', array_map( static fn( string $m ): string => '<li>' . esc_html( $m ) . '</li>', $block ) ) . '</ul></div>';
			}
			$html .= AdminUi::form( self::ACTION, 'publish', $rule_id, '<p class="description">' . esc_html__( 'After publishing, learners can start the exam and the rules no longer change. A previously published version of this course is retired; running attempts keep their rules.', 'course-captain' ) . '</p>', __( 'Publish exam', 'course-captain' ) );
			$html .= QuestionsScreen::draft_script();
		} else {
			$html .= self::readonly( $rule );
			$html .= AdminUi::form( self::ACTION, 'new_version', $rule_id, '<p class="description">' . esc_html__( 'A published exam never changes. Create a new version as a draft, change it and publish it.', 'course-captain' ) . '</p>', __( 'Create new version', 'course-captain' ) );
		}
		$html .= self::variants_section( $rule, $rule_id );
		$html .= '<h3>' . esc_html__( 'Effective settings', 'course-captain' ) . '</h3><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Setting', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Value', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Source', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( ExamRules::current()->panel( $rule_id ) as $row ) {
			$value = is_bool( $row['value'] ) ? ( $row['value'] ? __( 'yes', 'course-captain' ) : __( 'no', 'course-captain' ) ) : ( null === $row['value'] ? __( 'none', 'course-captain' ) : ( is_array( $row['value'] ) ? (string) wp_json_encode( $row['value'] ) : (string) $row['value'] ) );
			$html .= '<tr><td>' . esc_html( $row['label'] ) . '</td><td>' . esc_html( $value ) . '</td><td>' . esc_html( $row['overridden'] ? __( 'this exam', 'course-captain' ) : __( 'site default', 'course-captain' ) ) . ( $row['locked'] ? ' (' . esc_html__( 'locked for all courses', 'course-captain' ) . ')' : '' ) . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Fields of the draft editor.
	 *
	 * @param array<string, mixed> $rule  Rule.
	 * @param array<string, mixed> $input Kept input after an error (wins).
	 * @return string
	 */
	private static function editor_fields( array $rule, array $input ): string {
		$ratio   = (array) ( $rule['passing_rule']['min_ratio'] ?? array() );
		$pass    = $input['pass_percent'] ?? ( isset( $ratio['num'], $ratio['den'] ) ? AdminUi::percent( (int) $ratio['num'], (int) $ratio['den'] ) : '' );
		$modules = CourseStructure::current()->modules( (int) $rule['course_id'] );
		$bp      = array();
		foreach ( (array) $rule['blueprints'] as $b ) {
			$bp[ (int) $b['module_id'] ] = array( (int) $b['question_count'], (int) ( ( (array) json_decode( (string) $b['rules'], true ) )['points'] ?? 1 ) );
		}
		$html  = '<p><label for="ccap-ex-version">' . esc_html__( 'Name of the exam', 'course-captain' ) . '</label><br><input id="ccap-ex-version" type="text" name="version" class="regular-text" value="' . esc_attr( (string) ( $input['version'] ?? $rule['version'] ) ) . '"></p>';
		$html .= '<p><label for="ccap-ex-pass"><strong>' . esc_html__( 'Pass mark for the whole exam in percent', 'course-captain' ) . '</strong></label><br><input id="ccap-ex-pass" type="text" inputmode="decimal" name="pass_percent" size="6" value="' . esc_attr( (string) $pass ) . '" required aria-describedby="ccap-ex-pass-help"> % <span id="ccap-ex-pass-help" class="description">' . esc_html__( 'Share of the points needed to pass, for example 60.', 'course-captain' ) . '</span></p>';
		$html .= self::form_mode_field( (string) ( $input['form_mode'] ?? ( '' === ExamRules::form_mode( $rule ) ? 'random' : ExamRules::form_mode( $rule ) ) ) );
		$html .= '<fieldset><legend><strong>' . esc_html__( 'Questions per module', 'course-captain' ) . '</strong></legend><p class="description">' . esc_html__( 'Number of questions drawn at random from each module for every attempt. Leave empty or 0 to skip a module.', 'course-captain' ) . '</p><table class="widefat"><thead><tr><th scope="col">' . esc_html__( 'Module', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Questions drawn', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Points per question', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Published questions in the module', 'course-captain' ) . '</th></tr></thead><tbody>';
		if ( array() === $modules ) {
			$html .= '<tr><td colspan="4">' . esc_html__( 'The course has no modules yet.', 'course-captain' ) . '</td></tr>';
		}
		foreach ( $modules as $link ) {
			$m     = $link['module_id'];
			$title = (string) get_the_title( $m );
			$count = (string) ( $input['blueprint'][ $m ]['count'] ?? ( isset( $bp[ $m ] ) ? $bp[ $m ][0] : '' ) );
			$pts   = (string) ( $input['blueprint'][ $m ]['points'] ?? ( isset( $bp[ $m ] ) ? $bp[ $m ][1] : '1' ) );
			$pool  = count( QuestionRepository::current()->pool( $m ) );
			$small = '' !== $count && (int) $count > $pool;
			$html .= '<tr><th scope="row">' . esc_html( $title ) . '</th><td><label class="screen-reader-text" for="ccap-bp-' . $m . '">' . esc_html( sprintf( /* translators: %s: module */ __( 'Questions drawn from %s', 'course-captain' ), $title ) ) . '</label><input id="ccap-bp-' . $m . '" type="number" min="0" step="1" name="blueprint[' . $m . '][count]" value="' . esc_attr( $count ) . '"' . ( $small ? ' aria-describedby="ccap-bp-' . $m . '-small"' : '' ) . '></td>';
			$html .= '<td><label class="screen-reader-text" for="ccap-bp-' . $m . '-pts">' . esc_html( sprintf( /* translators: %s: module */ __( 'Points per question from %s', 'course-captain' ), $title ) ) . '</label><input id="ccap-bp-' . $m . '-pts" type="number" min="1" max="100" step="1" name="blueprint[' . $m . '][points]" value="' . esc_attr( $pts ) . '"></td>';
			$html .= '<td>' . (int) $pool . ( $small ? ' <strong id="ccap-bp-' . $m . '-small">' . esc_html( sprintf( /* translators: 1: pool, 2: drawn */ __( 'Pool too small (%1$d of %2$d)', 'course-captain' ), $pool, (int) $count ) ) . '</strong>' : '' ) . '</td></tr>';
		}
		$html .= '</tbody></table></fieldset>';
		// Part pass marks over module groups (E-331).
		$parts = array_values( (array) ( $input['parts'] ?? array() ) );
		if ( array() === $parts ) {
			foreach ( ExamRules::parts( (array) $rule['passing_rule'] ) as $part ) {
				$parts[] = array(
					'label'   => (string) $part['label'],
					'modules' => $part['sections'],
					'percent' => AdminUi::percent( $part['min_ratio']['num'], $part['min_ratio']['den'] ),
				);
			}
		}
		$rows  = count( $parts ) + 2;
		$html .= '<fieldset><legend><strong>' . esc_html__( 'Part pass marks (optional)', 'course-captain' ) . '</strong></legend><p class="description">' . esc_html__( 'A part groups one or more modules. Learners pass only if they reach the pass mark of the whole exam and of every part.', 'course-captain' ) . '</p>';
		for ( $i = 0; $i < $rows; $i++ ) {
			$part  = (array) ( $parts[ $i ] ?? array() );
			$chose = array_map( 'intval', (array) ( $part['modules'] ?? array() ) );
			$html .= '<fieldset class="ccap-ex-part"><legend>' . esc_html( sprintf( /* translators: %d: part number */ __( 'Part %d', 'course-captain' ), $i + 1 ) ) . '</legend>';
			$html .= '<p><label for="ccap-part-' . $i . '-label">' . esc_html__( 'Name (optional)', 'course-captain' ) . '</label> <input id="ccap-part-' . $i . '-label" type="text" name="parts[' . $i . '][label]" value="' . esc_attr( (string) ( $part['label'] ?? '' ) ) . '"> ';
			$html .= '<label for="ccap-part-' . $i . '-pct">' . esc_html__( 'Pass mark in percent', 'course-captain' ) . '</label> <input id="ccap-part-' . $i . '-pct" type="text" inputmode="decimal" size="6" name="parts[' . $i . '][percent]" value="' . esc_attr( (string) ( $part['percent'] ?? '' ) ) . '"> %</p><p>';
			foreach ( $modules as $link ) {
				$html .= '<label><input type="checkbox" name="parts[' . $i . '][modules][]" value="' . (int) $link['module_id'] . '"' . checked( in_array( $link['module_id'], $chose, true ), true, false ) . '> ' . esc_html( (string) get_the_title( $link['module_id'] ) ) . '</label> ';
			}
			$html .= '</p></fieldset>';
		}
		$html   .= '</fieldset>';
		$own     = (array) ( $rule['settings_stored']['overrides'] ?? array() );
		$html   .= '<fieldset><legend><strong>' . esc_html__( 'Time and attempts', 'course-captain' ) . '</strong></legend>';
		$html   .= self::mode_field( 'time', __( 'Time limit', 'course-captain' ), __( 'minutes', 'course-captain' ), __( 'no time limit', 'course-captain' ), $input, array_key_exists( 'exams.time_limit_minutes', $own ) ? $own['exams.time_limit_minutes'] : ( null !== $rule['time_limit_column'] ? (int) ceil( $rule['time_limit_column'] / 60 ) : EffectiveSettings::INHERIT ) );
		$html   .= self::mode_field( 'attempts', __( 'Attempts per learner', 'course-captain' ), __( 'attempts', 'course-captain' ), __( 'unlimited', 'course-captain' ), $input, array_key_exists( 'exams.max_attempts', $own ) ? $own['exams.max_attempts'] : EffectiveSettings::INHERIT );
		$html   .= '</fieldset>';
		$shuffle = (string) ( $input['shuffle_questions'] ?? ( $own['exams.shuffle_questions'] ?? 'inherit' ) );
		$html   .= '<p><label for="ccap-ex-shuffle">' . esc_html__( 'Order of the questions', 'course-captain' ) . '</label><br><select id="ccap-ex-shuffle" name="shuffle_questions">';
		foreach ( array(
			'inherit'        => __( 'site default', 'course-captain' ),
			'none'           => __( 'fixed order', 'course-captain' ),
			'within_section' => __( 'shuffled within each module', 'course-captain' ),
			'all'            => __( 'shuffled across all modules', 'course-captain' ),
		) as $key => $label ) {
			$html .= '<option value="' . esc_attr( $key ) . '"' . selected( $shuffle, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$fb = 'inherit';
		if ( isset( $own['exams.feedback'] ) && is_array( $own['exams.feedback'] ) ) {
			$fb = 'never' === ( $own['exams.feedback']['visibility'] ?? '' ) ? 'never' : ( ! empty( $own['exams.feedback']['show_correct_answers'] ) ? 'full' : 'score' );
		}
		$fb    = (string) ( $input['feedback'] ?? $fb );
		$html .= '</select></p><p><label for="ccap-ex-feedback">' . esc_html__( 'What learners see after submitting', 'course-captain' ) . '</label><br><select id="ccap-ex-feedback" name="feedback">';
		foreach ( array(
			'inherit' => __( 'site default', 'course-captain' ),
			'full'    => __( 'result, points and the correct answers', 'course-captain' ),
			'score'   => __( 'result and points only', 'course-captain' ),
			'never'   => __( 'nothing (result only later)', 'course-captain' ),
		) as $key => $label ) {
			$html .= '<option value="' . esc_attr( $key ) . '"' . selected( $fb, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html . '</select></p>';
	}

	/**
	 * Field with the modes "site default", "none/unlimited", "value".
	 *
	 * @param string               $name    Field name.
	 * @param string               $label   Label.
	 * @param string               $unit    Unit.
	 * @param string               $none    Label of "none".
	 * @param array<string, mixed> $input   Kept input.
	 * @param mixed                $current Stored override or INHERIT.
	 * @return string
	 */
	private static function mode_field( string $name, string $label, string $unit, string $none, array $input, $current ): string {
		$mode  = (string) ( $input[ $name . '_mode' ] ?? ( EffectiveSettings::INHERIT === $current ? 'inherit' : ( null === $current ? 'none' : 'value' ) ) );
		$value = (string) ( $input[ $name ] ?? ( is_int( $current ) ? $current : '' ) );
		$id    = 'ccap-ex-' . $name;
		$html  = '<fieldset><legend>' . esc_html( $label ) . '</legend>';
		$html .= '<label><input type="radio" name="' . esc_attr( $name ) . '_mode" value="inherit"' . checked( $mode, 'inherit', false ) . '> ' . esc_html__( 'site default', 'course-captain' ) . '</label><br>';
		$html .= '<label><input type="radio" name="' . esc_attr( $name ) . '_mode" value="none"' . checked( $mode, 'none', false ) . '> ' . esc_html( $none ) . '</label><br>';
		$html .= '<label><input type="radio" name="' . esc_attr( $name ) . '_mode" value="value"' . checked( $mode, 'value', false ) . '> ' . esc_html__( 'this value:', 'course-captain' ) . '</label> <label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html( $label . ' (' . $unit . ')' ) . '</label><input id="' . esc_attr( $id ) . '" type="number" min="1" step="1" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"> ' . esc_html( $unit );
		return $html . '</fieldset>';
	}

	/**
	 * Read-only view of a published or retired rule.
	 *
	 * @param array<string, mixed> $rule Rule.
	 * @return string
	 */
	private static function readonly( array $rule ): string {
		$html  = '<p>' . esc_html( 'variants' === ExamRules::form_mode( $rule ) ? __( 'How questions are chosen: fixed exam forms', 'course-captain' ) : __( 'How questions are chosen: random draw per attempt', 'course-captain' ) ) . '</p>';
		$html .= '<h3>' . esc_html__( 'Questions per module', 'course-captain' ) . '</h3><ul>';
		foreach ( (array) $rule['blueprints'] as $bp ) {
			$html .= '<li>' . esc_html( sprintf( /* translators: 1: module, 2: questions */ __( '%1$s: %2$d questions', 'course-captain' ), (string) get_the_title( (int) $bp['module_id'] ), (int) $bp['question_count'] ) ) . '</li>';
		}
		$html .= '</ul>';
		$parts = ExamRules::parts( (array) $rule['passing_rule'] );
		if ( array() !== $parts ) {
			$html .= '<h3>' . esc_html__( 'Part pass marks', 'course-captain' ) . '</h3><ul>';
			foreach ( $parts as $part ) {
				$html .= '<li>' . esc_html( sprintf( /* translators: 1: part, 2: modules, 3: percent */ __( '%1$s (%2$s): %3$s %%', 'course-captain' ), $part['label'] ?? $part['key'], implode( ', ', array_map( static fn( int $m ): string => (string) get_the_title( $m ), $part['sections'] ) ), AdminUi::percent( $part['min_ratio']['num'], $part['min_ratio']['den'] ) ) ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html;
	}

	/**
	 * Variant operations (UI-F8).
	 *
	 * @param Actor                $actor   Author.
	 * @param string               $op      Operation.
	 * @param int                  $rule_id Rule (way back, course).
	 * @param int                  $variant Variant (0 on create).
	 * @param array<string, mixed> $post    POST data.
	 * @return list<string> Messages (empty = done).
	 * @throws CourseException On rights, unknown or frozen variants.
	 */
	private static function variant_op( Actor $actor, string $op, int $rule_id, int $variant, array $post ): array {
		$rules = ExamRules::current();
		$rule  = $rules->get( $rule_id );
		if ( null === $rule ) {
			throw new CourseException( 'ccap_not_found', 'rule', 404 );
		}
		$db = Database::current();
		if ( 'variant_create' === $op ) {
			$variant = $rules->create_variant_draft( $actor, (int) $rule['course_id'] );
			AuditLog::write( $db, $actor, 'exam_variant_saved', 'exam_variant', (string) $variant, null, array( 'course_id' => (int) $rule['course_id'] ) );
			return array();
		}
		$course = (int) $db->scalar( $db->prepare( 'SELECT course_id FROM `' . $db->table( 'ccap_exam_variant' ) . '` WHERE id = %d', $variant ) );
		if ( $course !== (int) $rule['course_id'] ) {
			throw new CourseException( 'ccap_not_found', 'variant', 404 );
		}
		switch ( $op ) {
			case 'variant_save':
				$positions = array();
				foreach ( (array) ( $post['position'] ?? array() ) as $question => $position ) {
					$position = trim( (string) $position );
					if ( '' === $position || '0' === $position ) {
						continue;
					}
					if ( ! ctype_digit( $position ) || (int) $position > 1000 ) {
						return array( __( 'Enter the positions as whole numbers from 1 to 1000, or leave the field empty.', 'course-captain' ) );
					}
					$positions[ (int) $question ] = (int) $position;
				}
				// Stable order: position, then the order of the list.
				$keys = array_keys( $positions );
				uksort( $positions, static fn( int $a, int $b ): int => array( $positions[ $a ], array_search( $a, $keys, true ) ) <=> array( $positions[ $b ], array_search( $b, $keys, true ) ) );
				$rules->set_variant_questions( $actor, $variant, array_keys( $positions ) );
				AuditLog::write( $db, $actor, 'exam_variant_saved', 'exam_variant', (string) $variant, null, array( 'questions' => count( $positions ) ) );
				return array();
			case 'variant_freeze':
				$blockers = $rules->variant_blockers( $variant );
				if ( array() !== $blockers ) {
					return array( in_array( 'empty', $blockers, true ) ? __( 'Choose at least one question for the exam form first.', 'course-captain' ) : __( 'The exam form contains a question that is not published. Publish it or remove it from the form.', 'course-captain' ) );
				}
				$rules->freeze_variant( $actor, $variant );
				AuditLog::write( $db, $actor, 'exam_variant_frozen', 'exam_variant', (string) $variant );
				return array();
			default:
				if ( empty( $post['confirm'] ) ) {
					throw new CourseException( 'ccap_confirmation_required', 'confirm', 422 );
				}
				$rules->retire_variant( $actor, $variant );
				AuditLog::write( $db, $actor, 'exam_variant_retired', 'exam_variant', (string) $variant );
				return array();
		}
	}

	/**
	 * Choice "random draw" / "fixed exam forms".
	 *
	 * @param string $mode Current mode.
	 * @return string
	 */
	private static function form_mode_field( string $mode ): string {
		$html  = '<fieldset><legend><strong>' . esc_html__( 'How questions are chosen', 'course-captain' ) . '</strong></legend>';
		$html .= '<p><label><input type="radio" name="form_mode" value="random"' . checked( 'variants' !== $mode, true, false ) . '> ' . esc_html__( 'Random draw: every attempt draws new questions per module', 'course-captain' ) . '</label><br>';
		$html .= '<label><input type="radio" name="form_mode" value="variants"' . checked( 'variants', $mode, false ) . ' aria-describedby="ccap-ex-mode-help"> ' . esc_html__( 'Fixed exam forms: learners choose one of the frozen exam forms of the course', 'course-captain' ) . '</label></p>';
		return $html . '<p id="ccap-ex-mode-help" class="description">' . esc_html__( 'Fixed exam forms are managed below the exam, in the section "Fixed exam forms".', 'course-captain' ) . '</p></fieldset>';
	}

	/**
	 * Fixed exam forms of the course with their questions and actions.
	 *
	 * @param array<string, mixed> $rule    Rule.
	 * @param int                  $rule_id Rule.
	 * @return string
	 */
	private static function variants_section( array $rule, int $rule_id ): string {
		$course     = (int) $rule['course_id'];
		$variants   = ExamRules::current()->variants( $course );
		$candidates = ExamRules::variant_candidates( $course );
		$html       = '<h3 id="ccap-variants">' . esc_html__( 'Fixed exam forms', 'course-captain' ) . '</h3>';
		$html      .= '<p class="description">' . esc_html__( 'A fixed exam form is a list of questions in a fixed order. It belongs to the course and is used by exams set to "fixed exam forms". A frozen form never changes; retire it to stop new attempts. Attempts keep the questions they started with.', 'course-captain' ) . '</p>';
		if ( 'variants' !== ExamRules::form_mode( $rule ) ) {
			$html .= '<p>' . esc_html__( 'This exam draws questions at random; fixed exam forms are not offered to learners.', 'course-captain' ) . '</p>';
		}
		if ( array() === $variants ) {
			$html .= '<p>' . esc_html__( 'No exam forms yet.', 'course-captain' ) . '</p>';
		}
		$hidden = '<input type="hidden" name="rule" value="' . (int) $rule_id . '">';
		foreach ( $variants as $variant ) {
			$id    = $variant['id'];
			$count = count( $variant['questions'] );
			$html .= '<section class="ccap-variant" aria-labelledby="ccap-variant-' . $id . '"><h4 id="ccap-variant-' . $id . '">' . esc_html( sprintf( /* translators: 1: exam form number, 2: status */ __( 'Exam form %1$d (%2$s)', 'course-captain' ), $variant['variant_no'], self::variant_status_label( $variant['status'] ) ) ) . '</h4>';
			$html .= '<p>' . esc_html( sprintf( /* translators: %d: number of questions */ _n( '%d question', '%d questions', $count, 'course-captain' ), $count ) ) . '</p>';
			if ( 'draft' !== $variant['status'] ) {
				$html .= array() === $variant['questions'] ? '' : '<ol>' . implode( '', array_map( static fn( int $q ): string => '<li>' . esc_html( self::question_label( $q ) ) . '</li>', $variant['questions'] ) ) . '</ol>';
				if ( 'published' === $variant['status'] ) {
					$html .= AdminUi::form( self::ACTION, 'variant_retire', $id, $hidden . '<p><label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Confirm: retire this exam form (no new attempts; results stay)', 'course-captain' ) . '</label></p>', sprintf( /* translators: %d: exam form number */ __( 'Retire exam form %d', 'course-captain' ), $variant['variant_no'] ) );
				}
				$html .= '</section>';
				continue;
			}
			$order = array_flip( $variant['questions'] );
			/* translators: %d: exam form number */
			$caption = sprintf( __( 'Questions of exam form %d', 'course-captain' ), $variant['variant_no'] );
			$fields  = $hidden . '<table class="widefat"><caption class="screen-reader-text">' . esc_html( $caption ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Question', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Module', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Position in the form', 'course-captain' ) . '</th></tr></thead><tbody>';
			if ( array() === $candidates ) {
				$fields .= '<tr><td colspan="3">' . esc_html__( 'The modules of the course have no published questions yet.', 'course-captain' ) . '</td></tr>';
			}
			foreach ( $candidates as $question => $module ) {
				$label   = self::question_label( $question );
				$field   = 'ccap-vq-' . $id . '-' . $question;
				$fields .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) get_the_title( $module ) ) . '</td><td><label class="screen-reader-text" for="' . esc_attr( $field ) . '">' . esc_html( sprintf( /* translators: %s: question text */ __( 'Position of: %s', 'course-captain' ), $label ) ) . '</label><input id="' . esc_attr( $field ) . '" type="number" min="0" max="1000" step="1" name="position[' . (int) $question . ']" value="' . esc_attr( isset( $order[ $question ] ) ? (string) ( $order[ $question ] + 1 ) : '' ) . '"></td></tr>';
			}
			$fields .= '</tbody></table><p class="description">' . esc_html__( 'Enter 1 for the first question, 2 for the second and so on. Leave the field empty to leave a question out.', 'course-captain' ) . '</p>';
			$html   .= AdminUi::form( self::ACTION, 'variant_save', $id, $fields, sprintf( /* translators: %d: exam form number */ __( 'Save exam form %d', 'course-captain' ), $variant['variant_no'] ) );
			$html   .= AdminUi::form( self::ACTION, 'variant_freeze', $id, $hidden . '<p class="description">' . esc_html__( 'Freezing makes the exam form available to learners. It cannot be changed afterwards.', 'course-captain' ) . '</p>', sprintf( /* translators: %d: exam form number */ __( 'Freeze exam form %d', 'course-captain' ), $variant['variant_no'] ) );
			$html   .= AdminUi::form( self::ACTION, 'variant_retire', $id, $hidden . '<p><label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Confirm: discard this draft exam form', 'course-captain' ) . '</label></p>', sprintf( /* translators: %d: exam form number */ __( 'Discard exam form %d', 'course-captain' ), $variant['variant_no'] ) ) . '</section>';
		}
		return $html . AdminUi::form( self::ACTION, 'variant_create', $rule_id, '', __( 'Create exam form', 'course-captain' ) );
	}

	/**
	 * Short text of a question (current revision).
	 *
	 * @param int $question Question.
	 * @return string
	 */
	private static function question_label( int $question ): string {
		$db   = Database::current();
		$text = (string) $db->scalar( $db->prepare( 'SELECT r.question_text FROM `' . $db->table( 'ccap_question' ) . '` q JOIN `' . $db->table( 'ccap_question_revision' ) . '` r ON r.id = q.current_revision_id WHERE q.id = %d', $question ) );
		$text = mb_substr( trim( wp_strip_all_tags( $text ) ), 0, 90 );
		/* translators: %d: question number */
		return '' === $text ? sprintf( __( 'Question %d', 'course-captain' ), $question ) : $text;
	}

	/**
	 * Status label of an exam form.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function variant_status_label( string $status ): string {
		$labels = array(
			'draft'     => __( 'draft', 'course-captain' ),
			'published' => __( 'frozen', 'course-captain' ),
			'retired'   => __( 'retired', 'course-captain' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'draft'     => __( 'draft', 'course-captain' ),
			'published' => __( 'published', 'course-captain' ),
			'retired'   => __( 'retired', 'course-captain' ),
		);
		return $labels[ $status ] ?? $status;
	}
}
