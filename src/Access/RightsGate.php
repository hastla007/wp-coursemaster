<?php
/**
 * Delivery lock for foreign content with unclear or restricted rights (R0-10 §2.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Cache\CacheStore;
use CourseCaptain\Import\Rights;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * One gate for every delivery to learners (REST learning routes, lesson
 * view, quizzes from the module pool, flashcard decks, smart trainer,
 * exam draw, protected file route): foreign content (R0-10 §2.2) with the
 * rights status `unknown` or `restricted` or an expired `valid_until` is
 * withheld ({@see Rights::withheld()}). Own content is never withheld
 * (E-18). The editorial side (admin preview, editors, import dry run) is
 * exempt and shows a notice instead ({@see self::notice()}).
 *
 * - Questions and flashcards: effective status (local override, else the
 *   current revision), licence of the current revision. A question with
 *   `image_required` whose linked media are all withheld is withheld too
 *   (dependency rule, R0-10 §2.2).
 * - Media: `ccap_media` status and licence; locally uploaded media count
 *   as foreign once their status is not `own` ({@see Rights::media_foreign()}).
 * - Lessons: `_ccap_rights` of the lesson (imported from packages); a
 *   withheld lesson stays listed, its content is replaced by a placeholder.
 *
 * Caching (R0-21 §3.1/§3.7): the verdict is a decision (K3) and lives only
 * in this instance (one operation); it is never stored in a persistent
 * cache, so a rights change applies with the next request. Rights changes
 * bump the course generation of the affected courses ({@see self::changed()})
 * so that generation-versioned course data (K1g/K2) is recomputed.
 */
final class RightsGate {

	/**
	 * Verdicts of this operation (`q:<id>`, `m:<id>`, `p:<id>` → reason or '').
	 *
	 * @var array<string, string>
	 */
	private array $memo = array();

	/**
	 * Constructor.
	 *
	 * @param Database $db     Database access.
	 * @param Rights   $rights Rights rules (origin per source system).
	 */
	public function __construct( private Database $db, private Rights $rights ) {
	}

	/**
	 * Instance for the current site (one operation).
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, new Rights( $db ) );
	}

	/**
	 * Withheld questions or flashcards among the given ones.
	 *
	 * @param array<int> $ids Question IDs.
	 * @return array<int, string> Question → reason (`unknown`, `restricted`, `expired`).
	 */
	public function questions( array $ids ): array {
		$ids     = array_values( array_unique( array_filter( array_map( 'intval', $ids ), static fn( int $id ): bool => $id > 0 ) ) );
		$missing = array_values( array_filter( $ids, fn( int $id ): bool => ! isset( $this->memo[ 'q:' . $id ] ) ) );
		if ( array() !== $missing ) {
			$this->load_questions( $missing );
		}
		$out = array();
		foreach ( $ids as $id ) {
			if ( '' !== $this->memo[ 'q:' . $id ] ) {
				$out[ $id ] = $this->memo[ 'q:' . $id ];
			}
		}
		return $out;
	}

	/**
	 * Reason a question is withheld, or null.
	 *
	 * @param int $id Question.
	 * @return string|null
	 */
	public function question( int $id ): ?string {
		return $this->questions( array( $id ) )[ $id ] ?? null;
	}

	/**
	 * Questions without the withheld ones, order kept.
	 *
	 * @param array<int> $ids Question IDs.
	 * @return list<int>
	 */
	public function deliverable( array $ids ): array {
		$withheld = $this->questions( $ids );
		return array_values( array_filter( array_map( 'intval', $ids ), static fn( int $id ): bool => ! isset( $withheld[ $id ] ) ) );
	}

	/**
	 * Withheld media among the given ones.
	 *
	 * @param array<int> $ids Media IDs.
	 * @return array<int, string> Medium → reason.
	 */
	public function media( array $ids ): array {
		$ids     = array_values( array_unique( array_filter( array_map( 'intval', $ids ), static fn( int $id ): bool => $id > 0 ) ) );
		$missing = array_values( array_filter( $ids, fn( int $id ): bool => ! isset( $this->memo[ 'm:' . $id ] ) ) );
		if ( array() !== $missing ) {
			$today = $this->today();
			foreach ( $missing as $id ) {
				$this->memo[ 'm:' . $id ] = '';
			}
			$rows = $this->db->rows( 'SELECT id, source_system, rights_status, license_reference FROM `' . $this->db->table( 'ccap_media' ) . '` WHERE id IN (' . implode( ',', $missing ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
			foreach ( $rows as $row ) {
				$status                                = (string) $row['rights_status'];
				$this->memo[ 'm:' . (int) $row['id'] ] = (string) Rights::withheld( $status, self::license( $row['license_reference'] ?? null ), $this->rights->media_foreign( (string) ( $row['source_system'] ?? '' ), $status ), $today );
			}
		}
		$out = array();
		foreach ( $ids as $id ) {
			if ( '' !== $this->memo[ 'm:' . $id ] ) {
				$out[ $id ] = $this->memo[ 'm:' . $id ];
			}
		}
		return $out;
	}

	/**
	 * Reason a medium is withheld, or null.
	 *
	 * @param int $id Medium.
	 * @return string|null
	 */
	public function medium( int $id ): ?string {
		return $this->media( array( $id ) )[ $id ] ?? null;
	}

	/**
	 * Reason a medium (by UID) is withheld, or null (unknown UIDs: null).
	 *
	 * @param string $uid Media UID.
	 * @return string|null
	 */
	public function medium_uid( string $uid ): ?string {
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/', $uid ) ) {
			return null;
		}
		$id = (int) $this->db->scalar( $this->db->prepare( 'SELECT id FROM `' . $this->db->table( 'ccap_media' ) . '` WHERE uid = %s', $uid ) );
		return $id > 0 ? $this->medium( $id ) : null;
	}

	/**
	 * Reason a lesson (or another post with `_ccap_rights`) is withheld, or null.
	 *
	 * @param int $post_id Post.
	 * @return string|null
	 */
	public function post( int $post_id ): ?string {
		$key = 'p:' . $post_id;
		if ( ! isset( $this->memo[ $key ] ) ) {
			$meta               = Rights::course_meta( $post_id );
			$this->memo[ $key ] = '';
			if ( isset( $meta['status'] ) ) {
				$system             = (string) $this->db->scalar( $this->db->prepare( 'SELECT source_system FROM `' . $this->db->table( 'ccap_source_map' ) . '` WHERE object_id = %d AND object_type IN (\'lesson\', \'module\', \'course\') LIMIT 1', $post_id ) );
				$this->memo[ $key ] = (string) Rights::withheld( $meta['status'], $meta, ! $this->rights->own_system( $system ), $this->today() );
			}
		}
		return '' === $this->memo[ $key ] ? null : $this->memo[ $key ];
	}

	/**
	 * Whether the viewer belongs to the editorial side of a course (may
	 * edit it): exempt from the lock, sees a notice instead (R0-10 §2.2).
	 *
	 * @param int $user_id   Viewer.
	 * @param int $course_id Course.
	 * @return bool
	 */
	public static function exempt( int $user_id, int $course_id ): bool {
		return $user_id > 0 && $course_id > 0 && user_can( $user_id, 'edit_post', $course_id );
	}

	/**
	 * Notice for authors and admins in the preview: why the item is not
	 * shown to learners.
	 *
	 * @param string $reason `unknown`, `restricted` or `expired`.
	 * @return string Plain text.
	 */
	public static function notice( string $reason ): string {
		switch ( $reason ) {
			case 'restricted':
				return __( 'Not shown to learners: imported content marked as "must not be shown". Check the rights on the "Rights & release" page.', 'course-captain' );
			case 'expired':
				return __( 'Not shown to learners: the licence of this imported content has expired. Check the rights on the "Rights & release" page.', 'course-captain' );
			default:
				return __( 'Not shown to learners: the rights of this imported content are unclear. Check the rights on the "Rights & release" page.', 'course-captain' );
		}
	}

	/**
	 * Neutral placeholder for learners in place of withheld media (R0-10 §2.2:
	 * no error, no file path).
	 *
	 * @return string Plain text.
	 */
	public static function placeholder(): string {
		return __( 'Media not available', 'course-captain' );
	}

	/**
	 * Rights of content changed: bump the course generation of the
	 * affected courses after commit (R0-21 §3.7 "Rechtestatus/Auslieferungssperre").
	 *
	 * @param array<int> $courses Courses.
	 * @return void
	 */
	public static function changed( array $courses ): void {
		foreach ( array_values( array_unique( array_map( 'intval', $courses ) ) ) as $course ) {
			CacheStore::bump_course( $course );
		}
	}

	/**
	 * Courses that contain questions (module pools and inline lesson questions).
	 *
	 * @param array<int> $ids Questions.
	 * @return list<int>
	 */
	public static function courses_of_questions( array $ids ): array {
		$relations = new Relations( Database::current() );
		$courses   = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$courses = array_merge( $courses, $relations->courses_of_question( $id ) );
		}
		return array_values( array_unique( $courses ) );
	}

	/**
	 * Courses that contain a post (course itself, module, lesson).
	 *
	 * @param int $post_id Post.
	 * @return list<int>
	 */
	public static function courses_of_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return array();
		}
		if ( Relations::COURSE_TYPE === $post->post_type ) {
			return array( $post_id );
		}
		$relations = new Relations( Database::current() );
		$found     = $relations->post( $post_id );
		return null === $found ? array() : array_values( array_unique( array_map( 'intval', $relations->courses_of_item( $found ) ) ) );
	}

	/**
	 * Rights columns for a query over `ccap_question` (alias `q`) joined
	 * with its current revision (alias `r`): callers that read questions
	 * anyway add these columns and hand the rows to {@see self::absorb()},
	 * so the lock needs no query of its own (R0-14 budgets OP-03/OP-05).
	 *
	 * @return string SQL fragment starting with a comma.
	 */
	public function columns(): string {
		$ovr = $this->db->table( 'ccap_override' );
		$map = $this->db->table( 'ccap_source_map' );
		return ", r.id AS ccap_rights_revision, r.rights_status AS ccap_rights_status, r.license_reference AS ccap_rights_license, r.image_required AS ccap_rights_image, (SELECT o.value FROM `{$ovr}` o WHERE o.object_id = q.id AND o.object_type IN ('question', 'flashcard') AND o.field = '" . Rights::FIELD . "' ORDER BY o.id DESC LIMIT 1) AS ccap_rights_override, (SELECT sm.source_system FROM `{$map}` sm WHERE sm.object_id = q.id AND sm.object_type IN ('question', 'flashcard') LIMIT 1) AS ccap_rights_system";
	}

	/**
	 * Take the verdicts of questions from rows that carry {@see self::columns()}.
	 *
	 * @param array  $rows Rows.
	 * @param string $key  Column with the question ID.
	 * @phpstan-param list<array<string, mixed>> $rows
	 * @return void
	 */
	public function absorb( array $rows, string $key = 'id' ): void {
		$today = $this->today();
		$image = array();
		foreach ( $rows as $row ) {
			$id = (int) $row[ $key ];
			if ( isset( $this->memo[ 'q:' . $id ] ) ) {
				continue;
			}
			$status = (string) ( $row['ccap_rights_override'] ?? $row['ccap_rights_status'] );
			$system = (string) ( $row['ccap_rights_system'] ?? '' );
			// Local questions are own content: no lookup of the source system.
			$foreign                  = '' !== $system && 'local' !== $system && ! $this->rights->own_system( $system );
			$this->memo[ 'q:' . $id ] = (string) Rights::withheld( $status, self::license( $row['ccap_rights_license'] ?? null ), $foreign, $today );
			if ( '' === $this->memo[ 'q:' . $id ] && '1' === (string) $row['ccap_rights_image'] ) {
				$image[ (int) $row['ccap_rights_revision'] ] = $id;
			}
		}
		if ( array() === $image ) {
			return;
		}
		// Dependency (R0-10 §2.2): a question that needs its image is withheld when none of its media may be shown.
		$links = $this->db->rows( 'SELECT object_id, media_id FROM `' . $this->db->table( 'ccap_content_media' ) . "` WHERE object_type = 'question_revision' AND object_id IN (" . implode( ',', array_keys( $image ) ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		$by    = array();
		foreach ( $links as $link ) {
			$by[ (int) $link['object_id'] ][] = (int) $link['media_id'];
		}
		foreach ( $by as $revision => $media ) {
			$withheld = $this->media( $media );
			if ( count( $withheld ) === count( array_unique( $media ) ) ) {
				$this->memo[ 'q:' . $image[ $revision ] ] = (string) reset( $withheld );
			}
		}
	}

	/**
	 * Load the verdicts of questions (one query for the rights, one for the
	 * media of questions that need their image).
	 *
	 * @param array $ids Questions.
	 * @phpstan-param list<int> $ids
	 * @return void
	 */
	private function load_questions( array $ids ): void {
		$rows = $this->db->rows( 'SELECT q.id' . $this->columns() . ' FROM `' . $this->db->table( 'ccap_question' ) . '` q JOIN `' . $this->db->table( 'ccap_question_revision' ) . '` r ON r.id = q.current_revision_id WHERE q.id IN (' . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list and table names.
		$this->absorb( $rows );
		foreach ( $ids as $id ) {
			$this->memo[ 'q:' . $id ] ??= '';
		}
	}

	/**
	 * Licence fields from a stored JSON value.
	 *
	 * @param mixed $value `license_reference`.
	 * @return array<string, mixed>
	 */
	private static function license( $value ): array {
		$decoded = is_string( $value ) && '' !== $value ? json_decode( $value, true ) : null;
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Today (database clock) as `Y-m-d`.
	 *
	 * @return string
	 */
	private function today(): string {
		return gmdate( 'Y-m-d', $this->db->time() );
	}
}
