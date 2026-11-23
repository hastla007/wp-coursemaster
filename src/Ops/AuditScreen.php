<?php
/**
 * Admin page "Audit log" (R0-05 GM-138).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Admin\EditorScreens;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-text table with filters (GET form, works without JS) and paging.
 */
final class AuditScreen {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				add_submenu_page( EditorScreens::MENU, __( 'Audit log', 'course-captain' ), __( 'Audit log', 'course-captain' ), AuditView::CAP, 'course-captain-audit', array( self::class, 'render' ) );
			},
			20
		);
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
		$filters = array();
		foreach ( array( 'user', 'area', 'action', 'from', 'to', 'object_id' ) as $key ) {
			$filters[ $key ] = isset( $request[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $request[ $key ] ) ) : '';
		}
		$page   = isset( $request['paged'] ) ? max( 1, absint( $request['paged'] ) ) : 1;
		$result = AuditView::query( Actor::user( get_current_user_id() ), array_filter( $filters ), $page );
		$html   = '<div class="wrap"><h1>' . esc_html__( 'Audit log', 'course-captain' ) . '</h1><form method="get"><input type="hidden" name="page" value="course-captain-audit">';
		$fields = array(
			'user'      => __( 'User ID', 'course-captain' ),
			'object_id' => __( 'Object ID', 'course-captain' ),
			'from'      => __( 'From (YYYY-MM-DD)', 'course-captain' ),
			'to'        => __( 'To (YYYY-MM-DD)', 'course-captain' ),
		);
		foreach ( $fields as $key => $label ) {
			$html .= '<label for="ccap-audit-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label> <input id="ccap-audit-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $filters[ $key ] ) . '"> ';
		}
		$areas = array_unique( array_column( AuditView::registry(), 0 ) );
		$html .= '<label for="ccap-audit-area">' . esc_html__( 'Area', 'course-captain' ) . '</label> <select id="ccap-audit-area" name="area"><option value="">' . esc_html__( 'All', 'course-captain' ) . '</option>';
		foreach ( $areas as $area ) {
			$html .= '<option value="' . esc_attr( $area ) . '"' . selected( $filters['area'], $area, false ) . '>' . esc_html( $area ) . '</option>';
		}
		$html .= '</select> <button class="button">' . esc_html__( 'Filter', 'course-captain' ) . '</button></form>';
		$html .= '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Audit entries', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Time (UTC)', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Event', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'By', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Object', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Details', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $result['items'] as $item ) {
			$html .= '<tr><td>' . esc_html( $item['time'] ) . '</td><td>' . esc_html( $item['label'] ) . '</td><td>' . esc_html( $item['actor'] ) . '</td><td>' . esc_html( $item['object'] ) . '</td><td>' . esc_html( $item['details'] ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		/* translators: 1: page, 2: pages */
		$html .= '<p>' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'course-captain' ), $page, $result['pages'] ) ) . '</p></div>';
		return $html;
	}
}
