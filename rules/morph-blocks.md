---
paths:
  - "**/wp-content/themes/*/includes/core/constants.php"
  - "**/wp-content/themes/*/includes/core/signature.php"
  - "**/wp-content/themes/*/includes/core/save-handler.php"
  - "**/wp-content/themes/*/includes/core/render-mutate.php"
  - "**/wp-content/themes/*/includes/core/runtime-serve.php"
  - "**/wp-content/themes/*/includes/core/supports-rehydrate.php"
  - "**/wp-content/themes/*/includes/core/css-classify.php"
  - "**/wp-content/themes/*/includes/core/css-allowlist-extend.php"
  - "**/wp-content/themes/*/includes/core/viewport.php"
  - "**/wp-content/themes/*/includes/core/viewport-state.php"
  - "**/wp-content/themes/*/includes/core/variant-sources.php"
  - "**/wp-content/themes/*/includes/core/support.php"
  - "**/wp-content/themes/*/includes/core/compile.php"
  - "**/wp-content/themes/*/includes/core/editor.js"
  - "**/wp-content/themes/*/includes/core/preSave-builder.js"
  - "**/wp-content/themes/*/includes/core/store.js"
  - "**/wp-content/themes/*/includes/themes/prepaint/**"
  - "**/wp-content/themes/*/includes/admin/responsive/**"
  - "**/wp-content/themes/*/includes/addons/listview-bullets/**"
  - "**/wp-content/themes/*/includes/addons/preview-sync/**"
  - "**/wp-content/themes/*/includes/addons/viewport-switch-overlay/**"
---

# Moteur responsive morph (dans le thème) : une modif locale = risque cross-zone

- **Portée** : les chemins ci-dessus sont les fichiers du moteur relevés le 23/09/2026 (un fichier ajouté au moteur s'ajoute ici) ; la règle ne vaut que si le fichier appartient bien au moteur de variantes par écran (fonctions `morph_blocks_*`, constantes `MORPH_BLOCKS_*`, clés `_morph_tablet` / `_morph_mobile`). Le moteur vit dans le thème depuis le 08/09/2026 ; l'ancienne extension `morph-blocks`, sa couche de licence et son build gratuit n'existent plus. Le préfixe `morph_blocks_` est un espace de noms conservé, pas la trace d'une extension.
- **Le dépôt fait foi** : lire avant d'écrire le `CLAUDE.md` racine du projet, `includes/CLAUDE.md` du thème, les règles projet `.claude/rules/` (section « moteur responsive ») et la doc du moteur (chercher `morph_blocks_` dans le dossier `docs/` du projet). Arborescence, contrats (parité de signature, trois listes de sources, deux canaux d'adaptation, `SCHEMA_VER`), oracles et build y sont décrits : ne rien en recopier ici.
- **Routage** : tâche multi-zone ou « pourquoi X ne marche pas de bout en bout » → agent `morph-orchestrator` ; investigation localisée → `morph-blocks-auditor` ou l'agent de zone (`morph-editor-agent`, y compris l'écran de réglages responsive, `morph-build-cache-agent`, `morph-serve-front-agent`, `morph-signature-contracts-agent`) ; non-régression → `regression-tester`. Pas pour une retouche triviale mono-fichier.
- **Jamais « résolu » sans chaîne admin → cache → front validée** (vrai save UI, clic réel) et les oracles du projet au vert ; tout diagnostic reste une hypothèse jusqu'à mesure directe.
- **Jamais deux agents à navigateur en parallèle** (session unique) : sérialiser tout fan-out incluant `regression-tester` ou un agent de zone équipé de Playwright.
