#!/usr/bin/env bash
# RC-047 / R0-35 §5.6 / R0-20 §1.2: below the hard minimum (PHP 8.3, WordPress 7.0)
# the release ZIP must install without a fatal error, refuse the activation
# without writing anything and show an admin notice instead of breaking the site.
#
# Usage: tools/release/old-runtime-test.sh <zip> <wp_core_dir> [db_name]
#   WP_CLI         command to run WP-CLI (default: wp)
#   CCAP_SIMULATE_MIN_PHP  local self-test only: raise COURSE_CAPTAIN_MIN_PHP in the
#                  installed copy so the "too old" path runs on a current PHP.
set -euo pipefail
ZIP=$(realpath "$1"); CORE=$(realpath "$2"); DB=${3:-ccap_old_runtime}
WP="${WP_CLI:-wp} --allow-root"
SITE=$(mktemp -d /tmp/ccap-oldrt-XXXX)
HOSTPORT=${WP_TESTS_DB_HOST:-localhost}; DBHOST=${HOSTPORT%%:*}; DBPORT=3306; [[ "$HOSTPORT" == *:* ]] && DBPORT=${HOSTPORT##*:}
MYSQL=(mysql -u"${WP_TESTS_DB_USER:-ccap}" -p"${WP_TESTS_DB_PASSWORD:-ccap}" -h"$DBHOST" -P"$DBPORT")
SQL() { "${MYSQL[@]}" -N -B "$DB" -e "$1" 2>/dev/null; }
fail() { echo "FAIL: $1"; exit 1; }
php_errors() { grep -qE "PHP (Parse error|Fatal error|Warning|Notice|Deprecated)|^(Parse error|Fatal error|Warning|Notice|Deprecated):"; }
trap '[ -n "${CCAP_KEEP_SITE:-}" ] && echo "site kept: $SITE" || rm -rf "$SITE"' EXIT

cp -r "$CORE"/. "$SITE"/
"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\`" 2>/dev/null
$WP --path="$SITE" config create --dbname="$DB" --dbuser="${WP_TESTS_DB_USER:-ccap}" --dbpass="${WP_TESTS_DB_PASSWORD:-ccap}" --dbhost="$HOSTPORT" --skip-check --quiet
$WP --path="$SITE" config set WP_DEBUG true --raw --quiet
$WP --path="$SITE" config set WP_HTTP_BLOCK_EXTERNAL true --raw --quiet # no update checks against WordPress.org
$WP --path="$SITE" core install --url=http://old-runtime.test --title=OldRuntime --admin_user=admin --admin_password=old-runtime-pass --admin_email=admin@old-runtime.test --skip-email --quiet
echo "WordPress $($WP --path="$SITE" core version), PHP $(php -r 'echo PHP_VERSION;')"

# 1. Install without activation: unpacking must not parse modern code.
OUT=$($WP --path="$SITE" plugin install "$ZIP" 2>&1) || fail "install failed: $OUT"
if [ -n "${CCAP_SIMULATE_MIN_PHP:-}" ]; then
	sed -i "s/define( 'COURSE_CAPTAIN_MIN_PHP', '[0-9.]*' );/define( 'COURSE_CAPTAIN_MIN_PHP', '${CCAP_SIMULATE_MIN_PHP}' );/" "$SITE/wp-content/plugins/course-captain/course-captain.php"
	echo "simulated minimum PHP: ${CCAP_SIMULATE_MIN_PHP}"
fi

# 2. Activation is refused, without PHP errors and without writing anything.
set +e
OUT=$($WP --path="$SITE" plugin activate course-captain 2>&1); RC=$?
set -e
echo "$OUT"
echo "$OUT" | php_errors && fail "PHP error during activation"
[ "$RC" -ne 0 ] || echo "$OUT" | grep -qi "requires" || fail "activation was not refused"
$WP --path="$SITE" plugin is-active course-captain && fail "plugin is active"
OPTS=$(SQL "SELECT COUNT(*) FROM wp_options WHERE option_name LIKE 'course\\_captain\\_%' OR option_name LIKE 'ccap\\_%'")
TABLES=$(SQL "SHOW TABLES LIKE 'wp\\_ccap\\_%'" | wc -l)
CRON=$(SQL "SELECT option_value FROM wp_options WHERE option_name='cron'" | grep -c "ccap_" || true)
echo "own options: $OPTS, own tables: $TABLES, own cron events: $CRON"
[ "$OPTS" = "0" ] && [ "$TABLES" = "0" ] && [ "$CRON" = "0" ] || fail "data written although the activation was refused"

# 3. Files present while the plugin is marked active (runtime downgraded after activation):
#    the site keeps working and the admin notice explains why.
SQL "UPDATE wp_options SET option_value='a:1:{i:0;s:33:\"course-captain/course-captain.php\";}' WHERE option_name='active_plugins'"
OUT=$($WP --path="$SITE" plugin list --status=active --field=name --skip-update-check 2>&1) || fail "site broken with the plugin forced active: $OUT"; echo "$OUT"
echo "$OUT" | php_errors && fail "PHP error with the plugin forced active"
NOTICE=$($WP --path="$SITE" --user=admin eval 'do_action( "admin_notices" );' 2>&1 || true)
if echo "$NOTICE" | grep -q "is not a registered wp command"; then
	echo "admin notice: skipped (WP-CLI without eval)"
else
	echo "$NOTICE" | php_errors && fail "PHP error in admin_notices"
	echo "$NOTICE" | grep -q "requires" || fail "no admin notice about the requirements: $NOTICE"
	echo "admin notice: present"
fi
TABLES=$(SQL "SHOW TABLES LIKE 'wp\\_ccap\\_%'" | wc -l)
[ "$TABLES" = "0" ] || fail "tables created on an unsupported runtime"
echo "old-runtime-test: OK"
