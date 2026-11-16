<?php
/**
 * Result of evaluating one answer.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Question;

defined( 'ABSPATH' ) || exit;

/**
 * Points in thousandths (integers only, R0-08 §3.2). `correct` = full points.
 * `valid = false` means the input itself is invalid (error, nothing stored),
 * `answered = false` means an empty answer (withdrawn in exams).
 */
final class Evaluation {

	/**
	 * Constructor.
	 *
	 * @param bool $valid        Input valid.
	 * @param bool $answered     Non-empty answer.
	 * @param int  $points_milli Points (thousandths).
	 * @param int  $max_milli    Maximum (thousandths).
	 */
	public function __construct(
		public readonly bool $valid,
		public readonly bool $answered,
		public readonly int $points_milli,
		public readonly int $max_milli,
	) {
	}

	/**
	 * Invalid input.
	 *
	 * @return self
	 */
	public static function invalid(): self {
		return new self( false, false, 0, 0 );
	}

	/**
	 * Empty answer.
	 *
	 * @param int $max Maximum.
	 * @return self
	 */
	public static function empty_answer( int $max ): self {
		return new self( true, false, 0, $max );
	}

	/**
	 * Full points or zero.
	 *
	 * @param bool $correct Correct.
	 * @param int  $max     Maximum.
	 * @return self
	 */
	public static function binary( bool $correct, int $max ): self {
		return new self( true, true, $correct ? $max : 0, $max );
	}

	/**
	 * Full points reached.
	 *
	 * @return bool
	 */
	public function correct(): bool {
		return $this->valid && $this->answered && $this->max_milli > 0 && $this->points_milli === $this->max_milli;
	}
}
