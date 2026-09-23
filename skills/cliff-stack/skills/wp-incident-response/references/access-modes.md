# Modes d'accès

Un seul détecteur, `scripts/detect.php`, exécutable dans les quatre modes. Il est toujours en lecture seule et ne charge jamais WordPress.

## A. SSH (idéal)

```
php detect.php --root=/chemin/docroot \
  --url=https://site.example \
  --access-log=/chemin/logs/access.log \
  --scope=/chemin/abonnement \
  --json=/hors/docroot/rapport.json
```

Ajouter `--legit-admins=a,b`, `--since=AAAA-MM-JJ` quand ils sont connus, `--watch=60` après nettoyage, `--inventory-out=/hors/docroot/inv.json` pour la surveillance longue.

## B'. Tâche planifiée du panneau, en PHP CLI (préféré sans SSH)

Le cas d'école a été traité sans SSH. Quand le panneau permet une tâche planifiée en **PHP CLI**, c'est le meilleur mode sans SSH : pas de limite de temps HTTP, pas d'auto-exposition.

1. Déposer `detect.php` hors du docroot si possible, sinon dedans (temporairement).
2. Planifier une commande unique :
   `php /chemin/detect.php --root=/chemin/docroot --json=/hors/docroot/rapport-$(date +%s).json`
3. Récupérer le JSON par FTP, supprimer le script.

## B. Script HTTP durci (ni SSH ni tâche CLI)

En dernier recours. Le script s'appelle par le web, protégé et jetable (invariant I2) :

1. Éditer en tête de `detect.php` : `WD_HTTP_TOKEN` (≥ 32 caractères aléatoires) et `WD_HTTP_EXPIRES` (horodatage Unix d'expiration, quelques heures). Sans ces deux valeurs, le script **refuse** de s'exécuter en HTTP.
2. Déposer par FTP. Le nom doit passer un éventuel verrou `.htaccess` par liste blanche : sinon le retirer d'abord (chirurgical).
3. Appeler en **POST** avec l'en-tête `X-Detect-Token: <jeton>` (ou le champ POST `token`). Paramètres utiles en POST : `since`, `legit-admins`, `url`, `access-log`, `offset`, `max-seconds`, `watch`, `prefix`, `scope`.
4. Le script pose `register_shutdown_function(unlink)` **dès le chargement** : il s'auto-supprime même en cas d'erreur. Aucune capacité destructive en mode diagnostic.
5. Gros site : découper avec `--offset`/`--max-seconds`, relancer à l'offset rendu (le rapport le donne). `max_execution_time` est lu et respecté.

Limites HTTP fréquentes : `open_basedir`, `shell_exec` coupé, `/proc` masqué → les contrôles concernés passent `ILLISIBLE`/`NEEDS_HUMAN`, jamais « ok ».

## C. Hors ligne (dump + fichiers copiés)

```
php detect.php --root=/copie/docroot --sql=/copie/dump.sql --offline
```

- Copier fichiers **et** dump du serveur. Les contrôles système (processus, cron, `/tmp`, dates ctime) sont alors `NEEDS_HUMAN` : ils exigent le serveur.
- Base seule : `php detect.php --sql=dump.sql` (préfixe déduit du dump, ou `--prefix=`).
- Dump volumineux : le mode C peut être délégué à un agent d'analyse en lecture seule, à contexte isolé (invariant M4, optionnel).
- Antivirus local : le dossier de preuves peut déclencher une mise en quarantaine. **Ne jamais créer d'exclusion soi-même** : le signaler à l'utilisateur, qui décide (invariant I3, I8).

## Périmètre = abonnement / utilisateur système

`--scope=RACINE` trouve chaque installation WordPress sous la racine et les analyse toutes (recettes, copies, `*.old`). Un ancien docroot partage l'utilisateur système, donc l'exposition. Prouvé par le cas d'école : la recette était infectée, la prod saine.

## Références de confiance

- `--ref=/copie/saine` : diff de tout ce qui n'a pas de manifeste officiel contre une copie de confiance.
- `--ref=wp-content/plugins/xxx=/copie/version-editeur` : une extension commerciale contre l'archive de la **même version** du compte éditeur.
- `--baseline=inventaire.json` : un état antérieur sain (produit plus tôt par `--inventory-out`).
- `--no-network` : aucun appel aux API officielles ; sans référence, les composants passent `NEEDS_HUMAN` (jamais « ok »).
