<?php
/**
 * MINVER-1: MariaDB 12.3 snapshot isolation is switched off per session.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Storage\Database;

/**
 * Version detection for `innodb_snapshot_isolation` (MariaDB ≥ 11.6.2 only).
 *
 * @group MINVER-1
 */
final class DatabaseSessionTest extends TestCase {

	/**
	 * Server versions and whether the session must switch snapshot isolation off.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function versions(): array {
		return array(
			'MariaDB 12.3'         => array( '12.3.4-MariaDB-ubu2404', true ),
			'MariaDB 11.6.2'       => array( '11.6.2-MariaDB', true ),
			'MariaDB 11.6.1'       => array( '11.6.1-MariaDB', false ),
			'MariaDB 11.4'         => array( '11.4.5-MariaDB', false ),
			'MariaDB 10.11'        => array( '10.11.14-MariaDB-0ubuntu0.24.04.1', false ),
			'MariaDB 5.5.5 prefix' => array( '5.5.5-12.3.4-MariaDB-1:12.3.4+maria~ubu2404', true ),
			'MySQL 9.7'            => array( '9.7.4', false ),
			'MySQL 8.4'            => array( '8.4.3', false ),
			'empty answer'         => array( '', false ),
		);
	}

	/**
	 * Detection.
	 *
	 * @param string $version  Server version.
	 * @param bool   $expected Expected result.
	 * @dataProvider versions
	 */
	public function test_snapshot_isolation_detection( string $version, bool $expected ): void {
		self::assertSame( $expected, Database::needs_snapshot_isolation_off( $version ) );
	}
}
