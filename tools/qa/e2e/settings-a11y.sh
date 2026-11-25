#!/usr/bin/env bash
# SET-CC-21 / SET-CC-P5: accessibility acceptance of the settings surfaces on a live site
# (fresh WordPress from vendor, plugin of this working tree, `cc-fixture-neutral-v1`,
# `php -S`, Playwright; UI-F7: CCAP_E2E_BROWSER = chromium | firefox | webkit, default chromium;
# CCAP_E2E_DEVICE=mobile runs the admin pages on a phone at 390 px). Pages and checks: see
# settings-a11y.js.
#
# Usage: tools/qa/e2e/settings-a11y.sh [db_name]
# Table prefix `ccapset_` in the given test database (name must contain "test", D-01);
# only tables with this prefix are created and dropped. AXE_PATH=<axe.min.js> adds axe-core
# (not bundled, not downloaded by this script). CCAP_E2E_PORT (default 8767), CCAP_E2E_OUT
# (results directory, default: a new temp directory), CCAP_E2E_KEEP=1 keeps the site,
# CCAP_E2E_PREFIX another table prefix (parallel runs).
# CCAP_E2E_SHOTS=<dir> stores a screenshot of every checked page (outside the repository).
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../../.." && pwd)
# shellcheck source=../../release/php-min.sh
source "$ROOT/tools/release/php-min.sh"; ccap_php_min || exit 1
# shellcheck source=lib-site.sh
source "$ROOT/tools/qa/e2e/lib-site.sh"
DB=${1:-${WP_TESTS_DB_NAME:-ccap_test}}
PREFIX=${CCAP_E2E_PREFIX:-ccapset_}
export CCAP_E2E_PORT=${CCAP_E2E_PORT:-8767}
export CCAP_E2E_PASS="e2e-$(head -c 9 /dev/urandom | od -An -tx1 | tr -d ' \n')"
e2e_init
OUTDIR=${CCAP_E2E_OUT:-$(mktemp -d /tmp/ccap-e2e-out-XXXX)}
mkdir -p "$OUTDIR"
trap e2e_cleanup EXIT

e2e_site_create
SETUP=$(php "$ROOT/tools/qa/e2e/live-setup.php" "$SITE" setup)
echo "fixture: $SETUP"
e2e_server_start
STATUS=0
CCAP_E2E_URL="$URL" CCAP_E2E_SETUP="$SETUP" CCAP_E2E_OUT="$OUTDIR" node "$ROOT/tools/qa/e2e/settings-a11y.js" || STATUS=1
e2e_check_log || STATUS=1
echo "results: $OUTDIR"
if [[ $STATUS -eq 0 ]]; then echo "settings-a11y: OK"; else echo "settings-a11y: FAILED"; fi
exit $STATUS
