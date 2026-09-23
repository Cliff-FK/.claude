# Performance back-end : mesurer avant de corriger

Mesurer d'abord, sur l'URL réelle en cause ; un correctif sans mesure avant/après n'est pas prouvé.

## WP-CLI `profile` (paquet `wp package install wp-cli/profile-command`)
Source : developer.wordpress.org/cli/commands/profile/
- `wp profile stage [--url=<url>]` : temps par étape du chargement (bootstrap, main_query, template).
- `wp profile hook <hook> --spotlight [--url=<url>]` : coût des callbacks d'un hook ; `--all` pour tous les hooks ; `--spotlight` masque les valeurs quasi nulles.
- `wp profile eval '<code>'` / `eval-file` : profiler un bout de code isolé.
- Isoler un composant par comparaison `--skip-plugins` / `--skip-themes` (le comportement change : c'est un indice, pas une preuve).

## WP-CLI `doctor` (paquet `wp package install wp-cli/doctor-command:@stable`)
Source : github.com/wp-cli/doctor-command, make.wordpress.org/cli/handbook/guides/doctor/doctor-default-checks/
- `wp doctor check --all`, `wp doctor list`. Checks par défaut utiles ici : `autoload-options-size` (seuil 900 kB), `constant-savequeries-falsy`, `constant-wp-debug-falsy`, `cron-count`, `cron-duplicates`.

## Autoload (chargé à chaque requête)
- Taille : `wp option list --autoload=on --format=total_bytes` ; plus grosses options : `wp option list --autoload=on --fields=option_name,size_bytes`.
- Site Health alerte au-delà de 800 000 octets (filtre `site_status_autoloaded_options_size_limit`, `wp-admin/includes/class-wp-site-health.php:2708`).
- Depuis 6.6, une option ajoutée sans `autoload` explicite et plus grosse que 150 000 octets n'est pas autoloadée (filtre `wp_max_autoloaded_option_size`, `wp-includes/option.php:1362`) ; valeurs stockées `on`/`off`/`auto`/`auto-on`/`auto-off`.
- Correctif : passer les gros blobs en `autoload` `false`, déplacer le calculé vers transient/cache objet ; ne supprimer une option orpheline qu'après avoir prouvé qu'aucun code ne la lit.

## Query Monitor (en navigateur)
Source : wordpress.org/plugins/query-monitor/
- Requêtes SQL lentes, dupliquées ou en erreur, filtrables par composant responsable ; hooks ; appels HTTP API avec temps et code ; erreurs PHP avec pile.
- Requêtes REST authentifiées et AJAX jQuery : aperçu perf et erreurs PHP dans les en-têtes de réponse.
