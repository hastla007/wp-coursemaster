<?php
/**
 * "Back up settings": export download and import with preview
 * (R0-27 §2.5, R0-30 §1.1 "Einstellungen sichern", R0-31 §4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsTransfer;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden page (#19). Export is a browser download without a server copy
 * (EX-6); personal fields only with the opt-in box. Import: upload ≤ 256 KB
 * → preview with field diff per tab, skipped fields with reason and one
 * confirmation box per risk field → apply (per tab atomic, undo snapshot).
 */
final class SettingsBackup {

	public const SLUG    = 'course-captain-settings-backup';
	public const EXPORT  = 'ccap_settings_export';
	public const IMPORT  = 'ccap_settings_import';
	public const PENDING = 'ccap_settings_import_file_';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				AdminUi::settings_assets( add_submenu_page( '', __( 'Back up settings', 'course-captain' ), __( 'Back up settings', 'course-captain' ), Settings::CAP, self::SLUG, array( self::class, 'render' ) ) );
			},
			19
		);
		add_action( 'admin_post_' . self::EXPORT, array( self::class, 'handle_export' ) );
		add_action( 'admin_post_' . self::IMPORT, array( self::class, 'handle_import' ) );
	}

	/**
	 * Download (no server copy).
	 *
	 * @return void
	 */
	public static function handle_export(): void {
		check_admin_referer( self::EXPORT );
		try {
			$include = isset( $_POST['include'] ) && is_array( $_POST['include'] ) ? array_values( array_map( 'sanitize_key', array_map( 'strval', (array) wp_unslash( $_POST['include'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each entry passes sanitize_key().
			$data    = SettingsTransfer::export(
				Actor::user( get_current_user_id() ),
				array(
					'include_personal' => ! empty( $_POST['include_personal'] ),
					'include'          => $include,
				)
			);
		} catch ( CourseException $e ) {
			wp_die( esc_html__( 'You are not allowed to export settings.', 'course-captain' ), '', array( 'response' => 403 ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="course-captain-settings-' . gmdate( 'Ymd' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		exit;
	}

	/**
	 * Admin-post: upload (preview) or apply.
	 *
	 * @return void
	 */
	public static function handle_import(): void {
		$raw  = '';
		$tmp  = isset( $_FILES['file']['tmp_name'] ) ? sanitize_text_field( (string) $_FILES['file']['tmp_name'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process().
		$size = isset( $_FILES['file']['size'] ) ? absint( $_FILES['file']['size'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process().
		if ( '' !== $tmp && is_uploaded_file( $tmp ) && $size <= SettingsTransfer::MAX_BYTES ) {
			$raw = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Uploaded temp file.
		}
		$result = self::process( wp_unslash( $_POST ), $raw, get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process().
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'ccap_result' => $result ), admin_url( 'admin.php' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
		exit;
	}

	/**
	 * Testable core.
	 *
	 * @param array<string, mixed> $post    POST.
	 * @param string               $raw     Uploaded file content ('' on apply).
	 * @param int                  $user_id User.
	 * @return string `preview`, `imported`, `nothing`, error code.
	 */
	public static function process( array $post, string $raw, int $user_id ): string {
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::IMPORT ) ) {
			return 'ccap_invalid_nonce';
		}
		$actor = Actor::user( $user_id );
		try {
			if ( empty( $post['apply'] ) ) {
				$data = SettingsTransfer::parse( $raw );
				SettingsTransfer::import( $actor, $data );
				set_transient( self::PENDING . $user_id, $data, \CourseCaptain\Settings\RiskConfirm::TTL );
				return 'preview';
			}
			$data = get_transient( self::PENDING . $user_id );
			delete_transient( self::PENDING . $user_id );
			if ( ! is_array( $data ) ) {
				return 'ccap_confirmation_expired';
			}
			$tabs      = is_array( $post['tabs'] ?? null ) ? array_values( array_map( 'sanitize_key', array_map( 'strval', $post['tabs'] ) ) ) : array();
			$surfaces  = is_array( $post['surfaces'] ?? null ) ? array_values( array_map( 'sanitize_key', array_map( 'strval', $post['surfaces'] ) ) ) : array();
			$confirmed = is_array( $post['confirmed'] ?? null ) ? array_values( array_map( 'strval', $post['confirmed'] ) ) : array();
			$confirmed = array_values( array_filter( $confirmed, static fn( string $c ): bool => ! str_contains( $c, '*' ) ) );
			$options   = array(
				'tabs'             => $tabs,
				'surfaces'         => $surfaces,
				'confirmed'        => $confirmed,
				'retention_reason' => sanitize_textarea_field( (string) ( $post['retention_reason'] ?? '' ) ),
			);
			// E-245: a retention period above its default needs the reason; the preview stays for a second try.
			$dry = SettingsTransfer::import( $actor, $data, $options );
			if ( in_array( 'retention_reason', array_column( $dry['tabs']['privacy']['skipped'] ?? array(), 'reason' ), true ) ) {
				set_transient( self::PENDING . $user_id, $data, \CourseCaptain\Settings\RiskConfirm::TTL );
				return 'ccap_reason_required';
			}
			$report = SettingsTransfer::import( $actor, $data, array( 'apply' => true ) + $options );
			return array() === $report['applied'] && array() === $report['applied_surfaces'] ? 'nothing' : 'imported';
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
	}

	/**
	 * Page output.
	 *
	 * @return void
	 */
	public static function render(): void {
		echo self::html( $_GET, get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.NonceVerification.Recommended -- Escaped while building; read-only view parameters.
	}

	/**
	 * Page HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id User.
	 * @return string
	 */
	public static function html( array $request, int $user_id ): string {
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) ) {
			return '';
		}
		$result   = isset( $request['ccap_result'] ) ? sanitize_key( (string) $request['ccap_result'] ) : '';
		$messages = array(
			'imported'                     => __( 'Settings imported. Each imported tab can be undone for 30 days.', 'course-captain' ),
			'nothing'                      => __( 'Nothing was imported.', 'course-captain' ),
			'ccap_settings_import_invalid' => __( 'Error: the file is not a Course Captain settings file or larger than 256 KB.', 'course-captain' ),
			'ccap_settings_version_newer'  => __( 'Error: the file comes from a newer Course Captain version. Update the plugin first.', 'course-captain' ),
			'ccap_rate_limited'            => __( 'Error: at most ten imports per hour. Try again later.', 'course-captain' ),
			'ccap_forbidden'               => __( 'You are not allowed to import settings.', 'course-captain' ),
			'ccap_invalid_nonce'           => __( 'The form has expired. Reload the page and try again.', 'course-captain' ),
			'ccap_confirmation_expired'    => __( 'The preview has expired. Upload the file again.', 'course-captain' ),
			'ccap_reason_required'         => __( 'Error: the file sets retention periods longer than their suggested default. Enter a reason and import again.', 'course-captain' ),
		);
		$html     = '<div class="wrap"><h1>' . esc_html__( 'Back up settings', 'course-captain' ) . '</h1>';
		if ( isset( $messages[ $result ] ) ) {
			$html .= '<div class="notice notice-' . ( str_starts_with( $result, 'ccap_' ) ? 'error' : 'success' ) . '" tabindex="-1" data-ccap-focus="1"><p>' . esc_html( $messages[ $result ] ) . '</p></div>';
		}
		if ( 'preview' === $result || 'ccap_reason_required' === $result ) {
			return $html . self::preview( $user_id, 'ccap_reason_required' === $result ) . '</div>';
		}
		$post_url = esc_url( admin_url( 'admin-post.php' ) );
		$html    .= '<h2>' . esc_html__( 'Export', 'course-captain' ) . '</h2><form method="post" action="' . $post_url . '"><input type="hidden" name="action" value="' . esc_attr( self::EXPORT ) . '">' . wp_nonce_field( self::EXPORT, '_wpnonce', true, false )
			. '<p><label><input type="checkbox" name="include_personal" value="1" aria-describedby="ccap-export-help"> ' . esc_html__( 'Include personal fields (sender, footers)', 'course-captain' ) . '</label></p>'
			. self::surface_options( $user_id )
			. '<p id="ccap-export-help" class="description">' . esc_html__( 'Never exported: secrets, trusted proxies, file delivery, data deletion on uninstall, course and exam overrides. The file contains no URL and no names.', 'course-captain' ) . '</p>'
			. '<p><button type="submit" class="button">' . esc_html__( 'Download settings', 'course-captain' ) . '</button></p></form>';
		$html    .= '<h2>' . esc_html__( 'Import', 'course-captain' ) . '</h2><form method="post" enctype="multipart/form-data" action="' . $post_url . '"><input type="hidden" name="action" value="' . esc_attr( self::IMPORT ) . '">' . wp_nonce_field( self::IMPORT, '_wpnonce', true, false )
			. '<p><label for="ccap-import-file">' . esc_html__( 'Settings file (JSON, at most 256 KB)', 'course-captain' ) . '</label> <input type="file" id="ccap-import-file" name="file" accept="application/json,.json"></p>'
			. '<p><button type="submit" class="button">' . esc_html__( 'Show preview', 'course-captain' ) . '</button></p></form>';
		return $html . '</div>';
	}

	/**
	 * Preview with diff and confirmations (IM-1, IM-4).
	 *
	 * @param int  $user_id        User.
	 * @param bool $reason_invalid The reason for longer retention periods was missing (E-245).
	 * @return string
	 */
	private static function preview( int $user_id, bool $reason_invalid = false ): string {
		$data = get_transient( self::PENDING . $user_id );
		if ( ! is_array( $data ) ) {
			return '';
		}
		try {
			$report = SettingsTransfer::import(
				Actor::user( $user_id ),
				$data,
				array(
					'confirmed'       => self::all_risk_fields( Actor::user( $user_id ), $data ),
					'list_unreasoned' => true,
				)
			);
		} catch ( CourseException $e ) {
			return '';
		}
		$html = '<h2>' . esc_html__( 'Preview', 'course-captain' ) . '</h2>';
		if ( in_array( 'other_site', $report['notices'], true ) ) {
			$html .= '<div class="notice notice-warning inline"><p>' . esc_html__( 'Note: the file comes from another website.', 'course-captain' ) . '</p></div>';
		}
		if ( array() !== $report['addon'] ) {
			$count = count( $report['addon'] );
			/* translators: %d: number of settings */
			$html .= '<div class="notice notice-info inline"><p>' . esc_html( sprintf( _n( 'The file contains %d setting of an extension. It stays unchanged here; the extension imports it.', 'The file contains %d settings of an extension. They stay unchanged here; the extension imports them.', $count, 'course-captain' ), $count ) ) . '</p></div>';
		}
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::IMPORT ) . '"><input type="hidden" name="apply" value="1">' . wp_nonce_field( self::IMPORT, '_wpnonce', true, false );
		foreach ( $report['tabs'] as $tab => $entry ) {
			$label = SettingsFields::tab_label( $tab );
			$html .= '<fieldset class="ccap-field"><legend><label><input type="checkbox" name="tabs[]" value="' . esc_attr( $tab ) . '"' . checked( array() !== $entry['changed'], true, false ) . '> ' . esc_html( $label ) . '</label></legend>';
			$rows  = '';
			foreach ( $entry['changed'] as $change ) {
				$text  = (string) $change['field'] . ': ' . (string) wp_json_encode( $change['before'] ) . ' → ' . (string) wp_json_encode( $change['after'] );
				$rows .= '<li>' . esc_html( $text );
				if ( array() !== (array) $change['risks'] ) {
					$id    = 'ccap-confirm-' . $tab . '-' . (string) $change['field'];
					$risk  = RiskDialog::text( (string) $change['risks'][0], array(), array() );
					$rows .= '<br><label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="confirmed[]" value="' . esc_attr( $tab . '.' . (string) $change['field'] ) . '"> ' . esc_html( $risk['action'] ) . '</label> <span class="description">' . esc_html( $risk['text'] ) . '</span>';
				}
				if ( array() !== (array) ( $change['extended'] ?? array() ) ) {
					$rows .= self::retention_reason_field( (array) $change['extended'], $reason_invalid );
				}
				$rows .= '</li>';
			}
			foreach ( $entry['skipped'] as $skip ) {
				if ( 'confirmation' !== $skip['reason'] ) {
					/* translators: 1: field, 2: reason key */
					$rows .= '<li>' . esc_html( sprintf( __( '%1$s: not imported (%2$s)', 'course-captain' ), (string) $skip['field'], (string) $skip['reason'] ) ) . '</li>';
				}
			}
			foreach ( $entry['errors'] as $field => $rule ) {
				/* translators: 1: field, 2: rule */
				$rows .= '<li>' . esc_html( sprintf( __( 'Error: %1$s: invalid value (%2$s); this tab is not imported.', 'course-captain' ), (string) $field, (string) $rule ) ) . '</li>';
			}
			$html .= '' === $rows ? '<p>' . esc_html__( 'No changes.', 'course-captain' ) . '</p>' : '<ul>' . $rows . '</ul>';
			$html .= '</fieldset>';
		}
		foreach ( $report['surfaces'] as $key => $entry ) {
			$html .= self::surface_preview( (string) $key, $entry );
		}
		return $html . '<p><button type="submit" class="button button-primary">' . esc_html__( 'Import selected tabs', 'course-captain' ) . '</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Cancel', 'course-captain' ) . '</a></p></form>';
	}

	/**
	 * E-245: reason input for retention periods above their default.
	 *
	 * @param array $keys    Extended sub-keys.
	 * @param bool  $invalid Missing on the last try.
	 * @phpstan-param array<mixed> $keys
	 * @return string
	 */
	private static function retention_reason_field( array $keys, bool $invalid ): string {
		$labels = array_map( static fn( $key ): string => SettingsFields::ui( 'privacy', 'retention.' . (string) $key )['label'], $keys );
		/* translators: %s: list of retention periods */
		$help = sprintf( __( 'Longer than the suggested default: %s. A reason is required; it is stored in the change log.', 'course-captain' ), implode( ', ', $labels ) );
		return '<br><label for="ccap-import-retention-reason">' . esc_html__( 'Reason for longer periods', 'course-captain' ) . '</label><br><textarea id="ccap-import-retention-reason" name="retention_reason" rows="2" class="large-text" maxlength="500" aria-describedby="ccap-import-retention-reason-help"' . ( $invalid ? ' aria-invalid="true"' : '' ) . '></textarea><span id="ccap-import-retention-reason-help" class="description">' . esc_html( $help ) . '</span>';
	}

	/**
	 * All fields of a file, so the preview lists risk fields as changes.
	 *
	 * @param Actor                $actor Actor.
	 * @param array<string, mixed> $data  File.
	 * @return list<string>
	 */
	private static function all_risk_fields( Actor $actor, array $data ): array {
		unset( $actor );
		$out = array();
		foreach ( (array) $data['tabs'] as $tab => $content ) {
			foreach ( array_keys( (array) ( $content['values'] ?? array() ) ) as $field ) {
				$out[] = $tab . '.' . $field;
			}
		}
		foreach ( array_keys( SettingsTransfer::providers() ) as $key ) {
			$out[] = $key . '.*';
		}
		return $out;
	}

	/**
	 * Opt-in boxes of the export for opt-in surfaces the user may export (EX-1).
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	private static function surface_options( int $user_id ): string {
		$html = '';
		foreach ( SettingsTransfer::providers() as $key => $provider ) {
			if ( ! $provider->opt_in() || ! $provider->can_export( Actor::user( $user_id ) ) ) {
				continue;
			}
			/* translators: %s: name of a settings area, e.g. "Roles & permissions" */
			$html .= '<p><label><input type="checkbox" name="include[]" value="' . esc_attr( $key ) . '"> ' . esc_html( sprintf( __( 'Include: %s', 'course-captain' ), $provider->label() ) ) . '</label></p>';
		}
		return $html;
	}

	/**
	 * Preview of one surface (roles, locks, …) with its own selection box.
	 *
	 * @param string               $key   Provider key.
	 * @param array<string, mixed> $entry Plan with label.
	 * @return string
	 */
	private static function surface_preview( string $key, array $entry ): string {
		$html = '<fieldset class="ccap-field"><legend><label><input type="checkbox" name="surfaces[]" value="' . esc_attr( $key ) . '"' . checked( array() !== $entry['changed'] && array() === $entry['errors'], true, false ) . '> ' . esc_html( (string) $entry['label'] ) . '</label></legend>';
		$rows = '';
		foreach ( (array) $entry['changed'] as $change ) {
			$item  = (string) $change['item'];
			$text  = isset( $change['summary'] ) ? (string) $change['summary'] : $item . ': ' . (string) wp_json_encode( $change['before'] ) . ' → ' . (string) wp_json_encode( $change['after'] );
			$rows .= '<li>' . esc_html( $text );
			if ( array() !== (array) $change['risks'] ) {
				$id    = 'ccap-confirm-' . $key . '-' . sanitize_key( $item );
				$risk  = RiskDialog::text( (string) $change['risks'][0], array(), array() );
				$rows .= '<br><label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="confirmed[]" value="' . esc_attr( $key . '.' . $item ) . '"> ' . esc_html( $risk['action'] ) . '</label> <span class="description">' . esc_html( $risk['text'] ) . '</span>';
			}
			$rows .= '</li>';
		}
		foreach ( (array) $entry['skipped'] as $skip ) {
			if ( 'confirmation' !== $skip['reason'] ) {
				/* translators: 1: field, 2: reason key */
				$rows .= '<li>' . esc_html( sprintf( __( '%1$s: not imported (%2$s)', 'course-captain' ), (string) $skip['item'], (string) $skip['reason'] ) ) . '</li>';
			}
		}
		foreach ( (array) $entry['errors'] as $item => $rule ) {
			/* translators: 1: item, 2: rule */
			$rows .= '<li>' . esc_html( sprintf( __( 'Error: %1$s: invalid value (%2$s); this area is not imported.', 'course-captain' ), (string) $item, (string) $rule ) ) . '</li>';
		}
		$html .= '' === $rows ? '<p>' . esc_html__( 'No changes.', 'course-captain' ) . '</p>' : '<ul>' . $rows . '</ul>';
		return $html . '</fieldset>';
	}
}
