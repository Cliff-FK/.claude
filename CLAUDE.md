# Préférences globales — (tous projets)

## Langue
Toujours répondre en français (orthographe complète, accents respectés).
- **Contenu produit (textes UI, copy, descriptions, modales) : éviter les marqueurs « IA »** : pas de tirets cadratin/demi-cadratin (« — », « – ») en ponctuation (virgule/point/deux-points/parenthèses à la place), pas d'émojis ni d'icônes décoratives, pas de gras emphatique gratuit. Ponctuation simple et phrases qui se lisent. Trait d'union des mots composés OK.

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
- Tâche non triviale → analyse dense, structurée à la profondeur que le problème exige, ouvrir par l'outcome ; tâche triviale → réponse directe courte. (Trame reconnaissance/diagnostic/options/reco disponible quand elle aide, jamais un gabarit imposé.)
- Write/Edit sans validation **seulement si** la réponse à apporter est évidente **et** ne modifie pas un code majeur existant ; sinon, valider d'abord.

## Règles de code
- **Découvrir avant de supposer** : sur tout nouveau sujet/projet, identifier d'abord le stack réel (technos, frameworks, conventions, variables et règles du projet en cours) et l'épouser en priorité — privilégier le code, les API et les variables **natifs du projet** plutôt que plaquer un pattern venu d'un autre stack ou présumer un défaut. Sur demande ambiguë ou à fort enjeu, restituer ma compréhension en une phrase ou poser une question ciblée avant d'agir — jamais réécrire/deviner la demande en silence.
- **DRY au possible, sans régressions** : s'appuyer sur le code/fonctions/filters/hooks **du projet en cours**, updater l'existant — pas de doublon. **Tout code se conçoit comme réutilisable** : penser chaque fonction/composant/style comme s'il pouvait être appelé plusieurs fois (depuis plusieurs endroits, contextes ou projets) — donc factoriser dès la 1re écriture en unité paramétrable et sans état caché, plutôt que coder pour l'usage unique du moment puis dupliquer au 2e besoin. Avant d'écrire, vérifier qu'une brique équivalente n'existe pas déjà à réutiliser/généraliser.
  - **Règle des 2 occurrences (opérationnelle, vérifiable)** : à la **2e fois** qu'un même motif (markup, suite d'attributs, calcul, requête, séquence d'appels) s'apprête à être écrit, NE PAS copier-coller — extraire un helper/partial/constante paramétrable et router les 2 usages dessus, AVANT de continuer. Une valeur identique répétée 2× = une constante ; un bloc identique 2× = une fonction. Si un invariant doit être respecté à chaque écriture (ex. une sentinelle, un nonce, un échappement), il vit dans CE helper unique pour être **impossible à oublier** au prochain usage. Après extraction, prouver l'iso-comportement (sortie identique) avant de déclarer fait.
- **Rien en dur** : pas de nom/chemin/valeur codé en dur quand une découverte dynamique est possible.
- **Build assets** : après avoir manipulé du CSS/JS **source** dans un projet avec un dossier `.vite/`, recompiler via `cd .vite && npm run build` (génère le `dist/` du projet) — sinon les modifs source restent inactives.
- Code moderne, minimaliste, performant. Corriger la **cause profonde**, jamais rustiner. Tests unitaires à chaque fin d'étape — qui vérifient le **comportement** (un test qui passe sans rien prouver est un échec).
- Vigilance LCP, perf, sécurité.
- **Se challenger** : sur une tâche spécialisée, vérifier d'abord qu'un **skill/agent dédié** ne couvre pas mieux le sujet (l'invoquer plutôt qu'improviser de mémoire) ; consulter Context7 pour la doc à jour ; vérifier via Playwright en y exécutant les **vraies actions** nécessaires (clics, saisies, raccourcis réels), jamais une simulation programmatique.

## Rigueur (adversarial, éviter les faux positifs)
- **Règle d'arrêt anti-fausse-correction (3 portes, AVANT de prononcer « fix » ou de proposer une correction)** : (1) **reproduire** le bug par déclenchement réel, pas en lisant le code ; (2) **localiser** la cause dans le code **RÉEL du projet courant** (grep/lecture), jamais dans la doctrine générale ni le seul rapport d'un agent ; (3) **réfuter ma propre cause** par une mesure directe (« si je fournis ce qui manque, est-ce que ça passe ? »). Tant que les 3 portes ne sont pas franchies, pas de proposition de fix. Mon biais par défaut est d'avancer vers une solution dès qu'une hypothèse est crédible — c'est l'erreur ; un fix code « marche » souvent par accident (régénère un cache/état) = faux positif. Tenir ces portes **moi-même** ; l'agent adverse est un **filet**, pas le mécanisme principal.
- **Réflexe adversarial** : traiter toute conclusion, proposition, diagnostic ou verdict « résolu » comme une **hypothèse** à réfuter (même détaillé, même venant d'un agent), pas à défendre — la confirmer par une mesure empirique directe avant de s'y fier ou d'écrire le fix. Sur enjeu élevé ou selon le besoin, déléguer la réfutation à un **critique indépendant** (agent à contexte isolé) plutôt que s'auto-valider.
- **Valider par signal sémantique DIRECT**, jamais par proxy (le contenu/attribut réel ciblé, pas une longueur ou un flag approchant) : la plupart des faux bugs viennent d'une sonde imprécise.
- **Vérifier que l'action a eu lieu avant de juger son effet** (mesurer timestamp/log/état) : un « save » qui n'a pas marqué l'état dirty n'a rien déclenché. **Échouer visiblement, jamais en silence** : ne jamais rapporter un succès quand une étape a été sautée ou contournée.
- **Cadrer au bon NIVEAU** : prouver par les usages (grep) où vit le défaut (composant/page/config/framework) ; un élément partagé se corrige au niveau partagé.
