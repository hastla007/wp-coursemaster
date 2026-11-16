<?php
/**
 * Uninstall routine.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

use CourseCaptain\Course\DeletionGuard;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Schema;
use CourseCaptain\Storage\SchemaInspector;

defined( 'ABSPATH' ) || exit;

/**
 * Uninstall after R0-16 §2.5, R0-25 MS-R-13 and R0-39 FG-10.
 *
 * Default: data stays. Only cron events, transients and rewrite rules are
 * removed. Data is deleted only on sites whose general settings enable
 * `delete_data_on_uninstall` (the setting can only be switched on with the
 * confirmation of E-89: site name, backup hint and audit entry).
 */
final class Uninstaller {

	/**
	 * Run the uninstall for the whole installation.
	 *
	 * @return array<int, bool> Map site ID => whether data was deleted on that site.
	 */
	public static function run(): array {
		$result = array();

		if ( is_multisite() ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				$site_id = (int) $site_id;
				switch_to_blog( $site_id );
				try {
					$result[ $site_id ] = self::uninstall_current_site();
				} finally {
					restore_current_blog();
				}
			}
		} else {
			$result[ get_current_blog_id() ] = self::uninstall_current_site();
		}

		// Network-wide user meta only when no site keeps its data (MS-R-13).
		if ( array() !== $result && ! in_array( false, $result, true ) ) {
			foreach ( UninstallManifest::user_meta() as $meta_key ) {
				delete_metadata( 'user', 0, $meta_key, '', true );
			}
		}

		if ( in_array( true, $result, true ) ) {
			wp_cache_flush();
		}

		return $result;
	}

	/**
	 * Uninstall the current site.
	 *
	 * @return bool Whether data was deleted.
	 */
	public static function uninstall_current_site(): bool {
		CronHooks::unschedule_all();

		foreach ( UninstallManifest::transients() as $transient ) {
			delete_transient( $transient );
		}
		delete_option( 'rewrite_rules' );

		if ( ! self::is_data_deletion_confirmed( get_option( UninstallManifest::GENERAL_SETTINGS_OPTION, null ) ) ) {
			return false;
		}

		$manifest = UninstallManifest::to_array();

		/**
		 * Fires on each site before Course Captain deletes its data after the
		 * confirmed uninstall (E-89).
		 *
		 * @since 1.0.0
		 * @param array<string, list<string>> $manifest Uninstall manifest.
		 */
		do_action( 'course_captain_uninstall', $manifest );

		global $wpdb;
		foreach ( UninstallManifest::user_options() as $user_option ) {
			delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . $user_option, '', true );
		}

		self::delete_own_posts();
		foreach ( UninstallManifest::post_meta() as $meta_key ) {
			delete_post_meta_by_key( $meta_key );
		}

		Capabilities::remove_all();
		foreach ( UninstallManifest::roles() as $role ) {
			remove_role( $role );
		}

		self::drop_own_tables();

		foreach ( UninstallManifest::all_option_names() as $option ) {
			delete_option( $option );
		}
		SiteIdentity::delete_journal();

		return true;
	}

	/**
	 * Delete every post of the own post types with revisions, meta and terms
	 * (R0-16 §2.5 step 5). Runs before the tables are dropped; the
	 * application-level RESTRICT does not apply to the confirmed uninstall.
	 * Attachments stay in the media library (re-parented by WordPress).
	 *
	 * @return int Deleted posts.
	 */
	public static function delete_own_posts(): int {
		global $wpdb;
		$guard = has_filter( 'pre_delete_post', array( DeletionGuard::class, 'filter' ) );
		if ( false !== $guard ) {
			remove_filter( 'pre_delete_post', array( DeletionGuard::class, 'filter' ), (int) $guard );
		}
		$deleted = 0;
		try {
			foreach ( UninstallManifest::post_types() as $post_type ) {
				do {
					// One-off uninstall query by exact post type; no cache involved.
					$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID LIMIT 200", $post_type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$batch = 0;
					foreach ( $ids as $id ) {
						if ( wp_delete_post( (int) $id, true ) ) {
							++$batch;
						}
					}
					$deleted += $batch;
				} while ( array() !== $ids && $batch > 0 );
			}
		} finally {
			if ( false !== $guard ) {
				add_filter( 'pre_delete_post', array( DeletionGuard::class, 'filter' ), (int) $guard, 2 );
			}
		}
		return $deleted;
	}

	/**
	 * Drop the core tables of the current site that carry the ownership
	 * marker (KOMP-131), children first.
	 *
	 * @return list<string> Dropped tables without prefix.
	 */
	public static function drop_own_tables(): array {
		$db       = Database::current();
		$existing = ( new SchemaInspector( $db ) )->tables();
		$dropped  = array();
		foreach ( UninstallManifest::tables() as $table ) {
			// KOMP-131: only tables carrying our ownership marker.
			if ( ! isset( $existing[ $table ] ) || Schema::OWNER_COMMENT !== $existing[ $table ]['comment'] ) {
				continue;
			}
			$db->execute( 'DROP TABLE IF EXISTS `' . $db->table( $table ) . '`' );
			$dropped[] = $table;
		}
		return $dropped;
	}

	/**
	 * Whether the stored general settings enable data deletion.
	 *
	 * Only a strict boolean `true` counts (fail closed for any other value).
	 *
	 * @param mixed $general Value of the option `course_captain_settings_general`.
	 * @return bool
	 */
	public static function is_data_deletion_confirmed( $general ): bool {
		if ( ! is_array( $general ) || ! isset( $general['values'] ) || ! is_array( $general['values'] ) ) {
			return false;
		}
		return true === ( $general['values'][ UninstallManifest::DELETE_DATA_KEY ] ?? null );
	}
}
