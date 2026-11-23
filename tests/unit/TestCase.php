<?php
/**
 * Base class of the unit tests.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Sets up Brain Monkey and stubs the translation and escaping functions.
 */
abstract class TestCase extends PhpUnitTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Set up Brain Monkey.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Monkey\Functions\stubTranslationFunctions();
		Monkey\Functions\stubEscapeFunctions();
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Absolute path of the repository root.
	 *
	 * @return string
	 */
	protected static function root(): string {
		return dirname( __DIR__, 2 );
	}
}
