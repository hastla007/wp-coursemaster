<?php
/**
 * R1-02: trigger decision of R0-37 §2.2.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Storage\MigrationTrigger as T;

/**
 * Pure decision table.
 *
 * @group R1-02
 */
final class MigrationTriggerTest extends TestCase {

	private const CODE = '1.0.0+aaaaaaaaaaaa';
	private const NOW  = 1_800_000_000;

	/**
	 * State helper.
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return array{code_version: string}
	 */
	private static function state( array $overrides = array() ): array {
		return $overrides + array(
			'network_forced'      => false,
			'stored_version'      => '0.9.0+bbbbbbbbbbbb',
			'code_version'        => self::CODE,
			'fingerprint_current' => false,
			'steps_open'          => false,
			'poison'              => false,
			'last_failed_at'      => null,
			'scheduled_at'        => null,
		);
	}

	/**
	 * Decision table.
	 *
	 * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
	 */
	public function cases(): array {
		return array(
			'network forced'        => array( T::CONTEXT_ADMIN, array( 'network_forced' => true ), T::BLOCKED ),
			'schema newer'          => array( T::CONTEXT_CLI, array( 'stored_version' => '2.0.0+cccccccccccc' ), T::NONE ),
			'current'               => array(
				T::CONTEXT_ADMIN,
				array(
					'stored_version'      => self::CODE,
					'fingerprint_current' => true,
				),
				T::NONE,
			),
			'current but step open' => array(
				T::CONTEXT_ADMIN,
				array(
					'stored_version'      => self::CODE,
					'fingerprint_current' => true,
					'steps_open'          => true,
				),
				T::RUN,
			),
			'cli ignores backoff'   => array(
				T::CONTEXT_CLI,
				array(
					'last_failed_at' => self::NOW - 10,
					'poison'         => true,
				),
				T::RUN,
			),
			'poison'                => array( T::CONTEXT_ADMIN, array( 'poison' => true ), T::NONE ),
			'backoff'               => array( T::CONTEXT_CRON, array( 'last_failed_at' => self::NOW - 60 ), T::WAIT ),
			'backoff over'          => array( T::CONTEXT_CRON, array( 'last_failed_at' => self::NOW - 901 ), T::RUN ),
			'first install'         => array( T::CONTEXT_ACTIVATION, array( 'stored_version' => null ), T::RUN ),
			'admin'                 => array( T::CONTEXT_ADMIN, array(), T::RUN ),
			'frontend schedules'    => array( T::CONTEXT_FRONTEND, array(), T::SCHEDULE ),
			'frontend not overdue'  => array( T::CONTEXT_FRONTEND, array( 'scheduled_at' => self::NOW - 300 ), T::SCHEDULE ),
			'frontend overdue'      => array( T::CONTEXT_FRONTEND, array( 'scheduled_at' => self::NOW - 301 ), T::RUN ),
		);
	}

	/**
	 * R0-37 §2.2 rules 1–8.
	 *
	 * @dataProvider cases
	 * @param string               $context  Context.
	 * @param array<string, mixed> $state    State overrides.
	 * @param string               $expected Decision.
	 */
	public function test_decision( string $context, array $state, string $expected ): void {
		self::assertSame( $expected, T::action( $context, self::state( $state ), self::NOW ) );
	}
}
