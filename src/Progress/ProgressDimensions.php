<?php
/**
 * The three progress dimensions of one learner in one course (R0-08 §5, Konzept 28.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Progress;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Kernel\Locale;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Read model only: completion from `ccap_course_progress` (CourseProgress),
 * mastery from the smart trainer stage counts (`SmartTrainer::summary`) and
 * competency from the derived evidence (`Competency::states`, E-73). The
 * three values never feed each other (R0-08 §5). Every dimension carries
 * a value text (`summary`) and its rule (`rule`) so REST, the learning
 * area and the trainer view explain the same value in the same words.
 *
 * Known objectives of a course: the `certifies_objectives` of its
 * published exam rules and the `competency:` tags of the published
 * questions in its modules. Objectives that only appear in evidence
 * (an older rule version, a certificate) are reported as well – evidence
 * stays frozen with the snapshot it was taken with (R0-08 §5.3).
 *
 * Rights: the learner sees their own data in a published course; anyone
 * else needs M-11 (`ccap_view_learner`: assigned trainer for the course,
 * site-wide staff; never a group owner). Everything else is 404.
 */
final class ProgressDimensions {

	/**
	 * Constructor.
	 *
	 * @param Database     $db         Database access.
	 * @param Relations    $relations  Relations.
	 * @param SmartTrainer $trainer    Smart trainer (stage counts).
	 * @param Competency   $competency Derived competency.
	 */
	public function __construct( private Database $db, private Relations $relations, private SmartTrainer $trainer, private Competency $competency ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, new Relations( $db ), SmartTrainer::current(), new Competency( $db ) );
	}

	/**
	 * Own dimensions of the viewer in a published course.
	 *
	 * @param Actor    $viewer    Learner.
	 * @param int      $course_id Course.
	 * @param int|null $now       Unix time (tests).
	 * @return array<string, mixed>
	 * @throws CourseException 401 for guests, 404 for unknown or unpublished courses.
	 */
	public function own( Actor $viewer, int $course_id, ?int $now = null ): array {
		if ( $viewer->user_id <= 0 ) {
			throw new CourseException( 'ccap_auth_required', 'Please log in.', 401 );
		}
		$post = $this->relations->post( $course_id, Relations::COURSE_TYPE );
		if ( null === $post || Relations::STATUS_PUBLISHED !== Relations::course_status( $post ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		return $this->build( $viewer->user_id, $course_id, $now );
	}

	/**
	 * Dimensions of a learner for a viewer with M-11 (trainer view).
	 *
	 * @param Actor    $viewer    Viewer.
	 * @param int      $course_id Course.
	 * @param int      $learner   Learner.
	 * @param int|null $now       Unix time (tests).
	 * @return array<string, mixed>
	 * @throws CourseException 404 for unknown courses, unknown learners and learners outside the viewer's scope.
	 */
	public function of_learner( Actor $viewer, int $course_id, int $learner, ?int $now = null ): array {
		if ( null === $this->relations->post( $course_id, Relations::COURSE_TYPE ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		$decision = PolicyService::instance()->decide( $viewer, PolicyService::VIEW_LEARNER, array( $learner, $course_id ) );
		if ( ! $decision->allowed ) {
			// IDOR: a learner outside the scope looks like an unknown one.
			throw new CourseException( 'ccap_not_found', 'Unknown learner.', 404 );
		}
		return $this->build( $learner, $course_id, $now );
	}

	/**
	 * Dimensions without rights check (callers check).
	 *
	 * @param int      $learner   Learner.
	 * @param int      $course_id Course.
	 * @param int|null $now       Unix time (tests).
	 * @return array{course_uid: string, user_id: int, completion: array<string, mixed>, mastery: array<string, mixed>, competency: array{evidenced: int, total: int, summary: string, rule: string, objectives: list<array<string, mixed>>}}
	 */
	public function build( int $learner, int $course_id, ?int $now = null ): array {
		return array(
			'course_uid' => (string) ( new ObjectIdentity( $this->db ) )->uid_of( 'course', $course_id ),
			'user_id'    => $learner,
			'completion' => $this->completion( $learner, $course_id ),
			'mastery'    => $this->mastery( $learner, $course_id ),
			'competency' => $this->competency_dimension( $learner, $course_id, $now ),
		);
	}

	/**
	 * Known objectives of a course (uid => title).
	 *
	 * @param int $course_id Course.
	 * @return array<string, string>
	 */
	public function objectives( int $course_id ): array {
		$out   = array();
		$rules = $this->db->rows( $this->db->prepare( 'SELECT settings FROM `' . $this->db->table( 'ccap_exam_rule' ) . "` WHERE course_id = %d AND status = 'published'", $course_id ) );
		foreach ( $rules as $rule ) {
			$settings = json_decode( (string) $rule['settings'], true );
			foreach ( is_array( $settings ) ? (array) ( $settings['certifies_objectives'] ?? array() ) : array() as $objective ) {
				$objective = (string) $objective;
				if ( '' !== $objective ) {
					$out[ $objective ] = $objective;
				}
			}
		}
		$modules = array_map( static fn( array $link ): int => (int) $link['module_id'], CourseStructure::current()->modules( $course_id ) );
		if ( array() !== $modules ) {
			$in   = implode( ',', array_map( 'intval', $modules ) );
			$rows = $this->db->rows(
				'SELECT DISTINCT t.tag_key, t.label FROM `' . $this->db->table( 'ccap_question_tag' ) . '` t JOIN `' . $this->db->table( 'ccap_question' ) . '` q ON q.current_revision_id = t.revision_id JOIN `' . $this->db->table( 'ccap_module_question' ) . "` mq ON mq.question_id = q.id WHERE t.tag_type = 'competency' AND q.status = 'published' AND mq.module_id IN ({$in}) ORDER BY t.tag_key" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list and fixed table names.
			);
			foreach ( $rows as $row ) {
				$out[ (string) $row['tag_key'] ] = '' !== (string) $row['label'] ? (string) $row['label'] : (string) $row['tag_key'];
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Completion (worked through).
	 *
	 * @param int $learner   Learner.
	 * @param int $course_id Course.
	 * @return array<string, mixed>
	 */
	private function completion( int $learner, int $course_id ): array {
		$row     = CourseProgress::current()->get( $learner, $course_id );
		$status  = null === $row ? 'not_started' : (string) $row['status'];
		$done    = null === $row ? 0 : (int) $row['activities_done'];
		$total   = null === $row ? 0 : (int) $row['activities_total'];
		$percent = null === $row ? 0 : (int) floor( (float) $row['completion_ratio'] * 100 );
		return array(
			'status'           => $status,
			'status_label'     => self::status_label( $status ),
			'percent'          => $percent,
			'done'             => $done,
			'total'            => $total,
			'last_activity_at' => null === $row ? null : self::rfc3339( $row['last_activity_at'] ?? null ),
			'rule_version'     => CourseProgress::RULE_VERSION,
			'summary'          => 0 === $total
				? __( 'No required lessons counted yet.', 'course-captain' )
				: sprintf(
					/* translators: 1: completed required lessons, 2: all required lessons, 3: percentage, e.g. "30 %" */
					_n( '%1$s of %2$s required lesson completed (%3$s).', '%1$s of %2$s required lessons completed (%3$s).', $total, 'course-captain' ),
					number_format_i18n( $done ),
					number_format_i18n( $total ),
					Locale::percent( $percent )
				),
			'rule'             => __( 'Worked through counts the required lessons whose completion rule is met, for example viewed, confirmed, submitted or quiz passed. With access to single modules only, only those modules count. Practice results and evidence never change this value.', 'course-captain' ),
		);
	}

	/**
	 * Mastery (smart trainer stages over the unlocked scope).
	 *
	 * @param int $learner   Learner.
	 * @param int $course_id Course.
	 * @return array<string, mixed>
	 */
	private function mastery( int $learner, int $course_id ): array {
		$stages = $this->trainer->summary( Actor::user( $learner ), $course_id );
		unset( $stages['topics'] );
		$has_data = null !== $stages['percent'];
		return array(
			'has_data'          => $has_data,
			'algorithm_version' => 'smarttrainer.v' . SmartTrainer::ALGORITHM_VERSION,
			'stages'            => $stages,
			'percent'           => $stages['percent'],
			'summary'           => $has_data
				? sprintf(
					/* translators: 1: consolidated questions, 2: all questions, 3: percentage, 4: questions secure today, 5: questions due for review */
					_n( '%1$s of %2$s practice question consolidated (%3$s); %4$s secure, %5$s due for review.', '%1$s of %2$s practice questions consolidated (%3$s); %4$s secure, %5$s due for review.', (int) $stages['total'], 'course-captain' ),
					number_format_i18n( (int) $stages['mastered'] ),
					number_format_i18n( (int) $stages['total'] ),
					Locale::percent( (int) $stages['percent'] ),
					number_format_i18n( (int) $stages['secure'] ),
					number_format_i18n( (int) $stages['due'] )
				)
				: __( 'No data yet: no practice question has been answered.', 'course-captain' ),
			'rule'              => __( 'Mastered counts practice questions in the stage "Consolidated": three correct answers in a row with spacing make a question secure, one more correct answer on a later review day consolidates it. Only practice answers count; exam answers and opened lessons do not.', 'course-captain' ),
		);
	}

	/**
	 * Competency (evidence per objective).
	 *
	 * @param int      $learner   Learner.
	 * @param int      $course_id Course.
	 * @param int|null $now       Unix time.
	 * @return array{evidenced: int, total: int, summary: string, rule: string, objectives: list<array<string, mixed>>}
	 */
	private function competency_dimension( int $learner, int $course_id, ?int $now ): array {
		$titles     = $this->objectives( $course_id );
		$states     = $this->competency->states( $learner, $course_id, array_keys( $titles ), $now );
		$objectives = array();
		$evidenced  = 0;
		foreach ( $states as $uid => $state ) {
			$uid        = (string) $uid;
			$evidenced += 'evidenced' === $state['state'] ? 1 : 0;
			$item       = array(
				'uid'         => $uid,
				'title'       => $titles[ $uid ] ?? $uid,
				'state'       => $state['state'],
				'state_label' => self::competency_label( $state['state'] ),
			);
			if ( isset( $state['source'] ) ) {
				$item += array(
					'source_type'      => (string) ( $state['source_type'] ?? 'exam_session' ),
					'source'           => $state['source'],
					'rule_version'     => (string) ( $state['rule_version'] ?? '' ),
					'evaluated_at'     => self::rfc3339( $state['evaluated_at'] ?? null ),
					'points_milli'     => (int) ( $state['points_milli'] ?? 0 ),
					'max_points_milli' => (int) ( $state['max_points_milli'] ?? 0 ),
					'valid_until'      => self::rfc3339( $state['valid_until'] ?? null ),
				);
			}
			$item['summary'] = self::evidence_text( $state );
			$objectives[]    = $item;
		}
		$total = count( $objectives );
		return array(
			'evidenced'  => $evidenced,
			'total'      => $total,
			'summary'    => 0 === $total
				? __( 'This course names no learning objectives.', 'course-captain' )
				: sprintf(
					/* translators: 1: evidenced learning objectives, 2: all learning objectives */
					_n( '%1$s of %2$s learning objective evidenced.', '%1$s of %2$s learning objectives evidenced.', $total, 'course-captain' ),
					number_format_i18n( $evidenced ),
					number_format_i18n( $total )
				),
			'rule'       => __( 'Competency evidence counts only a passed exam whose rule names the learning objective, or a certificate issued from such an exam. Worked-through lessons, practice results or purchases never count. Each piece of evidence keeps the exam version, date and validity it was taken with; later course changes do not alter it.', 'course-captain' ),
			'objectives' => $objectives,
		);
	}

	/**
	 * Explanation of one objective state (R0-08 §5.3 "Erklärbarkeit").
	 *
	 * @param array<string, mixed> $state State from `Competency::states()`.
	 * @return string
	 */
	public static function evidence_text( array $state ): string {
		if ( ! isset( $state['source'] ) ) {
			return __( 'Not evidenced: no passed exam or certificate names this learning objective yet.', 'course-captain' );
		}
		$date   = self::date( $state['evaluated_at'] ?? null );
		$points = self::points( (int) ( $state['points_milli'] ?? 0 ) );
		$max    = self::points( (int) ( $state['max_points_milli'] ?? 0 ) );
		if ( 'certificate' === ( $state['source_type'] ?? '' ) ) {
			/* translators: 1: date of the exam, 2: points reached, 3: maximum points */
			$source = sprintf( __( 'Evidenced by a certificate based on the exam of %1$s with %2$s of %3$s points.', 'course-captain' ), $date, $points, $max );
		} else {
			/* translators: 1: version of the exam rules, 2: date, 3: points reached, 4: maximum points */
			$source = sprintf( __( 'Evidenced by a passed exam (rule version %1$s) on %2$s with %3$s of %4$s points.', 'course-captain' ), (string) ( $state['rule_version'] ?? '' ), $date, $points, $max );
		}
		$until = $state['valid_until'] ?? null;
		switch ( $state['state'] ) {
			case 'revoked':
				$validity = __( 'Revoked: the attempt was voided or the certificate was revoked.', 'course-captain' );
				break;
			case 'expired':
				/* translators: %s: date */
				$validity = sprintf( __( 'Expired on %s.', 'course-captain' ), self::date( $until ) );
				break;
			default:
				$validity = __( 'No expiry date.', 'course-captain' );
				if ( null !== $until ) {
					/* translators: %s: date */
					$validity = sprintf( __( 'Valid until %s.', 'course-captain' ), self::date( $until ) );
				}
		}
		return $source . ' ' . $validity;
	}

	/**
	 * Course status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'not_started' => __( 'not started', 'course-captain' ),
			'in_progress' => __( 'in progress', 'course-captain' ),
			'completed'   => __( 'completed', 'course-captain' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Competency state label (text, never colour only).
	 *
	 * @param string $state State.
	 * @return string
	 */
	public static function competency_label( string $state ): string {
		$labels = array(
			'evidenced'     => __( 'evidenced', 'course-captain' ),
			'not_evidenced' => __( 'not evidenced', 'course-captain' ),
			'expired'       => __( 'evidence expired', 'course-captain' ),
			'revoked'       => __( 'evidence revoked', 'course-captain' ),
		);
		return $labels[ $state ] ?? $state;
	}

	/**
	 * Points from thousandths without trailing zeros.
	 *
	 * @param int $milli Thousandths.
	 * @return string
	 */
	private static function points( int $milli ): string {
		$decimals = 0 === $milli % 1000 ? 0 : ( 0 === $milli % 100 ? 1 : ( 0 === $milli % 10 ? 2 : 3 ) );
		return number_format_i18n( $milli / 1000, $decimals );
	}

	/**
	 * Site date of a UTC SQL time.
	 *
	 * @param mixed $sql `Y-m-d H:i:s` (UTC) or null.
	 * @return string
	 */
	private static function date( $sql ): string {
		$time = is_string( $sql ) && '' !== $sql ? strtotime( $sql . ' UTC' ) : false;
		return false === $time ? '–' : (string) wp_date( (string) get_option( 'date_format' ), $time );
	}

	/**
	 * RFC 3339 UTC of a UTC SQL time (R0-19 §2.1).
	 *
	 * @param mixed $sql `Y-m-d H:i:s` (UTC) or null.
	 * @return string|null
	 */
	private static function rfc3339( $sql ): ?string {
		$time = is_string( $sql ) && '' !== $sql ? strtotime( $sql . ' UTC' ) : false;
		return false === $time ? null : gmdate( 'Y-m-d\TH:i:s\Z', $time );
	}
}
