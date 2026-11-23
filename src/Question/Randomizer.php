<?php
/**
 * Seeded, platform-independent randomness (R0-08 §3.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Question;

defined( 'ABSPATH' ) || exit;

/**
 * Derived streams `HMAC-SHA256(seed, purpose || counter)`; Fisher-Yates with
 * rejection sampling (no modulo bias). The materialized order is stored;
 * the seed only serves reproducibility, tests and audit.
 */
final class Randomizer {

	public const VERSION = 1;

	/**
	 * Counter per purpose.
	 *
	 * @var array<string, int>
	 */
	private array $counters = array();

	/**
	 * Constructor.
	 *
	 * @param string $seed Binary or numeric seed.
	 */
	public function __construct( private string $seed ) {
	}

	/**
	 * Randomizer with a fresh 64-bit seed from the CSPRNG.
	 *
	 * @return self
	 */
	public static function fresh(): self {
		return new self( (string) hexdec( bin2hex( random_bytes( 6 ) ) ) );
	}

	/**
	 * The seed (for snapshots and tokens).
	 *
	 * @return string
	 */
	public function seed(): string {
		return $this->seed;
	}

	/**
	 * Uniform integer in [0, $max] for a purpose.
	 *
	 * @param string $purpose Stream name, e.g. `answers:<uid>`.
	 * @param int    $max     Upper bound (inclusive), ≥ 0.
	 * @return int
	 */
	public function int( string $purpose, int $max ): int {
		if ( $max <= 0 ) {
			return 0;
		}
		$range = $max + 1;
		$limit = intdiv( 0xFFFFFFFF, $range ) * $range;
		do {
			$counter                    = $this->counters[ $purpose ] ?? 0;
			$this->counters[ $purpose ] = $counter + 1;
			$value                      = (int) hexdec( substr( hash_hmac( 'sha256', $purpose . '|' . $counter, $this->seed ), 0, 8 ) );
		} while ( $value >= $limit );
		return $value % $range;
	}

	/**
	 * Fisher-Yates shuffle of a list.
	 *
	 * @template T
	 * @param array  $items   Items.
	 * @phpstan-param list<T> $items
	 * @param string $purpose Stream name.
	 * @return array<int, T>
	 */
	public function shuffle( array $items, string $purpose ): array {
		for ( $i = count( $items ) - 1; $i > 0; $i-- ) {
			$j           = $this->int( $purpose, $i );
			$tmp         = $items[ $i ];
			$items[ $i ] = $items[ $j ];
			$items[ $j ] = $tmp;
		}
		return $items;
	}
}
