<?php
/**
 * R1-07: provider registry, settings schema, completion rules.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Activity\ActivityRegistry;
use CourseCaptain\Activity\Providers\LessonProvider;
use CourseCaptain\Activity\SettingsSchema;
use InvalidArgumentException;

/**
 * Pure tests (Brain Monkey stubs only for wp_parse_url).
 *
 * @group R1-07
 */
final class ActivityUnitTest extends TestCase {

	/**
	 * Stub wp_parse_url.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
	}

	/**
	 * Seven core providers; duplicates are rejected (R0-08 §2.2).
	 */
	public function test_registry(): void {
		$registry = ActivityRegistry::with_core_providers();
		self::assertSame( array( 'lesson', 'video', 'file', 'quiz', 'exam', 'flashcard', 'assignment' ), array_keys( $registry->all() ) );
		$this->expectException( InvalidArgumentException::class );
		$registry->register( new LessonProvider() );
	}

	/**
	 * Schema subset.
	 */
	public function test_settings_schema(): void {
		$registry = ActivityRegistry::with_core_providers();
		self::assertSame(
			array(),
			$registry->validate_settings(
				'video',
				array(
					'provider'  => 'url',
					'url'       => 'https://example.test/a.mp4',
					'subtitles' => 'vtt',
				)
			)
		);
		self::assertNotSame( array(), $registry->validate_settings( 'video', array( 'url' => 'http://example.test/a.mp4' ) ), 'https only' );
		self::assertNotSame( array(), $registry->validate_settings( 'video', array( 'subtitles' => 'srt' ) ), 'GM-014: only off/vtt' );
		self::assertNotSame( array(), $registry->validate_settings( 'lesson', array( 'unknown' => 1 ) ) );
		self::assertSame( array( '/media_uid: required' ), $registry->validate_settings( 'file', array() ) );
		self::assertSame( array( 'unknown_type' ), $registry->validate_settings( 'chart', array() ) );
		self::assertSame( array( '/: type object' ), SettingsSchema::validate( 'x', array( 'type' => 'object' ) ) );
	}

	/**
	 * Completion rules per provider (R0-08 §2.4).
	 */
	public function test_completion_rules(): void {
		$r = ActivityRegistry::with_core_providers();
		self::assertSame( 'not_started', $r->get( 'lesson' )->evaluate_completion( array( 'type' => 'manual_confirm' ), array() ) );
		self::assertSame( 'in_progress', $r->get( 'lesson' )->evaluate_completion( array( 'type' => 'manual_confirm' ), array( 'started' => true ) ) );
		self::assertSame( 'completed', $r->get( 'lesson' )->evaluate_completion( array( 'type' => 'manual_confirm' ), array( 'confirmed' => true ) ) );
		self::assertSame(
			'completed',
			$r->get( 'lesson' )->evaluate_completion(
				array(
					'type'   => 'view',
					'params' => array( 'min_seconds' => 30 ),
				),
				array(
					'started'      => true,
					'view_seconds' => 30,
				)
			)
		);
		self::assertSame(
			'completed',
			$r->get( 'video' )->evaluate_completion(
				array(
					'type'   => 'position_ratio',
					'params' => array( 'ratio' => 0.9 ),
				),
				array(
					'started'          => true,
					'position_seconds' => 90,
					'duration_seconds' => 100,
				)
			)
		);
		self::assertSame(
			'in_progress',
			$r->get( 'video' )->evaluate_completion(
				array(
					'type'   => 'position_ratio',
					'params' => array( 'ratio' => 0.9 ),
				),
				array(
					'started'          => true,
					'position_seconds' => 89,
					'duration_seconds' => 100,
				)
			)
		);
		self::assertSame(
			'completed',
			$r->get( 'quiz' )->evaluate_completion(
				array( 'type' => 'attempted' ),
				array(
					'answered' => 3,
					'total'    => 3,
				)
			),
			'GM-010 rule'
		);
		self::assertSame( 'completed', $r->get( 'file' )->evaluate_completion( array( 'type' => 'downloaded' ), array( 'downloaded' => true ) ) );
		self::assertSame(
			'completed',
			$r->get( 'flashcard' )->evaluate_completion(
				array( 'type' => 'all_known' ),
				array(
					'known' => 5,
					'total' => 5,
				)
			)
		);
		self::assertSame( 'completed', $r->get( 'exam' )->evaluate_completion( array( 'type' => 'passed' ), array( 'passed' => true ) ) );
		self::assertSame( 'completed', $r->get( 'assignment' )->evaluate_completion( array( 'type' => 'graded' ), array( 'graded' => true ) ) );

		$rule = ActivityRegistry::normalize_rule( $r->get( 'video' ), array( 'type' => 'graded' ) );
		self::assertSame( 'manual_confirm', $rule['type'], 'unsupported rule → provider default' );
	}
}
