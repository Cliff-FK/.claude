#!/usr/bin/env bash
# Inventaire heuristique, en lecture seule, de ce que scan-security-sinks.sh (wp-plugin-check) ne cible pas :
# scripts PHP appelables en direct, fichiers écrits sous la racine servie, secrets littéraux (MASQUÉS),
# vérification TLS coupée, secret porté dans une URL, entrée de requête recollée sans encodage.
# Un hit n'est pas une faille, zéro hit n'est pas une preuve : chaque ligne se trace jusqu'à son sink.
# Exige bash, GNU grep avec -P, sha256sum et perl (présents dans Git Bash et sous Linux).
# Limites connues : secret sur plusieurs lignes, heredoc, valeur construite par concaténation de
# variables : non détectés. Toute sortie de ligne source passe par mask-secrets.pl.
set -euo pipefail
export LC_ALL=C.UTF-8
HERE=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)

usage() {
  cat <<'HELP'
Usage: bash scan-exposure.sh [DIRECTORY]
DIRECTORY : dossier d'un plugin ou d'un thème (défaut : dossier courant).
Les secrets ne sont JAMAIS imprimés. Un secret d'au moins 16 caractères est rendu par ses 4 premiers
caractères, sa longueur et une empreinte sha256 tronquée (12), qui retrouve la même valeur dans un
autre projet sans la divulguer. Sous 16 caractères, seule la longueur est rendue : préfixe et
empreinte d'un secret court se retrouvent par force brute.
Code retour : 0 = inventaire complet, 2 = erreur d'usage ou de recherche.
HELP
}

[[ $# -eq 1 && ( $1 == -h || $1 == --help ) ]] && { usage; exit 0; }
[[ $# -gt 1 ]] && { usage >&2; exit 2; }
root=${1:-.}
[[ -d $root ]] || { printf 'ERREUR : pas un dossier : %s\n' "$root" >&2; exit 2; }
command -v sha256sum >/dev/null 2>&1 || { printf 'ERREUR : sha256sum requis.\n' >&2; exit 2; }
command -v perl >/dev/null 2>&1 || { printf 'ERREUR : perl requis (masquage).\n' >&2; exit 2; }
[[ -r $HERE/mask-secrets.pl ]] || { printf 'ERREUR : mask-secrets.pl introuvable à côté du script.\n' >&2; exit 2; }
printf 'x' | grep -qP 'x' 2>/dev/null || { printf 'ERREUR : grep -P requis (GNU grep).\n' >&2; exit 2; }
cd -- "$root" || exit 2

INC=(--include=*.php --include=*.phtml --include=*.inc --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git)
G=(grep -rnP "${INC[@]}")

section() { printf '\n## %s\n' "$1"; }
mask() { perl "$HERE/mask-secrets.pl"; }
run() {
  local status=0 out
  out=$("${G[@]}" "$@" . 2>/dev/null) || status=$?
  if [[ $status -gt 1 ]]; then printf 'ERREUR : recherche incomplète.\n' >&2; exit 2; fi
  if [[ -z ${out:-} ]]; then printf '(aucune correspondance textuelle)\n'; else printf '%s\n' "$out" | sed 's|^\./||' | mask; fi
}

printf '%s\n' 'INVENTAIRE HEURISTIQUE : pas un audit. Tracer chaque ligne jusqu au sink avant de conclure.'

section 'Scripts qui chargent WordPress eux-mêmes (points d entrée HTTP directs, hors routage WP)'
run "(require|include)(_once)?\s*\(?[^;]*wp-(load|config|blog-header)\.php"

section 'Fichiers PHP sans garde ABSPATH (examiner ceux qui exécutent du code au niveau du fichier)'
status=0
out=$(grep -rLP "${INC[@]}" "defined\s*\(\s*['\"]ABSPATH['\"]\s*\)" . 2>/dev/null) || status=$?
if [[ $status -gt 1 ]]; then printf 'ERREUR : recherche incomplète.\n' >&2; exit 2; fi
if [[ -n ${out:-} ]]; then printf '%s\n' "$out" | sed 's|^\./||' | sort; else printf '(aucun)\n'; fi

section 'Handlers sur des hooks sans utilisateur garanti (admin_init/init/wp_loaded/template_redirect : admin-ajax et admin-post déclenchent admin_init pour l anonyme)'
run "add_action\s*\(\s*['\"](admin_init|init|wp_loaded|template_redirect|parse_request)['\"]"

section 'is_admin() employé comme garde (il ne dit rien de l utilisateur)'
run "if\s*\(\s*!?\s*is_admin\s*\(\s*\)\s*\)|is_admin\s*\(\s*\)\s*(&&|\|\|)"

section 'Élévation par les données : option, méta ou utilisateur écrits avec une valeur de requête sur la ligne'
run "\b(update_option|add_option|update_site_option|update_user_meta|add_user_meta|wp_update_user|wp_insert_user|do_action|call_user_func(_array)?)\s*\([^;]*\\\$_(GET|POST|REQUEST|COOKIE)"

section 'Redirections avec une cible de requête (wp_safe_redirect + exit attendus)'
run "\bwp_redirect\s*\([^;]*\\\$_(GET|POST|REQUEST|SERVER)|header\s*\(\s*['\"]Location:[^;]*\\\$_(GET|POST|REQUEST)"

section 'Comparaison non stricte d un jeton, d une clé ou d un rôle (type juggling)'
run "(?i)(token|key|secret|hash|nonce|code|role|pass)[a-z_\]'\"]*\s*(==|!=)\s*[^=]|[^=!](==|!=)\s*\\\$[a-z_]*(token|key|secret|hash|code)|\\\$_(GET|POST|REQUEST|COOKIE)\s*\[[^]]+\][^;=!]*[^=!](==|!=)[^=]"

section 'CORS, IP de confiance, extract()'
run "Access-Control-Allow-Origin[^;]*HTTP_ORIGIN|HTTP_X_FORWARDED_FOR|HTTP_CLIENT_IP|\bextract\s*\("

section 'Écritures de fichiers (la cible est-elle servie en HTTP : wp-content, uploads, dossier du plugin ?)'
run "\b(file_put_contents|fopen|fwrite|mkdir|wp_mkdir_p|copy|move_uploaded_file|touch)\s*\(|error_log\s*\([^;]*,\s*3\s*,"

section 'Vérification TLS coupée (durcissement ; peser les serveurs clients non maîtrisés)'
run "CURLOPT_SSL_VERIFY(PEER|HOST)\s*,\s*(0|false)|['\"]sslverify['\"]\s*=>\s*(0|false)|https_(local_)?ssl_verify['\"]\s*,\s*['\"]?__return_false"

section 'Secret porté dans une URL (finit dans les journaux d accès, les journaux applicatifs, le Referer)'
run "(?i)[?&](api_?key|apikey|access_token|token|secret|password|customer_?key|key|signature)=(['\"]\s*\.|[^&'\"\s#]{6,})|://[^/\s:@'\"]+:[^/\s@'\"]+@"

section 'Entrée de requête recollée dans une chaîne sans encodage visible sur la ligne'
run "\\\$_(GET|POST|REQUEST|COOKIE|SERVER)\s*\[[^]]+\](?![^;]*(esc_|sanitize_|absint|intval|\(int\)|rawurlencode|urlencode|in_array|hash_equals|isset|empty|json_encode))[^;]*(\.\s*['\"]|['\"]\s*\.)|(\.\s*)\\\$_(GET|POST|REQUEST|COOKIE)\s*\["

section 'Secrets littéraux (MASQUÉS : longueur ; préfixe et empreinte seulement à partir de 16 caractères)'
# 1re branche : littéral affecté à un nom sans ambiguïté (api_key, secret, password…), sans exigence de
# chiffre. 2e : nom générique en *key, ou littéral comparé (garde d'accès) : un chiffre est exigé, sinon
# les noms d'options et d'actions de nonce noient le résultat. Le premier caractère exclut les
# concaténations ('.CONSTANTE.', '{$var}') ; un hachage bcrypt ($2y$…) est admis.
v_any="(?:\\\$2[aby]\\\$|[A-Za-z0-9_])[^'\"\s]{7,}(?=['\"])"
v_dig="(?=[^'\"\s]*[0-9])(?:\\\$2[aby]\\\$|[A-Za-z0-9_])[^'\"\s]{7,}(?=['\"])"
pattern="(?i)(api_?key|apikey|secret|passw(or)?d|token|customer_?key|client_?secret|auth_?key|private_?key|salt)[a-z0-9_]*['\"]?\s*(=>|=|,)\s*['\"]\K$v_any|[_a-z]key[a-z0-9_]*['\"]?\s*(=>|=|,)\s*['\"]\K$v_dig|(hash_equals\s*\(\s*|[!=]==?\s*)['\"]\K$v_dig"
status=0
hits=$("${G[@]}" -o "$pattern" . 2>/dev/null) || status=$?
if [[ $status -gt 1 ]]; then printf 'ERREUR : recherche incomplète.\n' >&2; exit 2; fi
if [[ -z ${hits:-} ]]; then
  printf '(aucune correspondance textuelle)\n'
else
  while IFS= read -r line; do
    # fichier:ligne:valeur ; la valeur peut contenir des ':' (URL), on la prend après le 2e.
    file=${line%%:*}; rest=${line#*:}; lno=${rest%%:*}; val=${rest#*:}
    if (( ${#val} >= 16 )); then
      fp=$(printf '%s' "$val" | sha256sum | cut -c1-12)
      printf '%s:%s  %s… (%d car., sha256:%s)\n' "${file#./}" "$lno" "${val:0:4}" "${#val}" "$fp"
    else
      printf '%s:%s  … (%d car., trop court pour un préfixe ou une empreinte : comparer localement sans afficher)\n' "${file#./}" "$lno" "${#val}"
    fi
  done <<<"$hits"
fi

printf '\nInventaire terminé. Une constante remplie depuis un réglage (define(X, get_option(...))) n est pas un secret en dur : lire la ligne.\n'
