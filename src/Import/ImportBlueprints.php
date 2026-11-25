<?php
/**
 * Exam blueprint entity of `cc-package` v1 (R0-10 §1.3, §3.4, §3.6).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * A blueprint record becomes an exam rule (`ccap_exam_rule` with module
 * draws in `ccap_exam_blueprint`) plus its fixed variants
 * (`ccap_exam_variant`, source ID `<blueprint>#<key>`, number = position).
 *
 * Record fields: `course`, `version`, `status` (`draft`/`published`, E-23;
 * missing = `draft`), `time_limit_s` (null = inherit), `passing_rule
 * {min_ratio {num, den}, parts[] {key, modules[], min_correct, of}}`,
 * `draws[] {module, count}`, `fixed_variants[] {key, questions[]}`,
 * optional `settings {purpose}`. There is no default pass mark
 * (INV-C-132): a record without `min_ratio` is an object error.
 *
 * Schema 1.1 (course export, E-382) adds optional fields: `draws[].points`
 * (1–100, default 1), `passing_rule.parts[].label`,
 * `passing_rule.empty_part_policy` (`pass`/`fail`), `settings.form_mode`
 * (`random`/`variants`, UI-F8) and `settings.overrides` (register key →
 * value of the exam rule scope, R0-28); `draws` may be empty when the
 * record has fixed variants. Overrides of a setting the target site has
 * locked are dropped on import (the lock applies anyway, E-225).
 *
 * A published rule never changes (R0-08): a changed record creates a new
 * rule version; a draft that was never activated is replaced. Commit
 * writes drafts only; the rule is published and its variants are written
 * in the activation of the course (learners see the old state until then).
 * A record that returns with the hash of an archived version reactivates
 * that version (rollback).
 */
final class ImportBlueprints {

	public const STATUSES = array( 'draft', 'published' );
	public const PURPOSES = array( 'evidence', 'practice' );

	/**
	 * Constructor.
	 *
	 * @param Database $db Database access.
	 */
	public function __construct( private Database $db ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current() );
	}

	/**
	 * V6 rule of a blueprint record, or null.
	 *
	 * @param array<string, mixed> $record Record.
	 * @return string|null
	 */
	public static function record_error( array $record ): ?string {
		$version = (string) ( $record['version'] ?? '' );
		if ( '' === (string) ( $record['course'] ?? '' ) || '' === trim( $version ) || strlen( $version ) > 48 ) {
			return 'schema';
		}
		if ( isset( $record['status'] ) && ! in_array( (string) $record['status'], self::STATUSES, true ) ) {
			return 'schema';
		}
		$limit = $record['time_limit_s'] ?? null;
		if ( null !== $limit && ( ! is_int( $limit ) || $limit < 1 || $limit > 86400 ) ) {
			return 'schema';
		}
		$settings = is_array( $record['settings'] ?? array() ) ? (array) ( $record['settings'] ?? array() ) : null;
		if ( null === $settings || array() !== array_diff( array_keys( $settings ), array( 'purpose', 'form_mode', 'overrides' ) ) || ( isset( $settings['purpose'] ) && ! in_array( $settings['purpose'], self::PURPOSES, true ) ) ) {
			return 'schema';
		}
		if ( isset( $settings['form_mode'] ) && ! in_array( $settings['form_mode'], ExamRules::FORM_MODES, true ) ) {
			return 'schema';
		}
		if ( isset( $settings['overrides'] ) && null === self::overrides( $settings['overrides'] ) ) {
			return 'schema';
		}
		$draws = $record['draws'] ?? null;
		// Schema 1.1: a blueprint of fixed variants only may have no draws.
		if ( ! is_array( $draws ) || ( array() === $draws && array() === (array) ( $record['fixed_variants'] ?? array() ) ) ) {
			return 'schema';
		}
		foreach ( $draws as $draw ) {
			// Draws by tag are part of the format but not of the exam engine (module blueprints only).
			if ( ! is_array( $draw ) || isset( $draw['tag'] ) || '' === (string) ( $draw['module'] ?? '' ) || ! is_int( $draw['count'] ?? null ) || $draw['count'] < 1 ) {
				return 'schema';
			}
			if ( isset( $draw['points'] ) && ( ! is_int( $draw['points'] ) || $draw['points'] < 1 || $draw['points'] > 100 ) ) {
				return 'schema';
			}
		}
		foreach ( (array) ( $record['fixed_variants'] ?? array() ) as $variant ) {
			if ( ! is_array( $variant ) || '' === (string) ( $variant['key'] ?? '' ) || ! is_array( $variant['questions'] ?? null ) || array() === $variant['questions'] ) {
				return 'schema';
			}
		}
		// Shape check only: distinct placeholder IDs per source ID (references are checked separately).
		$ids         = array();
		$placeholder = static function ( string $source ) use ( &$ids ): int {
			$ids[ $source ] = $ids[ $source ] ?? count( $ids ) + 1;
			return $ids[ $source ];
		};
		$passing     = is_array( $record['passing_rule'] ?? null ) ? self::passing( $record['passing_rule'], $placeholder ) : null;
		return null === $passing || array() !== ExamRules::validate_passing_rule( $passing ) ? 'schema' : null;
	}

	/**
	 * References of a blueprint record by object type.
	 *
	 * @param array<string, mixed> $record Record.
	 * @return array<string, list<string>>
	 */
	public static function references( array $record ): array {
		$modules = array();
		foreach ( (array) ( $record['draws'] ?? array() ) as $draw ) {
			$modules[] = (string) ( is_array( $draw ) ? ( $draw['module'] ?? '' ) : '' );
		}
		foreach ( (array) ( $record['passing_rule']['parts'] ?? array() ) as $part ) {
			foreach ( (array) ( is_array( $part ) ? ( $part['modules'] ?? array() ) : array() ) as $module ) {
				$modules[] = (string) $module;
			}
		}
		$questions = array();
		foreach ( (array) ( $record['fixed_variants'] ?? array() ) as $variant ) {
			foreach ( (array) ( is_array( $variant ) ? ( $variant['questions'] ?? array() ) : array() ) as $question ) {
				$questions[] = (string) $question;
			}
		}
		return array(
			'course'   => array( (string) ( $record['course'] ?? '' ) ),
			'module'   => array_values( array_unique( $modules ) ),
			'question' => array_values( array_unique( $questions ) ),
		);
	}

	/**
	 * Write the draft rule of a record (commit phase).
	 *
	 * @param Actor                          $actor    Importer.
	 * @param string                         $system   Source system.
	 * @param string                         $package  Package ID.
	 * @param array<string, mixed>           $record   Record.
	 * @param int|null                       $existing Rule mapped to the record.
	 * @param callable(string, string): ?int $mapped Object ID of `type, source_id`.
	 * @return array{object: int, payload: array<string, mixed>}
	 * @throws CourseException When a referenced object is missing or the rule is invalid.
	 */
	public function write( Actor $actor, string $system, string $package, array $record, ?int $existing, callable $mapped ): array {
		$course = $mapped( 'course', (string) $record['course'] );
		if ( null === $course ) {
			throw new CourseException( 'ccap_import_failed', 'Course of the blueprint is missing.', 422 );
		}
		$module   = static function ( string $source ) use ( $mapped ): int {
			$id = $mapped( 'module', $source );
			if ( null === $id ) {
				throw new CourseException( 'ccap_import_failed', 'Module of the blueprint is missing.', 422 );
			}
			return $id;
		};
		$variants = array();
		foreach ( array_values( (array) ( $record['fixed_variants'] ?? array() ) ) as $i => $variant ) {
			$ids = array();
			foreach ( (array) $variant['questions'] as $question ) {
				$id = $mapped( 'question', (string) $question );
				if ( null === $id ) {
					throw new CourseException( 'ccap_import_failed', 'Question of a fixed variant is missing.', 422 );
				}
				$ids[] = $id;
			}
			$variants[] = array(
				'source_id' => substr( (string) $record['source_id'] . '#' . (string) $variant['key'], 0, 191 ),
				'no'        => $i + 1,
				'questions' => $ids,
			);
		}
		$payload = array(
			'course'   => $course,
			'publish'  => 'published' === ( $record['status'] ?? 'draft' ),
			'variants' => $variants,
		);
		$table   = $this->db->table( 'ccap_exam_rule' );
		$row     = null === $existing ? null : $this->db->row( $this->db->prepare( "SELECT id, status, source_hash FROM `{$table}` WHERE id = %d", $existing ) );
		if ( null !== $row && (string) ( $record['hash'] ?? '' ) === (string) $row['source_hash'] ) {
			// Known version (e.g. rollback): reactivated in the activation.
			return array(
				'object'  => (int) $row['id'],
				'payload' => $payload + array( 'reactivate' => true ),
			);
		}
		if ( null !== $row && 'draft' === $row['status'] ) {
			// A draft that was never activated is replaced.
			$this->delete( (int) $row['id'] );
		} elseif ( null !== $row ) {
			$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET source_id = NULL WHERE id = %d", (int) $row['id'] ) );
		}
		$rules   = ExamRules::current();
		$version = substr( sanitize_text_field( (string) $record['version'] ), 0, 48 );
		if ( null !== $this->db->scalar( $this->db->prepare( "SELECT id FROM `{$table}` WHERE course_id = %d AND version = %s", $course, $version ) ) ) {
			// A changed record is a new version; the label stays readable (R0-08: published rules never change).
			$version .= '-' . substr( str_replace( 'sha256:', '', (string) $record['hash'] ), 0, 8 );
		}
		$settings  = isset( $record['settings']['purpose'] ) ? array( 'purpose' => (string) $record['settings']['purpose'] ) : array();
		$overrides = array_diff_key( (array) self::overrides( $record['settings']['overrides'] ?? array() ), EffectiveSettings::locks() );
		if ( array() !== $overrides ) {
			$settings['overrides'] = $overrides;
		}
		$passing = self::passing( (array) $record['passing_rule'], $module );
		if ( null === $passing ) {
			throw new CourseException( 'ccap_import_failed', 'Invalid passing rule.', 422 );
		}
		$rule = $rules->create( $actor, $course, $version, $passing, isset( $record['time_limit_s'] ) ? (int) $record['time_limit_s'] : null, $settings );
		foreach ( (array) $record['draws'] as $draw ) {
			$rules->set_blueprint( $actor, $rule, $module( (string) $draw['module'] ), (int) $draw['count'], (int) ( $draw['points'] ?? 1 ) );
		}
		if ( isset( $record['settings']['form_mode'] ) ) {
			$rules->set_form_mode( $actor, $rule, (string) $record['settings']['form_mode'] );
		}
		$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET source_system = %s, source_id = %s, source_hash = %s, package_id = %s, imported_at = %s WHERE id = %d", $system, (string) $record['source_id'], (string) ( $record['hash'] ?? '' ), $package, $this->db->now(), $rule ) );
		return array(
			'object'  => $rule,
			'payload' => $payload,
		);
	}

	/**
	 * Activation of a written rule: publish it (the previous published
	 * version of the course is retired) and write its fixed variants.
	 *
	 * @param Actor                $actor   Importer.
	 * @param int                  $rule_id Rule.
	 * @param array<string, mixed> $payload Payload from {@see write()}.
	 * @return void
	 * @throws CourseException When publishing fails.
	 */
	public function activate( Actor $actor, int $rule_id, array $payload ): void {
		$table = $this->db->table( 'ccap_exam_rule' );
		$row   = $this->db->row( $this->db->prepare( "SELECT id, course_id, status, source_system FROM `{$table}` WHERE id = %d", $rule_id ) );
		if ( null === $row ) {
			return;
		}
		$publish = ! empty( $payload['publish'] );
		if ( $publish && 'draft' === $row['status'] ) {
			ExamRules::current()->publish( $actor, $rule_id );
		} elseif ( $publish && 'retired' === $row['status'] ) {
			$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET status = 'retired', updated_at = %s WHERE course_id = %d AND status = 'published' AND id <> %d", $this->db->now(), (int) $row['course_id'], $rule_id ) );
			$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET status = 'published', updated_at = %s WHERE id = %d", $this->db->now(), $rule_id ) );
		}
		$variants = $this->db->table( 'ccap_exam_variant' );
		$items    = $this->db->table( 'ccap_exam_variant_question' );
		$course   = (int) $row['course_id'];
		foreach ( (array) ( $payload['variants'] ?? array() ) as $variant ) {
			$id = $this->db->scalar( $this->db->prepare( "SELECT id FROM `{$variants}` WHERE source_system = %s AND source_id = %s", (string) $row['source_system'], (string) $variant['source_id'] ) );
			if ( null === $id ) {
				$number = (int) $variant['no'];
				if ( null !== $this->db->scalar( $this->db->prepare( "SELECT id FROM `{$variants}` WHERE course_id = %d AND variant_no = %d", $course, $number ) ) ) {
					// The number is taken by a local variant: next free number, never overwritten.
					$number = 1 + (int) $this->db->scalar( $this->db->prepare( "SELECT MAX(variant_no) FROM `{$variants}` WHERE course_id = %d", $course ) );
				}
				$id = $this->db->insert(
					'ccap_exam_variant',
					array(
						'uid'           => $this->db->uuid(),
						'source_system' => (string) $row['source_system'],
						'source_id'     => (string) $variant['source_id'],
						'course_id'     => $course,
						'variant_no'    => $number,
						'status'        => $publish ? 'published' : 'draft',
						'sort_order'    => $number,
					),
					array( 'created_at', 'updated_at' )
				);
			} else {
				$this->db->execute( $this->db->prepare( "UPDATE `{$variants}` SET status = %s, updated_at = %s WHERE id = %d", $publish ? 'published' : 'draft', $this->db->now(), (int) $id ) );
				$this->db->execute( $this->db->prepare( "DELETE FROM `{$items}` WHERE variant_id = %d", (int) $id ) );
			}
			foreach ( array_values( (array) $variant['questions'] ) as $i => $question ) {
				$this->db->insert(
					'ccap_exam_variant_question',
					array(
						'variant_id'  => (int) $id,
						'question_id' => (int) $question,
						'position'    => $i + 1,
					)
				);
			}
		}
	}

	/**
	 * Archive a rule and its imported variants (never deleted).
	 *
	 * @param int $rule_id Rule.
	 * @return void
	 */
	public function archive( int $rule_id ): void {
		$table = $this->db->table( 'ccap_exam_rule' );
		$row   = $this->db->row( $this->db->prepare( "SELECT source_system, source_id FROM `{$table}` WHERE id = %d", $rule_id ) );
		$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET status = 'retired', updated_at = %s WHERE id = %d AND status IN ('draft', 'published')", $this->db->now(), $rule_id ) );
		if ( null !== $row && '' !== (string) $row['source_id'] ) {
			$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_exam_variant' ) . "` SET status = 'retired', updated_at = %s WHERE source_system = %s AND source_id LIKE %s", $this->db->now(), (string) $row['source_system'], $this->db->wpdb()->esc_like( (string) $row['source_id'] . '#' ) . '%' ) );
		}
	}

	/**
	 * Remove a draft rule that was never activated (discard, replaced draft).
	 *
	 * @param int $rule_id Rule.
	 * @return void
	 */
	public function delete( int $rule_id ): void {
		$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_exam_blueprint' ) . '` WHERE rule_id = %d', $rule_id ) );
		$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_exam_rule' ) . "` WHERE id = %d AND status = 'draft'", $rule_id ) );
	}

	/**
	 * Setting overrides of a record (schema 1.1, R0-28): register keys of
	 * the exam rule scope with valid values, sorted by key; null when the
	 * value is not an object of such overrides.
	 *
	 * @param mixed $overrides `settings.overrides` of a record.
	 * @return array<string, mixed>|null
	 */
	public static function overrides( $overrides ): ?array {
		if ( ! is_array( $overrides ) ) {
			return null;
		}
		$out = array();
		foreach ( $overrides as $key => $value ) {
			if ( ! is_string( $key ) || ! array_key_exists( $key, ExamRules::REGISTER_MAP ) ) {
				return null;
			}
			$check = EffectiveSettings::validate_override( $key, $value, 'exam_rule' );
			if ( ! $check['ok'] ) {
				return null;
			}
			$out[ $key ] = $check['value'];
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Stored passing rule of a package passing rule: `min_ratio {num, den}`,
	 * the optional `empty_part_policy` and parts with one module (`section`)
	 * or a module group (`sections`) and an optional `label` (schema 1.1).
	 *
	 * @param array<string, mixed>  $rule   Package passing rule.
	 * @param callable(string): int $module Module ID of a source ID.
	 * @return array<string, mixed>|null Null when malformed.
	 */
	public static function passing( array $rule, callable $module ): ?array {
		if ( ! is_array( $rule['min_ratio'] ?? null ) || ! is_int( $rule['min_ratio']['num'] ?? null ) || ! is_int( $rule['min_ratio']['den'] ?? null ) ) {
			return null;
		}
		$out = array(
			'min_ratio' => array(
				'num' => $rule['min_ratio']['num'],
				'den' => $rule['min_ratio']['den'],
			),
		);
		if ( isset( $rule['empty_part_policy'] ) ) {
			$out['empty_part_policy'] = $rule['empty_part_policy'];
		}
		if ( ! isset( $rule['parts'] ) ) {
			return $out;
		}
		$out['parts'] = array();
		foreach ( (array) $rule['parts'] as $part ) {
			$modules = is_array( $part ) ? array_values( (array) ( $part['modules'] ?? array() ) ) : array();
			if ( array() === $modules || ! is_int( $part['min_correct'] ?? null ) || ! is_int( $part['of'] ?? null ) ) {
				return null;
			}
			$ids   = array_map( static fn( $m ): int => $module( (string) $m ), $modules );
			$entry = array( 'key' => (string) ( $part['key'] ?? '' ) );
			if ( 1 === count( $ids ) ) {
				$entry['section'] = $ids[0];
			} else {
				$entry['sections'] = $ids;
			}
			$entry['min_ratio'] = array(
				'num' => $part['min_correct'],
				'den' => $part['of'],
			);
			if ( isset( $part['label'] ) ) {
				$entry['label'] = $part['label'];
			}
			$out['parts'][] = $entry;
		}
		return $out;
	}
}
