<?php
/**
 * Unit tests of the placeholder for withheld media in the lesson text (R0-10 §2.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Learner\WithheldInlineMedia;

/**
 * `WithheldInlineMedia::filter()` only works on the HTML and the resolver.
 *
 * @group SET-CC-rechte-auslieferung
 */
final class WithheldInlineMediaTest extends TestCase {

	private const BAD = '11111111-2222-3333-4444-555555555555';
	private const OK  = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

	/**
	 * Resolver: BAD is withheld (`unknown`), everything else not.
	 *
	 * @param string[] $asked Collects the asked UIDs.
	 * @return callable(string): ?string
	 */
	private static function resolver( array &$asked ): callable {
		return static function ( string $uid ) use ( &$asked ): ?string {
			$asked[] = $uid;
			return self::BAD === $uid ? 'unknown' : null;
		};
	}

	/**
	 * Images, links and players of a withheld medium become the neutral
	 * placeholder; other media and URLs stay; no UID or path remains.
	 */
	public function test_learner_sees_placeholder_without_path(): void {
		$asked = array();
		$html  = '<p>Text <img class="wp-image-5" src="https://example.test/cc-file/' . self::BAD . '/" alt="Chart"> and <img src="https://example.test/cc-file/' . self::OK . '/" alt="Own"></p>'
			. '<figure><a href="https://example.test/?course_captain_file=' . self::BAD . '"><img src="https://example.test/?course_captain_file=' . self::BAD . '" /></a></figure>'
			. '<p><a href="https://example.test/cc-file/' . self::BAD . '/">Handout PDF</a> <a href="https://example.test/other/">Other</a></p>'
			. '<video controls><source src="https://example.test/cc-file/' . self::BAD . '/" type="video/mp4">Fallback</video>'
			. '<img srcset="https://example.test/?a=1&#038;course_captain_file=' . self::BAD . ' 2x" src="https://example.test/plain.png" alt="">';

		$out = WithheldInlineMedia::filter( $html, self::resolver( $asked ), false );

		self::assertStringNotContainsString( self::BAD, $out, 'no UID of the withheld medium' );
		self::assertSame( 4, substr_count( $out, 'Media not available' ), 'image, linked image, video, srcset image' . "\n" . $out );
		self::assertStringContainsString( '<img src="https://example.test/cc-file/' . self::OK . '/" alt="Own">', $out, 'released medium stays' );
		self::assertStringContainsString( '<span class="ccap-withheld-link">Handout PDF</span>', $out, 'the link text stays, the link goes' );
		self::assertStringContainsString( '<a href="https://example.test/other/">Other</a>', $out );
		self::assertStringNotContainsString( 'Fallback', $out );
		self::assertStringNotContainsString( 'not shown to learners', strtolower( $out ) );
		self::assertSame( array( self::BAD, self::OK ), $asked, 'each UID is resolved once' );
	}

	/**
	 * Editors of the course see the reason instead of the placeholder, once per embed.
	 */
	public function test_editor_sees_reason(): void {
		$asked = array();
		$html  = '<p><a href="/cc-file/' . self::BAD . '/"><img src="/cc-file/' . self::BAD . '/" alt=""></a></p><p><a href="/cc-file/' . self::BAD . '/">PDF</a></p>';

		$out = WithheldInlineMedia::filter( $html, self::resolver( $asked ), true );

		self::assertSame( 2, substr_count( $out, 'Not shown to learners: the rights of this imported content are unclear.' ), $out );
		self::assertStringNotContainsString( 'Media not available', $out );
		self::assertStringNotContainsString( self::BAD, $out );
	}

	/**
	 * Without a file endpoint URL the resolver is never asked; the HTML stays byte for byte.
	 */
	public function test_text_without_protected_media_is_unchanged(): void {
		$asked = array();
		$html  = '<p>Plain <img src="https://example.test/wp-content/uploads/a.png" alt="A"> <a href="https://example.test/cc-files/x/">x</a></p>';

		self::assertSame( $html, WithheldInlineMedia::filter( $html, self::resolver( $asked ), false ) );
		self::assertSame( array(), $asked );
		self::assertSame( '', WithheldInlineMedia::filter( '', self::resolver( $asked ), false ) );
	}
}
