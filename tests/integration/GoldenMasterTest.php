<?php
/**
 * R1-18: Golden-Master checks on `cc-fixture-neutral-v1` (R0-05 §1, §2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Import\ImportService;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearnerQueries;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Progress\CourseProgress;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;
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
	 * @var array{users: array<string, int>, courses: array<string, int>}
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
	}

	/**
	 * Fixture counts (§2.2/§2.3) and loader idempotence (D-03).
	 */
	public function test_fixture_counts_and_idempotence(): void {
		$db = Database::current();
		self::assertSame( '70', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_question' ) . "` WHERE status = 'published'" ) );
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
	 */
	public function test_pkg_v2(): void {
		$service = ImportService::current();
		$admin   = Actor::user( $this->fx['users']['admin'] );
		$batch   = $service->upload( $admin, NeutralFixture::package( 2, $this->dir . '/pkg-v2.zip' ) );
		$service->validate( $admin, $batch );
		$plan = $service->preview( $admin, $batch )['plan'];
		self::assertSame(
			array(
				'unchanged' => 69,
				'update'    => 1,
			),
			$plan['questions']
		);
		self::assertSame( array( 'unchanged' => 11 ), $plan['lessons'] );
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );
		$db  = Database::current();
		$q   = (int) NeutralFixture::object( 'question', 'cc-fx-q-a1-007' );
		$rev = (array) QuestionRepository::current()->revision( (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $q ) ) );
		self::assertStringEndsWith( '(korrigiert)', (string) $rev['question_text'] );
	}
}
