<?php
/**
 * Access rules of a course: free access, drip and partial access
 * (R0-09, R0-08 §1.4, R1-14; UI-F8 "Zugangsregeln").
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Writes the rows of `ccap_access_rule` that {@see ContentGate} evaluates:
 *
 * - `free`: the course (module 0) or one module is open to every
 *   logged-in visitor, optionally in a date window (`valid_from`,
 *   `valid_until`, UTC);
 * - `drip`: a module unlocks *n* days after the access start; one rule per
 *   module (the gate takes the largest). 0 days is a rule "open at the
 *   start" (it overrides the site-wide drip plan for that module);
 * - `free_limited` (partial access): one limit per kind via
 *   {@see TrialLimits::set()} (no second implementation).
 *
 * Changing rules needs the course edit right (M-05) and the primitive
 * capability `ccap_manage_access_rules`; every change is audited
 * (`access_rule_changed`) and flushes the policy memo. Rules act at once
 * on every read (the gate reads the table, not the published snapshot).
 */
final class AccessRules {

	/** Kinds this service manages. */
	public const KINDS = array( 'free', 'drip', 'free_limited' );

	/** Largest drip delay in days. */
	public const MAX_DAYS = 3650;

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
	 * Whether a user may change the access rules of a course.
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $course_id Course.
	 * @return bool
	 */
	public function can_manage( Actor $actor, int $course_id ): bool {
		return $actor->user_id > 0 && user_can( $actor->user_id, 'ccap_manage_access_rules' ) && $this->policy->decide( $actor, PolicyService::EDIT_COURSE, array( $course_id ) )->allowed;
	}

	/**
	 * Rules of a course in display order (course first, then modules).
	 *
	 * @param int $course_id Course.
	 * @return list<array{id: int, module_id: int, access_type: string, limit_type: string|null, limit_value: int|null, valid_from: string|null, valid_until: string|null}>
	 */
	public function rules( int $course_id ): array {
		$rows = $this->db->rows( $this->db->prepare( 'SELECT id, module_id, access_type, limit_type, limit_value, valid_from, valid_until FROM `' . $this->db->table( 'ccap_access_rule' ) . '` WHERE course_id = %d ORDER BY COALESCE(module_id, 0), access_type, limit_type, id', $course_id ) );
		return array_map(
			static fn( array $row ): array => array(
				'id'          => (int) $row['id'],
				'module_id'   => (int) ( $row['module_id'] ?? 0 ),
				'access_type' => (string) $row['access_type'],
				'limit_type'  => null === $row['limit_type'] ? null : (string) $row['limit_type'],
				'limit_value' => null === $row['limit_value'] ? null : (int) $row['limit_value'],
				'valid_from'  => null === $row['valid_from'] ? null : (string) $row['valid_from'],
				'valid_until' => null === $row['valid_until'] ? null : (string) $row['valid_until'],
			),
			$rows
		);
	}

	/**
	 * Set or remove the drip delay of a module.
	 *
	 * @param Actor    $actor     Actor.
	 * @param int      $course_id Course.
	 * @param int      $module_id Module of the course.
	 * @param int|null $days      Days after the access start; null removes the rule.
	 * @return void
	 * @throws CourseException `ccap_forbidden`, `ccap_invalid_param`.
	 */
	public function set_drip( Actor $actor, int $course_id, int $module_id, ?int $days ): void {
		$this->authorize( $actor, $course_id );
		$this->assert_module( $course_id, $module_id, false );
		if ( null !== $days && ( $days < 0 || $days > self::MAX_DAYS ) ) {
			throw new CourseException( 'ccap_invalid_param', 'days', 422 );
		}
		$table = $this->db->table( 'ccap_access_rule' );
		$this->db->transaction(
			function ( Database $db ) use ( $table, $course_id, $module_id, $days ): void {
				$db->execute( $db->prepare( "DELETE FROM `{$table}` WHERE course_id = %d AND module_id = %d AND access_type = 'drip'", $course_id, $module_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				if ( null !== $days ) {
					$db->insert(
						'ccap_access_rule',
						array(
							'course_id'   => $course_id,
							'module_id'   => $module_id,
							'scope'       => 'view',
							'access_type' => 'drip',
							'limit_value' => $days,
						),
						array( 'created_at', 'updated_at' )
					);
				}
			}
		);
		$this->changed(
			$actor,
			$course_id,
			array(
				'kind'      => 'drip',
				'module_id' => $module_id,
				'days'      => $days,
			)
		);
	}

	/**
	 * Add a free-access rule for the course or a module.
	 *
	 * @param Actor       $actor       Actor.
	 * @param int         $course_id   Course.
	 * @param int         $module_id   Module (0 = whole course).
	 * @param string|null $valid_from  Start (UTC `Y-m-d H:i:s`) or null.
	 * @param string|null $valid_until End (UTC `Y-m-d H:i:s`) or null.
	 * @return int Rule ID.
	 * @throws CourseException `ccap_forbidden`, `ccap_invalid_param`.
	 */
	public function add_free( Actor $actor, int $course_id, int $module_id, ?string $valid_from, ?string $valid_until ): int {
		$this->authorize( $actor, $course_id );
		$this->assert_module( $course_id, $module_id, true );
		if ( null !== $valid_from && null !== $valid_until && $valid_until <= $valid_from ) {
			throw new CourseException( 'ccap_invalid_param', 'valid_until', 422 );
		}
		$id = $this->db->insert(
			'ccap_access_rule',
			array(
				'course_id'   => $course_id,
				'module_id'   => $module_id,
				'scope'       => 'view',
				'access_type' => 'free',
				'valid_from'  => $valid_from,
				'valid_until' => $valid_until,
			),
			array( 'created_at', 'updated_at' )
		);
		$this->changed(
			$actor,
			$course_id,
			array(
				'kind'        => 'free',
				'module_id'   => $module_id,
				'rule_id'     => $id,
				'valid_from'  => $valid_from,
				'valid_until' => $valid_until,
			)
		);
		return $id;
	}

	/**
	 * Partial-access limits of the course or a module (one per kind, null removes).
	 *
	 * @param Actor                   $actor     Actor.
	 * @param int                     $course_id Course.
	 * @param int                     $module_id Module (0 = whole course).
	 * @param array<string, int|null> $limits    Kind → limit.
	 * @return array<string, int> Limits after the change.
	 * @throws CourseException `ccap_forbidden`, `ccap_invalid_param`, `ccap_validation_failed`.
	 */
	public function set_limits( Actor $actor, int $course_id, int $module_id, array $limits ): array {
		$this->authorize( $actor, $course_id );
		$this->assert_module( $course_id, $module_id, true );
		$after = TrialLimits::current()->set( $actor, $course_id, $module_id, $limits );
		$this->changed(
			$actor,
			$course_id,
			array(
				'kind'      => 'free_limited',
				'module_id' => $module_id,
				'limits'    => $after,
			)
		);
		return $after;
	}

	/**
	 * Remove one rule of the course.
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $course_id Course.
	 * @param int   $rule_id   Rule.
	 * @return void
	 * @throws CourseException `ccap_forbidden`, `ccap_not_found`.
	 */
	public function delete( Actor $actor, int $course_id, int $rule_id ): void {
		$this->authorize( $actor, $course_id );
		$table = $this->db->table( 'ccap_access_rule' );
		$row   = $this->db->row( $this->db->prepare( "SELECT id, module_id, access_type, limit_type, limit_value, valid_from, valid_until FROM `{$table}` WHERE id = %d AND course_id = %d", $rule_id, $course_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
		if ( null === $row ) {
			throw new CourseException( 'ccap_not_found', 'rule', 404 );
		}
		$this->db->execute( $this->db->prepare( "DELETE FROM `{$table}` WHERE id = %d", $rule_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
		$this->changed(
			$actor,
			$course_id,
			array(
				'kind'    => (string) $row['access_type'],
				'removed' => $row,
			)
		);
	}

	/**
	 * Rights or exception.
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $course_id Course.
	 * @return void
	 * @throws CourseException `ccap_forbidden`.
	 */
	private function authorize( Actor $actor, int $course_id ): void {
		if ( ! $this->can_manage( $actor, $course_id ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to change the access rules.', 403 );
		}
	}

	/**
	 * The module belongs to the course (0 = whole course where allowed).
	 *
	 * @param int  $course_id  Course.
	 * @param int  $module_id  Module.
	 * @param bool $allow_zero Whether 0 (whole course) is allowed.
	 * @return void
	 * @throws CourseException `ccap_invalid_param`.
	 */
	private function assert_module( int $course_id, int $module_id, bool $allow_zero ): void {
		if ( 0 === $module_id && $allow_zero ) {
			return;
		}
		$modules = array_map( static fn( array $link ): int => (int) $link['module_id'], CourseStructure::current()->modules( $course_id ) );
		if ( ! in_array( $module_id, $modules, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'module', 422 );
		}
	}

	/**
	 * Audit, policy memo, caches.
	 *
	 * @param Actor                $actor     Actor.
	 * @param int                  $course_id Course.
	 * @param array<string, mixed> $context   Change.
	 * @return void
	 */
	private function changed( Actor $actor, int $course_id, array $context ): void {
		AuditLog::write( $this->db, $actor, 'access_rule_changed', 'course', (string) $course_id, null, $context );
		PolicyService::flush();
		do_action( 'course_captain_policy_changed' );
	}
}
