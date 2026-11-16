<?php
/**
 * R1-30: name conflicts and coexistence inventory (R0-20 §3; KOMP-130…134,
 * KOMP-110…114 as stubs). Foreign identifiers are "(v)": model knowledge.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\PostTypes;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Ops\Compatibility;
use CourseCaptain\Ops\HealthChecks;
use CourseCaptain\Ops\OpsStatus;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Security\Hardening;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Tests\Support\Compat\ForeignPlugin;
use CourseCaptain\Tests\Support\Compat\ForeignPlugins;

require_once dirname( __DIR__ ) . '/support/compat/ForeignPlugin.php';
require_once dirname( __DIR__ ) . '/support/compat/ForeignPlugins.php';

/**
 * Names and inventory.
 *
 * @group R1-30
 */
final class CompatibilityNamesTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Setup.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Teardown: restore own registrations.
	 */
	public function tear_down(): void {
		ForeignPlugin::deactivate_all();
		PostTypes::register();
		parent::tear_down();
	}

	/**
	 * Status check by ID.
	 *
	 * @param string $id ID.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private function check( string $id ): array {
		foreach ( OpsStatus::report( Actor::user( $this->admin ) )['checks'] as $check ) {
			if ( $id === $check['id'] ) {
				return $check;
			}
		}
		self::fail( 'missing check ' . $id );
	}

	/**
	 * KOMP-131 collider (`cc-collider`): foreign CPT, role, shortcode and block
	 * with own names are never overwritten; status "critical" with the
	 * location; no fatal. The table case is SchemaTest::test_KOMP_131_*.
	 *
	 * @group KOMP-131
	 */
	public function test_KOMP_131_collider(): void {
		self::assertSame( array(), Compatibility::collisions() );
		self::assertSame( 'ok', $this->check( 'name_collisions' )['status'] );

		// Post type.
		unregister_post_type( Relations::COURSE_TYPE );
		register_post_type(
			Relations::COURSE_TYPE,
			array(
				'public' => true,
				'label'  => 'Foreign course',
			)
		);
		PostTypes::register();
		self::assertSame( 'Foreign course', get_post_type_object( Relations::COURSE_TYPE )->label, 'not overwritten' );
		self::assertTrue( Compatibility::post_type_available( Relations::LESSON_TYPE ), 'other own types registered' );

		// Role with other capabilities.
		remove_role( Roles::TRAINER );
		add_role(
			Roles::TRAINER,
			'Foreign trainer',
			array(
				'read'       => true,
				'edit_posts' => true,
			)
		);
		Roles::install();
		self::assertSame(
			array(
				'read'       => true,
				'edit_posts' => true,
			),
			get_role( Roles::TRAINER )->capabilities,
			'foreign role unchanged'
		);

		// Shortcode.
		remove_shortcode( 'ccap_access' );
		add_shortcode( 'ccap_access', static fn(): string => 'FOREIGN' );
		self::assertFalse( Compatibility::add_shortcode( 'ccap_access', array( LearningArea::class, 'shortcode_access' ) ) );
		self::assertSame( 'FOREIGN', do_shortcode( '[ccap_access]' ) );

		// Block (registering twice would raise _doing_it_wrong).
		$registry = \WP_Block_Type_Registry::get_instance();
		$registry->unregister( 'course-captain/learning-link' );
		register_block_type( 'course-captain/learning-link', array( 'render_callback' => static fn(): string => 'FOREIGN' ) );
		LearningArea::blocks();
		self::assertSame( 'FOREIGN', $registry->get_registered( 'course-captain/learning-link' )->render( array() ) );

		$expected = array( 'post_type:ccap_course', 'role:ccap_trainer', 'shortcode:ccap_access', 'block:course-captain/learning-link' );
		self::assertSame( $expected, Compatibility::collisions() );
		$check = $this->check( 'name_collisions' );
		self::assertSame( 'fail', $check['status'] );
		self::assertSame( $expected, $check['ids'] );

		// Restore.
		unregister_post_type( Relations::COURSE_TYPE );
		PostTypes::register();
		remove_role( Roles::TRAINER );
		Roles::install();
		remove_shortcode( 'ccap_access' );
		self::assertTrue( Compatibility::add_shortcode( 'ccap_access', array( LearningArea::class, 'shortcode_access' ) ) );
		$registry->unregister( 'course-captain/learning-link' );
		LearningArea::blocks();
		self::assertSame( array(), Compatibility::collisions() );
	}

	/**
	 * KOMP-132 / F4.1: slugs used by pages, foreign types, category base or the
	 * other own slug need a confirmation; field error with alternative; no
	 * flush without confirmation; the existing page stays reachable.
	 *
	 * @group KOMP-132
	 */
	public function test_KOMP_132_slug_collisions(): void {
		$this->set_permalink_structure( '/%postname%/' );
		$page = (int) self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'akademie',
				'post_status' => 'publish',
			)
		);
		update_option( Plugin::REWRITE_VERSION_OPTION, Plugin::REWRITE_VERSION );
		$actor = Actor::user( $this->admin );
		try {
			Settings::save( $actor, 'general', array( 'learning_area_slug' => 'akademie' ), array( 'learning_area_slug' ), Settings::rev( 'general' ) );
			self::fail( 'conflict not detected' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_failed', $e->error_code );
			self::assertSame( 'learning_area_slug', $e->getMessage() );
			self::assertSame( array( 'page:' . $page ), $e->data['slug_conflicts']['learning_area_slug']['conflicts'] );
			self::assertSame( 'akademie-2', $e->data['slug_conflicts']['learning_area_slug']['suggestion'] );
		}
		self::assertSame( 'lernen', Settings::get( 'general.learning_area_slug' ) );
		self::assertSame( Plugin::REWRITE_VERSION, get_option( Plugin::REWRITE_VERSION_OPTION ), 'no flush without confirmation' );
		$this->go_to( (string) get_permalink( $page ) );
		self::assertTrue( is_page( $page ), 'page stays reachable' );

		// Foreign CPT slug (LifterLMS-like `course`), category base, own other slug.
		ForeignPlugins::lms()->activate();
		update_option( 'category_base', 'kurse' );
		$cases = array(
			'course'   => 'post_type:course',
			'courses'  => 'post_type:courses',
			'kurse'    => 'option:category_base',
			'lernen'   => 'setting:learning_area_slug',
			'unbelegt' => null,
		);
		foreach ( $cases as $slug => $conflict ) {
			$found = Compatibility::slug_conflicts( $slug, 'course_slug' );
			if ( null === $conflict ) {
				self::assertSame( array(), $found, $slug );
				continue;
			}
			self::assertContains( $conflict, $found, $slug );
		}
		self::assertSame( array(), Compatibility::slug_conflicts( 'kurs', 'course_slug' ), 'E-197 default free next to LMS slugs' );

		// Confirmation saves and schedules the flush.
		$result = Settings::save(
			$actor,
			'general',
			array(
				'learning_area_slug'         => 'akademie',
				Settings::CONFIRM_SLUG_FIELD => true,
			),
			array( 'learning_area_slug' ),
			Settings::rev( 'general' )
		);
		self::assertSame( array( 'learning_area_slug' ), $result['changed'] );
		self::assertFalse( get_option( Plugin::REWRITE_VERSION_OPTION ) );
		$check = $this->check( 'slug_conflicts' );
		self::assertSame( 'warn', $check['status'] );
		self::assertSame( array( 'learning_area_slug=akademie page:' . $page ), $check['ids'] );
	}

	/**
	 * KOMP-130 (runtime part): query vars, AJAX/admin-post actions, options,
	 * transients, post meta and cron hooks written or registered by Course
	 * Captain carry the prefixes of §3.1 (static part: KernelContractTest).
	 *
	 * @group KOMP-130
	 */
	public function test_KOMP_130_runtime_prefix_inventory(): void {
		global $wp, $wp_filter;
		$core = ( new \WP() )->public_query_vars;
		$skip = array();
		foreach ( get_post_types( array(), 'objects' ) as $type ) {
			if ( is_string( $type->query_var ) && ! in_array( $type->name, Relations::POST_TYPES, true ) ) {
				$skip[] = $type->query_var;
			}
		}
		foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
			if ( is_string( $taxonomy->query_var ) ) {
				$skip[] = $taxonomy->query_var;
			}
		}
		$vars = array_diff( array_unique( array_merge( $wp->public_query_vars, (array) apply_filters( 'query_vars', $core ) ) ), $core, $skip ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		self::assertNotEmpty( $vars );
		foreach ( $vars as $var ) {
			// Own CPT query var = post type name (`ccap_`, E-207); everything else `course_captain_` (D-20-03).
			self::assertTrue( in_array( $var, Relations::POST_TYPES, true ) || str_starts_with( $var, 'course_captain_' ), 'query var ' . $var );
		}

		foreach ( (array) $wp_filter as $hook => $callbacks ) {
			if ( 1 !== preg_match( '/^(?:wp_ajax_(?:nopriv_)?|admin_post_(?:nopriv_)?)(.+)$/', (string) $hook, $m ) ) {
				continue;
			}
			foreach ( $callbacks->callbacks as $by_priority ) {
				foreach ( $by_priority as $entry ) {
					if ( Compatibility::own_callback( $entry['function'] ) ) {
						self::assertStringStartsWith( 'ccap_', $m[1], 'E-285 action ' . $hook );
					}
				}
			}
		}

		// Options, transients and post meta written during activation and typical runs.
		$written  = array();
		$recorder = static function ( $name ) use ( &$written ): void {
			$written[] = (string) $name;
		};
		add_action( 'added_option', $recorder );
		add_action( 'updated_option', $recorder );
		add_action( 'setted_transient', $recorder );
		$meta = array();
		add_action(
			'added_post_meta',
			static function ( $mid, $post, $key ) use ( &$meta ): void {
				unset( $mid );
				if ( in_array( get_post_type( (int) $post ), Relations::POST_TYPES, true ) ) {
					$meta[] = (string) $key;
				}
			},
			10,
			3
		);
		self::reset_schema();
		Plugin::activate( false );
		Settings::save( Actor::user( $this->admin ), 'hardening', array( 'login_limit' => 'auto' ), array( 'login_limit' ), Settings::rev( 'hardening' ) );
		Hardening::apply();
		HealthChecks::run_isolated( array( 'noop' => static fn() => null ) );
		OpsStatus::report( Actor::user( $this->admin ) );
		wp_insert_post(
			array(
				'post_type'   => Relations::COURSE_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'KOMP-130',
			)
		);
		$core_options = array( 'rewrite_rules', 'cron', $GLOBALS['wpdb']->get_blog_prefix() . 'user_roles' );
		self::assertNotEmpty( $written );
		foreach ( array_unique( $written ) as $name ) {
			self::assertTrue( str_starts_with( $name, 'course_captain_' ) || in_array( $name, $core_options, true ), 'option/transient ' . $name );
		}
		foreach ( array_unique( $meta ) as $key ) {
			self::assertTrue( str_starts_with( $key, '_ccap_' ) || str_starts_with( $key, '_wp_' ) || str_starts_with( $key, '_edit_' ), 'post meta ' . $key );
		}
		foreach ( _get_cron_array() as $events ) {
			foreach ( array_keys( $events ) as $hook ) {
				if ( str_starts_with( (string) $hook, 'ccap' ) || str_starts_with( (string) $hook, 'course_captain' ) ) {
					self::assertContains( $hook, CronHooks::ALL, 'cron ' . $hook );
				}
			}
		}
		foreach ( CronHooks::ALL as $hook ) {
			self::assertStringStartsWith( 'ccap_', $hook );
		}
	}

	/**
	 * KOMP-134 / KOMP-111 / KOMP-112 / KOMP-114: multi-LMS inventory in
	 * both activation orders. Foreign post types, roles, shortcodes, REST
	 * routes and rewrite rules stay unchanged; Course Captain adds only
	 * prefixed entries; the LearnBoating identifiers (LB-K-09) do not overlap.
	 *
	 * @group KOMP-134
	 * @group KOMP-111
	 * @group KOMP-112
	 * @group KOMP-114
	 */
	public function test_KOMP_134_multi_lms_inventory(): void {
		$this->set_permalink_structure( '/%postname%/' );
		// Normalise: the suite registered the own types while permalinks were plain.
		PostTypes::register();
		flush_rewrite_rules( false );
		$own_before = $this->inventory( true );

		// Order 1: Course Captain first, LMS afterwards.
		ForeignPlugins::lms()->activate();
		do_action( 'rest_api_init', rest_get_server() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		flush_rewrite_rules( false );
		self::assertSame( $own_before, $this->inventory( true ), 'own entries unchanged by the LMS' );
		$foreign = $this->inventory( false );
		self::assertArrayHasKey( 'post_type:course', $foreign );
		self::assertArrayHasKey( 'role:tutor_instructor', $foreign );
		self::assertArrayHasKey( 'rest:/tutor/v1/courses', $foreign );

		// Order 2: Course Captain (re-)activated after the LMS.
		Plugin::activate( false );
		PostTypes::register();
		Roles::install();
		LearningArea::rewrite();
		do_action( 'rest_api_init', rest_get_server() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		flush_rewrite_rules( false );
		self::assertSame( $foreign, $this->inventory( false ), 'foreign entries unchanged by Course Captain' );
		self::assertSame( $own_before, $this->inventory( true ) );
		self::assertSame( array(), Compatibility::collisions() );

		// Own entries carry own prefixes only.
		foreach ( array_keys( $own_before ) as $key ) {
			self::assertMatchesRegularExpression( '#^(post_type:ccap_|role:ccap_|shortcode:(ccap_|course_captain_)|rest:/course-captain/|rewrite:.*(course_captain_|ccap_))#', (string) $key );
		}

		// LearnBoating identifiers (LB-K-09) never overlap with own names.
		$lb = array( 'bp_faq', 'bp_customer_faq', 'bp_tip', 'bp_app', 'bootsscheine', 'bootspruefung/v1', 'boatingschool/v1', 'bootspruefung/zugang' );
		foreach ( array_keys( $own_before ) as $key ) {
			foreach ( $lb as $name ) {
				self::assertStringNotContainsString( $name, (string) $key );
			}
		}
		foreach ( array( 'lernen', 'kurs' ) as $slug ) {
			self::assertNotContains( $slug, $lb );
		}
	}

	/**
	 * Inventory of post types, roles, shortcodes, REST routes and rewrite rules.
	 *
	 * @param bool $own Own entries (true) or foreign entries of the LMS stub (false).
	 * @return array<string, string> key => fingerprint.
	 */
	private function inventory( bool $own ): array {
		global $shortcode_tags;
		$out     = array();
		$is_own  = static fn( string $name ): bool => 1 === preg_match( '#^(ccap_|course_captain_|/course-captain/)#', $name );
		$foreign = array( 'courses', 'lesson', 'tutor_quiz', 'course', 'llms_quiz', 'tutor_instructor', 'instructor', 'instructors_assistant', 'lms_manager', 'student', 'tutor_dashboard', 'lifterlms_courses' );
		$want    = static fn( string $name ): bool => $own ? $is_own( $name ) : ( in_array( $name, $foreign, true ) || 1 === preg_match( '#^/(tutor|llms)/#', $name ) );
		foreach ( get_post_types( array(), 'objects' ) as $type ) {
			if ( $want( $type->name ) ) {
				$out[ 'post_type:' . $type->name ] = md5( (string) wp_json_encode( array( $type->label, $type->public, $type->rewrite, $type->query_var, $type->cap ) ) );
			}
		}
		foreach ( wp_roles()->roles as $slug => $role ) {
			if ( $want( (string) $slug ) ) {
				$out[ 'role:' . $slug ] = md5( (string) wp_json_encode( $role ) );
			}
		}
		foreach ( $shortcode_tags as $tag => $callback ) {
			if ( $want( (string) $tag ) ) {
				$out[ 'shortcode:' . $tag ] = Compatibility::own_callback( $callback ) ? 'own' : 'foreign';
			}
		}
		foreach ( array_keys( rest_get_server()->get_routes() ) as $route ) {
			if ( $want( (string) $route ) ) {
				$out[ 'rest:' . $route ] = 'route';
			}
		}
		foreach ( (array) get_option( 'rewrite_rules' ) as $regex => $query ) {
			$hit = $own ? 1 === preg_match( '#(course_captain_|ccap_)#', (string) $query ) : 1 === preg_match( '#[?&](courses|lesson|tutor_quiz|course|llms_quiz)=#', (string) $query );
			if ( $hit ) {
				$out[ 'rewrite:' . $regex . ' ' . $query ] = 'rule';
			}
		}
		ksort( $out );
		return $out;
	}
}
