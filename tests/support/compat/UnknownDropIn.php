<?php
/**
 * Unknown `db.php` drop-in class (KOMP-034 negative case).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Support\Compat;

/**
 * A `$wpdb` replacement of unknown origin.
 */
final class UnknownDropIn extends \wpdb {
}
