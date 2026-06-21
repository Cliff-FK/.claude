---
name: skill-builder
description: Use when the user asks to create, build, design or improve a Claude Code skill on a given subject ("crée/fais/conçois un skill sur X", "un skill spécialisé Y", "à la pointe sur Z", "améliore ce skill"). Combines Anthropic's official skill-authoring doctrine (eval-driven authoring with baseline, progressive disclosure, ≤500-line SKILL.md, third-person description, bundled scripts, multi-model testing, security audit) with a proven house research method (adversarial verification of "doesn't exist" claims, ground-truth over web over memory, parallel fan-out, encode the gotchas not the docs). Produces a global skill under ~/.claude/skills/ by default. NOT pour un sous-agent délégable à contexte isolé (avec ses propres tools restreints) → agent-builder ; ce skill ne fabrique qu'une procédure/connaissance chargée dans le contexte principal.
---

# skill-builder — fabriquer un skill à la pointe (doctrine officielle + méthode maison)

> **Réponds toujours en français** (accents complets). Identifiants de code et noms de fichiers inchangés.

Produire un skill **dense, sourcé, sûr, évalué**, pas une paraphrase de doc. Cible par défaut : **global** (`~/.claude/skills/<name>/`). Cette méthode fusionne les **best-practices officielles Anthropic** (vérifiées en source primaire) et la **méthode de recherche maison** qui a produit nos meilleurs skills. Référence officielle : `Anthropic skill-creator` (`github.com/anthropics/skills`, plugin `skill-creator@claude-plugins-official`) — s'en inspirer / déléguer la boucle d'éval si dispo.

## Principe directeur
Un bon skill **n'encode pas la doc** (déjà en ligne) — il encode **ce que la doc ne crie pas** : pièges, champs/méthodes inexistants, conventions maison, décisions de sécurité. Sa valeur se **mesure** : gain prouvé sur des évals, pas une impression. *« Claude est déjà intelligent — n'ajoute que ce qu'il ne sait pas. »*

## Procédure (ordre)

### 1. Reconnaissance parallèle (fan-out, un seul message multi-tools)
Lancer **ensemble** : (a) découverte de l'existant projet (Grep/Glob/Read), (b) recherche web/officielle, (c) **Context7** si lib/framework/SDK. Ne pas sérialiser l'indépendant.

### 2. Agent adversarial sur tout « ça n'existe pas »
Tout « pas de X officiel / pas d'outil » est une **HYPOTHÈSE**. Lancer un **agent chargé de RÉFUTER** (registres, GitHub, npm, CDN, `llms.txt`, `.well-known`). Ne conclure « n'existe pas » qu'après réfutation honnête.

### 3. Vérité terrain > web > mémoire
Pour toute signature/champ/comportement : (1) **sources/types installés** (`node_modules/**/*.d.ts`, repo officiel à la version épinglée), (2) **Context7**, (3) doc officielle. **Jamais la mémoire.** Souvent un agent explorateur sur les `.d.ts`/CDN. Les bugs coûteux viennent d'une shape devinée.

### 4. Idiomatique au projet
Lire les conventions maison (architecture, nommage, couches, sécurité) et faire parler le skill **dans ces conventions**. Un skill qui ignore l'existant pousse à dupliquer.

### 5. Évals d'ABORD, baseline, puis rédaction (eval-driven — officiel)
1. Faire **échouer** Claude sur des tâches représentatives **sans** skill ; noter les manques précis.
2. Créer **≥3 scénarios** d'éval (cf. `reference/checklist.md` pour le format JSON `query`/`expected_behavior`).
3. **Établir la baseline sans skill** (mesure de référence).
4. Écrire le **minimum** pour combler les manques.
5. Itérer : exécuter les évals, comparer à la baseline. Pour chaque test, **spawner with-skill ET without-skill dans le même tour** (pas les baselines après coup).

### 6. Rédaction — progressive disclosure (officiel)
- **Frontmatter** : `name` kebab-case **gérondif** (`processing-pdfs`), **≤64 car.**, sans "claude"/"anthropic". `description` **3e personne** (« Processes… Use when… »), **≤1024 car.**, what+when+**déclencheurs/mots-clés** réels (FR + termes techniques), volontairement **un peu insistante** (Claude sous-déclenche).
- **SKILL.md = table des matières dense, corps ≤500 lignes** (norme officielle ; viser bien plus court). Objectif, règle non négociable (sourcer), méthode, garde-fous sécurité, **3-5 pièges-clés**, pointeurs, anti-patterns.
- **`reference/*.md`** : tout le volumineux, **références à UN SEUL niveau** depuis SKILL.md (pas de nesting → lecture partielle). TOC en tête si >100 lignes.
- **`scripts/`** : bundler toute opération **déterministe/fragile/répétée** (plus fiable que du code généré, 0 token en contexte). Dire explicitement **exécuter** (`Run scripts/x.py`) vs **lire en référence**.
- Encoder les **pièges vérifiés**, pas la doc. Terminologie **constante**. Chemins en **forward-slash** (jamais `\`). Pas d'info datée (section « old patterns » sinon).
- **Densité sémantique, PAS brièveté lexicale.** Couper remplissage/redites/méta-phrases ; **garder intacts** distinctions fines, invariants, négations critiques (c'est le contenu). **Interdit** : style télégraphique/monosyllabique qui sacrifie une nuance — sous la *token complexity* intrinsèque, la qualité chute. Le levier prouvé du gain de tokens = **directives explicites**, pas la troncature. **Mesure** : jamais au compteur de caractères (proxy) ; un fichier n'est « trop long » que si une éval (§8) montre une instruction diluée/ignorée.
- **Anti-péremption — distinguer stable vs volatil.** Ne jamais figer un fait périssable comme certain (sinon le skill affirmera dans 1 an un fait périmé). Volatil (versions, IDs de modèle, « le plus récent », champs d'API, prix, « n'existe pas encore ») → **re-vérifier au runtime** (terrain/Context7) avant d'affirmer, ou **dater** (« vérifié AAAA-MM ») si la re-vérif est impossible. Stable (principes, architecture) → pas de marquage. Un fait **re-vérifié > daté > récité** ; dates toujours **absolues**. (Renforce « pas d'info datée » + « terrain > web > mémoire ».)

### 7. Sécurité = garde-fou dur (officiel + maison)
- Critères de **refus** propres au sujet (secrets serveur-only, vérif signature, anti-IDOR, validation d'entrée).
- **Auditer le skill produit lui-même** : scripts bundlés + tout **fetch d'URL externe** (injection d'instructions). Traiter `allowed-tools` comme une **élévation de privilège** à justifier. « Installer un skill = installer un logiciel ».

### 8. Valider — déclenchement ET qualité, multi-modèles, session fraîche (officiel)
Mesurer **deux choses distinctes** : (a) le skill **se déclenche** sur les bons prompts (et PAS sur les mauvais) ; (b) la **sortie est correcte** quand il se déclenche. Toujours en **session fraîche** (le contexte d'autoring masque les trous). **Tester sur Haiku, Sonnet ET Opus** si le skill les cible (ce qui suffit à Opus peut manquer à Haiku). Type-check/test si du code est touché. Un skill non validé n'est pas livré.

### 9. Tracer en mémoire
Entrée mémoire (le skill existe + verdict d'enquête clé) pour ne pas tout re-découvrir. Liens `[[...]]` vers les mémoires connexes.

## Anti-patterns à refuser
Paraphraser la doc • conclure « n'existe pas » sans agent adversarial • signatures « de mémoire » sans vérité terrain • écrire avant d'avoir évals + baseline • `description` à la 1re/2e personne ou pauvre en déclencheurs • `name` non-gérondif / avec "claude"/"anthropic" • SKILL.md fourre-tout >500 lignes • références imbriquées (>1 niveau) • chemins `\` Windows • valider seulement le déclenchement (pas la qualité) ou un seul modèle • skill produit sans audit de ses scripts/fetch externes • ignorer les conventions du projet.

## Référence
- `reference/checklist.md` — checklist officielle condensée, format JSON d'éval, squelette de fichiers, chiffres-clés.
