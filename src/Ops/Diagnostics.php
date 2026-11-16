<?php
/**
 * Redacted diagnostics package (R0-13 §2.2; R0-34 §5; SUP-017, SUP-018, SUP-021).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Allowlist, not blocklist: only the sections and keys below leave the
 * site. Free text (error messages) additionally runs through the scrubber.
 * Pseudonyms are HMAC-SHA-256 with a random key per package that is never
 * stored: comparable inside one package, not across packages. Never in the
 * package: names, emails, logins, user IDs, IP addresses, answers,
 * payloads, contents, audit entries, secrets, tokens, absolute paths,
 * folder secrets, the domain in plain text, settings values.
 */
final class Diagnostics {

	public const FORMAT          = '1.0';
	public const REDACTION_RULES = '1';
	public const MAX_JOBS        = 200;
	public const IMPORT_DAYS     = 30;
	public const MESSAGE_LENGTH  = 300;

	/**
	 * Per-package HMAC key.
	 *
	 * @var string
	 */
	private string $salt;

	/**
	 * Constructor.
	 *
	 * @param Database $db Database.
	 */
	public function __construct( private Database $db ) {
		$this->salt = random_bytes( 32 );
	}

	/**
	 * Instance for the current site (new pseudonym key).
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current() );
	}

	/**
	 * Build the package.
	 *
	 * @param Actor         $actor    Actor (`ccap_view_system_status`; CLI allowed).
	 * @param Migrator|null $migrator Migrator (tests).
	 * @return array<string, mixed>
	 * @throws CourseException Without the capability.
	 */
	public function build( Actor $actor, ?Migrator $migrator = null ): array {
		OpsStatus::assert_can_view( $actor );
		$report   = OpsStatus::report( $actor, $migrator );
		$sections = array(
			'versions'      => $this->versions( $report['migration'] ),
			'environment'   => $this->environment(),
			'plugins'       => $this->plugins(),
			'health'        => $this->health( $report['checks'] ),
			'jobs'          => $this->jobs(),
			'imports'       => $this->imports(),
			'outbox'        => $report['outbox'],
			'notifications' => $report['notifications'],
			'integrations'  => $this->integrations( $report['integrations'] ),
			'counts'        => $this->counts(),
			'schema'        => $this->schema( $report['migration'] ),
		);
		$sums     = array();
		foreach ( $sections as $name => $section ) {
			$sums[ $name ] = hash( 'sha256', (string) wp_json_encode( $section ) );
		}
		return array(
			'manifest' => array(
				'format'          => self::FORMAT,
				'generated_at'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'redaction_rules' => self::REDACTION_RULES,
				'site'            => $this->pseudonym( (string) home_url() ),
				'sections'        => $sums,
			),
		) + $sections;
	}

	/**
	 * Build, encode and audit (`diagnostics_exported`, scope only).
	 *
	 * @param Actor         $actor    Actor.
	 * @param Migrator|null $migrator Migrator (tests).
	 * @return array{filename: string, body: string, sha256: string, bytes: int}
	 * @throws CourseException Without the capability.
	 */
	public function export( Actor $actor, ?Migrator $migrator = null ): array {
		$body   = (string) wp_json_encode( $this->build( $actor, $migrator ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$sha    = hash( 'sha256', $body );
		$bytes  = strlen( $body );
		$result = array(
			'filename' => 'course-captain-diagnose-' . gmdate( 'Ymd\THis\Z' ) . '.json',
			'body'     => $body,
			'sha256'   => $sha,
			'bytes'    => $bytes,
		);
		AuditLog::write(
			$this->db,
			$actor,
			'diagnostics_exported',
			'diagnostics',
			substr( $sha, 0, 16 ),
			null,
			array(
				'bytes'  => $bytes,
				'sha256' => $sha,
				'format' => self::FORMAT,
			)
		);
		return $result;
	}

	/**
	 * Safe export writer for the CLI: new file only (`x`), mode 0600,
	 * never inside the WordPress directory.
	 *
	 * @param string $path File.
	 * @param string $body Content.
	 * @return void
	 * @throws CourseException When the target is not allowed or exists.
	 */
	public static function write_file( string $path, string $body ): void {
		$dir = realpath( dirname( $path ) );
		$abs = realpath( ABSPATH );
		if ( false === $dir || ( false !== $abs && str_starts_with( $dir . '/', rtrim( $abs, '/' ) . '/' ) ) ) {
			throw new CourseException( 'ccap_export_target_invalid', 'Choose a writable folder outside the WordPress directory.', 422 );
		}
		$handle = @fopen( $path, 'x' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Exclusive create; failure handled.
		if ( false === $handle ) {
			throw new CourseException( 'ccap_export_target_invalid', 'The file exists or cannot be created.', 422 );
		}
		chmod( $path, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- CLI writer.
		fwrite( $handle, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI writer.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CLI writer.
	}

	/**
	 * Scrubber for free text (second line of defence, R0-13 §2.2).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function scrub( string $text ): string {
		// Emails first: a site domain inside an address must not leave its local part behind.
		$text = (string) preg_replace( '/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+/', '<email>', $text );
		$host = (string) wp_parse_url( (string) home_url(), PHP_URL_HOST );
		if ( '' !== $host ) {
			$text = str_ireplace( $host, '<site>', $text );
		}
		foreach ( array_filter( array( defined( 'WP_CONTENT_DIR' ) ? (string) WP_CONTENT_DIR : '', (string) ABSPATH ) ) as $root ) {
			$text = str_replace( rtrim( $root, '/' ), '<abspath>', $text );
		}
		$rules = array(
			'/\bBearer\s+\S+/i'                          => 'Bearer <token>',
			'/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*/' => '<token>',
			'/\b(protected|packages)-[A-Za-z0-9]+/'      => '$1-<…>',
			'/(https?:\/\/[^\s?"\']+)\?[^\s"\']*/i'      => '$1?<redacted>',
			'/\?[A-Za-z0-9_\[\]-]+=[^\s"\']*/'           => '?<redacted>',
			'/\b(?:[0-9a-f]{1,4}:){7}[0-9a-f]{1,4}\b/i'  => '<ip>',
			'/\b(?:[0-9a-f]{1,4}:)+:(?:[0-9a-f]{1,4}:)*[0-9a-f]{1,4}\b/i' => '<ip>',
			'/\b(?:\d{1,3}\.){3}\d{1,3}\b/'              => '<ip>',
			'/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){3,7}(?:\s?[A-Z0-9]{1,4})?\b/' => '<iban>',
			'/\b[a-f0-9]{32,}\b/i'                       => '<token>',
			'/[A-Za-z0-9+\/=_]{32,}/'                    => '<token>',
			'/(?<![\w:\/<>.])\/(?:[\w.@-]+\/)+[\w.@-]*/' => '<abspath>/…',
			'/\b[A-Za-z]:\\\\(?:[^\\\\\s]+\\\\)*[^\\\\\s]*/' => '<abspath>\\…',
			'/\b(user|order|customer)(_id)?([\s:#=-]*)\d+\b/i' => '$1$2$3<id>',
		);
		return (string) preg_replace( array_keys( $rules ), array_values( $rules ), $text );
	}

	/**
	 * Pseudonym inside this package.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public function pseudonym( string $value ): string {
		return 'h:' . substr( hash_hmac( 'sha256', $value, $this->salt ), 0, 16 );
	}

	/**
	 * Versions.
	 *
	 * @param array<string, mixed> $migration Migration report.
	 * @return array<string, string|null>
	 */
	private function versions( array $migration ): array {
		$server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['SERVER_SOFTWARE'] ) ) : '';
		return array(
			'course_captain' => COURSE_CAPTAIN_VERSION,
			'contract'       => defined( 'COURSE_CAPTAIN_CONTRACT_VERSION' ) ? (string) COURSE_CAPTAIN_CONTRACT_VERSION : null,
			'schema'         => null === $migration['stored_version'] ? null : (string) $migration['stored_version'],
			'schema_code'    => (string) $migration['code_version'],
			'php'            => PHP_VERSION,
			'wordpress'      => (string) get_bloginfo( 'version' ),
			'database'       => (string) $this->db->wpdb()->db_server_info(),
			'webserver'      => '' === $server ? null : (string) strtok( $server, ' ' ),
		);
	}

	/**
	 * Environment (no paths, domain only as pseudonym).
	 *
	 * @return array<string, mixed>
	 */
	private function environment(): array {
		return array(
			'environment_type'   => wp_get_environment_type(),
			'multisite'          => is_multisite(),
			'locale'             => get_locale(),
			'timezone'           => wp_timezone_string(),
			'memory_limit'       => (string) ini_get( 'memory_limit' ),
			'max_execution_time' => (string) ini_get( 'max_execution_time' ),
			'upload_max'         => (string) ini_get( 'upload_max_filesize' ),
			'post_max'           => (string) ini_get( 'post_max_size' ),
			'object_cache'       => wp_using_ext_object_cache() ? 'persistent' : 'none',
			'disable_wp_cron'    => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'https'              => 'https' === wp_parse_url( (string) home_url(), PHP_URL_SCHEME ),
			'private_dir_custom' => defined( 'COURSE_CAPTAIN_PRIVATE_DIR' ),
			'secret_key'         => defined( 'COURSE_CAPTAIN_SECRET_KEY' ) ? '<konstante>' : '<leer>',
			'home'               => $this->pseudonym( (string) home_url() ),
		);
	}

	/**
	 * Active plugins and theme (slug, version), must-use plugin file names.
	 *
	 * @return array<string, mixed>
	 */
	private function plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = array();
		foreach ( get_plugins() as $file => $data ) {
			if ( is_plugin_active( (string) $file ) ) {
				$active[] = array(
					'slug'    => str_contains( (string) $file, '/' ) ? dirname( (string) $file ) : basename( (string) $file, '.php' ),
					'version' => (string) ( $data['Version'] ?? '' ),
					'network' => is_multisite() && is_plugin_active_for_network( (string) $file ),
				);
			}
		}
		$theme = wp_get_theme();
		return array(
			'active'    => $active,
			'must_use'  => array_values( array_map( 'strval', array_keys( get_mu_plugins() ) ) ),
			'theme'     => $theme->get_stylesheet(),
			'theme_ver' => (string) $theme->get( 'Version' ),
		);
	}

	/**
	 * Health checks (ids, status, scrubbed text).
	 *
	 * @param list<array{id: string, status: string, label: string, hint: string, ids: list<string>}> $checks Checks.
	 * @return list<array<string, mixed>>
	 */
	private function health( array $checks ): array {
		return array_map(
			static fn( array $c ): array => array(
				'id'     => $c['id'],
				'status' => $c['status'],
				'text'   => self::scrub( $c['label'] ),
				'count'  => count( $c['ids'] ),
				'ids'    => array_map( array( self::class, 'scrub' ), $c['ids'] ),
			),
			$checks
		);
	}

	/**
	 * Unfinished internal deliveries (no payload, no user reference).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function jobs(): array {
		$deliveries = $this->db->table( 'ccap_outbox_delivery' );
		$events     = $this->db->table( 'ccap_outbox_event' );
		$rows       = $this->db->rows( $this->db->prepare( "SELECT d.id, d.subscriber_id, d.status, d.attempts, d.manual_retries, d.next_attempt_at, d.last_error_class, d.last_error, d.created_at, e.event_type, e.object_type, e.correlation_id FROM `{$deliveries}` d JOIN `{$events}` e ON e.event_uuid = d.event_uuid WHERE d.status NOT IN ('delivered', 'skipped') ORDER BY d.id DESC LIMIT %d", self::MAX_JOBS ) );
		return array_map(
			fn( array $r ): array => array(
				'id'             => 'delivery:' . (int) $r['id'],
				'subscriber'     => (string) $r['subscriber_id'],
				'event_type'     => (string) $r['event_type'],
				'object_type'    => (string) $r['object_type'],
				'status'         => (string) $r['status'],
				'attempts'       => (int) $r['attempts'],
				'manual_retries' => (int) $r['manual_retries'],
				'next_attempt'   => null === $r['next_attempt_at'] ? null : (string) $r['next_attempt_at'],
				'error_class'    => null === $r['last_error_class'] ? null : (string) $r['last_error_class'],
				'message'        => self::message( (string) ( $r['last_error'] ?? '' ) ),
				'correlation_id' => null === $r['correlation_id'] ? null : $this->pseudonym( (string) $r['correlation_id'] ),
				'created_at'     => (string) $r['created_at'],
			),
			$rows
		);
	}

	/**
	 * Import batches of the last 30 days (status, heartbeat age, error code).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function imports(): array {
		$rows = $this->db->rows( $this->db->prepare( 'SELECT id, status, heartbeat_at, error, created_at FROM `' . $this->db->table( 'ccap_import_batch' ) . '` WHERE created_at > %s ORDER BY id DESC LIMIT 100', $this->db->now( -self::IMPORT_DAYS * DAY_IN_SECONDS ) ) );
		$now  = $this->db->time();
		return array_map(
			static fn( array $r ): array => array(
				'id'            => 'import:' . (int) $r['id'],
				'status'        => (string) $r['status'],
				'heartbeat_age' => null === $r['heartbeat_at'] ? null : max( 0, $now - (int) strtotime( $r['heartbeat_at'] . ' UTC' ) ),
				'message'       => self::message( (string) ( $r['error'] ?? '' ) ),
				'created_at'    => (string) $r['created_at'],
			),
			$rows
		);
	}

	/**
	 * Integration records (L fields only).
	 *
	 * @param list<array<string, mixed>> $records Records.
	 * @return list<array<string, mixed>>
	 */
	private function integrations( array $records ): array {
		return array_map(
			static fn( array $r ): array => array(
				'provider_id'  => $r['provider_id'],
				'state'        => $r['state'],
				'state_reason' => $r['state_reason'],
				'version'      => $r['partner']['version'] ?? null,
				'contract'     => $r['partner']['contract_version'] ?? null,
			),
			$records
		);
	}

	/**
	 * Object counts; learners only as size class.
	 *
	 * @return array<string, int|string>
	 */
	private function counts(): array {
		$grants = $this->db->table( 'ccap_grant' );
		return array(
			'courses'       => (int) ( wp_count_posts( 'ccap_course' )->publish ?? 0 ),
			'active_grants' => (int) $this->db->scalar( "SELECT COUNT(*) FROM `{$grants}` WHERE status = 'active'" ),
			'learners'      => self::size_class( (int) $this->db->scalar( "SELECT COUNT(DISTINCT user_id) FROM `{$grants}` WHERE user_id IS NOT NULL" ) ),
			'open_attempts' => (int) $this->db->scalar( 'SELECT COUNT(*) FROM `' . $this->db->table( 'ccap_exam_session' ) . "` WHERE status = 'in_progress'" ),
		);
	}

	/**
	 * Schema and migration history (messages scrubbed).
	 *
	 * @param array<string, mixed> $migration Migration report.
	 * @return array<string, mixed>
	 */
	private function schema( array $migration ): array {
		$steps = array();
		foreach ( (array) $migration['steps'] as $id => $step ) {
			$steps[ (string) $id ] = array(
				'status'   => $step['status'],
				'attempts' => $step['attempts'],
				'crashes'  => $step['crash_count'],
				'blocked'  => $step['blocked_reason'],
				'message'  => self::message( (string) ( $step['last_error'] ?? '' ) ),
			);
		}
		$fk = get_option( Migrator::FK_ISSUES );
		return array(
			'state'     => $migration['state'],
			'steps'     => $steps,
			'fk_issues' => is_array( $fk ) ? count( $fk ) : 0,
		);
	}

	/**
	 * Shortened, scrubbed message or null.
	 *
	 * @param string $text Text.
	 * @return string|null
	 */
	private static function message( string $text ): ?string {
		$text = trim( $text );
		return '' === $text ? null : mb_substr( self::scrub( $text ), 0, self::MESSAGE_LENGTH );
	}

	/**
	 * Size class instead of an exact number.
	 *
	 * @param int $n Number.
	 * @return string
	 */
	private static function size_class( int $n ): string {
		return match ( true ) {
			$n < 100   => '<100',
			$n < 1000  => '<1000',
			$n < 10000 => '<10000',
			default    => '>=10000',
		};
	}
}
