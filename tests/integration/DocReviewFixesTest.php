<?php
/**
 * Regression tests for code findings of the R1-24 documentation review (B-1, B-3, B-6).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ExternalMedia;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Privacy\PrivacyService;

/**
 * Findings B-1, B-3, B-6.
 *
 * @group R1-24
 */
final class DocReviewFixesTest extends SchemaTestCase {

	/**
	 * Setup.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
	}

	/**
	 * B-1: `link_only` never embeds a foreign video; it offers a link instead.
	 */
	public function test_B1_link_only_is_not_always_load(): void {
		update_option( 'course_captain_settings_privacy', array( 'values' => array( 'external_media_mode' => 'link_only' ) ) );
		self::assertSame( ExternalMedia::LINK_ONLY, ExternalMedia::mode() );
		$view = ExternalMedia::video_view( array( 'url' => 'https://video.example.net/v/1' ) );
		self::assertNull( $view['video_url'], 'not embedded' );
		self::assertSame( 'https://video.example.net/v/1', $view['link_url'] );
		self::assertFalse( $view['consent_required'] );
		$own = ExternalMedia::video_view( array( 'url' => 'https://' . wp_parse_url( home_url(), PHP_URL_HOST ) . '/media/clip.mp4' ) );
		self::assertNotNull( $own['video_url'], 'own host still embedded' );
		update_option( 'course_captain_settings_privacy', array( 'values' => array( 'external_media_mode' => 'nonsense' ) ) );
		self::assertSame( ExternalMedia::LINK_ONLY, ExternalMedia::mode(), 'invalid foreign write rejected by the settings guard (R1-31 S-9)' );
	}

	/**
	 * B-3: the eraser removes the per-site notification preferences.
	 */
	public function test_B3_eraser_removes_notification_prefs(): void {
		$user = (int) self::factory()->user->create();
		update_user_option( $user, 'course_captain_notification_prefs', array( 'optout' => array( 'grading_completed' ) ) );
		self::assertGreaterThan( 0, PrivacyService::erase_user( $user ) );
		self::assertFalse( get_user_option( 'course_captain_notification_prefs', $user ) );
	}

	/**
	 * B-6: authoring routes pass error details (`data`) through.
	 */
	public function test_B6_error_details_passed_through(): void {
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$route = new \ReflectionMethod( \CourseCaptain\Admin\AuthoringRoutes::class, 'run' );
		$route->setAccessible( true );
		$error = $route->invoke(
			null,
			static function (): void {
				throw new \CourseCaptain\Course\CourseException( 'ccap_rate_limited', 'x', 429, array( 'retry_after' => 9 ) );
			}
		);
		self::assertInstanceOf( \WP_Error::class, $error );
		self::assertSame( 9, $error->get_error_data()['retry_after'] );
	}
}
