<?php
/**
 * R1-28: contract tests for REST, hooks, shortcodes and blocks (R0-19 §1, §2.1, §3.1, §9 CT-01…CT-05).
 *
 * Snapshots live in `docs/api/`. After a deliberate contract change run the
 * suite once with `CCAP_UPDATE_SNAPSHOTS=1` and add an entry to
 * `docs/API-CHANGELOG.md`.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ContentProtection;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Rest\RestContract;

/**
 * Contract gate.
 *
 * @group R1-28
 */
final class ApiContractTest extends SchemaTestCase {

	/**
	 * Public routes (Ö) – documented exceptions to the login rule.
	 *
	 * @var list<string>
	 */
	private const PUBLIC_ROUTES = array(
		'/course-captain/v1/certificates/verify/(?P<token>[0-9a-zA-Z]{1,64})',
		// R-50 monitor (auth class K): overall status only without header key (GM-147/GM-148).
		'/course-captain/v1/health',
		'/course-captain/v1/monitor',
	);

	/**
	 * Setup.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
	}

	/**
	 * Own routes.
	 *
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function routes(): array {
		$out = array();
		foreach ( rest_get_server()->get_routes( RestContract::NAMESPACE ) as $route => $handlers ) {
			if ( '/' . RestContract::NAMESPACE === $route ) {
				continue;
			}
			$out[ $route ] = $handlers;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Compare with a snapshot file or rewrite it on request.
	 *
	 * @param string $file   File under docs/api.
	 * @param mixed  $actual Actual value.
	 * @return void
	 */
	private static function assert_snapshot( string $file, $actual ): void {
		$path = dirname( __DIR__, 2 ) . '/docs/api/' . $file;
		$json = (string) wp_json_encode( $actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
		if ( '1' === getenv( 'CCAP_UPDATE_SNAPSHOTS' ) ) {
			file_put_contents( $path, $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test fixture.
		}
		self::assertFileExists( $path );
		self::assertSame( (string) file_get_contents( $path ), $json, $file . ' differs: undeclared contract change (update snapshot + API-CHANGELOG)' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test fixture.
	}

	/**
	 * CT-01: route snapshot (path, methods, args, public flag).
	 *
	 * @group CT-01
	 * @group GM-155
	 */
	public function test_CT_01_route_snapshot(): void {
		$snapshot = array();
		foreach ( self::routes() as $route => $handlers ) {
			foreach ( $handlers as $handler ) {
				$methods = array_keys( array_filter( (array) $handler['methods'] ) );
				sort( $methods );
				$args = array();
				foreach ( (array) ( $handler['args'] ?? array() ) as $name => $spec ) {
					$args[ $name ] = array(
						'type'     => $spec['type'] ?? null,
						'required' => ! empty( $spec['required'] ),
					);
				}
				ksort( $args );
				self::assertArrayHasKey( 'permission_callback', $handler, $route . ' without permission_callback' );
				$snapshot[ $route . ' ' . implode( ',', $methods ) ] = array(
					'route'   => $route,
					'methods' => $methods,
					'args'    => $args,
					'public'  => '__return_true' === $handler['permission_callback'],
				);
			}
		}
		foreach ( $snapshot as $entry ) {
			self::assertSame( in_array( $entry['route'], self::PUBLIC_ROUTES, true ), $entry['public'], 'only documented public routes use __return_true: ' . $entry['route'] );
		}
		ksort( $snapshot );
		self::assert_snapshot( 'routes.v1.json', array_values( $snapshot ) );
	}

	/**
	 * Concrete path for a route pattern.
	 *
	 * @param string $route Route regex.
	 * @return string
	 */
	private static function sample_path( string $route ): string {
		return (string) preg_replace_callback(
			'/\(\?P<([a-z_]+)>[^)]*\)/',
			static fn( array $m ): string => in_array( $m[1], array( 'course', 'uid' ), true ) ? '00000000-0000-4000-8000-000000000000' : ( 'token' === $m[1] ? 'abc' : '999999' ),
			$route
		);
	}

	/**
	 * Dispatch incl. `rest_post_dispatch` (as `serve_request` does).
	 *
	 * @param string                              $method Method.
	 * @param string                              $path   Path.
	 * @param array<string, array<string, mixed>> $args   Route args (required ones get dummy values: WP checks them before permissions).
	 * @return \WP_REST_Response
	 */
	private static function call( string $method, string $path, array $args = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $path );
		foreach ( $args as $name => $spec ) {
			if ( ! empty( $spec['required'] ) ) {
				$type = (array) ( $spec['type'] ?? 'string' );
				$request->set_param( $name, in_array( 'array', $type, true ) ? array() : ( in_array( 'integer', $type, true ) ? 1 : ( in_array( 'boolean', $type, true ) ? true : 'x' ) ) );
			}
		}
		return apply_filters( 'rest_post_dispatch', rest_do_request( $request ), rest_get_server(), $request ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
	}

	/**
	 * CT-02 / CT-03: every private route rejects anonymous calls with 401 `ccap_auth_required`
	 * in the §3.1 error format; headers carry request ID and no-store.
	 *
	 * @group CT-02
	 * @group CT-03
	 * @group GM-145
	 */
	public function test_CT_02_03_anonymous_and_error_format(): void {
		wp_set_current_user( 0 );
		$checked = 0;
		foreach ( self::routes() as $route => $handlers ) {
			if ( in_array( $route, self::PUBLIC_ROUTES, true ) ) {
				continue;
			}
			foreach ( $handlers as $handler ) {
				foreach ( array_keys( array_filter( (array) $handler['methods'] ) ) as $method ) {
					$response = self::call( $method, self::sample_path( $route ), (array) ( $handler['args'] ?? array() ) );
					$data     = $response->get_data();
					self::assertSame( 401, $response->get_status(), $method . ' ' . $route );
					self::assertSame( 'ccap_auth_required', $data['code'], $method . ' ' . $route );
					foreach ( array( 'code', 'message', 'data', 'type', 'title', 'status', 'detail', 'request_id', 'reason', 'retry_after', 'errors', 'details' ) as $field ) {
						self::assertArrayHasKey( $field, $data, $field . ' in ' . $route );
					}
					self::assertSame( 401, $data['data']['status'] );
					self::assertSame( 'urn:course-captain:problem:auth-required', $data['type'] );
					self::assertSame( $data['request_id'], $response->get_headers()['X-Request-Id'] );
					self::assertMatchesRegularExpression( '/^req_[0-9a-f]{32}$/', $data['request_id'] );
					self::assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );
					++$checked;
				}
			}
		}
		self::assertGreaterThan( 20, $checked );
	}

	/**
	 * CT-02 (foreign = invisible) and CT-03 (no technical text) on a logged-in call.
	 *
	 * @group CT-02
	 * @group CT-03
	 */
	public function test_CT_02_foreign_is_404_and_messages_are_catalog_texts(): void {
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$response = self::call( 'GET', '/course-captain/v1/exam-sessions/999999' );
		$data     = $response->get_data();
		self::assertSame( 404, $response->get_status() );
		self::assertSame( 'ccap_not_found', $data['code'] );
		self::assertSame( __( 'The requested item was not found.', 'course-captain' ), $data['detail'] );
		self::assertSame( $data['message'], $data['detail'] );
		self::assertStringNotContainsString( 'SELECT', (string) wp_json_encode( $data ) );
		self::assertStringNotContainsString( '.php', (string) wp_json_encode( $data ) );
	}

	/**
	 * Retry-After and details on 429 (rate limit carries bucket).
	 *
	 * @group GM-145
	 */
	public function test_rate_limit_problem(): void {
		$body = RestContract::problem(
			array(
				'code'    => 'ccap_rate_limited',
				'message' => 'Rate limit reached.',
				'data'    => array(
					'status'      => 429,
					'bucket'      => 'exam_start',
					'retry_after' => 17,
				),
			),
			429,
			'req_' . str_repeat( 'a', 32 )
		);
		self::assertSame( 17, $body['retry_after'] );
		self::assertSame( array( 'bucket' => 'exam_start' ), $body['details'] );
		self::assertSame( 'urn:course-captain:problem:rate-limited', $body['type'] );
	}

	/**
	 * §1.5: deprecated route answers with Deprecation, Sunset and Link.
	 */
	public function test_route_deprecation_headers(): void {
		RestContract::deprecate( '/course-captain/v1/me/courses', 1798761600, 1830297600, 'https://example.org/api' );
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$headers = self::call( 'GET', '/course-captain/v1/me/courses' )->get_headers();
		self::assertSame( '@1798761600', $headers['Deprecation'] );
		self::assertSame( 'Sat, 01 Jan 2028 00:00:00 GMT', $headers['Sunset'] );
		self::assertSame( '<https://example.org/api>; rel="deprecation"', $headers['Link'] );
	}

	/**
	 * CT-05: hook names of the public surface are fixed by snapshot; prefix rule (§1.2).
	 *
	 * @group CT-05
	 */
	public function test_CT_05_hook_snapshot(): void {
		$hooks = array();
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( __DIR__, 2 ) . '/src' ) );
		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			preg_match_all( "/\b(do_action|apply_filters)(?:_deprecated)?\(\s*'([a-z0-9_]+)'/", (string) file_get_contents( $file->getPathname() ), $m, PREG_SET_ORDER ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Source scan.
			foreach ( $m as $hit ) {
				if ( 0 === strpos( $hit[2], 'course_captain' ) ) {
					$hooks[ $hit[2] ] = 'do_action' === $hit[1] ? 'action' : 'filter';
				}
			}
		}
		ksort( $hooks );
		self::assertArrayHasKey( 'course_captain_loaded_v1', $hooks );
		self::assert_snapshot( 'hooks.v1.json', $hooks );
	}

	/**
	 * V-07/V-08: shortcodes use `ccap_` (E-285); legacy alias deprecated; blocks follow block.json contract.
	 */
	public function test_shortcodes_and_blocks(): void {
		global $shortcode_tags;
		foreach ( array_keys( $shortcode_tags ) as $tag ) {
			if ( ContentProtection::SHORTCODE_LEGACY === $tag || 0 !== strpos( (string) $tag, 'c' ) ) {
				continue;
			}
			if ( 0 === strpos( (string) $tag, 'course_captain' ) || 0 === strpos( (string) $tag, 'ccap' ) ) {
				self::assertStringStartsWith( 'ccap_', (string) $tag );
			}
		}
		self::assertTrue( shortcode_exists( 'ccap_protected' ) );
		$this->setExpectedDeprecated( '[course_captain_protected]' );
		do_shortcode( '[course_captain_protected course="0"]x[/course_captain_protected]' );

		foreach ( glob( dirname( __DIR__, 2 ) . '/blocks/*/block.json' ) as $file ) {
			$block = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Fixture.
			self::assertSame( 3, $block['apiVersion'], $file );
			self::assertStringStartsWith( 'course-captain/', $block['name'] );
			self::assertSame( 'course-captain', $block['textdomain'] );
			self::assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', (string) ( $block['version'] ?? '' ), $file . ' needs a version' );
			self::assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( $block['name'] ), $block['name'] );
		}
	}

	/**
	 * V-01: contract version and handshake functions.
	 */
	public function test_contract_version(): void {
		self::assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', course_captain_contract_version() );
		self::assertSame( COURSE_CAPTAIN_CONTRACT_VERSION, course_captain_contract_version() );
		self::assertFalse( course_captain_supports( 'unknown.feature' ) );
		self::assertTrue( course_captain_supports( 'certificates' ) );
	}
}
