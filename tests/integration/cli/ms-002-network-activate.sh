#!/usr/bin/env bash
# MS-002 (R0-25 §4.2), KOMP-001: `wp plugin activate <plugin> --network` must exit != 0
# and leave no trace on any site (same end state as MS-001).
#
# Precondition: the multisite test installation exists, i.e. run
# `composer test:integration-ms` first (same database, prefix wptests_).
# Database settings come from WP_TESTS_DB_* like tests/integration/wp-tests-config.php.
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
slug="$(basename "$root")"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

ln -s "$root/vendor/roots/wordpress-no-content" "$work/wp"
cat > "$work/wp-config.php" <<PHP
<?php
define( 'DB_NAME', '${WP_TESTS_DB_NAME:-ccap_test}' );
define( 'DB_USER', '${WP_TESTS_DB_USER:-ccap}' );
define( 'DB_PASSWORD', '${WP_TESTS_DB_PASSWORD:-ccap}' );
define( 'DB_HOST', '${WP_TESTS_DB_HOST:-localhost}' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
\$table_prefix = 'wptests_';
define( 'WP_DEBUG', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', false );
define( 'DOMAIN_CURRENT_SITE', 'example.org' );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );
define( 'WP_PLUGIN_DIR', '$(dirname "$root")' );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp/' );
}
require_once ABSPATH . 'wp-settings.php';
PHP

case "${WP_TESTS_DB_NAME:-ccap_test}" in
	*test*) ;;
	*) echo "Refusing to run: DB name must contain 'test' (G-09)." >&2; exit 1 ;;
esac

wp() { "$root/vendor/bin/wp" --path="$work/wp" --url=example.org --allow-root "$@"; }

if ! wp site list --format=count >/dev/null 2>&1; then
	echo "Precondition missing: run 'composer test:integration-ms' first (multisite test install)." >&2
	exit 2
fi

# A second site, so that "no trace on any site" covers more than the main site.
wp site create --slug=ms002 --quiet >/dev/null 2>&1 || true

# The booted plugin copy of the PHPUnit bootstrap writes this option on `init`; it is no activation trace.
for url in $(wp site list --field=url); do
	wp option delete course_captain_rewrite_version --url="$url" >/dev/null 2>&1 || true
done

set +e
output="$(wp plugin activate "$slug" --network 2>&1)"
status=$?
set -e

fail=0
if [ "$status" -eq 0 ]; then
	echo "FAIL: network activation exited with 0" >&2; fail=1
fi
if ! grep -q "cannot be network activated" <<<"$output"; then
	echo "FAIL: message missing; output was: $output" >&2; fail=1
fi
if wp site option get active_sitewide_plugins --format=json | grep -q "$slug/course-captain.php"; then
	echo "FAIL: plugin is listed in active_sitewide_plugins" >&2; fail=1
fi

for url in $(wp site list --field=url); do
	options="$(wp option list --search='course_captain_*' --field=option_name --url="$url")"
	options="$options$(wp option list --search='ccap_*' --field=option_name --url="$url")"
	cron="$(wp option get cron --format=json --url="$url" | grep -o '"ccap_[a-z_]*"' || true)"
	roles_option="$(wp option list --search='*user_roles' --field=option_name --url="$url" | head -n1)"
	caps="$(wp option get "$roles_option" --format=json --url="$url" | grep -o '"ccap_[a-z_]*"' || true)"
	if [ -n "$options$cron$caps" ]; then
		echo "FAIL: traces on $url: options=[$options] cron=[$cron] caps=[$caps]" >&2; fail=1
	fi
done

if [ "$fail" -ne 0 ]; then
	exit 1
fi
echo "MS-002: OK (exit $status, no traces on $(wp site list --format=count) sites)"
