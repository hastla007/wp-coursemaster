<?php
/**
 * Activity provider contract (R0-08 §2.2, ADR-004).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity;

defined( 'ABSPATH' ) || exit;

/**
 * Mandatory for every provider. The core knows no hard-wired learning
 * areas; providers come from the core or extension plugins, never from
 * content packages. Optional abilities are separate interfaces
 * (RendersActivity, HandlesInteraction, DeterminesCompletion).
 */
interface ActivityProvider {

	/**
	 * Stable type key `[a-z0-9_]{1,32}`.
	 *
	 * @return string
	 */
	public function type(): string;

	/**
	 * SemVer of the provider contract; major = incompatible settings/evaluation.
	 *
	 * @return string
	 */
	public function contract_version(): string;

	/**
	 * Current settings version (integer).
	 *
	 * @return int
	 */
	public function settings_version(): int;

	/**
	 * Capabilities: completable, gradable, feeds_mastery, produces_evidence,
	 * access_scope, drip_aware, preview_limit_type, needs_external_consent.
	 *
	 * @return array<string, bool|string>
	 */
	public function capabilities(): array;

	/**
	 * Settings schema (subset of JSON Schema, see SettingsSchema).
	 *
	 * @return array<string, mixed>
	 */
	public function settings_schema(): array;

	/**
	 * Supported completion rule types; the first is the default.
	 *
	 * @return list<string>
	 */
	public function completion_rule_types(): array;

	/**
	 * Pure completion decision from rule and stored progress (§5.1).
	 *
	 * @param array<string, mixed> $rule     Rule (`type`, `params`).
	 * @param array<string, mixed> $progress Progress state.
	 * @return string not_started|in_progress|completed
	 */
	public function evaluate_completion( array $rule, array $progress ): string;
}
