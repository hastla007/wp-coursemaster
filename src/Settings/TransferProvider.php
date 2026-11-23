<?php
/**
 * Extra surface of the settings file (SET-CC-15): roles, locks, later mail templates.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * A provider owns one top-level key of the settings file next to `tabs`
 * (R0-27 §2.5: `mail_templates?: {…}`). Providers are collected by
 * {@see SettingsTransfer::providers()} and the filter
 * `course_captain_settings_transfer_providers`; the transfer handles the
 * file format, preview, selection, confirmations, rate limit and the
 * `settings_imported` audit, the provider only reads and writes its own
 * store through its existing service (same validation as its screen).
 *
 * Plan rows: `changed` = list of `{item, before, after, risks, summary?}`
 * (`risks` non-empty = needs the confirmation `<key>.<item>`), `skipped` =
 * list of `{item, reason, risks?}`, `errors` = item => rule. A surface is
 * applied atomically: any error and nothing of it is written.
 */
interface TransferProvider {

	/**
	 * Top-level key in the file (`[a-z0-9_]`, not a reserved head key).
	 *
	 * @return string
	 */
	public function key(): string;

	/**
	 * Translated name for the preview.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Whether the export needs an explicit opt-in (EX-1: roles, mail templates).
	 *
	 * @return bool
	 */
	public function opt_in(): bool;

	/**
	 * Whether the actor may export this surface.
	 *
	 * @param Actor $actor Actor.
	 * @return bool
	 */
	public function can_export( Actor $actor ): bool;

	/**
	 * Export section (no personal data of the exporting person, EX-5).
	 *
	 * @param Actor $actor Actor.
	 * @return array<string, mixed>
	 */
	public function export( Actor $actor ): array;

	/**
	 * Diff of a file section against the current state; writes nothing.
	 *
	 * @param Actor                $actor     Actor.
	 * @param array<string, mixed> $section   File section.
	 * @param array                $confirmed Confirmed items (`*` = all).
	 * @phpstan-param list<string> $confirmed
	 * @return array{changed: list<array<string, mixed>>, skipped: list<array<string, mixed>>, errors: array<string, string>}
	 */
	public function plan( Actor $actor, array $section, array $confirmed ): array;

	/**
	 * Apply the plan (caller checked that it has no errors).
	 *
	 * @param Actor                $actor     Actor.
	 * @param array<string, mixed> $section   File section.
	 * @param array                $confirmed Confirmed items (`*` = all).
	 * @phpstan-param list<string> $confirmed
	 * @return list<string> Applied items.
	 */
	public function apply( Actor $actor, array $section, array $confirmed ): array;
}
