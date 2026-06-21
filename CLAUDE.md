# Préférences globales — Cliff Rob (tous projets)

## Langue
Toujours répondre en français (orthographe complète, accents respectés).

## Architecture de sécurité Claude Code (IMPORTANT — ne pas désactiver)

Ce poste est configuré en **liberté shell maximale + sécurité par hooks**, valable
pour TOUS les projets (réglé dans `~/.claude/settings.json`). Ne jamais réintroduire
de règles `allow` ultra-spécifiques par projet : c'est inutile et ça crée des prompts.

**Liberté** : `Bash(*)`, `PowerShell(*)`, `Read/Edit/Write(*)`, `WebFetch(*)`,
`mcp__*__*`, `enableAllProjectMcpServers: true`. → Aucun prompt d'autorisation
attendu pour shell / MCP / web / fichiers dans le projet courant.

**Sécurité = 2 hooks `PreToolUse` globaux** (la vraie barrière, robuste aux
contournements que les deny-list déclaratives ne voient pas) :
- `~/.claude/hooks/guard-write-scope.ps1` : bloque écriture/destruction hors du
  projet courant (`$CLAUDE_PROJECT_DIR`) + `c:\tmp` ; bloque destruction système
  Windows (format, diskpart, registre, rm -rf racine, désactivation de défenses,
  pipe download→shell) ; MySQL modificateur limité à la DB du wp-config du projet.
  S'auto-adapte à chaque projet via `$CLAUDE_PROJECT_DIR` (aucune réécriture).
- `~/.claude/hooks/guard-secrets-read.ps1` : bloque la lecture shell de secrets
  (.env, credentials, clés SSH/PEM…).

**Filet déclaratif complémentaire** : `permissions.deny` dans le settings global
(secrets en Read/Edit, destructeurs système). `deny` > `ask` > `allow`.

**Notifications sonores** (hooks `Notification` + `Stop` globaux) : `~/.claude/hooks/play-sound.ps1`
joue `~/.claude/sounds/notify.wav` (cloche synthétisée 480 Hz, ~1,7 s) quand une validation
est requise (`permission_prompt`), en cas d'inactivité (`idle_prompt`), et à la fin du tour (`Stop`).

### Conséquences pratiques
- Écrire hors projet → lancer ce dossier comme projet Claude (ne pas contourner).
- Si un hook bloque à tort, corriger le hook (ne pas retirer le câblage).
- Les règles `allow`/`deny`/`hooks` FUSIONNENT entre global et projet : un projet
  peut ajouter des autorisations spécifiques, jamais besoin de redéclarer le shell.

## Style de travail (rappel)
- Tâche non triviale → analyse dense en 4 phases ; sinon réponse courte.
- Jamais Write/Edit sans validation explicite de l'utilisateur.

## Règles de code (à appliquer par défaut)
- **DRY, sans régressions.** S'appuyer sur le code/fonctions/filters/hooks **du projet en cours** (CMS, librairies déjà présentes). **Update l'existant en priorité — ne pas ajouter du code en double.**
- **Universel, rien en dur** : pas de nom/chemin/valeur codé en dur quand une découverte dynamique est possible.
  - **WordPress** : découvrir les valeurs réelles du **thème activé** AVANT de les utiliser/tester — presets spacing/couleur/typo via `wp_get_global_settings()` (slugs réels, ex `g-0..g-5`/`c-1..c-6`, jamais des slugs inventés), blocs via `WP_Block_Type_Registry`. Ne jamais supposer les slugs WP par défaut (un thème les redéfinit/désactive souvent).
- Code **moderne, minimaliste, performant, efficient**.
- **Corriger la cause profonde des bugs, jamais rustiner.**
- **Tests unitaires à chaque fin d'étape de code.**
- Vigilance **LCP, performances, failles de sécurité**.
- **Se challenger** : confirmer ou modifier sa proposition selon l'objectif ; exploiter les MCP **Context7** (doc à jour) et **Playwright** (comportement réel) à loisir.

## Méthodologie de debug (éviter les faux positifs, gagner du temps)
- **Valider par signal sémantique DIRECT, jamais par proxy** : comparer le contenu/attribut réel ciblé, pas une longueur de chaîne, un flag ou un nom approchant. La plupart des « faux bugs » viennent d'une sonde de test imprécise, pas du code.
- **Un diagnostic est une HYPOTHÈSE** (même venant d'un agent ou très détaillé) : le confirmer par une mesure empirique AVANT d'écrire le fix. Ne jamais coder sur un diagnostic non vérifié.
- **Vérifier que l'action a réellement eu lieu avant de juger son effet** : ex. un « save » qui n'a pas marqué l'état dirty n'a rien déclenché → conclure « le fix échoue » est une erreur de méthode. Mesurer l'effet de bord attendu (timestamp, log, état) d'abord.
- **Cadrer au bon NIVEAU avant de proposer une solution ou de faire trancher** : prouver par les USAGES (grep) à quel niveau vit le défaut (composant / page / config / framework), pas d'après le premier fichier ouvert (qui souffle un angle au hasard de l'ordre de lecture). Un élément partagé se corrige au niveau partagé, jamais chez un seul appelant.
