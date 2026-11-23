<?php
/**
 * "Decorative" switch for images in the block editor (UI-F8, E-349,
 * R0-22 VAL-140/141, A11Y-110).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Publish;

use CourseCaptain\Policy\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * The image block of courses, modules and lessons gets a toggle
 * "Decorative image" (`assets/js/image-decorative.js`, no build step). It
 * stores the block attribute `ccapDecorative` and empties the alternative
 * text. The saved block markup stays the one of the core block (no block
 * validation issues); the marker lives in the block comment:
 *
 * - the course check reads it ({@see self::mark()} before VAL-140 counts
 *   images): an empty `alt` inside such a block counts as decorative;
 * - the front end renders the image with `alt=""` and
 *   `data-ccap-decorative="1"` (E-349 HTML marker).
 *
 * A described image (non-empty alt) always counts as described; an image
 * the question needs (VAL-141) is never satisfied by "decorative".
 */
final class DecorativeImages {

	public const ATTRIBUTE = 'ccapDecorative';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue' ) );
		add_filter( 'register_block_type_args', array( self::class, 'block_args' ), 10, 2 );
		add_filter( 'render_block_core/image', array( self::class, 'render' ), 10, 2 );
	}

	/**
	 * Editor script for the course content types.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || ! in_array( $screen->post_type, array( Relations::COURSE_TYPE, Relations::MODULE_TYPE, Relations::LESSON_TYPE ), true ) ) {
			return;
		}
		wp_enqueue_script( 'ccap-image-decorative', plugins_url( 'assets/js/image-decorative.js', COURSE_CAPTAIN_FILE ), array( 'wp-hooks', 'wp-blocks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components' ), COURSE_CAPTAIN_VERSION, true );
		wp_localize_script(
			'ccap-image-decorative',
			'ccapImageDecorative',
			array(
				'attribute' => self::ATTRIBUTE,
				'panel'     => __( 'Accessibility', 'course-captain' ),
				'label'     => __( 'Decorative image', 'course-captain' ),
				'help'      => __( 'Only for images that carry no information. Screen readers skip them, so the alternative text stays empty. Images a question needs must be described.', 'course-captain' ),
			)
		);
	}

	/**
	 * Register the attribute on the core image block (server side, so the
	 * REST block renderer and `parse_blocks()` consumers know it).
	 *
	 * @param array<string, mixed> $args Block type arguments.
	 * @param string               $name Block name.
	 * @return array<string, mixed>
	 */
	public static function block_args( $args, $name ) {
		if ( 'core/image' === $name && is_array( $args ) ) {
			$args['attributes']                    = (array) ( $args['attributes'] ?? array() );
			$args['attributes'][ self::ATTRIBUTE ] = array(
				'type'    => 'boolean',
				'default' => false,
			);
		}
		return $args;
	}

	/**
	 * Front end: decorative images get `alt=""` and the HTML marker.
	 *
	 * @param string               $html  Rendered block.
	 * @param array<string, mixed> $block Parsed block.
	 * @return string
	 */
	public static function render( $html, $block ) {
		if ( ! is_string( $html ) || ! is_array( $block ) || empty( $block['attrs'][ self::ATTRIBUTE ] ) || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( array( 'tag_name' => 'img' ) ) ) {
			$alt = $tags->get_attribute( 'alt' );
			if ( ! is_string( $alt ) || '' === trim( $alt ) ) {
				$tags->set_attribute( 'alt', '' );
				$tags->set_attribute( 'data-ccap-decorative', '1' );
			}
		}
		return $tags->get_updated_html();
	}

	/**
	 * Mark the images of decorative image blocks in stored content, so the
	 * alt-text check sees the marker (empty alt only; a described image
	 * stays described).
	 *
	 * @param string $html Stored post content.
	 * @return string
	 */
	public static function mark( string $html ): string {
		if ( false === strpos( $html, '"' . self::ATTRIBUTE . '":true' ) ) {
			return $html;
		}
		return (string) preg_replace_callback(
			'#<!--\s+wp:image\s+(\{.*?\})\s+/?-->(.*?)<!--\s+/wp:image\s+-->#s',
			static function ( array $m ): string {
				$attrs = json_decode( $m[1], true );
				if ( ! is_array( $attrs ) || true !== ( $attrs[ self::ATTRIBUTE ] ?? false ) ) {
					return $m[0];
				}
				$inner = (string) preg_replace_callback(
					'/<img\b[^>]*>/i',
					static function ( array $img ): string {
						$tag = $img[0];
						if ( 1 === preg_match( '/\salt\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $alt ) && '' !== trim( html_entity_decode( $alt[1] . ( $alt[2] ?? '' ), ENT_QUOTES, 'UTF-8' ) ) ) {
							return $tag;
						}
						if ( 1 !== preg_match( '/\salt\s*=/i', $tag ) ) {
							$tag = (string) preg_replace( '/^<img\b/i', '<img alt=""', $tag );
						}
						return (string) preg_replace( '/^<img\b/i', '<img data-ccap-decorative="1"', $tag );
					},
					$m[2]
				);
				return str_replace( $m[2], $inner, $m[0] );
			},
			$html
		);
	}
}
