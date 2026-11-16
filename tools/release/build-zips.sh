#!/usr/bin/env bash
# Build the release ZIP (R0-35 §3.1, R0-18 §3.1). Usage:
#   tools/release/build-zips.sh --package free [--ref <commit>|--worktree] [--out <dir>]
set -euo pipefail
cd "$(dirname "$0")/../.."
exec php tools/release/build-zip.php "$@"
