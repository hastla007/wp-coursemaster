<?php
/**
 * Server-rendered learning area `/<learning_area_slug>/` and blocks (R1-16).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Access\ContentProtection;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Ops\Compatibility;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Works without JavaScript: "My learning" with state and "Continue",
 * the course room with module navigation (`nav`, `aria-current`,
 * lock reasons as text) and a skip link. Mobile first: single column,
 * no fixed widths, wrapping text, focus outlines kept. Pages are never
 * page-cached (personal data).
 */
final class LearningArea {

	/**
	 * Query var of the learning area (D-20-03: `course_captain_` for query vars, KOMP-130).
	 */
	public const QUERY_VAR = 'course_captain_app';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'rewrite' ) );
		add_filter(
			'query_vars',
			static function ( array $vars ): array {
				$vars[] = self::QUERY_VAR;
				return $vars;
			}
		);
		add_action( 'template_redirect', array( self::class, 'serve' ), 5 );
		add_action( 'init', array( self::class, 'blocks' ) );
		Compatibility::add_shortcode( 'ccap_learning_link', array( self::class, 'shortcode_link' ) );
		Compatibility::add_shortcode( 'ccap_access', array( self::class, 'shortcode_access' ) );
	}

	/**
	 * Rewrite rule for the learning area.
	 *
	 * @return void
	 */
	public static function rewrite(): void {
		$slug = preg_quote( self::slug(), '#' );
		add_rewrite_rule( '^' . $slug . '/?(.*)$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Slug of the learning area.
	 *
	 * @return string
	 */
	public static function slug(): string {
		return (string) Settings::get( 'general.learning_area_slug' );
	}

	/**
	 * URL in the learning area.
	 *
	 * @param string $path Path below the area.
	 * @return string
	 */
	public static function url( string $path = '' ): string {
		if ( Compatibility::plain_permalinks() ) {
			// KOMP-016 / D-20-08: query fallback without rewrite rules.
			return add_query_arg( self::QUERY_VAR, rawurlencode( ltrim( $path, '/' ) ), home_url( '/' ) );
		}
		return home_url( '/' . self::slug() . '/' . ltrim( $path, '/' ) );
	}

	/**
	 * `template_redirect`: render the area.
	 *
	 * @return void
	 */
	public static function serve(): void {
		$path = get_query_var( self::QUERY_VAR, null );
		if ( null === $path || false === $path ) {
			return;
		}
		ContentProtection::mark_uncacheable();
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::url( (string) $path ) ) );
			exit;
		}
		echo self::page( Actor::user( get_current_user_id() ), (string) $path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while building.
		exit;
	}

	/**
	 * Complete page for a path (`''` = My learning, `kurs/<uid>` = course room).
	 *
	 * @param Actor  $actor Learner.
	 * @param string $path  Path.
	 * @return string
	 */
	public static function page( Actor $actor, string $path ): string {
		$title = __( 'My learning', 'course-captain' );
		try {
			if ( 1 === preg_match( '#^(?:course|kurs)/([0-9a-f-]+)/?$#', trim( $path, '/' ), $m ) ) {
				$course = LearnerQueries::current()->course_id( $m[1] );
				$room   = LearnerQueries::current()->course( $actor, $course );
				$title  = $room['title'];
				$body   = self::course_room( $room );
			} else {
				$body = self::my_learning( $actor );
			}
		} catch ( CourseException $e ) {
			status_header( $e->status );
			$body = '<p role="alert">' . esc_html__( 'This course is not available.', 'course-captain' ) . '</p>';
		}
		$document_title = $title . ' – ' . get_bloginfo( 'name' );
		$title_filter   = static fn(): string => $document_title;
		add_filter( 'pre_get_document_title', $title_filter );
		ob_start();
		wp_head();
		$head = (string) ob_get_clean();
		remove_filter( 'pre_get_document_title', $title_filter );
		$own_title = current_theme_supports( 'title-tag' ) ? '' : '<title>' . esc_html( $document_title ) . '</title>';
		ob_start();
		wp_footer();
		$foot = (string) ob_get_clean();
		return '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1">' . $own_title . '<style>' . self::css() . '</style>' . $head . '</head><body class="ccap-learning"><a class="ccap-skip" href="#ccap-main">' . esc_html__( 'Skip to content', 'course-captain' ) . '</a><header class="ccap-bar"><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'My learning', 'course-captain' ) . '</a></header><main id="ccap-main" tabindex="-1"><h1>' . esc_html( $title ) . '</h1>' . $body . '</main>' . $foot . '</body></html>';
	}

	/**
	 * "My learning" list.
	 *
	 * @param Actor $actor Learner.
	 * @return string
	 */
	public static function my_learning( Actor $actor ): string {
		$courses = LearnerQueries::current()->my_courses( $actor );
		if ( array() === $courses ) {
			return '<p>' . esc_html__( 'You are not enrolled in any course yet.', 'course-captain' ) . '</p>';
		}
		$html = '<ul class="ccap-cards">';
		foreach ( $courses as $course ) {
			$html .= '<li class="ccap-card"><h2><a href="' . esc_url( self::url( 'kurs/' . $course['course_uid'] ) ) . '">' . esc_html( $course['title'] ) . '</a></h2>';
			$html .= '<p class="ccap-state">' . esc_html( self::state_label( $course['access']['state'] ) ) . '</p>';
			$html .= '<p><span class="screen-reader-text">' . esc_html__( 'Progress:', 'course-captain' ) . ' </span><progress max="100" value="' . (int) $course['progress']['completion_percent'] . '" aria-label="' . esc_attr__( 'Course progress', 'course-captain' ) . '"></progress> ' . esc_html( sprintf( /* translators: %d: percent */ __( '%d %% completed', 'course-captain' ), $course['progress']['completion_percent'] ) ) . '</p>';
			if ( null !== $course['resume'] ) {
				$html .= '<p><a class="ccap-button" href="' . esc_url( self::url( 'kurs/' . $course['course_uid'] ) . '#lesson-' . $course['resume']['lesson_id'] ) . '">' . esc_html( sprintf( /* translators: %s: lesson title */ __( 'Continue: %s', 'course-captain' ), $course['resume']['title'] ) ) . '</a></p>';
			}
			$html .= '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Course room.
	 *
	 * @param array<string, mixed> $room Course room data.
	 * @return string
	 */
	public static function course_room( array $room ): string {
		$resume = $room['resume']['lesson_id'] ?? 0;
		$html   = '<p class="ccap-state">' . esc_html( self::state_label( (string) $room['access']['state'] ) ) . '</p>';
		$html  .= '<nav aria-label="' . esc_attr__( 'Course contents', 'course-captain' ) . '"><ol class="ccap-modules">';
		foreach ( (array) $room['modules'] as $module ) {
			$html .= '<li><h2>' . esc_html( (string) $module['title'] ) . '</h2>';
			if ( null !== $module['locked_reason'] ) {
				$html .= '<p class="ccap-locked">' . esc_html( self::lock_label( (string) $module['locked_reason'], $module['unlock_at'] ) ) . '</p>';
			}
			$html .= '<ol class="ccap-lessons">';
			foreach ( (array) $module['lessons'] as $lesson ) {
				$current = (int) $lesson['lesson_id'] === $resume ? ' aria-current="step"' : '';
				$label   = esc_html( (string) $lesson['title'] ) . ' <span class="ccap-lesson-state">(' . esc_html( null === $lesson['locked_reason'] ? self::lesson_label( (string) $lesson['state'] ) : self::lock_label( (string) $lesson['locked_reason'], null ) ) . ')</span>';
				$html   .= '<li id="lesson-' . (int) $lesson['lesson_id'] . '">' . ( null === $lesson['locked_reason'] ? '<a href="' . esc_url( (string) get_permalink( (int) $lesson['lesson_id'] ) ) . '"' . $current . '>' . $label . '</a>' : '<span>' . $label . '</span>' ) . '</li>';
			}
			$html .= '</ol></li>';
		}
		return $html . '</ol></nav>';
	}

	/**
	 * Blocks (block.json, apiVersion 3, dynamic rendering).
	 *
	 * @return void
	 */
	public static function blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		$dir = dirname( __DIR__, 2 ) . '/blocks';
		Compatibility::register_block( $dir . '/course-catalog', 'course-captain/course-catalog', array( 'render_callback' => array( self::class, 'render_catalog' ) ) );
		Compatibility::register_block( $dir . '/learning-link', 'course-captain/learning-link', array( 'render_callback' => array( self::class, 'render_link' ) ) );
	}

	/**
	 * `course-captain/course-catalog`: published courses only.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render_catalog( $attributes ): string {
		$attributes = (array) $attributes;
		$queries    = LearnerQueries::current();
		$actor      = Actor::user( get_current_user_id() );
		$enrolled   = array_column( $queries->my_courses( $actor ), null, 'course_id' );
		$html       = '<ul class="ccap-catalog">';
		$identity   = new ObjectIdentity( Database::current() );
		foreach ( $queries->catalog( (int) ( $attributes['perPage'] ?? 12 ), (string) ( $attributes['orderBy'] ?? 'menu_order' ) ) as $course ) {
			if ( ! empty( $attributes['showOnlyEnrolled'] ) && ! isset( $enrolled[ (int) $course->ID ] ) ) {
				continue;
			}
			$html .= '<li><a href="' . esc_url( (string) get_permalink( $course ) ) . '">' . esc_html( $course->post_title ) . '</a>';
			if ( ( $attributes['showProgress'] ?? true ) && isset( $enrolled[ (int) $course->ID ] ) ) {
				$html .= ' <span class="ccap-progress">' . esc_html( sprintf( /* translators: %d: percent */ __( '%d %% completed', 'course-captain' ), $enrolled[ (int) $course->ID ]['progress']['completion_percent'] ) ) . '</span>';
			}
			$html .= ' <a class="screen-reader-text" href="' . esc_url( self::url( 'kurs/' . $identity->ensure( $course ) ) ) . '">' . esc_html( sprintf( /* translators: %s: course */ __( 'Open %s in the learning area', 'course-captain' ), $course->post_title ) ) . '</a></li>';
		}
		return $html . '</ul>';
	}

	/**
	 * `course-captain/learning-link` and `[ccap_learning_link]`.
	 *
	 * @param array<string, mixed> $attributes Attributes.
	 * @return string
	 */
	public static function render_link( $attributes ): string {
		$attributes = (array) $attributes;
		if ( ! is_user_logged_in() && isset( $attributes['showWhenLoggedOut'] ) && false === $attributes['showWhenLoggedOut'] ) {
			return '';
		}
		$label = '' !== (string) ( $attributes['label'] ?? '' ) ? (string) $attributes['label'] : __( 'Go to the learning area', 'course-captain' );
		return '<a class="ccap-button" href="' . esc_url( self::url() ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Shortcode `[ccap_learning_link label=""]`.
	 *
	 * @param mixed $atts    Attributes.
	 * @param mixed $content Content.
	 * @param mixed $tag     Tag.
	 * @return string
	 */
	public static function shortcode_link( $atts, $content = null, $tag = '' ): string {
		unset( $content, $tag );
		return self::render_link( shortcode_atts( array( 'label' => '' ), (array) $atts, 'ccap_learning_link' ) );
	}

	/**
	 * Shortcode `[ccap_access course="<uid>" message=""]…[/ccap_access]`.
	 *
	 * @param mixed $atts    Attributes.
	 * @param mixed $content Content.
	 * @param mixed $tag     Tag.
	 * @return string
	 */
	public static function shortcode_access( $atts, $content = null, $tag = '' ): string {
		unset( $tag );
		$atts   = shortcode_atts(
			array(
				'course'  => '',
				'message' => '',
			),
			(array) $atts,
			'ccap_access'
		);
		$course = LearnerQueries::current()->course_id( (string) $atts['course'] );
		ContentProtection::mark_uncacheable();
		if ( $course > 0 && ! ContentProtection::course_locked( $course, get_current_user_id() ) ) {
			return do_shortcode( (string) $content );
		}
		return '' === (string) $atts['message'] ? ContentProtection::notice() : '<p class="ccap-locked">' . esc_html( (string) $atts['message'] ) . '</p>';
	}

	/**
	 * Access state as text.
	 *
	 * @param string $state State.
	 * @return string
	 */
	public static function state_label( string $state ): string {
		$labels = array(
			'active'      => __( 'Access active', 'course-captain' ),
			'limited'     => __( 'Partial access', 'course-captain' ),
			'pending'     => __( 'Access pending', 'course-captain' ),
			'suspended'   => __( 'Access suspended', 'course-captain' ),
			'expired'     => __( 'Access expired', 'course-captain' ),
			'not_granted' => __( 'No access', 'course-captain' ),
		);
		return $labels[ $state ] ?? $state;
	}

	/**
	 * Lock reason as text.
	 *
	 * @param string      $reason    Reason.
	 * @param string|null $unlock_at Unlock time (UTC).
	 * @return string
	 */
	public static function lock_label( string $reason, ?string $unlock_at ): string {
		if ( 'drip' === $reason && null !== $unlock_at ) {
			/* translators: %s: date */
			return sprintf( __( 'Available from %s', 'course-captain' ), wp_date( (string) get_option( 'date_format' ), (int) strtotime( $unlock_at . ' UTC' ) ) );
		}
		$labels = array(
			'no_access'    => __( 'Locked: no access', 'course-captain' ),
			'trial'        => __( 'Locked: not part of the preview', 'course-captain' ),
			'drip'         => __( 'Locked: not yet released', 'course-captain' ),
			'prerequisite' => __( 'Locked: complete the previous steps first', 'course-captain' ),
			'exam_running' => __( 'Locked during your running exam', 'course-captain' ),
		);
		return $labels[ $reason ] ?? __( 'Locked', 'course-captain' );
	}

	/**
	 * Lesson state as text.
	 *
	 * @param string $state State.
	 * @return string
	 */
	private static function lesson_label( string $state ): string {
		return 'completed' === $state ? __( 'completed', 'course-captain' ) : ( 'in_progress' === $state ? __( 'in progress', 'course-captain' ) : __( 'not started', 'course-captain' ) );
	}

	/**
	 * Minimal responsive styles (single column, no overflow, visible focus).
	 *
	 * @return string
	 */
	private static function css(): string {
		return 'body.ccap-learning{margin:0;font:1rem/1.5 system-ui,sans-serif;overflow-wrap:anywhere}'
			. '.ccap-skip{position:absolute;left:-999px}.ccap-skip:focus{left:1rem;top:1rem;background:#fff;padding:.5rem;z-index:10}'
			. '.ccap-bar{padding:.75rem 1rem;border-bottom:1px solid #ccc}#ccap-main{max-width:60rem;margin:0 auto;padding:1rem;box-sizing:border-box}'
			. '.ccap-cards,.ccap-modules,.ccap-lessons{padding-left:1.25rem}.ccap-card{margin-bottom:1rem}'
			. '.ccap-button{display:inline-block;padding:.6rem 1rem;border:2px solid currentColor;border-radius:.25rem;text-decoration:none;min-height:44px;box-sizing:border-box}'
			. 'a:focus-visible,button:focus-visible{outline:3px solid #1d4ed8;outline-offset:2px}img,video,iframe{max-width:100%;height:auto}'
			. '.screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}'
			. 'progress{max-width:100%;vertical-align:middle}';
	}
}
