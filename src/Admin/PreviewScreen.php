<?php
/**
 * "Preview as learner" of a course (R0-11 Flow 1 F1, A-04; R0-30 §1.3 hidden page).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Access\RightsGate;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Kernel\Locale;
use CourseCaptain\Learner\WithheldInlineMedia;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only view of the current draft as learners will see it: modules
 * and lessons in order, lesson text through `the_content`, the activity
 * of each lesson in words, the deliverable questions of each module pool
 * without solution data (A11Y-022 form shape, inputs disabled) and the
 * exams. Embedded media of the lesson text whose rights withhold them
 * get the same placeholder as in the learner view ({@see self::learner_text()}).
 * Nothing is written: no progress, no attempts, no tokens.
 * Allowed for whoever may edit the course (`edit_post`).
 */
final class PreviewScreen {

	public const SLUG          = 'course-captain-preview';
	public const MAX_QUESTIONS = 30;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				// Hidden page (no menu entry); highlighted under "Courses".
				add_submenu_page( '', __( 'Preview as learner', 'course-captain' ), '', 'ccap_edit_courses', self::SLUG, array( self::class, 'render' ) );
			},
			20
		);
		add_filter( 'post_row_actions', array( self::class, 'row_action' ), 10, 2 );
		add_action( 'post_submitbox_misc_actions', array( self::class, 'submitbox_link' ) );
	}

	/**
	 * Preview URL of a course.
	 *
	 * @param int $course_id Course.
	 * @return string
	 */
	public static function url( int $course_id ): string {
		return add_query_arg(
			array(
				'page'   => self::SLUG,
				'course' => $course_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Row action "Preview as learner" in the course list.
	 *
	 * @param array<string, string> $actions Actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public static function row_action( array $actions, \WP_Post $post ): array {
		if ( Relations::COURSE_TYPE === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
			$actions['ccap_preview'] = '<a href="' . esc_url( self::url( (int) $post->ID ) ) . '">' . esc_html__( 'Preview as learner', 'course-captain' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Link in the publish box of the course editor.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function submitbox_link( $post ): void {
		if ( $post instanceof \WP_Post && Relations::COURSE_TYPE === $post->post_type && 'auto-draft' !== $post->post_status ) {
			echo '<div class="misc-pub-section"><a href="' . esc_url( self::url( (int) $post->ID ) ) . '">' . esc_html__( 'Preview as learner', 'course-captain' ) . '</a></div>';
		}
	}

	/**
	 * Page output.
	 *
	 * @return void
	 */
	public static function render(): void {
		echo self::html( absint( $_GET['course'] ?? 0 ), get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.NonceVerification.Recommended -- Escaped while building; read-only view.
	}

	/**
	 * Page HTML.
	 *
	 * @param int $course_id Course.
	 * @param int $user_id   Viewer.
	 * @return string
	 */
	public static function html( int $course_id, int $user_id ): string {
		$course = get_post( $course_id );
		if ( ! $course instanceof \WP_Post || Relations::COURSE_TYPE !== $course->post_type || ! user_can( $user_id, 'edit_post', $course_id ) ) {
			return '<div class="wrap"><h1>' . esc_html__( 'Preview as learner', 'course-captain' ) . '</h1><p>' . esc_html__( 'This course does not exist or you may not edit it.', 'course-captain' ) . '</p></div>';
		}
		$lang  = Locale::content_attributes( CourseStructure::course_language( $course_id ) );
		$html  = '<div class="wrap ccap-preview"><h1>' . esc_html( sprintf( /* translators: %s: course title */ __( 'Preview as learner: %s', 'course-captain' ), $course->post_title ) ) . '</h1>';
		$html .= '<div class="notice notice-info inline"><p>' . esc_html__( 'This is the current draft as learners will see it after publishing. Nothing you do here is saved: no progress, no attempts. Answer fields are switched off.', 'course-captain' ) . '</p><p><a href="' . esc_url( (string) get_edit_post_link( $course_id, 'raw' ) ) . '">' . esc_html__( 'Back to the course editor', 'course-captain' ) . '</a></p></div>';
		$html .= '<div class="ccap-preview-description"' . $lang . '>' . self::content( $course ) . '</div>';
		$repo  = QuestionRepository::current();
		$gate  = RightsGate::current();
		$nav   = '<nav aria-label="' . esc_attr__( 'Course contents', 'course-captain' ) . '"><ol>';
		$body  = '';
		foreach ( CourseStructure::current()->modules( $course_id ) as $m => $link ) {
			$module = get_post( $link['module_id'] );
			if ( ! $module instanceof \WP_Post ) {
				continue;
			}
			$anchor  = 'ccap-pv-module-' . (int) $module->ID;
			$nav    .= '<li><a href="#' . esc_attr( $anchor ) . '">' . esc_html( $module->post_title ) . '</a></li>';
			$body   .= '<section aria-labelledby="' . esc_attr( $anchor ) . '"><h2 id="' . esc_attr( $anchor ) . '">' . esc_html( sprintf( /* translators: 1: module number, 2: module title */ __( 'Module %1$d: %2$s', 'course-captain' ), $m + 1, $module->post_title ) ) . ( $link['required'] ? '' : ' <span class="description">(' . esc_html__( 'optional', 'course-captain' ) . ')</span>' ) . '</h2>';
			$lessons = CourseStructure::current()->lessons( (int) $module->ID );
			if ( array() === $lessons ) {
				$body .= '<p>' . esc_html__( 'This module has no lessons.', 'course-captain' ) . '</p>';
			}
			foreach ( $lessons as $lesson ) {
				$definition = ActivityService::definition( (int) $lesson->ID );
				$body      .= '<article class="ccap-pv-lesson"><h3>' . esc_html( $lesson->post_title ) . ' <span class="description">(' . esc_html( self::activity_label( $definition['type'] ) ) . ( 'publish' === $lesson->post_status ? '' : ', ' . esc_html__( 'not published', 'course-captain' ) ) . ')</span></h3>' . self::withheld_lesson( $gate, (int) $lesson->ID, $definition['settings'] ) . '<div' . $lang . '>' . self::learner_text( self::content( $lesson ), array( $gate, 'medium_uid' ) ) . '</div></article>';
			}
			// The editorial side sees withheld questions as a notice (R0-10 §2.2); learners never get them.
			$pool     = $repo->pool( (int) $module->ID, true );
			$withheld = array() === $pool ? array() : $gate->questions( $pool );
			if ( array() !== $pool ) {
				$shown = count( $pool ) - count( $withheld );
				$body .= '<details><summary>' . esc_html( sprintf( /* translators: %d: number of questions */ _n( '%d question for practice and exams', '%d questions for practice and exams', $shown, 'course-captain' ), $shown ) ) . '</summary>';
				if ( array() !== $withheld ) {
					$body .= '<p class="ccap-pv-withheld"><strong>' . esc_html( sprintf( /* translators: %d: number of questions */ _n( '%d imported question is not shown to learners because of its rights.', '%d imported questions are not shown to learners because of their rights.', count( $withheld ), 'course-captain' ), count( $withheld ) ) ) . '</strong></p>';
				}
				$ids = array();
				foreach ( array_slice( $pool, 0, self::MAX_QUESTIONS ) as $question ) {
					$ids[ $question ] = (int) Database::current()->scalar( Database::current()->prepare( 'SELECT current_revision_id FROM `' . Database::current()->table( 'ccap_question' ) . '` WHERE id = %d', $question ) );
				}
				$revisions = $repo->revisions( array_values( $ids ) );
				$n         = 0;
				foreach ( $ids as $question => $revision ) {
					if ( isset( $withheld[ $question ] ) ) {
						$body .= '<p class="ccap-pv-withheld">' . esc_html( sprintf( /* translators: 1: question number, 2: reason why the question is not shown to learners */ __( 'Question %1$d: %2$s', 'course-captain' ), ++$n, RightsGate::notice( $withheld[ $question ] ) ) ) . '</p>';
						continue;
					}
					if ( isset( $revisions[ $revision ] ) && array() === QuestionTypes::validate( $revisions[ $revision ] ) ) {
						$payload = QuestionTypes::deliver( $revisions[ $revision ], new Randomizer( 'preview' ), 'preview:' . $question )['payload'];
						$body   .= self::question_html( $payload, ++$n, 'ccap-pv-q' . $question, $lang );
					}
				}
				if ( count( $pool ) > self::MAX_QUESTIONS ) {
					$body .= '<p>' . esc_html( sprintf( /* translators: %d: number of shown questions */ __( 'Only the first %d questions are shown.', 'course-captain' ), self::MAX_QUESTIONS ) ) . '</p>';
				}
				$body .= '</details>';
			}
			$body .= '</section>';
		}
		$html .= $nav . '</ol></nav>' . ( '' === $body ? '<p>' . esc_html__( 'The course has no modules yet.', 'course-captain' ) . '</p>' : $body );
		return $html . self::exams( $course_id ) . '</div>';
	}

	/**
	 * Notice for a lesson that learners do not get (R0-10 §2.2): the lesson
	 * itself is withheld (its `_ccap_rights`), or a medium of the lesson
	 * (activity file or video, linked media) is replaced by the placeholder.
	 *
	 * @param RightsGate           $gate      Rights gate.
	 * @param int                  $lesson_id Lesson.
	 * @param array<string, mixed> $settings  Activity settings.
	 * @return string
	 */
	private static function withheld_lesson( RightsGate $gate, int $lesson_id, array $settings ): string {
		$reason = $gate->post( $lesson_id );
		if ( null !== $reason ) {
			return '<p class="ccap-pv-withheld"><strong>' . esc_html( RightsGate::notice( $reason ) ) . '</strong></p>';
		}
		$db  = Database::current();
		$ids = array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT media_id FROM `' . $db->table( 'ccap_content_media' ) . "` WHERE object_type = 'lesson' AND object_id = %d", $lesson_id ) ) );
		$uid = (string) ( $settings['media_uid'] ?? '' );
		if ( 1 === preg_match( '/^[0-9a-f-]{36}$/', $uid ) ) {
			$ids[] = (int) $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_media' ) . '` WHERE uid = %s', $uid ) );
		}
		$media = array() === $ids ? array() : $gate->media( $ids );
		if ( array() === $media ) {
			return '';
		}
		return '<p class="ccap-pv-withheld"><strong>' . esc_html(
			sprintf(
				/* translators: 1: placeholder text such as "Media not available", 2: reason why the medium is not shown to learners */
				__( 'Learners see "%1$s" in place of a medium of this lesson. %2$s', 'course-captain' ),
				RightsGate::placeholder(),
				RightsGate::notice( (string) reset( $media ) )
			)
		) . '</strong></p>';
	}

	/**
	 * Exams of the course as learners see them before the start.
	 *
	 * @param int $course_id Course.
	 * @return string
	 */
	private static function exams( int $course_id ): string {
		$db    = Database::current();
		$rules = $db->rows( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_exam_rule' ) . "` WHERE course_id = %d AND status IN ('draft','published') ORDER BY id", $course_id ) );
		if ( array() === $rules ) {
			return '';
		}
		$html = '<section aria-labelledby="ccap-pv-exams"><h2 id="ccap-pv-exams">' . esc_html__( 'Exams', 'course-captain' ) . '</h2><ul>';
		foreach ( $rules as $row ) {
			$rule = ExamRules::current()->get( (int) $row['id'] );
			if ( null === $rule ) {
				continue;
			}
			$html .= '<li>' . esc_html( ExamsScreen::summary( $rule ) ) . ( 'draft' === $rule['status'] ? ' <span class="description">(' . esc_html__( 'draft, not visible to learners yet', 'course-captain' ) . ')</span>' : '' ) . '</li>';
		}
		return $html . '</ul></section>';
	}

	/**
	 * Lesson text exactly as learners get it: embeds and links of withheld
	 * media (R0-10 §2.2) become the neutral placeholder "Media not
	 * available" ({@see WithheldInlineMedia}, learner mode, also for the
	 * editor viewing the preview). The reason stands in the notice above
	 * the lesson ({@see self::withheld_lesson()}).
	 *
	 * @param string                    $html   Rendered lesson text.
	 * @param callable(string): ?string $reason Media UID → reason or null ({@see RightsGate::medium_uid()}).
	 * @return string
	 */
	public static function learner_text( string $html, callable $reason ): string {
		return WithheldInlineMedia::filter( $html, $reason, false );
	}

	/**
	 * Content through `the_content`.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function content( \WP_Post $post ): string {
		if ( '' === trim( $post->post_content ) ) {
			return '';
		}
		return (string) apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
	}

	/**
	 * Activity type in words.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public static function activity_label( string $type ): string {
		$labels = array(
			''           => __( 'text lesson', 'course-captain' ),
			'lesson'     => __( 'text lesson', 'course-captain' ),
			'video'      => __( 'video', 'course-captain' ),
			'file'       => __( 'file download', 'course-captain' ),
			'quiz'       => __( 'practice', 'course-captain' ),
			'flashcard'  => __( 'flashcards', 'course-captain' ),
			'exam'       => __( 'exam', 'course-captain' ),
			'assignment' => __( 'assignment', 'course-captain' ),
		);
		return $labels[ $type ] ?? $type;
	}

	/**
	 * One question as learners see it (inputs disabled).
	 *
	 * @param array<string, mixed> $payload Learner payload (no solution data).
	 * @param int                  $number  Question number.
	 * @param string               $id      ID prefix.
	 * @param string               $lang    Escaped language attributes.
	 * @return string
	 */
	public static function question_html( array $payload, int $number, string $id, string $lang = '' ): string {
		$type  = (string) ( $payload['type'] ?? '' );
		$hint  = array(
			QuestionTypes::SINGLE     => __( 'one answer', 'course-captain' ),
			QuestionTypes::TRUE_FALSE => __( 'one answer', 'course-captain' ),
			QuestionTypes::MULTI      => __( 'several answers', 'course-captain' ),
		);
		$html  = '<fieldset class="ccap-pv-question" disabled aria-labelledby="' . esc_attr( $id ) . '-legend"><legend id="' . esc_attr( $id ) . '-legend">' . esc_html( sprintf( /* translators: %d: question number */ __( 'Question %d', 'course-captain' ), $number ) ) . ( isset( $hint[ $type ] ) ? ' (' . esc_html( $hint[ $type ] ) . ')' : '' ) . '</legend>';
		$html .= '<div class="ccap-qtext"' . $lang . '>' . wp_kses_post( (string) ( $payload['text'] ?? '' ) ) . '</div>';
		switch ( $type ) {
			case QuestionTypes::SINGLE:
			case QuestionTypes::TRUE_FALSE:
			case QuestionTypes::MULTI:
				$html .= '<ul class="ccap-options">';
				foreach ( (array) ( $payload['options'] ?? array() ) as $i => $option ) {
					$oid   = $id . '-o' . $i;
					$html .= '<li><input type="' . ( QuestionTypes::MULTI === $type ? 'checkbox' : 'radio' ) . '" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $id ) . '"> <label for="' . esc_attr( $oid ) . '">' . esc_html( (string) $option['text'] ) . '</label></li>';
				}
				$html .= '</ul>';
				break;
			case QuestionTypes::NUMERIC:
				$html .= '<p><label for="' . esc_attr( $id ) . '-n">' . esc_html__( 'Your answer', 'course-captain' ) . '</label> <input id="' . esc_attr( $id ) . '-n" type="text" inputmode="decimal"> ' . esc_html( (string) ( $payload['unit'] ?? '' ) ) . '</p>';
				break;
			case QuestionTypes::MATCHING:
				$html .= '<ul>';
				foreach ( (array) ( $payload['left'] ?? array() ) as $i => $left ) {
					$sid   = $id . '-m' . $i;
					$html .= '<li><label for="' . esc_attr( $sid ) . '">' . esc_html( (string) $left['text'] ) . '</label> <select id="' . esc_attr( $sid ) . '"><option>' . esc_html__( '— choose —', 'course-captain' ) . '</option>';
					foreach ( (array) ( $payload['right'] ?? array() ) as $right ) {
						$html .= '<option>' . esc_html( (string) $right['text'] ) . '</option>';
					}
					$html .= '</select></li>';
				}
				$html .= '</ul>';
				break;
			case QuestionTypes::ORDERING:
				$html .= '<ol>';
				foreach ( (array) ( $payload['items'] ?? array() ) as $item ) {
					$html .= '<li>' . esc_html( (string) $item['text'] ) . '</li>';
				}
				$html .= '</ol>';
				break;
			case QuestionTypes::CLOZE:
				foreach ( (array) ( $payload['gaps'] ?? array() ) as $i => $gap ) {
					$html .= '<p><label for="' . esc_attr( $id . '-g' . $i ) . '">' . esc_html( sprintf( /* translators: %s: blank label */ __( 'Blank %s', 'course-captain' ), (string) $gap ) ) . '</label> <input id="' . esc_attr( $id . '-g' . $i ) . '" type="text"></p>';
				}
				break;
			case QuestionTypes::OPEN_TEXT:
				$html .= '<p><label for="' . esc_attr( $id ) . '-t">' . esc_html__( 'Your answer', 'course-captain' ) . '</label><br><textarea id="' . esc_attr( $id ) . '-t" rows="3" class="large-text"></textarea></p>';
				break;
			case QuestionTypes::FLASHCARD:
				$html .= '<p>' . esc_html__( 'Learners turn the card and rate themselves.', 'course-captain' ) . '</p>';
				break;
		}
		return $html . '</fieldset>';
	}
}
