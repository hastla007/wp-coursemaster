#!/usr/bin/env bash
# Nutzertest-Umgebung R1-29 (R0-26 §3.3): frisches WordPress mit Course Captain
# aus dem Release-ZIP, Mail-Sink und einem Startzustand je Profil.
#
#   NUR FÜR TEST-SITES. Das Skript LÖSCHT die angegebene Datenbank und das
#   Site-Verzeichnis und legt Konten mit Passwörtern an. Die Datenbank muss
#   mit "ccap_usab" beginnen; ein vorhandenes Verzeichnis wird nur ersetzt,
#   wenn es von diesem Skript stammt (Markierung .ccap-ux-testsite).
#
# Aufruf (Neuaufbau = Zurücksetzen auf den Startzustand zwischen Sitzungen):
#   tools/usability/setup-site.sh [--profil referenz|autor|admin] [--site DIR]
#       [--db NAME] [--url URL] [--kit DIR] [--lernende N] [--worktree]
#
# Standard: Profil referenz, Site /tmp/ccap-usab-<profil>, DB ccap_usab_<profil>,
# URL https://ccap-usab-<profil>.example.test, Kit /tmp/ccap-usab-kit.
# DB-Zugang wie die Tests: WP_TESTS_DB_USER/_PASSWORD/_HOST (Standard ccap/ccap/localhost).
# Optional: CCAP_UX_EXTERNAL_VIDEO_URL (Fremdhost-Video für E-45a/LE-T03).
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
PROFIL=referenz; SITE=""; DB=""; URL=""; KIT=/tmp/ccap-usab-kit; LERNENDE=9; ZIPREF=(--ref=HEAD)
while [[ $# -gt 0 ]]; do
	case "$1" in
		--profil) PROFIL=$2; shift 2 ;;
		--site) SITE=$2; shift 2 ;;
		--db) DB=$2; shift 2 ;;
		--url) URL=$2; shift 2 ;;
		--kit) KIT=$2; shift 2 ;;
		--lernende) LERNENDE=$2; shift 2 ;;
		--worktree) ZIPREF=(--worktree); shift ;;
		-h|--help) sed -n '2,20p' "$0"; exit 0 ;;
		*) echo "Unbekannte Option: $1" >&2; exit 2 ;;
	esac
done
case "$PROFIL" in referenz|autor|admin) ;; *) echo "Profil muss referenz, autor oder admin sein." >&2; exit 2 ;; esac
SITE=${SITE:-/tmp/ccap-usab-$PROFIL}; DB=${DB:-ccap_usab_$PROFIL}; URL=${URL:-https://ccap-usab-$PROFIL.example.test}
[[ "$DB" == ccap_usab* ]] || { echo "Abbruch: Die Datenbank muss mit ccap_usab beginnen (nur Test-Sites)." >&2; exit 2; }
if [[ -e "$SITE" && ! -f "$SITE/.ccap-ux-testsite" && -n "$(ls -A "$SITE" 2>/dev/null)" ]]; then
	echo "Abbruch: $SITE existiert und ist keine von diesem Skript erzeugte Test-Site." >&2; exit 2
fi
CORE=${WP_CORE_DIR:-$ROOT/vendor/roots/wordpress-no-content}
WP=("$ROOT/vendor/bin/wp" --allow-root --path="$SITE" --require="$ROOT/tools/usability/isolate-autoload.php")
DBUSER=${WP_TESTS_DB_USER:-ccap}; DBPASS=${WP_TESTS_DB_PASSWORD:-ccap}; HOSTPORT=${WP_TESTS_DB_HOST:-localhost}
DBHOST=${HOSTPORT%%:*}; DBPORT=3306; [[ "$HOSTPORT" == *:* ]] && DBPORT=${HOSTPORT##*:}
MYSQL=(mysql -u"$DBUSER" -p"$DBPASS" -h"$DBHOST" -P"$DBPORT")
T0=$(date +%s)

echo "[1/7] Release-ZIP bauen (${ZIPREF[*]})"
BUILD=$(mktemp -d /tmp/ccap-usab-zip-XXXX); trap 'rm -rf "$BUILD"' EXIT
php "$ROOT/tools/release/build-zip.php" "${ZIPREF[@]}" --out="$BUILD" >/dev/null
ZIP=$(ls "$BUILD"/*.zip | head -n1)
php "$ROOT/tools/release/check-zip.php" "$ZIP" >/dev/null || { echo "check-zip meldet Befunde – Abbruch." >&2; exit 1; }

echo "[2/7] WordPress-Kern nach $SITE kopieren"
rm -rf "$SITE"; mkdir -p "$SITE"; cp -r "$CORE"/. "$SITE"/
rm -rf "$SITE/wp-content/uploads"/* "$SITE/wp-content/plugins/hello.php"
touch "$SITE/.ccap-ux-testsite"

echo "[3/7] wp-config und Datenbank $DB"
"${WP[@]}" config create --dbname="$DB" --dbuser="$DBUSER" --dbpass="$DBPASS" --dbhost="$HOSTPORT" --locale=de_DE --skip-check --quiet --extra-php <<'PHP'
/* Nutzertest R1-29 – NUR TEST-SITE. Der Befehl `wp ccap-ux` arbeitet nur mit dieser Konstante. */
define( 'CCAP_UX_TESTSITE', true );
define( 'WP_ENVIRONMENT_TYPE', 'staging' );
define( 'WP_DEBUG', false );
define( 'FS_METHOD', 'direct' );
PHP
"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>/dev/null

echo "[4/7] WordPress installieren ($URL)"
"${WP[@]}" core install --url="$URL" --title="Course Captain Nutzertest (Testsite)" --admin_user=cc_fx_ux_admin \
	--admin_password="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 20)" \
	--admin_email=cc_fx_ux_admin@example.test --skip-email --quiet
LANGZIP=${CCAP_UX_CORE_LANG_ZIP:-}; mkdir -p "$SITE/wp-content/languages"
if [[ -z "$LANGZIP" ]]; then
	LANGZIP="$BUILD/de_DE.zip"
	curl -fsS -o "$LANGZIP" "https://downloads.wordpress.org/translation/core/$("${WP[@]}" core version)/de_DE.zip" 2>/dev/null || LANGZIP=""
fi
if [[ -n "$LANGZIP" ]] && unzip -oq "$LANGZIP" -d "$SITE/wp-content/languages"; then
	"${WP[@]}" option update WPLANG de_DE --quiet
else
	echo "WARNUNG: WordPress-Sprachpaket de_DE fehlt (kein Download möglich). Der Lernbereich bleibt Englisch, bis es installiert ist" >&2
	echo "         (CCAP_UX_CORE_LANG_ZIP=<de_DE.zip> setzen oder auf der Staging-Site Einstellungen > Allgemein > Sprache). E-169 verlangt de_DE." >&2
fi
"${WP[@]}" option update permalink_structure '/%postname%/' --quiet
"${WP[@]}" option update blog_public 0 --quiet
"${WP[@]}" theme activate twentytwentyfive --quiet
cat > "$SITE/.htaccess" <<'HTA'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTA

echo "[5/7] Course Captain aus dem ZIP installieren und aktivieren"
OUT=$("${WP[@]}" plugin install "$ZIP" --activate 2>&1); echo "$OUT" | tail -n 2
if echo "$OUT" | grep -qE "PHP (Notice|Warning|Deprecated|Fatal)|^(Notice|Deprecated|Fatal error):"; then echo "PHP-Ausgabe bei der Aktivierung – Abbruch." >&2; exit 1; fi
mkdir -p "$SITE/wp-content/mu-plugins"; cp "$ROOT/tools/usability/mailsink.php" "$SITE/wp-content/mu-plugins/ccap-ux-mailsink.php"

echo "[6/7] Startzustand Profil $PROFIL anlegen"
SEED=("${WP[@]}" --require="$ROOT/tools/usability/seeder.php" ccap-ux seed --profil="$PROFIL" --lernende="$LERNENDE")
[[ "$PROFIL" == referenz ]] && SEED+=(--kit="$KIT")
"${SEED[@]}"

echo "[7/7] Fertig in $(( $(date +%s) - T0 )) s"
cat <<INFO
  Site:        $SITE   (DocumentRoot; Apache mit .htaccess oder nginx mit try_files)
  URL:         $URL
  Datenbank:   $DB
  Mail-Sink:   $SITE/wp-content/ccap-ux-mails/mails.log (keine echten Mails)
  Befehle:     $ROOT/vendor/bin/wp --allow-root --path=$SITE --require=$ROOT/tools/usability/isolate-autoload.php --require=$ROOT/tools/usability/seeder.php ccap-ux <scenario|check-rk|kit> …
  Zurücksetzen: dieses Skript mit denselben Optionen erneut ausführen (neue Passwörter).
INFO
[[ "$PROFIL" == referenz ]] && echo "  Autoren-Kit: $KIT" || true
