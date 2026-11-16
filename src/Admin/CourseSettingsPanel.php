<?php
/**
 * Course editor panel "Settings for this course" (R0-28 §1.7, R1-33).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Per key a radio group "Use the default" / "Set for this course"
 * (`fieldset/legend`), the inherited value as text, locked keys read-only
 * (`aria-readonly`) with the website's reason, resting overrides named.
 * Below, the panel "Effective settings" for the course and each exam rule
 * shows value, source and when a change applies — all from
 * {@see EffectiveSettings}, never an own calculation. Saved with the
 * course (nonce, M-03 in the service) before a revision is published.
 */
final class CourseSettingsPanel {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'meta_box' ) );
		add_action( 'save_post_' . Relations::COURSE_TYPE, array( self::class, 'save' ), 15, 2 );
	}

	/**
	 * Meta box.
	 *
	 * @return void
	 */
	public static function meta_box(): void {
		add_meta_box( 'ccap-course-settings', __( 'Settings for this course', 'course-captain' ), array( self::class, 'render' ), Relations::COURSE_TYPE, 'normal', 'default' );
	}

	/**
	 * Render the panel.
	 *
	 * @param \WP_Post $post Course.
	 * @return void
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( 'ccap_course_settings_' . $post->ID, 'ccap_settings_nonce' );
		echo self::html( (int) $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while building.
	}

	/**
	 * Panel HTML (escaped).
	 *
	 * @param int $course Course.
	 * @return string
	 */
	public static function html( int $course ): string {
		$html = '<p class="description">' . esc_html__( 'A course uses the website defaults unless you set a value here. Changes apply with the next published revision; running exam attempts keep the values they started with.', 'course-captain' ) . '</p>';
		foreach ( EffectiveSettings::course_panel( $course ) as $row ) {
			$html .= self::field_html( $row );
		}
		$html .= '<p><label><input type="checkbox" name="ccap_settings_reset" value="1"> ' . esc_html__( 'Reset all settings of this course to the website defaults (can be undone for 30 days)', 'course-captain' ) . '</label></p>';
		if ( is_array( get_post_meta( $course, EffectiveSettings::UNDO_META, true ) ) ) {
			$html .= '<p><label><input type="checkbox" name="ccap_settings_undo" value="1"> ' . esc_html__( 'Undo the last reset', 'course-captain' ) . '</label></p>';
		}
		return $html . self::effective_html( $course );
	}

	/**
	 * Save the overrides of the course.
	 *
	 * @param int      $post_id Course.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function save( int $post_id, \WP_Post $post ): void {
		unset( $post );
		if ( ! isset( $_POST['ccap_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['ccap_settings_nonce'] ) ), 'ccap_course_settings_' . $post_id ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$actor = Actor::user( get_current_user_id() );
		try {
			if ( ! empty( $_POST['ccap_settings_undo'] ) ) {
				EffectiveSettings::undo_course( $actor, $post_id );
				return;
			}
			if ( ! empty( $_POST['ccap_settings_reset'] ) ) {
				EffectiveSettings::reset_course( $actor, $post_id );
				return;
			}
			$input  = (array) wp_unslash( $_POST['ccap_settings'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated against the register in EffectiveSettings.
			$result = EffectiveSettings::save_course( $actor, $post_id, self::changes( $post_id, $input ) );
			if ( array() !== $result['rejected'] ) {
				/* translators: %s: setting names */
				set_transient( 'ccap_editor_error_' . get_current_user_id(), sprintf( __( 'Not saved because the website sets these values: %s', 'course-captain' ), implode( ', ', self::names( array_keys( $result['rejected'] ) ) ) ), 600 );
			}
		} catch ( CourseException $e ) {
			/* translators: %s: error details */
			set_transient( 'ccap_editor_error_' . get_current_user_id(), sprintf( __( 'Settings not saved: %s', 'course-captain' ), $e->getMessage() ), 600 );
		}
	}

	/**
	 * Turn the form input into service changes.
	 *
	 * @param int                  $course Course.
	 * @param array<string, mixed> $input  `mode[key]` and `value[key]`.
	 * @return array<string, mixed>
	 */
	public static function changes( int $course, array $input ): array {
		$current = EffectiveSettings::course_overrides( $course );
		$modes   = is_array( $input['mode'] ?? null ) ? $input['mode'] : array();
		$values  = is_array( $input['value'] ?? null ) ? $input['value'] : array();
		$changes = array();
		foreach ( $modes as $key => $mode ) {
			$key = (string) $key;
			if ( 'inherit' === $mode ) {
				if ( array_key_exists( $key, $current ) ) {
					$changes[ $key ] = EffectiveSettings::INHERIT;
				}
				continue;
			}
			if ( 'set' !== $mode || ! array_key_exists( $key, $values ) ) {
				continue;
			}
			$value = $values[ $key ];
			if ( is_array( $value ) ) {
				$value = array_map( static fn( $v ) => is_string( $v ) ? trim( $v ) : $v, $value );
			} elseif ( is_string( $value ) ) {
				$value = trim( $value );
			}
			$changes[ $key ] = $value;
		}
		return $changes;
	}

	/**
	 * One key as radio group.
	 *
	 * @param array<string, mixed> $row Panel row.
	 * @return string
	 */
	private static function field_html( array $row ): string {
		$key   = (string) $row['key'];
		$id    = 'ccap-set-' . sanitize_html_class( str_replace( '.', '-', $key ) );
		$field = EffectiveSettings::field( $key );
		$html  = '<fieldset class="ccap-setting" aria-describedby="' . esc_attr( $id ) . '-state"><legend><strong>' . esc_html( (string) $row['label'] ) . '</strong></legend>';
		if ( $row['locked'] ) {
			$html .= '<p id="' . esc_attr( $id ) . '-state">';
			$html .= '<label for="' . esc_attr( $id ) . '-locked">' . esc_html__( 'Set by the website:', 'course-captain' ) . '</label> <input id="' . esc_attr( $id ) . '-locked" type="text" readonly aria-readonly="true" value="' . esc_attr( self::display( $row['value'] ) ) . '">';
			if ( '' !== $row['lock_reason'] ) {
				$html .= ' <span>' . esc_html( (string) $row['lock_reason'] ) . '</span>';
			}
			if ( $row['resting'] ) {
				/* translators: %s: value */
				$html .= ' <span>' . esc_html( sprintf( __( 'Your value %s rests while the website setting applies.', 'course-captain' ), self::display( $row['override'] ) ) ) . '</span>';
				$html .= ' <label><input type="radio" name="ccap_settings[mode][' . esc_attr( $key ) . ']" value="inherit"> ' . esc_html__( 'Remove resting value', 'course-captain' ) . '</label>';
			}
			return $html . '</p></fieldset>';
		}
		$html .= '<p id="' . esc_attr( $id ) . '-state">' . esc_html(
			$row['overridden']
				/* translators: %s: default value */
				? sprintf( __( 'Set for this course. The default would be: %s', 'course-captain' ), self::display( $row['default'] ) )
				/* translators: %s: inherited value */
				: sprintf( __( 'Inherits from the website: %s', 'course-captain' ), self::display( $row['default'] ) )
		) . ( $row['overridden'] && $row['override'] === $row['default'] ? ' ' . esc_html__( '(currently the same as the default)', 'course-captain' ) : '' ) . ' <span class="description">(' . esc_html( (string) $row['applies'] ) . ')</span></p>';
		$html .= '<label><input type="radio" name="ccap_settings[mode][' . esc_attr( $key ) . ']" value="inherit"' . checked( $row['overridden'], false, false ) . '> ' . esc_html__( 'Use the default', 'course-captain' ) . '</label><br>';
		$html .= '<label><input type="radio" name="ccap_settings[mode][' . esc_attr( $key ) . ']" value="set"' . checked( $row['overridden'], true, false ) . '> ' . esc_html__( 'Set for this course', 'course-captain' ) . '</label>';
		$value = $row['overridden'] ? $row['override'] : $row['default'];
		if ( 'object' === $field['type'] ) {
			$value = array_replace( (array) $row['default'], (array) $value );
			foreach ( (array) $field['fields'] as $leaf => $def ) {
				$html .= '<br>' . self::input( $id . '-' . $leaf, 'ccap_settings[value][' . $key . '][' . $leaf . ']', (array) $def, $value[ $leaf ] ?? null, self::leaf_label( (string) $leaf ) );
			}
		} else {
			$html .= '<br>' . self::input( $id . '-value', 'ccap_settings[value][' . $key . ']', $field, $value, __( 'Value for this course', 'course-captain' ) );
		}
		return $html . '</fieldset>';
	}

	/**
	 * Input for a field type.
	 *
	 * @param string               $id    Element ID.
	 * @param string               $name  Input name.
	 * @param array<string, mixed> $def   Field definition.
	 * @param mixed                $value Current value.
	 * @param string               $label Label.
	 * @return string
	 */
	private static function input( string $id, string $name, array $def, $value, string $label ): string {
		$html = '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label> ';
		switch ( $def['type'] ) {
			case 'bool':
				return $html . '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="1"' . selected( true === $value, true, false ) . '>' . esc_html__( 'Yes', 'course-captain' ) . '</option><option value="0"' . selected( true === $value, false, false ) . '>' . esc_html__( 'No', 'course-captain' ) . '</option></select>';
			case 'enum':
				$html .= '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( (array) $def['values'] as $option ) {
					$html .= '<option value="' . esc_attr( (string) $option ) . '"' . selected( $value, $option, false ) . '>' . esc_html( (string) $option ) . '</option>';
				}
				return $html . '</select>';
			case 'int':
				$html .= '<input id="' . esc_attr( $id ) . '" type="number" step="1" name="' . esc_attr( $name ) . '" value="' . esc_attr( null === $value ? '' : (string) $value ) . '"' . ( isset( $def['min'] ) ? ' min="' . (int) $def['min'] . '"' : '' ) . ( isset( $def['max'] ) ? ' max="' . (int) $def['max'] . '"' : '' ) . '>';
				return $html . ( empty( $def['nullable'] ) ? '' : ' <span class="description">' . esc_html__( 'Leave empty for no limit.', 'course-captain' ) . '</span>' );
			case 'text':
				return $html . '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="2" class="widefat">' . esc_textarea( (string) $value ) . '</textarea>';
		}
		return $html . '<input id="' . esc_attr( $id ) . '" type="text" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '">';
	}

	/**
	 * Panel "Effective settings" (course and its exam rules).
	 *
	 * @param int $course Course.
	 * @return string
	 */
	private static function effective_html( int $course ): string {
		$html = '<h3>' . esc_html__( 'Effective settings', 'course-captain' ) . '</h3><table class="widefat striped"><caption>' . esc_html__( 'Course', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Setting', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Value in the draft', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Value for learners now', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Source', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'When it applies', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( EffectiveSettings::course_panel( $course ) as $row ) {
			$html .= '<tr><th scope="row">' . esc_html( (string) $row['label'] ) . '</th><td>' . esc_html( self::display( $row['value'] ) ) . '</td><td>' . esc_html( null === $row['published'] && 'K' !== $row['class'] ? __( 'not published yet', 'course-captain' ) : self::display( $row['published'] ) ) . '</td><td>' . esc_html( EffectiveSettings::source_label( (string) $row['source'] ) ) . '</td><td>' . esc_html( (string) $row['applies'] ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		$db    = Database::current();
		$rules = ExamRules::current();
		foreach ( $db->rows( $db->prepare( 'SELECT id, version, status FROM `' . $db->table( 'ccap_exam_rule' ) . "` WHERE course_id = %d AND status IN ('draft', 'published') ORDER BY id", $course ) ) as $rule ) {
			/* translators: 1: exam version, 2: status */
			$html .= '<table class="widefat striped"><caption>' . esc_html( sprintf( __( 'Exam %1$s (%2$s)', 'course-captain' ), (string) $rule['version'], 'draft' === $rule['status'] ? __( 'draft', 'course-captain' ) : __( 'published', 'course-captain' ) ) ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Setting', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Value', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Source', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'When it applies', 'course-captain' ) . '</th></tr></thead><tbody>';
			foreach ( $rules->panel( (int) $rule['id'] ) as $row ) {
				$html .= '<tr><th scope="row">' . esc_html( $row['label'] ) . '</th><td>' . esc_html( self::display( $row['value'] ) ) . ( $row['resting'] ? ' <span>' . esc_html__( '(own value rests)', 'course-captain' ) . '</span>' : '' ) . '</td><td>' . esc_html( EffectiveSettings::source_label( $row['source'] ) ) . '</td><td>' . esc_html( $row['applies'] ) . '</td></tr>';
			}
			$html .= '</tbody></table>';
		}
		return $html;
	}

	/**
	 * Value as text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function display( $value ): string {
		if ( null === $value ) {
			return __( 'none (no limit)', 'course-captain' );
		}
		if ( is_bool( $value ) ) {
			return $value ? __( 'yes', 'course-captain' ) : __( 'no', 'course-captain' );
		}
		if ( is_array( $value ) ) {
			$parts = array();
			foreach ( $value as $k => $v ) {
				$parts[] = self::leaf_label( (string) $k ) . ': ' . ( is_array( $v ) ? '…' : self::display( $v ) );
			}
			return implode( '; ', $parts );
		}
		return '' === (string) $value ? __( '(empty)', 'course-captain' ) : (string) $value;
	}

	/**
	 * Label of an object sub-key.
	 *
	 * @param string $leaf Sub-key.
	 * @return string
	 */
	private static function leaf_label( string $leaf ): string {
		$labels = array(
			'visibility'                => __( 'Show feedback', 'course-captain' ),
			'show_correct_answers'      => __( 'Show correct answers', 'course-captain' ),
			'show_score'                => __( 'Show score', 'course-captain' ),
			'show_correct_answers_when' => __( 'Show correct answers when', 'course-captain' ),
			'issuer_name'               => __( 'Issuer', 'course-captain' ),
			'footer_text'               => __( 'Footer text', 'course-captain' ),
			'logo_id'                   => __( 'Logo (media ID)', 'course-captain' ),
			'signature_label'           => __( 'Signature line', 'course-captain' ),
		);
		return $labels[ $leaf ] ?? $leaf;
	}

	/**
	 * Labels of keys.
	 *
	 * @param array $keys Keys.
	 * @phpstan-param list<string> $keys
	 * @return list<string>
	 */
	private static function names( array $keys ): array {
		$labels = EffectiveSettings::labels();
		return array_map( static fn( string $k ): string => $labels[ $k ] ?? $k, $keys );
	}
}
