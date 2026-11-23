<?php
/**
 * Admin screen A-13 "Import content", area "Packages" (R0-10, R0-38, R0-30 §1.2 Pos 9).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Import\ImportReport;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\RightsReleaseForm;
use CourseCaptain\Learner\LearnerRoutes;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Upload a ZIP package → the format check (V1–V6) and the dry run run
 * right away → the batch page shows what would happen (plan per entity
 * and action, object errors, report download as CSV/JSON) → "approve and
 * import" (or "discard"). Published batches can be rolled back with a
 * mandatory reason (a new batch from the kept copy, again with dry run).
 * Everything goes through {@see ImportService} (rights M-21, audit,
 * state machine); this screen only adds the forms. Page cap
 * `ccap_menu_import` ⇐ #6 ∨ #7 ∨ #8; the package area needs #6.
 */
final class ImportScreen {

	public const SLUG     = 'course-captain-import';
	public const ACTION   = 'ccap_import_admin';
	public const MENU_CAP = 'ccap_menu_import';
	public const CAP      = 'ccap_import_content';
	public const OPS      = array( 'upload', 'preview', 'approve', 'run', 'discard', 'rollback' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				add_submenu_page( EditorScreens::MENU, __( 'Import content', 'course-captain' ), _x( 'Import content', 'admin menu', 'course-captain' ), self::MENU_CAP, self::SLUG, array( self::class, 'render' ), 9 );
			},
			21
		);
		add_filter( 'map_meta_cap', array( self::class, 'map_meta_cap' ), 10, 3 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * `ccap_menu_import` ⇐ `ccap_import_content` ∨ `ccap_export_content` ∨ `ccap_release_rights`.
	 *
	 * @param array<int, string> $caps    Primitive caps.
	 * @param string             $cap     Requested cap.
	 * @param int                $user_id User.
	 * @return array<int, string>
	 */
	public static function map_meta_cap( $caps, $cap, $user_id ) {
		if ( self::MENU_CAP !== $cap ) {
			return $caps;
		}
		foreach ( array( self::CAP, 'ccap_export_content', 'ccap_release_rights' ) as $candidate ) {
			if ( user_can( (int) $user_id, $candidate ) ) {
				return array( $candidate );
			}
		}
		return array( self::CAP );
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
		$file = '';
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in process().
		if ( isset( $_FILES['package']['tmp_name'] ) && is_string( $_FILES['package']['tmp_name'] ) && is_uploaded_file( $_FILES['package']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Temporary upload path, only checked.
			$file = (string) $_FILES['package']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Temporary upload path checked by is_uploaded_file().
		}
		$error = isset( $_FILES['package']['error'] ) ? (int) $_FILES['package']['error'] : UPLOAD_ERR_NO_FILE;
		$out   = self::process( wp_unslash( $_POST ), get_current_user_id(), $file, $error );
		// phpcs:enable
		$args = array(
			'page'        => self::SLUG,
			'ccap_result' => $out['result'],
		);
		if ( $out['batch'] > 0 ) {
			$args['batch'] = $out['batch'];
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Testable core of the handler.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @param int                  $user_id Importer.
	 * @param string               $file    Uploaded ZIP (upload only).
	 * @param int                  $error   PHP upload error code.
	 * @return array{op: string, batch: int, result: string}
	 */
	public static function process( array $post, int $user_id, string $file = '', int $error = UPLOAD_ERR_OK ): array {
		$op    = sanitize_key( (string) ( $post['op'] ?? '' ) );
		$batch = absint( $post['object'] ?? 0 );
		$out   = array(
			'op'     => $op,
			'batch'  => $batch,
			'result' => 'ok',
		);
		if ( ! in_array( $op, self::OPS, true ) ) {
			$out['result'] = 'ccap_not_found';
			return $out;
		}
		if ( ! AdminUi::verify( self::ACTION, $post ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		$actor   = Actor::user( $user_id );
		$service = ImportService::current();
		$partial = ! empty( $post['partial'] );
		try {
			switch ( $op ) {
				case 'upload':
					if ( UPLOAD_ERR_OK !== $error || '' === $file ) {
						$out['result'] = in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ? 'ccap_upload_too_large' : 'ccap_upload_missing';
						break;
					}
					if ( 'PK' !== (string) file_get_contents( $file, false, null, 0, 2 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Two magic bytes of a local temp file.
						$out['result'] = 'ccap_upload_not_zip';
						break;
					}
					$out['batch']  = $service->upload( $actor, $file );
					$out['result'] = self::check_and_preview( $actor, $out['batch'], $partial );
					break;
				case 'preview':
					$out['result'] = self::check_and_preview( $actor, $batch, $partial );
					break;
				case 'approve':
				case 'run':
					if ( 'approve' === $op ) {
						$service->approve( $actor, $batch );
					}
					if ( ! user_can( $user_id, 'ccap_publish_courses' ) ) {
						$out['result'] = 'approved_only';
						break;
					}
					$service->run( $actor, $batch );
					$row           = (array) $service->get( $batch );
					$out['result'] = 'published' === ( $row['status'] ?? '' ) ? 'imported' : 'import_' . sanitize_key( (string) ( $row['status'] ?? 'unknown' ) );
					break;
				case 'discard':
					if ( empty( $post['confirm'] ) ) {
						$out['result'] = 'ccap_confirmation_required';
						break;
					}
					$service->discard( $actor, $batch );
					break;
				case 'rollback':
					$new           = $service->rollback( $actor, $batch, (string) ( $post['reason'] ?? '' ) );
					$out['batch']  = $new;
					$out['result'] = self::check_and_preview( $actor, $new, false );
					break;
			}
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
		}
		return $out;
	}

	/**
	 * Format check and dry run of an uploaded batch.
	 *
	 * @param Actor $actor   Importer.
	 * @param int   $batch   Batch.
	 * @param bool  $partial Partial package.
	 * @return string Result code.
	 */
	private static function check_and_preview( Actor $actor, int $batch, bool $partial ): string {
		$service = ImportService::current();
		$row     = (array) $service->get( $batch );
		if ( 'uploaded' === ( $row['status'] ?? '' ) && 'validated' !== $service->validate( $actor, $batch )['status'] ) {
			return 'invalid_package';
		}
		return 'previewed' === $service->preview( $actor, $batch, $partial )['status'] ? 'previewed' : 'preview_failed';
	}

	/**
	 * Page HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 */
	public static function html( array $request, int $user_id ): string {
		$html = '<div class="wrap"><h1>' . esc_html__( 'Import content', 'course-captain' ) . '</h1>';
		if ( ! user_can( $user_id, self::CAP ) ) {
			return $html . '<p>' . esc_html__( 'You are not allowed to import packages.', 'course-captain' ) . '</p></div>';
		}
		$html .= self::notice( sanitize_key( (string) ( $request['ccap_result'] ?? '' ) ) );
		$batch = absint( $request['batch'] ?? 0 );
		return $html . ( $batch > 0 ? self::detail( $batch, $user_id ) : self::listing() ) . '</div>';
	}

	/**
	 * Result notice.
	 *
	 * @param string $code Result.
	 * @return string
	 */
	private static function notice( string $code ): string {
		$texts = array(
			'previewed'                  => array( 'info', __( 'Test run finished. Nothing has been imported yet: check the result below, then approve or discard.', 'course-captain' ) ),
			'imported'                   => array( 'success', __( 'Imported. The new content is in place; courses from the package start as drafts unless they were published before.', 'course-captain' ) ),
			'approved_only'              => array( 'warning', __( 'Approved. Someone who may publish courses must now start the import.', 'course-captain' ) ),
			'invalid_package'            => array( 'error', __( 'The file is not a valid course package. The errors are listed below.', 'course-captain' ) ),
			'preview_failed'             => array( 'error', __( 'The test run found too many errors. Nothing was imported. Download the report for details.', 'course-captain' ) ),
			'ccap_upload_missing'        => array( 'error', __( 'Choose a package file (ZIP) first.', 'course-captain' ) ),
			'ccap_upload_too_large'      => array( 'error', __( 'The file is larger than this site allows for uploads. Ask the administrator or use the command line import.', 'course-captain' ) ),
			'ccap_upload_not_zip'        => array( 'error', __( 'The file is not a ZIP package.', 'course-captain' ) ),
			'ccap_import_state'          => array( 'error', __( 'This step is not possible in the current state of the import. Reload the page.', 'course-captain' ) ),
			'ccap_import_locked'         => array( 'error', __( 'Another import is running. Try again in a few minutes.', 'course-captain' ) ),
			'ccap_confirmation_required' => array( 'error', __( 'Please confirm the action (tick the confirmation box).', 'course-captain' ) ),
			'ccap_storage_unavailable'   => array( 'error', __( 'The package could not be stored. Check the protected folders under System status.', 'course-captain' ) ),
		);
		if ( isset( $texts[ $code ] ) ) {
			return '<div class="notice notice-' . esc_attr( $texts[ $code ][0] ) . '" role="' . ( 'error' === $texts[ $code ][0] ? 'alert' : 'status' ) . '"><p>' . esc_html( $texts[ $code ][1] ) . '</p></div>';
		}
		if ( str_starts_with( $code, 'import_' ) ) {
			return '<div class="notice notice-error" role="alert"><p>' . esc_html__( 'The import did not finish. The batch page shows the state; you can resume or discard it.', 'course-captain' ) . '</p></div>';
		}
		return AdminUi::notice( $code, array( 'ccap_validation_failed' => __( 'Not done: a reason is required.', 'course-captain' ) ), __( 'Done.', 'course-captain' ) );
	}

	/**
	 * Batch list and upload form.
	 *
	 * @return string
	 */
	private static function listing(): string {
		$db      = Database::current();
		$rows    = $db->rows( 'SELECT id, package_id, status, created_at, created_by FROM `' . $db->table( 'ccap_import_batch' ) . '` ORDER BY id DESC LIMIT 50' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No input.
		$fields  = '<p><label for="ccap-imp-file">' . esc_html__( 'Course package (ZIP file)', 'course-captain' ) . '</label><br><input id="ccap-imp-file" type="file" name="package" accept=".zip,application/zip" required aria-describedby="ccap-imp-help"></p>';
		$fields .= '<p id="ccap-imp-help" class="description">' . esc_html__( 'The package is checked and a test run shows what would change. Nothing is imported before you approve.', 'course-captain' ) . ' ' . esc_html( sprintf( /* translators: %s: size */ __( 'Largest file this site accepts: %s.', 'course-captain' ), size_format( wp_max_upload_size() ) ) ) . '</p>';
		$fields .= '<p><label><input type="checkbox" name="partial" value="1"> ' . esc_html__( 'Partial package: keep content that is missing in the package (do not archive it)', 'course-captain' ) . '</label></p>';
		$html    = '<h2>' . esc_html__( 'Import a package', 'course-captain' ) . '</h2>' . AdminUi::form( self::ACTION, 'upload', 0, $fields, __( 'Upload and test', 'course-captain' ), '1', ' enctype="multipart/form-data"' );
		$html   .= '<h2>' . esc_html__( 'Imports', 'course-captain' ) . '</h2><table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Imports', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Import', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Started (UTC)', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'By', 'course-captain' ) . '</th></tr></thead><tbody>';
		if ( array() === $rows ) {
			$html .= '<tr><td colspan="4">' . esc_html__( 'No imports yet. A course package is a ZIP file with a manifest and data files in the Course Captain package format.', 'course-captain' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$user  = get_userdata( (int) $row['created_by'] );
			$url   = add_query_arg(
				array(
					'page'  => self::SLUG,
					'batch' => (int) $row['id'],
				),
				admin_url( 'admin.php' )
			);
			$html .= '<tr><th scope="row"><a href="' . esc_url( $url ) . '">' . esc_html( sprintf( /* translators: 1: import number, 2: package */ __( 'Import %1$d (%2$s)', 'course-captain' ), (int) $row['id'], (string) $row['package_id'] ) ) . '</a></th><td>' . esc_html( self::status_label( (string) $row['status'] ) ) . '</td><td>' . esc_html( (string) $row['created_at'] ) . '</td><td>' . esc_html( $user instanceof \WP_User ? $user->display_name : '' ) . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Batch detail: state, errors, dry-run result and the next steps.
	 *
	 * @param int $batch   Batch.
	 * @param int $user_id Viewer.
	 * @return string
	 */
	private static function detail( int $batch, int $user_id ): string {
		$row  = ImportService::current()->get( $batch );
		$back = '<p><a href="' . esc_url( add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Back to the imports', 'course-captain' ) . '</a></p>';
		if ( null === $row ) {
			return $back . '<p>' . esc_html__( 'This import does not exist.', 'course-captain' ) . '</p>';
		}
		$status = (string) $row['status'];
		$stats  = (array) json_decode( (string) $row['stats'], true );
		$html   = $back . '<h2>' . esc_html( sprintf( /* translators: 1: import number, 2: package */ __( 'Import %1$d (%2$s)', 'course-captain' ), $batch, (string) $row['package_id'] ) ) . '</h2>';
		$html  .= '<p><strong>' . esc_html( sprintf( /* translators: %s: status */ __( 'Status: %s', 'course-captain' ), self::status_label( $status ) ) ) . '</strong></p>';
		if ( isset( $stats['rollback_of'] ) ) {
			$html .= '<p>' . esc_html( sprintf( /* translators: 1: earlier import, 2: reason */ __( 'Rollback to import %1$d. Reason: %2$s', 'course-captain' ), (int) $stats['rollback_of'], (string) ( $stats['reason'] ?? '' ) ) ) . '</p>';
		}
		$errors = json_decode( (string) ( $row['error'] ?? '' ), true );
		if ( 'failed' === $status && is_array( $errors ) && array() !== $errors ) {
			$html .= '<h3>' . esc_html__( 'Errors in the package', 'course-captain' ) . '</h3><ul>';
			foreach ( array_slice( $errors, 0, 50 ) as $error ) {
				$html .= '<li>' . esc_html( (string) ( $error['rule'] ?? '' ) . ( '' !== (string) ( $error['path'] ?? '' ) ? ': ' . (string) $error['path'] : '' ) ) . '</li>';
			}
			$html .= '</ul>';
		}
		$plan = (array) ( $stats['plan'] ?? array() );
		if ( array() !== $plan ) {
			$labels = array(
				'create'    => __( 'new', 'course-captain' ),
				'update'    => __( 'changed', 'course-captain' ),
				'unchanged' => __( 'unchanged', 'course-captain' ),
				'archive'   => __( 'archived', 'course-captain' ),
				'conflict'  => __( 'conflict', 'course-captain' ),
				'error'     => __( 'error', 'course-captain' ),
			);
			$html  .= '<h3>' . esc_html__( 'Result of the test run (what the import would do)', 'course-captain' ) . '</h3><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Content', 'course-captain' ) . '</th>';
			foreach ( $labels as $label ) {
				$html .= '<th scope="col">' . esc_html( $label ) . '</th>';
			}
			$html .= '</tr></thead><tbody>';
			foreach ( $plan as $entity => $actions ) {
				$html .= '<tr><th scope="row">' . esc_html( self::entity_label( (string) $entity ) ) . '</th>';
				foreach ( array_keys( $labels ) as $action ) {
					$html .= '<td>' . (int) ( $actions[ $action ] ?? 0 ) . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
			$dry   = (array) ( $stats['dry_run'] ?? array() );
			if ( isset( $dry['object_errors'] ) ) {
				$html .= '<p>' . esc_html( sprintf( /* translators: 1: errors, 2: records */ __( '%1$d of %2$d entries have errors and would be skipped.', 'course-captain' ), (int) $dry['object_errors'], (int) ( $dry['items'] ?? 0 ) ) ) . '</p>';
			}
		}
		$summary = ImportReport::summary( Database::current(), $row );
		$top     = (array) $summary['top_rules'];
		if ( array() !== $top ) {
			$html .= '<h3>' . esc_html__( 'Most frequent problems', 'course-captain' ) . '</h3><ul>';
			foreach ( $top as $rule => $count ) {
				$html .= '<li>' . esc_html( sprintf( /* translators: 1: rule, 2: count */ __( '%1$s: %2$d times', 'course-captain' ), (string) $rule, (int) $count ) ) . '</li>';
			}
			$html .= '</ul>';
		}
		if ( array() !== $top || array() !== $plan ) {
			$base  = rest_url( LearnerRoutes::NAMESPACE . '/imports/' . $batch . '/report' );
			$nonce = wp_create_nonce( 'wp_rest' );
			$html .= '<p>' . esc_html__( 'Error report:', 'course-captain' ) . ' <a href="' . esc_url( add_query_arg( array( 'format' => 'csv', 'per_page' => ImportReport::EXPORT_BLOCK, '_wpnonce' => $nonce ), $base ) ) . '">' . esc_html__( 'Download as CSV (spreadsheet)', 'course-captain' ) . '</a> · <a href="' . esc_url( add_query_arg( array( 'format' => 'json', 'per_page' => ImportReport::EXPORT_BLOCK, '_wpnonce' => $nonce ), $base ) ) . '">' . esc_html__( 'Download as JSON', 'course-captain' ) . '</a></p>'; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
		}
		return $html . self::actions( $batch, $status, $user_id );
	}

	/**
	 * Next steps for the state of a batch.
	 *
	 * @param int    $batch   Batch.
	 * @param string $status  Status.
	 * @param int    $user_id Viewer.
	 * @return string
	 */
	private static function actions( int $batch, string $status, int $user_id ): string {
		$html    = '<h3>' . esc_html__( 'Next step', 'course-captain' ) . '</h3>';
		$discard = AdminUi::form( self::ACTION, 'discard', $batch, '<p><label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Confirm: discard this import (nothing of it stays)', 'course-captain' ) . '</label></p>', __( 'Discard', 'course-captain' ) );
		switch ( $status ) {
			case 'validated':
				return $html . AdminUi::form( self::ACTION, 'preview', $batch, '<p><label><input type="checkbox" name="partial" value="1"> ' . esc_html__( 'Partial package', 'course-captain' ) . '</label></p>', __( 'Start test run', 'course-captain' ), '1' ) . $discard;
			case 'previewed':
				$can_run = user_can( $user_id, 'ccap_publish_courses' );
				return $html . AdminUi::form( self::ACTION, 'approve', $batch, '<p class="description">' . esc_html__( 'Approving confirms that you checked the test run and may use the content (rights). The import then runs at once.', 'course-captain' ) . '</p>', $can_run ? __( 'Approve and import', 'course-captain' ) : __( 'Approve', 'course-captain' ), '1' ) . $discard;
			case 'approved':
				return $html . ( user_can( $user_id, 'ccap_publish_courses' ) ? AdminUi::form( self::ACTION, 'run', $batch, '', __( 'Import now', 'course-captain' ), '1' ) : '<p>' . esc_html__( 'Someone who may publish courses must start the import.', 'course-captain' ) . '</p>' ) . $discard;
			case 'uploaded':
			case 'failed':
			case 'aborted':
				return $html . $discard;
			case 'published':
			case 'superseded':
				$fields  = '<p><label for="ccap-imp-reason">' . esc_html__( 'Reason (required, recorded)', 'course-captain' ) . '</label><br><input id="ccap-imp-reason" type="text" class="large-text" name="reason" required></p>';
				$fields .= '<p class="description">' . esc_html__( 'A rollback imports the kept copy of this package again as a new import, with its own test run and approval.', 'course-captain' ) . '</p>';
				$html   .= AdminUi::form( self::ACTION, 'rollback', $batch, $fields, __( 'Roll back to this import', 'course-captain' ) );
				if ( user_can( $user_id, 'ccap_release_rights' ) ) {
					$html .= '<h3>' . esc_html__( 'Release unclear rights', 'course-captain' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( RightsReleaseForm::ACTION ) . '"><input type="hidden" name="batch" value="' . (int) $batch . '"><input type="hidden" name="_ccap_return" value="' . esc_attr( add_query_arg( array( 'page' => self::SLUG, 'batch' => $batch ), admin_url( 'admin.php' ) ) ) . '">' . wp_nonce_field( RightsReleaseForm::ACTION, '_wpnonce', true, false ) . '<p><label for="ccap-rights-reason">' . esc_html__( 'Reason (required)', 'course-captain' ) . '</label><br><input id="ccap-rights-reason" type="text" class="large-text" name="reason"></p><p><button type="submit" class="button">' . esc_html__( 'Show what would be released', 'course-captain' ) . '</button></p></form>'; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
				}
				return $html;
			case 'importing':
			case 'activating':
				return $html . '<p>' . esc_html__( 'The import is running in the background. Reload the page to see the progress.', 'course-captain' ) . '</p>';
		}
		return $html . '<p>' . esc_html__( 'No further step is possible.', 'course-captain' ) . '</p>';
	}

	/**
	 * Entity label.
	 *
	 * @param string $entity Entity.
	 * @return string
	 */
	private static function entity_label( string $entity ): string {
		$labels = array(
			'course'    => __( 'Courses', 'course-captain' ),
			'module'    => __( 'Modules', 'course-captain' ),
			'lesson'    => __( 'Lessons', 'course-captain' ),
			'question'  => __( 'Questions', 'course-captain' ),
			'flashcard' => __( 'Flashcards', 'course-captain' ),
			'media'     => __( 'Media', 'course-captain' ),
			'exam_rule' => __( 'Exams', 'course-captain' ),
		);
		return $labels[ $entity ] ?? $entity;
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'uploaded'   => __( 'uploaded, not checked', 'course-captain' ),
			'validated'  => __( 'checked, test run missing', 'course-captain' ),
			'failed'     => __( 'failed', 'course-captain' ),
			'previewed'  => __( 'test run done, waiting for approval', 'course-captain' ),
			'approved'   => __( 'approved, waiting for import', 'course-captain' ),
			'importing'  => __( 'importing', 'course-captain' ),
			'activating' => __( 'activating', 'course-captain' ),
			'published'  => __( 'imported', 'course-captain' ),
			'superseded' => __( 'replaced by a newer import', 'course-captain' ),
			'discarded'  => __( 'discarded', 'course-captain' ),
			'aborted'    => __( 'stopped', 'course-captain' ),
		);
		return $labels[ $status ] ?? $status;
	}
}
