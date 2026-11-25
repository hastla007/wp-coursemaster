<?php
/**
 * Preview of the retention run on the privacy tab (SET-CC-02, PRIV-008).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Privacy\RetentionPolicy;
use CourseCaptain\Privacy\RetentionService;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only table "what the next run would delete" per sub-key of
 * `privacy.retention` (count queries only, {@see RetentionService::preview()}),
 * plus the texts used by the field notices and the RC-23 dialog.
 */
final class RetentionPreview {

	/**
	 * Preview cache per candidate (one request).
	 *
	 * @var array<string, array<string, array{value: int|string|null, unit: string, kind: string, count: int|null, error: bool}>>
	 */
	private static array $cache = array();

	/**
	 * Preview entry of one sub-key.
	 *
	 * @param string                    $key    Sub-key.
	 * @param array<string, mixed>|null $values Candidate values (null = stored setting).
	 * @return array{value: int|string|null, unit: string, kind: string, count: int|null, error: bool}
	 */
	public static function entry( string $key, ?array $values = null ): array {
		$hash = null === $values ? 'stored' : md5( (string) wp_json_encode( $values ) );
		if ( ! isset( self::$cache[ $hash ] ) ) {
			self::$cache[ $hash ] = RetentionService::preview( $values );
		}
		return self::$cache[ $hash ][ $key ] ?? array(
			'value' => null,
			'unit'  => 'fixed',
			'kind'  => 'rows',
			'count' => null,
			'error' => false,
		);
	}

	/**
	 * Forget cached previews (after a save, tests).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$cache = array();
	}

	/**
	 * Readable period.
	 *
	 * @param string          $key   Sub-key.
	 * @param int|string|null $value Value.
	 * @return string
	 */
	public static function value_text( string $key, $value ): string {
		$unit = RetentionPolicy::KEYS[ $key ]['unit'] ?? 'days';
		if ( 'fixed' === $unit ) {
			return __( 'until the account is deleted', 'course-captain' );
		}
		if ( null === $value ) {
			return __( 'never (off)', 'course-captain' );
		}
		$n = (int) $value;
		if ( 'years' === $unit ) {
			/* translators: %s: number of years */
			return sprintf( _n( '%s year', '%s years', $n, 'course-captain' ), number_format_i18n( $n ) );
		}
		if ( 'count' === $unit ) {
			/* translators: %s: number of package copies */
			return sprintf( _n( '%s copy per source', '%s copies per source', $n, 'course-captain' ), number_format_i18n( $n ) );
		}
		/* translators: %s: number of days */
		return sprintf( _n( '%s day', '%s days', $n, 'course-captain' ), number_format_i18n( $n ) );
	}

	/**
	 * Readable count of a preview entry.
	 *
	 * @param array{value: int|string|null, unit: string, kind: string, count: int|null, error: bool} $entry Entry.
	 * @return string
	 */
	public static function count_text( array $entry ): string {
		if ( $entry['error'] ) {
			return __( 'not available (check the system status)', 'course-captain' );
		}
		if ( null === $entry['count'] ) {
			return __( 'nothing (no automatic deletion)', 'course-captain' );
		}
		$n = (int) $entry['count'];
		if ( 'files' === $entry['kind'] ) {
			/* translators: %s: number of files */
			return sprintf( _n( '%s file', '%s files', $n, 'course-captain' ), number_format_i18n( $n ) );
		}
		if ( 'people' === $entry['kind'] ) {
			/* translators: %s: number of people */
			return sprintf( _n( 'learning data of %s person', 'learning data of %s people', $n, 'course-captain' ), number_format_i18n( $n ) );
		}
		/* translators: %s: number of database entries */
		return sprintf( _n( '%s entry', '%s entries', $n, 'course-captain' ), number_format_i18n( $n ) );
	}

	/**
	 * Intro of the retention section: legal review note and filter notice.
	 *
	 * @return string
	 */
	public static function intro(): string {
		$html     = '<p class="ccap-retention-intro">' . esc_html__( 'These periods are changeable suggestions, not legal advice. Every value is marked “Legal review needed”: have it checked by your legal advisor before you rely on it. Shorter periods need a confirmation that shows what the next run deletes; the effect starts with the next hourly run.', 'course-captain' ) . '</p>';
		$filtered = RetentionPolicy::filtered();
		if ( array() !== $filtered ) {
			/* translators: 1: number of periods, 2: name of the filter */
			$html .= '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( _n( 'A plugin or theme sets %1$d period with the filter %2$s; for it the value shown in the field is not effective.', 'A plugin or theme sets %1$d periods with the filter %2$s; for them the values shown in the fields are not effective.', count( $filtered ), 'course-captain' ), count( $filtered ), RetentionPolicy::FILTER ) ) . '</p></div>';
		}
		// RR-4 sentence 2: allowed, but evidence disappears before the attempts it rests on.
		if ( \CourseCaptain\Settings\SettingsRules::certificates_before_attempts( RetentionPolicy::effective() ) ) {
			$html .= '<div class="notice notice-info inline ccap-retention-rr4"><p>' . esc_html__( 'Note: certificates are deleted earlier than the exam attempts they are based on. Afterwards the verification page reports them as not available although the attempt still exists.', 'course-captain' ) . '</p></div>';
		}
		return $html;
	}

	/**
	 * Read-only preview table of the stored (effective) periods.
	 *
	 * @return string
	 */
	public static function table(): string {
		$rows = '';
		foreach ( array_keys( RetentionPolicy::KEYS ) as $key ) {
			$entry = self::entry( $key );
			$rows .= '<tr><th scope="row">' . esc_html( SettingsFields::ui( 'privacy', 'retention.' . $key )['label'] ) . '</th><td>' . esc_html( self::value_text( $key, $entry['value'] ) ) . '</td><td>' . esc_html( self::start_text( $key ) ) . '</td><td>' . esc_html( self::count_text( $entry ) ) . '</td></tr>';
		}
		return '<table class="widefat striped ccap-retention-preview"><caption>' . esc_html__( 'What the next run would delete with the saved periods (preview; nothing is deleted here)', 'course-captain' ) . '</caption>'
			. '<thead><tr><th scope="col">' . esc_html__( 'Data', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Effective period', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Period starts', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Next run deletes', 'course-captain' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * Start of the period of a sub-key (R0-31 §2.1 column "Auslöser";
	 * course end = E-244, {@see \CourseCaptain\Privacy\CourseEnd}).
	 *
	 * @param string $key Sub-key.
	 * @return string
	 */
	public static function start_text( string $key ): string {
		switch ( $key ) {
			case 'audit_log_years':
				return __( 'when the entry was written', 'course-captain' );
			case 'grant_history_deleted_accounts_years':
				return __( 'when the account was deleted (purchase access is kept pseudonymised until then)', 'course-captain' );
			case 'import_log_days':
			case 'import_unchanged_days':
				return __( 'when the log entry was written', 'course-captain' );
			case 'import_keep_packages':
				return __( 'no date: the active copy and the newest copies per source stay', 'course-captain' );
			case 'import_package_days':
				return __( 'last activity of the unfinished import', 'course-captain' );
			case 'inbox_days':
				return __( 'when the event was received or created', 'course-captain' );
			case 'outbox_event_days':
				return __( 'when the event was created (deliveries still open)', 'course-captain' );
			case 'notification_log_days':
				return __( 'when the message was created', 'course-captain' );
			case 'error_log_days':
			case 'debug_file_days':
				return __( 'date of the log file', 'course-captain' );
			case 'exam_attempts_years':
				return __( 'submission or evaluation of the attempt', 'course-captain' );
			case 'raw_answers_years_after_course_end':
				return __( 'course end: the later of the end of the last access and the last activity in the course; no period runs while the access is active', 'course-captain' );
			case 'certificates_years':
				return __( 'issue of the latest certificate revision', 'course-captain' );
			case 'inactive_learning_data_years':
				return __( 'last learning activity, only without active access', 'course-captain' );
			case 'invite_email_days_after_expiry':
				return __( 'when the invitation expired', 'course-captain' );
			case 'held_mail_days':
				return __( 'when the held message was created', 'course-captain' );
			case 'learning_progress':
				return __( 'deletion of the account or erasure request', 'course-captain' );
			case 'ended_grants_years':
				return __( 'end of the access', 'course-captain' );
			case 'removed_seats_days':
				return __( 'when the seat was removed', 'course-captain' );
			case 'closed_groups_years':
				return __( 'closing of the group or end of its validity', 'course-captain' );
			case 'lifted_locks_days':
				return __( 'when the lock was lifted', 'course-captain' );
		}
		return '';
	}

	/**
	 * Field status of every retention field (marker "Legal review needed").
	 *
	 * @return string
	 */
	public static function status(): string {
		return __( 'Legal review needed', 'course-captain' );
	}

	/**
	 * Field warning when the filter replaces the stored value.
	 *
	 * @param string $key Sub-key.
	 * @return string
	 */
	public static function filter_warning( string $key ): string {
		$filtered = RetentionPolicy::filtered();
		if ( ! array_key_exists( $key, $filtered ) ) {
			return '';
		}
		/* translators: 1: name of the filter, 2: effective period */
		return sprintf( __( 'Warning: the filter %1$s overrides this value; effective is %2$s.', 'course-captain' ), RetentionPolicy::FILTER, self::value_text( $key, $filtered[ $key ] ) );
	}
}
