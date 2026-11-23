<?php
/**
 * Admin page "Audit log" (R0-05 GM-138).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Admin\AdminUi;
use CourseCaptain\Admin\EditorScreens;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-text table with filters (GET form, works without JS) and paging.
 * Area "Settings" switches to one row per changed key with before/after
 * (SET-CC-27); both views export the current filter as CSV (admin-post,
 * nonce, audited).
 */
final class AuditScreen {

	public const SLUG   = 'course-captain-audit';
	public const EXPORT = 'ccap_audit_export';

	/** Filter keys of the GET form. */
	private const FILTERS = array( 'user', 'area', 'action', 'from', 'to', 'object_id', 'reference', 'key' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				AdminUi::settings_assets( add_submenu_page( EditorScreens::MENU, __( 'Audit log', 'course-captain' ), __( 'Audit log', 'course-captain' ), AuditView::CAP, self::SLUG, array( self::class, 'render' ) ) );
			},
			20
		);
		add_action( 'admin_post_' . self::EXPORT, array( self::class, 'handle_export' ) );
	}

	/**
	 * CSV download of the current filter.
	 *
	 * @return void
	 */
	public static function handle_export(): void {
		check_admin_referer( self::EXPORT );
		$filters = self::filters( wp_unslash( $_POST ) );
		try {
			$csv = AuditView::csv( Actor::user( get_current_user_id() ), array_filter( $filters ), 'settings' === $filters['area'] );
		} catch ( \CourseCaptain\Course\CourseException $e ) {
			wp_die( esc_html__( 'You are not allowed to export the audit log.', 'course-captain' ), '', array( 'response' => 403 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="audit-' . ( 'settings' === $filters['area'] ? 'settings-' : '' ) . gmdate( 'Ymd-His' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, cells quoted and neutralized.
		exit;
	}

	/**
	 * Sanitized filters of a request.
	 *
	 * @param array<string, mixed> $request Raw (unslashed) request.
	 * @return array<string, string>
	 */
	public static function filters( array $request ): array {
		$filters = array();
		foreach ( self::FILTERS as $key ) {
			$filters[ $key ] = isset( $request[ $key ] ) && is_scalar( $request[ $key ] ) ? sanitize_text_field( (string) $request[ $key ] ) : '';
		}
		return $filters;
	}

	/**
	 * Page output.
	 *
	 * @return void
	 */
	public static function render(): void {
		echo self::html( $_GET ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.NonceVerification.Recommended -- Escaped while building; read-only filter form.
	}

	/**
	 * Page HTML for a filter set.
	 *
	 * @param array<string, mixed> $request Raw request.
	 * @return string
	 */
	public static function html( array $request ): string {
		$filters  = self::filters( wp_unslash( $request ) );
		$settings = 'settings' === $filters['area'];
		$page     = isset( $request['paged'] ) ? max( 1, absint( $request['paged'] ) ) : 1;
		$html     = '<div class="wrap"><h1>' . esc_html__( 'Audit log', 'course-captain' ) . '</h1>';
		$html    .= '<p>' . ( $settings
			? '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Show all entries', 'course-captain' ) . '</a>'
			: '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&area=settings' ) ) . '">' . esc_html__( 'Show settings changes with before and after', 'course-captain' ) . '</a>' ) . '</p><form method="get"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '">';
		$fields   = array(
			'user'      => __( 'User ID', 'course-captain' ),
			'object_id' => __( 'Object ID', 'course-captain' ),
			'reference' => __( 'Grant reference', 'course-captain' ),
			'from'      => __( 'From (YYYY-MM-DD)', 'course-captain' ),
			'to'        => __( 'To (YYYY-MM-DD)', 'course-captain' ),
			'key'       => __( 'Setting (e.g. privacy or privacy.show_avatars; area Settings)', 'course-captain' ),
		);
		foreach ( $fields as $key => $label ) {
			$html .= '<label for="ccap-audit-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label> <input type="text" id="ccap-audit-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $filters[ $key ] ) . '"> ';
		}
		$areas = array_unique( array_column( AuditView::registry(), 0 ) );
		$html .= '<label for="ccap-audit-area">' . esc_html__( 'Area', 'course-captain' ) . '</label> <select id="ccap-audit-area" name="area"><option value="">' . esc_html__( 'All', 'course-captain' ) . '</option>';
		foreach ( $areas as $area ) {
			$html .= '<option value="' . esc_attr( $area ) . '"' . selected( $filters['area'], $area, false ) . '>' . esc_html( $area ) . '</option>';
		}
		$html .= '</select> <button class="button">' . esc_html__( 'Filter', 'course-captain' ) . '</button></form>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::EXPORT ) . '">' . wp_nonce_field( self::EXPORT, '_wpnonce', true, false );
		foreach ( array_filter( $filters ) as $key => $value ) {
			$html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
		$html .= '<p><button type="submit" class="button">' . esc_html__( 'Export as CSV', 'course-captain' ) . '</button> <span class="description">' . esc_html( sprintf( /* translators: %d: maximum number of entries */ __( 'Exports the current filter, at most %d entries. Values of personal and secret settings are never included.', 'course-captain' ), AuditView::CSV_MAX ) ) . '</span></p></form>';
		if ( $settings ) {
			return $html . self::settings_table( $filters, $page ) . '</div>';
		}
		$result = AuditView::query( Actor::user( get_current_user_id() ), array_filter( $filters ), $page );
		$table  = '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Audit entries', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Time (UTC)', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Event', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'By', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Object', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Details', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $result['items'] as $item ) {
			$table .= '<tr><td>' . esc_html( $item['time'] ) . '</td><td>' . esc_html( $item['label'] ) . '</td><td>' . esc_html( $item['actor'] ) . '</td><td>' . esc_html( $item['object'] ) . '</td><td>' . esc_html( $item['details'] ) . '</td></tr>';
		}
		$html .= AdminUi::scroll_table( $table . '</tbody></table>', __( 'Audit entries', 'course-captain' ) );
		/* translators: 1: page, 2: pages */
		$html .= '<p>' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'course-captain' ), $page, $result['pages'] ) ) . '</p></div>';
		return $html;
	}

	/**
	 * Settings view: one row per changed key with before/after (SET-CC-27).
	 *
	 * @param array<string, string> $filters Filters.
	 * @param int                   $page    Page.
	 * @return string
	 */
	private static function settings_table( array $filters, int $page ): string {
		$result = AuditView::settings_changes( Actor::user( get_current_user_id() ), array_filter( $filters ), $page );
		$html   = '<table class="widefat striped"><caption>' . esc_html__( 'Settings changes with values before and after', 'course-captain' ) . '</caption><thead><tr>'
			. '<th scope="col">' . esc_html__( 'Time (UTC)', 'course-captain' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Event', 'course-captain' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'By', 'course-captain' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Setting', 'course-captain' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Before', 'course-captain' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'After', 'course-captain' ) . '</th>'
			. '</tr></thead><tbody>';
		foreach ( $result['items'] as $item ) {
			$by    = $item['actor'] . ( '' === $item['via'] ? '' : ' (' . $item['via'] . ')' );
			$html .= '<tr><td>' . esc_html( $item['time'] ) . '</td><td>' . esc_html( $item['label'] ) . '</td><td>' . esc_html( $by ) . '</td><th scope="row"><code>' . esc_html( $item['key'] ) . '</code></th><td>' . esc_html( $item['before'] ) . '</td><td>' . esc_html( $item['after'] ) . '</td></tr>';
		}
		if ( array() === $result['items'] ) {
			$html .= '<tr><td colspan="6">' . esc_html__( 'No settings changes for this filter.', 'course-captain' ) . '</td></tr>';
		}
		$html = AdminUi::scroll_table( $html . '</tbody></table>', __( 'Settings changes with values before and after', 'course-captain' ) );
		/* translators: 1: page, 2: pages */
		return $html . '<p>' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'course-captain' ), $page, $result['pages'] ) ) . '</p>';
	}
}
