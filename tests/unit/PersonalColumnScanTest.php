<?php
/**
 * PRIV-001 (R0-17 §7, T-42): schema scan – every `ccap_*` column `user_id`,
 * `*_user_id`, `*_email` or `actor_id` is in the person registry with an
 * eraser rule and an export group, and its table is classified `personal`
 * or carries only author references that the eraser clears.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Storage\Schema;

/**
 * Static scan of the schema definition.
 *
 * @group R1-17
 * @group PRIV-001
 * @group T-42
 */
final class PersonalColumnScanTest extends TestCase {

	private const PATTERN = '/^(user_id|[a-z_]+_user_id|[a-z_]+_email|actor_id)$/';

	private const RULES = array( 'delete', 'null', 'pseudonymise', 'detach', 'audit_identity', 'service' );

	/**
	 * Person columns of the schema as `table.column`.
	 *
	 * @return list<string>
	 */
	private static function scanned(): array {
		$out = array();
		foreach ( Schema::tables() as $table => $def ) {
			foreach ( array_keys( $def['columns'] ) as $column ) {
				if ( 1 === preg_match( self::PATTERN, (string) $column ) ) {
					$out[] = $table . '.' . $column;
				}
			}
		}
		sort( $out );
		return $out;
	}

	/**
	 * Every scanned column is registered and every registered column exists.
	 */
	public function test_PRIV_001_every_person_column_is_registered(): void {
		$registered = array_keys( PrivacyService::PERSON_COLUMNS );
		sort( $registered );
		self::assertNotEmpty( self::scanned() );
		self::assertSame( self::scanned(), $registered, 'a new person column needs an eraser and export rule in PrivacyService::PERSON_COLUMNS' );
	}

	/**
	 * Every entry has a known eraser rule and the table a classification;
	 * tables classified `content` only carry references the eraser clears.
	 */
	public function test_PRIV_001_rules_and_classification(): void {
		$source = (string) file_get_contents( self::root() . '/src/Privacy/PrivacyService.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Repository file.
		foreach ( PrivacyService::PERSON_COLUMNS as $key => $rule ) {
			list( $table ) = explode( '.', $key, 2 );
			self::assertContains( $rule[0], self::RULES, $key );
			self::assertArrayHasKey( $table, PrivacyService::CLASSIFICATION, $key );
			if ( 'personal' !== PrivacyService::CLASSIFICATION[ $table ] ) {
				self::assertSame( 'null', $rule[0], $key . ': a content table only holds a reference that is cleared' );
				self::assertSame( '', $rule[1], $key . ': not exported' );
			}
			if ( '' !== $rule[1] ) {
				self::assertMatchesRegularExpression( '/^cc-[a-z-]+$/', $rule[1], $key );
			}
			if ( 'delete' === $rule[0] ) {
				self::assertStringContainsString( "'" . $table . "'", $source, $key . ': deleted by erase_user()' );
			}
		}
	}
}
