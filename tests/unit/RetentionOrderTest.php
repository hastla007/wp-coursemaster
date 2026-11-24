<?php
/**
 * RR-4 (R0-31 §2.3, PSD-018): raw answers are never kept longer than the
 * exam attempts they belong to; certificates earlier than the attempts only
 * give a notice.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Privacy\RetentionPolicy;
use CourseCaptain\Settings\SettingsRules;

/**
 * Pure rule functions without WordPress.
 *
 * @group SET-CC-02
 * @group RR-4
 * @group PSD-018
 */
final class RetentionOrderTest extends TestCase {

	/**
	 * Sentence 1: raw answers ≤ exam attempts, equal is allowed, missing keys count with their default.
	 */
	public function test_PSD_018_raw_answers_not_longer_than_exam_attempts(): void {
		$defaults = RetentionPolicy::defaults();
		self::assertFalse( SettingsRules::retention_order_violation( $defaults ), 'defaults (2 ≤ 10) are valid' );
		self::assertFalse( SettingsRules::retention_order_violation( array() ), 'missing keys = defaults' );
		self::assertFalse( SettingsRules::retention_order_violation( array( 'raw_answers_years_after_course_end' => 10 ) + $defaults ), 'equal is allowed' );
		self::assertTrue( SettingsRules::retention_order_violation( array( 'raw_answers_years_after_course_end' => 11 ) + $defaults ) );
		self::assertTrue( SettingsRules::retention_order_violation( array( 'exam_attempts_years' => 1 ) + $defaults ), 'shortening the attempts below the answers' );
		$zero = array(
			'exam_attempts_years'                => 1,
			'raw_answers_years_after_course_end' => 0,
		);
		self::assertFalse( SettingsRules::retention_order_violation( $zero + $defaults ), '0 = delete at course end' );
		self::assertTrue( SettingsRules::retention_order_violation( array( 'raw_answers_years_after_course_end' => 30 ) ), 'a single key is compared with the default of the other' );
		self::assertFalse( SettingsRules::retention_order_violation( array( 'raw_answers_years_after_course_end' => '30' ) ), 'type-invalid values are left to the schema check' );
	}

	/**
	 * Sentence 2: certificates earlier than the attempts are allowed and only flagged.
	 */
	public function test_RR_4_certificates_before_attempts_notice(): void {
		$defaults = RetentionPolicy::defaults();
		self::assertFalse( SettingsRules::certificates_before_attempts( $defaults ), 'defaults: 10 = 10' );
		self::assertTrue( SettingsRules::certificates_before_attempts( array( 'certificates_years' => 9 ) + $defaults ) );
		self::assertFalse( SettingsRules::certificates_before_attempts( array( 'certificates_years' => 20 ) + $defaults ) );
		self::assertTrue( SettingsRules::certificates_before_attempts( array( 'exam_attempts_years' => 11 ) ), 'missing certificate key = default 10' );
		self::assertFalse( SettingsRules::retention_order_violation( array( 'certificates_years' => 1 ) + $defaults ), 'not a rejection' );
	}
}
