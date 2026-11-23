<?php
/**
 * Asynchronous self-service data export (R0-05 GM-175; R0-19 §3
 * `ccap_export_not_ready`, `ccap_export_expired`, `ccap_export_failed`;
 * R0-31 RT-36).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Privacy;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\RateLimiter;

defined( 'ABSPATH' ) || exit;

/**
 * A logged-in person asks for an export of their Course Captain data. The
 * job starts as `pending` and is built in the background (`ccap_privacy_export_generate`
 * with the arguments `[0, user_id]`); then it is `ready` for 24 hours (RT-36)
 * or `failed`. Each person has at most one job (per site, user option); a
 * request while a job is pending returns that job. Only the owner can read a
 * job: the job store is the owner's own user option, other IDs are 404.
 * The file lives in the private `packages` folder and is deleted on expiry.
 */
final class DataExport {

	public const OPTION      = 'course_captain_data_export';
	public const TTL         = DAY_IN_SECONDS;
	public const LIMIT_DAY   = 5;
	public const PENDING     = 'pending';
	public const READY       = 'ready';
	public const FAILED      = 'failed';
	public const EXPIRED     = 'expired';
	private const MAX_PAGES  = 100;
	private const SUBFOLDER  = 'exports';
	private const RETRY_SECS = 30;

	/**
	 * Request an export (REST `POST /me/data-exports`).
	 *
	 * @param Actor    $actor Person.
	 * @param int|null $now   Time (tests).
	 * @return array{export: array<string, mixed>, created: bool}
	 * @throws CourseException 401 without account, 429 over the limit.
	 */
	public static function request( Actor $actor, ?int $now = null ): array {
		$now  = $now ?? time();
		$user = $actor->user_id;
		if ( $user <= 0 || ! get_userdata( $user ) instanceof \WP_User ) {
			throw new CourseException( 'ccap_auth_required', 'Please log in.', 401 );
		}
		$job = self::job( $user );
		if ( null !== $job && self::PENDING === $job['status'] ) {
			return array(
				'export'  => self::public_view( $job ),
				'created' => false,
			);
		}
		RateLimiter::hit( Database::current(), 'privacy_export', $user, DAY_IN_SECONDS, self::LIMIT_DAY, $now );
		if ( null !== $job ) {
			self::delete_file( $job );
		}
		$job = array(
			'uid'        => Database::current()->uuid(),
			'status'     => self::PENDING,
			'created_at' => $now,
			'ready_at'   => null,
			'expires_at' => null,
			'file'       => null,
			'error'      => null,
		);
		update_user_option( $user, self::OPTION, $job );
		if ( false === wp_next_scheduled( CronHooks::PRIVACY_EXPORT, array( 0, $user ) ) ) {
			wp_schedule_single_event( $now, CronHooks::PRIVACY_EXPORT, array( 0, $user ) );
		}
		return array(
			'export'  => self::public_view( $job ),
			'created' => true,
		);
	}

	/**
	 * Read a job of the owner (REST `GET /me/data-exports/{uid}`).
	 *
	 * @param Actor    $actor Person.
	 * @param string   $uid   Job UID.
	 * @param int|null $now   Time (tests).
	 * @return array<string, mixed> Job with the exported data.
	 * @throws CourseException 404 unknown/foreign, 409 pending, 410 expired or failed.
	 */
	public static function get( Actor $actor, string $uid, ?int $now = null ): array {
		$now = $now ?? time();
		$job = $actor->user_id > 0 ? self::job( $actor->user_id ) : null;
		if ( null === $job || ! hash_equals( (string) $job['uid'], $uid ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown export.', 404 );
		}
		if ( self::PENDING === $job['status'] ) {
			throw new CourseException( 'ccap_export_not_ready', 'The export is being created.', 409, array( 'retry_after' => self::RETRY_SECS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: machine details for the entry point.
		}
		if ( self::FAILED === $job['status'] ) {
			throw new CourseException( 'ccap_export_failed', 'The export failed.', 410 );
		}
		if ( self::EXPIRED === $job['status'] || ( self::READY === $job['status'] && (int) $job['expires_at'] <= $now ) ) {
			self::expire( $actor->user_id, $job );
			throw new CourseException( 'ccap_export_expired', 'The download has expired.', 410 );
		}
		$path = self::path( $job );
		$data = null === $path || ! is_file( $path ) ? null : json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local private file.
		if ( ! is_array( $data ) ) {
			self::fail( $actor->user_id, $job, 'missing_file' );
			throw new CourseException( 'ccap_export_failed', 'The export failed.', 410 );
		}
		return self::public_view( $job ) + array( 'data' => $data );
	}

	/**
	 * Background step: build the file of a pending job.
	 *
	 * @param int      $user User.
	 * @param int|null $now  Time (tests).
	 * @return string New status, or `none` without a pending job.
	 * @throws \RuntimeException Internally only (caught: the job becomes `failed`).
	 */
	public static function generate( int $user, ?int $now = null ): string {
		$now     = $now ?? time();
		$job     = self::job( $user );
		$account = get_userdata( $user );
		if ( null === $job || self::PENDING !== $job['status'] ) {
			return 'none';
		}
		if ( ! $account instanceof \WP_User ) {
			self::fail( $user, $job, 'no_account' );
			return self::FAILED;
		}
		$file = null;
		try {
			$groups = array();
			for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
				$response = PrivacyService::export( $account->user_email, $page );
				foreach ( (array) ( $response['data'] ?? array() ) as $item ) {
					$group = (string) ( $item['group_id'] ?? '' );
					if ( ! isset( $groups[ $group ] ) ) {
						$groups[ $group ] = array(
							'label' => (string) ( $item['group_label'] ?? $group ),
							'items' => array(),
						);
					}
					$fields = array();
					foreach ( (array) ( $item['data'] ?? array() ) as $field ) {
						$fields[ (string) ( $field['name'] ?? '' ) ] = $field['value'] ?? null;
					}
					$groups[ $group ]['items'][] = $fields;
				}
				if ( ! empty( $response['done'] ) ) {
					break;
				}
			}
			$dir = ProtectedStorage::dir( 'packages' ) . '/' . self::SUBFOLDER;
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				throw new \RuntimeException( 'Export folder not writable.' );
			}
			$file = (string) $job['uid'] . '.json';
			$json = (string) wp_json_encode(
				array(
					'generated_at' => gmdate( 'c', $now ),
					'groups'       => $groups,
				)
			);
			if ( false === file_put_contents( $dir . '/' . $file, $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Private storage.
				throw new \RuntimeException( 'Export file not writable.' );
			}
		} catch ( \Throwable $e ) {
			$job['file'] = $file;
			self::fail( $user, $job, get_class( $e ) );
			return self::FAILED;
		}
		$job['status']     = self::READY;
		$job['file']       = $file;
		$job['ready_at']   = $now;
		$job['expires_at'] = $now + self::TTL;
		update_user_option( $user, self::OPTION, $job );
		return self::READY;
	}

	/**
	 * RT-36: remove export files older than the download period (hourly).
	 *
	 * @param int $now Time.
	 * @return int Deleted files.
	 */
	public static function purge_files( int $now ): int {
		$dir = ProtectedStorage::dir( 'packages' ) . '/' . self::SUBFOLDER;
		if ( ! is_dir( $dir ) ) {
			return 0;
		}
		$count = 0;
		foreach ( (array) glob( $dir . '/*.json' ) as $file ) {
			if ( is_string( $file ) && is_file( $file ) && (int) filemtime( $file ) + self::TTL < $now ) {
				wp_delete_file( $file );
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Stored job of a user (current site).
	 *
	 * @param int $user User.
	 * @return array<string, mixed>|null
	 */
	public static function job( int $user ): ?array {
		$job = get_user_option( self::OPTION, $user );
		return is_array( $job ) && isset( $job['uid'], $job['status'] ) ? $job : null;
	}

	/**
	 * Public fields of a job.
	 *
	 * @param array<string, mixed> $job Job.
	 * @return array<string, mixed>
	 */
	private static function public_view( array $job ): array {
		$time = static fn( $value ): ?string => null === $value ? null : gmdate( 'c', (int) $value );
		return array(
			'uid'        => (string) $job['uid'],
			'status'     => (string) $job['status'],
			'created_at' => $time( $job['created_at'] ?? null ),
			'expires_at' => $time( $job['expires_at'] ?? null ),
		);
	}

	/**
	 * Mark failed and remove a partial file.
	 *
	 * @param int                  $user User.
	 * @param array<string, mixed> $job  Job.
	 * @param string               $code Error class (no personal data).
	 * @return void
	 */
	private static function fail( int $user, array $job, string $code ): void {
		self::delete_file( $job );
		$job['status'] = self::FAILED;
		$job['file']   = null;
		$job['error']  = substr( $code, 0, 64 );
		update_user_option( $user, self::OPTION, $job );
	}

	/**
	 * Mark expired and delete the file.
	 *
	 * @param int                  $user User.
	 * @param array<string, mixed> $job  Job.
	 * @return void
	 */
	private static function expire( int $user, array $job ): void {
		self::delete_file( $job );
		$job['status'] = self::EXPIRED;
		$job['file']   = null;
		update_user_option( $user, self::OPTION, $job );
	}

	/**
	 * Delete the file of a job, if any.
	 *
	 * @param array<string, mixed> $job Job.
	 * @return void
	 */
	private static function delete_file( array $job ): void {
		$path = self::path( $job );
		if ( null !== $path && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Absolute path of the job file.
	 *
	 * @param array<string, mixed> $job Job.
	 * @return string|null
	 */
	public static function path( array $job ): ?string {
		$file = (string) ( $job['file'] ?? '' );
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}\.json$/', $file ) ) {
			return null;
		}
		return ProtectedStorage::dir( 'packages' ) . '/' . self::SUBFOLDER . '/' . $file;
	}
}
