<?php
/**
 * "Locks and overrides" (SET-CC-11, R0-28 §1.6/§1.7, R0-30 §3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden page (#19), linked from the settings page. Overview of every key
 * that courses, exams, groups or media may override: website value, lock
 * state with reason, number of own values. Locking and unlocking go
 * through a confirmation page that names the affected courses and exams
 * (works without JavaScript); the override list of a key offers a bulk
 * reset to "inherit". All values come from {@see EffectiveSettings}; the
 * lock option and the audit are the service's.
 */
final class SettingLocksScreen {

	public const SLUG   = 'course-captain-setting-locks';
	public const ACTION = 'ccap_setting_locks';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				add_submenu_page( '', __( 'Locks and course overrides', 'course-captain' ), __( 'Locks and course overrides', 'course-captain' ), Settings::CAP, self::SLUG, array( self::class, 'render' ) );
			},
			19
		);
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Page output.
	 *
	 * @return void
	 */
	public static function render(): void {
		echo self::html( $_GET, get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.NonceVerification.Recommended -- Escaped while building; read-only view parameters.
	}

	/**
	 * Admin-post: lock, unlock or bulk reset.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$out  = self::process( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process().
		$args = array(
			'page'        => self::SLUG,
			'ccap_result' => $out['result'],
			'ccap_count'  => $out['count'],
		);
		if ( 'reset' === $out['op'] && '' !== $out['key'] ) {
			$args['view'] = 'overrides';
			$args['key']  = $out['key'];
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Testable core.
	 *
	 * @param array<string, mixed> $post    Unslashed POST (`op` lock|unlock|reset, `key`, `reason`, `items[]`).
	 * @param int                  $user_id User.
	 * @return array{op: string, key: string, result: string, count: int}
	 */
	public static function process( array $post, int $user_id ): array {
		$op  = isset( $post['op'] ) ? sanitize_key( (string) $post['op'] ) : '';
		$key = isset( $post['key'] ) ? sanitize_text_field( (string) $post['key'] ) : '';
		$out = array(
			'op'     => $op,
			'key'    => $key,
			'result' => 'ok',
			'count'  => 0,
		);
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::ACTION . '_' . $op . '_' . $key ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		$actor = Actor::user( $user_id );
		try {
			switch ( $op ) {
				case 'lock':
					$out['count']  = EffectiveSettings::lock( $actor, $key, (string) ( $post['reason'] ?? '' ) );
					$out['result'] = 'locked';
					break;
				case 'unlock':
					$out['count']  = EffectiveSettings::unlock( $actor, $key );
					$out['result'] = 'unlocked';
					break;
				case 'reset':
					$items         = is_array( $post['items'] ?? null ) ? array_values( array_map( 'sanitize_text_field', array_map( 'strval', $post['items'] ) ) ) : array();
					$done          = EffectiveSettings::reset_overrides( $actor, $key, $items );
					$out['count']  = count( $done['reset'] );
					$out['result'] = array() === $done['failed'] ? 'reset' : 'reset_partial';
					break;
				default:
					$out['result'] = 'ccap_invalid_param';
			}
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
		}
		return $out;
	}

	/**
	 * Page HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id User.
	 * @return string
	 */
	public static function html( array $request, int $user_id ): string {
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) ) {
			return '';
		}
		$view  = isset( $request['view'] ) ? sanitize_key( (string) $request['view'] ) : '';
		$key   = isset( $request['key'] ) ? sanitize_text_field( (string) $request['key'] ) : '';
		$html  = '<div class="wrap ccap-locks"><h1>' . esc_html__( 'Locks and course overrides', 'course-captain' ) . '</h1>';
		$html .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . SettingsScreen::SLUG ) ) . '">' . esc_html__( 'Back to the settings', 'course-captain' ) . '</a></p>';
		$html .= self::message( $request );
		$meta  = SettingsSchema::overridable()[ $key ] ?? null;
		if ( null !== $meta && in_array( $view, array( 'lock', 'unlock', 'overrides', 'reset' ), true ) ) {
			$items = isset( $request['items'] ) && is_array( $request['items'] ) ? array_values( array_map( 'sanitize_text_field', array_map( 'strval', $request['items'] ) ) ) : array();
			switch ( $view ) {
				case 'lock':
				case 'unlock':
					return $html . self::lock_page( $view, $key, $meta ) . '</div>';
				case 'overrides':
					return $html . self::overrides_page( $key ) . '</div>';
				case 'reset':
					return $html . self::reset_page( $key, $items ) . '</div>';
			}
		}
		return $html . self::overview() . '</div>';
	}

	/**
	 * Overview table of all overridable keys.
	 *
	 * @return string
	 */
	private static function overview(): string {
		$labels = EffectiveSettings::labels();
		$locks  = EffectiveSettings::locks();
		$html   = '<p>' . esc_html__( 'Courses, exams, groups and media can deviate from these website values. A lock makes the website value apply everywhere; own values stay stored and apply again when the lock is removed.', 'course-captain' ) . '</p>';
		$html  .= '<table class="widefat striped"><caption>' . esc_html__( 'Settings that can be changed per course, exam, group or media item', 'course-captain' ) . '</caption><thead><tr>'
			. '<th scope="col">' . esc_html__( 'Setting', 'course-captain' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Changeable per', 'course-captain' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Website value', 'course-captain' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Own values', 'course-captain' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Lock', 'course-captain' ) . '</th>'
			. '</tr></thead><tbody>';
		foreach ( SettingsSchema::overridable() as $key => $meta ) {
			$label  = $labels[ $key ] ?? $key;
			$count  = EffectiveSettings::count_overrides( $key );
			$listed = array_intersect( $meta['scopes'], array( 'course', 'exam_rule' ) );
			$own    = array() === $listed ? esc_html__( 'not listed here', 'course-captain' ) : esc_html( number_format_i18n( $count ) );
			if ( array() !== $listed && $count > 0 ) {
				/* translators: %s: setting name */
				$own .= ' <a href="' . esc_url( self::url( array( 'view' => 'overrides', 'key' => $key ) ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Show own values of “%s”', 'course-captain' ), $label ) ) . '">' . esc_html__( 'Show', 'course-captain' ) . '</a>'; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
			}
			if ( empty( $meta['lockable'] ) ) {
				$lock = esc_html__( 'cannot be locked', 'course-captain' );
			} elseif ( isset( $locks[ $key ] ) ) {
				/* translators: %s: reason given for the lock */
				$lock  = esc_html__( 'Locked', 'course-captain' ) . ( '' === $locks[ $key ]['reason'] ? '' : esc_html( sprintf( __( ' – reason: %s', 'course-captain' ), $locks[ $key ]['reason'] ) ) );
				$lock .= ' <a href="' . esc_url( self::url( array( 'view' => 'unlock', 'key' => $key ) ) ) . '">' . esc_html( sprintf( /* translators: %s: setting name */ __( 'Remove lock of “%s”…', 'course-captain' ), $label ) ) . '</a>'; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
			} else {
				$lock = esc_html__( 'Not locked', 'course-captain' ) . ' <a href="' . esc_url( self::url( array( 'view' => 'lock', 'key' => $key ) ) ) . '">' . esc_html( sprintf( /* translators: %s: setting name */ __( 'Lock “%s” for all…', 'course-captain' ), $label ) ) . '</a>'; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
			}
			$html .= '<tr><th scope="row">' . esc_html( $label ) . '<br><code>' . esc_html( $key ) . '</code></th><td>' . esc_html( self::scopes( $meta['scopes'] ) ) . '</td><td>' . esc_html( CourseSettingsPanel::display( EffectiveSettings::global_value( $key )['value'] ) ) . '</td><td>' . $own . '</td><td>' . $lock . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Confirmation page "affects N courses" for lock and unlock.
	 *
	 * @param string               $view lock|unlock.
	 * @param string               $key  Key.
	 * @param array<string, mixed> $meta Override metadata.
	 * @return string
	 */
	private static function lock_page( string $view, string $key, array $meta ): string {
		$label  = EffectiveSettings::labels()[ $key ] ?? $key;
		$locked = isset( EffectiveSettings::locks()[ $key ] );
		if ( empty( $meta['lockable'] ) || ( 'lock' === $view ) === $locked ) {
			return '<p>' . esc_html__( 'Nothing to do: the lock state has changed in the meantime.', 'course-captain' ) . '</p>' . self::back();
		}
		$rows    = EffectiveSettings::list_overrides( $key );
		$courses = count( array_filter( $rows, static fn( array $r ): bool => 'course' === $r['scope'] ) );
		$rules   = count( $rows ) - $courses;
		$value   = CourseSettingsPanel::display( EffectiveSettings::global_value( $key )['value'] );
		/* translators: %s: setting name */
		$title = 'lock' === $view ? sprintf( __( 'Lock “%s” for all courses?', 'course-captain' ), $label ) : sprintf( __( 'Remove the lock of “%s”?', 'course-captain' ), $label );
		$html  = '<div role="alert"><h2>' . esc_html( $title ) . '</h2><p>';
		if ( 'lock' === $view ) {
			/* translators: 1: number of courses, 2: number of exams, 3: website value */
			$html .= esc_html( sprintf( _n( 'This affects %1$d course and %2$d exams with an own value. They then use the website value “%3$s”; their own values stay stored but rest.', 'This affects %1$d courses and %2$d exams with an own value. They then use the website value “%3$s”; their own values stay stored but rest.', $courses, 'course-captain' ), $courses, $rules, $value ) );
		} else {
			/* translators: 1: number of courses, 2: number of exams */
			$html .= esc_html( sprintf( _n( 'This affects %1$d course and %2$d exams: their own values apply again.', 'This affects %1$d courses and %2$d exams: their own values apply again.', $courses, 'course-captain' ), $courses, $rules ) );
		}
		$html .= ' ' . esc_html( EffectiveSettings::class_label( (string) $meta['class'] ) ) . '.</p></div>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="op" value="' . esc_attr( $view ) . '"><input type="hidden" name="key" value="' . esc_attr( $key ) . '">' . wp_nonce_field( self::ACTION . '_' . $view . '_' . $key, '_wpnonce', true, false );
		if ( 'lock' === $view ) {
			$html .= '<p><label for="ccap-lock-reason">' . esc_html__( 'Reason (shown in the course editor, at most 200 characters)', 'course-captain' ) . '</label><br><input type="text" class="regular-text" id="ccap-lock-reason" name="reason" maxlength="200"></p>';
		}
		$button = 'lock' === $view ? __( 'Lock for all courses', 'course-captain' ) : __( 'Remove lock', 'course-captain' );
		return $html . '<p><button type="submit" class="button button-primary">' . esc_html( $button ) . '</button> ' . self::back( __( 'Cancel', 'course-captain' ) ) . '</p></form>';
	}

	/**
	 * Override list of a key with selection for the bulk reset.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function overrides_page( string $key ): string {
		$label = EffectiveSettings::labels()[ $key ] ?? $key;
		$rows  = EffectiveSettings::list_overrides( $key );
		/* translators: %s: setting name */
		$html = '<h2>' . esc_html( sprintf( __( 'Own values of “%s”', 'course-captain' ), $label ) ) . '</h2>';
		/* translators: %s: website value */
		$html .= '<p>' . esc_html( sprintf( __( 'Website value: %s', 'course-captain' ), CourseSettingsPanel::display( EffectiveSettings::global_value( $key )['value'] ) ) ) . ( isset( EffectiveSettings::locks()[ $key ] ) ? ' ' . esc_html__( '(locked: own values rest)', 'course-captain' ) : '' ) . '</p>';
		if ( array() === $rows ) {
			return $html . '<p>' . esc_html__( 'No course or exam has an own value.', 'course-captain' ) . '</p>' . self::back();
		}
		$html .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="view" value="reset"><input type="hidden" name="key" value="' . esc_attr( $key ) . '">';
		$html .= '<table class="widefat striped"><caption>' . esc_html__( 'Courses and exams with an own value', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Select', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Course or exam', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Own value', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$ref  = $row['scope'] . ':' . $row['id'];
			$id   = 'ccap-ovr-' . $row['scope'] . '-' . $row['id'];
			$kind = 'course' === $row['scope'] ? __( 'Course', 'course-captain' ) : __( 'Exam', 'course-captain' );
			$name = $kind . ': ' . $row['title'];
			$cell = $row['resettable']
				/* translators: %s: course or exam name */
				? '<input type="checkbox" id="' . esc_attr( $id ) . '" name="items[]" value="' . esc_attr( $ref ) . '"><label for="' . esc_attr( $id ) . '" class="screen-reader-text">' . esc_html( sprintf( __( 'Reset %s', 'course-captain' ), $name ) ) . '</label>'
				: esc_html__( 'published, cannot change', 'course-captain' );
			$link  = 'course' === $row['scope'] ? get_edit_post_link( $row['id'], 'raw' ) : '';
			$title = is_string( $link ) && '' !== $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name );
			$html .= '<tr><td>' . $cell . '</td><th scope="row">' . $title . '</th><td>' . esc_html( CourseSettingsPanel::display( $row['value'] ) ) . '</td><td>' . esc_html( $row['status'] ) . '</td></tr>';
		}
		$html .= '</tbody></table><p><button type="submit" class="button">' . esc_html__( 'Reset selected to the website value…', 'course-captain' ) . '</button> ' . self::back() . '</p></form>';
		return $html;
	}

	/**
	 * Confirmation of the bulk reset.
	 *
	 * @param string $key   Key.
	 * @param array  $items Selected refs.
	 * @phpstan-param list<string> $items
	 * @return string
	 */
	private static function reset_page( string $key, array $items ): string {
		$label  = EffectiveSettings::labels()[ $key ] ?? $key;
		$chosen = array();
		foreach ( EffectiveSettings::list_overrides( $key ) as $row ) {
			if ( $row['resettable'] && in_array( $row['scope'] . ':' . $row['id'], $items, true ) ) {
				$chosen[] = $row;
			}
		}
		if ( array() === $chosen ) {
			return '<p role="alert">' . esc_html__( 'Select at least one course or exam.', 'course-captain' ) . '</p>' . self::back( __( 'Back to the list', 'course-captain' ), array( 'view' => 'overrides', 'key' => $key ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
		}
		$count = count( $chosen );
		/* translators: 1: number of courses and exams, 2: setting name */
		$html = '<div role="alert"><h2>' . esc_html( sprintf( _n( 'Reset %1$d own value of “%2$s”?', 'Reset %1$d own values of “%2$s”?', $count, 'course-captain' ), $count, $label ) ) . '</h2><p>' . esc_html__( 'The selected courses and exams then use the website value again. Courses apply it with their next published revision.', 'course-captain' ) . '</p><ul>';
		$form = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="op" value="reset"><input type="hidden" name="key" value="' . esc_attr( $key ) . '">' . wp_nonce_field( self::ACTION . '_reset_' . $key, '_wpnonce', true, false );
		foreach ( $chosen as $row ) {
			$html .= '<li>' . esc_html( ( 'course' === $row['scope'] ? __( 'Course', 'course-captain' ) : __( 'Exam', 'course-captain' ) ) . ': ' . $row['title'] . ' (' . CourseSettingsPanel::display( $row['value'] ) . ')' ) . '</li>';
			$form .= '<input type="hidden" name="items[]" value="' . esc_attr( $row['scope'] . ':' . $row['id'] ) . '">';
		}
		return $html . '</ul></div>' . $form . '<p><button type="submit" class="button button-primary">' . esc_html__( 'Reset to the website value', 'course-captain' ) . '</button> ' . self::back( __( 'Cancel', 'course-captain' ), array( 'view' => 'overrides', 'key' => $key ) ) . '</p></form>'; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
	}

	/**
	 * Result notice.
	 *
	 * @param array<string, mixed> $request GET.
	 * @return string
	 */
	private static function message( array $request ): string {
		$result = isset( $request['ccap_result'] ) ? sanitize_key( (string) $request['ccap_result'] ) : '';
		$count  = absint( $request['ccap_count'] ?? 0 );
		switch ( $result ) {
			case '':
				return '';
			case 'locked':
				/* translators: %d: number of own values that rest */
				$text = sprintf( _n( 'Locked for all courses. %d own value rests.', 'Locked for all courses. %d own values rest.', $count, 'course-captain' ), $count );
				break;
			case 'unlocked':
				/* translators: %d: number of own values that apply again */
				$text = sprintf( _n( 'Lock removed. %d own value applies again.', 'Lock removed. %d own values apply again.', $count, 'course-captain' ), $count );
				break;
			case 'reset':
			case 'reset_partial':
				/* translators: %d: number of reset courses and exams */
				$text = sprintf( _n( '%d own value reset to the website value.', '%d own values reset to the website value.', $count, 'course-captain' ), $count ) . ( 'reset_partial' === $result ? ' ' . __( 'Some could not be reset (no permission or published).', 'course-captain' ) : '' );
				break;
			case 'ccap_setting_lock_conflict':
				$text = __( 'Error: a published exam would become invalid with this lock. Change the website value first.', 'course-captain' );
				break;
			case 'ccap_validation_failed':
				$text = __( 'Error: the reason is too long or the setting cannot be locked.', 'course-captain' );
				break;
			case 'ccap_invalid_nonce':
				$text = __( 'The form has expired. Reload the page and try again.', 'course-captain' );
				break;
			default:
				$text = __( 'Error: the action was refused.', 'course-captain' );
		}
		$error = str_starts_with( $result, 'ccap_' );
		return '<div class="notice notice-' . ( $error ? 'error' : 'success' ) . '" role="' . ( $error ? 'alert' : 'status' ) . '" tabindex="-1" data-ccap-focus="1"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Scope names.
	 *
	 * @param array $scopes Scopes.
	 * @phpstan-param list<string> $scopes
	 * @return string
	 */
	private static function scopes( array $scopes ): string {
		$names = array(
			'course'    => __( 'course', 'course-captain' ),
			'activity'  => __( 'activity', 'course-captain' ),
			'exam_rule' => __( 'exam', 'course-captain' ),
			'group'     => __( 'group', 'course-captain' ),
			'media'     => __( 'media item', 'course-captain' ),
		);
		return implode( ', ', array_map( static fn( string $s ): string => $names[ $s ] ?? $s, $scopes ) );
	}

	/**
	 * Back link.
	 *
	 * @param string               $text Link text ('' = default).
	 * @param array<string, mixed> $args View args.
	 * @return string
	 */
	private static function back( string $text = '', array $args = array() ): string {
		return '<a class="button" href="' . esc_url( self::url( $args ) ) . '">' . esc_html( '' === $text ? __( 'Back to the overview', 'course-captain' ) : $text ) . '</a>';
	}

	/**
	 * Page URL.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return string
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array( 'page' => self::SLUG ) + $args, admin_url( 'admin.php' ) );
	}
}
