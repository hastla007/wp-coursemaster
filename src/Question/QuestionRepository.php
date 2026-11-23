<?php
/**
 * Question bank with immutable revisions (R0-06 §4.1, §5; R0-08 §3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Question;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * - A question is identity (`ccap_question`) plus revisions.
 * - Saving computes `content_hash`; a draft revision is updated in place,
 *   a published revision is never changed: the change becomes a new
 *   revision (editorial change of a published revision = new revision).
 * - A known hash reactivates the existing revision (import rollback).
 * - Publishing validates the type rules, sets `current_revision_id` and
 *   deprecates the previous published revision.
 * - Questions are archived, never hard-deleted.
 */
final class QuestionRepository {

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
	 * Create a question with its first draft revision.
	 *
	 * @param Actor                $actor Author (needs `ccap_edit_courses`).
	 * @param array<string, mixed> $data  Revision data (see QuestionTypes) plus optional `tags`, `feedback_*`, `language`, `reference_no`.
	 * @return int Question ID.
	 * @throws CourseException When not allowed.
	 */
	public function create( Actor $actor, array $data ): int {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_edit_courses' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to create questions.', 403 );
		}
		return $this->db->transaction(
			function () use ( $actor, $data ): int {
				$question = $this->db->insert(
					'ccap_question',
					array(
						'uid'            => $this->db->uuid(),
						'reference_no'   => isset( $data['reference_no'] ) ? (string) $data['reference_no'] : null,
						'language'       => (string) ( $data['language'] ?? '' ),
						'status'         => 'draft',
						'author_user_id' => $actor->user_id,
					),
					array( 'created_at', 'updated_at' )
				);
				$revision = $this->insert_revision( $question, 1, $data, $actor->user_id, 'editorial' );
				$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_question' ) . '` SET current_revision_id = %d WHERE id = %d', $revision, $question ) );
				return $question;
			}
		);
	}

	/**
	 * Save new content. Draft revision → updated in place; published →
	 * new draft revision; known hash → that revision becomes the working one.
	 *
	 * @param Actor                $actor        Actor (M-07).
	 * @param int                  $question_id  Question.
	 * @param array<string, mixed> $data         Revision data.
	 * @param string               $change_class editorial|substantive (Δ-23-3).
	 * @return int Revision ID now holding the content.
	 */
	public function save( Actor $actor, int $question_id, array $data, string $change_class = 'editorial' ): int {
		$this->authorize( $actor, $question_id );
		$hash = self::content_hash( $data );
		return $this->db->transaction(
			function () use ( $actor, $question_id, $data, $change_class, $hash ): int {
				$table = $this->db->table( 'ccap_question_revision' );
				$known = $this->db->scalar( $this->db->prepare( "SELECT id FROM `{$table}` WHERE question_id = %d AND content_hash = %s ORDER BY revision_no DESC LIMIT 1", $question_id, $hash ) );
				if ( null !== $known ) {
					return (int) $known;
				}
				$latest = $this->db->row( $this->db->prepare( "SELECT id, revision_no, editorial_status FROM `{$table}` WHERE question_id = %d ORDER BY revision_no DESC LIMIT 1 FOR UPDATE", $question_id ) );
				if ( null !== $latest && 'draft' === $latest['editorial_status'] && ! $this->is_referenced( (int) $latest['id'] ) ) {
					$this->replace_draft( (int) $latest['id'], $data, $change_class );
					return (int) $latest['id'];
				}
				return $this->insert_revision( $question_id, null === $latest ? 1 : (int) $latest['revision_no'] + 1, $data, $actor->user_id, $change_class );
			}
		);
	}

	/**
	 * Always add a new draft revision (e.g. a type change, UI-F8): no
	 * existing revision is updated, so attempts and exam snapshots keep
	 * theirs; the published revision stays current until this one is published.
	 *
	 * @param Actor                $actor        Actor (M-07).
	 * @param int                  $question_id  Question.
	 * @param array<string, mixed> $data         Revision data.
	 * @param string               $change_class editorial|substantive.
	 * @return int New revision ID.
	 */
	public function add_revision( Actor $actor, int $question_id, array $data, string $change_class = 'substantive' ): int {
		$this->authorize( $actor, $question_id );
		return $this->db->transaction(
			function () use ( $actor, $question_id, $data, $change_class ): int {
				$table = $this->db->table( 'ccap_question_revision' );
				$no    = (int) $this->db->scalar( $this->db->prepare( "SELECT COALESCE(MAX(revision_no), 0) FROM `{$table}` WHERE question_id = %d FOR UPDATE", $question_id ) );
				return $this->insert_revision( $question_id, $no + 1, $data, $actor->user_id, $change_class );
			}
		);
	}

	/**
	 * Publish a revision (newest draft by default) after type validation.
	 *
	 * @param Actor    $actor       Actor (M-07).
	 * @param int      $question_id Question.
	 * @param int|null $revision_id Revision; null = newest.
	 * @return int Published revision ID.
	 * @throws CourseException With `ccap_validation_failed` and the error codes as message.
	 */
	public function publish( Actor $actor, int $question_id, ?int $revision_id = null ): int {
		$this->authorize( $actor, $question_id );
		$table = $this->db->table( 'ccap_question_revision' );
		$row   = null === $revision_id
			? $this->db->row( $this->db->prepare( "SELECT id FROM `{$table}` WHERE question_id = %d ORDER BY revision_no DESC LIMIT 1", $question_id ) )
			: $this->db->row( $this->db->prepare( "SELECT id FROM `{$table}` WHERE question_id = %d AND id = %d", $question_id, $revision_id ) );
		if ( null === $row ) {
			throw new CourseException( 'ccap_not_found', 'Unknown revision.', 404 );
		}
		$revision = (int) $row['id'];
		$errors   = QuestionTypes::validate( (array) $this->revision( $revision ) );
		if ( array() !== $errors ) {
			throw new CourseException( 'ccap_validation_failed', esc_html( implode( ',', $errors ) ), 422 );
		}
		$this->db->transaction(
			function () use ( $table, $question_id, $revision ): void {
				$now = $this->db->now();
				$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET editorial_status = 'deprecated' WHERE question_id = %d AND editorial_status = 'published' AND id <> %d", $question_id, $revision ) );
				$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET editorial_status = 'published', published_at = COALESCE(published_at, %s) WHERE id = %d", $now, $revision ) );
				$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_question' ) . "` SET status = 'published', current_revision_id = %d, updated_at = %s WHERE id = %d", $revision, $now, $question_id ) );
				// Editorial status of older revisions changed: drop their K1 entries; courses using the question see a new content generation (§3.7).
				foreach ( $this->db->rows( $this->db->prepare( "SELECT id FROM `{$table}` WHERE question_id = %d", $question_id ) ) as $old ) {
					$id = (int) $old['id'];
					$this->db->after_commit( static fn() => wp_cache_delete( \CourseCaptain\Cache\CacheStore::key( 'k1', 'shared', 'qrev:' . $id, array() ), \CourseCaptain\Cache\CacheStore::GROUP ) );
				}
				\CourseCaptain\Cache\CacheStore::bump_content();
			}
		);
		return $revision;
	}

	/**
	 * Archive a question (never hard delete, INV-C-108).
	 *
	 * @param Actor $actor       Actor.
	 * @param int   $question_id Question.
	 * @return void
	 */
	public function archive( Actor $actor, int $question_id ): void {
		$this->authorize( $actor, $question_id );
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_question' ) . "` SET status = 'archived', updated_at = %s WHERE id = %d", $this->db->now(), $question_id ) );
	}

	/**
	 * Add a question to a module pool (or change its position).
	 *
	 * @param Actor    $actor       Actor (M-06 on the module and M-07 on the question).
	 * @param int      $module_id   Module.
	 * @param int      $question_id Question.
	 * @param int|null $sort_order  Position; null sorts last (R0-08 §1.3).
	 * @return void
	 * @throws CourseException When not allowed.
	 */
	public function add_to_pool( Actor $actor, int $module_id, int $question_id, ?int $sort_order = null ): void {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_COURSE_ITEM, array( $module_id, 'module' ) ), 'Not allowed to edit this module.' );
		$this->authorize( $actor, $question_id );
		$table = $this->db->table( 'ccap_module_question' );
		$sql   = null === $sort_order
			? $this->db->prepare( "INSERT INTO `{$table}` (module_id, question_id, sort_order) VALUES (%d, %d, NULL) ON DUPLICATE KEY UPDATE sort_order = NULL", $module_id, $question_id )
			: $this->db->prepare( "INSERT INTO `{$table}` (module_id, question_id, sort_order) VALUES (%d, %d, %d) ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)", $module_id, $question_id, $sort_order );
		$this->db->execute( $sql );
	}

	/**
	 * Deliverable questions of a module pool in order: published, not
	 * archived, with a published current revision (drafts never, GM-023).
	 *
	 * @param int $module_id Module.
	 * @return list<int>
	 */
	public function pool( int $module_id ): array {
		return array_keys( $this->pool_revisions( $module_id ) );
	}

	/**
	 * Deliverable pool with the current revision per question, in pool order
	 * (exam draw: no second read of the current revisions, R0-14 OP-05).
	 *
	 * @param int $module_id Module.
	 * @return array<int, int> Question => current revision.
	 */
	public function pool_revisions( int $module_id ): array {
		$mq   = $this->db->table( 'ccap_module_question' );
		$q    = $this->db->table( 'ccap_question' );
		$rev  = $this->db->table( 'ccap_question_revision' );
		$rows = $this->db->rows(
			$this->db->prepare(
				"SELECT q.id, q.current_revision_id FROM `{$mq}` mq JOIN `{$q}` q ON q.id = mq.question_id JOIN `{$rev}` r ON r.id = q.current_revision_id WHERE mq.module_id = %d AND q.status = 'published' AND r.editorial_status = 'published' AND (r.valid_from IS NULL OR r.valid_from <= %s) AND (r.valid_to IS NULL OR r.valid_to >= %s) ORDER BY COALESCE(mq.sort_order, 2147483647), q.id",
				$module_id,
				gmdate( 'Y-m-d', $this->db->time() ),
				gmdate( 'Y-m-d', $this->db->time() )
			)
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row['id'] ] = (int) $row['current_revision_id'];
		}
		return $out;
	}

	/**
	 * One question of the deliverable pool (same conditions as `pool()`) with
	 * its current revision and UID, or null (one query, R0-14 OP-03/OP-04).
	 *
	 * @param int $module_id   Module.
	 * @param int $question_id Question.
	 * @return array{current_revision_id: int, uid: string}|null
	 */
	public function pool_member( int $module_id, int $question_id ): ?array {
		$mq  = $this->db->table( 'ccap_module_question' );
		$q   = $this->db->table( 'ccap_question' );
		$rev = $this->db->table( 'ccap_question_revision' );
		$row = $this->db->row(
			$this->db->prepare(
				"SELECT q.current_revision_id, q.uid FROM `{$mq}` mq JOIN `{$q}` q ON q.id = mq.question_id JOIN `{$rev}` r ON r.id = q.current_revision_id WHERE mq.module_id = %d AND mq.question_id = %d AND q.status = 'published' AND r.editorial_status = 'published' AND (r.valid_from IS NULL OR r.valid_from <= %s) AND (r.valid_to IS NULL OR r.valid_to >= %s) LIMIT 1",
				$module_id,
				$question_id,
				gmdate( 'Y-m-d', $this->db->time() ),
				gmdate( 'Y-m-d', $this->db->time() )
			)
		);
		return null === $row ? null : array(
			'current_revision_id' => (int) $row['current_revision_id'],
			'uid'                 => (string) $row['uid'],
		);
	}

	/**
	 * Revision data in the shape of QuestionTypes, incl. tags and feedback.
	 *
	 * @param int $revision_id Revision.
	 * @return array<string, mixed>|null
	 */
	public function revision( int $revision_id ): ?array {
		$key = \CourseCaptain\Cache\CacheStore::key( 'k1', 'shared', 'qrev:' . $revision_id, array() );
		$hit = wp_cache_get( $key, \CourseCaptain\Cache\CacheStore::GROUP );
		if ( is_array( $hit ) && array_key_exists( 'v', $hit ) ) {
			return $hit['v'];
		}
		$value = $this->load_revision( $revision_id );
		// K1 only once immutable: drafts are still edited in place.
		if ( null !== $value && 'draft' !== $value['editorial_status'] ) {
			wp_cache_set( $key, array( 'v' => $value ), \CourseCaptain\Cache\CacheStore::GROUP, \CourseCaptain\Cache\CacheStore::TTL_K1 );
		}
		return $value;
	}

	/**
	 * Revision data from the database.
	 *
	 * @param int $revision_id Revision.
	 * @return array<string, mixed>|null
	 */
	private function load_revision( int $revision_id ): ?array {
		return $this->load_many( array( $revision_id ) )[ $revision_id ] ?? null;
	}

	/**
	 * Several revisions with three queries in total (no per-revision queries, R0-14 OP-05).
	 * Immutable revisions are put into the K1 cache like `revision()`.
	 *
	 * @param array<int> $revision_ids Revisions.
	 * @return array<int, array<string, mixed>> Keyed by revision ID; unknown IDs are missing.
	 */
	public function revisions( array $revision_ids ): array {
		$out     = array();
		$missing = array();
		foreach ( array_values( array_unique( array_map( 'intval', $revision_ids ) ) ) as $id ) {
			$hit = wp_cache_get( \CourseCaptain\Cache\CacheStore::key( 'k1', 'shared', 'qrev:' . $id, array() ), \CourseCaptain\Cache\CacheStore::GROUP );
			if ( is_array( $hit ) && array_key_exists( 'v', $hit ) && is_array( $hit['v'] ) ) {
				$out[ $id ] = $hit['v'];
			} else {
				$missing[] = $id;
			}
		}
		foreach ( $this->load_many( $missing ) as $id => $value ) {
			if ( 'draft' !== $value['editorial_status'] ) {
				wp_cache_set( \CourseCaptain\Cache\CacheStore::key( 'k1', 'shared', 'qrev:' . $id, array() ), array( 'v' => $value ), \CourseCaptain\Cache\CacheStore::GROUP, \CourseCaptain\Cache\CacheStore::TTL_K1 );
			}
			$out[ $id ] = $value;
		}
		return $out;
	}

	/**
	 * Load revisions from the database (two queries: revisions, then answers and tags together).
	 *
	 * @param array<int> $ids Revision IDs.
	 * @return array<int, array<string, mixed>>
	 */
	private function load_many( array $ids ): array {
		$ids = array_values( array_filter( array_map( 'intval', $ids ), static fn( int $id ): bool => $id > 0 ) );
		if ( array() === $ids ) {
			return array();
		}
		$in      = implode( ',', $ids );
		$rows    = $this->db->rows( 'SELECT * FROM `' . $this->db->table( 'ccap_question_revision' ) . "` WHERE id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		$answers = array();
		$tags    = array();
		// Answers and tags in one statement (R0-14 OP-03/OP-05): part 0 = answer (sort order, key, text, correct), part 1 = tag (type, key, label).
		$parts = $this->db->rows(
			'SELECT 0 AS part, revision_id, sort_order AS s, answer_key AS k, answer_text AS t, is_correct AS c FROM `' . $this->db->table( 'ccap_answer' ) . "` WHERE revision_id IN ({$in})" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
			. ' UNION ALL SELECT 1, revision_id, 0, tag_type, tag_key, label FROM `' . $this->db->table( 'ccap_question_tag' ) . "` WHERE revision_id IN ({$in}) ORDER BY part, revision_id, s, k, t" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		);
		foreach ( $parts as $row ) {
			if ( '0' === (string) $row['part'] ) {
				$answers[ (int) $row['revision_id'] ][] = array(
					'key'     => (string) $row['k'],
					'text'    => (string) $row['t'],
					'correct' => '1' === (string) $row['c'],
					'sort'    => (int) $row['s'],
				);
				continue;
			}
			$tags[ (int) $row['revision_id'] ][] = array(
				'type'  => (string) $row['k'],
				'key'   => (string) $row['t'],
				'label' => (string) $row['c'],
			);
		}
		$out = array();
		foreach ( $rows as $row ) {
			$id         = (int) $row['id'];
			$decoded    = json_decode( (string) $row['settings'], true );
			$out[ $id ] = array(
				'id'                 => $id,
				'question_id'        => (int) $row['question_id'],
				'revision_no'        => (int) $row['revision_no'],
				'content_hash'       => (string) $row['content_hash'],
				'editorial_status'   => (string) $row['editorial_status'],
				'question_type'      => (string) $row['question_type'],
				'question_text'      => (string) $row['question_text'],
				'settings'           => is_array( $decoded ) ? $decoded : array(),
				'feedback_correct'   => $row['feedback_correct'],
				'feedback_incorrect' => $row['feedback_incorrect'],
				'answers'            => $answers[ $id ] ?? array(),
				'tags'               => $tags[ $id ] ?? array(),
			);
		}
		return $out;
	}


	/**
	 * Canonical content hash (`sha256:` + 64 hex) over everything that
	 * matters for learners and evaluation.
	 *
	 * @param array<string, mixed> $data Revision data.
	 * @return string
	 */
	public static function content_hash( array $data ): string {
		$answers = array_map(
			static fn( array $a ): array => array( (string) $a['key'], (string) $a['text'], ! empty( $a['correct'] ), (int) ( $a['sort'] ?? 0 ), ! empty( $a['pinned'] ) ),
			array_values( (array) ( $data['answers'] ?? array() ) )
		);
		$tags    = array_map(
			static fn( array $t ): array => array( (string) $t['type'], (string) $t['key'], (string) ( $t['label'] ?? '' ) ),
			array_values( (array) ( $data['tags'] ?? array() ) )
		);
		sort( $tags );
		$canonical = array(
			(string) ( $data['question_type'] ?? '' ),
			(string) ( $data['question_text'] ?? '' ),
			self::sorted( (array) ( $data['settings'] ?? array() ) ),
			$answers,
			(string) ( $data['feedback_correct'] ?? '' ),
			(string) ( $data['feedback_incorrect'] ?? '' ),
			$tags,
		);
		return 'sha256:' . hash( 'sha256', (string) wp_json_encode( $canonical ) );
	}

	/**
	 * Insert a revision with answers and tags.
	 *
	 * @param int                  $question_id  Question.
	 * @param int                  $revision_no  Number.
	 * @param array<string, mixed> $data         Data.
	 * @param int                  $user_id      Creator.
	 * @param string               $change_class Class.
	 * @return int
	 */
	private function insert_revision( int $question_id, int $revision_no, array $data, int $user_id, string $change_class ): int {
		$revision = $this->db->insert(
			'ccap_question_revision',
			array(
				'question_id'        => $question_id,
				'revision_no'        => $revision_no,
				'content_hash'       => self::content_hash( $data ),
				'question_type'      => (string) ( $data['question_type'] ?? '' ),
				'change_class'       => in_array( $change_class, array( 'editorial', 'substantive' ), true ) ? $change_class : 'editorial',
				'question_text'      => (string) ( $data['question_text'] ?? '' ),
				'settings'           => (string) wp_json_encode( (array) ( $data['settings'] ?? array() ) ),
				'feedback_correct'   => isset( $data['feedback_correct'] ) ? (string) $data['feedback_correct'] : null,
				'feedback_incorrect' => isset( $data['feedback_incorrect'] ) ? (string) $data['feedback_incorrect'] : null,
				'image_required'     => empty( $data['image_required'] ) ? 0 : 1,
				'editorial_status'   => 'draft',
				'rights_status'      => (string) ( $data['rights_status'] ?? 'own' ),
				// Carried over by edits that only change tags (UI-F8): rights and validity stay.
				'rights_owner'       => isset( $data['rights_owner'] ) ? (string) $data['rights_owner'] : null,
				'license_reference'  => isset( $data['license_reference'] ) ? (string) $data['license_reference'] : null,
				'valid_from'         => isset( $data['valid_from'] ) ? (string) $data['valid_from'] : null,
				'valid_to'           => isset( $data['valid_to'] ) ? (string) $data['valid_to'] : null,
				'created_by'         => $user_id > 0 ? $user_id : null,
			),
			array( 'created_at' )
		);
		$this->write_children( $revision, $data );
		return $revision;
	}

	/**
	 * Replace the content of an unreferenced draft revision.
	 *
	 * @param int                  $revision_id  Revision.
	 * @param array<string, mixed> $data         Data.
	 * @param string               $change_class Class.
	 * @return void
	 */
	private function replace_draft( int $revision_id, array $data, string $change_class ): void {
		$this->db->execute(
			$this->db->prepare(
				'UPDATE `' . $this->db->table( 'ccap_question_revision' ) . "` SET content_hash = %s, question_type = %s, change_class = %s, question_text = %s, settings = %s, feedback_correct = %s, feedback_incorrect = %s WHERE id = %d AND editorial_status = 'draft'",
				self::content_hash( $data ),
				(string) ( $data['question_type'] ?? '' ),
				in_array( $change_class, array( 'editorial', 'substantive' ), true ) ? $change_class : 'editorial',
				(string) ( $data['question_text'] ?? '' ),
				(string) wp_json_encode( (array) ( $data['settings'] ?? array() ) ),
				(string) ( $data['feedback_correct'] ?? '' ),
				(string) ( $data['feedback_incorrect'] ?? '' ),
				$revision_id
			)
		);
		$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_answer' ) . '` WHERE revision_id = %d', $revision_id ) );
		$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_question_tag' ) . '` WHERE revision_id = %d', $revision_id ) );
		$this->write_children( $revision_id, $data );
	}

	/**
	 * Answers and tags of a revision.
	 *
	 * @param int                  $revision_id Revision.
	 * @param array<string, mixed> $data        Data.
	 * @return void
	 */
	private function write_children( int $revision_id, array $data ): void {
		foreach ( array_values( (array) ( $data['answers'] ?? array() ) ) as $i => $answer ) {
			$this->db->insert(
				'ccap_answer',
				array(
					'revision_id' => $revision_id,
					'answer_key'  => (string) $answer['key'],
					'answer_text' => (string) $answer['text'],
					'is_correct'  => empty( $answer['correct'] ) ? 0 : 1,
					'sort_order'  => (int) ( $answer['sort'] ?? $i ),
				)
			);
		}
		foreach ( array_values( (array) ( $data['tags'] ?? array() ) ) as $tag ) {
			$this->db->insert(
				'ccap_question_tag',
				array(
					'revision_id' => $revision_id,
					'tag_type'    => (string) $tag['type'],
					'tag_key'     => (string) $tag['key'],
					'label'       => (string) ( $tag['label'] ?? '' ),
				)
			);
		}
	}

	/**
	 * Whether attempts or exam sessions reference a revision.
	 *
	 * @param int $revision_id Revision.
	 * @return bool
	 */
	private function is_referenced( int $revision_id ): bool {
		foreach ( array( 'ccap_attempt', 'ccap_exam_session_question' ) as $table ) {
			if ( null !== $this->db->scalar( $this->db->prepare( 'SELECT 1 FROM `' . $this->db->table( $table ) . '` WHERE revision_id = %d LIMIT 1', $revision_id ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * M-07 or exception.
	 *
	 * @param Actor $actor       Actor.
	 * @param int   $question_id Question.
	 * @return void
	 */
	private function authorize( Actor $actor, int $question_id ): void {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_QUESTION, array( $question_id ) ), 'Not allowed to edit this question.' );
	}

	/**
	 * Recursively key-sorted array (canonical JSON).
	 *
	 * @param array<mixed> $value Value.
	 * @return array<mixed>
	 */
	private static function sorted( array $value ): array {
		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = self::sorted( $item );
			}
		}
		return $value;
	}
}
