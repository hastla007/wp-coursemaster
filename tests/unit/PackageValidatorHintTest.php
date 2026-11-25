<?php
/**
 * Unit tests of the readable hints for package format and schema version (IMP-004, IMP-005).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Import\PackageValidator;

/**
 * `PackageValidator::hint()` only names what exists (course export, real versions).
 *
 * @group IMP-004
 * @group IMP-005
 */
final class PackageValidatorHintTest extends TestCase {

	/**
	 * Foreign format (also an LB package without `format`): points to the course export.
	 */
	public function test_IMP_004_foreign_format_points_to_course_export(): void {
		$hint = (string) PackageValidator::hint( 'V5_format', '' );
		self::assertStringContainsString( 'not a Course Captain package', $hint );
		self::assertStringContainsString( '"Import content > Course export"', $hint );
		self::assertDoesNotMatchRegularExpression( '/migration|update the plugin|Migrationswerkzeug|Plugin aktualisieren/i', $hint );
	}

	/**
	 * Newer minor, newer and older major: the package version and the real supported range.
	 */
	public function test_IMP_005_version_outside_range_names_real_versions(): void {
		$highest = '1.' . PackageValidator::SCHEMA_MINOR . '.x';
		$cases   = array(
			'V5_minor' => '1.9.0',
			'V5_major' => '2.0.0',
		);
		foreach ( $cases as $rule => $version ) {
			self::assertSame(
				'The package has schema version ' . $version . ', but this site imports only schema versions 1.0.0 to ' . $highest . '. Export the package again with a matching version and upload that file.',
				PackageValidator::hint( $rule, $version ),
				$rule
			);
		}
		self::assertStringContainsString( 'schema version 0.4.2,', (string) PackageValidator::hint( 'V5_major', '0.4.2' ), 'too old' );
		foreach ( array( '', 'abc', '1.0', '1.0.0<script>' ) as $version ) {
			self::assertSame(
				'The package has no valid schema version. This site imports schema versions 1.0.0 to ' . $highest . '. Export the package again with a matching version and upload that file.',
				PackageValidator::hint( 'V5_major', $version ),
				$version
			);
		}
		self::assertDoesNotMatchRegularExpression( '/migration|update the plugin/i', (string) PackageValidator::hint( 'V5_minor', '1.9.0' ) );
	}

	/**
	 * Other rules keep their code (no hint).
	 */
	public function test_other_rules_have_no_hint(): void {
		self::assertNull( PackageValidator::hint( 'V1_archive', '1.0.0' ) );
		self::assertNull( PackageValidator::hint( 'V5_checksum', '1.0.0' ) );
	}
}
