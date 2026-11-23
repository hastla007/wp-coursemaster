# shellcheck shell=bash
# R1-18 live E2E: shared site handling for live-smoke.sh and live-flows.sh (sourced).
#
# Expects ROOT (plugin working tree) and DB; sets SITE, URL, PORT, PREFIX, WP, MYSQL.
# The site uses its own table prefix inside a test database (name must contain
# "test", D-01); only tables with that prefix are created and dropped.
# `php -S` runs either on the host or, when `php` is a Docker wrapper (php8.5
# shim), in its own named container with `--init` so that stopping it really
# ends the server (no orphaned containers).

e2e_init() {
	[[ "$DB" == *test* ]] || { echo "Refusing: database name must contain 'test'"; exit 2; }
	PREFIX=${PREFIX:-ccape2e_}
	PORT=${CCAP_E2E_PORT:-8765}
	URL="http://127.0.0.1:$PORT"
	CORE=${WP_CORE_DIR:-$ROOT/vendor/roots/wordpress-no-content}
	WP="$ROOT/vendor/bin/wp --allow-root"
	SITE=$(mktemp -d /tmp/ccap-e2e-XXXX)
	# The php Docker wrapper reaches MariaDB over TCP (host network), not over the socket.
	local default_host=localhost
	e2e_php_image >/dev/null 2>&1 && default_host=127.0.0.1
	HOSTPORT=${WP_TESTS_DB_HOST:-$default_host}; DBHOST=${HOSTPORT%%:*}; DBPORT=3306; [[ "$HOSTPORT" == *:* ]] && DBPORT=${HOSTPORT##*:}
	MYSQL=(mysql -u"${WP_TESTS_DB_USER:-ccap}" -p"${WP_TESTS_DB_PASSWORD:-ccap}" -h"$DBHOST" -P"$DBPORT" -N -B "$DB")
	SERVER=
	SERVER_NAME=
}

e2e_drop_prefix() {
	local tables
	tables=$("${MYSQL[@]}" -e "SELECT GROUP_CONCAT(CONCAT('\`', table_name, '\`')) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE '${PREFIX//_/\\_}%'" 2>/dev/null)
	if [[ -n "$tables" && "$tables" != "NULL" ]]; then "${MYSQL[@]}" -e "SET FOREIGN_KEY_CHECKS = 0; DROP TABLE $tables; SET FOREIGN_KEY_CHECKS = 1" 2>/dev/null; fi
}

# Fresh WordPress with the plugin of this working tree, active.
e2e_site_create() {
	e2e_drop_prefix
	# Core files and wp-admin are copied (they locate wp-load.php/wp-config.php
	# by their own path); wp-includes and the themes are read-only and linked.
	# Not copied: the package's git history and uploads of earlier test runs.
	tar -C "$CORE" --exclude=./.git --exclude=./wp-includes --exclude=./wp-content -cf - . | tar -C "$SITE" -xf -
	ln -s "$(readlink -f "$CORE")/wp-includes" "$SITE/wp-includes"
	mkdir -p "$SITE/wp-content/plugins" "$SITE/wp-content/mu-plugins" "$SITE/wp-content/uploads"
	ln -s "$(readlink -f "$CORE")/wp-content/themes" "$SITE/wp-content/themes"
	ln -s "$ROOT" "$SITE/wp-content/plugins/course-captain"
	$WP --path="$SITE" config create --dbname="$DB" --dbuser="${WP_TESTS_DB_USER:-ccap}" --dbpass="${WP_TESTS_DB_PASSWORD:-ccap}" --dbhost="$HOSTPORT" --dbprefix="$PREFIX" --skip-check --quiet
	$WP --path="$SITE" core install --url="$URL" --title="CC E2E" --admin_user=cc_e2e_admin --admin_password="$CCAP_E2E_PASS" --admin_email=admin@fixture.test --skip-email --quiet
	$WP --path="$SITE" plugin activate course-captain --quiet
}

# Image of the php Docker wrapper, if `php` is one.
e2e_php_image() {
	local bin
	bin=$(readlink -f "$(command -v php)")
	grep -q 'docker run' "$bin" 2>/dev/null || return 1
	grep -oE '[a-z0-9.-]+(/[a-z0-9._-]+)+:cli-php[0-9.]+' "$bin" | head -1
}

e2e_server_start() {
	local image
	if image=$(e2e_php_image) && [[ -n "$image" ]]; then
		SERVER_NAME="ccap-e2e-$PORT-$$"
		local core
		core=$(readlink -f "$CORE")
		local mounts=(-v "$SITE:$SITE" -v "$ROOT:$ROOT" -v "$core:$core:ro")
		[[ -d /var/run/mysqld ]] && mounts+=(-v /var/run/mysqld:/var/run/mysqld)
		docker run -d --init --name "$SERVER_NAME" --network host -u "$(id -u):$(id -g)" "${mounts[@]}" -w "$SITE" \
			-e PHP_CLI_SERVER_WORKERS="${CCAP_E2E_WORKERS:-4}" --entrypoint php "$image" -d memory_limit=1G -S "127.0.0.1:$PORT" -t "$SITE" >/dev/null
	else
		PHP_CLI_SERVER_WORKERS="${CCAP_E2E_WORKERS:-4}" php -S "127.0.0.1:$PORT" -t "$SITE" >"$SITE/server.log" 2>&1 &
		SERVER=$!
	fi
	for _ in $(seq 1 100); do curl -s -o /dev/null "$URL/wp-login.php" && return 0; sleep 0.2; done
	echo "server did not start"; return 1
}

# Server log (container logs or the host log file).
e2e_server_log() {
	if [[ -n "$SERVER_NAME" ]]; then docker logs "$SERVER_NAME" 2>&1; else cat "$SITE/server.log" 2>/dev/null; fi
}

e2e_server_stop() {
	if [[ -n "$SERVER_NAME" ]]; then
		e2e_server_log >"$SITE/server.log" 2>&1 || true
		docker rm -f "$SERVER_NAME" >/dev/null 2>&1 || true
		SERVER_NAME=
	fi
	if [[ -n "$SERVER" ]]; then kill "$SERVER" 2>/dev/null || true; wait "$SERVER" 2>/dev/null || true; SERVER=; fi
}

e2e_cleanup() {
	e2e_server_stop
	if [[ -z "${CCAP_E2E_KEEP:-}" ]]; then e2e_drop_prefix; rm -rf "$SITE"; else echo "kept: $SITE (prefix $PREFIX)"; fi
}

# Fails on PHP fatals/warnings/notices/deprecations in the server log.
e2e_check_log() {
	if e2e_server_log | grep -qE "PHP (Fatal|Warning|Notice|Deprecated)"; then
		echo "FAIL: PHP messages in the server log"; e2e_server_log | grep -E "PHP (Fatal|Warning|Notice|Deprecated)" | head -20; return 1
	fi
}
