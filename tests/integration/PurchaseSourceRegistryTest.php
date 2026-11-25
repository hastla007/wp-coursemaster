<?php
/**
 * Registry of the purchase grant sources instead of the hard-coded list
 * `woo_order`/`voucher` (R0-09 §7, R0-31 RT-13/RT-14): a connector adds its
 * own purchase source with `course_captain_purchase_sources`, the eraser
 * pseudonymises its grants instead of deleting them.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\PurchaseSources;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Privacy\RetentionService;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group SET-CC-02
 * @group aufbewahrung-2
 * @group purchase-sources
 */
final class PurchaseSourceRegistryTest extends SchemaTestCase {

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->course = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'C',
			)
		);
	}

	/**
	 * Clean up.
	 */
	public function tear_down(): void {
		remove_all_filters( 'course_captain_purchase_sources' );
		parent::tear_down();
	}

	/**
	 * Grant row.
	 *
	 * @param int    $user   User.
	 * @param string $source Source type.
	 * @param string $status Status.
	 * @return int
	 */
	private function grant( int $user, string $source, string $status = 'active' ): int {
		$db = Database::current();
		return $db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $this->course,
				'status'      => $status,
				'source_type' => $source,
				'source_id'   => $db->uuid(),
				'created_at'  => $db->now(),
				'updated_at'  => $db->now(),
			)
		);
	}

	/**
	 * Without extensions the registry holds exactly the core purchase
	 * sources; the old constant is an alias of them.
	 */
	public function test_core_sources_without_filter(): void {
		self::assertSame( array( 'woo_order', 'voucher' ), PurchaseSources::all() );
		self::assertSame( PurchaseSources::CORE, PrivacyService::PURCHASE_SOURCES );
		self::assertTrue( PurchaseSources::is_purchase( 'voucher' ) );
		self::assertFalse( PurchaseSources::is_purchase( 'manual' ) );
		self::assertSame( "'woo_order', 'voucher'", PurchaseSources::sql_list() );
	}

	/**
	 * The filter only adds: core types cannot be removed; invalid names,
	 * duplicates, non-strings and the non-purchase sources of RT-14 are
	 * ignored (no SQL injection through the IN list).
	 */
	public function test_filter_is_additive_and_validated(): void {
		add_filter(
			'course_captain_purchase_sources',
			static function (): array {
				return array( 'shop_order', 'shop_order', 'manual', 'group_seat', 'workflow', 'free_enrollment', "x') OR ('1'='1", 'Upper', str_repeat( 'a', 33 ), 7, '' );
			}
		);
		self::assertSame( array( 'woo_order', 'voucher', 'shop_order' ), PurchaseSources::all() );
		self::assertTrue( PurchaseSources::is_purchase( 'shop_order' ) );
		self::assertFalse( PurchaseSources::is_purchase( 'manual' ) );

		remove_all_filters( 'course_captain_purchase_sources' );
		add_filter( 'course_captain_purchase_sources', '__return_null' );
		self::assertSame( PurchaseSources::CORE, PurchaseSources::all(), 'a broken filter keeps the core types' );
	}

	/**
	 * RT-13 for a registered connector source: the eraser keeps the grant
	 * pseudonymised (running one revoked/account_deleted), an unregistered
	 * foreign source is deleted at once (RT-14), RT-13 deletes the history
	 * after its period.
	 */
	public function test_eraser_pseudonymises_registered_connector_source(): void {
		add_filter(
			'course_captain_purchase_sources',
			static function ( array $sources ): array {
				$sources[] = 'shop_order';
				return $sources;
			}
		);
		$db      = Database::current();
		$learner = (int) self::factory()->user->create( array( 'user_email' => 'shop-' . wp_generate_password( 6, false ) . '@example.org' ) );
		$shop    = $this->grant( $learner, 'shop_order' );
		$ended   = $this->grant( $learner, 'shop_order', 'expired' );
		$other   = $this->grant( $learner, 'other_connector' );

		self::assertTrue( PrivacyService::erase( (string) get_userdata( $learner )->user_email )['items_removed'] );

		$ref  = PrivacyService::grant_subject_ref( $learner );
		$kept = $db->rows( 'SELECT id, user_id, subject_ref, status, revocation_reason FROM `' . $db->table( 'ccap_grant' ) . "` WHERE source_type = 'shop_order' ORDER BY id" );
		self::assertSame( array( $shop, $ended ), array_map( 'intval', array_column( $kept, 'id' ) ), 'registered purchase source stays' );
		foreach ( $kept as $row ) {
			self::assertNull( $row['user_id'] );
			self::assertSame( $ref, $row['subject_ref'] );
		}
		self::assertSame( array( 'revoked', 'account_deleted' ), array( $kept[0]['status'], $kept[0]['revocation_reason'] ) );
		self::assertSame( 'expired', $kept[1]['status'] );
		self::assertSame( 0, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $other ) ), 'unregistered source deleted (RT-14)' );

		$result = RetentionService::run( time() + 11 * YEAR_IN_SECONDS );
		self::assertSame( 2, $result['RT-13_grant_history_deleted'] );
	}
}
