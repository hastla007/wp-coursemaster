<?php
/**
 * Lease lost during a migration.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Another runner took over (`ccap_migration_fenced`, R0-37 §3.1). The run
 * stops without writing a failure status.
 */
final class MigrationFencedException extends StorageException {
}
