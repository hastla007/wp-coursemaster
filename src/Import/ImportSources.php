<?php
/**
 * Package sources: upload, drop folder, HTTPS link (R0-10 §3.1, R0-17 T-30).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Every source ends in {@see ImportService::upload()} (private copy of the
 * package, batch `uploaded`), so the same validation follows.
 *
 * - Upload: limit = smallest of `upload_max_filesize`, `post_max_size`,
 *   `wp_max_upload_size()` and the package limit (200 MB, filter
 *   `course_captain_import_package_limit`); larger packages go through the
 *   drop folder or WP-CLI (E-178).
 * - Drop folder `<private>/packages-<secret>/import/`: base name only, the
 *   real path must lie directly in the folder, no links. A package taken
 *   over (validated) is deleted from the folder, a rejected one stays.
 * - Link: `https` and port 443 only, no user info, no internal hosts; every
 *   resolved address must be public; the connection is pinned to the
 *   checked address (DNS rebinding); at most 3 redirects, each checked
 *   like the first; size limit; optional expected SHA-256. The download
 *   lands in the private folder and is removed in every case.
 */
final class ImportSources {

	public const MAX_REDIRECTS  = 3;
	public const PACKAGE_LIMIT  = PackageValidator::MAX_ZIP;
	public const LIMIT_FILTER   = 'course_captain_import_package_limit';
	private const REDIRECT_CODE = array( 301, 302, 303, 307, 308 );

	/**
	 * Host resolver: host → list of IP addresses.
	 *
	 * @var \Closure(string): list<string>
	 */
	private \Closure $resolve;

	/**
	 * HTTP transport: (url, pinned ip, target file, byte limit) →
	 * `{code: int, location: string|null}`; the body goes to the file.
	 *
	 * @var \Closure(string, string, string, int): array{code: int, location: string|null}
	 */
	private \Closure $transport;

	/**
	 * Constructor.
	 *
	 * @param ImportService $service   Import service.
	 * @param \Closure|null $resolve   Resolver (tests); default DNS.
	 * @param \Closure|null $transport Transport (tests); default WP HTTP API with IP pinning.
	 */
	public function __construct( private ImportService $service, ?\Closure $resolve = null, ?\Closure $transport = null ) {
		$this->resolve   = $resolve ?? \Closure::fromCallable( array( self::class, 'dns' ) );
		$this->transport = $transport ?? \Closure::fromCallable( array( self::class, 'fetch' ) );
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( ImportService::current() );
	}

	/**
	 * Upload limit in bytes: the smallest positive value of PHP, WordPress
	 * and the package limit (GM-129).
	 *
	 * @param array<string, int|string> $values Overrides for `upload_max_filesize`, `post_max_size`, `wp`, `package` (tests).
	 * @return int
	 */
	public static function upload_limit( array $values = array() ): int {
		$candidates = array(
			wp_convert_hr_to_bytes( (string) ( $values['upload_max_filesize'] ?? ini_get( 'upload_max_filesize' ) ) ),
			wp_convert_hr_to_bytes( (string) ( $values['post_max_size'] ?? ini_get( 'post_max_size' ) ) ),
			(int) ( $values['wp'] ?? wp_max_upload_size() ),
			(int) ( $values['package'] ?? self::package_limit() ),
		);
		// 0 means "no limit" for the PHP settings.
		$candidates = array_filter( $candidates, static fn( int $v ): bool => $v > 0 );
		return array() === $candidates ? self::PACKAGE_LIMIT : (int) min( $candidates );
	}

	/**
	 * Package limit (filterable, never above the validator's V1 limit).
	 *
	 * @return int
	 */
	public static function package_limit(): int {
		/**
		 * Largest package for upload and link import in bytes (R0-10 §3.1).
		 *
		 * @since 1.0.0
		 * @param int $bytes Default 200 MB.
		 */
		$limit = (int) apply_filters( 'course_captain_import_package_limit', self::PACKAGE_LIMIT );
		return $limit > 0 ? min( $limit, self::PACKAGE_LIMIT ) : self::PACKAGE_LIMIT;
	}

	/**
	 * Uploaded package (admin upload).
	 *
	 * @param Actor  $actor Importer.
	 * @param string $file  Temporary upload file.
	 * @return int Batch ID.
	 * @throws CourseException 413 above the upload limit (nothing stored).
	 */
	public function from_upload( Actor $actor, string $file ): int {
		$limit = self::upload_limit();
		$size  = is_file( $file ) ? (int) filesize( $file ) : 0;
		if ( $size > $limit ) {
			throw new CourseException( 'ccap_package_too_large', 'The package is larger than the upload limit; use the drop folder or WP-CLI.', 413, array( 'limit' => $limit ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Data for the caller, not output.
		}
		return $this->service->upload( $actor, $file );
	}

	/**
	 * Drop folder for large packages (created on demand).
	 *
	 * @return string
	 * @throws CourseException When the folder cannot be created.
	 */
	public static function drop_dir(): string {
		$dir = ProtectedStorage::dir( 'packages' ) . '/import';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new CourseException( 'ccap_storage_unavailable', 'Drop folder not writable.', 500 );
		}
		return $dir;
	}

	/**
	 * Take over a package from the drop folder and validate it.
	 *
	 * @param Actor  $actor Importer.
	 * @param string $name  Base name of the ZIP in the drop folder.
	 * @return array{batch: int, status: string}
	 * @throws CourseException 422 for names outside the folder, 404 for missing files.
	 */
	public function from_drop( Actor $actor, string $name ): array {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_import_content' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to import.', 403 );
		}
		$dir = (string) realpath( self::drop_dir() );
		if ( '' === $name || wp_basename( $name ) !== $name || str_starts_with( $name, '.' ) || str_contains( $name, "\0" ) || ! str_ends_with( strtolower( $name ), '.zip' ) ) {
			throw new CourseException( 'ccap_validation_failed', 'name', 422 );
		}
		$path = $dir . '/' . $name;
		$real = realpath( $path );
		if ( false === $real || is_link( $path ) || dirname( $real ) !== $dir || ! is_file( $real ) ) {
			throw new CourseException( 'ccap_not_found', 'No such package in the drop folder.', 404 );
		}
		$batch  = $this->service->upload( $actor, $real, array( 'source' => 'drop' ) );
		$status = $this->service->validate( $actor, $batch )['status'];
		if ( 'validated' === $status ) {
			// Taken over: the private batch copy is the package from now on.
			wp_delete_file( $real );
		}
		return array(
			'batch'  => $batch,
			'status' => $status,
		);
	}

	/**
	 * Download a package from an HTTPS link (GM-130, SEC-029).
	 *
	 * @param Actor       $actor  Importer.
	 * @param string      $url    HTTPS URL.
	 * @param string|null $sha256 Expected SHA-256 (hex), optional.
	 * @return int Batch ID.
	 * @throws CourseException 403, 422 (`ccap_link_invalid`, `ccap_link_blocked`, `ccap_link_redirects`, `ccap_link_checksum`, `ccap_package_too_large`), 502 (`ccap_link_failed`).
	 */
	public function from_url( Actor $actor, string $url, ?string $sha256 = null ): int {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_import_content' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to import.', 403 );
		}
		if ( null !== $sha256 && 1 !== preg_match( '/^[0-9a-f]{64}$/i', $sha256 ) ) {
			throw new CourseException( 'ccap_validation_failed', 'sha256', 422 );
		}
		$dir = ProtectedStorage::dir( 'packages' ) . '/download';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new CourseException( 'ccap_storage_unavailable', 'Download folder not writable.', 500 );
		}
		$file  = $dir . '/link-' . wp_generate_uuid4() . '.part';
		$limit = self::package_limit();
		try {
			$current = $url;
			$hops    = 0;
			while ( true ) {
				$target   = self::target( $current, $this->resolve );
				$response = ( $this->transport )( $current, $target['ip'], $file, $limit );
				if ( in_array( (int) $response['code'], self::REDIRECT_CODE, true ) ) {
					++$hops;
					if ( $hops > self::MAX_REDIRECTS ) {
						throw new CourseException( 'ccap_link_redirects', 'Too many redirects.', 422 );
					}
					$current = self::absolute( (string) ( $response['location'] ?? '' ), $current );
					if ( is_file( $file ) ) {
						wp_delete_file( $file );
					}
					continue;
				}
				if ( 200 !== (int) $response['code'] || ! is_file( $file ) ) {
					throw new CourseException( 'ccap_link_failed', 'The package could not be downloaded.', 502, array( 'http_status' => (int) $response['code'] ) );
				}
				break;
			}
			if ( (int) filesize( $file ) > $limit ) {
				throw new CourseException( 'ccap_package_too_large', 'The package is larger than the package limit.', 422, array( 'limit' => $limit ) );
			}
			$hash = (string) hash_file( 'sha256', $file );
			if ( null !== $sha256 && ! hash_equals( strtolower( $sha256 ), $hash ) ) {
				throw new CourseException( 'ccap_link_checksum', 'Checksum does not match.', 422 );
			}
			$origin = (string) wp_parse_url( $url, PHP_URL_HOST );
			$batch  = $this->service->upload(
				$actor,
				$file,
				array(
					'source'      => 'link',
					'source_host' => $origin,
					'sha256'      => $hash,
					'redirects'   => $hops,
				)
			);
			AuditLog::write(
				Database::current(),
				$actor,
				'import_link_downloaded',
				'import_batch',
				(string) $batch,
				null,
				array(
					'host'     => $origin,
					'sha256'   => $hash,
					'verified' => null !== $sha256,
				)
			);
			return $batch;
		} finally {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Check a link and resolve its host: https, port 443, no user info,
	 * no internal host names, every resolved address public.
	 *
	 * @param string   $url     URL.
	 * @param callable $resolve Resolver host → list<string>.
	 * @return array{host: string, ip: string}
	 * @throws CourseException 422 `ccap_link_invalid` / `ccap_link_blocked`.
	 */
	public static function target( string $url, callable $resolve ): array {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			throw new CourseException( 'ccap_link_invalid', 'Only https links are allowed.', 422 );
		}
		if ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) {
			throw new CourseException( 'ccap_link_invalid', 'Only port 443 is allowed.', 422 );
		}
		$host = rtrim( strtolower( (string) $parts['host'] ), '.' );
		$host = trim( $host, '[]' );
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$ips = array( $host );
		} elseif ( '' === $host || 'localhost' === $host || str_ends_with( $host, '.localhost' ) || 1 === preg_match( '/^[0-9.]+$|^0x|\.0x/i', $host ) || 1 !== preg_match( '/^[a-z0-9.-]+$/', $host ) ) {
			// Numeric or obfuscated hosts (e.g. 2130706433, 0x7f.1) are never resolved.
			throw new CourseException( 'ccap_link_blocked', 'Host not allowed.', 422 );
		} else {
			$ips = array_values( array_map( 'strval', (array) $resolve( $host ) ) );
		}
		if ( array() === $ips ) {
			throw new CourseException( 'ccap_link_failed', 'Host not found.', 502 );
		}
		foreach ( $ips as $ip ) {
			if ( ! self::public_ip( $ip ) ) {
				throw new CourseException( 'ccap_link_blocked', 'Internal address not allowed.', 422 );
			}
		}
		return array(
			'host' => $host,
			'ip'   => $ips[0],
		);
	}

	/**
	 * Whether an address is publicly routable (no private, loopback,
	 * link-local, reserved, CGNAT, multicast, mapped or documentation ranges).
	 *
	 * @param string $ip Address.
	 * @return bool
	 */
	public static function public_ip( string $ip ): bool {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}
		$bin = (string) inet_pton( $ip );
		if ( 4 === strlen( $bin ) ) {
			$blocked = array( '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/3' );
		} else {
			$blocked = array( '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '100::/64', '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10', 'ff00::/8' );
		}
		foreach ( $blocked as $cidr ) {
			list( $net, $bits ) = explode( '/', $cidr );
			$net_bin            = (string) inet_pton( $net );
			if ( strlen( $net_bin ) !== strlen( $bin ) ) {
				continue;
			}
			$bytes = intdiv( (int) $bits, 8 );
			$rest  = (int) $bits % 8;
			if ( substr( $bin, 0, $bytes ) !== substr( $net_bin, 0, $bytes ) ) {
				continue;
			}
			if ( 0 === $rest || ( ( ord( $bin[ $bytes ] ) ^ ord( $net_bin[ $bytes ] ) ) & ( 0xff << ( 8 - $rest ) ) & 0xff ) === 0 ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Absolute redirect target.
	 *
	 * @param string $location Location header.
	 * @param string $base     Current URL.
	 * @return string
	 * @throws CourseException Without a location.
	 */
	private static function absolute( string $location, string $base ): string {
		if ( '' === $location ) {
			throw new CourseException( 'ccap_link_failed', 'Redirect without target.', 502 );
		}
		if ( 1 === preg_match( '#^[a-z][a-z0-9+.-]*://#i', $location ) ) {
			return $location;
		}
		$parts  = (array) wp_parse_url( $base );
		$origin = 'https://' . (string) ( $parts['host'] ?? '' );
		if ( str_starts_with( $location, '//' ) ) {
			return 'https:' . $location;
		}
		if ( str_starts_with( $location, '/' ) ) {
			return $origin . $location;
		}
		$path = (string) ( $parts['path'] ?? '/' );
		return $origin . substr( $path, 0, (int) strrpos( $path, '/' ) + 1 ) . $location;
	}

	/**
	 * Default resolver (A and AAAA records).
	 *
	 * @param string $host Host.
	 * @return list<string>
	 */
	private static function dns( string $host ): array {
		$ips = gethostbynamel( $host );
		$ips = false === $ips ? array() : $ips;
		$six = function_exists( 'dns_get_record' ) ? @dns_get_record( $host, DNS_AAAA ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Lookup failures mean "no address".
		foreach ( is_array( $six ) ? $six : array() as $record ) {
			if ( isset( $record['ipv6'] ) ) {
				$ips[] = (string) $record['ipv6'];
			}
		}
		return array_values( array_unique( $ips ) );
	}

	/**
	 * Default transport: `wp_safe_remote_get` without automatic redirects,
	 * streamed into the file, the connection pinned to the checked address
	 * (cURL `CURLOPT_RESOLVE`, https only).
	 *
	 * @param string $url   URL.
	 * @param string $ip    Checked address.
	 * @param string $file  Target file.
	 * @param int    $limit Byte limit.
	 * @return array{code: int, location: string|null}
	 * @throws CourseException 502 without cURL or on transport errors.
	 */
	private static function fetch( string $url, string $ip, string $file, int $limit ): array {
		if ( ! function_exists( 'curl_init' ) ) {
			// Without cURL the connection cannot be pinned (DNS rebinding): refuse.
			throw new CourseException( 'ccap_link_failed', 'Link import needs cURL.', 502 );
		}
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$pin  = static function ( $handle, $args, $request_url ) use ( $url, $host, $ip ): void {
			unset( $args );
			if ( $request_url === $url ) {
				// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt -- Pinning needs the handle of the WP HTTP API.
				curl_setopt( $handle, CURLOPT_RESOLVE, array( $host . ':443:' . ( str_contains( $ip, ':' ) ? '[' . $ip . ']' : $ip ) ) );
				curl_setopt( $handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS );
				// phpcs:enable
			}
		};
		add_action( 'http_api_curl', $pin, 10, 3 );
		try {
			$response = wp_safe_remote_get(
				$url,
				array(
					'redirection'         => 0,
					'timeout'             => 60,
					'stream'              => true,
					'filename'            => $file,
					'limit_response_size' => $limit + 1,
				)
			);
		} finally {
			remove_action( 'http_api_curl', $pin, 10 );
		}
		if ( is_wp_error( $response ) ) {
			throw new CourseException( 'ccap_link_failed', 'The package could not be downloaded.', 502 );
		}
		$location = wp_remote_retrieve_header( $response, 'location' );
		return array(
			'code'     => (int) wp_remote_retrieve_response_code( $response ),
			'location' => is_array( $location ) ? (string) reset( $location ) : ( '' === $location ? null : $location ),
		);
	}
}
