<?php
/**
 * Gate C-13 (R0-12 §3.3): measures of the threat model R0-17 checked
 * against the code (sample), findings fixed in the C-13 run.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ContentProtection;
use CourseCaptain\Certificate\CertificatePages;
use CourseCaptain\Learner\LearningArea;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Reads the own source to assert the header call sites.

/**
 * R0-17 T-33 / §3.5: token pages send no referrer and are not cached.
 *
 * @group R1-18
 * @group C-13
 */
final class GateCSecurityTest extends TestCase {

	/**
	 * Body of a method as source text.
	 *
	 * @param class-string $class  Class.
	 * @param string       $method Method.
	 * @return string
	 */
	private static function source( string $class, string $method ): string {
		$ref   = new \ReflectionMethod( $class, $method );
		$lines = (array) file( (string) $ref->getFileName() );
		return implode( '', array_slice( $lines, (int) $ref->getStartLine() - 1, (int) $ref->getEndLine() - (int) $ref->getStartLine() + 1 ) );
	}

	/**
	 * T-33: claim and verification URLs carry a token; the pages send
	 * `Referrer-Policy: no-referrer`, `nosniff` and no-store.
	 */
	public function test_C_13_T_33_token_pages_send_no_referrer(): void {
		$headers = ContentProtection::token_page_headers();
		self::assertSame( 'no-referrer', $headers['Referrer-Policy'] );
		self::assertSame( 'nosniff', $headers['X-Content-Type-Options'] );
		self::assertSame( 'no-store, private', $headers['Cache-Control'] );
		self::assertSame( 'no-store', $headers['CDN-Cache-Control'] );

		// Call sites: the invitation (claim) branch of the learning area and the verification page.
		$area = self::source( LearningArea::class, 'serve' );
		self::assertMatchesRegularExpression( '/if \( null !== \$claim \) \{\s*ContentProtection::send_token_page_headers\(\);/', $area );
		self::assertLessThan( strpos( $area, 'echo self::page' ), strpos( $area, 'send_token_page_headers' ), 'headers before output' );
		$cert = self::source( CertificatePages::class, 'serve' );
		self::assertStringContainsString( "header( 'Referrer-Policy: no-referrer' );", $cert );
		self::assertLessThan( strpos( $cert, 'echo $page' ), strpos( $cert, 'Referrer-Policy' ) );
	}
}
