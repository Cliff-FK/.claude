# Reprise : audit des skills externes et chantier « site piraté »

Session du 23/09/2026. Cette note et le cahier des charges (`arbitrage-cahier-des-charges.md`) sont dans `~/.claude/suivi/`. Tout le travail est dans `C:\Users\cliff\.claude` (dépôt git, dernier commit utilisateur `12c716f` à 18:38). Seul fichier non commité : `wp-incident-response/scripts/detect.php` (travail partiel, voir « Reste à faire » point 1).

## Fait et vérifié

### 1. Sécurité du setup Claude (point 0)
- `hooks/scripts/guard-write-scope.ps1` : une commande shell qui peut modifier la config de sécurité (settings*.json, write-scope-extra-roots.txt, scripts et hooks.json, sentinelle git) renvoie `ask` (invite de permission native). Trou corrigé : une redirection vers `~/...` n'était pas vue comme une écriture. Détection par nom, au mieux.
- `hooks/scripts/guard-git-destructive.ps1` : la sentinelle `git-destructive-authorized` (écrite par le modèle) est supprimée ; un git destructif renvoie `ask`.
- `hooks/scripts/guard-workflow-budget.ps1` : inchangé (revenu à l'original, à ta demande).
- `settings.json` : `ask` ajouté sur Edit/Write de `settings.json`, `settings.local.json`, `write-scope-extra-roots.txt`.
- `SECURITY-SETUP.md` et `CLAUDE.md` alignés sur la réalité (mode `auto`, liste des hooks, git-destructive ne couvre pas `push --force`).
- Preuve : 18 cas sur 18 conformes (script `hooktest.py`, commandes fabriquées, aucune écriture réelle).

### 2. Refactors
- `skills/handbook/SKILL.md` : frontmatter YAML réparé (description entre guillemets) ; la description complète s'affiche.
- `skills/cliff-stack/skills/design-auditor/` : SKILL.md 2552 → 459 lignes, contenu déplacé à l'identique dans `references/` (non-perte prouvée par script).
- `CLAUDE.md` : règle « termes génériques, aucune liste blanche/noire nommée sans source communautaire ou experte ; les noms propres d'un cas restent dans ses tests ».
- `.claude-plugin/plugin.json` : 24 skills, 14 agents.

### 3. Doctrine WordPress (chaque fait vérifié dans le core 7.1.2 ou une doc officielle)
- Créé `skills/cliff-stack/references/wp-security-controls.md` (partagé par wp-native, wp-plugin-check, wp-incident-response) : contrôles par point d'entrée, durcissement, méthode de revue Confirmé / Piste / Durcissement. Erreurs du dépôt externe corrigées, snippet dangereux exclu.
- `wp-plugin-check` : étape « revue logique » + script `scripts/scan-security-sinks.sh` (MIT, lecture seule). ripgrep installé (winget, 15.2.0).
- `rules/wordpress-php.md` + `wp-native` : piège kebab-case des slugs de presets (`3xl` → `--3-xl`), traductions avant `init`.
- `wp-native/references/` : `editor-iframe.md`, `deprecations.md` (les migrations ne s'enchaînent pas), `perf-backend.md` ; matrice : pagination Query Loop en GET.
- `in-product-upgrade-prompts` : Guideline 5 complète, plus aucun plugin tiers nommé. `wporg-readme-optimizer` : règles de nommage (Guideline 17).

### 4. morph (moteur désormais dans le thème themezero)
- Agents : licensing supprimé, serve + front fusionnés en `morph-serve-front-agent`, 6 agents réécrits, `rules/morph-blocks.md` réécrite. 0 référence orpheline.
- Mémoires `projects/c--MAMP-htdocs-wmki-wp-builder-2026/memory/` : 6 supprimées, 8 corrigées, MEMORY.md cohérent.
- `regression-tester` : reste en Opus (ta décision).

### 5. Skill `wp-incident-response` (site piraté + mises à jour bloquées)
- `skills/cliff-stack/skills/wp-incident-response/` : SKILL.md, 10 références, `scripts/detect.php` (lecture seule, ne charge jamais WordPress, PHP 7.4 à 8.3, mode hors ligne sur dump .sql), tests `unit.php` et `mutations.php`.
- Détection par principes (provenance, capacité du code, comportement, corrélation temporelle) ; signatures seulement pour prioriser. Aucun nom de produit ou de client.
- Résultats : unit 21/21, mutations 20/20 (dont un piratage « inconnu » détecté par la seule provenance), acceptation valream 40/40, sur PHP 7.4 et 8.2.
- Test client sorti de la skill : `Desktop\debug_valream\recette-skill\acceptance-case.php` (charge `tests/lib.php` de la skill, option `--skill=`).

## Reste à faire

1. **Outil à déposer par FTP : écrit et testé le 24/09/2026, non commité.** `scripts/build-drop.php` + `scripts/drop/actions.php` + `scripts/drop/app.php` ; `detect.php` gagne `--exclude`. Doc : `SKILL.md` mode B et `references/access-modes.md`.
   - Testé dans un vrai navigateur sur un faux site local (`php -S`, base MySQL jetable supprimée ensuite) : jeton faux 403 et compté, session, CSRF falsifié 403, accès sans session sans fuite, actions forgées (fichier ou compte hors plan) rejetées, 7 actions exécutées avec effet mesuré sur disque et en base puis annulées (état final identique à la référence), refus si la cible a changé depuis l'analyse, autodestruction à l'expiration, après 10 jetons faux et par le bouton final. Unit 25/25, mutations 20/20 sur PHP 7.4 et 8.2.
   - **Non testé** : remplacement d'un fichier du cœur par l'archive officielle (le PHP de MAMP n'a pas de bundle de certificats pour curl ; l'outil le signale en ILLISIBLE). À exercer sur un PHP avec certificats. Test d'acceptation sur le cas réel : absent de ce poste.
   - **Reste** : critique adversariale indépendante centrée sur la sécurité du fichier déposé ; test automatisé rejouable du parcours web (non écrit).
2. **Vérifier en nouvelle conversation** que la skill `cliff-stack:wp-incident-response` et `morph-serve-front-agent` apparaissent et se déclenchent.
4. **morph et WP 7.1** : le core gère maintenant des styles par écran (`settings.viewport`, `@mobile`/`@tablet` par bloc) et la visibilité par écran (7.0). Tester dans l'éditeur ce qui recoupe le moteur avant toute décision de positionnement.
5. **Non tranché, à tester en UI réelle** : `wp-native` ligne 23 (align wide/full et `supports.layout`).
6. **Doc du dépôt thème incohérente** (non modifiée) : `docs/moteur-responsive/doctrine-moteur.md` place encore `tests/` et `build/` dans le thème ; commentaire obsolète en tête de `save-handler.php`.

## Valream (incident réel, suivi dans l'autre conversation)
- Script recette `livrable/5-recette/ext.php` : le kill automatique vise tout worker php-fpm de plus de 120 s (`etimes` = âge du processus, pas de la requête) ; la date de coupure des comptes (17/07) est postérieure à la plus ancienne trace (fichier témoin du 10/06) : vérifier chaque compte « conservé » créé après début juin.
- La cause du raté « aucun processus suspect » était la troncature de `ps -eo user`, pas php-fpm.
- Restent : rotation de tous les secrets (panneau, base, FTP/SSH, clés d'API), suppression de `httpdocs.old-20260724`, lecture des logs d'accès du 22 au 23/07, audit des sites voisins, décision sur la notification RGPD, mot de passe `admin-wmki` à changer (affiché en clair dans la conversation).
- ACF Pro 6.8.4 : diff possible contre la même version téléchargée depuis le compte éditeur.

## Fichiers de travail (scratchpad de session, temporaires)
`C:\Users\cliff\AppData\Local\Temp\claude\c--Users-cliff-Desktop-debug-valream\26eb6eeb-d07c-4c08-8078-eb02eac7a56e\scratchpad\` : `arbitrage.md` (cahier des charges et amendements), `hooktest.py`, clones des dépôts audités (`audit\`), copie de test du site (`wir\`).
