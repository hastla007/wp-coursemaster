<?php
/**
 * R1-18: Golden-Master import cases on `cc-fixture-neutral-v1`
 * (R0-05 GM-064, GM-123, GM-127, GM-129, GM-130, GM-137; R0-10 §2, §3.1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\AuthoringService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\StructureFingerprint;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\ImportSources;
use CourseCaptain\Import\Rights;
use CourseCaptain\Import\RightsReleaseForm;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\Fixtures\NeutralFixture;

/**
 * Each test loads the fixture itself (idempotent, D-03).
 *
 * @group R1-18
 */
final class GoldenMasterImportTest extends SchemaTestCase {

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
	}

	/**
	 * Remove filters of the tests.
	 */
	public function tear_down(): void {
		remove_all_filters( ImportSources::LIMIT_FILTER );
		parent::tear_down();
	}

	/**
	 * GM-064: a card removed from the package (PKG-v2 without FC-A-08) is
	 * archived (`source_archived`), never deleted; the rollback to PKG-v1
	 * makes it active again with its old revision.
	 *
	 * @group GM-064
	 */
	public function test_GM_064_card_archived_and_restored(): void {
		$service = ImportService::current();
		$admin   = Actor::user( $this->fx['users']['admin'] );
		$db      = Database::current();
		$first   = (int) $db->scalar( 'SELECT id FROM `' . $db->table( 'ccap_import_batch' ) . "` WHERE status = 'published' ORDER BY id LIMIT 1" );
		self::assertSame( 13, (int) json_decode( (string) $service->get( $first )['manifest'], true )['counts']['flashcards'] );
		$decks    = NeutralFixture::flashcards( $this->fx );
		$card     = $decks['cards']['FC-A-08'];
		$state    = static fn(): array => (array) $db->row( $db->prepare( 'SELECT status, current_revision_id, (SELECT COUNT(*) FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE question_id = %d) AS revisions FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $card, $card ) );
		$before   = $state();
		$revision = (array) QuestionRepository::current()->revision( (int) $before['current_revision_id'] );
		self::assertSame( array( 'published', QuestionTypes::FLASHCARD, 'Rückseite FC-A-08', 'self_assessment' ), array( $before['status'], $revision['question_type'], $revision['settings']['back'], $revision['settings']['mode'] ) );
		self::assertCount( 8, QuestionPresenter::current()->inline_questions( $decks['FC-A'] ) );

		$b2 = $service->upload( $admin, NeutralFixture::package( 2, $this->dir . '/pkg-v2.zip' ) );
		$service->validate( $admin, $b2 );
		$plan = $service->preview( $admin, $b2 )['plan'];
		self::assertSame(
			array(
				'unchanged' => 12,
				'archive'   => 1,
			),
			$plan['flashcards']
		);
		$service->approve( $admin, $b2 );
		$service->run( $admin, $b2 );
		$after = $state();
		self::assertSame( 'archived', $after['status'], 'source_archived' );
		self::assertSame( array( $before['current_revision_id'], $before['revisions'] ), array( $after['current_revision_id'], $after['revisions'] ), 'archived, not deleted' );
		self::assertSame( $card, NeutralFixture::object( 'flashcard', 'cc-fx-fc-a-08' ), 'source map kept' );
		self::assertSame( 'archive', (string) $db->scalar( $db->prepare( 'SELECT action FROM `' . $db->table( 'ccap_import_item_log' ) . "` WHERE batch_id = %d AND entity = 'flashcards' AND source_id = 'cc-fx-fc-a-08'", $b2 ) ) );
		self::assertCount( 7, QuestionPresenter::current()->inline_questions( $decks['FC-A'] ), 'archived card leaves the deck' );

		try {
			$service->rollback( $admin, $first, '' );
			self::fail( 'rollback without reason' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_failed', $e->error_code );
		}
		$rb = $service->rollback( $admin, $first, 'FC-A-08 wird wieder gebraucht' );
		$service->validate( $admin, $rb );
		$plan = $service->preview( $admin, $rb )['plan'];
		self::assertSame(
			array(
				'unchanged' => 12,
				'update'    => 1,
			),
			$plan['flashcards']
		);
		$service->approve( $admin, $rb );
		$service->run( $admin, $rb );
		$restored = $state();
		self::assertSame( 'published', $restored['status'], 'active after rollback' );
		self::assertSame( array( $before['current_revision_id'], $before['revisions'] ), array( $restored['current_revision_id'], $restored['revisions'] ), 'old revision reactivated, no new one' );
		self::assertCount( 8, QuestionPresenter::current()->inline_questions( $decks['FC-A'] ) );
	}

	/**
	 * GM-123: rights override on Q-A3-010 (`unknown`) with reason and audit;
	 * without reason refused; the override survives a re-import.
	 *
	 * @group GM-123
	 */
	public function test_GM_123_rights_override(): void {
		$rights = Rights::current();
		$admin  = Actor::user( $this->fx['users']['admin'] );
		$db     = Database::current();
		$q      = (int) NeutralFixture::object( 'question', 'cc-fx-q-a3-010' );
		self::assertSame( 'unknown', $rights->effective( 'question', $q ), 'imported status' );
		self::assertSame( 'licensed', $rights->effective( 'question', NeutralFixture::question( 'A3-009' ) ), 'foreign own → licensed (E-62)' );
		self::assertSame( 'unknown', $rights->effective( 'flashcard', (int) NeutralFixture::object( 'flashcard', 'cc-fx-fc-a-07' ) ) );
		$audits = static fn(): int => (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'rights_override_set'" );

		foreach ( array( '', "  \n" ) as $reason ) {
			$this->rejected( fn() => $rights->override( $admin, 'question', $q, 'licensed', $reason ), 'ccap_validation_failed' );
		}
		$this->rejected( fn() => $rights->override( $admin, 'question', $q, 'stolen', 'Grund' ), 'ccap_validation_failed' );
		$this->rejected( fn() => $rights->override( Actor::user( $this->fx['users']['lea'] ), 'question', $q, 'licensed', 'Grund' ), 'ccap_forbidden' );
		$this->rejected( fn() => $rights->override( $admin, 'question', 999999, 'licensed', 'Grund' ), 'ccap_not_found' );
		self::assertSame( 'unknown', $rights->effective( 'question', $q ), 'nothing stored' );
		self::assertSame( 0, $audits() );

		self::assertSame(
			array(
				'before' => 'unknown',
				'after'  => 'licensed',
			),
			$rights->override( $admin, 'question', $q, 'licensed', 'Lizenzvertrag LV-7 geprüft' )
		);
		self::assertSame( 'licensed', $rights->effective( 'question', $q ) );
		$row = (array) $db->row( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'rights_override_set'" );
		self::assertSame( array( 'question', (string) $q, 'Lizenzvertrag LV-7 geprüft', (string) $this->fx['users']['admin'] ), array( $row['object_type'], $row['object_id'], $row['reason'], (string) \CourseCaptain\Event\AuditIdentity::user_of( $db, $row['actor_ref'] ) ) );
		self::assertSame(
			array(
				'field'  => 'rights_status',
				'before' => 'unknown',
				'after'  => 'licensed',
			),
			json_decode( (string) $row['context'], true )
		);
		self::assertSame( 'Lizenzvertrag LV-7 geprüft', (string) $db->scalar( $db->prepare( 'SELECT reason FROM `' . $db->table( 'ccap_override' ) . "` WHERE object_type = 'question' AND object_id = %d", $q ) ) );

		// Re-import of the same source: the override survives (R0-10 §3.7).
		$service = ImportService::current();
		$batch   = $service->upload( $admin, NeutralFixture::package( 2, $this->dir . '/pkg-v2.zip' ) );
		$service->validate( $admin, $batch );
		$service->preview( $admin, $batch );
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );
		self::assertSame( 'licensed', $rights->effective( 'question', $q ) );
		self::assertSame( 'unknown', (string) $db->scalar( $db->prepare( 'SELECT r.rights_status FROM `' . $db->table( 'ccap_question' ) . '` q JOIN `' . $db->table( 'ccap_question_revision' ) . '` r ON r.id = q.current_revision_id WHERE q.id = %d', $q ) ), 'package value unchanged' );
	}

	/**
	 * GM-127: bulk release with confirmation – `restricted` stays, archived
	 * items are left out, `unknown` becomes `licensed`; the confirmation names
	 * the count; nothing happens without it; the no-JS form returns to the page.
	 *
	 * @group GM-127
	 * @group MED-020
	 */
	public function test_GM_127_bulk_release(): void {
		$rights  = Rights::current();
		$service = ImportService::current();
		$admin   = Actor::user( $this->fx['users']['admin'] );
		$db      = Database::current();
		$q010    = (int) NeutralFixture::object( 'question', 'cc-fx-q-a3-010' );
		$fc07    = (int) NeutralFixture::object( 'flashcard', 'cc-fx-fc-a-07' );
		$fc08    = (int) NeutralFixture::object( 'flashcard', 'cc-fx-fc-a-08' );
		$q024    = NeutralFixture::question( 'A1-024' );
		// 1× restricted (Q-A1-024, §2.3), 1× archived with unclear rights (FC-A-08 after PKG-v2).
		$rights->override( $admin, 'question', $q024, 'restricted', 'Fixture GM-025' );
		$rights->override( $admin, 'flashcard', $fc08, 'unknown', 'Fixture GM-127' );
		$batch = $service->upload( $admin, NeutralFixture::package( 2, $this->dir . '/pkg-v2.zip' ) );
		$service->validate( $admin, $batch );
		$service->preview( $admin, $batch );
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );
		$status  = static fn(): array => array(
			'Q-A3-010' => $rights->effective( 'question', $q010 ),
			'FC-A-07'  => $rights->effective( 'flashcard', $fc07 ),
			'FC-A-08'  => $rights->effective( 'flashcard', $fc08 ),
			'Q-A1-024' => $rights->effective( 'question', $q024 ),
		);
		$initial = $status();
		self::assertSame(
			array(
				'Q-A3-010' => 'unknown',
				'FC-A-07'  => 'unknown',
				'FC-A-08'  => 'unknown',
				'Q-A1-024' => 'restricted',
			),
			$initial
		);

		$preview = $rights->release_preview( $admin, $batch );
		self::assertSame( 2, $preview['count'] );
		self::assertSame( array( 'cc-fx-fc-a-07', 'cc-fx-q-a3-010' ), array_column( $preview['items'], 'source_id' ) );
		self::assertSame( 1, $preview['kept']['restricted'] );
		self::assertSame( 1, $preview['kept']['archived'] );

		// Without (or with a stale) confirmation nothing changes.
		$this->rejected( fn() => $rights->release( $admin, $batch, 'Rechte geklärt', null ), 'ccap_confirmation_required' );
		$this->rejected( fn() => $rights->release( $admin, $batch, 'Rechte geklärt', 3 ), 'ccap_confirmation_required' );
		$this->rejected( fn() => $rights->release( $admin, $batch, ' ', 2 ), 'ccap_validation_failed' );
		$this->rejected( fn() => $rights->release_preview( Actor::user( $this->fx['users']['lea'] ), $batch ), 'ccap_forbidden' );
		self::assertSame( $initial, $status() );

		// No-JS path over admin-post: step 1 renders the confirmation with the count.
		$return = admin_url( 'admin.php?page=course-captain-import&batch=' . $batch );
		$step   = RightsReleaseForm::respond(
			$admin,
			array(
				'batch'        => (string) $batch,
				'reason'       => 'Rechte mit dem Herausgeber geklärt',
				'_ccap_return' => $return,
			)
		);
		self::assertSame( 'page', $step['type'] );
		self::assertStringContainsString( 'data-ccap-count="2"', $step['body'] );
		self::assertStringContainsString( '2 imported items with unclear rights', $step['body'] );
		self::assertStringContainsString( 'Not changed: 1 restricted, 1 archived.', $step['body'] );
		self::assertStringContainsString( 'name="count" value="2"', $step['body'] );
		self::assertStringContainsString( 'name="_ccap_confirm" value="1"', $step['body'] );
		self::assertStringContainsString( 'action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"', $step['body'] );
		self::assertSame( $initial, $status(), 'the question alone changes nothing' );
		$missing = RightsReleaseForm::respond(
			$admin,
			array(
				'batch'        => (string) $batch,
				'_ccap_return' => $return,
			)
		);
		self::assertSame( add_query_arg( 'ccap_rights_error', 'ccap_validation_failed', $return ), $missing['body'], 'error returns to the page' );

		// Step 2 with the confirmed count releases and returns to the page.
		$done = RightsReleaseForm::respond(
			$admin,
			array(
				'batch'         => (string) $batch,
				'reason'        => 'Rechte mit dem Herausgeber geklärt',
				'_ccap_return'  => $return,
				'_ccap_confirm' => '1',
				'count'         => '2',
			)
		);
		self::assertSame(
			array(
				'type' => 'redirect',
				'body' => add_query_arg( 'ccap_rights_released', 2, $return ),
			),
			$done
		);
		self::assertSame(
			array(
				'Q-A3-010' => 'licensed',
				'FC-A-07'  => 'licensed',
				'FC-A-08'  => 'unknown',
				'Q-A1-024' => 'restricted',
			),
			$status()
		);
		$audit = (array) $db->row( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'rights_bulk_release'" );
		self::assertSame( array( 'import_batch', (string) $batch, 'Rechte mit dem Herausgeber geklärt' ), array( $audit['object_type'], $audit['object_id'], $audit['reason'] ) );
		self::assertSame( 2, json_decode( (string) $audit['context'], true )['count'] );
		self::assertSame( 0, $rights->release_preview( $admin, $batch )['count'], 'nothing left to release' );
	}

	/**
	 * GM-129: upload limit = smallest value; system files of PKG-SYS are
	 * ignored and never unpacked; a package taken over from the drop folder is
	 * deleted there, a rejected one stays.
	 *
	 * @group GM-129
	 */
	public function test_GM_129_upload_limit_and_drop_folder(): void {
		$mb = 1048576;
		self::assertSame(
			$mb,
			ImportSources::upload_limit(
				array(
					'upload_max_filesize' => '1M',
					'post_max_size'       => '8M',
					'wp'                  => 4 * $mb,
					'package'             => 200 * $mb,
				)
			)
		);
		self::assertSame(
			2 * $mb,
			ImportSources::upload_limit(
				array(
					'upload_max_filesize' => '64M',
					'post_max_size'       => '2M',
					'wp'                  => 4 * $mb,
					'package'             => 200 * $mb,
				)
			)
		);
		self::assertSame(
			3 * $mb,
			ImportSources::upload_limit(
				array(
					'upload_max_filesize' => '64M',
					'post_max_size'       => '0',
					'wp'                  => 3 * $mb,
					'package'             => 200 * $mb,
				)
			),
			'0 = unlimited'
		);
		self::assertSame(
			5 * $mb,
			ImportSources::upload_limit(
				array(
					'upload_max_filesize' => '64M',
					'post_max_size'       => '64M',
					'wp'                  => 64 * $mb,
					'package'             => 5 * $mb,
				)
			)
		);
		self::assertLessThanOrEqual( wp_max_upload_size(), ImportSources::upload_limit() );
		self::assertLessThanOrEqual( ImportSources::PACKAGE_LIMIT, ImportSources::upload_limit() );

		$admin   = Actor::user( $this->fx['users']['admin'] );
		$db      = Database::current();
		$batches = static fn(): string => (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_import_batch' ) . '`' );
		$v1      = NeutralFixture::package( 1, $this->dir . '/pkg-v1.zip' );
		add_filter( ImportSources::LIMIT_FILTER, static fn(): int => 1000 );
		self::assertSame( 1000, ImportSources::upload_limit() );
		$count = $batches();
		$e     = $this->rejected( fn() => ImportSources::current()->from_upload( $admin, $v1 ), 'ccap_package_too_large' );
		self::assertSame( 413, $e->status );
		self::assertSame( 1000, $e->data['limit'] );
		self::assertSame( $count, $batches(), 'nothing stored' );
		remove_all_filters( ImportSources::LIMIT_FILTER );
		self::assertIsInt( ImportSources::current()->from_upload( $admin, $v1 ) );

		// PKG-SYS: PKG-v1 plus .DS_Store, Thumbs.db and __MACOSX/ (not in the manifest).
		$drop = ImportSources::drop_dir();
		copy( $v1, $drop . '/pkg-sys.zip' );
		$zip = new \ZipArchive();
		self::assertTrue( $zip->open( $drop . '/pkg-sys.zip' ) );
		$zip->addFromString( '.DS_Store', 'junk' );
		$zip->addFromString( 'data/questions/.DS_Store', 'junk' );
		$zip->addFromString( 'Thumbs.db', 'junk' );
		$zip->addFromString( '__MACOSX/._manifest.json', 'junk' );
		$zip->close();
		$sources = ImportSources::current();
		$taken   = $sources->from_drop( $admin, 'pkg-sys.zip' );
		self::assertSame( 'validated', $taken['status'] );
		self::assertFileDoesNotExist( $drop . '/pkg-sys.zip', 'taken over → deleted from the drop folder' );
		$row = (array) ImportService::current()->get( $taken['batch'] );
		self::assertFileExists( (string) $row['storage_path'], 'private batch copy' );
		self::assertNull( $row['error'] );
		$plan = ImportService::current()->preview( $admin, $taken['batch'] )['plan'];
		self::assertSame( array( 'unchanged' => 71 ), $plan['questions'], 'system files are no records' );
		self::assertSame( array(), (array) glob( \CourseCaptain\Access\ProtectedStorage::dir( 'packages' ) . '/{,*/}{.DS_Store,Thumbs.db,__MACOSX}', GLOB_BRACE ), 'nothing unpacked' );

		// PKG-BAD-FOREIGN in the drop folder: rejected and kept.
		copy( $v1, $drop . '/pkg-bad.zip' );
		self::assertTrue( $zip->open( $drop . '/pkg-bad.zip' ) );
		$zip->addFromString( 'evil.php', '<?php echo 1;' );
		$zip->close();
		$bad = $sources->from_drop( $admin, 'pkg-bad.zip' );
		self::assertSame( 'failed', $bad['status'] );
		self::assertFileExists( $drop . '/pkg-bad.zip', 'rejected package stays' );
		unlink( $drop . '/pkg-bad.zip' );

		// Only base names directly in the folder, no links, only importers.
		$this->rejected( fn() => $sources->from_drop( $admin, '../pkg-v1.zip' ), 'ccap_validation_failed' );
		$this->rejected( fn() => $sources->from_drop( $admin, '.hidden.zip' ), 'ccap_validation_failed' );
		$this->rejected( fn() => $sources->from_drop( $admin, 'missing.zip' ), 'ccap_not_found' );
		symlink( $v1, $drop . '/link.zip' );
		$this->rejected( fn() => $sources->from_drop( $admin, 'link.zip' ), 'ccap_not_found' );
		unlink( $drop . '/link.zip' );
		$this->rejected( fn() => $sources->from_drop( Actor::user( $this->fx['users']['lea'] ), 'pkg-sys.zip' ), 'ccap_forbidden' );
	}

	/**
	 * GM-130 / SEC-029: link import over an HTTP stub – https and port 443
	 * only, at most 3 redirects, internal targets refused (also after a
	 * redirect and on DNS rebinding: the connection goes to the checked
	 * address), matching SHA-256 taken over, wrong one discarded, no
	 * temporary files left.
	 *
	 * @group GM-130
	 */
	public function test_GM_130_link_import(): void {
		$admin = Actor::user( $this->fx['users']['admin'] );
		$pkg   = NeutralFixture::package( 1, $this->dir . '/pkg-v1.zip' );
		$sha   = (string) hash_file( 'sha256', $pkg );
		$dns   = array(
			'pkg.example.test'      => array( '93.184.216.34' ),
			'mirror.example.test'   => array( '93.184.216.35', '2606:2800:220:1:248:1893:25c8:1946' ),
			'intranet.example.test' => array( '10.0.0.5' ),
			'mixed.example.test'    => array( '93.184.216.36', '192.168.1.10' ),
		);
		$asked = array();
		$sent  = array();
		// DNS rebinding: first answer public, every later answer internal.
		$resolve   = static function ( string $host ) use ( $dns, &$asked ): array {
			$asked[] = $host;
			if ( 'rebind.example.test' === $host ) {
				return 1 === count( array_keys( $asked, $host, true ) ) ? array( '93.184.216.40' ) : array( '127.0.0.1' );
			}
			return $dns[ $host ] ?? array();
		};
		$transport = static function ( string $url, string $ip, string $file, int $limit ) use ( $pkg, &$sent ): array {
			unset( $limit );
			$sent[] = array( $url, $ip );
			if ( 1 === preg_match( '#/hop/(\d+)/(\d+)$#', $url, $m ) ) {
				// /hop/<n>/<total>: redirect until n = total.
				return (int) $m[1] < (int) $m[2] ? array(
					'code'     => 302,
					'location' => '/hop/' . ( (int) $m[1] + 1 ) . '/' . $m[2],
				) : self::serve( $pkg, $file );
			}
			if ( str_ends_with( $url, '/to-internal' ) ) {
				return array(
					'code'     => 301,
					'location' => 'https://intranet.example.test/pkg.zip',
				);
			}
			if ( str_ends_with( $url, '/to-http' ) ) {
				return array(
					'code'     => 302,
					'location' => 'http://pkg.example.test/pkg.zip',
				);
			}
			if ( str_ends_with( $url, '/missing' ) ) {
				return array(
					'code'     => 404,
					'location' => null,
				);
			}
			return self::serve( $pkg, $file );
		};
		$sources   = new ImportSources( ImportService::current(), $resolve, $transport );
		$download  = \CourseCaptain\Access\ProtectedStorage::dir( 'packages' ) . '/download';
		$leftover  = static fn(): array => is_dir( $download ) ? array_values( array_diff( (array) scandir( $download ), array( '.', '..' ) ) ) : array();
		$db        = Database::current();
		$batches   = static fn(): string => (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_import_batch' ) . '`' );

		// Matching checksum: taken over, pinned to the checked address.
		$batch = $sources->from_url( $admin, 'https://pkg.example.test/pkg-v1.zip', strtoupper( $sha ) );
		self::assertSame( array( 'https://pkg.example.test/pkg-v1.zip', '93.184.216.34' ), end( $sent ) );
		self::assertSame( 'validated', ImportService::current()->validate( $admin, $batch )['status'] );
		$stats = json_decode( (string) ImportService::current()->get( $batch )['stats'], true );
		self::assertSame( array( 'link', 'pkg.example.test', $sha ), array( $stats['source'], $stats['source_host'], $stats['sha256'] ) );
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'import_link_downloaded'" ) );
		self::assertSame( array(), $leftover(), 'no temporary files' );

		// Three redirects are fine (each hop checked and pinned), the fourth aborts.
		$sent = array();
		self::assertIsInt( $sources->from_url( $admin, 'https://mirror.example.test/hop/0/3' ) );
		self::assertCount( 4, $sent );
		self::assertSame( array( '93.184.216.35' ), array_values( array_unique( array_column( $sent, 1 ) ) ) );
		$count = $batches();
		$this->rejected( fn() => $sources->from_url( $admin, 'https://mirror.example.test/hop/0/4' ), 'ccap_link_redirects' );

		// Wrong checksum: discarded.
		$this->rejected( fn() => $sources->from_url( $admin, 'https://pkg.example.test/pkg-v1.zip', str_repeat( '0', 64 ) ), 'ccap_link_checksum' );
		$this->rejected( fn() => $sources->from_url( $admin, 'https://pkg.example.test/pkg-v1.zip', 'abc' ), 'ccap_validation_failed' );

		// SEC-029: scheme, port, internal targets – refused before any connection.
		$sent = array();
		foreach ( array(
			'http://pkg.example.test/pkg.zip'           => 'ccap_link_invalid',
			'https://pkg.example.test:8080/pkg.zip'     => 'ccap_link_invalid',
			'https://user:pw@pkg.example.test/pkg.zip'  => 'ccap_link_invalid',
			'ftp://pkg.example.test/pkg.zip'            => 'ccap_link_invalid',
			'https://127.0.0.1/pkg.zip'                 => 'ccap_link_blocked',
			'https://[::1]/pkg.zip'                     => 'ccap_link_blocked',
			'https://10.0.0.1/pkg.zip'                  => 'ccap_link_blocked',
			'https://169.254.169.254/latest/meta-data/' => 'ccap_link_blocked',
			'https://[::ffff:127.0.0.1]/pkg.zip'        => 'ccap_link_blocked',
			'https://2130706433/pkg.zip'                => 'ccap_link_blocked',
			'https://0x7f.0.0.1/pkg.zip'                => 'ccap_link_blocked',
			'https://localhost./pkg.zip'                => 'ccap_link_blocked',
			'https://intranet.example.test/pkg.zip'     => 'ccap_link_blocked',
			'https://mixed.example.test/pkg.zip'        => 'ccap_link_blocked',
			'https://100.64.0.1/pkg.zip'                => 'ccap_link_blocked',
			'https://[fe80::1]/pkg.zip'                 => 'ccap_link_blocked',
		) as $url => $code ) {
			$this->rejected( fn() => $sources->from_url( $admin, $url ), $code );
		}
		self::assertSame( array(), $sent, 'no connection to refused targets' );

		// Redirect into the intranet or to http: refused at the hop.
		$this->rejected( fn() => $sources->from_url( $admin, 'https://pkg.example.test/to-internal' ), 'ccap_link_blocked' );
		$this->rejected( fn() => $sources->from_url( $admin, 'https://pkg.example.test/to-http' ), 'ccap_link_invalid' );
		self::assertNotContains( 'https://intranet.example.test/pkg.zip', array_column( $sent, 0 ) );
		$this->rejected( fn() => $sources->from_url( $admin, 'https://pkg.example.test/missing' ), 'ccap_link_failed' );

		// DNS rebinding: one lookup per hop, the transport gets the checked address.
		$sent = array();
		self::assertIsInt( $sources->from_url( $admin, 'https://rebind.example.test/pkg.zip' ) );
		self::assertSame( array( array( 'https://rebind.example.test/pkg.zip', '93.184.216.40' ) ), $sent );
		self::assertSame( 1, count( array_keys( $asked, 'rebind.example.test', true ) ) );

		self::assertSame( (int) $count + 1, (int) $batches(), 'only the rebinding case added a batch' );
		self::assertSame( array(), $leftover(), 'no temporary files after failures' );
		$this->rejected( fn() => $sources->from_url( Actor::user( $this->fx['users']['lea'] ), 'https://pkg.example.test/pkg-v1.zip' ), 'ccap_forbidden' );
		self::assertFalse( ImportSources::public_ip( '198.18.0.1' ) );
		self::assertTrue( ImportSources::public_ip( '93.184.216.34' ) );
		self::assertTrue( ImportSources::public_ip( '2606:2800:220:1:248:1893:25c8:1946' ) );
	}

	/**
	 * GM-137: C-A built by hand in the editor has the structure fingerprint
	 * of the imported C-A (identity-free; the revision `structure_hash`
	 * contains IDs and differs by design).
	 *
	 * @group GM-137
	 */
	public function test_GM_137_hand_built_course(): void {
		$admin   = Actor::user( $this->fx['users']['admin'] );
		$records = NeutralFixture::records( 1 );
		$by      = static fn( string $entity ): array => array_column( $records[ $entity ], null, 'source_id' );
		$spec    = $by( 'courses' )['cc-fx-course-a'];
		$repo    = QuestionRepository::current();
		$course  = (int) wp_insert_post(
			array(
				'post_type'    => 'ccap_course',
				'post_name'    => 'cc-a-hand',
				'post_title'   => $spec['title'],
				'post_excerpt' => $spec['summary'],
				'post_content' => $spec['description_html'],
				'post_status'  => 'draft',
				'post_author'  => $this->fx['users']['admin'],
			)
		);
		foreach ( $spec['modules'] as $link ) {
			$m      = $by( 'modules' )[ $link['module'] ];
			$module = (int) wp_insert_post(
				array(
					'post_type'    => 'ccap_module',
					'post_title'   => $m['title'],
					'post_excerpt' => $m['summary'],
					'post_status'  => 'publish',
				)
			);
			foreach ( $m['lessons'] as $l ) {
				$spec_lesson = $by( 'lessons' )[ $l['lesson'] ];
				$lesson      = (int) wp_insert_post(
					array(
						'post_type'    => 'ccap_lesson',
						'post_title'   => $spec_lesson['title'],
						'post_content' => $spec_lesson['body_html'],
						'post_parent'  => $module,
						'menu_order'   => $l['sort'],
						// L-A-10 is archived in the package (`deprecated`, R0-05 §2.2).
						'post_status'  => 'deprecated' === ( $spec_lesson['editorial_status'] ?? '' ) ? 'ccap_archived' : 'publish',
					)
				);
				AuthoringService::current()->define_activity( $admin, $lesson, $spec_lesson['activity_type'], $spec_lesson['settings'] );
			}
			foreach ( $m['question_pool'] as $p ) {
				$q       = $by( 'questions' )[ $p['question'] ];
				$type    = $q['revision']['question_type'];
				$answers = array();
				foreach ( $q['answers'] as $i => $a ) {
					$answers[] = array(
						'key'     => QuestionTypes::OPEN_TEXT === $type ? 'MODEL' : chr( 65 + $i ),
						'text'    => $a['text_html'],
						'correct' => $a['correct'],
					);
				}
				$id = $repo->create(
					$admin,
					array(
						'question_type' => $type,
						'question_text' => $q['revision']['text_html'],
						'settings'      => $q['revision']['settings'],
						'answers'       => $answers,
						'tags'          => $q['tags'],
					)
				);
				$repo->publish( $admin, $id );
				$repo->add_to_pool( $admin, $module, $id, $p['sort'] );
			}
			CourseStructure::current()->assign_module( $admin, $course, $module, $link['sort'], $link['required'] );
		}
		PolicyService::flush();
		CoursePublisher::current()->publish( $admin, $course );

		$imported = $this->fx['courses']['C-A'];
		self::assertSame( 'publish', get_post_status( $course ) );
		self::assertSame( StructureFingerprint::structure( $imported ), StructureFingerprint::structure( $course ) );
		self::assertSame( StructureFingerprint::hash( $imported ), StructureFingerprint::hash( $course ) );
		self::assertNotSame( CoursePublisher::current()->current_revision( $imported )['structure_hash'], CoursePublisher::current()->current_revision( $course )['structure_hash'], 'revision hash contains IDs' );
		self::assertSame( 6, count( StructureFingerprint::structure( $course )['modules'] ) );

		// A single changed answer text changes the fingerprint.
		$first                       = $repo->pool( (int) CourseStructure::current()->modules( $course )[0]['module_id'] )[0];
		$data                        = (array) $repo->revision( (int) Database::current()->scalar( Database::current()->prepare( 'SELECT current_revision_id FROM `' . Database::current()->table( 'ccap_question' ) . '` WHERE id = %d', $first ) ) );
		$data['answers'][1]['text'] .= ' (geändert)';
		$repo->publish( $admin, $first, $repo->save( $admin, $first, $data, 'substantive' ) );
		self::assertNotSame( StructureFingerprint::hash( $imported ), StructureFingerprint::hash( $course ) );
	}

	/**
	 * Serve the package through the stub.
	 *
	 * @param string $pkg  Package.
	 * @param string $file Target.
	 * @return array{code: int, location: null}
	 */
	private static function serve( string $pkg, string $file ): array {
		copy( $pkg, $file );
		return array(
			'code'     => 200,
			'location' => null,
		);
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
}
