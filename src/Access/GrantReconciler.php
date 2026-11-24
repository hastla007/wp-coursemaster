<?php
/**
 * Applies the difference between desired and stored grants (R0-19 §5.8, R0-09 R-04).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Ops\SourceReview;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\NamedLock;

defined( 'ABSPATH' ) || exit;

/**
 * Under the source lock `grant-source:<type>:<reference>`: stale source
 * revisions are ignored (`source_seq`), `revoked → active` never happens
 * automatically (E-29), manual decisions are only overridden by
 * revocation (GrantService), revocation only touches this source. A
 * source without snapshot changes no grant; with the operator policy
 * `keep_and_flag` its open grants are marked for review (ACCESS-09).
 */
final class GrantReconciler {

	/**
	 * Constructor.
	 *
	 * @param Database     $db     Database access.
	 * @param GrantService $grants Grants.
	 */
	public function __construct( private Database $db, private GrantService $grants ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), GrantService::current() );
	}

	/**
	 * Reconcile one source object.
	 *
	 * @param GrantSourceAdapter $adapter         Adapter.
	 * @param SourceStatusMapper $mapper          Mapper.
	 * @param string             $reference       Source reference.
	 * @param string|null        $source_event_id External event ID (dedupe in events).
	 * @return array{created: int, changed: int, ignored: int, stale: bool, no_change: bool}
	 */
	public function reconcile( GrantSourceAdapter $adapter, SourceStatusMapper $mapper, string $reference, ?string $source_event_id = null ): array {
		$result   = array(
			'created'   => 0,
			'changed'   => 0,
			'ignored'   => 0,
			'stale'     => false,
			'no_change' => false,
		);
		$type     = $adapter->source_type();
		$actor    = Actor::connector( $type );
		$snapshot = $adapter->snapshot( $reference );
		if ( null === $snapshot ) {
			// ACCESS-09: never a revocation; the operator policy may mark the grants for review.
			SourceReview::source_missing( $this->db, $actor, $type, $reference );
			$result['no_change'] = true;
			return $result;
		}
		SourceReview::source_back( $this->db, $actor, $type, $reference );
		$lock = NamedLock::for_key( $this->db, 'grant-source:' . $type . ':' . $reference );
		$lock->acquire( 10 );
		try {
			$revision = (int) ( $snapshot['revision'] ?? 0 );
			$table    = $this->db->table( 'ccap_grant' );
			$seen     = $this->db->scalar( $this->db->prepare( "SELECT MAX(source_seq) FROM `{$table}` WHERE source_type = %s AND source_id = %s", $type, $reference ) );
			if ( null !== $seen && $revision <= (int) $seen ) {
				$result['stale'] = true;
				return $result;
			}
			$desired = $mapper->desired_grants( $snapshot );
			if ( $desired['no_change'] ) {
				$result['no_change'] = true;
				return $result;
			}
			foreach ( $desired['grants'] as $grant ) {
				$current = $this->db->row(
					$this->db->prepare(
						"SELECT id, status FROM `{$table}` WHERE source_type = %s AND source_id = %s AND source_line = %s AND course_id = %d AND module_id = %d ORDER BY source_revision DESC LIMIT 1",
						$type,
						$reference,
						$grant['source_line'],
						$grant['course_id'],
						$grant['module_id']
					)
				);
				$target  = array(
					'active'    => GrantTransitions::ACTIVE,
					'pending'   => GrantTransitions::PENDING_CONFIRMATION,
					'suspended' => GrantTransitions::SUSPENDED,
					'revoked'   => GrantTransitions::REVOKED,
				)[ (string) $grant['target_status'] ] ?? null;
				if ( null === $target ) {
					continue;
				}
				if ( null === $current ) {
					if ( GrantTransitions::REVOKED === $target || GrantTransitions::SUSPENDED === $target || (int) $grant['user_id'] <= 0 ) {
						continue;
					}
					$this->grants->create(
						array(
							'user_id'       => (int) $grant['user_id'],
							'course_id'     => (int) $grant['course_id'],
							'module_id'     => (int) $grant['module_id'],
							'scopes'        => (string) $grant['scopes'],
							'source_type'   => $type,
							'source_id'     => $reference,
							'source_line'   => (string) $grant['source_line'],
							'source_seq'    => $revision,
							'source_plugin' => $type,
							'status'        => $target,
							'valid_until'   => $grant['valid_until'],
							'renewal_mode'  => 'parallel',
						),
						$actor,
						(string) $grant['reason_code'],
						$source_event_id
					);
					++$result['created'];
					continue;
				}
				if ( $current['status'] === $target ) {
					continue;
				}
				$outcome = $this->grants->transition( $actor, (int) $current['id'], $target, (string) $grant['reason_code'], false );
				if ( 'ignored' === $outcome ) {
					++$result['ignored'];
				} else {
					++$result['changed'];
				}
			}
			$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET source_seq = GREATEST(source_seq, %d) WHERE source_type = %s AND source_id = %s", $revision, $type, $reference ) );
			return $result;
		} finally {
			$lock->release();
		}
	}
}
