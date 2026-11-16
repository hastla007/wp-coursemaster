<?php
/**
 * Catalog of foreign plugin stubs (R1-30). All foreign names are "(v)":
 * model knowledge, to be verified against the current plugin versions.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Support\Compat;

use CourseCaptain\Policy\Relations;

/**
 * Factories; each returns an inactive stub. The tests activate it and
 * `ForeignPlugin::deactivate_all()` cleans up in tear down.
 */
final class ForeignPlugins {

	/**
	 * Security plugins with an own login limit (KOMP-057, list after LB-K-06).
	 *
	 * @return array<string, ForeignPlugin> Expected reason slug => stub.
	 */
	public static function security(): array {
		return array(
			'wordfence'              => new ForeignPlugin( 'wordfence', array( 'constant:WORDFENCE_VERSION' => '8.0.0' ) ),
			'solid-security'         => new ForeignPlugin( 'better-wp-security', array( 'class:ITSEC_Core' => true ) ),
			'limit-login-attempts'   => new ForeignPlugin( 'limit-login-attempts-reloaded', array( 'constant:LLA_PLUGIN_URL' => 'https://example.org/llar/' ) ),
			'all-in-one-wp-security' => new ForeignPlugin( 'all-in-one-wp-security-and-firewall', array( 'constant:AIO_WP_SECURITY_VERSION' => '5.0.0' ) ),
			'loginizer'              => new ForeignPlugin( 'loginizer', array( 'constant:LOGINIZER_VERSION' => '1.9.0' ) ),
		);
	}

	/**
	 * Jetpack (XML-RPC needed for the app/Jetpack connection, KOMP-058).
	 *
	 * @return ForeignPlugin
	 */
	public static function jetpack(): ForeignPlugin {
		return new ForeignPlugin( 'jetpack', array( 'constant:JETPACK__VERSION' => '14.0' ) );
	}

	/**
	 * Security plugin rule that switches XML-RPC off (KOMP-058).
	 *
	 * @return ForeignPlugin
	 */
	public static function xmlrpc_blocker(): ForeignPlugin {
		return new ForeignPlugin(
			'xmlrpc-blocker',
			array(),
			static function (): void {
				add_filter( 'xmlrpc_enabled', '__return_false' );
			},
			static function (): void {
				remove_filter( 'xmlrpc_enabled', '__return_false' );
			}
		);
	}

	/**
	 * Security/header plugin: sets security headers itself (KOMP-059, E-307).
	 *
	 * @return ForeignPlugin
	 */
	public static function header_plugin(): ForeignPlugin {
		$headers = static function ( $headers ) {
			$headers                           = (array) $headers;
			$headers['X-Frame-Options']        = 'DENY';
			$headers['x-content-type-options'] = 'nosniff';
			return $headers;
		};
		$rest    = static function ( $response ) {
			if ( $response instanceof \WP_HTTP_Response ) {
				$response->header( 'Permissions-Policy', 'camera=(self)' );
			}
			return $response;
		};
		return new ForeignPlugin(
			'header-plugin',
			array(),
			static function () use ( $headers, $rest ): void {
				add_filter( 'wp_headers', $headers );
				add_filter( 'rest_post_dispatch', $rest, 10 );
			},
			static function () use ( $headers, $rest ): void {
				remove_filter( 'wp_headers', $headers );
				remove_filter( 'rest_post_dispatch', $rest, 10 );
			}
		);
	}

	/**
	 * Generic "disable REST API for guests" snippet (KOMP-052); with
	 * `$solid` the Solid Security markers as well (KOMP-051).
	 *
	 * @param bool $solid Solid Security variant.
	 * @return ForeignPlugin
	 */
	public static function rest_guest_block( bool $solid = false ): ForeignPlugin {
		$filter = static function ( $result ) {
			if ( null !== $result || is_user_logged_in() ) {
				return $result;
			}
			return new \WP_Error( 'rest_not_logged_in', 'Only logged-in users may use the REST API.', array( 'status' => 401 ) );
		};
		return new ForeignPlugin(
			$solid ? 'better-wp-security' : 'disable-rest-snippet',
			$solid ? array( 'class:ITSEC_Core' => true ) : array(),
			static function () use ( $filter ): void {
				add_filter( 'rest_authentication_errors', $filter, 10 );
			},
			static function () use ( $filter ): void {
				remove_filter( 'rest_authentication_errors', $filter, 10 );
			}
		);
	}

	/**
	 * SEO plugins (KOMP-060…063).
	 *
	 * @return array<string, ForeignPlugin> Expected mode => stub.
	 */
	public static function seo(): array {
		return array(
			'yoast'             => new ForeignPlugin( 'wordpress-seo', array( 'constant:WPSEO_VERSION' => '25.0' ) ),
			'rank_math'         => new ForeignPlugin( 'seo-by-rank-math', array( 'class:RankMath' => true ) ),
			'aioseo'            => new ForeignPlugin( 'all-in-one-seo-pack', array( 'constant:AIOSEO_VERSION' => '4.8.0' ) ),
			'seopress'          => new ForeignPlugin( 'wp-seopress', array( 'constant:SEOPRESS_VERSION' => '8.0' ) ),
			'the-seo-framework' => new ForeignPlugin( 'autodescription', array( 'constant:THE_SEO_FRAMEWORK_VERSION' => '5.1.0' ) ),
		);
	}

	/**
	 * Mail plugin with or without mail log (KOMP-056).
	 *
	 * @param string $slug wp-mail-smtp|fluent-smtp|post-smtp|wp-mail-logging.
	 * @param bool   $log  Log switched on.
	 * @return ForeignPlugin
	 */
	public static function mail( string $slug, bool $log ): ForeignPlugin {
		switch ( $slug ) {
			case 'wp-mail-smtp':
				return new ForeignPlugin(
					$slug,
					array( 'constant:WPMS_PLUGIN_VER' => '4.0.0' ),
					static function () use ( $log ): void {
						update_option( 'wp_mail_smtp', array( 'logs' => array( 'enabled' => $log ) ) );
					},
					static function (): void {
						delete_option( 'wp_mail_smtp' );
					}
				);
			case 'fluent-smtp':
				return new ForeignPlugin(
					$slug,
					array( 'constant:FLUENTMAIL_PLUGIN_VERSION' => '2.2.0' ),
					static function () use ( $log ): void {
						update_option( 'fluentmail-settings', array( 'misc' => array( 'log_emails' => $log ? 'yes' : 'no' ) ) );
					},
					static function (): void {
						delete_option( 'fluentmail-settings' );
					}
				);
			case 'post-smtp':
				return new ForeignPlugin( $slug, array( 'constant:POST_SMTP_VER' => '3.0.0' ) );
			default:
				return new ForeignPlugin( $slug, array( 'class:No3x\WPML\WPML_Plugin' => true ) );
		}
	}

	/**
	 * WooCommerce: version marker and the `shop_manager` role (KOMP-101/102).
	 *
	 * @return ForeignPlugin
	 */
	public static function woocommerce(): ForeignPlugin {
		return new ForeignPlugin(
			'woocommerce',
			array(
				'constant:WC_VERSION' => '9.8.0',
				'function:WC'         => true,
			),
			static function (): void {
				if ( null === get_role( 'shop_manager' ) ) {
					add_role(
						'shop_manager',
						'Shop manager',
						array(
							'read'               => true,
							'edit_posts'         => true,
							'manage_woocommerce' => true,
						)
					);
				}
			},
			static function (): void {
				remove_role( 'shop_manager' );
			}
		);
	}

	/**
	 * Behaviour of the Woo admin lock (v): users without `edit_posts`,
	 * `manage_woocommerce` or `view_admin_dashboard` are sent to "My account".
	 *
	 * @param int $user User.
	 * @return bool Whether the shop would block wp-admin.
	 */
	public static function woo_blocks_admin( int $user ): bool {
		$allowed = false;
		foreach ( array( 'edit_posts', 'manage_woocommerce', 'view_admin_dashboard' ) as $cap ) {
			$allowed = $allowed || user_can( $user, $cap );
		}
		return (bool) apply_filters( 'woocommerce_prevent_admin_access', ! $allowed ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Foreign filter emulated.
	}

	/**
	 * Polylang with all public post types selected for translation (KOMP-091).
	 *
	 * @return ForeignPlugin
	 */
	public static function polylang(): ForeignPlugin {
		$translated = static function ( string $type ): bool {
			$types = array_merge( array( 'post', 'page' ), Relations::POST_TYPES );
			$types = (array) apply_filters( 'pll_get_post_types', array_combine( $types, $types ), false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Foreign filter emulated.
			return isset( $types[ $type ] );
		};
		return new ForeignPlugin( 'polylang', array( 'function:pll_is_translated_post_type' => $translated ) );
	}

	/**
	 * Elementor with the "Element Caching" experiment state (KOMP-073).
	 *
	 * @param string|null $state active|inactive|default; null = option missing.
	 * @return ForeignPlugin
	 */
	public static function elementor( ?string $state ): ForeignPlugin {
		return new ForeignPlugin(
			'elementor',
			array( 'constant:ELEMENTOR_VERSION' => '3.30.0' ),
			static function () use ( $state ): void {
				if ( null !== $state ) {
					update_option( 'elementor_experiment-e_element_cache', $state );
				}
			},
			static function (): void {
				delete_option( 'elementor_experiment-e_element_cache' );
			}
		);
	}

	/**
	 * `DISABLE_WP_CRON` (KOMP-120).
	 *
	 * @return ForeignPlugin
	 */
	public static function wp_cron_disabled(): ForeignPlugin {
		return new ForeignPlugin( 'wp-config', array( 'constant:DISABLE_WP_CRON' => true ) );
	}

	/**
	 * WP-Cron on (hides the constant the WP test suite defines).
	 *
	 * @return ForeignPlugin
	 */
	public static function wp_cron_enabled(): ForeignPlugin {
		return new ForeignPlugin( 'wp-config-cron-on', array( 'constant:DISABLE_WP_CRON' => false ) );
	}

	/**
	 * Tutor LMS and LifterLMS identifiers (KOMP-111, KOMP-112, KOMP-134).
	 *
	 * @return ForeignPlugin
	 */
	public static function lms(): ForeignPlugin {
		$types = array(
			'courses'    => 'courses',
			'lesson'     => 'lesson',
			'tutor_quiz' => 'tutor_quiz',
			'course'     => 'course',
			'llms_quiz'  => 'llms_quiz',
		);
		$roles = array( 'tutor_instructor', 'instructor', 'instructors_assistant', 'lms_manager', 'student' );
		$codes = array( 'tutor_dashboard', 'lifterlms_courses' );
		$rest  = static function (): void {
			foreach ( array( 'tutor/v1', 'llms/v1' ) as $namespace ) {
				register_rest_route(
					$namespace,
					'/courses',
					array(
						'methods'             => 'GET',
						'callback'            => '__return_empty_array',
						'permission_callback' => '__return_true',
					)
				);
			}
		};
		return new ForeignPlugin(
			'lms-coexistence',
			array(),
			static function () use ( $types, $roles, $codes, $rest ): void {
				foreach ( $types as $type => $slug ) {
					register_post_type(
						$type,
						array(
							'public'  => true,
							'label'   => 'Foreign ' . $type,
							'rewrite' => array( 'slug' => $slug ),
						)
					);
				}
				foreach ( $roles as $role ) {
					add_role( $role, 'Foreign ' . $role, array( 'read' => true ) );
				}
				foreach ( $codes as $code ) {
					add_shortcode( $code, '__return_empty_string' );
				}
				add_action( 'rest_api_init', $rest );
			},
			static function () use ( $types, $roles, $codes, $rest ): void {
				foreach ( array_keys( $types ) as $type ) {
					unregister_post_type( $type );
				}
				foreach ( $roles as $role ) {
					remove_role( $role );
				}
				foreach ( $codes as $code ) {
					remove_shortcode( $code );
				}
				remove_action( 'rest_api_init', $rest );
			}
		);
	}
}
