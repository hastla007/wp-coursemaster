<?php
/**
 * Certificate management: list, filter, CSV export, logo usage, slug history (R0-29 §2.2, R1-35).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Certificate;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * - List A-11 (CF-11): filters course, status, period, template kind;
 *   columns without email; read with #13 or #17.
 * - CSV export only with a reason (personal data, R0-07 §1), audited
 *   (`certificates_exported`), formula prefixes neutralized.
 * - Logo usage (CRT-024): the media library warns before deleting an
 *   attachment used by the base template or by issued certificates.
 * - Slug history (E-220, CRT-020): earlier learning area slugs are kept in
 *   `course_captain_slug_history` and old certificate links answer 301 to
 *   the current path, as long as no page uses the old slug.
 */
final class CertificateAdmin {

	public const SLUG_HISTORY = 'course_captain_slug_history';
	public const PER_PAGE     = 50;
	public const EXPORT_LIMIT = 5000;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'update_option_' . Settings::OPTION_PREFIX . 'general', array( self::class, 'remember_slug' ), 10, 2 );
		add_action( 'add_option_' . Settings::OPTION_PREFIX . 'general', array( self::class, 'remember_added_slug' ), 10, 2 );
		add_action( 'template_redirect', array( self::class, 'redirect_old_slug' ), 1 );
		add_filter( 'attachment_fields_to_edit', array( self::class, 'logo_warning_field' ), 10, 2 );
	}

	/**
	 * Whether the actor may read the list (#13 or #17).
	 *
	 * @param Actor $actor Actor.
	 * @return bool
	 */
	public static function can_read( Actor $actor ): bool {
		return $actor->user_id > 0 && ( user_can( $actor->user_id, 'ccap_view_reports' ) || user_can( $actor->user_id, 'ccap_manage_certificates' ) );
	}

	/**
	 * Filtered list (latest revision per certificate).
	 *
	 * @param Actor                $actor   Reader.
	 * @param array<string, mixed> $filters `course`, `status` (valid|expired|corrected|revoked), `from`, `to` (Y-m-d), `kind`.
	 * @param int                  $page    Page (1-based); 0 = all up to the export limit.
	 * @return array{items: list<array<string, mixed>>, total: int, pages: int}
	 * @throws CourseException `ccap_forbidden`.
	 */
	public static function query( Actor $actor, array $filters = array(), int $page = 1 ): array {
		if ( ! self::can_read( $actor ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to view certificates.', 403 );
		}
		$db    = Database::current();
		$table = $db->table( 'ccap_certificate' );
		$where = array( "c.revision_no = (SELECT MAX(c2.revision_no) FROM `{$table}` c2 WHERE c2.certificate_uid = c.certificate_uid)" );
		if ( ! empty( $filters['course'] ) ) {
			$where[] = $db->prepare( 'c.course_id = %d', (int) $filters['course'] );
		}
		foreach ( array(
			'from' => '>=',
			'to'   => '<=',
		) as $key => $op ) {
			if ( ! empty( $filters[ $key ] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $filters[ $key ] ) ) {
				$where[] = $db->prepare( "c.issued_at {$op} %s", (string) $filters[ $key ] . ( '>=' === $op ? ' 00:00:00' : ' 23:59:59' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed operator.
			}
		}
		$status = (string) ( $filters['status'] ?? '' );
		if ( in_array( $status, array( 'corrected', 'revoked' ), true ) ) {
			$where[] = $db->prepare( 'c.status = %s', $status );
		} elseif ( in_array( $status, array( 'valid', 'expired' ), true ) ) {
			$where[] = "c.status = 'issued'";
		}
		$rows  = $db->rows( "SELECT c.* FROM `{$table}` c WHERE " . implode( ' AND ', $where ) . ' ORDER BY c.issued_at DESC, c.id DESC LIMIT ' . self::EXPORT_LIMIT ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Conditions prepared above.
		$items = array();
		foreach ( $rows as $row ) {
			$snapshot = json_decode( (string) $row['snapshot'], true );
			$snapshot = is_array( $snapshot ) ? $snapshot : array();
			$derived  = CertificateService::status_of( $row );
			if ( in_array( $status, array( 'valid', 'expired' ), true ) && $derived !== $status ) {
				continue;
			}
			$kind = (string) ( $snapshot['template']['kind'] ?? 'base' );
			if ( ! empty( $filters['kind'] ) && $kind !== $filters['kind'] ) {
				continue;
			}
			$items[] = array(
				'certificate_uid'  => (string) $row['certificate_uid'],
				'display_id'       => (string) ( $snapshot['display_id'] ?? '' ),
				'revision_no'      => (int) $row['revision_no'],
				'status'           => $derived,
				'holder'           => (string) ( $snapshot['holder']['display_name'] ?? '' ),
				'user_id'          => (int) $row['user_id'],
				'course_id'        => (int) $row['course_id'],
				'course_title'     => (string) ( $snapshot['course']['title'] ?? '' ),
				'issued_at'        => (string) $row['issued_at'],
				'valid_until'      => isset( $snapshot['valid_until'] ) ? (string) $snapshot['valid_until'] : '',
				'template_kind'    => $kind,
				'template_version' => (string) $row['template_version'],
			);
		}
		$total = count( $items );
		if ( $page > 0 ) {
			$items = array_slice( $items, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );
		}
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => (int) max( 1, ceil( $total / self::PER_PAGE ) ),
		);
	}

	/**
	 * CSV export (CF-11): reason mandatory, audited, no email, formula-safe.
	 *
	 * @param Actor                $actor   Reader (#13 or #17).
	 * @param array<string, mixed> $filters Filters as in {@see self::query()}.
	 * @param string               $reason  Reason.
	 * @return string CSV (UTF-8 with BOM).
	 * @throws CourseException `ccap_forbidden`, `ccap_reason_required`.
	 */
	public static function export_csv( Actor $actor, array $filters, string $reason ): string {
		if ( ! self::can_read( $actor ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to export certificates.', 403 );
		}
		\CourseCaptain\Settings\Modules::assert_enabled( \CourseCaptain\Settings\Modules::CERTIFICATES );
		$reason = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_reason_required', 'A reason is required.', 422 );
		}
		$result = self::query( $actor, $filters, 0 );
		// SET-CC-26: a pseudonymised export carries a stable pseudonym instead of the holder name.
		$pseudo  = \CourseCaptain\Privacy\Pseudonym::exports_enabled();
		$columns = array( 'display_id', 'certificate_uid', 'revision_no', 'status', $pseudo ? 'pseudonym' : 'holder', 'course_title', 'issued_at', 'valid_until', 'template_kind', 'template_version' );
		$lines   = array( implode( ',', $columns ) );
		foreach ( $result['items'] as $item ) {
			$item['pseudonym'] = \CourseCaptain\Privacy\Pseudonym::of( (int) $item['user_id'] );
			$lines[]           = implode( ',', array_map( static fn( string $c ): string => self::csv_cell( (string) $item[ $c ] ), $columns ) );
		}
		AuditLog::write(
			Database::current(),
			$actor,
			'certificates_exported',
			'certificate',
			'list',
			$reason,
			array(
				'count'        => $result['total'],
				'pseudonymous' => $pseudo,
				'filters'      => array_intersect_key( $filters, array_flip( array( 'course', 'status', 'from', 'to', 'kind' ) ) ),
			)
		);
		return "\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * CSV cell; formula prefixes are neutralized (CSV injection, R0-17).
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function csv_cell( string $value ): string {
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return '"' . str_replace( '"', '""', $value ) . '"';
	}

	/**
	 * Base template versions (newest first) for the management view.
	 *
	 * @return list<array{version: int, definition_hash: string, created_at: string}>
	 */
	public static function template_versions(): array {
		$state = get_option( CertificateService::TEMPLATE_OPTION );
		$out   = array();
		foreach ( is_array( $state ) ? (array) ( $state['versions'] ?? array() ) : array() as $version ) {
			if ( is_array( $version ) ) {
				$out[] = array(
					'version'         => (int) ( $version['version'] ?? 0 ),
					'definition_hash' => (string) ( $version['definition_hash'] ?? '' ),
					'created_at'      => (string) ( $version['created_at'] ?? '' ),
				);
			}
		}
		return array_reverse( $out );
	}

	/**
	 * Number of uses of an attachment: base template logo (site), plus
	 * issued certificate revisions that printed it (CRT-024).
	 *
	 * @param int $attachment_id Attachment.
	 * @return array{template: bool, certificates: int}
	 */
	public static function logo_usage( int $attachment_id ): array {
		$template = (array) Settings::get( 'certificates.base_template' );
		$db       = Database::current();
		$count    = (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_certificate' ) . '` WHERE snapshot LIKE %s', '%"attachment_id":' . $attachment_id . ',%' ) );
		$count   += (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_certificate' ) . '` WHERE snapshot LIKE %s', '%"attachment_id":' . $attachment_id . '}%' ) );
		return array(
			'template'     => (int) ( $template['logo_id'] ?? 0 ) === $attachment_id && $attachment_id > 0,
			'certificates' => $count,
		);
	}

	/**
	 * Warning in the media library before deleting a used logo (CRT-024, SOLL).
	 *
	 * @param array<string, mixed> $fields Fields.
	 * @param \WP_Post             $post   Attachment.
	 * @return array<string, mixed>
	 */
	public static function logo_warning_field( $fields, $post ) {
		if ( ! is_array( $fields ) || ! $post instanceof \WP_Post || ! current_user_can( 'ccap_manage_certificates' ) ) {
			return $fields;
		}
		$usage = self::logo_usage( (int) $post->ID );
		if ( ! $usage['template'] && 0 === $usage['certificates'] ) {
			return $fields;
		}
		$text  = $usage['template']
			? __( 'This image is the logo of the certificate template.', 'course-captain' ) . ' '
			: '';
		$text .= sprintf(
			/* translators: %d: number of certificates */
			_n( 'It is printed on %d certificate. If you delete it, the certificate is shown without logo.', 'It is printed on %d certificates. If you delete it, these certificates are shown without logo.', $usage['certificates'], 'course-captain' ),
			$usage['certificates']
		);
		$fields['ccap_logo_use'] = array(
			'label' => __( 'Used by Course Captain', 'course-captain' ),
			'input' => 'html',
			'html'  => '<p role="note"><strong>' . esc_html( $text ) . '</strong></p>',
		);
		return $fields;
	}

	/**
	 * Remember the previous learning area and course slug (E-220).
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $value     New option value.
	 * @return void
	 */
	public static function remember_slug( $old_value, $value ): void {
		$history = self::history();
		foreach ( array( 'learning_area_slug', 'course_slug' ) as $field ) {
			$before = is_array( $old_value ) && is_array( $old_value['values'] ?? null ) ? (string) ( $old_value['values'][ $field ] ?? '' ) : '';
			$after  = is_array( $value ) && is_array( $value['values'] ?? null ) ? (string) ( $value['values'][ $field ] ?? '' ) : '';
			if ( '' === $before && 'learning_area_slug' === $field ) {
				$before = (string) ( \CourseCaptain\Settings\SettingsSchema::defaults( 'general' )[ $field ] ?? '' );
			}
			if ( '' === $after || $before === $after || '' === $before ) {
				continue;
			}
			$list              = array_values( array_diff( $history[ $field ] ?? array(), array( $after, $before ) ) );
			$list[]            = $before;
			$history[ $field ] = array_slice( $list, -20 );
		}
		update_option( self::SLUG_HISTORY, $history, false );
	}

	/**
	 * First save of the general tab: the default slug was in use before.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return void
	 */
	public static function remember_added_slug( $option, $value ): void {
		unset( $option );
		self::remember_slug( array( 'values' => \CourseCaptain\Settings\SettingsSchema::defaults( 'general' ) ), $value );
	}

	/**
	 * Slug history.
	 *
	 * @return array<string, list<string>>
	 */
	public static function history(): array {
		$raw = get_option( self::SLUG_HISTORY, array() );
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $field => $list ) {
			if ( is_string( $field ) && is_array( $list ) ) {
				$out[ $field ] = array_values( array_filter( array_map( 'strval', $list ) ) );
			}
		}
		return $out;
	}

	/**
	 * Target of an old certificate path (`/<old>/nachweis…`), or null.
	 *
	 * @param string $path Request path (without home path).
	 * @return string|null Absolute URL.
	 */
	public static function redirect_target( string $path ): ?string {
		$path    = '/' . ltrim( $path, '/' );
		$current = \CourseCaptain\Learner\LearningArea::slug();
		foreach ( self::history()['learning_area_slug'] ?? array() as $old ) {
			if ( $old === $current || 1 !== preg_match( '#^/' . preg_quote( $old, '#' ) . '/(' . CertificatePages::VERIFY_PATH . '/[0-9a-zA-Z]{1,64}|' . CertificatePages::LIST_PATH . '(?:/[0-9a-f-]{36})?)/?$#', $path, $m ) ) {
				continue;
			}
			// E-220: only while no page uses the old slug.
			if ( null !== get_page_by_path( $old ) ) {
				return null;
			}
			return \CourseCaptain\Learner\LearningArea::url( $m[1] );
		}
		return null;
	}

	/**
	 * 301 for old certificate links (`template_redirect`).
	 *
	 * @return void
	 */
	public static function redirect_old_slug(): void {
		if ( ! is_404() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = (string) wp_parse_url( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $home && '/' !== $home && 0 === strpos( $path, rtrim( $home, '/' ) ) ) {
			$path = substr( $path, strlen( rtrim( $home, '/' ) ) );
		}
		$target = self::redirect_target( $path );
		if ( null !== $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	}
}
