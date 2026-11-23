<?php
/**
 * Server-side effect of the privacy tab (R0-27 `privacy.*`, R0-31 §1.2,
 * MC-5, MC-9, RB-9; E-43, E-223).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Privacy;

use CourseCaptain\Event\AuditLog;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * - `auto_export_on_confirm` (default on): a confirmed export request is
 *   processed in the background and the link goes to the confirmed address.
 * - `auto_erase_on_confirm` (default off): a confirmed erasure request
 *   deletes the account, except protected staff accounts (administrative
 *   or any `ccap_*` capability, filter `course_captain_account_deletion_protected`).
 * - `show_avatars` (default off): no Gravatar in the learning area (MC-9).
 */
final class PrivacySettings {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'user_request_action_confirmed',
			static function ( $request_id ): void {
				self::on_confirmed( $request_id );
			},
			20
		);
		add_action(
			CronHooks::PRIVACY_EXPORT,
			static function ( $request_id, $user_id = 0 ): void {
				// `[0, user_id]`: self-service export (GM-175); otherwise a core data request.
				if ( (int) $user_id > 0 ) {
					DataExport::generate( (int) $user_id );
					return;
				}
				self::generate_export( $request_id );
			},
			10,
			2
		);
		add_filter( 'pre_option_show_avatars', array( self::class, 'show_avatars' ) );
	}

	/**
	 * A data request was confirmed by the person.
	 *
	 * @param int $request_id Request.
	 * @return string What happened: `export_scheduled`, `erased`, `protected`, `none`.
	 */
	public static function on_confirmed( $request_id ): string {
		$request = wp_get_user_request( (int) $request_id );
		if ( ! $request instanceof \WP_User_Request ) {
			return 'none';
		}
		if ( 'export_personal_data' === $request->action_name && true === Settings::get( 'privacy.auto_export_on_confirm' ) ) {
			if ( false === wp_next_scheduled( CronHooks::PRIVACY_EXPORT, array( (int) $request_id ) ) ) {
				wp_schedule_single_event( time(), CronHooks::PRIVACY_EXPORT, array( (int) $request_id ) );
			}
			self::audit( 'export_scheduled', (int) $request_id );
			return 'export_scheduled';
		}
		if ( 'remove_personal_data' === $request->action_name && true === Settings::get( 'privacy.auto_erase_on_confirm' ) ) {
			$user = get_user_by( 'email', $request->email );
			if ( ! $user instanceof \WP_User ) {
				return 'none';
			}
			if ( self::protected_account( $user ) ) {
				self::audit( 'erase_protected', (int) $request_id );
				return 'protected';
			}
			require_once ABSPATH . 'wp-admin/includes/user.php';
			require_once ABSPATH . 'wp-admin/includes/privacy-tools.php';
			if ( is_multisite() ) {
				remove_user_from_blog( (int) $user->ID, get_current_blog_id() );
				PrivacyService::erase_user( (int) $user->ID );
			} else {
				wp_delete_user( (int) $user->ID );
			}
			_wp_privacy_completed_request( (int) $request_id );
			self::audit( 'erased', (int) $request_id );
			return 'erased';
		}
		return 'none';
	}

	/**
	 * RB-9: staff accounts are never deleted automatically.
	 *
	 * @param \WP_User $user User.
	 * @return bool
	 */
	public static function protected_account( \WP_User $user ): bool {
		$protected = user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) || user_can( $user, 'promote_users' );
		foreach ( array_keys( array_filter( (array) $user->allcaps ) ) as $cap ) {
			if ( str_starts_with( (string) $cap, 'ccap_' ) ) {
				$protected = true;
			}
		}
		return (bool) apply_filters( 'course_captain_account_deletion_protected', $protected, $user );
	}

	/**
	 * Background export: run all exporters and send the link (core export pipeline).
	 *
	 * @param int $request_id Request.
	 * @return bool
	 */
	public static function generate_export( $request_id ): bool {
		$request = wp_get_user_request( (int) $request_id );
		if ( ! $request instanceof \WP_User_Request || 'export_personal_data' !== $request->action_name ) {
			return false;
		}
		require_once ABSPATH . 'wp-admin/includes/privacy-tools.php';
		$registry  = 'wp_privacy_personal_data_exporters';
		$exporters = (array) apply_filters( $registry, array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Core exporter registry.
		$index     = 0;
		foreach ( $exporters as $key => $exporter ) {
			++$index;
			if ( ! is_array( $exporter ) || ! is_callable( $exporter['callback'] ?? null ) ) {
				continue;
			}
			for ( $page = 1; $page <= 100; $page++ ) {
				$response = call_user_func( $exporter['callback'], $request->email, $page );
				$response = is_array( $response ) ? $response : array(
					'data' => array(),
					'done' => true,
				);
				$response = wp_privacy_process_personal_data_export_page( $response, $index, $request->email, $page, (int) $request_id, true, (string) $key );
				if ( ! empty( $response['done'] ) ) {
					break;
				}
			}
		}
		return true;
	}

	/**
	 * No Gravatar in the learning area unless `privacy.show_avatars` (MC-9).
	 *
	 * @param mixed $value Pre-option value.
	 * @return mixed
	 */
	public static function show_avatars( $value ) {
		if ( is_admin() || ! did_action( 'parse_query' ) ) {
			return $value;
		}
		$path = get_query_var( LearningArea::QUERY_VAR, null );
		if ( null === $path || false === $path ) {
			return $value;
		}
		return true === Settings::get( 'privacy.show_avatars' ) ? $value : '0';
	}

	/**
	 * Audit without personal data (request ID only).
	 *
	 * @param string $what       What.
	 * @param int    $request_id Request.
	 * @return void
	 */
	private static function audit( string $what, int $request_id ): void {
		AuditLog::write( Database::current(), Actor::cron(), 'privacy_request_automated', 'privacy_request', (string) $request_id, null, array( 'step' => $what ) );
	}
}
