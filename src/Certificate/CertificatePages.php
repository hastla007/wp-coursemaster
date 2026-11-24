<?php
/**
 * Certificate pages: holder view/print (L-15), public verification (L-16),
 * block `course-captain/certificate-verify` (R0-29 CF-05…CF-07).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Certificate;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Server-rendered, no JavaScript, no server-side PDF (CR-6): print CSS
 * for one A4/Letter page, text instead of images (A11Y-096), the
 * verification link as text, certificate number and check code.
 * Verification pages are `noindex`, `no-store` and rate limited; the
 * holder pages need a login. Paths live below the learning area:
 * `/<area>/nachweise/`, `/<area>/nachweise/<uid>`, `/<area>/nachweis/<token>`.
 */
final class CertificatePages {

	public const LIST_PATH   = 'nachweise';
	public const VERIFY_PATH = 'nachweis';

	/**
	 * Hooks (before the learning area, which requires a login).
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'serve' ), 4 );
		add_action( 'init', array( self::class, 'block' ) );
	}

	/**
	 * Path of the verification pages (snapshot `verify.path_at_issue`).
	 *
	 * @return string
	 */
	public static function verify_path(): string {
		return '/' . LearningArea::slug() . '/' . self::VERIFY_PATH . '/';
	}

	/**
	 * Public verification URL.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function verify_url( string $token ): string {
		return LearningArea::url( self::VERIFY_PATH . '/' . $token );
	}

	/**
	 * Holder view URL (`view_url` of R-40).
	 *
	 * @param string $uid Certificate UID.
	 * @return string
	 */
	public static function view_url( string $uid ): string {
		return LearningArea::url( self::LIST_PATH . '/' . $uid );
	}

	/**
	 * `template_redirect`.
	 *
	 * @return void
	 */
	public static function serve(): void {
		$path = get_query_var( LearningArea::QUERY_VAR, null );
		if ( ! is_string( $path ) ) {
			return;
		}
		$path = trim( $path, '/' );
		if ( 1 === preg_match( '#^' . self::VERIFY_PATH . '/([0-9a-zA-Z]{1,64})$#', $path, $m ) ) {
			$page = self::verification_page( $m[1], self::client() );
		} elseif ( \CourseCaptain\Settings\Modules::enabled( \CourseCaptain\Settings\Modules::CERTIFICATES ) && 1 === preg_match( '#^' . self::LIST_PATH . '(?:/([0-9a-f-]{36}))?$#', $path, $m ) ) {
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( wp_login_url( LearningArea::url( $path ) ) );
				exit;
			}
			$actor = Actor::user( get_current_user_id() );
			$page  = isset( $m[1] ) ? self::certificate_page( $actor, $m[1] ) : self::list_page( $actor );
		} else {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: no-store' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		// No referrer: the verification token is part of the URL (R0-17 T-33).
		header( 'Referrer-Policy: no-referrer' );
		status_header( $page['status'] );
		echo $page['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while building.
		exit;
	}

	/**
	 * Public verification page (L-16).
	 *
	 * @param string $token  Token.
	 * @param string $client Client key for the rate limit.
	 * @return array{status: int, html: string}
	 */
	public static function verification_page( string $token, string $client ): array {
		$title = __( 'Certificate verification', 'course-captain' );
		try {
			$service = CertificateService::current();
			$service->rate_limit( $client );
			$body   = self::verification_html( $service->verify( $token ), true );
			$status = 200;
		} catch ( CourseException $e ) {
			$status = $e->status;
			$body   = '<p role="alert">' . esc_html( self::error_text( $e->error_code ) ) . '</p>';
		}
		return array(
			'status' => $status,
			'html'   => self::document( $title, $body ),
		);
	}

	/**
	 * Holder list (L-15).
	 *
	 * @param Actor $actor Learner.
	 * @return array{status: int, html: string}
	 */
	public static function list_page( Actor $actor ): array {
		$title = __( 'My certificates', 'course-captain' );
		try {
			$items  = CertificateService::current()->mine( $actor );
			$status = 200;
			if ( array() === $items ) {
				$body = '<p>' . esc_html__( 'You do not have any certificates yet.', 'course-captain' ) . '</p>';
			} else {
				$body = '<ul class="ccap-certificates">';
				foreach ( $items as $item ) {
					$body .= '<li><a href="' . esc_url( (string) $item['view_url'] ) . '" dir="auto">' . esc_html( (string) $item['course_title'] ) . '</a> – ' . esc_html( self::status_text( (string) $item['status'] ) );
					if ( ! empty( $item['recertify'] ) && 'revoked' !== $item['status'] ) {
						$body .= ' – ' . esc_html__( 'Recertification possible', 'course-captain' );
					}
					$body .= '</li>';
				}
				$body .= '</ul>';
			}
		} catch ( CourseException $e ) {
			$status = $e->status;
			$body   = '<p role="alert">' . esc_html( self::error_text( $e->error_code ) ) . '</p>';
		}
		return array(
			'status' => $status,
			'html'   => self::document( $title, $body ),
		);
	}

	/**
	 * Holder view and print page (CF-06).
	 *
	 * @param Actor  $actor Holder or manager.
	 * @param string $uid   Certificate UID.
	 * @return array{status: int, html: string}
	 */
	public static function certificate_page( Actor $actor, string $uid ): array {
		$paper = 'a4';
		try {
			$view   = CertificateService::current()->view( $actor, $uid );
			$paper  = (string) ( $view['snapshot']['layout']['paper'] ?? 'a4' );
			$title  = (string) ( $view['snapshot']['texts']['heading'] ?? __( 'Certificate', 'course-captain' ) );
			$body   = self::render( $view['snapshot'], $view['status'], $view['verify_url'], (string) $view['row']['snapshot_hash'] );
			$body  .= '<p class="ccap-noprint"><a href="' . esc_url( LearningArea::url( self::LIST_PATH ) ) . '">' . esc_html__( 'Back to my certificates', 'course-captain' ) . '</a></p>';
			$status = 200;
		} catch ( CourseException $e ) {
			$title  = __( 'Certificate', 'course-captain' );
			$status = $e->status;
			$body   = '<p role="alert">' . esc_html( self::error_text( $e->error_code ) ) . '</p>';
		}
		return array(
			'status' => $status,
			'html'   => self::document( $title, $body, false, $paper ),
		);
	}

	/**
	 * Render a certificate from its snapshot. `kind = base` is rendered
	 * exactly; other kinds use a registered renderer (slot
	 * `certificateRenderers`) or the fallback in the base layout (CR-4).
	 *
	 * @param array<string, mixed> $snapshot   Snapshot.
	 * @param string               $status     Display status.
	 * @param string               $verify_url Verification URL.
	 * @param string               $hash       Stored `snapshot_hash`.
	 * @return string
	 */
	public static function render( array $snapshot, string $status, string $verify_url, string $hash ): string {
		$kind = (string) ( $snapshot['template']['kind'] ?? 'base' );
		$note = '';
		if ( 'base' !== $kind ) {
			/**
			 * Renderers per template kind (slot `certificateRenderers`, R0-29 Δ-3).
			 *
			 * @since 1.0.0
			 * @param array<string, callable> $renderers Kind => fn(array $snapshot, string $status, string $verify_url): string.
			 */
			$renderers = (array) apply_filters( 'course_captain_certificate_renderers', array() );
			if ( isset( $renderers[ $kind ] ) && is_callable( $renderers[ $kind ] ) ) {
				return (string) call_user_func( $renderers[ $kind ], $snapshot, $status, $verify_url );
			}
			/**
			 * Diagnostic entry: a certificate was rendered in the standard layout.
			 *
			 * @since 1.0.0
			 * @param string $kind Template kind without renderer.
			 * @param string $uid  Certificate UID.
			 */
			do_action( 'course_captain_certificate_fallback_rendered', $kind, (string) ( $snapshot['certificate_uid'] ?? '' ) );
			$note = '<p class="ccap-fallback">' . esc_html__( 'Shown in the standard layout.', 'course-captain' ) . '</p>';
		}
		// SET-CC-08: certificates issued with a language setting show their fixed labels in that language; older ones stay as they were.
		$locale   = isset( $snapshot['layout'] ) ? (string) ( $snapshot['locale']['ui'] ?? '' ) : '';
		$switched = '' !== $locale && determine_locale() !== $locale && in_array( $locale, \CourseCaptain\Kernel\Locale::installed(), true ) && switch_to_locale( $locale );
		try {
			$html = self::article( $snapshot, $status, $verify_url, $hash, $note );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
		return $html . '<p class="ccap-noprint"><button type="button" onclick="window.print()">' . esc_html__( 'Print', 'course-captain' ) . '</button></p>';
	}

	/**
	 * The certificate itself (base layout).
	 *
	 * @param array<string, mixed> $snapshot   Snapshot.
	 * @param string               $status     Display status.
	 * @param string               $verify_url Verification URL.
	 * @param string               $hash       Stored `snapshot_hash`.
	 * @param string               $note       Fallback note.
	 * @return string
	 */
	private static function article( array $snapshot, string $status, string $verify_url, string $hash, string $note ): string {
		$issuer = (array) ( $snapshot['issuer'] ?? array() );
		$texts  = (array) ( $snapshot['texts'] ?? array() );
		$html   = '<article class="ccap-certificate" lang="' . esc_attr( (string) ( $snapshot['locale']['content'] ?? '' ) ) . '">' . $note;
		$html  .= '<p class="ccap-status"><strong>' . esc_html__( 'Status:', 'course-captain' ) . '</strong> ' . esc_html( self::status_text( $status ) ) . '</p>';
		$html  .= '<p class="ccap-issuer" dir="auto">' . esc_html( (string) ( $issuer['name'] ?? '' ) ) . '</p>';
		if ( isset( $issuer['logo'] ) && '' !== (string) ( $issuer['logo']['alt'] ?? '' ) ) {
			$html .= '<p class="ccap-logo-text">' . esc_html( (string) $issuer['logo']['alt'] ) . '</p>';
		}
		$html .= '<h1 class="ccap-heading" dir="auto">' . esc_html( (string) ( $texts['heading'] ?? '' ) ) . '</h1>';
		$html .= '<p class="ccap-holder" dir="auto">' . esc_html( (string) ( $snapshot['holder']['display_name'] ?? '' ) ) . '</p>';
		$html .= '<p dir="auto">' . esc_html( (string) ( $texts['body'] ?? '' ) ) . '</p>';
		$html .= '<p class="ccap-course" dir="auto">' . esc_html( (string) ( $snapshot['course']['title'] ?? '' ) ) . '</p>';
		$html .= '<dl class="ccap-facts"><dt>' . esc_html__( 'Issued on', 'course-captain' ) . '</dt><dd>' . esc_html( CertificateService::site_date( gmdate( 'Y-m-d H:i:s', (int) strtotime( (string) ( $snapshot['issued_at'] ?? '' ) ) ) ) ) . '</dd>';
		if ( null !== ( $snapshot['valid_until'] ?? null ) ) {
			$html .= '<dt>' . esc_html__( 'Valid until', 'course-captain' ) . '</dt><dd>' . esc_html( (string) $snapshot['valid_until'] ) . '</dd>';
		}
		foreach ( (array) ( $snapshot['fields'] ?? array() ) as $field ) {
			$field = (array) $field;
			$html .= '<dt dir="auto">' . esc_html( (string) ( $field['label'] ?? $field['key'] ?? '' ) ) . '</dt><dd dir="auto">' . esc_html( (string) ( $field['value'] ?? '' ) ) . '</dd>';
		}
		$html      .= '<dt>' . esc_html__( 'Certificate number', 'course-captain' ) . '</dt><dd>' . esc_html( (string) ( $snapshot['display_id'] ?? '' ) ) . '</dd>';
		$html      .= '<dt>' . esc_html__( 'Check code', 'course-captain' ) . '</dt><dd>' . esc_html( CertificateSnapshot::check_code( $hash ) ) . '</dd></dl>';
		$objectives = (array) ( $snapshot['objectives'] ?? array() );
		if ( array() !== $objectives ) {
			$html .= '<h2>' . esc_html__( 'Learning objectives', 'course-captain' ) . '</h2><ul>';
			foreach ( $objectives as $objective ) {
				$html .= '<li dir="auto">' . esc_html( (string) ( ( (array) $objective )['title'] ?? '' ) ) . '</li>';
			}
			$html .= '</ul>';
		}
		foreach ( (array) ( $snapshot['assets'] ?? array() ) as $asset ) {
			$alt = (string) ( ( (array) $asset )['alt'] ?? '' );
			if ( '' !== $alt ) {
				$html .= '<p class="ccap-asset-text">' . esc_html( $alt ) . '</p>';
			}
		}
		if ( '' !== (string) ( $issuer['signature_label'] ?? '' ) ) {
			$html .= '<p class="ccap-signature" dir="auto">' . esc_html( (string) $issuer['signature_label'] ) . '</p>';
		}
		if ( '' !== (string) ( $texts['footer'] ?? '' ) ) {
			$html .= '<p class="ccap-footer" dir="auto">' . esc_html( (string) $texts['footer'] ) . '</p>';
		}
		$html .= '<p class="ccap-verify">' . esc_html__( 'Verify this certificate at:', 'course-captain' ) . ' <a href="' . esc_url( $verify_url ) . '">' . esc_html( $verify_url ) . '</a></p>';
		return $html . '</article>';
	}

	/**
	 * Verification result as HTML (fields per R0-29 §2.5).
	 *
	 * @param array<string, mixed> $result     Result of {@see CertificateService::verify()}.
	 * @param bool                 $show_name  Name allowed by the caller (block attribute, only restrictive).
	 * @return string
	 */
	public static function verification_html( array $result, bool $show_name ): string {
		$status = (string) $result['status'];
		if ( 'revoked' === $status ) {
			/* translators: %s: date */
			return '<p class="ccap-status">' . esc_html( sprintf( __( 'This certificate was revoked on %s.', 'course-captain' ), (string) $result['revoked_on'] ) ) . '</p>';
		}
		if ( 'corrected' === $status ) {
			/* translators: %s: date */
			return '<p class="ccap-status">' . esc_html( sprintf( __( 'This version was replaced by the version of %s.', 'course-captain' ), (string) $result['superseded_on'] ) ) . '</p>';
		}
		$html = '<p class="ccap-status"><strong>' . esc_html( self::status_text( $status ) ) . '</strong>';
		if ( 'expired' === $status ) {
			/* translators: %s: date */
			$html .= ' ' . esc_html( sprintf( __( 'Validity ended on %s.', 'course-captain' ), (string) $result['valid_until'] ) );
		}
		$html .= '</p><dl class="ccap-facts">';
		$rows  = array(
			__( 'Issuer', 'course-captain' )             => (string) $result['issuer'],
			__( 'Course', 'course-captain' )             => (string) $result['course_title'],
			__( 'Issued on', 'course-captain' )          => (string) $result['issued_on'],
			__( 'Valid until', 'course-captain' )        => null === $result['valid_until'] ? __( 'unlimited', 'course-captain' ) : (string) $result['valid_until'],
			__( 'Certificate number', 'course-captain' ) => (string) $result['display_id'],
			__( 'Check code', 'course-captain' )         => (string) $result['check_code'],
		);
		if ( $show_name && isset( $result['holder_name'] ) ) {
			$rows = array( __( 'Name', 'course-captain' ) => (string) $result['holder_name'] ) + $rows;
		}
		foreach ( $rows as $label => $value ) {
			$html .= '<dt>' . esc_html( $label ) . '</dt><dd dir="auto">' . esc_html( $value ) . '</dd>';
		}
		$html .= '</dl>';
		if ( array() !== (array) $result['objectives'] ) {
			$html .= '<h2>' . esc_html__( 'Learning objectives', 'course-captain' ) . '</h2><ul>';
			foreach ( (array) $result['objectives'] as $objective ) {
				$html .= '<li dir="auto">' . esc_html( (string) $objective ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html;
	}

	/**
	 * Block registration.
	 *
	 * @return void
	 */
	public static function block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		\CourseCaptain\Ops\Compatibility::register_block( dirname( __DIR__, 2 ) . '/blocks/certificate-verify', 'course-captain/certificate-verify', array( 'render_callback' => array( self::class, 'render_block' ) ) );
	}

	/**
	 * `course-captain/certificate-verify`: form plus result (GET `ccap_certificate`).
	 *
	 * @param mixed $attributes Attributes.
	 * @return string
	 */
	public static function render_block( $attributes ): string {
		$attributes = (array) $attributes;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only lookup.
		$token = isset( $_GET['ccap_certificate'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['ccap_certificate'] ) ) : '';
		$html  = '<form class="ccap-verify-form" method="get"><label for="ccap-certificate-token">' . esc_html__( 'Verification code', 'course-captain' ) . '</label> <input id="ccap-certificate-token" name="ccap_certificate" type="text" value="' . esc_attr( $token ) . '" autocomplete="off"> <button type="submit">' . esc_html__( 'Verify', 'course-captain' ) . '</button></form>';
		if ( '' === $token ) {
			return $html;
		}
		\CourseCaptain\Access\ContentProtection::mark_uncacheable();
		try {
			$service = CertificateService::current();
			$service->rate_limit( self::client() );
			$html .= '<div class="ccap-verify-result" role="status">' . self::verification_html( $service->verify( $token ), false !== ( $attributes['showHolderName'] ?? true ) ) . '</div>';
		} catch ( CourseException $e ) {
			$html .= '<p class="ccap-verify-result" role="alert">' . esc_html( self::error_text( $e->error_code ) ) . '</p>';
		}
		return $html;
	}

	/**
	 * Status as text.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_text( string $status ): string {
		$labels = array(
			'valid'     => __( 'Valid', 'course-captain' ),
			'expired'   => __( 'Issued validly, validity expired', 'course-captain' ),
			'corrected' => __( 'Replaced by a corrected version', 'course-captain' ),
			'revoked'   => __( 'Revoked', 'course-captain' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Error as text (no oracle for unknown tokens).
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	private static function error_text( string $code ): string {
		$labels = array(
			'ccap_not_found'               => __( 'No certificate was found for this code.', 'course-captain' ),
			'ccap_rate_limited'            => __( 'Too many requests. Please try again in a minute.', 'course-captain' ),
			'ccap_temporarily_unavailable' => __( 'Verification is temporarily unavailable.', 'course-captain' ),
			'ccap_account_locked'          => __( 'Your account is locked.', 'course-captain' ),
		);
		return $labels[ $code ] ?? __( 'This certificate is not available.', 'course-captain' );
	}

	/**
	 * Rate-limit client key.
	 *
	 * @return string
	 */
	private static function client(): string {
		return \CourseCaptain\Security\ClientIp::rate_key();
	}

	/**
	 * Standalone document with print styles (one page; SET-CC-08 paper format
	 * from the snapshot, A4 for certificates issued before).
	 *
	 * @param string $title   Title.
	 * @param string $body    Body HTML.
	 * @param bool   $heading Print the title as `h1`.
	 * @param string $paper   `a4` or `letter`.
	 * @return string
	 */
	private static function document( string $title, string $body, bool $heading = true, string $paper = 'a4' ): string {
		$size = 'letter' === $paper ? 'letter' : 'A4';
		$css  = 'body{margin:0;font:1rem/1.5 system-ui,sans-serif;overflow-wrap:anywhere}main{max-width:48rem;margin:0 auto;padding:1rem;box-sizing:border-box}'
			. '.ccap-certificate{border:2px solid #333;padding:1.5rem}.ccap-heading{font-size:1.75rem}.ccap-holder{font-size:1.5rem;font-weight:700}.ccap-course{font-size:1.25rem}'
			. '.ccap-facts dt{font-weight:700}.ccap-facts dd{margin:0 0 .5rem}a:focus-visible,button:focus-visible{outline:3px solid #1d4ed8;outline-offset:2px}'
			. '@page{size:' . $size . ';margin:15mm}@media print{.ccap-noprint{display:none}main{max-width:none;padding:0}.ccap-certificate{break-inside:avoid;page-break-inside:avoid}a{color:inherit;text-decoration:none}}';
		return '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( (string) get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>' . esc_html( $title . ' – ' . get_bloginfo( 'name' ) ) . '</title><style>' . $css . '</style></head><body><main id="ccap-main">' . ( $heading ? '<h1>' . esc_html( $title ) . '</h1>' : '' ) . $body . '</main></body></html>';
	}
}
