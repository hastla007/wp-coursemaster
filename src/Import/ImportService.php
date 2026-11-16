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
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\PostTypes;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\NamedLock;

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
 *   learners see the old state until then.
 * - Rollback creates a new import from the kept package copy that runs the
 *   normal path; old hashes become active again, later content is archived.
 */
final class ImportService {

	public const ENTITY_TYPES = array(
		'questions' => 'question',
		'lessons'   => 'lesson',
		'modules'   => 'module',
		'courses'   => 'course',
	);

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
	 * Dry run: plan and object issues, nothing written to content.
	 *
	 * @param Actor $actor   Importer.
	 * @param int   $batch   Batch.
	 * @param bool  $partial Partial package (no archiving of missing records).
	 * @return array{status: string, plan: array<string, array<string, int>>, items: list<array{entity: string, source_id: string, action: string, rule: string|null}>}
	 */
	public function preview( Actor $actor, int $batch, bool $partial = false ): array {
		$row      = $this->authorize( $actor, $batch, 'preview', array( 'validated', 'previewed' ) );
		$manifest = (array) json_decode( (string) $row['manifest'], true );
		$plan     = $this->plan( $row, $manifest, $partial );
		$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_import_issue' ) . '` WHERE batch_id = %d', $batch ) );
		$errors = 0;
		foreach ( $plan['items'] as $item ) {
			if ( null === $item['rule'] ) {
				continue;
			}
			++$errors;
			$this->db->insert(
				'ccap_import_issue',
				array(
					'batch_id'    => $batch,
					'phase'       => 'preview',
					'entity'      => $item['entity'],
					'source_id'   => substr( $item['source_id'], 0, 191 ),
					'rule'        => $item['rule'],
					'severity'    => 'error',
					'class'       => 'object',
					'action'      => $item['action'],
					'message_key' => 'import.' . $item['rule'],
					'fingerprint' => hash( 'sha256', $batch . '|' . $item['entity'] . '|' . $item['source_id'] . '|' . $item['rule'] ),
				),
				array( 'created_at' )
			);
		}
		$summary = array();
		foreach ( $plan['items'] as $item ) {
			$summary[ $item['entity'] ][ $item['action'] ] = ( $summary[ $item['entity'] ][ $item['action'] ] ?? 0 ) + 1;
		}
		$records = max( 1, count( $plan['items'] ) );
		$status  = $errors > max( 100, (int) ceil( 0.02 * $records ) ) ? 'failed' : 'previewed';
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
						)
					)
				),
			)
		);
		return array(
			'status' => $status,
			'plan'   => $summary,
			'items'  => $plan['items'],
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
	 * Import and activate an approved batch.
	 *
	 * @param Actor $actor Importer with `ccap_publish_courses`.
	 * @param int   $batch Batch.
	 * @return array<string, array<string, int>> Applied actions per entity.
	 * @throws CourseException When locked or failing (batch → aborted).
	 */
	public function run( Actor $actor, int $batch ): array {
		$row  = $this->authorize( $actor, $batch, 'publish', array( 'approved' ) );
		$lock = NamedLock::for_key( $this->db, 'import' );
		if ( NamedLock::BUSY === $lock->acquire( 0 ) ) {
			throw new CourseException( 'ccap_import_busy', 'Another import is running.', 409 );
		}
		self::$importing = true;
		try {
			$this->move( $batch, array( 'approved' ), 'importing', array( 'heartbeat_at' => $this->db->now() ) );
			$manifest = (array) json_decode( (string) $row['manifest'], true );
			$stats    = (array) json_decode( (string) $row['stats'], true );
			$plan     = $this->plan( $row, $manifest, ! empty( $stats['partial'] ) );
			$applied  = $this->apply( $actor, $batch, $manifest, $plan );
			$this->move( $batch, array( 'importing' ), 'activating', array( 'heartbeat_at' => $this->db->now() ) );
			$this->activate( $actor, $manifest, $applied );
			$this->move( $batch, array( 'activating' ), 'published', array( 'published_at' => $this->db->now() ) );
			$this->supersede( $batch, (string) ( $manifest['source']['system'] ?? '' ) );
			AuditLog::write( $this->db, $actor, 'import_published', 'import_batch', (string) $batch, null, array( 'package_id' => (string) $row['package_id'] ) );
			return $applied['summary'];
		} catch ( CourseException $e ) {
			$this->abort( $batch, $e->getMessage() );
			throw $e;
		} catch ( \Throwable $e ) {
			$this->abort( $batch, $e->getMessage() );
			throw new CourseException( 'ccap_import_failed', 'Import failed.', 500 );
		} finally {
			self::$importing = false;
			$lock->release();
		}
	}

	/**
	 * Mark a running batch as aborted.
	 *
	 * @param int    $batch   Batch.
	 * @param string $message Error.
	 * @return void
	 */
	private function abort( int $batch, string $message ): void {
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_import_batch' ) . "` SET status = 'aborted', error = %s WHERE id = %d AND status IN ('importing', 'activating')", $message, $batch ) );
	}

	/**
	 * Discard a batch that is not published or running.
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @return void
	 */
	public function discard( Actor $actor, int $batch ): void {
		$from = array( 'uploaded', 'validated', 'failed', 'previewed', 'approved', 'aborted' );
		$this->authorize( $actor, $batch, 'discard', $from );
		$this->move( $batch, $from, 'discarded', array() );
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
	 * Plan: records, V6 checks, actions.
	 *
	 * @param array<string, mixed> $row      Batch row.
	 * @param array<string, mixed> $manifest Manifest.
	 * @param bool                 $partial  Partial package.
	 * @return array{records: array<string, array<string, array<string, mixed>>>, items: list<array{entity: string, source_id: string, action: string, rule: string|null}>}
	 */
	private function plan( array $row, array $manifest, bool $partial ): array {
		$system  = (string) ( $manifest['source']['system'] ?? '' );
		$records = $this->read( (string) $row['storage_path'] );
		$items   = array();
		$failed  = array();
		$map     = $this->source_map( $system );
		foreach ( self::ENTITY_TYPES as $entity => $type ) {
			foreach ( $records[ $entity ] as $source => $record ) {
				$rule = $record['_error'] ?? null;
				if ( null === $rule && Canonical::hash( array_diff_key( $record, array( '_error' => 1 ) ) ) !== ( $record['hash'] ?? '' ) ) {
					$rule = 'hash_mismatch';
				}
				if ( null === $rule && 'questions' === $entity && ! in_array( (string) ( $record['revision']['question_type'] ?? '' ), QuestionTypes::ALL, true ) ) {
					$rule = 'unknown_type';
				}
				if ( null === $rule ) {
					foreach ( self::references( $entity, $record ) as $ref_type => $refs ) {
						foreach ( $refs as $ref ) {
							$ref_entity = array_search( $ref_type, self::ENTITY_TYPES, true );
							if ( isset( $failed[ $ref_type . ':' . $ref ] ) ) {
								$rule = 'dependency_failed';
							} elseif ( ! isset( $records[ (string) $ref_entity ][ $ref ] ) && ! isset( $map[ $ref_type ][ $ref ] ) ) {
								$rule = 'missing_reference';
							}
						}
					}
				}
				$known = $map[ $type ][ $source ] ?? null;
				if ( null !== $rule ) {
					$action = 'error';
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
				if ( null !== $rule ) {
					$failed[ $type . ':' . $source ] = true;
				}
				$items[] = array(
					'entity'    => $entity,
					'source_id' => (string) $source,
					'action'    => $action,
					'rule'      => $rule,
				);
			}
			if ( ! $partial ) {
				foreach ( $map[ $type ] ?? array() as $source => $known ) {
					if ( ! isset( $records[ $entity ][ $source ] ) && 'archived' !== $known['state'] ) {
						$items[] = array(
							'entity'    => $entity,
							'source_id' => (string) $source,
							'action'    => 'archive',
							'rule'      => null,
						);
					}
				}
			}
		}
		return array(
			'records' => $records,
			'items'   => $items,
		);
	}

	/**
	 * Apply the plan (content written, revisions not yet switched).
	 *
	 * @param Actor                                                                                                                                                        $actor    Importer.
	 * @param int                                                                                                                                                          $batch    Batch.
	 * @param array<string, mixed>                                                                                                                                         $manifest Manifest.
	 * @param array{records: array<string, array<string, array<string, mixed>>>, items: list<array{entity: string, source_id: string, action: string, rule: string|null}>} $plan Plan.
	 * @return array{summary: array<string, array<string, int>>, questions: array<int, int>, courses: list<int>}
	 */
	private function apply( Actor $actor, int $batch, array $manifest, array $plan ): array {
		$system  = (string) ( $manifest['source']['system'] ?? '' );
		$package = (string) ( $manifest['package_id'] ?? '' );
		$repo    = QuestionRepository::current();
		$out     = array(
			'summary'   => array(),
			'questions' => array(),
			'courses'   => array(),
		);
		foreach ( $plan['items'] as $item ) {
			$entity = $item['entity'];
			$type   = self::ENTITY_TYPES[ $entity ];
			$source = $item['source_id'];
			$action = $item['action'];
			$record = $plan['records'][ $entity ][ $source ] ?? array();
			$object = $this->mapped( $system, $type, $source );
			if ( 'archive' === $action && null !== $object ) {
				if ( 'question' === $type ) {
					$repo->archive( $actor, $object );
				} else {
					wp_update_post(
						array(
							'ID'          => $object,
							'post_status' => PostTypes::STATUS_ARCHIVED,
						)
					);
				}
			} elseif ( in_array( $action, array( 'create', 'update' ), true ) ) {
				$object = $this->write_object( $actor, $system, $type, $record, $object );
				if ( 'question' === $type ) {
					$out['questions'][ $object ] = (int) $this->db->scalar( $this->db->prepare( 'SELECT current_revision_id FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', $object ) );
					if ( isset( $this->saved[ $object ] ) ) {
						$out['questions'][ $object ] = $this->saved[ $object ];
					}
				}
				$this->map( $system, $type, $source, $object, (string) $record['hash'], $package, $batch );
			}
			if ( 'course' === $type && null !== $object && 'archive' !== $action && 'error' !== $action ) {
				$out['courses'][] = $object;
			}
			$out['summary'][ $entity ][ $action ] = ( $out['summary'][ $entity ][ $action ] ?? 0 ) + 1;
			$this->db->insert(
				'ccap_import_item_log',
				array(
					'batch_id'  => $batch,
					'entity'    => $entity,
					'source_id' => substr( $source, 0, 191 ),
					'object_id' => $object,
					'action'    => $action,
					'outcome'   => 'error' === $action ? 'error' : 'ok',
				),
				array( 'created_at' )
			);
		}
		return $out;
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
		if ( 'question' === $type ) {
			$repo = QuestionRepository::current();
			$data = array(
				'question_type' => (string) $record['revision']['question_type'],
				'question_text' => self::html( (string) ( $record['revision']['text_html'] ?? '' ) ),
				'settings'      => (array) ( $record['revision']['settings'] ?? array() ),
				'answers'       => self::answers( (string) $record['revision']['question_type'], array_values( (array) ( $record['answers'] ?? array() ) ) ),
				'tags'          => array_values( (array) ( $record['tags'] ?? array() ) ),
			);
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
	 * Activation: switch question revisions and publish course revisions.
	 *
	 * @param Actor                                                                                             $actor    Importer.
	 * @param array<string, mixed>                                                                              $manifest Manifest.
	 * @param array{summary: array<string, array<string, int>>, questions: array<int, int>, courses: list<int>} $applied Applied changes.
	 * @return void
	 */
	private function activate( Actor $actor, array $manifest, array $applied ): void {
		$repo = QuestionRepository::current();
		foreach ( $applied['questions'] as $question => $revision ) {
			$repo->publish( $actor, (int) $question, $revision > 0 ? $revision : null );
		}
		/* translators: %s: package ID */
		$note = sprintf( __( 'Import %s', 'course-captain' ), (string) ( $manifest['package_id'] ?? '' ) );
		foreach ( array_unique( $applied['courses'] ) as $course ) {
			CoursePublisher::current()->publish( $actor, $course, 'minor', $note );
		}
	}

	/**
	 * Earlier published batches of the same source system become superseded.
	 *
	 * @param int    $batch  Current batch.
	 * @param string $system Source system.
	 * @return void
	 */
	private function supersede( int $batch, string $system ): void {
		$table = $this->db->table( 'ccap_import_batch' );
		foreach ( $this->db->rows( "SELECT id, manifest FROM `{$table}` WHERE status = 'published'" ) as $row ) {
			$m = (array) json_decode( (string) $row['manifest'], true );
			if ( (int) $row['id'] !== $batch && ( $m['source']['system'] ?? '' ) === $system ) {
				$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET status = 'superseded' WHERE id = %d", (int) $row['id'] ) );
			}
		}
	}

	/**
	 * Records per entity keyed by source_id (`_error` on V6 schema problems).
	 *
	 * @param string $file Package.
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private function read( string $file ): array {
		$out = array_fill_keys( array_keys( self::ENTITY_TYPES ), array() );
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file, \ZipArchive::RDONLY ) ) {
			return $out;
		}
		$names = array();
		$count = $zip->count();
		for ( $i = 0; $i < $count; $i++ ) {
			$names[] = (string) $zip->getNameIndex( $i );
		}
		sort( $names, SORT_STRING );
		$dupes = 0;
		foreach ( $names as $name ) {
			if ( 1 !== preg_match( '#^data/(courses|modules|lessons|questions)/\d{6}\.json$#', $name, $m ) ) {
				continue;
			}
			foreach ( (array) json_decode( (string) $zip->getFromName( $name ), true ) as $record ) {
				$source = is_array( $record ) ? (string) ( $record['source_id'] ?? '' ) : '';
				if ( '' === $source || strlen( $source ) > 191 ) {
					$out[ $m[1] ][ 'invalid-' . ( ++$dupes ) ] = array( '_error' => 'schema' );
					continue;
				}
				if ( isset( $out[ $m[1] ][ $source ] ) ) {
					$out[ $m[1] ][ $source . '#dup' . ( ++$dupes ) ] = array( '_error' => 'duplicate_source_id' ) + $record;
					continue;
				}
				$out[ $m[1] ][ $source ] = $record;
			}
		}
		$zip->close();
		return $out;
	}

	/**
	 * References of a record by object type.
	 *
	 * @param string               $entity Entity.
	 * @param array<string, mixed> $record Record.
	 * @return array<string, list<string>>
	 */
	private static function references( string $entity, array $record ): array {
		$col = static fn( string $key, string $field ): array => array_values( array_map( static fn( $l ): string => (string) ( is_array( $l ) ? ( $l[ $field ] ?? '' ) : '' ), (array) ( $record[ $key ] ?? array() ) ) );
		switch ( $entity ) {
			case 'courses':
				return array( 'module' => $col( 'modules', 'module' ) );
			case 'modules':
				return array(
					'lesson'   => $col( 'lessons', 'lesson' ),
					'question' => $col( 'question_pool', 'question' ),
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
		$out = array();
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT object_type, object_id, source_id, last_hash, locally_modified FROM `' . $this->db->table( 'ccap_source_map' ) . '` WHERE source_system = %s', $system ) ) as $row ) {
			$type                                       = (string) $row['object_type'];
			$state                                      = 'question' === $type
				? (string) $this->db->scalar( $this->db->prepare( 'SELECT status FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', (int) $row['object_id'] ) )
				: (string) get_post_status( (int) $row['object_id'] );
			$out[ $type ][ (string) $row['source_id'] ] = $row + array( 'state' => in_array( $state, array( 'archived', PostTypes::STATUS_ARCHIVED, '' ), true ) ? 'archived' : 'active' );
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
	private function authorize( Actor $actor, int $batch, string $step, array $from ): array {
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
	private function move( int $batch, array $from, string $to, array $set ): void {
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
