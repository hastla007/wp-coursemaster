<?php
/**
 * Favorites and collections (R0-05 PAR-05 GM-055…GM-060; R0-06 E-30).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learning;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Favorites belong to the user; collections belong to user and course
 * (max. 30 per course, name ≤ 60 characters, trimmed, unique without
 * case). Only favorites can be put into a collection; removing a favorite
 * removes it from all collections, deleting a collection keeps the
 * favorites. Every question must be in an accessible module of the course
 * (partial access, GM-058); foreign collections are 404.
 */
final class Favorites {

	public const MAX_COLLECTIONS = 30;
	public const MAX_NAME        = 60;
	public const SORTS           = array( 'saved_desc', 'saved_asc', 'assigned_desc', 'assigned_asc' );

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
	 * Mark a question (idempotent).
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @param int   $question  Question.
	 * @return void
	 */
	public function add( Actor $actor, int $course_id, int $question ): void {
		$this->assert_accessible( $actor, $course_id, $question );
		$this->db->execute(
			$this->db->prepare(
				'INSERT IGNORE INTO `' . $this->db->table( 'ccap_favorite' ) . "` (user_id, question_id, context, created_at) VALUES (%d, %d, 'question', %s)",
				$actor->user_id,
				$question,
				$this->db->now()
			)
		);
	}

	/**
	 * Unmark a question; it leaves all collections of the user (GM-059).
	 *
	 * @param Actor $actor    Learner.
	 * @param int   $question Question.
	 * @return void
	 */
	public function remove( Actor $actor, int $question ): void {
		$this->db->transaction(
			function ( Database $db ) use ( $actor, $question ): void {
				$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_favorite' ) . "` WHERE user_id = %d AND question_id = %d AND context = 'question'", $actor->user_id, $question ) );
				$db->execute(
					$db->prepare(
						'DELETE i FROM `' . $db->table( 'ccap_collection_item' ) . '` i JOIN `' . $db->table( 'ccap_collection' ) . '` c ON c.id = i.collection_id WHERE c.user_id = %d AND i.question_id = %d',
						$actor->user_id,
						$question
					)
				);
			}
		);
	}

	/**
	 * Favorites of a course with their collections (GM-057, GM-060).
	 *
	 * @param Actor    $actor      Learner.
	 * @param int      $course_id  Course.
	 * @param string   $sort       saved_desc|saved_asc|assigned_desc|assigned_asc.
	 * @param int|null $collection Only this collection.
	 * @return list<array{question_id: int, saved_at: string|null, assigned_at: string|null, collections: list<int>}>
	 * @throws CourseException `ccap_invalid_param` (422) for invalid sorting.
	 */
	public function list( Actor $actor, int $course_id, string $sort = 'saved_desc', ?int $collection = null ): array {
		if ( ! in_array( $sort, self::SORTS, true ) || ( str_starts_with( $sort, 'assigned' ) && null === $collection ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid sort.', 422 );
		}
		if ( null !== $collection ) {
			$this->owned( $actor, $collection, $course_id );
		}
		$questions = $this->course_questions( $course_id );
		if ( array() === $questions ) {
			return array();
		}
		$ids   = implode( ',', $questions );
		$fav   = $this->db->table( 'ccap_favorite' );
		$item  = $this->db->table( 'ccap_collection_item' );
		$col   = $this->db->table( 'ccap_collection' );
		$where = null === $collection ? '' : $this->db->prepare( ' AND f.question_id IN (SELECT question_id FROM `' . $item . '` WHERE collection_id = %d)', $collection );
		$rows  = $this->db->rows( $this->db->prepare( "SELECT f.question_id, f.created_at FROM `{$fav}` f WHERE f.user_id = %d AND f.context = 'question' AND f.question_id IN ({$ids})", $actor->user_id ) . $where );
		$links = $this->db->rows( $this->db->prepare( "SELECT i.question_id, i.collection_id, i.created_at FROM `{$item}` i JOIN `{$col}` c ON c.id = i.collection_id WHERE c.user_id = %d AND c.course_id = %d ORDER BY i.collection_id", $actor->user_id, $course_id ) );
		$out   = array();
		foreach ( $rows as $row ) {
			$q         = (int) $row['question_id'];
			$saved     = self::date( $row['created_at'] );
			$assigned  = null;
			$member_of = array();
			foreach ( $links as $link ) {
				if ( (int) $link['question_id'] !== $q ) {
					continue;
				}
				$member_of[] = (int) $link['collection_id'];
				if ( (int) $link['collection_id'] === $collection ) {
					$assigned = self::date( $link['created_at'] );
				}
			}
			$out[] = array(
				'question_id' => $q,
				'saved_at'    => $saved,
				'assigned_at' => $assigned,
				'collections' => $member_of,
			);
		}
		$key  = str_starts_with( $sort, 'assigned' ) ? 'assigned_at' : 'saved_at';
		$desc = str_ends_with( $sort, 'desc' );
		usort(
			$out,
			static function ( array $a, array $b ) use ( $key, $desc ): int {
				// Zero dates last, then time, then question ID (stable, GM-060).
				if ( ( null === $a[ $key ] ) !== ( null === $b[ $key ] ) ) {
					return null === $a[ $key ] ? 1 : -1;
				}
				$cmp = strcmp( (string) $a[ $key ], (string) $b[ $key ] );
				$cmp = 0 === $cmp ? $a['question_id'] <=> $b['question_id'] : $cmp;
				return $desc ? -$cmp : $cmp;
			}
		);
		return $out;
	}

	/**
	 * Create a collection (GM-056).
	 *
	 * @param Actor  $actor     Learner.
	 * @param int    $course_id Course.
	 * @param string $name      Name.
	 * @return int
	 * @throws CourseException For invalid or duplicate names and the limit.
	 */
	public function create_collection( Actor $actor, int $course_id, string $name ): int {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::LEARN, array( $course_id ) ), 'No learning access.' );
		$name  = $this->valid_name( $actor, $course_id, $name, null );
		$table = $this->db->table( 'ccap_collection' );
		if ( (int) $this->db->scalar( $this->db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d AND course_id = %d", $actor->user_id, $course_id ) ) >= self::MAX_COLLECTIONS ) {
			throw new CourseException( 'ccap_limit_reached', 'Collection limit reached.', 422 );
		}
		return $this->db->insert(
			'ccap_collection',
			array(
				'user_id'   => $actor->user_id,
				'course_id' => $course_id,
				'name'      => $name,
			),
			array( 'created_at' )
		);
	}

	/**
	 * Rename a collection.
	 *
	 * @param Actor  $actor      Learner.
	 * @param int    $collection Collection.
	 * @param string $name       New name.
	 * @return void
	 */
	public function rename_collection( Actor $actor, int $collection, string $name ): void {
		$row  = $this->owned( $actor, $collection );
		$name = $this->valid_name( $actor, (int) $row['course_id'], $name, $collection );
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_collection' ) . '` SET name = %s WHERE id = %d', $name, $collection ) );
	}

	/**
	 * Delete a collection; favorites stay (GM-059).
	 *
	 * @param Actor $actor      Learner.
	 * @param int   $collection Collection.
	 * @return void
	 */
	public function delete_collection( Actor $actor, int $collection ): void {
		$this->owned( $actor, $collection );
		$this->db->transaction(
			function ( Database $db ) use ( $collection ): void {
				$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_collection_item' ) . '` WHERE collection_id = %d', $collection ) );
				$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_collection' ) . '` WHERE id = %d', $collection ) );
			}
		);
	}

	/**
	 * Put a favorite into a collection (idempotent).
	 *
	 * @param Actor $actor      Learner.
	 * @param int   $collection Collection.
	 * @param int   $question   Question.
	 * @return void
	 * @throws CourseException `ccap_not_favorite` (422).
	 */
	public function assign( Actor $actor, int $collection, int $question ): void {
		$row = $this->owned( $actor, $collection );
		$this->assert_accessible( $actor, (int) $row['course_id'], $question );
		$fav = $this->db->scalar( $this->db->prepare( 'SELECT id FROM `' . $this->db->table( 'ccap_favorite' ) . "` WHERE user_id = %d AND question_id = %d AND context = 'question'", $actor->user_id, $question ) );
		if ( null === $fav ) {
			throw new CourseException( 'ccap_not_favorite', 'Only favorites can be added.', 422 );
		}
		$this->db->execute( $this->db->prepare( 'INSERT IGNORE INTO `' . $this->db->table( 'ccap_collection_item' ) . '` (collection_id, question_id, created_at) VALUES (%d, %d, %s)', $collection, $question, $this->db->now() ) );
	}

	/**
	 * Remove a question from a collection.
	 *
	 * @param Actor $actor      Learner.
	 * @param int   $collection Collection.
	 * @param int   $question   Question.
	 * @return void
	 */
	public function unassign( Actor $actor, int $collection, int $question ): void {
		$this->owned( $actor, $collection );
		$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_collection_item' ) . '` WHERE collection_id = %d AND question_id = %d', $collection, $question ) );
	}

	/**
	 * Question IDs of a learning mode `favorites` (optionally one collection).
	 *
	 * @param Actor    $actor      Learner.
	 * @param int      $course_id  Course.
	 * @param int|null $collection Collection.
	 * @return list<int>
	 */
	public function question_ids( Actor $actor, int $course_id, ?int $collection = null ): array {
		$out = array();
		foreach ( $this->list( $actor, $course_id, 'saved_desc', $collection ) as $item ) {
			if ( $this->accessible( $actor, $course_id, $item['question_id'] ) ) {
				$out[] = $item['question_id'];
			}
		}
		return $out;
	}

	/**
	 * Whether a question is in an accessible module of the course.
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @param int   $question  Question.
	 * @return bool
	 */
	public function accessible( Actor $actor, int $course_id, int $question ): bool {
		foreach ( $this->modules_of( $course_id, $question ) as $module ) {
			if ( $this->policy->decide( $actor, PolicyService::LEARN, array( $course_id ), array( 'module' => $module ) )->allowed ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Reject questions outside the course (404) or outside the accessible scope.
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @param int   $question  Question.
	 * @return void
	 * @throws CourseException 404 or the learning denial.
	 */
	private function assert_accessible( Actor $actor, int $course_id, int $question ): void {
		$modules = $this->modules_of( $course_id, $question );
		if ( array() === $modules ) {
			throw new CourseException( 'ccap_not_found', 'Unknown question.', 404 );
		}
		foreach ( $modules as $module ) {
			$decision = $this->policy->decide( $actor, PolicyService::LEARN, array( $course_id ), array( 'module' => $module ) );
			if ( $decision->allowed ) {
				return;
			}
		}
		CourseException::unless_allowed( $decision, 'No learning access.' );
	}

	/**
	 * Modules of the course whose pool contains the published question.
	 *
	 * @param int $course_id Course.
	 * @param int $question  Question.
	 * @return list<int>
	 */
	private function modules_of( int $course_id, int $question ): array {
		$pool = $this->db->table( 'ccap_module_question' );
		$cm   = $this->db->table( 'ccap_course_module' );
		$q    = $this->db->table( 'ccap_question' );
		return array_map(
			'intval',
			$this->db->wpdb()->get_col(
				$this->db->prepare( "SELECT p.module_id FROM `{$pool}` p JOIN `{$cm}` cm ON cm.module_id = p.module_id JOIN `{$q}` q ON q.id = p.question_id WHERE cm.course_id = %d AND p.question_id = %d AND q.status = 'published' ORDER BY cm.sort_order, p.module_id", $course_id, $question )
			)
		);
	}

	/**
	 * Question IDs in the pools of a course.
	 *
	 * @param int $course_id Course.
	 * @return list<int>
	 */
	private function course_questions( int $course_id ): array {
		$pool = $this->db->table( 'ccap_module_question' );
		$cm   = $this->db->table( 'ccap_course_module' );
		return array_map( 'intval', $this->db->wpdb()->get_col( $this->db->prepare( "SELECT DISTINCT p.question_id FROM `{$pool}` p JOIN `{$cm}` cm ON cm.module_id = p.module_id WHERE cm.course_id = %d", $course_id ) ) );
	}

	/**
	 * Own collection or 404 (no existence oracle).
	 *
	 * @param Actor    $actor      Learner.
	 * @param int      $collection Collection.
	 * @param int|null $course_id  Expected course.
	 * @return array<string, mixed>
	 * @throws CourseException 404.
	 */
	private function owned( Actor $actor, int $collection, ?int $course_id = null ): array {
		$row = $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_collection' ) . '` WHERE id = %d', $collection ) );
		if ( null === $row || (int) $row['user_id'] !== $actor->user_id || ( null !== $course_id && (int) $row['course_id'] !== $course_id ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown collection.', 404 );
		}
		return $row;
	}

	/**
	 * Clean and validate a name.
	 *
	 * @param Actor    $actor     Learner.
	 * @param int      $course_id Course.
	 * @param string   $name      Raw name.
	 * @param int|null $except    Collection being renamed.
	 * @return string
	 * @throws CourseException `ccap_validation_failed` or `ccap_duplicate`.
	 */
	private function valid_name( Actor $actor, int $course_id, string $name, ?int $except ): string {
		$name = trim( (string) preg_replace( '/\s+/u', ' ', sanitize_text_field( $name ) ) );
		if ( '' === $name || mb_strlen( $name ) > self::MAX_NAME ) {
			throw new CourseException( 'ccap_validation_failed', 'Invalid collection name.', 422 );
		}
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT id, name FROM `' . $this->db->table( 'ccap_collection' ) . '` WHERE user_id = %d AND course_id = %d', $actor->user_id, $course_id ) ) as $row ) {
			if ( (int) $row['id'] !== $except && mb_strtolower( (string) $row['name'] ) === mb_strtolower( $name ) ) {
				throw new CourseException( 'ccap_duplicate', 'A collection with this name exists.', 409 );
			}
		}
		return $name;
	}

	/**
	 * Null for zero dates.
	 *
	 * @param mixed $value DB value.
	 * @return string|null
	 */
	private static function date( $value ): ?string {
		$value = (string) $value;
		return '' === $value || str_starts_with( $value, '0000-00-00' ) ? null : $value;
	}
}
