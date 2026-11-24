<?php
/**
 * Import state machine, dry run, apply, activation, rollback (R0-10 §1.4, §3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Activity\ActivityRegistry;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\PostTypes;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Lease;
use CourseCaptain\Storage\MigrationBudget;

defined( 'ABSPATH' ) || exit;

/**
 * `uploaded → validated|failed → previewed → approved → importing →
 * activating → published (→ superseded)`, `aborted`, `discarded`.
 *
 * - Validation V1–V5 fails the whole batch; `requires` must be available.
 * - The dry run writes nothing but the plan and object issues (V6: schema,
 *   duplicate `source_id`, hash, references within the package or via the
 *   source map, unknown types). Object errors mark the object and its
 *   dependents; the batch fails only above max(100, 2 %) errors (E-180).
 * - Actions: create/update/unchanged (same hash)/conflict (locally
 *   modified, kept)/archive (missing from a full package; never deleted).
 * - Question revisions and course revisions switch only in `activating`;
 *   learners see the old state until then. Commit and activation run in
 *   budgeted, resumable chunks ({@see ImportRunner}, R1-41).
 * - Rollback creates a new import from the kept package copy that runs the
 *   normal path; old hashes become active again, later content is archived.
 */
final class ImportService {

	/**
	 * Entities in scan and commit order (R0-10 §3.5): media first (other
	 * records refer to them), blueprints last (they refer to courses,
	 * modules and questions).
	 */
	public const ENTITY_TYPES = array(
		'media'      => 'media',
		'questions'  => 'question',
		'flashcards' => 'flashcard',
		'lessons'    => 'lesson',
		'modules'    => 'module',
		'courses'    => 'course',
		'blueprints' => 'blueprint',
	);

	/**
	 * Entities stored as posts.
	 */
	public const POST_TYPES = array( 'course', 'module', 'lesson' );

	/**
	 * Entities stored as questions (`ccap_question`); flashcards use the
	 * question type `flashcard` (R0-10 §1.3, E-21).
	 */
	public const QUESTION_TYPES = array( 'question', 'flashcard' );

	/**
	 * Flashcard modes of the package format (R0-10 §1.3).
	 */
	public const CARD_MODES = array( 'self_assessment', 'streak_3' );

	/**
	 * Editorial states that are imported but not published (R0-10 §1.3).
	 */
	public const UNPUBLISHED = array( 'draft', 'review', 'approved' );

	/**
	 * Running import (local edits are not marked as conflicts).
	 *
	 * @var bool
	 */
	private static bool $importing = false;

	/**
	 * Revision holding the imported content per question (known hash → reactivated).
	 *
	 * @var array<int, int>
	 */
	private array $saved = array();

	/**
	 * Manifests per package ID (rights of the current run).
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $manifests = array();

	/**
	 * Package copies per batch (media files of the current run).
	 *
	 * @var array<int, string>
	 */
	private array $packages = array();

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
	 * Flag a running import (local edits are not marked as conflicts).
	 *
	 * @internal Runner use.
	 * @param bool $running Running.
	 * @return void
	 */
	public static function importing( bool $running ): void {
		self::$importing = $running;
	}

	/**
	 * Mark local edits of imported posts (conflict policy §3.7).
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'save_post',
			static function ( $post_id, $post ): void {
				if ( self::$importing || ! $post instanceof \WP_Post || wp_is_post_revision( (int) $post_id ) ) {
					return;
				}
				$type = array_search(
					$post->post_type,
					array(
						'course' => Relations::COURSE_TYPE,
						'module' => Relations::MODULE_TYPE,
						'lesson' => Relations::LESSON_TYPE,
					),
					true
				);
				if ( false !== $type ) {
					$db = Database::current();
					$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_source_map' ) . '` SET locally_modified = 1 WHERE object_type = %s AND object_id = %d', $type, (int) $post_id ) );
				}
			},
			20,
			2
		);
		ImportRunner::register();
		ImportRoutes::register();
		ImportCommand::register();
		RightsReleaseForm::register();
	}

	/**
	 * Upload: keep a private copy of the package.
	 *
	 * @param Actor  $actor Importer (`ccap_import_content`).
	 * @param string $file  ZIP file.
	 * @param array  $stats Extra stats (rollback reference).
	 * @phpstan-param array<string, mixed> $stats
	 * @return int Batch ID.
	 * @throws CourseException When not allowed or the copy fails.
	 */
	public function upload( Actor $actor, string $file, array $stats = array() ): int {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_import_content' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to import.', 403 );
		}
		$copy = ProtectedStorage::dir( 'packages' ) . '/batch-' . wp_generate_uuid4() . '.zip';
		if ( ! is_file( $file ) || ! copy( $file, $copy ) ) {
			throw new CourseException( 'ccap_storage_unavailable', 'Package could not be stored.', 500 );
		}
		return $this->db->insert(
			'ccap_import_batch',
			array(
				'package_id'     => 'pending',
				'schema_version' => '',
				'status'         => 'uploaded',
				'manifest'       => '{}',
				'stats'          => (string) wp_json_encode( (object) $stats ),
				'storage_path'   => $copy,
				'created_by'     => $actor->user_id,
			),
			array( 'created_at' )
		);
	}

	/**
	 * V1–V5 and `requires`. Media files rejected by V4 do not fail the
	 * package: they are kept in `stats.rejected_media` and become object
	 * errors of their media records in the dry run (R0-38 §5.3).
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @return array{status: string, errors: list<array{rule: string, path: string}>, rejected: array<string, string>}
	 */
	public function validate( Actor $actor, int $batch ): array {
		$row      = $this->authorize( $actor, $batch, 'validate', array( 'uploaded' ) );
		$result   = PackageValidator::validate( (string) $row['storage_path'] );
		$errors   = array();
		$rejected = array();
		foreach ( $result['errors'] as $error ) {
			// V4 is an object error of the medium (R0-38 §2.3 IMP-V4.0x, class O); V1–V3, V5 fail the package.
			if ( str_starts_with( $error['rule'], 'V4_' ) && str_starts_with( $error['path'], 'media/' ) ) {
				$rejected[ $error['path'] ] = $error['rule'];
			} else {
				$errors[] = $error;
			}
		}
		$m = $result['manifest'] ?? array();
		foreach ( (array) ( $m['requires']['question_types'] ?? array() ) as $type ) {
			if ( ! in_array( $type, QuestionTypes::ALL, true ) ) {
				$errors[] = array(
					'rule' => 'V6_requires_question_type',
					'path' => (string) $type,
				);
			}
		}
		foreach ( (array) ( $m['requires']['activity_providers'] ?? array() ) as $type ) {
			if ( null === ActivityRegistry::instance()->get( (string) $type ) ) {
				$errors[] = array(
					'rule' => 'V6_requires_activity_provider',
					'path' => (string) $type,
				);
			}
		}
		$status = array() === $errors ? 'validated' : 'failed';
		$this->move(
			$batch,
			array( 'uploaded' ),
			$status,
			array(
				'package_id'      => substr( (string) ( $m['package_id'] ?? 'invalid' ), 0, 64 ),
				'schema_version'  => substr( (string) ( $m['schema_version'] ?? '' ), 0, 16 ),
				'source_revision' => isset( $m['source']['revision'] ) ? substr( (string) $m['source']['revision'], 0, 64 ) : null,
				'manifest'        => (string) wp_json_encode( (object) $m ),
				'error'           => array() === $errors ? null : (string) wp_json_encode( $errors ),
				'stats'           => (string) wp_json_encode( array_merge( (array) json_decode( (string) $row['stats'], true ), array( 'rejected_media' => (object) $rejected ) ) ),
			)
		);
		return array(
			'status'   => $status,
			'errors'   => $errors,
			'rejected' => $rejected,
		);
	}

	/**
	 * Dry run: plan, object issues and statistics; nothing written to content.
	 *
	 * The same work as {@see preview_step()} without a time budget, so a dry
	 * run in partial steps gives the same result as one at once (GM-125).
	 *
	 * @param Actor $actor   Importer.
	 * @param int   $batch   Batch.
	 * @param bool  $partial Partial package (no archiving of missing records).
	 * @return array{status: string, plan: array<string, array<string, int>>, items: list<array{entity: string, source_id: string, action: string, rule: string|null}>, stats: array<string, mixed>}
	 */
	public function preview( Actor $actor, int $batch, bool $partial = false ): array {
		$out = $this->preview_step( $actor, $batch, null, $partial );
		/**
		 * Planned actions per entity.
		 *
		 * @var array<string, array<string, int>> $plan
		 */
		$plan = (array) ( $out['plan'] ?? array() );
		/**
		 * Items of the dry run.
		 *
		 * @var list<array{entity: string, source_id: string, action: string, rule: string|null}> $items
		 */
		$items = array_values( (array) ( $out['items'] ?? array() ) );
		return array(
			'status' => (string) $out['status'],
			'plan'   => $plan,
			'items'  => $items,
			'stats'  => (array) ( $out['stats'] ?? array() ),
		);
	}

	/**
	 * One budgeted step of the dry run (R0-10 §3.3 time slices, R0-38 §4.1).
	 *
	 * The package is streamed in units (one data file, or the archive plan
	 * of one entity); a step works on units until its budget is used, at
	 * least one. Between steps the state (seen and failed IDs, counters,
	 * items) lies in a private file and `stats.preview_progress` points to
	 * it; the batch stays `validated` (a new dry run on a `previewed` batch
	 * resets it to `validated`, so it cannot be approved meanwhile). Memory
	 * depends on the largest data file and the ID sets (R0-38 §4.2). The
	 * last step stores the plan and ends in `previewed` or `failed`.
	 *
	 * @param Actor      $actor   Importer.
	 * @param int        $batch   Batch.
	 * @param float|null $seconds Budget; null = until done.
	 * @param bool       $partial Partial package (used when the dry run starts).
	 * @return array<string, mixed> `status`, `done`, `units_done`, `units_total`; when done also `plan`, `items`, `stats`.
	 * @throws CourseException 409 while another step of this dry run runs.
	 */
	public function preview_step( Actor $actor, int $batch, ?float $seconds, bool $partial = false ): array {
		$row   = $this->authorize( $actor, $batch, 'preview', array( 'validated', 'previewed' ) );
		$lease = Lease::acquire( $this->db, 'import-preview:' . $batch, ImportRunner::LEASE_TTL, false );
		if ( null === $lease ) {
			throw new CourseException( 'ccap_import_busy', 'The dry run of this import is running.', 409 );
		}
		try {
			return $this->preview_units( $batch, (array) $this->get( $batch ), $seconds, $partial );
		} finally {
			$lease->release();
		}
	}

	/**
	 * Dry-run units of one step (under the preview lease).
	 *
	 * @param int                  $batch   Batch.
	 * @param array<string, mixed> $row     Batch row (re-read after the lease).
	 * @param float|null           $seconds Budget.
	 * @param bool                 $partial Partial package.
	 * @return array<string, mixed>
	 */
	private function preview_units( int $batch, array $row, ?float $seconds, bool $partial ): array {
		$manifest = (array) json_decode( (string) $row['manifest'], true );
		$stats    = (array) json_decode( (string) $row['stats'], true );
		$progress = is_array( $stats['preview_progress'] ?? null ) ? $stats['preview_progress'] : null;
		$file     = null === $progress ? '' : ProtectedStorage::dir( 'packages' ) . '/' . basename( (string) ( $progress['state'] ?? '' ) );
		$state    = '' !== $file && is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Private state file.
		if ( ! is_array( $state ) ) {
			// Fresh dry run: previous findings go, a previewed batch is reset (R0-10 §3.3).
			if ( 'previewed' === (string) $row['status'] ) {
				$this->move( $batch, array( 'previewed' ), 'validated', array() );
			}
			$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_import_issue' ) . '` WHERE batch_id = %d', $batch ) );
			$state = self::scan_state( $partial ) + array(
				'summary' => array(),
				'items'   => array(),
				'rules'   => array(),
				'errors'  => 0,
				'elapsed' => 0.0,
			);
			$file  = ProtectedStorage::dir( 'packages' ) . '/preview-' . $batch . '-' . wp_generate_password( 12, false, false ) . '.json';
		}
		$partial = ! empty( $state['partial'] );
		$units   = $this->scan_units( $row, $manifest );
		$context = $this->scan_context( $row, $manifest );
		$budget  = new MigrationBudget( $seconds );
		$started = microtime( true );
		$buffer  = array();
		$emit    = function ( array $item ) use ( $batch, &$state, &$buffer ): void {
			$state['summary'][ $item['entity'] ][ $item['action'] ] = ( $state['summary'][ $item['entity'] ][ $item['action'] ] ?? 0 ) + 1;
			$state['items'][]                                       = array(
				'entity'    => $item['entity'],
				'source_id' => $item['source_id'],
				'action'    => $item['action'],
				'rule'      => $item['rule'],
			);
			if ( null === $item['rule'] ) {
				return;
			}
			++$state['errors'];
			$code                    = ImportReport::code( $item['rule'] );
			$state['rules'][ $code ] = ( $state['rules'][ $code ] ?? 0 ) + 1;
			if ( $state['errors'] <= ImportReport::MAX_ISSUES ) {
				$buffer[] = ImportReport::issue( $this->db, $batch, 'preview', $item['entity'], $item['source_id'], $item['rule'], 'error', 'O', 'rejected', array(), $item['file'] );
			}
			if ( count( $buffer ) >= ImportReport::INSERT_BLOCK ) {
				ImportReport::insert_many( $this->db, 'ccap_import_issue', $buffer );
				$buffer = array();
			}
		};
		$zip     = new \ZipArchive();
		$opened  = true === $zip->open( (string) $row['storage_path'], \ZipArchive::RDONLY );
		$total   = count( $units );
		do {
			$unit = $units[ (int) $state['unit'] ] ?? null;
			if ( null !== $unit ) {
				$this->scan_unit( $opened ? $zip : null, $manifest, $partial, $unit, $state, $context, $emit );
				++$state['unit'];
			}
		} while ( (int) $state['unit'] < $total && ! $budget->expired() );
		if ( $opened ) {
			$zip->close();
		}
		ImportReport::insert_many( $this->db, 'ccap_import_issue', $buffer );
		$state['elapsed'] = (float) $state['elapsed'] + ( microtime( true ) - $started );
		if ( (int) $state['unit'] < $total ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Private state file in the packages folder.
			file_put_contents( $file, (string) wp_json_encode( $state ) );
			$stats['preview_progress'] = array(
				'state'   => basename( $file ),
				'unit'    => (int) $state['unit'],
				'units'   => $total,
				'partial' => $partial,
			);
			$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_import_batch' ) . "` SET stats = %s WHERE id = %d AND status = 'validated'", (string) wp_json_encode( $stats ), $batch ) );
			return array(
				'status'      => 'validated',
				'done'        => false,
				'units_done'  => (int) $state['unit'],
				'units_total' => $total,
			);
		}
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
		$errors   = (int) $state['errors'];
		$records  = max( 1, count( $state['items'] ) );
		$status   = $errors > max( 100, (int) ceil( 0.02 * $records ) ) || $errors > ImportReport::MAX_ISSUES ? 'failed' : 'previewed';
		$duration = max( 0.001, (float) $state['elapsed'] );
		$rules    = (array) $state['rules'];
		arsort( $rules );
		$summary = (array) $state['summary'];
		$result  = array(
			'records'         => (int) $state['records'],
			'items'           => count( $state['items'] ),
			'files'           => (int) $state['files'],
			'package_bytes'   => is_file( (string) $row['storage_path'] ) ? (int) filesize( (string) $row['storage_path'] ) : 0,
			'object_errors'   => $errors,
			'issues_stored'   => min( $errors, ImportReport::MAX_ISSUES ),
			'rules'           => array_slice( $rules, 0, 10, true ),
			'threshold'       => max( 100, (int) ceil( 0.02 * $records ) ),
			'duration_ms'     => (int) round( $duration * 1000 ),
			'peak_memory'     => memory_get_peak_usage( true ),
			'records_per_s'   => (int) round( (int) $state['records'] / $duration ),
			// Writing costs about ten times the dry run per record (R0-38 §6.2 M-02/M-03 start values).
			'estimate_commit' => (int) ceil( 10 * $duration ),
			'units'           => $total,
		);
		unset( $stats['preview_progress'] );
		$this->move(
			$batch,
			array( 'validated' ),
			$status,
			array(
				'stats' => (string) wp_json_encode(
					array_merge(
						$stats,
						array(
							'plan'    => $summary,
							'partial' => $partial,
							'dry_run' => $result,
						)
					)
				),
			)
		);
		return array(
			'status'      => $status,
			'done'        => true,
			'units_done'  => $total,
			'units_total' => $total,
			'plan'        => $summary,
			'items'       => $state['items'],
			'stats'       => $result,
		);
	}

	/**
	 * Approve a previewed batch (rights confirmation, audit).
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @return void
	 */
	public function approve( Actor $actor, int $batch ): void {
		$this->authorize( $actor, $batch, 'approve', array( 'previewed' ) );
		$this->move(
			$batch,
			array( 'previewed' ),
			'approved',
			array(
				'approved_by' => $actor->user_id,
				'approved_at' => $this->db->now(),
			)
		);
		AuditLog::write( $this->db, $actor, 'import_approved', 'import_batch', (string) $batch );
	}

	/**
	 * Import and activate an approved batch in this request (no time budget).
	 *
	 * Same runner as background, browser and CLI (R0-38 §4.6).
	 *
	 * @param Actor $actor Importer with `ccap_publish_courses`.
	 * @param int   $batch Batch.
	 * @return array<string, array<string, int>> Applied actions per entity.
	 * @throws CourseException When locked or failing (batch → aborted).
	 */
	public function run( Actor $actor, int $batch ): array {
		return ImportRunner::current()->run_all( $actor, $batch );
	}

	/**
	 * Discard a batch that is not published or running (R0-38 §5.1).
	 *
	 * An aborted run is cleaned up first: objects this batch created (never
	 * activated) are removed with their source map rows. Refused while a
	 * step holds the lease or once activation started (then rollback only).
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @return void
	 * @throws CourseException 409 while running or after activation.
	 */
	public function discard( Actor $actor, int $batch ): void {
		$from    = array( 'uploaded', 'validated', 'failed', 'previewed', 'approved', 'aborted' );
		$row     = $this->authorize( $actor, $batch, 'discard', $from );
		$run     = ImportRunner::run_state( $row );
		$removed = 0;
		if ( ! empty( $run['activated'] ) ) {
			throw new CourseException( 'ccap_import_state', 'activated', 409 );
		}
		if ( 'aborted' === (string) $row['status'] ) {
			$removed = ImportRunner::current()->discard_created( $batch );
		}
		$this->move( $batch, $from, 'discarded', array() );
		AuditLog::write( $this->db, $actor, 'import_discarded', 'import_batch', (string) $batch, null, array( 'removed' => $removed ) );
	}

	/**
	 * Rollback to a published/superseded state: a new batch from the kept copy.
	 *
	 * @param Actor  $actor  Importer.
	 * @param int    $batch  Earlier batch.
	 * @param string $reason Mandatory reason.
	 * @return int New batch (status `uploaded`).
	 * @throws CourseException Without reason or copy.
	 */
	public function rollback( Actor $actor, int $batch, string $reason ): int {
		$row    = $this->authorize( $actor, $batch, 'rollback', array( 'published', 'superseded' ) );
		$reason = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_validation_failed', 'reason', 422 );
		}
		if ( ! is_file( (string) $row['storage_path'] ) ) {
			throw new CourseException( 'ccap_not_found', 'Package copy no longer kept.', 404 );
		}
		$new = $this->upload(
			$actor,
			(string) $row['storage_path'],
			array(
				'rollback_of' => $batch,
				'reason'      => $reason,
			)
		);
		AuditLog::write( $this->db, $actor, 'import_rollback_started', 'import_batch', (string) $new, $reason, array( 'rollback_of' => $batch ) );
		return $new;
	}

	/**
	 * Batch row.
	 *
	 * @param int $batch Batch.
	 * @return array<string, mixed>|null
	 */
	public function get( int $batch ): ?array {
		return $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_import_batch' ) . '` WHERE id = %d', $batch ) );
	}

	/**
	 * Stream the package: V6 checks and the planned action per record.
	 *
	 * Entities run in dependency order ({@see ENTITY_TYPES}), data files in
	 * name order, one file in memory at a time. Only the ID sets of seen
	 * and failed records are kept. A record gets the first object error
	 * (`schema`, `duplicate_source_id`, `hash_mismatch`, `unknown_type`,
	 * V4 of its media file, `dependency_failed`, `missing_reference`); then
	 * the action create/update/unchanged/conflict/error. Archive items follow
	 * each entity for full packages.
	 *
	 * @internal Used by the dry run and the runner's plan phase.
	 * @param array<string, mixed>                                                                                                                         $row      Batch row.
	 * @param array<string, mixed>                                                                                                                         $manifest Manifest.
	 * @param bool                                                                                                                                         $partial  Partial package.
	 * @param callable(array{entity: string, source_id: string, action: string, rule: string|null, file: string, record: array<string, mixed>|null}): void $emit Receives each item.
	 * @return array{records: int, files: int}
	 */
	public function scan( array $row, array $manifest, bool $partial, callable $emit ): array {
		$state   = self::scan_state( $partial );
		$context = $this->scan_context( $row, $manifest );
		$zip     = new \ZipArchive();
		$opened  = true === $zip->open( (string) $row['storage_path'], \ZipArchive::RDONLY );
		foreach ( $this->scan_units( $row, $manifest ) as $unit ) {
			$this->scan_unit( $opened ? $zip : null, $manifest, $partial, $unit, $state, $context, $emit );
		}
		if ( $opened ) {
			$zip->close();
		}
		return array(
			'records' => (int) $state['records'],
			'files'   => (int) $state['files'],
		);
	}

	/**
	 * Empty scan state.
	 *
	 * @param bool $partial Partial package.
	 * @return array<string, mixed>
	 */
	private static function scan_state( bool $partial ): array {
		return array(
			'partial' => $partial,
			'unit'    => 0,
			'seen'    => array(),
			'failed'  => array(),
			'paths'   => array(),
			'invalid' => 0,
			'records' => 0,
			'files'   => 0,
		);
	}

	/**
	 * Units of a package: each data file, then the archive plan per entity.
	 *
	 * @param array<string, mixed> $row      Batch row.
	 * @param array<string, mixed> $manifest Manifest.
	 * @return list<array{entity: string, file: string|null, present: bool}>
	 */
	private function scan_units( array $row, array $manifest ): array {
		$files  = array_fill_keys( array_keys( self::ENTITY_TYPES ), array() );
		$zip    = new \ZipArchive();
		$opened = true === $zip->open( (string) $row['storage_path'], \ZipArchive::RDONLY );
		$count  = $opened ? $zip->count() : 0;
		for ( $i = 0; $i < $count; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( 1 === preg_match( '#^data/(' . implode( '|', array_keys( self::ENTITY_TYPES ) ) . ')/\d{6}\.json$#', $name, $m ) ) {
				$files[ $m[1] ][] = $name;
			}
		}
		if ( $opened ) {
			$zip->close();
		}
		$units = array();
		foreach ( array_keys( self::ENTITY_TYPES ) as $entity ) {
			sort( $files[ $entity ], SORT_STRING );
			foreach ( $files[ $entity ] as $name ) {
				$units[] = array(
					'entity'  => $entity,
					'file'    => $name,
					'present' => true,
				);
			}
			// An entity the package does not carry at all counts as empty, not as "archive everything" (R0-10 §1.4).
			$units[] = array(
				'entity'  => $entity,
				'file'    => null,
				'present' => array() !== $files[ $entity ] || array_key_exists( $entity, (array) ( $manifest['counts'] ?? array() ) ),
			);
		}
		return $units;
	}

	/**
	 * Read-only context of a scan step: source map, manifest files, media
	 * files rejected by the validator.
	 *
	 * @param array<string, mixed> $row      Batch row.
	 * @param array<string, mixed> $manifest Manifest.
	 * @return array{map: array<string, array<string, array<string, mixed>>>, files: array<string, array<string, mixed>>, rejected: array<string, string>}
	 */
	private function scan_context( array $row, array $manifest ): array {
		$files = array();
		foreach ( (array) ( $manifest['files'] ?? array() ) as $file ) {
			if ( is_array( $file ) && isset( $file['path'] ) ) {
				$files[ (string) $file['path'] ] = $file;
			}
		}
		$stats = (array) json_decode( (string) ( $row['stats'] ?? '' ), true );
		return array(
			'map'      => $this->source_map( (string) ( $manifest['source']['system'] ?? '' ) ),
			'files'    => $files,
			'rejected' => array_map( 'strval', (array) ( $stats['rejected_media'] ?? array() ) ),
		);
	}

	/**
	 * Scan one unit.
	 *
	 * @param \ZipArchive|null                                                                                                                            $zip      Open package.
	 * @param array<string, mixed>                                                                                                                        $manifest Manifest.
	 * @param bool                                                                                                                                        $partial  Partial package.
	 * @param array{entity: string, file: string|null, present: bool}                                                                                     $unit Unit.
	 * @param array<string, mixed>                                                                                                                        $state    Scan state (updated).
	 * @param array{map: array<string, array<string, array<string, mixed>>>, files: array<string, array<string, mixed>>, rejected: array<string, string>} $context Context.
	 * @param callable                                                                                                                                    $emit     Receives each item.
	 * @return void
	 */
	private function scan_unit( ?\ZipArchive $zip, array $manifest, bool $partial, array $unit, array &$state, array $context, callable $emit ): void {
		unset( $manifest );
		$entity = $unit['entity'];
		$type   = self::ENTITY_TYPES[ $entity ];
		$map    = $context['map'];
		if ( null === $unit['file'] ) {
			if ( 'media' === $entity ) {
				// A rejected file without a record is logged once as well (R0-10 §3.2).
				foreach ( $context['rejected'] as $path => $rule ) {
					if ( ! isset( $state['paths'][ $path ] ) ) {
						$emit(
							array(
								'entity'    => 'media',
								'source_id' => $path,
								'action'    => 'error',
								'rule'      => $rule,
								'file'      => $path,
								'record'    => null,
							)
						);
					}
				}
			}
			if ( $partial || ! $unit['present'] ) {
				return;
			}
			foreach ( $map[ $type ] ?? array() as $source => $known ) {
				if ( ! isset( $state['seen'][ $type ][ (string) $source ] ) && 'archived' !== $known['state'] ) {
					$emit(
						array(
							'entity'    => $entity,
							'source_id' => (string) $source,
							'action'    => 'archive',
							'rule'      => null,
							'file'      => '',
							'record'    => null,
						)
					);
				}
			}
			return;
		}
		++$state['files'];
		$list = null === $zip ? null : json_decode( (string) $zip->getFromName( $unit['file'] ), true );
		foreach ( is_array( $list ) ? $list : array() as $index => $record ) {
			++$state['records'];
			$file   = $unit['file'] . '#' . $index;
			$record = is_array( $record ) ? $record : array();
			$source = (string) ( $record['source_id'] ?? '' );
			$rule   = null;
			if ( '' === $source || strlen( $source ) > 191 ) {
				$source = 'invalid-' . ( ++$state['invalid'] );
				$rule   = 'schema';
			} elseif ( isset( $state['seen'][ $type ][ $source ] ) ) {
				$source = $source . '#dup' . ( ++$state['invalid'] );
				$rule   = 'duplicate_source_id';
			} else {
				$state['seen'][ $type ][ $source ] = true;
			}
			if ( 'media' === $entity && isset( $record['path'] ) ) {
				$state['paths'][ (string) $record['path'] ] = true;
			}
			if ( null === $rule && Canonical::hash( $record ) !== ( $record['hash'] ?? '' ) ) {
				$rule = 'hash_mismatch';
			}
			if ( null === $rule && 'questions' === $entity && ! in_array( (string) ( $record['revision']['question_type'] ?? '' ), QuestionTypes::ALL, true ) ) {
				$rule = 'unknown_type';
			}
			if ( null === $rule && 'flashcards' === $entity ) {
				$rule = self::card_error( $record );
			}
			// D8: course and module `rights` follow `common.rights` (R0-10 §1.5).
			if ( null === $rule && in_array( $entity, array( 'courses', 'modules' ), true ) && array_key_exists( 'rights', $record ) && ! Rights::valid_package_rights( $record['rights'] ) ) {
				$rule = 'schema';
			}
			if ( null === $rule && 'media' === $entity ) {
				$rule = ImportMedia::record_error( $record, $context['files'], $context['rejected'] );
			}
			if ( null === $rule && 'blueprints' === $entity ) {
				$rule = ImportBlueprints::record_error( $record );
			}
			if ( null === $rule ) {
				foreach ( self::references( $entity, $record ) as $ref_type => $refs ) {
					foreach ( $refs as $ref ) {
						if ( isset( $state['failed'][ $ref_type . ':' . $ref ] ) ) {
							$rule = 'dependency_failed';
						} elseif ( ! isset( $state['seen'][ $ref_type ][ $ref ] ) && ! isset( $map[ $ref_type ][ $ref ] ) ) {
							$rule = 'missing_reference';
						}
					}
				}
				// Media references must resolve; a rejected optional medium only drops the reference (R0-38 §5.3).
				foreach ( ImportMedia::references( $record ) as $ref ) {
					if ( ! isset( $state['seen']['media'][ $ref ] ) && ! isset( $map['media'][ $ref ] ) ) {
						$rule = $rule ?? 'missing_reference';
					}
				}
			}
			$known = $map[ $type ][ $source ] ?? null;
			if ( null !== $rule ) {
				$action                                   = 'error';
				$state['failed'][ $type . ':' . $source ] = true;
			} elseif ( null === $known ) {
				$action = 'create';
			} elseif ( 'archived' === $known['state'] && ( ! self::retired( $type, $record ) || $known['last_hash'] !== $record['hash'] ) ) {
				// Present again (e.g. rollback): restore, never stay archived.
				$action = 'update';
			} elseif ( $known['last_hash'] === $record['hash'] ) {
				$action = 'unchanged';
			} else {
				$action = '1' === (string) $known['locally_modified'] ? 'conflict' : 'update';
			}
			$emit(
				array(
					'entity'    => $entity,
					'source_id' => $source,
					'action'    => $action,
					'rule'      => $rule,
					'file'      => $file,
					'record'    => in_array( $action, array( 'create', 'update' ), true ) ? $record : null,
				)
			);
		}
	}

	/**
	 * Whether a record of a post entity is retired (`editorial_status =
	 * deprecated`): imported and kept archived (R0-05 §2.2 L-A-10).
	 *
	 * @param string               $type   Object type.
	 * @param array<string, mixed> $record Record.
	 * @return bool
	 */
	private static function retired( string $type, array $record ): bool {
		return in_array( $type, self::POST_TYPES, true ) && 'deprecated' === ( $record['editorial_status'] ?? '' );
	}

	/**
	 * Apply one planned item (content written, revisions not yet switched).
	 *
	 * Archive items only resolve the object here; the runner archives them
	 * during activation so that learners see nothing before (R0-38 §5.1).
	 *
	 * @internal Runner use inside its chunk transaction.
	 * @param Actor                                                                                $actor   Importer.
	 * @param int                                                                                  $batch   Batch.
	 * @param string                                                                               $system  Source system.
	 * @param string                                                                               $package Package ID.
	 * @param array{entity: string, source_id: string, action: string, record?: array<mixed>|null} $item    Spool item.
	 * @return array{object: int|null, revision: int|null, draft: bool, payload: array<string, mixed>|null}
	 * @throws CourseException On write errors.
	 */
	public function apply_item( Actor $actor, int $batch, string $system, string $package, array $item ): array {
		$type     = self::ENTITY_TYPES[ $item['entity'] ];
		$source   = $item['source_id'];
		$object   = $this->mapped( $system, $type, $source );
		$revision = null;
		$draft    = false;
		$payload  = null;
		if ( in_array( $item['action'], array( 'create', 'update' ), true ) ) {
			$record = (array) ( $item['record'] ?? array() );
			$draft  = in_array( (string) ( $record['editorial_status'] ?? 'published' ), self::UNPUBLISHED, true );
			unset( $this->saved[ (int) $object ] );
			if ( 'media' === $type ) {
				$rights = $this->resolve_rights( isset( $record['rights'] ) && is_array( $record['rights'] ) ? $record['rights'] : null, $package );
				$object = ImportMedia::current()->write( $this->package_file( $batch ), $system, $package, $record, $object, $rights );
			} elseif ( 'blueprint' === $type ) {
				$written = ImportBlueprints::current()->write( $actor, $system, $package, $record, $object, fn( string $ref_type, string $ref ): ?int => $this->mapped( $system, $ref_type, $ref ) );
				$object  = $written['object'];
				$payload = $written['payload'];
			} else {
				$object = $this->write_object( $actor, $system, $type, $record, $object );
			}
			if ( in_array( $type, array( 'course', 'module' ), true ) ) {
				$this->write_post_rights( $object, $record, $package );
			}
			if ( in_array( $type, self::QUESTION_TYPES, true ) ) {
				$revision = $this->saved[ $object ] ?? (int) $this->db->scalar( $this->db->prepare( 'SELECT current_revision_id FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', $object ) );
				$this->write_rights( $revision, $record, $package );
				if ( array_key_exists( 'media', $record ) && $revision > 0 ) {
					ImportMedia::current()->link( $system, 'question_revision', $revision, array_values( (array) $record['media'] ) );
				}
			}
			$this->map( $system, $type, $source, $object, (string) ( $record['hash'] ?? '' ), $package, $batch );
		}
		return array(
			'object'   => $object,
			'revision' => $revision,
			'draft'    => $draft,
			'payload'  => $payload,
		);
	}

	/**
	 * Activate the blueprints this batch wrote (R0-10 §3.4 activation):
	 * those of one course (inside its activation), or all remaining ones
	 * except those of held courses (null).
	 *
	 * @internal Runner use inside the activation transaction.
	 * @param Actor    $actor  Importer.
	 * @param int      $batch  Batch.
	 * @param int|null $course Course, or null for the rest.
	 * @param array    $held   Held courses (rest only).
	 * @phpstan-param list<int> $held
	 * @return int Blueprints activated.
	 */
	public function activate_blueprints( Actor $actor, int $batch, ?int $course, array $held = array() ): int {
		$log   = $this->db->table( 'ccap_import_item_log' );
		$done  = 0;
		$rules = ImportBlueprints::current();
		foreach ( $this->db->rows( $this->db->prepare( "SELECT id, object_id, message FROM `{$log}` WHERE batch_id = %d AND entity = 'blueprints' AND outcome = 'pending' AND object_id IS NOT NULL ORDER BY id", $batch ) ) as $row ) {
			$payload = (array) json_decode( (string) $row['message'], true );
			$owner   = (int) ( $payload['course'] ?? 0 );
			if ( null !== $course && $owner !== $course ) {
				continue;
			}
			if ( null === $course && in_array( $owner, $held, true ) ) {
				// The course was held (validator blocker, E-179): the rule stays a draft.
				$this->db->execute( $this->db->prepare( "UPDATE `{$log}` SET outcome = 'held' WHERE id = %d", (int) $row['id'] ) );
				continue;
			}
			$rules->activate( $actor, (int) $row['object_id'], $payload );
			$this->db->execute( $this->db->prepare( "UPDATE `{$log}` SET outcome = 'ok' WHERE id = %d", (int) $row['id'] ) );
			++$done;
		}
		return $done;
	}

	/**
	 * Package copy of a batch (cached per run).
	 *
	 * @param int $batch Batch.
	 * @return string
	 */
	private function package_file( int $batch ): string {
		if ( ! isset( $this->packages[ $batch ] ) ) {
			$this->packages[ $batch ] = (string) $this->db->scalar( $this->db->prepare( 'SELECT storage_path FROM `' . $this->db->table( 'ccap_import_batch' ) . '` WHERE id = %d', $batch ) );
		}
		return $this->packages[ $batch ];
	}

	/**
	 * Archive an object (never deleted).
	 *
	 * @internal Runner use (activation phase `archive`).
	 * @param Actor  $actor  Importer.
	 * @param string $type   Object type.
	 * @param int    $object_id Object.
	 * @return void
	 */
	public function archive_object( Actor $actor, string $type, int $object_id ): void {
		if ( 'media' === $type ) {
			ImportMedia::current()->archive( $object_id );
			return;
		}
		if ( 'blueprint' === $type ) {
			ImportBlueprints::current()->archive( $object_id );
			return;
		}
		if ( in_array( $type, self::QUESTION_TYPES, true ) ) {
			QuestionRepository::current()->archive( $actor, $object_id );
			return;
		}
		wp_update_post(
			array(
				'ID'          => $object_id,
				'post_status' => PostTypes::STATUS_ARCHIVED,
			)
		);
	}

	/**
	 * Write one object.
	 *
	 * @param Actor                $actor  Importer.
	 * @param string               $system Source system.
	 * @param string               $type   Object type.
	 * @param array<string, mixed> $record Record.
	 * @param int|null             $existing Existing object.
	 * @return int
	 * @throws CourseException On write errors.
	 */
	private function write_object( Actor $actor, string $system, string $type, array $record, ?int $existing ): int {
		$object = $existing;
		if ( in_array( $type, self::QUESTION_TYPES, true ) ) {
			$repo = QuestionRepository::current();
			$data = 'flashcard' === $type ? self::card_data( $record ) : array(
				'question_type' => (string) $record['revision']['question_type'],
				'question_text' => self::html( (string) ( $record['revision']['text_html'] ?? '' ) ),
				'settings'      => (array) ( $record['revision']['settings'] ?? array() ),
				'answers'       => self::answers( (string) $record['revision']['question_type'], array_values( (array) ( $record['answers'] ?? array() ) ) ),
				'tags'          => array_values( (array) ( $record['tags'] ?? array() ) ),
			);
			if ( 'flashcard' !== $type && ! empty( $record['revision']['image_required'] ) ) {
				$data['image_required'] = true;
			}
			// Explanation texts are imported and kept in Free; delivery needs Pro (E-20).
			foreach ( array( 'feedback_correct', 'feedback_incorrect' ) as $field ) {
				if ( isset( $record['revision'][ $field ] ) && '' !== (string) $record['revision'][ $field ] ) {
					$data[ $field ] = self::html( (string) $record['revision'][ $field ] );
				}
			}
			if ( null === $object ) {
				return $repo->create( $actor, $data );
			}
			$this->saved[ $object ] = $repo->save( $actor, $object, $data, 'substantive' );
			return $object;
		}
		$post_type = array(
			'course' => Relations::COURSE_TYPE,
			'module' => Relations::MODULE_TYPE,
			'lesson' => Relations::LESSON_TYPE,
		)[ $type ];
		$postarr   = array(
			'post_type'    => $post_type,
			'post_title'   => sanitize_text_field( (string) ( $record['title'] ?? '' ) ),
			'post_content' => self::html( (string) ( $record['body_html'] ?? $record['description_html'] ?? '' ) ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $record['summary'] ?? '' ) ),
			'post_author'  => $actor->user_id,
		);
		$retired   = self::retired( $type, $record );
		if ( null === $object ) {
			$postarr['post_status'] = $retired ? PostTypes::STATUS_ARCHIVED : ( 'course' === $type ? 'draft' : 'publish' );
			if ( 'course' === $type && ! empty( $record['slug'] ) ) {
				$postarr['post_name'] = sanitize_title( (string) $record['slug'] );
			}
			$created = wp_insert_post( $postarr, true );
			$object  = is_wp_error( $created ) ? 0 : (int) $created;
		} else {
			$postarr['ID'] = $object;
			if ( $retired ) {
				$postarr['post_status'] = PostTypes::STATUS_ARCHIVED;
			} elseif ( 'course' !== $type ) {
				$postarr['post_status'] = 'publish';
			}
			wp_update_post( $postarr );
		}
		if ( $object <= 0 ) {
			throw new CourseException( 'ccap_import_failed', 'Post could not be written.', 500 );
		}
		if ( 'lesson' === $type ) {
			ActivityService::current()->define( $actor, $object, (string) ( $record['activity_type'] ?? 'lesson' ), (array) ( $record['settings'] ?? array() ) );
			if ( array_key_exists( 'quiz', $record ) ) {
				$this->link_quiz( $system, $object, array_values( (array) $record['quiz'] ) );
			}
			if ( array_key_exists( 'media', $record ) ) {
				ImportMedia::current()->link( $system, 'lesson', $object, array_values( (array) $record['media'] ) );
			}
		} elseif ( 'module' === $type ) {
			foreach ( (array) ( $record['lessons'] ?? array() ) as $link ) {
				$lesson = $this->mapped( $system, 'lesson', (string) $link['lesson'] );
				if ( null !== $lesson ) {
					wp_update_post(
						array(
							'ID'          => $lesson,
							'post_parent' => $object,
							'menu_order'  => (int) ( $link['sort'] ?? 0 ),
						)
					);
				}
			}
			foreach ( (array) ( $record['question_pool'] ?? array() ) as $link ) {
				$question = $this->mapped( $system, 'question', (string) $link['question'] );
				if ( null !== $question ) {
					QuestionRepository::current()->add_to_pool( $actor, $object, $question, (int) ( $link['sort'] ?? 0 ) );
				}
			}
		} elseif ( 'course' === $type ) {
			$structure = CourseStructure::current();
			$keep      = array();
			foreach ( (array) ( $record['modules'] ?? array() ) as $link ) {
				$module = $this->mapped( $system, 'module', (string) $link['module'] );
				if ( null !== $module ) {
					$structure->assign_module( $actor, $object, $module, (int) ( $link['sort'] ?? 0 ), ! array_key_exists( 'required', $link ) || ! empty( $link['required'] ) );
					$keep[] = $module;
				}
			}
			foreach ( $structure->modules( $object ) as $existing ) {
				if ( ! in_array( $existing['module_id'], $keep, true ) ) {
					$structure->remove_module( $actor, $object, $existing['module_id'] );
				}
			}
		}
		return $object;
	}

	/**
	 * Earlier published batches of the same source system become superseded.
	 *
	 * @internal Runner use (finalize).
	 * @param int    $batch  Current batch.
	 * @param string $system Source system.
	 * @return void
	 */
	public function supersede( int $batch, string $system ): void {
		$table = $this->db->table( 'ccap_import_batch' );
		foreach ( $this->db->rows( "SELECT id, manifest FROM `{$table}` WHERE status = 'published'" ) as $row ) {
			$m = (array) json_decode( (string) $row['manifest'], true );
			if ( (int) $row['id'] !== $batch && ( $m['source']['system'] ?? '' ) === $system ) {
				$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET status = 'superseded' WHERE id = %d", (int) $row['id'] ) );
			}
		}
	}

	/**
	 * References of a record by object type.
	 *
	 * @param string               $entity Entity.
	 * @param array<string, mixed> $record Record.
	 * @return array<string, list<string>>
	 */
	public static function references( string $entity, array $record ): array {
		$col = static fn( string $key, string $field ): array => array_values( array_map( static fn( $l ): string => (string) ( is_array( $l ) ? ( $l[ $field ] ?? '' ) : '' ), (array) ( $record[ $key ] ?? array() ) ) );
		switch ( $entity ) {
			case 'questions':
				// Only a required image makes the question depend on its medium (R0-38 §5.3).
				return empty( $record['revision']['image_required'] ) ? array() : array( 'media' => ImportMedia::references( $record ) );
			case 'blueprints':
				return ImportBlueprints::references( $record );
			case 'courses':
				return array( 'module' => $col( 'modules', 'module' ) );
			case 'modules':
				return array(
					'lesson'   => $col( 'lessons', 'lesson' ),
					'question' => $col( 'question_pool', 'question' ),
				);
			case 'lessons':
				// `quiz[]` entries name a question or a flashcard (R0-10 §1.3).
				$quiz = array_values( array_filter( (array) ( $record['quiz'] ?? array() ), 'is_array' ) );
				return array(
					'question'  => array_values( array_map( 'strval', array_filter( array_column( $quiz, 'question' ), static fn( $v ): bool => '' !== (string) $v ) ) ),
					'flashcard' => array_values( array_map( 'strval', array_filter( array_column( $quiz, 'flashcard' ), static fn( $v ): bool => '' !== (string) $v ) ) ),
				);
		}
		return array();
	}

	/**
	 * Source map of a system: type → source_id → row (+ state).
	 *
	 * @param string $system Source system.
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function source_map( string $system ): array {
		$out   = array();
		$map   = $this->db->table( 'ccap_source_map' );
		$posts = $this->db->wpdb()->posts;
		// Two joined reads instead of one status query per row (large re-imports, R0-38 M-05).
		$rows = array_merge(
			$this->db->rows( $this->db->prepare( "SELECT m.object_type, m.object_id, m.source_id, m.last_hash, m.locally_modified, q.status AS state FROM `{$map}` m LEFT JOIN `" . $this->db->table( 'ccap_question' ) . "` q ON q.id = m.object_id WHERE m.source_system = %s AND m.object_type IN ('question', 'flashcard')", $system ) ),
			$this->db->rows( $this->db->prepare( "SELECT m.object_type, m.object_id, m.source_id, m.last_hash, m.locally_modified, p.post_status AS state FROM `{$map}` m LEFT JOIN `{$posts}` p ON p.ID = m.object_id WHERE m.source_system = %s AND m.object_type IN ('course', 'module', 'lesson')", $system ) ),
			$this->db->rows( $this->db->prepare( "SELECT m.object_type, m.object_id, m.source_id, m.last_hash, m.locally_modified, x.status AS state FROM `{$map}` m LEFT JOIN `" . $this->db->table( 'ccap_media' ) . "` x ON x.id = m.object_id WHERE m.source_system = %s AND m.object_type = 'media'", $system ) ),
			$this->db->rows( $this->db->prepare( "SELECT m.object_type, m.object_id, m.source_id, m.last_hash, m.locally_modified, x.status AS state FROM `{$map}` m LEFT JOIN `" . $this->db->table( 'ccap_exam_rule' ) . "` x ON x.id = m.object_id WHERE m.source_system = %s AND m.object_type = 'blueprint'", $system ) )
		);
		foreach ( $rows as $row ) {
			$state        = (string) ( $row['state'] ?? '' );
			$row['state'] = in_array( $state, array( 'archived', 'retired', PostTypes::STATUS_ARCHIVED, '', 'trash' ), true ) ? 'archived' : 'active';
			$out[ (string) $row['object_type'] ][ (string) $row['source_id'] ] = $row;
		}
		return $out;
	}

	/**
	 * Mapped object ID.
	 *
	 * @param string $system Source system.
	 * @param string $type   Object type.
	 * @param string $source Source ID.
	 * @return int|null
	 */
	private function mapped( string $system, string $type, string $source ): ?int {
		$id = $this->db->scalar( $this->db->prepare( 'SELECT object_id FROM `' . $this->db->table( 'ccap_source_map' ) . '` WHERE source_system = %s AND object_type = %s AND source_id = %s', $system, $type, $source ) );
		return null === $id ? null : (int) $id;
	}

	/**
	 * Upsert the source map row.
	 *
	 * @param string $system  Source system.
	 * @param string $type    Object type.
	 * @param string $source  Source ID.
	 * @param int    $object_id Object ID.
	 * @param string $hash    Record hash.
	 * @param string $package Package ID.
	 * @param int    $batch   Batch.
	 * @return void
	 */
	private function map( string $system, string $type, string $source, int $object_id, string $hash, string $package, int $batch ): void {
		$now = $this->db->now();
		$this->db->execute(
			$this->db->prepare(
				'INSERT INTO `' . $this->db->table( 'ccap_source_map' ) . '` (uid, object_type, object_id, source_system, source_id, source_hash, package_id, imported_at, locally_modified, last_batch_id, last_hash, created_at, updated_at) VALUES (%s, %s, %d, %s, %s, %s, %s, %s, 0, %d, %s, %s, %s) ON DUPLICATE KEY UPDATE object_id = VALUES(object_id), source_system = VALUES(source_system), source_id = VALUES(source_id), source_hash = VALUES(source_hash), package_id = VALUES(package_id), imported_at = VALUES(imported_at), locally_modified = 0, last_batch_id = VALUES(last_batch_id), last_hash = VALUES(last_hash), updated_at = VALUES(updated_at)',
				$this->db->uuid(),
				$type,
				$object_id,
				$system,
				$source,
				$hash,
				$package,
				$now,
				$batch,
				$hash,
				$now,
				$now
			)
		);
	}

	/**
	 * V6 rule of a flashcard record (R0-10 §1.3): front and back side,
	 * known mode, valid rights status.
	 *
	 * @param array<string, mixed> $record Record.
	 * @return string|null Rule or null.
	 */
	private static function card_error( array $record ): ?string {
		if ( '' === trim( (string) ( $record['front_html'] ?? '' ) ) || '' === trim( (string) ( $record['back_html'] ?? '' ) ) ) {
			return 'schema';
		}
		if ( isset( $record['mode'] ) && ! in_array( (string) $record['mode'], self::CARD_MODES, true ) ) {
			return 'schema';
		}
		if ( isset( $record['rights'] ) && ! in_array( (string) ( $record['rights']['status'] ?? '' ), Rights::STATUSES, true ) ) {
			return 'schema';
		}
		return null;
	}

	/**
	 * Revision data of a flashcard (question type `flashcard`, E-21).
	 *
	 * @param array<string, mixed> $record Record.
	 * @return array<string, mixed>
	 */
	private static function card_data( array $record ): array {
		$data = array(
			'question_type' => QuestionTypes::FLASHCARD,
			'question_text' => self::html( (string) ( $record['front_html'] ?? '' ) ),
			'settings'      => array(
				'back' => self::html( (string) ( $record['back_html'] ?? '' ) ),
				'mode' => (string) ( $record['mode'] ?? 'self_assessment' ),
			),
			'tags'          => array_values( (array) ( $record['tags'] ?? array() ) ),
		);
		if ( '' !== trim( (string) ( $record['explanation_html'] ?? '' ) ) ) {
			// Explanation of the card: stored in Free, delivered only with Pro (E-20).
			$data['feedback_correct'] = self::html( (string) $record['explanation_html'] );
		}
		return $data;
	}

	/**
	 * Store the rights of an imported question or flashcard on its revision
	 * (R0-10 §2.1). Imported content is foreign unless the package is a
	 * re-import of this site's own export; foreign `own` becomes `licensed`
	 * with the publisher as owner (E-62). Overrides stay untouched.
	 *
	 * @param int                  $revision Revision holding the content.
	 * @param array<string, mixed> $record   Record.
	 * @param string               $package  Package ID.
	 * @return void
	 */
	private function write_rights( int $revision, array $record, string $package ): void {
		if ( $revision <= 0 ) {
			return;
		}
		$rights = isset( $record['revision'] ) ? ( $record['revision']['rights'] ?? null ) : ( $record['rights'] ?? null );
		$rights = $this->resolve_rights( is_array( $rights ) ? $rights : null, $package );
		$this->db->execute(
			$this->db->prepare(
				'UPDATE `' . $this->db->table( 'ccap_question_revision' ) . '` SET rights_status = %s, rights_owner = %s, license_reference = %s WHERE id = %d',
				$rights['status'],
				null === $rights['owner'] ? '' : substr( sanitize_text_field( $rights['owner'] ), 0, 255 ),
				array() === $rights['license'] ? '' : (string) wp_json_encode( $rights['license'] ),
				$revision
			)
		);
	}

	/**
	 * Store the rights of an imported course or module (R0-10 §2.1 post
	 * meta `_ccap_rights`): the record's `rights`, else the package's
	 * `default_rights`, else none. Foreign `own` becomes `licensed` with
	 * the publisher as owner (E-62); locally saved rights stay.
	 *
	 * @param int                  $post_id Course or module.
	 * @param array<string, mixed> $record  Record.
	 * @param string               $package Package ID.
	 * @return void
	 */
	private function write_post_rights( int $post_id, array $record, string $package ): void {
		$rights = is_array( $record['rights'] ?? null ) ? $record['rights'] : null;
		if ( null === $rights ) {
			$default = $this->package_manifest( $package )['default_rights'] ?? null;
			$rights  = Rights::valid_package_rights( $default ) ? (array) $default : null;
		}
		Rights::import_meta( $post_id, null === $rights ? null : $this->resolve_rights( $rights, $package ) );
	}

	/**
	 * Rights of a record (R0-10 §2.1): without rights the record is `own`
	 * of the source; foreign `own` becomes `licensed` with the publisher as
	 * owner (E-62); an unknown status counts as `unknown`.
	 *
	 * @param array<string, mixed>|null $rights  Rights object of the record.
	 * @param string                    $package Package ID.
	 * @return array{status: string, owner: string|null, license: array<string, mixed>}
	 */
	private function resolve_rights( ?array $rights, string $package ): array {
		$rights = $rights ?? array( 'status' => 'own' );
		$status = in_array( (string) ( $rights['status'] ?? '' ), Rights::STATUSES, true ) ? (string) $rights['status'] : 'unknown';
		$owner  = isset( $rights['owner'] ) ? (string) $rights['owner'] : null;
		if ( 'own' === $status && ! $this->own_package( $package ) ) {
			$status = 'licensed';
			$owner  = $owner ?? $this->publisher( $package );
		}
		return array(
			'status'  => $status,
			'owner'   => $owner,
			'license' => array_intersect_key( $rights, array_flip( array( 'license', 'license_url', 'attribution', 'source_url', 'valid_until', 'note' ) ) ),
		);
	}

	/**
	 * Manifest of the batch that carries a package (cached per run).
	 *
	 * @param string $package Package ID.
	 * @return array<string, mixed>
	 */
	private function package_manifest( string $package ): array {
		if ( ! isset( $this->manifests[ $package ] ) ) {
			$json                        = $this->db->scalar( $this->db->prepare( 'SELECT manifest FROM `' . $this->db->table( 'ccap_import_batch' ) . '` WHERE package_id = %s ORDER BY id DESC LIMIT 1', $package ) );
			$this->manifests[ $package ] = (array) json_decode( (string) $json, true );
		}
		return $this->manifests[ $package ];
	}

	/**
	 * Whether a package is this site's own content (R0-10 §2.2): source
	 * system `local` or exported from this installation.
	 *
	 * @param string $package Package ID.
	 * @return bool
	 */
	private function own_package( string $package ): bool {
		return Rights::own_manifest( $this->package_manifest( $package ) );
	}

	/**
	 * Publisher name of a package (owner of foreign `own` content, E-62).
	 *
	 * @param string $package Package ID.
	 * @return string
	 */
	private function publisher( string $package ): string {
		$manifest = $this->package_manifest( $package );
		return (string) ( $manifest['publisher']['name'] ?? $manifest['source']['system'] ?? '' );
	}

	/**
	 * Replace the inline questions/cards of a lesson (`quiz[]`, R0-10 §1.3).
	 *
	 * @param string            $system Source system.
	 * @param int               $lesson Lesson.
	 * @param array<int, mixed> $quiz   Entries `{question}` or `{flashcard}` with optional `sort`.
	 * @return void
	 */
	private function link_quiz( string $system, int $lesson, array $quiz ): void {
		$table = $this->db->table( 'ccap_activity_question' );
		$this->db->execute( $this->db->prepare( "DELETE FROM `{$table}` WHERE activity_id = %d", $lesson ) );
		foreach ( $quiz as $i => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$type   = isset( $entry['flashcard'] ) ? 'flashcard' : 'question';
			$target = $this->mapped( $system, $type, (string) ( $entry[ $type ] ?? '' ) );
			if ( null !== $target ) {
				$this->db->execute( $this->db->prepare( "INSERT IGNORE INTO `{$table}` (activity_id, question_id, sort_order) VALUES (%d, %d, %d)", $lesson, $target, (int) ( $entry['sort'] ?? $i + 1 ) ) );
			}
		}
	}

	/**
	 * Answers with internal keys (R0-08 §3.1 `^([A-Z]|MODEL)$`): package keys
	 * in another form (e.g. `A1-001-a`) map by position to A, B, …; open text
	 * uses `MODEL`.
	 *
	 * @param string $type    Question type.
	 * @param array  $answers Package answers.
	 * @phpstan-param list<mixed> $answers
	 * @return list<array{key: string, text: string, correct: bool}>
	 */
	private static function answers( string $type, array $answers ): array {
		$out = array();
		foreach ( $answers as $i => $answer ) {
			$answer = is_array( $answer ) ? $answer : array();
			$key    = (string) ( $answer['key'] ?? '' );
			if ( 1 !== preg_match( '/^([A-Z]|MODEL)$/', $key ) ) {
				$key = QuestionTypes::OPEN_TEXT === $type ? 'MODEL' : chr( 65 + min( 25, (int) $i ) );
			}
			$out[] = array(
				'key'     => $key,
				'text'    => self::html( (string) ( $answer['text_html'] ?? '' ) ),
				'correct' => ! empty( $answer['correct'] ),
			);
		}
		return $out;
	}

	/**
	 * HTML allowlist for imported fields (no iframe/script/style/on*, https/mailto only).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function html( string $html ): string {
		$allowed = wp_kses_allowed_html( 'post' );
		unset( $allowed['iframe'], $allowed['script'], $allowed['style'], $allowed['form'], $allowed['input'] );
		foreach ( $allowed as $tag => $attrs ) {
			if ( is_array( $attrs ) ) {
				unset( $allowed[ $tag ]['style'] );
			}
		}
		return wp_kses( $html, $allowed, array( 'https', 'mailto' ) );
	}

	/**
	 * Batch with rights check and allowed states.
	 *
	 * @param Actor  $actor Importer.
	 * @param int    $batch Batch.
	 * @param string $step  Step.
	 * @param array  $from  Allowed states.
	 * @phpstan-param list<string> $from
	 * @return array<string, mixed>
	 * @throws CourseException 403/404/409.
	 */
	public function authorize( Actor $actor, int $batch, string $step, array $from ): array {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::MANAGE_IMPORT, array( $batch ), array( 'step' => $step ) ), 'Not allowed to import.' );
		$row = (array) $this->get( $batch );
		if ( ! in_array( (string) $row['status'], $from, true ) ) {
			throw new CourseException( 'ccap_import_state', esc_html( (string) $row['status'] ), 409 );
		}
		return $row;
	}

	/**
	 * Conditional status change.
	 *
	 * @param int                  $batch Batch.
	 * @param array                $from  Allowed states.
	 * @param string               $to    Target state.
	 * @param array<string, mixed> $set   Extra columns.
	 * @phpstan-param list<string> $from
	 * @return void
	 * @throws CourseException When the batch changed concurrently.
	 */
	public function move( int $batch, array $from, string $to, array $set ): void {
		$set['status'] = $to;
		$assign        = array();
		$values        = array();
		foreach ( $set as $column => $value ) {
			if ( null === $value ) {
				$assign[] = "`{$column}` = NULL";
				continue;
			}
			$assign[] = "`{$column}` = %s";
			$values[] = (string) $value;
		}
		$in       = implode( ',', array_map( fn( string $s ): string => $this->db->prepare( '%s', $s ), $from ) );
		$values[] = $batch;
		$done     = $this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_import_batch' ) . '` SET ' . implode( ', ', $assign ) . " WHERE id = %d AND status IN ({$in})", ...$values ) );
		if ( 1 !== $done ) {
			throw new CourseException( 'ccap_import_state', 'concurrent_change', 409 );
		}
	}
}
