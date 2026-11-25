<?php
/**
 * PRIV-003 (R0-17 §7, T-42) for the columns found by the PRIV-001 scan
 * that the eraser did not clear: question author, rights override author
 * and the actor of a progress reset. After the erasure no row names the
 * person any more; the content rows and other people's references stay.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-17
 * @group PRIV-003
 */
final class PrivacyPersonColumnsTest extends SchemaTestCase {

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
	}

	/**
	 * Clean up.
	 */
	public function tear_down(): void {
		self::fk( true );
		parent::tear_down();
	}

	/**
	 * Foreign key checks (fixture rows without parents).
	 *
	 * @param bool $on On.
	 * @return void
	 */
	private static function fk( bool $on ): void {
		$GLOBALS['wpdb']->query( 'SET FOREIGN_KEY_CHECKS = ' . ( $on ? '1' : '0' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Rows per author/actor column.
	 *
	 * @param string $table  Table.
	 * @param string $column Column.
	 * @param int    $user   User.
	 * @return int
	 */
	private static function refs( string $table, string $column, int $user ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . "` WHERE `{$column}` = %d", $user ) );
	}

	/**
	 * Rows of a table.
	 *
	 * @param string $table Table.
	 * @return int
	 */
	private static function rows_in( string $table ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '`' );
	}

	/**
	 * Author and actor references are cleared, rows and foreign references stay.
	 */
	public function test_PRIV_003_author_and_actor_references_cleared(): void {
		$db     = Database::current();
		$author = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		$other  = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		$now    = $db->now();
		self::fk( false );
		foreach ( array( $author, $other ) as $user ) {
			$db->insert(
				'ccap_question',
				array(
					'uid'            => $db->uuid(),
					'status'         => 'published',
					'author_user_id' => $user,
					'created_at'     => $now,
					'updated_at'     => $now,
				)
			);
			$db->insert(
				'ccap_override',
				array(
					'object_type' => 'question',
					'object_id'   => $user,
					'field'       => 'rights_status',
					'value'       => 'cleared',
					'reason'      => 'Licence checked',
					'user_id'     => $user,
					'created_at'  => $now,
				)
			);
		}
		$learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$db->insert(
			'ccap_reset_journal',
			array(
				'user_id'       => $learner,
				'course_id'     => 1,
				'prior_state'   => '{}',
				'actor_user_id' => $author,
				'actor_role'    => 'trainer',
				'created_at'    => $now,
			)
		);

		$questions = self::rows_in( 'ccap_question' );
		$overrides = self::rows_in( 'ccap_override' );
		self::assertGreaterThan( 0, PrivacyService::erase_user( $author ) );

		foreach ( array( array( 'ccap_question', 'author_user_id' ), array( 'ccap_override', 'user_id' ), array( 'ccap_reset_journal', 'actor_user_id' ) ) as $ref ) {
			self::assertSame( 0, self::refs( $ref[0], $ref[1], $author ), $ref[0] . '.' . $ref[1] . ' cleared' );
		}
		self::assertSame( 1, self::refs( 'ccap_question', 'author_user_id', $other ), 'other author stays' );
		self::assertSame( 1, self::refs( 'ccap_override', 'user_id', $other ), 'other override author stays' );
		self::assertSame( $questions, self::rows_in( 'ccap_question' ), 'content stays' );
		self::assertSame( $overrides, self::rows_in( 'ccap_override' ), 'rights decisions stay' );
		self::assertSame( 1, self::refs( 'ccap_reset_journal', 'user_id', $learner ), 'the learner\'s reset journal stays (undo window)' );
		self::assertSame( 0, PrivacyService::erase_user( $author ), 'second run without effect' );
	}
}
