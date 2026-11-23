<?php
/**
 * Maps a source snapshot to desired grants (pure, R0-19 §5.8).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

defined( 'ABSPATH' ) || exit;

/**
 * Desired grant (array): `source_line`, `course_id`, `module_id`,
 * `scopes`, `user_id`, `target_status` (active|pending|suspended|revoked),
 * `reason_code`, `valid_until`. An unknown source status means
 * `no_change = true`: change nothing (K-02).
 */
interface SourceStatusMapper {

	/**
	 * Desired grants of a snapshot.
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return array{grants: list<array<string, mixed>>, no_change: bool}
	 */
	public function desired_grants( array $snapshot ): array;
}
