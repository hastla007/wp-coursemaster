<?php
/**
 * External media in the lesson view (E-45a, AR-01, AG-07 J-14; R0-10 §5.2, R0-31 MC-1…MC-8).
 *
 * `CCAP_DUMP_DIR=<dir>` writes the rendered click-to-load lessons for the
 * browser check `tools/qa/e2e/external-media.js`.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Activity\ExternalMedia;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

/**
 * Real tables; one course with a module and video lessons.
 *
 * @group AG-07
 * @group E-45a
 */
final class ExternalMediaViewTest extends SchemaTestCase {

	private const YOUTUBE = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
	private const VIMEO   = 'https://vimeo.com/76979871';
	private const FILE    = 'https://videos.example.test/clip.mp4';

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Module.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = $this->post( 'ccap_course', 'C-V' );
		$this->module  = $this->post( 'ccap_module', 'M-V' );
		CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $this->module );
		wp_set_current_user( $this->learner );
		wp_dequeue_script( 'ccap-external-media' );
	}

	/**
	 * Published post.
	 *
	 * @param string $type      Type.
	 * @param string $title     Title.
	 * @param int    $parent_id Parent.
	 * @return int
	 */
	private function post( string $type, string $title, int $parent_id = 0 ): int {
		return (int) wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_parent' => $parent_id,
				'post_author' => $this->admin,
			)
		);
	}

	/**
	 * Video lesson in the published course with an active grant; returns the rendered page.
	 *
	 * @param array<string, mixed> $settings Video settings.
	 * @return string
	 */
	private function page( array $settings ): string {
		$lesson = $this->post( 'ccap_lesson', 'Video lesson', $this->module );
		ActivityService::current()->define( Actor::user( $this->admin ), $lesson, 'video', $settings );
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );
		$db = Database::current();
		if ( null === $db->scalar( $db->prepare( 'SELECT 1 FROM `' . $db->table( 'ccap_grant' ) . '` WHERE user_id = %d', $this->learner ) ) ) {
			$db->insert(
				'ccap_grant',
				array(
					'uid'         => $db->uuid(),
					'user_id'     => $this->learner,
					'course_id'   => $this->course,
					'status'      => 'active',
					'source_type' => 'manual',
					'source_id'   => $db->uuid(),
				),
				array( 'created_at', 'updated_at' )
			);
		}
		PolicyService::flush();
		$uid = ( new ObjectIdentity( $db ) )->ensure( get_post( $this->course ) );
		return LearningArea::page( Actor::user( $this->learner ), 'kurs/' . $uid . '/lektion/' . $lesson );
	}

	/**
	 * Set privacy settings.
	 *
	 * @param array<string, mixed> $values Values.
	 * @return void
	 */
	private function privacy( array $values ): void {
		Settings::save( Actor::user( $this->admin ), 'privacy', $values, array_keys( $values ), Settings::rev( 'privacy' ) );
	}

	/**
	 * XPath of a page.
	 *
	 * @param string $html HTML.
	 * @return \DOMXPath
	 */
	private static function dom( string $html ): \DOMXPath {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( $html );
		libxml_clear_errors();
		return new \DOMXPath( $doc );
	}

	/**
	 * Elements that would make the browser contact a foreign host (MC-1a).
	 *
	 * @param \DOMXPath $xpath Page.
	 * @param string    $host  Foreign host part.
	 * @return list<string>
	 */
	private static function foreign_loads( \DOMXPath $xpath, string $host ): array {
		$found = array();
		foreach ( $xpath->query( '//iframe | //video | //audio | //track | //source | //img | //link[@rel="preconnect" or @rel="dns-prefetch" or @rel="preload" or @rel="prefetch"] | //*[@poster] | //script[@src]' ) as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}
			foreach ( array( 'src', 'href', 'poster', 'srcset' ) as $attr ) {
				if ( str_contains( $node->getAttribute( $attr ), $host ) ) {
					$found[] = $node->nodeName . '[' . $attr . ']'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				}
			}
			if ( in_array( $node->nodeName, array( 'iframe', 'video', 'track' ), true ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				$found[] = $node->nodeName; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			}
		}
		return $found;
	}

	/**
	 * Optional dump for the browser check.
	 *
	 * @param string $name File name.
	 * @param string $html HTML.
	 * @return void
	 */
	private static function dump( string $name, string $html ): void {
		$dir = (string) getenv( 'CCAP_DUMP_DIR' );
		if ( '' !== $dir ) {
			file_put_contents( $dir . '/' . $name, $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Browser check.
		}
	}

	/**
	 * R0-10 §5.1 / MC-2: privacy-friendly embed URLs for YouTube and Vimeo; other URLs are not embedded.
	 *
	 * @group MED-031
	 */
	public function test_embed_urls(): void {
		$nocookie = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';
		foreach ( array(
			self::YOUTUBE,
			'https://youtube.com/watch?v=dQw4w9WgXcQ&t=42',
			'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
			'https://youtu.be/dQw4w9WgXcQ',
			'https://www.youtube.com/embed/dQw4w9WgXcQ',
			'https://www.youtube.com/shorts/dQw4w9WgXcQ',
			$nocookie,
		) as $url ) {
			self::assertSame( $nocookie, ExternalMedia::embed_url( $url ), $url );
		}
		self::assertSame( 'https://player.vimeo.com/video/76979871?dnt=1', ExternalMedia::embed_url( self::VIMEO ) );
		self::assertSame( 'https://player.vimeo.com/video/76979871?dnt=1', ExternalMedia::embed_url( 'https://player.vimeo.com/video/76979871' ) );
		foreach ( array( 'https://www.youtube.com/watch?v=short', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ"><script>', 'https://www.youtube.com/channel/abc', 'https://vimeo.com/about', self::FILE, 'https://example.org/watch?v=dQw4w9WgXcQ' ) as $url ) {
			self::assertNull( ExternalMedia::embed_url( $url ), $url );
		}
		self::assertSame( 'YouTube', ExternalMedia::service( 'www.youtube.com' )['name'] );
		self::assertSame( 'Vimeo', ExternalMedia::service( 'vimeo.com' )['name'] );
		self::assertSame(
			array(
				'name'        => 'videos.example.test',
				'privacy_url' => null,
			),
			ExternalMedia::service( 'videos.example.test' )
		);
	}

	/**
	 * Default `always_load` (E-45a): YouTube and Vimeo are embedded directly (youtube-nocookie.com, Vimeo `dnt=1`), without JavaScript.
	 *
	 * @group MED-031
	 * @group PSD-022
	 */
	public function test_always_load_embeds_youtube_and_vimeo(): void {
		$xpath  = self::dom(
			$this->page(
				array(
					'provider' => 'youtube',
					'url'      => self::YOUTUBE,
				)
			)
		);
		$frames = $xpath->query( '//section[@aria-labelledby="ccap-activity-heading"]//iframe' );
		self::assertSame( 1, $frames->length );
		$frame = $frames->item( 0 );
		self::assertInstanceOf( \DOMElement::class, $frame );
		self::assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $frame->getAttribute( 'src' ) );
		self::assertSame( 'Video on YouTube', $frame->getAttribute( 'title' ), 'accessible name of the frame' );
		self::assertSame( 0, $xpath->query( '//*[contains(@src,"www.youtube.com")]' )->length, 'never the tracking domain' );
		self::assertSame( 0, $xpath->query( '//link[@rel="preconnect" or @rel="dns-prefetch"]' )->length );
		self::assertSame( 0, $xpath->query( '//*[@data-ccap-external-media]' )->length, 'no consent placeholder in always_load' );
		self::assertFalse( wp_script_is( 'ccap-external-media', 'enqueued' ), 'no script needed' );

		$vimeo = self::dom(
			$this->page(
				array(
					'provider' => 'vimeo',
					'url'      => self::VIMEO,
				)
			)
		);
		self::assertSame( 'https://player.vimeo.com/video/76979871?dnt=1', $vimeo->query( '//iframe' )->item( 0 )?->getAttribute( 'src' ) );
	}

	/**
	 * `click_to_load` without a consent tool (AG-07 J-14 Abweichung 1): the server delivers a placeholder
	 * without any connection to the foreign host, a "Load video" button (revealed by the script),
	 * the never pre-ticked "always load" box and, without JavaScript, a link to the service.
	 *
	 * @group MED-021
	 * @group MED-022
	 * @group PSD-023
	 * @group PSD-027
	 */
	public function test_click_to_load_placeholder_with_load_button(): void {
		$this->privacy( array( 'external_media_mode' => 'click_to_load' ) );
		$html  = $this->page(
			array(
				'provider' => 'youtube',
				'url'      => self::YOUTUBE,
			)
		);
		$xpath = self::dom( $html );
		self::assertSame( array(), self::foreign_loads( $xpath, 'youtube' ), 'MED-021: nothing connects to the service before the click' );

		$box = $xpath->query( '//div[@data-ccap-external-media]' )->item( 0 );
		self::assertInstanceOf( \DOMElement::class, $box );
		self::assertSame( 'embed', $box->getAttribute( 'data-ccap-kind' ) );
		self::assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $box->getAttribute( 'data-ccap-src' ), 'loaded only after the click' );
		self::assertSame( 'youtube', $box->getAttribute( 'data-ccap-service' ) );
		self::assertSame( 'marketing', $box->getAttribute( 'data-ccap-category' ), 'E-246 default category for the WP Consent API' );
		self::assertSame( '1', $box->getAttribute( 'data-ccap-remember' ) );

		$note = $xpath->query( '//p[@id="ccap-media-note"]' )->item( 0 );
		self::assertInstanceOf( \DOMElement::class, $note );
		self::assertStringContainsString( 'This video is hosted by YouTube and is only loaded after your consent.', $note->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
		self::assertStringContainsString( 'data such as your IP address is transferred to YouTube', $note->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
		self::assertSame( 'https://policies.google.com/privacy', $xpath->query( '//p[@id="ccap-media-note"]/a' )->item( 0 )?->getAttribute( 'href' ) );

		$button = $xpath->query( '//div[@class="ccap-media-load"]//button[@data-ccap-load]' )->item( 0 );
		self::assertInstanceOf( \DOMElement::class, $button );
		self::assertSame( 'Load video', trim( $button->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
		self::assertSame( 'button', $button->getAttribute( 'type' ), 'never submits a form' );
		self::assertSame( 'ccap-media-note', $button->getAttribute( 'aria-describedby' ), 'the hint describes the button' );
		self::assertTrue( $xpath->query( '//div[@class="ccap-media-load"]' )->item( 0 )?->hasAttribute( 'hidden' ), 'MC-7: without JavaScript no dead button' );

		$remember = $xpath->query( '//input[@id="ccap-media-remember"]' )->item( 0 );
		self::assertInstanceOf( \DOMElement::class, $remember );
		self::assertSame( 'checkbox', $remember->getAttribute( 'type' ) );
		self::assertFalse( $remember->hasAttribute( 'checked' ), 'MC-3: never pre-ticked' );
		self::assertSame( 1, $xpath->query( '//label[@for="ccap-media-remember"][.="Always load external media from YouTube"]' )->length );

		$link = $xpath->query( '//div[@data-ccap-external-media]//p[@class="ccap-media-link"]/a' )->item( 0 );
		self::assertInstanceOf( \DOMElement::class, $link );
		self::assertSame( self::YOUTUBE, $link->getAttribute( 'href' ), 'PSD-027: link without JavaScript' );
		self::assertSame( '_blank', $link->getAttribute( 'target' ) );
		self::assertSame( 1, $xpath->query( '//div[@data-ccap-external-media]//*[@role="status"]' )->length, 'live region for the load message' );
		self::assertTrue( wp_script_is( 'ccap-external-media', 'enqueued' ) );
		self::dump( 'external-media-youtube.html', $html );
	}

	/**
	 * `click_to_load` with a video file on a foreign host: no `<video>` and no `<track>` before the click,
	 * subtitles are attached only with the video (MED-022, T176).
	 *
	 * @group MED-021
	 * @group MED-022
	 */
	public function test_click_to_load_file_and_subtitles_only_after_click(): void {
		$this->privacy( array( 'external_media_mode' => 'click_to_load' ) );
		$html  = $this->page(
			array(
				'provider'         => 'url',
				'url'              => self::FILE,
				'subtitles'        => 'vtt',
				'subtitle_url'     => 'https://videos.example.test/clip.vtt',
				'duration_seconds' => 245,
			)
		);
		$xpath = self::dom( $html );
		self::assertSame( array(), self::foreign_loads( $xpath, 'videos.example.test' ) );
		$box = $xpath->query( '//div[@data-ccap-external-media]' )->item( 0 );
		self::assertInstanceOf( \DOMElement::class, $box );
		self::assertSame( 'file', $box->getAttribute( 'data-ccap-kind' ) );
		self::assertSame( self::FILE, $box->getAttribute( 'data-ccap-src' ) );
		self::assertSame( 'https://videos.example.test/clip.vtt', $box->getAttribute( 'data-ccap-subtitles' ) );
		self::assertSame( 'videos.example.test', $box->getAttribute( 'data-ccap-service' ) );
		self::assertStringContainsString( 'Length: 4:05', $html );
		self::assertSame( 0, $xpath->query( '//p[@id="ccap-media-note"]/a' )->length, 'no privacy link for an unknown host' );
		self::dump( 'external-media-file.html', $html );
	}

	/**
	 * `external_media_remember` off: no "always load" box, the stored browser choice is ignored (SET-114, MC-3).
	 *
	 * @group SET-114
	 * @group MED-023
	 */
	public function test_click_to_load_without_remember(): void {
		$this->privacy( array( 'external_media_mode' => 'click_to_load' ) );
		$this->privacy( array( 'external_media_remember' => false ) );
		$html  = $this->page(
			array(
				'provider' => 'vimeo',
				'url'      => self::VIMEO,
			)
		);
		$xpath = self::dom( $html );
		self::assertSame( 0, $xpath->query( '//input[@id="ccap-media-remember"]' )->length );
		self::assertSame( '0', $xpath->query( '//div[@data-ccap-external-media]' )->item( 0 )?->getAttribute( 'data-ccap-remember' ) );
		self::assertSame( 1, $xpath->query( '//button[@data-ccap-load]' )->length, 'the button stays' );
		self::assertSame( 'https://player.vimeo.com/video/76979871?dnt=1', $xpath->query( '//div[@data-ccap-external-media]' )->item( 0 )?->getAttribute( 'data-ccap-src' ) );
		self::dump( 'external-media-noremember.html', $html );
	}

	/**
	 * `link_only`: never embedded, no button, only the link; own host needs no consent.
	 *
	 * @group SET-114
	 */
	public function test_link_only_and_own_host(): void {
		$this->privacy( array( 'external_media_mode' => 'link_only' ) );
		$xpath = self::dom(
			$this->page(
				array(
					'provider' => 'youtube',
					'url'      => self::YOUTUBE,
				)
			)
		);
		self::assertSame( array(), self::foreign_loads( $xpath, 'youtube' ) );
		self::assertSame( 0, $xpath->query( '//button[@data-ccap-load]' )->length );
		self::assertSame( 'Watch the video on YouTube (opens in a new tab)', trim( (string) $xpath->query( '//p[@class="ccap-media-link"]/a' )->item( 0 )?->textContent ) );

		$this->privacy( array( 'external_media_mode' => 'click_to_load' ) );
		$own   = 'https://' . wp_parse_url( home_url(), PHP_URL_HOST ) . '/media/clip.mp4';
		$xpath = self::dom(
			$this->page(
				array(
					'provider' => 'url',
					'url'      => $own,
				)
			)
		);
		self::assertSame( $own, $xpath->query( '//video' )->item( 0 )?->getAttribute( 'src' ), 'own host loads directly' );
		self::assertSame( 0, $xpath->query( '//*[@data-ccap-external-media]' )->length );
	}
}
