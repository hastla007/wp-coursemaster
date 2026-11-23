<?php
/**
 * R1-18 follow-up (group g3): GM-089, GM-090, GM-092, GM-104, GM-113,
 * GM-114, GM-115, GM-117 on `cc-fixture-neutral-v1` (R0-05 §1.8–§1.10).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\GrantTransitions;
use CourseCaptain\Access\GroupService;
use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Access\StorageLayout;
use CourseCaptain\Activity\MediaLibrary;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\Invitation;
use CourseCaptain\Learner\LearnerQueries;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Ops\AuditView;
use CourseCaptain\Ops\HealthChecks;
use CourseCaptain\Ops\OpsCommand;
use CourseCaptain\Ops\RestoreCheck;
use CourseCaptain\Ops\StorageSelfTest;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\Fixtures\NeutralFixture;

/**
 * Each test loads the fixture itself (idempotent, D-03).
 *
 * @group R1-18
 */
final class GoldenMasterG3Test extends SchemaTestCase {

	/**
	 * Loaded fixture.
	 *
	 * @var array{users: array<string, int>, courses: array<string, int>, modules: array<string, int>, rules: array<string, int>, variants: array<string, int>, media: array<string, int>, pages: array<string, int>, groups: array<string, int>}
	 */
	private array $fx;

	/**
	 * Scratch directories removed after the test.
	 *
	 * @var list<string>
	 */
	private array $dirs = array();

	/**
	 * HTTP stubs added by the test.
	 *
	 * @var list<\Closure>
	 */
	private array $stubs = array();

	/**
	 * Load the fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$dir          = self::temp_dir( 'fx' );
		$this->dirs[] = $dir;
		$this->fx     = NeutralFixture::load( $dir );
	}

	/**
	 * Remove filters, faults and scratch folders.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_option_users_can_register', array( self::class, 'one' ) );
		remove_filter( 'pre_site_option_registration', array( self::class, 'user' ) );
		remove_all_filters( 'course_captain_private_dir' );
		foreach ( $this->stubs as $stub ) {
			remove_filter( 'pre_http_request', $stub, 10 );
		}
		StorageLayout::with_fault( null );
		delete_transient( StorageSelfTest::TRANSIENT );
		foreach ( $this->dirs as $dir ) {
			self::remove_dir( $dir );
		}
		parent::tear_down();
	}

	/**
	 * GM-089: grant rules of the source API – extend appends at the end,
	 * parallel starts now, no duration is permanent, an existing permanent
	 * access refuses (`ccap_access_exists`, nothing created), no learner is
	 * `ccap_user_required`, revocation by rule (`revoke` moves the extension
	 * forward, `keep` keeps the grant).
	 *
	 * @group GM-089
	 */
	public function test_GM_089_grant_api_rules(): void {
		$grants = GrantService::current();
		$fake   = Actor::connector( 'fake' );
		$user   = $this->fx['users']['free'];
		$course = $this->fx['courses']['C-B'];
		$req    = static fn( string $ref, array $extra = array() ): array => array(
			'user_id'          => $user,
			'course_id'        => $course,
			'source_type'      => 'fake',
			'source_reference' => $ref,
		) + $extra;
		$row    = static function ( int $id ): array {
			$db = Database::current();
			return (array) $db->row( $db->prepare( 'SELECT status, valid_from, valid_until FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $id ) );
		};
		$near   = static fn( int $expected, ?string $actual ): bool => null !== $actual && abs( (int) strtotime( $actual . ' UTC' ) - $expected ) <= 5;

		// Extension: 365 d, the second one starts where the first ends.
		$first  = $grants->grant_from_source( $req( 'fx:grant:0101', array( 'duration_days' => 365 ) ), $fake );
		$second = $grants->grant_from_source( $req( 'fx:grant:0102', array( 'duration_days' => 365 ) ), $fake );
		self::assertSame( 'created', $first['code'] );
		self::assertTrue( $near( time() + 365 * DAY_IN_SECONDS, $row( $first['grant_id'] )['valid_until'] ) );
		self::assertSame( $row( $first['grant_id'] )['valid_until'], $row( $second['grant_id'] )['valid_from'], 'appended at the end' );
		self::assertTrue( $near( time() + 730 * DAY_IN_SECONDS, $row( $second['grant_id'] )['valid_until'] ) );

		// Parallel: 30 d from now.
		$parallel = $grants->grant_from_source(
			$req(
				'fx:grant:0103',
				array(
					'duration_days' => 30,
					'renewal_mode'  => 'parallel',
				)
			),
			$fake
		);
		self::assertTrue( $near( time(), $row( $parallel['grant_id'] )['valid_from'] ), 'starts now' );
		self::assertTrue( $near( time() + 30 * DAY_IN_SECONDS, $row( $parallel['grant_id'] )['valid_until'] ) );

		// Revocation by rule: revoke → revoked, the extension moves forward; keep → unchanged.
		self::assertSame( 'updated', $grants->revoke_source( $fake, 'fake', 'fx:grant:0101', 'revoke', 'item_refunded' )['code'] );
		self::assertSame( GrantTransitions::REVOKED, $row( $first['grant_id'] )['status'] );
		self::assertTrue( $near( time(), $row( $second['grant_id'] )['valid_from'] ), 'extension moved forward' );
		self::assertTrue( $near( time() + 365 * DAY_IN_SECONDS, $row( $second['grant_id'] )['valid_until'] ), 'duration kept' );
		self::assertSame( 'unchanged', $grants->revoke_source( $fake, 'fake', 'fx:grant:0103', 'keep' )['code'] );
		self::assertSame( GrantTransitions::ACTIVE, $row( $parallel['grant_id'] )['status'], 'rule "keep"' );
		$this->assertRejected( static fn() => $grants->revoke_source( $fake, 'fake', 'fx:grant:0103', 'delete' ), 'ccap_invalid_param' );

		// Permanent: no duration.
		$permanent = $grants->grant_from_source( $req( 'fx:grant:0104' ), $fake );
		self::assertNull( $row( $permanent['grant_id'] )['valid_until'] );
		self::assertSame( 'duplicate', $grants->grant_from_source( $req( 'fx:grant:0104' ), $fake )['code'], 'same key returns the grant' );

		// Existing permanent access: refused, nothing created.
		$count = self::grant_count();
		$this->assertRejected( static fn() => $grants->grant_from_source( $req( 'fx:grant:0105', array( 'duration_days' => 365 ) ), $fake ), 'ccap_access_exists' );
		$this->assertRejected( static fn() => $grants->grant_from_source( $req( 'fx:grant:0106', array( 'scopes' => 'view,exam' ) ), $fake ), 'ccap_access_exists' );
		// Without learner.
		$this->assertRejected( static fn() => $grants->grant_from_source( array( 'user_id' => 0 ) + $req( 'fx:grant:0107' ), $fake ), 'ccap_user_required' );
		self::assertSame( $count, self::grant_count(), 'nothing created' );
		// Another learner on a used key.
		$other = $this->fx['users']['lea'];
		$this->assertRejected( static fn() => $grants->grant_from_source( array( 'user_id' => $other ) + $req( 'fx:grant:0104' ), $fake ), 'ccap_idempotency_conflict' );
	}

	/**
	 * GM-090: grant audit with learner, source, reference and time; the
	 * search by reference finds the grant and its audit entries.
	 *
	 * @group GM-090
	 */
	public function test_GM_090_grant_audit_search_by_reference(): void {
		$grants = GrantService::current();
		$fake   = Actor::connector( 'fake' );
		$user   = $this->fx['users']['free'];
		$id     = $grants->grant_from_source(
			array(
				'user_id'          => $user,
				'course_id'        => $this->fx['courses']['C-A'],
				'source_type'      => 'fake',
				'source_reference' => 'fx:grant:0001',
				'duration_days'    => 30,
			),
			$fake
		)['grant_id'];
		$grants->grant_from_source(
			array(
				'user_id'          => $this->fx['users']['lea'],
				'course_id'        => $this->fx['courses']['C-B'],
				'source_type'      => 'fake',
				'source_reference' => 'fx:grant:0002',
			),
			$fake
		);

		$found = $grants->find_by_reference( 'fx:grant:0001' );
		self::assertCount( 1, $found );
		self::assertSame( $id, (int) $found[0]['id'] );
		self::assertSame( array(), $grants->find_by_reference( 'fx:grant:0001', 'manual' ) );

		$db    = Database::current();
		$audit = (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'grant.source_created' AND object_id = %s", (string) $id ) );
		self::assertSame( (string) $user, (string) $audit['subject_user_id'], 'learner' );
		self::assertSame( 'connector', $audit['actor_type'], 'source' );
		self::assertNotEmpty( $audit['created_at'], 'time' );
		$context = (array) json_decode( (string) $audit['context'], true );
		self::assertSame( 'fake', $context['source_type'] );
		self::assertSame( 'fx:grant:0001', $context['source_reference'] );

		$grants->revoke_source( $fake, 'fake', 'fx:grant:0001', 'revoke' );
		$admin  = Actor::user( $this->fx['users']['admin'] );
		$result = AuditView::query( $admin, array( 'reference' => 'fx:grant:0001' ) );
		self::assertSame( 2, $result['total'], 'created and revoked, nothing of fx:grant:0002' );
		self::assertSame( array( 'grant ' . $id, 'grant ' . $id ), array_column( $result['items'], 'object' ) );
		self::assertStringContainsString( 'fx:grant:0001', $result['items'][1]['details'] );
		self::assertSame( 'access', $result['items'][0]['area'] );
		self::assertNotSame( '', $result['items'][0]['time'] );

		// Manual grants are found by their request key (G-10).
		$manual = AuditView::query( $admin, array( 'reference' => NeutralFixture::uuid( 'G-10' ) ) );
		self::assertSame( 1, $manual['total'] );
		self::assertSame( 0, AuditView::query( $admin, array( 'reference' => 'fx:grant:9999' ) )['total'] );
	}

	/**
	 * GM-092: manual grant + group seat + free rule at the same time: union
	 * of the scopes, the latest end wins, ending one source keeps the others.
	 *
	 * @group GM-092
	 */
	public function test_GM_092_manual_group_and_free_rule_combined(): void {
		$user      = $this->fx['users']['seat_1'];
		$course    = $this->fx['courses']['C-A'];
		$relations = new Relations( Database::current() );
		$owner     = Actor::user( $this->fx['users']['group_owner'] );
		$groups    = GroupService::current();
		NeutralFixture::rule( 'AR-08', $this->fx );
		$seat = $groups->claim( Actor::user( $user ), $groups->rotate_link( $owner, $this->fx['groups']['GRP-1'] ) );
		PolicyService::flush();

		$db     = Database::current();
		$table  = $db->table( 'ccap_grant' );
		$manual = (array) $db->row( $db->prepare( "SELECT id, valid_until FROM `{$table}` WHERE source_type = 'manual' AND source_id = %s", NeutralFixture::uuid( 'G-10' ) ) );
		$group  = (array) $db->row( $db->prepare( "SELECT id, valid_until FROM `{$table}` WHERE source_type = 'group_seat' AND user_id = %d", $user ) );
		self::assertGreaterThan( $manual['valid_until'], $group['valid_until'] );

		// Union: view (manual, group, free), exam (group); download from no source.
		self::assertSame( 'ok', $relations->effective_access( $user, $course, 0, 'view' ) );
		self::assertSame( 'ok', $relations->effective_access( $user, $course, 0, 'exam' ) );
		self::assertNotSame( 'ok', $relations->effective_access( $user, $course, 0, 'download' ) );
		$state = LearnerQueries::current()->access_state( Actor::user( $user ), $course );
		self::assertSame( 'active', $state['state'] );
		self::assertSame( $group['valid_until'], $state['valid_until'], 'latest end wins' );

		// Ending the group seat keeps the manual grant and the free rule.
		$groups->remove_seat( $owner, $this->fx['groups']['GRP-1'], $seat );
		PolicyService::flush();
		self::assertSame( 'ok', $relations->effective_access( $user, $course, 0, 'view' ) );
		self::assertNotSame( 'ok', $relations->effective_access( $user, $course, 0, 'exam' ) );
		self::assertSame( GrantTransitions::ACTIVE, (string) $db->scalar( $db->prepare( "SELECT status FROM `{$table}` WHERE id = %d", (int) $manual['id'] ) ) );
		self::assertSame( $manual['valid_until'], LearnerQueries::current()->access_state( Actor::user( $user ), $course )['valid_until'] );

		// Revoking the manual grant keeps the free rule.
		GrantService::current()->transition( Actor::user( $this->fx['users']['admin'] ), (int) $manual['id'], GrantTransitions::REVOKED, 'manual_revoke', true );
		PolicyService::flush();
		self::assertSame( 'ok', $relations->effective_access( $user, $course, 0, 'view' ) );

		// A source without end makes the combined access permanent.
		GrantService::current()->grant_manual( Actor::user( $this->fx['users']['admin'] ), $user, $course, wp_generate_uuid4(), 'GM-092', array( 'scopes' => 'download' ) );
		$state = LearnerQueries::current()->access_state( Actor::user( $user ), $course );
		self::assertSame( 'active', $state['state'] );
		self::assertNull( $state['valid_until'] );
	}

	/**
	 * GM-104: after login/registration back to the invitation; a foreign
	 * target is ignored; guests see no group data; accepting works without
	 * JavaScript.
	 *
	 * @group GM-104
	 */
	public function test_GM_104_return_to_invitation(): void {
		Invitation::register();
		$token  = GroupService::current()->rotate_link( Actor::user( $this->fx['users']['group_owner'] ), $this->fx['groups']['GRP-1'] );
		$invite = Invitation::url( $token );
		$user   = get_userdata( $this->fx['users']['seat_2'] );
		self::assertSame( $invite, Invitation::return_target( $invite ) );
		$path = (string) wp_parse_url( LearningArea::url(), PHP_URL_PATH );
		foreach ( array(
			'https://evil.example' . $path . '?ccap_claim=' . $token,
			home_url( '/somewhere-else/?ccap_claim=' . $token ),
			add_query_arg( 'ccap_claim', 'ccg_short', LearningArea::url() ),
		) as $foreign ) {
			self::assertSame( '', Invitation::return_target( $foreign ), $foreign );
			self::assertSame( LearningArea::url(), apply_filters( 'login_redirect', $foreign, $foreign, $user ), 'foreign invitation target ignored' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
			self::assertSame( '', apply_filters( 'registration_redirect', $foreign, 0 ), 'foreign invitation target ignored' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
		}
		self::assertSame( $invite, apply_filters( 'login_redirect', $invite, $invite, $user ), 'back after login' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
		self::assertSame( add_query_arg( 'checkemail', 'registered', wp_login_url( $invite ) ), apply_filters( 'registration_redirect', $invite, 0 ), 'back after registration via login' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
		self::assertSame( admin_url( 'profile.php' ), apply_filters( 'login_redirect', admin_url( 'profile.php' ), admin_url( 'profile.php' ), $user ), 'other targets untouched' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.

		// Guest page: login and registration lead back, no group data.
		// Open registration via filters (no option writes: SchemaTestCase commits).
		add_filter( 'pre_option_users_can_register', array( self::class, 'one' ) );
		add_filter( 'pre_site_option_registration', array( self::class, 'user' ) );
		$guest = LearningArea::resolve( Actor::user( 0 ), '', '', $token )['body'];
		self::assertStringContainsString( esc_url( wp_login_url( $invite ) ), $guest );
		self::assertStringContainsString( esc_url( add_query_arg( 'redirect_to', rawurlencode( $invite ), wp_registration_url() ) ), $guest );
		self::assertStringNotContainsString( 'Gruppe Eins', $guest );

		// Logged in: invitation with form; accepting claims a seat.
		wp_set_current_user( $user->ID );
		$page = LearningArea::resolve( Actor::user( $user->ID ), '', '', $token );
		self::assertStringContainsString( 'Gruppe Eins', $page['body'] );
		self::assertStringContainsString( 'name="ccap_do" value="claim"', $page['body'] );
		$bad = LearningArea::handle_post(
			Actor::user( $user->ID ),
			'',
			array(
				'ccap_do'    => 'claim',
				'ccap_claim' => $token,
				'_wpnonce'   => 'x',
			)
		);
		self::assertSame( add_query_arg( 'ccap_status', 'ccap_forbidden', $invite ), $bad );
		$done = LearningArea::handle_post(
			Actor::user( $user->ID ),
			'',
			array(
				'ccap_do'    => 'claim',
				'ccap_claim' => $token,
				'_wpnonce'   => wp_create_nonce( Invitation::NONCE ),
			)
		);
		self::assertSame( LearningArea::url(), $done );
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_seat' ) . "` WHERE user_id = %d AND status = 'active'", $user->ID ) ) );
		wp_set_current_user( 0 );
	}

	/**
	 * GM-113: moving the legacy folder; on failure the old folder stays
	 * active without data loss, the retry waits; then complete move (copy
	 * fallback), paths rewritten.
	 *
	 * @group GM-113
	 */
	public function test_GM_113_move_legacy_folder_with_fallback(): void {
		// Legacy layout: protected files below uploads/course-captain.
		$legacy = ProtectedStorage::default_base();
		$file   = $this->protected_attachment( 'legacy' );
		$old    = (string) get_attached_file( $file['attachment'], true );
		self::assertStringStartsWith( $legacy . '/protected-', $old );
		$bytes = (string) file_get_contents( $old );

		$private      = self::temp_dir( 'private' );
		$this->dirs[] = $private;
		add_filter( 'course_captain_private_dir', static fn(): string => $private );

		// Failure: rename and copy fail → old folder active, nothing half moved.
		StorageLayout::with_fault( static fn(): bool => false );
		self::assertSame( $legacy, ProtectedStorage::base() );
		self::assertFileExists( $old );
		self::assertSame( $old, get_attached_file( $file['attachment'], true ) );
		self::assertDirectoryDoesNotExist( $private . '/protected-' . ProtectedStorage::secret() );
		self::assertGreaterThan( 0, StorageLayout::state()['failed_at'] );
		self::assertSame( 'critical', HealthChecks::test_storage()['status'] );
		self::assertTrue( ProtectedStorage::is_protected_path( $old ), 'old folder still serves' );

		// Retry only after the waiting time.
		StorageLayout::with_fault( null );
		self::assertSame( $legacy, ProtectedStorage::base() );
		self::assertFileExists( $old );
		$state              = (array) get_option( StorageLayout::OPTION );
		$state['failed_at'] = time() - StorageLayout::RETRY_AFTER - 1;
		update_option( StorageLayout::OPTION, $state );

		// Rename refused, copy works: completely moved, source removed, paths rewritten.
		StorageLayout::with_fault( static fn( string $op ): bool => 'copy' === $op );
		self::assertSame( $private, ProtectedStorage::base() );
		$new = (string) get_attached_file( $file['attachment'], true );
		self::assertSame( $private . substr( $old, strlen( $legacy ) ), $new );
		self::assertSame( $bytes, file_get_contents( $new ) );
		self::assertFileDoesNotExist( $old );
		self::assertDirectoryDoesNotExist( $legacy . '/protected-' . ProtectedStorage::secret() );
		self::assertTrue( ProtectedStorage::is_protected_path( $new ) );
		self::assertSame( 0, StorageLayout::state()['failed_at'] );
		self::assertSame( 'good', HealthChecks::test_storage()['status'] );
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'storage_moved'" ) );
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'storage_move_failed'" ) );
	}

	/**
	 * GM-114: private folder outside the web root: no public URL, self test
	 * "private" without HTTP request; the way back moves the files and
	 * writes the paths back.
	 *
	 * @group GM-114
	 */
	public function test_GM_114_private_folder_outside_web_root(): void {
		$private      = self::temp_dir( 'private' );
		$this->dirs[] = $private;
		add_filter( 'course_captain_private_dir', static fn(): string => $private );
		$requests      = 0;
		$this->stubs[] = static function () use ( &$requests ) {
			++$requests;
			return new \WP_Error( 'blocked', 'no requests expected' );
		};
		add_filter( 'pre_http_request', $this->stubs[0] );

		$file = $this->protected_attachment( 'private' );
		$path = (string) get_attached_file( $file['attachment'], true );
		self::assertStringStartsWith( $private . '/protected-', $path );
		self::assertNull( ProtectedStorage::public_url( ProtectedStorage::dir( 'protected' ) ), 'no public URL' );
		self::assertNull( ProtectedStorage::public_url( ProtectedStorage::dir( 'packages' ) ) );
		$test = StorageSelfTest::run( true );
		self::assertSame( 'private', $test['state'] );
		self::assertSame(
			array(
				'protected' => 'private',
				'packages'  => 'private',
			),
			$test['folders']
		);
		self::assertSame( 0, $requests );
		self::assertSame( 'good', StorageSelfTest::health()['status'] );

		// Way back: constant removed → files back below uploads, paths rewritten.
		remove_all_filters( 'course_captain_private_dir' );
		self::assertSame( ProtectedStorage::default_base(), ProtectedStorage::base() );
		$back = (string) get_attached_file( $file['attachment'], true );
		self::assertSame( ProtectedStorage::default_base() . substr( $path, strlen( $private ) ), $back );
		self::assertFileExists( $back );
		self::assertFileDoesNotExist( $path );
		self::assertNotNull( ProtectedStorage::public_url( ProtectedStorage::dir( 'protected' ) ) );
		self::assertTrue( ProtectedStorage::is_protected_path( $back ) );
	}

	/**
	 * GM-115: HTTP self test of the protected folders (stub): 200 with the
	 * token → critical with the nginx rule, 403 → green, no answer →
	 * yellow; the result is cached; probe files are removed.
	 *
	 * @group GM-115
	 */
	public function test_GM_115_http_self_test(): void {
		$answer        = 'exposed';
		$requests      = 0;
		$this->stubs[] = static function ( $pre, array $args, string $url ) use ( &$answer, &$requests ) {
				++$requests;
				self::assertSame( 0, $args['redirection'] );
				self::assertSame( 3, $args['timeout'] );
				self::assertTrue( $args['reject_unsafe_urls'], 'C-13: safe HTTP API (SSRF)' );
			if ( 'none' === $answer ) {
				return new \WP_Error( 'http_request_failed', 'timeout' );
			}
				$file = ProtectedStorage::default_base() . substr( (string) wp_parse_url( $url, PHP_URL_PATH ), strlen( (string) wp_parse_url( ProtectedStorage::public_url( ProtectedStorage::default_base() ), PHP_URL_PATH ) ) );
				return array(
					'headers'  => array(),
					'body'     => 'exposed' === $answer ? (string) file_get_contents( $file ) : 'Forbidden',
					'response' => array(
						'code'    => 'exposed' === $answer ? 200 : 403,
						'message' => '',
					),
					'cookies'  => array(),
				);
		};
		add_filter( 'pre_http_request', $this->stubs[0], 10, 3 );

		$health = StorageSelfTest::health();
		self::assertSame( 'exposed', $health['state'] );
		self::assertSame( 'critical', $health['status'] );
		self::assertStringContainsString( '(packages|protected)- { deny all; }', $health['description'] );
		self::assertStringContainsString( (string) wp_parse_url( ProtectedStorage::public_url( ProtectedStorage::default_base() ), PHP_URL_PATH ), StorageSelfTest::nginx_rule() );
		self::assertStringNotContainsString( ProtectedStorage::secret(), $health['description'] );
		self::assertSame( 2, $requests, 'protected and packages' );

		// Cached: no new request.
		$answer = 'blocked';
		self::assertSame( 'critical', StorageSelfTest::health()['status'] );
		self::assertSame( 2, $requests );

		$blocked = StorageSelfTest::run( true );
		self::assertSame( 'ok', $blocked['state'] );
		self::assertSame( 'good', StorageSelfTest::health()['status'] );

		$answer = 'none';
		self::assertSame( 'unknown', StorageSelfTest::run( true )['state'] );
		$health = StorageSelfTest::health();
		self::assertSame( 'recommended', $health['status'] );
		$timeout = (int) get_option( '_transient_timeout_' . StorageSelfTest::TRANSIENT );
		if ( $timeout > 0 ) {
			self::assertLessThanOrEqual( time() + HOUR_IN_SECONDS, $timeout, 'unknown is cached for one hour' );
		}
		foreach ( StorageSelfTest::KINDS as $kind ) {
			self::assertSame( array(), glob( ProtectedStorage::dir( $kind ) . '/ccap-selftest-*' ), 'probe file removed' );
		}
		self::assertArrayHasKey( 'course_captain_storage', HealthChecks::tests( array() )['direct'] );
		// The stub answered every probe in-process: only own self-test URLs, nothing left the site (LG-1).
		$base = (string) ProtectedStorage::public_url( ProtectedStorage::default_base() );
		foreach ( $GLOBALS['ccap_test_http_requests'] as $url ) {
			self::assertStringStartsWith( $base . '/', $url );
			self::assertMatchesRegularExpression( '#/ccap-selftest-[a-z0-9]{12}\.txt$#', $url );
		}
		$GLOBALS['ccap_test_http_requests'] = array();
	}

	/**
	 * GM-117: restore check only reads; a foreign folder is reported and
	 * stays; a missing protected file (MED-004 removed) is reported.
	 *
	 * @group GM-117
	 */
	public function test_GM_117_read_only_restore_check(): void {
		// Own base inside the uploads folder: isolated from other runs, still below the web root.
		$uploads      = wp_upload_dir( null, false );
		$own          = untrailingslashit( (string) $uploads['basedir'] ) . '/ccap-g3-' . strtolower( wp_generate_password( 8, false, false ) );
		$this->dirs[] = $own;
		wp_mkdir_p( $own );
		add_filter( 'course_captain_private_dir', static fn(): string => $own );
		$kept    = $this->protected_attachment( 'kept' );
		$removed = $this->protected_attachment( 'med-004' );
		$base    = ProtectedStorage::base();
		$foreign = $base . '/protected-zzzzzzzzzzzzzzzzzzzzabcd';
		wp_mkdir_p( $foreign );
		file_put_contents( $foreign . '/other.pdf', '%PDF-1.4' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->dirs[] = $foreign;
		wp_delete_file( (string) get_attached_file( $removed['attachment'], true ) );

		$before = $this->snapshot( $base );
		$report = RestoreCheck::run();
		self::assertSame( $before, $this->snapshot( $base ), 'read only' );

		self::assertSame( 'fail', $report['status'] );
		$checks = array_column( $report['checks'], null, 'id' );
		self::assertSame( 'ok', $checks['options']['status'] );
		self::assertSame( 'warn', $checks['storage']['status'] );
		self::assertStringContainsString( 'protected-…abcd', implode( ' ', $checks['storage']['items'] ) );
		self::assertFileExists( $foreign . '/other.pdf', 'foreign folder stays' );
		self::assertSame( 'fail', $checks['files']['status'] );
		self::assertSame( array( $removed['uid'] ), $checks['files']['items'] );
		self::assertStringNotContainsString( ProtectedStorage::secret(), (string) wp_json_encode( $report ) );

		$cli = OpsCommand::execute( 'backup-check', array(), array( 'format' => 'json' ), Actor::cli() );
		self::assertSame( 1, $cli['exit'] );
		self::assertSame( 'fail', json_decode( $cli['lines'][0], true )['status'] );
		self::assertSame( $before, $this->snapshot( $base ) );

		// Without the foreign folder and with the file back: ok.
		self::remove_dir( $foreign );
		file_put_contents( (string) get_attached_file( $removed['attachment'], true ), '%PDF-1.4' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		self::assertSame( 'ok', RestoreCheck::run()['status'] );
		self::assertSame( 0, OpsCommand::execute( 'backup-check', array(), array(), Actor::cli() )['exit'] );
		unset( $kept );
	}

	/**
	 * A protected PDF attachment registered as course medium.
	 *
	 * @param string $name File name part.
	 * @return array{attachment: int, media: int, uid: string}
	 */
	private function protected_attachment( string $name ): array {
		$admin  = Actor::user( $this->fx['users']['admin'] );
		$upload = wp_upload_bits( 'gm-' . $name . '-' . wp_generate_password( 6, false ) . '.pdf', null, "%PDF-1.4\n" . str_repeat( 'g', 64 ) );
		$id     = (int) wp_insert_attachment(
			array(
				'post_title'     => $name,
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		$media  = MediaLibrary::current()->register_attachment( $admin, $id );
		ProtectedStorage::protect_attachment( $id, $media );
		$db = Database::current();
		return array(
			'attachment' => $id,
			'media'      => $media,
			'uid'        => (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_media' ) . '` WHERE id = %d', $media ) ),
		);
	}

	/**
	 * Files below a folder plus the own options and media rows.
	 *
	 * @param string $base Folder.
	 * @return array<string, mixed>
	 */
	private function snapshot( string $base ): array {
		$files = array();
		$it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $it as $entry ) {
			$files[ (string) $entry ] = $entry->isFile() ? md5_file( (string) $entry ) : 'dir';
		}
		ksort( $files );
		global $wpdb;
		$db = Database::current();
		return array(
			'files'   => $files,
			'options' => $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'course\\_captain\\_%' ORDER BY option_name", ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'media'   => $db->rows( 'SELECT * FROM `' . $db->table( 'ccap_media' ) . '` ORDER BY id' ),
		);
	}

	/**
	 * Filter value `1`.
	 *
	 * @return string
	 */
	public static function one(): string {
		return '1';
	}

	/**
	 * Filter value `user`.
	 *
	 * @return string
	 */
	public static function user(): string {
		return 'user';
	}

	/**
	 * Number of grants.
	 *
	 * @return int
	 */
	private static function grant_count(): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_grant' ) . '`' );
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 */
	private function assertRejected( callable $call, string $code ): void {
		try {
			$call();
			self::fail( 'expected ' . $code );
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code );
		}
	}

	/**
	 * Fresh scratch directory outside the web root.
	 *
	 * @param string $name Name part.
	 * @return string
	 */
	private static function temp_dir( string $name ): string {
		$dir = get_temp_dir() . 'ccap-g3-' . $name . '-' . strtolower( wp_generate_password( 8, false, false ) );
		wp_mkdir_p( $dir );
		return untrailingslashit( $dir );
	}

	/**
	 * Remove a directory recursively (no symlink targets).
	 *
	 * @param string $dir Directory.
	 */
	private static function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) || is_link( $dir ) ) {
			return;
		}
		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				self::remove_dir( $path );
			} else {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
