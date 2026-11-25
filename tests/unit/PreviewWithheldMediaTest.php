<?php
/**
 * Unit test: the learner preview hides withheld embedded media like the learner view (R0-10 §2.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Admin\PreviewScreen;

/**
 * `PreviewScreen::learner_text()` always uses the learner mode of the filter.
 *
 * @group SET-CC-rechte-auslieferung
 * @group MED-018
 */
final class PreviewWithheldMediaTest extends TestCase {

	private const BAD = '11111111-2222-3333-4444-555555555555';
	private const OK  = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

	/**
	 * The editor of the preview sees exactly the learner placeholder, not
	 * the reason; released media and other links stay.
	 */
	public function test_preview_shows_learner_placeholder(): void {
		$asked  = array();
		$reason = static function ( string $uid ) use ( &$asked ): ?string {
			$asked[] = $uid;
			return self::BAD === $uid ? 'restricted' : null;
		};
		$html   = '<p><img src="https://example.test/cc-file/' . self::BAD . '/" alt="Foreign"> <img src="https://example.test/cc-file/' . self::OK . '/" alt="Own"></p>'
			. '<p><a href="https://example.test/?course_captain_file=' . self::BAD . '">Sheet</a> <a href="https://example.test/other/">Other</a></p>';

		$out = PreviewScreen::learner_text( $html, $reason );

		self::assertSame( 1, substr_count( $out, 'Media not available' ), $out );
		self::assertStringNotContainsString( self::BAD, $out );
		self::assertStringNotContainsString( 'Not shown to learners', $out, 'no editor reason inside the learner text' );
		self::assertStringContainsString( '<span class="ccap-withheld-link">Sheet</span>', $out );
		self::assertStringContainsString( self::OK, $out );
		self::assertStringContainsString( '<a href="https://example.test/other/">Other</a>', $out );
		self::assertSame( array( self::BAD, self::OK ), $asked );
	}

	/**
	 * Text without the file endpoint stays byte for byte.
	 */
	public function test_plain_text_is_unchanged(): void {
		$html = '<p>Plain <img src="https://example.test/wp-content/uploads/a.png" alt="A"></p>';
		self::assertSame( $html, PreviewScreen::learner_text( $html, static fn( string $uid ): ?string => '' === $uid ? null : 'unknown' ) );
	}
}
