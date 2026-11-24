<?php
/**
 * PRIV-005 (R0-17 §7, T-45): the pseudonym of a deleted account in the
 * grant history (`ccap_grant.subject_ref`, R0-09 §7) cannot be recomputed
 * from the user ID without the site secret – no plain SHA-256 of any ID
 * 1…10⁶ (with or without the known prefix) hits it.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use Brain\Monkey\Functions;
use CourseCaptain\Privacy\PrivacyService;

/**
 * Brute force over the ID space without the key.
 *
 * @group R1-17
 * @group PRIV-005
 * @group T-45
 */
final class PseudonymReversalTest extends TestCase {

	/**
	 * Site secret of the test (24 lower-case characters, as ProtectedStorage::secret() creates it).
	 */
	private const SECRET = 'k3y9q0w8e7r6t5y4u3i2o1pz';

	/**
	 * No unkeyed hash of IDs 1…10⁶ matches a pseudonym; the pseudonym is
	 * stable, differs per ID and depends on the secret.
	 */
	public function test_PRIV_005_no_reversal_without_key(): void {
		Functions\when( 'get_option' )->justReturn( self::SECRET );
		$ids  = array( 1, 2, 7, 42, 1000, 31337, 999999, 1000000 );
		$refs = array();
		foreach ( $ids as $id ) {
			$ref = PrivacyService::grant_subject_ref( $id );
			self::assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $ref );
			self::assertSame( $ref, PrivacyService::grant_subject_ref( $id ), 'stable' );
			$refs[ $ref ] = $id;
		}
		self::assertCount( count( $ids ), $refs, 'one pseudonym per ID' );

		$hits = 0;
		for ( $id = 1; $id <= 1000000; $id++ ) {
			foreach ( array( (string) $id, 'user:' . $id, 'course-captain-grant-subject|' . $id ) as $plain ) {
				if ( isset( $refs[ hash( 'sha256', $plain ) ] ) ) {
					++$hits;
				}
			}
		}
		self::assertSame( 0, $hits, 'no plain SHA-256 of an ID hits a pseudonym' );

		$wrong = hash_hmac( 'sha256', 'course-captain-grant-subject|42', 'wrong-secret-of-another-s' );
		self::assertArrayNotHasKey( $wrong, $refs, 'another site secret gives another pseudonym' );
	}
}
