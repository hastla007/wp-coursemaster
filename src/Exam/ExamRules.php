<?php
/**
 * Exam rules, blueprints and fixed variants (R0-08 §4, §7; R0-06 §4.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Exam;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * A rule has a mandatory passing rule (no default, INV-C-132) and the
 * status draft → published → retired; a published rule never changes,
 * a change is a new version. Settings: `purpose` (evidence|practice),
 * `attempt_policy`, `feedback`, `shuffle_questions`, `shuffle_answers`,
 * `grace_seconds`, `late_tolerance_seconds`, `timer_mode`. The explanation
 * switch is a Pro setting and not part of the Free defaults (R0-39 G-12).
 * Register-backed settings inherit global → rule (R1-33): drafts resolve
 * live, publication materializes `{v, src}`, locks act from the next start.
 */
final class ExamRules {

	public const SETTINGS_FORMAT = 2;

	/**
	 * Register keys backed by a rule setting (R0-28 §1.4 #9–#18) → rule
	 * path; the time limit lives in the column `time_limit_seconds`.
	 *
	 * @var array<string, string>
	 */
	public const REGISTER_MAP = array(
		'exams.max_attempts'              => 'attempt_policy.max_attempts',
		'exams.cooldown_seconds'          => 'attempt_policy.cooldown_seconds',
		'exams.count_cancelled'           => 'attempt_policy.count_cancelled',
		'exams.time_limit_minutes'        => '',
		'exams.timer_mode'                => 'timer_mode',
		'exams.late_tolerance_seconds'    => 'late_tolerance_seconds',
		'exams.shuffle_questions'         => 'shuffle_questions',
		'exams.shuffle_answers'           => 'shuffle_answers',
		'exams.feedback'                  => 'feedback',
		'exams.auto_submit_inactive_days' => 'auto_submit_inactive_days',
	);

	/**
	 * Constructor.
	 *
	 * @param Database      $db     Database access.
	 * @param PolicyService $policy Policy.
	 */
	public function __construct( private Database $db, private PolicyService $policy ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), PolicyService::instance() );
	}

	/**
	 * Full settings shape with code defaults; register-backed values are
	 * replaced by the resolver (R0-28), rule-only values are stored.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'purpose'                   => 'evidence',
			'attempt_policy'            => array(
				'max_attempts'     => null,
				'cooldown_seconds' => 0,
				'count_cancelled'  => true,
			),
			'feedback'                  => array(
				'feedback_visibility'       => 'after_submit',
				'show_score'                => true,
				'show_correct_answers'      => true,
				'show_correct_answers_when' => 'no_attempts_left_or_passed',
			),
			'shuffle_questions'         => 'within_section',
			'shuffle_answers'           => true,
			'grace_seconds'             => 5,
			'late_tolerance_seconds'    => 120,
			'timer_mode'                => 'strict',
			'open_text_in_score'        => true,
			'certifies_objectives'      => array(),
			'competency_validity_days'  => null,
			'auto_submit_inactive_days' => null,
		);
	}

	/**
	 * Rule-only settings without a register key (stored explicitly).
	 *
	 * @return array<string, mixed>
	 */
	public static function own_defaults(): array {
		return array_intersect_key( self::default_settings(), array_flip( array( 'purpose', 'grace_seconds', 'open_text_in_score', 'certifies_objectives', 'competency_validity_days' ) ) );
	}

	/**
	 * Create a draft rule. Register-backed settings (R0-28 §1.4 #9–#18)
	 * are stored as overrides only when given; missing ones inherit the
	 * global value until publication (E-227). A time limit in seconds is
	 * an explicit override; `overrides` may name register keys directly
	 * (e.g. `exams.time_limit_minutes => null` = explicitly unlimited).
	 *
	 * @param Actor                $actor        Actor (M-06).
	 * @param int                  $course_id    Course.
	 * @param string               $version      Version label.
	 * @param array<string, mixed> $passing_rule `min_ratio` {num, den}, optional `parts`.
	 * @param int|null             $time_limit   Seconds or null (inherit).
	 * @param array<string, mixed> $settings     Settings (legacy shape and/or `overrides`).
	 * @return int Rule ID.
	 * @throws CourseException On invalid data or missing rights.
	 */
	public function create( Actor $actor, int $course_id, string $version, array $passing_rule, ?int $time_limit = null, array $settings = array() ): int {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_COURSE, array( $course_id ) ), 'Not allowed to edit this course.' );
		$errors = self::validate_passing_rule( $passing_rule );
		if ( array() !== $errors || '' === trim( $version ) || ( null !== $time_limit && $time_limit < 1 ) ) {
			throw new CourseException( 'ccap_validation_failed', esc_html( implode( ',', array_merge( $errors, array( 'rule' ) ) ) ), 422 );
		}
		$overrides = self::validated_overrides( self::extract_overrides( $settings ) );
		return $this->db->insert(
			'ccap_exam_rule',
			array(
				'uid'                => $this->db->uuid(),
				'course_id'          => $course_id,
				'version'            => $version,
				'status'             => 'draft',
				'time_limit_seconds' => $time_limit,
				'passing_rule'       => (string) wp_json_encode( $passing_rule ),
				'settings'           => (string) wp_json_encode( self::stored_settings( $settings, $overrides ) ),
			),
			array( 'created_at', 'updated_at' )
		);
	}

	/**
	 * Change overrides of a draft rule; `inherit` removes one (§4.1).
	 * `exams.time_limit_minutes` replaces the explicit time limit.
	 *
	 * @param Actor                $actor   Actor (M-06).
	 * @param int                  $rule_id Draft rule.
	 * @param array<string, mixed> $changes Register key → value or `inherit`.
	 * @return array<string, mixed> Overrides after the change.
	 * @throws CourseException `ccap_rule_frozen`, `ccap_validation_failed`, `ccap_setting_locked`.
	 */
	public function set_overrides( Actor $actor, int $rule_id, array $changes ): array {
		$rule   = $this->editable( $actor, $rule_id );
		$stored = (array) $rule['settings_stored'];
		if ( self::SETTINGS_FORMAT !== (int) ( $stored['_format'] ?? 0 ) ) {
			// Draft from before R1-33: every stored value counts as explicit.
			$stored = array(
				'_format'   => self::SETTINGS_FORMAT,
				'overrides' => self::extract_overrides( array_replace_recursive( self::default_settings(), $stored ) ),
			) + array_intersect_key( array_replace_recursive( self::own_defaults(), $stored ), self::own_defaults() );
		}
		$overrides = (array) ( $stored['overrides'] ?? array() );
		$column    = $rule['time_limit_column'];
		$set       = array();
		foreach ( $changes as $key => $value ) {
			$key = (string) $key;
			if ( ! array_key_exists( $key, self::REGISTER_MAP ) ) {
				throw new CourseException( 'ccap_validation_failed', esc_html( $key ), 422 );
			}
			if ( 'exams.time_limit_minutes' === $key ) {
				$column = null;
			}
			if ( EffectiveSettings::INHERIT === $value ) {
				unset( $overrides[ $key ] );
				continue;
			}
			$set[ $key ] = $value;
		}
		$overrides = array_replace( $overrides, self::validated_overrides( $set ) );
		ksort( $overrides );
		$stored['overrides'] = $overrides;
		$table               = $this->db->table( 'ccap_exam_rule' );
		$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET settings = %s, time_limit_seconds = " . ( null === $column ? 'NULL' : (int) $column ) . ', updated_at = %s WHERE id = %d', (string) wp_json_encode( $stored ), $this->db->now(), $rule_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer cast.
		return $overrides;
	}

	/**
	 * Panel "Effective settings" of a rule (§1.7): value, source, class.
	 *
	 * @param int $rule_id Rule.
	 * @return list<array{key: string, label: string, value: mixed, source: string, locked: bool, resting: bool, overridden: bool, applies: string}>
	 */
	public function panel( int $rule_id ): array {
		$rule = $this->get( $rule_id );
		if ( null === $rule ) {
			return array();
		}
		$labels = EffectiveSettings::labels();
		$own    = (array) ( $rule['settings_stored']['overrides'] ?? array() );
		$rows   = array();
		foreach ( (array) $rule['settings_provenance'] as $key => $entry ) {
			$rows[] = array(
				'key'        => (string) $key,
				'label'      => $labels[ $key ] ?? (string) $key,
				'value'      => $entry['v'],
				'source'     => (string) $entry['src'],
				'locked'     => 'global_lock' === $entry['src'],
				'resting'    => 'global_lock' === $entry['src'] && ( array_key_exists( $key, $own ) || ( 'exams.time_limit_minutes' === $key && null !== $rule['time_limit_column'] ) ),
				'overridden' => array_key_exists( $key, $own ) || ( 'exams.time_limit_minutes' === $key && null !== $rule['time_limit_column'] ),
				'applies'    => 'draft' === $rule['status'] ? __( 'applies when the exam is published', 'course-captain' ) : EffectiveSettings::class_label( 'R' ),
			);
		}
		return $rows;
	}

	/**
	 * Add or replace a blueprint section (module → question count, points).
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $rule_id   Draft rule.
	 * @param int   $module_id Module of the course.
	 * @param int   $count     Questions drawn.
	 * @param int   $points    Points per question 1–100.
	 * @return void
	 * @throws CourseException For published rules or invalid input.
	 */
	public function set_blueprint( Actor $actor, int $rule_id, int $module_id, int $count, int $points = 1 ): void {
		$rule = $this->editable( $actor, $rule_id );
		if ( $count < 1 || $points < 1 || $points > 100 || ! in_array( (int) $rule['course_id'], ( new \CourseCaptain\Policy\Relations( $this->db ) )->courses_of_module( $module_id ), true ) ) {
			throw new CourseException( 'ccap_validation_failed', 'Invalid blueprint.', 422 );
		}
		$table = $this->db->table( 'ccap_exam_blueprint' );
		$this->db->execute(
			$this->db->prepare(
				"INSERT INTO `{$table}` (rule_id, module_id, question_count, rules) VALUES (%d, %d, %d, %s) ON DUPLICATE KEY UPDATE question_count = VALUES(question_count), rules = VALUES(rules)",
				$rule_id,
				$module_id,
				$count,
				(string) wp_json_encode( array( 'points' => $points ) )
			)
		);
	}

	/**
	 * Create a fixed variant of a course with an ordered question list.
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $course_id Course.
	 * @param int   $number    Variant number.
	 * @param array $questions Question IDs in position order.
	 * @phpstan-param list<int> $questions
	 * @return int Variant ID.
	 * @throws CourseException When not allowed or empty.
	 */
	public function create_variant( Actor $actor, int $course_id, int $number, array $questions ): int {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_COURSE, array( $course_id ) ), 'Not allowed to edit this course.' );
		if ( array() === $questions ) {
			throw new CourseException( 'ccap_validation_failed', 'A variant needs questions.', 422 );
		}
		return $this->db->transaction(
			function ( Database $db ) use ( $course_id, $number, $questions ): int {
				$variant = $db->insert(
					'ccap_exam_variant',
					array(
						'uid'        => $db->uuid(),
						'course_id'  => $course_id,
						'variant_no' => $number,
						'status'     => 'published',
					),
					array( 'created_at', 'updated_at' )
				);
				foreach ( array_values( $questions ) as $i => $question ) {
					$db->insert(
						'ccap_exam_variant_question',
						array(
							'variant_id'  => $variant,
							'question_id' => (int) $question,
							'position'    => $i + 1,
						)
					);
				}
				return $variant;
			}
		);
	}

	/**
	 * Publish a draft rule; the previous published version of the same
	 * course becomes `retired`.
	 *
	 * @param Actor $actor   Actor (M-04 on the course).
	 * @param int   $rule_id Rule.
	 * @return void
	 * @throws CourseException When not allowed or invalid.
	 */
	public function publish( Actor $actor, int $rule_id ): void {
		$rule = $this->editable( $actor, $rule_id );
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::PUBLISH_COURSE, array( (int) $rule['course_id'] ) ), 'Not allowed to publish.' );
		// Materialize every register value with its source (R0-28 §2.1, E-227); locks act live at start (E-225).
		$resolved = self::resolve_settings( (array) $rule['settings_stored'], $rule['time_limit_column'], 'draft', true )['provenance'];
		$stored   = (array) $rule['settings_stored'];
		if ( 'practice' !== ( $stored['purpose'] ?? 'evidence' ) && 'practice' === ( $resolved['exams.timer_mode']['v'] ?? 'strict' ) ) {
			// INH-018: a practice timer is never evidence-eligible.
			throw new CourseException( 'ccap_validation_failed', 'timer_mode', 422 );
		}
		$stored['resolved'] = $resolved;
		$limit              = self::seconds( $resolved['exams.time_limit_minutes']['v'] ?? null );
		$table              = $this->db->table( 'ccap_exam_rule' );
		$this->db->transaction(
			function ( Database $db ) use ( $table, $rule, $rule_id, $actor, $stored, $limit ): void {
				$db->execute( $db->prepare( "UPDATE `{$table}` SET status = 'retired', updated_at = %s WHERE course_id = %d AND status = 'published' AND id <> %d", $db->now(), (int) $rule['course_id'], $rule_id ) );
				$db->execute( $db->prepare( "UPDATE `{$table}` SET status = 'published', settings = %s, time_limit_seconds = " . ( null === $limit ? 'NULL' : (int) $limit ) . ", published_by = %d, published_at = %s, updated_at = %s WHERE id = %d AND status = 'draft'", (string) wp_json_encode( $stored ), $actor->user_id, $db->now(), $db->now(), $rule_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer cast.
			}
		);
	}

	/**
	 * Rule row with decoded JSON.
	 *
	 * @param int $rule_id Rule.
	 * @return array<string, mixed>|null
	 */
	public function get( int $rule_id ): ?array {
		$row = $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_exam_rule' ) . '` WHERE id = %d', $rule_id ) );
		if ( null === $row ) {
			return null;
		}
		$passing                    = json_decode( (string) $row['passing_rule'], true );
		$settings                   = json_decode( (string) $row['settings'], true );
		$settings                   = is_array( $settings ) ? $settings : array();
		$column                     = null === $row['time_limit_seconds'] ? null : (int) $row['time_limit_seconds'];
		$effective                  = self::resolve_settings( $settings, $column, (string) $row['status'] );
		$row['passing_rule']        = is_array( $passing ) ? $passing : null;
		$row['settings_stored']     = $settings;
		$row['time_limit_column']   = $column;
		$row['settings']            = $effective['settings'];
		$row['time_limit_seconds']  = $effective['time_limit_seconds'];
		$row['settings_provenance'] = $effective['provenance'];
		$row['blueprints']          = $this->db->rows( $this->db->prepare( 'SELECT module_id, question_count, rules FROM `' . $this->db->table( 'ccap_exam_blueprint' ) . '` WHERE rule_id = %d ORDER BY id', $rule_id ) );
		return $row;
	}

	/**
	 * Effective settings of a stored rule (R0-28 §1.3 class R).
	 *
	 * Drafts resolve live (overrides → global); published rules use their
	 * materialized `resolved` block; rules stored before R1-33 (no
	 * `_format`) count as fully explicit. Locks replace values live unless
	 * `$ignore_locks` (materialization).
	 *
	 * @param array<string, mixed> $stored       Stored settings JSON.
	 * @param int|null             $column       Stored time limit (seconds).
	 * @param string               $status       Rule status.
	 * @param bool                 $ignore_locks Skip locks.
	 * @return array{settings: array<string, mixed>, time_limit_seconds: int|null, provenance: array<string, array{v: mixed, src: string}>}
	 */
	public static function resolve_settings( array $stored, ?int $column, string $status, bool $ignore_locks = false ): array {
		$legacy  = self::SETTINGS_FORMAT !== (int) ( $stored['_format'] ?? 0 );
		$context = array( 'ignore_locks' => $ignore_locks );
		if ( $legacy ) {
			$full     = array_replace_recursive( self::default_settings(), $stored );
			$resolved = array();
			foreach ( self::REGISTER_MAP as $key => $path ) {
				$resolved[ $key ] = array(
					'v'   => 'exams.time_limit_minutes' === $key ? self::minutes( $column ) : self::read_path( $full, $key, $path ),
					'src' => 'exam_rule',
				);
			}
			$context['rule'] = array( 'resolved' => $resolved );
		} elseif ( 'draft' !== $status && is_array( $stored['resolved'] ?? null ) ) {
			$context['rule'] = array( 'resolved' => $stored['resolved'] );
		} else {
			$overrides = (array) ( $stored['overrides'] ?? array() );
			if ( null !== $column ) {
				$overrides['exams.time_limit_minutes'] = self::minutes( $column );
			}
			$context['rule'] = array( 'overrides' => $overrides );
		}
		$settings   = array_replace_recursive( self::default_settings(), $legacy ? $stored : array_intersect_key( $stored, self::own_defaults() ) );
		$provenance = array();
		$limit      = null;
		foreach ( self::REGISTER_MAP as $key => $path ) {
			$r                  = EffectiveSettings::resolve( $key, $context );
			$provenance[ $key ] = array(
				'v'   => $r['value'],
				'src' => $r['source'],
			);
			if ( 'exams.time_limit_minutes' === $key ) {
				$limit = self::seconds( $r['value'] );
				continue;
			}
			$settings = self::write_path( $settings, $key, $path, $r['value'] );
		}
		// C-6: inactivity submission only without a time limit.
		if ( null !== $limit && null !== ( $settings['auto_submit_inactive_days'] ?? null ) ) {
			$settings['auto_submit_inactive_days']              = null;
			$provenance['exams.auto_submit_inactive_days']['v'] = null;
		}
		ksort( $provenance );
		return array(
			'settings'           => $settings,
			'time_limit_seconds' => $limit,
			'provenance'         => $provenance,
		);
	}

	/**
	 * Register overrides contained in submitted settings (legacy shape or `overrides`).
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	private static function extract_overrides( array $settings ): array {
		$out = array();
		foreach ( self::REGISTER_MAP as $key => $path ) {
			if ( '' === $path ) {
				continue;
			}
			if ( 'exams.feedback' === $key ) {
				if ( isset( $settings['feedback'] ) && is_array( $settings['feedback'] ) ) {
					$leaves = array();
					foreach ( $settings['feedback'] as $leaf => $value ) {
						$leaves[ 'feedback_visibility' === $leaf ? 'visibility' : (string) $leaf ] = $value;
					}
					$out[ $key ] = $leaves;
				}
				continue;
			}
			$parts = explode( '.', $path );
			$node  = $settings;
			$found = true;
			foreach ( $parts as $part ) {
				if ( ! is_array( $node ) || ! array_key_exists( $part, $node ) ) {
					$found = false;
					break;
				}
				$node = $node[ $part ];
			}
			if ( $found ) {
				$out[ $key ] = $node;
			}
		}
		foreach ( (array) ( $settings['overrides'] ?? array() ) as $key => $value ) {
			$out[ (string) $key ] = $value;
		}
		return $out;
	}

	/**
	 * Validate rule overrides (scope `exam_rule`, locks refused).
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return array<string, mixed>
	 * @throws CourseException `ccap_validation_failed`, `ccap_setting_locked`.
	 */
	private static function validated_overrides( array $overrides ): array {
		$out    = array();
		$errors = array();
		$locks  = EffectiveSettings::locks();
		foreach ( $overrides as $key => $value ) {
			$check = EffectiveSettings::validate_override( (string) $key, $value, 'exam_rule' );
			if ( ! $check['ok'] ) {
				$errors[] = (string) $key;
				continue;
			}
			if ( isset( $locks[ $key ] ) ) {
				throw new CourseException( 'ccap_setting_locked', esc_html( (string) $key ), 422 );
			}
			$out[ (string) $key ] = $check['value'];
		}
		if ( array() !== $errors ) {
			throw new CourseException( 'ccap_validation_failed', esc_html( implode( ',', $errors ) ), 422 );
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Stored settings of a new draft.
	 *
	 * @param array<string, mixed> $settings  Submitted settings.
	 * @param array<string, mixed> $overrides Validated overrides.
	 * @return array<string, mixed>
	 */
	private static function stored_settings( array $settings, array $overrides ): array {
		$own = array_replace_recursive( self::own_defaults(), array_intersect_key( $settings, self::own_defaults() ) );
		return array(
			'_format'   => self::SETTINGS_FORMAT,
			'overrides' => (object) $overrides,
		) + $own;
	}

	/**
	 * Value at a rule path (feedback renames `visibility`).
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $key      Register key.
	 * @param string               $path     Rule path.
	 * @return mixed
	 */
	private static function read_path( array $settings, string $key, string $path ) {
		if ( 'exams.feedback' === $key ) {
			$feedback = (array) ( $settings['feedback'] ?? array() );
			$out      = array();
			foreach ( $feedback as $leaf => $value ) {
				$out[ 'feedback_visibility' === $leaf ? 'visibility' : (string) $leaf ] = $value;
			}
			return $out;
		}
		$node = $settings;
		foreach ( explode( '.', $path ) as $part ) {
			$node = is_array( $node ) ? ( $node[ $part ] ?? null ) : null;
		}
		return $node;
	}

	/**
	 * Write a value at a rule path (feedback renames `visibility`).
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $key      Register key.
	 * @param string               $path     Rule path.
	 * @param mixed                $value    Value.
	 * @return array<string, mixed>
	 */
	private static function write_path( array $settings, string $key, string $path, $value ): array {
		if ( 'exams.feedback' === $key ) {
			$feedback = array();
			foreach ( (array) $value as $leaf => $v ) {
				$feedback[ 'visibility' === $leaf ? 'feedback_visibility' : (string) $leaf ] = $v;
			}
			$settings['feedback'] = array_replace( (array) ( $settings['feedback'] ?? array() ), $feedback );
			return $settings;
		}
		$parts = explode( '.', $path );
		if ( 2 === count( $parts ) ) {
			$settings[ $parts[0] ]              = (array) ( $settings[ $parts[0] ] ?? array() );
			$settings[ $parts[0] ][ $parts[1] ] = $value;
			return $settings;
		}
		$settings[ $path ] = $value;
		return $settings;
	}

	/**
	 * Minutes (register unit) of a time limit in seconds.
	 *
	 * @param int|null $seconds Seconds.
	 * @return int|float|null
	 */
	private static function minutes( ?int $seconds ) {
		if ( null === $seconds ) {
			return null;
		}
		return 0 === $seconds % 60 ? intdiv( $seconds, 60 ) : round( $seconds / 60, 4 );
	}

	/**
	 * Seconds of a register time limit (minutes).
	 *
	 * @param mixed $minutes Minutes or null.
	 * @return int|null
	 */
	private static function seconds( $minutes ): ?int {
		return is_int( $minutes ) || is_float( $minutes ) ? (int) round( $minutes * 60 ) : null;
	}

	/**
	 * Passing rule validation (no default value exists).
	 *
	 * @param mixed $rule Passing rule.
	 * @return list<string>
	 */
	public static function validate_passing_rule( $rule ): array {
		if ( ! is_array( $rule ) || ! isset( $rule['min_ratio']['num'], $rule['min_ratio']['den'] ) ) {
			return array( 'passing_rule_missing' );
		}
		$num = $rule['min_ratio']['num'];
		$den = $rule['min_ratio']['den'];
		if ( ! is_int( $num ) || ! is_int( $den ) || $num <= 0 || $den <= 0 || $num > $den ) {
			return array( 'passing_rule_ratio' );
		}
		return array();
	}

	/**
	 * Draft rule the actor may edit.
	 *
	 * @param Actor $actor   Actor.
	 * @param int   $rule_id Rule.
	 * @return array<string, mixed>
	 * @throws CourseException For unknown or non-draft rules.
	 */
	private function editable( Actor $actor, int $rule_id ): array {
		$rule = $this->get( $rule_id );
		if ( null === $rule ) {
			throw new CourseException( 'ccap_not_found', 'Unknown rule.', 404 );
		}
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_COURSE_ITEM, array( $rule_id, 'exam_rule' ) ), 'Not allowed to edit this rule.' );
		if ( 'draft' !== $rule['status'] ) {
			throw new CourseException( 'ccap_rule_frozen', 'Published rules never change; create a new version.', 409 );
		}
		return $rule;
	}
}
