<?php
/**
 * Admin screen A-13, area "Rights & release" per course (UI-F8, R0-10 §2, R0-22 VAL-100…105).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Import\Rights;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Per course: the rights metadata of the course (post meta `_ccap_rights`;
 * without it the origin of an imported course from its package), the
 * release status of its content (questions and flashcards of the module
 * pools per rights status, own or imported, how many are hidden from
 * learners) and the actions "release unclear rights of this course"
 * (preview count + reason) and "change the rights status of one item".
 * Everything goes through {@see Rights} (reasons, audit, overrides that
 * survive re-imports). Cap `ccap_release_rights` plus edit rights on the
 * course; the single-item change also needs `ccap_import_content`.
 */
final class RightsArea {

	public const ACTION = 'ccap_course_rights';
	public const CAP    = 'ccap_release_rights';
	public const OPS    = array( 'save_meta', 'release', 'override' );

	/**
	 * Admin-post entry point.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$out  = self::process( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process().
		$args = array(
			'page'        => ImportScreen::SLUG,
			'area'        => 'rights',
			'course'      => $out['course'],
			'ccap_result' => $out['result'],
		);
		if ( $out['count'] >= 0 ) {
			$args['count'] = $out['count'];
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Testable core.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @param int                  $user_id Actor.
	 * @return array{op: string, course: int, result: string, count: int, fields: array<string, string>}
	 * @throws CourseException Never; domain errors become the result code.
	 */
	public static function process( array $post, int $user_id ): array {
		$op     = sanitize_key( (string) ( $post['op'] ?? '' ) );
		$course = absint( $post['object'] ?? 0 );
		$out    = array(
			'op'     => $op,
			'course' => $course,
			'result' => 'ok',
			'count'  => -1,
			'fields' => array(),
		);
		if ( ! in_array( $op, self::OPS, true ) ) {
			$out['result'] = 'ccap_not_found';
			return $out;
		}
		if ( ! AdminUi::verify( self::ACTION, $post ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		if ( ! user_can( $user_id, self::CAP ) || ! user_can( $user_id, 'edit_post', $course ) ) {
			$out['result'] = 'ccap_forbidden';
			return $out;
		}
		$actor  = Actor::user( $user_id );
		$rights = Rights::current();
		try {
			switch ( $op ) {
				case 'save_meta':
					$out['fields'] = $rights->set_course_meta( $actor, $course, (array) ( $post['rights'] ?? array() ), (string) ( $post['reason'] ?? '' ) );
					if ( array() !== $out['fields'] ) {
						$out['result'] = 'ccap_validation_failed';
						set_transient( 'ccap_rights_form_' . $user_id, array( $post, $out['fields'] ), 600 );
					}
					break;
				case 'release':
					$done          = $rights->course_release( $actor, $course, (string) ( $post['reason'] ?? '' ), isset( $post['count'] ) && '' !== (string) $post['count'] ? (int) $post['count'] : null );
					$out['result'] = 'released';
					$out['count']  = $done['released'];
					break;
				case 'override':
					$item = absint( $post['item'] ?? 0 );
					$type = 'flashcard' === ( $post['type'] ?? '' ) ? 'flashcard' : 'question';
					if ( ! in_array( $item, array_column( $rights->course_items( $course ), 'object_id' ), true ) ) {
						throw new CourseException( 'ccap_not_found', 'item', 404 );
					}
					$rights->override( $actor, $type, $item, sanitize_key( (string) ( $post['status'] ?? '' ) ), (string) ( $post['reason'] ?? '' ) );
					break;
			}
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
			if ( 'ccap_confirmation_required' === $e->error_code ) {
				$out['count'] = (int) ( $e->data['count'] ?? 0 );
			}
		}
		return $out;
	}

	/**
	 * Status labels.
	 *
	 * @return array<string, string>
	 */
	public static function status_labels(): array {
		return array(
			'own'        => __( 'own content', 'course-captain' ),
			'licensed'   => __( 'licensed', 'course-captain' ),
			'public'     => __( 'public domain or free licence', 'course-captain' ),
			'review'     => __( 'rights being checked', 'course-captain' ),
			'unknown'    => __( 'rights unclear', 'course-captain' ),
			'restricted' => __( 'must not be shown', 'course-captain' ),
		);
	}

	/**
	 * Area HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 */
	public static function html( array $request, int $user_id ): string {
		$course = absint( $request['course'] ?? 0 );
		$code   = sanitize_key( (string) ( $request['ccap_result'] ?? '' ) );
		$count  = absint( $request['count'] ?? 0 );
		$html   = '';
		if ( 'released' === $code ) {
			/* translators: %d: number of content items */
			$html .= '<div class="notice notice-success" role="status"><p>' . esc_html( sprintf( _n( '%d item released.', '%d items released.', $count, 'course-captain' ), $count ) ) . '</p></div>';
		} else {
			$html .= AdminUi::notice(
				$code,
				array(
					'ccap_confirmation_required' => __( 'Not released: the number of items changed or was not confirmed. Check the new number and confirm again.', 'course-captain' ),
					'ccap_validation_failed'     => __( 'Not saved: please correct the marked entries. A reason is always required.', 'course-captain' ),
				),
				__( 'Saved.', 'course-captain' )
			);
		}
		$html .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( ImportScreen::SLUG ) . '"><input type="hidden" name="area" value="rights"><p><label for="ccap-rights-course">' . esc_html__( 'Course', 'course-captain' ) . '</label> ' . AdminUi::course_select( 'ccap-rights-course', 'course', $course, $user_id, __( '— choose a course —', 'course-captain' ) ) . ' <button type="submit" class="button">' . esc_html__( 'Show', 'course-captain' ) . '</button></p></form>';
		if ( $course <= 0 ) {
			return $html . '<p>' . esc_html__( 'Choose a course to see and edit its rights and release status.', 'course-captain' ) . '</p>';
		}
		if ( ! user_can( $user_id, 'edit_post', $course ) || ! get_post( $course ) instanceof \WP_Post ) {
			return $html . '<p>' . esc_html__( 'This course does not exist or you may not edit it.', 'course-captain' ) . '</p>';
		}
		return $html . self::meta_section( $course, $user_id ) . self::status_section( $course, $user_id, $code, $count );
	}

	/**
	 * Rights metadata of the course (form).
	 *
	 * @param int $course  Course.
	 * @param int $user_id Viewer.
	 * @return string
	 */
	private static function meta_section( int $course, int $user_id ): string {
		$meta   = Rights::course_meta( $course );
		$kept   = get_transient( 'ccap_rights_form_' . $user_id );
		$errors = array();
		if ( is_array( $kept ) && absint( $kept[0]['object'] ?? 0 ) === $course ) {
			delete_transient( 'ccap_rights_form_' . $user_id );
			$meta   = array_map( 'strval', (array) ( $kept[0]['rights'] ?? array() ) );
			$errors = (array) $kept[1];
		}
		$html = '<h2>' . esc_html( sprintf( /* translators: %s: course title */ __( 'Rights of the course "%s"', 'course-captain' ), (string) get_the_title( $course ) ) ) . '</h2>';
		if ( array() === Rights::course_meta( $course ) ) {
			$origin = Rights::current()->package_rights( $course );
			if ( null === $origin ) {
				$html .= '<p>' . esc_html__( 'No rights information is stored for this course yet. Content created on this site counts as own content.', 'course-captain' ) . '</p>';
			} else {
				$html .= '<p>' . esc_html( sprintf( /* translators: 1: source name, 2: publisher */ __( 'Imported course. Source: %1$s; publisher: %2$s. No rights information is stored for the course itself; the values below come from the package defaults where present.', 'course-captain' ), $origin['system'], '' === $origin['publisher'] ? '–' : $origin['publisher'] ) ) . '</p>';
				if ( array() === $meta ) {
					$meta = array_map( 'strval', array_filter( array_intersect_key( $origin['default_rights'], array_flip( Rights::COURSE_FIELDS ) ), 'is_scalar' ) );
				}
			}
		}
		$messages           = array(
			'status' => __( 'Choose a rights status from the list.', 'course-captain' ),
			'url'    => __( 'Enter a complete address starting with https://.', 'course-captain' ),
			'date'   => __( 'Enter the date as YYYY-MM-DD.', 'course-captain' ),
		);
		$error              = static function ( string $field ) use ( $errors, $messages ): array {
			if ( ! isset( $errors[ $field ] ) ) {
				return array( '', '' );
			}
			$id = 'ccap-rights-' . $field . '-error';
			return array( ' aria-invalid="true" aria-describedby="' . $id . '"', '<br><span id="' . $id . '" class="ccap-field-error">' . esc_html( $messages[ (string) $errors[ $field ] ] ?? __( 'This field is required.', 'course-captain' ) ) . '</span>' );
		};
		list( $attr, $msg ) = $error( 'status' );
		$fields             = '<p><label for="ccap-rights-status">' . esc_html__( 'Rights status of the course', 'course-captain' ) . '</label><br><select id="ccap-rights-status" name="rights[status]"' . $attr . '><option value="">' . esc_html__( '— not set —', 'course-captain' ) . '</option>';
		foreach ( self::status_labels() as $key => $label ) {
			$fields .= '<option value="' . esc_attr( $key ) . '"' . selected( (string) ( $meta['status'] ?? '' ), $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$fields .= '</select>' . $msg . '</p>';
		foreach ( array(
			'owner'       => array( __( 'Rights holder', 'course-captain' ), 'text' ),
			'license'     => array( __( 'Licence (name)', 'course-captain' ), 'text' ),
			'license_url' => array( __( 'Address of the licence text', 'course-captain' ), 'url' ),
			'attribution' => array( __( 'Attribution to show', 'course-captain' ), 'text' ),
			'source_url'  => array( __( 'Address of the source', 'course-captain' ), 'url' ),
			'valid_until' => array( __( 'Licence valid until (YYYY-MM-DD, optional)', 'course-captain' ), 'date' ),
		) as $field => $spec ) {
			list( $attr, $msg ) = $error( $field );
			$fields            .= '<p><label for="ccap-rights-' . esc_attr( $field ) . '">' . esc_html( $spec[0] ) . '</label><br><input id="ccap-rights-' . esc_attr( $field ) . '" type="' . esc_attr( $spec[1] ) . '" class="regular-text" name="rights[' . esc_attr( $field ) . ']" value="' . esc_attr( (string) ( $meta[ $field ] ?? '' ) ) . '"' . $attr . '>' . $msg . '</p>';
		}
		$fields            .= '<p><label for="ccap-rights-note">' . esc_html__( 'Note (internal)', 'course-captain' ) . '</label><br><textarea id="ccap-rights-note" class="large-text" rows="2" name="rights[note]">' . esc_textarea( (string) ( $meta['note'] ?? '' ) ) . '</textarea></p>';
		list( $attr, $msg ) = $error( 'reason' );
		$fields            .= '<p><label for="ccap-rights-reason">' . esc_html__( 'Reason for the change (required, recorded)', 'course-captain' ) . '</label><br><input id="ccap-rights-reason" type="text" class="large-text" name="reason" required' . $attr . '>' . $msg . '</p>';
		return $html . AdminUi::form( self::ACTION, 'save_meta', $course, $fields, __( 'Save course rights', 'course-captain' ), '1' );
	}

	/**
	 * Release status of the course content and the release actions.
	 *
	 * @param int    $course  Course.
	 * @param int    $user_id Viewer.
	 * @param string $code    Result code.
	 * @param int    $count   Count from the redirect.
	 * @return string
	 */
	private static function status_section( int $course, int $user_id, string $code, int $count ): string {
		$rights  = Rights::current();
		$summary = $rights->course_summary( $course );
		$labels  = self::status_labels();
		$html    = '<h2>' . esc_html__( 'Release status of the content', 'course-captain' ) . '</h2>';
		$html   .= '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Questions and flashcards by rights status', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Rights status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Own content', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Imported content', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Shown to learners', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $summary['counts'] as $status => $row ) {
			$blocking = in_array( $status, Rights::BLOCKING, true );
			$html    .= '<tr><th scope="row">' . esc_html( $labels[ $status ] ?? $status ) . '</th><td>' . (int) $row['own'] . '</td><td>' . (int) $row['foreign'] . '</td><td>' . esc_html( $blocking ? __( 'own content only', 'course-captain' ) : __( 'yes', 'course-captain' ) ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		if ( $summary['blocked'] > 0 ) {
			/* translators: %d: number of content items */
			$html .= '<div class="notice notice-warning inline" role="status"><p>' . esc_html( sprintf( _n( '%d imported item has unclear or restricted rights and is not shown to learners.', '%d imported items have unclear or restricted rights and are not shown to learners.', $summary['blocked'], 'course-captain' ), $summary['blocked'] ) ) . '</p></div>';
		} elseif ( $summary['total'] > 0 ) {
			$html .= '<p role="status">' . esc_html__( 'All content of this course can be shown to learners.', 'course-captain' ) . '</p>';
		}
		if ( $summary['review'] > 0 ) {
			/* translators: %d: number of content items */
			$html .= '<p>' . esc_html( sprintf( _n( '%d item is still being checked for rights.', '%d items are still being checked for rights.', $summary['review'], 'course-captain' ), $summary['review'] ) ) . '</p>';
		}
		$preview = $rights->course_release_preview( Actor::user( $user_id ), $course );
		$html   .= '<h3>' . esc_html__( 'Release unclear rights of this course', 'course-captain' ) . '</h3>';
		if ( 0 === $preview['count'] ) {
			$html .= '<p>' . esc_html__( 'There are no imported items with unclear rights in this course.', 'course-captain' ) . '</p>';
		} else {
			$fields  = '<p>' . esc_html( sprintf( /* translators: %d: number of content items */ _n( '%d imported item with unclear rights will be marked as licensed and shown to learners.', '%d imported items with unclear rights will be marked as licensed and shown to learners.', $preview['count'], 'course-captain' ), $preview['count'] ) ) . '</p>';
			$fields .= '<p>' . esc_html( sprintf( /* translators: 1: number of restricted items, 2: number of archived items */ __( 'Not changed: %1$d restricted, %2$d archived.', 'course-captain' ), $preview['kept']['restricted'], $preview['kept']['archived'] ) ) . '</p>';
			$fields .= '<p><label for="ccap-release-count">' . esc_html__( 'Number of items to release (confirm the number above)', 'course-captain' ) . '</label><br><input id="ccap-release-count" type="number" min="0" step="1" name="count" required value="' . esc_attr( 'ccap_confirmation_required' === $code ? (string) $count : '' ) . '"></p>';
			$fields .= '<p><label for="ccap-release-reason">' . esc_html__( 'Reason (required, recorded)', 'course-captain' ) . '</label><br><input id="ccap-release-reason" type="text" class="large-text" name="reason" required></p>';
			$html   .= AdminUi::form( self::ACTION, 'release', $course, $fields, sprintf( /* translators: %d: number of content items */ _n( 'Release %d item', 'Release %d items', $preview['count'], 'course-captain' ), $preview['count'] ) );
		}
		if ( ! user_can( $user_id, 'ccap_import_content' ) ) {
			return $html;
		}
		$items = array_slice( array_values( array_filter( $rights->course_items( $course ), static fn( array $i ): bool => 'archived' !== $i['status'] && ( $i['foreign'] || ! in_array( $i['rights'], array( 'own', 'licensed', 'public' ), true ) ) ) ), 0, 50 );
		if ( array() === $items ) {
			return $html;
		}
		$html .= '<h3>' . esc_html__( 'Rights of single items', 'course-captain' ) . '</h3><p class="description">' . esc_html__( 'Imported items and items whose rights are not clear. A change stays in place when the package is imported again.', 'course-captain' ) . '</p><table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Rights of single items', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Item', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Rights status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Change', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $items as $item ) {
			$id     = $item['object_id'];
			$label  = self::item_label( $id );
			$fields = '<input type="hidden" name="item" value="' . (int) $id . '"><input type="hidden" name="type" value="' . esc_attr( $item['type'] ) . '"><p><label for="ccap-ovr-' . $id . '">' . esc_html( sprintf( /* translators: %s: item text */ __( 'New status of: %s', 'course-captain' ), $label ) ) . '</label><br><select id="ccap-ovr-' . $id . '" name="status">';
			foreach ( $labels as $key => $text ) {
				$fields .= '<option value="' . esc_attr( $key ) . '"' . selected( $item['rights'], $key, false ) . '>' . esc_html( $text ) . '</option>';
			}
			$fields .= '</select></p><p><label for="ccap-ovr-' . $id . '-reason">' . esc_html__( 'Reason (required)', 'course-captain' ) . '</label><br><input id="ccap-ovr-' . $id . '-reason" type="text" name="reason" required></p>';
			$html   .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( ( $labels[ $item['rights'] ] ?? $item['rights'] ) . ( $item['foreign'] ? ' (' . __( 'imported', 'course-captain' ) . ')' : '' ) ) . '</td><td>' . AdminUi::form( self::ACTION, 'override', $course, $fields, __( 'Change status', 'course-captain' ) ) . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Short text of a question or flashcard.
	 *
	 * @param int $question Question.
	 * @return string
	 */
	private static function item_label( int $question ): string {
		$db   = \CourseCaptain\Storage\Database::current();
		$text = (string) $db->scalar( $db->prepare( 'SELECT r.question_text FROM `' . $db->table( 'ccap_question' ) . '` q JOIN `' . $db->table( 'ccap_question_revision' ) . '` r ON r.id = q.current_revision_id WHERE q.id = %d', $question ) );
		$text = mb_substr( trim( wp_strip_all_tags( $text ) ), 0, 90 );
		/* translators: %d: question number */
		return '' === $text ? sprintf( __( 'Question %d', 'course-captain' ), $question ) : $text;
	}
}
