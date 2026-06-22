#!/usr/bin/env bash
# Delete un user WP et cleanup .tmp_pass
# Usage : ./wp-user-delete.sh <username>

set -e

USERNAME="${1:-claude_reader}"

# MAMP_ROOT override : env var $MAMP_ROOT (Git Bash style, ex: /c/MAMP ou /d/MAMP). Defaut : /c/MAMP
MAMP_ROOT="${MAMP_ROOT:-/c/MAMP}"

# Détection PHP MAMP — priorité 1 : version ACTIVE dans Apache (httpd.conf → PHPIniDir)
# Regex élargie pour absorber suffixes RC/beta/alpha/snap
ACTIVE_VER=$(grep -oE 'php[0-9]+\.[0-9]+\.[0-9]+[A-Za-z0-9_-]*' "$MAMP_ROOT/conf/apache/httpd.conf" 2>/dev/null | head -1)
if [ -n "$ACTIVE_VER" ] && [ -f "$MAMP_ROOT/bin/php/${ACTIVE_VER}/php.exe" ]; then
  PHP_DIR="$MAMP_ROOT/bin/php/${ACTIVE_VER}/"
else
  PHP_DIR=$(ls -d "$MAMP_ROOT"/bin/php/php*/ 2>/dev/null | grep -v _DISABLE | sort -V | tail -1)
fi
PHP="${PHP_DIR}php.exe"

# Le CLI MAMP ne charge aucun php.ini par défaut → openssl/curl/etc. manquent.
PHP_VER=$(basename "${PHP_DIR%/}")
PHP_INI="$MAMP_ROOT/conf/${PHP_VER}/php.ini"
[ -f "$PHP_INI" ] || PHP_INI=""

WP_ROOT="$(pwd)"
while [ "$WP_ROOT" != "/" ] && [ ! -f "$WP_ROOT/wp-config.php" ]; do
  WP_ROOT="$(dirname "$WP_ROOT")"
done

WP_ROOT_WIN=$(cygpath -w "$WP_ROOT" 2>/dev/null || echo "$WP_ROOT")
WP_ROOT_WIN="${WP_ROOT_WIN//\\//}"

"$PHP" ${PHP_INI:+-c "$PHP_INI"} -r "
define('ABSPATH', '$WP_ROOT_WIN/');
\$_SERVER['HTTP_HOST']='localhost';
\$_SERVER['REQUEST_URI']='/';
require '$WP_ROOT_WIN/wp-load.php';
require '$WP_ROOT_WIN/wp-admin/includes/user.php';
\$u = get_user_by('login', '$USERNAME');
if (\$u) {
    wp_delete_user(\$u->ID, 1);
    echo 'OK delete $USERNAME';
} else {
    echo 'user $USERNAME introuvable';
}
" 2>/dev/null

rm -f "$WP_ROOT/.claude/.tmp_pass"
