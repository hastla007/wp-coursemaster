<?php
/**
 * Placeholder for withheld media embedded in the lesson text (R0-10 §2.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Access\RightsGate;

defined( 'ABSPATH' ) || exit;

/**
 * Protected attachments in the lesson text point to the checked file
 * endpoint (`/cc-file/<media_uid>/`, with plain permalinks
 * `?course_captain_file=<media_uid>`, see `ProtectedFiles::url()`). The
 * endpoint answers 404 for a withheld medium, so the image would stay
 * empty. This filter replaces such embeds in the rendered HTML:
 *
 * - `<img>`, `<video>`, `<audio>`, `<source>`, `<object>`, `<embed>`,
 *   `<iframe>` whose `src`/`data`/`srcset`/`poster` references a withheld
 *   medium become a neutral text placeholder ("Media not available",
 *   no path, no UID) for learners and the reason
 *   ({@see RightsGate::notice()}) for editors of the course;
 * - a link (`<a href>`) to a withheld medium loses the link, its text
 *   stays (learners) or is followed by the reason (editors).
 *
 * Media that are not withheld and URLs of other hosts or paths stay
 * untouched. The rendered HTML only (no change to the stored content).
 */
final class WithheldInlineMedia {

	/**
	 * Media UID in a URL of the checked file endpoint.
	 */
	private const UID = '~(?:/cc-file/|[?&](?:amp;|#0?38;)?course_captain_file=)([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})~i';

	/**
	 * Embedding elements (void or with closing tag).
	 */
	private const EMBEDS = 'img|video|audio|source|object|embed|iframe';

	/**
	 * Replace embeds of withheld media.
	 *
	 * @param string                    $html   Rendered lesson text.
	 * @param callable(string): ?string $reason Media UID → reason (`unknown`, `restricted`, `expired`) or null.
	 * @param bool                      $exempt Viewer edits the course (sees the reason).
	 * @return string
	 */
	public static function filter( string $html, callable $reason, bool $exempt ): string {
		if ( '' === $html || 1 !== preg_match( self::UID, $html ) ) {
			return $html;
		}
		$memo    = array();
		$verdict = static function ( string $fragment ) use ( $reason, &$memo ): ?string {
			if ( 0 === preg_match_all( self::UID, $fragment, $m ) ) {
				return null;
			}
			foreach ( array_unique( array_map( 'strtolower', $m[1] ) ) as $uid ) {
				if ( ! array_key_exists( $uid, $memo ) ) {
					$memo[ $uid ] = $reason( $uid );
				}
				if ( null !== $memo[ $uid ] ) {
					return (string) $memo[ $uid ];
				}
			}
			return null;
		};
		$text    = static fn( string $why ): string => $exempt ? RightsGate::notice( $why ) : RightsGate::placeholder();

		// Container elements first (video/audio/object/iframe with inner <source>/fallback), then void ones.
		$html = (string) preg_replace_callback(
			'~<(video|audio|object|iframe)\b[^>]*>.*?</\1\s*>~is',
			static function ( array $m ) use ( $verdict, $text ): string {
				$why = $verdict( $m[0] );
				return null === $why ? $m[0] : self::placeholder( $text( $why ) );
			},
			$html
		);
		$html = (string) preg_replace_callback(
			'~<(?:' . self::EMBEDS . ')\b[^>]*>~i',
			static function ( array $m ) use ( $verdict, $text ): string {
				$why = $verdict( $m[0] );
				return null === $why ? $m[0] : self::placeholder( $text( $why ) );
			},
			$html
		);
		return (string) preg_replace_callback(
			'~<a\b([^>]*)>(.*?)</a\s*>~is',
			static function ( array $m ) use ( $verdict, $text, $exempt ): string {
				if ( 1 !== preg_match( '~\bhref\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', $m[1], $href ) ) {
					return $m[0];
				}
				$why = $verdict( $href[1] );
				if ( null === $why ) {
					return $m[0];
				}
				return '<span class="ccap-withheld-link">' . $m[2] . '</span>' . ( $exempt && ! str_contains( $m[2], 'ccap-withheld-media' ) ? ' ' . self::placeholder( $text( $why ) ) : '' );
			},
			$html
		);
	}

	/**
	 * Placeholder markup (plain text in a span, inline-safe inside `<p>`/`<figure>`).
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function placeholder( string $text ): string {
		return '<span class="ccap-withheld ccap-withheld-media">' . esc_html( $text ) . '</span>';
	}
}
