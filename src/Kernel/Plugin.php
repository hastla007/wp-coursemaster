<?php
/**
 * Plugin kernel: bootstrap and life cycle entry points.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

use CourseCaptain\Contract\Api;
use CourseCaptain\Infrastructure\Migration\MigrationRunner;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\DeletionGuard;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Course\PostTypes;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Policy\EscalationGuard;
use CourseCaptain\Policy\Invalidation;
use CourseCaptain\Policy\MetaCaps;
use CourseCaptain\Storage\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the plugin on `plugins_loaded` (priority 20) and fires
 * `course_captain_loaded_v1` once requirements are met and no hold mode is
 * active (R0-19 §1.6).
 */
final class Plugin {

	/** Option that controls the one-time rewrite flush (R0-27 §2.7). */
	public const REWRITE_VERSION_OPTION = 'course_captain_rewrite_version';

	/** Bump when own rewrite rules change. */
	public const REWRITE_VERSION = '4';

	/**
	 * Instance booted by WordPress.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Whether the full plugin is running (requirements met, no hold mode).
	 *
	 * @var bool
	 */
	private bool $running = false;

	/**
	 * Unmet runtime requirements.
	 *
	 * @var list<RequirementFailure>
	 */
	private array $failures = array();

	/**
	 * Hold mode state of this request.
	 *
	 * @var HoldMode
	 */
	private HoldMode $hold_mode;

	/**
	 * Test override for the migration runner.
	 *
	 * @var MigrationRunner|null
	 */
	private static ?MigrationRunner $runner_override = null;

	/**
	 * Constructor.
	 *
	 * @param string          $basename   Plugin basename.
	 * @param MigrationRunner $migrations Schema upgrade trigger point.
	 */
	public function __construct(
		private string $basename,
		private MigrationRunner $migrations,
	) {
		$this->hold_mode = new HoldMode( array() );
	}

	/**
	 * `plugins_loaded` callback.
	 *
	 * @return void
	 */
	public static function boot_instance(): void {
		if ( null === self::$instance ) {
			self::$instance = new self( plugin_basename( COURSE_CAPTAIN_FILE ), self::migration_runner() );
			self::$instance->boot();
		}
	}

	/**
	 * Migration runner for this site (overridable in tests only).
	 *
	 * @return MigrationRunner
	 */
	public static function migration_runner(): MigrationRunner {
		return self::$runner_override ?? Migrator::for_current_site();
	}

	/**
	 * Test seam: replace the migration runner of the static hooks.
	 *
	 * @internal Only for the test suites; never called by plugin code.
	 * @param MigrationRunner|null $runner Runner or null for the real migrator.
	 * @return void
	 */
	public static function override_migration_runner( ?MigrationRunner $runner ): void {
		self::$runner_override = $runner;
	}

	/**
	 * Whether background jobs may run: false while the schema of this site
	 * is not current (W1, R0-37 §3.3). Test runners other than the real
	 * migrator always report ready.
	 *
	 * @return bool
	 */
	public static function schema_ready(): bool {
		$runner = self::migration_runner();
		return ! $runner instanceof Migrator || $runner->fingerprint_current();
	}

	/**
	 * The instance booted by WordPress, if any.
	 *
	 * @return self|null
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/**
	 * Activation hook callback.
	 *
	 * @param bool|mixed $network_wide Passed by WordPress; missing on the activation error page.
	 * @return void
	 */
	public static function activate( $network_wide = false ): void {
		( new Activator( self::migration_runner() ) )->activate( (bool) $network_wide );
	}

	/**
	 * Deactivation hook callback: removes cron events and rewrite rules of
	 * the current site only, never data (R0-16 §2.5, MS-R-10).
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		CronHooks::unschedule_all();
		delete_option( 'rewrite_rules' );
	}

	/**
	 * Register hooks for this request.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_filter( 'network_admin_plugin_action_links_' . $this->basename, array( NetworkGuard::class, 'filter_network_action_links' ) );

		$this->failures = Requirements::evaluate( EnvironmentSnapshot::runtime() );
		if ( array() !== $this->failures ) {
			add_action( 'admin_notices', array( $this, 'render_requirements_notice' ) );
			add_action( 'network_admin_notices', array( $this, 'render_requirements_notice' ) );
			return;
		}

		$this->hold_mode = HoldMode::detect( $this->basename );
		if ( $this->hold_mode->is_active() ) {
			add_action( 'admin_notices', array( $this, 'render_hold_mode_notice' ) );
			add_action( 'network_admin_notices', array( $this, 'render_hold_mode_notice' ) );
			return;
		}

		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 999 );
		add_action( 'admin_init', array( $this, 'on_admin_init' ) );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_process_complete' ), 10, 2 );
		add_action( CronHooks::HOURLY, array( CronHooks::class, 'run_hourly' ) );
		add_action( CronHooks::MIGRATE, array( $this->migrations, 'run_cron' ) );
		add_action( CronHooks::OUTBOX_DISPATCH, array( self::class, 'run_outbox_dispatch' ) );
		\CourseCaptain\Ops\MigrateCommand::register();
		\CourseCaptain\Ops\OpsCommand::register();
		MetaCaps::register();
		add_action( 'init', array( PostTypes::class, 'register' ), 5 );
		ObjectIdentity::register();
		CoursePublisher::register();
		DeletionGuard::register();
		\CourseCaptain\Progress\CourseProgress::register();
		\CourseCaptain\Access\ContentProtection::register();
		\CourseCaptain\Settings\Settings::register();
		\CourseCaptain\Admin\AuthoringRoutes::register();
		\CourseCaptain\Admin\Notices::register();
		\CourseCaptain\Admin\EditorScreens::register();
		\CourseCaptain\Import\ImportService::register();
		\CourseCaptain\Learner\LearnerRoutes::register();
		\CourseCaptain\Learner\LearningArea::register();
		\CourseCaptain\Certificate\CertificateService::register();
		\CourseCaptain\Certificate\CertificateRoutes::register();
		\CourseCaptain\Certificate\CertificatePages::register();
		\CourseCaptain\Privacy\PrivacyService::register();
		\CourseCaptain\Ops\HealthChecks::register();
		\CourseCaptain\Ops\AuditScreen::register();
		\CourseCaptain\Ops\OpsScreen::register();
		\CourseCaptain\Publish\PublishGuard::register();
		\CourseCaptain\Notification\NotificationService::register();
		\CourseCaptain\Access\ProtectedFiles::register();
		Invalidation::register();
		\CourseCaptain\Cache\CacheInvalidation::register();
		\CourseCaptain\Rest\RestContract::register();
		\CourseCaptain\Seo\CourseSeo::register();
		\CourseCaptain\Security\Hardening::register();
		EscalationGuard::register();
		add_action( 'init', array( $this, 'on_init' ), 5 );

		require_once dirname( __DIR__ ) . '/functions.php';
		$this->running = true;

		/**
		 * Fires once Course Captain is ready; extensions register from here on.
		 *
		 * @since 1.0.0
		 * @param Api $api Version handshake.
		 */
		do_action( 'course_captain_loaded_v1', new Api() );
	}

	/**
	 * Whether the full plugin runs in this request.
	 *
	 * @return bool
	 */
	public function is_running(): bool {
		return $this->running;
	}

	/**
	 * Hold mode state of this request.
	 *
	 * @return HoldMode
	 */
	public function hold_mode(): HoldMode {
		return $this->hold_mode;
	}

	/**
	 * Unmet runtime requirements.
	 *
	 * @return list<RequirementFailure>
	 */
	public function requirement_failures(): array {
		return $this->failures;
	}

	/**
	 * Load the bundled translations (until the language pack is approved, E-208).
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Bundled fallback until translate.wordpress.org ships the pack (E-208).
		load_plugin_textdomain( 'course-captain', false, dirname( $this->basename ) . '/languages' );
	}

	/**
	 * Flush rewrite rules once after activation or a rewrite version change.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( self::REWRITE_VERSION === get_option( self::REWRITE_VERSION_OPTION ) ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION, true );
	}

	/**
	 * `ccap_outbox_dispatch`: deliver, then re-schedule when more is due.
	 *
	 * @return void
	 */
	public static function run_outbox_dispatch(): void {
		if ( ! self::schema_ready() ) {
			// W1 (R0-37 §3.3, MIG-025): no delivery against a half-migrated schema; rows stay queued.
			if ( false === wp_next_scheduled( CronHooks::OUTBOX_DISPATCH ) ) {
				wp_schedule_single_event( time() + Migrator::RETRY_AFTER, CronHooks::OUTBOX_DISPATCH );
			}
			return;
		}
		$dispatcher = OutboxDispatcher::current();
		$stats      = $dispatcher->dispatch();
		if ( $stats['delivered'] + $stats['retried'] + $stats['failed'] + $stats['dead'] + $stats['skipped'] >= OutboxDispatcher::BATCH ) {
			OutboxDispatcher::schedule_soon();
		}
	}

	/**
	 * Trigger T-4 of R0-37 for frontend, REST, Ajax and admin-post requests.
	 *
	 * @return void
	 */
	public function on_init(): void {
		if ( is_admin() && ! wp_doing_ajax() && 'admin-post.php' !== ( $GLOBALS['pagenow'] ?? '' ) ) {
			return;
		}
		if ( wp_doing_cron() ) {
			return;
		}
		$this->migrations->on_request();
	}

	/**
	 * Trigger T-3 of R0-37 (admin requests only).
	 *
	 * @return void
	 */
	public function on_admin_init(): void {
		if ( wp_doing_ajax() || is_network_admin() || 'admin-post.php' === ( $GLOBALS['pagenow'] ?? '' ) ) {
			return;
		}
		$this->migrations->maybe_run_in_admin();
	}

	/**
	 * Trigger T-2 of R0-37: never migrate with the old code, only schedule.
	 *
	 * @param mixed $upgrader Upgrader instance (unused).
	 * @param mixed $options  Upgrade details.
	 * @return void
	 */
	public function on_upgrader_process_complete( $upgrader, $options ): void {
		unset( $upgrader );
		if ( ! is_array( $options ) || 'plugin' !== ( $options['type'] ?? '' ) ) {
			return;
		}
		$plugins = $options['plugins'] ?? array();
		if ( is_array( $plugins ) && in_array( $this->basename, $plugins, true ) ) {
			$this->migrations->schedule_after_update();
		}
	}

	/**
	 * Admin notice for unmet requirements.
	 *
	 * @return void
	 */
	public function render_requirements_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Course Captain is inactive because the site does not meet its requirements:', 'course-captain' ) . '</p><ul>';
		foreach ( $this->failures as $failure ) {
			echo '<li>' . esc_html( $failure->message() ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Admin notice for the hold mode (network admin and every site, MS-R-02).
	 *
	 * @return void
	 */
	public function render_hold_mode_notice(): void {
		$capability = is_network_admin() ? 'manage_network_plugins' : 'activate_plugins';
		if ( ! current_user_can( $capability ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html( $this->hold_mode->message() ) . '</p></div>';
	}
}
