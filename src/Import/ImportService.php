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

	public const ENTITY_TYPES = array(
		'questions'  => 'question',
		'flashcards' => 'flashcard',
		'lessons'    => 'lesson',
		'modules'    => 'module',
		'courses'    => 'course',
	);

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
	 * V1–V5 and `requires`.
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @return array{status: string, errors: list<array{rule: string, path: string}>}
	 */
	public function validate( Actor $actor, int $batch ): array {
		$row    = $this->authorize( $actor, $batch, 'validate', array( 'uploaded' ) );
		$result = PackageValidator::validate( (string) $row['storage_path'] );
		$errors = $result['errors'];
		$m      = $result['manifest'] ?? array();
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
			)
		);
		return array(
			'status' => $status,
			'errors' => $errors,
		);
	}

	/**
	 * Dry run: plan, object issues and statistics; nothing written to content.
	 *
	 * The package is streamed file by file (R0-38 §4.2): memory depends on
	 * the largest data file and the ID sets, not on the record contents.
	 *
	 * @param Actor $actor   Importer.
	 * @param int   $batch   Batch.
	 * @param bool  $partial Partial package (no archiving of missing records).
	 * @return array{status: string, plan: array<string, array<string, int>>, items: list<array{entity: string, source_id: string, action: string, rule: string|null}>, stats: array<string, mixed>}
	 */
	public function preview( Actor $actor, int $batch, bool $partial = false ): array {
		$row      = $this->authorize( $actor, $batch, 'preview', array( 'validated', 'previewed' ) );
		$manifest = (array) json_decode( (string) $row['manifest'], true );
		$started  = microtime( true );
		$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_import_issue' ) . '` WHERE batch_id = %d', $batch ) );
		$summary = array();
		$items   = array();
		$rules   = array();
		$buffer  = array();
		$errors  = 0;
		$scan    = $this->scan(
			$row,
			$manifest,
			$partial,
			function ( array $item ) use ( $batch, &$summary, &$items, &$rules, &$buffer, &$errors ): void {
				$summary[ $item['entity'] ][ $item['action'] ] = ( $summary[ $item['entity'] ][ $item['action'] ] ?? 0 ) + 1;
				$items[]                                       = array(
					'entity'    => $item['entity'],
					'source_id' => $item['source_id'],
					'action'    => $item['action'],
					'rule'      => $item['rule'],
				);
				if ( null === $item['rule'] ) {
					return;
				}
				++$errors;
				$code           = ImportReport::code( $item['rule'] );
				$rules[ $code ] = ( $rules[ $code ] ?? 0 ) + 1;
				if ( $errors <= ImportReport::MAX_ISSUES ) {
					$buffer[] = ImportReport::issue( $this->db, $batch, 'preview', $item['entity'], $item['source_id'], $item['rule'], 'error', 'O', 'rejected', array(), $item['file'] );
				}
				if ( count( $buffer ) >= ImportReport::INSERT_BLOCK ) {
					ImportReport::insert_many( $this->db, 'ccap_import_issue', $buffer );
					$buffer = array();
				}
			}
		);
		ImportReport::insert_many( $this->db, 'ccap_import_issue', $buffer );
		$records  = max( 1, count( $items ) );
		$status   = $errors > max( 100, (int) ceil( 0.02 * $records ) ) || $errors > ImportReport::MAX_ISSUES ? 'failed' : 'previewed';
		$duration = max( 0.001, microtime( true ) - $started );
		arsort( $rules );
		$stats = array(
			'records'         => $scan['records'],
			'items'           => count( $items ),
			'files'           => $scan['files'],
			'package_bytes'   => is_file( (string) $row['storage_path'] ) ? (int) filesize( (string) $row['storage_path'] ) : 0,
			'object_errors'   => $errors,
			'issues_stored'   => min( $errors, ImportReport::MAX_ISSUES ),
			'rules'           => array_slice( $rules, 0, 10, true ),
			'threshold'       => max( 100, (int) ceil( 0.02 * $records ) ),
			'duration_ms'     => (int) round( $duration * 1000 ),
			'peak_memory'     => memory_get_peak_usage( true ),
			'records_per_s'   => (int) round( $scan['records'] / $duration ),
			// Writing costs about ten times the dry run per record (R0-38 §6.2 M-02/M-03 start values).
			'estimate_commit' => (int) ceil( 10 * $duration ),
		);
		$this->move(
			$batch,
			array( 'validated', 'previewed' ),
			$status,
			array(
				'stats' => (string) wp_json_encode(
					array_merge(
						(array) json_decode( (string) $row['stats'], true ),
						array(
							'plan'    => $summary,
							'partial' => $partial,
							'dry_run' => $stats,
						)
					)
				),
			)
		);
		return array(
			'status' => $status,
			'plan'   => $summary,
			'items'  => $items,
			'stats'  => $stats,
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
	 * Entities run in dependency order (questions → lessons → modules →
	 * courses), data files in name order, one file in memory at a time. Only
	 * the ID sets of seen and failed records are kept. A record gets the
	 * first object error (`schema`, `duplicate_source_id`, `hash_mismatch`,
	 * `unknown_type`, `dependency_failed`, `missing_reference`); then the
	 * action create/update/unchanged/conflict/error. Archive items follow
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
		$system = (string) ( $manifest['source']['system'] ?? '' );
		$map    = $this->source_map( $system );
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
		$seen    = array();
		$failed  = array();
		$invalid = 0;
		$records = 0;
		$total   = 0;
		foreach ( self::ENTITY_TYPES as $entity => $type ) {
			sort( $files[ $entity ], SORT_STRING );
			foreach ( $files[ $entity ] as $name ) {
				++$total;
				$list = json_decode( (string) $zip->getFromName( $name ), true );
				foreach ( is_array( $list ) ? $list : array() as $index => $record ) {
					++$records;
					$file   = $name . '#' . $index;
					$record = is_array( $record ) ? $record : array();
					$source = (string) ( $record['source_id'] ?? '' );
					$rule   = null;
					if ( '' === $source || strlen( $source ) > 191 ) {
						$source = 'invalid-' . ( ++$invalid );
						$rule   = 'schema';
					} elseif ( isset( $seen[ $type ][ $source ] ) ) {
						$source = $source . '#dup' . ( ++$invalid );
						$rule   = 'duplicate_source_id';
					} else {
						$seen[ $type ][ $source ] = true;
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
					if ( null === $rule ) {
						foreach ( self::references( $entity, $record ) as $ref_type => $refs ) {
							foreach ( $refs as $ref ) {
								if ( isset( $failed[ $ref_type . ':' . $ref ] ) ) {
									$rule = 'dependency_failed';
								} elseif ( ! isset( $seen[ $ref_type ][ $ref ] ) && ! isset( $map[ $ref_type ][ $ref ] ) ) {
									$rule = 'missing_reference';
								}
							}
						}
					}
					$known = $map[ $type ][ $source ] ?? null;
					if ( null !== $rule ) {
						$action                          = 'error';
						$failed[ $type . ':' . $source ] = true;
					} elseif ( null === $known ) {
						$action = 'create';
					} elseif ( 'archived' === $known['state'] ) {
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
				unset( $list );
			}
			// An entity the package does not carry at all counts as empty, not as "archive everything" (R0-10 §1.4).
			if ( ! $partial && ( array() !== $files[ $entity ] || array_key_exists( $entity, (array) ( $manifest['counts'] ?? array() ) ) ) ) {
				foreach ( $map[ $type ] ?? array() as $source => $known ) {
					if ( ! isset( $seen[ $type ][ (string) $source ] ) && 'archived' !== $known['state'] ) {
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
			}
		}
		if ( $opened ) {
			$zip->close();
		}
		return array(
			'records' => $records,
			'files'   => $total,
		);
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
	 * @return array{object: int|null, revision: int|null, draft: bool}
	 * @throws CourseException On write errors.
	 */
	public function apply_item( Actor $actor, int $batch, string $system, string $package, array $item ): array {
		$type     = self::ENTITY_TYPES[ $item['entity'] ];
		$source   = $item['source_id'];
		$object   = $this->mapped( $system, $type, $source );
		$revision = null;
		$draft    = false;
		if ( in_array( $item['action'], array( 'create', 'update' ), true ) ) {
			$record = (array) ( $item['record'] ?? array() );
			$draft  = in_array( (string) ( $record['editorial_status'] ?? 'published' ), self::UNPUBLISHED, true );
			unset( $this->saved[ (int) $object ] );
			$object = $this->write_object( $actor, $system, $type, $record, $object );
			if ( in_array( $type, self::QUESTION_TYPES, true ) ) {
				$revision = $this->saved[ $object ] ?? (int) $this->db->scalar( $this->db->prepare( 'SELECT current_revision_id FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', $object ) );
				$this->write_rights( $revision, $record, $package );
			}
			$this->map( $system, $type, $source, $object, (string) ( $record['hash'] ?? '' ), $package, $batch );
		}
		return array(
			'object'   => $object,
			'revision' => $revision,
			'draft'    => $draft,
		);
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
		if ( null === $object ) {
			$postarr['post_status'] = 'course' === $type ? 'draft' : 'publish';
			if ( 'course' === $type && ! empty( $record['slug'] ) ) {
				$postarr['post_name'] = sanitize_title( (string) $record['slug'] );
			}
			$created = wp_insert_post( $postarr, true );
			$object  = is_wp_error( $created ) ? 0 : (int) $created;
		} else {
			$postarr['ID'] = $object;
			if ( 'course' !== $type ) {
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
			$this->db->rows( $this->db->prepare( "SELECT m.object_type, m.object_id, m.source_id, m.last_hash, m.locally_modified, p.post_status AS state FROM `{$map}` m LEFT JOIN `{$posts}` p ON p.ID = m.object_id WHERE m.source_system = %s AND m.object_type NOT IN ('question', 'flashcard')", $system ) )
		);
		foreach ( $rows as $row ) {
			$state        = (string) ( $row['state'] ?? '' );
			$row['state'] = in_array( $state, array( 'archived', PostTypes::STATUS_ARCHIVED, '', 'trash' ), true ) ? 'archived' : 'active';
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
		$rights = is_array( $rights ) ? $rights : array( 'status' => 'own' );
		$status = in_array( (string) ( $rights['status'] ?? '' ), Rights::STATUSES, true ) ? (string) $rights['status'] : 'unknown';
		$owner  = isset( $rights['owner'] ) ? (string) $rights['owner'] : null;
		$own    = $this->own_package( $package );
		if ( 'own' === $status && ! $own ) {
			$status = 'licensed';
			$owner  = $owner ?? $this->publisher( $package );
		}
		$license = array_intersect_key( $rights, array_flip( array( 'license', 'license_url', 'attribution', 'source_url', 'valid_until', 'note' ) ) );
		$this->db->execute(
			$this->db->prepare(
				'UPDATE `' . $this->db->table( 'ccap_question_revision' ) . '` SET rights_status = %s, rights_owner = %s, license_reference = %s WHERE id = %d',
				$status,
				null === $owner ? '' : substr( sanitize_text_field( $owner ), 0, 255 ),
				array() === $license ? '' : (string) wp_json_encode( $license ),
				$revision
			)
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
		$manifest = $this->package_manifest( $package );
		$site     = (string) get_option( 'course_captain_installation_uuid', '' );
		$from     = (string) ( $manifest['source']['exported_from_site'] ?? '' );
		return 'local' === ( $manifest['source']['system'] ?? '' ) || ( '' !== $site && $site === $from );
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
