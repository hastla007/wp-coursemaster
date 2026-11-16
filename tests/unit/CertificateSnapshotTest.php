<?php
/**
 * Unit tests of the certificate snapshot (R0-29 §2.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Certificate\CertificateSnapshot;
use CourseCaptain\Course\CourseException;

/**
 * CRT-008, CRT-009, display ID and check code.
 *
 * @group R1-44
 */
final class CertificateSnapshotTest extends TestCase {

	/**
	 * Minimal valid snapshot.
	 *
	 * @return array<string, mixed>
	 */
	private static function snapshot(): array {
		return array(
			'format_version'  => 1,
			'certificate_uid' => '7f3a91d2-0000-4000-8000-000000000001',
			'revision_no'     => 1,
			'revision_kind'   => 'initial',
			'display_id'      => 'CC-7F3A-91D2',
			'template'        => array(
				'template_uid'    => '11111111-0000-4000-8000-000000000001',
				'version'         => 1,
				'kind'            => 'base',
				'definition_hash' => 'sha256:' . str_repeat( 'a', 64 ),
			),
			'locale'          => array(
				'content' => 'de-DE',
				'ui'      => 'de_DE',
			),
			'issued_at'       => '2026-11-16T09:30:00Z',
			'issued_by'       => array(
				'type' => 'system',
				'role' => 'system',
			),
			'issuer'          => array( 'name' => 'Akademie' ),
			'holder'          => array( 'display_name' => "J\u{00FC}rgen M\u{00FC}ller" ),
			'course'          => array(
				'course_uid'         => '22222222-0000-4000-8000-000000000001',
				'title'              => 'Grundkurs',
				'course_revision_id' => 3,
			),
			'evidence'        => array(
				'type'              => 'exam_session',
				'ref_uuid'          => '33333333-0000-4000-8000-000000000001',
				'points'            => 27000,
				'max_points'        => 30000,
				'passed'            => true,
				'evaluated_at'      => '2026-11-16T09:00:00Z',
				'evaluation_no'     => 1,
				'rule_uid'          => '44444444-0000-4000-8000-000000000001',
				'rule_version'      => 'v3',
				'evidence_eligible' => true,
			),
			'objectives'      => array(),
			'valid_until'     => null,
			'fields'          => array(),
			'assets'          => array(),
			'texts'           => array(
				'heading' => 'Certificate',
				'body'    => 'has passed',
				'footer'  => '',
			),
			'verify'          => array( 'path_at_issue' => '/lernen/nachweis/' ),
		);
	}

	/**
	 * CRT-008: other key order and NFD umlauts give the same hash.
	 *
	 * @group CRT-008
	 */
	public function test_CRT_008_canonical_hash(): void {
		$a                           = self::snapshot();
		$b                           = array_reverse( $a, true );
		$b['course']                 = array_reverse( $b['course'], true );
		$b['holder']['display_name'] = "Ju\u{0308}rgen Mu\u{0308}ller";
		self::assertNotSame( $a['holder']['display_name'], $b['holder']['display_name'] );
		self::assertSame( CertificateSnapshot::hash( $a ), CertificateSnapshot::hash( $b ) );
		self::assertMatchesRegularExpression( '/^sha256:[0-9a-f]{64}$/', CertificateSnapshot::hash( $a ) );
		$sealed = CertificateSnapshot::seal( $b );
		self::assertSame( $sealed['hash'], $sealed['snapshot']['snapshot_hash'] );
		self::assertTrue( CertificateSnapshot::intact( $sealed['json'], $sealed['hash'] ) );
		self::assertFalse( CertificateSnapshot::intact( str_replace( 'Grundkurs', 'Grundkurz', $sealed['json'] ), $sealed['hash'] ) );
	}

	/**
	 * CRT-009: unknown or missing fields are rejected.
	 *
	 * @group CRT-009
	 */
	public function test_CRT_009_validation(): void {
		$cases = array();
		$extra = self::snapshot();

		$extra['grade'] = 'A';
		$cases[]        = $extra;
		$nested         = self::snapshot();

		$nested['course']['email'] = 'x@example.org';
		$cases[]                   = $nested;
		$missing                   = self::snapshot();
		unset( $missing['holder'] );
		$cases[] = $missing;
		$type    = self::snapshot();

		$type['revision_no'] = '1';
		$cases[]             = $type;
		foreach ( $cases as $case ) {
			try {
				CertificateSnapshot::seal( $case );
				self::fail( 'expected ccap_validation_failed' );
			} catch ( CourseException $e ) {
				self::assertSame( 'ccap_validation_failed', $e->error_code );
			}
		}
		CertificateSnapshot::validate( self::snapshot() );
		self::assertTrue( true );
	}

	/**
	 * Display ID from the UID; check code = first 12 hex characters, grouped.
	 */
	public function test_display_id_and_check_code(): void {
		self::assertSame( 'CC-7F3A-91D2', CertificateSnapshot::display_id( '7f3a91d2-1234-4000-8000-000000000001' ) );
		self::assertSame( 'ABCD-EF01-2345', CertificateSnapshot::check_code( 'sha256:abcdef0123456789' ) );
	}
}
