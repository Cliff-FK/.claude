# Vérification et recette

Re-lancer la détection de bout en bout et **consigner le résultat de chaque contrôle, y compris ceux qui passent**. Aucun statut « ok » n'existe : l'absence de constat ne vaut que pour les vérifications faites.

## Surveillance de réinfection

- **Courte** : `--watch=60`. Le détecteur hache `index.php`, `.htaccess`, `wp-config.php` et les fichiers signalés, attend, re-hache. Une réécriture pendant la fenêtre prouve qu'une persistance tourne encore (retour à [persistence.md](persistence.md), ne pas re-patcher sans tuer le processus).
- **Longue** : `--inventory-out=/hors/docroot/inv.json` maintenant, puis re-hash à 24 et 72 h (par une tâche planifiée ou une nouvelle passe `--baseline=inv.json`). Une divergence tardive signale une persistance manquée.

## Comportement en ligne

- Cache-buster sur trois profils (navigateur, robot d'indexation, référent moteur) : plus aucune divergence (fin du cloaking).
- Un `.php` inexistant renvoie 404 (plus 403 : le verrou est levé) et pas 200 (plus d'attrape-tout).
- Purger le cache/CDN de tout ce qui a été probé de l'extérieur, re-tester jusqu'à `MISS`/404.

## Antivirus hébergeur

Relancer le scan de l'hébergeur pour un avis indépendant, gérer la mise en quarantaine du script d'intervention. Toute exclusion antivirus est décidée et faite par le client, jamais par la skill.

## Recette fonctionnelle (après nettoyage)

Un site désinfecté doit rester utilisable. Vérifier par de **vraies actions**, pas des proxys :

- **Connexion** d'un compte légitime.
- **Mot de passe oublié** : le mail part réellement (surveiller un échec `wp_mail_failed`). Piège : un filtre (`login_errors`, `pre_option_...`) peut **masquer la vraie erreur** ; on mesure l'événement d'envoi, pas seulement l'absence de message d'erreur.
- **Envoi de mail réel** (formulaire de contact, notification), pas une simulation.
- **Formulaires** du site : soumission réelle.
- **Cron** : les tâches légitimes se planifient et s'exécutent.

Si un `.htaccess` de recette porte une authentification (Basic Auth), la traiter comme un bloc identifié : sauvegarde puis retrait chirurgical.

## Matrice minimale à consigner

| Contrôle | Attendu |
|---|---|
| Intégrité du cœur (manifeste) | conforme, ou API injoignable signalée |
| Composants sans référence | listés `NEEDS_HUMAN`, référence éditeur demandée |
| Code à capacité forte (fichiers + base) | 0 `CONFIRMÉ` restant |
| PHP dans uploads / dossiers de données | 0 |
| Comptes | tous nommés et réclamés |
| Options sensibles, cron orphelin | absents |
| Mises à jour | débloquées (filtres/constantes/verrous retirés) |
| Secrets | tous tournés |
| Réécriture (watch court) | aucune |
| Cloaking, verrou 403 | disparus |
| Recette fonctionnelle | connexion, mail réel, formulaires, cron OK |

## Rapport et RGPD

Rapport **trouvé / changé / non fait**, avec les effets de bord (y compris ceux de nos tests). Si des données personnelles ont pu être exposées (comptes, formulaires, journaux contenant IP/emails, commerce), évaluer la notification RGPD : art. 33 (à l'autorité de contrôle sous 72 h), et communication aux personnes concernées selon le risque. Un dossier de journaux de formulaire téléchargeable publiquement est une fuite en soi, à traiter même sans malware.
