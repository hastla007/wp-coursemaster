<?php
/**
 * Mail text editor per mail type and language (SET-CC-04; R0-27 §2.4,
 * §3.9, §3.10).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Notification\MailTemplates;
use CourseCaptain\Notification\NotificationPrefs;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Notification\NotificationTypes;
use CourseCaptain\Notification\TemplateError;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden page below the settings (#19), works without JavaScript
 * (admin-post → redirect → GET). Overview: every type with the state of
 * every installed language (own text / built-in text). Edit form: subject,
 * HTML and text body with the placeholder list of the type; buttons
 * "Save", "Preview" and "Send test mail to me" work on the entered text
 * (preview and test mail never save). Invalid input saves nothing and
 * comes back with an error summary and field-near messages that name the
 * allowed placeholders. "Restore built-in text" has its own form with a
 * confirmation step. Export downloads all own texts as JSON; import shows
 * a preview per entry before anything is written. All writes go through
 * {@see MailTemplates} (audit `mail_template_saved`, `mail_template_reset`,
 * `mail_templates_imported`).
 */
final class MailTextsScreen {

	public const SLUG   = 'course-captain-mail-texts';
	public const ACTION = 'ccap_mail_text';
	public const EXPORT = 'ccap_mail_text_export';
	public const DRAFT  = 'ccap_mail_text_draft_';
	public const IMPORT = 'ccap_mail_text_import_';
	public const TTL    = 600;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				AdminUi::settings_assets( add_submenu_page( '', __( 'Mail texts', 'course-captain' ), __( 'Mail texts', 'course-captain' ), Settings::CAP, self::SLUG, array( self::class, 'render' ) ) );
			},
			19
		);
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_post_' . self::EXPORT, array( self::class, 'handle_export' ) );
	}

	/**
	 * Page URL.
	 *
	 * @param array<string, string> $args Extra query args.
	 * @return string
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
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
	 * Admin-post entry.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$raw  = '';
		$tmp  = isset( $_FILES['file']['tmp_name'] ) ? sanitize_text_field( (string) $_FILES['file']['tmp_name'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process().
		$size = isset( $_FILES['file']['size'] ) ? absint( $_FILES['file']['size'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process().
		if ( '' !== $tmp && is_uploaded_file( $tmp ) && $size <= MailTemplates::IMPORT_MAX_BYTES ) {
			$raw = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Uploaded temp file.
		}
		$out  = self::process( wp_unslash( $_POST ), $raw, get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process().
		$args = array( 'ccap_result' => $out['result'] );
		if ( '' !== $out['type'] ) {
			$args['type']   = $out['type'];
			$args['locale'] = $out['locale'];
		}
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	/**
	 * Admin-post: download of all own texts (no server copy).
	 *
	 * @return void
	 */
	public static function handle_export(): void {
		check_admin_referer( self::EXPORT );
		try {
			$data = MailTemplates::export( Actor::user( get_current_user_id() ) );
		} catch ( CourseException $e ) {
			wp_safe_redirect( self::url( array( 'ccap_result' => $e->error_code ) ) );
			exit;
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="course-captain-mail-texts-' . gmdate( 'Ymd' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		exit;
	}

	/**
	 * Testable core of every action (`do` = save, preview, test, reset, import, import_apply, import_cancel).
	 *
	 * @param array<string, mixed> $post    Unslashed POST.
	 * @param string               $raw     Uploaded import file ('' otherwise).
	 * @param int                  $user_id User.
	 * @return array{result: string, type: string, locale: string}
	 */
	public static function process( array $post, string $raw, int $user_id ): array {
		$do     = isset( $post['do'] ) ? sanitize_key( (string) $post['do'] ) : '';
		$type   = isset( $post['type'] ) ? sanitize_key( (string) $post['type'] ) : '';
		$locale = isset( $post['locale'] ) ? (string) preg_replace( '/[^A-Za-z0-9_]/', '', (string) $post['locale'] ) : '';
		$out    = array(
			'result' => 'ok',
			'type'   => NotificationTypes::exists( $type ) ? $type : '',
			'locale' => $locale,
		);
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::ACTION ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) ) {
			$out['result'] = 'ccap_forbidden';
			return $out;
		}
		$actor = Actor::user( $user_id );
		if ( in_array( $do, array( 'import', 'import_apply', 'import_cancel' ), true ) ) {
			$out['type']   = '';
			$out['result'] = self::process_import( $actor, $do, $raw );
			return $out;
		}
		if ( '' === $out['type'] || ! in_array( $locale, MailTemplates::locales(), true ) ) {
			$out['result'] = 'ccap_invalid_param';
			$out['type']   = '';
			return $out;
		}
		try {
			if ( 'reset' === $do ) {
				if ( empty( $post['confirm'] ) ) {
					$out['result'] = 'reset_confirm';
					return $out;
				}
				delete_transient( self::DRAFT . $user_id );
				$out['result'] = MailTemplates::reset( $actor, $type, $locale ) ? 'reset' : 'reset_none';
				return $out;
			}
			$check = MailTemplates::check( $type, (string) ( $post['subject'] ?? '' ), (string) ( $post['body_html'] ?? '' ), (string) ( $post['body_text'] ?? '' ) );
			$draft = array(
				'type'   => $type,
				'locale' => $locale,
				'values' => array(
					'subject'   => sanitize_text_field( (string) ( $post['subject'] ?? '' ) ),
					'body_html' => (string) $check['values']['body_html'],
					'body_text' => sanitize_textarea_field( (string) ( $post['body_text'] ?? '' ) ),
				),
				'errors' => $check['errors'],
			);
			if ( array() !== $check['errors'] ) {
				set_transient( self::DRAFT . $user_id, $draft, self::TTL );
				$out['result'] = 'invalid';
				return $out;
			}
			if ( 'preview' === $do ) {
				$draft['preview'] = true;
				set_transient( self::DRAFT . $user_id, $draft, self::TTL );
				$out['result'] = 'preview';
				return $out;
			}
			if ( 'test' === $do ) {
				set_transient( self::DRAFT . $user_id, $draft, self::TTL );
				$sent          = NotificationService::current()->send_template_test( $actor, $type, $locale, $check['values'] );
				$out['result'] = 'test_' . $sent['status'];
				return $out;
			}
			$before = MailTemplates::stored( $type )[ $locale ]['rev'] ?? 0;
			$rev    = MailTemplates::save( $actor, $type, $locale, $check['values']['subject'], $check['values']['body_html'], $check['values']['body_text'] );
			delete_transient( self::DRAFT . $user_id );
			$out['result'] = $rev === $before ? 'unchanged' : 'saved';
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
		}
		return $out;
	}

	/**
	 * Import steps: upload → preview (transient) → apply or cancel.
	 *
	 * @param Actor  $actor Admin.
	 * @param string $step  Step.
	 * @param string $raw   File content.
	 * @return string Result code.
	 */
	private static function process_import( Actor $actor, string $step, string $raw ): string {
		$key = self::IMPORT . $actor->user_id;
		try {
			if ( 'import_cancel' === $step ) {
				delete_transient( $key );
				return 'import_cancelled';
			}
			if ( 'import' === $step ) {
				$data = MailTemplates::parse( $raw );
				MailTemplates::import( $actor, $data );
				set_transient( $key, $data, self::TTL );
				return 'import_preview';
			}
			$data = get_transient( $key );
			delete_transient( $key );
			if ( ! is_array( $data ) ) {
				return 'ccap_confirmation_expired';
			}
			$plan = MailTemplates::import( $actor, $data, true );
			return array() === array_filter( $plan, static fn( array $e ): bool => in_array( $e['status'], array( 'new', 'changed' ), true ) ) ? 'import_nothing' : 'imported';
		} catch ( CourseException $e ) {
			return 'ccap_rate_limited' === $e->error_code ? $e->error_code : 'import_invalid';
		}
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
			return '<div class="wrap"><h1>' . esc_html__( 'Mail texts', 'course-captain' ) . '</h1><p>' . esc_html__( 'You are not allowed to change settings.', 'course-captain' ) . '</p></div>';
		}
		$type   = isset( $request['type'] ) ? sanitize_key( (string) $request['type'] ) : '';
		$locale = isset( $request['locale'] ) ? (string) preg_replace( '/[^A-Za-z0-9_]/', '', (string) $request['locale'] ) : '';
		$result = isset( $request['ccap_result'] ) ? sanitize_key( (string) $request['ccap_result'] ) : '';
		$html   = '<div class="wrap ccap-mail-texts"><h1>' . esc_html__( 'Mail texts', 'course-captain' ) . '</h1>';
		$html  .= '<p><a href="' . esc_url( SettingsScreen::url( 'emails' ) ) . '">' . esc_html__( 'Back to the email settings', 'course-captain' ) . '</a></p>';
		$html  .= self::message( $result );
		if ( NotificationTypes::exists( $type ) && in_array( $locale, MailTemplates::locales(), true ) ) {
			$html .= 'reset_confirm' === $result ? self::reset_page( $type, $locale ) : self::editor( $type, $locale, $user_id );
		} else {
			$html .= self::overview( $user_id );
		}
		return $html . '</div>';
	}

	/**
	 * Readable name of a mail type.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public static function type_label( string $type ): string {
		$labels = array(
			'seat_invitation'       => __( 'Group invitation', 'course-captain' ),
			'certificate_corrected' => __( 'Certificate corrected', 'course-captain' ),
			'certificate_revoked'   => __( 'Certificate revoked', 'course-captain' ),
			'attempt_voided'        => __( 'Exam attempt voided', 'course-captain' ),
			'attempt_annulled'      => __( 'Exam result annulled', 'course-captain' ),
			'test_mail'             => __( 'Test mail', 'course-captain' ),
		);
		return $labels[ $type ] ?? NotificationPrefs::label( $type );
	}

	/**
	 * Result message in a status region (errors as alert).
	 *
	 * @param string $result Result code.
	 * @return string
	 */
	private static function message( string $result ): string {
		$alert = false;
		switch ( $result ) {
			case 'saved':
				$text = __( 'Mail text saved. It is used from the next message on.', 'course-captain' );
				break;
			case 'unchanged':
				$text = __( 'No changes – nothing saved.', 'course-captain' );
				break;
			case 'preview':
				$text = __( 'Preview below. The text is not saved yet.', 'course-captain' );
				break;
			case 'test_sent':
				$text = __( 'Test mail sent to your own address. The text is not saved yet.', 'course-captain' );
				break;
			case 'test_caught':
				$text = __( 'Test mode is active: the test mail went to the catch address. The text is not saved yet.', 'course-captain' );
				break;
			case 'test_suppressed':
				$text  = __( 'No test mail was sent: outgoing mails are switched off or held (see “Test mode” in the email settings).', 'course-captain' );
				$alert = true;
				break;
			case 'test_failed':
				$text  = __( 'Warning: the test mail could not be sent. A frequent cause: the server may not send mails. An SMTP plugin helps.', 'course-captain' );
				$alert = true;
				break;
			case 'reset':
				$text = __( 'Built-in text restored for this language.', 'course-captain' );
				break;
			case 'reset_none':
				$text = __( 'This language already uses the built-in text.', 'course-captain' );
				break;
			case 'invalid':
				return '';
			case 'import_preview':
				$text = __( 'Check the import below and confirm it. Nothing has been changed yet.', 'course-captain' );
				break;
			case 'imported':
				$text = __( 'Mail texts imported.', 'course-captain' );
				break;
			case 'import_nothing':
				$text = __( 'The file contained no new or changed mail texts – nothing imported.', 'course-captain' );
				break;
			case 'import_cancelled':
				$text = __( 'Import cancelled. Nothing was changed.', 'course-captain' );
				break;
			case 'import_invalid':
				$text  = __( 'Error: this is not a Course Captain mail text file (JSON, at most 256 KB).', 'course-captain' );
				$alert = true;
				break;
			case 'ccap_rate_limited':
				$text  = __( 'Too many attempts in the last hour. Try again later.', 'course-captain' );
				$alert = true;
				break;
			case 'ccap_invalid_nonce':
				$text  = __( 'The form has expired. Reload the page and try again.', 'course-captain' );
				$alert = true;
				break;
			case 'ccap_forbidden':
				$text  = __( 'You are not allowed to change these settings.', 'course-captain' );
				$alert = true;
				break;
			case 'ccap_confirmation_expired':
				$text  = __( 'The import has expired. Upload the file again.', 'course-captain' );
				$alert = true;
				break;
			case 'ccap_invalid_param':
				$text  = __( 'Unknown message or language.', 'course-captain' );
				$alert = true;
				break;
			default:
				return '';
		}
		return '<div id="ccap-mail-message" class="notice notice-' . ( $alert ? 'error' : 'success' ) . '" role="' . ( $alert ? 'alert' : 'status' ) . '" tabindex="-1" data-ccap-focus="1"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Overview of all types and languages, export and import.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	private static function overview( int $user_id ): string {
		$locales = MailTemplates::locales();
		$head    = '<tr><th scope="col">' . esc_html__( 'Message', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Kind', 'course-captain' ) . '</th>';
		foreach ( $locales as $locale ) {
			$head .= '<th scope="col" lang="' . esc_attr( str_replace( '_', '-', $locale ) ) . '">' . esc_html( $locale ) . '</th>';
		}
		$rows = '';
		foreach ( NotificationTypes::all() as $type ) {
			$stored = MailTemplates::stored( $type );
			$rows  .= '<tr><th scope="row">' . esc_html( self::type_label( $type ) ) . '</th><td>' . esc_html( NotificationTypes::is_informational( $type ) ? __( 'informational', 'course-captain' ) : __( 'required', 'course-captain' ) ) . '</td>';
			foreach ( $locales as $locale ) {
				$state = isset( $stored[ $locale ] ) ? __( 'Own text', 'course-captain' ) : __( 'Built-in text', 'course-captain' );
				/* translators: 1: message name, 2: language code */
				$aria  = sprintf( __( 'Edit “%1$s” (%2$s)', 'course-captain' ), self::type_label( $type ), $locale );
				$rows .= '<td>' . esc_html( $state ) . ' · <a href="' . esc_url(
					self::url(
						array(
							'type'   => $type,
							'locale' => $locale,
						)
					)
				) . '" aria-label="' . esc_attr( $aria ) . '">' . esc_html__( 'Edit', 'course-captain' ) . '</a></td>';
			}
			$rows .= '</tr>';
		}
		$html    = '<p>' . esc_html__( 'Every message exists as built-in text in all languages. Adapt a text per language; a learner gets the text in their profile language, else the same language in another variant, else the text of the website language, else the built-in text.', 'course-captain' ) . '</p>';
		$html   .= AdminUi::scroll_table( '<table class="widefat striped ccap-mail-overview"><caption>' . esc_html__( 'Messages and languages', 'course-captain' ) . '</caption><thead>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table>', __( 'Messages and languages', 'course-captain' ) );
		$html   .= '<h2>' . esc_html__( 'Export and import', 'course-captain' ) . '</h2>';
		$html   .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-inline-form"><input type="hidden" name="action" value="' . esc_attr( self::EXPORT ) . '">' . wp_nonce_field( self::EXPORT, '_wpnonce', true, false )
			. '<button type="submit" class="button">' . esc_html__( 'Export own mail texts (JSON)', 'course-captain' ) . '</button></form>';
		$pending = get_transient( self::IMPORT . $user_id );
		if ( is_array( $pending ) ) {
			return $html . self::import_preview( Actor::user( $user_id ), $pending );
		}
		$html .= '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="do" value="import">' . wp_nonce_field( self::ACTION, '_wpnonce', true, false )
			. '<p><label for="ccap-mail-import-file">' . esc_html__( 'Import file (JSON, at most 256 KB)', 'course-captain' ) . '</label><br><input type="file" id="ccap-mail-import-file" name="file" accept="application/json,.json" aria-describedby="ccap-mail-import-help"></p>'
			. '<p id="ccap-mail-import-help" class="description">' . esc_html__( 'You see what changes before anything is written. Texts for languages that are not installed are skipped.', 'course-captain' ) . '</p>'
			. '<p><button type="submit" class="button">' . esc_html__( 'Check import file', 'course-captain' ) . '</button></p></form>';
		return $html;
	}

	/**
	 * Import preview with apply/cancel.
	 *
	 * @param Actor                $actor Admin.
	 * @param array<string, mixed> $data  Parsed file.
	 * @return string
	 */
	private static function import_preview( Actor $actor, array $data ): string {
		try {
			$plan = MailTemplates::import( $actor, $data );
		} catch ( CourseException $e ) {
			$plan = array();
		}
		$labels = array(
			'new'            => __( 'new', 'course-captain' ),
			'changed'        => __( 'changed', 'course-captain' ),
			'unchanged'      => __( 'unchanged', 'course-captain' ),
			'unknown_type'   => __( 'skipped: unknown message', 'course-captain' ),
			'unknown_locale' => __( 'skipped: language not installed', 'course-captain' ),
			'invalid'        => __( 'skipped: invalid text or placeholder', 'course-captain' ),
		);
		$rows   = '';
		foreach ( $plan as $entry ) {
			$rows .= '<tr><th scope="row">' . esc_html( NotificationTypes::exists( $entry['type'] ) ? self::type_label( $entry['type'] ) : $entry['type'] ) . '</th><td>' . esc_html( $entry['locale'] ) . '</td><td>' . esc_html( $labels[ $entry['status'] ] ?? $entry['status'] ) . '</td></tr>';
		}
		$form = static fn( string $step, string $label, string $css ): string => '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-inline-form"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="do" value="' . esc_attr( $step ) . '">' . wp_nonce_field( self::ACTION, '_wpnonce', true, false ) . '<button type="submit" class="' . esc_attr( $css ) . '">' . esc_html( $label ) . '</button></form> ';
		return '<section aria-labelledby="ccap-mail-import-title"><h3 id="ccap-mail-import-title">' . esc_html__( 'Import preview', 'course-captain' ) . '</h3>'
			. '<table class="widefat striped"><caption>' . esc_html__( 'New and changed texts replace the current text of that language; all others stay.', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Message', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Language', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Result', 'course-captain' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
			. '<p>' . $form( 'import_apply', __( 'Import mail texts', 'course-captain' ), 'button button-primary' ) . $form( 'import_cancel', __( 'Cancel', 'course-captain' ), 'button' ) . '</p></section>';
	}

	/**
	 * Edit form with placeholders, preview, test mail and reset.
	 *
	 * @param string $type    Type.
	 * @param string $locale  Locale.
	 * @param int    $user_id User.
	 * @return string
	 */
	private static function editor( string $type, string $locale, int $user_id ): string {
		$draft = get_transient( self::DRAFT . $user_id );
		$draft = is_array( $draft ) && ( $draft['type'] ?? '' ) === $type && ( $draft['locale'] ?? '' ) === $locale ? $draft : null;
		if ( null !== $draft ) {
			delete_transient( self::DRAFT . $user_id );
		}
		$stored = MailTemplates::stored( $type )[ $locale ] ?? null;
		$values = null !== $draft ? (array) $draft['values'] : ( $stored ?? self::builtin_values( $type, $locale ) );
		$errors = null !== $draft ? (array) $draft['errors'] : array();
		$vars   = NotificationTypes::variables( $type );
		/* translators: 1: message name, 2: language code */
		$html  = '<h2>' . esc_html( sprintf( __( '%1$s (%2$s)', 'course-captain' ), self::type_label( $type ), $locale ) ) . '</h2>';
		$html .= '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'All messages', 'course-captain' ) . '</a></p>';
		$html .= '<p>' . esc_html( null === $stored ? __( 'This language currently uses the built-in text. Saving creates an own text.', 'course-captain' ) : __( 'This language uses an own text.', 'course-captain' ) ) . '</p>';
		$html .= self::summary( $errors );
		$list  = '<ul class="ccap-placeholders">';
		foreach ( $vars as $var ) {
			$list .= '<li><code>{{' . esc_html( $var ) . '}}</code></li>';
		}
		$list .= '</ul>';
		$html .= '<h3 id="ccap-mail-placeholders">' . esc_html__( 'Allowed placeholders', 'course-captain' ) . '</h3>' . $list;
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="ccap-mail-form" data-ccap-dirty-form="1"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="type" value="' . esc_attr( $type ) . '"><input type="hidden" name="locale" value="' . esc_attr( $locale ) . '">' . wp_nonce_field( self::ACTION, '_wpnonce', true, false );
		$html .= self::text_field( 'subject', __( 'Subject', 'course-captain' ), sprintf( /* translators: %d: maximum number of characters */ __( 'At most %d characters, no line breaks.', 'course-captain' ), MailTemplates::SUBJECT_MAX ), (string) ( $values['subject'] ?? '' ), $errors['subject'] ?? null, $vars, false );
		$html .= self::text_field( 'body_html', __( 'Text (HTML)', 'course-captain' ), __( 'Allowed: paragraphs, line breaks, bold, italic, links, lists and headings (h2, h3). Placeholders are inserted as plain text, never as HTML.', 'course-captain' ), (string) ( $values['body_html'] ?? '' ), $errors['body_html'] ?? null, $vars, true );
		$html .= self::text_field( 'body_text', __( 'Text version (optional)', 'course-captain' ), __( 'For mail programs without HTML and for the setting “Text only”. Leave empty to create it from the HTML text.', 'course-captain' ), (string) ( $values['body_text'] ?? '' ), $errors['body_text'] ?? null, $vars, true );
		$html .= '<p class="submit"><button type="submit" name="do" value="save" class="button button-primary">' . esc_html__( 'Save', 'course-captain' ) . '</button> <button type="submit" name="do" value="preview" class="button">' . esc_html__( 'Preview', 'course-captain' ) . '</button> ';
		$user  = get_userdata( $user_id );
		/* translators: %s: email address of the current user */
		$html .= '<button type="submit" name="do" value="test" class="button" aria-describedby="ccap-mail-test-help">' . esc_html( sprintf( __( 'Send test mail to %s', 'course-captain' ), $user instanceof \WP_User ? $user->user_email : '' ) ) . '</button></p>';
		$html .= '<p id="ccap-mail-test-help" class="description">' . esc_html__( 'Preview and test mail use the entered text with sample data and do not save it. At most 5 test mails per hour.', 'course-captain' ) . '</p></form>';
		if ( null !== $stored ) {
			$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="do" value="reset"><input type="hidden" name="type" value="' . esc_attr( $type ) . '"><input type="hidden" name="locale" value="' . esc_attr( $locale ) . '">' . wp_nonce_field( self::ACTION, '_wpnonce', true, false )
				. '<p><button type="submit" class="button">' . esc_html__( 'Restore built-in text …', 'course-captain' ) . '</button></p></form>';
		}
		if ( null !== $draft && ! empty( $draft['preview'] ) ) {
			$html .= self::preview( Actor::user( $user_id ), $type, $locale, (array) $draft['values'] );
		}
		return $html;
	}

	/**
	 * Built-in text of a type in a language as editable values.
	 *
	 * @param string $type   Type.
	 * @param string $locale Locale.
	 * @return array{subject: string, body_html: string, body_text: string}
	 */
	private static function builtin_values( string $type, string $locale ): array {
		$switched = switch_to_locale( $locale );
		try {
			$builtin = MailTemplates::builtin( $type );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
		return array(
			'subject'   => $builtin['subject'],
			'body_html' => '<p>' . implode( "</p>\n<p>", array_map( 'esc_html', $builtin['paragraphs'] ) ) . '</p>',
			'body_text' => '',
		);
	}

	/**
	 * Error summary (role=alert, links to the fields).
	 *
	 * @param array<string, mixed> $errors Errors.
	 * @return string
	 */
	private static function summary( array $errors ): string {
		if ( array() === $errors ) {
			return '';
		}
		$labels = array(
			'subject'   => __( 'Subject', 'course-captain' ),
			'body_html' => __( 'Text (HTML)', 'course-captain' ),
			'body_text' => __( 'Text version (optional)', 'course-captain' ),
		);
		$items  = '';
		foreach ( array_keys( $errors ) as $field ) {
			$items .= '<li><a href="#ccap-mail-' . esc_attr( str_replace( '_', '-', (string) $field ) ) . '">' . esc_html( $labels[ $field ] ?? (string) $field ) . '</a></li>';
		}
		/* translators: %d: number of fields with errors */
		$title = sprintf( _n( '%d entry must be corrected:', '%d entries must be corrected:', count( $errors ), 'course-captain' ), count( $errors ) );
		return '<div id="ccap-error-summary" class="notice notice-error ccap-error-summary" role="alert" tabindex="-1" data-ccap-focus="1"><p><strong>' . esc_html( $title ) . '</strong></p><ul>' . $items . '</ul></div>';
	}

	/**
	 * One input with help and field-near error.
	 *
	 * @param string                    $field    Field.
	 * @param string                    $label    Label.
	 * @param string                    $help     Help.
	 * @param string                    $value    Value.
	 * @param array<string, mixed>|null $error    Error `{rule, unknown}`.
	 * @param array                     $vars     Allowed placeholders.
	 * @param bool                      $textarea Multi-line.
	 * @phpstan-param list<string> $vars
	 * @return string
	 */
	private static function text_field( string $field, string $label, string $help, string $value, ?array $error, array $vars, bool $textarea ): string {
		$id       = 'ccap-mail-' . str_replace( '_', '-', $field );
		$describe = $id . '-help ccap-mail-placeholders';
		$err      = '';
		if ( null !== $error ) {
			$describe .= ' ' . $id . '-error';
			$err       = '<p id="' . esc_attr( $id . '-error' ) . '" class="ccap-field-error"><strong>' . esc_html( self::error_text( $label, $error, $vars ) ) . '</strong></p>';
		}
		$aria    = ' aria-describedby="' . esc_attr( $describe ) . '"' . ( null !== $error ? ' aria-invalid="true"' : '' );
		$control = $textarea
			? '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $field ) . '" rows="' . ( 'body_html' === $field ? '10' : '6' ) . '" class="large-text code"' . $aria . '>' . esc_textarea( $value ) . '</textarea>'
			: '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '" class="large-text" maxlength="' . esc_attr( (string) MailTemplates::SUBJECT_MAX ) . '"' . $aria . '>';
		return '<div class="ccap-field' . ( null !== $error ? ' ccap-field-invalid' : '' ) . '"><label for="' . esc_attr( $id ) . '" class="ccap-field-label">' . esc_html( $label ) . '</label><br>' . $control
			. '<p id="' . esc_attr( $id . '-help' ) . '" class="description ccap-field-help">' . esc_html( $help ) . '</p>' . $err . '</div>';
	}

	/**
	 * Field-near message.
	 *
	 * @param string               $label Field label.
	 * @param array<string, mixed> $error Error.
	 * @param array                $vars  Allowed placeholders.
	 * @phpstan-param list<string> $vars
	 * @return string
	 */
	private static function error_text( string $label, array $error, array $vars ): string {
		switch ( (string) ( $error['rule'] ?? '' ) ) {
			case 'required':
				/* translators: %s: field label */
				return sprintf( __( 'Error: %s: required field.', 'course-captain' ), $label );
			case 'length':
				/* translators: %s: field label */
				return sprintf( __( 'Error: %s: the text is too long.', 'course-captain' ), $label );
			case 'placeholder':
				$wrap = static fn( string $name ): string => '{{' . $name . '}}';
				return sprintf(
					/* translators: 1: field label, 2: unknown placeholders, 3: allowed placeholders */
					__( 'Error: %1$s: unknown placeholder %2$s. Allowed for this message: %3$s.', 'course-captain' ),
					$label,
					implode( ', ', array_map( $wrap, array_map( 'strval', (array) ( $error['unknown'] ?? array() ) ) ) ),
					implode( ', ', array_map( $wrap, $vars ) )
				);
		}
		/* translators: %s: field label */
		return sprintf( __( 'Error: %s: check the value.', 'course-captain' ), $label );
	}

	/**
	 * Preview with sample data: subject, HTML (sandboxed frame) and text version.
	 *
	 * @param Actor                $actor  Admin.
	 * @param string               $type   Type.
	 * @param string               $locale Locale.
	 * @param array<string, mixed> $values Draft values.
	 * @return string
	 */
	private static function preview( Actor $actor, string $type, string $locale, array $values ): string {
		try {
			$mail = MailTemplates::preview(
				$actor,
				$type,
				$locale,
				array(
					'subject'   => (string) ( $values['subject'] ?? '' ),
					'body_html' => (string) ( $values['body_html'] ?? '' ),
					'body_text' => (string) ( $values['body_text'] ?? '' ),
				)
			);
		} catch ( TemplateError | CourseException $e ) {
			return '<p role="alert">' . esc_html__( 'The preview could not be created. Check the placeholders.', 'course-captain' ) . '</p>';
		}
		return '<section aria-labelledby="ccap-mail-preview-title" class="ccap-mail-preview"><h3 id="ccap-mail-preview-title">' . esc_html__( 'Preview with sample data', 'course-captain' ) . '</h3>'
			. '<p><strong>' . esc_html__( 'Subject:', 'course-captain' ) . '</strong> <span lang="' . esc_attr( $mail['lang'] ) . '">' . esc_html( $mail['subject'] ) . '</span></p>'
			. '<iframe title="' . esc_attr__( 'HTML version of the mail', 'course-captain' ) . '" sandbox="" srcdoc="' . esc_attr( $mail['html'] ) . '" style="inline-size:100%;block-size:24rem;border:1px solid #8c8f94"></iframe>'
			. '<h4>' . esc_html__( 'Text version', 'course-captain' ) . '</h4><pre lang="' . esc_attr( $mail['lang'] ) . '" dir="' . esc_attr( $mail['dir'] ) . '" style="white-space:pre-wrap">' . esc_html( $mail['text'] ) . '</pre></section>';
	}

	/**
	 * Confirmation page of "Restore built-in text".
	 *
	 * @param string $type   Type.
	 * @param string $locale Locale.
	 * @return string
	 */
	private static function reset_page( string $type, string $locale ): string {
		/* translators: 1: message name, 2: language code */
		$title = sprintf( __( 'Restore the built-in text of “%1$s” (%2$s)?', 'course-captain' ), self::type_label( $type ), $locale );
		return '<section aria-labelledby="ccap-mail-reset-title"><h2 id="ccap-mail-reset-title" tabindex="-1" data-ccap-focus="1">' . esc_html( $title ) . '</h2>'
			. '<p>' . esc_html__( 'Your own text of this language is deleted; other languages and messages stay. The deletion is recorded in the activity log.', 'course-captain' ) . '</p>'
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="do" value="reset"><input type="hidden" name="confirm" value="1"><input type="hidden" name="type" value="' . esc_attr( $type ) . '"><input type="hidden" name="locale" value="' . esc_attr( $locale ) . '">' . wp_nonce_field( self::ACTION, '_wpnonce', true, false )
			. '<button type="submit" class="button button-primary">' . esc_html__( 'Restore built-in text', 'course-captain' ) . '</button> <a class="button" href="' . esc_url(
				self::url(
					array(
						'type'   => $type,
						'locale' => $locale,
					)
				)
			) . '">' . esc_html__( 'Cancel', 'course-captain' ) . '</a></form></section>';
	}
}
