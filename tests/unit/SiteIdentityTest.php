<?php
/**
 * Unit tests of the restore/copy decision (R0-16 §2.3, R0-17 §5.5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Kernel\SiteIdentity;

/**
 * `SiteIdentity::decide()` is pure.
 *
 * @group R1-23
 */
final class SiteIdentityTest extends TestCase {

	/**
	 * Stored components.
	 *
	 * @var array<string, string>
	 */
	private const STORED = array(
		'home'        => 'h1',
		'private'     => 'p1',
		'database'    => 'd1',
		'environment' => 'e1',
	);

	/**
	 * Cases: current components, status, epochs → expected decision.
	 *
	 * @return array<string, array{array<string, string>, string, int, int|null, bool, list<string>}>
	 */
	public static function cases(): array {
		return array(
			'unchanged'                    => array( array(), 'ok', 3, 3, false, array() ),
			'unchanged without journal'    => array( array(), 'ok', 3, null, false, array() ),
			'journal behind (normal)'      => array( array(), 'ok', 4, 3, false, array() ),
			'new domain (REC-002)'         => array( array( 'home' => 'h2' ), 'ok', 0, null, true, array( 'home' ) ),
			'copy next to original'        => array(
				array(
					'private'     => 'p2',
					'database'    => 'd2',
					'environment' => 'e2',
				),
				'ok',
				0,
				null,
				true,
				array( 'private', 'database', 'environment' ),
			),
			'restore same place (REC-016)' => array( array(), 'ok', 2, 5, true, array( 'epoch' ) ),
			'already held, values back'    => array( array(), 'held', 3, 3, true, array() ),
			'confirmed copy stays held'    => array( array(), 'copy', 3, 3, true, array() ),
			'move and restore at once'     => array( array( 'home' => 'h3' ), 'ok', 1, 2, true, array( 'home', 'epoch' ) ),
			'missing component counts too' => array( array( 'environment' => '' ), 'ok', 0, null, true, array( 'environment' ) ),
		);
	}

	/**
	 * Decision table.
	 *
	 * @group REC-002
	 * @group REC-003
	 * @group REC-016
	 * @dataProvider cases
	 *
	 * @param array<string, string> $changes  Changed components.
	 * @param string                $status   Stored status.
	 * @param int                   $db       DB epoch.
	 * @param int|null              $journal  Journal epoch.
	 * @param bool                  $held     Expected hold.
	 * @param string[]              $changed  Expected changed list.
	 */
	public function test_decision( array $changes, string $status, int $db, ?int $journal, bool $held, array $changed ): void {
		$current = array_merge( self::STORED, $changes );

		$decision = SiteIdentity::decide( self::STORED, $status, $current, $db, $journal );

		self::assertSame( $held, $decision['held'] );
		self::assertSame( $changed, $decision['changed'] );
	}

	/**
	 * Choices and components are the three ways and four values of R0-16 §2.3.
	 */
	public function test_contract_constants(): void {
		self::assertSame( array( 'same_place', 'moved', 'copy' ), SiteIdentity::CHOICES );
		self::assertSame( array( 'home', 'private', 'database', 'environment' ), SiteIdentity::COMPONENTS );
		self::assertSame( 'course_captain_site_identity', SiteIdentity::OPTION );
		self::assertSame( 'course_captain_state_epoch', SiteIdentity::EPOCH_OPTION );
	}
}
