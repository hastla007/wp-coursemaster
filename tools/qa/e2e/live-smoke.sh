#!/usr/bin/env bash
# R1-18 live E2E smoke (R0-11 Flow 2, Gate B-4 part): fresh WordPress from
# vendor/roots/wordpress-no-content, the plugin from this working tree,
# `cc-fixture-neutral-v1` loaded, served by `php -S`, driven by Chromium
# (Playwright): learner logs in → My learning → course → lesson → quiz
# answer → exam start/answer/reload/submit → certificate verified as guest;
# then the exam room cases GM-050/GM-051 (exam-room.js, learner U-L2).
#
# Usage: tools/qa/e2e/live-smoke.sh [db_name]
# The site uses the table prefix `ccape2e_` inside the given test database
# (default $WP_TESTS_DB_NAME or ccap_test); only tables with that prefix are
# created and dropped. The database name must contain "test" (D-01).
# The full reference run (Flows 1–3, mobile, benchmark, A11Y) is live-flows.sh.
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../../.." && pwd)
# shellcheck source=../../release/php-min.sh
source "$ROOT/tools/release/php-min.sh"; ccap_php_min || exit 1
# shellcheck source=lib-site.sh
source "$ROOT/tools/qa/e2e/lib-site.sh"
DB=${1:-${WP_TESTS_DB_NAME:-ccap_test}}
export CCAP_E2E_PASS="e2e-$(head -c 9 /dev/urandom | od -An -tx1 | tr -d ' \n')"
e2e_init
trap e2e_cleanup EXIT

e2e_site_create
SETUP=$(php "$ROOT/tools/qa/e2e/live-setup.php" "$SITE" setup)
echo "fixture: $SETUP"
e2e_server_start
CCAP_E2E_URL="$URL" CCAP_E2E_SETUP="$SETUP" CCAP_E2E_DISPATCH="php $ROOT/tools/qa/e2e/live-setup.php $SITE dispatch" \
	node "$ROOT/tools/qa/e2e/live-smoke.js"
# Exam room in the browser: offline buffer (GM-050), abort dialog (GM-051), open questions without the API (F-4).
CCAP_E2E_URL="$URL" CCAP_E2E_SETUP="$SETUP" node "$ROOT/tools/qa/e2e/exam-room.js"
e2e_check_log
echo "live-smoke: OK"
