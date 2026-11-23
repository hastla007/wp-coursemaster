<?php
// Semgrep rule tests for cc-rules.yml (run: semgrep --test --metrics=off tools/semgrep/).
// Not shipped, not loaded; only parsed by Semgrep.

// ruleid: cc-wpdb-interpolated-sql
$wpdb->get_results( "SELECT * FROM {$wpdb->prefix}ccap_grant WHERE user_id = $user" );
// ruleid: cc-wpdb-interpolated-sql
$wpdb->query( 'DELETE FROM ' . $table . ' WHERE id = ' . $id );
// ok: cc-wpdb-interpolated-sql
$wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM t WHERE id = %d', $id ) );
// ok: cc-wpdb-interpolated-sql
$wpdb->get_var( 'SELECT VERSION()' );

// ruleid: cc-superglobal-unsanitized
$a = $_GET['course'];
// ok: cc-superglobal-unsanitized
$b = isset( $_GET['course'] ) ? absint( $_GET['course'] ) : 0;
// ok: cc-superglobal-unsanitized
$c = sanitize_text_field( wp_unslash( $_POST['name'] ) );

// ruleid: cc-rest-route-public
register_rest_route( 'ns/v1', '/x', array( 'methods' => 'GET', 'permission_callback' => '__return_true' ) );
// ok: cc-rest-route-public
register_rest_route( 'ns/v1', '/y', array( 'methods' => 'GET', 'permission_callback' => array( $this, 'can' ) ) );

// ruleid: cc-remote-request-unsafe
wp_remote_get( $url );
// ok: cc-remote-request-unsafe
wp_safe_remote_get( $url );
// ok: cc-remote-request-unsafe
wp_remote_get( $url, array( 'reject_unsafe_urls' => true ) );

// ruleid: cc-zip-extract-to
$zip->extractTo( $dir );

// ruleid: cc-unserialize
unserialize( $raw );
// ok: cc-unserialize
unserialize( $raw, array( 'allowed_classes' => false ) );

// ruleid: cc-weak-token-random
$token = md5( uniqid() );
// ok: cc-weak-token-random
$token = bin2hex( random_bytes( 16 ) );

// ruleid: cc-eval-shell
shell_exec( $cmd );

// ruleid: cc-echo-unescaped-request, cc-superglobal-unsanitized
echo $_GET['q'];
