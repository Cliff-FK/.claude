# Arbitrage : skills externes vs setup ~/.claude + cliff-stack (brouillon à réfuter)

## 0. Sécurité du setup (priorité 0, décision utilisateur car touche les hooks en `ask`)
- guard-write-scope.ps1:45 ajoute tout ~/.claude en écriture shell libre, et :52-58 lit write-scope-extra-roots.txt (auto-élargissable). Les sentinelles git-destructive-authorized / agents-max-user-ok sont écrites par le modèle.
- Proposition : dans guard-write-scope, les écritures shell vers hooks/**, settings*.json, write-scope-extra-roots.txt et fichiers sentinelles renvoient `permissionDecision: "ask"` ; remplacer le mécanisme sentinelle par `ask` natif (doc hooks).
- Aligner CLAUDE.md:8 et SECURITY-SETUP.md (mode réel `auto`, 7 hooks).

## 1. AJOUTER : skill maison `cliff-stack:wp-incident-response` (réécrite, aucune copie AGPL)
Sources : squelette panstemon (MIT, 8 phases, pièges terrain) + doctrine aditya réécrite (graphe de réinjection, porte anti-FP, cloaking) + méthode fp-check (ToB, contenu seulement) + cas valream comme jeu d'acceptation.
Modes d'accès (le cas valream s'est fait SANS SSH, par script PHP déposé) :
- A. SSH + WP-CLI : `wp eval-file detect.php` ;
- B. Script PHP déposé, jeton, simulation par défaut, auto-suppression (nom compatible whitelist .htaccess si verrou lock360) ;
- C. Hors-ligne : dump SQL + copie des fichiers (analyse locale, Defender en exclusion sur le dossier de preuves).
Un SEUL détecteur PHP en lecture seule (DRY) exécutable dans les 3 modes, sortie JSON, statuts CONFIRMÉ / PISTE / DURCISSEMENT / NEEDS_HUMAN.
Détecteurs exigés par valream :
- checksums core (avec locale) + plugins ; les plugins intègres ne sont PAS une preuve d'innocuité (code-snippets) ;
- décodage systématique des littéraux base64 (fichiers ET options) avant tout grep d'IOC ;
- audit du contenu de TOUS les .htaccess (FilesMatch Deny php + whitelist = lock360) + test live « .php inexistant → 403 » ;
- mtime jamais utilisé comme filtre d'inclusion (timestomping) ;
- processus : `ps -u <user>` (jamais filtrage regex sur colonne user tronquée), PPID 1, etimes long, `/proc/<pid>/cwd|exe (deleted)` ; /tmp, /dev/shm, /var/tmp du propriétaire ;
- tables qui exécutent du code : snippets, wpcode, options d'exécution ; grep du dump entier ;
- admins : liste blanche + domaines .invalid/.local, sosies, fenêtre temporelle ; session_tokens IP ; user_activation_key ;
- artefacts de contenu : post_type anormaux (customize_changeset, request statut non standard, oembed_cache), frontière d'ID, postmeta orphelines / post_id fantômes, tables SEO (Yoast) par plage created_at, défacement sans <script> ;
- corrélation temporelle (install plugin ↔ création admin ↔ snippets) ;
- préfixe de table réel (jamais wp_), erreurs jamais avalées.
Vérification : surveillance de réinfection (hash index.php/.htaccess ≥ 20-60 s) ; cache-buster + X-Cache-Status ; ordre : fermer le vecteur + tuer le processus AVANT nettoyage DB ; rotation de tous les secrets exfiltrés (Plesk .psa.shadow, DB, SSH, clés API) ; ancien docroot (httpdocs.old-*) supprimé.
Section « mises à jour bloquées » : .maintenance, verrou core_updater.lock, FS_METHOD/DISALLOW_FILE_MODS, permissions/propriétaire, disque, .htaccess qui 403 admin-ajax/wp-cron/update (lock360 !), requêtes sortantes api.wordpress.org.
Pièges corrigés de panstemon : find -o -delete, fallback wp_ silencieux, hit() qui avale les erreurs, umask tardif, heuristique $P$ post-6.8.
Tests : E1-E17 exécutés en local sur valream (dump + importvalream, fixtures référencées par chemin, pas copiées dans ~/.claude à cause de Defender) ; E18-E22 en tests unitaires sur la logique (sortie ps réelle L2135-L2145, utilisateur > 8 caractères).
Garde-fous propres (les hooks ne protègent pas une prod distante) : lecture seule par défaut, confirmation par action destructive, sauvegarde vérifiée (date du dump, pas du marqueur).

## 2. ADAPTER dans l'existant (pas de nouvelle skill)
- wp-native/references/security-controls.md (depuis wpsec, erreurs 2-8 corrigées, snippet rest_endpoints EXCLU) ; pointeur depuis wp-native/SKILL.md:42.
- wp-plugin-check : étape « revue logique » + classement Confirmé/Piste/Durcissement + scan-security-sinks.sh (MIT, lu, lecture seule) ; étendre aux thèmes (idée Lonsdale wp-security-audit).
- rules/wordpress-php.md + wp-native : piège kebab-case des slugs de presets (vérifié) ; traductions avant init ; ligne durcissement wp-config.
- wp-native/references : editor-iframe (WP 7.1 toujours iframé), deprecations, interactivity, perf backend (wp profile/doctor/autoload), à vérifier par Context7 avant écriture.
- block-save-render-matrix.md:143 : pagination Query Loop en GET (vérifié) ; wp-native:23 (align ↔ supports.layout) à TESTER avant toute modif.
- in-product-upgrade-prompts : Guideline 5 complète ; wporg-readme-optimizer : naming rules.
- Signalement stratégique à morph-orchestrator : styles responsive natifs WP 7.1 (settings.viewport) vs morph-blocks.

## 3. REFACTOR local
- handbook/SKILL.md:3 : description entre guillemets (YAML invalide, vérifié).
- design-auditor : 2552 lignes → SKILL.md < 500 + references/.
- Plus tard, à tester : `paths:` des skills vs rules déclencheuses ; `timeout` explicites.

## 4. NE PAS METTRE
respira (MCP payant + télémétrie silencieuse) ; code/signatures d'aditya (AGPL, FP massifs, Defender) ; installation des skills WordPress officielles (collision de routage avec wp-native, chemins cassés) ; Lonsdale en bloc ; wp-env/playground/wpds/patterns/abilities ; superpowers (doublon CLAUDE.md) ; méga-collections ; plugins ToB installés (hooks Stop globaux) ; Vouch comme barrière.

## 5. Hygiène
Supprimer les clones du scratchpad (fichiers bloqués par Defender).
Rappeler à l'utilisateur les actions ouvertes sur valream : rotation secrets, suppression httpdocs.old-20260724, et le diagnostic erroné « php-fpm » (vraie cause : troncature ps).

---
# AMENDEMENTS VALIDÉS (prévalent sur le brouillon ci-dessus)

## Après critique adversariale
- B1 : le détecteur ne charge JAMAIS WordPress ni aucun fichier du site (pas de wp-load, pas de `wp eval-file`, pas d'include). wp-config lu par token_get_all, SQL direct mysqli, aucune API WP. Mode A = `php detect.php` via SSH. Test : assertion « aucun include/require d'un chemin sous la racine du site ».
- B2 : invariants génériques, pas signatures valream : décodage itératif multi-schémas (base64, gzinflate/gzuncompress, str_rot13, hex \x, chr() concat, strrev, urldecode) jusqu'au point fixe ; puits dynamiques (eval, assert, create_function, preg_replace /e, include/require de chemin non constant, appel de fonction par variable, call_user_func sur entrée) ; entropie / longues chaînes ; toute valeur DB qui finit dans un puits (snippets, wpcode, insert-headers, custom css/js, widget_* avec script/iframe, theme_mods, option cron avec hook sans code, active_plugins hors plugins/, siteurl/home/template/stylesheet, wp_user_roles, _application_passwords) ; persistance .user.ini/php.ini auto_prepend_file, php_value en .htaccess, drop-ins (object-cache, db, advanced-cache, sunrise), wp-config injecté, PHP dans uploads, crontab + tâches planifiées panel, fichiers oubliés (cloner.php, adminer, *.sql, *.bak) ; tout .htaccess qui touche handlers/accès PHP/ErrorDocument/rewrites vers non-core = PISTE ; cloaking par 3 UA (navigateur, Googlebot, référent Google) ; agents distants (ManageWP worker, MainWP child, InfiniteWP, Jetpack) : clés + décodage des journaux d'erreur ; surveillance réinfection courte (20-60 s) ET longue (24-72 h via tâche planifiée/antivirus hébergeur).
- Fixtures de mutation synthétiques (autre encodage, autre plugin d'exécution, autre nom de verrou, persistance mu-plugin/cron/.user.ini, JS dans widget) : chacune doit être détectée. Données client jamais copiées dans ~/.claude.
- I1 : modes B' (tâche planifiée Plesk/cPanel en PHP CLI, préféré sans SSH), B HTTP découpé/reprenable ; open_basedir / shell_exec désactivé / /proc illisible → statut ILLISIBLE → NEEDS_HUMAN, jamais ok ; max_execution_time.
- I2 : script déposé : jeton en en-tête/POST, expiration codée en dur, register_shutdown_function(unlink) dès le chargement, aucune capacité destructive en mode diagnostic.
- I3 : pas de signatures en clair (antivirus local) : heuristiques ; IOC éventuels stockés encodés ; toute exclusion antivirus = action manuelle de l'utilisateur, signalée comme affaiblissement.
- I4 : périmètre = abonnement / utilisateur système (tous docroots, recettes, *.old, bases). PROUVÉ par valream (recette infectée, prod saine).
- I5 : plugins premium / thèmes / dossiers aléatoires sans référence → NEEDS_HUMAN, diff contre zip éditeur fourni.
- I6 : phase vecteur avec logs d'accès (Plesk : /var/www/vhosts/system/<domaine>/logs/access_ssl_log).
- I7 : copie de preuves hachée hors serveur AVANT action ; quarantaine hors docroot ; checklist notification (RGPD art. 33, 72 h) et communication client.
- I8 : antivirus hébergeur (Imunify360) : lire rapport, relancer après, gérer la mise en quarantaine du script.
- M2 : ne pas étendre wp-plugin-check aux thèmes ; référence de scan de puits partagée.
- M3 : référence sécurité PARTAGÉE au niveau plugin : ~/.claude/skills/cliff-stack/references/wp-security-controls.md, consommée par wp-native, wp-plugin-check, wp-incident-response.
- M4 : agent d'analyse lecture seule à contexte isolé pour le mode C (dump volumineux) : optionnel.
- Point 0 : fermer aussi la voie Write/Edit ; sentinelles remplacées par permissionDecision "ask".

## Après la suite de la conversation valream
- Mises à jour auto désactivées par le thème (auto_update_core __return_false) = facilitateur du vecteur : détecter filtres auto_update_*, automatic_updater_disabled, constantes AUTOMATIC_UPDATER_DISABLED / WP_AUTO_UPDATE_CORE / DISALLOW_FILE_MODS ; section « mises à jour bloquées ».
- Recette fonctionnelle post-nettoyage : connexion, mot de passe oublié, envoi mail réel (wp_mail_failed), formulaires, cron ; piège : filtres (login_errors) qui masquent la vraie erreur.
- .htaccess : jamais de réécriture complète ; retrait chirurgical des blocs identifiés + sauvegarde (Basic Auth recette).
- Comptes : fenêtre temporelle fondée sur la preuve la plus ANCIENNE (ex. canary /tmp 10/06), pas la date CVE ; simulation + validation humaine ligne à ligne.
- /tmp : détection par contenu.
- Obfuscation : heuristique noms de variables en O/0/_ (pas de noms exacts).
- API wordpress.org injoignable (HTTP/2) ≠ « pas de correctif » : statut ILLISIBLE.
- Tests : copie fraîche à chaque exécution + hash de l'état initial.
- Kill : seulement sur signaux forts (cwd/exe (deleted), PPID 1 hors php-fpm, script hors docroot), confirmation par PID ; etimes = âge du processus, pas de la requête.
- Aucun secret affiché dans le chat/rapport.
- diff --strip-trailing-cr ; comparer un plugin à la MÊME version.
