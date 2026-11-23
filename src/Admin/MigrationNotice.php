<?php
/**
 * Admin notice for a failed or blocked database update (R0-05 GM-167, R0-37 §4.5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Infrastructure\Migration\MigrationRunner;
use CourseCaptain\Storage\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Exactly one notice in the bundled admin notices (GM-140), however many
 * steps failed. The text names only the step IDs; the database message
 * (table names, user, host) stays in the step log, Site Health and the CLI.
 * Only users with `manage_options` see it.
 */
final class MigrationNotice {

	public const CODE = 'ccap_migration_failed';

	/**
	 * Add the notice for the current request when the update failed.
	 *
	 * @param MigrationRunner $runner Migration runner.
	 * @return bool Whether a notice was added.
	 */
	public static function maybe_add( MigrationRunner $runner ): bool {
		if ( ! $runner instanceof Migrator || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$message = self::message( $runner->report() );
		if ( null === $message ) {
			return false;
		}
		Notices::add( self::CODE, 'error', $message );
		return true;
	}

	/**
	 * Masked text for a migration report, or null when nothing failed.
	 *
	 * @param array<string, mixed> $report {@see Migrator::report()}.
	 * @return string|null
	 */
	public static function message( array $report ): ?string {
		$steps = array_map( static fn( $id ): string => (string) preg_replace( '/[^a-z0-9:_-]/', '', strtolower( (string) $id ) ), array_merge( (array) ( $report['poison'] ?? array() ), (array) ( $report['failed'] ?? array() ) ) );
		$steps = array_values( array_unique( array_filter( $steps ) ) );
		switch ( $report['state'] ?? '' ) {
			case 'newer':
				return __( 'The database schema is newer than the plugin. Install the matching plugin version or restore database and files together.', 'course-captain' );
			case 'blocked':
			case 'failed':
				return sprintf(
					/* translators: %s: step IDs, e.g. "core:0002_seat" */
					__( 'The database update failed (step %s). Course Captain keeps the data unchanged and retries later. Details are in Tools > Site Health or with "wp course-captain migrate --status".', 'course-captain' ),
					array() === $steps ? '-' : implode( ', ', $steps )
				);
		}
		return null;
	}
}
