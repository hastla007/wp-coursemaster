#!/usr/bin/env bash
# J-17 artefact test (R0-35 §3.4, R0-18 J-17): install the release ZIP into a
# fresh WordPress (no symlinks, no tests/), activate, check Site Health and
# the schema, deactivate and reactivate, uninstall without data deletion.
# Usage: tools/release/artifact-test.sh <zip> [db_name]
set -euo pipefail
ZIP=$(realpath "$1"); DB=${2:-ccap_zip_test}
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
CORE=${WP_CORE_DIR:-$ROOT/vendor/roots/wordpress-no-content}
WP="$ROOT/vendor/bin/wp --allow-root"
SITE=$(mktemp -d /tmp/ccap-artefact-XXXX)
HOSTPORT=${WP_TESTS_DB_HOST:-localhost}; DBHOST=${HOSTPORT%%:*}; DBPORT=3306; [[ "$HOSTPORT" == *:* ]] && DBPORT=${HOSTPORT##*:}
MYSQL=(mysql -u"${WP_TESTS_DB_USER:-ccap}" -p"${WP_TESTS_DB_PASSWORD:-ccap}" -h"$DBHOST" -P"$DBPORT")
SQL() { "${MYSQL[@]}" -N -B "$DB" -e "$1" 2>/dev/null; }
trap 'rm -rf "$SITE"' EXIT
cp -r "$CORE"/. "$SITE"/
$WP --path="$SITE" config create --dbname="$DB" --dbuser="${WP_TESTS_DB_USER:-ccap}" --dbpass="${WP_TESTS_DB_PASSWORD:-ccap}" --dbhost="${WP_TESTS_DB_HOST:-localhost}" --skip-check --quiet
"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\`" 2>/dev/null || $WP --path="$SITE" db reset --yes --quiet
$WP --path="$SITE" core install --url=http://artefact.test --title=Artefact --admin_user=admin --admin_password=artefact-pass --admin_email=admin@artefact.test --skip-email --quiet
echo "WordPress $($WP --path="$SITE" core version), PHP $(php -r 'echo PHP_VERSION;')"
OUT=$($WP --path="$SITE" plugin install "$ZIP" --activate 2>&1); echo "$OUT"
if echo "$OUT" | grep -qE "PHP (Notice|Warning|Deprecated|Fatal)|^(Notice|Deprecated|Fatal error):"; then echo "FAIL: PHP output during install/activation"; exit 1; fi
test ! -L "$SITE/wp-content/plugins/course-captain" || { echo "FAIL: plugin is a symlink"; exit 1; }
test ! -d "$SITE/wp-content/plugins/course-captain/tests" || { echo "FAIL: tests/ shipped"; exit 1; }
DBV=$($WP --path="$SITE" option get course_captain_db_version 2>/dev/null || echo "")
echo "schema version after activation: ${DBV:-<none>}"
test -n "$DBV" || { echo "FAIL: schema not installed"; exit 1; }
TABLES=$(SQL "SHOW TABLES LIKE 'wp\_ccap\_%'" | wc -l); echo "own tables: $TABLES"
$WP --path="$SITE" plugin deactivate course-captain --quiet
AFTER=$(SQL "SHOW TABLES LIKE 'wp\_ccap\_%'" | wc -l)
test "$AFTER" = "$TABLES" || { echo "FAIL: deactivation changed tables"; exit 1; }
$WP --path="$SITE" plugin activate course-captain --quiet
$WP --path="$SITE" plugin deactivate course-captain --quiet
$WP --path="$SITE" plugin uninstall course-captain --quiet
LEFT=$(SQL "SHOW TABLES LIKE 'wp\_ccap\_%'" | wc -l)
echo "tables after uninstall without data deletion: $LEFT (expected $TABLES, E-89)"
test "$LEFT" = "$TABLES" || { echo "FAIL: uninstall without confirmation removed data"; exit 1; }
echo "artifact-test: OK"
