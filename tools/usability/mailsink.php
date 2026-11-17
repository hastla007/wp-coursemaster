<?php
/**
 * Plugin Name: Course Captain Nutzertest – Mail-Sink (NUR TEST-SITE)
 * Description: Fängt alle Mails ab und schreibt sie nach wp-content/ccap-ux-mails/mails.log (R0-26 §3.3). Wird von tools/usability/setup-site.sh installiert.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( defined( 'CCAP_UX_TESTSITE' ) && true === CCAP_UX_TESTSITE ) {
	add_filter(
		'pre_wp_mail',
		static function ( $value, $atts ) {
			unset( $value );
			$dir = WP_CONTENT_DIR . '/ccap-ux-mails';
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
				file_put_contents( $dir . '/.htaccess', "Require all denied\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test site only.
				file_put_contents( $dir . '/index.php', "<?php\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test site only.
			}
			$atts  = (array) $atts;
			$entry = array(
				'time'    => gmdate( 'c' ),
				'to'      => $atts['to'] ?? '',
				'subject' => $atts['subject'] ?? '',
				'message' => $atts['message'] ?? '',
			);
			file_put_contents( $dir . '/mails.log', wp_json_encode( $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test site only.
			return true;
		},
		10,
		2
	);
}
