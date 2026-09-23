# Invariants du détecteur (`scripts/detect.php`)

Ce que le détecteur cherche, exprimé en invariants structurels génériques. Aucun nom de produit, de famille de malware ni de domaine : la détection tient sur des variantes jamais vues. Les sources des listes embarquées (fichiers du cœur, statuts, hooks de mise à jour, fonctions-puits, domaines réservés) sont citées en tête de `detect.php`.

## Contraintes dures (invariant B1)

- **Ne charge jamais WordPress ni aucun fichier du site.** `wp-config.php` est lu par `token_get_all`, la base par `mysqli` en SELECT seul ou par lecture du dump. Aucun `include`/`require`, aucun `wp eval-file`, aucune API WordPress. Le test unitaire `U9` le prouve par analyse statique du script lui-même.
- **Aucun préfixe supposé.** Le préfixe réel vient de `wp-config.php` (jeton `$table_prefix`) ou, hors ligne, de la table d'options du dump. Jamais `wp_` par défaut : si le préfixe est introuvable, statut `ILLISIBLE`, pas de repli silencieux.
- **Erreurs jamais avalées.** Un gestionnaire d'erreurs capture les avertissements PHP dans le rapport. Les sondes dont l'échec est attendu (un `gzinflate` sur une donnée non compressée) passent par `wd_probe`, qui rend les avertissements à l'appelant au lieu de les perdre.
- **Aucune capacité destructive.** Le script lit, hache, compare, décode. Il ne supprime, ne déplace, ne tue rien. Le kill, la quarantaine, le retrait de règle sont des gestes humains.

## Décodage générique jusqu'au point fixe

Toute chaîne suspecte est décodée en retirant les couches l'une après l'autre jusqu'à stabilisation : base64 (y compris `strrev`+base64), `gzinflate`/`gzuncompress`/`gzdecode`, hexadécimal, échappements `\xNN`, `urlencode`, `rot13`. À chaque couche, on ne poursuit que si le résultat est imprimable ou re-décompressible, ce qui évite de « décoder » du bruit. Le décodage vaut pour les **littéraux de fichiers** ET les **valeurs de base**. Un puits n'est jamais jugé sur la chaîne brute : on juge le contenu final.

## Analyse par capacité (flux entrée → puits)

Analyse du PHP par jetons, avec propagation des « teintes » à travers les affectations (y compris `foreach`, `list()`, `parse_str`, `preg_match`, `extract`) jusqu'au point fixe, et à travers les portées de fonctions (closures et `use`).

- **Sources non fiables** : `$_GET/$_POST/$_REQUEST/$_COOKIE/$_FILES`, `$_SERVER` sur les clés contrôlables (`HTTP_*`, `REQUEST_URI`, `QUERY_STRING`, `PHP_SELF`, `argv`), `php://input`, `getallheaders`, une lecture réseau, une lecture de base (teinte plus faible), `extract()` d'une entrée.
- **Puits** (source : manuel PHP) : exécution de code (`eval`, `assert`, `create_function`, `preg_replace` motif `/e`), exécution de commande (`system`, `exec`, `shell_exec`, `passthru`, `popen`, `proc_open`, accents graves), appel par variable/chaîne/expression (`$f()`, `"name"()`, `call_user_func`, rappels de `array_map`/`register_shutdown_function`…), écriture de fichier, requête sortante, envoi de courriel, création de compte, écriture d'option sensible, `touch` (antidatage).
- **Assainisseurs** qui coupent la teinte : `intval`, `absint`, `esc_*`, `sanitize_*`, `hash_equals`, `preg_match`, `in_array`, `current_user_can`, `basename`, etc. Un `esc_html($_GET[...])` ne déclenche donc pas (test `U4`).
- **Niveau.** Entrée non fiable OU décodée qui atteint un puits d'exécution/écriture → `CONFIRMÉ`. Puits alimenté indirectement (valeur de base, chemin partiellement contrôlé) → `PISTE`. Le nom d'une fonction reconstruit par concaténation ou `chr()` puis appelé est traité comme l'appel réel (tests `U3`, `M2`).

## Intégrité par provenance

Pour chaque fichier exécutable : `identique` / `differe` / `orphelin` / `sans_reference`, contre la meilleure référence disponible (manifeste officiel du cœur avec locale, manifeste ou archive officielle d'extension à la même version, copie de confiance `--ref`, inventaire antérieur `--baseline`). Un fichier `differe` dans le cœur est `CONFIRMÉ`. Un composant entier `sans_reference` est `NEEDS_HUMAN` : la skill explique comment **dériver** une référence. `API injoignable` est distinct de `pas de correctif` : le premier est `ILLISIBLE`, jamais une absence d'écart.

## Emplacement attendu

L'architecture WordPress attend du code exécutable dans le cœur (`wp-admin`, `wp-includes`, fichiers racine du manifeste) et les extensions (`plugins`, `themes`, `mu-plugins`), plus les drop-ins connus à la racine de `wp-content`. Partout ailleurs (uploads, dossiers de données de `wp-content`, racine servie hors cœur, traductions, `/tmp`), un fichier exécutable est au moins une `PISTE`, même s'il est propre par ailleurs. C'est le principe différentiel pur : un fichier bénin au mauvais endroit est signalé par sa seule provenance (test `M9`). Un fichier de données pur (`return [ ... ]`, vide, `exit` en tête) n'est pas du code.

## Marqueurs (priorisation, jamais conclusion)

Entropie élevée, longues chaînes sans espace, noms de variables en O/0/_/I/l/1, `chr()` en série, `goto` en série, appels dynamiques nombreux, script conçu pour survivre à la requête (`ignore_user_abort` + `set_time_limit(0)`). Deux marqueurs ou plus sur un fichier non déjà `CONFIRMÉ` donnent une `PISTE` « code obfusqué » : à décoder et lire, pas à supprimer sur la seule forme.

## Cloaking (comportement observable)

Trois profils sur la même URL avec cache-buster : navigateur, robot d'indexation, visiteur venu d'un moteur de recherche (source de l'agent d'indexation : documentation officielle du moteur). Une réponse qui **diffère** selon le profil (statut, redirection, hôtes de liens, taille) est la marque du spam SEO. Un `.php` inexistant qui renvoie 403 trahit un verrou par nom ; 200, un attrape-tout.

## Système (uniquement sur le serveur)

Processus par `ps -u USER` (jamais la colonne user tronquée), PPID, `/proc/<pid>/cwd|exe`, fichiers temporaires du propriétaire jugés **par contenu**. Kill proposé sur signaux forts seulement. Crontab et tâches du panneau. En mode hors ligne, ces contrôles sont `NEEDS_HUMAN` (ils exigent le serveur). Détail : [persistence.md](persistence.md).
