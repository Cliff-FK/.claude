# Durcissement post-incident

À croiser avec la référence sécurité partagée du plugin, `../../references/wp-security-controls.md` (consommée aussi par wp-native et wp-plugin-check). Ici, l'angle est post-brèche : refermer ce qui a servi, en **deux couches indépendantes** (serveur + application) pour qu'un changement de configuration ne retire pas silencieusement un contrôle.

## Application (WordPress)

- Cœur et extensions à jour, mises à jour automatiques de sécurité **réactivées** (retirer les filtres/constantes de blocage non justifiés, voir [updates-blocked.md](updates-blocked.md)).
- `DISALLOW_FILE_EDIT` actif (l'éditeur de fichiers de l'admin est un vecteur d'écriture de code). `DISALLOW_FILE_MODS` seulement si les mises à jour passent par un canal maîtrisé.
- Tous les sels régénérés (fait en phase rotation).
- Limitation des tentatives de connexion, 2FA imposée aux administrateurs.
- Énumération des utilisateurs bloquée, XML-RPC désactivé s'il est inutile, chacun en deux couches.
- Supprimer les fichiers d'information de version exposés à la racine.

## Serveur

- Interdire l'exécution de PHP dans `uploads` et les caches (règle serveur, pas seulement `.htaccess` si le serveur sert le statique directement).
- Permissions : `wp-config.php` en 640, aucun fichier en écriture pour tous, aucun dossier en 0777 dans le docroot.
- Propriétaire cohérent (éviter les fichiers créés par l'utilisateur FTP quand PHP tourne sous un autre utilisateur).
- Pare-feu applicatif / module de sécurité de l'hébergeur actif, règles géographiques si le trafic du client s'y prête.
- **Un abonnement par site** quand c'est possible : casser le partage d'utilisateur système qui propage une compromission.

## Vérifier chaque contrôle de l'extérieur

Une règle `.htaccess` ne prouve rien sur un hébergement qui sert le statique par un autre serveur, où les `.php` renvoient 403 mais où le dump de base à côté se télécharge très bien. On teste depuis l'extérieur : un `.php` de sonde renvoie 403/404, un fichier sensible connu renvoie 404, le cache est en `MISS`.

## Ce qui reste au client

Rotation effective des secrets côté services tiers, décision sur les exclusions antivirus (jamais faites par la skill), suppression des anciens docroots (`*.old`) une fois l'analyse close, et le suivi des sites voisins de l'abonnement.
