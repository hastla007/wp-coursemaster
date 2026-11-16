<?php
/**
 * R1-46: text/file assignments with grading (R0-08 §2.5, R0-10 §4.5,
 * R0-23 RV-22, R0-27 N-14…N-16, R0-31 RT-45).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ProtectedFiles;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Assignment\AssignmentService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Notification\NotificationPrefs;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Privacy\RetentionService;
use CourseCaptain\Storage\Database;

/**
 * Real tables and files; tests are new without LB source (E-168).
 *
 * @group R1-46
 */
final class AssignmentTest extends SchemaTestCase {

	/**
	 * Admin (author of the course).
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner U-L1 with grant.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Foreign learner U-X with grant.
	 *
	 * @var int
	 */
	private int $other;

	/**
	 * Trainer U-TUT of the course.
	 *
	 * @var int
	 */
	private int $trainer;

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Module.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Temporary files.
	 *
	 * @var list<string>
	 */
	private array $tmp = array();

	/**
	 * Mail sink.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $mails = array();

	/**
	 * Build course, module, learners with grant and a trainer.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'l1-' . wp_generate_password( 6, false ) . '@example.org',
			)
		);
		$this->other   = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->trainer = (int) self::factory()->user->create(
			array(
				'role'       => 'ccap_trainer',
				'user_email' => 'tut-' . wp_generate_password( 6, false ) . '@example.org',
			)
		);
		$this->course  = $this->post( 'ccap_course', 0 );
		$this->module  = $this->post( 'ccap_module', 0 );
		PolicyService::flush();
		CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $this->module );
		$this->grant( $this->learner );
		$this->grant( $this->other );
		$this->staff( $this->trainer, 'trainer' );
		$this->mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );
	}

	/**
	 * Cleanup.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
		remove_all_filters( 'course_captain_assignment_file_scan' );
		foreach ( $this->tmp as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
		parent::tear_down();
	}

	/**
	 * Mail sink.
	 *
	 * @param mixed                $value Short-circuit value.
	 * @param array<string, mixed> $atts  Mail.
	 * @return bool
	 */
	public function catch_mail( $value, $atts ): bool {
		unset( $value );
		$this->mails[] = (array) $atts;
		return true;
	}

	/**
	 * Published post.
	 *
	 * @param string $type      Post type.
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
				'post_title'   => $type . ' A',
				'post_content' => 'content',
			)
		);
	}

	/**
	 * Active manual grant.
	 *
	 * @param int $user User.
	 */
	private function grant( int $user ): void {
		$db = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $this->course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
	}

	/**
	 * Staff assignment.
	 *
	 * @param int    $user User.
	 * @param string $role Role.
	 */
	private function staff( int $user, string $role ): void {
		Database::current()->insert(
			'ccap_course_staff',
			array(
				'course_id' => $this->course,
				'user_id'   => $user,
				'role'      => $role,
				'group_id'  => 0,
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
	}

	/**
	 * Assignment lesson in the published course.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $rule     Completion rule.
	 * @return int Lesson.
	 */
	private function task( array $settings, string $rule = 'graded' ): int {
		$lesson = $this->post( 'ccap_lesson', $this->module );
		ActivityService::current()->define( Actor::user( $this->admin ), $lesson, 'assignment', $settings, array( 'type' => $rule ) );
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );
		return $lesson;
	}

	/**
	 * L-E-01: text task, 10 points, pass from 6.
	 *
	 * @param string $rule Completion rule.
	 * @return int
	 */
	private function text_task( string $rule = 'graded' ): int {
		return $this->task(
			array(
				'submission_type' => 'text',
				'max_points'      => 10,
				'passing_points'  => 6,
				'instructions'    => 'Describe the knot.',
				'rubric_text'     => 'Only for graders.',
			),
			$rule
		);
	}

	/**
	 * Temporary file below the temp dir.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	private function tmp( string $content ): string {
		$path = (string) wp_tempnam( 'ccap-asg' );
		file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test fixture.
		$this->tmp[] = $path;
		return $path;
	}

	/**
	 * Minimal PDF of about `$bytes` bytes.
	 *
	 * @param int $bytes Size.
	 * @return string
	 */
	private function pdf( int $bytes = 2000 ): string {
		return $this->tmp( "%PDF-1.4\n1 0 obj<<>>endobj\n%" . str_repeat( 'A', max( 0, $bytes - 40 ) ) . "\ntrailer<<>>\n%%EOF\n" );
	}

	/**
	 * PNG image.
	 *
	 * @return string
	 */
	private function png(): string {
		$image = imagecreatetruecolor( 4, 4 );
		ob_start();
		imagepng( $image );
		return $this->tmp( (string) ob_get_clean() );
	}

	/**
	 * Office container.
	 *
	 * @param string $type docx|odt|zip.
	 * @return string
	 */
	private function office( string $type ): string {
		$path        = (string) wp_tempnam( 'ccap-zip' );
		$this->tmp[] = $path;
		$zip         = new \ZipArchive();
		$zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		if ( 'odt' === $type ) {
			$zip->addFromString( 'mimetype', 'application/vnd.oasis.opendocument.text' );
			$zip->setCompressionName( 'mimetype', \ZipArchive::CM_STORE );
			$zip->addFromString( 'content.xml', '<office:document-content/>' );
		} elseif ( 'docx' === $type ) {
			$zip->addFromString( '[Content_Types].xml', '<Types/>' );
			$zip->addFromString( 'word/document.xml', '<w:document/>' );
		} else {
			$zip->addFromString( 'a.txt', 'x' );
		}
		$zip->close();
		return $path;
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param string   $code     Code.
	 * @param callable $callback Call.
	 * @return CourseException
	 */
	private static function expect( string $code, callable $callback ): CourseException {
		try {
			$callback();
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code, $e->getMessage() );
			return $e;
		}
		self::fail( 'Expected ' . $code );
	}

	/**
	 * Audit actions on an object.
	 *
	 * @param string $object_id Object.
	 * @return list<string>
	 */
	private static function audit( string $object_id ): array {
		$db = Database::current();
		return array_column( $db->rows( $db->prepare( 'SELECT action FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE object_id = %s ORDER BY id', $object_id ) ), 'action' );
	}

	/**
	 * Count of a table.
	 *
	 * @param string $table Table.
	 * @return int
	 */
	private static function count_rows( string $table ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '`' );
	}

	/**
	 * GM-195: state machine draft → submitted → returned → submitted → graded, regrade, rights.
	 *
	 * @group GM-195
	 */
	public function test_GM_195_state_machine(): void {
		$service = AssignmentService::current();
		$lesson  = $this->text_task();
		$l1      = Actor::user( $this->learner );
		$tut     = Actor::user( $this->trainer );

		$first  = $service->save_draft( $l1, $this->course, $lesson, 'Draft one' );
		$second = $service->save_draft( $l1, $this->course, $lesson, 'Bowline: loop, then tuck.', $first['submission_uid'], $first['row_version'] );
		self::assertSame( 'draft', $second['status'] );
		self::assertSame( $first['submission_uid'], $second['submission_uid'] );
		self::assertSame( 1, $second['submission_no'] );
		self::expect( 'ccap_version_conflict', fn() => $service->save_draft( $l1, $this->course, $lesson, 'stale', null, $first['row_version'] ) );
		self::expect( 'ccap_validation_failed', fn() => $service->save_draft( $l1, $this->course, $lesson, str_repeat( 'x', 20001 ) ) );

		$submitted = $service->submit( $l1, $first['submission_uid'] );
		self::assertSame( 'submitted', $submitted['status'] );
		self::assertSame( 1, $submitted['submission_no'] );
		self::assertStringStartsWith( 'sha256:', (string) $submitted['content_hash'] );
		$again = $service->submit( $l1, $first['submission_uid'] );
		self::assertTrue( $again['replayed'] );
		self::assertSame( $submitted['submitted_at'], $again['submitted_at'] );
		self::assertSame( 1, self::count_rows( 'ccap_assignment_submission' ) );
		self::expect( 'ccap_submission_not_editable', fn() => $service->save_draft( $l1, $this->course, $lesson, 'changed' ) );

		// Frozen settings (§7.1).
		$db  = Database::current();
		$row = $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_assignment_submission' ) . '` WHERE uid = %s', $first['submission_uid'] ) );
		self::assertSame( 'sha256:' . hash( 'sha256', (string) wp_json_encode( ActivityService::definition( $lesson )['settings'] ) ), $row['settings_hash'] );
		self::assertSame( (string) CoursePublisher::current()->current_revision( $this->course )['id'], (string) $row['course_revision_id'] );

		// Return needs a comment.
		self::expect( 'ccap_grade_invalid', fn() => $service->grade( $tut, $first['submission_uid'], 'return', null, '', '', 'key-return-0' ) );
		$returned = $service->grade( $tut, $first['submission_uid'], 'return', null, 'Please add the second half.', '', 'key-return-1' );
		self::assertSame( 'returned', $returned['status'] );
		$view = $service->view( $l1, $this->course, $lesson );
		self::assertTrue( $view['editable'] );
		self::assertSame( 'Please add the second half.', $view['submissions'][0]['return_comment'] );
		self::assertSame( 'Describe the knot.', $view['assignment']['instructions'] );
		self::assertArrayNotHasKey( 'grading', $view['submissions'][0], 'rubric is for graders only' );

		// Version 2.
		$v2 = $service->save_draft( $l1, $this->course, $lesson, 'Bowline: loop, tuck, around, back.', $first['submission_uid'] );
		self::assertSame( 2, $v2['submission_no'] );
		$service->submit( $l1, $v2['submission_uid'] );
		$view = $service->view( $l1, $this->course, $lesson );
		self::assertSame( array( 'returned', 'submitted' ), array_column( $view['submissions'], 'status' ) );
		self::assertSame( 'Bowline: loop, then tuck.', $view['submissions'][0]['text'], 'version 1 stays readable' );

		// Grading.
		self::expect( 'ccap_grade_invalid', fn() => $service->grade( $tut, $v2['submission_uid'], 'grade', 11000, '', '', 'key-grade-0' ) );
		self::expect( 'ccap_grade_invalid', fn() => $service->grade( $tut, $v2['submission_uid'], 'grade', 6500, '', '', 'key-grade-h' ) );
		$graded = $service->grade( $tut, $v2['submission_uid'], 'grade', 7000, 'Good.', '', 'key-grade-1' );
		self::assertSame( array( 'graded', 1, 7000, true ), array( $graded['status'], $graded['evaluation_no'], $graded['points_milli'], $graded['passed'] ) );
		$state = ActivityProgress::current()->state( $this->learner, $lesson );
		self::assertSame( array( 'completed', 'graded' ), array( $state['status'], $state['reason_code'] ) );
		$replay = $service->grade( $tut, $v2['submission_uid'], 'grade', 7000, 'Good.', '', 'key-grade-1' );
		self::assertTrue( $replay['replayed'] );
		self::assertSame( 1, self::count_rows( 'ccap_assignment_grade' ) );
		self::expect( 'ccap_idempotency_conflict', fn() => $service->grade( $tut, $v2['submission_uid'], 'grade', 8000, 'Good.', '', 'key-grade-1' ) );

		// Regrade: reason mandatory, new evaluation, completion stays (monotonic).
		self::expect( 'ccap_grade_invalid', fn() => $service->grade( $tut, $v2['submission_uid'], 'grade', 5000, '', '', 'key-grade-2' ) );
		$regrade = $service->grade( $tut, $v2['submission_uid'], 'grade', 5000, '', 'Second half was copied.', 'key-grade-3' );
		self::assertSame( array( 2, false ), array( $regrade['evaluation_no'], $regrade['passed'] ) );
		self::assertSame( 'completed', ActivityProgress::current()->state( $this->learner, $lesson )['status'] );
		$detail = $service->detail( $l1, $v2['submission_uid'] );
		self::assertSame( array( 1, 2 ), array_column( $detail['evaluations'], 'evaluation_no' ) );
		self::assertSame( 5000, $detail['grade']['points_milli'] );
		self::assertSame( array( 'assignment_returned' ), self::audit( $first['submission_uid'] ) );
		self::assertSame( array( 'assignment_graded', 'assignment_regraded' ), self::audit( $v2['submission_uid'] ) );
		self::assertSame( 'Only for graders.', $service->detail( $tut, $v2['submission_uid'] )['grading']['rubric_text'] );

		// Foreign learner: 404 without existence oracle; no self-grading; learner cannot grade.
		$ux = Actor::user( $this->other );
		self::expect( 'ccap_not_found', fn() => $service->detail( $ux, $v2['submission_uid'] ) );
		self::expect( 'ccap_not_found', fn() => $service->submit( $ux, $v2['submission_uid'] ) );
		self::expect( 'ccap_forbidden', fn() => $service->grade( $l1, $v2['submission_uid'], 'grade', 1000, '', 'x', 'key-self-01' ) );
		self::assertFalse( $service->grade( $tut, $v2['submission_uid'], 'grade', 5000, '', 'x', 'key-grade-4' )['replayed'] );
		self::expect( 'ccap_submission_not_editable', fn() => $service->save_draft( $l1, $this->course, $lesson, 'after graded' ) );
	}

	/**
	 * GM-196 / MED-027 / MED-028 / MED-029 / MED-030: files, rights, delivery, privacy.
	 *
	 * @group GM-196
	 * @group MED-027
	 * @group MED-028
	 * @group MED-029
	 * @group MED-030
	 */
	public function test_GM_196_files_rights_and_privacy(): void {
		$service = AssignmentService::current();
		$lesson  = $this->task(
			array(
				'submission_type' => 'file',
				'max_points'      => 10,
				'file_rules'      => array(
					'allowlist' => array( 'pdf', 'png' ),
					'max_bytes' => 2097152,
				),
			)
		);
		$l1      = Actor::user( $this->learner );
		$draft   = $service->save_draft( $l1, $this->course, $lesson, null );
		$uid     = $draft['submission_uid'];
		self::expect( 'ccap_submission_incomplete', fn() => $service->submit( $l1, $uid ) );

		$ok = $service->add_file( $l1, $uid, $this->pdf( 1000000 ), 'Lösung 1.pdf', 'application/pdf' );
		self::assertSame( 'Lösung 1.pdf', $ok['file']['name'] );
		$media = self::count_rows( 'ccap_media' );
		foreach ( array(
			array( $this->png(), 'x.php.png', 'image/png', 'dangerous' ),
			array( $this->tmp( '<svg xmlns="http://www.w3.org/2000/svg"/>' ), 'a.svg', 'image/svg+xml', 'dangerous' ),
			array( $this->tmp( 'Deny from all' ), '.htaccess', 'text/plain', 'dangerous' ),
			array( $this->png(), 'a.pdf', 'application/pdf', 'mime' ),
			array( $this->pdf(), 'a.pdf', 'image/png', 'mime' ),
			array( $this->pdf( 3 * 1048576 ), 'big.pdf', 'application/pdf', 'size' ),
			array( $this->tmp( 'hello' ), 'a.txt', 'text/plain', 'type' ),
		) as $case ) {
			$e = self::expect( 'ccap_submission_file_rejected', fn() => $service->add_file( $l1, $uid, $case[0], $case[1], $case[2] ) );
			self::assertSame( $case[3], $e->data['reason'], $case[1] );
		}
		self::assertSame( $media, self::count_rows( 'ccap_media' ), 'nothing stored' );
		self::assertSame( 1, self::count_rows( 'ccap_assignment_file' ) );

		// Delivery only through the protected route, always as download.
		$files    = ProtectedFiles::current();
		$response = $files->prepare( $l1, $ok['file']['media_uid'] );
		self::assertStringStartsWith( 'attachment;', $response['headers']['Content-Disposition'] );
		self::assertSame( 'nosniff', $response['headers']['X-Content-Type-Options'] );
		self::assertSame( 'sandbox', $response['headers']['Content-Security-Policy'] );
		self::assertStringContainsString( "filename*=UTF-8''L%C3%B6sung%201.pdf", $response['headers']['Content-Disposition'] );
		self::assertStringNotContainsString( 'Lösung', $response['path'], 'original name is metadata only' );
		self::expect( 'ccap_not_found', fn() => $files->prepare( Actor::user( $this->other ), $ok['file']['media_uid'] ) );
		self::assertSame( array(), self::audit( $uid ) );
		self::assertSame( 200, $files->prepare( Actor::user( $this->trainer ), $ok['file']['media_uid'] )['status'] );
		self::assertSame( array( 'assignment_file_accessed' ), self::audit( $uid ) );

		// Upload after submit is rejected (MED-029).
		$service->submit( $l1, $uid );
		self::expect( 'ccap_submission_not_editable', fn() => $service->add_file( $l1, $uid, $this->pdf(), 'b.pdf', 'application/pdf' ) );
		// Without a grant (MED-029, E-27): no new chain.
		$nogrant = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$e       = self::expect( 'ccap_content_locked', fn() => $service->save_draft( Actor::user( $nogrant ), $this->course, $lesson, null ) );
		self::assertSame( 403, $e->status );
		self::assertSame( 0, (int) Database::current()->scalar( Database::current()->prepare( 'SELECT COUNT(*) FROM `' . Database::current()->table( 'ccap_assignment_submission' ) . '` WHERE user_id = %d', $nogrant ) ) );

		// Privacy export and erasure (MED-030, doc review B-4).
		$email  = (string) get_userdata( $this->learner )->user_email;
		$export = PrivacyService::export( $email );
		$groups = array_unique( array_column( $export['data'], 'group_id' ) );
		self::assertContains( 'cc-submissions', $groups );
		self::assertContains( 'cc-submission-files', $groups );
		$path = $response['path'];
		self::assertFileExists( $path );
		PrivacyService::erase( $email );
		self::assertFileDoesNotExist( $path );
		self::assertSame( 0, self::count_rows( 'ccap_assignment_submission' ) );
		self::assertSame( 0, self::count_rows( 'ccap_assignment_file' ) );
		self::assertSame( $media - 1, self::count_rows( 'ccap_media' ) );
		self::expect( 'ccap_not_found', fn() => $files->prepare( $l1, $ok['file']['media_uid'] ) );
	}

	/**
	 * MED-033 / MED-034: allowlist defaults, count and size limits, scanner hook.
	 *
	 * @group MED-033
	 * @group MED-034
	 */
	public function test_MED_033_034_limits_and_scanner(): void {
		$service = AssignmentService::current();
		$lesson  = $this->task(
			array(
				'submission_type' => 'text_and_file',
				'max_points'      => 5,
			)
		);
		$l1      = Actor::user( $this->learner );
		$uid     = $service->save_draft( $l1, $this->course, $lesson, 'See files.' )['submission_uid'];
		$service->add_file( $l1, $uid, $this->office( 'docx' ), 'a.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
		$service->add_file( $l1, $uid, $this->office( 'odt' ), 'a.odt', 'application/vnd.oasis.opendocument.text' );
		$service->add_file( $l1, $uid, $this->tmp( "plain text\n" ), 'a.txt', 'text/plain' );
		$e = self::expect( 'ccap_submission_file_rejected', fn() => $service->add_file( $l1, $uid, $this->office( 'zip' ), 'a.zip', 'application/zip' ) );
		self::assertSame( 'type', $e->data['reason'] );
		$e = self::expect( 'ccap_submission_file_rejected', fn() => $service->add_file( $l1, $uid, $this->office( 'zip' ), 'fake.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ) );
		self::assertSame( 'mime', $e->data['reason'] );
		$e = self::expect( 'ccap_submission_file_rejected', fn() => $service->add_file( $l1, $uid, $this->pdf( 21 * 1048576 ), 'big.pdf', 'application/pdf' ) );
		self::assertSame( 'size', $e->data['reason'] );

		// Scanner finding: rejected, nothing stored (MED-034).
		$stored = self::count_rows( 'ccap_media' );
		add_filter( 'course_captain_assignment_file_scan', static fn() => 'EICAR-Test-File' );
		$e = self::expect( 'ccap_submission_file_rejected', fn() => $service->add_file( $l1, $uid, $this->pdf(), 'a.pdf', 'application/pdf' ) );
		self::assertSame( 'scan', $e->data['reason'] );
		self::assertSame( $stored, self::count_rows( 'ccap_media' ) );
		remove_all_filters( 'course_captain_assignment_file_scan' );

		$service->add_file( $l1, $uid, $this->pdf(), 'a.pdf', 'application/pdf' );
		$service->add_file( $l1, $uid, $this->png(), 'a.png', 'image/png' );
		$e = self::expect( 'ccap_submission_file_rejected', fn() => $service->add_file( $l1, $uid, $this->png(), 'b.png', 'image/png' ) );
		self::assertSame( array( 'count', 5 ), array( $e->data['reason'], $e->data['max_files'] ) );

		// Removing a file frees a slot; submit needs text and file.
		$view  = $service->view( $l1, $this->course, $lesson );
		$first = $view['submissions'][0]['files'][0]['media_uid'];
		$service->remove_file( $l1, $uid, $first );
		self::assertCount( 4, $service->view( $l1, $this->course, $lesson )['submissions'][0]['files'] );
		self::assertSame( 'submitted', $service->submit( $l1, $uid )['status'] );
	}

	/**
	 * Completion rules `submitted` and `passed` (R0-08 §2.4, §5.1).
	 */
	public function test_completion_rules(): void {
		$service   = AssignmentService::current();
		$l1        = Actor::user( $this->learner );
		$submitted = $this->text_task( 'submitted' );
		$uid       = $service->save_draft( $l1, $this->course, $submitted, 'Answer' )['submission_uid'];
		self::assertNotSame( 'completed', ActivityProgress::current()->state( $this->learner, $submitted )['status'] ?? 'not_started' );
		$service->submit( $l1, $uid );
		self::assertSame( 'completed', ActivityProgress::current()->state( $this->learner, $submitted )['status'] );

		$passed = $this->text_task( 'passed' );
		$uid    = $service->save_draft( $l1, $this->course, $passed, 'Answer' )['submission_uid'];
		$service->submit( $l1, $uid );
		$service->grade( Actor::user( $this->trainer ), $uid, 'grade', 3000, '', '', 'key-pass-01' );
		self::assertNotSame( 'completed', ActivityProgress::current()->state( $this->learner, $passed )['status'] );
		$service->grade( Actor::user( $this->trainer ), $uid, 'grade', 8000, '', 'Recount', 'key-pass-02' );
		self::assertSame( array( 'completed', 'passed' ), array( ActivityProgress::current()->state( $this->learner, $passed )['status'], ActivityProgress::current()->state( $this->learner, $passed )['reason_code'] ) );
	}

	/**
	 * REV-047: grading uses the frozen settings; drafts follow the new revision.
	 *
	 * @group REV-047
	 */
	public function test_REV_047_frozen_settings(): void {
		$service = AssignmentService::current();
		$lesson  = $this->text_task();
		$a       = Actor::user( $this->learner );
		$b       = Actor::user( $this->other );
		$sub_a   = $service->save_draft( $a, $this->course, $lesson, 'A' )['submission_uid'];
		$service->submit( $a, $sub_a );
		$sub_b = $service->save_draft( $b, $this->course, $lesson, 'B' )['submission_uid'];

		ActivityService::current()->define(
			Actor::user( $this->admin ),
			$lesson,
			'assignment',
			array(
				'submission_type' => 'text',
				'max_points'      => 20,
				'instructions'    => 'New wording.',
			),
			array( 'type' => 'graded' )
		);
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );

		$tut = Actor::user( $this->trainer );
		self::expect( 'ccap_grade_invalid', fn() => $service->grade( $tut, $sub_a, 'grade', 15000, '', '', 'key-rev-001' ) );
		$graded = $service->grade( $tut, $sub_a, 'grade', 8000, '', '', 'key-rev-002' );
		self::assertTrue( $graded['passed'], 'frozen passing_points 6' );
		self::assertSame( 10000, $service->detail( $a, $sub_a )['grade']['max_points_milli'] );

		$service->submit( $b, $sub_b );
		$grade_b = $service->grade( $tut, $sub_b, 'grade', 15000, '', '', 'key-rev-003' );
		self::assertNull( $grade_b['passed'], 'no passing_points in the new settings' );
		self::assertSame( 20000, $service->detail( $b, $sub_b )['grade']['max_points_milli'] );
		self::assertNotSame( $service->detail( $tut, $sub_a )['grading']['settings_hash'], $service->detail( $tut, $sub_b )['grading']['settings_hash'] );
	}

	/**
	 * REV-048: the task leaves the revision; drafts read-only, submissions gradable, results readable.
	 *
	 * @group REV-048
	 */
	public function test_REV_048_removed_task(): void {
		$service = AssignmentService::current();
		$lesson  = $this->text_task();
		$z_user  = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->grant( $z_user );
		$x   = Actor::user( $this->learner );
		$y   = Actor::user( $this->other );
		$z   = Actor::user( $z_user );
		$tut = Actor::user( $this->trainer );
		$service->save_draft( $x, $this->course, $lesson, 'X draft' );
		$sub_y = $service->save_draft( $y, $this->course, $lesson, 'Y' )['submission_uid'];
		$service->submit( $y, $sub_y );
		$sub_z = $service->save_draft( $z, $this->course, $lesson, 'Z' )['submission_uid'];
		$service->submit( $z, $sub_z );
		$service->grade( $tut, $sub_z, 'grade', 9000, '', '', 'key-rem-001' );
		$files = self::count_rows( 'ccap_assignment_submission' );

		wp_update_post(
			array(
				'ID'          => $lesson,
				'post_status' => 'draft',
			)
		);
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );

		$e = self::expect( 'ccap_submission_not_editable', fn() => $service->save_draft( $x, $this->course, $lesson, 'more' ) );
		self::assertSame( 409, $e->status );
		$view = $service->view( $x, $this->course, $lesson );
		self::assertFalse( $view['editable'] );
		self::assertSame( 'X draft', $view['submissions'][0]['text'] );
		self::assertSame( 'graded', $service->grade( $tut, $sub_y, 'grade', 4000, '', '', 'key-rem-002' )['status'] );
		self::assertSame( 'completed', ActivityProgress::current()->state( $z_user, $lesson )['status'] );
		self::assertSame( 9000, $service->view( $z, $this->course, $lesson )['submissions'][0]['grade']['points_milli'] );
		self::assertSame( $files, self::count_rows( 'ccap_assignment_submission' ) );
	}

	/**
	 * NTF-035 / NTF-036 / NTF-037: N-14 to each trainer, N-15 once, N-16 per evaluation, opt-out.
	 *
	 * @group NTF-035
	 * @group NTF-036
	 * @group NTF-037
	 */
	public function test_NTF_035_036_037_notifications(): void {
		$second = (int) self::factory()->user->create(
			array(
				'role'       => 'ccap_trainer',
				'user_email' => 'tut2-' . wp_generate_password( 6, false ) . '@example.org',
			)
		);
		$author = (int) self::factory()->user->create( array( 'role' => 'ccap_author' ) );
		$this->staff( $second, 'trainer' );
		$this->staff( $author, 'author' );
		$service = AssignmentService::current();
		$lesson  = $this->text_task();
		$l1      = Actor::user( $this->learner );
		$uid     = $service->save_draft( $l1, $this->course, $lesson, 'Secret answer text' )['submission_uid'];
		$service->submit( $l1, $uid );
		$run = static function (): void {
			OutboxDispatcher::current()->dispatch();
			NotificationService::current()->process_queue();
		};
		$run();
		$rows = static function ( string $type ): array {
			$db = Database::current();
			return $db->rows( $db->prepare( 'SELECT recipient_user_id, status, reason_code FROM `' . $db->table( 'ccap_notification' ) . '` WHERE type = %s ORDER BY id', $type ) );
		};
		$n14  = $rows( 'assignment_submitted' );
		self::assertEqualsCanonicalizing( array( $this->trainer, $second ), array_map( 'intval', array_column( $n14, 'recipient_user_id' ) ) );
		self::assertSame( array( 'sent', 'sent' ), array_column( $n14, 'status' ) );

		$tut = Actor::user( $this->trainer );
		$service->grade( $tut, $uid, 'return', null, 'Hidden comment', '', 'key-ntf-001' );
		$v2 = $service->save_draft( $l1, $this->course, $lesson, 'Second answer', $uid )['submission_uid'];
		$service->submit( $l1, $v2 );
		$service->grade( $tut, $v2, 'grade', 7000, 'Hidden feedback', '', 'key-ntf-002' );
		$run();
		NotificationPrefs::set( $l1, $this->learner, 'assignment_graded', true, 'account' );
		$service->grade( $tut, $v2, 'grade', 6000, '', 'Recount', 'key-ntf-003' );
		$run();
		self::assertCount( 1, $rows( 'assignment_returned' ) );
		$n16 = $rows( 'assignment_graded' );
		self::assertSame( array( 'sent', 'suppressed' ), array_column( $n16, 'status' ) );
		self::assertSame( 'opt_out', $n16[1]['reason_code'] );
		self::assertCount( 4, $rows( 'assignment_submitted' ), 'version 2 notifies both trainers again' );
		$text = (string) wp_json_encode( $this->mails );
		foreach ( array( 'Secret answer', 'Hidden comment', 'Hidden feedback', '7000', '7/10' ) as $leak ) {
			self::assertStringNotContainsString( $leak, $text );
		}
	}

	/**
	 * R-46, R-54…R-58 over REST: status codes, If-Match, idempotency, queue, upload.
	 */
	public function test_rest_routes(): void {
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook.
		$lesson = $this->task(
			array(
				'submission_type' => 'text_and_file',
				'max_points'      => 10,
			)
		);
		$call   = static function ( string $method, string $path, array $params = array(), array $headers = array(), array $files = array() ): \WP_REST_Response {
			$request = new \WP_REST_Request( $method, '/course-captain/v1' . $path );
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
			foreach ( $headers as $key => $value ) {
				$request->set_header( $key, $value );
			}
			if ( array() !== $files ) {
				$request->set_file_params( $files );
			}
			return rest_do_request( $request );
		};
		wp_set_current_user( $this->learner );
		$view = $call( 'GET', '/courses/' . $this->course . '/assignments/' . $lesson );
		self::assertSame( 200, $view->get_status() );
		self::assertSame( 'private, no-store', $view->get_headers()['Cache-Control'] );
		$draft = $call( 'PUT', '/courses/' . $this->course . '/assignments/' . $lesson . '/draft', array( 'text' => 'Hello' ) );
		self::assertSame( 200, $draft->get_status() );
		$uid      = $draft->get_data()['submission_uid'];
		$conflict = $call( 'PUT', '/courses/' . $this->course . '/assignments/' . $lesson . '/draft', array( 'text' => 'x' ), array( 'If-Match' => '"999"' ) );
		self::assertSame( array( 409, 'ccap_version_conflict' ), array( $conflict->get_status(), $conflict->get_data()['code'] ) );

		$incomplete = $call( 'POST', '/assignment-submissions/' . $uid . '/submit' );
		self::assertSame( array( 422, 'ccap_submission_incomplete' ), array( $incomplete->get_status(), $incomplete->get_data()['code'] ) );
		self::assertSame( 'empty', $incomplete->get_data()['data']['reason'] );

		$file   = array(
			'file' => array(
				'name'     => 'answer.pdf',
				'type'     => 'application/pdf',
				'tmp_name' => $this->pdf(),
				'error'    => UPLOAD_ERR_OK,
				'size'     => 2000,
			),
		);
		$upload = $call( 'POST', '/assignment-submissions/' . $uid . '/files', array(), array( 'Idempotency-Key' => 'upload-key-0001' ), $file );
		self::assertSame( 201, $upload->get_status() );
		self::assertSame( 'answer.pdf', $upload->get_data()['file']['name'] );
		$replay = $call( 'POST', '/assignment-submissions/' . $uid . '/files', array(), array( 'Idempotency-Key' => 'upload-key-0001' ), $file );
		self::assertSame( 200, $replay->get_status() );
		self::assertSame( 1, self::count_rows( 'ccap_assignment_file' ) );
		$forged                     = $file;
		$forged['file']['tmp_name'] = ABSPATH . 'wp-config-sample.php';
		$bad                        = $call( 'POST', '/assignment-submissions/' . $uid . '/files', array(), array(), $forged );
		self::assertSame( array( 422, 'upload' ), array( $bad->get_status(), $bad->get_data()['data']['reason'] ) );

		self::assertSame( 200, $call( 'POST', '/assignment-submissions/' . $uid . '/submit' )->get_status() );
		self::assertSame( 200, $call( 'POST', '/assignment-submissions/' . $uid . '/submit' )->get_status(), 'repetition 200' );
		self::assertSame( 0, (int) $call( 'GET', '/grading/queue' )->get_headers()['X-WP-Total'], 'learners see no foreign submissions' );

		wp_set_current_user( $this->trainer );
		$queue = $call( 'GET', '/grading/queue', array( 'course' => (string) $this->course ) );
		self::assertSame( '1', $queue->get_headers()['X-WP-Total'] );
		self::assertSame( array( 'assignment', $uid ), array( $queue->get_data()[0]['type'], $queue->get_data()[0]['submission_uid'] ) );
		$nokey = $call(
			'PUT',
			'/assignment-submissions/' . $uid . '/grade',
			array(
				'action'       => 'grade',
				'points_milli' => 7000,
			)
		);
		self::assertSame( array( 400, 'idempotency_key' ), array( $nokey->get_status(), $nokey->get_data()['data']['reason'] ) );
		$grade = $call(
			'PUT',
			'/assignment-submissions/' . $uid . '/grade',
			array(
				'action'       => 'grade',
				'points_milli' => 7000,
			),
			array( 'Idempotency-Key' => 'grade-key-0001' )
		);
		self::assertSame( 200, $grade->get_status() );
		self::assertSame( array( 'graded', 1 ), array( $grade->get_data()['status'], $grade->get_data()['evaluation_no'] ) );
		self::assertSame( '0', $call( 'GET', '/grading/queue' )->get_headers()['X-WP-Total'] );

		wp_set_current_user( $this->other );
		$foreign = $call( 'GET', '/assignment-submissions/' . $uid );
		self::assertSame( array( 404, 'ccap_not_found' ), array( $foreign->get_status(), $foreign->get_data()['code'] ) );
		$forbidden = $call(
			'PUT',
			'/assignment-submissions/' . $uid . '/grade',
			array(
				'action'       => 'grade',
				'points_milli' => 1000,
			),
			array( 'Idempotency-Key' => 'grade-key-0002' )
		);
		self::assertContains( $forbidden->get_status(), array( 403, 404 ) );
	}

	/**
	 * RT-45: chains of ended courses are deleted with their files; open grading stays.
	 */
	public function test_RT_45_retention(): void {
		$service = AssignmentService::current();
		$lesson  = $this->task(
			array(
				'submission_type' => 'file',
				'max_points'      => 10,
			)
		);
		$l1      = Actor::user( $this->learner );
		$uid     = $service->save_draft( $l1, $this->course, $lesson, null )['submission_uid'];
		$file    = $service->add_file( $l1, $uid, $this->pdf(), 'a.pdf', 'application/pdf' );
		$path    = ProtectedFiles::current()->prepare( $l1, $file['file']['media_uid'] )['path'];
		$db      = Database::current();
		$old     = gmdate( 'Y-m-d H:i:s', time() - 800 * DAY_IN_SECONDS );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_assignment_submission' ) . '` SET updated_at = %s', $old ) );

		RetentionService::run();
		self::assertSame( 1, self::count_rows( 'ccap_assignment_submission' ), 'active grant keeps the data' );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . "` SET status = 'expired', valid_until = %s WHERE user_id = %d", $old, $this->learner ) );
		$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_course_progress' ) . '` WHERE user_id = %d', $this->learner ) );
		$result = RetentionService::run();
		self::assertSame( 1, $result['RT-45_assignments'] );
		self::assertSame( 0, self::count_rows( 'ccap_assignment_submission' ) );
		self::assertFileDoesNotExist( $path );
	}
}
