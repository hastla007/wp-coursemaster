<?php
/**
 * Course publish validator (R0-22 §1, ValidationReport §1.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Publish;

use CourseCaptain\Activity\ActivityRegistry;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Deterministic, server-side rules with stable codes `VAL-nnn`; the
 * candidate is the live draft structure. Blockers prevent publishing
 * (no override); warnings need an acknowledgement per fingerprint; notices
 * are informational. An exception inside a rule is the blocker `VAL-000`.
 * Messages name object, problem and next step without internal IDs.
 */
final class CourseValidator {

	public const VERSION = '1.0.0';

	/**
	 * Mapping of question type validation codes to catalog codes.
	 *
	 * @var array<string, string>
	 */
	private const QUESTION_CODES = array(
		'text_missing'         => 'VAL-040',
		'at_least_one_correct' => 'VAL-044',
		'answers_count'        => 'VAL-045',
		'answer_key_invalid'   => 'VAL-045',
		'true_false_shape'     => 'VAL-053',
		'model_answer'         => 'VAL-054',
		'back_missing'         => 'VAL-055',
		'scoring_not_allowed'  => 'VAL-056',
		'target_invalid'       => 'VAL-063',
		'tolerance_invalid'    => 'VAL-063',
		'pairs_count'          => 'VAL-066',
		'pairs_duplicate'      => 'VAL-066',
		'items_count'          => 'VAL-067',
		'items_duplicate'      => 'VAL-067',
		'gaps_invalid'         => 'VAL-068',
		'gap_answers'          => 'VAL-068',
	);

	/**
	 * Issues of the run.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $issues = array();

	/**
	 * Validate a course candidate.
	 *
	 * @param int    $course_id    Course.
	 * @param string $context      live|manual|submit|publish|import_dry_run|health.
	 * @param array  $acknowledged Acknowledged warning fingerprints.
	 * @phpstan-param list<string> $acknowledged
	 * @return array{course_uid: string, context: string, candidate_hash: string, validated_at: string, validator_version: string, summary: array{blocker: int, warning: int, notice: int, acknowledged: int}, issues: list<array<string, mixed>>}
	 */
	public static function validate( int $course_id, string $context = 'manual', array $acknowledged = array() ): array {
		$self = new self();
		$db   = Database::current();
		$post = get_post( $course_id );
		$uid  = $post instanceof \WP_Post ? ( new ObjectIdentity( $db ) )->ensure( $post ) : '';
		$hash = '';
		foreach ( array( 'course', 'structure', 'questions', 'exams', 'prerequisites', 'media' ) as $group ) {
			try {
				switch ( $group ) {
					case 'course':
						$self->course( $course_id, $post );
						break;
					case 'structure':
						$self->structure( $course_id );
						break;
					case 'questions':
						$self->questions( $course_id );
						break;
					case 'exams':
						$self->exams( $course_id );
						break;
					case 'prerequisites':
						$self->prerequisites( $course_id );
						break;
					case 'media':
						$self->alt_texts( $course_id, $post instanceof \WP_Post ? $post : null );
						break;
				}
			} catch ( \Throwable $e ) {
				$self->add( 'VAL-000', 'blocker', 'course', $course_id, (string) ( $post->post_title ?? '' ), 'cc.val.internal', array( 'group' => $group ), __( 'The check could not run completely. Please try again or contact support.', 'course-captain' ) );
			}
		}
		try {
			$hash = 'sha256:' . hash( 'sha256', (string) wp_json_encode( CoursePublisher::current()->build_structure( $course_id ) ) );
			$live = CoursePublisher::current()->current_revision( $course_id );
			if ( null !== $live && $hash === $live['structure_hash'] ) {
				$self->add( 'VAL-008', 'notice', 'course', $course_id, (string) ( $post->post_title ?? '' ), 'cc.val.course.unchanged', array(), __( 'Nothing changed since the published revision.', 'course-captain' ) );
			}
		} catch ( \Throwable $e ) {
			$hash = 'sha256:' . str_repeat( '0', 64 );
		}
		$summary = array(
			'blocker'      => 0,
			'warning'      => 0,
			'notice'       => 0,
			'acknowledged' => 0,
		);
		foreach ( $self->issues as $i => $issue ) {
			if ( 'blocker' === $issue['severity'] ) {
				++$summary['blocker'];
			} elseif ( 'warning' === $issue['severity'] ) {
				++$summary['warning'];
			} else {
				++$summary['notice'];
			}
			if ( 'warning' === $issue['severity'] && in_array( $issue['fingerprint'], $acknowledged, true ) ) {
				$self->issues[ $i ]['acknowledged'] = true;
				++$summary['acknowledged'];
			}
		}
		return array(
			'course_uid'        => $uid,
			'context'           => $context,
			'candidate_hash'    => $hash,
			'validated_at'      => gmdate( 'c' ),
			'validator_version' => self::VERSION,
			'summary'           => $summary,
			'issues'            => $self->issues,
		);
	}

	/**
	 * Whether a report allows publishing (no blockers, all warnings acknowledged).
	 *
	 * @param array<string, mixed> $report Report.
	 * @return bool
	 */
	public static function publishable( array $report ): bool {
		return 0 === $report['summary']['blocker'] && $report['summary']['warning'] === $report['summary']['acknowledged'];
	}

	/**
	 * VAL-001…VAL-006.
	 *
	 * @param int           $course_id Course.
	 * @param \WP_Post|null $post      Post.
	 * @return void
	 */
	private function course( int $course_id, ?\WP_Post $post ): void {
		$title = null === $post ? '' : $post->post_title;
		if ( '' === trim( $title ) ) {
			$this->add( 'VAL-001', 'blocker', 'course', $course_id, $title, 'cc.val.course.title', array(), __( 'The course has no title. Enter a title.', 'course-captain' ), 'post_title' );
		}
		$language = CourseStructure::course_language( $course_id );
		if ( 1 !== preg_match( '/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $language ) ) {
			$this->add( 'VAL-002', 'blocker', 'course', $course_id, $title, 'cc.val.course.language', array( 'language' => $language ), __( 'The course language is missing or invalid. Choose a language.', 'course-captain' ), 'language' );
		}
		if ( array() === CourseStructure::current()->modules( $course_id ) ) {
			$this->add( 'VAL-003', 'blocker', 'course', $course_id, $title, 'cc.val.course.no_modules', array(), __( 'The course has no modules. Add at least one module.', 'course-captain' ) );
		}
		if ( null !== $post && '' === trim( wp_strip_all_tags( $post->post_excerpt . $post->post_content ) ) ) {
			$this->add( 'VAL-006', 'notice', 'course', $course_id, $title, 'cc.val.course.description', array(), __( 'The course has no description. A short description helps learners.', 'course-captain' ), 'post_content' );
		}
	}

	/**
	 * VAL-004, VAL-020…VAL-033.
	 *
	 * @param int $course_id Course.
	 * @return void
	 */
	private function structure( int $course_id ): void {
		$structure = CourseStructure::current();
		$registry  = ActivityRegistry::instance();
		$required  = 0;
		foreach ( $structure->modules( $course_id ) as $link ) {
			$module = get_post( $link['module_id'] );
			if ( ! $module instanceof \WP_Post ) {
				continue;
			}
			$lessons = array_values( array_filter( $structure->lessons( $link['module_id'] ), static fn( \WP_Post $l ): bool => 'publish' === $l->post_status ) );
			$pool    = QuestionRepository::current()->pool( $link['module_id'] );
			if ( '' === trim( $module->post_title ) ) {
				$this->add( 'VAL-021', 'blocker', 'module', (int) $module->ID, '', 'cc.val.module.title', array(), __( 'A module has no title. Enter a title.', 'course-captain' ), 'post_title' );
			}
			if ( array() === $lessons && array() === $pool ) {
				$this->add( 'VAL-020', 'blocker', 'module', (int) $module->ID, $module->post_title, 'cc.val.module.empty', array(), __( 'This module has no lessons and no questions. Add content or remove the module.', 'course-captain' ) );
			}
			$titles = array();
			foreach ( $lessons as $lesson ) {
				$definition = ActivityService::definition( (int) $lesson->ID );
				$type       = $definition['type'];
				if ( $link['required'] ) {
					++$required;
				}
				$key = mb_strtolower( trim( $lesson->post_title ) );
				if ( isset( $titles[ $key ] ) ) {
					$this->add( 'VAL-033', 'notice', 'lesson', (int) $lesson->ID, $lesson->post_title, 'cc.val.lesson.duplicate_title', array(), __( 'Two lessons in this module have the same title.', 'course-captain' ), 'post_title' );
				}
				$titles[ $key ] = true;
				if ( '' === $type ) {
					$this->add( 'VAL-023', 'blocker', 'lesson', (int) $lesson->ID, $lesson->post_title, 'cc.val.lesson.no_activity', array(), __( 'This lesson has no activity type. Choose a type in the lesson editor.', 'course-captain' ), 'activity_type' );
					continue;
				}
				if ( null === $registry->get( $type ) ) {
					$this->add( 'VAL-024', 'blocker', 'activity', (int) $lesson->ID, $lesson->post_title, 'cc.val.activity.provider', array( 'type' => $type ), __( 'The activity type of this lesson is not available. Choose another type or activate the extension.', 'course-captain' ), 'activity_type' );
					continue;
				}
				$errors = $registry->validate_settings( $type, $definition['settings'] );
				if ( array() !== $errors ) {
					$this->add( 'VAL-025', 'blocker', 'activity', (int) $lesson->ID, $lesson->post_title, 'cc.val.activity.settings', array( 'errors' => implode( ',', $errors ) ), __( 'The activity settings of this lesson are invalid. Correct them in the lesson editor.', 'course-captain' ), 'settings' );
				}
				$placeholder = ! empty( $definition['settings']['placeholder'] );
				if ( 'lesson' === $type && '' === trim( wp_strip_all_tags( $lesson->post_content ) ) ) {
					$this->add( 'VAL-026', 'blocker', 'activity', (int) $lesson->ID, $lesson->post_title, 'cc.val.lesson.empty', array(), __( 'This lesson has no content. Write the lesson text.', 'course-captain' ), 'post_content' );
				}
				if ( 'video' === $type && ! $placeholder && '' === (string) ( $definition['settings']['url'] ?? '' ) && array() === \CourseCaptain\Activity\MediaLibrary::current()->media_of( 'lesson', (int) $lesson->ID ) ) {
					$this->add( 'VAL-027', 'blocker', 'activity', (int) $lesson->ID, $lesson->post_title, 'cc.val.video.source', array(), __( 'This video lesson has no video. Add a video file or address.', 'course-captain' ), 'settings.url' );
				}
				// SET-CC-18 (A11Y-090): a video without transcript is reported; with `display.require_transcripts` it blocks.
				if ( 'video' === $type && ! $placeholder && '' === trim( (string) ( $definition['settings']['transcript'] ?? '' ) ) && '' === (string) ( $definition['settings']['transcript_url'] ?? '' ) ) {
					$this->add( 'VAL-144', true === Settings::get( 'display.require_transcripts' ) ? 'blocker' : 'notice', 'activity', (int) $lesson->ID, $lesson->post_title, 'cc.val.video.transcript', array(), __( 'This video lesson has no transcript. Enter the transcript as text or link in the lesson settings.', 'course-captain' ), 'settings.transcript' );
				}
				if ( 'video' === $type && $placeholder ) {
					$this->add( 'VAL-027', 'notice', 'activity', (int) $lesson->ID, $lesson->post_title, 'cc.val.video.placeholder', array(), __( 'This video lesson is a placeholder and does not count for completion.', 'course-captain' ), 'settings.placeholder' );
				}
				if ( 'quiz' === $type && array() === \CourseCaptain\Question\QuestionPresenter::current()->inline_questions( (int) $lesson->ID ) ) {
					$this->add( 'VAL-029', 'blocker', 'activity', (int) $lesson->ID, $lesson->post_title, 'cc.val.quiz.empty', array(), __( 'This quiz has no questions. Add questions to the quiz.', 'course-captain' ) );
				}
			}
		}
		if ( 0 === $required && array() !== $structure->modules( $course_id ) ) {
			$this->add( 'VAL-004', 'warning', 'course', $course_id, (string) get_the_title( $course_id ), 'cc.val.course.no_required', array(), __( 'The course has no required activity, so it can never be completed.', 'course-captain' ) );
		}
	}

	/**
	 * VAL-040…VAL-058 for every question reachable through the course pools.
	 *
	 * @param int $course_id Course.
	 * @return void
	 */
	private function questions( int $course_id ): void {
		$db   = Database::current();
		$repo = QuestionRepository::current();
		$seen = array();
		foreach ( CourseStructure::current()->modules( $course_id ) as $link ) {
			// All pool rows, also unpublished ones (VAL-058); delivery uses only published ones.
			$all = array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT question_id FROM `' . $db->table( 'ccap_module_question' ) . '` WHERE module_id = %d ORDER BY question_id', $link['module_id'] ) ) );
			foreach ( $all as $question ) {
				if ( isset( $seen[ $question ] ) ) {
					continue;
				}
				$seen[ $question ] = true;
				$row               = (array) $db->row( $db->prepare( 'SELECT status, current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question ) );
				$revision          = (array) $repo->revision( (int) ( $row['current_revision_id'] ?? 0 ) );
				$title             = mb_substr( wp_strip_all_tags( (string) ( $revision['question_text'] ?? '' ) ), 0, 60 );
				if ( 'published' !== ( $row['status'] ?? '' ) ) {
					$this->add( 'VAL-058', 'warning', 'question', $question, $title, 'cc.val.question.unpublished', array(), __( 'A question in the pool has no published revision and will not be delivered.', 'course-captain' ) );
					continue;
				}
				foreach ( QuestionTypes::validate( $revision ) as $error ) {
					$code = self::QUESTION_CODES[ $error ] ?? 'VAL-041';
					if ( 'exactly_one_correct' === $error ) {
						$correct = count( array_filter( (array) $revision['answers'], static fn( $a ): bool => is_array( $a ) && ! empty( $a['correct'] ) ) );
						$code    = 0 === $correct ? 'VAL-042' : 'VAL-043';
					}
					$this->add( $code, 'blocker', 'question', $question, $title, 'cc.val.question.' . $error, array(), __( 'This question is incomplete or contradictory. Correct it in the question editor.', 'course-captain' ) );
				}
				$keys  = array();
				$texts = array();
				foreach ( (array) ( $revision['answers'] ?? array() ) as $answer ) {
					$answer = (array) $answer;
					$key    = (string) ( $answer['key'] ?? '' );
					if ( isset( $keys[ $key ] ) ) {
						$this->add( 'VAL-049', 'blocker', 'question', $question, $title, 'cc.val.option.duplicate_key', array( 'key' => $key ), __( 'Two options of this question have the same key.', 'course-captain' ) );
					}
					$keys[ $key ] = true;
					$norm         = mb_strtolower( (string) preg_replace( '/\s+/u', ' ', trim( wp_strip_all_tags( (string) ( $answer['text'] ?? '' ) ) ) ) );
					if ( '' !== $norm && isset( $texts[ $norm ] ) ) {
						$correct  = ! empty( $answer['correct'] );
						$conflict = $correct !== $texts[ $norm ];
						$this->add( $conflict ? 'VAL-047' : 'VAL-048', $conflict ? 'blocker' : 'warning', 'question', $question, $title, 'cc.val.option.duplicate_text', array(), $conflict ? __( 'Two options have the same text, one right and one wrong.', 'course-captain' ) : __( 'Two options of this question have the same text.', 'course-captain' ) );
					}
					$texts[ $norm ] = ! empty( $answer['correct'] );
				}
				if ( QuestionTypes::MULTI === ( $revision['question_type'] ?? '' ) && array() !== $texts && ! in_array( false, $texts, true ) ) {
					$this->add( 'VAL-052', 'warning', 'question', $question, $title, 'cc.val.multi.all_correct', array(), __( 'All options of this multiple-choice question are correct.', 'course-captain' ) );
				}
			}
		}
	}

	/**
	 * VAL-073…VAL-090 for the exam rules and variants of the course.
	 *
	 * @param int $course_id Course.
	 * @return void
	 */
	private function exams( int $course_id ): void {
		$db        = Database::current();
		$relations = new Relations( $db );
		$rules     = ExamRules::current();
		$ids       = $db->wpdb()->get_col( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_exam_rule' ) . "` WHERE course_id = %d AND status IN ('draft', 'published')", $course_id ) );
		foreach ( $ids as $id ) {
			$rule   = (array) $rules->get( (int) $id );
			$title  = (string) $rule['version'];
			$errors = ExamRules::validate_passing_rule( $rule['passing_rule'] );
			if ( array( 'passing_rule_part' ) === $errors ) {
				$this->add( 'VAL-075', 'blocker', 'exam_rule', (int) $id, $title, 'cc.val.exam.part', array(), __( 'A part of the pass rule is invalid. Each part needs at least one module and a pass mark between 0 and 100 %.', 'course-captain' ), 'passing_rule.parts' );
			} elseif ( array() !== $errors ) {
				$this->add( null === $rule['passing_rule'] ? 'VAL-073' : 'VAL-074', 'blocker', 'exam_rule', (int) $id, $title, 'cc.val.exam.passing_rule', array(), __( 'The pass mark of this exam is missing or invalid. Set a pass mark between 0 and 100 %.', 'course-captain' ), 'passing_rule.min_ratio' );
			} else {
				$this->exam_parts( (int) $id, $title, (array) $rule['passing_rule'], array_values( array_map( static fn( array $b ): int => (int) $b['module_id'], (array) $rule['blueprints'] ) ) );
			}
			$limit = $rule['time_limit_seconds'];
			if ( null !== $limit && ( (int) $limit <= 0 || (int) $limit > 86400 ) ) {
				$this->add( 'VAL-079', 'blocker', 'exam_rule', (int) $id, $title, 'cc.val.exam.time_limit', array(), __( 'The time limit must be between 1 second and 24 hours.', 'course-captain' ), 'time_limit_seconds' );
			}
			foreach ( array( 'grace_seconds', 'late_tolerance_seconds' ) as $field ) {
				$value = (int) ( $rule['settings'][ $field ] ?? 0 );
				if ( $value < 0 || $value > 600 ) {
					$this->add( 'VAL-082', 'blocker', 'exam_rule', (int) $id, $title, 'cc.val.exam.grace', array( 'field' => $field ), __( 'Grace and tolerance times must be between 0 and 600 seconds.', 'course-captain' ), 'settings.' . $field );
				}
			}
			foreach ( (array) $rule['blueprints'] as $blueprint ) {
				$module = (int) $blueprint['module_id'];
				$count  = (int) $blueprint['question_count'];
				if ( ! in_array( $course_id, $relations->courses_of_module( $module ), true ) ) {
					$this->add( 'VAL-083', 'blocker', 'blueprint', $module, (string) get_the_title( $module ), 'cc.val.blueprint.module', array(), __( 'The exam draws from a module that is not part of this course.', 'course-captain' ) );
					continue;
				}
				if ( $count < 1 ) {
					$this->add( 'VAL-084', 'blocker', 'blueprint', $module, (string) get_the_title( $module ), 'cc.val.blueprint.count', array(), __( 'The exam must draw at least one question from each module.', 'course-captain' ) );
				}
				$available = $this->deliverable( $module );
				if ( 0 === $available ) {
					$this->add( 'VAL-085', 'blocker', 'blueprint', $module, (string) get_the_title( $module ), 'cc.val.exam.pool_empty', array(), __( 'The module has no deliverable questions for the exam.', 'course-captain' ) );
				} elseif ( $count > $available ) {
					$evidence = 'evidence' === ( $rule['settings']['purpose'] ?? 'evidence' );
					$this->add(
						$evidence ? 'VAL-086' : 'VAL-087',
						$evidence ? 'blocker' : 'warning',
						'blueprint',
						$module,
						(string) get_the_title( $module ),
						'cc.val.exam.pool_too_small',
						array(
							'required'  => $count,
							'available' => $available,
						),
						sprintf(
							/* translators: 1: available questions, 2: required questions */
							_n( 'Only %1$s of %2$s question is available in this module. Reduce the number or add questions.', 'Only %1$s of %2$s questions are available in this module. Reduce the number or add questions.', $count, 'course-captain' ),
							number_format_i18n( $available ),
							number_format_i18n( $count )
						)
					);
				}
			}
		}
		$variants = $db->rows( $db->prepare( 'SELECT id, variant_no FROM `' . $db->table( 'ccap_exam_variant' ) . "` WHERE course_id = %d AND status = 'published'", $course_id ) );
		foreach ( $variants as $variant ) {
			$rows = $db->rows( $db->prepare( 'SELECT v.position, q.status FROM `' . $db->table( 'ccap_exam_variant_question' ) . '` v LEFT JOIN `' . $db->table( 'ccap_question' ) . '` q ON q.id = v.question_id WHERE v.variant_id = %d ORDER BY v.position', (int) $variant['id'] ) );
			$name = sprintf( /* translators: %d: variant number */ __( 'Variant %d', 'course-captain' ), (int) $variant['variant_no'] );
			if ( array() === $rows || array_column( $rows, 'position' ) !== array_map( 'strval', range( 1, count( $rows ) ) ) ) {
				$this->add( 'VAL-090', 'blocker', 'variant', (int) $variant['id'], $name, 'cc.val.variant.positions', array(), __( 'The variant is empty or its positions have gaps.', 'course-captain' ) );
			}
			if ( in_array( true, array_map( static fn( array $r ): bool => 'published' !== $r['status'], $rows ), true ) ) {
				$this->add( 'VAL-089', 'blocker', 'variant', (int) $variant['id'], $name, 'cc.val.variant.undeliverable', array(), __( 'The variant contains a question that cannot be delivered.', 'course-captain' ) );
			}
		}
	}

	/**
	 * VAL-075 / VAL-092 on the parts of a passing rule (E-331): a part may
	 * cover several blueprint modules, but only modules the blueprint
	 * draws from; a blueprint module outside every part counts only for
	 * the total ratio.
	 *
	 * @param int                  $id      Rule.
	 * @param string               $title   Rule title.
	 * @param array<string, mixed> $passing Passing rule.
	 * @param array                $modules Blueprint modules.
	 * @phpstan-param list<int> $modules
	 * @return void
	 */
	private function exam_parts( int $id, string $title, array $passing, array $modules ): void {
		$parts = ExamRules::parts( $passing );
		if ( array() === $parts || array() === $modules ) {
			return;
		}
		$covered = array();
		foreach ( $parts as $part ) {
			$foreign = array_values( array_diff( $part['sections'], $modules ) );
			if ( array() !== $foreign ) {
				$this->add(
					'VAL-075',
					'blocker',
					'exam_rule',
					$id,
					$title,
					'cc.val.exam.part_section',
					array(
						'part'    => $part['key'],
						'modules' => $foreign,
					),
					sprintf(
						/* translators: %s: part key such as P2 */
						__( 'Part %s refers to a module the exam does not draw from.', 'course-captain' ),
						$part['key']
					),
					'passing_rule.parts'
				);
			}
			$covered = array_merge( $covered, $part['sections'] );
		}
		$outside = array_values( array_diff( $modules, $covered ) );
		if ( array() !== $outside ) {
			$this->add(
				'VAL-092',
				'warning',
				'exam_rule',
				$id,
				$title,
				'cc.val.exam.part_missing',
				array( 'modules' => $outside ),
				__( 'Some questions of this exam belong to no part and count only for the total.', 'course-captain' ),
				'passing_rule.parts'
			);
		}
	}

	/**
	 * VAL-120, VAL-121, VAL-127 on module prerequisites.
	 *
	 * @param int $course_id Course.
	 * @return void
	 */
	private function prerequisites( int $course_id ): void {
		$modules = CourseStructure::current()->modules( $course_id );
		$ids     = array_column( $modules, 'module_id' );
		$edges   = array();
		foreach ( $modules as $link ) {
			$rules = null === $link['rules'] ? array() : (array) ( json_decode( (string) $link['rules'], true )['prerequisites'] ?? array() );
			foreach ( $rules as $rule ) {
				$type = (string) ( $rule['type'] ?? '' );
				$ref  = (int) ( $rule['id'] ?? 0 );
				if ( 'completed_module' === $type ) {
					if ( ! in_array( $ref, $ids, true ) ) {
						$exists = get_post( $ref ) instanceof \WP_Post;
						$this->add( $exists ? 'VAL-127' : 'VAL-121', 'blocker', 'module', $link['module_id'], (string) get_the_title( $link['module_id'] ), 'cc.val.prerequisite.missing', array(), $exists ? __( 'A prerequisite points to a module of another course.', 'course-captain' ) : __( 'A prerequisite points to a module that no longer exists.', 'course-captain' ) );
						continue;
					}
					$edges[ $link['module_id'] ][] = $ref;
				} elseif ( in_array( $type, array( 'completed_activity', 'passed_exam' ), true ) ) {
					$lesson = get_post( $ref );
					if ( ! $lesson instanceof \WP_Post || 'publish' !== $lesson->post_status || ! in_array( (int) $lesson->post_parent, $ids, true ) ) {
						$this->add( 'VAL-121', 'blocker', 'module', $link['module_id'], (string) get_the_title( $link['module_id'] ), 'cc.val.prerequisite.missing', array(), __( 'A prerequisite points to an activity that is missing, archived or outside this course.', 'course-captain' ) );
					}
				}
			}
		}
		$state = array();
		$visit = function ( int $node ) use ( &$visit, &$state, $edges ): bool {
			$state[ $node ] = 1;
			foreach ( $edges[ $node ] ?? array() as $next ) {
				if ( 1 === ( $state[ $next ] ?? 0 ) || ( 0 === ( $state[ $next ] ?? 0 ) && $visit( $next ) ) ) {
					return true;
				}
			}
			$state[ $node ] = 2;
			return false;
		};
		foreach ( array_keys( $edges ) as $node ) {
			if ( 0 === ( $state[ $node ] ?? 0 ) && $visit( $node ) ) {
				$this->add( 'VAL-120', 'blocker', 'course', $course_id, (string) get_the_title( $course_id ), 'cc.val.prerequisite.cycle', array(), __( 'The prerequisites form a cycle, so some modules can never be unlocked. Remove one prerequisite.', 'course-captain' ) );
				break;
			}
		}
	}

	/**
	 * VAL-140/VAL-141 (E-131, A11Y-110): images without alternative text.
	 *
	 * An image counts as described when its `alt` is not empty; `alt=""`
	 * counts only when it is explicitly decorative (`data-ccap-decorative`,
	 * `role="presentation"`/`"none"` or `aria-hidden="true"`). Course media
	 * rows (`ccap_media`) of type image need `alt_text` (NULL = missing,
	 * '' = decorative). A question that needs its image (`image_required`)
	 * must describe it in words; decorative is not enough (VAL-141). One
	 * issue per object with the number of images.
	 *
	 * @param int           $course_id Course.
	 * @param \WP_Post|null $post      Course post.
	 * @return void
	 */
	private function alt_texts( int $course_id, ?\WP_Post $post ): void {
		$db      = Database::current();
		$message = static fn( int $n ): string => sprintf(
			/* translators: %d: number of images */
			_n( '%d image has no alternative text. Screen reader users do not learn what it shows. Add an alternative text or mark the image as decorative.', '%d images have no alternative text. Screen reader users do not learn what they show. Add an alternative text or mark the images as decorative.', $n, 'course-captain' ),
			$n
		);
		$media   = static function ( string $type, int $id, bool $words = false ): int {
			$db = Database::current();
			return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_content_media' ) . '` cm JOIN `' . $db->table( 'ccap_media' ) . "` m ON m.id = cm.media_id WHERE cm.object_type = %s AND cm.object_id = %d AND m.media_type = 'image' AND " . ( $words ? "(m.alt_text IS NULL OR m.alt_text = '')" : 'm.alt_text IS NULL' ), $type, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed condition.
		};
		if ( $post instanceof \WP_Post ) {
			$missing = self::images_without_alt( $post->post_content )['missing'] + $media( 'course', $course_id );
			if ( $missing > 0 ) {
				$this->add( 'VAL-140', 'blocker', 'course', $course_id, $post->post_title, 'cc.val.media.alt_missing', array( 'count' => $missing ), $message( $missing ), 'alt_text' );
			}
		}
		$seen = array();
		foreach ( CourseStructure::current()->modules( $course_id ) as $link ) {
			$module  = (int) $link['module_id'];
			$missing = $media( 'module', $module );
			if ( $missing > 0 ) {
				$this->add( 'VAL-140', 'blocker', 'module', $module, (string) get_the_title( $module ), 'cc.val.media.alt_missing', array( 'count' => $missing ), $message( $missing ), 'alt_text' );
			}
			foreach ( CourseStructure::current()->lessons( $module ) as $lesson ) {
				$missing = self::images_without_alt( $lesson->post_content )['missing'] + $media( 'lesson', (int) $lesson->ID );
				if ( $missing > 0 ) {
					$this->add( 'VAL-140', 'blocker', 'lesson', (int) $lesson->ID, $lesson->post_title, 'cc.val.media.alt_missing', array( 'count' => $missing ), $message( $missing ), 'alt_text' );
				}
			}
			foreach ( array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT question_id FROM `' . $db->table( 'ccap_module_question' ) . '` WHERE module_id = %d ORDER BY question_id', $module ) ) ) as $question ) {
				if ( isset( $seen[ $question ] ) ) {
					continue;
				}
				$seen[ $question ] = true;
				$row               = $db->row( $db->prepare( 'SELECT r.id, r.question_text, r.image_required FROM `' . $db->table( 'ccap_question' ) . '` q JOIN `' . $db->table( 'ccap_question_revision' ) . "` r ON r.id = q.current_revision_id WHERE q.id = %d AND q.status <> 'archived'", $question ) );
				if ( null === $row ) {
					continue;
				}
				$title  = mb_substr( wp_strip_all_tags( (string) $row['question_text'] ), 0, 60 );
				$images = self::images_without_alt( (string) $row['question_text'] );
				if ( '1' === (string) $row['image_required'] ) {
					$undescribed = $images['undescribed'] + $media( 'question_revision', (int) $row['id'], true );
					if ( $undescribed > 0 ) {
						$this->add( 'VAL-141', 'blocker', 'question', $question, $title, 'cc.val.media.alt_required', array( 'count' => $undescribed ), __( 'The question cannot be solved without the image, but the image is not described. Enter an alternative text; "decorative" is not enough here.', 'course-captain' ), 'alt_text' );
					}
					continue;
				}
				$missing = $images['missing'] + $media( 'question_revision', (int) $row['id'] );
				if ( $missing > 0 ) {
					$this->add( 'VAL-140', 'blocker', 'question', $question, $title, 'cc.val.media.alt_missing', array( 'count' => $missing ), $message( $missing ), 'alt_text' );
				}
			}
		}
	}

	/**
	 * Images in HTML: total, `missing` (no alt, or an empty alt without a
	 * decorative marker) and `undescribed` (empty alt, decorative or not).
	 *
	 * @param string $html HTML.
	 * @return array{total: int, missing: int, undescribed: int}
	 */
	public static function images_without_alt( string $html ): array {
		$out = array(
			'total'       => 0,
			'missing'     => 0,
			'undescribed' => 0,
		);
		if ( false === stripos( $html, '<img' ) || ! preg_match_all( '/<img\b[^>]*>/i', $html, $tags ) ) {
			return $out;
		}
		foreach ( $tags[0] as $tag ) {
			++$out['total'];
			$alt = null;
			if ( 1 === preg_match( '/\salt\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $m ) ) {
				$alt = trim( html_entity_decode( (string) ( $m[1] . ( $m[2] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) );
			}
			if ( null !== $alt && '' !== $alt ) {
				continue;
			}
			++$out['undescribed'];
			$decorative = 1 === preg_match( '/data-ccap-decorative\s*=\s*["\']1["\']|role\s*=\s*["\'](?:presentation|none)["\']|aria-hidden\s*=\s*["\']true["\']/i', $tag );
			if ( null === $alt || ! $decorative ) {
				++$out['missing'];
			}
		}
		return $out;
	}

	/**
	 * Deliverable questions of a module pool.
	 *
	 * @param int $module Module.
	 * @return int
	 */
	private function deliverable( int $module ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_module_question' ) . '` p JOIN `' . $db->table( 'ccap_question' ) . "` q ON q.id = p.question_id WHERE p.module_id = %d AND q.status = 'published'", $module ) );
	}

	/**
	 * Add an issue.
	 *
	 * @param string               $code     Code.
	 * @param string               $severity blocker|warning|notice.
	 * @param string               $type     Object type.
	 * @param int                  $id       Object ID.
	 * @param string               $title    Object title.
	 * @param string               $key      Message key.
	 * @param array<string, mixed> $params   Parameters.
	 * @param string               $message  Translated message.
	 * @param string|null          $field    Field.
	 * @return void
	 */
	private function add( string $code, string $severity, string $type, int $id, string $title, string $key, array $params, string $message, ?string $field = null ): void {
		ksort( $params );
		$this->issues[] = array(
			'code'         => $code,
			'severity'     => $severity,
			'fingerprint'  => 'sha256:' . hash( 'sha256', $code . '|' . $type . ':' . $id . '|' . (string) $field . '|' . (string) wp_json_encode( $params ) ),
			'object'       => array(
				'type'  => $type,
				'id'    => $id,
				'title' => $title,
			),
			'field'        => $field,
			'message_key'  => $key,
			'params'       => $params,
			'message'      => $message,
			'target'       => array(
				'object_id' => $id,
				'field'     => $field,
			),
			'acknowledged' => false,
		);
	}
}
