<?php
/**
 * Admin screen A-07 "Question bank" (R0-11 §2, R0-30 §1.2 Pos 3, R0-08 §3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Activity\MediaLibrary;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Question\QuestionBulk;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Question\TypeChange;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * List (filter by module, type, status, text), two-step "new question"
 * (type first, so only its fields are shown and the form needs no
 * JavaScript), editor with answers, feedback, question image with the
 * alt-text rule (B-05), module pool, learner preview without solution
 * data and the revision history.
 *
 * Saving goes through {@see QuestionRepository}: a published revision is
 * never changed; the editor says so before saving ("new revision, running
 * attempts keep the old one", A-07 Z-F) and asks for the kind of change
 * (Δ-23-3). Every write is audited. Entries survive validation errors
 * (kept for ten minutes and shown again, INV-D-025).
 */
final class QuestionsScreen {

	public const SLUG   = 'course-captain-questions';
	public const ACTION = 'ccap_question_admin';
	public const CAP    = 'ccap_edit_courses';
	public const OPS    = array( 'save', 'save_publish', 'publish', 'archive', 'pool_add', 'pool_remove', 'change_type', 'bulk' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				add_submenu_page( EditorScreens::MENU, __( 'Question bank', 'course-captain' ), _x( 'Question bank', 'admin menu', 'course-captain' ), self::CAP, self::SLUG, array( self::class, 'render' ), 3 );
			},
			20
		);
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Page output.
	 *
	 * @return void
	 */
	public static function render(): void {
		echo self::html( wp_unslash( $_GET ), get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.NonceVerification.Recommended -- Escaped while building; read-only view parameters.
	}

	/**
	 * Admin-post entry point.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$out  = self::process( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process().
		$args = array(
			'page'        => self::SLUG,
			'ccap_result' => $out['result'],
		);
		if ( $out['question'] > 0 ) {
			$args['action']   = 'edit';
			$args['question'] = $out['question'];
			if ( 'change_type' === $out['op'] && 'ccap_confirmation_required' === $out['result'] ) {
				$args['change_to'] = $out['type'];
			}
		} elseif ( 'bulk' === $out['op'] ) {
			// UI-F8: back to the filtered list.
			foreach ( array( 'module', 'type', 'status', 's', 'tag' ) as $key ) {
				$value = sanitize_text_field( (string) wp_unslash( $_POST[ 'filter_' . $key ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process(); display filters only.
				if ( '' !== $value ) {
					$args[ $key ] = $value;
				}
			}
		} elseif ( '' !== $out['type'] ) {
			$args['action'] = 'new';
			$args['type']   = $out['type'];
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Testable core of the handler.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @param int                  $user_id Current user.
	 * @return array{op: string, question: int, type: string, result: string, messages: list<string>, fields: array<string, string>}
	 * @throws CourseException Never; domain errors become the result code.
	 */
	public static function process( array $post, int $user_id ): array {
		$op       = sanitize_key( (string) ( $post['op'] ?? '' ) );
		$question = absint( $post['object'] ?? 0 );
		$out      = array(
			'op'       => $op,
			'question' => $question,
			'type'     => sanitize_key( (string) ( $post['question_type'] ?? '' ) ),
			'result'   => 'ok',
			'messages' => array(),
			'fields'   => array(),
		);
		if ( ! in_array( $op, self::OPS, true ) ) {
			$out['result'] = 'ccap_not_found';
			return $out;
		}
		$group = in_array( $op, array( 'save', 'save_publish' ), true ) ? 'editor' : $op;
		if ( sanitize_key( (string) ( $post['nonce_op'] ?? $op ) ) !== $group || ! AdminUi::verify( self::ACTION, $post ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		if ( ! user_can( $user_id, self::CAP ) ) {
			$out['result'] = 'ccap_forbidden';
			return $out;
		}
		$actor = Actor::user( $user_id );
		$repo  = QuestionRepository::current();
		$db    = Database::current();
		try {
			switch ( $op ) {
				case 'save':
				case 'save_publish':
					$out = self::save( $actor, $question, $post, 'save_publish' === $op, $out );
					break;
				case 'publish':
					$errors = QuestionTypes::validate( (array) $repo->revision( self::latest_revision( $question ) ) );
					if ( array() !== $errors ) {
						$out['result']   = 'ccap_validation_failed';
						$out['messages'] = QuestionForm::messages( $errors );
						break;
					}
					$revision = $repo->publish( $actor, $question );
					AuditLog::write( $db, $actor, 'question_published', 'question', (string) $question, null, array( 'revision_id' => $revision ) );
					break;
				case 'archive':
					if ( empty( $post['confirm'] ) ) {
						$out['result'] = 'ccap_confirmation_required';
						break;
					}
					$repo->archive( $actor, $question );
					AuditLog::write( $db, $actor, 'question_archived', 'question', (string) $question );
					break;
				case 'pool_add':
					$module = absint( $post['module'] ?? 0 );
					$sort   = isset( $post['sort_order'] ) && '' !== (string) $post['sort_order'] ? (int) $post['sort_order'] : null;
					if ( null === get_post( $module ) ) {
						throw new CourseException( 'ccap_invalid_param', 'module', 422 );
					}
					$repo->add_to_pool( $actor, $module, $question, $sort );
					AuditLog::write( $db, $actor, 'question_pool_changed', 'question', (string) $question, null, array( 'module_id' => $module ) );
					break;
				case 'change_type':
					$out = self::change_type( $actor, $question, $post, $out );
					break;
				case 'pool_remove':
					$module = absint( $post['module'] ?? 0 );
					CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::EDIT_COURSE_ITEM, array( $module, 'module' ) ), 'Not allowed to edit this module.' );
					CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::EDIT_QUESTION, array( $question ) ), 'Not allowed to edit this question.' );
					$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_module_question' ) . '` WHERE module_id = %d AND question_id = %d', $module, $question ) );
					\CourseCaptain\Cache\CacheStore::bump_content();
					AuditLog::write( $db, $actor, 'question_pool_changed', 'question', (string) $question, null, array( 'removed_from' => $module ) );
					break;
				case 'bulk':
					$out['messages'] = self::bulk( $actor, $post );
					break;
			}
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
		}
		if ( 'ok' !== $out['result'] && in_array( $op, array( 'save', 'save_publish' ), true ) ) {
			set_transient( self::keep_key( $user_id ), $post, 600 );
		}
		if ( array() !== $out['messages'] || array() !== $out['fields'] ) {
			set_transient( self::messages_key( $user_id ), array( $out['messages'], $out['fields'] ), 600 );
		}
		return $out;
	}

	/**
	 * Save (and optionally publish) a question from the editor.
	 *
	 * @param Actor                $actor    Author.
	 * @param int                  $question Question (0 = new).
	 * @param array<string, mixed> $post     POST data.
	 * @param bool                 $publish  Publish after saving.
	 * @param array<string, mixed> $out      Result so far.
	 * @phpstan-param array{op: string, question: int, type: string, result: string, messages: list<string>, fields: array<string, string>} $out
	 * @return array{op: string, question: int, type: string, result: string, messages: list<string>, fields: array<string, string>}
	 * @throws CourseException On missing rights.
	 */
	private static function save( Actor $actor, int $question, array $post, bool $publish, array $out ): array {
		$repo = QuestionRepository::current();
		$db   = Database::current();
		$data = QuestionForm::from_post( $post );
		// UI-F8: tags from the editor field; without the field the tags of the newest revision stay.
		$data['tags'] = array_key_exists( 'tags', $post ) ? QuestionBulk::parse_tags( (string) $post['tags'] ) : array();
		if ( $question > 0 ) {
			// The type of an existing question does not change in the editor.
			$current = $repo->revision( self::latest_revision( $question ) );
			if ( null === $current ) {
				throw new CourseException( 'ccap_not_found', 'question', 404 );
			}
			CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::EDIT_QUESTION, array( $question ) ), 'Not allowed to edit this question.' );
			if ( $data['question_type'] !== $current['question_type'] ) {
				throw new CourseException( 'ccap_invalid_param', 'type', 422 );
			}
			if ( ! array_key_exists( 'tags', $post ) ) {
				$data['tags'] = (array) $current['tags'];
			}
		}
		$fields = QuestionForm::field_errors( $data );
		if ( isset( $fields['ccap-q-type'] ) || isset( $fields['ccap-q-image'] ) ) {
			$out['result'] = 'ccap_validation_failed';
			$out['fields'] = $fields;
			return $out;
		}
		$module = absint( $post['module'] ?? 0 );
		if ( 0 === $question && $module > 0 ) {
			CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::EDIT_COURSE_ITEM, array( $module, 'module' ) ), 'Not allowed to edit this module.' );
		}
		$raw   = (array) $data['image'];
		$image = array(
			'attachment' => (int) ( $raw['attachment'] ?? 0 ),
			'alt'        => (string) ( $raw['alt'] ?? '' ),
			'decorative' => ! empty( $raw['decorative'] ),
		);
		unset( $data['image'] );
		$media = 0;
		if ( $image['attachment'] > 0 ) {
			$media = MediaLibrary::current()->register_attachment( $actor, (int) $image['attachment'] );
			// Alt text lives on the medium (R0-10); '' only when marked decorative.
			$alt = '' === $image['alt'] ? ( $image['decorative'] ? '' : null ) : $image['alt'];
			$db->execute(
				null === $alt
					? $db->prepare( 'UPDATE `' . $db->table( 'ccap_media' ) . '` SET alt_text = NULL, updated_at = %s WHERE id = %d', $db->now(), $media )
					: $db->prepare( 'UPDATE `' . $db->table( 'ccap_media' ) . '` SET alt_text = %s, updated_at = %s WHERE id = %d', $alt, $db->now(), $media )
			);
			// The URL may change once the medium is protected (GM-111): compose again.
			$data['question_text'] = QuestionForm::compose_text( QuestionForm::split_text( (string) $data['question_text'] )['text'], $image );
		}
		$change = 'substantive' === ( $post['change_class'] ?? '' ) ? 'substantive' : 'editorial';
		if ( 0 === $question ) {
			$question        = $repo->create( $actor, $data );
			$out['question'] = $question;
			if ( $module > 0 ) {
				$repo->add_to_pool( $actor, $module, $question );
			}
			$revision = self::latest_revision( $question );
		} else {
			$revision = $repo->save( $actor, $question, $data, $change );
		}
		if ( $media > 0 ) {
			$draft = (string) $db->scalar( $db->prepare( 'SELECT editorial_status FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE id = %d', $revision ) );
			if ( 'draft' === $draft ) {
				MediaLibrary::current()->attach( $actor, $media, 'question_revision', $revision, 'image' );
			}
		}
		AuditLog::write( $db, $actor, 'question_saved', 'question', (string) $question, null, array( 'revision_id' => $revision ) );
		if ( isset( $fields['ccap-q-image-alt'] ) ) {
			// Saved as draft; publishing waits for the alt text (VAL-140/141).
			$out['result'] = 'ccap_alt_missing';
			$out['fields'] = $fields;
			return $out;
		}
		if ( ! $publish ) {
			return $out;
		}
		$errors = QuestionTypes::validate( (array) $repo->revision( $revision ) );
		if ( array() !== $errors ) {
			$out['result']   = 'ccap_saved_not_published';
			$out['messages'] = QuestionForm::messages( $errors );
			return $out;
		}
		$published = $repo->publish( $actor, $question, $revision );
		AuditLog::write( $db, $actor, 'question_published', 'question', (string) $question, null, array( 'revision_id' => $published ) );
		$out['result'] = 'published';
		return $out;
	}

	/**
	 * Change the type of a question (UI-F8): always a new draft revision,
	 * possible data loss only after confirmation; published revisions and
	 * exam snapshots stay as they are.
	 *
	 * @param Actor                $actor    Author.
	 * @param int                  $question Question.
	 * @param array<string, mixed> $post     POST data (`to`, `confirm_loss`).
	 * @param array<string, mixed> $out      Result so far.
	 * @phpstan-param array{op: string, question: int, type: string, result: string, messages: list<string>, fields: array<string, string>} $out
	 * @return array{op: string, question: int, type: string, result: string, messages: list<string>, fields: array<string, string>}
	 * @throws CourseException On missing rights or unknown questions.
	 */
	private static function change_type( Actor $actor, int $question, array $post, array $out ): array {
		$repo    = QuestionRepository::current();
		$db      = Database::current();
		$latest  = self::latest_revision( $question );
		$current = $repo->revision( $latest );
		if ( null === $current ) {
			throw new CourseException( 'ccap_not_found', 'question', 404 );
		}
		CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::EDIT_QUESTION, array( $question ) ), 'Not allowed to edit this question.' );
		$to = sanitize_key( (string) ( $post['to'] ?? '' ) );
		if ( ! in_array( $to, QuestionTypes::ALL, true ) || $to === $current['question_type'] ) {
			throw new CourseException( 'ccap_invalid_param', 'to', 422 );
		}
		$plan        = TypeChange::plan( $current, $to );
		$out['type'] = $to;
		if ( array() !== $plan['lost'] && empty( $post['confirm_loss'] ) ) {
			$out['result'] = 'ccap_confirmation_required';
			return $out;
		}
		$row                            = (array) $db->row( $db->prepare( 'SELECT image_required, rights_status FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE id = %d', $latest ) );
		$plan['data']['image_required'] = ! empty( $row['image_required'] );
		$plan['data']['rights_status']  = (string) ( $row['rights_status'] ?? 'own' );
		$revision                       = $repo->add_revision( $actor, $question, $plan['data'], 'substantive' );
		$out['result']                  = $plan['complete'] ? 'type_changed' : 'type_changed_incomplete';
		AuditLog::write(
			$db,
			$actor,
			'question_type_changed',
			'question',
			(string) $question,
			null,
			array(
				'revision_id' => $revision,
				'before'      => (string) $current['question_type'],
				'after'       => $to,
				'lost'        => $plan['lost'],
			)
		);
		return $out;
	}

	/**
	 * Texts of the parts a type change keeps or loses.
	 *
	 * @return array<string, string>
	 */
	public static function part_labels(): array {
		return array(
			'text'     => __( 'question text', 'course-captain' ),
			'feedback' => __( 'feedback texts', 'course-captain' ),
			'tags'     => __( 'tags', 'course-captain' ),
			'options'  => __( 'answer options', 'course-captain' ),
			'correct'  => __( 'marking of the correct answers', 'course-captain' ),
			'solution' => __( 'solution (model answer or card back)', 'course-captain' ),
			'order'    => __( 'correct order', 'course-captain' ),
			'numeric'  => __( 'number check with tolerance', 'course-captain' ),
			'pairs'    => __( 'matching pairs', 'course-captain' ),
			'gaps'     => __( 'gaps and accepted answers', 'course-captain' ),
			'scoring'  => __( 'partial scoring', 'course-captain' ),
			'shuffle'  => __( 'shuffle setting of the answers', 'course-captain' ),
		);
	}

	/**
	 * Section "Change question type": check (GET) and change (POST).
	 *
	 * @param array<string, mixed> $request  GET.
	 * @param int                  $question Question.
	 * @param array<string, mixed> $revision Newest revision.
	 * @return string
	 */
	private static function type_change_section( array $request, int $question, array $revision ): string {
		$from   = (string) ( $revision['question_type'] ?? '' );
		$labels = QuestionForm::type_labels();
		$to     = sanitize_key( (string) ( $request['change_to'] ?? '' ) );
		$html   = '<h2 id="ccap-type-change">' . esc_html__( 'Change question type', 'course-captain' ) . '</h2>';
		$html  .= '<p class="description">' . esc_html__( 'A type change creates a new draft version. The published version, running and finished exam attempts are not changed.', 'course-captain' ) . '</p>';
		$html  .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '#ccap-type-change"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="action" value="edit"><input type="hidden" name="question" value="' . (int) $question . '">';
		$html  .= '<p><label for="ccap-type-to">' . esc_html__( 'New question type', 'course-captain' ) . '</label> <select id="ccap-type-to" name="change_to">';
		foreach ( $labels as $key => $label ) {
			if ( $key !== $from ) {
				$html .= '<option value="' . esc_attr( $key ) . '"' . selected( $to, $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
		}
		$html .= '</select> <button type="submit" class="button">' . esc_html__( 'Check the change', 'course-captain' ) . '</button></p></form>';
		if ( ! in_array( $to, QuestionTypes::ALL, true ) || $to === $from ) {
			return $html;
		}
		$plan  = TypeChange::plan( $revision, $to );
		$parts = self::part_labels();
		$names = static fn( array $codes ): string => implode( ', ', array_map( static fn( string $c ): string => $parts[ $c ] ?? $c, $codes ) );
		$html .= '<div class="ccap-type-plan" role="status"><p><strong>' . esc_html( sprintf( /* translators: 1: old type, 2: new type */ __( 'Change from "%1$s" to "%2$s":', 'course-captain' ), $labels[ $from ] ?? $from, $labels[ $to ] ) ) . '</strong></p>';
		$html .= '<p>' . esc_html( sprintf( /* translators: %s: list of parts */ __( 'Taken over: %s.', 'course-captain' ), $names( $plan['kept'] ) ) ) . '</p>';
		if ( ! $plan['complete'] ) {
			$html .= '<p>' . esc_html__( 'The new version is not complete yet: add the missing answers in the editor before publishing.', 'course-captain' ) . '</p>';
		}
		$html  .= '</div>';
		$fields = '<input type="hidden" name="to" value="' . esc_attr( $to ) . '">';
		if ( array() !== $plan['lost'] ) {
			$html   .= '<div class="notice notice-warning inline" role="alert"><p>' . esc_html( sprintf( /* translators: %s: list of parts */ __( 'Lost in the new version: %s.', 'course-captain' ), $names( $plan['lost'] ) ) ) . '</p></div>';
			$fields .= '<p><label><input type="checkbox" name="confirm_loss" value="1" required> ' . esc_html__( 'Confirm: create the new version without these parts (older versions keep them)', 'course-captain' ) . '</label></p>';
		}
		return $html . AdminUi::form( self::ACTION, 'change_type', $question, $fields, sprintf( /* translators: %s: question type */ __( 'Change type to "%s"', 'course-captain' ), $labels[ $to ] ) );
	}

	/**
	 * Newest revision of a question.
	 *
	 * @param int $question Question.
	 * @return int
	 */
	public static function latest_revision( int $question ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE question_id = %d ORDER BY revision_no DESC LIMIT 1', $question ) );
	}

	/**
	 * Page HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 */
	public static function html( array $request, int $user_id ): string {
		if ( ! user_can( $user_id, self::CAP ) ) {
			return '<div class="wrap"><h1>' . esc_html__( 'Question bank', 'course-captain' ) . '</h1><p>' . esc_html__( 'You are not allowed to edit questions.', 'course-captain' ) . '</p></div>';
		}
		$action = sanitize_key( (string) ( $request['action'] ?? '' ) );
		$html   = '<div class="wrap">';
		if ( 'edit' === $action ) {
			$html .= self::editor( $request, $user_id, absint( $request['question'] ?? 0 ), '' );
		} elseif ( 'new' === $action ) {
			$type  = sanitize_key( (string) ( $request['type'] ?? '' ) );
			$html .= in_array( $type, QuestionTypes::ALL, true ) ? self::editor( $request, $user_id, 0, $type ) : self::type_step( $request, $user_id );
		} else {
			$html .= self::listing( $request, $user_id );
		}
		return $html . '</div>';
	}

	/**
	 * Result notice incl. kept messages.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 */
	private static function notice( array $request, int $user_id ): string {
		$code  = sanitize_key( (string) ( $request['ccap_result'] ?? '' ) );
		$kept  = get_transient( self::messages_key( $user_id ) );
		$extra = '';
		if ( is_array( $kept ) && '' !== $code ) {
			delete_transient( self::messages_key( $user_id ) );
			$items = array_merge( (array) $kept[0], array_values( (array) $kept[1] ) );
			if ( array() !== $items ) {
				$extra = '<ul>';
				foreach ( (array) $kept[1] as $field => $message ) {
					$extra .= '<li><a href="#' . esc_attr( (string) $field ) . '">' . esc_html( (string) $message ) . '</a></li>';
				}
				foreach ( (array) $kept[0] as $message ) {
					$extra .= '<li>' . esc_html( (string) $message ) . '</li>';
				}
				$extra .= '</ul>';
			}
		}
		if ( 'type_changed' === $code ) {
			return '<div class="notice notice-success" role="status"><p>' . esc_html__( 'Type changed. A new draft version was created; publish it to use it in new attempts.', 'course-captain' ) . '</p></div>';
		}
		if ( 'published' === $code ) {
			return '<div class="notice notice-success" role="status"><p>' . esc_html__( 'Saved and published. New exam attempts use this version; running attempts keep theirs.', 'course-captain' ) . '</p></div>';
		}
		return AdminUi::notice(
			$code,
			array(
				'ccap_alt_missing'           => __( 'Saved as draft, not published: the image needs an alternative text.', 'course-captain' ),
				'ccap_saved_not_published'   => __( 'Saved as draft, not published: the question is not complete yet.', 'course-captain' ),
				'ccap_confirmation_required' => __( 'Please confirm the action (tick the confirmation box).', 'course-captain' ),
				'type_changed_incomplete'    => __( 'Type changed. The new draft version still needs answers before it can be published.', 'course-captain' ),
				'ccap_revision_frozen'       => __( 'This version is published and can no longer take new media. Save again to create a new version.', 'course-captain' ),
				'ccap_media_type_forbidden'  => __( 'This file type cannot be used.', 'course-captain' ),
				'ccap_nothing_selected'      => __( 'Nothing changed: select at least one question.', 'course-captain' ),
				/* translators: %d: maximum number of questions */
				'ccap_too_many'              => sprintf( __( 'Nothing changed: select at most %d questions at once.', 'course-captain' ), QuestionBulk::MAX ),
			),
			__( 'Saved.', 'course-captain' ),
			$extra
		);
	}

	/**
	 * Question list with filters.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 */
	private static function listing( array $request, int $user_id ): string {
		$db     = Database::current();
		$module = absint( $request['module'] ?? 0 );
		$type   = sanitize_key( (string) ( $request['type'] ?? '' ) );
		$status = sanitize_key( (string) ( $request['status'] ?? '' ) );
		$search = sanitize_text_field( (string) ( $request['s'] ?? '' ) );
		$tag    = sanitize_text_field( (string) ( $request['tag'] ?? '' ) );
		$page   = max( 1, absint( $request['paged'] ?? 1 ) );
		$q      = $db->table( 'ccap_question' );
		$r      = $db->table( 'ccap_question_revision' );
		$mq     = $db->table( 'ccap_module_question' );
		$qt     = $db->table( 'ccap_question_tag' );
		$where  = array( '1=1' );
		if ( $module > 0 ) {
			$where[] = $db->prepare( "q.id IN (SELECT question_id FROM `{$mq}` WHERE module_id = %d)", $module ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
		}
		if ( in_array( $type, QuestionTypes::ALL, true ) ) {
			$where[] = $db->prepare( 'r.question_type = %s', $type );
		}
		if ( in_array( $status, array( 'draft', 'published', 'archived' ), true ) ) {
			$where[] = $db->prepare( 'q.status = %s', $status );
		} else {
			$where[] = "q.status <> 'archived'";
		}
		if ( '' !== $search ) {
			$where[] = $db->prepare( 'r.question_text LIKE %s', '%' . $db->wpdb()->esc_like( $search ) . '%' );
		}
		// UI-F8: filter by a tag of the newest revision (`type:key`).
		$tag_parts = explode( ':', $tag, 2 );
		if ( 2 === count( $tag_parts ) && in_array( $tag_parts[0], QuestionBulk::TYPES, true ) ) {
			$where[] = $db->prepare( "EXISTS (SELECT 1 FROM `{$qt}` t WHERE t.revision_id = r.id AND t.tag_type = %s AND t.tag_key = %s)", $tag_parts[0], $tag_parts[1] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
		}
		$sql  = "SELECT q.id, q.status, r.id AS revision_id, r.question_type, r.question_text, r.revision_no, r.editorial_status FROM `{$q}` q JOIN `{$r}` r ON r.id = (SELECT id FROM `{$r}` WHERE question_id = q.id ORDER BY revision_no DESC LIMIT 1) WHERE " . implode( ' AND ', $where ) . ' ORDER BY q.id DESC LIMIT 1000';
		$rows = array_values( array_filter( $db->rows( $sql ), static fn( array $row ): bool => PolicyService::instance()->decide( Actor::user( $user_id ), PolicyService::EDIT_QUESTION, array( (int) $row['id'] ) )->allowed ) );
		$per  = 50;
		$all  = count( $rows );
		$rows = array_slice( $rows, ( $page - 1 ) * $per, $per );
		$tags = self::row_tags( array_map( static fn( array $row ): int => (int) $row['revision_id'], $rows ) );
		$args = array(
			'page'   => self::SLUG,
			'module' => $module,
			'type'   => $type,
			'status' => $status,
			's'      => $search,
			'tag'    => $tag,
		);

		$html  = '<h1 class="wp-heading-inline">' . esc_html__( 'Question bank', 'course-captain' ) . '</h1> <a class="page-title-action" href="' . esc_url( add_query_arg( array( 'page' => self::SLUG, 'action' => 'new', 'module' => $module ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Add question', 'course-captain' ) . '</a><hr class="wp-header-end">'; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
		$html .= self::notice( $request, $user_id );
		$html .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="ccap-filter"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><p>';
		$html .= '<label for="ccap-f-module">' . esc_html__( 'Module', 'course-captain' ) . '</label> ' . self::module_select( 'ccap-f-module', 'module', $module, __( 'All modules', 'course-captain' ) ) . ' ';
		$html .= '<label for="ccap-f-type">' . esc_html__( 'Question type', 'course-captain' ) . '</label> <select id="ccap-f-type" name="type"><option value="">' . esc_html__( 'All types', 'course-captain' ) . '</option>';
		foreach ( QuestionForm::type_labels() as $key => $label ) {
			$html .= '<option value="' . esc_attr( $key ) . '"' . selected( $type, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select> <label for="ccap-f-status">' . esc_html__( 'Status', 'course-captain' ) . '</label> <select id="ccap-f-status" name="status"><option value="">' . esc_html__( 'Draft and published', 'course-captain' ) . '</option>';
		foreach ( array( 'draft', 'published', 'archived' ) as $key ) {
			$html .= '<option value="' . esc_attr( $key ) . '"' . selected( $status, $key, false ) . '>' . esc_html( self::status_label( $key ) ) . '</option>';
		}
		$html .= '</select> <label for="ccap-f-tag">' . esc_html__( 'Tag', 'course-captain' ) . '</label> <select id="ccap-f-tag" name="tag"><option value="">' . esc_html__( 'All tags', 'course-captain' ) . '</option>';
		foreach ( self::known_tags() as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $tag, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select> <label for="ccap-f-search">' . esc_html__( 'Text contains', 'course-captain' ) . '</label> <input id="ccap-f-search" type="search" name="s" value="' . esc_attr( $search ) . '"> <button type="submit" class="button">' . esc_html__( 'Filter', 'course-captain' ) . '</button></p></form>';
		$html .= '<p role="status">' . esc_html( sprintf( /* translators: %d: number of questions */ _n( '%d question found.', '%d questions found.', $all, 'course-captain' ), $all ) ) . '</p>';
		$table = '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Questions', 'course-captain' ) . '</caption><thead><tr><td class="check-column"></td><th scope="col">' . esc_html__( 'Question', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Question type', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Tags', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Modules', 'course-captain' ) . '</th></tr></thead><tbody>';
		if ( array() === $rows ) {
			$table .= '<tr><td colspan="6">' . esc_html__( 'No questions yet. Add a question or import a package.', 'course-captain' ) . '</td></tr>';
		}
		$labels = QuestionForm::type_labels();
		foreach ( $rows as $row ) {
			$id      = (int) $row['id'];
			$text    = mb_substr( trim( wp_strip_all_tags( (string) $row['question_text'] ) ), 0, 90 );
			$text    = '' === $text ? __( '(no text)', 'course-captain' ) : $text;
			$state   = self::status_label( (string) $row['status'] );
			$pending = 'published' === $row['status'] && 'draft' === $row['editorial_status'];
			$modules = array_map( static fn( $m ): string => (string) get_the_title( (int) $m ), $db->wpdb()->get_col( $db->prepare( "SELECT module_id FROM `{$mq}` WHERE question_id = %d ORDER BY module_id", $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
			$url     = add_query_arg(
				array(
					'page'     => self::SLUG,
					'action'   => 'edit',
					'question' => $id,
				),
				admin_url( 'admin.php' )
			);
			$names   = array_map( static fn( array $t ): string => self::tag_label( $t ), $tags[ (int) $row['revision_id'] ] ?? array() );
			$table  .= '<tr><td class="check-column"><input type="checkbox" id="ccap-bulk-q-' . $id . '" name="questions[]" value="' . $id . '"><label class="screen-reader-text" for="ccap-bulk-q-' . $id . '">' . esc_html( sprintf( /* translators: %s: question text */ __( 'Select: %s', 'course-captain' ), $text ) ) . '</label></td><th scope="row"><a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></th><td>' . esc_html( $labels[ (string) $row['question_type'] ] ?? (string) $row['question_type'] ) . '</td><td>' . esc_html( $state . ( $pending ? ' – ' . __( 'unpublished changes', 'course-captain' ) : '' ) ) . '</td><td>' . esc_html( implode( ', ', $names ) ) . '</td><td>' . esc_html( implode( ', ', $modules ) ) . '</td></tr>';
		}
		$table .= '</tbody></table>';
		$html  .= array() === $rows ? $table : AdminUi::form_multi( self::ACTION, 'bulk', 0, $table . self::bulk_fields( $args ), ' id="ccap-bulk-form"' );
		if ( $all > $per ) {
			$html .= '<nav aria-label="' . esc_attr__( 'Question pages', 'course-captain' ) . '"><p>';
			$last  = (int) ceil( $all / $per );
			for ( $p = 1; $p <= $last; $p++ ) {
				$html .= $p === $page ? '<span aria-current="page">' . $p . '</span> ' : '<a href="' . esc_url( add_query_arg( $args + array( 'paged' => $p ), admin_url( 'admin.php' ) ) ) . '">' . $p . '</a> ';
			}
			$html .= '</p></nav>';
		}
		return $html;
	}

	/**
	 * Bulk action fields below the question list (UI-F8): one action for the
	 * ticked questions; the form works without JavaScript.
	 *
	 * @param array<string, mixed> $args Current filters (kept after the redirect).
	 * @return string
	 */
	private static function bulk_fields( array $args ): string {
		$actions = array(
			'tag_add'    => __( 'Add tag', 'course-captain' ),
			'tag_remove' => __( 'Remove tag', 'course-captain' ),
			'pool_add'   => __( 'Add to module', 'course-captain' ),
			'pool_move'  => __( 'Move to module (remove from the other modules)', 'course-captain' ),
			'deprecate'  => __( 'Take out of use (archive)', 'course-captain' ),
		);
		$html    = '<fieldset class="ccap-bulk"><legend><strong>' . esc_html__( 'Change the selected questions', 'course-captain' ) . '</strong></legend>';
		$html   .= '<p><label for="ccap-bulk-action">' . esc_html__( 'Action', 'course-captain' ) . '</label> <select id="ccap-bulk-action" name="bulk_action">';
		foreach ( $actions as $key => $label ) {
			$html .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		$html .= '</select></p>';
		$html .= '<p><label for="ccap-bulk-tag">' . esc_html__( 'Tag (for adding or removing)', 'course-captain' ) . '</label> <input id="ccap-bulk-tag" type="text" name="bulk_tag" class="regular-text" aria-describedby="ccap-bulk-tag-help"><br><span id="ccap-bulk-tag-help" class="description">' . esc_html( self::tag_help() ) . '</span></p>';
		$html .= '<p><label for="ccap-bulk-module">' . esc_html__( 'Module (for adding or moving)', 'course-captain' ) . '</label> ' . self::module_select( 'ccap-bulk-module', 'bulk_module', 0, __( '— choose a module —', 'course-captain' ) ) . '</p>';
		$html .= '<p><label><input type="checkbox" name="bulk_publish" value="1" checked> ' . esc_html__( 'Publish tag changes of published questions right away (questions with unpublished changes stay drafts)', 'course-captain' ) . '</label><br>';
		$html .= '<label><input type="checkbox" name="bulk_confirm" value="1"> ' . esc_html__( 'Confirm: take the selected questions out of use (they are archived, results stay)', 'course-captain' ) . '</label></p>';
		foreach ( array( 'module', 'type', 'status', 's', 'tag' ) as $key ) {
			$html .= '<input type="hidden" name="filter_' . esc_attr( $key ) . '" value="' . esc_attr( (string) ( $args[ $key ] ?? '' ) ) . '">';
		}
		return $html . '<p><button type="submit" name="op" value="bulk" class="button">' . esc_html__( 'Apply to selected questions', 'course-captain' ) . '</button></p></fieldset>';
	}

	/**
	 * Run a bulk action and describe the outcome.
	 *
	 * @param Actor                $actor Author.
	 * @param array<string, mixed> $post  POST data.
	 * @return list<string>
	 * @throws CourseException `ccap_nothing_selected`, `ccap_too_many`, `ccap_invalid_param`, `ccap_confirmation_required`, `ccap_forbidden`.
	 */
	private static function bulk( Actor $actor, array $post ): array {
		$result = QuestionBulk::current()->apply(
			$actor,
			sanitize_key( (string) ( $post['bulk_action'] ?? '' ) ),
			array_map( 'absint', (array) ( $post['questions'] ?? array() ) ),
			array(
				'tag'     => QuestionBulk::parse_tag( (string) ( $post['bulk_tag'] ?? '' ) ),
				'module'  => absint( $post['bulk_module'] ?? 0 ),
				'publish' => ! empty( $post['bulk_publish'] ),
				'confirm' => ! empty( $post['bulk_confirm'] ),
			)
		);
		$lines  = array(
			/* translators: %d: number of questions */
			sprintf( _n( '%d question changed.', '%d questions changed.', $result['changed'], 'course-captain' ), $result['changed'] ),
		);
		if ( $result['published'] > 0 ) {
			/* translators: %d: number of questions */
			$lines[] = sprintf( _n( '%d changed question was published right away as a new version.', '%d changed questions were published right away as new versions.', $result['published'], 'course-captain' ), $result['published'] );
		}
		if ( $result['draft'] > 0 ) {
			/* translators: %d: number of questions */
			$lines[] = sprintf( _n( '%d changed question is a draft: publish it in the editor.', '%d changed questions are drafts: publish them in the editor.', $result['draft'], 'course-captain' ), $result['draft'] );
		}
		if ( $result['unchanged'] > 0 ) {
			/* translators: %d: number of questions */
			$lines[] = sprintf( _n( '%d question was already in this state.', '%d questions were already in this state.', $result['unchanged'], 'course-captain' ), $result['unchanged'] );
		}
		if ( $result['failed'] > 0 ) {
			/* translators: %d: number of questions */
			$lines[] = sprintf( _n( '%d question could not be changed (not allowed or no longer there).', '%d questions could not be changed (not allowed or no longer there).', $result['failed'], 'course-captain' ), $result['failed'] );
		}
		return $lines;
	}

	/**
	 * Tags of the listed revisions in one query.
	 *
	 * @param array<int> $revisions Revision IDs.
	 * @return array<int, list<array<string, string>>>
	 */
	private static function row_tags( array $revisions ): array {
		$revisions = array_values( array_filter( array_map( 'intval', $revisions ) ) );
		if ( array() === $revisions ) {
			return array();
		}
		$db  = Database::current();
		$out = array();
		foreach ( $db->rows( 'SELECT revision_id, tag_type, tag_key, label FROM `' . $db->table( 'ccap_question_tag' ) . '` WHERE revision_id IN (' . implode( ',', $revisions ) . ') ORDER BY revision_id, tag_type, label' ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Integer list.
			$out[ (int) $row['revision_id'] ][] = array(
				'type'  => (string) $row['tag_type'],
				'key'   => (string) $row['tag_key'],
				'label' => (string) $row['label'],
			);
		}
		return $out;
	}

	/**
	 * Tags in use on the newest revisions (filter options), `type:key` → label.
	 *
	 * @return array<string, string>
	 */
	private static function known_tags(): array {
		$db   = Database::current();
		$r    = $db->table( 'ccap_question_revision' );
		$rows = $db->rows( 'SELECT DISTINCT t.tag_type, t.tag_key, t.label FROM `' . $db->table( 'ccap_question_tag' ) . "` t JOIN `{$r}` r ON r.id = t.revision_id WHERE r.id IN (SELECT MAX(id) FROM `{$r}` GROUP BY question_id) ORDER BY t.tag_type, t.label LIMIT 300" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names.
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ $row['tag_type'] . ':' . $row['tag_key'] ] = self::tag_label(
				array(
					'type'  => (string) $row['tag_type'],
					'key'   => (string) $row['tag_key'],
					'label' => (string) $row['label'],
				)
			);
		}
		return $out;
	}

	/**
	 * Visible tag name: "label" for topics, "kind: label" otherwise.
	 *
	 * @param array<string, mixed> $tag Tag.
	 * @return string
	 */
	public static function tag_label( array $tag ): string {
		$kinds = array(
			'subtopic'       => __( 'Subtopic', 'course-captain' ),
			'competency'     => __( 'Competency', 'course-captain' ),
			'smart_category' => __( 'Practice category', 'course-captain' ),
			'scope'          => __( 'Scope', 'course-captain' ),
		);
		$label = '' !== (string) ( $tag['label'] ?? '' ) ? (string) $tag['label'] : (string) ( $tag['key'] ?? '' );
		$type  = (string) ( $tag['type'] ?? 'topic' );
		/* translators: 1: kind of tag, 2: tag */
		return isset( $kinds[ $type ] ) ? sprintf( __( '%1$s: %2$s', 'course-captain' ), $kinds[ $type ], $label ) : $label;
	}

	/**
	 * Help text of the tag fields.
	 *
	 * @return string
	 */
	private static function tag_help(): string {
		return __( 'A tag is a topic, for example "Knots". Other kinds start with their name and a colon: subtopic, competency, smart_category (practice category) or scope, for example "competency: Navigation".', 'course-captain' );
	}

	/**
	 * Step 1 of a new question: type and module.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 */
	private static function type_step( array $request, int $user_id ): string {
		$html  = '<h1>' . esc_html__( 'Add question', 'course-captain' ) . '</h1>' . self::notice( $request, $user_id );
		$html .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="action" value="new">';
		$html .= '<fieldset><legend><strong>' . esc_html__( 'Step 1 of 2: What kind of question?', 'course-captain' ) . '</strong></legend>';
		$first = true;
		foreach ( QuestionForm::type_labels() as $key => $label ) {
			$html .= '<p><label><input type="radio" name="type" value="' . esc_attr( $key ) . '"' . ( $first ? ' required' : '' ) . '> ' . esc_html( $label ) . '</label></p>';
			$first = false;
		}
		$html .= '</fieldset><p><label for="ccap-new-module">' . esc_html__( 'Add to module (optional)', 'course-captain' ) . '</label><br>' . self::module_select( 'ccap-new-module', 'module', absint( $request['module'] ?? 0 ), __( '— no module —', 'course-captain' ) ) . '</p>';
		return $html . '<p><button type="submit" class="button button-primary">' . esc_html__( 'Next: enter the question', 'course-captain' ) . '</button></p></form>';
	}

	/**
	 * Editor (new or existing question).
	 *
	 * @param array<string, mixed> $request  GET.
	 * @param int                  $user_id  Viewer.
	 * @param int                  $question Question (0 = new).
	 * @param string               $type     Type of a new question.
	 * @return string
	 */
	private static function editor( array $request, int $user_id, int $question, string $type ): string {
		$repo     = QuestionRepository::current();
		$db       = Database::current();
		$revision = array();
		$status   = 'draft';
		if ( $question > 0 ) {
			if ( ! PolicyService::instance()->decide( Actor::user( $user_id ), PolicyService::EDIT_QUESTION, array( $question ) )->allowed ) {
				return '<h1>' . esc_html__( 'Edit question', 'course-captain' ) . '</h1><p>' . esc_html__( 'This question does not exist or you may not edit it.', 'course-captain' ) . '</p>';
			}
			$revision = (array) $repo->revision( self::latest_revision( $question ) );
			$type     = (string) ( $revision['question_type'] ?? '' );
			$status   = (string) $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question ) );
		}
		$kept      = get_transient( self::keep_key( $user_id ) );
		$tags_text = QuestionBulk::format_tags( (array) ( $revision['tags'] ?? array() ) );
		if ( is_array( $kept ) && '' !== (string) ( $request['ccap_result'] ?? '' ) && absint( $kept['object'] ?? 0 ) === $question ) {
			delete_transient( self::keep_key( $user_id ) );
			$tags_text           = isset( $kept['tags'] ) ? (string) $kept['tags'] : $tags_text;
			$restored            = QuestionForm::from_post( $kept );
			$restored['answers'] = array_values( $restored['answers'] );
			if ( QuestionForm::type_labels()[ $type ] ?? false ) {
				$revision = array_merge( $revision, $restored );
			}
		}
		$split  = QuestionForm::split_text( (string) ( $revision['question_text'] ?? '' ) );
		$image  = isset( $revision['image'] ) ? (array) $revision['image'] : $split['image'];
		$labels = QuestionForm::type_labels();
		$title  = 0 === $question ? __( 'Add question', 'course-captain' ) : __( 'Edit question', 'course-captain' );
		$html   = '<h1>' . esc_html( $title ) . '</h1><p><a href="' . esc_url( add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Back to the question bank', 'course-captain' ) . '</a></p>' . self::notice( $request, $user_id );
		$html  .= '<p>' . esc_html( sprintf( /* translators: %s: question type */ __( 'Question type: %s', 'course-captain' ), $labels[ $type ] ?? $type ) ) . ( $question > 0 ? ' · ' . esc_html( sprintf( /* translators: %s: status */ __( 'Status: %s', 'course-captain' ), self::status_label( $status ) ) ) : '' ) . '</p>';
		if ( 'published' === $status ) {
			$html .= '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'This question is published.', 'course-captain' ) . '</strong> ' . esc_html__( 'Saving creates a new version. Running exam attempts keep the version they started with; new attempts use the new version once you publish it.', 'course-captain' ) . '</p></div>';
		}
		$fields  = '<input type="hidden" name="question_type" value="' . esc_attr( $type ) . '">';
		$fields .= '<p><label for="ccap-q-text"><strong>' . esc_html__( 'Question text', 'course-captain' ) . '</strong></label><textarea id="ccap-q-text" name="question_text" rows="5" class="large-text" required>' . esc_textarea( $split['text'] ) . '</textarea>';
		if ( QuestionTypes::CLOZE === $type ) {
			$fields .= '<span class="description">' . esc_html__( 'Example: The capital of France is [1].', 'course-captain' ) . '</span>';
		}
		$fields .= '</p>' . QuestionForm::type_fields( $type, $revision );
		$fields .= self::image_fields( $image, ! empty( $revision['image_required'] ) );
		$fields .= '<p><label for="ccap-q-tags">' . esc_html__( 'Tags (optional, one per line)', 'course-captain' ) . '</label><textarea id="ccap-q-tags" name="tags" rows="3" class="large-text" aria-describedby="ccap-q-tags-help">' . esc_textarea( $tags_text ) . '</textarea><span id="ccap-q-tags-help" class="description">' . esc_html( self::tag_help() ) . '</span></p>';
		$fields .= '<fieldset><legend>' . esc_html__( 'Feedback (optional)', 'course-captain' ) . '</legend>';
		$fields .= '<p><label for="ccap-q-fb-ok">' . esc_html__( 'Text after a correct answer', 'course-captain' ) . '</label><textarea id="ccap-q-fb-ok" name="feedback_correct" rows="2" class="large-text">' . esc_textarea( (string) ( $revision['feedback_correct'] ?? '' ) ) . '</textarea></p>';
		$fields .= '<p><label for="ccap-q-fb-no">' . esc_html__( 'Text after a wrong answer', 'course-captain' ) . '</label><textarea id="ccap-q-fb-no" name="feedback_incorrect" rows="2" class="large-text">' . esc_textarea( (string) ( $revision['feedback_incorrect'] ?? '' ) ) . '</textarea></p></fieldset>';
		if ( 0 === $question ) {
			$fields .= '<p><label for="ccap-q-module">' . esc_html__( 'Add to module (optional)', 'course-captain' ) . '</label><br>' . self::module_select( 'ccap-q-module', 'module', absint( $request['module'] ?? 0 ), __( '— no module —', 'course-captain' ) ) . '</p>';
		}
		if ( 'published' === $status ) {
			$fields .= '<fieldset><legend>' . esc_html__( 'Kind of change', 'course-captain' ) . '</legend><label><input type="radio" name="change_class" value="editorial" checked> ' . esc_html__( 'Correction (typing error, wording; the meaning stays the same)', 'course-captain' ) . '</label><br><label><input type="radio" name="change_class" value="substantive"> ' . esc_html__( 'Change of content (other correct answer or other meaning)', 'course-captain' ) . '</label></fieldset>';
		}
		$fields .= '<p class="ccap-q-buttons"><button type="submit" name="op" value="save" class="button">' . esc_html__( 'Save draft', 'course-captain' ) . '</button> <button type="submit" name="op" value="save_publish" class="button button-primary">' . esc_html__( 'Save and publish', 'course-captain' ) . '</button></p>';
		$html   .= AdminUi::form_multi( self::ACTION, 'editor', $question, $fields, ' id="ccap-question-form" data-ccap-draft="question-' . (int) $question . '"' );
		if ( $question > 0 ) {
			if ( 'archived' !== $status ) {
				$html .= self::type_change_section( $request, $question, (array) $repo->revision( self::latest_revision( $question ) ) );
			}
			$html .= self::pool_section( $question );
			$html .= self::preview( $revision );
			$html .= self::history( $question );
			if ( 'archived' !== $status ) {
				$html .= '<h2>' . esc_html__( 'Archive', 'course-captain' ) . '</h2>' . AdminUi::form( self::ACTION, 'archive', $question, '<p><label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Confirm: archive the question (it is no longer delivered; results stay)', 'course-captain' ) . '</label></p>', __( 'Archive question', 'course-captain' ) );
			}
		}
		return $html . self::draft_script();
	}

	/**
	 * Question image with alt text (B-05, A11Y-110).
	 *
	 * @param array<string, mixed> $image    Image.
	 * @param bool                 $required Image needed to solve the question.
	 * @return string
	 */
	private static function image_fields( array $image, bool $required ): string {
		$images = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'numberposts'    => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$html   = '<fieldset id="ccap-q-image-set"><legend>' . esc_html__( 'Image (optional)', 'course-captain' ) . '</legend>';
		$html  .= '<p><label for="ccap-q-image">' . esc_html__( 'Image from the media library', 'course-captain' ) . '</label><br><select id="ccap-q-image" name="image_id"><option value="0">' . esc_html__( '— no image —', 'course-captain' ) . '</option>';
		foreach ( $images as $attachment ) {
			if ( $attachment instanceof \WP_Post ) {
				$html .= '<option value="' . (int) $attachment->ID . '"' . selected( (int) ( $image['attachment'] ?? 0 ), (int) $attachment->ID, false ) . '>' . esc_html( '' !== $attachment->post_title ? $attachment->post_title : basename( (string) get_attached_file( $attachment->ID ) ) ) . '</option>';
			}
		}
		$html .= '</select><br><span class="description">' . esc_html__( 'Upload new images under Media › Add New first.', 'course-captain' ) . '</span></p>';
		$html .= '<p><label for="ccap-q-image-alt">' . esc_html__( 'Alternative text (what the image shows, for screen readers)', 'course-captain' ) . '</label><input id="ccap-q-image-alt" type="text" class="large-text" name="image_alt" value="' . esc_attr( (string) ( $image['alt'] ?? '' ) ) . '" aria-describedby="ccap-q-image-alt-help"><br><span id="ccap-q-image-alt-help" class="description">' . esc_html__( 'Required unless the image is only decoration. Without it the course cannot be published.', 'course-captain' ) . '</span></p>';
		$html .= '<p><label><input type="checkbox" name="image_decorative" value="1"' . checked( ! empty( $image['decorative'] ), true, false ) . '> ' . esc_html__( 'The image is only decoration (no alternative text needed)', 'course-captain' ) . '</label><br>';
		$html .= '<label><input type="checkbox" name="image_required" value="1"' . checked( $required, true, false ) . '> ' . esc_html__( 'The question can only be solved with the image', 'course-captain' ) . '</label></p></fieldset>';
		return $html;
	}

	/**
	 * Module pools of the question.
	 *
	 * @param int $question Question.
	 * @return string
	 */
	private static function pool_section( int $question ): string {
		$db    = Database::current();
		$mods  = array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT module_id FROM `' . $db->table( 'ccap_module_question' ) . '` WHERE question_id = %d ORDER BY module_id', $question ) ) );
		$html  = '<h2>' . esc_html__( 'Modules using this question', 'course-captain' ) . '</h2>';
		$html .= array() === $mods ? '<p>' . esc_html__( 'The question is not in any module yet. Exams and practice only use questions of the course modules.', 'course-captain' ) . '</p>' : '<ul>';
		foreach ( $mods as $module ) {
			$html .= '<li>' . esc_html( (string) get_the_title( $module ) ) . ' ' . AdminUi::form( self::ACTION, 'pool_remove', $question, '<input type="hidden" name="module" value="' . (int) $module . '">', sprintf( /* translators: %s: module title */ __( 'Remove from module %s', 'course-captain' ), (string) get_the_title( $module ) ) ) . '</li>';
		}
		$html .= array() === $mods ? '' : '</ul>';
		$html .= AdminUi::form( self::ACTION, 'pool_add', $question, '<p><label for="ccap-pool-module">' . esc_html__( 'Add to module', 'course-captain' ) . '</label> ' . self::module_select( 'ccap-pool-module', 'module', 0, '' ) . ' <label for="ccap-pool-sort">' . esc_html__( 'Position (optional)', 'course-captain' ) . '</label> <input id="ccap-pool-sort" type="number" min="0" step="1" name="sort_order"></p>', __( 'Add to module', 'course-captain' ) );
		return $html;
	}

	/**
	 * Learner preview of the question (no solution data, A11Y-022 shape).
	 *
	 * @param array<string, mixed> $revision Revision.
	 * @return string
	 */
	public static function preview( array $revision ): string {
		if ( array() !== QuestionTypes::validate( $revision ) ) {
			return '<h2>' . esc_html__( 'Preview for learners', 'course-captain' ) . '</h2><p>' . esc_html__( 'The preview is shown once the question is complete.', 'course-captain' ) . '</p>';
		}
		$payload = QuestionTypes::deliver( $revision, new Randomizer( 'preview' ), 'preview' )['payload'];
		return '<h2>' . esc_html__( 'Preview for learners', 'course-captain' ) . '</h2>' . PreviewScreen::question_html( $payload, 1, 'ccap-qp' );
	}

	/**
	 * Revision history.
	 *
	 * @param int $question Question.
	 * @return string
	 */
	private static function history( int $question ): string {
		$db    = Database::current();
		$rows  = $db->rows( $db->prepare( 'SELECT revision_no, editorial_status, change_class, created_at, published_at FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE question_id = %d ORDER BY revision_no DESC', $question ) );
		$html  = '<h2>' . esc_html__( 'Versions', 'course-captain' ) . '</h2><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Version', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Kind of change', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Created (UTC)', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Published (UTC)', 'course-captain' ) . '</th></tr></thead><tbody>';
		$state = array(
			'draft'      => __( 'draft', 'course-captain' ),
			'published'  => __( 'published', 'course-captain' ),
			'deprecated' => __( 'replaced', 'course-captain' ),
		);
		foreach ( $rows as $row ) {
			$html .= '<tr><td>' . (int) $row['revision_no'] . '</td><td>' . esc_html( $state[ (string) $row['editorial_status'] ] ?? (string) $row['editorial_status'] ) . '</td><td>' . esc_html( 'substantive' === $row['change_class'] ? __( 'change of content', 'course-captain' ) : __( 'correction', 'course-captain' ) ) . '</td><td>' . esc_html( (string) $row['created_at'] ) . '</td><td>' . esc_html( (string) ( $row['published_at'] ?? '–' ) ) . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Module select.
	 *
	 * @param string $id       Field ID.
	 * @param string $name     Name.
	 * @param int    $selected Selected.
	 * @param string $placeholder    Empty option label ('' = none).
	 * @return string
	 */
	public static function module_select( string $id, string $name, int $selected, string $placeholder ): string {
		$modules = get_posts(
			array(
				'post_type'   => Relations::MODULE_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$html    = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . ( '' !== $placeholder ? '<option value="0">' . esc_html( $placeholder ) . '</option>' : '' );
		foreach ( $modules as $module ) {
			if ( $module instanceof \WP_Post ) {
				$courses = array_map( static fn( int $c ): string => (string) get_the_title( $c ), ( new Relations( Database::current() ) )->courses_of_module( (int) $module->ID ) );
				$html   .= '<option value="' . (int) $module->ID . '"' . selected( $selected, (int) $module->ID, false ) . '>' . esc_html( $module->post_title . ( array() === $courses ? '' : ' (' . implode( ', ', $courses ) . ')' ) ) . '</option>';
			}
		}
		return $html . '</select>';
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'draft'     => __( 'draft', 'course-captain' ),
			'published' => __( 'published', 'course-captain' ),
			'archived'  => __( 'archived', 'course-captain' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Progressive enhancement against lost input (UX-012): the browser keeps
	 * unsaved entries of the open form and offers them again after a
	 * session or network loss. The form works without it.
	 *
	 * @return string
	 */
	public static function draft_script(): string {
		$restore = esc_js( __( 'Unsaved entries from your last visit were restored. Check them and save.', 'course-captain' ) );
		return '<script>(function(){var f=document.querySelector("form[data-ccap-draft]");if(!f||!window.localStorage){return;}var k="ccap-draft-"+f.getAttribute("data-ccap-draft");try{var s=JSON.parse(localStorage.getItem(k)||"null");if(s){var n=0;Object.keys(s).forEach(function(name){var el=f.elements[name];if(el&&el.type!=="hidden"&&el.type!=="checkbox"&&el.type!=="radio"&&el.value!==s[name]){el.value=s[name];n++;}});if(n){var p=document.createElement("div");p.className="notice notice-info";p.setAttribute("role","status");p.innerHTML="<p></p>";p.firstChild.textContent="' . $restore . '";f.parentNode.insertBefore(p,f);}}}catch(e){}f.addEventListener("input",function(){var d={};Array.prototype.forEach.call(f.elements,function(el){if(el.name&&(el.tagName==="TEXTAREA"||el.type==="text")){d[el.name]=el.value;}});try{localStorage.setItem(k,JSON.stringify(d));}catch(e){}});f.addEventListener("submit",function(){try{localStorage.removeItem(k);}catch(e){}});})();</script>';
	}

	/**
	 * Transient key for kept form input.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	private static function keep_key( int $user_id ): string {
		return 'ccap_question_form_' . $user_id;
	}

	/**
	 * Transient key for kept messages.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	private static function messages_key( int $user_id ): string {
		return 'ccap_question_msg_' . $user_id;
	}
}
