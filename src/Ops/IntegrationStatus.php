<?php
/**
 * Read-only partner detection for the integration status list (R0-34 §1.3,
 * §2.1–§2.3; R0-19 §6, CT-09; E-185, E-254, E-256).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Static catalog, changed only per release (never by filter, R0-34 §1.3).
 * The probe reads documented public constants/functions only; the plugin
 * list (`get_plugins()`) only separates `not_installed` from `inactive`.
 * Detection never writes options and fires no partner hooks (AB-1, INT-007).
 * Free knows the states `not_installed`, `inactive`, `active_incompatible`
 * and `active_compatible`; `actions` is always empty in R1.
 */
final class IntegrationStatus {

	public const CAP            = 'ccap_view_system_status';
	public const SCHEMA_VERSION = '1.0';

	public const NOT_INSTALLED       = 'not_installed';
	public const INACTIVE            = 'inactive';
	public const ACTIVE_INCOMPATIBLE = 'active_incompatible';
	public const ACTIVE_COMPATIBLE   = 'active_compatible';

	/**
	 * Partner catalog R1 (R0-34 §1.3).
	 *
	 * `rule`: `version` (constant version ≥ minimum), `contract_major`
	 * (contract constant with the given major), `no_contract` (no published
	 * contract: active means `active_incompatible/no_published_contract`).
	 *
	 * @return list<array{id: string, label: string, slug: string, version_constant: string, function: string, contract_constant: string, rule: string, required: string, ok_reason: string}>
	 */
	public static function catalog(): array {
		return array(
			array(
				'id'                => 'woocommerce',
				'label'             => 'WooCommerce',
				'slug'              => 'woocommerce',
				'version_constant'  => 'WC_VERSION',
				'function'          => 'WC',
				'contract_constant' => '',
				'rule'              => 'version',
				'required'          => '8.0.0',
				'ok_reason'         => 'connector_missing',
			),
			array(
				'id'                => 'voucher-captain',
				'label'             => 'Voucher Captain',
				'slug'              => 'voucher-captain',
				'version_constant'  => 'VCAP_VERSION',
				'function'          => 'vcap',
				'contract_constant' => '',
				'rule'              => 'version',
				'required'          => '1.1.0',
				'ok_reason'         => 'connector_missing',
			),
			array(
				'id'                => 'affiliate-captain',
				'label'             => 'Affiliate Captain',
				'slug'              => 'affiliate-captain',
				'version_constant'  => '',
				'function'          => '',
				'contract_constant' => 'WPAC_CONTRACT_VERSION',
				'rule'              => 'contract_major',
				'required'          => '1',
				'ok_reason'         => 'no_exchange_planned',
			),
			array(
				'id'                => 'pass-captain',
				'label'             => 'Pass Captain',
				'slug'              => 'pass-captain',
				'version_constant'  => '',
				'function'          => '',
				'contract_constant' => '',
				'rule'              => 'no_contract',
				'required'          => '',
				'ok_reason'         => '',
			),
			array(
				'id'                => 'form-captain',
				'label'             => 'Form Captain',
				'slug'              => 'form-captain',
				'version_constant'  => '',
				'function'          => '',
				'contract_constant' => '',
				'rule'              => 'no_contract',
				'required'          => '',
				'ok_reason'         => '',
			),
			array(
				'id'                => 'workflow-captain',
				'label'             => 'Workflow Captain',
				'slug'              => 'workflow-captain',
				'version_constant'  => '',
				'function'          => '',
				'contract_constant' => '',
				'rule'              => 'no_contract',
				'required'          => '',
				'ok_reason'         => '',
			),
			array(
				'id'                => 'review-captain',
				'label'             => 'Review Captain',
				'slug'              => 'review-captain',
				'version_constant'  => '',
				'function'          => '',
				'contract_constant' => '',
				'rule'              => 'no_contract',
				'required'          => '',
				'ok_reason'         => '',
			),
		);
	}

	/**
	 * Status records (schema `IntegrationStatus` v1, R1 fields).
	 *
	 * @param Actor                    $actor         Viewer (`ccap_view_system_status`; CLI allowed).
	 * @param bool                     $detected_only Only partners that are installed (filter "Only detected", default in the UI).
	 * @param array<string, bool>|null $plugins       Plugin slug => active (tests); default from WordPress.
	 * @return list<array<string, mixed>>
	 * @throws CourseException Without the capability.
	 */
	public static function all( Actor $actor, bool $detected_only = false, ?array $plugins = null ): array {
		if ( ! $actor->is_system() && ( $actor->user_id <= 0 || ! user_can( $actor->user_id, self::CAP ) ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to view the integration status.', 403 );
		}
		$plugins = $plugins ?? self::plugins();
		$now     = gmdate( 'Y-m-d\TH:i:s\Z' );
		$out     = array();
		foreach ( self::catalog() as $entry ) {
			$probe   = self::probe( $entry, $plugins );
			$derived = self::derive( $entry, $probe );
			if ( $detected_only && self::NOT_INSTALLED === $derived['state'] ) {
				continue;
			}
			$out[] = array(
				'provider_id'    => $entry['id'],
				'label'          => $entry['label'],
				'kind'           => 'partner',
				'partner'        => array(
					'detected'         => $probe['detected'],
					'version'          => $probe['version'],
					'contract_version' => $probe['contract'],
					'required'         => self::required( $entry ),
				),
				'course'         => array( 'contract_version' => defined( 'COURSE_CAPTAIN_CONTRACT_VERSION' ) ? (string) COURSE_CAPTAIN_CONTRACT_VERSION : '' ),
				'state'          => $derived['state'],
				'state_reason'   => $derived['reason'],
				'checked_at'     => $now,
				'privacy'        => array( 'class' => 'IK-0' ),
				'actions'        => array(),
				'schema_version' => self::SCHEMA_VERSION,
			);
		}
		return $out;
	}

	/**
	 * Pure derivation of state and reason (R0-34 §2.2, §2.3; R0-19 §6).
	 *
	 * @param array{rule: string, required: string, ok_reason: string}             $entry Catalog entry.
	 * @param array{detected: string, version: string|null, contract: string|null} $probe Probe result.
	 * @return array{state: string, reason: string}
	 */
	public static function derive( array $entry, array $probe ): array {
		if ( 'missing' === $probe['detected'] ) {
			return array(
				'state'  => self::NOT_INSTALLED,
				'reason' => 'not_found',
			);
		}
		if ( 'installed' === $probe['detected'] ) {
			return array(
				'state'  => self::INACTIVE,
				'reason' => 'plugin_inactive',
			);
		}
		$incompatible = static fn( string $reason ): array => array(
			'state'  => self::ACTIVE_INCOMPATIBLE,
			'reason' => $reason,
		);
		switch ( $entry['rule'] ) {
			case 'version':
				if ( null === $probe['version'] ) {
					// Slug alone is never enough (INT-004).
					return $incompatible( 'no_published_contract' );
				}
				if ( version_compare( $probe['version'], $entry['required'], '<' ) ) {
					return $incompatible( 'version_too_low' );
				}
				break;
			case 'contract_major':
				if ( null === $probe['contract'] ) {
					return $incompatible( 'no_published_contract' );
				}
				if ( (string) (int) $probe['contract'] !== $entry['required'] ) {
					return $incompatible( 'contract_major_mismatch' );
				}
				break;
			default:
				return $incompatible( 'no_published_contract' );
		}
		return array(
			'state'  => self::ACTIVE_COMPATIBLE,
			'reason' => $entry['ok_reason'],
		);
	}

	/**
	 * Probe one partner (reads constants and functions only).
	 *
	 * @param array{slug: string, version_constant: string, function: string, contract_constant: string} $entry   Catalog entry.
	 * @param array<string, bool>                                                                        $plugins Plugin slug => active.
	 * @return array{detected: string, version: string|null, contract: string|null}
	 */
	public static function probe( array $entry, array $plugins ): array {
		$version  = null;
		$contract = null;
		$api      = false;
		if ( '' !== $entry['version_constant'] && Compatibility::constant( $entry['version_constant'] ) && ( '' === $entry['function'] || Compatibility::has_function( $entry['function'] ) ) ) {
			$version = self::semver( Compatibility::constant_value( $entry['version_constant'] ) );
			$api     = null !== $version;
		}
		if ( '' !== $entry['contract_constant'] && Compatibility::constant( $entry['contract_constant'] ) ) {
			$contract = self::semver( Compatibility::constant_value( $entry['contract_constant'] ) );
			$api      = $api || null !== $contract;
		}
		if ( $api || ! empty( $plugins[ $entry['slug'] ] ) ) {
			$detected = 'active';
		} elseif ( array_key_exists( $entry['slug'], $plugins ) ) {
			$detected = 'installed';
		} else {
			$detected = 'missing';
		}
		return array(
			'detected' => $detected,
			'version'  => $version,
			'contract' => $contract,
		);
	}

	/**
	 * Installed plugins of this site as directory slug => active (read-only).
	 *
	 * @return array<string, bool>
	 */
	public static function plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$out = array();
		foreach ( array_keys( get_plugins() ) as $file ) {
			$slug         = str_contains( (string) $file, '/' ) ? dirname( (string) $file ) : basename( (string) $file, '.php' );
			$out[ $slug ] = ( $out[ $slug ] ?? false ) || is_plugin_active( (string) $file );
		}
		return $out;
	}

	/**
	 * Translated badge and short text of a record (R0-34 §3.3).
	 *
	 * @param array<string, mixed> $record Record from all().
	 * @return array{badge: string, text: string}
	 */
	public static function texts( array $record ): array {
		$label    = (string) $record['label'];
		$partner  = (array) $record['partner'];
		$found    = (string) ( $partner['version'] ?? $partner['contract_version'] ?? '' );
		$required = (string) ( $partner['required'] ?? '' );
		switch ( $record['state'] . '/' . $record['state_reason'] ) {
			case 'not_installed/not_found':
				$badge = __( 'Not installed', 'course-captain' );
				/* translators: %s: plugin name */
				$text = sprintf( __( '%s is not installed on this site. Course Captain works without this plugin.', 'course-captain' ), $label );
				break;
			case 'inactive/plugin_inactive':
				$badge = __( 'Deactivated', 'course-captain' );
				/* translators: %s: plugin name */
				$text = sprintf( __( '%s is installed but not active. Existing access stays in place.', 'course-captain' ), $label );
				break;
			case 'active_incompatible/version_too_low':
			case 'active_incompatible/contract_major_mismatch':
				$badge = __( 'Not compatible', 'course-captain' );
				/* translators: 1: plugin name, 2: found version, 3: required version */
				$text = sprintf( __( 'Found: %1$s %2$s. Required: %3$s. Until then Course Captain exchanges no data.', 'course-captain' ), $label, $found, $required );
				break;
			case 'active_incompatible/no_published_contract':
				$badge = __( 'No contract', 'course-captain' );
				/* translators: %s: plugin name */
				$text = sprintf( __( '%s is active. There is no agreed exchange contract for this plugin yet; no data is exchanged.', 'course-captain' ), $label );
				break;
			case 'active_compatible/no_exchange_planned':
				$badge = __( 'Detected', 'course-captain' );
				/* translators: %s: plugin name */
				$text = sprintf( __( '%s is active. Course Captain sends no data to this plugin.', 'course-captain' ), $label );
				break;
			default:
				$badge = __( 'Detected', 'course-captain' );
				/* translators: 1: plugin name, 2: version */
				$text = sprintf( __( '%1$s %2$s is active and compatible. A connector is needed for a data exchange; none is installed.', 'course-captain' ), $label, $found );
		}
		return array(
			'badge' => $badge,
			'text'  => $text,
		);
	}

	/**
	 * Required version or contract as a range.
	 *
	 * @param array{rule: string, required: string} $entry Catalog entry.
	 * @return string
	 */
	private static function required( array $entry ): string {
		return match ( $entry['rule'] ) {
			'version'        => '>=' . $entry['required'],
			'contract_major' => '^' . $entry['required'] . '.0',
			default          => '',
		};
	}

	/**
	 * Accept a version string only in SemVer-like form.
	 *
	 * @param mixed $value Constant value.
	 * @return string|null
	 */
	private static function semver( $value ): ?string {
		$value = is_scalar( $value ) ? (string) $value : '';
		return 1 === preg_match( '/^\d+(\.\d+){0,2}([-+][0-9A-Za-z.-]+)?$/', $value ) ? $value : null;
	}
}
