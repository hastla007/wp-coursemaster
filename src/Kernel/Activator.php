<?php
/**
 * Activation of the plugin on a single site.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

use Closure;
use CourseCaptain\Infrastructure\Migration\MigrationRunner;
use CourseCaptain\Policy\Roles;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Order matters: every check runs before anything is written (MS-R-01,
 * R0-20 §1.2), and the migration runs before capabilities, cron and rewrite
 * rules (R0-37 T-1).
 */
final class Activator {

	/**
	 * Provides the environment snapshot (replaceable in tests).
	 *
	 * @var Closure(): EnvironmentSnapshot
	 */
	private Closure $environment;

	/**
	 * Constructor.
	 *
	 * @param MigrationRunner                       $migrations  Schema upgrade trigger point.
	 * @param (Closure(): EnvironmentSnapshot)|null $environment Snapshot provider; defaults to the live database probe.
	 */
	public function __construct( private MigrationRunner $migrations, ?Closure $environment = null ) {
		$this->environment = $environment ?? static fn(): EnvironmentSnapshot => EnvironmentSnapshot::with_database();
	}

	/**
	 * Activate on the current site.
	 *
	 * @param bool $network_wide Whether WordPress requested a network activation.
	 * @return void
	 */
	public function activate( bool $network_wide ): void {
		if ( NetworkGuard::must_block_activation( $network_wide ) ) {
			self::abort( array( NetworkGuard::blocked_message() ) );
		}

		$failures = Requirements::evaluate( ( $this->environment )() );
		if ( array() !== $failures ) {
			$messages = array();
			foreach ( $failures as $failure ) {
				$messages[] = $failure->message();
			}
			self::abort( $messages );
		}

		// KOMP-131: never touch tables of the same name that are not ours.
		$conflicts = $this->migrations->conflicting_tables();
		if ( array() !== $conflicts ) {
			self::abort(
				array(
					sprintf(
						/* translators: %s: comma-separated table names */
						__( 'Database tables with the names Course Captain needs already exist and belong to other software: %s. Nothing has been changed.', 'course-captain' ),
						implode( ', ', $conflicts )
					),
				)
			);
		}

		// The activation error page (plugins.php?action=error_scrape) re-runs this
		// hook without arguments. It may report errors but must never write.
		// Note: WP_SANDBOX_SCRAPING is defined on every activation and is no signal.
		if ( self::is_error_scrape() ) {
			return;
		}

		try {
			$this->migrations->run_on_activation();
		} catch ( Throwable $e ) {
			self::abort( array( __( 'The database could not be prepared. Nothing else has been changed; please check the system status and try again.', 'course-captain' ) ) );
		}

		Capabilities::grant_activation_defaults();
		Roles::install();
		CronHooks::schedule_recurring( time() );
		// Rewrite rules are flushed once on the next request, after all rules are registered.
		delete_option( Plugin::REWRITE_VERSION_OPTION );
	}

	/**
	 * Whether the current request is the activation error page of WordPress.
	 *
	 * @return bool
	 */
	public static function is_error_scrape(): bool {
		// WordPress verifies the nonce of this request itself (check_admin_referer in plugins.php).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		return is_admin() && 'error_scrape' === $action;
	}

	/**
	 * Abort the activation with a readable message. WP-CLI turns this into exit code 1.
	 *
	 * @param string[] $messages Translated messages.
	 * @phpstan-param list<string> $messages
	 * @return never
	 */
	private static function abort( array $messages ): never {
		$items = '';
		foreach ( $messages as $message ) {
			$items .= '<li>' . esc_html( $message ) . '</li>';
		}

		wp_die(
			'<p>' . esc_html__( 'Course Captain cannot be activated:', 'course-captain' ) . '</p><ul>' . $items . '</ul>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every part is escaped above.
			esc_html__( 'Course Captain cannot be activated', 'course-captain' ),
			array(
				'response'  => 500,
				'back_link' => true,
			)
		);
	}
}
