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
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * A rule has a mandatory passing rule (no default, INV-C-132) and the
 * status draft → published → retired; a published rule never changes,
 * a change is a new version. Settings: `purpose` (evidence|practice),
 * `attempt_policy`, `feedback`, `shuffle_questions`, `shuffle_answers`,
 * `grace_seconds`, `late_tolerance_seconds`, `timer_mode`. The explanation
 * switch is a Pro setting and not part of the Free defaults (R0-39 G-12).
 */
final class ExamRules {

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
	 * Default settings (explicitly stored at creation).
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'purpose'                  => 'evidence',
			'attempt_policy'           => array(
				'max_attempts'     => null,
				'cooldown_seconds' => 0,
				'count_cancelled'  => true,
			),
			'feedback'                 => array(
				'feedback_visibility'       => 'after_submit',
				'show_score'                => true,
				'show_correct_answers'      => true,
				'show_correct_answers_when' => 'no_attempts_left_or_passed',
			),
			'shuffle_questions'        => 'within_section',
			'shuffle_answers'          => true,
			'grace_seconds'            => 5,
			'late_tolerance_seconds'   => 120,
			'timer_mode'               => 'strict',
			'open_text_in_score'       => true,
			'certifies_objectives'     => array(),
			'competency_validity_days' => null,
		);
	}

	/**
	 * Create a draft rule.
	 *
	 * @param Actor                $actor        Actor (M-06).
	 * @param int                  $course_id    Course.
	 * @param string               $version      Version label.
	 * @param array<string, mixed> $passing_rule `min_ratio` {num, den}, optional `parts`.
	 * @param int|null             $time_limit   Seconds or null.
	 * @param array<string, mixed> $settings     Settings (merged over defaults).
	 * @return int Rule ID.
	 * @throws CourseException On invalid data or missing rights.
	 */
	public function create( Actor $actor, int $course_id, string $version, array $passing_rule, ?int $time_limit = null, array $settings = array() ): int {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_COURSE, array( $course_id ) ), 'Not allowed to edit this course.' );
		$errors = self::validate_passing_rule( $passing_rule );
		if ( array() !== $errors || '' === trim( $version ) || ( null !== $time_limit && $time_limit < 1 ) ) {
			throw new CourseException( 'ccap_validation_failed', esc_html( implode( ',', array_merge( $errors, array( 'rule' ) ) ) ), 422 );
		}
		return $this->db->insert(
			'ccap_exam_rule',
			array(
				'uid'                => $this->db->uuid(),
				'course_id'          => $course_id,
				'version'            => $version,
				'status'             => 'draft',
				'time_limit_seconds' => $time_limit,
				'passing_rule'       => (string) wp_json_encode( $passing_rule ),
				'settings'           => (string) wp_json_encode( array_replace_recursive( self::default_settings(), $settings ) ),
			),
			array( 'created_at', 'updated_at' )
		);
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
		$table = $this->db->table( 'ccap_exam_rule' );
		$this->db->transaction(
			function ( Database $db ) use ( $table, $rule, $rule_id, $actor ): void {
				$db->execute( $db->prepare( "UPDATE `{$table}` SET status = 'retired', updated_at = %s WHERE course_id = %d AND status = 'published' AND id <> %d", $db->now(), (int) $rule['course_id'], $rule_id ) );
				$db->execute( $db->prepare( "UPDATE `{$table}` SET status = 'published', published_by = %d, published_at = %s, updated_at = %s WHERE id = %d AND status = 'draft'", $actor->user_id, $db->now(), $db->now(), $rule_id ) );
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
		$passing             = json_decode( (string) $row['passing_rule'], true );
		$settings            = json_decode( (string) $row['settings'], true );
		$row['passing_rule'] = is_array( $passing ) ? $passing : null;
		$row['settings']     = is_array( $settings ) ? array_replace_recursive( self::default_settings(), $settings ) : self::default_settings();
		$row['blueprints']   = $this->db->rows( $this->db->prepare( 'SELECT module_id, question_count, rules FROM `' . $this->db->table( 'ccap_exam_blueprint' ) . '` WHERE rule_id = %d ORDER BY id', $rule_id ) );
		return $row;
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
