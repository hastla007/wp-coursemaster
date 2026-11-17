#!/usr/bin/env bash
# Sourced by the release/usability scripts: make `php` (and everything that calls it,
# e.g. vendor/bin/wp) the plugin's minimum PHP or newer (MINVER-1: 8.5). If the default
# `php` is older and a `php8.5`/`php8.6` binary exists, a shim directory is put first in PATH.
ccap_php_min() {
	local need=${CCAP_PHP_MIN:-8.5} bin shim
	if php -r "exit(version_compare(PHP_VERSION, '$need', '>=') ? 0 : 1);" 2>/dev/null; then return 0; fi
	for bin in php8.6 php8.5; do
		if command -v "$bin" >/dev/null 2>&1; then
			shim=$(mktemp -d /tmp/ccap-php-XXXX); ln -s "$(command -v "$bin")" "$shim/php"; export PATH="$shim:$PATH"
			echo "using $bin ($("$bin" -r 'echo PHP_VERSION;')) instead of the default php" >&2; return 0
		fi
	done
	echo "PHP $need or newer required (MINVER-1); default php is $(php -r 'echo PHP_VERSION;')" >&2; return 1
}
