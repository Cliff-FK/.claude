#!/usr/bin/env bash
# Crée un user WP + écrit le mdp dans .claude/.tmp_pass
# Usage : ./wp-user-create.sh <username> <role>
# Roles supportés : administrator, editor, author, contributor, subscriber

set -e

USERNAME="${1:-claude_reader}"
ROLE="${2:-administrator}"
EMAIL="${USERNAME}@local.dev"

# MAMP_ROOT override : env var $MAMP_ROOT (Git Bash style, ex: /c/MAMP ou /d/MAMP). Defaut : /c/MAMP
MAMP_ROOT="${MAMP_ROOT:-/c/MAMP}"

# Détection PHP MAMP — priorité 1 : version ACTIVE dans Apache (httpd.conf → PHPIniDir)
# Regex élargie pour absorber suffixes RC/beta/alpha/snap (ex: php8.4.0RC1, php9.0.0-beta3)
ACTIVE_VER=$(grep -oE 'php[0-9]+\.[0-9]+\.[0-9]+[A-Za-z0-9_-]*' "$MAMP_ROOT/conf/apache/httpd.conf" 2>/dev/null | head -1)
if [ -n "$ACTIVE_VER" ] && [ -f "$MAMP_ROOT/bin/php/${ACTIVE_VER}/php.exe" ]; then
  PHP_DIR="$MAMP_ROOT/bin/php/${ACTIVE_VER}/"
else
  # Fallback : dernière version installée non _DISABLE
  PHP_DIR=$(ls -d "$MAMP_ROOT"/bin/php/php*/ 2>/dev/null | grep -v _DISABLE | sort -V | tail -1)
fi
PHP="${PHP_DIR}php.exe"

if [ ! -f "$PHP" ]; then
  echo "ERR: PHP MAMP introuvable (MAMP_ROOT=$MAMP_ROOT)" >&2
  exit 1
fi

# Le CLI MAMP ne charge aucun php.ini par défaut → openssl/curl/etc. manquent.
# On force le ini de la version courante pour que le bootstrap WP fonctionne.
PHP_VER=$(basename "${PHP_DIR%/}")
PHP_INI="$MAMP_ROOT/conf/${PHP_VER}/php.ini"
[ -f "$PHP_INI" ] || PHP_INI=""

# Trouver la racine WP (présence wp-config.php)
WP_ROOT="$(pwd)"
while [ "$WP_ROOT" != "/" ] && [ ! -f "$WP_ROOT/wp-config.php" ]; do
  WP_ROOT="$(dirname "$WP_ROOT")"
done
if [ ! -f "$WP_ROOT/wp-config.php" ]; then
  echo "ERR: wp-config.php introuvable" >&2
  exit 1
fi

# Mdp aléatoire 32 chars
PASS=$(node -e "console.log(require('crypto').randomBytes(16).toString('hex'))")
mkdir -p "$WP_ROOT/.claude"
echo -n "$PASS" > "$WP_ROOT/.claude/.tmp_pass"

# Convertir le path Git Bash → Windows pour PHP
WP_ROOT_WIN=$(cygpath -w "$WP_ROOT" 2>/dev/null || echo "$WP_ROOT")
WP_ROOT_WIN="${WP_ROOT_WIN//\\//}"

# Création user via PHP CLI (bootstrap WP)
"$PHP" ${PHP_INI:+-c "$PHP_INI"} -r "
define('ABSPATH', '$WP_ROOT_WIN/');
\$_SERVER['HTTP_HOST']='localhost';
\$_SERVER['REQUEST_URI']='/';
require '$WP_ROOT_WIN/wp-load.php';
if (username_exists('$USERNAME')) {
    wp_delete_user(get_user_by('login','$USERNAME')->ID);
}
\$id = wp_create_user('$USERNAME', '$PASS', '$EMAIL');
if (is_wp_error(\$id)) {
    fwrite(STDERR, 'ERR: '.\$id->get_error_message()); exit(1);
}
\$u = new WP_User(\$id);
\$u->set_role('$ROLE');
echo 'OK user_id='.\$id.' role=$ROLE';
" 2>/dev/null
