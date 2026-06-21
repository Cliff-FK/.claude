---
name: design-system-advisor
description: "Generates professional web layouts right the first time, any stack (HTML/CSS, Tailwind, React, Next, Astro, PHP/WordPress). Detects the project typology (SaaS, marketing/brand site, e-commerce, conversion landing, editorial/portfolio, docs/admin dashboard, or a mix) and applies its expected anatomy, section order, and strong-convention micro-patterns (price/promo display, pricing table, hero, product card, CTA, social proof, forms). Use when asked to create/code/design/build a page, section, layout, hero, landing, pricing, product card, dashboard, checkout — or to improve/redesign the look of an existing render. Triggers: crée/code/conçois une page/section/landing/hero/pricing/carte produit/dashboard, mise en page, layout, design, refais le design. NOT for design auditing/review of an existing render (use design-auditor), NOT for back-end/business logic."
---

# design-system-advisor — produire des mises en page pro du premier coup

> **Réponds toujours en français** (accents complets). Identifiants de code et noms de patterns inchangés.

Ce skill sert à **GÉNÉRER** une mise en page professionnelle adaptée au type de projet. Pour **AUDITER/critiquer** un rendu existant → c'est `design-auditor` (skill distinct, complémentaire). Génération ≠ audit.

## Principe directeur
Le « pro vs amateur » ne tient pas à des effets visuels mais à des **conventions respectées** : la bonne structure pour le bon type de page, et les micro-patterns à convention forte posés correctement. Ce skill encode **ce qui se génère** (anatomie, ordre, micro-patterns) ; il **délègue** les règles atomiques (espacement, contraste, tokens, états) à `design-auditor/references/`.

## Règle non négociable : découvrir AVANT de générer
**Rien en dur.** Avant d'écrire le moindre style, découvrir les tokens RÉELS du projet et générer dans ces tokens (DRY) :
- **WordPress** : `wp_get_global_settings()` → slugs spacing/couleur/typo réels du thème activé (ex. `g-0..g-5`, `c-1..c-6` — jamais des slugs inventés). Blocs via `WP_Block_Type_Registry`.
- **Tailwind** : lire `tailwind.config.*` (échelle d'espacement, couleurs, fonts) → utiliser les utilitaires existants avant toute classe nommée.
- **CSS/autre** : grep les variables CSS (`--color-*`, `--space-*`, `--font-*`) et composants déjà présents → réutiliser, ne pas redéfinir.
- **Perf/LCP = garde-fou** : hero léger, pas de JS lourd pour une mise en page (préférer CSS), images dimensionnées, police self-host si possible.

## Workflow de génération (ordre)
1. **Détecter la/les typologie(s)** (cf. `reference/typologies.md`). Identifier la DOMINANTE + les secondaires.
2. **Découvrir les tokens réels** du projet (règle ci-dessus).
3. **Poser l'anatomie** de la typologie dominante (structure de page + ordre des sections).
4. **Remplir avec les micro-patterns** à convention forte (`reference/micro-patterns.md`) — c'est là que se joue le « du premier coup ».
5. **Si projet mixte** : appliquer les règles de composition (`reference/cas-mixtes.md`) — ce qui reste constant vs ce qui change par zone.
6. **Auto-contrôle** : passer la checklist ci-dessous ; si un audit complet est demandé, renvoyer à `design-auditor`.

## Détection de typologie (raccourci)
| Signal dans la demande | Typologie dominante |
|---|---|
| app, dashboard, connexion, plans, trial, "mon SaaS" | **SaaS** |
| présence, à-propos, services, "site de l'entreprise/marque" | **Vitrine** |
| boutique, catalogue, panier, fiche produit, checkout | **E-commerce** |
| convertir, un seul objectif, "page de vente", inscription | **Landing** |
| blog, article, magazine, portfolio, étude de cas | **Éditorial** |
| documentation, admin, tableau de bord interne, données | **Docs/Admin** |

Détail complet (anatomie + codes esthétiques par typologie) : `reference/typologies.md`.

## Les 5 micro-patterns qui trahissent l'amateur le plus vite
(forme PRO résumée ; détail + erreurs dans `reference/micro-patterns.md`)

1. **PRIX & PROMO** — barré PETIT (~12px, gris) au-dessus, badge « -X% », **prix réel GROS (18-24px) coloré DESSOUS**, proximité <20px. ❌ Amateur : barré-à-gauche / réel-à-droite, même taille, désalignés. (Source : Baymard Institute.)
2. **CTA** — primaire = filled, verbe d'action, fort contraste ; secondaire = ghost/outline. Hiérarchie par taille > remplissage > couleur. ❌ Deux CTA de même poids.
3. **Hero** — headline (value prop, ~10-15 mots) + subhead (~20 mots) + CTA + visuel net. ❌ Headline vague, pas de CTA above-the-fold.
4. **Pricing table** — 3 colonnes, tier « recommended » au milieu mis en avant, toggle mensuel/annuel centré au-dessus. ❌ Tarifs noyés, pas de plan recommandé.
5. **Preuve sociale** — logos clients above-the-fold (grayscale, 5-8) ; au checkout, badges sécurité **près du champ de paiement**. ❌ Témoignages sans nom, badges entassés.

## Pour les règles atomiques → design-auditor (ne pas réécrire ici)
Espacement (grille 8px), typographie (échelle, mesure 65-75 car.), couleur/contraste (≥4.5:1), design tokens, états de formulaire (default/focus/error/disabled + aria), élévation, navigation, microcopy : tout est déjà dans `design-auditor/references/` (`spacing.md`, `typography.md`, `color.md`, `tokens.md`, `states.md`…). Les générer conformes à ces règles, sans les redécrire.

## Pièges
- **Ne pas générer avant d'avoir découvert les tokens** → sinon valeurs en dur qui cassent la cohérence du projet.
- **Ne pas confondre densités** : une landing respire (sections 32-64px) ; un dashboard/checkout est dense. Appliquer la densité de la ZONE, pas une densité unique.
- **Zone transactionnelle (checkout) : la confiance prime sur la persuasion.** Sobriété, signaux de sécurité ; pas de copy survendeur.
- **Mixte ≠ patchwork** : tokens/nav/footer/boutons CONSTANTS partout ; seules densité et ton changent par zone.
- **Ce skill génère ; il n'audite pas.** Pour un verdict sur un rendu existant, passer la main à `design-auditor`.

## Référence (un seul niveau)
- `reference/typologies.md` — anatomie + ordre des sections + codes esthétiques des 6 typologies.
- `reference/micro-patterns.md` — catalogue détaillé (forme PRO + erreur amateur) de chaque composant à convention forte.
- `reference/cas-mixtes.md` — composer plusieurs typologies sans incohérence (constant vs variable par zone).
