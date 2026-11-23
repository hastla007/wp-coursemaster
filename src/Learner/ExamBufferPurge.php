<?php
/**
 * Empty the offline exam buffer of the browser on logout (R0-17 T-04,
 * DF-05, CS-03; R0-31 §4; PRIV-018).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

defined( 'ABSPATH' ) || exit;

/**
 * The exam room buffers unsent answers in localStorage under
 * `ccap_exam_<session uid>` (assets/js/exam-room.js). The server cannot
 * reach the browser storage, so logout sets the short marker cookie
 * `ccap_exam_purge` (no personal data, value `1`, not HttpOnly so the
 * script can remove it). A small inline script removes every `ccap_exam_*`
 * key from localStorage and sessionStorage and then the marker cookie:
 *
 * - on the login page (`login_footer`, where WordPress lands after logout)
 *   always printed, it acts only when the marker cookie is present, so it
 *   also works on cached pages;
 * - on front-end pages (`wp_footer`, also the learning area) only printed
 *   when the request carries the marker cookie;
 * - the exam room script checks the marker itself before it touches the
 *   buffer.
 *
 * Keys of other plugins stay untouched.
 */
final class ExamBufferPurge {

	public const COOKIE = 'ccap_exam_purge';
	public const PREFIX = 'ccap_exam_';
	public const TTL    = 30 * DAY_IN_SECONDS;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_logout', array( self::class, 'on_logout' ) );
		add_action( 'login_footer', array( self::class, 'print_script' ) );
		add_action( 'wp_footer', array( self::class, 'maybe_print_script' ) );
	}

	/**
	 * `wp_logout`: set the marker cookie for every cookie path.
	 *
	 * @return void
	 */
	public static function on_logout(): void {
		$_COOKIE[ self::COOKIE ] = '1';
		if ( headers_sent() ) {
			return;
		}
		foreach ( self::cookies() as $cookie ) {
			setcookie( self::COOKIE, '1', $cookie );
		}
	}

	/**
	 * Cookie options of the marker: path of the site and of the login page
	 * (as WordPress sets its own cookies), `SameSite=Lax`, secure on HTTPS.
	 *
	 * @return list<array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'}>
	 */
	public static function cookies(): array {
		$list = array();
		foreach ( self::paths() as $path ) {
			$list[] = array(
				'expires'  => time() + self::TTL,
				'path'     => $path,
				'domain'   => defined( 'COOKIE_DOMAIN' ) && is_string( constant( 'COOKIE_DOMAIN' ) ) ? constant( 'COOKIE_DOMAIN' ) : '',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			);
		}
		return $list;
	}

	/**
	 * `login_footer`: always print the purge script.
	 *
	 * @return void
	 */
	public static function print_script(): void {
		wp_print_inline_script_tag( self::script() );
	}

	/**
	 * `wp_footer`: print the purge script only after a logout.
	 *
	 * @return void
	 */
	public static function maybe_print_script(): void {
		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			self::print_script();
		}
	}

	/**
	 * Inline script: acts only when the marker cookie is present.
	 *
	 * @return string
	 */
	public static function script(): string {
		$expire = array();
		foreach ( self::paths() as $path ) {
			$expire[] = self::COOKIE . '=; Max-Age=0; path=' . $path . '; SameSite=Lax';
		}
		return '(function(){if(-1===(";"+document.cookie).replace(/\s/g,"").indexOf(";' . self::COOKIE . '=")){return;}'
			. 'try{[window.localStorage,window.sessionStorage].forEach(function(s){for(var i=s.length-1;i>=0;i--){var k=s.key(i);if(k&&0===k.indexOf(' . wp_json_encode( self::PREFIX ) . ')){s.removeItem(k);}}});}catch(e){}'
			. wp_json_encode( $expire ) . '.forEach(function(c){document.cookie=c;});}());';
	}

	/**
	 * Cookie paths (site and login page), without duplicates.
	 *
	 * @return list<string>
	 */
	private static function paths(): array {
		$paths = array();
		foreach ( array( 'COOKIEPATH', 'SITECOOKIEPATH' ) as $constant ) {
			$path    = defined( $constant ) && is_string( constant( $constant ) ) && '' !== constant( $constant ) ? (string) constant( $constant ) : '/';
			$paths[] = $path;
		}
		return array_values( array_unique( $paths ) );
	}
}
