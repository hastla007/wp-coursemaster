<?php
/**
 * Unit tests of kernel constants, prefixes and the version handshake.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Contract\Api;
use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Tests\Support\StorageKeyScanner;

/**
 * R0-07 §3.1, E-83, R0-19 §1.2/§1.6/§4.8, R0-20 §3.
 *
 * @group R1-01
 */
final class KernelContractTest extends TestCase {

	/**
	 * E-83: 24 unique `ccap_` capabilities; only `administrator` receives them on activation.
	 */
	public function test_capability_scaffold_matches_r0_07(): void {
		self::assertCount( 24, Capabilities::PRIMITIVE );
		self::assertSame( Capabilities::PRIMITIVE, array_values( array_unique( Capabilities::PRIMITIVE ) ) );
		foreach ( Capabilities::PRIMITIVE as $cap ) {
			self::assertMatchesRegularExpression( '/^ccap_[a-z_]+$/', $cap );
		}
		self::assertSame( array( 'administrator' ), Capabilities::ACTIVATION_ROLES );
	}

	/**
	 * GM-158 precondition: all five R1 cron hooks are listed and prefixed (R0-19 §4.8).
	 *
	 * @group GM-158
	 */
	public function test_GM_158_cron_hook_registry(): void {
		self::assertSame(
			array( 'ccap_hourly', 'ccap_outbox_dispatch', 'ccap_task_continue', 'ccap_migrate', 'ccap_privacy_export_generate' ),
			CronHooks::ALL
		);
	}

	/**
	 * Version handshake: unknown feature keys are unsupported (R0-19 §1.6).
	 */
	public function test_api_handshake(): void {
		$api = new Api();

		self::assertSame( COURSE_CAPTAIN_CONTRACT_VERSION, $api->contract_version() );
		self::assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $api->contract_version() );
		self::assertFalse( $api->supports( 'events.v1' ) );
		self::assertFalse( $api->supports( 'grants.write.v1' ) );
		self::assertFalse( $api->supports( 'unknown.feature' ) );
	}

	/**
	 * KOMP-130 / RC-032 (static part): own hooks, storage keys and CSS-free names carry the prefixes of R0-19 §1.2.
	 *
	 * @group KOMP-130
	 * @group RC-032
	 */
	public function test_KOMP_130_prefix_scan(): void {
		$root  = self::root();
		$files = StorageKeyScanner::php_files( array( $root . '/course-captain.php', $root . '/uninstall.php', $root . '/src' ) );

		$violations = array();
		foreach ( $files as $file ) {
			$code = (string) file_get_contents( $file );

			// Hooks defined by Course Captain.
			preg_match_all( '/\b(?:do_action|apply_filters|do_action_ref_array|apply_filters_ref_array)\(\s*[\'"]([^\'"]+)[\'"]/', $code, $hooks );
			foreach ( $hooks[1] as $hook ) {
				// R1-30: foreign/core hooks probed read-only or fired for page caches (R0-20 KOMP-032/052/058).
				if ( in_array( $hook, array( 'litespeed_control_set_nocache', 'rest_authentication_errors', 'xmlrpc_enabled' ), true ) ) {
					continue;
				}
				if ( ! str_starts_with( $hook, 'course_captain_' ) && ! str_starts_with( $hook, 'ccap_' ) ) {
					$violations[] = $file . ': hook ' . $hook;
				}
			}

			// Global functions and constants.
			preg_match_all( '/^function\s+([a-z0-9_]+)/mi', $code, $functions );
			foreach ( $functions[1] as $function ) {
				if ( ! str_starts_with( $function, 'course_captain_' ) ) {
					$violations[] = $file . ': function ' . $function;
				}
			}
			preg_match_all( '/\bdefine\(\s*[\'"]([^\'"]+)[\'"]/', $code, $constants );
			foreach ( $constants[1] as $constant ) {
				// GM-116: `DONOTCACHEPAGE` is the shared page-cache convention, not an own constant.
				if ( ! str_starts_with( $constant, 'COURSE_CAPTAIN_' ) && ! str_starts_with( $constant, 'CourseCaptain\\' ) && 'DONOTCACHEPAGE' !== $constant ) {
					$violations[] = $file . ': constant ' . $constant;
				}
			}
		}

		foreach ( StorageKeyScanner::own_keys( $files ) as $key => $locations ) {
			$is_option = str_starts_with( $key, 'course_captain_' );
			$is_cron   = in_array( $key, CronHooks::ALL, true );
			// E-207: meta keys carry `_ccap_` and must be in the uninstall manifest.
			$is_meta = 1 === preg_match( '/^_?ccap_/', $key ) && in_array( $key, array_merge( UninstallManifest::post_meta(), UninstallManifest::user_meta() ), true );
			if ( ! $is_option && ! $is_cron && ! $is_meta ) {
				$violations[] = 'storage key ' . $key . ' (' . implode( ', ', $locations ) . ')';
			}
		}

		self::assertSame( array(), $violations );
	}

	/**
	 * Every file in src/ ends on direct access (R0-18 J-01 check-abspath).
	 */
	public function test_every_src_file_exits_on_direct_access(): void {
		foreach ( StorageKeyScanner::php_files( array( self::root() . '/src' ) ) as $file ) {
			self::assertStringContainsString( "defined( 'ABSPATH' ) || exit;", (string) file_get_contents( $file ), $file );
		}
	}
}
