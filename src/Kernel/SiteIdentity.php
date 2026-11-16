<?php
/**
 * Site identity and restore/copy detection (R0-16 §2.3, E-85; R0-17 §5.5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Option `course_captain_site_identity` stores hashes of the raw `home`
 * option, the resolved private base directory, `DB_NAME|<blog prefix>` and
 * `wp_get_environment_type()`. A deviation puts the site into the identity
 * hold mode: Course Captain keeps running (cron, learning, reconciliation),
 * but outbound mails are held (`OutboundMode::is_held()`) until an
 * administrator confirms one of three ways: same place, moved, copy.
 * "Copy" keeps the hold permanently (staging copy next to the original).
 *
 * Restore at the same place does not change the identity. It is detected
 * through the state epoch (R0-17 §5.5): `course_captain_state_epoch` grows
 * with every irreversible outbound effect (mail sent, personal data erased)
 * and is mirrored into a head file in `COURSE_CAPTAIN_PRIVACY_JOURNAL_DIR`
 * (outside the backup scope). Journal epoch > DB epoch → the database was
 * rolled back → hold. Without the constant only identity changes are detected.
 *
 * The detection is persisted on first sight (status `held`, `detected_at`);
 * a later return to the old values does not lift the hold. A missing option
 * (fresh install, upgrade from a version without identity) is recorded
 * silently.
 */
final class SiteIdentity {

	public const OPTION       = 'course_captain_site_identity';
	public const EPOCH_OPTION = 'course_captain_state_epoch';
	public const VERSION      = 1;

	public const STATUS_OK   = 'ok';
	public const STATUS_HELD = 'held';
	public const STATUS_COPY = 'copy';

	public const CHOICE_SAME_PLACE = 'same_place';
	public const CHOICE_MOVED      = 'moved';
	public const CHOICE_COPY       = 'copy';
	public const CHOICES           = array( self::CHOICE_SAME_PLACE, self::CHOICE_MOVED, self::CHOICE_COPY );

	/** Identity components (R0-16 §2.3). */
	public const COMPONENTS = array( 'home', 'private', 'database', 'environment' );

	/** Pseudo component: journal epoch ahead of the database (restore at the same place). */
	public const REASON_EPOCH = 'epoch';

	/**
	 * Test seam: journal directory (null = constant).
	 *
	 * @var string|null
	 */
	private static ?string $journal_dir = null;

	/**
	 * Test seam: environment type (null = `wp_get_environment_type()`).
	 *
	 * @var string|null
	 */
	private static ?string $environment = null;

	/**
	 * Override the journal directory (tests; `null` resets to the constant).
	 *
	 * @internal
	 * @param string|null $dir Directory or null.
	 * @return void
	 */
	public static function with_journal_dir( ?string $dir ): void {
		self::$journal_dir = $dir;
	}

	/**
	 * Override the environment type (tests; `null` resets).
	 *
	 * @internal
	 * @param string|null $environment Environment type or null.
	 * @return void
	 */
	public static function with_environment( ?string $environment ): void {
		self::$environment = $environment;
	}

	/**
	 * Current identity hashes of this site.
	 *
	 * @return array<string, string>
	 */
	public static function components(): array {
		global $wpdb;
		try {
			$private = ProtectedStorage::base();
		} catch ( \Throwable $e ) {
			$private = '';
		}
		return array(
			// Raw option, not home_url(): language plugins filter home_url() per request.
			'home'        => self::hash( untrailingslashit( (string) get_option( 'home' ) ) ),
			'private'     => self::hash( wp_normalize_path( $private ) ),
			'database'    => self::hash( ( defined( 'DB_NAME' ) ? (string) constant( 'DB_NAME' ) : '' ) . '|' . $wpdb->get_blog_prefix() ),
			'environment' => self::hash( self::$environment ?? wp_get_environment_type() ),
		);
	}

	/**
	 * Pure decision (unit tests).
	 *
	 * @param array<string, string> $stored        Stored components.
	 * @param string                $status        Stored status.
	 * @param array<string, string> $current       Current components.
	 * @param int                   $db_epoch      Epoch in the database.
	 * @param int|null              $journal_epoch Epoch in the journal head, null when unknown.
	 * @return array{held: bool, changed: list<string>}
	 */
	public static function decide( array $stored, string $status, array $current, int $db_epoch, ?int $journal_epoch ): array {
		$changed = array();
		foreach ( self::COMPONENTS as $component ) {
			if ( ( $stored[ $component ] ?? '' ) !== ( $current[ $component ] ?? '' ) ) {
				$changed[] = $component;
			}
		}
		if ( null !== $journal_epoch && $journal_epoch > $db_epoch ) {
			$changed[] = self::REASON_EPOCH;
		}
		return array(
			'held'    => self::STATUS_OK !== $status || array() !== $changed,
			'changed' => $changed,
		);
	}

	/**
	 * Read-only evaluation (admin notice, Site Health, CLI status).
	 *
	 * @return array{state: string, held: bool, changed: list<string>, detected_at: int}
	 */
	public static function evaluate(): array {
		$stored = self::stored();
		if ( null === $stored ) {
			return array(
				'state'       => 'unrecorded',
				'held'        => false,
				'changed'     => array(),
				'detected_at' => 0,
			);
		}
		$decision = self::decide( $stored['components'], $stored['status'], self::components(), self::db_epoch(), self::journal_epoch() );
		$changed  = array_values( array_unique( array_merge( $stored['changed'], $decision['changed'] ) ) );
		$state    = self::STATUS_COPY === $stored['status'] ? self::STATUS_COPY : ( $decision['held'] ? self::STATUS_HELD : self::STATUS_OK );
		return array(
			'state'       => $state,
			'held'        => $decision['held'],
			'changed'     => $changed,
			'detected_at' => $stored['detected_at'] > 0 ? $stored['detected_at'] : ( $decision['held'] ? time() : 0 ),
		);
	}

	/**
	 * Evaluate and persist a new detection (hourly, before sending, admin notice).
	 *
	 * @return array{state: string, held: bool, changed: list<string>, detected_at: int}
	 */
	public static function check(): array {
		$stored = self::stored();
		if ( null === $stored ) {
			self::record( self::STATUS_OK, '' );
			return self::evaluate();
		}
		$result = self::evaluate();
		if ( ! $result['held'] ) {
			return $result;
		}
		$status = self::STATUS_COPY === $stored['status'] ? self::STATUS_COPY : self::STATUS_HELD;
		$fresh  = self::STATUS_HELD === $status && 0 === $stored['detected_at'];
		if ( $status !== $stored['status'] || $result['changed'] !== $stored['changed'] || $fresh ) {
			$stored['status']      = $status;
			$stored['changed']     = $result['changed'];
			$stored['detected_at'] = $result['detected_at'];
			update_option( self::OPTION, $stored, false );
		}
		return $result;
	}

	/**
	 * Whether outbound effects are held on this site (E-85).
	 *
	 * @return bool
	 */
	public static function is_held(): bool {
		return self::check()['held'];
	}

	/**
	 * Administrator confirmation after restore, move or copy (R0-16 §2.3 step 7).
	 *
	 * Rewrites the identity with the current values and synchronizes the
	 * epoch. `same_place` and `moved` lift the hold; `copy` keeps it for good.
	 * Held mails stay `held` (E-88: the admin releases or discards them; held
	 * mails expire to `discarded` after 30 days).
	 *
	 * @param Actor  $actor  Site administrator or WP-CLI.
	 * @param string $choice One of the CHOICE_* constants.
	 * @return array{state: string, held: bool, changed: list<string>, detected_at: int}
	 * @throws CourseException `ccap_forbidden`, `ccap_invalid_param`.
	 */
	public static function confirm( Actor $actor, string $choice ): array {
		$allowed = Actor::SYSTEM_CLI === $actor->type || ( Actor::USER === $actor->type && $actor->user_id > 0 && user_can( $actor->user_id, 'manage_options' ) );
		if ( ! $allowed ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to confirm the site identity.', 403 );
		}
		if ( ! in_array( $choice, self::CHOICES, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown confirmation.', 400 );
		}
		$before = self::check();
		self::record( self::CHOICE_COPY === $choice ? self::STATUS_COPY : self::STATUS_OK, $choice, $actor->user_id );
		$epoch = max( self::db_epoch(), (int) self::journal_epoch() );
		update_option( self::EPOCH_OPTION, $epoch, false );
		self::write_journal( $epoch );
		AuditLog::write(
			Database::current(),
			$actor,
			'site_identity_confirmed',
			'site',
			(string) get_current_blog_id(),
			null,
			array(
				'choice'   => $choice,
				'previous' => $before['state'],
				'changed'  => $before['changed'],
			)
		);
		return self::evaluate();
	}

	/**
	 * Increase the state epoch after an irreversible outbound effect.
	 *
	 * A pending detection is persisted first, so the bump never hides it.
	 *
	 * @return int New epoch.
	 */
	public static function bump_epoch(): int {
		self::check();
		$epoch = max( self::db_epoch(), (int) self::journal_epoch() ) + 1;
		update_option( self::EPOCH_OPTION, $epoch, false );
		self::write_journal( $epoch );
		return $epoch;
	}

	/**
	 * Epoch stored in the database.
	 *
	 * @return int
	 */
	public static function db_epoch(): int {
		return max( 0, (int) get_option( self::EPOCH_OPTION, 0 ) );
	}

	/**
	 * Epoch in the journal head of this site, null without journal.
	 *
	 * @return int|null
	 */
	public static function journal_epoch(): ?int {
		$file = self::journal_file();
		if ( '' === $file || ! is_readable( $file ) ) {
			return null;
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local head file.
		return is_array( $data ) && isset( $data['epoch'] ) && is_int( $data['epoch'] ) ? $data['epoch'] : null;
	}

	/**
	 * Head file of this site (empty without configured journal directory).
	 *
	 * @return string
	 */
	public static function journal_file(): string {
		global $wpdb;
		$dir = self::$journal_dir ?? ( defined( 'COURSE_CAPTAIN_PRIVACY_JOURNAL_DIR' ) ? (string) constant( 'COURSE_CAPTAIN_PRIVACY_JOURNAL_DIR' ) : '' );
		if ( '' === $dir ) {
			return '';
		}
		$key = substr( hash( 'sha256', ( defined( 'DB_NAME' ) ? (string) constant( 'DB_NAME' ) : '' ) . '|' . $wpdb->get_blog_prefix() ), 0, 16 );
		return untrailingslashit( $dir ) . '/course-captain-epoch-' . $key . '.json';
	}

	/**
	 * Remove the own journal head file (confirmed uninstall).
	 *
	 * @return void
	 */
	public static function delete_journal(): void {
		$file = self::journal_file();
		if ( '' !== $file && is_file( $file ) ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * Translated name of a component.
	 *
	 * @param string $component Component.
	 * @return string
	 */
	public static function label( string $component ): string {
		switch ( $component ) {
			case 'home':
				return __( 'site address', 'course-captain' );
			case 'private':
				return __( 'private file folder', 'course-captain' );
			case 'database':
				return __( 'database', 'course-captain' );
			case 'environment':
				return __( 'environment type', 'course-captain' );
			case self::REASON_EPOCH:
				return __( 'database restored from an older state', 'course-captain' );
		}
		return $component;
	}

	/**
	 * Stored identity, normalized.
	 *
	 * @return array{v: int, components: array<string, string>, status: string, changed: list<string>, detected_at: int, choice: string, confirmed_at: int, confirmed_by: int}|null
	 */
	private static function stored(): ?array {
		$raw = get_option( self::OPTION );
		if ( ! is_array( $raw ) || ! isset( $raw['components'] ) || ! is_array( $raw['components'] ) ) {
			return null;
		}
		$status = in_array( $raw['status'] ?? '', array( self::STATUS_OK, self::STATUS_HELD, self::STATUS_COPY ), true ) ? (string) $raw['status'] : self::STATUS_HELD;
		return array(
			'v'            => (int) ( $raw['v'] ?? self::VERSION ),
			'components'   => array_map( 'strval', $raw['components'] ),
			'status'       => $status,
			'changed'      => array_values( array_map( 'strval', (array) ( $raw['changed'] ?? array() ) ) ),
			'detected_at'  => (int) ( $raw['detected_at'] ?? 0 ),
			'choice'       => (string) ( $raw['choice'] ?? '' ),
			'confirmed_at' => (int) ( $raw['confirmed_at'] ?? 0 ),
			'confirmed_by' => (int) ( $raw['confirmed_by'] ?? 0 ),
		);
	}

	/**
	 * Write the identity with the current components.
	 *
	 * @param string $status Status.
	 * @param string $choice Confirmation choice ('' when recorded silently).
	 * @param int    $user   Confirming user (0 = system).
	 * @return void
	 */
	private static function record( string $status, string $choice, int $user = 0 ): void {
		update_option(
			self::OPTION,
			array(
				'v'            => self::VERSION,
				'components'   => self::components(),
				'status'       => $status,
				'changed'      => array(),
				'detected_at'  => 0,
				'choice'       => $choice,
				'confirmed_at' => '' === $choice ? 0 : time(),
				'confirmed_by' => $user,
			),
			false
		);
	}

	/**
	 * Write the journal head (best effort; no journal → no same-place detection).
	 *
	 * @param int $epoch Epoch.
	 * @return bool
	 */
	private static function write_journal( int $epoch ): bool {
		$file = self::journal_file();
		if ( '' === $file || ! is_dir( dirname( $file ) ) || ! wp_is_writable( dirname( $file ) ) ) {
			return false;
		}
		return false !== file_put_contents( $file, (string) wp_json_encode( array( 'epoch' => $epoch ) ), LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Head file outside the backup scope (R0-17 §5.5).
	}

	/**
	 * Hash of one identity value.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function hash( string $value ): string {
		return hash( 'sha256', 'ccap-site-identity|' . $value );
	}
}
