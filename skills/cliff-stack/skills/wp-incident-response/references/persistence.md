# Persistance : processus, tâches, points de réentrée

Une porte dérobée qui réécrit `index.php` à chaque nettoyage tourne quelque part, ou se relance. On ferme le vecteur et on tue la persistance **avant** de nettoyer la base, sinon le nettoyage est réécrit.

## Processus (sur le serveur uniquement)

- **Lister par utilisateur système, pas par regex.** `ps -u USER -o pid=,ppid=,etimes=,args=`. La colonne user de `ps -eo user,...` est **tronquée à 8 caractères** : un filtre par nom complet ne trouve rien, un filtre sur les 8 premiers caractères attrape des faux positifs. C'est l'erreur qui a fait conclure à tort « php-fpm » dans le cas d'école. Le parseur tolère l'entête et une colonne `lstart` intercalée (tests `U5`, `E23`).
- **Repli par `/proc`** si `shell_exec` est coupé : lister `/proc/<pid>` du bon UID, lire `stat` (ppid, âge via uptime), `cmdline`, `cwd`, `exe`. Si `/proc` est masqué (hidepid, open_basedir), statut `NEEDS_HUMAN`.
- **Kill sur signaux forts seulement** (jamais l'âge) : `cwd` ou `exe` en `(deleted)` (le fichier n'existe plus sur disque, le processus tourne en mémoire), exécutable dans `/tmp`, `/dev/shm`, `/var/tmp`, ou script rattaché à **PPID 1** (détaché de toute requête ou tâche). `etimes` est l'âge du **processus**, pas de la requête : un pool applicatif peut être vieux sans être suspect (test `U8`). Confirmer **par PID** avant `kill -9`, après avoir copié `cmdline` et l'environnement comme preuve.
- L'arbre du script lui-même et les pools applicatifs normaux sont exclus des suspects (test `U7b`).

## Tâches planifiées

- **Crontab système** de l'utilisateur : toute ligne qui télécharge puis exécute (`curl|wget … | sh/php`), décode (`base64`), ou vise `/tmp`, un fichier caché, `uploads` → `CONFIRMÉ`. Le reste est `NEEDS_HUMAN` (à faire confirmer).
- **Tâches du panneau d'hébergement** : à relire à la main quand `shell_exec` est coupé (le détecteur le signale `NEEDS_HUMAN`).
- **Option cron de WordPress** : arguments encodés/exécutables, ou hook qu'aucun code installé ne déclare (voir [db-artifacts.md](db-artifacts.md)).

## Points de réentrée fichiers

- **Drop-ins** à la racine de `wp-content` (`object-cache.php`, `advanced-cache.php`, `db.php`, `sunrise.php`, `maintenance.php`…) : chargés par le cœur avant les extensions, légitimes sur beaucoup d'hébergements ET emplacements classiques de persistance. Chacun est `NEEDS_HUMAN` : comparer au fichier fourni par l'extension qui l'a posé.
- **mu-plugins** : chargés sans activation, invisibles dans la liste des extensions, sans manifeste public. Chaque fichier est `NEEDS_HUMAN`.
- **`auto_prepend_file`/`auto_append_file`** dans `.user.ini`, `php.ini`, ou `php_value` en `.htaccess` : un fichier exécuté avant chaque script. Vers `/tmp`, `uploads` ou un fichier caché → `CONFIRMÉ` (test `M5`).
- **`wp-config.php`** avec include caché (souvent en fin de fichier ou après de longs espaces).
- **PHP dans `uploads`** ou tout dossier de données ; **fichiers oubliés** (dump `.sql`, archive, `.bak`, outil d'administration de base) exposés dans l'arborescence.
- **`.htaccess`** : handler qui exécute des fichiers non PHP, `ErrorDocument` servi par un script non-cœur, réécriture vers un script qu'aucun composant n'explique, redirection externe conditionnée par le visiteur. Verrou par liste blanche de noms : voir [updates-blocked.md](updates-blocked.md).

## Fichiers temporaires

`/tmp`, `/var/tmp`, `/dev/shm` du propriétaire, jugés **par contenu** (binaire ELF, shebang, `<?php`, gros bloc encodé), pas par nom ni extension. Un binaire ou un script y est au moins une `PISTE` ; du PHP à capacité forte, `CONFIRMÉ`. À copier comme preuve avant suppression, une fois les processus liés tués.
