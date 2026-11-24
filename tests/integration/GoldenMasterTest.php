<?php
/**
 * R1-18: Golden-Master checks on `cc-fixture-neutral-v1` (R0-05 §1, §2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\AccessRuleKindsBackfill;
use CourseCaptain\Access\ContentGate;
use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\ProtectedFiles;
use CourseCaptain\Access\TrialLimits;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Assignment\AssignmentFiles;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learning\Flashcards;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Publish\CourseValidator;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Learner\ExamRoom;
use CourseCaptain\Learner\LearnerQueries;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Progress\CourseProgress;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Migrator;
use CourseCaptain\Tests\Support\Fixtures\NeutralFixture;

/**
 * Each test loads the fixture itself (idempotent, D-03).
 *
 * @group R1-18
 */
final class GoldenMasterTest extends SchemaTestCase {

	/**
	 * Loaded fixture.
	 *
	 * @var array{users: array<string, int>, courses: array<string, int>, modules: array<string, int>, rules: array<string, int>, variants: array<string, int>, media: array<string, int>, pages: array<string, int>, groups: array<string, int>}
	 */
	private array $fx;

	/**
	 * Scratch directory.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Load the fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->dir = get_temp_dir() . 'ccap-fx-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->dir );
		$this->fx = NeutralFixture::load( $this->dir );
		NeutralFixture::release_rights( $this->fx );
	}

	/**
	 * Fixture counts (§2.2/§2.3) and loader idempotence (D-03).
	 */
	public function test_fixture_counts_and_idempotence(): void {
		$db        = Database::current();
		$published = static fn( string $op ): string => (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_question' ) . '` q JOIN `' . $db->table( 'ccap_question_revision' ) . "` r ON r.id = q.current_revision_id WHERE q.status = 'published' AND r.question_type {$op} 'flashcard'" );
		self::assertSame( '70', $published( '<>' ) );
		self::assertSame( '13', $published( '=' ), 'FC-A-01…08, FC-B-01…05 (§2.5)' );
		self::assertSame( 'draft', (string) $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', (int) NeutralFixture::object( 'question', 'cc-fx-q-a3-011' ) ) ), 'Q-A3-011 stays a draft (§2.3)' );
		$pool  = static fn( string $module ): int => count( QuestionRepository::current()->pool( (int) NeutralFixture::object( 'module', $module ) ) );
		self::assertSame( array( 24, 16, 10, 12, 8 ), array( $pool( 'cc-fx-m-a1' ), $pool( 'cc-fx-m-a2' ), $pool( 'cc-fx-m-a3' ), $pool( 'cc-fx-m-b1' ), $pool( 'cc-fx-m-b2' ) ) );
		$again = NeutralFixture::load( $this->dir );
		self::assertSame( $this->fx, $again );
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_import_batch' ) . "` WHERE status = 'published'" ) );
		self::assertSame( 'draft', get_post_status( $this->fx['courses']['C-C'] ) );
		self::assertSame( 'ccap_archived', get_post_status( $this->fx['courses']['C-D'] ) );
	}

	/**
	 * GM-001 on the fixture: catalog = [cc-a, cc-b].
	 *
	 * @group GM-001
	 */
	public function test_GM_001(): void {
		self::assertSame( array( 'cc-a', 'cc-b' ), array_map( static fn( \WP_Post $p ): string => $p->post_name, LearnerQueries::current()->catalog( 20, 'title' ) ) );
	}

	/**
	 * GM-009 on the fixture: denominator 8 (placeholder L-A-06 and archived L-A-10 excluded).
	 *
	 * @group GM-009
	 */
	public function test_GM_009(): void {
		$state = CourseProgress::current()->recalculate( $this->fx['users']['lea'], $this->fx['courses']['C-A'] );
		self::assertSame( 8, $state['activities_total'] );
	}

	/**
	 * GM-022: Q-A2-015 with all five options correct; GM-004: topics 12 + 12.
	 *
	 * @group GM-022
	 * @group GM-004
	 */
	public function test_GM_022_and_GM_004(): void {
		$db       = Database::current();
		$q        = (int) NeutralFixture::object( 'question', 'cc-fx-q-a2-015' );
		$revision = (array) QuestionRepository::current()->revision( (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $q ) ) );
		$keys     = array_column( (array) $revision['answers'], 'key' );
		self::assertCount( 5, array_filter( array_column( (array) $revision['answers'], 'correct' ) ) );
		self::assertTrue( QuestionTypes::evaluate( $revision, $keys, $keys )->correct() );
		self::assertFalse( QuestionTypes::evaluate( $revision, array_slice( $keys, 0, 4 ), $keys )->correct() );

		$topics = SmartTrainer::current()->topics( Actor::user( $this->fx['users']['lea'] ), $this->fx['courses']['C-A'], (int) NeutralFixture::object( 'module', 'cc-fx-m-a1' ) );
		self::assertSame(
			array(
				'T-A1-a' => 12,
				'T-A1-b' => 12,
			),
			$topics
		);
	}

	/**
	 * AR-01: partial access, first 3 questions per module; GM-086: expired grant stays expired.
	 *
	 * @group GM-086
	 * @group GM-076
	 */
	public function test_partial_access_and_GM_086(): void {
		SmartTrainer::set_now( strtotime( NeutralFixture::T0 . ' UTC' ) );
		$summary = SmartTrainer::current()->summary( Actor::user( $this->fx['users']['free'] ), $this->fx['courses']['C-A'] );
		SmartTrainer::set_now( null );
		self::assertSame( 9, $summary['total'], '3 per question module (M-A1, M-A2, M-A3)' );
		self::assertSame( 'expired', LearnerQueries::current()->access_state( Actor::user( $this->fx['users']['other'] ), $this->fx['courses']['C-B'] )['state'] );
	}

	/**
	 * PKG-v2: only Q-A1-007 changes (update), all else unchanged.
	 *
	 * @group GM-120
	 */
	public function test_pkg_v2(): void {
		$service = ImportService::current();
		$admin   = Actor::user( $this->fx['users']['admin'] );
		$batch   = $service->upload( $admin, NeutralFixture::package( 2, $this->dir . '/pkg-v2.zip' ) );
		$service->validate( $admin, $batch );
		$plan = $service->preview( $admin, $batch )['plan'];
		self::assertSame(
			array(
				'unchanged' => 70,
				'update'    => 1,
			),
			$plan['questions']
		);
		self::assertSame( array( 'unchanged' => 12 ), $plan['lessons'], '12 lessons incl. the archived L-A-10 (R0-05 §2.2)' );
		self::assertSame(
			array(
				'unchanged' => 12,
				'archive'   => 1,
			),
			$plan['flashcards'],
			'FC-A-08 removed'
		);
		$db    = Database::current();
		$q     = (int) NeutralFixture::object( 'question', 'cc-fx-q-a1-007' );
		$rev1  = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $q ) );
		$card  = (int) NeutralFixture::object( 'flashcard', 'cc-fx-fc-a-08' );
		$state = static fn( string $table, int $id, string $column ): string => (string) $db->scalar( $db->prepare( "SELECT {$column} FROM `" . $db->table( $table ) . '` WHERE id = %d', $id ) );
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );
		$rev = (array) QuestionRepository::current()->revision( (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $q ) ) );
		self::assertStringEndsWith( '(korrigiert)', (string) $rev['question_text'] );
		self::assertSame( 'published', $state( 'ccap_question_revision', (int) $rev['id'], 'editorial_status' ), 'rev2 published' );
		self::assertSame( 'deprecated', $state( 'ccap_question_revision', $rev1, 'editorial_status' ), 'rev1 deprecated' );
		self::assertSame( 'archived', $state( 'ccap_question', $card, 'status' ), 'card source_archived' );
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 * @return CourseException
	 */
	private function rejected( callable $call, string $code ): CourseException {
		try {
			$call();
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code, $e->getMessage() );
			return $e;
		}
		self::fail( 'expected ' . $code );
	}

	/**
	 * Start an exam for a fixture user with the fixed seed of §3.
	 *
	 * @param string      $user    Login key.
	 * @param string      $rule    ER-A/ER-B.
	 * @param string|null $variant EP-… or null (random).
	 * @return int
	 */
	private function start( string $user, string $rule, ?string $variant = null ): int {
		return ExamSessions::current()->start( Actor::user( $this->fx['users'][ $user ] ), $this->fx['rules'][ $rule ], null === $variant ? null : $this->fx['variants'][ $variant ], wp_generate_uuid4(), new Randomizer( '20300115' ) );
	}

	/**
	 * Session row and its questions (position → [question, revision, module key]).
	 *
	 * @param int $session Session.
	 * @return array{row: array<string, mixed>, questions: array<int, array{question: int, revision: int}>}
	 */
	private static function session( int $session ): array {
		$db  = Database::current();
		$row = (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		$out = array();
		foreach ( $db->rows( $db->prepare( 'SELECT position, question_id, revision_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d ORDER BY position', $session ) ) as $q ) {
			$out[ (int) $q['position'] ] = array(
				'question' => (int) $q['question_id'],
				'revision' => (int) $q['revision_id'],
			);
		}
		return array(
			'row'       => $row,
			'questions' => $out,
		);
	}

	/**
	 * Answer every choice question of a session; `$wrong` positions get a wrong answer.
	 *
	 * @param string $user    Login key.
	 * @param int    $session Session.
	 * @param array  $wrong   Positions answered wrongly.
	 * @phpstan-param list<int> $wrong
	 * @return array<string, mixed> Stored internal result after submit.
	 */
	private function answer_and_submit( string $user, int $session, array $wrong = array() ): array {
		$actor    = Actor::user( $this->fx['users'][ $user ] );
		$sessions = ExamSessions::current();
		foreach ( self::session( $session )['questions'] as $position => $q ) {
			$revision = (array) QuestionRepository::current()->revision( $q['revision'] );
			if ( QuestionTypes::OPEN_TEXT === $revision['question_type'] ) {
				$sessions->reveal( $actor, $session, $position );
				$sessions->save_answer( $actor, $session, $position, array( 'known' => ! in_array( $position, $wrong, true ) ) );
				continue;
			}
			$right = array();
			$false = array();
			foreach ( (array) $revision['answers'] as $a ) {
				if ( empty( $a['correct'] ) ) {
					$false[] = (string) $a['key'];
				} else {
					$right[] = (string) $a['key'];
				}
			}
			$sessions->save_answer( $actor, $session, $position, in_array( $position, $wrong, true ) ? array( $false[0] ?? 'Z' ) : $right );
		}
		$sessions->submit( $actor, $session );
		return (array) json_decode( (string) self::session( $session )['row']['result'], true );
	}

	/**
	 * Extension of §2.2–§2.8: users/roles, ER-A/ER-B/ER-C, EP-A-1…3/EP-B-1,
	 * MED-001…004 and DL-A-1…3, PAGE-1/2, G-10, GRP-1/2.
	 */
	public function test_fixture_extension(): void {
		$db = Database::current();
		self::assertContains( Roles::TRAINER, get_userdata( $this->fx['users']['tutor'] )->roles );
		self::assertContains( Roles::COURSE_MANAGER, get_userdata( $this->fx['users']['manager'] )->roles );

		$er_a = (array) ExamRules::current()->get( $this->fx['rules']['ER-A'] );
		self::assertSame( 'published', $er_a['status'] );
		self::assertSame( 3600, (int) $er_a['time_limit_seconds'] );
		self::assertSame(
			array( 7, 13, 10 ),
			array_map( static fn( array $b ): int => (int) $b['question_count'], (array) $er_a['blueprints'] )
		);
		$er_b = (array) ExamRules::current()->get( $this->fx['rules']['ER-B'] );
		self::assertNull( $er_b['time_limit_seconds'] );
		self::assertSame( 'practice', $er_b['settings_stored']['purpose'] );
		$variant = static fn( string $key ): array => array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT question_id FROM `' . $db->table( 'ccap_exam_variant_question' ) . '` WHERE variant_id = %d ORDER BY position', $key ) ) );
		$ep_a_1  = $variant( (string) $this->fx['variants']['EP-A-1'] );
		self::assertCount( 30, $ep_a_1 );
		self::assertSame( NeutralFixture::question( 'A1-007' ), $ep_a_1[6], 'EP-A-1 holds Q-A1-007 (GM-052)' );
		self::assertSame( NeutralFixture::question( 'B1-010' ), $variant( (string) $this->fx['variants']['EP-B-1'] )[0], 'EP-B-1 position 1 is the open question' );

		self::assertSame(
			array( $this->fx['media']['DL-A-1'], $this->fx['media']['DL-A-2'], $this->fx['media']['DL-A-3'] ),
			ContentGate::current()->course_files( $this->fx['courses']['C-A'] )
		);
		$sha = $db->scalar( $db->prepare( 'SELECT sha256 FROM `' . $db->table( 'ccap_media' ) . '` WHERE id = %d', $this->fx['media']['MED-002'] ) );
		self::assertSame( '2e447110bc99f416570890be32fca0b3c073a6d38356c7fe351550b065e9caa3', $sha, 'MED-002 is the committed build artefact' );
		self::assertStringStartsWith( '%PDF-1.4', NeutralFixture::media_files()['fixture.pdf'] );
		self::assertSame( (string) $this->fx['courses']['C-A'], get_post_meta( $this->fx['pages']['PAGE-1'], '_ccap_protected_course', true ) );

		self::assertSame( '3', (string) $db->scalar( $db->prepare( 'SELECT seats FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $this->fx['groups']['GRP-1'] ) ) );
		self::assertSame( '5', (string) $db->scalar( $db->prepare( 'SELECT seats FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $this->fx['groups']['GRP-2'] ) ) );
		self::assertSame( 'view', (string) $db->scalar( $db->prepare( 'SELECT scopes FROM `' . $db->table( 'ccap_grant' ) . '` WHERE user_id = %d AND course_id = %d', $this->fx['users']['seat_1'], $this->fx['courses']['C-A'] ) ) );
		self::assertSame( NeutralFixture::uuid( 'G-10' ), NeutralFixture::uuid( 'G-10' ) );
		self::assertMatchesRegularExpression( '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', NeutralFixture::uuid( 'G-10' ) );
	}

	/**
	 * GM-002 on the fixture: the learner API answers guests with 401 `ccap_auth_required`.
	 *
	 * @group GM-002
	 */
	public function test_GM_002(): void {
		wp_set_current_user( 0 );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/me/courses' ) );
		self::assertSame( 401, $response->get_status() );
		self::assertSame( 'ccap_auth_required', $response->get_data()['code'] );
	}

	/**
	 * GM-028 / GM-029 on ER-A: 30 questions without solutions; composition {M-A1 7, M-A2 13, M-A3 10}; evidence-eligible, not reduced.
	 *
	 * @group GM-028
	 * @group GM-029
	 */
	public function test_GM_028_029_er_a(): void {
		$session  = $this->start( 'lea', 'ER-A' );
		$view     = ExamSessions::current()->view( Actor::user( $this->fx['users']['lea'] ), $session );
		$json     = (string) wp_json_encode( $view['questions'] );
		$snapshot = json_decode( (string) self::session( $session )['row']['snapshot'], true );
		self::assertCount( 30, $view['questions'] );
		foreach ( array( 'is_correct', 'correct_ids', 'explanation', '"correct"' ) as $leak ) {
			self::assertStringNotContainsString( $leak, $json );
		}
		self::assertSame(
			array(
				$this->fx['modules']['M-A1'] => 7,
				$this->fx['modules']['M-A2'] => 13,
				$this->fx['modules']['M-A3'] => 10,
			),
			array_count_values( array_column( $snapshot['scoring'], 'section' ) )
		);
		self::assertTrue( $snapshot['evidence_eligible'] );
		self::assertFalse( $snapshot['reduced'] );
		self::assertSame( 120, (int) $snapshot['timing']['late_tolerance_seconds'] );
	}

	/**
	 * GM-030 on EP-A-1 (ER-A): the first three P1 questions wrong, all others right →
	 * 27/30 (total ratio reached), P1 4/7 < 5/7 → not passed. The open
	 * question Q-A3-010 counts after reveal and self-assessment.
	 *
	 * @group GM-030
	 */
	public function test_GM_030(): void {
		$result = $this->answer_and_submit( 'lea', $this->start( 'lea', 'ER-A', 'EP-A-1' ), array( 1, 2, 3 ) );
		$p1     = $result['sections'][ $this->fx['modules']['M-A1'] ];
		self::assertSame( array( 4000, 7000 ), array( $p1['points'], $p1['max'] ) );
		self::assertSame( array( 27000, 30000 ), array( $result['points_milli'], $result['max_points_milli'] ) );
		self::assertGreaterThanOrEqual( 0.75, $result['points_milli'] / $result['max_points_milli'], 'total ratio reached' );
		self::assertFalse( $result['passed'] );
	}

	/**
	 * GM-031: evaluation follows the snapshot; the snapshot moves every
	 * question to M-A2, so part P1 (M-A1) is empty and does not fail.
	 *
	 * @group GM-031
	 */
	public function test_GM_031(): void {
		$session  = $this->start( 'lea', 'ER-A', 'EP-A-1' );
		$db       = Database::current();
		$row      = self::session( $session )['row'];
		$snapshot = json_decode( (string) $row['snapshot'], true );
		foreach ( $snapshot['scoring'] as $i => $item ) {
			$snapshot['scoring'][ $i ]['section'] = $this->fx['modules']['M-A2'];
		}
		$json = (string) wp_json_encode( $snapshot );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . '` SET snapshot = %s, snapshot_hash = %s WHERE id = %d', $json, 'sha256:' . hash( 'sha256', $json ), $session ) );
		$result = $this->answer_and_submit( 'lea', $session );
		self::assertArrayNotHasKey( $this->fx['modules']['M-A1'], $result['sections'], 'P1 empty' );
		self::assertSame( array( 30000, 30000 ), array( $result['sections'][ $this->fx['modules']['M-A2'] ]['points'], $result['points_milli'] ) );
		self::assertTrue( $result['passed'] );
	}

	/**
	 * GM-029 / E-331: ER-A has part P1 (M-A1) and part P2 over the module
	 * group M-A2 + M-A3; the snapshot freezes the parts and the question →
	 * part assignment `question_parts` {P1: 7, P2: 23}.
	 *
	 * @group GM-029
	 * @group R1-18
	 */
	public function test_GM_029_parts_over_module_groups(): void {
		$snapshot = json_decode( (string) self::session( $this->start( 'lea', 'ER-A' ) )['row']['snapshot'], true );
		$counts   = array_count_values( $snapshot['question_parts'] );
		ksort( $counts );
		self::assertSame(
			array(
				'P1' => 7,
				'P2' => 23,
			),
			$counts
		);
		self::assertSame( array( 'P1', 'P2' ), array_column( $snapshot['parts'], 'key' ) );
		self::assertSame( array( $this->fx['modules']['M-A1'] ), $snapshot['parts'][0]['sections'], 'single-module part (form before E-331) stays valid' );
		self::assertSame( array( $this->fx['modules']['M-A2'], $this->fx['modules']['M-A3'] ), $snapshot['parts'][1]['sections'] );
		self::assertSame(
			array(
				'num' => 18,
				'den' => 23,
			),
			$snapshot['parts'][1]['min_ratio']
		);
		self::assertSame( $snapshot['section_labels'][ $this->fx['modules']['M-A2'] ] . ' + ' . $snapshot['section_labels'][ $this->fx['modules']['M-A3'] ], $snapshot['parts'][1]['label'], 'label frozen from the module titles' );
		foreach ( $snapshot['scoring'] as $item ) {
			self::assertSame( $item['section'] === $this->fx['modules']['M-A1'] ? 'P1' : 'P2', $snapshot['question_parts'][ $item['position'] ] );
		}
		self::assertSame( array(), array_values( array_filter( CourseValidator::validate( $this->fx['courses']['C-A'] )['issues'], static fn( array $i ): bool => in_array( $i['code'], array( 'VAL-075', 'VAL-092' ), true ) ) ), 'ER-A parts are valid and cover every blueprint module' );
	}

	/**
	 * GM-030 / E-331 on EP-A-1: (a) the first three P1 questions wrong →
	 * P1 4/7 < 5 failed, P2 23/23 passed, not passed; (b) six P2 questions
	 * wrong → 24/30 (total reached), P1 7/7, P2 17/23 < 18 → not passed
	 * because of the module-group part. The learner result and the exam
	 * room show the parts.
	 *
	 * @group GM-030
	 * @group R1-18
	 */
	public function test_GM_030_part_p2_over_module_group(): void {
		$parts = static fn( array $result ): array => array_map( static fn( array $p ): array => array( $p['key'], $p['correct'], $p['total'], $p['passed'] ), $result['parts'] );

		$a = $this->answer_and_submit( 'lea', $this->start( 'lea', 'ER-A', 'EP-A-1' ), array( 1, 2, 3 ) );
		self::assertSame( array( array( 'P1', 4, 7, false ), array( 'P2', 23, 23, true ) ), $parts( $a ) );
		self::assertSame( array( 23000, 23000 ), array( $a['parts'][1]['points'], $a['parts'][1]['max'] ) );
		self::assertFalse( $a['passed'] );

		$session = $this->start( 'ole', 'ER-A', 'EP-A-1' );
		$b       = $this->answer_and_submit( 'ole', $session, range( 8, 13 ) );
		self::assertSame( array( 24000, 30000 ), array( $b['points_milli'], $b['max_points_milli'] ) );
		self::assertSame( array( array( 'P1', 7, 7, true ), array( 'P2', 17, 23, false ) ), $parts( $b ) );
		self::assertFalse( $b['passed'], 'total 24/30 ≥ 23/30 but P2 17/23 < 18/23' );

		$ole    = Actor::user( $this->fx['users']['ole'] );
		$result = ExamSessions::current()->view( $ole, $session )['result'];
		self::assertSame( array( 'P1', 'P2' ), array_column( $result['parts'], 'key' ) );
		self::assertSame( array( true, false ), array_column( $result['parts'], 'passed' ) );
		self::assertSame( 17000, $result['parts'][1]['points_milli'] );
		$html = ExamRoom::render( $ole, $session )['body'];
		self::assertStringContainsString( 'P2 (', $html );
		self::assertStringContainsString( '17 of 23 correct, not passed', $html );
	}

	/**
	 * GM-031 / E-331: the frozen `question_parts` decide; the snapshot moves
	 * every question to P2 → P1 `total=0, empty=true, passed=true`, P2 30/30,
	 * passed. A snapshot from before E-331 (no `parts`/`question_parts`)
	 * is evaluated by its sections as before.
	 *
	 * @group GM-031
	 * @group R1-18
	 */
	public function test_GM_031_snapshot_parts_take_precedence(): void {
		$db      = Database::current();
		$rewrite = function ( int $session, callable $change ) use ( $db ): void {
			$snapshot = $change( json_decode( (string) self::session( $session )['row']['snapshot'], true ) );
			$json     = (string) wp_json_encode( $snapshot );
			$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . '` SET snapshot = %s, snapshot_hash = %s WHERE id = %d', $json, 'sha256:' . hash( 'sha256', $json ), $session ) );
		};
		$session = $this->start( 'lea', 'ER-A', 'EP-A-1' );
		$rewrite(
			$session,
			static function ( array $s ): array {
				foreach ( array_keys( $s['question_parts'] ) as $position ) {
					$s['question_parts'][ $position ] = 'P2';
				}
				return $s;
			}
		);
		$result = $this->answer_and_submit( 'lea', $session, array( 1, 2, 3 ) );
		self::assertSame( array( 0, 0, true, true ), array( $result['parts'][0]['total'], $result['parts'][0]['max'], $result['parts'][0]['empty'], $result['parts'][0]['passed'] ) );
		self::assertSame( array( 27, 30, true ), array( $result['parts'][1]['correct'], $result['parts'][1]['total'], $result['parts'][1]['passed'] ), 'P2 27/30 ≥ 18/23' );
		self::assertTrue( $result['passed'], 'P1 empty does not fail, P1 questions count for P2' );

		$legacy = $this->start( 'ole', 'ER-A', 'EP-A-1' );
		$rewrite(
			$legacy,
			static function ( array $s ): array {
				unset( $s['parts'], $s['question_parts'] );
				return $s;
			}
		);
		$old = $this->answer_and_submit( 'ole', $legacy, array( 1, 2, 3 ) );
		self::assertSame( array( array( 'P1', 4, 7 ), array( 'P2', 23, 23 ) ), array_map( static fn( array $p ): array => array( $p['key'], $p['correct'], $p['total'] ), $old['parts'] ), 'parts derived from the frozen passing rule' );
		self::assertFalse( $old['passed'] );
	}

	/**
	 * E-331 data contract and validator: parts with `sections` (group) or
	 * `section` (single module, unchanged); malformed parts are refused at
	 * creation and never startable; VAL-075 for a part outside the
	 * blueprint, VAL-092 for a blueprint module outside every part.
	 *
	 * @group GM-029
	 * @group R1-18
	 */
	public function test_E331_part_contract_and_validator(): void {
		$m     = $this->fx['modules'];
		$ratio = array(
			'num' => 1,
			'den' => 2,
		);
		self::assertSame(
			array(),
			ExamRules::validate_passing_rule(
				array(
					'min_ratio' => $ratio,
					'parts'     => array(
						array(
							'section'   => $m['M-A1'],
							'min_ratio' => $ratio,
						),
					),
				)
			)
		);
		self::assertSame(
			array(),
			ExamRules::validate_passing_rule(
				array(
					'min_ratio' => $ratio,
					'parts'     => array(
						array(
							'key'       => 'P2',
							'label'     => 'Teil 2',
							'sections'  => array( $m['M-A2'], $m['M-A3'] ),
							'min_ratio' => $ratio,
						),
					),
				)
			)
		);
		foreach ( array(
			array(
				array(
					'sections'  => array(),
					'min_ratio' => $ratio,
				),
			),
			array(
				array(
					'sections'  => array( $m['M-A2'], $m['M-A2'] ),
					'min_ratio' => $ratio,
				),
			),
			array(
				array(
					'section'   => $m['M-A1'],
					'sections'  => array( $m['M-A2'] ),
					'min_ratio' => $ratio,
				),
			),
			array( array( 'sections' => array( $m['M-A2'] ) ) ),
			array(
				array(
					'section'   => $m['M-A1'],
					'min_ratio' => array(
						'num' => 3,
						'den' => 2,
					),
				),
			),
			array(
				array(
					'key'       => 'P1',
					'section'   => $m['M-A1'],
					'min_ratio' => $ratio,
				),
				array(
					'key'       => 'P1',
					'section'   => $m['M-A2'],
					'min_ratio' => $ratio,
				),
			),
		) as $i => $parts ) {
			self::assertSame(
				array( 'passing_rule_part' ),
				ExamRules::validate_passing_rule(
					array(
						'min_ratio' => $ratio,
						'parts'     => $parts,
					)
				),
				'case ' . $i
			);
		}
		self::assertSame(
			array( 'P1', 'P2' ),
			array_column(
				ExamRules::parts(
					array(
						'parts' => array(
							array(
								'section'   => $m['M-A1'],
								'min_ratio' => $ratio,
							),
							array(
								'sections'  => array( $m['M-A2'], $m['M-A3'] ),
								'min_ratio' => $ratio,
							),
						),
					)
				),
				'key'
			),
			'default keys P1, P2'
		);

		$admin  = Actor::user( $this->fx['users']['admin'] );
		$rules  = ExamRules::current();
		$course = $this->fx['courses']['C-A'];
		$this->rejected(
			fn() => $rules->create(
				$admin,
				$course,
				'cc-fx-bad-part',
				array(
					'min_ratio' => $ratio,
					'parts'     => array(
						array(
							'sections'  => array(),
							'min_ratio' => $ratio,
						),
					),
				)
			),
			'ccap_validation_failed'
		);

		$draft = $rules->create(
			$admin,
			$course,
			'cc-fx-part-check',
			array(
				'min_ratio' => $ratio,
				'parts'     => array(
					array(
						'key'       => 'PX',
						'sections'  => array( $m['M-A1'], $m['M-B1'] ),
						'min_ratio' => $ratio,
					),
				),
			)
		);
		$rules->set_blueprint( $admin, $draft, $m['M-A1'], 2 );
		$rules->set_blueprint( $admin, $draft, $m['M-A2'], 2 );
		$issues = array_values( array_filter( CourseValidator::validate( $course )['issues'], static fn( array $i ): bool => 'exam_rule' === $i['object']['type'] && $draft === $i['object']['id'] ) );
		$codes  = array_column( $issues, 'code' );
		sort( $codes );
		self::assertSame( array( 'VAL-075', 'VAL-092' ), $codes );
		$by = array_column( $issues, null, 'code' );
		self::assertSame( 'PX', $by['VAL-075']['params']['part'] );
		self::assertSame( array( $m['M-B1'] ), $by['VAL-075']['params']['modules'] );
		self::assertSame( array( $m['M-A2'] ), $by['VAL-092']['params']['modules'] );
	}

	/**
	 * GM-033 on ER-B: published practice rule, never evidence; a draft version of ER-A is not startable.
	 *
	 * @group GM-033
	 */
	public function test_GM_033(): void {
		GrantService::current()->grant_manual( Actor::user( $this->fx['users']['admin'] ), $this->fx['users']['lea'], $this->fx['courses']['C-B'], NeutralFixture::uuid( 'GM-033' ), 'Fixture' );
		PolicyService::flush();
		$snapshot = json_decode( (string) self::session( $this->start( 'lea', 'ER-B' ) )['row']['snapshot'], true );
		self::assertTrue( $snapshot['practice'] );
		self::assertFalse( $snapshot['evidence_eligible'] );

		$admin = Actor::user( $this->fx['users']['admin'] );
		$draft = ExamRules::current()->create(
			$admin,
			$this->fx['courses']['C-A'],
			'cc-fx-er-a-v2',
			array(
				'min_ratio' => array(
					'num' => 23,
					'den' => 30,
				),
			),
			3600
		);
		ExamRules::current()->set_blueprint( $admin, $draft, $this->fx['modules']['M-A1'], 7 );
		$this->rejected( fn() => ExamSessions::current()->start( Actor::user( $this->fx['users']['ole'] ), $draft, null, wp_generate_uuid4() ), 'ccap_exam_not_startable' );
	}

	/**
	 * GM-048 / GM-083 with AR-04 (M-A2, 7 days) and AR-05 (LM-A2, 30 days):
	 * drip locks the fixed variant EP-A-1 and the module; free after +8 days;
	 * U-L4 starts at the registration (T0 − 40 d) and is never drip-locked.
	 *
	 * @group GM-048
	 * @group GM-083
	 */
	public function test_GM_048_083_drip(): void {
		NeutralFixture::rule( 'AR-04', $this->fx );
		NeutralFixture::rule( 'AR-05', $this->fx );
		$lea  = Actor::user( $this->fx['users']['lea'] );
		$gate = ContentGate::current()->module( $lea, $this->fx['courses']['C-A'], $this->fx['modules']['M-A2'] );
		self::assertSame( 'drip', $gate->reason );
		self::assertGreaterThan( gmdate( 'Y-m-d H:i:s' ), (string) $gate->unlock_at );
		$exam = ContentGate::current()->exam( $lea, $this->fx['courses']['C-A'], array( $this->fx['modules']['M-A1'], $this->fx['modules']['M-A2'], $this->fx['modules']['M-A3'] ), 1 );
		self::assertSame( 'drip', $exam->reason, 'drip also for fixed variants' );
		$e = $this->rejected( fn() => $this->start( 'lea', 'ER-A', 'EP-A-1' ), 'ccap_content_locked' );
		self::assertSame( 403, $e->status );
		$lesson = (int) NeutralFixture::object( 'lesson', 'cc-fx-l-a-05' );
		self::assertSame( 'drip', ContentGate::current()->lesson( $lea, $this->fx['courses']['C-A'], $lesson )->reason, 'chapter LM-A2 locked' );

		self::assertNotSame( 'drip', ContentGate::current()->module( Actor::user( $this->fx['users']['newcomer'] ), $this->fx['courses']['C-A'], $this->fx['modules']['M-A2'] )->reason, 'U-L4: start = registration' );

		ContentGate::set_now( time() + 8 * DAY_IN_SECONDS );
		try {
			self::assertTrue( ContentGate::current()->module( $lea, $this->fx['courses']['C-A'], $this->fx['modules']['M-A2'] )->allowed() );
			self::assertNull( ContentGate::current()->module( $lea, $this->fx['courses']['C-A'], $this->fx['modules']['M-A2'] )->unlock_at );
		} finally {
			ContentGate::set_now( null );
		}
	}

	/**
	 * GM-081: without grant and rule no access; the free rule AR-08 opens the question bank.
	 *
	 * @group GM-081
	 */
	public function test_GM_081(): void {
		$this->without_ar_01();
		$free = Actor::user( $this->fx['users']['free'] );
		$q4   = NeutralFixture::question( 'A1-004' );
		self::assertSame( 'no_access', ContentGate::current()->question( $free, $this->fx['courses']['C-A'], $this->fx['modules']['M-A1'], $q4 )->reason );
		NeutralFixture::rule( 'AR-08', $this->fx );
		self::assertTrue( ContentGate::current()->question( $free, $this->fx['courses']['C-A'], $this->fx['modules']['M-A1'], $q4 )->allowed() );
	}

	/**
	 * GM-082 with AR-01 (questions 3), AR-02 (variants 2) and AR-03
	 * (lessons 2) at the same time (E-332, one limit per kind): without a
	 * rule of the kind no access; 3 questions per module; variants 1–2
	 * free, variant 3 and the random exam locked, the locked start refused;
	 * 2 lessons; G-3 lifts every limit (`limit = null`).
	 *
	 * @group GM-082
	 * @group R1-18
	 */
	public function test_GM_082(): void {
		$free    = Actor::user( $this->fx['users']['free'] );
		$gate    = ContentGate::current();
		$course  = $this->fx['courses']['C-A'];
		$modules = array( $this->fx['modules']['M-A1'], $this->fx['modules']['M-A2'], $this->fx['modules']['M-A3'] );
		$lessons = array_map( static fn( string $l ): int => (int) NeutralFixture::object( 'lesson', $l ), array( 'cc-fx-l-a-01', 'cc-fx-l-a-02', 'cc-fx-l-a-03' ) );
		// Only AR-01 (questions): no rule for variants and lessons → no access.
		self::assertSame( 'no_access', $gate->exam( $free, $course, $modules, 1 )->reason );
		self::assertSame( 'no_access', $gate->lesson( $free, $course, $lessons[0] )->reason );

		NeutralFixture::rule( 'AR-02', $this->fx );
		NeutralFixture::rule( 'AR-03', $this->fx );
		$gate = ContentGate::current();
		self::assertSame( 3, $gate->module( $free, $course, $modules[0] )->limit );
		self::assertTrue( $gate->question( $free, $course, $modules[0], NeutralFixture::question( 'A1-003' ) )->allowed() );
		self::assertSame( 'trial', $gate->question( $free, $course, $modules[0], NeutralFixture::question( 'A1-004' ) )->reason );
		self::assertTrue( $gate->exam( $free, $course, $modules, 1 )->allowed() );
		self::assertTrue( $gate->exam( $free, $course, $modules, 2 )->allowed() );
		self::assertSame( 'trial', $gate->exam( $free, $course, $modules, 3 )->reason );
		self::assertSame( 'trial', $gate->exam( $free, $course, $modules, null )->reason, 'random exam locked' );
		$this->rejected( fn() => $this->start( 'free', 'ER-A' ), 'ccap_content_locked' );
		$this->rejected( fn() => $this->start( 'free', 'ER-A', 'EP-A-3' ), 'ccap_content_locked' );
		self::assertTrue( $gate->lesson( $free, $course, $lessons[1] )->allowed() );
		self::assertSame( 'trial', $gate->lesson( $free, $course, $lessons[2] )->reason );
		self::assertSame(
			array(
				TrialLimits::QUESTIONS => 3,
				TrialLimits::LESSONS   => 2,
				TrialLimits::VARIANTS  => 2,
			),
			TrialLimits::current()->limits( $course )
		);

		NeutralFixture::grant( 'G-3', $this->fx );
		$gate = ContentGate::current();
		self::assertNull( $gate->module( $free, $course, $modules[0] )->limit );
		self::assertTrue( $gate->question( $free, $course, $modules[0], NeutralFixture::question( 'A1-004' ) )->allowed() );
		self::assertTrue( $gate->exam( $free, $course, $modules, null )->allowed() );
		self::assertTrue( $gate->lesson( $free, $course, $lessons[2] )->allowed() );
	}

	/**
	 * E-332 / F-2: AR-02 (variants 2), AR-03 (lessons 2), AR-06 (flashcards
	 * = questions 5) and AR-07 (files 1) at the same time with different
	 * numbers; each kind keeps its own limit (before E-332 the largest, 5,
	 * applied to all). Flashcards: 5 cards free, the 6th never delivered
	 * (no back side), state for a card that became locked refused (GM-062).
	 *
	 * @group GM-082
	 * @group GM-015
	 * @group GM-062
	 * @group R1-18
	 */
	public function test_E332_GM_082_015_062_kind_limits_at_once(): void {
		$this->without_ar_01();
		foreach ( array( 'AR-02', 'AR-03', 'AR-06', 'AR-07' ) as $rule ) {
			NeutralFixture::rule( $rule, $this->fx );
		}
		$decks   = NeutralFixture::flashcards( $this->fx );
		$free    = Actor::user( $this->fx['users']['free'] );
		$gate    = ContentGate::current();
		$course  = $this->fx['courses']['C-A'];
		$modules = array( $this->fx['modules']['M-A1'], $this->fx['modules']['M-A2'], $this->fx['modules']['M-A3'] );
		self::assertSame(
			array(
				TrialLimits::QUESTIONS => 5,
				TrialLimits::LESSONS   => 2,
				TrialLimits::VARIANTS  => 2,
				TrialLimits::FILES     => 1,
			),
			TrialLimits::current()->limits( $course )
		);

		// Questions (AR-06 kind): 5 per module.
		self::assertTrue( $gate->question( $free, $course, $modules[0], NeutralFixture::question( 'A1-005' ) )->allowed() );
		self::assertSame( 'trial', $gate->question( $free, $course, $modules[0], NeutralFixture::question( 'A1-006' ) )->reason );
		// Lessons (AR-03): 2, not 5.
		$lessons = array_map( static fn( string $l ): int => (int) NeutralFixture::object( 'lesson', $l ), array( 'cc-fx-l-a-01', 'cc-fx-l-a-02', 'cc-fx-l-a-03' ) );
		self::assertTrue( $gate->lesson( $free, $course, $lessons[1] )->allowed() );
		self::assertSame( 'trial', $gate->lesson( $free, $course, $lessons[2] )->reason );
		// Variants (AR-02): 1–2, not 5.
		self::assertTrue( $gate->exam( $free, $course, $modules, 2 )->allowed() );
		self::assertSame( 'trial', $gate->exam( $free, $course, $modules, 3 )->reason );
		self::assertSame( 'trial', $gate->exam( $free, $course, $modules, null )->reason );
		// Files (AR-07): 1 of 3, not 5.
		self::assertSame( array( false, true, true ), array_column( ProtectedFiles::current()->listing( $free, $course ), 'locked' ) );

		// Flashcards (AR-06): cards 1…5 of FC-A free, card 6 locked without back side.
		$presenter = QuestionPresenter::current();
		foreach ( range( 1, 5 ) as $i ) {
			$card = $presenter->deliver_inline( $free, $course, $decks['FC-A'], $decks['cards'][ sprintf( 'FC-A-%02d', $i ) ] );
			self::assertSame( 'Rückseite FC-A-' . sprintf( '%02d', $i ), $presenter->reveal( $free, $card['token'] ) );
		}
		$this->rejected( fn() => $presenter->deliver_inline( $free, $course, $decks['FC-A'], $decks['cards']['FC-A-06'] ), 'ccap_content_locked' );
		$fc = Flashcards::current();
		self::assertSame( 'known', $fc->set_state( $free, $presenter->deliver_inline( $free, $course, $decks['FC-A'], $decks['cards']['FC-A-05'] )['token'], 'known', wp_generate_uuid4() )['state'] );

		// A card delivered under a larger limit is refused once the limit drops (re-check on every write).
		$admin = Actor::user( $this->fx['users']['admin'] );
		TrialLimits::current()->set( $admin, $course, 0, array( TrialLimits::QUESTIONS => 8 ) );
		$token = $presenter->deliver_inline( $free, $course, $decks['FC-A'], $decks['cards']['FC-A-06'] )['token'];
		$after = TrialLimits::current()->set( $admin, $course, 0, array( TrialLimits::QUESTIONS => 5 ) );
		self::assertSame( 2, $after[ TrialLimits::LESSONS ], 'other kinds unchanged' );
		$this->rejected( fn() => $fc->set_state( $free, $token, 'known', wp_generate_uuid4() ), 'ccap_content_locked' );
		$this->rejected( fn() => $presenter->reveal( $free, $token ), 'ccap_content_locked' );
	}

	/**
	 * E-332 migration: a single limit from before E-332 (`first_items`)
	 * still applies to every kind, and the backfill splits it into one row
	 * per kind with the same value (effective access unchanged, idempotent).
	 *
	 * @group GM-082
	 * @group R1-18
	 */
	public function test_E332_legacy_single_limit_migration(): void {
		$this->without_ar_01();
		$db     = Database::current();
		$course = $this->fx['courses']['C-A'];
		$id     = $db->insert(
			'ccap_access_rule',
			array(
				'course_id'   => $course,
				'scope'       => 'view',
				'access_type' => 'free_limited',
				'limit_type'  => TrialLimits::LEGACY,
				'limit_value' => 2,
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
		$free    = Actor::user( $this->fx['users']['free'] );
		$modules = array( $this->fx['modules']['M-A1'], $this->fx['modules']['M-A2'], $this->fx['modules']['M-A3'] );
		$lesson3 = (int) NeutralFixture::object( 'lesson', 'cc-fx-l-a-03' );
		$probe   = function () use ( $free, $course, $modules, $lesson3 ): array {
			$gate = ContentGate::current();
			return array(
				$gate->question( $free, $course, $modules[0], NeutralFixture::question( 'A1-002' ) )->allowed(),
				$gate->question( $free, $course, $modules[0], NeutralFixture::question( 'A1-003' ) )->reason,
				$gate->lesson( $free, $course, $lesson3 )->reason,
				$gate->exam( $free, $course, $modules, 2 )->allowed(),
				$gate->exam( $free, $course, $modules, 3 )->reason,
				array_column( ProtectedFiles::current()->listing( $free, $course ), 'locked' ),
			);
		};
		$before  = $probe();
		self::assertSame( array( true, 'trial', 'trial', true, 'trial', array( false, false, true ) ), $before );

		$step  = new AccessRuleKindsBackfill();
		$table = $db->table( 'ccap_access_rule' );
		self::assertContains( AccessRuleKindsBackfill::ID, array_map( static fn( $s ): string => $s->id(), Migrator::core_backfills() ) );
		self::assertSame( '1', (string) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE " . $step->pending_where() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Constant condition.
		self::assertSame( 1, $step->apply( $db, array( $id ) ) );
		self::assertSame( 0, $step->apply( $db, array( $id ) ), 'idempotent' );
		self::assertSame( '0', (string) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE " . $step->pending_where() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Constant condition.
		PolicyService::flush();
		self::assertSame(
			array(
				TrialLimits::QUESTIONS => 2,
				TrialLimits::LESSONS   => 2,
				TrialLimits::VARIANTS  => 2,
				TrialLimits::FILES     => 2,
			),
			TrialLimits::current()->limits( $course )
		);
		self::assertSame( TrialLimits::QUESTIONS, (string) $db->scalar( $db->prepare( "SELECT limit_type FROM `{$table}` WHERE id = %d", $id ) ), 'the legacy row keeps its ID' );
		self::assertSame( $before, $probe(), 'effective access unchanged' );
	}

	/**
	 * GM-015 with AR-07 (1 download): U-L3 sees DL-A-1…3, only DL-A-1 free; U-L1 all free.
	 *
	 * @group GM-015
	 */
	public function test_GM_015_downloads(): void {
		$this->without_ar_01();
		NeutralFixture::rule( 'AR-07', $this->fx );
		$files = ProtectedFiles::current();
		$free  = $files->listing( Actor::user( $this->fx['users']['free'] ), $this->fx['courses']['C-A'] );
		self::assertSame( array( 'uebersicht', 'arbeitsblatt', 'Übersicht "A"' ), array_column( $free, 'name' ) );
		self::assertSame( array( false, true, true ), array_column( $free, 'locked' ) );
		self::assertSame( array( false, false, false ), array_column( $files->listing( Actor::user( $this->fx['users']['lea'] ), $this->fx['courses']['C-A'] ), 'locked' ) );
		self::assertSame( 'attachment; filename="Ubersicht \\"A\\".pdf"; filename*=UTF-8\'\'%C3%9Cbersicht%20%22A%22.pdf', ProtectedFiles::content_disposition( 'Übersicht "A".pdf' ) );
	}

	/**
	 * GM-052: PKG-v2 during a running exam: the attempt keeps Q-A1-007 rev1, a new attempt uses rev2.
	 *
	 * @group GM-052
	 */
	public function test_GM_052(): void {
		$running = $this->start( 'lea', 'ER-A', 'EP-A-1' );
		$before  = self::session( $running )['questions'][7];
		self::assertSame( NeutralFixture::question( 'A1-007' ), $before['question'] );

		$service = ImportService::current();
		$admin   = Actor::user( $this->fx['users']['admin'] );
		$batch   = $service->upload( $admin, NeutralFixture::package( 2, $this->dir . '/pkg-v2.zip' ) );
		$service->validate( $admin, $batch );
		$service->preview( $admin, $batch );
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );

		self::assertSame( $before['revision'], self::session( $running )['questions'][7]['revision'], 'running attempt keeps rev1' );
		$text = static fn( int $revision ): string => (string) ( (array) QuestionRepository::current()->revision( $revision ) )['question_text'];
		self::assertStringEndsNotWith( '(korrigiert)', $text( $before['revision'] ) );
		$new  = self::session( $this->start( 'ole', 'ER-A', 'EP-A-1' ) )['questions'][7];
		self::assertNotSame( $before['revision'], $new['revision'] );
		self::assertStringEndsWith( '(korrigiert)', $text( $new['revision'] ) );
	}

	/**
	 * GM-151 / GM-049 on the fixture: U-X can neither see nor change U-L1's attempt (404).
	 *
	 * @group GM-049
	 * @group GM-151
	 */
	public function test_GM_049_151_idor(): void {
		$session  = $this->start( 'lea', 'ER-A', 'EP-A-1' );
		$other    = Actor::user( $this->fx['users']['other'] );
		$sessions = ExamSessions::current();
		$this->rejected( fn() => $sessions->view( $other, $session ), 'ccap_not_found' );
		$this->rejected( fn() => $sessions->save_answer( $other, $session, 1, array( 'A' ) ), 'ccap_not_found' );
		$this->rejected( fn() => $sessions->submit( $other, $session ), 'ccap_not_found' );
		self::assertSame( 'in_progress', self::session( $session )['row']['status'] );
	}

	/**
	 * GM-119: the identical PKG-v1 again changes nothing.
	 *
	 * @group GM-119
	 */
	public function test_GM_119(): void {
		$service = ImportService::current();
		$admin   = Actor::user( $this->fx['users']['admin'] );
		$db      = Database::current();
		$count   = static fn(): string => (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_question_revision' ) . '`' );
		$before  = $count();
		$batch   = $service->upload( $admin, NeutralFixture::package( 1, $this->dir . '/pkg-v1-again.zip' ) );
		$service->validate( $admin, $batch );
		$plan = $service->preview( $admin, $batch )['plan'];
		self::assertSame( array( 'unchanged' => 71 ), $plan['questions'] );
		self::assertSame( array( 'unchanged' => 13 ), $plan['flashcards'] );
		self::assertSame( array( 'unchanged' => 12 ), $plan['lessons'], '12 lessons incl. the archived L-A-10 (R0-05 §2.2)' );
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );
		self::assertSame( $before, $count(), '0 new revisions' );
	}

	/**
	 * GM-144: C-C (M-C1 without questions, ER-C 10 > 0) cannot be published; the report names the problems.
	 *
	 * @group GM-144
	 */
	public function test_GM_144(): void {
		$report = CourseValidator::validate( $this->fx['courses']['C-C'], 'publish' );
		self::assertFalse( CourseValidator::publishable( $report ) );
		self::assertGreaterThanOrEqual( 2, $report['summary']['blocker'], (string) wp_json_encode( $report['issues'] ) );
	}

	/**
	 * GM-128 (upload part) with MED-BAD-1…6: MED-001 passes the file check, every bad file is rejected.
	 *
	 * @group GM-128
	 */
	public function test_GM_128_bad_media(): void {
		$rules = array(
			'allowlist' => array( 'png', 'pdf' ),
			'max_bytes' => 2097152,
		);
		$png   = $this->dir . '/a1-003.png';
		file_put_contents( $png, NeutralFixture::media_files()['a1-003.png'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Scratch file.
		self::assertSame( 'image/png', AssignmentFiles::check( $png, 'a1-003.png', 'image/png', $rules ) );
		foreach ( NeutralFixture::bad_media( $this->dir ) as $key => $file ) {
			$this->rejected( fn() => AssignmentFiles::check( $file['path'], $file['name'], $file['mime'], $rules ), 'ccap_submission_file_rejected' );
		}
	}

	/**
	 * GM-062 / GM-063 on FC-A (self-assessment, 8 cards) and FC-B (streak 3).
	 *
	 * @group GM-062
	 * @group GM-063
	 */
	public function test_GM_062_063_flashcards(): void {
		$decks = NeutralFixture::flashcards( $this->fx );
		self::assertCount( 13, $decks['cards'] );
		self::assertSame( $decks, NeutralFixture::flashcards( $this->fx ), 'idempotent' );
		$lea   = Actor::user( $this->fx['users']['lea'] );
		$fc    = Flashcards::current();
		$token = fn( int $lesson, int $card ): string => QuestionPresenter::current()->deliver_inline( $lea, $this->fx['courses']['C-A'], $lesson, $card )['token'];
		foreach ( range( 1, 8 ) as $i ) {
			$fc->set_state( $lea, $token( $decks['FC-A'], $decks['cards'][ sprintf( 'FC-A-%02d', $i ) ] ), 'known', wp_generate_uuid4() );
		}
		self::assertSame( 'completed', ActivityProgress::current()->state( $this->fx['users']['lea'], $decks['FC-A'] )['status'] );
		$card = $token( $decks['FC-B'], $decks['cards']['FC-B-01'] );
		self::assertSame( 'practice', $fc->assess( $lea, $card, true, wp_generate_uuid4() )['state'] );
		$fc->assess( $lea, $card, true, wp_generate_uuid4() );
		self::assertSame( 'known', $fc->assess( $lea, $card, true, wp_generate_uuid4() )['state'], 'learned after 3 in a row' );
		$state = CourseProgress::current()->recalculate( $this->fx['users']['lea'], $this->fx['courses']['C-A'] );
		self::assertSame( 8, $state['activities_total'], 'optional deck module outside the denominator' );
	}

	/**
	 * GM-005: progress in the course list equals the progress in the course data.
	 *
	 * @group GM-005
	 */
	public function test_GM_005(): void {
		$lea    = Actor::user( $this->fx['users']['lea'] );
		$lesson = (int) NeutralFixture::object( 'lesson', 'cc-fx-l-a-01' );
		ActivityProgress::current()->interact( $lea, $this->fx['courses']['C-A'], $lesson, 'confirm', array() );
		$list = array_column( LearnerQueries::current()->my_courses( $lea ), null, 'course_id' );
		$room = LearnerQueries::current()->course( $lea, $this->fx['courses']['C-A'] );
		$item = $list[ $this->fx['courses']['C-A'] ]['progress'];
		self::assertSame( array( '1', '8' ), array( (string) $room['progress']['activities_done'], (string) $room['progress']['activities_total'] ) );
		self::assertSame( (int) floor( 100 * (float) $room['progress']['completion_ratio'] ), $item['completion_percent'] );
		self::assertSame( $room['progress']['status'], $item['status'] );
	}

	/**
	 * GM-118 (path) on PKG-v2: validated → run without approval refused → approved → published.
	 *
	 * @group GM-118
	 */
	public function test_GM_118(): void {
		$service = ImportService::current();
		$admin   = Actor::user( $this->fx['users']['admin'] );
		$batch   = $service->upload( $admin, NeutralFixture::package( 2, $this->dir . '/pkg-v2.zip' ) );
		$service->validate( $admin, $batch );
		$service->preview( $admin, $batch );
		try {
			$service->run( $admin, $batch );
			self::fail( 'run without approval' );
		} catch ( CourseException $e ) {
			self::assertSame( 409, $e->status, $e->error_code );
		}
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );
		$db = Database::current();
		self::assertSame( 'published', (string) $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_import_batch' ) . '` WHERE id = %d', $batch ) ) );
	}

	/**
	 * GM-132: the package build is reproducible (same content hash and package ID),
	 * source IDs are unique and every reference resolves.
	 *
	 * @group GM-132
	 */
	public function test_GM_132(): void {
		$manifest = static function ( string $file ): array {
			$zip = new \ZipArchive();
			self::assertTrue( $zip->open( $file ) );
			$data = (array) json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
			$zip->close();
			return $data;
		};
		$a        = $manifest( NeutralFixture::package( 1, $this->dir . '/a.zip' ) );
		$b        = $manifest( NeutralFixture::package( 1, $this->dir . '/b.zip' ) );
		self::assertSame( array( $a['package_id'], $a['content_sha256'] ), array( $b['package_id'], $b['content_sha256'] ) );
		self::assertNotSame( $a['content_sha256'], $manifest( NeutralFixture::package( 2, $this->dir . '/c.zip' ) )['content_sha256'] );

		$records = NeutralFixture::records( 1 );
		$ids     = array();
		foreach ( $records as $list ) {
			$ids = array_merge( $ids, array_column( $list, 'source_id' ) );
		}
		self::assertSame( count( $ids ), count( array_unique( $ids ) ), '0 duplicates' );
		$refs = array();
		foreach ( $records['modules'] as $module ) {
			$refs = array_merge( $refs, array_column( (array) $module['lessons'], 'lesson' ), array_column( (array) $module['question_pool'], 'question' ) );
		}
		foreach ( $records['courses'] as $course ) {
			$refs = array_merge( $refs, array_column( (array) $course['modules'], 'module' ) );
		}
		self::assertSame( array(), array_values( array_diff( $refs, $ids ) ), '0 open references' );
	}

	/**
	 * Remove AR-01 inside the test transaction (on-demand rule sets of §2.8).
	 */
	private function without_ar_01(): void {
		$db = Database::current();
		$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_access_rule' ) . "` WHERE course_id = %d AND access_type = 'free_limited'", $this->fx['courses']['C-A'] ) );
		PolicyService::flush();
	}
}
