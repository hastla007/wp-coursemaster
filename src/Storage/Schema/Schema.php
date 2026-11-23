<?php
/**
 * Schema of the Free core.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * All R1 core tables of R0-06 §4.1–§4.10 including the additions of the
 * follow-up tasks (R0-07, R0-08, R0-09, R0-13, R0-15, R0-19, R0-21, R0-23,
 * R0-27, R0-37, R0-38). Pro tables (`ccap_source_saga`, Woo mapping) are
 * not part of the core (E-31, E-34, E-53).
 *
 * Conventions (R0-06 §4.0): InnoDB + utf8mb4, `*_at` in UTC set by PHP (no
 * `CURRENT_TIMESTAMP` defaults), idempotency and unique columns `NOT NULL`
 * with `''`/`0` as neutral value, JSON as `longtext` validated in PHP,
 * references to posts and users without foreign keys (E-48).
 *
 * Generated columns with their UNIQUE keys (`ccap_exam_session.running`,
 * `ccap_seat.active_user`, R0-21 D21-4) and the foreign keys are added by
 * numbered migration steps because dbDelta cannot handle them.
 */
final class Schema {

	/** Schema version of this code (semver, R0-06 §6.1). */
	public const VERSION = '1.0.0';

	/** Lowest code version that may read and write this schema (E-90, R0-37 §6.2). */
	public const COMPAT_MIN = '1.0.0';

	/** Prefix of the ownership marker in the table comment (KOMP-131). */
	public const MARKER_PREFIX = 'course-captain:';

	/** Tables created in phase P0 before any lease exists (R0-37 §4.1). */
	public const BOOTSTRAP_TABLES = array( 'lease', 'migration_step' );

	private const ID       = 'bigint(20) unsigned NOT NULL AUTO_INCREMENT';
	private const REF      = 'bigint(20) unsigned NOT NULL';
	private const REF_NULL = 'bigint(20) unsigned NULL';
	private const REF_ZERO = "bigint(20) unsigned NOT NULL DEFAULT '0'";
	private const UID      = 'char(36) NOT NULL';
	private const DT       = 'datetime NOT NULL';
	private const DT_NULL  = 'datetime NULL';
	private const HASH     = 'char(71) NOT NULL';
	private const TEXT     = 'longtext NOT NULL';
	private const TEXT_NUL = 'longtext NULL';
	private const FLAG_0   = "tinyint(1) NOT NULL DEFAULT '0'";
	private const FLAG_1   = "tinyint(1) NOT NULL DEFAULT '1'";
	private const FLAG_NUL = 'tinyint(1) NULL';
	private const SORT     = "int(11) NOT NULL DEFAULT '0'";
	private const UINT     = 'int(10) unsigned NOT NULL';
	private const UINT_0   = "int(10) unsigned NOT NULL DEFAULT '0'";
	private const SMALL_0  = "smallint(5) unsigned NOT NULL DEFAULT '0'";
	private const SMALL_1  = "smallint(5) unsigned NOT NULL DEFAULT '1'";
	private const ALGO     = 'smallint(5) unsigned NOT NULL';
	private const RATIO    = "decimal(5,4) NOT NULL DEFAULT '0.0000'";

	/**
	 * Cached definitions.
	 *
	 * @var array<string, Table>|null
	 */
	private static ?array $tables = null;

	/**
	 * All core tables keyed by short name.
	 *
	 * @return array<string, Table>
	 */
	public static function tables(): array {
		if ( null === self::$tables ) {
			self::$tables = array();
			foreach ( self::define() as $table ) {
				self::$tables[ $table->name ] = $table;
			}
		}
		return self::$tables;
	}

	/**
	 * One table definition.
	 *
	 * @param string $name Short name.
	 * @return Table
	 */
	public static function table( string $name ): Table {
		$tables = self::tables();
		if ( ! isset( $tables[ $name ] ) ) {
			throw new \InvalidArgumentException( 'Unknown table ' . $name ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message.
		}
		return $tables[ $name ];
	}

	/**
	 * Short table names in drop order: referencing (child) tables first (R0-16 §2.5 Nr. 3).
	 *
	 * @return list<string>
	 */
	public static function drop_order(): array {
		$tables  = self::tables();
		$order   = array();
		$visited = array();
		$visit   = static function ( string $name ) use ( &$visit, &$order, &$visited, $tables ): void {
			if ( isset( $visited[ $name ] ) ) {
				return;
			}
			$visited[ $name ] = true;
			foreach ( $tables as $child ) {
				foreach ( $child->foreign as $fk ) {
					if ( $fk->ref_table === $name && $child->name !== $name ) {
						$visit( $child->name );
					}
				}
			}
			$order[] = $name;
		};
		foreach ( array_keys( $tables ) as $name ) {
			$visit( $name );
		}
		return $order;
	}

	/**
	 * Hash of all table definitions (part of `course_captain_db_version`, R0-06 §6.1).
	 *
	 * @return string
	 */
	public static function definition_hash(): string {
		$parts = array();
		foreach ( self::tables() as $table ) {
			$parts[] = $table->create_sql( '', '' );
			foreach ( $table->foreign as $fk ) {
				$parts[] = $fk->add_sql( '', $table->name );
			}
		}
		return sha1( implode( "\n", $parts ) );
	}

	/**
	 * UID macro (R0-06 §4.0).
	 *
	 * @return array<string, string>
	 */
	private static function uid(): array {
		return array( 'uid' => self::UID );
	}

	/**
	 * SRC macro (R0-06 §4.0).
	 *
	 * @return array<string, string>
	 */
	private static function src(): array {
		return array(
			'source_system' => "varchar(32) NOT NULL DEFAULT 'local'",
			'source_id'     => 'varchar(191) NULL',
			'source_hash'   => 'char(71) NULL',
			'package_id'    => 'varchar(64) NULL',
			'imported_at'   => self::DT_NULL,
		);
	}

	/**
	 * TS macro (R0-06 §4.0).
	 *
	 * @return array<string, string>
	 */
	private static function ts(): array {
		return array(
			'created_at' => self::DT,
			'updated_at' => self::DT,
		);
	}

	/**
	 * CAS macro (R0-06 §4.0).
	 *
	 * @return array<string, string>
	 */
	private static function cas(): array {
		return array( 'row_version' => self::UINT_0 );
	}

	/**
	 * Shorthand for a foreign key.
	 *
	 * @param string $column    Column.
	 * @param string $ref_table Referenced table.
	 * @param string $on_delete Rule.
	 * @param string $ref_col   Referenced column.
	 * @return ForeignKey
	 */
	private static function fk( string $column, string $ref_table, string $on_delete, string $ref_col = 'id' ): ForeignKey {
		return new ForeignKey( $column, $ref_table, $ref_col, $on_delete );
	}

	/**
	 * The definitions.
	 *
	 * @return list<Table>
	 */
	private static function define(): array {
		$r = ForeignKey::RESTRICT;
		$c = ForeignKey::CASCADE;
		$n = ForeignKey::SET_NULL;

		return array(
			// §4.1 Content.
			new Table(
				'source_map',
				array( 'id' => self::ID ) + self::uid() + array(
					'object_type' => 'varchar(32) NOT NULL',
					'object_id'   => self::REF,
				) + self::src() + array(
					'locally_modified' => self::FLAG_0,
					'last_batch_id'    => self::REF_NULL,
					'last_hash'        => 'char(71) NULL',
				) + self::ts(),
				array( 'id' ),
				array(
					'uid'    => array( 'uid' ),
					'source' => array( 'source_system', 'source_id', 'object_type' ),
					'object' => array( 'object_type', 'object_id' ),
				)
			),
			new Table(
				'course_module',
				array(
					'id'         => self::ID,
					'course_id'  => self::REF,
					'module_id'  => self::REF,
					'sort_order' => self::SORT,
					'required'   => self::FLAG_1,
					'rules'      => self::TEXT_NUL,
				),
				array( 'id' ),
				array( 'course_module' => array( 'course_id', 'module_id' ) ),
				array(
					'course_sort' => array( 'course_id', 'sort_order' ),
					'module_id'   => array( 'module_id' ),
				)
			),
			new Table(
				'course_relation',
				array(
					'id'                => self::ID,
					'course_id'         => self::REF,
					'related_course_id' => self::REF,
					'relation_type'     => "varchar(16) NOT NULL DEFAULT 'contains'",
					'sort_order'        => self::SORT,
					'created_by'        => self::REF_NULL,
				) + self::ts(),
				array( 'id' ),
				array( 'course_relation' => array( 'course_id', 'related_course_id', 'relation_type' ) ),
				array( 'related' => array( 'related_course_id', 'relation_type' ) )
			),
			new Table(
				'course_revision',
				array(
					'id'             => self::ID,
					'course_id'      => self::REF,
					'revision_no'    => self::UINT,
					'status'         => 'varchar(16) NOT NULL',
					'change_class'   => 'varchar(8) NULL',
					'notes'          => self::TEXT_NUL,
					'structure'      => self::TEXT,
					'structure_hash' => self::HASH,
					'format_version' => 'smallint(5) unsigned NOT NULL',
					'published_by'   => self::REF_NULL,
					'published_at'   => self::DT,
				),
				array( 'id' ),
				array( 'course_rev' => array( 'course_id', 'revision_no' ) ),
				array( 'course_status' => array( 'course_id', 'status' ) )
			),
			new Table(
				'question',
				array( 'id' => self::ID ) + self::uid() + self::src() + array(
					'reference_no'        => 'varchar(32) NULL',
					'language'            => 'varchar(20) NOT NULL',
					'status'              => "varchar(16) NOT NULL DEFAULT 'draft'",
					'current_revision_id' => self::REF_NULL,
					'author_user_id'      => self::REF_NULL,
				) + self::ts(),
				array( 'id' ),
				array(
					'uid'    => array( 'uid' ),
					'source' => array( 'source_system', 'source_id' ),
				),
				array(
					'status'              => array( 'status' ),
					'current_revision_id' => array( 'current_revision_id' ),
					'reference_no'        => array( 'reference_no' ),
				)
			),
			new Table(
				'question_revision',
				array(
					'id'                 => self::ID,
					'question_id'        => self::REF,
					'revision_no'        => self::UINT,
					'content_hash'       => self::HASH,
					'package_id'         => 'varchar(64) NULL',
					'source_version'     => 'varchar(64) NULL',
					'question_type'      => 'varchar(32) NOT NULL',
					'question_text'      => self::TEXT,
					'settings'           => self::TEXT_NUL,
					'feedback_correct'   => self::TEXT_NUL,
					'feedback_incorrect' => self::TEXT_NUL,
					'image_required'     => self::FLAG_0,
					'editorial_status'   => "varchar(20) NOT NULL DEFAULT 'draft'",
					'change_class'       => 'varchar(16) NULL',
					'rights_status'      => 'varchar(20) NOT NULL',
					'rights_owner'       => 'varchar(255) NULL',
					'license_reference'  => self::TEXT_NUL,
					'valid_from'         => 'date NULL',
					'valid_to'           => 'date NULL',
					'created_by'         => self::REF_NULL,
					'created_at'         => self::DT,
					'published_at'       => self::DT_NULL,
				),
				array( 'id' ),
				array( 'question_rev' => array( 'question_id', 'revision_no' ) ),
				array(
					'question_hash' => array( 'question_id', 'content_hash' ),
					'publish'       => array( 'question_id', 'editorial_status', 'valid_from', 'valid_to' ),
				),
				array( self::fk( 'question_id', 'question', $r ) )
			),
			new Table(
				'question_tag',
				array(
					'id'          => self::ID,
					'revision_id' => self::REF,
					'tag_type'    => 'varchar(32) NOT NULL',
					'tag_key'     => 'varchar(191) NOT NULL',
					'label'       => 'varchar(255) NOT NULL',
				),
				array( 'id' ),
				array( 'rev_tag' => array( 'revision_id', 'tag_type', 'tag_key' ) ),
				array( 'tag' => array( 'tag_type', 'tag_key' ) ),
				array( self::fk( 'revision_id', 'question_revision', $c ) )
			),
			new Table(
				'answer',
				array(
					'id'          => self::ID,
					'revision_id' => self::REF,
					'answer_key'  => 'varchar(16) NOT NULL',
					'answer_text' => self::TEXT,
					'is_correct'  => self::FLAG_0,
					'sort_order'  => self::SORT,
				),
				array( 'id' ),
				array( 'revision_key' => array( 'revision_id', 'answer_key' ) ),
				array(),
				array( self::fk( 'revision_id', 'question_revision', $c ) )
			),
			new Table(
				'module_question',
				array(
					'id'          => self::ID,
					'module_id'   => self::REF,
					'question_id' => self::REF,
					'sort_order'  => 'int(11) NULL',
					'required'    => self::FLAG_1,
				),
				array( 'id' ),
				array( 'module_question' => array( 'module_id', 'question_id' ) ),
				array( 'question_id' => array( 'question_id' ) ),
				array( self::fk( 'question_id', 'question', $c ) )
			),
			new Table(
				'activity_question',
				array(
					'id'          => self::ID,
					'activity_id' => self::REF,
					'question_id' => self::REF,
					'sort_order'  => self::SORT,
				),
				array( 'id' ),
				array( 'activity_question' => array( 'activity_id', 'question_id' ) ),
				array( 'question_id' => array( 'question_id' ) ),
				array( self::fk( 'question_id', 'question', $r ) )
			),
			new Table(
				'media',
				array( 'id' => self::ID ) + self::uid() + self::src() + array(
					'attachment_id'     => self::REF_NULL,
					'storage_key'       => 'varchar(255) NULL',
					'package_path'      => 'varchar(255) NULL',
					'media_type'        => "varchar(20) NOT NULL DEFAULT 'image'",
					'mime_type'         => 'varchar(100) NOT NULL',
					'size_bytes'        => "bigint(20) unsigned NOT NULL DEFAULT '0'",
					'sha256'            => 'char(64) NOT NULL',
					'access'            => "varchar(16) NOT NULL DEFAULT 'protected'",
					'alt_text'          => 'varchar(255) NULL',
					'caption'           => self::TEXT_NUL,
					'description'       => self::TEXT_NUL,
					'source_text'       => self::TEXT_NUL,
					'source_url'        => 'varchar(2048) NULL',
					'rights_owner'      => 'varchar(255) NULL',
					'license_reference' => self::TEXT_NUL,
					'rights_status'     => 'varchar(20) NOT NULL',
					'status'            => "varchar(16) NOT NULL DEFAULT 'active'",
				) + self::ts(),
				array( 'id' ),
				array(
					'uid'           => array( 'uid' ),
					'source'        => array( 'source_system', 'source_id' ),
					'attachment_id' => array( 'attachment_id' ),
				),
				array(
					'sha256'        => array( 'sha256' ),
					'rights_status' => array( 'rights_status' ),
				)
			),
			new Table(
				'content_media',
				array(
					'id'               => self::ID,
					'object_type'      => 'varchar(32) NOT NULL',
					'object_id'        => self::REF,
					'media_id'         => self::REF,
					'role'             => 'varchar(32) NOT NULL',
					'sort_order'       => self::SORT,
					'caption_override' => self::TEXT_NUL,
					'alt_override'     => 'varchar(255) NULL',
				),
				array( 'id' ),
				array( 'object_media' => array( 'object_type', 'object_id', 'media_id', 'role' ) ),
				array( 'media_id' => array( 'media_id' ) ),
				array( self::fk( 'media_id', 'media', $r ) )
			),

			// §4.2 Exams.
			new Table(
				'exam_rule',
				array( 'id' => self::ID ) + self::uid() + self::src() + array(
					'course_id'          => self::REF,
					'version'            => 'varchar(64) NOT NULL',
					'status'             => "varchar(16) NOT NULL DEFAULT 'draft'",
					'valid_from'         => 'date NULL',
					'valid_until'        => 'date NULL',
					'time_limit_seconds' => 'int(10) unsigned NULL',
					'passing_rule'       => self::TEXT,
					'settings'           => self::TEXT_NUL,
					'published_by'       => self::REF_NULL,
					'published_at'       => self::DT_NULL,
				) + self::ts(),
				array( 'id' ),
				array(
					'uid'            => array( 'uid' ),
					'source'         => array( 'source_system', 'source_id' ),
					'course_version' => array( 'course_id', 'version' ),
				),
				array( 'course_status' => array( 'course_id', 'status' ) )
			),
			new Table(
				'exam_blueprint',
				array(
					'id'             => self::ID,
					'rule_id'        => self::REF,
					'module_id'      => self::REF,
					'question_count' => self::UINT,
					'rules'          => self::TEXT_NUL,
				),
				array( 'id' ),
				array( 'rule_module' => array( 'rule_id', 'module_id' ) ),
				array( 'module_id' => array( 'module_id' ) ),
				array( self::fk( 'rule_id', 'exam_rule', $c ) )
			),
			new Table(
				'exam_variant',
				array( 'id' => self::ID ) + self::uid() + self::src() + array(
					'course_id'  => self::REF,
					'variant_no' => self::UINT,
					'status'     => "varchar(16) NOT NULL DEFAULT 'draft'",
					'sort_order' => self::SORT,
				) + self::ts(),
				array( 'id' ),
				array(
					'uid'            => array( 'uid' ),
					'source'         => array( 'source_system', 'source_id' ),
					'course_variant' => array( 'course_id', 'variant_no' ),
				)
			),
			new Table(
				'exam_variant_question',
				array(
					'id'          => self::ID,
					'variant_id'  => self::REF,
					'question_id' => self::REF,
					'position'    => self::UINT,
				),
				array( 'id' ),
				array( 'variant_position' => array( 'variant_id', 'position' ) ),
				array( 'question_id' => array( 'question_id' ) ),
				array(
					self::fk( 'variant_id', 'exam_variant', $c ),
					self::fk( 'question_id', 'question', $r ),
				)
			),
			new Table(
				'exam_session',
				array( 'id' => self::ID ) + self::uid() + array(
					'user_id'            => self::REF,
					'course_id'          => self::REF,
					'course_revision_id' => self::REF_NULL,
					'rule_id'            => self::REF,
					'variant_id'         => self::REF_NULL,
					'activity_uid'       => 'char(36) NULL',
					'mode'               => 'varchar(16) NOT NULL',
					'form_key'           => 'varchar(64) NOT NULL',
					'status'             => 'varchar(16) NOT NULL',
					'end_reason'         => 'varchar(24) NULL',
					'start_token'        => self::UID,
					'snapshot'           => self::TEXT,
					'snapshot_hash'      => self::HASH,
					'seed'               => 'bigint(20) unsigned NULL',
					'evaluator_version'  => 'varchar(32) NOT NULL',
					'evaluation_no'      => self::SMALL_0,
					'started_at'         => self::DT,
					'deadline_at'        => self::DT_NULL,
					'submitted_at'       => self::DT_NULL,
					'evaluated_at'       => self::DT_NULL,
					'points_milli'       => 'int(11) NULL',
					'max_points_milli'   => 'int(11) NULL',
					'passed'             => self::FLAG_NUL,
					'result'             => self::TEXT_NUL,
				) + self::cas(),
				array( 'id' ),
				array(
					'uid'         => array( 'uid' ),
					'start_token' => array( 'start_token' ),
				),
				array(
					'user_course'        => array( 'user_id', 'course_id', 'status' ),
					'status_deadline'    => array( 'status', 'deadline_at' ),
					'rule_id'            => array( 'rule_id' ),
					'variant_id'         => array( 'variant_id' ),
					'course_revision_id' => array( 'course_revision_id' ),
				),
				array(
					self::fk( 'rule_id', 'exam_rule', $r ),
					self::fk( 'variant_id', 'exam_variant', $r ),
					self::fk( 'course_revision_id', 'course_revision', $r ),
				)
			),
			new Table(
				'exam_session_question',
				array(
					'id'                  => self::ID,
					'session_id'          => self::REF,
					'position'            => self::UINT,
					'question_id'         => self::REF,
					'revision_id'         => self::REF,
					'answer_order'        => self::TEXT_NUL,
					'given_answer'        => self::TEXT_NUL,
					'self_assessed_known' => self::FLAG_NUL,
					'revealed_at'         => self::DT_NULL,
					'is_correct'          => self::FLAG_NUL,
					'answered_at'         => self::DT_NULL,
					'flagged'             => self::FLAG_0,
				),
				array( 'id' ),
				array( 'session_position' => array( 'session_id', 'position' ) ),
				array(
					'question_id' => array( 'question_id' ),
					'revision_id' => array( 'revision_id' ),
				),
				array(
					self::fk( 'session_id', 'exam_session', $c ),
					self::fk( 'question_id', 'question', $r ),
					self::fk( 'revision_id', 'question_revision', $r ),
				)
			),

			// §4.3 Learning.
			new Table(
				'attempt',
				array(
					'id'                => self::ID,
					'user_id'           => self::REF,
					'question_id'       => self::REF,
					'revision_id'       => self::REF,
					'course_id'         => self::REF_NULL,
					'context'           => 'varchar(32) NOT NULL',
					'context_ref'       => 'varchar(64) NULL',
					'is_correct'        => self::FLAG_NUL,
					'self_assessment'   => 'varchar(16) NULL',
					'answer_ms'         => 'int(10) unsigned NULL',
					'idempotency_key'   => 'char(36) NOT NULL',
					'correction_of'     => self::REF_NULL,
					'algorithm_version' => self::ALGO,
					'created_at'        => self::DT,
				),
				array( 'id' ),
				array( 'user_idem' => array( 'user_id', 'idempotency_key' ) ),
				array(
					'user_question'  => array( 'user_id', 'question_id' ),
					'user_created'   => array( 'user_id', 'created_at' ),
					'question_id'    => array( 'question_id' ),
					'revision_id'    => array( 'revision_id' ),
					'course_created' => array( 'course_id', 'created_at' ),
				),
				array(
					self::fk( 'question_id', 'question', $r ),
					self::fk( 'revision_id', 'question_revision', $r ),
				)
			),
			new Table(
				'attempt_answer',
				array(
					'id'         => self::ID,
					'attempt_id' => self::REF,
					'answer_id'  => self::REF_NULL,
					'answer_key' => 'varchar(16) NULL',
					'free_text'  => self::TEXT_NUL,
					'position'   => self::SORT,
				),
				array( 'id' ),
				array(),
				array(
					'attempt_id' => array( 'attempt_id' ),
					'answer_id'  => array( 'answer_id' ),
				),
				array( self::fk( 'attempt_id', 'attempt', $c ) )
			),
			new Table(
				'question_progress',
				array(
					'id'                => self::ID,
					'user_id'           => self::REF,
					'question_id'       => self::REF,
					'attempts'          => self::UINT_0,
					'correct_count'     => self::UINT_0,
					'wrong_count'       => self::UINT_0,
					'streak'            => self::UINT_0,
					'mastery_score'     => self::RATIO,
					'learn_status'      => "varchar(16) NOT NULL DEFAULT 'new'",
					'algorithm_version' => self::ALGO,
					'first_answered_at' => self::DT_NULL,
					'last_answered_at'  => self::DT_NULL,
					'due_at'            => self::DT_NULL,
				) + self::cas(),
				array( 'id' ),
				array( 'user_question' => array( 'user_id', 'question_id' ) ),
				array(
					'question_id' => array( 'question_id' ),
					'user_status' => array( 'user_id', 'learn_status' ),
					'user_due'    => array( 'user_id', 'due_at' ),
				),
				array( self::fk( 'question_id', 'question', $r ) )
			),
			new Table(
				'activity_progress',
				array(
					'id'               => self::ID,
					'user_id'          => self::REF,
					'activity_id'      => self::REF,
					'course_id'        => self::REF_NULL,
					'activity_type'    => 'varchar(32) NOT NULL',
					'status'           => 'varchar(16) NOT NULL',
					'reason_code'      => 'varchar(32) NULL',
					'rule_version'     => 'smallint(5) unsigned NULL',
					'position_seconds' => self::UINT_0,
					'data'             => self::TEXT_NUL,
					'content_hash'     => 'char(71) NULL',
					'completed_at'     => self::DT_NULL,
					'last_access_at'   => self::DT,
					'created_at'       => self::DT,
				),
				array( 'id' ),
				array( 'user_activity' => array( 'user_id', 'activity_id' ) ),
				array(
					'activity_id' => array( 'activity_id' ),
					'user_course' => array( 'user_id', 'course_id' ),
				)
			),
			new Table(
				'course_progress',
				array(
					'id'                    => self::ID,
					'user_id'               => self::REF,
					'course_id'             => self::REF,
					'course_revision_id'    => self::REF_NULL,
					'completed_revision_id' => self::REF_NULL,
					'cycle_no'              => self::SMALL_1,
					'status'                => "varchar(16) NOT NULL DEFAULT 'not_started'",
					'completion_ratio'      => self::RATIO,
					'mastery_ratio'         => 'decimal(5,4) NULL',
					'activities_done'       => self::UINT_0,
					'activities_total'      => self::UINT_0,
					'started_at'            => self::DT_NULL,
					'completed_at'          => self::DT_NULL,
					'last_activity_at'      => self::DT_NULL,
					'updated_at'            => self::DT,
				) + self::cas(),
				array( 'id' ),
				array( 'user_course' => array( 'user_id', 'course_id' ) ),
				array(
					'course_status'         => array( 'course_id', 'status' ),
					'user_last'             => array( 'user_id', 'last_activity_at' ),
					'course_rev'            => array( 'course_id', 'course_revision_id' ),
					'course_revision_id'    => array( 'course_revision_id' ),
					'completed_revision_id' => array( 'completed_revision_id' ),
				),
				array(
					self::fk( 'course_revision_id', 'course_revision', $r ),
					self::fk( 'completed_revision_id', 'course_revision', $r ),
				)
			),
			new Table(
				'favorite',
				array(
					'id'          => self::ID,
					'user_id'     => self::REF,
					'question_id' => self::REF,
					'context'     => "varchar(32) NOT NULL DEFAULT 'question'",
					'created_at'  => self::DT,
				),
				array( 'id' ),
				array( 'user_question_context' => array( 'user_id', 'question_id', 'context' ) ),
				array( 'question_id' => array( 'question_id' ) ),
				array( self::fk( 'question_id', 'question', $c ) )
			),
			new Table(
				'collection',
				array(
					'id'         => self::ID,
					'user_id'    => self::REF,
					'course_id'  => self::REF,
					'name'       => 'varchar(80) NOT NULL',
					'created_at' => self::DT,
				),
				array( 'id' ),
				array(),
				array(
					'user_course' => array( 'user_id', 'course_id' ),
					'course_id'   => array( 'course_id' ),
				)
			),
			new Table(
				'collection_item',
				array(
					'collection_id' => self::REF,
					'question_id'   => self::REF,
					'created_at'    => self::DT,
				),
				array( 'collection_id', 'question_id' ),
				array(),
				array( 'question_id' => array( 'question_id' ) ),
				array(
					self::fk( 'collection_id', 'collection', $c ),
					self::fk( 'question_id', 'question', $c ),
				)
			),

			// §4.4 Access and groups.
			new Table(
				'grant',
				array( 'id' => self::ID ) + self::uid() + array(
					'user_id'              => self::REF_NULL,
					'subject_ref'          => 'char(64) NULL',
					'course_id'            => self::REF,
					'module_id'            => self::REF_ZERO,
					'scopes'               => "varchar(64) NOT NULL DEFAULT 'all'",
					'depends_on_course_id' => self::REF_ZERO,
					'status'               => "varchar(24) NOT NULL DEFAULT 'pending_confirmation'",
					'source_type'          => 'varchar(32) NOT NULL',
					'source_id'            => 'varchar(191) NOT NULL',
					'source_line'          => "varchar(64) NOT NULL DEFAULT ''",
					'source_revision'      => "int(10) unsigned NOT NULL DEFAULT '1'",
					'source_seq'           => "bigint(20) unsigned NOT NULL DEFAULT '0'",
					'source_plugin'        => 'varchar(64) NULL',
					'seat_id'              => self::REF_NULL,
					'valid_from'           => self::DT_NULL,
					'valid_until'          => self::DT_NULL,
					'valid_until_locked'   => self::FLAG_0,
					'manual_override'      => 'varchar(16) NULL',
					'paid_at'              => self::DT_NULL,
					'chain_anchor_at'      => self::DT_NULL,
					'renewal_mode'         => "varchar(16) NOT NULL DEFAULT 'extend'",
					'revocation_reason'    => 'varchar(64) NULL',
					'granted_by'           => self::REF_NULL,
					'reason'               => self::TEXT_NUL,
					'meta'                 => self::TEXT_NUL,
				) + self::cas() + self::ts(),
				array( 'id' ),
				array(
					'uid'          => array( 'uid' ),
					'grant_source' => array( 'source_type', 'source_id', 'source_line', 'source_revision', 'course_id', 'module_id' ),
				),
				array(
					'user_access'   => array( 'user_id', 'course_id', 'module_id', 'status', 'valid_until' ),
					'status_until'  => array( 'status', 'valid_until' ),
					'course_status' => array( 'course_id', 'status' ),
					'chain'         => array( 'user_id', 'course_id', 'renewal_mode', 'chain_anchor_at' ),
					'depends'       => array( 'user_id', 'depends_on_course_id' ),
					'seat_id'       => array( 'seat_id' ),
				),
				array( self::fk( 'seat_id', 'seat', $n ) )
			),
			new Table(
				'grant_event',
				array(
					'id'              => self::ID,
					'grant_id'        => self::REF,
					'event_type'      => 'varchar(32) NOT NULL',
					'from_status'     => 'varchar(24) NULL',
					'to_status'       => 'varchar(24) NULL',
					'reason_code'     => 'varchar(64) NOT NULL',
					'actor_type'      => 'varchar(16) NOT NULL',
					'actor_id'        => self::REF_NULL,
					'source_event_id' => 'varchar(191) NULL',
					'correlation_id'  => 'char(36) NULL',
					'details'         => self::TEXT_NUL,
					'occurred_at'     => self::DT,
					'created_at'      => self::DT,
				),
				array( 'id' ),
				array( 'grant_source_event' => array( 'grant_id', 'source_event_id' ) ),
				array(
					'grant_created'  => array( 'grant_id', 'created_at' ),
					'correlation_id' => array( 'correlation_id' ),
				),
				array( self::fk( 'grant_id', 'grant', $c ) )
			),
			new Table(
				'access_rule',
				array(
					'id'          => self::ID,
					'course_id'   => self::REF,
					'module_id'   => self::REF_NULL,
					'scope'       => 'varchar(32) NOT NULL',
					'access_type' => 'varchar(16) NOT NULL',
					'limit_type'  => 'varchar(32) NULL',
					'limit_value' => 'int(11) NULL',
					'valid_from'  => self::DT_NULL,
					'valid_until' => self::DT_NULL,
				) + self::ts(),
				array( 'id' ),
				array(),
				array( 'course_scope' => array( 'course_id', 'scope' ) )
			),
			new Table(
				'group',
				array( 'id' => self::ID ) + self::uid() + array(
					'owner_user_id'   => self::REF,
					'name'            => 'varchar(190) NOT NULL',
					'seats'           => self::UINT_0,
					'status'          => "varchar(16) NOT NULL DEFAULT 'active'",
					'claim_policy'    => "varchar(24) NOT NULL DEFAULT 'transferable'",
					'valid_from'      => self::DT_NULL,
					'valid_until'     => self::DT_NULL,
					'source_type'     => "varchar(32) NOT NULL DEFAULT 'manual'",
					'source_id'       => 'varchar(191) NULL',
					'source_revision' => "int(10) unsigned NOT NULL DEFAULT '1'",
					'join_token_hash' => 'char(64) NULL',
					'reason'          => self::TEXT_NUL,
				) + self::cas() + self::ts(),
				array( 'id' ),
				array(
					'uid'             => array( 'uid' ),
					'group_source'    => array( 'source_type', 'source_id' ),
					'join_token_hash' => array( 'join_token_hash' ),
				),
				array(
					'owner'  => array( 'owner_user_id' ),
					'status' => array( 'status' ),
				)
			),
			new Table(
				'group_course',
				array(
					'id'        => self::ID,
					'group_id'  => self::REF,
					'course_id' => self::REF,
					'module_id' => self::REF_ZERO,
					'scopes'    => "varchar(64) NOT NULL DEFAULT 'all'",
				),
				array( 'id' ),
				array( 'group_course' => array( 'group_id', 'course_id', 'module_id' ) ),
				array( 'course_id' => array( 'course_id' ) ),
				array( self::fk( 'group_id', 'group', $c ) )
			),
			new Table(
				'seat',
				array(
					'id'               => self::ID,
					'group_id'         => self::REF,
					'user_id'          => self::REF_NULL,
					'invite_email'     => 'varchar(190) NULL',
					'status'           => "varchar(16) NOT NULL DEFAULT 'invited'",
					'claim_token_hash' => 'char(64) NULL',
					'claim_expires_at' => self::DT_NULL,
					'invited_at'       => self::DT_NULL,
					'claimed_at'       => self::DT_NULL,
					'removed_at'       => self::DT_NULL,
				) + self::ts(),
				array( 'id' ),
				array( 'claim_token_hash' => array( 'claim_token_hash' ) ),
				array(
					'group_status'   => array( 'group_id', 'status' ),
					'status_invited' => array( 'status', 'invited_at' ),
					'user_id'        => array( 'user_id' ),
					'invite_email'   => array( 'invite_email' ),
				),
				array( self::fk( 'group_id', 'group', $c ) )
			),

			// §4.4a Text and file assignments (E-168).
			new Table(
				'assignment_submission',
				array( 'id' => self::ID ) + self::uid() + array(
					'user_id'            => self::REF,
					'course_id'          => self::REF,
					'activity_id'        => self::REF,
					'activity_uid'       => self::UID,
					'course_revision_id' => self::REF_NULL,
					'submission_no'      => self::SMALL_1,
					'status'             => "varchar(16) NOT NULL DEFAULT 'draft'",
					'text_answer'        => self::TEXT_NUL,
					'submitted_at'       => self::DT_NULL,
					'returned_at'        => self::DT_NULL,
					'graded_at'          => self::DT_NULL,
					'current_grade_id'   => self::REF_NULL,
				) + self::cas() + self::ts(),
				array( 'id' ),
				array(
					'uid'              => array( 'uid' ),
					'user_activity_no' => array( 'user_id', 'activity_id', 'submission_no' ),
				),
				array(
					'course_status'      => array( 'course_id', 'status' ),
					'activity_status'    => array( 'activity_id', 'status' ),
					'course_revision_id' => array( 'course_revision_id' ),
				),
				array( self::fk( 'course_revision_id', 'course_revision', $r ) )
			),
			new Table(
				'assignment_file',
				array(
					'id'            => self::ID,
					'submission_id' => self::REF,
					'media_id'      => self::REF,
					'original_name' => 'varchar(255) NOT NULL',
					'mime_type'     => 'varchar(100) NOT NULL',
					'size_bytes'    => 'bigint(20) unsigned NOT NULL',
					'sha256'        => 'char(64) NOT NULL',
					'sort_order'    => self::SORT,
					'created_at'    => self::DT,
				),
				array( 'id' ),
				array( 'submission_media' => array( 'submission_id', 'media_id' ) ),
				array( 'media_id' => array( 'media_id' ) ),
				array(
					self::fk( 'submission_id', 'assignment_submission', $c ),
					self::fk( 'media_id', 'media', $r ),
				)
			),
			new Table(
				'assignment_grade',
				array(
					'id'               => self::ID,
					'submission_id'    => self::REF,
					'evaluation_no'    => 'smallint(5) unsigned NOT NULL',
					'points_milli'     => 'int(11) NULL',
					'max_points_milli' => 'int(11) NULL',
					'passed'           => self::FLAG_NUL,
					'feedback'         => self::TEXT_NUL,
					'graded_by'        => self::REF,
					'graded_at'        => self::DT,
					'reason'           => self::TEXT_NUL,
					'created_at'       => self::DT,
				),
				array( 'id' ),
				array( 'submission_eval' => array( 'submission_id', 'evaluation_no' ) ),
				array( 'graded_by' => array( 'graded_by' ) ),
				array( self::fk( 'submission_id', 'assignment_submission', $r ) )
			),

			// §4.5 Certificates.
			new Table(
				'certificate',
				array(
					'id'                 => self::ID,
					'certificate_uid'    => self::UID,
					'revision_no'        => self::SMALL_1,
					'user_id'            => self::REF,
					'course_id'          => self::REF,
					'course_revision_id' => self::REF,
					'evidence_type'      => 'varchar(32) NOT NULL',
					'evidence_id'        => self::REF,
					'status'             => 'varchar(16) NOT NULL',
					'supersedes_id'      => self::REF_NULL,
					'template_version'   => 'varchar(32) NOT NULL',
					'snapshot'           => self::TEXT,
					'snapshot_hash'      => self::HASH,
					'verify_token_hash'  => 'char(64) NOT NULL',
					'issued_at'          => self::DT,
					'issued_by'          => self::REF_NULL,
					'revoked_at'         => self::DT_NULL,
					'revoked_by'         => self::REF_NULL,
					'revoke_reason'      => self::TEXT_NUL,
					'created_at'         => self::DT,
				),
				array( 'id' ),
				array(
					'cert_rev'          => array( 'certificate_uid', 'revision_no' ),
					'evidence_rev'      => array( 'evidence_type', 'evidence_id', 'revision_no' ),
					'verify_token_hash' => array( 'verify_token_hash' ),
				),
				array(
					'user_course'        => array( 'user_id', 'course_id' ),
					'course_revision_id' => array( 'course_revision_id' ),
					'supersedes_id'      => array( 'supersedes_id' ),
				),
				array(
					self::fk( 'course_revision_id', 'course_revision', $r ),
					self::fk( 'supersedes_id', 'certificate', $r ),
				)
			),

			// §4.6 Integration (internal outbox only in Free, E-38/E-182).
			new Table(
				'inbox_event',
				array(
					'id'               => self::ID,
					'connector_id'     => 'varchar(64) NOT NULL',
					'event_id'         => 'varchar(191) NOT NULL',
					'contract_version' => 'varchar(16) NOT NULL',
					'event_type'       => 'varchar(64) NOT NULL',
					'correlation_id'   => 'char(36) NULL',
					'payload'          => self::TEXT,
					'payload_hash'     => 'char(64) NOT NULL',
					'status'           => 'varchar(16) NOT NULL',
					'attempts'         => self::SMALL_0,
					'next_attempt_at'  => self::DT_NULL,
					'locked_until'     => self::DT_NULL,
					'lock_owner'       => 'varchar(64) NULL',
					'last_error'       => 'text NULL',
					'occurred_at'      => self::DT,
					'received_at'      => self::DT,
					'processed_at'     => self::DT_NULL,
				),
				array( 'id' ),
				array( 'connector_event' => array( 'connector_id', 'event_id' ) ),
				array(
					'status_next'    => array( 'status', 'next_attempt_at' ),
					'correlation_id' => array( 'correlation_id' ),
				)
			),
			new Table(
				'outbox_event',
				array(
					'id'               => self::ID,
					'event_uuid'       => self::UID,
					'event_type'       => 'varchar(64) NOT NULL',
					'schema_version'   => 'varchar(16) NOT NULL',
					'contract_version' => 'varchar(16) NOT NULL',
					'object_type'      => 'varchar(32) NOT NULL',
					'object_id'        => self::UID,
					'object_version'   => self::UINT,
					'course_uid'       => 'char(36) NULL',
					'user_ref'         => 'varchar(32) NULL',
					'actor_type'       => 'varchar(16) NOT NULL',
					'actor_ref'        => 'varchar(64) NULL',
					'correlation_id'   => 'char(36) NULL',
					'causation_id'     => 'char(36) NULL',
					'idempotency_key'  => 'varchar(191) NOT NULL',
					'privacy_tags'     => 'varchar(191) NOT NULL',
					'payload'          => self::TEXT,
					'created_at'       => self::DT,
				),
				array( 'id' ),
				array( 'event_uuid' => array( 'event_uuid' ) ),
				array(
					'type_created' => array( 'event_type', 'created_at' ),
					'object'       => array( 'object_type', 'object_id' ),
					'created_at'   => array( 'created_at' ),
				)
			),
			new Table(
				'outbox_delivery',
				array(
					'id'               => self::ID,
					'event_uuid'       => self::UID,
					'subscriber_id'    => 'varchar(64) NOT NULL',
					'status'           => 'varchar(16) NOT NULL',
					'skip_reason'      => 'varchar(32) NULL',
					'manual_retries'   => self::SMALL_0,
					'discarded_at'     => self::DT_NULL,
					'attempts'         => self::SMALL_0,
					'next_attempt_at'  => self::DT_NULL,
					'locked_until'     => self::DT_NULL,
					'lock_owner'       => 'varchar(64) NULL',
					'last_error_class' => 'varchar(32) NULL',
					'last_error'       => 'text NULL',
					'created_at'       => self::DT,
					'delivered_at'     => self::DT_NULL,
				),
				array( 'id' ),
				array( 'event_subscriber' => array( 'event_uuid', 'subscriber_id' ) ),
				array( 'status_next' => array( 'status', 'next_attempt_at' ) ),
				array( self::fk( 'event_uuid', 'outbox_event', $c, 'event_uuid' ) )
			),

			// §4.7 Operations.
			new Table(
				'import_batch',
				array(
					'id'              => self::ID,
					'package_id'      => 'varchar(64) NOT NULL',
					'schema_version'  => 'varchar(16) NOT NULL',
					'source_revision' => 'varchar(64) NULL',
					'status'          => "varchar(20) NOT NULL DEFAULT 'uploaded'",
					'manifest'        => self::TEXT,
					'stats'           => self::TEXT_NUL,
					'storage_path'    => 'varchar(255) NULL',
					'error'           => self::TEXT_NUL,
					'heartbeat_at'    => self::DT_NULL,
					'created_by'      => self::REF_NULL,
					'approved_by'     => self::REF_NULL,
					'created_at'      => self::DT,
					'approved_at'     => self::DT_NULL,
					'published_at'    => self::DT_NULL,
				),
				array( 'id' ),
				array(),
				array(
					'package_id' => array( 'package_id' ),
					'status'     => array( 'status' ),
				)
			),
			new Table(
				'import_item_log',
				array(
					'id'         => self::ID,
					'batch_id'   => self::REF,
					'attempt'    => self::SMALL_1,
					'entity'     => 'varchar(32) NOT NULL',
					'source_id'  => 'varchar(191) NULL',
					'object_id'  => self::REF_NULL,
					'action'     => 'varchar(16) NOT NULL',
					'outcome'    => 'varchar(24) NULL',
					'message'    => self::TEXT_NUL,
					'created_at' => self::DT,
				),
				array( 'id' ),
				array(),
				array(
					'batch_entity'   => array( 'batch_id', 'entity' ),
					'action'         => array( 'action' ),
					'action_created' => array( 'action', 'created_at' ),
					'created_at'     => array( 'created_at' ),
				),
				array( self::fk( 'batch_id', 'import_batch', $c ) )
			),
			new Table(
				'import_issue',
				array(
					'id'          => self::ID,
					'batch_id'    => self::REF,
					'attempt'     => self::SMALL_1,
					'phase'       => 'varchar(24) NOT NULL',
					'entity'      => 'varchar(32) NOT NULL',
					'source_id'   => 'varchar(191) NULL',
					'uid'         => 'char(36) NULL',
					'file_ref'    => 'varchar(255) NULL',
					'path'        => 'varchar(255) NULL',
					'rule'        => 'varchar(64) NOT NULL',
					'severity'    => 'varchar(16) NOT NULL',
					'error_class' => 'varchar(32) NOT NULL',
					'action'      => 'varchar(32) NULL',
					'message_key' => 'varchar(64) NOT NULL',
					'params'      => self::TEXT_NUL,
					'decision'    => 'varchar(32) NULL',
					'fingerprint' => 'char(64) NOT NULL',
					'created_at'  => self::DT,
				),
				array( 'id' ),
				array(),
				array(
					'batch'          => array( 'batch_id', 'id' ),
					'batch_severity' => array( 'batch_id', 'severity', 'id' ),
					'batch_rule'     => array( 'batch_id', 'rule' ),
					'batch_entity'   => array( 'batch_id', 'entity', 'source_id' ),
				),
				array( self::fk( 'batch_id', 'import_batch', $c ) )
			),
			new Table(
				'audit_log',
				array(
					'id'              => self::ID,
					'actor_user_id'   => self::REF_NULL,
					'actor_type'      => 'varchar(16) NOT NULL',
					'subject_user_id' => self::REF_NULL,
					'action'          => 'varchar(64) NOT NULL',
					'reason_code'     => 'varchar(64) NULL',
					'object_type'     => 'varchar(32) NULL',
					'object_id'       => 'varchar(191) NULL',
					'reason'          => self::TEXT_NUL,
					'context'         => self::TEXT_NUL,
					'correlation_id'  => 'char(36) NULL',
					'request_id'      => 'varchar(40) NULL',
					'row_hash'        => 'char(64) NOT NULL',
					'created_at'      => self::DT,
				),
				array( 'id' ),
				array(),
				array(
					'actor'          => array( 'actor_user_id' ),
					'subject'        => array( 'subject_user_id' ),
					'object'         => array( 'object_type', 'object_id(64)' ),
					'action'         => array( 'action' ),
					'created_at'     => array( 'created_at' ),
					'correlation_id' => array( 'correlation_id' ),
				)
			),
			new Table(
				'override',
				array(
					'id'          => self::ID,
					'object_type' => 'varchar(32) NOT NULL',
					'object_id'   => self::REF,
					'field'       => 'varchar(32) NOT NULL',
					'value'       => 'varchar(255) NULL',
					'reason'      => self::TEXT,
					'user_id'     => self::REF_NULL,
					'created_at'  => self::DT,
				),
				array( 'id' ),
				array( 'object_field' => array( 'object_type', 'object_id', 'field' ) )
			),
			new Table(
				'rate_limit',
				array(
					'bucket_hash'  => 'char(64) NOT NULL',
					'window_start' => self::UINT,
					'hits'         => self::UINT_0,
					'expires_at'   => self::DT,
				),
				array( 'bucket_hash' ),
				array(),
				array( 'expires_at' => array( 'expires_at' ) )
			),
			new Table(
				'request_idempotency',
				array(
					'id'           => self::ID,
					'user_id'      => self::REF,
					'scope'        => 'varchar(64) NOT NULL',
					'idem_key'     => 'char(36) NOT NULL',
					'request_hash' => 'char(64) NOT NULL',
					'status_code'  => self::SMALL_0,
					'response'     => self::TEXT_NUL,
					'resource'     => 'varchar(64) NULL',
					'created_at'   => self::DT,
					'expires_at'   => self::DT,
				),
				array( 'id' ),
				array( 'user_scope_key' => array( 'user_id', 'scope', 'idem_key' ) ),
				array( 'expires_at' => array( 'expires_at' ) )
			),

			// §4.10 Tables from decisions and follow-up tasks.
			new Table(
				'course_staff',
				array(
					'id'          => self::ID,
					'course_id'   => self::REF,
					'user_id'     => self::REF,
					'staff_role'  => 'varchar(16) NOT NULL',
					'group_id'    => self::REF_ZERO,
					'assigned_by' => self::REF_NULL,
				) + self::ts(),
				array( 'id' ),
				array( 'course_user_role' => array( 'course_id', 'user_id', 'staff_role', 'group_id' ) ),
				array( 'user_id' => array( 'user_id' ) )
			),
			new Table(
				'learner_lock',
				array(
					'id'          => self::ID,
					'user_id'     => self::REF,
					'status'      => "varchar(16) NOT NULL DEFAULT 'active'",
					'reason_code' => 'varchar(32) NOT NULL',
					'reason'      => self::TEXT_NUL,
					'locked_from' => self::DT,
					'locked_until' => self::DT_NULL,
					'locked_by'   => self::REF_NULL,
					'lifted_at'   => self::DT_NULL,
					'lifted_by'   => self::REF_NULL,
					'audit_id'    => self::REF_NULL,
				) + self::ts(),
				array( 'id' ),
				array(),
				array(
					'user_status'   => array( 'user_id', 'status' ),
					'status_lifted' => array( 'status', 'lifted_at' ),
				)
			),
			new Table(
				'accommodation',
				array(
					'id'              => self::ID,
					'user_id'         => self::REF,
					'scope_type'      => 'varchar(16) NOT NULL',
					'scope_id'        => self::REF_ZERO,
					'time_factor_pct' => 'smallint(5) unsigned NULL',
					'unlimited'       => self::FLAG_0,
					'valid_from'      => self::DT_NULL,
					'valid_until'     => self::DT_NULL,
					'created_by'      => self::REF_NULL,
					'created_at'      => self::DT,
				),
				array( 'id' ),
				array(),
				array( 'user_scope' => array( 'user_id', 'scope_type', 'scope_id' ) )
			),
			new Table(
				'lease',
				array(
					'name'         => 'varchar(191) NOT NULL',
					'owner'        => 'varchar(64) NOT NULL',
					'fence'        => "bigint(20) unsigned NOT NULL DEFAULT '0'",
					'expires_at'   => self::DT,
					'heartbeat_at' => self::DT,
					'created_at'   => self::DT,
				),
				array( 'name' )
			),
			new Table(
				'migration_step',
				array(
					'step_id'        => 'varchar(96) NOT NULL',
					'module'         => 'varchar(32) NOT NULL',
					'kind'           => 'varchar(12) NOT NULL',
					'status'         => "varchar(10) NOT NULL DEFAULT 'pending'",
					'attempts'       => self::SMALL_0,
					'crash_count'    => self::SMALL_0,
					'fence'          => "bigint(20) unsigned NOT NULL DEFAULT '0'",
					'batch_cursor'   => 'varchar(191) NULL',
					'rows_done'      => "bigint(20) unsigned NOT NULL DEFAULT '0'",
					'rows_total'     => 'bigint(20) unsigned NULL',
					'checksum'       => 'char(40) NOT NULL',
					'blocked_reason' => 'varchar(32) NULL',
					'last_error'     => 'text NULL',
					'started_at'     => self::DT_NULL,
					'heartbeat_at'   => self::DT_NULL,
					'finished_at'    => self::DT_NULL,
					'released_in'    => 'varchar(16) NOT NULL',
				),
				array( 'step_id' ),
				array(),
				array( 'status' => array( 'status' ) )
			),
			new Table(
				'notification',
				array( 'id' => self::ID ) + self::uid() + array(
					'type'              => 'varchar(48) NOT NULL',
					'dedupe_key'        => 'char(64) NOT NULL',
					'event_uuid'        => self::UID,
					'correlation_id'    => 'char(36) NULL',
					'recipient_user_id' => self::REF_NULL,
					'recipient_ref'     => 'varchar(64) NULL',
					'status'            => 'varchar(16) NOT NULL',
					'reason_code'       => 'varchar(48) NULL',
					'attempts'          => "tinyint(3) unsigned NOT NULL DEFAULT '0'",
					'next_attempt_at'   => self::DT_NULL,
					'locked_until'      => self::DT_NULL,
					'lock_owner'        => 'varchar(64) NULL',
					'last_error_class'  => 'varchar(32) NULL',
					'locale_requested'  => 'varchar(20) NULL',
					'locale_resolved'   => 'varchar(20) NULL',
					'template_rev'      => 'int(11) NULL',
					'resend_of'         => self::REF_NULL,
					'created_at'        => self::DT,
					'sent_at'           => self::DT_NULL,
				),
				array( 'id' ),
				array(
					'uid'        => array( 'uid' ),
					'dedupe_key' => array( 'dedupe_key' ),
				),
				array(
					'status_next' => array( 'status', 'next_attempt_at' ),
					'recipient'   => array( 'recipient_user_id' ),
					'event_uuid'  => array( 'event_uuid' ),
					'created_at'  => array( 'created_at' ),
				)
			),
			new Table(
				'reset_journal',
				array(
					'id'             => self::ID,
					'user_id'        => self::REF,
					'course_id'      => self::REF,
					'object_type'    => 'varchar(32) NOT NULL',
					'object_id'      => self::REF_ZERO,
					'previous_state' => self::TEXT,
					'triggered_by'   => self::REF_NULL,
					'triggered_role' => 'varchar(32) NOT NULL',
					'audit_id'       => self::REF_NULL,
					'status'         => "varchar(16) NOT NULL DEFAULT 'active'",
					'undone_at'      => self::DT_NULL,
					'created_at'     => self::DT,
				),
				array( 'id' ),
				array(),
				array(
					'user_course' => array( 'user_id', 'course_id' ),
					'created_at'  => array( 'created_at' ),
				)
			),
		);
	}
}
