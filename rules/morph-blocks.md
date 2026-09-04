---
paths:
  - "**/wp-content/plugins/morph-blocks/**"
---

# morph-blocks — pipeline multi-zones (une modif locale = risque cross-zone)

- **Arborescence** : `includes/core/` (pipeline + ses moitiés JS, requis en ordre explicite), `includes/addons/<unité>/` et `includes/admin/<unité>/` (chargées par SCAN), `licensing/`. Le **`CLAUDE.md` du plugin fait foi** : le lire avant de créer ou déplacer un fichier. Le JS vit avec le PHP qui l'enqueue, jamais dans un dossier d'assets séparé.
- **Toute feature neuve naît verticale** : un dossier avec ses moitiés (PHP, JS, `*.asset.php`, CSS), sa moitié payante sous `premium/`. Rien à câbler : le loader et `compile.php` scannent. Un `*.asset.php` déclare handle, deps, translations et gate.
- **Déplacer un JS = rejouer `npm run i18n` dans le MÊME geste** (les `.json` sont indexés sur le md5 du chemin ; un chemin changé les orpheline sans erreur, l'anglais s'affiche).
- **Oracles de déplacement** : `tests/fingerprints.php` (empreinte avant/après) et `tests/module-gates.php` (axe déclenchement) en plus de `matrix.php`.
- **Source vs build** : éditer la source (`includes/`, `licensing/` + JS source), **jamais `build/dist/**`** (sortie générée).
- **Constantes = source de vérité** : `includes/core/constants.php` (SCHEMA_VER, meta keys, markers) — jamais de mémoire. Parité **PHP↔JS byte-for-byte** ; tout changement de payload/sig/meta-key → bump `MORPH_BLOCKS_SCHEMA_VER`.
- **Zones** (editor / build+cache / serve / front / licensing / signature) reliées par la signature `pos_<hex>` + cache 1-ligne/post : **signaler les contrats cross-zone (seams) AVANT de changer**.
- **Selon le besoin** : tâche cross-zone ou « pourquoi X end-to-end » → agent `morph-orchestrator` ; investigation d'une zone localisée → `morph-blocks-auditor` / l'agent de zone. **Pas pour une retouche triviale mono-fichier.**
- **Jamais « résolu » sans chaîne admin→cache→front validée (vrai save UI, clic réel)** ; tout diagnostic = hypothèse jusqu'à mesure directe.
- **Jamais deux agents Playwright en parallèle** (session navigateur unique) : sérialiser tout fan-out incluant `regression-tester` ou un agent de zone à navigateur.
