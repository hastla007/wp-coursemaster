#!/usr/bin/env bash
# R1-18 live E2E smoke (R0-11 Flow 2, Gate B-4 part): fresh WordPress from
# vendor/roots/wordpress-no-content, the plugin from this working tree,
# `cc-fixture-neutral-v1` loaded, served by `php -S`, driven by Chromium
# (Playwright): learner logs in → My learning → course → lesson → quiz
# answer → exam start/answer/reload/submit → certificate verified as guest.
#
# Usage: tools/qa/e2e/live-smoke.sh [db_name]
# The site uses the table prefix `ccape2e_` inside the given test database
# (default $WP_TESTS_DB_NAME or ccap_test); only tables with that prefix are
# created and dropped. The database name must contain "test" (D-01).
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../../.." && pwd)
# shellcheck source=../../release/php-min.sh
source "$ROOT/tools/release/php-min.sh"; ccap_php_min || exit 1
DB=${1:-${WP_TESTS_DB_NAME:-ccap_test}}
[[ "$DB" == *test* ]] || { echo "Refusing: database name must contain 'test'"; exit 2; }
PREFIX=ccape2e_
PORT=${CCAP_E2E_PORT:-8765}
URL="http://127.0.0.1:$PORT"
CORE=${WP_CORE_DIR:-$ROOT/vendor/roots/wordpress-no-content}
WP="$ROOT/vendor/bin/wp --allow-root"
SITE=$(mktemp -d /tmp/ccap-e2e-XXXX)
export CCAP_E2E_PASS="e2e-$(head -c 9 /dev/urandom | od -An -tx1 | tr -d ' \n')"
HOSTPORT=${WP_TESTS_DB_HOST:-localhost}; DBHOST=${HOSTPORT%%:*}; DBPORT=3306; [[ "$HOSTPORT" == *:* ]] && DBPORT=${HOSTPORT##*:}
MYSQL=(mysql -u"${WP_TESTS_DB_USER:-ccap}" -p"${WP_TESTS_DB_PASSWORD:-ccap}" -h"$DBHOST" -P"$DBPORT" -N -B "$DB")
drop_prefix() {
	local tables
	tables=$("${MYSQL[@]}" -e "SELECT GROUP_CONCAT(CONCAT('\`', table_name, '\`')) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE '${PREFIX//_/\\_}%'" 2>/dev/null)
	if [[ -n "$tables" && "$tables" != "NULL" ]]; then "${MYSQL[@]}" -e "SET FOREIGN_KEY_CHECKS = 0; DROP TABLE $tables; SET FOREIGN_KEY_CHECKS = 1" 2>/dev/null; fi
}
SERVER=
cleanup() { [[ -n "$SERVER" ]] && kill "$SERVER" 2>/dev/null; drop_prefix; rm -rf "$SITE"; }
trap cleanup EXIT

drop_prefix
cp -r "$CORE"/. "$SITE"/
mkdir -p "$SITE/wp-content/plugins"
ln -s "$ROOT" "$SITE/wp-content/plugins/course-captain"
$WP --path="$SITE" config create --dbname="$DB" --dbuser="${WP_TESTS_DB_USER:-ccap}" --dbpass="${WP_TESTS_DB_PASSWORD:-ccap}" --dbhost="$HOSTPORT" --dbprefix="$PREFIX" --skip-check --quiet
$WP --path="$SITE" core install --url="$URL" --title="CC E2E" --admin_user=cc_e2e_admin --admin_password="$CCAP_E2E_PASS" --admin_email=admin@fixture.test --skip-email --quiet
$WP --path="$SITE" plugin activate course-captain --quiet
SETUP=$(php "$ROOT/tools/qa/e2e/live-setup.php" "$SITE" setup)
echo "fixture: $SETUP"
php -S "127.0.0.1:$PORT" -t "$SITE" >"$SITE/server.log" 2>&1 &
SERVER=$!
for _ in $(seq 1 50); do curl -s -o /dev/null "$URL/wp-login.php" && break; sleep 0.2; done
CCAP_E2E_URL="$URL" CCAP_E2E_SETUP="$SETUP" CCAP_E2E_DISPATCH="php $ROOT/tools/qa/e2e/live-setup.php $SITE dispatch" \
	node "$ROOT/tools/qa/e2e/live-smoke.js"
if grep -qE "PHP (Fatal|Warning|Notice|Deprecated)" "$SITE/server.log"; then echo "FAIL: PHP messages in the server log"; grep -E "PHP (Fatal|Warning|Notice|Deprecated)" "$SITE/server.log" | head -20; exit 1; fi
echo "live-smoke: OK"
