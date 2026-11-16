<?php
/**
 * Detection of foreign plugins and environment conflicts (R0-20 §2, §3; R1-30).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Policy\Relations;
use CourseCaptain\Policy\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only probes on the public surface of other plugins (constants,
 * classes, functions, documented options, filters). Course Captain never
 * changes options of other plugins (LB-K-05). Foreign names come from model
 * knowledge and are marked "(v)" in the conflict matrix until verified
 * against the current plugin versions (R0-20 OP-20-2).
 *
 * Every lookup of a foreign marker goes through `constant()`, `has_class()`
 * and `has_function()`: the test fixtures under `tests/support/compat/`
 * switch markers on and off through `$stubs` because real constants and
 * classes cannot be removed within one PHP process.
 */
final class Compatibility {

	/**
	 * Test seam: `constant:NAME`, `class:Name` or `function:name` => value.
	 * `false` hides a marker; a callable for a function is used by `call()`.
	 *
	 * @var array<string, mixed>
	 */
	public static array $stubs = array();

	/**
	 * Security plugins with their own login/reset limit (LB-K-06 behaviour, E-240) (v).
	 *
	 * @var array<string, list<string>>
	 */
	public const SECURITY_PLUGINS = array(
		'wordfence'              => array( 'constant:WORDFENCE_VERSION' ),
		'solid-security'         => array( 'constant:ITSEC_CORE_FILE', 'class:ITSEC_Core' ),
		'limit-login-attempts'   => array( 'class:LLAR\Core\LimitLoginAttempts', 'constant:LLA_PLUGIN_URL' ),
		'loginizer'              => array( 'constant:LOGINIZER_VERSION' ),
		'all-in-one-wp-security' => array( 'constant:AIO_WP_SECURITY_VERSION' ),
		'wp-cerber'              => array( 'constant:CERBER_VER' ),
		'shield'                 => array( 'constant:ICWP_WPSF_FULL_PATH' ),
	);

	/**
	 * SEO plugins (R0-20 §2.7) (v).
	 *
	 * @var array<string, list<string>>
	 */
	public const SEO_PLUGINS = array(
		'yoast'             => array( 'constant:WPSEO_VERSION' ),
		'rank_math'         => array( 'class:RankMath' ),
		'aioseo'            => array( 'constant:AIOSEO_VERSION', 'function:aioseo' ),
		'seopress'          => array( 'constant:SEOPRESS_VERSION' ),
		'the-seo-framework' => array( 'constant:THE_SEO_FRAMEWORK_VERSION' ),
	);

	/**
	 * Database classes that cache query results (KOMP-034, E-198) (v).
	 *
	 * @var array<string, string>
	 */
	public const DB_QUERY_CACHES = array(
		'W3TC\DbCache_Wpdb'       => 'w3-total-cache',
		'W3TC\DbCache_WpdbLegacy' => 'w3-total-cache',
		'W3TC\DbCache_WpdbNew'    => 'w3-total-cache',
	);

	/**
	 * Own shortcodes (R0-19 §4.5, E-285).
	 *
	 * @var list<string>
	 */
	public const SHORTCODES = array( 'ccap_access', 'ccap_learning_link', 'ccap_protected', 'course_captain_protected' );

	/**
	 * Own blocks.
	 *
	 * @var list<string>
	 */
	public const BLOCKS = array( 'course-captain/course-catalog', 'course-captain/learning-link', 'course-captain/certificate-verify' );

	/**
	 * Option of the Elementor "Element Caching" experiment (KOMP-073) (v).
	 */
	public const ELEMENTOR_CACHE_OPTION = 'elementor_experiment-e_element_cache';

	/**
	 * Hooks: Polylang post type filter (D-25-06) and Site Health test.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'pll_get_post_types', array( self::class, 'polylang_post_types' ), 99 );
		add_filter( 'site_status_tests', array( self::class, 'site_status_tests' ) );
	}

	/**
	 * Register the Site Health test `course_captain_compatibility`.
	 *
	 * @param mixed $tests Tests.
	 * @return mixed
	 */
	public static function site_status_tests( $tests ) {
		if ( is_array( $tests ) ) {
			$tests['direct']['course_captain_compatibility'] = array(
				'label' => __( 'Course Captain compatibility', 'course-captain' ),
				'test'  => array( self::class, 'site_health' ),
			);
		}
		return $tests;
	}

	/**
	 * Whether a marker (`constant:…`, `class:…`, `function:…`) is present.
	 *
	 * @param string $marker Marker.
	 * @return bool
	 */
	public static function present( string $marker ): bool {
		if ( array_key_exists( $marker, self::$stubs ) ) {
			return false !== self::$stubs[ $marker ] && null !== self::$stubs[ $marker ];
		}
		list( $type, $name ) = array_pad( explode( ':', $marker, 2 ), 2, '' );
		return match ( $type ) {
			'constant' => defined( $name ),
			'class'    => class_exists( '\\' . ltrim( $name, '\\' ), false ),
			'function' => function_exists( $name ),
			default    => false,
		};
	}

	/**
	 * Whether a constant is defined.
	 *
	 * @param string $name Constant.
	 * @return bool
	 */
	public static function constant( string $name ): bool {
		return self::present( 'constant:' . $name );
	}

	/**
	 * Value of a constant (stub value first).
	 *
	 * @param string $name Constant.
	 * @return mixed
	 */
	public static function constant_value( string $name ) {
		$marker = 'constant:' . $name;
		if ( array_key_exists( $marker, self::$stubs ) ) {
			return self::$stubs[ $marker ];
		}
		return defined( $name ) ? constant( $name ) : null;
	}

	/**
	 * Whether a class exists (without autoloading).
	 *
	 * @param string $name Class.
	 * @return bool
	 */
	public static function has_class( string $name ): bool {
		return self::present( 'class:' . ltrim( $name, '\\' ) );
	}

	/**
	 * Whether a function exists.
	 *
	 * @param string $name Function.
	 * @return bool
	 */
	public static function has_function( string $name ): bool {
		return self::present( 'function:' . $name );
	}

	/**
	 * Call a foreign function (stub callable first).
	 *
	 * @param string $name Function.
	 * @param mixed  ...$args Arguments.
	 * @return mixed Null when the function is missing.
	 */
	public static function call( string $name, ...$args ) {
		$stub = self::$stubs[ 'function:' . $name ] ?? null;
		if ( is_callable( $stub ) ) {
			return $stub( ...$args );
		}
		if ( ! self::has_function( $name ) || ! is_callable( $name ) ) {
			return null;
		}
		return call_user_func_array( $name, $args );
	}

	/**
	 * First catalog entry whose markers are present.
	 *
	 * @param array<string, list<string>> $catalog Catalog.
	 * @return string|null Slug.
	 */
	public static function detect( array $catalog ): ?string {
		foreach ( $catalog as $slug => $markers ) {
			foreach ( $markers as $marker ) {
				if ( self::present( $marker ) ) {
					return $slug;
				}
			}
		}
		return null;
	}

	/**
	 * Security plugin with its own login limit (KOMP-057).
	 *
	 * @return string|null
	 */
	public static function security_plugin(): ?string {
		return self::detect( self::SECURITY_PLUGINS );
	}

	/**
	 * Active SEO plugin (KOMP-060…063).
	 *
	 * @return string|null
	 */
	public static function seo_plugin(): ?string {
		return self::detect( self::SEO_PLUGINS );
	}

	/**
	 * KOMP-052/051: the REST API rejects visitors who are not logged in
	 * (`rest_authentication_errors` returns an error for user 0).
	 *
	 * @return bool
	 */
	public static function rest_guest_blocked(): bool {
		$user = get_current_user_id();
		wp_set_current_user( 0 );
		try {
			$result = apply_filters( 'rest_authentication_errors', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter probed read-only.
		} finally {
			wp_set_current_user( $user );
		}
		return is_wp_error( $result );
	}

	/**
	 * KOMP-034: database class that caches query results.
	 *
	 * @param object|null $db Database object (tests); default `$wpdb`.
	 * @return array{plugin: string, class: string}|null `plugin` is `unknown` for an unknown drop-in class.
	 */
	public static function db_query_cache( ?object $db = null ): ?array {
		$db = $db ?? ( $GLOBALS['wpdb'] ?? null );
		if ( ! is_object( $db ) ) {
			return null;
		}
		$classes = array_merge( array( get_class( $db ) ), array_values( (array) class_parents( $db ) ) );
		foreach ( self::DB_QUERY_CACHES as $class => $plugin ) {
			if ( in_array( $class, $classes, true ) ) {
				return array(
					'plugin' => $plugin,
					'class'  => $class,
				);
			}
		}
		if ( 'wpdb' !== get_class( $db ) ) {
			return array(
				'plugin' => 'unknown',
				'class'  => get_class( $db ),
			);
		}
		return null;
	}

	/**
	 * KOMP-056: active mail logs that store message bodies (and thus invitation links) (v).
	 *
	 * @return list<string> Plugin slugs.
	 */
	public static function mail_logs(): array {
		$out = array();
		if ( self::constant( 'WPMS_PLUGIN_VER' ) ) {
			$settings = get_option( 'wp_mail_smtp' );
			if ( is_array( $settings ) && ! empty( $settings['logs']['enabled'] ) ) {
				$out[] = 'wp-mail-smtp';
			}
		}
		if ( self::constant( 'FLUENTMAIL_PLUGIN_VERSION' ) ) {
			$settings = get_option( 'fluentmail-settings' );
			if ( is_array( $settings ) && 'yes' === ( $settings['misc']['log_emails'] ?? 'yes' ) ) {
				$out[] = 'fluent-smtp';
			}
		}
		if ( self::constant( 'POST_SMTP_VER' ) ) {
			$out[] = 'post-smtp';
		}
		if ( self::has_class( 'No3x\WPML\WPML_Plugin' ) ) {
			$out[] = 'wp-mail-logging';
		}
		return $out;
	}

	/**
	 * KOMP-120: WP-Cron switched off by constant.
	 *
	 * @return bool
	 */
	public static function wp_cron_disabled(): bool {
		return self::constant( 'DISABLE_WP_CRON' ) && (bool) self::constant_value( 'DISABLE_WP_CRON' );
	}

	/**
	 * KOMP-016: plain permalinks (query fallback for the learning area and files).
	 *
	 * @return bool
	 */
	public static function plain_permalinks(): bool {
		return '' === (string) get_option( 'permalink_structure' );
	}

	/**
	 * KOMP-091: own post types that Polylang still treats as translatable.
	 *
	 * @return list<string>|null Null without Polylang.
	 */
	public static function polylang_translated_types(): ?array {
		if ( ! self::has_function( 'pll_is_translated_post_type' ) ) {
			return null;
		}
		return array_values( array_filter( Relations::POST_TYPES, static fn( string $type ): bool => (bool) self::call( 'pll_is_translated_post_type', $type ) ) );
	}

	/**
	 * D-25-06: own post types are not translatable in Polylang (`pll_get_post_types` (v)).
	 *
	 * @param mixed $types Post types (slug => slug).
	 * @return mixed
	 */
	public static function polylang_post_types( $types ) {
		if ( is_array( $types ) ) {
			foreach ( Relations::POST_TYPES as $type ) {
				unset( $types[ $type ] );
			}
		}
		return $types;
	}

	/**
	 * KOMP-073: state of Elementor "Element Caching".
	 *
	 * @return string|null `active`, `default`, `inactive`; null without Elementor.
	 */
	public static function elementor_element_cache(): ?string {
		if ( ! self::constant( 'ELEMENTOR_VERSION' ) ) {
			return null;
		}
		$state = (string) get_option( self::ELEMENTOR_CACHE_OPTION, 'default' );
		return in_array( $state, array( 'active', 'inactive' ), true ) ? $state : 'default';
	}

	/**
	 * Whether a callback belongs to Course Captain.
	 *
	 * @param mixed $callback Callback.
	 * @return bool
	 */
	public static function own_callback( $callback ): bool {
		if ( is_array( $callback ) && isset( $callback[0] ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return 0 === strpos( ltrim( $class, '\\' ), 'CourseCaptain\\' );
		}
		return is_string( $callback ) && 0 === strpos( ltrim( $callback, '\\' ), 'CourseCaptain\\' );
	}

	/**
	 * Whether a post type slug is free or registered by Course Captain (KOMP-131).
	 *
	 * @param string $type Post type.
	 * @return bool
	 */
	public static function post_type_available( string $type ): bool {
		$object = get_post_type_object( $type );
		return null === $object || ! empty( get_object_vars( $object )['ccap_owner'] );
	}

	/**
	 * `add_shortcode()` without overwriting a foreign shortcode (KOMP-131).
	 *
	 * @param string   $tag      Tag.
	 * @param callable $callback Callback.
	 * @phpstan-param non-empty-string $tag
	 * @return bool Whether the shortcode is ours.
	 */
	public static function add_shortcode( string $tag, callable $callback ): bool {
		global $shortcode_tags;
		if ( shortcode_exists( $tag ) && ! self::own_callback( $shortcode_tags[ $tag ] ?? null ) ) {
			return false;
		}
		add_shortcode( $tag, $callback );
		return true;
	}

	/**
	 * `register_block_type()` without overwriting a foreign block (KOMP-131).
	 *
	 * @param string               $dir  block.json folder.
	 * @param string               $name Block name.
	 * @param array<string, mixed> $args Arguments.
	 * @return bool Whether the block is ours.
	 */
	public static function register_block( string $dir, string $name, array $args ): bool {
		$registry = \WP_Block_Type_Registry::get_instance();
		if ( $registry->is_registered( $name ) ) {
			$block = $registry->get_registered( $name );
			return null !== $block && self::own_callback( $block->render_callback );
		}
		return false !== register_block_type( $dir, $args );
	}

	/**
	 * F4.1 / KOMP-132: where a public slug of Course Captain is already in use.
	 *
	 * @param string $slug  Slug.
	 * @param string $field `learning_area_slug` or `course_slug`.
	 * @return list<string> `kind:name` entries.
	 */
	public static function slug_conflicts( string $slug, string $field ): array {
		global $wp_rewrite;
		$out   = array();
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), Relations::POST_TYPES ) );
		$post  = get_page_by_path( $slug, OBJECT, $types );
		if ( $post instanceof \WP_Post ) {
			$out[] = $post->post_type . ':' . $post->ID;
		}
		foreach ( get_post_types( array(), 'objects' ) as $type ) {
			if ( in_array( $type->name, Relations::POST_TYPES, true ) ) {
				continue;
			}
			$rewrite = is_array( $type->rewrite ) ? (string) ( $type->rewrite['slug'] ?? '' ) : '';
			$archive = is_string( $type->has_archive ) ? $type->has_archive : '';
			if ( ( $type->public || $type->publicly_queryable ) && in_array( $slug, array( $type->name, $rewrite, $archive ), true ) ) {
				$out[] = 'post_type:' . $type->name;
			}
		}
		foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
			$rewrite = is_array( $taxonomy->rewrite ) ? (string) ( $taxonomy->rewrite['slug'] ?? '' ) : '';
			if ( '' !== $rewrite && $slug === $rewrite ) {
				$out[] = 'taxonomy:' . $taxonomy->name;
			}
		}
		foreach ( array( 'category_base', 'tag_base' ) as $option ) {
			if ( trim( (string) get_option( $option ), '/' ) === $slug ) {
				$out[] = 'option:' . $option;
			}
		}
		if ( $wp_rewrite instanceof \WP_Rewrite && $slug === $wp_rewrite->author_base ) {
			$out[] = 'author_base';
		}
		$other = 'course_slug' === $field ? 'learning_area_slug' : 'course_slug';
		try {
			$other_value = (string) \CourseCaptain\Settings\Settings::get( 'general.' . $other );
		} catch ( \Throwable $e ) {
			unset( $e );
			$other_value = '';
		}
		if ( $slug === $other_value ) {
			$out[] = 'setting:' . $other;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Free alternative for a taken slug (`<slug>-2`, `<slug>-3`, …).
	 *
	 * @param string $slug  Slug.
	 * @param string $field Field.
	 * @return string
	 */
	public static function slug_suggestion( string $slug, string $field ): string {
		for ( $n = 2; $n < 50; $n++ ) {
			$candidate = substr( $slug, 0, 60 ) . '-' . $n;
			if ( array() === self::slug_conflicts( $candidate, $field ) ) {
				return $candidate;
			}
		}
		return substr( $slug, 0, 50 ) . '-' . wp_rand( 100, 999 );
	}

	/**
	 * Own identifiers occupied by somebody else (KOMP-131): `kind:name`.
	 *
	 * @return list<string>
	 */
	public static function collisions(): array {
		global $shortcode_tags;
		$out = array();
		foreach ( Relations::POST_TYPES as $type ) {
			if ( ! self::post_type_available( $type ) ) {
				$out[] = 'post_type:' . $type;
			}
		}
		foreach ( Roles::ALL as $slug ) {
			$role = get_role( $slug );
			if ( null !== $role && ! Roles::owned( $role ) ) {
				$out[] = 'role:' . $slug;
			}
		}
		foreach ( self::SHORTCODES as $tag ) {
			if ( isset( $shortcode_tags[ $tag ] ) && ! self::own_callback( $shortcode_tags[ $tag ] ) ) {
				$out[] = 'shortcode:' . $tag;
			}
		}
		$registry = \WP_Block_Type_Registry::get_instance();
		foreach ( self::BLOCKS as $name ) {
			$block = $registry->get_registered( $name );
			if ( null !== $block && ! self::own_callback( $block->render_callback ) ) {
				$out[] = 'block:' . $name;
			}
		}
		return $out;
	}

	/**
	 * Compatibility checks for the system status (`ok`/`warn`/`fail`/`info`).
	 *
	 * @return list<array{id: string, status: string, label: string, hint: string, ids: list<string>}>
	 */
	public static function checks(): array {
		return array(
			self::collision_check(),
			self::slug_check(),
			self::rest_check(),
			self::db_cache_check(),
			self::mail_log_check(),
			self::cron_check(),
			self::permalink_check(),
			self::polylang_check(),
			self::elementor_check(),
		);
	}

	/**
	 * Worst compatibility check as Site Health result.
	 *
	 * @return array<string, mixed>
	 */
	public static function site_health(): array {
		$checks = self::checks();
		$status = OpsStatus::overall( $checks );
		$items  = '';
		foreach ( $checks as $check ) {
			if ( in_array( $check['status'], array( 'warn', 'fail' ), true ) ) {
				$items .= '<li>' . esc_html( $check['label'] . ' ' . $check['hint'] ) . '</li>';
			}
		}
		return array(
			'label'       => 'ok' === $status ? __( 'No known conflicts with other plugins or the server setup.', 'course-captain' ) : __( 'Course Captain found conflicts with other plugins or the server setup.', 'course-captain' ),
			'status'      => 'fail' === $status ? 'critical' : ( 'warn' === $status ? 'recommended' : 'good' ),
			'badge'       => array(
				'label' => __( 'Course Captain', 'course-captain' ),
				'color' => 'ok' === $status ? 'blue' : 'orange',
			),
			'description' => '' === $items ? '' : '<ul>' . $items . '</ul>',
			'actions'     => '',
			'test'        => 'course_captain_compatibility',
		);
	}

	/**
	 * KOMP-131 check.
	 *
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function collision_check(): array {
		$ids = self::collisions();
		if ( array() === $ids ) {
			return self::check( 'name_collisions', 'ok', __( 'No other plugin uses the names of Course Captain.', 'course-captain' ), '' );
		}
		return self::check( 'name_collisions', 'fail', __( 'Another plugin already uses names of Course Captain; they were not overwritten and the affected functions are missing.', 'course-captain' ), __( 'Deactivate the plugin that registers these names or contact its developer.', 'course-captain' ), $ids );
	}

	/**
	 * KOMP-132 check: current public slugs against pages, types, bases.
	 *
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function slug_check(): array {
		$ids = array();
		foreach ( array( 'learning_area_slug', 'course_slug' ) as $field ) {
			try {
				$slug = (string) \CourseCaptain\Settings\Settings::get( 'general.' . $field );
			} catch ( \Throwable $e ) {
				unset( $e );
				continue;
			}
			foreach ( self::slug_conflicts( $slug, $field ) as $conflict ) {
				$ids[] = $field . '=' . $slug . ' ' . $conflict;
			}
		}
		if ( array() === $ids ) {
			return self::check( 'slug_conflicts', 'ok', __( 'The addresses of the learning area and the courses are free.', 'course-captain' ), '' );
		}
		return self::check( 'slug_conflicts', 'warn', __( 'An address of Course Captain is also used by a page, content type or archive; one of them may not be reachable.', 'course-captain' ), __( 'Change the slug of the learning area or of the courses in the general settings.', 'course-captain' ), $ids );
	}

	/**
	 * KOMP-051/052 check `rest_guest_blocked`.
	 *
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function rest_check(): array {
		if ( ! self::rest_guest_blocked() ) {
			return self::check( 'rest_guest_blocked', 'ok', __( 'The REST API is reachable for visitors.', 'course-captain' ), '' );
		}
		return self::check( 'rest_guest_blocked', 'warn', __( 'A plugin or snippet blocks the REST API for visitors who are not logged in.', 'course-captain' ), __( 'Logged-in learners are not affected. The certificate verification page works without the REST API; allow the course-captain/v1 routes for visitors if you use the catalog or verification in other clients.', 'course-captain' ) );
	}

	/**
	 * KOMP-034 check (E-198: not supported).
	 *
	 * @param object|null $db Database object (tests); default `$wpdb`.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	public static function db_cache_check( ?object $db = null ): array {
		$found = self::db_query_cache( $db );
		if ( null === $found ) {
			return self::check( 'db_query_cache', 'ok', __( 'No database query cache detected.', 'course-captain' ), '' );
		}
		if ( 'unknown' === $found['plugin'] ) {
			return self::check( 'db_query_cache', 'info', __( 'A database drop-in replaces the WordPress database class.', 'course-captain' ), __( 'If it caches query results, switch that off: Course Captain needs current data for access checks.', 'course-captain' ), array( 'class:' . $found['class'] ) );
		}
		return self::check( 'db_query_cache', 'fail', __( 'A database query cache is active. It can return outdated access rights and locks; this setup is not supported.', 'course-captain' ), __( 'Switch off the database cache of the cache plugin (object and page cache may stay on).', 'course-captain' ), array( 'plugin:' . $found['plugin'] ) );
	}

	/**
	 * KOMP-056 check.
	 *
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function mail_log_check(): array {
		$logs = self::mail_logs();
		if ( array() === $logs ) {
			return self::check( 'mail_log', 'ok', __( 'No mail log detected.', 'course-captain' ), '' );
		}
		return self::check( 'mail_log', 'warn', __( 'Mail log active: links in invitations are logged as well.', 'course-captain' ), __( 'Invitation and claim links expire, but restrict access to the mail log or shorten its retention.', 'course-captain' ), array_map( static fn( string $slug ): string => 'plugin:' . $slug, $logs ) );
	}

	/**
	 * KOMP-120 check.
	 *
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function cron_check(): array {
		if ( ! self::wp_cron_disabled() ) {
			return self::check( 'wp_cron', 'ok', __( 'WP-Cron runs with page requests.', 'course-captain' ), '' );
		}
		$state = get_option( HealthChecks::STATE_OPTION );
		$last  = is_array( $state ) ? (int) ( $state['last_run'] ?? 0 ) : 0;
		if ( $last >= time() - 3 * HOUR_IN_SECONDS ) {
			return self::check( 'wp_cron', 'info', __( 'WP-Cron is switched off; a server cron job runs the maintenance.', 'course-captain' ), '' );
		}
		return self::check( 'wp_cron', 'warn', __( 'WP-Cron is switched off (DISABLE_WP_CRON) and no server cron job has run the maintenance in the last three hours.', 'course-captain' ), __( 'Access deadlines are still checked on every access, but mails and cleanup wait. Set up a server cron job that calls wp-cron.php.', 'course-captain' ) );
	}

	/**
	 * KOMP-016 check (D-20-08).
	 *
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function permalink_check(): array {
		if ( ! self::plain_permalinks() ) {
			return self::check( 'permalinks', 'ok', __( 'Permalinks are set up.', 'course-captain' ), '' );
		}
		return self::check( 'permalinks', 'warn', __( 'Plain permalinks are active: the learning area and protected files use query links.', 'course-captain' ), __( 'Choose a permalink structure under Settings → Permalinks for readable links.', 'course-captain' ) );
	}

	/**
	 * KOMP-091 check (D-25-06).
	 *
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function polylang_check(): array {
		$types = self::polylang_translated_types();
		if ( null === $types ) {
			return self::check( 'translation', 'ok', __( 'No translation plugin with post type translation detected.', 'course-captain' ), '' );
		}
		if ( array() !== $types ) {
			return self::check( 'translation', 'warn', __( 'Course Captain content is marked as translatable in the translation plugin; translated copies are not supported.', 'course-captain' ), __( 'Switch off translation for the Course Captain content types in the translation plugin.', 'course-captain' ), array_map( static fn( string $type ): string => 'post_type:' . $type, $types ) );
		}
		return self::check( 'translation', 'info', __( 'Translation plugin detected: Course Captain content is not translated.', 'course-captain' ), '' );
	}

	/**
	 * KOMP-073 check.
	 *
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function elementor_check(): array {
		$state = self::elementor_element_cache();
		if ( null === $state || 'inactive' === $state ) {
			return self::check( 'element_cache', 'ok', __( 'No widget output cache detected.', 'course-captain' ), '' );
		}
		$hint = __( 'Cached widgets could show protected content to other users. Switch off "Element Caching" in Elementor or set it to "Inactive" for widgets with Course Captain shortcodes.', 'course-captain' );
		if ( 'active' === $state ) {
			return self::check( 'element_cache', 'fail', __( 'Elementor "Element Caching" is active.', 'course-captain' ), $hint );
		}
		return self::check( 'element_cache', 'warn', __( 'Elementor "Element Caching" may be active by default.', 'course-captain' ), $hint );
	}

	/**
	 * Check record.
	 *
	 * @param string $id     ID.
	 * @param string $status ok|warn|fail|info.
	 * @param string $label  Text.
	 * @param string $hint   Hint.
	 * @param array  $ids    Object IDs.
	 * @phpstan-param list<string> $ids
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function check( string $id, string $status, string $label, string $hint, array $ids = array() ): array {
		return array(
			'id'     => $id,
			'status' => $status,
			'label'  => $label,
			'hint'   => $hint,
			'ids'    => $ids,
		);
	}
}
