<?php
/**
 * WP-native authoring screens (R1-05): menu, course and lesson panels.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Activity\ActivityRegistry;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Publish\CourseValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Classic meta boxes next to the block editor; every field has a visible
 * label, states are text (no symbols only), forms work without JavaScript
 * (order via number fields). Saving goes through {@see AuthoringService};
 * errors come back as bundled notices.
 */
final class EditorScreens {

	public const MENU = 'course-captain';

	/**
	 * A course save of this request is running (nested `save_post` from publishing).
	 *
	 * @var bool
	 */
	private static bool $saving = false;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'add_meta_boxes', array( self::class, 'meta_boxes' ) );
		add_action( 'save_post_' . Relations::COURSE_TYPE, array( self::class, 'save_course' ), 20, 2 );
		add_action( 'save_post_' . Relations::LESSON_TYPE, array( self::class, 'save_lesson' ), 20, 2 );
		add_filter( 'post_row_actions', array( self::class, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_ccap_duplicate', array( self::class, 'handle_duplicate' ) );
		add_action( 'admin_notices', array( self::class, 'flash' ), 5 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_notices' ) );
		add_action( 'wp_ajax_ccap_editor_flash', array( self::class, 'ajax_flash' ) );
	}

	/**
	 * Top-level menu with the content types.
	 *
	 * @return void
	 */
	public static function menu(): void {
		add_menu_page( __( 'Course Captain', 'course-captain' ), __( 'Course Captain', 'course-captain' ), 'ccap_edit_courses', self::MENU, '__return_null', 'dashicons-welcome-learn-more', 26 );
		foreach ( array( Relations::COURSE_TYPE, Relations::MODULE_TYPE, Relations::LESSON_TYPE ) as $type ) {
			$object = get_post_type_object( $type );
			if ( null !== $object ) {
				add_submenu_page( self::MENU, (string) $object->labels->name, (string) $object->labels->name, (string) $object->cap->edit_posts, 'edit.php?post_type=' . $type );
				// The types are not shown in a menu of their own (`show_in_menu` false), so
				// `post-new.php?post_type=…` needs its own entry: without it WordPress finds
				// no parent page and denies roles without the core `edit_posts` (Course
				// Manager, Course Author) because the core "Add New Post" entry is hidden
				// for them (user_can_access_admin_page()).
				add_submenu_page( self::MENU, (string) $object->labels->add_new_item, (string) $object->labels->add_new_item, (string) $object->cap->create_posts, 'post-new.php?post_type=' . $type );
			}
		}
		remove_submenu_page( self::MENU, self::MENU );
	}

	/**
	 * Meta boxes.
	 *
	 * @return void
	 */
	public static function meta_boxes(): void {
		add_meta_box( 'ccap-course-structure', __( 'Modules', 'course-captain' ), array( self::class, 'render_structure' ), Relations::COURSE_TYPE, 'normal', 'high' );
		add_meta_box( 'ccap-course-publish', __( 'Publish revision', 'course-captain' ), array( self::class, 'render_publish' ), Relations::COURSE_TYPE, 'side', 'high' );
		add_meta_box( 'ccap-lesson-activity', __( 'Activity', 'course-captain' ), array( self::class, 'render_activity' ), Relations::LESSON_TYPE, 'normal', 'high' );
	}

	/**
	 * Course structure panel.
	 *
	 * @param \WP_Post $post Course.
	 * @return void
	 */
	public static function render_structure( \WP_Post $post ): void {
		wp_nonce_field( 'ccap_course_structure_' . $post->ID, 'ccap_structure_nonce' );
		try {
			$rows = AuthoringService::current()->structure( Actor::user( get_current_user_id() ), (int) $post->ID );
		} catch ( CourseException $e ) {
			$rows = array();
		}
		echo '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Modules of this course', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Module', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Position', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Required', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Remove', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $i => $row ) {
			$id = 'ccap-module-' . $row['module_id'];
			echo '<tr><td>' . esc_html( $row['title'] ) . ' <span class="description">(' . esc_html( sprintf( /* translators: %d: lesson count */ _n( '%d lesson', '%d lessons', $row['lessons'], 'course-captain' ), $row['lessons'] ) ) . ')</span><input type="hidden" name="ccap_modules[' . (int) $i . '][module_id]" value="' . (int) $row['module_id'] . '"></td>';
			echo '<td><label class="screen-reader-text" for="' . esc_attr( $id ) . '-sort">' . esc_html__( 'Position', 'course-captain' ) . '</label><input id="' . esc_attr( $id ) . '-sort" type="number" min="0" step="1" name="ccap_modules[' . (int) $i . '][sort_order]" value="' . (int) $row['sort_order'] . '"></td>';
			echo '<td><input type="hidden" name="ccap_modules[' . (int) $i . '][required]" value="0"><label><input type="checkbox" name="ccap_modules[' . (int) $i . '][required]" value="1"' . checked( $row['required'], true, false ) . '> ' . esc_html__( 'Required', 'course-captain' ) . '</label></td>';
			echo '<td><label><input type="checkbox" name="ccap_modules[' . (int) $i . '][remove]" value="1"> ' . esc_html__( 'Remove from course', 'course-captain' ) . '</label></td></tr>';
		}
		echo '</tbody></table>';
		echo '<input type="hidden" name="ccap_structure_rev" value="' . esc_attr( self::structure_rev( $rows ) ) . '">';
		$assigned = array_column( $rows, 'module_id' );
		$modules  = get_posts(
			array(
				'post_type'   => Relations::MODULE_TYPE,
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
				'exclude'     => $assigned,
			)
		);
		echo '<p><label for="ccap-add-module">' . esc_html__( 'Add module', 'course-captain' ) . '</label> <select id="ccap-add-module" name="ccap_add_module"><option value="0">' . esc_html__( '— none —', 'course-captain' ) . '</option>';
		foreach ( $modules as $module ) {
			if ( $module instanceof \WP_Post ) {
				echo '<option value="' . (int) $module->ID . '">' . esc_html( $module->post_title ) . '</option>';
			}
		}
		echo '</select></p><p class="description">' . esc_html__( 'Changes apply to the draft. Learners keep their published revision until you publish again.', 'course-captain' ) . '</p>';
	}

	/**
	 * Publish panel with change class.
	 *
	 * @param \WP_Post $post Course.
	 * @return void
	 */
	public static function render_publish( \WP_Post $post ): void {
		$current = CoursePublisher::current()->current_revision( (int) $post->ID );
		echo '<p>' . esc_html( null === $current ? __( 'Not published yet.', 'course-captain' ) : sprintf( /* translators: %d: revision number */ __( 'Published revision: %d', 'course-captain' ), (int) $current['revision_no'] ) ) . '</p>';
		if ( 'auto-draft' !== $post->post_status ) {
			echo '<p><a href="' . esc_url( PreviewScreen::url( (int) $post->ID ) ) . '">' . esc_html__( 'Preview as learner', 'course-captain' ) . '</a></p>';
		}
		echo '<fieldset><legend>' . esc_html__( 'Kind of change', 'course-captain' ) . '</legend>';
		echo '<label><input type="radio" name="ccap_change_class" value="patch"> ' . esc_html__( 'Correction (no effect on progress)', 'course-captain' ) . '</label><br>';
		echo '<label><input type="radio" name="ccap_change_class" value="minor" checked> ' . esc_html__( 'Content change', 'course-captain' ) . '</label></fieldset>';
		echo '<p><label for="ccap-publish-notes">' . esc_html__( 'Note for learners (optional)', 'course-captain' ) . '</label><textarea id="ccap-publish-notes" name="ccap_publish_notes" rows="3" class="widefat"></textarea></p>';
		echo self::validation_panel( (int) $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while building.
		// UI-F6: the value names the revision this form was rendered for. The block editor
		// saves the meta boxes again without reloading them; a box still ticked from an
		// earlier save then no longer matches and does not publish once more.
		echo '<p><label><input type="checkbox" name="ccap_publish_revision" value="' . esc_attr( self::publish_base( null === $current ? 0 : (int) $current['revision_no'] ) ) . '"> ' . esc_html__( 'Publish a new revision when saving', 'course-captain' ) . '</label></p>';
	}

	/**
	 * Value of the "publish" box: the published revision the form was rendered for.
	 *
	 * @param int $revision_no Published revision number (0 = none).
	 * @return string
	 */
	private static function publish_base( int $revision_no ): string {
		return 'base:' . $revision_no;
	}

	/**
	 * Whether a posted "publish" box still belongs to the current state:
	 * `1` (forms without a base, e.g. scripts) or `base:<n>` with the
	 * current published revision number.
	 *
	 * @param string $posted    Posted value.
	 * @param int    $course_id Course.
	 * @return bool
	 */
	private static function publish_requested( string $posted, int $course_id ): bool {
		if ( '1' === $posted ) {
			return true;
		}
		if ( 1 !== preg_match( '/^base:(\d{1,9})$/', $posted, $m ) ) {
			return false;
		}
		$current   = CoursePublisher::current()->current_revision( $course_id );
		$published = null === $current ? 0 : (int) $current['revision_no'];
		return $published === (int) $m[1];
	}

	/**
	 * Validation summary (R0-22 §3.1/§3.3): blockers, warnings with
	 * acknowledgement boxes, notices; announced politely to screen readers.
	 *
	 * @param int $course_id Course.
	 * @return string
	 */
	public static function validation_panel( int $course_id ): string {
		$report = CourseValidator::validate( $course_id, 'live' );
		$html   = '<div class="ccap-validation" role="status" aria-live="polite"><p><strong>' . esc_html(
			sprintf(
				/* translators: 1: blockers, 2: warnings, 3: notices */
				__( 'Check: %1$d blockers, %2$d warnings, %3$d notes', 'course-captain' ),
				$report['summary']['blocker'],
				$report['summary']['warning'],
				$report['summary']['notice']
			)
		) . '</strong></p><ul>';
		$labels = array(
			'blocker' => __( 'Blocker', 'course-captain' ),
			'warning' => __( 'Warning', 'course-captain' ),
			'notice'  => __( 'Note', 'course-captain' ),
		);
		foreach ( $report['issues'] as $i => $issue ) {
			$text = esc_html( $labels[ $issue['severity'] ] . ': ' . $issue['object']['title'] . ' – ' . $issue['message'] ) . ' <span class="description">(' . esc_html( $issue['code'] ) . ')</span>' . self::jump_link( $issue );
			if ( 'warning' === $issue['severity'] ) {
				$html .= '<li><label><input type="checkbox" name="ccap_ack[]" value="' . esc_attr( $issue['fingerprint'] ) . '"> ' . esc_html__( 'Read:', 'course-captain' ) . ' ' . $text . '</label></li>';
			} else {
				$html .= '<li>' . $text . '</li>';
			}
			unset( $i );
		}
		return $html . '</ul></div>';
	}

	/**
	 * Version of a module list as shown in the form (order, position, required).
	 *
	 * @param list<array{module_id: int, sort_order: int, required: bool}> $rows Rows of AuthoringService::structure().
	 * @return string
	 */
	private static function structure_rev( array $rows ): string {
		return md5( (string) wp_json_encode( array_map( static fn( array $r ): array => array( (int) $r['module_id'], (int) $r['sort_order'], (bool) $r['required'] ), $rows ) ) );
	}

	/**
	 * Jump link of an issue (R0-11 F1.1, R0-22 §1.3 `target`): opens the
	 * editor of the module or lesson the issue names. Only for objects the
	 * viewer may edit; otherwise nothing is linked (no existence leak).
	 * Issues of the course itself are fixed on the current screen.
	 *
	 * @param array<string, mixed> $issue Issue of the validation report.
	 * @return string
	 */
	private static function jump_link( array $issue ): string {
		$object = (array) ( $issue['object'] ?? array() );
		$id     = (int) ( $object['id'] ?? 0 );
		if ( $id <= 0 || ! in_array( (string) ( $object['type'] ?? '' ), array( 'module', 'lesson', 'activity' ), true ) || ! current_user_can( 'edit_post', $id ) ) {
			return '';
		}
		$url = get_edit_post_link( $id, 'raw' );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}
		$title = '' === (string) ( $object['title'] ?? '' ) ? (string) $issue['code'] : (string) $object['title'];
		/* translators: %s: module or lesson title */
		return ' <a href="' . esc_url( $url ) . '" class="ccap-validation-jump">' . esc_html( sprintf( __( 'Edit "%s"', 'course-captain' ), $title ) ) . '</a>';
	}

	/**
	 * Lesson activity panel.
	 *
	 * @param \WP_Post $post Lesson.
	 * @return void
	 */
	public static function render_activity( \WP_Post $post ): void {
		wp_nonce_field( 'ccap_lesson_activity_' . $post->ID, 'ccap_activity_nonce' );
		$definition = ActivityService::definition( (int) $post->ID );
		$modules    = get_posts(
			array(
				'post_type'   => Relations::MODULE_TYPE,
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		echo '<p><label for="ccap-lesson-module">' . esc_html__( 'Module', 'course-captain' ) . '</label><br><select id="ccap-lesson-module" name="ccap_lesson_module"><option value="0">' . esc_html__( '— none —', 'course-captain' ) . '</option>';
		foreach ( $modules as $module ) {
			if ( $module instanceof \WP_Post && ( (int) $module->ID === (int) $post->post_parent || current_user_can( 'edit_post', $module->ID ) ) ) {
				echo '<option value="' . (int) $module->ID . '"' . selected( (int) $post->post_parent, (int) $module->ID, false ) . '>' . esc_html( '' === $module->post_title ? sprintf( /* translators: %d: module ID */ __( 'Module without title (#%d)', 'course-captain' ), $module->ID ) : $module->post_title ) . '</option>';
			}
		}
		echo '</select></p>';
		echo '<p><label for="ccap-activity-type">' . esc_html__( 'Activity type', 'course-captain' ) . '</label><br><select id="ccap-activity-type" name="ccap_activity_type">';
		foreach ( array_keys( ActivityRegistry::instance()->all() ) as $type ) {
			echo '<option value="' . esc_attr( $type ) . '"' . selected( $definition['type'], $type, false ) . '>' . esc_html( $type ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label for="ccap-activity-settings">' . esc_html__( 'Settings (JSON)', 'course-captain' ) . '</label><textarea id="ccap-activity-settings" name="ccap_activity_settings" rows="4" class="widefat code">' . esc_textarea( (string) wp_json_encode( (object) $definition['settings'], JSON_PRETTY_PRINT ) ) . '</textarea></p>';
		echo '<p><label for="ccap-activity-rule">' . esc_html__( 'Completion rule', 'course-captain' ) . '</label><br><input id="ccap-activity-rule" type="text" name="ccap_activity_rule" value="' . esc_attr( (string) ( $definition['rule']['type'] ?? '' ) ) . '" class="regular-text"><br><span class="description">' . esc_html__( 'Leave empty for the default rule of the type.', 'course-captain' ) . '</span></p>';
		echo '<p class="description">' . esc_html__( 'The order of lessons in a module follows the "Order" field in the page attributes. Use "Preview" to see the lesson as a learner.', 'course-captain' ) . '</p>';
	}

	/**
	 * Save the course panels.
	 *
	 * @param int      $post_id Course.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function save_course( int $post_id, \WP_Post $post ): void {
		if ( self::$saving ) {
			// Publishing a draft updates the post status, which runs this hook again (UI-F6).
			return;
		}
		if ( ! isset( $_POST['ccap_structure_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['ccap_structure_nonce'] ) ), 'ccap_course_structure_' . $post_id ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$actor = Actor::user( get_current_user_id() );
		$items = array();
		foreach ( (array) ( wp_unslash( $_POST['ccap_modules'] ?? array() ) ) as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated in AuthoringService.
			if ( is_array( $row ) && empty( $row['remove'] ) ) {
				$items[] = $row;
			}
		}
		$add = isset( $_POST['ccap_add_module'] ) ? absint( $_POST['ccap_add_module'] ) : 0;
		// The block editor saves the meta boxes again without reloading them, so a
		// second save posts the module rows of the first page load. Rows that no
		// longer match the stored list are not applied (they would silently drop
		// modules added in between, F1.4); only an added module is kept.
		$posted_rev = isset( $_POST['ccap_structure_rev'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['ccap_structure_rev'] ) ) : '';
		if ( '' !== $posted_rev ) {
			try {
				$current = AuthoringService::current()->structure( $actor, $post_id );
			} catch ( CourseException $e ) {
				$current = array();
			}
			if ( ! hash_equals( self::structure_rev( $current ), $posted_rev ) ) {
				$items = array_map(
					static fn( array $row ): array => array(
						'module_id'  => $row['module_id'],
						'sort_order' => $row['sort_order'],
						'required'   => $row['required'],
					),
					$current
				);
				if ( in_array( $add, array_column( $current, 'module_id' ), true ) ) {
					$add = 0;
				}
				set_transient( 'ccap_editor_error_' . get_current_user_id(), __( 'The module list was changed by an earlier save. Only an added module was saved; reload the page to change positions or remove modules.', 'course-captain' ), 600 );
			}
		}
		if ( $add > 0 ) {
			$items[] = array( 'module_id' => $add );
		}
		self::$saving = true;
		try {
			self::save_course_steps( $actor, $post_id, $items, $post );
		} finally {
			self::$saving = false;
		}
	}

	/**
	 * Structure and optional publish of one course save.
	 *
	 * @param Actor    $actor   Author.
	 * @param int      $post_id Course.
	 * @param array    $items   Module rows.
	 * @phpstan-param list<array<string, mixed>> $items
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	private static function save_course_steps( Actor $actor, int $post_id, array $items, \WP_Post $post ): void {
		self::attempt(
			static function () use ( $actor, $post_id, $items, $post ): void {
				AuthoringService::current()->update_structure( $actor, $post_id, $items );
				$publish = isset( $_POST['ccap_publish_revision'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['ccap_publish_revision'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
				if ( '' !== $publish && ! self::publish_requested( $publish, $post_id ) ) {
					// UI-F6: the box was ticked for a revision that has been published since.
					set_transient( 'ccap_editor_error_' . get_current_user_id(), __( 'Not published again: this revision was already published by an earlier save. Tick "Publish a new revision when saving" again to publish another revision.', 'course-captain' ), 600 );
					$publish = '';
				}
				if ( '' !== $publish && 'auto-draft' !== $post->post_status ) {
					$class = isset( $_POST['ccap_change_class'] ) ? sanitize_key( wp_unslash( (string) $_POST['ccap_change_class'] ) ) : 'minor'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
					$notes = isset( $_POST['ccap_publish_notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['ccap_publish_notes'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
					$ack   = array_values( array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['ccap_ack'] ?? array() ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
					AuthoringService::current()->publish( $actor, $post_id, $class, $notes, $ack );
				}
			}
		);
	}

	/**
	 * Save the lesson activity panel.
	 *
	 * @param int      $post_id Lesson.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function save_lesson( int $post_id, \WP_Post $post ): void {
		unset( $post );
		if ( ! isset( $_POST['ccap_activity_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['ccap_activity_nonce'] ) ), 'ccap_lesson_activity_' . $post_id ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$type     = isset( $_POST['ccap_activity_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['ccap_activity_type'] ) ) : '';
		$settings = json_decode( isset( $_POST['ccap_activity_settings'] ) ? (string) wp_unslash( $_POST['ccap_activity_settings'] ) : '{}', true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, validated by the provider schema.
		$rule     = isset( $_POST['ccap_activity_rule'] ) ? sanitize_key( wp_unslash( (string) $_POST['ccap_activity_rule'] ) ) : '';
		$module   = isset( $_POST['ccap_lesson_module'] ) ? absint( $_POST['ccap_lesson_module'] ) : 0;
		if ( $module > 0 ) {
			self::attempt(
				static function () use ( $post_id, $module ): void {
					AuthoringService::current()->move_lesson( Actor::user( get_current_user_id() ), $post_id, $module );
				}
			);
		}
		self::attempt(
			static function () use ( $post_id, $type, $settings, $rule ): void {
				if ( ! is_array( $settings ) ) {
					throw new CourseException( 'ccap_validation_failed', 'settings_json', 422 );
				}
				AuthoringService::current()->define_activity( Actor::user( get_current_user_id() ), $post_id, $type, $settings, '' === $rule ? array() : array( 'type' => $rule ) );
			}
		);
	}

	/**
	 * "Duplicate" row action for modules and lessons.
	 *
	 * @param array<string, string> $actions Actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( in_array( $post->post_type, array( Relations::MODULE_TYPE, Relations::LESSON_TYPE ), true ) && current_user_can( 'edit_post', $post->ID ) ) {
			$url                       = wp_nonce_url( admin_url( 'admin-post.php?action=ccap_duplicate&post=' . (int) $post->ID ), 'ccap_duplicate_' . $post->ID );
			$actions['ccap_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'course-captain' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * `admin-post` duplicate handler.
	 *
	 * @return void
	 */
	public static function handle_duplicate(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'ccap_duplicate_' . $post_id );
		try {
			$copy = AuthoringService::current()->duplicate( Actor::user( get_current_user_id() ), $post_id );
			wp_safe_redirect( (string) get_edit_post_link( $copy, 'raw' ) );
		} catch ( CourseException $e ) {
			wp_die( esc_html__( 'The item could not be duplicated.', 'course-captain' ), '', array( 'response' => (int) $e->status ) );
		}
		exit;
	}

	/**
	 * Show errors stored during the save request.
	 *
	 * @return void
	 */
	public static function flash(): void {
		if ( ! self::page_view() ) {
			return;
		}
		$error = self::pull_flash();
		if ( '' !== $error ) {
			Notices::add( 'editor_save', 'error', $error );
		}
	}

	/**
	 * Whether the current request is a page a person sees. The block editor
	 * saves the meta boxes with fetch() and follows the redirect to the edit
	 * screen in the background; that page is never shown, so it must not
	 * consume the message (the author would never see it, F1.4). Browsers
	 * mark such requests with `Sec-Fetch-Mode` other than `navigate`.
	 *
	 * @return bool
	 */
	private static function page_view(): bool {
		$mode = isset( $_SERVER['HTTP_SEC_FETCH_MODE'] ) ? sanitize_key( wp_unslash( (string) $_SERVER['HTTP_SEC_FETCH_MODE'] ) ) : 'navigate';
		return 'navigate' === $mode;
	}

	/**
	 * Take the stored save message of the current user (once).
	 *
	 * @return string Message or empty.
	 */
	public static function pull_flash(): string {
		$key   = 'ccap_editor_error_' . get_current_user_id();
		$error = get_transient( $key );
		if ( ! is_string( $error ) || '' === $error ) {
			return '';
		}
		delete_transient( $key );
		return $error;
	}

	/**
	 * Block editor of courses, modules and lessons: classic admin notices are
	 * hidden there and the meta boxes are saved in the background, so the save
	 * messages are shown as editor notices (on load and after each meta box
	 * save, `assets/js/editor-notices.js`).
	 *
	 * @return void
	 */
	public static function editor_notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || ! in_array( $screen->post_type, array( Relations::COURSE_TYPE, Relations::MODULE_TYPE, Relations::LESSON_TYPE ), true ) ) {
			return;
		}
		wp_enqueue_script( 'ccap-editor-notices', plugins_url( 'assets/js/editor-notices.js', COURSE_CAPTAIN_FILE ), array( 'wp-data', 'wp-notices' ), COURSE_CAPTAIN_VERSION, true );
		wp_localize_script(
			'ccap-editor-notices',
			'ccapEditorNotices',
			array(
				'url'     => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ccap_editor_flash' ),
				'message' => self::page_view() ? self::pull_flash() : '',
				'postId'  => isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ? (int) $GLOBALS['post']->ID : 0,
			)
		);
	}

	/**
	 * `wp_ajax_ccap_editor_flash`: the pending save message after a meta box save.
	 *
	 * @return void
	 */
	public static function ajax_flash(): void {
		check_ajax_referer( 'ccap_editor_flash' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		wp_send_json_success(
			array(
				'message' => self::pull_flash(),
				'boxes'   => self::fresh_boxes( $post_id ),
			)
		);
	}

	/**
	 * UI-F6: the meta boxes of a post rendered from the stored state, so the
	 * block editor can replace the panels after a background save (module
	 * list, structure version, published revision, validation, the
	 * "publish" box unticked). Only for posts the viewer may edit.
	 *
	 * @param int $post_id Course or lesson.
	 * @return array<string, string> Meta box ID → inner HTML.
	 */
	public static function fresh_boxes( int $post_id ): array {
		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post_id ) ) {
			return array();
		}
		$boxes = array();
		if ( Relations::COURSE_TYPE === $post->post_type ) {
			$boxes = array(
				'ccap-course-structure' => array( self::class, 'render_structure' ),
				'ccap-course-publish'   => array( self::class, 'render_publish' ),
			);
		} elseif ( Relations::LESSON_TYPE === $post->post_type ) {
			$boxes = array( 'ccap-lesson-activity' => array( self::class, 'render_activity' ) );
		}
		$out = array();
		foreach ( $boxes as $id => $render ) {
			ob_start();
			$render( $post );
			$out[ $id ] = (string) ob_get_clean();
		}
		return $out;
	}

	/**
	 * Run a save step and keep its error for the next screen.
	 *
	 * @param callable $step Step.
	 * @return void
	 */
	private static function attempt( callable $step ): void {
		try {
			$step();
		} catch ( CourseException $e ) {
			/* translators: %s: error details */
			set_transient( 'ccap_editor_error_' . get_current_user_id(), sprintf( __( 'Not saved: %s', 'course-captain' ), $e->getMessage() ), 600 );
		}
	}
}
