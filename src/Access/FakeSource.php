<?php
/**
 * FakeSource: in-memory grant source for tests (R0-19 §5.8, INV-B-169).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

defined( 'ABSPATH' ) || exit;

/**
 * Adapter and mapper in one. Statuses: `paid` → active, `pending` →
 * pending, `disputed` → suspended, `refunded`/`cancelled` → revoked;
 * a fully refunded line → revoked; anything else → no change.
 */
final class FakeSource implements GrantSourceAdapter, SourceStatusMapper {

	/**
	 * Snapshots by reference.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $objects = array();

	/**
	 * Store or replace a source object.
	 *
	 * @param array<string, mixed> $snapshot Snapshot without `source_type`.
	 * @return void
	 */
	public function put( array $snapshot ): void {
		$this->objects[ (string) $snapshot['reference'] ] = array( 'source_type' => $this->source_type() ) + $snapshot;
	}

	/**
	 * Source type.
	 *
	 * @return string
	 */
	public function source_type(): string {
		return 'fake';
	}

	/**
	 * Contract version.
	 *
	 * @return string
	 */
	public function contract_version(): string {
		return '1.0.0';
	}

	/**
	 * Snapshot.
	 *
	 * @param string $reference Reference.
	 * @return array<string, mixed>|null
	 */
	public function snapshot( string $reference ): ?array {
		return $this->objects[ $reference ] ?? null;
	}

	/**
	 * Desired grants.
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return array{grants: list<array<string, mixed>>, no_change: bool}
	 */
	public function desired_grants( array $snapshot ): array {
		$map    = array(
			'paid'      => array( 'active', 'order_paid' ),
			'pending'   => array( 'pending', 'consent_missing' ),
			'disputed'  => array( 'suspended', 'payment_disputed' ),
			'refunded'  => array( 'revoked', 'order_refunded' ),
			'cancelled' => array( 'revoked', 'order_cancelled' ),
		);
		$status = (string) ( $snapshot['status'] ?? '' );
		if ( ! isset( $map[ $status ] ) ) {
			return array(
				'grants'    => array(),
				'no_change' => true,
			);
		}
		$grants = array();
		foreach ( (array) ( $snapshot['lines'] ?? array() ) as $line ) {
			[ $target, $reason ] = $map[ $status ];
			if ( 'full' === ( $line['refund'] ?? 'none' ) ) {
				$target = 'revoked';
				$reason = 'item_refunded';
			}
			foreach ( (array) ( $line['targets'] ?? array() ) as $course ) {
				$grants[] = array(
					'source_line'   => (string) $line['line'],
					'course_id'     => (int) $course['course_id'],
					'module_id'     => (int) ( $course['module_id'] ?? 0 ),
					'scopes'        => (string) ( $course['scopes'] ?? 'all' ),
					'user_id'       => isset( $snapshot['user_id'] ) ? (int) $snapshot['user_id'] : 0,
					'target_status' => $target,
					'reason_code'   => $reason,
					'valid_until'   => $line['valid_until'] ?? null,
				);
			}
		}
		return array(
			'grants'    => $grants,
			'no_change' => false,
		);
	}
}
