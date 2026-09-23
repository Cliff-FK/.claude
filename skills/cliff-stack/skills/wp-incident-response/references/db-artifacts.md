# Artefacts de base de données

La base survit à une réinstallation des fichiers. Un nettoyage fichiers-seuls laisse tout ceci en place. Le détecteur lit la base en SELECT seul (mysqli) ou depuis un dump hors ligne, sans jamais supposer le préfixe.

## Code stocké qui s'exécute

Toute colonne dont une valeur, une fois désérialisée et décodée, contient du code atteignant un puits (voir [detection-invariants.md](detection-invariants.md)). Cas typiques, décrits en structure et non par produit :

- **Table dont une colonne porte du code exécuté à chaque requête** par une extension d'exécution de code : une valeur avec `system`/`exec`/`eval` sur une entrée non fiable est `CONFIRMÉ`, la colonne entière est `NEEDS_HUMAN` (chaque ligne active à relire). Le webshell moderne n'utilise pas forcément `eval` : il enchaîne `shell_exec`/`exec`/`system`/`passthru`/`proc_open` derrière un jeton (`hash_equals`), et un second mode exfiltre en JSON. Détecté par capacité, pas par `eval`.
- **Option d'un agent de gestion distante** contenant un journal d'erreur encodé : décoder révèle des tentatives (par exemple une demande d'exécution de code refusée). C'est une preuve de tentative, à dater.
- **Valeur d'option, de theme_mod, de widget** qui finit dans un puits, ou qui contient du balisage actif (`<script>` injecteur, `<iframe>` cachée, `meta refresh`, gestionnaire `on*`).
- **Arguments de tâche planifiée** (`option cron`) encodés ou exécutables ; **hook de cron qu'aucun code installé ne déclare** (tâche orpheline, test `M12`).

## Comptes

- **Fenêtre d'incident** fondée sur la **preuve la plus ancienne** (premier compte à signal fort, première date de code en base, canary /tmp), jamais sur une date de CVE. Passée par `--since=AAAA-MM-JJ` si connue.
- **Signaux forts** (→ `CONFIRMÉ`) : domaine réservé (RFC 2606/6761/6762 : `.invalid`, `.test`, `.local`, `.localhost`, `example.*`), mot de passe en md5 brut (inséré directement en base, pas via WordPress), **sosie** d'un administrateur existant (préfixe + suffixe aléatoire, ou distance de Levenshtein ≤ 2).
- **Signaux de fenêtre** (→ `PISTE`) : compte à privilèges (administrateur, éditeur) créé dans la fenêtre, ou administrateur absent de la liste `--legit-admins` fournie par le client.
- **À confirmer** (→ `NEEDS_HUMAN`) : administrateurs antérieurs à la fenêtre. On ne les supprime jamais sans validation.
- Aussi : `session_tokens` (IP des sessions), `user_activation_key` (réinitialisation en cours), `_application_passwords` (survivent au changement de mot de passe), rôles non-administrateur dotés de capacités d'administration, inscription ouverte avec rôle par défaut privilégié.
- La suppression se fait en **simulation puis validation humaine ligne à ligne**, avec réattribution des contenus.

## Contenus

- **Type de contenu (`post_type`) qu'aucun code installé ne déclare** : artefact d'exploitation probable. `NEEDS_HUMAN` si le code du site n'a pas été analysé en entier (sinon on ne peut pas dire qu'aucun composant ne le déclare).
- **Statut de contenu que le cœur ne définit pas** ; **défacement** repérable au titre même sans `<script>`.
- **Frontière d'ID** : les contenus créés dans la fenêtre forment une plage contiguë ; un contenu d'ID postérieur mais **antidaté** (daté avant la fenêtre) trahit une manipulation.
- **Pic statistique** : une nature de contenu qui explose pendant la fenêtre par rapport au rythme des 365 jours précédents.
- **Contenus créés par des comptes suspects** → `CONFIRMÉ`.

## Orphelins et tables

- **Métadonnées orphelines** (`postmeta`/usermeta rattachées à un contenu/compte inexistant), **relations de termes** vers des objets inexistants, **lignes de table d'extension** (`object_id`/`post_id`) pointant vers des contenus disparus : reste d'un nettoyage partiel ou d'une injection.
- **Table qu'aucune chaîne littérale du code installé ne référence** : reste d'une extension supprimée ou table posée par un tiers. `PISTE` si le code a été analysé en entier, `NEEDS_HUMAN` sinon.
- **Écart d'AUTO_INCREMENT** (compteur très supérieur au nombre de lignes) : signale des insertions massives puis supprimées.

## Options sensibles et secrets

- `siteurl`/`home` non conformes ou divergents, `active_plugins` hors du dossier des extensions ou pointant vers un fichier absent, `template`/`stylesheet` absents du disque.
- **Secrets** (clés, jetons, mots de passe, certificats) : signalés **valeur masquée**, jamais affichés. À faire tourner si la base a pu être lue.
- Verrous et options de mise à jour : voir [updates-blocked.md](updates-blocked.md).

## Corrélation

Les événements datés (création de compte à signal fort, code en base, premier accès à un fichier signalé dans les journaux) sont regroupés : une **grappe** d'au moins deux natures différentes à moins de 15 minutes reconstitue la chronologie de l'attaque (test d'acceptation `E16`).
