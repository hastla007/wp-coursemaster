<?php
/**
 * Accessible form fields for settings tabs and setup steps
 * (R0-30 T-3, T-5, T-12, W-5; R0-15 K-04, K-14, A11Y-080).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Settings\SettingsRules;
use CourseCaptain\Settings\SettingsSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Renders one field with label (or fieldset/legend), visible help text,
 * meta line and error text, all referenced by `aria-describedby`; marks
 * invalid fields with `aria-invalid`. Dependent fields stay focusable and
 * get `aria-disabled` with the reason in the help text (T-12).
 *
 * Decoding turns the POST of rendered keys into register values: checkboxes
 * send a hidden `0`, nullable numbers a mode (`none`/`value`), sets a
 * presence marker, line lists one entry per line and percentages a
 * reduced `{num, den}` fraction.
 */
final class SettingsForm {

	/**
	 * Field ID.
	 *
	 * @param string $tab Tab.
	 * @param string $key Key.
	 * @return string
	 */
	public static function id( string $tab, string $key ): string {
		return 'ccap-field-' . $tab . '-' . str_replace( array( '.', '_' ), '-', $key );
	}

	/**
	 * Value of a key in a value map.
	 *
	 * @param array<string, mixed> $values Values.
	 * @param string               $key    Key.
	 * @return mixed
	 */
	public static function value_of( array $values, string $key ) {
		list( $f, $sub ) = array_pad( explode( '.', $key, 2 ), 2, '' );
		if ( '' === $sub ) {
			return $values[ $f ] ?? null;
		}
		return is_array( $values[ $f ] ?? null ) ? ( $values[ $f ][ $sub ] ?? null ) : null;
	}

	/**
	 * Decode the POST of rendered keys.
	 *
	 * @param string               $tab     Tab.
	 * @param array                $keys    Rendered keys.
	 * @param array<string, mixed> $post    Unslashed POST.
	 * @param array<string, mixed> $current Current values of the tab.
	 * @phpstan-param list<string> $keys
	 * @return array{input: array<string, mixed>, fields: list<string>, errors: array<string, array{rule: string, params: array<string, mixed>}>}
	 */
	public static function decode( string $tab, array $keys, array $post, array $current ): array {
		$raw    = is_array( $post['ccap'] ?? null ) ? $post['ccap'] : array();
		$modes  = is_array( $post['ccap_mode'] ?? null ) ? $post['ccap_mode'] : array();
		$input  = array();
		$errors = array();
		foreach ( $keys as $key ) {
			$def = SettingsFields::schema( $tab, $key );
			$ui  = SettingsFields::ui( $tab, $key );
			if ( null === $def || 'static' === $ui['element'] ) {
				continue;
			}
			list( $f, $sub ) = array_pad( explode( '.', $key, 2 ), 2, '' );
			$posted          = '' === $sub ? ( $raw[ $f ] ?? null ) : ( is_array( $raw[ $f ] ?? null ) ? ( $raw[ $f ][ $sub ] ?? null ) : null );
			$value           = self::decode_one( $ui['element'], $def, $posted, $modes[ str_replace( '.', '__', $key ) ] ?? null, self::value_of( $current, $key ), $error );
			if ( null !== $error ) {
				$errors[ $f ] = $error;
			}
			if ( '' === $sub ) {
				$input[ $f ] = $value;
				continue;
			}
			if ( ! isset( $input[ $f ] ) || ! is_array( $input[ $f ] ) ) {
				$input[ $f ] = is_array( $current[ $f ] ?? null ) ? $current[ $f ] : array();
			}
			$input[ $f ][ $sub ] = $value;
		}
		return array(
			'input'  => $input,
			'fields' => array_keys( $input ),
			'errors' => $errors,
		);
	}

	/**
	 * Decode one value.
	 *
	 * @param string                                                 $element Element.
	 * @param array<string, mixed>                                   $def     Schema definition.
	 * @param mixed                                                  $posted  Posted value.
	 * @param mixed                                                  $mode    Mode of nullable numbers.
	 * @param mixed                                                  $current Current value.
	 * @param array{rule: string, params: array<string, mixed>}|null $error   Decode error (out).
	 * @return mixed
	 */
	private static function decode_one( string $element, array $def, $posted, $mode, $current, &$error ) {
		$error = null;
		switch ( $element ) {
			case 'checkbox':
				return is_string( $posted ) && '1' === $posted;
			case 'hardening':
				if ( is_string( $posted ) && '1' === $posted ) {
					return 'force' === $current ? 'force' : 'auto';
				}
				return 'off';
			case 'xmlrpc':
				if ( 'auto' === $posted ) {
					return 'force' === $current ? 'force' : 'auto';
				}
				return 'off';
			case 'tristate':
				return '1' === $posted ? true : ( '0' === $posted ? false : null );
			case 'radio':
				if ( 'bool' === $def['type'] ) {
					return '1' === $posted;
				}
				return is_string( $posted ) ? $posted : (string) $current;
			case 'nullable_number':
				return 'none' === $mode ? null : ( is_string( $posted ) ? trim( $posted ) : '' );
			case 'number':
			case 'page':
				return is_string( $posted ) ? trim( $posted ) : '';
			case 'set':
				return is_array( $posted ) ? array_values( array_filter( $posted, 'is_string' ) ) : array();
			case 'bools':
				$out = array();
				foreach ( array_keys( (array) ( $def['fields'] ?? array() ) ) as $item ) {
					$out[ $item ] = is_array( $posted ) && isset( $posted[ $item ] ) && '1' === $posted[ $item ];
				}
				return $out;
			case 'lines':
				$result = self::decode_lines( is_string( $posted ) ? $posted : '', (int) ( $def['max'] ?? 50 ) );
				$error  = $result['error'];
				return $result['value'];
			case 'hosts':
				$result = self::decode_hosts( is_string( $posted ) ? $posted : '', (int) ( $def['max'] ?? 50 ) );
				$error  = $result['error'];
				return $result['value'];
			case 'percent':
				$result = self::decode_percent( is_string( $posted ) ? $posted : '', $current );
				$error  = $result['error'];
				return $result['value'];
		}
		return is_string( $posted ) ? $posted : '';
	}

	/**
	 * One host name per line with the line number of the first error (SET-CC-13).
	 *
	 * @param string $text Text.
	 * @param int    $max  Max entries.
	 * @return array{value: list<string>, error: array{rule: string, params: array<string, mixed>}|null}
	 */
	private static function decode_hosts( string $text, int $max ): array {
		$error = null;
		$hosts = array();
		$split = preg_split( '/\r\n|\r|\n/', $text );
		foreach ( false === $split ? array() : $split as $i => $line ) {
			$line = strtolower( trim( $line ) );
			if ( '' === $line ) {
				continue;
			}
			$hosts[] = $line;
			if ( null === $error && ! SettingsSchema::is_host( $line ) ) {
				$error = array(
					'rule'   => 'host',
					'params' => array(
						'line'  => $i + 1,
						'value' => $line,
					),
				);
			}
		}
		if ( null === $error && count( $hosts ) > $max ) {
			$error = array(
				'rule'   => 'count',
				'params' => array( 'count' => count( $hosts ) ),
			);
		}
		return array(
			'value' => $hosts,
			'error' => $error,
		);
	}

	/**
	 * One IP/CIDR per line with the line number of the first error (F-81…F-83).
	 *
	 * @param string $text Text.
	 * @param int    $max  Max entries.
	 * @return array{value: list<string>, error: array{rule: string, params: array<string, mixed>}|null}
	 */
	private static function decode_lines( string $text, int $max ): array {
		$error = null;
		$lines = array();
		$split = preg_split( '/\r\n|\r|\n/', $text );
		foreach ( false === $split ? array() : $split as $i => $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$lines[] = $line;
			if ( null !== $error ) {
				continue;
			}
			if ( in_array( $line, array( '0.0.0.0/0', '::/0' ), true ) || 1 === preg_match( '#/0$#', $line ) ) {
				$error = array(
					'rule'   => 'ip_all',
					'params' => array(
						'line'  => $i + 1,
						'value' => $line,
					),
				);
				continue;
			}
			$parts = explode( '/', $line, 2 );
			$cidr  = ! isset( $parts[1] ) || ( 1 === preg_match( '/^\d{1,3}$/', $parts[1] ) && (int) $parts[1] <= ( str_contains( $parts[0], ':' ) ? 128 : 32 ) );
			if ( false === filter_var( $parts[0], FILTER_VALIDATE_IP ) || ! $cidr ) {
				$error = array(
					'rule'   => 'ip',
					'params' => array(
						'line'  => $i + 1,
						'value' => $line,
					),
				);
			}
		}
		if ( null === $error && count( $lines ) > $max ) {
			$error = array(
				'rule'   => 'count',
				'params' => array( 'count' => count( $lines ) ),
			);
		}
		return array(
			'value' => $lines,
			'error' => $error,
		);
	}

	/**
	 * Percentage with at most one decimal (comma or point) to a reduced
	 * fraction over 1000 (F-31, F-32; ADM-096).
	 *
	 * @param string $text    Text.
	 * @param mixed  $current Current fraction.
	 * @return array{value: mixed, error: array{rule: string, params: array<string, mixed>}|null}
	 */
	private static function decode_percent( string $text, $current ): array {
		$text = str_replace( array( ' ', '%' ), '', trim( $text ) );
		$rule = null;
		if ( 1 === preg_match( '/^\d{1,3}[.,]\d{2,}$/', $text ) ) {
			$rule = 'percent_precision';
		} elseif ( 1 !== preg_match( '/^\d{1,3}([.,]\d)?$/', $text ) ) {
			$rule = 'percent_range';
		}
		$tenths = null === $rule ? (int) round( (float) str_replace( ',', '.', $text ) * 10 ) : 0;
		if ( null === $rule && ( $tenths <= 0 || $tenths > 1000 ) ) {
			$rule = 'percent_range';
		}
		if ( null !== $rule ) {
			return array(
				'value' => $text,
				'error' => array(
					'rule'   => $rule,
					'params' => array(),
				),
			);
		}
		if ( is_array( $current ) && isset( $current['num'], $current['den'] ) && (int) $current['den'] > 0 && (int) round( (int) $current['num'] * 1000 / (int) $current['den'] ) === $tenths ) {
			return array(
				'value' => $current,
				'error' => null,
			);
		}
		$gcd = self::gcd( $tenths, 1000 );
		return array(
			'value' => array(
				'num' => intdiv( $tenths, $gcd ),
				'den' => intdiv( 1000, $gcd ),
			),
			'error' => null,
		);
	}

	/**
	 * Greatest common divisor.
	 *
	 * @param int $a A.
	 * @param int $b B.
	 * @return int
	 */
	private static function gcd( int $a, int $b ): int {
		while ( 0 !== $b ) {
			$t = $b;
			$b = $a % $b;
			$a = $t;
		}
		return max( 1, $a );
	}

	/**
	 * Turn service errors (`field => rule`) into field errors with parameters.
	 *
	 * An object field (e.g. `hardening.login_limits`) whose value fails as a
	 * whole gets the rule of its first invalid part and `params.sub`, so the
	 * message names the real limits and only that part is marked (SET-CC-P5).
	 *
	 * @param array<string, string> $errors Errors.
	 * @param array<string, mixed>  $input  Input.
	 * @param string                $tab    Tab (for the parts of object fields).
	 * @return array<string, array{rule: string, params: array<string, mixed>}>
	 */
	public static function with_params( array $errors, array $input, string $tab = '' ): array {
		$schema = '' === $tab ? array() : ( SettingsSchema::fields()[ $tab ] ?? array() );
		$out    = array();
		foreach ( $errors as $field => $rule ) {
			$value  = $input[ $field ] ?? '';
			$params = array();
			if ( 'invalid' === $rule && isset( $schema[ $field ] ) ) {
				$part = SettingsRules::invalid_part( $schema[ $field ], $value );
				if ( null !== $part ) {
					$rule          = $part['rule'];
					$params['sub'] = $part['sub'];
					$value         = is_array( $value ) ? ( $value[ $part['sub'] ] ?? '' ) : '';
				}
			}
			$value         = is_scalar( $value ) ? (string) $value : '';
			$out[ $field ] = array(
				'rule'   => $rule,
				'params' => array(
					'value'      => $value,
					'suggestion' => in_array( $rule, array( 'format', 'reserved', 'collision' ), true ) ? SettingsRules::slug_suggestion( $value . ( 'format' === $rule ? '' : '-1' ) ) : '',
				) + $params,
			);
		}
		return $out;
	}

	/**
	 * Error of a rendered key: an error with `params.sub` belongs only to that part.
	 *
	 * @param array<string, array{rule: string, params: array<string, mixed>}> $errors Errors by field.
	 * @param string                                                           $key    Rendered key.
	 * @return array{rule: string, params: array<string, mixed>}|null
	 */
	public static function error_for( array $errors, string $key ): ?array {
		list( $f, $sub ) = array_pad( explode( '.', $key, 2 ), 2, '' );
		$error           = $errors[ $f ] ?? null;
		if ( null === $error ) {
			return null;
		}
		$part = (string) ( $error['params']['sub'] ?? '' );
		return '' === $part || '' === $sub || $part === $sub ? $error : null;
	}

	/**
	 * Rendered key an error summary links to (the invalid part, else the first key).
	 *
	 * @param string                                                 $field Field.
	 * @param array{rule: string, params: array<string, mixed>}|null $error Error.
	 * @param array                                                  $keys  Rendered keys.
	 * @phpstan-param list<string> $keys
	 * @return string
	 */
	public static function error_key( string $field, ?array $error, array $keys ): string {
		$part = (string) ( $error['params']['sub'] ?? '' );
		return '' !== $part && in_array( $field . '.' . $part, $keys, true ) ? $field . '.' . $part : self::first_key( $field, $keys );
	}

	/**
	 * Error summary (T-5/W-5): `role="alert"`, focusable, links to fields.
	 *
	 * @param string                                                           $tab    Tab.
	 * @param array<string, array{rule: string, params: array<string, mixed>}> $errors Errors.
	 * @param array                                                            $keys   Rendered keys.
	 * @phpstan-param list<string> $keys
	 * @return string
	 */
	public static function summary( string $tab, array $errors, array $keys ): string {
		if ( array() === $errors ) {
			return '';
		}
		$items = '';
		foreach ( $errors as $field => $error ) {
			$key    = self::error_key( $field, $error, $keys );
			$label  = SettingsFields::ui( $tab, $key )['label'];
			$items .= '<li><a href="#' . esc_attr( self::id( $tab, $key ) ) . '">' . esc_html( $label ) . '</a></li>';
		}
		/* translators: %d: number of fields with errors */
		$title = sprintf( _n( '%d entry must be corrected:', '%d entries must be corrected:', count( $errors ), 'course-captain' ), count( $errors ) );
		return '<div id="ccap-error-summary" class="notice notice-error ccap-error-summary" role="alert" tabindex="-1" data-ccap-focus="1"><p><strong>' . esc_html( $title ) . '</strong></p><ul>' . $items . '</ul></div>';
	}

	/**
	 * First rendered key of a field.
	 *
	 * @param string $field Field.
	 * @param array  $keys  Keys.
	 * @phpstan-param list<string> $keys
	 * @return string
	 */
	public static function first_key( string $field, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( $key === $field || str_starts_with( $key, $field . '.' ) ) {
				return $key;
			}
		}
		return $field;
	}

	/**
	 * One field.
	 *
	 * @param string                                                 $tab      Tab.
	 * @param string                                                 $key      Key.
	 * @param mixed                                                  $value    Value (register value or raw input after an error).
	 * @param array{rule: string, params: array<string, mixed>}|null $error    Error.
	 * @param array<string, mixed>                                   $opts     `readonly`, `disabled_reason`, `warning`, `status`, `extra_help`, `form`, `root` (name prefix).
	 * @return string
	 */
	public static function field( string $tab, string $key, $value, ?array $error = null, array $opts = array() ): string {
		$ui              = SettingsFields::ui( $tab, $key );
		$def             = SettingsFields::schema( $tab, $key ) ?? array(
			'type'    => 'string',
			'default' => '',
		);
		$id              = self::id( $tab, $key );
		list( $f, $sub ) = array_pad( explode( '.', $key, 2 ), 2, '' );
		$root            = isset( $opts['root'] ) ? (string) $opts['root'] : '';
		$name            = ( '' === $root ? 'ccap' : $root . '[ccap]' ) . '[' . $f . ']' . ( '' === $sub ? '' : '[' . $sub . ']' );
		$describe        = isset( $opts['status'] ) ? array( $id . '-help', $id . '-meta', $id . '-status' ) : array( $id . '-help', $id . '-meta' );
		$help            = $ui['help'] . ( isset( $opts['extra_help'] ) ? ' ' . (string) $opts['extra_help'] : '' );
		$disabled        = ! empty( $opts['disabled_reason'] );
		if ( $disabled ) {
			$help .= ' ' . (string) $opts['disabled_reason'];
		}
		$err_html = '';
		// "Legal review" marking (SET-CC-06): the operator decides with their legal advice whether and how the value is required.
		if ( ! empty( $def['legal_review'] ) ) {
			$describe[] = $id . '-legal';
			$err_html  .= '<p id="' . esc_attr( $id . '-legal' ) . '" class="description ccap-legal-review"><strong>' . esc_html__( 'Legal review:', 'course-captain' ) . '</strong> ' . esc_html__( 'Whether this is required for your website and what it must contain is your decision. Course Captain does not check it.', 'course-captain' ) . '</p>';
		}
		if ( null !== $error ) {
			$describe[] = $id . '-error';
			$err_html  .= '<p id="' . esc_attr( $id . '-error' ) . '" class="ccap-field-error"><strong>' . esc_html( SettingsFields::message( $ui['label'], $error['rule'], $def, $error['params'] ) ) . '</strong></p>';
		}
		if ( ! empty( $opts['warning'] ) ) {
			$describe[] = $id . '-warning';
			$err_html  .= '<p id="' . esc_attr( $id . '-warning' ) . '" class="ccap-field-warning">' . esc_html( (string) $opts['warning'] ) . '</p>';
		}
		$aria    = ' aria-describedby="' . esc_attr( implode( ' ', $describe ) ) . '"' . ( null !== $error ? ' aria-invalid="true"' : '' ) . ( $disabled ? ' aria-disabled="true"' : '' ) . ( ! empty( $opts['readonly'] ) ? ' disabled' : '' ) . ( isset( $opts['form'] ) ? ' form="' . esc_attr( (string) $opts['form'] ) . '"' : '' );
		$control = self::control( $ui, $def, $id, $name, $key, $value, $aria, '' === $root ? 'ccap_mode' : $root . '[ccap_mode]' );
		$group   = in_array( $ui['element'], array( 'radio', 'tristate', 'nullable_number', 'set', 'bools', 'xmlrpc' ), true );
		$status  = isset( $opts['status'] ) ? ' <span id="' . esc_attr( $id . '-status' ) . '" class="ccap-field-status">' . esc_html( (string) $opts['status'] ) . '</span>' : '';
		$label   = $group
			? '<legend id="' . esc_attr( $id ) . '-legend" class="ccap-field-label">' . esc_html( $ui['label'] ) . '</legend>'
			: '<label for="' . esc_attr( $id ) . '" class="ccap-field-label">' . esc_html( $ui['label'] ) . '</label>';
		$meta    = self::meta( $tab, $ui, $def );
		$body    = $label . $control . $status
			. '<p id="' . esc_attr( $id . '-help' ) . '" class="description ccap-field-help">' . esc_html( trim( $help ) ) . '</p>'
			. '<p id="' . esc_attr( $id . '-meta' ) . '" class="description ccap-field-meta">' . esc_html( $meta ) . '</p>'
			. $err_html;
		$class   = 'ccap-field' . ( null !== $error ? ' ccap-field-invalid' : '' );
		if ( $group ) {
			return '<fieldset class="' . esc_attr( $class ) . '" id="' . esc_attr( $id ) . '" tabindex="-1">' . $body . '</fieldset>';
		}
		return '<div class="' . esc_attr( $class ) . '">' . $body . '</div>';
	}

	/**
	 * Meta line (T-3): default, scope, dependency.
	 *
	 * @param string               $tab Tab.
	 * @param array<string, mixed> $ui  UI definition.
	 * @param array<string, mixed> $def Schema.
	 * @return string
	 */
	private static function meta( string $tab, array $ui, array $def ): string {
		/* translators: 1: default value, 2: scope */
		$meta = sprintf( __( 'Default: %1$s · Applies: %2$s', 'course-captain' ), self::display( $ui, $def, $def['default'] ?? null ), SettingsFields::scope_label( (string) $ui['scope'] ) );
		if ( '' !== $ui['depends'] ) {
			/* translators: %s: label of the field this one depends on */
			$meta .= ' · ' . sprintf( __( 'Depends on: %s', 'course-captain' ), SettingsFields::ui( $tab, (string) $ui['depends'] )['label'] );
		}
		return $meta;
	}

	/**
	 * Readable value.
	 *
	 * @param array<string, mixed> $ui    UI definition.
	 * @param array<string, mixed> $def   Schema.
	 * @param mixed                $value Value.
	 * @return string
	 */
	public static function display( array $ui, array $def, $value ): string {
		if ( null === $value ) {
			return '' !== $ui['none'] ? (string) $ui['none'] : (string) ( $ui['options'][''] ?? __( 'none', 'course-captain' ) );
		}
		if ( 'hardening' === $ui['element'] ) {
			return 'off' === $value ? __( 'off until saved once, then on', 'course-captain' ) : __( 'on', 'course-captain' );
		}
		if ( is_bool( $value ) ) {
			$key = $value ? '1' : '0';
			return isset( $ui['options'][ $key ] ) ? (string) $ui['options'][ $key ] : ( $value ? __( 'on', 'course-captain' ) : __( 'off', 'course-captain' ) );
		}
		if ( 'percent' === $ui['element'] && is_array( $value ) && (int) ( $value['den'] ?? 0 ) > 0 ) {
			return self::percent( $value ) . ' %';
		}
		if ( is_array( $value ) ) {
			if ( array() === $value ) {
				return __( 'empty', 'course-captain' );
			}
			$labels = array();
			foreach ( $value as $k => $v ) {
				if ( is_bool( $v ) ) {
					if ( $v ) {
						$labels[] = (string) ( $ui['options'][ $k ] ?? $k );
					}
					continue;
				}
				$labels[] = (string) ( $ui['options'][ (string) $v ] ?? ( is_scalar( $v ) ? (string) $v : '' ) );
			}
			return array() === $labels ? __( 'none', 'course-captain' ) : implode( ', ', $labels );
		}
		if ( '' === $value ) {
			if ( 'url' === $ui['element'] && '' !== $ui['none'] ) {
				return (string) $ui['none'];
			}
			return __( 'empty', 'course-captain' );
		}
		if ( is_scalar( $value ) && isset( $ui['options'][ (string) $value ] ) ) {
			return (string) $ui['options'][ (string) $value ];
		}
		if ( 'page' === $ui['element'] && 0 === $value ) {
			return (string) $ui['none'];
		}
		return trim( ( is_scalar( $value ) ? (string) $value : '' ) . ' ' . (string) $ui['unit'] );
	}

	/**
	 * Fraction as percentage text with at most one decimal.
	 *
	 * @param array<string, mixed> $value Fraction.
	 * @return string
	 */
	public static function percent( array $value ): string {
		$tenths = (int) round( (int) $value['num'] * 1000 / max( 1, (int) $value['den'] ) );
		return 0 === $tenths % 10 ? (string) intdiv( $tenths, 10 ) : number_format_i18n( $tenths / 10, 1 );
	}

	/**
	 * The input control(s).
	 *
	 * @param array<string, mixed> $ui    UI definition.
	 * @param array<string, mixed> $def   Schema.
	 * @param string               $id    ID.
	 * @param string               $name  Name.
	 * @param string               $key   Key.
	 * @param mixed                $value Value.
	 * @param string               $aria  Common attributes.
	 * @param string               $modes Name of the mode array of nullable numbers.
	 * @return string
	 */
	private static function control( array $ui, array $def, string $id, string $name, string $key, $value, string $aria, string $modes ): string {
		switch ( $ui['element'] ) {
			case 'checkbox':
			case 'hardening':
				$on = 'hardening' === $ui['element'] ? ( is_string( $value ) && 'off' !== $value ) : ( true === $value || '1' === $value );
				return '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"' . self::form_attr( $aria ) . '><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( $on, true, false ) . $aria . '>';
			case 'radio':
			case 'tristate':
			case 'xmlrpc':
				$current = is_bool( $value ) ? ( $value ? '1' : '0' ) : ( null === $value ? '' : ( is_scalar( $value ) ? (string) $value : '' ) );
				if ( 'xmlrpc' === $ui['element'] ) {
					$current = 'off' === $current ? 'off' : 'auto';
				}
				$html = '';
				$i    = 0;
				foreach ( $ui['options'] as $opt => $label ) {
					$oid   = $id . '-' . ( $i++ );
					$html .= '<label for="' . esc_attr( $oid ) . '" class="ccap-choice"><input type="radio" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $opt ) . '"' . checked( (string) $opt, $current, false ) . $aria . '> ' . esc_html( (string) $label ) . '</label>';
				}
				return $html;
			case 'select':
				$html = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $aria . '>';
				foreach ( $ui['options'] as $opt => $label ) {
					$html .= '<option value="' . esc_attr( (string) $opt ) . '"' . selected( (string) $opt, is_scalar( $value ) ? (string) $value : '', false ) . '>' . esc_html( (string) $label ) . '</option>';
				}
				return $html . '</select>';
			case 'nullable_number':
				$mode_name = $modes . '[' . str_replace( '.', '__', $key ) . ']';
				$is_none   = null === $value;
				$unit      = '' !== $ui['unit'] ? ' <span id="' . esc_attr( $id ) . '-unit">' . esc_html( (string) $ui['unit'] ) . '</span>' : '';
				return '<label for="' . esc_attr( $id ) . '-none" class="ccap-choice"><input type="radio" id="' . esc_attr( $id ) . '-none" name="' . esc_attr( $mode_name ) . '" value="none"' . checked( $is_none, true, false ) . $aria . '> ' . esc_html( (string) $ui['none'] ) . '</label>'
					. '<label for="' . esc_attr( $id ) . '-value" class="ccap-choice"><input type="radio" id="' . esc_attr( $id ) . '-value" name="' . esc_attr( $mode_name ) . '" value="value"' . checked( $is_none, false, false ) . $aria . '> ' . esc_html__( 'Value:', 'course-captain' ) . '</label> '
					. '<input type="number" id="' . esc_attr( $id ) . '-number" name="' . esc_attr( $name ) . '" value="' . esc_attr( $is_none ? '' : ( is_scalar( $value ) ? (string) $value : '' ) ) . '" min="' . esc_attr( (string) ( $def['min'] ?? '' ) ) . '" max="' . esc_attr( (string) ( $def['max'] ?? '' ) ) . '" class="small-text" aria-labelledby="' . esc_attr( $id ) . '-legend ' . esc_attr( $id ) . '-value' . ( '' !== $unit ? ' ' . esc_attr( $id ) . '-unit' : '' ) . '"' . $aria . '>' . $unit;
			case 'number':
				$unit = '' !== $ui['unit'] ? ' <span>' . esc_html( (string) $ui['unit'] ) . '</span>' : '';
				return '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '" min="' . esc_attr( (string) ( $def['min'] ?? '' ) ) . '" max="' . esc_attr( (string) ( $def['max'] ?? '' ) ) . '" class="small-text"' . $aria . '>' . $unit;
			case 'percent':
				$text = is_array( $value ) && isset( $value['num'], $value['den'] ) ? self::percent( $value ) : ( is_scalar( $value ) ? (string) $value : '' );
				return '<input type="text" inputmode="decimal" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $text ) . '" class="small-text"' . $aria . '> %';
			case 'textarea':
				return '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" class="large-text"' . $aria . '>' . esc_textarea( is_scalar( $value ) ? (string) $value : '' ) . '</textarea>';
			case 'lines':
			case 'hosts':
				return '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="4" class="large-text code"' . $aria . '>' . esc_textarea( is_array( $value ) ? implode( "\n", array_map( 'strval', $value ) ) : ( is_scalar( $value ) ? (string) $value : '' ) ) . '</textarea>';
			case 'url':
				return '<input type="url" inputmode="url" autocomplete="off" spellcheck="false" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '" class="large-text code"' . $aria . '>';
			case 'email':
				return '<input type="email" autocomplete="off" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '" class="regular-text"' . $aria . '>';
			case 'page':
				$pages = get_pages(
					array(
						'post_status' => 'publish',
						'number'      => 200,
					)
				);
				$html  = '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $aria . '><option value="0">' . esc_html( (string) $ui['none'] ) . '</option>';
				$cur   = is_scalar( $value ) ? (int) $value : 0;
				$found = 0 === $cur;
				foreach ( is_array( $pages ) ? $pages : array() as $page ) {
					$found = $found || (int) $page->ID === $cur;
					$html .= '<option value="' . esc_attr( (string) $page->ID ) . '"' . selected( (int) $page->ID, $cur, false ) . '>' . esc_html( get_the_title( $page ) ) . '</option>';
				}
				if ( ! $found ) {
					/* translators: %d: page ID */
					$html .= '<option value="' . esc_attr( (string) $cur ) . '" selected>' . esc_html( sprintf( __( 'Page %d (not published)', 'course-captain' ), $cur ) ) . '</option>';
				}
				return $html . '</select>';
			case 'set':
			case 'bools':
				$html = '';
				$i    = 0;
				foreach ( $ui['options'] as $opt => $label ) {
					$oid = $id . '-' . ( $i++ );
					if ( 'set' === $ui['element'] ) {
						$on    = is_array( $value ) && in_array( (string) $opt, array_map( 'strval', $value ), true );
						$html .= '<label for="' . esc_attr( $oid ) . '" class="ccap-choice"><input type="checkbox" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( (string) $opt ) . '"' . checked( $on, true, false ) . $aria . '> ' . esc_html( (string) $label ) . '</label>';
						continue;
					}
					$on = is_array( $value ) && ! empty( $value[ $opt ] );
					/* translators: %s: message name */
					$html .= '<label for="' . esc_attr( $oid ) . '" class="ccap-choice"><input type="checkbox" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name . '[' . $opt . ']' ) . '" value="1"' . checked( $on, true, false ) . $aria . '> ' . esc_html( sprintf( __( 'Send “%s”', 'course-captain' ), (string) $label ) ) . '</label>';
				}
				return $html;
		}
		return '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '" class="regular-text"' . ( 'slug' === ( $def['type'] ?? '' ) ? ' spellcheck="false" autocapitalize="off"' : '' ) . $aria . '>';
	}

	/**
	 * `form` attribute part of the common attributes (hidden inputs need it, nothing else).
	 *
	 * @param string $aria Common attributes.
	 * @return string
	 */
	private static function form_attr( string $aria ): string {
		return 1 === preg_match( '/ form="[^"]*"/', $aria, $m ) ? $m[0] : '';
	}
}
