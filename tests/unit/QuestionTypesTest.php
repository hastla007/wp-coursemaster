<?php
/**
 * R1-06: question types, evaluation and randomizer (R0-08 §3, R0-05 §1.22).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Question\QuestionTypes as T;
use CourseCaptain\Question\Randomizer;

/**
 * Pure tests without WordPress.
 *
 * @group R1-06
 */
final class QuestionTypesTest extends TestCase {

	/**
	 * Choice revision helper; option A..E, the given keys are correct.
	 *
	 * @param string $type    Type.
	 * @param int    $count   Number of options.
	 * @param array  $correct Correct keys.
	 * @phpstan-param list<string> $correct
	 * @param array  $settings Settings.
	 * @return array<string, mixed>
	 */
	private static function choice( string $type, int $count, array $correct, array $settings = array() ): array {
		$answers = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$key       = chr( 65 + $i );
			$answers[] = array(
				'key'     => $key,
				'text'    => 'Option ' . $key,
				'correct' => in_array( $key, $correct, true ),
				'sort'    => $i,
			);
		}
		return array(
			'question_type' => $type,
			'question_text' => 'Q',
			'settings'      => $settings,
			'answers'       => $answers,
		);
	}

	/**
	 * Publish validation per type.
	 */
	public function test_validation(): void {
		self::assertSame( array(), T::validate( self::choice( T::SINGLE, 4, array( 'A' ) ) ) );
		self::assertContains( 'exactly_one_correct', T::validate( self::choice( T::SINGLE, 4, array( 'A', 'B' ) ) ) );
		self::assertContains( 'answers_count', T::validate( self::choice( T::MULTI, 1, array( 'A' ) ) ) );
		self::assertContains( 'at_least_one_correct', T::validate( self::choice( T::MULTI, 3, array() ) ) );
		self::assertContains( 'scoring_not_allowed', T::validate( self::choice( T::SINGLE, 3, array( 'A' ), array( 'scoring' => T::PROPORTIONAL_PENALTY ) ) ) );
		self::assertContains( 'scoring_not_allowed', T::validate( self::choice( T::MULTI, 3, array( 'A' ), array( 'scoring' => 'per_option' ) ) ), 'E-77' );
		self::assertSame( array(), T::validate( self::choice( T::MULTI, 3, array( 'A' ), array( 'scoring' => T::PROPORTIONAL_PENALTY ) ) ) );
		self::assertContains(
			'model_answer',
			T::validate(
				array(
					'question_type' => T::OPEN_TEXT,
					'question_text' => 'Q',
					'answers'       => array(),
				)
			)
		);
		self::assertSame(
			array( 'unknown_type' ),
			T::validate(
				array(
					'question_type' => 'chart_task',
					'question_text' => 'Q',
				)
			),
			'chart_task excluded (LB-10)'
		);
		self::assertContains( 'tolerance_invalid', T::validate( self::numeric( '1', 'relative', '101' ) ) );
		self::assertContains(
			'items_duplicate',
			T::validate(
				array(
					'question_type' => T::ORDERING,
					'question_text' => 'Q',
					'settings'      => array( 'items' => array( array( 'text' => 'a' ), array( 'text' => ' A ' ) ) ),
				)
			)
		);
		self::assertContains(
			'gap_answers',
			T::validate(
				self::cloze(
					T::ALL_OR_NOTHING,
					array(
						array(
							'id'       => 'g1',
							'accepted' => array( '' ),
						),
					)
				)
			)
		);
	}

	/**
	 * GM-019: single exactly one, multi exact set (sub- and superset wrong), open self-assessment.
	 *
	 * @group GM-019
	 */
	public function test_GM_019_choice_and_open(): void {
		$single = self::choice( T::SINGLE, 4, array( 'A' ) );
		self::assertTrue( T::evaluate( $single, array( 'A' ) )->correct() );
		self::assertFalse( T::evaluate( $single, array( 'B' ) )->correct() );
		self::assertFalse( T::evaluate( $single, array( 'A', 'B' ) )->valid );

		$multi = self::choice( T::MULTI, 5, array( 'A', 'B' ) );
		self::assertTrue( T::evaluate( $multi, array( 'B', 'A' ) )->correct() );
		self::assertFalse( T::evaluate( $multi, array( 'A' ) )->correct(), 'subset' );
		self::assertFalse( T::evaluate( $multi, array( 'A', 'B', 'C' ) )->correct(), 'superset' );
		self::assertFalse( T::evaluate( $multi, array() )->answered, 'empty = withdrawn' );
		self::assertFalse( T::evaluate( $multi, array( 'Z' ) )->valid, 'only delivered options' );
		self::assertFalse( T::evaluate( $multi, array( 'E' ), array( 'A', 'B', 'C', 'D' ) )->valid );

		$open = array(
			'question_type' => T::OPEN_TEXT,
			'question_text' => 'Q',
			'answers'       => array(
				array(
					'key'     => 'MODEL',
					'text'    => 'Model',
					'correct' => true,
				),
			),
		);
		self::assertTrue( T::evaluate( $open, array( 'known' => true ) )->correct() );
		self::assertFalse( T::evaluate( $open, array( 'known' => false ) )->correct() );
		self::assertFalse( T::evaluate( $open, array() )->valid );
	}

	/**
	 * GM-022 anchor: ticking every option never yields full points.
	 *
	 * @group GM-022
	 */
	public function test_GM_022_ticking_everything_never_wins(): void {
		foreach ( array( T::ALL_OR_NOTHING, T::PROPORTIONAL_PENALTY ) as $scoring ) {
			$multi = self::choice( T::MULTI, 5, array( 'A', 'B' ), array( 'scoring' => $scoring ) );
			$all   = T::evaluate( $multi, array( 'A', 'B', 'C', 'D', 'E' ) );
			self::assertLessThan( $all->max_milli, $all->points_milli, $scoring );
		}
		$partial = T::evaluate( self::choice( T::MULTI, 4, array( 'A', 'B' ) ), array( 'A', 'B', 'C' ), array(), 1000, T::PROPORTIONAL_PENALTY );
		self::assertSame( 500, $partial->points_milli );
	}

	/**
	 * Numeric helper.
	 *
	 * @param string $target    Target.
	 * @param string $kind      absolute|relative.
	 * @param string $tolerance Tolerance.
	 * @return array<string, mixed>
	 */
	private static function numeric( string $target, string $kind, string $tolerance ): array {
		return array(
			'question_type' => T::NUMERIC,
			'question_text' => 'Q',
			'settings'      => array(
				'target'         => $target,
				'tolerance_type' => $kind,
				'tolerance'      => $tolerance,
				'unit'           => 'cm',
			),
		);
	}

	/**
	 * GM-191 (E-22): absolute/relative tolerance, inclusive, comma and dot, invalid ≠ wrong.
	 *
	 * @group GM-191
	 */
	public function test_GM_191_numeric_tolerance(): void {
		$abs = self::numeric( '12,5', 'absolute', '0.1' );
		self::assertTrue( T::evaluate( $abs, '12,4' )->correct() );
		self::assertTrue( T::evaluate( $abs, '12.6' )->correct() );
		self::assertFalse( T::evaluate( $abs, '12,39' )->correct() );
		self::assertSame( T::evaluate( $abs, '12,45' )->points_milli, T::evaluate( $abs, '12.45' )->points_milli );
		foreach ( array( '12,5 cm', '1.234,5', '1,2,3', '12.1234567', 'abc' ) as $bad ) {
			self::assertFalse( T::evaluate( $abs, $bad )->valid, $bad );
		}
		self::assertFalse( T::evaluate( $abs, '' )->answered );

		$rel = self::numeric( '200', 'relative', '5' );
		self::assertTrue( T::evaluate( $rel, '210' )->correct() );
		self::assertTrue( T::evaluate( $rel, '190' )->correct() );
		self::assertFalse( T::evaluate( $rel, '210,01' )->correct() );
		self::assertFalse( T::evaluate( $rel, '189,99' )->correct() );

		$delivery = T::deliver( $abs, new Randomizer( '20300115' ), 'q' );
		self::assertSame( array( 'type', 'text', 'unit' ), array_keys( $delivery['payload'] ), 'no target, no tolerance' );
	}

	/**
	 * GM-192 (E-69): matching with distractor; all-or-nothing and proportional.
	 *
	 * @group GM-192
	 */
	public function test_GM_192_matching(): void {
		$pairs = array(
			array(
				'left'  => 'L-a',
				'right' => 'R-a',
			),
			array(
				'left'  => 'L-b',
				'right' => 'R-b',
			),
			array(
				'left'  => 'L-c',
				'right' => 'R-c',
			),
		);
		$aon   = array(
			'question_type' => T::MATCHING,
			'question_text' => 'Q',
			'settings'      => array(
				'pairs'       => $pairs,
				'distractors' => array( 'R-x' ),
			),
		);
		self::assertSame(
			1000,
			T::evaluate(
				$aon,
				array(
					'L1' => 'R1',
					'L2' => 'R2',
					'L3' => 'R3',
				)
			)->points_milli
		);
		self::assertSame(
			0,
			T::evaluate(
				$aon,
				array(
					'L1' => 'R1',
					'L2' => 'R2',
					'L3' => 'D1',
				)
			)->points_milli
		);

		$prop                        = $aon;
		$prop['settings']['scoring'] = T::PROPORTIONAL_PENALTY;
		self::assertSame(
			333,
			T::evaluate(
				$prop,
				array(
					'L1' => 'R1',
					'L2' => 'R2',
					'L3' => 'D1',
				)
			)->points_milli,
			'distractor counts as wrong'
		);
		self::assertSame(
			666,
			T::evaluate(
				$prop,
				array(
					'L1' => 'R1',
					'L2' => 'R2',
					'L3' => '',
				)
			)->points_milli
		);
		self::assertFalse(
			T::evaluate(
				$prop,
				array(
					'L1' => 'R1',
					'L2' => 'R1',
				)
			)->valid,
			'right entry used twice'
		);

		$a = T::deliver( $aon, new Randomizer( '20300115' ), 'q' );
		$b = T::deliver( $aon, new Randomizer( '20300115' ), 'q' );
		self::assertSame( $a, $b, 'stable with the same seed' );
		self::assertStringNotContainsString(
			'pairs',
			(string) json_encode( $a['payload'] ) // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Unit test without WordPress.
		);
		self::assertCount( 4, $a['payload']['right'] );
	}

	/**
	 * GM-193 (E-69): only the exact order; delivery never equals the solution.
	 *
	 * @group GM-193
	 */
	public function test_GM_193_ordering(): void {
		$four = array(
			'question_type' => T::ORDERING,
			'question_text' => 'Q',
			'settings'      => array( 'items' => array( array( 'text' => '1' ), array( 'text' => '2' ), array( 'text' => '3' ), array( 'text' => '4' ) ) ),
		);
		self::assertTrue( T::evaluate( $four, array( 'I1', 'I2', 'I3', 'I4' ) )->correct() );
		self::assertFalse( T::evaluate( $four, array( 'I2', 'I1', 'I3', 'I4' ) )->correct() );
		self::assertFalse( T::evaluate( $four, array( 'I1', 'I2' ) )->valid );

		$two = array(
			'question_type' => T::ORDERING,
			'question_text' => 'Q',
			'settings'      => array( 'items' => array( array( 'text' => 'x' ), array( 'text' => 'y' ) ) ),
		);
		for ( $seed = 1; $seed <= 50; $seed++ ) {
			self::assertNotSame( array( 'I1', 'I2' ), T::deliver( $two, new Randomizer( (string) $seed ), 'q' )['order'] );
		}
	}

	/**
	 * Cloze helper.
	 *
	 * @param string $scoring Scoring.
	 * @param array  $gaps    Gaps.
	 * @return array<string, mixed>
	 */
	private static function cloze( string $scoring, array $gaps = array() ): array {
		return array(
			'question_type' => T::CLOZE,
			'question_text' => 'Ein {{g1}} für ein {{g2}}.',
			'settings'      => array(
				'scoring' => $scoring,
				'gaps'    => array() !== $gaps ? $gaps : array(
					array(
						'id'       => 'g1',
						'accepted' => array( 'Beispiel' ),
					),
					array(
						'id'       => 'g2',
						'accepted' => array( 'Konzept', 'Begriff' ),
					),
				),
			),
		);
	}

	/**
	 * GM-194 (E-69): normalization and partial points per gap.
	 *
	 * @group GM-194
	 */
	public function test_GM_194_cloze(): void {
		$aon = self::cloze( T::ALL_OR_NOTHING );
		self::assertTrue(
			T::evaluate(
				$aon,
				array(
					'g1' => ' beispiel ',
					'g2' => 'Begriff',
				)
			)->correct()
		);
		self::assertSame(
			0,
			T::evaluate(
				$aon,
				array(
					'g1' => 'Beispiel',
					'g2' => 'Falsch',
				)
			)->points_milli
		);
		$prop = self::cloze( T::PROPORTIONAL_PENALTY );
		self::assertSame(
			0,
			T::evaluate(
				$prop,
				array(
					'g1' => 'Beispiel',
					'g2' => 'Falsch',
				)
			)->points_milli
		);
		self::assertSame(
			500,
			T::evaluate(
				$prop,
				array(
					'g1' => 'Beispiel',
					'g2' => '',
				)
			)->points_milli
		);
		self::assertSame( array( 'type', 'text', 'gaps' ), array_keys( T::deliver( $aon, new Randomizer( '1' ), 'q' )['payload'] ), 'no accepted answers' );
	}

	/**
	 * GM-017 / GM-026: no solution in the payload; seeded order reproducible
	 * and the correct option uniformly distributed (χ², p > 0.01).
	 *
	 * @group GM-017
	 * @group GM-026
	 */
	public function test_GM_017_GM_026_delivery_and_distribution(): void {
		$single = self::choice( T::SINGLE, 4, array( 'A' ) );
		$first  = T::deliver( $single, new Randomizer( '20300115' ), 'Q-A1-001' );
		self::assertSame( $first, T::deliver( $single, new Randomizer( '20300115' ), 'Q-A1-001' ) );
		foreach ( $first['payload']['options'] as $option ) {
			self::assertSame( array( 'key', 'text' ), array_keys( $option ) );
		}

		$rand   = new Randomizer( '20300115' );
		$counts = array( 0, 0, 0, 0 );
		for ( $i = 0; $i < 400; $i++ ) {
			$order = T::deliver( $single, $rand, 'draw' . $i )['order'];
			++$counts[ (int) array_search( 'A', $order, true ) ];
		}
		$chi2 = 0.0;
		foreach ( $counts as $count ) {
			$chi2 += ( ( $count - 100 ) ** 2 ) / 100;
		}
		self::assertLessThan( 11.345, $chi2, 'χ²(3) critical value for p = 0.01: ' . implode( ',', $counts ) );
	}

	/**
	 * Pinned options keep their position; true/false is never shuffled.
	 */
	public function test_pinned_and_true_false(): void {
		$rev                         = self::choice( T::SINGLE, 4, array( 'A' ) );
		$rev['answers'][3]['pinned'] = true;
		for ( $s = 1; $s <= 20; $s++ ) {
			self::assertSame( 'D', T::deliver( $rev, new Randomizer( (string) $s ), 'q' )['order'][3] );
		}
		$tf = self::choice( T::TRUE_FALSE, 2, array( 'A' ) );
		self::assertSame( array( 'A', 'B' ), T::deliver( $tf, new Randomizer( '9' ), 'q' )['order'] );
	}
}
