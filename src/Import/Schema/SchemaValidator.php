<?php
/**
 * JSON Schema 2020-12 subset validator for package schemas (R0-10 §1.5, INV-C-091).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import\Schema;

defined( 'ABSPATH' ) || exit;

// JSON Schema keywords are camelCase properties of the decoded schema; exception messages are machine codes, never output.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase, WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Pure PHP, no WordPress. Schemas and data are decoded JSON with objects
 * as `\stdClass` (so `{}` and `[]` stay distinct).
 *
 * Supported keywords: `$schema`, `$id`, `$ref`, `$defs`, `$comment`,
 * annotations (`title`, `description`, `default`, `examples`,
 * `deprecated`, `readOnly`, `writeOnly`), `type`, `enum`, `const`,
 * `minimum`, `maximum`, `exclusiveMinimum`, `exclusiveMaximum`,
 * `multipleOf`, `minLength`, `maxLength`, `pattern`, `format` (`date`,
 * `date-time`, `uuid`, `uri`, `email`), `items`, `prefixItems`,
 * `minItems`, `maxItems`, `uniqueItems`, `contains`, `minContains`,
 * `maxContains`, `properties`, `patternProperties`,
 * `additionalProperties`, `required`, `propertyNames`,
 * `minProperties`, `maxProperties`, `dependentRequired`, `allOf`,
 * `anyOf`, `oneOf`, `not`, `if`/`then`/`else`.
 *
 * Unknown keywords or formats abort with {@see SchemaException} (a typo
 * in a schema must never silently accept data). `$ref` resolves within
 * the document (`#/$defs/x`), to another schema file next to the current
 * one (`common.schema.json#/$defs/x`) or to a registered `$id`.
 */
final class SchemaValidator {

	private const ANNOTATIONS = array( '$schema', '$id', '$defs', '$comment', 'title', 'description', 'default', 'examples', 'deprecated', 'readOnly', 'writeOnly' );
	private const KEYWORDS    = array( '$ref', 'type', 'enum', 'const', 'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'minLength', 'maxLength', 'pattern', 'format', 'items', 'prefixItems', 'minItems', 'maxItems', 'uniqueItems', 'contains', 'minContains', 'maxContains', 'properties', 'patternProperties', 'additionalProperties', 'required', 'propertyNames', 'minProperties', 'maxProperties', 'dependentRequired', 'allOf', 'anyOf', 'oneOf', 'not', 'if', 'then', 'else' );
	private const FORMATS     = array( 'date', 'date-time', 'uuid', 'uri', 'email' );
	private const MAX_DEPTH   = 64;

	/**
	 * Loaded documents by absolute file path or `$id`.
	 *
	 * @var array<string, mixed>
	 */
	private array $documents = array();

	/**
	 * Errors of the current run.
	 *
	 * @var list<array{path: string, keyword: string}>
	 */
	private array $errors = array();

	/**
	 * Register a schema document under its `$id` (and an optional file name).
	 *
	 * @param mixed       $schema Decoded schema.
	 * @param string|null $file   Absolute file path.
	 * @return void
	 */
	public function add( $schema, ?string $file = null ): void {
		if ( null !== $file ) {
			$this->documents[ $file ] = $schema;
		}
		if ( $schema instanceof \stdClass && isset( $schema->{'$id'} ) && is_string( $schema->{'$id'} ) ) {
			$this->documents[ $schema->{'$id'} ] = $schema;
		}
	}

	/**
	 * Validate data against a schema file.
	 *
	 * @param mixed  $data Decoded data (objects as stdClass).
	 * @param string $file Absolute schema path.
	 * @return list<array{path: string, keyword: string}> Empty when valid.
	 * @throws SchemaException For unknown keywords, formats or unresolvable references.
	 */
	public function validate_file( $data, string $file ): array {
		return $this->validate( $data, $this->load( $file ), $file );
	}

	/**
	 * Validate data against a decoded schema.
	 *
	 * @param mixed  $data   Decoded data.
	 * @param mixed  $schema Decoded schema.
	 * @param string $base   Document key of the schema (file path or `$id`).
	 * @return list<array{path: string, keyword: string}>
	 * @throws SchemaException For unknown keywords, formats or unresolvable references.
	 */
	public function validate( $data, $schema, string $base = '' ): array {
		if ( '' === $base ) {
			$base = $schema instanceof \stdClass && isset( $schema->{'$id'} ) ? (string) $schema->{'$id'} : 'inline';
		}
		$this->add( $schema, $base );
		$this->errors = array();
		$this->check( $data, $schema, '', $base, 0 );
		return $this->errors;
	}

	/**
	 * Decode JSON with objects as stdClass.
	 *
	 * @param string $json JSON.
	 * @return mixed
	 * @throws SchemaException For invalid JSON.
	 */
	public static function decode( string $json ) {
		try {
			return json_decode( $json, false, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			throw new SchemaException( 'invalid_json' );
		}
	}

	/**
	 * Check one value (collects errors, returns validity).
	 *
	 * @param mixed  $data   Value.
	 * @param mixed  $schema Schema.
	 * @param string $path   JSON pointer of the value.
	 * @param string $base   Current document.
	 * @param int    $depth  Recursion depth.
	 * @return bool
	 * @throws SchemaException For schema errors.
	 */
	private function check( $data, $schema, string $path, string $base, int $depth ): bool {
		if ( $depth > self::MAX_DEPTH ) {
			throw new SchemaException( 'depth' );
		}
		if ( true === $schema ) {
			return true;
		}
		if ( false === $schema ) {
			return $this->fail( $path, 'false' );
		}
		if ( ! $schema instanceof \stdClass ) {
			throw new SchemaException( 'schema_not_object' );
		}
		foreach ( array_keys( get_object_vars( $schema ) ) as $keyword ) {
			if ( ! in_array( $keyword, self::ANNOTATIONS, true ) && ! in_array( $keyword, self::KEYWORDS, true ) ) {
				throw new SchemaException( 'unknown_keyword:' . $keyword );
			}
		}
		$ok = true;
		if ( isset( $schema->{'$ref'} ) ) {
			list( $target, $doc ) = $this->resolve( (string) $schema->{'$ref'}, $base );
			$ok                   = $this->check( $data, $target, $path, $doc, $depth + 1 );
		}
		$ok = $this->generic( $data, $schema, $path ) && $ok;
		if ( is_string( $data ) ) {
			$ok = $this->string( $data, $schema, $path ) && $ok;
		} elseif ( is_int( $data ) || is_float( $data ) ) {
			$ok = $this->number( $data, $schema, $path ) && $ok;
		} elseif ( is_array( $data ) ) {
			$ok = $this->list( $data, $schema, $path, $base, $depth ) && $ok;
		} elseif ( $data instanceof \stdClass ) {
			$ok = $this->object( $data, $schema, $path, $base, $depth ) && $ok;
		}
		return $this->combinators( $data, $schema, $path, $base, $depth ) && $ok;
	}

	/**
	 * `type`, `enum`, `const`.
	 *
	 * @param mixed     $data   Value.
	 * @param \stdClass $schema Schema.
	 * @param string    $path   Path.
	 * @return bool
	 */
	private function generic( $data, \stdClass $schema, string $path ): bool {
		$ok = true;
		if ( isset( $schema->type ) ) {
			$types = is_array( $schema->type ) ? $schema->type : array( $schema->type );
			$match = false;
			foreach ( $types as $type ) {
				$match = $match || self::is_type( $data, (string) $type );
			}
			$ok = $match || $this->fail( $path, 'type' );
		}
		if ( isset( $schema->enum ) ) {
			$found = false;
			foreach ( (array) $schema->enum as $candidate ) {
				$found = $found || self::equal( $data, $candidate );
			}
			$ok = ( $found || $this->fail( $path, 'enum' ) ) && $ok;
		}
		if ( property_exists( $schema, 'const' ) && ! self::equal( $data, $schema->const ) ) {
			$ok = $this->fail( $path, 'const' );
		}
		return $ok;
	}

	/**
	 * String keywords.
	 *
	 * @param string    $data   Value.
	 * @param \stdClass $schema Schema.
	 * @param string    $path   Path.
	 * @return bool
	 * @throws SchemaException For unknown formats or broken patterns.
	 */
	private function string( string $data, \stdClass $schema, string $path ): bool {
		$ok     = true;
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $data, 'UTF-8' ) : strlen( $data );
		if ( isset( $schema->minLength ) && $length < (int) $schema->minLength ) {
			$ok = $this->fail( $path, 'minLength' );
		}
		if ( isset( $schema->maxLength ) && $length > (int) $schema->maxLength ) {
			$ok = $this->fail( $path, 'maxLength' );
		}
		if ( isset( $schema->pattern ) && ! self::matches( (string) $schema->pattern, $data ) ) {
			$ok = $this->fail( $path, 'pattern' );
		}
		if ( isset( $schema->format ) ) {
			$format = (string) $schema->format;
			if ( ! in_array( $format, self::FORMATS, true ) ) {
				throw new SchemaException( 'unknown_format:' . $format );
			}
			if ( ! self::format( $format, $data ) ) {
				$ok = $this->fail( $path, 'format' );
			}
		}
		return $ok;
	}

	/**
	 * Number keywords.
	 *
	 * @param int|float $data   Value.
	 * @param \stdClass $schema Schema.
	 * @param string    $path   Path.
	 * @return bool
	 */
	private function number( $data, \stdClass $schema, string $path ): bool {
		$ok = true;
		if ( isset( $schema->minimum ) && $data < $schema->minimum ) {
			$ok = $this->fail( $path, 'minimum' );
		}
		if ( isset( $schema->maximum ) && $data > $schema->maximum ) {
			$ok = $this->fail( $path, 'maximum' );
		}
		if ( isset( $schema->exclusiveMinimum ) && $data <= $schema->exclusiveMinimum ) {
			$ok = $this->fail( $path, 'exclusiveMinimum' );
		}
		if ( isset( $schema->exclusiveMaximum ) && $data >= $schema->exclusiveMaximum ) {
			$ok = $this->fail( $path, 'exclusiveMaximum' );
		}
		if ( isset( $schema->multipleOf ) && (float) $schema->multipleOf > 0 ) {
			$ratio = $data / $schema->multipleOf;
			if ( abs( $ratio - round( $ratio ) ) > 1e-9 ) {
				$ok = $this->fail( $path, 'multipleOf' );
			}
		}
		return $ok;
	}

	/**
	 * Array keywords incl. `contains`.
	 *
	 * @param array<int, mixed> $data   Value.
	 * @param \stdClass         $schema Schema.
	 * @param string            $path   Path.
	 * @param string            $base   Document.
	 * @param int               $depth  Depth.
	 * @return bool
	 */
	private function list( array $data, \stdClass $schema, string $path, string $base, int $depth ): bool {
		$ok    = true;
		$count = count( $data );
		if ( isset( $schema->minItems ) && $count < (int) $schema->minItems ) {
			$ok = $this->fail( $path, 'minItems' );
		}
		if ( isset( $schema->maxItems ) && $count > (int) $schema->maxItems ) {
			$ok = $this->fail( $path, 'maxItems' );
		}
		if ( ! empty( $schema->uniqueItems ) ) {
			foreach ( $data as $i => $a ) {
				foreach ( array_slice( $data, $i + 1 ) as $b ) {
					if ( self::equal( $a, $b ) ) {
						$ok = $this->fail( $path, 'uniqueItems' );
						break 2;
					}
				}
			}
		}
		$prefix = isset( $schema->prefixItems ) ? (array) $schema->prefixItems : array();
		foreach ( $data as $i => $item ) {
			if ( isset( $prefix[ $i ] ) ) {
				$ok = $this->check( $item, $prefix[ $i ], $path . '/' . $i, $base, $depth + 1 ) && $ok;
			} elseif ( property_exists( $schema, 'items' ) ) {
				$ok = $this->check( $item, $schema->items, $path . '/' . $i, $base, $depth + 1 ) && $ok;
			}
		}
		if ( property_exists( $schema, 'contains' ) ) {
			$hits = 0;
			foreach ( $data as $i => $item ) {
				if ( $this->probe( $item, $schema->contains, $path . '/' . $i, $base, $depth + 1 ) ) {
					++$hits;
				}
			}
			$min = isset( $schema->minContains ) ? (int) $schema->minContains : 1;
			if ( $hits < $min ) {
				$ok = $this->fail( $path, 'contains' );
			}
			if ( isset( $schema->maxContains ) && $hits > (int) $schema->maxContains ) {
				$ok = $this->fail( $path, 'maxContains' );
			}
		}
		return $ok;
	}

	/**
	 * Object keywords.
	 *
	 * @param \stdClass $data   Value.
	 * @param \stdClass $schema Schema.
	 * @param string    $path   Path.
	 * @param string    $base   Document.
	 * @param int       $depth  Depth.
	 * @return bool
	 */
	private function object( \stdClass $data, \stdClass $schema, string $path, string $base, int $depth ): bool {
		$ok    = true;
		$props = get_object_vars( $data );
		foreach ( isset( $schema->required ) ? (array) $schema->required : array() as $name ) {
			if ( ! array_key_exists( (string) $name, $props ) ) {
				$ok = $this->fail( $path . '/' . self::escape( (string) $name ), 'required' );
			}
		}
		if ( isset( $schema->minProperties ) && count( $props ) < (int) $schema->minProperties ) {
			$ok = $this->fail( $path, 'minProperties' );
		}
		if ( isset( $schema->maxProperties ) && count( $props ) > (int) $schema->maxProperties ) {
			$ok = $this->fail( $path, 'maxProperties' );
		}
		foreach ( isset( $schema->dependentRequired ) ? get_object_vars( $schema->dependentRequired ) : array() as $name => $needs ) {
			if ( array_key_exists( (string) $name, $props ) ) {
				foreach ( (array) $needs as $need ) {
					if ( ! array_key_exists( (string) $need, $props ) ) {
						$ok = $this->fail( $path . '/' . self::escape( (string) $need ), 'dependentRequired' );
					}
				}
			}
		}
		$defined  = isset( $schema->properties ) ? get_object_vars( $schema->properties ) : array();
		$patterns = isset( $schema->patternProperties ) ? get_object_vars( $schema->patternProperties ) : array();
		foreach ( $props as $name => $value ) {
			$name  = (string) $name;
			$where = $path . '/' . self::escape( $name );
			if ( isset( $schema->propertyNames ) ) {
				$ok = $this->check( $name, $schema->propertyNames, $where, $base, $depth + 1 ) && $ok;
			}
			$known = false;
			if ( array_key_exists( $name, $defined ) ) {
				$known = true;
				$ok    = $this->check( $value, $defined[ $name ], $where, $base, $depth + 1 ) && $ok;
			}
			foreach ( $patterns as $pattern => $sub ) {
				if ( self::matches( (string) $pattern, $name ) ) {
					$known = true;
					$ok    = $this->check( $value, $sub, $where, $base, $depth + 1 ) && $ok;
				}
			}
			if ( ! $known && property_exists( $schema, 'additionalProperties' ) ) {
				if ( false === $schema->additionalProperties ) {
					$ok = $this->fail( $where, 'additionalProperties' );
				} else {
					$ok = $this->check( $value, $schema->additionalProperties, $where, $base, $depth + 1 ) && $ok;
				}
			}
		}
		return $ok;
	}

	/**
	 * `allOf`, `anyOf`, `oneOf`, `not`, `if`/`then`/`else`.
	 *
	 * @param mixed     $data   Value.
	 * @param \stdClass $schema Schema.
	 * @param string    $path   Path.
	 * @param string    $base   Document.
	 * @param int       $depth  Depth.
	 * @return bool
	 */
	private function combinators( $data, \stdClass $schema, string $path, string $base, int $depth ): bool {
		$ok = true;
		foreach ( isset( $schema->allOf ) ? (array) $schema->allOf : array() as $sub ) {
			$ok = $this->check( $data, $sub, $path, $base, $depth + 1 ) && $ok;
		}
		if ( isset( $schema->anyOf ) ) {
			$any = false;
			foreach ( (array) $schema->anyOf as $sub ) {
				$any = $this->probe( $data, $sub, $path, $base, $depth + 1 ) || $any;
			}
			$ok = ( $any || $this->fail( $path, 'anyOf' ) ) && $ok;
		}
		if ( isset( $schema->oneOf ) ) {
			$hits = 0;
			foreach ( (array) $schema->oneOf as $sub ) {
				$hits += $this->probe( $data, $sub, $path, $base, $depth + 1 ) ? 1 : 0;
			}
			$ok = ( 1 === $hits || $this->fail( $path, 'oneOf' ) ) && $ok;
		}
		if ( property_exists( $schema, 'not' ) && $this->probe( $data, $schema->not, $path, $base, $depth + 1 ) ) {
			$ok = $this->fail( $path, 'not' );
		}
		if ( property_exists( $schema, 'if' ) ) {
			if ( $this->probe( $data, $schema->if, $path, $base, $depth + 1 ) ) {
				if ( property_exists( $schema, 'then' ) ) {
					$ok = $this->check( $data, $schema->then, $path, $base, $depth + 1 ) && $ok;
				}
			} elseif ( property_exists( $schema, 'else' ) ) {
				$ok = $this->check( $data, $schema->else, $path, $base, $depth + 1 ) && $ok;
			}
		}
		return $ok;
	}

	/**
	 * Check without keeping the errors (sub-schemas of anyOf, contains …).
	 *
	 * @param mixed  $data   Value.
	 * @param mixed  $schema Schema.
	 * @param string $path   Path.
	 * @param string $base   Document.
	 * @param int    $depth  Depth.
	 * @return bool
	 */
	private function probe( $data, $schema, string $path, string $base, int $depth ): bool {
		$kept         = $this->errors;
		$result       = $this->check( $data, $schema, $path, $base, $depth );
		$this->errors = $kept;
		return $result;
	}

	/**
	 * Resolve a reference against the current document.
	 *
	 * @param string $ref  Reference.
	 * @param string $base Current document key.
	 * @return array{0: mixed, 1: string} Target schema and its document key.
	 * @throws SchemaException When the target cannot be found.
	 */
	private function resolve( string $ref, string $base ): array {
		$hash     = strpos( $ref, '#' );
		$document = false === $hash ? $ref : substr( $ref, 0, $hash );
		$pointer  = false === $hash ? '' : substr( $ref, $hash + 1 );
		if ( '' === $document ) {
			$key = $base;
		} elseif ( isset( $this->documents[ $document ] ) ) {
			$key = $document;
		} else {
			if ( str_contains( $document, '..' ) || str_contains( $document, '://' ) || str_starts_with( $document, '/' ) || ! is_file( $base ) ) {
				throw new SchemaException( 'unresolved_ref:' . $ref );
			}
			$key = dirname( $base ) . '/' . $document;
			$this->load( $key );
		}
		$node = $this->documents[ $key ] ?? null;
		if ( null === $node ) {
			throw new SchemaException( 'unresolved_ref:' . $ref );
		}
		if ( '' !== $pointer ) {
			foreach ( explode( '/', ltrim( $pointer, '/' ) ) as $segment ) {
				$segment = str_replace( array( '~1', '~0' ), array( '/', '~' ), rawurldecode( $segment ) );
				if ( $node instanceof \stdClass && property_exists( $node, $segment ) ) {
					$node = $node->{$segment};
				} elseif ( is_array( $node ) && array_key_exists( (int) $segment, $node ) ) {
					$node = $node[ (int) $segment ];
				} else {
					throw new SchemaException( 'unresolved_ref:' . $ref );
				}
			}
		}
		return array( $node, $key );
	}

	/**
	 * Load a schema file once.
	 *
	 * @param string $file Absolute path.
	 * @return mixed
	 * @throws SchemaException When missing or invalid.
	 */
	private function load( string $file ) {
		if ( ! isset( $this->documents[ $file ] ) ) {
			if ( ! is_file( $file ) ) {
				throw new SchemaException( 'missing_schema:' . basename( $file ) );
			}
			$this->add( self::decode( (string) file_get_contents( $file ) ), $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local schema file.
		}
		return $this->documents[ $file ];
	}

	/**
	 * JSON type check (integers are numbers; 1.0 counts as integer).
	 *
	 * @param mixed  $data Value.
	 * @param string $type Type.
	 * @return bool
	 * @throws SchemaException For unknown types.
	 */
	private static function is_type( $data, string $type ): bool {
		switch ( $type ) {
			case 'null':
				return null === $data;
			case 'boolean':
				return is_bool( $data );
			case 'string':
				return is_string( $data );
			case 'integer':
				return is_int( $data ) || ( is_float( $data ) && floor( $data ) === $data );
			case 'number':
				return is_int( $data ) || is_float( $data );
			case 'array':
				return is_array( $data );
			case 'object':
				return $data instanceof \stdClass;
		}
		throw new SchemaException( 'unknown_type:' . $type );
	}

	/**
	 * Deep JSON equality.
	 *
	 * @param mixed $a A.
	 * @param mixed $b B.
	 * @return bool
	 */
	private static function equal( $a, $b ): bool {
		if ( ( is_int( $a ) || is_float( $a ) ) && ( is_int( $b ) || is_float( $b ) ) ) {
			return (float) $a === (float) $b;
		}
		if ( $a instanceof \stdClass && $b instanceof \stdClass ) {
			$va = get_object_vars( $a );
			$vb = get_object_vars( $b );
			if ( count( $va ) !== count( $vb ) ) {
				return false;
			}
			foreach ( $va as $k => $v ) {
				if ( ! array_key_exists( $k, $vb ) || ! self::equal( $v, $vb[ $k ] ) ) {
					return false;
				}
			}
			return true;
		}
		if ( is_array( $a ) && is_array( $b ) ) {
			if ( count( $a ) !== count( $b ) ) {
				return false;
			}
			foreach ( $a as $i => $v ) {
				if ( ! array_key_exists( $i, $b ) || ! self::equal( $v, $b[ $i ] ) ) {
					return false;
				}
			}
			return true;
		}
		return $a === $b;
	}

	/**
	 * ECMA-262-like pattern match (unanchored, Unicode).
	 *
	 * @param string $pattern Pattern.
	 * @param string $value   Value.
	 * @return bool
	 * @throws SchemaException For invalid patterns.
	 */
	private static function matches( string $pattern, string $value ): bool {
		$result = @preg_match( '/' . str_replace( '/', '\/', $pattern ) . '/u', $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid patterns are reported below.
		if ( false === $result ) {
			throw new SchemaException( 'invalid_pattern' );
		}
		return 1 === $result;
	}

	/**
	 * Format check.
	 *
	 * @param string $format Format.
	 * @param string $value  Value.
	 * @return bool
	 */
	private static function format( string $format, string $value ): bool {
		switch ( $format ) {
			case 'date':
				return 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
			case 'date-time':
				return 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})[Tt](\d{2}):(\d{2}):(\d{2})(\.\d+)?([Zz]|[+-]\d{2}:\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) && (int) $m[4] < 24 && (int) $m[5] < 60 && (int) $m[6] < 61;
			case 'uuid':
				return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value );
			case 'uri':
				return 1 === preg_match( '/^[a-z][a-z0-9+.-]*:[^\s]*$/i', $value );
		}
		return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * JSON pointer segment.
	 *
	 * @param string $name Property.
	 * @return string
	 */
	private static function escape( string $name ): string {
		return str_replace( array( '~', '/' ), array( '~0', '~1' ), $name );
	}

	/**
	 * Record an error.
	 *
	 * @param string $path    Path.
	 * @param string $keyword Keyword.
	 * @return false
	 */
	private function fail( string $path, string $keyword ): bool {
		$this->errors[] = array(
			'path'    => '' === $path ? '/' : $path,
			'keyword' => $keyword,
		);
		return false;
	}
}
