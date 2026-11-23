<?php
/**
 * Bulk rights release without JavaScript (R0-10 §2.2, GM-127).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Two server-side steps over `admin-post.php` (works without JS):
 *
 * 1. the form posts batch and reason; the answer is a confirmation page
 *    that names the number of items, what stays (`restricted`, archived)
 *    and carries the confirmed count;
 * 2. the confirmation posts again with `_ccap_confirm`; the release runs
 *    only if the count still matches, then the browser returns to the
 *    page it came from with the result in the query.
 *
 * Without confirmation nothing changes; errors also return to the page.
 */
final class RightsReleaseForm {

	public const ACTION = 'ccap_rights_release';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * `admin-post.php` handler.
	 *
	 * @return void
	 */
	public static function handle(): void {
		check_admin_referer( self::ACTION );
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized field by field in respond().
		$out   = self::respond( Actor::user( get_current_user_id() ), is_array( $input ) ? $input : array() );
		if ( 'redirect' === $out['type'] ) {
			wp_safe_redirect( $out['body'] );
			exit;
		}
		nocache_headers();
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html__( 'Release unclear rights?', 'course-captain' ) . '</title></head><body class="wp-admin">' . $out['body'] . '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in page().
		exit;
	}

	/**
	 * Testable core.
	 *
	 * @param Actor                $actor Actor.
	 * @param array<string, mixed> $input Posted fields: `batch`, `reason`, `_ccap_return`, `_ccap_confirm`, `count`.
	 * @return array{type: string, body: string}
	 * @throws CourseException Never: errors become a redirect with the code.
	 */
	public static function respond( Actor $actor, array $input ): array {
		$batch  = absint( $input['batch'] ?? 0 );
		$reason = sanitize_textarea_field( (string) ( $input['reason'] ?? '' ) );
		$back   = wp_validate_redirect( esc_url_raw( (string) ( $input['_ccap_return'] ?? '' ) ), admin_url() );
		$rights = Rights::current();
		try {
			if ( empty( $input['_ccap_confirm'] ) ) {
				if ( '' === trim( $reason ) ) {
					throw new CourseException( 'ccap_validation_failed', 'reason', 422 );
				}
				return array(
					'type' => 'page',
					'body' => self::page( $batch, $reason, $back, $rights->release_preview( $actor, $batch ) ),
				);
			}
			$done = $rights->release( $actor, $batch, $reason, isset( $input['count'] ) ? (int) $input['count'] : null );
			return array(
				'type' => 'redirect',
				'body' => add_query_arg( 'ccap_rights_released', $done['released'], $back ),
			);
		} catch ( CourseException $e ) {
			return array(
				'type' => 'redirect',
				'body' => add_query_arg( 'ccap_rights_error', rawurlencode( $e->error_code ), $back ),
			);
		}
	}

	/**
	 * Confirmation page (focus on "Cancel", action button names the action).
	 *
	 * @param int                                                                        $batch   Batch.
	 * @param string                                                                     $reason  Reason.
	 * @param string                                                                     $back  Return URL.
	 * @param array{count: int, kept: array{restricted: int, archived: int, other: int}} $preview Preview.
	 * @return string
	 */
	public static function page( int $batch, string $reason, string $back, array $preview ): string {
		$count = $preview['count'];
		$html  = '<section class="ccap-confirm" aria-labelledby="ccap-confirm-title"><h2 id="ccap-confirm-title">' . esc_html__( 'Release unclear rights?', 'course-captain' ) . '</h2>';
		/* translators: %d: number of content items */
		$html .= '<p data-ccap-count="' . esc_attr( (string) $count ) . '">' . esc_html( sprintf( _n( '%d imported item with unclear rights will be marked as licensed and shown to learners.', '%d imported items with unclear rights will be marked as licensed and shown to learners.', $count, 'course-captain' ), $count ) ) . '</p>';
		/* translators: 1: number of restricted items, 2: number of archived items */
		$html .= '<p>' . esc_html( sprintf( __( 'Not changed: %1$d restricted, %2$d archived.', 'course-captain' ), $preview['kept']['restricted'], $preview['kept']['archived'] ) ) . '</p>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= wp_nonce_field( self::ACTION, '_wpnonce', true, false );
		foreach ( array(
			'action'        => self::ACTION,
			'_ccap_confirm' => '1',
			'batch'         => (string) $batch,
			'count'         => (string) $count,
			'reason'        => $reason,
			'_ccap_return'  => $back,
		) as $name => $value ) {
			$html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		$html .= '<a class="button" href="' . esc_url( $back ) . '" autofocus>' . esc_html__( 'Cancel', 'course-captain' ) . '</a> ';
		if ( $count > 0 ) {
			/* translators: %d: number of content items */
			$html .= '<button type="submit" class="button button-primary">' . esc_html( sprintf( _n( 'Release %d item', 'Release %d items', $count, 'course-captain' ), $count ) ) . '</button>';
		}
		return $html . '</form></section>';
	}
}
