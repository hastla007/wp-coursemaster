#!/usr/bin/env bash
# R1-18 Gate B-4 (R0-12 §3.2): reference E2E of R0-11 Flows 1–3 on a live site in a browser
# (UI-F7: CCAP_E2E_BROWSER = chromium | firefox | webkit, default chromium).
#
#   Flow 1  flow-author.js   Course Manager builds the reference course in the admin (block
#                            editor + meta boxes): 3 modules, 10 lessons incl. quiz, assignment
#                            (E-168) and exam; validator guidance (F1.1); publishes revision 1.
#   Flow 2  live-smoke.js    learner learns, passes, gets the certificate – desktop (U-L1) and
#                            phone (390 px, `cc_e2e_mia`); exam-room.js (GM-050/051, phone).
#   Flow 3  flow-group.js    admin switches groups on, group manager creates a group and
#                            invites by email (mail sink), the person logs in via the link,
#                            accepts, learns the reference course: lesson, practice questions,
#                            assignment (graded by the manager), exam 20 of 50, certificate.
#   A11Y    flow-a11y.js     DOM checks of the core pages; axe-core when AXE_PATH points to
#                            axe.min.js (not bundled, not downloaded).
#   Bench   bench-report.js  timings of all steps + sampled learner requests (benchmark.md).
#
# Usage: tools/qa/e2e/live-flows.sh [db_name]
# Table prefix `ccapflow_` in the given test database (name must contain "test", D-01);
# only tables with this prefix are created and dropped. CCAP_E2E_PORT (default 8766),
# CCAP_E2E_OUT (default: a new temp directory; results and screenshots of failures),
# CCAP_E2E_KEEP=1 keeps the site, CCAP_E2E_PREFIX another table prefix (parallel runs).
# CCAP_E2E_DEVICE=mobile runs Flow 1, Flow 3 and the A11Y pages (admin and learner) on a phone
# at 390 px (lib.js phone()); Flow 2 always runs on desktop and phone, the benchmark on desktop.
# CCAP_E2E_SHOTS=<dir> stores a screenshot of every A11Y page (outside the repository).
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../../.." && pwd)
# shellcheck source=../../release/php-min.sh
source "$ROOT/tools/release/php-min.sh"; ccap_php_min || exit 1
# shellcheck source=lib-site.sh
source "$ROOT/tools/qa/e2e/lib-site.sh"
DB=${1:-${WP_TESTS_DB_NAME:-ccap_test}}
PREFIX=${CCAP_E2E_PREFIX:-ccapflow_}
export CCAP_E2E_PORT=${CCAP_E2E_PORT:-8766}
export CCAP_E2E_PASS="e2e-$(head -c 9 /dev/urandom | od -An -tx1 | tr -d ' \n')"
e2e_init
OUTDIR=${CCAP_E2E_OUT:-$(mktemp -d /tmp/ccap-e2e-out-XXXX)}
mkdir -p "$OUTDIR"
rm -f "$OUTDIR/timings.jsonl"
trap e2e_cleanup EXIT

T0=$(date +%s)
e2e_site_create
cp "$ROOT/tools/qa/e2e/mu-mail-capture.php" "$SITE/wp-content/mu-plugins/"
CLI="php $ROOT/tools/qa/e2e/live-setup.php $SITE"
SETUP=$($CLI setup)
USERS=$($CLI users)
echo "fixture: $SETUP users: $USERS"
e2e_server_start
echo "site ready after $(( $(date +%s) - T0 )) s: $URL"

export CCAP_E2E_URL="$URL" CCAP_E2E_SETUP="$SETUP" CCAP_E2E_OUT="$OUTDIR" CCAP_E2E_CLI="$CLI" \
	CCAP_E2E_DISPATCH="$CLI dispatch" CCAP_E2E_MAIL="$SITE/wp-content/ccap-e2e-mail.jsonl"
STATUS=0
run() {
	local name=$1; shift
	local t=$(date +%s)
	echo "== $name"
	if "$@"; then echo "-- $name: OK ($(( $(date +%s) - t )) s)"; else echo "-- $name: FAILED"; STATUS=1; fi
}
export CCAP_E2E_BROWSER=${CCAP_E2E_BROWSER:-chromium}
DEV=desktop; [[ "${CCAP_E2E_DEVICE:-}" == mobile ]] && DEV="phone 390 px"
B=$CCAP_E2E_BROWSER
run "Flow 1 (author, $B $DEV)" node "$ROOT/tools/qa/e2e/flow-author.js"
run "Flow 2 (learner U-L1, $B desktop)" env CCAP_E2E_DEVICE=desktop node "$ROOT/tools/qa/e2e/live-smoke.js"
run "Flow 2 (learner, $B phone 390 px)" env CCAP_E2E_LEARNER=cc_e2e_mia CCAP_E2E_DEVICE=mobile node "$ROOT/tools/qa/e2e/live-smoke.js"
run "Flow 2 exam room (U-L2, $B phone 390 px)" node "$ROOT/tools/qa/e2e/exam-room.js"
if [[ -f "$OUTDIR/reference-course.json" ]]; then
	run "Flow 3 (group manager invites, member learns the reference course; $B $DEV)" node "$ROOT/tools/qa/e2e/flow-group.js"
	run "A11Y core pages ($B $DEV)" node "$ROOT/tools/qa/e2e/flow-a11y.js"
	run "Benchmark ($B desktop)" env CCAP_E2E_DEVICE=desktop node "$ROOT/tools/qa/e2e/bench-report.js"
else
	echo "-- Flow 3, A11Y, benchmark skipped: no reference course from Flow 1"; STATUS=1
fi
e2e_check_log || STATUS=1
echo "results: $OUTDIR"
if [[ $STATUS -eq 0 ]]; then echo "live-flows: OK"; else echo "live-flows: FAILED"; fi
exit $STATUS
