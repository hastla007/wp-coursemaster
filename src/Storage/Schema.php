<?php
/**
 * Declarative core schema of Course Captain (R0-06 §4, R1-02).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for all core tables.
 *
 * Rules (R0-06 §4.0, R0-37 I-M2):
 * - `dbDelta`-ready: one column per line, `PRIMARY KEY  (…)` with two spaces,
 *   `KEY` instead of `INDEX`, no foreign keys and no generated columns in the
 *   `CREATE` statement (those are imperative steps, see `Migration\Steps`).
 * - Integer types carry the display width MariaDB reports, otherwise
 *   `dbDelta` would alter the column on every run (MySQL ≥ 8.0.17 ignores it).
 * - No `DEFAULT CURRENT_TIMESTAMP`: all `*_at` columns are UTC and set by PHP.
 * - Unique/idempotency columns are `NOT NULL`.
 * - Every table carries the ownership comment `course-captain:core` (KOMP-131).
 *
 * Table names are given without `$wpdb->prefix`.
 */
final class Schema {

	/**
	 * Core schema version (semver). Raise with every structural change.
	 */
	public const VERSION = '1.0.0';

	/**
	 * Lowest code version that can work with this schema (E-90, R0-37 §6.2).
	 */
	public const COMPAT_MIN = '1.0.0';

	/**
	 * Ownership marker stored as table comment (R0-20 D-20-03, KOMP-131).
	 */
	public const OWNER_COMMENT = 'course-captain:core';

	/**
	 * Prefix of every ownership comment, also of add-on tables.
	 */
	public const OWNER_COMMENT_PREFIX = 'course-captain:';

	/**
	 * Tables created in phase P0 before everything else (R0-37 §4.1).
	 *
	 * @var list<string>
	 */
	public const BOOTSTRAP_TABLES = array( 'ccap_lease', 'ccap_migration_step' );

	private const ID    = 'bigint(20) unsigned NOT NULL AUTO_INCREMENT';
	private const REF   = 'bigint(20) unsigned NOT NULL';
	private const REFN  = 'bigint(20) unsigned NULL';
	private const REF0  = 'bigint(20) unsigned NOT NULL DEFAULT 0';
	private const UID   = 'char(36) NOT NULL';
	private const HASH  = 'char(71) NOT NULL';
	private const HASHN = 'char(71) NULL';
	private const DT    = 'datetime NOT NULL';
	private const DTN   = 'datetime NULL';
	private const JSON  = 'longtext NOT NULL';
	private const JSONN = 'longtext NULL';
	private const FLAG0 = 'tinyint(1) NOT NULL DEFAULT 0';
	private const FLAG1 = 'tinyint(1) NOT NULL DEFAULT 1';
	private const FLAGN = 'tinyint(1) NULL';
	private const SORT  = 'int(11) NOT NULL DEFAULT 0';
	private const CAS   = 'int(10) unsigned NOT NULL DEFAULT 0';
	private const UINT0 = 'int(10) unsigned NOT NULL DEFAULT 0';

	/**
	 * Provenance columns (SRC macro, R0-06 §4.0).
	 *
	 * @return array<string, string>
	 */
	private static function src(): array {
		return array(
			'source_system' => "varchar(32) NOT NULL DEFAULT 'local'",
			'source_id'     => 'varchar(191) NULL',
			'source_hash'   => self::HASHN,
			'package_id'    => 'varchar(64) NULL',
			'imported_at'   => self::DTN,
		);
	}

	/**
	 * Timestamp columns (TS macro).
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
	 * All core tables in creation order (parents before children).
	 *
	 * Each entry: `columns` (name => definition) and `keys` (dbDelta key lines).
	 *
	 * @return array<string, array{columns: array<string, string>, keys: list<string>}>
	 */
	public static function tables(): array {
		return array(
			// P0 bootstrap (R0-37 §4.1, §4.3; R0-21 Δ-21-1).
			'ccap_lease'                 => array(
				'columns' => array(
					'name'         => 'varchar(191) NOT NULL',
					'owner'        => 'varchar(64) NOT NULL',
					'fence'        => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'expires_at'   => self::DT,
					'heartbeat_at' => self::DT,
					'created_at'   => self::DT,
				),
				'keys'    => array( 'PRIMARY KEY  (name)', 'KEY expires_at (expires_at)' ),
			),
			'ccap_migration_step'        => array(
				'columns' => array(
					'step_id'        => 'varchar(96) NOT NULL',
					'module'         => 'varchar(32) NOT NULL',
					'kind'           => 'varchar(12) NOT NULL',
					'status'         => "varchar(10) NOT NULL DEFAULT 'pending'",
					'attempts'       => 'smallint(5) unsigned NOT NULL DEFAULT 0',
					'crash_count'    => 'smallint(5) unsigned NOT NULL DEFAULT 0',
					'fence'          => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'cursor'         => 'varchar(191) NULL',
					'rows_done'      => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'rows_total'     => 'bigint(20) unsigned NULL',
					'checksum'       => 'char(40) NOT NULL',
					'blocked_reason' => 'varchar(32) NULL',
					'last_error'     => 'text NULL',
					'started_at'     => self::DTN,
					'heartbeat_at'   => self::DTN,
					'finished_at'    => self::DTN,
					'released_in'    => 'varchar(16) NOT NULL',
				),
				'keys'    => array( 'PRIMARY KEY  (step_id)', 'KEY module_status (module, status)' ),
			),

			// §4.1 Content.
			'ccap_source_map'            => array(
				'columns' => array(
					'id'          => self::ID,
					'uid'         => self::UID,
					'object_type' => 'varchar(32) NOT NULL',
					'object_id'   => self::REF,
				) + self::src() + array(
					'locally_modified' => self::FLAG0,
					'last_batch_id'    => self::REFN,
					'last_hash'        => self::HASHN,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY uid (uid)',
					'UNIQUE KEY source (source_system,source_id,object_type)',
					'UNIQUE KEY object (object_type,object_id)',
				),
			),
			'ccap_course_module'         => array(
				'columns' => array(
					'id'         => self::ID,
					'course_id'  => self::REF,
					'module_id'  => self::REF,
					'sort_order' => self::SORT,
					'required'   => self::FLAG1,
					'rules'      => self::JSONN,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY course_module (course_id,module_id)',
					'KEY course_sort (course_id,sort_order)',
					'KEY module_id (module_id)',
				),
			),
			'ccap_course_relation'       => array(
				'columns' => array(
					'id'                => self::ID,
					'course_id'         => self::REF,
					'related_course_id' => self::REF,
					'relation_type'     => "varchar(16) NOT NULL DEFAULT 'contains'",
					'sort_order'        => self::SORT,
					'created_by'        => self::REFN,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY course_relation (course_id,related_course_id,relation_type)',
					'KEY related (related_course_id,relation_type)',
				),
			),
			'ccap_course_revision'       => array(
				'columns' => array(
					'id'             => self::ID,
					'course_id'      => self::REF,
					'revision_no'    => 'int(10) unsigned NOT NULL',
					'status'         => "varchar(16) NOT NULL DEFAULT 'published'",
					'change_class'   => "varchar(8) NOT NULL DEFAULT 'minor'",
					'notes'          => self::JSONN,
					'structure'      => self::JSON,
					'structure_hash' => self::HASH,
					'format_version' => 'smallint(5) unsigned NOT NULL DEFAULT 1',
					'published_by'   => self::REFN,
					'published_at'   => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY course_rev (course_id,revision_no)',
					'KEY course_status (course_id,status)',
				),
			),
			'ccap_question'              => array(
				'columns' => array(
					'id'  => self::ID,
					'uid' => self::UID,
				) + self::src() + array(
					'reference_no'        => 'varchar(32) NULL',
					'language'            => "varchar(20) NOT NULL DEFAULT ''",
					'status'              => "varchar(16) NOT NULL DEFAULT 'draft'",
					'current_revision_id' => self::REFN,
					'author_user_id'      => self::REFN,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY uid (uid)',
					'UNIQUE KEY source (source_system,source_id)',
					'KEY status (status)',
					'KEY current_revision_id (current_revision_id)',
					'KEY reference_no (reference_no)',
				),
			),
			'ccap_question_revision'     => array(
				'columns' => array(
					'id'                 => self::ID,
					'question_id'        => self::REF,
					'revision_no'        => 'int(10) unsigned NOT NULL',
					'content_hash'       => self::HASH,
					'package_id'         => 'varchar(64) NULL',
					'source_version'     => 'varchar(64) NULL',
					'question_type'      => 'varchar(32) NOT NULL',
					'change_class'       => "varchar(16) NOT NULL DEFAULT 'editorial'",
					'question_text'      => 'longtext NOT NULL',
					'settings'           => self::JSONN,
					'feedback_correct'   => 'longtext NULL',
					'feedback_incorrect' => 'longtext NULL',
					'image_required'     => self::FLAG0,
					'editorial_status'   => "varchar(20) NOT NULL DEFAULT 'draft'",
					'rights_status'      => "varchar(20) NOT NULL DEFAULT 'own'",
					'rights_owner'       => 'varchar(255) NULL',
					'license_reference'  => 'longtext NULL',
					'valid_from'         => 'date NULL',
					'valid_to'           => 'date NULL',
					'created_by'         => self::REFN,
					'created_at'         => self::DT,
					'published_at'       => self::DTN,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY question_rev (question_id,revision_no)',
					'KEY question_hash (question_id,content_hash)',
					'KEY publish (question_id,editorial_status,valid_from,valid_to)',
				),
			),
			'ccap_question_tag'          => array(
				'columns' => array(
					'id'          => self::ID,
					'revision_id' => self::REF,
					'tag_type'    => 'varchar(32) NOT NULL',
					'tag_key'     => 'varchar(191) NOT NULL',
					'label'       => "varchar(255) NOT NULL DEFAULT ''",
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY rev_tag (revision_id,tag_type,tag_key)',
					'KEY tag (tag_type,tag_key)',
				),
			),
			'ccap_answer'                => array(
				'columns' => array(
					'id'          => self::ID,
					'revision_id' => self::REF,
					'answer_key'  => 'varchar(16) NOT NULL',
					'answer_text' => 'longtext NOT NULL',
					'is_correct'  => self::FLAG0,
					'sort_order'  => self::SORT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY revision_key (revision_id,answer_key)',
				),
			),
			'ccap_module_question'       => array(
				'columns' => array(
					'id'          => self::ID,
					'module_id'   => self::REF,
					'question_id' => self::REF,
					'sort_order'  => 'int(11) NULL',
					'required'    => self::FLAG1,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY module_question (module_id,question_id)',
					'KEY question_id (question_id)',
				),
			),
			'ccap_activity_question'     => array(
				'columns' => array(
					'id'          => self::ID,
					'activity_id' => self::REF,
					'question_id' => self::REF,
					'sort_order'  => self::SORT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY activity_question (activity_id,question_id)',
					'KEY question_id (question_id)',
				),
			),
			'ccap_media'                 => array(
				'columns' => array(
					'id'  => self::ID,
					'uid' => self::UID,
				) + self::src() + array(
					'attachment_id'     => self::REFN,
					'storage_key'       => 'varchar(255) NULL',
					'package_path'      => 'varchar(255) NULL',
					'media_type'        => "varchar(20) NOT NULL DEFAULT 'image'",
					'mime_type'         => "varchar(100) NOT NULL DEFAULT ''",
					'size_bytes'        => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'sha256'            => 'char(64) NOT NULL',
					'access'            => "varchar(16) NOT NULL DEFAULT 'protected'",
					'alt_text'          => 'varchar(255) NULL',
					'caption'           => 'longtext NULL',
					'description'       => 'longtext NULL',
					'source_text'       => 'longtext NULL',
					'source_url'        => 'varchar(2048) NULL',
					'rights_owner'      => 'varchar(255) NULL',
					'license_reference' => 'longtext NULL',
					'rights_status'     => "varchar(20) NOT NULL DEFAULT 'own'",
					'status'            => "varchar(16) NOT NULL DEFAULT 'active'",
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY uid (uid)',
					'UNIQUE KEY source (source_system,source_id)',
					'UNIQUE KEY attachment_id (attachment_id)',
					'KEY sha256 (sha256)',
					'KEY rights_status (rights_status)',
				),
			),
			'ccap_content_media'         => array(
				'columns' => array(
					'id'               => self::ID,
					'object_type'      => 'varchar(32) NOT NULL',
					'object_id'        => self::REF,
					'media_id'         => self::REF,
					'role'             => 'varchar(32) NOT NULL',
					'sort_order'       => self::SORT,
					'caption_override' => 'longtext NULL',
					'alt_override'     => 'varchar(255) NULL',
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY object_media (object_type,object_id,media_id,role)',
					'KEY media_id (media_id)',
				),
			),

			// §4.2 Assessment.
			'ccap_exam_rule'             => array(
				'columns' => array(
					'id'  => self::ID,
					'uid' => self::UID,
				) + self::src() + array(
					'course_id'          => self::REF,
					'version'            => 'varchar(64) NOT NULL',
					'status'             => "varchar(16) NOT NULL DEFAULT 'draft'",
					'valid_from'         => 'date NULL',
					'valid_until'        => 'date NULL',
					'time_limit_seconds' => 'int(10) unsigned NULL',
					'passing_rule'       => self::JSON,
					'settings'           => self::JSONN,
					'published_by'       => self::REFN,
					'published_at'       => self::DTN,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY uid (uid)',
					'UNIQUE KEY source (source_system,source_id)',
					'UNIQUE KEY course_version (course_id,version)',
					'KEY course_status (course_id,status)',
				),
			),
			'ccap_exam_blueprint'        => array(
				'columns' => array(
					'id'             => self::ID,
					'rule_id'        => self::REF,
					'module_id'      => self::REF,
					'question_count' => 'int(10) unsigned NOT NULL',
					'rules'          => self::JSONN,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY rule_module (rule_id,module_id)',
					'KEY module_id (module_id)',
				),
			),
			'ccap_exam_variant'          => array(
				'columns' => array(
					'id'  => self::ID,
					'uid' => self::UID,
				) + self::src() + array(
					'course_id'  => self::REF,
					'variant_no' => 'int(10) unsigned NOT NULL',
					'status'     => "varchar(16) NOT NULL DEFAULT 'draft'",
					'sort_order' => self::SORT,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY uid (uid)',
					'UNIQUE KEY source (source_system,source_id)',
					'UNIQUE KEY course_variant (course_id,variant_no)',
				),
			),
			'ccap_exam_variant_question' => array(
				'columns' => array(
					'id'          => self::ID,
					'variant_id'  => self::REF,
					'question_id' => self::REF,
					'position'    => 'int(10) unsigned NOT NULL',
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY variant_position (variant_id,position)',
					'KEY question_id (question_id)',
				),
			),
			// `running` (generated) and UNIQUE running_form: step core:0001.
			'ccap_exam_session'          => array(
				'columns' => array(
					'id'                 => self::ID,
					'uid'                => self::UID,
					'user_id'            => self::REF,
					'course_id'          => self::REF,
					'course_revision_id' => self::REFN,
					'rule_id'            => self::REF,
					'variant_id'         => self::REFN,
					'activity_uid'       => 'char(36) NULL',
					'mode'               => 'varchar(16) NOT NULL',
					'form_key'           => 'varchar(64) NOT NULL',
					'status'             => "varchar(16) NOT NULL DEFAULT 'in_progress'",
					'end_reason'         => 'varchar(24) NULL',
					'start_token'        => 'char(36) NOT NULL',
					'snapshot'           => self::JSON,
					'snapshot_hash'      => self::HASH,
					'seed'               => 'bigint(20) unsigned NULL',
					'evaluator_version'  => "varchar(32) NOT NULL DEFAULT ''",
					'evaluation_no'      => 'smallint(5) unsigned NOT NULL DEFAULT 0',
					'started_at'         => self::DT,
					'deadline_at'        => self::DTN,
					'submitted_at'       => self::DTN,
					'evaluated_at'       => self::DTN,
					'points_milli'       => 'int(11) NULL',
					'max_points_milli'   => 'int(11) NULL',
					'passed'             => self::FLAGN,
					'result'             => self::JSONN,
					'row_version'        => self::CAS,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY uid (uid)',
					'UNIQUE KEY start_token (start_token)',
					'KEY user_course (user_id,course_id,status)',
					'KEY status_deadline (status,deadline_at)',
					'KEY rule_id (rule_id)',
					'KEY variant_id (variant_id)',
					'KEY course_revision_id (course_revision_id)',
				),
			),
			'ccap_exam_session_question' => array(
				'columns' => array(
					'id'                  => self::ID,
					'session_id'          => self::REF,
					'position'            => 'int(10) unsigned NOT NULL',
					'question_id'         => self::REF,
					'revision_id'         => self::REF,
					'answer_order'        => self::JSONN,
					'given_answer'        => self::JSONN,
					'self_assessed_known' => self::FLAGN,
					'revealed_at'         => self::DTN,
					'is_correct'          => self::FLAGN,
					'answered_at'         => self::DTN,
					'flagged'             => self::FLAG0,
					'neutralized'         => self::FLAG0,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY session_position (session_id,position)',
					'KEY question_id (question_id)',
					'KEY revision_id (revision_id)',
				),
			),

			// §4.3 Learning.
			'ccap_attempt'               => array(
				'columns' => array(
					'id'                => self::ID,
					'user_id'           => self::REF,
					'question_id'       => self::REF,
					'revision_id'       => self::REF,
					'course_id'         => self::REFN,
					'context'           => "varchar(32) NOT NULL DEFAULT 'learn'",
					'context_ref'       => 'varchar(64) NULL',
					'is_correct'        => self::FLAGN,
					'self_assessment'   => 'varchar(16) NULL',
					'answer_ms'         => 'int(10) unsigned NULL',
					'idempotency_key'   => 'char(36) NOT NULL',
					'correction_of'     => self::REFN,
					'algorithm_version' => 'smallint(5) unsigned NOT NULL DEFAULT 1',
					'created_at'        => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY user_idem (user_id,idempotency_key)',
					'KEY user_question (user_id,question_id)',
					'KEY user_created (user_id,created_at)',
					'KEY question_id (question_id)',
					'KEY revision_id (revision_id)',
					'KEY course_created (course_id,created_at)',
				),
			),
			'ccap_attempt_answer'        => array(
				'columns' => array(
					'id'         => self::ID,
					'attempt_id' => self::REF,
					'answer_id'  => self::REFN,
					'answer_key' => 'varchar(16) NULL',
					'free_text'  => 'longtext NULL',
					'position'   => self::SORT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'KEY attempt_id (attempt_id)',
					'KEY answer_id (answer_id)',
				),
			),
			'ccap_question_progress'     => array(
				'columns' => array(
					'id'                => self::ID,
					'user_id'           => self::REF,
					'question_id'       => self::REF,
					'attempts'          => self::UINT0,
					'correct_count'     => self::UINT0,
					'wrong_count'       => self::UINT0,
					'streak'            => self::UINT0,
					'mastery_score'     => 'decimal(5,4) NOT NULL DEFAULT 0.0000',
					'learn_status'      => "varchar(16) NOT NULL DEFAULT 'new'",
					'algorithm_version' => 'smallint(5) unsigned NOT NULL DEFAULT 1',
					'first_answered_at' => self::DTN,
					'last_answered_at'  => self::DTN,
					'due_at'            => self::DTN,
					'row_version'       => self::CAS,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY user_question (user_id,question_id)',
					'KEY question_id (question_id)',
					'KEY user_status (user_id,learn_status)',
					'KEY user_due (user_id,due_at)',
				),
			),
			'ccap_activity_progress'     => array(
				'columns' => array(
					'id'                 => self::ID,
					'user_id'            => self::REF,
					'activity_id'        => self::REF,
					'course_id'          => self::REFN,
					'course_revision_id' => self::REFN,
					'activity_type'      => 'varchar(32) NOT NULL',
					'status'             => "varchar(16) NOT NULL DEFAULT 'started'",
					'reason_code'        => 'varchar(32) NULL',
					'rule_version'       => 'smallint(5) unsigned NULL',
					'position_seconds'   => self::UINT0,
					'data'               => self::JSONN,
					'content_hash'       => self::HASHN,
					'completed_at'       => self::DTN,
					'last_access_at'     => self::DT,
					'created_at'         => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY user_activity (user_id,activity_id)',
					'KEY activity_id (activity_id)',
					'KEY user_course (user_id,course_id)',
				),
			),
			'ccap_course_progress'       => array(
				'columns' => array(
					'id'                    => self::ID,
					'user_id'               => self::REF,
					'course_id'             => self::REF,
					'course_revision_id'    => self::REFN,
					'completed_revision_id' => self::REFN,
					'cycle_no'              => 'smallint(5) unsigned NOT NULL DEFAULT 1',
					'status'                => "varchar(16) NOT NULL DEFAULT 'not_started'",
					'completion_ratio'      => 'decimal(5,4) NOT NULL DEFAULT 0.0000',
					'mastery_ratio'         => 'decimal(5,4) NULL',
					'activities_done'       => self::UINT0,
					'activities_total'      => self::UINT0,
					'started_at'            => self::DTN,
					'completed_at'          => self::DTN,
					'last_activity_at'      => self::DTN,
					'updated_at'            => self::DT,
					'row_version'           => self::CAS,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY user_course (user_id,course_id)',
					'KEY course_status (course_id,status)',
					'KEY user_last (user_id,last_activity_at)',
					'KEY course_rev (course_id,course_revision_id)',
					'KEY course_revision_id (course_revision_id)',
					'KEY completed_revision_id (completed_revision_id)',
				),
			),
			'ccap_favorite'              => array(
				'columns' => array(
					'id'          => self::ID,
					'user_id'     => self::REF,
					'question_id' => self::REF,
					'context'     => "varchar(32) NOT NULL DEFAULT 'question'",
					'created_at'  => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY user_question_context (user_id,question_id,context)',
					'KEY question_id (question_id)',
				),
			),
			'ccap_collection'            => array(
				'columns' => array(
					'id'         => self::ID,
					'user_id'    => self::REF,
					'course_id'  => self::REF,
					'name'       => 'varchar(80) NOT NULL',
					'created_at' => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'KEY user_course (user_id,course_id)',
					'KEY course_id (course_id)',
				),
			),
			'ccap_collection_item'       => array(
				'columns' => array(
					'collection_id' => self::REF,
					'question_id'   => self::REF,
					'created_at'    => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (collection_id,question_id)',
					'KEY question_id (question_id)',
				),
			),

			// §4.4 Access and groups (parents first: group, seat, grant).
			'ccap_group'                 => array(
				'columns' => array(
					'id'              => self::ID,
					'uid'             => self::UID,
					'owner_user_id'   => self::REF0,
					'name'            => 'varchar(190) NOT NULL',
					'seats'           => self::UINT0,
					'status'          => "varchar(16) NOT NULL DEFAULT 'active'",
					'claim_policy'    => "varchar(24) NOT NULL DEFAULT 'transferable'",
					'valid_from'      => self::DTN,
					'valid_until'     => self::DTN,
					'source_type'     => "varchar(32) NOT NULL DEFAULT 'manual'",
					'source_id'       => 'varchar(191) NULL',
					'source_revision' => 'int(10) unsigned NOT NULL DEFAULT 1',
					'join_token_hash' => 'char(64) NULL',
					'reason'          => 'longtext NULL',
					'row_version'     => self::CAS,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY uid (uid)',
					'UNIQUE KEY group_source (source_type,source_id)',
					'UNIQUE KEY join_token_hash (join_token_hash)',
					'KEY owner (owner_user_id)',
					'KEY status (status)',
				),
			),
			'ccap_group_course'          => array(
				'columns' => array(
					'id'        => self::ID,
					'group_id'  => self::REF,
					'course_id' => self::REF,
					'module_id' => self::REF0,
					'scopes'    => "varchar(64) NOT NULL DEFAULT 'all'",
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY group_course (group_id,course_id,module_id)',
					'KEY course_id (course_id)',
				),
			),
			// `active_user` (generated) and UNIQUE active_member: step core:0002.
			'ccap_seat'                  => array(
				'columns' => array(
					'id'               => self::ID,
					'group_id'         => self::REF,
					'user_id'          => self::REFN,
					'invite_email'     => 'varchar(190) NULL',
					'status'           => "varchar(16) NOT NULL DEFAULT 'invited'",
					'claim_token_hash' => 'char(64) NULL',
					'claim_expires_at' => self::DTN,
					'invited_at'       => self::DTN,
					'claimed_at'       => self::DTN,
					'removed_at'       => self::DTN,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY claim_token_hash (claim_token_hash)',
					'KEY group_status (group_id,status)',
					'KEY status_invited (status,invited_at)',
					'KEY user_id (user_id)',
					'KEY invite_email (invite_email)',
				),
			),
			'ccap_grant'                 => array(
				'columns' => array(
					'id'                   => self::ID,
					'uid'                  => self::UID,
					'user_id'              => self::REFN,
					'subject_ref'          => 'char(64) NULL',
					'course_id'            => self::REF,
					'module_id'            => self::REF0,
					'scopes'               => "varchar(64) NOT NULL DEFAULT 'all'",
					'depends_on_course_id' => self::REF0,
					'status'               => "varchar(24) NOT NULL DEFAULT 'pending_confirmation'",
					'source_type'          => 'varchar(32) NOT NULL',
					'source_id'            => 'varchar(191) NOT NULL',
					'source_line'          => "varchar(64) NOT NULL DEFAULT ''",
					'source_revision'      => 'int(10) unsigned NOT NULL DEFAULT 1',
					'source_seq'           => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'source_plugin'        => 'varchar(64) NULL',
					'seat_id'              => self::REFN,
					'valid_from'           => self::DTN,
					'valid_until'          => self::DTN,
					'valid_until_locked'   => self::FLAG0,
					'manual_override'      => 'varchar(16) NULL',
					'paid_at'              => self::DTN,
					'chain_anchor_at'      => self::DTN,
					'renewal_mode'         => "varchar(16) NOT NULL DEFAULT 'extend'",
					'revocation_reason'    => 'varchar(64) NULL',
					'granted_by'           => self::REFN,
					'reason'               => 'longtext NULL',
					'meta'                 => self::JSONN,
					'row_version'          => self::CAS,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY uid (uid)',
					'UNIQUE KEY grant_source (source_type,source_id,source_line,source_revision,course_id,module_id)',
					'KEY user_access (user_id,course_id,module_id,status,valid_until)',
					'KEY status_until (status,valid_until)',
					'KEY course_status (course_id,status)',
					'KEY chain (user_id,course_id,renewal_mode,chain_anchor_at)',
					'KEY depends (user_id,depends_on_course_id)',
					'KEY seat_id (seat_id)',
				),
			),
			'ccap_grant_event'           => array(
				'columns' => array(
					'id'              => self::ID,
					'grant_id'        => self::REF,
					'event_type'      => 'varchar(32) NOT NULL',
					'from_status'     => 'varchar(24) NULL',
					'to_status'       => 'varchar(24) NULL',
					'reason_code'     => "varchar(64) NOT NULL DEFAULT ''",
					'actor_type'      => "varchar(16) NOT NULL DEFAULT 'system'",
					'actor_id'        => self::REFN,
					'source_event_id' => 'varchar(191) NULL',
					'correlation_id'  => 'char(36) NULL',
					'details'         => self::JSONN,
					'occurred_at'     => self::DT,
					'created_at'      => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY grant_source_event (grant_id,source_event_id)',
					'KEY grant_created (grant_id,created_at)',
					'KEY correlation_id (correlation_id)',
				),
			),
			'ccap_access_rule'           => array(
				'columns' => array(
					'id'          => self::ID,
					'course_id'   => self::REF,
					'module_id'   => self::REFN,
					'scope'       => 'varchar(32) NOT NULL',
					'access_type' => 'varchar(16) NOT NULL',
					'limit_type'  => 'varchar(32) NULL',
					'limit_value' => 'int(11) NULL',
					'valid_from'  => self::DTN,
					'valid_until' => self::DTN,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'KEY course_scope (course_id,scope)',
				),
			),

			// §4.4a Text/file assignments (E-168).
			'ccap_assignment_submission' => array(
				'columns' => array(
					'id'                 => self::ID,
					'uid'                => self::UID,
					'user_id'            => self::REF,
					'course_id'          => self::REF,
					'activity_id'        => self::REF,
					'activity_uid'       => self::UID,
					'course_revision_id' => self::REFN,
					'submission_no'      => 'smallint(5) unsigned NOT NULL DEFAULT 1',
					'status'             => "varchar(16) NOT NULL DEFAULT 'draft'",
					'text_answer'        => 'longtext NULL',
					'submitted_at'       => self::DTN,
					'returned_at'        => self::DTN,
					'graded_at'          => self::DTN,
					'current_grade_id'   => self::REFN,
					'row_version'        => self::CAS,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY uid (uid)',
					'UNIQUE KEY user_activity_no (user_id,activity_id,submission_no)',
					'KEY course_status (course_id,status)',
					'KEY activity_status (activity_id,status)',
					'KEY course_revision_id (course_revision_id)',
				),
			),
			'ccap_assignment_file'       => array(
				'columns' => array(
					'id'            => self::ID,
					'submission_id' => self::REF,
					'media_id'      => self::REF,
					'original_name' => 'varchar(255) NOT NULL',
					'mime_type'     => 'varchar(100) NOT NULL',
					'size_bytes'    => 'bigint(20) unsigned NOT NULL DEFAULT 0',
					'sha256'        => 'char(64) NOT NULL',
					'sort_order'    => self::SORT,
					'created_at'    => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY submission_media (submission_id,media_id)',
					'KEY media_id (media_id)',
				),
			),
			'ccap_assignment_grade'      => array(
				'columns' => array(
					'id'               => self::ID,
					'submission_id'    => self::REF,
					'evaluation_no'    => 'smallint(5) unsigned NOT NULL',
					'points_milli'     => 'int(11) NULL',
					'max_points_milli' => 'int(11) NULL',
					'passed'           => self::FLAGN,
					'feedback'         => 'longtext NULL',
					'graded_by'        => self::REF,
					'graded_at'        => self::DT,
					'reason'           => 'longtext NULL',
					'created_at'       => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY submission_eval (submission_id,evaluation_no)',
					'KEY graded_by (graded_by)',
				),
			),

			// §4.5 Evidence.
			'ccap_certificate'           => array(
				'columns' => array(
					'id'                 => self::ID,
					'certificate_uid'    => self::UID,
					'revision_no'        => 'smallint(5) unsigned NOT NULL DEFAULT 1',
					'user_id'            => self::REF,
					'course_id'          => self::REF,
					'course_revision_id' => self::REF,
					'evidence_type'      => 'varchar(32) NOT NULL',
					'evidence_id'        => self::REF,
					'status'             => "varchar(16) NOT NULL DEFAULT 'issued'",
					'supersedes_id'      => self::REFN,
					'template_version'   => 'varchar(32) NOT NULL',
					'snapshot'           => self::JSON,
					'snapshot_hash'      => self::HASH,
					'verify_token_hash'  => 'char(64) NOT NULL',
					'issued_at'          => self::DT,
					'issued_by'          => self::REFN,
					'revoked_at'         => self::DTN,
					'revoked_by'         => self::REFN,
					'revoke_reason'      => 'longtext NULL',
					'created_at'         => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY cert_rev (certificate_uid,revision_no)',
					'UNIQUE KEY evidence_rev (evidence_type,evidence_id,revision_no)',
					'UNIQUE KEY verify_token_hash (verify_token_hash)',
					'KEY user_course (user_id,course_id)',
					'KEY course_revision_id (course_revision_id)',
					'KEY supersedes_id (supersedes_id)',
				),
			),

			// §4.6 Integration (Free: internal outbox only, E-38/E-182).
			'ccap_inbox_event'           => array(
				'columns' => array(
					'id'               => self::ID,
					'connector_id'     => 'varchar(64) NOT NULL',
					'event_id'         => 'varchar(191) NOT NULL',
					'contract_version' => 'varchar(16) NOT NULL',
					'event_type'       => 'varchar(64) NOT NULL',
					'correlation_id'   => 'char(36) NULL',
					'payload'          => self::JSON,
					'payload_hash'     => 'char(64) NOT NULL',
					'status'           => "varchar(16) NOT NULL DEFAULT 'received'",
					'attempts'         => 'smallint(5) unsigned NOT NULL DEFAULT 0',
					'next_attempt_at'  => self::DTN,
					'locked_until'     => self::DTN,
					'lock_owner'       => 'varchar(64) NULL',
					'last_error'       => 'text NULL',
					'occurred_at'      => self::DT,
					'received_at'      => self::DT,
					'processed_at'     => self::DTN,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY connector_event (connector_id,event_id)',
					'KEY status_next (status,next_attempt_at)',
					'KEY correlation_id (correlation_id)',
				),
			),
			'ccap_outbox_event'          => array(
				'columns' => array(
					'id'               => self::ID,
					'event_uuid'       => self::UID,
					'event_type'       => 'varchar(64) NOT NULL',
					'schema_version'   => 'varchar(16) NOT NULL',
					'contract_version' => 'varchar(16) NOT NULL',
					'object_type'      => 'varchar(32) NOT NULL',
					'object_id'        => self::UID,
					'object_version'   => self::UINT0,
					'course_uid'       => 'char(36) NULL',
					'user_ref'         => 'varchar(32) NULL',
					'actor_type'       => "varchar(16) NOT NULL DEFAULT 'system'",
					'actor_ref'        => 'varchar(64) NULL',
					'correlation_id'   => 'char(36) NULL',
					'causation_id'     => 'char(36) NULL',
					'idempotency_key'  => 'varchar(191) NOT NULL',
					'privacy_tags'     => "varchar(191) NOT NULL DEFAULT ''",
					'payload'          => self::JSON,
					'created_at'       => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY event_uuid (event_uuid)',
					'KEY type_created (event_type,created_at)',
					'KEY object (object_type,object_id)',
					'KEY created_at (created_at)',
				),
			),
			'ccap_outbox_delivery'       => array(
				'columns' => array(
					'id'               => self::ID,
					'event_uuid'       => self::UID,
					'subscriber_id'    => 'varchar(64) NOT NULL',
					'status'           => "varchar(16) NOT NULL DEFAULT 'pending'",
					'skip_reason'      => 'varchar(32) NULL',
					'manual_retries'   => 'smallint(5) unsigned NOT NULL DEFAULT 0',
					'discarded_at'     => self::DTN,
					'attempts'         => 'smallint(5) unsigned NOT NULL DEFAULT 0',
					'next_attempt_at'  => self::DTN,
					'locked_until'     => self::DTN,
					'lock_owner'       => 'varchar(64) NULL',
					'last_error_class' => 'varchar(32) NULL',
					'last_error'       => 'text NULL',
					'created_at'       => self::DT,
					'delivered_at'     => self::DTN,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY event_subscriber (event_uuid,subscriber_id)',
					'KEY status_next (status,next_attempt_at)',
				),
			),

			// §4.7 Operations.
			'ccap_import_batch'          => array(
				'columns' => array(
					'id'              => self::ID,
					'package_id'      => 'varchar(64) NOT NULL',
					'schema_version'  => 'varchar(16) NOT NULL',
					'source_revision' => 'varchar(64) NULL',
					'status'          => "varchar(20) NOT NULL DEFAULT 'uploaded'",
					'manifest'        => self::JSON,
					'stats'           => self::JSONN,
					'storage_path'    => 'varchar(255) NULL',
					'error'           => 'longtext NULL',
					'heartbeat_at'    => self::DTN,
					'created_by'      => self::REFN,
					'approved_by'     => self::REFN,
					'created_at'      => self::DT,
					'approved_at'     => self::DTN,
					'published_at'    => self::DTN,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'KEY package_id (package_id)',
					'KEY status (status)',
				),
			),
			'ccap_import_item_log'       => array(
				'columns' => array(
					'id'         => self::ID,
					'batch_id'   => self::REF,
					'attempt'    => 'smallint(5) unsigned NOT NULL DEFAULT 1',
					'entity'     => 'varchar(32) NOT NULL',
					'source_id'  => 'varchar(191) NULL',
					'object_id'  => self::REFN,
					'action'     => 'varchar(16) NOT NULL',
					'outcome'    => "varchar(24) NOT NULL DEFAULT 'ok'",
					'message'    => 'longtext NULL',
					'created_at' => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'KEY batch_entity (batch_id,entity)',
					'KEY action (action)',
					'KEY action_created (action,created_at)',
					'KEY created_at (created_at)',
				),
			),
			'ccap_import_issue'          => array(
				'columns' => array(
					'id'          => self::ID,
					'batch_id'    => self::REF,
					'attempt'     => 'smallint(5) unsigned NOT NULL DEFAULT 1',
					'phase'       => 'varchar(16) NOT NULL',
					'entity'      => 'varchar(32) NOT NULL',
					'source_id'   => 'varchar(191) NULL',
					'uid'         => 'char(36) NULL',
					'file_ref'    => 'varchar(255) NULL',
					'path'        => 'varchar(255) NULL',
					'rule'        => 'varchar(64) NOT NULL',
					'severity'    => 'varchar(16) NOT NULL',
					'class'       => 'varchar(32) NOT NULL',
					'action'      => 'varchar(16) NULL',
					'message_key' => 'varchar(64) NOT NULL',
					'params'      => self::JSONN,
					'decision'    => 'varchar(16) NULL',
					'fingerprint' => 'char(64) NOT NULL',
					'created_at'  => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'KEY batch_id (batch_id,id)',
					'KEY batch_severity (batch_id,severity,id)',
					'KEY batch_rule (batch_id,rule)',
					'KEY batch_entity (batch_id,entity,source_id)',
				),
			),
			'ccap_audit_log'             => array(
				'columns' => array(
					'id'              => self::ID,
					'actor_user_id'   => self::REFN,
					'actor_type'      => "varchar(16) NOT NULL DEFAULT 'user'",
					'subject_user_id' => self::REFN,
					'action'          => 'varchar(64) NOT NULL',
					'reason_code'     => 'varchar(64) NULL',
					'object_type'     => 'varchar(32) NULL',
					'object_id'       => 'varchar(191) NULL',
					'reason'          => 'longtext NULL',
					'context'         => self::JSONN,
					'correlation_id'  => 'char(36) NULL',
					'request_id'      => 'varchar(40) NULL',
					'row_hash'        => 'char(64) NOT NULL',
					'created_at'      => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'KEY actor (actor_user_id)',
					'KEY subject (subject_user_id)',
					'KEY object (object_type,object_id(64))',
					'KEY action (action)',
					'KEY created_at (created_at)',
					'KEY correlation_id (correlation_id)',
				),
			),
			'ccap_override'              => array(
				'columns' => array(
					'id'          => self::ID,
					'object_type' => 'varchar(32) NOT NULL',
					'object_id'   => self::REF,
					'field'       => 'varchar(32) NOT NULL',
					'value'       => 'varchar(255) NULL',
					'reason'      => 'longtext NOT NULL',
					'user_id'     => self::REFN,
					'created_at'  => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY object_field (object_type,object_id,field)',
				),
			),
			'ccap_rate_limit'            => array(
				'columns' => array(
					'bucket_hash'  => 'char(64) NOT NULL',
					'window_start' => self::UINT0,
					'hits'         => self::UINT0,
					'expires_at'   => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (bucket_hash)',
					'KEY expires_at (expires_at)',
				),
			),
			'ccap_request_idempotency'   => array(
				'columns' => array(
					'id'           => self::ID,
					'user_id'      => self::REF,
					'scope'        => 'varchar(64) NOT NULL',
					'idem_key'     => 'char(36) NOT NULL',
					'request_hash' => 'char(64) NOT NULL',
					'status_code'  => 'smallint(5) unsigned NOT NULL DEFAULT 0',
					'response'     => 'longtext NULL',
					'resource'     => 'varchar(64) NULL',
					'created_at'   => self::DT,
					'expires_at'   => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY user_scope_key (user_id,scope,idem_key)',
					'KEY expires_at (expires_at)',
				),
			),

			// §4.10 Tables from decisions and follow-up tasks.
			'ccap_course_staff'          => array(
				'columns' => array(
					'id'          => self::ID,
					'course_id'   => self::REF,
					'user_id'     => self::REF,
					'role'        => 'varchar(16) NOT NULL',
					'group_id'    => self::REF0,
					'assigned_by' => self::REFN,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY course_user_role (course_id,user_id,role,group_id)',
					'KEY user_id (user_id)',
					'KEY group_id (group_id)',
				),
			),
			'ccap_learner_lock'          => array(
				'columns' => array(
					'id'          => self::ID,
					'user_id'     => self::REF,
					'reason_code' => 'varchar(64) NOT NULL',
					'reason'      => 'longtext NULL',
					'valid_from'  => self::DT,
					'valid_until' => self::DTN,
					'set_by'      => self::REFN,
					'lifted_at'   => self::DTN,
					'lifted_by'   => self::REFN,
				) + self::ts(),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'KEY user_active (user_id,lifted_at)',
					'KEY lifted_at (lifted_at)',
				),
			),
			'ccap_accommodation'         => array(
				'columns' => array(
					'id'              => self::ID,
					'user_id'         => self::REF,
					'scope_type'      => 'varchar(16) NOT NULL',
					'scope_id'        => self::REF0,
					'time_factor_pct' => 'smallint(5) unsigned NULL',
					'unlimited'       => self::FLAG0,
					'valid_until'     => self::DTN,
					'created_by'      => self::REFN,
					'created_at'      => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY user_scope (user_id,scope_type,scope_id)',
				),
			),
			'ccap_notification'          => array(
				'columns' => array(
					'id'                => self::ID,
					'uid'               => self::UID,
					'type'              => 'varchar(48) NOT NULL',
					'dedupe_key'        => 'char(64) NOT NULL',
					'event_uuid'        => self::UID,
					'correlation_id'    => 'char(36) NULL',
					'recipient_user_id' => self::REFN,
					'recipient_ref'     => 'varchar(64) NULL',
					'status'            => "varchar(16) NOT NULL DEFAULT 'queued'",
					'reason_code'       => 'varchar(48) NULL',
					'attempts'          => 'tinyint(3) unsigned NOT NULL DEFAULT 0',
					'next_attempt_at'   => self::DTN,
					'locked_until'      => self::DTN,
					'lock_owner'        => 'varchar(64) NULL',
					'last_error_class'  => 'varchar(32) NULL',
					'locale_requested'  => "varchar(20) NOT NULL DEFAULT ''",
					'locale_resolved'   => "varchar(20) NOT NULL DEFAULT ''",
					'template_rev'      => 'int(10) unsigned NULL',
					'resend_of'         => self::REFN,
					'created_at'        => self::DT,
					'sent_at'           => self::DTN,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'UNIQUE KEY uid (uid)',
					'UNIQUE KEY dedupe_key (dedupe_key)',
					'KEY status_next (status,next_attempt_at)',
					'KEY recipient (recipient_user_id)',
					'KEY created_at (created_at)',
				),
			),
			'ccap_reset_journal'         => array(
				'columns' => array(
					'id'            => self::ID,
					'user_id'       => self::REF,
					'course_id'     => self::REF,
					'object_type'   => "varchar(32) NOT NULL DEFAULT 'course'",
					'object_id'     => self::REF0,
					'prior_state'   => self::JSON,
					'actor_user_id' => self::REFN,
					'actor_role'    => 'varchar(32) NOT NULL',
					'audit_id'      => self::REFN,
					'reverted_at'   => self::DTN,
					'created_at'    => self::DT,
				),
				'keys'    => array(
					'PRIMARY KEY  (id)',
					'KEY user_course (user_id,course_id)',
					'KEY created_at (created_at)',
				),
			),
		);
	}

	/**
	 * Foreign keys between own tables only (E-48, R0-06 §4). Created by step
	 * `core:0003_foreign_keys` after `dbDelta`; failures are non-fatal.
	 *
	 * @return list<array{table: string, column: string, ref_table: string, ref_column: string, on_delete: string}>
	 */
	public static function foreign_keys(): array {
		$fk = static fn( string $table, string $column, string $ref_table, string $on_delete, string $ref_column = 'id' ): array => array(
			'table'      => $table,
			'column'     => $column,
			'ref_table'  => $ref_table,
			'ref_column' => $ref_column,
			'on_delete'  => $on_delete,
		);
		return array(
			$fk( 'ccap_question_revision', 'question_id', 'ccap_question', 'RESTRICT' ),
			$fk( 'ccap_question_tag', 'revision_id', 'ccap_question_revision', 'CASCADE' ),
			$fk( 'ccap_answer', 'revision_id', 'ccap_question_revision', 'CASCADE' ),
			$fk( 'ccap_module_question', 'question_id', 'ccap_question', 'CASCADE' ),
			$fk( 'ccap_activity_question', 'question_id', 'ccap_question', 'RESTRICT' ),
			$fk( 'ccap_content_media', 'media_id', 'ccap_media', 'RESTRICT' ),
			$fk( 'ccap_exam_blueprint', 'rule_id', 'ccap_exam_rule', 'CASCADE' ),
			$fk( 'ccap_exam_variant_question', 'variant_id', 'ccap_exam_variant', 'CASCADE' ),
			$fk( 'ccap_exam_variant_question', 'question_id', 'ccap_question', 'RESTRICT' ),
			$fk( 'ccap_exam_session', 'rule_id', 'ccap_exam_rule', 'RESTRICT' ),
			$fk( 'ccap_exam_session', 'variant_id', 'ccap_exam_variant', 'RESTRICT' ),
			$fk( 'ccap_exam_session', 'course_revision_id', 'ccap_course_revision', 'RESTRICT' ),
			$fk( 'ccap_exam_session_question', 'session_id', 'ccap_exam_session', 'CASCADE' ),
			$fk( 'ccap_exam_session_question', 'question_id', 'ccap_question', 'RESTRICT' ),
			$fk( 'ccap_exam_session_question', 'revision_id', 'ccap_question_revision', 'RESTRICT' ),
			$fk( 'ccap_attempt', 'question_id', 'ccap_question', 'RESTRICT' ),
			$fk( 'ccap_attempt', 'revision_id', 'ccap_question_revision', 'RESTRICT' ),
			$fk( 'ccap_attempt_answer', 'attempt_id', 'ccap_attempt', 'CASCADE' ),
			$fk( 'ccap_question_progress', 'question_id', 'ccap_question', 'RESTRICT' ),
			$fk( 'ccap_course_progress', 'course_revision_id', 'ccap_course_revision', 'RESTRICT' ),
			$fk( 'ccap_course_progress', 'completed_revision_id', 'ccap_course_revision', 'RESTRICT' ),
			$fk( 'ccap_favorite', 'question_id', 'ccap_question', 'CASCADE' ),
			$fk( 'ccap_collection_item', 'collection_id', 'ccap_collection', 'CASCADE' ),
			$fk( 'ccap_collection_item', 'question_id', 'ccap_question', 'CASCADE' ),
			$fk( 'ccap_group_course', 'group_id', 'ccap_group', 'CASCADE' ),
			$fk( 'ccap_seat', 'group_id', 'ccap_group', 'CASCADE' ),
			$fk( 'ccap_grant', 'seat_id', 'ccap_seat', 'SET NULL' ),
			$fk( 'ccap_grant_event', 'grant_id', 'ccap_grant', 'CASCADE' ),
			$fk( 'ccap_assignment_submission', 'course_revision_id', 'ccap_course_revision', 'RESTRICT' ),
			$fk( 'ccap_assignment_file', 'submission_id', 'ccap_assignment_submission', 'CASCADE' ),
			$fk( 'ccap_assignment_file', 'media_id', 'ccap_media', 'RESTRICT' ),
			$fk( 'ccap_assignment_grade', 'submission_id', 'ccap_assignment_submission', 'RESTRICT' ),
			$fk( 'ccap_certificate', 'course_revision_id', 'ccap_course_revision', 'RESTRICT' ),
			$fk( 'ccap_certificate', 'supersedes_id', 'ccap_certificate', 'RESTRICT' ),
			$fk( 'ccap_outbox_delivery', 'event_uuid', 'ccap_outbox_event', 'CASCADE', 'event_uuid' ),
			$fk( 'ccap_import_item_log', 'batch_id', 'ccap_import_batch', 'CASCADE' ),
			$fk( 'ccap_import_issue', 'batch_id', 'ccap_import_batch', 'CASCADE' ),
		);
	}

	/**
	 * Generated columns with their unique keys (R0-21 D21-4). Created by
	 * imperative steps because `dbDelta` cannot handle generated columns.
	 *
	 * @return array<string, array{table: string, column: string, definition: string, key: string, key_columns: list<string>}>
	 */
	public static function generated_columns(): array {
		return array(
			'core:0001_exam_session_running' => array(
				'table'       => 'ccap_exam_session',
				'column'      => 'running',
				'definition'  => "tinyint(1) GENERATED ALWAYS AS (IF(`status` = 'in_progress', 1, NULL)) STORED",
				'key'         => 'running_form',
				'key_columns' => array( 'user_id', 'form_key', 'running' ),
			),
			'core:0002_seat_active_user'     => array(
				'table'       => 'ccap_seat',
				'column'      => 'active_user',
				'definition'  => "bigint(20) unsigned GENERATED ALWAYS AS (IF(`status` = 'active', `user_id`, NULL)) STORED",
				'key'         => 'active_member',
				'key_columns' => array( 'group_id', 'active_user' ),
			),
		);
	}

	/**
	 * Tables with personal data (R0-06 §4.9), for export/eraser (R1-17).
	 *
	 * @return list<string>
	 */
	public static function personal_data_tables(): array {
		return array(
			'ccap_attempt',
			'ccap_question_progress',
			'ccap_activity_progress',
			'ccap_course_progress',
			'ccap_favorite',
			'ccap_collection',
			'ccap_exam_session',
			'ccap_grant',
			'ccap_seat',
			'ccap_certificate',
			'ccap_request_idempotency',
			'ccap_assignment_submission',
			'ccap_learner_lock',
			'ccap_accommodation',
			'ccap_course_staff',
			'ccap_notification',
			'ccap_attempt_answer',
			'ccap_exam_session_question',
			'ccap_collection_item',
			'ccap_grant_event',
			'ccap_assignment_file',
			'ccap_assignment_grade',
			'ccap_audit_log',
			'ccap_group',
			'ccap_outbox_event',
			'ccap_outbox_delivery',
			'ccap_reset_journal',
		);
	}

	/**
	 * Table names without prefix, in creation order.
	 *
	 * @return list<string>
	 */
	public static function table_names(): array {
		return array_keys( self::tables() );
	}

	/**
	 * Table names in drop order (children before parents).
	 *
	 * @return list<string>
	 */
	public static function drop_order(): array {
		return array_reverse( self::table_names() );
	}

	/**
	 * `CREATE TABLE` statement of one table for `dbDelta`.
	 *
	 * @param string $table   Table name without prefix.
	 * @param string $prefix  Site table prefix (`$wpdb->prefix`).
	 * @param string $charset Result of `$wpdb->get_charset_collate()`.
	 * @return string
	 */
	public static function create_statement( string $table, string $prefix, string $charset ): string {
		$definition = self::tables()[ $table ];
		$lines      = array();
		foreach ( $definition['columns'] as $column => $type ) {
			$lines[] = '`' . $column . '` ' . $type;
		}
		foreach ( $definition['keys'] as $key ) {
			$lines[] = $key;
		}
		return 'CREATE TABLE ' . $prefix . $table . " (\n" . implode( ",\n", $lines ) . "\n) ENGINE=InnoDB " . $charset . " COMMENT='" . self::OWNER_COMMENT . "';";
	}

	/**
	 * Parsed key definitions of a table: name => ordered column list
	 * (`PRIMARY` for the primary key). Prefix lengths are dropped.
	 *
	 * @param string $table Table name without prefix.
	 * @return array<string, array{unique: bool, columns: list<string>}>
	 */
	public static function key_columns( string $table ): array {
		$keys = array();
		foreach ( self::tables()[ $table ]['keys'] as $line ) {
			if ( 1 !== preg_match( '/^(PRIMARY KEY|UNIQUE KEY (\w+)|KEY (\w+))\s+\((.+)\)$/', $line, $m ) ) {
				continue;
			}
			$name    = 'PRIMARY KEY' === $m[1] ? 'PRIMARY' : ( '' !== $m[2] ? $m[2] : $m[3] );
			$columns = array();
			foreach ( explode( ',', $m[4] ) as $part ) {
				$columns[] = (string) preg_replace( '/\(\d+\)$/', '', trim( $part ) );
			}
			$keys[ $name ] = array(
				'unique'  => 'KEY' !== substr( $m[1], 0, 3 ) || 'PRIMARY' === $name,
				'columns' => $columns,
			);
		}
		return $keys;
	}

	/**
	 * Hash over all definitions (tables, keys, FKs, generated columns).
	 *
	 * @return string 40 hex characters.
	 */
	public static function definition_hash(): string {
		return sha1(
			(string) wp_json_encode(
				array(
					self::VERSION,
					self::tables(),
					self::foreign_keys(),
					self::generated_columns(),
				)
			)
		);
	}

	/**
	 * Value of `course_captain_db_version`: semver plus definition hash
	 * (R0-06 §6.1).
	 *
	 * @return string
	 */
	public static function db_version(): string {
		return self::VERSION . '+' . substr( self::definition_hash(), 0, 12 );
	}

	/**
	 * Value of `course_captain_schema_fingerprint`: plugin version plus
	 * definition hash (R0-06 §6.1, cheap frontend comparison).
	 *
	 * @param string $plugin_version Plugin version.
	 * @return string
	 */
	public static function fingerprint( string $plugin_version ): string {
		return substr( sha1( $plugin_version . '|' . self::definition_hash() ), 0, 16 );
	}

	/**
	 * Semver part of a stored db version value.
	 *
	 * @param string $db_version Stored value such as `1.0.0+abcdef123456`.
	 * @return string
	 */
	public static function semver_of( string $db_version ): string {
		$pos = strpos( $db_version, '+' );
		return false === $pos ? $db_version : substr( $db_version, 0, $pos );
	}
}
