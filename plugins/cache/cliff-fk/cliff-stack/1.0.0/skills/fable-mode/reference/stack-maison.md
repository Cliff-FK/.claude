# Stack maison — routage par capacité

But : router vers le bon skill/agent SANS coder de nom en dur (les noms peuvent être renommés). Vérifier le nom réel au runtime avant d'invoquer.

## Comment vérifier au runtime
- Skills : la liste des skills disponibles est fournie en contexte de session (bloc « available skills »). S'y référer pour le nom exact.
- Agents : de même, la liste des types d'agents disponibles est fournie en contexte. Sinon, `ls ~/.claude/agents/` donne les fichiers `.md` installés.
- Ne jamais invoquer un nom de mémoire : confirmer d'abord qu'il existe dans la liste courante.

## Table capacité → où router (juin 2026, à re-confirmer au runtime)

| Besoin | Capacité à chercher |
|---|---|
| Feature/bloc/réglage WordPress ou Gutenberg | skill d'implémentation WP-native |
| Feature/composant/page Astro | skill d'implémentation Astro-native |
| Bug/audit/feature morph-blocks (une seule zone) | agent de la zone concernée (editor, build+cache, serve, front, licensing, signature) |
| Tâche morph-blocks touchant plusieurs zones, ou "pourquoi X ne marche pas de bout en bout" | orchestrateur morph-blocks (dispatche + impose la chaîne admin→cache→front) |
| Investigation morph-blocks générale (repro Playwright + cache DB + code) | agent auditeur morph-blocks |
| Non-régression après un fix morph-blocks (matrice save-paths × viewports) | agent testeur de régression |
| Tracer un bloc WP dans son pipeline de rendu (instrumentation temporaire) | agent traceur de pipeline de bloc WP |
| Cadrage d'un plan de fix/refactor HORS morph-blocks (cause à la source, bon niveau) | agent critique de cadrage |
| Landing/page de vente/copy | skill copywriting landing |
| SEO/contenu/acquisition organique | skill SEO launch |
| Modèle de prix/tiers/WTP | skill pricing strategist |
| Séquences email lifecycle | skill email lifecycle |
| Plan d'acquisition payante | skill paid ads |
| Upsell free→pro DANS l'éditeur Gutenberg | skill in-product upgrade prompts |
| Optimisation readme.txt / classement repo WordPress.org | skill wporg readme optimizer |
| Freemius (code + produit) | skill freemius |
| Audit conformité plugin avant livraison | skill plugin check |
| Vrai save humain éditeur via Playwright | skill wp save UI test |
| Minifier JS/CSS | skill minify assets |
| Audit UI/design/a11y/dark patterns | skill design auditor |
| Recherche multi-sources vérifiée et arbitrée | skill research-arbitrate ou deep-research selon l'ampleur |
| Boucle générateur→critique→vérifieur en worktree | skill generator-critic-verifier |
| Créer/MAJ un skill | skill builder de skills |
| Créer/MAJ un agent | skill builder d'agents |

## Règles de fan-out (Fable)
- Fable excelle en sous-agents async : déléguer les sous-tâches indépendantes et continuer.
- Plafond maison : environ 10 agents par recherche. Ne pas exploser (les workflows challenge×pivots peuvent dériver à 19-21).
- Jamais plus d'un agent Playwright à la fois (navigateur + session admin partagés → faux positifs).
