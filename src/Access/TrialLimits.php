<?php
/**
 * Partial access (`free_limited`) with one limit per kind (E-332, R0-08 §1.4 Nr. 2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * A `free_limited` rule row (`ccap_access_rule`) names its kind in
 * `limit_type`: the first *n* questions per module (flashcards are
 * questions, E-332), lessons per module, fixed exam variants of the course
 * or protected files of the course. Each kind has its own limit, so
 * different numbers coexist (AR-02/03/06/07). Rows from before E-332
 * (`limit_type` `first_items` or NULL) are one limit for every kind; they
 * stay valid and are split into one row per kind by the backfill
 * {@see AccessRuleKindsBackfill}. Several rules of the same kind: the
 * largest limit wins (LB `MAX(limit_value)`).
 */
final class TrialLimits {

	public const QUESTIONS = 'first_questions';
	public const LESSONS   = 'first_lessons';
	public const VARIANTS  = 'first_variants';
	public const FILES     = 'first_files';

	/**
	 * Limit type of a rule from before E-332 (one limit for all kinds).
	 */
	public const LEGACY = 'first_items';

	/**
	 * Kinds with the scope stored in `ccap_access_rule.scope`.
	 *
	 * @var array<string, string>
	 */
	public const KINDS = array(
		self::QUESTIONS => 'view',
		self::LESSONS   => 'view',
		self::VARIANTS  => 'exam',
		self::FILES     => 'download',
	);

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
	 * Whether a stored `limit_type` applies to a kind (legacy rows apply to all).
	 *
	 * @param string|null $limit_type Stored limit type.
	 * @param string|null $kind       Kind or null (any).
	 * @return bool
	 */
	public static function applies( ?string $limit_type, ?string $kind ): bool {
		if ( null === $kind || null === $limit_type || '' === $limit_type || self::LEGACY === $limit_type ) {
			return true;
		}
		return $limit_type === $kind;
	}

	/**
	 * Set the partial-access limits of a course (module 0) or module; a
	 * kind with `null` removes its limit. Legacy rows of the same course
	 * and module are split first, so a change of one kind keeps the others.
	 *
	 * @param Actor                   $actor     Actor (course editor).
	 * @param int                     $course_id Course.
	 * @param int                     $module_id Module (0 = whole course).
	 * @param array<string, int|null> $limits    Kind → limit 0…10 000 or null.
	 * @return array<string, int> Limits per kind after the change.
	 * @throws CourseException `ccap_forbidden`, `ccap_validation_failed`.
	 */
	public function set( Actor $actor, int $course_id, int $module_id, array $limits ): array {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_COURSE, array( $course_id ) ), 'Not allowed to edit this course.' );
		foreach ( $limits as $kind => $value ) {
			if ( ! isset( self::KINDS[ $kind ] ) || ( null !== $value && ( ! is_int( $value ) || $value < 0 || $value > 10000 ) ) ) {
				throw new CourseException( 'ccap_validation_failed', esc_html( (string) $kind ), 422 );
			}
		}
		$table = $this->db->table( 'ccap_access_rule' );
		$this->db->transaction(
			function ( Database $db ) use ( $table, $course_id, $module_id, $limits ): void {
				$legacy = $db->rows( $db->prepare( "SELECT id FROM `{$table}` WHERE course_id = %d AND COALESCE(module_id, 0) = %d AND access_type = 'free_limited' AND (limit_type IS NULL OR limit_type NOT IN ('first_questions', 'first_lessons', 'first_variants', 'first_files')) FOR UPDATE", $course_id, $module_id ) );
				if ( array() !== $legacy ) {
					self::split( $db, array_map( static fn( array $r ): int => (int) $r['id'], $legacy ) );
				}
				foreach ( $limits as $kind => $value ) {
					$db->execute( $db->prepare( "DELETE FROM `{$table}` WHERE course_id = %d AND COALESCE(module_id, 0) = %d AND access_type = 'free_limited' AND limit_type = %s", $course_id, $module_id, (string) $kind ) );
					if ( null === $value ) {
						continue;
					}
					$db->insert(
						'ccap_access_rule',
						array(
							'course_id'   => $course_id,
							'module_id'   => $module_id,
							'scope'       => self::KINDS[ $kind ],
							'access_type' => 'free_limited',
							'limit_type'  => (string) $kind,
							'limit_value' => $value,
						),
						array( 'created_at', 'updated_at' )
					);
				}
			}
		);
		PolicyService::flush();
		do_action( 'course_captain_policy_changed' );
		return $this->limits( $course_id, $module_id );
	}

	/**
	 * Stored limits of a course (module 0) or module per kind; legacy
	 * rows count for every kind, the largest value wins.
	 *
	 * @param int $course_id Course.
	 * @param int $module_id Module (0 = whole course).
	 * @return array<string, int>
	 */
	public function limits( int $course_id, int $module_id = 0 ): array {
		$rows = $this->db->rows( $this->db->prepare( 'SELECT limit_type, limit_value FROM `' . $this->db->table( 'ccap_access_rule' ) . "` WHERE course_id = %d AND COALESCE(module_id, 0) = %d AND access_type = 'free_limited' ORDER BY id", $course_id, $module_id ) );
		$out  = array();
		foreach ( array_keys( self::KINDS ) as $kind ) {
			foreach ( $rows as $row ) {
				if ( self::applies( null === $row['limit_type'] ? null : (string) $row['limit_type'], $kind ) ) {
					$out[ $kind ] = max( $out[ $kind ] ?? 0, max( 0, (int) $row['limit_value'] ) );
				}
			}
		}
		return $out;
	}

	/**
	 * Split legacy rows (one limit for every kind) into one row per kind
	 * with the same course, module, value and validity window; the legacy
	 * row becomes the question row (keeps its ID for `rule_refs`).
	 *
	 * @param Database $db  Database (in a transaction).
	 * @param array    $ids Legacy rule IDs.
	 * @phpstan-param list<int> $ids
	 * @return int Rows changed.
	 */
	public static function split( Database $db, array $ids ): int {
		$table   = $db->table( 'ccap_access_rule' );
		$changed = 0;
		foreach ( $ids as $id ) {
			$row = $db->row( $db->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND access_type = 'free_limited' AND (limit_type IS NULL OR limit_type NOT IN ('first_questions', 'first_lessons', 'first_variants', 'first_files'))", $id ) );
			if ( null === $row ) {
				continue;
			}
			foreach ( self::KINDS as $kind => $scope ) {
				if ( self::QUESTIONS === $kind ) {
					$db->execute( $db->prepare( "UPDATE `{$table}` SET limit_type = %s, scope = %s WHERE id = %d", $kind, $scope, $id ) );
					continue;
				}
				$db->insert(
					'ccap_access_rule',
					array(
						'course_id'   => (int) $row['course_id'],
						'module_id'   => null === $row['module_id'] ? null : (int) $row['module_id'],
						'scope'       => $scope,
						'access_type' => 'free_limited',
						'limit_type'  => $kind,
						'limit_value' => null === $row['limit_value'] ? null : (int) $row['limit_value'],
						'valid_from'  => $row['valid_from'],
						'valid_until' => $row['valid_until'],
						'created_at'  => $row['created_at'] ?? $db->now(),
						'updated_at'  => $db->now(),
					)
				);
			}
			++$changed;
		}
		return $changed;
	}
}
