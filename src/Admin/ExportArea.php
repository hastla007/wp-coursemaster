<?php
/**
 * Admin screen A-13, area "Course export" (UI-F8, R0-10 §6.1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Choose courses and the package options, then download the
 * `cc-package` v1 built by {@see PackageExporter} (courses, modules,
 * lessons, pool questions; never learner data, grants or settings).
 * Plain admin-post form without JavaScript; the archive is built in the
 * private packages folder, streamed and deleted. Cap
 * `ccap_export_content` plus edit rights on every chosen course (M-04).
 */
final class ExportArea {

	public const ACTION = 'ccap_course_export';
	public const CAP    = 'ccap_export_content';

	/**
	 * Admin-post entry point: stream the package or redirect with an error.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$out = self::process( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process().
		if ( 'ok' !== $out['result'] || '' === $out['file'] ) {
			$args = array(
				'page'        => ImportScreen::SLUG,
				'area'        => 'export',
				'ccap_result' => $out['result'],
			);
			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
			exit;
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $out['name'] . '"' );
		header( 'Content-Length: ' . (string) filesize( $out['file'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $out['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams the private archive.
		wp_delete_file( $out['file'] );
		exit;
	}

	/**
	 * Testable core: build the package.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data: `courses[]`, `title`, `system`, `per_file`.
	 * @param int                  $user_id Exporter.
	 * @return array{result: string, file: string, name: string, counts: array<string, int>}
	 */
	public static function process( array $post, int $user_id ): array {
		$out = array(
			'result' => 'ok',
			'file'   => '',
			'name'   => '',
			'counts' => array(),
		);
		if ( 'export' !== sanitize_key( (string) ( $post['op'] ?? '' ) ) || ! AdminUi::verify( self::ACTION, $post ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		if ( ! user_can( $user_id, self::CAP ) ) {
			$out['result'] = 'ccap_forbidden';
			return $out;
		}
		$courses = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $post['courses'] ?? array() ) ) ) ) );
		if ( array() === $courses ) {
			$out['result'] = 'ccap_export_no_course';
			return $out;
		}
		$per_file = trim( (string) ( $post['per_file'] ?? '' ) );
		if ( '' !== $per_file && ( ! ctype_digit( $per_file ) || (int) $per_file < 1 || (int) $per_file > PackageExporter::RECORDS_PER_FILE ) ) {
			$out['result'] = 'ccap_invalid_param';
			return $out;
		}
		$system = sanitize_key( (string) ( $post['system'] ?? '' ) );
		$system = '' === $system ? self::default_system() : $system;
		$title  = trim( sanitize_text_field( (string) ( $post['title'] ?? '' ) ) );
		$title  = '' === $title ? implode( ', ', array_map( static fn( int $c ): string => (string) get_the_title( $c ), $courses ) ) : $title;
		$actor  = Actor::user( $user_id );
		try {
			$records       = PackageExporter::records( $actor, $courses );
			$out['file']   = PackageExporter::write( $records, $system, $title, null, '' === $per_file ? PackageExporter::RECORDS_PER_FILE : (int) $per_file, array(), true );
			$out['counts'] = array_map( 'count', $records );
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
			return $out;
		}
		$out['name'] = sanitize_file_name( sanitize_title( $title ) . '-' . gmdate( 'Y-m-d' ) . '.zip' );
		AuditLog::write(
			Database::current(),
			$actor,
			'course_exported',
			'course',
			(string) $courses[0],
			null,
			array(
				'courses' => $courses,
				'counts'  => $out['counts'],
				'system'  => $system,
			)
		);
		return $out;
	}

	/**
	 * Default `source.system` of an export: the site host as key.
	 *
	 * @return string
	 */
	public static function default_system(): string {
		$host = sanitize_key( str_replace( '.', '-', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		return '' === $host ? 'course-captain' : $host;
	}

	/**
	 * Area HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 */
	public static function html( array $request, int $user_id ): string {
		$html    = AdminUi::notice(
			sanitize_key( (string) ( $request['ccap_result'] ?? '' ) ),
			array(
				'ccap_export_no_course'    => __( 'Choose at least one course to export.', 'course-captain' ),
				'ccap_invalid_param'       => __( 'Enter the records per file as a whole number from 1 to 5000.', 'course-captain' ),
				'ccap_storage_unavailable' => __( 'The package could not be written. Check the protected folders under System status.', 'course-captain' ),
			),
			__( 'Done.', 'course-captain' )
		);
		$html   .= '<h2>' . esc_html__( 'Export a course as a package', 'course-captain' ) . '</h2>';
		$html   .= '<p>' . esc_html__( 'The package (ZIP file in the Course Captain package format) contains the course, its modules, published lessons and the questions of the module pools. Learner data, access, results and settings are never part of it. You can import it on another site under Import content.', 'course-captain' ) . '</p>';
		$courses = AdminUi::courses( $user_id );
		if ( array() === $courses ) {
			return $html . '<p>' . esc_html__( 'There is no course you may export.', 'course-captain' ) . '</p>';
		}
		$fields = '<fieldset><legend><strong>' . esc_html__( 'Courses', 'course-captain' ) . '</strong></legend>';
		$chosen = absint( $request['course'] ?? 0 );
		foreach ( $courses as $course ) {
			$fields .= '<p><label><input type="checkbox" name="courses[]" value="' . (int) $course->ID . '"' . checked( $chosen, (int) $course->ID, false ) . '> ' . esc_html( $course->post_title ) . '</label></p>';
		}
		$fields .= '</fieldset><fieldset><legend><strong>' . esc_html__( 'Options', 'course-captain' ) . '</strong></legend>';
		$fields .= '<p><label for="ccap-exp-title">' . esc_html__( 'Package title (optional)', 'course-captain' ) . '</label><br><input id="ccap-exp-title" type="text" class="regular-text" name="title" aria-describedby="ccap-exp-title-help"><br><span id="ccap-exp-title-help" class="description">' . esc_html__( 'Empty: the titles of the chosen courses.', 'course-captain' ) . '</span></p>';
		$fields .= '<p><label for="ccap-exp-system">' . esc_html__( 'Source name', 'course-captain' ) . '</label><br><input id="ccap-exp-system" type="text" class="regular-text" name="system" value="' . esc_attr( self::default_system() ) . '" aria-describedby="ccap-exp-system-help"><br><span id="ccap-exp-system-help" class="description">' . esc_html__( 'Identifies where the content comes from. Keep it the same for later exports of the same courses, so that a new import updates the content instead of copying it (lowercase letters, digits and hyphens).', 'course-captain' ) . '</span></p>';
		$fields .= '<p><label for="ccap-exp-per-file">' . esc_html__( 'Records per data file', 'course-captain' ) . '</label><br><input id="ccap-exp-per-file" type="number" min="1" max="' . (int) PackageExporter::RECORDS_PER_FILE . '" step="1" name="per_file" value="' . (int) PackageExporter::RECORDS_PER_FILE . '" aria-describedby="ccap-exp-per-file-help"><br><span id="ccap-exp-per-file-help" class="description">' . esc_html__( 'Large courses are split into several data files; 5000 is the most the format allows.', 'course-captain' ) . '</span></p></fieldset>';
		return $html . AdminUi::form( self::ACTION, 'export', 0, $fields, __( 'Export and download', 'course-captain' ), '1' );
	}
}
