<?php
/**
 * Numbered migration step.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * A numbered, idempotent migration step `<module>:<NNNN>_<slug>` (R0-37 §4.1).
 *
 * Steps are immutable once released; the checksum of the definition is
 * stored in `ccap_migration_step.checksum` (MIG-023). `is_applied()` checks
 * the actual state (information_schema, counts), not the status (I-M1).
 */
abstract class Step {

	public const KIND_STRUCTURE = 'structure';
	public const KIND_DATA      = 'data';
	public const KIND_BACKFILL  = 'backfill';
	public const KIND_CONTRACT  = 'contract';

	/**
	 * Constructor.
	 *
	 * @param string       $id          Step id, e.g. `core:0001_exam_session_running`.
	 * @param string       $kind        One of the KIND_* constants.
	 * @param string       $released_in Plugin version that introduced the step.
	 * @param list<string> $requires    Step ids that must be `done` first.
	 */
	public function __construct(
		private string $id,
		private string $kind,
		private string $released_in,
		private array $requires = array(),
	) {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_-]*:\d{4}_[a-z0-9_]+$/', $id ) ) {
			throw new \InvalidArgumentException( 'Invalid step id ' . $id ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message.
		}
	}

	/**
	 * Step id.
	 *
	 * @return string
	 */
	final public function id(): string {
		return $this->id;
	}

	/**
	 * Module part of the id (`core`, `pro`, …).
	 *
	 * @return string
	 */
	final public function module(): string {
		return strstr( $this->id, ':', true ) ?: 'core';
	}

	/**
	 * Kind of step.
	 *
	 * @return string
	 */
	final public function kind(): string {
		return $this->kind;
	}

	/**
	 * Plugin version that introduced the step.
	 *
	 * @return string
	 */
	final public function released_in(): string {
		return $this->released_in;
	}

	/**
	 * Step ids that must be done first.
	 *
	 * @return list<string>
	 */
	final public function requires(): array {
		return $this->requires;
	}

	/**
	 * Checksum of the definition (sha1, MIG-023).
	 *
	 * @return string
	 */
	public function checksum(): string {
		return sha1( $this->id . '|' . $this->kind . '|' . $this->definition() );
	}

	/**
	 * Stable text form of what the step does (DDL, predicate, …).
	 *
	 * @return string
	 */
	abstract public function definition(): string;

	/**
	 * Whether the actual state already matches the target (I-M1).
	 *
	 * @param MigrationContext $context Context.
	 * @return bool
	 */
	abstract public function is_applied( MigrationContext $context ): bool;
}
