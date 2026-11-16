<?php
/**
 * Unit tests of the multisite boundary.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use Brain\Monkey\Functions;
use CourseCaptain\Kernel\HoldMode;
use CourseCaptain\Kernel\NetworkGuard;

/**
 * R0-25 MS-R-01/02, E-49.
 *
 * @group R1-01
 */
final class NetworkGuardTest extends TestCase {

	/**
	 * MS-001/MS-002 (decision part): network-wide activation is refused on multisite.
	 *
	 * @group MS-001
	 * @group MS-002
	 * @group KOMP-001
	 */
	public function test_MS_001_network_wide_activation_is_blocked(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_network_admin' )->justReturn( false );

		self::assertTrue( NetworkGuard::must_block_activation( true ) );
		self::assertFalse( NetworkGuard::must_block_activation( false ) );
	}

	/**
	 * The activation error page in the network admin re-runs the hook without arguments; it is refused too.
	 *
	 * @group MS-001
	 */
	public function test_MS_001_sandbox_run_in_network_admin_is_blocked(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_network_admin' )->justReturn( true );

		self::assertTrue( NetworkGuard::must_block_activation( false ) );
	}

	/**
	 * Single site: never blocked (MS-R-09: per-site activation is the normal case).
	 */
	public function test_single_site_is_never_blocked(): void {
		Functions\when( 'is_multisite' )->justReturn( false );

		self::assertFalse( NetworkGuard::must_block_activation( true ) );
		self::assertFalse( NetworkGuard::is_network_forced( 'course-captain/course-captain.php' ) );
	}

	/**
	 * MS-003: the "Network Activate" link is removed, other links stay.
	 *
	 * @group MS-003
	 */
	public function test_MS_003_network_activate_link_is_removed(): void {
		$links = NetworkGuard::filter_network_action_links(
			array(
				'activate' => '<a href="#">Network Activate</a>',
				'delete'   => '<a href="#">Delete</a>',
			)
		);

		self::assertSame( array( 'delete' => '<a href="#">Delete</a>' ), $links );
		self::assertSame( 'not-an-array', NetworkGuard::filter_network_action_links( 'not-an-array' ) );
	}

	/**
	 * MS-004 (detection part): an entry in `active_sitewide_plugins` starts the hold mode.
	 *
	 * @group MS-004
	 * @group KOMP-001
	 */
	public function test_MS_004_forced_network_activation_starts_hold_mode(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_site_option' )->justReturn( array( 'course-captain/course-captain.php' => 1700000000 ) );

		$hold = HoldMode::detect( 'course-captain/course-captain.php' );

		self::assertTrue( $hold->is_active() );
		self::assertTrue( $hold->has( HoldMode::REASON_NETWORK_FORCED ) );
		self::assertFalse( $hold->has( HoldMode::REASON_SITE_IDENTITY ) );
		self::assertNotSame( '', $hold->message() );
	}

	/**
	 * Without the entry there is no hold mode.
	 *
	 * @group MS-004
	 */
	public function test_MS_004_no_hold_mode_without_sitewide_entry(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_site_option' )->justReturn( array( 'other/other.php' => 1 ) );

		$hold = HoldMode::detect( 'course-captain/course-captain.php' );

		self::assertFalse( $hold->is_active() );
		self::assertSame( array(), $hold->reasons() );
		self::assertSame( '', $hold->message() );
	}
}
