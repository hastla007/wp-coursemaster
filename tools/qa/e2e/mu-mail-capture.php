<?php
/**
 * R1-18 live E2E (test site only, copied to wp-content/mu-plugins by live-flows.sh):
 * mail sink. Every wp_mail() is written as one JSON line to
 * wp-content/ccap-e2e-mail.jsonl and not sent (R0-26 "Mail-Sink").
 *
 * @package CourseCaptain
 */

add_filter(
	'pre_wp_mail',
	static function ( $result, $atts ) {
		$line = wp_json_encode(
			array(
				'to'      => $atts['to'],
				'subject' => $atts['subject'],
				'message' => $atts['message'],
			)
		);
		file_put_contents( WP_CONTENT_DIR . '/ccap-e2e-mail.jsonl', $line . "\n", FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test sink.
		return true;
	},
	10,
	2
);
