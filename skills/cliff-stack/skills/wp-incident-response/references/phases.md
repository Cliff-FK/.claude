# Phases de l'intervention (check-lists)

Ordre non négociable. Chaque action destructive est confirmée par l'humain, une par une. Toute action « bloquée » sans preuve de son effet (un `curl` qui montre le 403, un timestamp, un log) est une affirmation, pas un contrôle.

## 0. Intake

- Domaines et copies (staging, recettes, `*.old`), accès (panneau, FTP, SSH selon le mode), autorisation écrite (**audit seul** vs **remédiation**).
- Qui fournit les archives des extensions/thèmes commerciaux, et depuis quel compte éditeur.
- Fenêtre de maintenance (le site peut-il renvoyer une page de maintenance pendant l'intervention).
- **Confirmer qu'on est sur la machine qui sert le domaine** : un instantané restauré porte le vrai `siteurl`/`home`, qui ne prouvent rien. Comparer l'IP résolue du domaine à l'IP jointe.

## 1. Contenir (si compromission active)

- Maintenance **au niveau serveur**, pas WordPress (une maintenance PHP exécute encore les portes dérobées).
- Bloquer l'exécution PHP dans les dossiers inscriptibles (uploads, caches).
- Couper les connexions sortantes si l'hébergeur le permet (les portes dérobées appellent, exfiltrent, tirent une charge de second étage).
- Suspendre les tâches planifiées non encore lues.
- **Geler les autres sites de l'abonnement** : sur mutualisé, un docroot compromis infecte ses voisins par l'utilisateur système partagé.

## 2. Preuves

- Sauvegarde complète fichiers + base **avant toute action**, hachée, tirée hors du serveur, étiquetée « infectée, ne pas restaurer ». C'est le seul enregistrement de ce que l'attaquant a fait.
- Retirer du docroot les archives de sauvegarde exposées (un dump public est une exposition plus grave que le malware).
- Copier `access.log`, `error.log`, journaux du module de sécurité **avant rotation** : ils disent si une porte a été récupérée, d'où et quand (la différence entre « exposé » et « exploité », que le client demande).

## 3. Détecter

Lancer `detect.php` (mode selon l'accès). **Lire les constats, pas les compteurs.**

## 4. Vecteur

- Croiser la fenêtre d'incident avec les journaux (`--access-log`) : POST vers des scripts hors cœur, premier accès aux fichiers signalés, IP récurrentes.
- La fenêtre part de la **preuve la plus ancienne** (canary `/tmp`, premier compte à signal fort, premier code en base), jamais d'une date de CVE.
- Chemin des journaux : souvent un dossier `logs` voisin du docroot dans le panneau.

## 5. Fermer et tuer

- Retirer le vecteur (extension vulnérable, faille corrigée, identifiants volés révoqués).
- Tuer les processus de persistance sur **signaux forts** (voir [persistence.md](persistence.md)), confirmés par PID.
- **Avant** le nettoyage base : sinon la persistance réécrit `index.php`/`.htaccess`.

## 6. Nettoyer

- **Reconstruire plutôt que nettoyer** : réinstaller tout ce qui a une référence officielle (cœur, extensions du catalogue) ; réinstaller les composants commerciaux depuis le compte éditeur, jamais depuis la copie du serveur ; relire à la main ce qui n'a pas de référence (mu-plugins, drop-ins, code du client).
- Base : simulation puis **validation humaine ligne à ligne**. Comptes suspects supprimés avec réattribution des contenus ; contenus d'exploitation, options injectées, cron orphelin purgés ; caches vidés.
- `.htaccess` : retrait chirurgical, jamais de réécriture complète.

## 7. Rotation de TOUS les secrets

Comptes WordPress (mots de passe, suppression des comptes non réclamés), sels (`wp config shuffle-salts`, invalide toutes les sessions), mot de passe de base (mis à jour dans `wp-config.php` dans le même geste), FTP/SFTP/SSH (supprimer utilisateurs et clés inconnus), panneau, clés d'API et **mots de passe d'application** stockés dans le site, secrets des agents de gestion distante (les reconnecter). Un mot de passe d'application survit au changement de mot de passe.

## 8. Durcir

Voir [hardening.md](hardening.md) et `../../references/wp-security-controls.md` (partagée avec wp-native et wp-plugin-check).

## 9. Vérifier et rapporter

Voir [verification.md](verification.md). Rapport **trouvé / changé / non fait**, effets de bord (y compris ceux de nos propres tests), check-list de notification RGPD (art. 33, 72 h à la CNIL, communication aux personnes selon le risque). Clore proprement : retirer le script/clé d'intervention du serveur, supprimer le dossier de secrets local, confirmer par écrit.

## Ce qu'on ne fait pas (à consigner)

La liste des choses **non faites** est ce qui permet à quelqu'un d'autre de reprendre : composants non vérifiables faute de référence, contrôles système impossibles en hors ligne, sites de l'abonnement non audités, etc. On échoue visiblement, jamais en silence.
