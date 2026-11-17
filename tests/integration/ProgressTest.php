<?php
/**
 * R1-10: course completion, mastery aggregate, competency, rebuild (R0-08 §5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Progress\Competency;
use CourseCaptain\Progress\CourseProgress;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-10
 */
final class ProgressTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner with full grant.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Modules M1 (required), M2 (required), M3 (optional).
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Counting lessons per module (placeholder excluded).
	 *
	 * @var array<int, list<int>>
	 */
	private array $lessons = array();

	/**
	 * M1: 2 lessons + placeholder video; M2: 2 lessons; M3 (optional): 1 lesson.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = $this->post( 'ccap_course', 0 );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		foreach ( array( array( 2, true ), array( 2, true ), array( 1, false ) ) as $spec ) {
			$module          = $this->post( 'ccap_module', 0 );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( $admin, $this->course, $module, null, $spec[1] );
			for ( $i = 0; $i < $spec[0]; $i++ ) {
				$lesson = $this->post( 'ccap_lesson', $module );
				ActivityService::current()->define( $admin, $lesson, 'lesson', array() );
				$this->lessons[ $module ][] = $lesson;
			}
		}
		$placeholder = $this->post( 'ccap_lesson', $this->modules[0] );
		ActivityService::current()->define( $admin, $placeholder, 'video', array( 'placeholder' => true ) );
		CoursePublisher::current()->publish( $admin, $this->course );
		$this->grant( $this->learner, 0 );
	}

	/**
	 * Published post.
	 *
	 * @param string $type      Type.
	 * @param int    $parent_id Parent.
	 * @return int
	 */
	private function post( string $type, int $parent_id ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_parent'  => $parent_id,
				'post_author'  => $this->admin,
				'post_title'   => $type,
				'post_content' => 'content',
			)
		);
	}

	/**
	 * Active grant (module 0 = whole course).
	 *
	 * @param int $user   User.
	 * @param int $module Module.
	 */
	private function grant( int $user, int $module ): void {
		$db = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $this->course,
				'module_id'   => $module,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
	}

	/**
	 * Confirm a lesson.
	 *
	 * @param int $user   Learner.
	 * @param int $lesson Lesson.
	 * @return void
	 */
	private function confirm( int $user, int $lesson ): void {
		ActivityProgress::current()->interact( Actor::user( $user ), $this->course, $lesson, 'confirm' );
	}

	/**
	 * GM-009 and §5.1: denominator without placeholder/optional; monotonic completion; one event.
	 *
	 * @group GM-009
	 */
	public function test_GM_009_completion(): void {
		add_filter( 'course_captain_internal_subscribers', static fn( array $s ): array => $s + array( 'test' => array( 'course.*' ) ) );
		$progress = CourseProgress::current();
		$fresh    = $progress->recalculate( $this->learner, $this->course );
		self::assertSame( 'not_started', $fresh['status'] );
		self::assertSame( 4, $fresh['activities_total'], 'placeholder and optional module excluded' );

		$this->confirm( $this->learner, $this->lessons[ $this->modules[0] ][0] );
		$row = (array) $progress->get( $this->learner, $this->course );
		self::assertSame( 'in_progress', $row['status'], 'recalculated by the hook' );
		self::assertSame( '0.2500', $row['completion_ratio'] );
		self::assertNotNull( $row['started_at'] );

		foreach ( array( $this->lessons[ $this->modules[0] ][1], $this->lessons[ $this->modules[1] ][0], $this->lessons[ $this->modules[1] ][1] ) as $lesson ) {
			$this->confirm( $this->learner, $lesson );
		}
		$row      = (array) $progress->get( $this->learner, $this->course );
		$revision = (int) CoursePublisher::current()->current_revision( $this->course )['id'];
		self::assertSame( 'completed', $row['status'], 'optional module not needed' );
		self::assertSame( '1.0000', $row['completion_ratio'] );
		self::assertSame( $revision, (int) $row['completed_revision_id'] );

		$this->confirm( $this->learner, $this->lessons[ $this->modules[2] ][0] );
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_outbox_event' ) . "` WHERE event_type = 'course.completed'" ), 'exactly once' );

		// Monotonic: an access loss does not reset the completion.
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . "` SET status = 'revoked' WHERE user_id = %d", $this->learner ) );
		PolicyService::flush();
		self::assertSame( 'completed', $progress->recalculate( $this->learner, $this->course )['status'] );
	}

	/**
	 * E-286: module grants — denominator of the accessible modules, completion still needs all required modules.
	 *
	 * @group E-286
	 * @group GM-200
	 */
	public function test_E286_partial_access(): void {
		$partial = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->grant( $partial, $this->modules[0] );
		foreach ( $this->lessons[ $this->modules[0] ] as $lesson ) {
			$this->confirm( $partial, $lesson );
		}
		$state = CourseProgress::current()->recalculate( $partial, $this->course );
		self::assertSame( 2, $state['activities_total'] );
		self::assertSame( '1.0000', $state['completion_ratio'] );
		self::assertSame( 'in_progress', $state['status'] );
		self::assertSame( array( true, false, false ), array_column( $state['modules'], 'accessible' ) );
		self::assertTrue( $state['modules'][0]['completed'] );
	}

	/**
	 * REV-010 / RV-07 / RV-08 (E-103): learners roll forward to the new revision at the next access;
	 * completed activities keep their status, a new required activity raises the denominator.
	 *
	 * @group REV-010
	 */
	public function test_revision_rolls_forward_with_vested_rights(): void {
		$this->confirm( $this->learner, $this->lessons[ $this->modules[0] ][0] );
		$before = (array) CourseProgress::current()->get( $this->learner, $this->course );
		$admin  = Actor::user( $this->admin );
		$extra  = $this->post( 'ccap_lesson', $this->modules[1] );
		ActivityService::current()->define( $admin, $extra, 'lesson', array() );
		CoursePublisher::current()->publish( $admin, $this->course );
		$after = CourseProgress::current()->recalculate( $this->learner, $this->course );
		self::assertNotSame( (int) $before['course_revision_id'], $after['course_revision_id'], 'switched to the published revision' );
		self::assertSame( 5, $after['activities_total'], 'new required activity counts' );
		self::assertSame( (int) $before['activities_done'], $after['activities_done'], 'completed activity kept (vested right)' );

		$newcomer = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->grant( $newcomer, 0 );
		self::assertSame( 5, CourseProgress::current()->recalculate( $newcomer, $this->course )['activities_total'], 'new learners start on the new revision' );
	}

	/**
	 * Mastery aggregate = mastered / questions of the unlocked modules (§5.2); never from completion.
	 */
	public function test_mastery_ratio(): void {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$ids   = array();
		foreach ( array( 1, 2 ) as $n ) {
			$id = $repo->create(
				$admin,
				array(
					'question_type' => QuestionTypes::SINGLE,
					'question_text' => 'Q' . $n,
					'answers'       => array(
						array(
							'key'     => 'A',
							'text'    => 'a',
							'correct' => true,
						),
						array(
							'key'  => 'B',
							'text' => 'b',
						),
					),
				)
			);
			$repo->publish( $admin, $id );
			$repo->add_to_pool( $admin, $this->modules[0], $id );
			$ids[] = $id;
		}
		foreach ( $this->lessons[ $this->modules[0] ] as $lesson ) {
			$this->confirm( $this->learner, $lesson );
		}
		self::assertSame( '0.0000', CourseProgress::current()->recalculate( $this->learner, $this->course )['mastery_ratio'], 'completion does not create mastery' );
		$db = Database::current();
		$db->insert(
			'ccap_question_progress',
			array(
				'user_id'      => $this->learner,
				'question_id'  => $ids[0],
				'learn_status' => 'mastered',
			)
		);
		self::assertSame( '0.5000', CourseProgress::current()->recalculate( $this->learner, $this->course )['mastery_ratio'] );
	}

	/**
	 * Competency (§5.3): only eligible passed attempts with certifies_objectives; validity; voided → revoked.
	 */
	public function test_competency(): void {
		$rule    = ExamRules::current()->create(
			Actor::user( $this->admin ),
			$this->course,
			'v1',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			)
		);
		$session = function ( bool $eligible, bool $passed, string $status, array $objectives, ?int $days, string $evaluated ) use ( $rule ): void {
			$db       = Database::current();
			$snapshot = (string) wp_json_encode(
				array(
					'evidence_eligible' => $eligible,
					'rule'              => array( 'version' => 'v1' ),
					'competency'        => array(
						'certifies_objectives' => $objectives,
						'validity_days'        => $days,
					),
				)
			);
			$db->insert(
				'ccap_exam_session',
				array(
					'uid'              => $db->uuid(),
					'user_id'          => $this->learner,
					'course_id'        => $this->course,
					'rule_id'          => $rule,
					'mode'             => 'random',
					'form_key'         => 'rule:' . $rule,
					'status'           => $status,
					'end_reason'       => 'cancelled' === $status ? 'support_void' : 'submitted',
					'start_token'      => $db->uuid(),
					'snapshot'         => $snapshot,
					'snapshot_hash'    => 'sha256:' . hash( 'sha256', $snapshot ),
					'started_at'       => $evaluated,
					'evaluated_at'     => $evaluated,
					'points_milli'     => 8000,
					'max_points_milli' => 10000,
					'passed'           => $passed ? 1 : 0,
				)
			);
		};
		$session( true, true, 'evaluated', array( 'obj-a' ), null, '2026-11-01 10:00:00' );
		$session( false, true, 'evaluated', array( 'obj-b' ), null, '2026-11-02 10:00:00' );
		$session( true, false, 'evaluated', array( 'obj-c' ), null, '2026-11-03 10:00:00' );
		$session( true, true, 'evaluated', array( 'obj-d' ), 10, '2026-10-01 10:00:00' );
		$session( true, true, 'cancelled', array( 'obj-e' ), null, '2026-11-04 10:00:00' );

		$states = Competency::current()->states( $this->learner, $this->course, array( 'obj-a', 'obj-b', 'obj-c', 'obj-d', 'obj-e' ), (int) strtotime( '2026-11-16 00:00:00 UTC' ) );
		self::assertSame( 'evidenced', $states['obj-a']['state'] );
		self::assertSame( 8000, $states['obj-a']['points_milli'] );
		self::assertSame( 'not_evidenced', $states['obj-b']['state'], 'practice/reduced attempts are no evidence' );
		self::assertSame( 'not_evidenced', $states['obj-c']['state'], 'failed attempts are no evidence' );
		self::assertSame( 'expired', $states['obj-d']['state'] );
		self::assertSame( '2026-10-11 10:00:00', $states['obj-d']['valid_until'] );
		self::assertSame( 'revoked', $states['obj-e']['state'] );
		self::assertArrayNotHasKey( 'source', $states['obj-b'], 'no estimated ratio' );
	}

	/**
	 * Rebuild job restores materialized rows in batches.
	 */
	public function test_rebuild(): void {
		foreach ( $this->lessons[ $this->modules[0] ] as $lesson ) {
			$this->confirm( $this->learner, $lesson );
		}
		$db = Database::current();
		$db->execute( 'DELETE FROM `' . $db->table( 'ccap_course_progress' ) . '`' );
		self::assertSame( 0, CourseProgress::current()->rebuild( $this->course, 0, 50 ) );
		$row = (array) CourseProgress::current()->get( $this->learner, $this->course );
		self::assertSame( '0.5000', $row['completion_ratio'] );
		self::assertSame( 'in_progress', $row['status'] );

		$db->execute( 'DELETE FROM `' . $db->table( 'ccap_course_progress' ) . '`' );
		do_action( 'ccap_task_continue', 'progress_rebuild:' . $this->course, 0 );
		self::assertNotNull( CourseProgress::current()->get( $this->learner, $this->course ), 'task_continue handler' );
		do_action( 'ccap_task_continue', 'other_task', 0 );
	}

	/**
	 * Remove a lesson from the next revision (not published any more) and publish.
	 *
	 * @param int $lesson Lesson.
	 * @return void
	 */
	private function remove_and_publish( int $lesson ): void {
		wp_update_post(
			array(
				'ID'          => $lesson,
				'post_status' => 'draft',
			)
		);
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );
	}

	/**
	 * REV-001 / RV-07: content change keeps a completed lesson completed; quota unchanged.
	 *
	 * @group REV-001
	 */
	public function test_REV_001_content_change_keeps_completion(): void {
		$lesson = $this->lessons[ $this->modules[0] ][0];
		$this->confirm( $this->learner, $lesson );
		$before = CourseProgress::current()->recalculate( $this->learner, $this->course );
		wp_update_post(
			array(
				'ID'           => $lesson,
				'post_content' => 'changed text',
			)
		);
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );
		$after = CourseProgress::current()->recalculate( $this->learner, $this->course );
		self::assertNotSame( $before['course_revision_id'], $after['course_revision_id'] );
		self::assertSame( $before['activities_done'], $after['activities_done'] );
		self::assertSame( $before['completion_ratio'], $after['completion_ratio'] );
		self::assertSame( 'completed', (string) Database::current()->scalar( Database::current()->prepare( 'SELECT status FROM `' . Database::current()->table( 'ccap_activity_progress' ) . '` WHERE user_id = %d AND activity_id = %d', $this->learner, $lesson ) ) );
	}

	/**
	 * REV-003 / RV-10: the last open required lesson is removed → course completed on switch with `revision_recalc`.
	 *
	 * @group REV-003
	 */
	public function test_REV_003_removed_open_lesson_completes_course(): void {
		add_filter( 'course_captain_internal_subscribers', static fn( array $s ): array => $s + array( 'test' => array( 'course.*' ) ) );
		$open = $this->lessons[ $this->modules[1] ][1];
		foreach ( array( $this->lessons[ $this->modules[0] ][0], $this->lessons[ $this->modules[0] ][1], $this->lessons[ $this->modules[1] ][0] ) as $lesson ) {
			$this->confirm( $this->learner, $lesson );
		}
		self::assertSame( 'in_progress', CourseProgress::current()->recalculate( $this->learner, $this->course )['status'] );
		$this->remove_and_publish( $open );
		$after = CourseProgress::current()->recalculate( $this->learner, $this->course );
		self::assertSame( 'completed', $after['status'] );
		self::assertSame( $after['course_revision_id'], $after['completed_revision_id'], 'completed in the new revision' );
		$db      = Database::current();
		$payload = (string) $db->scalar( $db->prepare( 'SELECT payload FROM `' . $db->table( 'ccap_outbox_event' ) . "` WHERE event_type = 'course.completed' AND user_ref = %s", 'user:' . $this->learner ) );
		self::assertStringContainsString( '"reason_code":"revision_recalc"', $payload );
	}

	/**
	 * REV-004 / RV-08: a removed completed lesson no longer counts; the row stays as history.
	 *
	 * @group REV-004
	 */
	public function test_REV_004_removed_completed_lesson(): void {
		$lesson = $this->lessons[ $this->modules[1] ][0];
		$this->confirm( $this->learner, $lesson );
		self::assertSame( 1, CourseProgress::current()->recalculate( $this->learner, $this->course )['activities_done'] );
		$this->remove_and_publish( $lesson );
		$after = CourseProgress::current()->recalculate( $this->learner, $this->course );
		self::assertSame( 0, $after['activities_done'] );
		self::assertSame( 3, $after['activities_total'] );
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_activity_progress' ) . '` WHERE user_id = %d AND activity_id = %d', $this->learner, $lesson ) ), 'history kept' );
	}

	/**
	 * REV-010 (B) / RV-09: a completed course stays completed with its completion revision after a new required lesson.
	 *
	 * @group REV-010
	 */
	public function test_REV_010_completed_course_stays_completed(): void {
		foreach ( array( $this->lessons[ $this->modules[0] ][0], $this->lessons[ $this->modules[0] ][1], $this->lessons[ $this->modules[1] ][0], $this->lessons[ $this->modules[1] ][1] ) as $lesson ) {
			$this->confirm( $this->learner, $lesson );
		}
		$done = CourseProgress::current()->recalculate( $this->learner, $this->course );
		self::assertSame( 'completed', $done['status'] );
		$extra = $this->post( 'ccap_lesson', $this->modules[1] );
		ActivityService::current()->define( Actor::user( $this->admin ), $extra, 'lesson', array() );
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );
		$after = CourseProgress::current()->recalculate( $this->learner, $this->course );
		self::assertSame( 'completed', $after['status'], 'completion is an event' );
		self::assertSame( $done['completed_revision_id'], $after['completed_revision_id'] );
		self::assertNotSame( $done['course_revision_id'], $after['course_revision_id'], 'learning revision still follows' );
		self::assertSame( 5, $after['activities_total'] );
	}

	/**
	 * §3.2 step 3: the backfill after publishing switches learners without their access.
	 *
	 * @group REV-037
	 */
	public function test_backfill_after_publish(): void {
		$this->confirm( $this->learner, $this->lessons[ $this->modules[0] ][0] );
		$old = CourseProgress::current()->recalculate( $this->learner, $this->course )['course_revision_id'];
		$this->remove_and_publish( $this->lessons[ $this->modules[1] ][1] );
		self::assertNotFalse( wp_next_scheduled( \CourseCaptain\Kernel\CronHooks::TASK_CONTINUE, array( 'progress_rebuild:' . $this->course, 0 ) ), 'backfill scheduled' );
		self::assertSame( 0, CourseProgress::current()->rebuild( $this->course ) );
		$row = (array) CourseProgress::current()->get( $this->learner, $this->course );
		self::assertNotSame( (string) $old, (string) $row['course_revision_id'] );
		self::assertSame( 0, CourseProgress::current()->rebuild( $this->course ), 'idempotent' );
	}
}
