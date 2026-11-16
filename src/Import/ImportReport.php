<?php
/**
 * Per-object import report: rows, summary, CSV/JSON export (R0-38 §2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Dry run, commit and activation write into the same structure
 * (`ccap_import_issue`): one row per finding at one object with rule code
 * (`IMP-…` or `VAL-…`), severity, class (F/O/W/T/N), action, phase and a
 * fingerprint over rule, object, path and parameters, so a finding of the
 * dry run and the same finding in the commit are recognisable.
 *
 * Messages are rendered on read in the reader's language; rows hold no
 * learner data, no server paths and no raw exception texts (only package
 * paths such as `data/questions/000001.json#12` and error codes).
 */
final class ImportReport {

	public const VERSION      = '1';
	public const MAX_ISSUES   = 100000;
	public const INSERT_BLOCK = 200;
	public const EXPORT_BLOCK = 1000;
	public const FORMATS      = array( 'json', 'csv' );
	public const CSV_COLUMNS  = array( 'seq', 'severity', 'class', 'rule', 'entity', 'source_id', 'file', 'path', 'action', 'message', 'suggestion', 'decision', 'phase', 'attempt' );
	public const FILTERS      = array( 'severity', 'class', 'rule', 'phase', 'entity' );

	/**
	 * Internal rule → stable rule code (R0-38 §2.3).
	 *
	 * @var array<string, string>
	 */
	public const CODES = array(
		'schema'              => 'IMP-V6.01',
		'unknown_type'        => 'IMP-V6.01',
		'duplicate_source_id' => 'IMP-V6.02',
		'missing_reference'   => 'IMP-V6.03',
		'hash_mismatch'       => 'IMP-V6.06',
		'lease_lost'          => 'IMP-B1.02',
		'stale'               => 'IMP-B1.02',
		'transient'           => 'IMP-B1.03',
		'write_failed'        => 'IMP-B1.04',
		'threshold'           => 'IMP-B1.05',
		'superseded'          => 'IMP-B1.06',
		'package_missing'     => 'IMP-B1.07',
		'dependency_failed'   => 'IMP-B1.08',
		'cancelled'           => 'IMP-B1.09',
		'approver_rights'     => 'IMP-B1.10',
		'error'               => 'IMP-B1.11',
	);

	/**
	 * Rule code of an internal rule (VAL codes pass through).
	 *
	 * @param string $rule Internal rule or code.
	 * @return string
	 */
	public static function code( string $rule ): string {
		return self::CODES[ $rule ] ?? $rule;
	}

	/**
	 * Issue row for `ccap_import_issue`.
	 *
	 * @param Database             $db    Database access.
	 * @param int                  $batch Batch.
	 * @param string               $phase preview|commit|activate|run.
	 * @param string               $entity Entity (`questions`, `courses`, `batch`, …).
	 * @param string|null          $source Source ID.
	 * @param string               $rule  Internal rule or `VAL-nnn`.
	 * @param string               $severity fatal|error|blocker|warning|notice.
	 * @param string               $issue_class F|O|W|T|N.
	 * @param string|null          $action Planned or performed action.
	 * @param array<string, mixed> $params Message parameters (no personal data).
	 * @param string               $file  Package file and record index.
	 * @param string|null          $message_key Message key (default `cc.imp.<rule>`).
	 * @return array<string, mixed>
	 */
	public static function issue( Database $db, int $batch, string $phase, string $entity, ?string $source, string $rule, string $severity, string $issue_class, ?string $action, array $params = array(), string $file = '', ?string $message_key = null ): array {
		$code = self::code( $rule );
		ksort( $params );
		$json = array() === $params ? null : (string) wp_json_encode( $params );
		return array(
			'batch_id'    => $batch,
			'attempt'     => 1,
			'phase'       => substr( $phase, 0, 16 ),
			'entity'      => substr( $entity, 0, 32 ),
			'source_id'   => null === $source ? null : substr( $source, 0, 191 ),
			'file_ref'    => '' === $file ? null : substr( $file, 0, 255 ),
			'rule'        => substr( $code, 0, 64 ),
			'severity'    => $severity,
			'class'       => $issue_class,
			'action'      => null === $action ? null : substr( $action, 0, 16 ),
			'message_key' => substr( $message_key ?? 'cc.imp.' . $rule, 0, 64 ),
			'params'      => $json,
			// Without the batch: the same finding in dry run and commit has the same fingerprint (BIG-008).
			'fingerprint' => hash( 'sha256', $code . '|' . $entity . '|' . (string) $source . '|' . (string) $json ),
			'created_at'  => $db->now(),
		);
	}

	/**
	 * Multi-row insert (one statement per block, explicit formats, NULL kept).
	 *
	 * @param Database                   $db    Database access.
	 * @param string                     $table Table without prefix.
	 * @param list<array<string, mixed>> $rows  Rows with identical keys.
	 * @return void
	 */
	public static function insert_many( Database $db, string $table, array $rows ): void {
		if ( array() === $rows ) {
			return;
		}
		$columns = array_keys( $rows[0] );
		foreach ( $columns as $column ) {
			Database::assert_identifier( (string) $column );
		}
		foreach ( array_chunk( $rows, self::INSERT_BLOCK ) as $block ) {
			$values = array();
			foreach ( $block as $row ) {
				$cells = array();
				foreach ( $columns as $column ) {
					$value   = $row[ $column ] ?? null;
					$cells[] = null === $value ? 'NULL' : ( is_int( $value ) ? (string) $value : $db->prepare( '%s', (string) $value ) );
				}
				$values[] = '(' . implode( ',', $cells ) . ')';
			}
			$db->execute( 'INSERT INTO `' . $db->table( $table ) . '` (`' . implode( '`,`', $columns ) . '`) VALUES ' . implode( ',', $values ) );
		}
	}

	/**
	 * Report rows after a keyset cursor.
	 *
	 * @param Database              $db      Database access.
	 * @param int                   $batch   Batch.
	 * @param array<string, string> $filters Filters (see FILTERS).
	 * @param int                   $after   Last seen `seq`.
	 * @param int                   $limit   Rows (≤ EXPORT_BLOCK).
	 * @return list<array<string, mixed>>
	 */
	public static function rows( Database $db, int $batch, array $filters, int $after = 0, int $limit = self::EXPORT_BLOCK ): array {
		$where = $db->prepare( 'batch_id = %d AND id > %d', $batch, $after );
		foreach ( self::FILTERS as $field ) {
			if ( isset( $filters[ $field ] ) && '' !== $filters[ $field ] ) {
				$where .= $db->prepare( " AND `{$field}` = %s", $filters[ $field ] );
			}
		}
		$out = array();
		foreach ( $db->rows( 'SELECT id, attempt, phase, entity, source_id, file_ref, rule, severity, class, action, message_key, params, decision, fingerprint FROM `' . $db->table( 'ccap_import_issue' ) . "` WHERE {$where} ORDER BY id LIMIT " . max( 1, min( self::EXPORT_BLOCK, $limit ) ) ) as $row ) {
			$params = json_decode( (string) $row['params'], true );
			$params = is_array( $params ) ? $params : array();
			$text   = self::message( (string) $row['message_key'], (string) $row['rule'], $params );
			unset( $params['message'] );
			$out[] = array(
				'seq'         => (int) $row['id'],
				'severity'    => (string) $row['severity'],
				'class'       => (string) $row['class'],
				'rule'        => (string) $row['rule'],
				'entity'      => (string) $row['entity'],
				'source_id'   => null === $row['source_id'] ? null : (string) $row['source_id'],
				'file'        => (string) $row['file_ref'],
				'path'        => '',
				'action'      => (string) $row['action'],
				'message_key' => (string) $row['message_key'],
				'params'      => (object) $params,
				'message'     => $text[0],
				'suggestion'  => $text[1],
				'decision'    => null === $row['decision'] ? null : (string) $row['decision'],
				'phase'       => (string) $row['phase'],
				'attempt'     => (int) $row['attempt'],
				'fingerprint' => 'sha256:' . (string) $row['fingerprint'],
			);
		}
		return $out;
	}

	/**
	 * Summary block (R0-38 §2.5, reduced to what R1 stores).
	 *
	 * @param Database             $db  Database access.
	 * @param array<string, mixed> $row Batch row.
	 * @return array<string, mixed>
	 */
	public static function summary( Database $db, array $row ): array {
		$batch   = (int) $row['id'];
		$stats   = (array) json_decode( (string) $row['stats'], true );
		$actions = array();
		$outcome = array();
		foreach ( $db->rows( $db->prepare( 'SELECT entity, action, outcome, COUNT(*) AS n FROM `' . $db->table( 'ccap_import_item_log' ) . '` WHERE batch_id = %d GROUP BY entity, action, outcome', $batch ) ) as $r ) {
			$actions[ (string) $r['entity'] ][ (string) $r['action'] ] = ( $actions[ (string) $r['entity'] ][ (string) $r['action'] ] ?? 0 ) + (int) $r['n'];
			$outcome[ (string) $r['outcome'] ]                         = ( $outcome[ (string) $r['outcome'] ] ?? 0 ) + (int) $r['n'];
		}
		$severity = array();
		$class    = array();
		$rules    = array();
		foreach ( $db->rows( $db->prepare( 'SELECT severity, class, rule, COUNT(*) AS n FROM `' . $db->table( 'ccap_import_issue' ) . '` WHERE batch_id = %d GROUP BY severity, class, rule', $batch ) ) as $r ) {
			$severity[ (string) $r['severity'] ] = ( $severity[ (string) $r['severity'] ] ?? 0 ) + (int) $r['n'];
			$class[ (string) $r['class'] ]       = ( $class[ (string) $r['class'] ] ?? 0 ) + (int) $r['n'];
			$rules[ (string) $r['rule'] ]        = ( $rules[ (string) $r['rule'] ] ?? 0 ) + (int) $r['n'];
		}
		arsort( $rules );
		$run = (array) ( $stats['run'] ?? array() );
		return array(
			'plan'         => (object) ( $stats['plan'] ?? array() ),
			'actions'      => (object) ( array() === $actions ? ( $stats['plan'] ?? array() ) : $actions ),
			'outcomes'     => (object) $outcome,
			'severity'     => (object) $severity,
			'class'        => (object) $class,
			'top_rules'    => (object) array_slice( $rules, 0, 10, true ),
			'held_courses' => count( (array) ( $run['held'] ?? array() ) ),
			'dry_run'      => (object) ( $stats['dry_run'] ?? array() ),
		);
	}

	/**
	 * Export (authorised; R0-38 §2.4). JSON pages by keyset, CSV in one file.
	 *
	 * @param Actor                 $actor   Importer.
	 * @param int                   $batch   Batch.
	 * @param string                $format  json|csv.
	 * @param array<string, string> $filters Filters.
	 * @param int                   $after   JSON: last seen `seq`.
	 * @param int                   $limit   JSON: page size.
	 * @return array<string, mixed>|string JSON document or CSV text.
	 * @throws CourseException 400 for an unknown format, 403/404 without rights.
	 */
	public static function export( Actor $actor, int $batch, string $format, array $filters = array(), int $after = 0, int $limit = self::EXPORT_BLOCK ) {
		if ( ! in_array( $format, self::FORMATS, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'format', 400 );
		}
		$service = ImportService::current();
		$row     = $service->authorize( $actor, $batch, 'report', array( 'uploaded', 'validated', 'failed', 'previewed', 'approved', 'importing', 'activating', 'published', 'superseded', 'aborted', 'discarded' ) );
		$db      = Database::current();
		$filters = array_intersect_key( array_map( 'strval', $filters ), array_flip( self::FILTERS ) );
		if ( 'csv' === $format || 0 === $after ) {
			AuditLog::write(
				$db,
				$actor,
				'import_report_exported',
				'import_batch',
				(string) $batch,
				null,
				array(
					'format'  => $format,
					'filters' => $filters,
				)
			);
		}
		if ( 'csv' === $format ) {
			return self::csv( $db, $batch, $filters );
		}
		$manifest = (array) json_decode( (string) $row['manifest'], true );
		$issues   = self::rows( $db, $batch, $filters, $after, $limit );
		$last     = array() === $issues ? null : end( $issues )['seq'];
		return array(
			'report_version' => self::VERSION,
			'batch_id'       => $batch,
			'package_id'     => (string) $row['package_id'],
			'source_system'  => (string) ( $manifest['source']['system'] ?? '' ),
			'status'         => (string) $row['status'],
			'generated_at'   => gmdate( 'c' ),
			'summary'        => self::summary( $db, $row ),
			'issues'         => $issues,
			'next'           => count( $issues ) < max( 1, min( self::EXPORT_BLOCK, $limit ) ) ? null : $last,
		);
	}

	/**
	 * CSV (UTF-8 with BOM, RFC 4180, CRLF, injection-safe), read in blocks.
	 *
	 * @param Database              $db      Database access.
	 * @param int                   $batch   Batch.
	 * @param array<string, string> $filters Filters.
	 * @return string
	 */
	public static function csv( Database $db, int $batch, array $filters ): string {
		$out = fopen( 'php://temp/maxmemory:2097152', 'w+b' );
		if ( false === $out ) {
			return '';
		}
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Stream to php://temp.
		fputcsv( $out, self::CSV_COLUMNS, ',', '"', '', "\r\n" );
		$after = 0;
		do {
			$rows = self::rows( $db, $batch, $filters, $after );
			$full = self::EXPORT_BLOCK === count( $rows );
			foreach ( $rows as $row ) {
				$after = (int) $row['seq'];
				fputcsv( $out, array_map( static fn( string $column ): string => self::cell( $row[ $column ] ?? '' ), self::CSV_COLUMNS ), ',', '"', '', "\r\n" );
			}
		} while ( $full );
		rewind( $out );
		$csv = (string) stream_get_contents( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Stream to php://temp.
		return $csv;
	}

	/**
	 * CSV cell; formula starts get a leading apostrophe (BIG-004).
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function cell( $value ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		return '' !== $text && in_array( $text[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $text : $text;
	}

	/**
	 * Message and suggestion in the reader's language.
	 *
	 * @param string               $key    Message key.
	 * @param string               $rule   Rule code.
	 * @param array<string, mixed> $params Parameters.
	 * @return array{0: string, 1: string}
	 */
	public static function message( string $key, string $rule, array $params ): array {
		if ( str_starts_with( $rule, 'VAL-' ) ) {
			return array( (string) ( $params['message'] ?? $rule ), __( 'Correct the course after the import and publish it in the editor, or fix the package and import it again.', 'course-captain' ) );
		}
		switch ( $key ) {
			case 'cc.imp.schema':
				return array( __( 'The record does not match the package format.', 'course-captain' ), __( 'Correct the record in the source system and export the package again.', 'course-captain' ) );
			case 'cc.imp.unknown_type':
				return array( __( 'The question type is not available on this site.', 'course-captain' ), __( 'Activate the extension that provides the question type, then import again.', 'course-captain' ) );
			case 'cc.imp.duplicate_source_id':
				return array( __( 'The object ID occurs more than once in the package.', 'course-captain' ), __( 'Give every object a unique ID in the source system.', 'course-captain' ) );
			case 'cc.imp.missing_reference':
				return array( __( 'The object refers to another object that is neither in the package nor on this site.', 'course-captain' ), __( 'Add the referenced object to the package or remove the reference.', 'course-captain' ) );
			case 'cc.imp.hash_mismatch':
				return array( __( 'The record was changed after the package was created (checksum differs).', 'course-captain' ), __( 'Export the package again from the source system.', 'course-captain' ) );
			case 'cc.imp.dependency_failed':
				return array( __( 'The object depends on another object that could not be imported.', 'course-captain' ), __( 'Fix the object it depends on; this object is imported with it.', 'course-captain' ) );
			case 'cc.imp.write_failed':
				return array( __( 'The object could not be saved.', 'course-captain' ), __( 'Check the object in the source system and import the package again.', 'course-captain' ) );
			case 'cc.imp.threshold':
				return array( __( 'Too many objects failed, so the import was stopped.', 'course-captain' ), __( 'Look for a common cause in the report, fix it and import again.', 'course-captain' ) );
			case 'cc.imp.cancelled':
				return array( __( 'The import was cancelled.', 'course-captain' ), __( 'Discard the import or resume it.', 'course-captain' ) );
			case 'cc.imp.lease_lost':
			case 'cc.imp.stale':
				return array( __( 'The import stopped responding.', 'course-captain' ), __( 'Resume the import.', 'course-captain' ) );
			case 'cc.imp.transient':
				return array( __( 'The database was temporarily unavailable.', 'course-captain' ), __( 'The import resumes automatically; otherwise resume it.', 'course-captain' ) );
			case 'cc.imp.superseded':
				return array( __( 'A newer import of the same source was activated in the meantime.', 'course-captain' ), __( 'Discard this import.', 'course-captain' ) );
			case 'cc.imp.package_missing':
				return array( __( 'The stored package copy is missing or was changed.', 'course-captain' ), __( 'Upload and validate the package again.', 'course-captain' ) );
			case 'cc.imp.approver_rights':
				return array( __( 'The person who approved the import no longer has the required rights.', 'course-captain' ), __( 'Approve and start the import again with an account that may publish courses.', 'course-captain' ) );
		}
		return array( __( 'The import stopped because of an unexpected error.', 'course-captain' ), __( 'Resume the import; if it stops again, contact support with the report.', 'course-captain' ) );
	}
}
