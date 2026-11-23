<?php
/**
 * Shared building blocks of the authoring screens (R0-30 §1.4, R0-15).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Policy\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * Forms are plain admin-post forms (no JavaScript needed) with one nonce
 * per operation and object; result notices are text in a `status` or
 * `alert` region; the area navigation is a named `nav` with
 * `aria-current` (M-3, no ARIA tab widget).
 */
final class AdminUi {

	/**
	 * One admin-post form.
	 *
	 * @param string $action  admin-post action.
	 * @param string $op      Operation.
	 * @param int    $object_id  Object ID (part of the nonce).
	 * @param string $fields  Escaped inner HTML.
	 * @param string $button  Button label.
	 * @param string $primary Whether the button is primary ('1').
	 * @param string $attrs   Extra escaped form attributes.
	 * @return string
	 */
	public static function form( string $action, string $op, int $object_id, string $fields, string $button, string $primary = '', string $attrs = '' ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-admin-form"' . $attrs . '>'
			. '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">'
			. '<input type="hidden" name="op" value="' . esc_attr( $op ) . '">'
			. '<input type="hidden" name="object" value="' . (int) $object_id . '">'
			. wp_nonce_field( self::nonce_action( $action, $op, $object_id ), '_wpnonce', true, false )
			. $fields
			. '<p><button type="submit" class="button' . ( '1' === $primary ? ' button-primary' : '' ) . '">' . esc_html( $button ) . '</button></p></form>';
	}

	/**
	 * Form with several submit buttons (`name="op"`); the nonce covers the
	 * group `$nonce_op`, the handler checks that the op belongs to it.
	 *
	 * @param string $action   admin-post action.
	 * @param string $nonce_op Nonce group.
	 * @param int    $object_id   Object ID.
	 * @param string $fields   Escaped inner HTML incl. the buttons.
	 * @param string $attrs    Extra escaped form attributes.
	 * @return string
	 */
	public static function form_multi( string $action, string $nonce_op, int $object_id, string $fields, string $attrs = '' ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-admin-form"' . $attrs . '>'
			. '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">'
			. '<input type="hidden" name="nonce_op" value="' . esc_attr( $nonce_op ) . '">'
			. '<input type="hidden" name="object" value="' . (int) $object_id . '">'
			. wp_nonce_field( self::nonce_action( $action, $nonce_op, $object_id ), '_wpnonce', true, false )
			. $fields . '</form>';
	}

	/**
	 * Nonce action of a form.
	 *
	 * @param string $action admin-post action.
	 * @param string $op     Operation.
	 * @param int    $object_id Object.
	 * @return string
	 */
	public static function nonce_action( string $action, string $op, int $object_id ): string {
		return $action . '_' . $op . '_' . $object_id;
	}

	/**
	 * Verify the nonce of a submitted form (`nonce_op` for {@see form_multi()}).
	 *
	 * @param string               $action admin-post action.
	 * @param array<string, mixed> $post   Unslashed POST data.
	 * @return bool
	 */
	public static function verify( string $action, array $post ): bool {
		$op        = sanitize_key( (string) ( $post['nonce_op'] ?? $post['op'] ?? '' ) );
		$object_id = absint( $post['object'] ?? 0 );
		return false !== wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::nonce_action( $action, $op, $object_id ) );
	}

	/**
	 * Area navigation (M-3).
	 *
	 * @param string                $label   Accessible name ("{page}: areas").
	 * @param array<string, string> $areas   Area key → label (already filtered by caps, M-2).
	 * @param string                $current Current area.
	 * @param string                $slug    Page slug.
	 * @return string
	 */
	public static function areas( string $label, array $areas, string $current, string $slug ): string {
		if ( count( $areas ) < 2 ) {
			return '';
		}
		$html = '<nav class="ccap-areas" aria-label="' . esc_attr( $label ) . '"><ul class="subsubsub">';
		foreach ( $areas as $key => $text ) {
			$url   = add_query_arg(
				array(
					'page' => $slug,
					'area' => $key,
				),
				admin_url( 'admin.php' )
			);
			$html .= '<li><a href="' . esc_url( $url ) . '"' . ( $key === $current ? ' aria-current="page" class="current"' : '' ) . '>' . esc_html( $text ) . '</a> </li>';
		}
		return $html . '</ul></nav><div class="clear"></div>';
	}

	/**
	 * Result notice after a redirect.
	 *
	 * @param string                $code  Result code (`ok` = success).
	 * @param array<string, string> $texts Known error codes → text.
	 * @param string                $ok    Success text.
	 * @param string                $extra Escaped extra detail (e.g. field errors).
	 * @return string
	 */
	public static function notice( string $code, array $texts, string $ok, string $extra = '' ): string {
		if ( '' === $code ) {
			return '';
		}
		$common = array(
			'ccap_invalid_nonce'     => __( 'The form has expired. Reload the page and try again.', 'course-captain' ),
			'ccap_forbidden'         => __( 'You are not allowed to run this action.', 'course-captain' ),
			'ccap_not_found'         => __( 'The item was not found. It may have been removed.', 'course-captain' ),
			'ccap_validation_failed' => __( 'Not saved: please correct the marked entries.', 'course-captain' ),
			'ccap_invalid_param'     => __( 'Not saved: please check the entries.', 'course-captain' ),
		);
		if ( 'ok' === $code ) {
			return '<div class="notice notice-success" role="status"><p>' . esc_html( $ok ) . '</p>' . $extra . '</div>';
		}
		/* translators: %s: error code */
		$text = $texts[ $code ] ?? $common[ $code ] ?? sprintf( __( 'The action failed (%s).', 'course-captain' ), $code );
		return '<div class="notice notice-error" role="alert"><p>' . esc_html( $text ) . '</p>' . $extra . '</div>';
	}

	/**
	 * Courses the user may edit, by title.
	 *
	 * @param int $user_id User.
	 * @return list<\WP_Post>
	 */
	public static function courses( int $user_id ): array {
		$posts = get_posts(
			array(
				'post_type'   => Relations::COURSE_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		return array_values( array_filter( $posts, static fn( $p ): bool => $p instanceof \WP_Post && user_can( $user_id, 'edit_post', $p->ID ) ) );
	}

	/**
	 * Course select field.
	 *
	 * @param string $id       Field ID.
	 * @param string $name     Field name.
	 * @param int    $selected Selected course.
	 * @param int    $user_id  User.
	 * @param string $placeholder    Label of the empty option ('' = none).
	 * @return string
	 */
	public static function course_select( string $id, string $name, int $selected, int $user_id, string $placeholder = '' ): string {
		$html = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		if ( '' !== $placeholder ) {
			$html .= '<option value="0">' . esc_html( $placeholder ) . '</option>';
		}
		foreach ( self::courses( $user_id ) as $course ) {
			$html .= '<option value="' . (int) $course->ID . '"' . selected( $selected, (int) $course->ID, false ) . '>' . esc_html( $course->post_title ) . '</option>';
		}
		return $html . '</select>';
	}

	/**
	 * Percentage text of a ratio (e.g. 7/10 → "70").
	 *
	 * @param int $num Numerator.
	 * @param int $den Denominator.
	 * @return string
	 */
	public static function percent( int $num, int $den ): string {
		if ( $den <= 0 ) {
			return '';
		}
		$value = round( 100 * $num / $den, 2 );
		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Ratio from a percentage input (1–100, up to two decimals, comma or point).
	 *
	 * @param string $input Input.
	 * @return array{num: int, den: int}|null Null when invalid.
	 */
	public static function ratio( string $input ): ?array {
		$input = str_replace( ',', '.', trim( str_replace( '%', '', $input ) ) );
		if ( 1 !== preg_match( '/^\d{1,3}(\.\d{1,2})?$/', $input ) ) {
			return null;
		}
		$num = (int) round( (float) $input * 100 );
		$den = 10000;
		if ( $num <= 0 || $num > $den ) {
			return null;
		}
		$a = $num;
		$b = $den;
		while ( 0 !== $b ) {
			$t = $b;
			$b = $a % $b;
			$a = $t;
		}
		return array(
			'num' => intdiv( $num, $a ),
			'den' => intdiv( $den, $a ),
		);
	}

	/**
	 * Fresh idempotency key for a form.
	 *
	 * @return string
	 */
	public static function request_key(): string {
		return wp_generate_uuid4();
	}
}
