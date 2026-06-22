# Préférences globales — (tous projets)

## Langue
Toujours répondre en français (orthographe complète, accents respectés).

## Sécurité Claude Code (NE PAS désactiver)
Poste en **liberté shell maximale + sécurité par hooks** (`~/.claude/settings.json`, tous projets).
- **Liberté** : `Bash(*)`, `PowerShell(*)`, `Read/Edit/Write(*)`, `WebFetch(*)`, `mcp__*__*`, `enableAllProjectMcpServers`. Aucun prompt attendu pour shell/MCP/web/fichiers du projet courant. **Ne jamais réintroduire de règles `allow` par projet** (inutile, génère des prompts).
- **Vraie barrière = 2 hooks `PreToolUse` globaux** (robustes aux contournements que les deny-list déclaratives ne voient pas) :
  - `hooks/guard-write-scope.ps1` : bloque écriture/destruction hors projet (`$CLAUDE_PROJECT_DIR`) + `c:\tmp` ; destruction système Windows (format, diskpart, registre, rm -rf racine, désactivation de défenses, pipe download→shell) ; MySQL modificateur limité à la DB du wp-config du projet. S'auto-adapte par projet via `$CLAUDE_PROJECT_DIR`.
  - `hooks/guard-secrets-read.ps1` : bloque la lecture shell de secrets (.env, credentials, clés SSH/PEM).
- **Filet déclaratif** : `permissions.deny` global (secrets en Read/Edit, destructeurs système). Priorité `deny > ask > allow`.
- **Sons** : `hooks/play-sound.ps1` joue `sounds/notify.wav` sur validation requise, inactivité et fin de tour (`Stop`).
- **En pratique** : écrire hors projet → lancer ce dossier comme projet (ne pas contourner) ; un hook bloque à tort → corriger le hook, pas le câblage ; règles `allow`/`deny`/`hooks` fusionnent global+projet.

## Style de travail
- Tâche non triviale → analyse dense en 4 phases ; sinon réponse courte.
- Write/Edit sans validation **seulement si** la réponse à apporter est évidente **et** ne modifie pas un code majeur existant ; sinon, valider d'abord.

## Règles de code
- **Découvrir avant de supposer** : sur tout nouveau sujet/projet, identifier d'abord le stack réel (technos, frameworks, conventions, variables et règles du projet en cours) et l'épouser en priorité — privilégier le code, les API et les variables **natifs du projet** plutôt que plaquer un pattern venu d'un autre stack ou présumer un défaut.
- **DRY, sans régressions** : s'appuyer sur le code/fonctions/filters/hooks **du projet en cours**, updater l'existant — pas de doublon.
- **Rien en dur** : pas de nom/chemin/valeur codé en dur quand une découverte dynamique est possible.
- Code moderne, minimaliste, performant. Corriger la **cause profonde**, jamais rustiner. Tests unitaires à chaque fin d'étape — qui vérifient le **comportement** (un test qui passe sans rien prouver est un échec).
- Vigilance LCP, perf, sécurité.
- **Se challenger** : sur une tâche spécialisée, vérifier d'abord qu'un **skill/agent dédié** ne couvre pas mieux le sujet (l'invoquer plutôt qu'improviser de mémoire) ; consulter Context7 pour la doc à jour ; vérifier via Playwright en y exécutant les **vraies actions** nécessaires (clics, saisies, raccourcis réels), jamais une simulation programmatique.

## Rigueur (adversarial, éviter les faux positifs)
- **Réflexe adversarial** : traiter toute conclusion, proposition, diagnostic ou verdict « résolu » comme une **hypothèse** à réfuter (même détaillé, même venant d'un agent), pas à défendre — la confirmer par une mesure empirique directe avant de s'y fier ou d'écrire le fix. Sur enjeu élevé, déléguer la réfutation à un **critique indépendant** (agent à contexte isolé) plutôt que s'auto-valider.
- **Valider par signal sémantique DIRECT**, jamais par proxy (le contenu/attribut réel ciblé, pas une longueur ou un flag approchant) : la plupart des faux bugs viennent d'une sonde imprécise.
- **Vérifier que l'action a eu lieu avant de juger son effet** (mesurer timestamp/log/état) : un « save » qui n'a pas marqué l'état dirty n'a rien déclenché. **Échouer visiblement, jamais en silence** : ne jamais rapporter un succès quand une étape a été sautée ou contournée.
- **Cadrer au bon NIVEAU** : prouver par les usages (grep) où vit le défaut (composant/page/config/framework) ; un élément partagé se corrige au niveau partagé.
