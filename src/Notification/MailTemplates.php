<?php
/**
 * Mail templates, built-in texts and rendering (R0-27 §3.9, §3.10).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Notification;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Option `course_captain_mail_template_<type>` = map<locale, {subject,
 * body_html, body_text, rev}>; nothing is stored until the operator adapts
 * a text. Syntax `{{name}}`, only variables of the type's allowlist
 * (unknown → save refused with the allowed list). Escaping by context:
 * HTML part `esc_html`/`esc_url`, text part without markup, subject without
 * CR/LF and at most 200 characters; no variable ever yields raw HTML.
 * Resolution: exact variant → same language → operator default (site
 * locale) → built-in text in the recipient locale.
 */
final class MailTemplates {

	public const OPTION_PREFIX = 'course_captain_mail_template_';
	public const SUBJECT_MAX   = 200;
	public const BODY_MAX      = 20000;

	/** Export format of the mail texts (SET-CC-04). */
	public const FORMAT = 'course-captain-mail-templates';

	/** Maximum size of an import file (as settings imports, R0-27 §2.5). */
	public const IMPORT_MAX_BYTES = 262144;

	/** Exports/imports per account and hour, fail-closed (R0-17 §3.4). */
	public const TRANSFER_PER_HOUR = 10;

	/** User transient of the transfer rate limit. */
	public const RATE_PREFIX = 'ccap_mail_tpl_rate_';

	/** Mail kses allowlist (§3.10). */
	private const KSES = array(
		'p'      => array(),
		'br'     => array(),
		'strong' => array(),
		'em'     => array(),
		'a'      => array( 'href' => true ),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'h2'     => array(),
		'h3'     => array(),
	);

	/** Languages written right to left (fallback when no translation is loaded). */
	private const RTL = array( 'ar', 'arc', 'ckb', 'dv', 'fa', 'ha', 'he', 'ps', 'sd', 'ug', 'ur', 'yi' );

	/**
	 * Locales a template may be stored for (installed languages).
	 *
	 * @return list<string>
	 */
	public static function locales(): array {
		$locales = array_values( array_unique( array_merge( array( 'en_US', get_locale() ), get_available_languages() ) ) );
		/**
		 * Locales offered for mail templates.
		 *
		 * @since 1.0.0
		 * @param list<string> $locales Installed locales.
		 */
		$filtered = apply_filters( 'course_captain_mail_template_locales', $locales );
		return is_array( $filtered ) ? array_values( array_map( 'strval', $filtered ) ) : $locales;
	}

	/**
	 * Stored variants of a type.
	 *
	 * @param string $type Type.
	 * @return array<string, array{subject: string, body_html: string, body_text: string, rev: int}>
	 */
	public static function stored( string $type ): array {
		$raw = get_option( self::OPTION_PREFIX . $type, array() );
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $locale => $variant ) {
			if ( is_array( $variant ) && isset( $variant['subject'], $variant['body_html'] ) ) {
				$out[ (string) $locale ] = array(
					'subject'   => (string) $variant['subject'],
					'body_html' => (string) $variant['body_html'],
					'body_text' => (string) ( $variant['body_text'] ?? '' ),
					'rev'       => (int) ( $variant['rev'] ?? 1 ),
				);
			}
		}
		return $out;
	}

	/**
	 * Save a variant (operator text).
	 *
	 * @param Actor  $actor     Admin (`ccap_manage_settings`).
	 * @param string $type      Type.
	 * @param string $locale    Installed locale.
	 * @param string $subject   Subject.
	 * @param string $body_html HTML body (mail kses).
	 * @param string $body_text Text body (empty = derived from HTML).
	 * @return int New revision.
	 * @throws CourseException `ccap_forbidden`, `ccap_invalid_param`, `ccap_validation_failed`.
	 */
	public static function save( Actor $actor, string $type, string $locale, string $subject, string $body_html, string $body_text = '' ): int {
		self::assert_admin( $actor );
		if ( ! NotificationTypes::exists( $type ) || ! in_array( $locale, self::locales(), true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown mail type or locale.', 422 );
		}
		$check = self::check( $type, $subject, $body_html, $body_text );
		if ( array() !== $check['errors'] ) {
			$unknown = array();
			foreach ( $check['errors'] as $error ) {
				$unknown = array_merge( $unknown, $error['unknown'] );
			}
			if ( array() !== $unknown ) {
				throw new CourseException( 'ccap_validation_failed', esc_html( 'Unknown placeholders: ' . implode( ', ', array_unique( $unknown ) ) . '. Allowed: ' . implode( ', ', NotificationTypes::variables( $type ) ) ), 422, array( 'fields' => $check['errors'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Data for the caller, not output.
			}
			throw new CourseException( 'ccap_validation_failed', 'subject,body_html', 422, array( 'fields' => $check['errors'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Data for the caller, not output.
		}
		list( $subject, $body_html, $body_text ) = array( $check['values']['subject'], $check['values']['body_html'], $check['values']['body_text'] );
		$all                                     = self::stored( $type );
		$before                                  = $all[ $locale ] ?? null;
		$rev                                     = null === $before ? 1 : $before['rev'] + 1;
		$all[ $locale ]                          = array(
			'subject'   => $subject,
			'body_html' => $body_html,
			'body_text' => $body_text,
			'rev'       => $rev,
		);
		$hash                                    = static fn( ?array $v ): ?string => null === $v ? null : hash( 'sha256', (string) wp_json_encode( array( $v['subject'], $v['body_html'], $v['body_text'] ) ) );
		$hash_after                              = $hash( $all[ $locale ] );
		$hash_before                             = $hash( $before );
		if ( null !== $before && $hash_after === $hash_before ) {
			return $before['rev'];
		}
		update_option( self::OPTION_PREFIX . $type, $all, false );
		AuditLog::write(
			Database::current(),
			$actor,
			'mail_template_saved',
			'mail_template',
			$type,
			null,
			array(
				'locale'      => $locale,
				'hash_before' => $hash_before,
				'hash_after'  => $hash_after,
			)
		);
		return $rev;
	}

	/**
	 * Placeholder names used in a text.
	 *
	 * @param string $text Text.
	 * @return list<string>
	 */
	public static function placeholders( string $text ): array {
		preg_match_all( '/\{\{\s*([^{}]*?)\s*\}\}/', $text, $m );
		return array_values( array_unique( $m[1] ) );
	}

	/**
	 * Clean and check an operator text (field-near rules, SET-CC-04).
	 *
	 * Rules per field (`subject`, `body_html`, `body_text`): `required`,
	 * `length`, `placeholder` (with `unknown` = names outside the allowlist
	 * of the type).
	 *
	 * @param string $type      Type.
	 * @param string $subject   Subject.
	 * @param string $body_html HTML body.
	 * @param string $body_text Text body (empty = derived from HTML).
	 * @return array{values: array{subject: string, body_html: string, body_text: string}, errors: array<string, array{rule: string, unknown: list<string>}>}
	 */
	public static function check( string $type, string $subject, string $body_html, string $body_text ): array {
		$values  = array(
			'subject'   => trim( (string) preg_replace( '/[\r\n]+/', ' ', sanitize_text_field( $subject ) ) ),
			'body_html' => wp_kses( $body_html, self::KSES ),
			'body_text' => sanitize_textarea_field( $body_text ),
		);
		$allowed = NotificationTypes::variables( $type );
		$errors  = array();
		foreach ( $values as $field => $value ) {
			$max     = 'subject' === $field ? self::SUBJECT_MAX : self::BODY_MAX;
			$unknown = array_values( array_diff( self::placeholders( $value ), $allowed ) );
			if ( 'body_text' !== $field && '' === trim( wp_strip_all_tags( $value ) ) ) {
				$errors[ $field ] = array(
					'rule'    => 'required',
					'unknown' => array(),
				);
			} elseif ( mb_strlen( $value ) > $max ) {
				$errors[ $field ] = array(
					'rule'    => 'length',
					'unknown' => array(),
				);
			} elseif ( array() !== $unknown ) {
				$errors[ $field ] = array(
					'rule'    => 'placeholder',
					'unknown' => $unknown,
				);
			}
		}
		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Restore the built-in text of one type and language (R0-27 §2.4
	 * "Werkstext wiederherstellen"); other languages stay.
	 *
	 * @param Actor  $actor  Admin.
	 * @param string $type   Type.
	 * @param string $locale Locale.
	 * @return bool Whether an operator text was removed.
	 * @throws CourseException `ccap_forbidden`, `ccap_invalid_param`.
	 */
	public static function reset( Actor $actor, string $type, string $locale ): bool {
		self::assert_admin( $actor );
		if ( ! NotificationTypes::exists( $type ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown mail type.', 422 );
		}
		$all = self::stored( $type );
		if ( ! isset( $all[ $locale ] ) ) {
			return false;
		}
		$hash = hash( 'sha256', (string) wp_json_encode( array( $all[ $locale ]['subject'], $all[ $locale ]['body_html'], $all[ $locale ]['body_text'] ) ) );
		unset( $all[ $locale ] );
		if ( array() === $all ) {
			delete_option( self::OPTION_PREFIX . $type );
		} else {
			update_option( self::OPTION_PREFIX . $type, $all, false );
		}
		AuditLog::write(
			Database::current(),
			$actor,
			'mail_template_reset',
			'mail_template',
			$type,
			null,
			array(
				'locale'      => $locale,
				'hash_before' => $hash,
			)
		);
		return true;
	}

	/**
	 * Export of all operator texts (SET-CC-04; no personal data, no secrets).
	 *
	 * @param Actor $actor Admin.
	 * @return array<string, mixed>
	 * @throws CourseException `ccap_forbidden`, `ccap_rate_limited`.
	 */
	public static function export( Actor $actor ): array {
		self::assert_admin( $actor );
		self::rate_limit( $actor );
		$templates = array();
		foreach ( NotificationTypes::all() as $type ) {
			foreach ( self::stored( $type ) as $locale => $variant ) {
				$templates[ $type ][ $locale ] = array(
					'subject'   => $variant['subject'],
					'body_html' => $variant['body_html'],
					'body_text' => $variant['body_text'],
				);
			}
		}
		return array(
			'format'         => self::FORMAT,
			'format_version' => 1,
			'plugin_version' => defined( 'COURSE_CAPTAIN_VERSION' ) ? (string) constant( 'COURSE_CAPTAIN_VERSION' ) : '',
			'exported_at'    => gmdate( 'c' ),
			'site_hash'      => hash( 'sha256', home_url() ),
			'templates'      => (object) $templates,
		);
	}

	/**
	 * Parse an import file.
	 *
	 * @param string $raw Raw JSON.
	 * @return array<string, mixed>
	 * @throws CourseException `ccap_validation_failed`.
	 */
	public static function parse( string $raw ): array {
		if ( strlen( $raw ) > self::IMPORT_MAX_BYTES ) {
			throw new CourseException( 'ccap_validation_failed', 'The file is larger than 256 KB.', 413 );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || self::FORMAT !== ( $data['format'] ?? '' ) || 1 !== (int) ( $data['format_version'] ?? 0 ) || ! is_array( $data['templates'] ?? null ) ) {
			throw new CourseException( 'ccap_validation_failed', 'Not a Course Captain mail text file.', 422 );
		}
		return $data;
	}

	/**
	 * Preview or apply an import: every entry is checked like a manual save.
	 *
	 * @param Actor                $actor Admin.
	 * @param array<string, mixed> $data  Parsed file.
	 * @param bool                 $apply Write the valid entries.
	 * @return list<array{type: string, locale: string, status: string, errors: array<string, array{rule: string, unknown: list<string>}>}> Status `new`, `changed`, `unchanged`, `unknown_type`, `unknown_locale`, `invalid`.
	 * @throws CourseException `ccap_forbidden`, `ccap_rate_limited`.
	 */
	public static function import( Actor $actor, array $data, bool $apply = false ): array {
		self::assert_admin( $actor );
		if ( ! $apply ) {
			self::rate_limit( $actor );
		}
		$plan    = array();
		$applied = 0;
		foreach ( (array) $data['templates'] as $type => $variants ) {
			foreach ( is_array( $variants ) ? $variants : array() as $locale => $variant ) {
				$entry = array(
					'type'   => (string) $type,
					'locale' => (string) $locale,
					'status' => 'invalid',
					'errors' => array(),
				);
				if ( ! NotificationTypes::exists( (string) $type ) ) {
					$entry['status'] = 'unknown_type';
				} elseif ( ! in_array( (string) $locale, self::locales(), true ) ) {
					$entry['status'] = 'unknown_locale';
				} elseif ( is_array( $variant ) && is_string( $variant['subject'] ?? null ) && is_string( $variant['body_html'] ?? null ) ) {
					$check = self::check( (string) $type, $variant['subject'], $variant['body_html'], is_string( $variant['body_text'] ?? null ) ? $variant['body_text'] : '' );
					if ( array() !== $check['errors'] ) {
						$entry['errors'] = $check['errors'];
					} else {
						$before          = self::stored( (string) $type )[ (string) $locale ] ?? null;
						$entry['status'] = null === $before ? 'new' : ( array( $before['subject'], $before['body_html'], $before['body_text'] ) === array_values( $check['values'] ) ? 'unchanged' : 'changed' );
						if ( $apply && 'unchanged' !== $entry['status'] ) {
							self::save( $actor, (string) $type, (string) $locale, $check['values']['subject'], $check['values']['body_html'], $check['values']['body_text'] );
							++$applied;
						}
					}
				}
				$plan[] = $entry;
			}
		}
		if ( $apply ) {
			AuditLog::write( Database::current(), $actor, 'mail_templates_imported', 'mail_template', 'import', null, array( 'applied' => $applied ) );
		}
		return $plan;
	}

	/**
	 * Administrators with `ccap_manage_settings` only.
	 *
	 * @param Actor $actor Actor.
	 * @return void
	 * @throws CourseException `ccap_forbidden`.
	 */
	private static function assert_admin( Actor $actor ): void {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, Settings::CAP ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to change mail templates.', 403 );
		}
	}

	/**
	 * 10 exports/imports per account and hour, fail-closed.
	 *
	 * @param Actor $actor Actor.
	 * @return void
	 * @throws CourseException `ccap_rate_limited`.
	 */
	private static function rate_limit( Actor $actor ): void {
		$key   = self::RATE_PREFIX . $actor->user_id;
		$now   = time();
		$state = get_transient( $key );
		$state = array_values( array_filter( array_map( 'intval', is_array( $state ) ? $state : array() ), static fn( int $t ): bool => $t > $now - HOUR_IN_SECONDS ) );
		if ( count( $state ) >= self::TRANSFER_PER_HOUR ) {
			throw new CourseException( 'ccap_rate_limited', 'At most ten mail text exports or imports per hour.', 429 );
		}
		$state[] = $now;
		if ( ! set_transient( $key, $state, HOUR_IN_SECONDS ) && false === get_transient( $key ) ) {
			throw new CourseException( 'ccap_rate_limited', 'Rate limit storage unavailable.', 503 );
		}
	}

	/**
	 * Variant for a recipient locale (§3.9): exact → same language → operator default → null (built-in).
	 *
	 * @param string $type   Type.
	 * @param string $locale Recipient locale.
	 * @return array{0: string, 1: array{subject: string, body_html: string, body_text: string, rev: int}}|null
	 */
	public static function variant( string $type, string $locale ): ?array {
		$stored = self::stored( $type );
		if ( isset( $stored[ $locale ] ) ) {
			return array( $locale, $stored[ $locale ] );
		}
		$language = strtok( $locale, '_' );
		ksort( $stored );
		foreach ( $stored as $candidate => $variant ) {
			if ( strtok( (string) $candidate, '_' ) === $language ) {
				return array( (string) $candidate, $variant );
			}
		}
		$site = get_locale();
		return isset( $stored[ $site ] ) ? array( $site, $stored[ $site ] ) : null;
	}

	/**
	 * Built-in text (translated in the current locale).
	 *
	 * @param string $type Type.
	 * @return array{subject: string, paragraphs: list<string>}
	 * @throws CourseException For unknown types.
	 */
	public static function builtin( string $type ): array {
		$hello = __( 'Hello {{recipient_display_name}},', 'course-captain' );
		switch ( $type ) {
			case 'access_granted':
				return array(
					'subject'    => __( 'Your access to {{course_title}}', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'You now have access to the course {{course_title}}.', 'course-captain' ), __( 'Open the course: {{course_url}}', 'course-captain' ) ),
				);
			case 'seat_invitation':
				return array(
					'subject'    => __( 'Invitation to {{group_name}} on {{site_name}}', 'course-captain' ),
					'paragraphs' => array( __( '{{inviter_display_name}} has invited you to the group {{group_name}}.', 'course-captain' ), __( 'Accept the invitation: {{claim_url}}', 'course-captain' ), __( 'The invitation is valid until {{invite_expires_at}}.', 'course-captain' ) ),
				);
			case 'seat_claimed':
				return array(
					'subject'    => __( 'Welcome to {{course_title}}', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'You have joined the group and can now learn in {{course_title}}.', 'course-captain' ), __( 'Go to your learning area: {{learning_area_url}}', 'course-captain' ) ),
				);
			case 'course_completed':
				return array(
					'subject'    => __( 'You have completed {{course_title}}', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'Congratulations, you have completed the course {{course_title}}.', 'course-captain' ), __( 'View the course: {{course_url}}', 'course-captain' ) ),
				);
			case 'certificate_issued':
				return array(
					'subject'    => __( 'Your certificate for {{course_title}}', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'Your certificate is ready: {{certificate_url}}', 'course-captain' ) ),
				);
			case 'certificate_corrected':
				return array(
					'subject'    => __( 'Your certificate was corrected', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'Your certificate was corrected ({{reason_label}}). Current version: {{certificate_url}}', 'course-captain' ) ),
				);
			case 'certificate_revoked':
				return array(
					'subject'    => __( 'Your certificate was revoked', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'Your certificate was revoked ({{reason_label}}).', 'course-captain' ) ),
				);
			case 'attempt_voided':
				return array(
					'subject'    => __( 'An exam attempt was voided', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'Your attempt {{attempt_title}} was voided ({{reason_label}}). Details: {{result_url}}', 'course-captain' ) ),
				);
			case 'attempt_annulled':
				return array(
					'subject'    => __( 'An exam result was annulled', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'The result of {{attempt_title}} was annulled ({{reason_label}}). Details: {{result_url}}', 'course-captain' ) ),
				);
			case 'access_transferred':
				return array(
					'subject'    => __( 'Your access to {{course_title}}', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'Access to the course {{course_title}} was transferred to you.', 'course-captain' ), __( 'Open the course: {{course_url}}', 'course-captain' ) ),
				);
			case 'grading_completed':
				return array(
					'subject'    => __( 'Your exam was graded', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'The grading of {{attempt_title}} is complete.', 'course-captain' ), __( 'View it in your learning area: {{result_url}}', 'course-captain' ) ),
				);
			case 'access_expired':
				return array(
					'subject'    => __( 'Your access to {{course_title}} has ended', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'Your access to the course {{course_title}} ended on {{access_end_at}}.', 'course-captain' ), __( 'Your learning area: {{learning_area_url}}', 'course-captain' ) ),
				);
			case 'test_mail':
				return array(
					'subject'    => __( 'Test message from {{site_name}}', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'This is a test message. Mail delivery from Course Captain works.', 'course-captain' ) ),
				);
			case 'assignment_submitted':
				return array(
					'subject'    => __( 'New submission in {{course_title}}', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'A new assignment submission in {{course_title}} is waiting for your review.', 'course-captain' ), __( 'Open the course: {{course_url}}', 'course-captain' ) ),
				);
			case 'assignment_returned':
				return array(
					'subject'    => __( 'Your submission in {{course_title}} was returned', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'Your assignment submission was returned for revision.', 'course-captain' ), __( 'Open the course: {{course_url}}', 'course-captain' ) ),
				);
			case 'assignment_graded':
				return array(
					'subject'    => __( 'Your submission in {{course_title}} was graded', 'course-captain' ),
					'paragraphs' => array( $hello, __( 'Your assignment submission was graded.', 'course-captain' ), __( 'Open the course: {{course_url}}', 'course-captain' ) ),
				);
		}
		throw new CourseException( 'ccap_invalid_param', 'Unknown mail type.', 422 );
	}

	/**
	 * Render a mail in the current (already switched) locale.
	 *
	 * @param string                $type   Type.
	 * @param string                $locale Requested locale.
	 * @param array<string, string> $vars   Variable values (raw, unescaped).
	 * @param bool                  $builtin_only Skip operator variants (fallback after `template_error`).
	 * @param array|null            $draft  Unsaved operator text `{subject, body_html, body_text}` (preview and test mail of the editor).
	 * @phpstan-param array{subject: string, body_html: string, body_text: string}|null $draft
	 * @return array{subject: string, html: string, text: string, locale: string, template_rev: int|null, lang: string, dir: string}
	 * @throws TemplateError When the template cannot be rendered.
	 */
	public static function render( string $type, string $locale, array $vars, bool $builtin_only = false, ?array $draft = null ): array {
		$allowed = NotificationTypes::variables( $type );
		$vars    = array_intersect_key( $vars, array_flip( $allowed ) );
		$variant = $builtin_only ? null : self::variant( $type, $locale );
		if ( null !== $draft ) {
			$variant = array( $locale, $draft + array( 'rev' => 0 ) );
		}
		if ( null !== $variant ) {
			list( $resolved, $data ) = $variant;
			$used                    = self::placeholders( $data['subject'] . ' ' . $data['body_html'] . ' ' . $data['body_text'] );
			if ( array() !== array_diff( $used, $allowed ) ) {
				throw new TemplateError( 'unknown placeholder' );
			}
			$subject = self::fill_plain( $data['subject'], $vars );
			$html    = self::fill_html( wp_kses( $data['body_html'], self::KSES ), $vars, false );
			$text    = '' === trim( $data['body_text'] ) ? self::html_to_text( $html ) : self::fill_plain( $data['body_text'], $vars );
			$rev     = $data['rev'];
		} else {
			$resolved = determine_locale();
			$builtin  = self::builtin( $type );
			$subject  = self::fill_plain( $builtin['subject'], $vars );
			$html     = '';
			$text     = array();
			foreach ( $builtin['paragraphs'] as $paragraph ) {
				$html  .= '<p>' . self::fill_html( esc_html( $paragraph ), $vars, true ) . '</p>';
				$text[] = self::fill_plain( $paragraph, $vars );
			}
			$text = implode( "\n\n", $text );
			$rev  = null;
		}
		list( $footer_html, $footer_text ) = self::footer( $type, $vars );
		$html                             .= $footer_html;
		$text                             .= $footer_text;
		if ( str_contains( $subject . $html . $text, '{{' ) ) {
			throw new TemplateError( 'unresolved placeholder' );
		}
		$subject  = mb_substr( trim( (string) preg_replace( '/[\r\n\t]+/', ' ', $subject ) ), 0, self::SUBJECT_MAX );
		$language = (string) strtok( $resolved, '_' );
		$dir      = in_array( $language, self::RTL, true ) || ( determine_locale() === $resolved && is_rtl() ) ? 'rtl' : 'ltr';
		$lang     = str_replace( '_', '-', $resolved );
		return array(
			'subject'      => $subject,
			'html'         => '<!DOCTYPE html><html lang="' . esc_attr( $lang ) . '" dir="' . esc_attr( $dir ) . '"><head><meta charset="utf-8"></head><body style="margin:0;padding:24px;background:#ffffff;color:#1d2327;font-family:sans-serif;font-size:16px;line-height:1.5"><div style="max-width:600px;margin:0 auto">' . $html . '</div></body></html>',
			'text'         => $text,
			'locale'       => $resolved,
			'template_rev' => $rev,
			'lang'         => $lang,
			'dir'          => $dir,
		);
	}

	/**
	 * Sample values for the preview (§3.10).
	 *
	 * @param string $type Type.
	 * @return array<string, string>
	 */
	public static function sample_vars( string $type ): array {
		$area   = home_url( '/' );
		$sample = array(
			'recipient_display_name' => __( 'Alex Example', 'course-captain' ),
			'unsubscribe_url'        => $area,
			'course_title'           => __( 'Sample course', 'course-captain' ),
			'course_url'             => $area,
			'group_name'             => __( 'Sample group', 'course-captain' ),
			'inviter_display_name'   => __( 'Sam Example', 'course-captain' ),
			'invite_expires_at'      => (string) wp_date( (string) get_option( 'date_format' ) . ' T' ),
			'claim_url'              => $area,
			'certificate_url'        => $area,
			'verify_url'             => $area,
			'reason_label'           => __( 'Correction', 'course-captain' ),
			'attempt_title'          => __( 'Final exam', 'course-captain' ),
			'result_url'             => $area,
			'access_end_at'          => (string) wp_date( (string) get_option( 'date_format' ) . ' T' ),
		);
		return array_intersect_key( self::site_vars() + $sample, array_flip( NotificationTypes::variables( $type ) ) );
	}

	/**
	 * Preview with sample data in a chosen locale.
	 *
	 * @param Actor      $actor  Admin.
	 * @param string     $type   Type.
	 * @param string     $locale Locale.
	 * @param array|null $draft Unsaved operator text (editor), null = stored text or built-in text.
	 * @phpstan-param array{subject: string, body_html: string, body_text: string}|null $draft
	 * @return array{subject: string, html: string, text: string, locale: string, template_rev: int|null, lang: string, dir: string}
	 * @throws CourseException Without the capability.
	 * @throws TemplateError When the text cannot be rendered.
	 */
	public static function preview( Actor $actor, string $type, string $locale, ?array $draft = null ): array {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, Settings::CAP ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to preview mail templates.', 403 );
		}
		$switched = switch_to_locale( $locale );
		try {
			return self::render( $type, $locale, self::sample_vars( $type ), false, $draft );
		} catch ( TemplateError $e ) {
			// The editor shows its own message; the caller decides (no state change here).
			throw $e;
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * Variables every type has.
	 *
	 * @return array<string, string>
	 */
	public static function site_vars(): array {
		return array(
			'site_name'          => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'           => home_url( '/' ),
			'learning_area_url'  => \CourseCaptain\Learner\LearningArea::url(),
			'privacy_policy_url' => (string) get_privacy_policy_url(),
		);
	}

	/**
	 * Footer: operator text once, then the unsubscribe line (informational) or the reason sentence (required).
	 *
	 * @param string                $type Type.
	 * @param array<string, string> $vars Variables.
	 * @return array{0: string, 1: string}
	 */
	private static function footer( string $type, array $vars ): array {
		$html   = '';
		$text   = '';
		$custom = (string) Settings::get( 'emails.footer_text' );
		if ( '' !== trim( $custom ) ) {
			$html .= '<p>' . nl2br( esc_html( $custom ) ) . '</p>';
			$text .= "\n\n" . $custom;
		}
		if ( NotificationTypes::is_informational( $type ) && isset( $vars['unsubscribe_url'] ) ) {
			$line  = __( 'You no longer want these messages? Unsubscribe: {{unsubscribe_url}}', 'course-captain' );
			$html .= '<p>' . self::fill_html( esc_html( $line ), $vars, true ) . '</p>';
			$text .= "\n\n" . self::fill_plain( $line, $vars );
		} elseif ( ! NotificationTypes::is_informational( $type ) ) {
			$line  = __( 'You receive this message because it is necessary for your account or your course participation.', 'course-captain' );
			$html .= '<p>' . esc_html( $line ) . '</p>';
			$text .= "\n\n" . $line;
		}
		return array( $html, $text );
	}

	/**
	 * Replace placeholders with plain values (no markup, no line breaks).
	 *
	 * @param string                $template Template.
	 * @param array<string, string> $vars     Values.
	 * @return string
	 */
	private static function fill_plain( string $template, array $vars ): string {
		return (string) preg_replace_callback(
			'/\{\{\s*([a-z_]+)\s*\}\}/',
			static fn( array $m ): string => isset( $vars[ $m[1] ] ) ? trim( (string) preg_replace( '/[\r\n\t]+/', ' ', wp_strip_all_tags( $vars[ $m[1] ] ) ) ) : $m[0],
			$template
		);
	}

	/**
	 * Replace placeholders in an (escaped) HTML template.
	 *
	 * @param string                $template   Escaped HTML.
	 * @param array<string, string> $vars       Raw values.
	 * @param bool                  $link_urls  Render URL variables as links (built-in text).
	 * @return string
	 */
	private static function fill_html( string $template, array $vars, bool $link_urls ): string {
		return (string) preg_replace_callback(
			'/\{\{\s*([a-z_]+)\s*\}\}/',
			static function ( array $m ) use ( $vars, $link_urls ): string {
				if ( ! isset( $vars[ $m[1] ] ) ) {
					return $m[0];
				}
				$value = trim( (string) preg_replace( '/[\r\n\t]+/', ' ', wp_strip_all_tags( $vars[ $m[1] ] ) ) );
				if ( in_array( $m[1], NotificationTypes::URL_VARIABLES, true ) ) {
					return $link_urls ? '<a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>' : esc_url( $value );
				}
				return in_array( $m[1], array( 'course_title', 'group_name', 'attempt_title' ), true ) ? '<bdi>' . esc_html( $value ) . '</bdi>' : esc_html( $value );
			},
			$template
		);
	}

	/**
	 * Text part from HTML (content-equal, no markup).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function html_to_text( string $html ): string {
		$html = (string) preg_replace( '#</(p|h2|h3|li|ul|ol)>|<br\s*/?>#i', "\n\n", $html );
		$text = html_entity_decode( wp_strip_all_tags( $html, false ), ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( "/\n{3,}/", "\n\n", (string) preg_replace( "/[ \t]*\n[ \t]*/", "\n", $text ) ) );
	}
}
