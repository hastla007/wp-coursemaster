<?php
/**
 * Server-rendered lesson view in the learning area (R1-29 B-01, R1-16 A-1 part).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Access\ContentProtection;
use CourseCaptain\Access\ProtectedFiles;
use CourseCaptain\Access\RightsGate;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityRegistry;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Activity\ExternalMedia;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Locale;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Rest\RestContract;

defined( 'ABSPATH' ) || exit;

/**
 * `/<area>/kurs/<course-uid>/lektion/<lesson-id>`. The lesson post type
 * stays non-public; this view is the only learner entry point.
 *
 * - The content gate (`ContentGate::lesson()`) decides on every request;
 *   a locked lesson answers 403 with the lock reason as text, a lesson
 *   outside the course 404.
 * - The text runs through `the_content`, so `ContentProtection` and
 *   other content filters apply; the page is never page-cached.
 * - Completion works without JavaScript (forms, PRG, nonce) like the
 *   exam room: `manual_confirm` (button), `view` (time since opening,
 *   signed), `downloaded` (download button records the download and
 *   redirects to the checked file endpoint). Other rules show their
 *   condition as text.
 * - Accessibility (R0-15): the lesson title is the page's only `h1`
 *   (rendered by the learning area), sections are named by headings,
 *   status after a form post is text in a `status`/`alert` region,
 *   previous/next navigation is a named `nav`.
 */
final class LessonView {

	public const NONCE = 'ccap_lesson';

	/**
	 * Page parts for a lesson.
	 *
	 * @param Actor  $actor     Learner.
	 * @param int    $course_id Course.
	 * @param int    $lesson_id Lesson.
	 * @param string $status    Status code after a form post ('' = none).
	 * @return array{status: int, title: string, title_attr: string, body: string}
	 * @throws CourseException 404 for unknown courses or lessons outside the course.
	 */
	public static function render( Actor $actor, int $course_id, int $lesson_id, string $status = '' ): array {
		$room  = LearnerQueries::current()->course( $actor, $course_id );
		$lang  = Locale::content_attributes( CourseStructure::course_language( $course_id ) );
		$found = self::locate( $room, $lesson_id );
		$back  = '<p class="ccap-back"><a href="' . esc_url( LearningArea::url( 'kurs/' . LearningArea::course_uid( $course_id ) ) . '#lesson-' . $lesson_id ) . '">' . esc_html(
			sprintf(
				/* translators: %s: course title */
				__( 'Back to the course: %s', 'course-captain' ),
				(string) $room['title']
			)
		) . '</a></p>';
		$back   = self::breadcrumbs( $room, $found, $course_id, $lang ) . $back;
		$gate   = ContentGate::current()->lesson( $actor, $course_id, $lesson_id );
		$lesson = get_post( $lesson_id );
		if ( ! $lesson instanceof \WP_Post ) {
			throw new CourseException( 'ccap_not_found', 'Unknown lesson.', 404 );
		}
		if ( ! $gate->allowed() ) {
			$unlock = $gate->unlock_at ?? $found['module']['unlock_at'];
			return array(
				'status'     => 403,
				'title'      => $lesson->post_title,
				'title_attr' => $lang,
				'body'       => $back . '<p class="ccap-locked" role="alert">' . esc_html( LearningArea::lock_label( (string) $gate->reason, null === $unlock ? null : (string) $unlock ) ) . '</p>',
			);
		}

		// R0-10 §2.2: a foreign lesson with unclear or restricted rights stays listed, its content is not shown.
		$rights   = RightsGate::current();
		$withheld = $rights->post( $lesson_id );
		if ( null !== $withheld ) {
			return array(
				'status'     => 200,
				'title'      => $lesson->post_title,
				'title_attr' => $lang,
				'body'       => $back . '<p class="ccap-withheld" role="status">' . esc_html( RightsGate::exempt( $actor->user_id, $course_id ) ? RightsGate::notice( $withheld ) : __( 'This content is not available at the moment.', 'course-captain' ) ) . '</p>' . self::navigation( $room, $lesson_id, $lang ),
			);
		}

		// Opening counts as access: the lesson becomes "in progress" and the resume target (no completion).
		try {
			ActivityProgress::current()->interact( $actor, $course_id, $lesson_id, 'position' );
		} catch ( CourseException $e ) {
			unset( $e );
		}
		$definition = ActivityService::definition( $lesson_id );
		$type       = '' === $definition['type'] ? 'lesson' : $definition['type'];
		$provider   = ActivityRegistry::instance()->get( $type );
		$rule       = null === $provider ? array(
			'type'   => 'none',
			'params' => array(),
		) : ActivityRegistry::normalize_rule( $provider, $definition['rule'] );
		$state      = (array) ActivityProgress::current()->state( $actor->user_id, $lesson_id );
		$media      = self::withheld_media( $rights, $actor, $course_id, $type, $definition['settings'] );

		$html = $back . self::status_message( $status );
		if ( ! \CourseCaptain\Settings\Modules::activity_enabled( $type ) ) {
			// SET-CC-24: the module is switched off; the lesson text stays readable, the activity is hidden.
			$html .= '<article class="ccap-lesson">' . self::content( $actor, $lesson, $lang, $rights, $course_id ) . '<section class="ccap-activity" aria-labelledby="ccap-activity-heading"><h2 id="ccap-activity-heading">' . esc_html__( 'Activity', 'course-captain' ) . '</h2><p>' . esc_html__( 'This activity is not available on this website at the moment. Your previous work is kept.', 'course-captain' ) . '</p></section></article>';
		} else {
			$activity = null !== $media && 'video' === $type
				? '<section class="ccap-activity" aria-labelledby="ccap-activity-heading"><h2 id="ccap-activity-heading">' . esc_html__( 'Video', 'course-captain' ) . '</h2><p class="ccap-withheld">' . esc_html( $media ) . '</p></section>'
				: ( 'assignment' === $type ? AssignmentView::section( $actor, $course_id, $lesson_id, $lang ) : ( 'quiz' === $type ? QuizView::section( $actor, $course_id, $lesson_id, $lang, $status ) : self::activity( $type, $definition['settings'], $course_id ) ) );
			$html    .= '<article class="ccap-lesson">' . self::content( $actor, $lesson, $lang, $rights, $course_id ) . $activity . '</article>';
			$html    .= self::completion( $actor, $course_id, $lesson_id, $type, $rule, $state, $definition['settings'], $media );
		}
		$html .= self::navigation( $room, $lesson_id, $lang );
		return array(
			'status'     => 200,
			'title'      => $lesson->post_title,
			'title_attr' => $lang,
			'body'       => $html,
		);
	}

	/**
	 * Handle a form post; returns the status shown after the redirect and, for downloads, the file URL.
	 *
	 * @param Actor                $actor     Learner.
	 * @param int                  $course_id Course.
	 * @param int                  $lesson_id Lesson.
	 * @param array<string, mixed> $post      Unslashed POST data.
	 * @param array<string, mixed> $files     Uploaded files (assignment form).
	 * @return array{status: string, location: string|null}
	 */
	public static function handle( Actor $actor, int $course_id, int $lesson_id, array $post, array $files = array() ): array {
		$result = static fn( string $status, ?string $location = null ): array => array(
			'status'   => $status,
			'location' => $location,
		);
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::NONCE . ':' . $lesson_id ) ) {
			return $result( 'ccap_invalid_token' );
		}
		try {
			self::locate( LearnerQueries::current()->course( $actor, $course_id ), $lesson_id );
			$definition = ActivityService::definition( $lesson_id );
			$type       = '' === $definition['type'] ? 'lesson' : $definition['type'];
			$provider   = ActivityRegistry::instance()->get( $type );
			$rule       = null === $provider ? 'none' : ActivityRegistry::normalize_rule( $provider, $definition['rule'] )['type'];
			$progress   = ActivityProgress::current();
			switch ( (string) ( $post['ccap_do'] ?? '' ) ) {
				case 'complete':
					if ( 'manual_confirm' !== $rule ) {
						return $result( 'ccap_invalid_param' );
					}
					$state = $progress->interact( $actor, $course_id, $lesson_id, 'confirm' );
					return $result( 'completed' === ( $state['status'] ?? '' ) ? 'completed' : 'saved' );
				case 'view':
					if ( 'view' !== $rule ) {
						return $result( 'ccap_invalid_param' );
					}
					$opened = (int) ( $post['opened'] ?? 0 );
					if ( $opened <= 0 || ! hash_equals( self::sign( $actor, $lesson_id, $opened ), (string) ( $post['opened_sig'] ?? '' ) ) ) {
						return $result( 'ccap_invalid_token' );
					}
					$state = $progress->interact( $actor, $course_id, $lesson_id, 'view', array( 'seconds' => max( 0, time() - $opened ) ) );
					return $result( 'completed' === ( $state['status'] ?? '' ) ? 'completed' : 'view_too_short' );
				case 'assignment_save':
				case 'assignment_submit':
					if ( 'assignment' !== $type ) {
						return $result( 'ccap_invalid_param' );
					}
					if ( ! \CourseCaptain\Settings\Modules::enabled( \CourseCaptain\Settings\Modules::ASSIGNMENTS ) ) {
						return $result( 'ccap_module_disabled' );
					}
					return $result( AssignmentView::handle( $actor, $course_id, $lesson_id, (string) $post['ccap_do'], $post, $files ) );
				case 'quiz_answer':
				case 'quiz_reveal':
				case 'quiz_restart':
					// UI-F8 (h5 L-3): quiz lessons answered without JavaScript.
					return $result( QuizView::handle( $actor, $course_id, $lesson_id, (string) $post['ccap_do'], $post ) );
				case 'download':
					$uid = (string) ( $definition['settings']['media_uid'] ?? '' );
					if ( 'file' !== $type || 1 !== preg_match( '/^[0-9a-f-]{36}$/', $uid ) ) {
						return $result( 'ccap_invalid_param' );
					}
					if ( null !== RightsGate::current()->post( $lesson_id ) || ( null !== RightsGate::current()->medium_uid( $uid ) && ! RightsGate::exempt( $actor->user_id, $course_id ) ) ) {
						// R0-10 §2.2: a withheld file is neither delivered nor counted as downloaded.
						return $result( 'ccap_not_found' );
					}
					$progress->interact( $actor, $course_id, $lesson_id, 'download' );
					return $result( 'downloaded', ProtectedFiles::url( $uid ) );
				default:
					return $result( 'ccap_invalid_param' );
			}
		} catch ( CourseException $e ) {
			return $result( $e->error_code );
		}
	}

	/**
	 * Find the lesson in the course room.
	 *
	 * @param array<string, mixed> $room      Course room.
	 * @param int                  $lesson_id Lesson.
	 * @return array{module: array<string, mixed>, lesson: array<string, mixed>}
	 * @throws CourseException 404 when the lesson is not a published lesson of the course.
	 */
	private static function locate( array $room, int $lesson_id ): array {
		foreach ( (array) $room['modules'] as $module ) {
			foreach ( (array) $module['lessons'] as $lesson ) {
				if ( (int) $lesson['lesson_id'] === $lesson_id ) {
					return array(
						'module' => (array) $module,
						'lesson' => (array) $lesson,
					);
				}
			}
		}
		throw new CourseException( 'ccap_not_found', 'Unknown lesson.', 404 );
	}

	/**
	 * Breadcrumb navigation My learning › course › module › lesson
	 * (`display.breadcrumbs`, per course overridable; SET-CC-P1).
	 *
	 * @param array<string, mixed> $room      Course room.
	 * @param array<string, mixed> $found     Module and lesson of the room.
	 * @param int                  $course_id Course.
	 * @param string               $lang      Content language attributes.
	 * @return string
	 */
	private static function breadcrumbs( array $room, array $found, int $course_id, string $lang ): string {
		if ( true !== \CourseCaptain\Settings\EffectiveSettings::for_course( 'display.breadcrumbs', $course_id ) ) {
			return '';
		}
		return '<nav class="ccap-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'course-captain' ) . '"><ol>'
			. '<li><a href="' . esc_url( LearningArea::url() ) . '">' . esc_html__( 'My learning', 'course-captain' ) . '</a></li>'
			. '<li><a href="' . esc_url( LearningArea::url( 'kurs/' . LearningArea::course_uid( $course_id ) ) ) . '"' . $lang . '>' . esc_html( (string) $room['title'] ) . '</a></li>'
			. '<li' . $lang . '>' . esc_html( (string) ( $found['module']['title'] ?? '' ) ) . '</li>'
			. '<li aria-current="page"' . $lang . '>' . esc_html( (string) ( $found['lesson']['title'] ?? '' ) ) . '</li></ol></nav>';
	}

	/**
	 * Lesson text through `the_content` (content filters, `ContentProtection`).
	 *
	 * Embedded media whose rights withhold them (R0-10 §2.2) are replaced by
	 * the placeholder ({@see WithheldInlineMedia}).
	 *
	 * @param Actor      $actor     Learner.
	 * @param \WP_Post   $lesson    Lesson.
	 * @param string     $lang      Content language attributes.
	 * @param RightsGate $rights    Rights gate of the request.
	 * @param int        $course_id Course.
	 * @return string
	 */
	private static function content( Actor $actor, \WP_Post $lesson, string $lang, RightsGate $rights, int $course_id ): string {
		if ( ContentProtection::is_locked( (int) $lesson->ID, $actor->user_id ) ) {
			return ContentProtection::notice();
		}
		if ( '' === trim( $lesson->post_content ) ) {
			return '';
		}
		global $post;
		$previous = $post;
		$post     = $lesson; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below; filters read the current post.
		setup_postdata( $lesson );
		$html = (string) apply_filters( 'the_content', $lesson->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
		$html = WithheldInlineMedia::filter( $html, array( $rights, 'medium_uid' ), RightsGate::exempt( $actor->user_id, $course_id ) );
		$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore.
		if ( $previous instanceof \WP_Post ) {
			setup_postdata( $previous );
		}
		return '<div class="ccap-lesson-content"' . $lang . '>' . $html . '</div>';
	}

	/**
	 * Text in place of the protected medium of a video or file lesson when
	 * the medium is withheld (R0-10 §2.2): the neutral placeholder "Media
	 * not available" for learners (no error, no path), the reason for
	 * editors of the course. Null when the medium may be shown.
	 *
	 * @param RightsGate           $rights    Rights gate of the request.
	 * @param Actor                $actor     Viewer.
	 * @param int                  $course_id Course.
	 * @param string               $type      Activity type.
	 * @param array<string, mixed> $settings  Activity settings.
	 * @return string|null
	 */
	private static function withheld_media( RightsGate $rights, Actor $actor, int $course_id, string $type, array $settings ): ?string {
		$uid = (string) ( $settings['media_uid'] ?? '' );
		if ( ! ( 'file' === $type || ( 'video' === $type && 'media' === ( $settings['provider'] ?? '' ) ) ) || '' === $uid ) {
			return null;
		}
		$reason = $rights->medium_uid( $uid );
		if ( null === $reason ) {
			return null;
		}
		return RightsGate::exempt( $actor->user_id, $course_id ) ? RightsGate::notice( $reason ) : RightsGate::placeholder();
	}

	/**
	 * Activity part per provider.
	 *
	 * @param string               $type      Activity type.
	 * @param array<string, mixed> $settings  Settings.
	 * @param int                  $course_id Course.
	 * @return string
	 */
	private static function activity( string $type, array $settings, int $course_id ): string {
		switch ( $type ) {
			case 'video':
				return self::video( $settings );
			case 'file':
				// The download button sits in the completion form (records the download).
				return '';
			case 'exam':
				return '<section class="ccap-activity" aria-labelledby="ccap-activity-heading"><h2 id="ccap-activity-heading">' . esc_html__( 'Exam', 'course-captain' ) . '</h2><p><a href="' . esc_url( LearningArea::url( 'kurs/' . LearningArea::course_uid( $course_id ) ) . '#ccap-exams-heading' ) . '">' . esc_html__( 'Start the exam on the course page.', 'course-captain' ) . '</a></p></section>';
			case 'flashcard':
				return '<section class="ccap-activity" aria-labelledby="ccap-activity-heading"><h2 id="ccap-activity-heading">' . esc_html__( 'Practice', 'course-captain' ) . '</h2><p>' . esc_html__( 'This practice activity cannot be used on this page yet.', 'course-captain' ) . '</p></section>';
			default:
				return '';
		}
	}

	/**
	 * Video (R0-10 §5.2, E-45a): native player for direct or protected files;
	 * YouTube/Vimeo as privacy-friendly embed (MC-2) in `always_load`; in
	 * `click_to_load` a placeholder without any connection to the foreign
	 * host and a "Load video" button ({@see self::click_to_load()}); in
	 * `link_only` only a link.
	 *
	 * @param array<string, mixed> $settings Video settings.
	 * @return string
	 */
	private static function video( array $settings ): string {
		$html       = self::player( $settings );
		$transcript = self::transcript( $settings );
		return '' !== $transcript && str_ends_with( $html, '</section>' ) ? substr( $html, 0, -10 ) . $transcript . '</section>' : $html;
	}

	/**
	 * Transcript below the player (SET-CC-18, A11Y-090): text in a
	 * disclosure, a link, or both. Shown in every external media mode,
	 * because it loads nothing from the video service.
	 *
	 * @param array<string, mixed> $settings Video settings.
	 * @return string
	 */
	private static function transcript( array $settings ): string {
		$text = trim( (string) ( $settings['transcript'] ?? '' ) );
		$url  = (string) ( $settings['transcript_url'] ?? '' );
		$html = '';
		if ( '' !== $text ) {
			$html .= '<details class="ccap-transcript"><summary>' . esc_html__( 'Transcript', 'course-captain' ) . '</summary>' . wpautop( esc_html( $text ) ) . '</details>';
		}
		if ( '' !== $url && \CourseCaptain\Activity\SettingsSchema::is_https_url( $url ) ) {
			$html .= '<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the transcript', 'course-captain' ) . '</a></p>';
		}
		return $html;
	}

	/**
	 * Player part of a video lesson.
	 *
	 * @param array<string, mixed> $settings Video settings.
	 * @return string
	 */
	private static function player( array $settings ): string {
		$html = '<section class="ccap-activity" aria-labelledby="ccap-activity-heading"><h2 id="ccap-activity-heading">' . esc_html__( 'Video', 'course-captain' ) . '</h2>';
		$uid  = (string) ( $settings['media_uid'] ?? '' );
		if ( 'media' === ( $settings['provider'] ?? '' ) && 1 === preg_match( '/^[0-9a-f-]{36}$/', $uid ) ) {
			$src       = ProtectedFiles::url( $uid );
			$subtitles = null;
		} else {
			$view = ExternalMedia::video_view( $settings );
			if ( $view['consent_required'] ) {
				return $html . self::click_to_load( $view, (int) ( $settings['duration_seconds'] ?? 0 ) ) . '</section>';
			}
			if ( null !== $view['link_url'] ) {
				return $html . self::watch_link( (string) $view['link_url'], (string) $view['foreign_host'] ) . '</section>';
			}
			if ( null !== $view['embed_url'] ) {
				$host = (string) ( $view['foreign_host'] ?? wp_parse_url( (string) $view['embed_url'], PHP_URL_HOST ) );
				return $html . '<div class="ccap-media-frame"><iframe class="ccap-embed" src="' . esc_url( (string) $view['embed_url'] ) . '" title="' . esc_attr( self::frame_title( $host ) ) . '" width="560" height="315" allow="fullscreen; picture-in-picture; encrypted-media" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>'
					. ( null !== $view['watch_url'] ? self::watch_link( (string) $view['watch_url'], $host ) : '' ) . '</section>';
			}
			if ( null !== $view['video_url'] && in_array( $settings['provider'] ?? '', array( 'youtube', 'vimeo' ), true ) ) {
				// A YouTube/Vimeo address without a recognizable video ID cannot be embedded.
				return $html . self::watch_link( (string) $view['video_url'], (string) ( $view['foreign_host'] ?? wp_parse_url( (string) $view['video_url'], PHP_URL_HOST ) ) ) . '</section>';
			}
			if ( null === $view['video_url'] ) {
				return $html . '<p>' . esc_html__( 'The video is not available yet.', 'course-captain' ) . '</p></section>';
			}
			$src       = (string) $view['video_url'];
			$subtitles = $view['subtitles_url'];
		}
		$html .= '<video controls preload="metadata" src="' . esc_url( $src ) . '">';
		if ( null !== $subtitles ) {
			$html .= '<track kind="subtitles" default src="' . esc_url( (string) $subtitles ) . '" label="' . esc_attr__( 'Subtitles', 'course-captain' ) . '">';
		}
		return $html . '<a href="' . esc_url( $src ) . '">' . esc_html__( 'Download the video', 'course-captain' ) . '</a></video></section>';
	}

	/**
	 * Link to the video page of the foreign service (new tab).
	 *
	 * @param string $url  Page URL.
	 * @param string $host Foreign host.
	 * @return string
	 */
	private static function watch_link( string $url, string $host ): string {
		return '<p class="ccap-media-link"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html(
			sprintf(
				/* translators: %s: name of the video service or host name */
				__( 'Watch the video on %s (opens in a new tab)', 'course-captain' ),
				ExternalMedia::service( $host )['name']
			)
		) . '</a></p>';
	}

	/**
	 * Accessible name of an embedded player.
	 *
	 * @param string $host Host of the player.
	 * @return string
	 */
	private static function frame_title( string $host ): string {
		return sprintf(
			/* translators: %s: name of the video service or host name */
			__( 'Video on %s', 'course-captain' ),
			ExternalMedia::service( $host )['name']
		);
	}

	/**
	 * "Click to load" placeholder (R0-10 §5.2.2/§5.3, R0-31 MC-1a/MC-3/MC-4/MC-7).
	 *
	 * The server output contains no element that connects to the foreign
	 * host (no `iframe`, `video`, `track`, poster, `preconnect`); the same
	 * HTML for every learner (MC-8). Without JavaScript the learner gets
	 * the hint and a link to the service. The script
	 * (`assets/js/external-media.js`) reveals the "Load video" button,
	 * loads only this medium after the click, attaches subtitles only then,
	 * and honours the WP Consent API, `window.courseCaptain.consent` and,
	 * when `privacy.external_media_remember` is on, the per-service choice
	 * "always load" in `localStorage` (never pre-ticked, revocable).
	 *
	 * @param array<string, mixed> $view     Result of `ExternalMedia::video_view()`.
	 * @param int                  $duration Duration in seconds (0 = unknown).
	 * @return string
	 */
	private static function click_to_load( array $view, int $duration ): string {
		$host    = (string) $view['foreign_host'];
		$service = ExternalMedia::service( $host );
		$name    = $service['name'];
		$pending = (array) $view['pending'];
		$consent = ExternalMedia::consent();
		$attrs   = array(
			'data-ccap-external-media' => '1',
			'data-ccap-service'        => strtolower( 'YouTube' === $name || 'Vimeo' === $name ? $name : $host ),
			'data-ccap-kind'           => (string) $pending['kind'],
			'data-ccap-src'            => esc_url_raw( (string) $pending['src'] ),
			'data-ccap-title'          => self::frame_title( $host ),
			'data-ccap-category'       => $consent['category'],
			'data-ccap-remember'       => $consent['remember_offer'] ? '1' : '0',
			'data-ccap-loaded-text'    => sprintf(
				/* translators: %s: name of the video service or host name */
				__( 'The video from %s has been loaded.', 'course-captain' ),
				$name
			),
			'data-ccap-forget-text'    => sprintf(
				/* translators: %s: name of the video service or host name */
				__( 'Stop loading external media from %s automatically', 'course-captain' ),
				$name
			),
			'data-ccap-forgotten-text' => sprintf(
				/* translators: %s: name of the video service or host name */
				__( 'Saved choice removed. Media from %s will only load after a click again.', 'course-captain' ),
				$name
			),
		);
		if ( 'file' === $pending['kind'] ) {
			$attrs['data-ccap-download-text'] = __( 'Download the video', 'course-captain' );
			if ( null !== $pending['subtitles'] ) {
				$attrs['data-ccap-subtitles']       = esc_url_raw( (string) $pending['subtitles'] );
				$attrs['data-ccap-subtitles-label'] = __( 'Subtitles', 'course-captain' );
			}
		}
		$html = '<div class="ccap-external-media"';
		foreach ( $attrs as $name_attr => $value ) {
			$html .= ' ' . $name_attr . '="' . esc_attr( $value ) . '"';
		}
		$html .= '><p id="ccap-media-note">' . esc_html(
			sprintf(
				/* translators: %s: name of the video service or host name */
				__( 'This video is hosted by %s and is only loaded after your consent.', 'course-captain' ),
				$name
			)
		) . ' ' . esc_html(
			sprintf(
				/* translators: %s: name of the video service or host name */
				__( 'When the video is loaded, data such as your IP address is transferred to %s.', 'course-captain' ),
				$name
			)
		);
		if ( null !== $service['privacy_url'] ) {
			$html .= ' <a href="' . esc_url( $service['privacy_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html(
				sprintf(
					/* translators: %s: name of the video service */
					__( 'Privacy policy of %s (opens in a new tab)', 'course-captain' ),
					$name
				)
			) . '</a>';
		}
		$html .= '</p>';
		if ( $duration > 0 ) {
			$html .= '<p>' . esc_html(
				sprintf(
					/* translators: %s: duration as minutes:seconds, e.g. 4:05 */
					__( 'Length: %s', 'course-captain' ),
					sprintf( '%d:%02d', intdiv( $duration, 60 ), $duration % 60 )
				)
			) . '</p>';
		}
		// Revealed by the script; without JavaScript only the link below is offered (MC-7).
		$html .= '<div class="ccap-media-load" hidden><p><button type="button" class="ccap-button" data-ccap-load aria-describedby="ccap-media-note">' . esc_html__( 'Load video', 'course-captain' ) . '</button></p>';
		if ( $consent['remember_offer'] ) {
			// MC-3: never pre-ticked.
			$html .= '<p><input type="checkbox" id="ccap-media-remember" data-ccap-remember-box> <label for="ccap-media-remember">' . esc_html(
				sprintf(
					/* translators: %s: name of the video service or host name */
					__( 'Always load external media from %s', 'course-captain' ),
					$name
				)
			) . '</label></p>';
		}
		$html .= '</div><div class="ccap-media-frame" hidden></div><p class="ccap-media-forget" hidden><button type="button" class="ccap-button" data-ccap-forget></button></p>';
		$html .= self::watch_link( (string) $view['watch_url'], $host ) . '<p class="screen-reader-text" role="status" data-ccap-media-status></p></div>';
		wp_enqueue_script( 'ccap-external-media', plugins_url( 'assets/js/external-media.js', COURSE_CAPTAIN_FILE ), array(), COURSE_CAPTAIN_VERSION, true );
		return $html;
	}

	/**
	 * Completion state and, where the rule allows it, the form.
	 *
	 * @param Actor                $actor     Learner.
	 * @param int                  $course_id Course.
	 * @param int                  $lesson_id Lesson.
	 * @param string               $type      Activity type.
	 * @param array<string, mixed> $rule      Normalized rule.
	 * @param array<string, mixed> $state     Stored progress row.
	 * @param array<string, mixed> $settings  Settings.
	 * @param string|null          $withheld  Text in place of a withheld file (no download button).
	 * @return string
	 */
	private static function completion( Actor $actor, int $course_id, int $lesson_id, string $type, array $rule, array $state, array $settings, ?string $withheld = null ): string {
		$done  = 'completed' === ( $state['status'] ?? '' );
		$html  = '<section class="ccap-completion" aria-labelledby="ccap-completion-heading"><h2 id="ccap-completion-heading">' . esc_html__( 'Your progress', 'course-captain' ) . '</h2>';
		$html .= '<p class="ccap-lesson-state">' . esc_html( $done ? __( 'Status: completed', 'course-captain' ) : __( 'Status: not completed yet', 'course-captain' ) ) . '</p>';
		$form  = '<form method="post" action="' . esc_url( LearningArea::lesson_url( LearningArea::course_uid( $course_id ), $lesson_id ) ) . '">' . wp_nonce_field( self::NONCE . ':' . $lesson_id, '_wpnonce', false, false );
		$uid   = (string) ( $settings['media_uid'] ?? '' );
		if ( 'file' === $type && null !== $withheld ) {
			$html .= '<p class="ccap-withheld">' . esc_html( $withheld ) . '</p>';
		} elseif ( 'file' === $type && 1 === preg_match( '/^[0-9a-f-]{36}$/', $uid ) ) {
			$html .= $form . '<p><button type="submit" class="ccap-button" name="ccap_do" value="download">' . esc_html__( 'Download the file', 'course-captain' ) . '</button></p></form>';
		}
		if ( $done ) {
			return $html . '</section>';
		}
		switch ( (string) $rule['type'] ) {
			case 'manual_confirm':
				$html .= $form . '<p><button type="submit" class="ccap-button" name="ccap_do" value="complete">' . esc_html( LearningArea::button_label( 'complete', __( 'Mark as completed', 'course-captain' ) ) ) . '</button></p></form>';
				break;
			case 'view':
				$min     = max( 0, (int) ( $rule['params']['min_seconds'] ?? 0 ) );
				$opened  = time();
				$minutes = (int) ceil( $min / 60 );
				if ( $min > 0 ) {
					$html .= '<p id="ccap-view-hint">' . esc_html(
						sprintf(
							/* translators: %s: number of minutes */
							_n( 'You can mark this lesson as read after %s minute.', 'You can mark this lesson as read after %s minutes.', $minutes, 'course-captain' ),
							number_format_i18n( $minutes )
						)
					) . '</p>';
				}
				$html .= $form . '<input type="hidden" name="opened" value="' . (int) $opened . '"><input type="hidden" name="opened_sig" value="' . esc_attr( self::sign( $actor, $lesson_id, $opened ) ) . '"><p><button type="submit" class="ccap-button" name="ccap_do" value="view"' . ( $min > 0 ? ' aria-describedby="ccap-view-hint"' : '' ) . '>' . esc_html__( 'Mark as read', 'course-captain' ) . '</button></p></form>';
				break;
			case 'downloaded':
				$html .= '<p>' . esc_html__( 'This step is completed when you download the file.', 'course-captain' ) . '</p>';
				break;
			case 'position_ratio':
				$html .= '<p>' . esc_html__( 'This step is completed when you have watched the video.', 'course-captain' ) . '</p>';
				break;
			case 'attempted':
				$html .= '<p>' . esc_html__( 'This step is completed when you have answered every question of the quiz.', 'course-captain' ) . '</p>';
				break;
			case 'all_correct_once':
				$html .= '<p>' . esc_html__( 'This step is completed when you have answered every question of the quiz correctly once.', 'course-captain' ) . '</p>';
				break;
			case 'passed':
			case 'evaluated':
				if ( 'quiz' === $type ) {
					$html .= '<p>' . esc_html__( 'This step is completed when you pass the quiz.', 'course-captain' ) . '</p>';
					break;
				}
				$html .= '<p>' . esc_html( 'exam' === $type ? __( 'This step is completed with the exam.', 'course-captain' ) : __( 'This step is completed when your assignment has been graded.', 'course-captain' ) ) . '</p>';
				break;
			default:
				$html .= '<p>' . esc_html__( 'This step is completed automatically.', 'course-captain' ) . '</p>';
		}
		return $html . '</section>';
	}

	/**
	 * Previous/next unlocked lesson of the course.
	 *
	 * @param array<string, mixed> $room      Course room.
	 * @param int                  $lesson_id Current lesson.
	 * @param string               $lang      Content language attributes.
	 * @return string
	 */
	private static function navigation( array $room, int $lesson_id, string $lang ): string {
		$all = array();
		foreach ( (array) $room['modules'] as $module ) {
			foreach ( (array) $module['lessons'] as $lesson ) {
				$all[] = (array) $lesson;
			}
		}
		$ids      = array_map( static fn( array $l ): int => (int) $l['lesson_id'], $all );
		$position = (int) array_search( $lesson_id, $ids, true );
		$uid      = LearningArea::course_uid( (int) $room['course_id'] );
		$links    = array();
		foreach ( array(
			'prev' => array_reverse( array_slice( $all, 0, $position ) ),
			'next' => array_slice( $all, $position + 1 ),
		) as $direction => $candidates ) {
			foreach ( $candidates as $candidate ) {
				if ( null === $candidate['locked_reason'] ) {
					$label = 'prev' === $direction
						/* translators: %s: lesson title */
						? __( 'Previous: %s', 'course-captain' )
						/* translators: %s: lesson title */
						: __( 'Next: %s', 'course-captain' );
					$parts   = explode( '%s', $label, 2 );
					$links[] = '<li><a rel="' . $direction . '" href="' . esc_url( LearningArea::lesson_url( $uid, (int) $candidate['lesson_id'] ) ) . '">' . esc_html( $parts[0] ) . '<span' . $lang . '>' . esc_html( (string) $candidate['title'] ) . '</span>' . esc_html( $parts[1] ?? '' ) . '</a></li>';
					break;
				}
			}
		}
		if ( array() === $links ) {
			return '';
		}
		return '<nav aria-label="' . esc_attr__( 'Lessons', 'course-captain' ) . '"><ul class="ccap-lesson-nav">' . implode( '', $links ) . '</ul></nav>';
	}

	/**
	 * Status after a form post as text.
	 *
	 * @param string $status Status code.
	 * @return string
	 */
	private static function status_message( string $status ): string {
		$messages = array(
			'completed'  => __( 'Lesson completed.', 'course-captain' ),
			'saved'      => __( 'Your progress has been saved.', 'course-captain' ),
			'downloaded' => __( 'The download has been recorded.', 'course-captain' ),
		);
		if ( isset( $messages[ $status ] ) ) {
			return '<p class="ccap-status" role="status">' . esc_html( $messages[ $status ] ) . '</p>';
		}
		$quiz = QuizView::message( $status );
		if ( null !== $quiz ) {
			if ( '' === $quiz['text'] ) {
				return '';
			}
			return $quiz['ok'] ? '<p class="ccap-status" role="status">' . esc_html( $quiz['text'] ) . '</p>' : '<p class="ccap-error" role="alert">' . esc_html( $quiz['text'] ) . '</p>';
		}
		$assignment = AssignmentView::message( $status );
		if ( null !== $assignment ) {
			return $assignment['ok'] ? '<p class="ccap-status" role="status">' . esc_html( $assignment['text'] ) . '</p>' : '<p class="ccap-error" role="alert">' . esc_html( $assignment['text'] ) . '</p>';
		}
		if ( 'view_too_short' === $status ) {
			return '<p class="ccap-error" role="alert">' . esc_html__( 'Please take a little more time with this lesson before you mark it as read.', 'course-captain' ) . '</p>';
		}
		return '' === $status ? '' : '<p class="ccap-error" role="alert">' . esc_html( RestContract::user_message( $status ) ) . '</p>';
	}

	/**
	 * Signature of the opening time (view rule; the learner cannot backdate it).
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $lesson_id Lesson.
	 * @param int   $opened    Unix time.
	 * @return string
	 */
	private static function sign( Actor $actor, int $lesson_id, int $opened ): string {
		return wp_hash( 'ccap_lesson_opened|' . $actor->user_id . '|' . $lesson_id . '|' . $opened, 'nonce' );
	}
}
