#!/usr/bin/env bash
# O-4 / G-02 (R0-18 §2): container images of the CI services are pinned by digest.
#
#   tools/ci/image-digests.sh check   list service images without a digest (exit 1 if any)
#   tools/ci/image-digests.sh pin     resolve the digest of every `image: 'name:tag'` from
#                                     Docker Hub and rewrite it to `name:tag@sha256:…`
#   tools/ci/image-digests.sh refresh resolve again for already pinned images (tag kept)
#
# Dependabot does not update `services.*.image` in workflow files, so the nightly lane
# runs `refresh` in dry-run mode (CCAP_DRY_RUN=1) and reports drift as a warning.
set -euo pipefail
MODE=${1:-check}
FILES=${CCAP_WORKFLOWS:-.github/workflows/*.yml}
# shellcheck disable=SC2206
FILES=( $FILES )

images() { grep -hoE "image: '[^']+'" "${FILES[@]}" | sed -E "s/image: '([^']+)'/\1/" | sort -u; }

digest_of() { # name:tag -> sha256:...
	local ref=$1 repo tag token
	repo=${ref%%:*}; tag=${ref#*:}; tag=${tag%%@*}
	[[ "$repo" == */* ]] || repo="library/$repo"
	token=$(curl -fsS "https://auth.docker.io/token?service=registry.docker.io&scope=repository:${repo}:pull" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["token"] ?? "";')
	curl -fsSI -H "Authorization: Bearer ${token}" \
		-H 'Accept: application/vnd.oci.image.index.v1+json' \
		-H 'Accept: application/vnd.docker.distribution.manifest.list.v2+json' \
		"https://registry-1.docker.io/v2/${repo}/manifests/${tag}" | tr -d '\r' | awk 'tolower($1)=="docker-content-digest:"{print $2}'
}

case "$MODE" in
	check)
		missing=$(images | grep -v '@sha256:' || true)
		if [ -n "$missing" ]; then
			while read -r img; do echo "::warning title=G-02 image not pinned by digest::$img (run tools/ci/image-digests.sh pin)"; done <<< "$missing"
			exit 1
		fi
		echo "all service images pinned by digest"
		;;
	pin|refresh)
		drift=0
		while read -r img; do
			base=${img%%@*}
			if [ "$MODE" = pin ] && [[ "$img" == *@sha256:* ]]; then continue; fi
			d=$(digest_of "$base")
			[ -n "$d" ] || { echo "cannot resolve $base" >&2; exit 2; }
			[ "$img" = "$base@$d" ] && continue
			drift=1
			echo "$img -> $base@$d"
			[ -n "${CCAP_DRY_RUN:-}" ] || sed -i "s|image: '${img}'|image: '${base}@${d}'|g" "${FILES[@]}"
		done < <(images)
		if [ -n "${CCAP_DRY_RUN:-}" ] && [ "$drift" = 1 ]; then echo "::warning title=G-02 image digest drift::run tools/ci/image-digests.sh refresh"; fi
		;;
	*) echo "usage: $0 check|pin|refresh" >&2; exit 64 ;;
esac
