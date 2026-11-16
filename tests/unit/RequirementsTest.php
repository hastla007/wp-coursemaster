<?php
/**
 * Unit tests of the version and environment check.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Kernel\EnvironmentSnapshot;
use CourseCaptain\Kernel\RequirementFailure;
use CourseCaptain\Kernel\Requirements;

/**
 * Platform matrix R0-20 §1.2 (E-08, E-58, E-195).
 *
 * @group R1-01
 */
final class RequirementsTest extends TestCase {

	/**
	 * Codes of a failure list.
	 *
	 * @param RequirementFailure[] $failures Failures.
	 * @phpstan-param list<RequirementFailure> $failures
	 * @return list<string>
	 */
	private static function codes( array $failures ): array {
		return array_map( static fn( RequirementFailure $f ): string => $f->code, $failures );
	}

	/**
	 * RC-047: too old PHP and WordPress are reported, not fatal.
	 *
	 * @group RC-047
	 */
	public function test_RC_047_too_old_php_and_wordpress_are_reported(): void {
		$failures = Requirements::evaluate( new EnvironmentSnapshot( '8.2.29', '6.9.1' ) );

		self::assertSame( array( RequirementFailure::PHP, RequirementFailure::WORDPRESS ), self::codes( $failures ) );
		self::assertStringContainsString( '8.3.0', $failures[0]->message() );
		self::assertStringContainsString( '8.2.29', $failures[0]->message() );
		self::assertStringContainsString( '6.9.1', $failures[1]->message() );
	}

	/**
	 * KOMP-001 precondition / RC-046: the lower bound of the matrix (PHP 8.3, WP 7.0) passes.
	 *
	 * @group RC-046
	 */
	public function test_RC_046_lower_bound_passes(): void {
		self::assertSame( array(), Requirements::evaluate( new EnvironmentSnapshot( '8.3.0', '7.0' ) ) );
		self::assertSame( array(), Requirements::evaluate( new EnvironmentSnapshot( '8.5.1', '7.1.4' ) ) );
		self::assertSame( array(), Requirements::evaluate( new EnvironmentSnapshot( '8.3.0', '7.0-RC2' ) ) );
	}

	/**
	 * Database matrix: vendor detection and support levels.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function database_versions(): array {
		return array(
			'MariaDB 10.5 below hard minimum' => array( '10.5.27-MariaDB', Requirements::LEVEL_UNSUPPORTED ),
			'MariaDB 10.6 best effort'        => array( '10.6.21-MariaDB-0ubuntu0.22.04.2', Requirements::LEVEL_BEST_EFFORT ),
			'MariaDB 10.11 supported'         => array( '10.11.14-MariaDB-0ubuntu0.24.04.1', Requirements::LEVEL_SUPPORTED ),
			'MariaDB 12.3 supported'          => array( '12.3.3-MariaDB', Requirements::LEVEL_SUPPORTED ),
			'MariaDB with 5.5.5 prefix'       => array( '5.5.5-10.11.6-MariaDB-1:10.11.6+maria~ubu2204', Requirements::LEVEL_SUPPORTED ),
			'MySQL 5.7 below hard minimum'    => array( '5.7.44', Requirements::LEVEL_UNSUPPORTED ),
			'MySQL 8.0 best effort'           => array( '8.0.40', Requirements::LEVEL_BEST_EFFORT ),
			'MySQL 8.4 supported'             => array( '8.4.3', Requirements::LEVEL_SUPPORTED ),
			'Percona 8.4 supported'           => array( '8.4.2-2', Requirements::LEVEL_SUPPORTED ),
			'unknown server'                  => array( 'SQLite 3.45', Requirements::LEVEL_UNSUPPORTED ),
			'empty answer'                    => array( '', Requirements::LEVEL_UNSUPPORTED ),
		);
	}

	/**
	 * Hard minimum and "supported from" of the database (R0-20 §1.2).
	 *
	 * @param string $server   Server version string.
	 * @param string $expected Expected support level.
	 * @dataProvider database_versions
	 */
	public function test_database_support_levels( string $server, string $expected ): void {
		self::assertSame( $expected, Requirements::database_support_level( $server ) );
	}

	/**
	 * Activation check: unsupported database and missing InnoDB/JSON/utf8mb4 refuse activation.
	 */
	public function test_database_capabilities_refuse_activation(): void {
		$failures = Requirements::evaluate( new EnvironmentSnapshot( '8.3.0', '7.1', '5.7.44', false, false, false ) );

		self::assertSame(
			array( RequirementFailure::DATABASE, RequirementFailure::INNODB, RequirementFailure::JSON, RequirementFailure::UTF8MB4 ),
			self::codes( $failures )
		);
		foreach ( $failures as $failure ) {
			self::assertNotSame( $failure->code, $failure->message() );
		}
	}

	/**
	 * Best-effort databases are accepted (E-58): no failure, only a status level.
	 */
	public function test_best_effort_database_is_accepted(): void {
		self::assertSame( array(), Requirements::evaluate( new EnvironmentSnapshot( '8.3.0', '7.1', '8.0.40' ) ) );
	}

	/**
	 * Without a database inspection (runtime check) only PHP and WordPress are evaluated.
	 */
	public function test_runtime_check_skips_database(): void {
		self::assertSame( array(), Requirements::evaluate( new EnvironmentSnapshot( '8.3.0', '7.1', null, false, false, false ) ) );
	}

	/**
	 * Error codes carry the `ccap_` prefix (E-285).
	 */
	public function test_failure_codes_are_prefixed(): void {
		$reflection = new \ReflectionClass( RequirementFailure::class );
		foreach ( $reflection->getConstants() as $code ) {
			self::assertIsString( $code );
			self::assertStringStartsWith( 'ccap_', $code );
		}
	}
}
