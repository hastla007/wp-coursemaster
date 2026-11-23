<?php
/**
 * R1-18 / R1-15: JSON-Schema subset validator (R0-10 §1.5, R0-05 GM-135, cases SV-01…SV-10).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Import\Schema\SchemaException;
use CourseCaptain\Import\Schema\SchemaValidator;

/**
 * Schemas under `tests/unit/fixtures/schema/` (synthetic, E-02).
 *
 * @group R1-18
 * @group GM-135
 */
final class SchemaValidatorTest extends TestCase {

	/**
	 * Valid manifest of the fixtures.
	 *
	 * @return array<string, mixed>
	 */
	private static function manifest(): array {
		return array(
			'format'         => 'cc-package',
			'schema_version' => '1.0.0',
			'package_id'     => 'cc-fx-neutral-v1-0123456789ab',
			'created_at'     => '2030-01-15T10:00:00Z',
			'default_rights' => array(
				'status'      => 'licensed',
				'valid_until' => '2031-01-31',
			),
			'counts'         => array( 'questions' => 71 ),
			'files'          => array(
				array(
					'path'      => 'data/questions/000001.json',
					'size'      => 10,
					'mime_type' => 'application/json',
					'sha256'    => str_repeat( 'ab', 32 ),
				),
			),
		);
	}

	/**
	 * Validate PHP data (encoded and decoded with objects) against a fixture schema.
	 *
	 * @param mixed  $data   Data.
	 * @param string $schema Schema file name.
	 * @return list<array{path: string, keyword: string}>
	 */
	private static function errors( $data, string $schema ): array {
		$decoded = SchemaValidator::decode( (string) json_encode( $data ) );
		return ( new SchemaValidator() )->validate_file( $decoded, __DIR__ . '/fixtures/schema/' . $schema );
	}

	/**
	 * SV-01: a valid manifest passes (incl. `$ref` into the common schema).
	 */
	public function test_SV_01_valid_manifest(): void {
		self::assertSame( array(), self::errors( self::manifest(), 'manifest.schema.json' ) );
	}

	/**
	 * SV-02: unknown field, wrong constant and missing required field.
	 */
	public function test_SV_02_additional_const_required(): void {
		$m           = self::manifest();
		$m['extra']  = 1;
		$m['format'] = 'boatingschool';
		unset( $m['created_at'] );
		$errors = self::errors( $m, 'manifest.schema.json' );
		self::assertContains(
			array(
				'path'    => '/created_at',
				'keyword' => 'required',
			),
			$errors
		);
		self::assertContains(
			array(
				'path'    => '/extra',
				'keyword' => 'additionalProperties',
			),
			$errors
		);
		self::assertContains(
			array(
				'path'    => '/format',
				'keyword' => 'const',
			),
			$errors
		);
	}

	/**
	 * SV-03: `$ref` over files – the checksum rule lives in `common.schema.json`.
	 */
	public function test_SV_03_ref_over_files(): void {
		$m                       = self::manifest();
		$m['files'][0]['sha256'] = 'XYZ';
		self::assertSame(
			array(
				array(
					'path'    => '/files/0/sha256',
					'keyword' => 'pattern',
				),
			),
			self::errors( $m, 'manifest.schema.json' )
		);
	}

	/**
	 * SV-04: a reference inside the referenced file resolves against that
	 * file (`rights.status` → `#/$defs/rightsStatus` of common), formats.
	 */
	public function test_SV_04_nested_ref_and_format(): void {
		$m                   = self::manifest();
		$m['default_rights'] = array(
			'status'      => 'stolen',
			'valid_until' => '2030-02-30',
		);
		$errors              = self::errors( $m, 'manifest.schema.json' );
		self::assertContains(
			array(
				'path'    => '/default_rights/status',
				'keyword' => 'enum',
			),
			$errors
		);
		self::assertContains(
			array(
				'path'    => '/default_rights/valid_until',
				'keyword' => 'format',
			),
			$errors
		);
		$m['created_at'] = '2030-01-15 10:00';
		self::assertContains(
			array(
				'path'    => '/created_at',
				'keyword' => 'format',
			),
			self::errors( $m, 'manifest.schema.json' )
		);
	}

	/**
	 * SV-05: `if`/`then`: an image needs an alternative text unless decorative.
	 */
	public function test_SV_05_if_then(): void {
		$image = array(
			'source_id'  => 'cc-fx-med-001',
			'media_type' => 'image',
			'alt_text'   => '',
			'rights'     => array( 'status' => 'own' ),
		);
		self::assertSame(
			array(
				array(
					'path'    => '/alt_text',
					'keyword' => 'minLength',
				),
			),
			self::errors( $image, 'media.schema.json' )
		);
		$image['decorative'] = true;
		self::assertSame( array(), self::errors( $image, 'media.schema.json' ) );
		$image['alt_text'] = 'Farbfläche mit Text A1-003';
		unset( $image['decorative'] );
		self::assertSame( array(), self::errors( $image, 'media.schema.json' ) );
	}

	/**
	 * SV-06: `else` branch: only images may be marked decorative.
	 */
	public function test_SV_06_else(): void {
		$pdf = array(
			'source_id'  => 'cc-fx-med-004',
			'media_type' => 'document',
			'alt_text'   => '',
			'rights'     => array( 'status' => 'own' ),
		);
		self::assertSame( array(), self::errors( $pdf, 'media.schema.json' ) );
		$pdf['decorative'] = true;
		self::assertSame(
			array(
				array(
					'path'    => '/',
					'keyword' => 'not',
				),
			),
			self::errors( $pdf, 'media.schema.json' )
		);
	}

	/**
	 * SV-07: `contains` with `maxContains` and `uniqueItems`.
	 */
	public function test_SV_07_contains(): void {
		$course = array(
			'source_id' => 'cc-fx-course-a',
			'modules'   => array(
				array(
					'module'   => 'cc-fx-m-a1',
					'sort'     => 10,
					'required' => false,
				),
				array(
					'module' => 'cc-fx-m-a2',
					'sort'   => 20,
				),
			),
		);
		self::assertSame(
			array(
				array(
					'path'    => '/modules',
					'keyword' => 'contains',
				),
			),
			self::errors( $course, 'course.schema.json' ),
			'no required module'
		);
		$course['modules'][0]['required'] = true;
		self::assertSame( array(), self::errors( $course, 'course.schema.json' ) );
		$course['modules'][1]['required'] = true;
		$course['modules'][]              = array(
			'module'   => 'cc-fx-m-a3',
			'sort'     => 30,
			'required' => true,
		);
		self::assertSame(
			array(
				array(
					'path'    => '/modules',
					'keyword' => 'maxContains',
				),
			),
			self::errors( $course, 'course.schema.json' )
		);
		$course['modules'] = array( $course['modules'][0], $course['modules'][0] );
		self::assertSame(
			array(
				array(
					'path'    => '/modules',
					'keyword' => 'uniqueItems',
				),
			),
			self::errors( $course, 'course.schema.json' )
		);
	}

	/**
	 * SV-08: an unknown keyword in a schema aborts (never silently accepts).
	 */
	public function test_SV_08_unknown_keyword_aborts(): void {
		$this->expectException( SchemaException::class );
		$this->expectExceptionMessage( 'unknown_keyword:minLenght' );
		self::errors( array( 'title' => 'x' ), 'broken-keyword.schema.json' );
	}

	/**
	 * SV-09: unresolvable references abort (missing pointer, missing file, no remote fetch).
	 */
	public function test_SV_09_unresolvable_ref(): void {
		foreach ( array( array( 'a' => 'x' ), array( 'b' => 'x' ) ) as $data ) {
			try {
				self::errors( $data, 'broken-ref.schema.json' );
				self::fail( 'expected SchemaException' );
			} catch ( SchemaException $e ) {
				self::assertStringStartsWith( 'unresolved_ref:', str_replace( 'missing_schema:', 'unresolved_ref:', $e->getMessage() ) );
			}
		}
		$validator = new SchemaValidator();
		$this->expectException( SchemaException::class );
		$validator->validate( 'x', SchemaValidator::decode( '{"$ref": "https://example.test/remote.schema.json"}' ) );
	}

	/**
	 * SV-10: JSON types – object vs. empty array, integer vs. number, `oneOf`/`anyOf`, unknown format aborts.
	 */
	public function test_SV_10_types_and_combinators(): void {
		$m           = self::manifest();
		$m['counts'] = array();
		self::assertSame(
			array(
				array(
					'path'    => '/counts',
					'keyword' => 'type',
				),
			),
			self::errors( $m, 'manifest.schema.json' ),
			'[] is not an object'
		);
		$m['counts'] = new \stdClass();
		self::assertSame( array(), self::errors( $m, 'manifest.schema.json' ) );
		$m['counts']           = array( 'questions' => 1.5 );
		$m['files'][0]['size'] = -1;
		$errors                = self::errors( $m, 'manifest.schema.json' );
		self::assertContains(
			array(
				'path'    => '/counts/questions',
				'keyword' => 'type',
			),
			$errors
		);
		self::assertContains(
			array(
				'path'    => '/files/0/size',
				'keyword' => 'minimum',
			),
			$errors
		);

		$validator = new SchemaValidator();
		$schema    = SchemaValidator::decode( '{"oneOf": [{"type": "integer"}, {"type": "number", "minimum": 2}], "anyOf": [{"type": "number"}]}' );
		self::assertSame( array(), $validator->validate( 1, $schema ) );
		self::assertSame(
			array(
				array(
					'path'    => '/',
					'keyword' => 'oneOf',
				),
			),
			$validator->validate( 3, $schema ),
			'matches both'
		);
		self::assertSame(
			array(
				array(
					'path'    => '/',
					'keyword' => 'anyOf',
				),
			),
			$validator->validate( 'x', SchemaValidator::decode( '{"anyOf": [{"type": "number"}]}' ) )
		);
		$this->expectException( SchemaException::class );
		$validator->validate( 'x', SchemaValidator::decode( '{"format": "hostname"}' ) );
	}
}
