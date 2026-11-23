<?php
/**
 * Admin screen A-11 "Certificates" (R0-29 §2.2 CF-03, CF-04, CF-09…CF-11, R1-35).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Certificate\CertificateAdmin;
use CourseCaptain\Certificate\CertificatePages;
use CourseCaptain\Certificate\CertificateService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tabs "Issued certificates" (list with filters, CSV export with a
 * mandatory reason, manual issuance from evidence) and "Base template"
 * (issuer, footer, logo ≤ 2 MB, signature line; every change = new
 * template version, issued certificates stay unchanged). The detail view
 * offers correction and revocation through the domain handlers of R1-44.
 * Free only: no PDF, designer or licence elements (CR-6, E-100).
 */
final class CertificatesScreen {

	public const SLUG            = 'course-captain-certificates';
	public const CAP             = 'ccap_manage_certificates';
	public const EXPORT_ACTION   = 'ccap_certificate_export';
	public const TEMPLATE_ACTION = 'ccap_certificate_template';
	public const LOGO_MAX_BYTES  = 2097152;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				// SET-CC-24: no menu entry while the module is switched off.
				if ( ! \CourseCaptain\Settings\Modules::enabled( \CourseCaptain\Settings\Modules::CERTIFICATES ) ) {
					return;
				}
				add_submenu_page( EditorScreens::MENU, __( 'Certificates', 'course-captain' ), __( 'Certificates', 'course-captain' ), self::CAP, self::SLUG, array( self::class, 'render' ) );
			},
			20
		);
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( self::class, 'handle_export' ) );
		add_action( 'admin_post_' . self::TEMPLATE_ACTION, array( self::class, 'handle_template' ) );
	}

	/**
	 * Page output.
	 *
	 * @return void
	 */
	public static function render(): void {
		try {
			echo self::html( $_GET, get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.NonceVerification.Recommended -- Escaped while building; read-only view parameters.
		} catch ( CourseException $e ) {
			wp_die( esc_html__( 'You are not allowed to view certificates.', 'course-captain' ), '', array( 'response' => (int) $e->status ) );
		}
	}

	/**
	 * CSV download (CF-11).
	 *
	 * @return void
	 */
	public static function handle_export(): void {
		check_admin_referer( self::EXPORT_ACTION );
		$post = wp_unslash( $_POST );
		try {
			$csv = CertificateAdmin::export_csv( Actor::user( get_current_user_id() ), self::filters( (array) $post ), isset( $post['reason'] ) ? (string) $post['reason'] : '' );
		} catch ( CourseException $e ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'             => self::SLUG,
						'ccap_cert_result' => $e->error_code,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="certificates-' . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, cells quoted and neutralized.
		exit;
	}

	/**
	 * Base template save (CF-04).
	 *
	 * @return void
	 */
	public static function handle_template(): void {
		$result = self::save_template( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_template().
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::SLUG,
					'tab'              => 'template',
					'ccap_cert_result' => $result,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Testable core of the template save: #19, nonce, logo image ≤ 2 MB.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @param int                  $user_id Current user.
	 * @return string Result code.
	 */
	public static function save_template( array $post, int $user_id ): string {
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::TEMPLATE_ACTION ) ) {
			return 'ccap_invalid_nonce';
		}
		if ( ! \CourseCaptain\Settings\Modules::enabled( \CourseCaptain\Settings\Modules::CERTIFICATES ) ) {
			return 'ccap_module_disabled';
		}
		$logo = absint( $post['logo_id'] ?? 0 );
		if ( $logo > 0 ) {
			$file = get_attached_file( $logo );
			if ( ! wp_attachment_is_image( $logo ) || ! is_string( $file ) || ! is_readable( $file ) || (int) filesize( $file ) > self::LOGO_MAX_BYTES ) {
				return 'ccap_logo_invalid';
			}
		}
		$value = array(
			'issuer_name'     => isset( $post['issuer_name'] ) ? (string) $post['issuer_name'] : '',
			'footer_text'     => isset( $post['footer_text'] ) ? (string) $post['footer_text'] : '',
			'logo_id'         => $logo,
			'signature_label' => isset( $post['signature_label'] ) ? (string) $post['signature_label'] : '',
		);
		try {
			$saved = Settings::save( Actor::user( $user_id ), 'certificates', array( 'base_template' => $value ), array( 'base_template' ), absint( $post['rev'] ?? 0 ) );
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
		if ( array() !== $saved['changed'] ) {
			// A new definition gets its version now, before the next issuance.
			CertificateService::template();
		}
		return 'ok';
	}

	/**
	 * Filters from a request.
	 *
	 * @param array<string, mixed> $request Request.
	 * @return array<string, mixed>
	 */
	public static function filters( array $request ): array {
		return array(
			'course' => absint( $request['course'] ?? 0 ),
			'status' => isset( $request['status'] ) ? sanitize_key( (string) $request['status'] ) : '',
			'from'   => isset( $request['from'] ) ? sanitize_text_field( (string) $request['from'] ) : '',
			'to'     => isset( $request['to'] ) ? sanitize_text_field( (string) $request['to'] ) : '',
			'kind'   => isset( $request['kind'] ) ? sanitize_key( (string) $request['kind'] ) : '',
		);
	}

	/**
	 * Page HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 * @throws CourseException Without #13/#17.
	 */
	public static function html( array $request, int $user_id ): string {
		$actor = Actor::user( $user_id );
		if ( ! CertificateAdmin::can_read( $actor ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to view certificates.', 403 );
		}
		if ( ! \CourseCaptain\Settings\Modules::enabled( \CourseCaptain\Settings\Modules::CERTIFICATES ) ) {
			return '<div class="wrap"><h1>' . esc_html__( 'Certificates', 'course-captain' ) . '</h1><p>' . esc_html__( 'Certificates are switched off under Settings → Certificates. Issued certificates are kept and stay verifiable.', 'course-captain' ) . '</p></div>';
		}
		$tab  = isset( $request['tab'] ) && 'template' === $request['tab'] ? 'template' : 'list';
		$html = '<div class="wrap"><h1>' . esc_html__( 'Certificates', 'course-captain' ) . '</h1>' . self::nav( $tab ) . self::notice( $request );
		if ( 'template' === $tab ) {
			return $html . self::template_tab( $actor ) . '</div>';
		}
		if ( ! empty( $request['certificate'] ) ) {
			return $html . self::detail( $actor, sanitize_text_field( (string) $request['certificate'] ) ) . '</div>';
		}
		return $html . self::list_tab( $actor, $request ) . '</div>';
	}

	/**
	 * List with filters, export and manual issuance.
	 *
	 * @param Actor                $actor   Viewer.
	 * @param array<string, mixed> $request GET.
	 * @return string
	 */
	private static function list_tab( Actor $actor, array $request ): string {
		$filters = self::filters( $request );
		$page    = max( 1, absint( $request['paged'] ?? 1 ) );
		$result  = CertificateAdmin::query( $actor, $filters, $page );
		$courses = get_posts(
			array(
				'post_type'   => Relations::COURSE_TYPE,
				'post_status' => 'any',
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$html    = '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><fieldset><legend>' . esc_html__( 'Filter', 'course-captain' ) . '</legend>';
		$html   .= '<label for="ccap-f-course">' . esc_html__( 'Course', 'course-captain' ) . '</label> <select id="ccap-f-course" name="course"><option value="0">' . esc_html__( 'all', 'course-captain' ) . '</option>';
		foreach ( $courses as $course ) {
			if ( $course instanceof \WP_Post ) {
				$html .= '<option value="' . (int) $course->ID . '"' . selected( $filters['course'], (int) $course->ID, false ) . '>' . esc_html( $course->post_title ) . '</option>';
			}
		}
		$html .= '</select> <label for="ccap-f-status">' . esc_html__( 'Status', 'course-captain' ) . '</label> <select id="ccap-f-status" name="status"><option value="">' . esc_html__( 'all', 'course-captain' ) . '</option>';
		foreach ( array( 'valid', 'expired', 'corrected', 'revoked' ) as $status ) {
			$html .= '<option value="' . esc_attr( $status ) . '"' . selected( $filters['status'], $status, false ) . '>' . esc_html( CertificatePages::status_text( $status ) ) . '</option>';
		}
		$html .= '</select> <label for="ccap-f-from">' . esc_html__( 'Issued from', 'course-captain' ) . '</label> <input id="ccap-f-from" type="date" name="from" value="' . esc_attr( (string) $filters['from'] ) . '"> <label for="ccap-f-to">' . esc_html__( 'to', 'course-captain' ) . '</label> <input id="ccap-f-to" type="date" name="to" value="' . esc_attr( (string) $filters['to'] ) . '">';
		$html .= ' <label for="ccap-f-kind">' . esc_html__( 'Template', 'course-captain' ) . '</label> <select id="ccap-f-kind" name="kind"><option value="">' . esc_html__( 'all', 'course-captain' ) . '</option><option value="base"' . selected( $filters['kind'], 'base', false ) . '>' . esc_html__( 'base template', 'course-captain' ) . '</option></select> <button class="button" type="submit">' . esc_html__( 'Apply filter', 'course-captain' ) . '</button></fieldset></form>';
		/* translators: %d: number of certificates */
		$html .= '<table class="widefat striped"><caption>' . esc_html( sprintf( _n( '%d certificate', '%d certificates', $result['total'], 'course-captain' ), $result['total'] ) ) . '</caption><thead><tr>';
		foreach ( array( __( 'Certificate no.', 'course-captain' ), __( 'Holder', 'course-captain' ), __( 'Course', 'course-captain' ), __( 'Status', 'course-captain' ), __( 'Issued', 'course-captain' ), __( 'Valid until', 'course-captain' ), __( 'Version', 'course-captain' ) ) as $label ) {
			$html .= '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		if ( array() === $result['items'] ) {
			$html .= '<tr><td colspan="7">' . esc_html__( 'No certificates match the filter.', 'course-captain' ) . '</td></tr>';
		}
		foreach ( $result['items'] as $item ) {
			$url   = add_query_arg(
				array(
					'page'        => self::SLUG,
					'certificate' => $item['certificate_uid'],
				),
				admin_url( 'admin.php' )
			);
			$html .= '<tr><th scope="row"><a href="' . esc_url( $url ) . '">' . esc_html( $item['display_id'] ) . '</a></th><td>' . esc_html( $item['holder'] ) . '</td><td>' . esc_html( $item['course_title'] ) . '</td><td>' . esc_html( CertificatePages::status_text( $item['status'] ) ) . '</td><td>' . esc_html( CertificateService::site_date( $item['issued_at'] ) ) . '</td><td>' . esc_html( '' === $item['valid_until'] ? __( 'unlimited', 'course-captain' ) : $item['valid_until'] ) . '</td><td>' . esc_html( $item['template_version'] . ' / ' . $item['revision_no'] ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		if ( $result['pages'] > 1 ) {
			$html .= '<p>' . esc_html( sprintf( /* translators: 1: page, 2: pages */ __( 'Page %1$d of %2$d', 'course-captain' ), $page, $result['pages'] ) ) . ' ';
			if ( $page < $result['pages'] ) {
				$html .= '<a href="' . esc_url( add_query_arg( array_filter( $filters ) + array( 'page' => self::SLUG, 'paged' => $page + 1 ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Next page', 'course-captain' ) . '</a>'; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			}
			$html .= '</p>';
		}
		$hidden = '';
		foreach ( $filters as $key => $value ) {
			$hidden .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		$html .= '<h2>' . esc_html__( 'Export', 'course-captain' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::EXPORT_ACTION ) . '">' . wp_nonce_field( self::EXPORT_ACTION, '_wpnonce', true, false ) . $hidden . '<p><label for="ccap-export-reason">' . esc_html__( 'Reason for the export (required, the export contains names)', 'course-captain' ) . '</label><br><input id="ccap-export-reason" type="text" name="reason" class="regular-text" required></p><button class="button" type="submit">' . esc_html__( 'Download CSV (current filter)', 'course-captain' ) . '</button></form>';
		$html .= '<h2>' . esc_html__( 'Issue from existing evidence', 'course-captain' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ccap_certificate_issue">' . wp_nonce_field( 'ccap_certificate_issue', '_wpnonce', true, false );
		$html .= '<p><label for="ccap-issue-type">' . esc_html__( 'Evidence', 'course-captain' ) . '</label> <select id="ccap-issue-type" name="type"><option value="' . esc_attr( CertificateService::EVIDENCE_EXAM ) . '">' . esc_html__( 'passed exam attempt', 'course-captain' ) . '</option><option value="' . esc_attr( CertificateService::EVIDENCE_COURSE ) . '">' . esc_html__( 'course completion', 'course-captain' ) . '</option></select> <label for="ccap-issue-id">' . esc_html__( 'Evidence ID', 'course-captain' ) . '</label> <input id="ccap-issue-id" type="number" min="1" name="evidence_id" required></p>';
		$html .= '<p><label for="ccap-issue-reason">' . esc_html__( 'Reason (required)', 'course-captain' ) . '</label><br><textarea id="ccap-issue-reason" name="reason" rows="2" class="large-text" required></textarea></p><button class="button" type="submit">' . esc_html__( 'Issue certificate', 'course-captain' ) . '</button></form>';
		return $html;
	}

	/**
	 * Detail with correction and revocation.
	 *
	 * @param Actor  $actor Viewer.
	 * @param string $uid   Certificate UID.
	 * @return string
	 */
	private static function detail( Actor $actor, string $uid ): string {
		$row = CertificateService::current()->latest( $uid );
		if ( null === $row ) {
			return '<p>' . esc_html__( 'This certificate does not exist.', 'course-captain' ) . '</p>';
		}
		$snapshot = json_decode( (string) $row['snapshot'], true );
		$snapshot = is_array( $snapshot ) ? $snapshot : array();
		$status   = CertificateService::status_of( $row );
		$html     = '<h2>' . esc_html( (string) ( $snapshot['display_id'] ?? '' ) ) . '</h2><dl>';
		$pairs    = array(
			__( 'Status', 'course-captain' )           => CertificatePages::status_text( $status ),
			__( 'Holder', 'course-captain' )           => (string) ( $snapshot['holder']['display_name'] ?? '' ),
			__( 'Course', 'course-captain' )           => (string) ( $snapshot['course']['title'] ?? '' ),
			__( 'Issued', 'course-captain' )           => CertificateService::site_date( (string) $row['issued_at'] ),
			__( 'Issuer', 'course-captain' )           => (string) ( $snapshot['issuer']['name'] ?? '' ),
			__( 'Revision', 'course-captain' )         => (string) $row['revision_no'],
			__( 'Template version', 'course-captain' ) => (string) $row['template_version'],
		);
		foreach ( $pairs as $label => $value ) {
			$html .= '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
		}
		$verify = CertificatePages::verify_url( CertificateService::token( $uid, (int) $row['revision_no'] ) );
		$html  .= '<dt>' . esc_html__( 'Verification link', 'course-captain' ) . '</dt><dd><a href="' . esc_url( $verify ) . '">' . esc_html( $verify ) . '</a></dd>';
		$html  .= '<dt>' . esc_html__( 'View', 'course-captain' ) . '</dt><dd><a href="' . esc_url( CertificatePages::view_url( $uid ) ) . '">' . esc_html__( 'Open the HTML and print view', 'course-captain' ) . '</a></dd></dl>';
		if ( 'revoked' === $status || ! user_can( $actor->user_id, self::CAP ) ) {
			return $html;
		}
		$html .= '<h3>' . esc_html__( 'Correct', 'course-captain' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ccap_certificate_correct">' . wp_nonce_field( 'ccap_certificate_correct', '_wpnonce', true, false ) . '<input type="hidden" name="uid" value="' . esc_attr( $uid ) . '"><input type="hidden" name="request_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
		$html .= '<p><label for="ccap-c-name">' . esc_html__( 'Holder name (empty = unchanged)', 'course-captain' ) . '</label><br><input id="ccap-c-name" type="text" name="holder_name" class="regular-text"></p>';
		$html .= '<p><label for="ccap-c-title">' . esc_html__( 'Course title (empty = unchanged)', 'course-captain' ) . '</label><br><input id="ccap-c-title" type="text" name="course_title" class="regular-text"></p>';
		$html .= '<p><label><input type="checkbox" name="refresh_evidence" value="1"> ' . esc_html__( 'Take the current evaluation of the evidence', 'course-captain' ) . '</label></p>';
		$html .= '<p><label for="ccap-c-reason">' . esc_html__( 'Reason (required)', 'course-captain' ) . '</label><br><textarea id="ccap-c-reason" name="reason" rows="2" class="large-text" required></textarea></p><button class="button" type="submit">' . esc_html__( 'Create corrected version', 'course-captain' ) . '</button></form>';
		$html .= '<h3>' . esc_html__( 'Revoke', 'course-captain' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ccap_certificate_revoke">' . wp_nonce_field( 'ccap_certificate_revoke', '_wpnonce', true, false ) . '<input type="hidden" name="uid" value="' . esc_attr( $uid ) . '">';
		$html .= '<p><label for="ccap-r-cat">' . esc_html__( 'Reason category', 'course-captain' ) . '</label> <select id="ccap-r-cat" name="category">';
		$cats  = array(
			'fraud'              => __( 'fraud', 'course-captain' ),
			'invalid_assessment' => __( 'invalid assessment', 'course-captain' ),
			'issued_in_error'    => __( 'issued in error', 'course-captain' ),
			'other'              => __( 'other', 'course-captain' ),
		);
		foreach ( $cats as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		$html .= '</select></p><p><label for="ccap-r-text">' . esc_html__( 'Details (internal)', 'course-captain' ) . '</label><br><textarea id="ccap-r-text" name="reason" rows="2" class="large-text"></textarea></p><p>' . esc_html__( 'Revocation is final and applies to all versions of this certificate.', 'course-captain' ) . '</p><button class="button" type="submit">' . esc_html__( 'Revoke certificate', 'course-captain' ) . '</button></form>';
		return $html;
	}

	/**
	 * Base template tab (CF-04).
	 *
	 * @param Actor $actor Viewer.
	 * @return string
	 */
	private static function template_tab( Actor $actor ): string {
		$template = (array) Settings::get( 'certificates.base_template' );
		$html     = '<p>' . esc_html__( 'Every change creates a new template version. Certificates already issued keep their version and look the same as before.', 'course-captain' ) . '</p>';
		if ( user_can( $actor->user_id, Settings::CAP ) ) {
			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::TEMPLATE_ACTION ) . '">' . wp_nonce_field( self::TEMPLATE_ACTION, '_wpnonce', true, false ) . '<input type="hidden" name="rev" value="' . (int) Settings::rev( 'certificates' ) . '">';
			$html .= '<p><label for="ccap-t-issuer">' . esc_html__( 'Issuer (empty = site title)', 'course-captain' ) . '</label><br><input id="ccap-t-issuer" type="text" maxlength="120" name="issuer_name" class="regular-text" value="' . esc_attr( (string) ( $template['issuer_name'] ?? '' ) ) . '"></p>';
			$html .= '<p><label for="ccap-t-footer">' . esc_html__( 'Footer text', 'course-captain' ) . '</label><br><textarea id="ccap-t-footer" maxlength="500" name="footer_text" rows="3" class="large-text">' . esc_textarea( (string) ( $template['footer_text'] ?? '' ) ) . '</textarea></p>';
			$html .= '<p><label for="ccap-t-logo">' . esc_html__( 'Logo (media ID of an image, at most 2 MB; 0 = none)', 'course-captain' ) . '</label><br><input id="ccap-t-logo" type="number" min="0" name="logo_id" value="' . (int) ( $template['logo_id'] ?? 0 ) . '"></p>';
			$html .= '<p><label for="ccap-t-sign">' . esc_html__( 'Signature line', 'course-captain' ) . '</label><br><input id="ccap-t-sign" type="text" maxlength="120" name="signature_label" class="regular-text" value="' . esc_attr( (string) ( $template['signature_label'] ?? '' ) ) . '"></p>';
			$html .= '<button class="button button-primary" type="submit">' . esc_html__( 'Save template', 'course-captain' ) . '</button></form>';
		}
		$html .= '<table class="widefat striped"><caption>' . esc_html__( 'Template versions', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Version', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Created', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( CertificateAdmin::template_versions() as $version ) {
			$html .= '<tr><td>' . (int) $version['version'] . '</td><td>' . esc_html( $version['created_at'] ) . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Tabs.
	 *
	 * @param string $current Current tab.
	 * @return string
	 */
	private static function nav( string $current ): string {
		$html = '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Certificates: areas', 'course-captain' ) . '">';
		foreach ( array(
			'list'     => __( 'Issued certificates', 'course-captain' ),
			'template' => __( 'Base template', 'course-captain' ),
		) as $tab => $label ) {
			$url   = add_query_arg(
				array(
					'page' => self::SLUG,
					'tab'  => $tab,
				),
				admin_url( 'admin.php' )
			);
			$html .= '<a class="nav-tab' . ( $tab === $current ? ' nav-tab-active" aria-current="page' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		return $html . '</nav>';
	}

	/**
	 * Result notice (own results and those of the R1-44 handlers).
	 *
	 * @param array<string, mixed> $request GET.
	 * @return string
	 */
	private static function notice( array $request ): string {
		$code = '';
		foreach ( array( 'ccap_cert_result', 'ccap_certificate_result' ) as $key ) {
			if ( ! empty( $request[ $key ] ) ) {
				$code = sanitize_key( (string) $request[ $key ] );
			}
		}
		if ( '' === $code ) {
			return '';
		}
		$texts = array(
			'ok'                     => __( 'Saved.', 'course-captain' ),
			'ccap_invalid_nonce'     => __( 'The form has expired. Reload the page and try again.', 'course-captain' ),
			'ccap_forbidden'         => __( 'You are not allowed to run this action.', 'course-captain' ),
			'ccap_reason_required'   => __( 'Please enter a reason.', 'course-captain' ),
			'ccap_validation_failed' => __( 'Please check the entries.', 'course-captain' ),
			'ccap_logo_invalid'      => __( 'The logo must be an image from the media library and at most 2 MB.', 'course-captain' ),
			'ccap_settings_conflict' => __( 'The template was changed in the meantime. Reload the page.', 'course-captain' ),
			'ccap_not_found'         => __( 'The certificate or evidence was not found.', 'course-captain' ),
		);
		/* translators: %s: error code */
		$text = $texts[ $code ] ?? sprintf( __( 'The action failed (%s).', 'course-captain' ), $code );
		$ok   = 'ok' === $code;
		return '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . '" role="' . ( $ok ? 'status' : 'alert' ) . '"><p>' . esc_html( $text ) . '</p></div>';
	}
}
