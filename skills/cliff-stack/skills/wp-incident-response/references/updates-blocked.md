# Mises à jour bloquées

Des mises à jour automatiques coupées sont un **facilitateur** du vecteur (le cœur et les extensions restent vulnérables), pas un simple désagrément. Le détecteur traite ce sujet comme une famille de constats à part, dont beaucoup se recoupent avec la persistance.

## Ce que le détecteur signale

### Filtres (souvent cachés dans un thème ou un mu-plugin)
`auto_update_core`, `auto_update_plugin`, `auto_update_theme`, `auto_update_translation` renvoyant faux ; `automatic_updater_disabled` ; `allow_(major|minor|dev)_auto_core_updates` ; `file_mod_allowed` ; `pre_site_transient_update_(core|plugins|themes)` qui vide la liste des mises à jour ; `pre_http_request` qui intercepte l'API. Le détecteur localise ces filtres **par fichier** (seconde passe légère) quand ils renvoient une valeur constante de blocage. Dans le cas d'école, trois `auto_update_*` étaient posés par le thème (test `E10`).

### Constantes
`AUTOMATIC_UPDATER_DISABLED`, `WP_AUTO_UPDATE_CORE` (à `false`/`0`), `DISALLOW_FILE_MODS`, `FS_METHOD` forcé, `WP_HTTP_BLOCK_EXTERNAL`. Dans `wp-config.php` ou définies ailleurs (signalé avec le fichier). `DISALLOW_FILE_EDIT` est du bon durcissement ; son absence est un `DURCISSEMENT` à recommander.

### Base
`auto_update_core_major|minor|dev` à `disabled` ; verrous `core_updater.lock`, `auto_updater.lock` (au-delà de 15 min sans mise à jour en cours, un verrou resté en place bloque tout).

### Système de fichiers et réseau
Fichier `.maintenance` présent (bloque le site ET les mises à jour) ; moins de 200 Mo libres ; dossier `wp-includes`, `wp-admin`, `wp-content/{plugins,themes,upgrade}` non inscriptible par l'utilisateur PHP ; joignabilité de l'API officielle. **API injoignable n'est pas « pas de correctif »** : c'est `ILLISIBLE`.

### Verrou serveur
Un `.htaccess` qui interdit l'exécution de PHP **sauf une liste blanche de noms** bloque `wp-admin`, `admin-ajax`, `wp-cron` et donc les mises à jour, en plus de servir de porte pour les noms autorisés. Le détecteur teste la règle contre une sonde `.php` inexistante et liste les noms autorisés étrangers au cœur (tests `E2`, `M6`, cloaking en ligne). Le retrait est **chirurgical** (bloc par bloc, sauvegarde avant), jamais une réécriture complète.

## Diagnostic pas à pas quand une mise à jour échoue

1. **Fenêtre serveur, pas WordPress** : lire l'erreur exacte (« Download failed », « Could not create directory », « Could not copy file ») via le journal de débogage ; le test « mises à jour en arrière-plan » de la santé du site donne souvent la cause.
2. **Constantes et filtres** : les constats du détecteur ci-dessus. Un filtre dans un mu-plugin ne se voit pas dans la liste des extensions.
3. **Système de fichiers** : permissions, propriétaire (un fichier créé par l'utilisateur FTP quand PHP tourne sous un autre utilisateur), attribut immuable, quota, dossier `upgrade` inaccessible.
4. **Verrous** : `.maintenance`, `core_updater.lock`, `auto_updater.lock`.
5. **Réseau** : joignabilité de `api.wordpress.org` / `downloads.wordpress.org` (pare-feu sortant, DNS, `WP_HTTP_BLOCK_EXTERNAL`, verrou `.htaccess`).
6. **Composant hors catalogue** : une extension commerciale se met à jour via une licence valide côté éditeur ; sans elle, le bouton échoue même sur un site sain. Ce n'est pas une infection.

Après incident, on rétablit les mises à jour (retirer les filtres et constantes de blocage non justifiés, corriger droits et propriétaire) et on met le cœur et les extensions à jour, une fois le vecteur fermé.
