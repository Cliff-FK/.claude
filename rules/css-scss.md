---
paths:
  - "**/*.scss"
  - "**/*.css"
---

# Invariants CSS (baseline du projet d'abord, zéro déclaration morte)

- **Avant TOUTE règle CSS : établir la baseline réelle du projet** — lire son reset (ex. the-new-css-reset : `all: unset` neutralise DÉJÀ bordure/fond/police/couleur/padding/outline des contrôles), ses tokens (custom properties), ses utilitaires/objets. Un reset défensif plaqué de mémoire (`border:0`, `background:none`, `font:inherit`, `color:inherit`, `padding:0`, `outline:none`) est un DÉFAUT s'il doublonne la baseline.
- **Chaque déclaration doit être prouvable** : si la barrer dans l'inspecteur ne change rien, elle n'a pas le droit d'exister. En cas de doute, tester — pas d'écriture « au cas où ».
- **Jamais de dimension figée quand un mécanisme intrinsèque existe** : `field-sizing`, `fit-content`, `min()/clamp()`, attribut HTML `size` (repli natif) avant toute `width` en dur — une largeur magique est une régression en attente.
- **Valeurs du projet, pas des littéraux** : tokens du projet pour couleurs/espacements/transitions ; fallback var(--x, littéral) seulement si le token peut être absent du contexte.
- **Moderne et non redondant** : `:is()/:where()` pour une définition unique par comportement partagé, propriétés logiques, `:focus-visible` (le focus ne se supprime jamais, il se style). Une valeur répétée 2× = une custom property.
- **Ordre des déclarations en sablier inversé** — dans un bloc, les valeurs se rangent par type : mot-clé pur, fonction (`blur(4px)`), variable (`var(--x)`), valeur avec unité, chiffre nu. Les mots-clés **montent** par longueur de ligne croissante, tout le reste **descend** par longueur décroissante : le bloc dessine un losange. Deux déclarations qui se recouvrent (même propriété, ou shorthand/longhand comme `margin`/`margin-top`, fallback `background`) gardent leur ordre d'origine, la cascade prime sur la forme.
- **Un bloc de déclarations est une suite continue** — aucun commentaire sur sa propre ligne entre deux déclarations (il coupe le bloc et soustrait les déclarations voisines au tri) ; commentaire court en fin de ligne toléré. Spec exécutable et vérification : `skills/cliff-stack/hooks/scripts/css-order.mjs` (`--check` / `--write` / `--audit`), branché en hook `PostToolUse`.
- **Le structurel s'arrête au mécanisme** : jamais inventer d'habillage ni d'affordance (bordures, couleurs, soulignés, alignements décoratifs) — le design appartient au projet/dev ; livrer le minimum fonctionnel et signaler ce qui reste à dessiner.
