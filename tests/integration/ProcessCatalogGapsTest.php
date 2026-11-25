<?php
/**
 * R0-41 §4: catalogue cases without a test (ID-003/004/010/014, REV-030…032,
 * REC-026, PRIV-006 with the OPS-01 duplicate "eraser twice", NFR-010/011,
 * SEC-014).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GroupService;
use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Access\SelfEnrollment;
use CourseCaptain\Certificate\CertificateService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Import\Rights;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\SiteIdentity;
use CourseCaptain\Learner\Invitation;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Ops\Diagnostics;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Privacy\RetentionService;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Schema;
use CourseCaptain\Storage\StorageException;
use CourseCaptain\Tests\Support\RaceRunner;

/**
 * Real tables. Multi-process cases (`P`) skip without pcntl/posix; their
 * scale follows `CCAP_RACE_N` / `CCAP_RACE_REPEAT` (load lane, NFR-010).
 *
 * @group R0-41
 */
final class ProcessCatalogGapsTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Scratch directory (import packages).
	 *
	 * @var string
	 */
	private string $dir = '';

	/**
	 * Activate, admin.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		PolicyService::flush();
		add_filter( 'course_captain_internal_subscribers', array( self::class, 'subscribers' ) );
	}

	/**
	 * Remove filters, globals and scratch files.
	 */
	public function tear_down(): void {
		remove_filter( 'course_captain_internal_subscribers', array( self::class, 'subscribers' ) );
		unset( $GLOBALS['wp_rest_auth_cookie'], $_REQUEST['_wpnonce'] );
		if ( '' !== $this->dir ) {
			foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
				wp_delete_file( (string) $file );
			}
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
		parent::tear_down();
	}

	/**
	 * Test subscriber so that exam and certificate events are stored (Free: internal outbox only).
	 *
	 * @param mixed $subscribers Subscribers.
	 * @return array<string, mixed>
	 */
	public static function subscribers( $subscribers ): array {
		return (array) $subscribers + array( 'k17' => array( 'exam.*', 'certificate.*' ) );
	}

	/**
	 * Published post.
	 *
	 * @param string $type      Type.
	 * @param int    $parent_id Parent.
	 * @return int
	 */
	private function post( string $type, int $parent_id = 0 ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_parent'  => $parent_id,
				'post_title'   => $type . ' ' . wp_generate_password( 4, false ),
				'post_content' => 'content',
				'post_author'  => $this->admin,
			)
		);
	}

	/**
	 * Question payload (single choice); `$right` is the correct key.
	 *
	 * @param string $text  Text.
	 * @param string $right Correct key.
	 * @return array<string, mixed>
	 */
	private static function question_data( string $text, string $right = 'A' ): array {
		return array(
			'question_type' => QuestionTypes::SINGLE,
			'question_text' => $text,
			'answers'       => array(
				array(
					'key'     => 'A',
					'text'    => 'a',
					'correct' => 'A' === $right,
				),
				array(
					'key'     => 'B',
					'text'    => 'b',
					'correct' => 'B' === $right,
				),
			),
		);
	}

	/**
	 * Published course with one module (five published questions in the pool) and one revision.
	 *
	 * @return array{course: int, module: int, questions: list<int>}
	 */
	private function world(): array {
		$admin  = Actor::user( $this->admin );
		$course = $this->post( 'ccap_course' );
		$module = $this->post( 'ccap_module' );
		PolicyService::flush();
		CourseStructure::current()->assign_module( $admin, $course, $module );
		$repo      = QuestionRepository::current();
		$questions = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$question = $repo->create( $admin, self::question_data( 'Q' . $i . ' ' . wp_generate_password( 6, false ) ) );
			$repo->publish( $admin, $question );
			$repo->add_to_pool( $admin, $module, $question );
			$questions[] = $question;
		}
		CoursePublisher::current()->publish( $admin, $course );
		return array(
			'course'    => $course,
			'module'    => $module,
			'questions' => $questions,
		);
	}

	/**
	 * Learner with an active manual grant on a course.
	 *
	 * @param int $course Course.
	 * @return int
	 */
	private function learner( int $course ): int {
		$user = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'k17-' . wp_generate_password( 8, false ) . '@example.org',
			)
		);
		$db   = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
		return $user;
	}

	/**
	 * Exam rule over one module (five questions, pass 3/5).
	 *
	 * @param int  $course  Course.
	 * @param int  $module  Module.
	 * @param bool $publish Publish (false = draft).
	 * @return int
	 */
	private function rule( int $course, int $module, bool $publish = true ): int {
		$admin = Actor::user( $this->admin );
		$rules = ExamRules::current();
		$rule  = $rules->create(
			$admin,
			$course,
			'v' . wp_generate_password( 4, false ),
			array(
				'min_ratio' => array(
					'num' => 3,
					'den' => 5,
				),
			),
			600
		);
		$rules->set_blueprint( $admin, $rule, $module, 5 );
		if ( $publish ) {
			$rules->publish( $admin, $rule );
		}
		return $rule;
	}

	/**
	 * Count rows.
	 *
	 * @param string $table Table.
	 * @param string $where Trusted condition.
	 * @return int
	 */
	private static function rows( string $table, string $where = '1=1' ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '` WHERE ' . $where );
	}

	/**
	 * One session row.
	 *
	 * @param int $session Session.
	 * @return array<string, mixed>
	 */
	private static function session( int $session ): array {
		$db = Database::current();
		return (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
	}

	/**
	 * Position => question and revision of a session.
	 *
	 * @param int $session Session.
	 * @return array<int, array{question_id: int, revision_id: int}>
	 */
	private static function positions( int $session ): array {
		$db  = Database::current();
		$out = array();
		foreach ( $db->rows( $db->prepare( 'SELECT position, question_id, revision_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d ORDER BY position', $session ) ) as $row ) {
			$out[ (int) $row['position'] ] = array(
				'question_id' => (int) $row['question_id'],
				'revision_id' => (int) $row['revision_id'],
			);
		}
		return $out;
	}

	/**
	 * Rows of the tables a claim writes (group, seats, grants, events, outbox, audit).
	 *
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function claim_state(): array {
		$db  = Database::current();
		$out = array();
		foreach ( array( 'ccap_group', 'ccap_seat', 'ccap_grant', 'ccap_grant_event', 'ccap_outbox_event', 'ccap_audit_log' ) as $table ) {
			$out[ $table ] = $db->rows( 'SELECT * FROM `' . $db->table( $table ) . '` ORDER BY id' );
		}
		return $out;
	}

	/**
	 * Rows of every own table.
	 *
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function all_rows(): array {
		$db  = Database::current();
		$out = array();
		foreach ( array_keys( Schema::tables() ) as $table ) {
			$out[ $table ] = $db->rows( 'SELECT * FROM `' . $db->table( $table ) . '` ORDER BY 1' );
		}
		return $out;
	}

	/**
	 * Code of a refused call ('ok' when it passed).
	 *
	 * @param callable $call Call.
	 * @return string
	 */
	private static function code( callable $call ): string {
		try {
			$call();
			return 'ok';
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
	}

	/**
	 * Group of an owner on a course with a group link.
	 *
	 * @param int $owner  Owner.
	 * @param int $course Course.
	 * @param int $seats  Seats.
	 * @return array{group: int, link: string}
	 */
	private function group( int $owner, int $course, int $seats ): array {
		$service = GroupService::current();
		$group   = $service->create( Actor::user( $this->admin ), $owner, 'Team ' . wp_generate_password( 4, false ), $seats, array( array( 'course_id' => $course ) ), wp_generate_uuid4() );
		return array(
			'group' => $group,
			'link'  => $service->rotate_link( Actor::user( $owner ), $group ),
		);
	}

	/**
	 * New subscriber.
	 *
	 * @return int
	 */
	private function user(): int {
		return (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'k17u-' . wp_generate_password( 8, false ) . '@example.org',
			)
		);
	}

	/**
	 * ID-003 (ID-02): the invitation preview (REST R-31, invitation page,
	 * service) with a valid group link and a valid personal invitation
	 * changes nothing: no seat, grant, event, outbox or audit row.
	 *
	 * @group ID-003
	 * @group ID-02
	 */
	public function test_ID_003_preview_with_valid_token_changes_nothing(): void {
		$world   = $this->world();
		$owner   = $this->user();
		$group   = $this->group( $owner, $world['course'], 3 );
		$invite  = GroupService::current()->invite( Actor::user( $owner ), $group['group'], 'k17-invited-' . wp_generate_password( 6, false ) . '@example.org' );
		$viewer  = $this->user();
		$service = GroupService::current();
		self::assertNotNull( $invite );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global reset for a fresh route registry.
		wp_set_current_user( $viewer );
		$before = self::claim_state();
		foreach ( array( $group['link'], $invite['token'] ) as $token ) {
			$rest = rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/groups/join/' . $token ) );
			self::assertSame( 200, $rest->get_status(), (string) wp_json_encode( $rest->get_data() ) );
			self::assertTrue( $rest->get_data()['seats_free'] );
			$preview = $service->preview( Actor::user( $viewer ), $token );
			self::assertFalse( $preview['already_member'] );
			$page = LearningArea::page( Actor::user( $viewer ), '', '', $token );
			if ( $group['link'] === $token ) {
				self::assertStringContainsString( 'name="ccap_do" value="claim"', $page, 'the page only offers the form' );
			}
		}
		self::assertSame( $before, self::claim_state(), 'preview GET changes nothing' );
		self::assertSame( 0, self::rows( 'ccap_seat', 'user_id = ' . $viewer ) );
		self::assertSame( 'invited', (string) Database::current()->scalar( Database::current()->prepare( 'SELECT status FROM `' . Database::current()->table( 'ccap_seat' ) . '` WHERE id = %d', $invite['seat_id'] ) ) );
	}

	/**
	 * ID-004 (ID-02, OPS-02): a claim without nonce, with a wrong nonce or
	 * by GET changes nothing; REST cookie requests with a wrong nonce are
	 * refused (403), without nonce they run anonymously (401). With the
	 * right nonce the same form post claims (control).
	 *
	 * @group ID-004
	 * @group ID-02
	 */
	public function test_ID_004_claim_without_nonce_or_by_get_changes_nothing(): void {
		$world                     = $this->world();
		$owner                     = $this->user();
		$group                     = $this->group( $owner, $world['course'], 3 );
		$viewer                    = $this->user();
		$actor                     = Actor::user( $viewer );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global reset for a fresh route registry.
		wp_set_current_user( $viewer );
		$before = self::claim_state();
		$posts  = array(
			array(),
			array( '_wpnonce' => 'invalid' ),
			array( '_wpnonce' => wp_create_nonce( 'another_action' ) ),
		);
		foreach ( $posts as $extra ) {
			$target = LearningArea::handle_post(
				$actor,
				'',
				array(
					'ccap_do'         => 'claim',
					Invitation::PARAM => $group['link'],
				) + $extra
			);
			self::assertStringContainsString( 'ccap_status=ccap_forbidden', $target );
		}
		// GET: the invitation page renders, it never claims.
		LearningArea::page( $actor, '', '', $group['link'] );
		// The REST claim route only accepts POST.
		$get = new \WP_REST_Request( 'GET', '/course-captain/v1/groups/join' );
		$get->set_query_params( array( 'token' => $group['link'] ) );
		self::assertContains( rest_do_request( $get )->get_status(), array( 404, 405 ) );
		self::assertSame( $before, self::claim_state(), 'nothing changed' );

		// Cookie authentication: a wrong nonce is refused before any route runs.
		$GLOBALS['wp_rest_auth_cookie'] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global of the cookie check.
		$_REQUEST['_wpnonce']           = 'invalid';
		$refused                        = rest_cookie_check_errors( null );
		self::assertInstanceOf( \WP_Error::class, $refused );
		self::assertSame( 403, $refused->get_error_data()['status'] );
		// Without nonce the request runs anonymously: the claim route answers 401.
		unset( $_REQUEST['_wpnonce'] );
		self::assertTrue( rest_cookie_check_errors( null ) );
		self::assertSame( 0, get_current_user_id() );
		$post = new \WP_REST_Request( 'POST', '/course-captain/v1/groups/join' );
		$post->set_param( 'token', $group['link'] );
		self::assertSame( 401, rest_do_request( $post )->get_status() );
		unset( $GLOBALS['wp_rest_auth_cookie'] );
		self::assertSame( $before, self::claim_state(), 'nothing changed' );

		// Control: the right nonce claims.
		wp_set_current_user( $viewer );
		LearningArea::handle_post(
			$actor,
			'',
			array(
				'ccap_do'         => 'claim',
				Invitation::PARAM => $group['link'],
				'_wpnonce'        => wp_create_nonce( Invitation::NONCE ),
			)
		);
		self::assertSame( 1, self::rows( 'ccap_seat', "status = 'active' AND user_id = " . $viewer ) );
	}

	/**
	 * ID-010 (ID-05, GM-102) with the duplicate of ID-05 (two deletion
	 * requests): the owner first asks for erasure, then the account is
	 * deleted. The group keeps running (`owner_user_id = 0`), members keep
	 * seat and grant, the admin manages the group, the old owner ID has no
	 * access; the second request changes nothing more.
	 *
	 * @group ID-010
	 * @group ID-05
	 */
	public function test_ID_010_owner_deletes_account_group_continues(): void {
		$world   = $this->world();
		$owner   = $this->user();
		$member  = $this->user();
		$group   = $this->group( $owner, $world['course'], 3 );
		$service = GroupService::current();
		$seat    = $service->claim( Actor::user( $member ), $group['link'] );
		$db      = Database::current();
		$email   = (string) get_userdata( $owner )->user_email;
		self::assertSame( 1, self::rows( 'ccap_grant', "status = 'active' AND user_id = " . $member . ' AND seat_id = ' . $seat ) );

		self::assertTrue( PrivacyService::erase( $email )['items_removed'] );
		$after_first = self::all_rows();
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $owner );
		self::assertSame( $after_first, self::all_rows(), 'the deletion after the erasure changes nothing more' );
		self::assertFalse( PrivacyService::erase( $email )['items_removed'], 'a third request finds nothing' );
		self::assertSame( 1, self::rows( 'ccap_audit_log', "action = 'personal_data_erased'" ), 'one erasure entry' );

		$row = (array) $db->row( $db->prepare( 'SELECT status, owner_user_id FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $group['group'] ) );
		self::assertSame( array( 'active', '0' ), array( $row['status'], (string) $row['owner_user_id'] ) );
		self::assertSame( 'active', (string) $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_seat' ) . '` WHERE id = %d', $seat ) ) );
		self::assertSame( 1, self::rows( 'ccap_grant', "status = 'active' AND user_id = " . $member . ' AND seat_id = ' . $seat ), 'member keeps access' );
		PolicyService::flush();
		self::assertSame( 'ok', ( new \CourseCaptain\Policy\Relations( $db ) )->effective_access( $member, $world['course'], 0, 'view' ) );

		// The admin manages the group; the old owner ID has no access.
		$admin = Actor::user( $this->admin );
		self::assertCount( 1, $service->members( $admin, $group['group'] ) );
		self::assertNotNull( $service->invite( $admin, $group['group'], 'k17-new-' . wp_generate_password( 6, false ) . '@example.org' ) );
		self::assertSame( array(), $service->owned( Actor::user( $owner ) ) );
		self::assertNotSame( 'ok', self::code( fn() => $service->rename( Actor::user( $owner ), $group['group'], 'Taken over' ) ) );
	}

	/**
	 * ID-014 (ID-05, R0-09 R-12, CON-014): account deletion and join at the
	 * same time leave no seat or grant on the deleted account. `I`: the join
	 * commits inside the deletion (after the first erasure pass, before the
	 * account row is gone); a join after the deletion is refused. `P`: both
	 * in parallel processes.
	 *
	 * @group ID-014
	 * @group ID-05
	 * @group CON-014
	 */
	public function test_ID_014_account_deletion_parallel_to_join(): void {
		$world  = $this->world();
		$owner  = $this->user();
		$group  = $this->group( $owner, $world['course'], 5 );
		$member = $this->user();
		$joined = 0;
		$join   = static function ( $user_id ) use ( $member, $group, &$joined ): void {
			if ( $member === (int) $user_id ) {
				$joined = GroupService::current()->claim( Actor::user( $member ), $group['link'] );
			}
		};
		require_once ABSPATH . 'wp-admin/includes/user.php';
		add_action( 'delete_user', $join, 20 );
		try {
			wp_delete_user( $member );
		} finally {
			remove_action( 'delete_user', $join, 20 );
		}
		self::assertGreaterThan( 0, $joined, 'the join ran during the deletion' );
		self::assertSame( 0, self::rows( 'ccap_seat', 'user_id = ' . $member ), 'no seat on the deleted account' );
		self::assertSame( 0, self::rows( 'ccap_grant', 'user_id = ' . $member ), 'no grant on the deleted account' );
		self::assertSame( 'removed', (string) Database::current()->scalar( Database::current()->prepare( 'SELECT status FROM `' . Database::current()->table( 'ccap_seat' ) . '` WHERE id = %d', $joined ) ) );
		self::assertNotSame( 'ok', self::code( fn() => GroupService::current()->claim( Actor::user( $member ), $group['link'] ) ), 'join after the deletion' );
		self::assertSame( 0, self::rows( 'ccap_seat', "status = 'active' AND group_id = " . $group['group'] ) );

		if ( ! RaceRunner::available() ) {
			return;
		}
		$rounds = RaceRunner::repeat( 2 );
		for ( $round = 0; $round < $rounds; $round++ ) {
			$user    = $this->user();
			$link    = $group['link'];
			$results = RaceRunner::run(
				'ID-014',
				array(
					'delete' => static function () use ( $user ): bool {
						require_once ABSPATH . 'wp-admin/includes/user.php';
						return wp_delete_user( $user );
					},
					'join'   => static fn() => GroupService::current()->claim( Actor::user( $user ), $link ),
				)
			);
			self::assertTrue( ! empty( $results['delete']['ok'] ), (string) wp_json_encode( $results ) );
			self::assertNotSame( 'exception', $results['join']['code'] ?? '', (string) wp_json_encode( $results ) );
			self::assertSame( 0, self::rows( 'ccap_seat', 'user_id = ' . $user ), 'no seat: ' . wp_json_encode( $results ) );
			self::assertSame( 0, self::rows( 'ccap_grant', 'user_id = ' . $user ), 'no grant: ' . wp_json_encode( $results ) );
		}
	}

	/**
	 * REV-030 (R0-23 §6): `saveAnswer` in parallel to publishing (a question
	 * of the attempt corrected, another archived, the rule replaced, a new
	 * course revision): both commit, the answer is stored, the attempt keeps
	 * its revisions and snapshot and is evaluated against them. REV-032:
	 * an attempt start in parallel to publishing reads one consistent rule
	 * and published revisions only; no deadlock reaches the caller.
	 *
	 * @group REV-030
	 * @group REV-032
	 * @group R1-27
	 */
	public function test_REV_030_032_exam_in_parallel_to_publishing(): void {
		if ( ! RaceRunner::available() ) {
			self::markTestSkipped( 'pcntl/posix required.' );
		}
		$admin  = $this->admin;
		$rounds = RaceRunner::repeat( 1 );
		for ( $round = 0; $round < $rounds; $round++ ) {
			// REV-030.
			$world    = $this->world();
			$rule     = $this->rule( $world['course'], $world['module'] );
			$learner  = $this->learner( $world['course'] );
			$session  = ExamSessions::current()->start( Actor::user( $learner ), $rule, null, wp_generate_uuid4(), new Randomizer( 'rev030-' . $round ) );
			$before   = self::positions( $session );
			$snapshot = (string) self::session( $session )['snapshot_hash'];
			$fixed    = $before[1]['question_id'];
			$archived = $before[2]['question_id'];
			$next     = $this->rule( $world['course'], $world['module'], false );
			$course   = $world['course'];
			$revision = (int) CoursePublisher::current()->current_revision( $course )['id'];
			$publish  = static function () use ( $admin, $fixed, $archived, $next, $course ): int {
				$actor = Actor::user( $admin );
				$repo  = QuestionRepository::current();
				$repo->publish( $actor, $fixed, $repo->add_revision( $actor, $fixed, self::question_data( 'Corrected', 'B' ) ) );
				$repo->archive( $actor, $archived );
				ExamRules::current()->publish( $actor, $next );
				return CoursePublisher::current()->publish( $actor, $course );
			};
			$results  = RaceRunner::run(
				'REV-030',
				array(
					'save'    => static fn() => ExamSessions::current()->save_answer( Actor::user( $learner ), $session, 1, array( 'A' ) )['applied'],
					'publish' => $publish,
				)
			);
			self::assertSame( array( 'ok' => 2 ), RaceRunner::outcomes( $results ), (string) wp_json_encode( $results ) );
			self::assertTrue( $results['save']['value'], 'answer applied' );
			self::assertNotSame( $revision, (int) CoursePublisher::current()->current_revision( $course )['id'], 'a new course revision exists' );
			self::assertSame( $before, self::positions( $session ), 'revisions of the attempt unchanged' );
			self::assertSame( $snapshot, (string) self::session( $session )['snapshot_hash'] );
			$db = Database::current();
			self::assertSame( '1', (string) $db->scalar( $db->prepare( 'SELECT is_correct FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d AND position = 1', $session ) ), 'evaluated against the old revision (A right)' );
			self::assertSame( 'evaluated', ExamSessions::current()->submit( Actor::user( $learner ), $session )['status'] );
			self::assertSame( $before, self::positions( $session ) );
			self::assertSame( (int) $rule, (int) self::session( $session )['rule_id'] );

			// REV-032.
			$world    = $this->world();
			$rule     = $this->rule( $world['course'], $world['module'] );
			$learner  = $this->learner( $world['course'] );
			$fixed    = $world['questions'][0];
			$old_rev  = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $fixed ) );
			$next     = $this->rule( $world['course'], $world['module'], false );
			$course   = $world['course'];
			$revision = (int) CoursePublisher::current()->current_revision( $course )['id'];
			$start    = static function () use ( $learner, $course ): int {
				$db = Database::current();
				for ( $try = 0; ; $try++ ) {
					$current = (int) $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_exam_rule' ) . "` WHERE course_id = %d AND status = 'published' ORDER BY id DESC LIMIT 1", $course ) );
					try {
						return ExamSessions::current()->start( Actor::user( $learner ), $current, null, wp_generate_uuid4() );
					} catch ( CourseException $e ) {
						// The rule read just before was retired by the publication: read again once.
						if ( $try > 0 || 'ccap_exam_not_startable' !== $e->error_code ) {
							throw $e;
						}
					}
				}
			};
			$publish  = static function () use ( $admin, $fixed, $next, $course ): int {
				$actor = Actor::user( $admin );
				$repo  = QuestionRepository::current();
				$repo->publish( $actor, $fixed, $repo->add_revision( $actor, $fixed, self::question_data( 'Corrected start', 'B' ) ) );
				ExamRules::current()->publish( $actor, $next );
				return CoursePublisher::current()->publish( $actor, $course );
			};
			$results  = RaceRunner::run(
				'REV-032',
				array(
					'start'   => $start,
					'publish' => $publish,
				)
			);
			self::assertSame( array( 'ok' => 2 ), RaceRunner::outcomes( $results ), (string) wp_json_encode( $results ) );
			$new_rev = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $fixed ) );
			$row     = self::session( (int) $results['start']['value'] );
			$frozen  = (array) json_decode( (string) $row['snapshot'], true );
			self::assertContains( (int) $row['rule_id'], array( $rule, $next ) );
			self::assertSame( (int) $row['rule_id'], (int) $frozen['rule']['id'], 'snapshot and row name the same rule' );
			self::assertSame( 'sha256:' . hash( 'sha256', (string) $row['snapshot'] ), (string) $row['snapshot_hash'] );
			self::assertContains( (int) $row['course_revision_id'], array( $revision, (int) $results['publish']['value'] ) );
			foreach ( self::positions( (int) $row['id'] ) as $item ) {
				$allowed = $fixed === $item['question_id'] ? array( $old_rev, $new_rev ) : array( (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $item['question_id'] ) ) );
				self::assertContains( $item['revision_id'], $allowed, 'only published revisions' );
			}
		}
	}

	/**
	 * REV-031 (R0-23 §6): `saveAnswer` and `submit` in parallel to the import
	 * of a new package version of the same course (activation): exactly one
	 * result, evaluated against the snapshot; the attempt keeps its revisions.
	 *
	 * @group REV-031
	 * @group R1-27
	 */
	public function test_REV_031_save_and_submit_in_parallel_to_import_activation(): void {
		if ( ! RaceRunner::available() ) {
			self::markTestSkipped( 'pcntl/posix required.' );
		}
		$this->dir = get_temp_dir() . 'ccap-rev031-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->dir );
		$admin   = Actor::user( $this->admin );
		$service = ImportService::current();
		$import  = function ( string $suffix, bool $run ) use ( $admin, $service ): int {
			$file  = PackageExporter::write( self::package_records( $suffix ), 'k17-rev031', 'REV-031 ' . $suffix, $this->dir . '/p' . $suffix . '.zip', 1000 );
			$batch = $service->upload( $admin, $file );
			self::assertSame( 'validated', $service->validate( $admin, $batch )['status'] );
			self::assertSame( 'previewed', $service->preview( $admin, $batch )['status'] );
			$service->approve( $admin, $batch );
			if ( $run ) {
				$service->run( $admin, $batch );
			}
			return $batch;
		};
		$first   = $import( 'v1', true );
		$db      = Database::current();
		$object  = static fn( string $type, string $source ): int => (int) $db->scalar( $db->prepare( 'SELECT object_id FROM `' . $db->table( 'ccap_source_map' ) . "` WHERE source_system = 'k17-rev031' AND object_type = %s AND source_id = %s", $type, $source ) );
		$course  = $object( 'course', 'RC-1' );
		$module  = $object( 'module', 'RM-1' );
		self::assertGreaterThan( 0, $course );
		self::assertSame( 'published', $service->get( $first )['status'] );
		$rights = Rights::current();
		$rights->course_release( $admin, $course, 'REV-031 fixture', $rights->course_release_preview( $admin, $course )['count'] );
		PolicyService::flush();
		$rule    = $this->rule( $course, $module );
		$learner = $this->learner( $course );
		$session = ExamSessions::current()->start( Actor::user( $learner ), $rule, null, wp_generate_uuid4(), new Randomizer( 'rev031' ) );
		ExamSessions::current()->save_answer( Actor::user( $learner ), $session, 1, array( 'A' ) );
		$before   = self::positions( $session );
		$snapshot = (string) self::session( $session )['snapshot_hash'];
		$second   = $import( 'v2', false );
		$admin_id = $this->admin;
		$results  = RaceRunner::run(
			'REV-031',
			array(
				'import' => static fn() => (string) ImportService::current()->get( $second )['status'] . ':' . (string) wp_json_encode( ImportService::current()->run( Actor::user( $admin_id ), $second )['questions'] ?? array() ),
				'save'   => static fn() => ExamSessions::current()->save_answer( Actor::user( $learner ), $session, 2, array( 'A' ) )['applied'],
				'submit' => static fn() => ExamSessions::current()->submit( Actor::user( $learner ), $session )['status'],
			)
		);
		self::assertTrue( ! empty( $results['import']['ok'] ), (string) wp_json_encode( $results ) );
		self::assertSame( array( 'ok' => true ), array( 'ok' => ! empty( $results['submit']['ok'] ) ), (string) wp_json_encode( $results ) );
		self::assertSame( 'evaluated', $results['submit']['value'] );
		self::assertTrue( ! empty( $results['save']['ok'] ) || str_starts_with( (string) ( $results['save']['code'] ?? '' ), 'ccap_exam_' ), (string) wp_json_encode( $results ) );
		self::assertSame( 'published', $service->get( $second )['status'] );
		$row = self::session( $session );
		self::assertSame( array( 'evaluated', '1' ), array( (string) $row['status'], (string) $row['evaluation_no'] ), 'exactly one result' );
		self::assertSame( 1, self::rows( 'ccap_outbox_event', "event_type IN ('exam.passed', 'exam.failed') AND user_ref = 'user:" . $learner . "'" ) );
		self::assertSame( $before, self::positions( $session ), 'revisions of the attempt unchanged' );
		self::assertSame( $snapshot, (string) $row['snapshot_hash'] );
		$current = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $before[1]['question_id'] ) );
		self::assertNotSame( $before[1]['revision_id'], $current, 'the import switched the question to a new revision' );
	}

	/**
	 * Package records for REV-031: five questions, one lesson, one module, one course.
	 *
	 * @param string $suffix Question text suffix (a new text makes a new revision).
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function package_records( string $suffix ): array {
		$out = array(
			'questions' => array(),
			'lessons'   => array(),
			'modules'   => array(),
			'courses'   => array(),
		);
		for ( $i = 1; $i <= 5; $i++ ) {
			$out['questions'][] = PackageExporter::sealed(
				array(
					'source_id' => 'RQ-' . $i,
					'revision'  => array(
						'question_type' => 'single_choice',
						'text_html'     => 'Frage ' . $i . ' ' . $suffix,
						'settings'      => (object) array(),
					),
					'answers'   => array(
						array(
							'key'       => 'A',
							'text_html' => 'richtig',
							'correct'   => true,
						),
						array(
							'key'       => 'B',
							'text_html' => 'falsch',
							'correct'   => false,
						),
					),
					'tags'      => array(),
				)
			);
		}
		$out['lessons'][] = PackageExporter::sealed(
			array(
				'source_id'     => 'RL-1',
				'title'         => 'Lektion',
				'body_html'     => '<p>Inhalt</p>',
				'activity_type' => 'lesson',
				'settings'      => (object) array(),
			)
		);
		$out['modules'][] = PackageExporter::sealed(
			array(
				'source_id'     => 'RM-1',
				'title'         => 'Modul',
				'summary'       => '',
				'lessons'       => array(
					array(
						'lesson' => 'RL-1',
						'sort'   => 1,
					),
				),
				'question_pool' => array_map(
					static fn( int $n ): array => array(
						'question' => 'RQ-' . $n,
						'sort'     => $n,
					),
					range( 1, 5 )
				),
			)
		);
		$out['courses'][] = PackageExporter::sealed(
			array(
				'source_id'        => 'RC-1',
				'title'            => 'Kurs REV-031',
				'slug'             => 'rev031-kurs-' . strtolower( wp_generate_password( 4, false ) ),
				'summary'          => '',
				'description_html' => '<p>Kurs</p>',
				'modules'          => array(
					array(
						'module'   => 'RM-1',
						'sort'     => 1,
						'required' => true,
					),
				),
			)
		);
		return $out;
	}

	/**
	 * SEC-014 (LEARN-04, T-16, PI-04): 20 parallel submits of one attempt
	 * plus the cron expiry → exactly one result, one exam event, one
	 * certificate; identical answers for every submit. Second round: the
	 * attempt is overdue, so the cron competes for real.
	 *
	 * @group SEC-014
	 * @group LEARN-04
	 */
	public function test_SEC_014_parallel_submits_and_cron_give_one_result(): void {
		if ( ! RaceRunner::available() ) {
			self::markTestSkipped( 'pcntl/posix required.' );
		}
		Settings::save( Actor::user( $this->admin ), 'certificates', array( 'auto_issue' => true ), array( 'auto_issue' ), Settings::rev( 'certificates' ) );
		$world = $this->world();
		$rule  = $this->rule( $world['course'], $world['module'] );
		foreach ( array( false, true ) as $overdue ) {
			$learner = $this->learner( $world['course'] );
			$session = ExamSessions::current()->start( Actor::user( $learner ), $rule, null, wp_generate_uuid4(), new Randomizer( 'sec014' ) );
			foreach ( array( 1, 2, 3, 4, 5 ) as $position ) {
				ExamSessions::current()->save_answer( Actor::user( $learner ), $session, $position, array( 'A' ) );
			}
			$db = Database::current();
			if ( $overdue ) {
				// Answers given in time, deadline (plus tolerance) passed since.
				$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session_question' ) . '` SET answered_at = %s WHERE session_id = %d', gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ), $session ) );
				$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . '` SET started_at = %s, deadline_at = %s WHERE id = %d', gmdate( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ), $session ) );
			}
			$jobs = array();
			$n    = RaceRunner::n( 20 );
			for ( $i = 0; $i < $n; $i++ ) {
				$jobs[ 'submit' . $i ] = static fn() => ExamSessions::current()->submit( Actor::user( $learner ), $session );
			}
			$jobs['cron1'] = static fn() => ExamSessions::current()->expire_due();
			if ( $overdue ) {
				$jobs['cron2'] = static fn() => ExamSessions::current()->expire_due();
			}
			$results = RaceRunner::run( 'SEC-014', $jobs );
			self::assertSame( array( 'ok' => count( $jobs ) ), RaceRunner::outcomes( $results ), (string) wp_json_encode( $results ) );
			$answers = array();
			foreach ( $results as $name => $result ) {
				if ( str_starts_with( (string) $name, 'submit' ) ) {
					$answers[] = (string) wp_json_encode( $result['value'] );
				}
			}
			self::assertCount( 1, array_unique( $answers ), 'identical results: ' . wp_json_encode( $results ) );
			$row = self::session( $session );
			self::assertSame( array( 'evaluated', '1', '1' ), array( (string) $row['status'], (string) $row['evaluation_no'], (string) $row['passed'] ) );
			self::assertContains( (string) $row['end_reason'], $overdue ? array( 'submitted', 'expired' ) : array( 'submitted' ) );
			self::assertSame( 1, self::rows( 'ccap_outbox_event', "event_type IN ('exam.passed', 'exam.failed') AND user_ref = 'user:" . $learner . "'" ), 'one exam event' );
			if ( ! $overdue ) {
				OutboxDispatcher::current()->dispatch();
				OutboxDispatcher::current()->dispatch();
				self::assertSame( 1, self::rows( 'ccap_certificate', "evidence_type = 'exam_session' AND evidence_id = " . $session ), 'one certificate' );
				self::assertSame( 1, self::rows( 'ccap_certificate', 'user_id = ' . $learner ) );
			}
		}
	}

	/**
	 * PRIV-006 (OPS-01, PSD-012): retention with time travel. Audit rows
	 * older than 4 years, raw answers older than 2 years, attempts older than
	 * 10 years (without certificate), processed inbox events older than
	 * 90 days and import protocol rows older than 180 days go; rows one day
	 * younger stay. A step deletes at most one batch per run; the next run
	 * takes the rest; then a run has no effect. The counters of the last run
	 * are kept for the system status.
	 *
	 * @group PRIV-006
	 * @group OPS-01
	 */
	public function test_PRIV_006_retention_time_travel_batches_and_second_run(): void {
		$f    = new PolicyFixtures();
		$db   = Database::current();
		$now  = time();
		$at   = static fn( string $relative ): string => gmdate( 'Y-m-d H:i:s', (int) strtotime( $relative, $now ) );
		$set  = static function ( string $table, string $column, string $value, int $id ) use ( $db ): void {
			$db->execute( $db->prepare( 'UPDATE `' . $db->table( $table ) . "` SET `{$column}` = %s WHERE id = %d", $value, $id ) );
		};
		$keep = array();
		$gone = array();

		// Audit (RT-23, 4 years).
		$audit_old  = AuditLog::write( $db, Actor::cron(), 'priv006.probe', 'probe', 'old' );
		$audit_keep = AuditLog::write( $db, Actor::cron(), 'priv006.probe', 'probe', 'young' );
		$set( 'ccap_audit_log', 'created_at', $at( '-4 years -1 day' ), $audit_old );
		$set( 'ccap_audit_log', 'created_at', $at( '-4 years +1 day' ), $audit_keep );
		$gone[] = array( 'ccap_audit_log', $audit_old );
		$keep[] = array( 'ccap_audit_log', $audit_keep );

		// Raw learning answers (RT-04, 2 years; no course end needed without course).
		$revision = $db->insert(
			'ccap_question_revision',
			array(
				'question_id'   => $f->question_a,
				'revision_no'   => 1,
				'question_type' => 'single_choice',
				'question_text' => 'q',
				'content_hash'  => str_repeat( 'b', 64 ),
				'created_at'    => $db->now(),
			)
		);
		foreach ( array(
			'-2 years -1 day' => 'gone',
			'-2 years +1 day' => 'keep',
		) as $relative => $fate ) {
			$id = $db->insert(
				'ccap_attempt',
				array(
					'user_id'         => $f->l1,
					'question_id'     => $f->question_a,
					'revision_id'     => $revision,
					'idempotency_key' => wp_generate_uuid4(),
					'created_at'      => $at( $relative ),
				)
			);
			if ( 'gone' === $fate ) {
				$gone[] = array( 'ccap_attempt', $id );
			} else {
				$keep[] = array( 'ccap_attempt', $id );
			}
		}

		// Exam attempts (RT-06, 10 years); an attempt that is a certificate's evidence stays.
		$set( 'ccap_exam_session', 'started_at', $at( '-10 years -1 day' ), $f->session_tut );
		$set( 'ccap_exam_session', 'started_at', $at( '-10 years +1 day' ), $f->session_b );
		$set( 'ccap_exam_session', 'started_at', $at( '-11 years' ), $f->session_l1_submitted );
		$gone[] = array( 'ccap_exam_session', $f->session_tut );
		$keep[] = array( 'ccap_exam_session', $f->session_b );
		$keep[] = array( 'ccap_exam_session', $f->session_l1_submitted );

		// Processed inbox events (RT-27, 90 days).
		foreach ( array(
			'-91 days' => 'gone',
			'-89 days' => 'keep',
		) as $relative => $fate ) {
			$id = $db->insert(
				'ccap_inbox_event',
				array(
					'connector_id'     => 'k17',
					'event_id'         => wp_generate_uuid4(),
					'contract_version' => '1.0',
					'event_type'       => 'order.paid',
					'payload'          => '{}',
					'payload_hash'     => str_repeat( 'c', 64 ),
					'status'           => 'processed',
					'occurred_at'      => $at( $relative ),
					'received_at'      => $at( $relative ),
					'processed_at'     => $at( $relative ),
				)
			);
			if ( 'gone' === $fate ) {
				$gone[] = array( 'ccap_inbox_event', $id );
			} else {
				$keep[] = array( 'ccap_inbox_event', $id );
			}
		}

		// Import protocol (RT-25, 180 days).
		foreach ( array(
			'-181 days' => 'gone',
			'-179 days' => 'keep',
		) as $relative => $fate ) {
			$id = $db->insert(
				'ccap_import_item_log',
				array(
					'batch_id'   => $f->import_batch,
					'entity'     => 'questions',
					'source_id'  => 'Q-' . $fate,
					'action'     => 'created',
					'created_at' => $at( $relative ),
				)
			);
			if ( 'gone' === $fate ) {
				$gone[] = array( 'ccap_import_item_log', $id );
			} else {
				$keep[] = array( 'ccap_import_item_log', $id );
			}
		}

		// Batch limit: ended grants (RT-12, 10 years) one more than a batch.
		$values  = array();
		$updated = $at( '-10 years -1 day' );
		for ( $i = 0; $i <= RetentionService::BATCH; $i++ ) {
			$values[] = $db->prepare( "(%s, %d, %d, 'expired', 'manual', %s, %s, %s)", $db->uuid(), $f->l2, $f->course_a, 'k17-batch-' . $i, $updated, $updated );
		}
		$db->execute( 'INSERT INTO `' . $db->table( 'ccap_grant' ) . '` (uid, user_id, course_id, status, source_type, source_id, created_at, updated_at) VALUES ' . implode( ',', $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Rows prepared above.
		$batch_where = "source_id LIKE 'k17-batch-%'";

		$exists = static fn( array $ref ): bool => null !== $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( $ref[0] ) . '` WHERE id = %d', $ref[1] ) );
		$first  = RetentionService::run( $now );
		self::assertSame( array(), array_filter( $first, 'is_string' ), 'no step failed: ' . wp_json_encode( $first ) );
		foreach ( $gone as $ref ) {
			self::assertFalse( $exists( $ref ), 'due: ' . implode( '#', $ref ) );
		}
		foreach ( $keep as $ref ) {
			self::assertTrue( $exists( $ref ), 'not yet due: ' . implode( '#', $ref ) );
		}
		self::assertSame( RetentionService::BATCH, $first['RT-12_ended_grants'], 'one batch per run' );
		self::assertSame( 1, self::rows( 'ccap_grant', $batch_where ) );
		$state = get_option( 'course_captain_retention_state' );
		self::assertSame( $first, $state['result'], 'counters for the system status' );
		self::assertSame( gmdate( 'c', $now ), $state['last_run'] );
		self::assertGreaterThan( 0, self::rows( 'ccap_audit_log', "action = 'audit_pruned'" ) );

		$second = RetentionService::run( $now );
		self::assertSame( 1, $second['RT-12_ended_grants'], 'the rest in the next run' );
		self::assertSame( 0, self::rows( 'ccap_grant', $batch_where ) );

		$audit = self::rows( 'ccap_audit_log' );
		$rows  = self::all_rows();
		unset( $rows['ccap_audit_log'] );
		$third = RetentionService::run( $now );
		self::assertSame( 0, array_sum( array_map( 'intval', $third ) ), 'third run without effect: ' . wp_json_encode( $third ) );
		$after = self::all_rows();
		unset( $after['ccap_audit_log'] );
		self::assertSame( $rows, $after );
		self::assertSame( $audit, self::rows( 'ccap_audit_log' ), 'no audit entry without effect' );
		self::assertSame( $third, get_option( 'course_captain_retention_state' )['result'] );
	}

	/**
	 * OPS-01 duplicate (R0-41 §4.2 "eraser twice", PRIV-006 cell): the
	 * WordPress eraser runs twice for a learner with purchase and manual
	 * grants, seat, own group, progress and audit traces. The second run
	 * reports nothing removed and changes no row, no audit entry and not the
	 * state epoch.
	 *
	 * @group OPS-01
	 * @group PRIV-006
	 */
	public function test_OPS_01_eraser_twice_is_idempotent(): void {
		$world   = $this->world();
		$learner = $this->learner( $world['course'] );
		$db      = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $learner,
				'course_id'   => $world['course'],
				'status'      => 'active',
				'source_type' => 'woo_order',
				'source_id'   => 'order-k17-' . wp_generate_password( 6, false ),
			),
			array( 'created_at', 'updated_at' )
		);
		$owner = $this->user();
		$group = $this->group( $owner, $world['course'], 3 );
		GroupService::current()->claim( Actor::user( $learner ), $group['link'] );
		$this->group( $learner, $world['course'], 1 );
		$db->insert(
			'ccap_course_progress',
			array(
				'user_id'    => $learner,
				'course_id'  => $world['course'],
				'status'     => 'in_progress',
				'updated_at' => $db->now(),
			)
		);
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		self::assertArrayHasKey( PrivacyService::ERASER, $erasers );
		$eraser = $erasers[ PrivacyService::ERASER ]['callback'];
		$email  = (string) get_userdata( $learner )->user_email;

		$first = $eraser( $email, 1 );
		self::assertTrue( $first['items_removed'] );
		self::assertTrue( $first['done'] );
		$rows  = self::all_rows();
		$epoch = get_option( SiteIdentity::EPOCH_OPTION );
		self::assertSame( 0, self::rows( 'ccap_grant', 'user_id = ' . $learner ) );
		self::assertSame( 1, self::rows( 'ccap_grant', "user_id IS NULL AND subject_ref IS NOT NULL AND source_type = 'woo_order'" ), 'purchase grant kept pseudonymised' );

		$second = $eraser( $email, 1 );
		self::assertFalse( $second['items_removed'], 'nothing left to remove' );
		self::assertTrue( $second['done'] );
		self::assertSame( $rows, self::all_rows(), 'second run changes no row (audit included)' );
		self::assertSame( $epoch, get_option( SiteIdentity::EPOCH_OPTION ), 'no new restore epoch' );
		self::assertSame( 1, self::rows( 'ccap_audit_log', "action = 'personal_data_erased'" ) );
	}

	/**
	 * REC-026 (OPS-02), the part the Free plugin has: rotating the site
	 * salt invalidates signatures made before (question token refused at
	 * once, there is no transition period); the plugin secret and the salt
	 * appear nowhere in the diagnostics export or in the privacy export.
	 *
	 * @group REC-026
	 * @group OPS-02
	 */
	public function test_REC_026_salt_rotation_and_no_plaintext_secret(): void {
		$world   = $this->world();
		$learner = $this->learner( $world['course'] );
		$token   = (string) QuestionPresenter::current()->deliver( Actor::user( $learner ), $world['course'], $world['module'], $world['questions'][0] )['token'];
		self::assertSame( $world['questions'][0], QuestionPresenter::current()->verify( Actor::user( $learner ), $token )['q'] );

		$rotated = static fn( $salt, $scheme ) => 'auth' === $scheme ? 'k17-rotated-' . $salt : $salt;
		add_filter( 'salt', $rotated, 10, 2 );
		try {
			self::assertSame( 'ccap_invalid_token', self::code( fn() => QuestionPresenter::current()->verify( Actor::user( $learner ), $token ) ), 'old signature refused after rotation' );
			$fresh = (string) QuestionPresenter::current()->deliver( Actor::user( $learner ), $world['course'], $world['module'], $world['questions'][0] )['token'];
			self::assertSame( $world['questions'][0], QuestionPresenter::current()->verify( Actor::user( $learner ), $fresh )['q'], 'new signatures work' );
		} finally {
			remove_filter( 'salt', $rotated, 10 );
		}

		$secret = ProtectedStorage::secret();
		$body   = Diagnostics::current()->export( Actor::user( $this->admin ) )['body'];
		$export = (string) wp_json_encode( PrivacyService::export( (string) get_userdata( $learner )->user_email ) );
		foreach ( array( $secret, wp_salt( 'auth' ), wp_salt( 'nonce' ) ) as $plain ) {
			self::assertStringNotContainsString( $plain, $body, 'diagnostics' );
			self::assertStringNotContainsString( $plain, $export, 'privacy export' );
		}
		self::assertSame( 0, self::rows( 'ccap_audit_log', Database::current()->prepare( 'context LIKE %s', '%' . $secret . '%' ) ) );
	}

	/**
	 * NFR-010 (OPS-04, R0-14 L5) in the deterministic part: the race
	 * scenarios not covered elsewhere (self-enrollment, certificate issue)
	 * keep their unique invariants. Seat claim, attempt start, submit and
	 * completion are covered by ConcurrencyTest (CON-001/005/016/019); the
	 * scale N = 50 × 20 runs on MariaDB and MySQL via CCAP_RACE_N /
	 * CCAP_RACE_REPEAT in the load lane.
	 *
	 * @group NFR-010
	 * @group OPS-04
	 */
	public function test_NFR_010_enrollment_and_certificate_races_keep_invariants(): void {
		if ( ! RaceRunner::available() ) {
			self::markTestSkipped( 'pcntl/posix required.' );
		}
		$admin = Actor::user( $this->admin );
		Settings::save( $admin, 'enrollment', array( 'self_enrollment' => true ), array( 'self_enrollment' ), Settings::rev( 'enrollment' ) );
		Settings::save( $admin, 'certificates', array( 'auto_issue' => true ), array( 'auto_issue' ), Settings::rev( 'certificates' ) );
		$world  = $this->world();
		$course = $world['course'];
		$rounds = RaceRunner::repeat( 1 );
		$n      = RaceRunner::n( 8 );
		for ( $round = 0; $round < $rounds; $round++ ) {
			$user = $this->user();
			$jobs = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$jobs[ 'e' . $i ] = static fn() => SelfEnrollment::current()->enroll( Actor::user( $user ), $course )['grant_id'];
			}
			$results = RaceRunner::run( 'NFR-010-enrollment', $jobs );
			self::assertSame( array(), array_diff( array_keys( RaceRunner::outcomes( $results ) ), array( 'ok', 'ccap_db_busy' ) ), (string) wp_json_encode( $results ) );
			$ids = array_unique( array_column( array_filter( $results, static fn( array $r ): bool => ! empty( $r['ok'] ) ), 'value' ) );
			self::assertCount( 1, $ids, (string) wp_json_encode( $results ) );
			self::assertSame( 1, self::rows( 'ccap_grant', Database::current()->prepare( 'source_type = %s AND user_id = %d', SelfEnrollment::SOURCE, $user ) ) );

			// Certificate: one passed attempt, N parallel issues on the same evidence.
			$rule    = $this->rule( $course, $world['module'] );
			$learner = $this->learner( $course );
			$session = ExamSessions::current()->start( Actor::user( $learner ), $rule, null, wp_generate_uuid4(), new Randomizer( 'nfr010' ) );
			foreach ( array( 1, 2, 3, 4, 5 ) as $position ) {
				ExamSessions::current()->save_answer( Actor::user( $learner ), $session, $position, array( 'A' ) );
			}
			ExamSessions::current()->submit( Actor::user( $learner ), $session );
			$jobs = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$jobs[ 'c' . $i ] = static fn() => (string) ( CertificateService::current()->auto_issue( CertificateService::EVIDENCE_EXAM, $session )['certificate_uid'] ?? '' );
			}
			$results = RaceRunner::run( 'NFR-010-certificate', $jobs );
			self::assertSame( array(), array_diff( array_keys( RaceRunner::outcomes( $results ) ), array( 'ok', 'ccap_db_busy' ) ), (string) wp_json_encode( $results ) );
			$uids = array_unique( array_column( array_filter( $results, static fn( array $r ): bool => ! empty( $r['ok'] ) ), 'value' ) );
			self::assertCount( 1, $uids, (string) wp_json_encode( $results ) );
			self::assertSame( 1, self::rows( 'ccap_certificate', "evidence_type = 'exam_session' AND evidence_id = " . $session ) );
			self::assertSame( 1, self::rows( 'ccap_outbox_event', "event_type = 'certificate.issued' AND user_ref = 'user:" . $learner . "'" ) );
		}
	}

	/**
	 * NFR-011 (OPS-04, R0-21 T-2/T-3/T-6): every deadlock (1213) and
	 * snapshot conflict (1020) is counted and the unit re-runs completely,
	 * at most three attempts; then `ccap_db_busy`. A lock wait timeout
	 * (1205) is not retried and leaves no partial write.
	 *
	 * @group NFR-011
	 * @group OPS-04
	 */
	public function test_NFR_011_deadlocks_counted_retried_at_most_three_times(): void {
		self::assertLessThanOrEqual( 3, Database::DEADLOCK_RETRIES );
		$db       = Database::current();
		$baseline = Database::deadlocks();
		$attempts = 0;
		$result   = $db->transaction(
			static function ( Database $d ) use ( &$attempts ): string {
				++$attempts;
				\CourseCaptain\Storage\RateLimiter::hit( $d, 'nfr011', $attempts, 3600, 100, 1800000000 );
				if ( 1 === $attempts ) {
					throw new StorageException( 'Deadlock found when trying to get lock', 1213 );
				}
				if ( 2 === $attempts ) {
					throw new StorageException( 'Record has changed since last read', 1020 );
				}
				return 'done';
			}
		);
		self::assertSame( 'done', $result );
		self::assertSame( 3, $attempts );
		self::assertSame( 2, Database::deadlocks() - $baseline, 'both conflicts counted' );
		self::assertSame( 0, self::rows( 'ccap_rate_limit', $db->prepare( 'bucket_hash IN (%s, %s)', hash( 'sha256', 'nfr011:1' ), hash( 'sha256', 'nfr011:2' ) ) ), 'rolled back attempts leave nothing' );
		self::assertSame( 1, self::rows( 'ccap_rate_limit', $db->prepare( 'bucket_hash = %s', hash( 'sha256', 'nfr011:3' ) ) ) );

		$baseline = Database::deadlocks();
		$attempts = 0;
		self::assertSame(
			'ccap_db_busy',
			self::code(
				static function () use ( $db, &$attempts ): void {
					$db->transaction(
						static function () use ( &$attempts ): void {
							++$attempts;
							throw new StorageException( 'Deadlock found when trying to get lock', 1213 );
						}
					);
				}
			)
		);
		self::assertSame( Database::DEADLOCK_RETRIES, $attempts );
		self::assertSame( Database::DEADLOCK_RETRIES, Database::deadlocks() - $baseline );

		$baseline = Database::deadlocks();
		$attempts = 0;
		self::assertSame(
			'ccap_db_busy',
			self::code(
				static function () use ( $db, &$attempts ): void {
					$db->transaction(
						static function ( Database $d ) use ( &$attempts ): void {
							++$attempts;
							\CourseCaptain\Storage\RateLimiter::hit( $d, 'nfr011-wait', 1, 3600, 100, 1800000000 );
							throw new StorageException( 'Lock wait timeout exceeded; try restarting transaction', 1205 );
						}
					);
				}
			)
		);
		self::assertSame( 1, $attempts, '1205 is not retried' );
		self::assertSame( 0, Database::deadlocks() - $baseline, 'a lock wait is no deadlock' );
		self::assertSame( 0, self::rows( 'ccap_rate_limit', $db->prepare( 'bucket_hash = %s', hash( 'sha256', 'nfr011-wait:1' ) ) ), 'no partial write after 1205' );
		self::assertFalse( $db->in_transaction() );
	}
}
