<?php
/**
 * Transfer surface "Setting locks" (SET-CC-15, R0-28 §1.6).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Section `locks` = `{_schema: 1, locks: {<key>: {reason}}}`; who locked
 * and since when stay on the source site (EX-5). The section is the full
 * lock set: keys missing in the file are unlocked on import, keys in the
 * file are (re)locked through {@see EffectiveSettings::lock()} with its
 * preflight and audit. Every row names the number of course and exam
 * overrides that rest or become active again.
 */
final class LocksTransfer implements TransferProvider {

	public const KEY = 'locks';

	/**
	 * Top-level key in the file.
	 *
	 * @return string
	 */
	public function key(): string {
		return self::KEY;
	}

	/**
	 * Translated name.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Setting locks', 'course-captain' );
	}

	/**
	 * Whether the export needs an opt-in.
	 *
	 * @return bool
	 */
	public function opt_in(): bool {
		return false;
	}

	/**
	 * Whether the actor may export this surface.
	 *
	 * @param Actor $actor Actor.
	 * @return bool
	 */
	public function can_export( Actor $actor ): bool {
		return Settings::allowed( $actor, 'settings' );
	}

	/**
	 * Export section.
	 *
	 * @param Actor $actor Actor.
	 * @return array<string, mixed>
	 */
	public function export( Actor $actor ): array {
		$locks = array();
		foreach ( EffectiveSettings::locks() as $key => $lock ) {
			$locks[ $key ] = array( 'reason' => $lock['reason'] );
		}
		ksort( $locks );
		return array(
			'_schema' => 1,
			'locks'   => $locks,
		);
	}

	/**
	 * Diff against the current state; writes nothing.
	 *
	 * @param Actor                $actor     Actor.
	 * @param array<string, mixed> $section   File section.
	 * @param array                $confirmed Confirmed items (`*` = all).
	 * @phpstan-param list<string> $confirmed
	 * @return array{changed: list<array<string, mixed>>, skipped: list<array<string, mixed>>, errors: array<string, string>}
	 */
	public function plan( Actor $actor, array $section, array $confirmed ): array {
		unset( $confirmed );
		$out = array(
			'changed' => array(),
			'skipped' => array(),
			'errors'  => array(),
		);
		if ( ! Settings::allowed( $actor, 'settings' ) ) {
			$out['skipped'][] = array(
				'item'   => '*',
				'reason' => 'forbidden',
			);
			return $out;
		}
		$file    = is_array( $section['locks'] ?? null ) ? $section['locks'] : array();
		$current = EffectiveSettings::locks();
		$wanted  = array();
		foreach ( $file as $key => $lock ) {
			$key = (string) $key;
			if ( empty( SettingsSchema::overridable()[ $key ]['lockable'] ) ) {
				$out['skipped'][] = array(
					'item'   => $key,
					'reason' => 'unknown',
				);
				continue;
			}
			$reason = sanitize_text_field( is_array( $lock ) ? (string) ( $lock['reason'] ?? '' ) : '' );
			if ( mb_strlen( $reason ) > 200 ) {
				$out['errors'][ $key ] = 'reason';
				continue;
			}
			$wanted[ $key ] = $reason;
		}
		ksort( $wanted );
		foreach ( $wanted as $key => $reason ) {
			if ( isset( $current[ $key ] ) && $current[ $key ]['reason'] === $reason ) {
				continue;
			}
			$count            = EffectiveSettings::count_overrides( $key );
			$out['changed'][] = array(
				'item'     => $key,
				'before'   => isset( $current[ $key ] ) ? $current[ $key ]['reason'] : null,
				'after'    => $reason,
				'risks'    => array(),
				'affected' => $count,
				'summary'  => sprintf(
					/* translators: 1: setting name, 2: number of course and exam overrides */
					_n( '%1$s: locked for all courses; %2$d own value rests.', '%1$s: locked for all courses; %2$d own values rest.', $count, 'course-captain' ),
					EffectiveSettings::labels()[ $key ] ?? $key,
					$count
				),
			);
		}
		foreach ( array_diff_key( $current, $wanted ) as $key => $lock ) {
			$count            = EffectiveSettings::count_overrides( (string) $key );
			$out['changed'][] = array(
				'item'     => (string) $key,
				'before'   => $lock['reason'],
				'after'    => null,
				'risks'    => array(),
				'affected' => $count,
				'summary'  => sprintf(
					/* translators: 1: setting name, 2: number of course and exam overrides */
					_n( '%1$s: lock removed; %2$d own value applies again.', '%1$s: lock removed; %2$d own values apply again.', $count, 'course-captain' ),
					EffectiveSettings::labels()[ (string) $key ] ?? (string) $key,
					$count
				),
			);
		}
		return $out;
	}

	/**
	 * Apply the plan.
	 *
	 * @param Actor                $actor     Actor.
	 * @param array<string, mixed> $section   File section.
	 * @param array                $confirmed Confirmed items (`*` = all).
	 * @phpstan-param list<string> $confirmed
	 * @return list<string> Applied items.
	 */
	public function apply( Actor $actor, array $section, array $confirmed ): array {
		$plan = $this->plan( $actor, $section, $confirmed );
		if ( array() !== $plan['errors'] ) {
			return array();
		}
		$done = array();
		foreach ( $plan['changed'] as $row ) {
			try {
				if ( null === $row['after'] ) {
					EffectiveSettings::unlock( $actor, (string) $row['item'] );
				} else {
					EffectiveSettings::lock( $actor, (string) $row['item'], (string) $row['after'] );
				}
				$done[] = (string) $row['item'];
			} catch ( CourseException $e ) {
				// Lock preflight (§1.6, K-4) refused this key; the others are applied.
				continue;
			}
		}
		return $done;
	}
}
