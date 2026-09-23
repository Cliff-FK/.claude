# Faux positifs et pièges de terrain

Méthode : trois portes avant de dire « malveillant » (voir SKILL.md). Toute conclusion est une hypothèse à réfuter par une mesure directe : si je fournis la référence manquante ou si je prouve que l'entrée n'atteint pas le puits, le constat tombe-t-il ?

## Faux positifs que le détecteur évite (et pourquoi)

- **`eval()` sur une valeur de configuration constante** (par exemple un outil qui lit `$table_prefix` par jetons puis `eval` la chaîne littérale) : aucune entrée non fiable n'atteint le puits, donc pas de `CONFIRMÉ`. Vérifié sur le cas d'école (`E12`).
- **`base64_decode()` de licence, d'icône, de données sérialisées** : décodeur sans puits en aval. Le détecteur déroule la chaîne mais ne signale que si le contenu final atteint un puits ou parle d'exécution. Vérifié sur une extension commerciale (`E13`).
- **`eval(` présent dans une chaîne** (message d'un pare-feu applicatif qui refuse les requêtes contenant `eval(`) : c'est un littéral, pas un appel. Vérifié (`E11`).
- **Sortie échappée** : `esc_html($_GET[...])`, `intval(...)`, `hash_equals(...)` coupent la teinte (test `U4`).
- **Fichier de données pur** : `return [ ... ]`, fichier vide, `exit`/`die` en tête (fichiers de cache) ne sont pas du code.
- **`<iframe>` d'intégration du site vers lui-même** (oEmbed interne) : ignoré quand l'hôte est le site ou un sous-domaine. Une iframe cachée vers un tiers reste `CONFIRMÉ`.
- **Bibliothèque de cryptographie, données de règles d'un module de sécurité** : fortes en entropie et en fonctions sensibles, mais couvertes par leur manifeste/référence. D'où l'importance de fournir les références : un composant `sans_reference` reste `NEEDS_HUMAN`, jamais `CONFIRMÉ` sur la seule forme.

## Pièges corrigés par rapport à une méthode naïve

- **Filtre `ps` sur la colonne user tronquée** (8 caractères) : conclut à tort. On filtre par `-u USER` (voir [persistence.md](persistence.md)).
- **Repli silencieux sur le préfixe `wp_`** : si le préfixe réel est introuvable, `ILLISIBLE`, pas de repli.
- **Fonction qui avale les erreurs** : ici tout avertissement est capturé et rapporté.
- **`umask` posé trop tard** : le détecteur pose `umask(0077)` en tête, avant toute écriture (rapport, inventaire).
- **Heuristique du format de hash `$P$` inversée** : sur les versions récentes, le format de hash historique n'a plus le même sens qu'avant ; on ne conclut donc pas « compromis » sur le seul format. Le signal fort retenu est le **md5 brut** (insertion directe en base), pas le format historique.
- **`find … -o -delete`** (priorité des opérateurs qui supprime plus que prévu) : le détecteur ne supprime rien, le risque n'existe pas ; côté remédiation, la skill proscrit les suppressions groupées non validées.
- **Dates de fichiers comme filtre d'inclusion** : un antidatage est courant ; les dates sont une donnée, jamais un filtre.
- **Sonde HTTP à effet de bord** : un fichier « à usage unique » s'auto-supprime quand on l'appelle, et un fetch à travers un cache/CDN crée une copie qui survit à la suppression. On lit les fichiers côté serveur ; en HTTP on ne probe que la question « que voit le public » avec un cache-buster, et on purge le cache après.

## Limites connues du détecteur (à compléter par l'humain)

Ces angles morts sont documentés pour ne pas donner une fausse assurance. Un audit sérieux les couvre à la main.

- **Teinte intra-fichier seulement.** Le suivi entrée→puits ne franchit pas les frontières de fonctions (paramètres, valeurs de retour), les propriétés d'objet ni les variables variables. `function f($x){ system($x); } f($_GET['c']);` n'est pas relié. Relire à la main les composants sensibles, surtout un thème enfant ou maison (sans référence publique).
- **Contrôle vs injection.** Une identité prise dans la requête puis passée en `(int)` à une fonction d'authentification (`wp_set_auth_cookie((int)$_GET['u'])`) n'est pas signalée : le transtypage neutralise l'injection mais pas le détournement d'autorisation. Vérifier tout appel d'authentification dont un argument dépend de la requête.
- **Objets et réflexion.** Les appels via `new ...->methode()`, `ReflectionFunction`, `$objet->methode()` dynamiques ne sont pas suivis.
- **Multisite.** Les tables `wp_2_*` d'un sous-site apparaissent comme non expliquées ; l'option `site_admins` (super-admins) n'est pas contrôlée. Traiter le multisite à la main.
- **Cloaking.** Seule la page d'accueil est testée, depuis l'adresse du serveur : un cloaking sur la langue ou sur une plage d'adresses de robot vérifiée par DNS inverse passe.
- **Références indispensables.** Sans manifeste officiel ni copie de confiance, la plupart des composants passent en analyse complète et la précision baisse. Fournir les archives éditeur des composants commerciaux améliore fortement le signal.

## Composants sans référence publique

Extension ou thème commercial, dossier aléatoire sans en-tête : `NEEDS_HUMAN`. On **dérive** une référence en téléchargeant la **même version** depuis le compte éditeur du client (ou une sauvegarde saine antérieure), puis on compare (`diff --strip-trailing-cr`, même version des deux côtés). Sans copie de référence, on ne conclut pas à l'innocuité : un scan de fichiers propre sur un composant non vérifiable ne prouve rien.
