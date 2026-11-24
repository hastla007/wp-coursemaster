<?php
/**
 * Server-rendered learning area `/<learning_area_slug>/` and blocks (R1-16).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Access\ContentProtection;
use CourseCaptain\Access\SelfEnrollment;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Locale;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Ops\Compatibility;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Rest\RestContract;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Works without JavaScript: "My learning" with state and "Continue",
 * the course room with module navigation (`nav`, `aria-current`,
 * lock reasons as text) and a skip link. Mobile first: single column,
 * no fixed widths, wrapping text, focus outlines kept. Pages are never
 * page-cached (personal data). Rendered in the learner's profile
 * language when the setting is on (E-124); `lang`/`dir` follow the
 * resolved UI locale, content containers carry the course language
 * when it differs (A11Y-032). CSS uses logical properties only (RTL,
 * I18N-R-12).
 */
final class LearningArea {

	/**
	 * Query var of the learning area (D-20-03: `course_captain_` for query vars, KOMP-130).
	 */
	public const QUERY_VAR = 'course_captain_app';

	/**
	 * Nonce action of the self-enrollment form (suffix `:<course>`).
	 */
	public const ENROLL_NONCE = 'ccap_enroll';

	/**
	 * Lesson path below the area: `kurs/<course-uid>/lektion/<lesson-id>`.
	 */
	private const LESSON_ROUTE = '#^(?:course|kurs)/([0-9a-f-]+)/(?:lesson|lektion)/(\d+)/?$#';

	/**
	 * Practice path: `kurs/<course-uid>/ueben[/<mode>]` (E-342).
	 */
	private const PRACTICE_ROUTE = '#^(?:course|kurs)/([0-9a-f-]+)/(?:practice|ueben)(?:/([a-z]+))?/?$#';

	/**
	 * Groups of the owner: `gruppen[/<group-uid>]` (R0-11 L-18).
	 */
	private const GROUP_ROUTE = '#^(?:groups|gruppen)(?:/([0-9a-f-]{36}))?/?$#';

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
		Invitation::register();
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
	 * URL of a lesson in the learning area (the lesson post type is not public).
	 *
	 * @param string $course_uid Course UID.
	 * @param int    $lesson_id  Lesson.
	 * @return string
	 */
	public static function lesson_url( string $course_uid, int $lesson_id ): string {
		return self::url( 'kurs/' . $course_uid . '/lektion/' . $lesson_id );
	}

	/**
	 * UID of a course (created when missing).
	 *
	 * @param int $course_id Course.
	 * @return string
	 */
	public static function course_uid( int $course_id ): string {
		$post = get_post( $course_id );
		return $post instanceof \WP_Post ? ( new ObjectIdentity( Database::current() ) )->ensure( $post ) : (string) $course_id;
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
		// SET-CC-10: CSP of the learning area per `privacy.csp_mode` (R0-17 §3.5, PSD-052).
		\CourseCaptain\Security\Csp::start();
		$claim = Invitation::token( isset( $_GET[ Invitation::PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ Invitation::PARAM ] ) ) : null ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token link, verified by the group service.
		if ( null !== $claim ) {
			ContentProtection::send_token_page_headers();
		}
		if ( ! is_user_logged_in() ) {
			if ( null !== $claim && '' === trim( (string) $path, '/' ) ) {
				// GM-104: guests see the invitation hint with login/registration leading back.
				echo self::page( Actor::user( 0 ), '', '', $claim ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while building.
				exit;
			}
			wp_safe_redirect( wp_login_url( self::url( (string) $path ) ) );
			exit;
		}
		$actor = Actor::user( get_current_user_id() );
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
			// Nonce is verified in handle_post() (testable core).
			// Files only reach the assignment service, which checks them (R0-10 §4.5).
			wp_safe_redirect( self::handle_post( $actor, (string) $path, wp_unslash( $_POST ), $_FILES ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			exit;
		}
		$status = isset( $_GET['ccap_status'] ) ? sanitize_key( wp_unslash( $_GET['ccap_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$query  = array( 'ccap_seat' => isset( $_GET['ccap_seat'] ) ? absint( $_GET['ccap_seat'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only (confirmation step).
		echo self::page( $actor, (string) $path, $status, (string) $claim, $query ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while building.
		exit;
	}

	/**
	 * One-time message for the next page of the learner (PRG).
	 *
	 * @param int                  $user User.
	 * @param string               $code Code.
	 * @param array<string, mixed> $data Data.
	 * @return void
	 */
	public static function flash( int $user, string $code, array $data = array() ): void {
		if ( $user > 0 ) {
			set_transient(
				'ccap_area_flash_' . $user,
				array(
					'code' => $code,
					'data' => $data,
				),
				10 * MINUTE_IN_SECONDS
			);
		}
	}

	/**
	 * Read and remove the one-time message.
	 *
	 * @param int $user User.
	 * @return array{code: string, data: array<string, mixed>}|null
	 */
	public static function take_flash( int $user ): ?array {
		$flash = $user > 0 ? get_transient( 'ccap_area_flash_' . $user ) : false;
		if ( ! is_array( $flash ) ) {
			return null;
		}
		delete_transient( 'ccap_area_flash_' . $user );
		return array(
			'code' => (string) ( $flash['code'] ?? '' ),
			'data' => (array) ( $flash['data'] ?? array() ),
		);
	}

	/**
	 * Form posts in the learning area (exam start, exam answers, lesson completion); returns the redirect target (PRG).
	 *
	 * @param Actor                $actor Learner.
	 * @param string               $path  Path.
	 * @param array<string, mixed> $post  Unslashed POST data.
	 * @param array<string, mixed> $files Uploaded files (`$_FILES`, assignment form only).
	 * @return string
	 */
	public static function handle_post( Actor $actor, string $path, array $post, array $files = array() ): string {
		$path = trim( $path, '/' );
		return Locale::in_learning_locale(
			$actor->user_id,
			static function () use ( $actor, $path, $post, $files ): string {
				if ( 1 === preg_match( self::PRACTICE_ROUTE, $path, $m ) && '' !== (string) ( $m[2] ?? '' ) ) {
					$course = LearnerQueries::current()->course_id( $m[1] );
					$mode   = (string) ( $m[2] ?? '' );
					$status = TrainerView::handle( $actor, $course, $mode, $post );
					return add_query_arg( 'ccap_status', $status, TrainerView::url( $course, TrainerView::mode( $mode ) ) ) . '#ccap-main';
				}
				if ( 1 === preg_match( self::GROUP_ROUTE, $path, $m ) && '' !== (string) ( $m[1] ?? '' ) ) {
					return GroupsView::handle( $actor, (string) ( $m[1] ?? '' ), $post );
				}
				if ( 1 === preg_match( '#^(?:course|kurs)/([0-9a-f-]+)$#', $path, $m ) && 'enroll' === ( $post['ccap_do'] ?? '' ) ) {
					$course = LearnerQueries::current()->course_id( $m[1] );
					return add_query_arg( 'ccap_status', self::enroll( $actor, $course, $post ), self::url( $path ) ) . '#ccap-main';
				}
				if ( 1 === preg_match( '#^(?:exam|pruefung)/(\d+)$#', $path, $m ) ) {
					$status = ExamRoom::handle( $actor, (int) $m[1], $post );
					if ( 'cancelled' === $status ) {
						// GM-051: back to the course room with one message.
						ExamRoom::flash_cancelled( $actor->user_id );
						return self::url( 'kurs/' . ExamRoom::course_uid_of( (int) $m[1] ) ) . '#ccap-main';
					}
					if ( str_starts_with( $status, 'revealed:' ) ) {
						return add_query_arg( 'ccap_status', 'revealed', self::url( 'pruefung/' . (int) $m[1] ) ) . '#ccap-q-' . (int) substr( $status, 9 );
					}
					if ( str_starts_with( $status, 'ordering_incomplete:' ) ) {
						return add_query_arg( 'ccap_status', 'ordering_incomplete', self::url( 'pruefung/' . (int) $m[1] ) ) . '#ccap-q-' . (int) substr( $status, 20 );
					}
					return add_query_arg( 'ccap_status', 'submitted' === $status ? false : $status, self::url( 'pruefung/' . (int) $m[1] ) ) . ( in_array( $status, array( 'submitted', 'confirm_cancel' ), true ) ? '#ccap-main' : '' );
				}
				if ( 1 === preg_match( self::LESSON_ROUTE, $path, $m ) ) {
					$course = LearnerQueries::current()->course_id( $m[1] );
					$result = LessonView::handle( $actor, $course, (int) $m[2], $post, $files );
					if ( null !== $result['location'] ) {
						return $result['location'];
					}
					return add_query_arg( 'ccap_status', $result['status'], self::lesson_url( $m[1], (int) $m[2] ) ) . '#ccap-main';
				}
				if ( '' === $path && 'claim' === ( $post['ccap_do'] ?? '' ) ) {
					return Invitation::handle( $actor, $post );
				}
				if ( 1 === preg_match( '#^(?:course|kurs)/([0-9a-f-]+)$#', $path, $m ) && 'start_exam' === ( $post['ccap_do'] ?? '' ) ) {
					$session = ExamRoom::start( $actor, LearnerQueries::current()->course_id( $m[1] ), $post );
					return is_int( $session ) ? self::url( 'pruefung/' . $session ) : add_query_arg( 'ccap_status', $session, self::url( $path ) );
				}
				return self::url( $path );
			}
		);
	}

	/**
	 * Complete page for a path (`''` = My learning, `kurs/<uid>` = course room,
	 * `kurs/<uid>/lektion/<id>` = lesson, `pruefung/<id>` = exam room), in the
	 * learning-area locale (E-124).
	 *
	 * @param Actor                $actor  Learner.
	 * @param string               $path   Path.
	 * @param string               $status Status after a form post.
	 * @param string               $claim  Invitation token on the start page (GM-104).
	 * @param array<string, mixed> $query  Display-only query values (`ccap_seat`).
	 * @return string
	 */
	public static function page( Actor $actor, string $path, string $status = '', string $claim = '', array $query = array() ): string {
		return Locale::in_learning_locale( $actor->user_id, static fn(): string => self::document( $actor, $path, $status, $claim, $query ) );
	}

	/**
	 * Route a path to page parts: HTTP status, title, title attributes, body.
	 *
	 * @param Actor                $actor  Learner.
	 * @param string               $path   Path (`''`, `kurs/<uid>`, `kurs/<uid>/lektion/<id>`, `pruefung/<id>`).
	 * @param string               $status Status after a form post.
	 * @param string               $claim  Invitation token on the start page (GM-104).
	 * @param array<string, mixed> $query  Display-only query values (`ccap_seat`).
	 * @return array{status: int, title: string, title_attr: string, body: string}
	 */
	public static function resolve( Actor $actor, string $path, string $status = '', string $claim = '', array $query = array() ): array {
		$path      = trim( $path, '/' );
		$result    = array(
			'status'     => 200,
			'title'      => __( 'My learning', 'course-captain' ),
			'title_attr' => '',
			'body'       => '',
		);
		$is_lesson = 1 === preg_match( self::LESSON_ROUTE, $path, $lesson );
		try {
			if ( 1 === preg_match( self::PRACTICE_ROUTE, $path, $m ) ) {
				$course = LearnerQueries::current()->course_id( $m[1] );
				$result = '' === (string) ( $m[2] ?? '' ) ? TrainerView::overview( $actor, $course ) : TrainerView::render( $actor, $course, (string) ( $m[2] ?? '' ), $status );
			} elseif ( 1 === preg_match( self::GROUP_ROUTE, $path, $m ) ) {
				$result = '' === (string) ( $m[1] ?? '' ) ? GroupsView::index( $actor ) : GroupsView::detail( $actor, (string) ( $m[1] ?? '' ), $status, $query );
			} elseif ( $is_lesson ) {
				$m = $lesson;
				// Unknown course references resolve to 0 and end as 404 in the read model.
				$result = LessonView::render( $actor, LearnerQueries::current()->course_id( $m[1] ), (int) $m[2], $status );
			} elseif ( 1 === preg_match( '#^(?:course|kurs)/([0-9a-f-]+)$#', $path, $m ) ) {
				$course               = LearnerQueries::current()->course_id( $m[1] );
				$room                 = LearnerQueries::current()->course( $actor, $course );
				$result['title']      = $room['title'];
				$result['title_attr'] = Locale::content_attributes( CourseStructure::course_language( $course ) );
				$result['body']       = ExamRoom::take_flash( $actor->user_id ) . self::course_status( $status ) . self::enrollment( $actor, $room ) . self::course_room( $room ) . self::dimensions( $actor, $room ) . self::practice( $room ) . ExamRoom::course_exams( $actor, $course );
			} elseif ( 1 === preg_match( '#^(?:exam|pruefung)/(\d+)$#', $path, $m ) ) {
				$exam            = ExamRoom::render( $actor, (int) $m[1], $status );
				$result['title'] = $exam['title'];
				$result['body']  = $exam['body'];
			} elseif ( '' === $path && null !== Invitation::token( $claim ) ) {
				$result['title'] = __( 'Group invitation', 'course-captain' );
				$result['body']  = Invitation::body( $actor, $claim, $status );
			} else {
				$result['body'] = self::flash_notice( $actor ) . self::my_learning( $actor );
			}
		} catch ( CourseException $e ) {
			$text = $is_lesson ? __( 'This lesson is not available.', 'course-captain' ) : __( 'This course is not available.', 'course-captain' );
			if ( 1 === preg_match( self::GROUP_ROUTE, $path ) ) {
				$text = __( 'This group is not available.', 'course-captain' );
			} elseif ( 1 === preg_match( self::PRACTICE_ROUTE, $path ) && 'ccap_not_found' !== $e->error_code ) {
				$text = RestContract::user_message( $e->error_code );
			}
			$result = array(
				'status'     => $e->status,
				'title'      => __( 'My learning', 'course-captain' ),
				'title_attr' => '',
				'body'       => '<p role="alert">' . esc_html( $text ) . '</p>',
			);
		}
		return $result;
	}

	/**
	 * Document in the current locale.
	 *
	 * @param Actor                $actor  Learner.
	 * @param string               $path   Path.
	 * @param string               $status Status after a form post.
	 * @param string               $claim  Invitation token on the start page.
	 * @param array<string, mixed> $query  Display-only query values.
	 * @return string
	 */
	private static function document( Actor $actor, string $path, string $status, string $claim = '', array $query = array() ): string {
		$page       = self::resolve( $actor, $path, $status, $claim, $query );
		$title      = $page['title'];
		$title_attr = $page['title_attr'];
		$body       = $page['body'];
		if ( 200 !== $page['status'] ) {
			status_header( $page['status'] );
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
		return '<!doctype html><html' . Locale::ui_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1">' . $own_title . '<style>' . self::css() . self::brand_css() . '</style>' . $head . '</head><body class="ccap-learning ccap-width-' . esc_attr( self::container_width( $path ) ) . '"><a class="ccap-skip" href="#ccap-main">' . esc_html__( 'Skip to content', 'course-captain' ) . '</a><header class="ccap-bar">' . self::logo() . '<a class="ccap-home" href="' . esc_url( self::url() ) . '">' . esc_html__( 'My learning', 'course-captain' ) . '</a>' . ( GroupsView::has_groups( $actor ) ? ' <a class="ccap-groups-link" href="' . esc_url( GroupsView::url() ) . '">' . esc_html__( 'My groups', 'course-captain' ) . '</a>' : '' ) . '</header><main id="ccap-main" tabindex="-1"><h1' . $title_attr . '>' . esc_html( $title ) . '</h1>' . $body . '</main>' . self::footer() . $foot . '</body></html>';
	}

	/**
	 * `display.container_width` of the page (SET-CC-P1): course pages use the
	 * course value (overridable per course), all others the site value.
	 *
	 * @param string $path Learning area path.
	 * @return string `theme`, `wide` or `full`.
	 */
	public static function container_width( string $path ): string {
		try {
			$course = 1 === preg_match( '#^(?:course|kurs)/([0-9a-f-]+)#', trim( $path, '/' ), $m ) ? LearnerQueries::current()->course_id( $m[1] ) : 0;
			$width  = (string) ( $course > 0 ? EffectiveSettings::for_course( 'display.container_width', $course ) : Settings::get( 'display.container_width' ) );
		} catch ( \Throwable $e ) {
			unset( $e );
			$width = 'theme';
		}
		return in_array( $width, array( 'theme', 'wide', 'full' ), true ) ? $width : 'theme';
	}

	/**
	 * Label of a main button: the operator text of `display.button_labels`
	 * (all languages) or the translated default (SET-CC-16).
	 *
	 * @param string $key      `enroll`, `complete`, `start_exam`, `submit_exam`.
	 * @param string $fallback Translated default text.
	 * @return string
	 */
	public static function button_label( string $key, string $fallback ): string {
		$labels = (array) Settings::get( 'display.button_labels' );
		$own    = trim( (string) ( $labels[ $key ] ?? '' ) );
		return '' === $own ? $fallback : $own;
	}

	/**
	 * Logo of the learning area (`display.logo_id`, SET-CC-16): the website
	 * name is the alternative text.
	 *
	 * @return string
	 */
	public static function logo(): string {
		$id = (int) Settings::get( 'display.logo_id' );
		if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			return '';
		}
		$alt = '' !== (string) Settings::get( 'general.learning_area_title' ) ? (string) Settings::get( 'general.learning_area_title' ) : wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$img = wp_get_attachment_image(
			$id,
			'medium',
			false,
			array(
				'class'   => 'ccap-logo',
				'alt'     => $alt,
				'loading' => 'eager',
			)
		);
		return '' === $img ? '' : $img . ' ';
	}

	/**
	 * Accent colour as CSS (`display.accent_color`, validated `#rrggbb` with
	 * contrast ≥ 4.5:1 to white; SET-CC-16, R0-27 SET-041).
	 *
	 * @return string
	 */
	public static function brand_css(): string {
		$accent = (string) Settings::get( 'display.accent_color' );
		if ( 1 !== preg_match( '/^#[0-9a-f]{6}$/', $accent ) ) {
			return '';
		}
		return 'body.ccap-learning{--ccap-accent:' . $accent . '}.ccap-learning .ccap-button{background:var(--ccap-accent);border-color:var(--ccap-accent);color:#fff}.ccap-learning progress{accent-color:var(--ccap-accent)}';
	}

	/**
	 * Footer with the links to the legal texts (SET-CC-06) and the
	 * accessibility statement (SET-CC-18); only links that are set.
	 *
	 * @return string
	 */
	public static function footer(): string {
		$items = \CourseCaptain\Settings\LegalLinks::list_items();
		$page  = (int) Settings::get( 'display.accessibility_page_id' );
		$post  = $page > 0 ? get_post( $page ) : null;
		if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
			$items .= '<li><a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html__( 'Accessibility statement', 'course-captain' ) . '</a></li>';
		}
		if ( '' === $items ) {
			return '';
		}
		return '<footer class="ccap-footer"><nav aria-label="' . esc_attr__( 'Legal information', 'course-captain' ) . '"><ul class="ccap-footer-links">' . $items . '</ul></nav></footer>';
	}

	/**
	 * "My learning" list.
	 *
	 * @param Actor $actor Learner.
	 * @return string
	 */
	public static function my_learning( Actor $actor ): string {
		$courses = LearnerQueries::current()->my_courses( $actor );
		$catalog = self::page_url( 'general.catalog_page_id' );
		// SET-CC-P1: `general.catalog_page_id` – link to the catalogue page when one is set.
		$find = '' === $catalog ? '' : '<p><a href="' . esc_url( $catalog ) . '">' . esc_html__( 'Find courses in the catalogue', 'course-captain' ) . '</a></p>';
		if ( array() === $courses ) {
			return '<p>' . esc_html__( 'You are not enrolled in any course yet.', 'course-captain' ) . '</p>' . $find;
		}
		$html = '<ul class="ccap-cards">';
		foreach ( $courses as $course ) {
			$html .= '<li class="ccap-card"><h2' . Locale::content_attributes( CourseStructure::course_language( (int) $course['course_id'] ) ) . '><a href="' . esc_url( self::url( 'kurs/' . $course['course_uid'] ) ) . '">' . esc_html( $course['title'] ) . '</a></h2>';
			$html .= '<p class="ccap-state">' . esc_html( self::state_label( $course['access']['state'] ) ) . '</p>';
			$html .= '<p><span class="screen-reader-text">' . esc_html__( 'Progress:', 'course-captain' ) . ' </span><progress max="100" value="' . (int) $course['progress']['completion_percent'] . '" aria-label="' . esc_attr__( 'Course progress', 'course-captain' ) . '"></progress> ' . esc_html( sprintf( /* translators: %s: percentage, e.g. "75 %" */ __( '%s completed', 'course-captain' ), Locale::percent( (int) $course['progress']['completion_percent'] ) ) ) . '</p>';
			if ( null !== $course['resume'] ) {
				$html .= '<p><a class="ccap-button" href="' . esc_url( self::lesson_url( $course['course_uid'], (int) $course['resume']['lesson_id'] ) ) . '">' . esc_html( sprintf( /* translators: %s: lesson title */ __( 'Continue: %s', 'course-captain' ), $course['resume']['title'] ) ) . '</a></p>';
			}
			$html .= '</li>';
		}
		return $html . '</ul>' . $find;
	}

	/**
	 * Permalink of a page setting (`general.catalog_page_id`,
	 * `general.learner_home_page_id`): only a published page counts; a
	 * missing page falls back to '' (the caller uses the learning area).
	 *
	 * @param string $key Register key.
	 * @return string
	 */
	public static function page_url( string $key ): string {
		$id   = (int) Settings::get( $key );
		$page = $id > 0 ? get_post( $id ) : null;
		return $page instanceof \WP_Post && 'page' === $page->post_type && 'publish' === $page->post_status ? (string) get_permalink( $page ) : '';
	}

	/**
	 * One-time message on "My learning" (after joining a group).
	 *
	 * @param Actor $actor Learner.
	 * @return string
	 */
	private static function flash_notice( Actor $actor ): string {
		$flash = self::take_flash( $actor->user_id );
		if ( null === $flash || 'joined' !== $flash['code'] ) {
			return '';
		}
		return '<p class="ccap-status" role="status">' . esc_html__( 'You have joined the group. The group courses are listed below.', 'course-captain' ) . '</p>';
	}

	/**
	 * Status after a form post in the course room.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function course_status( string $status ): string {
		if ( '' === $status ) {
			return '';
		}
		if ( 'enrolled' === $status ) {
			return '<p class="ccap-status" role="status">' . esc_html__( 'You are enrolled. You can start learning now.', 'course-captain' ) . '</p>';
		}
		return '<p class="ccap-error" role="alert">' . esc_html( RestContract::user_message( $status ) ) . '</p>';
	}

	/**
	 * Self-enrollment (R0-11 L-02, F2.1): form when the course is open for it
	 * and the learner has no full access; otherwise the contact hint.
	 *
	 * @param Actor                $actor Learner.
	 * @param array<string, mixed> $room  Course room.
	 * @return string
	 */
	public static function enrollment( Actor $actor, array $room ): string {
		$state = (string) $room['access']['state'];
		if ( ! in_array( $state, array( 'not_granted', 'limited', 'expired' ), true ) ) {
			return '';
		}
		$course  = (int) $room['course_id'];
		$service = SelfEnrollment::current();
		if ( $service->enrolled( $actor, $course ) ) {
			return '';
		}
		$html = '<section class="ccap-enroll" aria-labelledby="ccap-enroll-heading"><h2 id="ccap-enroll-heading">' . esc_html__( 'Access to this course', 'course-captain' ) . '</h2>';
		if ( SelfEnrollment::closed( $course ) ) {
			// UI-F2 / E-108: no enroll button while the enrollment is closed.
			return $html . '<p class="ccap-enroll-closed">' . esc_html__( 'Enrollment for this course is closed. New learners cannot enroll at the moment; people who are already enrolled keep their access.', 'course-captain' ) . '</p></section>';
		}
		if ( ! $service->open( $course ) ) {
			return $html . '<p>' . esc_html__( 'Access to this course is granted by the course administration. Please contact them if you want to take part.', 'course-captain' ) . '</p></section>';
		}
		$days  = EffectiveSettings::for_course( 'enrollment.default_access_days', $course );
		$html .= '<p id="ccap-enroll-hint">' . esc_html(
			is_int( $days ) && $days > 0
				? sprintf(
					/* translators: %s: number of days */
					_n( 'You can enroll yourself for free and get access at once. The access is valid for %s day.', 'You can enroll yourself for free and get access at once. The access is valid for %s days.', $days, 'course-captain' ),
					number_format_i18n( $days )
				)
				: __( 'You can enroll yourself for free and get access at once.', 'course-captain' )
		) . '</p>';
		$html .= '<form method="post" action="' . esc_url( self::url( 'kurs/' . self::course_uid( $course ) ) ) . '">' . wp_nonce_field( self::ENROLL_NONCE . ':' . $course, '_wpnonce', false, false ) . '<input type="hidden" name="ccap_do" value="enroll">';
		$html .= '<button type="submit" class="ccap-button" aria-describedby="ccap-enroll-hint">' . esc_html( self::button_label( 'enroll', __( 'Enroll for free', 'course-captain' ) ) ) . '</button></form>';
		return $html . '</section>';
	}

	/**
	 * Enroll from the course room form; returns the status.
	 *
	 * @param Actor                $actor  Learner.
	 * @param int                  $course Course.
	 * @param array<string, mixed> $post   Unslashed POST data.
	 * @return string
	 */
	private static function enroll( Actor $actor, int $course, array $post ): string {
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::ENROLL_NONCE . ':' . $course ) ) {
			return 'ccap_invalid_token';
		}
		try {
			SelfEnrollment::current()->enroll( $actor, $course );
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
		return 'enrolled';
	}

	/**
	 * Three progress dimensions of the learner (Kap. 28, R0-08 §5): shown
	 * once the learner has or had access to the course.
	 *
	 * @param Actor                $actor Learner.
	 * @param array<string, mixed> $room  Course room.
	 * @return string
	 */
	private static function dimensions( Actor $actor, array $room ): string {
		if ( $actor->user_id <= 0 || 'not_granted' === (string) $room['access']['state'] ) {
			return '';
		}
		return ProgressPanel::html( \CourseCaptain\Progress\ProgressDimensions::current()->own( $actor, (int) $room['course_id'] ), 'ccap-progress' );
	}

	/**
	 * Entry to practice (smart trainer, mistakes, due reviews; R0-11 L-05).
	 *
	 * @param array<string, mixed> $room Course room.
	 * @return string
	 */
	private static function practice( array $room ): string {
		if ( ! in_array( (string) $room['access']['state'], array( 'active', 'limited' ), true ) ) {
			return '';
		}
		$course = (int) $room['course_id'];
		$html   = '<section class="ccap-practice" aria-labelledby="ccap-practice-heading"><h2 id="ccap-practice-heading">' . esc_html__( 'Practice', 'course-captain' ) . '</h2><ul>';
		$html  .= '<li><a href="' . esc_url( TrainerView::url( $course ) ) . '">' . esc_html__( 'Practice overview and learning stages', 'course-captain' ) . '</a></li>';
		$html  .= '<li><a href="' . esc_url( TrainerView::url( $course, 'smart' ) ) . '">' . esc_html__( 'Smart practice', 'course-captain' ) . '</a></li>';
		$html  .= \CourseCaptain\Settings\Modules::course_feature( 'mistake_training', $course ) ? '<li><a href="' . esc_url( TrainerView::url( $course, 'fehler' ) ) . '">' . esc_html__( 'Practise mistakes', 'course-captain' ) . '</a></li>' : '';
		$html  .= '<li><a href="' . esc_url( TrainerView::url( $course, 'faellig' ) ) . '">' . esc_html__( 'Due reviews', 'course-captain' ) . '</a></li>';
		$html  .= '<li><a href="' . esc_url( TrainerView::url( $course, 'pruefungsfehler' ) ) . '">' . esc_html__( 'Practise exam mistakes', 'course-captain' ) . '</a></li>';
		return $html . '</ul></section>';
	}

	/**
	 * Course room.
	 *
	 * @param array<string, mixed> $room Course room data.
	 * @return string
	 */
	public static function course_room( array $room ): string {
		$resume = $room['resume']['lesson_id'] ?? 0;
		$lang   = Locale::content_attributes( CourseStructure::course_language( (int) $room['course_id'] ) );
		$uid    = self::course_uid( (int) $room['course_id'] );
		$html   = '<p class="ccap-state">' . esc_html( self::state_label( (string) $room['access']['state'] ) ) . '</p>';
		$html  .= '<nav aria-label="' . esc_attr__( 'Course contents', 'course-captain' ) . '"><ol class="ccap-modules">';
		foreach ( (array) $room['modules'] as $module ) {
			$html .= '<li><h2' . $lang . '>' . esc_html( (string) $module['title'] ) . '</h2>';
			if ( null !== $module['locked_reason'] ) {
				$html .= '<p class="ccap-locked">' . esc_html( self::lock_label( (string) $module['locked_reason'], $module['unlock_at'] ) ) . '</p>';
			}
			$html .= '<ol class="ccap-lessons">';
			foreach ( (array) $module['lessons'] as $lesson ) {
				$current = (int) $lesson['lesson_id'] === $resume ? ' aria-current="step"' : '';
				$label   = '<span' . $lang . '>' . esc_html( (string) $lesson['title'] ) . '</span> <span class="ccap-lesson-state">(' . esc_html( null === $lesson['locked_reason'] ? self::lesson_label( (string) $lesson['state'] ) : self::lock_label( (string) $lesson['locked_reason'], null ) ) . ')</span>';
				$html   .= '<li id="lesson-' . (int) $lesson['lesson_id'] . '">' . ( null === $lesson['locked_reason'] ? '<a href="' . esc_url( self::lesson_url( $uid, (int) $lesson['lesson_id'] ) ) . '"' . $current . '>' . $label . '</a>' : '<span>' . $label . '</span>' ) . '</li>';
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
	 * `display.catalog_sort` → order of {@see LearnerQueries::catalog()}.
	 *
	 * @var array<string, string>
	 */
	private const CATALOG_SORT = array(
		'manual' => 'menu_order',
		'title'  => 'title',
		'newest' => 'date',
	);

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
		$enrollment = SelfEnrollment::current();
		// SET-CC-P1: without an explicit block order the site setting `display.catalog_sort` decides.
		$orderby   = (string) ( $attributes['orderBy'] ?? '' );
		$orderby   = '' === $orderby ? self::CATALOG_SORT[ (string) Settings::get( 'display.catalog_sort' ) ] ?? 'menu_order' : $orderby;
		$elements  = (array) Settings::get( 'display.card_elements' );
		$structure = CourseStructure::current();
		foreach ( $queries->catalog( (int) ( $attributes['perPage'] ?? 12 ), $orderby ) as $course ) {
			if ( ! empty( $attributes['showOnlyEnrolled'] ) && ! isset( $enrolled[ (int) $course->ID ] ) ) {
				continue;
			}
			$html .= '<li>';
			// `display.card_elements` (SET-CC-P1): cover, summary, lesson count, progress; empty = title only.
			if ( in_array( 'cover', $elements, true ) && has_post_thumbnail( $course ) ) {
				$html .= get_the_post_thumbnail(
					$course,
					'medium',
					array(
						'class' => 'ccap-card-cover',
						'alt'   => '',
					)
				);
			}
			$html .= '<a href="' . esc_url( (string) get_permalink( $course ) ) . '">' . esc_html( $course->post_title ) . '</a>';
			if ( in_array( 'summary', $elements, true ) && '' !== trim( $course->post_excerpt ) ) {
				$html .= ' <span class="ccap-card-summary">' . esc_html( wp_strip_all_tags( $course->post_excerpt ) ) . '</span>';
			}
			if ( in_array( 'lesson_count', $elements, true ) ) {
				$lessons = 0;
				foreach ( $structure->lessons_of( array_map( static fn( array $m ): int => (int) $m['module_id'], $structure->modules( (int) $course->ID ) ) ) as $list ) {
					$lessons += count( array_filter( $list, static fn( \WP_Post $lesson ): bool => 'publish' === $lesson->post_status ) );
				}
				/* translators: %s: number of lessons */
				$html .= ' <span class="ccap-card-lessons">' . esc_html( sprintf( _n( '%s lesson', '%s lessons', $lessons, 'course-captain' ), number_format_i18n( $lessons ) ) ) . '</span>';
			}
			if ( in_array( 'progress', $elements, true ) && ( $attributes['showProgress'] ?? true ) && isset( $enrolled[ (int) $course->ID ] ) ) {
				$html .= ' <span class="ccap-progress">' . esc_html( sprintf( /* translators: %s: percentage, e.g. "75 %" */ __( '%s completed', 'course-captain' ), Locale::percent( (int) $enrolled[ (int) $course->ID ]['progress']['completion_percent'] ) ) ) . '</span>';
			}
			$room   = self::url( 'kurs/' . $identity->ensure( $course ) );
			$active = isset( $enrolled[ (int) $course->ID ] ) && in_array( $enrolled[ (int) $course->ID ]['access']['state'], array( 'active', 'limited', 'suspended', 'pending' ), true );
			if ( ! $active && $enrollment->open( (int) $course->ID ) ) {
				// L-02: visible entry to self-enrollment (the course room asks guests to log in first).
				$html .= ' <a class="ccap-enroll-link" href="' . esc_url( $room ) . '">' . esc_html( sprintf( /* translators: %s: course */ __( 'Enroll in %s for free', 'course-captain' ), $course->post_title ) ) . '</a></li>';
				continue;
			}
			$html .= ' <a class="screen-reader-text" href="' . esc_url( $room ) . '">' . esc_html( sprintf( /* translators: %s: course */ __( 'Open %s in the learning area', 'course-captain' ), $course->post_title ) ) . '</a></li>';
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
		// SET-CC-P1: learners land on `general.learner_home_page_id` when set (fallback: the learning area).
		$home = self::page_url( 'general.learner_home_page_id' );
		return '<a class="ccap-button" href="' . esc_url( '' === $home ? self::url() : $home ) . '">' . esc_html( $label ) . '</a>';
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
		// Logical properties only (RTL without a second stylesheet, I18N-R-12); visible focus everywhere (A11Y-003).
		return 'html{scroll-padding-block-start:6rem}body.ccap-learning{margin:0;font:1rem/1.5 system-ui,sans-serif;overflow-wrap:anywhere;background:#fff;color:#1d2327}'
			. '.ccap-skip{position:absolute;inset-block-start:1rem;inset-inline-start:1rem;background:#fff;color:#1d2327;padding:.5rem;z-index:10}'
			. '.ccap-skip:not(:focus){width:1px;height:1px;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap}'
			. '.ccap-bar{padding:.75rem 1rem;border-block-end:1px solid #8c8f94}.ccap-logo{max-block-size:3rem;inline-size:auto;vertical-align:middle;margin-inline-end:.75rem}.ccap-footer{max-inline-size:60rem;margin-inline:auto;padding:1rem;box-sizing:border-box;border-block-start:1px solid #8c8f94}.ccap-footer-links{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:.5rem 1.5rem}#ccap-main{max-inline-size:60rem;margin-inline:auto;padding:1rem;box-sizing:border-box}'
			. '.ccap-cards,.ccap-modules,.ccap-lessons,.ccap-qnav,.ccap-result-list{padding-inline-start:1.25rem}.ccap-card{margin-block-end:1rem}'
			. '.ccap-button{display:inline-block;padding:.6rem 1rem;border:2px solid currentColor;border-radius:.25rem;text-decoration:none;min-block-size:44px;box-sizing:border-box;font:inherit;background:#fff;color:inherit;cursor:pointer}'
			. ':focus-visible,a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,[tabindex]:focus-visible{outline:3px solid #1d4ed8;outline-offset:2px}'
			. 'img,video,iframe{max-inline-size:100%;block-size:auto}'
			. '.ccap-media-frame iframe{inline-size:100%;aspect-ratio:16/9;border:0}.ccap-external-media{border:1px solid #8c8f94;border-radius:.25rem;padding:.75rem 1rem;background:#f6f7f7}.ccap-external-media input[type=checkbox]{inline-size:1.5rem;block-size:1.5rem;vertical-align:middle}'
			. '.screen-reader-text{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}'
			. 'progress{max-inline-size:100%;vertical-align:middle}'
			. '.ccap-width-wide #ccap-main,.ccap-width-wide .ccap-footer{max-inline-size:80rem}.ccap-width-full #ccap-main,.ccap-width-full .ccap-footer{max-inline-size:none}'
			. '.ccap-breadcrumbs ol{list-style:none;padding:0;margin:0 0 .5rem;display:flex;flex-wrap:wrap;gap:.25rem}.ccap-breadcrumbs li+li::before{content:"›";margin-inline-end:.25rem}'
			. '.ccap-question{margin-block:1.5rem}fieldset{border:1px solid #8c8f94;padding:.75rem 1rem;min-inline-size:0;margin:0}legend{font-weight:600;padding-inline:.25rem}'
			. '.ccap-options{padding:0}.ccap-options li{list-style:none;margin-block:.5rem}.ccap-options input[type=radio],.ccap-options input[type=checkbox],.ccap-question input[type=checkbox]{inline-size:1.5rem;block-size:1.5rem;vertical-align:middle;margin-inline-end:.5rem}'
			. 'input[type=text],select{font:inherit;min-block-size:44px;max-inline-size:100%;box-sizing:border-box;padding-inline:.5rem}'
			. '.ccap-exam-bar{position:sticky;inset-block-start:0;background:#fff;border-block-end:1px solid #8c8f94;padding-block:.25rem;z-index:5}.ccap-timer-line{margin:0;display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}'
			. '@media (max-height:32rem){.ccap-exam-bar{position:static}html{scroll-padding-block-start:0}}'
			. '.ccap-lesson-nav{padding:0;display:flex;flex-wrap:wrap;gap:1rem;list-style:none}.ccap-status{border-inline-start:4px solid #00a32a;padding-inline-start:.75rem}'
			. '.ccap-error{border-inline-start:4px solid #b32d2e;padding-inline-start:.75rem}'
			. '.ccap-offline{position:fixed;inset-inline:0;inset-block-end:0;margin:0;padding:.75rem 1rem;background:#fcf0f1;color:#1d2327;border-block-start:4px solid #b32d2e;z-index:20}.ccap-model-answer{border-inline-start:4px solid #8c8f94;padding-inline-start:.75rem}[hidden]{display:none!important}'
			. '@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;scroll-behavior:auto!important}}'
			. '@media (forced-colors:active){:focus-visible{outline:3px solid CanvasText}.ccap-button{border-color:ButtonText}}';
	}
}
