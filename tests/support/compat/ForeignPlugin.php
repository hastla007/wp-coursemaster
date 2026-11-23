<?php
/**
 * Stub of a foreign plugin for the compatibility suite (R1-30, R0-20 §2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Support\Compat;

use CourseCaptain\Ops\Compatibility;

/**
 * Emulates only the public surface the detection reads: markers
 * (constants, classes, functions) through `Compatibility::$stubs`, plus
 * hooks, options, roles or registrations set up by `$setup` and removed by
 * `$teardown`. No code of the real plugin is used (E-01): the stubs carry
 * names only, and every foreign name is marked "(v)" in the evidence
 * (model knowledge, verify against the current plugin version).
 */
final class ForeignPlugin {

	/**
	 * Active stubs (for `deactivate_all()`).
	 *
	 * @var array<string, self>
	 */
	private static array $active = array();

	/**
	 * Constructor.
	 *
	 * @param string               $slug     Plugin slug.
	 * @param array<string, mixed> $markers  Marker => value (see `Compatibility::$stubs`).
	 * @param \Closure|null        $setup    Registers hooks, options, roles.
	 * @param \Closure|null        $teardown Removes them again.
	 */
	public function __construct(
		public readonly string $slug,
		private array $markers = array(),
		private ?\Closure $setup = null,
		private ?\Closure $teardown = null
	) {
	}

	/**
	 * "Activate" the stub; fires `activated_plugin` like WordPress.
	 *
	 * @return self
	 */
	public function activate(): self {
		foreach ( $this->markers as $marker => $value ) {
			Compatibility::$stubs[ $marker ] = $value;
		}
		if ( null !== $this->setup ) {
			( $this->setup )();
		}
		self::$active[ $this->slug ] = $this;
		do_action( 'activated_plugin', $this->slug . '/' . $this->slug . '.php', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook emulated.
		return $this;
	}

	/**
	 * "Deactivate" the stub; fires `deactivated_plugin`.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		foreach ( array_keys( $this->markers ) as $marker ) {
			unset( Compatibility::$stubs[ $marker ] );
		}
		if ( null !== $this->teardown ) {
			( $this->teardown )();
		}
		unset( self::$active[ $this->slug ] );
		do_action( 'deactivated_plugin', $this->slug . '/' . $this->slug . '.php', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook emulated.
	}

	/**
	 * Deactivate every stub that is still active (tear down).
	 *
	 * @return void
	 */
	public static function deactivate_all(): void {
		foreach ( array_reverse( self::$active ) as $plugin ) {
			$plugin->deactivate();
		}
		self::$active         = array();
		Compatibility::$stubs = array();
	}
}
